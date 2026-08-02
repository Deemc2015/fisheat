<?php
/**
 * Лёгкий endpoint прогресса синхронизации iiko.
 * Не грузит ядро Битрикс и не стартует сессию, поэтому не блокируется
 * параллельным запросом синхронизации (PHP-блокировка сессии).
 *
 * GET ?token=... -> {"percent": N}
 */

$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
$percent = 0;

if ($token !== '') {
    $file = $_SERVER['DOCUMENT_ROOT'] . '/upload/iiko_sync/' . md5($token) . '.txt';
    if (is_file($file)) {
        $percent = (int)file_get_contents($file);
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['percent' => $percent]);
