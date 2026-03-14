<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();?>

<?
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Loader;
use \Ldo\Favorites\Favorites;
use \Ldo\Develop\Pages;
?>
<!DOCTYPE html>
<html>
<head>
<?$APPLICATION->ShowHead();?>
<meta name="robots" content="noindex, nofollow" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="format-detection" content="telephone=no" />
<title><?$APPLICATION->ShowTitle()?></title>

<?
    //css
    Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/fonts/fonts.css");
    Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/slick.css");
    Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/slick-theme.css");
    Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/jquery.mCustomScrollbar.css");

    Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/style.css");
    Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/media.css");

    //js
    Asset::getInstance()->addJs(SITE_TEMPLATE_PATH. '/assets/js/jquery.min.js');
    Asset::getInstance()->addJs(SITE_TEMPLATE_PATH. '/assets/js/slick.min.js');
    Asset::getInstance()->addJs(SITE_TEMPLATE_PATH. '/assets/js/jquery.cookie.min.js');
    Asset::getInstance()->addJs(SITE_TEMPLATE_PATH. '/assets/js/masked.js');
    Asset::getInstance()->addJs(SITE_TEMPLATE_PATH. '/assets/js/jquery.mCustomScrollbar.concat.min.js');
    Asset::getInstance()->addJs(SITE_TEMPLATE_PATH. '/assets/js/main.js');

    $left_menu = $APPLICATION->GetProperty("left_menu");

    /*Определение мобильного устройства*/
    $isMobile = \Bitrix\Main\Loader::includeModule('conversion') && ($md=new \Bitrix\Conversion\Internals\MobileDetect) && $md->isMobile();

    if($_SERVER['HTTP_USER_AGENT'] != 'USER_APP_FISH_EAT'){
        echo "Будет переадресация на заглушку или еще что-то";
    }

?>
</head>

<?$APPLICATION->ShowPanel()?>
<body>
<header>
    <div class="container">
        <div class="header">
            <div class="mobile-head hidden-pk">
                <div class="burger"></div>
                <a href="/mobile_app/" class="logo-mobile"></a>
                <div class="mobile-menu"></div>
            </div>
            <div class="mobile-main-menu">
                <span class="close-menu"></span>
                <div class="mobile-menu-top">
                    <div class="location ">Уфа</div>
                    <a href="tel:+79170472929" class="phone">8 (917) 041 29 29</a>
                </div>
                <?$APPLICATION->IncludeComponent("bitrix:menu", "top-menu", Array(
                    "ALLOW_MULTI_SELECT" => "N",	// Разрешить несколько активных пунктов одновременно
                    "CHILD_MENU_TYPE" => "left",	// Тип меню для остальных уровней
                    "DELAY" => "N",	// Откладывать выполнение шаблона меню
                    "MAX_LEVEL" => "1",	// Уровень вложенности меню
                    "MENU_CACHE_GET_VARS" => array(	// Значимые переменные запроса
                        0 => "",
                    ),
                    "MENU_CACHE_TIME" => "3600",	// Время кеширования (сек.)
                    "MENU_CACHE_TYPE" => "N",	// Тип кеширования
                    "MENU_CACHE_USE_GROUPS" => "Y",	// Учитывать права доступа
                    "ROOT_MENU_TYPE" => "top",	// Тип меню для первого уровня
                    "USE_EXT" => "N",	// Подключать файлы с именами вида .тип_меню.menu_ext.php
                ),
                    false
                );?>
                <div class="bottom-menu-mobile">
                    <div class="aplication-line">
                        <a class="rustore" href="#"></a>
                        <a class="appstore" href="#"></a>
                    </div>
                    <span>Отдел качества</span>
                    <a href="tel:+79170472929" class="phone">8 (777) 777 77 77</a>
                </div>
            </div>
        </div>
    </div>
</header>


    <div class="menu-view-mobile">
        <span class="menu-view-mobile__close"></span>
        <?$APPLICATION->IncludeComponent(
            "bitrix:catalog.section.list",
            "product-menu-modal",
            array(
                "ADDITIONAL_COUNT_ELEMENTS_FILTER" => "additionalCountFilter",
                "ADD_SECTIONS_CHAIN" => "N",
                "CACHE_FILTER" => "N",
                "CACHE_GROUPS" => "Y",
                "CACHE_TIME" => "36000000",
                "CACHE_TYPE" => "A",
                "COUNT_ELEMENTS" => "N",
                "COUNT_ELEMENTS_FILTER" => "CNT_ACTIVE",
                "FILTER_NAME" => "sectionsFilter",
                "HIDE_SECTIONS_WITH_ZERO_COUNT_ELEMENTS" => "N",
                "IBLOCK_ID" => "4",
                "IBLOCK_TYPE" => "catalog",
                "SECTION_CODE" => "",
                "SECTION_FIELDS" => array(
                    0 => "NAME",
                    1 => "PICTURE",
                    2 => "",
                ),
                "SECTION_ID" => '',
                "SECTION_URL" => "",
                "SECTION_USER_FIELDS" => array(
                    0 => "",
                    1 => "",
                ),
                "SHOW_PARENT_NAME" => "Y",
                "TOP_DEPTH" => "1",
                "VIEW_MODE" => "LINE",
                "COMPONENT_TEMPLATE" => "product-menu"
            ),
            false
        );?>
    </div>

<div class="container">
    <div class="wrapper">

    <?if($APPLICATION->GetCurPage(false) !== '/oformlenie-zakaza/'):?>
        
    <div class="content <?if($APPLICATION->GetCurPage(false) == '/personal/'){ echo 'privilegies-block' ;}?>">
<? if ($APPLICATION->GetCurPage(false) !== '/'): ?>
<section id="page" class="<?if($APPLICATION->GetCurPage(false) == '/personal/'){ echo 'personal-first' ;}?>">

<? endif; ?>

    <? endif; ?>
