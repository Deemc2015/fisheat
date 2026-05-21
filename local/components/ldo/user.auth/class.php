<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;

class CUserAuth extends \CBitrixComponent implements Controllerable
{
    // ВАЖНО: ключи должны быть ровно 48 символов в HEX (только 0-9a-f)
    // Пример правильного формата: 'a1b2c3d4e5f67890123456789012345678901234567890'
    private $authKey = '958234bf11a4b741f927bdb53798c83bfeaff24a6a7daeb3';
    private $signKey = '68a4b84dfd2ab242e328f25dd3bbecf11518683e82db35b1';
    private $apiUrl = 'https://api.new-tel.net/';

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
            'testConnection' => [
                '-prefilters' => [],
            ],
        ];
    }

    /**
     * Тестовый метод для проверки соединения и авторизации
     */
    public function testConnectionAction()
    {
        // Простой GET запрос к API (не требует авторизации)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.new-tel.net/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_NOBODY, true);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'connection_test' => [
                'http_code' => $httpCode,
                'curl_error' => $error,
                'status' => $httpCode > 0 ? 'connected' : 'failed'
            ],
            'api_info' => [
                'auth_key_length' => strlen($this->authKey),
                'sign_key_length' => strlen($this->signKey),
                'required_length' => 48,
                'auth_key_valid' => $this->validateHex($this->authKey),
                'sign_key_valid' => $this->validateHex($this->signKey)
            ]
        ];
    }

    /**
     * Проверка что строка состоит только из HEX символов
     */
    private function validateHex($str)
    {
        return preg_match('/^[0-9a-f]+$/i', $str) && strlen($str) === 48;
    }

    /**
     * Формирование Bearer-токена (по документации Нью-Тел)
     */
    private function getToken($methodName, $params, $time)
    {
        // Формируем строку для подписи
        $signatureString = $methodName . "\n" . $time . "\n" . $this->authKey . "\n" . $params . "\n" . $this->signKey;

        // Вычисляем SHA-256 хэш
        $signature = hash('sha256', $signatureString);

        // Формируем токен: authKey + time + signature
        $token = $this->authKey . $time . $signature;

        // Логируем для отладки (временно)
        addMessage2Log("=== TOKEN DEBUG ===");
        addMessage2Log("Method: " . $methodName);
        addMessage2Log("Time: " . $time);
        addMessage2Log("Params: " . $params);
        addMessage2Log("Signature string (with \\n): " . str_replace("\n", "\\n", $signatureString));
        addMessage2Log("Signature: " . $signature);
        addMessage2Log("Token length: " . strlen($token) . " (должен быть 48+10+64=122)");

        return $token;
    }

    /**
     * Выполнение запроса к API
     */
    private function apiRequest($methodName, $paramsArray = [])
    {
        $jsonParams = json_encode($paramsArray);
        $time = time();
        $token = $this->getToken($methodName, $jsonParams, $time);

        $url = $this->apiUrl . $methodName;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonParams);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        addMessage2Log("API Response Code: " . $httpCode);
        addMessage2Log("API Response: " . $response);

        if ($response === false) {
            return ['success' => false, 'error' => 'CURL error: ' . $error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'HTTP ' . $httpCode . ': ' . $response];
        }

        $data = json_decode($response, true);
        return ['success' => true, 'data' => $data];
    }

    /**
     * 1. Инициируем звонок
     */
    public function nextStepAction($userPhone)
    {
        $userPhone = $this->normalizePhone($userPhone);
        if (!$userPhone) {
            return ['success' => false, 'error' => 'Неверный формат номера телефона'];
        }

        session_start();
        $_SESSION['auth_phone'] = $userPhone;

        // Генерируем 4-значный PIN
        $pin = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $_SESSION['expected_code'] = $pin;

        // Параметры запроса
        $params = [
            'dstNumber' => $userPhone,
            'pin' => $pin,
            'fixedCid' => 0
        ];

        $response = $this->apiRequest('call-password/start-password-call', $params);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        $data = $response['data'];

        if (isset($data['status']) && $data['status'] === 'success') {
            if (isset($data['data']['result']) && $data['data']['result'] === 'success') {
                $callDetails = $data['data']['callDetails'] ?? [];

                if (!empty($callDetails['callId'])) {
                    $_SESSION['call_id'] = $callDetails['callId'];

                    return [
                        'success' => true,
                        'callId' => $callDetails['callId'],
                        'message' => 'На ваш номер поступит звонок. Введите последние 4 цифры номера, с которого поступил вызов.'
                    ];
                }
            }

            $errorMsg = $data['data']['message'] ?? 'Ошибка сервиса';
            return ['success' => false, 'error' => $errorMsg];
        }

        return ['success' => false, 'error' => 'Неизвестная ошибка API'];
    }

    /**
     * 2. Проверка статуса звонка
     */
    public function checkCallStatusAction($callId)
    {
        $response = $this->apiRequest('call-password/get-password-call-status', ['callId' => $callId]);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        $data = $response['data'];

        if (isset($data['status']) && $data['status'] === 'success') {
            if (isset($data['data']['result']) && $data['data']['result'] === 'success') {
                $callDetails = $data['data']['callDetails'] ?? [];

                // Маппинг статусов для вашего JS
                $statusMap = [
                    'answered' => 'NORMAL_CLEARING',
                    'busy' => 'USER_BUSY',
                    'no answer' => 'NO_ANSWER',
                    'cancel' => 'CANCEL'
                ];

                $status = $callDetails['status'] ?? 'unknown';

                return [
                    'success' => true,
                    'status' => $statusMap[$status] ?? $status
                ];
            }
        }

        return ['success' => false, 'error' => 'Статус не найден'];
    }

    /**
     * 3. Подтверждение кода
     */
    public function confirmCodeAction($code)
    {
        session_start();

        $userPhone = $_SESSION['auth_phone'] ?? null;
        $expectedCode = $_SESSION['expected_code'] ?? null;

        if (!$userPhone) {
            return ['success' => false, 'error' => 'Сессия истекла'];
        }

        if ($expectedCode && $expectedCode === $code) {
            $authResult = $this->authorizeUser($userPhone);

            if ($authResult['success']) {
                unset($_SESSION['auth_phone']);
                unset($_SESSION['call_id']);
                unset($_SESSION['expected_code']);

                return ['success' => true, 'message' => 'Авторизация успешна!'];
            }

            return ['success' => false, 'error' => $authResult['error']];
        }

        return ['success' => false, 'error' => 'Неверный код'];
    }

    /**
     * Авторизация пользователя
     */
    private function authorizeUser($phone)
    {
        return ['success' => true];
    }

    /**
     * Нормализация номера
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