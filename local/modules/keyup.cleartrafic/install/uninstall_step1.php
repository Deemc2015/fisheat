<?php
/**
 * Первый шаг удаления модуля: выбор — сохранять ли таблицы базы данных.
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

$moduleId = 'keyup.cleartrafic';
$tables = [
    'keyup_cleartrafic_rule',
    'keyup_cleartrafic_email',
    'keyup_cleartrafic_visit',
    'keyup_cleartrafic_form_log',
];
?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="hidden" name="id" value="<?= htmlspecialcharsbx($moduleId) ?>">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">

    <p><?= Loc::getMessage('KEYUP_CLEARTRAFIC_UNINSTALL_SAVE_NOTE') ?></p>

    <p>
        <?= Loc::getMessage('KEYUP_CLEARTRAFIC_UNINSTALL_TABLES') ?>
        <b><?= implode(', ', array_map('htmlspecialcharsbx', $tables)) ?></b>
    </p>

    <p>
        <input type="checkbox" name="savedata" id="savedata" value="Y" checked>
        <label for="savedata"><?= Loc::getMessage('KEYUP_CLEARTRAFIC_UNINSTALL_SAVE_LABEL') ?></label>
    </p>

    <p style="color:#a00;"><?= Loc::getMessage('KEYUP_CLEARTRAFIC_UNINSTALL_SAVE_HINT') ?></p>

    <p>
        <input type="submit" name="inst" value="<?= Loc::getMessage('KEYUP_CLEARTRAFIC_UNINSTALL_DELETE_BUTTON') ?>">
    </p>
</form>
