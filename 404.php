<?
include_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/urlrewrite.php');

CHTTP::SetStatus("404 Not Found");
@define("ERROR_404","Y");

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("title", "Страница не найдена");
$APPLICATION->SetTitle("Страница не найдена");?>

<div class="page-404">
    <div class="page-404__code" aria-hidden="true">404</div>
    <h2 class="page-404__title">Такой страницы нет</h2>
    <p class="page-404__text">
        Возможно, страница была удалена, переехала или в адресе опечатка.
        Загляните в каталог или вернитесь на главную — там всегда есть из чего выбрать.
    </p>
    <div class="page-404__actions">
        <a class="page-404__btn page-404__btn--primary" href="/">На главную</a>
        <a class="page-404__btn" href="/catalog/">Перейти в каталог</a>
    </div>
</div>
<?require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");?>
