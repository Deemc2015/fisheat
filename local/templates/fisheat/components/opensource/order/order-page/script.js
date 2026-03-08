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
                        price: this.extractPrice(item.querySelector('.price-product__sum'))
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
            if (!this.personCount.node) return;

            this.personCount.input = this.personCount.node.querySelector('input[name="properties[COUNT_PERSON]"]');

            if (this.personCount.input) {
                this.personCount.value = parseInt(this.personCount.input.value) || 1;
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

            var oldValue = this.personCount.value;
            this.personCount.value = newValue;

            if (this.personCount.input) {
                this.personCount.input.value = newValue;
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

        /**
         * Действия при выборе доставки
         * @param {number} deliveryId - ID доставки
         * @param {string} deliveryName - название доставки
         */
        onDeliverySelected: function(deliveryId, deliveryName) {
            if (deliveryId == 3){
                this.toggleTimeDeliveryBlock(true);
                this.toggleDeliveryElements('show');
            } else {
                this.toggleTimeDeliveryBlock(false);
                this.toggleDeliveryElements();
            }
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
            if (basketItem.quantity > 1) {
                var newQuantity = basketItem.quantity - 1;
                this.debouncedUpdate(basketItem, newQuantity);
            } else {
                this.showDeleteItemModal(basketItem);
            }
        },

        /** Увеличивает количество товара на 1 */
        increaseQuantity: function(basketItem) {
            var newQuantity = basketItem.quantity + 1;
            this.debouncedUpdate(basketItem, newQuantity);
        },

        /**
         * Обновление с задержкой (debounce) для избежания множества запросов
         * @param {object} basketItem - объект товара
         * @param {number} newQuantity - новое количество
         */
        debouncedUpdate: function(basketItem, newQuantity) {
            var productId = basketItem.productId;

            if (this.debounceTimers[productId]) {
                clearTimeout(this.debounceTimers[productId]);
            }

            this.updateQuantityDisplay(basketItem, newQuantity);
            basketItem.quantity = newQuantity;

            this.debounceTimers[productId] = setTimeout(function() {
                this.sendQuantityUpdate(basketItem, newQuantity);
                delete this.debounceTimers[productId];
            }.bind(this), 300);
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
        updateQuantityDisplay: function(basketItem, newQuantity) {
            if (basketItem.quantityNode) {
                basketItem.quantityNode.textContent = newQuantity;
            }
            this.addQuantityAnimation(basketItem.node);
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
                        // Обновление цены конкретного товара, если пришла
                        if (response.data.itemPrice) {
                            self.updateItemPrice(basketItem, response.data.itemPrice);
                        }

                        // Обновление всех цен, если пришел массив
                        if (response.data.items) {
                            self.updateAllItemsPrices(response.data.items);
                        }

                        // Обновление общей суммы
                        if (response.data.totalPrice) {
                            self.updateTotalPriceDisplay(response.data.totalPrice);
                        }

                        // Обновление итоговых блоков
                        self.updateAllTotals({
                            deliveryPrice: response.data.deliveryPrice,
                            baseSum: response.data.baseSum,
                            discount: response.data.discount,
                            total: response.data.totalPrice
                        });

                        self.showSuccessMessage('Количество обновлено');
                    } else {
                        var errorMsg = response.data && response.data.error
                            ? response.data.error
                            : 'Ошибка при обновлении';

                        self.revertQuantity(basketItem, oldQuantity || basketItem.quantity - 1);
                        self.showErrorMessage(errorMsg);
                    }

                    self.lockButtons(basketItem, false);
                })
                .catch(function(error) {
                    console.error('AJAX ошибка:', error);

                    self.revertQuantity(basketItem, oldQuantity || basketItem.quantity - 1);
                    self.showErrorMessage('Ошибка соединения с сервером');
                    self.lockButtons(basketItem, false);
                });
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

                if (minusBtn) minusBtn.disabled = true;
                if (plusBtn) plusBtn.disabled = true;
            } else {
                delete this.buttonLocks[productId];
                BX.removeClass(itemNode, 'basket-item-updating');

                var minusBtn = itemNode.querySelector('.minus');
                var plusBtn = itemNode.querySelector('.plus');

                if (minusBtn) minusBtn.disabled = false;
                if (plusBtn) plusBtn.disabled = false;
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
            var duration = 300;

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

            console.log('Выбран подарок:', productId);

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
                    console.log("Ответ от сервера:", response);

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

                        this.showSuccessMessage('Промокод успешно применен');
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

                    if ( response.success) {
                        // Удаляем элемент из DOM
                        if (addressItem && addressItem.parentNode) {
                            addressItem.parentNode.removeChild(addressItem);
                        }


                    } else {
                        var errorMsg = response.data && response.data.error
                            ? response.data.error
                            : 'Ошибка при удалении адреса';

                        self.showErrorMessage(errorMsg);

                    }
                })
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
        }
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