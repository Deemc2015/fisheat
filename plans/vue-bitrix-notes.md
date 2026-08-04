# Конспект: Vue.js в документации Bitrix Framework (BitrixVue 3)

Источники: официальная документация Bitrix (`pages/advanced/vue.md`, `pages/advanced/localization.md`, `pages/framework/extensions.md`) + проверка установки на проекте riba.

---

## 1. Что такое BitrixVue

`BitrixVue` — расширение библиотеки Vue.js 3 от Bitrix Framework. Полностью совместимо с оригинальным Vue 3 и добавляет интеграцию с ядром Битрикса.

- Доступно с версии модуля **ui 22.100.0**.
- **Vue 2 устарел** — использовать только BitrixVue 3 (`ui.vue3`).
- Ключевые свойства:
  - интеграция с локализациями, событиями, REST/Pull API;
  - единая версия Vue для всех модулей без конфликтов;
  - кастомизация встроенных компонентов без правки ядра (мутабельные компоненты);
  - изоляция — Vue НЕ попадает в `window.Vue` (сторонние приложения могут использовать свою версию).

> **Не подходит** для SSR и серверной компиляции однофайловых компонентов `.vue` (нужна сборка).

### Подсказки в IDE
Файл типов для автокомплита:
`/bitrix/modules/ui/install/js/ui/vue3/ui.vue3.d.ts` (присутствует в установке).

---

## 2. Подключение BitrixVue 3

Два способа:

**A. Через расширения (предпочтительно)** — ES6+, модульная структура, сборка `@bitrix/cli`:

```javascript
import {BitrixVue} from 'ui.vue3';
BitrixVue.createApp({...}).mount('#application');
```

**B. На обычной PHP-странице** (без сборки), через глобальный `BX`:

```php
<?php \Bitrix\Main\UI\Extension::load("ui.vue3"); ?>
<div id="application"></div>
<script>
    BX.Vue3.BitrixVue.createApp({
        template: '<div>Hello World</div>'
    }).mount('#application');
</script>
```

### Создание приложения
- `BitrixVue.createApp(rootComponent, rootProps?)` — создаёт экземпляр приложения.
- `rootProps` (входные данные) доступен с **ui 22.300.0** — удобно передавать данные из PHP.
- Монтирование через `.mount(selector)`.

### Рекомендуемая структура: Контроллер + Компоненты
- Контроллер (класс) создаёт/монтирует/демонтирует приложение и хранит бизнес-логику.
- Компоненты только отображают данные и вызывают методы контроллера через `$Bitrix.Application`.
- В хуке `beforeCreate` доступ к интеграции идёт через **`this.$bitrix`** (с маленькой буквы), в остальных местах — `this.$Bitrix`.

```javascript
// Контроллер
import {BitrixVue} from 'ui.vue3';
export class TaskManager {
    attachTemplate() {
        this.#application = BitrixVue.createApp({
            components: {TaskManagerComponent},
            beforeCreate() { this.$bitrix.Application.set(this); },
            template: '<TaskManagerComponent/>'
        });
        this.#application.mount(this.rootNode);
    }
}

// Компонент
methods: {
    close() { this.$Bitrix.Application.get().detachTemplate(); }
}
```

---

## 3. Компоненты

### Два типа
1. **Классические** — простые объекты Vue без спец. обработки.
2. **Мутабельные** — для кастомизации стандартных компонентов продукта без изменения исходника:
   ```javascript
   export const MyWidget = BitrixVue.mutableComponent('mymodule-widget', { ... });
   ```
   Имя формата `модуль-компонент` (kebab-case).

### Порядок свойств в компоненте
Соблюдать единый порядок Vue style-guide: `props` → `data` → `computed` → методы → хуки.

### Пример классического компонента (эммиты, глобальные события, локализация)
```javascript
export const MyComponent = {
    emits: ['buttonClicked'],
    props: { title: String },
    data() { return { count: 0 }; },
    created() {
        this.$Bitrix.eventEmitter.subscribe('mymodule:mycomponent:action', this.handleGlobalAction);
    },
    beforeUnmount() {
        this.$Bitrix.eventEmitter.unsubscribe('mymodule:mycomponent:action', this.handleGlobalAction);
    },
    methods: {
        onClick() { this.count++; this.$emit('buttonClicked', this.count); }
    },
    template: `<div>{{ $Bitrix.Loc.getMessage('MYMODULE_HELLO') }} ...</div>`
};
```
> ⚠️ Важно отписываться от глобальных событий в `beforeUnmount`.

### Мутация компонентов — `BitrixVue.mutateComponent(source, mutations)`
- Применяется до первого рендера, возвращает `true/false`.
- **Плейсхолдер** `#PARENT_TEMPLATE#` — расширение исходного шаблона, а не замена.
- **Префикс `parent`** — доступ к оригинальным методам/свойствам (`parentSendText`), в `watch` — префикс `parentWatch`.
- **Префикс `replace`** — полная замена свойств (`replaceMixins`, `replaceInject`, `replaceEmits`).

### Клонирование — `BitrixVue.cloneComponent(source, mutations)`
- Создаёт новый независимый компонент на основе существующего.
- Клон всегда строится от оригинального компонента (даже если к оригиналу уже применяли мутации).
- Для классических компонентов обратная совместимость не гарантируется.

### Отложенная загрузка — `BitrixVue.defineAsyncComponent(extension, exportName, options?)`
- Опции: `loadingComponent`, `errorComponent`, `delay` (по умолчанию 200 мс), `timeout`, `delayLoadExtension`.

### Директивы
- Локальные директивы — обычные объекты с хуками (`mounted: el => el.focus()`).
- Регистрация: `directives: { focus }` → в шаблоне `v-focus`.
- Оформляются как расширения, размещаются в папке `directives`, обязателен JSDoc-комментарий с `@example`.
- ⚠️ Не использовать автофокус для элементов с анимацией.

---

## 4. Интеграция с Bitrix Framework — `$Bitrix`

Единая точка доступа. Основные классы:

| Класс | Назначение |
|---|---|
| `$Bitrix.Loc` | Языковые фразы |
| `$Bitrix.eventEmitter` | События внутри приложения |
| `$Bitrix.Application` | Связь с контроллером (`set`/`get`) |
| `$Bitrix.Data` | Общие данные приложения (`set`/`get`) |
| `$Bitrix.RestClient` | REST-клиент (для внешних виджетов) |
| `$Bitrix.PullClient` | Pull-клиент (для внешних виджетов) |

### Локализация `$Bitrix.Loc`
```javascript
// В шаблоне, с реактивной подстановкой
template: `<div>{{ $Bitrix.Loc.getMessage('USER_COUNT', {'#COUNT#': userCount}) }}</div>`

// Все фразы
this.$Bitrix.Loc.getMessages();

// Ручная установка (внешние виджеты)
beforeCreate() { this.$bitrix.Loc.setMessage('DEMO_COUNTER', 'Счетчик: #COUNTER#'); }

// Оптимизация для сотен фраз
computed: { localize() { return BitrixVue.getFilteredPhrases('MYCOMP_'); } }
```
Фразы в JS доступны также глобально через `BX.message()`.

### События — три уровня
1. **Компонентный**: `$emit` / `@event` — связь родитель ↔ потомок.
2. **Приложение**: `this.$Bitrix.eventEmitter.emit/subscribe('module:component:action', ...)` — между компонентами одного приложения. Формат имени: `модуль:компонент:действие` (напр. `ui:button:click`).
3. **Сайт**: глобальный `EventEmitter` из `main.core.events` — между разными приложениями на странице.

### REST / Pull
- Для внешних виджетов (формы, чат вне Битрикса): `$Bitrix.RestClient` / `$Bitrix.PullClient` с методами `.get()`, `.set()`, `.isCustom()`.
- Во всех остальных случаях — обычные импорты `rest.client` / `pull.client`.
- Смена клиента: события `BitrixVue.events.restClientChange` / `pullClientChange`.

---

## 5. Роутинг, хранилище, IndexedDB

### Vue Router — `ui.vue3.router`
```javascript
import {createRouter, createWebHashHistory} from 'ui.vue3.router';
const router = createRouter({ history: createWebHashHistory(), routes });
application.use(router);
application.mount('#application');
```
Без транспиляции: глобальный `BX.Vue3.VueRouter`.

### Хранилища
- **Pinia** (рекомендуется) — расширение `ui.vue3.pinia`: `createPinia`, `defineStore`, `mapState`, `mapActions`, `app.use(pinia)`.
- **Vuex** — расширение `ui.vue3.vuex`: `createStore`, `this.$store`, модули с `namespaced: true`.

### Dexie (IndexedDB) — `ui.dexie` (с ui 22.500.0)
- Реактивная работа: `liveQuery` из `ui.dexie` + `useObservable` из `ui.vue3`.
- Без сборки: `BX.Dexie3`, `BX.Vue3`.

---

## 6. Teleport
`<teleport to="#modal-container" :disabled="!showModal">` — рендер части шаблона в другом месте DOM (модалки, тултипы, уведомления). Логическая связь с компонентом сохраняется.

---

## 7. Внешние библиотеки
Подключаются через сборку в Bitrix JS Extension (ESM → удаление лишних импортов → зависимости от `ui.vue3` → экспорт). Пример: `/bitrix/modules/ui/install/js/ui/vue3/router/`.

---

## 8. Отладка
В `/bitrix/php_interface/init.php` (для проекта — в `local/php_interface/init.php`):
```php
define('VUEJS_DEBUG', true);                 // режим разработчика
define('VUEJS_LOCALIZATION_DEBUG', true);    // коды фраз вместо текста
```
Плюс расширение **Vue.js Devtools** в браузере.

---

## 9. Практика для проекта riba

- **Vue в проекте сейчас НЕ используется** (поиск по `local/` — 0 совпадений `ui.vue3` / `BX.Vue3` / `BitrixVue`).
- В установке доступны расширения: `ui.vue3` (ядро + `bitrixvue`, `components`, `directives`, `pinia`, `router`, `vue`, `vuex`).
- Готовые компоненты `ui.vue3.components`: `audioplayer`, `button`, `hint`, `popup`, `reactions`, `rich-loc`, `rich-menu`, `smiles`, `socialvideo`, `switcher`.

### Как внедрять (чек-лист)
1. Создать своё расширение: `/local/js/<module>/<extension>/` со структурой `src/`, `dist/`, `bundle.config.js`, `config.php` (или создать через `@bitrix/cli`: `bitrix create`).
2. Подключить в PHP: `\Bitrix\Main\UI\Extension::load("local.<extension>");`.
3. Использовать паттерн **Контроллер + Компоненты**.
4. Локализацию выносить в `lang/ru`, фразы — через `$Bitrix.Loc.getMessage`.
5. Для передачи данных с сервера — `rootProps` (ui 22.300.0+) или `config.php` → `settings`/`lang_additional`.
6. Тяжёлые компоненты — через `defineAsyncComponent`.
7. Включить `VUEJS_DEBUG` в dev-окружении.

---

## Полезные ссылки из документации
- Переход с BitrixVue 2 на 3: dev.1c-bitrix.ru (курс 176, глава 024460)
- BitrixVue 2: dev.1c-bitrix.ru (курс 176, глава 024504)
