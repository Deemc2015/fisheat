<?php

use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Loader;
use  Ldo\Develop\Product;
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
                        <?if($arBasketItem['VES']):?>
                            <div class="weight"><?=$arBasketItem['VES']?></div>
                        <?endif;?>
                    </div>
                    <?if($isMobile):?></div><?endif?>

                    <div class="amount-product-block">
                            <span class="minus"></span>
                            <span class="quantity-product"><?=$arBasketItem['QUANTITY']?></span>
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
            <??>
        </div>
        <div class="gifts-block">
            <div class="gifts-block__top">
                <i></i>
                <p>До подарка 1 уровня осталось еще <span>184 ₽</span></p>
            </div>
            <div class="gifts-block__items"></div>
        </div>
        <div class="promo-block">
            <h2>Применение скидок</h2>
            <div class="promo-block__line">
                <div class="promo-block__left">
                    <label for="code-1">
                        <input  type="radio" id="code-1" name="promo_id" value="promokod" >
                        <span></span>
                        Промокод
                    </label>
                    <label for="code-2">
                        <input  type="radio" id="code-2" name="promo_id" value="bonus" >
                        <span></span>
                        Оплата бонусами
                    </label>
                </div>
                <div class="promo-block__right">
                    <div class="promo-block__left-promokod">
                        <form action="#" class="promoChange">
                            <input type="text" name="promokod" id="promocode" required />
                            <button type="submit">Применить</button>
                        </form>

                    </div>

                </div>
            </div>


        </div>
        <?if($isMobile):?>
            <div class="delivery-block">
                <?if($arResult['DELIVERY_LIST']):?>
                    <div class="delivery-block__butons">
                        <?
                        $i == 1;
                        foreach($arResult['DELIVERY_LIST'] as $itemDelivery):
                            ?>

                            <label for="code-<?=$itemDelivery['ID']?>">
                                <input <?if($arParams['DEFAULT_DELIVERY_ID'] == $itemDelivery['ID']){echo 'checked';}?>  type="radio" id="code-<?=$itemDelivery['ID']?>" name="delivery_id" value="<?=$itemDelivery['ID']?>" <?=$itemDelivery['CHECKED'] ? 'checked' : ''?>>

                                <div class="delivery-name"><?=$itemDelivery['NAME']?></div>
                            </label>

                        <?endforeach;?>
                    </div>
                <?endif?>


                <div class="adress-user-list">
                    <div class="adress-user-list__item">
                        <label for="adress-user-list__item-name-1">
                            <input name="address_id" type="radio" id="adress-user-list__item-name-1" value="1">
                            <span></span>
                            Максима Горького ул, д. 44, кв. 90
                        </label>
                        <div class="adress-user-list__item-btn">
                            <div class="adress-user-list__item-btn-edit"></div>
                            <div class="adress-user-list__item-btn-delete"></div>
                        </div>
                    </div>
                    <div class="adress-user-list__item">
                        <label for="adress-user-list__item-name-2">
                            <input name="address_id" type="radio" id="adress-user-list__item-name-2" value="2">
                            <span></span>
                            Максима Горького ул, д. 44, кв. 90
                        </label>
                        <div class="adress-user-list__item-btn">
                            <div class="adress-user-list__item-btn-edit"></div>
                            <div class="adress-user-list__item-btn-delete"></div>
                        </div>
                    </div>
                </div>
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

        ?>
            <?if($fieldsTimeDelivery):
                ?>
                <div class="time-delivery">
                    <h2>Время доставки</h2>
                    <div class="time-delivery__item">
                        <label for="default_time">

                            <input id="default_time" type="radio"
                                   name="time_delivery"
                                   value="Y">
                            <span></span>
                            Как можно скорее
                        </label>
                        <label for="date_time">

                            <input id="date_time" type="radio"
                                   name="time_delivery"
                                   value="Y">
                            <span></span>
                            Выбрать дату и время
                        </label>
                    </div>

                    <?foreach($fieldsTimeDelivery as $itemTime):?>
                        <?if($itemTime['TYPE'] == 'Y/N'):?>
                            <input class="hidden-input default_time" id="<?=$itemTime['FORM_LABEL']?>" type="checkbox"
                                   name="<?=$itemTime['FORM_NAME']?>"
                                   value="Y">
                        <?else:?>
                            <input class="hidden-input date_time" id="<?=$itemTime['FORM_LABEL']?>" type="text"
                                   name="<?=$itemTime['FORM_NAME']?>"
                                   value="<?=$itemTime['NAME']?>">
                        <?endif?>

                    <?endforeach;?>
                </div>
            <?endif;?>
        </div>

    </div>
    <? // region?>
    <?
    if (\Bitrix\Main\Loader::includeModule('acrit.bonus')) {

        $arResultEx = [
                'ORDER_PRICE' => $arResult['PRICE'] - $arResult['DELIVERY_PRICE'],
                'DELIVERY_PRICE' => $arResult['DELIVERY_PRICE'],
                'BASKET' => $arResult['BASKET'],
            ] + $component->order->getFieldValues();
        $resultBonus = \Acrit\Bonus\Profile::runPayProfiles($arResultEx);
        echo "<pre>";
        print_r($resultBonus);
        echo "</pre>";
        ?>
    <?

    echo \Acrit\Bonus\Core::getPayOrderBlock($arResultEx['BONUSPAY'], /** @lang JavaScript */ 'AcritBonusPayBonusBtn();');
    ?>
    <script>
        function AcritBonusPayBonusBtn() {
            // не отправляем заказ на сохранение, а перегружаем страницу
            $(".send_open_source_order_flag").val('n');
            // эмулируем отправку формы заказа, чтобы бонусы подхватились
            $(".send_open_source_order_submit").click();
            // отключаем кнопку "оплатить бонусами" от использования
            $(this).css('pointer-events', 'none');
        }
    </script>
    <?
        // распечатайте $arResultEx['BONUSPAY'] - его можно использовать в дальнейшем для вывода данных,
        // в частности: $arResultEx['BONUSPAY']['USER_VALUE_CURRENCY'] - это сколько бонусов человек решил использовать в оплату заказа
        // \Bitrix\Main\Diag\Debug::dump($arResultEx);

        // 4/ Получение бонусов за этот заказ (с учетом фильтров в профиле начисления бонусов за заказ)
        $arResultEx['BONUS']['ORDER'] = \Acrit\Bonus\Core::getCartOrderBonus('ORDER', $arResultEx);
        //d($arResultEx);
    }
    ?>
    <? // endregion?>


    <div class="right-order-page">
        <?if(!$isMobile):?>
        <div class="delivery-block">
                <?if($arResult['DELIVERY_LIST']):?>
                    <div class="delivery-block__butons">
                <?
                $i == 1;
                foreach($arResult['DELIVERY_LIST'] as $itemDelivery):
                    ?>
                        <label for="code-<?=$itemDelivery['ID']?>">
                            <input <?if($arParams['DEFAULT_DELIVERY_ID'] == $itemDelivery['ID']){echo 'checked';}?>  type="radio" id="code-<?=$itemDelivery['ID']?>" name="delivery_id" value="<?=$itemDelivery['ID']?>" <?=$itemDelivery['CHECKED'] ? 'checked' : ''?>>
                            <div class="delivery-name"><?=$itemDelivery['NAME']?></div>
                        </label>

                <?endforeach;?>
                    </div>
                <?endif?>


            <div class="adress-user-list">
                <div class="adress-user-list__item">
                    <label for="adress-user-list__item-name-1">
                        <input name="address_id" type="radio" id="adress-user-list__item-name-1" value="1">
                        <span></span>
                        Максима Горького ул, д. 44, кв. 90
                    </label>
                    <div class="adress-user-list__item-btn">
                        <div class="adress-user-list__item-btn-edit"></div>
                        <div class="adress-user-list__item-btn-delete"></div>
                    </div>
                </div>
                <div class="adress-user-list__item">
                    <label for="adress-user-list__item-name-2">
                        <input name="address_id" type="radio" id="adress-user-list__item-name-2" value="2">
                        <span></span>
                        Максима Горького ул, д. 44, кв. 90
                    </label>
                    <div class="adress-user-list__item-btn">
                        <div class="adress-user-list__item-btn-edit"></div>
                        <div class="adress-user-list__item-btn-delete"></div>
                    </div>
                </div>
            </div>
            <div class="addAdress-user">Добавить адрес</div>
        </div>
        <?endif?>
        <div class="total-order-block">
            <h2>Стоимость заказа</h2>
            <div class="total-order-block__line delivery-text">
                <span>Адрес доставки</span><span>Максима Горького ул, д. 44, кв. 90</span>
            </div>
            <div class="total-order-block__line delivery-text">
                <span>Сумма доставки</span><span><?=$arResult['DELIVERY_PRICE_DISPLAY']?></span>
            </div>
            <div class="total-order-block__line">
                <span>Сумма заказа</span><span><?=$arResult['SUM_BASE_DISPLAY']?></span>
            </div>
            <div class="total-order-block__line">
                <span>Скидка</span><span><?=$arResult['DISCOUNT_VALUE_DISPLAY']?></span>
            </div>
            <div class="total-order-block__line">
                <span>Начислено бонусов</span><span><?=$arResultEx['BONUS']['ORDER']['VALUE_FORMAT']?></span>
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
    <br>

    <br>
    <br>
    <? // endregion?>

</form>
