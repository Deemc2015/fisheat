<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;

class CUserAuth extends \CBitrixComponent implements Controllerable
{
    private $apiToken = '0a2532ebe145039a1f9356451746a0139a2adc979c5f51c1ba6e4877450940ba';
    private $apiUrl = 'https://api.call-password.ru/api/';

    public function executeComponent()
    {
        $this->arResult['NOT_AUTH_USER'] = !$this->isAuth();
        $this->includeComponentTemplate();
    }

    private function isAuth()
    {
        global $USER;
        return $USER->IsAuthorized();
    }

    public function configureActions()
    {
        return [
            'nextStep' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                ],
            ],
            'checkCallStatus' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                ],
            ],
            'confirmCode' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                ],
            ],
        ];
    }

    /**
     * Выполнение CURL запроса к API
     */
    private function curlPost($url, $postData)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($postData)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => 'CURL ошибка: ' . $error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'HTTP ошибка: ' . $httpCode];
        }

        return ['success' => true, 'data' => json_decode($response, true)];
    }

    /**
     * 1. Инициируем звонок (синхронный режим)
     */
    public function nextStepAction($userPhone)
    {
        $userPhone = $this->normalizePhone($userPhone);
        if (!$userPhone) {
            return [
                'success' => false,
                'error' => 'Неверный формат номера телефона'
            ];
        }

        session_start();
        $_SESSION['auth_phone'] = $userPhone;

        // Используем API v1.0 (синхронный, сразу получаем pin)
        $url = $this->apiUrl . 'v1.0/start-call-password/';
        $postData = http_build_query([
            'dn' => $userPhone,
            'timeout' => 30
        ]);

        $result = $this->curlPost($url, $postData);

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error']
            ];
        }

        $data = $result['data'];

        if ($data && isset($data['status']) && $data['status'] === 'success') {
            $callId = $data['data']['callid'] ?? null;
            $pin = $data['data']['pin'] ?? null;
            $callResult = $data['data']['result'] ?? null;

            if ($callId && $pin) {
                $_SESSION['call_id'] = $callId;
                $_SESSION['expected_code'] = $pin;

                return [
                    'success' => true,
                    'callId' => $callId,
                    'message' => 'На ваш номер поступит звонок. Введите последние 4 цифры номера, с которого поступил вызов.'
                ];
            } elseif ($callId && $callResult === 'null') {
                // Асинхронный режим, нужно проверять статус
                $_SESSION['call_id'] = $callId;
                return [
                    'success' => true,
                    'callId' => $callId,
                    'message' => 'Звонок инициирован. Ожидайте звонок и введите код.',
                    'async' => true
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Не удалось получить код подтверждения'
                ];
            }
        }

        $errorMessage = $data['data']['message'] ?? 'Ошибка сервиса';
        return [
            'success' => false,
            'error' => $errorMessage
        ];
    }

    /**
     * 2. Проверка статуса звонка (для асинхронного режима)
     */
    public function checkCallStatusAction($callId)
    {
        $url = $this->apiUrl . 'v2.0/get-call-password-status/';
        $postData = http_build_query(['callId' => $callId]);

        $result = $this->curlPost($url, $postData);

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error']
            ];
        }

        $data = $result['data'];

        if ($data && isset($data['status']) && $data['status'] === 'success') {
            $status = $data['data']['result'] ?? null;
            $pin = $data['data']['pin'] ?? null;

            return [
                'success' => true,
                'status' => $status,
                'code' => $pin
            ];
        }

        return [
            'success' => false,
            'error' => 'Ошибка проверки статуса'
        ];
    }

    /**
     * 3. Подтверждение кода и авторизация
     */
    public function confirmCodeAction($code)
    {
        session_start();

        $userPhone = $_SESSION['auth_phone'] ?? null;
        $expectedCode = $_SESSION['expected_code'] ?? null;

        if (!$userPhone) {
            return [
                'success' => false,
                'error' => 'Сессия истекла. Попробуйте снова.'
            ];
        }

        // Сравниваем код
        if ($expectedCode && $expectedCode === $code) {
            $authResult = $this->authorizeUser($userPhone);

            if ($authResult['success']) {
                unset($_SESSION['auth_phone']);
                unset($_SESSION['call_id']);
                unset($_SESSION['expected_code']);

                return [
                    'success' => true,
                    'message' => 'Авторизация успешна!'
                ];
            }

            return [
                'success' => false,
                'error' => $authResult['error'] ?? 'Ошибка авторизации'
            ];
        }

        return [
            'success' => false,
            'error' => 'Неверный код. Попробуйте снова.'
        ];
    }

    /**
     * Авторизация/регистрация пользователя
     */
    private function authorizeUser($phone)
    {
        return true;
    }

    /**
     * Нормализация номера телефона
     */
    private function normalizePhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 11 && ($phone[0] === '7' || $phone[0] === '8')) {
            if ($phone[0] === '8') {
                $phone = '7' . substr($phone, 1);
            }
            return $phone;
        }

        if (strlen($phone) === 10) {
            return '7' . $phone;
        }

        return false;
    }
}