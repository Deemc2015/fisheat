<?
/**
 * Проверка авторизации для страниц раздела /partners/.
 * Подключается после require prolog_before.
 * Если пользователь не авторизован — редирект на страницу входа (/partners/).
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
global $USER;

if (!$USER->IsAuthorized()) {
    LocalRedirect('/partners/');
}
