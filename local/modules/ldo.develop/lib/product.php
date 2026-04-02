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

    public static function checkInFreeCategoryProducts($sectionProduct){

        $dataFreePosition = Iblock::getList('free', ['ID','NAME', 'ATT_RAZDEL_' => 'ATT_RAZDEL.VALUE','FREE_POSITION' => 'ATT_FREE_PRODUCT.VALUE','COUNT' =>'ATT_COUNT_PRODUCT.VALUE']);

        $freeProductsId = [];


        if($dataFreePosition){
            foreach($dataFreePosition as $categoryInfo){
                if($sectionProduct == (int)$categoryInfo['ATT_RAZDEL_']){
                    $freeProductsId['IDS'][] = (int)$categoryInfo['FREE_POSITION'];
                    $freeProductsId['PORTION'] = (int)$categoryInfo['COUNT'];
                }
            }

            if(!empty($freeProductsId)){
                return $freeProductsId;
            }
        }

        return false;
    }



}


