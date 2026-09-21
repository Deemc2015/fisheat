<?php

/*
 * Настройки модуля для ядра Bitrix.
 *
 * defaultNamespace обязателен, чтобы AJAX-диспетчер нашёл контроллеры модуля
 * по имени действия keyup:cleartrafic.captcha.check.
 */
return [
    'controllers' => [
        'value' => [
            'defaultNamespace' => '\\Keyup\\Cleartrafic\\Controller',
        ],
        'readonly' => true,
    ],
];
