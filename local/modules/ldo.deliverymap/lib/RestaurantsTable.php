<?php
namespace Ldo\Deliverymap;

use Bitrix\Main\Entity;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;

class RestaurantsTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'ldo_delivery_restaurants';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new Entity\StringField('ACTIVE', [
                'required' => true,
                'values' => ['N', 'Y'],
                'default_value' => 'Y'
            ]),
            new Entity\StringField('NAME', [
                'required' => true,
                'validation' => function() {
                    return [new Entity\Validator\Length(null, 255)];
                }
            ]),
            new Entity\TextField('COORDINATES', [
                'required' => true
            ]),
            new Entity\StringField('SITE_ID', [
                'required' => true,
                'validation' => function() {
                    return [new Entity\Validator\Length(null, 2)];
                }
            ]),
            new Entity\StringField('PHONE', [
                'required' => false,
                'default_value' => '',
                'validation' => function() {
                    return [new Entity\Validator\Length(null, 50)];
                }
            ]),
            new Entity\StringField('EMAIL', [
                'required' => false,
                'default_value' => '',
                'validation' => function() {
                    return [new Entity\Validator\Length(null, 100)];
                }
            ]),
            new Entity\TextField('REQUISITES', [
                'required' => false,
                'default_value' => ''
            ]),
            new Entity\StringField('XML_ID', [
                'required' => false,
                'default_value' => '',
                'validation' => function() {
                    return [new Entity\Validator\Length(null, 255)];
                }
            ]),
        ];
    }

    /**
     * Получить список активных ресторанов
     *
     * @param array $extraFilter Дополнительные параметры фильтра
     * @param array $order Порядок сортировки
     * @return array
     */
    public static function getActiveList($extraFilter = [], $order = ['ID' => 'ASC'])
    {
        $filter = array_merge(['=ACTIVE' => 'Y'], $extraFilter);

        $result = self::getList([
            'filter' => $filter,
            'order' => $order
        ]);

        $list = [];
        while ($row = $result->fetch()) {
            $list[] = $row;
        }

        return $list;
    }

    /**
     * Получить ресторан по ID
     *
     * @param int $id
     * @return array|false
     */
    public static function getById($id)
    {
        $result = self::getList([
            'filter' => ['=ID' => $id],
            'limit' => 1
        ]);

        return $result->fetch();
    }

    /**
     * Получить ресторан по XML_ID
     *
     * @param string $xmlId
     * @return array|false
     */
    public static function getByXmlId($xmlId)
    {
        $result = self::getList([
            'filter' => ['=XML_ID' => $xmlId],
            'limit' => 1
        ]);

        return $result->fetch();
    }

    /**
     * Добавить ресторан
     *
     * @param array $data
     * @return \Bitrix\Main\Entity\AddResult
     */
    public static function addRestaurant($data)
    {
        return self::add($data);
    }

    /**
     * Обновить ресторан
     *
     * @param int $id
     * @param array $data
     * @return \Bitrix\Main\Entity\UpdateResult
     */
    public static function updateRestaurant($id, $data)
    {
        return self::update($id, $data);
    }

    /**
     * Удалить ресторан
     *
     * @param int $id
     * @return \Bitrix\Main\Entity\DeleteResult
     */
    public static function deleteRestaurant($id)
    {
        return self::delete($id);
    }

    /**
     * Получить рестораны по сайту
     *
     * @param string $siteId
     * @param bool $onlyActive Только активные
     * @return array
     */
    public static function getBySiteId($siteId, $onlyActive = true)
    {
        $filter = ['=SITE_ID' => $siteId];

        if ($onlyActive) {
            $filter['=ACTIVE'] = 'Y';
        }

        $result = self::getList([
            'filter' => $filter,
            'order' => ['ID' => 'ASC']
        ]);

        $list = [];
        while ($row = $result->fetch()) {
            $list[] = $row;
        }

        return $list;
    }

    /**
     * Переключить активность ресторана
     *
     * @param int $id
     * @param string $active Y|N
     * @return \Bitrix\Main\Entity\UpdateResult
     */
    public static function setActive($id, $active = 'Y')
    {
        return self::update($id, ['ACTIVE' => $active === 'Y' ? 'Y' : 'N']);
    }
}
