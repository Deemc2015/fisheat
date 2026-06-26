<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arParams
 * @var array $templateData
 * @var string $templateFolder
 * @var CatalogSectionComponent $component
 */

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

static $scriptAdded = false;

if (!$scriptAdded && !empty($arResult['BASKET_IDS']) && is_array($arResult['BASKET_IDS'])) {
    $scriptAdded = true;
    $ids = CUtil::PhpToJSObject($arResult['BASKET_IDS']);
    ?>
    <script>
        (function() {
            window.BASKET_IDS = <?= $ids ?>;

            function updateCartButtons() {
                if (!window.BASKET_IDS || !Array.isArray(window.BASKET_IDS)) {
                    return;
                }

                document.querySelectorAll('.addCart').forEach(function(btn) {
                    var id = parseInt(btn.getAttribute('data-id'));
                    if (window.BASKET_IDS.indexOf(id) !== -1) {
                        btn.classList.add('in_cart');
                    }
                });
            }

            // Обновляем после загрузки DOM
            if (document.readyState === 'complete') {
                setTimeout(updateCartButtons, 500);
            } else {
                document.addEventListener('readystatechange', function() {
                    if (document.readyState === 'complete') {
                        setTimeout(updateCartButtons, 500);
                    }
                });
            }

            // Обновляем после инициализации слайдеров
            if (typeof $ !== 'undefined' && $.fn.slick) {
                $(document).on('init', '.product-item-container', function() {
                    setTimeout(updateCartButtons, 300);
                });
            }
        })();
    </script>
    <?php
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