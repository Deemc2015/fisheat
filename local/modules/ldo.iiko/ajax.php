<?php
use Bitrix\Main\Loader;

if (!Loader::includeModule('ldo.iiko')) {
    \Bitrix\Main\Application::getInstance()->terminate();
}
