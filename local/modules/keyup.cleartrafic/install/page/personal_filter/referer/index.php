<?php

define("NO_KEEP_STATISTIC", true);
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Keyup\Cleartrafic\User;
use Keyup\Cleartrafic\Model\RuleTable;

if (!Loader::IncludeModule('keyup.cleartrafic')) {
    echo "Не установлен модуль фильтрации трафика";
}

global $USER, $APPLICATION;

if (!$USER->isAuthorized()) {
    LocalRedirect(SITE_DIR . 'auth/');
}

$result = new User;
$permission = $result->hasPermission();

$ruleType = RuleTable::TYPE_REFERER;

$pageTitle = 'Рефереры';
include dirname(__DIR__) . '/header.php';
?>
<? if ($permission): ?><button id="addIp" type="button">Добавить</button><? endif; ?>

<? $APPLICATION->IncludeComponent(
    'keyup:cleartrafic.list',
    '',
    array(
        'ENTITY'        => 'rules',
        'RULE_TYPE'     => $ruleType,
        'ROWS_PER_PAGE' => 20,
        'ACTIONS'       => 'Y',
        'PAGEN_ID'      => 'page',
    )
); ?>

<?php include dirname(__DIR__) . '/footer.php'; ?>

<div class="wrp"></div>
<div class="modalAdd">
    <div class="modal-title">Добавить разрешённый реферер</div>
    <p class="modal-subtitle">Переходы только с этих доменов не будут считаться подозрительными.</p>
    <form action="#" method="post">
        <span class="close"><svg width="30px" height="30px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
  <path fill="none" stroke="#000000" stroke-width="2" d="M7,7 L17,17 M7,17 L17,7"/>
</svg></span>
        <input type="hidden" class="entity" value="rule">
        <input type="hidden" class="ruleType" value="referer">
        <input type="text" class="VALUE" required placeholder="Например: yandex.ru">
        <input type="text" class="COMMENT" placeholder="Комментарий (необязательно)">
        <button type="submit">Сохранить</button>
    </form>
    <span class="error"></span>
</div>

<div class="modalEdit">
    <div class="modal-title">Изменить реферер</div>
    <p class="modal-subtitle">Обновите домен в списке разрешённых источников.</p>
    <form action="#" method="post">
        <span class="close"><svg width="30px" height="30px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
  <path fill="none" stroke="#000000" stroke-width="2" d="M7,7 L17,17 M7,17 L17,7"/>
</svg></span>
        <input type="hidden" class="entity" value="rule">
        <input type="hidden" class="ruleType" value="referer">
        <input type="hidden" class="idElement">
        <input type="text" class="VALUE" required placeholder="Например: yandex.ru">
        <input type="text" class="COMMENT" placeholder="Комментарий (необязательно)">
        <button type="submit">Сохранить</button>
    </form>
    <span class="error"></span>
</div>

<script>
    $(document).ready(function () {
        $('#addIp').click(function () {
            $('.modalAdd,.wrp').addClass('show');
        });

        $('.modalAdd .close, .modalEdit .close').click(function () {
            $(this).closest('.modalAdd, .modalEdit').add('.wrp').removeClass('show');
        });
    });
</script>
