<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Ldo\Deliverymap\SettingsTable;
use Ldo\Deliverymap\RestaurantsTable;

global $USER;

$APPLICATION->SetTitle("Рестораны");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Управление доставкой", "/partners/delivery-zones/");
$APPLICATION->AddChainItem("Рестораны");

$request = Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

$siteId = 's1';
$moduleLoaded = Loader::includeModule('ldo.deliverymap');

// Читаем настройки карты из БД (редактирование — в /partners/settings/dostavka/)
$yandexApiKey  = $moduleLoaded ? SettingsTable::get($siteId, 'yandex_api_key', '') : '';
$defaultLat    = $moduleLoaded ? SettingsTable::get($siteId, 'default_lat', '54.7355') : '54.7355';
$defaultLng    = $moduleLoaded ? SettingsTable::get($siteId, 'default_lng', '55.9587') : '55.9587';
$defaultZoom   = $moduleLoaded ? SettingsTable::get($siteId, 'default_zoom', '11') : '11';

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

$partnersActivePage  = 'delivery-zones';
$partnersPageTitle   = 'Управление доставкой';
$partnersHeaderStyle = 'padding-bottom:0; border-bottom:none;';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
?>

        <!-- Подменю доставки -->
        <div class="p-dash-tabs">
            <a class="tab-btn" data-tab="zones" href="/partners/delivery-zones/">Зоны доставки</a>
            <a class="tab-btn active" data-tab="restaurants" href="/partners/delivery-zones/restorany/">Рестораны</a>
        </div>

        <div class="p-main">
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
    // --- Карта ресторанов (глобальные) ---
    window.restMapInitialized = false;
    window.restMap = null;
    window.restAdding = false;    // режим добавления
    window.restTempPlacemark = null;

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
})();

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

// ===== Карта ресторанов =====
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
                    if (!window.restAdding) return;
                    var coords = e.get('coords');
                    var lat = coords[0].toFixed(6);
                    var lng = coords[1].toFixed(6);
                    if (window.restTempPlacemark) { restMap.geoObjects.remove(window.restTempPlacemark); window.restTempPlacemark = null; }
                    window.restTempPlacemark = new ymaps.Placemark(coords, {}, {
                        iconLayout: 'default#image',
                        iconImageHref: iconUrl,
                        iconImageSize: [40, 48],
                        iconImageOffset: [-20, -48]
                    });
                    restMap.geoObjects.add(window.restTempPlacemark);
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

// Инициализация карты после загрузки
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() { if (window.initRestaurantsMap) initRestaurantsMap(); }, 100);
});
</script>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
