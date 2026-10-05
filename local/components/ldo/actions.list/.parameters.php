<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

/**
 * Компонент "Список акций".
 * ID инфоблока задан константой в class.php (ActionsList::IBLOCK_ID = 6).
 */

$arComponentParameters = array(
	"PARAMETERS" => array(
		"LIMIT" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("LDO_ACTIONS_LIST_LIMIT"),
			"TYPE" => "STRING",
			"DEFAULT" => "100",
		),
		"CACHE_TIME" => array(
			"DEFAULT" => 3600000,
		),
	),
);
