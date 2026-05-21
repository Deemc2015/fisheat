<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;

class CUserAuth extends \CBitrixComponent implements Controllerable
{
    // Данные из личного кабинета GreenSMS
    //private $login = 'deemc';
    //private $password = '0479096qQ!';

    private $login = 'test';
    private $password = 'test';
    private $bearerToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyIjoiZGVlbWMiLCJpYXQiOjE3NzkzNjg2MjYsImlzcyI6ImFwaS5ncmVlbnNtcy5ydSJ9.AWP40CPxUtUb0VLs1p9yCJ2ZFoA9MU-HH4QPTecwfu0';

    private $apiUrlSend = 'https://api3.greensms.ru/call/send';
    private $apiUrlStatus = 'https://api3.greensms.ru/call/status';

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
            'nextStep' => ['-prefilters' => [ActionFilter\Authentication::class]],
            'checkCallStatus' => ['-prefilters' => [ActionFilter\Authentication::class]],
            'confirmCode' => ['-prefilters' => [ActionFilter\Authentication::class]],
        ];
    }

    /**
     * 1. Отправляем запрос на звонок через /call/send с Bearer токеном
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

        // Параметры запроса согласно документации
        $params = [
            'to' => $userPhone,
            'user' => $this->login,
            'pass' => $this->password,
            'voice' => 'true',  // Проговаривать код голосом
            'lang' => 'ru'      // Язык голосового сообщения
        ];

        $result = $this->sendCall($params);

        if ($result['success']) {
            // Сохраняем request_id и ожидаемый код
            $_SESSION['request_id'] = $result['request_id'];
            $_SESSION['expected_code'] = $result['code'];

            return [
                'success' => true,
                'requestId' => $result['request_id'],
                'type' => 'stepTwo'
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Ошибка инициализации звонка'
        ];
    }

    /**
     * 2. Проверка статуса звонка (опционально)
     */
    public function checkCallStatusAction($requestId)
    {
        if (!$requestId) {
            return ['success' => false, 'error' => 'Не указан ID запроса'];
        }

        $result = $this->getCallStatus($requestId);

        if ($result['success']) {
            return [
                'success' => true,
                'status' => $result['status'],
                'statusCode' => $result['status_code']
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Ошибка проверки статуса'
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

        // Сравниваем введенный код с ожидаемым (4 цифры из Caller ID)
        if ($expectedCode && $expectedCode === $code) {
            $authResult = $this->authorizeUser($userPhone);

            if ($authResult['success']) {
                unset($_SESSION['auth_phone']);
                unset($_SESSION['request_id']);
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
            'error' => 'Неверный код. Введите последние 4 цифры номера, с которого поступил звонок.'
        ];
    }

    /**
     * Отправка звонка через API GreenSMS с Bearer токеном
     */
    private function sendCall($params)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrlSend);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // Используем Bearer токен для авторизации
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->bearerToken,
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Логируем для отладки
        $this->logDebug("Request URL: " . $this->apiUrlSend);
        $this->logDebug("Request params: " . print_r($params, true));
        $this->logDebug("Response HTTP code: " . $httpCode);
        $this->logDebug("Response body: " . $response);

        if ($response === false) {
            return ['success' => false, 'error' => 'CURL ошибка: ' . $error];
        }

        $data = json_decode($response, true);

        // Проверяем успешный ответ
        if ($httpCode === 200 && isset($data['request_id']) && isset($data['code'])) {
            return [
                'success' => true,
                'request_id' => $data['request_id'],
                'code' => $data['code']
            ];
        }

        // Обработка ошибок
        $errorMessage = $data['error'] ?? 'Ошибка сервиса (HTTP ' . $httpCode . ')';
        return ['success' => false, 'error' => $errorMessage];
    }

    /**
     * Проверка статуса звонка
     */
    private function getCallStatus($requestId)
    {
        $params = [
            'id' => $requestId,
            'user' => $this->login,
            'pass' => $this->password,
            'extended' => 'true'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrlStatus . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // Используем Bearer токен для авторизации
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->bearerToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => 'Ошибка соединения'];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && isset($data['status'])) {
            return [
                'success' => true,
                'status' => $data['status'],
                'status_code' => $data['status_code'] ?? 0
            ];
        }

        $errorMessage = $data['error'] ?? 'Ошибка получения статуса';
        return ['success' => false, 'error' => $errorMessage];
    }

    /**
     * Авторизация/регистрация пользователя
     */
    private function authorizeUser($phone)
    {

        return ['success' => true];
    }

    /**
     * Нормализация номера телефона
     */
    private function normalizePhone($phone)
    {
        // Удаляем все нецифровые символы
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Если номер начинается с 8, заменяем на 7
        if (strlen($phone) === 11 && $phone[0] === '8') {
            $phone = '7' . substr($phone, 1);
            return $phone;
        }

        // Если номер 10-значный, добавляем 7
        if (strlen($phone) === 10) {
            return '7' . $phone;
        }

        // Если номер уже в международном формате (11-14 цифр)
        if (strlen($phone) >= 11 && strlen($phone) <= 14) {
            return $phone;
        }

        return false;
    }

    /**
     * Функция для отладки
     */
    private function logDebug($message)
    {
        if (defined("BX_LOG_DEBUG") && BX_LOG_DEBUG) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/greensms_debug.log', date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
        }
    }
}