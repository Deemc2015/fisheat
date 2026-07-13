<?php
use Bitrix\Main\Loader;
use Bitrix\Main\EventManager;

// Регистрируем автозагрузку классов для D7
Loader::registerAutoLoadClasses(
    'ldo.develop',
    [
        'Ldo\\Develop\\Sections' => 'lib/sections.php',
        'Ldo\\Develop\\Property' => 'lib/property.php',
    ]
);

// Регистрируем тип свойства "Привязка к сайту" для инфоблоков
$eventManager = EventManager::getInstance();
$eventManager->registerEventHandler(
    'iblock',
    'OnIBlockPropertyBuildList',
    'ldo.develop',
    \Ldo\Develop\Property::class,
    'GetUserTypeDescription'
);
