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

// Ассеты визуального редактора (модуль fileman, JS/CSS-расширение html_editor)
// должны подключиться в <head>, поэтому инициализируем редактор до header.php.
// Скелет редактора здесь не нужен — только регистрация и загрузка ассетов,
// поэтому вывод буферизуем и отбрасываем.
\Bitrix\Main\Loader::includeModule('fileman');
if (class_exists('\CHTMLEditor')) {
    ob_start();
    $editorAssetsInit = new \CHTMLEditor();
    $editorAssetsInit->Show([
        'id'                        => 'pd-assets-init',
        'display'                   => false,
        'inputName'                 => 'pd_assets_init',
        'content'                   => '',
        'arTemplates'               => [],
        'useFileDialogs'            => false,
        'showTaskbars'              => false,
        'showComponents'            => false,
        'showSnippets'              => false,
        'bAllowPhp'                 => false,
        'allowPhp'                  => false,
        'askBeforeUnloadPage'       => false,
        'uploadImagesFromClipboard' => false,
    ]);
    ob_end_clean();
    unset($editorAssetsInit);
}

$partnersActivePage  = 'menu';
$partnersPageTitle   = 'Меню';
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
