<?php

namespace Keyup\Cleartrafic\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

/**
 * Адреса для уведомлений о событиях фильтрации.
 */
class EmailTable extends DataManager
{
    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'keyup_cleartrafic_email';
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

            (new StringField('EMAIL'))
                ->configureRequired()
                ->configureSize(255),

            (new DatetimeField('DATE_CREATE'))
                ->configureDefaultValueNow(),
        ];
    }
}
