<?php
namespace Ldo\Develop;

use Bitrix\Main\Loader;
use Ldo\Develop\Iblock;

class Product
{
    /** Кэш правил бесплатных позиций на время запроса: SITE_ID => строки. */
    private static $freePositionRulesCache = [];

    /** Кэш развёрнутых ID разделов: ключ из ID => список разделов с подразделами. */
    private static $expandedSectionsCache = [];

    /** Кэш ID инфоблока каталога. */
    private static $catalogIblockId = null;

    /** Кэш ID родительского товара для SKU: productId => parentId. */
    private static $skuParentCache = [];

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
        if (array_key_exists($siteId, self::$freePositionRulesCache)) {
            return self::$freePositionRulesCache[$siteId];
        }

        self::includeMarketingModule();

        if (!class_exists('\Ldo\Marketing\FreePositionsTable')) {
            return self::$freePositionRulesCache[$siteId] = [];
        }

        try {
            $rows = \Ldo\Marketing\FreePositionsTable::getList([
                'filter' => ['=SITE_ID' => $siteId],
                'order'  => ['ID' => 'ASC'],
            ])->fetchAll();
        } catch (\Throwable $e) {
            return self::$freePositionRulesCache[$siteId] = [];
        }

        return self::$freePositionRulesCache[$siteId] = ($rows ?: []);
    }

    /**
     * Подключение модуля маркетинга с фолбэком на прямое подключение классов.
     *
     * @return void
     */
    public static function includeMarketingModule(): void
    {
        if (Loader::includeModule('ldo.marketing')) {
            return;
        }

        $root = $_SERVER['DOCUMENT_ROOT'] ?? '';
        foreach (['FreePositionsTable', 'GiftsTable', 'Settings'] as $class) {
            $fqcn = '\\Ldo\\Marketing\\' . $class;
            if (class_exists($fqcn)) {
                continue;
            }
            $file = $root . '/local/modules/ldo.marketing/lib/' . $class . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
    }

    /**
     * ID инфоблока каталога из настроек модуля ldo.marketing (фолбэк — 4).
     *
     * @return int
     */
    public static function getCatalogIblockId(): int
    {
        if (self::$catalogIblockId !== null) {
            return self::$catalogIblockId;
        }

        self::includeMarketingModule();

        $id = 0;
        if (class_exists('\Ldo\Marketing\Settings')) {
            try {
                $id = (int)\Ldo\Marketing\Settings::getCatalogIblockId();
            } catch (\Throwable $e) {
                $id = 0;
            }
        }

        return self::$catalogIblockId = ($id > 0 ? $id : 4);
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
        if (empty($sectionIds)) {
            return $sectionIds;
        }

        $cacheKey = implode(',', $sectionIds);
        if (isset(self::$expandedSectionsCache[$cacheKey])) {
            return self::$expandedSectionsCache[$cacheKey];
        }

        if (!Loader::includeModule('iblock')) {
            return self::$expandedSectionsCache[$cacheKey] = $sectionIds;
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

        return self::$expandedSectionsCache[$cacheKey] = $result;
    }

    /**
     * Пакетно возвращает разделы товаров: сначала собственные разделы, а для
     * торговых предложений (SKU) — дополнительно разделы родительского товара.
     * Это нужно, чтобы правила, заданные по разделу каталога, срабатывали и для
     * товаров, у которых этот раздел не является основным, и для SKU.
     *
     * @param array $productIds
     * @return array productId => [sectionId, ...]
     */
    public static function getElementsSectionsMap(array $productIds): array
    {
        $ids = [];
        foreach ($productIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if (empty($ids)) {
            return [];
        }

        $ids = array_values($ids);
        $map = self::loadSectionsForElements($ids);

        // Для SKU добавляем разделы родительского товара.
        $parentOf = [];
        $parentIds = [];
        foreach ($ids as $id) {
            $parentId = self::getSkuParentId($id);
            if ($parentId > 0 && $parentId !== $id) {
                $parentOf[$id] = $parentId;
                $parentIds[$parentId] = $parentId;
            }
        }

        if (!empty($parentIds)) {
            $parentMap = self::loadSectionsForElements(array_values($parentIds));
            foreach ($parentOf as $id => $parentId) {
                if (empty($parentMap[$parentId])) {
                    continue;
                }
                foreach ($parentMap[$parentId] as $sectionId) {
                    $map[$id][$sectionId] = $sectionId;
                }
            }
        }

        $result = [];
        foreach ($map as $id => $sections) {
            $result[$id] = array_values($sections);
        }

        return $result;
    }

    /**
     * Разделы элементов одним-двумя запросами (основной раздел + все привязки).
     *
     * @param array $ids
     * @return array elementId => [sectionId => sectionId]
     */
    private static function loadSectionsForElements(array $ids): array
    {
        $result = [];
        foreach ($ids as $id) {
            $result[(int)$id] = [];
        }

        if (empty($ids)) {
            return $result;
        }

        \CModule::IncludeModule('iblock');

        if (class_exists('\Bitrix\Iblock\ElementTable')) {
            try {
                $rs = \Bitrix\Iblock\ElementTable::getList([
                    'filter' => ['=ID' => array_values($ids)],
                    'select' => ['ID', 'IBLOCK_SECTION_ID'],
                ]);
                while ($row = $rs->fetch()) {
                    $id = (int)$row['ID'];
                    $sectionId = (int)$row['IBLOCK_SECTION_ID'];
                    if ($sectionId > 0 && isset($result[$id])) {
                        $result[$id][$sectionId] = $sectionId;
                    }
                }
            } catch (\Throwable $e) {
                // игнорируем — ниже попробуем через SectionElementTable
            }
        }

        if (class_exists('\Bitrix\Iblock\SectionElementTable')) {
            try {
                $rs = \Bitrix\Iblock\SectionElementTable::getList([
                    'filter' => ['=IBLOCK_ELEMENT_ID' => array_values($ids)],
                    'select' => ['IBLOCK_ELEMENT_ID', 'IBLOCK_SECTION_ID', 'ADDITIONAL_PROPERTY_ID'],
                ]);
                while ($row = $rs->fetch()) {
                    // служебные секции, используемые для хранения свойств, пропускаем
                    if (!empty($row['ADDITIONAL_PROPERTY_ID'])) {
                        continue;
                    }
                    $id = (int)$row['IBLOCK_ELEMENT_ID'];
                    $sectionId = (int)$row['IBLOCK_SECTION_ID'];
                    if ($sectionId > 0 && isset($result[$id])) {
                        $result[$id][$sectionId] = $sectionId;
                    }
                }
            } catch (\Throwable $e) {
                // разделы элемента могут отсутствовать — не критично
            }
        }

        return $result;
    }

    /**
     * ID родительского товара для торгового предложения (0 — если не SKU).
     *
     * @param int $productId
     * @return int
     */
    private static function getSkuParentId(int $productId): int
    {
        if ($productId <= 0) {
            return 0;
        }

        if (array_key_exists($productId, self::$skuParentCache)) {
            return self::$skuParentCache[$productId];
        }

        if (!Loader::includeModule('catalog')) {
            return self::$skuParentCache[$productId] = 0;
        }

        $parentId = 0;
        try {
            $info = \CCatalogSku::GetProductInfo($productId);
            if (is_array($info) && !empty($info['ID'])) {
                $parentId = (int)$info['ID'];
            }
        } catch (\Throwable $e) {
            $parentId = 0;
        }

        return self::$skuParentCache[$productId] = $parentId;
    }

    /**
     * Пакетно возвращает значения свойства для списка товаров.
     * Для SKU, если у самого предложения значения нет, берётся значение родителя.
     *
     * @param array  $productIds
     * @param int    $iblockId
     * @param string $propertyCode
     * @return array productId => int
     */
    public static function getElementsPropertyMap(array $productIds, int $iblockId, string $propertyCode): array
    {
        $ids = [];
        foreach ($productIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if (empty($ids) || $iblockId <= 0 || $propertyCode === '') {
            return [];
        }

        $ids = array_values($ids);
        $map = self::loadPropertyValuesForElements($ids, $iblockId, $propertyCode);

        // Для SKU без собственного значения пробуем родителя.
        $parentOf = [];
        $parentIds = [];
        foreach ($ids as $id) {
            if (array_key_exists($id, $map) && $map[$id] > 0) {
                continue;
            }
            $parentId = self::getSkuParentId($id);
            if ($parentId > 0 && $parentId !== $id) {
                $parentOf[$id] = $parentId;
                $parentIds[$parentId] = $parentId;
            }
        }

        if (!empty($parentIds)) {
            $parentMap = self::loadPropertyValuesForElements(array_values($parentIds), $iblockId, $propertyCode);
            foreach ($parentOf as $id => $parentId) {
                if (!empty($parentMap[$parentId])) {
                    $map[$id] = (int)$parentMap[$parentId];
                }
            }
        }

        return $map;
    }

    /**
     * Значения свойства элементов одним запросом.
     *
     * @param array  $ids
     * @param int    $iblockId
     * @param string $propertyCode
     * @return array elementId => int
     */
    private static function loadPropertyValuesForElements(array $ids, int $iblockId, string $propertyCode): array
    {
        $result = [];
        if (empty($ids)) {
            return $result;
        }

        \CModule::IncludeModule('iblock');

        $propertyIds = [];
        try {
            // Сначала ищем свойство в указанном инфоблоке, иначе — по коду во всех
            // инфоблоках (важно для SKU, где свойство лежит в инфоблоке предложений).
            $property = \CIBlockProperty::GetList([], [
                'IBLOCK_ID' => $iblockId,
                'CODE'      => $propertyCode,
            ])->Fetch();
            if ($property) {
                $propertyIds[] = (int)$property['ID'];
            } else {
                $rsProps = \CIBlockProperty::GetList([], ['CODE' => $propertyCode]);
                while ($row = $rsProps->Fetch()) {
                    $propertyIds[] = (int)$row['ID'];
                }
            }
        } catch (\Throwable $e) {
            $propertyIds = [];
        }

        $propertyIds = array_values(array_filter(array_unique($propertyIds)));
        if (empty($propertyIds) || !class_exists('\Bitrix\Iblock\ElementPropertyTable')) {
            return $result;
        }

        try {
            $rs = \Bitrix\Iblock\ElementPropertyTable::getList([
                'filter' => [
                    '=IBLOCK_PROPERTY_ID' => $propertyIds,
                    '=IBLOCK_ELEMENT_ID'  => array_values($ids),
                ],
                'select' => ['IBLOCK_ELEMENT_ID', 'VALUE'],
            ]);
            while ($row = $rs->fetch()) {
                $id = (int)$row['IBLOCK_ELEMENT_ID'];
                $value = (int)$row['VALUE'];
                if ($id > 0 && $value > 0) {
                    $result[$id] = $value;
                }
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $result;
    }

    /**
     * Базовая (каталожная) цена товара за единицу.
     *
     * @param int    $productId
     * @param string $currency
     * @return float
     */
    public static function getProductBasePrice(int $productId, string $currency = ''): float
    {
        if ($productId <= 0) {
            return 0.0;
        }

        \CModule::IncludeModule('catalog');

        try {
            $price = \CPrice::GetBasePrice($productId, 1, $currency !== '' ? $currency : false);
        } catch (\Throwable $e) {
            $price = false;
        }

        if (is_array($price) && isset($price['PRICE'])) {
            return (float)$price['PRICE'];
        }

        // Фолбэк: оптимальная цена (для товаров без явной базовой цены).
        try {
            $optimal = \CCatalogProduct::GetOptimalPrice($productId, 1, []);
        } catch (\Throwable $e) {
            $optimal = false;
        }
        if (is_array($optimal)) {
            if (isset($optimal['BASE_PRICE'])) {
                return (float)$optimal['BASE_PRICE'];
            }
            if (isset($optimal['PRICE'])) {
                return (float)$optimal['PRICE'];
            }
        }

        return 0.0;
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


