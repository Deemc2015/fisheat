<?php

namespace Ldo\Develop;

use Bitrix\Main\Loader;
use CIBlockElement;

/**
 * Агенты модуля ldo.develop.
 */
class Agents
{
    /** ID инфоблока каталога с товарами */
    public const IBLOCK_ID = 4;

    /** Код свойства инфоблока для основного WebP (700x700) */
    public const WEBP_PROPERTY = 'ATT_WEBP_PHOTO';

    /** Код свойства инфоблока для превью WebP (280x280) */
    public const WEBP_PREV_PROPERTY = 'ATT_WEBP_PHOTO_PREV';

    /** Размер основного изображения для WebP */
    public const MAIN_SIZE = 700;

    /** Размер превью-изображения для WebP */
    public const PREV_SIZE = 280;

    /** Сколько товаров обрабатывать за один запуск агента */
    public const BATCH_SIZE = 50;

    /** Сколько товаров просматривать за один запуск (часть из них может быть без фото) */
    public const CANDIDATE_LIMIT = 300;

    /** Качество сжатия WebP */
    public const WEBP_QUALITY = 85;

    /** Интервал запуска агента в секундах (5 минут) */
    public const INTERVAL = 300;

    /**
     * Агент генерации WebP-версий изображений товаров.
     *
     * Выбирает товары, у которых свойство ATT_WEBP_PHOTO не заполнено. Источник фото —
     * PREVIEW_PICTURE, при отсутствии — DETAIL_PICTURE. Для каждого товара формируются
     * две WebP-версии: 700x700 -> ATT_WEBP_PHOTO и 280x280 -> ATT_WEBP_PHOTO_PREV.
     *
     * @return string Строка вызова агента, чтобы он продолжил работу
     */
    public static function run(): string
    {
        $agentCall = "\\Ldo\\Develop\\Agents::run();";

        if (!Loader::includeModule('iblock')) {
            return $agentCall;
        }

        $processed = 0;

        foreach (self::getCandidates() as $element) {
            if ($processed >= self::BATCH_SIZE) {
                break;
            }

            try {
                if (self::processElement((int)$element['ID'], $element)) {
                    $processed++;
                }
            } catch (\Throwable $e) {
                // Ошибка одного товара не должна останавливать весь агент
                AddMessage2Log('ldo.develop webp agent: ' . $e->getMessage());
            }
        }

        return $agentCall;
    }

    /**
     * Выбирает товары, у которых свойство ATT_WEBP_PHOTO не заполнено.
     *
     * Наличие картинки (PREVIEW_PICTURE/DETAIL_PICTURE) проверяется в PHP —
     * фильтрация файловых полей через CIBlockElement::GetList работает ненадёжно.
     *
     * @return array
     */
    protected static function getCandidates(): array
    {
        $elements = [];

        $res = CIBlockElement::GetList(
            ['ID' => 'ASC'],
            [
                'IBLOCK_ID' => self::IBLOCK_ID,
                'ACTIVE' => 'Y',
                // Свойство не заполнено
                'PROPERTY_' . self::WEBP_PROPERTY => false,
            ],
            false,
            ['nTopCount' => self::CANDIDATE_LIMIT],
            ['ID', 'IBLOCK_ID', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']
        );

        while ($element = $res->Fetch()) {
            $elements[] = $element;
        }


        return $elements;
    }

    /**
     * Генерирует WebP-версии товара и записывает пути в свойства.
     *
     * Основное изображение — 700x700, превью — 280x280
     * (пропорциональный ресайз средствами Битрикс).
     *
     * @param int   $elementId
     * @param array $element
     * @return bool
     */
    protected static function processElement(int $elementId, array $element): bool
    {
        // PREVIEW_PICTURE в приоритете, если пусто — берём DETAIL_PICTURE
        $fileId = (int)($element['PREVIEW_PICTURE'] ?: $element['DETAIL_PICTURE']);

        if ($fileId <= 0) {
            return false;
        }

        $webpMain = self::makeWebp($fileId, self::MAIN_SIZE);
        $webpPrev = self::makeWebp($fileId, self::PREV_SIZE);

        if ($webpMain === '' && $webpPrev === '') {
            return false;
        }

        $properties = [];

        if ($webpMain !== '') {
            $properties[self::WEBP_PROPERTY] = $webpMain;
        }

        if ($webpPrev !== '') {
            $properties[self::WEBP_PREV_PROPERTY] = $webpPrev;
        }

        CIBlockElement::SetPropertyValuesEx($elementId, self::IBLOCK_ID, $properties);

        return $webpMain !== '';
    }

    /**
     * Пропорционально ресайзит изображение средствами Битрикс и генерирует WebP.
     *
     * @param int $fileId ID файла
     * @param int $size   Сторона квадрата (ширина и высота)
     * @return string Путь к сгенерированному .webp либо пустая строка
     */
    protected static function makeWebp(int $fileId, int $size): string
    {
        // CFile::ResizeImageGet (внутри Pict) + генерация WebP классом Pict
        Pict::getResizeWebpSrc($fileId, $size, $size, true, self::WEBP_QUALITY);

        $webpSrc = Pict::getLastWebpSrc();

        // Пишем только реально сгенерированный .webp, а не исходный файл
        if ($webpSrc === '' || !preg_match('/\.webp$/i', $webpSrc)) {
            return '';
        }

        return $webpSrc;
    }
}
