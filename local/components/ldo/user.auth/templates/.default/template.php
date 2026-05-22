<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */

/** @var CBitrixComponent $component */


?>


<?if($arResult['NOT_AUTH_USER']):?>
    <div class="wrp"></div>
    <div class="modal-auth ">
        <span class="close-modal"></span>
        <h3>Войти или создать профиль</h3>
        <form id="userAuth" action="#" method="POST">
            <div id="phone-auth">
                <input id="phone-user" name="USER_PHONE" type="text" value="" required>
            </div>

            <button type="submit">Получить код</button>
        </form>
        <div class="bottom-auth">
            Нажимая на кнопку, я даю <a target="_blank" href="">согласие на обработку моих персональных данных</a>.
            <a target="_blank" href="">Политика конфиденциальности</a>
        </div>
    </div>

    <div class="modal-auth-step">
        <span class="return-step"></span>
        <span class="close-modal"></span>
        <h3 id="modal-title">Введите последние 4 цифры входящего номера</h3>
        <form id="codeAuthForm" action="#" method="POST">
            <div id="call-message" style="display: none; margin-bottom: 15px; text-align: center;"></div>
            <div id="call-status" style="margin-bottom: 15px; text-align: center; color: #666;"></div>

            <div class="code-input-block" data-amount="4">
                <div class="code-input-wrapper">
                    <input type="text" inputmode="numeric" autocomplete="one-time-code" class="code-input" maxlength="1">
                </div>
                <div class="code-input-wrapper">
                    <input type="text" inputmode="numeric" autocomplete="one-time-code" class="code-input" maxlength="1">
                </div>
                <div class="code-input-wrapper">
                    <input type="text" inputmode="numeric" autocomplete="one-time-code" class="code-input" maxlength="1">
                </div>
                <div class="code-input-wrapper">
                    <input type="text" inputmode="numeric" autocomplete="one-time-code" class="code-input" maxlength="1">
                </div>
            </div>

            <input type="hidden" id="call-id">

            <span class="error-block"></span>

            <button id="submit-code-btn" type="submit">Подтвердить</button>
        </form>
    </div>

<?endif;?>
