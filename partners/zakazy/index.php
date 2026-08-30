<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;
use Bitrix\Main\UI\Extension;

global $USER;

$APPLICATION->SetTitle("Заказы");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Заказы");

$request = Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

// Проверка авторизации (защищает в т.ч. выгрузку в Excel до вывода страницы)
include $_SERVER['DOCUMENT_ROOT'] . '/partners/auth.php';

// ============================================================
// Список заказов — компонент ldo:orders.list (шаблон "vue")
// Данные собирает PHP-компонент, рендерит Vue-приложение (ldo.orders на ui.vue3)
// Параметры передаются ЯВНЫМ массивом, чтобы их корректно распознавал
// визуальный редактор параметров компонента (PHPParser не разбирает переменные).
// PAGE_SIZE не указываем — берётся из параметров компонента (.parameters.php).
// ============================================================

// Экспорт в Excel: компонент сам отдаёт CSV и завершает выполнение
// (вызывается ДО шапки страницы, чтобы в файл не попал HTML-код)
if ($request->getQuery('EXPORT') === 'excel') {
    $APPLICATION->IncludeComponent(
        'ldo:orders.list',
        '',
        [
            'EXPORT_ENABLED' => 'Y',
        ]
    );
    return;
}

// Vue-приложение: расширения подключаем ДО header.php,
// т.к. ShowHead() в шаблоне выводит скрипты, добавленные до него
Extension::load(['ui.vue3', 'ldo.orders']);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->IncludeComponent(
	"ldo:orders.list", 
	"vue", 
	array(
		"EXPORT_ENABLED" => "Y",
		"COMPONENT_TEMPLATE" => "vue",
		"PAGE_SIZE" => "20",
		"CACHE_TYPE" => "A",
		"CACHE_TIME" => ""
	),
	false
);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
