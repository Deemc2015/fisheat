<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $USER;

$APPLICATION->SetTitle("Маркетинг");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Маркетинг");

$request = Bitrix\Main\Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

// Ассеты визуального редактора (модуль fileman, JS/CSS html_editor)
// должны подключиться в <head>, поэтому инициализируем редактор до header.php.
// Скелет редактора здесь не нужен — только регистрация и загрузка ассетов,
// поэтому вывод буферизуем и отбрасываем.
\Bitrix\Main\Loader::includeModule('fileman');
if (class_exists('\CHTMLEditor')) {
    ob_start();
    $editorAssetsInit = new \CHTMLEditor();
    $editorAssetsInit->Show([
        'id'                        => 'al-assets-init',
        'display'                   => false,
        'inputName'                 => 'al_assets_init',
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

$partnersActivePage  = 'marketing';
$partnersPageTitle   = 'Маркетинг';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
?>

        <div class="p-main">
            <div class="p-section">
                <div class="p-section__header">
                    <h2 class="p-section__title">Акции</h2>
                </div>

                <?
                $APPLICATION->IncludeComponent(
                    "ldo:actions.list",
                    ".default",
                    array(
                        "CACHE_TIME" => "3600000",
                        "COMPONENT_TEMPLATE" => ".default",
                        "LIMIT" => "100",
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
