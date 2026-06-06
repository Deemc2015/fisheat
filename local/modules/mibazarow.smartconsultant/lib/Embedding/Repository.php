<?php

declare(strict_types=1);

/**
 * CRUD-операции с векторами эмбеддингов в БД.
 */

namespace Mibazarow\Smartconsultant\Embedding;

use Bitrix\Main\DB\Connection;
use Bitrix\Main\Application;

class Repository
{
    private Connection $connection;
    private string $tableName = 'mib_smartconsultant_embedding';

    public function __construct()
    {
        $this->connection = Application::getConnection();
    }

    /**
     * Сохранить один эмбеддинг (INSERT ... ON DUPLICATE KEY UPDATE).
     *
     * @param int $searchContentId ID записи из b_search_content
     * @param array $embedding float[1024]
     * @param string $textHash MD5 текста
     */
    public function save(int $searchContentId, array $embedding, string $textHash): void
    {
        $blob = self::packVector($embedding);
        $helper = $this->connection->getSqlHelper();

        // Экранируем бинарные данные для безопасной вставки в SQL
        $safeBlob = $helper->forSql($blob);
        $safeHash = $helper->forSql($textHash);

        $sql = "
            INSERT INTO {$this->tableName} (SEARCH_CONTENT_ID, EMBEDDING, TEXT_HASH, INDEXED_AT)
            VALUES ({$searchContentId}, '{$safeBlob}', '{$safeHash}', NOW())
            ON DUPLICATE KEY UPDATE
                EMBEDDING = VALUES(EMBEDDING),
                TEXT_HASH = VALUES(TEXT_HASH),
                INDEXED_AT = NOW()
        ";

        $this->connection->queryExecute($sql);
    }

    /**
     * Пакетное сохранение эмбеддингов (один INSERT на все записи).
     *
     * @param array $items Массив [{id, embedding: float[], hash}, ...]
     */
    public function saveBatch(array $items): void
    {
        if (empty($items)) {
            return;
        }

        $helper = $this->connection->getSqlHelper();
        $values = [];

        foreach ($items as $item) {
            $blob = self::packVector($item['embedding']);
            $safeBlob = $helper->forSql($blob);
            $safeHash = $helper->forSql($item['hash']);
            $id = (int)$item['id'];

            $values[] = "({$id}, '{$safeBlob}', '{$safeHash}', NOW())";
        }

        // Batch INSERT с ON DUPLICATE KEY UPDATE
        $valueStr = implode(",\n", $values);

        $sql = "
            INSERT INTO {$this->tableName} (SEARCH_CONTENT_ID, EMBEDDING, TEXT_HASH, INDEXED_AT)
            VALUES {$valueStr}
            ON DUPLICATE KEY UPDATE
                EMBEDDING = VALUES(EMBEDDING),
                TEXT_HASH = VALUES(TEXT_HASH),
                INDEXED_AT = NOW()
        ";

        $this->connection->queryExecute($sql);
    }

    /**
     * Потоковая загрузка векторов — возвращает генератор.
     * Не копит все векторы в памяти, каждый отдаётся по одному.
     * Память: O(1) вместо O(N).
     *
     * @param int[]|null $iblockIds ID инфоблоков для фильтрации (через b_search_content.PARAM2)
     * @return \Generator Каждый элемент: ['id' => int, 'vector' => float[]]
     */
    public function streamVectors(?array $iblockIds = null): \Generator
    {
        $sql = "
            SELECT e.SEARCH_CONTENT_ID, e.EMBEDDING
            FROM {$this->tableName} e
        ";

        if (!empty($iblockIds)) {
            $iblockList = implode(',', array_map('intval', $iblockIds));
            $sql .= "
                INNER JOIN b_search_content sc ON sc.ID = e.SEARCH_CONTENT_ID
                WHERE sc.PARAM2 IN ({$iblockList})
            ";
        }

        $result = $this->connection->query($sql);

        while ($row = $result->fetch()) {
            yield [
                'id' => (int)$row['SEARCH_CONTENT_ID'],
                'vector' => self::unpackVector($row['EMBEDDING']),
            ];
        }
    }

    /**
     * Загрузить все векторы (опционально с фильтром по инфоблокам).
     *
     * @param int[]|null $iblockIds ID инфоблоков для фильтрации (через b_search_content.PARAM2)
     * @return array Массив [{id: SEARCH_CONTENT_ID, vector: float[]}, ...]
     * @deprecated Используйте streamVectors() для потоковой обработки без OOM
     */
    public function loadAll(?array $iblockIds = null): array
    {
        $sql = "
            SELECT e.SEARCH_CONTENT_ID, e.EMBEDDING
            FROM {$this->tableName} e
        ";

        if (!empty($iblockIds)) {
            $iblockList = implode(',', array_map('intval', $iblockIds));
            $sql .= "
                INNER JOIN b_search_content sc ON sc.ID = e.SEARCH_CONTENT_ID
                WHERE sc.PARAM2 IN ({$iblockList})
            ";
        }

        $result = $this->connection->query($sql);

        $vectors = [];
        while ($row = $result->fetch()) {
            $vectors[] = [
                'id' => (int)$row['SEARCH_CONTENT_ID'],
                'vector' => self::unpackVector($row['EMBEDDING']),
            ];
        }

        return $vectors;
    }

    /**
     * Получить хеши текстов для проверки изменений.
     * Возвращает ассоциативный массив SEARCH_CONTENT_ID => TEXT_HASH.
     *
     * @return array
     */
    public function getHashIndex(): array
    {
        $sql = "SELECT SEARCH_CONTENT_ID, TEXT_HASH FROM {$this->tableName}";
        $result = $this->connection->query($sql);

        $hashes = [];
        while ($row = $result->fetch()) {
            $hashes[(int)$row['SEARCH_CONTENT_ID']] = $row['TEXT_HASH'];
        }

        return $hashes;
    }

    /**
     * Удалить эмбеддинги, чьих SEARCH_CONTENT_ID больше нет в b_search_content.
     */
    public function deleteOrphaned(): void
    {
        $sql = "
            DELETE e FROM {$this->tableName} e
            LEFT JOIN b_search_content sc ON sc.ID = e.SEARCH_CONTENT_ID
            WHERE sc.ID IS NULL
        ";
        $this->connection->queryExecute($sql);
    }

    /**
     * Количество проиндексированных записей.
     */
    public function count(): int
    {
        $result = $this->connection->query("SELECT COUNT(*) as cnt FROM {$this->tableName}");
        $row = $result->fetch();
        return (int)$row['cnt'];
    }

    /**
     * Дата последней индексации.
     */
    public function lastIndexedAt(): ?string
    {
        $result = $this->connection->query("SELECT MAX(INDEXED_AT) as last_dt FROM {$this->tableName}");
        $row = $result->fetch();
        return $row['last_dt'] ?: null;
    }

    /**
     * Упаковать float[] в бинарную строку (BLOB).
     * 1024 float32 = 4096 байта.
     *
     * @param float[] $vector
     * @return string
     */
    public static function packVector(array $vector): string
    {
        return pack('f*', ...$vector);
    }

    /**
     * Распаковать бинарную строку в float[].
     *
     * @param string $blob
     * @return float[]
     */
    public static function unpackVector(string $blob): array
    {
        $unpacked = unpack('f*', $blob);
        return $unpacked ? array_values($unpacked) : [];
    }
}
