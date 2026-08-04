<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\UI\Extension;

global $USER;

$APPLICATION->SetTitle("Статистика");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Статистика");

$request = Bitrix\Main\Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

// Подключаем расширение BitrixVue 3 (ldo.vue-app)
// Должно быть вызвано ДО include header.php, чтобы бандл попал в $APPLICATION->ShowHead()
Extension::load("ldo.vue-app");

$partnersActivePage  = 'statistics';
$partnersPageTitle   = 'Статистика';
$partnersHeaderStyle = 'padding-bottom:0; border-bottom:none;';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
?>

        <div class="p-main">
            <div class="p-section">
                <div class="p-section__header">
                    <h2 class="p-section__title">Статистика</h2>
                </div>

                <!-- Demo-приложение BitrixVue 3 (паттерн MVVM) -->
                <div id="partners-vue-demo"></div>
            </div>
        </div>

        <script>
            BX.ready(function () {
                // Контроллер из расширения ldo.vue-app (namespace BX.LDO.VueApp)
                var app = new BX.LDO.VueApp.PartnersApplication('#partners-vue-demo', {
                    title: 'Партнёрский кабинет на Vue',
                });
                app.start();
            });
        </script>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
