;(function () {
    'use strict';

    // ===== Визуальный редактор описания раздела (Bitrix CHTMLEditor) =====
    // Разметка редактора (~25КБ на инстанс) грузится по AJAX в момент
    // открытия панели редактирования и вставляется в .section-desc-wrap.
    var activeEditItem = null;

    function getEditorId(item) {
        return 'sd' + item.getAttribute('data-id');
    }

    function getEditor(item) {
        if (!window.BXHtmlEditor || !BXHtmlEditor.Get) {
            return null;
        }
        return BXHtmlEditor.Get(getEditorId(item)) || null;
    }

    function getDescWrap(item) {
        return item.querySelector('.section-desc-wrap');
    }

    function getDescBlock(item) {
        return item.querySelector('.section-desc-block');
    }

    // Текущее HTML описания: редактор -> data-атрибут контейнера.
    function getDescriptionHtml(item) {
        var ed = getEditor(item);
        if (ed) {
            try { return ed.GetContent(); } catch (e) {}
        }
        var wrap = getDescWrap(item);
        return wrap ? (wrap.getAttribute('data-description') || '') : '';
    }

    function setDescriptionHtml(item, html) {
        html = html || '';
        var wrap = getDescWrap(item);
        if (wrap) {
            wrap.setAttribute('data-description', html);
        }
        var ed = getEditor(item);
        if (ed) {
            try { ed.SetContent(html); } catch (e) {}
        }
    }

    // Вставка HTML с выполнением инлайн-скриптов (скелет редактора содержит
    // <script> с BXHtmlEditor.SaveConfig, innerHTML его не выполняет).
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

    // Поднять редактор описания для раздела.
    function openEditor(item) {
        var wrap = getDescWrap(item);
        if (!wrap) {
            return;
        }
        var id = getEditorId(item);

        var hasInstance = false;
        if (window.BXHtmlEditor && BXHtmlEditor.Get) {
            hasInstance = !!BXHtmlEditor.Get(id);
        }

        if (window.BXHtmlEditor && BXHtmlEditor.Show && (hasInstance || wrap.querySelector('.bx-html-editor'))) {
            BXHtmlEditor.Show(null, id);
            setDescriptionHtml(item, wrap.getAttribute('data-description') || '');
            return;
        }

        if (wrap.getAttribute('data-editor-loading') === '1') {
            return;
        }
        wrap.setAttribute('data-editor-loading', '1');

        var formData = new FormData();
        formData.append('id', item.getAttribute('data-id'));
        formData.append('sessid', BX.bitrix_sessid());

        BX.ajax.runComponentAction('ldo:sections.list', 'getEditor', {
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
            }
        }).catch(function () {
            wrap.removeAttribute('data-editor-loading');
            alert('Ошибка соединения с сервером');
        });
    }

    // Переключение табов: Описание раздела / SEO описание.
    function switchSectionTab(tabBtn) {
        var item = tabBtn.closest('.section-item');
        if (!item) {
            return;
        }
        var name = tabBtn.getAttribute('data-section-tab');

        var tabs = item.querySelectorAll('[data-section-tab]');
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.toggle('is-active', tabs[i] === tabBtn);
        }

        var panes = item.querySelectorAll('[data-section-pane]');
        for (var j = 0; j < panes.length; j++) {
            panes[j].style.display = (panes[j].getAttribute('data-section-pane') === name) ? '' : 'none';
        }

        if (name === 'main') {
            var editor = getEditor(item);
            if (editor) {
                try { editor.ResizeSceleton(); } catch (e) {}
            }
        }
    }

    // Пока один раздел открыт на редактирование — блокируем кнопки у остальных.
    function lockOtherEditButtons(currentItem) {
        activeEditItem = currentItem;
        var items = document.querySelectorAll('.section-item');
        for (var i = 0; i < items.length; i++) {
            if (items[i] === currentItem) {
                continue;
            }
            var btn = items[i].querySelector('[data-action="edit"]');
            if (btn) {
                btn.disabled = true;
                btn.setAttribute('title', 'Сначала завершите редактирование другого раздела');
            }
        }
    }

    function unlockEditButtons() {
        activeEditItem = null;
        var btns = document.querySelectorAll('.section-item [data-action="edit"]');
        for (var i = 0; i < btns.length; i++) {
            btns[i].disabled = false;
            btns[i].removeAttribute('title');
        }
    }

    // Показ превью выбранного файла (иконки)
    function previewIcon(item, file) {
        var img = item.querySelector('.section-icon');
        if (!img || !file) return;
        var url = URL.createObjectURL(file);
        img.src = url;
        img.classList.remove('section-icon--empty');
    }

    // Переключение режима редактирования раздела
    function setEditMode(item, editing) {
        var toggles = item.querySelectorAll('.section-active, .section-on-main');
        var select = item.querySelector('.section-parent-select');
        var sortInput = item.querySelector('.section-sort-input');
        var fileInput = item.querySelector('.section-picture-input');
        var seoTitle = item.querySelector('.section-seo-title');
        var seoDescription = item.querySelector('.section-seo-description');
        var editBtn = item.querySelector('[data-action="edit"]');
        var saveBtn = item.querySelector('[data-action="save"]');
        var cancelBtn = item.querySelector('[data-action="cancel"]');
        var descBlock = getDescBlock(item);

        for (var i = 0; i < toggles.length; i++) {
            toggles[i].disabled = !editing;
        }
        if (select) select.disabled = !editing;
        if (sortInput) sortInput.disabled = !editing;
        if (fileInput) fileInput.disabled = !editing;
        if (seoTitle) seoTitle.disabled = !editing;
        if (seoDescription) seoDescription.disabled = !editing;

        if (editBtn) editBtn.style.display = editing ? 'none' : '';
        if (saveBtn) saveBtn.style.display = editing ? '' : 'none';
        if (cancelBtn) cancelBtn.style.display = editing ? '' : 'none';

        if (descBlock) descBlock.style.display = editing ? '' : 'none';
    }

    // Сохраняем текущие значения раздела, чтобы откатить при "Отмена"
    function rememberState(item) {
        var active = item.querySelector('.section-active');
        var main = item.querySelector('.section-on-main');
        var select = item.querySelector('.section-parent-select');
        var sortInput = item.querySelector('.section-sort-input');

        item.setAttribute('data-active', active && active.checked ? 'Y' : 'N');
        item.setAttribute('data-main', main && main.checked ? '1' : '0');
        item.setAttribute('data-parent', select ? select.value : '0');
        item.setAttribute('data-sort', sortInput ? sortInput.value : '0');

        var seoTitle = item.querySelector('.section-seo-title');
        var seoDescription = item.querySelector('.section-seo-description');
        item.setAttribute('data-seo-title', seoTitle ? seoTitle.value : '');
        item.setAttribute('data-seo-description', seoDescription ? seoDescription.value : '');
        // Каноническое описание — в data-атрибут контейнера.
        item.setAttribute('data-desc-state', getDescriptionHtml(item));
        var wrap = getDescWrap(item);
        if (wrap) {
            wrap.setAttribute('data-description', item.getAttribute('data-desc-state') || '');
        }
    }

    // Восстанавливаем сохранённые значения раздела
    function restoreState(item) {
        var active = item.querySelector('.section-active');
        var main = item.querySelector('.section-on-main');
        var select = item.querySelector('.section-parent-select');
        var sortInput = item.querySelector('.section-sort-input');
        var fileInput = item.querySelector('.section-picture-input');

        if (active) active.checked = item.getAttribute('data-active') === 'Y';
        if (main) main.checked = item.getAttribute('data-main') === '1';
        if (select) select.value = item.getAttribute('data-parent') || '0';
        if (sortInput) sortInput.value = item.getAttribute('data-sort') || '0';
        if (fileInput) fileInput.value = '';

        var seoTitle = item.querySelector('.section-seo-title');
        var seoDescription = item.querySelector('.section-seo-description');
        if (seoTitle) seoTitle.value = item.getAttribute('data-seo-title') || '';
        if (seoDescription) seoDescription.value = item.getAttribute('data-seo-description') || '';

        setDescriptionHtml(item, item.getAttribute('data-desc-state') || '');
        setEditMode(item, false);
    }

    // Сохранение раздела через контроллер компонента (runComponentAction).
    // Данные и файл иконки отправляются одним FormData-запросом;
    // сжатие иконки и очистка HTML описания выполняются на сервере.
    function saveSection(item, btn) {
        var id = item.getAttribute('data-id');
        var active = item.querySelector('.section-active');
        var main = item.querySelector('.section-on-main');
        var select = item.querySelector('.section-parent-select');
        var sortInput = item.querySelector('.section-sort-input');
        var fileInput = item.querySelector('.section-picture-input');

        var formData = new FormData();
        formData.append('id', id);
        formData.append('active', active && active.checked ? 'Y' : 'N');
        formData.append('viewIndex', main && main.checked ? 1 : 0);
        formData.append('parentId', select ? select.value : 0);
        formData.append('sort', sortInput ? sortInput.value : 0);
        formData.append('description', getDescriptionHtml(item));
        var seoTitle = item.querySelector('.section-seo-title');
        var seoDescription = item.querySelector('.section-seo-description');
        formData.append('seoTitle', seoTitle ? seoTitle.value : '');
        formData.append('seoDescription', seoDescription ? seoDescription.value : '');
        if (fileInput && fileInput.files && fileInput.files[0]) {
            formData.append('picture', fileInput.files[0]);
        }
        formData.append('sessid', BX.bitrix_sessid());

        btn.disabled = true;
        btn.textContent = 'Сохранение...';

        BX.ajax.runComponentAction('ldo:sections.list', 'saveSection', {
            mode: 'class',
            data: formData
        }).then(function (response) {
            btn.disabled = false;
            btn.textContent = 'Изменить';

            if (response && response.data && response.data.success) {
                // Сервер возвращает очищенный HTML — синхронизируем редактор.
                if (response.data.description !== undefined) {
                    setDescriptionHtml(item, response.data.description || '');
                }
                rememberState(item);
                setEditMode(item, false);
                unlockEditButtons();
            } else {
                var msg = (response && response.data && response.data.error)
                    ? response.data.error
                    : 'Ошибка сохранения раздела';
                alert(msg);
            }
        }).catch(function () {
            btn.disabled = false;
            btn.textContent = 'Изменить';
            alert('Ошибка соединения с сервером');
        });
    }

    // Обработка кликов: кнопки раздела и табы
    document.addEventListener('click', function (e) {
        // Табы (Описание раздела / SEO описание)
        var tabBtn = e.target.closest('[data-section-tab]');
        if (tabBtn) {
            switchSectionTab(tabBtn);
            return;
        }

        var btn = e.target.closest('[data-action]');
        if (!btn) return;

        var item = btn.closest('.section-item');
        if (!item) return;

        var action = btn.getAttribute('data-action');

        if (action === 'edit') {
            rememberState(item);
            setEditMode(item, true);
            lockOtherEditButtons(item);
            // Поднимаем редактор описания только для этого раздела.
            openEditor(item);
        } else if (action === 'cancel') {
            restoreState(item);
            unlockEditButtons();
        } else if (action === 'save') {
            saveSection(item, btn);
        }
    });

    // Выбор файла иконки: показываем превью
    document.addEventListener('change', function (e) {
        var input = e.target.closest('.section-picture-input');
        if (!input) return;

        var item = input.closest('.section-item');
        if (item && input.files && input.files[0]) {
            previewIcon(item, input.files[0]);
        }
    });

    // Поиск разделов по названию (делегирование — работает независимо от готовности DOM)
    document.addEventListener('input', function (e) {
        var input = e.target.closest('.sections-search-input');
        if (!input) return;

        var sectionList = document.getElementById('sections-list');
        if (!sectionList) return;

        var query = input.value.trim().toLowerCase();
        var items = sectionList.querySelectorAll('.section-item');

        for (var i = 0; i < items.length; i++) {
            var nameEl = items[i].querySelector('.rest-item__name');
            var name = nameEl ? nameEl.textContent.toLowerCase() : '';
            items[i].style.display = (!query || name.indexOf(query) !== -1) ? '' : 'none';
        }
    });
})();
