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
?>
<div class="p-section__header">
    <h2 class="p-section__title">Подарки к заказам</h2>
    <button type="button" class="p-btn p-btn--primary" id="gift-add-btn">+ Добавить уровень</button>
</div>

<div id="gifts-message" style="display:none; padding:12px 16px; border-radius:8px; margin-bottom:16px; background:rgba(231,76,60,.12); color:#e74c3c;"></div>

<!-- Форма уровня: создание / редактирование -->
<div id="gift-form" class="p-gifts-form" style="display:none;">
    <input type="hidden" id="gift-id" value="0">

    <div class="p-gifts-form__row">
        <label class="p-gifts-field p-gifts-field--grow">
            <span class="p-gifts-field__label">Название уровня</span>
            <input type="text" id="gift-name" value="">
        </label>
        <label class="p-gifts-field p-gifts-field--narrow">
            <span class="p-gifts-field__label">Сумма корзины, ₽</span>
            <input type="number" id="gift-sum" min="0" step="1" value="0">
        </label>
        <label class="p-gifts-field p-gifts-field--narrow">
            <span class="p-gifts-field__label">Сортировка</span>
            <input type="number" id="gift-sort" value="500">
        </label>
        <label class="p-gifts-field p-gifts-field--active" title="Активность">
            <span class="p-gifts-field__label">Активность</span>
            <span class="toggle-switch">
                <input type="checkbox" id="gift-active" checked>
                <span class="toggle-switch__slider"></span>
            </span>
        </label>
    </div>

    <div class="p-gifts-field p-gifts-field--full">
        <span class="p-gifts-field__label">Товары уровня</span>
        <div class="p-gifts-picker">
            <div class="p-gifts-chips" id="gift-chips"></div>
            <input type="text" id="gift-product-search" placeholder="Введите название товара и выберите из списка..." autocomplete="off">
            <div class="p-gifts-suggest" id="gift-suggest" style="display:none;"></div>
        </div>
    </div>

    <div class="p-gifts-form__actions">
        <button type="button" class="p-btn p-btn--primary" id="gift-save-btn">Сохранить</button>
        <button type="button" class="p-btn p-btn--ghost" id="gift-cancel-btn">Отмена</button>
    </div>
</div>

<div id="gifts-list">
    <?php if (empty($arResult['LEVELS'])): ?>
        <div class="p-gifts-empty">Уровни подарков не найдены</div>
    <?php else: ?>
        <?php foreach ($arResult['LEVELS'] as $level): ?>
            <div class="p-gifts-item<?= $level['ACTIVE'] ? '' : ' p-gifts-item--inactive' ?>"
                 data-id="<?= (int)$level['ID'] ?>"
                 data-name="<?= htmlspecialchars($level['NAME'], ENT_QUOTES) ?>"
                 data-sum="<?= htmlspecialchars((string)$level['SUM'], ENT_QUOTES) ?>"
                 data-sort="<?= (int)$level['SORT'] ?>"
                 data-active="<?= $level['ACTIVE'] ? 'Y' : 'N' ?>">
                <div class="p-gifts-item__head">
                    <div class="p-gifts-item__icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M20 6h-2.18c.11-.31.18-.65.18-1 0-1.66-1.34-3-3-3-1.05 0-1.96.54-2.5 1.35l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm11 15H4v-2h16v2zm0-5H4V8h5.08L7 10.83 8.62 12 11 8.76l1-1.36 1 1.36L15.38 12 17 10.83 14.92 8H20v6z"/></svg>
                    </div>
                    <div class="p-gifts-item__sum">от <?= (int)$level['SUM'] ?> ₽</div>
                    <div class="p-gifts-item__info">
                        <div class="p-gifts-item__name"><?= htmlspecialchars($level['NAME']) ?></div>
                        <div class="p-gifts-item__meta">
                            Товаров: <?= count($level['PRODUCTS']) ?><?= $level['ACTIVE'] ? '' : ' · неактивен' ?>
                        </div>
                    </div>
                    <div class="p-gifts-item__actions">
                        <button type="button" class="p-btn p-btn--ghost" data-action="edit">Изменить</button>
                        <button type="button" class="p-users-delete" data-action="delete" title="Удалить уровень">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 19C6 20.1 6.9 21 8 21H16C17.1 21 18 20.1 18 19V7H6V19ZM8 9H16V19H8V9ZM15.5 4L14.5 3H9.5L8.5 4H5V6H19V4H15.5Z" fill="currentColor"/></svg>
                        </button>
                    </div>
                </div>
                <?php if (!empty($level['PRODUCTS'])): ?>
                    <div class="p-gifts-item__products" data-role="products">
                        <?php foreach ($level['PRODUCTS'] as $product): ?>
                            <span class="p-gifts-product" data-pid="<?= (int)$product['ID'] ?>" data-pname="<?= htmlspecialchars($product['NAME'], ENT_QUOTES) ?>">
                                <?php if ($product['PICTURE'] !== ''): ?>
                                    <img class="p-gifts-product__img" src="<?= htmlspecialchars($product['PICTURE']) ?>" alt="">
                                <?php endif; ?>
                                <span class="p-gifts-product__name"><?= htmlspecialchars($product['NAME']) ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
