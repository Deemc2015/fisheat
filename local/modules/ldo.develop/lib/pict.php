<?php
namespace Ldo\Develop;

/**
 * Класс для работы с изображениями и генерации WebP формата
 */
class Pict {

    /** @var bool Флаг формата изображения (true - PNG, false - JPEG) */
    private static $isPng = true;

    /** @var array Массив с данными текущего обрабатываемого файла */
    private static $arFile = Array();

    /**
     * Проверяет MIME-тип изображения
     * @param string $str MIME-тип изображения
     * @return bool true если формат поддерживается (PNG/JPEG)
     */
    private static function checkFormat(string $str):bool
    {
        if ($str === 'image/png')
        {
            self::$isPng = true;
            return true;
        }
        elseif ($str === 'image/jpeg')
        {
            self::$isPng = false;
            return true;
        }
        else return false;
    }

    /**
     * Склеивает путь из массива, заменяя последний элемент на пустую строку
     * @param array $arr Массив частей пути
     * @return string Полученный путь
     */
    private static function implodeSrc(array $arr):string
    {
        $arr[count($arr) - 1] = '';
        return implode('/', $arr);
    }

    /**
     * Генерирует путь для сохранения WebP файла на основе исходного пути
     * @param string $str Исходный путь к изображению
     * @return string Путь для WebP файла
     */
    private static function generateSrc(string $str):string
    {
        $arPath = explode('/', $str);

        if ($arPath[2] === 'resize_cache')
        {
            // Для ресайзнутых изображений
            $arPath = self::implodeSrc($arPath);
            return str_replace('resize_cache/iblock', 'webp/resize_cache', $arPath);
        }
        else
        {
            // Для оригинальных изображений
            $arPath = self::implodeSrc($arPath);
            return str_replace('upload/iblock', 'upload/webp/iblock', $arPath);
        }
    }

    /**
     * Генерирует WebP версию изображения
     * @param int $intQuality Качество сжатия (1-100)
     */
    private static function generateWebp(int $intQuality = 100):void
    {
        // Проверяем поддерживается ли формат
        if (self::checkFormat(self::$arFile['CONTENT_TYPE']))
        {
            // Формируем путь для WebP
            self::$arFile['WEBP_PATH'] = self::generateSrc(self::$arFile['SRC']);

            // Формируем имя файла с заменой расширения на .webp
            if (self::$isPng)
            {
                self::$arFile['WEBP_FILE_NAME'] = str_replace('.png', '.webp', strtolower(self::$arFile['FILE_NAME']));
            }
            else
            {
                self::$arFile['WEBP_FILE_NAME'] = str_replace('.jpg', '.webp', strtolower(self::$arFile['FILE_NAME']));
                self::$arFile['WEBP_FILE_NAME'] = str_replace('.jpeg', '.webp', strtolower(self::$arFile['WEBP_FILE_NAME']));
            }

            // Создаем директорию если её нет
            if (!file_exists($_SERVER['DOCUMENT_ROOT'] . self::$arFile['WEBP_PATH']))
            {
                mkdir($_SERVER['DOCUMENT_ROOT'] . self::$arFile['WEBP_PATH'], 0777, true);
            }

            // Формируем полный путь к WebP файлу
            self::$arFile['WEBP_SRC'] = self::$arFile['WEBP_PATH'] . self::$arFile['WEBP_FILE_NAME'];

            // Если WebP файл ещё не существует - создаём
            if (!file_exists($_SERVER['DOCUMENT_ROOT'] . self::$arFile['WEBP_SRC']))
            {
                // Загружаем исходное изображение в зависимости от формата
                if (self::$isPng)
                {
                    $im = imagecreatefrompng($_SERVER['DOCUMENT_ROOT'] . self::$arFile['SRC']);
                }
                else
                {
                    $im = imagecreatefromjpeg($_SERVER['DOCUMENT_ROOT'] . self::$arFile['SRC']);
                }

                // Создаём WebP файл
                imagewebp($im, $_SERVER['DOCUMENT_ROOT'] . self::$arFile['WEBP_SRC'], $intQuality);

                // Освобождаем память
                imagedestroy($im);
            }
        }
    }

    /**
     * Получает путь к ресайзнутому изображению (оригинальный формат)
     * @param mixed $file ID файла или массив с данными файла
     * @param int $width Требуемая ширина
     * @param int $height Требуемая высота
     * @param bool $isProportional Сохранять пропорции
     * @param int $intQuality Качество сжатия
     * @return string Путь к изображению
     */
    public static function getResizeSrc($file, int $width, int $height, bool $isProportional = true, int $intQuality = 100):string
    {
        self::$arFile = Array();

        // Получаем данные файла если передан ID
        if (!is_array($file) && intval($file) > 0)
        {
            self::$arFile = CFile::GetFileArray($file);
        }
        else
        {
            self::$arFile = $file;
        }

        // Получаем имя файла если его нет
        if (!self::$arFile['FILE_NAME'])
        {
            self::$arFile['FILE_NAME'] = array_pop(explode('/', self::$arFile['SRC']));
        }

        // Ресайзим изображение через стандартный метод Битрикса
        $file = CFile::ResizeImageGet($file, array('width' => $width, 'height' => $height), ($isProportional ? BX_RESIZE_IMAGE_PROPORTIONAL : BX_RESIZE_IMAGE_EXACT), true, false, false, $intQuality);

        // Сохраняем результат
        self::$arFile['SRC'] = $file['src'];
        self::$arFile['WIDTH'] = $file['width'];
        self::$arFile['HEIGHT'] = $file['height'];

        return self::$arFile['SRC'];
    }

    /**
     * Получает путь к ресайзнутому изображению в формате WebP
     * @param mixed $file ID файла или массив с данными файла
     * @param int $width Требуемая ширина
     * @param int $height Требуемая высота
     * @param bool $isProportional Сохранять пропорции
     * @param int $intQuality Качество сжатия
     * @return string Путь к WebP изображению
     */
    public static function getResizeWebpSrc($file, int $width, int $height, bool $isProportional = true, int $intQuality = 100):string
    {
        // Сначала получаем обычное ресайзнутое изображение
        self::getResizeSrc($file, $width, $height, $isProportional, $intQuality);

        // Генерируем WebP версию
        self::generateWebp($intQuality);

        return self::$arFile['WEBP_SRC'];
    }

    /**
     * Возвращает ширину последнего обработанного изображения
     * @return int
     */
    public static function getLastWidth():int
    {
        return (int)self::$arFile['WIDTH'];
    }

    /**
     * Возвращает высоту последнего обработанного изображения
     * @return int
     */
    public static function getLastHeight():int
    {
        return (int)self::$arFile['HEIGHT'];
    }
}