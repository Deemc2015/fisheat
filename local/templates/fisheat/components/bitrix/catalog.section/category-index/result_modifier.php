<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @var CBitrixComponentTemplate $this
 * @var CatalogSectionComponent $component
 */

$component = $this->getComponent();
$arParams = $component->applyTemplateModifications();



$categoryId = $arResult['ORIGINAL_PARAMETERS']['SECTION_ID'];

if($categoryId){
    $categoryParams = CIBlockSection::GetByID($categoryId);
    if($result = $categoryParams->GetNext()){
        $categoryInfo = $result;
    }
	
    
    if($categoryInfo){
        $arResult['CATEGORY_INFO'] = [
            "NAME" =>  $categoryInfo['NAME'],
            "URL" => $categoryInfo['SECTION_PAGE_URL'],
            "CODE" => $categoryInfo['CODE']
        ];
    }
    
    //фильтру указываем ID раздела и ID его инфоблока
    $arFilter = array('SECTION_ID' => $categoryId); // устанавливаем фильр - что ищем. Если у раздела родитель имеет ID равный текущему, что это наш пациент
    $rsSect = CIBlockSection::GetList(
        Array("SORT"=>"ASC"),
        $arFilter, 
        false,
        ['NAME', 'SECTION_PAGE_URL','UF_VIEW_INDEX','CODE','ID']
    );
    while ($arSect = $rsSect->GetNext()) {
        
        $arResult['CATEGORY_INFO']['PODRAZDEL'][] = $arSect;
        
        
    }
    
    
    unset($arSect,$categoryInfo,$categoryParams,$categoryId);
}