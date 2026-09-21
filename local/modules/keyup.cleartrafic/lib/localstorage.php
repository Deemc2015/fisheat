<?php


namespace Keyup\Cleartrafic;

use Bitrix\Main\Application;

/*Класс для работы с локальным хранилищем сессий*/
class LocalStorage
{
    public function addIp($ip){

        // Получаем доступ к локальному хранилищу сессии
        $localStorage = Application::getInstance()->getLocalSession('ipListing');

        // Получаем сохраненные данные IP-адресов, если они есть
        $storedData = $localStorage->getData();

        // Проверяем, не содержит ли массив уже данный IP-адрес
        if (!in_array($ip, $storedData, true))
        {
            // Если IP-адрес еще не добавлен, то добавляем его в массив
            $storedData[] = $ip;

            // Сохраняем обновленный массив в локальном хранилище
            $resultAdd = $localStorage->setData($storedData);

            return $ip;
        }

    }

    /*Добавляем метку бота*/
    public static function addBotsMarker($ip){

        $localStorage = Application::getInstance()->getLocalSession('ipBlock');
        // Получаем сохраненные данные IP-адресов, если они есть
        $storedData = $localStorage->getData();

        // Проверяем, не содержит ли массив уже данный IP-адрес
        if (!in_array($ip, $storedData, true))
        {
            // Если IP-адрес еще не добавлен, то добавляем его в массив
            $storedData[] = $ip;

            // Сохраняем обновленный массив в локальном хранилище
            $resultAdd = $localStorage->setData($storedData);

            return $ip;
        }
    }

    /*Проверка IP  списке подозрений на бота*/
    public static function checkBots($ip = null){

        if(!$ip)
        {
            $ip = self::getIp();
        }

        //Проверяем успешное прохождение каптчи
        if(self::getWhiteStatus($ip))
        {

            return false;
        }
        else{
            $localStorage = Application::getInstance()->getLocalSession('ipBlock');

            // Получаем сохраненные данные IP-адресов, если они есть
            $storedData = $localStorage->getData();

            // Проверяем, не содержит ли массив уже данный IP-адрес
            if (in_array($ip, $storedData, true)){

                return true;

            }
        }


    }

    /*метод получения IP*/
    public static function getIp(){

        $requestData = Application::getInstance()->getContext()->getRequest();

        $ip = $requestData->getRemoteAddress();

        return $ip;
    }

    /*Добавление в белый список(прошли каптчу)*/
    public static function addWhiteList($ip = null){

        if(!$ip)
        {
            $ip = self::getIp();
        }

        $localStorage = Application::getInstance()->getLocalSession('ipWhiteList');

        // Получаем сохраненные данные IP-адресов, если они есть
        $storedData = $localStorage->getData();

        // Проверяем, не содержит ли массив уже данный IP-адрес
        if (!in_array($ip, $storedData, true))
        {
            // Если IP-адрес еще не добавлен, то добавляем его в массив
            $storedData[] = $ip;

            // Сохраняем обновленный массив в локальном хранилище
            $resultAdd = $localStorage->setData($storedData);

            /*Принудительно сохраняем локальную сессию: в AJAX-запросах (prolog_before + die)
              без полного завершения ядра данные могут не записаться автоматически,
              из-за чего после перезагрузки страницы каптча показывается снова.*/
            try
            {
                Application::getInstance()->getSessionLocalStorageManager()->save();
            }
            catch (\Exception $e)
            {
            }

            return '123';
        }
    }

    /*Проверка нахождения в Белом списке*/
    public static function getWhiteStatus($ip){

        // Получаем текущий список разрешенных IP-адресов из локальной сессии
        $ipWhiteList = Application::getInstance()->getLocalSession('ipWhiteList')->getData();

        // Проверяем, содержится ли IP-адрес в списке разрешенных IP
        return in_array($ip, $ipWhiteList, true);
    }



}