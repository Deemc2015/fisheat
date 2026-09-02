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

// Восстанавливаем служебную службу «Без доставки» (ID=1) — её не должен был затронуть самовосстановление
$row = DeliveryTable::getRowById(1);
if ($row && mb_stripos((string)$row['CLASS_NAME'], 'EmptyDeliveryService') === false) {
    DeliveryTable::update(1, ['CLASS_NAME' => '\\Bitrix\\Sale\\Delivery\\Services\\EmptyDeliveryService']);
    \Bitrix\Main\Data\Cache::clearCache(true);
    echo "ID=1 'Без доставки' restored to EmptyDeliveryService\n";
}

echo "=== Delivery services final ===\n";
$res = DeliveryTable::getList(['select' => ['ID', 'NAME', 'CLASS_NAME', 'ACTIVE']]);
while ($r = $res->fetch()) {
    echo "ID={$r['ID']} NAME={$r['NAME']} CLASS={$r['CLASS_NAME']} ACTIVE={$r['ACTIVE']}\n";
}

echo "\n=== ADDRESS_ID order props ===\n";
$props = \Bitrix\Sale\Internals\OrderPropsTable::getList([
    'filter' => ['=CODE' => 'ADDRESS_ID'],
    'select' => ['ID', 'PERSON_TYPE_ID', 'UTIL'],
]);
$cnt = 0;
while ($p = $props->fetch()) {
    echo "ID={$p['ID']} PT={$p['PERSON_TYPE_ID']} UTIL={$p['UTIL']}\n";
    $cnt++;
}
echo ($cnt === 0 ? 'NOT created' : 'created: ' . $cnt) . "\n";

echo "\ndone\n";
