<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arParams
 * @var array $templateData
 * @var string $templateFolder
 * @var CatalogSectionComponent $component
 */

use Bitrix\Main\Page\Asset;
use Bitrix\Main\Loader;
global $APPLICATION;

if (isset($templateData['TEMPLATE_THEME']))
{
	$APPLICATION->SetAdditionalCSS($templateFolder.'/themes/'.$templateData['TEMPLATE_THEME'].'/style.css');
	$APPLICATION->SetAdditionalCSS('/bitrix/css/main/themes/'.$templateData['TEMPLATE_THEME'].'/style.css', true);
}

if (!empty($templateData['TEMPLATE_LIBRARY']))
{
	$loadCurrency = false;
	if (!empty($templateData['CURRENCIES']))
	{
		$loadCurrency = \Bitrix\Main\Loader::includeModule('currency');
	}

	CJSCore::Init($templateData['TEMPLATE_LIBRARY']);

	if ($loadCurrency)
	{
		?>
		<script>
			BX.Currency.setCurrencies(<?=$templateData['CURRENCIES']?>);
		</script>
		<?
	}
}



//	lazy load and big data json answers
$request = \Bitrix\Main\Context::getCurrent()->getRequest();
if ($request->isAjaxRequest() && ($request->get('action') === 'showMore' || $request->get('action') === 'deferredLoad'))
{
	$content = ob_get_contents();
	ob_end_clean();

	[, $itemsContainer] = explode('<!-- items-container -->', $content);
	$paginationContainer = '';
	if ($templateData['USE_PAGINATION_CONTAINER'])
	{
		[, $paginationContainer] = explode('<!-- pagination-container -->', $content);
	}
	[, $epilogue] = explode('<!-- component-end -->', $content);

	if (isset($arParams['AJAX_MODE']) && $arParams['AJAX_MODE'] === 'Y')
	{
		$component->prepareLinks($paginationContainer);
	}

	$component::sendJsonAnswer(array(
		'items' => $itemsContainer,
		'pagination' => $paginationContainer,
		'epilogue' => $epilogue,
	));
}



// ===== ОБЩАЯ ДИНАМИЧЕСКАЯ ЗОНА ДЛЯ КОРЗИНЫ И ИЗБРАННОГО =====
$commonFrame = new \Bitrix\Main\Page\FrameHelper("products_common_frame_cat_index");
$commonFrame->begin();

// Данные корзины
$arInBasket = [];
if (Loader::includeModule('sale'))
{
    try {
        $basket = Bitrix\Sale\Basket::loadItemsForFUser(
            Bitrix\Sale\Fuser::getId(),
            Bitrix\Main\Context::getCurrent()->getSite()
        );
        foreach ($basket->getBasketItems() as $basketItem) {
            $arInBasket[] = (int)$basketItem->getProductId();
        }
    } catch (Exception $e) {}
}

// Данные избранного
$arInFavorites = [];
if (Loader::includeModule('ldo.favorites'))
{
    $arInFavorites = array_values(\Ldo\Favorites\Favorites::getItems());
}
?>
<script>
(function() {
    var basketIds = <?=CUtil::PhpToJSObject($arInBasket)?>.map(String);
    var wishIds = <?=CUtil::PhpToJSObject($arInFavorites)?>.map(String);
    
    function addClasses() {
        var found = false;
        
        // Добавляем класс корзины
        var cartButtons = document.querySelectorAll(".addCart");
        if (cartButtons.length > 0) {
            found = true;
            cartButtons.forEach(function(button) {
                var productId = String(button.dataset.id);
                if (basketIds.includes(productId)) {
                    button.classList.add("in_cart");
                }
            });
        }
        
        // Добавляем класс избранного
        var wishButtons = document.querySelectorAll(".wish-add");
        if (wishButtons.length > 0) {
            found = true;
            wishButtons.forEach(function(button) {
                var productId = String(button.dataset.id);
                if (wishIds.includes(productId)) {
                    button.classList.add("active");
                }
            });
        }
        
        return found;
    }
    
    // Пробуем выполнить сразу (скрипт выполняется при вставке динамической зоны)
    if (!addClasses()) {
        // MutationObserver — если кнопок ещё нет в DOM
        var observer = new MutationObserver(function(mutations, obs) {
            if (addClasses()) {
                obs.disconnect();
            }
        });
        var targetNode = document.body || document.documentElement;
        observer.observe(targetNode, { childList: true, subtree: true });
        setTimeout(function() { observer.disconnect(); }, 5000);
    }
    
    // Подписываемся на обновление композитных динамических зон
    if (window.BX && window.frameCacheVars !== undefined) {
        BX.addCustomEvent("onFrameDataReceived", function() {
            addClasses();
        });
    }
})();
</script>
<?php
$commonFrame->end();

