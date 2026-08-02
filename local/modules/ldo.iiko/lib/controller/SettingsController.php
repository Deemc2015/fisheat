<?php
namespace Ldo\Iiko\Controller;

use Bitrix\Main\Engine\ActionFilter\Authentication;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use Ldo\Iiko\Auth;
use Ldo\Iiko\SettingsTable;

class SettingsController extends Controller
{
    /**
     * Ограничиваем действие сохранения авторизованными пользователями.
     */
    public function configureActions()
    {
        return [
            'save' => [
                'prefilters' => [
                    new Authentication(),
                ],
            ],
        ];
    }

    /**
     * Сохраняет настройки iiko и проверяет связь: обращается к Auth,
     * пробует получить токен. Если токен получен — статус "связь установлена" (OK).
     *
     * @param string $siteId  ID сайта (по умолчанию s1)
     * @param string $apiLogin
     * @param string $secret
     * @param string $appId
     * @return array|null
     */
    public function saveAction($siteId = 's1', $apiLogin = '', $secret = '', $appId = '')
    {
        $siteId   = trim((string)$siteId);
        $apiLogin = trim((string)$apiLogin);
        $secret   = trim((string)$secret);
        $appId    = trim((string)$appId);

        if ($apiLogin === '' || $secret === '' || $appId === '') {
            $this->addError(new Error('Все поля (apiLogin, secret, appId) обязательны для заполнения.'));
            return null;
        }

        // Сохраняем настройки (одной строкой на сайт)
        SettingsTable::save($siteId, $apiLogin, $secret, $appId);

        // Проверяем связь — обращаемся к классу авторизации и пробуем получить токен
        $auth = new Auth($siteId);
        $token = $auth->getToken();

        if ($token) {
            // Связь установлена
            SettingsTable::setStatus($siteId, 'OK');
            return [
                'success' => true,
                'status' => 'OK',
                'statusLabel' => 'Связь установлена',
            ];
        }

        // Токен не получен
        SettingsTable::setStatus($siteId, 'ERROR');
        return [
            'success' => false,
            'status' => 'ERROR',
            'statusLabel' => 'Связь не установлена — не удалось получить токен',
        ];
    }
}
