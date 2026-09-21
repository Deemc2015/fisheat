<?php

namespace Keyup\Cleartrafic\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\EnumField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;

/**
 * Журнал посещений сайта.
 *
 * Одна запись на IP-адрес: счётчик визитов инкрементируется,
 * дата последнего визита и сработавшее правило обновляются.
 */
class VisitTable extends DataManager
{
    /** Совпадений нет */
    public const TYPE_NONE = 'none';
    /** Совпадение по рефереру */
    public const TYPE_REFERER = 'referer';
    /** Совпадение по маске подсети */
    public const TYPE_MASK = 'mask';
    /** Найден в сером списке */
    public const TYPE_GRAY = 'gray';
    /** Найден в чёрном списке */
    public const TYPE_BLACK = 'black';
    /** Найден по User-Agent */
    public const TYPE_USERAGENT = 'useragent';

    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'keyup_cleartrafic_visit';
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

            (new StringField('IP'))
                ->configureRequired()
                ->configureSize(45)
                ->addValidator(new LengthValidator(1, 45)),

            /*
             * Длину колонки в DDL задаёт только LengthValidator: configureSize()
             * влияет лишь на обрезание значения в PHP. Без валидатора ORM создаёт
             * varchar(255) и длинный REQUEST_URI с UTM-метками ломает вставку.
             */
            (new StringField('PAGE'))
                ->configureSize(500)
                ->addValidator(new LengthValidator(0, 500))
                ->configureDefaultValue(''),

            (new StringField('REFERER'))
                ->configureSize(500)
                ->addValidator(new LengthValidator(0, 500))
                ->configureDefaultValue(''),

            (new EnumField('MATCH_TYPE'))
                ->configureValues([
                    self::TYPE_NONE,
                    self::TYPE_REFERER,
                    self::TYPE_MASK,
                    self::TYPE_GRAY,
                    self::TYPE_BLACK,
                    self::TYPE_USERAGENT,
                ])
                ->configureDefaultValue(self::TYPE_NONE),

            (new StringField('MATCH_RULE'))
                ->configureSize(255)
                ->configureDefaultValue(''),

            (new BooleanField('CAPTCHA_PASSED'))
                ->configureValues('N', 'Y')
                ->configureDefaultValue('N'),

            (new IntegerField('VISITS'))
                ->configureDefaultValue(1),

            /*
             * TEXT в MySQL не может иметь DEFAULT на уровне схемы, а колонка создаётся
             * как NOT NULL. Без значения по умолчанию ORM не включает поле в INSERT
             * и MySQL в strict-режиме падает с ошибкой 1364 «Field 'MESSAGE' doesn't
             * have a default value». Пустая строка задаётся на уровне ORM.
             */
            (new TextField('MESSAGE'))
                ->configureDefaultValue(''),

            (new DatetimeField('DATE_CREATE'))
                ->configureDefaultValueNow(),

            (new DatetimeField('DATE_UPDATE'))
                ->configureDefaultValueNow(),
        ];
    }
}
