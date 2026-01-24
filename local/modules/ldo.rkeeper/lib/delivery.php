<?php
namespace Ldo\Rkeeper;
use Ldo\Rkeeper\Auth;
use Bitrix\Main\Web\HttpClient;

class Delivery
{

    public function getPrice($lat,$lon) {

        $url = 'https://delivery.ucs.ru/orders/api/v1/restaurants/geo/first?' . http_build_query([
                'lat' => $lat,
                'lon' => $lon
            ]);

        $tokenData = new Auth();
        $token = $tokenData->getToken();
        if($token){
            $httpClient = new HttpClient();
            $httpClient->setHeader('Authorization', 'Bearer ' . $token);
            $response = $httpClient->get($url);
            $result = json_decode($response, true);

            if($result['code'] == 'restaurant_not_found'){
                $resultData['errors'] = 'Адрес не попадает в зону доставки';
                return $resultData;
            }

            $resultData = [
                'restaurant_id' => $result['result']['restaurantId'],
                'restaurant_name' => $result['result']['restaurantName'],
                'price' => $result['result']['amountDelivery']['price'],
                'deliveryTime' => $result['result']['deliveryTime'],
                'minPrice' => $result['result']['minOrderAmountDelivery'],
                'priceFreeDelivery' => $result['result']['minOrderAmountFreeDelivery'],
            ];

            return $resultData;
        }
    }

}


