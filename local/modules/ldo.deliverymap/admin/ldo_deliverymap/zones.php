<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Ldo\Deliverymap\DeliveryZoneTable;

$moduleId = 'ldo.deliverymap';

if (!Loader::includeModule($moduleId)) {
    ShowError("Не удалось загрузить модуль {$moduleId}");
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
    die();
}

$permission = $APPLICATION->GetGroupRight($moduleId);
if ($permission < "R") {
    $APPLICATION->AuthForm("Доступ запрещен");
}

// Обработка AJAX запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');

    $ajaxAction = $_POST['ajax_action'] ?? '';

    if ($ajaxAction === 'save_zone' && $permission >= "U") {
        // Проверка сессии
        if (!check_bitrix_sessid()) {
            echo json_encode(['success' => false, 'error' => 'Ошибка сессии']);
            die();
        }

        // Декодируем координаты если пришли в JSON
        $coordinates = $_POST['COORDINATES'] ?? '';
        if (is_string($coordinates) && !empty($coordinates)) {
            // Проверяем, не двойное ли кодирование
            $decoded = json_decode($coordinates, true);
            if (is_array($decoded)) {
                $coordinates = $decoded;
            }
        }

        $arFields = [
            'NAME' => trim($_POST['NAME'] ?? ''),
            'PRICE' => (int)($_POST['PRICE'] ?? 0),
            'COLOR' => $_POST['COLOR'] ?? '#00FF00',
            'SORT' => (int)($_POST['SORT'] ?? 500),
            'MIN_ORDER_PRICE' => (int)($_POST['MIN_ORDER_PRICE'] ?? 0),
            'ACTIVE' => ($_POST['ACTIVE'] ?? 'Y') === 'Y' ? 'Y' : 'N',
            'COORDINATES' => $coordinates
        ];

        // Валидация
        if (empty($arFields['NAME'])) {
            echo json_encode(['success' => false, 'error' => 'Введите название зоны']);
            die();
        }

        if (empty($arFields['COORDINATES']) || !is_array($arFields['COORDINATES']) || count($arFields['COORDINATES']) < 3) {
            echo json_encode(['success' => false, 'error' => 'Нарисуйте зону на карте (минимум 3 точки)']);
            die();
        }

        $zoneId = (int)($_POST['ID'] ?? 0);

        if ($zoneId > 0) {
            $result = DeliveryZoneTable::update($zoneId, $arFields);
            if ($result->isSuccess()) {
                echo json_encode(['success' => true, 'id' => $zoneId]);
            } else {
                echo json_encode(['success' => false, 'error' => implode(', ', $result->getErrorMessages())]);
            }
        } else {
            $result = DeliveryZoneTable::add($arFields);
            if ($result->isSuccess()) {
                echo json_encode(['success' => true, 'id' => $result->getId()]);
            } else {
                echo json_encode(['success' => false, 'error' => implode(', ', $result->getErrorMessages())]);
            }
        }
        die();
    }

    if ($ajaxAction === 'delete_zone' && $permission >= "W") {
        if (!check_bitrix_sessid()) {
            echo json_encode(['success' => false, 'error' => 'Ошибка сессии']);
            die();
        }

        $zoneId = (int)($_POST['ID'] ?? 0);
        DeliveryZoneTable::delete($zoneId);
        echo json_encode(['success' => true]);
        die();
    }

    if ($ajaxAction === 'get_zones') {
        $zones = DeliveryZoneTable::getList([
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC']
        ])->fetchAll();

        foreach ($zones as &$zone) {
            $zone['COORDINATES'] = unserialize($zone['COORDINATES']);
        }

        echo json_encode(['success' => true, 'zones' => $zones]);
        die();
    }
}

// Обычная обработка удаления (без AJAX, для совместимости)
$ID = (int)($_REQUEST['ID'] ?? 0);
$action = $_REQUEST['action'] ?? '';

if ($action === 'delete' && $ID > 0 && $permission >= "W" && check_bitrix_sessid()) {
    DeliveryZoneTable::delete($ID);
    LocalRedirect("/bitrix/admin/ldo_deliverymap/zones.php?lang=" . LANG);
}

$APPLICATION->SetTitle("Зоны доставки");
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
?>

    <style>
        .delivery-map-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }

        .delivery-map {
            flex: 3;
            height: 600px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .delivery-sidebar {
            flex: 1;
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            padding: 15px;
        }

        .delivery-sidebar h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .zone-form-group {
            margin-bottom: 15px;
        }

        .zone-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .zone-form-group input,
        .zone-form-group select {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 3px;
            box-sizing: border-box;
        }

        .zone-form-group input[type="color"] {
            height: 35px;
        }

        .form-actions {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }

        .zone-info {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .zone-info p {
            margin: 5px 0;
        }

        .zone-color-preview {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 3px;
            vertical-align: middle;
            margin-right: 8px;
        }

        .drawing-mode-active {
            background: #e6a017 !important;
            color: #fff !important;
        }

        .button-danger {
            background: #e74c3c !important;
            border-color: #c0392b !important;
            color: #fff !important;
        }

        .button-danger:hover {
            background: #c0392b !important;
        }
    </style>

    <div class="delivery-map-container">
        <div class="delivery-map" id="delivery-map"></div>

        <div class="delivery-sidebar">
            <h3 id="sidebar-title">Добавление зоны</h3>

            <form id="zone-form">
                <input type="hidden" id="zone_id" name="ID" value="0">
                <?= bitrix_sessid_post() ?>

                <div class="zone-form-group">
                    <label>Название зоны *</label>
                    <input type="text" id="zone_name" name="NAME" required>
                </div>

                <div class="zone-form-group">
                    <label>Цена доставки (руб)</label>
                    <input type="number" id="zone_price" name="PRICE" value="0">
                </div>

                <div class="zone-form-group">
                    <label>Цвет зоны</label>
                    <input type="color" id="zone_color" name="COLOR" value="#00FF00">
                </div>

                <div class="zone-form-group">
                    <label>Минимальная сумма заказа (руб)</label>
                    <input type="number" id="zone_min_price" name="MIN_ORDER_PRICE" value="0">
                </div>

                <div class="zone-form-group">
                    <label>Сортировка</label>
                    <input type="number" id="zone_sort" name="SORT" value="500">
                </div>

                <div class="zone-form-group">
                    <label>Активность</label>
                    <select id="zone_active" name="ACTIVE">
                        <option value="Y">Активна</option>
                        <option value="N">Неактивна</option>
                    </select>
                </div>

                <input type="hidden" id="zone_coordinates" name="COORDINATES">

                <div class="form-actions">
                    <button type="button" id="draw_btn" class="adm-btn">✏️ Нарисовать зону</button>
                    <button type="submit" id="save_btn" class="adm-btn adm-btn-save">💾 Сохранить</button>
                    <button type="button" id="cancel_btn" class="adm-btn" style="display:none;">Отмена</button>
                </div>
            </form>

            <div id="zone-info-panel" style="margin-top: 20px; display: none;">
                <h4>Информация о зоне</h4>
                <div id="zone-info-content"></div>
                <div style="margin-top: 10px; display: flex; gap: 10px;">
                    <button id="edit_zone_btn" class="adm-btn" style="flex:1;">✏️ Редактировать</button>
                    <button id="delete_zone_btn" class="adm-btn button-danger" style="flex:1;">🗑️ Удалить</button>
                </div>
            </div>
        </div>
    </div>

    <div id="draw-hint" style="display:none; position:fixed; bottom:20px; left:50%; transform:translateX(-50%); background:#333; color:#fff; padding:10px 20px; border-radius:5px; z-index:1000;">
        ⚡ Режим рисования: кликайте на карте для добавления точек. Двойной клик для завершения.
    </div>

    <script src="https://api-maps.yandex.ru/2.1/?apikey=<?=Option::get($moduleId, 'yandex_api_key')?>&lang=ru_RU"></script>
    <script>
        let map;
        let polygons = [];
        let drawingMode = false;
        let tempPoints = [];
        let tempPolygon = null;
        let tempPlacemarks = [];
        let selectedPolygon = null;
        let currentZoneData = null;

        const defaultLat = parseFloat('<?=Option::get($moduleId, 'default_lat', '55.751574')?>');
        const defaultLng = parseFloat('<?=Option::get($moduleId, 'default_lng', '37.573856')?>');
        const defaultZoom = parseInt('<?=Option::get($moduleId, 'default_zoom', '10')?>');

        function initMap() {
            if (typeof ymaps === 'undefined') {
                setTimeout(initMap, 500);
                return;
            }

            ymaps.ready(function() {
                map = new ymaps.Map('delivery-map', {
                    center: [defaultLat, defaultLng],
                    zoom: defaultZoom,
                    controls: ['zoomControl', 'fullscreenControl', 'geolocationControl']
                });

                loadZones();

                // Обработчики формы
                document.getElementById('zone-form').addEventListener('submit', saveZone);
                document.getElementById('draw_btn').addEventListener('click', toggleDrawing);
                document.getElementById('cancel_btn').addEventListener('click', cancelDrawing);
                document.getElementById('edit_zone_btn').addEventListener('click', editSelectedZone);
                document.getElementById('delete_zone_btn').addEventListener('click', deleteSelectedZone);
            });
        }

        function loadZones() {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'ajax_action=get_zones'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.zones) {
                        data.zones.forEach(zone => {
                            addPolygonToMap(zone);
                        });
                    }
                })
                .catch(error => console.error('Ошибка загрузки зон:', error));
        }

        function addPolygonToMap(zone) {
            if (!zone.COORDINATES || zone.COORDINATES.length < 3) return;

            const polygon = new ymaps.Polygon([zone.COORDINATES], {
                hintContent: zone.NAME,
                zoneData: zone
            }, {
                fillColor: zone.COLOR + '80',
                strokeColor: zone.COLOR,
                strokeWidth: 3,
                fillOpacity: 0.5,
                strokeStyle: 'solid'
            });

            polygon.events.add('click', function(e) {
                e.stopPropagation();
                currentZoneData = zone;
                showZoneInfo(zone);
                selectPolygon(polygon);
            });

            polygon.events.add('mouseenter', function() {
                polygon.options.set({
                    fillOpacity: 0.7,
                    strokeWidth: 4
                });
            });

            polygon.events.add('mouseleave', function() {
                if (selectedPolygon !== polygon) {
                    polygon.options.set({
                        fillOpacity: 0.5,
                        strokeWidth: 3
                    });
                }
            });

            map.geoObjects.add(polygon);
            polygons.push({ polygon, zone });
        }

        function selectPolygon(polygon) {
            if (selectedPolygon) {
                selectedPolygon.options.set({ fillOpacity: 0.5, strokeWidth: 3 });
            }
            selectedPolygon = polygon;
            selectedPolygon.options.set({ fillOpacity: 0.8, strokeWidth: 5 });
        }

        function showZoneInfo(zone) {
            const panel = document.getElementById('zone-info-panel');
            const content = document.getElementById('zone-info-content');

            content.innerHTML = `
            <div class="zone-info">
                <p><strong>${escapeHtml(zone.NAME)}</strong></p>
                <p>💰 Цена: ${zone.PRICE} руб.</p>
                <p>🛒 Мин. заказ: ${zone.MIN_ORDER_PRICE > 0 ? zone.MIN_ORDER_PRICE + ' руб.' : 'нет'}</p>
                <p>🔢 Сортировка: ${zone.SORT}</p>
                <p>${zone.ACTIVE === 'Y' ? '✅ Активна' : '⛔ Неактивна'}</p>
                <p><span class="zone-color-preview" style="background:${zone.COLOR};"></span>Цвет: ${zone.COLOR}</p>
            </div>
        `;

            panel.style.display = 'block';
            content.dataset.zoneId = zone.ID;
        }

        function editSelectedZone() {
            if (!currentZoneData) return;

            document.getElementById('zone_id').value = currentZoneData.ID;
            document.getElementById('zone_name').value = currentZoneData.NAME;
            document.getElementById('zone_price').value = currentZoneData.PRICE;
            document.getElementById('zone_color').value = currentZoneData.COLOR;
            document.getElementById('zone_min_price').value = currentZoneData.MIN_ORDER_PRICE;
            document.getElementById('zone_sort').value = currentZoneData.SORT;
            document.getElementById('zone_active').value = currentZoneData.ACTIVE;
            document.getElementById('zone_coordinates').value = JSON.stringify(currentZoneData.COORDINATES);
            document.getElementById('sidebar-title').textContent = 'Редактирование зоны';
            document.getElementById('cancel_btn').style.display = 'inline-block';
            document.getElementById('zone-info-panel').style.display = 'none';

            if (selectedPolygon) {
                map.setBounds(selectedPolygon.geometry.getBounds());
            }
        }

        function deleteSelectedZone() {
            if (!currentZoneData) return;

            if (confirm(`Удалить зону "${currentZoneData.NAME}"?`)) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `ajax_action=delete_zone&ID=${currentZoneData.ID}&<?=bitrix_sessid_get()?>`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Ошибка удаления: ' + (data.error || 'Неизвестная ошибка'));
                        }
                    })
                    .catch(error => alert('Ошибка: ' + error));
            }
        }

        function saveZone(e) {
            e.preventDefault();

            const coords = document.getElementById('zone_coordinates').value;
            if (!coords) {
                alert('Сначала нарисуйте зону на карте!');
                return;
            }

            const formData = new FormData(document.getElementById('zone-form'));
            formData.append('ajax_action', 'save_zone');

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Ошибка сохранения: ' + (data.error || 'Неизвестная ошибка'));
                    }
                })
                .catch(error => alert('Ошибка: ' + error));
        }

        function toggleDrawing() {
            if (drawingMode) {
                finishDrawing();
            } else {
                startDrawing();
            }
        }

        function startDrawing() {
            drawingMode = true;
            const btn = document.getElementById('draw_btn');
            btn.textContent = '✅ Завершить рисование';
            btn.classList.add('drawing-mode-active');
            document.getElementById('draw-hint').style.display = 'block';
            document.getElementById('sidebar-title').textContent = 'Рисование новой зоны';
            document.getElementById('cancel_btn').style.display = 'inline-block';
            document.getElementById('zone-info-panel').style.display = 'none';

            // Очищаем форму
            document.getElementById('zone_id').value = 0;
            document.getElementById('zone_name').value = '';
            document.getElementById('zone_price').value = 0;
            document.getElementById('zone_min_price').value = 0;
            document.getElementById('zone_sort').value = 500;
            document.getElementById('zone_active').value = 'Y';
            document.getElementById('zone_coordinates').value = '';

            tempPoints = [];

            // Создаем временный полигон
            const color = document.getElementById('zone_color').value;
            tempPolygon = new ymaps.Polygon([[]], {
                hintContent: 'Рисование... кликните для добавления точек'
            }, {
                fillColor: color + '80',
                strokeColor: color,
                strokeWidth: 2,
                fillOpacity: 0.3
            });
            map.geoObjects.add(tempPolygon);

            map.events.add('click', onMapClick);
            map.events.add('dblclick', finishDrawing);
        }

        function onMapClick(e) {
            if (!drawingMode) return;

            const coords = e.get('coords');
            tempPoints.push(coords);

            // Добавляем метку
            const placemark = new ymaps.Placemark(coords, {}, {
                preset: 'islands#redDotIcon',
                draggable: false
            });
            map.geoObjects.add(placemark);
            tempPlacemarks.push(placemark);

            // Обновляем полигон
            if (tempPoints.length === 1) {
                tempPolygon.geometry.setCoordinates([tempPoints.concat([tempPoints[0]])]);
            } else {
                tempPolygon.geometry.setCoordinates([tempPoints.concat([tempPoints[0]])]);
            }
        }

        function finishDrawing() {
            if (!drawingMode) return;

            drawingMode = false;
            const btn = document.getElementById('draw_btn');
            btn.textContent = '✏️ Нарисовать зону';
            btn.classList.remove('drawing-mode-active');
            document.getElementById('draw-hint').style.display = 'none';

            map.events.remove('click', onMapClick);
            map.events.remove('dblclick', finishDrawing);

            // Удаляем временные метки
            tempPlacemarks.forEach(pm => map.geoObjects.remove(pm));
            tempPlacemarks = [];

            if (tempPoints.length >= 3) {
                // Замыкаем полигон
                const finalCoords = tempPoints.concat([tempPoints[0]]);
                document.getElementById('zone_coordinates').value = JSON.stringify(finalCoords);

                // Показываем финальный полигон
                if (tempPolygon) {
                    map.geoObjects.remove(tempPolygon);
                }

                const color = document.getElementById('zone_color').value;
                const previewPolygon = new ymaps.Polygon([finalCoords], {
                    hintContent: 'Новая зона'
                }, {
                    fillColor: color + '80',
                    strokeColor: color,
                    strokeWidth: 3,
                    fillOpacity: 0.5
                });
                map.geoObjects.add(previewPolygon);

                // Сохраняем его для отображения
                if (window.previewPolygon) {
                    map.geoObjects.remove(window.previewPolygon);
                }
                window.previewPolygon = previewPolygon;
                map.setBounds(previewPolygon.geometry.getBounds());
            } else {
                if (tempPolygon) map.geoObjects.remove(tempPolygon);
                alert('Нужно минимум 3 точки для создания зоны');
            }

            tempPoints = [];
            tempPolygon = null;
        }

        function cancelDrawing() {
            if (window.previewPolygon) {
                map.geoObjects.remove(window.previewPolygon);
                window.previewPolygon = null;
            }
            if (tempPolygon) {
                map.geoObjects.remove(tempPolygon);
                tempPolygon = null;
            }

            drawingMode = false;

            document.getElementById('zone-form').reset();
            document.getElementById('zone_id').value = 0;
            document.getElementById('zone_coordinates').value = '';
            document.getElementById('sidebar-title').textContent = 'Добавление зоны';
            document.getElementById('cancel_btn').style.display = 'none';
            document.getElementById('draw_btn').textContent = '✏️ Нарисовать зону';
            document.getElementById('draw_btn').classList.remove('drawing-mode-active');
            document.getElementById('draw-hint').style.display = 'none';
            document.getElementById('zone-info-panel').style.display = 'none';

            map.events.remove('click', onMapClick);
            map.events.remove('dblclick', finishDrawing);

            if (selectedPolygon) {
                selectedPolygon.options.set({ fillOpacity: 0.5, strokeWidth: 3 });
                selectedPolygon = null;
            }

            currentZoneData = null;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        document.addEventListener('DOMContentLoaded', initMap);
    </script>

<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
?>