<?php

namespace Ldo\Favorites;

use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Bitrix\Main\Loader;
use Ldo\Favorites\ClientStorage;

Loader::includeModule('iblock');


/**
 * Class Favorites
 * @package Siart
 */
class Favorites
{
    private const MODULE_ID = 'ldo.favorites';
    private static $arItems = array();

    /**
     * Возвращает массив ID избранных
     *
     * @return array
     */
    public static function getItems(): array
    {
        if (empty(self::$arItems)) {
            $strData = ClientStorage::get(self::MODULE_ID, serialize(array()));
            if (CheckSerializedData($strData)) {
                self::$arItems = unserialize($strData);
            }
        }

        return self::$arItems;
    }

    /**
     * Добавляет товар в избранное если его нет
     * удаляет из избранного если такой есть.
     *
     * @param int $intItemId ID товара
     * @return Result
     */
    public static function setItems(int $intItemId): Result
    {
        $result = new Result;

        if ((int)$intItemId > 0) {
            $arItems = self::getItems();
            if (!in_array($intItemId, $arItems)) {
                $arItems[] = $intItemId;

            } else {
                unset($arItems[array_search($intItemId, $arItems)]);
            }

            ClientStorage::set(self::MODULE_ID, serialize($arItems));
            self::$arItems = $arItems;

        } else {
            $error = new Error('Переданного товара не существует!');
            $result->addError($error);
        }

        $result->setData(array(
            'COUNT' => count(self::$arItems)
        ));

        return $result;
    }

    /**
     * Удаляет все товары из избранного.
     *
     * @return Result
     */
    public static function delete(): Result
    {
        ClientStorage::set(self::MODULE_ID, serialize(array()));

        $result = new Result;
        $result->setData(array(
            'COUNT' => 0
        ));

        return $result;
    }

    /**
     * Возвращает количество товаров в избранном
     *
     * @return Result
     */
    public static function getCount()
    {
        $arItems = self::getItems();

        return count($arItems);
    }

    /**
     * Определяет есть ли товар в избранном
     *
     * @param int $intItemId ID товара
     * @return bool
     */
    public static function isOnList(int $intItemId): bool
    {
        if ((int)$intItemId <= 0) $isResult = false;
        else {
            $arItems = self::getItems();
            $isResult = in_array($intItemId, $arItems);
        }

        return $isResult;
    }
}