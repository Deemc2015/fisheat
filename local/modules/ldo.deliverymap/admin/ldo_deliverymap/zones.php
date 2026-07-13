<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Ldo\Deliverymap\DeliveryZoneTable;
use Ldo\Deliverymap\SettingsTable;

$moduleId = 'ldo.deliverymap';

if (!Loader::includeModule($moduleId)) {
    ShowError("Не удалось загрузить модуль {$moduleId}");
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
    die();
}

$permission = $APPLICATION->GetGroupRight($moduleId);
if ($permission < "R") {
    ShowError("Доступ запрещен");
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
    die();
}

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
$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax_action']))
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
if ($isAjax) {
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

        if ($ajaxAction === 'save_zone') {
            if ($permission < "U") {
                throw new Exception('Недостаточно прав');
            }

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
                'DELIVERY_TIME_START' => (int)($_POST['DELIVERY_TIME_START'] ?? 0),
                'DELIVERY_TIME_END' => (int)($_POST['DELIVERY_TIME_END'] ?? 0),
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
        } elseif ($ajaxAction === 'delete_zone') {
            if ($permission < "W") {
                throw new Exception('Недостаточно прав');
            }

            if (!check_bitrix_sessid()) {
                throw new Exception('Ошибка сессии безопасности');
            }

            $zoneId = (int)($_POST['ID'] ?? 0);
            if ($zoneId <= 0) {
                throw new Exception('Неверный ID зоны');
            }

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
        } elseif ($ajaxAction === 'save_settings') {
            if ($permission < "U") {
                throw new Exception('Недостаточно прав');
            }
            if (!check_bitrix_sessid()) {
                throw new Exception('Ошибка сессии безопасности');
            }

            $siteId = $_POST['SITE_ID'] ?? $currentSiteId;

            // Сохраняем только те поля, которые реально переданы
            $knownFields = [
                'yandex_api_key', 'default_lat', 'default_lng', 'default_zoom',
                'high_load_enabled', 'high_load_add_time'
            ];

            foreach ($knownFields as $name) {
                if (array_key_exists($name, $_POST)) {
                    $value = $_POST[$name];
                    // Нормализация известных полей
                    if ($name === 'high_load_enabled') {
                        $value = ($value === 'Y') ? 'Y' : 'N';
                    } elseif (in_array($name, ['high_load_add_time', 'default_zoom'])) {
                        $value = (string)((int)$value);
                    }
                    SettingsTable::set($siteId, $name, $value);
                }
            }

            $response = ['success' => true, 'message' => 'Настройки сохранены'];

        } elseif ($ajaxAction === 'get_zones') {
            try {
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
                    $zone['DELIVERY_TIME_START'] = (int)($zone['DELIVERY_TIME_START'] ?? 0);
                    $zone['DELIVERY_TIME_END'] = (int)($zone['DELIVERY_TIME_END'] ?? 0);

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
                    'site_id' => $currentSiteId,
                    'high_load_enabled' => $highLoadEnabled,
                    'high_load_add_time' => (int)$highLoadAddTime
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
        $exists = DeliveryZoneTable::getList([
            'filter' => [
                '=ID' => $ID,
                '=SITE_ID' => $currentSiteId
            ]
        ])->fetch();

        if ($exists) {
            DeliveryZoneTable::delete($ID);
        }

        LocalRedirect("/bitrix/admin/ldo_deliverymap_zones.php?lang=" . LANG);
    } catch (Exception $e) {
        ShowError('Ошибка удаления: ' . $e->getMessage());
    }
}

$APPLICATION->SetTitle("Зоны доставки");
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");

\Bitrix\Main\UI\Extension::load("ui.buttons.icons");
\Bitrix\Main\UI\Extension::load("ui.buttons");

// Получаем настройки для текущего сайта
$settings = [];
$settingsRows = SettingsTable::getList(['filter' => ['=SITE_ID' => $currentSiteId]])->fetchAll();
foreach ($settingsRows as $row) {
    $settings[$row['NAME']] = $row['VALUE'];
}
$yandexApiKey = $settings['yandex_api_key'] ?? '';
$defaultLat = $settings['default_lat'] ?? '55.751574';
$defaultLng = $settings['default_lng'] ?? '37.573856';
$defaultZoom = $settings['default_zoom'] ?? '10';
$highLoadEnabled = $settings['high_load_enabled'] ?? 'N';
$highLoadAddTime = $settings['high_load_add_time'] ?? '0';
?>
    <link rel="stylesheet" href="/bitrix/admin/ldo_deliverymap_zones.css">

    <!-- Табы -->
    <div class="admin-tabs">
        <button class="tab-btn active" data-tab="zones">Зоны доставки</button>
        <button class="tab-btn" data-tab="restaurants">Рестораны</button>
        <button class="tab-btn" data-tab="settings">Настройки</button>
    </div>

    <!-- ===== ВКЛАДКА: ЗОНЫ ДОСТАВКИ ===== -->
    <div class="tab-content active" id="tab-zones">
        <div class="top-line-map">
            <!-- Блок высокой нагрузки -->
            <div class="high-load-container">
                <label>
                    <input type="checkbox" id="highLoadToggle" <?= $highLoadEnabled === 'Y' ? 'checked' : '' ?>>
                    <span>⚠️ Высокая нагрузка</span>
                </label>

                <div id="highLoadSettings" class="<?= $highLoadEnabled === 'Y' ? 'is-visible' : '' ?>">
                    <label>Доп. время доставки (минут):</label>
                    <input type="number" id="highLoadMinutes" value="<?= (int)$highLoadAddTime ?>" min="1" max="1440" step="5">
                    <button id="saveHighLoadBtn" class="ui-btn ui-btn-success ui-btn-sm">Сохранить</button>
                    <span id="highLoadStatus" class="status-hidden">✓ Сохранено</span>
                </div>
            </div>
            <div class="load-file">Загрузить из файла</div>
        </div>

        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-spinner"></div>
        </div>
        <div class="delivery-map-container">
            <div class="delivery-map-wrapper">
                <div class="delivery-map" id="delivery-map"></div>
                <div class="map-controls">
                    <button class="ui-btn ui-btn-success ui-btn-icon-add" id="addZoneBtn">Добавить зону</button>
                    <button type="submit" id="save_btn" class="ui-btn ui-btn-success ui-btn-icon-done" style="display:none;">Сохранить</button>
                    <button type="button" id="cancel_btn" class="ui-btn ui-btn-danger" style="display:none;">Отмена</button>
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
                            <input type="text" id="zone_name" name="NAME" required placeholder="Например: Центр">
                        </div>
                        <div class="zone-form-group">
                            <label>Цена доставки (руб)</label>
                            <input type="number" id="zone_price" name="PRICE" value="0" step="0.01" min="0">
                            <span class="hint">0 - бесплатно</span>
                        </div>
                        <div class="zone-form-group">
                            <label>Бесплатная доставка от (руб)</label>
                            <input type="number" id="zone_free_from" name="FREE_FROM" value="0" step="0.01" min="0">
                        </div>
                        <div class="zone-form-group">
                            <label>Время доставки (минут)</label>
                            <div class="delivery-time-range">
                                <div class="time-field">
                                    <span class="time-label">от</span>
                                    <input type="number" id="zone_delivery_time_start" name="DELIVERY_TIME_START" value="" min="0" step="5" placeholder="мин">
                                </div>
                                <div class="time-field">
                                    <span class="time-label">до</span>
                                    <input type="number" id="zone_delivery_time_end" name="DELIVERY_TIME_END" value="" min="0" step="5" placeholder="мин">
                                </div>
                            </div>
                        </div>
                        <div class="zone-form-group">
                            <label>Цвет зоны</label>
                            <input type="color" id="zone_color" name="COLOR" value="#00FF00">
                        </div>
                        <div class="zone-form-group">
                            <label>Минимальная сумма заказа (руб)</label>
                            <input type="number" id="zone_min_price" name="MIN_ORDER_PRICE" value="0" step="0.01" min="0">
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
    </div>

    <!-- ===== ВКЛАДКА: РЕСТОРАНЫ ===== -->
    <div class="tab-content" id="tab-restaurants" style="display:none;">
        <div class="restaurants-container">
            <div class="restaurants-map" id="restaurants-map" style="width:100%; height:600px;"></div>
        </div>
    </div>

    <!-- ===== ВКЛАДКА: НАСТРОЙКИ ===== -->
    <div class="tab-content" id="tab-settings" style="display:none;">
        <div class="settings-container">
            <div class="settings-section">
                <h3>Настройки карты (сайт: <?= htmlspecialchars($currentSiteId) ?>)</h3>
                <form id="settingsForm" method="post">
                    <input type="hidden" name="site_id" value="<?= htmlspecialchars($currentSiteId) ?>">
                    <?= bitrix_sessid_post() ?>

                    <div class="settings-group">
                        <label>API ключ Яндекс.Карт:</label>
                        <input type="text" id="settings_api_key" name="yandex_api_key"
                               value="<?= htmlspecialchars($yandexApiKey) ?>" size="60">
                        <span class="hint">Получить в <a href="https://developer.tech.yandex.ru/" target="_blank">кабинете разработчика</a></span>
                    </div>

                    <div class="settings-group">
                        <label>Центр карты по умолчанию:</label>
                        <div class="settings-coords">
                            <span>Широта:</span>
                            <input type="text" id="settings_lat" name="default_lat" value="<?= htmlspecialchars($defaultLat) ?>" size="12">
                            <span>Долгота:</span>
                            <input type="text" id="settings_lng" name="default_lng" value="<?= htmlspecialchars($defaultLng) ?>" size="12">
                            <span>Зум:</span>
                            <input type="number" id="settings_zoom" name="default_zoom" value="<?= (int)$defaultZoom ?>" min="1" max="19" size="4">
                        </div>
                        <span class="hint">Переместите карту ниже в нужное место и нажмите "Взять центр с карты"</span>
                    </div>

                    <button type="submit" class="adm-btn-save" id="saveSettingsBtn">Сохранить настройки</button>
                    <span id="settingsStatus" class="status-hidden">✓ Сохранено</span>
                </form>
            </div>

            <div class="settings-section">
                <h4>Выбор центра карты</h4>
                <div id="settings-map" style="width:100%; height:450px;"></div>
                <br>
                <button type="button" class="adm-btn" id="getCenterBtn">📌 Взять центр с карты</button>
            </div>
        </div>
    </div>

    <div id="map-data"
         data-api-key="<?= htmlspecialchars($yandexApiKey) ?>"
         data-default-lat="<?= htmlspecialchars($defaultLat) ?>"
         data-default-lng="<?= htmlspecialchars($defaultLng) ?>"
         data-default-zoom="<?= htmlspecialchars($defaultZoom) ?>"
         data-site-id="<?= htmlspecialchars($currentSiteId) ?>">
    </div>

    <script src="https://api-maps.yandex.ru/2.1/?apikey=<?= htmlspecialchars($yandexApiKey) ?>&lang=ru_RU"></script>
    <script src="/bitrix/admin/ldo_deliverymap_zones.js"></script>

<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
?>