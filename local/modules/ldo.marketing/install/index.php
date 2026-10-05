<?
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

global $DOCUMENT_ROOT, $MESS;
Loc::loadMessages(__FILE__);

class Ldo_marketing extends CModule
{
    var $MODULE_ID = "ldo.marketing";
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

        $this->MODULE_NAME = Loc::getMessage("LDO_MARKETING_INSTALL_NAME") ?: "Маркетинг";
        $this->MODULE_DESCRIPTION = Loc::getMessage("LDO_MARKETING_INSTALL_DESCRIPTION") ?: "Маркетинговые механики: акции, подарки и бесплатные позиции к товарам";
        $this->PARTNER_NAME = Loc::getMessage('LDO_MARKETING_PARTNER') ?: "LDO";
        $this->PARTNER_URI = Loc::getMessage('LDO_MARKETING_PARTNER_URI') ?: "https://key-up.ru";
    }

    public function DoInstall()
    {
        global $DB, $APPLICATION;

        $this->InstallDB();

        ModuleManager::registerModule($this->MODULE_ID);

        return true;
    }

    public function DoUninstall()
    {
        global $DB, $APPLICATION;

        $this->UnInstallDB();

        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }

    /**
     * Установка БД: создание таблиц модуля.
     *
     * @return bool
     */
    public function InstallDB()
    {
        $this->createTables();

        return true;
    }

    /**
     * Удаление БД модуля.
     *
     * @return bool
     */
    public function UnInstallDB()
    {
        $this->dropTables();

        return true;
    }

    /**
     * Создание таблиц модуля:
     * - ldo_marketing_free_positions — бесплатные позиции к товарам;
     * - ldo_marketing_gift_levels — подарки к заказам (уровни).
     *
     * @return void
     */
    private function createTables()
    {
        global $DB;

        $DB->Query("
            CREATE TABLE IF NOT EXISTS `ldo_marketing_free_positions` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `NAME` varchar(255) NOT NULL,
                `SECTION_IDS` text,
                `PRODUCT_IDS` text,
                `PORTIONS` int(11) NOT NULL DEFAULT '1',
                `SITE_ID` varchar(2) NOT NULL DEFAULT 's1',
                PRIMARY KEY (`ID`),
                KEY `IX_SITE_ID` (`SITE_ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $DB->Query("
            CREATE TABLE IF NOT EXISTS `ldo_marketing_gift_levels` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `ACTIVE` char(1) NOT NULL DEFAULT 'Y',
                `NAME` varchar(255) NOT NULL,
                `SUM` int(11) NOT NULL DEFAULT '0',
                `PRODUCT_IDS` text,
                `SORT` int(11) NOT NULL DEFAULT '500',
                `SITE_ID` varchar(2) NOT NULL DEFAULT 's1',
                PRIMARY KEY (`ID`),
                KEY `IX_SITE_ID` (`SITE_ID`),
                KEY `IX_ACTIVE` (`ACTIVE`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /**
     * Удаление таблиц модуля.
     *
     * @return void
     */
    private function dropTables()
    {
        global $DB;
        $DB->Query("DROP TABLE IF EXISTS `ldo_marketing_free_positions`");
        $DB->Query("DROP TABLE IF EXISTS `ldo_marketing_gift_levels`");
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
}
?>
