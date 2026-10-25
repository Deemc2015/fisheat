<?php
namespace Ldo\Marketing;

use Bitrix\Currency\CurrencyManager;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Sale;

Loader::includeModule('sale');

/**
 * Сервис выдачи товара по промокоду.
 *
 * Bitrix сам не добавляет подарок правила в корзину: правило лишь помечает
 * набор подарков. Сервис материализует товар по применённому купону —
 * добавляет позицию с нулевой ценой и свойством PROMO_GIFT, а при снятии
 * купона удаляет её.
 *
 * Данные берутся из штатных мест: применённые купоны —
 * Sale\DiscountCouponsManager, привязка купона к правилу —
 * b_sale_discount_coupon, товар правила — ACTIONS_LIST в b_sale_discount.
 */
class PromoGiftService
{
    /** Свойство позиции: признак товара, выданного по промокоду. */
    public const BASKET_PROP_FLAG = 'PROMO_GIFT';

    /** Свойство позиции: код купона, по которому выдан товар. */
    public const BASKET_PROP_COUPON = 'PROMO_COUPON';

    /** Защита от рекурсивной синхронизации при сохранении корзины. */
    private static bool $syncing = false;

    /**
     * Выполняется ли синхронизация прямо сейчас.
     *
     * @return bool
     */
    public static function isSyncing(): bool
    {
        return self::$syncing;
    }

    /**
     * Коды применённых купонов текущего покупателя.
     *
     * @return array
     */
    public static function getAppliedCouponCodes(): array
    {
        if (!Loader::includeModule('sale')) {
            return [];
        }

        $codes = [];

        // Основной источник — сессия применённых купонов (её же использует
        // шаблон оформления заказа).
        if (!empty($_SESSION['CATALOG_USER_COUPONS']) && is_array($_SESSION['CATALOG_USER_COUPONS'])) {
            foreach ($_SESSION['CATALOG_USER_COUPONS'] as $code) {
                $code = trim((string)$code);
                if ($code !== '') {
                    $codes[$code] = $code;
                }
            }
        }

        // Дополняем данными ядра: только фактически применённые купоны.
        $list = Sale\DiscountCouponsManager::get(true);
        if (is_array($list)) {
            foreach ($list as $key => $coupon) {
                if (!is_array($coupon)) {
                    continue;
                }

                // Ядро отдаёт признак применения как APPLY (Y/N), а статус —
                // как битовую маску. Учитываем оба варианта.
                $applied = true;
                if (isset($coupon['APPLY'])) {
                    $applied = ((string)$coupon['APPLY'] === 'Y');
                } elseif (isset($coupon['APPLIED'])) {
                    $applied = ((string)$coupon['APPLIED'] === 'Y');
                } elseif (isset($coupon['STATUS'])) {
                    $applied = (((int)$coupon['STATUS']
                        & Sale\DiscountCouponsManager::STATUS_APPLYED) !== 0);
                }

                if (!$applied) {
                    continue;
                }

                $code = trim((string)($coupon['COUPON'] ?? ''));
                if ($code === '') {
                    continue;
                }

                $codes[$code] = $code;
            }
        }

        return array_values($codes);
    }

    /**
     * Промо-товары по применённым купонам.
     *
     * @param array|null $couponCodes Коды купонов; null — определить самим
     * @return array productId => couponCode
     */
    public static function getActivePromoGifts(?array $couponCodes = null): array
    {
        if ($couponCodes === null) {
            $couponCodes = self::getAppliedCouponCodes();
        }

        $result = [];
        foreach ($couponCodes as $code) {
            $code = trim((string)$code);
            if ($code === '') {
                continue;
            }

            $discountId = PromoAdminService::findDiscountIdByCoupon($code);
            if ($discountId <= 0) {
                continue;
            }

            $productId = PromoAdminService::getGiftProductIdByDiscount($discountId);
            if ($productId <= 0) {
                continue;
            }

            $result[$productId] = $code;
        }

        return $result;
    }

    /**
     * Синхронизация корзины с применёнными промокодами.
     *
     * Метод изменяет корзину НА МЕСТЕ: save() выполняет вызывающая сторона.
     *
     * @param Sale\Basket $basket
     * @param array|null  $couponCodes
     * @return bool Были ли изменения
     */
    public static function sync(Sale\Basket $basket, ?array $couponCodes = null): bool
    {
        if (self::$syncing) {
            return false;
        }

        if (!($basket instanceof Sale\Basket)) {
            return false;
        }

        // Корзина, привязанная к заказу, не пересчитывается.
        if (method_exists($basket, 'getOrderId') && (int)$basket->getOrderId() > 0) {
            return false;
        }

        $siteId = method_exists($basket, 'getSiteId') ? (string)$basket->getSiteId() : '';
        if ($siteId === '') {
            $siteId = (string)Context::getCurrent()->getSite();
        }
        if ($siteId === '') {
            $siteId = 's1';
        }

        $gifts = self::getActivePromoGifts($couponCodes);

        self::$syncing = true;
        $changed = false;

        try {
            // Убираем позиции, которых нет среди активных промокодов
            // (снят купон, сменился товар, выбран другой промокод).
            foreach ($basket as $item) {
                if (!self::isPromoGiftItem($item)) {
                    continue;
                }

                $productId = (int)$item->getProductId();
                $itemCoupon = self::getItemCoupon($item);

                if (!isset($gifts[$productId]) || $gifts[$productId] !== $itemCoupon) {
                    $item->delete();
                    $changed = true;
                }
            }

            // Добавляем недостающие позиции.
            $currency = CurrencyManager::getBaseCurrency();
            foreach ($gifts as $productId => $code) {
                $productId = (int)$productId;
                if ($productId <= 0) {
                    continue;
                }

                if (self::findPromoItem($basket, $productId, $code) !== null) {
                    continue;
                }

                $item = $basket->createItem('catalog', $productId);
                $item->setField('CUSTOM_PRICE', 'Y');
                $item->setFields([
                    'QUANTITY'                => 1,
                    'CURRENCY'                => $currency,
                    'LID'                     => $siteId,
                    'PRODUCT_PROVIDER_CLASS'  => 'CCatalogProductProvider',
                ]);

                self::markItemAsPromo($item, $code);
                $changed = true;
            }
        } finally {
            self::$syncing = false;
        }

        return $changed;
    }

    /**
     * Удаление всех промо-позиций корзины.
     *
     * @param Sale\Basket $basket
     * @return bool Были ли изменения
     */
    public static function removeAll(Sale\Basket $basket): bool
    {
        if (self::$syncing || !($basket instanceof Sale\Basket)) {
            return false;
        }

        self::$syncing = true;
        $changed = false;

        try {
            foreach ($basket as $item) {
                if (self::isPromoGiftItem($item)) {
                    $item->delete();
                    $changed = true;
                }
            }
        } finally {
            self::$syncing = false;
        }

        return $changed;
    }

    /**
     * Является ли позиция выданной по промокоду.
     *
     * @param mixed $item
     * @return bool
     */
    public static function isPromoGiftItem($item): bool
    {
        if (!is_object($item) || !method_exists($item, 'getPropertyCollection')) {
            return false;
        }

        $coupon = self::getItemCoupon($item);
        if ($coupon !== '') {
            return true;
        }

        foreach ($item->getPropertyCollection() as $property) {
            if ((string)$property->getField('CODE') === self::BASKET_PROP_FLAG
                && (string)$property->getField('VALUE') === 'Y'
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Код купона, по которому выдан промо-товар.
     *
     * @param mixed $item
     * @return string
     */
    public static function getItemCoupon($item): string
    {
        if (!is_object($item) || !method_exists($item, 'getPropertyCollection')) {
            return '';
        }

        foreach ($item->getPropertyCollection() as $property) {
            if ((string)$property->getField('CODE') === self::BASKET_PROP_COUPON) {
                return trim((string)$property->getField('VALUE'));
            }
        }

        return '';
    }

    /**
     * Поиск промо-позиции по товару и купону.
     *
     * @param Sale\Basket $basket
     * @param int         $productId
     * @param string      $coupon
     * @return Sale\BasketItem|null
     */
    private static function findPromoItem(Sale\Basket $basket, int $productId, string $coupon)
    {
        foreach ($basket as $item) {
            if ((int)$item->getProductId() !== $productId) {
                continue;
            }

            if (self::isPromoGiftItem($item) && self::getItemCoupon($item) === $coupon) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Пометка позиции: нулевая цена и свойства промо-товара.
     *
     * CUSTOM_PRICE задаётся первым — иначе провайдер каталога подставит
     * цену товара вместо нуля.
     *
     * @param Sale\BasketItem $item
     * @param string          $coupon
     * @return void
     */
    private static function markItemAsPromo(Sale\BasketItem $item, string $coupon): void
    {
        $item->setField('CUSTOM_PRICE', 'Y');
        $item->setField('BASE_PRICE', 0);
        $item->setField('DISCOUNT_PRICE', 0);
        $item->setField('PRICE', 0);

        $properties = $item->getPropertyCollection();
        if (!is_object($properties) || !method_exists($properties, 'createItem')) {
            return;
        }

        $existing = [];
        foreach ($properties as $property) {
            $existing[(string)$property->getField('CODE')] = true;
        }

        if (!isset($existing[self::BASKET_PROP_FLAG])) {
            self::createProperty($properties, self::BASKET_PROP_FLAG, 'Товар по промокоду', 'Y', 100);
        }

        if (!isset($existing[self::BASKET_PROP_COUPON])) {
            self::createProperty($properties, self::BASKET_PROP_COUPON, 'Промокод', $coupon, 110);
        }
    }

    /**
     * Добавление свойства позиции корзины.
     *
     * @param mixed  $properties Коллекция свойств позиции
     * @param string $code
     * @param string $name
     * @param string $value
     * @param int    $sort
     * @return void
     */
    private static function createProperty($properties, string $code, string $name, string $value, int $sort): void
    {
        if (!is_object($properties) || !method_exists($properties, 'createItem')) {
            return;
        }

        $property = $properties->createItem();
        if (is_object($property) && method_exists($property, 'setFields')) {
            $property->setFields([
                'NAME'  => $name,
                'CODE'  => $code,
                'VALUE' => $value,
                'SORT'  => $sort,
            ]);
        }
    }
}
