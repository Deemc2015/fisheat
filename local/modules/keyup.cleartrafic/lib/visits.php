<?php

namespace Keyup\Cleartrafic;

use Bitrix\Main\Type\DateTime;
use Keyup\Cleartrafic\Model\VisitTable;

/**
 * Журнал посещений: одна запись на IP-адрес.
 *
 * Счётчик визитов инкрементируется, фиксируются страница входа,
 * реферер и сработавшее правило фильтрации.
 */
class Visits
{
    /**
     * Регистрирует визит: обновляет существующую запись или создаёт новую.
     *
     * @param array $data IP, PAGE, REFERER, MATCH_TYPE, MATCH_RULE
     *
     * @return int|null ID записи
     */
    public static function register(array $data)
    {
        $ip = isset($data['IP']) ? trim((string)$data['IP']) : '';

        if ($ip === '') {
            return null;
        }

        $now = new DateTime();
        $existing = VisitTable::getList([
            'select' => ['ID', 'VISITS'],
            'filter' => ['=IP' => $ip],
            'limit'  => 1,
        ])->fetch();

        if ($existing) {
            $fields = [
                'VISITS'      => (int)$existing['VISITS'] + 1,
                'DATE_UPDATE' => $now,
            ];

            if (!empty($data['PAGE'])) {
                $fields['PAGE'] = $data['PAGE'];
            }

            if (!empty($data['REFERER'])) {
                $fields['REFERER'] = $data['REFERER'];
            }

            if (!empty($data['MATCH_TYPE'])) {
                $fields['MATCH_TYPE'] = $data['MATCH_TYPE'];
            }

            if (!empty($data['MATCH_RULE'])) {
                $fields['MATCH_RULE'] = $data['MATCH_RULE'];
            }

            VisitTable::update($existing['ID'], $fields);

            return (int)$existing['ID'];
        }

        $result = VisitTable::add([
            'IP'           => $ip,
            'PAGE'         => isset($data['PAGE']) ? (string)$data['PAGE'] : '',
            'REFERER'      => isset($data['REFERER']) ? (string)$data['REFERER'] : '',
            'MATCH_TYPE'   => !empty($data['MATCH_TYPE']) ? $data['MATCH_TYPE'] : VisitTable::TYPE_NONE,
            'MATCH_RULE'   => isset($data['MATCH_RULE']) ? (string)$data['MATCH_RULE'] : '',
            'CAPTCHA_PASSED' => 'N',
            'VISITS'       => 1,
            'MESSAGE'      => isset($data['MESSAGE']) ? (string)$data['MESSAGE'] : '',
            'DATE_CREATE'  => $now,
            'DATE_UPDATE'  => $now,
        ]);

        if (!$result->isSuccess()) {
            return null;
        }

        return (int)$result->getId();
    }

    /**
     * @param string $ip
     *
     * @return array|null
     */
    public static function getByIp($ip)
    {
        $row = VisitTable::getList([
            'select' => ['*'],
            'filter' => ['=IP' => $ip],
            'limit'  => 1,
        ])->fetch();

        return $row ?: null;
    }

    /**
     * @param int $id
     *
     * @return array|null
     */
    public static function getById($id)
    {
        $row = VisitTable::getList([
            'select' => ['*'],
            'filter' => ['=ID' => (int)$id],
            'limit'  => 1,
        ])->fetch();

        return $row ?: null;
    }

    /**
     * Отмечает, что посетитель прошёл капчу.
     *
     * @param string $ip
     *
     * @return void
     */
    public static function setCaptchaPassed($ip)
    {
        $row = self::getByIp($ip);

        if ($row) {
            VisitTable::update($row['ID'], ['CAPTCHA_PASSED' => 'Y']);
        }
    }

    /**
     * Сохраняет сообщение посетителя в его записи журнала.
     *
     * @param string $ip
     * @param string $text
     *
     * @return void
     */
    public static function addMessage($ip, $text)
    {
        $row = self::getByIp($ip);

        if ($row) {
            VisitTable::update($row['ID'], ['MESSAGE' => (string)$text]);
        }
    }

    /**
     * Постраничный список записей журнала.
     *
     * @param array $filter
     * @param int   $limit
     * @param int   $offset
     * @param array $order
     *
     * @return array
     */
    public static function getList(array $filter = [], $limit = 50, $offset = 0, array $order = ['ID' => 'DESC'])
    {
        return VisitTable::getList([
            'select' => ['ID', 'IP', 'PAGE', 'REFERER', 'MATCH_TYPE', 'MATCH_RULE', 'CAPTCHA_PASSED', 'VISITS', 'DATE_CREATE', 'DATE_UPDATE'],
            'filter' => $filter,
            'order'  => $order,
            'limit'  => (int)$limit,
            'offset' => (int)$offset,
        ])->fetchAll();
    }

    /**
     * Общее количество записей по фильтру.
     *
     * @param array $filter
     *
     * @return int
     */
    public static function getCount(array $filter = [])
    {
        return (int)VisitTable::getCount($filter);
    }

    /**
     * Удаляет записи журнала старше указанного количества дней.
     *
     * @param int $days
     *
     * @return int Количество удалённых записей
     */
    public static function cleanupOlderThan($days)
    {
        $days = (int)$days;

        if ($days <= 0) {
            return 0;
        }

        $threshold = new DateTime();
        $threshold->add('-' . $days . ' days');

        $items = VisitTable::getList([
            'select' => ['ID'],
            'filter' => ['<DATE_CREATE' => $threshold],
        ]);

        $deleted = 0;

        while ($row = $items->fetch()) {
            $result = VisitTable::delete($row['ID']);

            if ($result->isSuccess()) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
