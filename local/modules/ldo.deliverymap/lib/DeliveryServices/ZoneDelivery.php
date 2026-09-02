<?php

namespace Ldo\Deliverymap\DeliveryServices;

use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Sale\Delivery\CalculationResult;
use Bitrix\Sale\Delivery\Services\Base;
use Bitrix\Sale\Shipment;
use Ldo\Deliverymap\DeliveryZoneTable;

/**
 * Служба доставки «Доставка»: стоимость рассчитывается по зоне выбранного адреса.
 *
 * Адрес берётся из свойства заказа ADDRESS_ID (таблица ldo_iiko_user_address,
 * модуль ldo.iiko). Зона адреса привязана к адресу при его добавлении (ZONE_ID),
 * поэтому расчёт выполняется строго по ней — без гео-поиска по координатам и без
 * подстановки «первого адреса» пользователя. Адрес без зоны (или с неактивной
 * зоной) в расчёте не участвует.
 *
 * Цена возвращается из таблицы зон (ldo_delivery_zones.PRICE).
 *
 * Используется и при отображении стоимости (OrderHelper::calcDeliveries),
 * и при сохранении заказа (order->save()), поэтому цена зоны попадает
 * в заказ на бэкенде, а скидки/промокоды начисляются от неё.
 */
class ZoneDelivery extends Base
{
    /**
     * @return string
     */
    public static function getClassTitle()
    {
        return 'Доставка по зонам';
    }

    /**
     * @return string
     */
    public static function getClassDescription()
    {
        return 'Стоимость доставки рассчитывается по зоне доставки выбранного адреса';
    }

    /**
     * Рассчитывает стоимость доставки по зоне выбранного адреса.
     *
     * @param Shipment $shipment
     * @return CalculationResult
     */
    protected function calculateConcrete(\Bitrix\Sale\Shipment $shipment)
    {
        $result = new CalculationResult();

        if (!Loader::includeModule('ldo.deliverymap') || !Loader::includeModule('ldo.iiko')) {
            $result->addError(new Error('Модуль зон доставки недоступен'));
            return $result;
        }

        $zone = $this->resolveZone($shipment);
        if ($zone === null) {
            $result->addError(new Error('Адрес не входит в зону доставки'));
            return $result;
        }

        $price = (float)$zone['PRICE'];

        $result->setDeliveryPrice($price);
        $result->setData([
            'ZONE_ID' => (int)$zone['ID'],
            'ZONE_NAME' => (string)$zone['NAME'],
            'PRICE' => $price,
        ]);

        return $result;
    }

    /**
     * Определяет зону доставки строго по зоне, привязанной к адресу (ZONE_ID).
     *
     * Адрес без зоны не существует по бизнес-правилу (добавление вне зоны запрещено),
     * поэтому гео-поиск по координатам здесь не выполняется. Если у адреса зоны нет
     * или она неактивна — адрес не участвует в расчёте (вернёт null).
     *
     * @param Shipment $shipment
     * @return array|null
     */
    private function resolveZone(Shipment $shipment)
    {
        $address = $this->resolveAddress($shipment);
        if (!$address) {
            return null;
        }

        $zoneId = (int)($address['UF_ZONE_ID'] ?? 0);
        if ($zoneId <= 0) {
            return null;
        }

        $zone = DeliveryZoneTable::getRowById($zoneId);
        if (!$zone || ($zone['ACTIVE'] ?? '') !== 'Y') {
            return null;
        }

        return $zone;
    }

    /**
     * Возвращает данные выбранного адреса из таблицы ldo_iiko_user_address.
     *
     * @param Shipment $shipment
     * @return array|null
     */
    private function resolveAddress(Shipment $shipment)
    {
        $addressId = 0;

        $order = $shipment->getParentOrder();
        if ($order) {
            foreach ($order->getPropertyCollection() as $property) {
                if ($property->getField('CODE') === 'ADDRESS_ID') {
                    $addressId = (int)$property->getValue();
                    break;
                }
            }
        }

        // В заказе обязательно должен быть передан ID выбранного адреса.
        // Не подставляем «отмеченный»/первый адрес пользователя — если адрес
        // не выбран, доставку по зоне рассчитать нельзя.
        if ($addressId <= 0) {
            return null;
        }

        if (!Loader::includeModule('ldo.iiko')) {
            return null;
        }

        $address = \Ldo\Iiko\UserAddress::getById($addressId);
        if (!$address) {
            return null;
        }

        return [
            'UF_ZONE_ID' => (int)($address['ZONE_ID'] ?? 0),
            'UF_SHIRINA' => $address['LAT'] ?? 0,
            'UF_DOLGOTA' => $address['LON'] ?? 0,
            'UF_PRICE' => 0,
        ];
    }
}
