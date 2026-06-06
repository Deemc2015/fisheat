<?php

declare(strict_types=1);

/**
 * Математические операции с векторами: косинусное расстояние, нормализация.
 */

namespace Mibazarow\Smartconsultant\Embedding;

class Math
{
    /**
     * Вычислить косинусное сходство между двумя векторами.
     * Оба вектора должны быть одинаковой длины и нормализованы.
     *
     * @param float[] $a
     * @param float[] $b
     * @return float Значение от -1 до 1 (1 = идентичны, 0 = ортогональны, -1 = противоположны)
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = self::dotProduct($a, $b);
        $normA = self::norm($a);
        $normB = self::norm($b);

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Скалярное произведение двух векторов.
     *
     * @param float[] $a
     * @param float[] $b
     * @return float
     */
    public static function dotProduct(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new \InvalidArgumentException(
                'Vectors must have same dimension: ' . count($a) . ' vs ' . count($b)
            );
        }
        $sum = 0.0;
        $count = count($a);
        for ($i = 0; $i < $count; $i++) {
            $sum += $a[$i] * $b[$i];
        }
        return $sum;
    }

    /**
     * Евклидова норма (L2) вектора.
     *
     * @param float[] $vector
     * @return float
     */
    public static function norm(array $vector): float
    {
        $sum = 0.0;
        foreach ($vector as $v) {
            $sum += $v * $v;
        }
        return sqrt($sum);
    }

    /**
     * Поиск top-N наиболее похожих векторов.
     *
     * @param float[] $queryVector Нормализованный вектор запроса
     * @param array  $vectors     Массив [{id, vector}, ...]
     * @param int    $topN        Сколько вернуть
     * @param float  $minSimilarity Минимальный порог сходства (0.0 – 1.0)
     * @return array Отсортированный массив [{id, similarity}, ...]
     */
    public static function findTopN(array $queryVector, array $vectors, int $topN = 20, float $minSimilarity = 0.4): array
    {
        $scores = [];

        foreach ($vectors as $item) {
            // Векторы от Python уже нормализованы (normalize_embeddings=True),
            // поэтому косинусное сходство = скалярное произведение.
            // Пропускаем norm() — экономим ~50% CPU на этом цикле.
            $sim = self::dotProduct($queryVector, $item['vector']);
            if ($sim >= $minSimilarity) {
                $scores[] = [
                    'id' => $item['id'],
                    'similarity' => round($sim, 4),
                ];
            }
        }

        // Сортировка по убыванию сходства
        usort($scores, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        // Top-N
        return array_slice($scores, 0, $topN);
    }
}
