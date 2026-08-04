<?php

/**
 * Логика уведомлений о смене статуса заказа (Push & Poll).
 * PUSH — при смене статуса заказа (sale:OnSaleStatusOrderChange) создаётся
 *       уведомление для владельца заказа.
 * POLL  — JS на странице периодически опрашивает /local/ajax/order_notifications.php
 *         и показывает новые уведомления пользователю.
 */

require_once __DIR__ . '/order_notifications.php';

use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;

Loader::includeModule('main');

// Создание таблицы уведомлений (если ещё не создана)
\Ldo\OrderNotifications::install();

// Регистрация обработчика смены статуса заказа
EventManager::getInstance()->addEventHandler(
    'sale',
    'OnSaleStatusOrderChange',
    ['\Ldo\OrderNotifications', 'onOrderStatusChange']
);

// Клиентский poll: периодический опрос уведомлений на всех страницах сайта
if (!defined('ADMIN_SECTION') || ADMIN_SECTION !== true) {
    \Bitrix\Main\Page\Asset::getInstance()->addString('
    <script>
    (function () {
        "use strict";

        var POLL_URL = "/local/ajax/order_notifications.php";
        var POLL_INTERVAL = 15000; // опрос каждые 15 секунд
        var TOAST_DURATION = 8000;

        function poll() {
            if (typeof fetch === "undefined") {
                return;
            }

            fetch(POLL_URL, {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store"
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data && Array.isArray(data.items) && data.items.length) {
                        data.items.forEach(function (item) {
                            if (item && item.message) {
                                showToast(item.message);
                            }
                        });
                    }
                })
                .catch(function () { /* молча пропускаем ошибки сети */ });
        }

        function showToast(message) {
            var box = document.getElementById("order-notifications-toast");
            if (!box) {
                box = document.createElement("div");
                box.id = "order-notifications-toast";
                box.style.cssText = "position:fixed;right:16px;top:16px;z-index:99999;" +
                    "display:flex;flex-direction:column;gap:10px;max-width:340px;";
                document.body.appendChild(box);
            }

            var el = document.createElement("div");
            el.style.cssText = "padding:14px 16px;background:#1B1818;color:#FFFFFF;" +
                "border:1px solid #868686;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.35);" +
                "font-size:14px;line-height:1.4;font-family:\'Blogger Sans\', Roboto, sans-serif;";
            el.textContent = message;

            box.appendChild(el);

            setTimeout(function () {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, TOAST_DURATION);
        }

        // Первый опрос — через 2 секунды, далее по интервалу
        setTimeout(poll, 2000);
        setInterval(poll, POLL_INTERVAL);
    })();
    </script>
    ', true);
}
