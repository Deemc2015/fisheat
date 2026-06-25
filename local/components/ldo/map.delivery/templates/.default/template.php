<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */

use Bitrix\Main\Localization\Loc;
?>

<div id="page-delivery" class="delivery-block">
    <div class="delivery-block__title">
        <?= Loc::getMessage('LDO_DELIVERY_TITLE') ?: 'Расчет стоимости доставки' ?>
    </div>

    <div class="delivery-block__form">
        <form id="deliveryForm" action="#" method="POST">
            <div class="form-line">
                <span><?= Loc::getMessage('LDO_DELIVERY_ADDRESS_LABEL') ?: 'Введите адрес доставки' ?></span>
                <input type="text" name="ADDRESS" id="addressInput" value="" required />
                <div id="suggestions" style="display: none;"></div>
            </div>
            <input type="hidden" name="LAT" id="latInput" value="" readonly />
            <input type="hidden" name="LON" id="lonInput" value="" readonly />
            <input type="hidden" name="ZONE_ID" id="zoneIdInput" value="" readonly />
            <button type="submit"><?= Loc::getMessage('LDO_DELIVERY_BUTTON') ?: 'Рассчитать' ?></button>
        </form>
    </div>

    <div class="delivery-block__info" style="display:none;">
        <div class="delivery-block__total-sum">
            <?= Loc::getMessage('LDO_DELIVERY_TOTAL') ?: 'Сумма доставки на указанный адрес:' ?>
            <span><?= Loc::getMessage('LDO_DELIVERY_NOT_CALCULATED') ?: 'не рассчитана' ?></span>
        </div>
        <div class="delivery-block__time-delivery">
            <?= Loc::getMessage('LDO_DELIVERY_TIME') ?: 'Ориентировочное время доставки:' ?>
            <span><?= Loc::getMessage('LDO_DELIVERY_NOT_CALCULATED_TIME') ?: 'не рассчитано' ?></span>
        </div>
        <div class="delivery-block__min-sum">
            <?= Loc::getMessage('LDO_DELIVERY_MIN_ORDER') ?: 'Минимальная сумма заказа:' ?>
            <span><?= Loc::getMessage('LDO_DELIVERY_NOT_CALCULATED') ?: 'не рассчитана' ?></span>
        </div>
        <div class="delivery-block__free-delivery">
            <?= Loc::getMessage('LDO_DELIVERY_FREE') ?: 'Бесплатная доставка от:' ?>
            <span><?= Loc::getMessage('LDO_DELIVERY_NOT_CALCULATED') ?: 'не рассчитана' ?></span>
        </div>
    </div>
</div>

<div id="map"></div>

<!-- Передаем настройки в JS -->
<script>
    window.deliveryMapSettings = {
        yandexApiKey: '<?= htmlspecialchars($arResult['YANDEX_API_KEY']) ?>',
        defaultLat: <?= (float)$arResult['DEFAULT_LAT'] ?>,
        defaultLng: <?= (float)$arResult['DEFAULT_LNG'] ?>,
        defaultZoom: <?= (int)$arResult['DEFAULT_ZOOM'] ?>,
        siteId: '<?= htmlspecialchars($arResult['SITE_ID']) ?>'
    };
</script>

<script src="https://api-maps.yandex.ru/2.1/?apikey=<?= htmlspecialchars($arResult['YANDEX_API_KEY']) ?>&lang=ru_RU" type="text/javascript"></script>
<script src="<?= $templateFolder ?>/script.js"></script>
<link rel="stylesheet" href="<?= $templateFolder ?>/style.css">