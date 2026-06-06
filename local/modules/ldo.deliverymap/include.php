<?php
use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('ldo.deliverymap', [
    'Ldo\\Deliverymap\\DeliveryZoneTable' => 'lib/DeliveryZoneTable.php',
    'Ldo\\Deliverymap\\Admin\\ZoneEdit' => 'lib/Admin/ZoneEdit.php',
]);