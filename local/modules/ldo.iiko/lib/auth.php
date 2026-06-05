<?php
namespace Ldo\Iiko;

use Bitrix\Main\Web\HttpClient;

class Auth
{
    const AUTH_URL = 'https://api-ru.iiko.services/api/1/access_token';
    private $apiLogin = 'be29f89bc57145f8b9ffdd944d7d2136';

    public function getToken() {

        $httpClient = new HttpClient();

        $httpClient->setHeader('Content-Type', 'application/json');

        $response = $httpClient->post(
            self::AUTH_URL,
            json_encode(['apiLogin' => $this->apiLogin])
        );

        $result = json_decode($response, true);

        if($result['errorDescription']){
            return [
                'status' => 'error',
                'message' => $result['errorDescription']
            ];
        }

        if($result['token']){
            return $result['token'];
        }


    }

}


