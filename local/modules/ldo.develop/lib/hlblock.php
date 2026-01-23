<?php
namespace Ldo\Develop;

use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;


class Hlblock
{
    private static $hlblockTableName = 'b_hlbd_colors';

    public static function getImageByIds( $arrCodes)
    {

        if (!Loader::includeModule('highloadblock')) {
            return [];
        }

        $entity = self::getHLEntity();
        if (!$entity) {
            return [];
        }

        // Получаем все записи + файлы за 1 запрос
        $records = $entity::getList([
            'select' => ['UF_XML_ID', 'UF_FILE'],
            'filter' => ['UF_XML_ID' => $arrCodes]
        ])->fetchAll();

        // Собираем ID файлов
        $fileIds = array_column($records, 'UF_FILE', 'UF_XML_ID');

        // Получаем все файлы за 1 запрос
        $fileUrls = [];
        if (!empty($fileIds)) {
            $files = \CFile::GetList([], ['@ID' => array_values($fileIds)]);
            while ($file = $files->Fetch()) {
                $fileUrls[$file['ID']] = \CFile::GetFileSRC($file);
            }
        }

        // Сопоставляем CODE с URL
        $result = [];
        foreach ($fileIds as $code => $fileId) {
            $result[$code] = $fileUrls[$fileId] ?? '';
        }

        return $result;
    }

    public static function getImageByNames($arrNames)
    {

        if (!Loader::includeModule('highloadblock')) {
            return [];
        }

        $entity = self::getHLEntity();
        if (!$entity) {
            return [];
        }

        // Получаем все записи + файлы за 1 запрос
        $records = $entity::getList([
            'select' => ['UF_NAME', 'UF_FILE'],
            'filter' => ['UF_NAME' => $arrNames]
        ])->fetchAll();

        // Собираем ID файлов
        $fileIds = array_column($records, 'UF_FILE', 'UF_NAME');

        // Получаем все файлы за 1 запрос
        $fileUrls = [];
        if (!empty($fileIds)) {
            $files = \CFile::GetList([], ['@ID' => array_values($fileIds)]);
            while ($file = $files->Fetch()) {
                $fileUrls[$file['ID']] = \CFile::GetFileSRC($file);
            }
        }

        // Сопоставляем CODE с URL
        $result = $fileUrls;


        return $result;
    }

    public static function getNameByIds(array $arrCodes):array
    {
        if (!Loader::includeModule('highloadblock')) {
            return [];
        }

        $entity = self::getHLEntity();

        if (!$entity) {
            return [];
        }

        $records = $entity::getList([
            'select' => ['UF_XML_ID', 'UF_NAME'],
            'filter' => ['UF_XML_ID' => $arrCodes]
        ])->fetchAll();

        return array_column($records, 'UF_NAME');
    }

    private static function getHLEntity()
    {
        static $entity = null;

        if ($entity === null) {
            $hlblock = HL\HighloadBlockTable::getRow([
                'filter' => ['=TABLE_NAME' => self::$hlblockTableName]
            ]);
            if ($hlblock) {
                $entity = HL\HighloadBlockTable::compileEntity($hlblock)->getDataClass();
            }
        }

        return $entity;
    }


}


