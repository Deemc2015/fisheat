<?php

namespace Keyup\Cleartrafic;

use Bitrix\Main\Application;
use Keyup\Cleartrafic\Model\VisitTable;

/**
 * Определяет, нужно ли показать посетителю окно капчи.
 *
 * Источник истины — правила модуля (Rules), а не сессия: решение принимается
 * заново на каждом запросе, поэтому оно не зависит от того, успел ли
 * сработать обработчик журнала и сохранилась ли сессия.
 */
class Gate
{
    /** Капча нужна: адрес в сером списке */
    public const REASON_GRAY = 'gray';
    /** Капча нужна: адрес попал в маску подсети */
    public const REASON_MASK = 'mask';
    /** Капча нужна: переход не с доверенного домена */
    public const REASON_REFERER = 'referer';
    /** Капча нужна: совпал User-Agent */
    public const REASON_USERAGENT = 'useragent';

    /**
     * Подстроки User-Agent поисковых роботов.
     *
     * Капчу им не показываем: робот её не пройдёт и страница выпадет из индекса.
     */
    public const SEARCH_BOT_MARKERS = [
        'googlebot',
        'google-inspectiontool',
        'yandexbot',
        'yandex.com/bots',
        'yandeximages',
        'bingbot',
        'msnbot',
        'duckduckbot',
        'baiduspider',
        'slurp',
        'applebot',
        'ahrefsbot',
        'semrushbot',
        'mj12bot',
        'petalbot',
        'sputnikbot',
        'mail.ru_bot',
        'seznambot',
        'ia_archiver',
    ];

    /**
     * Каталоги, в которых окно капчи не выводится:
     * служебные страницы модуля, страница блокировки, админка, служебные файлы.
     */
    public const EXCLUDED_DIRS = [
        '/bitrix/',
        '/personal_filter/',
        '/black_page/',
        '/callForm/',
        '/upload/',
        '/local/',
    ];

    /** @var string|null Причина срабатывания после вычисления */
    protected static $reason;

    /**
     * Нужна ли капча текущему посетителю.
     *
     * @return bool
     */
    public static function isRequired()
    {
        return self::getReason() !== '';
    }

    /**
     * Причина показа капчи: gray, mask, referer, useragent или пустая строка.
     *
     * @return string
     */
    public static function getReason()
    {
        if (self::$reason !== null) {
            return self::$reason;
        }

        self::$reason = self::resolveReason();

        return self::$reason;
    }

    /**
     * Сбрасывает вычисленный результат (нужно для тестов и CLI-проверок).
     *
     * @return void
     */
    public static function reset()
    {
        self::$reason = null;
    }

    /**
     * Служебная страница: админка или разделы самого модуля.
     *
     * На таких страницах не выводится ни окно капчи, ни код аналитики.
     *
     * @return bool
     */
    public static function isServicePage()
    {
        if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
            return true;
        }

        return self::isExcludedPage();
    }

    /**
     * @return string
     */
    protected static function resolveReason()
    {
        /* Админка и служебные разделы: окно не показываем */
        if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
            return '';
        }

        if (!self::isHtmlGetRequest()) {
            return '';
        }

        if (self::isExcludedPage()) {
            return '';
        }

        /* Капча уже пройдена — повторно не показываем */
        if (Pass::isPassed()) {
            return '';
        }

        $request = Application::getInstance()->getContext()->getRequest();
        $ip = $request->getRemoteAddress();
        $userAgent = (string)$request->getServer()->get('HTTP_USER_AGENT');

        if (!$ip) {
            return '';
        }

        /* Поисковые роботы капчу не видят */
        if (self::isSearchBot($userAgent)) {
            return '';
        }

        /* Чёрный список обрабатывается редиректом на /black_page/ */
        if (Rules::isBlack($ip)) {
            return '';
        }

        /* Порядок проверок совпадает с порядком в журнале визитов */
        $matchedUserAgent = $userAgent !== '' ? Rules::getMatchedUserAgent($userAgent) : null;

        if ($matchedUserAgent !== null) {
            return self::REASON_USERAGENT;
        }

        $referer = (string)$request->getServer()->get('HTTP_REFERER');

        if ($referer !== '') {
            $host = Handlers::getRefererHost($referer);

            if ($host !== '' && !Rules::isReferer($host)) {
                return self::REASON_REFERER;
            }
        }

        if (Rules::isMask($ip)) {
            return self::REASON_MASK;
        }

        if (Rules::isGray($ip)) {
            return self::REASON_GRAY;
        }

        return '';
    }

    /**
     * Окно вставляется только в обычные GET-ответы с HTML.
     *
     * @return bool
     */
    protected static function isHtmlGetRequest()
    {
        $request = Application::getInstance()->getContext()->getRequest();

        if (strtoupper((string)$request->getRequestMethod()) !== 'GET') {
            return false;
        }

        $requestedWith = strtolower((string)$request->getServer()->get('HTTP_X_REQUESTED_WITH'));

        return $requestedWith !== 'xmlhttprequest';
    }

    /**
     * Служебная страница, на которой капча не выводится.
     *
     * @return bool
     */
    protected static function isExcludedPage()
    {
        global $APPLICATION;

        $dir = $APPLICATION instanceof \CMain ? (string)$APPLICATION->GetCurDir() : '';

        if ($dir === '') {
            return false;
        }

        foreach (self::EXCLUDED_DIRS as $excluded) {
            if (strpos($dir, $excluded) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $userAgent
     *
     * @return bool
     */
    protected static function isSearchBot($userAgent)
    {
        if ($userAgent === '') {
            return false;
        }

        $userAgent = strtolower($userAgent);

        foreach (self::SEARCH_BOT_MARKERS as $marker) {
            if (strpos($userAgent, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Тип совпадения для журнала визитов.
     *
     * @return string
     */
    public static function getVisitType()
    {
        switch (self::getReason()) {
            case self::REASON_GRAY:
                return VisitTable::TYPE_GRAY;

            case self::REASON_MASK:
                return VisitTable::TYPE_MASK;

            case self::REASON_REFERER:
                return VisitTable::TYPE_REFERER;

            case self::REASON_USERAGENT:
                return VisitTable::TYPE_USERAGENT;
        }

        return VisitTable::TYPE_NONE;
    }
}
