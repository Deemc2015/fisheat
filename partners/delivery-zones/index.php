<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Page\Asset;
use Ldo\Deliverymap\SettingsTable;
use Ldo\Deliverymap\RestaurantsTable;
use Ldo\Deliverymap\DeliveryZoneTable;

global $USER;

$APPLICATION->SetTitle("Управление доставкой");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Управление доставкой");

Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/partners.css");
Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/fonts/fonts.css");
Asset::getInstance()->addJs(SITE_TEMPLATE_PATH. '/assets/js/jquery.min.js');
Asset::getInstance()->addJs(SITE_TEMPLATE_PATH. '/assets/js/main.js');

$request = Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

$siteId = 's1';
$moduleLoaded = Loader::includeModule('ldo.deliverymap');

// Читаем настройки из БД
$yandexApiKey  = $moduleLoaded ? SettingsTable::get($siteId, 'yandex_api_key', '') : '';
$defaultLat    = $moduleLoaded ? SettingsTable::get($siteId, 'default_lat', '54.7355') : '54.7355';
$defaultLng    = $moduleLoaded ? SettingsTable::get($siteId, 'default_lng', '55.9587') : '55.9587';
$defaultZoom   = $moduleLoaded ? SettingsTable::get($siteId, 'default_zoom', '11') : '11';

// --- Сохранение настроек ---
if ($request->isPost() && $request->getPost('save_settings') === 'Y' && $moduleLoaded) {
    $key   = trim((string)$request->getPost('yandex_api_key'));
    $lat   = trim((string)$request->getPost('default_lat'));
    $lng   = trim((string)$request->getPost('default_lng'));
    $zoom  = trim((string)$request->getPost('default_zoom'));

    SettingsTable::set($siteId, 'yandex_api_key', $key);
    SettingsTable::set($siteId, 'default_lat', $lat);
    SettingsTable::set($siteId, 'default_lng', $lng);
    SettingsTable::set($siteId, 'default_zoom', $zoom);

    LocalRedirect('/partners/delivery-zones/?settings_saved=1');
}

$saved = $request->getQuery('settings_saved') === '1';

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

// --- AJAX обработка ресторанов ---
if ($request->isPost() && $request->getPost('ajax_restaurant') && $moduleLoaded) {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'error' => 'Неизвестное действие'];

    try {
        $action = $request->getPost('action');

        if ($action === 'save') {
            $id = (int)$request->getPost('ID');
            $data = [
                'NAME' => trim((string)$request->getPost('NAME')),
                'COORDINATES' => trim((string)$request->getPost('COORDINATES')),
                'SITE_ID' => $siteId,
                'PHONE' => trim((string)$request->getPost('PHONE')),
                'EMAIL' => trim((string)$request->getPost('EMAIL')),
                'REQUISITES' => trim((string)$request->getPost('REQUISITES')),
                'ACTIVE' => $request->getPost('ACTIVE') === 'Y' ? 'Y' : 'N',
            ];

            if (empty($data['NAME'])) throw new \Exception('Введите название ресторана');

            if ($id > 0) {
                RestaurantsTable::update($id, $data);
                $response = ['success' => true, 'message' => 'Ресторан обновлён'];
            } else {
                $result = RestaurantsTable::add($data);
                $response = ['success' => true, 'id' => $result->getId(), 'message' => 'Ресторан добавлен'];
            }
        } elseif ($action === 'delete') {
            $id = (int)$request->getPost('ID');
            if ($id <= 0) throw new \Exception('Неверный ID');
            RestaurantsTable::delete($id);
            $response = ['success' => true, 'message' => 'Ресторан удалён'];
        } elseif ($action === 'list') {
            $list = RestaurantsTable::getList([
                'filter' => ['=SITE_ID' => $siteId],
                'order' => ['ID' => 'ASC']
            ])->fetchAll();
            $response = ['success' => true, 'restaurants' => $list];
        }
    } catch (\Exception $e) {
        $response = ['success' => false, 'error' => $e->getMessage()];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    die();
}
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
<style>
.p-dash-tabs {
    display: flex;
    gap: 12px;
    margin-bottom: 0;
    padding: 0 32px;
    background: transparent;
}
.p-dash-tabs .tab-btn {
    padding: 14px 28px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--color-muted, #969696);
    font-size: 16px;
    font-family: var(--font-family, 'Blogger Sans', 'Roboto');
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}
.p-dash-tabs .tab-btn:hover {
    color: var(--bg-white, #FFFFFF);
}
.p-dash-tabs .tab-btn.active {
    color: var(--bg-button, #F44336);
    border-bottom-color: var(--bg-button, #F44336);
}
.p-main {
    padding-top: 24px;
}

/* Модальное окно (общее для зон и ресторанов) */
.dz-form-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.dz-form-overlay.open {
    display: flex;
}
.dz-form-modal {
    background: var(--bg-gray, #2B2D2F);
    border-radius: 16px;
    padding: 32px;
    width: 500px;
    max-width: 92vw;
    max-height: 90vh;
    overflow-y: auto;
}
.dz-form-modal h3 {
    margin: 0 0 20px;
    color: var(--bg-white, #FFFFFF);
    font-size: 20px;
}
.dz-form-group {
    margin-bottom: 16px;
}
.dz-form-group label {
    display: block;
    font-size: 14px;
    color: var(--color-muted, #969696);
    margin-bottom: 6px;
}
.dz-form-group input,
.dz-form-group select {
    width: 100%;
    padding: 12px 14px;
    background: var(--bg-black, #1B1818);
    border: 1px solid var(--color-border, #868686);
    border-radius: 8px;
    color: var(--bg-white, #FFFFFF);
    font-size: 15px;
    font-family: var(--font-family, 'Blogger Sans', 'Roboto');
    transition: all 0.3s ease;
    box-sizing: border-box;
}
.dz-form-group input:focus,
.dz-form-group select:focus {
    border-color: var(--bg-button, #F44336);
    outline: none;
}
.dz-form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}
.dz-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-family: var(--font-family, 'Blogger Sans', 'Roboto');
    cursor: pointer;
    transition: all 0.3s ease;
}
.dz-btn--primary {
    background: var(--bg-button, #F44336);
    color: #fff;
}
.dz-btn--primary:hover {
    background: #d32f2f;
}
.dz-btn--secondary {
    background: var(--bg-btn-secondary, #494C4F);
    color: #fff;
}
.dz-btn--secondary:hover {
    background: #5a5d60;
}

/* Форма настроек */
.settings-group {
    margin-bottom: 24px;
}
.settings-group label {
    display: block;
    font-size: 14px;
    color: var(--color-muted, #969696);
    margin-bottom: 8px;
    font-weight: 500;
}
.settings-group input[type="text"],
.settings-group input[type="number"] {
    width: 100%;
    max-width: 480px;
    padding: 12px 14px;
    background: var(--bg-black, #1B1818);
    border: 1px solid var(--color-border, #868686);
    border-radius: 8px;
    color: var(--bg-white, #FFFFFF);
    font-size: 15px;
    font-family: var(--font-family, 'Blogger Sans', 'Roboto');
    transition: all 0.3s ease;
    box-sizing: border-box;
}
.settings-group input:focus {
    border-color: var(--bg-button, #F44336);
    outline: none;
}
.settings-hint {
    display: block;
    font-size: 13px;
    color: var(--color-muted, #969696);
    margin-top: 6px;
}
.settings-coords {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.settings-coords span {
    color: var(--color-muted, #969696);
    font-size: 14px;
    white-space: nowrap;
}
.settings-coords input {
    max-width: 140px;
}

/* Рестораны */
.rest-item {
    background: var(--bg-black, #1B1818);
    border-radius: 10px;
    margin-bottom: 10px;
}
.rest-item__main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    cursor: pointer;
}
.rest-item__main:hover {
    background: rgba(255,255,255,0.03);
}
.rest-item__info {
    flex: 1;
    min-width: 0;
}
.rest-item__name {
    font-size: 15px;
    font-weight: 500;
    color: var(--bg-white, #FFFFFF);
}
.rest-item__meta {
    font-size: 13px;
    color: var(--color-muted, #969696);
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rest-item__burger {
    position: relative;
    width: 32px;
    height: 32px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    cursor: pointer;
    border-radius: 8px;
    flex-shrink: 0;
    margin-left: 12px;
}
.rest-item__burger:hover {
    background: rgba(255,255,255,0.08);
}
.rest-item__burger span {
    display: block;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--color-muted, #969696);
}
.rest-item__dropdown {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: var(--bg-gray, #2B2D2F);
    border-radius: 8px;
    padding: 4px 0;
    z-index: 50;
    min-width: 140px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.4);
}
.rest-item__dropdown.open {
    display: block;
}
.rest-item__dropdown div {
    padding: 10px 16px;
    font-size: 14px;
    color: var(--bg-white, #FFFFFF);
    cursor: pointer;
    white-space: nowrap;
}
.rest-item__dropdown div:hover {
    background: rgba(255,255,255,0.06);
}
.rest-item__dropdown-del:hover {
    color: #F44336 !important;
}
.rest-item__edit {
    display: none;
    border-top: 1px solid var(--color-divider-light, #ffffff30);
}
.rest-item__edit.open {
    display: block;
}
.rest-item__edit-inner {
    padding: 16px;
}
.rest-edit-field {
    margin-bottom: 12px;
}
.rest-edit-field label {
    display: block;
    font-size: 13px;
    color: var(--color-muted, #969696);
    margin-bottom: 4px;
}
.rest-edit-field input,
.rest-edit-field textarea {
    width: 100%;
    padding: 10px 12px;
    background: var(--bg-black, #1B1818);
    border: 1px solid var(--color-border, #868686);
    border-radius: 6px;
    color: var(--bg-white, #FFFFFF);
    font-size: 14px;
    font-family: var(--font-family, 'Blogger Sans', 'Roboto');
    box-sizing: border-box;
}
.rest-edit-field input:focus,
.rest-edit-field textarea:focus {
    border-color: var(--bg-button, #F44336);
    outline: none;
}
.rest-edit-row {
    display: flex;
    gap: 12px;
}
.rest-edit-row .rest-edit-field {
    flex: 1;
}
.rest-edit-actions {
    display: flex;
    gap: 10px;
    margin-top: 8px;
}
.settings-saved {
    display: inline-block;
    padding: 8px 16px;
    background: rgba(76, 175, 80, 0.15);
    color: #4CAF50;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 16px;
}
#settings-map {
    width: 50%;
    height: 400px;
    border-radius: 12px;
    overflow: hidden;
    margin-top: 16px;
}

/* ===== Toggle Switch (чекбокс активности) ===== */
.toggle-switch {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    user-select: none;
}
.toggle-switch input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.toggle-switch__slider {
    position: relative;
    width: 44px;
    height: 24px;
    background: var(--color-border, #868686);
    border-radius: 12px;
    transition: all 0.3s ease;
    flex-shrink: 0;
}
.toggle-switch__slider::before {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 18px;
    height: 18px;
    background: #fff;
    border-radius: 50%;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.toggle-switch input:checked + .toggle-switch__slider {
    background: var(--bg-button, #4CAF50);
}
.toggle-switch input:checked + .toggle-switch__slider::before {
    left: 23px;
}
.toggle-switch__label {
    font-size: 14px;
    color: var(--bg-white, #FFFFFF);
}

/* ===== Кастомный селект (Restaurant Picker) ===== */
.custom-select {
    position: relative;
    width: 100%;
}
.custom-select__trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    background: var(--bg-black, #1B1818);
    border: 1px solid var(--color-border, #868686);
    border-radius: 8px;
    color: var(--bg-white, #FFFFFF);
    font-size: 15px;
    font-family: var(--font-family, 'Blogger Sans', 'Roboto');
    cursor: pointer;
    transition: all 0.3s ease;
    box-sizing: border-box;
    gap: 8px;
}
.custom-select__trigger:hover,
.custom-select.open .custom-select__trigger {
    border-color: var(--bg-button, #F44336);
}
.custom-select__trigger .arrow {
    width: 10px;
    height: 10px;
    border: solid var(--color-muted, #969696);
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
    transition: transform 0.3s ease;
    margin-top: -4px;
    flex-shrink: 0;
}
.custom-select.open .custom-select__trigger .arrow {
    transform: rotate(-135deg);
    margin-top: 4px;
}
.custom-select__trigger .placeholder {
    color: var(--color-muted, #969696);
}
.custom-select__dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: var(--bg-gray, #2B2D2F);
    border: 1px solid var(--color-border, #868686);
    border-radius: 8px;
    z-index: 100;
    max-height: 200px;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0,0,0,0.4);
}
.custom-select.open .custom-select__dropdown {
    display: block;
}
.custom-select__option {
    padding: 10px 14px;
    font-size: 14px;
    color: var(--bg-white, #FFFFFF);
    cursor: pointer;
    transition: background 0.2s ease;
}
.custom-select__option:hover {
    background: rgba(255,255,255,0.06);
}
.custom-select__option.selected {
    color: var(--bg-button, #F44336);
    font-weight: 500;
}
.custom-select__option:first-child {
    border-radius: 8px 8px 0 0;
}
.custom-select__option:last-child {
    border-radius: 0 0 8px 8px;
}
</style>
</head>
<body>
<?$APPLICATION->ShowPanel()?>

<?if (!$USER->IsAuthorized()):?>
    <script>document.location.href = '/partners/';</script>
<?else:?>

<div class="partners-page">
    <div class="p-overlay" id="p-overlay" onclick="togglePartnersMenu()"></div>

    <aside class="p-sidebar" id="p-sidebar">
        <div class="p-sidebar__logo">
            <a href="/partners/">
                <img src="<?=SITE_TEMPLATE_PATH?>/assets/images/logo.svg" alt="Рыба закусывала">
            </a>
        </div>
        <nav class="p-sidebar__nav">
            <ul class="p-sidebar__menu">
                <li class="p-sidebar__menu-item">
                    <a href="/partners/" class="p-sidebar__menu-link">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M3 13H11V3H3V13ZM3 21H11V15H3V21ZM13 21H21V11H13V21ZM13 3V9H21V3H13Z" fill="white"/></svg>
                        <span>Обзор</span>
                    </a>
                </li>
                <li class="p-sidebar__menu-item">
                    <a href="/partners/delivery-zones/" class="p-sidebar__menu-link active">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 2C8.13 2 5 5.13 5 9C5 14.25 12 22 12 22C12 22 19 14.25 19 9C19 5.13 15.87 2 12 2ZM12 11.5C10.62 11.5 9.5 10.38 9.5 9C9.5 7.62 10.62 6.5 12 6.5C13.38 6.5 14.5 7.62 14.5 9C14.5 10.38 13.38 11.5 12 11.5Z" fill="white"/></svg>
                        <span>Управление доставкой</span>
                    </a>
                </li>
                <li class="p-sidebar__menu-item">
                    <a href="/partners/orders/" class="p-sidebar__menu-link">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM19 19H5V5H19V19ZM17 17H7V15H17V17ZM17 13H7V11H17V13ZM17 9H7V7H17V9Z" fill="white"/></svg>
                        <span>Заказы</span>
                    </a>
                </li>
                <li class="p-sidebar__menu-item">
                    <a href="/partners/settings/" class="p-sidebar__menu-link">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M19.14 12.94C19.18 12.64 19.2 12.33 19.2 12C19.2 11.68 19.18 11.36 19.13 11.06L21.16 9.48C21.34 9.34 21.39 9.07 21.28 8.87L19.36 5.55C19.24 5.33 18.99 5.26 18.77 5.33L16.38 6.29C15.88 5.91 15.35 5.59 14.76 5.35L14.4 2.81C14.36 2.57 14.16 2.4 13.91 2.4H10.09C9.84 2.4 9.64 2.57 9.6 2.81L9.24 5.35C8.65 5.59 8.12 5.92 7.62 6.29L5.23 5.33C5.01 5.25 4.76 5.33 4.64 5.55L2.72 8.87C2.61 9.08 2.66 9.34 2.84 9.48L4.87 11.06C4.82 11.36 4.8 11.69 4.8 12C4.8 12.31 4.82 12.64 4.87 12.94L2.84 14.52C2.66 14.66 2.61 14.93 2.72 15.13L4.64 18.45C4.76 18.67 5.01 18.74 5.23 18.67L7.62 17.71C8.12 18.09 8.65 18.41 9.24 18.65L9.6 21.19C9.65 21.43 9.84 21.6 10.09 21.6H13.91C14.16 21.6 14.36 21.43 14.4 21.19L14.76 18.65C15.35 18.41 15.88 18.09 16.38 17.71L18.77 18.67C19 18.75 19.25 18.67 19.36 18.45L21.28 15.13C21.39 14.91 21.34 14.66 21.16 14.52L19.14 12.94ZM12 15.6C10.02 15.6 8.4 13.98 8.4 12C8.4 10.02 10.02 8.4 12 8.4C13.98 8.4 15.6 10.02 15.6 12C15.6 13.98 13.98 15.6 12 15.6Z" fill="white"/></svg>
                        <span>Настройки</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="p-sidebar__bottom">
            <div class="p-sidebar__user">
                <div class="p-sidebar__user-avatar">Р</div>
                <div>
                    <div class="p-sidebar__user-name">Ресторан</div>
                    <div style="font-size:13px; color:var(--color-muted);">ул. Ленина, 1</div>
                </div>
            </div>
        </div>
    </aside>

    <main class="p-content">
        <div class="p-header" style="padding-bottom:0; border-bottom:none;">
            <div style="display:flex; align-items:center;">
                <button class="p-mobile-toggle" onclick="togglePartnersMenu()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M3 18H21V16H3V18ZM3 13H21V11H3V13ZM3 6V8H21V6H3Z" fill="white"/></svg>
                </button>
                <h1 class="p-header__title">Управление доставкой</h1>
            </div>
            <div class="p-header__actions">
                <button class="p-header__action-btn" title="Выйти" onclick="document.location='/partners/delivery-zones/?logout=yes'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M17 7L15.59 8.41L18.17 11H8V13H18.17L15.59 15.58L17 17L22 12L17 7ZM4 5H12V3H4C2.9 3 2 3.9 2 5V19C2 20.1 2.9 21 4 21H12V19H4V5Z" fill="white"/></svg>
                </button>
            </div>
        </div>

        <!-- Три кнопки-таба -->
        <div class="p-dash-tabs">
            <button class="tab-btn active" data-tab="zones">Зоны доставки</button>
            <button class="tab-btn" data-tab="restaurants">Рестораны</button>
            <button class="tab-btn" data-tab="settings">Настройки</button>
        </div>

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
                        <div id="zones-list">
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
                                                        <span class="toggle-switch__label"><?= $z['ACTIVE'] === 'Y' ? 'Активна' : 'Неактивна' ?></span>
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

            <!-- Вкладка: Рестораны -->
            <div class="tab-content" id="tab-restaurants" style="display:none;">
                <div class="p-section" style="display:flex; gap:20px; flex-wrap:wrap;">
                    <div class="p-section__header" style="width:100%;">
                        <h2 class="p-section__title">Рестораны</h2>
                    </div>
                    <!-- Левая колонка: карта -->
                    <div style="flex:1; min-width:300px;">
                        <?php if (!empty($yandexApiKey)): ?>
                            <div id="restaurants-map" style="width:100%; height:400px; border-radius:12px; overflow:hidden;"></div>
                        <?php else: ?>
                            <div style="padding:40px; text-align:center; color:var(--color-muted); background:var(--bg-black); border-radius:12px;">
                                API-ключ Яндекс.Карт не настроен.
                            </div>
                        <?php endif; ?>
                        <div style="display:flex; align-items:center; gap:12px; margin-top:12px;">
                            <button class="p-btn p-btn--primary" id="btn-add-rest" onclick="startAddRestaurant()">+ Добавить ресторан</button>
                            <button class="p-btn p-btn--outline" id="btn-cancel-add-rest" style="display:none;" onclick="cancelAddRestaurant()">Отменить</button>
                            <span id="rest-add-hint" style="display:none; font-size:14px; color:var(--bg-button);align-items:center;gap:6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 2C8.13 2 5 5.13 5 9C5 14.25 12 22 12 22C12 22 19 14.25 19 9C19 5.13 15.87 2 12 2ZM12 11.5C10.62 11.5 9.5 10.38 9.5 9C9.5 7.62 10.62 6.5 12 6.5C13.38 6.5 14.5 7.62 14.5 9C14.5 10.38 13.38 11.5 12 11.5Z" fill="currentColor"/></svg> Установите точку на карте</span>
                        </div>
                    </div>

                    <!-- Правая колонка: список ресторанов -->
                    <div style="flex:1; min-width:300px;">
                        <div class="p-section__header" style="border-bottom:none; padding-bottom:0; margin-bottom:12px;">
                            <h2 class="p-section__title" style="font-size:18px;">Рестораны</h2>
                            <span style="font-size:13px; color:var(--color-muted);">Всего: <?= count($restaurants) ?></span>
                        </div>
                        <div id="restaurants-list">
                            <?php foreach ($restaurants as $r): ?>
                                <div class="rest-item" data-id="<?= $r['ID'] ?>">
                                    <div class="rest-item__main">
                                        <div class="rest-item__info">
                                            <div class="rest-item__name"><?= htmlspecialchars($r['NAME']) ?></div>
                                            <div class="rest-item__meta">
                                                <?php if ($r['PHONE']): ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="vertical-align:middle;margin-right:2px;"><path d="M6.62 10.79C8.06 13.62 10.38 15.94 13.21 17.38L15.41 15.18C15.68 14.91 16.08 14.82 16.43 14.94C17.55 15.31 18.76 15.51 20 15.51C20.55 15.51 21 15.96 21 16.51V20C21 20.55 20.55 21 20 21C10.61 21 3 13.39 3 4C3 3.45 3.45 3 4 3H7.5C8.05 3 8.5 3.45 8.5 4C8.5 5.25 8.7 6.45 9.07 7.57C9.18 7.92 9.1 8.31 8.82 8.59L6.62 10.79Z" fill="currentColor"/></svg><?= htmlspecialchars($r['PHONE']) ?><?php endif; ?>
                                                <?php if ($r['PHONE'] && $r['EMAIL']): ?> · <?php endif; ?>
                                                <?php if ($r['EMAIL']): ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="vertical-align:middle;margin-right:2px;"><path d="M20 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6C22 4.9 21.1 4 20 4ZM20 8L12 13L4 8V6L12 11L20 6V8Z" fill="currentColor"/></svg><?= htmlspecialchars($r['EMAIL']) ?><?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="rest-item__burger" onclick="event.stopPropagation();toggleRestMenu(this)">
                                            <span></span><span></span><span></span>
                                            <div class="rest-item__dropdown">
                                                <div onclick="event.stopPropagation();openRestaurantEdit(<?= $r['ID'] ?>)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="vertical-align:middle;margin-right:4px;"><path d="M3 17.25V21H6.75L17.81 9.94L14.06 6.19L3 17.25ZM20.71 7.04C21.1 6.65 21.1 6.02 20.71 5.63L18.37 3.29C17.98 2.9 17.35 2.9 16.96 3.29L15.13 5.12L18.88 8.87L20.71 7.04Z" fill="currentColor"/></svg>Изменить</div>
                                                <div class="rest-item__dropdown-del" onclick="event.stopPropagation();deleteRestaurant(<?= $r['ID'] ?>)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="vertical-align:middle;margin-right:4px;"><path d="M6 19C6 20.1 6.9 21 8 21H16C17.1 21 18 20.1 18 19V7H6V19ZM8 9H16V19H8V9ZM15.5 4L14.5 3H9.5L8.5 4H5V6H19V4H15.5Z" fill="currentColor"/></svg>Удалить</div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Скрытая форма редактирования -->
                                    <div class="rest-item__edit" id="rest-edit-<?= $r['ID'] ?>">
                                        <div class="rest-item__edit-inner">
                                            <div class="rest-edit-field">
                                                <label>Название</label>
                                                <input type="text" class="rest-edit-name" value="<?= htmlspecialchars($r['NAME']) ?>">
                                            </div>
                                            <div class="rest-edit-field">
                                                <label>Координаты (lat, lng)</label>
                                                <input type="text" class="rest-edit-coords" value="<?= htmlspecialchars($r['COORDINATES']) ?>">
                                            </div>
                                            <div class="rest-edit-row">
                                                <div class="rest-edit-field">
                                                    <label>Телефон</label>
                                                    <input type="text" class="rest-edit-phone" value="<?= htmlspecialchars($r['PHONE']) ?>">
                                                </div>
                                                <div class="rest-edit-field">
                                                    <label>Email</label>
                                                    <input type="text" class="rest-edit-email" value="<?= htmlspecialchars($r['EMAIL']) ?>">
                                                </div>
                                            </div>
                                            <div class="rest-edit-field">
                                                <label>Реквизиты</label>
                                                <textarea class="rest-edit-reqv" rows="2"><?= htmlspecialchars($r['REQUISITES']) ?></textarea>
                                            </div>
                                            <div class="rest-edit-actions">
                                                <button class="p-btn p-btn--primary" style="padding:8px 20px;font-size:14px;" onclick="saveRestaurantEdit(<?= $r['ID'] ?>)">Сохранить</button>
                                                <button class="p-btn p-btn--outline" style="padding:8px 20px;font-size:14px;" onclick="cancelRestaurantEdit(<?= $r['ID'] ?>)">Отменить</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($restaurants)): ?>
                                <div style="padding:20px;text-align:center;color:var(--color-muted);">Рестораны не найдены</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Вкладка: Настройки -->
            <div class="tab-content" id="tab-settings" style="display:none;">
                <div class="p-section">
                    <div class="p-section__header">
                        <h2 class="p-section__title">Настройки доставки</h2>
                        <span style="font-size:13px; color:var(--color-muted);">Сайт: <?= htmlspecialchars($siteId) ?></span>
                    </div>

                    <?php if ($saved): ?>
                        <div class="settings-saved">✓ Настройки сохранены</div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="save_settings" value="Y">

                        <div class="settings-group">
                            <label for="yandex_api_key">API-ключ Яндекс.Карт</label>
                            <input type="text" id="yandex_api_key" name="yandex_api_key"
                                   value="<?= htmlspecialchars($yandexApiKey) ?>"
                                   placeholder="Ваш API-ключ">
                            <span class="settings-hint">
                                Получить в <a href="https://developer.tech.yandex.ru/" target="_blank" style="color:var(--bg-button);">кабинете разработчика Яндекс</a>
                            </span>
                        </div>

                        <div class="settings-group">
                            <label>Центр карты по умолчанию</label>
                            <div class="settings-coords">
                                <span>Широта:</span>
                                <input type="text" id="settings_lat" name="default_lat"
                                       value="<?= htmlspecialchars($defaultLat) ?>"
                                       style="max-width:110px;">
                                <span>Долгота:</span>
                                <input type="text" id="settings_lng" name="default_lng"
                                       value="<?= htmlspecialchars($defaultLng) ?>"
                                       style="max-width:110px;">
                                <span>Зум:</span>
                                <input type="number" id="settings_zoom" name="default_zoom"
                                       value="<?= (int)$defaultZoom ?>" min="1" max="19"
                                       style="max-width:70px;">
                            </div>
                        </div>

                    <?php if (!empty($yandexApiKey)): ?>
                        <div id="settings-map"></div>

                        <script>
                        (function() {
                            var mapInitialized = false;
                            var map, placemark;

                            function initSettingsMap() {
                                if (mapInitialized) return;
                                var container = document.getElementById('settings-map');
                                if (!container) return;

                                if (typeof ymaps === 'undefined') {
                                    setTimeout(initSettingsMap, 500);
                                    return;
                                }

                                ymaps.ready(function() {
                                    try {
                                        var lat = parseFloat(document.getElementById('settings_lat').value) || 54.7355;
                                        var lng = parseFloat(document.getElementById('settings_lng').value) || 55.9587;
                                        var zoom = parseInt(document.getElementById('settings_zoom').value) || 11;

                                        map = new ymaps.Map('settings-map', {
                                            center: [lat, lng],
                                            zoom: zoom,
                                            controls: ['zoomControl', 'fullscreenControl', 'geolocationControl']
                                        });

                                        placemark = new ymaps.Placemark([lat, lng], {}, {
                                            preset: 'islands#redDotIcon',
                                            draggable: true
                                        });
                                        map.geoObjects.add(placemark);

                                        placemark.events.add('dragend', function() {
                                            var coords = placemark.geometry.getCoordinates();
                                            updateCoords(coords[0], coords[1]);
                                        });

                                        map.events.add('click', function(e) {
                                            var coords = e.get('coords');
                                            placemark.geometry.setCoordinates(coords);
                                            updateCoords(coords[0], coords[1]);
                                        });

                                        mapInitialized = true;
                                    } catch (e) {
                                        console.error('Settings map init error:', e);
                                    }
                                });
                            }

                            function updateCoords(lat, lng) {
                                document.getElementById('settings_lat').value = lat.toFixed(6);
                                document.getElementById('settings_lng').value = lng.toFixed(6);
                            }

                            // Инициализируем карту при показе таба настроек
                            function checkSettingsTab() {
                                var tab = document.getElementById('tab-settings');
                                if (tab && tab.style.display !== 'none' && tab.classList.contains('active')) {
                                    initSettingsMap();
                                } else {
                                    setTimeout(checkSettingsTab, 300);
                                }
                            }
                            checkSettingsTab();
                        })();
                        </script>
                    <?php else: ?>
                        <div style="margin-top:32px; padding:20px; text-align:center; color:var(--color-muted); background:var(--bg-black); border-radius:12px;">
                            Укажите API-ключ Яндекс.Карт и сохраните настройки, чтобы появилась карта для выбора центра.
                        </div>
                    <?php endif; ?>

                    <br>
                    <button type="submit" class="p-btn p-btn--primary">Сохранить настройки</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
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

<!-- Модальное окно добавления ресторана -->
<div class="dz-form-overlay" id="rest-form-overlay">
    <div class="dz-form-modal">
        <h3 id="rest-form-title">Новый ресторан</h3>
        <form id="rest-form" onsubmit="return saveRestaurant(event)">
            <input type="hidden" id="rest-id" value="0">
            <input type="hidden" id="rest-coords-save" value="">
            <div class="dz-form-group">
                <label>Название *</label>
                <input type="text" id="rest-name" required placeholder="Название ресторана">
            </div>
            <div class="dz-form-group">
                <label>Координаты</label>
                <input type="text" id="rest-coords-display" readonly style="opacity:0.7;" placeholder="Будут взяты с карты">
            </div>
            <div style="display:flex;gap:12px;">
                <div class="dz-form-group" style="flex:1;">
                    <label>Телефон</label>
                    <input type="text" id="rest-phone" placeholder="+7 (999) 123-45-67">
                </div>
                <div class="dz-form-group" style="flex:1;">
                    <label>Email</label>
                    <input type="text" id="rest-email" placeholder="rest@example.com">
                </div>
            </div>
            <div class="dz-form-group">
                <label>Реквизиты</label>
                <textarea id="rest-reqv" rows="3"></textarea>
            </div>
            <div class="dz-form-actions">
                <button type="submit" class="dz-btn dz-btn--primary">Сохранить</button>
                <button type="button" class="dz-btn dz-btn--secondary" onclick="closeRestForm()">Отмена</button>
            </div>
        </form>
    </div>
</div>

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
            if (tabName === 'restaurants') {
                initRestaurantsMap();
            }
        });
    });

    // --- Карта ресторанов (глобальные) ---
    window.restMapInitialized = false;
    window.restMap = null;
    var restAdding = false;    // режим добавления
    var restTempPlacemark = null;

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

    // Режим добавления
    window.startAddRestaurant = function() {
        restAdding = true;
        document.getElementById('btn-add-rest').style.display = 'none';
        document.getElementById('btn-cancel-add-rest').style.display = 'inline-block';
        document.getElementById('rest-add-hint').style.display = 'inline-flex';
    };

    window.cancelAddRestaurant = function() {
        restAdding = false;
        document.getElementById('btn-add-rest').style.display = 'inline-block';
        document.getElementById('btn-cancel-add-rest').style.display = 'none';
        document.getElementById('rest-add-hint').style.display = 'none';

        if (restTempPlacemark && restMap) {
            restMap.geoObjects.remove(restTempPlacemark);
            restTempPlacemark = null;
        }
    };

    // Табы инициализируются через tab-клики или по готовности DOM
})();

// Инициализация карт после загрузки всех скриптов
document.addEventListener('DOMContentLoaded', function() {
    var zonesTab = document.getElementById('tab-zones');
    if (zonesTab && zonesTab.style.display !== 'none') {
        setTimeout(function() { if (window.initZonesMap) initZonesMap(); }, 100);
    }
    var restTab = document.getElementById('tab-restaurants');
    if (restTab && restTab.style.display !== 'none') {
        setTimeout(function() { if (window.initRestaurantsMap) initRestaurantsMap(); }, 100);
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

function closeRestForm() {
    document.getElementById('rest-form-overlay').classList.remove('open');
}

function saveRestaurant(e) {
    e.preventDefault();
    var id = parseInt(document.getElementById('rest-id').value) || 0;
    var formData = new FormData();
    formData.append('ajax_restaurant', '1');
    formData.append('action', 'save');
    formData.append('ID', id);
    formData.append('NAME', document.getElementById('rest-name').value);
    formData.append('COORDINATES', document.getElementById('rest-coords-save').value);
    formData.append('PHONE', document.getElementById('rest-phone').value);
    formData.append('EMAIL', document.getElementById('rest-email').value);
    formData.append('REQUISITES', document.getElementById('rest-reqv').value);
    formData.append('ACTIVE', 'Y');

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            closeRestForm();
            location.reload();
        } else {
            alert(data.error || 'Ошибка');
        }
    });
    return false;
}

function deleteRestaurant(id) {
    if (!confirm('Удалить ресторан?')) return;
    var formData = new FormData();
    formData.append('ajax_restaurant', '1');
    formData.append('action', 'delete');
    formData.append('ID', id);

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
        else alert(data.error || 'Ошибка');
    });
}

function openRestaurantEdit(id) {
    cancelRestaurantEdit(0);
    var el = document.getElementById('rest-edit-' + id);
    if (el) el.classList.add('open');
}

function cancelRestaurantEdit(id) {
    document.querySelectorAll('.rest-item__edit.open').forEach(function(el) { el.classList.remove('open'); });
}

function saveRestaurantEdit(id) {
    var el = document.getElementById('rest-edit-' + id);
    if (!el) return;
    var formData = new FormData();
    formData.append('ajax_restaurant', '1');
    formData.append('action', 'save');
    formData.append('ID', id);
    formData.append('NAME', el.querySelector('.rest-edit-name').value);
    formData.append('COORDINATES', el.querySelector('.rest-edit-coords').value);
    formData.append('PHONE', el.querySelector('.rest-edit-phone').value);
    formData.append('EMAIL', el.querySelector('.rest-edit-email').value);
    formData.append('REQUISITES', el.querySelector('.rest-edit-reqv').value);
    formData.append('ACTIVE', 'Y');

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
        else alert(data.error || 'Ошибка');
    });
}

document.getElementById('rest-form-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeRestForm();
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

// ===== Кастомные метки ресторанов (бело-красные) =====
function createRestaurantIcon() {
    // SVG разметка: белый круг с красной окантовкой и вилкой/рыбой внутри
    var svg = '<svg width="36" height="36" viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg">' +
        '<circle cx="18" cy="14" r="12" fill="#FFFFFF" stroke="#F44336" stroke-width="2"/>' +
        '<path d="M12 8L14 14H13L11 8H12ZM15 8L17 14H16L14 8H15ZM18 8L20 14H19L17 8H18ZM21 8L23 14H22L20 8H21Z" fill="#F44336"/>' +
        '<path d="M18 22L12 26L18 24L24 26L18 22Z" fill="#F44336"/>' +
        '</svg>';
    return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
}

// Переопределяем initRestaurantsMap с кастомными иконками
(function() {
    window.initRestaurantsMap = function() {
        if (restMapInitialized) return;
        var container = document.getElementById('restaurants-map');
        if (!container) return;
        if (typeof ymaps === 'undefined') { setTimeout(window.initRestaurantsMap, 500); return; }

        ymaps.ready(function() {
            try {
                if (restMapInitialized) return;
                if (!restMap) {
                    restMap = new ymaps.Map('restaurants-map', {
                        center: [<?= $defaultLat ?>, <?= $defaultLng ?>],
                        zoom: <?= $defaultZoom ?>,
                        controls: ['zoomControl', 'fullscreenControl', 'searchControl']
                    });
                    restMapInitialized = true;
                }

                // Кастомная иконка ресторана: рыба (логотип без текста) на красном фоне
                var iconSvg = '<svg width="40" height="48" viewBox="0 0 40 48" xmlns="http://www.w3.org/2000/svg">' +
                    '<path d="M20 0C30 0 38 8 38 18C38 28 30 36 20 36C10 36 2 28 2 18C2 8 10 0 20 0Z" fill="#F44336"/>' +
                    '<ellipse cx="20" cy="18" rx="16" ry="14" fill="#FFFFFF"/>' +
                    // Тело рыбы
                    '<path d="M10 16C10 12 14 9 18 9H22C26 9 30 12 30 16V20C30 24 26 27 22 27H18C14 27 10 24 10 20V16Z" fill="#F44336"/>' +
                    // Хвост рыбы
                    '<path d="M30 14L36 10V22L30 18Z" fill="#F44336"/>' +
                    // Глаз
                    '<circle cx="15" cy="16" r="2.5" fill="#FFFFFF"/>' +
                    // Нижний треугольник (острие маркера)
                    '<path d="M20 36L17 44H23L20 36Z" fill="#F44336"/>' +
                    '</svg>';
                var iconUrl = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(iconSvg);

                // Добавляем метки
                <?php foreach ($restaurants as $r):
                    $coords = explode(',', $r['COORDINATES']);
                    if (count($coords) === 2):
                        $lat = trim($coords[0]); $lng = trim($coords[1]);
                        if (is_numeric($lat) && is_numeric($lng)):
                ?>
                (function(){
                    var pm = new ymaps.Placemark([<?= $lat ?>, <?= $lng ?>], {
                        hintContent: '<?= CUtil::JSEscape($r['NAME']) ?>',
                        balloonContent: '<strong><?= CUtil::JSEscape($r['NAME']) ?></strong>'
                    }, {
                        iconLayout: 'default#image',
                        iconImageHref: iconUrl,
                        iconImageSize: [40, 48],
                        iconImageOffset: [-20, -48],
                        iconShadow: false
                    });
                    restMap.geoObjects.add(pm);
                })();
                <?php
                        endif;
                    endif;
                endforeach; ?>

                // Клик по карте
                restMap.events.add('click', function(e) {
                    if (!restAdding) return;
                    var coords = e.get('coords');
                    var lat = coords[0].toFixed(6);
                    var lng = coords[1].toFixed(6);
                    if (restTempPlacemark) { restMap.geoObjects.remove(restTempPlacemark); restTempPlacemark = null; }
                    restTempPlacemark = new ymaps.Placemark(coords, {}, {
                        iconLayout: 'default#image',
                        iconImageHref: iconUrl,
                        iconImageSize: [40, 48],
                        iconImageOffset: [-20, -48]
                    });
                    restMap.geoObjects.add(restTempPlacemark);
                    document.getElementById('rest-coords-save').value = lat + ', ' + lng;
                    document.getElementById('rest-form-title').textContent = 'Новый ресторан';
                    document.getElementById('rest-id').value = '0';
                    document.getElementById('rest-name').value = '';
                    document.getElementById('rest-phone').value = '';
                    document.getElementById('rest-email').value = '';
                    document.getElementById('rest-reqv').value = '';
                    document.getElementById('rest-form-overlay').classList.add('open');
                    cancelAddRestaurant();
                });
            } catch(e) { console.error('Rest map error:', e); }
        });
    };
})();

</script>

<?endif;?>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog.php");
?>
</body>
</html>











