<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}
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
?>
<div class="search-page">



<?php if (count($arResult['SEARCH']) > 0):?>
	<?php
    foreach ($arResult['SEARCH'] as $arItem):?>
    <?
        if (substr($arItem["ITEM_ID"], 0, 1) == "S"){
            $categoryList[] = $arItem;
            continue;
        }
        else{
            $productIds[] = $arItem["ITEM_ID"];
        }
        ?>
	<?php endforeach;?>

<?if(isset($productIds) && count($productIds) > 0):?>
<?
        $GLOBALS['wishFilter'] = array('ID' => $productIds);
        $APPLICATION->IncludeComponent(
            "bitrix:catalog.top",
            "wishpage",
            array(
                "ACTION_VARIABLE" => "action",
                "ADD_PICT_PROP" => "-",
                "ADD_PROPERTIES_TO_BASKET" => "Y",
                "ADD_TO_BASKET_ACTION" => "ADD",
                "BASKET_URL" => "/personal/basket.php",
                "CACHE_FILTER" => "N",
                "CACHE_GROUPS" => "Y",
                "CACHE_TIME" => "36000000",
                "CACHE_TYPE" => "A",
                "COMPARE_NAME" => "CATALOG_COMPARE_LIST",
                "COMPATIBLE_MODE" => "N",
                "CONVERT_CURRENCY" => "N",
                "CUSTOM_FILTER" => "{\"CLASS_ID\":\"CondGroup\",\"DATA\":{\"All\":\"AND\",\"True\":\"True\"},\"CHILDREN\":[]}",
                "DETAIL_URL" => "",
                "DISPLAY_COMPARE" => "N",
                "ELEMENT_COUNT" => "9",
                "ELEMENT_SORT_FIELD" => "sort",
                "ELEMENT_SORT_FIELD2" => "id",
                "ELEMENT_SORT_ORDER" => "asc",
                "ELEMENT_SORT_ORDER2" => "desc",
                "ENLARGE_PRODUCT" => "STRICT",
                "FILTER_NAME" => "wishFilter",
                "HIDE_NOT_AVAILABLE" => "L",
                "HIDE_NOT_AVAILABLE_OFFERS" => "N",
                "IBLOCK_ID" => "4",
                "IBLOCK_TYPE" => "catalog",
                "LABEL_PROP" => array(
                ),
                "LINE_ELEMENT_COUNT" => "3",
                "MESS_BTN_ADD_TO_BASKET" => "В корзину",
                "MESS_BTN_BUY" => "Купить",
                "MESS_BTN_COMPARE" => "Сравнить",
                "MESS_BTN_DETAIL" => "Подробнее",
                "MESS_NOT_AVAILABLE" => "Нет в наличии",
                "MESS_NOT_AVAILABLE_SERVICE" => "Недоступно",
                "OFFERS_FIELD_CODE" => array(
                    0 => "",
                    1 => "",
                ),
                "OFFERS_LIMIT" => "5",
                "OFFERS_SORT_FIELD" => "sort",
                "OFFERS_SORT_FIELD2" => "id",
                "OFFERS_SORT_ORDER" => "asc",
                "OFFERS_SORT_ORDER2" => "desc",
                "PARTIAL_PRODUCT_PROPERTIES" => "N",
                "PRICE_CODE" => array(
                    0 => "BASE",
                ),
                "PRICE_VAT_INCLUDE" => "Y",
                "PRODUCT_BLOCKS_ORDER" => "price,props,sku,quantityLimit,quantity,buttons",
                "PRODUCT_DISPLAY_MODE" => "Y",
                "PRODUCT_ID_VARIABLE" => "id",
                "PRODUCT_PROPS_VARIABLE" => "prop",
                "PRODUCT_QUANTITY_VARIABLE" => "quantity",
                "PRODUCT_ROW_VARIANTS" => "[{'VARIANT':'2','BIG_DATA':false},{'VARIANT':'2','BIG_DATA':false},{'VARIANT':'2','BIG_DATA':false}]",
                "PRODUCT_SUBSCRIPTION" => "Y",
                "PROPERTY_CODE_MOBILE" => array(
                ),
                "ROTATE_TIMER" => "30",
                "SECTION_URL" => "",
                "SEF_MODE" => "Y",
                "SHOW_CLOSE_POPUP" => "N",
                "SHOW_DISCOUNT_PERCENT" => "N",
                "SHOW_MAX_QUANTITY" => "N",
                "SHOW_OLD_PRICE" => "N",
                "SHOW_PAGINATION" => "Y",
                "SHOW_PRICE_COUNT" => "1",
                "SHOW_SLIDER" => "Y",
                "SLIDER_INTERVAL" => "3000",
                "SLIDER_PROGRESS" => "N",
                "TEMPLATE_THEME" => "blue",
                "USE_ENHANCED_ECOMMERCE" => "Y",
                "USE_PRICE_COUNT" => "N",
                "USE_PRODUCT_QUANTITY" => "N",
                "VIEW_MODE" => "SECTION",
                "COMPONENT_TEMPLATE" => "wishpage",
                "DATA_LAYER_NAME" => "dataLayer",
                "BRAND_PROPERTY" => "-",
                "SEF_RULE" => "",
                "OFFER_ADD_PICT_PROP" => "-"
            ),
            false
        );
        ?>

<?endif?>

<?

if(isset($categoryList) && count($categoryList) > 0):?>
    <div class="children_category searchPage">
        <?foreach($categoryList as $category):?>
            <a href="<?=$category['URL_WO_PARAMS']?>" class="children_category__item"><?=$category['TITLE']?></a>
        <?endforeach;?>
    </div>


<?endif?>



<?php else:?>
	<?php ShowNote(GetMessage('SEARCH_NOTHING_TO_FOUND'));?>
<?php endif;?>
</div>
