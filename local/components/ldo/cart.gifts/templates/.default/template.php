<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
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

use Bitrix\Main\Localization\Loc;

$height = 100;


if($arResult['PROGRESS']){
    $height =  $height - $arResult['PROGRESS'];
}
?>


<div class="gifts-block">
    <div class="steps-blocks">
        <div></div>
        <div></div>
        <div></div>
    </div>
    <div class="progress-block">
        <div class="progress-line">
            <div class="progress-fill"  style="height: <?=$height?>%"></div>
        </div>
    </div>
</div>