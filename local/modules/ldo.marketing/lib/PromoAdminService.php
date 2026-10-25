<?php
namespace Ldo\Marketing;

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\Discount\Gift\RelatedDataTable;
use Bitrix\Sale\Internals\DiscountCouponTable;
use Bitrix\Sale\Internals\DiscountTable;

/**
 * Сервис управления промокодами для партнёрского раздела.
 *
 * Промокод — это штатное правило работы с корзиной (b_sale_discount),
 * привязанное к купону (b_sale_discount_coupon). Модуль не создаёт своё
 * хранилище промокодов: лимиты, сроки, персональность и условия остаются
 * в штатных таблицах Bitrix, поэтому работают отчёты и учёт использований.
 *
 * Один промокод даёт ровно один эффект:
 * - TYPE_ORDER    — скидка на заказ (действие ActSaleBsktGrp);
 * - TYPE_DELIVERY — скидка на доставку (действие ActSaleDelivery);
 * - TYPE_PRODUCT  — товар по промокоду (действие ActSaleGift, товар из правила
 *                   ядро складывает в b_sale_gift_related_data).
 *
 * Материализацию товара в корзине выполняет отдельный сервис PromoGiftService
 * (см. plans/promokody-plan.md).
 */
class PromoAdminService
{
    /** Скидка на заказ. */
    const TYPE_ORDER = 'order';

    /** Скидка на доставку. */
    const TYPE_DELIVERY = 'delivery';

    /** Товар по промокоду. */
    const TYPE_PRODUCT = 'product';

    /** Значение скидки в процентах (атом Unit правила). */
    const UNIT_PERCENT = 'Perc';

    /** Значение скидки фиксированной суммой за единицу (атом Unit правила). */
    const UNIT_CUR = 'Cur';

    /** Значение скидки фиксированной суммой на весь заказ (атом Unit правила). */
    const UNIT_CUR_ALL = 'CurAll';

    /** Тип купона: на одну позицию корзины. */
    const COUPON_TYPE_POSITION = 'position';

    /** Тип купона: одноразовый. */
    const COUPON_TYPE_ONE = 'one';

    /** Тип купона: многоразовый. */
    const COUPON_TYPE_MULTI = 'multi';

    /**
     * Названия типов промокода.
     *
     * @return array
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_ORDER    => 'Скидка на заказ',
            self::TYPE_DELIVERY => 'Скидка на доставку',
            self::TYPE_PRODUCT  => 'Товар по промокоду',
        ];
    }

    /**
     * Названия типов купона.
     *
     * @return array
     */
    public static function getCouponTypes(): array
    {
        return [
            self::COUPON_TYPE_ONE      => 'Одноразовый',
            self::COUPON_TYPE_MULTI    => 'Многоразовый',
            self::COUPON_TYPE_POSITION => 'На позицию корзины',
        ];
    }

    /**
     * Список промокодов сайта (правило + купон).
     *
     * @param string $siteId
     * @return array
     */
    public static function getList(string $siteId): array
    {
        if (!Loader::includeModule('sale')) {
            return [];
        }

        $discounts = [];
        $rs = DiscountTable::getList([
            'filter' => [
                '=LID'         => $siteId,
                '=USE_COUPONS' => 'Y',
            ],
            'select' => [
                'ID', 'NAME', 'ACTIVE', 'ACTIVE_FROM', 'ACTIVE_TO', 'SORT',
                'PRIORITY', 'LAST_DISCOUNT', 'CONDITIONS_LIST', 'ACTIONS_LIST',
                'DISCOUNT_VALUE', 'DISCOUNT_TYPE',
            ],
            'order'  => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);
        while ($row = $rs->fetch()) {
            $discounts[(int)$row['ID']] = $row;
        }

        if (empty($discounts)) {
            return [];
        }

        $coupons = [];
        $crs = DiscountCouponTable::getList([
            'filter' => ['=DISCOUNT_ID' => array_keys($discounts)],
            'select' => [
                'ID', 'DISCOUNT_ID', 'COUPON', 'ACTIVE', 'TYPE', 'MAX_USE',
                'USE_COUNT', 'USER_ID', 'ACTIVE_FROM', 'ACTIVE_TO',
            ],
            'order'  => ['ID' => 'ASC'],
        ]);
        while ($coupon = $crs->fetch()) {
            $discountId = (int)$coupon['DISCOUNT_ID'];
            // Один промокод — один купон: берём первый.
            if (!isset($coupons[$discountId])) {
                $coupons[$discountId] = $coupon;
            }
        }

        $result = [];
        foreach ($discounts as $discountId => $discount) {
            $coupon = $coupons[$discountId] ?? null;
            if ($coupon === null) {
                continue;
            }

            $result[] = self::prepareRow($discount, $coupon);
        }

        return $result;
    }

    /**
     * Один промокод по ID правила.
     *
     * @param int $discountId
     * @return array|null
     */
    public static function getById(int $discountId): ?array
    {
        if ($discountId <= 0 || !Loader::includeModule('sale')) {
            return null;
        }

        $discount = DiscountTable::getRow([
            'filter' => ['=ID' => $discountId],
            'select' => [
                'ID', 'LID', 'NAME', 'ACTIVE', 'ACTIVE_FROM', 'ACTIVE_TO', 'SORT',
                'PRIORITY', 'LAST_DISCOUNT', 'CONDITIONS_LIST', 'ACTIONS_LIST',
                'DISCOUNT_VALUE', 'DISCOUNT_TYPE',
            ],
        ]);
        if (!$discount) {
            return null;
        }

        $coupon = DiscountCouponTable::getRow([
            'filter' => ['=DISCOUNT_ID' => $discountId],
            'select' => [
                'ID', 'DISCOUNT_ID', 'COUPON', 'ACTIVE', 'TYPE', 'MAX_USE',
                'USE_COUNT', 'USER_ID', 'ACTIVE_FROM', 'ACTIVE_TO',
            ],
            'order'  => ['ID' => 'ASC'],
        ]);
        if (!$coupon) {
            return null;
        }

        return self::prepareRow($discount, $coupon);
    }

    /**
     * ID правила работы с корзиной по коду купона.
     *
     * @param string $code
     * @return int
     */
    public static function findDiscountIdByCoupon(string $code): int
    {
        $code = trim($code);
        if ($code === '' || !Loader::includeModule('sale')) {
            return 0;
        }

        $row = DiscountCouponTable::getRow([
            'filter' => ['=COUPON' => $code],
            'select' => ['DISCOUNT_ID'],
        ]);

        return $row ? (int)$row['DISCOUNT_ID'] : 0;
    }

    /**
     * Товар-подарок правила (0, если правило не товарное).
     *
     * @param int $discountId
     * @return int
     */
    public static function getGiftProductIdByDiscount(int $discountId): int
    {
        if ($discountId <= 0 || !Loader::includeModule('sale')) {
            return 0;
        }

        $row = DiscountTable::getRow([
            'filter' => ['=ID' => $discountId],
            'select' => ['ID', 'ACTIONS_LIST'],
        ]);
        if (!$row) {
            return 0;
        }

        return self::extractGiftProductId((array)($row['ACTIONS_LIST'] ?? []));
    }

    /**
     * Признак того, что правило и его купон активны.
     *
     * @param int $discountId
     * @return bool
     */
    public static function isDiscountActive(int $discountId): bool
    {
        if ($discountId <= 0 || !Loader::includeModule('sale')) {
            return false;
        }

        $discount = DiscountTable::getRow([
            'filter' => ['=ID' => $discountId, '=ACTIVE' => 'Y'],
            'select' => ['ID'],
        ]);
        if (!$discount) {
            return false;
        }

        $coupon = DiscountCouponTable::getRow([
            'filter' => ['=DISCOUNT_ID' => $discountId, '=ACTIVE' => 'Y'],
            'select' => ['ID'],
        ]);

        return (bool)$coupon;
    }

    /**
     * Проверка: занят ли код промокода другим правилом.
     *
     * @param string $code
     * @param int    $ignoreDiscountId
     * @return int ID правила, занявшего код (0 — свободен)
     */
    public static function findDiscountByCode(string $code, int $ignoreDiscountId = 0): int
    {
        if ($code === '' || !Loader::includeModule('sale')) {
            return 0;
        }

        $row = DiscountCouponTable::getRow([
            'filter' => ['=COUPON' => $code],
            'select' => ['DISCOUNT_ID'],
        ]);
        if (!$row) {
            return 0;
        }

        $discountId = (int)$row['DISCOUNT_ID'];

        return $discountId === $ignoreDiscountId ? 0 : $discountId;
    }

    /**
     * Создание или обновление промокода.
     *
     * @param array $data Данные формы (см. normalizeInput)
     * @return array{success: bool, id?: int, error?: string}
     */
    public static function save(array $data): array
    {
        if (!Loader::includeModule('sale')) {
            return ['success' => false, 'error' => 'Модуль sale не установлен.'];
        }

        $input = self::normalizeInput($data);

        $errors = self::validate($input);
        if ($errors !== []) {
            return ['success' => false, 'error' => implode(' ', $errors)];
        }

        $discountFields = self::buildDiscountFields($input);

        try {
            if ($input['id'] > 0) {
                $existing = self::getById($input['id']);
                if ($existing === null) {
                    return ['success' => false, 'error' => 'Промокод не найден.'];
                }

                $discountFields['LID'] = $existing['SITE_ID'];
                $result = DiscountTable::update($input['id'], $discountFields);
                $discountId = $input['id'];
            } else {
                $result = DiscountTable::add($discountFields);
                $discountId = $result->isSuccess() ? (int)$result->getId() : 0;
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Ошибка сохранения правила: ' . $e->getMessage()];
        }

        if (!$result->isSuccess()) {
            $messages = $result->getErrorMessages();

            return [
                'success' => false,
                'error'   => $messages ? implode('; ', $messages) : 'Не удалось сохранить правило.',
            ];
        }

        $couponResult = self::saveCoupon($discountId, $input);
        if (!$couponResult['success']) {
            // Правило без купона работать не будет — откатываем создание.
            if ($input['id'] <= 0 && $discountId > 0) {
                DiscountTable::delete($discountId);
            }

            return $couponResult;
        }

        self::syncGiftRelatedData($discountId, (array)$discountFields['ACTIONS_LIST']);

        return ['success' => true, 'id' => $discountId];
    }

    /**
     * Синхронизация штатной таблицы подарков правила
     * (b_sale_gift_related_data) для товарных промокодов.
     *
     * Ядро заполняет её не всегда, поэтому поддерживаем данные явно:
     * иначе Gift\Manager не увидит подарок правила.
     *
     * @param int   $discountId
     * @param array $actionsList
     * @return void
     */
    private static function syncGiftRelatedData(int $discountId, array $actionsList): void
    {
        if ($discountId <= 0 || !Loader::includeModule('sale')) {
            return;
        }

        try {
            RelatedDataTable::deleteByDiscount($discountId);

            $discount = [
                'ID'          => $discountId,
                'ACTIONS_LIST' => $actionsList,
            ];

            $giftManager = \Bitrix\Sale\Discount\Gift\Manager::getInstance();
            if ($giftManager->isContainGiftAction($discount)) {
                RelatedDataTable::fillByDiscount($discount);
            }
        } catch (\Throwable $e) {
            // Подарочные related-данные не критичны: сервис выдачи товара
            // читает товар непосредственно из ACTIONS_LIST правила.
        }
    }

    /**
     * Удаление промокода вместе с правилом (ядро удаляет и купоны).
     *
     * @param int $discountId
     * @return array{success: bool, error?: string}
     */
    public static function delete(int $discountId): array
    {
        if ($discountId <= 0 || !Loader::includeModule('sale')) {
            return ['success' => false, 'error' => 'Неверный идентификатор промокода.'];
        }

        try {
            $result = DiscountTable::delete($discountId);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Ошибка удаления: ' . $e->getMessage()];
        }

        if (!$result->isSuccess()) {
            return ['success' => false, 'error' => 'Не удалось удалить промокод.'];
        }

        return ['success' => true];
    }

    /**
     * Включение и выключение промокода.
     *
     * @param int    $discountId
     * @param string $active 'Y' или 'N'
     * @return array{success: bool, error?: string}
     */
    public static function setActive(int $discountId, string $active): array
    {
        $active = $active === 'Y' ? 'Y' : 'N';

        if ($discountId <= 0 || !Loader::includeModule('sale')) {
            return ['success' => false, 'error' => 'Неверный идентификатор промокода.'];
        }

        try {
            DiscountTable::update($discountId, ['ACTIVE' => $active]);

            $coupons = DiscountCouponTable::getList([
                'filter' => ['=DISCOUNT_ID' => $discountId],
                'select' => ['ID'],
            ]);
            while ($coupon = $coupons->fetch()) {
                DiscountCouponTable::update((int)$coupon['ID'], ['ACTIVE' => $active]);
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Ошибка сохранения: ' . $e->getMessage()];
        }

        return ['success' => true];
    }

    /**
     * Приведение входящих данных к единому виду.
     *
     * @param array $data
     * @return array
     */
    private static function normalizeInput(array $data): array
    {
        $type = (string)($data['type'] ?? self::TYPE_ORDER);
        if (!isset(self::getTypes()[$type])) {
            $type = self::TYPE_ORDER;
        }

        $valueType = (string)($data['value_type'] ?? 'percent');
        if (!in_array($valueType, ['percent', 'sum'], true)) {
            $valueType = 'percent';
        }

        $couponType = (string)($data['coupon_type'] ?? self::COUPON_TYPE_ONE);
        if (!isset(self::getCouponTypes()[$couponType])) {
            $couponType = self::COUPON_TYPE_ONE;
        }

        $siteId = trim((string)($data['site_id'] ?? ''));
        if ($siteId === '') {
            $siteId = (string)Context::getCurrent()->getSite();
        }
        if ($siteId === '') {
            $siteId = 's1';
        }
        $siteId = mb_substr($siteId, 0, 2);

        return [
            'id'          => (int)($data['id'] ?? 0),
            'site_id'     => $siteId,
            'name'        => trim((string)($data['name'] ?? '')),
            'code'        => mb_strtoupper(trim((string)($data['code'] ?? ''))),
            'type'        => $type,
            'value'       => (float)str_replace(',', '.', (string)($data['value'] ?? '0')),
            'value_type'  => $valueType,
            'min_sum'     => max(0.0, (float)str_replace(',', '.', (string)($data['min_sum'] ?? '0'))),
            'product_id'  => (int)($data['product_id'] ?? 0),
            'active'      => ($data['active'] ?? 'Y') === 'Y' ? 'Y' : 'N',
            'coupon_type' => $couponType,
            'max_use'     => max(0, (int)($data['max_use'] ?? 0)),
            'user_id'     => max(0, (int)($data['user_id'] ?? 0)),
            'date_from'   => trim((string)($data['date_from'] ?? '')),
            'date_to'     => trim((string)($data['date_to'] ?? '')),
        ];
    }

    /**
     * Проверка данных промокода.
     *
     * @param array $input
     * @return array Список ошибок
     */
    private static function validate(array $input): array
    {
        $errors = [];

        if ($input['code'] === '') {
            $errors[] = 'Укажите код промокода.';
        } elseif (!preg_match('/^[A-Z0-9_\-]{2,32}$/', $input['code'])) {
            $errors[] = 'Код может содержать латиницу, цифры, дефис и подчёркивание, от 2 до 32 символов.';
        } else {
            $busyId = self::findDiscountByCode($input['code'], $input['id']);
            if ($busyId > 0) {
                $errors[] = 'Такой код уже используется другим промокодом.';
            }
        }

        if ($input['type'] === self::TYPE_PRODUCT) {
            if ($input['product_id'] <= 0) {
                $errors[] = 'Выберите товар, который выдаётся по промокоду.';
            } elseif (!self::isCatalogProduct($input['product_id'], $input['site_id'])) {
                $errors[] = 'Товар не найден в каталоге выбранного сайта.';
            }
        } else {
            if ($input['value'] <= 0) {
                $errors[] = 'Укажите значение скидки больше нуля.';
            } elseif ($input['value_type'] === 'percent' && $input['value'] > 100) {
                $errors[] = 'Скидка в процентах не может быть больше 100.';
            }
        }

        foreach (['date_from' => $input['date_from'], 'date_to' => $input['date_to']] as $key => $raw) {
            if ($raw !== '' && self::parseDate($raw) === null) {
                $errors[] = 'Дата должна быть в формате ДД.ММ.ГГГГ или ДД.ММ.ГГГГ ЧЧ:ММ.';
                break;
            }
        }

        return $errors;
    }

    /**
     * Формирование полей правила b_sale_discount.
     *
     * @param array $input
     * @return array
     */
    private static function buildDiscountFields(array $input): array
    {
        $price = $input['value'];

        return [
            'LID'             => $input['site_id'],
            'NAME'            => $input['name'] !== '' ? $input['name'] : 'Промокод ' . $input['code'],
            'ACTIVE'          => $input['active'],
            'SORT'            => 500,
            'PRIORITY'        => 1,
            'LAST_DISCOUNT'   => 'N',
            'USE_COUPONS'     => 'Y',
            'EXECUTE_MODULE'  => 'all',
            'DISCOUNT_VALUE'  => $price,
            'DISCOUNT_TYPE'   => $input['value_type'] === 'percent' ? 'P' : 'M',
            'ACTIVE_FROM'     => self::parseDate($input['date_from']),
            'ACTIVE_TO'       => self::parseDate($input['date_to']),
            'CONDITIONS_LIST' => self::buildConditions($input),
            'ACTIONS_LIST'    => self::buildActions($input),
        ];
    }

    /**
     * Условия правила: минимальная сумма корзины.
     *
     * @param array $input
     * @return array
     */
    private static function buildConditions(array $input): array
    {
        $children = [];
        if ($input['min_sum'] > 0) {
            $children[] = [
                'CLASS_ID' => 'CondBsktAmtGroup',
                'DATA'     => [
                    'logic' => 'EqGr',
                    'Value' => $input['min_sum'],
                    'All'   => 'AND',
                ],
                'CHILDREN' => [],
            ];
        }

        return [
            'CLASS_ID' => 'CondGroup',
            'DATA'     => [
                'All'  => 'AND',
                'True' => 'True',
            ],
            'CHILDREN' => $children,
        ];
    }

    /**
     * Действия правила в зависимости от типа промокода.
     *
     * @param array $input
     * @return array
     */
    private static function buildActions(array $input): array
    {
        if ($input['type'] === self::TYPE_PRODUCT) {
            // Группа подарков: товар описывается вложенным условием GifterCondIBElement.
            // Именно в такой структуре ядро (Gift\RelatedDataTable::fillByDiscount)
            // заполняет b_sale_gift_related_data и находит подарки правила.
            $child = [
                'CLASS_ID' => 'GiftCondGroup',
                'DATA'     => ['All' => 'AND'],
                'CHILDREN' => [
                    [
                        'CLASS_ID' => 'GifterCondIBElement',
                        'DATA'     => ['Value' => [(int)$input['product_id']]],
                    ],
                ],
            ];
        } elseif ($input['type'] === self::TYPE_DELIVERY) {
            $child = [
                'CLASS_ID' => 'ActSaleDelivery',
                'DATA'     => [
                    'Type'  => 'Discount',
                    'Value' => $input['value'],
                    'Unit'  => $input['value_type'] === 'percent' ? self::UNIT_PERCENT : self::UNIT_CUR,
                ],
                'CHILDREN' => [],
            ];
        } else {
            $child = [
                'CLASS_ID' => 'ActSaleBsktGrp',
                'DATA'     => [
                    'Type'  => 'Discount',
                    'Value' => $input['value'],
                    'Unit'  => $input['value_type'] === 'percent' ? self::UNIT_PERCENT : self::UNIT_CUR_ALL,
                    'Max'   => 0,
                    'All'   => 'AND',
                    'True'  => 'True',
                ],
                'CHILDREN' => [],
            ];
        }

        return [
            'CLASS_ID' => 'CondGroup',
            'DATA'     => ['All' => 'AND'],
            'CHILDREN' => [$child],
        ];
    }

    /**
     * Создание или обновление купона правила.
     *
     * @param int   $discountId
     * @param array $input
     * @return array{success: bool, error?: string}
     */
    private static function saveCoupon(int $discountId, array $input): array
    {
        if ($discountId <= 0) {
            return ['success' => false, 'error' => 'Не удалось определить правило промокода.'];
        }

        $existing = DiscountCouponTable::getRow([
            'filter' => ['=DISCOUNT_ID' => $discountId],
            'select' => ['ID'],
            'order'  => ['ID' => 'ASC'],
        ]);

        $fields = [
            'DISCOUNT_ID' => $discountId,
            'COUPON'      => $input['code'],
            'ACTIVE'      => $input['active'],
            'TYPE'        => self::mapCouponType($input['coupon_type']),
            'MAX_USE'     => $input['max_use'],
            'USER_ID'     => $input['user_id'],
            'ACTIVE_FROM' => self::parseDate($input['date_from']),
            'ACTIVE_TO'   => self::parseDate($input['date_to']),
        ];

        try {
            if ($existing) {
                $result = DiscountCouponTable::update((int)$existing['ID'], $fields);
            } else {
                $result = DiscountCouponTable::add($fields);
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Ошибка сохранения купона: ' . $e->getMessage()];
        }

        if (!$result->isSuccess()) {
            $messages = $result->getErrorMessages();

            return [
                'success' => false,
                'error'   => $messages ? implode('; ', $messages) : 'Не удалось сохранить купон.',
            ];
        }

        return ['success' => true];
    }

    /**
     * Тип купона Bitrix по строке формы.
     *
     * @param string $type
     * @return int
     */
    private static function mapCouponType(string $type): int
    {
        switch ($type) {
            case self::COUPON_TYPE_MULTI:
                return DiscountCouponTable::TYPE_MULTI_ORDER;
            case self::COUPON_TYPE_POSITION:
                return DiscountCouponTable::TYPE_BASKET_ROW;
            case self::COUPON_TYPE_ONE:
            default:
                return DiscountCouponTable::TYPE_ONE_ORDER;
        }
    }

    /**
     * Подготовка строки промокода для интерфейса.
     *
     * @param array $discount
     * @param array $coupon
     * @return array
     */
    private static function prepareRow(array $discount, array $coupon): array
    {
        $type = self::detectType($discount['ACTIONS_LIST'] ?? []);
        $actionData = self::firstActionData($discount['ACTIONS_LIST'] ?? []);

        $productId = $type === self::TYPE_PRODUCT
            ? self::extractGiftProductId($discount['ACTIONS_LIST'] ?? [])
            : 0;

        $percent = null;
        $value = null;
        if ($type === self::TYPE_ORDER || $type === self::TYPE_DELIVERY) {
            $value = (float)($actionData['Value'] ?? 0);
            $percent = (($actionData['Unit'] ?? '') === self::UNIT_PERCENT);
        }

        $minSum = 0.0;
        $conditions = $discount['CONDITIONS_LIST'] ?? [];
        if (!empty($conditions['CHILDREN'][0]['DATA']['Value'])) {
            $minSum = (float)$conditions['CHILDREN'][0]['DATA']['Value'];
        }

        return [
            'ID'           => (int)$discount['ID'],
            'NAME'         => (string)$discount['NAME'],
            'SITE_ID'      => (string)($discount['LID'] ?? ''),
            'TYPE'         => $type,
            'TYPE_NAME'    => self::getTypes()[$type] ?? $type,
            'ACTIVE'       => (($discount['ACTIVE'] ?? 'N') === 'Y'
                && ($coupon['ACTIVE'] ?? 'N') === 'Y') ? 'Y' : 'N',
            'DISCOUNT_ACTIVE' => ($discount['ACTIVE'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'COUPON_ACTIVE'   => ($coupon['ACTIVE'] ?? 'N') === 'Y' ? 'Y' : 'N',
            'CODE'         => (string)$coupon['COUPON'],
            'COUPON_ID'    => (int)$coupon['ID'],
            'COUPON_TYPE'  => self::detectCouponType((int)$coupon['TYPE']),
            'VALUE'        => $value,
            'IS_PERCENT'   => $percent,
            'MIN_SUM'      => $minSum,
            'PRODUCT_ID'   => $productId,
            'MAX_USE'      => (int)$coupon['MAX_USE'],
            'USE_COUNT'    => (int)$coupon['USE_COUNT'],
            'USER_ID'      => (int)$coupon['USER_ID'],
            'DATE_FROM'    => self::formatDate($discount['ACTIVE_FROM'] ?? null),
            'DATE_TO'      => self::formatDate($discount['ACTIVE_TO'] ?? null),
            'COUPON_DATE_FROM' => self::formatDate($coupon['ACTIVE_FROM'] ?? null),
            'COUPON_DATE_TO'   => self::formatDate($coupon['ACTIVE_TO'] ?? null),
        ];
    }

    /**
     * Определение типа промокода по структуре действий.
     *
     * @param array $actionsList
     * @return string
     */
    private static function detectType(array $actionsList): string
    {
        $classId = (string)($actionsList['CHILDREN'][0]['CLASS_ID'] ?? '');

        switch ($classId) {
            case 'ActSaleDelivery':
                return self::TYPE_DELIVERY;
            case 'GiftCondGroup':
            case 'ActSaleGift':
                return self::TYPE_PRODUCT;
            case 'ActSaleBsktGrp':
            default:
                return self::TYPE_ORDER;
        }
    }

    /**
     * DATA первого действия правила.
     *
     * @param array $actionsList
     * @return array
     */
    private static function firstActionData(array $actionsList): array
    {
        $data = $actionsList['CHILDREN'][0]['DATA'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * ID товара-подарка из структуры действий правила.
     *
     * Поддерживает группу подарков (GiftCondGroup → GifterCondIBElement)
     * и устаревшее действие ActSaleGift с полем GiftValue.
     *
     * @param array $actionsList
     * @return int
     */
    private static function extractGiftProductId(array $actionsList): int
    {
        $children = $actionsList['CHILDREN'] ?? [];
        if (!is_array($children)) {
            return 0;
        }

        foreach ($children as $child) {
            $classId = (string)($child['CLASS_ID'] ?? '');

            if ($classId === 'ActSaleGift' && !empty($child['DATA']['GiftValue'])) {
                return (int)reset($child['DATA']['GiftValue']);
            }

            if ($classId !== 'GiftCondGroup' || empty($child['CHILDREN'])) {
                continue;
            }

            foreach ($child['CHILDREN'] as $gifter) {
                if (($gifter['CLASS_ID'] ?? '') === 'GifterCondIBElement'
                    && !empty($gifter['DATA']['Value'])
                ) {
                    return (int)reset($gifter['DATA']['Value']);
                }
            }
        }

        return 0;
    }

    /**
     * Тип купона в строковом виде.
     *
     * @param int $type
     * @return string
     */
    private static function detectCouponType(int $type): string
    {
        if ($type & DiscountCouponTable::TYPE_MULTI_ORDER) {
            return self::COUPON_TYPE_MULTI;
        }
        if ($type & DiscountCouponTable::TYPE_BASKET_ROW) {
            return self::COUPON_TYPE_POSITION;
        }

        return self::COUPON_TYPE_ONE;
    }

    /**
     * Дата из формы в объект Bitrix.
     *
     * @param string $raw
     * @return DateTime|null
     */
    private static function parseDate(string $raw): ?DateTime
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        foreach (['d.m.Y H:i', 'd.m.Y H:i:s', 'd.m.Y'] as $format) {
            try {
                return DateTime::createFromFormat($format, $raw);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Дата в строку для формы.
     *
     * @param mixed $value
     * @return string
     */
    private static function formatDate($value): string
    {
        if ($value instanceof DateTime) {
            return $value->format('d.m.Y H:i');
        }

        return '';
    }

    /**
     * Проверка, что товар принадлежит каталогу сайта.
     *
     * @param int    $productId
     * @param string $siteId
     * @return bool
     */
    private static function isCatalogProduct(int $productId, string $siteId): bool
    {
        if ($productId <= 0 || !Loader::includeModule('iblock')) {
            return false;
        }

        $iblockId = Settings::getCatalogIblockId($siteId);
        if ($iblockId <= 0) {
            return false;
        }

        $row = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $iblockId, '=ID' => $productId, 'ACTIVE' => 'Y'],
            false,
            ['nTopCount' => 1],
            ['ID']
        )->Fetch();

        return (bool)$row;
    }
}
