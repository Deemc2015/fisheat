<?php
define("NO_KEEP_STATISTIC", true);
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
use Bitrix\Main\Loader;
if(!Loader::IncludeModule('keyup.cleartrafic')){
    echo "Не установлен модуль фильтрации трафика";
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="format-detection" content="telephone=no">
    <title>Доступ временно ограничен</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <link href="/local/modules/keyup.cleartrafic/assets/style.css" type="text/css" rel="stylesheet" />
</head>
<body class="bp-body">
<main class="bp-wrap">
    <section class="bp-card">
        <div class="bp-icon" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 3l7 3v6c0 4.5-3 7.6-7 9-4-1.4-7-4.5-7-9V6l7-3z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                <path d="M12 9v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <circle cx="12" cy="16" r="1" fill="currentColor"/>
            </svg>
        </div>

        <h1 class="bp-title">Доступ временно ограничен</h1>
        <p class="bp-text">С вашего IP-адреса зафиксирована подозрительная активность, поэтому дальнейшая работа с сайтом сейчас приостановлена.</p>
        <p class="bp-text bp-text--muted">Если это произошло по ошибке — отправьте нам сообщение, и мы вернём доступ.</p>

        <div class="bp-divider"></div>

        <form id="modal-black" class="bp-form" action="#" method="post">
            <input type="text" name="NAME" placeholder="Ваше имя" required autocomplete="name">
            <input type="text" name="EMAIL" placeholder="E-mail для ответа" required autocomplete="email">
            <input type="hidden" name="sessid" value="<?=bitrix_sessid()?>">
            <input type="hidden" name="TYPE" value="Страница черного IP">
            <input type="hidden" name="TABLE" value="yes">

            <textarea name="TEXT" id="message" rows="7" placeholder="Опишите ситуацию: какую сеть используете (если знаете), пользовались ли ранее нашим сайтом, были ли клиентом, когда последний раз заходили. Для возврата доступа важны любые технические детали."></textarea>

            <div class="bp-row">
                <label class="bp-file" for="file">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    Прикрепить файл
                    <input id="file" name="IMAGE_ID" class="bp-file__input" type="file">
                    <span class="bp-file__name"></span>
                </label>

                <button type="submit" class="bp-submit">Отправить сообщение</button>
            </div>
        </form>
    </section>
</main>

<div class="bp-modal" id="bp-success" role="dialog" aria-modal="true" aria-live="polite">
    <div class="bp-modal__box">
        <div class="bp-modal__icon" aria-hidden="true">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h2 class="bp-modal__title" id="bp-modal-title">Сообщение отправлено</h2>
        <p class="bp-modal__text" id="bp-modal-text">Спасибо за обращение. Мы рассмотрим вашу ситуацию и свяжемся с вами.</p>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script>
$(function () {
    /* Имя выбранного файла */
    $('#file').on('change', function () {
        if (this.value) {
            $('.bp-file__name').text(this.files && this.files[0] ? this.files[0].name : this.value);
        } else {
            $('.bp-file__name').text('');
        }
    });

    /* Отправка формы разблокировки */
    $('#modal-black').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var formData = new FormData($form.get(0));
        var $button = $form.find('.bp-submit');

        $button.prop('disabled', true);

        $.ajax({
            url: '/personal_filter/ajax.php',
            processData: false,
            contentType: false,
            type: 'POST',
            data: formData,
            success: function (response) {
                var data = response;

                try {
                    data = JSON.parse(response);
                } catch (err) {
                    data = response;
                }

                if (data == 'success') {
                    $('#bp-modal-title').text('Сообщение отправлено');
                    $('#bp-modal-text').text('Спасибо за обращение. Мы рассмотрим вашу ситуацию и свяжемся с вами.');
                    $('#bp-success').addClass('show');
                } else if (data == 'time') {
                    $('#bp-modal-title').text('Сообщение уже отправлено');
                    $('#bp-modal-text').text('Отправить новое сообщение можно не чаще одного раза в минуту. Пожалуйста, подождите немного.');
                    $('#bp-success').addClass('show');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log('Error: ' + errorThrown);
            },
            complete: function () {
                $button.prop('disabled', false);
            }
        });
    });
});
</script>
</body>
</html>
