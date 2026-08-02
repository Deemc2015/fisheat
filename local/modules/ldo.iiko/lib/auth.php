<?php
namespace Ldo\Iiko;

use Bitrix\Main\Web\HttpClient;

class Auth
{
    const AUTH_URL = 'https://api-ru.iiko.services/api/v2/access_token';

    private $apiLogin;

    private $secret;

    private $appId;

    /**
     * @param string $siteId ID сайта, для которого читаются настройки
     */
    public function __construct($siteId = 's1')
    {
        $row = SettingsTable::getRow($siteId);

        $this->apiLogin = $row['API_LOGIN'] ?? '';
        $this->secret   = $row['SECRET'] ?? '';
        $this->appId    = $row['APP_ID'] ?? '';
    }

    public function getToken() {


        if ($this->apiLogin === '' || $this->secret === '' || $this->appId === '') {
            return null;
        }

        $httpClient = new HttpClient();

        $httpClient->setHeader('Content-Type', 'application/json');

        $response = $httpClient->post(
            self::AUTH_URL,
            json_encode([
                'apiKey' => $this->apiLogin,
                'appId' => $this->appId,
                'clientSecret' => $this->secret
            ])
        );

        if ($response === false) {
            addMessage2Log('Auth::getToken: HTTP request failed. Errors: ' . print_r($httpClient->getError(), true));
            return null;
        }

        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            addMessage2Log('Auth::getToken: JSON decode error: ' . json_last_error_msg() . '. Raw response: ' . $response);
            return null;
        }

        if (!empty($result['errorDescription'])){
            addMessage2Log('Auth::getToken: API error: ' . $result['errorDescription']);
            return null;
        }

        if(!empty($result['token'])){
            return $result['token'];
        }

        addMessage2Log('Auth::getToken: token not found in response. Response: ' . print_r($result, true));
        return null;

    }

}


