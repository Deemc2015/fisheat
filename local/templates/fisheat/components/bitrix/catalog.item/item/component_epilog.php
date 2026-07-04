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


// В начале файла
if (defined('BX_COMP_MANAGED_CACHE')) {
    file_put_contents(
        $_SERVER['DOCUMENT_ROOT'] . '/debug_cache.txt',
        date('Y-m-d H:i:s') . " - CACHED_TPL: " . (isset($arResult["CACHED_TPL"]) ? strlen($arResult["CACHED_TPL"]) : 'empty') . "\n",
        FILE_APPEND
    );
}









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


// Проверяем, определена ли функция, чтобы избежать повторного объявления
if (!function_exists('set_buttons_callback')) {
    function set_buttons_callback($matches) {
        static $arInBasket = null;

        // Получаем корзину один раз при первом вызове
        if ($arInBasket === null) {
            $arInBasket = [];
            try {
                $basket = Bitrix\Sale\Basket::loadItemsForFUser(
                    Bitrix\Sale\Fuser::getId(),
                    Bitrix\Main\Context::getCurrent()->getSite()
                );
                foreach ($basket->getBasketItems() as $basketItem) {
                    $arInBasket[] = (int)$basketItem->getProductId();
                }
            } catch (Exception $e) {
                // Обработка ошибки
            }
        }

        // Получаем ID товара из маски
        $productId = (int)$matches[1];

        // Возвращаем класс с пробелом, если товар в корзине
        return in_array($productId, $arInBasket) ? ' in_cart' : '';
    }
}

// Проверяем, есть ли кешированный HTML для замены
if (!empty($arResult["CACHED_TPL"])) {
    // Заменяем маски на класс
    echo preg_replace_callback(
        "/#BUY_CLASS_([\d]+)#/is",
        'set_buttons_callback',
        $arResult["CACHED_TPL"]
    );
} else {
    // Если кеша нет, выводим то, что есть
    echo $arResult["CACHED_TPL"] ?? '';
}
