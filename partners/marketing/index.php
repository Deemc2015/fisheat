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

// Активный таб: ?tab=... либо #hash (обрабатывается на клиенте).
$activeTab = (string)$request->getQuery('tab');
$allowedTabs = ['actions', 'gifts', 'free'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'actions';
}

$partnersActivePage  = 'marketing';
$partnersPageTitle   = 'Маркетинг';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
?>

        <div class="p-main">
            <div class="p-marketing-tabs" id="marketing-tabs" role="tablist">
                <button type="button" class="p-marketing-tab<?= $activeTab === 'actions' ? ' is-active' : '' ?>"
                        data-tab="actions" role="tab">Раздел акций</button>
                <button type="button" class="p-marketing-tab<?= $activeTab === 'gifts' ? ' is-active' : '' ?>"
                        data-tab="gifts" role="tab">Подарки к заказу</button>
                <button type="button" class="p-marketing-tab<?= $activeTab === 'free' ? ' is-active' : '' ?>"
                        data-tab="free" role="tab">Бесплатные позиции к товарам</button>
            </div>

            <!-- Таб: Раздел акций -->
            <div class="p-section p-marketing-pane<?= $activeTab === 'actions' ? ' is-active' : '' ?>"
                 data-tab-pane="actions"<?= $activeTab === 'actions' ? '' : ' style="display:none;"' ?>>
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

            <!-- Таб: Подарки к заказу (вынесено со /partners/menu/gifts/) -->
            <div class="p-section p-marketing-pane<?= $activeTab === 'gifts' ? ' is-active' : '' ?>"
                 data-tab-pane="gifts"<?= $activeTab === 'gifts' ? '' : ' style="display:none;"' ?>>
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

            <!-- Таб: Бесплатные позиции к товарам -->
            <div class="p-section p-marketing-pane<?= $activeTab === 'free' ? ' is-active' : '' ?>"
                 data-tab-pane="free"<?= $activeTab === 'free' ? '' : ' style="display:none;"' ?>>
                <?
                $APPLICATION->IncludeComponent(
                    "ldo:freepositions.list",
                    "",
                    array(
                        "CACHE_TIME" => "0"
                    ),
                    false
                );
                ?>
            </div>
        </div>

        <script>
        (function () {
            var tabs = document.querySelectorAll('#marketing-tabs .p-marketing-tab');
            var panes = document.querySelectorAll('.p-marketing-pane');
            if (!tabs.length) return;

            function activate(name, pushHash) {
                var found = false;
                for (var i = 0; i < tabs.length; i++) {
                    var isActive = tabs[i].getAttribute('data-tab') === name;
                    tabs[i].classList.toggle('is-active', isActive);
                    if (isActive) found = true;
                }
                if (!found) return;

                for (var j = 0; j < panes.length; j++) {
                    var match = panes[j].getAttribute('data-tab-pane') === name;
                    panes[j].classList.toggle('is-active', match);
                    panes[j].style.display = match ? '' : 'none';
                }

                if (pushHash !== false) {
                    if (history.replaceState) {
                        history.replaceState(null, '', '#' + name);
                    } else {
                        location.hash = name;
                    }
                }
            }

            for (var k = 0; k < tabs.length; k++) {
                tabs[k].addEventListener('click', function () {
                    activate(this.getAttribute('data-tab'));
                });
            }

            // Определяем активный таб по hash (при наличии).
            var hash = (location.hash || '').replace('#', '');
            if (hash && document.querySelector('#marketing-tabs .p-marketing-tab[data-tab="' + hash + '"]')) {
                activate(hash, false);
            }
        })();
        </script>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
