/**
 * Менеджер зон доставки
 */
class DeliveryZonesManager {
    constructor() {
        console.log('DeliveryZonesManager constructor started');

        this.map = null;
        this.polygons = [];
        this.labels = [];
        this.zonesLoaded = false;

        this.modes = {
            VIEW: 'view',
            DRAW: 'draw',
            EDIT: 'edit'
        };
        this.currentMode = this.modes.VIEW;

        this.tempPoints = [];
        this.tempPolygon = null;
        this.tempPlacemarks = [];

        this.editPolygon = null;
        this.originalCoordinates = [];
        this.autoSaveTimer = null;

        this.selectedPolygon = null;
        this.selectedZoneId = null;
        this.currentZoneData = null;

        const mapData = document.getElementById('map-data');
        this.defaultLat = parseFloat(mapData.dataset.defaultLat) || 55.751574;
        this.defaultLng = parseFloat(mapData.dataset.defaultLng) || 37.573856;
        this.defaultZoom = parseInt(mapData.dataset.defaultZoom) || 10;
        this.apiKey = mapData.dataset.apiKey || '';

        this.init();
    }

    init() {
        if (typeof ymaps === 'undefined') {
            setTimeout(() => this.init(), 500);
            return;
        }

        ymaps.ready(() => {
            try {
                this.initMap();
                this.loadZones();
                this.bindEvents();
                this.initHighLoadFeature();
            } catch (error) {
                console.error('Init error:', error);
                this.showNotification('Ошибка инициализации карты', 'error');
            }
        });
    }

    initMap() {
        this.map = new ymaps.Map('delivery-map', {
            center: [this.defaultLat, this.defaultLng],
            zoom: this.defaultZoom,
            controls: ['zoomControl', 'fullscreenControl', 'geolocationControl']
        });

        this.map.events.add('click', (e) => {
            const target = e.get('target');
            if (!target || !target.geometry || !target.properties) {
                this.deselectZone();
            }
        });

        this.map.events.add('error', (error) => {
            console.error('Map error:', error);
            this.showNotification('Ошибка работы карты', 'error');
        });
    }

    bindEvents() {
        const zoneForm = document.getElementById('zoneForm');
        if (zoneForm) {
            zoneForm.addEventListener('submit', (e) => this.saveZone(e));
        }

        const cancelBtn = document.getElementById('cancel_btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.cancelForm());
        }

        const addBtn = document.getElementById('addZoneBtn');
        if (addBtn) {
            addBtn.addEventListener('click', () => this.showAddForm());
        }

        const saveBtn = document.getElementById('save_btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.currentMode === this.modes.DRAW) {
                    this.finishDrawing();
                }
                const form = document.getElementById('zoneForm');
                if (form) {
                    form.dispatchEvent(new Event('submit'));
                }
            });
        }

        const formInputs = document.querySelectorAll('#zoneForm input');
        formInputs.forEach(input => {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && this.currentMode !== this.modes.DRAW) {
                    e.preventDefault();
                    const saveBtn = document.getElementById('save_btn');
                    if (saveBtn && saveBtn.style.display !== 'none') {
                        saveBtn.click();
                    }
                }
            });
        });
    }

    showLoading(show) {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            if (show) {
                overlay.classList.add('active');
            } else {
                overlay.classList.remove('active');
            }
        }
    }

    showAddForm() {
        console.log('showAddForm called');

        this.closeAllAccordions();

        const wrapper = document.getElementById('zoneFormWrapper');
        if (wrapper) {
            wrapper.classList.add('show');
        }

        document.getElementById('formTitle').textContent = 'Новая зона';
        document.getElementById('zone_id').value = 0;
        document.getElementById('zone_name').value = '';
        document.getElementById('zone_price').value = 0;
        document.getElementById('zone_free_from').value = 0;
        document.getElementById('zone_delivery_time_start').value = '';
        document.getElementById('zone_delivery_time_end').value = '';
        document.getElementById('zone_min_price').value = 0;
        document.getElementById('zone_active').value = 'Y';
        document.getElementById('zone_color').value = '#00FF00';
        document.getElementById('zone_coordinates').value = '';

        document.getElementById('cancel_btn').style.display = 'inline-block';
        document.getElementById('save_btn').style.display = 'inline-block';

        this.exitAllModes();
        this.deselectZone();
        document.getElementById('zone-list').style.display = 'none';

        setTimeout(() => {
            this.startDrawing();
        }, 300);

        if (wrapper) {
            wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    cancelForm() {
        console.log('cancelForm called');

        const wrapper = document.getElementById('zoneFormWrapper');
        if (wrapper) {
            wrapper.classList.remove('show');
        }

        document.getElementById('zoneForm').reset();
        document.getElementById('zone_coordinates').value = '';
        document.getElementById('cancel_btn').style.display = 'none';
        document.getElementById('save_btn').style.display = 'none';

        if (this.polygons.length > 0) {
            document.getElementById('zone-list').style.display = 'block';
        }

        this.exitAllModes();
        this.deselectZone();
        this.showNotification('Добавление отменено', 'info');
    }

    closeAllAccordions() {
        document.querySelectorAll('.zone-accordion-body').forEach(el => {
            el.classList.remove('open');
        });
        document.querySelectorAll('.zone-accordion-item').forEach(el => {
            el.classList.remove('active');
        });
    }

    exitAllModes() {
        if (this.currentMode === this.modes.DRAW) {
            this.cancelDrawing();
        }
        if (this.currentMode === this.modes.EDIT) {
            this.exitEditMode();
        }
        this.currentMode = this.modes.VIEW;
    }

    loadZones() {
        console.log('loadZones called');
        this.showLoading(true);

        const formData = new FormData();
        formData.append('ajax_action', 'get_zones');

        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                this.showLoading(false);

                if (data.success && data.zones) {
                    console.log(`Zones loaded: ${data.zones.length}`);

                    data.zones.forEach(zone => {
                        this.addPolygonToMap(zone);
                    });

                    this.zonesLoaded = true;
                    this.updateZoneList(data.zones);

                    if (data.zones.length > 0) {
                        document.getElementById('zone-list').style.display = 'block';
                    }
                } else if (data.error) {
                    this.showNotification('Ошибка: ' + data.error, 'error');
                }
            })
            .catch(error => {
                this.showLoading(false);
                console.error('Ошибка загрузки зон:', error);
                this.showNotification('Ошибка загрузки зон: ' + error.message, 'error');
            });
    }

    addPolygonToMap(zone) {
        if (!zone.COORDINATES || zone.COORDINATES.length < 3) {
            console.warn('Zone has invalid coordinates:', zone.ID);
            return;
        }

        try {
            const center = this.getPolygonCenter(zone.COORDINATES);
            const zoneId = parseInt(zone.ID);
            const zoneData = zone;

            const polygon = new ymaps.Polygon([zone.COORDINATES], {
                hintContent: zone.NAME,
                zoneId: zoneId
            }, {
                fillColor: this.hexToRgba(zone.COLOR, 0.5),
                strokeColor: zone.COLOR,
                strokeWidth: 3,
                fillOpacity: 0.5,
                strokeStyle: 'solid',
                interactive: true
            });

            const label = new ymaps.Placemark(center, {
                iconContent: zone.NAME
            }, {
                preset: 'islands#circleIcon',
                iconColor: zone.COLOR,
                iconContentLayout: ymaps.templateLayoutFactory.createClass(
                    `<div class="zone-label" style="border-color: ${zone.COLOR};">$[properties.iconContent]</div>`
                )
            });

            const clickHandler = (e) => {
                e.stopPropagation();
                this.selectZone(polygon, zoneData);
                this.toggleAccordion(zoneId);
            };

            label.events.add('click', clickHandler);
            polygon.events.add('click', clickHandler);

            this.map.geoObjects.add(polygon);
            this.map.geoObjects.add(label);

            this.polygons.push({ polygon, zone });
            this.labels.push({ label, zoneId: zoneId });

            console.log('Zone added to map:', zoneId, zone.NAME);

        } catch (e) {
            console.error('Ошибка добавления зоны:', e, zone);
        }
    }

    hexToRgba(hex, alpha) {
        let r = parseInt(hex.slice(1, 3), 16);
        let g = parseInt(hex.slice(3, 5), 16);
        let b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    getPolygonCenter(coordinates) {
        if (!coordinates || coordinates.length < 3) return [0, 0];

        let lat = 0;
        let lng = 0;
        const count = coordinates.length;

        coordinates.forEach(coord => {
            lat += coord[0];
            lng += coord[1];
        });

        return [lat / count, lng / count];
    }

    selectZone(polygon, zone) {
        if (this.currentMode === this.modes.EDIT) return;

        this.deselectZone();

        this.selectedPolygon = polygon;
        this.selectedZoneId = parseInt(zone.ID);
        this.currentZoneData = zone;

        polygon.options.set({
            fillOpacity: 0.8,
            strokeWidth: 5
        });

        const labelObj = this.labels.find(l => parseInt(l.zoneId) === parseInt(zone.ID));
        if (labelObj) {
            labelObj.label.options.set('iconContentOffset', [0, -5]);
            const labelElement = labelObj.label._domElement;
            if (labelElement) {
                labelElement.classList.add('active');
            }
        }

        this.highlightZoneInList(parseInt(zone.ID));
    }

    deselectZone() {
        if (this.selectedPolygon) {
            this.selectedPolygon.options.set({
                fillOpacity: 0.5,
                strokeWidth: 3
            });
            this.selectedPolygon = null;
        }

        if (this.selectedZoneId) {
            const labelObj = this.labels.find(l => parseInt(l.zoneId) === parseInt(this.selectedZoneId));
            if (labelObj) {
                labelObj.label.options.set('iconContentOffset', [0, 0]);
                const labelElement = labelObj.label._domElement;
                if (labelElement) {
                    labelElement.classList.remove('active');
                }
            }
        }

        this.selectedZoneId = null;
        this.currentZoneData = null;
        this.highlightZoneInList(null);
    }

    updateZoneList(zones) {
        const container = document.getElementById('zone-list-items');
        const listBlock = document.getElementById('zone-list');

        if (!zones || zones.length === 0) {
            listBlock.style.display = 'none';
            return;
        }

        listBlock.style.display = 'block';

        let html = '<div class="zone-accordion">';

        zones.forEach((zone) => {
            const isActive = zone.ACTIVE === 'Y';
            const statusClass = isActive ? '' : 'inactive';
            const statusText = isActive ? 'активна' : 'неактивна';
            const freeText = zone.FREE_FROM > 0 ? this.formatPrice(zone.FREE_FROM) + ' руб.' : 'нет';
            const minOrderText = zone.MIN_ORDER_PRICE > 0 ? this.formatPrice(zone.MIN_ORDER_PRICE) + ' руб.' : 'нет';

            let deliveryTimeText = 'не указано';
            const timeStart = parseInt(zone.DELIVERY_TIME_START) || 0;
            const timeEnd = parseInt(zone.DELIVERY_TIME_END) || 0;

            if (timeStart > 0 && timeEnd > 0) {
                deliveryTimeText = `от ${timeStart} до ${timeEnd} мин.`;
            } else if (timeStart > 0) {
                deliveryTimeText = `от ${timeStart} мин.`;
            } else if (timeEnd > 0) {
                deliveryTimeText = `до ${timeEnd} мин.`;
            }

            const zoneId = parseInt(zone.ID);

            html += `
                <div class="zone-accordion-item" data-zone-id="${zoneId}">
                    <div class="zone-accordion-header" onclick="deliveryManager.toggleAccordion(${zoneId})">
                        <div class="zone-header-left">
                            <span class="zone-color-circle" style="background: ${zone.COLOR};"></span>
                            <span class="zone-name-text ${statusClass}">${this.escapeHtml(zone.NAME)}</span>
                        </div>
                        <div class="zone-header-right">
                            <span class="zone-status-badge ${statusClass}">${statusText}</span>
                            <span class="zone-accordion-icon">▼</span>
                        </div>
                    </div>
                    <div class="zone-accordion-body" id="accordion-body-${zoneId}">
                        <div class="zone-accordion-content">
                            <div class="zone-detail-row">
                                <span class="label">💰 Цена доставки:</span>
                                <span class="value">${this.formatPrice(zone.PRICE)} руб.</span>
                            </div>
                            <div class="zone-detail-row">
                                <span class="label">🎁 Бесплатно от:</span>
                                <span class="value">${freeText}</span>
                            </div>
                            <div class="zone-detail-row">
                                <span class="label">⏱ Время доставки:</span>
                                <span class="value">${deliveryTimeText}</span>
                            </div>
                            <div class="zone-detail-row">
                                <span class="label">🛒 Мин. заказ:</span>
                                <span class="value">${minOrderText}</span>
                            </div>
                            <div class="zone-actions">
                                <button class="adm-btn" onclick="deliveryManager.editZoneFromAccordion(${zoneId})">✏️ Редактировать</button>
                                <button class="adm-btn button-danger" onclick="deliveryManager.deleteZoneFromAccordion(${zoneId})">🗑️ Удалить</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;
        console.log('Zone list updated, zones count:', zones.length);
    }

    initHighLoadFeature() {
        console.log('initHighLoadFeature called');

        const toggle = document.getElementById('highLoadToggle');
        const settings = document.getElementById('highLoadSettings');
        const saveBtn = document.getElementById('saveHighLoadBtn');
        const minutesInput = document.getElementById('highLoadMinutes');
        const status = document.getElementById('highLoadStatus');

        if (!toggle) {
            console.error('highLoadToggle not found');
            return;
        }

        if (!settings) {
            console.error('highLoadSettings not found');
            return;
        }

        console.log('High load elements found, initializing...');

        toggle.addEventListener('change', (e) => {
            const isChecked = e.target.checked;
            console.log('Toggle changed:', isChecked);

            if (isChecked) {
                settings.classList.add('is-visible');
            } else {
                settings.classList.remove('is-visible');
            }

            const mapContainer = document.getElementById('delivery-map');
            if (mapContainer) {
                if (isChecked) {
                    mapContainer.classList.add('high-load-active');
                } else {
                    mapContainer.classList.remove('high-load-active');
                }
            }
        });

        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                console.log('Save button clicked');

                const minutes = parseInt(minutesInput.value) || 60;
                if (minutes < 1 || minutes > 1440) {
                    this.showNotification('Введите значение от 1 до 1440 минут (24 часа)', 'error');
                    return;
                }

                status.classList.remove('status-hidden');
                status.classList.add('status-visible');

                let timeText = '';
                if (minutes >= 60) {
                    const hours = Math.floor(minutes / 60);
                    const mins = minutes % 60;
                    timeText = mins > 0 ? `${hours} ч ${mins} мин` : `${hours} ч`;
                } else {
                    timeText = `${minutes} мин`;
                }

                status.textContent = `✓ Сохранено (${timeText})`;

                setTimeout(() => {
                    status.classList.remove('status-visible');
                    status.classList.add('status-hidden');
                }, 3000);

                this.showNotification(`Время доставки установлено: ${timeText}`, 'success');
            });
        }

        if (minutesInput) {
            minutesInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (saveBtn) saveBtn.click();
                }
            });

            minutesInput.addEventListener('change', () => {
                let val = parseInt(minutesInput.value) || 60;
                if (val < 1) minutesInput.value = 1;
                if (val > 1440) minutesInput.value = 1440;
            });
        }
    }

    toggleAccordion(zoneId) {
        zoneId = parseInt(zoneId);
        console.log('toggleAccordion called for zone:', zoneId);

        const item = document.querySelector(`.zone-accordion-item[data-zone-id="${zoneId}"]`);
        if (!item) {
            console.error('Accordion item not found for zone:', zoneId);
            return;
        }

        const body = document.getElementById(`accordion-body-${zoneId}`);
        const isOpen = body.classList.contains('open');

        document.querySelectorAll('.zone-accordion-body').forEach(el => {
            el.classList.remove('open');
        });
        document.querySelectorAll('.zone-accordion-item').forEach(el => {
            el.classList.remove('active');
        });

        if (!isOpen) {
            body.classList.add('open');
            item.classList.add('active');

            const zone = this.polygons.find(p => parseInt(p.zone.ID) === zoneId);
            if (zone) {
                this.selectZone(zone.polygon, zone.zone);
                try {
                    this.map.setBounds(zone.polygon.geometry.getBounds(), {
                        checkZoomRange: true,
                        zoomMargin: 50
                    });
                } catch (e) {
                    console.warn('Не удалось установить границы', e);
                }
            }
        } else {
            this.deselectZone();
        }
    }

    editZoneFromAccordion(zoneId) {
        zoneId = parseInt(zoneId);
        console.log('editZoneFromAccordion called for zone:', zoneId);

        const zone = this.polygons.find(p => parseInt(p.zone.ID) === zoneId);
        if (zone) {
            const wrapper = document.getElementById('zoneFormWrapper');
            if (wrapper) {
                wrapper.classList.remove('show');
            }

            const body = document.getElementById(`accordion-body-${zoneId}`);
            if (body && !body.classList.contains('open')) {
                this.toggleAccordion(zoneId);
            }

            this.selectZone(zone.polygon, zone.zone);
            this.startEditMode(zone.zone);
        } else {
            console.error('Zone not found for editing:', zoneId);
            this.showNotification('Зона не найдена. Попробуйте обновить страницу.', 'error');
        }
    }

    deleteZoneFromAccordion(zoneId) {
        zoneId = parseInt(zoneId);
        console.log('deleteZoneFromAccordion called for zone:', zoneId);

        const zone = this.polygons.find(p => parseInt(p.zone.ID) === zoneId);
        if (zone) {
            this.selectZone(zone.polygon, zone.zone);
            this.deleteSelectedZone();
        } else {
            console.error('Zone not found for deletion:', zoneId);
            this.showNotification('Зона не найдена', 'error');
        }
    }

    highlightZoneInList(zoneId) {
        document.querySelectorAll('.zone-accordion-item').forEach(el => {
            const isActive = parseInt(el.dataset.zoneId) === zoneId;
            el.classList.toggle('active', isActive);

            const body = document.getElementById(`accordion-body-${el.dataset.zoneId}`);
            if (body) {
                body.classList.toggle('open', isActive);
            }
        });
    }

    startEditMode(zone) {
        console.log('startEditMode called for zone:', zone.ID);

        if (this.currentMode === this.modes.EDIT) {
            this.exitEditMode();
        }

        this.currentMode = this.modes.EDIT;
        this.currentZoneData = zone;
        this.originalCoordinates = JSON.parse(JSON.stringify(zone.COORDINATES));

        const found = this.polygons.find(p => parseInt(p.zone.ID) === parseInt(zone.ID));
        if (!found) {
            console.error('Polygon not found for zone:', zone.ID);
            return;
        }

        const polygon = found.polygon;
        const coords = polygon.geometry.getCoordinates()[0];

        this.map.geoObjects.remove(polygon);

        this.editPolygon = new ymaps.Polygon([coords], {
            hintContent: 'Редактирование: ' + zone.NAME
        }, {
            fillColor: this.hexToRgba(zone.COLOR, 0.5),
            strokeColor: zone.COLOR,
            strokeWidth: 3,
            fillOpacity: 0.5,
            strokeStyle: 'solid',
            interactive: true,
            draggable: false,
            editor: {
                options: {
                    drawing: true,
                    maxPoints: 0
                }
            }
        });

        this.map.geoObjects.add(this.editPolygon);
        this.editPolygon.editor.startEditing();

        this.editPolygon.events.add('geometrychange', () => {
            const newCoords = this.editPolygon.geometry.getCoordinates()[0];
            document.getElementById('zone_coordinates').value = JSON.stringify(newCoords);
        });

        const wrapper = document.getElementById('zoneFormWrapper');
        if (wrapper) {
            wrapper.classList.add('show');
        }

        document.getElementById('formTitle').textContent = 'Редактирование зоны';
        document.getElementById('zone_id').value = zone.ID;
        document.getElementById('zone_name').value = zone.NAME;
        document.getElementById('zone_price').value = zone.PRICE;
        document.getElementById('zone_free_from').value = zone.FREE_FROM || 0;
        document.getElementById('zone_delivery_time_start').value = zone.DELIVERY_TIME_START || 0;
        document.getElementById('zone_delivery_time_end').value = zone.DELIVERY_TIME_END || 0;
        document.getElementById('zone_color').value = zone.COLOR;
        document.getElementById('zone_min_price').value = zone.MIN_ORDER_PRICE;
        document.getElementById('zone_active').value = zone.ACTIVE;
        document.getElementById('zone_coordinates').value = JSON.stringify(coords);

        document.getElementById('cancel_btn').style.display = 'inline-block';
        document.getElementById('save_btn').style.display = 'inline-block';

        this.closeAllAccordions();
        this.showNotification('Режим редактирования: перетаскивайте точки зоны', 'info');
    }

    exitEditMode() {
        console.log('exitEditMode called');

        if (this.editPolygon) {
            this.editPolygon.editor.stopEditing();
            this.map.geoObjects.remove(this.editPolygon);
            this.editPolygon = null;

            if (this.currentZoneData) {
                const index = this.polygons.findIndex(p => parseInt(p.zone.ID) === parseInt(this.currentZoneData.ID));
                if (index !== -1) {
                    this.polygons.splice(index, 1);
                }
                this.addPolygonToMap(this.currentZoneData);
            }
        }

        this.currentMode = this.modes.VIEW;
        document.getElementById('editModeHint').classList.remove('show');

        const wrapper = document.getElementById('zoneFormWrapper');
        if (wrapper) {
            wrapper.classList.remove('show');
        }
        this.currentZoneData = null;
    }

    deleteSelectedZone() {
        console.log('deleteSelectedZone called');

        if (!this.currentZoneData) {
            this.showNotification('Выберите зону для удаления', 'warning');
            return;
        }

        if (!confirm(`Удалить зону "${this.currentZoneData.NAME}"?`)) {
            return;
        }

        this.showLoading(true);

        const formData = new FormData();
        formData.append('ajax_action', 'delete_zone');
        formData.append('ID', this.currentZoneData.ID);
        formData.append('sessid', BX.bitrix_sessid());

        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                this.showLoading(false);
                if (data.success) {
                    this.showNotification('Зона удалена', 'success');
                    setTimeout(() => location.reload(), 500);
                } else {
                    this.showNotification('Ошибка удаления: ' + (data.error || 'Неизвестная ошибка'), 'error');
                }
            })
            .catch(error => {
                this.showLoading(false);
                this.showNotification('Ошибка: ' + error.message, 'error');
            });
    }

    saveZone(e) {
        console.log('saveZone called');
        e.preventDefault();

        if (this.currentMode === this.modes.DRAW) {
            this.finishDrawing();
        }

        const coords = document.getElementById('zone_coordinates').value;
        if (!coords) {
            this.showNotification('Сначала нарисуйте зону на карте!', 'warning');
            return;
        }

        try {
            const parsed = JSON.parse(coords);
            if (!Array.isArray(parsed) || parsed.length < 3) {
                this.showNotification('Зона должна содержать минимум 3 точки', 'warning');
                return;
            }
        } catch (e) {
            this.showNotification('Ошибка в координатах зоны', 'error');
            return;
        }

        const formData = new FormData(document.getElementById('zoneForm'));

        const timeStart = parseInt(document.getElementById('zone_delivery_time_start').value) || 0;
        const timeEnd = parseInt(document.getElementById('zone_delivery_time_end').value) || 0;

        formData.append('DELIVERY_TIME_START', timeStart);
        formData.append('DELIVERY_TIME_END', timeEnd);
        formData.append('ajax_action', 'save_zone');

        this.showLoading(true);

        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                this.showLoading(false);
                if (data.success) {
                    this.showNotification('Зона сохранена', 'success');
                    if (this.currentMode === this.modes.EDIT) {
                        this.exitEditMode();
                    }
                    setTimeout(() => location.reload(), 500);
                } else {
                    this.showNotification('Ошибка сохранения: ' + (data.error || 'Неизвестная ошибка'), 'error');
                }
            })
            .catch(error => {
                this.showLoading(false);
                this.showNotification('Ошибка: ' + error.message, 'error');
            });
    }

    startDrawing() {
        console.log('startDrawing called');

        if (this.currentMode === this.modes.EDIT) {
            this.showNotification('Сначала завершите редактирование', 'warning');
            return;
        }

        if (this.currentMode === this.modes.DRAW) {
            return;
        }

        this.currentMode = this.modes.DRAW;
        document.getElementById('draw-hint').style.display = 'block';

        document.getElementById('zone_coordinates').value = '';
        this.tempPoints = [];

        const color = document.getElementById('zone_color').value;
        this.tempPolygon = new ymaps.Polygon([[]], {
            hintContent: 'Рисование... кликните для добавления точек'
        }, {
            fillColor: this.hexToRgba(color, 0.3),
            strokeColor: color,
            strokeWidth: 2,
            fillOpacity: 0.3,
            interactive: false
        });
        this.map.geoObjects.add(this.tempPolygon);

        if (this.mapClickHandler) {
            this.map.events.remove('click', this.mapClickHandler);
        }

        this.mapClickHandler = (e) => this.onMapClick(e);
        this.map.events.add('click', this.mapClickHandler);

        this.setCursor('crosshair');

        const pointsSpan = document.getElementById('pointsCount');
        if (pointsSpan) {
            pointsSpan.textContent = '0';
        }
    }

    onMapClick(e) {
        if (this.currentMode !== this.modes.DRAW) return;

        const coords = e.get('coords');
        this.tempPoints.push(coords);

        const placemark = new ymaps.Placemark(coords, {}, {
            preset: 'islands#redDotIcon',
            draggable: false
        });
        this.map.geoObjects.add(placemark);
        this.tempPlacemarks.push(placemark);

        this.tempPolygon.geometry.setCoordinates([this.tempPoints]);

        const pointsSpan = document.getElementById('pointsCount');
        if (pointsSpan) {
            pointsSpan.textContent = this.tempPoints.length;
        }
    }

    finishDrawing() {
        console.log('finishDrawing called');

        if (this.currentMode !== this.modes.DRAW) return;

        this.currentMode = this.modes.VIEW;
        document.getElementById('draw-hint').style.display = 'none';

        if (this.mapClickHandler) {
            this.map.events.remove('click', this.mapClickHandler);
            this.mapClickHandler = null;
        }

        this.tempPlacemarks.forEach(pm => this.map.geoObjects.remove(pm));
        this.tempPlacemarks = [];

        this.setCursor('default');

        if (this.tempPoints.length >= 3) {
            const finalCoords = [...this.tempPoints, this.tempPoints[0]];
            document.getElementById('zone_coordinates').value = JSON.stringify(finalCoords);

            if (this.tempPolygon) {
                this.map.geoObjects.remove(this.tempPolygon);
            }

            const color = document.getElementById('zone_color').value;
            const previewPolygon = new ymaps.Polygon([finalCoords], {
                hintContent: 'Новая зона'
            }, {
                fillColor: this.hexToRgba(color, 0.5),
                strokeColor: color,
                strokeWidth: 3,
                fillOpacity: 0.5,
                interactive: false
            });
            this.map.geoObjects.add(previewPolygon);

            if (window.previewPolygon) {
                this.map.geoObjects.remove(window.previewPolygon);
            }
            window.previewPolygon = previewPolygon;

            try {
                this.map.setBounds(previewPolygon.geometry.getBounds(), {
                    checkZoomRange: true,
                    zoomMargin: 50
                });
            } catch (e) {
                console.warn('Не удалось установить границы', e);
            }

            this.showNotification('Зона нарисована! Заполните поля и нажмите "Сохранить"', 'success');
        } else {
            if (this.tempPolygon) {
                this.map.geoObjects.remove(this.tempPolygon);
            }
            this.showNotification('Нужно минимум 3 точки для создания зоны', 'warning');
            setTimeout(() => {
                this.startDrawing();
            }, 500);
        }

        this.tempPoints = [];
        this.tempPolygon = null;
    }

    cancelDrawing() {
        if (window.previewPolygon) {
            this.map.geoObjects.remove(window.previewPolygon);
            window.previewPolygon = null;
        }
        if (this.tempPolygon) {
            this.map.geoObjects.remove(this.tempPolygon);
            this.tempPolygon = null;
        }

        this.tempPlacemarks.forEach(pm => this.map.geoObjects.remove(pm));
        this.tempPlacemarks = [];

        this.currentMode = this.modes.VIEW;
        document.getElementById('draw-hint').style.display = 'none';
        document.getElementById('zone_coordinates').value = '';

        if (this.mapClickHandler) {
            this.map.events.remove('click', this.mapClickHandler);
            this.mapClickHandler = null;
        }

        this.setCursor('default');
        this.showNotification('Рисование отменено', 'info');
    }

    setCursor(cursor) {
        try {
            if (this.map && this.map.container) {
                const container = this.map.container.getElement();
                if (container) {
                    container.style.cursor = cursor;
                }
            }
        } catch (e) {
            console.warn('Не удалось изменить курсор', e);
        }
    }

    formatPrice(price) {
        return Number(price).toFixed(2).replace(/\.00$/, '');
    }

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    showNotification(message, type = 'info') {
        const colors = {
            success: '#d4edda',
            error: '#f8d7da',
            warning: '#fff3cd',
            info: '#d1ecf1'
        };
        const textColors = {
            success: '#155724',
            error: '#721c24',
            warning: '#856404',
            info: '#0c5460'
        };

        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%);
            padding: 12px 20px;
            background: ${colors[type] || colors.info};
            color: ${textColors[type] || textColors.info};
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 10000;
            max-width: 400px;
            font-size: 14px;
            animation: slideIn 0.3s ease;
            text-align: center;
        `;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

// Создаем глобальный экземпляр менеджера
let deliveryManager;

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded event fired');
    deliveryManager = new DeliveryZonesManager();
    console.log('deliveryManager created:', deliveryManager);
});

window.onerror = function(msg, url, line, col, error) {
    console.error('Global error:', msg, error);
    if (msg.includes('ymaps')) {
        if (deliveryManager) {
            deliveryManager.showNotification('Ошибка загрузки карты. Проверьте API ключ.', 'error');
        }
    }
    return false;
};