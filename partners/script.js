/* ============================================
   Partners Dashboard — скрипты
   ============================================ */

// ====== Общие функции (partners/index.php) ======

// Показать/скрыть пароль
function togglePassword() {
    var pwd = document.getElementById('password');
    var icon = document.getElementById('eye-icon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.innerHTML = '<path d="M12 4.5C7 4.5 2.73 7.61 1 12C2.73 16.39 7 19.5 12 19.5C17 19.5 21.27 16.39 23 12C21.27 7.61 17 4.5 12 4.5ZM12 17C9.24 17 7 14.76 7 12C7 9.24 9.24 7 12 7C14.76 7 17 9.24 17 12C17 14.76 14.76 17 12 17ZM12 9C10.34 9 9 10.34 9 12C9 13.66 10.34 15 12 15C13.66 15 15 13.66 15 12C15 10.34 13.66 9 12 9Z" fill="currentColor"/>';
    } else {
        pwd.type = 'password';
        icon.innerHTML = '<path d="M12 4.5C7 4.5 2.73 7.61 1 12C2.73 16.39 7 19.5 12 19.5C17 19.5 21.27 16.39 23 12C21.27 7.61 17 4.5 12 4.5ZM12 17C9.24 17 7 14.76 7 12C7 9.24 9.24 7 12 7C14.76 7 17 9.24 17 12C17 14.76 14.76 17 12 17ZM12 9C10.34 9 9 10.34 9 12C9 13.66 10.34 15 12 15C13.66 15 15 13.66 15 12C15 10.34 13.66 9 12 9Z" fill="currentColor"/>';
    }
}

// Открыть/закрыть мобильное меню
function togglePartnersMenu() {
    var sidebar = document.getElementById('p-sidebar');
    var overlay = document.getElementById('p-overlay');
    if (sidebar) sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('open');
}

// ====== Функции импорта (partners/delivery-zones/index.php) ======

window.showImportDialog = function() {
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
        if (kmlContent.indexOf('<kml') === -1) {
            alert('Файл должен быть в формате KML');
            return;
        }
        var encoded = btoa(unescape(encodeURIComponent(kmlContent)));
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

// Клик по оверлею импорта
document.addEventListener('DOMContentLoaded', function() {
    var importDialog = document.getElementById('import-dialog');
    if (importDialog) {
        importDialog.addEventListener('click', function(e) {
            if (e.target === this) closeImportDialog();
        });
    }

    var restFormOverlay = document.getElementById('rest-form-overlay');
    if (restFormOverlay) {
        restFormOverlay.addEventListener('click', function(e) {
            if (e.target === this) closeRestForm();
        });
    }
});

// ====== Карта настроек (partners/delivery-zones/index.php) ======
(function() {
    var settingsMapInitialized = false;
    var settingsMap, settingsPlacemark;

    window.initSettingsMap = function() {
        if (settingsMapInitialized) return;
        var container = document.getElementById('settings-map');
        if (!container) return;
        if (typeof ymaps === 'undefined') {
            setTimeout(window.initSettingsMap, 500);
            return;
        }
        ymaps.ready(function() {
            try {
                var lat = parseFloat(document.getElementById('settings_lat').value) || 54.7355;
                var lng = parseFloat(document.getElementById('settings_lng').value) || 55.9587;
                var zoom = parseInt(document.getElementById('settings_zoom').value) || 11;

                settingsMap = new ymaps.Map('settings-map', {
                    center: [lat, lng],
                    zoom: zoom,
                    controls: ['zoomControl', 'fullscreenControl', 'geolocationControl']
                });

                settingsPlacemark = new ymaps.Placemark([lat, lng], {}, {
                    preset: 'islands#redDotIcon',
                    draggable: true
                });
                settingsMap.geoObjects.add(settingsPlacemark);

                settingsPlacemark.events.add('dragend', function() {
                    var coords = settingsPlacemark.geometry.getCoordinates();
                    updateCoords(coords[0], coords[1]);
                });

                settingsMap.events.add('click', function(e) {
                    var coords = e.get('coords');
                    settingsPlacemark.geometry.setCoordinates(coords);
                    updateCoords(coords[0], coords[1]);
                });

                settingsMapInitialized = true;
            } catch (e) {
                console.error('Settings map init error:', e);
            }
        });
    };

    function updateCoords(lat, lng) {
        document.getElementById('settings_lat').value = lat.toFixed(6);
        document.getElementById('settings_lng').value = lng.toFixed(6);
    }

    window.checkSettingsTab = function() {
        var tab = document.getElementById('tab-settings');
        if (tab && tab.style.display !== 'none' && tab.classList.contains('active')) {
            window.initSettingsMap();
        } else {
            setTimeout(window.checkSettingsTab, 300);
        }
    };
})();

// ====== Функции ресторанов и зон (partners/delivery-zones/index.php) ======

// --- Карта ресторанов (глобальные) ---
window.restMapInitialized = false;
window.restMap = null;
window.restAdding = false;
window.restTempPlacemark = null;

// --- Карта зон доставки (глобальные) ---
window.zonesMapInitialized = false;
window.zonesMap = null;
window.zoneDrawing = false;
window.zoneTempPoints = [];
window.zoneTempPolygon = null;
window.zoneTempPlacemarks = [];

window.finishZoneDrawing = function() {
    if (window.zoneTempPoints.length < 3) return;
    var coordsJson = JSON.stringify(window.zoneTempPoints);
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
};

window.cancelAddZone = function() {
    window.zoneDrawing = false;
    document.getElementById('btn-add-zone').style.display = 'inline-block';
    document.getElementById('btn-cancel-add-zone').style.display = 'none';
    document.getElementById('zone-add-hint').style.display = 'none';
    if (window.zoneTempPolygon) { window.zonesMap.geoObjects.remove(window.zoneTempPolygon); window.zoneTempPolygon = null; }
    window.zoneTempPlacemarks.forEach(function(pm) { window.zonesMap.geoObjects.remove(pm); });
    window.zoneTempPlacemarks = [];
    window.zoneTempPoints = [];
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
    fd.append('RESTAURANT_ID', document.getElementById('zone-form-restaurant').value);
    fd.append('ACTIVE', 'Y');
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
        else alert(data.error);
    });
};

window.startAddZone = function() {
    window.zoneDrawing = true;
    document.getElementById('btn-add-zone').style.display = 'none';
    document.getElementById('btn-cancel-add-zone').style.display = 'inline-block';
    document.getElementById('zone-add-hint').style.display = 'inline-flex';
};

// Режим добавления ресторана
window.startAddRestaurant = function() {
    window.restAdding = true;
    document.getElementById('btn-add-rest').style.display = 'none';
    document.getElementById('btn-cancel-add-rest').style.display = 'inline-block';
    document.getElementById('rest-add-hint').style.display = 'inline-flex';
};

window.cancelAddRestaurant = function() {
    window.restAdding = false;
    document.getElementById('btn-add-rest').style.display = 'inline-block';
    document.getElementById('btn-cancel-add-rest').style.display = 'none';
    document.getElementById('rest-add-hint').style.display = 'none';
    if (window.restTempPlacemark && window.restMap) {
        window.restMap.geoObjects.remove(window.restTempPlacemark);
        window.restTempPlacemark = null;
    }
};

window.closeRestForm = function() {
    document.getElementById('rest-form-overlay').classList.remove('open');
};

window.saveRestaurant = function(e) {
    e.preventDefault();
    var id = parseInt(document.getElementById('rest-id').value) || 0;
    var fd = new FormData();
    fd.append('ajax_rest', '1');
    fd.append('action', 'save');
    fd.append('ID', id);
    fd.append('NAME', document.getElementById('rest-name').value);
    fd.append('COORDINATES', document.getElementById('rest-coords-save').value);
    fd.append('PHONE', document.getElementById('rest-phone').value);
    fd.append('EMAIL', document.getElementById('rest-email').value);
    fd.append('REQUISITES', document.getElementById('rest-reqv').value);
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
        else alert(data.error);
    });
    return false;
};

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

window.deleteRestaurant = function(id) {
    if (!confirm('Удалить ресторан?')) return;
    var fd = new FormData();
    fd.append('ajax_rest', '1');
    fd.append('action', 'delete');
    fd.append('ID', id);
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
        else alert(data.error);
    });
};

window.openRestEdit = function(id) {
    cancelRestEdit(0);
    var el = document.getElementById('rest-edit-' + id);
    if (el) el.classList.add('open');
};

window.cancelRestEdit = function(id) {
    document.querySelectorAll('.rest-item__edit.open').forEach(function(el) { el.classList.remove('open'); });
};

window.saveRestEdit = function(id) {
    var el = document.getElementById('rest-edit-' + id);
    if (!el) return;
    var fd = new FormData();
    fd.append('ajax_rest', '1');
    fd.append('action', 'save');
    fd.append('ID', id);
    fd.append('NAME', el.querySelector('.rest-edit-name').value);
    fd.append('COORDINATES', el.querySelector('.rest-edit-coords').value);
    fd.append('PHONE', el.querySelector('.rest-edit-phone').value);
    fd.append('EMAIL', el.querySelector('.rest-edit-email').value);
    fd.append('REQUISITES', el.querySelector('.rest-edit-reqv').value);
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) location.reload();
        else alert(data.error);
    });
};

// --- Управление зонами ---
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
    .then(function(data) {
        if (data.success) location.reload();
        else alert(data.error);
    });
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

// ====== Табы дашборда ======
document.addEventListener('DOMContentLoaded', function() {
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

            if (tabName === 'zones' && typeof window.initZonesMap === 'function') {
                window.initZonesMap();
            }
            if (tabName === 'restaurants' && typeof window.initRestaurantsMap === 'function') {
                window.initRestaurantsMap();
            }
            if (tabName === 'settings' && typeof window.checkSettingsTab === 'function') {
                window.checkSettingsTab();
            }
        });
    });
});

// Инициализация карт после загрузки всех скриптов
document.addEventListener('DOMContentLoaded', function() {
    var zonesTab = document.getElementById('tab-zones');
    if (zonesTab && zonesTab.style.display !== 'none') {
        setTimeout(function() { if (typeof window.initZonesMap === 'function') window.initZonesMap(); }, 100);
    }
    var restTab = document.getElementById('tab-restaurants');
    if (restTab && restTab.style.display !== 'none') {
        setTimeout(function() { if (typeof window.initRestaurantsMap === 'function') window.initRestaurantsMap(); }, 100);
    }
});

// ====== Кастомный селект ======
function toggleCustomSelect(trigger) {
    var wrapper = trigger.closest('.custom-select');
    if (!wrapper) return;
    var isOpen = wrapper.classList.contains('open');
    document.querySelectorAll('.custom-select.open').forEach(function(s) { s.classList.remove('open'); });
    if (!isOpen) wrapper.classList.add('open');
}

function selectCustomOption(el) {
    var wrapper = el.closest('.custom-select');
    if (!wrapper) return;
    wrapper.querySelectorAll('.custom-select__option').forEach(function(o) { o.classList.remove('selected'); });
    el.classList.add('selected');
    var trigger = wrapper.querySelector('.custom-select__trigger .placeholder');
    if (trigger) trigger.textContent = el.textContent;
    var hiddenId = wrapper.dataset.hidden;
    if (hiddenId) {
        var hidden = document.getElementById(hiddenId);
        if (hidden) hidden.value = el.dataset.value;
    }
    wrapper.classList.remove('open');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.custom-select')) {
        document.querySelectorAll('.custom-select.open').forEach(function(s) { s.classList.remove('open'); });
    }
});

// ====== Toggle активности ======
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('zone-edit-active')) {
        var label = e.target.closest('.toggle-switch');
        if (label) {
            var labelText = label.querySelector('.toggle-switch__label');
            if (labelText) labelText.textContent = e.target.checked ? 'Активна' : 'Неактивна';
        }
    }
});

// ====== Кастомная иконка ресторана ======
function createRestaurantIcon() {
    var svg = '<svg width="36" height="36" viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg">' +
        '<circle cx="18" cy="14" r="12" fill="#FFFFFF" stroke="#F44336" stroke-width="2"/>' +
        '<path d="M12 8L14 14H13L11 8H12ZM15 8L17 14H16L14 8H15ZM18 8L20 14H19L17 8H18ZM21 8L23 14H22L20 8H21Z" fill="#F44336"/>' +
        '<path d="M18 22L12 26L18 24L24 26L18 22Z" fill="#F44336"/>' +
        '</svg>';
    return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
}

// ====== Зоны доставки: редактирование на карте ======
var zonePolygons = {};

window.selectZoneOnMap = function(zoneId) {
    deselectZoneOnMap();
    var entry = zonePolygons[zoneId];
    if (!entry || !window.zonesMap) return;
    entry.polygon.options.set('fillColor', entry.color + '80');
    entry.polygon.options.set('strokeWidth', 4);
    window.zonesMap.setBounds(entry.polygon.geometry.getBounds(), { checkZoomRange: true, zoomMargin: 3 });
};

function deselectZoneOnMap() {
    for (var key in zonePolygons) {
        if (zonePolygons.hasOwnProperty(key)) {
            zonePolygons[key].polygon.options.set('fillColor', zonePolygons[key].color + '33');
            zonePolygons[key].polygon.options.set('strokeWidth', 3);
        }
    }
}

window.highlightZoneInList = function(id) {
    // Сбросить подсветку всех элементов списка
    document.querySelectorAll('#zones-list .rest-item').forEach(function(item) {
        item.style.removeProperty('background');
        item.style.removeProperty('box-shadow');
    });
    // Подсветить нужный элемент
    var el = document.querySelector('#zones-list .rest-item[data-id="' + id + '"]');
    if (el) {
        el.style.background = 'rgba(244, 67, 54, 0.12)';
        el.style.boxShadow = 'inset 0 0 0 2px rgba(244, 67, 54, 0.4)';
        // Прокрутить к элементу в списке
        var list = document.getElementById('zones-list');
        if (list) {
            var elRect = el.getBoundingClientRect();
            var listRect = list.getBoundingClientRect();
            if (elRect.top < listRect.top || elRect.bottom > listRect.bottom) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }
};

// ====== Редактирование зоны на карте (перетаскивание точек) ======
var editingZoneId = null;
var editOverlayPolygon = null;

// ====== Сохранение зоны с карты ======
window.saveZoneMapEdit = function(id) {
    var el = document.getElementById('zone-edit-' + id);
    if (!el) return;
    var fd = new FormData();
    fd.append('ajax_zone', '1');
    fd.append('action', 'save');
    fd.append('ID', id);
    fd.append('NAME', el.querySelector('.zone-edit-name').value);
    fd.append('COORDINATES', JSON.stringify(window.zonePolygons[id]?.coords || []));
    fd.append('PRICE', el.querySelector('.zone-edit-price').value);
    fd.append('FREE_FROM', el.querySelector('.zone-edit-free').value);
    fd.append('DELIVERY_TIME_START', el.querySelector('.zone-edit-tstart').value);
    fd.append('DELIVERY_TIME_END', el.querySelector('.zone-edit-tend').value);
    fd.append('MIN_ORDER_PRICE', el.querySelector('.zone-edit-min').value);
    fd.append('COLOR', el.querySelector('.zone-edit-color').value);
    fd.append('ACTIVE', el.querySelector('.zone-edit-active').value);
    fd.append('RESTAURANT_ID', el.querySelector('.zone-edit-restaurant').value);

    try {
        var polygonEntry = window.zonePolygons[id];
        if (polygonEntry && polygonEntry.polygon) {
            var geom = polygonEntry.polygon.geometry;
            if (geom) {
                var newCoords = geom.getCoordinates();
                fd.set('COORDINATES', JSON.stringify(newCoords[0] || newCoords));
            }
        }
    } catch(e) {
        fd.set('COORDINATES', '[]');
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
