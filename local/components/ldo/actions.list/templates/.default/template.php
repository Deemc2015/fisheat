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

<div class="actions-toolbar">
    <button type="button" class="action-btn action-btn--edit" id="action-add-btn">+ Добавить акцию</button>
</div>

<!-- Форма создания новой акции (скрыта до нажатия "Добавить акцию") -->
<div id="action-create" class="action-create" data-id="0" style="display:none;">
    <div class="action-tabs" role="tablist">
        <button type="button" class="action-tab is-active" data-action-tab="main">Описание акции</button>
        <button type="button" class="action-tab" data-action-tab="seo">SEO описание</button>
    </div>

    <div class="action-pane is-active" data-action-pane="main">
        <label class="action-field">
            <span class="action-field__label">Название акции</span>
            <input type="text" class="action-name" value="">
        </label>

        <label class="action-field action-field--narrow">
            <span class="action-field__label">Сортировка</span>
            <input type="number" class="action-sort" value="500">
        </label>

        <label class="action-field action-field--active" title="Активность">
            <span class="action-field__label">Активность</span>
            <span class="toggle-switch">
                <input type="checkbox" class="action-active" checked>
                <span class="toggle-switch__slider"></span>
            </span>
        </label>

        <label class="action-field">
            <span class="action-field__label">Дата активности (с)</span>
            <input type="datetime-local" class="action-active-from" value="">
        </label>

        <label class="action-field">
            <span class="action-field__label">Дата активности (по)</span>
            <input type="datetime-local" class="action-active-to" value="">
        </label>

        <label class="action-field action-field--full">
            <span class="action-field__label">Фото акции</span>
            <span class="action-photo-block">
                <span class="action-photo action-photo--empty"></span>
                <input type="file" class="action-picture" accept="image/*">
            </span>
        </label>

        <label class="action-field action-field--full">
            <span class="action-field__label">Описание акции (визуальный редактор)</span>
            <div class="action-detail-wrap" data-detail=""></div>
        </label>
    </div>

    <div class="action-pane is-active" data-action-pane="seo" style="display:none;">
        <label class="action-field action-field--full">
            <span class="action-field__label">SEO: Заголовок (meta title)</span>
            <input type="text" class="action-seo-title" value="">
        </label>

        <label class="action-field action-field--full">
            <span class="action-field__label">SEO описание (meta description)</span>
            <textarea class="action-seo-description" rows="4"></textarea>
        </label>
    </div>

    <div class="action-create__actions">
        <button type="button" class="action-btn action-btn--save" id="action-create-submit">Создать акцию</button>
        <button type="button" class="action-btn action-btn--cancel" id="action-create-cancel">Отмена</button>
    </div>
</div>

<div id="actions-list">
    <? if (empty($arResult['ITEMS'])): ?>
        <p class="actions-empty">Акции не найдены.</p>
    <? else: ?>
        <?php foreach ($arResult['ITEMS'] as $item): ?>
            <?= $component->renderItemHtml($item) ?>
        <?php endforeach; ?>
    <? endif; ?>
</div>
