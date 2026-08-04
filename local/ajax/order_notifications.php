<?php

/**
 * POLL-эндпоинт уведомлений о смене статуса заказа.
 *
 * Возвращает JSON со списком новых уведомлений текущего пользователя
 * и удаляет их из очереди (метод Ldo\OrderNotifications::poll).
 * Для неавторизованных пользователей возвращает пустой список.
 */

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;

global $USER;

$APPLICATION->RestartBuffer();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!$USER || !$USER->IsAuthorized()) {
    echo json_encode(['items' => []]);
    return;
}

require_once($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/order_notifications.php");

try {
    $items = \Ldo\OrderNotifications::poll((int)$USER->GetID());
} catch (\Throwable $e) {
    $items = [];
}

echo json_encode(['items' => $items]);
