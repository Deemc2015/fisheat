/* eslint-disable */
this.BX = this.BX || {};
this.BX.LDO = this.BX.LDO || {};
(function (exports,ui_vue3,ui_vue3_pinia) {
    'use strict';

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
    var HelloWorld = {
      props: {
        title: {
          type: String,
          "default": ''
        }
      },
      data: function data() {
        return {
          count: 0
        };
      },
      computed: {
        greeting: function greeting() {
          return this.$Bitrix.Loc.getMessage('LDO_VUEAPP_HELLO') || 'Hello from BitrixVue 3!';
        }
      },
      methods: {
        getOptions: function getOptions() {
          // Доступ к контексту контроллера (Model из PHP)
          return this.$Bitrix.Application.get().getOptions();
        }
      },
      // language=Vue
      template: "\n        <div class=\"vue-app-demo\">\n            <h3>{{ title }}</h3>\n            <p>{{ greeting }}</p>\n            <button type=\"button\" @click=\"count++\">\n                {{ $Bitrix.Loc.getMessage('LDO_VUEAPP_CLICKS', {'#COUNT#': count}) }}\n            </button>\n        </div>\n    "
    };

    /**
     * Pinia store дашборда партнёрского кабинета.
     *
     * MVVM: это часть ViewModel — централизованное реактивное состояние,
     * которое синхронизирует Model (данные из PHP/AJAX) и View (компоненты).
     *
     * Инициализация данных происходит из rootProps при создании приложения
     * (метод init), а обновления — через setData (например, при смене периода).
     */
    var useDashboardStore = ui_vue3_pinia.defineStore('dashboard', {
      state: function state() {
        return {
          period: 'week',
          // Активный период: day | week | month | year
          loading: false,
          // Флаг загрузки данных
          cards: [],
          // Стат-карточки: {id, label, value, change, changeType, currency}
          chart: {
            // Данные графика
            labels: [],
            // Подписи оси X
            series: [] // Серии: {name, color, values: []}
          },

          table: {
            // Данные таблицы
            columns: [],
            // Колонки: {key, title, align}
            rows: [] // Строки: {id, ...cells}
          },

          lastUpdated: null // Время последнего обновления
        };
      },

      getters: {
        /** Общий тренд по первой серии графика (в процентах) */
        chartTrend: function chartTrend(state) {
          var _state$chart$series$, _state$chart$series$2;
          var values = (_state$chart$series$ = (_state$chart$series$2 = state.chart.series[0]) === null || _state$chart$series$2 === void 0 ? void 0 : _state$chart$series$2.values) !== null && _state$chart$series$ !== void 0 ? _state$chart$series$ : [];
          if (values.length < 2) {
            return 0;
          }
          var first = values[0];
          var last = values[values.length - 1];
          if (first === 0) {
            return 0;
          }
          return Math.round((last - first) / first * 100);
        }
      },
      actions: {
        /**
         * Инициализация store данными из rootProps.
         * @param {Object} data
         */
        init: function init() {
          var data = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
          this.period = data.period || 'week';
          this.cards = data.cards || [];
          this.chart = data.chart || {
            labels: [],
            series: []
          };
          this.table = data.table || {
            columns: [],
            rows: []
          };
          this.lastUpdated = new Date().toISOString();
        },
        /**
         * Обновление данных (при смене периода, после AJAX и т.п.).
         * @param {Object} data
         */
        setData: function setData() {
          var data = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
          if (data.cards) {
            this.cards = data.cards;
          }
          if (data.chart) {
            this.chart = data.chart;
          }
          if (data.table) {
            this.table = data.table;
          }
          this.lastUpdated = new Date().toISOString();
        },
        /**
         * Установка периода.
         * @param {string} period
         */
        setPeriod: function setPeriod(period) {
          if (this.period === period) {
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
        setLoading: function setLoading(value) {
          this.loading = value;
        }
      }
    });

    /**
     * StatCard — карточка показателя (доход, заказы, средний чек и т.п.).
     *
     * MVVM: компонент получает данные как props (Model → ViewModel),
     * шаблон декларативно отображает их (View).
     *
     * @example <StatCard :item="card" />
     */
    var StatCard = {
      props: {
        item: {
          type: Object,
          required: true
        }
      },
      computed: {
        changeClass: function changeClass() {
          var type = this.item.changeType;
          return {
            'p-stat-card__change--up': type === 'up',
            'p-stat-card__change--down': type === 'down'
          };
        },
        changeArrow: function changeArrow() {
          return this.item.changeType === 'down' ? '↓' : '↑';
        },
        formattedValue: function formattedValue() {
          var value = this.item.value;
          var currency = this.item.currency ? " ".concat(this.item.currency) : '';
          return "".concat(value).concat(currency);
        }
      },
      // language=Vue
      template: "\n        <div class=\"p-stat-card\">\n            <div class=\"p-stat-card__label\">{{ item.label }}</div>\n            <div class=\"p-stat-card__value\">{{ formattedValue }}</div>\n            <div v-if=\"item.change\" class=\"p-stat-card__change\" :class=\"changeClass\">\n                {{ changeArrow }} {{ item.change }}\n            </div>\n        </div>\n    "
    };

    /**
     * PeriodFilter — переключатель периода (день/неделя/месяц/год).
     *
     * MVVM: выбор пользователя (View) обновляет store (ViewModel),
     * компоненты автоматически перерисовываются.
     *
     * @example <PeriodFilter />
     */
    var PeriodFilter = {
      props: {
        periods: {
          type: Array,
          "default": function _default() {
            return ['day', 'week', 'month', 'year'];
          }
        }
      },
      computed: {
        // NB: $Bitrix — computed-свойство из глобального mixin BitrixVue.
        // В data() оно недоступно (data выполняется раньше computed), поэтому
        // локализацию выносим именно в computed.
        labels: function labels() {
          return {
            day: this.$Bitrix.Loc.getMessage('LDO_VUEAPP_PERIOD_DAY'),
            week: this.$Bitrix.Loc.getMessage('LDO_VUEAPP_PERIOD_WEEK'),
            month: this.$Bitrix.Loc.getMessage('LDO_VUEAPP_PERIOD_MONTH'),
            year: this.$Bitrix.Loc.getMessage('LDO_VUEAPP_PERIOD_YEAR')
          };
        }
      },
      methods: {
        select: function select(period) {
          this.$store.dashboard.setPeriod(period);
        }
      },
      // language=Vue
      template: "\n        <div class=\"p-period-filter\">\n            <button\n                v-for=\"period in periods\"\n                :key=\"period\"\n                type=\"button\"\n                class=\"p-period-filter__btn\"\n                :class=\"{ 'is-active': $store.dashboard.period === period }\"\n                @click=\"select(period)\"\n            >\n                {{ labels[period] || period }}\n            </button>\n        </div>\n    "
    };

    function _createForOfIteratorHelper(o, allowArrayLike) { var it = typeof Symbol !== "undefined" && o[Symbol.iterator] || o["@@iterator"]; if (!it) { if (Array.isArray(o) || (it = _unsupportedIterableToArray(o)) || allowArrayLike && o && typeof o.length === "number") { if (it) o = it; var i = 0; var F = function F() {}; return { s: F, n: function n() { if (i >= o.length) return { done: true }; return { done: false, value: o[i++] }; }, e: function e(_e) { throw _e; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var normalCompletion = true, didErr = false, err; return { s: function s() { it = it.call(o); }, n: function n() { var step = it.next(); normalCompletion = step.done; return step; }, e: function e(_e2) { didErr = true; err = _e2; }, f: function f() { try { if (!normalCompletion && it["return"] != null) it["return"](); } finally { if (didErr) throw err; } } }; }
    function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
    function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) arr2[i] = arr[i]; return arr2; }
    /**
     * ChartLine — линейный график на SVG (без внешних библиотек).
     *
     * MVVM: данные серий приходят из store (Model → ViewModel), компонент
     * декларативно вычисляет координаты и рендерит SVG (View).
     *
     * @example <ChartLine :data="store.chart" :height="260" />
     */
    var ChartLine = {
      props: {
        data: {
          type: Object,
          required: true
        },
        height: {
          type: Number,
          "default": 260
        }
      },
      computed: {
        labels: function labels() {
          return this.data.labels || [];
        },
        series: function series() {
          return this.data.series || [];
        },
        width: function width() {
          return 600;
        },
        padding: function padding() {
          return {
            top: 16,
            right: 16,
            bottom: 28,
            left: 48
          };
        },
        innerWidth: function innerWidth() {
          return this.width - this.padding.left - this.padding.right;
        },
        innerHeight: function innerHeight() {
          return this.height - this.padding.top - this.padding.bottom;
        },
        maxValue: function maxValue() {
          var max = 0;
          var _iterator = _createForOfIteratorHelper(this.series),
            _step;
          try {
            for (_iterator.s(); !(_step = _iterator.n()).done;) {
              var s = _step.value;
              var _iterator2 = _createForOfIteratorHelper(s.values),
                _step2;
              try {
                for (_iterator2.s(); !(_step2 = _iterator2.n()).done;) {
                  var v = _step2.value;
                  if (v > max) {
                    max = v;
                  }
                }
              } catch (err) {
                _iterator2.e(err);
              } finally {
                _iterator2.f();
              }
            }
          } catch (err) {
            _iterator.e(err);
          } finally {
            _iterator.f();
          }
          return max || 1;
        },
        /** Горизонтальные линии сетки с подписями значений */gridLines: function gridLines() {
          var lines = [];
          var steps = 4;
          for (var i = 0; i <= steps; i++) {
            var value = this.maxValue / steps * i;
            var y = this.padding.top + this.innerHeight - this.innerHeight * (i / steps);
            lines.push({
              value: Math.round(value),
              y: y
            });
          }
          return lines;
        },
        /** Пути для серий */seriesPaths: function seriesPaths() {
          var _this = this;
          return this.series.map(function (s) {
            var points = s.values.map(function (v, i) {
              var x = _this.padding.left + _this.innerWidth * (i / Math.max(_this.labels.length - 1, 1));
              var y = _this.padding.top + _this.innerHeight - _this.innerHeight * (v / _this.maxValue);
              return {
                x: x,
                y: y
              };
            });
            var d = points.map(function (p, i) {
              return "".concat(i === 0 ? 'M' : 'L', " ").concat(p.x.toFixed(1), " ").concat(p.y.toFixed(1));
            }).join(' ');
            return {
              name: s.name,
              color: s.color || '#2ecc71',
              d: d,
              points: points
            };
          });
        }
      },
      // language=Vue
      template: "\n        <svg\n            class=\"p-chart-line\"\n            :viewBox=\"'0 0 ' + width + ' ' + height\"\n            preserveAspectRatio=\"xMidYMid meet\"\n            role=\"img\"\n        >\n            <g>\n                <line\n                    v-for=\"line in gridLines\"\n                    :key=\"'grid-' + line.y\"\n                    :x1=\"padding.left\"\n                    :x2=\"width - padding.right\"\n                    :y1=\"line.y\"\n                    :y2=\"line.y\"\n                    stroke=\"rgba(255,255,255,.08)\"\n                    stroke-width=\"1\"\n                />\n                <text\n                    v-for=\"line in gridLines\"\n                    :key=\"'lbl-' + line.y\"\n                    :x=\"padding.left - 8\"\n                    :y=\"line.y + 4\"\n                    text-anchor=\"end\"\n                    font-size=\"11\"\n                    fill=\"var(--color-muted, #8b93a7)\"\n                >{{ line.value }}</text>\n            </g>\n\n            <g v-for=\"path in seriesPaths\" :key=\"path.name\">\n                <path\n                    :d=\"path.d\"\n                    fill=\"none\"\n                    :stroke=\"path.color\"\n                    stroke-width=\"2.5\"\n                    stroke-linejoin=\"round\"\n                    stroke-linecap=\"round\"\n                />\n                <circle\n                    v-for=\"p in path.points\"\n                    :key=\"path.name + '-' + p.x\"\n                    :cx=\"p.x\"\n                    :cy=\"p.y\"\n                    r=\"3.5\"\n                    :fill=\"path.color\"\n                />\n            </g>\n\n            <g>\n                <text\n                    v-for=\"(label, i) in labels\"\n                    :key=\"'x-' + i\"\n                    :x=\"padding.left + (innerWidth * (i / Math.max(labels.length - 1, 1)))\"\n                    :y=\"height - 8\"\n                    text-anchor=\"middle\"\n                    font-size=\"11\"\n                    fill=\"var(--color-muted, #8b93a7)\"\n                >{{ label }}</text>\n            </g>\n        </svg>\n    "
    };

    /**
     * DataTable — таблица статистики с сортировкой по колонкам.
     *
     * MVVM: данные (строки/колонки) из store — Model/ViewModel,
     * шаблон декларативно отображает и сортирует (View).
     *
     * @example <DataTable :columns="store.table.columns" :rows="store.table.rows" />
     */
    var DataTable = {
      props: {
        columns: {
          type: Array,
          "default": function _default() {
            return [];
          }
        },
        rows: {
          type: Array,
          "default": function _default() {
            return [];
          }
        }
      },
      data: function data() {
        return {
          sortKey: '',
          sortDir: 'asc' // asc | desc
        };
      },

      computed: {
        sortedRows: function sortedRows() {
          var _this = this;
          if (!this.sortKey) {
            return this.rows;
          }
          var dir = this.sortDir === 'asc' ? 1 : -1;
          return babelHelpers.toConsumableArray(this.rows).sort(function (a, b) {
            var av = a[_this.sortKey];
            var bv = b[_this.sortKey];
            if (typeof av === 'number' && typeof bv === 'number') {
              return (av - bv) * dir;
            }
            return String(av !== null && av !== void 0 ? av : '').localeCompare(String(bv !== null && bv !== void 0 ? bv : ''), 'ru') * dir;
          });
        }
      },
      methods: {
        toggleSort: function toggleSort(key) {
          if (this.sortKey === key) {
            this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
          } else {
            this.sortKey = key;
            this.sortDir = 'asc';
          }
        },
        sortIcon: function sortIcon(key) {
          if (this.sortKey !== key) {
            return '↕';
          }
          return this.sortDir === 'asc' ? '↑' : '↓';
        }
      },
      // language=Vue
      template: "\n        <div class=\"p-table-wrap\">\n            <table class=\"p-table\">\n                <thead>\n                    <tr>\n                        <th\n                            v-for=\"col in columns\"\n                            :key=\"col.key\"\n                            :class=\"{ 'is-sortable': col.sortable }\"\n                            @click=\"col.sortable && toggleSort(col.key)\"\n                        >\n                            {{ col.title }}\n                            <span v-if=\"col.sortable\" class=\"p-table__sort\">{{ sortIcon(col.key) }}</span>\n                        </th>\n                    </tr>\n                </thead>\n                <tbody>\n                    <tr v-for=\"row in sortedRows\" :key=\"row.id\">\n                        <td\n                            v-for=\"col in columns\"\n                            :key=\"col.key\"\n                            :class=\"'p-table__cell--' + (col.align || 'left')\"\n                        >{{ row[col.key] }}</td>\n                    </tr>\n                    <tr v-if=\"sortedRows.length === 0\">\n                        <td :colspan=\"columns.length\" class=\"p-table__empty\">\n                            {{ $Bitrix.Loc.getMessage('LDO_VUEAPP_TABLE_EMPTY') }}\n                        </td>\n                    </tr>\n                </tbody>\n            </table>\n        </div>\n    "
    };

    /**
     * DashboardApp — корневой компонент дашборда партнёрского кабинета.
     *
     * MVVM: ViewModel (Pinia store dashboard) связывает Model (данные из PHP/AJAX)
     * с View (декларативный шаблон, состоящий из дочерних компонентов).
     *
     * @example <DashboardApp />
     */
    var DashboardApp = {
      components: {
        StatCard: StatCard,
        PeriodFilter: PeriodFilter,
        ChartLine: ChartLine,
        DataTable: DataTable
      },
      setup: function setup() {
        var store = useDashboardStore();
        return {
          store: store
        };
      },
      // language=Vue
      template: "\n        <div class=\"p-dashboard\">\n            <!-- \u0421\u0442\u0430\u0442-\u043A\u0430\u0440\u0442\u043E\u0447\u043A\u0438 -->\n            <div class=\"p-stats\">\n                <StatCard\n                    v-for=\"card in store.cards\"\n                    :key=\"card.id\"\n                    :item=\"card\"\n                />\n            </div>\n\n            <!-- \u0424\u0438\u043B\u044C\u0442\u0440 \u043F\u0435\u0440\u0438\u043E\u0434\u0430 + \u0433\u0440\u0430\u0444\u0438\u043A -->\n            <div class=\"p-dashboard__panel\">\n                <div class=\"p-dashboard__panel-head\">\n                    <div class=\"p-dashboard__panel-title\">\n                        {{ $Bitrix.Loc.getMessage('LDO_VUEAPP_CHART_TITLE') }}\n                    </div>\n                    <PeriodFilter />\n                </div>\n                <ChartLine v-if=\"store.chart.series.length\" :data=\"store.chart\" :height=\"260\" />\n            </div>\n\n            <!-- \u0422\u0430\u0431\u043B\u0438\u0446\u0430 -->\n            <div class=\"p-dashboard__panel\">\n                <div class=\"p-dashboard__panel-head\">\n                    <div class=\"p-dashboard__panel-title\">\n                        {{ $Bitrix.Loc.getMessage('LDO_VUEAPP_TABLE_TITLE') }}\n                    </div>\n                </div>\n                <DataTable :columns=\"store.table.columns\" :rows=\"store.table.rows\" />\n            </div>\n        </div>\n    "
    };

    function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }
    function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys(Object(source), !0).forEach(function (key) { babelHelpers.defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }
    function _classPrivateFieldInitSpec(obj, privateMap, value) { _checkPrivateRedeclaration(obj, privateMap); privateMap.set(obj, value); }
    function _checkPrivateRedeclaration(obj, privateCollection) { if (privateCollection.has(obj)) { throw new TypeError("Cannot initialize the same private elements twice on an object"); } }

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
    var _application = /*#__PURE__*/new WeakMap();
    var _options = /*#__PURE__*/new WeakMap();
    var _pinia = /*#__PURE__*/new WeakMap();
    var PartnersApplication = /*#__PURE__*/function () {
      /**
       * @param {string} rootNode CSS-селектор контейнера приложения
       * @param {Object} options  Данные для инициализации (rootProps)
       */
      function PartnersApplication(rootNode) {
        var options = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
        babelHelpers.classCallCheck(this, PartnersApplication);
        _classPrivateFieldInitSpec(this, _application, {
          writable: true,
          value: void 0
        });
        _classPrivateFieldInitSpec(this, _options, {
          writable: true,
          value: void 0
        });
        _classPrivateFieldInitSpec(this, _pinia, {
          writable: true,
          value: void 0
        });
        this.rootNode = document.querySelector(rootNode);
        babelHelpers.classPrivateFieldSet(this, _options, options);
      }

      /**
       * Запускает Vue-приложение дашборда.
       */
      babelHelpers.createClass(PartnersApplication, [{
        key: "start",
        value: function start() {
          if (!this.rootNode) {
            console.error('[LDO.VueApp] Root node not found');
            return;
          }
          var context = this;

          // Pinia: централизованное состояние (ViewModel)
          babelHelpers.classPrivateFieldSet(this, _pinia, ui_vue3_pinia.createPinia());

          // Если переданы данные дашборда — монтируем DashboardApp, иначе demo-компонент
          var isDashboard = Boolean(babelHelpers.classPrivateFieldGet(this, _options).dashboard);
          babelHelpers.classPrivateFieldSet(this, _application, ui_vue3.BitrixVue.createApp({
            name: 'PartnersApplication',
            components: {
              HelloWorld: HelloWorld,
              DashboardApp: DashboardApp
            },
            // В хуке beforeCreate доступ к интеграционным методам идёт через this.$bitrix
            beforeCreate: function beforeCreate() {
              this.$bitrix.Application.set(context);
            },
            template: isDashboard ? '<DashboardApp/>' : '<HelloWorld/>'
          }, _objectSpread({}, babelHelpers.classPrivateFieldGet(this, _options))));
          babelHelpers.classPrivateFieldGet(this, _application).use(babelHelpers.classPrivateFieldGet(this, _pinia));

          // Инициализация store данными из PHP
          var store = useDashboardStore(babelHelpers.classPrivateFieldGet(this, _pinia));
          store.init(babelHelpers.classPrivateFieldGet(this, _options).dashboard || {});
          babelHelpers.classPrivateFieldGet(this, _application).mount(this.rootNode);
        }
        /**
         * Демонтирует приложение и очищает контейнер.
         */
      }, {
        key: "detach",
        value: function detach() {
          if (babelHelpers.classPrivateFieldGet(this, _application)) {
            babelHelpers.classPrivateFieldGet(this, _application).unmount();
            babelHelpers.classPrivateFieldSet(this, _application, null);
          }
        }
        /**
         * Доступ к данным инициализации (вызывается из компонентов).
         * @returns {Object}
         */
      }, {
        key: "getOptions",
        value: function getOptions() {
          return babelHelpers.classPrivateFieldGet(this, _options);
        }
      }]);
      return PartnersApplication;
    }();

    exports.PartnersApplication = PartnersApplication;
    exports.HelloWorld = HelloWorld;
    exports.DashboardApp = DashboardApp;
    exports.useDashboardStore = useDashboardStore;

}((this.BX.LDO.VueApp = this.BX.LDO.VueApp || {}),BX.Vue3,BX.Vue3.Pinia));
//# sourceMappingURL=vue-app.bundle.js.map
