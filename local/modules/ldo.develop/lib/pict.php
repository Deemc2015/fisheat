<?php
namespace Ldo\Develop;

class Pict {

    private static $isPng = true;

    private static $arFile = Array();

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

    private static function implodeSrc(array $arr):string
    {
        $arr[count($arr) - 1] = '';

        return implode('/', $arr);
    }

    private static function generateSrc(string $str):string
    {
        $arPath = explode('/', $str);

        if ($arPath[2] === 'resize_cache')
        {
            $arPath = self::implodeSrc($arPath);

            return str_replace('resize_cache/iblock', 'webp/resize_cache', $arPath);
        }
        else
        {
            $arPath = self::implodeSrc($arPath);

            return str_replace('upload/iblock', 'upload/webp/iblock', $arPath);
        }
    }

    private static function generateWebp(int $intQuality = 100):void
    {
        if (self::checkFormat(self::$arFile['CONTENT_TYPE']))
        {
            self::$arFile['WEBP_PATH'] = self::generateSrc(self::$arFile['SRC']);

            if (self::$isPng)
            {
                self::$arFile['WEBP_FILE_NAME'] = str_replace('.png', '.webp', strtolower(self::$arFile['FILE_NAME']));
            }
            else
            {
                self::$arFile['WEBP_FILE_NAME'] = str_replace('.jpg', '.webp', strtolower(self::$arFile['FILE_NAME']));
                self::$arFile['WEBP_FILE_NAME'] = str_replace('.jpeg', '.webp', strtolower(self::$arFile['WEBP_FILE_NAME']));
            }

            if (!file_exists($_SERVER['DOCUMENT_ROOT'] . self::$arFile['WEBP_PATH']))
            {
                mkdir($_SERVER['DOCUMENT_ROOT'] . self::$arFile['WEBP_PATH'], 0777, true);
            }

            self::$arFile['WEBP_SRC'] = self::$arFile['WEBP_PATH'] . self::$arFile['WEBP_FILE_NAME'];

            if (!file_exists($_SERVER['DOCUMENT_ROOT'] . self::$arFile['WEBP_SRC']))
            {
                if (self::$isPng)
                {
                    $im = imagecreatefrompng($_SERVER['DOCUMENT_ROOT'] . self::$arFile['SRC']);
                }
                else
                {
                    $im = imagecreatefromjpeg($_SERVER['DOCUMENT_ROOT'] . self::$arFile['SRC']);
                }

                imagewebp($im, $_SERVER['DOCUMENT_ROOT'] . self::$arFile['WEBP_SRC'], $intQuality);

                imagedestroy($im);
            }
        }
    }

    public static function getResizeSrc($file, int $width, int $height, bool $isProportional = true, int $intQuality = 100):string
    {
        self::$arFile = Array();

        if (!is_array($file) && intval($file) > 0)
        {
            self::$arFile = CFile::GetFileArray($file);
        }
        else
        {
            self::$arFile = $file;
        }

        if (!self::$arFile['FILE_NAME'])
        {
            self::$arFile['FILE_NAME'] = array_pop(explode('/', self::$arFile['SRC']));
        }

        $file = CFile::ResizeImageGet($file, array('width' => $width, 'height' => $height), ($isProportional ? BX_RESIZE_IMAGE_PROPORTIONAL : BX_RESIZE_IMAGE_EXACT), true, false, false, $intQuality);

        self::$arFile['SRC'] = $file['src'];
        self::$arFile['WIDTH'] = $file['width'];
        self::$arFile['HEIGHT'] = $file['height'];

        return self::$arFile['SRC'];
    }

    public static function getResizeWebpSrc($file, int $width, int $height, bool $isProportional = true, int $intQuality = 100):string
    {
        self::getResizeSrc($file, $width, $height, $isProportional, $intQuality);

        self::generateWebp($intQuality);

        return self::$arFile['WEBP_SRC'];
    }

    public static function getLastWidth():int
    {
        return (int)self::$arFile['WIDTH'];
    }

    public static function getLastHeight():int
    {
        return (int)self::$arFile['HEIGHT'];
    }
}