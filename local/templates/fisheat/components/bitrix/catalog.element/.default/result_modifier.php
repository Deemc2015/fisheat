<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @var CBitrixComponentTemplate $this
 * @var CatalogElementComponent $component
 */

$component = $this->getComponent();
$arParams = $component->applyTemplateModifications();

$arResult['NUTRITIONAL'] = [];

if($arResult['PROPERTIES']['ATT_KALLORY']['VALUE']){
    $arResult['NUTRITIONAL']['CALORIES'] = $arResult['PROPERTIES']['ATT_KALLORY']['VALUE'];
}

if($arResult['PROPERTIES']['ATT_BELKI']['VALUE']){
    $arResult['NUTRITIONAL']['BELKI'] = $arResult['PROPERTIES']['ATT_BELKI']['VALUE'];
}

if($arResult['PROPERTIES']['ATT_GIRY']['VALUE']){
    $arResult['NUTRITIONAL']['GIR'] = $arResult['PROPERTIES']['ATT_GIRY']['VALUE'];
}

if($arResult['PROPERTIES']['ATT_YGLEVODY']['VALUE']){
    $arResult['NUTRITIONAL']['YGLEVODY'] = $arResult['PROPERTIES']['ATT_YGLEVODY']['VALUE'];
}


$arBasketItems = [];
$basketItemData = []; // Массив для хранения данных о позициях в корзине

$dbBasketItems = CSaleBasket::GetList(
    ["ID" => "ASC"],
    [
        "FUSER_ID" => CSaleBasket::GetBasketUserID(),
        "LID" => SITE_ID,
        "ORDER_ID" => "NULL"
    ],
    false,
    false,
    ["PRODUCT_ID", "QUANTITY", "PRICE", "ID"] // Добавляем нужные поля
);
while ($arItems = $dbBasketItems->Fetch())
{
    $arBasketItems[] = $arItems['PRODUCT_ID'];

    // Сохраняем данные по каждому товару в корзине, ключ - PRODUCT_ID
    $basketItemData[$arItems['PRODUCT_ID']] = [
        'BASKET_ID' => $arItems['ID'],
        'QUANTITY' => $arItems['QUANTITY'],
        'PRICE' => $arItems['PRICE']
    ];
}

if(in_array($arResult['ID'], $arBasketItems)){
    $arResult['IN_CART'] = true;

    // Добавляем данные конкретного товара из корзины
    $productId = $arResult['ID'];
    if (isset($basketItemData[$productId])) {
        $arResult['BASKET_DATA'] = $basketItemData[$productId];
    }
}


