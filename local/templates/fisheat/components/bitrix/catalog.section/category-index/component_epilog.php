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


global $basketScriptAdded;
if (!isset($basketScriptAdded) || !$basketScriptAdded) {
    $basketScriptAdded = true;

    $script = '
    <script>
        (function() {
            function addBasketClass() {
                // Пытаемся получить данные о корзине из стандартного JS-хранилища Битрикса
                var localData = localStorage.getItem("saleInternalData");
                var basketIds = [];

                if (localData) {
                    try {
                        var parsed = JSON.parse(localData);
                        // Проверяем наличие товаров в объекте корзины Битрикса
                        if (parsed && parsed.BASKET && parsed.BASKET.ITEMS) {
                            basketIds = parsed.BASKET.ITEMS.map(function(item) {
                                return String(item.PRODUCT_ID);
                            });
                        }
                    } catch(e) {
                        console.error("Ошибка парсинга корзины Битрикса:", e);
                    }
                }

                // Расставляем классы кнопок
                document.querySelectorAll(".addCart").forEach(function(button) {
                    var productId = String(button.dataset.id);
                    if (basketIds.includes(productId)) {
                        button.classList.add("in_cart");
                    } else {
                        button.classList.remove("in_cart"); // Очищаем класс, если товара нет
                    }
                });
            }

            // Подписываемся на стандартное событие изменения корзины в Битриксе
            if (typeof BX !== "undefined" && BX.addCustomEvent) {
                BX.addCustomEvent("OnBasketChange", addBasketClass);
            }

            // Запускаем проверку при загрузке
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", addBasketClass);
            } else {
                addBasketClass();
            }

            window.addEventListener("load", function() {
                setTimeout(addBasketClass, 300);
            });
        })();
    </script>';

    Asset::getInstance()->addString($script);
}

