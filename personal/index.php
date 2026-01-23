<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("title", "Контактные данные");
$APPLICATION->SetTitle("Контактные данные");
?>
<?$APPLICATION->IncludeComponent(
    "ldo:profile.data",
    "",
    Array()
);?>
</section>
<section id="privilegies">
    <div class="privilegies-block">
        <div class="bonus-block">
            <span class="bonus-block__title">Бонусы</span>
            <span class="bonus-block__count"><?$APPLICATION->IncludeComponent("acrit.bonus:bonus.account", "view-bonus", Array(

                ),
                    false
                );?></span>
            <span class="bonus-block__after">1 бонус = 1 ₽</span>
        </div>
        <div class="cashback-block">
            <span class="cashback-block__title">Кешбек</span>
            <span class="cashback-block__count">5%</span>
            <span class="cashback-block__after">5% от заказа</span>
        </div>
    </div>
    <a href="/" class="bonus-detail">Узнать, как получить бонусы</a>
</section>

<?/*require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");*/?>