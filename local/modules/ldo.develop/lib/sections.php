<?php
namespace Ldo\Develop;
use Bitrix\Iblock\Model\Section;
use Bitrix\Iblock\IblockTable;
use Bitrix\Main\SystemException;

class Sections
{

    public static function getListViewIndex(string $iblockName):array
    {
        try {
            $iblock = IblockTable::query()
                ->where('API_CODE', $iblockName)
                ->fetchObject();

            $sectionClass = Section::compileEntityByIblock($iblock);

            $section = $sectionClass::query()
                ->setSelect(['ID','UF_VIEW_INDEX']) // Все поля + все UF-поля
                ->where('UF_VIEW_INDEX', 1)
                ->where('DEPTH_LEVEL', 1)
                ->fetchAll();

            if(count($section) > 0){
                return $section;
            }

        }
        catch (Exception $e){
            echo 'Ошибка: ' . $e->getMessage();
        }

    }



}


