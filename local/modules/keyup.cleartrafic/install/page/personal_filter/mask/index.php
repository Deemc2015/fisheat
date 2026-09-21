<?php

define("NO_KEEP_STATISTIC", true);
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Keyup\Cleartrafic\User;
use Keyup\Cleartrafic\Rules;
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

$ruleType = RuleTable::TYPE_MASK;
$statusActive = Rules::getMasksActiveState();

$pageTitle = 'Маски подсетей';
include dirname(__DIR__) . '/header.php';
?>
<div id="mask_page">
    <? if ($permission): ?>
        <button id="addIp" type="button">Добавить</button>
        <button id="removeAll" type="button">Очистить данные</button>
        <? if ($statusActive !== null): ?>
            <? if ($statusActive): ?>
                <button id="stop" type="button">Остановить проверку</button>
            <? else: ?>
                <button id="start" type="button">Запустить проверку</button>
            <? endif; ?>
        <? endif; ?>
    <? endif; ?>

    <? $APPLICATION->IncludeComponent(
        'keyup:cleartrafic.list',
        '',
        array(
            'ENTITY'        => 'rules',
            'RULE_TYPE'     => $ruleType,
            'ROWS_PER_PAGE' => 50,
            'ACTIONS'       => 'Y',
            'PAGEN_ID'      => 'page',
        )
    ); ?>
</div>

<?php include dirname(__DIR__) . '/footer.php'; ?>

<div class="wrp"></div>

<div class="modalAdd">
    <div class="modal-title">Добавить маску подсети</div>
    <p class="modal-subtitle">Укажите IP-адрес сети и длину маски (от 0 до 32) либо загрузите список из файла.</p>

    <form action="#" method="post">
        <span class="close"><svg width="30px" height="30px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
  <path fill="none" stroke="#000000" stroke-width="2" d="M7,7 L17,17 M7,17 L17,7"/>
</svg></span>
        <input type="hidden" class="entity" value="rule">
        <input type="hidden" class="ruleType" value="mask">
        <input type="text" class="VALUE" required placeholder="IP-адрес сети, например 192.168.0.0">
        <input type="text" class="MASK" required placeholder="Маска подсети, например 24">
        <button type="submit">Сохранить</button>
    </form>

    <div class="pf-or">или загрузите из файла (.txt, строки вида IP/маска, до 5000 записей)</div>

    <div class="spinner"></div>
    <form action="#" method="post" class="import-file" data-custom="1" data-rule-type="mask">
        <input type="file" name="FILE" accept=".txt" required />
        <button type="submit" class="pf-file-btn">Загрузить</button>
    </form>
    <span class="import-result"></span>
    <span class="error"></span>
</div>

<div class="modalEdit">
    <div class="modal-title">Изменить маску подсети</div>
    <p class="modal-subtitle">Укажите IP-адрес сети и длину маски (от 0 до 32).</p>
    <form action="#" method="post">
        <span class="close"><svg width="30px" height="30px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
  <path fill="none" stroke="#000000" stroke-width="2" d="M7,7 L17,17 M7,17 L17,7"/>
</svg></span>
        <input type="hidden" class="entity" value="rule">
        <input type="hidden" class="ruleType" value="mask">
        <input type="hidden" class="idElement">
        <input type="text" class="VALUE" required placeholder="IP-адрес сети, например 192.168.0.0">
        <input type="text" class="MASK" required placeholder="Маска подсети, например 24">
        <button type="submit">Сохранить</button>
    </form>
    <span class="error"></span>
</div>

<script>
    $(document).ready(function () {
        var sessid = '<?= bitrix_sessid() ?>';

        $('#addIp').click(function () {
            $('.modalAdd,.wrp').addClass('show');
        });

        $('.modalAdd .close, .modalEdit .close').click(function () {
            $(this).closest('.modalAdd, .modalEdit').add('.wrp').removeClass('show');
        });

        /* Очистка масок */
        $('#removeAll').click(function () {
            if (!confirm('Вы действительно хотите очистить данные?')) {
                return;
            }

            $.post('/personal_filter/ajax.php', {
                TYPE: 'DELETEALL',
                RULE_TYPE: 'mask',
                sessid: sessid
            }).done(function () {
                location.reload();
            });
        });

        /* Управление активностью проверки масок */
        $('#stop, #start').click(function () {
            var isStop = $(this).attr('id') === 'stop';
            var question = isStop
                ? 'Вы действительно хотите остановить проверку?'
                : 'Вы действительно хотите возобновить проверку?';

            if (!confirm(question)) {
                return;
            }

            $.post('/personal_filter/ajax.php', {
                TYPE: isStop ? 'STOP' : 'START',
                sessid: sessid
            }).done(function () {
                location.reload();
            });
        });
    });
</script>
