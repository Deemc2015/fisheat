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



// Обязательно подключаем механизм динамических зон
$compositeFrame = new \Bitrix\Main\Page\FrameHelper("products_buttons_frame");
$compositeFrame->begin();

$arInBasket = [];

// ===== ИСПРАВЛЕННАЯ ЧАСТЬ =====
// Проверяем, загружен ли модуль sale перед использованием Basket
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
    } catch (Exception $e) {
        // Логируем ошибку, если нужно
        // AddMessage2Log('Ошибка при загрузке корзины: ' . $e->getMessage(), 'catalog.section');
    }
}
// ===== КОНЕЦ ИСПРАВЛЕННОЙ ЧАСТИ =====

$script = '
<script>
    (function() {
        var basketIds = ' . CUtil::PhpToJSObject($arInBasket) . '.map(String);
       
        function addBasketClass() {
            var buttons = document.querySelectorAll(".addCart");
            if (buttons.length === 0) return false;
            
            buttons.forEach(function(button) {
                var productId = String(button.dataset.id);
                if (basketIds.includes(productId)) {
                    button.classList.add("in_cart");
                }
            });
            return true;
        }
        
        // 1. Пробуем выполнить сразу
        var success = addBasketClass();
        
        // 2. Если кнопок нет, запускаем обсервер
        if (!success) {
            var observer = new MutationObserver(function(mutations, obs) {
                if (addBasketClass()) {
                    obs.disconnect(); // Отключаем, как только успешно покрасили кнопки
                }
            });
            
            // Защита от null: следим за document.documentElement (тег <html>), он есть всегда
            var targetNode = document.body || document.documentElement;
            
            observer.observe(targetNode, {
                childList: true,
                subtree: true
            });
            
            // На всякий случай гасим через 4 секунды
            setTimeout(function() { observer.disconnect(); }, 4000);
        }
        
        // 3. Страховка по событиям загрузки
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", addBasketClass);
        }
        window.addEventListener("load", function() {
            setTimeout(addBasketClass, 200);
        });
    })();
</script>';

Asset::getInstance()->addString($script);

$compositeFrame->end(); // Конец динамической зоны

// ===== ДИНАМИЧЕСКАЯ ЗОНА ДЛЯ ИЗБРАННОГО =====
$wishFrame = new \Bitrix\Main\Page\FrameHelper("products_wish_frame");
$wishFrame->begin();

$arInFavorites = [];
if (Loader::includeModule('ldo.favorites'))
{
    $arInFavorites = array_values(\Ldo\Favorites\Favorites::getItems());
}
?>
<script>
    (function() {
        var wishIds = <?=CUtil::PhpToJSObject($arInFavorites)?>.map(String);
        
        function addWishClass() {
            var buttons = document.querySelectorAll(".wish-add");
            
            if (buttons.length === 0) return false;
            
            buttons.forEach(function(button) {
                var productId = String(button.dataset.id);
                var isInFav = wishIds.includes(productId);
                if (isInFav) {
                    button.classList.add("active");
                }
            });
            return true;
        }
        
        var success = addWishClass();
        
        if (!success) {
            var observer = new MutationObserver(function(mutations, obs) {
                if (addWishClass()) {
                    obs.disconnect();
                }
            });
            
            var targetNode = document.body || document.documentElement;
            observer.observe(targetNode, { childList: true, subtree: true });
            setTimeout(function() {
                observer.disconnect();
            }, 4000);
        }
        
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", function() {
                addWishClass();
            });
        }
        window.addEventListener("load", function() {
            setTimeout(function() {
                addWishClass();
            }, 200);
        });
    })();
</script>
<?php
$wishFrame->end();

