<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Ldo\Iiko\SettingsTable;

global $USER;

$APPLICATION->SetTitle("Настройка синхронизации с iiko");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Настройка синхронизации с iiko");

$request = Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

$partnersActivePage  = 'menu';
$partnersPageTitle   = 'Настройка синхронизации с iiko';
$partnersHeaderStyle = 'padding-bottom:0; border-bottom:none;';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

// --- Данные страницы (после шапки) ---
$siteId = 's1';
$moduleLoaded = Loader::includeModule('ldo.iiko');

// Текущие настройки и статус проверки (одна строка на сайт)
$row = ($moduleLoaded) ? SettingsTable::getRow($siteId) : null;
$apiLogin    = $row['API_LOGIN'] ?? '';
$secret      = $row['SECRET'] ?? '';
$appId       = $row['APP_ID'] ?? '';
$checkStatus = $row['CHECK_STATUS'] ?? 'N';
$checkDate   = $row['CHECK_DATE'] ?? null;

$syncResult = $request->getQuery('sync_result');
$syncOk     = $syncResult === 'ok';
$syncError  = $syncResult === 'error';

// Описание статуса проверки
$statusMap = [
    'N'     => ['Связь не проверялась', '#f39c12'],
    'OK'    => ['Связь установлена', '#2ecc71'],
    'ERROR' => ['Связь не установлена', '#e74c3c'],
];
$statusInfo = $statusMap[$checkStatus] ?? $statusMap['N'];

// Если связь установлена — поля блокируются, доступен режим "Изменить"
$locked = ($checkStatus === 'OK');
?>
        <div class="p-main">
            <div class="p-section">
                <div class="p-section__header">
                    <h2 class="p-section__title">Настройка синхронизации с iiko</h2>
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

                <?php if ($syncOk): ?>
                    <div class="settings-saved">✓ Синхронизация завершена успешно</div>
                <?php endif; ?>

                <?php if ($syncError): ?>
                    <div style="background:rgba(231,76,60,.12); color:#e74c3c; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
                        ✗ Ошибка проверки данных или синхронизации. Подробности в журнале (addMessage2Log).
                    </div>
                <?php endif; ?>

                <?php if (!$moduleLoaded): ?>
                    <div style="background:rgba(231,76,60,.12); color:#e74c3c; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
                        Модуль ldo.iiko не подключён.
                    </div>
                <?php endif; ?>

                <!-- Форма настроек (все поля обязательны) -->
                <form id="iiko-settings-form" method="POST" action="" onsubmit="return saveIikoSettings(event)">
                    <input type="hidden" name="save_settings" value="Y">

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

                <!-- Синхронизация -->
                <div style="margin-top:32px; padding-top:24px; border-top:1px solid rgba(255,255,255,.08);">
                    <div class="p-section__header" style="margin-bottom:12px;">
                        <h2 class="p-section__title" style="font-size:18px;">Синхронизация меню</h2>
                    </div>
                    <p style="font-size:14px; color:var(--color-muted); margin:0 0 16px;">
                        Загружает разделы и товары из iiko и сохраняет их в инфоблок.
                    </p>

                    <button type="button" id="iiko-sync-btn" class="p-btn p-btn--primary" style="padding:10px 24px; font-size:14px;" onclick="syncIikoMenu()">Синхронизировать</button>

                    <div id="iiko-sync-progress-wrap" style="display:none; margin-top:16px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--color-muted); margin-bottom:6px;">
                            <span id="iiko-sync-progress-label">Синхронизация...</span>
                            <span id="iiko-sync-progress-percent">0%</span>
                        </div>
                        <div style="height:10px; background:rgba(255,255,255,.1); border-radius:5px; overflow:hidden;">
                            <div id="iiko-sync-progress-bar" style="height:100%; width:0%; background:#2ecc71; transition:width .3s;"></div>
                        </div>
                    </div>

                    <div id="iiko-sync-message" style="display:none; padding:12px 16px; border-radius:8px; margin-top:16px;"></div>
                </div>
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

function showSyncMsg(text, ok) {
    var msg = document.getElementById('iiko-sync-message');
    msg.style.display = 'block';
    msg.style.background = ok ? 'rgba(46,204,113,.12)' : 'rgba(231,76,60,.12)';
    msg.style.color = ok ? '#2ecc71' : '#e74c3c';
    msg.innerHTML = (ok ? '✓ ' : '✗ ') + text;
}

function syncIikoMenu() {
    var btn   = document.getElementById('iiko-sync-btn');
    var wrap  = document.getElementById('iiko-sync-progress-wrap');
    var bar   = document.getElementById('iiko-sync-progress-bar');
    var pct   = document.getElementById('iiko-sync-progress-percent');
    var label = document.getElementById('iiko-sync-progress-label');
    var msg   = document.getElementById('iiko-sync-message');

    if (!btn || btn.disabled) return;

    btn.disabled = true;
    btn.textContent = 'Синхронизация...';

    msg.style.display = 'none';
    wrap.style.display = 'block';
    bar.style.width = '0%';
    pct.textContent = '0%';
    label.textContent = 'Синхронизация...';

    var token = 'iiko' + Date.now() + Math.random().toString(16).substr(2);

    // Опрос прогресса синхронизации (progress.php не грузит ядро, сессию не блокирует)
    var timer = setInterval(function () {
        $.ajax({
            url: '/partners/menu/progress.php?token=' + encodeURIComponent(token),
            method: 'GET',
            dataType: 'json',
            success: function (res) {
                var percent = res && typeof res.percent !== 'undefined' ? parseInt(res.percent, 10) : 0;
                if (isNaN(percent)) percent = 0;
                if (percent > 100) percent = 100;
                bar.style.width = percent + '%';
                pct.textContent = percent + '%';
            }
        });
    }, 1000);

    // Запуск синхронизации
    $.ajax({
        url: '/partners/menu/sync.php',
        method: 'POST',
        data: {
            sync: 'Y',
            token: token,
            sessid: '<?= bitrix_sessid() ?>'
        },
        dataType: 'json',
        success: function (res) {
            clearInterval(timer);
            btn.disabled = false;
            btn.textContent = 'Синхронизировать';
            bar.style.width = '100%';
            pct.textContent = '100%';

            if (res && res.status === 'success') {
                var d = res.data || {};
                label.textContent = 'Синхронизация завершена';
                showSyncMsg('Синхронизация завершена: разделов — ' + (d.sections || 0) + ', товаров — ' + (d.items || 0), true);
            } else {
                label.textContent = 'Ошибка синхронизации';
                var err = 'Ошибка синхронизации.';
                if (res && res.errors && res.errors.length) err = res.errors[0].message;
                showSyncMsg(err, false);
            }
        },
        error: function () {
            clearInterval(timer);
            btn.disabled = false;
            btn.textContent = 'Синхронизировать';
            label.textContent = 'Ошибка синхронизации';
            showSyncMsg('Ошибка запроса к серверу.', false);
        }
    });
}
</script>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
