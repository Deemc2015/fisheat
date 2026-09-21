<?php

namespace Keyup\Cleartrafic;

use Bitrix\Main\Context;
use Bitrix\Main\Type\DateTime;

/**
 * Параметры фильтра списка визитов: период и IP-адрес.
 *
 * Возвращает массив в формате ORM-фильтра по полям IP и DATE_CREATE.
 */
class Filter
{
    /**
     * @return array|false
     */
    public static function getFilterParams()
    {
        try {
            $filterParams = self::getParams();

            $period = isset($filterParams['PERIOD']) ? $filterParams['PERIOD'] : null;
            $name = isset($filterParams['NAME']) ? $filterParams['NAME'] : null;
            $calendar = isset($filterParams['CALENDAR']) ? $filterParams['CALENDAR'] : null;

            $filterQuery = [];

            if ($calendar) {
                $search = self::getParamsByDatePeriod($calendar);
                $filterQuery['SEARCH'] = $search;
                $filterQuery['PERIOD'] = 'period';
                $filterQuery['PERIOD_LIST'] = $calendar;
            }

            if ($period) {
                $filterQuery['PERIOD'] = $period;
                $filterQuery['SEARCH'] = self::getParamsByDate($period);
            }

            if ($name) {
                $filterQuery['IP'] = $name;
                $filterQuery['SEARCH'][] = self::getParamsName($name);
            }

            if (!isset($filterQuery['SEARCH'])) {
                $filterQuery['SEARCH'] = [];
            }

            return $filterQuery;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Параметры фильтра за произвольный период.
     *
     * @param array $dates
     *
     * @return array
     */
    public static function getParamsByDatePeriod($dates)
    {
        $dateStart = isset($dates['DATE_START']) ? trim((string)$dates['DATE_START']) : '';
        $dateEnd = isset($dates['DATE_END']) ? trim((string)$dates['DATE_END']) : '';

        $startTs = $dateStart !== '' ? strtotime($dateStart) : false;
        $endTs = $dateEnd !== '' ? strtotime($dateEnd) : false;

        /* Некорректные даты игнорируем */
        if ($startTs === false || $endTs === false) {
            return [];
        }

        return [
            '>=DATE_CREATE' => DateTime::createFromUserTime(date('d.m.Y', $startTs) . ' 00:00:00'),
            '<=DATE_CREATE' => DateTime::createFromUserTime(date('d.m.Y', $endTs) . ' 23:59:59'),
        ];
    }

    /**
     * @param string $name
     *
     * @return array
     */
    public static function getParamsName($name)
    {
        return [
            '=IP' => (string)$name,
        ];
    }

    /**
     * @return array
     */
    public static function getParamsToday()
    {
        return [
            '>=DATE_CREATE' => DateTime::createFromUserTime(date("d.m.Y") . ' 00:00:00'),
            '<=DATE_CREATE' => DateTime::createFromUserTime(date("d.m.Y") . ' 23:59:59'),
        ];
    }

    /**
     * @return array
     */
    public static function getParamsWeek()
    {
        return [
            '>=DATE_CREATE' => DateTime::createFromUserTime(date("d.m.Y", strtotime("-7 days")) . ' 00:00:00'),
            '<=DATE_CREATE' => DateTime::createFromUserTime(date("d.m.Y") . ' 23:59:59'),
        ];
    }

    /**
     * @return array
     */
    public static function getParamsYesterday()
    {
        return [
            '>=DATE_CREATE' => DateTime::createFromUserTime(date("d.m.Y", strtotime("yesterday")) . ' 00:00:00'),
            '<=DATE_CREATE' => DateTime::createFromUserTime(date("d.m.Y", strtotime("yesterday")) . ' 23:59:59'),
        ];
    }

    /**
     * @return array
     */
    public static function getParamsDayBeforeYesterday()
    {
        return [
            '>=DATE_CREATE' => DateTime::createFromUserTime(date("d.m.Y", strtotime("-2 day")) . ' 00:00:00'),
            '<=DATE_CREATE' => DateTime::createFromUserTime(date("d.m.Y", strtotime("-2 day")) . ' 23:59:59'),
        ];
    }

    /**
     * @return array
     */
    public static function getParamsMonth()
    {
        return [
            '>=DATE_CREATE' => DateTime::createFromUserTime(date('01.m.Y 00:00:00')),
            '<=DATE_CREATE' => DateTime::createFromUserTime(date('t.m.Y 23:59:59')),
        ];
    }

    /**
     * @param string $params
     *
     * @return array
     */
    protected static function getParamsByDate($params)
    {
        switch ($params) {
            case 'today':
                return self::getParamsToday();

            case 'week':
                return self::getParamsWeek();

            case 'yesterday':
                return self::getParamsYesterday();

            case 'day_before_yesterday':
                return self::getParamsDayBeforeYesterday();

            case 'month':
                return self::getParamsMonth();
        }

        return [];
    }

    /**
     * @return array
     */
    protected static function getParams()
    {
        $request = Context::getCurrent()->getRequest();

        $filterDate = [];

        if ($request->get("date")) {
            $filterDate['PERIOD'] = $request->get("date");
        }

        if ($request->get("dateStart")) {
            $filterDate['CALENDAR'] = [
                "DATE_START" => $request->get("dateStart"),
                "DATE_END"   => $request->get("dateEnd"),
            ];
        }

        if ($request->get("ip")) {
            $filterDate['NAME'] = $request->get("ip");
        }

        if (empty($filterDate)) {
            throw new \Exception('Не переданы параметры для фильтра');
        }

        return $filterDate;
    }
}
