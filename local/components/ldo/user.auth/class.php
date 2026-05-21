<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use \Bitrix\Main\Data\Cache;
use Bitrix\Main\Loader;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Engine\ActionFilter;

class CUserAuth extends \CBitrixComponent implements Controllerable
{


    public function executeComponent()
    {
        $this->arResult['NOT_AUTH_USER'] = $this->isAuth();

        $this->includeComponentTemplate();
    }

    private function isAuth(){

        global $USER;

        $status = $USER->IsAuthorized();

        if(!$status){
            return true;
        }
    }

    public function configureActions()
    {
        return [
            'sendForm' => [
                '-prefilters' => [

                ],
            ]
        ];
    }


    public function sendFormAction($dataForm){
        return $dataForm;
    }
}
?>