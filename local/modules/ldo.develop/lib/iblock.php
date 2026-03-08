<?php
namespace Ldo\Develop;

use Bitrix\Iblock\IblockTable;
use Bitrix\Main\SystemException;

class Iblock
{

    public static function getList(string $iblockName, array $fieldsSelect, array $arrfilter = null):array
    {

        try {

            $filter = ['=ACTIVE' => 'Y'];

            if($arrfilter){
                $filter = array_merge($filter, $arrfilter);
            }

            if(!$iblockName){
                throw new SystemException('Инфоблок не передан');
            }

            // Формируем полное имя класса
            $className = '\\Bitrix\\Iblock\\Elements\\Element' . ucfirst($iblockName) . 'Table'; //TODO php version 8.4 replace mb_ucfirst

            if (!class_exists($className)) {
                throw new \Exception("Класс для инфоблока {$iblockName} не найден");
            }

            $elements = $className::getList([
                'select' => $fieldsSelect,
                'filter' => $filter,
                'cache' => ['ttl' => 3600],
            ])->fetchAll();

            return $elements ?: [];
        }
        catch (Exception $e){
            echo 'Ошибка: ' . $e->getMessage();
        }

    }



}


