<?php

use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Loader;
use  Ldo\Develop\Product;
use Bitrix\Sale;
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var OpenSourceOrderComponent $component */


?>


<form action="" method="post" name="os-order-form" id="os-order-form">
<div class="order-page">
    <div class="left-order-page">
        <div class="product-list">
            <div class="product-list__title">
                <div class="order-cart-title">Ваш заказ</div>
                <div class="delete-order"><?if(!$isMobile):?>Очистить корзину<?endif;?></div>
            </div>
            <?

            foreach ($arResult['BASKET'] as $arBasketItem):?>
                <div class="product-list__item" data-id="<?=$arBasketItem['PRODUCT_ID']?>">
                    <?if($arBasketItem['IMAGE']):?>
                    <div class="image-product">
                        <img src="<?=$arBasketItem['IMAGE']?>" alt="<?=$arBasketItem['NAME']?>">
                    </div>
                    <?endif?>
                    <?if($isMobile):?><div class="mobile-name"><?endif?>

                        <?if($arBasketItem['LINK']):?>
                            <a href="<?=$arBasketItem['LINK']?>" class="name-product"><?=$arBasketItem['NAME']?></a>
                        <?endif;?>

                    <div class="price-product">
                        <div class="price-product__sum"><?=$arBasketItem['SUM_DISPLAY']?></div>
                        <?if($arBasketItem['WEIGHT']):?>
                            <div class="weight"><?echo (int)$arBasketItem['WEIGHT']*$arBasketItem['QUANTITY']?> г</div>
                        <?endif;?>
                    </div>
                    <?if($isMobile):?></div><?endif?>

                    <div class="amount-product-block">
                            <span class="minus"></span>
                            <span data-max-quantity="<?=$arBasketItem['COUNT_AVALIABLE']?>" class="quantity-product"><?=$arBasketItem['QUANTITY']?></span>
                        <span class="plus"></span>
                    </div>
                </div>
            <?endforeach?>

            <div class="count-people-block">
                <?if($isMobile):?><div class="mobile-field-count"><?endif?>
                <div class="count-people-block__title">Указать кол-во персон</div>
                <div class="checked-button"></div>
                    <?if($isMobile):?></div><?endif?>
                <div class="count-people-block__count">
                    <span class="minus"></span>
                    <input readonly name="<?=$arResult['PROPERTIES']['COUNT_PERSON']['FORM_NAME']?>" id="<?=$arResult['PROPERTIES']['COUNT_PERSON']['FORM_LABEL']?>" type="<?=$arResult['PROPERTIES']['COUNT_PERSON']['TYPE']?>" class="count-people-block__count-num" value="<?=$arResult['PROPERTIES']['COUNT_PERSON']['VALUE']?>">
                    <span class="plus"></span>
                </div>
            </div>

        </div>
        <div class="comments-block">
            <div class="comments-block__top">
                <div class="comments-block__top-title">Комментарий кухне</div>
                <div class="comments-block__top-icon"></div>
            </div>
            <textarea id="orderDescription" cols="4" class="form-control bx-soa-customer-textarea bx-ios-fix" name="ORDER_DESCRIPTION"></textarea>
            <?print_r($arResult['NEAREST_GIFT']);?>
        </div>
        <?php if (!empty($arResult['GIFTS'])): ?>
        <div class="gifts-block">
            <?php if ($arResult['NEAREST_GIFT']): ?>
            <div class="gifts-block__top">
                <i></i>
                <p>До подарка <?= $arResult['NEAREST_GIFT']['LEVEL'] ?>  осталось еще <span><?= $arResult['NEAREST_GIFT']['SUM_FREE'] ?> ₽</span></p>
            </div>
            <?php endif; ?>
            <div class="gifts-block__items <?if($_SESSION["CATALOG_USER_COUPONS"]){echo 'block';}?>">

                    <!-- Список всех подарков (уже отсортирован) -->
                    <div class="gifts-list">
                        <?php foreach ($arResult['GIFTS'] as $level => $gifts): ?>

                        <?foreach($gifts as $gift):?>
                            <div class="gifts-list__item ">
                                <div class="gifts-list__item-img">
                                    <picture>
                                        <source srcset="<?=$gift['PREVIEW_PICTURE']?>" />
                                        <img src="<?=$gift['PREVIEW_PICTURE']?>" />
                                    </picture>
                                </div>
                                <div class="gifts-list__item-title"><?=$gift['NAME']?></div>
                                <?if($_SESSION["CATALOG_USER_COUPONS"]):?>
                                    <div class="not-avaliable-text">
                                        Выбор недоступен
                                    </div>
                                <?else:?>
                                    <?if($gift['AVAILABLE']):?>
                                        <div class="addCartGift" id-product="<?=$gift['ID']?>">Выбрать</div>
                                    <?else:?>
                                        <div class="not-avaliable-text">
                                            Доступно при заказе от<br> <?=$gift['SUM_LEVEL']?> ₽
                                        </div>
                                    <?endif;?>
                                <?endif?>


                            </div>
                        <?endforeach?>

                        <?php endforeach; ?>
                    </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="promo-block">
            <h2>Применение скидок</h2>
            <div class="promo-block__line">
                <div class="promo-block__left">
                    <label for="promo-1">
                        <input <?if($_SESSION["CATALOG_USER_COUPONS"]){echo 'checked';}?> type="radio" id="promo-1" name="promo_id" value="promokod" >
                        <span></span>
                        Промокод
                    </label>
                    <label for="promo-2">
                        <input  type="radio" id="promo-2" name="promo_id" value="bonus" >
                        <span></span>
                        Оплата бонусами
                    </label>
                </div>
                <div class="promo-block__right">

                        <?if($_SESSION["CATALOG_USER_COUPONS"]):?>
                    <div class="promo-block__left-promokod">
                        <div class="promoChange">
                            <input type="text" name="promokod" readonly  id="promocode" value="<?=$_SESSION["CATALOG_USER_COUPONS"][0]?>"/>
                            <button type="button">Отменить</button>
                        </div>
                    </div>
                            <?else:?>
                    <div class="promo-block__left-promokod" style="display:none;">
                            <div class="promoChange" >
                                <input type="text" name="promokod"  id="promocode" />
                                <button type="button">Применить</button>
                            </div>
                    </div>
                            <?endif?>


                </div>
            </div>


        </div>
        <?if($isMobile):?>
            <div class="delivery-block">
                <?if($arResult['DELIVERY_LIST']):?>
                    <div class="delivery-block__butons">
                        <?
                        $i == 1;
                        foreach($arResult['DELIVERY_LIST'] as $itemDelivery):?>

                            <label for="code-<?=$itemDelivery['ID']?>">
                                <input <?if($arParams['DEFAULT_DELIVERY_ID'] == $itemDelivery['ID']){echo 'checked';}?>  type="radio" id="code-<?=$itemDelivery['ID']?>" name="delivery_id" value="<?=$itemDelivery['ID']?>" <?=$itemDelivery['CHECKED'] ? 'checked' : ''?>>

                                <div class="delivery-name"><?=$itemDelivery['NAME']?></div>
                            </label>

                        <?endforeach;?>
                    </div>
                <?endif?>



                <?if($arResult['USER_ADRESS']):?>



                    <div class="adress-user-list">

                        <?foreach($arResult['USER_ADRESS'] as $adress):?>
                            <?//print_r($adress);?>
                            <div class="adress-user-list__item">
                                <label for="adress-user-list__item-name-<?=$adress['ID']?>">
                                    <input data-id="<?=$adress['ID']?>" data-price="<?=$adress['PRICE']?>"
                                           data-city="<?= htmlspecialchars($adress['CITY'] ?? '') ?>"
                                           data-kvartira="<?= htmlspecialchars($adress['KVARTIRA'] ?? '') ?>"
                                           data-podezd="<?= htmlspecialchars($adress['PODEZD'] ?? '') ?>"
                                           data-etag="<?= htmlspecialchars($adress['ETAG'] ?? '') ?>"
                                           data-domofon="<?= htmlspecialchars($adress['DOMOFON'] ?? '') ?>"
                                           data-lat="<?= htmlspecialchars($adress['SHIRINA'] ?? '') ?>"
                                           data-lon="<?= htmlspecialchars($adress['DOLGOTA'] ?? '') ?>"
                                           <?if($adress['CHECKED']){echo 'checked';}?> name="address_id" type="radio" id="adress-user-list__item-name-<?=$adress['ID']?>" value="<?=$adress['ADRESS_NAME']?>">
                                    <span></span>
                                    <?=$adress['ADRESS_NAME']?>
                                </label>
                                <div class="adress-user-list__item-btn">
                                    <div class="adress-user-list__item-btn-edit"></div>
                                    <div class="adress-user-list__item-btn-delete"></div>
                                </div>
                            </div>
                        <?endforeach?>
                    </div>
                <?endif?>
                <div class="addAdress-user">Добавить адрес</div>
            </div>
        <?endif?>

        <div class="block-line-two">
        <?if($arResult['PAY_SYSTEM_LIST']):?>
        <div class="payment-block">
            <h2>Способ оплаты</h2>
            <? foreach ($arResult['PAY_SYSTEM_ERRORS'] as $error):
                /** @var Error $error */
                ?>
                <div class="error"><?=$error->getMessage()?></div>
            <? endforeach;
            foreach ($arResult['PAY_SYSTEM_LIST'] as $arPaySystem):
                // region /1 Убираем отображение служебной платежной системы оплаты бонусами
                if (\Bitrix\Main\Loader::includeModule('acrit.bonus') && $arPaySystem['ID'] == \Acrit\Bonus\Pay::getBonusPaySystemId()) {
                    continue;
                }
                // endregion
                ?>
                <label for="pay-<?=$arPaySystem['ID']?>"  class="payment-block__item">
                    <input <?if($arParams['DEFAULT_PAY_SYSTEM_ID'] == $arPaySystem['ID']){echo 'checked';}?> id="pay-<?=$arPaySystem['ID']?>" type="radio" name="pay_system_id"
                           value="<?=$arPaySystem['ID']?>"
                        <?=$arPaySystem['CHECKED'] ? 'checked' : ''?>
                    >
                    <span></span>
                    <div><?=$arPaySystem['NAME']?></div>
                </label>

            <? endforeach; ?>
        </div>
        <?endif?>
        <?
            /*Группа полей времени и даты доставки*/

            $arrTimeDelivery = ['DATE_TIME_DELIVERY','DEFAULT_TIME','TIME_DELIVERY'];

            $fieldsTimeDelivery = [];

            foreach ($arResult['PROPERTIES'] as $key => $fields){
                if(in_array($key,$arrTimeDelivery)){
                    $fieldsTimeDelivery[] = $fields;
                }
            }

            /*Свойства, к которым привязаны чекбоксы (инпуты в блоке .hidden-fields)*/
            $defaultTimeProp = $arResult['PROPERTIES']['DEFAULT_TIME'] ?? null;
            $dateTimeProp    = $arResult['PROPERTIES']['DATE_TIME_DELIVERY'] ?? null;
            $timeProp        = $arResult['PROPERTIES']['TIME_DELIVERY'] ?? null;

            /*По умолчанию всегда выбрано «Как можно скорее».
              Режим «Выбрать дату и время» включается только при явном выборе
              пользователя (значение DEFAULT_TIME == 'N' из отправленной формы).*/
            $submittedDefaultTime = isset($_REQUEST['properties']['DEFAULT_TIME'])
                ? (string)$_REQUEST['properties']['DEFAULT_TIME']
                : null;
            $isDefaultTime = ($submittedDefaultTime === null) ? true : ($submittedDefaultTime !== 'N');

            /*Извлекаем дату и время из сохранённых значений для повторного отображения*/
            $deliveryDate = '';
            $deliveryTime = '';

            if ($dateTimeProp && trim((string)$dateTimeProp['VALUE']) !== '') {
                $dtValue = trim((string)$dateTimeProp['VALUE']);
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2})(?::\d{2})?)?/', $dtValue, $m)) {
                    $deliveryDate = $m[1] . '-' . $m[2] . '-' . $m[3];
                    if (!empty($m[4])) {
                        $deliveryTime = $m[4] . ':' . $m[5];
                    }
                } elseif (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{2}):(\d{2})(?::\d{2})?)?/', $dtValue, $m)) {
                    $deliveryDate = $m[3] . '-' . $m[2] . '-' . $m[1];
                    if (!empty($m[4])) {
                        $deliveryTime = $m[4] . ':' . $m[5];
                    }
                } else {
                    $deliveryDate = $dtValue;
                }
            }

            if ($deliveryTime === '' && $timeProp && preg_match('/^(\d{2}):(\d{2})/', trim((string)$timeProp['VALUE']), $m)) {
                $deliveryTime = $m[1] . ':' . $m[2];
            }
        ?>
            <?if($fieldsTimeDelivery):
                ?>
                <div class="time-delivery">
                    <h2>Время доставки</h2>
                    <div class="time-delivery__item">
                        <label for="default_time">

                            <input id="default_time" type="radio"
                                   name="time_delivery"
                                   value="default" <?=$isDefaultTime ? 'checked' : ''?>>
                            <span></span>
                            Как можно скорее
                        </label>
                        <label for="date_time">

                            <input id="date_time" type="radio"
                                   name="time_delivery"
                                   value="datetime" <?=!$isDefaultTime ? 'checked' : ''?>>
                            <span></span>
                            Выбрать дату и время
                        </label>
                    </div>

                    <div class="time-delivery__datetime"<?=$isDefaultTime ? ' style="display:none;"' : ''?>>
                        <div class="time-delivery__datetime-row">
                            <label for="delivery_datetime_date">Дата</label>
                            <input type="date" id="delivery_datetime_date"
                                   class="time-delivery__input time-delivery__input--date"
                                   value="<?=htmlspecialcharsbx($deliveryDate)?>">
                        </div>
                        <div class="time-delivery__datetime-row">
                            <label for="delivery_datetime_time">Время</label>
                            <input type="time" id="delivery_datetime_time"
                                   class="time-delivery__input time-delivery__input--time"
                                   value="<?=htmlspecialcharsbx($deliveryTime)?>">
                        </div>
                    </div>
                </div>
            <?endif;?>
        </div>

    </div>
    <? // region?>
    <?
    /*if (\Bitrix\Main\Loader::includeModule('acrit.bonus')) {

        $arResultEx = [
                'ORDER_PRICE' => $arResult['PRICE'] - $arResult['DELIVERY_PRICE'],
                'DELIVERY_PRICE' => $arResult['DELIVERY_PRICE'],
                'BASKET' => $arResult['BASKET'],
            ] + $component->order->getFieldValues();
        $resultBonus = \Acrit\Bonus\Profile::runPayProfiles($arResultEx);
        echo "<pre>";
        print_r($resultBonus);
        echo "</pre>";*/
        ?>
    <?

    // echo \Acrit\Bonus\Core::getPayOrderBlock($arResultEx['BONUSPAY'], /** @lang JavaScript */ 'AcritBonusPayBonusBtn();');
    ?>
    <script>
       /* function AcritBonusPayBonusBtn() {
            // не отправляем заказ на сохранение, а перегружаем страницу
            $(".send_open_source_order_flag").val('n');
            // эмулируем отправку формы заказа, чтобы бонусы подхватились
            $(".send_open_source_order_submit").click();
            // отключаем кнопку "оплатить бонусами" от использования
            $(this).css('pointer-events', 'none');
        }*/
    </script>
    <?
        // распечатайте $arResultEx['BONUSPAY'] - его можно использовать в дальнейшем для вывода данных,
        // в частности: $arResultEx['BONUSPAY']['USER_VALUE_CURRENCY'] - это сколько бонусов человек решил использовать в оплату заказа
        // \Bitrix\Main\Diag\Debug::dump($arResultEx);

        // 4/ Получение бонусов за этот заказ (с учетом фильтров в профиле начисления бонусов за заказ)
        //$arResultEx['BONUS']['ORDER'] = \Acrit\Bonus\Core::getCartOrderBonus('ORDER', $arResultEx);
        //d($arResultEx);
    //}
    ?>
    <? // endregion?>


    <div class="right-order-page">
        <?if(!$isMobile):?>
        <div class="delivery-block">
            <? foreach ($arResult['DELIVERY_ERRORS'] as $error):
            /** @var Error $error */
            ?>
            <div class="error"><?= $error->getMessage() ?></div>
            <? endforeach;?>
                <?if($arResult['DELIVERY_LIST']):?>
                    <div class="delivery-block__butons">
                <? foreach($arResult['DELIVERY_LIST'] as $itemDelivery):
                    ?>
                        <label for="code-<?=$itemDelivery['ID']?>">
                            <input <?if($arParams['DEFAULT_DELIVERY_ID'] == $itemDelivery['ID']){echo 'checked';}?>  type="radio" id="code-<?=$itemDelivery['ID']?>" name="delivery_id" value="<?=$itemDelivery['ID']?>" <?=$itemDelivery['CHECKED'] ? 'checked' : ''?>>
                            <div class="delivery-name"><?=$itemDelivery['NAME']?></div>
                        </label>

                <?endforeach;?>
                    </div>
                <?endif?>
            <div class="dostavka-block">
                <?if($arResult['USER_ADRESS']):?>
                    <div class="adress-user-list">
                        <?foreach($arResult['USER_ADRESS'] as $adress):?>
                            <div class="adress-user-list__item">
                                <label for="adress-user-list__item-name-<?=$adress['ID']?>">
                                    <input data-id="<?=$adress['ID']?>" data-price="<?=$adress['PRICE']?>"
                                           data-city="<?= htmlspecialchars($adress['CITY'] ?? '') ?>"
                                           data-kvartira="<?= htmlspecialchars($adress['KVARTIRA'] ?? '') ?>"
                                           data-podezd="<?= htmlspecialchars($adress['PODEZD'] ?? '') ?>"
                                           data-etag="<?= htmlspecialchars($adress['ETAG'] ?? '') ?>"
                                           data-domofon="<?= htmlspecialchars($adress['DOMOFON'] ?? '') ?>"
                                           data-lat="<?= htmlspecialchars($adress['SHIRINA'] ?? '') ?>"
                                           data-lon="<?= htmlspecialchars($adress['DOLGOTA'] ?? '') ?>"
                                           <?if($adress['CHECKED']){echo 'checked';}?> name="address_id" type="radio" id="adress-user-list__item-name-<?=$adress['ID']?>" value="<?=$adress['ADRESS_NAME']?>">
                                    <span></span>
                                    <?=$adress['ADRESS_NAME']?>
                                </label>
                                <div class="adress-user-list__item-btn">
                                    <div class="adress-user-list__item-btn-edit"></div>
                                    <div class="adress-user-list__item-btn-delete"></div>
                                </div>
                            </div>
                        <?endforeach?>
                    </div>
                <?endif?>
                <div class="addAdress-user">Добавить адрес</div>
            </div>
            <div class="samovivoz-block">
                <div class="restorans-list">
                <?if($arResult['RESTORAN_ADRESS']):?>
                        <?foreach($arResult['RESTORAN_ADRESS'] as $restoran):?>
                        <div class="restorans-list__item">
                            <label for="restorans-list__item-name-<?=$restoran['ID']?>">
                                <input data-id="<?=$restoran['ID']?>"  name="restoran_id" <?if($restoran['CHECKED']){echo 'checked';}?>  type="radio" id="restorans-list__item-name-<?=$restoran['ID']?>" value="<?=$restoran['NAME']?>">
                                <span></span>
                                <?=$restoran['NAME']?>
                            </label>
                        </div>
                        <?endforeach?>

                    <?endif?>
                </div>

                <div class="view-map-restotans">Показать на карте</div>
            </div>

        </div>
        <?endif?>
        <div class="total-order-block">

            <h2>Стоимость заказа</h2>
            <div class="total-order-block__line adress-text">
                <span>Адрес доставки</span><span class="adress-value">Максима Горького ул, д. 44, кв. 90</span>
            </div>
            <div class="total-order-block__line delivery-text">
                <span>Сумма доставки</span><span class="delivery-price"><?=$arResult['DELIVERY_PRICE_DISPLAY']?></span>
            </div>
            <div class="total-order-block__line">
                <span>Сумма заказа</span><span class="total-price"><?=$arResult['SUM_BASE_DISPLAY']?></span>
            </div>
            <div class="total-order-block__line">
                <span>Скидка</span><span class="total-skidka"><?=$arResult['DISCOUNT_VALUE_DISPLAY']?></span>
            </div>
            <div class="total-order-block__line">
                <span>Начислено бонусов</span><span class="total-bonus"><?=$arResultEx['BONUS']['ORDER']['VALUE_FORMAT']?></span>
            </div>

            <div class="total-order-block__bottom">
                <div class="total-title">Итого</div>
                <div class="total-value"><?=$arResult['SUM_DISPLAY']?></div>
            </div>

            <div class="total-order-block-btn">
                <label for="politika-order">
                    <input type="checkbox" id="politika-order" required>
                    <span></span>
                    <div class="politika-link" >Я даю <a href="#">согласие</a> на обработку моих персональных данных, в соответствии с Федеральным законом от 27.07.2006 г. №152-ФЗ "О персональных данных", на условиях, определенных политикой в области обработки и обеспечения безопасности персональных данных</div>
                </label>
                <input type="hidden" name="person_type_id" value="<?=$arParams['PERSON_TYPE_ID']?>">
                <button type="submit" class="send_open_source_order_submit"><?=Loc::getMessage('OPEN_SOURCE_ORDER_TEMPLATE_MAKE_ORDER_BUTTON')?></button>
            </div>


        </div>
    </div>
</div>

       <!-- <div id="acrit-bonus-paysystem" class="bx-soa-section">
            <div class="bx-soa-section-title-container">
                <h2 class="bx-soa-section-title col-sm-9">
                    <span class="bx-soa-section-title-count"></span><?=$arResultEx['BONUSPAY']['NAME']?>
                </h2>
            </div>
            <div class="bx-soa-section-content container-fluid">
                <div class="bx-soa-pp row">
                    <div class="col-sm-2 bx-soa-pp-item-container">
                        <div class="bx-soa-pp-company-graf-container">
                            <div class="bx-soa-pp-company-image"
                                 style="background-image: url(<?=$arResultEx['BONUSPAY']['LOGOTIP_SRC']?>);background-image: -webkit-image-set(url(<?=$arResultEx['BONUSPAY']['LOGOTIP_SRC']?>) 1x, url(<?=$arResultEx['BONUSPAY']['LOGOTIP_SRC']?>) 2x)">
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-9 bx-soa-pp-item-container">
                        <div class="bonus_comment">
                            <?
                            //print_r($arResultEx );

                            ?>
                            <strong>Ваш баланс <?=$bonusPay['CURRENT_BONUS_BUDGET_FORMATED']?></strong>
                        </div>
                        <span><br>Можете оплатить <?=$bonusPay['MAXPAY_FORMATTED']?></span>
                        <div id="bonus_payfield_block"><strong>Введите сумму</strong></div>
                        <input type="hidden" name="PAY_BONUS_ACCOUNT" value="Y">
                        <input type="text" id="BONUS_CNT" value="<?=$bonusPay['USER_VALUE']?>" name="BONUS_CNT" style="width: 150px;">
                        <label style="border: 1px solid rgb(227, 230, 232); padding: 2px; margin-left: 10px; cursor: pointer;"
                               class="bxr-subscribe-tab-link bxr-font-color bxr-border-color">
                            <span onclick="<?=$eventOnclickBtnJs?>">Применить</span>
                        </label>
                    </div>
                </div>
            </div>
        </div> -->


    <? // region 7/ проставили классы у флага и кнопки-отправки ?>
    <input type="hidden" name="save" value="y" class="send_open_source_order_flag">


    <style>
        .hidd3en-fields{
            display: none;
        }
        /* Новый дизайн модального окна адреса */
        .modal-add-address {
            width: 900px;
            max-width: 95vw;
            top: 50%;
            transform: translateY(-50%);
            padding: 30px;
            text-align: left;
        }
        .modal-add-address .close-modal {
            top: 12px;
            right: 12px;
            cursor: pointer;
        }
        .modal-add-address__inner {
            display: flex;
            gap: 24px;
            min-height: 450px;
        }
        .modal-add-address__left {
            flex: 0 0 380px;
            display: flex;
            flex-direction: column;
        }
        .modal-add-address__left .top-title {
            font-size: 24px;
            margin-bottom: 20px;
            text-align: left;
        }
        .modal-add-address__left .form-block-address {
            position: relative;
            margin-bottom: 12px;
        }
        .modal-add-address__left .form-block-address input {
            width: 100%;
            padding: 14px 16px;
            font-size: 16px;
            border-radius: 8px;
            border: 1px solid var(--bg-input);
            background: var(--bg-input);
            color: var(--color-input);
            box-sizing: border-box;
        }
        #modalSuggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 180px;
            overflow-y: auto;
            z-index: 10;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        #modalSuggestions .suggestion-item {
            padding: 10px 14px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            color: #333;
        }
        #modalSuggestions .suggestion-item:hover {
            background: #f5f5f5;
        }
        .form-block-extra {
            margin-bottom: 12px;
        }
        .form-block-extra__row {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
        }
        .form-block-extra__row input {
            flex: 1;
            padding: 10px 12px;
            font-size: 14px;
            border-radius: 6px;
            border: 1px solid var(--bg-input);
            background: var(--bg-input);
            color: var(--color-input);
            box-sizing: border-box;
            outline: none;
        }
        .form-block-extra__row input:focus {
            border-color: #F44336;
        }
        .modal-delivery-info {
            background: #f8f8f8;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .modal-delivery-info__item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 14px;
        }
        .modal-delivery-info__label {
            color: #666;
        }
        .modal-delivery-info__value {
            font-weight: 600;
            color: #333;
        }
        .modal-delivery-info__note {
            font-size: 12px;
            color: #999;
            margin-top: 8px;
            text-align: center;
        }
        .modal-add-address__left .button-modal {
            display: block;
            margin-top: auto;
        }
        .modal-add-address__left .add-btn {
            width: 100%;
            padding: 14px 0;
            font-size: 16px;
            border-radius: 8px;
            background: var(--bg-button);
            color: #fff;
            border: none;
            cursor: pointer;
            transition: opacity .3s;
            font-weight: 600;
        }
        .modal-add-address__left .add-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .modal-add-address__left .add-btn:not(:disabled):hover {
            opacity: 0.8;
        }
        .modal-add-address__right {
            flex: 1;
            min-height: 400px;
        }
        #modalMap {
            width: 100%;
            height: 100%;
            min-height: 400px;
            border-radius: 10px;
            overflow: hidden;
        }
        .modal-add-address .form-block-address input:focus {
            border-color: #F44336;
        }
    </style>


   <div class="hidden-fields">
        <?foreach($arResult['PROPERTIES'] as $field):?>
            <? foreach ($field['ERRORS'] as $error):
                /** @var Error $error */
                ?>
                <div class="error"><?= $error->getMessage() ?></div>
            <? endforeach; ?>

                <input name="<?=$field['FORM_NAME']?>" id="<?=$field['FORM_LABEL']?>" type="<?=$field['TYPE']?>" placeholder="<?=$field['NAME']?>" value="<?=$field['VALUE']?>">

        <?endforeach?>
    </div>


    <? // endregion?>

</form>

<div class="wrp"></div>
<div class="modal-delete">
    <span class="close-modal"></span>
    <div class="top-title">Удалить корзину</div>
    <div class="text-modal">Вы действительно хотите удалить?</div>
    <div class="button-modal">
        <div class="cancel">Отмена</div>
        <div class="delete">Да</div>
    </div>
    <?=bitrix_sessid_post()?>

</div>

<div class="modal-add-address">
    <span class="close-modal"></span>
    <div class="modal-add-address__inner">
        <div class="modal-add-address__left">
            <div class="top-title">Адрес доставки</div>
            <form type="" action="#" method="POST">
                <div class="form-block-address">
                    <input type="text" name="ADDRESS" id="modalAddressInput" placeholder="Город, улица, дом" autocomplete="off">
                    <div id="modalSuggestions" style="display:none;"></div>
                </div>

                <!-- Дополнительные поля (показываются после выбора адреса) -->
                <div class="form-block-extra" style="display:none;">
                    <div class="form-block-extra__row">
                        <input type="text" name="APARTMENT" placeholder="Кв / офис">
                        <input type="text" name="ENTRANCE" placeholder="Подъезд">
                    </div>
                    <div class="form-block-extra__row">
                        <input type="text" name="FLOOR" placeholder="Этаж">
                        <input type="text" name="INTERCOM" placeholder="Домофон">
                    </div>
                </div>

                <!-- Информация о доставке -->
                <div class="modal-delivery-info" style="display:none;">
                    <div class="modal-delivery-info__item">
                        <span class="modal-delivery-info__label">Время доставки</span>
                        <span class="modal-delivery-info__value delivery-time">—</span>
                    </div>
                    <div class="modal-delivery-info__item">
                        <span class="modal-delivery-info__label">Бесплатная доставка</span>
                        <span class="modal-delivery-info__value delivery-free">—</span>
                    </div>
                    <div class="modal-delivery-info__item">
                        <span class="modal-delivery-info__label">Мин. сумма заказа</span>
                        <span class="modal-delivery-info__value delivery-min-order">—</span>
                    </div>
                    <div class="modal-delivery-info__item">
                        <span class="modal-delivery-info__label">Стоимость доставки</span>
                        <span class="modal-delivery-info__value delivery-price-zone">—</span>
                    </div>
                    <div class="modal-delivery-info__note">При подтверждении заказа мы сообщим точное время</div>
                </div>

                <!-- Скрытые поля -->
                <input type="hidden" name="CITY" id="modalCityInput" value="">
                <input type="hidden" name="LAT" id="modalLatInput" value="">
                <input type="hidden" name="LON" id="modalLonInput" value="">
                <input type="hidden" name="ZONE_ID" id="modalZoneId" value="">

                <div class="button-modal">
                    <button type="submit" class="add-btn" disabled>Установить адрес</button>
                </div>
                <?=bitrix_sessid_post()?>
            </form>
        </div>
        <div class="modal-add-address__right">
            <div id="modalMap"></div>
        </div>
    </div>
</div>

