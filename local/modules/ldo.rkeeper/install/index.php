<?
use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Composite\Helper;

global $DOCUMENT_ROOT, $MESS;
Loc::loadMessages(__FILE__);

class Ldo_rkeeper extends CModule
{
    var $MODULE_ID = "ldo.rkeeper";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $PARTNER_URI;
    var $PARTNER_NAME;

    function __construct()
    {
        $arModuleVersion = array();

        $path = str_replace("\\", "/", __file__);
        $path = substr($path, 0, strlen($path) - strlen("/index.php"));
        include ($path . "/version.php");

        if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        }

        $this->MODULE_NAME = Loc::getMessage("LDO_RKEEPER_INSTALL_NAME");
        $this->MODULE_DESCRIPTION = Loc::getMessage("LDO_RKEEPER_INSTALL_DESCRIPTION");
        $this->PARTNER_NAME = Loc::getMessage('LDO_RKEEPER_PARTNER');
        $this->PARTNER_URI = Loc::getMessage('LDO_RKEEPER_PARTNER_URI');
    }

    public function DoInstall()
    {
        RegisterModule($this->MODULE_ID);
        //$this->RegisterModuleHandlers();
        $this->addAgents();
        //$this->InstallDB();
        //$this->InstallFiles();
       /* $this->InstallEvents();
        $this->registerModuleHandlers();
        $this->createHlBlockList();
        $this->createHlBlockBlackList();
        $this->createHlBlockGrayList();
        $this->createHlBlockReferers();
        $this->createHlBlockMasc();
        $this->createHlBlockConfig();
        $this->createHlBlockEmails();
        $this->createHlBlockForm();
        $this->addGroup();*/

        return true;
    }

    public function DoUninstall()
    {
        UnRegisterModule($this->MODULE_ID);
        //$this->unRegisterModuleHandlers();
        $this->removeAgents();
        //$this->UnInstallDB();
        //$this->UnInstallFiles();
        //$this->deleteHlBlock($this->name);
        //$this->deleteHlBlock('BlackIpList');
        //$this->deleteHlBlock('GrayIpList');
        //$this->deleteHlBlock('HttpReferer');
        //$this->deleteHlBlock('SubnetMasks');
        //$this->deleteHlBlock('OptionsForm');
        //$this->deleteHlBlock('OptionsEmail');
        //$this->deleteHlBlock('OptionsModule');
        //$this->UnInstallEvents();
        //$this->deleteGroup();
        return true;
    }

    public function InstallFiles()
    {
        /*CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/local/modules/prokhorov.b24/install/page/blackList",
            $_SERVER["DOCUMENT_ROOT"]."/black_page/", true, true);

        CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/local/modules/prokhorov.b24/install/page/personal_filter",
            $_SERVER["DOCUMENT_ROOT"]."/personal_filter/", true, true);

        CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/local/modules/prokhorov.b24/install/page/callForm",
            $_SERVER["DOCUMENT_ROOT"]."/callForm/", true, true);

        CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/local/modules/prokhorov.trafic/install/components/",
            $_SERVER["DOCUMENT_ROOT"]."/local/components/", true, true);*/

        return true;
    }
    
    
    protected function addAgents()
    {
        \CAgent::AddAgent( "\LDO\Rkeeper\Agents::updateCategory();", $this->MODULE_ID, "N", 60, "", "Y");
        \CAgent::AddAgent( "\LDO\Rkeeper\Agents::updateProducts();", $this->MODULE_ID, "N", 60, "", "Y");

    }

    protected function removeAgents()
    {
        \CAgent::RemoveModuleAgents($this->MODULE_ID);
    }


    protected function registerModuleHandlers(){
        $eventManager = EventManager::getInstance();
        $result = $eventManager->registerEventHandler("main", "OnProlog", $this->MODULE_ID, "\Ldo\Develop\Redirect", "checkRedirect");
        return true;
    }

    protected function unRegisterModuleHandlers()
    {
        $eventManager = EventManager::getInstance();
        $eventManager->unRegisterEventHandler("main", "OnProlog", $this->MODULE_ID, "\Ldo\Develop\Redirect", "checkRedirect");
    }



    public function UnInstallFiles()
    {
        DeleteDirFilesEx("/black_page");
        DeleteDirFilesEx("/personal_filter");
        DeleteDirFilesEx("/callForm");
        DeleteDirFilesEx("/local/components/bitrix");
        DeleteDirFilesEx("/local/components/ldo");
        return true;
    }

      
}
?>