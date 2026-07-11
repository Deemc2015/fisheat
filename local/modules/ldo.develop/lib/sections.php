<?php
namespace Ldo\Develop;

use Bitrix\Iblock\Model\Section;
use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Data\Cache;
use Exception;

class Sections
{
    public static function getListViewIndex(string $iblockName): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        $cacheId = 'sections_list_view_index_' . $iblockName;
        $cachePath = '/ldo/develop/sections/';
        $cache = Cache::createInstance();

        if ($cache->initCache(3600, $cacheId, $cachePath)) {
            return $cache->getVars();
        }

        try {
            $iblock = IblockTable::query()
                ->where('API_CODE', $iblockName)
                ->fetchObject();

            if (!$iblock) {
                return [];
            }

            $sectionClass = Section::compileEntityByIblock($iblock);

            $sections = $sectionClass::query()
                ->setSelect(['ID', 'UF_VIEW_INDEX'])
                ->where('UF_VIEW_INDEX', 1)
                ->where('DEPTH_LEVEL', 1)
                ->fetchAll();

            $result = $sections ?: [];

            // Сохраняем в кеш
            $cache->startDataCache();
            $cache->endDataCache($result);

            return $result;

        } catch (Exception $e) {
            if (class_exists('\\Bitrix\\Main\\Diag\\Debug')) {
                \Bitrix\Main\Diag\Debug::writeToLog(
                    'Ошибка в Sections::getListViewIndex: ' . $e->getMessage(),
                    'ldo.develop.error'
                );
            }
            return [];
        }
    }
}