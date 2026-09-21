<?php

namespace Keyup\Cleartrafic;

/**
 * Вывод кода счётчиков аналитики из настроек модуля.
 *
 * Код попадает на страницу только тем посетителям, которым капча не
 * показывается или которые её уже прошли: визиты до прохождения капчи
 * в статистику не попадают.
 */
class Analytics
{
    /** @var bool Защита от повторной вставки в рамках одного запроса */
    protected static $injected = false;

    /**
     * Разрешён ли вывод аналитики на текущей странице.
     *
     * @return bool
     */
    public static function isAllowed()
    {
        if (self::$injected) {
            return false;
        }

        if (!Settings::hasAnalytics()) {
            return false;
        }

        if (Gate::isServicePage()) {
            return false;
        }

        /* Капча нужна и ещё не пройдена — статистику не подключаем */
        return !Gate::isRequired();
    }

    /**
     * Вставляет код аналитики в готовый HTML страницы.
     *
     * @param string $content
     *
     * @return void
     */
    public static function inject(&$content)
    {
        if (!self::isAllowed()) {
            return;
        }

        if (!is_string($content) || $content === '') {
            return;
        }

        $head = Settings::getAnalyticsHead();

        if ($head !== '') {
            $content = self::insertBeforeHeadEnd($content, $head);
        }

        $body = Settings::getAnalyticsBody();

        if ($body !== '') {
            $content = self::insertBeforeBodyEnd($content, $body);
        }

        self::$injected = true;
    }

    /**
     * Сбрасывает признак вставки (нужно для проверок из CLI).
     *
     * @return void
     */
    public static function reset()
    {
        self::$injected = false;
    }

    /**
     * @param string $content
     * @param string $code
     *
     * @return string
     */
    protected static function insertBeforeHeadEnd($content, $code)
    {
        $position = stripos($content, '</head>');

        if ($position === false) {
            return $content;
        }

        return substr($content, 0, $position) . $code . substr($content, $position);
    }

    /**
     * @param string $content
     * @param string $code
     *
     * @return string
     */
    protected static function insertBeforeBodyEnd($content, $code)
    {
        $position = strripos($content, '</body>');

        if ($position === false) {
            return $content . $code;
        }

        return substr($content, 0, $position) . $code . substr($content, $position);
    }
}
