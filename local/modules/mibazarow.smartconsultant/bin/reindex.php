#!/usr/bin/env php
<?php
/**
 * CLI-скрипт переиндексации товаров.
 * Запускается по cron раз в сутки.
 *
 * Использование:
 *   php local/modules/mibazarow.smartconsultant/bin/reindex.php
 *
 * Cron (каждый день в 3:00):
 *   0 3 * * * php /home/bitrix/ext_www/cons.camouf.ru/local/modules/mibazarow.smartconsultant/bin/reindex.php
 */

// Защита от запуска через браузер
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script runs only from command line.');
}

// Обязательные константы для CLI-запуска Bitrix (без них prolog зависает)
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('STOP_STATISTICS', true);
define('BX_CRONTAB', true);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 4);

if (!defined('SITE_ID')) {
    define('SITE_ID', 's1');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

// Отключаем буферизацию, чтобы видеть вывод сразу
while (ob_get_level()) {
    ob_end_flush();
}

echo date('Y-m-d H:i:s') . " Bitrix loaded OK\n";

if (!\Bitrix\Main\Loader::includeModule('mibazarow.smartconsultant')) {
    echo "ERROR: Module mibazarow.smartconsultant not installed\n";
    exit(1);
}

$t0 = microtime(true);
echo date('Y-m-d H:i:s') . " Starting reindex...\n";

try {
    $pipeline = new \Mibazarow\Smartconsultant\Index\Pipeline();
    $pipeline->run();

    $repo = new \Mibazarow\Smartconsultant\Embedding\Repository();
    $elapsed = round(microtime(true) - $t0, 1);

    echo date('Y-m-d H:i:s') . " DONE: {$repo->count()} items indexed in {$elapsed}s\n";
} catch (\Throwable $e) {
    echo date('Y-m-d H:i:s') . " ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
