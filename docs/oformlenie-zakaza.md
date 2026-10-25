# Документация: страница оформления заказа `/oformlenie-zakaza/`

Компонент: `opensource:order`, шаблон `order-page`.
Точка входа: [`oformlenie-zakaza/index.php`](../oformlenie-zakaza/index.php:38) (и копия для мобильного приложения — `mobile_app/oformlenie-zakaza/index.php`).

---

## 1. Карта файлов

| Файл | Назначение |
|---|---|
| [`oformlenie-zakaza/index.php`](../oformlenie-zakaza/index.php:1) | Страница. Проверка авторизации (иначе редирект на `/`), обработчик `OnSaleOrderSaved` для `acrit.bonus`, подключение компонента с параметрами: `DEFAULT_PERSON_TYPE_ID=1`, `DEFAULT_DELIVERY_ID=2`, `DEFAULT_PAY_SYSTEM_ID=3`, композит включён (`COMPOSITE_FRAME_MODE=A`) |
| [`local/components/opensource/order/class.php`](../local/components/opensource/order/class.php:1) | Класс `OpenSourceOrderComponent` — ядро: создание виртуального заказа, свойства, отгрузка, оплата, валидации, AJAX-экшены (2700 строк, 57 методов) |
| [`local/components/opensource/order/ajax.php`](../local/components/opensource/order/ajax.php:1) | Контроллер `OpenSourceOrderAjaxController`: `searchLocation`, `calculateDeliveries`, `saveOrder`. **Боевым фронтендом не используется** (см. §9) |
| [`.../order-page/template.php`](../local/templates/fisheat/components/opensource/order/order-page/template.php:1) | Включает `done.php` (если `$arResult['ID'] > 0`) либо `form.php`; передаёт настройки Яндекс.Карт в `window.orderMapSettings`; выводит `errorCollection` |
| [`.../order-page/result_modifier.php`](../local/templates/fisheat/components/opensource/order/order-page/result_modifier.php:1) | Сборка `$arResult`: свойства, адреса, доставка, оплата, корзина, подарки, итоги, минимальная сумма, рестораны, настройки карт (714 строк) |
| [`.../order-page/form.php`](../local/templates/fisheat/components/opensource/order/order-page/form.php:1) | Разметка формы: корзина, комментарий, подарки, промокод, доставка/адреса/рестораны (моб. и десктоп блоки), оплата, время доставки, итоги, кнопка оформления, модалка адреса |
| [`.../order-page/script.js`](../local/templates/fisheat/components/opensource/order/order-page/script.js:1) | Объект `BX.LDO.CustomBasket` — 122 метода: корзина, доставка, адреса, рестораны, промокод, подарки, модальные окна, автозапуск на `BX.ready` |
| `fisheat_app` / `mobile_app` | Параллельные копии шаблона (мобильное приложение) — **правки нужно дублировать** |

Модули-зависимости: `sale`, `catalog`, `opensource.order`, `ldo.deliverymap` (зоны/рестораны/настройки), `ldo.iiko` (адреса пользователя), `ldo.develop` (`Basket`, `Product`, `Hlblock`), `ldo.marketing` (`GiftsTable`, `FreePositionsTable`, `Settings`), `acrit.bonus` (опционально), `conversion` (детект мобильного).

---

## 2. Порядок загрузки страницы (GET)

`executeComponent()` — [`class.php:1010`](../local/components/opensource/order/class.php:1010):

1. `ensureAddressIdProperty()` — при отсутствии создаёт служебное свойство `ADDRESS_ID` (`UTIL=Y`) для всех типов плательщиков. Защита от повторного создания — статический флаг `$addressIdPropEnsured`.
2. `ensureZoneDeliveryServices()` — прогоняет активные службы доставки (кроме «Самовывоз» и `EmptyDeliveryService`) и принудительно меняет `CLASS_NAME` на `\Ldo\Deliverymap\DeliveryServices\ZoneDelivery`, затем сбрасывает весь кэш Bitrix. Флаг `$zoneServicesEnsured`.
3. `createVirtualOrder($personTypeId)` — [`class.php:124`](../local/components/opensource/order/class.php:124):
   - `\Ldo\Develop\Basket::applyFreePositionsToCurrentBasket()` — добавление/пересчёт **бесплатных позиций**;
   - загрузка корзины FUSER; если позиций нет — исключение `LengthException` («Корзина пуста»);
   - `Order::create()` + `setPersonTypeId()` + `setBasket()`.
   Всё это **виртуальный, несохраняемый** заказ.
4. `prefillPropertiesWithUserData()` — для авторизованного пользователя заполняет свойства `NAME`/`FIO`/`EMAIL`/`PHONE`, если они пусты.
5. `setOrderProperties($propertiesList)` — пробегает по значениям свойств из `$_REQUEST['properties']`; служебные (`UTIL=Y`) пропускает, **кроме `ADDRESS_ID`**; пустые значения заменяет `DEFAULT_VALUE`. Дополнительно пишет `addMessage2Log($prop)` (отладочный шум, см. §8).
6. `applyGiftPropertyFromSession()` — подставляет `$_SESSION['LDO_GIFT_ID']` в служебное свойство заказа `GIFT`.
7. `setField('USER_DESCRIPTION', ORDER_DESCRIPTION)` — «Комментарий кухне».
8. `createOrderShipment($deliveryId)` — создаёт отгрузку и позиции отгрузки по всем `getOrderableItems()`; перед этим `ensureZoneDeliveryService($deliveryId)`, а сам объект службы берётся через `getDeliveryServiceObject()` (обход кэша `Manager`).
9. `createOrderPayment($paySystemId)` — если задан ID платёжной системы.
10. Если `arParams['SAVE'] === true` (saбmit формы с `save=y`):
    1. `applyAsapDeliveryDateTime()` — режим «Как можно скорее» и доставка: `DATE_TIME_DELIVERY = now + DELIVERY_TIME_END (зона) [+ high_load_add_time]`;
    2. `applyAsapPickupDeliveryDateTime()` — то же для самовывоза (+ `high_load_add_time`);
    3. `applyDeliveryZoneProperties()` — заполняет `zone_id` / `zone_name` по адресу;
    4. `validateOrder()` → при успехе `calculateShipmentDelivery()` и `order->save()`, при успехе — `unset($_SESSION['LDO_GIFT_ID'])`;
    5. при ошибке — сбор в `errorCollection`.
11. `includeComponentTemplate()` — рендер. `result_modifier.php` **дополнительно** вызывает `updateAddressPriceAction()` (см. §4.7).

Параметры: `onPrepareComponentParams()` — [`class.php:71`](../local/components/opensource/order/class.php:71): `DEFAULT_PERSON_TYPE_ID` (из первого типа плательщика или 1), `PERSON_TYPE_ID` (из `$_REQUEST['person_type_id']` или default), `SAVE` (`Y`/`y`).

---

## 3. Валидации (`validateOrder()` — [`class.php:978`](../local/components/opensource/order/class.php:978))

Выполняются **только** при `SAVE = true`; собирают ошибки с кодом и `type`.

| Метод | Что проверяет | Условия |
|---|---|---|
| `validateProperties()` [`691`](../local/components/opensource/order/class.php:691) | `checkRequiredValue` + `checkValue` по всем неслужебным свойствам | — |
| `validateDelivery()` [`723`](../local/components/opensource/order/class.php:723) | выбранная служба есть в `getRestrictedObjectsList($shipment)` | `type=unavailable` / `undefined` |
| `validatePayment()` [`926`](../local/components/opensource/order/class.php:926) | выбранная ПС есть в `getListWithRestrictions($payment)` | — |
| `validateDeliveryAvailability()` [`777`](../local/components/opensource/order/class.php:777) | самовывоз: активен ресторан `RESTORAN_ID`; доставка: активна зона адреса и активен ресторан зоны | «Адрес больше не обслуживается», «Ресторан… не принимает заказы» |
| `validateMinOrderSum()` [`864`](../local/components/opensource/order/class.php:864) | `Σ getBasePrice() × quantity ≥ MIN_ORDER_PRICE` (зоны — для доставки, ресторана — для самовывоза) | ошибка `basket`, `type=minOrder` |

Важно: минимальная сумма считается **по базовым ценам без скидок** и включает бесплатные позиции (у них `BASE_PRICE = 0`, поэтому фактически они не влияют).

---

## 4. Расчёты и итоги

### 4.1 Виртуальный заказ для расчёта
`calculateTotalsInOrderContext($deliveryId, $addressId)` — [`1518`](../local/components/opensource/order/class.php:1518): создаёт новый `Order::create`, `setPersonTypeId(1)` (жёстко), проставляет `ADDRESS_ID`, при необходимости создаёт отгрузку выбранной службы, затем берёт `$order->getDiscount()->getApplyResult(true)['PRICES']['BASKET']` и считает `baseSum / discount / totalPrice`. Именно так учитываются правила корзины, зависящие от **способа доставки** (например «−10% при самовывозе»), чего не делает обычный `Discount::buildFromBasket`.

### 4.2 Цена доставки
`getDeliveryPriceFromOrder($deliveryId, $basket, $addressId)` — [`1660`](../local/components/opensource/order/class.php:1660): снова виртуальный заказ, отгрузка через `getDeliveryServiceObject()`, затем `$deliveryService->calculate($shipment)` → цена зоны.

`calculateShipmentDelivery()` — [`232`](../local/components/opensource/order/class.php:232): для «Самовывоз» жёстко ставит 0, иначе `calculate()` и `setBasePriceDelivery/setField('PRICE_DELIVERY')`.

### 4.3 Время доставки
`getAddressTimeWindow($addressId)` — [`1269`](../local/components/opensource/order/class.php:1269): `DELIVERY_TIME_START/END` зоны (в минутах) + надбавка высокой нагрузки. `applySiteTimeZone()` — [`273`](../local/components/opensource/order/class.php:273): устанавливает таймзону сайта из настроек `ldo.deliverymap`.

### 4.4 Единый ответ корзины
`prepareBasketResponse()` — [`1713`](../local/components/opensource/order/class.php:1713): применяет скидки через `Discount::buildFromBasket` (**без контекста заказа!**), формирует `items[]` (id, price, basePrice, discount, link, image, available, weight), `totalPrice`, `baseSum`, `discount`, `deliveryPrice` (из `$_SESSION['LDO_DELIVERY_PRICE']`), `total`.

`getBasketProductsInfo()` — [`1812`](../local/components/opensource/order/class.php:1812): ссылка, фото (resize 100×100), доступное количество. ID инфоблока каталога берётся из `\Ldo\Marketing\Settings::getCatalogIblockId()`, а при неудаче — **хардкод 4**.

### 4.5 Сессия как хранилище состояния
| Ключ | Кто пишет | Кто читает |
|---|---|---|
| `LDO_DELIVERY_PRICE` | `updateDeliveryPriceAction`, `updateAddressPriceAction`, `updateRestaurantPriceAction`, `result_modifier` | `getDeliveryPrice()` |
| `LDO_IS_PICKUP` | те же экшены | — (фактически не читается) |
| `LDO_GIFT_ID` | `setGiftAction` | `applyGiftPropertyFromSession()` |
| `CATALOG_USER_COUPONS` | ядро sale (купоны) | `form.php` (radio/input/блокировка подарков) |
| `BONUS_PAY_USER_VALUE` | ядро acrit.bonus | обработчик в `oformlenie-zakaza/index.php` |

### 4.6 Свойства заказа, используемые логикой
| Код | Назначение |
|---|---|
| `ADDRESS_ID` | служебное, ID адреса из `ldo_iiko_user_address` |
| `GIFT` | служебное, ID товара-подарка |
| `zone_id`, `zone_name` | зона доставки |
| `DEFAULT_TIME` | `Y` — «как можно скорее», `N` — выбранные дата/время |
| `DATE_TIME_DELIVERY`, `TIME_DELIVERY` | дата/время |
| `RESTORAN_ID` | XML_ID ресторана (самовывоз) |
| `COUNT_PERSON` | количество персон |
| `CITY`, `NAME`, `EMAIL`, `PHONE` | контактные данные |

### 4.7 Побочные эффекты `result_modifier.php`
- пишет `$_SESSION['LDO_DELIVERY_PRICE']` и **безусловно** `$_SESSION['LDO_IS_PICKUP'] = 'N'` ([`362`](../local/templates/fisheat/components/opensource/order/order-page/result_modifier.php:362));
- при выбранном адресе синхронно вызывает `$component->updateAddressPriceAction(...)` ([`570`](../local/templates/fisheat/components/opensource/order/order-page/result_modifier.php:570)) — то есть AJAX-логика выполняется прямо при рендере, заново считая доставку и скидки;
- переопределяет итоги (`SUM_BASE`, `DISCOUNT_VALUE`, `SUM`) результатом этого «предпросмотра».

---

## 5. Подарки к заказу

### 5.1 Источник данных
Уровни подарков — таблица `ldo_marketing_gift_levels` (ORM `\Ldo\Marketing\GiftsTable`): `NAME` (название уровня), `SUM` (порог суммы), `PRODUCT_IDS` (JSON-массив ID товаров), `ACTIVE`, `SITE_ID`, `SORT`.

`result_modifier.php` ([`414–527`](../local/templates/fisheat/components/opensource/order/order-page/result_modifier.php:414)) собирает `$arResult['GIFTS']` (по уровням: `ID`, `NAME`, `PREVIEW_PICTURE` 150×150, `SUM_LEVEL`, `AVAILABLE`, `SUM_FREE`, `CURRENT_SUM`), сортирует по `SUM_LEVEL`, вычисляет `NEAREST_GIFT` (первый недоступный) и `GIFTS_SUMMARY`.

**Порог сравнивается с `$arResult['PRODUCTS_PRICE']`** — стоимостью товаров **со скидками**.

Фактическое состояние БД (проверено запросом к `ldo_marketing_gift_levels`): единственный уровень — `ID=1, ACTIVE=Y, SUM=500, SITE_ID=s1, NAME="1 уровня", PRODUCT_IDS=[8261,8265,8268,8329]`. То есть 4 подарка с общим порогом 500 ₽; так как уровень один, `NEAREST_GIFT` фактически вычисляется по уровню, а не по конкретному товару.

### 5.2 Порядок применения (клиент)
1. `form.php` рендерит слайдер `.gifts-list` (`data-slider-track data-slider-drag`), у каждого элемента `data-sum-level` (числовой порог) и `data-sum-level-name` (название уровня).
2. Кнопка выбора — `.addCartGift[id-product]`. При наличии купонов (`$_SESSION["CATALOG_USER_COUPONS"]`) вместо кнопок выводится «Выбор недоступен», а контейнер `.gifts-block__items` получает класс `block` (CSS-оверлей с замком — [`.block:before`](../local/templates/fisheat/assets/css/style.css:2322)).
3. Клик → `handleClick` → `selectGift(productId, item)` → модалка `showGiftWarningModal()` (текст: «использование промокода и списание бонусов будет невозможно») → `applyGiftSelection()`:
   - снимает `selected` с прежнего подарка, возвращает ему «Выбрать»;
   - ставит `selected` новому, текст кнопки → **«Отменить»**;
   - `BX.addClass('.promo-block', 'block')` — блокировка промокода/бонусов;
   - `sendGiftRequest(productId)` → `BX.ajax.runComponentAction('opensource:order', 'setGift', {dataGift:{giftId, sessid}})`.
4. Повторный клик по выбранному подарку → `clearGift()`: снятие `selected`, «Выбрать», `BX.removeClass('.promo-block','block')`, `sendGiftRequest(0)`.
5. `initGiftSelection()` (`BX.ready`) восстанавливает выбор по `data-selected-gift` из `form.php` (`$_SESSION['LDO_GIFT_ID']`) и блокирует промокод.
6. `recalcGiftsAvailability(total)` при каждом изменении корзины: показывает/скрывает кнопку и текст «Доступно при заказе от N ₽», пересчитывает верхнюю строку через `updateGiftsBlockTop()` («До подарка … осталось ещё … ₽» либо просто «Доступные подарки»); если подарок стал недоступен — снимает выбор, разблокирует промокод и чистит `GIFT`.

### 5.3 Серверная часть
- `setGiftAction($dataGift)` — [`1104`](../local/components/opensource/order/class.php:1104): проверка `check_bitrix_sessid()`, запись `$_SESSION['LDO_GIFT_ID']` либо `unset`.
- `applyGiftPropertyFromSession()` — [`1081`](../local/components/opensource/order/class.php:1081): `setValue((int)$_SESSION['LDO_GIFT_ID'])` в свойство с `CODE === 'GIFT'`.
- Свойство создаётся «вручную» в БД: `ID=24, CODE=GIFT, NAME="Подарок к заказу", UTIL=Y, TYPE=NUMBER, PERSON_TYPE_ID=1`.
- Сброс сессии — только после успешного `order->save()` в `executeComponent`.

### 5.4 Ключевые проблемы подарков
1. **Подарок не попадает в заказ.** Свойство `GIFT` нигде не читается: нет ни обработчика, ни правила корзины, которое добавляло бы товар-подарок. Проверено: поиск `GIFT` по коду проекта находит только новый код, а в БД нет ни одного правила корзины (`b_sale_discount`) с `GIFT` в `CONDITIONS`/`ACTIONS` (0 совпадений). Пользователь видит «выбрано», но в заказе подарка нет.
2. **Нет серверной валидации** доступности: `setGiftAction` принимает любой ID (не проверяет ни активность уровня, ни принадлежность товара уровню, ни `SUM_LEVEL ≤ сумма корзины`).
3. **Промокод не снимается.** Обещание «промокод станет невозможен» реализовано только визуальной блокировкой: применённый купон остаётся в корзине и продолжает давать скидку вместе с подарком.
4. **`ajax.php::saveOrderAction`** не вызывает `applyGiftPropertyFromSession()` — при сохранении этим путём подарок теряется.
5. **Рассинхрон порога**: сервер считает доступность по `PRODUCTS_PRICE` (со скидками), JS — по `totalPrice` из ответа `prepareBasketResponse` (там скидки считаются без контекста заказа и без учета удаления/добавления бесплатных позиций). Возможны «прыгающие» состояния кнопок.
6. При очистке/удалении корзины (`removeCartAction`) `LDO_GIFT_ID` не сбрасывается — выбранный подарок «наследуется» следующим заказом.

---

## 6. Промокоды и бонусы

### 6.1 Промокод
- Разметка — блок `.promo-block`: radio `promo_id` (`promokod` / `bonus`), поле `#promocode` и кнопка `.promoChange button[type="button"]`.
- При наличии купона `form.php` рендерит `input readonly value=<первый купон>` и кнопку **«Отменить»**, иначе — пустой input и «Применить».
- `bindPromoEvents()` ([`3604`](../local/templates/fisheat/components/opensource/order/order-page/script.js:3604)) — вызывается из `bindEvents()`, навешивает **один** обработчик на `.promoChange button`, который всегда вызывает `applyPromoCode()`; Enter в поле — тоже.
- `applyPromoCode()` — [`3780`](../local/templates/fisheat/components/opensource/order/order-page/script.js:3780): `changePromo` (сервер: `DiscountCouponsManager::clear(true)` → `add($promokod)` → `basket->refreshData(['PRICE','COUPONS'])` → save → `prepareBasketResponse`); при успехе обновляет цены/итоги и добавляет класс `block` блоку подарков (`.gifts-block`).
- **`clearPromoAction` не вызывается из JS вообще** — отмена промокода не реализована.
- Конфигурация правил (проверено в БД): активны 4 правила корзины — `ID=3` «Скидка 10% при сумме больше 5000», `ID=4` «Скидка 5% на первый заказ», `ID=5` «20%», `ID=6` «Скидка самовывоза». Правило `ID=6` использует условие `CondSaleDelivery` со значением `3`, т.е. **привязано к службе доставки с ID=3**, тогда как страница передаёт `DEFAULT_DELIVERY_ID=2` — стоит проверить, соответствует ли это задумке «−10% при самовывозе».

### 6.2 Бонусы
- `acrit.bonus` подключён частично: вся разметка блока бонусов в `form.php` закомментирована ([`366–401`](../local/templates/fisheat/components/opensource/order/order-page/form.php:366), [`540–574`](../local/templates/fisheat/components/opensource/order/order-page/form.php:540)), но живой обработчик `OnSaleOrderSaved` в странице остался.
- JS: `showBonusBlock()` / `hideBonusBlock()` — заглушки с `console.log` и `TODO`.
- Вывод «Начислено бонусов» использует `$arResultEx['BONUS']['ORDER']['VALUE_FORMAT']` ([`513`](../local/templates/fisheat/components/opensource/order/order-page/form.php:513)), тогда как `$arResultEx` формировался только внутри закомментированного блока → **значение пустое + PHP-notice**.

---

## 7. Бесплатные позиции (модуль `ldo.develop`)

`\Ldo\Develop\Basket::applyFreePositionsToCurrentBasket()` — [`basket.php:148`](../local/modules/ldo.develop/lib/basket.php:148):
- разрешает `self::$freePositionsEnabled = true`, вызывает `syncFreePositions()`, сохраняет корзину;
- `syncFreePositions()` ([`233`](../local/modules/ldo.develop/lib/basket.php:233)) читает правила `Product::getFreePositionRulesBySite()` (`ldo_marketing_free_positions`: `PRODUCT_IDS`, `SECTION_IDS`, `PORTIONS`), считает «штуки» товаров по разделам (с учётом свойства `ATT_COUNT_ROLL`), делит на порцию, добавляет/удаляет бесплатный товар и выставляет цену:
  - полностью бесплатно — `CUSTOM_PRICE=Y`, `BASE_PRICE=0`, `PRICE=0`;
  - частично — средняя цена `paidQty × base / totalQty`.

Вызовы: при построении заказа (`createVirtualOrder`) и после изменения количества (`addQuantityAction`). При обычном добавлении в корзину из каталога бесплатные позиции не считаются (флаг `$freePositionsEnabled`).

Особенности: у правила без `SECTION_IDS` совпадений нет — бесплатные позиции не удаляются (комментарий в коде); цена бесплатных позиций не участвует в минимальной сумме и в порогах подарков.

Текущее состояние: таблица `ldo_marketing_free_positions` пуста, поэтому на данный момент бесплатные позиции фактически не добавляются — весь механизм «спит» до заполнения правил в админке.

---

## 8. Доставка, адреса, рестораны, время

- **Способы получения**: список отгрузок `DELIVERY_LIST` (с `CHECKED` — последняя отгрузка заказа). Название «Самовывоз» сравнивается по `mb_strtolower()` — службы, названные иначе («Самовывоз из ресторана»), ведут себя как доставка.
- **Адреса** (`ldo_iiko_user_address`): в `result_modifier` для каждого адреса вычисляются зона (`ZONE_ACTIVE`), ресторан зоны (`RESTAURANT_ACTIVE`), `AVAILABLE`, `ZONE_MIN_ORDER_PRICE`; предвыбран первый доступный; недоступные рендерятся `disabled` с пометкой.
- **Добавление/редактирование адреса**: модальное окно + Яндекс.Карты + `selectize`; сохранение через `addAddress`/`editAddress` (зона определяется `Hlblock::findZoneIdByCoordinates()`, адрес нормализуется `normalizeAddress()` и `stripCityFromAddress()`); при изменении координат зона переопределяется, при редактировании текста без смены точки зона сохраняется.
- **Рестораны** (`RESTORANS_ADRESS`): все с заполненным `XML_ID`; неактивные помечаются «Закрыт» и `disabled`; `MIN_ORDER_PRICE` учитывается только для активных.
- **Время доставки**: radio «Как можно скорее» / «Выбрать дату и время» (`#default_time`, `#date_time`) + `input[type=date|time]`; `validateTimeDelivery()` блокирует submit при незаполненных полях; значения переносятся в скрытые свойства (`syncOrderAddressProps`, `saveDeliveryState`).
- **Итоги** обновляются через `updateAllTotals()` (доставка, база, скидка, итог), `updateDeliveryTime()` (строка «Время доставки»), `updateMinOrderSum()` (минимальная сумма и блокировка кнопки).

---

## 9. AJAX-экшены

Классовые (`BX.ajax.runComponentAction('opensource:order', '<action>', {mode:'class'})`), объявлены в `configureActions()` — [`1127`](../local/components/opensource/order/class.php:1127):

| Экшен | Вызов из JS | Назначение |
|---|---|---|
| `addQuantity` | `sendQuantityUpdate()` | изменение количества, пересчёт бесплатных позиций, `prepareBasketResponse` |
| `deleteProduct` | `sendRemoveRequest()` | удаление позиции |
| `removeCart` | `deleteOrder()` | очистка корзины + `DiscountCouponsManager::clear(true)` |
| `changePromo` | `applyPromoCode()` | применение купона |
| `clearPromo` | **не вызывается** | снятие купонов |
| `setGift` | `sendGiftRequest()` | выбор/отмена подарка |
| `addAddress` / `editAddress` / `deleteAddress` | модалка адресов | CRUD адресов |
| `getBasketItemData` | `BX.LDO.CustomBasket` (внешние вызовы) | состояние товара в корзине |
| `updateDeliveryPrice` / `updateAddressPrice` / `updateRestaurantPrice` | `updateTotalsByDelivery/Address/Restaurant` | пересчёт итогов и времени |
| `saveOrder`, `calculateDeliveries`, `searchLocation` (`ajax.php`) | **не вызывается** боевым шаблоном | устаревший API (использовался `example_*`-шаблонами) |

Все POST-экшены проверяют `check_bitrix_sessid()` вручную (префильтры пустые намеренно).

---

## 10. Найденные недочёты

### Критичные (ломают функциональность)
1. **Подарок не добавляется в заказ** — `GIFT` записывается, но никем не обрабатывается (§5.4.1).
2. **Кнопка «Отменить» у промокода не работает** — `bindPromoEvents` всегда вызывает `applyPromoCode()`, `clearPromoAction` мёртвый (§6.1).
3. **Нет серверной валидации выбранного подарка** (сумма/активность/принадлежность уровню) — можно записать любой ID товара в `GIFT` (§5.4.2).
4. **`$arResultEx` не определён** в `form.php:513` → пустое «Начислено бонусов» и notice.
5. **Блокировка промокода при выборе подарка не снимает применённый купон** — скидка и подарок действуют одновременно (§5.4.3).

### Высокий приоритет
6. `$_SESSION['LDO_IS_PICKUP'] = 'N'` устанавливается в `result_modifier` безусловно — при самовывозе флаг неверен.
7. `removeCartAction` не сбрасывает `LDO_GIFT_ID` и не снимает классы блокировки — «залипание» подарка между заказами.
8. `ajax.php::saveOrderAction` не применяет подарок (и вообще не используется) — риск при возврате к старому API.
9. Расхождение базы для порогов подарков (`PRODUCTS_PRICE` vs JS `totalPrice`) и для минимальной суммы (`PRODUCTS_BASE_PRICE`) (§5.4.5).
10. `prepareBasketResponse` считает скидки без контекста заказа — суммы в AJAX-ответе могут отличаться от финальных (правила по способу доставки не учитываются).
11. `DiscountCouponsManager::clear(true)` при каждом применении промокода **удаляет ранее применённые купоны** (и пользовательские) — неявное поведение.
12. `ensureZoneDeliveryServices()` / `ensureAddressIdProperty()` изменяют конфигурацию БД (классы служб доставки, свойства заказа) прямо из фронтенда + полный сброс кэша на каждом запросе — неожиданные побочные эффекты и просадка производительности.
13. Виртуальные заказы создаются заново в каждом AJAX-запросе (`calculateTotalsInOrderContext`, `getDeliveryPriceFromOrder`) — при быстрых «+/−» нагрузка растёт линейно; корректного кэша нет.
14. `setPersonTypeId(1)` жёстко зашит в расчётах — при других типах плательщика правила/свойства могут отличаться.
15. `getBasketProductsInfo` использует хардкод инфоблока `4` как фолбэк — на мультисайте ссылки/фото могут быть пустыми.

### Средний приоритет
16. Отладочные вызовы в проде: `addMessage2Log($prop)` в цикле по свойствам, `addMessage2Log('deleteProductAction - ошибка в методе')`, временная запись `upload/ldo_free_debug.log` в `Basket::syncFreePositions()`.
17. Тексты ошибок на русском прямо в коде вместо `Loc::getMessage()` («Адрес больше не обслуживается», «Минимальная сумма заказа — N ₽», «Выбранный ресторан недоступен для самовывоза»).
18. `showSuccessMessage` / `showErrorMessage` только пишут в `console` — пользователь не видит ошибок (например, при недоступном адресе AJAX отвечает `success:false`, но интерфейс молчит).
19. Дублирование логики минимальной суммы: сервер (`MIN_ORDER_LOCK/MIN_ORDER_VISIBLE`) и JS (`updateMinOrderSum`).
20. Дублирование адресных/доставочных блоков в `form.php` для мобильной и десктопной вёрстки (два набора `input[name="delivery_id"]`, два списка адресов) — оба в DOM, различия только в CSS; селекторы `:checked` могут вернуть невидимый элемент.
21. `$_SESSION["CATALOG_USER_COUPONS"]` используется без `isset()` — notice при пустой сессии.
22. `bindFormSubmit` проверяет `submitBtn.disabled`, хотя браузер и так не отправляет `disabled`-кнопку; реальная защита дублируется.
23. Проверка «Как можно скорее» дублируется на клиенте и сервере (`validateTimeDelivery` + `applyAsap*DateTime`), при этом свойство `DATE_TIME_DELIVERY` не валидируется сервером, если не помечено обязательным.
24. Композитный кэш включён (`COMPOSITE_FRAME_MODE=A`) при том, что разметка зависит от `$_SESSION` (купоны, подарок) — риск выдачи устаревшей/чужой разметки.
25. Три копии шаблона (`fisheat`, `fisheat_app`, `mobile_app`) — любая правка требует синхронизации.

### Низкий приоритет / косметика
26. `$i == 1;` в `form.php:187` — бессмысленное сравнение.
27. Комментарии-инструкции в коде: `// Добавьте этот метод в класс OpenSourceOrderComponent` (`class.php`), `// Добавьте эти методы в объект BX.LDO.CustomBasket` (`script.js`).
28. Заглушка `updateTotalPrice()` (пустое тело) и дублирующий `updateTotal()`.
29. Метод `updateDeliveryAddress()` помечен как «заглушка», хотя реально используется.

---

## 11. Не используется / мёртвый код

| Сущность | Где | Комментарий |
|---|---|---|
| `findDeliveryPriceByCoordinates()`, `isPointInPolygon()` | [`1587`](../local/components/opensource/order/class.php:1587), [`1625`](../local/components/opensource/order/class.php:1625) | остатки до ZoneDelivery, вызовов нет |
| `\Ldo\Develop\Basket::getNewProductIds()` | [`basket.php:189`](../local/modules/ldo.develop/lib/basket.php:189) | не вызывается |
| `\Ldo\Develop\Basket::getData()` (ветка `syncFreePositions`) | [`basket.php:108`](../local/modules/ldo.develop/lib/basket.php:108) | работает только когда `$freePositionsEnabled`, т.е. фактически лишь из `applyFreePositionsToCurrentBasket` |
| `initGramsBlock()` | [`script.js:1587`](../local/templates/fisheat/components/opensource/order/order-page/script.js:1587) | не вызывается, `this.gramsTotalBlock` нигде не инициализируется |
| `getUserInfo()` | [`class.php:2631`](../local/components/opensource/order/class.php:2631) и [`result_modifier.php:80`](../local/templates/fisheat/components/opensource/order/order-page/result_modifier.php:80) | две реализации одной функции |
| `showPromoCodeBlock()` / `showBonusBlock()` / `hideBonusBlock()` | `script.js` | TODO-заглушки |
| `ajax.php` (`searchLocation`, `calculateDeliveries`, `saveOrder`) | [`local/components/opensource/order/ajax.php`](../local/components/opensource/order/ajax.php:1) | вызываются только `example_*`-шаблонами; есть вторая копия в `local/modules/opensource.order/install/components/opensource/order/ajax.php` |
| `templates/example_person_type`, `templates/example_refresh_delivery` | компонент | демо-шаблоны из коробки, не используются боевым сайтом |
| `templates/.default` | компонент | пустая папка |
| Разметка бонусов acrit | `form.php` | закомментирована, но обработчик события жив |
| `GetOrderId`-ветки `addQuantityAction`/`changePromoAction`/`clearPromoAction` | `class.php` | сработают только если корзина привязана к заказу; на странице оформления корзина обычно ещё не привязана |

---

## 12. Итоговая последовательность применения скидок/подарков/условий

1. **Корзина**: товары пользователя + **бесплатные позиции** (`ldo.develop`, правила по разделам/порциям) — добавляются при входе на страницу оформления.
2. **Промокод** (если применён): `DiscountCouponsManager::add()`, купон учитывается во всех расчётах скидок.
3. **Правила корзины** (в т.ч. зависящие от способа доставки) — считаются в контексте виртуального заказа (`calculateTotalsInOrderContext`) с выбранной службой доставки и адресом.
4. **Минимальная сумма** — по базовым ценам (без скидок), источник: зона адреса (доставка) или ресторан (самовывоз); при нехватке — кнопка `disabled` и показ «Минимальная сумма заказа».
5. **Стоимость доставки** — по зоне адреса через `ZoneDelivery`; для самовывоза — 0.
6. **Подарки** — доступность по порогу `SUM` уровня (сравнение с суммой товаров со скидками); выбор блокирует промокод/бонусы; выбранный ID пишется в свойство `GIFT` (**но в состав заказа не добавляется**).
7. **Итог** = сумма товаров со скидками + доставка; сервер при `save=y` валидирует свойства, доставку, оплату, доступность точки и минимальную сумму, затем сохраняет заказ.

---

## 13. Рекомендации по исправлению (приоритет)

1. Реализовать фактическое добавление подарка в корзину/заказ по `GIFT` (обработчик `OnSaleOrderSaved`/`OnSaleBasketBeforeSaved` или правило корзины с подарком) **и** серверную валидацию доступности в `setGiftAction`.
2. Разделить обработчики кнопки промокода: «Применить» → `changePromo`, «Отменить» → `clearPromo` (плюс снятие класса `block` с блока подарков и очистка `#promocode`).
3. При выборе подарка — либо сбрасывать применённый купон на сервере, либо блокировать выбор подарка при наличии купона/бонусов (паритет с текстом предупреждения).
4. Сбросить `LDO_GIFT_ID` и состояние блокировок в `removeCartAction`/`deleteProductAction` при опустошении корзины.
5. Убрать побочные эффекты из `result_modifier.php`: не вызывать AJAX-экшены и не писать в `$_SESSION` при рендере; перенести расчёт в отдельный лёгкий метод.
6. Единая база сравнения для порогов: и на сервере, и в JS использовать одну и ту же величину (например, `PRODUCTS_BASE_PRICE` без бесплатных позиций и без доставки) — вынести в один метод API.
7. Убрать отладочный код (`addMessage2Log` в цикле, запись лог-файла, временные дампы) и перевести тексты ошибок на `Loc::getMessage()`.
8. Конфигурационные изменения из фронтенда (`ensureZoneDeliveryServices`, `ensureAddressIdProperty`) перенести в миграцию/агента с флагом в опциях, чтобы не сбрасывать кэш на каждом запросе.
9. Избавиться от дублирования шаблона (`fisheat` / `fisheat_app` / `mobile_app`) — вынести общую логику в компонент/один шаблон.
10. Удалить неиспользуемые `ajax.php`, `example_*`-шаблоны и мёртвые методы, либо задокументировать их как внутренний API.
