<?php
/**
 * Продающий лендинг модуля фильтрации трафика keyup.cleartrafic.
 *
 * Все тексты, цены и контакты берутся из config.php — правьте их там.
 */

$config = require __DIR__ . '/config.php';

$product  = $config['product'];
$contacts = $config['contacts'];
$form     = $config['form'];
$pricing  = $config['pricing'];
$services = $config['services'];

/** Безопасный вывод значения */
$e = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$pageTitle = $product['name'] . ' — ' . $product['tagline'];
$pageDescription = 'Модуль фильтрации трафика для 1С-Битрикс: чёрный и серый списки IP, маски подсетей, '
    . 'правила User-Agent и рефереров, Яндекс SmartCaptcha, журнал визитов и панель управления. '
    . 'Отсекаем ботов и парсеров капчей, а не блокировкой.';

$navItems = [
    '#problems'   => 'Проблемы',
    '#features'   => 'Возможности',
    '#tech'       => 'Технологии',
    '#pricing'    => 'Тарифы',
    '#faq'        => 'Вопросы',
];

$problems = [
    [
        'title' => 'Парсеры копируют ваш контент',
        'text'  => 'Конкуренты и агрегаторы выкачивают цены, ассортимент, описания и фото целиком — без вашего согласия и без пользы для бизнеса.',
    ],
    [
        'title' => 'Ботовый трафик съедает сервер',
        'text'  => 'Тысячи автоматических запросов в сутки нагружают процессор, базу и канал, а карточки товаров и корзина начинают открываться медленнее для живых клиентов.',
    ],
    [
        'title' => 'Спам в формах и заявках',
        'text'  => 'Менеджеры тратят время на мусорные обращения, реальные лиды теряются в потоке, а база клиентов засоряется фейковыми адресами.',
    ],
    [
        'title' => 'Накрутки и клик-фрод',
        'text'  => 'Скрипты имитируют поведение покупателей, кликают по рекламе и искажают поведенческие метрики, из-за чего страдает рекламный бюджет.',
    ],
    [
        'title' => 'Грязная аналитика',
        'text'  => 'Метрика, Google Analytics и рекламные кабинеты считают ботов как посетителей, поэтому конверсия, отказы и стоимость лида показываются неверно.',
    ],
    [
        'title' => 'Блокировка «в лоб» отпугивает клиентов',
        'text'  => 'Стоп-лист по IP или полный запрет — грубые инструменты. Они отсекают реальных покупателей и попадают под риски по поисковым роботам.',
    ],
];

$steps = [
    [
        'num'   => '01',
        'title' => 'Опишите правила',
        'text'  => 'Добавьте IP в чёрный или серый список, задайте подсети (CIDR), фрагменты User-Agent и доверенные рефереры. Списки можно загрузить из TXT-файла.',
    ],
    [
        'num'   => '02',
        'title' => 'Модуль проверит посетителя',
        'text'  => 'На каждом хите запрос сравнивается с правилами: до 5000 строк в списке, приоритеты описаны и предсказуемы, поисковые роботы распознаются отдельно.',
    ],
    [
        'num'   => '03',
        'title' => 'Срабатывает капча или блокировка',
        'text'  => 'Серый список, маска подсети, подозрительный User-Agent и переход не с доверенного источника получают Яндекс SmartCaptcha. Чёрный список блокируется сразу.',
    ],
    [
        'num'   => '04',
        'title' => 'Смотрите отчётность',
        'text'  => 'Каждый визит фиксируется в журнале: IP, страница входа, реферер, сработавшее правило, число обращений и факт прохождения капчи. Есть фильтры по датам и IP.',
    ],
];

$features = [
    [
        'title' => 'Чёрный список IP',
        'text'  => 'Полный запрет доступа к сайту с конкретных адресов. Посетитель переадресуется на вежливую страницу блокировки с формой обращения.',
        'icon'  => 'shield',
    ],
    [
        'title' => 'Серый список и SmartCaptcha',
        'text'  => 'Вместо жёсткой блокировки подозрительный адрес получает проверку Яндекс SmartCaptcha. Реальные покупатели проходят её и продолжают покупки.',
        'icon'  => 'check',
    ],
    [
        'title' => 'Маски подсетей (CIDR)',
        'text'  => 'Одним правилом закрывается целая сеть — облачные хостинги, дата-центры и подсети, откуда идёт основной мусорный трафик.',
        'icon'  => 'network',
    ],
    [
        'title' => 'Правила User-Agent',
        'text'  => 'Отсекаются SEO-краулеры и софт для массового сбора данных: AhrefsBot, SemrushBot, MJ12bot и любые свои фрагменты строки.',
        'icon'  => 'bot',
    ],
    [
        'title' => 'Белый список рефереров',
        'text'  => 'Переходы из соцсетей, с партнёрских сайтов и рекламных систем остаются доверенными, а мусорные источники трафика — под проверкой.',
        'icon'  => 'link',
    ],
    [
        'title' => 'Защита поисковых роботов',
        'text'  => 'Краулеры Google, Яндекса, Bing, Baidu и других систем проходят всегда: капча им не показывается, позиции в поиске не страдают.',
        'icon'  => 'search',
    ],
    [
        'title' => 'Журнал визитов',
        'text'  => 'IP, страница входа, реферер, сработавшее правило, количество визитов, отметка о прохождении капчи и сообщения посетителей из формы разблокировки.',
        'icon'  => 'list',
    ],
    [
        'title' => 'Импорт списков из файла',
        'text'  => 'Загрузите TXT со списком IP, масок или фрагментов User-Agent до 5000 строк за раз: комментарии и дубликаты отбрасываются автоматически.',
        'icon'  => 'upload',
    ],
    [
        'title' => 'Оповещения на e-mail',
        'text'  => 'Укажите адреса, и модуль сам сообщит о событиях фильтрации: попытках доступа из чёрного списка и обращениях посетителей.',
        'icon'  => 'mail',
    ],
];

$tech = [
    [
        'title' => 'Не требует правок шаблона',
        'text'  => 'Окно капчи и коды аналитики встраиваются в готовый HTML через событие буфера вывода, поэтому модуль работает с любым шаблоном и не ломает вёрстку.',
    ],
    [
        'title' => 'Ноль запросов к базе на хите',
        'text'  => 'Все правила читаются одним запросом и кэшируются на час под общим тегом модуля, а проверки сравнения выполняются в памяти.',
    ],
    [
        'title' => 'Композитный кэш не страдает',
        'text'  => 'Композит отключается только для того запроса, которому действительно нужна капча. Для остальных посетителей страница по-прежнему отдаётся из кэша.',
    ],
    [
        'title' => 'Подписанная cookie прохождения',
        'text'  => 'Факт прохождения капчи хранится в HTTP-only cookie с подписью HMAC-SHA256 и привязкой к IP: подделать её и обойти проверку нельзя.',
    ],
    [
        'title' => 'Современный код Bitrix D7',
        'text'  => 'ORM-сущности, миграции таблиц, индексы по IP, дате и типу правила, настройки в COption, отдельный AJAX-контроллер, классы без глобальных зависимостей.',
    ],
    [
        'title' => 'Свои права доступа',
        'text'  => 'Для панели создаётся отдельная группа «Администратор модуля фильтрации»: маркетолог или подрядчик видит статистику и правила, не получая доступ в админку.',
    ],
    [
        'title' => 'Контроль хранения данных',
        'text'  => 'Срок хранения журнала настраивается, включая режим «хранить бессрочно». Данные удаляются вместе с модулем, их можно сохранить при удалении.',
    ],
    [
        'title' => 'Аналитика без ботов',
        'text'  => 'Коды счётчиков подключаются внутри модуля и показываются только тем, кто прошёл проверку, поэтому в статистику попадают настоящие посетители.',
    ],
];

$comparison = [
    'rows' => [
        ['Проверка каждого посетителя', 'нет', 'по списку IP', 'IP, подсети, User-Agent, реферер'],
        ['Капча вместо блокировки', 'нет', 'нет', 'Яндекс SmartCaptcha'],
        ['Белый список поисковых роботов', '—', 'вручную', 'встроен, 20+ систем'],
        ['Журнал и отчётность', 'нет', 'логи хостинга', 'панель с фильтрами'],
        ['Импорт стоп-листов из файла', 'нет', 'вручную', 'до 5000 строк за раз'],
        ['Оповещения администратора', 'нет', 'нет', 'список адресов в модуле'],
        ['Чистая аналитика без ботов', 'нет', 'нет', 'коды счётчиков в модуле'],
        ['Нагрузка на сайт', '—', 'правка конфигов', '0 запросов к БД на хите'],
    ],
];

$audience = [
    [
        'title' => 'Интернет-магазины',
        'text'  => 'Защита цен, остатков и описаний от парсинга конкурентами и агрегаторами, разгрузка сервера в часы пик и чистая статистика продаж.',
    ],
    [
        'title' => 'Корпоративные сайты и каталоги',
        'text'  => 'Спокойная работа сайта при массовых сканированиях, отсутствие спама в формах и защита от перегрузки на слабом хостинге.',
    ],
    [
        'title' => 'Digital-агентства',
        'text'  => 'Готовый инструмент для всех клиентских проектов: отсекаем мусорный трафик, который портит отчёты по рекламе и влияет на KPI.',
    ],
    [
        'title' => 'SEO и PPC-специалисты',
        'text'  => 'Краулеры не отсекаются, а клик-фрод и накрутки поведенческих факторов уходят. Метрика и рекламные кабинеты показывают честные цифры.',
    ],
    [
        'title' => 'Онлайн-сервисы и личные кабинеты',
        'text'  => 'Остановка подбора паролей, сканирования уязвимостей и автоматизированных регистраций без ущерба для удобства живых пользователей.',
    ],
];

$reviews = [
    [
        'text' => 'Держали стоп-лист в .htaccess руками, каждую неделю кто-то из сотрудников его дописывал. Здесь правила правятся в панели за минуту, а журнал показывает, кого именно мы отсекли.',
        'name' => 'Технический директор интернет-магазина',
        'role' => 'Электроника, 40 000 товаров',
    ],
    [
        'text' => 'После включения фильтрации и капчи нагрузка на сервер в часы распродаж упала заметно, а заявки в формах почти перестали приходить от ботов. Заказы при этом не теряются.',
        'name' => 'Руководитель отдела разработки',
        'role' => 'Digital-агентство, 12 проектов на Битрикс',
    ],
    [
        'text' => 'Понравилось, что модуль не требует доработки шаблона и не ломает композит. Поставили на несколько сайтов, статистика по рекламе стала честнее, отчёты — понятнее.',
        'name' => 'Специалист по контекстной рекламе',
        'role' => 'Ниша B2B, услуги',
    ],
];

$faq = [
    [
        'q' => 'Нужно ли править шаблон сайта?',
        'a' => 'Нет. Окно капчи и коды аналитики добавляются в готовый HTML страницы на событии буфера вывода. Модуль не требует правок шаблона, компонентов и файлов сайта.',
    ],
    [
        'q' => 'Пострадает ли поисковая выдача?',
        'a' => 'Нет. В модуле есть белый список из 20+ поисковых роботов (YandexBot, GoogleBot, BingBot, Baidu, Applebot и другие): им капча не показывается и доступ не ограничивается.',
    ],
    [
        'q' => 'Что будет с реальными покупателями?',
        'a' => 'Из серого списка и подсетей посетителю показывается Яндекс SmartCaptcha вместо блокировки. Проверку проходит живой человек за пару секунд и продолжает работать с сайтом.',
    ],
    [
        'q' => 'Как быстро посетитель перестаёт видеть капчу?',
        'a' => 'После успешного прохождения ставится подписанная cookie со сроком действия 24 часа (значение настраивается) — при следующем визите проверка не показывается.',
    ],
    [
        'q' => 'Можно ли обойти капчу подделкой cookie?',
        'a' => 'Нет. Cookie подписывается HMAC-SHA256 с секретной солью и привязана к IP-адресу, хранится в HTTP-only режиме. Изменение или перенос значения делает её недействительной.',
    ],
    [
        'q' => 'Замедлит ли модуль сайт?',
        'a' => 'Нет. Правила читаются одним запросом и кэшируются на час, сами проверки выполняются в памяти без обращений к базе на каждом хите. Композитный кэш страдает только там, где реально нужна капча.',
    ],
    [
        'q' => 'Какой сайт нужен для демонстрации?',
        'a' => 'Битрикс-платформа любой редакции с доступом к установке модулей. Установка занимает несколько минут: загрузка, установка в админке, указание ключей SmartCaptcha и загрузка ваших стоп-листов.',
    ],
    [
        'q' => 'Что входит в поддержку и обновления?',
        'a' => 'Обновления модуля, помощь по установке и настройке правил, консультации по фильтрации трафика. Для тарифов «Старт» и «Бизнес» срок — 12 месяцев, для «Агентства» — 24 месяца.',
    ],
];

$stepsToBuy = [
    ['title' => 'Оставьте заявку', 'text' => 'Выберите тариф и отправьте форму — напишем, уточним задачу и подтвердим стоимость.'],
    ['title' => 'Получите модуль', 'text' => 'Пришлём архив модуля и инструкцию по установке, либо сами установим на ваш сайт.'],
    ['title' => 'Настройте правила', 'text' => 'Добавьте IP, подсети и User-Agent в панели управления или загрузите готовые списки из TXT.'],
    ['title' => 'Следите за результатом', 'text' => 'Смотрите журнал визитов и статистику: сколько мусорного трафика отсечено и что происходит на сайте.'],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($pageTitle) ?></title>
    <meta name="description" content="<?= $e($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= $e($pageTitle) ?>">
    <meta property="og:description" content="<?= $e($pageDescription) ?>">
    <meta name="theme-color" content="#0f172a">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/cleartrafic/assets/landing.css">
</head>
<body class="ct-body">

<a class="ct-skip" href="#features">Перейти к возможностям</a>

<header class="ct-header" id="ct-header">
    <div class="ct-wrap ct-header__inner">
        <a class="ct-logo" href="/cleartrafic/">
            <span class="ct-logo__mark" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 3l7 3v6c0 4.5-3 7.6-7 9-4-1.4-7-4.5-7-9V6l7-3z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="M8.5 8.5l7 7m0-7l-7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            <span class="ct-logo__text">
                <strong><?= $e($product['name']) ?></strong>
                <small>модуль для 1С-Битрикс</small>
            </span>
        </a>

        <nav class="ct-nav" aria-label="Основная навигация">
            <?php foreach ($navItems as $href => $label): ?>
                <a href="<?= $e($href) ?>"><?= $e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="ct-header__actions">
            <?php if ($product['demo']): ?>
                <a class="ct-btn ct-btn--ghost" href="<?= $e($product['demo_url']) ?>">Демо-панель</a>
            <?php endif; ?>
            <a class="ct-btn ct-btn--primary" href="#lead">Получить модуль</a>
        </div>

        <button class="ct-burger" type="button" id="ct-burger" aria-label="Меню">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>

<main>

    <!-- ================= HERO ================= -->
    <section class="ct-hero">
        <div class="ct-hero__glow" aria-hidden="true"></div>
        <div class="ct-wrap ct-hero__inner">
            <div class="ct-hero__content">
                <span class="ct-badge">Модуль для 1С-Битрикс · <?= $e($product['vendor']) ?></span>
                <h1 class="ct-hero__title">
                    Боты не пройдут.<br>
                    <span class="ct-gradient">Клиенты — останутся.</span>
                </h1>
                <p class="ct-hero__text">
                    Модуль фильтрации трафика отсекает парсеров, скрейперов, спам-ботов и клик-фрод
                    через Яндекс SmartCaptcha, а не грубую блокировку. Реальные покупатели
                    и поисковые роботы работают с сайтом как обычно.
                </p>

                <div class="ct-hero__actions">
                    <a class="ct-btn ct-btn--primary ct-btn--lg" href="#lead">Оставить заявку</a>
                    <a class="ct-btn ct-btn--ghost ct-btn--lg" href="#pricing">Посмотреть тарифы</a>
                </div>

                <dl class="ct-stats">
                    <div>
                        <dt>20+</dt>
                        <dd>поисковых систем в белом списке</dd>
                    </div>
                    <div>
                        <dt>0</dt>
                        <dd>запросов к базе на каждом хите</dd>
                    </div>
                    <div>
                        <dt>5000</dt>
                        <dd>строк стоп-листа из одного файла</dd>
                    </div>
                    <div>
                        <dt>24 ч</dt>
                        <dd>запомненное прохождение капчи</dd>
                    </div>
                </dl>
            </div>

            <div class="ct-hero__visual">
                <div class="ct-mock">
                    <div class="ct-mock__bar">
                        <span class="ct-mock__dot"></span>
                        <span class="ct-mock__dot"></span>
                        <span class="ct-mock__dot"></span>
                        <span class="ct-mock__title">Фильтрация трафика — журнал визитов</span>
                    </div>
                    <div class="ct-mock__body">
                        <div class="ct-mock__row ct-mock__row--head">
                            <span>IP</span><span>Страница входа</span><span>Правило</span><span>Статус</span>
                        </div>
                        <div class="ct-mock__row">
                            <span class="ct-mono">185.13.90.24</span><span>/catalog/</span>
                            <span><em class="ct-tag ct-tag--bad">чёрный список</em></span>
                            <span class="ct-status ct-status--block">заблокирован</span>
                        </div>
                        <div class="ct-mock__row">
                            <span class="ct-mono">51.15.204.77</span><span>/catalog/price/</span>
                            <span><em class="ct-tag ct-tag--warn">маска 51.15.0.0/16</em></span>
                            <span class="ct-status ct-status--captcha">капча</span>
                        </div>
                        <div class="ct-mock__row">
                            <span class="ct-mono">104.28.14.6</span><span>/search/</span>
                            <span><em class="ct-tag ct-tag--warn">User-Agent</em></span>
                            <span class="ct-status ct-status--captcha">капча</span>
                        </div>
                        <div class="ct-mock__row">
                            <span class="ct-mono">95.108.213.8</span><span>/</span>
                            <span><em class="ct-tag ct-tag--ok">YandexBot</em></span>
                            <span class="ct-status ct-status--ok">доступ открыт</span>
                        </div>
                        <div class="ct-mock__row">
                            <span class="ct-mono">178.62.3.19</span><span>/callback/</span>
                            <span><em class="ct-tag">реферер</em></span>
                            <span class="ct-status ct-status--ok">проверка пройдена</span>
                        </div>
                    </div>
                    <div class="ct-mock__foot">
                        <span class="ct-mock__pill">Правил в кэше: 4 218</span>
                        <span class="ct-mock__pill">Кэш правил: 1 час</span>
                        <span class="ct-mock__pill ct-mock__pill--accent">Капча: SmartCaptcha</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= БОЛИ ================= -->
    <section class="ct-section" id="problems">
        <div class="ct-wrap">
            <div class="ct-head">
                <span class="ct-eyebrow">Проблема</span>
                <h2 class="ct-h2">Знакомая картина любого загруженного сайта</h2>
                <p class="ct-lead">
                    Пока владелец сайта занимается продажами, автоматические скрипты решают свои задачи
                    за его счёт: ресурсами хостинга, контентом, рекламным бюджетом и временем сотрудников.
                </p>
            </div>

            <div class="ct-grid ct-grid--3">
                <?php foreach ($problems as $index => $item): ?>
                    <article class="ct-card ct-card--problem">
                        <span class="ct-card__num"><?= $e(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                        <h3 class="ct-card__title"><?= $e($item['title']) ?></h3>
                        <p class="ct-card__text"><?= $e($item['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="ct-callout">
                <strong>Итог:</strong> сервер работает на ботов, аналитика врёт, а часть бюджета уходит
                на трафик, который никогда не станет заказом. Проблема не решается ростом мощностей —
                нужна точечная фильтрация.
            </div>
        </div>
    </section>

    <!-- ================= КАК РАБОТАЕТ ================= -->
    <section class="ct-section ct-section--dark" id="how">
        <div class="ct-wrap">
            <div class="ct-head">
                <span class="ct-eyebrow">Решение</span>
                <h2 class="ct-h2">Капча для подозрительных, свободный доступ для клиентов</h2>
                <p class="ct-lead">
                    Модуль встраивается в обработку запроса и решает, что показать посетителю:
                    страницу, проверку или блокировку. Решение принимается на каждом запросе заново.
                </p>
            </div>

            <div class="ct-steps">
                <?php foreach ($steps as $step): ?>
                    <article class="ct-step">
                        <span class="ct-step__num"><?= $e($step['num']) ?></span>
                        <h3 class="ct-step__title"><?= $e($step['title']) ?></h3>
                        <p class="ct-step__text"><?= $e($step['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="ct-flow">
                <div class="ct-flow__item"><span class="ct-flow__label">Посетитель</span><span class="ct-flow__desc">запрос страницы</span></div>
                <span class="ct-flow__arrow" aria-hidden="true">&#8594;</span>
                <div class="ct-flow__item ct-flow__item--accent"><span class="ct-flow__label">Правила модуля</span><span class="ct-flow__desc">IP · подсети · User-Agent · реферер</span></div>
                <span class="ct-flow__arrow" aria-hidden="true">&#8594;</span>
                <div class="ct-flow__item"><span class="ct-flow__label">SmartCaptcha</span><span class="ct-flow__desc">серый список, маски, роботы-исключения</span></div>
                <span class="ct-flow__arrow" aria-hidden="true">&#8594;</span>
                <div class="ct-flow__item ct-flow__item--ok"><span class="ct-flow__label">Сайт и статистика</span><span class="ct-flow__desc">журнал визитов, оповещения</span></div>
            </div>
        </div>
    </section>

    <!-- ================= ВОЗМОЖНОСТИ ================= -->
    <section class="ct-section" id="features">
        <div class="ct-wrap">
            <div class="ct-head">
                <span class="ct-eyebrow">Возможности</span>
                <h2 class="ct-h2">Всё для управления трафиком — в одной панели</h2>
                <p class="ct-lead">
                    Модуль закрывает полный цикл: правила, автоматическая проверка, разбор журнала
                    и оповещения. Без редактирования файлов сайта и без отдельной инфраструктуры.
                </p>
            </div>

            <div class="ct-grid ct-grid--3">
                <?php foreach ($features as $item): ?>
                    <article class="ct-card ct-card--feature">
                        <span class="ct-card__icon" aria-hidden="true">
                            <?php if ($item['icon'] === 'shield'): ?>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 3l7 3v6c0 4.5-3 7.6-7 9-4-1.4-7-4.5-7-9V6l7-3z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                            <?php elseif ($item['icon'] === 'check'): ?>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <?php elseif ($item['icon'] === 'network'): ?>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="6" cy="6" r="2.4" stroke="currentColor" stroke-width="1.7"/><circle cx="18" cy="18" r="2.4" stroke="currentColor" stroke-width="1.7"/><path d="M8 8l8 8M18 6h-3m-6 12H6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                            <?php elseif ($item['icon'] === 'bot'): ?>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><rect x="4" y="8" width="16" height="11" rx="3" stroke="currentColor" stroke-width="1.7"/><path d="M12 4v4M9 13h.01M15 13h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                            <?php elseif ($item['icon'] === 'link'): ?>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M10 14a4 4 0 015.7 0l2.3-2.3a4 4 0 10-5.7-5.7L11 7.3M14 10a4 4 0 00-5.7 0L6 12.3a4 4 0 105.7 5.7L13 16.7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                            <?php elseif ($item['icon'] === 'search'): ?>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="6" stroke="currentColor" stroke-width="1.7"/><path d="M20 20l-4.3-4.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                            <?php elseif ($item['icon'] === 'list'): ?>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            <?php elseif ($item['icon'] === 'upload'): ?>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 16V5m0 0l-4 4m4-4l4 4M5 19h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <?php else: ?>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.7"/><path d="M4 7l8 6 8-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                            <?php endif; ?>
                        </span>
                        <h3 class="ct-card__title"><?= $e($item['title']) ?></h3>
                        <p class="ct-card__text"><?= $e($item['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ================= ТЕХНОЛОГИИ ================= -->
    <section class="ct-section ct-section--soft" id="tech">
        <div class="ct-wrap">
            <div class="ct-head">
                <span class="ct-eyebrow">Технологии</span>
                <h2 class="ct-h2">Сделано для продакшена, а не для демонстрации</h2>
                <p class="ct-lead">
                    Модуль рассчитан на нагруженные проекты: продуманы кэширование, композитный кэш,
                    безопасность флага проверки и разграничение доступа.
                </p>
            </div>

            <div class="ct-tech">
                <?php foreach ($tech as $item): ?>
                    <article class="ct-tech__item">
                        <h3 class="ct-tech__title"><?= $e($item['title']) ?></h3>
                        <p class="ct-tech__text"><?= $e($item['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="ct-tags" aria-label="Ключевые технологии">
                <span class="ct-chip">Bitrix D7 ORM</span>
                <span class="ct-chip">COption</span>
                <span class="ct-chip">AJAX-контроллер</span>
                <span class="ct-chip">HMAC-SHA256</span>
                <span class="ct-chip">HTTP-only cookie</span>
                <span class="ct-chip">Композитный кэш</span>
                <span class="ct-chip">Индексы MySQL</span>
                <span class="ct-chip">Импорт TXT</span>
                <span class="ct-chip">OndBufferContent</span>
                <span class="ct-chip">Права доступа</span>
            </div>
        </div>
    </section>

    <!-- ================= СРАВНЕНИЕ ================= -->
    <section class="ct-section" id="compare">
        <div class="ct-wrap">
            <div class="ct-head">
                <span class="ct-eyebrow">Сравнение</span>
                <h2 class="ct-h2">Почему не хватает стоп-листов и блокировок</h2>
                <p class="ct-lead">
                    Классические способы работают грубо: они не показывают картину трафика и легко
                    отсекают живых клиентов вместе с ботами.
                </p>
            </div>

            <div class="ct-table-wrap">
                <table class="ct-table">
                    <thead>
                    <tr>
                        <th scope="col">Возможность</th>
                        <th scope="col">Без фильтрации</th>
                        <th scope="col">.htaccess и стоп-листы</th>
                        <th scope="col" class="ct-table__highlight">Модуль фильтрации трафика</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($comparison['rows'] as $row): ?>
                        <tr>
                            <th scope="row"><?= $e($row[0]) ?></th>
                            <td><?= $e($row[1]) ?></td>
                            <td><?= $e($row[2]) ?></td>
                            <td class="ct-table__highlight">
                                <span class="ct-table__mark" aria-hidden="true">&#10003;</span>
                                <?= $e($row[3]) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ================= КОМУ ПОДХОДИТ ================= -->
    <section class="ct-section ct-section--dark" id="audience">
        <div class="ct-wrap">
            <div class="ct-head">
                <span class="ct-eyebrow">Кому подходит</span>
                <h2 class="ct-h2">Один модуль — разные задачи</h2>
            </div>

            <div class="ct-grid ct-grid--3">
                <?php foreach ($audience as $item): ?>
                    <article class="ct-card ct-card--dark">
                        <h3 class="ct-card__title"><?= $e($item['title']) ?></h3>
                        <p class="ct-card__text"><?= $e($item['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ================= ТАРИФЫ ================= -->
    <section class="ct-section" id="pricing">
        <div class="ct-wrap">
            <div class="ct-head">
                <span class="ct-eyebrow">Тарифы</span>
                <h2 class="ct-h2">Прозрачная цена без подписок на каждый чих</h2>
                <p class="ct-lead">
                    Лицензия покупается один раз. Обновления и поддержка включены на весь срок тарифа,
                    продление — со скидкой.
                </p>
            </div>

            <div class="ct-pricing">
                <?php foreach ($pricing as $plan): ?>
                    <article class="ct-plan<?= $plan['featured'] ? ' ct-plan--featured' : '' ?>">
                        <?php if ($plan['featured']): ?>
                            <span class="ct-plan__ribbon">Популярный выбор</span>
                        <?php endif; ?>
                        <h3 class="ct-plan__name"><?= $e($plan['name']) ?></h3>
                        <p class="ct-plan__note"><?= $e($plan['note']) ?></p>
                        <p class="ct-plan__price"><?= $e($plan['price']) ?></p>
                        <p class="ct-plan__period"><?= $e($plan['period']) ?></p>
                        <ul class="ct-plan__list">
                            <?php foreach ($plan['features'] as $feature): ?>
                                <li><?= $e($feature) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="ct-btn <?= $plan['featured'] ? 'ct-btn--primary' : 'ct-btn--outline' ?> ct-btn--block"
                           href="#lead" data-plan="<?= $e($plan['name']) ?>">
                            <?= $e($plan['button']) ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="ct-services">
                <?php foreach ($services as $service): ?>
                    <article class="ct-service">
                        <div class="ct-service__head">
                            <h3 class="ct-service__title"><?= $e($service['name']) ?></h3>
                            <span class="ct-service__price"><?= $e($service['price']) ?></span>
                        </div>
                        <p class="ct-service__text"><?= $e($service['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ================= ПОРЯДОК РАБОТЫ ================= -->
    <section class="ct-section ct-section--soft" id="process">
        <div class="ct-wrap">
            <div class="ct-head">
                <span class="ct-eyebrow">Как получить модуль</span>
                <h2 class="ct-h2">От заявки до работающей фильтрации</h2>
            </div>

            <ol class="ct-timeline">
                <?php foreach ($stepsToBuy as $index => $item): ?>
                    <li class="ct-timeline__item">
                        <span class="ct-timeline__num"><?= $e((string)($index + 1)) ?></span>
                        <h3 class="ct-timeline__title"><?= $e($item['title']) ?></h3>
                        <p class="ct-timeline__text"><?= $e($item['text']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <!-- ================= ОТЗЫВЫ ================= -->
    <section class="ct-section" id="reviews">
        <div class="ct-wrap">
            <div class="ct-head">
                <span class="ct-eyebrow">Отзывы</span>
                <h2 class="ct-h2">Что говорят те, кто уже фильтрует трафик</h2>
            </div>

            <div class="ct-grid ct-grid--3">
                <?php foreach ($reviews as $review): ?>
                    <blockquote class="ct-review">
                        <p class="ct-review__text"><?= $e($review['text']) ?></p>
                        <footer class="ct-review__footer">
                            <strong><?= $e($review['name']) ?></strong>
                            <span><?= $e($review['role']) ?></span>
                        </footer>
                    </blockquote>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ================= FAQ ================= -->
    <section class="ct-section ct-section--soft" id="faq">
        <div class="ct-wrap ct-wrap--narrow">
            <div class="ct-head">
                <span class="ct-eyebrow">Вопросы и ответы</span>
                <h2 class="ct-h2">Отвечаем на главное</h2>
            </div>

            <div class="ct-accordion" id="ct-accordion">
                <?php foreach ($faq as $index => $item): ?>
                    <details class="ct-accordion__item" <?= $index === 0 ? 'open' : '' ?>>
                        <summary class="ct-accordion__summary">
                            <span><?= $e($item['q']) ?></span>
                            <span class="ct-accordion__icon" aria-hidden="true"></span>
                        </summary>
                        <div class="ct-accordion__content"><p><?= $e($item['a']) ?></p></div>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ================= ФОРМА ================= -->
    <section class="ct-section ct-section--cta" id="lead">
        <div class="ct-wrap ct-lead__inner">
            <div class="ct-lead__content">
                <span class="ct-eyebrow ct-eyebrow--light">Заявка</span>
                <h2 class="ct-h2 ct-h2--light"><?= $e($form['title']) ?></h2>
                <p class="ct-lead__text"><?= $e($form['subtitle']) ?></p>

                <ul class="ct-lead__list">
                    <li>Подберём тариф под число ваших сайтов</li>
                    <li>Покажем демо-панель и журнал визитов</li>
                    <li>Расскажем, как настроить правила под вашу нишу</li>
                    <li>Пришлём точную стоимость и условия внедрения</li>
                </ul>

                <div class="ct-lead__contacts">
                    <?php if ($contacts['email'] !== ''): ?>
                        <a href="mailto:<?= $e($contacts['email']) ?>"><?= $e($contacts['email']) ?></a>
                    <?php endif; ?>
                    <?php if ($contacts['phone'] !== ''): ?>
                        <a href="tel:<?= $e(str_replace([' ', '(', ')', '-'], '', $contacts['phone'])) ?>"><?= $e($contacts['phone']) ?></a>
                    <?php endif; ?>
                    <?php if ($contacts['telegram'] !== ''): ?>
                        <a href="<?= $e($contacts['telegram']) ?>" rel="nofollow noopener" target="_blank">Telegram</a>
                    <?php endif; ?>
                </div>
            </div>

            <form class="ct-form" id="ct-form" action="/cleartrafic/send.php" method="post" novalidate>
                <div class="ct-form__row">
                    <label class="ct-field">
                        <span class="ct-field__label">Как к вам обращаться <i>*</i></span>
                        <input type="text" name="name" required maxlength="120" autocomplete="name" placeholder="Иван">
                    </label>
                </div>

                <div class="ct-form__row ct-form__row--2">
                    <label class="ct-field">
                        <span class="ct-field__label">E-mail <i>*</i></span>
                        <input type="email" name="email" required maxlength="120" autocomplete="email" placeholder="ivan@company.ru">
                    </label>
                    <label class="ct-field">
                        <span class="ct-field__label">Телефон или Telegram</span>
                        <input type="text" name="phone" maxlength="60" placeholder="+7 000 000-00-00">
                    </label>
                </div>

                <div class="ct-form__row ct-form__row--2">
                    <label class="ct-field">
                        <span class="ct-field__label">Сайт</span>
                        <input type="text" name="site" maxlength="160" placeholder="example.ru">
                    </label>
                    <label class="ct-field">
                        <span class="ct-field__label">Тариф</span>
                        <select name="plan" id="ct-form-plan">
                            <option value="">Подберите вместе с менеджером</option>
                            <?php foreach ($pricing as $plan): ?>
                                <option value="<?= $e($plan['name']) ?>"><?= $e($plan['name']) ?> — <?= $e($plan['price']) ?></option>
                            <?php endforeach; ?>
                            <option value="Внедрение «под ключ»">Внедрение «под ключ»</option>
                        </select>
                    </label>
                </div>

                <div class="ct-form__row">
                    <label class="ct-field">
                        <span class="ct-field__label">Задача</span>
                        <textarea name="message" rows="4" maxlength="2000" placeholder="Например: парсят каталог и выкачивают цены, нужна капча и защита от парсеров"></textarea>
                    </label>
                </div>

                <label class="ct-check">
                    <input type="checkbox" name="consent" value="Y" required>
                    <span><?= $e($config['leads']['consent_text']) ?></span>
                </label>

                <!-- Ловушка для ботов: поле скрыто от людей -->
                <div class="ct-trap" aria-hidden="true">
                    <label>Не заполняйте это поле
                        <input type="text" name="company" tabindex="-1" autocomplete="off">
                    </label>
                </div>

                <button class="ct-btn ct-btn--primary ct-btn--lg ct-btn--block" type="submit" id="ct-form-submit">
                    <?= $e($form['button']) ?>
                </button>

                <p class="ct-form__status" id="ct-form-status" role="status" aria-live="polite"></p>
                <p class="ct-form__hint">Отправляя форму, вы соглашаетесь на обработку персональных данных.</p>
            </form>
        </div>
    </section>

</main>

<footer class="ct-footer">
    <div class="ct-wrap ct-footer__inner">
        <div class="ct-footer__brand">
            <strong><?= $e($product['name']) ?></strong>
            <span>Модуль для <?= $e($product['platform']) ?> · <?= $e($product['module']) ?></span>
        </div>
        <nav class="ct-footer__nav" aria-label="Навигация в подвале">
            <?php foreach ($navItems as $href => $label): ?>
                <a href="<?= $e($href) ?>"><?= $e($label) ?></a>
            <?php endforeach; ?>
            <a href="#lead">Заявка</a>
        </nav>
        <div class="ct-footer__contacts">
            <?php if ($contacts['email'] !== ''): ?>
                <a href="mailto:<?= $e($contacts['email']) ?>"><?= $e($contacts['email']) ?></a>
            <?php endif; ?>
            <span><?= $e($contacts['department']) ?></span>
        </div>
    </div>
    <div class="ct-wrap ct-footer__bottom">
        <span>© <?= date('Y') ?> <?= $e($product['vendor']) ?>. Все права защищены.</span>
        <span>Яндекс SmartCaptcha — сервис проверки, что вы не робот.</span>
    </div>
</footer>

<script>
    /* Сообщения формы: подставляются в конфигурации лендинга */
    window.CT_FORM_MESSAGES = <?= json_encode([
        'validation' => 'Проверьте выделенные поля и согласие на обработку данных.',
        'error'      => $form['error'],
    ], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/cleartrafic/assets/landing.js" defer></script>
</body>
</html>
