<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arParams
 * @var array $templateData
 * @var string $templateFolder
 * @var CatalogSectionComponent $component
 * @var string $frameId - ID динамической зоны (обязательный параметр)
 */

use Bitrix\Main\Page\Asset;
use Bitrix\Main\Loader;
use Bitrix\Main\Context;

global $APPLICATION;

// ===== Тема оформления =====
if (isset($templateData['TEMPLATE_THEME']))
{
    $APPLICATION->SetAdditionalCSS($templateFolder.'/themes/'.$templateData['TEMPLATE_THEME'].'/style.css');
    $APPLICATION->SetAdditionalCSS('/bitrix/css/main/themes/'.$templateData['TEMPLATE_THEME'].'/style.css', true);
}

// ===== Библиотеки и валюта =====
if (!empty($templateData['TEMPLATE_LIBRARY']))
{
    $loadCurrency = false;
    if (!empty($templateData['CURRENCIES']))
    {
        $loadCurrency = Loader::includeModule('currency');
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
        $basket = Bitrix\Sale\Basket::loadItemsForFUser(
            Bitrix\Sale\Fuser::getId(),
            Context::getCurrent()->getSite()
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

// ===== AJAX-обработка (lazy load / deferred load) =====
$request = Context::getCurrent()->getRequest();
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
        'productStates' => array(
            'basketIds' => $arInBasket,
            'wishIds' => $arInFavorites,
        ),
    ));
}

// ===== Динамическая зона для корзины и избранного =====
$commonFrame = new \Bitrix\Main\Page\FrameHelper($frameId);
$commonFrame->begin();
?>
<script>
(function() {
    var basketIds = <?=CUtil::PhpToJSObject($arInBasket)?>.map(String);
    var wishIds = <?=CUtil::PhpToJSObject($arInFavorites)?>.map(String);
    
    function addClasses() {
        var found = false;
        
        var cartButtons = document.querySelectorAll(".addCart");
        if (cartButtons.length > 0) {
            found = true;
            cartButtons.forEach(function(button) {
                var productId = String(button.dataset.id);
                if (basketIds.indexOf(productId) !== -1) {
                    button.classList.add("in_cart");
                }
            });
        }
        
        var wishButtons = document.querySelectorAll(".wish-add");
        if (wishButtons.length > 0) {
            found = true;
            wishButtons.forEach(function(button) {
                var productId = String(button.dataset.id);
                if (wishIds.indexOf(productId) !== -1) {
                    button.classList.add("active");
                }
            });
        }
        
        return found;
    }
    
    if (!addClasses()) {
        var observer = new MutationObserver(function(mutations, obs) {
            if (addClasses()) {
                obs.disconnect();
            }
        });
        var targetNode = document.body || document.documentElement;
        observer.observe(targetNode, { childList: true, subtree: true });
        setTimeout(function() { observer.disconnect(); }, 5000);
    }
    
    if (window.BX && window.frameCacheVars !== undefined) {
        BX.addCustomEvent("onFrameDataReceived", function() {
            addClasses();
        });
    }
})();
</script>
<?php
$commonFrame->end();
