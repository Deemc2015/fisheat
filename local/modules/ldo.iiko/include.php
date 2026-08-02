<?php
use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('ldo.iiko', [
    'Ldo\\Iiko\\Auth' => 'lib/auth.php',
    'Ldo\\Iiko\\Product' => 'lib/product.php',
    'Ldo\\Iiko\\SettingsTable' => 'lib/SettingsTable.php',
    'Ldo\\Iiko\\Controller\\SettingsController' => 'lib/controller/SettingsController.php',
]);
