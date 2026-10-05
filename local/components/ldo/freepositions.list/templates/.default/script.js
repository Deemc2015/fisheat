;(function () {
    'use strict';

    // id товара -> название (выбранные бесплатные позиции текущего правила)
    var selectedProducts = {};

    function errMsg(error, fallback) {
        return (error && error.errors && error.errors.length)
            ? error.errors[0].message
            : fallback;
    }

    function showError(msg) {
        var box = document.getElementById('fp-message');
        if (!box) return;
        box.textContent = msg;
        box.className = 'fp-message fp-message--error';
        box.style.display = 'block';
        clearTimeout(showError._t);
        showError._t = setTimeout(function () { box.style.display = 'none'; }, 4000);
    }

    function chipsNode() { return document.getElementById('fp-chips'); }
    function suggestNode() { return document.getElementById('fp-suggest'); }

    function renderChips() {
        var box = chipsNode();
        if (!box) return;
        box.innerHTML = '';
        var ids = Object.keys(selectedProducts);
        for (var i = 0; i < ids.length; i++) {
            (function (id) {
                var chip = document.createElement('span');
                chip.className = 'fp-chip';
                chip.setAttribute('data-pid', id);

                var label = document.createElement('span');
                label.className = 'fp-chip__name';
                label.textContent = selectedProducts[id];
                chip.appendChild(label);

                var remove = document.createElement('span');
                remove.className = 'fp-chip__remove';
                remove.textContent = '×';
                remove.addEventListener('click', function () {
                    delete selectedProducts[id];
                    renderChips();
                });
                chip.appendChild(remove);

                box.appendChild(chip);
            })(ids[i]);
        }
    }

    function setSectionsChecked(ids) {
        var map = {};
        for (var i = 0; i < ids.length; i++) {
            map[String(ids[i])] = true;
        }
        var boxes = document.querySelectorAll('.fp-section-cb');
        for (var j = 0; j < boxes.length; j++) {
            boxes[j].checked = !!map[boxes[j].value];
        }
    }

    function getCheckedSections() {
        var ids = [];
        var boxes = document.querySelectorAll('.fp-section-cb:checked');
        for (var i = 0; i < boxes.length; i++) {
            ids.push(boxes[i].value);
        }
        return ids;
    }

    function openForm(item) {
        document.getElementById('fp-id').value = item ? item.id : '0';
        document.getElementById('fp-name').value = item ? item.name : '';
        document.getElementById('fp-portions').value = item ? item.portions : '1';
        document.getElementById('fp-site').value = item ? item.site : 's1';

        var sectionIds = [];
        if (item && item.sections) {
            sectionIds = String(item.sections).split(',');
        }
        setSectionsChecked(sectionIds);

        selectedProducts = {};
        if (item && item.products) {
            for (var i = 0; i < item.products.length; i++) {
                selectedProducts[item.products[i].id] = item.products[i].name;
            }
        }
        renderChips();

        document.getElementById('fp-product-search').value = '';
        suggestNode().style.display = 'none';
        suggestNode().innerHTML = '';

        document.getElementById('fp-form').style.display = '';
        document.getElementById('fp-add-btn').style.display = 'none';
    }

    function closeForm() {
        document.getElementById('fp-form').style.display = 'none';
        document.getElementById('fp-add-btn').style.display = '';
        suggestNode().style.display = 'none';
        suggestNode().innerHTML = '';
        document.getElementById('fp-product-search').value = '';
        selectedProducts = {};
        renderChips();
    }

    function getItemData(item) {
        var products = [];
        var productNodes = item.querySelectorAll('.fp-product');
        for (var i = 0; i < productNodes.length; i++) {
            products.push({
                id: productNodes[i].getAttribute('data-pid'),
                name: productNodes[i].getAttribute('data-pname')
            });
        }
        return {
            id: item.getAttribute('data-id'),
            name: item.getAttribute('data-name') || '',
            portions: item.getAttribute('data-portions') || '1',
            site: item.getAttribute('data-site') || 's1',
            sections: item.getAttribute('data-sections') || '',
            products: products
        };
    }

    // ===== Поиск товаров =====
    var searchTimer = null;

    function bindSearch() {
        var input = document.getElementById('fp-product-search');
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

        BX.ajax.runComponentAction('ldo:freepositions.list', 'searchProducts', {
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
                row.className = 'fp-suggest__item';
                row.textContent = it.name;
                row.addEventListener('click', function () {
                    selectedProducts[it.id] = it.name;
                    renderChips();
                    box.style.display = 'none';
                    box.innerHTML = '';
                    document.getElementById('fp-product-search').value = '';
                });
                box.appendChild(row);
            })(items[i]);
        }
        box.style.display = '';
    }

    // ===== Сохранение =====
    function save() {
        var id = parseInt(document.getElementById('fp-id').value, 10) || 0;
        var name = document.getElementById('fp-name').value.trim();
        if (name === '') {
            showError('Введите название.');
            return;
        }

        var btn = document.getElementById('fp-save-btn');
        btn.disabled = true;
        btn.textContent = 'Сохранение...';

        var fd = new FormData();
        fd.append('id', id);
        fd.append('name', name);
        fd.append('portions', document.getElementById('fp-portions').value);
        fd.append('site_id', document.getElementById('fp-site').value);

        var sections = getCheckedSections();
        for (var s = 0; s < sections.length; s++) {
            fd.append('section_ids[]', sections[s]);
        }

        var ids = Object.keys(selectedProducts);
        for (var i = 0; i < ids.length; i++) {
            fd.append('product_ids[]', ids[i]);
        }
        fd.append('sessid', BX.bitrix_sessid());

        BX.ajax.runComponentAction('ldo:freepositions.list', 'save', {
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
                    : 'Не удалось сохранить запись.');
            }
        }).catch(function (error) {
            btn.disabled = false;
            btn.textContent = 'Сохранить';
            showError(errMsg(error, 'Ошибка сети. Запись не сохранена.'));
        });
    }

    // ===== Удаление =====
    function remove(item) {
        var id = item.getAttribute('data-id');
        var name = item.getAttribute('data-name') || id;
        if (!confirm('Удалить правило «' + name + '»?')) {
            return;
        }

        var fd = new FormData();
        fd.append('id', id);
        fd.append('sessid', BX.bitrix_sessid());

        BX.ajax.runComponentAction('ldo:freepositions.list', 'delete', {
            mode: 'class',
            data: fd
        }).then(function (response) {
            if (response && response.data && response.data.success) {
                location.reload();
            } else {
                showError((response && response.data && response.data.error)
                    ? response.data.error
                    : 'Не удалось удалить запись.');
            }
        }).catch(function (error) {
            showError(errMsg(error, 'Ошибка сети. Запись не удалена.'));
        });
    }

    BX.ready(function () {
        bindSearch();

        var addBtn = document.getElementById('fp-add-btn');
        if (addBtn) {
            addBtn.addEventListener('click', function () { openForm(null); });
        }

        var cancelBtn = document.getElementById('fp-cancel-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeForm);
        }

        var saveBtn = document.getElementById('fp-save-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', save);
        }

        var list = document.getElementById('fp-list');
        if (list) {
            list.addEventListener('click', function (e) {
                var actionBtn = e.target.closest('[data-action]');
                if (!actionBtn) return;
                var item = actionBtn.closest('.fp-item');
                if (!item) return;
                var action = actionBtn.getAttribute('data-action');
                if (action === 'edit') {
                    openForm(getItemData(item));
                } else if (action === 'delete') {
                    remove(item);
                }
            });
        }
    });
})();
