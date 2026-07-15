<?
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\IO\Directory;
use Ldo\Deliverymap\SettingsTable;
use Ldo\Deliverymap\RestaurantsTable;

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
        $this->PARTNER_URI = Loc::getMessage('LDO_DELIVERYMAP_PARTNER_URI') ?: "https://key-up.ru";
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
        $docRoot = $_SERVER['DOCUMENT_ROOT'];
        $adminSource = $modulePath . '/admin/ldo_deliverymap';

        // Удаляем старые файлы (и подпапку, и корневые)
        DeleteDirFilesEx('/bitrix/admin/ldo_deliverymap');
        foreach (['zones.php', 'zones.js', 'zones.css'] as $file) {
            $path = '/bitrix/admin/ldo_deliverymap_' . $file;
            if (file_exists($docRoot . $path)) {
                unlink($docRoot . $path);
            }
        }

        // Копируем файлы с префиксом на уровень /bitrix/admin/ (НЕ в подпапку)
        // Это критически важно для корректной работы админ-ссылок Битрикса
        if (is_dir($adminSource)) {
            $files = scandir($adminSource);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $sourcePath = $adminSource . '/' . $file;
                $targetName = 'ldo_deliverymap_' . $file;
                $targetPath = $docRoot . '/bitrix/admin/' . $targetName;
                copy($sourcePath, $targetPath);
            }
        }

        // Копируем assets
        if (is_dir($modulePath . '/assets')) {
            DeleteDirFilesEx('/bitrix/assets/' . $this->MODULE_ID);
            CopyDirFiles(
                $modulePath . '/assets',
                $docRoot . '/bitrix/assets/' . $this->MODULE_ID,
                true,
                true
            );
        }

        return true;
    }

    public function UnInstallFiles()
    {
        $docRoot = $_SERVER['DOCUMENT_ROOT'];

        // Удаляем файлы с префиксом
        foreach (['zones.php', 'zones.js', 'zones.css'] as $file) {
            $path = '/bitrix/admin/ldo_deliverymap_' . $file;
            if (file_exists($docRoot . $path)) {
                unlink($docRoot . $path);
            }
        }

        // На всякий случай удаляем и старую подпапку
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

        $DB->Query("
            CREATE TABLE IF NOT EXISTS `ldo_delivery_zones` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `NAME` varchar(255) NOT NULL,
                `PRICE` int(11) NOT NULL DEFAULT '0',
                `COLOR` varchar(7) NOT NULL DEFAULT '#00FF00',
                `COORDINATES` text NOT NULL,
                `SORT` int(11) NOT NULL DEFAULT '500',
                `MIN_ORDER_PRICE` int(11) NOT NULL DEFAULT '0',
                `FREE_DELIVERY_PRICE` int(11) NOT NULL DEFAULT '0',
                `DELIVERY_TIME_START` int(11) NOT NULL DEFAULT '0',
                `DELIVERY_TIME_END` int(11) NOT NULL DEFAULT '0',
                `HIGH_TYPE` int(11) NOT NULL DEFAULT '0',
                `ACTIVE` char(1) NOT NULL DEFAULT 'Y',
                `SITE_ID` varchar(2) NOT NULL DEFAULT '',
                `RESTAURANT_ID` int(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`ID`),
                KEY `IX_ACTIVE` (`ACTIVE`),
                KEY `IX_SITE_ID` (`SITE_ID`),
                KEY `IX_RESTAURANT_ID` (`RESTAURANT_ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $DB->Query("
            CREATE TABLE IF NOT EXISTS `ldo_delivery_settings` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `SITE_ID` varchar(2) NOT NULL,
                `NAME` varchar(50) NOT NULL,
                `VALUE` text,
                PRIMARY KEY (`ID`),
                UNIQUE KEY `IX_SITE_NAME` (`SITE_ID`, `NAME`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $DB->Query("
            CREATE TABLE IF NOT EXISTS `ldo_delivery_restaurants` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `ACTIVE` char(1) NOT NULL DEFAULT 'Y',
                `NAME` varchar(255) NOT NULL,
                `COORDINATES` text NOT NULL,
                `SITE_ID` varchar(2) NOT NULL,
                `PHONE` varchar(50) NOT NULL DEFAULT '',
                `EMAIL` varchar(100) NOT NULL DEFAULT '',
                `REQUISITES` text,
                `XML_ID` varchar(255) NOT NULL DEFAULT '',
                PRIMARY KEY (`ID`),
                KEY `IX_ACTIVE` (`ACTIVE`),
                KEY `IX_SITE_ID` (`SITE_ID`),
                KEY `IX_XML_ID` (`XML_ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    private function dropTables()
    {
        global $DB;
        $DB->Query("DROP TABLE IF EXISTS `ldo_delivery_zones`");
        $DB->Query("DROP TABLE IF EXISTS `ldo_delivery_settings`");
        $DB->Query("DROP TABLE IF EXISTS `ldo_delivery_restaurants`");
    }

    private function setOptions()
    {
        // Миграция из Option в БД (таблица ldo_delivery_settings)
        $optionKeys = ['yandex_api_key', 'default_lat', 'default_lng', 'default_zoom'];
        $siteIds = ['s1']; // при необходимости расширить список сайтов

        foreach ($siteIds as $siteId) {
            foreach ($optionKeys as $key) {
                $oldValue = Option::get($this->MODULE_ID, $key, null);
                if ($oldValue !== null) {
                    SettingsTable::set($siteId, $key, $oldValue);
                    Option::delete($this->MODULE_ID, $key);
                }
            }
        }
    }

    private function addEventHandlers()
    {
        return true;
    }
}
?>