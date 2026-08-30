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

                <?
                $APPLICATION->IncludeComponent(
	"ldo:products.list", 
	".default", 
	array(
		"CACHE_TIME" => "3600000",
		"COMPONENT_TEMPLATE" => ".default",
		"SORT_FIELD" => "SORT",
		"SORT_ORDER" => "ASC",
		"PAGE_SIZE" => "20",
		"CACHE_TYPE" => "A"
	),
	false
);
                ?>
            </div>
        </div>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
