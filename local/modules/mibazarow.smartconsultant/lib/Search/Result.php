<?php

declare(strict_types=1);

/**
 * DTO результата семантического поиска.
 */

namespace Mibazarow\Smartconsultant\Search;

class Result
{
    /** @var int ID элемента инфоблока */
    public int $itemId;

    /** @var int ID инфоблока */
    public int $iblockId;

    /** @var string Название товара */
    public string $name;

    /** @var string URL страницы товара */
    public string $url;

    /** @var string|null URL картинки */
    public ?string $imageUrl;

    /** @var float Релевантность (0.0 – 1.0) */
    public float $similarity;

    /** @var int ID записи в b_search_content */
    public int $searchContentId;

    /**
     * Гидрация результатов: подгрузка URL и названий из b_search_content + инфоблоков.
     *
     * @param array $scores Результат Math::findTopN — [{id, similarity}, ...]
     * @return self[]
     */
    public static function hydrate(array $scores): array
    {
        if (empty($scores)) {
            return [];
        }

        $ids = array_column($scores, 'id');
        $idList = implode(',', array_map('intval', $ids));
        $similarityMap = [];
        foreach ($scores as $s) {
            $similarityMap[(int)$s['id']] = $s['similarity'];
        }

        $connection = \Bitrix\Main\Application::getConnection();

        $sql = "
            SELECT 
                sc.ID as SEARCH_CONTENT_ID,
                sc.ITEM_ID,
                sc.PARAM2 as IBLOCK_ID,
                sc.TITLE
            FROM b_search_content sc
            WHERE sc.ID IN ({$idList})
        ";

        $result = $connection->query($sql);

        $items = [];
        while ($row = $result->fetch()) {
            $searchContentId = (int)$row['SEARCH_CONTENT_ID'];

            $item = new self();
            $item->searchContentId = $searchContentId;
            $item->itemId = (int)$row['ITEM_ID'];
            $item->iblockId = (int)$row['IBLOCK_ID'];
            $item->name = $row['TITLE'];
            $item->url = ''; // Будет заполнено из инфоблока
            $item->imageUrl = null; // Будет заполнено ниже
            $item->similarity = $similarityMap[$searchContentId] ?? 0.0;

            $items[$searchContentId] = $item;
        }

        // Подгружаем URL и картинки из инфоблоков
        if (!empty($items)) {
            $items = self::loadElementData($items);
        }

        // Сортируем по релевантности
        usort($items, function (self $a, self $b) {
            return $b->similarity <=> $a->similarity;
        });

        return $items;
    }

    /**
     * Подгрузка URL (из настроек инфоблока) и картинок товаров.
     * URL берётся через CIBlockElement::GetList с учётом DETAIL_PAGE_URL шаблона.
     *
     * @param self[] $items
     * @return self[]
     */
    private static function loadElementData(array $items): array
    {
        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            return $items;
        }

        // Группируем по инфоблоку
        $byIblock = [];
        foreach ($items as $item) {
            $byIblock[$item->iblockId][] = $item->itemId;
        }

        foreach ($byIblock as $iblockId => $elementIds) {
            $res = \CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => $iblockId, 'ID' => $elementIds],
                false,
                false,
                ['ID', 'DETAIL_PAGE_URL', 'DETAIL_PICTURE', 'PREVIEW_PICTURE']
            );

            $urls = [];
            $images = [];
            while ($el = $res->GetNext()) {
                $id = (int)$el['ID'];

                // URL из настроек инфоблока
                if (!empty($el['DETAIL_PAGE_URL'])) {
                    $urls[$id] = $el['DETAIL_PAGE_URL'];
                }

                // Картинка
                $imageId = $el['DETAIL_PICTURE'] ?: $el['PREVIEW_PICTURE'];
                if ($imageId) {
                    $src = \CFile::GetPath($imageId);
                    if ($src) {
                        $images[$id] = $src;
                    }
                }
            }

            // Присваиваем URL и картинки
            foreach ($items as $item) {
                if ($item->iblockId === $iblockId) {
                    if (isset($urls[$item->itemId])) {
                        $item->url = $urls[$item->itemId];
                    }
                    if (isset($images[$item->itemId])) {
                        $item->imageUrl = $images[$item->itemId];
                    }
                }
            }
        }

        return $items;
    }
}
