<?php

use Bitrix\Main\Error;
use Bitrix\Sale\Order;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
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
/** @var OpenSourceOrderComponent $component */
CJSCore::Init(['jquery']);
$this->addExternalJs($templateFolder . '/selectize/selectize.js');
$this->addExternalCss($templateFolder . '/selectize/selectize.default.css');
$this->addExternalCss($templateFolder . '/selectize/selectize.dropdown.css');
/*Определение мобильного устройства*/
$isMobile = \Bitrix\Main\Loader::includeModule('conversion') && ($md=new \Bitrix\Conversion\Internals\MobileDetect) && $md->isMobile();

?>

    <?php if (count($component->errorCollection) > 0): ?>
        <div class="errors_list">
            <?php foreach ($component->errorCollection as $error):
                /**
                 * @var Error $error
                 */
                ?>

            <?if($error->getMessage() == 'Корзина пуста'):?>
            <div class="empty-cart">
                <div class="center-empty-cart">
                    <div class="title">Как только выберете блюда,<br> они появятся здесь</div>
                    <div class="buttons-block">
                        <a href="/actions/" class="akciya">Посмотреть акции</a>
                        <a href="/catalog/" class="return-catalog">Вернуться в каталог</a>
                    </div>
                </div>
            </div>
            <?else:?>
                <div class="error"><?= $error->getMessage() ?></div>
            <?endif?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($arResult['ID']) && $arResult['ID'] > 0) {
        include 'done.php';
    } elseif ($component->order instanceof Order && count($component->order->getBasket()) > 0) {
        include 'form.php';
    } ?>


