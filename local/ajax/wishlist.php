<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use \Ldo\Favorites\Favorites;
use Bitrix\Main\Application,
    Bitrix\Main\Context,
    Bitrix\Main\Request,
    Bitrix\Main\Loader,
    Bitrix\Main\Server;

/* Избранное */
global $APPLICATION;

if(Loader::IncludeModule('ldo.favorites')){

    $context = Context::getCurrent();
    $request = Context::getCurrent()->getRequest();
    $idProduct = $request->get("id");

    if($idProduct){
        /*Добавляем в избранное, если уже есть - удаляем*/
        $isFavorites = Favorites::setItems($idProduct);

        // Сбрасываем HTML-кеш текущей страницы
        $currentUrl = $_SERVER['HTTP_REFERER'];
        $cache = \Bitrix\Main\Data\StaticHtmlCache::getInstance();
        $cache->delete($currentUrl);
        if (method_exists($cache, 'deleteHtmlCache')) {
            $cache->deleteHtmlCache($currentUrl);
        }

        // Сбрасываем управляемый кеш с тегом каталога, чтобы обновились страницы списков
        $taggedCache = \Bitrix\Main\Application::getInstance()->getTaggedCache();
        $taggedCache->clearByTag('iblock_id_4');
    }

    echo Favorites::getCount();
}



/* Избранное */
