;(function () {
    'use strict';

    // id товара -> название (выбранные товары текущего уровня)
    var selected = {};

    function errMsg(error, fallback) {
        return (error && error.errors && error.errors.length)
            ? error.errors[0].message
            : fallback;
    }

    function showError(msg) {
        var box = document.getElementById('gifts-message');
        if (!box) return;
        box.textContent = msg;
        box.style.display = 'block';
        clearTimeout(showError._t);
        showError._t = setTimeout(function () { box.style.display = 'none'; }, 4000);
    }

    function chipsNode() { return document.getElementById('gift-chips'); }
    function suggestNode() { return document.getElementById('gift-suggest'); }

    function renderChips() {
        var box = chipsNode();
        if (!box) return;
        box.innerHTML = '';
        var ids = Object.keys(selected);
        for (var i = 0; i < ids.length; i++) {
            (function (id) {
                var chip = document.createElement('span');
                chip.className = 'p-gifts-chip';
                chip.setAttribute('data-pid', id);

                var label = document.createElement('span');
                label.className = 'p-gifts-chip__name';
                label.textContent = selected[id];
                chip.appendChild(label);

                var remove = document.createElement('span');
                remove.className = 'p-gifts-chip__remove';
                remove.textContent = '×';
                remove.addEventListener('click', function () {
                    delete selected[id];
                    renderChips();
                });
                chip.appendChild(remove);

                box.appendChild(chip);
            })(ids[i]);
        }
    }

    function openForm(level) {
        document.getElementById('gift-id').value = level ? level.id : '0';
        document.getElementById('gift-name').value = level ? level.name : '';
        document.getElementById('gift-sum').value = level ? level.sum : '0';
        document.getElementById('gift-sort').value = level ? level.sort : '500';
        document.getElementById('gift-active').checked = level ? (level.active === 'Y') : true;

        selected = {};
        if (level && level.products) {
            for (var i = 0; i < level.products.length; i++) {
                selected[level.products[i].id] = level.products[i].name;
            }
        }
        renderChips();

        document.getElementById('gift-product-search').value = '';
        suggestNode().style.display = 'none';
        suggestNode().innerHTML = '';

        document.getElementById('gift-form').style.display = '';
        document.getElementById('gift-add-btn').style.display = 'none';
    }

    function closeForm() {
        document.getElementById('gift-form').style.display = 'none';
        document.getElementById('gift-add-btn').style.display = '';
        suggestNode().style.display = 'none';
        suggestNode().innerHTML = '';
        document.getElementById('gift-product-search').value = '';
        selected = {};
        renderChips();
    }

    function getItemLevel(item) {
        var products = [];
        var productNodes = item.querySelectorAll('.p-gifts-product');
        for (var i = 0; i < productNodes.length; i++) {
            products.push({
                id: productNodes[i].getAttribute('data-pid'),
                name: productNodes[i].getAttribute('data-pname')
            });
        }
        return {
            id: item.getAttribute('data-id'),
            name: item.getAttribute('data-name') || '',
            sum: item.getAttribute('data-sum') || '0',
            sort: item.getAttribute('data-sort') || '0',
            active: item.getAttribute('data-active') || 'N',
            products: products
        };
    }

    // ===== Поиск товаров =====
    var searchTimer = null;

    function bindSearch() {
        var input = document.getElementById('gift-product-search');
        if (!input) return;
        input.addEventListener('input', function () {
            clearTimeout(searchTimer);
            var q = input.value.trim();
            if (q.length < 2) {
                suggestNode().style.display = 'none';
                suggestNode().innerHTML = '';
                return;
            }
            searchTimer = setTimeout(function () { doSearch(q); }, 300);
        });
    }

    function doSearch(q) {
        var fd = new FormData();
        fd.append('q', q);
        fd.append('sessid', BX.bitrix_sessid());

        BX.ajax.runComponentAction('ldo:gifts.list', 'searchProducts', {
            mode: 'class',
            data: fd
        }).then(function (response) {
            var items = (response && response.data && response.data.items) ? response.data.items : [];
            renderSuggest(items);
        }).catch(function () {
            suggestNode().style.display = 'none';
        });
    }

    function renderSuggest(items) {
        var box = suggestNode();
        box.innerHTML = '';
        if (!items.length) {
            box.style.display = 'none';
            return;
        }
        for (var i = 0; i < items.length; i++) {
            (function (it) {
                var row = document.createElement('div');
                row.className = 'p-gifts-suggest__item';
                row.textContent = it.name;
                row.addEventListener('click', function () {
                    selected[it.id] = it.name;
                    renderChips();
                    box.style.display = 'none';
                    box.innerHTML = '';
                    document.getElementById('gift-product-search').value = '';
                });
                box.appendChild(row);
            })(items[i]);
        }
        box.style.display = '';
    }

    // ===== Сохранение уровня =====
    function saveLevel() {
        var id = parseInt(document.getElementById('gift-id').value, 10) || 0;
        var name = document.getElementById('gift-name').value.trim();
        if (name === '') {
            showError('Введите название уровня.');
            return;
        }

        var btn = document.getElementById('gift-save-btn');
        btn.disabled = true;
        btn.textContent = 'Сохранение...';

        var fd = new FormData();
        fd.append('id', id);
        fd.append('name', name);
        fd.append('sum', document.getElementById('gift-sum').value);
        fd.append('sort', document.getElementById('gift-sort').value);
        fd.append('active', document.getElementById('gift-active').checked ? 'Y' : 'N');
        var ids = Object.keys(selected);
        for (var i = 0; i < ids.length; i++) {
            fd.append('product_ids[]', ids[i]);
        }
        fd.append('sessid', BX.bitrix_sessid());

        BX.ajax.runComponentAction('ldo:gifts.list', 'saveLevel', {
            mode: 'class',
            data: fd
        }).then(function (response) {
            btn.disabled = false;
            btn.textContent = 'Сохранить';
            if (response && response.data && response.data.success) {
                location.reload();
            } else {
                showError((response && response.data && response.data.error)
                    ? response.data.error
                    : 'Не удалось сохранить уровень.');
            }
        }).catch(function (error) {
            btn.disabled = false;
            btn.textContent = 'Сохранить';
            showError(errMsg(error, 'Ошибка сети. Уровень не сохранён.'));
        });
    }

    // ===== Удаление уровня =====
    function deleteLevel(item) {
        var id = item.getAttribute('data-id');
        var name = item.getAttribute('data-name') || id;
        if (!confirm('Удалить уровень «' + name + '»?')) {
            return;
        }

        var fd = new FormData();
        fd.append('id', id);
        fd.append('sessid', BX.bitrix_sessid());

        BX.ajax.runComponentAction('ldo:gifts.list', 'deleteLevel', {
            mode: 'class',
            data: fd
        }).then(function (response) {
            if (response && response.data && response.data.success) {
                location.reload();
            } else {
                showError((response && response.data && response.data.error)
                    ? response.data.error
                    : 'Не удалось удалить уровень.');
            }
        }).catch(function (error) {
            showError(errMsg(error, 'Ошибка сети. Уровень не удалён.'));
        });
    }

    BX.ready(function () {
        bindSearch();

        var addBtn = document.getElementById('gift-add-btn');
        if (addBtn) {
            addBtn.addEventListener('click', function () { openForm(null); });
        }

        var cancelBtn = document.getElementById('gift-cancel-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeForm);
        }

        var saveBtn = document.getElementById('gift-save-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', saveLevel);
        }

        var list = document.getElementById('gifts-list');
        if (list) {
            list.addEventListener('click', function (e) {
                var actionBtn = e.target.closest('[data-action]');
                if (!actionBtn) return;
                var item = actionBtn.closest('.p-gifts-item');
                if (!item) return;
                var action = actionBtn.getAttribute('data-action');
                if (action === 'edit') {
                    openForm(getItemLevel(item));
                } else if (action === 'delete') {
                    deleteLevel(item);
                }
            });
        }
    });
})();
