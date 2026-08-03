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

<? if (empty($arResult['SECTIONS']) && empty($arResult['ITEMS'])): ?>
    <p>Товары не найдены.</p>
<? else: ?>

<div class="products-toolbar">
    <label class="products-filter">
        <span class="products-filter__label">Категория</span>
        <select id="products-category">
            <option value="0">Все категории</option>
            <?php foreach ($arResult['SECTIONS'] as $sid => $sname): ?>
                <option value="<?= (int)$sid ?>"><?= htmlspecialcharsbx($sname) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="products-search">
        <span class="products-search__label">Поиск по названию</span>
        <input type="text" id="products-search-input" placeholder="Введите название товара..." autocomplete="off">
    </label>
</div>

<div
    id="products-list"
    data-page-size="<?= (int)$arResult['PAGE_SIZE'] ?>"
    data-sort-field="<?= htmlspecialcharsbx($arResult['SORT_FIELD']) ?>"
    data-sort-order="<?= htmlspecialcharsbx($arResult['SORT_ORDER']) ?>"
    data-has-more="<?= $arResult['HAS_MORE'] ? 'Y' : 'N' ?>"
>
    <?php foreach ($arResult['ITEMS'] as $item): ?>
        <?= $component->renderItemHtml($item) ?>
    <?php endforeach; ?>
</div>

<div class="products-more">
    <button type="button" class="products-more__btn" id="products-more-btn">
        Показать ещё
    </button>
</div>

<? endif; ?>
