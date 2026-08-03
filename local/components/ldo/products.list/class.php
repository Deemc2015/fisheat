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
        return implode('|', [
            self::IBLOCK_ID,
            $this->arParams['PAGE_SIZE'],
            $this->arParams['SORT_FIELD'],
            $this->arParams['SORT_ORDER'],
        ]);
    }

    /**
     * Получить список товаров с постраничной навигацией (offset/limit).
     * Для каждого товара подтягиваются фото, раздел, цена, кол-во и вес.
     *
     * @param int $offset
     * @param int $limit
     * @return array
     */
    private function getProducts(int $offset, int $limit): array
    {
        if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
            return [];
        }

        // Постраничная навигация: nStart не поддерживается корректно,
        // используем iNumPage (номер страницы) + nPageSize.
        $pageNum = intdiv($offset, $limit) + 1;

        $rs = \CIBlockElement::GetList(
            [
                $this->arParams['SORT_FIELD'] => $this->arParams['SORT_ORDER'],
                'ID'                          => 'ASC',
            ],
            [
                'IBLOCK_ID' => self::IBLOCK_ID,
            ],
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
        }
        unset($item);

        return array_values($items);
    }

    /**
     * Общее количество товаров в инфоблоке.
     *
     * @return int
     */
    private function getTotalCount(): int
    {
        if (!Loader::includeModule('iblock')) {
            return 0;
        }

        try {
            $count = \Bitrix\Iblock\ElementTable::getCount(['=IBLOCK_ID' => self::IBLOCK_ID]);
            return (int)$count;
        } catch (\Throwable $e) {
            // Фолбэк на классический API, если ORM недоступен
            $rs = \CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => self::IBLOCK_ID],
                'IBLOCK_ID',
                false,
                ['ID']
            );

            $row = $rs->Fetch();

            return (int)($row['CNT'] ?? 0);
        }
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

        $rs = \CIBlockSection::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            ['IBLOCK_ID' => self::IBLOCK_ID],
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

        return '
        <div class="product-item" data-id="' . $id . '">
            <div class="product-item__img-wrap">' . $img . '</div>
            <div class="product-item__name" title="' . $name . '">' . $name . '</div>

            <label class="toggle-switch product-active-wrap" title="Активность">
                <input type="checkbox" class="product-active"' . $checkAttr . ' disabled>
                <span class="toggle-switch__slider"></span>
            </label>

            <select class="product-section" disabled>
                ' . $sectionsHtml . '
            </select>

            <input type="number" class="product-sort" value="' . $sort . '" disabled title="Сортировка">
            <input type="number" step="0.01" class="product-price" value="' . number_format($price, 2, '.', '') . '" disabled title="Цена, руб.">
            <input type="number" step="0.001" class="product-quantity" value="' . number_format($quantity, 3, '.', '') . '" disabled title="Кол-во">
            <input type="number" step="1" class="product-weight" value="' . $weight . '" disabled title="Вес, г">

            <div class="product-item__actions">
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

        // Временные параметры для getProducts()
        $this->arParams['PAGE_SIZE'] = $pageSize;
        $this->arParams['SORT_FIELD'] = $sortField;
        $this->arParams['SORT_ORDER'] = $sortOrder;

        // ДИАГНОСТИКА: что реально приходит в POST при клике "Показать ещё"
        addMessage2Log('products.getMore: page=' . var_export($request->getPost('page'), true)
            . ' pageSize=' . var_export($request->getPost('pageSize'), true)
            . ' sortField=' . var_export($request->getPost('sortField'), true)
            . ' sortOrder=' . var_export($request->getPost('sortOrder'), true));

        $cache = Cache::createInstance();
        // 'v2' — версия ключа: не даём отдавать старый кеш,
        // записанный до исправления навигации (iNumPage).
        $cacheId = implode('|', [
            'more',
            'v2',
            $page,
            $pageSize,
            $sortField,
            $sortOrder,
            self::IBLOCK_ID,
        ]);

        if ($cache->startDataCache(self::DEFAULT_CACHE_TIME, $cacheId, self::CACHE_DIR)) {
            try {
                $items = $this->getProducts(($page - 1) * $pageSize, $pageSize);

                $html = '';
                foreach ($items as $item) {
                    $html .= $this->renderItemHtml($item);
                }

                $total = $this->getTotalCount();
                $hasMore = ($page * $pageSize) < $total;

                // ДИАГНОСТИКА: итоги страницы
                addMessage2Log('products.getMore: page=' . $page . ' pageSize=' . $pageSize
                    . ' items=' . count($items) . ' total=' . $total . ' hasMore=' . var_export($hasMore, true));

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
            'ACTIVE'             => $request->getPost('active') === 'Y' ? 'Y' : 'N',
            'IBLOCK_SECTION_ID'  => (int)$request->getPost('sectionId'),
            'SORT'               => (int)$request->getPost('sort'),
        ];

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
