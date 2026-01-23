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

use Bitrix\Main\Localization\Loc;
?>

<form action="#" id="profile-form">
    <div class="form-line">
        <div class="form-group">
            <label for="name">Имя *</label>
            <div class="input-edit name">
                <input type="text" name="NAME" id="name" required placeholder="Имя" value="<?=($arResult['NAME'] ?? ' ')?>">
                <?if($arResult['NAME']):?>
                    <span class="clear"></span>
                <?endif;?>
            </div>
        </div>
        <div class="form-group">
            <label for="name">Email</label>
            <div class="input-edit email">
                <input type="text" name="EMAIL" id="email" placeholder="Почта" value="<?=($arResult['EMAIL'] ?? ' ')?>">
                <?if($arResult['EMAIL']):?>
                    <span class="clear"></span>
                <?endif;?>
            </div>
        </div>
    </div>
    <div class="form-line">
        <div class="form-group">
            <label for="name">Телефон *</label>
            <div class="input-edit phone">
                <input type="text" name="PHONE" id="phone" required placeholder="Телефон" value="<?=($arResult['PHONE'] ?? ' ')?>">
                <?if($arResult['PHONE']):?>
                    <span class="clear"></span>
                <?endif;?>
            </div>
        </div>
        <div class="form-group">
            <label for="name">День рождения </label>
            <div class="input-edit dateB">
                <input type="text" name="DATEB" id="dateB" <?if($arResult['BIRTHDAY']){echo "readonly";}?> placeholder="__.__.____г." value="<?=($arResult['BIRTHDAY'] ?? ' ')?>">
            </div>
        </div>
    </div>
    <div class="infoDate">Дату рождения можно указать только один раз.</div>

    <div class="button-line">
        <button type="submit" class="button-line__save">Сохранить</button>
        <a class="button-line__exit" href="/?logout=yes&<?=bitrix_sessid_get()?>">Выйти</a>
        <div class="button-line__delete-account">Удалить аккаунт</div>
    </div>
</form>

<div class="wrp"></div>
<div class="modal-delete">
    <span class="close-modal"></span>
    <div class="top-title">Удалить аккаунт</div>
    <div class="text-modal">Вы действительно хотите удалить?</div>
    <div class="button-modal">
        <div class="cancel">Отмена</div>
        <div class="delete">Да</div>
    </div>

</div>