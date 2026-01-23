<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use \Bitrix\Main\Data\Cache;
use Bitrix\Main\Loader;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Engine\ActionFilter;

class CProfile extends \CBitrixComponent implements Controllerable
{
    protected $userId;

    public function executeComponent()
    {
        global $USER;


        if (!$USER->IsAuthorized()) {
            return false;
        }

        $this->userId = $USER->GetID();


        $cache = Cache::createInstance();

        if(isset($_GET['clear_cache']) && $_GET['clear_cache'] == 'Y'){
            $cache->clean('ldo_profile_'.$this->userId);
        }

        if ($cache->initCache(3600, 'ldo_profile_'.$this->userId)) {

            $this->arResult = $cache->getVars();

        }
        elseif ($cache->startDataCache()) {

            $this->arResult = $this->getUserData($this->userId);

            $cache->endDataCache($this->arResult); // записываем в кеш

        }

        $this->includeComponentTemplate();
    }



    public function configureActions()
    {
        return [
            'sendForm' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class
                ],
            ],
            'deleteUser' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class
                ],
            ]
        ];
    }

    private function getUserData($userID)
    {
        $user = CUser::GetByID($userID)->Fetch();

        if (!$user) {
            return false;
        }
        return array(
            'EMAIL' => $user['EMAIL'],
            'NAME' => $user['NAME'],
            'PHONE' => $user['PERSONAL_PHONE'],
            'BIRTHDAY' => $user['PERSONAL_BIRTHDAY']

        );
    }

    public function sendFormAction($post)
    {
        //$this->arParams = $post['arParams'];

        return $post;

    }

    public function deleteUserAction($delete){
        if($delete == 'Y'){

        }
    }

    private function delete(){

    }





}
?>