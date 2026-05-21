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
    <div class="modal-auth">
        <span class="close-modal"></span>
        <h3>Войти или создать профиль</h3>
        <form action="#" method="POST">
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
<?endif;?>
