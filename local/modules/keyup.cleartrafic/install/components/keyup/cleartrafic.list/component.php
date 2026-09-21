<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Bitrix\Main\UI\PageNavigation;
use Keyup\Cleartrafic\Rules;
use Keyup\Cleartrafic\Visits;
use Keyup\Cleartrafic\Emails;
use Keyup\Cleartrafic\Model\RuleTable;
use Keyup\Cleartrafic\Model\VisitTable;

if (!Loader::IncludeModule('keyup.cleartrafic')) {
    ShowError('Не установлен модуль фильтрации трафика');
    return;
}

$entity = isset($arParams['ENTITY']) ? (string)$arParams['ENTITY'] : 'visits';
$ruleType = isset($arParams['RULE_TYPE']) ? (string)$arParams['RULE_TYPE'] : '';
$pageSize = isset($arParams['ROWS_PER_PAGE']) ? (int)$arParams['ROWS_PER_PAGE'] : 50;

if ($pageSize <= 0) {
    $pageSize = 50;
}

$nav = new PageNavigation(isset($arParams['PAGEN_ID']) ? $arParams['PAGEN_ID'] : 'page');
$nav->allowAllRecords(true)->setPageSize($pageSize)->initFromUri();

$rows = [];
$columns = [];

$matchTypeLabels = [
    VisitTable::TYPE_NONE      => '',
    VisitTable::TYPE_REFERER   => 'Реферер',
    VisitTable::TYPE_MASK      => 'Маска',
    VisitTable::TYPE_GRAY      => 'Серый список',
    VisitTable::TYPE_BLACK     => 'Черный список',
    VisitTable::TYPE_USERAGENT => 'User-Agent',
];

switch ($entity) {
    case 'rules':
        $all = Rules::getList($ruleType);

        if ($ruleType === RuleTable::TYPE_MASK) {
            $columns = [
                'ID'           => 'ID',
                'VALUE'        => 'IP-адрес сети',
                'MASK'         => 'Маска',
                'ACTIVE_LABEL' => 'Активность',
                'COMMENT'      => 'Комментарий',
            ];
        } elseif ($ruleType === RuleTable::TYPE_REFERER) {
            $columns = [
                'ID'      => 'ID',
                'VALUE'   => 'HTTP_REFERER',
                'COMMENT' => 'Комментарий',
            ];
        } elseif ($ruleType === RuleTable::TYPE_USERAGENT) {
            $columns = [
                'ID'      => 'ID',
                'VALUE'   => 'User-Agent (фрагмент)',
                'COMMENT' => 'Комментарий',
            ];
        } else {
            $columns = [
                'ID'      => 'ID',
                'VALUE'   => 'IP адрес',
                'COMMENT' => 'Комментарий',
            ];
        }

        $all = array_map(function ($row) {
            $row['ACTIVE_LABEL'] = $row['ACTIVE'] ? 'да' : 'нет';

            return $row;
        }, $all);

        $nav->setRecordCount(count($all));
        $rows = array_slice($all, $nav->getOffset(), $nav->getLimit());
        break;

    case 'emails':
        $columns = [
            'ID'     => 'ID',
            'EMAIL'  => 'Email для оповещений',
        ];

        $all = Emails::getList();
        $nav->setRecordCount(count($all));
        $rows = array_slice($all, $nav->getOffset(), $nav->getLimit());
        break;

    case 'visits':
    default:
        $filter = (isset($arParams['FILTER']) && is_array($arParams['FILTER'])) ? $arParams['FILTER'] : [];

        $columns = [
            'ID'                => 'ID',
            'IP'                => 'IP адрес',
            'PAGE'              => 'Страница входа',
            'REFERER'           => 'Переход с сайта',
            'MATCH_TYPE_LABEL'  => 'Совпадение',
            'MATCH_RULE'        => 'Правило',
            'CAPTCHA_LABEL'     => 'Каптча',
            'VISITS'            => 'Номер визита',
            'DATE_CREATE'       => 'Дата и время визита',
            'DATE_UPDATE'       => 'Дата последнего визита',
        ];

        $nav->setRecordCount(Visits::getCount($filter));

        $rawRows = Visits::getList($filter, $nav->getLimit(), $nav->getOffset());

        foreach ($rawRows as $row) {
            $row['MATCH_TYPE_LABEL'] = isset($matchTypeLabels[$row['MATCH_TYPE']])
                ? $matchTypeLabels[$row['MATCH_TYPE']]
                : '';
            $row['CAPTCHA_LABEL'] = ($row['CAPTCHA_PASSED'] === 'Y') ? 'да' : '';

            $rows[] = $row;
        }
        break;
}

$arResult['ROWS'] = $rows;
$arResult['COLUMNS'] = $columns;
$arResult['ENTITY'] = $entity;
$arResult['RULE_TYPE'] = $ruleType;
$arResult['NAV_OBJECT'] = $nav;
$arResult['NAV_STRING'] = '';
$arResult['NAV_NUM'] = 0;
$arResult['SORT_ID'] = isset($arParams['SORT_FIELD']) ? $arParams['SORT_FIELD'] : 'ID';
$arResult['SORT_TYPE'] = isset($arParams['SORT_ORDER']) ? $arParams['SORT_ORDER'] : 'DESC';

/* Названия типов правил для подписей в интерфейсе */
$arResult['RULE_TYPE_LABELS'] = [
    RuleTable::TYPE_BLACK     => 'чёрный список',
    RuleTable::TYPE_GRAY      => 'серый список',
    RuleTable::TYPE_MASK      => 'маска подсети',
    RuleTable::TYPE_REFERER   => 'реферер',
    RuleTable::TYPE_USERAGENT => 'User-Agent',
];

$this->IncludeComponentTemplate();
