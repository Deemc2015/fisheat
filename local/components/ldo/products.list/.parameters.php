<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

/**
 * Компонент "Список товаров".
 * ID инфоблока задан константой в class.php (ProductsList::IBLOCK_ID = 4).
 */

$arComponentParameters = array(
	"GROUPS" => array(
		"SORT_SETTINGS" => array(
			"NAME" => GetMessage("LDO_PRODUCTS_LIST_GROUP_SORT"),
			"SORT" => 100,
		),
	),
	"PARAMETERS" => array(
		"PAGE_SIZE" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("LDO_PRODUCTS_LIST_PAGE_SIZE"),
			"TYPE" => "STRING",
			"DEFAULT" => "30",
		),
		"SORT_FIELD" => array(
			"PARENT" => "SORT_SETTINGS",
			"NAME" => GetMessage("LDO_PRODUCTS_LIST_SORT_FIELD"),
			"TYPE" => "LIST",
			"VALUES" => array(
				"SORT" => GetMessage("LDO_PRODUCTS_LIST_SORT_FIELD_SORT"),
				"NAME" => GetMessage("LDO_PRODUCTS_LIST_SORT_FIELD_NAME"),
				"ID"   => GetMessage("LDO_PRODUCTS_LIST_SORT_FIELD_ID"),
			),
			"DEFAULT" => "SORT",
		),
		"SORT_ORDER" => array(
			"PARENT" => "SORT_SETTINGS",
			"NAME" => GetMessage("LDO_PRODUCTS_LIST_SORT_ORDER"),
			"TYPE" => "LIST",
			"VALUES" => array(
				"ASC"  => GetMessage("LDO_PRODUCTS_LIST_SORT_ORDER_ASC"),
				"DESC" => GetMessage("LDO_PRODUCTS_LIST_SORT_ORDER_DESC"),
			),
			"DEFAULT" => "ASC",
		),
		"CACHE_TIME" => array(
			"DEFAULT" => 3600000,
		),
	),
);
