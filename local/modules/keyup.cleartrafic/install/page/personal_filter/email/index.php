<?php

define("NO_KEEP_STATISTIC", true);
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Keyup\Cleartrafic\User;

if (!Loader::IncludeModule('keyup.cleartrafic')) {
    echo "Не установлен модуль фильтрации трафика";
}

global $USER, $APPLICATION;

if (!$USER->isAuthorized()) {
    LocalRedirect(SITE_DIR . 'auth/');
}

$result = new User;
$permission = $result->hasPermission();

if (!$permission) {
    ?>
    <p>Доступ запрещен</p>
    <?php
    die();
}

$pageTitle = 'Настройки оповещения';
include dirname(__DIR__) . '/header.php';
?>
<button id="addIp" type="button">Добавить email</button>

<? $APPLICATION->IncludeComponent(
    'keyup:cleartrafic.list',
    '',
    array(
        'ENTITY'        => 'emails',
        'ROWS_PER_PAGE' => 20,
        'ACTIONS'       => 'Y',
        'PAGEN_ID'      => 'page',
    )
); ?>

<?php include dirname(__DIR__) . '/footer.php'; ?>

<div class="wrp"></div>
<div class="modalAdd">
    <div class="modal-title">Добавить e-mail для оповещений</div>
    <p class="modal-subtitle">На этот адрес будут приходить уведомления о событиях фильтрации.</p>
    <form action="#" method="post">
        <span class="close"><svg width="30px" height="30px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
  <path fill="none" stroke="#000000" stroke-width="2" d="M7,7 L17,17 M7,17 L17,7"/>
</svg></span>
        <input type="hidden" class="entity" value="email">
        <input type="text" class="VALUE" required placeholder="Введите email">
        <button type="submit">Сохранить</button>
    </form>
    <span class="error"></span>
</div>

<div class="modalEdit">
    <div class="modal-title">Изменить e-mail</div>
    <p class="modal-subtitle">Обновите адрес получателя уведомлений.</p>
    <form action="#" method="post">
        <span class="close"><svg width="30px" height="30px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
  <path fill="none" stroke="#000000" stroke-width="2" d="M7,7 L17,17 M7,17 L17,7"/>
</svg></span>
        <input type="hidden" class="entity" value="email">
        <input type="hidden" class="idElement">
        <input type="text" class="VALUE" required placeholder="Введите email">
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
