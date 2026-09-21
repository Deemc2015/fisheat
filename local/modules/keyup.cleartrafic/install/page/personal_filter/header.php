<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var CMain $APPLICATION */
/** @var string $pageTitle Заголовок страницы (устанавливается до include) */
/** @var bool $permission Доступ администратора модуля */

$curDir = $APPLICATION->GetCurDir();

if (!function_exists('keyupActiveMenu')) {
    function keyupActiveMenu($href, $curDir) {
        return rtrim((string)$curDir, '/') === rtrim((string)$href, '/') ? 'active' : '';
    }
}

/* Понятные подзаголовки для каждой страницы панели */
$keyupSubtitles = array(
    '/personal_filter/'              => 'Все посещения сайта: IP, страница входа, реферер и сработавшее правило фильтрации.',
    '/personal_filter/gray_list/'    => 'Посетители, помеченные как подозрительные. Им показывается капча.',
    '/personal_filter/black_list/'   => 'Заблокированные адреса: доступ к сайту немедленно прекращается.',
    '/personal_filter/maska_list/'   => 'Посещения, попавшие под одну из настроенных масок подсети.',
    '/personal_filter/referer_list/' => 'Посещения, определённые как бот по источнику перехода (HTTP_REFERER).',
    '/personal_filter/useragent_list/' => 'Посещения, у которых User-Agent совпал с одним из правил.',
    '/personal_filter/black/'        => 'Управление чёрным списком IP-адресов: добавление, изменение и удаление.',
    '/personal_filter/gray/'         => 'Управление серым списком IP-адресов: адресам из списка показывается капча.',
    '/personal_filter/mask/'         => 'Управление масками подсетей и импортом готовых списков из файла.',
    '/personal_filter/referer/'      => 'Управление списком разрешённых источников перехода.',
    '/personal_filter/useragent/'    => 'Управление правилами User-Agent: совпавшие посетители обрабатываются как серый список.',
    '/personal_filter/config/'       => 'Настройки фильтрации: время показа капчи и служебные параметры.',
    '/personal_filter/email/'        => 'Адреса, на которые приходят уведомления о событиях фильтра.',
);

$pageSubtitle = isset($keyupSubtitles[$curDir])
    ? $keyupSubtitles[$curDir]
    : 'Панель управления модулем фильтрации трафика.';

$pageTitleSafe = (isset($pageTitle) && (string)$pageTitle !== '') ? (string)$pageTitle : 'Панель управления';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="format-detection" content="telephone=no">
    <title><?= htmlspecialcharsbx($pageTitleSafe) ?> — Фильтрация трафика</title>
    <link href="/local/components/bitrix/highloadblock.list/templates/.default/bootstrap-grid.min.css" type="text/css" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://code.jquery.com/ui/1.13.3/themes/base/jquery-ui.css" type="text/css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Sofia+Sans:wght@600&display=swap" rel="stylesheet" />
    <link href="/personal_filter/style.css" type="text/css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script>
        /* Сессионный идентификатор для AJAX-запросов панели */
        window.PF_SESSID = '<?= bitrix_sessid() ?>';
    </script>
</head>
<body class="pf-admin">
<div class="pf-topbar">
    <div class="container pf-container">
        <div class="pf-topbar__inner">
            <div class="pf-brand">
                <span class="pf-brand__logo" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3l7 3v6c0 4.5-3 7.6-7 9-4-1.4-7-4.5-7-9V6l7-3z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="pf-brand__text">
                    <strong>Фильтрация трафика</strong>
                    <small>Панель управления модулем</small>
                </span>
            </div>
            <div class="pf-topbar__meta">
                <span class="pf-clock" id="time">--:--:--</span>
            </div>
        </div>
    </div>
</div>

<div class="container pf-container pf-layout">
    <div class="row">
        <div class="col-lg-2">
            <aside class="menu-block pf-sidebar">
                <div class="pf-nav__title">Аналитика</div>
                <a href="/personal_filter/" class="<?= keyupActiveMenu('/personal_filter/', $curDir) ?>">Общая сводка</a>
                <a href="/personal_filter/gray_list/" class="<?= keyupActiveMenu('/personal_filter/gray_list/', $curDir) ?>">Серый список</a>
                <a href="/personal_filter/black_list/" class="<?= keyupActiveMenu('/personal_filter/black_list/', $curDir) ?>">Чёрный список</a>
                <a href="/personal_filter/maska_list/" class="<?= keyupActiveMenu('/personal_filter/maska_list/', $curDir) ?>">Список по маске</a>
                <a href="/personal_filter/referer_list/" class="<?= keyupActiveMenu('/personal_filter/referer_list/', $curDir) ?>">Список по рефереру</a>
                <a href="/personal_filter/useragent_list/" class="<?= keyupActiveMenu('/personal_filter/useragent_list/', $curDir) ?>">Список по User-Agent</a>

                <div class="pf-nav__title">Управление фильтрами</div>
                <a href="/personal_filter/black/" class="<?= keyupActiveMenu('/personal_filter/black/', $curDir) ?>">Чёрный список IP</a>
                <a href="/personal_filter/gray/" class="<?= keyupActiveMenu('/personal_filter/gray/', $curDir) ?>">Серый список IP</a>
                <a href="/personal_filter/mask/" class="<?= keyupActiveMenu('/personal_filter/mask/', $curDir) ?>">Маски подсетей</a>
                <a href="/personal_filter/referer/" class="<?= keyupActiveMenu('/personal_filter/referer/', $curDir) ?>">Рефереры</a>
                <a href="/personal_filter/useragent/" class="<?= keyupActiveMenu('/personal_filter/useragent/', $curDir) ?>">User-Agent</a>

                <div class="pf-nav__title">Настройки</div>
                <a href="/personal_filter/config/" class="<?= keyupActiveMenu('/personal_filter/config/', $curDir) ?>">Параметры модуля</a>
                <?php if ($permission): ?><a href="/personal_filter/email/" class="<?= keyupActiveMenu('/personal_filter/email/', $curDir) ?>">Настройки оповещения</a><?php endif; ?>
            </aside>
        </div>
        <div class="col-lg-10">
            <div class="pf-page-header">
                <h1><?= htmlspecialcharsbx($pageTitleSafe) ?></h1>
                <p><?= htmlspecialcharsbx($pageSubtitle) ?></p>
            </div>
