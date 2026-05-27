<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Bitrix\Sale\Fuser;
use Bitrix\Sale\Basket;

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


        $isBan = $this->isBan($userPhone);

        if($isBan){
            return [
                'success' => false,
                'error' => 'Аккаунт с таким телефоном заблокирован. '
                    . 'Обратитесь к администратору.'
            ];

            return false;
        }




        $isDisabledUser = $this->isDisabled($userPhone);

        if($isDisabledUser){
            return [
                'success' => false,
                'error' => 'Аккаунт с таким телефоном был ранее удален. '
                    . 'Для восстановления, обратитесь к администратору.'
            ];

            return false;
        }

        session_start();
        $_SESSION['auth_phone'] = $userPhone;

        // Параметры запроса
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
     * 2. Подтверждение кода и авторизация/регистрация
     */
    public function confirmCodeAction($code)
    {
        session_start();

        $userPhone = $_SESSION['auth_phone'] ?? null;
        $expectedCode = $_SESSION['expected_code'] ?? null;

        if (!$userPhone || !$expectedCode) {
            return [
                'success' => false,
                'error' => 'Сессия истекла. Попробуйте снова.'
            ];
        }

        // Сравниваем коды
        if ($expectedCode === $code) {
            // Сохраняем старый FUSER до авторизации
            $oldFuserId = null;
            if (Loader::includeModule('sale')) {
                $oldFuserId = Fuser::getId();
            }

            $authResult = $this->authorizeUser($userPhone);

            if ($authResult['success']) {
                // Переносим корзину после успешной авторизации
                if ($oldFuserId && Loader::includeModule('sale')) {
                    global $USER;
                    $userId = $USER->GetID();
                    $this->mergeBasketAfterAuth($oldFuserId, $userId);
                }

                unset($_SESSION['auth_phone']);
                unset($_SESSION['expected_code']);
                unset($_SESSION['request_id']);

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

        // Неверный код
        return [
            'success' => false,
            'error' => 'Неверный код.'
        ];
    }

    /**
     * Перенос корзины при авторизации
     */
    private function mergeBasketAfterAuth($oldFuserId, $userId)
    {
        if (!Loader::includeModule('sale')) {
            return;
        }

        try {
            $siteId = Context::getCurrent()->getSite();

            // Получаем новый FUSER для авторизованного пользователя
            $newFuserId = Fuser::getIdByUserId($userId);

            // Если FUSER совпадают, то перенос не нужен
            if ($oldFuserId == $newFuserId) {
                return;
            }

            // Загружаем старую корзину (анонимную)
            $oldBasket = Basket::loadItemsForFUser($oldFuserId, $siteId);

            // Загружаем новую корзину (пользовательскую, если есть)
            $newBasket = Basket::loadItemsForFUser($newFuserId, $siteId);

            // Объединяем корзины
            if ($oldBasket->count() > 0) {
                foreach ($oldBasket as $oldItem) {
                    $productId = $oldItem->getProductId();
                    $moduleId = $oldItem->getField('MODULE');
                    $quantity = $oldItem->getQuantity();

                    // Проверяем, есть ли уже такой товар в новой корзине
                    $existingItem = $newBasket->getExistsItem($moduleId, $productId);

                    if ($existingItem) {
                        // Если товар уже есть - увеличиваем количество
                        $newQuantity = $existingItem->getQuantity() + $quantity;
                        $existingItem->setField('QUANTITY', $newQuantity);
                    } else {
                        // Если товара нет - переносим его
                        $newItem = $newBasket->createItem($moduleId, $productId);
                        $newItem->setFields([
                            'QUANTITY' => $quantity,
                            'CURRENCY' => $oldItem->getCurrency(),
                            'LID' => $siteId,
                            'PRICE' => $oldItem->getPrice(),
                            'CUSTOM_PRICE' => $oldItem->isCustomPrice() ? 'Y' : 'N',
                            'CAN_BUY' => $oldItem->canBuy() ? 'Y' : 'N',
                            'DELAY' => $oldItem->isDelay() ? 'Y' : 'N',
                        ]);

                        // Переносим свойства товара
                        $oldPropertyCollection = $oldItem->getPropertyCollection();
                        $newPropertyCollection = $newItem->getPropertyCollection();

                        $props = [];
                        foreach ($oldPropertyCollection as $property) {
                            $props[] = [
                                'NAME' => $property->getField('NAME'),
                                'CODE' => $property->getField('CODE'),
                                'VALUE' => $property->getField('VALUE'),
                                'SORT' => $property->getField('SORT'),
                            ];
                        }

                        if (!empty($props)) {
                            $newPropertyCollection->setProperty($props);
                        }
                    }
                }

                // Сохраняем новую корзину
                $saveResult = $newBasket->save();
                if (!$saveResult->isSuccess()) {
                    $errors = $saveResult->getErrorMessages();
                    // Логируем ошибку, но не прерываем авторизацию
                    \Bitrix\Main\Diag\Debug::writeToFile(
                        implode(', ', $errors),
                        'Basket merge error: ',
                        '/local/logs/basket.log'
                    );
                }

                // Очищаем старую корзину
                $oldBasket->clearCollection();
                $oldBasket->save();
            }

            // Обновляем FUSER в текущей сессии
            Fuser::refreshSessionCurrentId();

        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем авторизацию
            \Bitrix\Main\Diag\Debug::writeToFile(
                $e->getMessage(),
                'Basket merge exception: ',
                '/local/logs/basket.log'
            );
        }
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
     * Авторизация/регистрация пользователя
     */
    private function authorizeUser($phone)
    {
        $userInDb = $this->isExist($phone);

        if($userInDb){
            $resultAuth  = $this->authUser($userInDb);
            if($resultAuth){
                return ['success' => true];
            }
            else{
                return ['success' => false, 'error' => 'Ошибка авторизации.'];
            }
        }
        else{
            $resultAuth  = $this->registrationUser($phone);

            $userUpdate = $this->updateUser($resultAuth['ID'],$phone);

            if($resultAuth['TYPE'] == 'OK'){
                $resultAuth = $this->authUser($resultAuth['ID']);

                if($resultAuth){
                    return ['success' => true];
                }
                else{
                    return ['success' => false, 'error' => 'Ошибка регистрации.'];
                }
            }

            return ['success' => false, 'error' => 'Ошибка регистрации.'];
        }
    }

    /**
     * Проверка пользователя на активность
     */
    private function isDisabled($phone)
    {
        $dbUsers = CUser::GetList([], [], ['LOGIN' => $phone]);

        if ($arUser = $dbUsers->Fetch()){
            if($arUser['ACTIVE'] == 'N'){
                return true;
            }
        }
    }


    /**
     * Проверка пользователя на блокировку
     */
    private function isBan($phone)
    {
        $dbUsers = CUser::GetList([], [], ['LOGIN' => $phone]);

        if ($arUser = $dbUsers->Fetch()){
            if($arUser['BLOCKED'] == 'Y'){
                return true;
            }
        }
    }

    /**
     * Проверка пользователя на наличие в БД
     */
    private function isExist($phone)
    {
        $dbUsers = CUser::GetList([], [], ['LOGIN' => $phone]);

        if ($arUser = $dbUsers->Fetch()){
            return $arUser['ID'];
        }

        return false;
    }

    /**
     * Авторизация пользователя
     */
    private function authUser(int $id){
        global $USER;

        $authResult = $USER->Authorize($id);

        if($authResult){
            return true;
        }

        return false;
    }

    /**
     * Регистрация пользователя
     */
    private function registrationUser($phone){
        global $USER;

        $arResult = $USER->Register($phone, "", "", "123456", "123456", $phone.'@gmail.com', SITE_ID);

        return $arResult;
    }

    private function updateUser(int $userId, $phone){

        $user = new CUser;

        $fields = [
            'EMAIL' => '',
            'PERSONAL_PHONE' => $phone
	    ];

        $user->Update($userId, $fields);

        if($user->LAST_ERROR){
            return $user->LAST_ERROR;
        }

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
}