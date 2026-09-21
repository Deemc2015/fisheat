<?php

namespace Keyup\Cleartrafic\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use Keyup\Cleartrafic\LocalStorage;
use Keyup\Cleartrafic\Pass;
use Keyup\Cleartrafic\Visits;

/**
 * AJAX-действия окна капчи.
 *
 * Вызов: action=keyup:cleartrafic.captcha.check
 */
class Captcha extends Controller
{
    /**
     * Проверка капчи доступна неавторизованному посетителю,
     * но по-прежнему защищена проверкой идентификатора сессии.
     *
     * @return array
     */
    public function configureActions()
    {
        return [
            'check' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                ],
            ],
        ];
    }

    /**
     * Проверяет токен SmartCaptcha и отмечает прохождение капчи.
     *
     * @param string $token
     *
     * @return array
     */
    public function checkAction($token = '')
    {
        $token = trim((string)$token);

        if ($token === '') {
            return ['status' => 'fail'];
        }

        if (!\Keyup\Cleartrafic\Captcha::check($token)) {
            return ['status' => 'fail'];
        }

        Pass::markPassed();

        /* Отмечаем в журнале визитов, что капча пройдена */
        Visits::setCaptchaPassed(LocalStorage::getIp());

        return ['status' => 'ok'];
    }
}
