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
    }

    echo Favorites::getCount();
}



/* Избранное */
