<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\Main\UserTable;
use Bitrix\Main\Web\Json;

global $USER, $DB;

$APPLICATION->SetTitle("Пользователи");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Пользователи");

$request = Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

Loader::includeModule('main');

// ============================================================
// AJAX: переключение флагов «активность» (ACTIVE) и
// «заблокирован» (BLOCKED) — штатные поля пользователя Битрикс
// ============================================================
if ($request->isPost() && $request->getPost('ajax_users') === 'Y') {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'error' => ''];

    try {
        if (!$USER->IsAuthorized()) {
            throw new \Exception('Требуется авторизация.');
        }
        if (!check_bitrix_sessid()) {
            throw new \Exception('Сессия истекла. Обновите страницу.');
        }

        $action = (string)$request->getPost('ACTION');
        $userId = (int)$request->getPost('ID');

        if ($userId <= 0) {
            throw new \Exception('Неверный идентификатор пользователя.');
        }
        if ($userId === (int)$USER->GetID()) {
            throw new \Exception('Нельзя изменять или удалять собственную учётную запись.');
        }

        if ($action === 'delete') {
            // Удаление пользователя (штатный метод с очисткой связанных данных)
            $obUser = new \CUser();
            if (!$obUser->Delete($userId)) {
                $err = trim(strip_tags((string)$obUser->LAST_ERROR));
                if ($err === '') {
                    $err = 'Не удалось удалить пользователя.';
                }
                throw new \Exception($err);
            }
            $response = ['success' => true, 'deleted' => $userId];
        } else {
            // Переключение флагов «активность» (ACTIVE) / «заблокирован» (BLOCKED)
            $field = (string)$request->getPost('FIELD');
            $value = $request->getPost('VALUE') === 'Y' ? 'Y' : 'N';

            if (!in_array($field, ['ACTIVE', 'BLOCKED'], true)) {
                throw new \Exception('Неизвестное поле.');
            }

            // Проверяем, что пользователь существует
            $dbExists = $DB->Query("SELECT ID FROM b_user WHERE ID = " . $userId);
            if (!$dbExists->Fetch()) {
                throw new \Exception('Пользователь не найден.');
            }

            // Прямое обновление штатных полей b_user (ACTIVE / BLOCKED)
            $valueSql = $DB->ForSql($value);
            $DB->Query("UPDATE b_user SET `{$field}` = '{$valueSql}' WHERE ID = " . $userId);

            $response = ['success' => true, 'value' => $value];
        }
    } catch (\Exception $e) {
        $response['error'] = $e->getMessage();
    }

    echo Json::encode($response, JSON_UNESCAPED_UNICODE);
    die();
}

$partnersActivePage  = 'polzovateli';
$partnersPageTitle   = 'Пользователи';
$partnersHeaderStyle = 'padding-bottom:0; border-bottom:none;';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

// ============================================================
// Данные: пользователи из админки (b_user)
// ============================================================

// Привязка к сайту: коды сайтов -> названия
$siteNames = [];
$rsSites = \CSite::GetList($by = 'sort', $order = 'asc');
while ($site = $rsSites->Fetch()) {
    $siteNames[$site['LID']] = $site['NAME'];
}

// Параметры фильтра: поиск (имя/телефон) и статус пользователя
$search     = trim((string)$request->getQuery('SEARCH'));
$userStatus = trim((string)$request->getQuery('USER_STATUS'));
if (!in_array($userStatus, ['active', 'inactive', 'blocked'], true)) {
    $userStatus = '';
}

$nav = new PageNavigation('users');
$nav->allowAllRecords(false)->setPageSize(50)->initFromUri();

$filter = ['!=ID' => 0];
if ($userStatus === 'active') {
    $filter['=ACTIVE']  = 'Y';
    $filter['=BLOCKED'] = 'N';
} elseif ($userStatus === 'inactive') {
    $filter['=ACTIVE'] = 'N';
} elseif ($userStatus === 'blocked') {
    $filter['=BLOCKED'] = 'Y';
}

$query = UserTable::query()
    ->setSelect([
        'ID', 'LOGIN', 'EMAIL', 'PERSONAL_PHONE', 'NAME', 'LAST_NAME', 'SECOND_NAME',
        'LID', 'DATE_REGISTER', 'LAST_LOGIN', 'ACTIVE', 'BLOCKED',
    ])
    ->setFilter($filter)
    ->setOrder(['DATE_REGISTER' => 'DESC']);

if ($search !== '') {
    // Поиск по имени, фамилии, логину или телефону
    $query->where(
        Query::filter()
            ->logic('or')
            ->whereLike('NAME', '%' . $search . '%')
            ->whereLike('LAST_NAME', '%' . $search . '%')
            ->whereLike('SECOND_NAME', '%' . $search . '%')
            ->whereLike('LOGIN', '%' . $search . '%')
            ->whereLike('PERSONAL_PHONE', '%' . $search . '%')
    );
}

$rsUsers = $query
    ->countTotal(true)
    ->setOffset($nav->getOffset())
    ->setLimit($nav->getLimit())
    ->exec();

$nav->setRecordCount($rsUsers->getCount());

$users = [];
while ($u = $rsUsers->fetch()) {
    $users[] = $u;
}

$totalCount   = (int)$nav->getRecordCount();
$pageCount    = $nav->getPageCount();
$currentPage  = $nav->getCurrentPage();

// Вспомогательные функции форматирования
$fmtDate = function ($dt) {
    if ($dt instanceof DateTime) {
        return $dt->format('d.m.Y H:i');
    }
    if (is_string($dt) && $dt !== '') {
        return htmlspecialchars($dt);
    }
    return '—';
};

$fmtFio = function (array $u) {
    $fio = trim(trim((string)$u['LAST_NAME'] . ' ' . (string)$u['NAME'] . ' ' . (string)$u['SECOND_NAME']));
    if ($fio === '') {
        $fio = trim((string)$u['LOGIN']);
    }
    if ($fio === '') {
        $fio = trim((string)$u['EMAIL']);
    }
    return $fio;
};

$fmtSite = function ($lid) use ($siteNames) {
    $lid = trim((string)$lid);
    if ($lid === '') {
        return '—';
    }
    if (isset($siteNames[$lid]) && $siteNames[$lid] !== '') {
        return htmlspecialchars($siteNames[$lid]) . ' <span style="color:var(--color-muted);">(' . htmlspecialchars($lid) . ')</span>';
    }
    return htmlspecialchars($lid);
};

// Параметры для ссылок пагинации (фильтр сохраняется)
$pageParam  = 'PAGEN_users';
$baseParams = [];
if ($search !== '')     $baseParams['SEARCH'] = $search;
if ($userStatus !== '') $baseParams['USER_STATUS'] = $userStatus;

$makeUrl = function (array $extra = []) use ($baseParams) {
    $params = array_merge($baseParams, $extra);
    $qs = http_build_query($params);
    return $qs !== '' ? '?' . $qs : '';
};

$prevUrl = $currentPage > 1 ? $makeUrl([$pageParam => $currentPage - 1]) : '';
$nextUrl = $currentPage < $pageCount ? $makeUrl([$pageParam => $currentPage + 1]) : '';

// Окно страниц для пагинации
$window = [];
$start = max(1, $currentPage - 4);
$end = min($pageCount, $currentPage + 4);
for ($p = $start; $p <= $end; $p++) {
    $window[] = $p;
}
?>
<style>
    .p-users-filter {
        background: var(--bg-black, #1B1818);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        color-scheme: dark;
    }
    .p-users-filter__row {
        display: flex;
        gap: 18px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    .p-users-filter__group {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 160px;
    }
    .p-users-filter__group--grow { flex: 1; min-width: 220px; }
    .p-users-filter__group label {
        font-size: 13px;
        color: var(--color-muted, #969696);
        font-weight: 500;
    }
    .p-users-filter__group input[type="text"],
    .p-users-filter__group select {
        width: 100%;
        padding: 10px 12px;
        background-color: var(--bg-gray, #2B2D2F);
        border: 1px solid var(--color-border, #868686);
        border-radius: 8px;
        color: var(--bg-white, #FFFFFF);
        font-size: 14px;
        font-family: var(--font-family, 'Blogger Sans', 'Roboto');
        box-sizing: border-box;
        transition: border-color .2s ease;
    }
    .p-users-filter__group input:focus,
    .p-users-filter__group select:focus {
        border-color: var(--bg-button, #F44336);
        outline: none;
    }
    .p-users-filter__group select {
        min-width: 200px;
        padding-right: 36px;
        background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M6 9L12 15L18 9' stroke='%23969696' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 14px;
        cursor: pointer;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
    }
    .p-users-filter__group select:hover { border-color: var(--bg-button, #F44336); }
    .p-users-filter__group select option {
        background: var(--bg-gray, #2B2D2F);
        color: var(--bg-white, #FFFFFF);
    }
    .p-users-filter__actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    .p-btn {
        padding: 10px 18px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-family: var(--font-family, 'Blogger Sans', 'Roboto');
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all .2s ease;
        white-space: nowrap;
    }
    .p-btn--primary { background: var(--bg-button, #F44336); color: #fff; }
    .p-btn--primary:hover { background: #d32f2f; }
    .p-btn--ghost { background: transparent; color: var(--bg-white, #FFFFFF); border: 1px solid var(--color-border, #868686); }
    .p-btn--ghost:hover { border-color: var(--bg-button, #F44336); color: var(--bg-button, #F44336); }
</style>

        <div class="p-main">
            <div class="p-section">
                <div class="p-section__header">
                    <h2 class="p-section__title">Пользователи сайта</h2>
                    <span style="font-size:13px; color:var(--color-muted);">Всего: <?= $totalCount ?></span>
                </div>

                <form class="p-users-filter" method="get" action="">
                    <div class="p-users-filter__row">
                        <div class="p-users-filter__group p-users-filter__group--grow">
                            <label for="p-users-search">Поиск</label>
                            <input type="text" id="p-users-search" name="SEARCH" value="<?= htmlspecialchars($search) ?>" placeholder="Имя, фамилия, логин или телефон">
                        </div>
                        <div class="p-users-filter__group">
                            <label for="p-users-status">Статус</label>
                            <select id="p-users-status" name="USER_STATUS">
                                <option value="">Все статусы</option>
                                <option value="active" <?= $userStatus === 'active' ? 'selected' : '' ?>>Активен</option>
                                <option value="inactive" <?= $userStatus === 'inactive' ? 'selected' : '' ?>>Не активен</option>
                                <option value="blocked" <?= $userStatus === 'blocked' ? 'selected' : '' ?>>Заблокирован</option>
                            </select>
                        </div>
                        <div class="p-users-filter__actions">
                            <button type="submit" class="p-btn p-btn--primary">Применить</button>
                            <a class="p-btn p-btn--ghost" href="?">Сбросить</a>
                        </div>
                    </div>
                </form>

                <div id="users-message" style="display:none; padding:12px 16px; border-radius:8px; margin-bottom:16px; background:rgba(231,76,60,.12); color:#e74c3c;"></div>

                <div class="p-users-wrap">
                    <div style="overflow-x:auto;">
                        <table class="p-users-table">
                            <thead>
                                <tr>
                                    <th style="text-align:center;">ID</th>
                                    <th>ФИО</th>
                                    <th>Email</th>
                                    <th>Телефон</th>
                                    <th>Сайт</th>
                                    <th>Дата регистрации</th>
                                    <th>Последний вход</th>
                                    <th style="text-align:center;">Активность</th>
                                    <th style="text-align:center;">Заблокирован</th>
                                    <th style="text-align:center;">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$users): ?>
                                    <tr>
                                        <td colspan="10">
                                            <div class="p-users-empty">Пользователи не найдены</div>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php foreach ($users as $u):
                                    $uId       = (int)$u['ID'];
                                    $isSelf    = $uId === (int)$USER->GetID();
                                    $isActive  = $u['ACTIVE'] === 'Y';
                                    $isBlocked = $u['BLOCKED'] === 'Y';
                                ?>
                                    <tr>
                                        <td style="text-align:center; color:var(--color-muted);"><?= $uId ?></td>
                                        <td>
                                            <div class="p-users-fio"><?= htmlspecialchars($fmtFio($u)) ?></div>
                                            <div class="p-users-login"><?= htmlspecialchars((string)$u['LOGIN']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars((string)$u['EMAIL'] !== '' ? $u['EMAIL'] : '—') ?></td>
                                        <td><?= htmlspecialchars((string)$u['PERSONAL_PHONE'] !== '' ? $u['PERSONAL_PHONE'] : '—') ?></td>
                                        <td><?= $fmtSite($u['LID']) ?></td>
                                        <td><?= $fmtDate($u['DATE_REGISTER']) ?></td>
                                        <td><?= $fmtDate($u['LAST_LOGIN']) ?></td>
                                        <td class="p-users-toggle-cell">
                                            <?php if ($isSelf): ?>
                                                <span style="color:var(--color-muted); font-size:12px;">вы</span>
                                            <?php else: ?>
                                                <label class="p-checkbox" title="Активность">
                                                    <input type="checkbox" class="js-users-toggle" data-id="<?= $uId ?>" data-field="ACTIVE" <?= $isActive ? 'checked' : '' ?>>
                                                    <span class="p-checkbox__box">
                                                        <svg class="p-checkbox__check" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" fill="none" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                    </span>
                                                </label>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-users-toggle-cell">
                                            <?php if ($isSelf): ?>
                                                <span style="color:var(--color-muted); font-size:12px;">—</span>
                                            <?php else: ?>
                                                <label class="p-checkbox p-checkbox--danger" title="Заблокирован">
                                                    <input type="checkbox" class="js-users-toggle" data-id="<?= $uId ?>" data-field="BLOCKED" <?= $isBlocked ? 'checked' : '' ?>>
                                                    <span class="p-checkbox__box">
                                                        <svg class="p-checkbox__check" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" fill="none" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                    </span>
                                                </label>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-users-toggle-cell">
                                            <?php if ($isSelf): ?>
                                                <span style="color:var(--color-muted); font-size:12px;">—</span>
                                            <?php else: ?>
                                                <button type="button" class="p-users-delete" data-id="<?= $uId ?>" data-name="<?= htmlspecialchars($fmtFio($u), ENT_QUOTES) ?>" title="Удалить пользователя">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 19C6 20.1 6.9 21 8 21H16C17.1 21 18 20.1 18 19V7H6V19ZM8 9H16V19H8V9ZM15.5 4L14.5 3H9.5L8.5 4H5V6H19V4H15.5Z" fill="currentColor"/></svg>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($pageCount > 1): ?>
                        <div class="p-users-nav">
                            <?php if ($currentPage > 1): ?>
                                <a href="<?= $prevUrl ?>">‹</a>
                            <?php else: ?>
                                <span class="page disabled">‹</span>
                            <?php endif; ?>

                            <?php foreach ($window as $p): ?>
                                <?php if ($p === $currentPage): ?>
                                    <span class="page current"><?= $p ?></span>
                                <?php else: ?>
                                    <a href="<?= $makeUrl([$pageParam => $p]) ?>"><?= $p ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php if ($currentPage < $pageCount): ?>
                                <a href="<?= $nextUrl ?>">›</a>
                            <?php else: ?>
                                <span class="page disabled">›</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

<script>
BX.ready(function () {
    var sessid = '<?= bitrix_sessid() ?>';
    var messageBox = document.getElementById('users-message');

    function showError(msg) {
        if (!messageBox) return;
        messageBox.textContent = msg;
        messageBox.style.display = 'block';
        clearTimeout(showError._t);
        showError._t = setTimeout(function () {
            messageBox.style.display = 'none';
        }, 4000);
    }

    var toggles = document.querySelectorAll('.js-users-toggle');
    for (var i = 0; i < toggles.length; i++) {
        (function (input) {
            input.addEventListener('change', function () {
                var id = input.getAttribute('data-id');
                var field = input.getAttribute('data-field');
                var value = input.checked ? 'Y' : 'N';
                var body = 'ajax_users=Y'
                    + '&ID=' + encodeURIComponent(id)
                    + '&FIELD=' + encodeURIComponent(field)
                    + '&VALUE=' + encodeURIComponent(value)
                    + '&sessid=' + encodeURIComponent(sessid);

                fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    credentials: 'same-origin',
                    body: body
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data || !data.success) {
                        input.checked = !input.checked;
                        showError((data && data.error) ? data.error : 'Не удалось сохранить изменение.');
                    }
                })
                .catch(function () {
                    input.checked = !input.checked;
                    showError('Ошибка сети. Изменение не сохранено.');
                });
            });
        })(toggles[i]);
    }

    // Кнопки удаления пользователя
    var delButtons = document.querySelectorAll('.p-users-delete');
    for (var j = 0; j < delButtons.length; j++) {
        (function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-id');
                var name = btn.getAttribute('data-name') || id;

                if (!confirm('Удалить пользователя «' + name + '»? Действие необратимо.')) {
                    return;
                }

                var body = 'ajax_users=Y'
                    + '&ACTION=delete'
                    + '&ID=' + encodeURIComponent(id)
                    + '&sessid=' + encodeURIComponent(sessid);

                fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    credentials: 'same-origin',
                    body: body
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        location.reload();
                    } else {
                        showError((data && data.error) ? data.error : 'Не удалось удалить пользователя.');
                    }
                })
                .catch(function () {
                    showError('Ошибка сети. Пользователь не удалён.');
                });
            });
        })(delButtons[j]);
    }
});
</script>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
