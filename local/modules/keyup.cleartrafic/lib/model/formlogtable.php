<?php

namespace Keyup\Cleartrafic\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

/**
 * Лог отправок публичной формы: используется для ограничения частоты
 * обращения (не чаще одного раза в минуту с одного IP).
 */
class FormLogTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'keyup_cleartrafic_form_log';
    }

    /**
     * @return array
     */
    public static function getMap()
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new StringField('IP'))
                ->configureRequired()
                ->configureSize(45),

            (new DatetimeField('DATE_CREATE'))
                ->configureDefaultValueNow(),
        ];
    }
}
