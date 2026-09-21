# Полный перевод хранения данных и настроек на собственные таблицы D7

## Цель

Убрать зависимость от Highload-блоков там, где это вредит производительности и поддержке, и перейти на собственные таблицы через **Bitrix D7 ORM (`DataManager`)** с индексами, пакетной записью и кэшированием. Настройки — в `COption`.

## Проверено в вашей версии Битрикса

- `Bitrix\Main\ORM\Entity::createDbTable()` — создание таблицы из карты сущности ([`bitrix/modules/main/lib/ORM/Entity.php`](../../../bitrix/modules/main/lib/ORM/Entity.php)).
- Поля: `IntegerField`, `StringField`, `TextField`, `BooleanField`, `DatetimeField`, `EnumField`, `FloatField`, `JsonField`, `ArrayField`.
- `Bitrix\Main\DB\Connection::createIndex()` — создание индексов, переопределён для MySQL и PgSQL.

## Целевая модель данных

Префикс таблиц: `keyup_cleartrafic_`. Пространство имён классов: `Keyup\Cleartrafic\Model`, каталог `lib/model/`.

| Сущность | Таблица | Назначение |
|---|---|---|
| VisitTable | `keyup_cleartrafic_visit` | журнал визитов, горячие данные |
| BlackListTable | `keyup_cleartrafic_black_list` | чёрный список IP |
| GrayListTable | `keyup_cleartrafic_gray_list` | серый список IP |
| MaskTable | `keyup_cleartrafic_mask` | маски подсетей |
| RefererTable | `keyup_cleartrafic_referer` | разрешённые рефереры |
| EmailTable | `keyup_cleartrafic_email` | адреса оповещений |
| FormLogTable | `keyup_cleartrafic_form_log` | лог отправок формы, антиспам |

Настройки (время показа капчи, ключи SmartCaptcha) — только `COption`, HL-блоки `OptionsModule` и `OptionsEmail` не нужны.

### Схемы

`keyup_cleartrafic_visit`:
- `ID` Integer, primary, auto
- `IP` String 45, required, индекс
- `PAGE` String 500
- `REFERER` String 500
- `TYPE` Enum 0..4, индекс — нет сработки, реферер, маска, серый, чёрный
- `RULE` String 255 — значение сработавшего правила
- `CAPTCHA_PASSED` Boolean, default N
- `VISITS` Integer, default 1
- `MESSAGE` TextField
- `DATE_CREATE` Datetime, default now, индекс
- `DATE_UPDATE` Datetime
- Уникальный индекс по `IP`

`keyup_cleartrafic_black_list`, `keyup_cleartrafic_gray_list`:
- `ID`, `IP` String 45 required unique, `COMMENT` String 255, `DATE_CREATE` Datetime

`keyup_cleartrafic_mask`:
- `ID`, `NETWORK` String 45 required, `MASK` Integer 0..32 required, `ACTIVE` Boolean, `SORT` Integer, `DATE_CREATE` Datetime
- Индекс по `ACTIVE`

`keyup_cleartrafic_referer`:
- `ID`, `HOST` String 255 required unique, `DATE_CREATE` Datetime

`keyup_cleartrafic_email`:
- `ID`, `EMAIL` String 255 required, `DATE_CREATE` Datetime

`keyup_cleartrafic_form_log`:
- `ID`, `IP` String 45, индекс, `DATE_CREATE` Datetime, индекс

## Сервисный слой

- `lib/model/` — только `DataManager`-классы, карта полей, индексы в install.
- `lib/repository/VisitRepository.php` — upsert визита по IP, инкремент счётчика, отметка каптчи, запись сообщения, очистка по retention.
- `lib/repository/RulesRepository.php` — единая загрузка всех правил одним проходом.
- `lib/cache/RuleCache.php` — управляемый кэш правил, тег `keyup.cleartrafic.rules`, инвалидация при изменениях.
- `lib/installer/TableInstaller.php` — создание/удаление таблиц и индексов, идемпотентно.

## UI: отвязка от highloadblock.list

Компонент `bitrix:highloadblock.list` работает только с HL-блоками. Поэтому страницы `/personal_filter/` переводятся на собственный вывод:

- Новый компонент `install/components/keyup/cleartrafic.list/` с шаблонами `ip_list`, `mask_ref`, `referal`, `email_list`, `config` — переносим верстку таблиц, источник данных — ORM-запрос с `PageNavigation`.
- Либо прямой вывод в страницах — решается на этапе реализации; компонент предпочтительнее для переиспользования.
- `ajax.php` — CRUD через `DataManager` (`add`, `update`, `delete`) вместо `HlBlock`.

## Изменения в установке

- `DoInstall`: вызов `TableInstaller::create()` вместо `createHlBlock*`, регистрация обработчика остаётся.
- `DoUninstall`: `TableInstaller::drop()` вместо `deleteHlBlock`.
- Отказ от `InstallDB`/`install.sql` с мёртвыми таблицами.
- Поднять версию модуля.

## Перенос существующих данных

Модуль уже установлен, данные лежат в HL-блоках. Нужен одноразовый скрипт миграции:
- считать записи из `IpList`, `BlackIpList`, `GrayIpList`, `SubnetMasks`, `HttpReferer`, `OptionsEmail`, `OptionsModule`;
- привести типы к новой схеме (`да/нет` → boolean, строка совпадения → enum);
- записать в новые таблицы, дедуплицировать, агрегировать счётчики визитов;
- по завершении удалить HL-блоки.

Точка входа: отдельная страница-мастер в админке модуля либо консольная команда, с обязательным подтверждением.

## Совместимость сервисов

| Файл | Действие |
|---|---|
| [`lib/handlers.php`](install/../lib/handlers.php:14) | перевод на `RuleCache` и `VisitRepository` |
| [`lib/iplist.php`](install/../lib/iplist.php:14) | удаляется, логика в `VisitTable` и `VisitRepository` |
| [`lib/blacklist.php`](install/../lib/blacklist.php:9) | репозиторий поверх `BlackListTable` |
| [`lib/graylist.php`](install/../lib/graylist.php:9) | репозиторий поверх `GrayListTable` |
| [`lib/mask.php`](install/../lib/mask.php:12) | `MaskTable` плюс кэш, батч-импорт |
| [`lib/referers.php`](install/../lib/referers.php:9) | `RefererTable` плюс кэш |
| [`lib/email.php`](install/../lib/email.php:9) | `EmailTable` |
| [`lib/form.php`](install/../lib/form.php:9) | `FormLogTable` |
| [`lib/config.php`](install/../lib/config.php:9) | настройки в `COption` |
| [`lib/hlblock.php`](install/../lib/hlblock.php:9) | удаляется |
| [`lib/import.php`](install/../lib/import.php:11) | батч-вставка в транзакции |

## Порядок выполнения

1. Сущности `lib/model/*` и `TableInstaller`, правки установки и удаления.
2. Сервисы: `VisitRepository`, `RulesRepository`, `RuleCache`.
3. Перевод обработчика и фильтрации на новые сервисы.
4. UI: свой компонент списка и перевод страниц `/personal_filter/`, переписывание `ajax.php`.
5. Настройки в `COption`, чистка `config`/`email`.
6. Скрипт миграции данных из HL-блоков.
7. Индексы и батч-операции, retention журнала.
8. Обновление версии и проверка на установке с нуля и на обновлении.

## Риски

- **Крупный рефактор с изменением UI** — нужен отдельный этап и проверка всех страниц.
- **Миграция данных** — обязательна резервная копия перед запуском.
- **Обратная совместимость** — сторонние сайты, использующие классы модуля напрямую, затронуты; фиксируется в changelog.
