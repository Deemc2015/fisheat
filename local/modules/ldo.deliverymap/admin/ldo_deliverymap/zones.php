<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Ldo\Deliverymap\DeliveryZoneTable;

$moduleId = 'ldo.deliverymap';

if (!Loader::includeModule($moduleId)) {
    ShowError("Не удалось загрузить модуль {$moduleId}");
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
    die();
}
$arSites = [];

$rsSites = CSite::GetList($by="sort", $order="desc", array("DOMAIN"=>$_SERVER['SERVER_NAME']));
while ($arSite = $rsSites->Fetch())
{
    $arSites[] = $arSite;
}

print_r($arSites);

// Получаем текущий ID сайта
$currentSiteId = 's1';



function validateCoordinates($coordinates) {
    if (!is_array($coordinates) || count($coordinates) < 3) {
        return false;
    }

    foreach ($coordinates as $point) {
        if (!is_array($point) || count($point) !== 2) {
            return false;
        }
        if (!is_numeric($point[0]) || !is_numeric($point[1])) {
            return false;
        }
        if ($point[0] < -90 || $point[0] > 90) {
            return false;
        }
        if ($point[1] < -180 || $point[1] > 180) {
            return false;
        }
    }
    return true;
}

function prepareCoordinates($coordinates) {
    if (is_string($coordinates) && !empty($coordinates)) {
        $decoded = json_decode($coordinates, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        $coordinates = $decoded;
    }

    if (!is_array($coordinates)) {
        return null;
    }

    if (count($coordinates) > 1) {
        $first = $coordinates[0];
        $last = $coordinates[count($coordinates) - 1];
        if ($first[0] == $last[0] && $first[1] == $last[1]) {
            array_pop($coordinates);
        }
    }

    return $coordinates;
}

// === ОБРАБОТКА AJAX ЗАПРОСОВ ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    if (defined('BX_DEBUG') && BX_DEBUG) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    } else {
        error_reporting(0);
        ini_set('display_errors', 0);
    }

    header('Content-Type: application/json; charset=utf-8');

    try {
        $ajaxAction = $_POST['ajax_action'] ?? '';
        $response = ['success' => false, 'error' => 'Неизвестное действие'];

        if ($ajaxAction === 'save_zone' && $permission >= "U") {
            if (!check_bitrix_sessid()) {
                throw new Exception('Ошибка сессии безопасности');
            }

            $coordinates = prepareCoordinates($_POST['COORDINATES'] ?? '');
            if ($coordinates === null) {
                throw new Exception('Неверный формат координат');
            }

            if (!validateCoordinates($coordinates)) {
                throw new Exception('Зона должна содержать минимум 3 валидные точки');
            }

            $arFields = [
                'NAME' => trim($_POST['NAME'] ?? ''),
                'PRICE' => (float)($_POST['PRICE'] ?? 0),
                'FREE_DELIVERY_PRICE' => (float)($_POST['FREE_FROM'] ?? 0),
                'DELIVERY_TIME' => (int)($_POST['DELIVERY_TIME'] ?? 0),
                'COLOR' => preg_match('/^#[a-fA-F0-9]{6}$/', $_POST['COLOR'] ?? '') ? $_POST['COLOR'] : '#00FF00',
                'SORT' => (int)($_POST['SORT'] ?? 500),
                'MIN_ORDER_PRICE' => (float)($_POST['MIN_ORDER_PRICE'] ?? 0),
                'ACTIVE' => ($_POST['ACTIVE'] ?? 'Y') === 'Y' ? 'Y' : 'N',
                'SITE_ID' => $_POST['SITE_ID'] ?? $currentSiteId,
                'COORDINATES' => $coordinates
            ];

            if (empty($arFields['NAME'])) {
                throw new Exception('Введите название зоны');
            }

            if (empty($arFields['SITE_ID'])) {
                throw new Exception('Не указан ID сайта');
            }

            if ($arFields['PRICE'] < 0) {
                throw new Exception('Цена доставки не может быть отрицательной');
            }

            if ($arFields['FREE_DELIVERY_PRICE'] < 0) {
                throw new Exception('Сумма бесплатной доставки не может быть отрицательной');
            }

            if ($arFields['MIN_ORDER_PRICE'] < 0) {
                throw new Exception('Минимальная сумма заказа не может быть отрицательной');
            }

            $zoneId = (int)($_POST['ID'] ?? 0);
            $connection = Application::getConnection();
            $connection->startTransaction();

            try {
                if ($zoneId > 0) {
                    // Проверяем существование зоны и принадлежность к текущему сайту
                    $exists = DeliveryZoneTable::getList([
                        'filter' => [
                            '=ID' => $zoneId,
                            '=SITE_ID' => $currentSiteId
                        ]
                    ])->fetch();

                    if (!$exists) {
                        throw new Exception('Зона с указанным ID не найдена или принадлежит другому сайту');
                    }

                    $result = DeliveryZoneTable::update($zoneId, $arFields);
                } else {
                    $result = DeliveryZoneTable::add($arFields);
                }

                if (!$result->isSuccess()) {
                    throw new Exception(implode(', ', $result->getErrorMessages()));
                }

                $connection->commitTransaction();

                $response = [
                    'success' => true,
                    'id' => $zoneId > 0 ? $zoneId : $result->getId(),
                    'message' => $zoneId > 0 ? 'Зона обновлена' : 'Зона создана'
                ];

            } catch (Exception $e) {
                $connection->rollbackTransaction();
                throw $e;
            }
        }

        if ($ajaxAction === 'delete_zone' && $permission >= "W") {
            if (!check_bitrix_sessid()) {
                throw new Exception('Ошибка сессии безопасности');
            }

            $zoneId = (int)($_POST['ID'] ?? 0);
            if ($zoneId <= 0) {
                throw new Exception('Неверный ID зоны');
            }

            // Проверяем принадлежность зоны к текущему сайту
            $exists = DeliveryZoneTable::getList([
                'filter' => [
                    '=ID' => $zoneId,
                    '=SITE_ID' => $currentSiteId
                ]
            ])->fetch();

            if (!$exists) {
                throw new Exception('Зона не найдена или принадлежит другому сайту');
            }

            $result = DeliveryZoneTable::delete($zoneId);
            if (!$result->isSuccess()) {
                throw new Exception(implode(', ', $result->getErrorMessages()));
            }

            $response = ['success' => true, 'message' => 'Зона удалена'];
        }

        if ($ajaxAction === 'get_zones') {
            try {
                // Получаем зоны только для текущего сайта
                $zones = DeliveryZoneTable::getList([
                    'filter' => [
                        '=SITE_ID' => $currentSiteId
                    ],
                    'order' => ['SORT' => 'ASC', 'ID' => 'ASC']
                ])->fetchAll();

                foreach ($zones as &$zone) {
                    if (isset($zone['COORDINATES'])) {
                        if (is_string($zone['COORDINATES'])) {
                            $zone['COORDINATES'] = json_decode($zone['COORDINATES'], true);
                        }
                        if (!is_array($zone['COORDINATES'])) {
                            $zone['COORDINATES'] = [];
                        }
                        if (!validateCoordinates($zone['COORDINATES'])) {
                            $zone['COORDINATES'] = [];
                        }
                    } else {
                        $zone['COORDINATES'] = [];
                    }

                    $zone['FREE_FROM'] = $zone['FREE_DELIVERY_PRICE'] ?? 0;
                    $zone['DELIVERY_TIME'] = $zone['DELIVERY_TIME'] ?? 0;

                    $zone['ID'] = (int)$zone['ID'];
                    $zone['PRICE'] = (float)$zone['PRICE'];
                    $zone['FREE_DELIVERY_PRICE'] = (float)$zone['FREE_DELIVERY_PRICE'];
                    $zone['MIN_ORDER_PRICE'] = (float)$zone['MIN_ORDER_PRICE'];
                    $zone['SORT'] = (int)$zone['SORT'];
                }
                unset($zone);

                $response = [
                    'success' => true,
                    'zones' => $zones,
                    'total' => count($zones),
                    'site_id' => $currentSiteId
                ];

            } catch (Exception $e) {
                throw new Exception('Ошибка загрузки зон: ' . $e->getMessage());
            }
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        die();

    } catch (Exception $e) {
        if (defined('BX_DEBUG') && BX_DEBUG) {
            AddMessage2Log('DeliveryMap Error: ' . $e->getMessage(), 'ldo.deliverymap');
        }

        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        die();
    }
}

// === ОБЫЧНАЯ ОБРАБОТКА (НЕ AJAX) ===
$ID = (int)($_REQUEST['ID'] ?? 0);
$action = $_REQUEST['action'] ?? '';

if ($action === 'delete' && $ID > 0 && $permission >= "W" && check_bitrix_sessid()) {
    try {
        // Проверяем принадлежность зоны к текущему сайту
        $exists = DeliveryZoneTable::getList([
            'filter' => [
                '=ID' => $ID,
                '=SITE_ID' => $currentSiteId
            ]
        ])->fetch();

        if ($exists) {
            DeliveryZoneTable::delete($ID);
        }

        LocalRedirect("/bitrix/admin/ldo_deliverymap/zones.php?lang=" . LANG);
    } catch (Exception $e) {
        ShowError('Ошибка удаления: ' . $e->getMessage());
    }
}

$APPLICATION->SetTitle("Зоны доставки");
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
?>
    <link rel="stylesheet" href="/bitrix/admin/ldo_deliverymap/zones.css">

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="delivery-map-container">
        <div class="delivery-map-wrapper">
            <div class="delivery-map" id="delivery-map"></div>

            <!-- Кнопки под картой -->
            <div class="map-controls">
                <button class="adm-btn adm-btn-save" id="addZoneBtn">➕ Добавить зону</button>
                <button type="submit" id="save_btn" class="adm-btn adm-btn-save" style="display:none;">💾 Сохранить</button>
                <button type="button" id="cancel_btn" class="adm-btn" style="display:none;">✖ Отмена</button>
            </div>
        </div>

        <div class="delivery-sidebar">
            <h3>Зоны доставки <span style="font-size:12px;color:#888;font-weight:normal;">(сайт: <?= htmlspecialchars($currentSiteId) ?>)</span></h3>

            <div class="zone-form-wrapper" id="zoneFormWrapper">
                <h4 id="formTitle">Новая зона</h4>
                <form id="zoneForm">
                    <input type="hidden" id="zone_id" name="ID" value="0">
                    <input type="hidden" id="zone_site_id" name="SITE_ID" value="<?= htmlspecialchars($currentSiteId) ?>">
                    <?= bitrix_sessid_post() ?>

                    <div class="zone-form-group">
                        <label>Название зоны *</label>
                        <input type="text" id="zone_name" name="NAME" required placeholder="Например: Центр города">
                    </div>

                    <div class="zone-form-group">
                        <label>Цена доставки (руб)</label>
                        <input type="number" id="zone_price" name="PRICE" value="0" step="0.01" min="0">
                        <span class="hint">Стоимость доставки в эту зону. 0 - бесплатно</span>
                    </div>

                    <div class="zone-form-group">
                        <label>Бесплатная доставка от (руб)</label>
                        <input type="number" id="zone_free_from" name="FREE_FROM" value="0" step="0.01" min="0">
                        <span class="hint">При сумме заказа от этой суммы доставка бесплатна</span>
                    </div>

                    <div class="zone-form-group">
                        <label>Время доставки (минут)</label>
                        <input type="number" id="zone_delivery_time" name="DELIVERY_TIME" value="0" min="0" step="5">
                        <span class="hint">Примерное время доставки в минутах</span>
                    </div>

                    <div class="zone-form-group">
                        <label>Цвет зоны</label>
                        <input type="color" id="zone_color" name="COLOR" value="#00FF00">
                    </div>

                    <div class="zone-form-group">
                        <label>Минимальная сумма заказа (руб)</label>
                        <input type="number" id="zone_min_price" name="MIN_ORDER_PRICE" value="0" step="0.01" min="0">
                        <span class="hint">Заказ на меньшую сумму не будет доставляться в эту зону</span>
                    </div>

                    <div class="zone-form-group">
                        <label>Активность</label>
                        <select id="zone_active" name="ACTIVE">
                            <option value="Y">Активна</option>
                            <option value="N">Неактивна</option>
                        </select>
                    </div>

                    <input type="hidden" id="zone_coordinates" name="COORDINATES">
                </form>
            </div>

            <div id="zone-list" style="display: none;">
                <div id="zone-list-items"></div>
            </div>
        </div>
    </div>

    <div id="draw-hint" style="display:none; position:fixed; bottom:80px; left:50%; transform:translateX(-50%); background:#333; color:#fff; padding:10px 20px; border-radius:5px; z-index:1000; text-align:center;">
        ⚡ Режим рисования: кликайте на карте для добавления точек
        <br><small>Добавлено точек: <span id="pointsCount">0</span></small>
    </div>

    <div class="edit-mode-hint" id="editModeHint">
        ✏️ Режим редактирования: перетаскивайте точки зоны мышью
        <small>Изменения сохранятся автоматически</small>
    </div>

    <div id="map-data"
         data-api-key="<?= htmlspecialchars(Option::get($moduleId, 'yandex_api_key')) ?>"
         data-default-lat="<?= htmlspecialchars(Option::get($moduleId, 'default_lat', '55.751574')) ?>"
         data-default-lng="<?= htmlspecialchars(Option::get($moduleId, 'default_lng', '37.573856')) ?>"
         data-default-zoom="<?= htmlspecialchars(Option::get($moduleId, 'default_zoom', '10')) ?>"
         data-site-id="<?= htmlspecialchars($currentSiteId) ?>">
    </div>

    <script src="https://api-maps.yandex.ru/2.1/?apikey=<?= htmlspecialchars(Option::get($moduleId, 'yandex_api_key')) ?>&lang=ru_RU"></script>
    <script src="/bitrix/admin/ldo_deliverymap/zones.js"></script>

<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
?>