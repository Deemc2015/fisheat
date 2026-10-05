/**
 * Доска заказов ресторана (KDS для кухни).
 *
 * Колонки доски — ЭТАПЫ ГОТОВКИ (Принят → Готовится → Готов → Передан),
 * они не зависят от статусов заказа. Этап хранит PHP-компонент ldo:kds.board
 * в служебном свойстве заказа kds_stage.
 *
 * Экраны:
 *   1) выбор ресторана (при входе),
 *   2) доска: заказы по этапам готовки.
 *
 * Заказ переводится на другой этап перетаскиванием карточки (HTML5 drag & drop)
 * либо кнопкой «следующий этап». Данные — getList, смена этапа — setStage.
 */
(function () {
    "use strict";

    if (typeof BX === "undefined" || typeof BX.Vue3 === "undefined") {
        console.error("[ldo.kds] ui.vue3 не загружен");
        return;
    }

    const { reactive, computed, onMounted, onBeforeUnmount } = BX.Vue3;

    const REFRESH_MS = 30000;
    const LS_KEY = "ldo_kds_restaurant";

    // Приведение данных к массиву
    const toArray = (value) => {
        if (Array.isArray(value)) return value;
        if (value && typeof value === "object") return Object.values(value);
        return [];
    };

    const KdsApp = {
        name: "KdsApp",
        props: {
            data: { type: Object, default: () => ({}) },
        },
        setup(props) {
            // Данные приходят через rootProps (шаблон) или глобальную переменную
            const initial = (props && props.data && Object.keys(props.data).length ? props.data : null)
                || window.LDO_KDS_DATA || {};

            const state = reactive({
                restaurants: initial.RESTAURANTS || {},
                stages: toArray(initial.STAGES),
                orders: toArray(initial.ORDERS),
                restaurant: initial.RESTAURANT || "",
                restaurantName: initial.RESTAURANT_NAME || "",
                daysBack: initial.DAYS_BACK || 1,
                serverTime: initial.SERVER_TIME || "",
                loading: false,
                error: "",
                autoRefresh: true,
            });

            // Состояние перетаскивания карточки
            const drag = reactive({
                orderId: null,
                from: "",
                over: "",
            });

            let timer = null;

            // Экран: 'select' — выбор ресторана, 'board' — доска
            const screen = computed(() => (state.restaurant !== "" ? "board" : "select"));

            // ============================================================
            // Загрузка данных
            // ============================================================

            const applyData = (data) => {
                if (!data) return;
                state.restaurants = data.RESTAURANTS || state.restaurants;
                state.stages = toArray(data.STAGES);
                state.orders = toArray(data.ORDERS);
                state.restaurant = data.RESTAURANT || "";
                state.restaurantName = data.RESTAURANT_NAME || "";
                state.daysBack = data.DAYS_BACK || state.daysBack;
                state.serverTime = data.SERVER_TIME || "";
            };

            const load = () => {
                if (state.restaurant === "") {
                    return;
                }

                state.loading = true;
                state.error = "";

                BX.ajax.runComponentAction("ldo:kds.board", "getList", {
                    mode: "class",
                    data: {
                        RESTAURANT: state.restaurant,
                        DAYS_BACK: state.daysBack,
                    },
                }).then((response) => {
                    state.loading = false;
                    const payload = response && response.data;
                    if (payload && payload.success && payload.data) {
                        applyData(payload.data);
                    } else {
                        state.error = (payload && payload.error) || "Не удалось загрузить заказы";
                    }
                }).catch(() => {
                    state.loading = false;
                    state.error = "Ошибка сети при загрузке заказов";
                });
            };

            const selectRestaurant = (xmlId, name) => {
                state.restaurant = xmlId;
                state.restaurantName = name || state.restaurants[xmlId] || "";
                try {
                    window.localStorage.setItem(LS_KEY, xmlId);
                } catch (e) {
                    /* localStorage может быть недоступен */
                }
                load();
            };

            const changeRestaurant = () => {
                state.restaurant = "";
                state.restaurantName = "";
                state.orders = [];
                try {
                    window.localStorage.removeItem(LS_KEY);
                } catch (e) {
                    /* ignore */
                }
            };

            // ============================================================
            // Колонки-этапы и карточки
            // ============================================================

            const ordersByStage = (stageId) => state.orders.filter((order) => order.STAGE === stageId);

            const countIn = (stageId) => state.orders.filter((order) => order.STAGE === stageId).length;

            const stageName = (stageId) => {
                const column = state.stages.find((item) => item.ID === stageId);
                return column ? column.NAME : stageId;
            };

            // Следующий этап по порядку колонок
            const nextStage = (stageId) => {
                const ids = state.stages.map((item) => item.ID);
                const index = ids.indexOf(stageId);
                if (index === -1 || index >= ids.length - 1) {
                    return "";
                }
                return ids[index + 1];
            };

            // ============================================================
            // Смена этапа готовки
            // ============================================================

            const setOrderStage = (orderId, stageId) => {
                if (!orderId || stageId === "") {
                    return;
                }

                state.loading = true;
                state.error = "";

                BX.ajax.runComponentAction("ldo:kds.board", "setStage", {
                    mode: "class",
                    data: {
                        ORDER_ID: orderId,
                        STAGE: stageId,
                    },
                }).then((response) => {
                    state.loading = false;
                    const payload = response && response.data;
                    if (payload && payload.success) {
                        // Оптимистично переносим карточку, затем синхронизируемся
                        state.orders = state.orders.map((item) => (
                            item.ID === orderId ? Object.assign({}, item, { STAGE: stageId }) : item
                        ));
                        load();
                    } else {
                        state.error = (payload && payload.error) || "Не удалось сменить этап";
                    }
                }).catch(() => {
                    state.loading = false;
                    state.error = "Ошибка сети при смене этапа";
                });
            };

            const moveOrder = (order, stageId) => {
                setOrderStage(order.ID, nextStage(stageId));
            };

            // ============================================================
            // Перетаскивание карточек между колонками
            // ============================================================

            const onDragStart = (order, event) => {
                drag.orderId = order.ID;
                drag.from = order.STAGE;
                drag.over = "";
                if (event && event.dataTransfer) {
                    event.dataTransfer.effectAllowed = "move";
                    // Некоторые браузеры требуют данные для начала перетаскивания
                    try {
                        event.dataTransfer.setData("text/plain", String(order.ID));
                    } catch (e) {
                        /* ignore */
                    }
                }
            };

            const onDragOver = (stageId, event) => {
                if (event) {
                    event.preventDefault();
                    if (event.dataTransfer) {
                        event.dataTransfer.dropEffect = "move";
                    }
                }
                drag.over = stageId;
            };

            const onDragLeave = (stageId) => {
                if (drag.over === stageId) {
                    drag.over = "";
                }
            };

            const onDrop = (stageId) => {
                const orderId = drag.orderId;
                const from = drag.from;

                drag.orderId = null;
                drag.from = "";
                drag.over = "";

                if (!orderId || stageId === "" || from === stageId) {
                    return;
                }

                setOrderStage(orderId, stageId);
            };

            const onDragEnd = () => {
                drag.orderId = null;
                drag.from = "";
                drag.over = "";
            };

            // ============================================================
            // Автообновление
            // ============================================================

            const startTimer = () => {
                stopTimer();
                timer = window.setInterval(() => {
                    if (state.autoRefresh && state.restaurant !== "" && !state.loading && drag.orderId === null) {
                        load();
                    }
                }, REFRESH_MS);
            };

            const stopTimer = () => {
                if (timer !== null) {
                    window.clearInterval(timer);
                    timer = null;
                }
            };

            onMounted(() => {
                // Восстанавливаем последний выбранный ресторан
                if (state.restaurant === "") {
                    let saved = "";
                    try {
                        saved = window.localStorage.getItem(LS_KEY) || "";
                    } catch (e) {
                        saved = "";
                    }
                    if (saved !== "" && state.restaurants[saved]) {
                        selectRestaurant(saved, state.restaurants[saved]);
                    }
                } else if (state.orders.length === 0) {
                    load();
                }
                startTimer();
            });

            onBeforeUnmount(() => {
                stopTimer();
            });

            return {
                state,
                drag,
                screen,
                load,
                selectRestaurant,
                changeRestaurant,
                ordersByStage,
                countIn,
                stageName,
                nextStage,
                moveOrder,
                onDragStart,
                onDragOver,
                onDragLeave,
                onDrop,
                onDragEnd,
            };
        },
        template: `
            <div class="kds-page">
                <header class="kds-head">
                    <h1 class="kds-head__title">
                        Кухня<span v-if="state.restaurantName"> — {{ state.restaurantName }}</span>
                    </h1>
                    <div v-if="screen === 'board'" class="kds-head__tools">
                        <span class="kds-time">{{ state.serverTime }}</span>
                        <label class="kds-auto">
                            <input type="checkbox" v-model="state.autoRefresh">
                            Автообновление
                        </label>
                        <button type="button" class="kds-btn kds-btn--ghost" :disabled="state.loading" @click="load">
                            {{ state.loading ? 'Обновляю…' : 'Обновить' }}
                        </button>
                        <button type="button" class="kds-btn kds-btn--ghost" @click="changeRestaurant">
                            Сменить ресторан
                        </button>
                    </div>
                </header>

                <div v-if="state.error" class="kds-error">{{ state.error }}</div>

                <!-- Экран выбора ресторана -->
                <div v-if="screen === 'select'" class="kds-select">
                    <p class="kds-select__hint">Выберите ресторан, чтобы открыть доску кухни</p>
                    <div class="kds-select__grid">
                        <button
                            v-for="(name, xmlId) in state.restaurants"
                            :key="xmlId"
                            type="button"
                            class="kds-select__item"
                            @click="selectRestaurant(xmlId, name)"
                        >
                            {{ name }}
                        </button>
                    </div>
                    <div v-if="!Object.keys(state.restaurants).length" class="kds-empty">
                        Нет активных ресторанов с заполненным XML_ID
                    </div>
                </div>

                <!-- Доска: колонки по этапам готовки -->
                <div v-else class="kds-board">
                    <div class="kds-columns">
                        <div
                            v-for="column in state.stages"
                            :key="column.ID"
                            class="kds-column"
                            :class="{ 'kds-column--over': drag.over === column.ID }"
                            @dragover="onDragOver(column.ID, $event)"
                            @dragenter.prevent
                            @dragleave="onDragLeave(column.ID)"
                            @drop.prevent="onDrop(column.ID)"
                        >
                            <div class="kds-column__head">
                                <span class="kds-column__title">{{ column.NAME }}</span>
                                <span class="kds-column__count">{{ countIn(column.ID) }}</span>
                            </div>
                            <div class="kds-column__body">
                                <div v-if="!countIn(column.ID)" class="kds-column__empty">
                                    Перетащите заказ сюда
                                </div>

                                <div
                                    v-for="order in ordersByStage(column.ID)"
                                    :key="order.ID"
                                    class="kds-card"
                                    :class="{ 'kds-card--dragging': drag.orderId === order.ID }"
                                    draggable="true"
                                    @dragstart="onDragStart(order, $event)"
                                    @dragend="onDragEnd"
                                >
                                    <div class="kds-card__top">
                                        <span class="kds-card__num">№ {{ order.NUMBER }}</span>
                                        <span class="kds-card__time">{{ order.TIME_INSERT }}</span>
                                    </div>

                                    <div class="kds-card__meta">
                                        <span
                                            class="kds-badge"
                                            :class="order.IS_DELIVERY ? 'kds-badge--delivery' : (order.IS_PICKUP ? 'kds-badge--pickup' : 'kds-badge--none')"
                                        >
                                            {{ order.IS_DELIVERY ? 'Доставка' : (order.IS_PICKUP ? 'Самовывоз' : 'Без доставки') }}
                                        </span>
                                        <span v-if="order.PERSONS" class="kds-card__persons">{{ order.PERSONS }} персон</span>
                                    </div>

                                    <div v-if="order.IS_DELIVERY && order.ADDRESS" class="kds-card__addr">{{ order.ADDRESS }}</div>
                                    <div v-else-if="order.PICKUP_POINT" class="kds-card__addr">Точка: {{ order.PICKUP_POINT }}</div>
                                    <div v-if="order.DELIVERY_TIME" class="kds-card__wish">Желаемое время: {{ order.DELIVERY_TIME }}</div>

                                    <ul v-if="order.ITEMS.length" class="kds-card__items">
                                        <li v-for="(item, index) in order.ITEMS" :key="index">
                                            <b>{{ item.QTY }}</b> × {{ item.NAME }}
                                        </li>
                                    </ul>
                                    <div v-else class="kds-card__items-empty">Нет состава заказа</div>

                                    <div v-if="order.COMMENT" class="kds-card__comment">{{ order.COMMENT }}</div>

                                    <div class="kds-card__footer">
                                        <span class="kds-card__sum">{{ order.PRICE }} ₽</span>
                                        <button
                                            v-if="nextStage(column.ID)"
                                            type="button"
                                            class="kds-btn kds-btn--primary kds-card__next"
                                            :disabled="state.loading"
                                            @click="moveOrder(order, column.ID)"
                                        >
                                            {{ stageName(nextStage(column.ID)) }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `,
    };

    // Экспорт приложения — монтируется шаблоном компонента (templates/.default/template.php)
    window.BX.LDO = window.BX.LDO || {};
    window.BX.LDO.Kds = {
        KdsApp,
    };
})();
