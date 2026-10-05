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

            if ($dataCart instanceof \Bitrix\Main\Event) {
                $entity = $dataCart->getParameter('ENTITY');
                if ($entity instanceof \Bitrix\Sale\Basket) {
                    $basket = $entity;
                } elseif ($entity instanceof \Bitrix\Sale\BasketItem) {
                    $basket = $entity->getCollection();
                }
            } elseif ($dataCart instanceof \Bitrix\Sale\Basket) {
                $basket = $dataCart;
            } elseif ($dataCart instanceof \Bitrix\Sale\BasketItem) {
                $basket = $dataCart->getCollection();
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
     * Пересчитывает бесплатные позиции по всем правилам для текущего
     * содержимого корзины (правила — из таблицы ldo_marketing_free_positions).
     *
     * @param \Bitrix\Sale\Basket $currentBasket
     * @return void
     */
    public static function syncFreePositions($currentBasket)
    {
        if (self::$freePositionsSyncing) {
            return;
        }

        $siteId = '';
        if (is_object($currentBasket) && method_exists($currentBasket, 'getSiteId')) {
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

            $sectionIds = Product::decodeFreePositionIds($rule['SECTION_IDS'] ?? '');
            $preparedRules[] = [
                'IDS'      => $productIds,
                'SECTIONS' => Product::expandSectionIds($sectionIds),
                'PORTION'  => max(1, (int)($rule['PORTIONS'] ?? 1)),
            ];
        }

        if (empty($preparedRules)) {
            return;
        }

        // Текущее содержимое корзины (включая ещё не сохранённые позиции).
        $currentItems = [];
        foreach ($currentBasket as $item) {
            $currentItems[] = [
                'PRODUCT_ID' => (int)$item->getProductId(),
                'QUANTITY'   => (float)$item->getQuantity(),
            ];
        }

        // Требуемое количество каждого бесплатного товара.
        $required = [];

        foreach ($preparedRules as $rule) {
            $totalPieces = 0;

            foreach ($currentItems as $item) {
                $productId = $item['PRODUCT_ID'];
                if (isset($freeIds[$productId])) {
                    continue;
                }

                $dataProduct = Product::getDataById($productId);
                $sectionId = (int)($dataProduct['IBLOCK_SECTION_ID'] ?? 0);
                if (!$sectionId || !in_array($sectionId, $rule['SECTIONS'], true)) {
                    continue;
                }

                $pieces = 1;
                $dbProp = \CIBlockElement::GetProperty(4, $productId, [], ['CODE' => 'ATT_COUNT_ROLL']);
                if ($prop = $dbProp->Fetch()) {
                    $pieces = (int)$prop['VALUE'];
                    if ($pieces <= 0) {
                        $pieces = 1;
                    }
                }

                $totalPieces += $item['QUANTITY'] * $pieces;
            }

            $count = (int)floor($totalPieces / $rule['PORTION']);

            foreach ($rule['IDS'] as $freeProductId) {
                $required[$freeProductId] = $count;
            }
        }

        // Применяем изменения к сохранённой корзине пользователя.
        $userBasket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), $siteId);

        self::$freePositionsSyncing = true;

        try {
            $changed = false;

            foreach ($required as $freeProductId => $needCount) {
                $item = $userBasket->getExistsItem('catalog', $freeProductId);

                if ($needCount <= 0) {
                    if ($item) {
                        $item->delete();
                        $changed = true;
                    }
                    continue;
                }

                if ($item) {
                    if ((float)$item->getQuantity() !== (float)$needCount) {
                        $item->setField('QUANTITY', $needCount);
                        $changed = true;
                    }
                } else {
                    $newItem = $userBasket->createItem('catalog', $freeProductId);
                    $newItem->setFields([
                        'QUANTITY' => $needCount,
                        'CURRENCY' => CurrencyManager::getBaseCurrency(),
                        'LID'      => $siteId,
                        'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
                    ]);
                    $changed = true;
                }
            }

            if ($changed) {
                $userBasket->save();
            }
        } finally {
            self::$freePositionsSyncing = false;
        }
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
