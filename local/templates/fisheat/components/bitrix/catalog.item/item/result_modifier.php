<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}

// Указываем, что компонент может кешироваться
$this->__component->SetResultCacheKeys([
    "CACHED_TPL",
    "ID",
    "IBLOCK_ID"
]);

// Добавляем в кеш информацию о том, что используется динамическая замена
$this->__component->arResult["CACHED_TPL"] = ob_get_contents();


?>