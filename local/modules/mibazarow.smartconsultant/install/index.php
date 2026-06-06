<?php
/**
 * Установщик модуля mibazarow.smartconsultant
 * Семантический (эмбеддинг) поиск по товарам
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Application;

class mibazarow_smartconsultant extends CModule
{
    var $MODULE_ID = 'mibazarow.smartconsultant';
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME = 'AI Консультант (семантический поиск)';
    var $MODULE_DESCRIPTION = 'Семантический поиск по товарам на основе нейросети. Находит товары по смыслу, а не по точному совпадению слов. Требует Python 3.10+ на Linux-сервере.';
    var $PARTNER_NAME = 'Михаил Базаров';
    var $PARTNER_URI = 'https://bazarow.ru';

    function __construct()
    {
        $arModuleVersion = [];
        include(dirname(__FILE__) . '/version.php');

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
    }

    function InstallDB($arParams = [])
    {
        global $DB, $APPLICATION;

        $errors = $DB->RunSQLBatch(
            dirname(__FILE__) . '/db/mysql/install.sql'
        );

        if (!empty($errors)) {
            $APPLICATION->ThrowException(implode('<br>', $errors));
            return false;
        }

        return true;
    }

    function UnInstallDB($arParams = [])
    {
        global $DB;
        $DB->Query("DROP TABLE IF EXISTS mib_smartconsultant_embedding");
        return true;
    }

    function InstallFiles($arParams = [])
    {
        // Копирование компонента в /local/components/
        $componentSource = dirname(__FILE__) . '/../components/mibazarow/smartconsultant.search';
        $componentTarget = $_SERVER['DOCUMENT_ROOT'] . '/local/components/mibazarow/smartconsultant.search';

        if (is_dir($componentSource)) {
            CopyDirFiles($componentSource, $componentTarget, true, true);
        }

        // Копирование .settings.php в корень модуля (уже на месте, просто проверяем)
        return true;
    }

    function UnInstallFiles()
    {
        // Удаление компонента
        \Bitrix\Main\IO\Directory::deleteDirectory(
            $_SERVER['DOCUMENT_ROOT'] . '/local/components/mibazarow/smartconsultant.search'
        );
        return true;
    }

    function InstallAgents()
    {
        // Регистрация агента переиндексации (раз в сутки)
        \CAgent::AddAgent(
            'Mibazarow\\Smartconsultant\\Agent\\ReindexAgent::reindex();',
            $this->MODULE_ID,
            'N',        // не периодический? нет, периодический
            86400,      // интервал: 24 часа
            date('d.m.Y H:i:s'), // сегодня
            'Y',        // активен
            date('d.m.Y H:i:s'), // первое выполнение сегодня
            30          // приоритет
        );

        return true;
    }

    function UnInstallAgents()
    {
        \CAgent::RemoveModuleAgents($this->MODULE_ID);
        return true;
    }

    function DoInstall()
    {
        global $APPLICATION;

        // Проверка наличия Python-окружения
        $pythonPath = realpath(dirname(__FILE__) . '/../python/venv/bin/python');
        $pythonWarning = '';

        if (!$pythonPath || !is_executable($pythonPath)) {
            $pythonWarning = '<p style="color:#c00;font-weight:bold;">⚠ Python-окружение не найдено!</p>'
                . '<p>Создайте venv и установите зависимости (см. python/README.md):</p>'
                . '<pre>cd ' . dirname(__FILE__) . '/../python'
                . "\npython3 -m venv venv"
                . "\nvenv/bin/pip install -r requirements.txt</pre>";
        } else {
            // Проверка доступности HTTP-сервиса эмбеддингов
            try {
                $http = new \Bitrix\Main\Web\HttpClient();
                $response = $http->get('http://127.0.0.1:9876/health');
                if (!str_contains($response, '"status":"ok"')) {
                    $pythonWarning = '<p style="color:#e90;font-weight:bold;">⚠ HTTP-сервис эмбеддингов не запущен!</p>'
                        . '<p>Запустите сервис на сервере:</p>'
                        . '<pre>systemctl enable --now mib-smartconsultant</pre>'
                        . '<p>Без сервиса поиск не будет работать. Модуль установлен, но требует запуска сервиса.</p>';
                }
            } catch (\Throwable $e) {
                $pythonWarning = '<p style="color:#e90;font-weight:bold;">⚠ HTTP-сервис эмбеддингов недоступен (порт 9876).</p>'
                    . '<p>Убедитесь, что сервис запущен на Linux-сервере.</p>';
            }
        }

        // Установка БД
        if (!$this->InstallDB()) {
            $APPLICATION->ThrowException('Ошибка создания таблиц БД');
            return false;
        }

        // Установка файлов
        $this->InstallFiles();

        // Агент не используется — индексация запускается по cron через bin/reindex.php

        // Регистрация модуля
        RegisterModule($this->MODULE_ID);

        // Вывод информации
        $APPLICATION->IncludeAdminFile(
            'Установка модуля «AI Консультант»',
            dirname(__FILE__) . '/../install/install_message.php'
        );

        if ($pythonWarning) {
            echo $pythonWarning;
        }
    }

    function DoUninstall()
    {
        global $APPLICATION;

        // Удаление агентов
        $this->UnInstallAgents();

        // Удаление файлов
        $this->UnInstallFiles();

        // Удаление БД
        if ($_REQUEST['save_tables'] !== 'Y') {
            $this->UnInstallDB();
        }

        // Снятие регистрации модуля
        UnRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile(
            'Удаление модуля «AI Консультант»',
            dirname(__FILE__) . '/../install/uninstall_message.php'
        );
    }
}
