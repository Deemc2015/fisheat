<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use \Bitrix\Main\Loader;
use \Bitrix\Main\Engine\Contract\Controllerable;
use \Bitrix\Main\Engine\ActionFilter;
use \Bitrix\Main\Localization\Loc;
use Ldo\Deliverymap\DeliveryZoneTable;
use Ldo\Deliverymap\SettingsTable;

class CDeliveryMap extends \CBitrixComponent implements Controllerable
{
    /** @var string ID модуля */
    const MODULE_ID = 'ldo.deliverymap';

    /** @var string ID текущего сайта */
    public $siteId = 's1';

    /**
     * {@inheritdoc}
     */
    public function executeComponent()
    {
        $this->siteId = $this->arParams['SITE_ID'] ?? 's1';

        $this->arResult['SITE_ID'] = $this->siteId;
        $this->arResult['LINK_RESTORANS'] = $this->arParams['LINK_RESTORANS'] ?? '/restorans';

        // Настройки модуля (теперь хранятся в БД через SettingsTable)
        if (Loader::includeModule(self::MODULE_ID)) {
            $this->arResult['YANDEX_API_KEY'] = SettingsTable::get($this->siteId, 'yandex_api_key', '');
            $this->arResult['DEFAULT_LAT'] = (float)SettingsTable::get($this->siteId, 'default_lat', '54.7355');
            $this->arResult['DEFAULT_LNG'] = (float)SettingsTable::get($this->siteId, 'default_lng', '55.9587');
            $this->arResult['DEFAULT_ZOOM'] = (int)SettingsTable::get($this->siteId, 'default_zoom', '11');
            $this->arResult['HIGH_LOAD_ENABLED'] = SettingsTable::get($this->siteId, 'high_load_enabled', 'N');
            $this->arResult['HIGH_LOAD_ADD_TIME'] = (int)SettingsTable::get($this->siteId, 'high_load_add_time', '0');
        } else {
            $this->arResult['YANDEX_API_KEY'] = '';
            $this->arResult['DEFAULT_LAT'] = 54.7355;
            $this->arResult['DEFAULT_LNG'] = 55.9587;
            $this->arResult['DEFAULT_ZOOM'] = 11;
            $this->arResult['HIGH_LOAD_ENABLED'] = 'N';
            $this->arResult['HIGH_LOAD_ADD_TIME'] = 0;
        }

        $this->includeComponentTemplate();
    }

    /**
     * {@inheritdoc}
     */
    public function configureActions()
    {
        return [
            'getZones' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class
                ],
            ],
            'calculateDelivery' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class
                ],
            ]
        ];
    }

    /**
     * Получение зон доставки
     *
     * @return array
     */
    public function getZonesAction()
    {
        try {
            if (!$this->isModuleLoaded()) {
                return $this->errorResponse('Модуль доставки не установлен');
            }

            $zones = $this->fetchZonesFromDatabase();

            return [
                'success' => true,
                'zones' => $zones,
                'total' => count($zones)
            ];

        } catch (Exception $e) {
            $this->logError('Ошибка получения зон доставки', $e);
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Расчет стоимости доставки по координатам
     *
     * @param array $post Параметры запроса
     * @return array
     */
    public function calculateDeliveryAction($post)
    {
        $lat = (float)($post['arParams']['lat'] ?? 0);
        $lon = (float)($post['arParams']['lon'] ?? 0);

        if ($lat == 0 || $lon == 0) {
            return $this->errorResponse('Не указаны координаты');
        }

        try {
            if (!$this->isModuleLoaded()) {
                return $this->errorResponse('Модуль доставки не установлен');
            }

            $zone = $this->findZoneByCoordinates($lat, $lon);

            if (!$zone) {
                return [
                    'success' => false,
                    'error' => 'Адрес вне зоны доставки',
                    'in_zone' => false
                ];
            }

            // Учитываем высокую нагрузку
            $highLoadEnabled = SettingsTable::get($this->siteId, 'high_load_enabled', 'N');
            $highLoadAddTime = (int)SettingsTable::get($this->siteId, 'high_load_add_time', '0');

            $deliveryTimeStart = (int)$zone['delivery_time_start'];
            $deliveryTimeEnd = (int)$zone['delivery_time_end'];

            if ($highLoadEnabled === 'Y' && $highLoadAddTime > 0) {
                if ($deliveryTimeStart > 0) {
                    $deliveryTimeStart += $highLoadAddTime;
                }
                if ($deliveryTimeEnd > 0) {
                    $deliveryTimeEnd += $highLoadAddTime;
                }
            }

            return [
                'success' => true,
                'in_zone' => true,
                'zone_id' => $zone['id'],
                'zone_name' => $zone['name'],
                'price' => $zone['price'],
                'delivery_time_start' => $deliveryTimeStart,
                'delivery_time_end' => $deliveryTimeEnd,
                'min_order_price' => $zone['min_order_price'],
                'free_delivery_price' => $zone['free_delivery_price'],
                'high_load_enabled' => $highLoadEnabled,
                'high_load_add_time' => $highLoadAddTime
            ];

        } catch (Exception $e) {
            $this->logError('Ошибка расчета доставки', $e);
            return $this->errorResponse('Ошибка расчета доставки');
        }
    }

    /**
     * Проверка загрузки модуля
     *
     * @return bool
     */
    protected function isModuleLoaded()
    {
        return Loader::includeModule(self::MODULE_ID);
    }

    /**
     * Получение зон из базы данных
     *
     * @return array
     */
    protected function fetchZonesFromDatabase()
    {
        $zones = [];

        // Получаем настройки высокой нагрузки
        $highLoadEnabled = SettingsTable::get($this->siteId, 'high_load_enabled', 'N');
        $highLoadAddTime = (int)SettingsTable::get($this->siteId, 'high_load_add_time', '0');

        $dbZones = DeliveryZoneTable::getList([
            'filter' => [
                '=ACTIVE' => 'Y',
                '=SITE_ID' => $this->siteId
            ],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC']
        ]);

        while ($zone = $dbZones->fetch()) {
            $coordinates = $this->prepareCoordinates($zone['COORDINATES']);

            if (empty($coordinates)) {
                continue;
            }

            $deliveryTimeStart = (int)$zone['DELIVERY_TIME_START'];
            $deliveryTimeEnd = (int)$zone['DELIVERY_TIME_END'];

            // Корректируем время с учётом высокой нагрузки
            if ($highLoadEnabled === 'Y' && $highLoadAddTime > 0) {
                if ($deliveryTimeStart > 0) {
                    $deliveryTimeStart += $highLoadAddTime;
                }
                if ($deliveryTimeEnd > 0) {
                    $deliveryTimeEnd += $highLoadAddTime;
                }
            }

            $zones[] = [
                'id' => (int)$zone['ID'],
                'name' => $zone['NAME'],
                'price' => (float)$zone['PRICE'],
                'free_delivery_price' => (float)$zone['FREE_DELIVERY_PRICE'],
                'delivery_time_start' => $deliveryTimeStart,
                'delivery_time_end' => $deliveryTimeEnd,
                'min_order_price' => (float)$zone['MIN_ORDER_PRICE'],
                'color' => $zone['COLOR'],
                'coordinates' => $coordinates
            ];
        }

        return $zones;
    }

    /**
     * Подготовка координат для Яндекс.Карт
     *
     * @param mixed $coordinates
     * @return array
     */
    protected function prepareCoordinates($coordinates)
    {
        if (is_string($coordinates)) {
            $coordinates = json_decode($coordinates, true);
        }

        if (!is_array($coordinates) || count($coordinates) < 3) {
            return [];
        }

        $result = [];
        foreach ($coordinates as $point) {
            if (is_array($point) && count($point) === 2) {
                $result[] = [(float)$point[0], (float)$point[1]];
            }
        }

        return $result;
    }

    /**
     * Поиск зоны по координатам
     *
     * @param float $lat
     * @param float $lon
     * @return array|null
     */
    protected function findZoneByCoordinates($lat, $lon)
    {
        $zones = $this->fetchZonesFromDatabase();
        $point = [$lat, $lon];

        foreach ($zones as $zone) {
            if ($this->isPointInPolygon($point, $zone['coordinates'])) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * Проверка принадлежности точки полигону (алгоритм Ray Casting)
     *
     * @param array $point [lat, lng]
     * @param array $polygon [[lat, lng], ...]
     * @return bool
     */
    protected function isPointInPolygon($point, $polygon)
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

    /**
     * Формирование ответа с ошибкой
     *
     * @param string $message
     * @return array
     */
    protected function errorResponse($message)
    {
        return [
            'success' => false,
            'error' => $message
        ];
    }

    /**
     * Логирование ошибки
     *
     * @param string $message
     * @param Exception $e
     * @return void
     */
    protected function logError($message, $e)
    {
        AddMessage2Log(
            sprintf('%s: %s in %s:%d', $message, $e->getMessage(), $e->getFile(), $e->getLine()),
            self::MODULE_ID
        );
    }
}