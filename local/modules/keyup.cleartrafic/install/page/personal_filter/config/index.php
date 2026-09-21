<?php

define("NO_KEEP_STATISTIC", true);
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Keyup\Cleartrafic\User;
use Keyup\Cleartrafic\Settings;

if (!Loader::IncludeModule('keyup.cleartrafic')) {
    echo "Не установлен модуль фильтрации трафика";
}

global $USER, $APPLICATION;

if (!$USER->isAuthorized()) {
    LocalRedirect(SITE_DIR . 'auth/');
}

$result = new User;
$permission = $result->hasPermission();

$captchaTime = Settings::getCaptchaTime();
$retentionDays = Settings::getRetentionDays();
$captchaSiteKey = Settings::getCaptchaSiteKey();
$captchaSecretSet = Settings::getCaptchaSecret() !== '' ? 'да' : 'нет';

$settingsUrl = '/bitrix/admin/settings.php?lang=' . LANGUAGE_ID . '&mid=' . Settings::MODULE_ID . '&mid_menu=1';

$pageTitle = 'Настройки';
include dirname(__DIR__) . '/header.php';
?>
<div class="reports-result-list-wrap">
    <div class="report-table-wrap">
        <table cellspacing="0" class="reports-list-table" id="report-result-table">
            <tbody>
            <tr class="reports-list-item">
                <td class="reports-first-column"><strong>Задержка показа капчи</strong></td>
                <td><?= (int)$captchaTime ?> сек.</td>
            </tr>
            <tr class="reports-list-item">
                <td><strong>Срок хранения журнала визитов</strong></td>
                <td><?= $retentionDays > 0 ? (int)$retentionDays . ' дн.' : 'без ограничения' ?></td>
            </tr>
            <tr class="reports-list-item">
                <td><strong>Публичный ключ SmartCaptcha</strong></td>
                <td><?= htmlspecialcharsbx($captchaSiteKey) ?></td>
            </tr>
            <tr class="reports-list-item">
                <td><strong>Секретный ключ SmartCaptcha задан</strong></td>
                <td><?= $captchaSecretSet ?></td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<p style="margin-top:18px;">
    <a class="edit-button" href="<?= htmlspecialcharsbx($settingsUrl) ?>">Изменить настройки модуля</a>
    <a class="edit-button" href="/personal_filter/email/">Настроить оповещения</a>
</p>

<?php include dirname(__DIR__) . '/footer.php'; ?>
