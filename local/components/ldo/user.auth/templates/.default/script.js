$(document).ready(function(){

    // Глобальные переменные для таймера
    let timerInterval = null;
    let remainingTime = 0;

    // Функция запуска таймера
    function startTimer(seconds) {
        // Останавливаем существующий таймер если есть
        stopTimer();

        remainingTime = seconds;

        // Показываем блок таймера
        $('#timer-block').show();
        $('#timer-countdown').text(remainingTime);

        // Делаем кнопку неактивной
        $('#submit-phone-btn').prop('disabled', true);

        timerInterval = setInterval(function() {
            remainingTime--;

            if(remainingTime > 0) {
                $('#timer-countdown').text(remainingTime);
            } else {
                // Таймер закончился
                stopTimer();
                $('#timer-block').hide();
                $('#submit-phone-btn').prop('disabled', false);
            }
        }, 1000);
    }

    // Функция остановки таймера
    function stopTimer() {
        if(timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }

    // Функция сохранения времени отправки в localStorage
    function savePhoneSendTime() {
        localStorage.setItem('phone_auth_send_time', Date.now());
        localStorage.setItem('phone_auth_phone', $('#phone-user').val());
    }

    // Функция проверки и восстановления таймера
    function checkAndRestoreTimer() {
        let sendTime = localStorage.getItem('phone_auth_send_time');
        let savedPhone = localStorage.getItem('phone_auth_phone');
        let currentPhone = $('#phone-user').val();

        if(sendTime && savedPhone === currentPhone) {
            let elapsed = Math.floor((Date.now() - parseInt(sendTime)) / 1000);
            let remaining = 60 - elapsed;

            if(remaining > 0) {
                // Если таймер еще не закончился
                startTimer(remaining);
                return true;
            } else {
                // Таймер закончился - очищаем localStorage
                localStorage.removeItem('phone_auth_send_time');
                localStorage.removeItem('phone_auth_phone');
            }
        }
        return false;
    }

    // Функция очистки данных таймера
    function clearTimerData() {
        stopTimer();
        localStorage.removeItem('phone_auth_send_time');
        localStorage.removeItem('phone_auth_phone');
        $('#timer-block').hide();
        $('#submit-phone-btn').prop('disabled', false);
    }

    $('#codeAuthForm').on('submit', function(e) {
        e.preventDefault();
        let errorBlock = $('#codeAuthForm .error-block');

        let code = '';
        $('.code-input').each(function() {
            code += $(this).val();
        });

        if (code.length !== 4) {
            errorBlock.addClass('show').html('Введите все 4 цифры');
            return false;
        }

        $('#confirm_code').val(code);

        confirmCode(code).then(function(result) {
            if (result) {
                // При успешной авторизации очищаем данные таймера
                clearTimerData();
                $('.modal-auth-step, .wrp').removeClass('show');
                location.href="/personal/";
            } else {
                errorBlock.addClass('show').html('Неверный код. Попробуйте снова.');
                $('.code-input').val('');
                $('.code-input').first().focus();
            }
        });
    });

    $('.modal-auth .close-modal').click(function(){
        $('.modal-auth, .wrp').removeClass('show');
        $('.modal-auth form').trigger('reset');
        $('.modal-auth-step').removeClass('show');
        // Очищаем данные таймера при закрытии
        clearTimerData();
    });

    $('.modal-auth-step .close-modal').click(function(){
        $('.modal-auth-step, .wrp').removeClass('show');
        $('.modal-auth form,.modal-auth-step form').trigger('reset');
        // Не очищаем таймер при закрытии второго шага
    });

    $('#userAuth').submit(function(e){
        e.preventDefault();

        var userPhone = $('#phone-user', this).val();

        // Проверяем, активен ли таймер
        if($('#submit-phone-btn').prop('disabled')) {
            alert('Пожалуйста, подождите ' + remainingTime + ' секунд перед повторной отправкой');
            return false;
        }

        if(userPhone){
            // Сохраняем время отправки и телефон
            savePhoneSendTime();

            getNextStep(userPhone).then(function(result) {
                if(result){
                    $('.modal-auth').removeClass('show');
                    $('.modal-auth-step').addClass('show');
                    // Запускаем таймер после успешной отправки
                    startTimer(60);
                } else {
                    // Если отправка не удалась, очищаем данные таймера
                    clearTimerData();
                }
            }).catch(function() {
                // При ошибке очищаем таймер
                clearTimerData();
            });
        }
    });

    $('.modal-auth-step .return-step').click(function(){
        $('.modal-auth-step').removeClass('show');
        $('.modal-auth').addClass('show');

        // При возврате на первый шаг проверяем состояние таймера
        checkAndRestoreTimer();
    });

    // При загрузке страницы проверяем таймер
    // Проверяем только если модальное окно активно
    if($('.modal-auth').length > 0) {
        checkAndRestoreTimer();
    }
});

// Остальной код для работы с полями ввода кода остается без изменений
$(document).on('keyup', '.code-input', function(e) {
    let $this = $(this);
    let $wrapper = $this.closest('.code-input-block');
    let $inputs = $wrapper.find('.code-input');
    let currentIndex = $inputs.index($this);
    let errorBlock = $('#codeAuthForm .error-block');

    errorBlock.removeClass('show');

    let key = e.key;

    if(/^[0-9]$/.test(key)) {
        if($this.val().length === 0 || e.keyCode !== 46) {
            $this.val(key);
        }

        if(currentIndex < $inputs.length - 1) {
            $inputs.eq(currentIndex + 1).focus();
        }

        if(currentIndex === $inputs.length - 1) {
            let code = '';
            $inputs.each(function() {
                code += $(this).val();
            });
            if(code.length === $inputs.length) {
                $('#codeAuthForm').submit();
            }
        }
    }

    if(e.key === 'Backspace') {
        if($this.val() === '') {
            if(currentIndex > 0) {
                $inputs.eq(currentIndex - 1).val('').focus();
            }
        } else {
            $this.val('');
        }
        e.preventDefault();
    }

    if(e.key === 'Delete') {
        $this.val('');
        if(currentIndex < $inputs.length - 1) {
            $inputs.eq(currentIndex + 1).focus();
        }
    }

    if(e.key === 'ArrowLeft' && currentIndex > 0) {
        $inputs.eq(currentIndex - 1).focus();
    }
    if(e.key === 'ArrowRight' && currentIndex < $inputs.length - 1) {
        $inputs.eq(currentIndex + 1).focus();
    }
});

$(document).on('paste', '.code-input', function(e) {
    e.preventDefault();
    let paste = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
    let digits = paste.replace(/\D/g, '').split('');
    let $wrapper = $(this).closest('.code-input-block');
    let $inputs = $wrapper.find('.code-input');

    for(let i = 0; i < Math.min(digits.length, $inputs.length); i++) {
        $inputs.eq(i).val(digits[i]);
    }

    let nextEmptyIndex = -1;
    for(let i = 0; i < $inputs.length; i++) {
        if($inputs.eq(i).val() === '') {
            nextEmptyIndex = i;
            break;
        }
    }

    if(nextEmptyIndex !== -1) {
        $inputs.eq(nextEmptyIndex).focus();
    } else if(digits.length >= $inputs.length) {
        let code = '';
        $inputs.each(function() {
            code += $(this).val();
        });
        if(code.length === $inputs.length) {
            $('#codeAuthForm').submit();
        }
    }
});

// Остальные функции getNextStep и confirmCode остаются без изменений
function getNextStep(userPhone){
    return BX.ajax.runComponentAction('ldo:user.auth', 'nextStep', {
        mode: 'class',
        data: {userPhone},
    })
        .then(function(response) {
            if(response.status == 'success' && response.data.type === 'stepTwo'){
                if(response.data.requestId) {
                    $('#call-id').val(response.data.requestId);
                }
                return true;
            }
            if(response.data && response.data.error) {
                alert(response.data.error);
            }
            return false;
        })
        .catch(function(error) {
            console.error('Ошибка:', error);
            let errorMsg = error.errors?.[0]?.message || 'Ошибка отправки запроса';
            alert(errorMsg);
            return false;
        });
}

function confirmCode(code){
    const submitBtn = $('#codeAuthForm button[type="submit"]');
    const codeInputs = $('.code-input');
    let errorBlock = $('#codeAuthForm .error-block');

    submitBtn.prop('disabled', true).text('Проверка...');
    codeInputs.prop('disabled', true);

    return BX.ajax.runComponentAction('ldo:user.auth', 'confirmCode', {
        mode: 'class',
        data: {code},
    })
        .then(function(response) {
            submitBtn.prop('disabled', false).text('Подтвердить');
            codeInputs.prop('disabled', false);

            if(response.status == 'success' && response.data && response.data.success === true){
                return true;
            }

            let errorMsg = response.data?.error || 'Неверный код. Попробуйте снова.';
            errorBlock.addClass('show').html(errorMsg);
            $('.code-input').val('');
            $('.code-input').first().focus();

            return false;
        })
        .catch(function(error) {
            submitBtn.prop('disabled', false).text('Подтвердить');
            codeInputs.prop('disabled', false);

            let errorMsg = 'Ошибка проверки кода';
            if(error.errors && error.errors[0] && error.errors[0].message) {
                errorMsg = error.errors[0].message;
            } else if(error.data && error.data.error) {
                errorMsg = error.data.error;
            }

            errorBlock.addClass('show').html(errorMsg);
            $('.code-input').val('');
            $('.code-input').first().focus();

            return false;
        });
}