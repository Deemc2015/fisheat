;(function() {
    'use strict';

    BX.namespace('LDO.CustomBasket');

    BX.LDO.CustomBasket = {
        init: function(options) {
            this.options = options || {};
            this.basketItems = {};
            this.priceAnimationData = {};
            this.debounceTimers = {};
            this.buttonLocks = {};
            this.totalPriceNode = document.querySelector('.basket-total-price');
            this.currency = 'RUB'; // Валюта по умолчанию

            this.initializeBasketItems();
            this.bindEvents();
            this.bindRemoveOrder();
            this.bindDeliveryEvents();
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

        // Извлечение числа из цены
        extractPrice: function(node) {
            if (!node) return 0;
            var priceText = node.textContent.replace(/[^\d,.]/g, '').replace(',', '.');
            return parseFloat(priceText) || 0;
        },

        // Форматирование цены
        formatPrice: function(price, currency) {
            // Округляем до 2 знаков
            price = Math.round(price * 100) / 100;

            // Проверяем, есть ли дробная часть
            if (price % 1 === 0) {
                // Целое число - без копеек
                return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽';
            } else {
                // Есть копейки - с двумя знаками
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
            this.showConfirmModal();
        },

        showConfirmModal: function() {
            var modal = document.querySelector('.modal-delete');
            var wrp = document.querySelector('.wrp');

            if(modal){
                BX.addClass(wrp, 'show');
                BX.addClass(modal, 'show');

                var confirmButton = modal.querySelector('.delete');
                var cancelButton = modal.querySelector('.cancel');
                var closeButton = modal.querySelector('.close-modal');

                if (confirmButton) {
                    BX.unbindAll(confirmButton);
                    BX.bind(confirmButton, 'click', BX.proxy(this.confirmDeleteOrder, this));
                }

                if (cancelButton) {
                    BX.unbindAll(cancelButton);
                    BX.bind(cancelButton, 'click', BX.proxy(this.closeConfirmModal, this));
                }

                if (closeButton) {
                    BX.unbindAll(closeButton);
                    BX.bind(closeButton, 'click', BX.proxy(this.closeConfirmModal, this));
                }
            }
        },

        closeConfirmModal: function() {
            var modal = document.querySelector('.modal-delete');
            var wrp = document.querySelector('.wrp');
            if (modal) {
                BX.removeClass(wrp, 'show');
                BX.removeClass(modal, 'show');
            }
        },

        confirmDeleteOrder: function(event) {
            event.preventDefault();
            event.stopPropagation();
            this.closeConfirmModal();
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
                this.removeItem(basketItem);
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

        // ОСНОВНОЙ МЕТОД ОТПРАВКИ ЗАПРОСА (ОБНОВЛЕН)
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

                        // ВАРИАНТ 1: Если сервер возвращает цены для всех товаров (рекомендуется)
                        if (response.data.items) {
                            self.updateAllItemsPrices(response.data.items);
                        }

                        // ВАРИАНТ 2: Если сервер возвращает массив всех цен
                        if (response.data.allPrices) {
                            self.updateAllPricesFromArray(response.data.allPrices);
                        }

                        // ВАРИАНТ 3: Если сервер возвращает только цену измененного товара
                        if (response.data.itemPrice && !response.data.items) {
                            self.updateItemPrice(basketItem, response.data.itemPrice);
                        }

                        // Обновляем общую сумму корзины
                        if (response.data.totalPrice) {
                            self.updateTotalPriceDisplay(response.data.totalPrice);
                        }

                        // Сохраняем валюту, если пришла
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

        // НОВЫЙ МЕТОД: обновление цен всех товаров из объекта items
        updateAllItemsPrices: function(itemsData) {
            console.log('Обновление цен всех товаров:', itemsData);

            for (var productId in this.basketItems) {
                if (this.basketItems.hasOwnProperty(productId)) {
                    var basketItem = this.basketItems[productId];

                    // Ищем товар в ответе сервера
                    var serverData = itemsData[productId];

                    // Также пробуем найти по числовому ключу
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
                        // Обновляем цену
                        if (basketItem.priceNode && serverData.price) {
                            this.updateItemPrice(basketItem, serverData.price);
                        }

                        // Обновляем количество для синхронизации
                        if (basketItem.quantityNode && serverData.quantity) {
                            basketItem.quantityNode.textContent = serverData.quantity;
                            basketItem.quantity = serverData.quantity;
                        }
                    }
                }
            }
        },


        updateAllPricesFromArray: function(priceArray) {
            console.log('Обновление цен из массива:', priceArray);

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
            if (confirm('Вы уверены, что хотите удалить товар из корзины?')) {
                this.sendRemoveRequest(basketItem);
            }
        },

        sendRemoveRequest: function(basketItem) {
            console.log('Would remove item:', basketItem.productId);
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
        },

        showErrorMessage: function(message) {
            console.error('Error:', message);
            alert('Ошибка: ' + message);
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