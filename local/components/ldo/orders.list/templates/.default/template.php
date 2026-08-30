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

$orders           = $arResult['ORDERS'] ?? [];
$statuses         = $arResult['STATUSES'] ?? [];
// Полные карты — для колонок таблицы (названия у ВСЕХ заказов)
$deliveryServices = $arResult['DELIVERY_SERVICES'] ?? [];
$paySystems       = $arResult['PAY_SYSTEMS'] ?? [];
// Отфильтрованные по параметрам карты — только для селектов фильтра
$filterDeliveryServices = $arResult['FILTER_DELIVERY'] ?? $deliveryServices;
$filterPaySystems       = $arResult['FILTER_PAY'] ?? $paySystems;
$orderProps       = $arResult['ORDER_PROPS'] ?? [];
$baskets          = $arResult['BASKETS'] ?? [];
$deliverySum      = $arResult['DELIVERY_SUM'] ?? [];

$filterStatus   = $arResult['FILTER']['STATUS'] ?? [];
$filterDelivery = $arResult['FILTER']['DELIVERY'] ?? [];
$filterPaySystem= $arResult['FILTER']['PAY_SYSTEM'] ?? [];
$dateFromRaw    = $arResult['FILTER']['DATE_FROM'] ?? '';
$dateToRaw      = $arResult['FILTER']['DATE_TO'] ?? '';
$search         = $arResult['FILTER']['SEARCH'] ?? '';

$totalCount  = (int)($arResult['NAV']['TOTAL_COUNT'] ?? 0);
$pageCount   = (int)($arResult['NAV']['PAGE_COUNT'] ?? 1);
$currentPage = (int)($arResult['NAV']['CURRENT_PAGE'] ?? 1);

$pageParam  = $arResult['PAGE_PARAM'] ?? 'PAGEN_orders';
$baseParams = $arResult['BASE_PARAMS'] ?? [];
$exportEnabled = (bool)($arResult['EXPORT_ENABLED'] ?? true);

$fmtDate = function ($value) {
    if (is_object($value) && method_exists($value, 'format')) {
        return $value->format('d.m.Y H:i');
    }
    if (is_string($value) && $value !== '') {
        return htmlspecialchars($value);
    }
    return '—';
};

$fmtFio = function (array $o) {
    $fio = trim(trim((string)($o['USER_LAST_NAME'] ?? '')) . ' ' . trim((string)($o['USER_NAME'] ?? '')));
    if ($fio === '') {
        $fio = trim((string)($o['USER_LOGIN'] ?? ''));
    }
    return $fio;
};

$fmtWeight = function ($weight) {
    $weight = (float)$weight;
    if ($weight <= 0) {
        return '—';
    }
    if ($weight >= 1000) {
        return rtrim(rtrim(number_format($weight / 1000, 2, ',', ' '), '0'), ',') . ' кг';
    }
    return rtrim(rtrim(number_format($weight, 1, ',', ' '), '0'), ',') . ' г';
};

$makeUrl = function (array $extra = []) use ($baseParams, $pageParam) {
    // Параметр навигации PageNavigation должен быть в формате "page-N"
    // (initFromUri() разбирает строку по "-": "page" => N)
    if (isset($extra[$pageParam])) {
        $extra[$pageParam] = 'page-' . (int)$extra[$pageParam];
    }
    $params = array_merge($baseParams, $extra);
    $qs = http_build_query($params);
    return $qs !== '' ? '?' . $qs : '';
};

$prevUrl = $currentPage > 1 ? $makeUrl([$pageParam => $currentPage - 1]) : '';
$nextUrl = $currentPage < $pageCount ? $makeUrl([$pageParam => $currentPage + 1]) : '';

$window = [];
$start = max(1, $currentPage - 4);
$end = min($pageCount, $currentPage + 4);
for ($p = $start; $p <= $end; $p++) {
    $window[] = $p;
}

$exportUrl = $makeUrl(['EXPORT' => 'excel']);
?>

<div class="p-main">
    <div class="p-section">
        <div class="p-section__header">
            <h2 class="p-section__title">Заказы сайта</h2>
            <span style="font-size:13px; color:var(--color-muted);">Всего: <?= $totalCount ?></span>
        </div>

        <form class="p-orders-filter" method="get" action="">
            <div class="p-orders-filter__row">
                <div class="p-orders-filter__group">
                    <label for="p-orders-status">Статус</label>
                    <select id="p-orders-status" name="STATUS">
                        <option value="">Все статусы</option>
                        <?php foreach ($statuses as $statusId => $statusName): ?>
                            <option value="<?= htmlspecialchars($statusId) ?>" <?= in_array($statusId, $filterStatus, true) ? 'selected' : '' ?>><?= htmlspecialchars($statusName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="p-orders-filter__group">
                    <label for="p-orders-delivery">Способ доставки</label>
                    <select id="p-orders-delivery" name="DELIVERY">
                        <option value="">Все способы</option>
                        <?php foreach ($filterDeliveryServices as $deliveryId => $deliveryName): ?>
                            <option value="<?= (int)$deliveryId ?>" <?= in_array((int)$deliveryId, $filterDelivery, true) ? 'selected' : '' ?>><?= htmlspecialchars($deliveryName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="p-orders-filter__group">
                    <label for="p-orders-pay">Способ оплаты</label>
                    <select id="p-orders-pay" name="PAY_SYSTEM">
                        <option value="">Все способы</option>
                        <?php foreach ($filterPaySystems as $payId => $payName): ?>
                            <option value="<?= (int)$payId ?>" <?= in_array((int)$payId, $filterPaySystem, true) ? 'selected' : '' ?>><?= htmlspecialchars($payName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="p-orders-filter__group">
                    <label for="p-orders-date-from">Дата с</label>
                    <input type="date" id="p-orders-date-from" name="DATE_FROM" value="<?= htmlspecialchars($dateFromRaw) ?>">
                </div>

                <div class="p-orders-filter__group">
                    <label for="p-orders-date-to">Дата по</label>
                    <input type="date" id="p-orders-date-to" name="DATE_TO" value="<?= htmlspecialchars($dateToRaw) ?>">
                </div>

                <div class="p-orders-filter__group p-orders-filter__group--grow">
                    <label for="p-orders-search">Поиск</label>
                    <input type="text" id="p-orders-search" name="SEARCH" value="<?= htmlspecialchars($search) ?>" placeholder="№ заказа, имя, e-mail">
                </div>

                <div class="p-orders-filter__actions">
                    <button type="submit" class="p-btn p-btn--primary">Применить</button>
                    <a class="p-btn p-btn--ghost" href="?">Сбросить</a>
                    <?php if ($exportEnabled): ?>
                        <a class="p-btn p-btn--excel" href="<?= htmlspecialchars($exportUrl) ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14 2H6C4.9 2 4 2.9 4 4V20C4 21.1 4.9 22 6 22H18C19.1 22 20 21.1 20 20V8L14 2ZM18 20H6V4H13V9H18V20ZM8 15H10.5L12 12.9L13.5 15H16L13.5 11.5L16 8H13.5L12 10.1L10.5 8H8L10.5 11.5L8 15Z" fill="currentColor"/></svg>
                            Выгрузить в Excel
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <div class="p-users-wrap">
            <div style="overflow-x:auto;">
                <table class="p-users-table">
                    <thead>
                        <tr>
                            <th>№ заказа</th>
                            <th>Дата</th>
                            <th>Клиент</th>
                            <th>Телефон</th>
                            <th>Доставка</th>
                            <th>Оплата</th>
                            <th style="text-align:right;">Сумма</th>
                            <th style="text-align:right;">Статус</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$orders): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="p-users-empty">Заказы не найдены</div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($orders as $o): ?>
                            <?php
                                $number     = (string)($o['ACCOUNT_NUMBER'] !== '' ? $o['ACCOUNT_NUMBER'] : $o['ID']);
                                $props      = $orderProps[$o['ID']] ?? [];
                                $fio        = ($props['FIO'] ?? '') !== '' ? $props['FIO'] : $fmtFio($o);
                                $phone      = $props['PHONE'] ?? '';
                                $email      = ($props['EMAIL'] ?? '') !== '' ? $props['EMAIL'] : (string)($o['USER_EMAIL'] ?? '');
                                $statusName = (string)($statuses[$o['STATUS_ID']] ?? $o['STATUS_ID']);
                            ?>
                            <tr>
                                <td>
                                    <div class="p-order-num"><?= htmlspecialchars($number) ?></div>
                                    <div class="p-order-sub">ID: <?= (int)$o['ID'] ?></div>
                                </td>
                                <td><?= $fmtDate($o['DATE_INSERT']) ?></td>
                                <td>
                                    <?php if ($fio !== ''): ?>
                                        <div class="p-users-fio"><?= htmlspecialchars($fio) ?></div>
                                    <?php endif; ?>
                                    <div class="p-users-login">
                                        <?= htmlspecialchars($email !== '' ? $email : ((string)($o['USER_LOGIN'] ?? '') !== '' ? (string)$o['USER_LOGIN'] : '—')) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($phone !== '' ? $phone : '—') ?></td>
                                <td class="p-order-delivery"><?= htmlspecialchars((string)($deliveryServices[$o['DELIVERY_ID']] ?? '—')) ?></td>
                                <td class="p-order-pay"><?= htmlspecialchars((string)($paySystems[$o['PAY_SYSTEM_ID']] ?? '—')) ?></td>
                                <td style="text-align:right; white-space:nowrap;"><?= number_format((float)$o['PRICE'], 2, ',', ' ') ?> ₽</td>
                                <td style="text-align:right;">
                                    <span class="p-order-status" data-status="<?= htmlspecialchars((string)$o['STATUS_ID']) ?>"><?= htmlspecialchars($statusName) ?></span>
                                </td>
                                <td>
                                    <div class="p-order-menu">
                                        <button type="button" class="p-order-menu__trigger" title="Действия с заказом" aria-label="Действия с заказом" aria-haspopup="true" aria-expanded="false">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
                                        </button>
                                        <div class="p-order-menu__dropdown">
                                            <button type="button" class="p-order-menu__item js-order-details" data-id="<?= (int)$o['ID'] ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5C7 5 2.73 8.11 1 12C2.73 15.89 7 19 12 19C17 19 21.27 15.89 23 12C21.27 8.11 17 5 12 5ZM12 17C9.24 17 7 14.76 7 12C7 9.24 9.24 7 12 7C14.76 7 17 9.24 17 12C17 14.76 14.76 17 12 17ZM12 9C10.34 9 9 10.34 9 12C9 13.66 10.34 15 12 15C13.66 15 15 13.66 15 12C15 10.34 13.66 9 12 9Z" fill="currentColor"/></svg>
                                                Подробнее
                                            </button>
                                            <button type="button" class="p-order-menu__item js-order-print" data-id="<?= (int)$o['ID'] ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19 8H5C3.34 8 2 9.34 2 11V17H6V21H18V17H22V11C22 9.34 20.66 8 19 8ZM16 19H8V14H16V19ZM18 12C17.45 12 17 11.55 17 11C17 10.45 17.45 10 18 10C18.55 10 19 10.45 19 11C19 11.55 18.55 12 18 12ZM17 3H7V7H17V3Z" fill="currentColor"/></svg>
                                                Печать
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr class="p-order-detail-row" id="p-order-detail-<?= (int)$o['ID'] ?>">
                                <td colspan="9">
                                    <div class="p-order-detail">
                                        <?php if (!empty($baskets[$o['ID']])): ?>
                                            <table class="p-order-detail__products">
                                                <thead>
                                                    <tr>
                                                        <th>Наименование</th>
                                                        <th style="text-align:right;">Вес</th>
                                                        <th style="text-align:right;">Цена</th>
                                                        <th style="text-align:center;">Кол-во</th>
                                                        <th style="text-align:right;">Сумма</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($baskets[$o['ID']] as $item): ?>
                                                        <tr>
                                                            <td class="p-order-detail__name"><?= htmlspecialchars($item['NAME']) ?></td>
                                                            <td style="text-align:right; white-space:nowrap;"><?= $fmtWeight($item['WEIGHT']) ?></td>
                                                            <td style="text-align:right; white-space:nowrap;"><?= number_format((float)$item['PRICE'], 2, ',', ' ') ?> ₽</td>
                                                            <td style="text-align:center;"><?= (float)$item['QUANTITY'] ?></td>
                                                            <td style="text-align:right; white-space:nowrap;"><?= number_format((float)$item['SUMMARY_PRICE'], 2, ',', ' ') ?> ₽</td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <div class="p-order-detail__empty">Нет данных о составе заказа</div>
                                        <?php endif; ?>

                                        <div class="p-order-detail__totals">
                                            <div class="p-order-detail__total">
                                                <span>Сумма доставки</span>
                                                <b><?= number_format((float)($deliverySum[$o['ID']] ?? 0), 2, ',', ' ') ?> ₽</b>
                                            </div>
                                            <div class="p-order-detail__total">
                                                <span>Скидка</span>
                                                <b><?= number_format((float)($o['DISCOUNT_ALL'] ?? 0), 2, ',', ' ') ?> ₽</b>
                                            </div>
                                            <div class="p-order-detail__total p-order-detail__total--final">
                                                <span>Сумма заказа</span>
                                                <b><?= number_format((float)$o['PRICE'], 2, ',', ' ') ?> ₽</b>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pageCount > 1): ?>
                <div class="p-users-nav">
                    <?php if ($currentPage > 1): ?>
                        <a href="<?= $prevUrl ?>">‹</a>
                    <?php else: ?>
                        <span class="page disabled">‹</span>
                    <?php endif; ?>

                    <?php foreach ($window as $p): ?>
                        <?php if ($p === $currentPage): ?>
                            <span class="page current"><?= $p ?></span>
                        <?php else: ?>
                            <a href="<?= $makeUrl([$pageParam => $p]) ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($currentPage < $pageCount): ?>
                        <a href="<?= $nextUrl ?>">›</a>
                    <?php else: ?>
                        <span class="page disabled">›</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
BX.ready(function () {
    var wrap = document.querySelector('.p-users-wrap');
    if (!wrap) return;

    function closeMenus(except) {
        var menus = wrap.querySelectorAll('.p-order-menu.open');
        for (var i = 0; i < menus.length; i++) {
            if (!except || menus[i] !== except) {
                menus[i].classList.remove('open');
            }
        }
    }

    function toggleDetails(id) {
        var row = document.getElementById('p-order-detail-' + id);
        if (row) {
            row.classList.toggle('open');
        }
    }

    wrap.addEventListener('click', function (e) {
        // Триггер — открыть/закрыть выпадающее меню
        var trigger = e.target.closest ? e.target.closest('.p-order-menu__trigger') : null;
        if (trigger) {
            var menu = trigger.closest('.p-order-menu');
            if (!menu) return;
            var isOpen = menu.classList.contains('open');
            closeMenus(menu);
            menu.classList.toggle('open', !isOpen);
            return;
        }

        // Пункт «Подробнее» — раскрыть/свернуть состав заказа
        var detailsBtn = e.target.closest ? e.target.closest('.js-order-details') : null;
        if (detailsBtn) {
            toggleDetails(detailsBtn.getAttribute('data-id'));
            closeMenus();
            return;
        }

        // Пункт «Печать» — метод будет реализован позже
        var printBtn = e.target.closest ? e.target.closest('.js-order-print') : null;
        if (printBtn) {
            closeMenus();
            return;
        }

        // Клик вне меню — закрыть все открытые меню
        if (!e.target.closest || !e.target.closest('.p-order-menu')) {
            closeMenus();
        }
    });

    // Закрытие меню по клику в любом другом месте страницы
    document.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('.p-order-menu')) return;
        closeMenus();
    });
});
</script>
