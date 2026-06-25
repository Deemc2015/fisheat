<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
$arComponentParameters = [
    'GROUPS' => [
        'USER_CARD' => [
            'NAME' => 'Параметры компонента',
        ],
    ],
    'PARAMETERS' => [
        'LINK_RESTORANS' => [
            'NAME' => 'Ссылка на список ресторанов',
            'TYPE' => 'TEXT',
            'PARENT' => 'USER_CARD',
        ]
    ],
];
?>
