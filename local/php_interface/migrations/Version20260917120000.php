<?php

namespace Sprint\Migration;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;

/**
 * Приведение состояния prokhorov.trafic к корректному после рефактора:
 *
 * 1. В таблице prokhorov_trafic_visit колонки PAGE и REFERER были созданы как
 *    varchar(255), хотя карта VisitTable описывала 500 символов (configureSize()
 *    не влияет на DDL). Длинный REQUEST_URI с UTM-метками не влезал в колонку и
 *    ломал запись визита — колонки расширяются до varchar(500).
 *
 * 2. Кэш правил фильтрации (/prokhorov.trafic/rules) не сбрасывался из-за
 *    неверного порядка аргументов Cache::clean() в Rules::clearCache(), поэтому
 *    до часа работали устаревшие правила (добавленный IP не попадал в серый или
 *    чёрный список). Сам вызов в Rules::clearCache() уже исправлен, а здесь
 *    удаляется кэш, оставшийся от старой версии.
 */
class Version20260917120000 extends Version
{
    protected $author = "79177695923";

    protected $description = "prokhorov.trafic: PAGE/REFERER до varchar(500) и сброс устаревшего кэша правил";

    protected $moduleVersion = "5.6.1";

    /**
     * @return bool|void
     */
    public function up()
    {
        $connection = Application::getConnection();
        $tableName = 'prokhorov_trafic_visit';

        if ($connection->isTableExists($tableName)) {
            /* MODIFY идемпотентен: повторный запуск просто выставит ту же длину */
            $connection->query(
                'ALTER TABLE `' . $tableName . '` '
                . 'MODIFY `PAGE` varchar(500) NOT NULL, '
                . 'MODIFY `REFERER` varchar(500) NOT NULL'
            );
        }

        /* Совпадает с Rules::CACHE_DIR — каталог кэша правил фильтрации */
        Cache::createInstance()->cleanDir('/prokhorov.trafic/rules');

        return true;
    }

    /**
     * @return bool|void
     */
    public function down()
    {
        return true;
    }
}
