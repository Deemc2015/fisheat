<?php

namespace Keyup\Cleartrafic;

use Bitrix\Main\Config\Option;

/**
 * Настройки модуля: хранятся в COption, HL-блоки для этого не нужны.
 */
class Settings
{
    public const MODULE_ID = 'keyup.cleartrafic';

    /** Секретный ключ Яндекс SmartCaptcha */
    public const OPTION_CAPTCHA_SECRET = 'captcha_secret';
    /** Публичный ключ Яндекс SmartCaptcha */
    public const OPTION_CAPTCHA_SITE_KEY = 'captcha_site_key';
    /** Задержка показа капчи, секунд */
    public const OPTION_CAPTCHA_TIME = 'captcha_time';
    /** Срок хранения журнала визитов, дней. 0 — хранить бессрочно */
    public const OPTION_RETENTION_DAYS = 'retention_days';
    /** Код счётчиков аналитики для вставки в head */
    public const OPTION_ANALYTICS_HEAD = 'analytics_head';
    /** Код счётчиков аналитики для вставки перед закрывающим body */
    public const OPTION_ANALYTICS_BODY = 'analytics_body';

    /** Значение задержки показа капчи по умолчанию */
    public const DEFAULT_CAPTCHA_TIME = 7;
    /** Публичный ключ по умолчанию */
    public const DEFAULT_CAPTCHA_SITE_KEY = 'ysc1_aAIlRyqnHhZ3KtvGujotC8kaPZF8llWTWwVmU1Np871f5e66';

    /**
     * @return string
     */
    public static function getCaptchaSecret()
    {
        return (string)Option::get(self::MODULE_ID, self::OPTION_CAPTCHA_SECRET, '');
    }

    /**
     * @param string $value
     *
     * @return void
     */
    public static function setCaptchaSecret($value)
    {
        Option::set(self::MODULE_ID, self::OPTION_CAPTCHA_SECRET, trim((string)$value));
    }

    /**
     * @return string
     */
    public static function getCaptchaSiteKey()
    {
        $key = (string)Option::get(self::MODULE_ID, self::OPTION_CAPTCHA_SITE_KEY, '');

        return $key !== '' ? $key : self::DEFAULT_CAPTCHA_SITE_KEY;
    }

    /**
     * @param string $value
     *
     * @return void
     */
    public static function setCaptchaSiteKey($value)
    {
        Option::set(self::MODULE_ID, self::OPTION_CAPTCHA_SITE_KEY, trim((string)$value));
    }

    /**
     * Задержка показа капчи в секундах.
     *
     * @return int
     */
    public static function getCaptchaTime()
    {
        $value = (int)Option::get(self::MODULE_ID, self::OPTION_CAPTCHA_TIME, self::DEFAULT_CAPTCHA_TIME);

        return $value > 0 ? $value : self::DEFAULT_CAPTCHA_TIME;
    }

    /**
     * @param int $seconds
     *
     * @return void
     */
    public static function setCaptchaTime($seconds)
    {
        Option::set(self::MODULE_ID, self::OPTION_CAPTCHA_TIME, max(1, (int)$seconds));
    }

    /**
     * Срок хранения журнала визитов в днях. 0 — хранение без ограничения.
     *
     * @return int
     */
    public static function getRetentionDays()
    {
        return max(0, (int)Option::get(self::MODULE_ID, self::OPTION_RETENTION_DAYS, 0));
    }

    /**
     * @param int $days
     *
     * @return void
     */
    public static function setRetentionDays($days)
    {
        Option::set(self::MODULE_ID, self::OPTION_RETENTION_DAYS, max(0, (int)$days));
    }

    /**
     * Код счётчиков аналитики, который выводится в head.
     *
     * @return string
     */
    public static function getAnalyticsHead()
    {
        return (string)Option::get(self::MODULE_ID, self::OPTION_ANALYTICS_HEAD, '');
    }

    /**
     * @param string $value
     *
     * @return void
     */
    public static function setAnalyticsHead($value)
    {
        Option::set(self::MODULE_ID, self::OPTION_ANALYTICS_HEAD, (string)$value);
    }

    /**
     * Код счётчиков аналитики, который выводится перед закрывающим body.
     *
     * @return string
     */
    public static function getAnalyticsBody()
    {
        return (string)Option::get(self::MODULE_ID, self::OPTION_ANALYTICS_BODY, '');
    }

    /**
     * @param string $value
     *
     * @return void
     */
    public static function setAnalyticsBody($value)
    {
        Option::set(self::MODULE_ID, self::OPTION_ANALYTICS_BODY, (string)$value);
    }

    /**
     * Задан ли хотя бы один блок кода аналитики.
     *
     * @return bool
     */
    public static function hasAnalytics()
    {
        return self::getAnalyticsHead() !== '' || self::getAnalyticsBody() !== '';
    }
}
