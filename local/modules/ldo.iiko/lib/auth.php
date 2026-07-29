<?php
namespace Ldo\Iiko;

use Bitrix\Main\Web\HttpClient;

class Auth
{
    const AUTH_URL = 'https://api-ru.iiko.services/api/v2/access_token';
    private $apiLogin = '1629c4e76ce643568091465ff902cb4e';

    private $secret = 'Nscw0U-2au7YnHATgdA7UAnR_xnCW7Eg1rrzEz9Q4es=';

    private $appId = '0a7af56f-40db-4fba-9aa9-fffe383d232b';

    public function getToken() {

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


