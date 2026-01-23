<?php
namespace Ldo\Rkeeper;
use Ldo\Rkeeper\Auth;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Loader;


Loader::includeModule("iblock");
Loader::includeModule('catalog');

class Product
{
    const DATA_URL = 'https://delivery.ucs.ru/orders/api/v1/menu/view/?restaurantId=7e44794d-6051-458e-872a-c4487bf54720';
    const IBLOCK_ID = 4;

    private $token;

    public function __construct() {
        $tokenData = new Auth();
        $this->token = $tokenData->getToken();
    }

    public function getCategory(): array
    {
        return $this->makeApiRequest('categories');
    }

    public function getList(): array
    {
        return $this->makeApiRequest('products');
    }

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

        if($isExist){
            return $isExist['ID'];
        }

    }



    public function addCategory(array $dataCategory)
    {
        if($dataCategory){

            $codeElement = $this->generateCode($dataCategory['name']);

            $arFields = [
                'IBLOCK_ID' => self::IBLOCK_ID,
                'NAME' => $dataCategory['name'],
                'UF_ID_RK' => $dataCategory['id'],
                'UF_PARENT_ID' => $dataCategory['parentId'],
                'CODE' => $codeElement
            ];

            $dataObSection = new \CIBlockSection;

            $addResult = $dataObSection->Add($arFields,true);

            if($addResult > 0){
                return true;
            }

            if (!$addResult) {

                $errorMessage = $dataObSection->LAST_ERROR;
                addMessage2Log($errorMessage);
                return false;
            }

        }
    }

    public function sync(){
        $productList = $this->getList();
        if($productList){
            $i = 0;
            foreach ($productList as $product){
                $i++;

                $idProduct = $this->isExist($product['id']);

                if($idProduct){
                    $resultUpdate = $this->update($product,$idProduct);
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

        $idProduct = $element->Add($arLoadElementArray);

        if(!$idProduct){
            addMessage2Log($element->LAST_ERROR);
            return false;
        }

        if(is_numeric($idProduct)){
            $this->addPrice($idProduct,$dataProduct['PRICE']);
            $this->addCount($idProduct);
        }

    }

    public function addPrice(int $idElement, $price){
        $typePrice = 1;

        $arFields = Array(
            "PRODUCT_ID" => $idElement,
            "CATALOG_GROUP_ID" => $typePrice,
            "PRICE" => $price,
            "CURRENCY" => "RUB",

        );
        $res = \CPrice::GetList(
            array(),
            array(
                "PRODUCT_ID" => $idElement,
                "CATALOG_GROUP_ID" => $typePrice
            )
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

    private function addCount(int $idElement){

        return  \CCatalogProduct::add(array("ID" => $idElement, "QUANTITY" => 1000));

    }


    public function addStopList(int $idElement){

    }

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

        $resultUpdate = $element->Update($idProduct, $arLoadElementArray);

        if($resultUpdate){
            $this->addPrice($idProduct,$dataElement['price']);
            $this->addCount($idProduct);
        }

    }


    public function generateCode($name){
        $arParamsCode = [
            "replace_space" => "-", "replace_other" => "-"
        ];

        return \CUtil::translit($name, "ru", $arParamsCode );
    }

    private function makeApiRequest(string $dataType): array
    {
        if (!$this->token) {
            return [];
        }

        $httpClient = new HttpClient();
        $httpClient->setHeader('Authorization', 'Bearer ' . $this->token);

        try {
            $response = $httpClient->get(self::DATA_URL);
            $result = json_decode($response, true);

            return $result['result'][$dataType] ?? [];

        } catch (Exception $e) {
            error_log("API request failed: " . $e->getMessage());
            return [];
        }
    }

    private function prepareProductData($dataElement){
        $image = $this->prepareProductImage($dataElement['imageUrls'][0] ?? '');
        $categoryId = $this->checkCategoryByIdRk($dataElement['categoryId']);

        $textProduct = explode("\n", $dataElement['description'] ?? '');
        $description = $textProduct[0] ?? '';
        $sostav = $textProduct[1] ?? '';
        $recomendation = $textProduct[2] ?? '';

        $dataProduct = [
            'NAME' => $dataElement['name'],
            'DESCRIPTION' => $description,
            'IMAGE' => $image,
            'CATEGORY_ID' => $categoryId,
            'PRICE' => $dataElement['price'],
            'PROPS' => [
                'ATT_RK_ID' => $dataElement['id'],
                'ATT_RK_CATEGORY_ID' => $dataElement['categoryId'],
                'ATT_EXTERNAL_ID' => $dataElement['externalId'],
                'ATT_SOSTAV' => $sostav,
                'ATT_RECOMENDATION' => $recomendation,
                'ATT_KALLORY' => $dataElement['calories'],
                'ATT_BELKI' => $dataElement['proteins'],
                'ATT_GIRY' => $dataElement['fats'],
                'ATT_YGLEVODY' => $dataElement['carbohydrates'],
                'ATT_VES' => $dataElement['measure']['value'] . ' ' . $dataElement['measure']['unit']
            ]
        ];

        return $dataProduct;
    }

    private function prepareProductImage($imageUrl){

        if(!$imageUrl){
            return false;
        }

        $dataFile = \CFile::MakeFileArray($imageUrl);

        $savedFileId = \CFile::SaveFile($dataFile, 'saved');

        $savedFileSrc = \CFile::GetPath($savedFileId);

        if($savedFileSrc){
            return  \CFile::MakeFileArray($savedFileSrc);
        }

    }

}


