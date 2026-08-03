<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Ldo\Iiko\SettingsTable;

global $USER;

$APPLICATION->SetTitle("Синхронизация меню");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Синхронизация меню");

$request = Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

$partnersActivePage  = 'menu';
$partnersPageTitle   = 'Синхронизация меню';
$partnersHeaderStyle = 'padding-bottom:0; border-bottom:none;';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

// --- Данные страницы (после шапки) ---
$siteId = 's1';
$moduleLoaded = Loader::includeModule('ldo.iiko');

// Текущий статус проверки подключения (одна строка на сайт)
$row = ($moduleLoaded) ? SettingsTable::getRow($siteId) : null;
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
?>

        <!-- Подменю меню -->
        <div class="p-dash-tabs">
            <a class="tab-btn active" data-tab="sync" href="/partners/menu/">Синхронизация</a>
            <a class="tab-btn" data-tab="tovary" href="/partners/menu/tovary/">Настройка товаров</a>
            <a class="tab-btn" data-tab="razdely" href="/partners/menu/razdely/">Настройка разделов</a>
        </div>
<?$APPLICATION->IncludeComponent(
        "bitrix:menu",
        "",
        Array(
                "ALLOW_MULTI_SELECT" => "N",
                "CHILD_MENU_TYPE" => "left",
                "DELAY" => "N",
                "MAX_LEVEL" => "1",
                "MENU_CACHE_GET_VARS" => array(""),
                "MENU_CACHE_TIME" => "3600",
                "MENU_CACHE_TYPE" => "N",
                "MENU_CACHE_USE_GROUPS" => "Y",
                "ROOT_MENU_TYPE" => "personallevel",
                "USE_EXT" => "N"
        )
);?>

        <div class="p-main">
            <div class="p-section">
                <div class="p-section__header">
                    <h2 class="p-section__title">Синхронизация меню</h2>
                    <span style="font-size:13px; color:var(--color-muted);">Сайт: <?= htmlspecialchars($siteId) ?></span>
                </div>

                <!-- Статус проверки подключения -->
                <div style="display:flex; align-items:center; gap:8px; padding:12px 16px; margin-bottom:16px; background:rgba(255,255,255,.04); border-radius:8px; font-size:14px;">
                    <span style="width:10px; height:10px; border-radius:50%; background:<?= $statusInfo[1] ?>; flex-shrink:0;"></span>
                    <span>Статус: <b><?= htmlspecialchars($statusInfo[0]) ?></b></span>
                    <?php if ($checkDate): ?>
                        <span style="color:var(--color-muted);">· <?= htmlspecialchars($checkDate->format('d.m.Y H:i')) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Сообщения -->
                <div id="iiko-sync-message" style="display:none; padding:12px 16px; border-radius:8px; margin-bottom:16px;"></div>

                <?php if ($syncOk): ?>
                    <div class="settings-saved">✓ Синхронизация завершена успешно</div>
                <?php endif; ?>

                <?php if ($syncError): ?>
                    <div style="background:rgba(231,76,60,.12); color:#e74c3c; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
                        ✗ Ошибка синхронизации. Подробности в журнале (addMessage2Log).
                    </div>
                <?php endif; ?>

                <?php if (!$moduleLoaded): ?>
                    <div style="background:rgba(231,76,60,.12); color:#e74c3c; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
                        Модуль ldo.iiko не подключён.
                    </div>
                <?php endif; ?>



                <?php if ($checkStatus === 'OK'): ?>
                    <button type="button" id="iiko-sync-btn" class="p-btn p-btn--primary" style="padding:10px 24px; font-size:14px;" onclick="syncIikoMenu()">Синхронизировать</button>
                <?php else: ?>
                    <button type="button" id="iiko-sync-btn" class="p-btn p-btn--primary" style="padding:10px 24px; font-size:14px; background:#6c757d; border-color:#6c757d; cursor:not-allowed;" disabled onclick="return false;">Синхронизировать</button>
                    <p style="font-size:14px; color:var(--color-muted); margin:12px 0 0;">
                        Для синхронизации необходимо ввести данные для подключения к iiko.
                        Укажите их в <a href="/partners/settings/" style="color:var(--bg-button);">настройках</a>.
                    </p>
                <?php endif; ?>

                <div id="iiko-sync-progress-wrap" style="display:none; margin-top:16px;">
                    <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--color-muted); margin-bottom:6px;">
                        <span id="iiko-sync-progress-label">Синхронизация...</span>
                        <span id="iiko-sync-progress-percent">0%</span>
                    </div>
                    <div style="height:10px; background:rgba(255,255,255,.1); border-radius:5px; overflow:hidden;">
                        <div id="iiko-sync-progress-bar" style="height:100%; width:0%; background:#2ecc71; transition:width .3s;"></div>
                    </div>
                </div>
            </div>
        </div>

<script>
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
