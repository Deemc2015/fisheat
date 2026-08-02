<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();?>
<?
/**
 * Страница входа в раздел /partners/.
 * Подключается из /partners/index.php, когда пользователь не авторизован.
 * Ожидает переменную $authError (текст ошибки, опционально).
 */
$authError = isset($authError) ? (string)$authError : '';

// Стили и скрипты (те же, что в шаблоне partners)
\Bitrix\Main\Page\Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/fonts/fonts.css");
\Bitrix\Main\Page\Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/style.css");
\Bitrix\Main\Page\Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/partners.css");
\Bitrix\Main\Page\Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/media.css");
\Bitrix\Main\Page\Asset::getInstance()->addCss("/partners/style.css");
\Bitrix\Main\Page\Asset::getInstance()->addJs(SITE_TEMPLATE_PATH . '/assets/js/jquery.min.js');
\Bitrix\Main\Page\Asset::getInstance()->addJs(SITE_TEMPLATE_PATH . '/assets/js/main.js');
\Bitrix\Main\Page\Asset::getInstance()->addJs("/partners/script.js");
?>
<!DOCTYPE html>
<html>
<head>
<?$APPLICATION->ShowHead();?>
<meta name="robots" content="noindex, nofollow" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="format-detection" content="telephone=no" />
<link rel="icon" href="/favicon.webp" >
<title>Вход в личный кабинет</title>
</head>
<body>
<?$APPLICATION->ShowPanel()?>

<!-- ========== СТРАНИЦА ВХОДА ========== -->
<div class="p-login">
    <div class="p-login__card">
        <!-- Декоративный верхний элемент -->
        <div class="p-login__glow"></div>

        <!-- Логотип -->
        <div class="p-login__logo">
            <img src="<?=SITE_TEMPLATE_PATH?>/assets/images/logo.svg" alt="Рыба закусывала">
        </div>

        <h1 class="p-login__title">Вход в личный кабинет</h1>
        <p class="p-login__subtitle">Авторизуйтесь для доступа</p>

        <?if ($authError !== ''):?>
            <div class="p-login__error"><?=$authError?></div>
        <?endif;?>

        <form method="POST" action="" class="p-login__form">
            <input type="hidden" name="AUTH_ACTION" value="partner_login">

            <div class="p-login__field">
                <label class="p-login__label" for="login">Логин</label>
                <div class="p-login__input-wrap">
                    <svg class="p-login__icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M12 12C14.21 12 16 10.21 16 8C16 5.79 14.21 4 12 4C9.79 4 8 5.79 8 8C8 10.21 9.79 12 12 12ZM12 14C9.33 14 4 15.34 4 18V20H20V18C20 15.34 14.67 14 12 14Z" fill="currentColor"/>
                    </svg>
                    <input type="text" id="login" name="LOGIN" class="p-login__input" placeholder="Ваш логин" value="<?=htmlspecialcharsbx(isset($_POST['LOGIN']) ? $_POST['LOGIN'] : '')?>" autocomplete="username" autofocus>
                </div>
            </div>

            <div class="p-login__field">
                <label class="p-login__label" for="password">Пароль</label>
                <div class="p-login__input-wrap">
                    <svg class="p-login__icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M18 8H17V6C17 3.24 14.76 1 12 1C9.24 1 7 3.24 7 6V8H6C4.9 8 4 8.9 4 10V20C4 21.1 4.9 22 6 22H18C19.1 22 20 21.1 20 20V10C20 8.9 19.1 8 18 8ZM12 17C10.9 17 10 16.1 10 15C10 13.9 10.9 13 12 13C13.1 13 14 13.9 14 15C14 16.1 13.1 17 12 17ZM15.1 8H8.9V6C8.9 4.29 10.29 2.9 12 2.9C13.71 2.9 15.1 4.29 15.1 6V8Z" fill="currentColor"/>
                    </svg>
                    <input type="password" id="password" name="PASSWORD" class="p-login__input" placeholder="Ваш пароль" autocomplete="current-password">
                    <button type="button" class="p-login__toggle-pass" onclick="togglePassword()" tabindex="-1">
                        <svg id="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12C2.73 16.39 7 19.5 12 19.5C17 19.5 21.27 16.39 23 12C21.27 7.61 17 4.5 12 4.5ZM12 17C9.24 17 7 14.76 7 12C7 9.24 9.24 7 12 7C14.76 7 17 9.24 17 12C17 14.76 14.76 17 12 17ZM12 9C10.34 9 9 10.34 9 12C9 13.66 10.34 15 12 15C13.66 15 15 13.66 15 12C15 10.34 13.66 9 12 9Z" fill="currentColor"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="p-login__btn">
                <span>Войти в кабинет</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M17 7L15.59 8.41L18.17 11H8V13H18.17L15.59 15.58L17 17L22 12L17 7ZM4 5H12V3H4C2.9 3 2 3.9 2 5V19C2 20.1 2.9 21 4 21H12V19H4V5Z" fill="currentColor"/>
                </svg>
            </button>
        </form>
    </div>
</div>

<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog.php");
?>
</body>
</html>
