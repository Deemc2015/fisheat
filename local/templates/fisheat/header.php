<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();?>

<?
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Loader;
use \Ldo\Favorites\Favorites;
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
?>
</head>

<?$APPLICATION->ShowPanel()?>
<body>
<header>
    <div class="container">
        <div class="header">
            <?if($isMobile):?>
            <div class="mobile-head hidden-pk">
                <div class="burger"></div>
                <a href="/" class="logo-mobile"></a>
                <div class="mobile-menu"></div>
            </div>
            <?else:?>
                <div class="top-header hidden-mobile">
                    <div class="top-menu ">
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

                    </div>
                    <div class="location hidden-mobile">Уфа</div>
                    <div class="top-header__right hidden-mobile">
                        <a href="tel:" class="phone">8 (917) 041 29 29</a>
                        <div class="time-work">12:00 - 22:30</div>
                    </div>
                </div>
                <div class="bottom-header hidden-mobile">
                    <a href="/" class="logo"></a>
                    <div class="search-block">
                        <?$APPLICATION->IncludeComponent(
                            "bitrix:search.title",
                            "top-search",
                            array(
                                "COMPONENT_TEMPLATE" => "top-search",
                                "NUM_CATEGORIES" => "1",
                                "TOP_COUNT" => "10",
                                "ORDER" => "date",
                                "USE_LANGUAGE_GUESS" => "Y",
                                "CHECK_DATES" => "N",
                                "SHOW_OTHERS" => "N",
                                "PAGE" => "#SITE_DIR#search/index.php",
                                "SHOW_INPUT" => "Y",
                                "INPUT_ID" => "title-search-input",
                                "CONTAINER_ID" => "title-search",
                                "CATEGORY_0_TITLE" => "",
                                "CATEGORY_0" => array(
                                    0 => "iblock_catalog",
                                ),
                                "CATEGORY_0_iblock_catalog" => array(
                                    0 => "4",
                                )
                            ),
                            false
                        );?>
                    </div>
                    <div class="button-header">
                        <?
                        $wishCountItems = 0;

                        if(Loader::IncludeModule('ldo.favorites')){
                            $wishCountItems = Favorites::getCount();
                        }

                        ?>
                        <a href="/izbrannye-tovary/" class="wish-page">

                            <span class="count-wish" <?if($wishCountItems == 0){echo "style='display:none;'";}?>><?=$wishCountItems?></span>

                            <i><svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M0.275391 8.10603C0.275391 4.47745 2.92383 1.375 6.37957 1.375C8.24943 1.375 9.89384 2.29469 11.0004 3.70532C12.1069 2.29474 13.7511 1.375 15.62 1.375C19.0769 1.375 21.7254 4.47884 21.7254 8.10603C21.7254 9.56485 21.1742 10.9684 20.378 12.2412C19.5801 13.5171 18.5056 14.7106 17.382 15.7659C15.1355 17.8759 12.6052 19.5137 11.4295 20.2297C11.1658 20.3902 10.8346 20.3902 10.571 20.2294C9.39559 19.513 6.86526 17.8751 4.61875 15.7654C3.49519 14.7102 2.42075 13.5168 1.62276 12.2411C0.826642 10.9684 0.275391 9.56486 0.275391 8.10603ZM6.37957 3.025C4.0042 3.025 1.92539 5.20988 1.92539 8.10603C1.92539 9.14968 2.32268 10.2487 3.02163 11.3661C3.71869 12.4804 4.68581 13.5648 5.7483 14.5626C7.63512 16.3346 9.75367 17.7701 11.0005 18.5546C12.2472 17.7707 14.3657 16.3352 16.2525 14.5631C17.3149 13.5653 18.2821 12.4808 18.9791 11.3663C19.6781 10.2488 20.0754 9.14969 20.0754 8.10603C20.0754 5.21126 17.9966 3.025 15.62 3.025C13.9927 3.025 12.5231 4.03204 11.7395 5.61171C11.6001 5.89247 11.3138 6.07006 11.0004 6.07006C10.687 6.07006 10.4006 5.89247 10.2613 5.61171C9.47772 4.03215 8.00817 3.025 6.37957 3.025Z" fill="white"/>
                                </svg>
                            </i>Избранное</a>
                        <?$APPLICATION->IncludeComponent(
                            "bitrix:sale.basket.basket.line",
                            "header-cart",
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

                        <a href="/" class="lk-page"><i><svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <g clip-path="url(#clip0_35_350)">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M7.59375 1.32692C4.13268 1.32692 1.32692 4.13268 1.32692 7.59375C1.32692 9.11467 1.86873 10.5091 2.76986 11.5944C3.8694 10.4699 5.34676 9.54277 7.59375 9.54277C9.8397 9.54277 11.3171 10.4626 12.4183 11.5936C13.319 10.5084 13.8606 9.1143 13.8606 7.59375C13.8606 4.13268 11.0548 1.32692 7.59375 1.32692ZM11.6143 12.401C10.6697 11.4254 9.46463 10.6822 7.59375 10.6822C5.72502 10.6822 4.5195 11.43 3.57396 12.4017C4.66204 13.3124 6.06388 13.8606 7.59375 13.8606C9.12397 13.8606 10.5261 13.3121 11.6143 12.401ZM0.1875 7.59375C0.1875 3.50339 3.50339 0.1875 7.59375 0.1875C11.6841 0.1875 15 3.50339 15 7.59375C15 11.6841 11.6841 15 7.59375 15C3.50339 15 0.1875 11.6841 0.1875 7.59375ZM7.59375 4.20543C6.51733 4.20543 5.64473 5.07803 5.64473 6.15445C5.64473 7.23085 6.51733 8.10345 7.59375 8.10345C8.67015 8.10345 9.54277 7.23085 9.54277 6.15445C9.54277 5.07803 8.67015 4.20543 7.59375 4.20543ZM4.50531 6.15445C4.50531 4.44874 5.88805 3.06601 7.59375 3.06601C9.29947 3.06601 10.6822 4.44874 10.6822 6.15445C10.6822 7.86015 9.29947 9.24285 7.59375 9.24285C5.88805 9.24285 4.50531 7.86015 4.50531 6.15445Z" fill="white"/>
                                    </g>
                                    <defs>
                                        <clipPath id="clip0_35_350">
                                            <rect width="15" height="15" fill="white"/>
                                        </clipPath>
                                    </defs>
                                </svg></i>Войти</a>
                    </div>
                </div>
            <?endif?>


        </div>
    </div>
</header>

<div class="container">
    <div class="wrapper">
        <?if($left_menu == 'Y'):?>
        <?if(!$isMobile):?>
            <aside class="hidden-mobile">
                <div data-mcs-theme="minimal" class="block-menu mycustom-scroll">
                    <?$APPLICATION->IncludeComponent(
            "bitrix:catalog.section.list",
            "product-menu",
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
            </aside>
            <?endif;?>
        <?endif;?>
        
        <?if($left_menu == 'P'):?>
        <aside class="profile-page">
            <div class="block-menu ">
                <?$APPLICATION->IncludeComponent("bitrix:menu", "personal-menu", Array(
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
		"ROOT_MENU_TYPE" => "personal",	// Тип меню для первого уровня
		"USE_EXT" => "N",	// Подключать файлы с именами вида .тип_меню.menu_ext.php
	),
	false
);?>
            </div>
        </aside>
        <?endif;?>

    <?if($APPLICATION->GetCurPage(false) !== '/oformlenie-zakaza/'):?>
        
    <div class="content <?if($APPLICATION->GetCurPage(false) == '/personal/'){ echo 'privilegies-block' ;}?>">
<? if ($APPLICATION->GetCurPage(false) !== '/'): ?>
<section id="page" class="<?if($APPLICATION->GetCurPage(false) == '/personal/'){ echo 'personal-first' ;}?>">


    <?if(!$isMobile):?>

    <div class="top-page">
        <h1 class="title-page"><?$APPLICATION->ShowTitle()?></h1>
        <div class="bread-link"><div class="bread-toggle"></div>   <?$APPLICATION->IncludeComponent(
	"bitrix:breadcrumb", 
	"breadcrumb", 
	array(
		"PATH" => "",
		"SITE_ID" => "s1",
		"START_FROM" => "0",
		"COMPONENT_TEMPLATE" => "breadcrumb"
	),
	false
);?>
        </div>
    </div>

    <?endif?>



<? endif; ?>

    <? endif; ?>
