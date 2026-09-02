<?php

namespace Ldo\Iiko;

use Bitrix\Main\Application;
use Bitrix\Main\Entity;

/**
 * Таблица адресов доставки пользователей (собственная таблица вместо HL-блока adress_user).
 *
 * Хранятся только необходимые данные адреса. Никаких полей цены доставки,
 * минимальной суммы и т.п. здесь нет — стоимость доставки рассчитывается
 * от зоны доставки (ldo_delivery_zones) на бэкенде.
 *
 * Таблица создаётся автоматически (см. ensureTable) — при установке модуля
 * либо при первом обращении.
 */
class UserAddressTable extends Entity\DataManager
{
    private static $tableChecked = false;

    public static function getTableName()
    {
        return 'ldo_iiko_user_address';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new Entity\IntegerField('USER_ID', [
                'required' => true,
            ]),
            new Entity\StringField('CITY', [
                'default_value' => '',
                'validation' => function () {
                    return [new Entity\Validator\Length(null, 255)];
                },
            ]),
            new Entity\StringField('ADDRESS', [
                'required' => true,
                'validation' => function () {
                    return [new Entity\Validator\Length(null, 255)];
                },
            ]),
            new Entity\StringField('KVARTIRA', [
                'default_value' => '',
                'validation' => function () {
                    return [new Entity\Validator\Length(null, 64)];
                },
            ]),
            new Entity\StringField('PODEZD', [
                'default_value' => '',
                'validation' => function () {
                    return [new Entity\Validator\Length(null, 64)];
                },
            ]),
            new Entity\StringField('ETAG', [
                'default_value' => '',
                'validation' => function () {
                    return [new Entity\Validator\Length(null, 64)];
                },
            ]),
            new Entity\StringField('DOMOFON', [
                'default_value' => '',
                'validation' => function () {
                    return [new Entity\Validator\Length(null, 64)];
                },
            ]),
            new Entity\StringField('LAT', [
                'default_value' => '',
                'validation' => function () {
                    return [new Entity\Validator\Length(null, 32)];
                },
            ]),
            new Entity\StringField('LON', [
                'default_value' => '',
                'validation' => function () {
                    return [new Entity\Validator\Length(null, 32)];
                },
            ]),
            new Entity\IntegerField('ZONE_ID', [
                'default_value' => 0,
            ]),
        ];
    }

    /**
     * Гарантирует наличие таблицы в БД (создаёт при первом обращении).
     *
     * @return void
     */
    public static function ensureTable()
    {
        if (self::$tableChecked) {
            return;
        }

        self::$tableChecked = true;

        $connection = Application::getConnection();
        if (!$connection->isTableExists(self::getTableName())) {
            self::createTable($connection);
        }
    }

    /**
     * SQL создания таблицы (используется и при установке модуля, и на лету).
     *
     * @param \Bitrix\Main\DB\Connection $connection
     * @return void
     */
    public static function createTable($connection)
    {
        $connection->queryExecute("
            CREATE TABLE IF NOT EXISTS `ldo_iiko_user_address` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `USER_ID` int(11) NOT NULL DEFAULT '0',
                `CITY` varchar(255) NOT NULL DEFAULT '',
                `ADDRESS` varchar(255) NOT NULL DEFAULT '',
                `KVARTIRA` varchar(64) NOT NULL DEFAULT '',
                `PODEZD` varchar(64) NOT NULL DEFAULT '',
                `ETAG` varchar(64) NOT NULL DEFAULT '',
                `DOMOFON` varchar(64) NOT NULL DEFAULT '',
                `LAT` varchar(32) NOT NULL DEFAULT '',
                `LON` varchar(32) NOT NULL DEFAULT '',
                `ZONE_ID` int(11) NOT NULL DEFAULT '0',
                PRIMARY KEY (`ID`),
                KEY `IX_USER_ID` (`USER_ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}
