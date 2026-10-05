<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Engine\Contract\Controllerable;

Loader::includeModule('iblock');

// Модуль с таблицами маркетинга подключаем на уровне файла: AJAX-действия
// компонента (runComponentAction) выполняются без вызова executeComponent(),
// поэтому подключение внутри метода не срабатывало и выдавало "модуль не установлен".
if (!Loader::includeModule('ldo.marketing')
    && !class_exists('\\Ldo\\Marketing\\GiftsTable')) {
    $giftsTableFile = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/ldo.marketing/lib/GiftsTable.php';
    if (is_file($giftsTableFile)) {
        require_once $giftsTableFile;
    }
}
if (!class_exists('\\Ldo\\Marketing\\Settings')) {
    $marketingSettingsFile = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/ldo.marketing/lib/Settings.php';
    if (is_file($marketingSettingsFile)) {
        require_once $marketingSettingsFile;
    }
}

/**
 * Компонент "Уровни подарков к заказам" для партнёрского раздела
 * (таб "Подарки к заказу" на странице /partners/marketing/).
 *
 * Данные хранятся в собственной таблице модуля ldo.marketing
 * (ldo_marketing_gift_levels) через ORM-сущность GiftsTable.
 * Ранее использовался инфоблок подарков (ID = 8); функционал перенесён
 * на таблицу, интерфейс компонента (шаблон и JS) сохранён без изменений.
 *
 * Изменение данных выполняется AJAX-контроллерами компонента
 * (saveLevelAction / deleteLevelAction), поиск товаров — searchProductsAction.
 */
class GiftsList extends \CBitrixComponent implements Controllerable
{
    /** Код модуля, которому принадлежит таблица. */
    const MODULE_ID = 'ldo.marketing';

    /** Класс ORM-таблицы уровней подарков. */
    const TABLE_CLASS = '\\Ldo\\Marketing\\GiftsTable';

    /** ID инфоблока каталога (товары для привязки). */
    const CATALOG_IBLOCK_ID = 4;

    /** Сайт по умолчанию. */
    const DEFAULT_SITE_ID = 's1';

    /**
     * Основной вывод компонента.
     */
    public function executeComponent()
    {
        $moduleOk = $this->includeMarketingModule();

        $siteId = (string)Context::getCurrent()->getSite();
        if ($siteId === '') {
            $siteId = self::DEFAULT_SITE_ID;
        }

        $this->arResult['SESSID']  = bitrix_sessid();
        $this->arResult['SITE_ID'] = $siteId;
        $this->arResult['ERROR']   = $moduleOk ? '' : 'Модуль ldo.marketing не установлен. Установите модуль для работы раздела.';

        $levels = [];
        if ($moduleOk) {
            $class = self::TABLE_CLASS;
            $class::ensureTable();
            $rows = $class::getBySite($siteId);
            $levels = $this->prepareLevels($rows);
        }

        $this->arResult['LEVELS'] = $levels;

        $this->includeComponentTemplate();
    }

    /**
     * Подключение модуля ldo.marketing с фолбэком на прямое подключение
     * класса таблицы (если модуль не зарегистрирован, но файлы есть).
     *
     * @return bool
     */
    private function includeMarketingModule(): bool
    {
        if (Loader::includeModule(self::MODULE_ID) && class_exists(self::TABLE_CLASS)) {
            return true;
        }

        if (!class_exists(self::TABLE_CLASS)) {
            $file = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . self::MODULE_ID . '/lib/GiftsTable.php';
            if (is_file($file)) {
                require_once $file;
            }
        }

        return class_exists(self::TABLE_CLASS);
    }

    /**
     * Преобразование строк таблицы в структуру для шаблона:
     * декодирование JSON-поля товаров и обогащение их данными каталога.
     *
     * @param array $rows
     * @return array
     */
    private function prepareLevels(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $allProductIds = [];
        $decoded = [];
        foreach ($rows as $row) {
            $id = (int)$row['ID'];
            $productIds = self::decodeIds($row['PRODUCT_IDS'] ?? '');
            $decoded[$id] = $productIds;
            foreach ($productIds as $pid) {
                $allProductIds[$pid] = $pid;
            }
        }

        $productsMap = $this->getProductsInfo(array_values($allProductIds));

        $levels = [];
        foreach ($rows as $row) {
            $id = (int)$row['ID'];

            $products = [];
            foreach ($decoded[$id] as $pid) {
                $products[] = $productsMap[$pid] ?? [
                    'ID'      => $pid,
                    'NAME'    => '#' . $pid,
                    'PICTURE' => '',
                ];
            }

            $levels[] = [
                'ID'       => $id,
                'NAME'     => (string)$row['NAME'],
                'ACTIVE'   => ($row['ACTIVE'] ?? 'Y') === 'Y',
                'SORT'     => (int)$row['SORT'],
                'SUM'      => (int)$row['SUM'],
                'SITE_ID'  => (string)$row['SITE_ID'],
                'PRODUCTS' => $products,
            ];
        }

        return $levels;
    }

    /**
     * Данные товаров каталога (название + уменьшенное фото).
     *
     * @param array $ids
     * @return array id => ['ID','NAME','PICTURE']
     */
    private function getProductsInfo(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $result = [];
        $rs = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $this->getCatalogIblockId(), '=ID' => $ids],
            false,
            false,
            ['ID', 'NAME', 'PREVIEW_PICTURE']
        );

        while ($p = $rs->Fetch()) {
            $img = '';
            if ((int)$p['PREVIEW_PICTURE'] > 0) {
                $resized = \CFile::ResizeImageGet(
                    (int)$p['PREVIEW_PICTURE'],
                    ['width' => 80, 'height' => 80],
                    BX_RESIZE_IMAGE_PROPORTIONAL,
                    true
                );
                if (is_array($resized)) {
                    $img = (string)$resized['src'];
                }
            }

            $result[(int)$p['ID']] = [
                'ID'      => (int)$p['ID'],
                'NAME'    => (string)$p['NAME'],
                'PICTURE' => $img,
            ];
        }

        return $result;
    }

    /**
     * ID инфоблока каталога из настроек модуля
     * (используется для фото и данных товаров).
     *
     * @return int
     */
    private function getCatalogIblockId(): int
    {
        if (class_exists('\\Ldo\\Marketing\\Settings')) {
            return \Ldo\Marketing\Settings::getCatalogIblockId();
        }

        return self::CATALOG_IBLOCK_ID;
    }

    /**
     * Кодирование массива ID в JSON для хранения в БД.
     *
     * @param array $ids
     * @return string
     */
    private static function encodeIds(array $ids): string
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }

        return (string)json_encode(array_values($clean), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Декодирование JSON-массива ID из БД.
     *
     * @param mixed $raw
     * @return array
     */
    private static function decodeIds($raw): array
    {
        if (is_array($raw)) {
            $data = $raw;
        } else {
            $raw = trim((string)$raw);
            if ($raw === '') {
                return [];
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                return [];
            }
        }

        $ids = [];
        foreach ($data as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
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
            'saveLevel' => [
                'prefilters' => [],
            ],
            'deleteLevel' => [
                'prefilters' => [],
            ],
            'searchProducts' => [
                'prefilters' => [],
            ],
        ];
    }

    /**
     * AJAX-контроллер: создание/обновление уровня подарка.
     *
     * @return array{success: bool, id?: int, error?: string}
     */
    public function saveLevelAction(): array
    {
        if (!check_bitrix_sessid()) {
            return ['success' => false, 'error' => 'Сессия истекла. Обновите страницу.'];
        }

        global $USER;
        if (!$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Требуется авторизация.'];
        }

        if (!class_exists(self::TABLE_CLASS)) {
            return ['success' => false, 'error' => 'Модуль ldo.marketing не установлен.'];
        }

        $request = Context::getCurrent()->getRequest();

        $id   = (int)$request->getPost('id');
        $name = trim((string)$request->getPost('name'));
        if ($name === '') {
            return ['success' => false, 'error' => 'Введите название уровня.'];
        }

        $sum    = (int)$request->getPost('sum');
        $sort   = (int)$request->getPost('sort');
        $active = $request->getPost('active') === 'Y' ? 'Y' : 'N';

        $productIds = $this->readIntArray($request->getPost('product_ids'));

        $siteId = trim((string)$request->getPost('site_id'));
        if ($siteId === '') {
            $siteId = (string)Context::getCurrent()->getSite();
        }
        if ($siteId === '') {
            $siteId = self::DEFAULT_SITE_ID;
        }
        $siteId = mb_substr($siteId, 0, 2);

        $data = [
            'NAME'        => $name,
            'ACTIVE'      => $active,
            'SORT'        => $sort,
            'SUM'         => $sum,
            'PRODUCT_IDS' => self::encodeIds($productIds),
            'SITE_ID'     => $siteId,
        ];

        $class = self::TABLE_CLASS;

        try {
            if ($id > 0) {
                $result = $class::update($id, $data);
            } else {
                $result = $class::add($data);
                if ($result->isSuccess()) {
                    $id = (int)$result->getId();
                }
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Ошибка сохранения: ' . $e->getMessage()];
        }

        if (!$result->isSuccess()) {
            $errors = $result->getErrorMessages();
            return [
                'success' => false,
                'error'   => !empty($errors) ? implode('; ', $errors) : 'Не удалось сохранить уровень.',
            ];
        }

        return ['success' => true, 'id' => $id];
    }

    /**
     * AJAX-контроллер: удаление уровня подарка.
     *
     * @return array{success: bool, error?: string}
     */
    public function deleteLevelAction(): array
    {
        if (!check_bitrix_sessid()) {
            return ['success' => false, 'error' => 'Сессия истекла. Обновите страницу.'];
        }

        global $USER;
        if (!$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Требуется авторизация.'];
        }

        if (!class_exists(self::TABLE_CLASS)) {
            return ['success' => false, 'error' => 'Модуль ldo.marketing не установлен.'];
        }

        $request = Context::getCurrent()->getRequest();
        $id = (int)$request->getPost('id');
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Неверный идентификатор уровня.'];
        }

        $class = self::TABLE_CLASS;

        try {
            $result = $class::delete($id);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Ошибка удаления: ' . $e->getMessage()];
        }

        if (!$result->isSuccess()) {
            return ['success' => false, 'error' => 'Не удалось удалить уровень.'];
        }

        return ['success' => true];
    }

    /**
     * AJAX-контроллер: поиск товаров каталога по названию (для выбора в уровне).
     *
     * @return array{success: bool, items?: array, error?: string}
     */
    public function searchProductsAction(): array
    {
        if (!check_bitrix_sessid()) {
            return ['success' => false, 'error' => 'Сессия истекла. Обновите страницу.'];
        }

        global $USER;
        if (!$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Требуется авторизация.'];
        }

        $request = Context::getCurrent()->getRequest();
        $q = trim((string)$request->getPost('q'));
        $q = mb_substr($q, 0, 100);

        if (mb_strlen($q) < 2) {
            return ['success' => true, 'items' => []];
        }

        $items = [];
        $rs = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->getCatalogIblockId(),
                'ACTIVE'    => 'Y',
                '%NAME'     => $q,
            ],
            false,
            ['nTopCount' => 20],
            ['ID', 'NAME']
        );

        while ($p = $rs->Fetch()) {
            $items[] = [
                'id'   => (int)$p['ID'],
                'name' => (string)$p['NAME'],
            ];
        }

        return ['success' => true, 'items' => $items];
    }

    /**
     * Приведение значения POST к массиву целых положительных ID.
     *
     * @param mixed $value
     * @return array
     */
    private function readIntArray($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $v) {
            $v = (int)$v;
            if ($v > 0) {
                $ids[$v] = $v;
            }
        }

        return array_values($ids);
    }
}
