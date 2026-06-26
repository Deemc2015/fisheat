<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

header('Content-Type: application/json');

if (!CModule::IncludeModule('sale')) {
    echo json_encode(['ids' => []]);
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
    $arBasketItems[] = (int)$arItems['PRODUCT_ID'];
}

echo json_encode(['ids' => $arBasketItems]);
die();
?>
