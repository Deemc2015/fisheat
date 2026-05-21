<?php

$token = '0a2532ebe145039a1f9356451746a0139a2adc979c5f51c1ba6e4877450940ba';
$phone = '79177695923'; // Ваш тестовый номер

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.call-password.ru/api/v1.0/start-call-password/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['dn' => $phone, 'timeout' => 30]));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/x-www-form-urlencoded'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Response: " . $response . "\n";
echo "Error: " . $error . "\n";