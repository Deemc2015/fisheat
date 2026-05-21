<?php
echo "<pre>";

// Тест 1: file_get_contents
echo "1. file_get_contents test:\n";
$result = @file_get_contents('https://api.new-tel.net');
echo $result === false ? "FAILED\n" : "SUCCESS\n";
$error = error_get_last();
print_r($error);

// Тест 2: CURL test
echo "\n2. CURL test:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.new-tel.net');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
$error = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "Result: " . ($result === false ? "FAILED\n" : "SUCCESS\n");
echo "Error: $error\n";
print_r($info);

// Тест 3: fsockopen
echo "\n3. fsockopen test:\n";
$fp = @fsockopen('ssl://api.new-tel.net', 443, $errno, $errstr, 10);
if ($fp) {
    echo "SUCCESS - Connection established\n";
    fclose($fp);
} else {
    echo "FAILED - $errno: $errstr\n";
}

// Тест 4: Проверка disabled функций
echo "\n4. Disabled functions:\n";
$disabled = ini_get('disable_functions');
echo $disabled ?: "none\n";

echo "\n5. open_basedir:\n";
echo ini_get('open_basedir') ?: "none\n";

echo "\n6. allow_url_fopen:\n";
echo ini_get('allow_url_fopen') ? "On\n" : "Off\n";