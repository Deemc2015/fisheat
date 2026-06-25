<?php
namespace Ldo\Deliverymap;

use Bitrix\Main\Entity;
use Bitrix\Main\Localization\Loc;

class DeliveryZoneTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'ldo_delivery_zones';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new Entity\StringField('NAME', [
                'required' => true,
                'validation' => function() {
                    return [new Entity\Validator\Length(null, 255)];
                }
            ]),
            new Entity\IntegerField('PRICE', [
                'required' => true,
                'default_value' => 0
            ]),
            new Entity\IntegerField('SORT', [
                'required' => false,
                'default_value' => 500
            ]),
            new Entity\IntegerField('DELIVERY_TIME', [
                'required' => false,
                'default_value' => 0
            ]),
            new Entity\IntegerField('FREE_DELIVERY_PRICE', [
                'required' => true,
                'default_value' => 0
            ]),
            new Entity\StringField('COLOR', [
                'required' => true,
                'default_value' => '#00FF00'
            ]),
            new Entity\TextField('COORDINATES', [
                'required' => true,
                'serialized' => true
            ]),
            new Entity\StringField('SITE_ID', [
                'required' => true,
                'default_value' => '',
                'validation' => function() {
                    return [new Entity\Validator\Length(null, 2)];
                }
            ]),
            new Entity\IntegerField('MIN_ORDER_PRICE', [
                'default_value' => 0
            ]),
            new Entity\StringField('ACTIVE', [
                'values' => ['N', 'Y'],
                'default_value' => 'Y'
            ])
        ];
    }
}