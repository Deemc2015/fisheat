<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
{
    die();
}
use \Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);
$arComponentDescription = array(
    "NAME" => "Проверка каптчи", // Название компонента
    "DESCRIPTION" => "Проверка каптчи", // Описание компонента
    "PATH" => array(
        "ID" => "keyup", // ID вашего компонента
        "NAME" => "Компоненты KeyUp", // Название вашего компонента
    ),
    "CACHE_PATH" => "N",
    "COMPLEX" => "N",
);
?>