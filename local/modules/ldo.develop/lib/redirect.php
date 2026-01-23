<?php
namespace Ldo\Develop;

use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Application;
use Bitrix\Main\Web\Uri;

use Bitrix\Main\Data\Cache;



class Redirect
{
    private static $hlblockId = 3; // ID вашего highload-блока

    public static function checkRedirect()
    {
        if (defined('ADMIN_SECTION') || defined('PUBLIC_AJAX_MODE')) {
            return;
        }

        $request = Application::getInstance()->getContext()->getRequest();
        $requestUrl = rtrim(strtok($request->getRequestUri(), '?'), '/');

        if ($requestUrl) {
            $newLink = self::getLink($requestUrl);
        }
    }

    public static function getLink($link)
    {
        if (!Loader::includeModule('highloadblock')) {
            AddMessage2Log('Module highloadblock not installed', 'redirects');
            return [];
        }

        $entity = self::getHLEntity();
        if (!$entity) {
            AddMessage2Log('HL Entity not found for block ID: ' . self::$hlblockId, 'redirects');
            return [];
        }

        try {
            $redirectLink = $entity::getList([
                'select' => ['UF_NEW_LINK', 'UF_CODE'],
                'filter' => ['=UF_OLD_LINK' => $link],
                'limit' => 1
            ])->fetch();

            if ($redirectLink && $redirectLink['UF_NEW_LINK']) {
                self::redirect($redirectLink);
            }

            return $redirectLink ?: [];
        } catch (\Exception $e) {
            AddMessage2Log('Redirect error: ' . $e->getMessage(), 'redirects');
            return [];
        }
    }

    private static function redirect(array $redirectData)
    {
        $newLink = $redirectData['UF_NEW_LINK'];
        $redirectCode = $redirectData['UF_CODE'] ?? 301;

        if (preg_match('#^https?://#i', $newLink)) {
            $redirectUrl = $newLink;
        } elseif (strpos($newLink, '/') === 0) {
            $redirectUrl = $newLink;
        } else {
            $request = Application::getInstance()->getContext()->getRequest();
            $currentDir = dirname($request->getRequestedPage());
            $redirectUrl = rtrim($currentDir, '/') . '/' . ltrim($newLink, '/');
        }

        $status = $redirectCode == 302 ? '302 Found' : '301 Moved Permanently';
        LocalRedirect($redirectUrl, true, $status);
        exit;
    }

    private static function getHLEntity()
    {
        static $entity = null;

        if ($entity === null) {
            try {
                $hlblock = HL\HighloadBlockTable::getById(self::$hlblockId)->fetch();
                if ($hlblock) {
                    $entity = HL\HighloadBlockTable::compileEntity($hlblock)->getDataClass();
                } else {
                    AddMessage2Log('Highload block not found with ID: ' . self::$hlblockId, 'redirects');
                }
            } catch (\Exception $e) {
                AddMessage2Log('HL Block error: ' . $e->getMessage(), 'redirects');
            }
        }

        return $entity;
    }
}


