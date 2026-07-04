<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arParams
 * @var array $templateData
 * @var string $templateFolder
 * @var CatalogSectionComponent $component
 */

use Bitrix\Main\Page\Asset;

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
try {
    $basket = Bitrix\Sale\Basket::loadItemsForFUser(
        Bitrix\Sale\Fuser::getId(),
        Bitrix\Main\Context::getCurrent()->getSite()
    );
    foreach ($basket->getBasketItems() as $basketItem) {
        $arInBasket[] = (int)$basketItem->getProductId();
    }
} catch (Exception $e) {}

$script = '
<script>
    (function() {
        var basketIds = ' . CUtil::PhpToJSObject($arInBasket) . '.map(String);
       
        function addBasketClass() {
            var buttons = document.querySelectorAll(".addCart");
            if (buttons.length === 0) return false; // Если кнопок еще нет, выходим
            
            buttons.forEach(function(button) {
                var productId = String(button.dataset.id);
                if (basketIds.includes(productId)) {
                    button.classList.add("in_cart");
                }
            });
            return true;
        }
        
        // 1. Пытаемся выполнить сразу (если кнопки уже успели отрендериться)
        var success = addBasketClass();
        
        // 2. Если кнопок еще нет или каталог догружается динамически, включаем слежку за DOM
        if (!success || document.readyState === "loading") {
            var observer = new MutationObserver(function(mutations, obs) {
                // Как только на странице появились нужные кнопки — красим их
                if (document.querySelectorAll(".addCart").length > 0) {
                    addBasketClass();
                    // Отключаем слежку, чтобы не тратить ресурсы браузера
                    obs.disconnect(); 
                }
            });
            
            // Начинаем следить за всем документом
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            // Страховочное отключение обсервера через 3 секунды, чтобы он не висел вечно
            setTimeout(function() { observer.disconnect(); }, 3000);
        }
        
        // 3. Железный таймаут для старых браузеров
        window.addEventListener("load", function() {
            setTimeout(addBasketClass, 200);
        });
    })();
</script>';

Asset::getInstance()->addString($script);

$compositeFrame->end(); // Конец динамической зоны

