<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $USER;

$APPLICATION->SetTitle("Настройка разделов");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Меню", "/partners/menu/");
$APPLICATION->AddChainItem("Настройка разделов");

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
                    <h2 class="p-section__title">Настройка разделов</h2>
                </div>
                <?
                $APPLICATION->IncludeComponent(
                        "ldo:sections.list",
                        "",
                        array(
                                "CACHE_TIME" => 3600000,
                        )
                );
                ?>

            </div>
        </div>

<script>
function editSection(id, name) {
    alert('Редактирование раздела #' + id + ' «' + name + '» — в разработке');
}

function deleteSection(id, name) {
    if (confirm('Удалить раздел «' + name + '»?')) {
        alert('Удаление раздела #' + id + ' — в разработке');
    }
}
</script>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
