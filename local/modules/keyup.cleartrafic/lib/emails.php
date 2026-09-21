<?php

namespace Keyup\Cleartrafic;

use Bitrix\Main\Error;
use Bitrix\Main\ORM\Data\AddResult;
use Bitrix\Main\ORM\Data\UpdateResult;
use Bitrix\Main\Type\DateTime;
use Keyup\Cleartrafic\Model\EmailTable;

/**
 * Адреса для уведомлений: список хранится в собственной таблице.
 */
class Emails
{
    /** Код ошибки: такой адрес уже есть в списке */
    public const ERROR_DUPLICATE = 'DUPLICATE';
    /**
     * Список записей для интерфейса.
     *
     * @return array
     */
    public static function getList()
    {
        return EmailTable::getList([
            'select' => ['ID', 'EMAIL', 'DATE_CREATE'],
            'order'  => ['ID' => 'DESC'],
        ])->fetchAll();
    }

    /**
     * Получатели в виде строки, разделённой запятыми.
     *
     * @return string
     */
    public static function getRecipients()
    {
        $emails = [];

        $result = EmailTable::getList([
            'select' => ['EMAIL'],
            'order'  => ['ID' => 'ASC'],
        ]);

        while ($row = $result->fetch()) {
            $email = trim((string)$row['EMAIL']);

            if ($email !== '') {
                $emails[] = $email;
            }
        }

        return implode(',', $emails);
    }

    /**
     * Добавляет адрес, если такого ещё нет.
     *
     * При попытке добавить дубликат возвращается результат с ошибкой
     * с кодом self::ERROR_DUPLICATE.
     *
     * @param string $email
     *
     * @return AddResult
     */
    public static function add($email)
    {
        $email = trim((string)$email);

        if (self::findDuplicate($email) !== null) {
            return self::duplicateResult(new AddResult());
        }

        return EmailTable::add([
            'EMAIL'       => $email,
            'DATE_CREATE' => new DateTime(),
        ]);
    }

    /**
     * Изменяет адрес, не допуская появления дубликата.
     *
     * @param int    $id
     * @param string $email
     *
     * @return UpdateResult
     */
    public static function update($id, $email)
    {
        $id = (int)$id;
        $email = trim((string)$email);

        if ($id && self::findDuplicate($email, $id) !== null) {
            return self::duplicateResult(new UpdateResult());
        }

        return EmailTable::update($id, [
            'EMAIL' => $email,
        ]);
    }

    /**
     * Идентификатор записи с таким же адресом.
     *
     * @param string $email
     * @param int    $exceptId ID записи, которая не учитывается (при изменении)
     *
     * @return int|null
     */
    protected static function findDuplicate($email, $exceptId = 0)
    {
        $email = trim((string)$email);

        if ($email === '') {
            return null;
        }

        $filter = ['=EMAIL' => $email];

        if ($exceptId > 0) {
            $filter['!=ID'] = (int)$exceptId;
        }

        $row = EmailTable::getList([
            'select' => ['ID'],
            'filter' => $filter,
            'limit'  => 1,
        ])->fetch();

        return $row ? (int)$row['ID'] : null;
    }

    /**
     * Результат с ошибкой «адрес уже добавлен».
     *
     * @param AddResult|UpdateResult $result
     *
     * @return AddResult|UpdateResult
     */
    protected static function duplicateResult($result)
    {
        $result->addError(new Error('Такой адрес уже добавлен в список оповещений', self::ERROR_DUPLICATE));

        return $result;
    }

    /**
     * @param int $id
     *
     * @return \Bitrix\Main\ORM\Data\DeleteResult
     */
    public static function delete($id)
    {
        return EmailTable::delete((int)$id);
    }
}
