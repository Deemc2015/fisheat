(function () {
    'use strict';

    if (typeof BX === 'undefined') {
        return;
    }

    var COMPONENT = 'ldo:promocodes.list';
    var root = document.getElementById('p-promo');
    if (!root) {
        return;
    }

    var sessid = root.getAttribute('data-sessid') || '';
    var siteId = root.getAttribute('data-site') || 's1';

    var modal = document.getElementById('p-promo-modal');
    var form = document.getElementById('p-promo-form');
    var formError = document.getElementById('p-promo-form-error');
    var modalTitle = document.getElementById('p-promo-modal-title');
    var productSearch = document.getElementById('p-promo-product-search');
    var productList = document.getElementById('p-promo-products');
    var productSelected = document.getElementById('p-promo-product-selected');
    var siteSelect = document.getElementById('p-promo-site');
    var notices = document.getElementById('p-promo-notices');

    var dataById = {};
    var searchTimer = null;

    (function collectPromos() {
        var nodes = root.querySelectorAll('.p-promo__data');
        for (var i = 0; i < nodes.length; i++) {
            try {
                var data = JSON.parse(nodes[i].textContent);
                dataById[String(data.ID)] = data;
            } catch (e) {
                // пропускаем повреждённые данные
            }
        }
    })();

    /**
     * Вызов AJAX-действия компонента.
     */
    function runAction(action, data, onSuccess, onError) {
        data = data || {};
        data.sessid = sessid;

        BX.ajax.runComponentAction(COMPONENT, action, {
            mode: 'class',
            dataType: 'json',
            data: data
        }).then(function (response) {
            var result = response && response.data ? response.data : response;
            if (!result || result.success !== true) {
                if (onError) {
                    onError(result && result.error ? result.error : 'Не удалось выполнить действие.');
                }
                return;
            }
            if (onSuccess) {
                onSuccess(result);
            }
        }).catch(function (error) {
            var message = 'Ошибка соединения. Попробуйте ещё раз.';
            if (error && error.errors && error.errors.length) {
                message = error.errors[0].message;
            }
            if (onError) {
                onError(message);
            }
        });
    }

    /**
     * Уведомление в правом нижнем углу.
     */
    function showNotice(message, type) {
        if (!notices) {
            return;
        }

        var node = document.createElement('div');
        node.className = 'p-promo-notice p-promo-notice--' + (type === 'error' ? 'error' : 'success');
        node.textContent = message;
        notices.appendChild(node);

        window.setTimeout(function () {
            if (node.parentNode) {
                node.parentNode.removeChild(node);
            }
        }, 4000);
    }

    function showFormError(message) {
        if (!formError) {
            return;
        }
        if (!message) {
            formError.hidden = true;
            formError.textContent = '';
            return;
        }
        formError.hidden = false;
        formError.textContent = message;
    }

    /**
     * Показ полей, зависящих от типа промокода.
     */
    function toggleTypeFields() {
        var typeField = form.querySelector('[name="type"]');
        var type = typeField ? typeField.value : 'order';

        form.querySelectorAll('[data-field="discount"]').forEach(function (node) {
            node.hidden = (type === 'product');
        });
        form.querySelectorAll('[data-field="product"]').forEach(function (node) {
            node.hidden = (type !== 'product');
        });
    }

    /**
     * Заполнение выбранного товара.
     */
    function setSelectedProduct(id, name) {
        var hidden = form.querySelector('[name="product_id"]');
        if (hidden) {
            hidden.value = id > 0 ? String(id) : '0';
        }

        if (!productSelected) {
            return;
        }

        if (id > 0) {
            productSelected.hidden = false;
            productSelected.textContent = 'Выбран товар: ' + name + ' (#' + id + ')';
        } else {
            productSelected.hidden = true;
            productSelected.textContent = '';
        }
    }

    /**
     * Открытие формы.
     */
    function openForm(promo) {
        showFormError('');
        form.reset();
        setSelectedProduct(0, '');
        if (productList) {
            productList.hidden = true;
            productList.innerHTML = '';
        }
        if (productSearch) {
            productSearch.value = '';
        }

        var siteField = form.querySelector('[name="site_id"]');
        if (siteField) {
            siteField.value = promo && promo.SITE_ID ? promo.SITE_ID : siteId;
        }

        if (promo) {
            modalTitle.textContent = 'Промокод ' + promo.CODE;
            form.querySelector('[name="id"]').value = promo.ID;
            form.querySelector('[name="code"]').value = promo.CODE;
            form.querySelector('[name="name"]').value = promo.NAME === 'Промокод ' + promo.CODE ? '' : promo.NAME;
            form.querySelector('[name="type"]').value = promo.TYPE;
            form.querySelector('[name="coupon_type"]').value = promo.COUPON_TYPE;
            form.querySelector('[name="max_use"]').value = promo.MAX_USE;
            form.querySelector('[name="user_id"]').value = promo.USER_ID;
            form.querySelector('[name="min_sum"]').value = promo.MIN_SUM > 0 ? promo.MIN_SUM : '';
            form.querySelector('[name="date_from"]').value = promo.DATE_FROM || '';
            form.querySelector('[name="date_to"]').value = promo.DATE_TO || '';
            form.querySelector('[name="active"]').checked = promo.ACTIVE === 'Y';

            if (promo.TYPE === 'product') {
                setSelectedProduct(
                    promo.PRODUCT_ID,
                    promo.PRODUCT && promo.PRODUCT.NAME ? promo.PRODUCT.NAME : ('#' + promo.PRODUCT_ID)
                );
            } else {
                form.querySelector('[name="value"]').value = promo.VALUE > 0 ? promo.VALUE : '';
                form.querySelector('[name="value_type"]').value = promo.IS_PERCENT ? 'percent' : 'sum';
            }
        } else {
            modalTitle.textContent = 'Новый промокод';
            form.querySelector('[name="id"]').value = '0';
            form.querySelector('[name="type"]').value = 'order';
            form.querySelector('[name="coupon_type"]').value = 'one';
            form.querySelector('[name="max_use"]').value = '0';
            form.querySelector('[name="user_id"]').value = '0';
            form.querySelector('[name="value_type"]').value = 'percent';
            form.querySelector('[name="active"]').checked = true;
        }

        toggleTypeFields();
        modal.hidden = false;
    }

    function closeForm() {
        modal.hidden = true;
        showFormError('');
    }

    /**
     * Сериализация формы в объект.
     */
    function serializeForm() {
        var data = {};
        var elements = form.elements;
        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            if (!el.name) {
                continue;
            }
            if (el.type === 'checkbox') {
                data[el.name] = el.checked ? el.value : 'N';
            } else {
                data[el.name] = el.value;
            }
        }

        return data;
    }

    // --- События интерфейса ---

    root.addEventListener('click', function (event) {
        var target = event.target;
        if (!target) {
            return;
        }

        if (target.id === 'p-promo-add') {
            event.preventDefault();
            openForm(null);
            return;
        }

        var closeButton = target.closest('[data-close]');
        if (closeButton) {
            event.preventDefault();
            closeForm();
            return;
        }

        var actionButton = target.closest('[data-action]');
        if (!actionButton) {
            return;
        }

        var action = actionButton.getAttribute('data-action');
        var promoId = actionButton.getAttribute('data-id');

        if (action === 'edit') {
            event.preventDefault();
            openForm(dataById[String(promoId)] || null);
            return;
        }

        if (action === 'delete') {
            event.preventDefault();
            if (!window.confirm('Удалить промокод без возможности восстановления?')) {
                return;
            }
            runAction('deletePromo', { id: promoId }, function () {
                showNotice('Промокод удалён', 'success');
                window.setTimeout(function () { window.location.reload(); }, 600);
            }, function (error) {
                showNotice(error, 'error');
            });
            return;
        }

        if (action === 'toggle') {
            event.preventDefault();
            var nextActive = actionButton.getAttribute('data-active') === 'Y' ? 'N' : 'Y';
            runAction('toggleActive', { id: promoId, active: nextActive }, function () {
                var isActive = nextActive === 'Y';
                actionButton.setAttribute('data-active', nextActive);
                actionButton.classList.toggle('is-active', isActive);
                actionButton.textContent = isActive ? 'Активен' : 'Выключен';
                showNotice(isActive ? 'Промокод включён' : 'Промокод выключен', 'success');
            }, function (error) {
                showNotice(error, 'error');
            });
        }
    });

    form.addEventListener('change', function (event) {
        if (event.target && event.target.name === 'type') {
            toggleTypeFields();
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        showFormError('');

        var data = serializeForm();
        if (!data.code) {
            showFormError('Укажите код промокода.');
            return;
        }

        runAction('savePromo', data, function () {
            showNotice('Промокод сохранён', 'success');
            window.setTimeout(function () { window.location.reload(); }, 600);
        }, function (error) {
            showFormError(error);
        });
    });

    // --- Поиск товара ---

    if (productSearch) {
        productSearch.addEventListener('input', function () {
            var query = productSearch.value.trim();

            if (searchTimer) {
                window.clearTimeout(searchTimer);
            }

            if (query.length < 2) {
                productList.hidden = true;
                productList.innerHTML = '';
                return;
            }

            searchTimer = window.setTimeout(function () {
                runAction('searchProducts', { q: query, site_id: siteId }, function (result) {
                    var items = result.items || [];
                    productList.innerHTML = '';

                    if (!items.length) {
                        productList.hidden = true;
                        return;
                    }

                    items.forEach(function (item) {
                        var option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'p-promo-form__product';
                        option.textContent = item.name + ' (#' + item.id + ')';
                        option.addEventListener('click', function () {
                            setSelectedProduct(item.id, item.name);
                            productSearch.value = '';
                            productList.hidden = true;
                            productList.innerHTML = '';
                        });
                        productList.appendChild(option);
                    });

                    productList.hidden = false;
                }, function (error) {
                    showNotice(error, 'error');
                });
            }, 300);
        });
    }

    if (siteSelect) {
        siteSelect.addEventListener('change', function () {
            window.location.href = window.location.pathname + '?site=' + encodeURIComponent(siteSelect.value);
        });
    }
})();
