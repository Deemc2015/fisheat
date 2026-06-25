<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */

use Bitrix\Main\Localization\Loc;
?>

<div id="page-delivery" class="delivery-block ">
    <div class="delivery-block__title">
        Расчет стоимости доставки
    </div>
    <div class="delivery-block__form">
        <form id="deliveryForm" action="#" method="POST">
            <div class="form-line">
                <span>Введите адрес доставки</span>
                <input type="text" name="ADDRESS" id="addressInput" value="" required />
                <div id="suggestions" style="display: none;"></div>
            </div>
            <input type="hidden" name="LAT" id="latInput" value="" readonly />
            <input type="hidden" name="LON" id="lonInput" value="" readonly />
            <input type="hidden" name="ZONE_ID" id="zoneIdInput" value="" readonly />
            <button type="submit">Рассчитать</button>
        </form>
    </div>
    <div class="delivery-block__info" style="display:none;">
        <div class="delivery-block__total-sum">
            Сумма доставки на указанный адрес:  <span>не рассчитана</span>
        </div>
        <div class="delivery-block__time-delivery ">
            Ориентировочное время доставки: <span> не рассчитано</span>
        </div>
        <div class="delivery-block__min-sum ">
            Минимальная сумма заказа: <span> не рассчитана</span>
        </div>
        <div class="delivery-block__free-delivery ">
            Бесплатная доставка от: <span> не рассчитана</span>
        </div>
    </div>
</div>
<?php
print_r($arResult['YANDEX_API_KEY']);
?>
<script src="https://api-maps.yandex.ru/2.1/?apikey=<?= htmlspecialchars($arResult['YANDEX_API_KEY']) ?>&lang=ru_RU" type="text/javascript"></script>

<div id="map"></div>

<script>
    $(document).ready(function(){
        $('#deliveryForm').submit(function(e){
            e.preventDefault();

            var lat = $('#latInput',this).val();
            var lon = $('#lonInput',this).val();

            if(lat && lon){
                getPrice(lat, lon);
            }
            else{
                $('.delivery-block__total-sum span').html('адрес не определен');
            }
        });

        ymaps.ready(init);

        let map;
        let selectedPlacemark = null;
        let deliveryZones = [];
        let suggestTimeout;

        // Настройки из модуля
        const defaultLat = <?= (float)$arResult['DEFAULT_LAT'] ?>;
        const defaultLng = <?= (float)$arResult['DEFAULT_LNG'] ?>;
        const defaultZoom = <?= (int)$arResult['DEFAULT_ZOOM'] ?>;

        function init() {
            map = new ymaps.Map('map', {
                center: [defaultLat, defaultLng],
                zoom: defaultZoom
            });

            initAddressSuggest();
            loadDeliveryZones();
        }

        function initAddressSuggest() {
            const addressInput = document.getElementById('addressInput');
            const suggestionsContainer = document.getElementById('suggestions');

            addressInput.addEventListener('input', function() {
                clearTimeout(suggestTimeout);

                const query = this.value.trim();

                if (query.length < 3) {
                    suggestionsContainer.style.display = 'none';
                    return;
                }

                suggestTimeout = setTimeout(() => {
                    searchAddressSuggestions(query, suggestionsContainer);
                }, 300);
            });

            document.addEventListener('click', function(e) {
                if (!addressInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                    suggestionsContainer.style.display = 'none';
                }
            });

            addressInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    suggestionsContainer.style.display = 'none';
                }
            });
        }

        function searchAddressSuggestions(query, container) {
            const bounds = map.getBounds();

            ymaps.geocode(query, {
                boundedBy: bounds,
                strictBounds: true,
                results: 5
            }).then(function(res) {
                const suggestions = res.geoObjects;

                if (suggestions.length === 0) {
                    container.style.display = 'none';
                    return;
                }

                container.innerHTML = '';

                suggestions.each(function(suggestion) {
                    const address = suggestion.getAddressLine();
                    const item = document.createElement('div');
                    item.className = 'suggestion-item';
                    item.textContent = address;

                    item.addEventListener('click', function() {
                        selectSuggestion(suggestion);
                        container.style.display = 'none';
                    });

                    container.appendChild(item);
                });

                container.style.display = 'block';

            }).catch(function(error) {
                console.error('Ошибка поиска подсказок:', error);
                container.style.display = 'none';
            });
        }

        function selectSuggestion(suggestion) {
            const coords = suggestion.geometry.getCoordinates();
            const address = suggestion.getAddressLine();

            document.getElementById('addressInput').value = address;
            document.getElementById('latInput').value = coords[0].toFixed(6);
            document.getElementById('lonInput').value = coords[1].toFixed(6);

            map.setCenter(coords, 15);
            addPlacemark(coords, address, 'Выбранный адрес');
            checkDeliveryZone(coords);
        }

        function getAddressByCoords(coords, zone) {
            ymaps.geocode(coords).then(function (res) {
                const firstGeoObject = res.geoObjects.get(0);
                const address = firstGeoObject.getAddressLine();

                document.getElementById('addressInput').value = address;
                document.getElementById('latInput').value = coords[0].toFixed(6);
                document.getElementById('lonInput').value = coords[1].toFixed(6);
                document.getElementById('zoneIdInput').value = zone.id;

                addPlacemark(coords, address, zone.name);

            }).catch(function (error) {
                console.error('Ошибка геокодирования:', error);
            });
        }

        function checkDeliveryZone(coords) {
            let foundZone = null;

            for (let zone of deliveryZones) {
                if (isPointInPolygon(coords, zone.polygon)) {
                    foundZone = zone;
                    break;
                }
            }

            if (foundZone) {
                document.getElementById('zoneIdInput').value = foundZone.id;
                getPrice(coords[0], coords[1]);
                return true;
            } else {
                document.getElementById('zoneIdInput').value = 'не в зоне доставки';
                $('.delivery-block__info').hide();
                $('.delivery-block__total-sum span').html('Адрес вне зоны доставки');
                return false;
            }
        }

        function isPointInPolygon(point, polygon) {
            const geometry = polygon.geometry;
            return geometry.contains(point);
        }

        function addPlacemark(coords, address, zoneName) {
            if (selectedPlacemark) {
                map.geoObjects.remove(selectedPlacemark);
            }

            selectedPlacemark = new ymaps.Placemark(coords, {
                balloonContent: `
                <strong>Адрес доставки:</strong> ${address}
                ${zoneName ? `<br><strong>Зона:</strong> ${zoneName}` : ''}
            `,
                hintContent: 'Выбранный адрес доставки'
            }, {
                preset: 'islands#redDotIcon'
            });

            map.geoObjects.add(selectedPlacemark);
            selectedPlacemark.balloon.open();
        }

        function loadDeliveryZones() {
            BX.ajax.runComponentAction('ldo:map.delivery',
                'getZones', {
                    mode: 'class',
                })
                .then(function(response) {
                    if (response.data && response.data.success && response.data.zones) {
                        const zones = response.data.zones;

                        zones.forEach(function(zoneData) {
                            const coordinates = zoneData.COORDINATES;

                            if (!coordinates || coordinates.length < 3) {
                                return;
                            }

                            const polygon = new ymaps.Polygon([coordinates], {
                                hintContent: zoneData.NAME
                            }, {
                                fillColor: zoneData.COLOR + '33',
                                strokeColor: zoneData.COLOR,
                                strokeWidth: 2,
                                fillOpacity: 0.3,
                                strokeOpacity: 0.9,
                                interactive: true
                            });

                            const zoneInfo = {
                                polygon: polygon,
                                id: zoneData.ID,
                                name: zoneData.NAME,
                                price: zoneData.PRICE,
                                freeDeliveryPrice: zoneData.FREE_DELIVERY_PRICE,
                                deliveryTime: zoneData.DELIVERY_TIME,
                                minOrderPrice: zoneData.MIN_ORDER_PRICE,
                                color: zoneData.COLOR,
                                coordinates: coordinates
                            };

                            deliveryZones.push(zoneInfo);

                            polygon.events.add('click', function(e) {
                                const coords = e.get('coords');
                                getAddressByCoords(coords, zoneInfo);
                            });

                            // Метка с названием зоны
                            const center = getPolygonCenter(coordinates);
                            const label = new ymaps.Placemark(center, {
                                iconContent: zoneData.NAME
                            }, {
                                preset: 'islands#circleIcon',
                                iconColor: zoneData.COLOR,
                                iconContentLayout: ymaps.templateLayoutFactory.createClass(
                                    '<div class="zone-label" style="border-color: ' + zoneData.COLOR + '; background: rgba(255,255,255,0.9); padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; color: #333; pointer-events: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">$[properties.iconContent]</div>'
                                )
                            });

                            map.geoObjects.add(polygon);
                            map.geoObjects.add(label);
                        });

                        if (deliveryZones.length > 0) {
                            map.setBounds(map.geoObjects.getBounds());
                        }
                    }
                })
                .catch(function(error) {
                    console.error('Ошибка загрузки зон доставки:', error);
                });
        }

        function getPolygonCenter(coordinates) {
            if (!coordinates || coordinates.length < 3) return [0, 0];

            let lat = 0;
            let lng = 0;
            const count = coordinates.length;

            coordinates.forEach(function(coord) {
                lat += coord[0];
                lng += coord[1];
            });

            return [lat / count, lng / count];
        }

        function getPrice(lat, lon) {
            BX.ajax.runComponentAction('ldo:map.delivery',
                'sendForm', {
                    mode: 'class',
                    data: {
                        post: {
                            arParams: {
                                lat: lat,
                                lon: lon
                            }
                        }
                    },
                })
                .then(function(response) {
                    if(response.data){
                        if(response.data.error){
                            $('.delivery-block__info').hide();
                            $('.delivery-block__total-sum span').html(response.data.error);
                            return;
                        }

                        if(response.data.success){
                            $('.delivery-block__info').slideDown();
                            $('.delivery-block__total-sum span').html(response.data.price + ' рублей');
                            $('.delivery-block__time-delivery span').html(response.data.deliveryTime + ' минут');
                            $('.delivery-block__min-sum span').html(response.data.minPrice + ' рублей');
                            $('.delivery-block__free-delivery span').html(response.data.priceFreeDelivery + ' рублей');
                        }
                    }
                })
                .catch(function(error) {
                    console.error('Ошибка расчета доставки:', error);
                    $('.delivery-block__total-sum span').html('Ошибка расчета');
                });
        }
    });
</script>

<style>
    #suggestions {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #ccc;
        border-top: none;
        border-radius: 0 0 4px 4px;
        z-index: 1000;
        max-height: 200px;
        overflow-y: auto;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .suggestion-item {
        padding: 10px 15px;
        cursor: pointer;
        transition: background 0.2s;
        border-bottom: 1px solid #f0f0f0;
    }

    .suggestion-item:hover {
        background: #f0f8ff;
    }

    .suggestion-item:last-child {
        border-bottom: none;
    }

    #map {
        width: 100%;
        height: 500px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    .zone-label {
        white-space: nowrap;
    }

    .delivery-block__info {
        display: none;
    }
</style>