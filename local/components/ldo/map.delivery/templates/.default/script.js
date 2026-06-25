(function() {
    'use strict';

    if (typeof window.DeliveryMap !== 'undefined') {
        return;
    }

    class DeliveryMap {
        constructor(settings) {
            this.settings = settings;
            this.map = null;
            this.selectedPlacemark = null;
            this.deliveryZones = [];
            this.suggestTimeout = null;
            this.isInitialized = false;
            this.currentCity = null;

            this.init();
        }

        init() {
            this.bindFormEvents();

            if (typeof ymaps !== 'undefined') {
                ymaps.ready(() => {
                    this.initMap();
                    this.initAddressSuggest();
                    this.loadDeliveryZones();
                    this.isInitialized = true;
                });
            } else {
                const checkYmaps = setInterval(() => {
                    if (typeof ymaps !== 'undefined') {
                        clearInterval(checkYmaps);
                        ymaps.ready(() => {
                            this.initMap();
                            this.initAddressSuggest();
                            this.loadDeliveryZones();
                            this.isInitialized = true;
                        });
                    }
                }, 100);
            }
        }

        bindFormEvents() {
            const form = document.getElementById('deliveryForm');
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();

                    const lat = document.getElementById('latInput')?.value;
                    const lon = document.getElementById('lonInput')?.value;

                    if (lat && lon) {
                        this.calculateDelivery(lat, lon);
                    } else {
                        const totalSpan = document.querySelector('.delivery-block__total-sum span');
                        if (totalSpan) {
                            totalSpan.innerHTML = 'адрес не определен';
                        }
                    }
                });
            }
        }

        initMap() {
            this.map = new ymaps.Map('map', {
                center: [this.settings.defaultLat, this.settings.defaultLng],
                zoom: this.settings.defaultZoom
            });

            // Получаем город при инициализации карты
            this.getCurrentCity();

            this.map.events.add('click', (e) => {
                const coords = e.get('coords');
                console.log('Клик по карте:', coords);
                this.getAddressByCoords(coords, null);
            });

            // Обновляем город при перемещении карты
            this.map.events.add('boundschange', () => {
                this.getCurrentCity();
            });
        }

        /**
         * Получение текущего города по центру карты
         */
        getCurrentCity() {
            if (!this.map) return;

            const center = this.map.getCenter();

            ymaps.geocode(center, {
                results: 1
            }).then((res) => {
                const firstGeoObject = res.geoObjects.get(0);
                if (!firstGeoObject) return;

                const metaData = firstGeoObject.properties.get('metaDataProperty');
                if (!metaData) return;

                const addressDetails = metaData.GeocoderMetaData?.AddressDetails;
                if (!addressDetails) return;

                // Пробуем получить город из разных уровней
                let city = null;
                const country = addressDetails.Country;

                if (country?.AdministrativeArea?.SubAdministrativeArea?.Locality?.LocalityName) {
                    city = country.AdministrativeArea.SubAdministrativeArea.Locality.LocalityName;
                } else if (country?.AdministrativeArea?.Locality?.LocalityName) {
                    city = country.AdministrativeArea.Locality.LocalityName;
                } else if (country?.Locality?.LocalityName) {
                    city = country.Locality.LocalityName;
                } else if (country?.AdministrativeArea?.AdministrativeAreaName) {
                    city = country.AdministrativeArea.AdministrativeAreaName;
                }

                if (city && city !== this.currentCity) {
                    this.currentCity = city;
                    console.log('Текущий город:', city);
                }
            }).catch((error) => {
                console.warn('Не удалось определить город:', error);
            });
        }

        initAddressSuggest() {
            const addressInput = document.getElementById('addressInput');
            const suggestionsContainer = document.getElementById('suggestions');

            if (!addressInput || !suggestionsContainer) {
                console.error('Элементы для подсказок не найдены');
                return;
            }

            console.log('Инициализация подсказок адресов');

            addressInput.addEventListener('input', () => {
                clearTimeout(this.suggestTimeout);

                const query = addressInput.value.trim();
                console.log('Ввод адреса:', query);

                if (query.length < 3) {
                    suggestionsContainer.style.display = 'none';
                    return;
                }

                this.suggestTimeout = setTimeout(() => {
                    this.searchAddressSuggestions(query, suggestionsContainer);
                }, 300);
            });

            document.addEventListener('click', (e) => {
                if (!addressInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                    suggestionsContainer.style.display = 'none';
                }
            });

            addressInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    suggestionsContainer.style.display = 'none';
                }
            });
        }

        searchAddressSuggestions(query, container) {
            console.log('Поиск подсказок для:', query);

            if (!this.map) {
                console.error('Карта не инициализирована');
                return;
            }

            // Формируем запрос с городом
            let searchQuery = query;
            if (this.currentCity && !query.toLowerCase().includes(this.currentCity.toLowerCase())) {
                // Если город не указан в запросе, добавляем его
                // Но не переопределяем если пользователь уже ввел город
                searchQuery = `${query}, ${this.currentCity}`;
                console.log('Поиск с городом:', searchQuery);
            }

            // Получаем bounds для ограничения области (но с большим запасом)
            let bounds;
            try {
                bounds = this.map.getBounds();
                // Расширяем границы в 3 раза, чтобы охватить весь город
                const latSpan = bounds[1][0] - bounds[0][0];
                const lngSpan = bounds[1][1] - bounds[0][1];
                bounds = [
                    [bounds[0][0] - latSpan * 1.5, bounds[0][1] - lngSpan * 1.5],
                    [bounds[1][0] + latSpan * 1.5, bounds[1][1] + lngSpan * 1.5]
                ];
            } catch (e) {
                console.warn('Не удалось получить bounds, используем центр');
                const lat = this.settings.defaultLat;
                const lng = this.settings.defaultLng;
                bounds = [
                    [lat - 1, lng - 1],
                    [lat + 1, lng + 1]
                ];
            }

            ymaps.geocode(searchQuery, {
                results: 5,
                boundedBy: bounds,
                strictBounds: false // Не строгое, чтобы найти даже если немного за пределами
            }).then((res) => {
                console.log('Результат геокодирования:', res);

                const suggestions = res.geoObjects;

                if (suggestions.length === 0) {
                    console.log('Подсказок не найдено');
                    container.style.display = 'none';
                    return;
                }

                container.innerHTML = '';

                suggestions.each((suggestion) => {
                    const address = suggestion.getAddressLine();
                    console.log('Найдена подсказка:', address);

                    const item = document.createElement('div');
                    item.className = 'suggestion-item';
                    item.textContent = address;

                    item.addEventListener('click', () => {
                        console.log('Выбрана подсказка:', address);
                        this.selectSuggestion(suggestion);
                        container.style.display = 'none';
                    });

                    container.appendChild(item);
                });

                container.style.display = 'block';

            }).catch((error) => {
                console.error('Ошибка поиска подсказок:', error);
                container.style.display = 'none';
            });
        }

        selectSuggestion(suggestion) {
            const coords = suggestion.geometry.getCoordinates();
            const address = suggestion.getAddressLine();

            console.log('Выбрана подсказка:', address, coords);

            document.getElementById('addressInput').value = address;
            document.getElementById('latInput').value = coords[0].toFixed(6);
            document.getElementById('lonInput').value = coords[1].toFixed(6);

            this.map.setCenter(coords, 15);
            this.addPlacemark(coords, address);
            this.checkDeliveryZone(coords);
        }

        getAddressByCoords(coords, zone) {
            console.log('getAddressByCoords вызван с координатами:', coords);

            ymaps.geocode(coords, {
                results: 1
            }).then((res) => {
                console.log('Результат геокодирования:', res);

                const firstGeoObject = res.geoObjects.get(0);
                if (!firstGeoObject) {
                    console.warn('Не найден объект по координатам');
                    return;
                }

                const address = firstGeoObject.getAddressLine();
                console.log('Найден адрес:', address);

                document.getElementById('addressInput').value = address;
                document.getElementById('latInput').value = coords[0].toFixed(6);
                document.getElementById('lonInput').value = coords[1].toFixed(6);

                if (zone) {
                    document.getElementById('zoneIdInput').value = zone.id;
                    this.addPlacemark(coords, address, zone.name);
                    this.showDeliveryInfo(zone);
                } else {
                    this.addPlacemark(coords, address);
                    this.checkDeliveryZone(coords);
                }

            }).catch((error) => {
                console.error('Ошибка геокодирования:', error);
            });
        }

        checkDeliveryZone(coords) {
            console.log('Проверка зоны для координат:', coords);
            console.log('Доступные зоны:', this.deliveryZones.length);

            let foundZone = null;

            for (const zone of this.deliveryZones) {
                const isInside = this.isPointInPolygonManual(coords, zone.coordinates);
                console.log(`Зона ${zone.name}: ${isInside ? 'внутри' : 'снаружи'}`);
                if (isInside) {
                    foundZone = zone;
                    break;
                }
            }

            if (foundZone) {
                console.log('Найдена зона:', foundZone.name);
                document.getElementById('zoneIdInput').value = foundZone.id;
                this.showDeliveryInfo(foundZone);
                return true;
            } else {
                console.log('Зона не найдена');
                document.getElementById('zoneIdInput').value = 'не в зоне доставки';
                const infoBlock = document.querySelector('.delivery-block__info');
                const totalSpan = document.querySelector('.delivery-block__total-sum span');
                if (infoBlock) {
                    infoBlock.style.display = 'none';
                }
                if (totalSpan) {
                    totalSpan.innerHTML = 'Адрес вне зоны доставки';
                }
                return false;
            }
        }

        isPointInPolygonManual(point, polygon) {
            const x = point[0];
            const y = point[1];
            let inside = false;
            const n = polygon.length;

            for (let i = 0, j = n - 1; i < n; j = i++) {
                const xi = polygon[i][0];
                const yi = polygon[i][1];
                const xj = polygon[j][0];
                const yj = polygon[j][1];

                const intersect = ((yi > y) !== (yj > y)) &&
                    (x < (xj - xi) * (y - yi) / (yj - yi) + xi);

                if (intersect) {
                    inside = !inside;
                }
            }

            return inside;
        }

        addPlacemark(coords, address, zoneName) {
            if (this.selectedPlacemark) {
                this.map.geoObjects.remove(this.selectedPlacemark);
            }

            let balloonContent = `<strong>Адрес доставки:</strong> ${address}`;
            if (zoneName) {
                balloonContent += `<br><strong>Зона:</strong> ${zoneName}`;
            }

            this.selectedPlacemark = new ymaps.Placemark(coords, {
                balloonContent: balloonContent,
                hintContent: 'Выбранный адрес доставки'
            }, {
                preset: 'islands#redDotIcon'
            });

            this.map.geoObjects.add(this.selectedPlacemark);
            this.selectedPlacemark.balloon.open();
        }

        loadDeliveryZones() {
            if (typeof BX === 'undefined' || !BX.ajax) {
                console.error('BX.ajax не доступен');
                return;
            }

            console.log('Загрузка зон доставки...');

            BX.ajax.runComponentAction('ldo:map.delivery', 'getZones', {
                mode: 'class'
            }).then((response) => {
                console.log('Ответ от сервера:', response);
                if (response.data && response.data.success && response.data.zones) {
                    this.renderZones(response.data.zones);
                }
            }).catch((error) => {
                console.error('Ошибка загрузки зон доставки:', error);
            });
        }

        renderZones(zones) {
            console.log('Отрисовка зон:', zones.length);

            zones.forEach((zoneData) => {
                const coordinates = zoneData.coordinates;

                if (!coordinates || coordinates.length < 3) {
                    console.warn('Некорректные координаты для зоны:', zoneData.name);
                    return;
                }

                console.log(`Отрисовка зоны: ${zoneData.name}, точек: ${coordinates.length}`);

                const polygon = new ymaps.Polygon([coordinates], {
                    hintContent: zoneData.name
                }, {
                    fillColor: zoneData.color + '33',
                    strokeColor: zoneData.color,
                    strokeWidth: 2,
                    fillOpacity: 0.3,
                    strokeOpacity: 0.9,
                    interactive: true
                });

                const zoneInfo = {
                    polygon: polygon,
                    id: zoneData.id,
                    name: zoneData.name,
                    price: zoneData.price,
                    free_delivery_price: zoneData.free_delivery_price,
                    delivery_time: zoneData.delivery_time,
                    min_order_price: zoneData.min_order_price,
                    color: zoneData.color,
                    coordinates: coordinates
                };

                this.deliveryZones.push(zoneInfo);

                polygon.events.add('click', (e) => {
                    const coords = e.get('coords');
                    console.log('Клик по зоне:', zoneData.name);
                    this.getAddressByCoords(coords, zoneInfo);
                });

                // Метка с названием зоны
                const center = this.getPolygonCenter(coordinates);
                const label = new ymaps.Placemark(center, {
                    iconContent: zoneData.name
                }, {
                    preset: 'islands#circleIcon',
                    iconColor: zoneData.color,
                    iconContentLayout: ymaps.templateLayoutFactory.createClass(
                        `<div class="zone-label" style="border-color: ${zoneData.color};">$[properties.iconContent]</div>`
                    )
                });

                this.map.geoObjects.add(polygon);
                this.map.geoObjects.add(label);
            });

            if (this.deliveryZones.length > 0) {
                try {
                    const bounds = this.map.geoObjects.getBounds();
                    if (bounds) {
                        this.map.setBounds(bounds);
                    }
                } catch (e) {
                    console.warn('Не удалось установить границы карты');
                }
            }
        }

        getPolygonCenter(coordinates) {
            if (!coordinates || coordinates.length < 3) {
                return [0, 0];
            }

            let lat = 0;
            let lng = 0;
            const count = coordinates.length;

            coordinates.forEach((coord) => {
                lat += coord[0];
                lng += coord[1];
            });

            return [lat / count, lng / count];
        }

        showDeliveryInfo(zone) {
            console.log('Показ информации о доставке для зоны:', zone.name);

            const infoBlock = document.querySelector('.delivery-block__info');
            const totalSpan = document.querySelector('.delivery-block__total-sum span');
            const timeSpan = document.querySelector('.delivery-block__time-delivery span');
            const minSpan = document.querySelector('.delivery-block__min-sum span');
            const freeSpan = document.querySelector('.delivery-block__free-delivery span');

            if (infoBlock) {
                infoBlock.style.display = 'block';
            }
            if (totalSpan) {
                totalSpan.innerHTML = zone.price + ' рублей';
            }
            if (timeSpan) {
                timeSpan.innerHTML = (zone.delivery_time || 0) + ' минут';
            }
            if (minSpan) {
                minSpan.innerHTML = (zone.min_order_price || 0) + ' рублей';
            }
            if (freeSpan) {
                freeSpan.innerHTML = (zone.free_delivery_price || 0) + ' рублей';
            }
        }

        calculateDelivery(lat, lon) {
            if (typeof BX === 'undefined' || !BX.ajax) {
                console.error('BX.ajax не доступен');
                return;
            }

            console.log('Расчет доставки для координат:', lat, lon);

            BX.ajax.runComponentAction('ldo:map.delivery', 'calculateDelivery', {
                mode: 'class',
                data: {
                    post: {
                        arParams: {
                            lat: lat,
                            lon: lon
                        }
                    }
                }
            }).then((response) => {
                console.log('Ответ расчета доставки:', response);
                const data = response.data;

                if (data.error) {
                    const totalSpan = document.querySelector('.delivery-block__total-sum span');
                    const infoBlock = document.querySelector('.delivery-block__info');
                    if (infoBlock) infoBlock.style.display = 'none';
                    if (totalSpan) totalSpan.innerHTML = data.error;
                    return;
                }

                if (data.success) {
                    const zone = this.deliveryZones.find(z => z.id === data.zone_id);
                    if (zone) {
                        this.showDeliveryInfo(zone);
                    }
                }
            }).catch((error) => {
                console.error('Ошибка расчета доставки:', error);
                const totalSpan = document.querySelector('.delivery-block__total-sum span');
                if (totalSpan) totalSpan.innerHTML = 'Ошибка расчета';
            });
        }
    }

    window.DeliveryMap = DeliveryMap;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.deliveryMapSettings !== 'undefined') {
                window.deliveryMapInstance = new DeliveryMap(window.deliveryMapSettings);
            }
        });
    } else {
        if (typeof window.deliveryMapSettings !== 'undefined') {
            window.deliveryMapInstance = new DeliveryMap(window.deliveryMapSettings);
        }
    }
})();