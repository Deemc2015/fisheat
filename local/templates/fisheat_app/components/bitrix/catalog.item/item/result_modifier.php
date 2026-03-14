<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}



$arBasketItems = [];

$dbBasketItems = CSaleBasket::GetList(
    ["ID" => "ASC"],
    [
        "FUSER_ID" => CSaleBasket::GetBasketUserID(),
        "LID" => SITE_ID,
        "ORDER_ID" => "NULL"
    ],
    false,
    false,
    ["PRODUCT_ID"]
);
while ($arItems = $dbBasketItems->Fetch())
{
    $arBasketItems[] = $arItems['PRODUCT_ID'];
}

$arResult['IN_CART'] = $arBasketItems;