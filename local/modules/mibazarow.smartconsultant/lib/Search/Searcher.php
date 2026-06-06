<?php

declare(strict_types=1);

/**
 * Поисковый движок: эмбеддинг запроса → сравнение с векторами в БД → top-N.
 */

namespace Mibazarow\Smartconsultant\Search;

use Mibazarow\Smartconsultant\Embedding\Engine;
use Mibazarow\Smartconsultant\Embedding\Repository;
use Mibazarow\Smartconsultant\Embedding\Math;

class Searcher
{
    /**
     * Выполнить семантический поиск.
     *
     * @param string    $query          Поисковый запрос
     * @param int       $limit          Количество результатов
     * @param int|null  $iblockId       Фильтр по инфоблоку (null = все)
     * @param float     $minSimilarity  Порог релевантности (0.0 – 1.0)
     * @return array { items: Result[], totalMatches: int, time: float }
     * @throws \RuntimeException
     */
    public function search(string $query, int $limit = 10, ?int $iblockId = null, float $minSimilarity = 0.4): array
    {
        $t0 = microtime(true);

        // 1. Получить эмбеддинг запроса через HTTP-сервис (~10ms)
        $queryVector = Engine::embedQuery($query);

        // 2. Потоковая обработка векторов: читаем по одному, считаем сходство,
        //    держим только top-N в памяти. Память: O(limit) вместо O(всех товаров).
        $repo = new Repository();
        $iblockIds = $iblockId ? [$iblockId] : null;

        // Мини-куча для top-N: храним [$similarity, $id] с приоритетом по минимальному сходству
        $topHeap = new \SplMinHeap();

        $totalCompared = 0;
        foreach ($repo->streamVectors($iblockIds) as $item) {
            $totalCompared++;
            $sim = Math::dotProduct($queryVector, $item['vector']);

            if ($sim >= $minSimilarity) {
                $topHeap->insert([$sim, $item['id']]);

                // Держим кучу размером не больше limit
                if ($topHeap->count() > $limit) {
                    $topHeap->extract();
                }
            }
        }

        // 3. Извлекаем из кучи и сортируем по убыванию
        $topScores = [];
        while (!$topHeap->isEmpty()) {
            [$sim, $id] = $topHeap->extract();
            $topScores[] = ['id' => $id, 'similarity' => round($sim, 4)];
        }
        $topScores = array_reverse($topScores); // Куча была min-heap, разворачиваем

        // 4. Гидрация результатов (названия, URL, картинки)
        $items = Result::hydrate($topScores);

        $elapsed = round(microtime(true) - $t0, 3);

        return [
            'items' => $items,
            'totalMatches' => count($topScores),
            'time' => $elapsed,
        ];
    }
}
