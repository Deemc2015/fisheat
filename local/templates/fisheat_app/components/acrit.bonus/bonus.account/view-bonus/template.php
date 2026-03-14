<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var array $templateData */
/** @var string $componentPath */
/** @var CBitrixComponent $component */

use Bitrix\Main\Loader;
use Bitrix\Sale\Order;

$replaceOrderIds = [];
if (Loader::includeModule('sale')) {
	$orderIds = array_keys($arResult['USER_ORDERS_BONUS']);
	$orders = Order::getList([
		'select' => ['ID', 'ACCOUNT_NUMBER'],
		'filter' => ['ID' => $orderIds]
	]);
	while ($order = $orders->fetch()) {
		if (trim($order['ACCOUNT_NUMBER']) != '') {
			$replaceOrderIds[ $order['ID'] ] = $order['ACCOUNT_NUMBER'];
		}
	}
}

if (strlen($arResult["ERROR_MESSAGE"]) <= 0):
	?>
	<?=$arResult['USER_BONUS_BALANCE']?>
<?
else:
	echo ShowError($arResult["ERROR_MESSAGE"]);
endif;
?>