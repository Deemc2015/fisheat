<?php
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__);
$_SERVER['SERVER_NAME'] = 'riba';
$_SERVER['HTTP_HOST'] = 'riba';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Sale\Delivery\Services\Table as DeliveryTable;

Loader::includeModule('sale');
Loader::includeModule('ldo.deliverymap');

echo "=== Delivery services (b_sale_delivery_srv) ===\n";
$res = DeliveryTable::getList(['select' => ['ID', 'NAME', 'CLASS_NAME', 'ACTIVE', 'PARENT_ID']]);
while ($r = $res->fetch()) {
    echo "ID={$r['ID']} NAME={$r['NAME']} CLASS={$r['CLASS_NAME']} ACTIVE={$r['ACTIVE']} PARENT={$r['PARENT_ID']}\n";
}

echo "\n=== Order props ADDRESS_ID ===\n";
$props = \Bitrix\Sale\Internals\OrderPropsTable::getList([
    'filter' => ['=CODE' => 'ADDRESS_ID'],
    'select' => ['ID', 'CODE', 'PERSON_TYPE_ID', 'UTIL'],
]);
$cnt = 0;
while ($p = $props->fetch()) {
    echo "ID={$p['ID']} CODE={$p['CODE']} PT={$p['PERSON_TYPE_ID']} UTIL={$p['UTIL']}\n";
    $cnt++;
}
if ($cnt === 0) {
    echo "ADDRESS_ID property NOT found\n";
}

echo "\n=== Delivery zones ===\n";
if (Loader::includeModule('ldo.deliverymap')) {
    $zones = \Ldo\Deliverymap\DeliveryZoneTable::getList([
        'select' => ['ID', 'NAME', 'PRICE', 'ACTIVE', 'SITE_ID', 'MIN_ORDER_PRICE', 'FREE_DELIVERY_PRICE', 'RESTAURANT_ID'],
        'order' => ['ID' => 'ASC'],
    ]);
    while ($z = $zones->fetch()) {
        echo "ID={$z['ID']} NAME={$z['NAME']} PRICE={$z['PRICE']} ACTIVE={$z['ACTIVE']} SITE={$z['SITE_ID']} MIN={$z['MIN_ORDER_PRICE']} FREE={$z['FREE_DELIVERY_PRICE']} REST={$z['RESTAURANT_ID']}\n";
    }
}

echo "\n=== User addresses (adress_user) ===\n";
Loader::includeModule('highloadblock');
Loader::includeModule('ldo.develop');
$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getRow(['filter' => ['=TABLE_NAME' => 'adress_user']]);
if ($hlblock) {
    $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock)->getDataClass();
    $addrs = $entity::getList(['select' => ['ID', 'UF_ADDRESS', 'UF_ZONE_ID', 'UF_SHIRINA', 'UF_DOLGOTA', 'UF_PRICE', 'UF_USER_ID']]);
    while ($a = $addrs->fetch()) {
        echo "ID={$a['ID']} ADDR={$a['UF_ADDRESS']} ZONE={$a['UF_ZONE_ID']} LAT={$a['UF_SHIRINA']} LON={$a['UF_DOLGOTA']} PRICE={$a['UF_PRICE']} USER={$a['UF_USER_ID']}\n";
    }
} else {
    echo "adress_user HL not found\n";
}

echo "\ndone\n";
