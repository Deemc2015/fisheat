<?php
namespace Ldo\Deliverymap;

use Bitrix\Main\Entity;
use Bitrix\Main\Localization\Loc;

class SettingsTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'ldo_delivery_settings';
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
            new Entity\StringField('NAME', [
                'required' => true,
                'validation' => function() {
                    return [new Entity\Validator\Length(null, 50)];
                }
            ]),
            new Entity\TextField('VALUE', [
                'default_value' => ''
            ]),
        ];
    }

    /**
     * Получить настройку для сайта
     */
    public static function get($siteId, $name, $default = '')
    {
        $row = self::getList([
            'filter' => ['=SITE_ID' => $siteId, '=NAME' => $name]
        ])->fetch();

        return $row ? $row['VALUE'] : $default;
    }

    /**
     * Установить настройку для сайта
     */
    public static function set($siteId, $name, $value)
    {
        $row = self::getList([
            'filter' => ['=SITE_ID' => $siteId, '=NAME' => $name]
        ])->fetch();

        if ($row) {
            self::update($row['ID'], ['VALUE' => $value]);
        } else {
            self::add([
                'SITE_ID' => $siteId,
                'NAME' => $name,
                'VALUE' => $value
            ]);
        }
    }
}
