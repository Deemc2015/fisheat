<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
$arComponentParameters = [
    'GROUPS' => [
        'USER_CARD' => [
            'NAME' => 'Параметры компонента',
        ],
    ],
    'PARAMETERS' => [
        'OUT_ZONES' => [
            'NAME' => 'Текст, если адрес вне зон доставок',
            'TYPE' => 'TEXT',
            'PARENT' => 'USER_CARD',
        ]
    ],
];
?>
