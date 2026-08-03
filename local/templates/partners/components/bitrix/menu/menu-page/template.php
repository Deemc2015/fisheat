<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>

<?if (!empty($arResult)):?>
    <div class="p-dash-tabs">

<?
foreach($arResult as $arItem):
	if($arParams["MAX_LEVEL"] == 1 && $arItem["DEPTH_LEVEL"] > 1) 
		continue;
?>


    <a class="tab-btn <?if($arItem["SELECTED"]){echo 'active';}?> " data-tab="iiko" href="<?=$arItem["LINK"]?>"><?=$arItem["TEXT"]?></a>
	
<?endforeach?>

    </div>
<?endif?>