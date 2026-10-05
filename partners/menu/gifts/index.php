<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

// Функционал "Подарки к заказам" перенесён в раздел Маркетинг
// (таб "Подарки к заказу" на странице /partners/marketing/).
// Старый адрес сохранён как редирект для обратной совместимости.
LocalRedirect('/partners/marketing/?tab=gifts');
?>
