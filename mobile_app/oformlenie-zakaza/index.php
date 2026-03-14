<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Оформление заказа");


class AcritBonusInOrderOpensourceIntegration
{
    public static function init(): void
    {
        $eventManager = Bitrix\Main\EventManager::getInstance();
        $eventManager->addEventHandler('sale', 'OnSaleOrderSaved',
            static function (Bitrix\Main\Event $event) {
                /** @var \Bitrix\Sale\Order $order */
                $order = $event->getParameter("ENTITY");
                $isNew = $event->getParameter("IS_NEW");

                if (\Bitrix\Main\Loader::includeModule('acrit.bonus') && $isNew) {
                    $params = [];
                    // if bonus-fields outside main order form-tag
                    if ((int)$_SESSION['BONUS_PAY_USER_VALUE'] > 0) {
                        $params['PAY_BONUS_ACCOUNT'] = 'Y';
                    }
                    \Acrit\Bonus\Core::OnSaleComponentOrderOneStepComplete($order->getId(), $order->getFieldValues(), $params);
                }
            }
        );
    }
}
AcritBonusInOrderOpensourceIntegration::init();



?><?$APPLICATION->IncludeComponent(
	"opensource:order", 
	"order-page", 
	array(
		"COMPONENT_TEMPLATE" => "order-page",
		"DEFAULT_PERSON_TYPE_ID" => "1",
		"DEFAULT_DELIVERY_ID" => "2",
		"DEFAULT_PAY_SYSTEM_ID" => "3",
		"PATH_TO_BASKET" => "/personal/cart/"
	),
	false
);?><?





//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>