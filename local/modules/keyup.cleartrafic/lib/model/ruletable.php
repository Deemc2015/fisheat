<?php

namespace Keyup\Cleartrafic\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\EnumField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

/**
 * Единая таблица правил фильтрации.
 *
 * Тип правила определяет смысл поля VALUE:
 *  - black: IP-адрес, доступ блокируется;
 *  - gray: IP-адрес, показывается капча;
 *  - mask: IP-адрес сети, поле MASK задаёт длину маски;
 *  - referer: домен, переходы с которого считаются доверенными.
 */
class RuleTable extends DataManager
{
    /** Чёрный список IP */
    public const TYPE_BLACK = 'black';
    /** Серый список IP */
    public const TYPE_GRAY = 'gray';
    /** Маска подсети */
    public const TYPE_MASK = 'mask';
    /** Разрешённый реферер */
    public const TYPE_REFERER = 'referer';
    /** Фрагмент User-Agent */
    public const TYPE_USERAGENT = 'useragent';

    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'keyup_cleartrafic_rule';
    }

    /**
     * @return array
     */
    public static function getMap()
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new EnumField('TYPE'))
                ->configureValues([
                    self::TYPE_BLACK,
                    self::TYPE_GRAY,
                    self::TYPE_MASK,
                    self::TYPE_REFERER,
                    self::TYPE_USERAGENT,
                ])
                ->configureRequired(),

            (new StringField('VALUE'))
                ->configureRequired()
                ->configureSize(255),

            (new IntegerField('MASK'))
                ->configureNullable(),

            (new BooleanField('ACTIVE'))
                ->configureValues('N', 'Y')
                ->configureDefaultValue('Y'),

            (new IntegerField('SORT'))
                ->configureDefaultValue(100),

            /*
             * Bitrix при создании таблицы не пишет DEFAULT в DDL, а MySQL 8 в strict-режиме
             * запрещает INSERT без значения для NOT NULL-колонки. Поэтому у всех
             * необязательных полей значение по умолчанию задаётся на уровне ORM:
             * иначе поля не попадают в запрос и вставка падает с ошибкой 1364.
             */
            (new StringField('COMMENT'))
                ->configureSize(255)
                ->configureDefaultValue(''),

            (new DatetimeField('DATE_CREATE'))
                ->configureDefaultValueNow(),
        ];
    }
}
