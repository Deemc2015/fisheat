<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arParams
 * @var array $templateData
 * @var string $templateFolder
 * @var CatalogSectionComponent $component
 */

use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Fuser;


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

use Bitrix\Main\Page\Asset;

// Получаем корзину (этот кусок кода работает без кэша, тут все ок)
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

// Ограничиваем повторное добавление скрипта на страницу, если компонент вызван несколько раз
global $basketScriptAdded;
if (!isset($basketScriptAdded) || !$basketScriptAdded) {
    $basketScriptAdded = true;

    // Формируем JS-скрипт
    $script = '
    <script>
        (function() {
            // Приводим все ID из PHP к строкам
            var basketIds = ' . CUtil::PhpToJSObject($arInBasket) . '.map(String);
           
            function addBasketClass() {
                document.querySelectorAll(".addCart").forEach(function(button) {
                    var productId = String(button.dataset.id);
                    if (basketIds.includes(productId)) {
                        button.classList.add("in_cart");
                    }
                });
            }
            
            // Запускаем сразу или по готовности DOM
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", addBasketClass);
            } else {
                addBasketClass();
            }
            
            // Страховочный перезапуск после полной загрузки страницы
            window.addEventListener("load", function() {
                setTimeout(addBasketClass, 200);
            });
        })();
    </script>';

    // ВАЖНО: Регистрируем скрипт в буфере Битрикса вместо обычного echo
    Asset::getInstance()->addString($script);
}

