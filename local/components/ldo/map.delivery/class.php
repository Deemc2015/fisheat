<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use \Bitrix\Main\Data\Cache;
use Bitrix\Main\Loader;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Engine\ActionFilter;
use Ldo\Rkeeper\Delivery;


class CDeliveryMap extends \CBitrixComponent implements Controllerable
{
    

    public function executeComponent()
    {
        $this->includeComponentTemplate();
    }

    public function configureActions()
    {
        return [
            'sendForm' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class
                ],
            ]
        ];
    }
    
    public function sendFormAction($post){

        $lat = $post['arParams']['lat'];
        $lon = $post['arParams']['lon'];

        $lat = htmlspecialchars($lat, ENT_QUOTES, 'UTF-8');
        $lon = htmlspecialchars($lon, ENT_QUOTES, 'UTF-8');

        if($lat && $lon){

            Loader::includeModule("ldo.rkeeper");

            $dataDelivery = new Delivery();

            $deliveryInfo = $dataDelivery->getPrice($lat,$lon);

            if($deliveryInfo){
                return $deliveryInfo;
            }

        }
    }

}
?>