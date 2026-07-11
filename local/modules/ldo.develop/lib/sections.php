<?php
namespace Ldo\Develop;

use Bitrix\Iblock\Model\Section;
use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\SystemException;
use Exception;

class Sections
{
    public static function getListViewIndex(string $iblockName): array
    {
        // КРИТИЧЕСКИ ВАЖНО: проверяем, загружен ли модуль iblock
        if (!Loader::includeModule('iblock')) {
            // Если модуль не загружен, возвращаем пустой массив
            // или можно выбросить исключение, но лучше вернуть пустой массив,
            // чтобы сайт продолжал работать
            return [];
        }

        try {
            $iblock = IblockTable::query()
                ->where('API_CODE', $iblockName)
                ->fetchObject();

            // Проверяем, найден ли инфоблок
            if (!$iblock) {
                return [];
            }

            $sectionClass = Section::compileEntityByIblock($iblock);

            $section = $sectionClass::query()
                ->setSelect(['ID', 'UF_VIEW_INDEX'])
                ->where('UF_VIEW_INDEX', 1)
                ->where('DEPTH_LEVEL', 1)
                ->fetchAll();

            if (count($section) > 0) {
                return $section;
            }

            return [];

        } catch (Exception $e) {
            // Лучше логировать ошибку, а не выводить на экран
            // echo 'Ошибка: ' . $e->getMessage();
            // Для отладки можно использовать:
            // AddMessage2Log('Ошибка в Sections::getListViewIndex: ' . $e->getMessage(), 'ldo.develop');
            return [];
        }
    }
}


