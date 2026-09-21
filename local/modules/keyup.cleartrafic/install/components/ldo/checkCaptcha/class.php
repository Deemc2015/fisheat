<?php

use \Keyup\Cleartrafic\Captcha;
use \Keyup\Cleartrafic\Settings;
use \Keyup\Cleartrafic\LocalStorage;
use \Keyup\Cleartrafic\Visits;
use \Bitrix\Main\Engine\Contract\Controllerable;

\Bitrix\Main\Loader::includeModule("keyup.cleartrafic");

class CCheckCaptcha extends \CBitrixComponent implements Controllerable
{
    public $arResult = [];

    public function executeComponent()
    {
        try {
            $this->arResult['CAPTCHA'] = $this->getCaptcha();
            $this->arResult['TIME'] = Settings::getCaptchaTime();
            $this->arResult['KEY'] = Settings::getCaptchaSiteKey();
            $this->includeComponentTemplate();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Показываем капчу, только если IP помечен как подозрительный.
     *
     * @return bool
     */
    public function getCaptcha()
    {
        return (bool)LocalStorage::checkBots();
    }

    public function configureActions()
    {
        return [
            'checkAction' => ['prefilters' => []],
        ];
    }

    /**
     * @param array|string $data
     *
     * @return array
     */
    public function checkAction($data)
    {
        $data = is_array($data) ? (string)($data['token'] ?? '') : (string)$data;
        $token = trim($data);

        if ($token === '') {
            return ['status' => 'fail'];
        }

        if (Captcha::check($token)) {
            LocalStorage::addWhiteList();

            /*Отмечаем в журнале визитов, что каптча пройдена*/
            Visits::setCaptchaPassed(LocalStorage::getIp());

            return ['status' => 'ok'];
        }

        return ['status' => 'fail'];
    }
}
