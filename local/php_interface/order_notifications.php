<?php

namespace Ldo;

use Bitrix\Main\Application;
use Bitrix\Main\Event;
use Bitrix\Main\Loader;

/**
 * Уведомления о смене статуса заказа по технологии Push & Poll.
 *
 * PUSH — при изменении статуса заказа (событие sale:OnSaleStatusOrderChange)
 * создаётся запись уведомления в собственной таблице БД для владельца заказа.
 *
 * POLL — клиент периодически опрашивает AJAX-эндпоинт (local/ajax/order_notifications.php),
 * который отдаёт и сразу удаляет уведомления текущего пользователя.
 */
class OrderNotifications
{
    /** Имя таблицы с уведомлениями */
    const TABLE = 'b_ldo_order_notifications';

    /**
     * Создать таблицу уведомлений, если её ещё нет.
     * Вызывается из init.php и при каждом обращении к хранилищу.
     *
     * @return void
     */
    public static function install(): void
    {
        $connection = Application::getConnection();

        if ($connection->isTableExists(self::TABLE)) {
            return;
        }

        $connection->queryExecute("CREATE TABLE IF NOT EXISTS `" . self::TABLE . "` (
            `ID` INT NOT NULL AUTO_INCREMENT,
            `USER_ID` INT NOT NULL,
            `ORDER_ID` INT NOT NULL,
            `STATUS_ID` VARCHAR(2) NOT NULL,
            `MESSAGE` VARCHAR(500) NOT NULL,
            `CREATED` DATETIME NOT NULL,
            PRIMARY KEY (`ID`),
            KEY `IX_ORDER_NOTIF_USER` (`USER_ID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * Обработчик события sale:OnSaleStatusOrderChange.
     * Параметры события: ENTITY (объект заказа), VALUE (новый статус), OLD_VALUE (старый статус).
     *
     * @param Event $event
     * @return void
     */
    public static function onOrderStatusChange(Event $event): void
    {
        if (!Loader::includeModule('sale')) {
            return;
        }

        $order = $event->getParameter('ENTITY');
        $statusId = (string)$event->getParameter('VALUE');

        if (!$order || !is_object($order) || !method_exists($order, 'getId')) {
            return;
        }

        $orderId = (int)$order->getId();
        $userId = (int)$order->getUserId();

        if ($orderId <= 0 || $userId <= 0) {
            // Заказ без привязки к пользователю — уведомлять некого
            return;
        }

        $accountNumber = (string)$order->getField('ACCOUNT_NUMBER');
        $number = $accountNumber !== '' ? $accountNumber : (string)$orderId;

        $statusName = self::getStatusName($statusId);

        $message = sprintf(
            'Заказ №%s: статус изменён на «%s»',
            $number,
            $statusName
        );

        self::push($userId, $orderId, $statusId, $message);
    }

    /**
     * Добавить уведомление в очередь (PUSH).
     *
     * @param int $userId
     * @param int $orderId
     * @param string $statusId
     * @param string $message
     * @return void
     */
    public static function push(int $userId, int $orderId, string $statusId, string $message): void
    {
        if ($userId <= 0 || $orderId <= 0) {
            return;
        }

        self::install();

        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();

        $connection->queryExecute(
            "INSERT INTO `" . self::TABLE . "`
                (`USER_ID`, `ORDER_ID`, `STATUS_ID`, `MESSAGE`, `CREATED`)
             VALUES (
                " . (int)$userId . ",
                " . (int)$orderId . ",
                '" . $helper->forSql($statusId) . "',
                '" . $helper->forSql(mb_substr($message, 0, 500)) . "',
                NOW()
             )"
        );
    }

    /**
     * Получить и удалить уведомления пользователя (POLL).
     * Очередь «вычитывается» — после выдачи записи удаляются,
     * чтобы одно и то же уведомление не показывалось повторно.
     *
     * @param int $userId
     * @return array
     */
    public static function poll(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        self::install();

        $connection = Application::getConnection();

        $rows = [];
        $rs = $connection->query(
            "SELECT `ID`, `ORDER_ID`, `STATUS_ID`, `MESSAGE`, `CREATED`
             FROM `" . self::TABLE . "`
             WHERE `USER_ID` = " . (int)$userId . "
             ORDER BY `ID` ASC"
        );

        while ($row = $rs->fetch()) {
            $rows[] = $row;
        }

        if (!empty($rows)) {
            $ids = array_column($rows, 'ID');
            $connection->queryExecute(
                "DELETE FROM `" . self::TABLE . "`
                 WHERE `ID` IN (" . implode(',', array_map('intval', $ids)) . ")"
            );
        }

        return $rows;
    }

    /**
     * Название статуса заказа (по ID) для текущего языка сайта.
     *
     * @param string $statusId
     * @return string
     */
    private static function getStatusName(string $statusId): string
    {
        static $statuses = null;

        if ($statuses === null) {
            $statuses = [];

            if (Loader::includeModule('sale')) {
                try {
                    $rs = \Bitrix\Sale\OrderStatus::getList([
                        'select' => ['ID', 'NAME'],
                        'filter' => [],
                    ]);
                    while ($row = $rs->fetch()) {
                        $statuses[$row['ID']] = (string)$row['NAME'];
                    }
                } catch (\Throwable $e) {
                    $statuses = [];
                }
            }
        }

        return $statuses[$statusId] ?? $statusId;
    }
}
