<?php
use Bitrix\Main\Loader;

// Регистрируем автозагрузку классов для D7
Loader::registerAutoLoadClasses(
    'ldo.develop',
    [
        'Ldo\\Develop\\Sections' => 'lib/sections.php',
    ]
);