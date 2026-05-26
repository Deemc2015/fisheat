$( document ).ready(function(){

    $('#deliveryForm').submit(function(e){
        e.preventDefault();

        var lat = $('#latInput',this).val();
        var lon = $('#lonInput',this).val();

        if(lat && lon){
            var dataPrice = getPrice(lat,lon);
        }
        else{
            $('.delivery-block__total-sum span').html('адрес не определен');
        }

    })


    ymaps.ready(init);

    let map;
    let selectedPlacemark = null;
    let deliveryZones = [];
    let suggestTimeout;

    function init() {
        map = new ymaps.Map('map', {
            center: [54.7355, 55.9587],
            zoom: 11
        });

        // Инициализируем подсказки для адреса
        initAddressSuggest();

        // Загрузка GeoJSON зон доставки
        loadDeliveryZones();
    }

    // Инициализация подсказок адресов через геокодер
    function initAddressSuggest() {
        const addressInput = document.getElementById('addressInput');
        const suggestionsContainer = document.getElementById('suggestions');

        // Обработчик ввода в поле адреса
        addressInput.addEventListener('input', function() {
            clearTimeout(suggestTimeout);

            const query = this.value.trim();

            if (query.length < 3) {
                suggestionsContainer.style.display = 'none';
                return;
            }

            // Задержка перед запросом (дебаунс)
            suggestTimeout = setTimeout(() => {
                searchAddressSuggestions(query, suggestionsContainer);
            }, 300);
        });

        // Скрытие подсказок при клике вне поля
        document.addEventListener('click', function(e) {
            if (!addressInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                suggestionsContainer.style.display = 'none';
            }
        });

        // Обработчик клавиш в поле адреса
        addressInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                suggestionsContainer.style.display = 'none';
            }
        });
    }

    // Поиск подсказок через геокодер
    function searchAddressSuggestions(query, container) {
        // Ограничиваем поиск областью карты (Уфа и окрестности)
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

            // Очищаем контейнер
            container.innerHTML = '';

            // Добавляем подсказки
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

    // Обработка выбора подсказки
    function selectSuggestion(suggestion) {
        const coords = suggestion.geometry.getCoordinates();
        const address = suggestion.getAddressLine();

        // Заполняем поля
        document.getElementById('addressInput').value = address;
        document.getElementById('latInput').value = coords[0].toFixed(6);
        document.getElementById('lonInput').value = coords[1].toFixed(6);

        // Перемещаем карту
        map.setCenter(coords, 15);

        // Добавляем метку
        addPlacemark(coords, address, 'Выбранный адрес');

        // Проверяем зону доставки
        checkDeliveryZone(coords);
    }

    // Функция получения адреса по координатам
    function getAddressByCoords(coords, zone) {
        ymaps.geocode(coords).then(function (res) {
            const firstGeoObject = res.geoObjects.get(0);
            const address = firstGeoObject.getAddressLine();

            // Записываем данные в инпуты
            document.getElementById('addressInput').value = address;
            document.getElementById('latInput').value = coords[0].toFixed(6);
            document.getElementById('lonInput').value = coords[1].toFixed(6);
            document.getElementById('zoneIdInput').value = zone.id;

            // Добавляем метку на карту
            addPlacemark(coords, address, zone.name);

        }).catch(function (error) {
            console.error('Ошибка геокодирования:', error);
        });
    }

    // Проверка зоны доставки
    function checkDeliveryZone(coords) {
        let inZone = false;

        for (let zone of deliveryZones) {
            if (isPointInPolygon(coords, zone.polygon)) {
                document.getElementById('zoneIdInput').value = zone.id;
                inZone = true;
                break;
            }
        }

        if (!inZone) {
            document.getElementById('zoneIdInput').value = 'не в зоне доставки';
        }

        return inZone;
    }

    // Проверка точки в полигоне
    function isPointInPolygon(point, polygon) {
        const geometry = polygon.geometry;
        return geometry.contains(point);
    }

    // Добавление метки на карту
    function addPlacemark(coords, address, zoneName) {
        // Удаляем предыдущую метку
        if (selectedPlacemark) {
            map.geoObjects.remove(selectedPlacemark);
        }

        // Создаем новую метку
        selectedPlacemark = new ymaps.Placemark(coords, {
            balloonContent: `
                <strong>Адрес доставки:</strong> ${address}
            `,
            hintContent: 'Выбранный адрес доставки'
        }, {
            preset: 'islands#redDotIcon'
        });

        map.geoObjects.add(selectedPlacemark);
        selectedPlacemark.balloon.open();
    }

    // Загрузка зон доставки
    function loadDeliveryZones() {
        fetch('/export.geojson')
            .then(response => response.json())
            .then(data => {
                data.features.forEach(feature => {
                    const coordinates = feature.geometry.coordinates[0].map(coord => [coord[1], coord[0]]);

                    const polygon = new ymaps.Polygon([coordinates], {

                    }, {
                        fillColor: feature.properties.fill,
                        strokeColor: feature.properties.stroke,
                        strokeWidth: 2,
                        fillOpacity: 0.3,
                        strokeOpacity: 0.9
                    });

                    // Сохраняем информацию о зоне
                    const zoneInfo = {
                        polygon: polygon,
                        id: feature.id,
                        name: feature.properties.description,
                        properties: feature.properties
                    };

                    deliveryZones.push(zoneInfo);

                    // Обработчик клика по зоне
                    polygon.events.add('click', function(e) {
                        const coords = e.get('coords');
                        getAddressByCoords(coords, zoneInfo);
                    });

                    map.geoObjects.add(polygon);
                });

                // Автоматическое подгонка под все объекты
                map.setBounds(map.geoObjects.getBounds());
            })
            .catch(error => console.error('Ошибка загрузки зон доставки:', error));
    }
})

function getPrice(lat, lon) {

    BX.ajax.runComponentAction('ldo:map.delivery',
        'sendForm', {
            mode: 'class',
            data: {post: {
                    arParams:{
                        lat:lat,
                        lon:lon
                    }
                }},
        })
        .then(function(response) {
            if(response['data']){
                if(response['data']['price'] > 0){
                    $('.delivery-block__info').slideDown();
                    $('.delivery-block__total-sum span').html(response['data']['price'] + ' рублей');
                    $('.delivery-block__time-delivery span').html(response['data']['deliveryTime']/60 + ' минут');
                    $('.delivery-block__min-sum span').html(response['data']['minPrice'] + ' рублей');
                    $('.delivery-block__free-delivery span').html(response['data']['priceFreeDelivery'] + ' рублей');
                }
            }
        });
}



