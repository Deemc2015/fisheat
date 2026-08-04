import {useDashboardStore} from '../../store/dashboard';
import {StatCard} from './stat-card';
import {PeriodFilter} from './period-filter';
import {ChartLine} from './chart-line';
import {DataTable} from './data-table';

/**
 * DashboardApp — корневой компонент дашборда партнёрского кабинета.
 *
 * MVVM: ViewModel (Pinia store dashboard) связывает Model (данные из PHP/AJAX)
 * с View (декларативный шаблон, состоящий из дочерних компонентов).
 *
 * @example <DashboardApp />
 */
export const DashboardApp = {
    components: {
        StatCard,
        PeriodFilter,
        ChartLine,
        DataTable,
    },

    setup()
    {
        const store = useDashboardStore();
        return {store};
    },

    // language=Vue
    template: `
        <div class="p-dashboard">
            <!-- Стат-карточки -->
            <div class="p-stats">
                <StatCard
                    v-for="card in store.cards"
                    :key="card.id"
                    :item="card"
                />
            </div>

            <!-- Фильтр периода + график -->
            <div class="p-dashboard__panel">
                <div class="p-dashboard__panel-head">
                    <div class="p-dashboard__panel-title">
                        {{ $Bitrix.Loc.getMessage('LDO_VUEAPP_CHART_TITLE') }}
                    </div>
                    <PeriodFilter />
                </div>
                <ChartLine v-if="store.chart.series.length" :data="store.chart" :height="260" />
            </div>

            <!-- Таблица -->
            <div class="p-dashboard__panel">
                <div class="p-dashboard__panel-head">
                    <div class="p-dashboard__panel-title">
                        {{ $Bitrix.Loc.getMessage('LDO_VUEAPP_TABLE_TITLE') }}
                    </div>
                </div>
                <DataTable :columns="store.table.columns" :rows="store.table.rows" />
            </div>
        </div>
    `,
};
