<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $USER;

$APPLICATION->SetTitle("Пользователи");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Пользователи");

$request = Bitrix\Main\Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

$partnersActivePage  = 'polzovateli';
$partnersPageTitle   = 'Пользователи';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
?>

        <div class="p-main">
            <div class="p-section">
                <?
                $APPLICATION->IncludeComponent(
                    "ldo:users.list",
                    "",
                    array(
                        "PAGE_SIZE" => "50",
                        "CACHE_TIME" => "0"
                    ),
                    false
                );
                ?>
            </div>
        </div>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
