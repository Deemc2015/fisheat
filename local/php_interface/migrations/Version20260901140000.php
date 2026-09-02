<?php

namespace Sprint\Migration;

use Bitrix\Main\Loader;
use Bitrix\Sale\Delivery\Services\Table as DeliveryServicesTable;
use Bitrix\Sale\Internals\OrderPropsGroupTable;
use Bitrix\Sale\Internals\PersonTypeTable;
use CSaleOrderProps;
use CSaleOrderPropsGroup;

/**
 * Стоимость доставки по зоне на бэкенде:
 * 1) добавляет служебное свойство заказа ADDRESS_ID;
 * 2) подключает службу «Доставка» к классу ZoneDelivery (цена по зоне).
 */
class Version20260901140000 extends Version
{
    protected $author = "LDO";

    protected $description = "Доставка по зонам: свойство ADDRESS_ID + служба доставки ZoneDelivery";

    protected $moduleVersion = "5.6.1";

    private $optionDeliveryOrigin = 'ldo_zone_delivery_origin_class';

    public function up()
    {
        Loader::includeModule('sale');
        Loader::includeModule('ldo.deliverymap');

        // 1. Свойство заказа ADDRESS_ID (служебное) для всех типов плательщиков
        $personTypes = PersonTypeTable::getList(['select' => ['ID']])->fetchAll();

        foreach ($personTypes as $pt) {
            $personTypeId = (int)$pt['ID'];

            $exists = \Bitrix\Sale\Internals\OrderPropsTable::getList([
                'filter' => ['=CODE' => 'ADDRESS_ID', '=PERSON_TYPE_ID' => $personTypeId],
            ])->fetch();
            if ($exists) {
                continue;
            }

            $groupId = 0;
            $group = OrderPropsGroupTable::getList([
                'select' => ['ID'],
                'filter' => ['=PERSON_TYPE_ID' => $personTypeId],
                'order' => ['ID' => 'ASC'],
                'limit' => 1,
            ])->fetch();
            if ($group) {
                $groupId = (int)$group['ID'];
            } else {
                $groupId = (int)(new CSaleOrderPropsGroup())->Add([
                    'PERSON_TYPE_ID' => $personTypeId,
                    'NAME' => 'Служебные',
                    'SORT' => 700,
                ]);
            }

            $prop = new CSaleOrderProps();
            $result = $prop->Add([
                'PERSON_TYPE_ID' => $personTypeId,
                'NAME' => 'ID адреса доставки',
                'CODE' => 'ADDRESS_ID',
                'TYPE' => 'STRING',
                'REQUIED' => 'N',
                'DEFAULT_VALUE' => '',
                'SORT' => 700,
                'USER_PROPS' => 'N',
                'IS_LOCATION' => 'N',
                'PROPS_GROUP_ID' => $groupId,
                'DESCRIPTION' => '',
                'IS_EMAIL' => 'N',
                'IS_PROFILE_NAME' => 'N',
                'IS_PAYER' => 'N',
                'IS_ZIP' => 'N',
                'IS_PHONE' => 'N',
                'UTIL' => 'Y',
                'SETTINGS' => ['SIZE' => 30, 'ROWS' => 1],
                'ENTITY_REGISTRY_TYPE' => 'ORDER',
            ]);

            $this->out('ADDRESS_ID свойство (person_type=' . $personTypeId . '): ' . ($result ? 'добавлено' : 'ошибка'));
        }

        // 2. Служба «Доставка» → ZoneDelivery
        $zoneClass = \Ldo\Deliverymap\DeliveryServices\ZoneDelivery::class;
        $originMap = [];

        $deliveries = DeliveryServicesTable::getList([
            'select' => ['ID', 'NAME', 'CLASS_NAME', 'ACTIVE', 'PARENT_ID'],
        ])->fetchAll();

        foreach ($deliveries as $delivery) {
            if ((int)$delivery['PARENT_ID'] > 0) {
                continue; // профили не трогаем
            }
            if (mb_stripos((string)$delivery['NAME'], 'самовывоз') !== false) {
                continue; // самовывоз не трогаем
            }
            if (mb_stripos((string)($delivery['CLASS_NAME'] ?? ''), 'EmptyDeliveryService') !== false) {
                continue; // «Без доставки» не трогаем
            }
            if ($delivery['CLASS_NAME'] === $zoneClass) {
                continue;
            }

            $originMap[$delivery['ID']] = $delivery['CLASS_NAME'];
            DeliveryServicesTable::update((int)$delivery['ID'], ['CLASS_NAME' => $zoneClass]);
            $this->out('Служба «' . $delivery['NAME'] . '» (ID=' . $delivery['ID'] . ') переключена на ZoneDelivery');
        }

        if (!empty($originMap)) {
            \Bitrix\Main\Config\Option::set('ldo.deliverymap', $this->optionDeliveryOrigin, serialize($originMap));
        }

        // Сброс кэша служб доставки
        \Bitrix\Main\Data\Cache::clearCache(true);

        return true;
    }

    public function down()
    {
        Loader::includeModule('sale');
        Loader::includeModule('ldo.deliverymap');

        // Удаляем свойство ADDRESS_ID
        $props = \Bitrix\Sale\Internals\OrderPropsTable::getList([
            'filter' => ['=CODE' => 'ADDRESS_ID'],
            'select' => ['ID'],
        ])->fetchAll();
        foreach ($props as $prop) {
            \Bitrix\Sale\Internals\OrderPropsTable::delete((int)$prop['ID']);
        }

        // Возвращаем прежние классы служб доставки
        $originMap = \Bitrix\Main\Config\Option::get('ldo.deliverymap', $this->optionDeliveryOrigin, '');
        if ($originMap !== '') {
            $originMap = unserialize($originMap, ['allowed_classes' => false]);
            if (is_array($originMap)) {
                foreach ($originMap as $deliveryId => $className) {
                    DeliveryServicesTable::update((int)$deliveryId, ['CLASS_NAME' => $className]);
                }
            }
            \Bitrix\Main\Config\Option::delete('ldo.deliverymap', $this->optionDeliveryOrigin);
        }

        \Bitrix\Main\Data\Cache::clearCache(true);

        return true;
    }
}
