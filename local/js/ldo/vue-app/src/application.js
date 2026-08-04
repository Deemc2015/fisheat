import {BitrixVue} from 'ui.vue3';
import {createPinia} from 'ui.vue3.pinia';
import {HelloWorld} from './components/hello-world';
import {DashboardApp} from './components/dashboard/dashboard-app';
import {useDashboardStore} from './store/dashboard';

/**
 * Контроллер Vue-приложения партнёрского кабинета (паттерн MVVM).
 *
 * Роль:
 * - Model  — данные, переданные из PHP (rootProps) и получаемые через AJAX/REST;
 * - ViewModel — реактивное состояние (Pinia store) и компоненты Vue;
 * - View  — декларативные шаблоны компонентов.
 *
 * Контроллер связывает страницу (PHP) с Vue-компонентами:
 * создаёт, монтирует и демонтирует приложение, подключает Pinia,
 * инициализирует store данными из PHP.
 */
export class PartnersApplication
{
    #application;
    #options;
    #pinia;

    /**
     * @param {string} rootNode CSS-селектор контейнера приложения
     * @param {Object} options  Данные для инициализации (rootProps)
     */
    constructor(rootNode, options = {})
    {
        this.rootNode = document.querySelector(rootNode);
        this.#options = options;
    }

    /**
     * Запускает Vue-приложение дашборда.
     */
    start()
    {
        if (!this.rootNode)
        {
            console.error('[LDO.VueApp] Root node not found');
            return;
        }

        const context = this;

        // Pinia: централизованное состояние (ViewModel)
        this.#pinia = createPinia();

        // Если переданы данные дашборда — монтируем DashboardApp, иначе demo-компонент
        const isDashboard = Boolean(this.#options.dashboard);

        this.#application = BitrixVue.createApp({
            name: 'PartnersApplication',
            components: {
                HelloWorld,
                DashboardApp,
            },
            // В хуке beforeCreate доступ к интеграционным методам идёт через this.$bitrix
            beforeCreate()
            {
                this.$bitrix.Application.set(context);
            },
            template: isDashboard ? '<DashboardApp/>' : '<HelloWorld/>',
        }, {
            // rootProps: входные данные корневого компонента (Model из PHP)
            ...this.#options,
        });

        this.#application.use(this.#pinia);

        // Инициализация store данными из PHP
        const store = useDashboardStore(this.#pinia);
        store.init(this.#options.dashboard || {});

        this.#application.mount(this.rootNode);
    }

    /**
     * Демонтирует приложение и очищает контейнер.
     */
    detach()
    {
        if (this.#application)
        {
            this.#application.unmount();
            this.#application = null;
        }
    }

    /**
     * Доступ к данным инициализации (вызывается из компонентов).
     * @returns {Object}
     */
    getOptions()
    {
        return this.#options;
    }
}
