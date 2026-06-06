<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    'PARAMETERS' => [
        'INPUT_PLACEHOLDER' => [
            'PARENT' => 'BASE',
            'NAME' => 'Текст в поле ввода',
            'TYPE' => 'STRING',
            'DEFAULT' => 'Опишите, что вы ищете...',
        ],
        'CACHE_TIME' => [
            'DEFAULT' => 3600,
        ],
    ],
];
