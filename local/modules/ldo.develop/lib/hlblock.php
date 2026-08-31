<?php
namespace Ldo\Develop;

use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;


class Hlblock
{
    private static $hlblockTableName = 'b_hlbd_colors';

    /** @var bool Поле UF_CITY в HL-блоке adress_user гарантировано создано */
    private static $cityFieldEnsured = false;

    /** @var bool Поле UF_ZONE_ID в HL-блоке adress_user гарантировано создано */
    private static $zoneFieldEnsured = false;

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
                'select' => ['ID','UF_SHIRINA', 'UF_DOLGOTA','UF_ADDRESS','UF_CITY','UF_KVARTIRA','UF_PODEZD','UF_ETAG','UF_DOMOFON','UF_PRICE','UF_DATE_ACTUAL','UF_MINIMAL_SUM','UF_FREE_DELIVERY','UF_ZONE_ID'],
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
                        'FREE_DELIVERY' => $item['UF_FREE_DELIVERY'],
                        'ZONE_ID' => (int)($item['UF_ZONE_ID'] ?? 0)
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
     * Гарантирует наличие поля UF_ZONE_ID в HL-блоке adress_user.
     * В это поле сохраняется ID зоны доставки, к которой относится адрес.
     * Если поле отсутствует — создаёт его через CUserTypeEntity.
     *
     * @return bool
     */
    public static function ensureAddressZoneField(): bool
    {
        if (self::$zoneFieldEnsured) {
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
            'filter' => ['=ENTITY_ID' => $entityId, '=FIELD_NAME' => 'UF_ZONE_ID']
        ]);

        if (!$field) {
            $userTypeEntity = new \CUserTypeEntity();
            $userTypeEntity->Add([
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => 'UF_ZONE_ID',
                'USER_TYPE_ID' => 'integer',
                'XML_ID' => 'UF_ZONE_ID',
                'SORT' => 500,
                'MULTIPLE' => 'N',
                'MANDATORY' => 'N',
                'SHOW_FILTER' => 'N',
                'SHOW_IN_LIST' => 'Y',
                'EDIT_IN_LIST' => 'Y',
                'IS_SEARCHABLE' => 'N',
                'EDIT_FORM_LABEL' => ['ru' => 'Зона доставки'],
                'LIST_COLUMN_LABEL' => ['ru' => 'Зона доставки'],
                'LIST_FILTER_LABEL' => ['ru' => 'Зона доставки'],
            ]);
        }

        self::$zoneFieldEnsured = true;

        return true;
    }

    /**
     * Определяет ID зоны доставки, к которой относятся координаты адреса.
     *
     * @param mixed $lat Широта
     * @param mixed $lon Долгота
     * @return int ID зоны доставки или 0, если адрес вне зон
     */
    public static function findZoneIdByCoordinates($lat, $lon): int
    {
        $lat = (float)$lat;
        $lon = (float)$lon;

        if ($lat == 0 || $lon == 0 || !Loader::includeModule('ldo.deliverymap')) {
            return 0;
        }

        $siteId = defined('SITE_ID') ? SITE_ID : 's1';

        $dbZones = \Ldo\Deliverymap\DeliveryZoneTable::getList([
            'filter' => [
                '=ACTIVE' => 'Y',
                '=SITE_ID' => $siteId
            ],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC']
        ]);

        $point = [$lat, $lon];

        while ($zone = $dbZones->fetch()) {
            $coordinates = $zone['COORDINATES'];
            if (is_string($coordinates)) {
                $coordinates = json_decode($coordinates, true);
            }

            if (!is_array($coordinates) || count($coordinates) < 3) {
                continue;
            }

            $polygon = [];
            foreach ($coordinates as $p) {
                if (is_array($p) && count($p) === 2) {
                    $polygon[] = [(float)$p[0], (float)$p[1]];
                }
            }

            if (count($polygon) < 3 || !self::isPointInPolygon($point, $polygon)) {
                continue;
            }

            return (int)$zone['ID'];
        }

        return 0;
    }

    /**
     * Проверка принадлежности точки полигону (алгоритм Ray Casting).
     *
     * @param array $point [lat, lng]
     * @param array $polygon [[lat, lng], ...]
     * @return bool
     */
    private static function isPointInPolygon(array $point, array $polygon): bool
    {
        $x = $point[0];
        $y = $point[1];
        $inside = false;
        $j = count($polygon) - 1;

        for ($i = 0; $i < count($polygon); $i++) {
            $xi = $polygon[$i][0];
            $yi = $polygon[$i][1];
            $xj = $polygon[$j][0];
            $yj = $polygon[$j][1];

            $intersect = (($yi > $y) != ($yj > $y)) &&
                ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = !$inside;
            }
            $j = $i;
        }

        return $inside;
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

        // Гарантируем наличие полей UF_CITY и UF_ZONE_ID
        self::ensureAddressCityField();
        self::ensureAddressZoneField();

        // Определяем ID зоны доставки, к которой относится адрес
        $lat = (float)($fields['UF_SHIRINA'] ?? 0);
        $lon = (float)($fields['UF_DOLGOTA'] ?? 0);
        if ($lat != 0 && $lon != 0) {
            $zoneId = self::findZoneIdByCoordinates($lat, $lon);
            if ($zoneId > 0) {
                $fields['UF_ZONE_ID'] = $zoneId;
            }
        }

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

        // Гарантируем наличие полей UF_CITY и UF_ZONE_ID
        self::ensureAddressCityField();
        self::ensureAddressZoneField();

        // Если координаты не переданы — берём их из текущей записи
        if (!array_key_exists('UF_SHIRINA', $fields) || !array_key_exists('UF_DOLGOTA', $fields)) {
            $hlblockCurrent = HL\HighloadBlockTable::getRow([
                'filter' => ['=TABLE_NAME' => 'adress_user']
            ]);
            if ($hlblockCurrent) {
                $entityCurrent = HL\HighloadBlockTable::compileEntity($hlblockCurrent)->getDataClass();
                $current = $entityCurrent::getRow([
                    'filter' => ['=ID' => $id],
                    'select' => ['ID', 'UF_SHIRINA', 'UF_DOLGOTA']
                ]);
                if ($current) {
                    if (!array_key_exists('UF_SHIRINA', $fields)) {
                        $fields['UF_SHIRINA'] = $current['UF_SHIRINA'] ?? '';
                    }
                    if (!array_key_exists('UF_DOLGOTA', $fields)) {
                        $fields['UF_DOLGOTA'] = $current['UF_DOLGOTA'] ?? '';
                    }
                }
            }
        }

        // Пересчитываем ID зоны доставки по координатам адреса
        $lat = (float)($fields['UF_SHIRINA'] ?? 0);
        $lon = (float)($fields['UF_DOLGOTA'] ?? 0);
        $fields['UF_ZONE_ID'] = ($lat != 0 && $lon != 0)
            ? self::findZoneIdByCoordinates($lat, $lon)
            : 0;

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


