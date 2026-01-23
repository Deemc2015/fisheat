<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use \Bitrix\Main\Data\Cache;
use Bitrix\Main\Loader;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Engine\ActionFilter;

class CNotification extends \CBitrixComponent implements Controllerable
{
    protected $userId;

    public function executeComponent()
    {
        global $USER;


        if (!$USER->IsAuthorized()) {
            return false;
        }

        $this->userId = $USER->GetID();


        $this->arResult = $this->get($this->userId);


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

    protected function get($userId){

        $arrNotification = [];

        $rsUser = \CUser::GetList([], [],
            array(
                "ID" => $userId,
            ),
            array(
                "SELECT" => array(
                    "UF_SMS",
                    "UF_PUSH",
                    "UF_EMAIL"
                ),
            )
        );

        if($arUser = $rsUser->Fetch())
        {
            $arrNotification = [
                'EMAIL' => $arUser['UF_EMAIL'],
                'SMS' => $arUser['UF_SMS'],
                'PUSH' => $arUser['UF_PUSH']
            ];
        }

        return $arrNotification;

    }

    public function sendFormAction($dataForm){
        global $USER;
        $userId = $USER->GetID();

        if($userId > 0) {
            $user = new \CUser;
            $ufData = [
                'UF_EMAIL' => ($dataForm['email'] === 'true') ? '1' : '0',
                'UF_SMS' => ($dataForm['sms'] === 'true') ? '1' : '0',
                'UF_PUSH' => ($dataForm['push'] === 'true') ? '1' : '0'
            ];

            $resultUpdate = $user->Update($userId, $ufData);
            if($resultUpdate){
                echo '200';
            }

        }
    }




}
?>