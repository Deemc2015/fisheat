<?php

declare(strict_types=1);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Mibazarow\Smartconsultant\Search\Searcher;

class SmartconsultantSearchComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        // Подключаем модуль
        if (!Loader::includeModule('mibazarow.smartconsultant')) {
            ShowError('Модуль mibazarow.smartconsultant не установлен');
            return;
        }

        // Все настройки — из модуля, а не из параметров компонента
        $iblockId = (int)Option::get('mibazarow.smartconsultant', 'CATALOG_IBLOCK_ID', '');
        $topCount = (int)Option::get('mibazarow.smartconsultant', 'TOP_COUNT', '20');
        $minSimilarity = (float)Option::get('mibazarow.smartconsultant', 'MIN_SIMILARITY', '0.4');

        $this->arResult['IBLOCK_ID'] = $iblockId;
        $this->arResult['TOP_COUNT'] = $topCount;
        $this->arResult['MIN_SIMILARITY'] = $minSimilarity;
        $this->arResult['INPUT_PLACEHOLDER'] = $this->arParams['INPUT_PLACEHOLDER'] ?: 'Опишите, что вы ищете...';

        // Идентификатор для JS (уникальный на странице)
        $this->arResult['COMPONENT_ID'] = 'smartconsultant_' . $this->randString();

        // URL AJAX-контроллера
        $this->arResult['AJAX_URL'] = '/bitrix/services/main/ajax.php?action='
            . 'mibazarow:smartconsultant.SearchController.search';

        // Серверный поиск по GET-параметру ?q=
        $query = trim((string)($_REQUEST['q'] ?? ''));
        $this->arResult['QUERY'] = $query;
        $this->arResult['FOUND_ITEMS'] = [];
        $this->arResult['FOUND_ELEMENT_IDS'] = [];
        $this->arResult['SEARCH_TIME'] = 0;
        $this->arResult['TOTAL_MATCHES'] = 0;

        if ($query !== '' && $iblockId > 0) {
            try {
                $searcher = new Searcher();
                $result = $searcher->search($query, $topCount, $iblockId, $minSimilarity);

                $this->arResult['FOUND_ITEMS'] = $result['items'];
                $this->arResult['SEARCH_TIME'] = $result['time'];
                $this->arResult['TOTAL_MATCHES'] = $result['totalMatches'];

                // Собираем ID элементов для catalog.section
                $elementIds = [];
                foreach ($result['items'] as $item) {
                    $elementIds[] = $item->itemId;
                }
                $this->arResult['FOUND_ELEMENT_IDS'] = $elementIds;
            } catch (\Throwable $e) {
                $this->arResult['SEARCH_ERROR'] = $e->getMessage();
            }
        }

        // Подключаем шаблон
        $this->includeComponentTemplate();
    }
}
