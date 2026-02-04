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
                <div class="delete-order">Очистить корзину</div>
            </div>
            <?foreach ($arResult['BASKET'] as $arBasketItem):?>
                <div class="product-list__item" data-id="<?=$arBasketItem['PRODUCT_ID']?>">
                    <?if(Loader::IncludeModule('ldo.develop')):?>
                    <div class="image-product">
                        <img src="<?=Product::getImageById($arBasketItem['PRODUCT_ID'])?>" alt="<?=$arBasketItem['NAME']?>">
                    </div>
                    <?endif?>
                    <div class="name-product"><?=$arBasketItem['NAME']?></div>
                    <div class="price-product">
                        <div class="price-product__sum"><?=$arBasketItem['SUM_DISPLAY']?></div>
                        <?if($arBasketItem['VES']):?>
                            <div class="weight"><?=$arBasketItem['VES']?></div>
                        <?endif;?>
                    </div>

                    <div class="amount-product-block">
                        <span class="minus"></span>
                        <span class="quantity-product"><?=$arBasketItem['QUANTITY']?></span>
                        <span class="plus"></span>
                    </div>
                </div>
            <?endforeach?>

            <div class="count-people-block">
                <div class="count-people-block__title">Указать кол-во персон</div>
                <div class="checked-button"></div>
                <div class="count-people-block__count">
                    <span class="minus"></span>
                    <input readonly name="<?=$arResult['PROPERTIES']['COUNT_PERSON']['FORM_NAME']?>" id="<?=$arResult['PROPERTIES']['COUNT_PERSON']['FORM_LABEL']?>" type="<?=$arResult['PROPERTIES']['COUNT_PERSON']['TYPE']?>" class="count-people-block__count-num" value="<?=$arResult['PROPERTIES']['COUNT_PERSON']['VALUE']?>">
                    <span class="plus"></span>
                </div>

                <?
                //print_r($arResult['PROPERTIES']['COUNT_PERSON']);
                ?>
            </div>

        </div>
        <div class="comments-block">
            <div class="comments-block__top">
                <div class="comments-block__top-title">Комментарий кухне</div>
                <div class="comments-block__top-icon"></div>
            </div>
            <??>
        </div>

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
            <?if(1==1):?>

            <?endif;?>
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





    <?
    echo "<pre>";
    print_r($arResult['PROPERTIES']);
    echo "</pre>";
    ?>
    <table>
        <?php
        unset($arResult['PROPERTIES']['COUNT_PERSON']);

        foreach ($arResult['PROPERTIES'] as $propCode => $arProp): ?>
            <tr>
                <td>
                    <label for="<?=$arProp['FORM_LABEL']?>"><?=$arProp['NAME']?></label>
                    <? foreach ($arProp['ERRORS'] as $error):
                        /** @var Error $error */
                        ?>
                        <div class="error"><?=$error->getMessage()?></div>
                    <? endforeach; ?>
                </td>
                <td>
                    <?php
                    switch ($arProp['TYPE']):
                        case 'LOCATION':
                            ?>
                            <div class="location">
                                <select class="location-search" name="<?=$arProp['FORM_NAME']?>"
                                        id="<?=$arProp['FORM_LABEL']?>">
                                    <option
                                            data-data='<? echo Json::encode($arProp['LOCATION_DATA']) ?>'
                                            value="<?=$arProp['VALUE']?>"><?=$arProp['LOCATION_DATA']['label']?></option>
                                </select>
                            </div>
                            <?
                            break;

                        case 'ENUM':
                            foreach ($arProp['OPTIONS'] as $code => $name):?>
                                <label class="enum-option">
                                    <input type="radio" name="<?=$arProp['FORM_NAME']?>" value="<?=$code?>">
                                    <?=$name?>
                                </label>
                            <?endforeach;
                            break;

                        case 'DATE':
                            $APPLICATION->IncludeComponent(
                                'bitrix:main.calendar',
                                '',
                                [
                                    'SHOW_INPUT' => 'Y',
                                    'FORM_NAME' => 'os-order-form',
                                    'INPUT_NAME' => $arProp['FORM_NAME'],
                                    'INPUT_VALUE' => $arProp['VALUE'],
                                    'SHOW_TIME' => 'Y',
                                    //'HIDE_TIMEBAR' => 'Y',
                                    'INPUT_ADDITIONAL_ATTR' => 'placeholder="выберите дату"'
                                ]
                            );
                            break;

                        case 'Y/N':
                            ?>
                            <input id="<?=$arProp['FORM_LABEL']?>" type="checkbox"
                                   name="<?=$arProp['FORM_NAME']?>"
                                   value="Y">
                            <?
                            break;

                        default:
                            ?>
                            <input id="<?=$arProp['FORM_LABEL']?>" type="text"
                                   name="<?=$arProp['FORM_NAME']?>"
                                   value="<?=$arProp['VALUE']?>">
                        <? endswitch; ?>
                </td>
            </tr>
        <? endforeach; ?>
    </table>


        <div id="acrit-bonus-paysystem" class="bx-soa-section">
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
                            print_r($arResultEx );

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
        </div>




    <h2><?=Loc::getMessage('OPEN_SOURCE_ORDER_TEMPLATE_ORDER_TOTAL_TITLE')?></h2>
    <h3><?=Loc::getMessage('OPEN_SOURCE_ORDER_TEMPLATE_PRODUCTS_PRICES_TITLE')?>:</h3>
    555
    <table>
        <tr>
            <td><?=Loc::getMessage('OPEN_SOURCE_ORDER_TEMPLATE_PRODUCTS_BASE_PRICE')?></td>
            <td><?=$arResult['PRODUCTS_BASE_PRICE_DISPLAY']?></td>
        </tr>
        <tr>
            <td><?=Loc::getMessage('OPEN_SOURCE_ORDER_TEMPLATE_PRODUCTS_PRICE')?></td>
            <td><?=$arResult['PRODUCTS_PRICE_DISPLAY']?></td>
        </tr>
        <tr>
            <td><?=Loc::getMessage('OPEN_SOURCE_ORDER_TEMPLATE_PRODUCTS_DISCOUNT')?></td>
            <td><?=$arResult['PRODUCTS_DISCOUNT_DISPLAY']?></td>
        </tr>
    </table>

    <h3><?=Loc::getMessage('OPEN_SOURCE_ORDER_TEMPLATE_DELIVERY_PRICES_TITLE')?>:</h3>
    432
    <table>
        <tr>
            <td><?=Loc::getMessage('OPEN_SOURCE_ORDER_TEMPLATE_DELIVERY_BASE_PRICE')?></td>
            <td><?=$arResult['DELIVERY_BASE_PRICE_DISPLAY']?></td>
        </tr>
        <tr>
            <td><?=Loc::getMessage('OPEN_SOURCE_ORDER_TEMPLATE_DELIVERY_PRICE')?></td>
            <td><?=$arResult['DELIVERY_PRICE_DISPLAY']?></td>
        </tr>
        <tr>
            <td><?=Loc::getMessage('OPEN_SOURCE_ORDER_TEMPLATE_DELIVERY_DISCOUNT')?></td>
            <td><?=$arResult['DELIVERY_DISCOUNT_DISPLAY']?></td>
        </tr>
    </table>

    <h3><?=Loc::getMessage('OPEN_SOURCE_ORDER_TEMPLATE_SUM_TITLE')?>:</h3>
    <table>
        <tr>
            <td><?=Loc::getMessage('OPEN_SOURCE_ORDER_TEMPLATE_TOTAL_BASE_PRICE')?></td>
            <td><?=$arResult['SUM_BASE_DISPLAY']?></td>
        </tr>
        <tr>
            <td><?=Loc::getMessage('OPEN_SOURCE_ORDER_TEMPLATE_TOTAL_DISCOUNT')?></td>
            <td><?=$arResult['DISCOUNT_VALUE_DISPLAY']?></td>
        </tr>

        <? // region 5/ бонус за заказ?>
        <? if ($arResultEx['BONUS']['ORDER']['VALUE']) { ?>
            <tr style="font-weight:bold">
                <td>Бонус за заказ:</td>
                <td><?=$arResultEx['BONUS']['ORDER']['VALUE_FORMAT']?></td>
            </tr>
        <? } ?>
        <? //endregion?>

        <? // region 6/ вывели кол-во оплаченных и пересчитали сумму?>
        <? if ($arResultEx['BONUSPAY']['USER_VALUE']) { ?>
            <tr style="font-weight:bold">
                <td>Оплачено бонусами:</td>
                <td><?=SaleFormatCurrency($arResultEx['BONUSPAY']['USER_VALUE_CURRENCY'], $arResult['CURRENCY'])?></td>
            </tr>
            <?
            // отнимаем от суммы уже оплаченную часть
            $arResult['SUM_DISPLAY'] = SaleFormatCurrency(
                $arResult['SUM'] - $arResultEx['BONUSPAY']['USER_VALUE_CURRENCY'],
                $arResult['CURRENCY']
            );
            ?>
        <? } ?>
        <? // endregion?>

        <tr>
            <td><?=Loc::getMessage('OPEN_SOURCE_ORDER_TEMPLATE_TOTAL_PRICE')?></td>
            <td><?=$arResult['SUM_DISPLAY']?></td>
        </tr>
    </table>

    <? // region 7/ проставили классы у флага и кнопки-отправки ?>
    <input type="hidden" name="save" value="y" class="send_open_source_order_flag">
    <br>

    <br>
    <br>
    <? // endregion?>

</form>
