<?php

namespace Sprint\Migration;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Data\Cache;

/**
 * Смена названия модуля prokhorov.trafic на keyup.cleartrafic.
 *
 * По соглашению Bitrix таблицы модуля названы по его идентификатору, поэтому
 * таблицы переименовываются: prokhorov_trafic_* -> keyup_cleartrafic_*.
 * Данные при RENAME TABLE сохраняются полностью.
 *
 * Идентификатор модуля и настройки в COption, а также регистрация модуля
 * и обработчика OnPageStart выполняются отдельно (установка модуля заново).
 */
class Version20260917140000 extends Version
{
    protected $author = "79177695923";

    protected $description = "keyup.cleartrafic: переименование таблиц модуля вслед за сменой названия";

    protected $moduleVersion = "5.6.1";

    /** Суффиксы таблиц модуля */
    private $suffixes = ['visit', 'rule', 'email', 'form_log'];

    /**
     * @return bool|void
     */
    public function up()
    {
        $connection = Application::getConnection();

        foreach ($this->suffixes as $suffix) {
            $oldName = 'prokhorov_trafic_' . $suffix;
            $newName = 'keyup_cleartrafic_' . $suffix;

            /* Переименовываем только если старая таблица есть, а новой ещё нет */
            if (!$connection->isTableExists($oldName) || $connection->isTableExists($newName)) {
                continue;
            }

            $connection->query('RENAME TABLE `' . $oldName . '` TO `' . $newName . '`');
        }

        /* Настройки модуля переезжают под новый идентификатор */
        $oldOptions = Option::getForModule('prokhorov.trafic');

        foreach ($oldOptions as $name => $value) {
            if (Option::get('keyup.cleartrafic', $name, null) === null) {
                Option::set('keyup.cleartrafic', $name, $value);
            }
        }

        /* Кэш правил под прежним именем модуля больше не используется */
        $cache = Cache::createInstance();
        $cache->cleanDir('/prokhorov.trafic/rules');
        $cache->cleanDir('/keyup.cleartrafic/rules');

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
