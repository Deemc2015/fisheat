<?php


use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);



Class Ldo_favorites extends CModule
{
    var $MODULE_ID = 'ldo.favorites';
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $PARTNER_NAME;
    var $PARTNER_URI;
    var $MODULE_GROUP_RIGHTS = 'N';

    function __construct()
    {
        $arModuleVersion = array();
        include(dirname(__FILE__) . '/version.php');

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('SF_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('SF_DESCRIPTION');

        $this->PARTNER_NAME = Loc::getMessage('SF_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('SF_PARTNER_URI');
    }

    function DoInstall()
    {
        RegisterModule($this->MODULE_ID);
        return true;
    }

    function DoUninstall()
    {
        UnRegisterModule($this->MODULE_ID);
        return true;
    }
}
