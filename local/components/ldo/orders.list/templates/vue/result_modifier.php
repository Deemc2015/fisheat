<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * Подготовка данных для Vue-приложения (шаблон "vue").
 *
 * Объекты Bitrix\Main\Type\DateTime не сериализуются в JSON корректно,
 * поэтому приводим даты к строке "d.m.Y H:i" заранее.
 */
if (!empty($arResult['ORDERS']) && is_array($arResult['ORDERS'])) {
    foreach ($arResult['ORDERS'] as &$order) {
        if (isset($order['DATE_INSERT']) && is_object($order['DATE_INSERT']) && method_exists($order['DATE_INSERT'], 'format')) {
            $order['DATE_INSERT'] = $order['DATE_INSERT']->format('d.m.Y H:i');
        }
    }
    unset($order);
}
