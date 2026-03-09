<?php
namespace Ldo\Develop;

use Bitrix\Main\Loader;
use CFile;

/**
 * Класс для работы с изображениями и генерации WebP формата
 */
class Pict {

    /** @var bool Флаг формата изображения (true - PNG, false - JPEG) */
    private static $isPng = true;

    /** @var array Массив с данными текущего обрабатываемого файла */
    private static $arFile = [];

    /**
     * Проверяет MIME-тип изображения
     * @param string $str MIME-тип изображения
     * @return bool true если формат поддерживается (PNG/JPEG)
     */
    private static function checkFormat(string $str): bool
    {
        $str = strtolower($str);
        if ($str === 'image/png') {
            self::$isPng = true;
            return true;
        } elseif ($str === 'image/jpeg' || $str === 'image/jpg' || $str === 'image/pjpeg') {
            self::$isPng = false;
            return true;
        }
        return false;
    }

    /**
     * Склеивает путь из массива, заменяя последний элемент на пустую строку
     * @param array $arr Массив частей пути
     * @return string Полученный путь
     */
    private static function implodeSrc(array $arr): string
    {
        if (empty($arr)) {
            return '';
        }
        $arr[count($arr) - 1] = '';
        return implode('/', $arr);
    }

    /**
     * Генерирует путь для сохранения WebP файла на основе исходного пути
     * @param string $str Исходный путь к изображению
     * @return string Путь для WebP файла
     */
    private static function generateSrc(string $str): string
    {
        if (empty($str)) {
            return '';
        }

        $arPath = explode('/', $str);

        // Проверяем наличие ключей в массиве
        if (isset($arPath[2]) && $arPath[2] === 'resize_cache') {
            $arPath = self::implodeSrc($arPath);
            return str_replace('resize_cache/iblock', 'webp/resize_cache', $arPath);
        } else {
            $arPath = self::implodeSrc($arPath);
            return str_replace('upload/iblock', 'upload/webp/iblock', $arPath);
        }
    }

    /**
     * Генерирует WebP версию изображения
     * @param int $intQuality Качество сжатия (1-100)
     * @return bool Успешность генерации
     */
    private static function generateWebp(int $intQuality = 100): bool
    {
        // Проверяем наличие необходимых данных
        if (empty(self::$arFile['CONTENT_TYPE']) || empty(self::$arFile['SRC'])) {
            return false;
        }

        // Проверяем поддерживается ли формат
        if (!self::checkFormat(self::$arFile['CONTENT_TYPE'])) {
            return false;
        }

        // Формируем путь для WebP
        self::$arFile['WEBP_PATH'] = self::generateSrc(self::$arFile['SRC']);

        if (empty(self::$arFile['WEBP_PATH'])) {
            return false;
        }

        // Формируем имя файла с заменой расширения на .webp
        if (isset(self::$arFile['FILE_NAME']) && !empty(self::$arFile['FILE_NAME'])) {
            $fileName = strtolower(self::$arFile['FILE_NAME']);

            if (self::$isPng) {
                self::$arFile['WEBP_FILE_NAME'] = str_replace('.png', '.webp', $fileName);
                // На случай если расширение в верхнем регистре
                self::$arFile['WEBP_FILE_NAME'] = str_replace('.PNG', '.webp', self::$arFile['WEBP_FILE_NAME']);
            } else {
                self::$arFile['WEBP_FILE_NAME'] = str_replace('.jpg', '.webp', $fileName);
                self::$arFile['WEBP_FILE_NAME'] = str_replace('.jpeg', '.webp', self::$arFile['WEBP_FILE_NAME']);
                self::$arFile['WEBP_FILE_NAME'] = str_replace('.JPG', '.webp', self::$arFile['WEBP_FILE_NAME']);
                self::$arFile['WEBP_FILE_NAME'] = str_replace('.JPEG', '.webp', self::$arFile['WEBP_FILE_NAME']);
            }
        } else {
            return false;
        }

        // Проверяем существование DOCUMENT_ROOT
        if (!isset($_SERVER['DOCUMENT_ROOT']) || empty($_SERVER['DOCUMENT_ROOT'])) {
            return false;
        }

        $documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
        $webpDir = $documentRoot . self::$arFile['WEBP_PATH'];
        $webpFile = $documentRoot . self::$arFile['WEBP_PATH'] . self::$arFile['WEBP_FILE_NAME'];

        // Создаем директорию если её нет
        if (!file_exists($webpDir)) {
            if (!mkdir($webpDir, 0777, true) && !is_dir($webpDir)) {
                return false;
            }
        }

        // Формируем полный путь к WebP файлу
        self::$arFile['WEBP_SRC'] = self::$arFile['WEBP_PATH'] . self::$arFile['WEBP_FILE_NAME'];

        // Если WebP файл уже существует - возвращаем успех
        if (file_exists($webpFile)) {
            return true;
        }

        // Проверяем существование исходного файла
        $sourceFile = $documentRoot . self::$arFile['SRC'];
        if (!file_exists($sourceFile)) {
            return false;
        }

        try {
            // Проверяем наличие функции imagewebp
            if (!function_exists('imagewebp')) {
                return false;
            }

            // Загружаем исходное изображение в зависимости от формата
            if (self::$isPng) {
                $im = @imagecreatefrompng($sourceFile);
            } else {
                $im = @imagecreatefromjpeg($sourceFile);
            }

            if ($im === false) {
                return false;
            }

            // Сохраняем альфа-канал для PNG
            if (self::$isPng) {
                imagealphablending($im, true);
                imagesavealpha($im, true);
            }

            // Создаём WebP файл
            $result = imagewebp($im, $webpFile, $intQuality);

            // Освобождаем память
            imagedestroy($im);

            if (!$result) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Получает путь к ресайзнутому изображению (оригинальный формат)
     * @param mixed $file ID файла или массив с данными файла
     * @param int $width Требуемая ширина
     * @param int $height Требуемая высота
     * @param bool $isProportional Сохранять пропорции
     * @param int $intQuality Качество сжатия
     * @return string Путь к изображению (всегда возвращает строку)
     */
    public static function getResizeSrc($file, int $width, int $height, bool $isProportional = true, int $intQuality = 100): string
    {
        self::$arFile = [];

        // Подключаем модуль main если он еще не подключен
        if (!Loader::includeModule('main')) {
            return '';
        }

        // Получаем данные файла если передан ID
        if (!is_array($file) && intval($file) > 0) {
            $fileData = CFile::GetFileArray($file);
            if ($fileData && is_array($fileData)) {
                self::$arFile = $fileData;
            }
        } elseif (is_array($file)) {
            self::$arFile = $file;
        }

        // Проверяем наличие необходимых данных
        if (empty(self::$arFile) || empty(self::$arFile['SRC'])) {
            return '';
        }

        // Получаем имя файла если его нет
        if (empty(self::$arFile['FILE_NAME']) && !empty(self::$arFile['SRC'])) {
            $pathParts = explode('/', self::$arFile['SRC']);
            self::$arFile['FILE_NAME'] = end($pathParts);
        }

        try {
            // Ресайзим изображение через стандартный метод Битрикса
            $resizedFile = CFile::ResizeImageGet(
                $file,
                ['width' => $width, 'height' => $height],
                ($isProportional ? BX_RESIZE_IMAGE_PROPORTIONAL : BX_RESIZE_IMAGE_EXACT),
                true,
                false,
                false,
                $intQuality
            );

            if ($resizedFile && is_array($resizedFile) && isset($resizedFile['src'])) {
                // Сохраняем результат
                self::$arFile['SRC'] = $resizedFile['src'];
                self::$arFile['WIDTH'] = $resizedFile['width'] ?? 0;
                self::$arFile['HEIGHT'] = $resizedFile['height'] ?? 0;

                return (string)$resizedFile['src'];
            }
        } catch (\Exception $e) {
            // Логируем ошибку если нужно
        }

        return '';
    }

    /**
     * Получает путь к ресайзнутому изображению в формате WebP
     * @param mixed $file ID файла или массив с данными файла
     * @param int $width Требуемая ширина
     * @param int $height Требуемая высота
     * @param bool $isProportional Сохранять пропорции
     * @param int $intQuality Качество сжатия
     * @return string Путь к WebP изображению (всегда возвращает строку)
     */
    public static function getResizeWebpSrc($file, int $width, int $height, bool $isProportional = true, int $intQuality = 100): string
    {
        // Сначала получаем обычное ресайзнутое изображение
        $src = self::getResizeSrc($file, $width, $height, $isProportional, $intQuality);

        // Если не удалось получить исходное изображение - возвращаем пустую строку
        if (empty($src)) {
            return '';
        }

        // Пытаемся сгенерировать WebP версию
        $webpGenerated = self::generateWebp($intQuality);

        // Если WebP сгенерирован успешно и путь существует - возвращаем его
        if ($webpGenerated && isset(self::$arFile['WEBP_SRC']) && !empty(self::$arFile['WEBP_SRC'])) {
            return (string)self::$arFile['WEBP_SRC'];
        }

        // Иначе возвращаем исходное изображение
        return $src;
    }

    /**
     * Проверяет поддерживает ли браузер WebP
     * @return bool
     */
    public static function isWebpSupported(): bool
    {
        static $supported = null;

        if ($supported === null) {
            $supported = isset($_SERVER['HTTP_ACCEPT']) &&
                is_string($_SERVER['HTTP_ACCEPT']) &&
                strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false;
        }

        return $supported;
    }

    /**
     * Возвращает оптимальный путь к изображению (WebP если поддерживается)
     * @param mixed $file ID файла или массив с данными файла
     * @param int $width Требуемая ширина
     * @param int $height Требуемая высота
     * @param bool $isProportional Сохранять пропорции
     * @param int $intQuality Качество сжатия
     * @return string Всегда возвращает строку
     */
    public static function getOptimalSrc($file, int $width, int $height, bool $isProportional = true, int $intQuality = 100): string
    {
        if (self::isWebpSupported()) {
            $webpSrc = self::getResizeWebpSrc($file, $width, $height, $isProportional, $intQuality);
            if (!empty($webpSrc)) {
                return $webpSrc;
            }
        }

        return self::getResizeSrc($file, $width, $height, $isProportional, $intQuality);
    }

    /**
     * Возвращает ширину последнего обработанного изображения
     * @return int
     */
    public static function getLastWidth(): int
    {
        return isset(self::$arFile['WIDTH']) ? (int)self::$arFile['WIDTH'] : 0;
    }

    /**
     * Возвращает высоту последнего обработанного изображения
     * @return int
     */
    public static function getLastHeight(): int
    {
        return isset(self::$arFile['HEIGHT']) ? (int)self::$arFile['HEIGHT'] : 0;
    }

    /**
     * Возвращает путь к последнему сгенерированному WebP изображению
     * @return string
     */
    public static function getLastWebpSrc(): string
    {
        return isset(self::$arFile['WEBP_SRC']) ? (string)self::$arFile['WEBP_SRC'] : '';
    }
}