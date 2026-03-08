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


/*Адреса доставки пользователя*/
if(Loader::includeModule('ldo.develop')){
    $adressList = Hlblock::getAdressList();

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
    if(Loader::IncludeModule('ldo.develop')){
        $dataProduct = Product::getDataById($arBasketItem['PRODUCT_ID']);
        if($dataProduct){

            $arBasketItem['LINK'] = $dataProduct['DETAIL_PAGE_URL'];

            if($dataProduct['PREVIEW_PICTURE']){

                $imageProduct = \CFile::ResizeImageGet($dataProduct['PREVIEW_PICTURE'], array('width'=>100, 'height'=>100), BX_RESIZE_IMAGE_PROPORTIONAL, true);

                if(is_array($imageProduct)){
                    $arBasketItem['IMAGE'] = $imageProduct['src'];
                }
            }
        }
    }


    $db_props = CIBlockElement::GetProperty(4, $basketItem->getField('PRODUCT_ID'), array("sort" => "asc"), array("CODE"=>"ATT_VES"));
    $ar_props = $db_props->Fetch();
    if($ar_props['VALUE']){
        $arBasketItem['VES'] = $ar_props['VALUE'];
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



$arResult['DELIVERY_PRICE_DISPLAY'] = SaleFormatCurrency(
    $arResult['DELIVERY_PRICE'],
    $arResult['CURRENCY']
);

//Скидка на доставку
$arResult['DELIVERY_DISCOUNT'] = $arShowPrices['DELIVERY']['DISCOUNT'] ?? 0;
$arResult['DELIVERY_DISCOUNT_DISPLAY'] = SaleFormatCurrency(
    $arResult['DELIVERY_PRICE'],
    $arResult['CURRENCY']
);


//Выводим список подарков в корзине
$idProducts = Iblock::getList('gifts', [
    'NAME',
    'PRODUCT_ID' => 'ATT_PRODUCT.VALUE',
    'SUM_LEVEL' => 'ATT_SUM_CART.VALUE'
]);

$arrProducts = []; // Инициализируем массив

if ($idProducts) {
    foreach ($idProducts as $gift) {
        $arrProducts['GIFTS'][$gift['NAME']][] = [
            'PRODUCT_ID' => (int)$gift['PRODUCT_ID'],
            'SUM_LEVEL' => (float)$gift['SUM_LEVEL']
        ];
    }
}

$dataProducts = [];

if (!empty($arrProducts['GIFTS'])) {
    foreach ($arrProducts['GIFTS'] as $key => $data) {
        // Собираем все ID товаров
        $productIds = array_column($data, 'PRODUCT_ID');

        // Получаем информацию о товарах
        $products = Iblock::getList('catalog',
            ['ID', 'NAME', 'PREVIEW_PICTURE'],
            ['ID' => $productIds]
        );

        if ($products) {
            $sumLevel = (int)$data[0]['SUM_LEVEL'];
            $currentSum = (int)$arResult['PRODUCTS_PRICE'];

            // Определяем доступность подарка
            $isAvailable = ($currentSum >= $sumLevel);
            $sumFree = $isAvailable ? 0 : ($sumLevel - $currentSum);

            foreach ($products as $product) {

                $arrImg = CFile::ResizeImageGet($product['PREVIEW_PICTURE'], array('width'=>150, 'height'=>150), BX_RESIZE_IMAGE_PROPORTIONAL, true);
                $img = $arrImg['src'];

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