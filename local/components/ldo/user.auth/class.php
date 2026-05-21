<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Web\HttpClient;

class CUserAuth extends \CBitrixComponent implements Controllerable
{
    // Новый API ключ для voicepassword.ru (нужно зарегистрироваться и получить)
    private $apiToken = 'c83dc2f5000c4cac1292522102535380';
    private $apiUrl = 'https://vp.voicepassword.ru/api/voice-password/send/';

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
            'confirmCode' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                ],
            ],
        ];
    }

    /**
     * 1. Инициируем звонок с кодом (Voice Password)
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

        // Вызываем API для инициализации звонка
        $result = $this->startVoiceCall($userPhone);

        if ($result['success']) {
            // Сохраняем ID звонка для возможной проверки статуса
            $_SESSION['call_id'] = $result['callId'];
            $_SESSION['expected_code'] = $result['code'];

            return [
                'success' => true,
                'message' => 'На ваш номер поступит звонок. Введите 4-значный код, который продиктует робот.',
                'code_length' => 4
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Ошибка инициализации звонка'
        ];
    }

    /**
     * 2. Подтверждение кода и авторизация
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
        if ($expectedCode && $expectedCode == $code) {
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
     * Инициирование Voice Password звонка через API voicepassword.ru
     */
    private function startVoiceCall($phone)
    {
        $httpClient = new HttpClient();
        $httpClient->disableSslVerification();
        $httpClient->setTimeout(30);
        $httpClient->setStreamTimeout(30);

        // Формируем JSON запрос согласно документации
        $requestData = [
            'security' => [
                'apiKey' => $this->apiToken
            ],
            'number' => $phone,
            'voice' => [
                'repeat' => 1 // Повторять код дважды для надежности
            ]
            // Если не указывать 'code', сервер сгенерирует его автоматически
        ];

        $jsonData = json_encode($requestData);

        // Устанавливаем заголовки для JSON API
        $httpClient->setHeader('Content-Type', 'application/json');
        $httpClient->setHeader('Authorization', $this->apiToken);
        $httpClient->setHeader('Content-Length', strlen($jsonData));

        $response = $httpClient->post($this->apiUrl, $jsonData);

        if ($response === false) {
            $error = $httpClient->getError();
            return ['success' => false, 'error' => 'Ошибка соединения: ' . print_r($error, true)];
        }

        $data = json_decode($response, true);

        // Проверяем успешность ответа
        if ($data && isset($data['result']) && $data['result'] === 'ok') {
            return [
                'success' => true,
                'callId' => $data['id'], // ID запроса
                'code' => $data['code']  // Сгенерированный код
            ];
        }

        // Обработка ошибок
        $errorMessage = $data['error_code'] ?? 'Неизвестная ошибка';
        return ['success' => false, 'error' => $this->getErrorMessage($errorMessage)];
    }

    /**
     * Альтернативный метод - Flash Call (звонок с кодом в номере телефона)
     */
    private function startFlashCall($phone)
    {
        $httpClient = new HttpClient();
        $httpClient->disableSslVerification();
        $httpClient->setTimeout(30);

        // Генерируем случайный 4-значный код
        $code = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

        $requestData = [
            'security' => [
                'apiKey' => $this->apiToken
            ],
            'number' => $phone,
            'flashcall' => [
                'code' => $code
            ]
        ];

        $jsonData = json_encode($requestData);

        $httpClient->setHeader('Content-Type', 'application/json');
        $httpClient->setHeader('Authorization', $this->apiToken);

        $response = $httpClient->post($this->apiUrl, $jsonData);

        if ($response === false) {
            return ['success' => false, 'error' => 'Ошибка соединения'];
        }

        $data = json_decode($response, true);

        if ($data && isset($data['result']) && $data['result'] === 'ok') {
            return [
                'success' => true,
                'callId' => $data['id'],
                'code' => $code // Возвращаем сгенерированный код
            ];
        }

        $errorMessage = $data['error_code'] ?? 'Неизвестная ошибка';
        return ['success' => false, 'error' => $this->getErrorMessage($errorMessage)];
    }

    /**
     * Получение статуса звонка (опционально)
     */
    private function getCallStatus($callId)
    {
        $httpClient = new HttpClient();
        $httpClient->disableSslVerification();
        $httpClient->setTimeout(30);

        $requestData = [
            'security' => [
                'apiKey' => $this->apiToken
            ],
            'request' => 'state',
            'id' => $callId
        ];

        $jsonData = json_encode($requestData);
        $statusUrl = 'https://vp.voicepassword.ru/api/voice-password/get/';

        $httpClient->setHeader('Content-Type', 'application/json');
        $httpClient->setHeader('Authorization', $this->apiToken);

        $response = $httpClient->post($statusUrl, $jsonData);

        if ($response === false) {
            return ['success' => false, 'error' => 'Ошибка получения статуса'];
        }

        $data = json_decode($response, true);

        if ($data && isset($data['result']) && $data['result'] === 'ok') {
            return [
                'success' => true,
                'state' => $data['data']['state'] ?? null
            ];
        }

        return ['success' => false, 'error' => 'Статус не найден'];
    }

    /**
     * Проверка баланса аккаунта
     */
    public function checkBalanceAction()
    {
        $httpClient = new HttpClient();
        $httpClient->disableSslVerification();

        $requestData = [
            'security' => [
                'apiKey' => $this->apiToken
            ],
            'request' => 'balance'
        ];

        $jsonData = json_encode($requestData);
        $balanceUrl = 'https://vp.voicepassword.ru/api/voice-password/get/';

        $httpClient->setHeader('Content-Type', 'application/json');
        $httpClient->setHeader('Authorization', $this->apiToken);

        $response = $httpClient->post($balanceUrl, $jsonData);

        if ($response === false) {
            return ['success' => false, 'error' => 'Ошибка проверки баланса'];
        }

        $data = json_decode($response, true);

        if ($data && isset($data['result']) && $data['result'] === 'ok') {
            return [
                'success' => true,
                'balance' => $data['balance'],
                'currency' => $data['currency']
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

        // Очищаем телефон от лишних символов
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Ищем пользователя по телефону
        $userLogin = 'user_' . $phone;

        // Пробуем найти по логину
        $userData = \CUser::GetByLogin($userLogin)->Fetch();

        // Если не нашли, ищем по пользовательскому полю телефона
        if (!$userData) {
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
                'GROUP_ID' => [2], // Группа "Все пользователи"
                'UF_PHONE' => $phone // Пользовательское поле для телефона
            ];

            $userId = $user->Add($fields);

            if ($userId) {
                $USER->Authorize($userId);
                return ['success' => true];
            }

            return ['success' => false, 'error' => 'Ошибка создания пользователя: ' . $user->LAST_ERROR];
        }

        // Авторизуем существующего пользователя
        $USER->Authorize($userData['ID']);
        return ['success' => true];
    }

    /**
     * Нормализация номера телефона
     */
    private function normalizePhone($phone)
    {
        // Удаляем все нецифровые символы
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Проверяем, что номер начинается с 7 или 8
        if (strlen($phone) === 11 && ($phone[0] === '7' || $phone[0] === '8')) {
            // Если начинается с 8, заменяем на 7
            if ($phone[0] === '8') {
                $phone = '7' . substr($phone, 1);
            }
            return $phone;
        }

        // Если номер из 10 цифр (без кода страны), добавляем 7
        if (strlen($phone) === 10) {
            return '7' . $phone;
        }

        return false;
    }

    /**
     * Получение понятного сообщения об ошибке
     */
    private function getErrorMessage($errorCode)
    {
        $messages = [
            'authorisation_failed' => 'Ошибка авторизации. Проверьте API ключ.',
            'user_disabled' => 'Ваш аккаунт заблокирован.',
            'number_is_empty' => 'Некорректно указан номер телефона.',
            'number_not_valid' => 'Не удалось определить направление звонка.',
            'number_not_permitted' => 'Звонки на данное направление запрещены.',
            'number_in_spam_list' => 'Номер занесен в СПАМ лист.',
            'not_enough_money' => 'Недостаточно средств на счете.',
            'unknown_request' => 'Ошибочный запрос.'
        ];

        return $messages[$errorCode] ?? 'Ошибка сервиса: ' . $errorCode;
    }
}