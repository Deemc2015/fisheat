<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

/**
 * Компонент "Список пользователей".
 */

$arComponentParameters = array(
	"PARAMETERS" => array(
		"PAGE_SIZE" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("LDO_USERS_LIST_PAGE_SIZE"),
			"TYPE" => "STRING",
			"DEFAULT" => "50",
		),
		"CACHE_TIME" => array(
			"DEFAULT" => 0,
		),
	),
);
