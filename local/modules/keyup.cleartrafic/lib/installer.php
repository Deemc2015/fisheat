<?php

namespace Keyup\Cleartrafic;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\DB\Connection;
use Keyup\Cleartrafic\Model\EmailTable;
use Keyup\Cleartrafic\Model\FormLogTable;
use Keyup\Cleartrafic\Model\RuleTable;
use Keyup\Cleartrafic\Model\VisitTable;

/**
 * Создание и удаление таблиц модуля средствами D7 ORM.
 *
 * Таблицы создаются вызовом Entity::createDbTable() — без SQL-скриптов,
 * индексы добавляются через Connection::createIndex().
 */
class Installer
{
    /**
     * Классы сущностей модуля в порядке создания.
     *
     * @return string[]
     */
    public static function getTableClasses()
    {
        return [
            VisitTable::class,
            RuleTable::class,
            EmailTable::class,
            FormLogTable::class,
        ];
    }

    /**
     * Описание индексов: имя => список колонок.
     *
     * @return array
     */
    public static function getIndexes()
    {
        return [
            VisitTable::class => [
                'ix_pf_visit_ip'         => ['IP'],
                'ix_pf_visit_date'       => ['DATE_CREATE'],
                'ix_pf_visit_match_type' => ['MATCH_TYPE'],
            ],
            RuleTable::class => [
                'ix_pf_rule_type_value' => ['TYPE', 'VALUE'],
                'ix_pf_rule_active'     => ['ACTIVE'],
            ],
            EmailTable::class => [
                'ix_pf_email' => ['EMAIL'],
            ],
            FormLogTable::class => [
                'ix_pf_form_log_ip_date' => ['IP', 'DATE_CREATE'],
            ],
        ];
    }

    /**
     * Создаёт все таблицы модуля и индексы.
     *
     * @return void
     */
    public static function create()
    {
        $connection = Application::getConnection();

        foreach (self::getTableClasses() as $className) {
            self::createTable($connection, $className);
        }

        foreach (self::getIndexes() as $className => $indexes) {
            $tableName = self::getTableName($className);

            foreach ($indexes as $indexName => $columns) {
                self::createIndex($connection, $tableName, $indexName, $columns);
            }
        }

        /* После установки кэш прежних правил не должен применяться */
        self::clearCache();
    }

    /**
     * Удаляет таблицы модуля.
     *
     * Вызывается только при удалении модуля без сохранения данных:
     * решение администратора передаёт метод DoUninstall().
     *
     * @return void
     */
    public static function drop()
    {
        $connection = Application::getConnection();

        foreach (self::getTableClasses() as $className) {
            $tableName = self::getTableName($className);

            if ($connection->isTableExists($tableName)) {
                $connection->dropTable($tableName);
            }
        }

        /* В кэше могли остаться правила из удалённых таблиц */
        self::clearCache();
    }

    /**
     * Сбрасывает кэш правил модуля (совпадает с Rules::CACHE_DIR).
     *
     * @return void
     */
    public static function clearCache()
    {
        Cache::createInstance()->cleanDir('/keyup.cleartrafic/rules');
    }

    /**
     * @param string $className
     *
     * @return string
     */
    protected static function getTableName($className)
    {
        return $className::getTableName();
    }

    /**
     * @param Connection $connection
     * @param string     $className
     *
     * @return void
     */
    protected static function createTable(Connection $connection, $className)
    {
        $tableName = self::getTableName($className);

        if ($connection->isTableExists($tableName)) {
            return;
        }

        $className::getEntity()->createDbTable();
    }

    /**
     * @param Connection $connection
     * @param string     $tableName
     * @param string     $indexName
     * @param array      $columns
     *
     * @return void
     */
    protected static function createIndex(Connection $connection, $tableName, $indexName, array $columns)
    {
        if (!$connection->isTableExists($tableName)) {
            return;
        }

        try {
            /* Метод принимает список колонок, а не имя индекса */
            if ($connection->isIndexExists($tableName, $columns)) {
                return;
            }
        } catch (\Throwable $e) {
            // Если проверка недоступна — пробуем создать, ошибку повтора игнорируем ниже.
        }

        try {
            $connection->createIndex($tableName, $indexName, $columns);
        } catch (\Throwable $e) {
            // Индекс уже существует или СУБД не поддерживает операцию — не прерываем установку.
        }
    }
}
