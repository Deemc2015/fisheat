<?php
namespace Ldo\Marketing;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;

/**
 * Настройки модуля ldo.marketing.
 *
 * Ключевая настройка — ID инфоблока каталога для текущего сайта.
 * Используется для подтягивания фото и других данных товаров
 * (подарки и бесплатные позиции).
 */
class Settings
{
    /** Код модуля. */
    const MODULE_ID = 'ldo.marketing';

    /** Префикс ключа опции с ID инфоблока каталога (далее — ID сайта). */
    const OPTION_CATALOG_IBLOCK = 'catalog_iblock_id_';

    /** Значение по умолчанию на случай, если настройка не задана. */
    const DEFAULT_CATALOG_IBLOCK_ID = 4;

    /**
     * ID инфоблока каталога для сайта.
     *
     * Порядок определения:
     * 1) сохранённая настройка для сайта;
     * 2) автоопределение инфоблока с API_CODE = catalog;
     * 3) значение по умолчанию.
     *
     * @param string|null $siteId
     * @return int
     */
    public static function getCatalogIblockId($siteId = null): int
    {
        if ($siteId === null) {
            $siteId = (string)Context::getCurrent()->getSite();
        }
        $siteId = (string)$siteId;

        if ($siteId !== '') {
            $val = (int)Option::get(self::MODULE_ID, self::OPTION_CATALOG_IBLOCK . $siteId, '0');
            if ($val > 0) {
                return $val;
            }
        }

        // Фолбэк 1: автоопределение инфоблока каталога по API_CODE.
        if (Loader::includeModule('iblock')) {
            $rs = \CIBlock::GetList([], ['API_CODE' => 'catalog', 'CHECK_PERMISSIONS' => 'N']);
            if ($ib = $rs->Fetch()) {
                return (int)$ib['ID'];
            }
        }

        // Фолбэк 2: историческое значение.
        return self::DEFAULT_CATALOG_IBLOCK_ID;
    }

    /**
     * Сохранение ID инфоблока каталога для сайта.
     *
     * @param string $siteId
     * @param int $iblockId
     * @return void
     */
    public static function setCatalogIblockId($siteId, $iblockId)
    {
        Option::set(
            self::MODULE_ID,
            self::OPTION_CATALOG_IBLOCK . (string)$siteId,
            (string)(int)$iblockId
        );
    }
}
