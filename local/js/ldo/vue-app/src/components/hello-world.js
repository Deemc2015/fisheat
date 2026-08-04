/**
 * Demo-компонент: проверка работы BitrixVue 3 в партнёрском кабинете.
 *
 * Демонстрирует паттерн MVVM:
 * - View: декларативный шаблон ниже;
 * - ViewModel: реактивные data (count) и props (title);
 * - Model: данные из rootProps (передаются из PHP) + фразы через $Bitrix.Loc.
 *
 * @example <HelloWorld title="Партнёрский кабинет" />
 */
export const HelloWorld = {
    props: {
        title: {
            type: String,
            default: '',
        },
    },

    data()
    {
        return {
            count: 0,
        };
    },

    computed: {
        greeting()
        {
            return this.$Bitrix.Loc.getMessage('LDO_VUEAPP_HELLO') || 'Hello from BitrixVue 3!';
        },
    },

    methods: {
        getOptions()
        {
            // Доступ к контексту контроллера (Model из PHP)
            return this.$Bitrix.Application.get().getOptions();
        },
    },

    // language=Vue
    template: `
        <div class="vue-app-demo">
            <h3>{{ title }}</h3>
            <p>{{ greeting }}</p>
            <button type="button" @click="count++">
                {{ $Bitrix.Loc.getMessage('LDO_VUEAPP_CLICKS', {'#COUNT#': count}) }}
            </button>
        </div>
    `,
};
