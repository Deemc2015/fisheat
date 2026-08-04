/**
 * StatCard — карточка показателя (доход, заказы, средний чек и т.п.).
 *
 * MVVM: компонент получает данные как props (Model → ViewModel),
 * шаблон декларативно отображает их (View).
 *
 * @example <StatCard :item="card" />
 */
export const StatCard = {
    props: {
        item: {
            type: Object,
            required: true,
        },
    },

    computed: {
        changeClass()
        {
            const type = this.item.changeType;
            return {
                'p-stat-card__change--up': type === 'up',
                'p-stat-card__change--down': type === 'down',
            };
        },

        changeArrow()
        {
            return this.item.changeType === 'down' ? '↓' : '↑';
        },

        formattedValue()
        {
            const value = this.item.value;
            const currency = this.item.currency ? ` ${this.item.currency}` : '';
            return `${value}${currency}`;
        },
    },

    // language=Vue
    template: `
        <div class="p-stat-card">
            <div class="p-stat-card__label">{{ item.label }}</div>
            <div class="p-stat-card__value">{{ formattedValue }}</div>
            <div v-if="item.change" class="p-stat-card__change" :class="changeClass">
                {{ changeArrow }} {{ item.change }}
            </div>
        </div>
    `,
};
