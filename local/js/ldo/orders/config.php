<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}

return [
    'js' => './vue-app.js',
    'rel' => [
        'main.core',
        'ui.vue3',
    ],
    'skip_core' => true,
];
