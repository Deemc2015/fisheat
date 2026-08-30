<?php

namespace Ldo\Iiko;

use Ldo\Iiko\Auth;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\HttpClient;

class Product
{
    const DATA_URL = 'https://api-ru.iiko.services/api/2/menu/by_id';
    const IBLOCK_ID = 4;
    const PROGRESS_DIR = '/upload/iiko_sync';

    private $externalMenuId = '82646';

    private $restoranId = '415f7533-6201-4d69-b387-dfa7daa954bf';

    private $token;

    private $lastProgressPercent = -1;

    public function __construct($siteId = 's1') {
        $tokenData = new Auth($siteId);
        $this->token = $tokenData->getToken();
    }

    /**
     * Получить разделы (категории) меню из itemCategories.
     * У каждого раздела сохраняется его iikoGroupId.
     *
     * @return array
     */
    public function getCategories(): array
    {
        $categories = $this->makeApiRequest('itemCategories');

        if (!is_array($categories)) {
            return [];
        }

        $result = [];
        foreach ($categories as $category) {
            $result[] = [
                'id'             => $category['id'] ?? '',
                'name'           => $category['name'] ?? '',
                'description'    => $category['description'] ?? '',
                'buttonImageUrl' => $category['buttonImageUrl'] ?? null,
                'headerImageUrl' => $category['headerImageUrl'] ?? null,
                'iikoGroupId'    => $category['iikoGroupId'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * Получить все товары меню (items внутри itemCategories).
     * В каждый товар добавляется iikoGroupId его родительского раздела,
     * а также id и название раздела (categoryId, categoryName).
     *
     * @return array
     */
    public function getItems(): array
    {
        $categories = $this->makeApiRequest('itemCategories');

        if (!is_array($categories)) {
            return [];
        }

        $items = [];
        foreach ($categories as $category) {
            $iikoGroupId = $category['iikoGroupId'] ?? '';
            $categoryId  = $category['id'] ?? '';
            $categoryName = $category['name'] ?? '';

            if (empty($category['items']) || !is_array($category['items'])) {
                continue;
            }

            foreach ($category['items'] as $item) {
                $item['iikoGroupId']  = $iikoGroupId;
                $item['categoryId']   = $categoryId;
                $item['categoryName'] = $categoryName;
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Синхронизировать разделы и товары из iiko в инфоблок (IBLOCK_ID).
     * Прогресс (0-100) записывается в файл для отображения через progress.php.
     *
     * @param string|null $progressToken
     * @return array{sections: int, items: int}
     */
    public function sync($progressToken = null): array
    {
        Loader::includeModule('iblock');
        Loader::includeModule('catalog');

        $categories = $this->getCategories();
        $items = $this->getItems();

        $total = count($categories) + count($items);
        if ($total === 0) {
            $this->reportProgress($progressToken, 100);
            return ['sections' => 0, 'items' => 0];
        }

        $done = 0;
        $sectionMap = [];
        $sectionCount = 0;

        // 1. Разделы
        foreach ($categories as $category) {
            $iikoGroupId = (string)($category['iikoGroupId'] ?? '');
            $sectionId = $this->syncSection($category);
            if ($sectionId) {
                $sectionMap[$iikoGroupId] = $sectionId;
                $sectionCount++;
            }
            $done++;
            $this->reportProgress($progressToken, (int)round($done / $total * 100));
        }

        // 2. Товары
        $itemCount = 0;
        foreach ($items as $item) {
            if ($this->syncItem($item, $sectionMap)) {
                $itemCount++;
            }
            $done++;
            $this->reportProgress($progressToken, (int)round($done / $total * 100));
        }

        $this->reportProgress($progressToken, 100);

        return [
            'sections' => $sectionCount,
            'items' => $itemCount,
        ];
    }

    /**
     * Синхронизация цен из iiko для существующих товаров (тип цены 1, RUB).
     * Метод заточен под запуск по крону и минимизирует нагрузку на БД:
     *  - один запрос к API iiko (itemCategories);
     *  - сопоставление itemId -> элемент инфоблока одним запросом;
     *  - загрузка всех текущих цен одним запросом (без GetList на каждый товар);
     *  - обновляются только цены, которые реально изменились.
     *
     * @return int количество товаров с изменённой ценой
     */
    public function syncPrices(): int
    {
        Loader::includeModule('iblock');
        Loader::includeModule('catalog');

        $items = $this->getItems();
        if (empty($items)) {
            return 0;
        }

        // 1. Карта itemId (ATT_RK_ID) -> ID элемента инфоблока (один запрос)
        $itemIdToElement = [];
        $rs = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_ID],
            false,
            false,
            ['ID', 'PROPERTY_ATT_RK_ID']
        );
        while ($row = $rs->Fetch()) {
            $rkId = (string)($row['PROPERTY_ATT_RK_ID_VALUE'] ?? '');
            if ($rkId !== '') {
                $itemIdToElement[$rkId] = (int)$row['ID'];
            }
        }

        if (empty($itemIdToElement)) {
            return 0;
        }

        // 2. Новая цена по itemId (только для товаров, присутствующих в БД)
        $typePrice = 1;
        $newPrices = []; // itemId => цена
        $elementIds = []; // ID элементов с новой ценой
        foreach ($items as $item) {
            $itemId = (string)($item['itemId'] ?? '');
            if ($itemId === '' || !isset($itemIdToElement[$itemId])) {
                continue;
            }

            $price = $this->extractPrice($item);
            if ($price === null) {
                continue;
            }

            $newPrices[$itemId] = $price;
            $elementIds[] = $itemIdToElement[$itemId];
        }

        if (empty($newPrices)) {
            return 0;
        }

        // 3. Текущие цены в БД одним запросом: elementId => [ID записи, PRICE]
        $currentPrices = [];
        $rs = \CPrice::GetList(
            [],
            ['PRODUCT_ID' => $elementIds, 'CATALOG_GROUP_ID' => $typePrice]
        );
        while ($row = $rs->Fetch()) {
            $currentPrices[(int)$row['PRODUCT_ID']] = [
                'ID'    => (int)$row['ID'],
                'PRICE' => (float)$row['PRICE'],
            ];
        }

        // 4. Обновляем только изменившиеся цены
        $updated = 0;
        foreach ($newPrices as $itemId => $price) {
            $elementId = $itemIdToElement[$itemId];
            $current = $currentPrices[$elementId] ?? null;

            if ($current !== null && abs($current['PRICE'] - $price) < 0.005) {
                continue;
            }

            $arFields = [
                'PRODUCT_ID'       => $elementId,
                'CATALOG_GROUP_ID' => $typePrice,
                'PRICE'            => $price,
                'CURRENCY'         => 'RUB',
            ];

            if ($current !== null) {
                \CPrice::Update($current['ID'], $arFields);
            } else {
                \CPrice::Add($arFields);
            }

            $updated++;
        }

        return $updated;
    }

    /**
     * Создать или обновить раздел инфоблока.
     * iikoGroupId хранится в UF_ID_RK.
     *
     * @param array $category
     * @return int|null
     */
    private function syncSection(array $category): ?int
    {
        $iikoGroupId = (string)($category['iikoGroupId'] ?? '');
        if ($iikoGroupId === '') {
            return null;
        }

        $fields = [
            'IBLOCK_ID' => self::IBLOCK_ID,
            'NAME'      => (string)($category['name'] ?? ''),
            'CODE'      => $this->uniqueCode((string)($category['name'] ?? ''), $iikoGroupId),
            'UF_ID_RK'  => $iikoGroupId,
            'ACTIVE'    => 'Y',
        ];

        $sectionId = $this->isSectionExists($iikoGroupId);
        $section = new \CIBlockSection();

        if ($sectionId) {
            $section->Update($sectionId, $fields);
            return $sectionId;
        }

        $sectionId = $section->Add($fields);
        if (!$sectionId) {
            addMessage2Log('Product::syncSection error: ' . $section->LAST_ERROR);
            return null;
        }

        return (int)$sectionId;
    }

    /**
     * Найти раздел инфоблока по iikoGroupId (UF_ID_RK).
     *
     * @param string $iikoGroupId
     * @return int|null
     */
    private function isSectionExists(string $iikoGroupId): ?int
    {
        $rs = \CIBlockSection::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_ID, '=UF_ID_RK' => $iikoGroupId],
            false,
            ['ID', 'NAME', 'UF_ID_RK']
        );

        if ($row = $rs->Fetch()) {
            return (int)$row['ID'];
        }

        return null;
    }

    /**
     * Создать или обновить товар в инфоблоке.
     * Уникальный ключ — itemId (свойство ATT_RK_ID), iikoGroupId хранится
     * в свойстве ATT_RK_CATEGORY_ID, раздел привязывается по iikoGroupId.
     * Помимо цены сохраняются вес (itemSizes[].portionWeightGrams -> WEIGHT
     * торгового каталога и свойство ATT_VES) и калорийность
     * (nutritions[] -> ATT_KALLORY, ATT_BELKI, ATT_GIRY, ATT_YGLEVODY).
     *
     * @param array $item
     * @param array $sectionMap
     * @return bool
     */
    private function syncItem(array $item, array $sectionMap): bool
    {
        $itemId = (string)($item['itemId'] ?? '');
        if ($itemId === '') {
            return false;
        }

        $iikoGroupId = (string)($item['iikoGroupId'] ?? '');
        $sectionId = isset($sectionMap[$iikoGroupId]) ? (int)$sectionMap[$iikoGroupId] : null;

        $price = $this->extractPrice($item);
        $weight = $this->extractWeight($item);
        $nutrition = $this->extractNutrition($item);
        $labels = $this->extractLabels($item);

        $propertyValues = [
            'ATT_RK_ID'          => $itemId,
            'ATT_RK_CATEGORY_ID' => $iikoGroupId,
            // Метки товара (labels[].name) во множественное свойство ATT_PLASHKA
            'ATT_PLASHKA'        => $labels,
        ];

        // Калорийность товара: энергия, белки, жиры, углеводы
        foreach ($this->nutritionPropertyMap() as $propertyCode => $key) {
            if ($nutrition[$key] !== null) {
                $propertyValues[$propertyCode] = $nutrition[$key];
            }
        }

        // Вес товара в виде строки "135 г" (свойство ATT_VES)
        if ($weight !== null) {
            $propertyValues['ATT_VES'] = $weight . ' г';
        }

        $fields = [
            'IBLOCK_ID'       => self::IBLOCK_ID,
            'NAME'            => (string)($item['name'] ?? ''),
            'DETAIL_TEXT'     => (string)($item['description'] ?? ''),
            'CODE'            => $this->uniqueCode((string)$item['name']),
            'ACTIVE'          => 'Y',
            'PROPERTY_VALUES' => $propertyValues,
        ];


        addMessage2Log($propertyValues);

        if ($sectionId) {
            $fields['IBLOCK_SECTION_ID'] = $sectionId;
        }

        $existId = $this->isItemExists($itemId);
        $element = new \CIBlockElement();

        if ($existId) {
            $updated = (bool)$element->Update($existId, $fields);
            if ($updated) {
                $this->registerCatalogProduct($existId);
                $this->addPrice($existId, $price);
                $this->updateCatalogProductWeight($existId, $weight);
            }
            return $updated;
        }

        $newId = $element->Add($fields);
        if (!$newId) {
            addMessage2Log('Product::syncItem error: ' . $element->LAST_ERROR);
            return false;
        }

        $this->registerCatalogProduct($newId, $weight);
        $this->addPrice($newId, $price);

        return true;
    }

    /**
     * Извлечь названия меток товара из labels[].name
     * (для множественного свойства ATT_PLASHKA).
     *
     * @param array $item
     * @return array
     */
    private function extractLabels(array $item): array
    {
        if (empty($item['labels']) || !is_array($item['labels'])) {
            return [];
        }

        $labels = [];
        foreach ($item['labels'] as $label) {
            if (!is_array($label)) {
                continue;
            }

            $name = (string)($label['name'] ?? '');
            if ($name !== '') {
                $labels[] = $name;
            }
        }

        return $labels;
    }

    /**
     * Соответствие свойств инфоблока ключам питательной ценности iiko.
     *
     * @return array<string, string>
     */
    private function nutritionPropertyMap(): array
    {
        return [
            'ATT_KALLORY'  => 'energy',
            'ATT_BELKI'    => 'proteins',
            'ATT_GIRY'     => 'fats',
            'ATT_YGLEVODY' => 'carbs',
        ];
    }

    /**
     * Извлечь вес товара из itemSizes[].portionWeightGrams (граммы).
     * Приоритет: размер с isDefault=true, иначе первый размер с указанным весом.
     *
     * @param array $item
     * @return float|null
     */
    private function extractWeight(array $item): ?float
    {
        if (empty($item['itemSizes']) || !is_array($item['itemSizes'])) {
            return null;
        }

        $fallbackWeight = null;

        foreach ($item['itemSizes'] as $size) {
            if (!isset($size['portionWeightGrams'])) {
                continue;
            }

            $weight = (float)$size['portionWeightGrams'];

            if ($fallbackWeight === null) {
                $fallbackWeight = $weight;
            }

            // Приоритет — размер по умолчанию
            if (!empty($size['isDefault'])) {
                return $weight;
            }
        }

        return $fallbackWeight;
    }

    /**
     * Извлечь калорийность (КБЖУ) товара из itemSizes[].nutritions[].
     * В ответе /menu/by_id питательная ценность привязана к размеру товара:
     * у каждого элемента itemSizes есть nutritions[] (для порции) и
     * nutritionPerHundredGrams (на 100 г). Приоритет — размер с isDefault=true,
     * иначе первый размер с данными.
     *
     * @param array $item
     * @return array{energy: ?float, proteins: ?float, fats: ?float, carbs: ?float}
     */
    private function extractNutrition(array $item): array
    {
        $empty = ['energy' => null, 'proteins' => null, 'fats' => null, 'carbs' => null];

        if (empty($item['itemSizes']) || !is_array($item['itemSizes'])) {
            return $empty;
        }

        $fallback = null;

        foreach ($item['itemSizes'] as $size) {
            if (!is_array($size)) {
                continue;
            }

            $nutritionRow = $this->findNutrition($size);
            if ($nutritionRow === null) {
                continue;
            }

            if ($fallback === null) {
                $fallback = $nutritionRow;
            }

            // Приоритет — размер по умолчанию
            if (!empty($size['isDefault'])) {
                return $this->mapNutrition($nutritionRow);
            }
        }

        return $fallback === null ? $empty : $this->mapNutrition($fallback);
    }

    /**
     * Получить данные КБЖУ из размера товара.
     * Сначала nutritions[] для нужного ресторана (или первый элемент),
     * затем nutritionPerHundredGrams как запасной источник.
     *
     * @param array $size
     * @return array|null
     */
    private function findNutrition(array $size): ?array
    {
        // Питательная ценность порции (itemSizes[].nutritions[])
        if (!empty($size['nutritions']) && is_array($size['nutritions'])) {
            $fallback = null;

            foreach ($size['nutritions'] as $nutrition) {
                if (!is_array($nutrition)) {
                    continue;
                }

                if ($fallback === null) {
                    $fallback = $nutrition;
                }

                // Приоритет — питательная ценность для нужного ресторана
                if (!empty($nutrition['organizations']) && in_array($this->restoranId, $nutrition['organizations'], true)) {
                    return $nutrition;
                }
            }

            if ($fallback !== null) {
                return $fallback;
            }
        }

        // Питательная ценность на 100 г (itemSizes[].nutritionPerHundredGrams)
        if (!empty($size['nutritionPerHundredGrams']) && is_array($size['nutritionPerHundredGrams'])) {
            return $size['nutritionPerHundredGrams'];
        }

        return null;
    }

    /**
     * Привести элемент nutritions к массиву КБЖУ.
     *
     * @param array $nutrition
     * @return array{energy: ?float, proteins: ?float, fats: ?float, carbs: ?float}
     */
    private function mapNutrition(array $nutrition): array
    {
        return [
            'energy'   => isset($nutrition['energy']) ? (float)$nutrition['energy'] : null,
            'proteins' => isset($nutrition['proteins']) ? (float)$nutrition['proteins'] : null,
            'fats'     => isset($nutrition['fats']) ? (float)$nutrition['fats'] : null,
            'carbs'    => isset($nutrition['carbs']) ? (float)$nutrition['carbs'] : null,
        ];
    }

    /**
     * Извлечь цену товара из itemSizes[].prices[].
     * Приоритет: размер с isDefault=true, иначе первый размер с ценой.
     * Фильтр: organizationId === restoranId.
     *
     * @param array $item
     * @return float|null
     */
    private function extractPrice(array $item): ?float
    {
        if (empty($item['itemSizes']) || !is_array($item['itemSizes'])) {
            return null;
        }

        $fallbackPrice = null;

        foreach ($item['itemSizes'] as $size) {
            if (empty($size['prices']) || !is_array($size['prices'])) {
                continue;
            }

            $sizePrice = null;
            foreach ($size['prices'] as $priceRow) {
                if (isset($priceRow['organizationId']) && $priceRow['organizationId'] === $this->restoranId) {
                    $sizePrice = (float)$priceRow['price'];
                    break;
                }
            }

            if ($sizePrice === null) {
                continue;
            }

            if ($fallbackPrice === null) {
                $fallbackPrice = $sizePrice;
            }

            // Приоритет — размер по умолчанию
            if (!empty($size['isDefault'])) {
                return $sizePrice;
            }
        }

        return $fallbackPrice;
    }

    /**
     * Зарегистрировать товар в торговом каталоге (b_catalog_product).
     * Вес (граммы) передаётся сразу при создании записи, чтобы не выполнять
     * дополнительный UPDATE для новых товаров.
     *
     * @param int $idElement
     * @param float|string|null $weight
     * @return void
     */
    private function registerCatalogProduct(int $idElement, $weight = null): void
    {
        $row = \CCatalogProduct::GetByID($idElement);
        if ($row) {
            return;
        }

        $fields = [
            'ID' => $idElement,
            'QUANTITY' => 1000,
        ];

        // Вес передаётся сразу при создании, чтобы не делать отдельный UPDATE
        if ($weight !== null && is_numeric($weight)) {
            $fields['WEIGHT'] = (float)$weight;
        }

        \CCatalogProduct::add($fields);
    }

    /**
     * Добавить или обновить цену товара (тип цены 1, валюта RUB).
     *
     * @param int $idElement
     * @param float|string|null $price
     * @return void
     */
    private function addPrice(int $idElement, $price): void
    {
        if ($price === null || $price === '' || !is_numeric($price)) {
            return;
        }

        $typePrice = 1;

        $arFields = [
            'PRODUCT_ID' => $idElement,
            'CATALOG_GROUP_ID' => $typePrice,
            'PRICE' => (float)$price,
            'CURRENCY' => 'RUB',
        ];

        $res = \CPrice::GetList(
            [],
            ['PRODUCT_ID' => $idElement, 'CATALOG_GROUP_ID' => $typePrice]
        );

        if ($arr = $res->Fetch()) {
            \CPrice::Update($arr['ID'], $arFields);
        } else {
            \CPrice::Add($arFields);
        }
    }

    /**
     * Обновить вес товара в торговом каталоге (b_catalog_product.WEIGHT, граммы).
     * Используется для вывода "Вес, г" в карточке товара и в корзине.
     *
     * @param int $idElement
     * @param float|string|null $weight
     * @return void
     */
    private function updateCatalogProductWeight(int $idElement, $weight): void
    {
        if ($weight === null || $weight === '' || !is_numeric($weight)) {
            return;
        }

        \CCatalogProduct::Update($idElement, [
            'WEIGHT' => (float)$weight,
        ]);
    }

    /**
     * Найти товар в инфоблоке по itemId (свойство ATT_RK_ID).
     *
     * @param string $itemId
     * @return int|null
     */
    private function isItemExists(string $itemId): ?int
    {
        $rs = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_ID, '=PROPERTY_ATT_RK_ID' => $itemId],
            false,
            false,
            ['ID', 'NAME']
        );

        if ($row = $rs->Fetch()) {
            return (int)$row['ID'];
        }

        return null;
    }

    /**
     * Уникальный символьный код из названия.
     *
     * @param string $name
     * @return string
     */
    private function uniqueCode(string $name): string
    {
        $base = \CUtil::translit($name, 'ru', [
            'replace_space' => '-',
            'replace_other' => '-',
        ]);

        return $base;
    }

    /**
     * Записать процент прогресса синхронизации (пишется только при изменении).
     *
     * @param string|null $token
     * @param int $percent
     * @return void
     */
    private function reportProgress($token, int $percent): void
    {
        if ($token === null || $token === '') {
            return;
        }

        if ($percent === $this->lastProgressPercent) {
            return;
        }
        $this->lastProgressPercent = $percent;

        $file = self::getProgressFilePath((string)$token);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($file, $percent, LOCK_EX);
    }

    /**
     * Путь к файлу прогресса (совпадает с logic в partners/menu/progress.php).
     *
     * @param string $token
     * @return string
     */
    private static function getProgressFilePath(string $token): string
    {
        return Application::getDocumentRoot() . self::PROGRESS_DIR . '/' . md5($token) . '.txt';
    }

    /**
     * Выполнить запрос к API iiko и вернуть секцию ответа по ключу.
     *
     * @param string $dataType
     * @return array
     */
    private function makeApiRequest(string $dataType): array
    {
        if (!$this->token) {
            return [];
        }

        $httpClient = new HttpClient();
        $httpClient->setHeader('Authorization', 'Bearer ' . $this->token);
        $httpClient->setHeader('Content-Type', 'application/json');

        try {
            $response = $httpClient->post(
                self::DATA_URL,
                json_encode([
                    'externalMenuId' => $this->externalMenuId,
                    'organizationIds' => [$this->restoranId],
                ])
            );

            if ($response === false) {
                return [];
            }

            $result = json_decode($response, true);

            if (isset($result[$dataType])) {
                return $result[$dataType];
            }

            return [];

        } catch (\Exception $e) {
            addMessage2Log('Product::makeApiRequest error: ' . $e->getMessage());
            return [];
        }
    }
}
