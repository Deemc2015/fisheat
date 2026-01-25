<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();?>
<? if ($APPLICATION->GetCurPage(false) !== '/'): ?>

</section>
<?endif;?>


<?
$viewed_product = $APPLICATION->GetProperty("viewed-product");
?>

<?if($viewed_product == 'Y'):?>

    <?
    $APPLICATION->IncludeComponent(
	"bitrix:catalog.products.viewed", 
	"viewed", 
	array(
		"ACTION_VARIABLE" => "action_cpv",
		"ADDITIONAL_PICT_PROP_4" => "-",
		"ADD_PROPERTIES_TO_BASKET" => "Y",
		"ADD_TO_BASKET_ACTION" => "ADD",
		"BASKET_URL" => "/personal/basket.php",
		"CACHE_GROUPS" => "Y",
		"CACHE_TIME" => "3600",
		"CACHE_TYPE" => "A",
		"CONVERT_CURRENCY" => "N",
		"DEPTH" => "2",
		"DISPLAY_COMPARE" => "N",
		"ENLARGE_PRODUCT" => "STRICT",
		"HIDE_NOT_AVAILABLE" => "N",
		"HIDE_NOT_AVAILABLE_OFFERS" => "N",
		"IBLOCK_ID" => "4",
		"IBLOCK_MODE" => "multi",
		"IBLOCK_TYPE" => "catalog",
		"LABEL_PROP_4" => array(
		),
		"LABEL_PROP_POSITION" => "top-left",
		"MESS_BTN_ADD_TO_BASKET" => "В корзину",
		"MESS_BTN_BUY" => "Купить",
		"MESS_BTN_DETAIL" => "Подробнее",
		"MESS_BTN_SUBSCRIBE" => "Подписаться",
		"MESS_NOT_AVAILABLE" => "Нет в наличии",
		"PAGE_ELEMENT_COUNT" => "6",
		"PARTIAL_PRODUCT_PROPERTIES" => "N",
		"PRICE_CODE" => array(
			0 => "BASE",
		),
		"PRICE_VAT_INCLUDE" => "Y",
		"PRODUCT_BLOCKS_ORDER" => "price,props,sku,quantityLimit,quantity,buttons",
		"PRODUCT_ID_VARIABLE" => "id",
		"PRODUCT_PROPS_VARIABLE" => "ATT",
		"PRODUCT_QUANTITY_VARIABLE" => "quantity",
		"PRODUCT_ROW_VARIANTS" => "[{'VARIANT':'6','BIG_DATA':false}]",
		"PRODUCT_SUBSCRIPTION" => "Y",
		"SECTION_CODE" => "",
		"SECTION_ELEMENT_CODE" => "",
		"SECTION_ELEMENT_ID" => $GLOBALS["CATALOG_CURRENT_ELEMENT_ID"],
		"SECTION_ID" => $GLOBALS["CATALOG_CURRENT_SECTION_ID"],
		"SHOW_CLOSE_POPUP" => "N",
		"SHOW_DISCOUNT_PERCENT" => "N",
		"SHOW_FROM_SECTION" => "N",
		"SHOW_MAX_QUANTITY" => "N",
		"SHOW_OLD_PRICE" => "N",
		"SHOW_PRICE_COUNT" => "1",
		"SHOW_SLIDER" => "Y",
		"SLIDER_INTERVAL" => "3000",
		"SLIDER_PROGRESS" => "N",
		"TEMPLATE_THEME" => "blue",
		"USE_ENHANCED_ECOMMERCE" => "N",
		"USE_PRICE_COUNT" => "N",
		"USE_PRODUCT_QUANTITY" => "N",
		"COMPONENT_TEMPLATE" => "viewed",
		"SHOW_PRODUCTS_4" => "N"
	),
	false
);?>
<?endif;?>
<footer>

<?if($isMobile):?>
    <div class="mobile-footer">
        <div class="mobile-footer__left">
            <a  href="/" class="home-icon <?if($APPLICATION->GetCurPage(false) == '/'){echo 'active';}?>"></a>
            <div class="search-footer-link"></div>
        </div>
        <div class="mobile-footer__right">
            <a href="/personal/" class="personal-link-footer <?if($APPLICATION->GetCurPage(false) == '/personal/'){echo 'active';}?>"></a>
            <a href="/izbrannye-tovary/" class="wish-link-footer <?if($APPLICATION->GetCurPage(false) == '/izbrannye-tovary/'){echo 'active';}?>"></a>
        </div>
    </div>

    <?$APPLICATION->IncludeComponent(
        "bitrix:sale.basket.basket.line",
        "header-mobile",
        array(
            "HIDE_ON_BASKET_PAGES" => "Y",
            "PATH_TO_AUTHORIZE" => "",
            "PATH_TO_BASKET" => "/oformlenie-zakaza/",
            "PATH_TO_ORDER" => "/oformlenie-zakaza/",
            "PATH_TO_PERSONAL" => SITE_DIR."personal/",
            "PATH_TO_PROFILE" => SITE_DIR."personal/",
            "PATH_TO_REGISTER" => SITE_DIR."login/",
            "POSITION_FIXED" => "N",
            "SHOW_AUTHOR" => "N",
            "SHOW_EMPTY_VALUES" => "Y",
            "SHOW_NUM_PRODUCTS" => "Y",
            "SHOW_PERSONAL_LINK" => "Y",
            "SHOW_PRODUCTS" => "N",
            "SHOW_REGISTRATION" => "Y",
            "SHOW_TOTAL_PRICE" => "Y",
            "COMPONENT_TEMPLATE" => "header-cart"
        ),
        false
    );?>



    <?else:?>
    <div class="top-line hidden-mobile">
        <div class="left-footer">
            <a href="/" class="footer-logo"></a>
        </div>
        <div class="center-footer">
            <div class="footer-menu">
                <?$APPLICATION->IncludeComponent(
                    "bitrix:menu",
                    "top-menu",
                    Array(
                        "ALLOW_MULTI_SELECT" => "N",
                        "CHILD_MENU_TYPE" => "left",
                        "DELAY" => "N",
                        "MAX_LEVEL" => "1",
                        "MENU_CACHE_GET_VARS" => array(""),
                        "MENU_CACHE_TIME" => "3600",
                        "MENU_CACHE_TYPE" => "N",
                        "MENU_CACHE_USE_GROUPS" => "Y",
                        "ROOT_MENU_TYPE" => "top",
                        "USE_EXT" => "N"
                    )
                );?>
            </div>
        </div>
        <div class="right-footer">
            <div class="link-aplication">
                <a href="#" class="rustore"></a>
                <a href="#" class="appstore"></a>
            </div>
        </div>
    </div>
    <div class="bottom-line hidden-mobile">
        <a href="" class="politika">Политика конфиденциальности</a>
        <span class="copyright">© 2010 - <?=Date('Y');?> Рыба закусывала</span>
        <a href="" class="politika-two">Политика cookie</a>
    </div>

    <?endif?>



</footer>
</div>
</div>
</div>

<?if(!$isMobile):?>

<?$APPLICATION->IncludeComponent(
    "ldo:cart.gifts",
    "",
    Array(
        "CACHE_TIME" => "3600000",
        "CACHE_TYPE" => "A",
        "IBLOCK_ID" => "8",
        "IBLOCK_TYPE" => "catalog"
    )
);?>

<?endif;?>

</body>
</html>