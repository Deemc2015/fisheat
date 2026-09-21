<?php

namespace Keyup\Cleartrafic;

/**
 * Разметка окна капчи и её вставка в готовый HTML страницы.
 *
 * Модуль добавляет окно прямо в буфер вывода, поэтому правки шаблона сайта
 * не нужны: работает и на страницах, отдаваемых композитным кэшем, если
 * композит для этого запроса отключён обработчиком OnBeforeProlog.
 */
class CaptchaWindow
{
    /** Действие внутреннего AJAX-контроллера модуля */
    public const AJAX_ACTION = 'keyup:cleartrafic.captcha.check';

    /** Точка входа AJAX-диспетчера Bitrix */
    public const AJAX_URL = '/bitrix/services/main/ajax.php';

    /** Маркер вставленной разметки: по нему проверяем, что окно уже добавлено */
    public const MARKER = 'keyup-captcha-overlay';

    /** Каталог модуля для подключения assets */
    public const ASSETS_PATH = '/local/modules/keyup.cleartrafic/assets';

    /**
     * Разметка окна со стилями и скриптами.
     *
     * @return string
     */
    public static function getHtml()
    {
        $config = [
            'siteKey' => Settings::getCaptchaSiteKey(),
            'delay'   => Settings::getCaptchaTime() * 1000,
            'ajaxUrl' => self::AJAX_URL . '?action=' . urlencode(self::AJAX_ACTION),
            'sessid'  => bitrix_sessid(),
            'messages' => [
                'empty'    => 'Пожалуйста, подтвердите, что вы не робот',
                'fail'     => 'Проверка не пройдена. Попробуйте ещё раз',
                'loadFail' => 'Не удалось загрузить капчу. Обновите страницу',
            ],
        ];

        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        ob_start();
        ?>
        <link rel="stylesheet" href="<?= self::ASSETS_PATH ?>/captcha.css">
        <div class="keyup-captcha-overlay" id="keyup-captcha-overlay"></div>
        <div class="keyup-captcha-modal" id="keyup-captcha-modal" role="dialog" aria-modal="true" aria-label="Проверка, что вы не робот">
            <p class="keyup-captcha-modal__text">Для продолжения подтвердите, что вы не робот</p>
            <div class="keyup-captcha-modal__container" id="keyup-captcha-container"></div>
            <p class="keyup-captcha-modal__error" id="keyup-captcha-error"></p>
            <button type="button" class="keyup-captcha-modal__submit" id="keyup-captcha-submit">Отправить</button>
        </div>
        <script>window.KEYUP_CAPTCHA_CONFIG = <?= $json ?>;</script>
        <script src="https://smartcaptcha.yandexcloud.net/captcha.js" defer></script>
        <script src="<?= self::ASSETS_PATH ?>/captcha.js" defer></script>
        <?php

        return (string)ob_get_clean();
    }

    /**
     * Вставляет разметку окна перед закрывающим тегом body.
     *
     * @param string $content
     *
     * @return string
     */
    public static function inject($content)
    {
        if (!self::isHtml($content) || strpos($content, self::MARKER) !== false) {
            return $content;
        }

        $position = strripos($content, '</body>');

        if ($position === false) {
            return $content . self::getHtml();
        }

        return substr($content, 0, $position) . self::getHtml() . substr($content, $position);
    }

    /**
     * Подходит ли ответ для вставки окна.
     *
     * @param mixed $content
     *
     * @return bool
     */
    protected static function isHtml($content)
    {
        return is_string($content) && $content !== '' && stripos($content, '</body>') !== false;
    }
}
