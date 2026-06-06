# AI Консультант - семантический поиск товаров

Модуль Bitrix для смыслового поиска товаров на основе нейросети `intfloat/multilingual-e5-large`.

## Примеры использования

Поиск понимает смысл запроса, а не ищет точное совпадение слов. Покупатель описывает что ему нужно своими словами - модуль находит подходящие товары.

| Тип каталога | Что вводит покупатель | Что находит |
|-------------|----------------------|-------------|
| Стройматериалы | «штукатурка для влажных помещений» | Влагостойкие штукатурки, гидроизоляционные смеси |
| Автозапчасти | «масло в двигатель японского авто 5w30» | Моторные масла 5W-30 для азиатских двигателей |
| Зоотовары | «корм для пожилой кошки с чувствительным пищеварением» | Корма для ageing cats, sensitive digestion |
| Электроника | «телефон с хорошей камерой до 40000 рублей» | Смартфоны с мощной камерой в бюджете до 40 000 |
| Одежда | «лёгкое летнее платье в горошек» | Летние платья в горох / polka dot |
| Бытовая техника | «чем косить траву на даче» | Газонокосилки, триммеры, сенокосилки |
| Косметика | «крем от морщин после 45 лет» | Антивозрастные кремы 45+ |
| Книги | «что почитать по саморазвитию для подростка» | Книги по психологии и саморазвитию для подростков |

Штатный поиск Bitrix такое не найдёт - он требует точного совпадения слов в названии.

**Как работает:**
- Товары индексируются раз в сутки (cron → PHP CLI-скрипт): текст -> эмбеддинг (1024 float32) -> MySQL
- Поиск: запрос -> HTTP к Python-сервису -> вектор -> косинусное сходство -> top-20
- Скорость поиска: ~1 сек на 15k товаров

## Требования к серверу

### Что consumes модуль (отдельно от системы)

| Ресурс | Значение | Примечание |
|--------|----------|------------|
| RAM | 2.5 GB | Модель multilingual-e5-large в памяти (560M float32 = 2.2 GB) + torch runtime (~300 MB) |
| Диск | 5 GB | Модель ~2.1GB (кэшируется при первом запуске), torch ~800MB, venv ~500MB |
| CPU | 2 ядра | На одно ядро эмбеддинг запроса ~40ms вместо ~15ms |

**Важно:** это потребление ТОЛЬКО модулем, без учёта остальной системы. Эти 2.5 GB RAM модуль забирает сверх того, что уже занято OS, MySQL, PHP и Bitrix.

### Полные требования к серверу (с учётом всего)

| Параметр | Минимальные (до 10k товаров) | Рекомендуемые (до 100k товаров) |
|----------|------------------------------|----------------------------------|
| **RAM всего** | **5 GB** | **10 GB** |
| - Модуль | 2.5 GB | 2.5 GB |
| - MySQL | 1 GB | 2 GB |
| - PHP-FPM (3-5 workers) | 0.5 GB | 1 GB |
| - OS + системные | 1 GB | 1.5 GB |
| - Запас | - | 3 GB |
| CPU | 2 ядра, 2.0+ GHz | 4 ядра, 2.5+ GHz |
| Диск | 15 GB SSD | 25 GB SSD |
| Python | 3.10+ | 3.10+ |
| PHP | `exec()` разрешён, `curl` | `exec()` разрешён, `curl` |
| Bitrix | Любая редакция с D7-ядром | Любая редакция с D7-ядром |

### Расчёт места под БД

Каждый товар занимает 4096 байт (1024 float32) в таблице `mib_smartconsultant_embedding`:
- 1 000 товаров = 4 MB
- 10 000 товаров = 40 MB
- 100 000 товаров = 400 MB
- 1 000 000 товаров = 4 GB

### Важно

- Модуль работает только на Linux. Python-часть не запустится на macOS (MAMP).
- Python-модель загружается в RAM при старте HTTP-сервиса (server.py) и на время индексации (embed.py).
- **Модель (2.1 GB) включена в дистрибутив** (`python/model/`). Если папка `model/` отсутствует — код автоматически скачает модель из HuggingFace при первом запуске.
- **Требуется PHP-расширение `curl`** для HTTP-запросов к Python-сервису при индексации. Проверьте: `php -m | grep curl`. Включить: `mv /etc/php.d/20-curl.ini.disabled /etc/php.d/20-curl.ini` (BitrixVM).
- **Права на папку `python/model/`** должны быть у пользователя, под которым работает systemd-сервис. Если модель скачивалась от `root`, а сервис работает от `bitrix` — выполните `chown -R bitrix:bitrix python/model/`. Иначе сервис не прочитает модель и будет пытаться скачать её заново из HuggingFace при каждом запуске.

## Установка

### 1. Копирование модуля на сервер

Скопируйте папку `mibazarow.smartconsultant` в `/local/modules/` вашего сайта.

Проверьте, что структура совпадает:

```bash
ls -la /var/www/site/local/modules/mibazarow.smartconsultant/
# .settings.php  include.php  options.php  default_option.php
# install/  lib/  components/  python/
```

### 2. Python-окружение (только Linux)

Модель (2.1 GB) уже включена в дистрибутив в папке `python/model/` — HuggingFace не нужен.

Все команды выполняются на Linux-сервере от root или пользователя с правами на `/var/www/site/`.

```bash
# Переходим в папку python
cd /var/www/site/local/modules/mibazarow.smartconsultant/python

# 2.1 Создаём виртуальное окружение
python3 -m venv venv

# 2.2 Обновляем pip внутри venv
venv/bin/pip install --upgrade pip

# 2.3 Ставим torch (CPU-версия, ~800MB)
venv/bin/pip install torch --index-url https://download.pytorch.org/whl/cpu

# 2.4 Ставим остальные зависимости (~5MB)
venv/bin/pip install -r requirements.txt

# 2.5 Проверка - должен вернуть JSON с embedding (1024 чисел)
#     Модель загрузится из локальной папки model/ за секунду
echo '[{"id":1,"text":"Тестовый товар"}]' | venv/bin/python embed.py
```

Если команда `python3` не найдена - установите Python 3.10+:
```bash
# Debian/Ubuntu
apt update && apt install python3 python3-venv python3-pip

# CentOS/RHEL
dnf install python3 python3-pip
```

### 3. Systemd-сервис для HTTP-сервиса

Сервис `server.py` должен работать постоянно, чтобы обрабатывать поисковые запросы за ~40ms. Без него PHP будет пытаться поднимать модель при каждом запросе (3-5 сек).

Создайте файл `/etc/systemd/system/mib-smartconsultant.service`:

```ini
[Unit]
Description=Mibazarow SmartConsultant Embedding Service
After=network.target

[Service]
Type=simple
User=bitrix
WorkingDirectory=/var/www/site/local/modules/mibazarow.smartconsultant/python
ExecStart=/var/www/site/local/modules/mibazarow.smartconsultant/python/venv/bin/python server.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

**Важно:**
- Замените `/var/www/site` на реальный путь к корню вашего сайта.
- Пользователь в `User=` должен совпадать с тем, под кем работает веб-сервер:
  - **BitrixVM / BitrixEnv** — `bitrix`
  - **Стандартный LAMP/LEMP** — `www-data` (Debian/Ubuntu) или `apache`/`nginx` (CentOS/RHEL)
- Если пользователь указан неверно, сервис будет падать с `status=217/USER` — проверьте командой: `id bitrix` или `id www-data`.
- Модель загружается из локальной папки `python/model/` — права на чтение должны быть у пользователя из `User=`. Если модель копировалась или скачивалась от `root`, выполните:
  ```bash
  chown -R bitrix:bitrix /var/www/site/local/modules/mibazarow.smartconsultant/python/model/
  ```
  Без этого сервис не найдёт локальную модель и будет пытаться скачать её из HuggingFace при каждом запуске (долго, нужен интернет).

Запустите и включите автозапуск:

```bash
systemctl daemon-reload
systemctl enable --now mib-smartconsultant

# Проверка
systemctl status mib-smartconsultant
# Active: active (running)

# Проверка HTTP-эндпоинта
curl http://127.0.0.1:9876/health
# {"model":"intfloat/multilingual-e5-large","status":"ok"}
```

### 4. Установка модуля в админке Bitrix

1. Зайдите в админку Bitrix
2. Перейдите: **Marketplace** -> **Установленные решения**
3. Найдите в списке **«AI Консультант (семантический поиск)»**
4. Нажмите **Установить** (зелёная стрелка справа)

При установке:
- Создаётся таблица `mib_smartconsultant_embedding` в БД
- Компонент копируется в `/local/components/mibazarow/`
- Проверяется доступность Python и HTTP-сервиса

Если HTTP-сервис не запущен, установщик покажет предупреждение - это нормально, вы запустите его на шаге 3.

### 5. Настройка модуля

После установки перейдите в настройки модуля:

**Админка** -> **Настройки** -> **Настройки продукта** -> **Настройки модулей** -> **AI Консультант (семантический поиск)**

или по прямой ссылке: `/bitrix/admin/settings.php?mid=mibazarow.smartconsultant&lang=ru`

Заполните параметры:

| Параметр | Пример | Описание |
|----------|--------|----------|
| **Инфоблок каталога** | выпадающий список | Выберите инфоблок с товарами. Только товары из этого инфоблока будут индексироваться и участвовать в поиске. |
| **Порог релевантности** | `0.4` | Минимальное сходство для показа результата. 0.3 - больше результатов но больше шума, 0.6 - только точные совпадения |
| **Количество результатов** | `20` | Сколько товаров возвращается при поиске |

Нажмите **Сохранить**. При успешном сохранении появится статус HTTP-сервиса (зелёный - работает, красный - не запущен).

**Важно:** все настройки поиска (инфоблок, порог, количество) задаются только здесь, в настройках модуля. Компонент не имеет собственных параметров кроме placeholder-текста.

### 6. Индексация товаров (cron)

Индексация запускается CLI-скриптом по cron. Никакие агенты Bitrix не используются.

**Как работает индексация:**

Скрипт проверяет MD5-хеш текста каждого товара. Если текст не изменился с прошлой индексации — товар пропускается. Если изменился или новый — вычисляется новый эмбеддинг.

Товары обрабатываются чанками по 20 штук: чанк → HTTP к Python-сервису → эмбеддинг → сразу INSERT в БД → следующий чанк. Каждый чанк сохраняется в БД немедленно, не накапливаясь в памяти.

**Устойчивость к сбоям:** если индексация прервётся (упал сервер, кончилась память, отключили свет) — уже обработанные товары сохранены в БД. При повторном запуске скрипт сверит хеши и продолжит с того места, где остановился, а не начнёт заново.

- **Первая индексация** — самая долгая: все товары новые, каждый проходит через нейросеть. Для 60 000 товаров на 4-ядерном CPU это ~2-3 часа.
- **Последующие запуски** — обрабатываются только изменённые и новые товары (обычно единицы или десятки). Занимает секунды.

**Первая индексация — вручную:**

```bash
php /var/www/site/local/modules/mibazarow.smartconsultant/bin/reindex.php
```

**Автоматическая индексация — cron (каждый день в 3:00):**

```bash
crontab -e
# Добавьте строку:
0 3 * * * php /var/www/site/local/modules/mibazarow.smartconsultant/bin/reindex.php >> /var/log/smartconsultant-reindex.log 2>&1
```

**Проверка результатов индексации:**

```sql
SELECT COUNT(*) FROM mib_smartconsultant_embedding;
SELECT MAX(INDEXED_AT) FROM mib_smartconsultant_embedding;
```

**Ускорение первой индексации:**

Если у вас десятки тысяч товаров и первая индексация идёт слишком долго:

| Способ | Ускорение | Сложность | Что делать |
|--------|-----------|-----------|------------|
| GPU (NVIDIA) | 10-50× | Средняя | `pip install torch` (CUDA) вместо `pip install torch --index-url https://download.pytorch.org/whl/cpu` |
| Больше ядер CPU | пропорционально | Низкая | Арендовать VDS с 8+ ядрами на время первой индексации |
| Увеличить чанк | ~2× | Низкая | В `lib/Embedding/Engine.php` изменить `BATCH_CHUNK_SIZE = 20` на 50-100 |
| Gunicorn (множество воркеров) | 3-4× | Средняя | Заменить `app.run()` на gunicorn с 4 workers (см. ниже) |
| Прямой вызов embed.py | 5-10× | Высокая | Потоковый stdin/stdout вместо HTTP (см. ниже) |
| Перенос с мощного сервера | ∞ | Средняя | Проиндексировать на мощной машине и перенести только таблицу в БД (см. ниже) |

**Перенос проиндексированных данных с мощного сервера:**

Эмбеддинги — это просто числа (векторы 1024 float32), они не зависят от железа. Можно проиндексировать товары на мощной машине с GPU, а результат перенести на продакшен.

Порядок действий:

```bash
# 1. На мощной машине: установить модуль, подключить к копии продакшен-БД
#    (или скопировать БД продакшена на мощную машину)
# 2. Запустить индексацию — на GPU 60K товаров за 5-10 минут
php bin/reindex.php

# 3. Выгрузить таблицу с эмбеддингами
mysqldump -u user -p dbname mib_smartconsultant_embedding > embeddings.sql

# 4. На продакшене: залить таблицу
mysql -u user -p dbname < embeddings.sql
```

После этого cron на продакшене будет переиндексировать только изменившиеся товары (единицы), и слабого CPU для этого достаточно.

Для поисковых запросов (один текст) текущего CPU достаточно — ответ приходит за ~1 сек.

#### Gunicorn с несколькими воркерами (×3-4)

Flask (`app.run()`) обрабатывает запросы строго по одному. Gunicorn запускает несколько независимых воркеров, каждый со своей копией модели. PHP может слать запросы параллельно через `curl_multi`.

**Требования к RAM:** каждый воркер держит свою копию модели (~2.2 GB). 4 воркера = ~8.8 GB только под модель. Нужно **16+ GB RAM** на сервере.

**Настройка:**

```bash
# 1. Установить gunicorn в виртуальное окружение
cd /var/www/site/local/modules/mibazarow.smartconsultant/python
venv/bin/pip install gunicorn

# 2. Остановить старый сервис
systemctl stop mib-smartconsultant
```

**3. Заменить systemd-юнит.** В файле `/etc/systemd/system/mib-smartconsultant.service` изменить `ExecStart`:

```ini
[Service]
Type=simple
User=bitrix
WorkingDirectory=/var/www/site/local/modules/mibazarow.smartconsultant/python
ExecStart=/var/www/site/local/modules/mibazarow.smartconsultant/python/venv/bin/gunicorn \
    --workers 4 \
    --bind 127.0.0.1:9876 \
    --timeout 300 \
    --preload \
    server:app
Restart=always
RestartSec=10
```

- `--workers 4` — 4 воркера (по одному на ядро)
- `--preload` — загружает модель до форка воркеров (экономит время старта, но RAM пик выше)
- `--timeout 300` — 5 минут на обработку batch-запроса

**4. В PHP** заменить последовательные curl-запросы на `curl_multi` (4 параллельных). Рефакторинг `Engine.php`: метод `embedBatch()` должен делить чанки на группы по числу воркеров и отправлять их одновременно.

```bash
# Применить и запустить
systemctl daemon-reload
systemctl start mib-smartconsultant

# Проверить: должно быть 4+ процессов python
ps aux | grep gunicorn
curl http://127.0.0.1:9876/health
```

**Минусы:** большой расход RAM, нужен рефакторинг PHP-кода под `curl_multi`.

#### Прямой вызов embed.py через stdin/stdout (×5-10)

Самый быстрый способ для CPU-сервера: убрать HTTP-оверхед (3 184 запросов → 1 вызов) и дать PyTorch самому управлять батчингом и потоками.

**Как работает:**
- PHP пишет все товары в stdin Python-процесса (JSON lines)
- `embed.py` читает построчно, накапливает батчи по 100-200 товаров
- PyTorch внутри использует все ядра CPU для одного батча
- Результаты пишутся в stdout построчно (JSON lines)
- PHP читает stdout и сохраняет в БД

**Архитектура (концепт):**

```php
// Pipeline.php — концепт прямого вызова
$python = Engine::findPython();
$embedScript = __DIR__ . '/../../python/embed.py';

$proc = proc_open(
    "$python $embedScript",
    [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w'],  // stderr
    ],
    $pipes
);

// Пишем все товары в stdin (JSON lines)
foreach ($items as $item) {
    fwrite($pipes[0], json_encode($item, JSON_UNESCAPED_UNICODE) . "\n");
}
fclose($pipes[0]);

// Читаем результаты построчно из stdout
while (($line = fgets($pipes[1])) !== false) {
    $result = json_decode(trim($line), true);
    // Сохраняем в БД
    $repo->save($result['id'], $result['embedding'], $result['hash']);
}

proc_close($proc);
```

**Изменения в embed.py:**

```python
# Читаем JSON lines из stdin, обрабатываем батчами, пишем в stdout
import sys, json
from sentence_transformers import SentenceTransformer

MODEL_NAME = 'intfloat/multilingual-e5-large'
BATCH_SIZE = 100  # большой батч = лучшая утилизация CPU

# Загрузка модели
model = SentenceTransformer(MODEL_NAME)

# Читаем все строки
items = []
for line in sys.stdin:
    line = line.strip()
    if line:
        items.append(json.loads(line))

# Обрабатываем батчами
for i in range(0, len(items), BATCH_SIZE):
    batch = items[i:i + BATCH_SIZE]
    texts = ['passage: ' + it['text'][:1024] for it in batch]
    embeddings = model.encode(texts, normalize_embeddings=True, show_progress_bar=False)
    
    for item, emb in zip(batch, embeddings):
        result = {
            'id': item['id'],
            'hash': hashlib.md5(item['text'].encode()).hexdigest(),
            'embedding': emb.tolist(),
        }
        print(json.dumps(result, ensure_ascii=False), flush=True)
```

**Плюсы:**
- Нет HTTP-оверхеда (3 184 round-trips → 1 вызов)
- PyTorch сам батчит и использует все ядра
- Потоковый I/O — память O(1)
- Не нужен gunicorn, не нужен curl_multi

**Минусы:**
- Нужен рефакторинг `Pipeline.php` и `embed.py`
- Неприменимо для поисковых запросов (там по-прежнему нужен HTTP-сервис)
- Только для индексации

### 7. Размещение компонента на странице

1. Откройте страницу в визуальном редакторе
2. Добавьте компонент: **Mibazarow** -> **AI Консультант: семантический поиск**
3. В настройках задайте только **Текст в поле ввода** (placeholder). Инфоблок, порог и количество результатов настраиваются в модуле.
4. Сохраните страницу

### 8. Проверка работы

1. Откройте страницу с компонентом
2. Введите поисковый запрос (например, «чем косить траву»)
3. Должен появиться выпадающий список с товарами и процентом релевантности
4. Нажмите Enter или кликните по товару - откроется страница товара

Если выпадающий список не появляется — см. раздел [Решение проблем](#решение-проблем).

## Решение проблем

### 1. Systemd-сервис падает с `status=217/USER`

**Причина:** пользователь в `User=` не существует в системе.

**Решение:**
```bash
# Проверьте, под каким пользователем работает веб-сервер:
ps aux | grep -E 'apache|nginx|httpd' | grep -v grep | awk '{print $1}' | sort -u

# Исправьте User= в /etc/systemd/system/mib-smartconsultant.service:
# - BitrixVM: User=bitrix
# - Debian/Ubuntu: User=www-data
# - CentOS/RHEL: User=apache или nginx

systemctl daemon-reload && systemctl restart mib-smartconsultant
```

### 2. Индексация не работает (0 товаров в `mib_smartconsultant_embedding`)

**Причина А:** не указаны ID инфоблоков в настройках модуля.

**Решение:**
- Админка → Настройки → Настройки модулей → AI Консультант
- Выберите инфоблок из выпадающего списка «Инфоблок каталога»
- Нажмите «Сохранить»
- Запустите индексацию вручную: `php .../bin/reindex.php`

**Причина Б:** ошибка `ModuleNotFoundError: No module named 'sentence_transformers'` при запуске `embed.py`.

**Решение:** `venv/bin/python` — это симлинк на системный Python. В коде модуля используется прямой путь к venv (без `realpath()`), но если вы модифицировали код — убедитесь, что путь не резолвится через `realpath()`, так как это ломает обнаружение `pyvenv.cfg`.

### 3. Ошибка `file_get_contents(http://127.0.0.1:9876/...): Failed to open stream`

**Причина:** отсутствует PHP-расширение `curl`.

**Решение:**
```bash
# Проверить:
php -m | grep curl

# Если нет — включить (BitrixVM):
mv /etc/php.d/20-curl.ini.disabled /etc/php.d/20-curl.ini

# Перезапустить PHP-FPM:
systemctl restart php-fpm
```

### 4. Ошибка `escapeshellarg(): Argument exceeds the allowed length of 2097152 bytes`

**Причина:** слишком много товаров передаётся в Python одной порцией (JSON > 2MB).

**Решение:** эта проблема исправлена в коде — товары разбиваются на чанки по 20 штук (константа `BATCH_CHUNK_SIZE` в `lib/Embedding/Engine.php`) и передаются через `curl` вместо `shell_exec`. Если вы используете старую версию модуля — обновите `lib/Embedding/Engine.php`.

### 5. Модель загружается при каждом поисковом запросе (медленно)

**Причина:** HTTP-сервис `server.py` не запущен.

**Решение:**
```bash
systemctl status mib-smartconsultant
# Если не active (running):
systemctl restart mib-smartconsultant
curl http://127.0.0.1:9876/health
# Должен вернуть: {"model":"intfloat/multilingual-e5-large","status":"ok"}
```

### 6. Поиск не находит товары, хотя индексация прошла

**Причины и решения:**
- **Порог релевантности слишком высокий** — уменьшите `MIN_SIMILARITY` в настройках (например, до `0.3`)
- **Товары не проиндексированы в `b_search_content`** — запустите переиндексацию поиска Bitrix: админка → Настройки → Поиск → Переиндексация
- **Не совпадает SITE_ID** — `SourceText::extractAll()` использует `SITE_ID`. В `bin/reindex.php` проверьте, что `SITE_ID` определён правильно (обычно `s1`)

### 7. Модель не загружается (No such file or directory)

**Причина:** отсутствует папка `python/model/` с файлами модели.

**Решение:** модель включена в дистрибутив в `python/model/`. Если папка пуста или отсутствует — скачайте модель повторно:
```bash
cd /path/to/python
venv/bin/python -c "from sentence_transformers import SentenceTransformer; m = SentenceTransformer('intfloat/multilingual-e5-large'); m.save('model')"
```

## Структура модуля

```
local/modules/mibazarow.smartconsultant/
├── .settings.php          # Контроллеры
├── default_option.php     # Настройки по умолчанию
├── options.php            # Страница настроек
├── include.php            # Автозагрузка
├── install/
│   ├── index.php          # Установщик
│   ├── version.php        # Версия
│   └── db/mysql/install.sql
├── bin/
│   └── reindex.php        # CLI-скрипт индексации (запускается по cron)
├── lib/
│   ├── Embedding/         # Math, Repository, Engine
│   ├── Index/             # SourceText, Pipeline
│   ├── Search/            # Searcher, Result
│   └── Infrastructure/Controller/  # SearchController
├── components/
│   └── mibazarow/smartconsultant.search/
└── python/
    ├── model/              # Модель multilingual-e5-large (2.1 GB, включена в дистрибутив)
    ├── embed.py            # Пакетная индексация (CLI, устаревший — вместо неё используется HTTP-сервис)
    ├── server.py           # HTTP-сервис (поиск + batch-индексация)
    └── requirements.txt
```
