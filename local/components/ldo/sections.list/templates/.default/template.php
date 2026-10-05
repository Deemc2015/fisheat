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

<? if (empty($arResult['SECTIONS'])): ?>
    <p>Разделы не найдены.</p>
<? else: ?>
<div class="sections-search">
    <input type="text" class="sections-search-input" placeholder="Поиск раздела по названию..." autocomplete="off">
</div>

<div id="sections-list">

    <?php foreach ($arResult['SECTIONS'] as $section): ?>
        <div class="rest-item section-item" data-id="<?= (int)$section['ID'] ?>">
            <div class="rest-item__main">
                <div class="rest-item__info">
                    <div class="rest-item__title-row" style="display:flex; align-items:center; gap:12px;">
                        <? if (!empty($section['ICON_SRC'])): ?>
                            <img class="section-icon" src="<?= $section['ICON_SRC'] ?>" alt="<?= htmlspecialchars($section['NAME']) ?>" width="50" height="50">
                        <? else: ?>
                            <div class="section-icon section-icon--empty"></div>
                        <? endif; ?>
                        <div class="rest-item__name"><?= htmlspecialchars($section['NAME']) ?></div>
                    </div>

                    <div class="rest-item__meta" style="display:flex; align-items:center; gap:20px; flex-wrap:wrap; margin-top:6px;">
                        <!-- Чекбокс: Активность -->
                        <label class="toggle-switch">
                            <input type="checkbox" class="section-active" <?= $section['ACTIVE'] === 'Y' ? 'checked' : '' ?> disabled>
                            <span class="toggle-switch__slider"></span>
                            <span class="toggle-switch__label">Активность</span>
                        </label>

                        <!-- Чекбокс: Показать на главной -->
                        <label class="toggle-switch">
                            <input type="checkbox" class="section-on-main" <?= $section['UF_VIEW_INDEX'] == 1 ? 'checked' : '' ?> disabled>
                            <span class="toggle-switch__slider"></span>
                            <span class="toggle-switch__label">Показать на главной</span>
                        </label>

                        <!-- Родительский раздел -->
                        <label class="section-parent-field">
                            <span class="section-parent-field__label">Родительский раздел:</span>
                            <select class="section-parent-select" disabled>
                                <option value="0">— Корневой раздел —</option>
                                <?php foreach ($arResult['SECTIONS'] as $parent): ?>
                                    <?php if ((int)$parent['ID'] === (int)$section['ID']) continue; ?>
                                    <option value="<?= (int)$parent['ID'] ?>" <?= ((int)$section['IBLOCK_SECTION_ID'] === (int)$parent['ID']) ? 'selected' : '' ?>><?= htmlspecialchars($parent['NAME']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <!-- Порядок (индекс сортировки) -->
                        <label class="section-sort-field">
                            <span class="section-sort-field__label">Порядок:</span>
                            <input type="number" class="section-sort-input" value="<?= (int)$section['SORT'] ?>" disabled>
                        </label>

                        <!-- Иконка раздела (загрузка/изменение) -->
                        <label class="section-picture-field">
                            <span class="section-picture-field__label">Иконка:</span>
                            <input type="file" class="section-picture-input" accept="image/*" disabled>
                        </label>
                    </div>
                </div>

                <div class="rest-item__actions" style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                    <button type="button" class="section-btn section-btn--edit" data-action="edit">Редактировать</button>
                    <button type="button" class="section-btn section-btn--save" data-action="save" style="display:none;">Изменить</button>
                    <button type="button" class="section-btn section-btn--cancel" data-action="cancel" style="display:none;">Отмена</button>
                </div>
            </div>

            <!-- Описание раздела: визуальный редактор + SEO (в режиме редактирования) -->
            <div class="section-desc-block" style="display:none;">
                <div class="section-tabs" role="tablist">
                    <button type="button" class="section-tab is-active" data-section-tab="main">Описание раздела</button>
                    <button type="button" class="section-tab" data-section-tab="seo">SEO описание</button>
                </div>

                <div class="section-tab-pane is-active" data-section-pane="main">
                    <span class="section-field__label">Описание раздела</span>
                    <div class="section-desc-wrap" data-description="<?= htmlspecialcharsbx((string)$section['DESCRIPTION']) ?>"></div>
                </div>

                <div class="section-tab-pane is-active" data-section-pane="seo" style="display:none;">
                    <label class="section-field section-field--full">
                        <span class="section-field__label">Заголовок раздела</span>
                        <input type="text" class="section-seo-title" value="<?= htmlspecialcharsbx((string)($section['SEO_TITLE'] ?? '')) ?>">
                    </label>

                    <label class="section-field section-field--full">
                        <span class="section-field__label">Описание раздела</span>
                        <textarea class="section-seo-description" rows="4"><?= htmlspecialcharsbx((string)($section['SEO_DESCRIPTION'] ?? '')) ?></textarea>
                    </label>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<? endif; ?>
