<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\EventManager;
use Keyup\Cleartrafic\Installer;

Loc::loadMessages(__FILE__);

class Keyup_cleartrafic extends CModule
{
    var $MODULE_ID = "keyup.cleartrafic";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $PARTNER_URI;
    var $PARTNER_NAME;

    public $errors;

    public function __construct()
    {
        $arModuleVersion = array();

        $path = str_replace("\\", "/", __file__);
        $path = substr($path, 0, strlen($path) - strlen("/index.php"));
        include($path . "/version.php");

        if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        }

        $this->MODULE_NAME = Loc::getMessage("KEYUP_CLEARTRAFIC_BOT_INSTALL_NAME");
        $this->MODULE_DESCRIPTION = Loc::getMessage("KEYUP_CLEARTRAFIC_BOT_INSTALL_DESCRIPTION");
        $this->PARTNER_NAME = Loc::getMessage('KEYUP_CLEARTRAFIC_BOT_PARTNER');
        $this->PARTNER_URI = Loc::getMessage('KEYUP_CLEARTRAFIC_BOT_PARTNER_URI');
    }

    public function DoInstall()
    {
        RegisterModule($this->MODULE_ID);
        $this->InstallFiles();
        $this->InstallEvents();
        $this->registerModuleHandlers();

        Loader::includeModule($this->MODULE_ID);
        Installer::create();

        $this->addGroup();

        return true;
    }

    /**
     * Удаление модуля в два шага: сначала вопрос о сохранении таблиц,
     * затем снятие регистрации, удаление файлов и (при необходимости) таблиц.
     *
     * @return bool
     */
    public function DoUninstall()
    {
        global $APPLICATION;

        if (!check_bitrix_sessid()) {
            return false;
        }

        $step = (int)($_REQUEST['step'] ?? 1);

        /* Шаг 1: выбор — сохранять ли данные модуля */
        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(
                $this->MODULE_NAME,
                __DIR__ . '/uninstall_step1.php'
            );

            return true;
        }

        /* Шаг 2: удаление */
        $this->UnInstallDB([
            'savedata' => $_REQUEST['savedata'] ?? 'N',
        ]);

        $this->unRegisterModuleHandlers();
        $this->UnInstallEvents();
        $this->UnInstallFiles();
        $this->deleteGroup();

        UnRegisterModule($this->MODULE_ID);

        $GLOBALS['KEYUP_CLEARTRAFIC_UNINSTALL_SAVEDATA'] = $_REQUEST['savedata'] ?? 'N';

        $APPLICATION->IncludeAdminFile(
            $this->MODULE_NAME,
            __DIR__ . '/uninstall_step2.php'
        );

        return true;
    }

    /**
     * Удаление данных модуля.
     *
     * @param array $params Параметры удаления, ключ savedata: 'Y' — таблицы сохраняются
     * @return bool
     */
    public function UnInstallDB(array $params = [])
    {
        if (($params['savedata'] ?? 'N') === 'Y') {
            /* Администратор решил сохранить таблицы базы данных модуля */
            return true;
        }

        Loader::includeModule($this->MODULE_ID);
        Installer::drop();

        return true;
    }

    public function InstallFiles()
    {
        CopyDirFiles($_SERVER["DOCUMENT_ROOT"] . "/local/modules/keyup.cleartrafic/install/page/blackList",
            $_SERVER["DOCUMENT_ROOT"] . "/black_page/", true, true);

        CopyDirFiles($_SERVER["DOCUMENT_ROOT"] . "/local/modules/keyup.cleartrafic/install/page/personal_filter",
            $_SERVER["DOCUMENT_ROOT"] . "/personal_filter/", true, true);

        CopyDirFiles($_SERVER["DOCUMENT_ROOT"] . "/local/modules/keyup.cleartrafic/install/page/callForm",
            $_SERVER["DOCUMENT_ROOT"] . "/callForm/", true, true);

        CopyDirFiles($_SERVER["DOCUMENT_ROOT"] . "/local/modules/keyup.cleartrafic/install/components/",
            $_SERVER["DOCUMENT_ROOT"] . "/local/components/", true, true);

        return true;
    }

    // возвращает список типов и почтовых шаблонов по умолчанию
    function __GetEventTypes()
    {
        return array(
            'KEYUP_CLEARTRAFIC_RECEIVE' => Array(
                Array(
                    "SUBJECT" => GetMessage('KEYUP_CLEARTRAFIC_RECEIVE_SUBJECT1'),
                    "MESSAGE" => GetMessage('KEYUP_CLEARTRAFIC_RECEIVE_MESSAGE1'),
                )
            ),
        );
    }

    // создание/обновление типов и шаблонов почтовых сообщений
    public function __InstallEvents()
    {
        global $APPLICATION;

        // список всех сайтов, сгруппированный по языкам
        $arSites = array();
        $rsSites = CSite::GetList($by, $order);
        while ($arSite = $rsSites->Fetch()) {
            if (!in_array($arSite["LANGUAGE_ID"], Array('ru', 'ua'))) {
                continue;
            }
            $arSites[$arSite["LANGUAGE_ID"]][] = $arSite["LID"];
        }

        // создание типов почтовых событий и почтовых шаблонов по-умолчанию для всех языков
        $rsLanguages = CLanguage::GetList($b = "", $o = "");
        $obEventType = new CEventType();
        $obEventMessage = new CEventMessage();
        while ($arLang = $rsLanguages->Fetch()) {
            if (!in_array($arLang["LID"], Array('ru', 'ua'))) {
                continue;
            }

            // подключение языковых сообщений для нужного языка
            IncludeModuleLangFile(dirname(__FILE__) . '/events.php', $arLang["LID"]);
            $arEventTypes = self::__GetEventTypes();

            foreach ($arEventTypes as $strEventName => $arEventTemplates) {
                $arEventTypeFields = Array(
                    "LID" => $arLang["LID"],
                    "EVENT_NAME" => $strEventName,
                    "NAME" => GetMessage($strEventName . '_TITLE'),
                    "DESCRIPTION" => GetMessage($strEventName . '_TEXT'),
                );
                $arEventType = CEventType::GetList(Array("EVENT_NAME" => $strEventName, 'LID' => $arLang['LID']))->Fetch();
                if (is_array($arEventType)) {
                    $bSuccess = $obEventType->Update(Array("ID" => $arEventType["ID"]), $arEventTypeFields);
                } else {
                    $bSuccess = $obEventType->Add($arEventTypeFields) > 0;
                    if ($bSuccess) {
                        // создание/обновление почтовых шаблонов для всех сайтов этого языка
                        if (array_key_exists($arLang["LID"], $arSites) && count($arSites[$arLang["LID"]]) > 0) {
                            foreach ($arEventTemplates as $arTemplate) {
                                $arTemplate['EVENT_NAME'] = $strEventName;
                                $arTemplate['LID'] = $arSites[$arLang["LID"]];

                                if (!array_key_exists('EMAIL_FROM', $arTemplate)) {
                                    $arTemplate['EMAIL_FROM'] = '#DEFAULT_EMAIL_FROM#';
                                }
                                if (!array_key_exists('EMAIL_TO', $arTemplate)) {
                                    $arTemplate['EMAIL_TO'] = '#EMAIL_TO#';
                                }
                                if (!array_key_exists('BODY_TYPE', $arTemplate)) {
                                    $arTemplate['BODY_TYPE'] = 'text';
                                }
                                if (!array_key_exists('ACTIVE', $arTemplate)) {
                                    $arTemplate['ACTIVE'] = 'Y';
                                }

                                $bSuccess = $obEventMessage->Add($arTemplate) > 0;

                                if (!$bSuccess) {
                                    return false;
                                }
                            }
                        }
                    }
                }

                if (!$bSuccess) {
                    return false;
                }
            }
        }

        return true;
    }

    public function InstallEvents()
    {
        return self::__InstallEvents();
    }

    protected function registerModuleHandlers()
    {
        $eventManager = EventManager::getInstance();
        $eventManager->registerEventHandler("main", "OnPageStart", $this->MODULE_ID, "\Keyup\Cleartrafic\Handlers", "handlerInfoIp");

        /* Композитный кэш отключается раньше, чем ядро решит отдать страницу из кэша */
        $eventManager->registerEventHandler("main", "OnBeforeProlog", $this->MODULE_ID, "\Keyup\Cleartrafic\Handlers", "handlerBeforeProlog");

        /* Разметка окна капчи добавляется в готовый HTML страницы */
        $eventManager->registerEventHandler("main", "OnEndBufferContent", $this->MODULE_ID, "\Keyup\Cleartrafic\Handlers", "handlerEndBuffer");

        return true;
    }

    protected function unRegisterModuleHandlers()
    {
        $eventManager = EventManager::getInstance();
        $eventManager->unRegisterEventHandler("main", "OnPageStart", $this->MODULE_ID, "\Keyup\Cleartrafic\Handlers", "handlerInfoIp");
        $eventManager->unRegisterEventHandler("main", "OnBeforeProlog", $this->MODULE_ID, "\Keyup\Cleartrafic\Handlers", "handlerBeforeProlog");
        $eventManager->unRegisterEventHandler("main", "OnEndBufferContent", $this->MODULE_ID, "\Keyup\Cleartrafic\Handlers", "handlerEndBuffer");
    }

    public function UnInstallFiles()
    {
        DeleteDirFilesEx("/black_page");
        DeleteDirFilesEx("/personal_filter");
        DeleteDirFilesEx("/callForm");
        /*Удаляем только свои компоненты, не трогая чужие*/
        DeleteDirFilesEx("/local/components/keyup/cleartrafic.list");
        DeleteDirFilesEx("/local/components/bitrix/highloadblock.list");
        DeleteDirFilesEx("/local/components/ldo/checkCaptcha");
        return true;
    }

    public function addGroup()
    {
        $group = new \CGroup;
        $arFields = [
            "ACTIVE" => "Y",
            "C_SORT" => 100,
            "NAME" => "Администратор модуля фильтрации",
            "DESCRIPTION" => "Предоставляет доступ к настройкам модуля фильтрации",
            "USER_ID" => [],
            "STRING_ID" => "admin_bots"
        ];

        $group->Add($arFields);
    }

    public function deleteGroup()
    {
        $id = 0;
        $rsGroups = \CGroup::GetList($by = "c_sort", $order = "asc", Array("STRING_ID" => 'admin_bots'));
        if ($data = $rsGroups->Fetch()) {
            if ($data['ID']) {
                $id = $data['ID'];
            }
        }

        if ($id) {
            $group = new \CGroup;
            $group->Delete($id);
        }
    }
}
