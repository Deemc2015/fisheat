<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $USER;

$APPLICATION->SetTitle("Настройка товаров");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Меню", "/partners/menu/");
$APPLICATION->AddChainItem("Настройка товаров");

$request = Bitrix\Main\Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

$partnersActivePage  = 'menu';
$partnersPageTitle   = 'Меню';
$partnersHeaderStyle = 'padding-bottom:0; border-bottom:none;';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
?>



        <div class="p-main">
            <div class="p-section">
                <div class="p-section__header">
                    <h2 class="p-section__title">Настройка товаров</h2>
                </div>

                <div style="padding:40px; text-align:center; color:var(--color-muted); background:var(--bg-black); border-radius:12px;">
                    Раздел в разработке.
                </div>
            </div>
        </div>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
