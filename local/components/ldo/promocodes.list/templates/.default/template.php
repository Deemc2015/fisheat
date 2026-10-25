<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arParams */
/** @var array $arResult */
/** @var CBitrixComponentTemplate $this */

// Подключаем ассеты с версией файла: иначе браузер может использовать
// закешированный CSS/JS после правок.
$assetFolder = $this->GetFolder();
$styleVersion = (int)@filemtime(__DIR__ . '/style.css');
$scriptVersion = (int)@filemtime(__DIR__ . '/script.js');

$sessid = (string)$arResult['SESSID'];
$siteId = (string)$arResult['SITE_ID'];
$promos = (array)$arResult['PROMOS'];
$types = (array)$arResult['TYPES'];
$couponTypes = (array)$arResult['COUPON_TYPES'];
$sites = (array)$arResult['SITES'];
?>
<link rel="stylesheet" href="<?= $assetFolder ?>/style.css?v=<?= $styleVersion ?>">
<div class="p-promo" id="p-promo" data-sessid="<?= htmlspecialcharsbx($sessid) ?>" data-site="<?= htmlspecialcharsbx($siteId) ?>">

    <? if ($arResult['ERROR'] !== ''): ?>
        <div class="p-promo__message p-promo__message--error"><?= htmlspecialcharsbx($arResult['ERROR']) ?></div>
    <? endif; ?>

    <div class="p-section">
        <div class="p-section__header">
            <h2 class="p-section__title">Промокоды</h2>

            <div class="p-promo__toolbar">
                <? if (!empty($arResult['MULTISITE'])): ?>
                    <label class="p-promo__site">
                        <span>Сайт</span>
                        <select id="p-promo-site">
                            <? foreach ($sites as $lid => $name): ?>
                                <option value="<?= htmlspecialcharsbx($lid) ?>"<?= $lid === $siteId ? ' selected' : '' ?>>
                                    <?= htmlspecialcharsbx($name) ?> (<?= htmlspecialcharsbx($lid) ?>)
                                </option>
                            <? endforeach; ?>
                        </select>
                    </label>
                <? endif; ?>

                <button type="button" class="p-btn p-btn--primary" id="p-promo-add">Добавить промокод</button>
            </div>
        </div>

        <div class="p-promo__hint">
            Один промокод даёт один эффект: скидку на заказ, скидку на доставку или товар.
            Промокод и подарок по сумме корзины не применяются одновременно.
        </div>

        <div class="p-promo__list" id="p-promo-list">
            <? if (empty($promos)): ?>
                <div class="p-promo__empty">Промокодов пока нет. Нажмите «Добавить промокод».</div>
            <? else: ?>
                <div class="p-promo__table">
                    <div class="p-promo__row p-promo__row--head">
                        <div class="p-promo__cell">Код</div>
                        <div class="p-promo__cell">Тип</div>
                        <div class="p-promo__cell">Значение</div>
                        <div class="p-promo__cell">Условия</div>
                        <div class="p-promo__cell">Использований</div>
                        <div class="p-promo__cell">Статус</div>
                        <div class="p-promo__cell p-promo__cell--actions">Действия</div>
                    </div>

                    <? foreach ($promos as $promo): ?>
                        <?
                        $valueText = '';
                        if ($promo['TYPE'] === 'product' && is_array($promo['PRODUCT'])) {
                            $valueText = 'Товар: ' . $promo['PRODUCT']['NAME'];
                        } elseif ($promo['VALUE'] > 0) {
                            $valueText = $promo['IS_PERCENT']
                                ? (float)$promo['VALUE'] . '%'
                                : (float)$promo['VALUE'] . ' ₽';
                        }

                        $conditions = [];
                        if ((float)$promo['MIN_SUM'] > 0) {
                            $conditions[] = 'от ' . (float)$promo['MIN_SUM'] . ' ₽';
                        }
                        if ($promo['DATE_FROM'] !== '') {
                            $conditions[] = 'с ' . $promo['DATE_FROM'];
                        }
                        if ($promo['DATE_TO'] !== '') {
                            $conditions[] = 'по ' . $promo['DATE_TO'];
                        }
                        if ((int)$promo['USER_ID'] > 0) {
                            $conditions[] = 'персональный';
                        }
                        $conditionsText = $conditions ? implode(', ', $conditions) : '—';

                        $useText = (string)(int)$promo['USE_COUNT'];
                        if ((int)$promo['MAX_USE'] > 0) {
                            $useText .= ' / ' . (int)$promo['MAX_USE'];
                        }
                        ?>
                        <div class="p-promo__row" data-id="<?= (int)$promo['ID'] ?>">
                            <div class="p-promo__cell p-promo__cell--code">
                                <?= htmlspecialcharsbx($promo['CODE']) ?>
                                <? if ($promo['NAME'] !== '' && $promo['NAME'] !== 'Промокод ' . $promo['CODE']): ?>
                                    <span class="p-promo__name"><?= htmlspecialcharsbx($promo['NAME']) ?></span>
                                <? endif; ?>
                            </div>
                            <div class="p-promo__cell"><?= htmlspecialcharsbx($promo['TYPE_NAME']) ?></div>
                            <div class="p-promo__cell"><?= htmlspecialcharsbx($valueText) ?></div>
                            <div class="p-promo__cell"><?= htmlspecialcharsbx($conditionsText) ?></div>
                            <div class="p-promo__cell"><?= htmlspecialcharsbx($useText) ?></div>
                            <div class="p-promo__cell">
                                <button type="button"
                                        class="p-promo__status<?= $promo['ACTIVE'] === 'Y' ? ' is-active' : '' ?>"
                                        data-action="toggle"
                                        data-id="<?= (int)$promo['ID'] ?>"
                                        data-active="<?= $promo['ACTIVE'] === 'Y' ? 'Y' : 'N' ?>">
                                    <?= $promo['ACTIVE'] === 'Y' ? 'Активен' : 'Выключен' ?>
                                </button>
                            </div>
                            <div class="p-promo__cell p-promo__cell--actions">
                                <button type="button" class="p-btn p-btn--ghost" data-action="edit" data-id="<?= (int)$promo['ID'] ?>">Изменить</button>
                                <button type="button" class="p-btn p-btn--danger" data-action="delete" data-id="<?= (int)$promo['ID'] ?>">Удалить</button>
                            </div>

                            <script type="application/json" class="p-promo__data"><?= \CUtil::PhpToJSObject($promo, false, false) ?></script>
                        </div>
                    <? endforeach; ?>
                </div>
            <? endif; ?>
        </div>
    </div>

    <!-- Форма создания и редактирования -->
    <div class="p-promo-modal" id="p-promo-modal" hidden>
        <div class="p-promo-modal__overlay" data-close="1"></div>
        <div class="p-promo-modal__dialog" role="dialog" aria-modal="true">
            <div class="p-promo-modal__header">
                <span id="p-promo-modal-title">Новый промокод</span>
                <button type="button" class="p-promo-modal__close" data-close="1" aria-label="Закрыть">&times;</button>
            </div>

            <form id="p-promo-form" class="p-promo-form" autocomplete="off">
                <input type="hidden" name="id" value="0">
                <input type="hidden" name="site_id" value="<?= htmlspecialcharsbx($siteId) ?>">
                <input type="hidden" name="product_id" value="0">

                <div class="p-promo-form__row">
                    <label class="p-promo-form__label" for="p-promo-type">Тип промокода</label>
                    <select name="type" id="p-promo-type" class="p-promo-form__field">
                        <? foreach ($types as $typeCode => $typeName): ?>
                            <option value="<?= htmlspecialcharsbx($typeCode) ?>"><?= htmlspecialcharsbx($typeName) ?></option>
                        <? endforeach; ?>
                    </select>
                </div>

                <div class="p-promo-form__row">
                    <label class="p-promo-form__label" for="p-promo-code">Код промокода</label>
                    <input type="text" name="code" id="p-promo-code" class="p-promo-form__field"
                           maxlength="32" placeholder="NEW1" required>
                </div>

                <div class="p-promo-form__row">
                    <label class="p-promo-form__label" for="p-promo-name">Название (необязательно)</label>
                    <input type="text" name="name" id="p-promo-name" class="p-promo-form__field"
                           maxlength="255" placeholder="Подарок за первый заказ">
                </div>

                <div class="p-promo-form__row" data-field="discount">
                    <label class="p-promo-form__label" for="p-promo-value">Размер скидки</label>
                    <div class="p-promo-form__inline">
                        <input type="text" name="value" id="p-promo-value" class="p-promo-form__field" placeholder="10">
                        <select name="value_type" class="p-promo-form__field p-promo-form__field--short">
                            <option value="percent">%</option>
                            <option value="sum">₽</option>
                        </select>
                    </div>
                </div>

                <div class="p-promo-form__row" data-field="product">
                    <label class="p-promo-form__label" for="p-promo-product-search">Товар по промокоду</label>
                    <input type="text" id="p-promo-product-search" class="p-promo-form__field"
                           placeholder="Начните вводить название товара">
                    <div class="p-promo-form__products" id="p-promo-products" hidden></div>
                    <div class="p-promo-form__selected" id="p-promo-product-selected" hidden></div>
                </div>

                <div class="p-promo-form__row">
                    <label class="p-promo-form__label" for="p-promo-min-sum">Минимальная сумма заказа, ₽</label>
                    <input type="text" name="min_sum" id="p-promo-min-sum" class="p-promo-form__field" placeholder="0">
                </div>

                <div class="p-promo-form__row p-promo-form__row--half">
                    <div>
                        <label class="p-promo-form__label" for="p-promo-date-from">Действует с</label>
                        <input type="text" name="date_from" id="p-promo-date-from" class="p-promo-form__field"
                               placeholder="ДД.ММ.ГГГГ ЧЧ:ММ">
                    </div>
                    <div>
                        <label class="p-promo-form__label" for="p-promo-date-to">Действует по</label>
                        <input type="text" name="date_to" id="p-promo-date-to" class="p-promo-form__field"
                               placeholder="ДД.ММ.ГГГГ ЧЧ:ММ">
                    </div>
                </div>

                <div class="p-promo-form__row p-promo-form__row--half">
                    <div>
                        <label class="p-promo-form__label" for="p-promo-coupon-type">Тип купона</label>
                        <select name="coupon_type" id="p-promo-coupon-type" class="p-promo-form__field">
                            <? foreach ($couponTypes as $couponCode => $couponName): ?>
                                <option value="<?= htmlspecialcharsbx($couponCode) ?>"><?= htmlspecialcharsbx($couponName) ?></option>
                            <? endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="p-promo-form__label" for="p-promo-max-use">Лимит использований</label>
                        <input type="text" name="max_use" id="p-promo-max-use" class="p-promo-form__field"
                               placeholder="0 — без ограничений">
                    </div>
                </div>

                <div class="p-promo-form__row">
                    <label class="p-promo-form__label" for="p-promo-user-id">ID пользователя (для персонального купона)</label>
                    <input type="text" name="user_id" id="p-promo-user-id" class="p-promo-form__field" placeholder="0">
                </div>

                <div class="p-promo-form__row p-promo-form__row--checkbox">
                    <label>
                        <input type="checkbox" name="active" value="Y" checked>
                        <span>Промокод активен</span>
                    </label>
                </div>

                <div class="p-promo-form__error" id="p-promo-form-error" hidden></div>

                <div class="p-promo-form__actions">
                    <button type="submit" class="p-btn p-btn--primary" id="p-promo-save">Сохранить</button>
                    <button type="button" class="p-btn p-btn--ghost" data-close="1">Отмена</button>
                </div>
            </form>
        </div>
    </div>

    <div class="p-promo-notices" id="p-promo-notices"></div>
</div>

<script src="<?= $assetFolder ?>/script.js?v=<?= $scriptVersion ?>"></script>
