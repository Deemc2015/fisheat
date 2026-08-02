<?php
namespace Ldo\Iiko;

use Bitrix\Main\Application;
use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

class SettingsTable extends Entity\DataManager
{
    private static $tableChecked = false;

    public static function getTableName()
    {
        return 'ldo_iiko_settings';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new Entity\StringField('SITE_ID', [
                'required' => true,
                'validation' => function() {
                    return [new Entity\Validator\Length(null, 2)];
                }
            ]),
            new Entity\StringField('API_LOGIN', [
                'default_value' => '',
                'validation' => function() {
                    return [new Entity\Validator\Length(null, 255)];
                }
            ]),
            new Entity\StringField('SECRET', [
                'default_value' => '',
                'validation' => function() {
                    return [new Entity\Validator\Length(null, 255)];
                }
            ]),
            new Entity\StringField('APP_ID', [
                'default_value' => '',
                'validation' => function() {
                    return [new Entity\Validator\Length(null, 255)];
                }
            ]),
            new Entity\StringField('CHECK_STATUS', [
                'default_value' => 'N',
                'validation' => function() {
                    return [new Entity\Validator\Length(null, 10)];
                }
            ]),
            new Entity\DatetimeField('CHECK_DATE'),
        ];
    }

    /**
     * Гарантирует наличие таблицы в БД и соответствие текущей схеме.
     * - если таблицы нет — создаёт её;
     * - если таблица есть, но по старой схеме (SITE_ID/NAME/VALUE, без колонки API_LOGIN) —
     *   пересоздаёт с новой структурой (старые данные ключ-значение не используются);
     * - если схема актуальна — ничего не делает.
     */
    public static function ensureTable()
    {
        if (self::$tableChecked) {
            return;
        }

        self::$tableChecked = true;

        $connection = Application::getConnection();
        $tableName = self::getTableName();

        if (!$connection->isTableExists($tableName)) {
            self::createTable($connection);
            return;
        }

        // Проверяем наличие новых колонок (миграция со старой схемы)
        $rs = $connection->query("SHOW COLUMNS FROM `" . $tableName . "`");
        $columns = [];
        while ($column = $rs->fetch()) {
            $columns[strtoupper((string)$column['Field'])] = true;
        }

        if (!isset($columns['API_LOGIN'])) {
            $connection->queryExecute("DROP TABLE IF EXISTS `" . $tableName . "`");
            self::createTable($connection);
        }
    }

    private static function createTable($connection)
    {
        $connection->queryExecute("
            CREATE TABLE IF NOT EXISTS `ldo_iiko_settings` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `SITE_ID` varchar(2) NOT NULL,
                `API_LOGIN` varchar(255) NOT NULL DEFAULT '',
                `SECRET` varchar(255) NOT NULL DEFAULT '',
                `APP_ID` varchar(255) NOT NULL DEFAULT '',
                `CHECK_STATUS` varchar(10) NOT NULL DEFAULT 'N',
                `CHECK_DATE` datetime DEFAULT NULL,
                PRIMARY KEY (`ID`),
                UNIQUE KEY `IX_SITE_ID` (`SITE_ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /**
     * Получить строку настроек для сайта (все настройки в одной строке).
     * Возвращает массив или null.
     */
    public static function getRow($siteId)
    {
        self::ensureTable();

        return self::getList([
            'filter' => ['=SITE_ID' => $siteId]
        ])->fetch();
    }

    /**
     * Сохранить настройки iiko для сайта одной строкой.
     * При сохранении статус проверки сбрасывается в 'N'.
     */
    public static function save($siteId, $apiLogin, $secret, $appId)
    {
        self::ensureTable();

        $row = self::getList([
            'filter' => ['=SITE_ID' => $siteId]
        ])->fetch();

        $fields = [
            'API_LOGIN' => $apiLogin,
            'SECRET' => $secret,
            'APP_ID' => $appId,
            'CHECK_STATUS' => 'N',
            'CHECK_DATE' => null,
        ];

        if ($row) {
            self::update($row['ID'], $fields);
        } else {
            self::add(array_merge(['SITE_ID' => $siteId], $fields));
        }
    }

    /**
     * Установить статус проверки данных.
     * Допустимые значения: 'N' — не проверялось, 'OK' — успешно, 'ERROR' — ошибка.
     */
    public static function setStatus($siteId, $status)
    {
        self::ensureTable();

        $row = self::getList([
            'filter' => ['=SITE_ID' => $siteId]
        ])->fetch();

        if (!$row) {
            return;
        }

        self::update($row['ID'], [
            'CHECK_STATUS' => $status,
            'CHECK_DATE' => new DateTime(),
        ]);
    }
}
