/* ============================================================
   Лендинг модуля фильтрации трафика keyup.cleartrafic
   Мобильное меню, выбор тарифа, отправка заявки, анимации
   ============================================================ */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initMenu();
        initPlanButtons();
        initAccordion();
        initForm();
        initReveal();
    });

    /* ---------- Мобильное меню ---------- */
    function initMenu() {
        var burger = document.getElementById('ct-burger');
        var nav = document.querySelector('.ct-nav');

        if (!burger || !nav) {
            return;
        }

        burger.addEventListener('click', function () {
            nav.classList.toggle('is-open');
        });

        nav.addEventListener('click', function (event) {
            if (event.target.tagName === 'A') {
                nav.classList.remove('is-open');
            }
        });
    }

    /* ---------- Кнопки тарифов: подстановка в поле формы ---------- */
    function initPlanButtons() {
        var select = document.getElementById('ct-form-plan');

        if (!select) {
            return;
        }

        document.querySelectorAll('[data-plan]').forEach(function (button) {
            button.addEventListener('click', function () {
                var plan = button.getAttribute('data-plan');
                var matched = false;

                Array.prototype.forEach.call(select.options, function (option) {
                    if (option.value === plan) {
                        select.value = plan;
                        matched = true;
                    }
                });

                if (!matched && plan) {
                    select.value = '';
                }

                var status = document.getElementById('ct-form-status');

                if (status) {
                    status.textContent = 'Тариф «' + plan + '» выбран — заполните форму ниже.';
                    status.className = 'ct-form__status is-success';
                }
            });
        });
    }

    /* ---------- Аккордеон: открываем только один вопрос ---------- */
    function initAccordion() {
        var container = document.getElementById('ct-accordion');

        if (!container) {
            return;
        }

        var items = container.querySelectorAll('details.ct-accordion__item');

        items.forEach(function (item) {
            item.addEventListener('toggle', function () {
                if (!item.open) {
                    return;
                }

                items.forEach(function (other) {
                    if (other !== item) {
                        other.open = false;
                    }
                });
            });
        });
    }

    /* ---------- Отправка формы без перезагрузки ---------- */
    function initForm() {
        var form = document.getElementById('ct-form');

        if (!form) {
            return;
        }

        var status = document.getElementById('ct-form-status');
        var submit = document.getElementById('ct-form-submit');
        var messages = window.CT_FORM_MESSAGES || {};

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            clearErrors(form);

            if (!validate(form, status, messages)) {
                return;
            }

            var data = new FormData(form);

            if (submit) {
                submit.disabled = true;
                submit.dataset.label = submit.textContent;
                submit.textContent = 'Отправляем...';
            }

            setStatus(status, '', '');

            fetch(form.action, {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return { status: 'error' };
                    });
                })
                .then(function (payload) {
                    if (payload && payload.status === 'success') {
                        setStatus(status, payload.message || 'Заявка отправлена.', 'success');
                        form.reset();
                        return;
                    }

                    if (payload && payload.errors) {
                        markErrors(form, payload.errors);
                    }

                    setStatus(
                        status,
                        (payload && payload.message) || messages.error || 'Не удалось отправить заявку.',
                        'error'
                    );
                })
                .catch(function () {
                    setStatus(status, messages.error || 'Не удалось отправить заявку.', 'error');
                })
                .finally(function () {
                    if (submit) {
                        submit.disabled = false;
                        submit.textContent = submit.dataset.label || 'Отправить заявку';
                    }
                });
        });
    }

    function validate(form, status, messages) {
        var ok = true;
        var name = form.querySelector('[name="name"]');
        var email = form.querySelector('[name="email"]');
        var consent = form.querySelector('[name="consent"]');

        if (name && name.value.trim().length < 2) {
            name.classList.add('is-error');
            ok = false;
        }

        if (email) {
            var value = email.value.trim();

            if (value === '' || !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) {
                email.classList.add('is-error');
                ok = false;
            }
        }

        if (consent && !consent.checked) {
            ok = false;
        }

        if (!ok) {
            setStatus(status, messages.validation || 'Проверьте выделенные поля и согласие на обработку данных.', 'error');
        }

        return ok;
    }

    function clearErrors(form) {
        form.querySelectorAll('.is-error').forEach(function (field) {
            field.classList.remove('is-error');
        });
    }

    function markErrors(form, errors) {
        Object.keys(errors).forEach(function (field) {
            var input = form.querySelector('[name="' + field + '"]');

            if (input) {
                input.classList.add('is-error');
            }
        });
    }

    function setStatus(node, text, type) {
        if (!node) {
            return;
        }

        node.textContent = text;
        node.className = 'ct-form__status' + (type ? ' is-' + type : '');
    }

    /* ---------- Мягкое появление блоков при скролле ---------- */
    function initReveal() {
        var targets = document.querySelectorAll('.ct-card, .ct-step, .ct-plan, .ct-service, .ct-review, .ct-tech__item, .ct-timeline__item');

        if (!('IntersectionObserver' in window) || targets.length === 0) {
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -60px 0px', threshold: 0.08 });

        targets.forEach(function (target, index) {
            target.classList.add('ct-reveal');
            target.style.transitionDelay = (index % 3) * 70 + 'ms';
            observer.observe(target);
        });
    }
})();
