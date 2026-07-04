<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

/**
 * @var array $arResult
 * @var array $arParams
 * @var array $templateData
 */




// check compared state
if ($arParams['DISPLAY_COMPARE'])
{
	$compared = false;
	$comparedIds = array();
	$item = $templateData['ITEM'];

	if (!empty($_SESSION[$arParams['COMPARE_NAME']][$item['IBLOCK_ID']]['ITEMS']))
	{
		if (!empty($item['JS_OFFERS']) && is_array($item['JS_OFFERS']))
		{
			foreach ($item['JS_OFFERS'] as $key => $offer)
			{
				if (array_key_exists($offer['ID'], $_SESSION[$arParams['COMPARE_NAME']][$item['IBLOCK_ID']]['ITEMS']))
				{
					if ($key == $item['OFFERS_SELECTED'])
					{
						$compared = true;
					}

					$comparedIds[] = $offer['ID'];
				}
			}
		}
		elseif (array_key_exists($item['ID'], $_SESSION[$arParams['COMPARE_NAME']][$item['IBLOCK_ID']]['ITEMS']))
		{
			$compared = true;
		}
	}

	if ($templateData['JS_OBJ'])
	{
		?>
		<script>
			BX.ready(BX.defer(function(){
				if (!!window.<?=$templateData['JS_OBJ']?>)
				{
					window.<?=$templateData['JS_OBJ']?>.setCompared('<?=$compared?>');
					<?php
					if (!empty($comparedIds)):
						?>
						window.<?=$templateData['JS_OBJ']?>.setCompareInfo(<?=CUtil::PhpToJSObject($comparedIds, false, true)?>);
						<?php
					endif;
					?>
				}
			}));
		</script>
		<?php
	}
}





// Получаем корзину
$arInBasket = [];
try {
    $basket = Bitrix\Sale\Basket::loadItemsForFUser(
        Bitrix\Sale\Fuser::getId(),
        Bitrix\Main\Context::getCurrent()->getSite()
    );
    foreach ($basket->getBasketItems() as $basketItem) {
        $arInBasket[] = (int)$basketItem->getProductId();
    }
} catch (Exception $e) {}

// Передаем JS массив
?>
    <script>
        var basketProductIds = <?= CUtil::PhpToJSObject($arInBasket) ?>;

        BX.ready(function() {
            // Добавляем класс in_cart для товаров в корзине
            document.querySelectorAll('.addCart').forEach(function(button) {
                var productId = parseInt(button.dataset.id);
                if (basketProductIds.indexOf(productId) !== -1) {
                    button.classList.add('in_cart');
                }
            });
        });
    </script>
<?php

// Выводим кешированный HTML (если есть)
if (!empty($arResult["CACHED_TPL"])) {
    echo $arResult["CACHED_TPL"];
} else {
    echo $arResult["CACHED_TPL"] ?? '';
}


