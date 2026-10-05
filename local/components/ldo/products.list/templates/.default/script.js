;(function () {
    'use strict';

    // Обработчики вешаем сразу через делегирование на document — они не
    // зависят ни от готовности DOM, ни от момента загрузки script.js
    // (шаблонный script.js может подключаться в <head>).
    var list = null;
    var moreBtn = null;
    var nextPage = 2;

    function initNodes() {
        if (!list) {
            list = document.getElementById('products-list');
        }
        if (!moreBtn) {
            moreBtn = document.getElementById('products-more-btn');
        }
    }

    // Текущая страница подгрузки (данные контейнера)
    function getPageSize() {
        return list ? (parseInt(list.getAttribute('data-page-size'), 10) || 30) : 30;
    }

    function getSortField() {
        return list ? (list.getAttribute('data-sort-field') || 'SORT') : 'SORT';
    }

    function getSortOrder() {
        return list ? (list.getAttribute('data-sort-order') || 'ASC') : 'ASC';
    }

    function setHasMore(hasMore) {
        if (moreBtn) {
            moreBtn.style.display = hasMore ? '' : 'none';
        }
    }

    // Выбранная категория (select сверху)
    function getSectionId() {
        var el = document.getElementById('products-category');
        return el ? (parseInt(el.value, 10) || 0) : 0;
    }

    // Текст живого поиска
    function getSearchQuery() {
        var el = document.getElementById('products-search-input');
        return el ? (el.value || '').trim() : '';
    }

    // Универсальный AJAX-запрос списка: page — номер страницы,
    // replace — true заменяет содержимое списка (фильтр/поиск),
    // false — добавляет в конец (подгрузка).
    function requestItems(page, replace) {
        initNodes();
        if (!list) {
            return null;
        }

        // Данные отправляем через FormData (как в saveProduct) —
        // так гарантированно доходят POST-параметры до getPost().
        var formData = new FormData();
        formData.append('page', page);
        formData.append('pageSize', getPageSize());
        formData.append('sortField', getSortField());
        formData.append('sortOrder', getSortOrder());
        formData.append('sectionId', getSectionId());
        formData.append('q', getSearchQuery());
        formData.append('sessid', BX.bitrix_sessid());

        if (replace && moreBtn) {
            moreBtn.disabled = true;
            moreBtn.textContent = 'Загрузка...';
        }

        return BX.ajax.runComponentAction('ldo:products.list', 'getMore', {
            mode: 'class',
            data: formData
        }).then(function (response) {
            if (moreBtn) {
                moreBtn.disabled = false;
                moreBtn.textContent = 'Показать ещё';
            }

            // Обработка ошибок на уровне Битрикса (status: 'error')
            if (!response || response.status === 'error') {
                var errors = response && response.errors ? response.errors : [];
                var message = errors.length ? errors[0].message : 'Ошибка загрузки товаров';
                alert(message);
                return;
            }

            var data = response.data ? response.data : null;
            if (!data) {
                alert('Не удалось загрузить товары');
                return;
            }

            if (replace) {
                if (typeof data.html === 'string' && data.html !== '') {
                    insertHtml(list, data.html, true);
                } else {
                    list.innerHTML = '<p class="products-empty">Ничего не найдено</p>';
                }
            } else if (typeof data.html === 'string' && data.html !== '') {
                insertHtml(list, data.html, false);
            }

            // После подгрузки/перезагрузки списка заново блокируем кнопки
            // "Изменить", если какая-то карточка уже открыта на редактирование.
            if (activeEditItem) {
                lockOtherEditButtons(activeEditItem);
            }

            setHasMore(!!data.hasMore);
        }).catch(function (error) {
            if (moreBtn) {
                moreBtn.disabled = false;
                moreBtn.textContent = 'Показать ещё';
            }
            alert(error && error.errors ? error.errors[0].message : 'Ошибка соединения с сервером');
        });
    }

    // ===== Подгрузка следующей страницы =====
    function loadMore() {
        initNodes();
        if (!moreBtn || !list || moreBtn.disabled) {
            return;
        }

        moreBtn.disabled = true;
        moreBtn.textContent = 'Загрузка...';

        requestItems(nextPage, false).then(function () {
            nextPage++;
        });
    }

    // ===== Перезагрузка списка при изменении категории / поиска =====
    function reloadList() {
        initNodes();
        if (!list) {
            return;
        }
        // Список будет перерисован — снимаем блокировку кнопок "Изменить".
        unlockEditButtons();
        nextPage = 2;
        requestItems(1, true);
    }

    // Вставка HTML списка с выполнением инлайн-скриптов.
    // Скелеты редакторов описания приходят вместе с html и содержат
    // <script> с BXHtmlEditor.SaveConfig(config) — innerHTML их не выполняет.
    function insertHtml(node, html, replace) {
        if (window.BX && BX.processHTML) {
            var ob = BX.processHTML(html);
            if (replace) {
                node.innerHTML = ob.HTML;
            } else {
                node.insertAdjacentHTML('beforeend', ob.HTML);
            }
            if (ob.STYLE && ob.STYLE.length && BX.loadCSS) {
                BX.loadCSS(ob.STYLE);
            }
            BX.ajax.processScripts(ob.SCRIPT);
        } else if (replace) {
            node.innerHTML = html;
        } else {
            node.insertAdjacentHTML('beforeend', html);
        }
    }

    // ===== Режим редактирования карточки =====
    function getInputs(item) {
        return {
            active: item.querySelector('.product-active'),
            section: item.querySelector('.product-section'),
            sort: item.querySelector('.product-sort'),
            price: item.querySelector('.product-price'),
            quantity: item.querySelector('.product-quantity'),
            weight: item.querySelector('.product-weight'),
            detail: item.querySelector('.product-detail'),
            kallory: item.querySelector('.product-kallory'),
            belki: item.querySelector('.product-belki'),
            giry: item.querySelector('.product-giry'),
            yglevody: item.querySelector('.product-yglevody'),
            seoTitle: item.querySelector('.product-seo-title'),
            seoDescription: item.querySelector('.product-seo-description'),
            detailPicture: item.querySelector('.product-detail-picture')
        };
    }

    // ===== Визуальный редактор описания (Bitrix CHTMLEditor) =====
    // Скелет редактора рендерится сервером скрытым (display=false), а сам
    // редактор создаётся по кнопке "Изменить" через BXHtmlEditor.Show(id).
    var activeEditItem = null;

    function getEditorId(item) {
        return 'pd' + item.getAttribute('data-id');
    }

    function getEditor(item) {
        if (!window.BXHtmlEditor || !BXHtmlEditor.Get) {
            return null;
        }
        return BXHtmlEditor.Get(getEditorId(item)) || null;
    }

    // Текущее HTML описания: редактор -> fallback textarea -> data-атрибут.
    function getDetailHtml(item) {
        var ed = getEditor(item);
        if (ed) {
            try { return ed.GetContent(); } catch (e) {}
        }
        var ta = item.querySelector('.product-detail');
        if (ta) {
            return ta.value;
        }
        var wrap = item.querySelector('.product-detail-wrap');
        return wrap ? (wrap.getAttribute('data-detail') || '') : '';
    }

    function setDetailHtml(item, html) {
        html = html || '';
        var wrap = item.querySelector('.product-detail-wrap');
        if (wrap) {
            wrap.setAttribute('data-detail', html);
        }
        var ed = getEditor(item);
        if (ed) {
            try { ed.SetContent(html); return; } catch (e) {}
        }
        var ta = item.querySelector('.product-detail');
        if (ta) {
            ta.value = html;
        }
    }

    // Поднять редактор для карточки. Разметку редактора (~25КБ на инстанс)
    // грузим по AJAX в момент открытия и вставляем в .product-detail-wrap —
    // чтобы не раздувать исходную страницу списка товаров.
    function openEditor(item) {
        var wrap = item.querySelector('.product-detail-wrap');
        if (!wrap) {
            return;
        }
        var id = getEditorId(item);

        var hasInstance = false;
        if (window.BXHtmlEditor && BXHtmlEditor.Get) {
            hasInstance = !!BXHtmlEditor.Get(id);
        }

        // Редактор уже создан или скелет уже вставлен — просто показываем.
        if (window.BXHtmlEditor && BXHtmlEditor.Show && (hasInstance || wrap.querySelector('.bx-html-editor'))) {
            BXHtmlEditor.Show(null, id);
            setDetailHtml(item, item.getAttribute('data-state-detail') || '');
            return;
        }

        if (wrap.getAttribute('data-editor-loading') === '1') {
            return;
        }
        wrap.setAttribute('data-editor-loading', '1');

        var formData = new FormData();
        formData.append('id', item.getAttribute('data-id'));
        formData.append('sessid', BX.bitrix_sessid());

        BX.ajax.runComponentAction('ldo:products.list', 'getEditor', {
            mode: 'class',
            data: formData
        }).then(function (response) {
            wrap.removeAttribute('data-editor-loading');

            var data = response && response.data ? response.data : null;
            if (!data || !data.html) {
                alert((data && data.error) ? data.error : 'Не удалось открыть редактор описания');
                return;
            }

            insertHtml(wrap, data.html, true);

            if (window.BXHtmlEditor && BXHtmlEditor.Show) {
                BXHtmlEditor.Show(null, id);
                setDetailHtml(item, item.getAttribute('data-state-detail') || '');
            }
        }).catch(function () {
            wrap.removeAttribute('data-editor-loading');
            alert('Ошибка соединения с сервером');
        });
    }

    // Пока одна карточка открыта на редактирование — блокируем кнопки
    // "Изменить" у всех остальных.
    function lockOtherEditButtons(currentItem) {
        activeEditItem = currentItem;
        var items = document.querySelectorAll('.product-item');
        for (var i = 0; i < items.length; i++) {
            if (items[i] === currentItem) {
                continue;
            }
            var btn = items[i].querySelector('[data-action="edit"]');
            if (btn) {
                btn.disabled = true;
                btn.setAttribute('title', 'Сначала завершите редактирование другого товара');
            }
        }
    }

    function unlockEditButtons() {
        activeEditItem = null;
        var btns = document.querySelectorAll('.product-item [data-action="edit"]');
        for (var i = 0; i < btns.length; i++) {
            btns[i].disabled = false;
            btns[i].removeAttribute('title');
        }
    }

    // Переключение табов карточки: "Описание товара" / "SEO описание".
    function switchProductTab(tabBtn) {
        var item = tabBtn.closest('.product-item');
        if (!item) {
            return;
        }
        var name = tabBtn.getAttribute('data-product-tab');

        var tabs = item.querySelectorAll('[data-product-tab]');
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.toggle('is-active', tabs[i] === tabBtn);
        }

        var panes = item.querySelectorAll('[data-product-pane]');
        for (var j = 0; j < panes.length; j++) {
            panes[j].style.display = (panes[j].getAttribute('data-product-pane') === name) ? '' : 'none';
        }

        // Редактор описания находится в скрытой панели — при возврате
        // пересчитываем его размеры.
        if (name === 'main') {
            var editor = getEditor(item);
            if (editor) {
                try { editor.ResizeSceleton(); } catch (e) {}
            }
        }
    }

    function setEditMode(item, editing) {
        var inputs = getInputs(item);
        var editBtn = item.querySelector('[data-action="edit"]');
        var saveBtn = item.querySelector('[data-action="save"]');
        var cancelBtn = item.querySelector('[data-action="cancel"]');
        var extra = item.querySelector('.product-extra');

        Object.keys(inputs).forEach(function (key) {
            // Чекбокс активности всегда доступен (работает без режима редактирования)
            if (key === 'active') {
                return;
            }
            if (inputs[key]) {
                inputs[key].disabled = !editing;
            }
        });

        item.setAttribute('data-editing', editing ? '1' : '0');

        if (extra) extra.style.display = editing ? '' : 'none';
        if (editBtn) editBtn.style.display = editing ? 'none' : '';
        if (saveBtn) saveBtn.style.display = editing ? '' : 'none';
        if (cancelBtn) cancelBtn.style.display = editing ? '' : 'none';
    }

    function rememberState(item) {
        var inputs = getInputs(item);
        item.setAttribute('data-state-active', inputs.active && inputs.active.checked ? 'Y' : 'N');
        item.setAttribute('data-state-section', inputs.section ? inputs.section.value : '0');
        item.setAttribute('data-state-sort', inputs.sort ? inputs.sort.value : '0');
        item.setAttribute('data-state-price', inputs.price ? inputs.price.value : '0');
        item.setAttribute('data-state-quantity', inputs.quantity ? inputs.quantity.value : '0');
        item.setAttribute('data-state-weight', inputs.weight ? inputs.weight.value : '0');
        item.setAttribute('data-state-detail', getDetailHtml(item));
        item.setAttribute('data-state-seo-title', inputs.seoTitle ? inputs.seoTitle.value : '');
        item.setAttribute('data-state-seo-description', inputs.seoDescription ? inputs.seoDescription.value : '');
        item.setAttribute('data-state-kallory', inputs.kallory ? inputs.kallory.value : '');
        item.setAttribute('data-state-belki', inputs.belki ? inputs.belki.value : '');
        item.setAttribute('data-state-giry', inputs.giry ? inputs.giry.value : '');
        item.setAttribute('data-state-yglevody', inputs.yglevody ? inputs.yglevody.value : '');
    }

    function restoreState(item) {
        var inputs = getInputs(item);
        if (inputs.active) inputs.active.checked = item.getAttribute('data-state-active') === 'Y';
        if (inputs.section) inputs.section.value = item.getAttribute('data-state-section') || '0';
        if (inputs.sort) inputs.sort.value = item.getAttribute('data-state-sort') || '0';
        if (inputs.price) inputs.price.value = item.getAttribute('data-state-price') || '0';
        if (inputs.quantity) inputs.quantity.value = item.getAttribute('data-state-quantity') || '0';
        if (inputs.weight) inputs.weight.value = item.getAttribute('data-state-weight') || '0';
        setDetailHtml(item, item.getAttribute('data-state-detail') || '');
        if (inputs.seoTitle) inputs.seoTitle.value = item.getAttribute('data-state-seo-title') || '';
        if (inputs.seoDescription) inputs.seoDescription.value = item.getAttribute('data-state-seo-description') || '';
        if (inputs.kallory) inputs.kallory.value = item.getAttribute('data-state-kallory') || '';
        if (inputs.belki) inputs.belki.value = item.getAttribute('data-state-belki') || '';
        if (inputs.giry) inputs.giry.value = item.getAttribute('data-state-giry') || '';
        if (inputs.yglevody) inputs.yglevody.value = item.getAttribute('data-state-yglevody') || '';
        if (inputs.detailPicture) inputs.detailPicture.value = '';
        setEditMode(item, false);
    }

    // ===== Сохранение товара через контроллер =====
    function saveProduct(item, btn) {
        var id = item.getAttribute('data-id');
        var inputs = getInputs(item);

        var formData = new FormData();
        formData.append('id', id);
        formData.append('active', inputs.active && inputs.active.checked ? 'Y' : 'N');
        formData.append('sectionId', inputs.section ? inputs.section.value : '0');
        formData.append('sort', inputs.sort ? inputs.sort.value : '0');
        formData.append('price', inputs.price ? inputs.price.value : '0');
        formData.append('quantity', inputs.quantity ? inputs.quantity.value : '0');
        formData.append('weight', inputs.weight ? inputs.weight.value : '0');
        formData.append('detail', getDetailHtml(item));
        formData.append('kallory', inputs.kallory ? inputs.kallory.value : '0');
        formData.append('belki', inputs.belki ? inputs.belki.value : '0');
        formData.append('giry', inputs.giry ? inputs.giry.value : '0');
        formData.append('yglevody', inputs.yglevody ? inputs.yglevody.value : '0');
        formData.append('seoTitle', inputs.seoTitle ? inputs.seoTitle.value : '');
        formData.append('seoDescription', inputs.seoDescription ? inputs.seoDescription.value : '');
        if (inputs.detailPicture && inputs.detailPicture.files && inputs.detailPicture.files[0]) {
            formData.append('detailPicture', inputs.detailPicture.files[0]);
        }
        formData.append('sessid', BX.bitrix_sessid());

        btn.disabled = true;
        btn.textContent = 'Сохранение...';

        BX.ajax.runComponentAction('ldo:products.list', 'saveProduct', {
            mode: 'class',
            data: formData
        }).then(function (response) {
            btn.disabled = false;
            btn.textContent = 'Сохранить';

            if (response && response.data && response.data.success) {
                // Сервер возвращает очищенный HTML — синхронизируем редактор,
                // чтобы повторное открытие показало актуальное описание.
                setDetailHtml(item, response.data.detail || '');
                rememberState(item);
                setEditMode(item, false);
                unlockEditButtons();
            } else {
                var msg = (response && response.data && response.data.error)
                    ? response.data.error
                    : 'Ошибка сохранения товара';
                alert(msg);
            }
        }).catch(function () {
            btn.disabled = false;
            btn.textContent = 'Сохранить';
            alert('Ошибка соединения с сервером');
        });
    }

    // ===== Быстрое переключение активности (без режима редактирования) =====
    function toggleActive(item, checkbox) {
        var id = item.getAttribute('data-id');
        var active = checkbox.checked ? 'Y' : 'N';

        var formData = new FormData();
        formData.append('id', id);
        formData.append('active', active);
        formData.append('sessid', BX.bitrix_sessid());

        BX.ajax.runComponentAction('ldo:products.list', 'toggleActive', {
            mode: 'class',
            data: formData
        }).then(function (response) {
            if (!response || !response.data || !response.data.success) {
                checkbox.checked = !checkbox.checked;
                var msg = (response && response.data && response.data.error)
                    ? response.data.error
                    : 'Ошибка обновления активности';
                alert(msg);
            }
        }).catch(function () {
            checkbox.checked = !checkbox.checked;
            alert('Ошибка соединения с сервером');
        });
    }

    // ===== Удаление товара =====
    function deleteProduct(item, btn) {
        var id = item.getAttribute('data-id');

        if (!confirm('Удалить товар #' + id + '?')) {
            return;
        }

        btn.disabled = true;

        var formData = new FormData();
        formData.append('id', id);
        formData.append('sessid', BX.bitrix_sessid());

        BX.ajax.runComponentAction('ldo:products.list', 'deleteProduct', {
            mode: 'class',
            data: formData
        }).then(function (response) {
            if (response && response.data && response.data.success) {
                var parent = item.parentNode;
                if (parent) {
                    parent.removeChild(item);
                }
                // Перезагружаем список с учётом фильтров/поиска
                reloadList();
            } else {
                btn.disabled = false;
                var msg = (response && response.data && response.data.error)
                    ? response.data.error
                    : 'Ошибка удаления товара';
                alert(msg);
            }
        }).catch(function () {
            btn.disabled = false;
            alert('Ошибка соединения с сервером');
        });
    }

    // ===== Делегирование кликов: кнопки карточек и кнопка подгрузки =====
    document.addEventListener('click', function (e) {
        initNodes();

        // Кнопка "Показать ещё"
        var more = e.target.closest('#products-more-btn');
        if (more) {
            loadMore();
            return;
        }

        // Табы карточки (Описание товара / SEO описание)
        var tabBtn = e.target.closest('[data-product-tab]');
        if (tabBtn) {
            switchProductTab(tabBtn);
            return;
        }

        var btn = e.target.closest('[data-action]');
        if (!btn) return;

        var item = btn.closest('.product-item');
        if (!item) return;

        var action = btn.getAttribute('data-action');

        if (action === 'delete') {
            deleteProduct(item, btn);
        } else if (action === 'edit') {
            rememberState(item);
            setEditMode(item, true);
            lockOtherEditButtons(item);
            // Поднимаем редактор описания только для этой карточки.
            openEditor(item);
        } else if (action === 'cancel') {
            restoreState(item);
            unlockEditButtons();
        } else if (action === 'save') {
            saveProduct(item, btn);
        }
    });

    // ===== Фильтр по категории, активность, превью детального фото =====
    document.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'products-category') {
            reloadList();
            return;
        }

        // Быстрое переключение активности (вне режима редактирования)
        if (e.target && e.target.classList && e.target.classList.contains('product-active')) {
            var item = e.target.closest('.product-item');
            if (item && item.getAttribute('data-editing') !== '1') {
                toggleActive(item, e.target);
            }
            return;
        }

        // Превью выбранного детального фото
        if (e.target && e.target.classList && e.target.classList.contains('product-detail-picture')) {
            var item = e.target.closest('.product-item');
            if (item && e.target.files && e.target.files[0]) {
                var img = item.querySelector('.product-detail-photo');
                if (img) {
                    img.src = URL.createObjectURL(e.target.files[0]);
                }
            }
        }
    });

    // ===== Живой поиск по названию (debounce 350мс) =====
    var searchTimer = null;
    document.addEventListener('input', function (e) {
        if (e.target && e.target.id === 'products-search-input') {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(reloadList, 350);
        }
    });
})();
