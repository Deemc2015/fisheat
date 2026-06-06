<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arResult
 * @var SmartconsultantSearchComponent $component
 */

$componentId = $arResult['COMPONENT_ID'];
$ajaxUrl = $arResult['AJAX_URL'];
$iblockId = $arResult['IBLOCK_ID'];
$topCount = $arResult['TOP_COUNT'];
$minSimilarity = $arResult['MIN_SIMILARITY'];
$placeholder = htmlspecialcharsbx($arResult['INPUT_PLACEHOLDER']);
$query = htmlspecialcharsbx($arResult['QUERY']);
$foundElementIds = $arResult['FOUND_ELEMENT_IDS'];
$foundItems = $arResult['FOUND_ITEMS'];
$searchTime = $arResult['SEARCH_TIME'];
$totalMatches = $arResult['TOTAL_MATCHES'];
$searchError = $arResult['SEARCH_ERROR'] ?? '';
?>

<div class="smartconsultant-search" id="<?= $componentId ?>">
    <div class="smartconsultant-search__input-wrap">
        <input
            type="text"
            class="smartconsultant-search__input"
            placeholder="<?= $placeholder ?>"
            value="<?= $query ?>"
            autocomplete="off"
        >
        <button
            type="button"
            class="smartconsultant-search__submit"
            title="Найти"
            aria-label="Найти"
        >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
        </button>
        <div class="smartconsultant-search__spinner" style="display:none;"></div>
    </div>
    <div class="smartconsultant-search__results" style="display:none;"></div>
</div>

<?php if ($query !== ''): ?>
<div class="smartconsultant-search__section">
    <?php if ($searchError): ?>
        <div class="smartconsultant-search__error"><?= htmlspecialcharsbx($searchError) ?></div>
    <?php elseif (empty($foundElementIds)): ?>
        <div class="smartconsultant-search__empty">По запросу «<?= $query ?>» ничего не найдено.</div>
    <?php else: ?>
        <div class="smartconsultant-search__info">
            Найдено: <?= $totalMatches ?> товаров за <?= $searchTime ?> сек.
        </div>

        <?php
        // Вывод catalog.section с найденными товарами
        global $arrFilter;
        $arrFilter = [
            'ID' => $foundElementIds,
        ];

        $APPLICATION->IncludeComponent(
            'bitrix:catalog.section',
            '.default',
            [
                'IBLOCK_TYPE' => '',
                'IBLOCK_ID' => '',
                'SECTION_ID' => '',
                'SECTION_CODE' => '',
                'SECTION_USER_FIELDS' => [],
                'FILTER_NAME' => 'arrFilter',
                'INCLUDE_SUBSECTIONS' => 'Y',
                'SHOW_ALL_WO_SECTION' => 'Y',
                'ELEMENT_SORT_FIELD' => 'ID',
                'ELEMENT_SORT_ORDER' => 'ASC',
                'ELEMENT_SORT_FIELD2' => 'ID',
                'ELEMENT_SORT_ORDER2' => 'ASC',
                'PAGE_ELEMENT_COUNT' => $topCount,
                'LINE_ELEMENT_COUNT' => 3,
                'PROPERTY_CODE' => [],
                'PROPERTY_CODE_MOBILE' => [],
                'OFFERS_LIMIT' => 0,
                'PRICE_CODE' => [],
                'BASKET_URL' => '/personal/basket.php',
                'ACTION_VARIABLE' => 'action',
                'PRODUCT_ID_VARIABLE' => 'id',
                'USE_PRODUCT_QUANTITY' => 'N',
                'PRODUCT_QUANTITY_VARIABLE' => 'quantity',
                'ADD_PROPERTIES_TO_BASKET' => 'N',
                'PRODUCT_PROPS_VARIABLE' => 'prop',
                'PARTIAL_PRODUCT_PROPERTIES' => 'N',
                'PRODUCT_PROPERTIES' => [],
                'PAGER_TEMPLATE' => '.default',
                'DISPLAY_TOP_PAGER' => 'N',
                'DISPLAY_BOTTOM_PAGER' => 'N',
                'PAGER_TITLE' => 'Товары',
                'PAGER_SHOW_ALWAYS' => 'N',
                'PAGER_DESC_NUMBERING' => 'N',
                'PAGER_DESC_NUMBERING_CACHE_TIME' => 36000,
                'PAGER_SHOW_ALL' => 'N',
                'PAGER_BASE_LINK_ENABLE' => 'N',
                'SET_STATUS_404' => 'N',
                'SHOW_404' => 'N',
                'MESSAGE_404' => '',
                'SEF_MODE' => 'N',
                'AJAX_MODE' => 'N',
                'CACHE_TYPE' => 'A',
                'CACHE_TIME' => $arParams['CACHE_TIME'] ?? 3600,
                'CACHE_FILTER' => 'Y',
                'CACHE_GROUPS' => 'Y',
                'COMPATIBLE_MODE' => 'Y',
            ]
        );
        ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
    (function() {
        const container = document.getElementById('<?= $componentId ?>');
        const input = container.querySelector('.smartconsultant-search__input');
        const results = container.querySelector('.smartconsultant-search__results');
        const spinner = container.querySelector('.smartconsultant-search__spinner');
        const submitBtn = container.querySelector('.smartconsultant-search__submit');

        let debounceTimer = null;
        let abortController = null;

        function doSubmit(query) {
            if (query.length < 2) return;
            const url = new URL(window.location.href);
            url.searchParams.set('q', query);
            window.location.href = url.toString();
        }

        // Кнопка «Найти»
        submitBtn.addEventListener('click', function() {
            doSubmit(input.value.trim());
        });

        // Enter в поле ввода
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doSubmit(this.value.trim());
            }
        });

        input.addEventListener('input', function() {
            const query = this.value.trim();

            // Отмена предыдущего запроса
            if (abortController) {
                abortController.abort();
            }

            clearTimeout(debounceTimer);

            if (query.length < 2) {
                results.style.display = 'none';
                results.innerHTML = '';
                return;
            }

            // Debounce 300ms
            debounceTimer = setTimeout(function() {
                abortController = new AbortController();
                spinner.style.display = 'block';

                const formData = new FormData();
                formData.append('query', query);
                formData.append('limit', '<?= $topCount ?>');
                formData.append('minSimilarity', '<?= $minSimilarity ?>');
                <?php if ($iblockId > 0): ?>
                formData.append('iblockId', '<?= $iblockId ?>');
                <?php endif; ?>

                const params = new URLSearchParams(formData).toString();

                fetch('<?= $ajaxUrl ?>&' + params, {
                    method: 'GET',
                    signal: abortController.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    spinner.style.display = 'none';

                    if (!data.data || !data.data.success || !data.data.items.length) {
                        results.innerHTML = '<div class="smartconsultant-search__empty">Ничего не найдено</div>';
                        results.style.display = 'block';
                        return;
                    }

                    let html = '';
                    const items = data.data.items;

                    items.forEach(function(item) {
                        const simPercent = Math.round(item.similarity * 100);
                        html += '<a href="' + item.url + '" class="smartconsultant-search__item">';
                        if (item.imageUrl) {
                            html += '<img src="' + escapeHtml(item.imageUrl) + '" class="smartconsultant-search__item-img" alt="">';
                        }
                        html += '<div class="smartconsultant-search__item-info">';
                        html += '<div class="smartconsultant-search__item-name">' + escapeHtml(item.name) + '</div>';
                        html += '<div class="smartconsultant-search__item-sim">' + simPercent + '%</div>';
                        html += '</div>';
                        html += '</a>';
                    });


                    results.innerHTML = html;
                    results.style.display = 'block';
                })
                .catch(function(err) {
                    if (err.name !== 'AbortError') {
                        spinner.style.display = 'none';
                        results.innerHTML = '<div class="smartconsultant-search__error">Ошибка поиска</div>';
                        results.style.display = 'block';
                    }
                });
            }, 300);
        });

        // Скрываем результаты при клике вне компонента
        document.addEventListener('click', function(e) {
            if (!container.contains(e.target)) {
                results.style.display = 'none';
            }
        });

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    })();
</script>
