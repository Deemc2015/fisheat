$(document).ready(function(){


    $('#codeAuthForm').on('submit', function(e) {
        e.preventDefault(); // Останавливаем обычную отправку

        // Собираем код из всех полей
        let code = '';
        $('.code-input').each(function() {
            code += $(this).val();
        });

        if (code.length !== 4) {
            alert('Введите все 4 цифры');
            return false;
        }

        // Записываем код в скрытое поле (если нужно)
        $('#confirm_code').val(code);

        // Вызываем функцию confirmCode
        confirmCode(code).then(function(result) {
            if (result) {
                // Успешное подтверждение кода
                console.log('Код подтвержден успешно');

                // Закрываем модальное окно
                $('.modal-auth-step, .wrp').removeClass('show');

                // Дополнительные действия при успехе
                // Например, перезагрузить страницу или показать сообщение
                // location.reload(); // Раскомментировать если нужно перезагрузить

            } else {
                // Ошибка подтверждения кода
                alert('Неверный код. Попробуйте снова.');

                // Очищаем поля ввода
                $('.code-input').val('');

                // Устанавливаем фокус на первое поле
                $('.code-input').first().focus();
            }
        });
    });




    $('.modal-auth .close-modal').click(function(){
        $('.modal-auth, .wrp').removeClass('show');
        $('.modal-auth form').trigger('reset');
        // Также скрываем второй шаг
        $('.modal-auth-step').removeClass('show');
    })


    $('.modal-auth-step .close-modal').click(function(){
        $('.modal-auth-step, .wrp').removeClass('show');
        $('.modal-auth-step').removeClass('show');
        $('.modal-auth form,.modal-auth-step form').trigger('reset');
    })

    $('#userAuth').submit(function(e){
        e.preventDefault();

        var userPhone = $('#phone-user', this).val();

        if(userPhone){
            // Используем async/await внутри обработчика
            getNextStep(userPhone).then(function(result) {

                if(result){
                    $('.modal-auth').removeClass('show');
                    $('.modal-auth-step').addClass('show');
                }
            });
        }
    })

})

$(document).on('keyup', '.code-input', function(e) {
    let $this = $(this);
    let $wrapper = $this.closest('.code-input-block');
    let $inputs = $wrapper.find('.code-input');
    let currentIndex = $inputs.index($this);

    // Получаем нажатую клавишу
    let key = e.key;

    // Ввод цифры (любая цифра от 0 до 9)
    if(/^[0-9]$/.test(key)) {
        // Если поле пустое или заменяем значение
        if($this.val().length === 0 || e.keyCode !== 46) {
            $this.val(key);
        }

        // Переход к следующему полю
        if(currentIndex < $inputs.length - 1) {
            $inputs.eq(currentIndex + 1).focus();
        }

        // Если последнее поле - проверяем код
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

    // Обработка Backspace
    if(e.key === 'Backspace') {
        if($this.val() === '') {
            // Если поле пустое - переходим к предыдущему
            if(currentIndex > 0) {
                $inputs.eq(currentIndex - 1).val('').focus();
            }
        } else {
            // Если есть значение - очищаем текущее поле
            $this.val('');
        }
        e.preventDefault();
    }

    // Обработка Delete
    if(e.key === 'Delete') {
        $this.val('');
        if(currentIndex < $inputs.length - 1) {
            $inputs.eq(currentIndex + 1).focus();
        }
    }

    // Обработка стрелок
    if(e.key === 'ArrowLeft' && currentIndex > 0) {
        $inputs.eq(currentIndex - 1).focus();
    }
    if(e.key === 'ArrowRight' && currentIndex < $inputs.length - 1) {
        $inputs.eq(currentIndex + 1).focus();
    }
});

// Дополнительно: обработка вставки из буфера
$(document).on('paste', '.code-input', function(e) {
    e.preventDefault();
    let paste = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
    let digits = paste.replace(/\D/g, '').split('');
    let $wrapper = $(this).closest('.code-input-block');
    let $inputs = $wrapper.find('.code-input');

    for(let i = 0; i < Math.min(digits.length, $inputs.length); i++) {
        $inputs.eq(i).val(digits[i]);
    }

    // Фокус на следующее пустое поле или последнее
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
        // Все поля заполнены - отправляем форму
        let code = '';
        $inputs.each(function() {
            code += $(this).val();
        });
        if(code.length === $inputs.length) {
            $('#codeAuthForm').submit();
        }
    }
});

// Фокус на первом поле при открытии модального окна
$(document).on('click', '.modal-auth-step.show', function() {
    setTimeout(function() {
        $('.code-input:first').focus();
    }, 100);
});




function getNextStep(userPhone){
    return BX.ajax.runComponentAction('ldo:user.auth', 'nextStep', {
        mode: 'class',
        data: {userPhone},
    })
        .then(function(response) {
            if(response.status == 'success' && response.data.type === 'stepTwo'){
                // Сохраняем requestId если нужно
                if(response.data.requestId) {
                    $('#call-id').val(response.data.requestId);
                }
                return true;
            }
            // Если есть ошибка в ответе
            if(response.data && response.data.error) {
                alert(response.data.error);
            }
            return false;
        })
        .catch(function(error) {
            console.error('Ошибка:', error);
            // Показываем ошибку пользователю
            let errorMsg = error.errors?.[0]?.message || 'Ошибка отправки запроса';
            alert(errorMsg);
            return false;
        });
}


function confirmCode(code){
    const submitBtn = $('#codeAuthForm button[type="submit"]');
    const codeInputs = $('.code-input');

    // Блокируем форму
    submitBtn.prop('disabled', true).text('Проверка...');
    codeInputs.prop('disabled', true);

    return BX.ajax.runComponentAction('ldo:user.auth', 'confirmCode', {
        mode: 'class',
        data: {code},
    })
        .then(function(response) {
            submitBtn.prop('disabled', false).text('Подтвердить');
            codeInputs.prop('disabled', false);

            // ИСПРАВЛЕНО: Проверяем success в response.data
            if(response.status == 'success' && response.data && response.data.success === true){
                console.log('Код подтвержден успешно');
                return true;
            }

            // Ошибка - показываем сообщение от сервера
            let errorMsg = response.data?.error || 'Неверный код. Попробуйте снова.';
            alert(errorMsg);

            // Очищаем поля ввода
            $('.code-input').val('');
            $('.code-input').first().focus();

            return false;
        })
        .catch(function(error) {
            submitBtn.prop('disabled', false).text('Подтвердить');
            codeInputs.prop('disabled', false);

            console.error('Ошибка:', error);

            // Обработка ошибок от сервера
            let errorMsg = 'Ошибка проверки кода';
            if(error.errors && error.errors[0] && error.errors[0].message) {
                errorMsg = error.errors[0].message;
            } else if(error.data && error.data.error) {
                errorMsg = error.data.error;
            }

            alert(errorMsg);

            // Очищаем поля ввода
            $('.code-input').val('');
            $('.code-input').first().focus();

            return false;
        });
}










