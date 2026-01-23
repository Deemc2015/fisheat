<?php
namespace Ldo\Rkeeper;

use Bitrix\Main\Web\HttpClient;

class Auth
{
    const AUTH_URL = 'https://auth-delivery.ucs.ru/connect/token';
    private $clientId = '9e00314a-c0e4-4924-afeb-f79ccbbf2bae';
    private $clientSecret = '77747be8-a5d6-4b65-abdc-b2ff2213ac9b';

    public function getToken() {

       $httpClient = new HttpClient();
        $httpClient->setHeader('Content-Type', 'application/x-www-form-urlencoded');

        $postData = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials'
        ];
        $response = $httpClient->post(self::AUTH_URL, $postData);

        $result = json_decode($response, true);

        if (isset($result['access_token'])) {
            return $result['access_token'];
        }
    }

}


