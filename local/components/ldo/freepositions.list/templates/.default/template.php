<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */

if (!empty($arResult['ERROR'])): ?>
    <div class="fp-message fp-message--error" style="display:block;"><?= htmlspecialcharsbx($arResult['ERROR']) ?></div>
<?php endif; ?>
<div id="fp-message" class="fp-message" style="display:none;"></div>

<div class="p-section__header">
    <h2 class="p-section__title">Бесплатные позиции к товарам</h2>
    <button type="button" class="p-btn p-btn--primary" id="fp-add-btn">+ Добавить</button>
</div>

<!-- Форма: создание / редактирование -->
<div id="fp-form" class="fp-form" style="display:none;">
    <input type="hidden" id="fp-id" value="0">

    <div class="fp-form__row">
        <label class="fp-field fp-field--grow">
            <span class="fp-field__label">Название</span>
            <input type="text" id="fp-name" value="">
        </label>
        <label class="fp-field fp-field--narrow">
            <span class="fp-field__label">Кол-во порции для применения</span>
            <input type="number" id="fp-portions" min="1" step="1" value="1">
        </label>
        <label class="fp-field fp-field--narrow">
            <span class="fp-field__label">Привязка к сайту</span>
            <select id="fp-site">
                <?php foreach ($arResult['SITES'] as $site): ?>
                    <option value="<?= htmlspecialcharsbx($site['LID']) ?>"
                        <?= $site['LID'] === $arResult['SITE_ID'] ? ' selected' : '' ?>>
                        <?= htmlspecialcharsbx($site['NAME'] !== '' ? $site['NAME'] : $site['LID']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <div class="fp-field fp-field--full">
        <span class="fp-field__label">Раздел каталога (можно выбрать несколько)</span>
        <div class="fp-sections" id="fp-sections">
            <?php if (empty($arResult['SECTIONS'])): ?>
                <div class="fp-sections__empty">Разделы каталога не найдены</div>
            <?php else: ?>
                <?php foreach ($arResult['SECTIONS'] as $section): ?>
                    <label class="fp-section-item" style="padding-left: <?= 12 + ((int)$section['DEPTH'] - 1) * 18 ?>px;">
                        <input type="checkbox" class="fp-section-cb" value="<?= (int)$section['ID'] ?>">
                        <span class="fp-section-item__name"><?= htmlspecialcharsbx($section['NAME']) ?></span>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="fp-field fp-field--full">
        <span class="fp-field__label">Бесплатные позиции (товары)</span>
        <div class="fp-picker">
            <div class="fp-chips" id="fp-chips"></div>
            <input type="text" id="fp-product-search" placeholder="Введите название товара и выберите из списка..." autocomplete="off">
            <div class="fp-suggest" id="fp-suggest" style="display:none;"></div>
        </div>
    </div>

    <div class="fp-form__actions">
        <button type="button" class="p-btn p-btn--primary" id="fp-save-btn">Сохранить</button>
        <button type="button" class="p-btn p-btn--ghost" id="fp-cancel-btn">Отмена</button>
    </div>
</div>

<div id="fp-list">
    <?php if (empty($arResult['ITEMS'])): ?>
        <div class="fp-empty">Правила не найдены</div>
    <?php else: ?>
        <?php foreach ($arResult['ITEMS'] as $item): ?>
            <div class="fp-item"
                 data-id="<?= (int)$item['ID'] ?>"
                 data-name="<?= htmlspecialcharsbx($item['NAME']) ?>"
                 data-portions="<?= (int)$item['PORTIONS'] ?>"
                 data-site="<?= htmlspecialcharsbx($item['SITE_ID']) ?>"
                 data-sections="<?= htmlspecialcharsbx(implode(',', $item['SECTION_IDS'])) ?>">
                <div class="fp-item__head">
                    <div class="fp-item__icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M20 6h-2.18c.11-.31.18-.65.18-1 0-1.66-1.34-3-3-3-1.05 0-1.96.54-2.5 1.35l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm11 15H4v-2h16v2zm0-5H4V8h5.08L7 10.83 8.62 12 11 8.76l1-1.36 1 1.36L15.38 12 17 10.83 14.92 8H20v6z"/></svg>
                    </div>
                    <div class="fp-item__info">
                        <div class="fp-item__name"><?= htmlspecialcharsbx($item['NAME']) ?></div>
                        <div class="fp-item__meta">
                            Порций: <?= (int)$item['PORTIONS'] ?> · Сайт: <?= htmlspecialcharsbx($item['SITE_ID']) ?>
                            · Товаров: <?= count($item['PRODUCTS']) ?>
                        </div>
                        <?php if (!empty($item['SECTIONS'])): ?>
                            <div class="fp-item__sections">
                                <?php foreach ($item['SECTIONS'] as $section): ?>
                                    <span class="fp-section-chip"><?= htmlspecialcharsbx($section['NAME']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="fp-item__actions">
                        <button type="button" class="p-btn p-btn--ghost" data-action="edit">Изменить</button>
                        <button type="button" class="p-users-delete" data-action="delete" title="Удалить правило">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 19C6 20.1 6.9 21 8 21H16C17.1 21 18 20.1 18 19V7H6V19ZM8 9H16V19H8V9ZM15.5 4L14.5 3H9.5L8.5 4H5V6H19V4H15.5Z" fill="currentColor"/></svg>
                        </button>
                    </div>
                </div>
                <?php if (!empty($item['PRODUCTS'])): ?>
                    <div class="fp-item__products">
                        <?php foreach ($item['PRODUCTS'] as $product): ?>
                            <span class="fp-product" data-pid="<?= (int)$product['ID'] ?>" data-pname="<?= htmlspecialcharsbx($product['NAME']) ?>">
                                <?php if ($product['PICTURE'] !== ''): ?>
                                    <img class="fp-product__img" src="<?= htmlspecialcharsbx($product['PICTURE']) ?>" alt="">
                                <?php endif; ?>
                                <span class="fp-product__name"><?= htmlspecialcharsbx($product['NAME']) ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
