<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\Main\UserTable;
use Bitrix\Main\Engine\Contract\Controllerable;

Loader::includeModule('main');

/**
 * Компонент "Список пользователей" для партнёрского раздела /partners/polzovateli/.
 *
 * Выводит пользователей (b_user) с фильтром по поиску и статусу,
 * постранично. Изменение данных (переключение ACTIVE / BLOCKED и удаление)
 * выполняется AJAX-контроллерами компонента (toggleFlagAction / deleteUserAction).
 */
class UsersList extends \CBitrixComponent implements Controllerable
{
    /** Размер страницы по умолчанию. */
    const DEFAULT_PAGE_SIZE = 50;

    /**
     * Подготовка параметров компонента.
     *
     * @param array $arParams
     * @return array
     */
    public function onPrepareComponentParams($arParams)
    {
        $arParams['PAGE_SIZE'] = (int)($arParams['PAGE_SIZE'] ?? 0);
        if ($arParams['PAGE_SIZE'] <= 0) {
            $arParams['PAGE_SIZE'] = self::DEFAULT_PAGE_SIZE;
        }

        return $arParams;
    }

    /**
     * Основной вывод компонента.
     */
    public function executeComponent()
    {
        $this->arResult = $this->buildResult();
        $this->includeComponentTemplate();
    }

    /**
     * Собирает данные списка: пользователи, фильтр, пагинация.
     *
     * @return array
     */
    private function buildResult(): array
    {
        global $USER;

        $request = Context::getCurrent()->getRequest();

        // Коды сайтов -> названия.
        $siteNames = [];
        $rsSites = \CSite::GetList($by = 'sort', $order = 'asc');
        while ($site = $rsSites->Fetch()) {
            $siteNames[$site['LID']] = $site['NAME'];
        }

        // Параметры фильтра: поиск (имя/телефон) и статус пользователя.
        $search     = trim((string)$request->getQuery('SEARCH'));
        $userId     = (int)$request->getQuery('ID');
        $userStatus = trim((string)$request->getQuery('USER_STATUS'));
        if (!in_array($userStatus, ['active', 'inactive', 'blocked'], true)) {
            $userStatus = '';
        }

        $nav = new PageNavigation('users');
        $nav->allowAllRecords(false)->setPageSize($this->arParams['PAGE_SIZE'])->initFromUri();

        $filter = ['!=ID' => 0];
        // Переход к конкретному пользователю (ссылка из заказов: ?ID=<USER_ID>)
        if ($userId > 0) {
            $filter['=ID'] = $userId;
        }
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
            // Поиск по имени, фамилии, отчеству, логину или телефону
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

        $selfId = (int)$USER->GetID();
        $users  = [];
        while ($u = $rsUsers->fetch()) {
            $uId = (int)$u['ID'];
            $users[] = [
                'ID'            => $uId,
                'LOGIN'         => (string)$u['LOGIN'],
                'EMAIL'         => (string)$u['EMAIL'],
                'PHONE'         => (string)$u['PERSONAL_PHONE'],
                'FIO'           => $this->formatFio($u),
                'LID'           => (string)$u['LID'],
                'SITE_NAME'     => (string)($siteNames[$u['LID']] ?? ''),
                'DATE_REGISTER' => $this->formatDate($u['DATE_REGISTER']),
                'LAST_LOGIN'    => $this->formatDate($u['LAST_LOGIN']),
                'ACTIVE'        => $u['ACTIVE'] === 'Y',
                'BLOCKED'       => $u['BLOCKED'] === 'Y',
                'IS_SELF'       => $uId === $selfId,
            ];
        }

        $totalCount  = (int)$nav->getRecordCount();
        $pageCount   = $nav->getPageCount();
        $currentPage = $nav->getCurrentPage();

        // Параметры для ссылок пагинации (фильтр сохраняется)
        $baseParams = [];
        if ($search !== '')     $baseParams['SEARCH'] = $search;
        if ($userStatus !== '') $baseParams['USER_STATUS'] = $userStatus;
        if ($userId > 0)        $baseParams['ID'] = $userId;

        $makeUrl = function (array $extra = []) use ($baseParams) {
            $params = array_merge($baseParams, $extra);
            $qs = http_build_query($params);
            return $qs !== '' ? '?' . $qs : '';
        };

        $pageParam = 'PAGEN_users';

        // Окно страниц для пагинации
        $window = [];
        $start  = max(1, $currentPage - 4);
        $end    = min($pageCount, $currentPage + 4);
        for ($p = $start; $p <= $end; $p++) {
            $window[] = [
                'PAGE'    => $p,
                'URL'     => $makeUrl([$pageParam => $p]),
                'CURRENT' => $p === $currentPage,
            ];
        }

        return [
            'USERS'        => $users,
            'SEARCH'       => $search,
            'USER_STATUS'  => $userStatus,
            'USER_ID'      => $userId,
            'TOTAL_COUNT'  => $totalCount,
            'PAGE_COUNT'   => $pageCount,
            'CURRENT_PAGE' => $currentPage,
            'WINDOW'       => $window,
            'PREV_URL'     => $currentPage > 1 ? $makeUrl([$pageParam => $currentPage - 1]) : '',
            'NEXT_URL'     => $currentPage < $pageCount ? $makeUrl([$pageParam => $currentPage + 1]) : '',
            'SESSID'       => bitrix_sessid(),
        ];
    }

    /**
     * ФИО пользователя (или логин/email как fallback).
     *
     * @param array $u
     * @return string
     */
    private function formatFio(array $u): string
    {
        $fio = trim(trim((string)$u['LAST_NAME'] . ' ' . (string)$u['NAME'] . ' ' . (string)$u['SECOND_NAME']));
        if ($fio === '') {
            $fio = trim((string)$u['LOGIN']);
        }
        if ($fio === '') {
            $fio = trim((string)$u['EMAIL']);
        }
        return $fio;
    }

    /**
     * Формат даты для вывода.
     *
     * @param mixed $dt
     * @return string
     */
    private function formatDate($dt): string
    {
        if ($dt instanceof DateTime) {
            return $dt->format('d.m.Y H:i');
        }
        if (is_string($dt) && $dt !== '') {
            return $dt;
        }
        return '—';
    }

    /**
     * Конфигурация AJAX-действий. Префильтры пустые — CSRF-защита
     * выполняется вручную через check_bitrix_sessid().
     *
     * @return array
     */
    public function configureActions()
    {
        return [
            'toggleFlag' => [
                'prefilters' => [],
            ],
            'deleteUser' => [
                'prefilters' => [],
            ],
        ];
    }

    /**
     * AJAX-контроллер: переключение флага ACTIVE / BLOCKED пользователя.
     *
     * @return array{success: bool, value?: string, error?: string}
     */
    public function toggleFlagAction(): array
    {
        global $USER, $DB;

        if (!check_bitrix_sessid()) {
            return ['success' => false, 'error' => 'Сессия истекла. Обновите страницу.'];
        }
        if (!$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Требуется авторизация.'];
        }

        $request = Context::getCurrent()->getRequest();
        $userId  = (int)$request->getPost('id');
        $field   = (string)$request->getPost('field');
        $value   = $request->getPost('value') === 'Y' ? 'Y' : 'N';

        if ($userId <= 0) {
            return ['success' => false, 'error' => 'Неверный идентификатор пользователя.'];
        }
        if ($userId === (int)$USER->GetID()) {
            return ['success' => false, 'error' => 'Нельзя изменять собственную учётную запись.'];
        }
        if (!in_array($field, ['ACTIVE', 'BLOCKED'], true)) {
            return ['success' => false, 'error' => 'Неизвестное поле.'];
        }

        // Проверяем, что пользователь существует
        $dbExists = $DB->Query("SELECT ID FROM b_user WHERE ID = " . $userId);
        if (!$dbExists->Fetch()) {
            return ['success' => false, 'error' => 'Пользователь не найден.'];
        }

        // Прямое обновление штатных полей b_user (ACTIVE / BLOCKED)
        $valueSql = $DB->ForSql($value);
        $DB->Query("UPDATE b_user SET `{$field}` = '{$valueSql}' WHERE ID = " . $userId);

        return ['success' => true, 'value' => $value];
    }

    /**
     * AJAX-контроллер: удаление пользователя (штатный метод с очисткой данных).
     *
     * @return array{success: bool, deleted?: int, error?: string}
     */
    public function deleteUserAction(): array
    {
        global $USER;

        if (!check_bitrix_sessid()) {
            return ['success' => false, 'error' => 'Сессия истекла. Обновите страницу.'];
        }
        if (!$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Требуется авторизация.'];
        }

        $request = Context::getCurrent()->getRequest();
        $userId  = (int)$request->getPost('id');

        if ($userId <= 0) {
            return ['success' => false, 'error' => 'Неверный идентификатор пользователя.'];
        }
        if ($userId === (int)$USER->GetID()) {
            return ['success' => false, 'error' => 'Нельзя удалить собственную учётную запись.'];
        }

        $obUser = new \CUser();
        if (!$obUser->Delete($userId)) {
            $err = trim(strip_tags((string)$obUser->LAST_ERROR));
            return ['success' => false, 'error' => $err !== '' ? $err : 'Не удалось удалить пользователя.'];
        }

        return ['success' => true, 'deleted' => $userId];
    }
}
