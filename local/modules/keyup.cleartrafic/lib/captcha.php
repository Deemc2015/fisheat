<?php
namespace Keyup\Cleartrafic;

/**
 * Класс для серверной проверки Яндекс SmartCaptcha.
 * Секретный ключ хранится в настройках модуля (Settings, опция "captcha_secret").
 */
class Captcha {

    /**
     * Проверка токена Яндекс SmartCaptcha на сервере.
     *
     * @param string      $token Токен, полученный с клиента (smart-token)
     * @param string|null $ip    IP пользователя (по умолчанию берётся REMOTE_ADDR)
     *
     * @return bool true - каптча пройдена, false - нет
     */
    public static function check($token, $ip = null)
    {
        $token = trim((string)$token);

        if ($token === '')
        {
            return false;
        }

        $secretKey = Settings::getCaptchaSecret();

        if ($secretKey === '')
        {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'KEYUP_CLEARTRAFIC_CAPTCHA',
                'MODULE_ID' => 'keyup.cleartrafic',
                'DESCRIPTION' => 'Не задан секретный ключ Яндекс SmartCaptcha в настройках модуля',
            ]);
            return false;
        }

        if (!$ip)
        {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Параметры проверки токена
        $data = [
            'secret' => $secretKey,
            'token'  => $token,
            'ip'     => $ip,
        ];

        // Настройки cURL
        $options = [
            CURLOPT_URL            => 'https://smartcaptcha.yandexcloud.net/validate',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_TIMEOUT        => 5,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $options);

        // Выполнение запроса cURL
        $response = curl_exec($ch);
        $curlError = curl_error($ch);

        // Закрытие соединения cURL
        curl_close($ch);

        if ($curlError !== '')
        {
            return false;
        }

        // Обработка ответа
        $responseKeys = json_decode($response, true);

        if (is_array($responseKeys) && isset($responseKeys['status']))
        {
            return $responseKeys['status'] === 'ok';
        }

        return false;
    }

}
