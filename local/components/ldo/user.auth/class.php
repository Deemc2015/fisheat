<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;

class CUserAuth extends \CBitrixComponent implements Controllerable
{
    // Данные из личного кабинета GreenSMS
    private $login = 'deemc';
    private $password = '0479096qQ!';
    private $apiUrl = 'https://api3.greensms.ru/call/';

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
     * 1. Отправляем запрос на звонок
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

        // Параметры запроса
        $params = [
            'to' => $userPhone,
            'voice' => 'true',  // Проговаривать код голосом (опционально)
            'lang' => 'ru'      // Язык голосового сообщения
        ];

        $result = $this->sendCall($params);

        if ($result['success']) {
            // Сохраняем request_id для проверки статуса (опционально)
            $_SESSION['call_id'] = $result['request_id'];
            // Сохраняем ожидаемый код (последние 4 цифры номера)
            $_SESSION['expected_code'] = $result['code'];

            return [
                'success' => true,
                'callId' => $result['request_id'],
                'message' => 'На ваш номер поступит звонок. Введите последние 4 цифры входящего номера.'
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
    public function checkCallStatusAction($callId)
    {
        $result = $this->getCallStatus($callId);

        if ($result['success']) {
            $statusMap = [
                'Call success' => 'NORMAL_CLEARING',
                'Call failure' => 'CALL_FAILURE',
                'Call rejected' => 'CALL_REJECTED',
                'Call buffered' => 'BUFFERED',
                'Status not ready' => 'PENDING'
            ];

            return [
                'success' => true,
                'status' => $statusMap[$result['status']] ?? $result['status']
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

        // Сравниваем введенный код с ожидаемым
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
            'error' => 'Неверный код. Введите последние 4 цифры номера, с которого поступил звонок.'
        ];
    }

    /**
     * Отправка звонка через API GreenSMS
     */
    private function sendCall($params)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . 'send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // Авторизация через user/pass (можно заменить на Bearer токен)
        curl_setopt($ch, CURLOPT_USERPWD, $this->login . ':' . $this->password);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => 'CURL ошибка: ' . $error];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && isset($data['request_id'])) {
            return [
                'success' => true,
                'request_id' => $data['request_id'],
                'code' => $data['code']
            ];
        }

        $errorMessage = $data['error'] ?? 'Ошибка сервиса (HTTP ' . $httpCode . ')';
        return ['success' => false, 'error' => $errorMessage];
    }

    /**
     * Проверка статуса звонка
     */
    private function getCallStatus($callId)
    {
        $params = ['id' => $callId];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . 'status?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERPWD, $this->login . ':' . $this->password);

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
                'status_code' => $data['status_code'] ?? null
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
        global $USER;

        $phone = preg_replace('/[^0-9]/', '', $phone);
        $userLogin = 'user_' . $phone;

        $userData = \CUser::GetByLogin($userLogin)->Fetch();

        if (!$userData) {
            $filter = ['UF_PHONE' => $phone];
            $rsUser = \CUser::GetList(($by = 'id'), ($order = 'asc'), $filter);
            $userData = $rsUser->Fetch();
        }

        if (!$userData) {
            $password = \Bitrix\Main\Security\Random::getString(8, true);

            $user = new \CUser();
            $fields = [
                'LOGIN' => $userLogin,
                'NAME' => 'Пользователь',
                'PASSWORD' => $password,
                'CONFIRM_PASSWORD' => $password,
                'ACTIVE' => 'Y',
                'GROUP_ID' => [2],
                'UF_PHONE' => $phone
            ];

            $userId = $user->Add($fields);

            if ($userId) {
                $USER->Authorize($userId);
                return ['success' => true];
            }

            return ['success' => false, 'error' => $user->LAST_ERROR];
        }

        $USER->Authorize($userData['ID']);
        return ['success' => true];
    }

    /**
     * Нормализация номера телефона
     * GreenSMS принимает номера от 11 до 14 цифр
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