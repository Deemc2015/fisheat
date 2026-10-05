<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/** @var array $arParams */
/** @var array $arResult */

use Bitrix\Main\Web\Json;

/**
 * Шаблон "default": доска заказов ресторана рендерится Vue-приложением
 * (расширение ldo.kds на ui.vue3). JS подключается ДО header.php на странице.
 *
 * Здесь — точка монтирования и передача данных (Model).
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
<div id="kds-board-vue" class="kds-board"></div>
<script>
    // Данные передаются двумя каналами: глобальная переменная + rootProps при createApp
    window.LDO_KDS_DATA = <?= $payload ?>;

    BX.ready(function () {
        if (typeof BX.Vue3 === "undefined") {
            console.error("[ldo.kds] ui.vue3 не загружен");
            return;
        }
        if (typeof BX.LDO === "undefined" || typeof BX.LDO.Kds === "undefined") {
            console.error("[ldo.kds] расширение ldo.kds не загружено");
            return;
        }

        var BitrixVue = BX.Vue3.BitrixVue || BX.Vue3;
        var root = document.getElementById("kds-board-vue");
        if (!root) {
            return;
        }

        BitrixVue.createApp(BX.LDO.Kds.KdsApp, {
            data: window.LDO_KDS_DATA,
        }).mount(root);
    });
</script>
