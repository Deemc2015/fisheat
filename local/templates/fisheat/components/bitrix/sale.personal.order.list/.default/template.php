<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
{
	die();
}

/** @var CBitrixPersonalOrderListComponent $component */
/** @var array $arParams */
/** @var array $arResult */

use Bitrix\Main;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Page\Asset;

use  Ldo\Develop\Product;
Loader::IncludeModule('ldo.develop');


\Bitrix\Main\UI\Extension::load([
	'ui.design-tokens',
	'ui.fonts.opensans',
	'clipboard',
	'fx',
]);

Asset::getInstance()->addJs("/bitrix/components/bitrix/sale.order.payment.change/templates/.default/script.js");
Asset::getInstance()->addCss("/bitrix/components/bitrix/sale.order.payment.change/templates/.default/style.css");


Loc::loadMessages(__FILE__);



$statusOrderName = [
    "F" => ["NAME" => "Выполнен","BG"=>"#00C566"]
];





if (!empty($arResult['ERRORS']['FATAL']))
{
	foreach($arResult['ERRORS']['FATAL'] as $error)
	{
		ShowError($error);
	}
	$component = $this->__component;
	if ($arParams['AUTH_FORM_IN_TEMPLATE'] && isset($arResult['ERRORS']['FATAL'][$component::E_NOT_AUTHORIZED]))
	{
		$APPLICATION->AuthForm('', false, false, 'N', false);
	}

}
else
{
	$filterHistory = ($_REQUEST['filter_history'] ?? '');
	$filterShowCanceled = ($_REQUEST["show_canceled"] ?? '');

	if (!empty($arResult['ERRORS']['NONFATAL']))
	{
		foreach($arResult['ERRORS']['NONFATAL'] as $error)
		{
			ShowError($error);
		}
	}
	if (empty($arResult['ORDERS']))
	{
		if ($filterHistory === 'Y')
		{
			if ($filterShowCanceled === 'Y')
			{
				?>
				<h3><?= Loc::getMessage('SPOL_TPL_EMPTY_CANCELED_ORDER')?></h3>
				<?
			}
			else
			{
				?>
				<h3><?= Loc::getMessage('SPOL_TPL_EMPTY_HISTORY_ORDER_LIST')?></h3>
				<?
			}
		}
		else
		{
			?>
			<h3><?= Loc::getMessage('SPOL_TPL_EMPTY_ORDER_LIST')?></h3>
			<?
		}
	}
	?>

	<?
	if (empty($arResult['ORDERS']))
	{
		?>
		Тут список заказов пуст, продумать
		<?
	}

	if ($filterHistory !== 'Y')
	{
		$paymentChangeData = array();
		$orderHeaderStatus = null;

		foreach ($arResult['ORDERS'] as $key => $order)
		{
			if ($orderHeaderStatus !== $order['ORDER']['STATUS_ID'] && $arResult['SORT_TYPE'] == 'STATUS')
			{
				$orderHeaderStatus = $order['ORDER']['STATUS_ID'];

				?>

				<?
			}
			?>
					<div class="order-container">
                        <div class="top-order-line">
                            <div class="top-order-line__number">Заказ <?=$order['ORDER']['ACCOUNT_NUMBER']?></div>
                            <div class="top-order-line__date">от <?=$order['ORDER']['DATE_INSERT_FORMATED']?></div>
                            <?if($order['ORDER']['CANCELED'] == 'Y'):?>
                                <div class="top-order-line__status canceled-order">Отменен</div>
                            <?else:?>
                                <div class="top-order-line__status" style="background: <?=$statusOrderName[$order['ORDER']['STATUS_ID']]['BG'];?>;"><?=$statusOrderName[$order['ORDER']['STATUS_ID']]['NAME'];?></div>
                            <?endif?>

                        </div>
                        <div class="bottom-order">
                            <div class="list-product">
                                <?foreach($order['BASKET_ITEMS'] as $product):?>

                                <a target="_blank" href="<?=$product['DETAIL_PAGE_URL']?>" class="list-product__item" title="<?=$product['NAME']?>">
                                    <img src="<?=Product::getImageById($product['PRODUCT_ID'])?>" alt="<?=$product['NAME']?>">
                                </a>
                                <?endforeach;?>
                            </div>
                            <div class="right-block-order">
                                <div class="right-block-order-price">
                                    <?=$order['ORDER']['FORMATED_PRICE']?>
                                </div>

                                <?if($order['ORDER']['STATUS_ID'] == 'F' || $order['ORDER']['CANCELED'] == 'Y'):?>
                                    <a target="_blank" href="<?=htmlspecialcharsbx($order["ORDER"]["URL_TO_COPY"])?>" class="right-block-order-return">
                                        Повторить
                                    </a>
                                <?endif;?>

                            </div>
                        </div>
					</div>

			<?
		}
	}
	else
	{
		$orderHeaderStatus = null;

		if ($filterShowCanceled === 'Y' && !empty($arResult['ORDERS']))
		{
			?>

			<?
		}

		foreach ($arResult['ORDERS'] as $key => $order)
		{
			if ($orderHeaderStatus !== $order['ORDER']['STATUS_ID'] && $filterShowCanceled !== 'Y')
			{
				$orderHeaderStatus = $order['ORDER']['STATUS_ID'];
				?>

				<?
			}
			?>

			<?
		}
	}
	?>
	<div class="clearfix"></div>
	<?
	echo $arResult["NAV_STRING"];

	if ($filterHistory !== 'Y')
	{
		$javascriptParams = array(
			"url" => CUtil::JSEscape($this->__component->GetPath().'/ajax.php'),
			"templateFolder" => CUtil::JSEscape($templateFolder),
			"templateName" => $this->__component->GetTemplateName(),
			"paymentList" => $paymentChangeData,
			"returnUrl" => CUtil::JSEscape($arResult["RETURN_URL"]),
		);
		$javascriptParams = CUtil::PhpToJSObject($javascriptParams);
		?>
		<script>
			BX.Sale.PersonalOrderComponent.PersonalOrderList.init(<?=$javascriptParams?>);
		</script>
		<?
	}
}
