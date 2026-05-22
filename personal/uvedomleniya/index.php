<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Уведомления");
?>

<? if(!$USER->IsAuthorized()){
    LocalRedirect('/');
} ?>


<?$APPLICATION->IncludeComponent(
	"ldo:profile.notification",
	"",
Array()
);?>


<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>