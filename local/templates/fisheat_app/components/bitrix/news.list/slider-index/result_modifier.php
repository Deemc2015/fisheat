<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Ldo\Develop\Pict;
use Bitrix\Main\Loader;

Loader::includeModule('ldo.develop');

foreach($arResult["ITEMS"] as $key => $arItem){
    $img =  CFile::ResizeImageGet($arItem['PREVIEW_PICTURE']['ID'], array('width'=>500, 'height'=>250), BX_RESIZE_IMAGE_PROPORTIONAL, true);
    $webP = Pict::getResizeWebpSrc($arItem['PREVIEW_PICTURE']['ID'], 500, 250, true, 65);

    if($img){
        $arResult["ITEMS"][$key]['IMG'] = $img;
    }

    if($webP){
        $arResult["ITEMS"][$key]['WEBP'] = $webP;
    }

    unset($img,$webP);
}