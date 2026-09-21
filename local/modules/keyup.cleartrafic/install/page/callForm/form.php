<?
define("NO_KEEP_STATISTIC", true);
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
use Bitrix\Main\Loader,
    Bitrix\Main\Context,
    Bitrix\Main\Application,
    \Keyup\Cleartrafic\IpList;

use \Keyup\Cleartrafic\HlBlock;
if(!Loader::IncludeModule('keyup.cleartrafic')){
    echo "Не установлен модуль фильтрации трафика";
}
$context = Context::getCurrent();
$request = Context::getCurrent()->getRequest();

if($message = $request->get("data")){

    $requestData = Application::getInstance()->getContext()->getRequest();
    $ip = $requestData->getRemoteAddress();

    if($ip)
    {
        $idElement = IpList::getIdElement($ip);

        if($idElement)
        {
            $addMessage = IpList::addMessage($idElement,$message);
        }
    }

}
?>
