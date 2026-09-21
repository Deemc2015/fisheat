<?php

namespace Keyup\Cleartrafic;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;

/**
 * Флаг прохождения капчи.
 *
 * Основное хранилище — подписанная cookie: она переживает перезапуск браузера
 * и не зависит от жизни PHP-сессии. Дополнительно результат дублируется
 * в локальную сессию, чтобы капча не показывалась повторно в рамках визита.
 */
class Pass
{
    /** Имя cookie с флагом прохождения капчи */
    public const COOKIE_NAME = 'keyup_ct_pass';

    /** Опция модуля: соль для подписи cookie */
    public const OPTION_SALT = 'captcha_pass_salt';

    /** Опция модуля: срок действия флага в часах */
    public const OPTION_TTL = 'captcha_pass_ttl';

    /** Срок действия флага по умолчанию, часов */
    public const DEFAULT_TTL = 24;

    /**
     * Прошёл ли посетитель капчу.
     *
     * @return bool
     */
    public static function isPassed()
    {
        $ip = self::getIp();

        if ($ip === '') {
            return false;
        }

        if (self::isCookieValid($ip)) {
            return true;
        }

        return LocalStorage::getWhiteStatus($ip);
    }

    /**
     * Отмечает, что капча пройдена: ставит cookie и пишет метку в сессию.
     *
     * @return void
     */
    public static function markPassed()
    {
        $ip = self::getIp();

        if ($ip === '') {
            return;
        }

        $expires = time() + self::getTtl();
        $ipHash = self::getIpHash($ip);
        $value = $expires . '|' . $ipHash . '|' . self::sign($expires, $ipHash);

        $isHttps = Application::getInstance()->getContext()->getRequest()->isHttps();

        setcookie(self::COOKIE_NAME, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $_COOKIE[self::COOKIE_NAME] = $value;

        LocalStorage::addWhiteList($ip);
    }

    /**
     * Снимает флаг прохождения капчи.
     *
     * @return void
     */
    public static function clear()
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        unset($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Срок действия флага в секундах.
     *
     * @return int
     */
    public static function getTtl()
    {
        $hours = (int)Option::get(Settings::MODULE_ID, self::OPTION_TTL, self::DEFAULT_TTL);

        if ($hours <= 0) {
            $hours = self::DEFAULT_TTL;
        }

        return $hours * 3600;
    }

    /**
     * @param int $hours
     *
     * @return void
     */
    public static function setTtl($hours)
    {
        Option::set(Settings::MODULE_ID, self::OPTION_TTL, max(1, (int)$hours));
    }

    /**
     * Соль для подписи cookie. Создаётся при первом обращении.
     *
     * @return string
     */
    public static function getSalt()
    {
        $salt = (string)Option::get(Settings::MODULE_ID, self::OPTION_SALT, '');

        if ($salt === '') {
            $salt = bin2hex(random_bytes(16));
            Option::set(Settings::MODULE_ID, self::OPTION_SALT, $salt);
        }

        return $salt;
    }

    /**
     * Проверка cookie: срок, подпись и привязка к IP.
     *
     * @param string $ip
     *
     * @return bool
     */
    protected static function isCookieValid($ip)
    {
        /*
         * Читаем cookie напрямую из $_COOKIE: Request::getCookie() ищет её
         * с префиксом BITRIX_SM, а модуль ставит cookie без префикса.
         */
        $value = isset($_COOKIE[self::COOKIE_NAME]) ? (string)$_COOKIE[self::COOKIE_NAME] : '';

        if ($value === '') {
            return false;
        }

        $parts = explode('|', $value);

        if (count($parts) !== 3) {
            return false;
        }

        list($expires, $ipHash, $signature) = $parts;

        if (!preg_match('/^\d+$/', $expires) || (int)$expires < time()) {
            return false;
        }

        if (!hash_equals(self::sign($expires, $ipHash), $signature)) {
            return false;
        }

        return hash_equals(self::getIpHash($ip), $ipHash);
    }

    /**
     * @param int|string $expires
     * @param string     $ipHash
     *
     * @return string
     */
    protected static function sign($expires, $ipHash)
    {
        return hash_hmac('sha256', $expires . '|' . $ipHash, self::getSalt());
    }

    /**
     * @param string $ip
     *
     * @return string
     */
    protected static function getIpHash($ip)
    {
        return substr(hash_hmac('sha256', (string)$ip, self::getSalt()), 0, 32);
    }

    /**
     * @return string
     */
    protected static function getIp()
    {
        return (string)Application::getInstance()->getContext()->getRequest()->getRemoteAddress();
    }
}
