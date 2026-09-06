<?php
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__);
$_SERVER['SERVER_NAME'] = 'riba';
$_SERVER['HTTP_HOST'] = 'riba';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;

Loader::includeModule('sale');

echo "=== Последние заказы: значения DATE_TIME_DELIVERY / DEFAULT_TIME / TIME_DELIVERY ===\n";

$orders = \Bitrix\Sale\Internals\OrderTable::getList([
    'order' => ['ID' => 'DESC'],
    'limit' => 20,
    'select' => ['ID'],
])->fetchAll();

foreach ($orders as $o) {
    $order = \Bitrix\Sale\Order::load($o['ID']);
    if (!$order) continue;
    $vals = [];
    foreach ($order->getPropertyCollection() as $p) {
        $code = $p->getField('CODE');
        if (in_array($code, ['DATE_TIME_DELIVERY', 'DEFAULT_TIME', 'TIME_DELIVERY'], true)) {
            $vals[$code] = (string)$p->getValue();
        }
    }
    if ($vals) {
        echo "Order {$o['ID']}: " . json_encode($vals, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n=== Форматы даты сайта ===\n";
echo 'FORMAT_DATE=' . \Bitrix\Main\Context::getCurrent()->getCulture()->getDateFormat() . "\n";
echo 'FORMAT_DATETIME=' . \Bitrix\Main\Context::getCurrent()->getCulture()->getDateTimeFormat() . "\n";

echo "\ndone\n";
