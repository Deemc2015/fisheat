<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;

global $USER;

$APPLICATION->SetTitle("Партнёрский дашборд");

// Получаем объект запроса через контекст Bitrix
$request = Context::getCurrent()->getRequest();

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

// --- Страница входа (форма в отдельном файле) ---
if (!$USER->IsAuthorized()) {
    include $_SERVER['DOCUMENT_ROOT'] . '/partners/login.php';
} else {
    // --- Дашборд: шапка и футер из шаблона partners ---
    $partnersActivePage = 'overview';
    $partnersPageTitle  = 'Партнёрский дашборд';

    $partnersSidebarBottom = '<div class="p-sidebar__bottom">'
        . '<div class="p-sidebar__user">'
        . '<div class="p-sidebar__user-avatar">Р</div>'
        . '<div>'
        . '<div class="p-sidebar__user-name">Ресторан</div>'
        . '<div style="font-size:13px; color:var(--color-muted);">ул. Ленина, 1</div>'
        . '</div>'
        . '</div>'
        . '</div>';

    $partnersHeaderActions ='<button class="p-header__action-btn" title="Выйти" onclick="document.location=\'/partners/?logout=yes\'">'
        . '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17 7L15.59 8.41L18.17 11H8V13H18.17L15.59 15.58L17 17L22 12L17 7ZM4 5H12V3H4C2.9 3 2 3.9 2 5V19C2 20.1 2.9 21 4 21H12V19H4V5Z" fill="white"/></svg>'
        . '</button>';

    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
    ?>
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
        </div>

    <?
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
}
?>
