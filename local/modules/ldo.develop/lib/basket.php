<?php

namespace Ldo\Develop;

use Bitrix\Currency\CurrencyManager;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\SystemException;
use Bitrix\Sale;
use Bitrix\Sale\DiscountCouponsManager;
use Ldo\Develop\Product;
use Ldo\Develop\Iblock;

Loader::includeModule('sale');

class Basket
{
    private $basket;
    private $arBasketItems = array();

    /** Защита от рекурсивного пересчёта бесплатных позиций. */
    private static $freePositionsSyncing = false;

    public function __construct()
    {
        $this->basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), Context::getCurrent()->getSite());
    }

    public function clean()
    {
        foreach ($this->basket as $item) $item->delete();
        $this->basket->save();
        $this->arBasketItems = array();
    }

    public function add($intProductID, $intQuantity = 1)
    {
        if (!is_numeric($intQuantity)) $intQuantity = 1;
        $intQuantity = (int)$intQuantity;


        if ($item = $this->basket->getExistsItem('catalog', $intProductID)) {
            $intQuantity = $item->getQuantity() + $intQuantity;
            if ($intQuantity < 1) $item->delete();
            else $item->setField('QUANTITY', $intQuantity);

        } else {
            $item = $this->basket->createItem('catalog', $intProductID);
            $arFields = array(
                'QUANTITY' => $intQuantity,
                'CURRENCY' => CurrencyManager::getBaseCurrency(),
                'LID' => Context::getCurrent()->getSite(),
                'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
            );

            $item->setFields($arFields);
        }
        $this->basket->save();
        $this->getBasketItems(true);

        return $this->count();
    }

    public function getBasketItems($isRefresh = false)
    {
        if ($isRefresh !== true) $isRefresh = false;

        if (empty($this->arBasketItems) || $isRefresh) {
            $this->arBasketItems = array();
            foreach ($this->basket as $item) {
                $this->arBasketItems[$item->getProductId()] = array(
                    'NAME' => $item->getField('NAME'),
                    'QUANTITY' => (int)$item->getQuantity(),
                    'ID' => $item->getProductId()
                );
            }
        }

        return $this->arBasketItems;
    }

    public function getTotalSum()
    {
        $price = $this->basket->getPrice();
        if($price){
            return $price;
        }
    }

    public function count($isTotal = false)
    {
        $intCount = 0;
        foreach ($this->basket as $basketItem) {
            if ($isTotal) $intCount += $basketItem->getQuantity();
            else $intCount++;
        }

        return (int)$intCount;
    }

    public static function getData($dataCart)
    {
        try {
            $basket = null;
            $entity = null;

            if ($dataCart instanceof \Bitrix\Main\Event) {
                $entity = $dataCart->getParameter('ENTITY');
            } elseif (is_array($dataCart)) {
                // registerEventHandlerCompatible передаёт массив параметров события
                $entity = $dataCart['ENTITY'] ?? reset($dataCart);
            } elseif ($dataCart instanceof \Bitrix\Sale\Basket
                || $dataCart instanceof \Bitrix\Sale\BasketItem) {
                $entity = $dataCart;
            }

            if ($entity instanceof \Bitrix\Sale\Basket) {
                $basket = $entity;
            } elseif ($entity instanceof \Bitrix\Sale\BasketItem) {
                $basket = $entity->getCollection();
            }

            if ($basket instanceof \Bitrix\Sale\Basket) {
                self::syncFreePositions($basket);
            }
        } catch (\Throwable $e) {
            // Обработчик не должен ломать сохранение корзины
            AddMessage2Log('ldo.develop basket getData: ' . $e->getMessage());
        }
    }

    /**
     * Возвращает ID товаров новых позиций корзины (ещё не сохранённых, без ID).
     *
     * @param \Bitrix\Sale\Basket $basket
     * @return array
     */
    private static function getNewProductIds($basket): array
    {
        $productIds = [];

        foreach ($basket as $item) {
            if (!$item->getId()) {
                $productIds[] = (int)$item->getProductId();
            }
        }

        return $productIds;
    }


    public function removeOne($productId)
    {
        if ($item = $this->basket->getExistsItem('catalog', $productId)) {
            $newQuantity = $item->getQuantity() - 1;

            if ($newQuantity <= 0) {
                $item->delete();
            } else {
                $item->setField('QUANTITY', $newQuantity);
            }

            $this->basket->save();
            $this->getBasketItems(true);
        }
    }

    /**
     * Пересчитывает бесплатные позиции по всем правилам для переданной корзины
     * (правила — из таблицы ldo_marketing_free_positions).
     *
     * Метод вызывается из события OnSaleBasketBeforeSaved и изменяет корзину
     * НА МЕСТЕ: дополнительные loadItemsForFUser()/save() не выполняются.
     * Ранее вложенный save() добавлял бесплатную позицию, но внешний save()
     * (внутри которого выполнялся обработчик) затем удалял её как «лишнюю» в
     * getOriginalItemsValues() — из-за этого бесплатные позиции пропадали.
     * Правка на месте также убирает двойное сохранение и лишние запросы к БД.
     *
     * @param \Bitrix\Sale\Basket $currentBasket
     * @return void
     */
    public static function syncFreePositions($currentBasket)
    {
        if (self::$freePositionsSyncing) {
            return;
        }

        if (!is_object($currentBasket) || !($currentBasket instanceof \Bitrix\Sale\Basket)) {
            return;
        }

        // Корзина, привязанная к заказу, не пересчитывается.
        if (method_exists($currentBasket, 'getOrderId') && (int)$currentBasket->getOrderId() > 0) {
            return;
        }

        $siteId = '';
        if (method_exists($currentBasket, 'getSiteId')) {
            $siteId = (string)$currentBasket->getSiteId();
        }
        if ($siteId === '') {
            $siteId = (string)Context::getCurrent()->getSite();
        }
        if ($siteId === '') {
            $siteId = 's1';
        }

        $rules = Product::getFreePositionRulesBySite($siteId);
        if (empty($rules)) {
            return;
        }

        // Множество всех бесплатных товаров (их не учитываем при подсчёте).
        $freeIds = [];
        $preparedRules = [];

        foreach ($rules as $rule) {
            $productIds = Product::decodeFreePositionIds($rule['PRODUCT_IDS'] ?? '');
            if (empty($productIds)) {
                continue;
            }

            foreach ($productIds as $pid) {
                $freeIds[$pid] = $pid;
            }

            // Сопоставляем СТРОГО с указанными в настройках разделами (без
            // разворачивания в подразделы), иначе правило, заданное для
            // родительского раздела, ошибочно срабатывает на все подкатегории
            // (например «Блины»).
            $sectionIds = Product::decodeFreePositionIds($rule['SECTION_IDS'] ?? '');
            $preparedRules[] = [
                'IDS'      => $productIds,
                'SECTIONS' => $sectionIds,
                'PORTION'  => max(1, (int)($rule['PORTIONS'] ?? 1)),
            ];
        }

        if (empty($preparedRules)) {
            return;
        }

        // Текущее содержимое корзины (включая ещё не сохранённые позиции).
        $currentItems = [];
        $productIds   = [];

        foreach ($currentBasket as $item) {
            $productId = (int)$item->getProductId();
            $currentItems[] = [
                'PRODUCT_ID' => $productId,
                'QUANTITY'   => (float)$item->getQuantity(),
            ];

            if ($productId > 0 && !isset($freeIds[$productId])) {
                $productIds[$productId] = $productId;
            }
        }

        // Пакетно получаем разделы товаров (включая родителя для SKU) и
        // количество в упаковке — без запросов в цикле (N+1).
        $sectionsMap = Product::getElementsSectionsMap(array_values($productIds));
        $piecesMap   = Product::getElementsPropertyMap(
            array_values($productIds),
            Product::getCatalogIblockId(),
            'ATT_COUNT_ROLL'
        );

        // Требуемое количество каждого бесплатного товара (максимум по правилам).
        $required = [];

        foreach ($preparedRules as $rule) {
            if (empty($rule['SECTIONS'])) {
                // Правило без разделов не может быть сопоставлено — пропускаем,
                // чтобы не удалять уже добавленные бесплатные позиции.
                continue;
            }

            $sections = array_flip($rule['SECTIONS']);
            $totalPieces = 0;

            foreach ($currentItems as $item) {
                $productId = $item['PRODUCT_ID'];
                if ($productId <= 0 || isset($freeIds[$productId])) {
                    continue;
                }

                $productSections = $sectionsMap[$productId] ?? [];
                if (empty($productSections)) {
                    continue;
                }

                $matched = false;
                foreach ($productSections as $itemSectionId) {
                    if (isset($sections[(int)$itemSectionId])) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    continue;
                }

                $pieces = (int)($piecesMap[$productId] ?? 0);
                if ($pieces <= 0) {
                    $pieces = 1;
                }

                $totalPieces += $item['QUANTITY'] * $pieces;
            }

            $count = (int)floor($totalPieces / $rule['PORTION']);

            foreach ($rule['IDS'] as $freeProductId) {
                $freeProductId = (int)$freeProductId;
                if (!isset($required[$freeProductId]) || $required[$freeProductId] < $count) {
                    $required[$freeProductId] = $count;
                }
            }
        }

        // Применяем изменения прямо к переданной корзине: она будет сохранена
        // текущим вызовом Basket::save(), внутри которого мы находимся.
        self::$freePositionsSyncing = true;

        try {
            $currency = CurrencyManager::getBaseCurrency();

            foreach ($required as $freeProductId => $freeCount) {
                $item = $currentBasket->getExistsItem('catalog', $freeProductId);

                // Условие бесплатной позиции не выполнено — убираем её из корзины.
                if ($freeCount <= 0) {
                    if ($item) {
                        $item->delete();
                    }
                    continue;
                }

                // Желаемое количество — то, что сейчас в корзине: пользователь мог
                // увеличить бесплатную позицию, докупая дополнительные единицы.
                $desiredQty = $item ? (float)$item->getQuantity() : 0.0;

                // Меньше бесплатного количества быть не может — оно начисляется авто.
                $totalQty = max($desiredQty, (float)$freeCount);

                if (!$item) {
                    $item = $currentBasket->createItem('catalog', $freeProductId);
                    // Сразу помечаем цену ручной — иначе провайдер подставит цену
                    // каталога (у бесплатных позиций она может быть, напр., 1 ₽).
                    $item->setField('CUSTOM_PRICE', 'Y');
                    $item->setFields([
                        'QUANTITY' => $totalQty,
                        'CURRENCY' => $currency,
                        'LID'      => $siteId,
                        'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
                    ]);
                } elseif ((float)$item->getQuantity() !== $totalQty) {
                    $item->setField('QUANTITY', $totalQty);
                }

                // Платные единицы — всё, что сверх бесплатного количества.
                $paidQty = max(0.0, $totalQty - (float)$freeCount);

                if ($paidQty <= 0) {
                    // Вся позиция бесплатная — ручная нулевая цена.
                    self::markItemAsFree($item);
                    continue;
                }

                // Часть единиц бесплатно, часть — по цене каталога. Итоговая сумма
                // строки = платных единиц × цена каталога; цена за единицу — средняя.
                $basePrice = Product::getProductBasePrice((int)$freeProductId, $currency);
                $unitPrice = $basePrice > 0
                    ? round(($paidQty * $basePrice) / $totalQty, 2)
                    : 0.0;

                // ВРЕМЕННАЯ диагностика (удалить после отладки цен).
                @file_put_contents(
                    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/upload/ldo_free_debug.log',
                    date('c') . ' ' . json_encode([
                        'freeProductId' => (int)$freeProductId,
                        'freeCount'     => (float)$freeCount,
                        'desiredQty'    => $desiredQty,
                        'totalQty'      => $totalQty,
                        'paidQty'       => $paidQty,
                        'basePrice'     => $basePrice,
                        'unitPrice'     => $unitPrice,
                        'priceBefore'   => (float)$item->getPrice(),
                        'baseBefore'    => (float)$item->getField('BASE_PRICE'),
                    ], JSON_UNESCAPED_UNICODE) . "\n",
                    FILE_APPEND
                );

                $isCustom = ((string)$item->getField('CUSTOM_PRICE') === 'Y');
                $priceMatches = (abs((float)$item->getPrice() - $unitPrice) <= 0.001
                    && abs((float)$item->getField('BASE_PRICE') - $unitPrice) <= 0.001);

                if (!$isCustom || !$priceMatches) {
                    $item->setField('CUSTOM_PRICE', 'Y');
                    $item->setField('BASE_PRICE', $unitPrice);
                    $item->setField('DISCOUNT_PRICE', 0);
                    $item->setField('PRICE', $unitPrice);
                }
            }
        } finally {
            self::$freePositionsSyncing = false;
        }
    }



    /**
     * Помечает позицию корзины как бесплатную: цена задаётся вручную и равна 0,
     * чтобы провайдер каталога не подставил цену товара.
     *
     * @param \Bitrix\Sale\BasketItem $item
     * @return void
     */
    private static function markItemAsFree($item): void
    {
        if (!is_object($item) || !method_exists($item, 'setFields')) {
            return;
        }

        if ((string)$item->getField('CUSTOM_PRICE') === 'Y'
            && (float)$item->getPrice() === 0.0
            && (float)$item->getField('BASE_PRICE') === 0.0) {
            return;
        }

        // CUSTOM_PRICE задаём первым: он помечает PRICE как «ручную цену»,
        // и провайдер каталога уже не перезапишет её ценой товара.
        $item->setField('CUSTOM_PRICE', 'Y');
        $item->setField('BASE_PRICE', 0);
        $item->setField('DISCOUNT_PRICE', 0);
        $item->setField('PRICE', 0);
    }

    public function deleteItem($productId)
    {
        if ($item = $this->basket->getExistsItem('catalog', $productId)) {
            $item->delete();
            $this->basket->save();
            $this->getBasketItems(true);
        }
    }



}
