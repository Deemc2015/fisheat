import {defineStore} from 'ui.vue3.pinia';

/**
 * Pinia store дашборда партнёрского кабинета.
 *
 * MVVM: это часть ViewModel — централизованное реактивное состояние,
 * которое синхронизирует Model (данные из PHP/AJAX) и View (компоненты).
 *
 * Инициализация данных происходит из rootProps при создании приложения
 * (метод init), а обновления — через setData (например, при смене периода).
 */
export const useDashboardStore = defineStore('dashboard', {
    state: () => ({
        period: 'week',               // Активный период: day | week | month | year
        loading: false,               // Флаг загрузки данных
        cards: [],                    // Стат-карточки: {id, label, value, change, changeType, currency}
        chart: {                      // Данные графика
            labels: [],               // Подписи оси X
            series: [],               // Серии: {name, color, values: []}
        },
        table: {                      // Данные таблицы
            columns: [],              // Колонки: {key, title, align}
            rows: [],                 // Строки: {id, ...cells}
        },
        lastUpdated: null,            // Время последнего обновления
    }),

    getters: {
        /** Общий тренд по первой серии графика (в процентах) */
        chartTrend: (state) => {
            const values = state.chart.series[0]?.values ?? [];
            if (values.length < 2)
            {
                return 0;
            }
            const first = values[0];
            const last = values[values.length - 1];
            if (first === 0)
            {
                return 0;
            }
            return Math.round(((last - first) / first) * 100);
        },
    },

    actions: {
        /**
         * Инициализация store данными из rootProps.
         * @param {Object} data
         */
        init(data = {})
        {
            this.period = data.period || 'week';
            this.cards = data.cards || [];
            this.chart = data.chart || {labels: [], series: []};
            this.table = data.table || {columns: [], rows: []};
            this.lastUpdated = new Date().toISOString();
        },

        /**
         * Обновление данных (при смене периода, после AJAX и т.п.).
         * @param {Object} data
         */
        setData(data = {})
        {
            if (data.cards)
            {
                this.cards = data.cards;
            }
            if (data.chart)
            {
                this.chart = data.chart;
            }
            if (data.table)
            {
                this.table = data.table;
            }
            this.lastUpdated = new Date().toISOString();
        },

        /**
         * Установка периода.
         * @param {string} period
         */
        setPeriod(period)
        {
            if (this.period === period)
            {
                return;
            }
            this.period = period;
            // Точка для подгрузки данных с сервера (AJAX). Сейчас данные уже
            // переданы из PHP; при необходимости здесь вызывается загрузка.
        },

        /**
         * Начало/окончание загрузки.
         * @param {boolean} value
         */
        setLoading(value)
        {
            this.loading = value;
        },
    },
});
