<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
use Bitrix\Main\Application,
    Bitrix\Main\Context,
    Bitrix\Main\Request,
    Bitrix\Main\Server;


$context = Context::getCurrent();
$request = Context::getCurrent()->getRequest();


$captcha = $request->get("data");

addMessage2Log($request);
?>