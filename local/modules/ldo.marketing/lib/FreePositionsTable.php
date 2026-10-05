<?php
namespace Ldo\Marketing;

use Bitrix\Main\Application;
use Bitrix\Main\Entity;

/**
 * ORM-сущность "Бесплатные позиции к товарам".
 *
 * Строка таблицы — правило, которое при добавлении в корзину товара из
 * выбранного раздела каталога автоматически добавляет (бесплатно) заданные
 * позиции в количестве, рассчитанном по числу порций.
 *
 * Множественные поля SECTION_IDS / PRODUCT_IDS хранятся как JSON-массив ID.
 */
class FreePositionsTable extends Entity\DataManager
{
    /** Имя таблицы в БД. */
    public static function getTableName()
    {
        return 'ldo_marketing_free_positions';
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
            new Entity\StringField('NAME', [
                'required'   => true,
                'validation' => function () {
                    return [new Entity\Validator\Length(null, 255)];
                },
            ]),
            // JSON-массив ID разделов каталога.
            new Entity\TextField('SECTION_IDS', [
                'required'      => false,
                'default_value' => '',
            ]),
            // JSON-массив ID товаров (бесплатных позиций).
            new Entity\TextField('PRODUCT_IDS', [
                'required'      => false,
                'default_value' => '',
            ]),
            // Количество порций для применения.
            new Entity\IntegerField('PORTIONS', [
                'required'      => true,
                'default_value' => 1,
            ]),
            // Привязка к сайту (пока s1).
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
     * Гарантировать наличие таблицы в БД (на случай, если модуль установлен,
     * но таблица по каким-то причинам отсутствует).
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
                `NAME` varchar(255) NOT NULL,
                `SECTION_IDS` text,
                `PRODUCT_IDS` text,
                `PORTIONS` int(11) NOT NULL DEFAULT '1',
                `SITE_ID` varchar(2) NOT NULL DEFAULT 's1',
                PRIMARY KEY (`ID`),
                KEY `IX_SITE_ID` (`SITE_ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /**
     * Список записей по сайту.
     *
     * @param string $siteId
     * @return array
     */
    public static function getBySite($siteId)
    {
        $result = self::getList([
            'filter' => ['=SITE_ID' => $siteId],
            'order'  => ['ID' => 'ASC'],
        ]);

        return $result->fetchAll();
    }
}
