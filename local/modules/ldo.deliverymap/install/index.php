<?
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\IO\Directory;

global $DOCUMENT_ROOT, $MESS;
Loc::loadMessages(__FILE__);

class Ldo_deliverymap extends CModule
{
    var $MODULE_ID = "ldo.deliverymap";
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

        $this->MODULE_NAME = Loc::getMessage("LDO_DELIVERYMAP_INSTALL_NAME") ?: "Зоны доставки на карте";
        $this->MODULE_DESCRIPTION = Loc::getMessage("LDO_DELIVERYMAP_INSTALL_DESCRIPTION") ?: "Визуальное управление зонами доставки";
        $this->PARTNER_NAME = Loc::getMessage('LDO_DELIVERYMAP_PARTNER') ?: "LDO";
        $this->PARTNER_URI = Loc::getMessage('LDO_DELIVERYMAP_PARTNER_URI') ?: "https://example.com";
    }

    public function DoInstall()
    {
        global $DB, $APPLICATION;

        $this->InstallFiles();
        $this->InstallDB();

        ModuleManager::registerModule($this->MODULE_ID);

        return true;
    }

    public function DoUninstall()
    {
        global $DB, $APPLICATION;

        $this->UnInstallDB();
        $this->UnInstallFiles();

        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }

    public function InstallDB()
    {
        global $DB;

        $this->createTables();
        $this->setOptions();
        $this->addEventHandlers();

        return true;
    }

    public function UnInstallDB()
    {
        global $DB;

        $this->dropTables();
        Option::delete($this->MODULE_ID);

        return true;
    }

    public function InstallFiles()
    {
        $modulePath = dirname(__DIR__);

        // Копируем папку в /bitrix/admin/
        CopyDirFiles(
            $modulePath . '/admin/ldo_deliverymap',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/ldo_deliverymap',
            true,
            true
        );

        // Копируем assets
        if (is_dir($modulePath . '/assets')) {
            CopyDirFiles(
                $modulePath . '/assets',
                $_SERVER['DOCUMENT_ROOT'] . '/bitrix/assets/' . $this->MODULE_ID,
                true,
                true
            );
        }

        return true;
    }

    public function UnInstallFiles()
    {
        DeleteDirFilesEx('/bitrix/admin/ldo_deliverymap');
        DeleteDirFilesEx('/bitrix/assets/' . $this->MODULE_ID);

        return true;
    }

    public function GetPath($notDocumentRoot = false)
    {
        if (defined('BX_PERSONAL_ROOT') && !$notDocumentRoot) {
            $path = BX_PERSONAL_ROOT . '/modules/' . $this->MODULE_ID;
        } else {
            $path = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID;

            if (!file_exists($path)) {
                $path = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/' . $this->MODULE_ID;
            }
        }

        return $path;
    }

    private function createTables()
    {
        global $DB;

        $sql = "
            CREATE TABLE IF NOT EXISTS `ldo_delivery_zones` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `NAME` varchar(255) NOT NULL,
                `PRICE` int(11) NOT NULL DEFAULT '0',
                `COLOR` varchar(7) NOT NULL DEFAULT '#00FF00',
                `COORDINATES` text NOT NULL,
                `SORT` int(11) NOT NULL DEFAULT '500',
                `MIN_ORDER_PRICE` int(11) NOT NULL DEFAULT '0',
                `FREE_DELIVERY_PRICE` int(11) NOT NULL DEFAULT '0',
                `DELIVERY_TIME` int(11) NOT NULL DEFAULT '0',
                `ACTIVE` char(1) NOT NULL DEFAULT 'Y',
                `SITE_ID` varchar(2) NOT NULL DEFAULT '',
                PRIMARY KEY (`ID`),
                KEY `IX_ACTIVE` (`ACTIVE`),
                KEY `IX_SITE_ID` (`SITE_ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";

        $DB->Query($sql);
    }

    private function dropTables()
    {
        global $DB;
        $DB->Query("DROP TABLE IF EXISTS `ldo_delivery_zones`");
    }

    private function setOptions()
    {
        Option::set($this->MODULE_ID, 'yandex_api_key', '');
        Option::set($this->MODULE_ID, 'default_lat', '55.751574');
        Option::set($this->MODULE_ID, 'default_lng', '37.573856');
        Option::set($this->MODULE_ID, 'default_zoom', '10');
    }

    private function addEventHandlers()
    {
        return true;
    }
}
?>