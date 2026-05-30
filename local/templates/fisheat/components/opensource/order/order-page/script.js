;(function() {
    'use strict';

    BX.namespace('LDO.CustomBasket');

    BX.LDO.CustomBasket = {

        // ==============================================
        // СВОЙСТВА КОМПОНЕНТА
        // ==============================================

        personCount: {
            node: null,           // DOM-элемент блока персон
            input: null,          // Поле ввода количества персон
            value: 1,             // Текущее значение
            minValue: 1,          // Минимальное количество
            maxValue: 20          // Максимальное количество
        },

        totalBlock: {
            node: null,                       // Основной блок итогов
            addressNode: null,                 // Элемент с текстом "Адрес"
            addressValueNode: null,            // Элемент со значением адреса
            deliveryNode: null,                 // Элемент с текстом "Доставка"
            deliveryPriceNode: null,            // Элемент с ценой доставки
            baseSumNode: null,                  // Базовая сумма заказа
            discountNode: null,                  // Сумма скидки
            bonusNode: null,                     // Сумма бонусов
            totalNode: null,                     // Итоговая сумма
            currentData: {                       // Текущие значения для быстрого доступа
                address: '',
                deliveryPrice: 0,
                baseSum: 0,
                discount: 0,
                bonus: 0,
                total: 0
            }
        },
        gramsTotalBlock: {
            node: null,           // DOM-элемент блока с общей суммой граммов
            totalGramsNode: null, // Элемент с общей суммой граммов
            totalGrams: 0         // Текущее значение граммов
        },
        selectedGiftId: null,        // ID выбранного подарка
        selectedGiftNode: null,      // DOM-элемент выбранного подарка
        gifts: {                     // Объект для хранения данных о подарках
            warningModal: null       // Ссылка на модальное окно предупреждения
        },

        // ==============================================
        // МЕТОДЫ ИНИЦИАЛИЗАЦИИ
        // ==============================================

        /**
         * Точка входа - инициализирует компонент корзины
         * @param {object} options - параметры компонента
         */
        init: function(options) {
            this.options = options || {};
            this.basketItems = {};               // Хранилище данных о товарах
            this.priceAnimationData = {};         // Данные для анимации цен
            this.debounceTimers = {};             // Таймеры для debounce-обновлений
            this.buttonLocks = {};                 // Блокировка кнопок при AJAX
            this.totalPriceNode = document.querySelector('.basket-total-price'); // Общая сумма корзины
            this.currency = 'RUB';

            // Последовательная инициализация всех частей компонента
            this.initTotalBlock();                 // Инициализация блока итогов
            this.initializeBasketItems();          // Сбор данных о товарах
            this.bindEvents();                      // Привязка основных событий
            this.bindRemoveOrder();                  // Привязка удаления корзины
            this.bindDeliveryEvents();               // Привязка событий доставки
            this.initPersonCount();                  // Инициализация блока персон
            this.initAddressDelete();                // Удаление адреса доставки
            this.initPromoType();
            this.initAddAddressModal(); //Модальное окно добавления адреса
            this.initAddressSuggest(); //Подсказки адреса

            // НОВЫЕ МЕТОДЫ
            this.initDeliveryToggle();      // Переключение доставка/самовывоз
            this.initAddressSelect();       // Выбор адреса доставки
            this.initRestaurantSelect();    // Выбор ресторана самовывоза
            // Устанавливаем правильный заголовок при загрузке страницы
            this.initTimeDeliveryTitle();
            // привязка отправки формы
            this.bindFormSubmit();
            this.initCommentToggle();
        },

        /**
         * Инициализация заголовка блока времени при загрузке страницы
         */
        initTimeDeliveryTitle: function() {
            var selectedDelivery = document.querySelector('input[name="delivery_id"]:checked');
            if (selectedDelivery) {
                var label = selectedDelivery.closest('label');
                if (label) {
                    var nameElement = label.querySelector('.delivery-name');
                    if (nameElement) {
                        var deliveryName = nameElement.textContent.trim();
                        if (deliveryName.toLowerCase() === 'самовывоз') {
                            this.updateTimeDeliveryTitle('самовывоза');
                            this.hideDeliveryPrice();
                        } else {
                            this.updateTimeDeliveryTitle('доставки');
                            this.showDeliveryPrice();
                        }
                    }
                }
            }
        },
        /**
         * Привязывает обработчик отправки формы
         */
        bindFormSubmit: function() {
            var form = document.getElementById('os-order-form');
            if (form) {
                BX.unbindAll(form);
                BX.bind(form, 'submit', BX.proxy(function(event) {
                    this.saveDeliveryState();
                }, this));
            }
        },
        /**
         * Инициализация переключения блока комментария кухне
         */
        initCommentToggle: function() {
            var commentIcon = document.querySelector('.comments-block__top-icon');
            var commentBlock = document.querySelector('.comments-block');

            if (!commentIcon || !commentBlock) return;

            // Добавляем обработчик клика по иконке
            BX.unbindAll(commentIcon);
            BX.bind(commentIcon, 'click', function(event) {
                event.preventDefault();
                event.stopPropagation();

                // Переключаем класс show
                if (commentBlock.classList.contains('show')) {
                    BX.removeClass(commentBlock, 'show');
                } else {
                    BX.addClass(commentBlock, 'show');
                }
            });
        },
        /**
         * Инициализация модального окна добавления адреса
         */
        initAddAddressModal: function() {
            var self = this;

            // Находим кнопку открытия модального окна (добавление нового адреса)
            var addAddressBtn = document.querySelector('.addAdress-user');
            var modal = document.querySelector('.modal-add-address');
            var wrp = document.querySelector('.wrp');

            if (!addAddressBtn || !modal || !wrp) return;

            // Обработчик открытия для добавления
            BX.unbindAll(addAddressBtn);
            BX.bind(addAddressBtn, 'click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                self.openAddAddressModal('add');
            });

            // Обработчики для кнопок редактирования (назначаем через делегирование)
            self.bindEditAddressButtons();

            // Также отслеживаем появление новых кнопок редактирования при обновлении списка адресов
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    if (mutation.addedNodes.length) {
                        self.bindEditAddressButtons();
                    }
                });
            });

            var addressList = document.querySelector('.adress-user-list');
            if (addressList) {
                observer.observe(addressList, {childList: true, subtree: true});
            }

            // Обработчики закрытия
            var closeBtn = modal.querySelector('.close-modal');
            var closeModalBtn = modal.querySelector('.close-modal-btn');

            if (closeBtn) {
                BX.unbindAll(closeBtn);
                BX.bind(closeBtn, 'click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    self.closeAddAddressModal();
                });
            }

            if (closeModalBtn) {
                BX.unbindAll(closeModalBtn);
                BX.bind(closeModalBtn, 'click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    self.closeAddAddressModal();
                });
            }

            // Обработчик отправки формы
            var form = modal.querySelector('form');
            if (form) {
                BX.unbindAll(form);
                BX.bind(form, 'submit', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    self.addAddress(form);
                });
            }

            // Закрытие по клику на подложку
            BX.bind(wrp, 'click', function (event) {
                if (event.target === wrp) {
                    self.closeAddAddressModal();
                }
            });
        },
        /**
         * Привязывает обработчики ко всем кнопкам редактирования адреса
         */
        bindEditAddressButtons: function() {
            var self = this;
            var editButtons = document.querySelectorAll('.adress-user-list__item-btn-edit');

            for (var i = 0; i < editButtons.length; i++) {
                var editBtn = editButtons[i];

                // Сохраняем старую ссылку, чтобы не навешивать повторно
                if (editBtn.hasAttribute('data-edit-bound')) continue;

                editBtn.setAttribute('data-edit-bound', 'true');

                BX.unbindAll(editBtn);
                BX.bind(editBtn, 'click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    // Находим контейнер адреса
                    var addressItem = event.target.closest('.adress-user-list__item');
                    if (!addressItem) return;

                    // Получаем данные адреса
                    var radioInput = addressItem.querySelector('input[name="address_id"]');
                    var addressId = radioInput ? radioInput.getAttribute('data-id') : '';
                    var addressText = radioInput ? radioInput.value : '';

                    console.log('Редактирование адреса ID:', addressId, 'Текст:', addressText);

                    // Открываем модальное окно в режиме редактирования
                    self.openAddAddressModal('edit', {
                        id: addressId,
                        address: addressText,
                        lat: radioInput ? radioInput.getAttribute('data-lat') : null,
                        lon: radioInput ? radioInput.getAttribute('data-lon') : null
                    });
                });
            }
        },
// ==============================================
// МЕТОДЫ ДЛЯ ПОДСКАЗОК АДРЕСА (Яндекс.Геокодер)
// ==============================================

        /**
         * Инициализация подсказок адреса
         */
        initAddressSuggest: function() {
            var self = this;

            if (typeof ymaps === 'undefined') {
                var script = document.createElement('script');
                script.src = 'https://api-maps.yandex.ru/2.1/?lang=ru_RU';
                script.onload = function() {
                    ymaps.ready(function() {
                        self.setupAddressSuggest();
                    });
                };
                document.head.appendChild(script);
            } else {
                ymaps.ready(function() {
                    self.setupAddressSuggest();
                });
            }
        },

        /**
         * Настройка подсказок для поля ввода адреса
         */
        setupAddressSuggest: function() {
            var self = this;
            var addressInput = document.querySelector('.modal-add-address input[name="ADDRESS"]');

            if (!addressInput) return;

            // Создаем карту (невидимую) для получения bounds
            var map = new ymaps.Map(document.createElement('div'), {
                center: [54.7355, 55.9587],
                zoom: 11
            });

            // Создаем контейнер для подсказок
            var suggestionsContainer = document.createElement('div');
            suggestionsContainer.className = 'address-suggestions';
            suggestionsContainer.style.cssText = 'position:absolute;background:white;border:1px solid #ddd;border-radius:4px;max-height:200px;overflow-y:auto;z-index:1002;display:none;box-shadow:0 2px 8px rgba(0,0,0,0.15);';

            addressInput.parentNode.style.position = 'relative';
            addressInput.parentNode.appendChild(suggestionsContainer);

            var suggestTimeout;

            addressInput.addEventListener('input', function() {
                clearTimeout(suggestTimeout);
                var query = this.value.trim();

                if (query.length < 3) {
                    suggestionsContainer.style.display = 'none';
                    return;
                }

                suggestTimeout = setTimeout(function() {
                    self.searchAddressSuggestions(query, suggestionsContainer, addressInput, map);
                }, 300);
            });

            document.addEventListener('click', function(e) {
                if (!addressInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                    suggestionsContainer.style.display = 'none';
                }
            });

            addressInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') suggestionsContainer.style.display = 'none';
            });
        },

        /**
         * Поиск подсказок через геокодер Яндекса
         */
        searchAddressSuggestions: function(query, container, input, map) {
            var bounds = map.getBounds();

            ymaps.geocode(query, {
                boundedBy: bounds,
                strictBounds: true,
                results: 5
            }).then(function(res) {
                var suggestions = res.geoObjects;

                if (suggestions.length === 0) {
                    container.style.display = 'none';
                    return;
                }

                container.innerHTML = '';

                suggestions.each(function(suggestion) {
                    var address = suggestion.getAddressLine();
                    var coords = suggestion.geometry.getCoordinates();

                    var item = document.createElement('div');
                    item.textContent = address;
                    item.style.cssText = 'padding:8px 12px;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:14px;';

                    item.addEventListener('mouseenter', function() { this.style.backgroundColor = '#f5f5f5'; });
                    item.addEventListener('mouseleave', function() { this.style.backgroundColor = 'white'; });
                    item.addEventListener('click', function() {
                        input.value = address;
                        input.setAttribute('data-lat', coords[0]);
                        input.setAttribute('data-lon', coords[1]);
                        container.style.display = 'none';
                    });

                    container.appendChild(item);
                });

                var rect = input.getBoundingClientRect();
                container.style.top = (rect.bottom + window.scrollY) + 'px';
                container.style.left = (rect.left + window.scrollX) + 'px';
                container.style.width = rect.width + 'px';
                container.style.display = 'block';

            }).catch(function(error) {
                console.error('Ошибка поиска подсказок:', error);
                container.style.display = 'none';
            });
        },

        /**
         * Открывает модальное окно добавления адреса
         */
        /**
         * Открывает модальное окно добавления/редактирования адреса
         * @param {string} mode - режим: 'add' или 'edit'
         * @param {object} addressData - данные адреса для редактирования
         */
        openAddAddressModal: function(mode, addressData) {
            var modal = document.querySelector('.modal-add-address');
            var wrp = document.querySelector('.wrp');

            if (!modal || !wrp) return;

            // Находим форму
            var form = modal.querySelector('form');
            if (!form) return;

            // Очищаем форму
            form.reset();

            // Устанавливаем атрибут type в зависимости от режима
            if (mode === 'edit') {
                form.setAttribute('type', 'edit');

                // Заполняем поля данными адреса
                var addressInput = modal.querySelector('input[name="ADDRESS"]');
                if (addressInput && addressData && addressData.address) {
                    addressInput.value = addressData.address;
                }

                // Если есть ID адреса, добавляем скрытое поле
                var hiddenIdInput = modal.querySelector('input[name="ADDRESS_ID"]');
                if (!hiddenIdInput && addressData && addressData.id) {
                    hiddenIdInput = document.createElement('input');
                    hiddenIdInput.type = 'hidden';
                    hiddenIdInput.name = 'ADDRESS_ID';
                    hiddenIdInput.value = addressData.id;
                    form.appendChild(hiddenIdInput);
                } else if (hiddenIdInput && addressData && addressData.id) {
                    hiddenIdInput.value = addressData.id;
                }

                // Меняем текст кнопки
                var submitBtn = modal.querySelector('.add-btn');
                if (submitBtn) {
                    submitBtn.textContent = 'Сохранить';
                }
            } else {
                form.setAttribute('type', 'add');

                // Удаляем скрытое поле с ID, если есть
                var hiddenIdInput = modal.querySelector('input[name="ADDRESS_ID"]');
                if (hiddenIdInput) {
                    hiddenIdInput.remove();
                }

                // Меняем текст кнопки обратно
                var submitBtn = modal.querySelector('.add-btn');
                if (submitBtn) {
                    submitBtn.textContent = 'Добавить';
                }
            }

            // Убираем предыдущие ошибки
            var errorDiv = modal.querySelector('.error-message');
            if (errorDiv) {
                errorDiv.remove();
            }

            // Сохраняем текущий режим в data-атрибуте модального окна
            modal.setAttribute('data-mode', mode);

            // Показываем модальное окно
            BX.addClass(wrp, 'show');
            BX.addClass(modal, 'show');

            // Фокус на поле ввода
            var addressInput = modal.querySelector('input[name="ADDRESS"]');
            if (addressInput) {
                setTimeout(function() {
                    addressInput.focus();
                }, 100);
            }
        },

        /**
         * Закрывает модальное окно добавления адреса
         */
        closeAddAddressModal: function() {
            var modal = document.querySelector('.modal-add-address');
            var wrp = document.querySelector('.wrp');

            if (modal && wrp) {
                BX.removeClass(wrp, 'show');
                BX.removeClass(modal, 'show');
            }
        },

        /**
         * Добавляет новый адрес через AJAX
         * @param {HTMLFormElement} form - форма с данными адреса
         */
        /**
         * Добавляет или редактирует адрес через AJAX
         * @param {HTMLFormElement} form - форма с данными адреса
         */
        addAddress: function(form) {
            var self = this;
            var addressInput = form.querySelector('input[name="ADDRESS"]');
            var address = addressInput ? addressInput.value.trim() : '';
            var mode = form.getAttribute('type') || 'add';

            // Получаем координаты из data-атрибутов (если были выбраны через подсказки)
            var lat = addressInput ? addressInput.getAttribute('data-lat') : null;
            var lon = addressInput ? addressInput.getAttribute('data-lon') : null;

            if (!address) {
                this.showAddressFormError('Пожалуйста, введите адрес');
                return;
            }

            var submitBtn = form.querySelector('.add-btn');
            var originalBtnText = submitBtn ? submitBtn.textContent : (mode === 'edit' ? 'Сохранить' : 'Добавить');

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = mode === 'edit' ? 'Сохранение...' : 'Добавление...';
            }

            var sessidInput = form.querySelector('input[name="sessid"]');
            var sessid = sessidInput ? sessidInput.value : BX.bitrix_sessid();

            var data = {
                action: mode === 'edit' ? 'editAddress' : 'addAddress',
                address: address,
                lat: lat,
                lon: lon,
                sessid: sessid
            };

            // Если режим редактирования, добавляем ID адреса
            if (mode === 'edit') {
                var addressIdInput = form.querySelector('input[name="ADDRESS_ID"]');
                if (addressIdInput) {
                    data.addressId = addressIdInput.value;
                }
            }

            BX.ajax.runComponentAction('opensource:order', mode === 'edit' ? 'editAddress' : 'addAddress', {
                mode: 'class',
                dataType: 'json',
                data: { dataAddress: data }
            })
                .then(function(response) {
                    console.log('Ответ при ' + (mode === 'edit' ? 'редактировании' : 'добавлении') + ' адреса:', response);

                    if (response.data && response.data.success) {

                        self.closeAddAddressModal();

                        // Очищаем data-атрибуты
                        if (addressInput) {
                            addressInput.removeAttribute('data-lat');
                            addressInput.removeAttribute('data-lon');
                        }

                        // Обновляем список адресов
                        if (response.data.addressListHtml) {
                            self.updateAddressList(response.data.addressListHtml);
                        } else {
                            // Если сервер не вернул HTML, перезагружаем страницу или обновляем через AJAX
                            location.reload();
                        }

                        BX.onCustomEvent('OnAddressChanged', [{
                            mode: mode,
                            address: response.data.address,
                            addressId: response.data.addressId,
                            lat: response.data.lat,
                            lon: response.data.lon
                        }]);
                    } else {
                        var errorMsg = response.data && response.data.error
                            ? response.data.error
                            : (mode === 'edit' ? 'Ошибка при редактировании адреса' : 'Ошибка при добавлении адреса');
                        self.showAddressFormError(errorMsg);
                    }

                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalBtnText;
                    }
                })
                .catch(function(error) {
                    console.error('AJAX ошибка:', error);
                    self.showAddressFormError('Ошибка соединения с сервером');

                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalBtnText;
                    }
                });
        },

        /**
         * Показывает ошибку в форме добавления адреса
         * @param {string} message - текст ошибки
         */
        showAddressFormError: function(message) {
            var modal = document.querySelector('.modal-add-address');
            if (!modal) return;

            // Удаляем старую ошибку, если есть
            var oldError = modal.querySelector('.error-message');
            if (oldError) {
                oldError.remove();
            }

            // Создаем элемент с ошибкой
            var errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.style.color = 'red';
            errorDiv.style.fontSize = '12px';
            errorDiv.style.marginTop = '5px';
            errorDiv.style.marginBottom = '10px';
            errorDiv.textContent = message;

            // Вставляем ошибку после поля ввода
            var formBlock = modal.querySelector('.form-block-address');
            if (formBlock) {
                formBlock.appendChild(errorDiv);
            }

            // Подсвечиваем поле ввода
            var addressInput = modal.querySelector('input[name="ADDRESS"]');
            if (addressInput) {
                BX.addClass(addressInput, 'error');
                setTimeout(function() {
                    BX.removeClass(addressInput, 'error');
                }, 3000);
            }
        },

        /**
         * Обновляет список адресов на странице
         * @param {string} html - HTML-код нового списка адресов
         */
        updateAddressList: function(html) {
            var addressListContainer = document.querySelector('.adress-user-list');
            if (addressListContainer && html) {
                addressListContainer.innerHTML = html;
                // Переинициализируем обработчики для новых адресов
                this.initAddressDelete();
                // Перепривязываем обработчики редактирования
                this.bindEditAddressButtons();
            }
        },

        /**
         * Добавляет новый адрес в DOM вручную (если сервер не вернул готовый HTML)
         * @param {string} address - текст адреса
         * @param {number|string} addressId - ID адреса
         */
        addAddressToDom: function(address, addressId) {
            var addressList = document.querySelector('.adress-user-list');
            if (!addressList) return;

            // Создаем новый элемент адреса
            var addressItem = document.createElement('div');
            addressItem.className = 'adress-user-list__item';

            // Создаем радио-кнопку
            var radioId = 'address_' + addressId;
            var radioHtml = '<label class="adress-user-list__label">' +
                '<input type="radio" name="address_id" value="' + addressId + '" data-id="' + addressId + '">' +
                '<span class="adress-user-list__text">' + this.escapeHtml(address) + '</span>' +
                '</label>' +
                '<div class="adress-user-list__item-btn-delete"></div>';

            addressItem.innerHTML = radioHtml;

            // Добавляем в конец списка
            addressList.appendChild(addressItem);

            // Инициализируем обработчик удаления для нового адреса
            this.initAddressDelete();

            // Автоматически выбираем добавленный адрес
            var radio = addressItem.querySelector('input[name="address_id"]');
            if (radio) {
                radio.checked = true;
                // Триггер события выбора адреса, если нужно
                BX.onCustomEvent('OnAddressSelected', [{
                    addressId: addressId,
                    address: address
                }]);
            }
        },

        /**
         * Экранирует HTML-спецсимволы для безопасной вставки
         * @param {string} str - строка для экранирования
         * @returns {string} - экранированная строка
         */
        escapeHtml: function(str) {
            if (!str) return '';
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },

        //
        // * Инициализация блока с граммами
        //
        initGramsBlock: function() {
        this.gramsTotalBlock.node = document.querySelector('.total-grams-block');
        if (!this.gramsTotalBlock.node) return;

        this.gramsTotalBlock.totalGramsNode = this.gramsTotalBlock.node.querySelector('.total-grams-value');
        this.updateGramsDisplay();
    },

        /**
         * Инициализация блока с итоговыми суммами заказа
         * Находит все DOM-элементы для отображения сумм
         */
        initTotalBlock: function() {
            this.totalBlock.node = document.querySelector('.total-order-block');
            if (!this.totalBlock.node) return;

            // Поиск всех элементов с суммами по их классам
            this.totalBlock.addressNode = this.totalBlock.node.querySelector('.adress-text');
            this.totalBlock.addressValueNode = this.totalBlock.node.querySelector('.adress-value');
            this.totalBlock.deliveryNode = this.totalBlock.node.querySelector('.delivery-text');
            this.totalBlock.deliveryPriceNode = this.totalBlock.node.querySelector('.delivery-price');
            this.totalBlock.baseSumNode = this.totalBlock.node.querySelector('.total-price');
            this.totalBlock.discountNode = this.totalBlock.node.querySelector('.total-skidka');
            this.totalBlock.bonusNode = this.totalBlock.node.querySelector('.total-bonus');
            this.totalBlock.totalNode = this.totalBlock.node.querySelector('.total-value');

            this.updateTotalBlockData();
            console.log('Total block initialized', this.totalBlock);
        },

        /**
         * Обновляет сохраненные данные блока итогов
         * Извлекает текущие значения из DOM
         */
        updateTotalBlockData: function() {
            if (this.totalBlock.addressValueNode) {
                this.totalBlock.currentData.address = this.totalBlock.addressValueNode.textContent;
            }

            if (this.totalBlock.deliveryPriceNode) {
                this.totalBlock.currentData.deliveryPrice = this.extractPrice(this.totalBlock.deliveryPriceNode);
            }

            if (this.totalBlock.baseSumNode) {
                this.totalBlock.currentData.baseSum = this.extractPrice(this.totalBlock.baseSumNode);
            }

            if (this.totalBlock.discountNode) {
                this.totalBlock.currentData.discount = this.extractPrice(this.totalBlock.discountNode);
            }

            if (this.totalBlock.bonusNode) {
                this.totalBlock.currentData.bonus = this.extractPrice(this.totalBlock.bonusNode);
            }

            if (this.totalBlock.totalNode) {
                this.totalBlock.currentData.total = this.extractPrice(this.totalBlock.totalNode);
            }
        },

        /**
         * Обновляет все итоговые блоки новыми значениями
         * @param {object} data - объект с новыми суммами
         */
        updateAllTotals: function(data) {
            if (data.deliveryPrice !== undefined) {
                this.updateDeliveryPrice(data.deliveryPrice);
            }

            if (data.baseSum !== undefined) {
                this.updateBaseSum(data.baseSum);
            }

            if (data.discount !== undefined) {
                this.updateDiscount(data.discount);
            }

            if (data.total !== undefined) {
                this.updateTotal(data.total);
            }
        },

        /**
         * Собирает данные о всех товарах в корзине
         * Создает объект basketItems с информацией о каждом товаре
         */
        initializeBasketItems: function() {
            var items = document.querySelectorAll('.product-list__item');

            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                var productId = item.getAttribute('data-id');

                if (productId) {
                    this.basketItems[productId] = {
                        node: item,                                      // DOM-элемент товара
                        quantityNode: item.querySelector('.quantity-product'), // Элемент с количеством
                        quantity: parseInt(item.querySelector('.quantity-product').textContent) || 0,
                        priceNode: item.querySelector('.price-product__sum'), // Элемент с ценой
                        basePriceNode: item.querySelector('.price-product__base'), // Базовая цена
                        productId: productId,
                        price: this.extractPrice(item.querySelector('.price-product__sum')),
                        weightNode: item.querySelector('.weight')
                    };
                }
            }
        },

        // ==============================================
        // МЕТОДЫ РАБОТЫ С КОЛИЧЕСТВОМ ПЕРСОН
        // ==============================================

        /**
         * Инициализация блока выбора количества персон
         */
        initPersonCount: function() {
            this.personCount.node = document.querySelector('.count-people-block__count');
            if (!this.personCount.node) {
                console.log('personCount.node not found');
                return;
            }

            this.personCount.input = this.personCount.node.querySelector('input[name="properties[COUNT_PERSON]"]');



            if (this.personCount.input) {
                this.personCount.value = parseInt(this.personCount.input.value) || 1;
                console.log('personCount.value:', this.personCount.value);
            }

            this.bindPersonCountEvents();
        },

        /**
         * Привязка обработчиков для кнопок + и - в блоке персон
         */
        bindPersonCountEvents: function() {
            if (!this.personCount.node) return;

            var minusBtn = this.personCount.node.querySelector('.minus');
            var plusBtn = this.personCount.node.querySelector('.plus');

            if (minusBtn) {
                BX.unbindAll(minusBtn);
                BX.bind(minusBtn, 'click', BX.proxy(function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.decreasePersonCount();
                }, this));
            }

            if (plusBtn) {
                BX.unbindAll(plusBtn);
                BX.bind(plusBtn, 'click', BX.proxy(function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.increasePersonCount();
                }, this));
            }
        },

        /** Уменьшает количество персон на 1 */
        decreasePersonCount: function() {
            var newValue = this.personCount.value - 1;
            this.setPersonCount(newValue);
        },

        /** Увеличивает количество персон на 1 */
        increasePersonCount: function() {
            var newValue = this.personCount.value + 1;
            this.setPersonCount(newValue);
        },


        /**
         * Устанавливает новое количество персон с валидацией
         * @param {number} newValue - новое значение
         */
        setPersonCount: function(newValue) {
            if (newValue < this.personCount.minValue) {
                this.showErrorMessage('Минимальное количество персон: ' + this.personCount.minValue, 'info');
                newValue = this.personCount.minValue;
            }

            if (newValue > this.personCount.maxValue) {
                this.showErrorMessage('Максимальное количество персон: ' + this.personCount.maxValue, 'info');
                newValue = this.personCount.maxValue;
            }

            if (newValue === this.personCount.value) return;

            this.personCount.value = newValue;

            // Обновляем ВСЕ поля с именем properties[COUNT_PERSON]
            var allPersonInputs = document.querySelectorAll('input[name="properties[COUNT_PERSON]"]');
            allPersonInputs.forEach(function(input) {
                input.value = newValue;
            });

            // Обновляем поле по ID
            var inputById = document.querySelector('#property_COUNT_PERSON');
            if (inputById) {
                inputById.value = newValue;
            }

            // Обновляем отображение в блоке
            var countDisplay = document.querySelector('.count-people-block__count-num');
            if (countDisplay) {
                countDisplay.value = newValue;
            }
        },

        // ==============================================
        // МЕТОДЫ РАБОТЫ С ЦЕНАМИ И ФОРМАТИРОВАНИЕМ
        // ==============================================

        /**
         * Извлекает число из текстового узла с ценой
         * @param {HTMLElement} node - DOM-элемент с ценой
         * @returns {number} - извлеченное число
         */
        extractPrice: function(node) {
            if (!node) return 0;
            var priceText = node.textContent.replace(/[^\d,.]/g, '').replace(',', '.');
            return parseFloat(priceText) || 0;
        },

        /**
         * Форматирует число в цену с пробелами и символом рубля
         * @param {number} price - цена
         * @param {string} currency - валюта
         * @returns {string} - отформатированная цена
         */
        formatPrice: function(price, currency) {
            price = Math.round(price * 100) / 100;

            if (price % 1 === 0) {
                return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽';
            } else {
                return price.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$& ') + ' ₽';
            }
        },

        // ==============================================
        // МЕТОДЫ ПРИВЯЗКИ СОБЫТИЙ
        // ==============================================

        /**
         * Привязка основных событий: клики и изменения полей
         */
        bindEvents: function() {
            document.addEventListener('click', BX.proxy(this.handleClick, this));
            document.addEventListener('change', BX.proxy(this.handleQuantityChange, this));
            this.bindRemoveOrder();
            this.bindPromoEvents();
        },

        /**
         * Привязка событий к радио-кнопкам выбора доставки
         */
        bindDeliveryEvents: function() {
            var deliveryInputs = document.querySelectorAll('input[name="delivery_id"]');

            if (deliveryInputs.length > 0) {
                for (var i = 0; i < deliveryInputs.length; i++) {
                    deliveryInputs[i].addEventListener('click', BX.proxy(this.handleDeliveryClick, this));
                }
            }
        },

        /**
         * Привязка события к кнопке удаления всей корзины
         */
        bindRemoveOrder: function() {
            var deleteButton = document.querySelector('.delete-order');

            if (deleteButton) {
                BX.unbindAll(deleteButton);
                BX.bind(deleteButton, 'click', BX.proxy(this.handleDeleteOrderClick, this));
            }
        },

        // ==============================================
        // МЕТОДЫ ОБРАБОТКИ СОБЫТИЙ
        // ==============================================

        /** Обработчик клика по кнопке удаления корзины */
        handleDeleteOrderClick: function(event) {
            event.preventDefault();
            event.stopPropagation();
            this.showDeleteCartModal();
        },

        /** Обработчик клика по радио-кнопкам доставки */
        handleDeliveryClick: function(event) {
            var target = event.target;
            var deliveryId = target.value;
            var deliveryName = this.getDeliveryName(target);

            this.onDeliverySelected(deliveryId, deliveryName);
        },

        /**
         * Получает название доставки из выбранного элемента
         * @param {HTMLElement} inputElement - радио-кнопка
         * @returns {string} - название доставки
         */
        getDeliveryName: function(inputElement) {
            var label = inputElement.closest('label');
            if (label) {
                var nameElement = label.querySelector('.delivery-name');
                if (nameElement) {
                    return nameElement.textContent.trim();
                }
            }
            return '';
        },
        // Добавьте эти методы в объект BX.LDO.CustomBasket

// ==============================================
// МЕТОДЫ УПРАВЛЕНИЯ ДОСТАВКОЙ И САМОВЫВОЗОМ
// ==============================================

        /**
         * Инициализация переключения между доставкой и самовывозом
         */
        initDeliveryToggle: function() {
            var self = this;

            // Находим все радио-кнопки доставки
            var deliveryInputs = document.querySelectorAll('input[name="delivery_id"]');

            if (deliveryInputs.length === 0) return;

            // Определяем текущий выбранный способ доставки
            var selectedDeliveryId = null;
            for (var i = 0; i < deliveryInputs.length; i++) {
                if (deliveryInputs[i].checked) {
                    selectedDeliveryId = deliveryInputs[i].value;
                    break;
                }
            }

            // Если есть выбранный по умолчанию, показываем соответствующий блок
            if (selectedDeliveryId) {
                this.toggleDeliveryBlocks(selectedDeliveryId);
            }

            // Добавляем обработчики на все радио-кнопки
            for (var i = 0; i < deliveryInputs.length; i++) {
                BX.unbindAll(deliveryInputs[i]);
                BX.bind(deliveryInputs[i], 'click', function(event) {
                    var target = event.target;
                    var deliveryId = target.value;
                    self.toggleDeliveryBlocks(deliveryId);
                });
            }
        },

        /**
         * Переключает блоки доставки в зависимости от выбранного способа
         * @param {string} deliveryId - ID выбранного способа доставки
         */
        toggleDeliveryBlocks: function(deliveryId) {
            // Блок с адресами доставки
            var dostavkaBlock = document.querySelector('.dostavka-block');
            // Блок с ресторанами для самовывоза
            var samovivozBlock = document.querySelector('.samovivoz-block');

            // Получаем название выбранного способа доставки
            var selectedDeliveryLabel = document.querySelector('input[name="delivery_id"]:checked');
            var deliveryName = '';
            if (selectedDeliveryLabel) {
                var label = selectedDeliveryLabel.closest('label');
                if (label) {
                    var nameElement = label.querySelector('.delivery-name');
                    if (nameElement) {
                        deliveryName = nameElement.textContent.trim();
                    }
                }
            }

            var isPickup = (deliveryName.toLowerCase() === 'самовывоз');

            if (isPickup) {
                // Показываем блок самовывоза, скрываем блок доставки
                if (samovivozBlock) {
                    samovivozBlock.style.display = 'block';
                }
                if (dostavkaBlock) {
                    dostavkaBlock.style.display = 'none';
                }

                // Скрываем цену доставки
                this.hideDeliveryPrice();

                // Меняем заголовок блока времени
                this.updateTimeDeliveryTitle('самовывоза');

            } else {
                // Показываем блок доставки, скрываем блок самовывоза
                if (samovivozBlock) {
                    samovivozBlock.style.display = 'none';
                }
                if (dostavkaBlock) {
                    dostavkaBlock.style.display = 'block';
                }

                // Показываем цену доставки
                this.showDeliveryPrice();

                // Меняем заголовок блока времени
                this.updateTimeDeliveryTitle('доставки');
            }

            // Обновляем итоговый блок (цену доставки, адрес и т.д.)
            this.updateTotalsByDelivery(deliveryId);

            // Обновляем отображение адреса/ресторана в итоговом блоке
            this.updateSelectedInfoDisplay();
        },
        /**
         * Обновляет отображение выбранного адреса/ресторана в итоговом блоке
         */
        updateSelectedInfoDisplay: function() {
            // Проверяем, какая доставка выбрана
            var selectedDelivery = document.querySelector('input[name="delivery_id"]:checked');
            if (!selectedDelivery) return;

            var label = selectedDelivery.closest('label');
            var deliveryName = '';
            if (label) {
                var nameElement = label.querySelector('.delivery-name');
                if (nameElement) {
                    deliveryName = nameElement.textContent.trim();
                }
            }

            var isPickup = (deliveryName.toLowerCase() === 'самовывоз');

            if (isPickup) {
                // Выбран самовывоз - показываем выбранный ресторан
                var selectedRestaurant = document.querySelector('.restorans-list input[name="restoran_id"]:checked');
                if (selectedRestaurant && this.totalBlock.addressValueNode) {
                    this.totalBlock.addressValueNode.textContent = selectedRestaurant.value + ' (самовывоз)';

                    // Обновляем скрытое поле ресторана
                    var restaurantIdInput = document.querySelector('input[name="properties[RESTAURANT_ID]"]');
                    if (restaurantIdInput) {
                        restaurantIdInput.value = selectedRestaurant.getAttribute('data-id') || selectedRestaurant.value;
                    }

                    // Очищаем поле адреса
                    var addressInput = document.querySelector('input[name="properties[ADDRESS]"]');
                    if (addressInput) {
                        addressInput.value = '';
                    }
                } else if (this.totalBlock.addressValueNode) {
                    this.totalBlock.addressValueNode.textContent = 'Не выбран ресторан';
                }
            } else {
                // Выбрана доставка - показываем выбранный адрес
                var selectedAddress = document.querySelector('.adress-user-list input[name="address_id"]:checked');
                if (selectedAddress && this.totalBlock.addressValueNode) {
                    this.totalBlock.addressValueNode.textContent = selectedAddress.value;

                    // Обновляем скрытое поле адреса
                    var addressInput = document.querySelector('input[name="properties[ADDRESS]"]');
                    if (addressInput) {
                        addressInput.value = selectedAddress.value;
                    }

                    // Очищаем поле ресторана
                    var restaurantIdInput = document.querySelector('input[name="properties[RESTAURANT_ID]"]');
                    if (restaurantIdInput) {
                        restaurantIdInput.value = '';
                    }
                } else if (this.totalBlock.addressValueNode) {
                    this.totalBlock.addressValueNode.textContent = 'Не выбран адрес';
                }
            }
        },

        /**
         * Сбрасывает выбранный адрес доставки
         */
        resetSelectedAddress: function() {
            var addressRadios = document.querySelectorAll('.adress-user-list input[name="address_id"]');
            for (var i = 0; i < addressRadios.length; i++) {
                addressRadios[i].checked = false;
            }

            // Обновляем отображение адреса в итоговом блоке
            if (this.totalBlock.addressValueNode) {
                this.totalBlock.addressValueNode.textContent = 'Не выбран';
            }
        },

        /**
         * Сбрасывает выбранный ресторан
         */
        resetSelectedRestaurant: function() {
            var restaurantRadios = document.querySelectorAll('.restorans-list input[name="restoran_id"]');
            for (var i = 0; i < restaurantRadios.length; i++) {
                restaurantRadios[i].checked = false;
            }
        },

        /**
         * Обновляет итоговые суммы в зависимости от выбранной доставки
         * @param {string} deliveryId - ID способа доставки
         */
        updateTotalsByDelivery: function(deliveryId) {
            var self = this;

            // Получаем название выбранного способа доставки
            var selectedDeliveryLabel = document.querySelector('input[name="delivery_id"]:checked');
            var deliveryName = '';
            if (selectedDeliveryLabel) {
                var label = selectedDeliveryLabel.closest('label');
                if (label) {
                    var nameElement = label.querySelector('.delivery-name');
                    if (nameElement) {
                        deliveryName = nameElement.textContent.trim();
                    }
                }
            }

            var isPickup = (deliveryName.toLowerCase() === 'самовывоз');

            // Отправляем AJAX запрос для получения актуальных сумм
            var data = {
                action: 'updateDelivery',
                deliveryId: deliveryId,
                deliveryName: deliveryName,
                sessid: BX.bitrix_sessid()
            };

            BX.ajax.runComponentAction('opensource:order', 'updateDeliveryPrice', {
                mode: 'class',
                dataType: 'json',
                data: { dataDelivery: data }
            })
                .then(function(response) {
                    if (response.data && response.data.success) {
                        // Обновляем цену доставки (если самовывоз - ставим 0)
                        var deliveryPrice = isPickup ? 0 : response.data.deliveryPrice;
                        self.updateDeliveryPrice(deliveryPrice);

                        // Обновляем общую сумму
                        if (response.data.totalPrice !== undefined) {
                            self.updateTotalPriceDisplay(response.data.totalPrice);
                        }

                        // Обновляем итоговые блоки
                        self.updateAllTotals({
                            deliveryPrice: deliveryPrice,
                            baseSum: response.data.baseSum,
                            discount: response.data.discount,
                            total: response.data.totalPrice
                        });
                    }
                })
                .catch(function(error) {
                    console.error('Ошибка обновления стоимости доставки:', error);
                });
        },

        /**
         * Инициализация выбора ресторана для самовывоза
         */
        initRestaurantSelect: function() {
            var self = this;
            var restaurantRadios = document.querySelectorAll('.restorans-list input[name="restoran_id"]');

            for (var i = 0; i < restaurantRadios.length; i++) {
                BX.unbindAll(restaurantRadios[i]);
                BX.bind(restaurantRadios[i], 'click', function(event) {
                    var target = event.target;
                    var restaurantId = target.getAttribute('data-id');
                    var restaurantName = target.value;

                    // Обновляем отображение в итоговом блоке
                    self.updateRestaurantInfo(restaurantId, restaurantName);

                    // Обновляем скрытое поле ресторана
                    var restaurantIdInput = document.querySelector('input[name="properties[RESTAURANT_ID]"]');
                    if (restaurantIdInput) {
                        restaurantIdInput.value = restaurantId || restaurantName;
                    }

                    // Очищаем поле адреса
                    var addressInput = document.querySelector('input[name="properties[ADDRESS]"]');
                    if (addressInput) {
                        addressInput.value = '';
                    }

                    // Обновляем отображение выбранной информации
                    self.updateSelectedInfoDisplay();
                });
            }

            // Также вызываем обновление для уже выбранного по умолчанию ресторана
            this.updateSelectedInfoDisplay();
        },

        /**
         * Обновляет информацию о выбранном ресторане в итоговом блоке
         * @param {string} restaurantId - ID ресторана
         * @param {string} restaurantName - название ресторана
         */
        updateRestaurantInfo: function(restaurantId, restaurantName) {
            if (this.totalBlock.addressValueNode) {
                this.totalBlock.addressValueNode.textContent = restaurantName + ' (самовывоз)';
            }

            // Здесь можно отправить AJAX запрос для обновления цен,
            // если стоимость зависит от выбранного ресторана
            this.updateRestaurantPrice(restaurantId);
        },

        /**
         * Обновляет цены при выборе ресторана
         * @param {string} restaurantId - ID ресторана
         */
        updateRestaurantPrice: function(restaurantId) {
            var self = this;

            var data = {
                action: 'updateRestaurant',
                restaurantId: restaurantId,
                sessid: BX.bitrix_sessid()
            };

            BX.ajax.runComponentAction('opensource:order', 'updateRestaurantPrice', {
                mode: 'class',
                dataType: 'json',
                data: { dataRestaurant: data }
            })
                .then(function(response) {
                    if (response.data && response.data.success) {
                        if (response.data.totalPrice !== undefined) {
                            self.updateTotalPriceDisplay(response.data.totalPrice);
                        }

                        self.updateAllTotals({
                            deliveryPrice: 0, // При самовывозе доставка бесплатна
                            baseSum: response.data.baseSum,
                            discount: response.data.discount,
                            total: response.data.totalPrice
                        });
                    }
                })
                .catch(function(error) {
                    console.error('Ошибка обновления цен ресторана:', error);
                });
        },

        /**
         * Инициализация выбора адреса доставки
         */
        initAddressSelect: function() {
            var self = this;
            var addressRadios = document.querySelectorAll('.adress-user-list input[name="address_id"]');

            for (var i = 0; i < addressRadios.length; i++) {
                BX.unbindAll(addressRadios[i]);
                BX.bind(addressRadios[i], 'click', function(event) {
                    var target = event.target;
                    var addressId = target.getAttribute('data-id');
                    var addressName = target.value;
                    var addressPrice = target.getAttribute('data-price') || 0;

                    // Обновляем отображение в итоговом блоке
                    self.updateAddressInfo(addressId, addressName, addressPrice);

                    // Обновляем скрытое поле
                    var addressInput = document.querySelector('input[name="properties[ADDRESS]"]');
                    if (addressInput) {
                        addressInput.value = addressName;
                    }

                    // Обновляем отображение выбранной информации
                    self.updateSelectedInfoDisplay();
                });
            }

            // Также вызываем обновление для уже выбранного по умолчанию адреса
            this.updateSelectedInfoDisplay();
        },

        /**
         * Обновляет информацию о выбранном адресе в итоговом блоке
         * @param {string} addressId - ID адреса
         * @param {string} addressName - адрес
         * @param {number} addressPrice - стоимость доставки по адресу
         */
        updateAddressInfo: function(addressId, addressName, addressPrice) {
            if (this.totalBlock.addressValueNode) {
                this.totalBlock.addressValueNode.textContent = addressName;
            }

            // Обновляем стоимость доставки, если она зависит от адреса
            if (addressPrice !== undefined && addressPrice != 0) {
                this.updateDeliveryPrice(parseFloat(addressPrice));
                this.updateTotalsByAddress(addressId, addressPrice);
            }
        },

        /**
         * Обновляет итоговые суммы при выборе адреса
         * @param {string} addressId - ID адреса
         * @param {number} deliveryPrice - стоимость доставки
         */
        updateTotalsByAddress: function(addressId, deliveryPrice) {
            var self = this;

            var data = {
                action: 'updateAddress',
                addressId: addressId,
                deliveryPrice: deliveryPrice,
                sessid: BX.bitrix_sessid()
            };

            BX.ajax.runComponentAction('opensource:order', 'updateAddressPrice', {
                mode: 'class',
                dataType: 'json',
                data: { dataAddress: data }
            })
                .then(function(response) {
                    if (response.data && response.data.success) {
                        if (response.data.totalPrice !== undefined) {
                            self.updateTotalPriceDisplay(response.data.totalPrice);
                        }

                        self.updateAllTotals({
                            deliveryPrice: response.data.deliveryPrice,
                            baseSum: response.data.baseSum,
                            discount: response.data.discount,
                            total: response.data.totalPrice
                        });
                    }
                })
                .catch(function(error) {
                    console.error('Ошибка обновления цен по адресу:', error);
                });
        },

        /**
         * Сохраняет состояние выбранного способа доставки для последующей отправки формы
         */
        saveDeliveryState: function() {
            // Получаем выбранный способ доставки
            var selectedDelivery = document.querySelector('input[name="delivery_id"]:checked');
            if (!selectedDelivery) return;

            var label = selectedDelivery.closest('label');
            var deliveryName = '';
            if (label) {
                var nameElement = label.querySelector('.delivery-name');
                if (nameElement) {
                    deliveryName = nameElement.textContent.trim();
                }
            }

            var isPickup = (deliveryName.toLowerCase() === 'самовывоз');

            // Находим поля
            var addressInput = document.querySelector('input[name="properties[ADDRESS]"]');
            var restaurantIdInput = document.querySelector('input[name="properties[RESTAURANT_ID]"]');

            if (isPickup) {
                // Выбран самовывоз - берем выбранный ресторан (если есть)
                var selectedRestaurant = document.querySelector('.restorans-list input[name="restoran_id"]:checked');
                if (selectedRestaurant && restaurantIdInput) {
                    restaurantIdInput.value = selectedRestaurant.getAttribute('data-id') || selectedRestaurant.value;
                }

                // Очищаем поле адреса при самовывозе
                if (addressInput) {
                    addressInput.value = '';
                }
            } else {
                // Выбрана доставка - берем выбранный адрес (если есть)
                var selectedAddressRadio = document.querySelector('.adress-user-list input[name="address_id"]:checked');
                if (selectedAddressRadio && addressInput) {
                    addressInput.value = selectedAddressRadio.value;
                }

                // Очищаем поле ресторана при доставке
                if (restaurantIdInput) {
                    restaurantIdInput.value = '';
                }
            }


            console.log('Сохранение состояния доставки перед отправкой:', {
                isPickup: isPickup,
                address: addressInput ? addressInput.value : 'not found',
                restaurant: restaurantIdInput ? restaurantIdInput.value : 'not found'
            });
        },
        /**
         * Действия при выборе доставки
         * @param {number} deliveryId - ID доставки
         * @param {string} deliveryName - название доставки
         */
        onDeliverySelected: function(deliveryId, deliveryName) {
            // Проверяем по названию, а не по ID
            if (deliveryName.toLowerCase() === 'самовывоз') {
                this.updateTimeDeliveryTitle('самовывоза');
                this.hideDeliveryPrice();  // Скрываем цену доставки
            } else {
                this.updateTimeDeliveryTitle('доставки');
                this.showDeliveryPrice();   // Показываем цену доставки
            }
        },
        /** Показывает блок с ценой доставки */
        showDeliveryPrice: function() {
            var deliveryBlocks = document.querySelectorAll('.delivery-text');
            deliveryBlocks.forEach(function(block) {
                block.style.display = 'flex';
            });
        },
        /**
         * Обновляет заголовок блока времени
         * @param {string} type - тип: 'доставки' или 'самовывоза'
         */
        updateTimeDeliveryTitle: function(type) {
            var timeBlock = document.querySelector('.time-delivery');
            if (!timeBlock) return;

            var title = timeBlock.querySelector('h2');
            if (title) {
                title.textContent = 'Время ' + type;
            }
        },

        /** Скрывает блок с ценой доставки */
        hideDeliveryPrice: function() {
            var deliveryBlocks = document.querySelectorAll('.delivery-text');
            deliveryBlocks.forEach(function(block) {
                block.style.display = 'none';
            });
        },

        /** Показывает/скрывает блок выбора времени доставки */
        toggleTimeDeliveryBlock: function(show) {
            var timeBlock = document.querySelector('.time-delivery');
            if (timeBlock) {
                timeBlock.style.display = show ? 'block' : 'none';
            }
        },

        /** Показывает/скрывает информацию о доставке */
        toggleDeliveryElements: function(view) {
            var deliveryBlocks = document.querySelectorAll('.delivery-text');
            deliveryBlocks.forEach(function(block) {
                block.style.display = view ? 'flex' : 'none';
            });
        },

        /**
         * Обработчик кликов по кнопкам + и - в товарах
         * @param {Event} event - событие клика
         */
        handleClick: function(event) {
            var target = event.target;


            // Обработка клика по кнопке выбора подарка
            if (target.classList.contains('addCartGift')) {
                event.preventDefault();
                event.stopPropagation();

                var giftItem = target.closest('.gifts-list__item');
                var productId = target.getAttribute('id-product');

                if (giftItem && productId) {
                    // Используем BX.proxy для вызова метода с правильным контекстом
                    BX.proxy(this.selectGift, this)(productId, giftItem);
                }
                return;
            }
            //**************************************************//



            var productItem = this.findProductItem(target);

            if (!productItem || !productItem.productId) return;

            var basketItem = this.basketItems[productItem.productId];
            if (!basketItem) return;

            if (this.buttonLocks[basketItem.productId]) return;

            if (target.classList.contains('minus')) {
                this.decreaseQuantity(basketItem);
                event.preventDefault();
                event.stopPropagation();
            }

            if (target.classList.contains('plus')) {
                this.increaseQuantity(basketItem);
                event.preventDefault();
                event.stopPropagation();
            }




        },

        /**
         * Выбор подарка
         */
        selectGift: function(productId, giftItem) {
            var self = this;

            // Если уже выбран этот же подарок - ничего не делаем
            if (this.selectedGiftId === productId) {
                return;
            }

            this.showGiftWarningModal(function() {
                self.applyGiftSelection(productId, giftItem);
           });

        },

        /**
         * Обработчик изменения поля ввода количества
         * @param {Event} event - событие изменения
         */
        handleQuantityChange: function(event) {
            var target = event.target;

            if (target.classList.contains('quantity-input')) {
                var productItem = this.findProductItem(target);

                if (productItem && productItem.productId) {
                    var basketItem = this.basketItems[productItem.productId];
                    var newQuantity = parseInt(target.value) || 0;

                    if (newQuantity > 0 && newQuantity !== basketItem.quantity) {
                        this.updateQuantity(basketItem, newQuantity);
                    }
                }
            }
        },

        /**
         * Находит родительский элемент товара по любому вложенному элементу
         * @param {HTMLElement} element - элемент внутри товара
         * @returns {object|null} - объект с node и productId или null
         */
        findProductItem: function(element) {
            while (element && !element.classList.contains('product-list__item')) {
                element = element.parentElement;
            }

            if (element && element.classList.contains('product-list__item')) {
                return {
                    node: element,
                    productId: element.getAttribute('data-id')
                };
            }
            return null;
        },

        // ==============================================
        // МЕТОДЫ УПРАВЛЕНИЯ КОЛИЧЕСТВОМ ТОВАРОВ
        // ==============================================

        /** Уменьшает количество товара на 1 */
        decreaseQuantity: function(basketItem) {
            // Проверяем, не заблокированы ли кнопки
            if (this.buttonLocks[basketItem.productId]) {
                return; // Если заблокированы, игнорируем клик
            }

            var maxQuantity = this.getMaxQuantity(basketItem);
            var currentQuantity = basketItem.quantity;

            if (currentQuantity > 1) {
                var newQuantity = currentQuantity - 1;
                this.debouncedUpdate(basketItem, newQuantity);
            } else {
                this.showDeleteItemModal(basketItem);
            }
        },

        /** Увеличивает количество товара на 1 */
        increaseQuantity: function(basketItem) {
            // Проверяем, не заблокированы ли кнопки
            if (this.buttonLocks[basketItem.productId]) {
                return; // Если заблокированы, игнорируем клик
            }

            var maxQuantity = this.getMaxQuantity(basketItem);
            var currentQuantity = basketItem.quantity;

            // Проверяем на клиенте перед отправкой
            if (currentQuantity + 1 > maxQuantity) {
                this.showErrorMessage('Доступно только ' + maxQuantity + ' шт.');
                return;
            }

            var newQuantity = currentQuantity + 1;
            this.debouncedUpdate(basketItem, newQuantity);
        },

        /**
         * Обновление с задержкой (debounce) для избежания множества запросов
         * @param {object} basketItem - объект товара
         * @param {number} newQuantity - новое количество
         */
        debouncedUpdate: function(basketItem, newQuantity) {
            var self = this;
            var productId = basketItem.productId;
            var maxQuantity = this.getMaxQuantity(basketItem); // Получаем максимальное количество

            // Проверяем на клиенте, не превышает ли новое количество максимум
            if (newQuantity > maxQuantity) {
                // Если превышает, показываем сообщение и НЕ отправляем запрос
                this.showErrorMessage('Доступно только ' + maxQuantity + ' шт.');

                // Возвращаем старое значение (не даем увеличить)
                this.updateQuantityDisplay(basketItem, basketItem.quantity);
                return;
            }

            // Проверяем минимальное количество
            if (newQuantity < 1) {
                this.showErrorMessage('Количество не может быть меньше 1');
                this.updateQuantityDisplay(basketItem, 1);
                return;
            }

            if (this.debounceTimers[productId]) {
                clearTimeout(this.debounceTimers[productId]);
            }

            this.updateQuantityDisplay(basketItem, newQuantity);
            basketItem.quantity = newQuantity;

            this.debounceTimers[productId] = setTimeout(function() {
                self.sendQuantityUpdate(basketItem, newQuantity, basketItem.quantity);
                delete self.debounceTimers[productId];
            }, 300);
        },

        /**
         * Немедленное обновление количества
         * @param {object} basketItem - объект товара
         * @param {number} newQuantity - новое количество
         */
        updateQuantity: function(basketItem, newQuantity) {
            if (newQuantity < 1) newQuantity = 1;

            var oldQuantity = basketItem.quantity;
            basketItem.quantity = newQuantity;

            this.updateQuantityDisplay(basketItem, newQuantity);
            this.sendQuantityUpdate(basketItem, newQuantity, oldQuantity);
        },

        /**
         * Обновляет отображение количества в DOM
         * @param {object} basketItem - объект товара
         * @param {number} newQuantity - новое количество
         */
        /**
         * Обновляет отображение количества в DOM
         * @param {object} basketItem - объект товара
         * @param {number} newQuantity - новое количество
         */
        updateQuantityDisplay: function(basketItem, newQuantity) {
            if (basketItem.quantityNode) {
                basketItem.quantityNode.textContent = newQuantity;

                // Обновляем состояние кнопок после изменения количества
                this.updateButtonState(basketItem);
            }
            this.addQuantityAnimation(basketItem.node);
        },

        /**
         * Обновляет состояние кнопок в зависимости от текущего количества
         * @param {object} basketItem - объект товара
         */
        updateButtonState: function(basketItem) {
            var minusBtn = basketItem.node.querySelector('.minus');
            var plusBtn = basketItem.node.querySelector('.plus');
            var quantityElement = basketItem.node.querySelector('.quantity-product');
            var maxQuantity = quantityElement ? parseInt(quantityElement.getAttribute('data-max-quantity')) || 999 : 999;
            var currentQuantity = basketItem.quantity;

            if (minusBtn) {
                if (currentQuantity <= 1) {
                    minusBtn.style.pointerEvents = 'none';
                    minusBtn.style.opacity = '0.5';
                } else {
                    minusBtn.style.pointerEvents = 'auto';
                    minusBtn.style.opacity = '1';
                }
            }

            if (plusBtn) {
                if (currentQuantity >= maxQuantity) {
                    plusBtn.style.pointerEvents = 'none';
                    plusBtn.style.opacity = '0.5';
                } else {
                    plusBtn.style.pointerEvents = 'auto';
                    plusBtn.style.opacity = '1';
                }
            }
        },

        /** Добавляет CSS-анимацию при изменении количества */
        addQuantityAnimation: function(itemNode) {
            if (itemNode) {
                BX.addClass(itemNode, 'quantity-updated');
                setTimeout(function() {
                    BX.removeClass(itemNode, 'quantity-updated');
                }, 300);
            }
        },

        /**
         * Отправляет AJAX-запрос на обновление количества товара
         * @param {object} basketItem - объект товара
         * @param {number} newQuantity - новое количество
         * @param {number} oldQuantity - старое количество (для отката при ошибке)
         */
        sendQuantityUpdate: function(basketItem, newQuantity, oldQuantity) {
            var self = this;
            var productId = basketItem.productId;

            // Блокируем кнопки
            this.lockButtons(basketItem, true);

            var data = {
                action: 'updateQuantity',
                productId: productId,
                quantity: newQuantity,
                sessid: BX.bitrix_sessid()
            };

            BX.ajax.runComponentAction('opensource:order', 'addQuantity', {
                mode: 'class',
                dataType: 'json',
                data: { dataProduct: data }
            })
                .then(function(response) {
                    console.log("Ответ от сервера:", response);

                    if (response.data && response.data.success) {
                        // Успешное обновление
                        if (response.data.itemPrice) {
                            self.updateItemPrice(basketItem, response.data.itemPrice);
                        }

                        if (response.data.items) {
                            self.updateAllItemsPrices(response.data.items);
                        }

                        if (response.data.totalPrice) {
                            self.updateTotalPriceDisplay(response.data.totalPrice);
                        }

                        self.updateAllTotals({
                            deliveryPrice: response.data.deliveryPrice,
                            baseSum: response.data.baseSum,
                            discount: response.data.discount,
                            total: response.data.totalPrice
                        });

                        // Обновляем максимальное количество, если пришло с сервера
                        if (response.data.maxQuantity) {
                            var quantityElement = basketItem.node.querySelector('.quantity-product');
                            if (quantityElement) {
                                quantityElement.setAttribute('data-max-quantity', response.data.maxQuantity);
                            }
                        }

                        self.showSuccessMessage('Количество обновлено');
                    } else {
                        // Ошибка - возвращаем старое количество
                        var errorMsg = response.data && response.data.error
                            ? response.data.error
                            : 'Ошибка при обновлении';

                        // Возвращаем именно то количество, которое было до попытки изменения
                        self.revertQuantity(basketItem, oldQuantity);
                        self.showErrorMessage(errorMsg);
                    }

                    // Разблокируем кнопки
                    self.lockButtons(basketItem, false);
                })
                .catch(function(error) {
                    console.error('AJAX ошибка:', error);

                    // При ошибке соединения возвращаем старое количество
                    self.revertQuantity(basketItem, oldQuantity);
                    self.showErrorMessage('Ошибка соединения с сервером');
                    self.lockButtons(basketItem, false);
                });
        },
        /**
         * Получает максимальное количество товара
         * @param {object} basketItem - объект товара
         * @returns {number} - максимальное количество
         */
        getMaxQuantity: function(basketItem) {
            // Ищем элемент с количеством, у которого есть data-max-quantity
            var quantityElement = basketItem.node.querySelector('.quantity-product[data-max-quantity]');
            if (quantityElement) {
                return parseInt(quantityElement.getAttribute('data-max-quantity')) || 999;
            }
            return 999; // Значение по умолчанию
        },

        /**
         * Обновляет цены всех товаров на основе данных с сервера
         * @param {object} itemsData - объект с данными о товарах от сервера
         */
        updateAllItemsPrices: function(itemsData) {
            for (var productId in this.basketItems) {
                if (this.basketItems.hasOwnProperty(productId)) {
                    var basketItem = this.basketItems[productId];

                    // Поиск данных для товара по разным возможным ключам
                    var serverData = itemsData[productId];

                    if (!serverData) {
                        for (var key in itemsData) {
                            if (itemsData.hasOwnProperty(key)) {
                                var item = itemsData[key];
                                if (item.productId == productId ||
                                    item.id == basketItem.quantityNode?.closest('[data-id]')?.getAttribute('data-id') ||
                                    key == productId) {
                                    serverData = item;
                                    break;
                                }
                            }
                        }
                    }

                    if (serverData) {
                        // Определение цены из разных возможных полей
                        if (basketItem.priceNode) {
                            var newPrice = serverData.price !== undefined ? serverData.price :
                                (serverData.sum !== undefined ? serverData.sum : null);

                            if (newPrice !== null) {
                                this.updateItemPrice(basketItem, newPrice);
                            }
                        }

                        if (serverData.unitWeight !== undefined) {
                            this.updateItemWeight(basketItem, serverData.unitWeight);
                        }

                        // Обновление количества, если пришло
                        if (basketItem.quantityNode && serverData.quantity !== undefined) {
                            basketItem.quantityNode.textContent = serverData.quantity;
                            basketItem.quantity = serverData.quantity;
                        }
                    }
                }
            }
        },

        /**
         * Обновляет цены из простого массива цен
         * @param {object} priceArray - объект вида {productId: price}
         */
        updateAllPricesFromArray: function(priceArray) {
            for (var productId in priceArray) {
                if (priceArray.hasOwnProperty(productId) && this.basketItems[productId]) {
                    var basketItem = this.basketItems[productId];
                    var newPrice = priceArray[productId];

                    if (basketItem.priceNode) {
                        this.updateItemPrice(basketItem, newPrice);
                    }
                }
            }
        },

        // ==============================================
        // МЕТОДЫ БЛОКИРОВКИ ИНТЕРФЕЙСА
        // ==============================================

        /**
         * Блокирует/разблокирует кнопки товара во время AJAX-запроса
         * @param {object} basketItem - объект товара
         * @param {boolean} lock - true для блокировки, false для разблокировки
         */
        lockButtons: function(basketItem, lock) {
            var productId = basketItem.productId;
            var itemNode = basketItem.node;

            if (lock) {
                this.buttonLocks[productId] = true;
                BX.addClass(itemNode, 'basket-item-updating');

                var minusBtn = itemNode.querySelector('.minus');
                var plusBtn = itemNode.querySelector('.plus');

                if (minusBtn) {
                    minusBtn.style.pointerEvents = 'none';
                    minusBtn.style.opacity = '0.5';
                }
                if (plusBtn) {
                    plusBtn.style.pointerEvents = 'none';
                    plusBtn.style.opacity = '0.5';
                }
            } else {
                delete this.buttonLocks[productId];
                BX.removeClass(itemNode, 'basket-item-updating');

                var minusBtn = itemNode.querySelector('.minus');
                var plusBtn = itemNode.querySelector('.plus');
                var quantityElement = itemNode.querySelector('.quantity-product');
                var maxQuantity = quantityElement ? parseInt(quantityElement.getAttribute('data-max-quantity')) || 999 : 999;
                var currentQuantity = basketItem.quantity;

                if (minusBtn) {
                    // Разблокируем минус только если количество больше 1
                    if (currentQuantity > 1) {
                        minusBtn.style.pointerEvents = 'auto';
                        minusBtn.style.opacity = '1';
                    } else {
                        minusBtn.style.pointerEvents = 'none';
                        minusBtn.style.opacity = '0.5';
                    }
                }

                if (plusBtn) {
                    // Разблокируем плюс только если количество меньше максимума
                    if (currentQuantity < maxQuantity) {
                        plusBtn.style.pointerEvents = 'auto';
                        plusBtn.style.opacity = '1';
                    } else {
                        plusBtn.style.pointerEvents = 'none';
                        plusBtn.style.opacity = '0.5';
                    }
                }
            }
        },

        /**
         * Возвращает старое количество при ошибке обновления
         * @param {object} basketItem - объект товара
         * @param {number} oldQuantity - старое количество
         */
        revertQuantity: function(basketItem, oldQuantity) {
            basketItem.quantity = oldQuantity;
            this.updateQuantityDisplay(basketItem, oldQuantity);
        },

        // ==============================================
        // МЕТОДЫ ОБНОВЛЕНИЯ ЦЕН И АНИМАЦИИ
        // ==============================================

        /**
         * Обновляет цену конкретного товара
         * @param {object} basketItem - объект товара
         * @param {number} newPrice - новая цена
         */
        updateItemPrice: function(basketItem, newPrice) {
            if (basketItem.priceNode) {
                var currentPrice = this.extractPrice(basketItem.priceNode);

                if (Math.abs(currentPrice - newPrice) > 0.01) {
                    basketItem.priceNode.setAttribute('data-old-price', currentPrice);
                    this.animatePrice(basketItem.priceNode, currentPrice, newPrice);
                    basketItem.price = newPrice;
                } else {
                    basketItem.priceNode.innerHTML = this.formatPrice(newPrice);
                }
            }
        },

        /**
         * Анимирует изменение цены от startPrice до endPrice
         * @param {HTMLElement} node - DOM-элемент с ценой
         * @param {number} startPrice - начальная цена
         * @param {number} endPrice - конечная цена
         */
        animatePrice: function(node, startPrice, endPrice) {
            var self = this;
            var startTime = null;
            var duration = 0;

            function animate(currentTime) {
                if (!startTime) startTime = currentTime;
                var elapsed = currentTime - startTime;
                var progress = Math.min(elapsed / duration, 1);
                var eased = 1 - (1 - progress) * (1 - progress);
                var currentPrice = startPrice + (endPrice - startPrice) * eased;

                node.innerHTML = self.formatPrice(currentPrice);

                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    node.innerHTML = self.formatPrice(endPrice);
                }
            }

            requestAnimationFrame(animate);
        },

        /**
         * Обновляет отображение общей суммы корзины
         * @param {number} totalPrice - новая общая сумма
         */
        updateTotalPriceDisplay: function(totalPrice) {
            if (this.totalPriceNode) {
                var currentTotal = this.extractPrice(this.totalPriceNode);

                if (Math.abs(currentTotal - totalPrice) > 0.01) {
                    this.animatePrice(this.totalPriceNode, currentTotal, totalPrice);
                } else {
                    this.totalPriceNode.innerHTML = this.formatPrice(totalPrice);
                }
            }
        },

        // ==============================================
        // МЕТОДЫ УДАЛЕНИЯ ТОВАРОВ
        // ==============================================

        /** Удаляет товар из корзины */
        removeItem: function(basketItem) {
            this.sendRemoveRequest(basketItem);
        },

        /**
         * Отправляет AJAX-запрос на удаление товара
         * @param {object} basketItem - объект товара
         */
        sendRemoveRequest: function(basketItem) {
            var self = this;
            var currentBasketItem = basketItem;

            var data = {
                action: 'deleteProduct',
                productId: basketItem.productId,
                sessid: BX.bitrix_sessid()
            };

            BX.ajax.runComponentAction('opensource:order', 'deleteProduct', {
                mode: 'class',
                dataType: 'json',
                data: { dataProduct: data }
            })
                .then(function(response) {
                    console.log("Ответ от сервера:", response);

                    if (response.data && response.data.success) {
                        if (response.data.reload == 'Y') {
                            location.reload();
                        } else {
                            // Удаление DOM-элемента товара
                            if (currentBasketItem.node && currentBasketItem.node.parentNode) {
                                currentBasketItem.node.parentNode.removeChild(currentBasketItem.node);
                            }

                            // Удаление из внутреннего хранилища
                            delete self.basketItems[currentBasketItem.productId];

                            // Обновление итоговой суммы
                            if (response.data.totalPrice) {
                                self.updateTotalPriceDisplay(response.data.totalPrice);
                            }

                            self.showSuccessMessage('Товар удален из корзины');

                            // Триггер кастомного события
                            BX.onCustomEvent('OnBasketItemRemove', [{
                                productId: currentBasketItem.productId
                            }]);
                        }
                    }
                })
                .catch(function(error) {
                    console.error('AJAX ошибка:', error);
                    self.showErrorMessage('Ошибка при удалении товара');
                });
        },
        // ==============================================
        // МЕТОДЫ ОБНОВЛЕНИЯ ИТОГОВЫХ БЛОКОВ
        // ==============================================

        /** Обновляет цену доставки */
        updateDeliveryPrice: function(price) {
            if (this.totalBlock.deliveryPriceNode) {
                var currentPrice = this.extractPrice(this.totalBlock.deliveryPriceNode);

                if (Math.abs(currentPrice - price) > 0.01) {
                    this.animatePrice(this.totalBlock.deliveryPriceNode, currentPrice, price);
                    this.totalBlock.currentData.deliveryPrice = price;
                } else {
                    this.totalBlock.deliveryPriceNode.innerHTML = this.formatPrice(price);
                }
            }
        },

        /** Обновляет базовую сумму заказа */
        updateBaseSum: function(price) {
            if (this.totalBlock.baseSumNode) {
                var currentPrice = this.extractPrice(this.totalBlock.baseSumNode);

                if (Math.abs(currentPrice - price) > 0.01) {
                    this.animatePrice(this.totalBlock.baseSumNode, currentPrice, price);
                    this.totalBlock.currentData.baseSum = price;
                } else {
                    this.totalBlock.baseSumNode.innerHTML = this.formatPrice(price);
                }
            }
        },

        /** Обновляет сумму скидки */
        updateDiscount: function(price) {
            if (this.totalBlock.discountNode) {
                var currentPrice = this.extractPrice(this.totalBlock.discountNode);

                if (Math.abs(currentPrice - price) > 0.01) {
                    this.animatePrice(this.totalBlock.discountNode, currentPrice, price);
                    this.totalBlock.currentData.discount = price;
                } else {
                    this.totalBlock.discountNode.innerHTML = this.formatPrice(price);
                }
            }
        },

        /** Обновляет сумму бонусов */
        updateBonus: function(price) {
            if (this.totalBlock.bonusNode) {
                var currentPrice = this.extractPrice(this.totalBlock.bonusNode);

                if (Math.abs(currentPrice - price) > 0.01) {
                    this.animatePrice(this.totalBlock.bonusNode, currentPrice, price);
                    this.totalBlock.currentData.bonus = price;
                } else {
                    this.totalBlock.bonusNode.innerHTML = this.formatPrice(price);
                }
            }
        },
        /** Обновляет вес товаров */
        updateItemWeight: function(basketItem, totalWeight) {
            var weightNode = basketItem.weightNode;
            if (weightNode && totalWeight !== undefined) {
                var formattedWeight = totalWeight + ' г';

                weightNode.textContent = formattedWeight;
            }
        },

        // ==============================================
        // МЕТОДЫ РАБОТЫ С ПРОМОКОДАМИ
        // ==============================================

        /** Привязка событий к полю и кнопке промокода */
        bindPromoEvents: function() {
            var promoButton = document.querySelector('.promoChange button[type="button"]');

            if (promoButton) {
                BX.unbindAll(promoButton);
                BX.bind(promoButton, 'click', BX.proxy(function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.applyPromoCode();
                }, this));
            }

            var promoInput = document.getElementById('promocode');
            if (promoInput) {
                BX.unbindAll(promoInput);
                BX.bind(promoInput, 'keypress', BX.proxy(function(event) {
                    if (event.keyCode === 13) {
                        event.preventDefault();
                        this.applyPromoCode();
                    }
                }, this));
            }
        },

        /**
         * Показывает предупреждение о несовместимости подарка с промокодом/бонусами
         * @param {Function} onConfirm - функция, выполняемая после подтверждения
         */
        showGiftWarningModal: function(onConfirm) {
            var self = this;

            var modal = document.querySelector('.modal-delete');
            var wrp = document.querySelector('.wrp');

            if (!modal || !wrp) {
                // Если нет модального окна, создаем простое confirm
                if (confirm('При выборе подарка использование промокода и списание бонусов будет невозможно. Продолжить?')) {
                    onConfirm();
                }
                return;
            }

            // Настраиваем существующее модальное окно
            var titleNode = modal.querySelector('.top-title');
            var messageNode = modal.querySelector('.text-modal');
            var confirmButton = modal.querySelector('.delete');
            var cancelButton = modal.querySelector('.cancel');

            if (titleNode) {
                titleNode.textContent = 'Внимание!';
            }

            if (messageNode) {
                messageNode.innerHTML = 'При выборе подарка использование промокода и списание бонусов будет невозможно.<br><br>Продолжить?';
            }

            // Очищаем старые обработчики
            if (confirmButton) BX.unbindAll(confirmButton);
            if (cancelButton) BX.unbindAll(cancelButton);

            // Добавляем новые обработчики
            if (confirmButton) {
                BX.bind(confirmButton, 'click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    self.closeModal();
                    onConfirm();
                });

                confirmButton.textContent = 'Да';
            }

            if (cancelButton) {
                BX.bind(cancelButton, 'click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    self.closeModal();
                });

                cancelButton.textContent = 'Отмена';
            }

            // Показываем модальное окно
            BX.addClass(wrp, 'show');
            BX.addClass(modal, 'show');

            // Сохраняем ссылку
            this.gifts.warningModal = modal;
        },

        /**
         * Применяет выбор подарка
         */
        applyGiftSelection: function(productId, giftItem) {
            // Убираем выделение с предыдущего подарка
            if (this.selectedGiftNode) {
                this.selectedGiftNode.classList.remove('selected');
                var prevButton = this.selectedGiftNode.querySelector('.addCartGift');
                if (prevButton) {
                    prevButton.textContent = 'Выбрать';
                }
            }

            // Выделяем новый подарок
            giftItem.classList.add('selected');
            var addButton = giftItem.querySelector('.addCartGift');
            if (addButton) {
                addButton.textContent = 'Выбрано';
            }

            // Сохраняем выбранный подарок
            this.selectedGiftId = productId;
            this.selectedGiftNode = giftItem;

            // ДОБАВИТЬ: присваиваем блоку подарков класс block
            var promoBlock = document.querySelector('.promo-block');
            if (promoBlock) {
                BX.addClass(promoBlock, 'block');
            }

            // TODO: Отправка на сервер
        },

        /** Применяет промокод - отправляет запрос на сервер */
        applyPromoCode: function() {
            var promoInput = document.getElementById('promocode');
            var promoCode = promoInput ? promoInput.value.trim() : '';

            if (!promoCode) {
                this.showErrorMessage('Введите промокод');
                return;
            }

            var data = {
                action: 'addPromokod',
                promokod: promoCode,
                sessid: BX.bitrix_sessid()
            };

            BX.ajax.runComponentAction('opensource:order', 'changePromo', {
                mode: 'class',
                dataType: 'json',
                data: { dataPromo: data }
            })
                .then(function(response) {

                    if (response.data && response.data.success) {
                        // Обновление цен товаров
                        if (response.data.items) {
                            this.updateAllItemsPrices(response.data.items);
                        }

                        // Обновление общей суммы
                        if (response.data.totalPrice) {
                            this.updateTotalPriceDisplay(response.data.totalPrice);
                        }

                        // Обновление итоговых блоков
                        this.updateAllTotals({
                            deliveryPrice: response.data.deliveryPrice,
                            baseSum: response.data.baseSum,
                            discount: response.data.discount,
                            total: response.data.totalPrice
                        });


                        // ДОБАВИТЬ: присваиваем блоку подарков класс block
                        var giftsBlock = document.querySelector('.gifts-block');
                        if (giftsBlock) {
                            BX.addClass(giftsBlock, 'block');
                        }



                    } else {
                        var errorMsg = response.data && response.data.error
                            ? response.data.error
                            : 'Не удалось применить промокод';
                        this.showErrorMessage(errorMsg);
                    }
                }.bind(this))
                .catch(function(error) {
                    console.error('AJAX ошибка:', error);
                    this.showErrorMessage('Ошибка соединения с сервером');
                }.bind(this));
        },

        /** Обновляет итоговую сумму заказа */
        updateTotal: function(price) {
            if (this.totalBlock.totalNode) {
                var currentPrice = this.extractPrice(this.totalBlock.totalNode);

                if (Math.abs(currentPrice - price) > 0.01) {
                    this.animatePrice(this.totalBlock.totalNode, currentPrice, price);
                    this.totalBlock.currentData.total = price;
                } else {
                    this.totalBlock.totalNode.innerHTML = this.formatPrice(price);
                }
            }
        },

        /** Обновляет адрес доставки (заглушка) */
        updateDeliveryAddress: function(address) {
            if (this.totalBlock.addressValueNode) {
                this.totalBlock.addressValueNode.textContent = address;
                this.totalBlock.currentData.address = address;
            }
        },

        /** Заглушка для обратной совместимости */
        updateTotalPrice: function() {
            // Заглушка
        },

        // ==============================================
        // МЕТОДЫ УПРАВЛЕНИЯ МОДАЛЬНЫМИ ОКНАМИ
        // ==============================================

        /**
         * Универсальный метод показа модального окна
         * @param {object} options - настройки окна
         */
        showModal: function(options) {
            var modal = document.querySelector('.modal-delete');
            var wrp = document.querySelector('.wrp');

            if (!modal || !wrp) return;

            this.clearModalHandlers(modal);
            this.setModalContent(modal, options);

            BX.addClass(wrp, 'show');
            BX.addClass(modal, 'show');

            var confirmButton = modal.querySelector('.delete');
            var cancelButton = modal.querySelector('.cancel');
            var closeButton = modal.querySelector('.close-modal');

            if (confirmButton) {
                BX.unbindAll(confirmButton);

                if (options.confirmText) {
                    confirmButton.textContent = options.confirmText;
                }

                BX.bind(confirmButton, 'click', BX.proxy(function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.closeModal();
                    if (options.onConfirm) {
                        options.onConfirm.call(this, options.context);
                    }
                }, this));
            }

            if (cancelButton) {
                BX.unbindAll(cancelButton);
                BX.bind(cancelButton, 'click', BX.proxy(function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.closeModal();
                    if (options.onCancel) {
                        options.onCancel.call(this, options.context);
                    }
                }, this));
            }

            if (closeButton) {
                BX.unbindAll(closeButton);
                BX.bind(closeButton, 'click', BX.proxy(function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.closeModal();
                }, this));
            }
        },

        /** Очищает обработчики кнопок модального окна */
        clearModalHandlers: function(modal) {
            var buttons = modal.querySelectorAll('.delete, .cancel, .close-modal');
            buttons.forEach(function(btn) {
                BX.unbindAll(btn);
            });
        },

        /** Устанавливает контент модального окна */
        setModalContent: function(modal, options) {
            var titleNode = modal.querySelector('.top-title');
            var messageNode = modal.querySelector('.text-modal');

            if (titleNode) {
                titleNode.textContent = options.title || 'Подтверждение';
            }

            if (messageNode) {
                messageNode.textContent = options.message || 'Вы действительно хотите выполнить это действие?';
            }
        },

        /** Закрывает модальное окно */
        closeModal: function() {
            var modal = document.querySelector('.modal-delete');
            var wrp = document.querySelector('.wrp');
            if (modal && wrp) {
                BX.removeClass(wrp, 'show');
                BX.removeClass(modal, 'show');
            }
        },

        // ==============================================
        // СПЕЦИАЛИЗИРОВАННЫЕ МЕТОДЫ МОДАЛЬНЫХ ОКОН
        // ==============================================

        /** Показывает окно подтверждения удаления всей корзины */
        showDeleteCartModal: function() {
            this.showModal({
                title: 'Удалить корзину',
                message: 'Вы действительно хотите удалить всю корзину?',
                confirmText: 'Да',
                onConfirm: function() {
                    this.deleteOrder();
                }
            });
        },

        /** Показывает окно подтверждения удаления товара */
        showDeleteItemModal: function(basketItem) {
            this.showModal({
                title: 'Удалить товар',
                message: 'Вы действительно хотите удалить этот товар из корзины?',
                confirmText: 'Да',
                context: basketItem,
                onConfirm: function(context) {
                    this.removeItem(context);
                }
            });
        },

        /** Показывает информационное окно */
        showInfoModal: function(message, title) {
            this.showModal({
                title: title || 'Информация',
                message: message,
                confirmText: 'Ок',
                onConfirm: function() {}
            });
        },

        /** Показывает окно подтверждения с кастомным действием */
        showConfirmModal: function(title, message, onConfirm) {
            this.showModal({
                title: title,
                message: message,
                confirmText: 'Да',
                onConfirm: onConfirm
            });
        },

        /** Закрывает окно подтверждения (обратная совместимость) */
        closeConfirmModal: function() {
            this.closeModal();
        },

        /** Подтверждает удаление заказа (обратная совместимость) */
        confirmDeleteOrder: function(event) {
            event.preventDefault();
            event.stopPropagation();
            this.closeModal();
            this.deleteOrder();
        },

        /** Удаляет всю корзину через AJAX */
        deleteOrder: function() {
            var data = {
                action: 'deleteCart',
                sessid: BX.bitrix_sessid()
            };

            BX.ajax.runComponentAction('opensource:order', 'removeCart', {
                mode: 'class',
                dataType: 'json',
                data: {dataUser: data}
            })
                .then(function(response) {
                    if (response.data && response.data.success) {
                        location.reload();
                    }
                })
                .catch(function(error) {
                    console.error('AJAX ошибка:', error);
                });
        },

        /**
         * Инициализация обработчиков для удаления адресов
         */
        initAddressDelete: function() {
            var deleteButtons = document.querySelectorAll('.adress-user-list__item-btn-delete');
            var self = this;

            for (var i = 0; i < deleteButtons.length; i++) {
                var button = deleteButtons[i];

                BX.unbindAll(button);
                BX.bind(button, 'click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    var addressItem = event.target.closest('.adress-user-list__item');
                    if (!addressItem) return;

                    // Получаем ID адреса из data-id атрибута
                    var radioInput = addressItem.querySelector('input[name="address_id"]');
                    var addressId = radioInput ? radioInput.getAttribute('data-id') : '';

                    console.log('Удаление адреса ID:', addressId);

                    // Показываем модальное окно
                    self.showDeleteAddressModal(addressId, addressItem);
                });
            }
        },

        /**
         * Показывает модальное окно подтверждения удаления адреса
         */
        showDeleteAddressModal: function(addressId, addressItem) {
            var self = this;

            this.showModal({
                title: 'Удаление адреса',
                message: 'Вы действительно хотите удалить этот адрес?',
                confirmText: 'Да',
                context: {
                    addressId: addressId,
                    addressItem: addressItem
                },
                onConfirm: function(context) {
                    self.deleteAddress(context.addressId, context.addressItem);
                }
            });
        },

        /**
         * Отправляет запрос на удаление адреса
         */
        deleteAddress: function(addressId, addressItem) {
            var self = this;

            // Показываем индикатор загрузки
            if (addressItem) {
                BX.addClass(addressItem, 'deleting');
            }

            var data = {
                action: 'deleteAddress',
                addressId: addressId,
                sessid: BX.bitrix_sessid()
            };

            BX.ajax.runComponentAction('opensource:order', 'deleteAddress', {
                mode: 'class',
                dataType: 'json',
                data: { dataAddress: data }
            })
                .then(function(response) {
                    console.log('Ответ при удалении адреса:', response);

                    // ИСПРАВЛЕНО: проверяем response.data.success
                    if (response.data && response.data.success) {
                        // Удаляем элемент из DOM
                        if (addressItem && addressItem.parentNode) {
                            addressItem.parentNode.removeChild(addressItem);
                        }

                        // Показываем сообщение об успехе (опционально)
                        self.showSuccessMessage('Адрес успешно удален');

                        // Если удален выбранный адрес, сбрасываем отображение
                        var selectedAddress = document.querySelector('.adress-user-list input[name="address_id"]:checked');
                        if (!selectedAddress && this.totalBlock.addressValueNode) {
                            this.totalBlock.addressValueNode.textContent = 'Не выбран';
                        }

                    } else {
                        var errorMsg = response.data && response.data.error
                            ? response.data.error
                            : 'Ошибка при удалении адреса';

                        self.showErrorMessage(errorMsg);
                    }
                }.bind(this))  // ДОБАВЛЕНО: привязываем контекст
                .catch(function(error) {
                    console.error('AJAX ошибка при удалении адреса:', error);
                    self.showErrorMessage('Ошибка соединения с сервером');
                });
        },

        // ==============================================
        // МЕТОДЫ ОТОБРАЖЕНИЯ СООБЩЕНИЙ
        // ==============================================

        /** Показывает сообщение об успехе */
        showSuccessMessage: function(message) {
            console.log('Success:', message);
            // this.showInfoModal(message, 'Успешно');
        },

        /** Показывает сообщение об ошибке */
        showErrorMessage: function(message) {
            console.error('Error:', message);
            this.showModal({
                title: 'Ошибка',
                message: message,
                confirmText: 'Понятно',
                onConfirm: function() {}
            });
        },
        // ==============================================
// МЕТОДЫ ДЛЯ РАБОТЫ С ПРОМО (ПРОМОКОД/БОНУСЫ)
// ==============================================

        /**
         * Инициализация обработчиков для радио-кнопок выбора типа промо
         */
        initPromoType: function() {
            var promoInputs = document.querySelectorAll('input[name="promo_id"]');

            for (var i = 0; i < promoInputs.length; i++) {
                var input = promoInputs[i];

                BX.unbindAll(input);
                BX.bind(input, 'change', BX.proxy(function(event) {
                    this.handlePromoTypeChange(event);
                }, this));
            }
        },

        /**
         * Обработчик изменения типа промо
         * @param {Event} event - событие изменения
         */
        handlePromoTypeChange: function(event) {
            var target = event.target;
            var promoType = target.value; // 'promokod' или 'bonus'

            console.log('Выбран тип промо:', promoType);

            if (promoType === 'promokod') {
                this.showPromoCodeBlock();
            } else if (promoType === 'bonus') {
                this.showBonusBlock();
            }
        },

        /**
         * Показывает блок ввода промокода
         */
        showPromoCodeBlock: function() {
            // TODO: реализовать показ блока промокода
            console.log('Показ блока промокода');

            // Скрываем блок бонусов, если он есть
            this.hideBonusBlock();

            // Показываем блок промокода
            var promoCodeBlock = document.querySelector('.promo-block__left-promokod');
            if (promoCodeBlock) {
                promoCodeBlock.style.display = 'block';
            }
        },

        /**
         * Показывает блок оплаты бонусами
         */
        showBonusBlock: function() {
            // TODO: реализовать показ блока бонусов
            console.log('Показ блока бонусов');

            // Скрываем блок промокода
            this.hidePromoCodeBlock();

            // TODO: показать блок бонусов (если он есть)
            // Если блока для бонусов еще нет, нужно его создать или показать
        },

        /**
         * Скрывает блок промокода
         */
        hidePromoCodeBlock: function() {
            var promoCodeBlock = document.querySelector('.promo-block__left-promokod');
            if (promoCodeBlock) {
                promoCodeBlock.style.display = 'none';
            }
        },

        /**
         * Скрывает блок бонусов
         */
        hideBonusBlock: function() {
            // TODO: скрыть блок бонусов (если он есть)
            console.log('Скрытие блока бонусов');
        },
    };

    // ==============================================
    // ГЛОБАЛЬНЫЕ ФУНКЦИИ И АВТОЗАПУСК
    // ==============================================

    /**
     * Глобальная функция для инициализации корзины извне
     * @param {object} options - параметры компонента
     */
    window.LDOInitBasket = function(options) {
        console.log('LDOInitBasket called with options:', options);
        BX.ready(function() {
            BX.LDO.CustomBasket.init(options);
        });
    };

    // Автоматическая инициализация при наличии товаров на странице
    BX.ready(function() {
        var basketItems = document.querySelectorAll('.product-list__item');
        if (basketItems.length > 0) {
            console.log('Auto-initializing basket component');
            BX.LDO.CustomBasket.init({});
        }
    });
})();