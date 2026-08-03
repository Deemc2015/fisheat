<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();?>
<?
/**
 * Шапка шаблона "partners" (раздел /partners/).
 * Подключается ПОСЛЕ require prolog_before и подготовки данных страницы.
 *
 * Проверка авторизации: если пользователь не авторизован — редирект на /partners/
 * (страница входа — отдельный файл /partners/login.php).
 *
 * Параметры (все опциональные, задаются на странице ДО include):
 *   $partnersActivePage    — ключ активного пункта меню
 *                            (overview|delivery-zones|statistics|orders|finance|menu|reports|settings)
 *   $partnersPageTitle     — заголовок (h1 в шапке)
 *   $partnersHeaderStyle   — доп. inline-стиль для .p-header (например "padding-bottom:0; border-bottom:none;")
 *   $partnersHeaderActions — HTML блока действий справа в шапке (по умолчанию — кнопка «Выйти»)
 *   $partnersLogoutUrl     — URL выхода (по умолчанию вычисляется из текущего адреса)
 *   $partnersSidebarBottom — доп. HTML внизу сайдбара (перед </aside>)
 */
global $USER;

// Проверка авторизации (редирект на вход, если не авторизован)
include $_SERVER['DOCUMENT_ROOT'] . '/partners/auth.php';

$partnersActivePage    = isset($partnersActivePage) ? (string)$partnersActivePage : '';
$partnersPageTitle     = isset($partnersPageTitle) ? (string)$partnersPageTitle : '';
$partnersHeaderStyle   = isset($partnersHeaderStyle) ? (string)$partnersHeaderStyle : '';
$partnersHeaderActions = isset($partnersHeaderActions) ? (string)$partnersHeaderActions : '';
$partnersLogoutUrl     = isset($partnersLogoutUrl) && $partnersLogoutUrl !== '' ? (string)$partnersLogoutUrl : rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/?logout=yes';
$partnersSidebarBottom = isset($partnersSidebarBottom) ? (string)$partnersSidebarBottom : '';

$partnersNav = function ($key) use ($partnersActivePage) {
    return $partnersActivePage === $key ? ' active' : '';
};

// Стили и скрипты шаблона (по аналогии с основным шаблоном сайта)
\Bitrix\Main\Page\Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/fonts/fonts.css");
\Bitrix\Main\Page\Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/style.css");
\Bitrix\Main\Page\Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/partners.css");
\Bitrix\Main\Page\Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/media.css");
\Bitrix\Main\Page\Asset::getInstance()->addCss("/partners/style.css");
\Bitrix\Main\Page\Asset::getInstance()->addJs(SITE_TEMPLATE_PATH . '/assets/js/jquery.min.js');
\Bitrix\Main\Page\Asset::getInstance()->addJs(SITE_TEMPLATE_PATH . '/assets/js/main.js');
\Bitrix\Main\Page\Asset::getInstance()->addJs("/partners/script.js");
?>
<!DOCTYPE html>
<html>
<head>
<?$APPLICATION->ShowHead();?>
<meta name="robots" content="noindex, nofollow" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="format-detection" content="telephone=no" />
<link rel="icon" href="/favicon.webp" >
<title><?$APPLICATION->ShowTitle()?></title>
</head>
<body>
<?$APPLICATION->ShowPanel()?>

<div class="partners-page">
    <div class="p-overlay" id="p-overlay" onclick="togglePartnersMenu()"></div>

    <aside class="p-sidebar" id="p-sidebar">

        <!-- Логотип -->
        <div class="p-sidebar__logo">
            <a href="/partners/">
                <img src="<?=SITE_TEMPLATE_PATH?>/assets/images/logo.svg" alt="Рыба закусывала">
            </a>
        </div>

        <?$APPLICATION->IncludeComponent("bitrix:menu", "partners-menu", Array(
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
    </aside>

    <main class="p-content">
        <div class="p-header"<?= $partnersHeaderStyle !== '' ? ' style="' . htmlspecialchars($partnersHeaderStyle, ENT_QUOTES) . '"' : '' ?>>
            <div style="display:flex; align-items:center;">
                <button class="p-mobile-toggle" onclick="togglePartnersMenu()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 18H21V16H3V18ZM3 13H21V11H3V13ZM3 6V8H21V6H3Z" fill="white"/>
                    </svg>
                </button>
                <h1 class="p-header__title"><?$APPLICATION->ShowTitle(false);?></h1>
            </div>
            <div class="p-header__actions">
                <?= $partnersHeaderActions !== '' ? $partnersHeaderActions : '<button class="p-header__action-btn" title="Выйти" onclick="document.location=\'' . htmlspecialchars($partnersLogoutUrl, ENT_QUOTES) . '\'"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17 7L15.59 8.41L18.17 11H8V13H18.17L15.59 15.58L17 17L22 12L17 7ZM4 5H12V3H4C2.9 3 2 3.9 2 5V19C2 20.1 2.9 21 4 21H12V19H4V5Z" fill="white"/></svg></button>' ?>
            </div>
        </div>
