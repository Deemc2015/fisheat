<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>




<?

if (!empty($arResult)):?>
<ul class="personal-menu">

<?
foreach($arResult as $arItem):
	if($arParams["MAX_LEVEL"] == 1 && $arItem["DEPTH_LEVEL"] > 1) 
		continue;
?>
	<?if($arItem["SELECTED"]):?>
		<li class="<?=$arItem['PARAMS']['CLASS']?> selected"><span></span><a href="<?=$arItem["LINK"]?>" ><?=$arItem["TEXT"]?></a></li>
	<?else:?>
		<li class="<?=$arItem['PARAMS']['CLASS']?>"><span></span><a href="<?=$arItem["LINK"]?>"><?=$arItem["TEXT"]?></a></li>
	<?endif?>
	
<?endforeach?>

</ul>
<?endif?>