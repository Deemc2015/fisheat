<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

/**
 * Компонент "Список заказов" (партнёрский раздел).
 * Читает заказы из модуля sale через штатный ORM D7 (OrderTable, BasketTable и т.д.).
 *
 * Параметры DELIVERY_SERVICES / PAY_SYSTEMS — мультиселекты "какие способы выводить
 * в фильтре". Значения (список служб доставки / платёжных систем) подгружаются
 * динамически из модуля sale; если параметр не выбран (пусто) — выводятся все.
 */

// Списки значений для параметров (заполняются при редактировании параметров компонента
// и один раз на запрос при выполнении — getDefaultParams()).
$deliveryOptions = [];
$payOptions = [];
try {
    if (\Bitrix\Main\Loader::includeModule('sale')) {
        $rsDelivery = \Bitrix\Sale\Delivery\Services\Table::getList([
            'select' => ['ID', 'NAME'],
            'order'  => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);
        while ($row = $rsDelivery->fetch()) {
            $deliveryOptions[(int)$row['ID']] = (string)$row['NAME'];
        }

        $rsPay = \Bitrix\Sale\Internals\PaySystemActionTable::getList([
            'select' => ['ID', 'NAME'],
            'order'  => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);
        while ($row = $rsPay->fetch()) {
            $payOptions[(int)$row['ID']] = (string)$row['NAME'];
        }
    }
} catch (\Throwable $e) {
}

$arComponentParameters = array(
	"GROUPS" => array(
	),
	"PARAMETERS" => array(
		"DELIVERY_SERVICES" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("LDO_ORDERS_LIST_DELIVERY_SERVICES"),
			"TYPE" => "LIST",
			"MULTIPLE" => "Y",
			"VALUES" => $deliveryOptions,
			"DEFAULT" => array(),
			"REFRESH" => "N",
		),
		"PAY_SYSTEMS" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("LDO_ORDERS_LIST_PAY_SYSTEMS"),
			"TYPE" => "LIST",
			"MULTIPLE" => "Y",
			"VALUES" => $payOptions,
			"DEFAULT" => array(),
			"REFRESH" => "N",
		),
		"PAGE_SIZE" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("LDO_ORDERS_LIST_PAGE_SIZE"),
			"TYPE" => "STRING",
			"DEFAULT" => "10",
		),
		"EXPORT_ENABLED" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("LDO_ORDERS_LIST_EXPORT_ENABLED"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "Y",
		),
		"CACHE_TIME" => array(
			"DEFAULT" => 0,
		),
	),
);
