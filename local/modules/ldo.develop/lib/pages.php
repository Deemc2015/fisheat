<?php
namespace Ldo\Develop;

use Bitrix\Main\Loader;

Loader::IncludeModule("iblock");

class Pages
{
    public static function isDetailProduct($iblockId)
    {
        global $APPLICATION;

        // Быстрый выход, если не в каталоге
        if (!\CSite::InDir('/catalog/')) {
            return false;
        }

        // Получаем путь без параметров
        $currentPage = $APPLICATION->GetCurPage(false);

        // Убираем начальный и конечный слэши
        $currentPath = trim($currentPage, '/');
        $pathParts = explode('/', $currentPath);

        // Детальная страница должна иметь минимум 2 уровня: catalog/product-code
        if (count($pathParts) < 2) {
            return false;
        }

        $productCode = end($pathParts);

        // Быстрая проверка: не пустой и не похож на файл
        if (empty($productCode) || preg_match('/\.(php|html|htm)$/i', $productCode)) {
            return false;
        }

        // Проверяем существование активного товара
        $res = \CIBlockElement::GetList(
            [],
            [
                "IBLOCK_ID" => $iblockId,
                "CODE" => $productCode,
                "ACTIVE" => "Y"
            ],
            false,
            ["nTopCount" => 1],
            ["ID"]
        );

        return (bool) $res->Fetch();
    }
}


