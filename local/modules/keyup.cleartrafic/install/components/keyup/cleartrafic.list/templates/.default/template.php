<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var CMain $APPLICATION */
/** @var array $arParams */
/** @var array $arResult */

use Keyup\Cleartrafic\User;
use Keyup\Cleartrafic\Model\RuleTable;
use Keyup\Cleartrafic\Model\VisitTable;

$result = new User;
$permission = $result->hasPermission();

$actions = isset($arParams['ACTIONS']) && $arParams['ACTIONS'] === 'Y';
$entity = $arResult['ENTITY'];
$ruleType = $arResult['RULE_TYPE'];
$isMask = ($ruleType === RuleTable::TYPE_MASK);
?>
<div class="reports-result-list-wrap">
    <div class="report-table-wrap">
        <table cellspacing="0" class="reports-list-table" id="report-result-table">
            <thead>
            <tr>
                <?php foreach ($arResult['COLUMNS'] as $colCode => $colTitle): ?>
                    <th class="reports-head-cell" colId="<?= htmlspecialcharsbx($colCode) ?>">
                        <div class="reports-head-cell">
                            <span class="reports-head-cell-title"><?= htmlspecialcharsbx($colTitle) ?></span>
                        </div>
                    </th>
                <?php endforeach; ?>
                <?php if ($actions): ?>
                    <th class="reports-last-column">
                        <div class="reports-head-cell"><span class="reports-head-cell-title">Действия</span></div>
                    </th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($arResult['ROWS'])): ?>
                <tr class="reports-list-item">
                    <td colspan="<?= count($arResult['COLUMNS']) + ($actions ? 1 : 0) ?>" class="pf-empty">
                        Пока нет данных для отображения.
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($arResult['ROWS'] as $row): ?>
                <?php
                $rowClass = '';

                if ($entity === 'visits') {
                    switch ($row['MATCH_TYPE']) {
                        case VisitTable::TYPE_BLACK:
                            $rowClass = 'black';
                            break;
                        case VisitTable::TYPE_GRAY:
                        case VisitTable::TYPE_MASK:
                        case VisitTable::TYPE_REFERER:
                            $rowClass = 'gray';
                            break;
                    }

                    if ($row['CAPTCHA_PASSED'] === 'Y') {
                        $rowClass .= ' checkCaptcha';
                    }
                }
                ?>
                <tr class="reports-list-item <?= $rowClass ?>">
                    <?php foreach ($arResult['COLUMNS'] as $colCode => $colTitle): ?>
                        <?php
                        $value = isset($row[$colCode]) ? $row[$colCode] : '';

                        if ($value instanceof \Bitrix\Main\Type\DateTime) {
                            $value = $value->toString();
                        }
                        ?>
                        <td class="reports-list-item-cell <?= htmlspecialcharsbx($colCode) ?>" title="<?= htmlspecialcharsbx((string)$value) ?>"><?= htmlspecialcharsbx((string)$value) ?></td>
                    <?php endforeach; ?>

                    <?php if ($actions && $permission): ?>
                        <td class="button-list">
                            <span class="edit-button"
                                  data-entity="<?= htmlspecialcharsbx($entity) ?>"
                                  data-rule-type="<?= htmlspecialcharsbx($ruleType) ?>"
                                  data-id="<?= (int)$row['ID'] ?>"
                                  data-value="<?= htmlspecialcharsbx(isset($row['VALUE']) ? $row['VALUE'] : (isset($row['EMAIL']) ? $row['EMAIL'] : '')) ?>"
                                  data-mask="<?= isset($row['MASK']) && $row['MASK'] !== null ? (int)$row['MASK'] : '' ?>"
                                  data-comment="<?= htmlspecialcharsbx(isset($row['COMMENT']) ? $row['COMMENT'] : '') ?>">Изменить</span>

                            <span class="delete-btn"
                                  data-entity="<?= htmlspecialcharsbx($entity) ?>"
                                  data-id="<?= (int)$row['ID'] ?>">Удалить</span>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($arParams['ROWS_PER_PAGE'] > 0): ?>
        <?php
        $APPLICATION->IncludeComponent(
            'bitrix:main.pagenavigation',
            '',
            array(
                'NAV_OBJECT' => $arResult['NAV_OBJECT'],
                'SEF_MODE'   => 'N',
            ),
            false
        );
        ?>
    <?php endif; ?>
</div>

<script>
(function () {
    var ajaxUrl = '/personal_filter/ajax.php';
    var sessid = '<?= bitrix_sessid() ?>';

    function request(data, done) {
        data.sessid = sessid;

        $.post(ajaxUrl, data, null, 'json').done(function (response) {
            done(response);
        });
    }

    /* Показ ошибки сохранения (дубликат и др.) в модальном окне формы */
    function showFormError($form, response) {
        var message = (response && response.message)
            ? response.message
            : 'Не удалось сохранить запись';

        $form.closest('.modalAdd, .modalEdit').find('.error').text(message);
    }

    /* Ответ-объект означает ошибку, число или строка — успешное сохранение */
    function handleFormResponse($form, response) {
        /* Если jQuery не разобрал JSON, пробуем разобрать сами */
        if (typeof response === 'string' && response.indexOf('{') === 0) {
            try {
                response = JSON.parse(response);
            } catch (e) {
                response = null;
            }
        }

        if (response && typeof response === 'object') {
            showFormError($form, response);
            return;
        }

        if (response) {
            location.reload();
        }
    }

    $(document).on('click', '#addIp', function () {
        $('.modalAdd').find('.error').text('');
    });

    $(document).on('click', '.delete-btn', function () {
        var $btn = $(this);

        if (!confirm('Вы действительно хотите удалить запись?')) {
            return;
        }

        request({
            TYPE: 'DELETE',
            ENTITY: $btn.data('entity'),
            ID: $btn.data('id')
        }, function () {
            location.reload();
        });
    });

    $(document).on('click', '.edit-button', function () {
        var $btn = $(this);
        var $modal = $('.modalEdit');

        if (!$modal.length) {
            return;
        }

        $modal.find('.idElement').val($btn.data('id'));
        $modal.find('.entity').val($btn.data('entity'));
        $modal.find('.ruleType').val($btn.data('rule-type'));
        $modal.find('.VALUE').val($btn.data('value'));

        if ($modal.find('.MASK').length) {
            $modal.find('.MASK').val($btn.data('mask'));
        }

        if ($modal.find('.COMMENT').length) {
            $modal.find('.COMMENT').val($btn.data('comment'));
        }

        $modal.find('.error').text('');
        $modal.add('.wrp').addClass('show');
    });

    /* Универсальная отправка формы добавления */
    $(document).on('submit', '.modalAdd form', function (e) {
        e.preventDefault();

        var $form = $(this);

        /* Формы с собственной логикой отправки пропускаем */
        if ($form.data('custom')) {
            return;
        }

        $form.closest('.modalAdd').find('.error').text('');

        request({
            TYPE: 'ADD',
            ENTITY: $form.find('.entity').val() || 'rule',
            RULE_TYPE: $form.find('.ruleType').val() || '',
            VALUE: $form.find('.VALUE').val() || '',
            MASK: $form.find('.MASK').val() || '',
            COMMENT: $form.find('.COMMENT').val() || ''
        }, function (response) {
            handleFormResponse($form, response);
        });
    });

    /* Универсальная отправка формы редактирования */
    $(document).on('submit', '.modalEdit form', function (e) {
        e.preventDefault();

        var $form = $(this);

        $form.closest('.modalEdit').find('.error').text('');

        request({
            TYPE: 'EDIT',
            ENTITY: $form.find('.entity').val() || 'rule',
            RULE_TYPE: $form.find('.ruleType').val() || '',
            ID: $form.find('.idElement').val() || '',
            VALUE: $form.find('.VALUE').val() || '',
            MASK: $form.find('.MASK').val() || '',
            COMMENT: $form.find('.COMMENT').val() || ''
        }, function (response) {
            handleFormResponse($form, response);
        });
    });
})();
</script>
