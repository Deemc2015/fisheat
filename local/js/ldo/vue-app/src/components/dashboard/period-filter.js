/**
 * PeriodFilter — переключатель периода (день/неделя/месяц/год).
 *
 * MVVM: выбор пользователя (View) обновляет store (ViewModel),
 * компоненты автоматически перерисовываются.
 *
 * @example <PeriodFilter />
 */
export const PeriodFilter = {
    props: {
        periods: {
            type: Array,
            default: () => ['day', 'week', 'month', 'year'],
        },
    },

    computed: {
        // NB: $Bitrix — computed-свойство из глобального mixin BitrixVue.
        // В data() оно недоступно (data выполняется раньше computed), поэтому
        // локализацию выносим именно в computed.
        labels()
        {
            return {
                day: this.$Bitrix.Loc.getMessage('LDO_VUEAPP_PERIOD_DAY'),
                week: this.$Bitrix.Loc.getMessage('LDO_VUEAPP_PERIOD_WEEK'),
                month: this.$Bitrix.Loc.getMessage('LDO_VUEAPP_PERIOD_MONTH'),
                year: this.$Bitrix.Loc.getMessage('LDO_VUEAPP_PERIOD_YEAR'),
            };
        },
    },

    methods: {
        select(period)
        {
            this.$store.dashboard.setPeriod(period);
        },
    },

    // language=Vue
    template: `
        <div class="p-period-filter">
            <button
                v-for="period in periods"
                :key="period"
                type="button"
                class="p-period-filter__btn"
                :class="{ 'is-active': $store.dashboard.period === period }"
                @click="select(period)"
            >
                {{ labels[period] || period }}
            </button>
        </div>
    `,
};
