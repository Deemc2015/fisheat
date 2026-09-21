<?php

use Bitrix\Main\Localization\Loc;
use Keyup\Cleartrafic\Settings;

Loc::loadMessages(__FILE__);

$module_id = 'keyup.cleartrafic';

/** @global CMain $APPLICATION */
global $APPLICATION;

if ($APPLICATION->GetGroupRight($module_id) < 'W') {
    return;
}

\Bitrix\Main\Loader::includeModule($module_id);

$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    Settings::setCaptchaSecret($_REQUEST['captcha_secret']);
    Settings::setCaptchaSiteKey($_REQUEST['captcha_site_key']);
    Settings::setCaptchaTime($_REQUEST['captcha_time']);
    Settings::setRetentionDays($_REQUEST['retention_days']);
    Settings::setAnalyticsHead($_REQUEST['analytics_head']);
    Settings::setAnalyticsBody($_REQUEST['analytics_body']);

    $saved = true;
}

$captchaSecret = Settings::getCaptchaSecret();
$captchaSiteKey = Settings::getCaptchaSiteKey();
$captchaTime = Settings::getCaptchaTime();
$retentionDays = Settings::getRetentionDays();
$analyticsHead = Settings::getAnalyticsHead();
$analyticsBody = Settings::getAnalyticsBody();

if ($saved):
?>
<div class="adm-info-message-wrap">
    <div class="adm-info-message">
        <?= GetMessage('KEYUP_CLEARTRAFIC_OPTIONS_SAVED') ?>
    </div>
</div>
<?php
endif;
?>
<form method="POST" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage(false) . '?mid=' . urlencode($module_id) . '&lang=' . LANGUAGE_ID) ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="mid" value="<?= htmlspecialcharsbx($module_id) ?>">
    <table class="adm-detail-content-table edit-table">
        <tr>
            <td width="40%" class="adm-detail-content-cell-l">
                <label for="captcha_secret"><?= GetMessage('KEYUP_CLEARTRAFIC_OPTIONS_CAPTCHA_SECRET') ?></label>
            </td>
            <td width="60%" class="adm-detail-content-cell-r">
                <input type="text" size="60" id="captcha_secret" name="captcha_secret" value="<?= htmlspecialcharsbx($captchaSecret) ?>">
                <br>
                <small><?= GetMessage('KEYUP_CLEARTRAFIC_OPTIONS_CAPTCHA_SECRET_HINT') ?></small>
            </td>
        </tr>
        <tr>
            <td width="40%" class="adm-detail-content-cell-l">
                <label for="captcha_site_key"><?= GetMessage('KEYUP_CLEARTRAFIC_OPTIONS_CAPTCHA_SITE_KEY') ?></label>
            </td>
            <td width="60%" class="adm-detail-content-cell-r">
                <input type="text" size="60" id="captcha_site_key" name="captcha_site_key" value="<?= htmlspecialcharsbx($captchaSiteKey) ?>">
                <br>
                <small><?= GetMessage('KEYUP_CLEARTRAFIC_OPTIONS_CAPTCHA_SITE_KEY_HINT') ?></small>
            </td>
        </tr>
        <tr>
            <td width="40%" class="adm-detail-content-cell-l">
                <label for="captcha_time"><?= GetMessage('KEYUP_CLEARTRAFIC_OPTIONS_CAPTCHA_TIME') ?></label>
            </td>
            <td width="60%" class="adm-detail-content-cell-r">
                <input type="number" min="1" step="1" size="10" id="captcha_time" name="captcha_time" value="<?= (int)$captchaTime ?>">
                <br>
                <small><?= GetMessage('KEYUP_CLEARTRAFIC_OPTIONS_CAPTCHA_TIME_HINT') ?></small>
            </td>
        </tr>
        <tr>
            <td width="40%" class="adm-detail-content-cell-l">
                <label for="retention_days"><?= GetMessage('KEYUP_CLEARTRAFIC_OPTIONS_RETENTION') ?></label>
            </td>
            <td width="60%" class="adm-detail-content-cell-r">
                <input type="number" min="0" step="1" size="10" id="retention_days" name="retention_days" value="<?= (int)$retentionDays ?>">
                <br>
                <small><?= GetMessage('KEYUP_CLEARTRAFIC_OPTIONS_RETENTION_HINT') ?></small>
            </td>
        </tr>
        <tr>
            <td width="40%" class="adm-detail-content-cell-l">
                <label for="analytics_head"><?= GetMessage('KEYUP_CLEARTRAFIC_OPTIONS_ANALYTICS_HEAD') ?></label>
            </td>
            <td width="60%" class="adm-detail-content-cell-r">
                <textarea cols="60" rows="8" id="analytics_head" name="analytics_head" style="font-family:monospace;"><?= htmlspecialcharsbx($analyticsHead) ?></textarea>
                <br>
                <small><?= GetMessage('KEYUP_CLEARTRAFIC_OPTIONS_ANALYTICS_HINT') ?></small>
            </td>
        </tr>
        <tr>
            <td width="40%" class="adm-detail-content-cell-l">
                <label for="analytics_body"><?= GetMessage('KEYUP_CLEARTRAFIC_OPTIONS_ANALYTICS_BODY') ?></label>
            </td>
            <td width="60%" class="adm-detail-content-cell-r">
                <textarea cols="60" rows="8" id="analytics_body" name="analytics_body" style="font-family:monospace;"><?= htmlspecialcharsbx($analyticsBody) ?></textarea>
                <br>
                <small><?= GetMessage('KEYUP_CLEARTRAFIC_OPTIONS_ANALYTICS_HINT') ?></small>
            </td>
        </tr>
    </table>
    <input type="submit" name="save" class="adm-btn-save" value="<?= GetMessage('KEYUP_CLEARTRAFIC_OPTIONS_SAVE') ?>">
</form>
