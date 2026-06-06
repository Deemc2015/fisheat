<?php

declare(strict_types=1);

/**
 * AJAX-контроллер семантического поиска.
 *
 * POST /bitrix/services/main/ajax.php?action=mibazarow:smartconsultant.SearchController.search
 * Body: { query: "чем постричь газон", limit: 10, iblockId: 5 }
 */

namespace Mibazarow\Smartconsultant\Infrastructure\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Config\Option;
use Mibazarow\Smartconsultant\Search\Searcher;
use Mibazarow\Smartconsultant\Embedding\Repository;
use Mibazarow\Smartconsultant\Embedding\Engine;

class SearchController extends Controller
{
    /**
     * Обязательные фильтры: только AJAX.
     */
    public function configureActions(): array
    {
        return [
            'search' => [
                'prefilters' => [],
            ],
        ];
    }

    /**
     * Семантический поиск товаров.
     *
     * @param string    $query      Поисковый запрос
     * @param int       $limit      Количество результатов (по умолчанию 10)
     * @param int|null  $iblockId   Фильтр по инфоблоку (null = все)
     * @param float     $minSimilarity Порог релевантности (по умолчанию 0.4)
     * @return array
     */
    public function searchAction(string $query, ?int $limit = null, ?int $iblockId = null, ?float $minSimilarity = null): array
    {
        $query = trim($query);

        // Значения по умолчанию — из настроек модуля
        $limit = $limit ?? (int)Option::get('mibazarow.smartconsultant', 'TOP_COUNT', '20');
        $iblockId = $iblockId ?? (int)Option::get('mibazarow.smartconsultant', 'CATALOG_IBLOCK_ID', '');
        $minSimilarity = $minSimilarity ?? (float)Option::get('mibazarow.smartconsultant', 'MIN_SIMILARITY', '0.4');

        if (empty($query)) {
            return [
                'success' => false,
                'error' => 'Пустой запрос',
                'items' => [],
            ];
        }

        try {
            $searcher = new Searcher();
            $result = $searcher->search($query, $limit, $iblockId ?: null, $minSimilarity);

            // Преобразуем Result DTO в массивы для JSON
            $items = array_map(function ($item) {
                return [
                    'itemId' => $item->itemId,
                    'iblockId' => $item->iblockId,
                    'name' => $item->name,
                    'url' => $item->url,
                    'imageUrl' => $item->imageUrl,
                    'similarity' => $item->similarity,
                ];
            }, $result['items']);

            return [
                'success' => true,
                'items' => $items,
                'totalMatches' => $result['totalMatches'],
                'time' => $result['time'],
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'items' => [],
            ];
        }
    }

    /**
     * Статус индекса: количество проиндексированных товаров, дата последней индексации.
     *
     * @return array
     */
    public function statusAction(): array
    {
        try {
            $repo = new Repository();
            return [
                'success' => true,
                'totalIndexed' => $repo->count(),
                'lastIndexedAt' => $repo->lastIndexedAt(),
                'serviceAvailable' => Engine::isServiceAvailable(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
