$(document).ready(function() {
    let currentCallId = null;
    let statusCheckInterval = null;

    // Закрытие модальных окон
    $('.modal-auth .close-modal, .modal-auth-step .close-modal').click(function() {
        $('.modal-auth, .modal-auth-step, .wrp').removeClass('show');
        $('.modal-auth form').trigger('reset');
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
        }
    });

    // Шаг 1: Отправка номера телефона
    $('#userAuth').submit(async function(e) {
        e.preventDefault();

        var userPhone = $('#phone-user', this).val();

        if (!userPhone) {
            alert('Введите номер телефона');
            return;
        }

        $('#submit-btn').prop('disabled', true).text('Отправка...');

        try {
            const response = await BX.ajax.runComponentAction('ldo:user.auth', 'nextStep', {
                mode: 'class',
                data: { userPhone: userPhone },
            });

            if (response.data.success) {
                currentCallId = response.data.callId;

                // Сохраняем callId в поле формы
                $('#call-id').val(currentCallId);

                // Меняем интерфейс
                $('#phone-auth').hide();
                $('#code-auth').show();
                $('#modal-title').text('Введите последние 4 цифры входящего номера');
                $('#submit-btn').text('Подтвердить');

                // Показываем сообщение
                $('#call-message').text(response.data.message).show();

                // Начинаем проверку статуса звонка
                startStatusCheck();

                step = 'code';
                $('.code-input:first').focus();
            } else {
                alert(response.data.error || 'Ошибка отправки');
                $('#submit-btn').prop('disabled', false).text('Получить код');
            }
        } catch (error) {
            console.error('Ошибка:', error);
            alert('Произошла ошибка. Попробуйте позже.');
            $('#submit-btn').prop('disabled', false).text('Получить код');
        }
    });

    // Шаг 2: Подтверждение кода
    $(document).on('submit', '#codeAuthForm', async function(e) {
        e.preventDefault();

        let code = '';
        $('.code-input').each(function() {
            code += $(this).val();
        });

        if (code.length !== 4) {
            alert('Введите 4-значный код');
            return;
        }

        $('#submit-code-btn').prop('disabled', true).text('Проверка...');

        // Останавливаем проверку статуса
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
        }

        try {
            const response = await BX.ajax.runComponentAction('ldo:user.auth', 'confirmCode', {
                mode: 'class',
                data: { code: code },
            });

            if (response.data.success) {
                alert('Авторизация успешна!');
                location.reload();
            } else {
                alert(response.data.error || 'Неверный код');
                $('.code-input').val('');
                $('.code-input:first').focus();
                $('#submit-code-btn').prop('disabled', false).text('Подтвердить');

                // Возобновляем проверку статуса
                startStatusCheck();
            }
        } catch (error) {
            console.error('Ошибка:', error);
            alert('Ошибка проверки кода');
            $('#submit-code-btn').prop('disabled', false).text('Подтвердить');
            startStatusCheck();
        }
    });

    // Функция проверки статуса звонка
    function startStatusCheck() {
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
        }

        // Проверяем каждые 3 секунды
        statusCheckInterval = setInterval(async function() {
            if (!currentCallId) return;

            try {
                const response = await BX.ajax.runComponentAction('ldo:user.auth', 'checkCallStatus', {
                    mode: 'class',
                    data: { callId: currentCallId },
                });

                if (response.data.success) {
                    if (response.data.status === 'NORMAL_CLEARING') {
                        // Звонок успешен, показываем сообщение
                        $('#call-status').text('Звонок принят! Введите полученный код.').css('color', 'green');

                        // Останавливаем проверку, так как звонок уже прошел
                        clearInterval(statusCheckInterval);
                        statusCheckInterval = null;
                    } else if (response.data.status === 'USER_BUSY') {
                        $('#call-status').text('Номер занят. Попробуйте снова.').css('color', 'red');
                    } else if (response.data.status === 'NO_ANSWER') {
                        $('#call-status').text('Нет ответа. Попробуйте снова.').css('color', 'red');
                    }
                }
            } catch (error) {
                console.error('Ошибка проверки статуса:', error);
            }
        }, 3000);

        // Таймаут через 60 секунд
        setTimeout(function() {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
                statusCheckInterval = null;
                $('#call-status').text('Время ожидания истекло. Попробуйте снова.').css('color', 'red');
            }
        }, 60000);
    }
});

// Обработка ввода кода (как было ранее)
$(document).on('keyup', '.code-input', function(e) {
    let $this = $(this);
    let $wrapper = $this.closest('.code-input-block');
    let $inputs = $wrapper.find('.code-input');
    let currentIndex = $inputs.index($this);

    if (/^[0-9]$/.test(e.key)) {
        $this.val(e.key);

        if (currentIndex < $inputs.length - 1) {
            $inputs.eq(currentIndex + 1).focus();
        }

        if (currentIndex === $inputs.length - 1) {
            let code = '';
            $inputs.each(function() {
                code += $(this).val();
            });
            if (code.length === $inputs.length) {
                $('#codeAuthForm').submit();
            }
        }
    }

    if (e.key === 'Backspace') {
        if ($this.val() === '') {
            if (currentIndex > 0) {
                $inputs.eq(currentIndex - 1).val('').focus();
            }
        } else {
            $this.val('');
        }
    }
});