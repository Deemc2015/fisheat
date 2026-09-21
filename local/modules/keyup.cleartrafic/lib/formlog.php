<?php

namespace Keyup\Cleartrafic;

use Bitrix\Main\Type\DateTime;
use Keyup\Cleartrafic\Model\FormLogTable;

/**
 * Ограничение частоты отправки публичной формы: не чаще одного раза в минуту.
 */
class FormLog
{
    /** Минимальный интервал между отправками, секунд */
    public const MIN_INTERVAL = 60;

    /**
     * Можно ли отправить сообщение с этого IP.
     * При разрешении запись о факте отправки создаётся сразу.
     *
     * @param string $ip
     *
     * @return bool
     */
    public static function check($ip)
    {
        $lastTimestamp = self::getLastTimestamp($ip);

        if ($lastTimestamp !== null && (time() - $lastTimestamp) < self::MIN_INTERVAL) {
            return false;
        }

        self::write($ip);

        return true;
    }

    /**
     * Метка времени последней отправки или null.
     *
     * @param string $ip
     *
     * @return int|null
     */
    public static function getLastTimestamp($ip)
    {
        $row = FormLogTable::getList([
            'select' => ['ID', 'DATE_CREATE'],
            'filter' => ['=IP' => $ip],
            'order'  => ['ID' => 'DESC'],
            'limit'  => 1,
        ])->fetch();

        if (!$row || !$row['DATE_CREATE'] instanceof \Bitrix\Main\Type\DateTime) {
            return null;
        }

        return $row['DATE_CREATE']->getTimestamp();
    }

    /**
     * Фиксирует факт отправки формы.
     *
     * @param string $ip
     *
     * @return void
     */
    public static function write($ip)
    {
        FormLogTable::add([
            'IP'          => $ip,
            'DATE_CREATE' => new DateTime(),
        ]);
    }

    /**
     * Удаляет устаревшие записи лога.
     *
     * @param int $days
     *
     * @return int
     */
    public static function cleanupOlderThan($days)
    {
        $days = (int)$days;

        if ($days <= 0) {
            return 0;
        }

        $threshold = new DateTime();
        $threshold->add('-' . $days . ' days');

        $items = FormLogTable::getList([
            'select' => ['ID'],
            'filter' => ['<DATE_CREATE' => $threshold],
        ]);

        $deleted = 0;

        while ($row = $items->fetch()) {
            if (FormLogTable::delete($row['ID'])->isSuccess()) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
