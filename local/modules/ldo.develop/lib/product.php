<?php
namespace Ldo\Develop;

use Bitrix\Main\Loader;
use Ldo\Develop\Iblock;

class Product
{

    public static function getImageById($id)
    {
        if(!empty($id)){
            \CModule::IncludeModule("iblock");

            $res = \CIBlockElement::GetByID($id);

            if($ar_res = $res->GetNext()){
                if($ar_res['PREVIEW_PICTURE']){
                    $file = \CFile::ResizeImageGet($ar_res['PREVIEW_PICTURE'], array('width'=>100, 'height'=>100), BX_RESIZE_IMAGE_PROPORTIONAL, true);
                    return $file['src'];
                }
            }
        }
    }

    public static function getLinkById($id):string
    {
        if(!empty($id)){
            \CModule::IncludeModule("iblock");

            $res = \CIBlockElement::GetByID($id);

            if($ar_res = $res->GetNext()){
                if($ar_res['DETAIL_PAGE_URL']){
                    return $ar_res['DETAIL_PAGE_URL'];
                }
            }
        }
    }

    public static function getDataById($id)
    {
        if(!empty($id)){
            \CModule::IncludeModule("iblock");

            $res = \CIBlockElement::GetByID($id);

            if($ar_res = $res->GetNext()){
                return  $ar_res;
            }
        }
    }

    /**
     * Правила бесплатных позиций, применимые к разделу каталога.
     * Данные берутся из таблицы ldo_marketing_free_positions (модуль ldo.marketing).
     *
     * @param int $sectionProduct ID раздела каталога
     * @return array список правил: [['IDS' => [id, ...], 'PORTION' => int], ...]
     */
    public static function checkInFreeCategoryProducts($sectionProduct)
    {
        $sectionProduct = (int)$sectionProduct;
        if ($sectionProduct <= 0) {
            return [];
        }

        $rules = [];

        foreach (self::getFreePositionRules() as $row) {
            $sectionIds = self::decodeFreePositionIds($row['SECTION_IDS'] ?? '');
            if (!in_array($sectionProduct, $sectionIds, true)) {
                continue;
            }

            $productIds = self::decodeFreePositionIds($row['PRODUCT_IDS'] ?? '');
            if (empty($productIds)) {
                continue;
            }

            $portion = (int)($row['PORTIONS'] ?? 0);
            if ($portion <= 0) {
                $portion = 1;
            }

            $rules[] = [
                'IDS'     => $productIds,
                'PORTION' => $portion,
            ];
        }

        return $rules;
    }

    /**
     * Является ли товар бесплатной позицией (есть в PRODUCT_IDS любого правила).
     *
     * @param int $productId
     * @return bool
     */
    public static function isFreeProduct($productId): bool
    {
        $productId = (int)$productId;
        if ($productId <= 0) {
            return false;
        }

        foreach (self::getFreePositionRules() as $row) {
            if (in_array($productId, self::decodeFreePositionIds($row['PRODUCT_IDS'] ?? ''), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Все правила бесплатных позиций для указанного сайта.
     *
     * @param string $siteId
     * @return array
     */
    public static function getFreePositionRulesBySite(string $siteId): array
    {
        // Модуль ldo.marketing: подключаем с фолбэком на прямой require класса.
        if (!Loader::includeModule('ldo.marketing')
            && !class_exists('\Ldo\Marketing\FreePositionsTable')) {
            $file = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/local/modules/ldo.marketing/lib/FreePositionsTable.php';
            if (is_file($file)) {
                require_once $file;
            }
        }

        if (!class_exists('\Ldo\Marketing\FreePositionsTable')) {
            return [];
        }

        try {
            $rows = \Ldo\Marketing\FreePositionsTable::getList([
                'filter' => ['=SITE_ID' => $siteId],
                'order'  => ['ID' => 'ASC'],
            ])->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }

        return $rows ?: [];
    }

    /**
     * Все правила бесплатных позиций для текущего сайта.
     *
     * @return array
     */
    private static function getFreePositionRules(): array
    {
        return self::getFreePositionRulesBySite(self::getCurrentSiteId());
    }

    /**
     * Декодирование JSON-массива ID из таблицы.
     *
     * @param mixed $raw
     * @return array
     */
    public static function decodeFreePositionIds($raw): array
    {
        if (is_array($raw)) {
            $data = $raw;
        } else {
            $raw = trim((string)$raw);
            if ($raw === '') {
                return [];
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                return [];
            }
        }

        $ids = [];
        foreach ($data as $value) {
            $value = (int)$value;
            if ($value > 0) {
                $ids[$value] = $value;
            }
        }

        return array_values($ids);
    }

    /**
     * Разворачивает список ID разделов, добавляя вложенные подразделы.
     *
     * @param array $sectionIds
     * @return array
     */
    public static function expandSectionIds(array $sectionIds): array
    {
        $sectionIds = array_values(array_unique(array_map('intval', $sectionIds)));
        if (empty($sectionIds) || !Loader::includeModule('iblock')) {
            return $sectionIds;
        }

        $result = $sectionIds;
        $queue = $sectionIds;
        $guard = 0;

        while (!empty($queue) && $guard < 50) {
            $guard++;
            $parent = array_shift($queue);

            $rs = \CIBlockSection::GetList(
                [],
                ['IBLOCK_SECTION_ID' => $parent, 'CHECK_PERMISSIONS' => 'N'],
                false,
                ['ID']
            );
            while ($section = $rs->Fetch()) {
                $id = (int)$section['ID'];
                if (!in_array($id, $result, true)) {
                    $result[] = $id;
                    $queue[] = $id;
                }
            }
        }

        return $result;
    }

    /**
     * ID текущего сайта (фолбэк s1).
     *
     * @return string
     */
    private static function getCurrentSiteId(): string
    {
        $siteId = '';
        try {
            $siteId = (string)\Bitrix\Main\Context::getCurrent()->getSite();
        } catch (\Throwable $e) {
            $siteId = '';
        }

        return $siteId !== '' ? $siteId : 's1';
    }



}


