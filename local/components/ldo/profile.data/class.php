<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;

class CProfile extends \CBitrixComponent implements Controllerable
{
    protected $userId;

    public function executeComponent()
    {
        global $USER;

        if (!$USER->IsAuthorized()) {
            return false;
        }

        $this->userId = $USER->GetID();

        // Кеширование данных пользователя
        $this->arResult = $this->getCachedUserData($this->userId);

        $this->includeComponentTemplate();
    }

    public function configureActions()
    {
        return [
            'sendForm' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class
                ],
            ],
            'deleteUser' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class
                ],
            ]
        ];
    }

    /**
     * Получение данных пользователя с кешированием
     */
    private function getCachedUserData($userID)
    {
        $cache = Cache::createInstance();
        $cacheKey = 'ldo_profile_data_' . $userID;
        $cachePath = '/ldo/profile/';
        $cacheTime = 3600; // 1 час

        // Очищаем кеш если передан параметр
        if (isset($_GET['clear_cache']) && $_GET['clear_cache'] == 'Y') {
            $cache->clean($cacheKey, $cachePath);
        }

        if ($cache->initCache($cacheTime, $cacheKey, $cachePath)) {
            // Возвращаем данные из кеша
            return $cache->getVars();
        } elseif ($cache->startDataCache()) {
            // Получаем данные из БД
            $userData = $this->getUserData($userID);

            // Тегируем кеш для возможности групповой очистки
            $taggedCache = \Bitrix\Main\Application::getInstance()->getTaggedCache();
            $taggedCache->startTagCache($cachePath);
            $taggedCache->registerTag('user_data_' . $userID);
            $taggedCache->registerTag('user_profile_' . $userID);
            $taggedCache->endTagCache();

            $cache->endDataCache($userData);
            return $userData;
        }

        return false;
    }

    /**
     * Очистка кеша пользователя
     */
    private function clearUserCache($userID)
    {
        $cache = Cache::createInstance();
        $cacheKey = 'ldo_profile_data_' . $userID;
        $cachePath = '/ldo/profile/';

        // Очищаем основной кеш
        $cache->clean($cacheKey, $cachePath);

        // Очищаем тегированный кеш
        $taggedCache = \Bitrix\Main\Application::getInstance()->getTaggedCache();
        $taggedCache->clearByTag('user_data_' . $userID);
        $taggedCache->clearByTag('user_profile_' . $userID);

        return true;
    }

    private function getUserData($userID)
    {
        $user = CUser::GetByID($userID)->Fetch();

        if (!$user) {
            return false;
        }

        return array(
            'EMAIL' => $user['EMAIL'],
            'NAME' => $user['NAME'],
            'PHONE' => $user['PERSONAL_PHONE'],
            'BIRTHDAY' => $user['PERSONAL_BIRTHDAY']
        );
    }

    public function sendFormAction($post)
    {
        if (is_string($post)) {
            parse_str($post, $postArray);
            $post = $postArray;
        }

        if($post['PHONE']){
                return [
                    'success' => false,
                    'error' => 'Нельзя изменить номер телефона, удалите аккаунт и зайдите с новым номером.'
                ];
        }

        $userFields = [];

        $userFields = [
            'NAME' => $post['NAME'] ?? '',
            'EMAIL' => $post['EMAIL'] ?? '',
        ];

        global $USER;
        $userInfo = $this->getUserData($USER->GetID());

        // Проверка даты рождения
        if(isset($post['DATEB']) && !empty($post['DATEB'])){
            $birthdayFromDB = $userInfo['BIRTHDAY'];

            // Если в БД есть дата
            if(!empty($birthdayFromDB) && $birthdayFromDB !== null && $birthdayFromDB !== ''){
                // Сравниваем напрямую
                if($birthdayFromDB !== $post['DATEB']){
                    return [
                        'success' => false,
                        'error' => 'Дата рождения уже указана и не может быть изменена'
                    ];
                }
                // Если даты совпадают - ничего не делаем
            } else {
                // Если в БД нет даты - сохраняем
                $userFields['PERSONAL_BIRTHDAY'] = $post['DATEB'];
            }
        }

        $resultUpdate = $this->updateUser($userFields);

        return $resultUpdate;
    }

    public function deleteUserAction($delete)
    {
        if($delete == 'Y'){
            return $this->deleteUser();
        }

        return ['success' => false, 'error' => 'Неверный параметр'];
    }

    private function deleteUser()
    {
        global $USER;

        $user = new CUser;

        $fields = [
            'ACTIVE' => 'N'
        ];

        $user = new CUser;
        $result = $user->Update($USER->GetID(), $fields);

        if ($result) {
            // Очищаем кеш перед удалением
            $this->clearUserCache($USER->GetID());
            $USER->Logout();
            return ['success' => true, 'message' => 'Аккаунт удален'];
        } else {
            return ['success' => false, 'error' => $user->LAST_ERROR];
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

    /**
     * Обновление данных пользователя с очисткой кеша
     */
    private function updateUser(array $fields)
    {
        global $USER;
        $idUser = $USER->GetID();

        if (!$idUser) {
            return [
                'success' => false,
                'error' => 'Пользователь не авторизован'
            ];
        }

        $user = new CUser;
        $result = $user->Update($idUser, $fields);

        if ($result) {
            // Очищаем кеш после успешного обновления
            $this->clearUserCache($idUser);

            return [
                'success' => true,
                'message' => 'Данные успешно обновлены',
                'data' => $fields
            ];
        } else {
            $error = $user->LAST_ERROR ?: 'Ошибка при обновлении данных';
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }
}