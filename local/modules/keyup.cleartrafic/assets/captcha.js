/**
 * Окно капчи модуля фильтрации трафика.
 *
 * Показывает модальное окно с виджетом Яндекс SmartCaptcha, отправляет токен
 * во внутренний AJAX-контроллер модуля и перезагружает страницу после успеха.
 * Зависимостей от jQuery и JS-ядра Bitrix нет.
 */
(function () {
    'use strict';

    var config = window.KEYUP_CAPTCHA_CONFIG || null;

    if (!config) {
        return;
    }

    var modal = document.getElementById('keyup-captcha-modal');
    var overlay = document.getElementById('keyup-captcha-overlay');

    if (!modal || !overlay) {
        return;
    }

    var container = document.getElementById('keyup-captcha-container');
    var button = document.getElementById('keyup-captcha-submit');
    var errorBox = document.getElementById('keyup-captcha-error');
    var widgetRendered = false;
    var modalShown = false;
    var messages = config.messages || {};

    function showModal() {
        if (modalShown) {
            return;
        }

        modalShown = true;
        overlay.className += ' keyup-captcha-overlay--visible';
        modal.className += ' keyup-captcha-modal--visible';

        if (document.body) {
            document.body.className += ' keyup-captcha-locked';
        }

        renderWidget();
    }

    function renderWidget() {
        if (widgetRendered || !container || !window.smartCaptcha) {
            return;
        }

        if (container.children.length > 0) {
            widgetRendered = true;
            return;
        }

        window.smartCaptcha.render(container, { sitekey: config.siteKey });
        widgetRendered = true;
    }

    function waitForWidget() {
        if (window.smartCaptcha) {
            renderWidget();
            return;
        }

        var attempts = 0;
        var timer = window.setInterval(function () {
            attempts++;

            if (window.smartCaptcha) {
                window.clearInterval(timer);
                renderWidget();
            } else if (attempts > 150) {
                window.clearInterval(timer);
                showError(messages.loadFail);
            }
        }, 100);
    }

    function getToken() {
        if (!container) {
            return '';
        }

        var input = container.querySelector('input[name="smart-token"]');

        return input ? String(input.value || '').trim() : '';
    }

    function showError(text) {
        if (!errorBox || !text) {
            return;
        }

        errorBox.textContent = text;
        errorBox.style.display = 'block';
    }

    function hideError() {
        if (errorBox) {
            errorBox.style.display = 'none';
        }
    }

    function submit() {
        var token = getToken();

        if (token === '') {
            showError(messages.empty);
            return;
        }

        hideError();

        if (button) {
            button.disabled = true;
        }

        var body = 'token=' + encodeURIComponent(token) + '&sessid=' + encodeURIComponent(config.sessid);

        window.fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            var status = json && json.data && json.data.status ? json.data.status : '';

            if (status === 'ok') {
                window.location.reload();
                return;
            }

            if (button) {
                button.disabled = false;
            }

            showError(messages.fail);
        })['catch'](function () {
            if (button) {
                button.disabled = false;
            }

            showError(messages.fail);
        });
    }

    if (button) {
        button.addEventListener('click', submit);
    }

    window.addEventListener('load', waitForWidget);

    window.setTimeout(showModal, parseInt(config.delay, 10) || 0);
})();
