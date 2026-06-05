<?php
namespace Ldo\Iiko;

use Bitrix\Main\Web\HttpClient;

class Auth
{
    const AUTH_URL = 'https://api-ru.iiko.services/api/1/access_token';

    public function getToken($apiLogin) {

        if(!$apiLogin){
            return false;
        }

       $httpClient = new HttpClient();
        $httpClient->setHeader('Content-Type', 'application/x-www-form-urlencoded');

        $postData = [
            'apiLogin' => $apiLogin,
        ];
        $response = $httpClient->post(self::AUTH_URL, $postData);

        $result = json_decode($response, true);

        return $result;

        if (isset($result['token'])) {
            return $result['token'];
        }
    }

}


