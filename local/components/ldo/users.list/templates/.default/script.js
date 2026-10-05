;(function () {
    'use strict';

    // Сообщение об ошибке (красный блок над таблицей)
    function showError(msg) {
        var box = document.getElementById('users-message');
        if (!box) {
            return;
        }
        box.textContent = msg;
        box.style.display = 'block';
        clearTimeout(showError._t);
        showError._t = setTimeout(function () {
            box.style.display = 'none';
        }, 4000);
    }

    // Переключение флагов ACTIVE / BLOCKED через контроллер компонента
    function bindToggles() {
        var toggles = document.querySelectorAll('.js-users-toggle');
        for (var i = 0; i < toggles.length; i++) {
            (function (input) {
                if (input.getAttribute('data-bound') === '1') {
                    return;
                }
                input.setAttribute('data-bound', '1');

                input.addEventListener('change', function () {
                    var fd = new FormData();
                    fd.append('id', input.getAttribute('data-id'));
                    fd.append('field', input.getAttribute('data-field'));
                    fd.append('value', input.checked ? 'Y' : 'N');
                    fd.append('sessid', BX.bitrix_sessid());

                    BX.ajax.runComponentAction('ldo:users.list', 'toggleFlag', {
                        mode: 'class',
                        data: fd
                    }).then(function (response) {
                        if (!response || !response.data || !response.data.success) {
                            input.checked = !input.checked;
                            var msg = (response && response.data && response.data.error)
                                ? response.data.error
                                : 'Не удалось сохранить изменение.';
                            showError(msg);
                        }
                    }).catch(function (error) {
                        input.checked = !input.checked;
                        var msg = (error && error.errors && error.errors.length)
                            ? error.errors[0].message
                            : 'Ошибка сети. Изменение не сохранено.';
                        showError(msg);
                    });
                });
            })(toggles[i]);
        }
    }

    // Удаление пользователя через контроллер компонента
    function bindDeletes() {
        var buttons = document.querySelectorAll('.p-users-delete');
        for (var j = 0; j < buttons.length; j++) {
            (function (btn) {
                if (btn.getAttribute('data-bound') === '1') {
                    return;
                }
                btn.setAttribute('data-bound', '1');

                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-id');
                    var name = btn.getAttribute('data-name') || id;

                    if (!confirm('Удалить пользователя «' + name + '»? Действие необратимо.')) {
                        return;
                    }

                    var fd = new FormData();
                    fd.append('id', id);
                    fd.append('sessid', BX.bitrix_sessid());

                    BX.ajax.runComponentAction('ldo:users.list', 'deleteUser', {
                        mode: 'class',
                        data: fd
                    }).then(function (response) {
                        if (response && response.data && response.data.success) {
                            location.reload();
                        } else {
                            var msg = (response && response.data && response.data.error)
                                ? response.data.error
                                : 'Не удалось удалить пользователя.';
                            showError(msg);
                        }
                    }).catch(function (error) {
                        var msg = (error && error.errors && error.errors.length)
                            ? error.errors[0].message
                            : 'Ошибка сети. Пользователь не удалён.';
                        showError(msg);
                    });
                });
            })(buttons[j]);
        }
    }

    BX.ready(function () {
        bindToggles();
        bindDeletes();
    });
})();
