/**
 * DataTable — таблица статистики с сортировкой по колонкам.
 *
 * MVVM: данные (строки/колонки) из store — Model/ViewModel,
 * шаблон декларативно отображает и сортирует (View).
 *
 * @example <DataTable :columns="store.table.columns" :rows="store.table.rows" />
 */
export const DataTable = {
    props: {
        columns: {
            type: Array,
            default: () => [],
        },
        rows: {
            type: Array,
            default: () => [],
        },
    },

    data()
    {
        return {
            sortKey: '',
            sortDir: 'asc', // asc | desc
        };
    },

    computed: {
        sortedRows()
        {
            if (!this.sortKey)
            {
                return this.rows;
            }
            const dir = this.sortDir === 'asc' ? 1 : -1;
            return [...this.rows].sort((a, b) => {
                const av = a[this.sortKey];
                const bv = b[this.sortKey];
                if (typeof av === 'number' && typeof bv === 'number')
                {
                    return (av - bv) * dir;
                }
                return String(av ?? '').localeCompare(String(bv ?? ''), 'ru') * dir;
            });
        },
    },

    methods: {
        toggleSort(key)
        {
            if (this.sortKey === key)
            {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            }
            else
            {
                this.sortKey = key;
                this.sortDir = 'asc';
            }
        },

        sortIcon(key)
        {
            if (this.sortKey !== key)
            {
                return '↕';
            }
            return this.sortDir === 'asc' ? '↑' : '↓';
        },
    },

    // language=Vue
    template: `
        <div class="p-table-wrap">
            <table class="p-table">
                <thead>
                    <tr>
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            :class="{ 'is-sortable': col.sortable }"
                            @click="col.sortable && toggleSort(col.key)"
                        >
                            {{ col.title }}
                            <span v-if="col.sortable" class="p-table__sort">{{ sortIcon(col.key) }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in sortedRows" :key="row.id">
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            :class="'p-table__cell--' + (col.align || 'left')"
                        >{{ row[col.key] }}</td>
                    </tr>
                    <tr v-if="sortedRows.length === 0">
                        <td :colspan="columns.length" class="p-table__empty">
                            {{ $Bitrix.Loc.getMessage('LDO_VUEAPP_TABLE_EMPTY') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `,
};
