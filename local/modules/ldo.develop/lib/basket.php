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
            $productIds = [];

            if ($dataCart instanceof \Bitrix\Main\Event) {
                $values = (array)$dataCart->getParameter('VALUES');
                if (!empty($values['PRODUCT']['ID'])) {
                    $productIds[] = (int)$values['PRODUCT']['ID'];
                }

                $entity = $dataCart->getParameter('ENTITY');
                if ($entity instanceof \Bitrix\Sale\BasketItem) {
                    $productIds[] = (int)$entity->getProductId();
                } elseif ($entity instanceof \Bitrix\Sale\Basket) {
                    $productIds = array_merge($productIds, self::getNewProductIds($entity));
                }
            } elseif ($dataCart instanceof \Bitrix\Sale\BasketItem) {
                $productIds[] = (int)$dataCart->getProductId();
            } elseif ($dataCart instanceof \Bitrix\Sale\Basket) {
                // Событие OnSaleBasketBeforeSaved передаёт корзину целиком,
                // поэтому берём только новые позиции (ещё без ID).
                $productIds = self::getNewProductIds($dataCart);
            }

            foreach (array_unique(array_filter($productIds)) as $productId) {
                // Проверяем, не является ли добавляемый товар сам бесплатным

                if (Product::isFreeProduct($productId)) {
                    // Это бесплатный товар, не обрабатываем
                    continue;
                }

                $dataProduct = Product::getDataById($productId);
                $productSectionId = $dataProduct['IBLOCK_SECTION_ID'] ?? 0;

                if ($productSectionId) {
                    $freeRules = Product::checkInFreeCategoryProducts($productSectionId);
                    foreach ($freeRules as $freeRule) {
                        self::addFreePosition($freeRule, $productSectionId);
                    }
                }
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

    public static function addFreePosition($freeProductsData, $categoryId)
    {
        $userBasket = new Basket();
        $basketItems = $userBasket->getBasketItems(true);

        // 1. Считаем общее количество ШТУК в корзине по нужной категории
        $totalPieces = 0;

        foreach ($basketItems as $productId => $item) {
            // Пропускаем сами бесплатные товары при подсчёте!
            if (in_array($productId, $freeProductsData['IDS'])) {
                continue;
            }

            $productData = Product::getDataById($productId);
            $productCategoryId = $productData['IBLOCK_SECTION_ID'] ?? 0;

            if ($productCategoryId == $categoryId) {
                $pieces = 1;
                $dbProp = \CIBlockElement::GetProperty(4, $productId, [], ['CODE' => 'ATT_COUNT_ROLL']);
                if ($prop = $dbProp->Fetch()) {
                    $pieces = (int)$prop['VALUE'];
                    if ($pieces <= 0) $pieces = 1;
                }
                $totalPieces += $item['QUANTITY'] * $pieces;
            }
        }

        $portion = $freeProductsData['PORTION'];
        $requiredCount = floor($totalPieces / $portion);

        // 2. Синхронизируем бесплатные товары
        foreach ($freeProductsData['IDS'] as $freeProductId) {
            $currentCount = $basketItems[$freeProductId]['QUANTITY'] ?? 0;

            if ($requiredCount > $currentCount) {
                $needToAdd = $requiredCount - $currentCount;
                for ($i = 0; $i < $needToAdd; $i++) {
                    $userBasket->add($freeProductId, 1);
                }
            } elseif ($requiredCount < $currentCount) {
                $needToRemove = $currentCount - $requiredCount;
                for ($i = 0; $i < $needToRemove; $i++) {
                    $userBasket->removeOne($freeProductId);
                }
            }
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
