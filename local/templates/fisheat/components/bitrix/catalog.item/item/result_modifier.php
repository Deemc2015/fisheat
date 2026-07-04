<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}

use Bitrix\Sale\Basket;
use Bitrix\Sale\Fuser;
use Bitrix\Main\Context;

use Bitrix\Main\Loader;

if (Loader::includeModule('sale') && Loader::includeModule('catalog')) {
    $fUserId = Fuser::getId();
    $siteId = Context::getCurrent()->getSite();

    // Загружаем корзину
    $basket = Basket::loadItemsForFUser($fUserId, $siteId);

    // Получаем все товары в корзине
    $basketItems = $basket->getBasketItems();

    // Собираем ID товаров в массив для быстрой проверки
    $basketProductIds = [];
    foreach ($basketItems as $item) {
        $basketProductIds[] = $item->getProductId(); // Получаем ID товара[citation:3]
    }

    // Далее в цикле по $arResult проверяем:
    $arResult['IN_BASKET'] = in_array($arResult['ITEM']['ID'], $basketProductIds);

    unset($basketProductIds);
}
?>