/**
 * ChartLine — линейный график на SVG (без внешних библиотек).
 *
 * MVVM: данные серий приходят из store (Model → ViewModel), компонент
 * декларативно вычисляет координаты и рендерит SVG (View).
 *
 * @example <ChartLine :data="store.chart" :height="260" />
 */
export const ChartLine = {
    props: {
        data: {
            type: Object,
            required: true,
        },
        height: {
            type: Number,
            default: 260,
        },
    },

    computed: {
        labels()
        {
            return this.data.labels || [];
        },

        series()
        {
            return this.data.series || [];
        },

        width()
        {
            return 600;
        },

        padding()
        {
            return {top: 16, right: 16, bottom: 28, left: 48};
        },

        innerWidth()
        {
            return this.width - this.padding.left - this.padding.right;
        },

        innerHeight()
        {
            return this.height - this.padding.top - this.padding.bottom;
        },

        maxValue()
        {
            let max = 0;
            for (const s of this.series)
            {
                for (const v of s.values)
                {
                    if (v > max)
                    {
                        max = v;
                    }
                }
            }
            return max || 1;
        },

        /** Горизонтальные линии сетки с подписями значений */
        gridLines()
        {
            const lines = [];
            const steps = 4;
            for (let i = 0; i <= steps; i++)
            {
                const value = (this.maxValue / steps) * i;
                const y = this.padding.top + this.innerHeight - (this.innerHeight * (i / steps));
                lines.push({value: Math.round(value), y});
            }
            return lines;
        },

        /** Пути для серий */
        seriesPaths()
        {
            return this.series.map((s) => {
                const points = s.values.map((v, i) => {
                    const x = this.padding.left + (this.innerWidth * (i / Math.max(this.labels.length - 1, 1)));
                    const y = this.padding.top + this.innerHeight - (this.innerHeight * (v / this.maxValue));
                    return {x, y};
                });
                const d = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' ');
                return {name: s.name, color: s.color || '#2ecc71', d, points};
            });
        },
    },

    // language=Vue
    template: `
        <svg
            class="p-chart-line"
            :viewBox="'0 0 ' + width + ' ' + height"
            preserveAspectRatio="xMidYMid meet"
            role="img"
        >
            <g>
                <line
                    v-for="line in gridLines"
                    :key="'grid-' + line.y"
                    :x1="padding.left"
                    :x2="width - padding.right"
                    :y1="line.y"
                    :y2="line.y"
                    stroke="rgba(255,255,255,.08)"
                    stroke-width="1"
                />
                <text
                    v-for="line in gridLines"
                    :key="'lbl-' + line.y"
                    :x="padding.left - 8"
                    :y="line.y + 4"
                    text-anchor="end"
                    font-size="11"
                    fill="var(--color-muted, #8b93a7)"
                >{{ line.value }}</text>
            </g>

            <g v-for="path in seriesPaths" :key="path.name">
                <path
                    :d="path.d"
                    fill="none"
                    :stroke="path.color"
                    stroke-width="2.5"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                />
                <circle
                    v-for="p in path.points"
                    :key="path.name + '-' + p.x"
                    :cx="p.x"
                    :cy="p.y"
                    r="3.5"
                    :fill="path.color"
                />
            </g>

            <g>
                <text
                    v-for="(label, i) in labels"
                    :key="'x-' + i"
                    :x="padding.left + (innerWidth * (i / Math.max(labels.length - 1, 1)))"
                    :y="height - 8"
                    text-anchor="middle"
                    font-size="11"
                    fill="var(--color-muted, #8b93a7)"
                >{{ label }}</text>
            </g>
        </svg>
    `,
};
