<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentDescription = array(
    'NAME'        => 'Список модуля фильтрации трафика',
    'DESCRIPTION' => 'Выводит журнал визитов, правила фильтрации или адреса оповещений из собственных таблиц модуля.',
    'ICON'        => '/images/icon.gif',
    'SORT'        => 10,
    'CACHE_PATH'  => 'Y',
    'PATH'        => array(
        'ID'    => 'keyup',
        'NAME'  => 'Key Up',
    ),
);
