<?php

namespace Ldo\Iiko;

use Ldo\Iiko\Auth;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Loader;


Loader::includeModule("iblock");
Loader::includeModule('catalog');

class Product
{
    const DATA_URL = 'https://api-ru.iiko.services/api/2/menu/by_id';
    const IBLOCK_ID = 4;

    private $externalMenuId = "82646";

    private $restoranId = "415f7533-6201-4d69-b387-dfa7daa954bf";

    private $token;

    public function __construct() {
        $tokenData = new Auth();
        $this->token = $tokenData->getToken();
    }

    /**
     * Получить категории товаров (из секции productCategories ответа API)
     */
    public function getCategory(): array
    {
        return $this->makeApiRequest('productCategories');
    }

    /**
     * Получить плоский список всех товаров, собрав их из вложенных items внутри itemCategories
     */
    public function getList(): array
    {
        $itemCategories = $this->makeApiRequest('itemCategories');
        $items = [];

        if (is_array($itemCategories)) {
            foreach ($itemCategories as $category) {
                if (isset($category['items']) && is_array($category['items'])) {
                    foreach ($category['items'] as $item) {
                        $item['_categoryName'] = $category['name'];
                        $item['_categoryId'] = $category['id'];
                        $items[] = $item;
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Синхронизация категорий (productCategories)
     */
    public function syncCategory(){
        $categoryList = $this->getCategory();
        if(is_array($categoryList)){
            foreach ($categoryList as $category){
                $checkExist = $this->checkCategoryByIdRk($category['id']);
                if(!$checkExist){
                    $this->addCategory($category);
                }
            }
        }
    }

    /**
     * Проверить существование категории (секции инфоблока) по UF_ID_RK
     */
    public function checkCategoryByIdRk(string $idRk)
    {
        $arFilter = [
            'IBLOCK_ID' => self::IBLOCK_ID,
            'GLOBAL_ACTIVE' => 'Y',
            'UF_ID_RK' => $idRk
        ];

        $objCat = \CIBlockSection::GetList(["SORT"=>"ASC"], $arFilter, false, ['NAME','ID','UF_ID_RK']);

        if($ar_result = $objCat->GetNext())
        {
            $isExist = $ar_result;
        }

        if(isset($isExist)){
            return $isExist['ID'];
        }

        return null;
    }

    /**
     * Добавить категорию (секцию инфоблока)
     */
    public function addCategory(array $dataCategory)
    {
        if(!$dataCategory){
            return false;
        }

        $codeElement = $this->generateCode($dataCategory['name']);

        $arFields = [
            'IBLOCK_ID' => self::IBLOCK_ID,
            'NAME' => $dataCategory['name'],
            'UF_ID_RK' => $dataCategory['id'],
            'CODE' => $codeElement
        ];

        if (isset($dataCategory['parentId'])) {
            $arFields['UF_PARENT_ID'] = $dataCategory['parentId'];
        }

        $dataObSection = new \CIBlockSection;

        $addResult = $dataObSection->Add($arFields, true);

        if($addResult > 0){
            return true;
        }

        $errorMessage = $dataObSection->LAST_ERROR;
        if ($errorMessage) {
            addMessage2Log('Product::addCategory error: ' . $errorMessage);
        }

        return false;
    }

    /**
     * Основной метод синхронизации товаров.
     * Предварительно синхронизирует категории (productCategories).
     */
    public function sync(){
        $this->syncCategory();

        $productList = $this->getList();
        if($productList){
            $i = 0;
            foreach ($productList as $product){
                $i++;

                $idProduct = $this->isExist($product['itemId']);

                if($idProduct){
                    $this->update($product, $idProduct);
                }
                else{
                    $this->add($product);
                }

                if($i == 1000){
                    break;
                }
            }
        }
    }

    /**
     * Проверить существование товара по PROPERTY_ATT_RK_ID
     */
    public function isExist(string $idRk)
    {
        $obCatalog = \CIBlockElement::GetList (
            ["ID" => "ASC"],
            ["IBLOCK_ID" => self::IBLOCK_ID, "ACTIVE" => "Y","PROPERTY_ATT_RK_ID" => $idRk],
            false,
            false,
            ['ID','NAME']
        );

        if($arServ = $obCatalog->GetNext())
        {
            return $arServ['ID'];
        }

        return false;
    }

    /**
     * Добавить новый товар
     */
    public function add($dataElement){
        $element = new \CIBlockElement;
        $dataProduct = $this->prepareProductData($dataElement);
        $codeElement = $this->generateCode($dataElement['name']);

        $arLoadElementArray = [
            "NAME" => $dataProduct['NAME'],
            "DETAIL_TEXT" => $dataProduct['DESCRIPTION'],
            "PREVIEW_PICTURE" => $dataProduct['IMAGE'],
            'DETAIL_PICTURE' => $dataProduct['IMAGE'],
            "IBLOCK_ID" => self::IBLOCK_ID,
            "CODE" => $codeElement,
            "IBLOCK_SECTION_ID" => $dataProduct['CATEGORY_ID'],
            "PROPERTY_VALUES"=> $dataProduct['PROPS'],
        ];

        // Убираем пустые значения изображений
        if (!$arLoadElementArray['PREVIEW_PICTURE']) {
            unset($arLoadElementArray['PREVIEW_PICTURE']);
            unset($arLoadElementArray['DETAIL_PICTURE']);
        }

        $idProduct = $element->Add($arLoadElementArray);

        if(!$idProduct){
            addMessage2Log('Product::add error: ' . $element->LAST_ERROR);
            return false;
        }

        if(is_numeric($idProduct)){
            $this->addPrice($idProduct, $dataProduct['PRICE']);
            $this->addCount($idProduct);
        }
    }

    /**
     * Установить/обновить цену товара
     */
    public function addPrice(int $idElement, $price){
        $typePrice = 1;

        $arFields = [
            "PRODUCT_ID" => $idElement,
            "CATALOG_GROUP_ID" => $typePrice,
            "PRICE" => $price,
            "CURRENCY" => "RUB",
        ];

        $res = \CPrice::GetList(
            [],
            [
                "PRODUCT_ID" => $idElement,
                "CATALOG_GROUP_ID" => $typePrice
            ]
        );

        if ($arr = $res->Fetch())
        {
            \CPrice::Update($arr["ID"], $arFields);
        }
        else
        {
            \CPrice::Add($arFields);
        }
    }

    /**
     * Установить количество товара
     */
    private function addCount(int $idElement){
        return \CCatalogProduct::add(["ID" => $idElement, "QUANTITY" => 1000]);
    }

    /**
     * Заглушка для стоп-листа
     */
    public function addStopList(int $idElement){
    }

    /**
     * Обновить существующий товар
     */
    public function update($dataElement, int $idProduct){
        $element = new \CIBlockElement;
        $dataProduct = $this->prepareProductData($dataElement);

        $arLoadElementArray = [
            "NAME" => $dataProduct['NAME'],
            "DETAIL_TEXT" => $dataProduct['DESCRIPTION'],
            "PREVIEW_PICTURE" => $dataProduct['IMAGE'],
            'DETAIL_PICTURE' => $dataProduct['IMAGE'],
            "IBLOCK_ID" => self::IBLOCK_ID,
            "IBLOCK_SECTION_ID" => $dataProduct['CATEGORY_ID'],
            "PROPERTY_VALUES"=> $dataProduct['PROPS'],
        ];

        // Убираем пустые значения изображений
        if (!$arLoadElementArray['PREVIEW_PICTURE']) {
            unset($arLoadElementArray['PREVIEW_PICTURE']);
            unset($arLoadElementArray['DETAIL_PICTURE']);
        }

        $resultUpdate = $element->Update($idProduct, $arLoadElementArray);

        if($resultUpdate){
            $this->addPrice($idProduct, $dataProduct['PRICE']);
            $this->addCount($idProduct);
        }
    }

    /**
     * Сгенерировать символьный код из названия
     */
    public function generateCode($name){
        $arParamsCode = [
            "replace_space" => "-", "replace_other" => "-"
        ];

        return \CUtil::translit($name, "ru", $arParamsCode);
    }

    /**
     * Выполнить запрос к API iiko и вернуть секцию ответа по ключу
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

        } catch (Exception $e) {
            addMessage2Log("Product::makeApiRequest error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Подготовить данные товара для записи в инфоблок
     */
    private function prepareProductData($dataElement): array
    {
        // Изображение: сначала на уровне товара, потом в первом размере
        $imageUrl = $dataElement['buttonImageUrl'] ?? null;
        if (empty($imageUrl) && !empty($dataElement['itemSizes'][0]['buttonImageUrl'])) {
            $imageUrl = $dataElement['itemSizes'][0]['buttonImageUrl'];
        }
        $image = $this->prepareProductImage($imageUrl);

        // Категория
        $categoryId = $this->checkCategoryByIdRk($dataElement['productCategoryId'] ?? '');

        // Парсинг описания: строка 1 — описание, строка 2 — состав, строка 3 — рекомендация
        $textProduct = explode("\n", $dataElement['description'] ?? '');
        $description = trim($textProduct[0] ?? '');
        $sostav = trim($textProduct[1] ?? '');
        $recomendation = trim($textProduct[2] ?? '');

        // Цена из первого размера
        $price = $dataElement['itemSizes'][0]['prices'][0]['price'] ?? 0;

        // Вес
        $portionWeight = (float)($dataElement['itemSizes'][0]['portionWeightGrams'] ?? 0);
        $measureUnit = $dataElement['measureUnit'] ?? 'г';
        $weightStr = $portionWeight > 0 ? $portionWeight . ' ' . $measureUnit : '';

        // Парсинг БЖУ и калорий из описания (строка вида "55 КАЛЛ, Б-1, Ж-4, У-2")
        $calories = $this->parseNutritionValue($description, '/(\d+)\s*КАЛЛ/i');
        $proteins = $this->parseNutritionValue($description, '/Б-([\d.]+)/i');
        $fats = $this->parseNutritionValue($description, '/Ж-([\d.]+)/i');
        $carbs = $this->parseNutritionValue($description, '/У-([\d.]+)/i');

        $dataProduct = [
            'NAME' => $dataElement['name'],
            'DESCRIPTION' => $description,
            'IMAGE' => $image,
            'CATEGORY_ID' => $categoryId,
            'PRICE' => $price,
            'PROPS' => [
                'ATT_RK_ID' => $dataElement['itemId'],
                'ATT_RK_CATEGORY_ID' => $dataElement['productCategoryId'] ?? '',
                'ATT_SOSTAV' => $sostav,
                'ATT_RECOMENDATION' => $recomendation,
                'ATT_KALLORY' => $calories,
                'ATT_BELKI' => $proteins,
                'ATT_GIRY' => $fats,
                'ATT_YGLEVODY' => $carbs,
                'ATT_VES' => $weightStr,
            ]
        ];

        return $dataProduct;
    }

    /**
     * Парсинг числового значения из текста по регулярному выражению
     */
    private function parseNutritionValue(string $text, string $pattern): string
    {
        if (preg_match($pattern, $text, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Загрузить изображение по URL в файловую систему Битрикс
     */
    private function prepareProductImage($imageUrl){
        if(!$imageUrl){
            return false;
        }

        $dataFile = \CFile::MakeFileArray($imageUrl);
        if (!$dataFile) {
            return false;
        }

        $savedFileId = \CFile::SaveFile($dataFile, 'iiko');
        if (!$savedFileId) {
            return false;
        }

        $savedFileSrc = \CFile::GetPath($savedFileId);

        if($savedFileSrc){
            return \CFile::MakeFileArray($savedFileSrc);
        }

        return false;
    }

}
