<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Промокоды");
?>

<? if(!$USER->IsAuthorized()){
    LocalRedirect('/');
} ?>




<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>