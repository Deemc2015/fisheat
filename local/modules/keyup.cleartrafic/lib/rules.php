<?php

namespace Keyup\Cleartrafic;

use Bitrix\Main\Data\Cache;
use Bitrix\Main\Error;
use Bitrix\Main\ORM\Data\AddResult;
use Bitrix\Main\ORM\Data\UpdateResult;
use Bitrix\Main\Type\DateTime;
use Keyup\Cleartrafic\Model\RuleTable;

/**
 * Правила фильтрации: чёрный и серый списки, маски подсетей, разрешённые рефереры.
 *
 * Все правила читаются одним запросом и кэшируются под общим тегом модуля,
 * поэтому проверки на каждом хите не обращаются к базе.
 */
class Rules
{
    /** Код ошибки: такая запись уже есть в списке */
    public const ERROR_DUPLICATE = 'DUPLICATE';
    /** Код ошибки: некорректное значение правила */
    public const ERROR_INVALID = 'INVALID';
    /** Время жизни кэша правил, секунд */
    public const CACHE_TTL = 3600;
    /** Каталог кэша */
    public const CACHE_DIR = '/keyup.cleartrafic/rules';

    /**
     * Полный набор правил, сгруппированный по типу.
     *
     * @return array
     */
    public static function getAll()
    {
        $cache = Cache::createInstance();
        /* Идентификатор версии набора правил: меняется при изменении структуры кэша */
        $cacheId = 'rules_v2';

        if ($cache->initCache(self::CACHE_TTL, $cacheId, self::CACHE_DIR)) {
            return $cache->getVars();
        }

        $rules = [
            RuleTable::TYPE_BLACK     => [],
            RuleTable::TYPE_GRAY      => [],
            RuleTable::TYPE_MASK      => [],
            RuleTable::TYPE_REFERER   => [],
            RuleTable::TYPE_USERAGENT => [],
        ];

        $result = RuleTable::getList([
            'select' => ['ID', 'TYPE', 'VALUE', 'MASK', 'ACTIVE', 'SORT'],
            'order'  => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);

        while ($row = $result->fetch()) {
            $rules[$row['TYPE']][] = [
                'ID'     => (int)$row['ID'],
                'VALUE'  => (string)$row['VALUE'],
                'MASK'   => $row['MASK'] !== null ? (int)$row['MASK'] : null,
                'ACTIVE' => $row['ACTIVE'] === 'Y',
                'SORT'   => (int)$row['SORT'],
            ];
        }

        if ($cache->startDataCache()) {
            $cache->endDataCache($rules);
        }

        return $rules;
    }

    /**
     * Активные маски подсетей.
     *
     * @return array
     */
    public static function getActiveMasks()
    {
        $masks = [];

        foreach (self::getAll()[RuleTable::TYPE_MASK] as $item) {
            if ($item['ACTIVE']) {
                $masks[] = $item;
            }
        }

        return $masks;
    }

    /**
     * Все IP из списка заданного типа.
     *
     * @param string $type
     *
     * @return array
     */
    public static function getIpList($type)
    {
        $list = [];

        foreach (self::getAll()[$type] as $item) {
            $list[$item['ID']] = $item['VALUE'];
        }

        return $list;
    }

    /**
     * @param string $ip
     *
     * @return bool
     */
    public static function isBlack($ip)
    {
        return self::hasValue(RuleTable::TYPE_BLACK, $ip);
    }

    /**
     * @param string $ip
     *
     * @return bool
     */
    public static function isGray($ip)
    {
        return self::hasValue(RuleTable::TYPE_GRAY, $ip);
    }

    /**
     * @param string $ip
     *
     * @return bool
     */
    public static function isReferer($host)
    {
        return self::hasValue(RuleTable::TYPE_REFERER, $host);
    }

    /**
     * Фрагмент User-Agent, совпавший с правилом, либо null.
     *
     * Сравнение регистронезависимое и по вхождению: правило «AhrefsBot»
     * сработает и для строки «Mozilla/5.0 (compatible; AhrefsBot/7.0; ...)».
     *
     * @param string $userAgent
     *
     * @return string|null
     */
    public static function getMatchedUserAgent($userAgent)
    {
        $userAgent = trim((string)$userAgent);

        if ($userAgent === '') {
            return null;
        }

        foreach (self::getAll()[RuleTable::TYPE_USERAGENT] as $item) {
            if ($item['VALUE'] !== '' && mb_stripos($userAgent, $item['VALUE']) !== false) {
                return $item['VALUE'];
            }
        }

        return null;
    }

    /**
     * @param string $userAgent
     *
     * @return bool
     */
    public static function isUserAgent($userAgent)
    {
        return self::getMatchedUserAgent($userAgent) !== null;
    }

    /**
     * Список правил по User-Agent для вывода в интерфейсе.
     *
     * @return array
     */
    public static function getUserAgentList()
    {
        return self::getList(RuleTable::TYPE_USERAGENT);
    }

    /**
     * IP входит в одну из активных масок подсетей.
     *
     * @param string $ip
     *
     * @return bool
     */
    public static function isMask($ip)
    {
        $ipLong = ip2long($ip);

        if ($ipLong === false) {
            return false;
        }

        foreach (self::getActiveMasks() as $mask) {
            if ($mask['MASK'] === null) {
                continue;
            }

            $netLong = ip2long($mask['VALUE']);

            if ($netLong === false) {
                continue;
            }

            $maskLong = -1 << (32 - max(0, min(32, $mask['MASK'])));

            if (($ipLong & $maskLong) === ($netLong & $maskLong)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $type
     * @param string $value
     *
     * @return bool
     */
    protected static function hasValue($type, $value)
    {
        foreach (self::getAll()[$type] as $item) {
            if ($item['VALUE'] === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Список правил заданного типа для вывода в интерфейсе.
     *
     * @param string $type
     *
     * @return array
     */
    public static function getList($type)
    {
        return RuleTable::getList([
            'select' => ['ID', 'TYPE', 'VALUE', 'MASK', 'ACTIVE', 'SORT', 'COMMENT'],
            'filter' => ['=TYPE' => $type],
            'order'  => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ])->fetchAll();
    }

    /**
     * Адреса для оповещений.
     *
     * @return array
     */
    public static function getRefererList()
    {
        return self::getList(RuleTable::TYPE_REFERER);
    }

    /**
     * Добавляет правило, если такого значения в списке ещё нет.
     *
     * При попытке добавить дубликат возвращается результат с ошибкой
     * с кодом self::ERROR_DUPLICATE.
     *
     * @param array $fields
     *
     * @return AddResult
     */
    public static function add(array $fields)
    {
        if (!isset($fields['DATE_CREATE'])) {
            $fields['DATE_CREATE'] = new DateTime();
        }

        $type = isset($fields['TYPE']) ? (string)$fields['TYPE'] : '';
        $value = isset($fields['VALUE']) ? (string)$fields['VALUE'] : '';
        $mask = array_key_exists('MASK', $fields) ? $fields['MASK'] : null;

        if (!self::isValidValue($type, $value, $mask)) {
            return self::invalidResult(new AddResult(), $type);
        }

        /* Маска хранится числом: корректность проверена выше */
        if ($type === RuleTable::TYPE_MASK) {
            $fields['MASK'] = (int)$mask;
        }

        if (self::findDuplicate($type, $value, $type === RuleTable::TYPE_MASK ? (int)$mask : null) !== null) {
            return self::duplicateResult(new AddResult(), $type);
        }

        $result = RuleTable::add($fields);
        self::clearCache();

        return $result;
    }

    /**
     * Изменяет правило, не допуская появления дубликата.
     *
     * @param int   $id
     * @param array $fields
     *
     * @return UpdateResult
     */
    public static function update($id, array $fields)
    {
        $id = (int)$id;

        if ($id) {
            $current = RuleTable::getList([
                'select' => ['TYPE', 'VALUE', 'MASK'],
                'filter' => ['=ID' => $id],
                'limit'  => 1,
            ])->fetch();

            if ($current) {
                $type = isset($fields['TYPE']) ? (string)$fields['TYPE'] : (string)$current['TYPE'];
                $value = isset($fields['VALUE']) ? (string)$fields['VALUE'] : (string)$current['VALUE'];
                $mask = array_key_exists('MASK', $fields) ? $fields['MASK'] : $current['MASK'];

                if (!self::isValidValue($type, $value, $mask)) {
                    return self::invalidResult(new UpdateResult(), $type);
                }

                /* Маска хранится числом: корректность проверена выше */
                if ($type === RuleTable::TYPE_MASK && array_key_exists('MASK', $fields)) {
                    $fields['MASK'] = (int)$mask;
                }

                if (self::findDuplicate($type, $value, $type === RuleTable::TYPE_MASK ? (int)$mask : null, $id) !== null) {
                    return self::duplicateResult(new UpdateResult(), $type);
                }
            }
        }

        $result = RuleTable::update($id, $fields);
        self::clearCache();

        return $result;
    }

    /**
     * Идентификатор правила с таким же значением (для масок — с такой же длиной маски).
     *
     * @param string   $type
     * @param string   $value
     * @param int|null $mask
     * @param int      $exceptId ID правила, которое не учитывается (при изменении)
     *
     * @return int|null
     */
    protected static function findDuplicate($type, $value, $mask = null, $exceptId = 0)
    {
        if ($type === '' || trim((string)$value) === '') {
            return null;
        }

        $filter = [
            '=TYPE'  => $type,
            '=VALUE' => trim((string)$value),
        ];

        if ($type === RuleTable::TYPE_MASK) {
            $filter['=MASK'] = (int)$mask;
        }

        if ($exceptId > 0) {
            $filter['!=ID'] = (int)$exceptId;
        }

        $row = RuleTable::getList([
            'select' => ['ID'],
            'filter' => $filter,
            'limit'  => 1,
        ])->fetch();

        return $row ? (int)$row['ID'] : null;
    }

    /**
     * Результат с ошибкой «запись уже существует».
     *
     * @param AddResult|UpdateResult $result
     * @param string                 $type
     *
     * @return AddResult|UpdateResult
     */
    protected static function duplicateResult($result, $type)
    {
        $names = [
            RuleTable::TYPE_BLACK     => 'чёрном списке',
            RuleTable::TYPE_GRAY      => 'сером списке',
            RuleTable::TYPE_MASK      => 'списке масок',
            RuleTable::TYPE_REFERER   => 'списке рефереров',
            RuleTable::TYPE_USERAGENT => 'списке User-Agent',
        ];

        $place = isset($names[$type]) ? $names[$type] : 'списке';

        $result->addError(new Error('Такая запись уже есть в ' . $place, self::ERROR_DUPLICATE));

        return $result;
    }

    /**
     * @param int $id
     *
     * @return \Bitrix\Main\ORM\Data\DeleteResult
     */
    public static function delete($id)
    {
        $result = RuleTable::delete($id);
        self::clearCache();

        return $result;
    }

    /**
     * Удаляет все правила заданного типа.
     *
     * @param string $type
     *
     * @return void
     */
    public static function deleteAllByType($type)
    {
        $items = RuleTable::getList([
            'select' => ['ID'],
            'filter' => ['=TYPE' => $type],
        ]);

        while ($row = $items->fetch()) {
            RuleTable::delete($row['ID']);
        }

        self::clearCache();
    }

    /**
     * Включает или выключает все маски подсетей.
     *
     * @param bool $active
     *
     * @return void
     */
    public static function setMasksActive($active)
    {
        $value = $active ? 'Y' : 'N';

        $items = RuleTable::getList([
            'select' => ['ID'],
            'filter' => ['=TYPE' => RuleTable::TYPE_MASK],
        ]);

        while ($row = $items->fetch()) {
            RuleTable::update($row['ID'], ['ACTIVE' => $value]);
        }

        self::clearCache();
    }

    /**
     * Есть ли вообще маски в таблице.
     *
     * @return bool
     */
    public static function hasMasks()
    {
        $result = RuleTable::getList([
            'select' => ['ID'],
            'filter' => ['=TYPE' => RuleTable::TYPE_MASK],
            'limit'  => 1,
        ])->fetch();

        return (bool)$result;
    }

    /**
     * Состояние активности масок: true — включены, false — выключены, null — масок нет.
     *
     * @return bool|null
     */
    public static function getMasksActiveState()
    {
        $result = RuleTable::getList([
            'select' => ['ACTIVE'],
            'filter' => ['=TYPE' => RuleTable::TYPE_MASK],
            'limit'  => 1,
        ])->fetch();

        if (!$result) {
            return null;
        }

        return $result['ACTIVE'] === 'Y';
    }

    /**
     * Массовый импорт масок подсетей из разобранного файла.
     *
     * Существующие маски не удаляются: дубликаты пропускаются.
     *
     * @param array $rows Список вида [['IP' => '10.0.0.0', 'MASKA' => 8], ...]
     *
     * @return int Количество добавленных записей
     */
    public static function importMasks(array $rows)
    {
        $stats = self::importRules(RuleTable::TYPE_MASK, $rows);

        return $stats['added'];
    }

    /**
     * Импорт правил из файла: записи добавляются к существующим, дубликаты
     * (по значению правила, для масок — по значению и длине маски) пропускаются,
     * поэтому повторная загрузка того же файла не создаёт дублей.
     *
     * @param string $type  Тип правила: black, gray, mask, referer
     * @param array  $items Список вида [['VALUE' => ..., 'MASK' => ...], ...]
     *                      либо [['IP' => ..., 'MASKA' => ...], ...]
     *
     * @return array ['added' => int, 'skipped' => int, 'invalid' => int]
     */
    public static function importRules($type, array $items)
    {
        $stats = ['added' => 0, 'skipped' => 0, 'invalid' => 0];

        if (!in_array($type, [
            RuleTable::TYPE_BLACK,
            RuleTable::TYPE_GRAY,
            RuleTable::TYPE_MASK,
            RuleTable::TYPE_REFERER,
            RuleTable::TYPE_USERAGENT,
        ], true)) {
            return $stats;
        }

        /* Существующие значения и текущий порядок сортировки */
        $existing = [];
        $maxSort = 0;

        $rows = RuleTable::getList([
            'select' => ['VALUE', 'MASK', 'SORT'],
            'filter' => ['=TYPE' => $type],
        ]);

        while ($row = $rows->fetch()) {
            $existing[self::ruleKey($type, (string)$row['VALUE'], $row['MASK'])] = true;
            $maxSort = max($maxSort, (int)$row['SORT']);
        }

        $sort = $maxSort;

        $connection = \Bitrix\Main\Application::getConnection();
        $connection->startTransaction();

        try {
            foreach ($items as $item) {
                if (isset($item['VALUE'])) {
                    $value = (string)$item['VALUE'];
                } elseif (isset($item['IP'])) {
                    $value = (string)$item['IP'];
                } else {
                    $stats['invalid']++;
                    continue;
                }

                $value = trim($value);

                if (isset($item['MASK'])) {
                    $mask = (int)$item['MASK'];
                } elseif (isset($item['MASKA'])) {
                    $mask = (int)$item['MASKA'];
                } else {
                    $mask = null;
                }

                if (!self::isValidValue($type, $value, $mask)) {
                    $stats['invalid']++;
                    continue;
                }

                $key = self::ruleKey($type, $value, $mask);

                if (isset($existing[$key])) {
                    $stats['skipped']++;
                    continue;
                }

                $sort += 100;

                $fields = [
                    'TYPE'        => $type,
                    'VALUE'       => $value,
                    'ACTIVE'      => 'Y',
                    'SORT'        => $sort,
                    'DATE_CREATE' => new DateTime(),
                ];

                if ($type === RuleTable::TYPE_MASK) {
                    $fields['MASK'] = $mask;
                }

                $result = RuleTable::add($fields);

                if ($result->isSuccess()) {
                    $existing[$key] = true;
                    $stats['added']++;
                } else {
                    $stats['invalid']++;
                }
            }

            $connection->commitTransaction();
        } catch (\Throwable $e) {
            $connection->rollbackTransaction();
            throw $e;
        }

        self::clearCache();

        return $stats;
    }

    /**
     * Ключ правила для проверки дублей.
     *
     * @param string   $type
     * @param string   $value
     * @param int|null $mask
     *
     * @return string
     */
    protected static function ruleKey($type, $value, $mask = null)
    {
        $value = mb_strtolower(trim((string)$value));

        return $type === RuleTable::TYPE_MASK
            ? $value . '/' . (int)$mask
            : $value;
    }

    /**
     * Проверка значения правила: используется и при ручном добавлении, и при импорте.
     *
     * @param string          $type
     * @param string          $value
     * @param int|string|null $mask
     *
     * @return bool
     */
    protected static function isValidValue($type, $value, $mask)
    {
        $value = trim((string)$value);

        if ($value === '') {
            return false;
        }

        /* Маска подсети: IPv4-адрес сети и длина маски от 0 до 32.
           Сопоставление масок построено на ip2long(), поэтому IPv6 не поддерживается. */
        if ($type === RuleTable::TYPE_MASK) {
            return self::isIpv4($value) && self::isMaskLength($mask);
        }

        /* Чёрный и серый списки: IPv4 или IPv6 */
        if ($type === RuleTable::TYPE_BLACK || $type === RuleTable::TYPE_GRAY) {
            return filter_var($value, FILTER_VALIDATE_IP) !== false;
        }

        /* Реферер — доменное имя хоста либо его IP-адрес, без протокола и пути */
        if ($type === RuleTable::TYPE_REFERER) {
            return self::isHostName($value);
        }

        /* User-Agent — фрагмент строки заголовка без переводов строк */
        if ($type === RuleTable::TYPE_USERAGENT) {
            return mb_strlen($value) <= 255 && !preg_match('/[\r\n]/', $value);
        }

        return true;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected static function isIpv4($value)
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Длина маски: целое число от 0 до 32.
     *
     * @param int|string|null $mask
     *
     * @return bool
     */
    protected static function isMaskLength($mask)
    {
        if (!is_string($mask) && !is_int($mask)) {
            return false;
        }

        return (bool)preg_match('/^\d{1,2}$/', (string)$mask) && (int)$mask <= 32;
    }

    /**
     * Доменное имя хоста: yandex.ru, www.yandex.ru, localhost.
     *
     * @param string $value
     *
     * @return bool
     */
    protected static function isHostName($value)
    {
        return (bool)preg_match(
            '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/i',
            $value
        );
    }

    /**
     * Результат с ошибкой «некорректное значение».
     *
     * @param AddResult|UpdateResult $result
     * @param string                 $type
     *
     * @return AddResult|UpdateResult
     */
    protected static function invalidResult($result, $type)
    {
        if ($type === RuleTable::TYPE_MASK) {
            $message = 'Укажите корректный IPv4-адрес сети и длину маски от 0 до 32';
        } elseif ($type === RuleTable::TYPE_BLACK || $type === RuleTable::TYPE_GRAY) {
            $message = 'Укажите корректный IP-адрес, например 192.168.0.1';
        } elseif ($type === RuleTable::TYPE_REFERER) {
            $message = 'Укажите корректный домен, например yandex.ru';
        } elseif ($type === RuleTable::TYPE_USERAGENT) {
            $message = 'Укажите фрагмент User-Agent, например AhrefsBot';
        } else {
            $message = 'Укажите корректное значение';
        }

        $result->addError(new Error($message, self::ERROR_INVALID));

        return $result;
    }

    /**
     * Сбрасывает кэш правил.
     *
     * Cache::clean() принимает ($uniqueString, $initDir, $baseDir), а кэш пишется
     * в initDir = self::CACHE_DIR. Поэтому чистится именно каталог кэша правил:
     * при передаче аргументов в порядке «каталог, id» удалялся несуществующий путь
     * и правила оставались закэшированными до истечения TTL.
     *
     * @return void
     */
    public static function clearCache()
    {
        Cache::createInstance()->cleanDir(self::CACHE_DIR);
    }
}
