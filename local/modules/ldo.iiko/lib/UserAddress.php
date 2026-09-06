<?php

namespace Ldo\Iiko;

use Bitrix\Main\Loader;

/**
 * Сервис работы с адресами доставки пользователей.
 *
 * Адреса хранятся в собственной таблице ldo_iiko_user_address
 * (модель UserAddressTable), а не в HL-блоке adress_user.
 *
 * Адрес всегда создаётся с привязанной зоной доставки (ZONE_ID) — добавление
 * вне зоны запрещено на уровне компонента заказа. Адреса без зоны в списке
 * не выводятся и в расчёте не участвуют.
 */
class UserAddress
{
    /**
     * Возвращает адреса пользователя для вывода на форме заказа.
     *
     * Формат совместим с прежним Hlblock::getAdressList(). Стоимость доставки
     * в таблице не хранится — поле PRICE (цена зоны адреса) вычисляется на лету
     * из зоны доставки, чтобы фронт формы продолжал получать актуальное значение.
     *
     * @param int $userId
     * @return array
     */
    public static function getListForUser(int $userId): array
    {
        if ($userId <= 0 || !Loader::includeModule('ldo.iiko')) {
            return [];
        }

        UserAddressTable::ensureTable();

        $rows = UserAddressTable::getList([
            'filter' => ['=USER_ID' => $userId],
            'order' => ['ID' => 'ASC'],
        ])->fetchAll();

        if (empty($rows)) {
            return [];
        }

        // Собираем ID зон адресов одним запросом (для поля PRICE)
        $zoneIds = [];
        foreach ($rows as $row) {
            $zoneId = (int)$row['ZONE_ID'];
            if ($zoneId > 0) {
                $zoneIds[$zoneId] = true;
            }
        }
        $zonePrices = self::getZonePrices(array_keys($zoneIds));

        $result = [];
        $isFirst = true;
        foreach ($rows as $row) {
            $zoneId = (int)$row['ZONE_ID'];
            if ($zoneId <= 0) {
                // Адрес без зоны не выводим: такой адрес не должен существовать
                continue;
            }

            $city = (string)($row['CITY'] ?? '');
            $street = trim((string)($row['ADDRESS'] ?? ''));
            $addrName = $street !== ''
                ? ($city !== '' ? $city . ', ' . $street : $street)
                : $city;

            $result[] = [
                'ID' => (int)$row['ID'],
                'CHECKED' => $isFirst,
                'ADRESS_NAME' => $addrName,
                'CITY' => $city,
                'KVARTIRA' => (string)($row['KVARTIRA'] ?? ''),
                'PODEZD' => (string)($row['PODEZD'] ?? ''),
                'ETAG' => (string)($row['ETAG'] ?? ''),
                'DOMOFON' => (string)($row['DOMOFON'] ?? ''),
                'SHIRINA' => (string)($row['LAT'] ?? ''),
                'DOLGOTA' => (string)($row['LON'] ?? ''),
                'ZONE_ID' => $zoneId,
                // Актуальная цена доставки по зоне адреса (не хранится в таблице)
                'PRICE' => (float)($zonePrices[$zoneId] ?? 0),
            ];
            $isFirst = false;
        }

        return $result;
    }

    /**
     * Возвращает цены зон по их ID (модуль ldo.deliverymap).
     *
     * @param array $zoneIds
     * @return array ID зоны => PRICE
     */
    private static function getZonePrices(array $zoneIds): array
    {
        if (empty($zoneIds) || !Loader::includeModule('ldo.deliverymap')) {
            return [];
        }

        $prices = [];
        $rows = \Ldo\Deliverymap\DeliveryZoneTable::getList([
            'filter' => ['=ID' => $zoneIds],
            'select' => ['ID', 'PRICE'],
        ]);
        while ($zone = $rows->fetch()) {
            $prices[(int)$zone['ID']] = (float)$zone['PRICE'];
        }

        return $prices;
    }

    /**
     * Возвращает адрес по ID (для расчёта доставки / выбора адреса).
     *
     * @param int $id
     * @return array|null
     */
    public static function getById(int $id): ?array
    {
        if ($id <= 0 || !Loader::includeModule('ldo.iiko')) {
            return null;
        }

        UserAddressTable::ensureTable();

        $row = UserAddressTable::getRowById($id);
        if (!$row) {
            return null;
        }

        return [
            'ID' => (int)$row['ID'],
            'USER_ID' => (int)$row['USER_ID'],
            'CITY' => (string)($row['CITY'] ?? ''),
            'ADDRESS' => (string)($row['ADDRESS'] ?? ''),
            'KVARTIRA' => (string)($row['KVARTIRA'] ?? ''),
            'PODEZD' => (string)($row['PODEZD'] ?? ''),
            'ETAG' => (string)($row['ETAG'] ?? ''),
            'DOMOFON' => (string)($row['DOMOFON'] ?? ''),
            'LAT' => (string)($row['LAT'] ?? ''),
            'LON' => (string)($row['LON'] ?? ''),
            'ZONE_ID' => (int)$row['ZONE_ID'],
        ];
    }

    /**
     * Добавляет адрес пользователя.
     *
     * Ожидаемые ключи: USER_ID, CITY, ADDRESS, KVARTIRA, PODEZD, ETAG,
     * DOMOFON, LAT, LON, ZONE_ID (ZONE_ID — зона, к которой относится адрес).
     *
     * @param array $fields
     * @return int|false ID новой записи или false
     */
    public static function add(array $fields): int|false
    {
        if (!Loader::includeModule('ldo.iiko')) {
            return false;
        }

        UserAddressTable::ensureTable();

        $data = self::normalizeFields($fields);
        if ((int)($data['USER_ID'] ?? 0) <= 0) {
            return false;
        }
        if (trim((string)($data['ADDRESS'] ?? '')) === '' && trim((string)($data['CITY'] ?? '')) === '') {
            return false;
        }

        $result = UserAddressTable::add($data);
        if (!$result->isSuccess()) {
            return false;
        }

        return (int)$result->getId();
    }

    /**
     * Обновляет адрес пользователя.
     *
     * Частичное обновление: меняются только переданные поля, остальные
     * (USER_ID, координаты, зона) не затираются. Если вместе с адресом
     * переданы координаты — зона доставки пересчитывается по ним.
     *
     * @param int $id
     * @param array $fields
     * @return bool
     */
    public static function update(int $id, array $fields): bool
    {
        if ($id <= 0 || !Loader::includeModule('ldo.iiko')) {
            return false;
        }

        UserAddressTable::ensureTable();

        $data = self::normalizeProvidedFields($fields);
        if (empty($data)) {
            return false;
        }

        // При редактировании координат пересчитываем зону адреса
        if (array_key_exists('LAT', $data) || array_key_exists('LON', $data)) {
            $lat = (float)($data['LAT'] ?? 0);
            $lon = (float)($data['LON'] ?? 0);
            if ($lat == 0 || $lon == 0) {
                // Пустые/нулевые координаты — не затираем существующие
                unset($data['LAT'], $data['LON']);
            } elseif (Loader::includeModule('ldo.develop')) {
                $zoneId = \Ldo\Develop\Hlblock::findZoneIdByCoordinates($lat, $lon);
                $data['ZONE_ID'] = $zoneId > 0 ? $zoneId : 0;
            }
        }

        $result = UserAddressTable::update($id, $data);
        return $result->isSuccess();
    }

    /**
     * Удаляет адрес пользователя.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool
    {
        if ($id <= 0 || !Loader::includeModule('ldo.iiko')) {
            return false;
        }

        UserAddressTable::ensureTable();

        $result = UserAddressTable::delete($id);
        return $result->isSuccess();
    }

    /**
     * Соответствие входных ключей полям таблицы (без UF_*-префиксов).
     *
     * @return array
     */
    private static function fieldMap(): array
    {
        return [
            'USER_ID' => 'USER_ID',
            'CITY' => 'CITY',
            'ADDRESS' => 'ADDRESS',
            'KVARTIRA' => 'KVARTIRA',
            'PODEZD' => 'PODEZD',
            'ETAG' => 'ETAG',
            'DOMOFON' => 'DOMOFON',
            'LAT' => 'LAT',
            'LON' => 'LON',
            'ZONE_ID' => 'ZONE_ID',
            // старые UF-имена (на случай вызова из устаревшего кода)
            'UF_USER_ID' => 'USER_ID',
            'UF_CITY' => 'CITY',
            'UF_ADDRESS' => 'ADDRESS',
            'UF_KVARTIRA' => 'KVARTIRA',
            'UF_PODEZD' => 'PODEZD',
            'UF_ETAG' => 'ETAG',
            'UF_DOMOFON' => 'DOMOFON',
            'UF_SHIRINA' => 'LAT',
            'UF_DOLGOTA' => 'LON',
            'UF_ZONE_ID' => 'ZONE_ID',
        ];
    }

    /**
     * Приводит входной массив к полям таблицы (без UF_*-префиксов),
     * заполняя отсутствующие поля значениями по умолчанию.
     * Используется при добавлении адреса.
     *
     * @param array $fields
     * @return array
     */
    private static function normalizeFields(array $fields): array
    {
        $data = self::normalizeProvidedFields($fields);

        $data['USER_ID'] = (int)($data['USER_ID'] ?? 0);
        $data['ZONE_ID'] = (int)($data['ZONE_ID'] ?? 0);
        $data['CITY'] = (string)($data['CITY'] ?? '');
        $data['ADDRESS'] = (string)($data['ADDRESS'] ?? '');
        $data['KVARTIRA'] = (string)($data['KVARTIRA'] ?? '');
        $data['PODEZD'] = (string)($data['PODEZD'] ?? '');
        $data['ETAG'] = (string)($data['ETAG'] ?? '');
        $data['DOMOFON'] = (string)($data['DOMOFON'] ?? '');
        $data['LAT'] = (string)($data['LAT'] ?? '');
        $data['LON'] = (string)($data['LON'] ?? '');

        return $data;
    }

    /**
     * Возвращает только переданные поля с приведением типов.
     * Используется для частичного обновления адреса, чтобы не затирать
     * остальные (USER_ID, LAT/LON, ZONE_ID) пустыми значениями.
     *
     * @param array $fields
     * @return array
     */
    private static function normalizeProvidedFields(array $fields): array
    {
        $data = [];
        foreach (self::fieldMap() as $source => $target) {
            if (!array_key_exists($source, $fields)) {
                continue;
            }
            $value = $fields[$source];
            if ($target === 'USER_ID' || $target === 'ZONE_ID') {
                $data[$target] = (int)$value;
            } else {
                $data[$target] = (string)$value;
            }
        }

        return $data;
    }
}
