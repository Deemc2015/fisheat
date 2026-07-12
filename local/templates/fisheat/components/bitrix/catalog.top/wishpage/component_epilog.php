<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arParams
 * @var string  $templateFolder
 * @var array $templateData
 * @var CatalogSectionComponent $component
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Page\FrameHelper;

global $APPLICATION;

switch ($arParams['VIEW_MODE'])
{
	case 'BANNER':
		$APPLICATION->AddHeadScript($templateFolder.'/banner/script.js');
		$APPLICATION->SetAdditionalCSS($templateFolder.'/banner/style.css');
		break;
	case 'SLIDER':
		$APPLICATION->AddHeadScript($templateFolder.'/slider/script.js');
		$APPLICATION->SetAdditionalCSS($templateFolder.'/slider/style.css');
		break;
	case 'SECTION':
	default:
		$APPLICATION->AddHeadScript($templateFolder.'/section/script.js');
		$APPLICATION->SetAdditionalCSS($templateFolder.'/section/style.css');

		if (isset($templateData['TEMPLATE_THEME']))
		{
			$APPLICATION->SetAdditionalCSS('/bitrix/css/main/themes/'.$templateData['TEMPLATE_THEME'].'/style.css', true);
		}
		break;
}

if (isset($templateData['TEMPLATE_THEME']))
{
	$APPLICATION->SetAdditionalCSS($templateFolder.'/'.mb_strtolower($arParams['VIEW_MODE']).'/themes/'.$arParams['TEMPLATE_THEME'].'/style.css');
}

if (isset($templateData['TEMPLATE_LIBRARY']) && !empty($templateData['TEMPLATE_LIBRARY']))
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

// ===== Получаем данные корзины и избранного =====
$arInBasket = [];
if (Loader::includeModule('sale'))
{
	try {
		$basket = \Bitrix\Sale\Basket::loadItemsForFUser(
			\Bitrix\Sale\Fuser::getId(),
			\Bitrix\Main\Context::getCurrent()->getSite()
		);
		foreach ($basket->getBasketItems() as $basketItem) {
			$arInBasket[] = (int)$basketItem->getProductId();
		}
	} catch (Exception $e) {}
}

$arInFavorites = [];
if (Loader::includeModule('ldo.favorites'))
{
	$arInFavorites = array_values(\Ldo\Favorites\Favorites::getItems());
}

// ===== AJAX-ответ с productStates =====
$request = \Bitrix\Main\Context::getCurrent()->getRequest();
if ($request->isAjaxRequest() && ($request->get('action') === 'deferredLoad'))
{
	$content = ob_get_contents();
	ob_end_clean();

	[, $itemsContainer] = explode('<!-- items-container -->', $content);
	[, $epilogue] = explode('<!-- component-end -->', $content);

	$component::sendJsonAnswer(array(
		'items' => $itemsContainer,
		'epilogue' => $epilogue,
		'productStates' => array(
			'basketIds' => $arInBasket,
			'wishIds' => $arInFavorites,
		),
	));
}

// ===== Динамическая зона для классов корзины и избранного =====
$commonFrame = new FrameHelper("products_common_frame_top_wishpage");
$commonFrame->begin();
?>
<script>
(function() {
    var basketIds = <?=CUtil::PhpToJSObject($arInBasket)?>.map(String);
    var wishIds = <?=CUtil::PhpToJSObject($arInFavorites)?>.map(String);

    function addClasses() {
        var found = false;

        // Класс корзины
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

        // Класс избранного
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

    // Пробуем выполнить сразу
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