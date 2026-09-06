<?php
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__);
$_SERVER['SERVER_NAME'] = 'riba';
$_SERVER['HTTP_HOST'] = 'riba';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;

Loader::includeModule('sale');

echo "=== Order props (codes: DATE_TIME_DELIVERY, DEFAULT_TIME, TIME_DELIVERY, ADDRESS) ===\n";
$props = \Bitrix\Sale\Internals\OrderPropsTable::getList([
    'filter' => ['=CODE' => ['DATE_TIME_DELIVERY', 'DEFAULT_TIME', 'TIME_DELIVERY', 'ADDRESS', 'ADDRESS_ID']],
    'select' => ['ID', 'CODE', 'NAME', 'TYPE', 'REQUIRED', 'UTIL', 'PERSON_TYPE_ID', 'DEFAULT_VALUE', 'SETTINGS', 'MULTIPLE'],
]);
while ($p = $props->fetch()) {
    echo "ID={$p['ID']} CODE={$p['CODE']} NAME={$p['NAME']} TYPE={$p['TYPE']} REQUIRED={$p['REQUIRED']} UTIL={$p['UTIL']} PT={$p['PERSON_TYPE_ID']} DEFAULT=" . var_export($p['DEFAULT_VALUE'], true) . "\n";
    echo '  SETTINGS=' . var_export($p['SETTINGS'], true) . "\n";
}

echo "\ndone\n";
