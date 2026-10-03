<?php

namespace Ldo\Develop;

use Bitrix\Main\Loader;
use CFile;
use CIBlockElement;

/**
 * Агенты модуля ldo.develop.
 */
class Agents
{
    /** ID инфоблока каталога с товарами */
    public const IBLOCK_ID = 4;

    /** Код свойства инфоблока, в которое записывается путь к WebP */
    public const WEBP_PROPERTY = 'ATT_WEBP_PHOTO';

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
     * Выбирает 50 товаров, у которых свойство ATT_WEBP_PHOTO не заполнено,
     * конвертирует в WebP сначала PREVIEW_PICTURE, а если его нет — DETAIL_PICTURE,
     * и записывает полученный путь в свойство.
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
     * Генерирует WebP для товара и записывает путь в свойство.
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

        $fileArray = CFile::GetFileArray($fileId);

        if (!$fileArray || empty($fileArray['SRC'])) {
            return false;
        }

        // Берём собственные размеры файла, чтобы получить WebP исходного изображения
        $width = (int)($fileArray['WIDTH'] ?? 0);
        $height = (int)($fileArray['HEIGHT'] ?? 0);

        if ($width <= 0 || $height <= 0) {
            $width = 2000;
            $height = 2000;
        }

        Pict::getResizeWebpSrc($fileId, $width, $height, true, self::WEBP_QUALITY);

        $webpSrc = Pict::getLastWebpSrc();

        // Пишем только реально сгенерированный .webp, а не исходный файл
        if ($webpSrc === '' || !preg_match('/\.webp$/i', $webpSrc)) {
            return false;
        }

        CIBlockElement::SetPropertyValuesEx(
            $elementId,
            self::IBLOCK_ID,
            [self::WEBP_PROPERTY => $webpSrc]
        );

        return true;
    }
}
