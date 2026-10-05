<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Верхняя корзина: НЕ учитываем позиции с нулевой ценой
 * (бесплатные позиции/подарки). Количество и сумма считаются
 * только по позициям с ценой больше нуля.
 *
 * @var array $arResult
 */

if (!\Bitrix\Main\Loader::includeModule('sale')) {
    return;
}

try {
    $siteId = \Bitrix\Main\Context::getCurrent()->getSite();
    $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
        \Bitrix\Sale\Fuser::getId(),
        $siteId
    );

    if ($basket instanceof \Bitrix\Sale\Basket) {
        $quantity = 0;
        $totalPrice = 0.0;

        foreach ($basket as $item) {
            /** @var \Bitrix\Sale\BasketItem $item */
            if ((float)$item->getPrice() <= 0) {
                continue;
            }

            $itemQuantity = (float)$item->getQuantity();
            $quantity += $itemQuantity;
            $totalPrice += (float)$item->getPrice() * $itemQuantity;
        }

        $arResult['NUM_PRODUCTS'] = $quantity;
        $arResult['TOTAL_PRICE_RAW'] = $totalPrice;
        $arResult['TOTAL_PRICE'] = \CCurrencyLang::CurrencyFormat(
            $totalPrice,
            \Bitrix\Sale\Internals\SiteCurrencyTable::getSiteCurrency($siteId),
            true
        );
    }
} catch (\Throwable $e) {
    // не ломаем рендер верхней корзины
}
