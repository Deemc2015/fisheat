<?php
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__);
$_SERVER['SERVER_NAME'] = 'riba';
$_SERVER['HTTP_HOST'] = 'riba';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Order;

Loader::includeModule('sale');
Loader::includeModule('catalog');

echo "=== Валидация свойств при выборе «Ко времени» ===\n";

// Тестовые значения как отправляет JS fisheat
$cases = [
    'Ко времени (дата+время в DATE_TIME_DELIVERY, время в TIME_DELIVERY)' => [
        'DEFAULT_TIME' => 'N',
        'TIME_DELIVERY' => '14:30',
        'DATE_TIME_DELIVERY' => '2026-09-03 14:30',
    ],
    'Ко времени (только ISO дата+время в DATE_TIME_DELIVERY, TIME_DELIVERY пусто)' => [
        'DEFAULT_TIME' => 'N',
        'TIME_DELIVERY' => '',
        'DATE_TIME_DELIVERY' => '2026-09-03 14:30',
    ],
    'Ко времени (РФ формат даты в DATE_TIME_DELIVERY)' => [
        'DEFAULT_TIME' => 'N',
        'TIME_DELIVERY' => '',
        'DATE_TIME_DELIVERY' => '03.09.2026 14:30',
    ],
    'Как можно скорее' => [
        'DEFAULT_TIME' => 'Y',
        'TIME_DELIVERY' => '',
        'DATE_TIME_DELIVERY' => '',
    ],
];

foreach ($cases as $label => $values) {
    echo "\n--- {$label} ---\n";
    try {
        $order = Order::create('s1', 1);
        $order->setPersonTypeId(1);
        $order->setBasket(Basket::create('s1'));

        foreach ($order->getPropertyCollection() as $p) {
            $code = $p->getField('CODE');
            if (array_key_exists($code, $values)) {
                $set = $p->setValue($values[$code]);
                echo "set {$code} = '{$values[$code]}' => " . ($set->isSuccess() ? 'OK' : 'ERR: ' . implode('; ', $set->getErrorMessages())) . "\n";

                $cv = $p->checkValue($code, $p->getValue());
                echo "  checkValue => " . ($cv->isSuccess() ? 'OK' : 'ERR: ' . implode('; ', $cv->getErrorMessages())) . "\n";

                $rv = $p->checkRequiredValue($code, $p->getValue());
                echo "  checkRequired => " . ($rv->isSuccess() ? 'OK' : 'ERR: ' . implode('; ', $rv->getErrorMessages())) . "\n";
            }
        }
    } catch (\Exception $e) {
        echo 'EXCEPTION: ' . $e->getMessage() . "\n";
    }
}

echo "\ndone\n";
