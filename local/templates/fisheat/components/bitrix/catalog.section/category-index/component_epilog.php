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



global $basketScriptAdded;
if (!isset($basketScriptAdded) || !$basketScriptAdded) {
    $basketScriptAdded = true;

    $script = '
    <script>
        (function() {
            function updateCartButtons() {
                var basketIds = [];
                
                // Исправлено: передаем ключ как СТРОКУ "saleInternalData"
                var localData = localStorage.getItem("saleInternalData");

                if (localData) {
                    try {
                        var parsed = JSON.parse(localData);
                        if (parsed && parsed.BASKET && parsed.BASKET.ITEMS) {
                            basketIds = parsed.BASKET.ITEMS.map(function(item) {
                                return String(item.PRODUCT_ID);
                            });
                        }
                    } catch(e) {
                        console.error("Ошибка чтения корзины:", e);
                    }
                }

                // Расставляем классы на кнопки
                document.querySelectorAll(".addCart").forEach(function(button) {
                    var productId = String(button.dataset.id);
                    if (basketIds.includes(productId)) {
                        button.classList.add("in_cart");
                    } else {
                        button.classList.remove("in_cart");
                    }
                });
            }

            // Альтернативный и самый надежный перехват данных для Битрикса:
            // Ловим событие OnBasketChange и вытаскиваем актуальные ID прямо из параметров события,
            // не дожидаясь записи в localStorage.
            if (typeof BX !== "undefined" && BX.addCustomEvent) {
                BX.addCustomEvent("OnBasketChange", function(currentBasketData) {
                    // Если Битрикс передал объект с данными в событие
                    if (currentBasketData && currentBasketData.ITEMS) {
                        var basketIds = currentBasketData.ITEMS.map(function(item) {
                            return String(item.PRODUCT_ID);
                        });
                        
                        document.querySelectorAll(".addCart").forEach(function(button) {
                            var productId = String(button.dataset.id);
                            if (basketIds.includes(productId)) {
                                button.classList.add("in_cart");
                            } else {
                                button.classList.remove("in_cart");
                            }
                        });
                    } else {
                        // Если событие пустое (старая версия ядра), дергаем функцию с localStorage
                        setTimeout(updateCartButtons, 150);
                    }
                });
            }

            // Проверки при загрузке страницы
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", updateCartButtons);
            } else {
                updateCartButtons();
            }

            window.addEventListener("load", function() {
                setTimeout(updateCartButtons, 300);
            });
        })();
    </script>';

    Asset::getInstance()->addString($script, false, \Bitrix\Main\Page\AssetLocation::AFTER_JS_KERNEL);
}
