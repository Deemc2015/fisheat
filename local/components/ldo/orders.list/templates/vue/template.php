<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/** @var array $arParams */
/** @var array $arResult */

use Bitrix\Main\Web\Json;

/**
 * Шаблон "vue": список заказов рендерится Vue-приложением (ldo.orders на ui.vue3).
 * JS-расширения ui.vue3 и ldo.orders подключаются ДО header.php в партнёрском разделе.
 * Здесь — контейнер и инициализация приложения с передачей данных (Model).
 */

try {
    $payload = Json::encode(
        $arResult,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (\Throwable $e) {
    $payload = '{}';
}
?>
<div id="partners-orders-vue" class="p-main"></div>
<script>
    // Данные передаются двумя каналами: глобальная переменная + rootProps при createApp
    window.LDO_ORDERS_DATA = <?= $payload ?>;

    BX.ready(function () {
        if (typeof BX.Vue3 === "undefined") {
            console.error("[ldo.orders] ui.vue3 не загружен");
            return;
        }
        if (typeof BX.LDO === "undefined" || typeof BX.LDO.Orders === "undefined") {
            console.error("[ldo.orders] расширение ldo.orders не загружено");
            return;
        }

        var BitrixVue = BX.Vue3.BitrixVue || BX.Vue3;
        var root = document.getElementById("partners-orders-vue");
        if (!root) {
            return;
        }

        BitrixVue.createApp(BX.LDO.Orders.OrdersApp, {
            data: window.LDO_ORDERS_DATA,
        }).mount(root);
    });
</script>
