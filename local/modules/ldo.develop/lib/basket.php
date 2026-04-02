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
        $dataBasket = $dataCart->getParameter("VALUES");
        $productId = $dataBasket['PRODUCT']['ID'];

        if ($productId) {
            // Проверяем, не является ли добавляемый товар сам бесплатным
            $freeProductsList = Product::checkInFreeCategoryProducts($productId);
            if ($freeProductsList !== false) {
                // Это бесплатный товар, не обрабатываем
                return;
            }

            $dataProduct = Product::getDataById($productId);
            $productSectionId = $dataProduct['IBLOCK_SECTION_ID'];

            if ($productSectionId) {
                $addFreeDopProduct = Product::checkInFreeCategoryProducts($productSectionId);
                if ($addFreeDopProduct) {
                    self::addFreePosition($addFreeDopProduct, $productSectionId);
                }
            }
        }
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
