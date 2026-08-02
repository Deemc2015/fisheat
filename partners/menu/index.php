<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Page\Asset;
use Ldo\Iiko\SettingsTable;

global $USER;

$APPLICATION->SetTitle("Настройка синхронизации с iiko");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Настройка синхронизации с iiko");

Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/partners.css");
Asset::getInstance()->addCss("/partners/style.css");
// Подключаем базовые стили и скрипты шаблона
Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/fonts/fonts.css");
Asset::getInstance()->addJs(SITE_TEMPLATE_PATH. '/assets/js/jquery.min.js');
Asset::getInstance()->addJs(SITE_TEMPLATE_PATH. '/assets/js/main.js');
Asset::getInstance()->addJs("/partners/script.js");

$request = Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

$siteId = 's1';
$moduleLoaded = Loader::includeModule('ldo.iiko');

// Текущие настройки и статус проверки (одна строка на сайт)
$row = ($moduleLoaded) ? SettingsTable::getRow($siteId) : null;
$apiLogin    = $row['API_LOGIN'] ?? '';
$secret      = $row['SECRET'] ?? '';
$appId       = $row['APP_ID'] ?? '';
$checkStatus = $row['CHECK_STATUS'] ?? 'N';
$checkDate   = $row['CHECK_DATE'] ?? null;

// --- Запуск синхронизации (AJAX POST, JSON-ответ) ---
if ($request->isPost() && $request->getPost('sync') === 'Y' && $moduleLoaded) {
    set_time_limit(0);

    $jsonResponse = function ($payload) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    };

    if (!$USER->IsAuthorized()) {
        $jsonResponse(['status' => 'error', 'errors' => [['message' => 'Требуется авторизация.']]]);
    }

    if (!check_bitrix_sessid()) {
        $jsonResponse(['status' => 'error', 'errors' => [['message' => 'Сессия истекла, обновите страницу.']]]);
    }

    $progressToken = (string)$request->getPost('token');

    try {
        // Освобождаем сессию, чтобы параллельный опрос прогресса не блокировался
        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        $product = new \Ldo\Iiko\Product($siteId);
        $result = $product->sync($progressToken);

        if ((int)$result['sections'] === 0 && (int)$result['items'] === 0) {
            SettingsTable::setStatus($siteId, 'ERROR');
            $jsonResponse([
                'status' => 'error',
                'errors' => [['message' => 'Не удалось получить данные от iiko — проверьте настройки подключения.']],
            ]);
        }

        SettingsTable::setStatus($siteId, 'OK');
        $jsonResponse([
            'status' => 'success',
            'data' => [
                'sections' => (int)$result['sections'],
                'items' => (int)$result['items'],
            ],
        ]);
    } catch (\Exception $e) {
        SettingsTable::setStatus($siteId, 'ERROR');
        addMessage2Log('Partners menu sync error: ' . $e->getMessage());
        $jsonResponse([
            'status' => 'error',
            'errors' => [['message' => 'Ошибка синхронизации: ' . $e->getMessage()]],
        ]);
    }
}

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
<!DOCTYPE html>
<html>
<head>
<?$APPLICATION->ShowHead();?>
<meta name="robots" content="noindex, nofollow" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="format-detection" content="telephone=no" />
<link rel="icon" href="/favicon.webp" >
<title><?$APPLICATION->ShowTitle()?></title></head>
<body>
<?$APPLICATION->ShowPanel()?>

<?if (!$USER->IsAuthorized()):?>
    <script>document.location.href = '/partners/';</script>
<?else:?>

<div class="partners-page">
    <div class="p-overlay" id="p-overlay" onclick="togglePartnersMenu()"></div>

    <aside class="p-sidebar" id="p-sidebar">

        <!-- Логотип -->
        <div class="p-sidebar__logo">
            <a href="/partners/">
                <img src="<?=SITE_TEMPLATE_PATH?>/assets/images/logo.svg" alt="Рыба закусывала">
            </a>
        </div>

        <!-- Навигация -->
        <nav class="p-sidebar__nav">
            <ul class="p-sidebar__menu">
                <li class="p-sidebar__menu-item">
                    <a href="/partners/" class="p-sidebar__menu-link">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 13H11V3H3V13ZM3 21H11V15H3V21ZM13 21H21V11H13V21ZM13 3V9H21V3H13Z" fill="white"/>
                        </svg>
                        <span>Обзор</span>
                    </a>
                </li>
                <li class="p-sidebar__menu-item">
                    <a href="/partners/delivery-zones/" class="p-sidebar__menu-link">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C8.13 2 5 5.13 5 9C5 14.25 12 22 12 22C12 22 19 14.25 19 9C19 5.13 15.87 2 12 2ZM12 11.5C10.62 11.5 9.5 10.38 9.5 9C9.5 7.62 10.62 6.5 12 6.5C13.38 6.5 14.5 7.62 14.5 9C14.5 10.38 13.38 11.5 12 11.5Z" fill="white"/>
                        </svg>
                        <span>Зоны доставки</span>
                    </a>
                </li>
                <li class="p-sidebar__menu-item">
                    <a href="/partners/statistics/" class="p-sidebar__menu-link">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 9.2H8V19H5V9.2ZM10.6 5H13.4V19H10.6V5ZM16.2 11.4H19V19H16.2V11.4Z" fill="white"/>
                        </svg>
                        <span>Статистика</span>
                    </a>
                </li>
                <li class="p-sidebar__menu-item">
                    <a href="/partners/orders/" class="p-sidebar__menu-link">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM19 19H5V5H19V19ZM17 17H7V15H17V17ZM17 13H7V11H17V13ZM17 9H7V7H17V9Z" fill="white"/>
                        </svg>
                        <span>Заказы</span>
                    </a>
                </li>
                <li class="p-sidebar__menu-item">
                    <a href="/partners/finance/" class="p-sidebar__menu-link">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M11.8 10.9C9.53 10.31 8.8 9.7 8.8 8.75C8.8 7.66 9.81 6.9 11.5 6.9C13.28 6.9 13.94 7.75 14 9H16.21C16.14 7.28 15.09 5.7 13 5.19V3H10V5.16C8.06 5.58 6.5 6.84 6.5 8.77C6.5 11.08 8.41 12.23 11.2 12.9C13.7 13.5 14.2 14.38 14.2 15.31C14.2 16 13.71 17.1 11.5 17.1C9.44 17.1 8.63 16.18 8.52 15H6.32C6.44 17.19 8.08 18.42 10 18.83V21H13V18.85C14.95 18.48 16.5 17.35 16.5 15.3C16.5 12.46 14.07 11.49 11.8 10.9Z" fill="white"/>
                        </svg>
                        <span>Финансы</span>
                    </a>
                </li>
                <li class="p-sidebar__menu-item">
                    <a href="/partners/menu/" class="p-sidebar__menu-link active">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20ZM13 7H11V13H17V11H13V7Z" fill="white"/>
                        </svg>
                        <span>Меню</span>
                    </a>
                </li>
                <li class="p-sidebar__menu-item">
                    <a href="/partners/reports/" class="p-sidebar__menu-link">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM19 19H5V5H19V19ZM17 15H13V17H17V15ZM9 13H7V17H9V13ZM17 9H13V11H17V9ZM9 7H7V11H9V7Z" fill="white"/>
                        </svg>
                        <span>Отчёты</span>
                    </a>
                </li>
                <li class="p-sidebar__menu-item">
                    <a href="/partners/settings/" class="p-sidebar__menu-link">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19.14 12.94C19.18 12.64 19.2 12.33 19.2 12C19.2 11.68 19.18 11.36 19.13 11.06L21.16 9.48C21.34 9.34 21.39 9.07 21.28 8.87L19.36 5.55C19.24 5.33 18.99 5.26 18.77 5.33L16.38 6.29C15.88 5.91 15.35 5.59 14.76 5.35L14.4 2.81C14.36 2.57 14.16 2.4 13.91 2.4H10.09C9.84 2.4 9.64 2.57 9.6 2.81L9.24 5.35C8.65 5.59 8.12 5.92 7.62 6.29L5.23 5.33C5.01 5.25 4.76 5.33 4.64 5.55L2.72 8.87C2.61 9.08 2.66 9.34 2.84 9.48L4.87 11.06C4.82 11.36 4.8 11.69 4.8 12C4.8 12.31 4.82 12.64 4.87 12.94L2.84 14.52C2.66 14.66 2.61 14.93 2.72 15.13L4.64 18.45C4.76 18.67 5.01 18.74 5.23 18.67L7.62 17.71C8.12 18.09 8.65 18.41 9.24 18.65L9.6 21.19C9.65 21.43 9.84 21.6 10.09 21.6H13.91C14.16 21.6 14.36 21.43 14.4 21.19L14.76 18.65C15.35 18.41 15.88 18.09 16.38 17.71L18.77 18.67C19 18.75 19.25 18.67 19.36 18.45L21.28 15.13C21.39 14.91 21.34 14.66 21.16 14.52L19.14 12.94ZM12 15.6C10.02 15.6 8.4 13.98 8.4 12C8.4 10.02 10.02 8.4 12 8.4C13.98 8.4 15.6 10.02 15.6 12C15.6 13.98 13.98 15.6 12 15.6Z" fill="white"/>
                        </svg>
                        <span>Настройки</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <main class="p-content">
        <div class="p-header" style="padding-bottom:0; border-bottom:none;">
            <div style="display:flex; align-items:center;">
                <button class="p-mobile-toggle" onclick="togglePartnersMenu()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M3 18H21V16H3V18ZM3 13H21V11H3V13ZM3 6V8H21V6H3Z" fill="white"/></svg>
                </button>
                <h1 class="p-header__title">Настройка синхронизации с iiko</h1>
            </div>
            <div class="p-header__actions">
                <button class="p-header__action-btn" title="Выйти" onclick="document.location='/partners/menu/?logout=yes'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M17 7L15.59 8.41L18.17 11H8V13H18.17L15.59 15.58L17 17L22 12L17 7ZM4 5H12V3H4C2.9 3 2 3.9 2 5V19C2 20.1 2.9 21 4 21H12V19H4V5Z" fill="white"/></svg>
                </button>
            </div>
        </div>

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
    </main>
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
        url: '/partners/menu/',
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

<?endif;?>
</body>
</html>
