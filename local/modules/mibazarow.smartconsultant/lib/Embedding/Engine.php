<?php

declare(strict_types=1);

/**
 * Интерфейс к Python: HTTP-сервис для поиска + CLI для пакетной индексации.
 */

namespace Mibazarow\Smartconsultant\Embedding;

use Bitrix\Main\Web\Json;

class Engine
{
    const EMBED_SERVICE_URL = 'http://127.0.0.1:9876';
    const VECTOR_DIM = 1024;
    const BATCH_CHUNK_SIZE = 32;

    /**
     * Получить эмбеддинг для одного поискового запроса (HTTP → server.py).
     * Модель уже в памяти — отвечает за ~40ms (e5-large).
     *
     * @param string $text Поисковый запрос
     * @return float[] Вектор размерности 1024
     * @throws \RuntimeException Если сервис недоступен
     */
    public static function embedQuery(string $text): array
    {
        $body = Json::encode(['text' => $text]);

        $ch = curl_init(self::EMBED_SERVICE_URL . '/embed');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Expect:',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \RuntimeException(
                'HTTP-сервис эмбеддингов вернул ошибку: статус=' . $httpCode
                . ($error ? ', curl: ' . $error : '')
                . '. Ответ: ' . (is_string($response) ? substr($response, 0, 500) : 'нет ответа')
            );
        }

        $data = Json::decode($response);

        if (!isset($data['embedding'])) {
            throw new \RuntimeException(
                'HTTP-сервис эмбеддингов вернул некорректный ответ: '
                . (is_string($response) ? substr($response, 0, 500) : 'нет ответа')
            );
        }

        return $data['embedding'];
    }

    /**
     * Пакетная индексация через HTTP-сервис server.py (модель уже в памяти).
     * Обрабатывает чанками по BATCH_CHUNK_SIZE и вызывает $onChunk для каждого чанка,
     * чтобы не копить все векторы в памяти (62k товаров × 1024 float ≈ 5.3 GB в PHP).
     *
     * @param array $items Массив [{id, text}, ...]
     * @param callable|null $onChunk Вызывается для каждого чанка: fn(array $chunkResults)
     * @return array Все результаты (пустой массив, если передан $onChunk)
     * @throws \RuntimeException Если сервис недоступен
     */
    public static function embedBatch(array $items, ?callable $onChunk = null): array
    {
        if (empty($items)) {
            return [];
        }

        $allResults = [];
        $chunks = array_chunk($items, self::BATCH_CHUNK_SIZE);
        $totalChunks = count($chunks);

        foreach ($chunks as $chunkIndex => $chunk) {
            // Прогресс каждые 10 чанков или первый/последний
            if ($chunkIndex % 10 === 0) {
                $pct = round(($chunkIndex / $totalChunks) * 100);
                echo "    Chunk {$chunkIndex}/{$totalChunks} ({$pct}%)\n";
                flush();
            }

            $body = Json::encode(['items' => array_values($chunk)]);
            $url = self::EMBED_SERVICE_URL . '/embed-batch';

            // Используем curl — надёжнее, чем file_get_contents (не зависит от allow_url_fopen)
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Expect:',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false || $httpCode !== 200) {
                throw new \RuntimeException(
                    'HTTP-сервис эмбеддингов недоступен или вернул ошибку (чанк ' . $chunkIndex
                    . ', статус=' . $httpCode
                    . ($error ? ', curl: ' . $error : '')
                    . '): ' . (is_string($response) ? substr($response, 0, 500) : 'нет ответа')
                );
            }

            $data = Json::decode($response);

            if (!isset($data['results']) || !is_array($data['results'])) {
                throw new \RuntimeException(
                    'HTTP-сервис эмбеддингов вернул некорректный ответ (чанк ' . $chunkIndex . '): '
                    . (is_string($response) ? substr($response, 0, 200) : 'нет ответа')
                );
            }

            if ($onChunk !== null) {
                // Сразу передаём результаты колбэку — не копим в памяти
                $onChunk($data['results']);
            } else {
                $allResults = array_merge($allResults, $data['results']);
            }
        }

        return $allResults;
    }

    /**
     * Найти путь к Python из виртуального окружения.
     *
     * @return string Абсолютный путь к python
     * @throws \RuntimeException
     */
    public static function findPython(): string
    {
        // Не используем realpath(), так как venv/bin/python — симлинк на системный python3.
        // При realpath() теряется контекст venv и Python не находит пакеты (pyvenv.cfg).
        $venvPython = __DIR__ . '/../../python/venv/bin/python';

        // Проверяем, что файл существует и доступен для выполнения
        if (file_exists($venvPython) && is_executable($venvPython)) {
            return $venvPython;
        }

        throw new \RuntimeException(
            'Python venv не найден. Создайте виртуальное окружение: '
            . 'cd ' . dirname(__DIR__, 2) . '/python'
            . ' && python3 -m venv venv'
            . ' && venv/bin/pip install -r requirements.txt'
        );
    }

    /**
     * Проверить, доступен ли HTTP-сервис эмбеддингов.
     *
     * @return bool
     */
    public static function isServiceAvailable(): bool
    {
        try {
            $ch = curl_init(self::EMBED_SERVICE_URL . '/health');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            if ($response === false) {
                return false;
            }

            $data = Json::decode($response);
            return ($data['status'] ?? '') === 'ok';
        } catch (\Throwable $e) {
            return false;
        }
    }
}
