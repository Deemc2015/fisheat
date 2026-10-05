<?php

namespace Prokhorov\Api\Helpers;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Iblock\InheritedProperty\ElementValues;
use Bitrix\Iblock\InheritedProperty\SectionValues;

class Seo
{
    public static function getParams(int $iblockId, int $elementId): array
    {
        $cache = Cache::createInstance();
        $cacheId = 'seo_params_' . $iblockId . '_' . $elementId;
        $cacheDir = '/prokhorov/api/seo/';
        $taggedCache = Application::getInstance()->getTaggedCache();

        if ($cache->initCache(3600, $cacheId, $cacheDir)) {
            return $cache->getVars() ?: [];
        }

        if ($cache->startDataCache()) {
            $ipropValues = new ElementValues($iblockId, $elementId);
            $values = $ipropValues->getValues();

            $result = [
                'title' => $values['ELEMENT_META_TITLE'] ?? '',
                'description' => $values['ELEMENT_META_DESCRIPTION'] ?? '',
                'keywords' => $values['ELEMENT_META_KEYWORDS'] ?? '',
            ];

            // Тегированный кеш - автоматически очищается при изменении элемента
            $taggedCache->startTagCache($cacheDir);
            $taggedCache->registerTag('iblock_' . $iblockId);
            $taggedCache->registerTag('element_' . $elementId);
            $taggedCache->endTagCache();

            $cache->endDataCache($result);
            return $result;
        }

        return [];
    }

    /**
     * SEO-параметры раздела инфоблока (meta title/description/keywords).
     *
     * @param int $iblockId
     * @param int $sectionId
     * @return array{title: string, description: string, keywords: string}
     */
    public static function getSectionParams(int $iblockId, int $sectionId): array
    {
        $cache = Cache::createInstance();
        $cacheId = 'seo_section_params_' . $iblockId . '_' . $sectionId;
        $cacheDir = '/prokhorov/api/seo/';
        $taggedCache = Application::getInstance()->getTaggedCache();

        if ($cache->initCache(3600, $cacheId, $cacheDir)) {
            return $cache->getVars() ?: [];
        }

        if ($cache->startDataCache()) {
            $ipropValues = new SectionValues($iblockId, $sectionId);
            $values = $ipropValues->getValues();

            $result = [
                'title' => $values['SECTION_META_TITLE'] ?? '',
                'description' => $values['SECTION_META_DESCRIPTION'] ?? '',
                'keywords' => $values['SECTION_META_KEYWORDS'] ?? '',
            ];

            // Тегированный кеш - автоматически очищается при изменении раздела
            $taggedCache->startTagCache($cacheDir);
            $taggedCache->registerTag('iblock_' . $iblockId);
            $taggedCache->registerTag('section_' . $sectionId);
            $taggedCache->endTagCache();

            $cache->endDataCache($result);
            return $result;
        }

        return [];
    }
}
