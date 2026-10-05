<?php
use Bitrix\Main\Loader;

// Регистрируем автозагрузку классов D7 для модуля ldo.marketing.
Loader::registerAutoLoadClasses(
    'ldo.marketing',
    [
        'Ldo\\Marketing\\FreePositionsTable' => 'lib/FreePositionsTable.php',
        'Ldo\\Marketing\\GiftsTable'         => 'lib/GiftsTable.php',
        'Ldo\\Marketing\\Settings'           => 'lib/Settings.php',
    ]
);
