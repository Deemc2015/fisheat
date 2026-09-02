<?php
use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('ldo.deliverymap', [
    'Ldo\\Deliverymap\\DeliveryZoneTable' => 'lib/DeliveryZoneTable.php',
    'Ldo\\Deliverymap\\SettingsTable' => 'lib/SettingsTable.php',
    'Ldo\\Deliverymap\\RestaurantsTable' => 'lib/RestaurantsTable.php',
    'Ldo\\Deliverymap\\DeliveryServices\\ZoneDelivery' => 'lib/DeliveryServices/ZoneDelivery.php',
]);