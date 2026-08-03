<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Ldo\Deliverymap\SettingsTable as DeliverySettingsTable;

global $USER;

$APPLICATION->SetTitle("Настройки доставки");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Настройки", "/partners/settings/");
$APPLICATION->AddChainItem("Доставка");

$request = Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

$siteId = 's1';
$deliveryModuleLoaded = Loader::includeModule('ldo.deliverymap');

// ============================================================
// Настройки доставки (карта)
// ============================================================
$yandexApiKey = $deliveryModuleLoaded ? DeliverySettingsTable::get($siteId, 'yandex_api_key', '') : '';
$defaultLat   = $deliveryModuleLoaded ? DeliverySettingsTable::get($siteId, 'default_lat', '54.7355') : '54.7355';
$defaultLng   = $deliveryModuleLoaded ? DeliverySettingsTable::get($siteId, 'default_lng', '55.9587') : '55.9587';
$defaultZoom  = $deliveryModuleLoaded ? DeliverySettingsTable::get($siteId, 'default_zoom', '11') : '11';

// --- Сохранение настроек доставки ---
if ($request->isPost() && $request->getPost('save_settings') === 'Y' && $deliveryModuleLoaded) {
    $key  = trim((string)$request->getPost('yandex_api_key'));
    $lat  = trim((string)$request->getPost('default_lat'));
    $lng  = trim((string)$request->getPost('default_lng'));
    $zoom = trim((string)$request->getPost('default_zoom'));

    DeliverySettingsTable::set($siteId, 'yandex_api_key', $key);
    DeliverySettingsTable::set($siteId, 'default_lat', $lat);
    DeliverySettingsTable::set($siteId, 'default_lng', $lng);
    DeliverySettingsTable::set($siteId, 'default_zoom', $zoom);

    LocalRedirect('/partners/settings/dostavka/?settings_saved=1');
}

$saved = $request->getQuery('settings_saved') === '1';

$partnersActivePage  = 'settings';
$partnersPageTitle   = 'Настройки';
$partnersHeaderStyle = 'padding-bottom:0; border-bottom:none;';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
?>

        <!-- Подменю настроек -->
        <div class="p-dash-tabs">
            <a class="tab-btn" data-tab="iiko" href="/partners/settings/">Синхронизация с iiko</a>
            <a class="tab-btn active" data-tab="delivery" href="/partners/settings/dostavka/">Доставка</a>
        </div>

        <div class="p-main">
            <div class="p-section">
                <div class="p-section__header">
                    <h2 class="p-section__title">Настройки доставки</h2>
                    <span style="font-size:13px; color:var(--color-muted);">Сайт: <?= htmlspecialchars($siteId) ?></span>
                </div>

                <?php if ($saved): ?>
                    <div class="settings-saved">✓ Настройки сохранены</div>
                <?php endif; ?>

                <?php if (!$deliveryModuleLoaded): ?>
                    <div style="background:rgba(231,76,60,.12); color:#e74c3c; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
                        Модуль ldo.deliverymap не подключён.
                    </div>
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
                    <div id="settings-map" style="width:100%; height:400px; border-radius:12px; overflow:hidden;"></div>
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

<?php if (!empty($yandexApiKey)): ?>
<script src="https://api-maps.yandex.ru/2.1/?apikey=<?= htmlspecialchars($yandexApiKey) ?>&lang=ru_RU"></script>
<?php endif; ?>

<script>
// Карта выбора центра доставки
var settingsMapInitialized = false;

function initSettingsMap() {
    if (settingsMapInitialized) return;
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

            var map = new ymaps.Map('settings-map', {
                center: [lat, lng],
                zoom: zoom,
                controls: ['zoomControl', 'fullscreenControl', 'geolocationControl']
            });

            var placemark = new ymaps.Placemark([lat, lng], {}, {
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

            settingsMapInitialized = true;
        } catch (e) {
            console.error('Settings map init error:', e);
        }
    });
}

function updateCoords(lat, lng) {
    document.getElementById('settings_lat').value = lat.toFixed(6);
    document.getElementById('settings_lng').value = lng.toFixed(6);
}

initSettingsMap();
</script>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
