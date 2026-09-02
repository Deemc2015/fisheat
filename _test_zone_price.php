<?php
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__);
$_SERVER['SERVER_NAME'] = 'riba';
$_SERVER['HTTP_HOST'] = 'riba';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Delivery\Services\Table as DeliveryTable;
use Bitrix\Sale\Order;

Loader::includeModule('sale');
Loader::includeModule('ldo.deliverymap');
Loader::includeModule('ldo.develop');
Loader::includeModule('highloadblock');

$row = DeliveryTable::getRowById(2);
echo 'Delivery ID=2 CLASS=' . $row['CLASS_NAME'] . "\n";

$svc = new \Ldo\Deliverymap\DeliveryServices\ZoneDelivery($row);

foreach ([43, 44, 999999] as $addressId) {
    $order = Order::create('s1', 1);
    $order->setPersonTypeId(1);
    $order->setBasket(Basket::create('s1'));

    foreach ($order->getPropertyCollection() as $p) {
        if ($p->getField('CODE') === 'ADDRESS_ID') {
            $p->setValue($addressId);
            break;
        }
    }

    $shipment = $order->getShipmentCollection()->createItem($svc);
    $res = $svc->calculate($shipment);

    echo "Address {$addressId}: price=" . $res->getPrice()
        . ' success=' . ($res->isSuccess() ? 'Y' : 'N')
        . ' errors=' . implode('; ', $res->getErrorMessages())
        . ' data=' . json_encode($res->getData(), JSON_UNESCAPED_UNICODE)
        . "\n";
}

echo "\ndone\n";
