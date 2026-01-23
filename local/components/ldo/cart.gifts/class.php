<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use \Bitrix\Main\Data\Cache;
use Bitrix\Main\Loader;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Engine\ActionFilter;
use \Ldo\Develop\Basket;

Loader::includeModule("iblock");


class CartGifts extends \CBitrixComponent implements Controllerable
{
    protected $userId;
    private $min;
    private $max;

    public function executeComponent()
    {
        $this->arResult['PROGRESS'] = $this->getProgress();
        $this->arResult['TOTAL'] = $this->getTotalSum();
        $this->includeComponentTemplate();
    }



    public function configureActions()
    {
        return [
            'getSum' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class
                ],
            ]
        ];
    }


    public function getSumAction()
    {
       return $this->getProgress();

    }

    private function getTotalSum(){

        if(Loader::includeModule("ldo.develop")){
            $objDasket = new Basket();
            return $objDasket->getTotalSum();
        }
    }

    private function getProgress(){

        $totalSum = $this->getTotalSum();

        $max = $this->getMax();


        // Защита от некорректных значений
        if ($max <= 0) {
            return 0;
        }

        // Если сумма отрицательная - возвращаем 0
        if ($totalSum <= 0) {
            return 0;
        }

        // Ограничиваем прогресс 100%
        $percentage = min(100, ($totalSum / $max) * 100);

        // Округляем вниз или оставляем с одним знаком после запятой
        return floor($percentage); // Или round($percentage, 1) для десятичных;
    }

    private function getMax(){
        $arrSum = [];

        $obCatalog = \CIBlockElement::GetList(
            ["ID" => "ASC"],
            [
                "IBLOCK_ID" => $this->arParams['IBLOCK_ID'],
                "ACTIVE" => "Y",
                "!PROPERTY_ATT_SUM_CART" => false
            ],
            false,
            false,
            ['PROPERTY_ATT_SUM_CART']
        );

        while($arElement = $obCatalog->GetNext()){
            if(isset($arElement['PROPERTY_ATT_SUM_CART_VALUE'])){

                $value = (float)$arElement['PROPERTY_ATT_SUM_CART_VALUE'];

                if($value > 0){
                    $arrSum[] = $value;
                }
            }
        }

        $maxSum = null;

        if(!empty($arrSum)){
            $maxSum =  max($arrSum);
        }

        return $maxSum;
    }

}
?>