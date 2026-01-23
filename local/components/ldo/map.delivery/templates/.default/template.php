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

<div class="delivery-block">
    <div class="delivery-block__title">
        Расчет стоимости доставки
    </div>
    <div class="delivery-block__form">
        <form id="deliveryForm" action="#" method="POST">
            <div class="form-line">
                <span>Введите адрес доставки</span>
                <input type="text" name="ADDRESS" id="addressInput" value="" required />
                <div id="suggestions" style="display: none;"></div>
            </div>
            <input type="hidden" name="LAT" id="latInput" value="" readonly />
            <input type="hidden" name="LON" id="lonInput" value="" readonly />
            <input type="hidden" name="ZONE_ID" id="zoneIdInput" value="" readonly />
            <button type="submit">Рассчитать</button>
        </form>
    </div>
    <div class="delivery-block__info">
        <div class="delivery-block__total-sum">
            Сумма доставки на указанный адрес:  <span>не рассчитана</span>
        </div>
        <div class="delivery-block__time-delivery ">
            Ориентировочное время доставки: <span> не рассчитано</span>
        </div>
        <div class="delivery-block__min-sum ">
            Минимальная сумма заказа: <span> не рассчитана</span>
        </div>

        <div class="delivery-block__free-delivery ">
            Бесплатная доставка от: <span> не рассчитана</span>
        </div>
    </div>


</div>
<script src="https://api-maps.yandex.ru/2.1/?apikey=e5f4d8fd-6912-4c51-8d74-17b816a751dc&lang=ru_RU" type="text/javascript"></script>

<div id="map"></div>