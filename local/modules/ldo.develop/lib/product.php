<?php
namespace Ldo\Develop;

use Bitrix\Main\Loader;

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

}


