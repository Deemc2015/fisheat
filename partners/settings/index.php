<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Ldo\Iiko\SettingsTable as IikoSettingsTable;

global $USER;

$APPLICATION->SetTitle("Настройки");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Настройки");

$request = Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

$siteId = 's1';
$iikoModuleLoaded = Loader::includeModule('ldo.iiko');

// ============================================================
// Настройки синхронизации с iiko
// ============================================================
$row = ($iikoModuleLoaded) ? IikoSettingsTable::getRow($siteId) : null;
$apiLogin    = $row['API_LOGIN'] ?? '';
$secret      = $row['SECRET'] ?? '';
$appId       = $row['APP_ID'] ?? '';
$checkStatus = $row['CHECK_STATUS'] ?? 'N';
$checkDate   = $row['CHECK_DATE'] ?? null;

// Описание статуса проверки
$statusMap = [
    'N'     => ['Связь не проверялась', '#f39c12'],
    'OK'    => ['Связь установлена', '#2ecc71'],
    'ERROR' => ['Связь не установлена', '#e74c3c'],
];
$statusInfo = $statusMap[$checkStatus] ?? $statusMap['N'];

// Если связь установлена — поля блокируются, доступен режим "Изменить"
$locked = ($checkStatus === 'OK');

$partnersActivePage  = 'settings';
$partnersPageTitle   = 'Настройки';
$partnersHeaderStyle = 'padding-bottom:0; border-bottom:none;';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
?>

        <div class="p-main">
            <div class="p-section">
                <div class="p-section__header">
                    <h2 class="p-section__title">Настройки синхронизации с iiko</h2>
                    <span style="font-size:13px; color:var(--color-muted);">Сайт: <?= htmlspecialchars($siteId) ?></span>
                </div>

                <!-- Статус проверки данных -->
                <div id="iiko-status-block" style="display:flex; align-items:center; gap:8px; padding:12px 16px; margin-bottom:16px; background:rgba(255,255,255,.04); border-radius:8px; font-size:14px;">
                    <span id="iiko-status-dot" style="width:10px; height:10px; border-radius:50%; background:<?= $statusInfo[1] ?>; flex-shrink:0;"></span>
                    <span>Статус: <b id="iiko-status-label"><?= htmlspecialchars($statusInfo[0]) ?></b></span>
                    <?php if ($checkDate): ?>
                        <span id="iiko-status-date" style="color:var(--color-muted);">· <?= htmlspecialchars($checkDate->format('d.m.Y H:i')) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Сообщения -->
                <div id="iiko-save-message" style="display:none; padding:12px 16px; border-radius:8px; margin-bottom:16px;"></div>

                <?php if (!$iikoModuleLoaded): ?>
                    <div style="background:rgba(231,76,60,.12); color:#e74c3c; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
                        Модуль ldo.iiko не подключён.
                    </div>
                <?php endif; ?>

                <!-- Форма настроек iiko (все поля обязательны) -->
                <form id="iiko-settings-form" method="POST" action="" onsubmit="return saveIikoSettings(event)">
                    <input type="hidden" name="save_settings_iiko" value="Y">

                    <div class="settings-group">
                        <label for="api_login">apiLogin <span style="color:#e74c3c;">*</span></label>
                        <input type="text" id="api_login" name="api_login" required <?= $locked ? 'disabled' : '' ?>
                               value="<?= htmlspecialchars($apiLogin) ?>"
                               placeholder="API-логин iiko"
                               autocomplete="off">
                        <span class="settings-hint">Логин API для доступа к iiko Cloud (обязательное поле)</span>
                    </div>

                    <div class="settings-group">
                        <label for="secret">secret <span style="color:#e74c3c;">*</span></label>
                        <input type="text" id="secret" name="secret" required <?= $locked ? 'disabled' : '' ?>
                               value="<?= htmlspecialchars($secret) ?>"
                               placeholder="Секретный ключ"
                               autocomplete="off">
                        <span class="settings-hint">Секретный ключ приложения iiko (обязательное поле)</span>
                    </div>

                    <div class="settings-group">
                        <label for="app_id">appId <span style="color:#e74c3c;">*</span></label>
                        <input type="text" id="app_id" name="app_id" required <?= $locked ? 'disabled' : '' ?>
                               value="<?= htmlspecialchars($appId) ?>"
                               placeholder="ID приложения"
                               autocomplete="off">
                        <span class="settings-hint">Идентификатор приложения iiko (обязательное поле)</span>
                    </div>

                    <button type="submit" id="iiko-save-btn" class="p-btn p-btn--primary" data-locked="<?= $locked ? '1' : '0' ?>"
                            style="padding:10px 24px; font-size:14px; <?= $locked ? 'background:#6c757d; border-color:#6c757d;' : '' ?>"><?= $locked ? 'Изменить' : 'Сохранить настройки' ?></button>
                </form>
            </div>
        </div>

<script>
function lockIikoForm() {
    var form = document.getElementById('iiko-settings-form');
    var btn = document.getElementById('iiko-save-btn');
    form.querySelectorAll('input[name="api_login"], input[name="secret"], input[name="app_id"]').forEach(function (inp) {
        inp.disabled = true;
    });
    btn.dataset.locked = '1';
    btn.textContent = 'Изменить';
    btn.style.background = '#6c757d';
    btn.style.borderColor = '#6c757d';
}

function unlockIikoForm() {
    var form = document.getElementById('iiko-settings-form');
    var btn = document.getElementById('iiko-save-btn');
    form.querySelectorAll('input[name="api_login"], input[name="secret"], input[name="app_id"]').forEach(function (inp) {
        inp.disabled = false;
    });
    btn.dataset.locked = '0';
    btn.textContent = 'Сохранить настройки';
    btn.style.background = '';
    btn.style.borderColor = '';
}

function saveIikoSettings(e) {
    if (e && e.preventDefault) e.preventDefault();

    var form = document.getElementById('iiko-settings-form');
    var btn = document.getElementById('iiko-save-btn');
    var msg = document.getElementById('iiko-save-message');

    // Режим "Изменить" (связь установлена): разблокируем поля
    if (btn.dataset.locked === '1') {
        unlockIikoForm();
        return false;
    }

    var apiLogin = (form.querySelector('[name="api_login"]').value || '').trim();
    var secret   = (form.querySelector('[name="secret"]').value || '').trim();
    var appId    = (form.querySelector('[name="app_id"]').value || '').trim();

    function showMsg(text, ok) {
        msg.style.display = 'block';
        msg.style.background = ok ? 'rgba(46,204,113,.12)' : 'rgba(231,76,60,.12)';
        msg.style.color = ok ? '#2ecc71' : '#e74c3c';
        msg.innerHTML = (ok ? '✓ ' : '✗ ') + text;
    }

    function setIikoStatus(status, label) {
        var colors = { 'N': '#f39c12', 'OK': '#2ecc71', 'ERROR': '#e74c3c' };
        var dot = document.getElementById('iiko-status-dot');
        var lbl = document.getElementById('iiko-status-label');
        var date = document.getElementById('iiko-status-date');
        if (dot) dot.style.background = colors[status] || '#f39c12';
        if (lbl) lbl.textContent = label;
        if (date) date.textContent = '';
    }

    if (!apiLogin || !secret || !appId) {
        showMsg('Все поля (apiLogin, secret, appId) обязательны для заполнения.', false);
        return false;
    }

    btn.disabled = true;
    btn.textContent = 'Проверка...';

    $.ajax({
        url: '/bitrix/services/main/ajax.php?mode=class&action=ldo:iiko.settingscontroller.save',
        method: 'POST',
        data: {
            siteId: '<?= $siteId ?>',
            apiLogin: apiLogin,
            secret: secret,
            appId: appId,
            sessid: '<?= bitrix_sessid() ?>'
        },
        dataType: 'json',
        success: function (res) {
            btn.disabled = false;
            btn.textContent = 'Сохранить настройки';

            if (res && res.status === 'success' && res.data && res.data.success) {
                showMsg('Настройки сохранены. ' + res.data.statusLabel, true);
                setIikoStatus(res.data.status || 'OK', res.data.statusLabel || 'Связь установлена');
                // Связь установлена — блокируем поля и переключаем кнопку на "Изменить"
                lockIikoForm();
            } else {
                var err = 'Ошибка сохранения.';
                if (res && res.errors && res.errors.length) {
                    err = res.errors[0].message;
                } else if (res && res.data && res.data.statusLabel) {
                    err = res.data.statusLabel;
                }
                showMsg(err, false);
                setIikoStatus('ERROR', 'Связь не установлена');
                unlockIikoForm();
            }
        },
        error: function () {
            btn.disabled = false;
            btn.textContent = 'Сохранить настройки';
            showMsg('Ошибка запроса к серверу.', false);
            unlockIikoForm();
        }
    });

    return false;
}
</script>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
