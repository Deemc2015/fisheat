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


use Bitrix\Main\Data\Cache;

$cache = Cache::createInstance();
$cacheId = 'catalog_element_basket_' . $arResult['ID'] . '_' . CSaleBasket::GetBasketUserID();
$cacheTime = 3600; // 1 час
$cacheDir = '/catalog/element/basket';

if ($cache->initCache($cacheTime, $cacheId, $cacheDir)) {
    // Данные есть в кеше
    $vars = $cache->getVars();
    $arResult['BASKET_DATA'] = $vars['BASKET_DATA'];
    $arResult['IN_CART'] = $vars['IN_CART'];
} else {
    // Кеша нет - получаем данные корзины
    $arBasketItems = [];
    $basketItemData = [];

    $dbBasketItems = CSaleBasket::GetList(
        ["ID" => "ASC"],
        [
            "FUSER_ID" => CSaleBasket::GetBasketUserID(),
            "LID" => SITE_ID,
            "ORDER_ID" => "NULL"
        ],
        false,
        false,
        ["PRODUCT_ID", "QUANTITY", "PRICE", "ID"]
    );

    while ($arItems = $dbBasketItems->Fetch())
    {
        $arBasketItems[] = $arItems['PRODUCT_ID'];
        $basketItemData[$arItems['PRODUCT_ID']] = [
            'BASKET_ID' => $arItems['ID'],
            'QUANTITY' => $arItems['QUANTITY'],
            'PRICE' => $arItems['PRICE']
        ];
    }

    $inCart = in_array($arResult['ID'], $arBasketItems);
    $basketData = $basketItemData[$arResult['ID']] ?? null;

    // Сохраняем в кеш
    if ($cache->startDataCache($cacheTime, $cacheId, $cacheDir)) {
        $cache->endDataCache([
            'BASKET_DATA' => $basketData,
            'IN_CART' => $inCart
        ]);
    }

    $arResult['BASKET_DATA'] = $basketData;
    $arResult['IN_CART'] = $inCart;
}


