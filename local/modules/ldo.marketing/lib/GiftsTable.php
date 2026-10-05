<?php
namespace Ldo\Marketing;

use Bitrix\Main\Application;
use Bitrix\Main\Entity;

/**
 * ORM-сущность "Подарки к заказам" (уровни подарков).
 *
 * Раньше данные хранились в инфоблоке подарков (API_CODE = gifts, ID = 8);
 * теперь — в собственной таблице модуля. Строка = уровень подарка:
 * NAME — название, SUM — сумма корзины, PRODUCT_IDS — JSON-массив ID
 * прикреплённых товаров, SITE_ID — привязка к сайту.
 */
class GiftsTable extends Entity\DataManager
{
    /** Имя таблицы в БД. */
    public static function getTableName()
    {
        return 'ldo_marketing_gift_levels';
    }

    /**
     * Описание полей сущности.
     *
     * @return array
     */
    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary'      => true,
                'autocomplete' => true,
            ]),
            new Entity\StringField('ACTIVE', [
                'required'      => true,
                'values'        => ['N', 'Y'],
                'default_value' => 'Y',
            ]),
            new Entity\StringField('NAME', [
                'required'   => true,
                'validation' => function () {
                    return [new Entity\Validator\Length(null, 255)];
                },
            ]),
            // Сумма корзины (порог), при которой доступен уровень.
            new Entity\IntegerField('SUM', [
                'required'      => true,
                'default_value' => 0,
            ]),
            // JSON-массив ID товаров уровня.
            new Entity\TextField('PRODUCT_IDS', [
                'required'      => false,
                'default_value' => '',
            ]),
            new Entity\IntegerField('SORT', [
                'required'      => true,
                'default_value' => 500,
            ]),
            // Привязка к сайту.
            new Entity\StringField('SITE_ID', [
                'required'      => true,
                'default_value' => 's1',
                'validation'   => function () {
                    return [new Entity\Validator\Length(null, 2)];
                },
            ]),
        ];
    }

    /**
     * Гарантировать наличие таблицы в БД.
     *
     * @return void
     */
    public static function ensureTable()
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists(self::getTableName())) {
            self::createTable($connection);
        }
    }

    /**
     * Создание таблицы.
     *
     * @param \Bitrix\Main\DB\Connection $connection
     * @return void
     */
    public static function createTable($connection)
    {
        if ($connection === null) {
            $connection = Application::getConnection();
        }

        $connection->queryExecute("
            CREATE TABLE IF NOT EXISTS `" . self::getTableName() . "` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `ACTIVE` char(1) NOT NULL DEFAULT 'Y',
                `NAME` varchar(255) NOT NULL,
                `SUM` int(11) NOT NULL DEFAULT '0',
                `PRODUCT_IDS` text,
                `SORT` int(11) NOT NULL DEFAULT '500',
                `SITE_ID` varchar(2) NOT NULL DEFAULT 's1',
                PRIMARY KEY (`ID`),
                KEY `IX_SITE_ID` (`SITE_ID`),
                KEY `IX_ACTIVE` (`ACTIVE`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /**
     * Список уровней по сайту (сортировка как в прежнем инфоблоке).
     *
     * @param string $siteId
     * @return array
     */
    public static function getBySite($siteId)
    {
        $result = self::getList([
            'filter' => ['=SITE_ID' => $siteId],
            'order'  => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);

        return $result->fetchAll();
    }
}
