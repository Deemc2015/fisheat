<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
$this->setFrameMode(true);

if($arResult['DESCRIPTIONS']){
    echo $arResult['DESCRIPTIONS'];
}
?>

<?foreach($arResult["ITEMS"] as $arItem):?>
	<?
	$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
	$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
	?>
	<div class="item-restoran" id="<?=$this->GetEditAreaId($arItem['ID']);?>"> 
    	<p class="item-restoran__name"><?=$arItem['NAME']?></p>
        <p>Фактический адрес: <?=$arItem['PREVIEW_TEXT']?> </p>
        <p>Электронная почта: <?=$arItem['PROPERTIES']['ATT_EMAIL']['VALUE']?></p>
        <p >Телефоны: <?=$arItem['PROPERTIES']['ATT_PHONE']['VALUE']?></p>
        <p >Реквизиты <?=$arItem['PROPERTIES']['ATT_REQV']['VALUE']?> </p>	
    </div>   
<?endforeach;?>

