<?php

declare(strict_types=1);

/**
 * Извлечение SEARCHABLE_CONTENT из b_search_content_text.
 * Джойнит b_search_content + b_search_content_site + b_search_content_text.
 */

namespace Mibazarow\Smartconsultant\Index;

use Bitrix\Main\Application;

class SourceText
{
    /**
     * Извлечь все товары из указанных инфоблоков.
     *
     * @param int[] $iblockIds ID инфоблоков каталога
     * @param string|null $siteId ID сайта (по умолчанию SITE_ID)
     * @return array Массив [{id, item_id, iblock_id, text}, ...]
     */
    public static function extractAll(array $iblockIds, ?string $siteId = null): array
    {
        if (empty($iblockIds)) {
            return [];
        }

        $connection = Application::getConnection();
        $siteId = $siteId ?: SITE_ID;
        $iblockList = implode(',', array_map('intval', $iblockIds));

        $sql = "
            SELECT 
                sc.ID,
                sc.ITEM_ID,
                sc.PARAM2 as IBLOCK_ID,
                sct.SEARCHABLE_CONTENT as TEXT
            FROM b_search_content sc
            INNER JOIN b_search_content_site scs ON sc.ID = scs.SEARCH_CONTENT_ID
            INNER JOIN b_search_content_text sct ON sct.SEARCH_CONTENT_ID = sc.ID
            WHERE scs.SITE_ID = '{$connection->getSqlHelper()->forSql($siteId)}'
              AND sc.MODULE_ID = 'iblock'
              AND sc.PARAM2 IN ({$iblockList})
        ";

        $result = $connection->query($sql);

        $items = [];
        while ($row = $result->fetch()) {
            $text = (string)$row['TEXT'];
            // Обрезаем до ~1024 символов (лимит 512 токенов для multilingual-e5-base)
            $text = mb_substr($text, 0, 1024);

            $items[] = [
                'id' => (int)$row['ID'],
                'item_id' => (int)$row['ITEM_ID'],
                'iblock_id' => (int)$row['IBLOCK_ID'],
                'text' => $text,
            ];
        }

        return $items;
    }
}
