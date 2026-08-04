<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;
use Bitrix\Main\UI\Extension;

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

    // ============================================================
    // Данные дашборда (Model) — передаются в Vue-приложение через rootProps.
    // TODO: заменить хардкод на выборку из БД (заказы/финансы/статистика).
    // ============================================================
    $dashboardData = [
        'period' => 'week',
        'cards' => [
            ['id' => 'revenue',   'label' => 'Общий доход',       'value' => '1 284 500', 'currency' => '₽', 'change' => '+12.5%', 'changeType' => 'up'],
            ['id' => 'orders',    'label' => 'Заказов сегодня',   'value' => '147',       'currency' => '',  'change' => '+8.3%',  'changeType' => 'up'],
            ['id' => 'avg',       'label' => 'Средний чек',       'value' => '1 850',     'currency' => '₽', 'change' => '+3.2%',  'changeType' => 'up'],
            ['id' => 'newclients','label' => 'Новых клиентов',    'value' => '38',        'currency' => '',  'change' => '-2.1%',  'changeType' => 'down'],
        ],
        'chart' => [
            'labels' => ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
            'series' => [
                ['name' => 'Продажи', 'color' => '#2ecc71', 'values' => [120, 190, 150, 260, 320, 410, 380]],
                ['name' => 'Заказы',  'color' => '#3498db', 'values' => [80, 120, 95, 170, 210, 260, 240]],
            ],
        ],
        'table' => [
            'columns' => [
                ['key' => 'number', 'title' => '№ заказа', 'sortable' => true],
                ['key' => 'client', 'title' => 'Клиент'],
                ['key' => 'amount', 'title' => 'Сумма', 'align' => 'right', 'sortable' => true],
                ['key' => 'status', 'title' => 'Статус'],
                ['key' => 'date',   'title' => 'Дата', 'sortable' => true],
            ],
            'rows' => [
                ['id' => 1, 'number' => '#4582', 'client' => 'Иван Петров',    'amount' => '2 340 ₽', 'status' => 'Выполнен', 'date' => '13.07.2026'],
                ['id' => 2, 'number' => '#4581', 'client' => 'Анна Смирнова',   'amount' => '1 560 ₽', 'status' => 'Выполнен', 'date' => '13.07.2026'],
                ['id' => 3, 'number' => '#4580', 'client' => 'Сергей Козлов',   'amount' => '3 780 ₽', 'status' => 'Готовится', 'date' => '13.07.2026'],
                ['id' => 4, 'number' => '#4579', 'client' => 'Елена Новикова',  'amount' => '890 ₽',   'status' => 'Отменён',   'date' => '12.07.2026'],
            ],
        ],
    ];

    // Подключаем расширение BitrixVue 3 (ДО include header.php)
    Extension::load("ldo.vue-app");

    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
    ?>
        <div class="p-main">
            <!-- Vue-приложение дашборда (BitrixVue 3, MVVM) -->
            <div id="partners-vue-dashboard"></div>
        </div>

        <script>
            BX.ready(function () {
                var app = new BX.LDO.VueApp.PartnersApplication('#partners-vue-dashboard', {
                    dashboard: <?= \Bitrix\Main\Web\Json::encode($dashboardData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
                });
                app.start();
            });
        </script>

    <?
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
}
?>
