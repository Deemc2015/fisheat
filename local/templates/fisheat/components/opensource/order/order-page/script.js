;(function() {
    'use strict';

    BX.namespace('LDO.CustomBasket');

    BX.LDO.CustomBasket = {
        init: function(options) {
            this.options = options || {};
            this.basketItems = {};
            this.initializeBasketItems();
            this.bindEvents();
            this.bindRemoveOrder();
            this.bindDeliveryEvents(); // Добавляем привязку событий доставки
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
                        productId: productId
                    };
                }
            }
        },

        // Привязка событий
        bindEvents: function() {
            // События на кнопки +/- для всех товаров
            document.addEventListener('click', BX.proxy(this.handleClick, this));

            // Событие изменения количества (если будем делать input)
            document.addEventListener('change', BX.proxy(this.handleQuantityChange, this));

            // Добавляем вызов метода привязки удаления корзины
            this.bindRemoveOrder();
        },

        //Функцционал выбора способов доставки
        bindDeliveryEvents: function() {

            var deliveryInputs = document.querySelectorAll('input[name="delivery_id"]');

            // Если есть элементы доставки, вешаем обработчики
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
        // Новый метод для обработки клика по удалению корзины
        handleDeleteOrderClick: function(event) {
            event.preventDefault();
            event.stopPropagation();
            // Показываем модальное окно подтверждения
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


                // Удаляем предыдущие обработчики
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
                // Убираем обработчик с фона
                BX.unbind(modal, 'click', BX.proxy(this.handleModalBackgroundClick, this));
            }
        },
        // Новый метод для подтверждения удаления
        confirmDeleteOrder: function(event) {
            event.preventDefault();
            event.stopPropagation();

            // Закрываем модальное окно
            this.closeConfirmModal();

            // Выполняем удаление корзины
            this.deleteOrder();
        },
        deleteOrder: function() {
            console.log('Отправка запроса на удаление');
        },
        // Функция кликка по доставке
        handleDeliveryClick: function(event) {
            var target = event.target;
            var deliveryId = target.value;

            // Находим название доставки
            var deliveryName = this.getDeliveryName(target);

            console.log('Delivery selected:', deliveryId, deliveryName);

            // Вызываем функцию действия
            this.onDeliverySelected(deliveryId, deliveryName);
        },

        // ПОЛУЧЕНИЕ НАЗВАНИЯ ДОСТАВКИ
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

        // ФУНКЦИЯ ДЕЙСТВИЯ ПРИ ВЫБОРЕ ДОСТАВКИ
        onDeliverySelected: function(deliveryId, deliveryName) {
            /*Усли выбрана доставка, показываем блок с выбором времени доставки*/


            if (deliveryId == 3){
                this.toggleTimeDeliveryBlock();
                this.toggleDeliveryElements('show');
            }
            else{
                this.toggleTimeDeliveryBlock('show');
                this.toggleDeliveryElements();
            }

        },

        // Функция для показа/скрытия блока времени
        toggleTimeDeliveryBlock: function(show) {
            var timeBlock = document.querySelector('.time-delivery');

            if (timeBlock) {
                if (show) {
                    // Показываем блок
                    timeBlock.style.display = 'block';
                } else {
                    // Скрываем блок
                    timeBlock.style.display = 'none';
                    // Дополнительно: сбрасываем выбранные значения времени

                }
            } else {
                console.log('Time delivery block not found');
            }
        },

        /*Удаляем информацию о доставке, если выбран Самовывоз*/
        toggleDeliveryElements: function(view) {
            // Находим ВСЕ блоки с классом delivery-text
            var deliveryBlocks = document.querySelectorAll('.delivery-text');

            if(deliveryBlocks.length > 0) {
                // Используем forEach для перебора
                deliveryBlocks.forEach(function(block) {
                    block.style.display = view ? 'flex' : 'none';
                });
            }
        },


        // Обработчик кликов (существующий код)
        handleClick: function(event) {
            var target = event.target;
            var productItem = this.findProductItem(target);

            if (!productItem || !productItem.productId) {
                return;
            }

            var basketItem = this.basketItems[productItem.productId];

            if (!basketItem) {
                return;
            }

            // Обработка кнопки минус
            if (target.classList.contains('minus')) {
                this.decreaseQuantity(basketItem);
                event.preventDefault();
                event.stopPropagation();
            }

            // Обработка кнопки плюс
            if (target.classList.contains('plus')) {
                this.increaseQuantity(basketItem);
                event.preventDefault();
                event.stopPropagation();
            }
        },

        // Обработчик изменения количества (если будет input)
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

        // Найти родительский элемент товара
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

        // Уменьшение количества
        decreaseQuantity: function(basketItem) {
            if (basketItem.quantity > 1) {
                var newQuantity = basketItem.quantity - 1;
                this.updateQuantity(basketItem, newQuantity);
            } else {
                // Если количество = 1, можно предложить удаление товара
                this.removeItem(basketItem);

            }
        },

        // Увеличение количества
        increaseQuantity: function(basketItem) {
            var newQuantity = basketItem.quantity + 1;
            this.updateQuantity(basketItem, newQuantity);
        },

        // Обновление количества товара
        updateQuantity: function(basketItem, newQuantity) {
            if (newQuantity < 1) {
                newQuantity = 1;
            }

            var oldQuantity = basketItem.quantity;
            basketItem.quantity = newQuantity;

            // Обновляем отображение
            this.updateQuantityDisplay(basketItem, newQuantity);

            // Отправляем запрос на сервер
            this.sendQuantityUpdate(basketItem, newQuantity, oldQuantity);
        },

        // Обновление отображения количества
        updateQuantityDisplay: function(basketItem, newQuantity) {
            if (basketItem.quantityNode) {
                basketItem.quantityNode.textContent = newQuantity;
            }

            // Можно добавить анимацию
            this.addQuantityAnimation(basketItem.node);
        },

        // Анимация изменения количества
        addQuantityAnimation: function(itemNode) {
            if (itemNode) {
                BX.addClass(itemNode, 'quantity-updated');

                setTimeout(function() {
                    BX.removeClass(itemNode, 'quantity-updated');
                }, 300);
            }
        },

        // Отправка запроса на обновление количества
        sendQuantityUpdate: function(basketItem, newQuantity, oldQuantity) {
            // Собираем данные для запроса
            var data = {
                action: 'updateQuantity',
                productId: basketItem.productId,
                quantity: newQuantity,
                sessid: BX.bitrix_sessid()
            };

            if(data){
                BX.ajax.runComponentAction('opensource:order', 'addQuantity', {
                    mode: 'class',
                    dataType: 'json',
                    data: { dataProduct: data }
                })
                    .then(function(response) {
                        console.log(response);
                    })
                    .catch(function(error) {
                        console.error('AJAX ошибка:', error);
                    });
            }


        },

        // Удаление товара
        removeItem: function(basketItem) {
            if (confirm('Вы уверены, что хотите удалить товар из корзины?')) {
                this.sendRemoveRequest(basketItem);
            }
        },

        // Отправка запроса на удаление
        sendRemoveRequest: function(basketItem) {
            console.log('Would remove item:', basketItem.productId);
        },

        // Обработка успешного удаления
        handleRemoveSuccess: function(basketItem) {
            // Удаляем элемент из DOM
            if (basketItem.node && basketItem.node.parentNode) {
                basketItem.node.parentNode.removeChild(basketItem.node);
            }

            // Удаляем из объекта
            delete this.basketItems[basketItem.productId];

            // Обновляем общую сумму
            this.updateTotalPrice();

            // Показываем сообщение
            this.showSuccessMessage('Товар удален из корзины');

            // Отправляем кастомное событие
            BX.onCustomEvent('OnBasketItemRemove', [{
                productId: basketItem.productId
            }]);
        },

        updateTotalPrice: function() {
            // Заглушка
        },

        showSuccessMessage: function(message) {
            alert(message);
        }
    };

    // Функция для инициализации из PHP
    window.LDOInitBasket = function(options) {
        console.log('LDOInitBasket called with options:', options);
        BX.ready(function() {
            BX.LDO.CustomBasket.init(options);
        });
    };

    // Автоматическая инициализация при загрузке DOM
    BX.ready(function() {
        // Проверяем, есть ли элементы корзины на странице
        var basketItems = document.querySelectorAll('.product-list__item');
        if (basketItems.length > 0) {
            console.log('Auto-initializing basket component');
            BX.LDO.CustomBasket.init({});
        }
    });
})();