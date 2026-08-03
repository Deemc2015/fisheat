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

        $fields = [
            'IBLOCK_ID'       => self::IBLOCK_ID,
            'NAME'            => (string)($item['name'] ?? ''),
            'DETAIL_TEXT'     => (string)($item['description'] ?? ''),
            'CODE'            => $this->uniqueCode((string)$item['name']),
            'ACTIVE'          => 'Y',
            'PROPERTY_VALUES' => [
                'ATT_RK_ID'          => $itemId,
                'ATT_RK_CATEGORY_ID' => $iikoGroupId,
            ],
        ];

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
            }
            return $updated;
        }

        $newId = $element->Add($fields);
        if (!$newId) {
            addMessage2Log('Product::syncItem error: ' . $element->LAST_ERROR);
            return false;
        }

        $this->registerCatalogProduct($newId);
        $this->addPrice($newId, $price);

        return true;
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
     *
     * @param int $idElement
     * @return void
     */
    private function registerCatalogProduct(int $idElement): void
    {
        $row = \CCatalogProduct::GetByID($idElement);
        if ($row) {
            return;
        }

        \CCatalogProduct::add([
            'ID' => $idElement,
            'QUANTITY' => 1000,
        ]);
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
