<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}
$categoryList = [];
if (!empty($arResult['CATEGORIES']) && $arResult['CATEGORIES_ITEMS_EXISTS']):?>
	<div class="title-search-result-block">
		<?php foreach ($arResult['CATEGORIES'] as $category_id => $arCategory):?>

			<?php


            foreach ($arCategory['ITEMS'] as $arItem):?>
            <?
                if (substr($arItem["ITEM_ID"], 0, 1) == "S"){
                    $categoryList[] = $arItem;
                    continue;
                }
                elseif ($arItem['NAME'] == 'остальные' || $arItem['NAME'] == 'Все результаты'){
                    continue;
                }
                else{
                    $dataProduct = GetIBlockElement($arItem['ITEM_ID']);

                    $arrImage = '';
                    $arrDesc = '';

                    if($dataProduct['PREVIEW_PICTURE']){
                        $productImage = $dataProduct['PREVIEW_PICTURE'];
                    }
                    else{
                        $productImage = $dataProduct['DETAIL_PICTURE'];
                    }

                    if($productImage){
                        $arrImage = CFile::ResizeImageGet($productImage, array('width'=>70, 'height'=>70), BX_RESIZE_IMAGE_PROPORTIONAL, true);
                        $arrImage = is_array($arrImage) ? $arrImage['src'] : '';

                        // Описание может содержать HTML (визуальный редактор) —
                        // в подсказке поиска выводим только текст.
                        $arrDesc = htmlspecialcharsbx(strip_tags((string)$dataProduct['DETAIL_TEXT']));
                    }

                    unset($productImage);
                }

                ?>
			<a href="<?=$arItem['URL']?>" class="title-search-result__item">
			             <div class="icon-product">
			                 <img src="<?=htmlspecialcharsbx($arrImage)?>" alt="<?=htmlspecialcharsbx($arItem['NAME'])?>">
			             </div>
			             <div class="right-block-product">

			                <div class="name"><?=htmlspecialcharsbx($arItem['NAME'])?></div>
			                 <div class="description"><?=$arrDesc?></div>
			             </div>
			</a>

			<?php unset($dataProduct,$arrImage,$arrDesc);  endforeach;?>
		<?php endforeach;?>
        <div class="children_category search">
            <?foreach ($categoryList as $category):?>
                <a href="<?=$category['URL']?>" class="children_category__item"><?=$category['NAME']?></a>
            <?endforeach?>
        </div>

	</div>
<?php endif;
