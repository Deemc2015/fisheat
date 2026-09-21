<?php

define("NO_KEEP_STATISTIC", true);
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Keyup\Cleartrafic\Filter;
use Keyup\Cleartrafic\User;
use Keyup\Cleartrafic\Model\VisitTable;

if (!Loader::IncludeModule('keyup.cleartrafic')) {
    echo "Не установлен модуль фильтрации трафика";
}

global $USER, $APPLICATION;

if (!$USER->isAuthorized()) {
    LocalRedirect(SITE_DIR . 'auth/');
}

$result = new User;
$permission = $result->hasPermission();

$filterParams = Filter::getFilterParams();
$fp = is_array($filterParams) ? $filterParams : array();
$filter = isset($fp['SEARCH']) ? $fp['SEARCH'] : array();
$filter[] = ['=MATCH_TYPE' => VisitTable::TYPE_GRAY];

$period = isset($fp['PERIOD']) ? $fp['PERIOD'] : '';
$ipValue = isset($fp['IP']) ? $fp['IP'] : '';
$periodList = isset($fp['PERIOD_LIST']) ? $fp['PERIOD_LIST'] : array('DATE_START' => '', 'DATE_END' => '');

$pageTitle = 'Сводка серого списка IP';
include dirname(__DIR__) . '/header.php';
?>
<div class="filter-line">
    <a href="?date=today" class="date <?= $period == 'today' ? 'active' : '' ?>">Сегодня</a>
    <a href="?date=yesterday" class="date <?= $period == 'yesterday' ? 'active' : '' ?>">Вчера</a>
    <a href="?date=day_before_yesterday" class="date <?= $period == 'day_before_yesterday' ? 'active' : '' ?>">Позавчера</a>
    <a href="?date=week" class="date <?= $period == 'week' ? 'active' : '' ?>">Неделя</a>
    <a href="?date=month" class="date <?= $period == 'month' ? 'active' : '' ?>">Месяц</a>
    <a href="#" class="date period <?= $period == 'period' ? 'active' : '' ?>">Выбрать период</a>
</div>

<div class="ipsearch">
    <input type="text" name="ip" class="ip" placeholder="Введите IP" value="<?= htmlspecialcharsbx($ipValue) ?>">
    <button type="submit" class="searchIp">Поиск</button>
    <div class="period-date <?= $period == 'period' ? 'show' : '' ?>">
        <form>
            <input type="text" id="datepicker" name="dateStart" placeholder="Начало периода" value="<?= htmlspecialcharsbx($periodList['DATE_START']) ?>">
            <input type="text" id="datepicker_2" name="dateEnd" placeholder="Конец периода" value="<?= htmlspecialcharsbx($periodList['DATE_END']) ?>">
            <button type="submit">Поиск</button>
        </form>
    </div>
</div>

<? $APPLICATION->IncludeComponent(
    'keyup:cleartrafic.list',
    '',
    array(
        'ENTITY'        => 'visits',
        'FILTER'        => $filter,
        'ROWS_PER_PAGE' => 50,
        'ACTIONS'       => 'N',
        'PAGEN_ID'      => 'page',
    )
); ?>

<?php include dirname(__DIR__) . '/footer.php'; ?>
