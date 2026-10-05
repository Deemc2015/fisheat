<?php
/**
 * Настройки модуля ldo.marketing.
 * Подключается из /bitrix/admin/settings.php (prolog уже выполнен).
 *
 * @global CMain $APPLICATION
 * @global CUser $USER
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;

IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"] . BX_ROOT . "/modules/main/options.php");
IncludeModuleLangFile(__FILE__);

$module_id = 'ldo.marketing';
CModule::IncludeModule($module_id);

$RIGHT = $APPLICATION->GetGroupRight($module_id);
if ($RIGHT < 'R' && !$USER->IsAdmin()) {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

// Автозагрузка класса настроек (на случай, если модуль ещё не зарегистрирован).
if (!class_exists('\\Ldo\\Marketing\\Settings')) {
    $settingsPath = getLocalPath('modules/' . $module_id . '/lib/Settings.php');
    if ($settingsPath !== false) {
        require_once $_SERVER['DOCUMENT_ROOT'] . $settingsPath;
    }
}
$optionPrefix = class_exists('\\Ldo\\Marketing\\Settings')
    ? \Ldo\Marketing\Settings::OPTION_CATALOG_IBLOCK
    : 'catalog_iblock_id_';

// Сайты.
$arSites = [];
$rs = SiteTable::getList([
    'select' => ['LID', 'NAME'],
    'order'  => ['SORT' => 'ASC', 'LID' => 'ASC'],
]);
while ($s = $rs->fetch()) {
    $arSites[] = $s;
}

// Инфоблоки для выбора.
$arIblocks = [];
if (Loader::includeModule('iblock')) {
    $rs = \CIBlock::GetList(['NAME' => 'ASC'], []);
    while ($ib = $rs->Fetch()) {
        $arIblocks[] = [
            'ID'   => (int)$ib['ID'],
            'NAME' => (string)$ib['NAME'],
            'CODE' => (string)$ib['CODE'],
        ];
    }
}

$aTabs = [
    [
        'DIV'   => 'edit1',
        'TAB'   => Loc::getMessage('LDO_MARKETING_OPT_TAB'),
        'TITLE' => Loc::getMessage('LDO_MARKETING_OPT_TAB_TITLE'),
    ],
];
$tabControl = new CAdminTabControl('tabControl', $aTabs);

$canWrite = ($USER->IsAdmin() || $RIGHT >= 'W');

// Сохранение.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid() && $canWrite) {
    foreach ($arSites as $site) {
        $key = $optionPrefix . $site['LID'];
        $val = (int)($_REQUEST[$key] ?? 0);
        COption::SetOptionString($module_id, $key, (string)$val);
    }

    LocalRedirect(
        $APPLICATION->GetCurPage()
        . '?mid=' . urlencode($module_id)
        . '&lang=' . urlencode(LANGUAGE_ID)
        . '&' . $tabControl->ActiveTabParam()
    );
}
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($module_id) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <? $tabControl->Begin(); ?>
    <? $tabControl->BeginNextTab(); ?>

    <tr>
        <td width="50%" class="adm-detail-content-cell-l" valign="top">
            <label><?= Loc::getMessage('LDO_MARKETING_OPT_DESC') ?></label>
        </td>
        <td width="50%" class="adm-detail-content-cell-r" valign="top">
            <div class="adm-info-message"><?= Loc::getMessage('LDO_MARKETING_OPT_HINT') ?></div>
        </td>
    </tr>

    <? foreach ($arSites as $site): ?>
        <tr>
            <td class="adm-detail-content-cell-l" valign="middle">
                <label><?= Loc::getMessage('LDO_MARKETING_OPT_SITE') ?>
                    &laquo;<?= htmlspecialcharsbx($site['NAME'] !== '' ? $site['NAME'] : $site['LID']) ?>&raquo;
                    (<?= htmlspecialcharsbx($site['LID']) ?>):</label>
            </td>
            <td class="adm-detail-content-cell-r" valign="middle">
                <? $current = (int)COption::GetOptionString($module_id, $optionPrefix . $site['LID'], '0'); ?>
                <select name="catalog_iblock_id_<?= htmlspecialcharsbx($site['LID']) ?>" style="max-width: 420px;">
                    <option value="0"><?= Loc::getMessage('LDO_MARKETING_OPT_NOT_SET') ?></option>
                    <? foreach ($arIblocks as $ib): ?>
                        <option value="<?= $ib['ID'] ?>"<?= $current === $ib['ID'] ? ' selected' : '' ?>>
                            [<?= $ib['ID'] ?>] <?= htmlspecialcharsbx($ib['NAME']) ?><?= $ib['CODE'] !== '' ? ' (' . htmlspecialcharsbx($ib['CODE']) . ')' : '' ?>
                        </option>
                    <? endforeach; ?>
                </select>
            </td>
        </tr>
    <? endforeach; ?>

    <? $tabControl->End(); ?>
    <div class="adm-detail-content-btns-wrap">
        <div class="adm-detail-content-btns">
            <input type="submit" name="save" value="<?= Loc::getMessage('LDO_MARKETING_OPT_SAVE') ?>" class="adm-btn-save">
        </div>
    </div>
</form>
