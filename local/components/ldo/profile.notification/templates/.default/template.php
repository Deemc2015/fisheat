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

use Bitrix\Main\Localization\Loc;?>

<form action="#" id="notification-form">
    <div class="form-line">
        <label for="email">
            <span><?=Loc::getMessage('EMAIL_TITLE');?></span>
            <input <?if($arResult['EMAIL']){echo 'checked';}?>  type="checkbox" name="email" id="email">
            <div class="checked-button-notification <?if($arResult['EMAIL']){echo 'active';}?>"></div>
        </label>
    </div>

    <div class="form-line">
        <label for="sms">
            <span><?=Loc::getMessage('SMS_TITLE');?></span>
            <input <?if($arResult['SMS']){echo 'checked';}?>  type="checkbox" name="sms" id="sms">
            <div class="checked-button-notification <?if($arResult['SMS']){echo 'active';}?>"></div>
        </label>
    </div>

    <div class="form-line">
        <label for="push">
            <span><?=Loc::getMessage('PUSH_TITLE');?></span>
            <input <?if($arResult['PUSH']){echo 'checked';}?>  type="checkbox" name="push" id="push">
            <div class="checked-button-notification <?if($arResult['PUSH']){echo 'active';}?>"></div>
        </label>
    </div>

    <div class="bottom-button">
        <button disabled class="save" type="submit"><?=Loc::getMessage('BTN_SAVE');?></button>
        <div class="mute"><?=Loc::getMessage('BTN_MUTE');?></div>
    </div>

</form>
