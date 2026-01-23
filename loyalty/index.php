<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
		$APPLICATION->SetTitle("Реферальная программа лояльности и бонусная система");
		$APPLICATION->AddChainItem("Реферальная программа лояльности и бонусная система", "/loyalty/");?>
		<?$APPLICATION->IncludeComponent("skyweb24:loyaltyprogram", ".default", array(
			"CACHE_TIME" => "3600",
			"CACHE_TYPE" => "A",
			"CHAIN_REFERRAL" => "Кабинет рефералодателя",
			"CHAIN_BONUSES" => "Кабинет пользователя",
			"DISPLAY_PAGER" => "Y",
			"PAGER_COUNT" => "10",
			"PAGER_NAME" => "",
			"PAGER_TEMPLATE" => "modern",
			"SEF_FOLDER" => "/loyalty/",
			"SEF_MODE" => "Y",
			"COMPONENT_TEMPLATE" => ".default",
			"TITLE_REFERRAL" => "Кабинет рефералодателя",
			"TITLE_BONUSES" => "Кабинет пользователя",
			"SEF_URL_TEMPLATES" => array(
				"referral" => "referral/",
				"bonuses" => "bonuses/",
			)),	false);?>
		<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>