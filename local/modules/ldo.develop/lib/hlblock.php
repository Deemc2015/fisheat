<?php
namespace Ldo\Develop;

use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;


class Hlblock
{
    private static $hlblockTableName = 'b_hlbd_colors';

    /** @var bool Поле UF_CITY в HL-блоке adress_user гарантировано создано */
    private static $cityFieldEnsured = false;

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

            // Гарантируем наличие поля UF_CITY
            self::ensureAddressCityField();

            $entity = self::getHLEntity($tableName );

            if (!$entity) {
                return [];
            }

            $records = $entity::getList([
                'select' => ['ID','UF_SHIRINA', 'UF_DOLGOTA','UF_ADDRESS','UF_CITY','UF_KVARTIRA','UF_PODEZD','UF_ETAG','UF_DOMOFON','UF_PRICE','UF_DATE_ACTUAL','UF_MINIMAL_SUM','UF_FREE_DELIVERY'],
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

                    // Город хранится отдельно, в названии — склейка "город, улица, дом"
                    $city = (string)($item['UF_CITY'] ?? '');
                    $street = trim((string)$item['UF_ADDRESS']);
                    $addrName = $street !== ''
                        ? ($city !== '' ? $city . ', ' . $street : $street)
                        : $city;

                    $arrAdress[] = [
                        'ID' => $item['ID'],
                        'CHECKED' => $checked,
                        'ADRESS_NAME' => $addrName,
                        'CITY' => $city,
                        'KVARTIRA' => (string)($item['UF_KVARTIRA'] ?? ''),
                        'PODEZD' => (string)($item['UF_PODEZD'] ?? ''),
                        'ETAG' => (string)($item['UF_ETAG'] ?? ''),
                        'DOMOFON' => (string)($item['UF_DOMOFON'] ?? ''),
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


    /**
     * Гарантирует наличие поля UF_CITY в HL-блоке adress_user.
     * Если поле отсутствует — создаёт его через CUserTypeEntity.
     *
     * @return bool
     */
    public static function ensureAddressCityField(): bool
    {
        if (self::$cityFieldEnsured) {
            return true;
        }

        if (!Loader::includeModule('highloadblock')) {
            return false;
        }

        $hlblock = HL\HighloadBlockTable::getRow([
            'filter' => ['=TABLE_NAME' => 'adress_user']
        ]);
        if (!$hlblock) {
            return false;
        }

        $entityId = 'HLBLOCK_' . (int)$hlblock['ID'];

        $field = \Bitrix\Main\UserFieldTable::getRow([
            'filter' => ['=ENTITY_ID' => $entityId, '=FIELD_NAME' => 'UF_CITY']
        ]);

        if (!$field) {
            $userTypeEntity = new \CUserTypeEntity();
            $userTypeEntity->Add([
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => 'UF_CITY',
                'USER_TYPE_ID' => 'string',
                'XML_ID' => 'UF_CITY',
                'SORT' => 500,
                'MULTIPLE' => 'N',
                'MANDATORY' => 'N',
                'SHOW_FILTER' => 'N',
                'SHOW_IN_LIST' => 'Y',
                'EDIT_IN_LIST' => 'Y',
                'IS_SEARCHABLE' => 'N',
                'SETTINGS' => ['SIZE' => 255, 'ROWS' => 1],
                'EDIT_FORM_LABEL' => ['ru' => 'Город'],
                'LIST_COLUMN_LABEL' => ['ru' => 'Город'],
                'LIST_FILTER_LABEL' => ['ru' => 'Город'],
            ]);
        }

        self::$cityFieldEnsured = true;

        return true;
    }

    /**
     * Добавляет адрес пользователя в HL-блок adress_user,
     * гарантируя наличие поля UF_CITY и используя свежую сущность.
     *
     * @param array $fields
     * @return int|false
     */
    public static function addAddress(array $fields): int|false
    {
        if (!Loader::includeModule('highloadblock')) {
            return false;
        }

        // Гарантируем наличие поля UF_CITY
        self::ensureAddressCityField();

        $hlblock = HL\HighloadBlockTable::getRow([
            'filter' => ['=TABLE_NAME' => 'adress_user']
        ]);
        if (!$hlblock) {
            return false;
        }

        // Компилируем свежую сущность, чтобы UF_CITY точно был в таблице
        $entity = HL\HighloadBlockTable::compileEntity($hlblock)->getDataClass();
        $result = $entity::add($fields);

        if (!$result->isSuccess()) {
            return false;
        }

        return $result->getId();
    }

    /**
     * Обновляет адрес пользователя в HL-блоке adress_user.
     *
     * @param int $id
     * @param array $fields
     * @return bool
     */
    public static function updateAddress(int $id, array $fields): bool
    {
        if (!Loader::includeModule('highloadblock')) {
            return false;
        }

        // Гарантируем наличие поля UF_CITY
        self::ensureAddressCityField();

        $hlblock = HL\HighloadBlockTable::getRow([
            'filter' => ['=TABLE_NAME' => 'adress_user']
        ]);
        if (!$hlblock) {
            return false;
        }

        $entity = HL\HighloadBlockTable::compileEntity($hlblock)->getDataClass();
        $result = $entity::update($id, $fields);

        return $result->isSuccess();
    }


    public static function add(array $fields, string $tableName = null): int|false
    {
        if (!Loader::includeModule('highloadblock')) {
            return false;
        }

        $entity = self::getHLEntity($tableName);

        if (!$entity) {
            return false;
        }

        $result = $entity::add($fields);

        if (!$result->isSuccess()) {
            return false;
        }

        return $result->getId();
    }


}


