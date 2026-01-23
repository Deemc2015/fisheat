<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$descriptionIblock = CIBlock::GetArrayByID($arParams['IBLOCK_ID'], "DESCRIPTION");

if($descriptionIblock){
    $arResult['DESCRIPTIONS'] = $descriptionIblock;
}