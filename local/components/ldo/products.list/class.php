<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;

Loader::includeModule("iblock");

class ProductsList extends \CBitrixComponent implements Controllerable
{
    /**
     * ID инфоблока, из которого выводятся товары (см. Ldo\Iiko\Product::IBLOCK_ID).
     */
    const IBLOCK_ID = 4;

    /**
     * Тип цены, используемый для вывода/сохранения (см. Ldo\Iiko\Product::addPrice).
     */
    const PRICE_TYPE_ID = 1;

    /**
     * Папка кеша компонента (общая для основного кеша и кеша AJAX-подгрузки).
     */
    const CACHE_DIR = '/products_list';

    /**
     * Размер страницы по умолчанию (кол-во товаров на "страницу").
     */
    const DEFAULT_PAGE_SIZE = 30;

    /**
     * Время жизни кеша по умолчанию (1 час).
     */
    const DEFAULT_CACHE_TIME = 3600000;

    /**
     * Кеш разделов инфоблока (id => NAME), заполняется один раз.
     *
     * @var array|null
     */
    private $sectionsMap = null;

    /**
     * Подготовка параметров компонента.
     *
     * @param array $arParams
     * @return array
     */
    public function onPrepareComponentParams($arParams)
    {
        $arParams['PAGE_SIZE'] = (int)($arParams['PAGE_SIZE'] ?? 0);
        if ($arParams['PAGE_SIZE'] <= 0) {
            $arParams['PAGE_SIZE'] = self::DEFAULT_PAGE_SIZE;
        }

        $arParams['SORT_FIELD'] = (string)($arParams['SORT_FIELD'] ?? '');
        if (!in_array($arParams['SORT_FIELD'], ['SORT', 'NAME', 'ID'], true)) {
            $arParams['SORT_FIELD'] = 'SORT';
        }

        $arParams['SORT_ORDER'] = (string)($arParams['SORT_ORDER'] ?? '');
        if ($arParams['SORT_ORDER'] !== 'DESC') {
            $arParams['SORT_ORDER'] = 'ASC';
        }

        $arParams['CACHE_TIME'] = (int)($arParams['CACHE_TIME'] ?? 0);
        if ($arParams['CACHE_TIME'] <= 0) {
            $arParams['CACHE_TIME'] = self::DEFAULT_CACHE_TIME;
        }

        return $arParams;
    }

    /**
     * Основной вывод компонента. Результат кешируется стандартным
     * механизмом (StartResultCache) в папку CACHE_DIR.
     */
    public function executeComponent()
    {
        $cacheId = $this->getCacheKey();

        if ($this->StartResultCache($this->arParams['CACHE_TIME'], $cacheId, self::CACHE_DIR)) {
            $this->arResult['ITEMS'] = $this->getProducts(0, $this->arParams['PAGE_SIZE']);
            $this->arResult['SECTIONS'] = $this->getSectionsMap();
            $this->arResult['TOTAL'] = $this->getTotalCount();
            $this->arResult['PAGE_SIZE'] = $this->arParams['PAGE_SIZE'];
            $this->arResult['HAS_MORE'] = $this->arResult['TOTAL'] > $this->arParams['PAGE_SIZE'];
            $this->arResult['SORT_FIELD'] = $this->arParams['SORT_FIELD'];
            $this->arResult['SORT_ORDER'] = $this->arParams['SORT_ORDER'];

            $this->EndResultCache();
        }

        $this->includeComponentTemplate();
    }

    /**
     * Идентификатор кеша: зависит от параметров вывода.
     * Название отличается от родительского getCacheId(), чтобы не перекрывать его.
     *
     * @return string
     */
    private function getCacheKey(): string
    {
        // 'v3' — версия: перезаписываем кеш после перехода на выборку
        // только активных разделов.
        return implode('|', [
            'v3',
            self::IBLOCK_ID,
            $this->arParams['PAGE_SIZE'],
            $this->arParams['SORT_FIELD'],
            $this->arParams['SORT_ORDER'],
        ]);
    }

    /**
     * Получить список товаров с постраничной навигацией (offset/limit),
     * с учётом фильтра по разделу (категории) и поиска по названию.
     * Для каждого товара подтягиваются фото, раздел, цена, кол-во и вес.
     *
     * @param int $offset
     * @param int $limit
     * @param int $sectionId
     * @param string $search
     * @return array
     */
    private function getProducts(int $offset, int $limit, int $sectionId = 0, string $search = ''): array
    {
        if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
            return [];
        }

        // Постраничная навигация: nStart не поддерживается корректно,
        // используем iNumPage (номер страницы) + nPageSize.
        $pageNum = intdiv($offset, $limit) + 1;

        $filter = ['IBLOCK_ID' => self::IBLOCK_ID];

        // Выводим только товары активных разделов (см. getSectionsMap).
        // Если выбран конкретный раздел — фильтруем по нему (он активен, т.к.
        // в выпадающем списке только активные), иначе — по всем активным разделам.
        if ($sectionId > 0) {
            $filter['SECTION_ID'] = $sectionId;
            $filter['INCLUDE_SUBSECTIONS'] = 'Y';
        } else {
            $activeSections = array_keys($this->getSectionsMap());
            if (empty($activeSections)) {
                return [];
            }
            $filter['SECTION_ID'] = $activeSections;
            $filter['INCLUDE_SUBSECTIONS'] = 'Y';
        }
        if ($search !== '') {
            $filter['?NAME'] = $search;
        }

        $rs = \CIBlockElement::GetList(
            [
                $this->arParams['SORT_FIELD'] => $this->arParams['SORT_ORDER'],
                'ID'                          => 'ASC',
            ],
            $filter,
            false,
            [
                'iNumPage'  => $pageNum,
                'nPageSize' => $limit,
            ],
            [
                'ID',
                'NAME',
                'ACTIVE',
                'SORT',
                'IBLOCK_SECTION_ID',
                'PREVIEW_PICTURE',
                'DETAIL_PICTURE',
                'DETAIL_PAGE_URL',
                'DETAIL_TEXT',
                'PROPERTY_ATT_KALLORY',
                'PROPERTY_ATT_BELKI',
                'PROPERTY_ATT_GIRY',
                'PROPERTY_ATT_YGLEVODY',
            ]
        );

        $items = [];
        $ids = [];

        while ($row = $rs->Fetch()) {
            $items[(int)$row['ID']] = $row;
            $ids[] = (int)$row['ID'];
        }

        if (empty($items)) {
            return [];
        }

        $prices = $this->getPricesMap($ids);
        $catalog = $this->getCatalogMap($ids);
        $sections = $this->getSectionsMap();

        foreach ($items as &$item) {
            $id = (int)$item['ID'];
            $item['PRICE']        = $prices[$id] ?? 0;
            $item['QUANTITY']     = $catalog[$id]['QUANTITY'] ?? 0;
            $item['WEIGHT']       = $catalog[$id]['WEIGHT'] ?? 0;
            $item['PICTURE_SRC']  = $this->getPictureSrc($item);
            $item['SECTION_NAME'] = $sections[(int)$item['IBLOCK_SECTION_ID']] ?? '';
            $item['DETAIL_TEXT']  = (string)($item['DETAIL_TEXT'] ?? '');

            // Свойства БЖУ/калорий (см. Ldo\Rkeeper\Product)
            $item['ATT_KALLORY']  = (string)($item['PROPERTY_ATT_KALLORY_VALUE'] ?? '');
            $item['ATT_BELKI']    = (string)($item['PROPERTY_ATT_BELKI_VALUE'] ?? '');
            $item['ATT_GIRY']     = (string)($item['PROPERTY_ATT_GIRY_VALUE'] ?? '');
            $item['ATT_YGLEVODY'] = (string)($item['PROPERTY_ATT_YGLEVODY_VALUE'] ?? '');
        }
        unset($item);

        return array_values($items);
    }

    /**
     * Общее количество товаров с учётом фильтра по разделу и поиска.
     *
     * @param int $sectionId
     * @param string $search
     * @return int
     */
    private function getTotalCount(int $sectionId = 0, string $search = ''): int
    {
        if (!Loader::includeModule('iblock')) {
            return 0;
        }

        $filter = ['IBLOCK_ID' => self::IBLOCK_ID];

        // Только товары активных разделов (см. getSectionsMap)
        if ($sectionId > 0) {
            $filter['SECTION_ID'] = $sectionId;
            $filter['INCLUDE_SUBSECTIONS'] = 'Y';
        } else {
            $activeSections = array_keys($this->getSectionsMap());
            if (empty($activeSections)) {
                return 0;
            }
            $filter['SECTION_ID'] = $activeSections;
            $filter['INCLUDE_SUBSECTIONS'] = 'Y';
        }
        if ($search !== '') {
            $filter['?NAME'] = $search;
        }

        $rs = \CIBlockElement::GetList(
            [],
            $filter,
            false,
            false,
            ['ID']
        );

        return (int)$rs->SelectedRowsCount();
    }

    /**
     * Карта цен: productId => PRICE (базовая цена, минимальный тип цены).
     *
     * @param array $ids
     * @return array
     */
    private function getPricesMap(array $ids): array
    {
        $map = [];
        if (empty($ids)) {
            return $map;
        }

        $rs = \CPrice::GetList(
            ['CATALOG_GROUP_ID' => 'ASC'],
            ['PRODUCT_ID' => $ids]
        );

        while ($row = $rs->Fetch()) {
            $productId = (int)$row['PRODUCT_ID'];
            if (!isset($map[$productId])) {
                $map[$productId] = (float)$row['PRICE'];
            }
        }

        return $map;
    }

    /**
     * Карта торгового каталога: productId => [QUANTITY, WEIGHT].
     *
     * @param array $ids
     * @return array
     */
    private function getCatalogMap(array $ids): array
    {
        $map = [];
        foreach ($ids as $id) {
            $row = \CCatalogProduct::GetByID((int)$id);
            $map[(int)$id] = [
                'QUANTITY' => (float)($row['QUANTITY'] ?? 0),
                'WEIGHT'   => (float)($row['WEIGHT'] ?? 0),
            ];
        }

        return $map;
    }

    /**
     * Получить src миниатюры товара (PREVIEW_PICTURE -> DETAIL_PICTURE).
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
            ['width' => 100, 'height' => 100],
            BX_RESIZE_IMAGE_PROPORTIONAL,
            true
        );

        return is_array($resized) ? (string)($resized['src'] ?? '') : '';
    }

    /**
     * Карта разделов инфоблока (id => NAME). Заполняется один раз за запрос.
     *
     * @return array
     */
    private function getSectionsMap(): array
    {
        if ($this->sectionsMap !== null) {
            return $this->sectionsMap;
        }

        $this->sectionsMap = [];

        if (!Loader::includeModule('iblock')) {
            return $this->sectionsMap;
        }

        // Только активные разделы (используется и для фильтра, и для select в карточке)
        $rs = \CIBlockSection::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            ['IBLOCK_ID' => self::IBLOCK_ID, 'ACTIVE' => 'Y'],
            false,
            ['ID', 'NAME']
        );

        while ($row = $rs->Fetch()) {
            $this->sectionsMap[(int)$row['ID']] = (string)$row['NAME'];
        }

        return $this->sectionsMap;
    }

    /**
     * Разметка одной карточки товара. Используется и в шаблоне, и при
     * AJAX-подгрузке (getMoreAction), чтобы не дублировать HTML.
     *
     * @param array $item
     * @return string
     */
    public function renderItemHtml(array $item): string
    {
        $id = (int)$item['ID'];
        $name = htmlspecialcharsbx((string)($item['NAME'] ?? ''));
        $active = ($item['ACTIVE'] ?? 'Y') === 'Y';
        $sectionId = (int)($item['IBLOCK_SECTION_ID'] ?? 0);
        $sort = (int)($item['SORT'] ?? 0);
        $price = (float)($item['PRICE'] ?? 0);
        $quantity = (float)($item['QUANTITY'] ?? 0);
        $weight = (float)($item['WEIGHT'] ?? 0);
        $sectionName = htmlspecialcharsbx((string)($item['SECTION_NAME'] ?? ''));

        $img = '';
        if (!empty($item['PICTURE_SRC'])) {
            $img = '<img class="product-item__img" src="' . htmlspecialcharsbx($item['PICTURE_SRC']) . '" alt="' . $name . '" loading="lazy">';
        } else {
            $img = '<div class="product-item__img product-item__img--empty"></div>';
        }

        $sectionsHtml = '<option value="0">— Без раздела —</option>';
        foreach ($this->getSectionsMap() as $sid => $sname) {
            $selected = ($sid === $sectionId) ? ' selected' : '';
            $sectionsHtml .= '<option value="' . $sid . '"' . $selected . '>' . htmlspecialcharsbx($sname) . '</option>';
        }

        $checkAttr = $active ? ' checked' : '';

        $detailText = htmlspecialcharsbx((string)($item['DETAIL_TEXT'] ?? ''), true);
        $kallory = htmlspecialcharsbx((string)($item['ATT_KALLORY'] ?? ''));
        $belki = htmlspecialcharsbx((string)($item['ATT_BELKI'] ?? ''));
        $giry = htmlspecialcharsbx((string)($item['ATT_GIRY'] ?? ''));
        $yglevody = htmlspecialcharsbx((string)($item['ATT_YGLEVODY'] ?? ''));

        // Текущее детальное фото (для предпросмотра при загрузке нового)
        $detailSrc = '';
        if (!empty($item['DETAIL_PICTURE'])) {
            $detailSrc = \CFile::GetPath((int)$item['DETAIL_PICTURE']);
        }

        return '
        <div class="product-item" data-id="' . $id . '">
            <div class="product-item__head">
                <div class="product-item__img-wrap">' . $img . '</div>
                <div class="product-item__name" title="' . $name . '">' . $name . '</div>
            </div>

            <div class="product-item__main-fields">
                <label class="product-field product-field--active" title="Активность">
                    <span class="product-field__label">Активность</span>
                    <span class="toggle-switch">
                        <input type="checkbox" class="product-active"' . $checkAttr . '>
                        <span class="toggle-switch__slider"></span>
                    </span>
                </label>

                <label class="product-field">
                    <span class="product-field__label">Цена, ₽</span>
                    <input type="number" step="0.01" class="product-price" value="' . number_format($price, 2, '.', '') . '" disabled>
                </label>

                <label class="product-field">
                    <span class="product-field__label">Сортировка</span>
                    <input type="number" class="product-sort" value="' . $sort . '" disabled>
                </label>
            </div>

            <div class="product-extra" style="display:none;">
                <label class="product-field">
                    <span class="product-field__label">Раздел</span>
                    <select class="product-section" disabled>
                        ' . $sectionsHtml . '
                    </select>
                </label>

                <label class="product-field">
                    <span class="product-field__label">Кол-во</span>
                    <input type="number" step="0.001" class="product-quantity" value="' . number_format($quantity, 3, '.', '') . '" disabled>
                </label>

                <label class="product-field">
                    <span class="product-field__label">Вес, г</span>
                    <input type="number" step="1" class="product-weight" value="' . $weight . '" disabled>
                </label>

                <label class="product-field product-field--full">
                    <span class="product-field__label">Детальное описание</span>
                    <textarea class="product-detail" rows="4" disabled>' . $detailText . '</textarea>
                </label>

                <label class="product-field">
                    <span class="product-field__label">Калории</span>
                    <input type="number" step="0.01" class="product-kallory" value="' . $kallory . '" disabled>
                </label>

                <label class="product-field">
                    <span class="product-field__label">Белки</span>
                    <input type="number" step="0.01" class="product-belki" value="' . $belki . '" disabled>
                </label>

                <label class="product-field">
                    <span class="product-field__label">Жиры</span>
                    <input type="number" step="0.01" class="product-giry" value="' . $giry . '" disabled>
                </label>

                <label class="product-field">
                    <span class="product-field__label">Углеводы</span>
                    <input type="number" step="0.01" class="product-yglevody" value="' . $yglevody . '" disabled>
                </label>

                <label class="product-field product-field--full">
                    <span class="product-field__label">Детальное фото</span>
                    <span class="product-photo-block">
                        ' . ($detailSrc ? '<img class="product-detail-photo" src="' . htmlspecialcharsbx($detailSrc) . '" alt="">' : '') . '
                        <input type="file" class="product-detail-picture" accept="image/*" disabled>
                    </span>
                </label>
            </div>

            <div class="product-item__actions">
                <button type="button" class="product-btn product-btn--delete" data-action="delete" title="Удалить товар">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                </button>
                <button type="button" class="product-btn product-btn--edit" data-action="edit">Изменить</button>
                <button type="button" class="product-btn product-btn--save" data-action="save" style="display:none;">Сохранить</button>
                <button type="button" class="product-btn product-btn--cancel" data-action="cancel" style="display:none;">Отмена</button>
            </div>
            <span class="product-item__section-name" title="Раздел: ' . $sectionName . '">' . $sectionName . '</span>
        </div>';
    }

    /**
     * Конфигурация AJAX-действий.
     * Действия перечислены явно с пустыми префильтрами — иначе Битрикс
     * добавляет к POST-действиям CSRF-фильтр по умолчанию (как в opensource:order).
     * Защита saveProduct от CSRF выполняется вручную через check_bitrix_sessid().
     *
     * @return array
     */
    public function configureActions()
    {
        return [
            'getMore' => [
                'prefilters' => [],
            ],
            'saveProduct' => [
                'prefilters' => [],
            ],
            'deleteProduct' => [
                'prefilters' => [],
            ],
            'toggleActive' => [
                'prefilters' => [],
            ],
        ];
    }

    /**
     * AJAX-действие: подгрузка следующей страницы товаров.
     * Результат кешируется в папку CACHE_DIR.
     *
     * @return array{html: string, hasMore: bool}
     */
    public function getMoreAction(): array
    {
        $request = \Bitrix\Main\Context::getCurrent()->getRequest();

        $page = (int)$request->getPost('page');
        if ($page < 1) {
            $page = 1;
        }

        $pageSize = (int)$request->getPost('pageSize');
        if ($pageSize <= 0) {
            $pageSize = self::DEFAULT_PAGE_SIZE;
        }

        $sortField = (string)$request->getPost('sortField');
        if (!in_array($sortField, ['SORT', 'NAME', 'ID'], true)) {
            $sortField = 'SORT';
        }

        $sortOrder = (string)$request->getPost('sortOrder');
        if ($sortOrder !== 'DESC') {
            $sortOrder = 'ASC';
        }

        // Фильтр по категории (разделу)
        $sectionId = (int)$request->getPost('sectionId');
        if ($sectionId < 0) {
            $sectionId = 0;
        }

        // Поиск по названию
        $search = trim((string)$request->getPost('q'));
        $search = substr($search, 0, 100);

        // Временные параметры для getProducts()
        $this->arParams['PAGE_SIZE'] = $pageSize;
        $this->arParams['SORT_FIELD'] = $sortField;
        $this->arParams['SORT_ORDER'] = $sortOrder;

        $cache = Cache::createInstance();
        // 'v5' — версия ключа: учитывает фильтр категории, поиск и выборку
        // только активных разделов.
        $cacheId = implode('|', [
            'more',
            'v5',
            $page,
            $pageSize,
            $sortField,
            $sortOrder,
            $sectionId,
            $search,
            self::IBLOCK_ID,
        ]);

        if ($cache->startDataCache(self::DEFAULT_CACHE_TIME, $cacheId, self::CACHE_DIR)) {
            try {
                $items = $this->getProducts(($page - 1) * $pageSize, $pageSize, $sectionId, $search);

                $html = '';
                foreach ($items as $item) {
                    $html .= $this->renderItemHtml($item);
                }

                $total = $this->getTotalCount($sectionId, $search);
                $hasMore = ($page * $pageSize) < $total;

                $data = [
                    'html'    => $html,
                    'hasMore' => $hasMore,
                ];

                $cache->endDataCache($data);
            } catch (\Throwable $e) {
                // Не оставляем "замок" на кеше, если формирование данных упало
                $cache->abortDataCache();
                throw $e;
            }
        } else {
            $data = $cache->getVars();
        }

        return $data;
    }

    /**
     * AJAX-действие: сохранение товара (типовой контроллер).
     * Обновляет элемент инфоблока, кол-во/вес в каталоге и цену.
     * После сохранения сбрасывается кеш компонента.
     *
     * @return array{success: bool, error?: string}
     */
    public function saveProductAction(): array
    {
        if (!check_bitrix_sessid()) {
            return [
                'success' => false,
                'error'   => 'Ошибка сессии. Пожалуйста, обновите страницу.',
            ];
        }

        if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
            return [
                'success' => false,
                'error'   => 'Модуль iblock или catalog не найден.',
            ];
        }

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();

        $id = (int)$request->getPost('id');
        if ($id <= 0) {
            return [
                'success' => false,
                'error'   => 'Не передан ID товара.',
            ];
        }

        // 1. Элемент инфоблока
        $fields = [
            'ACTIVE'            => $request->getPost('active') === 'Y' ? 'Y' : 'N',
            'IBLOCK_SECTION_ID' => (int)$request->getPost('sectionId'),
            'SORT'              => (int)$request->getPost('sort'),
            'DETAIL_TEXT'       => (string)$request->getPost('detail'),
            'PROPERTY_VALUES'   => [
                'ATT_KALLORY'  => (float)$request->getPost('kallory'),
                'ATT_BELKI'    => (float)$request->getPost('belki'),
                'ATT_GIRY'     => (float)$request->getPost('giry'),
                'ATT_YGLEVODY' => (float)$request->getPost('yglevody'),
            ],
        ];

        // Загрузка детального фото (DETAIL_PICTURE)
        $detailPicture = $request->getFile('detailPicture');
        if (
            is_array($detailPicture)
            && !empty($detailPicture['tmp_name'])
            && (int)($detailPicture['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
        ) {
            $fields['DETAIL_PICTURE'] = $detailPicture;
        }

        $element = new \CIBlockElement();
        if (!$element->Update($id, $fields)) {
            return [
                'success' => false,
                'error'   => $element->LAST_ERROR ?: 'Не удалось обновить товар.',
            ];
        }

        // 2. Кол-во и вес в торговом каталоге
        $quantity = (float)$request->getPost('quantity');
        $weight = (float)$request->getPost('weight');

        $catalogFields = [
            'QUANTITY' => $quantity,
            'WEIGHT'   => $weight,
        ];

        $catalogRow = \CCatalogProduct::GetByID($id);
        if ($catalogRow) {
            \CCatalogProduct::Update($id, $catalogFields);
        } else {
            $catalogFields['ID'] = $id;
            \CCatalogProduct::Add($catalogFields);
        }

        // 3. Цена
        $price = (float)$request->getPost('price');
        $this->setPrice($id, $price);

        // 4. Сброс кеша компонента
        BXClearCache(true, self::CACHE_DIR);

        return [
            'success' => true,
        ];
    }

    /**
     * AJAX-действие: удаление товара.
     * После удаления сбрасывается кеш компонента.
     *
     * @return array{success: bool, error?: string}
     */
    public function deleteProductAction(): array
    {
        if (!check_bitrix_sessid()) {
            return [
                'success' => false,
                'error'   => 'Ошибка сессии. Пожалуйста, обновите страницу.',
            ];
        }

        if (!Loader::includeModule('iblock')) {
            return [
                'success' => false,
                'error'   => 'Модуль iblock не найден.',
            ];
        }

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();

        $id = (int)$request->getPost('id');
        if ($id <= 0) {
            return [
                'success' => false,
                'error'   => 'Не передан ID товара.',
            ];
        }

        $element = new \CIBlockElement();
        if (!$element->Delete($id)) {
            return [
                'success' => false,
                'error'   => $element->LAST_ERROR ?: 'Не удалось удалить товар.',
            ];
        }

        BXClearCache(true, self::CACHE_DIR);

        return [
            'success' => true,
        ];
    }

    /**
     * AJAX-действие: быстрое переключение активности товара
     * (работает без режима редактирования, прямо из списка).
     *
     * @return array{success: bool, error?: string}
     */
    public function toggleActiveAction(): array
    {
        if (!check_bitrix_sessid()) {
            return [
                'success' => false,
                'error'   => 'Ошибка сессии. Пожалуйста, обновите страницу.',
            ];
        }

        if (!Loader::includeModule('iblock')) {
            return [
                'success' => false,
                'error'   => 'Модуль iblock не найден.',
            ];
        }

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();

        $id = (int)$request->getPost('id');
        if ($id <= 0) {
            return [
                'success' => false,
                'error'   => 'Не передан ID товара.',
            ];
        }

        $active = $request->getPost('active') === 'Y' ? 'Y' : 'N';

        $element = new \CIBlockElement();
        if (!$element->Update($id, ['ACTIVE' => $active])) {
            return [
                'success' => false,
                'error'   => $element->LAST_ERROR ?: 'Не удалось обновить активность товара.',
            ];
        }

        BXClearCache(true, self::CACHE_DIR);

        return [
            'success' => true,
        ];
    }

    /**
     * Добавить или обновить цену товара (тип цены PRICE_TYPE_ID, валюта RUB).
     *
     * @param int $id
     * @param float $price
     * @return void
     */
    private function setPrice(int $id, float $price): void
    {
        $arFields = [
            'PRODUCT_ID'       => $id,
            'CATALOG_GROUP_ID' => self::PRICE_TYPE_ID,
            'PRICE'            => $price,
            'CURRENCY'         => 'RUB',
        ];

        $rs = \CPrice::GetList(
            [],
            ['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => self::PRICE_TYPE_ID]
        );

        if ($row = $rs->Fetch()) {
            \CPrice::Update((int)$row['ID'], $arFields);
        } else {
            \CPrice::Add($arFields);
        }
    }
}
