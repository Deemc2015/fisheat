<?php

namespace Keyup\Cleartrafic;

use Bitrix\Main\Loader;
use Bitrix\Main\SystemException;

/**
 * Разбор загруженных текстовых файлов со списками.
 *
 * Файл сохраняется средствами модуля «file», содержимое разбирается построчно:
 *  - маски подсетей — записи вида «IP/маска»;
 *  - списки IP — по одному адресу в строке.
 *
 * Пустые строки и строки, начинающиеся с символа-комментария, пропускаются.
 */
class Import
{
    /** Максимальное количество записей, разбираемых из одного файла */
    public const MAX_ITEMS = 5000;

    private $idFiles;
    private $pathFiles;
    private $contentFiles;
    private $invalidCount = 0;
    private $duplicateCount = 0;

    /**
     * @param array $file Элемент $_FILES
     *
     * @throws SystemException
     */
    public function __construct($file)
    {
        Loader::IncludeModule('file');

        $this->checkFormat($file);
        $this->idFiles = $this->saveFile($file);
        $this->pathFiles = $this->getPath($this->idFiles);
        $this->contentFiles = $this->getContents($this->pathFiles);
    }

    /**
     * Список масок подсетей из файла.
     *
     * @param string $params Символ комментария: строки, начинающиеся с него, пропускаются
     *
     * @return array Список вида [['IP' => '10.0.0.0', 'MASKA' => 8], ...]
     */
    public function getMascList($params = '')
    {
        $maskList = [];
        $seen = [];

        $this->invalidCount = 0;
        $this->duplicateCount = 0;

        foreach ($this->getLines($params) as $line) {
            $parts = explode('/', $line);
            $ip = isset($parts[0]) ? trim($parts[0]) : '';
            $mask = isset($parts[1]) ? trim($parts[1]) : '';

            if (!$this->validateIPandMasc($ip, $mask)) {
                $this->invalidCount++;
                continue;
            }

            $key = $ip . '/' . (int)$mask;

            /* Дубликаты внутри файла не размножаем */
            if (isset($seen[$key])) {
                $this->duplicateCount++;
                continue;
            }

            $seen[$key] = true;
            $maskList[] = ['IP' => $ip, 'MASKA' => (int)$mask];

            if (count($maskList) >= self::MAX_ITEMS) {
                break;
            }
        }

        return $maskList;
    }

    /**
     * Список IP-адресов из файла.
     *
     * @param string $params Символ комментария: строки, начинающиеся с него, пропускаются
     *
     * @return array Список вида [['IP' => '127.0.0.1'], ...]
     */
    public function getIpList($params = '')
    {
        $ipList = [];
        $seen = [];

        $this->invalidCount = 0;
        $this->duplicateCount = 0;

        foreach ($this->getLines($params) as $line) {
            if (filter_var($line, FILTER_VALIDATE_IP) === false) {
                $this->invalidCount++;
                continue;
            }

            /* Дубликаты внутри файла не размножаем */
            if (isset($seen[$line])) {
                $this->duplicateCount++;
                continue;
            }

            $seen[$line] = true;
            $ipList[] = ['IP' => $line];

            if (count($ipList) >= self::MAX_ITEMS) {
                break;
            }
        }

        return $ipList;
    }

    /**
     * Список произвольных строк из файла: например фрагменты User-Agent.
     *
     * @param string $params Символ комментария: строки, начинающиеся с него, пропускаются
     *
     * @return array Список вида [['VALUE' => 'AhrefsBot'], ...]
     */
    public function getTextList($params = '')
    {
        $list = [];
        $seen = [];

        $this->invalidCount = 0;
        $this->duplicateCount = 0;

        foreach ($this->getLines($params) as $line) {
            /* Значение правила ограничено 255 символами */
            if (mb_strlen($line) > 255) {
                $this->invalidCount++;
                continue;
            }

            $key = mb_strtolower($line);

            /* Дубликаты внутри файла не размножаем */
            if (isset($seen[$key])) {
                $this->duplicateCount++;
                continue;
            }

            $seen[$key] = true;
            $list[] = ['VALUE' => $line];

            if (count($list) >= self::MAX_ITEMS) {
                break;
            }
        }

        return $list;
    }

    /**
     * Количество некорректных строк в последнем разобранном файле.
     *
     * @return int
     */
    public function getInvalidCount()
    {
        return $this->invalidCount;
    }

    /**
     * Количество дубликатов внутри последнего разобранного файла.
     *
     * @return int
     */
    public function getDuplicateCount()
    {
        return $this->duplicateCount;
    }

    /**
     * Значимые строки файла: без пустых строк и комментариев.
     *
     * @param string $params
     *
     * @return array
     *
     * @throws SystemException
     */
    private function getLines($params = '')
    {
        if (!$this->contentFiles) {
            throw new SystemException('Не передано содержимое файла');
        }

        $lines = preg_split('/\r\n|\r|\n/', (string)$this->contentFiles);

        if (!is_array($lines)) {
            throw new SystemException('Ошибка при формировании массива из файла');
        }

        $result = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            /* Комментарии: строка начинается с указанного символа */
            if ($params !== '' && strpos($line, (string)$params) === 0) {
                continue;
            }

            $result[] = $line;
        }

        return $result;
    }

    /**
     * @param string $ip
     * @param string $mask
     *
     * @return bool
     */
    private function validateIPandMasc($ip, $mask)
    {
        /* Сопоставление масок построено на ip2long(), поэтому принимаются только IPv4-адреса */
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        return (bool)preg_match('/^\d{1,2}$/', (string)$mask) && (int)$mask <= 32;
    }

    /**
     * @param array $file
     *
     * @return void
     *
     * @throws SystemException
     */
    private function checkFormat($file)
    {
        $name = isset($file['name']) ? (string)$file['name'] : '';

        if (mb_strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'txt') {
            throw new SystemException('Неверный формат файла: требуется .txt');
        }
    }

    /**
     * @param array $file
     *
     * @return int
     *
     * @throws SystemException
     */
    private function saveFile($file)
    {
        $id = \CFile::SaveFile($file, 'errorform');

        if (!($id > 0)) {
            throw new SystemException('Ошибка при сохранении файла');
        }

        return $id;
    }

    /**
     * @param int $id
     *
     * @return string
     *
     * @throws SystemException
     */
    private function getPath($id)
    {
        $pathFile = \CFile::GetPath($id);

        if (empty($pathFile)) {
            throw new SystemException('Ошибка при получении пути файла');
        }

        return $pathFile;
    }

    /**
     * @param string $path
     *
     * @return string
     *
     * @throws SystemException
     */
    private function getContents($path)
    {
        $content = file_get_contents($_SERVER['DOCUMENT_ROOT'] . $path);

        if ($content === false) {
            throw new SystemException('Ошибка при чтении файла');
        }

        return $content;
    }
}
