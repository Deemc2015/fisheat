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

    private static function getHLEntity($tableName)
    {
        static $entity = null;

        $table = self::$hlblockTableName;

        if($tableName){
            $table = $tableName;
        }

        if ($entity === null) {
            $hlblock = HL\HighloadBlockTable::getRow([
                'filter' => ['=TABLE_NAME' => $table]
            ]);
            if ($hlblock) {
                $entity = HL\HighloadBlockTable::compileEntity($hlblock)->getDataClass();
            }
        }

        return $entity;
    }

    public static function getAdressList(){

        global $USER;

        $userId = $USER->getId();

        $tableName = 'adress_user';

        if($userId){
            if (!Loader::includeModule('highloadblock')) {
                return [];
            }

            $entity = self::getHLEntity($tableName );

            if (!$entity) {
                return [];
            }

            $records = $entity::getList([
                'select' => ['ID','UF_SHIRINA', 'UF_DOLGOTA','UF_ADDRESS','UF_PRICE','UF_DATE_ACTUAL','UF_MINIMAL_SUM','UF_FREE_DELIVERY'],
                'filter' => ['UF_USER_ID' => $userId]
            ])->fetchAll();


            /*Тут добавить метод обновления данных по доставке, если актуальная дата меньше сегодняшнее*/

            /*Добавить метод проверки последнего адреса заказа, установить чеккед для него и выводить первым*/

            if(is_array($records)){
                $i = 1;
                $checked = false;
                foreach ($records as $item){

                    if($i == 1){
                        $checked = 'true';
                    }

                    $arrAdress[] = [
                        'ID' => $item['ID'],
                        'CHECKED' => $checked,
                        'ADRESS_NAME' => $item['UF_ADDRESS'],
                        'SHIRINA' => $item['UF_SHIRINA'],
                        'DOLGOTA' => $item['UF_DOLGOTA'],
                        'MIN_SUM' => $item['UF_MINIMAL_SUM'],
                        'PRICE' => $item['UF_PRICE'],
                        'FREE_DELIVERY' => $item['UF_FREE_DELIVERY']
                    ];
                    $i++;
                }

                return $arrAdress;
            }
        }
    }

    public static function deleteAddress(int $id){

        if($id){
            $tableName = 'adress_user';

            if (!Loader::includeModule('highloadblock')) {
                return [];
            }

            $entity = self::getHLEntity($tableName );

            if (!$entity) {
                return [];
            }


            $result = $entity::Delete($id);


            if($result->isSuccess()){
                return true;
            }

        }


    }


}


