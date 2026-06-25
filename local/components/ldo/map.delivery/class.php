<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use \Bitrix\Main\Data\Cache;
use Bitrix\Main\Loader;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Ldo\Deliverymap\DeliveryZoneTable;

class CDeliveryMap extends \CBitrixComponent implements Controllerable
{
    public function executeComponent()
    {
        $moduleId = 'ldo.deliverymap';

        // Получаем настройки модуля
        $this->arResult['YANDEX_API_KEY'] = Option::get($moduleId, 'yandex_api_key', '');
        $this->arResult['DEFAULT_LAT'] = Option::get($moduleId, 'default_lat', '54.7355');
        $this->arResult['DEFAULT_LNG'] = Option::get($moduleId, 'default_lng', '55.9587');
        $this->arResult['DEFAULT_ZOOM'] = (int)Option::get($moduleId, 'default_zoom', '11');

        $this->includeComponentTemplate();
    }

    public function configureActions()
    {
        return [
            'sendForm' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class
                ],
            ],
            'getZones' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class
                ],
            ]
        ];
    }

    /**
     * Получение зон доставки из БД
     */
    public function getZonesAction()
    {
        $zones = [];

        try {
            if (!Loader::includeModule('ldo.deliverymap')) {
                return ['success' => false, 'error' => 'Модуль доставки не установлен'];
            }

            $siteId = Context::getCurrent()->getSite();

            $dbZones = DeliveryZoneTable::getList([
                'filter' => [
                    '=ACTIVE' => 'Y',
                    '=SITE_ID' => $siteId
                ],
                'order' => ['SORT' => 'ASC', 'ID' => 'ASC']
            ]);

            while ($zone = $dbZones->fetch()) {
                $coordinates = $zone['COORDINATES'];
                if (is_string($coordinates)) {
                    $coordinates = json_decode($coordinates, true);
                }

                if (!is_array($coordinates) || count($coordinates) < 3) {
                    continue;
                }

                $ymapsCoords = [];
                foreach ($coordinates as $point) {
                    $ymapsCoords[] = [(float)$point[0], (float)$point[1]];
                }

                $zones[] = [
                    'ID' => (int)$zone['ID'],
                    'NAME' => $zone['NAME'],
                    'PRICE' => (float)$zone['PRICE'],
                    'FREE_DELIVERY_PRICE' => (float)$zone['FREE_DELIVERY_PRICE'],
                    'DELIVERY_TIME' => (int)$zone['DELIVERY_TIME'],
                    'MIN_ORDER_PRICE' => (float)$zone['MIN_ORDER_PRICE'],
                    'COLOR' => $zone['COLOR'],
                    'COORDINATES' => $ymapsCoords
                ];
            }

        } catch (Exception $e) {
            AddMessage2Log('Ошибка получения зон доставки: ' . $e->getMessage(), 'ldo.deliverymap');
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return ['success' => true, 'zones' => $zones];
    }

    public function sendFormAction($post)
    {
        $lat = $post['arParams']['lat'] ?? null;
        $lon = $post['arParams']['lon'] ?? null;

        $lat = htmlspecialchars($lat, ENT_QUOTES, 'UTF-8');
        $lon = htmlspecialchars($lon, ENT_QUOTES, 'UTF-8');

        if (!$lat || !$lon) {
            return ['error' => 'Не указаны координаты'];
        }

        try {
            if (!Loader::includeModule('ldo.deliverymap')) {
                return ['error' => 'Модуль доставки не установлен'];
            }

            // Ищем зону по координатам
            $zone = $this->findZoneByCoordinates((float)$lat, (float)$lon);

            if (!$zone) {
                return [
                    'error' => 'Адрес вне зоны доставки',
                    'in_zone' => false
                ];
            }

            return [
                'success' => true,
                'in_zone' => true,
                'zone_id' => $zone['ID'],
                'zone_name' => $zone['NAME'],
                'price' => $zone['PRICE'],
                'deliveryTime' => $zone['DELIVERY_TIME'],
                'minPrice' => $zone['MIN_ORDER_PRICE'],
                'priceFreeDelivery' => $zone['FREE_DELIVERY_PRICE']
            ];

        } catch (Exception $e) {
            AddMessage2Log('Ошибка расчета доставки: ' . $e->getMessage(), 'ldo.deliverymap');
            return ['error' => 'Ошибка расчета доставки'];
        }
    }

    private function findZoneByCoordinates($lat, $lon)
    {
        $zones = $this->getZonesAction();
        if (!$zones['success']) {
            return null;
        }

        foreach ($zones['zones'] as $zone) {
            $coords = $zone['COORDINATES'];
            if ($this->isPointInPolygon([$lat, $lon], $coords)) {
                return $zone;
            }
        }

        return null;
    }

    private function isPointInPolygon($point, $polygon)
    {
        $x = $point[0];
        $y = $point[1];
        $inside = false;
        $j = count($polygon) - 1;

        for ($i = 0; $i < count($polygon); $i++) {
            $xi = $polygon[$i][0];
            $yi = $polygon[$i][1];
            $xj = $polygon[$j][0];
            $yj = $polygon[$j][1];

            $intersect = (($yi > $y) != ($yj > $y)) &&
                ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = !$inside;
            }
            $j = $i;
        }

        return $inside;
    }
}
?>