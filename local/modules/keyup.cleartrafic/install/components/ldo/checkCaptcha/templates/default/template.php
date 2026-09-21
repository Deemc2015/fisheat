<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();?>

<?
$time = $arResult['TIME'];
$time = $time*1000;

if($arResult['CAPTCHA']):?>
    <script src="https://smartcaptcha.yandexcloud.net/captcha.js" defer></script>
    <div class="wrp-bot"></div>
    <div class="modal-ip-captcha">
        <p>Для продолжения подтвердите, что вы не бот</p>
        <form id="formCaptcha" action="#" method="post">
            <div style="height: 100px" id="captcha-container"></div>
            <p class="not-captcha">Идёт загрузка капчи, подождите <span></span></p>
        </form>
        <button id="submit" >Отправить</button>
        <p class="afterFormLink">Если капча работает некорректно, пожалуйста, <span>сообщите нам</span> об этом.</p>
    </div>

<?endif?>

<div class="modalError">
    <p class="title">Сообщите нам о проблеме</p>
    <form id="modalError" enctype="multipart/form-data">
        <input type="text" name="NAME" id="name" placeholder="Ваше имя" required>
        <input type="text" name="EMAIL" id="email" placeholder="E-mail" required>
        <input type="hidden" name="sessid" value="<?=bitrix_sessid()?>">
        <input type="hidden" name="TYPE" value="Форма нерабочей капчи">
        <textarea id="text" placeholder="Постарайтесь подробно описать проблему, по возможности прикрепите скриншот, указывающий на невозможность продолжить." name ="TEXT"></textarea>
        <div class="button-bottom">
            <label for="typefile">+ прикрепить файл
            <input id="file" name="IMAGE_ID" class="typefile" size="20" type="file"><span class="bx-input-file-desc"></span>            </label>
            <button type="submit">Отправить</button>
        </div>
        <div class="file-info"></div>
    </form>
</div>

<div class="succes-modal">
    <div class="title">
        <span>Сообщение отправлено!</span> Спасибо вам за сигнал!
    </div>
    <p>Мы свяжемся с вами для возможности предоставления доступа к сайту.</p>
</div>

<script>
    $('textarea').focus(function(){
        $(this).addClass('active');
    })
    document.getElementById('file').addEventListener('change', function(e){
      if( this.value ){
        $('.file-info').html(e.target.files[0].name);
      } 
    });
   
    /*Рендерим виджет SmartCaptcha вручную, когда модалка становится видимой.
      Авто-рендер в скрытый (display:none) контейнер вызывает ошибки гидрации React
      (React error #418/#423), поэтому рендер выполняем только после показа модалки.*/
    function initSmartCaptcha(){
        if (window.smartCaptcha && !window.__smartCaptchaRendered) {
            var container = document.getElementById('captcha-container');
            if (container && container.children.length === 0) {
                window.smartCaptcha.render('captcha-container', {
                    sitekey: '<?= $arResult['KEY'] ?>'
                });
                window.__smartCaptchaRendered = true;
                $('.not-captcha').hide();
            }
        }
    }

    setTimeout(function(){
        if($('.modal-ip-captcha').hasClass('show') == false){
            $('.modal-ip-captcha').addClass('show');
        }
        initSmartCaptcha();
    },<?=$time?>)

    $('.wrp-bot').click(function(){
        if($('.modal-ip-captcha').hasClass('show') == false){
            $('.modal-ip-captcha').addClass('show');
        }
        initSmartCaptcha();
    })

    /*Если скрипт каптчи ещё не загрузился (defer), пробуем после полной загрузки страницы*/
    window.addEventListener('load', initSmartCaptcha);

    $(function () {
        function sendFormData(dataForm) {
            if(dataForm){
                $.ajax({
                    url: '/personal_filter/ajax.php',
                    type: "post",
                    data: {data: dataForm, type: 'CAPTCHA', sessid: '<?=bitrix_sessid()?>'},
                    success: function(response) {
                        if(response){
                            location.reload();
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.log('Error: '+ errorThrown);
                    }
                });
            }
        }

        // Правильная привязка события
        $('#submit').on('click', function(e) {
            e.preventDefault(); // предотвращаем стандартное действие формы

            // Сериализуем данные формы
            var token = $('#formCaptcha input[name="smart-token"]').val(); // или укажите селектор вашей формы
            sendFormData(token);
        });
    });
</script>
