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

<? if (empty($arResult['ITEMS'])): ?>
    <p>Товары не найдены.</p>
<? else: ?>

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
