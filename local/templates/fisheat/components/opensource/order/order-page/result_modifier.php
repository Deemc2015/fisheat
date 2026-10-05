<?php
/**
 * MAKING $arResult FROM SCRATCHES
 *
 * @var OpenSourceOrderComponent $component
 */

use Bitrix\Sale\BasketItem;
use Bitrix\Sale\BasketPropertyItem;
use Bitrix\Sale\Delivery;
use Bitrix\Sale\Order;
use Bitrix\Sale\PropertyValue;
use OpenSource\Order\LocationHelper;
use OpenSource\Order\OrderHelper;
use Bitrix\Main\Loader;
use  Ldo\Develop\Product;
use Ldo\Develop\Hlblock;
use Ldo\Develop\Iblock;

$component = &$this->__component;
$order = $component->order;

if (!$order instanceof Order) {
    return;
}

/**
 * ORDER FIELDS
 */
$arResult = $order->getFieldValues();

/**
 * ORDER PROPERTIES
 */
$arResult['PROPERTIES'] = [];
foreach ($order->getPropertyCollection() as $prop) {
    /**
     * @var PropertyValue $prop
     */
    if ($prop->isUtil()) {
        continue;
    }

    $arProp['FORM_NAME'] = 'properties[' . $prop->getField('CODE') . ']';
    $arProp['FORM_LABEL'] = 'property_' . $prop->getField('CODE');

    $arProp['TYPE'] = $prop->getType();
    $arProp['NAME'] = $prop->getName();
    $arProp['VALUE'] = $prop->getValue();
    $arProp['IS_REQUIRED'] = $prop->isRequired();
    $arProp['ERRORS'] = $component->errorCollection->getAllErrorsByCode('PROPERTIES[' . $prop->getField('CODE') . ']');

    switch ($prop->getType()) {
        case 'LOCATION':
            if (!empty($arProp['VALUE'])) {
                $arProp['LOCATION_DATA'] = LocationHelper::getDisplayByCode($arProp['VALUE']);
            }
            break;

        case 'ENUM':
            $arProp['OPTIONS'] = $prop->getPropertyObject()
                ->getOptions();
            break;
    }

    $arResult['PROPERTIES'][$prop->getField('CODE')] = $arProp;

}

// Город доставки по умолчанию — подставляем из настроек доставки (если не заполнен)
if (!empty($arResult['PROPERTIES']['CITY']) && empty($arResult['PROPERTIES']['CITY']['VALUE'])
    && Loader::includeModule('ldo.deliverymap')) {
    $defaultCity = \Ldo\Deliverymap\SettingsTable::get('s1', 'default_city', '');
    if ($defaultCity !== '') {
        $arResult['PROPERTIES']['CITY']['VALUE'] = $defaultCity;
    }
}


function getUserInfo(){

    global $USER;

    $userID = $USER->GetID();

    if(!$userID){
        return false;
    }

    $user = CUser::GetByID($userID)->Fetch();

    if (!$user) {
        return false;
    }

    return array(
        'EMAIL' => $user['EMAIL'],
        'NAME' => $user['NAME'],
        'PHONE' => $user['PERSONAL_PHONE'],
    );
}


/*Адреса доставки пользователя (собственная таблица ldo_iiko_user_address)*/
global $USER;
if(Loader::includeModule('ldo.iiko')){
    $adressList = \Ldo\Iiko\UserAddress::getListForUser((int)$USER->GetID());

    // Для каждого адреса определяем ресторан зоны доставки
    // (XML_ID iiko и название) по сохранённому ID зоны
    if (is_array($adressList) && Loader::includeModule('ldo.deliverymap')) {
        foreach ($adressList as &$adr) {
            $zoneId = (int)($adr['ZONE_ID'] ?? 0);
            $adr['RESTORAN_XML_ID'] = '';
            $adr['RESTORAN_NAME'] = '';

            if ($zoneId > 0) {
                $dbZone = \Ldo\Deliverymap\DeliveryZoneTable::getList([
                    'filter' => ['=ID' => $zoneId],
                    'limit' => 1
                ]);
                $zone = $dbZone->fetch();
                if ($zone && (int)$zone['RESTAURANT_ID'] > 0) {
                    $restaurant = \Ldo\Deliverymap\RestaurantsTable::getById((int)$zone['RESTAURANT_ID']);
                    if ($restaurant) {
                        $adr['RESTORAN_XML_ID'] = (string)($restaurant['XML_ID'] ?? '');
                        $adr['RESTORAN_NAME'] = (string)($restaurant['NAME'] ?? '');
                    }
                }
            }
        }
        unset($adr);
    }

    $arResult['USER_ADRESS'] = $adressList;
}
/**/

/**
 * DELIVERY
 */
$arResult['DELIVERY_ERRORS'] = [];
foreach ($component->errorCollection->getAllErrorsByCode('delivery') as $error) {
    $arResult['DELIVERY_ERRORS'][] = $error;
}

$arResult['DELIVERY_LIST'] = [];
$shipment = OrderHelper::getFirstNonSystemShipment($order);
if ($shipment !== null) {
    $availableDeliveries = Delivery\Services\Manager::getRestrictedObjectsList($shipment);
    $allDeliveryIDs = $order->getDeliveryIdList();
    $checkedDeliveryId = end($allDeliveryIDs);

    foreach (OrderHelper::calcDeliveries($shipment, $availableDeliveries) as $deliveryID => $calculationResult) {
        /**
         * @var Delivery\Services\Base $obDelivery
         */
        $obDelivery = $availableDeliveries[$deliveryID];

        $arDelivery = [];
        $arDelivery['ID'] = $obDelivery->getId();
        $arDelivery['NAME'] = $obDelivery->getName();
        $arDelivery['CHECKED'] = $checkedDeliveryId === $obDelivery->getId();
        $arDelivery['PRICE'] = $calculationResult->getPrice();
        $arDelivery['PRICE_DISPLAY'] = SaleFormatCurrency(
            $calculationResult->getDeliveryPrice(),
            $order->getCurrency()
        );

        $arResult['DELIVERY_LIST'][$deliveryID] = $arDelivery;
    }
}


/**
 * PAY SYSTEM
 */
$arResult['PAY_SYSTEM_ERRORS'] = [];
foreach ($component->errorCollection->getAllErrorsByCode('payment') as $error) {
    $arResult['PAY_SYSTEM_ERRORS'][] = $error;
}

$arResult['PAY_SYSTEM_LIST'] = [];
$availablePaySystem = OrderHelper::getAvailablePaySystems($order);
$checkedPaySystemId = 0;
if (!$order->getPaymentCollection()->isEmpty()) {
    $payment = $order->getPaymentCollection()->current();
    $checkedPaySystemId = $payment->getPaymentSystemId();
}
foreach ($availablePaySystem as $paySystem) {
    $arPaySystem = [];

    $arPaySystem['ID'] = $paySystem->getField('ID');
    $arPaySystem['NAME'] = $paySystem->getField('NAME');
    $arPaySystem['CHECKED'] = $arPaySystem['ID'] === $checkedPaySystemId;

    $arResult['PAY_SYSTEM_LIST'][$arPaySystem['ID']] = $arPaySystem;
}

/**
 * BASKET
 */
$arResult['BASKET'] = [];
foreach ($order->getBasket() as $basketItem) {

    /**
     * @var BasketItem $basketItem
     */
    $arBasketItem = [];
    $arBasketItem['ID'] = $basketItem->getId();
    $arBasketItem['NAME'] = $basketItem->getField('NAME');
    $arBasketItem['CURRENCY'] = $basketItem->getCurrency();
    $arBasketItem['PRODUCT_ID'] = $basketItem->getField('PRODUCT_ID');
    $arProduct = CCatalogProduct::GetByID($arBasketItem['PRODUCT_ID']);
    $arBasketItem['COUNT_AVALIABLE'] = $arProduct['QUANTITY'];
    if(Loader::IncludeModule('ldo.develop')){
        $dataProduct = Product::getDataById($arBasketItem['PRODUCT_ID']);
        if($dataProduct){

            $arBasketItem['LINK'] = $dataProduct['DETAIL_PAGE_URL'];


            $image = $dataProduct['DETAIL_PICTURE'];

            if(empty($image)){
                $image = $dataProduct['PREVIEW_PICTURE'];
            }


            if($image){

                $imageProduct = \CFile::ResizeImageGet($image, array('width'=>100, 'height'=>100), BX_RESIZE_IMAGE_PROPORTIONAL, true);

                if(is_array($imageProduct)){
                    $arBasketItem['IMAGE'] = $imageProduct['src'];
                }
            }
        }
    }

    $arBasketItem['PROPERTIES'] = [];

    foreach ($basketItem->getPropertyCollection() as $basketPropertyItem):
        /**
         * @var BasketPropertyItem $basketPropertyItem
         */
        $propCode = $basketPropertyItem->getField('CODE');
        if ($propCode !== 'CATALOG.XML_ID' && $propCode !== 'PRODUCT.XML_ID') {
            $arBasketItem['PROPERTIES'][] = [
                'NAME' => $basketPropertyItem->getField('NAME'),
                'VALUE' => $basketPropertyItem->getField('VALUE'),
            ];
        }
    endforeach;

    $arBasketItem['WEIGHT'] = (int)$basketItem->getWeight();

    $arBasketItem['QUANTITY'] = $basketItem->getQuantity();
    $arBasketItem['QUANTITY_DISPLAY'] = $basketItem->getQuantity();
    $arBasketItem['QUANTITY_DISPLAY'] .= ' ' . $basketItem->getField('MEASURE_NAME');

    $arBasketItem['BASE_PRICE'] = $basketItem->getBasePrice();
    $arBasketItem['BASE_PRICE_DISPLAY'] = SaleFormatCurrency(
        $arBasketItem['BASE_PRICE'],
        $arBasketItem['CURRENCY']
    );

    $arBasketItem['PRICE'] = $basketItem->getPrice();
    $arBasketItem['PRICE_DISPLAY'] = SaleFormatCurrency(
        $arBasketItem['PRICE'],
        $arBasketItem['CURRENCY']
    );

    $arBasketItem['SUM'] = $basketItem->getPrice() * $basketItem->getQuantity();
    $arBasketItem['SUM_DISPLAY'] = SaleFormatCurrency(
        $arBasketItem['SUM'],
        $arBasketItem['CURRENCY']
    );

    $arResult['BASKET'][$arBasketItem['ID']] = $arBasketItem;
}

/**
 * ORDER TOTAL BASKET PRICES
 */
//Стоимость товаров без скидок
$arResult['PRODUCTS_BASE_PRICE'] = $order->getBasket()->getBasePrice();
$arResult['PRODUCTS_BASE_PRICE_DISPLAY'] = SaleFormatCurrency(
    $arResult['PRODUCTS_BASE_PRICE'],
    $arResult['CURRENCY']
);

//Стоимость товаров со скидами
$arResult['PRODUCTS_PRICE'] = $order->getBasket()->getPrice();
$arResult['PRODUCTS_PRICE_DISPLAY'] = SaleFormatCurrency(
    $arResult['PRODUCTS_PRICE'],
    $arResult['CURRENCY']
);

//Скидка на товары
$arResult['PRODUCTS_DISCOUNT'] = $arResult['PRODUCTS_BASE_PRICE'] - $arResult['PRODUCTS_PRICE'];
$arResult['PRODUCTS_DISCOUNT_DISPLAY'] = SaleFormatCurrency(
    $arResult['PRODUCTS_DISCOUNT'],
    $arResult['CURRENCY']
);

/**
 * ORDER TOTAL DELIVERY PRICES
 */
$arShowPrices = $order->getDiscount()
    ->getShowPrices();

//Стоимость доставки без скидок
$arResult['DELIVERY_BASE_PRICE'] = $arShowPrices['DELIVERY']['BASE_PRICE'] ?? 0;
$arResult['DELIVERY_BASE_PRICE_DISPLAY'] = SaleFormatCurrency(
    $arResult['DELIVERY_BASE_PRICE'],
    $arResult['CURRENCY']
);

//Стоимость доставки с учетом скидок
$arResult['DELIVERY_PRICE'] = $order->getDeliveryPrice();

// Сохраняем цену доставки в сессию для AJAX-запросов (addQuantityAction и др.)
$_SESSION['LDO_DELIVERY_PRICE'] = (float)$arResult['DELIVERY_PRICE'];
$_SESSION['LDO_IS_PICKUP'] = 'N';



$arResult['DELIVERY_PRICE_DISPLAY'] = SaleFormatCurrency(
    $arResult['DELIVERY_PRICE'],
    $arResult['CURRENCY']
);

/**
 * Время доставки (минуты) зоны выбранного адреса — для первичного рендера,
 * чтобы строка «Время доставки» была заполнена сразу, без ожидания AJAX.
 */
$selectedAddressId = (int)($arResult['PROPERTIES']['ADDRESS_ID']['VALUE'] ?? 0);
if ($selectedAddressId <= 0 && !empty($arResult['USER_ADRESS'])) {
    foreach ($arResult['USER_ADRESS'] as $userAddress) {
        if (!empty($userAddress['CHECKED'])) {
            $selectedAddressId = (int)$userAddress['ID'];
            break;
        }
    }
}
$deliveryTimeWindow = $component->getAddressTimeWindow($selectedAddressId);
$arResult['DELIVERY_TIME_FROM'] = (int)$deliveryTimeWindow['from'];
$arResult['DELIVERY_TIME_TO'] = (int)$deliveryTimeWindow['to'];

//Скидка на доставку
$arResult['DELIVERY_DISCOUNT'] = $arShowPrices['DELIVERY']['DISCOUNT'] ?? 0;
$arResult['DELIVERY_DISCOUNT_DISPLAY'] = SaleFormatCurrency(
    $arResult['DELIVERY_PRICE'],
    $arResult['CURRENCY']
);


//Выводим список подарков в корзине (данные из таблицы модуля ldo.marketing)
if (!\Bitrix\Main\Loader::includeModule('ldo.marketing')
    && !class_exists('\\Ldo\\Marketing\\GiftsTable')) {
    $giftsTableFile = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/ldo.marketing/lib/GiftsTable.php';
    if (is_file($giftsTableFile)) {
        require_once $giftsTableFile;
    }
}
if (!class_exists('\\Ldo\\Marketing\\Settings')) {
    $marketingSettingsFile = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/ldo.marketing/lib/Settings.php';
    if (is_file($marketingSettingsFile)) {
        require_once $marketingSettingsFile;
    }
}

$arrProducts = []; // Инициализируем массив

if (class_exists('\\Ldo\\Marketing\\GiftsTable')) {
    $giftLevels = \Ldo\Marketing\GiftsTable::getList([
        'filter' => [
            '=ACTIVE'  => 'Y',
            '=SITE_ID' => \Bitrix\Main\Context::getCurrent()->getSite() ?: 's1',
        ],
        'order'  => ['SORT' => 'ASC', 'ID' => 'ASC'],
    ])->fetchAll();

    foreach ($giftLevels as $giftLevel) {
        $giftProductIds = json_decode((string)($giftLevel['PRODUCT_IDS'] ?? ''), true);
        if (!is_array($giftProductIds)) {
            continue;
        }
        foreach ($giftProductIds as $giftProductId) {
            $giftProductId = (int)$giftProductId;
            if ($giftProductId <= 0) {
                continue;
            }
            $arrProducts['GIFTS'][$giftLevel['NAME']][] = [
                'PRODUCT_ID' => $giftProductId,
                'SUM_LEVEL' => (float)$giftLevel['SUM']
            ];
        }
    }
}

$dataProducts = [];

if (!empty($arrProducts['GIFTS'])) {
    foreach ($arrProducts['GIFTS'] as $key => $data) {
        // Собираем все ID товаров
        $productIds = array_column($data, 'PRODUCT_ID');

        // Получаем информацию о товарах из настроенного каталога (настройка модуля ldo.marketing)
        $catalogIblockId = \Ldo\Marketing\Settings::getCatalogIblockId();
        $products = [];
        if ($catalogIblockId > 0 && !empty($productIds)) {
            $rsProducts = \CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => $catalogIblockId, '=ID' => $productIds],
                false,
                false,
                ['ID', 'NAME', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']
            );
            while ($productRow = $rsProducts->Fetch()) {
                $products[] = $productRow;
            }
        }

        if ($products) {
            $sumLevel = (int)$data[0]['SUM_LEVEL'];
            $currentSum = (int)$arResult['PRODUCTS_PRICE'];

            // Определяем доступность подарка
            $isAvailable = ($currentSum >= $sumLevel);
            $sumFree = $isAvailable ? 0 : ($sumLevel - $currentSum);

            foreach ($products as $product) {

                $pictureId = (int)$product['PREVIEW_PICTURE'] > 0 ? (int)$product['PREVIEW_PICTURE'] : (int)$product['DETAIL_PICTURE'];
                $img = '';
                if ($pictureId > 0) {
                    $arrImg = CFile::ResizeImageGet($pictureId, array('width'=>150, 'height'=>150), BX_RESIZE_IMAGE_PROPORTIONAL, true);
                    $img = is_array($arrImg) ? $arrImg['src'] : '';
                }

                $dataProducts[$key][] = [
                    'ID' => $product['ID'],
                    'NAME' => $product['NAME'],
                    'PREVIEW_PICTURE' => $img,
                    'SUM_LEVEL' => $sumLevel,
                    'AVAILABLE' => $isAvailable,
                    'SUM_FREE' => $sumFree,
                    'CURRENT_SUM' => $currentSum   // Текущая сумма корзины
                ];
            }
        }
    }

    // Сортируем уровни по SUM_LEVEL (от меньшего к большему)
    if (!empty($dataProducts)) {
        uasort($dataProducts, function($a, $b) {
            return $a[0]['SUM_LEVEL'] - $b[0]['SUM_LEVEL'];
        });

        // Добавляем информацию о ближайшем недоступном подарке
        $nearestGift = null;
        foreach ($dataProducts as $level => $giftData) {
            if (!$giftData[0]['AVAILABLE']) {
                $nearestGift = [
                    'LEVEL' => $level,
                    'SUM_FREE' => $giftData[0]['SUM_FREE'],
                    'SUM_LEVEL' => $giftData[0]['SUM_LEVEL'],
                ];
                break;
            }
        }

        // Добавляем в результат
        $arResult['GIFTS'] = $dataProducts;
        $arResult['NEAREST_GIFT'] = $nearestGift; // Ближайший недоступный подарок

        // Добавляем информацию для быстрого доступа в шаблоне
        $arResult['GIFTS_SUMMARY'] = [
            'CURRENT_SUM' => $currentSum,
            'HAS_AVAILABLE' => !empty(array_filter($dataProducts, function($gift) {
                return $gift[0]['AVAILABLE'];
            })),
            'NEXT_LEVEL' => $nearestGift ? $nearestGift['LEVEL'] : null,
            'NEXT_SUM_FREE' => $nearestGift ? $nearestGift['SUM_FREE'] : 0
        ];
    }
}


/**
 * ORDER TOTAL PRICES
 */
//Общая цена без скидок
$arResult['SUM_BASE'] = $arResult['PRODUCTS_BASE_PRICE'] + $arResult['DELIVERY_BASE_PRICE'];
$arResult['SUM_BASE_DISPLAY'] = SaleFormatCurrency(
    $arResult['SUM_BASE'],
    $arResult['CURRENCY']
);

//Общая скидка
$arResult['DISCOUNT_VALUE'] = $arResult['SUM_BASE'] - $order->getPrice();
$arResult['DISCOUNT_VALUE_DISPLAY'] = SaleFormatCurrency(
    $arResult['DISCOUNT_VALUE'],
    $arResult['CURRENCY']
);

//К оплате
$arResult['SUM'] = $order->getPrice();
$arResult['SUM_DISPLAY'] = SaleFormatCurrency(
    $arResult['SUM'],
    $arResult['CURRENCY']
);

/**
 * Если адрес доставки уже выбран, пересчитываем суммы на сервере тем же способом,
 * что и AJAX (по зоне доставки), чтобы при первой загрузке цифры не «прыгали».
 */
if (!empty($selectedAddressId)) {
    $selectedDeliveryId = 0;
    foreach ($arResult['DELIVERY_LIST'] as $deliveryItem) {
        if (!empty($deliveryItem['CHECKED'])) {
            $selectedDeliveryId = (int)$deliveryItem['ID'];
            break;
        }
    }
    if ($selectedDeliveryId <= 0) {
        $selectedDeliveryId = (int)($arParams['DEFAULT_DELIVERY_ID'] ?? 0);
    }

    $addressPreview = $component->updateAddressPriceAction([
        'addressId' => $selectedAddressId,
        'deliveryId' => $selectedDeliveryId,
    ]);

    if (!empty($addressPreview['success'])) {
        $previewDeliveryPrice = (float)$addressPreview['deliveryPrice'];
        $previewBaseSum = (float)$addressPreview['baseSum'];
        $previewDiscount = (float)$addressPreview['discount'];
        $previewTotal = (float)$addressPreview['totalPrice'];

        $arResult['DELIVERY_PRICE'] = $previewDeliveryPrice;
        $arResult['DELIVERY_PRICE_DISPLAY'] = SaleFormatCurrency($previewDeliveryPrice, $arResult['CURRENCY']);

        $arResult['SUM_BASE'] = $previewBaseSum;
        $arResult['SUM_BASE_DISPLAY'] = SaleFormatCurrency($previewBaseSum, $arResult['CURRENCY']);

        $arResult['DISCOUNT_VALUE'] = $previewDiscount;
        $arResult['DISCOUNT_VALUE_DISPLAY'] = SaleFormatCurrency($previewDiscount, $arResult['CURRENCY']);

        $arResult['SUM'] = $previewTotal;
        $arResult['SUM_DISPLAY'] = SaleFormatCurrency($previewTotal, $arResult['CURRENCY']);

        $_SESSION['LDO_DELIVERY_PRICE'] = $previewDeliveryPrice;
    }
}


/*Рестораны для самовывоза*/
$arResult['RESTORAN_ADRESS'] = [];
if (Loader::includeModule('ldo.deliverymap')) {
    // Выводим только рестораны с заполненным XML_ID
    $restaurants = array_filter(
        \Ldo\Deliverymap\RestaurantsTable::getActiveList(),
        static function ($restaurant) {
            return !empty($restaurant['XML_ID']);
        }
    );
    if (!empty($restaurants)) {
        $checked = false;
        foreach ($restaurants as $restaurant) {
            $arResult['RESTORAN_ADRESS'][] = [
                'ID' => (int)$restaurant['ID'],
                'XML_ID' => $restaurant['XML_ID'],
                'NAME' => $restaurant['NAME'],
                'CHECKED' => !$checked ? 'Y' : 'N',
            ];
            if (!$checked) {
                $checked = true;
            }
        }
    }
}

/* Настройки Яндекс.Карт для модального окна добавления адреса */
$arResult['YANDEX_SETTINGS'] = [];
if (Loader::includeModule('ldo.deliverymap')) {
    $arResult['YANDEX_SETTINGS'] = [
        'YANDEX_API_KEY' => \Ldo\Deliverymap\SettingsTable::get('s1', 'yandex_api_key', ''),
        'DEFAULT_LAT' => (float)\Ldo\Deliverymap\SettingsTable::get('s1', 'default_lat', '54.7355'),
        'DEFAULT_LNG' => (float)\Ldo\Deliverymap\SettingsTable::get('s1', 'default_lng', '55.9587'),
        'DEFAULT_ZOOM' => (int)\Ldo\Deliverymap\SettingsTable::get('s1', 'default_zoom', '11'),
    ];
}