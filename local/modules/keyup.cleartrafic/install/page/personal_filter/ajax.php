<?php

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Bitrix\Main\Application;
use Keyup\Cleartrafic\Rules;
use Keyup\Cleartrafic\Visits;
use Keyup\Cleartrafic\Emails;
use Keyup\Cleartrafic\FormLog;
use Keyup\Cleartrafic\Captcha;
use Keyup\Cleartrafic\Import;
use Keyup\Cleartrafic\LocalStorage;
use Keyup\Cleartrafic\Model\RuleTable;

if (!Loader::IncludeModule('keyup.cleartrafic')) {
    echo "Не установлен модуль фильтрации трафика";
}

/** @var \Bitrix\Main\HttpRequest $request */
$request = Context::getCurrent()->getRequest();

/*Все административные операции доступны только администратору модуля и только с валидным CSRF-токеном*/
$adminTypes = array('ADD', 'EDIT', 'DELETE', 'DELETEALL', 'STOP', 'START');
$type = $request->get("TYPE");

if (in_array($type, $adminTypes, true)) {
    global $USER;

    if (!$USER->IsAuthorized() || !\Keyup\Cleartrafic\User::isAdmin()) {
        http_response_code(403);
        echo json_encode('forbidden');
        die();
    }

    if (!check_bitrix_sessid()) {
        http_response_code(403);
        echo json_encode('bad_session');
        die();
    }
}

/*Определение типа правила по запросу*/
$ruleType = (string)$request->get("RULE_TYPE");
$allowedRuleTypes = array(
    RuleTable::TYPE_BLACK,
    RuleTable::TYPE_GRAY,
    RuleTable::TYPE_MASK,
    RuleTable::TYPE_REFERER,
    RuleTable::TYPE_USERAGENT,
);

if (!in_array($ruleType, $allowedRuleTypes, true)) {
    $ruleType = '';
}

/**
 * Единый ответ об ошибке для формы: код и текст для показа в модальном окне.
 *
 * @param \Bitrix\Main\ORM\Data\Result|null $result
 *
 * @return array
 */
function keyupCleartraficError($result = null)
{
    /* Код ошибки => короткий признак для клиента */
    $codes = array(
        Rules::ERROR_DUPLICATE  => 'duplicate',
        Emails::ERROR_DUPLICATE => 'duplicate',
        Rules::ERROR_INVALID    => 'invalid',
    );

    if ($result) {
        $collection = $result->getErrorCollection();

        foreach ($codes as $code => $alias) {
            $error = $collection->getErrorByCode($code);

            if ($error) {
                return array(
                    'error'   => $alias,
                    'message' => $error->getMessage(),
                );
            }
        }
    }

    return array(
        'error'   => 'fail',
        'message' => 'Не удалось сохранить запись',
    );
}

/*Импорт правил из файла: маски подсетей, серый и чёрный списки*/
if ($request->getFile('FILE') && $request->getFile('FILE')['name']) {
    global $USER;

    if (!$USER->IsAuthorized() || !\Keyup\Cleartrafic\User::isAdmin()) {
        http_response_code(403);
        echo json_encode(array('error' => 'forbidden'));
        die();
    }

    if (!check_bitrix_sessid()) {
        http_response_code(403);
        echo json_encode(array('error' => 'bad_session'));
        die();
    }

    $importType = $ruleType;

    /*Совместимость со страницей масок прежних версий, где тип не передавался*/
    if ($importType === '') {
        $importType = RuleTable::TYPE_MASK;
    }

    $importTypes = array(
        RuleTable::TYPE_MASK,
        RuleTable::TYPE_BLACK,
        RuleTable::TYPE_GRAY,
        RuleTable::TYPE_USERAGENT,
    );

    if (!in_array($importType, $importTypes, true)) {
        echo json_encode(array('error' => 'unsupported_type'));
        die();
    }

    try {
        $fileContent = new Import($request->getFile('FILE'));

        /*Маски — записи вида IP/маска, списки IP — по адресу в строке,
          правила User-Agent — произвольные строки*/
        if ($importType === RuleTable::TYPE_MASK) {
            $items = $fileContent->getMascList('#');
        } elseif ($importType === RuleTable::TYPE_USERAGENT) {
            $items = $fileContent->getTextList('#');
        } else {
            $items = $fileContent->getIpList('#');
        }

        $stats = Rules::importRules($importType, $items);

        echo json_encode(array(
            'added'   => (int)$stats['added'],
            /* Дубликаты: уже есть в таблице или повторяются внутри файла */
            'skipped' => (int)$stats['skipped'] + (int)$fileContent->getDuplicateCount(),
            /* Некорректные строки файла плюс значения, отклонённые проверкой */
            'invalid' => (int)$stats['invalid'] + (int)$fileContent->getInvalidCount(),
        ));
    } catch (\Throwable $e) {
        echo json_encode(array('error' => $e->getMessage()));
    }

    die();
}

/*Очищение списка масок*/
if ($type == 'DELETEALL' && $ruleType === RuleTable::TYPE_MASK) {
    Rules::deleteAllByType(RuleTable::TYPE_MASK);
    echo json_encode('success');
    die();
}

/*Убираем статус активности масок*/
if ($type == 'STOP') {
    Rules::setMasksActive(false);
    echo json_encode('success');
    die();
}

/*Устанавливаем статус активности масок*/
if ($type == 'START') {
    Rules::setMasksActive(true);
    echo json_encode('success');
    die();
}

/*Добавление правила или адреса оповещения*/
if ($type == 'ADD') {
    $entity = (string)$request->get("ENTITY");

    if ($entity === 'email') {
        $email = trim((string)$request->get("VALUE"));

        if ($email !== '') {
            $result = Emails::add($email);

            if ($result->isSuccess()) {
                echo json_encode($result->getId());
                die();
            }

            /*Дубликат адреса оповещения*/
            echo json_encode(keyupCleartraficError($result));
            die();
        }

        echo json_encode(keyupCleartraficError());
        die();
    }

    if ($ruleType !== '') {
        $value = trim((string)$request->get("VALUE"));

        if ($value !== '') {
            $fields = [
                'TYPE'  => $ruleType,
                'VALUE' => $value,
            ];

            if ($ruleType === RuleTable::TYPE_MASK) {
                /* Значение передаётся как есть: корректность проверяет Rules::add() */
                $fields['MASK'] = trim((string)$request->get("MASK"));
            }

            $comment = trim((string)$request->get("COMMENT"));

            if ($comment !== '') {
                $fields['COMMENT'] = $comment;
            }

            $result = Rules::add($fields);

            if ($result->isSuccess()) {
                echo json_encode($result->getId());
                die();
            }

            /*Дубликат значения в списке*/
            echo json_encode(keyupCleartraficError($result));
            die();
        }
    }

    echo json_encode(keyupCleartraficError());
    die();
}

/*Изменение правила или адреса оповещения*/
if ($type == 'EDIT') {
    $entity = (string)$request->get("ENTITY");
    $id = (int)$request->get("ID");

    if ($entity === 'email') {
        $email = trim((string)$request->get("VALUE"));

        if ($id && $email !== '') {
            $result = Emails::update($id, $email);

            if ($result->isSuccess()) {
                echo json_encode('success');
                die();
            }

            /*Дубликат адреса оповещения*/
            echo json_encode(keyupCleartraficError($result));
            die();
        }

        echo json_encode(keyupCleartraficError());
        die();
    }

    if ($ruleType !== '' && $id) {
        $value = trim((string)$request->get("VALUE"));

        if ($value !== '') {
            $fields = ['VALUE' => $value];

            if ($ruleType === RuleTable::TYPE_MASK) {
                /* Значение передаётся как есть: корректность проверяет Rules::update() */
                $fields['MASK'] = trim((string)$request->get("MASK"));
            }

            $comment = (string)$request->get("COMMENT");

            if ($comment !== '') {
                $fields['COMMENT'] = trim($comment);
            }

            $result = Rules::update($id, $fields);

            if ($result->isSuccess()) {
                echo json_encode('success');
                die();
            }

            /*Дубликат значения в списке*/
            echo json_encode(keyupCleartraficError($result));
            die();
        }
    }

    echo json_encode(keyupCleartraficError());
    die();
}

/*Удаление правила или адреса оповещения*/
if ($type == 'DELETE') {
    $entity = (string)$request->get("ENTITY");
    $id = (int)$request->get("ID");

    if (!$id) {
        echo json_encode('fail');
        die();
    }

    if ($entity === 'email') {
        $result = Emails::delete($id);
    } else {
        $result = Rules::delete($id);
    }

    echo json_encode($result->isSuccess() ? 'success' : 'fail');
    die();
}

/*Проверка токена Яндекс SmartCaptcha и добавление IP в белый список*/
if ($request->get("type") == 'CAPTCHA') {
    if (!check_bitrix_sessid()) {
        http_response_code(403);
        echo json_encode('bad_session');
        die();
    }

    $captchaToken = $request->get("data");

    if (Captcha::check($captchaToken)) {
        LocalStorage::addWhiteList();

        /*Отмечаем в таблице визитов, что каптча пройдена*/
        Visits::setCaptchaPassed(LocalStorage::getIp());

        echo json_encode('success');
    } else {
        echo json_encode('fail');
    }

    die();
}

/*Публичная форма обратной связи*/
if ($request->getPost('NAME')) {
    if (!check_bitrix_sessid()) {
        http_response_code(403);
        echo json_encode('bad_session');
        die();
    }

    $userIp = LocalStorage::getIp();

    if ($userIp) {
        $checkPost = FormLog::check($userIp);
    }

    if (isset($checkPost) && $checkPost === true) {
        $name = $request->getPost('NAME');
        $email = $request->getPost('EMAIL');
        $typeForm = $request->getPost('TYPE');
        $text = $request->getPost('TEXT');

        $emailList = Emails::getRecipients();

        $id = null;

        if ($request->getFile('IMAGE_ID')['name']) {
            $file = $request->getFile('IMAGE_ID');
            $ext = substr($file["name"], -4, 4);

            if ($ext == ".jpg" || $ext == ".png") {
                $id = CFile::SaveFile($file, "errorform");
            }
        }

        if ($request->getPost("TABLE") && $request->getPost('TEXT') && $userIp) {
            Visits::addMessage($userIp, (string)$request->getPost('TEXT'));
        }

        if (!empty($name) && !empty($email)) {
            if ($emailList) {
                $subject = 'Сообщение с сайта' . ($typeForm ? ' (' . $typeForm . ')' : '');

                $body = "Имя: " . $name . "\n"
                    . "E-mail: " . $email . "\n"
                    . ($typeForm ? "Тип: " . $typeForm . "\n" : '')
                    . ($text ? "Сообщение:\n" . $text . "\n" : '');

                $attachment = array();

                if ($id) {
                    $filePath = \CFile::GetPath($id);

                    if ($filePath) {
                        $attachment[] = array(
                            'FILE' => $_SERVER['DOCUMENT_ROOT'] . $filePath,
                            'NAME' => basename($filePath),
                        );
                    }
                }

                $headerFrom = (string)\COption::GetOptionString('main', 'email_from', '');

                $result = \Bitrix\Main\Mail\Mail::send(array(
                    'TO' => $emailList,
                    'SUBJECT' => $subject,
                    'BODY' => $body,
                    'CHARSET' => 'UTF-8',
                    'CONTENT_TYPE' => 'text/plain',
                    'HEADER' => array(
                        'From' => $headerFrom,
                        'Reply-To' => $email,
                        'X-Mailer' => 'Bitrix Site Manager',
                    ),
                    'ATTACHMENT' => $attachment,
                ));

                echo json_encode($result ? 'success' : 'fail');
                die();
            }
        }
    } else {
        echo json_encode('time');
    }

    die();
}
