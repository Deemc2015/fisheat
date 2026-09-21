/*Общие скрипты личного кабинета модуля фильтрации трафика*/

/* Локализация datepicker */
if (typeof $.datepicker !== 'undefined') {
    $.datepicker.regional['ru'] = {
        closeText: 'Закрыть',
        prevText: 'Предыдущий',
        nextText: 'Следующий',
        currentText: 'Сегодня',
        monthNames: ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'],
        monthNamesShort: ['Янв','Фев','Мар','Апр','Май','Июн','Июл','Авг','Сен','Окт','Ноя','Дек'],
        dayNames: ['воскресенье','понедельник','вторник','среда','четверг','пятница','суббота'],
        dayNamesShort: ['вск','пнд','втр','срд','чтв','птн','сбт'],
        dayNamesMin: ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'],
        weekHeader: 'Не',
        dateFormat: 'dd.mm.yy',
        firstDay: 1,
        isRTL: false,
        showMonthAfterYear: false,
        yearSuffix: ''
    };
    $.datepicker.setDefaults($.datepicker.regional['ru']);
}

$(function () {
    /*Поллинг текущего времени (только если на странице есть блок #time)*/
    if ($('#time').length) {
        function updateTime() {
            $.ajax({
                type: 'POST',
                url: '/personal_filter/datetime.php',
                timeout: 1000,
                success: function (data) {
                    $("#time").html(data);
                    window.setTimeout(updateTime, 1000);
                }
            });
        }
        updateTime();
    }

    /*Переключение блока выбора периода*/
    $('a.period').click(function (e) {
        e.preventDefault();
        $('.period-date').toggleClass('show');
    });

    /*Поиск по IP*/
    $('.searchIp').click(function () {
        var url = window.location;
        var ip = $('.ip').val();

        if (!ip) {
            $('.ip').addClass('error');
            return false;
        }

        if (hasDateOrDateStartParameter()) {
            url = url + '&ip=' + ip;
        } else {
            url = url + '?ip=' + ip;
        }

        window.location.replace(url);
    });

    $('.ip').focus(function () {
        if ($(this).hasClass('error')) {
            $(this).removeClass('error');
        }
    });

    /*Инициализация datepicker (только если подключён jQuery UI)*/
    if (typeof $.fn.datepicker !== 'undefined') {
        $("#datepicker").datepicker();
        $("#datepicker_2").datepicker();
    }
});

function hasDateOrDateStartParameter() {
    const search = location.search.slice(1);
    const params = search.split('&').reduce(function (params, pair) {
        const parts = pair.split('=');
        const key = parts[0];
        const value = parts[1] || '';
        params[decodeURIComponent(key)] = decodeURIComponent(value);
        return params;
    }, {});

    return 'date' in params || 'dateStart' in params;
}

/*Импорт списков из файла: маски подсетей, серый и чёрный списки.
  Форма должна иметь класс import-file и атрибут data-rule-type с типом правила.*/
$(document).on('submit', 'form.import-file', function (e) {
    e.preventDefault();

    var $form = $(this);
    var fileInput = $form.find('input[type="file"]')[0];

    if (!fileInput || !fileInput.files.length) {
        return;
    }

    var $modal = $form.closest('.modalAdd, .modalEdit');
    var formData = new FormData();

    formData.append('sessid', window.PF_SESSID || '');
    formData.append('RULE_TYPE', $form.data('rule-type') || '');
    formData.append('FILE', fileInput.files[0]);

    $modal.addClass('loading');
    $modal.find('.error, .import-result').text('');

    $.ajax({
        url: '/personal_filter/ajax.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json'
    }).done(function (response) {
        $modal.removeClass('loading');

        if (!response || response.error) {
            $modal.find('.error').text('Не удалось загрузить файл: ' + ((response && response.error) ? response.error : 'ошибка'));
            return;
        }

        var text = 'Добавлено: ' + response.added + '. Пропущено дубликатов: ' + response.skipped + '.';

        if (response.invalid) {
            text += ' Некорректных строк: ' + response.invalid + '.';
        }

        $modal.find('.import-result').text(text);

        window.setTimeout(function () {
            window.location.reload();
        }, 1500);
    }).fail(function () {
        $modal.removeClass('loading');
        $modal.find('.error').text('Произошла ошибка');
    });
});
