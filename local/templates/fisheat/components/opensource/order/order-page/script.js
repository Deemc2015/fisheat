;(function() {
    'use strict';

    BX.namespace('LDO.CustomBasket');



    BX.LDO.CustomBasket = {

        personCount: {
            node: null,
            input: null,
            value: 1,
            minValue: 1,
            maxValue: 20 // Максимальное количество персон
        },

        init: function(options) {
            this.options = options || {};
            this.basketItems = {};
            this.priceAnimationData = {};
            this.debounceTimers = {};
            this.buttonLocks = {};
            this.totalPriceNode = document.querySelector('.basket-total-price');
            this.currency = 'RUB';

            this.initializeBasketItems();
            this.bindEvents();
            this.bindRemoveOrder();
            this.bindDeliveryEvents();
            this.initPersonCount();
        },

        // Инициализация данных товаров
        initializeBasketItems: function() {
            var items = document.querySelectorAll('.product-list__item');

            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                var productId = item.getAttribute('data-id');

                if (productId) {
                    this.basketItems[productId] = {
                        node: item,
                        quantityNode: item.querySelector('.quantity-product'),
                        quantity: parseInt(item.querySelector('.quantity-product').textContent) || 0,
                        priceNode: item.querySelector('.price-product__sum'),
                        basePriceNode: item.querySelector('.price-product__base'),
                        productId: productId,
                        price: this.extractPrice(item.querySelector('.price-product__sum'))
                    };
                }
            }
        },

        //Работа с количеством персон

        // Метод инициализации блока персон
        initPersonCount: function() {
            this.personCount.node = document.querySelector('.count-people-block__count');

            if (!this.personCount.node) return;

            this.personCount.input = this.personCount.node.querySelector('input[name="properties[COUNT_PERSON]"]');

            if (this.personCount.input) {
                this.personCount.value = parseInt(this.personCount.input.value) || 1;
            }

            // Добавляем обработчики
            this.bindPersonCountEvents();
        },

// Привязка событий для блока персон
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

// Уменьшение количества персон
        decreasePersonCount: function() {
            var newValue = this.personCount.value - 1;
            this.setPersonCount(newValue);
        },

// Увеличение количества персон
        increasePersonCount: function() {
            var newValue = this.personCount.value + 1;
            this.setPersonCount(newValue);
        },

// Установка количества персон
        setPersonCount: function(newValue) {
            // Валидация
            if (newValue < this.personCount.minValue) {
                this.showErrorMessage('Минимальное количество персон: ' + this.personCount.minValue, 'info');
                newValue = this.personCount.minValue;
            }

            if (newValue > this.personCount.maxValue) {
                this.showErrorMessage('Максимальное количество персон: ' + this.personCount.maxValue, 'info');
                newValue = this.personCount.maxValue;
            }

            // Если значение не изменилось - выходим
            if (newValue === this.personCount.value) return;

            // Сохраняем старое значение
            var oldValue = this.personCount.value;

            // Обновляем значение
            this.personCount.value = newValue;

            // Обновляем поле ввода
            if (this.personCount.input) {
                this.personCount.input.value = newValue;
            }
        },


        // Извлечение числа из цены
        extractPrice: function(node) {
            if (!node) return 0;
            var priceText = node.textContent.replace(/[^\d,.]/g, '').replace(',', '.');
            return parseFloat(priceText) || 0;
        },

        // Форматирование цены
        formatPrice: function(price, currency) {
            price = Math.round(price * 100) / 100;

            if (price % 1 === 0) {
                return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽';
            } else {
                return price.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$& ') + ' ₽';
            }
        },

        // Привязка событий
        bindEvents: function() {
            document.addEventListener('click', BX.proxy(this.handleClick, this));
            document.addEventListener('change', BX.proxy(this.handleQuantityChange, this));
            this.bindRemoveOrder();
        },

        bindDeliveryEvents: function() {
            var deliveryInputs = document.querySelectorAll('input[name="delivery_id"]');

            if (deliveryInputs.length > 0) {
                for (var i = 0; i < deliveryInputs.length; i++) {
                    deliveryInputs[i].addEventListener('click', BX.proxy(this.handleDeliveryClick, this));
                }
            }
        },

        bindRemoveOrder: function() {
            var deleteButton = document.querySelector('.delete-order');

            if (deleteButton) {
                BX.unbindAll(deleteButton);
                BX.bind(deleteButton, 'click', BX.proxy(this.handleDeleteOrderClick, this));
            }
        },

        handleDeleteOrderClick: function(event) {
            event.preventDefault();
            event.stopPropagation();
            this.showDeleteCartModal();
        },

        // === УНИВЕРСАЛЬНОЕ МОДАЛЬНОЕ ОКНО ===

        // Основной метод для показа модального окна
        showModal: function(options) {
            var modal = document.querySelector('.modal-delete');
            var wrp = document.querySelector('.wrp');

            if (!modal || !wrp) return;

            // Очищаем предыдущие обработчики
            this.clearModalHandlers(modal);

            // Устанавливаем контент
            this.setModalContent(modal, options);

            // Показываем окно
            BX.addClass(wrp, 'show');
            BX.addClass(modal, 'show');

            // Настраиваем кнопки
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

        // Очистка обработчиков модального окна
        clearModalHandlers: function(modal) {
            var buttons = modal.querySelectorAll('.delete, .cancel, .close-modal');
            buttons.forEach(function(btn) {
                BX.unbindAll(btn);
            });
        },

        // Установка контента модального окна
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

        // Закрытие модального окна
        closeModal: function() {
            var modal = document.querySelector('.modal-delete');
            var wrp = document.querySelector('.wrp');
            if (modal && wrp) {
                BX.removeClass(wrp, 'show');
                BX.removeClass(modal, 'show');
            }
        },

        // === СПЕЦИАЛИЗИРОВАННЫЕ МЕТОДЫ ===

        // Удаление корзины
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

        // Удаление товара
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

        // Информационное окно
        showInfoModal: function(message, title) {
            this.showModal({
                title: title || 'Информация',
                message: message,
                confirmText: 'Ок',
                onConfirm: function() {
                    // Просто закрываем
                }
            });
        },

        // Окно подтверждения с кастомным действием
        showConfirmModal: function(title, message, onConfirm) {
            this.showModal({
                title: title,
                message: message,
                confirmText: 'Да',
                onConfirm: onConfirm
            });
        },

        // Для обратной совместимости
        closeConfirmModal: function() {
            this.closeModal();
        },

        confirmDeleteOrder: function(event) {
            event.preventDefault();
            event.stopPropagation();
            this.closeModal();
            this.deleteOrder();
        },

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

        handleDeliveryClick: function(event) {
            var target = event.target;
            var deliveryId = target.value;
            var deliveryName = this.getDeliveryName(target);

            this.onDeliverySelected(deliveryId, deliveryName);
        },

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

        onDeliverySelected: function(deliveryId, deliveryName) {
            if (deliveryId == 3){
                this.toggleTimeDeliveryBlock(true);
                this.toggleDeliveryElements('show');
            } else {
                this.toggleTimeDeliveryBlock(false);
                this.toggleDeliveryElements();
            }
        },

        toggleTimeDeliveryBlock: function(show) {
            var timeBlock = document.querySelector('.time-delivery');
            if (timeBlock) {
                timeBlock.style.display = show ? 'block' : 'none';
            }
        },

        toggleDeliveryElements: function(view) {
            var deliveryBlocks = document.querySelectorAll('.delivery-text');
            deliveryBlocks.forEach(function(block) {
                block.style.display = view ? 'flex' : 'none';
            });
        },

        handleClick: function(event) {
            var target = event.target;
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

        decreaseQuantity: function(basketItem) {
            if (basketItem.quantity > 1) {
                var newQuantity = basketItem.quantity - 1;
                this.debouncedUpdate(basketItem, newQuantity);
            } else {
                this.showDeleteItemModal(basketItem);
            }
        },

        increaseQuantity: function(basketItem) {
            var newQuantity = basketItem.quantity + 1;
            this.debouncedUpdate(basketItem, newQuantity);
        },

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

        updateQuantity: function(basketItem, newQuantity) {
            if (newQuantity < 1) newQuantity = 1;

            var oldQuantity = basketItem.quantity;
            basketItem.quantity = newQuantity;

            this.updateQuantityDisplay(basketItem, newQuantity);
            this.sendQuantityUpdate(basketItem, newQuantity, oldQuantity);
        },

        updateQuantityDisplay: function(basketItem, newQuantity) {
            if (basketItem.quantityNode) {
                basketItem.quantityNode.textContent = newQuantity;
            }
            this.addQuantityAnimation(basketItem.node);
        },

        addQuantityAnimation: function(itemNode) {
            if (itemNode) {
                BX.addClass(itemNode, 'quantity-updated');
                setTimeout(function() {
                    BX.removeClass(itemNode, 'quantity-updated');
                }, 300);
            }
        },

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

                        if (response.data.items) {
                            self.updateAllItemsPrices(response.data.items);
                        }

                        if (response.data.allPrices) {
                            self.updateAllPricesFromArray(response.data.allPrices);
                        }

                        if (response.data.itemPrice && !response.data.items) {
                            self.updateItemPrice(basketItem, response.data.itemPrice);
                        }

                        if (response.data.totalPrice) {
                            self.updateTotalPriceDisplay(response.data.totalPrice);
                        }

                        if (response.data.currency) {
                            self.currency = response.data.currency;
                        }

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

        updateAllItemsPrices: function(itemsData) {

            for (var productId in this.basketItems) {
                if (this.basketItems.hasOwnProperty(productId)) {
                    var basketItem = this.basketItems[productId];
                    var serverData = itemsData[productId];

                    if (!serverData) {
                        for (var key in itemsData) {
                            if (itemsData.hasOwnProperty(key) &&
                                (key == productId || itemsData[key].productId == productId)) {
                                serverData = itemsData[key];
                                break;
                            }
                        }
                    }

                    if (serverData) {
                        if (basketItem.priceNode && serverData.price) {
                            this.updateItemPrice(basketItem, serverData.price);
                        }

                        if (basketItem.quantityNode && serverData.quantity) {
                            basketItem.quantityNode.textContent = serverData.quantity;
                            basketItem.quantity = serverData.quantity;
                        }
                    }
                }
            }
        },

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

        revertQuantity: function(basketItem, oldQuantity) {
            basketItem.quantity = oldQuantity;
            this.updateQuantityDisplay(basketItem, oldQuantity);
        },

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

        removeItem: function(basketItem) {
            this.sendRemoveRequest(basketItem);
        },

        sendRemoveRequest: function(basketItem) {
            // Сохраняем контекст для использования внутри колбэков
            var self = this;
            var currentBasketItem = basketItem; // Сохраняем товар для обработчика

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
                            // Передаем сохраненный basketItem
                            self.handleRemoveSuccess(currentBasketItem);
                        }
                    }
                })
                .catch(function(error) {
                    console.error('AJAX ошибка:', error);
                    self.showErrorMessage('Ошибка при удалении товара');
                });
        },

        handleRemoveSuccess: function(basketItem) {
            if (basketItem.node && basketItem.node.parentNode) {
                basketItem.node.parentNode.removeChild(basketItem.node);
            }

            delete this.basketItems[basketItem.productId];
            this.updateTotalPrice();
            this.showSuccessMessage('Товар удален из корзины');

            BX.onCustomEvent('OnBasketItemRemove', [{
                productId: basketItem.productId
            }]);
        },

        updateTotalPrice: function() {
            // Заглушка
        },

        showSuccessMessage: function(message) {
            console.log('Success:', message);
            // Можно заменить на информационное окно
            // this.showInfoModal(message, 'Успешно');
        },

        showErrorMessage: function(message) {
            console.error('Error:', message);
            // Используем модальное окно для отображения ошибки
            this.showModal({
                title: 'Ошибка',
                message: message,
                confirmText: 'Понятно',
                onConfirm: function() {
                    // Просто закрываем окно
                }
            });
        }
    };

    window.LDOInitBasket = function(options) {
        console.log('LDOInitBasket called with options:', options);
        BX.ready(function() {
            BX.LDO.CustomBasket.init(options);
        });
    };

    BX.ready(function() {
        var basketItems = document.querySelectorAll('.product-list__item');
        if (basketItems.length > 0) {
            console.log('Auto-initializing basket component');
            BX.LDO.CustomBasket.init({});
        }
    });
})();