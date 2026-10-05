<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Engine\Contract\Controllerable;

Loader::includeModule("iblock");

/**
 * Компонент "Список акций" для партнёрского раздела /partners/marketing/.
 *
 * Выводит элементы инфоблока акций (ID = 6, тип "pages"):
 * слева фото, справа название и кнопка "Изменить". При нажатии "Изменить"
 * под выбранной акцией раскрывается панель редактирования с визуальным
 * редактором описания (Bitrix CHTMLEditor), загрузкой фото, SEO-полями
 * и полями даты активности (ACTIVE_FROM / ACTIVE_TO).
 *
 * Сохранение выполняется AJAX-действием saveAction; разметка визуального
 * редактора подгружается по требованию действием getEditor.
 */
class ActionsList extends \CBitrixComponent implements Controllerable
{
    /** ID инфоблока акций (страница /actions/ использует IBLOCK_ID = 6, тип "pages"). */
    const IBLOCK_ID = 6;

    /** Папка кеша компонента. */
    const CACHE_DIR = '/actions_list';

    /** Максимальное количество выводимых акций по умолчанию. */
    const DEFAULT_LIMIT = 100;

    /** Время жизни кеша по умолчанию (1 час). */
    const DEFAULT_CACHE_TIME = 3600000;

    /**
     * Подготовка параметров компонента.
     *
     * @param array $arParams
     * @return array
     */
    public function onPrepareComponentParams($arParams)
    {
        $arParams['LIMIT'] = (int)($arParams['LIMIT'] ?? 0);
        if ($arParams['LIMIT'] <= 0) {
            $arParams['LIMIT'] = self::DEFAULT_LIMIT;
        }

        $arParams['CACHE_TIME'] = (int)($arParams['CACHE_TIME'] ?? 0);
        if ($arParams['CACHE_TIME'] <= 0) {
            $arParams['CACHE_TIME'] = self::DEFAULT_CACHE_TIME;
        }

        return $arParams;
    }

    /**
     * Основной вывод компонента. Результат кешируется в CACHE_DIR.
     */
    public function executeComponent()
    {
        $cacheId = implode('|', ['v2', self::IBLOCK_ID, $this->arParams['LIMIT']]);

        if ($this->StartResultCache($this->arParams['CACHE_TIME'], $cacheId, self::CACHE_DIR)) {
            $this->arResult['ITEMS'] = $this->getActions();
            $this->EndResultCache();
        }

        $this->includeComponentTemplate();
    }

    /**
     * Выборка акций инфоблока (включая неактивные — для управления
     * активностью) с фото, описанием, датами и SEO.
     *
     * @return array
     */
    private function getActions(): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        $rs = \CIBlockElement::GetList(
            [
                'ACTIVE_FROM' => 'DESC',
                'SORT'        => 'ASC',
                'ID'          => 'DESC',
            ],
            [
                'IBLOCK_ID' => self::IBLOCK_ID,
            ],
            false,
            ['nTopCount' => $this->arParams['LIMIT']],
            [
                'ID',
                'NAME',
                'ACTIVE',
                'SORT',
                'PREVIEW_PICTURE',
                'DETAIL_PICTURE',
                'DETAIL_TEXT',
                'ACTIVE_FROM',
                'ACTIVE_TO',
            ]
        );

        $items = [];
        while ($row = $rs->Fetch()) {
            $items[(int)$row['ID']] = $this->enrichItem($row);
        }

        return array_values($items);
    }

    /**
     * Дополнение строки элемента вычисляемыми полями: фото, даты для
     * datetime-local, отображаемые даты и SEO-параметры.
     *
     * @param array $row
     * @return array
     */
    private function enrichItem(array $row): array
    {
        $id = (int)$row['ID'];
        $row['ID'] = $id;
        $row['PICTURE_SRC'] = $this->getPictureSrc($row);
        $row['DETAIL_TEXT'] = (string)($row['DETAIL_TEXT'] ?? '');

        // Даты активности: значение из БД (формат сайта) -> timestamp -> ISO для datetime-local.
        $tsFrom = !empty($row['ACTIVE_FROM']) ? (int)strtotime((string)$row['ACTIVE_FROM']) : 0;
        $tsTo   = !empty($row['ACTIVE_TO']) ? (int)strtotime((string)$row['ACTIVE_TO']) : 0;

        $row['ACTIVE_FROM_INPUT'] = $tsFrom > 0 ? date('Y-m-d\TH:i', $tsFrom) : '';
        $row['ACTIVE_TO_INPUT']   = $tsTo > 0 ? date('Y-m-d\TH:i', $tsTo) : '';
        $row['DATE_FROM_DISPLAY'] = $tsFrom > 0 ? date('d.m.Y H:i', $tsFrom) : '';
        $row['DATE_TO_DISPLAY']   = $tsTo > 0 ? date('d.m.Y H:i', $tsTo) : '';

        $seo = $this->getSeoParams($id);
        $row['SEO_TITLE']       = $seo['title'];
        $row['SEO_DESCRIPTION'] = $seo['description'];

        return $row;
    }

    /**
     * Одна акция по ID (с обогащением) — используется после создания.
     *
     * @param int $id
     * @return array|null
     */
    private function getActionById(int $id): ?array
    {
        if ($id <= 0 || !Loader::includeModule('iblock')) {
            return null;
        }

        $rs = \CIBlockElement::GetList(
            [],
            ['ID' => $id, 'IBLOCK_ID' => self::IBLOCK_ID],
            false,
            false,
            [
                'ID', 'NAME', 'ACTIVE', 'SORT',
                'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'DETAIL_TEXT',
                'ACTIVE_FROM', 'ACTIVE_TO',
            ]
        );

        $row = $rs->Fetch();

        return $row ? $this->enrichItem($row) : null;
    }

    /**
     * SEO-параметры элемента (meta title/description).
     * Используется модуль ldo.develop (Prokhorov\Api\Helpers\Seo).
     *
     * @param int $elementId
     * @return array{title: string, description: string}
     */
    private function getSeoParams(int $elementId): array
    {
        if (
            !Loader::includeModule('ldo.develop')
            || !class_exists('\\Prokhorov\\Api\\Helpers\\Seo')
        ) {
            return ['title' => '', 'description' => ''];
        }

        try {
            $params = \Prokhorov\Api\Helpers\Seo::getParams(self::IBLOCK_ID, $elementId);
        } catch (\Throwable $e) {
            return ['title' => '', 'description' => ''];
        }

        return [
            'title'       => (string)($params['title'] ?? ''),
            'description' => (string)($params['description'] ?? ''),
        ];
    }

    /**
     * Src миниатюры фото акции (PREVIEW_PICTURE -> DETAIL_PICTURE).
     *
     * @param array $item
     * @return string
     */
    private function getPictureSrc(array $item): string
    {
        $pictureId = (int)($item['PREVIEW_PICTURE'] ?? 0);
        if ($pictureId <= 0) {
            $pictureId = (int)($item['DETAIL_PICTURE'] ?? 0);
        }
        if ($pictureId <= 0) {
            return '';
        }

        $resized = \CFile::ResizeImageGet(
            $pictureId,
            ['width' => 240, 'height' => 240],
            BX_RESIZE_IMAGE_PROPORTIONAL,
            true
        );

        return is_array($resized) ? (string)($resized['src'] ?? '') : '';
    }

    /**
     * Разметка одной акции: фото слева, название и кнопка "Изменить" справа,
     * скрытая панель редактирования снизу.
     *
     * @param array $item
     * @return string
     */
    public function renderItemHtml(array $item): string
    {
        $id = (int)$item['ID'];
        $name = htmlspecialcharsbx((string)($item['NAME'] ?? ''));
        $active = ($item['ACTIVE'] ?? 'Y') === 'Y';
        $sort = (int)($item['SORT'] ?? 0);

        $imgSrc = (string)($item['PICTURE_SRC'] ?? '');
        if ($imgSrc !== '') {
            $img = '<img class="action-item__img" src="' . htmlspecialcharsbx($imgSrc) . '" alt="' . $name . '" loading="lazy">';
        } else {
            $img = '<span class="action-item__img action-item__img--empty"></span>';
        }

        $dateFrom = htmlspecialcharsbx((string)($item['DATE_FROM_DISPLAY'] ?? ''));
        $dateTo   = htmlspecialcharsbx((string)($item['DATE_TO_DISPLAY'] ?? ''));
        $meta     = '';
        if ($dateFrom !== '' || $dateTo !== '') {
            $meta = 'Активность: ' . ($dateFrom !== '' ? $dateFrom : '—') . ' — ' . ($dateTo !== '' ? $dateTo : 'бессрочно');
        }

        $checkAttr = $active ? ' checked' : '';
        $inactiveClass = $active ? '' : ' action-item--inactive';
        $statusHtml = $active ? '' : '<div class="action-item__status">Неактивна</div>';

        // Сырой HTML описания — попадёт в конфиг визуального редактора.
        $detailRaw = (string)($item['DETAIL_TEXT'] ?? '');
        $activeFromInput = htmlspecialcharsbx((string)($item['ACTIVE_FROM_INPUT'] ?? ''));
        $activeToInput   = htmlspecialcharsbx((string)($item['ACTIVE_TO_INPUT'] ?? ''));
        $seoTitle       = htmlspecialcharsbx((string)($item['SEO_TITLE'] ?? ''));
        $seoDescription = htmlspecialcharsbx((string)($item['SEO_DESCRIPTION'] ?? ''));

        // Текущее фото (для предпросмотра при загрузке нового).
        $photoSrc = '';
        if ((int)($item['PREVIEW_PICTURE'] ?? 0) > 0) {
            $photoSrc = (string)\CFile::GetPath((int)$item['PREVIEW_PICTURE']);
        } elseif ((int)($item['DETAIL_PICTURE'] ?? 0) > 0) {
            $photoSrc = (string)\CFile::GetPath((int)$item['DETAIL_PICTURE']);
        }

        $photoHtml = $photoSrc !== ''
            ? '<img class="action-photo" src="' . htmlspecialcharsbx($photoSrc) . '" alt="">'
            : '<span class="action-photo action-photo--empty"></span>';

        return '
        <div class="action-item' . $inactiveClass . '" data-id="' . $id . '">
            <div class="action-item__head">
                <div class="action-item__img-wrap">' . $img . '</div>
                <div class="action-item__info">
                    <div class="action-item__name" title="' . $name . '">' . $name . '</div>
                    ' . $statusHtml . '
                    ' . ($meta !== '' ? '<div class="action-item__meta">' . $meta . '</div>' : '') . '
                </div>
                <div class="action-item__actions">
                    <button type="button" class="action-btn action-btn--edit" data-action="edit">Изменить</button>
                    <button type="button" class="action-btn action-btn--save" data-action="save" style="display:none;">Сохранить</button>
                    <button type="button" class="action-btn action-btn--cancel" data-action="cancel" style="display:none;">Отмена</button>
                </div>
            </div>

            <div class="action-edit" style="display:none;">
                <div class="action-tabs" role="tablist">
                    <button type="button" class="action-tab is-active" data-action-tab="main">Описание акции</button>
                    <button type="button" class="action-tab" data-action-tab="seo">SEO описание</button>
                </div>

                <div class="action-pane is-active" data-action-pane="main">
                    <label class="action-field">
                        <span class="action-field__label">Название акции</span>
                        <input type="text" class="action-name" value="' . $name . '" disabled>
                    </label>

                    <label class="action-field action-field--narrow">
                        <span class="action-field__label">Сортировка</span>
                        <input type="number" class="action-sort" value="' . $sort . '" disabled>
                    </label>

                    <label class="action-field action-field--active" title="Активность">
                        <span class="action-field__label">Активность</span>
                        <span class="toggle-switch">
                            <input type="checkbox" class="action-active"' . $checkAttr . ' disabled>
                            <span class="toggle-switch__slider"></span>
                        </span>
                    </label>

                    <label class="action-field">
                        <span class="action-field__label">Дата активности (с)</span>
                        <input type="datetime-local" class="action-active-from" value="' . $activeFromInput . '" disabled>
                    </label>

                    <label class="action-field">
                        <span class="action-field__label">Дата активности (по)</span>
                        <input type="datetime-local" class="action-active-to" value="' . $activeToInput . '" disabled>
                    </label>

                    <label class="action-field action-field--full">
                        <span class="action-field__label">Фото акции</span>
                        <span class="action-photo-block">
                            ' . $photoHtml . '
                            <input type="file" class="action-picture" accept="image/*" disabled>
                        </span>
                    </label>

                    <label class="action-field action-field--full">
                        <span class="action-field__label">Описание акции (визуальный редактор)</span>
                        <div class="action-detail-wrap" data-detail="' . htmlspecialcharsbx($detailRaw) . '"></div>
                    </label>
                </div>

                <div class="action-pane is-active" data-action-pane="seo" style="display:none;">
                    <label class="action-field action-field--full">
                        <span class="action-field__label">SEO: Заголовок (meta title)</span>
                        <input type="text" class="action-seo-title" value="' . $seoTitle . '" disabled>
                    </label>

                    <label class="action-field action-field--full">
                        <span class="action-field__label">SEO описание (meta description)</span>
                        <textarea class="action-seo-description" rows="4" disabled>' . $seoDescription . '</textarea>
                    </label>
                </div>
            </div>
        </div>';
    }

    /**
     * Разметка визуального HTML-редактора описания (Bitrix CHTMLEditor).
     * Скелет выводится скрытым (display=false) и только сохраняет конфиг —
     * сам редактор создаётся на клиенте (BXHtmlEditor.Show). HTML отдаётся
     * по AJAX (getEditorAction), чтобы не раздувать исходную страницу.
     * Если модуль fileman недоступен — фолбэк на обычный textarea.
     *
     * @param int $id
     * @param string $content
     * @return string
     */
    private function renderDetailEditor(int $id, string $content): string
    {
        if (!Loader::includeModule('fileman') || !class_exists('\CHTMLEditor')) {
            return '<textarea class="action-detail" rows="6" disabled>' . htmlspecialcharsbx($content) . '</textarea>';
        }

        ob_start();
        $editor = new \CHTMLEditor();
        $editor->Show([
            'id'                        => 'al' . $id,
            'inputName'                 => 'detail_' . $id,
            'inputId'                   => 'detail_' . $id,
            'content'                   => $content,
            'display'                   => false,
            // height — целое число (пиксели): JS-редактор использует его
            // в арифметике, строка '260px' приводит к NaN.
            'width'                     => '100%',
            'height'                    => 300,
            'showNodeNavi'              => false,
            'arTemplates'               => [],
            'useFileDialogs'            => false,
            'showTaskbars'              => false,
            'showComponents'            => false,
            'showSnippets'              => false,
            'bAllowPhp'                 => false,
            'allowPhp'                  => false,
            'askBeforeUnloadPage'       => false,
            'uploadImagesFromClipboard' => false,
            'setFocusAfterShow'         => false,
            'placeholder'               => 'Текст акции',
            'fontSize'                  => '14px',
            'iframeCss'                 => $this->getIframeCss(),
        ]);

        return (string)ob_get_clean();
    }

    /**
     * CSS для содержимого iframe визуального редактора (тёмная тема партнёрки).
     * Читается из файла шаблона и передаётся редактору текстом.
     *
     * @return string
     */
    private function getIframeCss(): string
    {
        static $css = null;
        if ($css !== null) {
            return $css;
        }

        $path = $_SERVER['DOCUMENT_ROOT']
            . '/local/components/ldo/actions.list/templates/.default/editor-iframe.css';

        $css = is_file($path) ? (string)file_get_contents($path) : '';

        return $css;
    }

    /**
     * Очистка пользовательского HTML описания (защита от XSS).
     * SECURE_LEVEL_LOW удаляет script/iframe/embed и on*-атрибуты,
     * сохраняя форматирование редактора (div/span/style/class).
     *
     * @param string $html
     * @return string
     */
    private function sanitizeDetailHtml(string $html): string
    {
        if ($html === '' || !class_exists('\CBXSanitizer')) {
            return $html;
        }

        $sanitizer = new \CBXSanitizer();
        $sanitizer->SetLevel(\CBXSanitizer::SECURE_LEVEL_LOW);

        return (string)$sanitizer->SanitizeHtml($html);
    }

    /**
     * Преобразование значения datetime-local ('Y-m-d\TH:i') в формат сайта
     * (d.m.Y H:i:s), принимаемый CIBlockElement::Update для ACTIVE_FROM/TO.
     *
     * @param string $value
     * @return string
     */
    private function normalizeDateInput(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $ts = strtotime(str_replace('T', ' ', $value));
        return $ts > 0 ? date('d.m.Y H:i:s', $ts) : '';
    }

    /**
     * Конфигурация AJAX-действий. Префильтры пустые — CSRF-защита
     * выполняется вручную через check_bitrix_sessid().
     *
     * @return array
     */
    public function configureActions()
    {
        return [
            'getEditor' => [
                'prefilters' => [],
            ],
            'saveAction' => [
                'prefilters' => [],
            ],
            'createAction' => [
                'prefilters' => [],
            ],
        ];
    }

    /**
     * AJAX-действие: разметка визуального редактора описания для акции.
     * Запрашивается по кнопке "Изменить".
     *
     * @return array{html: string, error?: string}
     */
    public function getEditorAction(): array
    {
        if (!check_bitrix_sessid()) {
            return ['html' => '', 'error' => 'Ошибка сессии. Пожалуйста, обновите страницу.'];
        }

        if (!Loader::includeModule('iblock')) {
            return ['html' => '', 'error' => 'Модуль iblock не найден.'];
        }

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        $id = (int)$request->getPost('id');

        // id = 0 — редактор для формы создания новой акции (пустое описание).
        $content = '';
        if ($id > 0) {
            $rs = \CIBlockElement::GetList(
                [],
                ['ID' => $id, 'IBLOCK_ID' => self::IBLOCK_ID],
                false,
                false,
                ['ID', 'DETAIL_TEXT']
            );

            $row = $rs->Fetch();
            if (!$row) {
                return ['html' => '', 'error' => 'Акция не найдена.'];
            }

            $content = (string)$row['DETAIL_TEXT'];
        }

        return [
            'html' => $this->renderDetailEditor($id, $content),
        ];
    }

    /**
     * AJAX-действие: сохранение акции.
     * Обновляет элемент инфоблока: название, активность, сортировку, фото,
     * описание (HTML), даты активности и SEO-поля. Сбрасывает кеш компонента
     * и кеш SEO-параметров.
     *
     * @return array{success: bool, error?: string, detail?: string, name?: string, dates?: array}
     */
    public function saveActionAction(): array
    {
        if (!check_bitrix_sessid()) {
            return ['success' => false, 'error' => 'Ошибка сессии. Пожалуйста, обновите страницу.'];
        }

        if (!Loader::includeModule('iblock')) {
            return ['success' => false, 'error' => 'Модуль iblock не найден.'];
        }

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();

        $id = (int)$request->getPost('id');
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Не передан ID акции.'];
        }

        $detailHtml = $this->sanitizeDetailHtml((string)$request->getPost('detail'));

        $name = trim((string)$request->getPost('name'));
        if ($name === '') {
            return ['success' => false, 'error' => 'Название акции не может быть пустым.'];
        }

        $fields = [
            'NAME'             => $name,
            'ACTIVE'           => $request->getPost('active') === 'Y' ? 'Y' : 'N',
            'SORT'             => (int)$request->getPost('sort'),
            'DETAIL_TEXT'      => $detailHtml,
            'DETAIL_TEXT_TYPE' => 'html',
            'ACTIVE_FROM'      => $this->normalizeDateInput((string)$request->getPost('activeFrom')),
            'ACTIVE_TO'        => $this->normalizeDateInput((string)$request->getPost('activeTo')),
        ];

        // Загрузка фото (PREVIEW_PICTURE) — поле, которое выводится в списке.
        $picture = $request->getFile('picture');
        if (
            is_array($picture)
            && !empty($picture['tmp_name'])
            && (int)($picture['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
        ) {
            $fields['PREVIEW_PICTURE'] = $picture;
        }

        // SEO-данные элемента (inherited properties). Пустое значение —
        // отказ от собственного значения (наследуется с раздела/инфоблока).
        $seoFields = [];
        if ($request->getPost('seoTitle') !== null) {
            $seoFields['ELEMENT_META_TITLE'] = trim((string)$request->getPost('seoTitle'));
        }
        if ($request->getPost('seoDescription') !== null) {
            $seoFields['ELEMENT_META_DESCRIPTION'] = trim((string)$request->getPost('seoDescription'));
        }
        if (!empty($seoFields)) {
            $fields['IPROPERTY_TEMPLATES'] = $seoFields;
        }

        $element = new \CIBlockElement();
        if (!$element->Update($id, $fields)) {
            return [
                'success' => false,
                'error'   => $element->LAST_ERROR ?: 'Не удалось сохранить акцию.',
            ];
        }

        // Сброс кеша списка и SEO-параметров.
        BXClearCache(true, self::CACHE_DIR);
        $this->clearSeoCache($id);

        return [
            'success' => true,
            'detail'  => $detailHtml,
            'name'    => $name,
            'dates'   => [
                'from' => (string)$fields['ACTIVE_FROM'],
                'to'   => (string)$fields['ACTIVE_TO'],
            ],
        ];
    }

    /**
     * AJAX-действие: создание новой акции в инфоблоке.
     * Возвращает HTML карточки новой акции для вставки в список и её ID.
     *
     * @return array{success: bool, error?: string, html?: string, id?: int}
     */
    public function createActionAction(): array
    {
        if (!check_bitrix_sessid()) {
            return ['success' => false, 'error' => 'Ошибка сессии. Пожалуйста, обновите страницу.'];
        }

        if (!Loader::includeModule('iblock')) {
            return ['success' => false, 'error' => 'Модуль iblock не найден.'];
        }

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();

        $name = trim((string)$request->getPost('name'));
        if ($name === '') {
            return ['success' => false, 'error' => 'Введите название акции.'];
        }

        $detailHtml = $this->sanitizeDetailHtml((string)$request->getPost('detail'));

        $fields = [
            'IBLOCK_ID'        => self::IBLOCK_ID,
            'NAME'             => $name,
            'ACTIVE'           => $request->getPost('active') === 'N' ? 'N' : 'Y',
            'SORT'             => (int)$request->getPost('sort'),
            'DETAIL_TEXT'      => $detailHtml,
            'DETAIL_TEXT_TYPE' => 'html',
            'ACTIVE_FROM'      => $this->normalizeDateInput((string)$request->getPost('activeFrom')),
            'ACTIVE_TO'        => $this->normalizeDateInput((string)$request->getPost('activeTo')),
        ];

        // Символьный код (для SEF-адреса детальной страницы акции).
        $code = \CUtil::translit($name, 'ru', [
            'replace_space' => '-',
            'replace_other' => '-',
        ]);
        $code = trim((string)preg_replace('/-+/', '-', $code), '-');
        if ($code === '') {
            $code = 'action-' . time();
        }

        // Уникальность символьного кода.
        $exists = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_ID, 'CODE' => $code],
            false,
            false,
            ['ID']
        )->Fetch();
        if ($exists) {
            $code .= '-' . time();
        }
        $fields['CODE'] = $code;

        // Загрузка фото (PREVIEW_PICTURE).
        $picture = $request->getFile('picture');
        if (
            is_array($picture)
            && !empty($picture['tmp_name'])
            && (int)($picture['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
        ) {
            $fields['PREVIEW_PICTURE'] = $picture;
        }

        // SEO-данные элемента (meta title/description).
        $seoFields = [];
        if ($request->getPost('seoTitle') !== null) {
            $seoFields['ELEMENT_META_TITLE'] = trim((string)$request->getPost('seoTitle'));
        }
        if ($request->getPost('seoDescription') !== null) {
            $seoFields['ELEMENT_META_DESCRIPTION'] = trim((string)$request->getPost('seoDescription'));
        }
        if (!empty($seoFields)) {
            $fields['IPROPERTY_TEMPLATES'] = $seoFields;
        }

        $element = new \CIBlockElement();
        $newId = (int)$element->Add($fields);
        if ($newId <= 0) {
            return [
                'success' => false,
                'error'   => $element->LAST_ERROR ?: 'Не удалось создать акцию.',
            ];
        }

        BXClearCache(true, self::CACHE_DIR);
        $this->clearSeoCache($newId);

        $item = $this->getActionById($newId);

        return [
            'success' => true,
            'id'      => $newId,
            'html'    => $item ? $this->renderItemHtml($item) : '',
        ];
    }

    /**
     * Сброс кеша SEO-параметров элемента (см. Prokhorov\Api\Helpers\Seo).
     *
     * @param int $elementId
     * @return void
     */
    private function clearSeoCache(int $elementId): void
    {
        try {
            \Bitrix\Main\Data\Cache::createInstance()->cleanDir('/prokhorov/api/seo/');

            \Bitrix\Main\Application::getInstance()
                ->getTaggedCache()
                ->clearByTag('element_' . $elementId);

            if (Loader::includeModule('iblock')) {
                (new \Bitrix\Iblock\InheritedProperty\ElementValues(self::IBLOCK_ID, $elementId))
                    ->clearValues();
            }
        } catch (\Throwable $e) {
            // Кеш не критичен — не прерываем сохранение.
        }
    }
}
