<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

/**
 * Компонент "Список заказов" (партнёрский раздел).
 * Читает заказы из модуля sale через штатный ORM D7 (OrderTable, BasketTable и т.д.).
 */

$arComponentParameters = array(
	"GROUPS" => array(
	),
	"PARAMETERS" => array(
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
