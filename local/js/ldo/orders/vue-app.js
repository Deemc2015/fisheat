(function () {
    "use strict";

    if (typeof BX === "undefined" || typeof BX.Vue3 === "undefined") {
        console.error("[ldo.orders] ui.vue3 не загружен");
        return;
    }

    const { ref, reactive, computed } = BX.Vue3;

    // ============================================================
    // Утилиты
    // ============================================================
    const fmtMoney = (value) => {
        return parseFloat(value || 0).toLocaleString("ru-RU", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    };

    const fmtWeight = (value) => {
        const weight = parseFloat(value) || 0;
        if (weight <= 0) return "—";
        if (weight >= 1000) {
            return (weight / 1000).toLocaleString("ru-RU", { maximumFractionDigits: 2 }) + " кг";
        }
        return weight.toLocaleString("ru-RU", { maximumFractionDigits: 1 }) + " г";
    };

    // Приведение данных заказов к массиву
    const toArray = (value) => {
        if (Array.isArray(value)) return value;
        if (value && typeof value === "object") return Object.values(value);
        return [];
    };

    // ============================================================
    // OrdersFilter — форма фильтра (AJAX, без перезагрузки)
    // ============================================================
    const OrdersFilter = {
        props: ["model", "statuses", "deliveryServices", "paySystems", "restaurants", "exportEnabled", "exportUrl", "loading"],
        emits: ["submit-filter", "reset-filter"],
        template: `
            <form class="p-orders-filter" @submit.prevent="$emit('submit-filter')">
                <div class="p-orders-filter__row">
                    <div class="p-orders-filter__group">
                        <label for="p-orders-status">Статус</label>
                        <select id="p-orders-status" v-model="model.status">
                            <option value="">Все статусы</option>
                            <option v-for="(name, id) in statuses" :key="id" :value="id">{{ name }}</option>
                        </select>
                    </div>

                    <div class="p-orders-filter__group">
                        <label for="p-orders-delivery">Способ доставки</label>
                        <select id="p-orders-delivery" v-model="model.delivery">
                            <option value="">Все способы</option>
                            <option v-for="(name, id) in deliveryServices" :key="id" :value="id">{{ name }}</option>
                        </select>
                    </div>

                    <div class="p-orders-filter__group">
                        <label for="p-orders-pay">Способ оплаты</label>
                        <select id="p-orders-pay" v-model="model.paySystem">
                            <option value="">Все способы</option>
                            <option v-for="(name, id) in paySystems" :key="id" :value="id">{{ name }}</option>
                        </select>
                    </div>

                    <div class="p-orders-filter__group">
                        <label for="p-orders-restaurant">Ресторан</label>
                        <select id="p-orders-restaurant" v-model="model.restaurant">
                            <option value="">Все рестораны</option>
                            <option v-for="(name, xmlId) in restaurants" :key="xmlId" :value="xmlId">{{ name }}</option>
                        </select>
                    </div>

                    <div class="p-orders-filter__group">
                        <label for="p-orders-date-from">Дата с</label>
                        <input type="date" id="p-orders-date-from" v-model="model.dateFrom">
                    </div>

                    <div class="p-orders-filter__group">
                        <label for="p-orders-date-to">Дата по</label>
                        <input type="date" id="p-orders-date-to" v-model="model.dateTo">
                    </div>

                    <div class="p-orders-filter__group p-orders-filter__group--grow">
                        <label for="p-orders-search">Поиск</label>
                        <input type="text" id="p-orders-search" v-model="model.search" placeholder="№ заказа, имя, e-mail">
                    </div>

                    <div class="p-orders-filter__actions">
                        <button type="submit" class="p-btn p-btn--primary" :disabled="loading">
                            {{ loading ? 'Загрузка…' : 'Применить' }}
                        </button>
                        <button type="button" class="p-btn p-btn--ghost" @click="$emit('reset-filter')" :disabled="loading">Сбросить</button>
                        <a v-if="exportEnabled" class="p-btn p-btn--excel" :href="exportUrl" target="_blank" rel="noopener">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14 2H6C4.9 2 4 2.9 4 4V20C4 21.1 4.9 22 6 22H18C19.1 22 20 21.1 20 20V8L14 2ZM18 20H6V4H13V9H18V20ZM8 15H10.5L12 12.9L13.5 15H16L13.5 11.5L16 8H13.5L12 10.1L10.5 8H8L10.5 11.5L8 15Z" fill="currentColor"/></svg>
                            Выгрузить в Excel
                        </a>
                    </div>
                </div>
            </form>
        `,
    };

    // ============================================================
    // OrdersApp — корневое приложение (AJAX-фильтр и пагинация)
    // ============================================================
    const OrdersApp = {
        name: "OrdersApp",
        components: { OrdersFilter },
        props: {
            data: { type: Object, default: () => ({}) },
        },
        setup(props) {
            // Данные приходят через rootProps (шаблон vue) или глобальную переменную (fallback)
            const initial = (props && props.data && Object.keys(props.data).length ? props.data : null)
                || window.LDO_ORDERS_DATA || {};

            const state = reactive({
                orders: toArray(initial.ORDERS),
                statuses: initial.STATUSES || {},
                // Полные карты — для колонок таблицы (названия у ВСЕХ заказов)
                deliveryServices: initial.DELIVERY_SERVICES || {},
                paySystems: initial.PAY_SYSTEMS || {},
                // Отфильтрованные по параметрам карты — только для селектов фильтра
                filterDelivery: initial.FILTER_DELIVERY || initial.DELIVERY_SERVICES || {},
                filterPay: initial.FILTER_PAY || initial.PAY_SYSTEMS || {},
                filterRestaurants: initial.FILTER_RESTAURANTS || {},
                orderProps: initial.ORDER_PROPS || {},
                baskets: initial.BASKETS || {},
                deliverySum: initial.DELIVERY_SUM || {},
                nav: initial.NAV || { TOTAL_COUNT: 0, PAGE_COUNT: 1, CURRENT_PAGE: 1 },
                baseParams: initial.BASE_PARAMS || {},
                pageParam: initial.PAGE_PARAM || "orders",
                pageSize: initial.PAGE_SIZE || 50,
                exportEnabled: initial.EXPORT_ENABLED !== false,
                loading: false,
                error: "",
            });

            // Поля формы фильтра (модель)
            const model = reactive({
                status: (initial.FILTER && Array.isArray(initial.FILTER.STATUS) && initial.FILTER.STATUS.length)
                    ? initial.FILTER.STATUS[0] : "",
                delivery: (initial.FILTER && Array.isArray(initial.FILTER.DELIVERY) && initial.FILTER.DELIVERY.length)
                    ? String(initial.FILTER.DELIVERY[0]) : "",
                paySystem: (initial.FILTER && Array.isArray(initial.FILTER.PAY_SYSTEM) && initial.FILTER.PAY_SYSTEM.length)
                    ? String(initial.FILTER.PAY_SYSTEM[0]) : "",
                restaurant: (initial.FILTER && initial.FILTER.RESTAURANT) || "",
                dateFrom: (initial.FILTER && initial.FILTER.DATE_FROM) || "",
                dateTo: (initial.FILTER && initial.FILTER.DATE_TO) || "",
                search: (initial.FILTER && initial.FILTER.SEARCH) || "",
            });

            const openId = ref(null);
            const menuId = ref(null);

            const toggleDetail = (id) => {
                openId.value = openId.value === id ? null : id;
                menuId.value = null;
            };
            const toggleMenu = (id) => {
                menuId.value = menuId.value === id ? null : id;
            };

            // Подготовка строки заказа для вывода
            const view = computed(() => {
                return state.orders.map((o) => {
                    const propsOfOrder = state.orderProps[o.ID] || {};
                    let fio = propsOfOrder.FIO || "";
                    if (fio === "") {
                        fio = [o.USER_LAST_NAME || "", o.USER_NAME || ""].filter(Boolean).join(" ").trim();
                    }
                    if (fio === "") fio = o.USER_LOGIN || "";
                    const phone = propsOfOrder.PHONE || "";
                    const email = propsOfOrder.EMAIL || o.USER_EMAIL || o.USER_LOGIN || "";

                    return Object.assign({}, o, {
                        number: o.ACCOUNT_NUMBER !== "" && o.ACCOUNT_NUMBER != null ? o.ACCOUNT_NUMBER : o.ID,
                        fio,
                        phone,
                        email,
                        statusName: state.statuses[o.STATUS_ID] || o.STATUS_ID,
                        deliveryName: state.deliveryServices[o.DELIVERY_ID] || "",
                        paySystemName: state.paySystems[o.PAY_SYSTEM_ID] || "",
                        items: state.baskets[o.ID] || [],
                        deliverySum: state.deliverySum[o.ID] || 0,
                    });
                });
            });

            // Ссылка экспорта с учётом текущего фильтра
            const exportUrl = computed(() => {
                const params = Object.assign({}, state.baseParams, { EXPORT: "excel" });
                const qs = new URLSearchParams();
                Object.keys(params).forEach((k) => qs.set(k, params[k]));
                const str = qs.toString();
                return str !== "" ? "?" + str : "";
            });

            // AJAX-загрузка списка
            const load = (params) => {
                state.loading = true;
                state.error = "";

                BX.ajax.runComponentAction("ldo:orders.list", "getList", {
                    mode: "class",
                    data: params,
                }).then((response) => {
                    state.loading = false;
                    const payload = response && response.data;
                    if (payload && payload.success && payload.data) {
                        const d = payload.data;
                        state.orders = toArray(d.ORDERS);
                        state.orderProps = d.ORDER_PROPS || {};
                        state.baskets = d.BASKETS || {};
                        state.deliverySum = d.DELIVERY_SUM || {};
                        state.nav = d.NAV || { TOTAL_COUNT: 0, PAGE_COUNT: 1, CURRENT_PAGE: 1 };
                        state.baseParams = d.BASE_PARAMS || {};
                        if (d.PAGE_SIZE) {
                            state.pageSize = d.PAGE_SIZE;
                        }
                        if (d.STATUSES && Object.keys(d.STATUSES).length) {
                            state.statuses = d.STATUSES;
                        }
                        if (d.FILTER_DELIVERY) {
                            state.filterDelivery = d.FILTER_DELIVERY;
                        }
                        if (d.FILTER_PAY) {
                            state.filterPay = d.FILTER_PAY;
                        }
                        if (d.FILTER_RESTAURANTS) {
                            state.filterRestaurants = d.FILTER_RESTAURANTS;
                        }
                        if (d.DELIVERY_SERVICES) {
                            state.deliveryServices = d.DELIVERY_SERVICES;
                        }
                        if (d.PAY_SYSTEMS) {
                            state.paySystems = d.PAY_SYSTEMS;
                        }
                    } else {
                        state.error = (payload && payload.error) || "Ошибка загрузки списка";
                    }
                }).catch(() => {
                    state.loading = false;
                    state.error = "Ошибка сети при загрузке списка";
                });
            };

            const buildParams = (page) => {
                return {
                    STATUS: model.status !== "" ? [model.status] : [],
                    DELIVERY: model.delivery !== "" ? [parseInt(model.delivery, 10)] : [],
                    PAY_SYSTEM: model.paySystem !== "" ? [parseInt(model.paySystem, 10)] : [],
                    RESTAURANT: model.restaurant,
                    // Списки способов из параметров компонента (state.filterDelivery/filterPay
                    // уже отфильтрованы бэкендом) — передаём, чтобы AJAX-ответ строился
                    // с теми же опциями в фильтре
                    DELIVERY_SERVICES: Object.keys(state.filterDelivery).map(Number),
                    PAY_SYSTEMS: Object.keys(state.filterPay).map(Number),
                    DATE_FROM: model.dateFrom,
                    DATE_TO: model.dateTo,
                    SEARCH: model.search,
                    PAGE: page,
                    PAGE_SIZE: state.pageSize,
                };
            };

            // Применение фильтра (страница 1)
            const applyFilter = () => {
                load(buildParams(1));
            };

            // Сброс фильтра
            const resetFilter = () => {
                model.status = "";
                model.delivery = "";
                model.paySystem = "";
                model.restaurant = "";
                model.dateFrom = "";
                model.dateTo = "";
                model.search = "";
                load(buildParams(1));
            };

            // Переход на страницу
            const goPage = (page) => {
                if (page < 1 || page > state.nav.PAGE_COUNT || page === state.nav.CURRENT_PAGE) {
                    return;
                }
                load(buildParams(page));
            };

            // Окно страниц для пагинации
            const windowPages = computed(() => {
                const current = state.nav.CURRENT_PAGE;
                const total = state.nav.PAGE_COUNT;
                const start = Math.max(1, current - 4);
                const end = Math.min(total, current + 4);
                const arr = [];
                for (let p = start; p <= end; p++) arr.push(p);
                return arr;
            });

            return {
                state,
                model,
                view,
                exportUrl,
                windowPages,
                openId,
                menuId,
                toggleDetail,
                toggleMenu,
                applyFilter,
                resetFilter,
                goPage,
                fmtMoney,
                fmtWeight,
                parseFloat,
            };
        },
        template: `
            <div class="p-main">
                <div class="p-section">
                    <div class="p-section__header">
                        <h2 class="p-section__title">Заказы сайта</h2>
                        <span style="font-size:13px; color:var(--color-muted);">Всего: {{ state.nav.TOTAL_COUNT }}</span>
                    </div>

                    <OrdersFilter
                        :model="model"
                        :statuses="state.statuses"
                        :delivery-services="state.filterDelivery"
                        :pay-systems="state.filterPay"
                        :restaurants="state.filterRestaurants"
                        :export-enabled="state.exportEnabled"
                        :export-url="exportUrl"
                        :loading="state.loading"
                        @submit-filter="applyFilter"
                        @reset-filter="resetFilter"
                    />

                    <div v-if="state.error" class="p-users-empty" style="margin-bottom:12px; color:#e74c3c;">{{ state.error }}</div>

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
                                    <tr v-if="!view.length && !state.loading">
                                        <td colspan="9">
                                            <div class="p-users-empty">Заказы не найдены</div>
                                        </td>
                                    </tr>
                                    <tr v-if="state.loading && !view.length">
                                        <td colspan="9">
                                            <div class="p-users-empty">Загрузка…</div>
                                        </td>
                                    </tr>

                                    <template v-for="order in view" :key="order.ID">
                                        <tr>
                                            <td>
                                                <div class="p-order-num">{{ order.number }}</div>
                                                <div class="p-order-sub">ID: {{ order.ID }}</div>
                                            </td>
                                            <td>{{ order.DATE_INSERT || '—' }}</td>
                                            <td>
                                                <div v-if="order.fio !== ''" class="p-users-fio">{{ order.fio }}</div>
                                                <div class="p-users-login">{{ order.email !== '' ? order.email : (order.USER_LOGIN || '—') }}</div>
                                            </td>
                                            <td>{{ order.phone !== '' ? order.phone : '—' }}</td>
                                            <td>{{ order.deliveryName !== '' ? order.deliveryName : '—' }}</td>
                                            <td>{{ order.paySystemName !== '' ? order.paySystemName : '—' }}</td>
                                            <td style="text-align:right; white-space:nowrap;">{{ fmtMoney(order.PRICE) }} ₽</td>
                                            <td style="text-align:right;">
                                                <span class="p-order-status" :data-status="order.STATUS_ID">{{ order.statusName }}</span>
                                            </td>
                                            <td>
                                                <div class="p-order-menu" :class="{ open: menuId === order.ID }">
                                                    <button type="button" class="p-order-menu__trigger" title="Действия с заказом" aria-label="Действия с заказом" aria-haspopup="true" :aria-expanded="menuId === order.ID" @click="toggleMenu(order.ID)">
                                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
                                                    </button>
                                                    <div v-if="menuId === order.ID" class="p-order-menu__dropdown">
                                                        <button type="button" class="p-order-menu__item" @click="toggleDetail(order.ID)">
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5C7 5 2.73 8.11 1 12C2.73 15.89 7 19 12 19C17 19 21.27 15.89 23 12C21.27 8.11 17 5 12 5ZM12 17C9.24 17 7 14.76 7 12C7 9.24 9.24 7 12 7C14.76 7 17 9.24 17 12C17 14.76 14.76 17 12 17ZM12 9C10.34 9 9 10.34 9 12C9 13.66 10.34 15 12 15C13.66 15 15 13.66 15 12C15 10.34 13.66 9 12 9Z" fill="currentColor"/></svg>
                                                            {{ openId === order.ID ? 'Скрыть состав' : 'Подробнее' }}
                                                        </button>
                                                        <button type="button" class="p-order-menu__item" @click="menuId = null">
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19 8H5C3.34 8 2 9.34 2 11V17H6V21H18V17H22V11C22 9.34 20.66 8 19 8ZM16 19H8V14H16V19ZM18 12C17.45 12 17 11.55 17 11C17 10.45 17.45 10 18 10C18.55 10 19 10.45 19 11C19 11.55 18.55 12 18 12ZM17 3H7V7H17V3Z" fill="currentColor"/></svg>
                                                            Печать
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="p-order-detail-row" :class="{ open: openId === order.ID }">
                                            <td colspan="9">
                                                <div class="p-order-detail">
                                                    <table v-if="order.items.length" class="p-order-detail__products">
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
                                                            <tr v-for="item in order.items" :key="item.ID">
                                                                <td class="p-order-detail__name">{{ item.NAME }}</td>
                                                                <td style="text-align:right; white-space:nowrap;">{{ fmtWeight(item.WEIGHT) }}</td>
                                                                <td style="text-align:right; white-space:nowrap;">{{ fmtMoney(item.PRICE) }} ₽</td>
                                                                <td style="text-align:center;">{{ parseFloat(item.QUANTITY) || 0 }}</td>
                                                                <td style="text-align:right; white-space:nowrap;">{{ fmtMoney(item.SUMMARY_PRICE) }} ₽</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                    <div v-else class="p-order-detail__empty">Нет данных о составе заказа</div>

                                                    <div class="p-order-detail__totals">
                                                        <div class="p-order-detail__total">
                                                            <span>Сумма доставки</span>
                                                            <b>{{ fmtMoney(order.deliverySum) }} ₽</b>
                                                        </div>
                                                        <div class="p-order-detail__total">
                                                            <span>Скидка</span>
                                                            <b>{{ fmtMoney(order.DISCOUNT_ALL) }} ₽</b>
                                                        </div>
                                                        <div class="p-order-detail__total p-order-detail__total--final">
                                                            <span>Сумма заказа</span>
                                                            <b>{{ fmtMoney(order.PRICE) }} ₽</b>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="state.nav.PAGE_COUNT > 1" class="p-users-nav">
                            <a v-if="state.nav.CURRENT_PAGE > 1" href="#" @click.prevent="goPage(state.nav.CURRENT_PAGE - 1)">‹</a>
                            <span v-else class="page disabled">‹</span>

                            <template v-for="p in windowPages" :key="p">
                                <span v-if="p === state.nav.CURRENT_PAGE" class="page current">{{ p }}</span>
                                <a v-else href="#" @click.prevent="goPage(p)">{{ p }}</a>
                            </template>

                            <a v-if="state.nav.CURRENT_PAGE < state.nav.PAGE_COUNT" href="#" @click.prevent="goPage(state.nav.CURRENT_PAGE + 1)">›</a>
                            <span v-else class="page disabled">›</span>
                        </div>
                    </div>
                </div>
            </div>
        `,
    };

    // Экспорт компонентов — приложение создаёт и монтирует шаблон (templates/vue/template.php)
    window.BX.LDO = window.BX.LDO || {};
    window.BX.LDO.Orders = {
        OrdersApp,
        OrdersFilter,
    };
})();
