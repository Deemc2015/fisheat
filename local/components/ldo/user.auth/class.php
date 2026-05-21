<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Web\HttpClient;

class CUserAuth extends \CBitrixComponent implements Controllerable
{
    private $apiToken = '0a2532ebe145039a1f9356451746a0139a2adc979c5f51c1ba6e4877450940ba';
    private $apiUrl = 'https://api.call-password.ru/api/v2.0/';

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
     * 1. Инициируем звонок с кодом
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
        $result = $this->startCall($userPhone);

        if ($result['success']) {
            // Сохраняем callId для последующей проверки
            $_SESSION['call_id'] = $result['callId'];

            return [
                'success' => true,
                'callId' => $result['callId'],
                'message' => 'На ваш номер поступит звонок. Введите последние 4 цифры номера, с которого поступил вызов.'
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Ошибка инициализации звонка'
        ];
    }

    /**
     * 2. Проверяем статус звонка и получаем код
     */
    public function checkCallStatusAction($callId)
    {
        $result = $this->getCallStatus($callId);

        if ($result['success']) {
            return [
                'success' => true,
                'status' => $result['status'],
                'code' => $result['code'] ?? null
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
        $callId = $_SESSION['call_id'] ?? null;

        if (!$userPhone || !$callId) {
            return [
                'success' => false,
                'error' => 'Сессия истекла. Попробуйте снова.'
            ];
        }

        // Проверяем статус звонка для получения кода
        $result = $this->getCallStatus($callId);

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Ошибка проверки кода'
            ];
        }

        // Проверяем статус звонка
        if ($result['status'] !== 'NORMAL_CLEARING') {
            $statusMessages = [
                'USER_BUSY' => 'Номер занят. Попробуйте снова.',
                'NO_ANSWER' => 'Нет ответа. Попробуйте снова.',
                'null' => 'Звонок еще не совершен. Подождите или попробуйте снова.'
            ];

            return [
                'success' => false,
                'error' => $statusMessages[$result['status']] ?? 'Звонок не состоялся. Попробуйте снова.'
            ];
        }

        // Сравниваем введенный код с кодом из звонка
        if ($result['code'] == $code) {
            // Авторизуем или регистрируем пользователя
            $authResult = $this->authorizeUser($userPhone);

            if ($authResult['success']) {
                // Очищаем сессию
                unset($_SESSION['auth_phone']);
                unset($_SESSION['call_id']);

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
     * Инициирование звонка через API
     */
    private function startCall($phone)
    {
        $httpClient = new HttpClient();
        $httpClient->disableSslVerification();
        $httpClient->setTimeout(60); // Увеличиваем таймаут для синхронного режима
        $httpClient->setStreamTimeout(60);

        // ВАЖНО: правильные заголовки
        $httpClient->setHeader('Authorization', 'Bearer ' . $this->apiToken);
        $httpClient->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $httpClient->setHeader('Content-Length', strlen(http_build_query([
            'dn' => $phone,
            'timeout' => 30,
            'async' => 0 // Меняем на синхронный режим, чтобы получить pin сразу
        ])));

        // Используем API v1.0 или v2.0 с async=0
        $url = $this->apiUrl . 'v1.0/start-call-password/'; // v1.0 всегда синхронный

        $postData = http_build_query([
            'dn' => $phone,
            'timeout' => 30
            // async не нужен для v1.0
        ]);

        $response = $httpClient->post($url, $postData);

        if ($response === false) {
            $error = $httpClient->getError();
            return ['success' => false, 'error' => 'Ошибка соединения: ' . print_r($error, true)];
        }

        $data = json_decode($response, true);

        // Проверяем статус ответа по документации
        if ($data && isset($data['status']) && $data['status'] === 'success') {
            // Для синхронного режима result должен быть не null
            if (isset($data['data']['result']) && $data['data']['result'] !== 'null') {
                return [
                    'success' => true,
                    'callId' => $data['data']['callid'],
                    'code' => $data['data']['pin'] ?? null // pin доступен только в синхронном режиме
                ];
            } else {
                // Если звонок еще не завершен
                return [
                    'success' => false,
                    'error' => 'Звонок еще не совершен. Попробуйте позже.',
                    'callId' => $data['data']['callid'] ?? null
                ];
            }
        }

        $errorMessage = $data['data']['message'] ?? 'Неизвестная ошибка';
        return ['success' => false, 'error' => $errorMessage];
    }


    /**
     * Получение статуса звонка
     */
    private function getCallStatus($callId)
    {
        $httpClient = new HttpClient();
        $httpClient->setHeader('Authorization', 'Bearer ' . $this->apiToken);
        $httpClient->setHeader('Content-Type', 'application/x-www-form-urlencoded');

        $postData = http_build_query([
            'callId' => $callId
        ]);

        $url = $this->apiUrl . 'get-call-password-status/';
        $response = $httpClient->post($url, $postData);

        if ($response === false) {
            return ['success' => false, 'error' => 'Ошибка соединения с сервером'];
        }

        $data = json_decode($response, true);

        if ($data && $data['status'] === 'success') {
            return [
                'success' => true,
                'status' => $data['data']['result'],
                'code' => $data['data']['pin'] ?? null
            ];
        }

        $errorMessage = $data['data']['message'] ?? 'Неизвестная ошибка';
        return ['success' => false, 'error' => $errorMessage];
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

        $userData = \CUser::GetByLogin($userLogin)->Fetch();

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
                'UF_PHONE' => $phone // Если есть пользовательское поле для телефона
            ];

            $userId = $user->Add($fields);

            if ($userId) {
                // Авторизуем созданного пользователя
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
}
?>