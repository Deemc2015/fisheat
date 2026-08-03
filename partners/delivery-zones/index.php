<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Ldo\Deliverymap\SettingsTable;
use Ldo\Deliverymap\RestaurantsTable;
use Ldo\Deliverymap\DeliveryZoneTable;

global $USER;

$APPLICATION->SetTitle("Управление доставкой");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Управление доставкой");

$request = Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

$siteId = 's1';
$moduleLoaded = Loader::includeModule('ldo.deliverymap');

// Читаем настройки из БД (редактирование — в /partners/settings/)
$yandexApiKey  = $moduleLoaded ? SettingsTable::get($siteId, 'yandex_api_key', '') : '';
$defaultLat    = $moduleLoaded ? SettingsTable::get($siteId, 'default_lat', '54.7355') : '54.7355';
$defaultLng    = $moduleLoaded ? SettingsTable::get($siteId, 'default_lng', '55.9587') : '55.9587';
$defaultZoom   = $moduleLoaded ? SettingsTable::get($siteId, 'default_zoom', '11') : '11';

// Загружаем рестораны для сайта
$restaurants = [];
if ($moduleLoaded) {
    $dbRestaurants = RestaurantsTable::getList([
        'filter' => ['=SITE_ID' => $siteId],
        'order' => ['ID' => 'ASC']
    ]);
    while ($r = $dbRestaurants->fetch()) {
        $restaurants[] = $r;
    }
}

// Загружаем зоны доставки для сайта
$deliveryZones = [];
$highLoadEnabled = 'N';
$highLoadAddTime = 0;
if ($moduleLoaded) {
    $dbZones = DeliveryZoneTable::getList([
        'filter' => ['=SITE_ID' => $siteId],
        'order' => ['SORT' => 'ASC', 'ID' => 'ASC']
    ]);
    while ($z = $dbZones->fetch()) {
        $z['COORDINATES'] = is_string($z['COORDINATES']) ? json_decode($z['COORDINATES'], true) : $z['COORDINATES'];
        $deliveryZones[] = $z;
    }
    $highLoadEnabled = SettingsTable::get($siteId, 'high_load_enabled', 'N');
    $highLoadAddTime = (int)SettingsTable::get($siteId, 'high_load_add_time', '0');
}

// --- AJAX обработка зон доставки ---
if ($request->isPost() && $request->getPost('ajax_zone') && $moduleLoaded) {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'error' => 'Неизвестное действие'];
    try {
        $action = $request->getPost('action');

        if ($action === 'save') {
            $id = (int)$request->getPost('ID');
            $coordinates = $request->getPost('COORDINATES');
            $coordinates = is_string($coordinates) ? json_decode($coordinates, true) : $coordinates;
            if (!is_array($coordinates) || count($coordinates) < 3) {
                $coordinates = [];
            }

            $data = [
                'NAME' => trim((string)$request->getPost('NAME')),
                'PRICE' => (int)$request->getPost('PRICE'),
                'FREE_DELIVERY_PRICE' => (int)$request->getPost('FREE_FROM'),
                'DELIVERY_TIME_START' => (int)$request->getPost('DELIVERY_TIME_START'),
                'DELIVERY_TIME_END' => (int)$request->getPost('DELIVERY_TIME_END'),
                'COLOR' => $request->getPost('COLOR') ?: '#00FF00',
                'SORT' => (int)$request->getPost('SORT') ?: 500,
                'MIN_ORDER_PRICE' => (int)$request->getPost('MIN_ORDER_PRICE'),
                'ACTIVE' => $request->getPost('ACTIVE') === 'Y' ? 'Y' : 'N',
                'SITE_ID' => $siteId,
                'COORDINATES' => $coordinates,
                'RESTAURANT_ID' => (int)$request->getPost('RESTAURANT_ID'),
            ];
            if (empty($data['NAME'])) throw new \Exception('Введите название зоны');

            if ($id > 0) {
                DeliveryZoneTable::update($id, $data);
                $response = ['success' => true, 'message' => 'Зона обновлена'];
            } else {
                $result = DeliveryZoneTable::add($data);
                $response = ['success' => true, 'id' => $result->getId(), 'message' => 'Зона создана'];
            }
        } elseif ($action === 'delete') {
            $id = (int)$request->getPost('ID');
            if ($id <= 0) throw new \Exception('Неверный ID');
            DeliveryZoneTable::delete($id);
            $response = ['success' => true, 'message' => 'Зона удалена'];
        } elseif ($action === 'import') {
                $kmlRaw = $request->getPost('kml_base64');
                if (empty($kmlRaw)) throw new \Exception('Нет данных KML');
                // Декодируем из base64 (обходим проактивный фильтр Битрикс)
                $kmlRaw = base64_decode($kmlRaw);
                if (!$kmlRaw) throw new \Exception('Ошибка декодирования данных');
                // Парсим KML вручную (через SimpleXML бывают проблемы с неймспейсами)
                // Удаляем текущие зоны для сайта
                $existingZones = DeliveryZoneTable::getList(['filter' => ['=SITE_ID' => $siteId]]);
                while ($existingZone = $existingZones->fetch()) {
                    DeliveryZoneTable::delete($existingZone['ID']);
                }
                $imported = 0;
                $colors = ['#FF0000', '#00FF00', '#0000FF', '#FFA500', '#800080', '#FFC0CB', '#00FFFF', '#FFFF00'];
                $colorIdx = 0;
                // Разбиваем на Placemark'и с Polygon (без Point)
                preg_match_all('/<Placemark[^>]*>(.*?)<\/Placemark>/is', $kmlRaw, $pmMatches);
                foreach ($pmMatches[1] as $pmXml) {
                    // Пропускаем Placemark без Polygon (точки)
                    if (strpos($pmXml, '<Polygon') === false) continue;
                    // Название
                    $name = '';
                    if (preg_match('/<name[^>]*><!\[CDATA\[(.*?)\]\]><\/name>/is', $pmXml, $m)) {
                        $name = trim($m[1]);
                    } elseif (preg_match('/<name[^>]*>(.*?)<\/name>/is', $pmXml, $m)) {
                        $name = trim(strip_tags($m[1]));
                    }
                    // Стоимость доставки из description
                    $price = 0;
                    if (preg_match('/Стоимость доставки\s*(\d+)/ui', $pmXml, $m)) {
                        $price = (int)$m[1];
                    }
                    if (empty($name)) {
                        $name = 'Зона доставки #' . ($imported + 1);
                    }
                    // Координаты из <coordinates>
                    if (preg_match('/<coordinates[^>]*>(.*?)<\/coordinates>/is', $pmXml, $m)) {
                        $coordText = trim($m[1]);
                        $points = preg_split('/\s+/', $coordText);
                        $coordinates = [];
                        foreach ($points as $pt) {
                            $pt = trim($pt);
                            if (empty($pt)) continue;
                            $parts = explode(',', $pt);
                            if (count($parts) >= 2) {
                                $coordinates[] = [(float)$parts[1], (float)$parts[0]]; // [lat, lng]
                            }
                        }
                        if (count($coordinates) >= 3) {
                            $data = [
                                'NAME' => $name,
                                'PRICE' => $price,
                                'FREE_DELIVERY_PRICE' => 0,
                                'DELIVERY_TIME_START' => 0,
                                'DELIVERY_TIME_END' => 0,
                                'COLOR' => $colors[$colorIdx % count($colors)],
                                'SORT' => ($imported + 1) * 100,
                                'MIN_ORDER_PRICE' => 0,
                                'ACTIVE' => 'Y',
                                'SITE_ID' => $siteId,
                                'COORDINATES' => $coordinates,
                                'RESTAURANT_ID' => 0,
                            ];
                            DeliveryZoneTable::add($data);
                            $imported++;
                            $colorIdx++;
                        }
                    }
                }
                $response = ['success' => true, 'message' => 'Импортировано зон: ' . $imported];
        } elseif ($action === 'save_high_load') {
            SettingsTable::set($siteId, 'high_load_enabled', $request->getPost('high_load_enabled') === 'Y' ? 'Y' : 'N');
            SettingsTable::set($siteId, 'high_load_add_time', (string)(int)$request->getPost('high_load_add_time'));
            $response = ['success' => true, 'message' => 'Сохранено'];
        }
    } catch (\Exception $e) {
        $response = ['success' => false, 'error' => $e->getMessage()];
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    die();
}

$partnersActivePage  = 'delivery-zones';
$partnersPageTitle   = 'Управление доставкой';
$partnersHeaderStyle = 'padding-bottom:0; border-bottom:none;';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
?>


        <div class="p-main">
            <!-- Вкладка: Зоны доставки -->
            <div class="tab-content active" id="tab-zones" style="display:block;">
                <div class="p-section" style="display:flex; gap:20px; flex-wrap:wrap;">
                    <div class="p-section__header" style="width:100%;">
                        <h2 class="p-section__title">Зоны доставки</h2>
                        <button class="p-btn p-btn--secondary" style="padding:8px 18px; font-size:14px;" onclick="showImportDialog()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="margin-right:4px;"><path d="M20 6H12L10 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V8C22 6.9 21.1 6 20 6ZM20 18H4V6H9.17L11.17 8H20V18Z" fill="currentColor"/></svg>Загрузить из файла</button>
                    </div>
                    <!-- Левая колонка: карта -->
                    <div style="flex:1; min-width:300px;">
                        <div class="zone-highload-bar <?= $highLoadEnabled === 'Y' ? 'active' : '' ?>" id="zone-highload-bar">
                            <label class="zone-highload-label">
                                <input type="checkbox" id="zone-high-load" <?= $highLoadEnabled === 'Y' ? 'checked' : '' ?> onchange="toggleHighLoad(this)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="flex-shrink:0;"><path d="M12 2L1 21H23L12 2ZM12 6L19.53 19H4.47L12 6ZM13 16H11V18H13V16ZM13 10H11V14H13V10Z" fill="currentColor"/></svg>
                                Высокая нагрузка
                            </label>
                            <div class="zone-highload-settings <?= $highLoadEnabled === 'Y' ? 'is-visible' : '' ?>" id="zone-high-load-settings">
                                <span>Доп. минут:</span>
                                <input type="number" id="zone-high-load-minutes" value="<?= $highLoadAddTime ?>" min="1" max="1440" step="5">
                                <button class="p-btn p-btn--primary" style="padding:6px 14px; font-size:13px;" onclick="saveHighLoad()">Сохранить</button>
                            </div>
                        </div>
                        <?php if (!empty($yandexApiKey)): ?>
                            <div id="zones-map" style="width:100%; height:400px; border-radius:12px; overflow:hidden;"></div>
                        <?php else: ?>
                            <div style="padding:40px; text-align:center; color:var(--color-muted); background:var(--bg-black); border-radius:12px;">
                                API-ключ Яндекс.Карт не настроен.
                            </div>
                        <?php endif; ?>
                        <div style="display:flex; align-items:center; gap:12px; margin-top:12px;">
                            <button class="p-btn p-btn--primary" id="btn-add-zone" onclick="startAddZone()">+ Добавить зону</button>
                            <button class="p-btn p-btn--outline" id="btn-cancel-add-zone" style="display:none;" onclick="cancelAddZone()">Отменить</button>
                            <span id="zone-add-hint" style="display:none; font-size:14px; color:var(--bg-button); align-items:center; gap:6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 2C8.13 2 5 5.13 5 9C5 14.25 12 22 12 22C12 22 19 14.25 19 9C19 5.13 15.87 2 12 2ZM12 11.5C10.62 11.5 9.5 10.38 9.5 9C9.5 7.62 10.62 6.5 12 6.5C13.38 6.5 14.5 7.62 14.5 9C14.5 10.38 13.38 11.5 12 11.5Z" fill="currentColor"/></svg> Рисуйте на карте (клик для точек, двойной клик — завершить)</span>
                        </div>
                    </div>

                    <!-- Правая колонка: список зон -->
                    <div style="flex:1; min-width:300px;">
                        <div style="border-bottom:none; padding-bottom:0; margin-bottom:12px;">
                            <h2 style="font-size:18px; margin:0; color:var(--bg-white);">Зоны доставки</h2>
                            <span style="font-size:13px; color:var(--color-muted);">Всего: <?= count($deliveryZones) ?></span>
                        </div>
                        <div id="zones-list" style="max-height:460px; overflow-y:auto;">
                            <?php foreach ($deliveryZones as $z):
                                $restName = '';
                                if ($z['RESTAURANT_ID'] > 0) {
                                    foreach ($restaurants as $r) {
                                        if ($r['ID'] == $z['RESTAURANT_ID']) {
                                            $restName = $r['NAME'];
                                            break;
                                        }
                                    }
                                }
                            ?>
                                <div class="rest-item" data-id="<?= $z['ID'] ?>" style="border-left:4px solid <?= htmlspecialchars($z['COLOR'] ?: '#00FF00') ?>;">
                                    <div class="rest-item__main">
                                        <div class="rest-item__info">
                                            <div class="rest-item__name"><?= htmlspecialchars($z['NAME']) ?></div>
                                            <div class="rest-item__meta">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="vertical-align:middle;margin-right:2px;"><path d="M20 8H17V4H3C1.9 4 1 4.9 1 6V17H3C3 18.66 4.34 20 6 20C7.66 20 9 18.66 9 17H15C15 18.66 16.34 20 18 20C19.66 20 21 18.66 21 17H23V12L20 8ZM6 18.5C5.17 18.5 4.5 17.83 4.5 17C4.5 16.17 5.17 15.5 6 15.5C6.83 15.5 7.5 16.17 7.5 17C7.5 17.83 6.83 18.5 6 18.5ZM19.5 9.5L21.46 12H17V9.5H19.5ZM18 18.5C17.17 18.5 16.5 17.83 16.5 17C16.5 16.17 17.17 15.5 18 15.5C18.83 15.5 19.5 16.17 19.5 17C19.5 17.83 18.83 18.5 18 18.5Z" fill="currentColor"/></svg> <?= (int)$z['PRICE'] ?> руб.
                                                <?php if ((int)$z['FREE_DELIVERY_PRICE'] > 0): ?> · Бесплатно от <?= (int)$z['FREE_DELIVERY_PRICE'] ?> руб.<?php endif; ?>
                                                <?php if ($restName): ?> · <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="vertical-align:middle;margin-right:2px;"><path d="M22 3H2V6H22V3ZM4 8V20H20V8H4ZM6 10H8V18H6V10ZM10 10H12V18H10V10ZM14 10H16V18H14V10Z" fill="currentColor"/></svg><?= htmlspecialchars($restName) ?><?php endif; ?>
                                                <?php if ($highLoadEnabled === 'Y' && $highLoadAddTime > 0): ?>
                                                    <span class="zone-highload-badge">+<?= $highLoadAddTime ?> мин. выс.нагр.</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="rest-item__burger" onclick="event.stopPropagation();toggleRestMenu(this)">
                                            <span></span><span></span><span></span>
                                            <div class="rest-item__dropdown">
                                                <div onclick="event.stopPropagation();openZoneEdit(<?= $z['ID'] ?>)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="vertical-align:middle;margin-right:4px;"><path d="M3 17.25V21H6.75L17.81 9.94L14.06 6.19L3 17.25ZM20.71 7.04C21.1 6.65 21.1 6.02 20.71 5.63L18.37 3.29C17.98 2.9 17.35 2.9 16.96 3.29L15.13 5.12L18.88 8.87L20.71 7.04Z" fill="currentColor"/></svg>Изменить</div>
                                                <div class="rest-item__dropdown-del" onclick="event.stopPropagation();deleteZone(<?= $z['ID'] ?>)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="vertical-align:middle;margin-right:4px;"><path d="M6 19C6 20.1 6.9 21 8 21H16C17.1 21 18 20.1 18 19V7H6V19ZM8 9H16V19H8V9ZM15.5 4L14.5 3H9.5L8.5 4H5V6H19V4H15.5Z" fill="currentColor"/></svg>Удалить</div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Форма редактирования -->
                                    <div class="rest-item__edit" id="zone-edit-<?= $z['ID'] ?>">
                                        <div class="rest-item__edit-inner">
                                            <div class="rest-edit-field">
                                                <label>Название *</label>
                                                <input type="text" class="zone-edit-name" value="<?= htmlspecialchars($z['NAME']) ?>">
                                            </div>
                                            <div class="rest-edit-field">
                                                <label>Ресторан</label>
                                                <div class="custom-select zone-edit-restaurant-select" data-hidden="zone-edit-rest-<?= $z['ID'] ?>">
                                                    <div class="custom-select__trigger" onclick="toggleCustomSelect(this)">
                                                        <span class="placeholder"><?php
                                                            $selectedRest = '';
                                                            foreach ($restaurants as $r) {
                                                                if ($r['ID'] == $z['RESTAURANT_ID']) { $selectedRest = htmlspecialchars($r['NAME']); break; }
                                                            }
                                                            echo $selectedRest ?: '— Без привязки —';
                                                        ?></span>
                                                        <span class="arrow"></span>
                                                    </div>
                                                    <div class="custom-select__dropdown">
                                                        <div class="custom-select__option <?= $z['RESTAURANT_ID'] == 0 ? 'selected' : '' ?>" data-value="0" onclick="selectCustomOption(this)">— Без привязки —</div>
                                                        <?php foreach ($restaurants as $r): ?>
                                                            <div class="custom-select__option <?= $z['RESTAURANT_ID'] == $r['ID'] ? 'selected' : '' ?>" data-value="<?= $r['ID'] ?>" onclick="selectCustomOption(this)"><?= htmlspecialchars($r['NAME']) ?></div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <input type="hidden" id="zone-edit-rest-<?= $z['ID'] ?>" class="zone-edit-restaurant" value="<?= $z['RESTAURANT_ID'] ?>">
                                            </div>
                                            <div class="rest-edit-row">
                                                <div class="rest-edit-field">
                                                    <label>Цена доставки (руб)</label>
                                                    <input type="number" class="zone-edit-price" value="<?= (int)$z['PRICE'] ?>" min="0">
                                                </div>
                                                <div class="rest-edit-field">
                                                    <label>Бесплатно от (руб)</label>
                                                    <input type="number" class="zone-edit-free" value="<?= (int)$z['FREE_DELIVERY_PRICE'] ?>" min="0">
                                                </div>
                                            </div>
                                            <div class="rest-edit-row">
                                                <div class="rest-edit-field">
                                                    <label>Время от (мин)</label>
                                                    <input type="number" class="zone-edit-tstart" value="<?= (int)$z['DELIVERY_TIME_START'] ?>" min="0">
                                                </div>
                                                <div class="rest-edit-field">
                                                    <label>до (мин)</label>
                                                    <input type="number" class="zone-edit-tend" value="<?= (int)$z['DELIVERY_TIME_END'] ?>" min="0">
                                                </div>
                                            </div>
                                            <div class="rest-edit-field">
                                                <label>Мин. заказ (руб)</label>
                                                <input type="number" class="zone-edit-min" value="<?= (int)$z['MIN_ORDER_PRICE'] ?>" min="0">
                                            </div>
                                            <div class="rest-edit-row">
                                                <div class="rest-edit-field">
                                                    <label>Цвет</label>
                                                    <input type="color" class="zone-edit-color" value="<?= htmlspecialchars($z['COLOR'] ?: '#00FF00') ?>">
                                                </div>
                                                <div class="rest-edit-field">
                                                    <label>Активность</label>
                                                    <label class="toggle-switch">
                                                        <input type="checkbox" class="zone-edit-active" value="Y" <?= $z['ACTIVE'] === 'Y' ? 'checked' : '' ?>>
                                                        <span class="toggle-switch__slider"></span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="rest-edit-actions">
                                                <button class="p-btn p-btn--primary p-dash-btn-sm" onclick="saveZoneEdit(<?= $z['ID'] ?>)">Сохранить</button>
                                                <button class="p-btn p-btn--outline p-dash-btn-sm" onclick="cancelZoneEdit(<?= $z['ID'] ?>)">Отменить</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($deliveryZones)): ?>
                                <div style="padding:20px; text-align:center; color:var(--color-muted);">Зоны доставки не найдены</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

<!-- Модальное окно добавления зоны доставки -->
<div class="dz-form-overlay" id="zone-form-overlay">
    <div class="dz-form-modal">
        <h3 id="zone-form-title">Новая зона доставки</h3>
        <form onsubmit="event.preventDefault(); saveZoneForm();">
            <input type="hidden" id="zone-form-id" value="0">
            <input type="hidden" id="zone-form-coords" value="">
            <div class="dz-form-group">
                <label>Название *</label>
                <input type="text" id="zone-form-name" required placeholder="Например: Центр">
            </div>
            <div class="rest-edit-row">
                <div class="dz-form-group" style="flex:1;">
                    <label>Цена доставки (руб)</label>
                    <input type="number" id="zone-form-price" value="0" min="0">
                </div>
                <div class="dz-form-group" style="flex:1;">
                    <label>Бесплатно от (руб)</label>
                    <input type="number" id="zone-form-free" value="0" min="0">
                </div>
            </div>
            <div class="rest-edit-row">
                <div class="dz-form-group" style="flex:1;">
                    <label>Время от (мин)</label>
                    <input type="number" id="zone-form-tstart" value="0" min="0">
                </div>
                <div class="dz-form-group" style="flex:1;">
                    <label>до (мин)</label>
                    <input type="number" id="zone-form-tend" value="0" min="0">
                </div>
            </div>
            <div class="dz-form-group">
                <label>Ресторан</label>
                <div class="custom-select" id="zone-form-restaurant-select" data-hidden="zone-form-restaurant">
                    <div class="custom-select__trigger" onclick="toggleCustomSelect(this)">
                        <span class="placeholder">— Без привязки —</span>
                        <span class="arrow"></span>
                    </div>
                    <div class="custom-select__dropdown">
                        <div class="custom-select__option selected" data-value="0" onclick="selectCustomOption(this)">— Без привязки —</div>
                        <?php foreach ($restaurants as $r): ?>
                            <div class="custom-select__option" data-value="<?= $r['ID'] ?>" onclick="selectCustomOption(this)"><?= htmlspecialchars($r['NAME']) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input type="hidden" id="zone-form-restaurant" value="0">
            </div>
            <div class="rest-edit-row">
                <div class="dz-form-group" style="flex:1;">
                    <label>Мин. заказ (руб)</label>
                    <input type="number" id="zone-form-min" value="0" min="0">
                </div>
                <div class="dz-form-group" style="flex:1;">
                    <label>Цвет</label>
                    <input type="color" id="zone-form-color" value="#00FF00">
                </div>
            </div>
            <div class="dz-form-actions">
                <button type="submit" class="dz-btn dz-btn--primary">Сохранить</button>
                <button type="button" class="dz-btn dz-btn--secondary" onclick="closeZoneForm()">Отмена</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно импорта из файла (2 шага) -->
<div class="dz-form-overlay" id="import-dialog">
    <div class="dz-form-modal" style="max-width:480px;">
        <!-- Шаг 1: Предупреждение -->
        <div id="import-step-warning">
            <h3>Загрузка зон из файла</h3>
            <p style="color:#F44336;font-size:18px;line-height:1.5;margin:20px 0;font-weight:500;text-align:center;">
                При загрузке зон из файла, текущие зоны доставки будут удалены.
            </p>
            <div class="dz-form-actions" style="justify-content:center;">
                <button class="dz-btn dz-btn--primary" onclick="importProceed()" style="padding:14px 40px;font-size:16px;">Продолжить</button>
                <button class="dz-btn dz-btn--secondary" onclick="closeImportDialog()" style="padding:14px 40px;font-size:16px;">Отменить</button>
            </div>
        </div>
        <!-- Шаг 2: Форма загрузки (скрыт) -->
        <div id="import-step-form" style="display:none;">
            <h3>Загрузка зон из файла</h3>
            <p style="color:var(--color-muted);font-size:14px;line-height:1.6;margin:0 0 8px;">
                Выберите KML-файл с зонами доставки (формат Яндекс.Карт):
            </p>
            <div class="dz-form-group">
                <label>Выберите файл (.kml)</label>
                <input type="file" id="import-file-input" accept=".kml,.xml" style="padding:10px;font-size:14px;">
            </div>
            <div class="dz-form-actions">
                <button class="dz-btn dz-btn--primary" onclick="importFromFile()">Загрузить</button>
                <button class="dz-btn dz-btn--secondary" onclick="closeImportDialog()">Отмена</button>
            </div>
        </div>
    </div>
</div>

<script>
window.showImportDialog = function() {
    // Сбрасываем на шаг 1 (предупреждение)
    document.getElementById('import-step-warning').style.display = 'block';
    document.getElementById('import-step-form').style.display = 'none';
    document.getElementById('import-file-input').value = '';
    document.getElementById('import-dialog').classList.add('open');
};

window.closeImportDialog = function() {
    document.getElementById('import-dialog').classList.remove('open');
};

window.importProceed = function() {
    document.getElementById('import-step-warning').style.display = 'none';
    document.getElementById('import-step-form').style.display = 'block';
};

window.importFromFile = function() {
    var input = document.getElementById('import-file-input');
    if (!input.files || !input.files[0]) {
        alert('Выберите KML-файл для загрузки');
        return;
    }
    var reader = new FileReader();
    reader.onload = function(e) {
        var kmlContent = e.target.result;
        // Простая проверка, что это KML
        if (kmlContent.indexOf('<kml') === -1) {
            alert('Файл должен быть в формате KML');
            return;
        }
        // Кодируем в base64, чтобы обойти проактивный фильтр Битрикс (блокирует XML в POST)
        var encoded = btoa(unescape(encodeURIComponent(kmlContent)));
        // Отправляем на сервер
        var fd = new FormData();
        fd.append('ajax_zone', '1');
        fd.append('action', 'import');
        fd.append('kml_base64', encoded);
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (resp.success) {
                closeImportDialog();
                location.reload();
            } else {
                alert(resp.error || 'Ошибка импорта');
            }
        });
    };
    reader.readAsText(input.files[0]);
};

document.getElementById('import-dialog')?.addEventListener('click', function(e) {
    if (e.target === this) closeImportDialog();
});
</script>

<?php if (!empty($yandexApiKey)): ?>
<script src="https://api-maps.yandex.ru/2.1/?apikey=<?= htmlspecialchars($yandexApiKey) ?>&lang=ru_RU"></script>
<?php endif; ?>

<script>
(function() {
    var tabBtns = document.querySelectorAll('.p-dash-tabs .tab-btn');
    tabBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tabName = this.dataset.tab;

            tabBtns.forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');

            document.querySelectorAll('.p-main .tab-content').forEach(function(tc) {
                tc.classList.remove('active');
                tc.style.display = 'none';
            });

            var target = document.getElementById('tab-' + tabName);
            if (target) {
                target.classList.add('active');
                target.style.display = 'block';
            }

            if (tabName === 'zones') {
                initZonesMap();
            }
        });
    });

    // --- Карта зон доставки (глобальные) ---
    window.zonesMapInitialized = false;
    window.zonesMap = null;
    window.zoneDrawing = false;
    window.zoneTempPoints = [];
    window.zoneTempPolygon = null;
    window.zoneTempPlacemarks = [];

    window.finishZoneDrawing = function() {
        if (zoneTempPoints.length < 3) return;

        var coordsJson = JSON.stringify(zoneTempPoints);
        var overlay = document.getElementById('zone-form-overlay');
        document.getElementById('zone-form-coords').value = coordsJson;
        document.getElementById('zone-form-title').textContent = 'Новая зона доставки';
        document.getElementById('zone-form-id').value = '0';
        document.getElementById('zone-form-name').value = '';
        document.getElementById('zone-form-price').value = '0';
        document.getElementById('zone-form-free').value = '0';
        document.getElementById('zone-form-tstart').value = '0';
        document.getElementById('zone-form-tend').value = '0';
        document.getElementById('zone-form-min').value = '0';
        document.getElementById('zone-form-color').value = '#00FF00';
        overlay.classList.add('open');
        cancelAddZone();
    }

    window.startAddZone = function() {
        zoneDrawing = true;
        document.getElementById('btn-add-zone').style.display = 'none';
        document.getElementById('btn-cancel-add-zone').style.display = 'inline-block';
        document.getElementById('zone-add-hint').style.display = 'inline-flex';
    };

    window.cancelAddZone = function() {
        zoneDrawing = false;
        document.getElementById('btn-add-zone').style.display = 'inline-block';
        document.getElementById('btn-cancel-add-zone').style.display = 'none';
        document.getElementById('zone-add-hint').style.display = 'none';

        if (zoneTempPolygon) { zonesMap.geoObjects.remove(zoneTempPolygon); zoneTempPolygon = null; }
        zoneTempPlacemarks.forEach(function(pm) { zonesMap.geoObjects.remove(pm); });
        zoneTempPlacemarks = [];
        zoneTempPoints = [];
    };

    window.closeZoneForm = function() {
        document.getElementById('zone-form-overlay').classList.remove('open');
    };

    window.saveZoneForm = function() {
        var id = parseInt(document.getElementById('zone-form-id').value) || 0;
        var fd = new FormData();
        fd.append('ajax_zone', '1');
        fd.append('action', 'save');
        fd.append('ID', id);
        fd.append('NAME', document.getElementById('zone-form-name').value);
        fd.append('COORDINATES', document.getElementById('zone-form-coords').value);
        fd.append('PRICE', document.getElementById('zone-form-price').value);
        fd.append('FREE_FROM', document.getElementById('zone-form-free').value);
        fd.append('DELIVERY_TIME_START', document.getElementById('zone-form-tstart').value);
        fd.append('DELIVERY_TIME_END', document.getElementById('zone-form-tend').value);
        fd.append('MIN_ORDER_PRICE', document.getElementById('zone-form-min').value);
        fd.append('COLOR', document.getElementById('zone-form-color').value);
        fd.append('ACTIVE', 'Y');
        fd.append('RESTAURANT_ID', document.getElementById('zone-form-restaurant').value);

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { closeZoneForm(); location.reload(); }
            else { alert(data.error || 'Ошибка'); }
        });
    };

    window.deleteZone = function(id) {
        if (!confirm('Удалить зону доставки?')) return;
        var fd = new FormData();
        fd.append('ajax_zone', '1');
        fd.append('action', 'delete');
        fd.append('ID', id);
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) { if (data.success) location.reload(); else alert(data.error); });
    };

    window.openZoneEdit = function(id) {
        cancelZoneEdit(0);
        var el = document.getElementById('zone-edit-' + id);
        if (el) el.classList.add('open');
    };

    window.cancelZoneEdit = function(id) {
        document.querySelectorAll('.rest-item__edit.open').forEach(function(el) { el.classList.remove('open'); });
    };

    window.saveZoneEdit = function(id) {
        var el = document.getElementById('zone-edit-' + id);
        if (!el) return;
        var fd = new FormData();
        fd.append('ajax_zone', '1');
        fd.append('action', 'save');
        fd.append('ID', id);
        fd.append('NAME', el.querySelector('.zone-edit-name').value);
        fd.append('PRICE', el.querySelector('.zone-edit-price').value);
        fd.append('FREE_FROM', el.querySelector('.zone-edit-free').value);
        fd.append('DELIVERY_TIME_START', el.querySelector('.zone-edit-tstart').value);
        fd.append('DELIVERY_TIME_END', el.querySelector('.zone-edit-tend').value);
        fd.append('MIN_ORDER_PRICE', el.querySelector('.zone-edit-min').value);
        fd.append('COLOR', el.querySelector('.zone-edit-color').value);
        fd.append('ACTIVE', el.querySelector('.zone-edit-active').value);
        fd.append('RESTAURANT_ID', el.querySelector('.zone-edit-restaurant').value);
        fd.append('COORDINATES', '[]');

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) { if (data.success) location.reload(); else alert(data.error); });
    };

    window.toggleHighLoad = function(cb) {
        var bar = document.getElementById('zone-highload-bar');
        var settings = document.getElementById('zone-high-load-settings');
        if (cb.checked) {
            bar.classList.add('active');
            settings.classList.add('is-visible');
        } else {
            bar.classList.remove('active');
            settings.classList.remove('is-visible');
        }
        saveHighLoad();
    };

    window.saveHighLoad = function() {
        var cb = document.getElementById('zone-high-load');
        var minutes = document.getElementById('zone-high-load-minutes').value;
        var fd = new FormData();
        fd.append('ajax_zone', '1');
        fd.append('action', 'save_high_load');
        fd.append('high_load_enabled', cb.checked ? 'Y' : 'N');
        fd.append('high_load_add_time', minutes);
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
    };

    // Табы инициализируются через tab-клики или по готовности DOM
})();

// Инициализация карт после загрузки всех скриптов
document.addEventListener('DOMContentLoaded', function() {
    var zonesTab = document.getElementById('tab-zones');
    if (zonesTab && zonesTab.style.display !== 'none') {
        setTimeout(function() { if (window.initZonesMap) initZonesMap(); }, 100);
    }
});

// --- Управление ресторанами ---
function toggleRestMenu(el) {
    var dropdown = el.querySelector('.rest-item__dropdown');
    if (!dropdown) return;
    var isOpen = dropdown.classList.contains('open');
    document.querySelectorAll('.rest-item__dropdown.open').forEach(function(d) { d.classList.remove('open'); });
    if (!isOpen) dropdown.classList.add('open');
}

document.addEventListener('click', function() {
    document.querySelectorAll('.rest-item__dropdown.open').forEach(function(d) { d.classList.remove('open'); });
});

function togglePartnersMenu() {
    document.getElementById('p-sidebar').classList.toggle('open');
    document.getElementById('p-overlay').classList.toggle('open');
}

// ===== Кастомный селект =====
function toggleCustomSelect(trigger) {
    var wrapper = trigger.closest('.custom-select');
    if (!wrapper) return;
    var isOpen = wrapper.classList.contains('open');
    // Закрыть все другие
    document.querySelectorAll('.custom-select.open').forEach(function(s) { s.classList.remove('open'); });
    if (!isOpen) wrapper.classList.add('open');
}
function selectCustomOption(el) {
    var wrapper = el.closest('.custom-select');
    if (!wrapper) return;
    // Снять выделение со всех опций
    wrapper.querySelectorAll('.custom-select__option').forEach(function(o) { o.classList.remove('selected'); });
    el.classList.add('selected');
    // Обновить текст триггера
    var trigger = wrapper.querySelector('.custom-select__trigger .placeholder');
    if (trigger) trigger.textContent = el.textContent;
    // Обновить скрытый input
    var hiddenId = wrapper.dataset.hidden;
    if (hiddenId) {
        var hidden = document.getElementById(hiddenId);
        if (hidden) hidden.value = el.dataset.value;
    }
    wrapper.classList.remove('open');
}
// Закрыть селекты при клике вне
document.addEventListener('click', function(e) {
    if (!e.target.closest('.custom-select')) {
        document.querySelectorAll('.custom-select.open').forEach(function(s) { s.classList.remove('open'); });
    }
});

// ===== Toggle активности =====
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('zone-edit-active')) {
        var label = e.target.closest('.toggle-switch');
        if (label) {
            var labelText = label.querySelector('.toggle-switch__label');
            if (labelText) labelText.textContent = e.target.checked ? 'Активна' : 'Неактивна';
        }
    }
});

// ===== Редактирование зоны на карте =====
var zonePolygons = {}; // id -> { polygon, coords, color }

// Переопределяем initZonesMap для сохранения ссылок на полигоны
(function() {
    var origInitZonesMap = window.initZonesMap;
    window.initZonesMap = function() {
        if (zonesMapInitialized) return;
        var container = document.getElementById('zones-map');
        if (!container) return;
        if (typeof ymaps === 'undefined') { setTimeout(window.initZonesMap, 500); return; }

        ymaps.ready(function() {
            try {
                if (zonesMapInitialized) return;
                if (!zonesMap) {
                    zonesMap = new ymaps.Map('zones-map', {
                        center: [<?= $defaultLat ?>, <?= $defaultLng ?>],
                        zoom: <?= $defaultZoom ?>,
                        controls: ['zoomControl', 'fullscreenControl', 'searchControl']
                    });
                    zonesMapInitialized = true;
                }

                // Рисуем существующие зоны и сохраняем ссылки
                <?php foreach ($deliveryZones as $z):
                    if (!empty($z['COORDINATES']) && is_array($z['COORDINATES']) && count($z['COORDINATES']) >= 3):
                        $coordsJson = json_encode($z['COORDINATES']);
                        $color = $z['COLOR'] ?: '#00FF00';
                        $zId = $z['ID'];
                ?>
                (function(){
                    var coords = <?= $coordsJson ?>;
                    var poly = new ymaps.Polygon([coords], {}, {
                        fillColor: '<?= $color ?>33',
                        strokeColor: '<?= $color ?>',
                        strokeWidth: 3,
                        fillOpacity: 0.4,
                        zIndex: 10
                    });
                    zonesMap.geoObjects.add(poly);
                    poly.events.add('click', function() {
                        selectZoneOnMap(<?= $zId ?>);
                        highlightZoneInList(<?= $zId ?>);
                    });
                    zonePolygons[<?= $zId ?>] = { polygon: poly, coords: coords, color: '<?= $color ?>' };
                })();
                <?php
                    endif;
                endforeach; ?>

                // Клик в режиме рисования
                zonesMap.events.add('click', function(e) {
                    if (!zoneDrawing) return;
                    var coords = e.get('coords');
                    zoneTempPoints.push(coords);
                    var pm = new ymaps.Placemark(coords, {}, { preset: 'islands#greenDotIcon' });
                    zonesMap.geoObjects.add(pm);
                    zoneTempPlacemarks.push(pm);
                    if (zoneTempPolygon) zonesMap.geoObjects.remove(zoneTempPolygon);
                    if (zoneTempPoints.length >= 3) {
                        zoneTempPolygon = new ymaps.Polygon([zoneTempPoints], {}, {
                            fillColor: '#F4433640',
                            strokeColor: '#F44336',
                            strokeWidth: 3,
                            fillOpacity: 0.3
                        });
                        zonesMap.geoObjects.add(zoneTempPolygon);
                    }
                });
                zonesMap.events.add('dblclick', function(e) {
                    if (!zoneDrawing || zoneTempPoints.length < 3) return;
                    e.stopPropagation();
                    finishZoneDrawing();
                });
            } catch(e) { console.error('Zones map init error:', e); }
        });
    };
})();

// ===== Редактирование зоны на карте =====
var editingZoneId = null;
var editOverlayPolygon = null;

window.selectZoneOnMap = function(zoneId) {
    deselectZoneOnMap();
    var entry = zonePolygons[zoneId];
    if (!entry || !zonesMap) return;
    editingZoneId = zoneId;

    // Создаём редактируемый полигон ПОВЕРХ (не удаляем оригинал)
    editOverlayPolygon = new ymaps.Polygon([entry.coords], {
        hintContent: 'Редактирование зоны'
    }, {
        fillColor: entry.color + '66',
        strokeColor: entry.color,
        strokeWidth: 4,
        fillOpacity: 0.5,
        zIndex: 50,
        editor: {
            options: {
                drawing: true,
                maxPoints: 0
            }
        }
    });

    zonesMap.geoObjects.add(editOverlayPolygon);
    editOverlayPolygon.editor.startEditing();

    // Подсветка оригинала
    entry.polygon.options.set({ fillOpacity: 0.2, strokeWidth: 2, zIndex: 10 });

    // Центрируем
    var center = getPolygonCenter(entry.coords);
    zonesMap.setCenter(center, Math.max(zonesMap.getZoom(), 12), {duration: 300});
};

function deselectZoneOnMap() {
    if (editOverlayPolygon) {
        try { editOverlayPolygon.editor.stopEditing(); } catch(e) {}
        if (zonesMap) zonesMap.geoObjects.remove(editOverlayPolygon);
        editOverlayPolygon = null;
    }
    // Восстанавливаем оригинал
    if (editingZoneId !== null) {
        var entry = zonePolygons[editingZoneId];
        if (entry) {
            entry.polygon.options.set({ fillOpacity: 0.4, strokeWidth: 3, zIndex: 10 });
        }
    }
    editingZoneId = null;
}

function getPolygonCenter(coords) {
    var lat = 0, lng = 0, count = coords.length;
    if (!count) return [54.7355, 55.9587];
    coords.forEach(function(c) { lat += c[0]; lng += c[1]; });
    return [lat / count, lng / count];
}

window.openZoneEdit = function(id) {
    cancelZoneEdit(0);
    var el = document.getElementById('zone-edit-' + id);
    if (el) el.classList.add('open');
    selectZoneOnMap(id);
};

window.cancelZoneEdit = function(id) {
    document.querySelectorAll('.rest-item__edit.open').forEach(function(el) { el.classList.remove('open'); });
    deselectZoneOnMap();
};

window.saveZoneEdit = function(id) {
    var el = document.getElementById('zone-edit-' + id);
    if (!el) return;
    var activeCb = el.querySelector('.zone-edit-active');
    var fd = new FormData();
    fd.append('ajax_zone', '1');
    fd.append('action', 'save');
    fd.append('ID', id);
    fd.append('NAME', el.querySelector('.zone-edit-name').value);
    fd.append('PRICE', el.querySelector('.zone-edit-price').value);
    fd.append('FREE_FROM', el.querySelector('.zone-edit-free').value);
    fd.append('DELIVERY_TIME_START', el.querySelector('.zone-edit-tstart').value);
    fd.append('DELIVERY_TIME_END', el.querySelector('.zone-edit-tend').value);
    fd.append('MIN_ORDER_PRICE', el.querySelector('.zone-edit-min').value);
    fd.append('COLOR', el.querySelector('.zone-edit-color').value);
    fd.append('ACTIVE', activeCb ? (activeCb.checked ? 'Y' : 'N') : 'Y');
    fd.append('RESTAURANT_ID', el.querySelector('.zone-edit-restaurant').value);

    // Координаты из редактора
    if (editOverlayPolygon) {
        try {
            var raw = editOverlayPolygon.geometry.getCoordinates();
            var coords = raw[0] || raw;
            fd.append('COORDINATES', JSON.stringify(coords));
        } catch(e) {
            fd.append('COORDINATES', '[]');
        }
    } else {
        fd.append('COORDINATES', '[]');
    }

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            deselectZoneOnMap();
            location.reload();
        }
        else alert(data.error);
    });
};

</script>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>













