;(function () {
    'use strict';

    // Обработчики вешаем через делегирование на document — они не зависят
    // от готовности DOM и от момента подключения script.js (может грузиться в <head>).

    var activeEditItem = null;

    // ===== Разметка визуального редактора (грузится по AJAX) =====
    // Форма создания имеет data-id="0" — для неё id редактора "al0".
    function getEditorId(item) {
        return 'al' + (item.getAttribute('data-id') || '0');
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
        var ta = item.querySelector('.action-detail');
        if (ta) {
            return ta.value;
        }
        var wrap = item.querySelector('.action-detail-wrap');
        return wrap ? (wrap.getAttribute('data-detail') || '') : '';
    }

    function setDetailHtml(item, html) {
        html = html || '';
        var wrap = item.querySelector('.action-detail-wrap');
        if (wrap) {
            wrap.setAttribute('data-detail', html);
        }
        var ed = getEditor(item);
        if (ed) {
            try { ed.SetContent(html); return; } catch (e) {}
        }
        var ta = item.querySelector('.action-detail');
        if (ta) {
            ta.value = html;
        }
    }

    // Вставка HTML с выполнением инлайн-скриптов (скелет редактора содержит
    // <script> с BXHtmlEditor.SaveConfig(config) — innerHTML их не исполняет).
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

    // Поднять редактор описания: разметку грузим по AJAX в момент открытия
    // и вставляем в .action-detail-wrap (item — карточка акции или форма создания).
    function openEditor(item) {
        var wrap = item.querySelector('.action-detail-wrap');
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
        formData.append('id', item.getAttribute('data-id') || '0');
        formData.append('sessid', BX.bitrix_sessid());

        BX.ajax.runComponentAction('ldo:actions.list', 'getEditor', {
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

    // ===== Поля карточки / формы создания =====
    function getInputs(item) {
        return {
            name: item.querySelector('.action-name'),
            sort: item.querySelector('.action-sort'),
            active: item.querySelector('.action-active'),
            activeFrom: item.querySelector('.action-active-from'),
            activeTo: item.querySelector('.action-active-to'),
            seoTitle: item.querySelector('.action-seo-title'),
            seoDescription: item.querySelector('.action-seo-description'),
            picture: item.querySelector('.action-picture'),
            // Fallback-поле описания (если модуль fileman недоступен)
            detail: item.querySelector('.action-detail')
        };
    }

    function setEditMode(item, editing) {
        var inputs = getInputs(item);
        Object.keys(inputs).forEach(function (key) {
            if (inputs[key]) {
                inputs[key].disabled = !editing;
            }
        });

        var editBtn = item.querySelector('[data-action="edit"]');
        var saveBtn = item.querySelector('[data-action="save"]');
        var cancelBtn = item.querySelector('[data-action="cancel"]');
        var panel = item.querySelector('.action-edit');

        item.setAttribute('data-editing', editing ? '1' : '0');
        if (panel) panel.style.display = editing ? '' : 'none';
        if (editBtn) editBtn.style.display = editing ? 'none' : '';
        if (saveBtn) saveBtn.style.display = editing ? '' : 'none';
        if (cancelBtn) cancelBtn.style.display = editing ? '' : 'none';
    }

    function rememberState(item) {
        var inputs = getInputs(item);
        item.setAttribute('data-state-name', inputs.name ? inputs.name.value : '');
        item.setAttribute('data-state-sort', inputs.sort ? inputs.sort.value : '0');
        item.setAttribute('data-state-active', inputs.active && inputs.active.checked ? 'Y' : 'N');
        item.setAttribute('data-state-active-from', inputs.activeFrom ? inputs.activeFrom.value : '');
        item.setAttribute('data-state-active-to', inputs.activeTo ? inputs.activeTo.value : '');
        item.setAttribute('data-state-seo-title', inputs.seoTitle ? inputs.seoTitle.value : '');
        item.setAttribute('data-state-seo-description', inputs.seoDescription ? inputs.seoDescription.value : '');
        item.setAttribute('data-state-detail', getDetailHtml(item));
    }

    function restoreState(item) {
        var inputs = getInputs(item);
        if (inputs.name) inputs.name.value = item.getAttribute('data-state-name') || '';
        if (inputs.sort) inputs.sort.value = item.getAttribute('data-state-sort') || '0';
        if (inputs.active) inputs.active.checked = item.getAttribute('data-state-active') === 'Y';
        if (inputs.activeFrom) inputs.activeFrom.value = item.getAttribute('data-state-active-from') || '';
        if (inputs.activeTo) inputs.activeTo.value = item.getAttribute('data-state-active-to') || '';
        if (inputs.seoTitle) inputs.seoTitle.value = item.getAttribute('data-state-seo-title') || '';
        if (inputs.seoDescription) inputs.seoDescription.value = item.getAttribute('data-state-seo-description') || '';
        setDetailHtml(item, item.getAttribute('data-state-detail') || '');
        if (inputs.picture) inputs.picture.value = '';
        setEditMode(item, false);
    }

    // Пока одна карточка открыта — блокируем кнопки "Изменить" у остальных.
    function lockOtherEditButtons(currentItem) {
        activeEditItem = currentItem;
        var items = document.querySelectorAll('.action-item');
        for (var i = 0; i < items.length; i++) {
            if (items[i] === currentItem) {
                continue;
            }
            var btn = items[i].querySelector('[data-action="edit"]');
            if (btn) {
                btn.disabled = true;
                btn.setAttribute('title', 'Сначала завершите редактирование другой акции');
            }
        }
    }

    function unlockEditButtons() {
        activeEditItem = null;
        var btns = document.querySelectorAll('.action-item [data-action="edit"]');
        for (var i = 0; i < btns.length; i++) {
            btns[i].disabled = false;
            btns[i].removeAttribute('title');
        }
    }

    // Переключение табов "Описание акции" / "SEO описание"
    // (работает и для карточки, и для формы создания).
    function switchTab(tabBtn) {
        var item = tabBtn.closest('.action-item, .action-create');
        if (!item) {
            return;
        }
        var name = tabBtn.getAttribute('data-action-tab');

        var tabs = item.querySelectorAll('[data-action-tab]');
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.toggle('is-active', tabs[i] === tabBtn);
        }

        var panes = item.querySelectorAll('[data-action-pane]');
        for (var j = 0; j < panes.length; j++) {
            panes[j].style.display = (panes[j].getAttribute('data-action-pane') === name) ? '' : 'none';
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

    // Формат 'YYYY-MM-DDTHH:MM' -> 'DD.MM.YYYY HH:MM' для строки активности.
    function formatLocalValue(value) {
        if (!value) {
            return '';
        }
        var m = value.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
        if (!m) {
            return value;
        }
        return m[3] + '.' + m[2] + '.' + m[1] + ' ' + m[4] + ':' + m[5];
    }

    function updateMeta(item, inputs) {
        var metaEl = item.querySelector('.action-item__meta');
        var from = inputs.activeFrom ? formatLocalValue(inputs.activeFrom.value) : '';
        var to = inputs.activeTo ? formatLocalValue(inputs.activeTo.value) : '';
        var text = '';
        if (from !== '' || to !== '') {
            text = 'Активность: ' + (from !== '' ? from : '—') + ' — ' + (to !== '' ? to : 'бессрочно');
        }
        if (metaEl) {
            metaEl.textContent = text;
            metaEl.style.display = text !== '' ? '' : 'none';
        }
    }

    // Заменить плейсхолдер фото на реальное изображение (форма создания).
    function ensurePhotoImg(node) {
        var img = node.querySelector('.action-photo');
        if (img && img.tagName !== 'IMG') {
            var newImg = document.createElement('img');
            newImg.className = 'action-photo';
            newImg.alt = '';
            img.parentNode.replaceChild(newImg, img);
            return newImg;
        }
        return img;
    }

    // ===== Сохранение акции =====
    function saveAction(item, btn) {
        var id = item.getAttribute('data-id');
        var inputs = getInputs(item);

        var formData = new FormData();
        formData.append('id', id);
        formData.append('name', inputs.name ? inputs.name.value : '');
        formData.append('sort', inputs.sort ? inputs.sort.value : '0');
        formData.append('active', inputs.active && inputs.active.checked ? 'Y' : 'N');
        formData.append('activeFrom', inputs.activeFrom ? inputs.activeFrom.value : '');
        formData.append('activeTo', inputs.activeTo ? inputs.activeTo.value : '');
        formData.append('detail', getDetailHtml(item));
        formData.append('seoTitle', inputs.seoTitle ? inputs.seoTitle.value : '');
        formData.append('seoDescription', inputs.seoDescription ? inputs.seoDescription.value : '');
        if (inputs.picture && inputs.picture.files && inputs.picture.files[0]) {
            formData.append('picture', inputs.picture.files[0]);
        }
        formData.append('sessid', BX.bitrix_sessid());

        btn.disabled = true;
        btn.textContent = 'Сохранение...';

        BX.ajax.runComponentAction('ldo:actions.list', 'saveAction', {
            mode: 'class',
            data: formData
        }).then(function (response) {
            btn.disabled = false;
            btn.textContent = 'Сохранить';

            if (response && response.data && response.data.success) {
                // Сервер возвращает очищенный HTML — синхронизируем редактор,
                // чтобы повторное открытие показало актуальное описание.
                setDetailHtml(item, response.data.detail || '');

                var nameEl = item.querySelector('.action-item__name');
                if (nameEl && response.data.name) {
                    nameEl.textContent = response.data.name;
                    nameEl.setAttribute('title', response.data.name);
                }
                updateMeta(item, inputs);

                // Признак активности: неактивная акция — приглушённый вид + бейдж.
                var isActiveNow = !!(inputs.active && inputs.active.checked);
                item.classList.toggle('action-item--inactive', !isActiveNow);
                var statusEl = item.querySelector('.action-item__status');
                if (!isActiveNow && !statusEl) {
                    statusEl = document.createElement('div');
                    statusEl.className = 'action-item__status';
                    statusEl.textContent = 'Неактивна';
                    var infoEl = item.querySelector('.action-item__info');
                    var nameEl2 = item.querySelector('.action-item__name');
                    if (infoEl && nameEl2 && nameEl2.nextSibling) {
                        infoEl.insertBefore(statusEl, nameEl2.nextSibling);
                    } else if (infoEl) {
                        infoEl.appendChild(statusEl);
                    }
                } else if (isActiveNow && statusEl) {
                    statusEl.parentNode.removeChild(statusEl);
                }

                // Если выбрали новое фото — обновляем превью в списке.
                var preview = item.querySelector('.action-photo');
                var listImg = item.querySelector('.action-item__img');
                if (preview && preview.tagName === 'IMG' && listImg && listImg.tagName === 'IMG') {
                    listImg.src = preview.src;
                }

                rememberState(item);
                setEditMode(item, false);
                unlockEditButtons();
            } else {
                var msg = (response && response.data && response.data.error)
                    ? response.data.error
                    : 'Ошибка сохранения акции';
                alert(msg);
            }
        }).catch(function () {
            btn.disabled = false;
            btn.textContent = 'Сохранить';
            alert('Ошибка соединения с сервером');
        });
    }

    // ===== Создание акции =====
    function openCreateForm() {
        var node = document.getElementById('action-create');
        if (!node) {
            return;
        }
        node.style.display = '';
        var addBtn = document.getElementById('action-add-btn');
        if (addBtn) {
            addBtn.style.display = 'none';
        }
        openEditor(node);
    }

    function resetCreateForm(node) {
        var inputs = getInputs(node);
        if (inputs.name) inputs.name.value = '';
        if (inputs.sort) inputs.sort.value = '500';
        if (inputs.active) inputs.active.checked = true;
        if (inputs.activeFrom) inputs.activeFrom.value = '';
        if (inputs.activeTo) inputs.activeTo.value = '';
        if (inputs.seoTitle) inputs.seoTitle.value = '';
        if (inputs.seoDescription) inputs.seoDescription.value = '';
        if (inputs.picture) inputs.picture.value = '';
        setDetailHtml(node, '');

        // Вернуть плейсхолдер фото.
        var img = node.querySelector('.action-photo');
        if (img && img.tagName === 'IMG') {
            var span = document.createElement('span');
            span.className = 'action-photo action-photo--empty';
            img.parentNode.replaceChild(span, img);
        }

        // Вернуть активный таб "Описание акции".
        var tabs = node.querySelectorAll('[data-action-tab]');
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.toggle('is-active', tabs[i].getAttribute('data-action-tab') === 'main');
        }
        var panes = node.querySelectorAll('[data-action-pane]');
        for (var j = 0; j < panes.length; j++) {
            panes[j].style.display = panes[j].getAttribute('data-action-pane') === 'main' ? '' : 'none';
        }
    }

    function closeCreateForm() {
        var node = document.getElementById('action-create');
        if (!node) {
            return;
        }
        resetCreateForm(node);
        node.style.display = 'none';
        var addBtn = document.getElementById('action-add-btn');
        if (addBtn) {
            addBtn.style.display = '';
        }
    }

    function submitCreateForm(btn) {
        var node = document.getElementById('action-create');
        if (!node) {
            return;
        }
        var inputs = getInputs(node);
        if (inputs.name && inputs.name.value.trim() === '') {
            alert('Введите название акции.');
            if (inputs.name) inputs.name.focus();
            return;
        }

        var formData = new FormData();
        formData.append('name', inputs.name ? inputs.name.value : '');
        formData.append('sort', inputs.sort ? inputs.sort.value : '0');
        formData.append('active', inputs.active && inputs.active.checked ? 'Y' : 'N');
        formData.append('activeFrom', inputs.activeFrom ? inputs.activeFrom.value : '');
        formData.append('activeTo', inputs.activeTo ? inputs.activeTo.value : '');
        formData.append('detail', getDetailHtml(node));
        formData.append('seoTitle', inputs.seoTitle ? inputs.seoTitle.value : '');
        formData.append('seoDescription', inputs.seoDescription ? inputs.seoDescription.value : '');
        if (inputs.picture && inputs.picture.files && inputs.picture.files[0]) {
            formData.append('picture', inputs.picture.files[0]);
        }
        formData.append('sessid', BX.bitrix_sessid());

        btn.disabled = true;
        btn.textContent = 'Создание...';

        BX.ajax.runComponentAction('ldo:actions.list', 'createAction', {
            mode: 'class',
            data: formData
        }).then(function (response) {
            btn.disabled = false;
            btn.textContent = 'Создать акцию';

            if (response && response.data && response.data.success) {
                var list = document.getElementById('actions-list');
                if (list && response.data.html) {
                    var empty = list.querySelector('.actions-empty');
                    if (empty && empty.parentNode) {
                        empty.parentNode.removeChild(empty);
                    }
                    list.insertAdjacentHTML('afterbegin', response.data.html);
                }
                closeCreateForm();
            } else {
                alert((response && response.data && response.data.error)
                    ? response.data.error
                    : 'Ошибка создания акции');
            }
        }).catch(function () {
            btn.disabled = false;
            btn.textContent = 'Создать акцию';
            alert('Ошибка соединения с сервером');
        });
    }

    // ===== Делегирование кликов =====
    document.addEventListener('click', function (e) {
        // Добавление акции
        if (e.target.closest('#action-add-btn')) {
            openCreateForm();
            return;
        }
        if (e.target.closest('#action-create-cancel')) {
            closeCreateForm();
            return;
        }
        var createSubmit = e.target.closest('#action-create-submit');
        if (createSubmit) {
            submitCreateForm(createSubmit);
            return;
        }

        // Табы карточки/формы (Описание акции / SEO описание)
        var tabBtn = e.target.closest('[data-action-tab]');
        if (tabBtn) {
            switchTab(tabBtn);
            return;
        }

        var btn = e.target.closest('[data-action]');
        if (!btn) return;

        var item = btn.closest('.action-item');
        if (!item) return;

        var action = btn.getAttribute('data-action');

        if (action === 'edit') {
            rememberState(item);
            setEditMode(item, true);
            lockOtherEditButtons(item);
            // Поднимаем редактор описания только для этой карточки.
            openEditor(item);
        } else if (action === 'cancel') {
            restoreState(item);
            unlockEditButtons();
        } else if (action === 'save') {
            saveAction(item, btn);
        }
    });

    // ===== Превью выбранного фото =====
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('action-picture')) {
            var item = e.target.closest('.action-item, .action-create');
            if (item && e.target.files && e.target.files[0]) {
                var img = ensurePhotoImg(item);
                if (img) {
                    img.src = URL.createObjectURL(e.target.files[0]);
                }
            }
        }
    });
})();
