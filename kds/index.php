<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\UI\Extension;

global $USER;

$APPLICATION->SetTitle("Доска заказов");

// Доступ: только авторизованные сотрудники (вход в партнёрском разделе)
if (!$USER->IsAuthorized()) {
    LocalRedirect('/partners/?backurl=' . urlencode('/kds/'));
}

// ============================================================
// Доска заказов ресторана — отдельный интерфейс кухни.
// Без шаблона сайта и бокового меню: страница сама выводит HTML,
// подключает только свои стили (/kds/kds.css) и JS-расширение ldo.kds.
// Ресторан выбирается на экране выбора либо в адресе: ?RESTAURANT=<xml_id>
// ============================================================

Extension::load(['ui.vue3', 'ldo.kds']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Доска заказов</title>
    <? $APPLICATION->ShowHead(); ?>
    <link rel="stylesheet" href="/kds/kds.css">
</head>
<body class="kds-body">
<?
$APPLICATION->IncludeComponent(
    'ldo:kds.board',
    '.default',
    [
        'COMPONENT_TEMPLATE' => '.default',
        // Этапы готовки кухни: Принят → Готовится → Готов → Передан
        'STAGES' => ['accepted', 'cooking', 'ready', 'handed'],
        'DAYS_BACK' => '1',
        'LIMIT' => '200',
    ],
    false
);
?>
</body>
</html>
<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
