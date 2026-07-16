<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;
use Bitrix\Main\Page\Asset;

global $USER;

$APPLICATION->SetTitle("Партнёрский дашборд");

// Получаем объект запроса через контекст Bitrix (вместо прямого $_GET/$_POST)
$request = Context::getCurrent()->getRequest();

// Подключаем стили дашборда
Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/partners.css");
Asset::getInstance()->addCss("/partners/style.css");
// Подключаем базовые стили и скрипты шаблона
Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . "/assets/css/fonts/fonts.css");
Asset::getInstance()->addJs(SITE_TEMPLATE_PATH. '/assets/js/jquery.min.js');
Asset::getInstance()->addJs(SITE_TEMPLATE_PATH. '/assets/js/main.js');
Asset::getInstance()->addJs("/partners/script.js");

// --- Выход (через GET-параметр) ---
if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

// --- Обработка формы входа ---
$authError = '';
if ($request->isPost() && $request->getPost('AUTH_ACTION') === 'partner_login') {
    $login = trim((string)$request->getPost('LOGIN'));
    $password = trim((string)$request->getPost('PASSWORD'));

    if ($login === '' || $password === '') {
        $authError = 'Введите логин и пароль.';
    } else {
        $result = $USER->Login($login, $password);
        if ($result === true || (is_object($result) && $result->isSuccess())) {
            // Успешно — релоад
            LocalRedirect('/partners/');
        } else {
            if (is_object($result)) {
                $authError = implode('<br>', $result->getErrorMessages());
            } else {
                $authError = 'Неверный логин или пароль.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<?$APPLICATION->ShowHead();?>
<meta name="robots" content="noindex, nofollow" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="format-detection" content="telephone=no" />
<link rel="icon" href="/favicon.webp" >
<title><?$APPLICATION->ShowTitle()?></title>
</head>
<body>
<?$APPLICATION->ShowPanel()?>

<?if (!$USER->IsAuthorized()):?>

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

        <?if ($authError):?>
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
                    <input type="text" id="login" name="LOGIN" class="p-login__input" placeholder="Ваш логин" value="<?=htmlspecialcharsbx($request->getPost('LOGIN'))?>" autocomplete="username" autofocus>
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



<?else:?>

<!-- ========== ДАШБОРД ========== -->
<div class="partners-page">

    <!-- Оверлей для мобильного меню -->
    <div class="p-overlay" id="p-overlay" onclick="togglePartnersMenu()"></div>

    <!-- Левое меню -->
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
                    <a href="/partners/" class="p-sidebar__menu-link active">
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
                    <a href="/partners/menu/" class="p-sidebar__menu-link">
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

        <!-- Нижняя часть с пользователем -->
        <div class="p-sidebar__bottom">
            <div class="p-sidebar__user">
                <div class="p-sidebar__user-avatar">Р</div>
                <div>
                    <div class="p-sidebar__user-name">Ресторан</div>
                    <div style="font-size:13px; color:var(--color-muted);">ул. Ленина, 1</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Основной контент -->
    <main class="p-content">

        <!-- Верхняя панель -->
        <div class="p-header">
            <div style="display:flex; align-items:center;">
                <button class="p-mobile-toggle" onclick="togglePartnersMenu()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 18H21V16H3V18ZM3 13H21V11H3V13ZM3 6V8H21V6H3Z" fill="white"/>
                    </svg>
                </button>
                <h1 class="p-header__title">Партнёрский дашборд</h1>
            </div>
            <div class="p-header__actions">
                <button class="p-header__action-btn" title="Уведомления">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 22C13.1 22 14 21.1 14 20H10C10 21.1 10.9 22 12 22ZM18 16V11C18 7.93 16.36 5.36 13.5 4.68V4C13.5 3.17 12.83 2.5 12 2.5C11.17 2.5 10.5 3.17 10.5 4V4.68C7.64 5.36 6 7.92 6 11V16L4 18V19H20V18L18 16Z" fill="white"/>
                    </svg>
                </button>
                <button class="p-header__action-btn" title="Выйти" onclick="document.location='/partners/?logout=yes'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17 7L15.59 8.41L18.17 11H8V13H18.17L15.59 15.58L17 17L22 12L17 7ZM4 5H12V3H4C2.9 3 2 3.9 2 5V19C2 20.1 2.9 21 4 21H12V19H4V5Z" fill="white"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Тело дашборда -->
        <div class="p-main">

            <!-- Статистика -->
            <div class="p-stats">
                <div class="p-stat-card">
                    <div class="p-stat-card__label">Общий доход</div>
                    <div class="p-stat-card__value">₽ 1 284 500</div>
                    <div class="p-stat-card__change p-stat-card__change--up">↑ +12.5%</div>
                </div>
                <div class="p-stat-card">
                    <div class="p-stat-card__label">Заказов сегодня</div>
                    <div class="p-stat-card__value">147</div>
                    <div class="p-stat-card__change p-stat-card__change--up">↑ +8.3%</div>
                </div>
                <div class="p-stat-card">
                    <div class="p-stat-card__label">Средний чек</div>
                    <div class="p-stat-card__value">₽ 1 850</div>
                    <div class="p-stat-card__change p-stat-card__change--up">↑ +3.2%</div>
                </div>
                <div class="p-stat-card">
                    <div class="p-stat-card__label">Новых клиентов</div>
                    <div class="p-stat-card__value">38</div>
                    <div class="p-stat-card__change p-stat-card__change--down">↓ -2.1%</div>
                </div>
            </div>

            <!-- Последние заказы -->
            <div class="p-section">
                <div class="p-section__header">
                    <h2 class="p-section__title">Последние заказы</h2>
                    <a href="/partners/orders/" class="p-section__link">Все заказы →</a>
                </div>
                <table class="p-table">
                    <thead>
                        <tr>
                            <th>№ заказа</th>
                            <th>Клиент</th>
                            <th>Сумма</th>
                            <th>Статус</th>
                            <th>Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>#4582</td>
                            <td>Иван Петров</td>
                            <td>₽ 2 340</td>
                            <td><span class="p-status p-status--active">Выполнен</span></td>
                            <td>13.07.2026</td>
                        </tr>
                        <tr>
                            <td>#4581</td>
                            <td>Анна Смирнова</td>
                            <td>₽ 1 560</td>
                            <td><span class="p-status p-status--active">Выполнен</span></td>
                            <td>13.07.2026</td>
                        </tr>
                        <tr>
                            <td>#4580</td>
                            <td>Сергей Козлов</td>
                            <td>₽ 3 780</td>
                            <td><span class="p-status p-status--pending">Готовится</span></td>
                            <td>13.07.2026</td>
                        </tr>
                        <tr>
                            <td>#4579</td>
                            <td>Елена Новикова</td>
                            <td>₽ 890</td>
                            <td><span class="p-status p-status--inactive">Отменён</span></td>
                            <td>12.07.2026</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Акционные предложения -->
            <div class="p-section">
                <div class="p-section__header">
                    <h2 class="p-section__title">Активные акции</h2>
                    <a href="/partners/menu/" class="p-section__link">Управлять →</a>
                </div>
                <div class="p-grid">
                    <div class="p-card">
                        <h3 class="p-card__title">Скидка 20% на роллы</h3>
                        <p class="p-card__text">На все роллы в меню при заказе от 1500₽</p>
                        <div class="p-card__footer">
                            <span style="color:var(--color-muted); font-size:13px;">до 20.07.2026</span>
                            <span class="p-status p-status--active">Активно</span>
                        </div>
                    </div>
                    <div class="p-card">
                        <h3 class="p-card__title">Комбо-обед</h3>
                        <p class="p-card__text">Суши + напиток в подарок при заказе от 2000₽</p>
                        <div class="p-card__footer">
                            <span style="color:var(--color-muted); font-size:13px;">до 31.07.2026</span>
                            <span class="p-status p-status--active">Активно</span>
                        </div>
                    </div>
                    <div class="p-card">
                        <h3 class="p-card__title">День рождения</h3>
                        <p class="p-card__text">Скидка 30% именинникам при предъявлении паспорта</p>
                        <div class="p-card__footer">
                            <span style="color:var(--color-muted); font-size:13px;">постоянно</span>
                            <span class="p-status p-status--active">Активно</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<?endif;?>



<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog.php");
?>
</body>
</html>
