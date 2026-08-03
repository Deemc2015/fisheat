;(function () {
    'use strict';

    function getEditor(item) {
        return item.querySelector('.section-desc-editor');
    }
    function getHtml(item) {
        return item.querySelector('.section-desc-html');
    }
    function getStorage(item) {
        return item.querySelector('.section-desc-storage');
    }
    function getDescBlock(item) {
        return item.querySelector('.section-desc-block');
    }

    // Синхронизация: редактор -> HTML-поле
    function syncEditorToHtml(block) {
        var editor = block.querySelector('.section-desc-editor');
        var html = block.querySelector('.section-desc-html');
        if (editor && html) {
            html.value = editor.innerHTML;
        }
    }

    // Синхронизация: HTML-поле -> редактор
    function syncHtmlToEditor(block) {
        var editor = block.querySelector('.section-desc-editor');
        var html = block.querySelector('.section-desc-html');
        if (editor && html) {
            editor.innerHTML = html.value;
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
        var editBtn = item.querySelector('[data-action="edit"]');
        var saveBtn = item.querySelector('[data-action="save"]');
        var cancelBtn = item.querySelector('[data-action="cancel"]');
        var descBlock = getDescBlock(item);
        var editor = getEditor(item);
        var html = getHtml(item);
        var storage = getStorage(item);

        for (var i = 0; i < toggles.length; i++) {
            toggles[i].disabled = !editing;
        }
        if (select) select.disabled = !editing;
        if (sortInput) sortInput.disabled = !editing;
        if (fileInput) fileInput.disabled = !editing;

        if (editBtn) editBtn.style.display = editing ? 'none' : '';
        if (saveBtn) saveBtn.style.display = editing ? '' : 'none';
        if (cancelBtn) cancelBtn.style.display = editing ? '' : 'none';

        if (descBlock) descBlock.style.display = editing ? '' : 'none';
        // При входе в режим редактирования показываем сохранённое описание
        if (editing && editor && storage) {
            editor.innerHTML = storage.value || '';
            if (html) html.value = storage.value || '';
        }
    }

    // Сохраняем текущие значения раздела, чтобы откатить при "Отмена"
    function rememberState(item) {
        var active = item.querySelector('.section-active');
        var main = item.querySelector('.section-on-main');
        var select = item.querySelector('.section-parent-select');
        var sortInput = item.querySelector('.section-sort-input');
        var editor = getEditor(item);
        var storage = getStorage(item);

        item.setAttribute('data-active', active && active.checked ? 'Y' : 'N');
        item.setAttribute('data-main', main && main.checked ? '1' : '0');
        item.setAttribute('data-parent', select ? select.value : '0');
        item.setAttribute('data-sort', sortInput ? sortInput.value : '0');
        // Фиксируем описание в скрытом textarea (исходное значение)
        if (editor && storage) {
            storage.value = editor.innerHTML;
        }
    }

    // Восстанавливаем сохранённые значения раздела
    function restoreState(item) {
        var active = item.querySelector('.section-active');
        var main = item.querySelector('.section-on-main');
        var select = item.querySelector('.section-parent-select');
        var sortInput = item.querySelector('.section-sort-input');
        var fileInput = item.querySelector('.section-picture-input');
        var editor = getEditor(item);
        var html = getHtml(item);
        var storage = getStorage(item);

        if (active) active.checked = item.getAttribute('data-active') === 'Y';
        if (main) main.checked = item.getAttribute('data-main') === '1';
        if (select) select.value = item.getAttribute('data-parent') || '0';
        if (sortInput) sortInput.value = item.getAttribute('data-sort') || '0';
        if (fileInput) fileInput.value = '';
        if (editor && storage) {
            editor.innerHTML = storage.value || '';
            if (html) html.value = storage.value || '';
        }
        setEditMode(item, false);
    }

    // Сохранение раздела через контроллер компонента (runComponentAction).
    // Данные и файл иконки отправляются одним FormData-запросом;
    // сжатие иконки выполняется в saveSectionAction на сервере.
    function saveSection(item, btn) {
        var id = item.getAttribute('data-id');
        var active = item.querySelector('.section-active');
        var main = item.querySelector('.section-on-main');
        var select = item.querySelector('.section-parent-select');
        var sortInput = item.querySelector('.section-sort-input');
        var fileInput = item.querySelector('.section-picture-input');
        var editor = getEditor(item);
        var storage = getStorage(item);

        // Фиксируем текущее описание в storage
        if (editor && storage) {
            storage.value = editor.innerHTML;
        }

        var formData = new FormData();
        formData.append('id', id);
        formData.append('active', active && active.checked ? 'Y' : 'N');
        formData.append('viewIndex', main && main.checked ? 1 : 0);
        formData.append('parentId', select ? select.value : 0);
        formData.append('sort', sortInput ? sortInput.value : 0);
        formData.append('description', storage ? storage.value : '');
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
                rememberState(item);
                setEditMode(item, false);
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

    // Обработка кликов по кнопкам раздела
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-action]');
        if (!btn) return;

        var item = btn.closest('.section-item');
        if (!item) return;

        var action = btn.getAttribute('data-action');

        if (action === 'edit') {
            rememberState(item);
            setEditMode(item, true);
        } else if (action === 'cancel') {
            restoreState(item);
        } else if (action === 'save') {
            saveSection(item, btn);
        }
    });

    // Обработка кнопок тулбара редактора описания
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-cmd]');
        if (!btn) return;

        var block = btn.closest('.section-desc-block');
        if (!block) return;

        var editor = block.querySelector('.section-desc-editor');
        if (!editor) return;

        var cmd = btn.getAttribute('data-cmd');

        editor.focus();
        document.execCommand(cmd, false, null);
        syncEditorToHtml(block);

        e.preventDefault();
    });

    // Выпадающий список форматов блоков (H1-H6, P)
    document.addEventListener('change', function (e) {
        var select = e.target.closest('.section-desc-block-select');
        if (!select) return;

        var block = select.closest('.section-desc-block');
        if (!block) return;

        var editor = block.querySelector('.section-desc-editor');
        if (!editor) return;

        var value = select.value;

        editor.focus();
        document.execCommand('formatBlock', false, value);
        syncEditorToHtml(block);

        // Сбрасываем выбор, чтобы можно было применять повторно
        select.value = 'P';
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

    // Синхронизация редактора и HTML-поля
    document.addEventListener('input', function (e) {
        var target = e.target;

        if (target.classList && target.classList.contains('section-desc-editor')) {
            var block = target.closest('.section-desc-block');
            if (block) syncEditorToHtml(block);
        } else if (target.classList && target.classList.contains('section-desc-html')) {
            var block = target.closest('.section-desc-block');
            if (block) syncHtmlToEditor(block);
        }
    });

    // Поиск разделов по названию
    var searchInput = document.querySelector('.sections-search-input');
    var sectionList = document.getElementById('sections-list');

    if (searchInput && sectionList) {
        searchInput.addEventListener('input', function () {
            var query = this.value.trim().toLowerCase();
            var items = sectionList.querySelectorAll('.section-item');

            for (var i = 0; i < items.length; i++) {
                var nameEl = items[i].querySelector('.rest-item__name');
                var name = nameEl ? nameEl.textContent.toLowerCase() : '';
                items[i].style.display = (!query || name.indexOf(query) !== -1) ? '' : 'none';
            }
        });
    }
})();
