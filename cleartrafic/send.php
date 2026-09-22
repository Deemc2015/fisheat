<?php

/**
 * Обработчик заявок с лендинга модуля фильтрации трафика.
 *
 * Что делает:
 *  - отклоняет ботов (скрытое поле-ловушка и лимит частоты по IP);
 *  - проверяет обязательные поля;
 *  - сохраняет заявку в CSV, чтобы она не потерялась при сбое почты;
 *  - отправляет письмо получателям из config.php.
 *
 * Ответ всегда в формате JSON.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$config = require __DIR__ . '/config.php';

$leads   = $config['leads'];
$form    = $config['form'];
$subject = (string)$config['mail_subject'];
$to      = trim((string)$config['contacts']['email']);

/**
 * @param array $payload
 * @param int   $code
 *
 * @return void
 */
$respond = static function (array $payload, int $code = 200) use ($form): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $respond(['status' => 'error', 'message' => 'Метод не поддерживается'], 405);
}

/* ---------- Ловушка для ботов ---------- */
/* Поле скрыто стилями: человек его не видит и не заполняет */
if (trim((string)($_POST['company'] ?? '')) !== '') {
    /* Отвечаем «успехом», чтобы бот не подбирал обход */
    $respond(['status' => 'success', 'message' => $form['success']]);
}

/**
 * IP посетителя с учётом прокси.
 *
 * @return string
 */
$getIp = static function (): string {
    $candidates = [];

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $candidates = array_map('trim', explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']));
    }

    $candidates[] = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $candidates[] = (string)($_SERVER['HTTP_X_REAL_IP'] ?? '');

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
            return $candidate;
        }
    }

    return '0.0.0.0';
};

$ip = $getIp();

/**
 * Обрезает значение до допустимой длины и убирает управляющие символы.
 *
 * @param mixed $value
 * @param int   $max
 *
 * @return string
 */
$clean = static function ($value, int $max): string {
    $value = str_replace(["\r", "\n", "\0"], ' ', (string)$value);
    $value = trim(strip_tags($value));

    if (mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }

    return $value;
};

$maxLength = (int)($leads['max_length'] ?? 2000);

$name    = $clean($_POST['name'] ?? '', 120);
$email   = $clean($_POST['email'] ?? '', 120);
$phone   = $clean($_POST['phone'] ?? '', 60);
$site    = $clean($_POST['site'] ?? '', 160);
$plan    = $clean($_POST['plan'] ?? '', 120);
$message = $clean($_POST['message'] ?? '', $maxLength);
$consent = (string)($_POST['consent'] ?? '');

/* ---------- Валидация ---------- */
$errors = [];

if (mb_strlen($name) < 2) {
    $errors['name'] = 'Укажите, как к вам обращаться';
}

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors['email'] = 'Укажите корректный e-mail';
}

if ($consent !== 'Y') {
    $errors['consent'] = 'Требуется согласие на обработку данных';
}

if ($errors !== []) {
    $respond([
        'status'  => 'error',
        'message' => 'Проверьте выделенные поля и согласие на обработку данных.',
        'errors'  => $errors,
    ], 422);
}

/* ---------- Лимит частоты по IP ---------- */
$interval  = max(0, (int)($leads['min_interval'] ?? 60));
$leadsDir  = (string)($leads['dir'] ?? __DIR__ . '/leads');
$rateFile  = null;

if ($interval > 0) {
    if (!is_dir($leadsDir)) {
        @mkdir($leadsDir, 0755, true);
    }

    if (is_dir($leadsDir) && is_writable($leadsDir)) {
        $rateFile = $leadsDir . '/rate_' . sha1($ip) . '.txt';

        if (is_file($rateFile)) {
            $lastTime = (int)@file_get_contents($rateFile);

            if ($lastTime > 0 && (time() - $lastTime) < $interval) {
                $respond([
                    'status'  => 'error',
                    'message' => $form['throttle'],
                ], 429);
            }
        }

        @file_put_contents($rateFile, (string)time(), LOCK_EX);
    }
}

/* ---------- Сохранение заявки ---------- */
$saved = false;

if (!empty($leads['save_to_file'])) {
    if (!is_dir($leadsDir)) {
        @mkdir($leadsDir, 0755, true);
    }

    if (is_dir($leadsDir) && is_writable($leadsDir)) {
        $file = $leadsDir . '/leads.csv';
        $isNew = !is_file($file);

        if ($handle = @fopen($file, 'ab')) {
            if ($isNew) {
                @fputcsv($handle, ['Дата', 'Имя', 'E-mail', 'Телефон', 'Сайт', 'Тариф', 'Задача', 'IP'], ';');
            }

            @fputcsv($handle, [
                date('d.m.Y H:i:s'),
                $name,
                $email,
                $phone,
                $site,
                $plan,
                $message,
                $ip,
            ], ';');

            @fclose($handle);
            $saved = true;
        }
    }
}

/* ---------- Письмо ---------- */
$body = implode("\n", [
    'Новая заявка с лендинга модуля фильтрации трафика.',
    '',
    'Имя:     ' . $name,
    'E-mail:  ' . $email,
    'Телефон: ' . ($phone !== '' ? $phone : '—'),
    'Сайт:    ' . ($site !== '' ? $site : '—'),
    'Тариф:   ' . ($plan !== '' ? $plan : 'не выбран'),
    '',
    'Задача:',
    $message !== '' ? $message : '—',
    '',
    'IP:      ' . $ip,
    'Время:   ' . date('d.m.Y H:i:s'),
]);

$mailSent = false;

/* Пытаемся отправить через почтовые настройки 1С-Битрикс, если платформа доступна */
$prolog = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/bitrix/modules/main/include/prolog_before.php';

if (is_file($prolog)) {
    define('NO_KEEP_STATISTIC', true);
    define('NOT_CHECK_PERMISSIONS', true);
    define('BX_NO_ACCELERATE_RESISTER', true);

    try {
        @include_once $prolog;

        if (class_exists('\Bitrix\Main\Mail\Event')) {
            $siteId = defined('SITE_ID') ? SITE_ID : null;

            $result = \Bitrix\Main\Mail\Event::send([
                'EVENT_NAME' => 'KEYUP_CLEARTRAFIC_LANDING',
                'C_FIELDS'   => [
                    'NAME'    => $name,
                    'EMAIL'   => $email,
                    'PHONE'   => $phone,
                    'SITE'    => $site,
                    'PLAN'    => $plan,
                    'MESSAGE' => $message,
                    'IP'      => $ip,
                ],
                'LID'        => $siteId,
                'MESSAGE'    => [
                    'SUBJECT'   => $subject,
                    'BODY'      => $body,
                    'BODY_TYPE' => 'text',
                    'EMAIL_TO'  => $to,
                ],
            ]);

            /* Метод возвращает идентификатор письма либо false */
            $mailSent = (bool)$result;
        }
    } catch (\Throwable $e) {
        /* Ошибку почты не показываем посетителю: заявка уже сохранена в CSV */
        $mailSent = false;
    }
}

/* Запасной вариант — стандартная функция PHP */
if (!$mailSent && $to !== '') {
    $headers = implode("\r\n", [
        'From: ' . ($config['contacts']['department'] !== '' ? $config['contacts']['department'] : 'Landing') . ' <no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>',
        'Reply-To: ' . $email,
        'Content-Type: text/plain; charset=UTF-8',
        'MIME-Version: 1.0',
    ]);

    $mailSent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

if (!$mailSent && !$saved) {
    $respond([
        'status'  => 'error',
        'message' => $form['error'],
    ], 500);
}

$respond([
    'status'  => 'success',
    'message' => $form['success'],
]);
