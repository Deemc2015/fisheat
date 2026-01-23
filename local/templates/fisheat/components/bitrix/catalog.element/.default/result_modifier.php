<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @var CBitrixComponentTemplate $this
 * @var CatalogElementComponent $component
 */

$component = $this->getComponent();
$arParams = $component->applyTemplateModifications();

$arResult['NUTRITIONAL'] = [];

if($arResult['PROPERTIES']['ATT_KALLORY']['VALUE']){
    $arResult['NUTRITIONAL']['CALORIES'] = $arResult['PROPERTIES']['ATT_KALLORY']['VALUE'];
}

if($arResult['PROPERTIES']['ATT_BELKI']['VALUE']){
    $arResult['NUTRITIONAL']['BELKI'] = $arResult['PROPERTIES']['ATT_BELKI']['VALUE'];
}

if($arResult['PROPERTIES']['ATT_GIRY']['VALUE']){
    $arResult['NUTRITIONAL']['GIR'] = $arResult['PROPERTIES']['ATT_GIRY']['VALUE'];
}

if($arResult['PROPERTIES']['ATT_YGLEVODY']['VALUE']){
    $arResult['NUTRITIONAL']['YGLEVODY'] = $arResult['PROPERTIES']['ATT_YGLEVODY']['VALUE'];
}
