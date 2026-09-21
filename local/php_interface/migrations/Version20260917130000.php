<?php

namespace Sprint\Migration;

use Bitrix\Main\Application;

/**
 * Добавление проверки по User-Agent в prokhorov.trafic.
 *
 * Появился новый тип правила «useragent» и новый тип совпадения визита
 * «useragent». Оба значения длиннее прежних (7 символов), поэтому колонки
 * prokhorov_trafic_rule.TYPE и prokhorov_trafic_visit.MATCH_TYPE
 * расширяются до varchar(9). На новых установках колонки создаются нужной
 * длины автоматически из карты ORM.
 */
class Version20260917130000 extends Version
{
    protected $author = "79177695923";

    protected $description = "prokhorov.trafic: тип правила и совпадения useragent (колонки до varchar(9))";

    protected $moduleVersion = "5.6.1";

    /**
     * @return bool|void
     */
    public function up()
    {
        $connection = Application::getConnection();

        $columns = [
            'prokhorov_trafic_rule'  => 'TYPE',
            'prokhorov_trafic_visit' => 'MATCH_TYPE',
        ];

        foreach ($columns as $tableName => $columnName) {
            if (!$connection->isTableExists($tableName)) {
                continue;
            }

            /* MODIFY идемпотентен: повторный запуск выставит ту же длину */
            $connection->query(
                'ALTER TABLE `' . $tableName . '` '
                . 'MODIFY `' . $columnName . '` varchar(9) NOT NULL'
            );
        }

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
