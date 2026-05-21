<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Web\HttpClient;

class CUserAuth extends \CBitrixComponent implements Controllerable
{
    // ВАШИ КЛЮЧИ из личного кабинета Нью-Тел
    // Раздел "CallPassword – Авторизация по звонку"
    private $authKey = '958234bf11a4b741f927bdb53798c83bfeaff24a6a7daeb3';  // ключ API для авторизации
    private $signKey = '68a4b84dfd2ab242e328f25dd3bbecf11518683e82db35b1';   // ключ API для подписи
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
     * Формирование Bearer-токена по документации Нью-Тел
     */
    private function getToken($methodName, $params, $time)
    {
        return $this->authKey . $time . hash(
                'sha256',
                $methodName . "\n" . $time . "\n" . $this->authKey . "\n" . $params . "\n" . $this->signKey
            );
    }

    /**
     * 1. Инициируем звонок с PIN-кодом (последние 4 цифры номера)
     */
    public function nextStepAction($userPhone)
    {
        // Валидация телефона
        $userPhone = $this->normalizePhone($userPhone);
        if (!$userPhone) {
            return [
                'success' => false,
                'error' => 'Неверный формат номера телефона'
            ];
        }

        // Сохраняем телефон в сессию
        session_start();
        $_SESSION['auth_phone'] = $userPhone;

        // Генерируем случайный 4-значный PIN-код
        $pin = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $_SESSION['expected_code'] = $pin;

        // Вызываем API для инициализации звонка
        $result = $this->startPasswordCall($userPhone, $pin);

        if ($result['success']) {
            $_SESSION['call_id'] = $result['callId'];

            return [
                'success' => true,
                'callId' => $result['callId'],
                'message' => 'На ваш номер поступит звонок. Введите последние 4 цифры номера, с которого поступил вызов.',
                'debug_pin' => $pin // Уберите в production
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Ошибка инициализации звонка'
        ];
    }

    /**
     * 2. Проверяем статус звонка (опционально)
     */
    public function checkCallStatusAction($callId)
    {
        $result = $this->getPasswordCallStatus($callId);

        if ($result['success']) {
            return [
                'success' => true,
                'status' => $result['status'],
                'callId' => $callId
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Ошибка проверки статуса звонка'
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
            // Авторизуем или регистрируем пользователя
            $authResult = $this->authorizeUser($userPhone);

            if ($authResult['success']) {
                // Очищаем сессию
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
     * Инициирование звонка CallPassword
     * Метод: call-password/start-password-call
     */
    private function startPasswordCall($phone, $pin)
    {
        // Формируем параметры запроса
        $params = [
            'dstNumber' => $phone,
            'pin' => $pin,
            'fixedCid' => 0 // 0 - использовать номер из общего пула, 1 - закрепленный номер
        ];

        $jsonParams = json_encode($params);
        $time = time();
        $methodName = 'call-password/start-password-call';

        // Формируем токен
        $token = $this->getToken($methodName, $jsonParams, $time);

        // Выполняем запрос
        $httpClient = new HttpClient();
        $httpClient->disableSslVerification(); // Для тестирования, в проде лучше включить
        $httpClient->setTimeout(30);
        $httpClient->setStreamTimeout(30);

        // Устанавливаем заголовки
        $httpClient->setHeader('Authorization', 'Bearer ' . $token);
        $httpClient->setHeader('Content-Type', 'application/json');
        $httpClient->setHeader('Accept', 'application/json');

        $url = $this->apiUrl . $methodName;
        $response = $httpClient->post($url, $jsonParams);

        // Логируем для отладки
        addMessage2Log('Нью-Тел API запрос: ' . $url);
        addMessage2Log('Параметры: ' . $jsonParams);
        addMessage2Log('Ответ: ' . $response);

        if ($response === false) {
            $error = $httpClient->getError();
            return ['success' => false, 'error' => 'Ошибка соединения: ' . print_r($error, true)];
        }

        $data = json_decode($response, true);

        // Анализируем ответ по документации
        if ($data && isset($data['status']) && $data['status'] === 'success') {
            if (isset($data['data']['result']) && $data['data']['result'] === 'success') {
                $callDetails = $data['data']['callDetails'] ?? [];

                // Проверяем валидность номера
                if (isset($callDetails['isValidNumber']) && $callDetails['isValidNumber'] === true) {
                    return [
                        'success' => true,
                        'callId' => $callDetails['callId'],
                        'pin' => $callDetails['pin'],
                        'operator' => $callDetails['oper'] ?? null,
                        'region' => $callDetails['region'] ?? null
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Номер телефона невалиден или не обслуживается'
                    ];
                }
            } else {
                $errorMessage = $data['data']['message'] ?? 'Неизвестная ошибка API';
                return ['success' => false, 'error' => $errorMessage];
            }
        }

        $errorMessage = $data['message'] ?? 'Неизвестная ошибка';
        return ['success' => false, 'error' => $errorMessage];
    }

    /**
     * Получение статуса звонка CallPassword
     * Метод: call-password/get-password-call-status
     */
    private function getPasswordCallStatus($callId)
    {
        $params = ['callId' => $callId];
        $jsonParams = json_encode($params);
        $time = time();
        $methodName = 'call-password/get-password-call-status';

        $token = $this->getToken($methodName, $jsonParams, $time);

        $httpClient = new HttpClient();
        $httpClient->disableSslVerification();
        $httpClient->setTimeout(30);
        $httpClient->setHeader('Authorization', 'Bearer ' . $token);
        $httpClient->setHeader('Content-Type', 'application/json');
        $httpClient->setHeader('Accept', 'application/json');

        $url = $this->apiUrl . $methodName;
        $response = $httpClient->post($url, $jsonParams);

        if ($response === false) {
            return ['success' => false, 'error' => 'Ошибка соединения'];
        }

        $data = json_decode($response, true);

        if ($data && isset($data['status']) && $data['status'] === 'success') {
            if (isset($data['data']['result']) && $data['data']['result'] === 'success') {
                $callDetails = $data['data']['callDetails'] ?? [];
                return [
                    'success' => true,
                    'status' => $callDetails['status'] ?? 'unknown',
                    'reasonCode' => $callDetails['reasonCode'] ?? null,
                    'summa' => $callDetails['summa'] ?? null
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
     * Проверка баланса (опционально)
     */
    public function getBalanceAction()
    {
        $params = ['request' => 'balance'];
        $jsonParams = json_encode($params);
        $time = time();
        $methodName = 'company/get-state';

        $token = $this->getToken($methodName, $jsonParams, $time);

        $httpClient = new HttpClient();
        $httpClient->disableSslVerification();
        $httpClient->setHeader('Authorization', 'Bearer ' . $token);
        $httpClient->setHeader('Content-Type', 'application/json');

        $response = $httpClient->post($this->apiUrl . $methodName, $jsonParams);

        if ($response === false) {
            return ['success' => false, 'error' => 'Ошибка проверки баланса'];
        }

        $data = json_decode($response, true);

        if ($data && isset($data['status']) && $data['status'] === 'success') {
            $state = $data['data']['state'] ?? [];
            return [
                'success' => true,
                'balance' => $state['balance'] ?? 'неизвестно',
                'currency' => $state['currency'] ?? 'RUB',
                'status' => $state['status'] ?? 'unknown'
            ];
        }

        return ['success' => false, 'error' => 'Ошибка получения баланса'];
    }

    /**
     * Авторизация/регистрация пользователя в Битрикс
     */
    private function authorizeUser($phone)
    {
        global $USER;

        $phone = preg_replace('/[^0-9]/', '', $phone);
        $userLogin = 'user_' . $phone;

        // Ищем пользователя
        $userData = \CUser::GetByLogin($userLogin)->Fetch();

        if (!$userData) {
            // Проверяем по полю телефона
            $filter = ['UF_PHONE' => $phone];
            $rsUser = \CUser::GetList(($by = 'id'), ($order = 'asc'), $filter);
            $userData = $rsUser->Fetch();
        }

        if (!$userData) {
            // Создаем нового пользователя
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

            return ['success' => false, 'error' => 'Ошибка создания пользователя: ' . $user->LAST_ERROR];
        }

        $USER->Authorize($userData['ID']);
        return ['success' => true];
    }

    /**
     * Нормализация номера телефона (формат E.164)
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