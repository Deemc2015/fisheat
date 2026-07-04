<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
    die();
}

// Получаем объект компонента
$cp = $this->__component;

addMessage2Log($cp);

if (is_object($cp)) {
    // Добавляем ключ в кеш
    $cp->SetResultCacheKeys(array("CACHED_TPL"));

    // Прямое добавление в arResultCacheKeys (запасной вариант)
    if (!in_array('CACHED_TPL', $cp->arResultCacheKeys)) {
        $cp->arResultCacheKeys[] = 'CACHED_TPL';
    }
}

?>