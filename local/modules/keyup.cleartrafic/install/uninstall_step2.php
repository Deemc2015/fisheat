<?php
/**
 * Второй шаг удаления модуля: результат.
 *
 * Подключается методом DoUninstall() через $APPLICATION->IncludeAdminFile().
 */

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loc::loadMessages(__DIR__ . '/index.php');

global $APPLICATION;

if (!check_bitrix_sessid()) {
    return;
}

$savedata = ($GLOBALS['KEYUP_CLEARTRAFIC_UNINSTALL_SAVEDATA'] ?? 'N') === 'Y';

CAdminMessage::ShowNote(
    $savedata
        ? Loc::getMessage('KEYUP_CLEARTRAFIC_UNINSTALL_RESULT_SAVED')
        : Loc::getMessage('KEYUP_CLEARTRAFIC_UNINSTALL_RESULT_DROPPED')
);
?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="submit" value="<?= Loc::getMessage('KEYUP_CLEARTRAFIC_UNINSTALL_BACK') ?>">
</form>
