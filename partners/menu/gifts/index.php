<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $USER;

$APPLICATION->SetTitle("Подарки к заказам");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Меню", "/partners/menu/");
$APPLICATION->AddChainItem("Подарки к заказам");

$request = Bitrix\Main\Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

$partnersActivePage  = 'menu';
$partnersPageTitle   = 'Меню';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
?>

        <div class="p-main">
            <div class="p-section">
                <?
                $APPLICATION->IncludeComponent(
                    "ldo:gifts.list",
                    "",
                    array(
                        "CACHE_TIME" => "0"
                    ),
                    false
                );
                ?>
            </div>
        </div>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
