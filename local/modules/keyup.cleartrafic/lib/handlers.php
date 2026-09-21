<?php

namespace Keyup\Cleartrafic;

use Bitrix\Main\Application;
use Keyup\Cleartrafic\Model\VisitTable;

/**
 * Обработчики страницы: классификация посетителя по правилам фильтрации,
 * журнал визитов и вывод окна капчи.
 */
class Handlers
{
    /**
     * Отключает композитный кэш для посетителей, которым нужна капча.
     *
     * Обработчик вызывается в prolog_before, то есть раньше, чем ядро примет
     * решение отдать страницу из композитного кэша. Без этого разметка окна
     * капчи не попала бы в отдаваемый HTML.
     *
     * @return bool
     */
    public static function handlerBeforeProlog()
    {
        if (Gate::isRequired() && !defined('BX_COMPOSITE_DISABLE')) {
            define('BX_COMPOSITE_DISABLE', true);
        }

        return true;
    }

    /**
     * Дорабатывает готовый HTML страницы.
     *
     * Показывать одновременно окно капчи и подключать аналитику нельзя:
     * окно выводится тем, кому капча нужна и ещё не пройдена, а счётчики —
     * всем остальным посетителям.
     *
     * @param string $content
     *
     * @return void
     */
    public static function handlerEndBuffer(&$content)
    {
        if (!is_string($content) || $content === '') {
            return;
        }

        if (Gate::isRequired()) {
            $content = CaptchaWindow::inject($content);

            return;
        }

        Analytics::inject($content);
    }

    /** Служебные страницы модуля, которые не попадают в журнал */
    public const EXCLUDED_DIRS = [
        '/personal_filter/',
        '/personal_filter/black/',
        '/personal_filter/gray/',
        '/personal_filter/mask/',
        '/personal_filter/referer/',
        '/personal_filter/useragent/',
        '/personal_filter/useragent_list/',
        '/personal_filter/config/',
    ];

    /**
     * @return bool|void
     */
    public static function handlerInfoIp()
    {
        $requestData = Application::getInstance()->getContext()->getRequest();
        $ip = $requestData->getRemoteAddress();
        $page = $requestData->getServer()->get("REQUEST_URI");
        $referer = $requestData->getServer()->get("HTTP_REFERER");
        $userAgent = $requestData->getServer()->get("HTTP_USER_AGENT");

        if (!$ip) {
            return true;
        }

        if ($page == '/ajax/basket_fly.php') {
            return true;
        }

        $matchType = '';
        $matchRule = '';
        $botsMarker = null;
        $redirect = false;

        /*Проверка User-Agent — приоритетная: при совпадении посетитель
          обрабатывается так же, как адрес из серого списка*/
        $matchedUserAgent = $userAgent ? Rules::getMatchedUserAgent($userAgent) : null;

        if ($matchedUserAgent !== null) {
            $matchType = VisitTable::TYPE_USERAGENT;
            $matchRule = $matchedUserAgent;
            $botsMarker = LocalStorage::addBotsMarker($ip);
        }

        /*Проверка реферера: переход не с доверенного домена помечает посетителя*/
        if (!$botsMarker && $referer) {
            $host = self::getRefererHost($referer);

            if ($host !== '' && !Rules::isReferer($host)) {
                $matchType = VisitTable::TYPE_REFERER;
                $matchRule = $host;
                $botsMarker = LocalStorage::addBotsMarker($ip);
            }
        }

        /*Проверка нахождения в масках подсетей*/
        if (!$botsMarker && Rules::isMask($ip)) {
            $matchType = VisitTable::TYPE_MASK;
            $matchRule = $ip;
            $botsMarker = LocalStorage::addBotsMarker($ip);
        }

        /*Проверка нахождения в сером списке*/
        if (!$botsMarker && Rules::isGray($ip)) {
            $matchType = VisitTable::TYPE_GRAY;
            $matchRule = $ip;
            LocalStorage::addBotsMarker($ip);
        }

        /*Проверка нахождения в чёрном списке*/
        if (!$botsMarker && Rules::isBlack($ip)) {
            $matchType = VisitTable::TYPE_BLACK;
            $matchRule = $ip;
            $redirect = true;
        }

        $localStorage = new LocalStorage();
        $resultAdd = $localStorage->addIp($ip);

        if ($resultAdd) {
            global $APPLICATION;

            $curDir = $APPLICATION->GetCurDir();

            if (!in_array($curDir, self::EXCLUDED_DIRS, true)) {
                Visits::register([
                    'IP'         => $ip,
                    'PAGE'       => $page,
                    'REFERER'    => $referer,
                    'MATCH_TYPE' => $matchType !== '' ? $matchType : VisitTable::TYPE_NONE,
                    'MATCH_RULE' => $matchRule,
                ]);
            }
        }

        /*Редирект на страницу с формой обратной связи*/
        if ($redirect) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                /*аякс запрос*/
            } else {
                if ($page != "/black_page/") {
                    return header("Location: /black_page/");
                }
            }
        }

        return true;
    }

    /**
     * Основной домен реферера или пустая строка.
     *
     * @param string $referer
     *
     * @return string
     */
    public static function getRefererHost($referer)
    {
        $host = parse_url((string)$referer, PHP_URL_HOST);

        if (!$host) {
            return '';
        }

        $parts = explode('.', $host);

        return count($parts) > 2
            ? implode('.', array_slice($parts, -2))
            : $host;
    }
}
