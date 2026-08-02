<?
/**
 * AJAX-endpoint синхронизации меню iiko.
 * Отдельный файл, чтобы страница /partners/menu/ оставалась обычной
 * и отдавала шапку/контент, а этот файл возвращал чистый JSON.
 */
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Ldo\Iiko\SettingsTable;

global $USER;

$request = Context::getCurrent()->getRequest();

$jsonResponse = function ($payload) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
};

if (!$USER->IsAuthorized()) {
    $jsonResponse(['status' => 'error', 'errors' => [['message' => 'Требуется авторизация.']]]);
}

if (!check_bitrix_sessid()) {
    $jsonResponse(['status' => 'error', 'errors' => [['message' => 'Сессия истекла, обновите страницу.']]]);
}

$siteId = 's1';
if (!Loader::includeModule('ldo.iiko')) {
    $jsonResponse(['status' => 'error', 'errors' => [['message' => 'Модуль ldo.iiko не подключён.']]]);
}

set_time_limit(0);
$progressToken = (string)$request->getPost('token');

try {
    // Освобождаем сессию, чтобы параллельный опрос прогресса не блокировался
    if (function_exists('session_write_close')) {
        @session_write_close();
    }

    $product = new \Ldo\Iiko\Product($siteId);
    $result = $product->sync($progressToken);

    if ((int)$result['sections'] === 0 && (int)$result['items'] === 0) {
        SettingsTable::setStatus($siteId, 'ERROR');
        $jsonResponse([
            'status' => 'error',
            'errors' => [['message' => 'Не удалось получить данные от iiko — проверьте настройки подключения.']],
        ]);
    }

    SettingsTable::setStatus($siteId, 'OK');
    $jsonResponse([
        'status' => 'success',
        'data' => [
            'sections' => (int)$result['sections'],
            'items' => (int)$result['items'],
        ],
    ]);
} catch (\Exception $e) {
    SettingsTable::setStatus($siteId, 'ERROR');
    addMessage2Log('Partners menu sync error: ' . $e->getMessage());
    $jsonResponse([
        'status' => 'error',
        'errors' => [['message' => 'Ошибка синхронизации: ' . $e->getMessage()]],
    ]);
}
