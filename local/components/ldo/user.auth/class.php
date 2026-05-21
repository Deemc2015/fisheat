<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;

class CUserAuth extends \CBitrixComponent implements Controllerable
{
    // ВАШИ КЛЮЧИ из личного кабинета Нью-Тел (замените на реальные)
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
        ];
    }

    /**
     * Формирование Bearer-токена
     */
    private function getToken($methodName, $params, $time)
    {
        return $this->authKey . $time . hash(
                'sha256',
                $methodName . "\n" . $time . "\n" . $this->authKey . "\n" . $params . "\n" . $this->signKey
            );
    }

    /**
     * Выполнение CURL запроса
     */
    private function curlRequest($methodName, $paramsArray)
    {
        $jsonParams = json_encode($paramsArray);
        $time = time();
        $token = $this->getToken($methodName, $jsonParams, $time);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . $methodName);
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

        if ($response === false) {
            return ['success' => false, 'error' => 'CURL ошибка: ' . $error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'HTTP ошибка: ' . $httpCode];
        }

        return ['success' => true, 'data' => json_decode($response, true)];
    }

    /**
     * 1. Инициируем звонок (nextStep)
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

        // Генерируем PIN (последние 4 цифры номера)
        $pin = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $_SESSION['expected_code'] = $pin;

        $params = [
            'dstNumber' => $userPhone,
            'pin' => $pin,
            'fixedCid' => 0
        ];

        $response = $this->curlRequest('call-password/start-password-call', $params);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        $data = $response['data'];

        if (isset($data['status']) && $data['status'] === 'success') {
            if (isset($data['data']['result']) && $data['data']['result'] === 'success') {
                $callDetails = $data['data']['callDetails'] ?? [];

                if (isset($callDetails['isValidNumber']) && $callDetails['isValidNumber'] === true) {
                    $_SESSION['call_id'] = $callDetails['callId'];

                    return [
                        'success' => true,
                        'callId' => $callDetails['callId'],
                        'message' => 'На ваш номер поступит звонок. Введите последние 4 цифры номера, с которого поступил вызов.'
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Номер телефона недействителен'
                    ];
                }
            } else {
                $errorMessage = $data['data']['message'] ?? 'Ошибка сервиса';
                return ['success' => false, 'error' => $errorMessage];
            }
        }

        return ['success' => false, 'error' => 'Ошибка API'];
    }

    /**
     * 2. Проверка статуса звонка (для вашего JS - checkCallStatus)
     */
    public function checkCallStatusAction($callId)
    {
        $params = ['callId' => $callId];
        $response = $this->curlRequest('call-password/get-password-call-status', $params);

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
                    'cancel' => 'CANCEL',
                    'busy' => 'USER_BUSY',
                    'no answer' => 'NO_ANSWER',
                    'not available' => 'NOT_AVAILABLE'
                ];

                $status = $callDetails['status'] ?? 'unknown';
                $mappedStatus = $statusMap[$status] ?? $status;

                return [
                    'success' => true,
                    'status' => $mappedStatus,
                    'callId' => $callId
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $data['data']['message'] ?? 'Звонок не найден'
                ];
            }
        }

        return ['success' => false, 'error' => 'Ошибка получения статуса'];
    }

    /**
     * 3. Подтверждение кода (confirmCode)
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
     * Авторизация пользователя в Битрикс
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