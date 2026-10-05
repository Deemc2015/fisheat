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
    && !class_exists('\\Ldo\\Marketing\\FreePositionsTable')) {
    $freePositionsTableFile = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/ldo.marketing/lib/FreePositionsTable.php';
    if (is_file($freePositionsTableFile)) {
        require_once $freePositionsTableFile;
    }
}
if (!class_exists('\\Ldo\\Marketing\\Settings')) {
    $marketingSettingsFile = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/ldo.marketing/lib/Settings.php';
    if (is_file($marketingSettingsFile)) {
        require_once $marketingSettingsFile;
    }
}

/**
 * Компонент "Бесплатные позиции к товарам".
 *
 * ID инфоблока каталога берётся из настроек модуля ldo.marketing (Settings)
 * для подтягивания разделов, фото и данных товаров.
 */
class FreePositionsList extends \CBitrixComponent implements Controllerable
{
    const MODULE_ID = 'ldo.marketing';
    const TABLE_CLASS = '\\Ldo\\Marketing\\FreePositionsTable';
    const CATALOG_IBLOCK_ID = 4;
    const DEFAULT_SITE_ID = 's1';

    public function executeComponent()
    {
        $moduleOk = $this->includeMarketingModule();

        $siteId = (string)Context::getCurrent()->getSite();
        if ($siteId === '') {
            $siteId = self::DEFAULT_SITE_ID;
        }

        $this->arResult['SITE_ID']  = $siteId;
        $this->arResult['SESSID']   = bitrix_sessid();
        $this->arResult['SECTIONS'] = $this->getSections();
        $this->arResult['SITES']    = $this->getSites();
        $this->arResult['ERROR']    = $moduleOk ? '' : 'Модуль ldo.marketing не установлен. Установите модуль для работы раздела.';

        $items = [];
        if ($moduleOk) {
            $class = self::TABLE_CLASS;
            $class::ensureTable();
            $rows = $class::getBySite($siteId);
            $items = $this->prepareItems($rows);
        }

        $this->arResult['ITEMS'] = $items;

        $this->includeComponentTemplate();
    }

    private function includeMarketingModule(): bool
    {
        if (Loader::includeModule(self::MODULE_ID) && class_exists(self::TABLE_CLASS)) {
            return true;
        }

        if (!class_exists(self::TABLE_CLASS)) {
            $file = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . self::MODULE_ID . '/lib/FreePositionsTable.php';
            if (is_file($file)) {
                require_once $file;
            }
        }

        return class_exists(self::TABLE_CLASS);
    }

    /**
     * ID инфоблока каталога из настроек модуля.
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

    private function prepareItems(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $sectionsMap = [];
        foreach ($this->arResult['SECTIONS'] as $section) {
            $sectionsMap[(int)$section['ID']] = $section['NAME'];
        }

        $allProductIds = [];
        $decoded = [];
        foreach ($rows as $row) {
            $id = (int)$row['ID'];
            $sectionIds = self::decodeIds($row['SECTION_IDS'] ?? '');
            $productIds = self::decodeIds($row['PRODUCT_IDS'] ?? '');
            $decoded[$id] = [$sectionIds, $productIds];
            foreach ($productIds as $pid) {
                $allProductIds[$pid] = $pid;
            }
        }

        $productsMap = $this->getProductsInfo(array_values($allProductIds));

        $items = [];
        foreach ($rows as $row) {
            $id = (int)$row['ID'];
            list($sectionIds, $productIds) = $decoded[$id];

            $sections = [];
            foreach ($sectionIds as $sid) {
                $sections[] = [
                    'ID'   => $sid,
                    'NAME' => $sectionsMap[$sid] ?? ('#' . $sid),
                ];
            }

            $products = [];
            foreach ($productIds as $pid) {
                $products[] = $productsMap[$pid] ?? [
                    'ID'      => $pid,
                    'NAME'    => '#' . $pid,
                    'PICTURE' => '',
                ];
            }

            $items[] = [
                'ID'          => $id,
                'NAME'        => (string)$row['NAME'],
                'PORTIONS'    => (int)$row['PORTIONS'],
                'SITE_ID'     => (string)$row['SITE_ID'],
                'SECTION_IDS' => $sectionIds,
                'PRODUCT_IDS' => $productIds,
                'SECTIONS'    => $sections,
                'PRODUCTS'    => $products,
            ];
        }

        return $items;
    }

    private function getSections(): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        $catalogIblockId = $this->getCatalogIblockId();
        if ($catalogIblockId <= 0) {
            return [];
        }

        $list = [];
        $rs = \CIBlockSection::GetList(
            ['LEFT_MARGIN' => 'ASC'],
            ['IBLOCK_ID' => $catalogIblockId],
            false,
            ['ID', 'NAME', 'DEPTH_LEVEL']
        );

        while ($s = $rs->Fetch()) {
            $list[] = [
                'ID'    => (int)$s['ID'],
                'NAME'  => (string)$s['NAME'],
                'DEPTH' => (int)$s['DEPTH_LEVEL'],
            ];
        }

        return $list;
    }

    private function getSites(): array
    {
        $list = [];
        $rs = \Bitrix\Main\SiteTable::getList([
            'select' => ['LID', 'NAME'],
            'order'  => ['SORT' => 'ASC'],
        ]);

        while ($site = $rs->fetch()) {
            $list[] = [
                'LID'  => (string)$site['LID'],
                'NAME' => (string)$site['NAME'],
            ];
        }

        return $list;
    }

    private function getProductsInfo(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $catalogIblockId = $this->getCatalogIblockId();
        if ($catalogIblockId <= 0) {
            return [];
        }

        $result = [];
        $rs = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $catalogIblockId, '=ID' => $ids],
            false,
            false,
            ['ID', 'NAME', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']
        );

        while ($p = $rs->Fetch()) {
            // Фото: PREVIEW_PICTURE, при отсутствии — DETAIL_PICTURE
            $pictureId = (int)$p['PREVIEW_PICTURE'] > 0
                ? (int)$p['PREVIEW_PICTURE']
                : (int)$p['DETAIL_PICTURE'];

            $img = '';
            if ($pictureId > 0) {
                $resized = \CFile::ResizeImageGet(
                    $pictureId,
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

    public function configureActions()
    {
        return [
            'save' => [
                'prefilters' => [],
            ],
            'delete' => [
                'prefilters' => [],
            ],
            'searchProducts' => [
                'prefilters' => [],
            ],
        ];
    }

    public function saveAction(): array
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
            return ['success' => false, 'error' => 'Введите название.'];
        }

        $portions = (int)$request->getPost('portions');
        if ($portions < 1) {
            $portions = 1;
        }

        $siteId = trim((string)$request->getPost('site_id'));
        if ($siteId === '') {
            $siteId = (string)Context::getCurrent()->getSite();
        }
        if ($siteId === '') {
            $siteId = self::DEFAULT_SITE_ID;
        }
        $siteId = mb_substr($siteId, 0, 2);

        $sectionIds = $this->readIntArray($request->getPost('section_ids'));
        $productIds = $this->readIntArray($request->getPost('product_ids'));

        $data = [
            'NAME'        => $name,
            'SECTION_IDS' => self::encodeIds($sectionIds),
            'PRODUCT_IDS' => self::encodeIds($productIds),
            'PORTIONS'    => $portions,
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
                'error'   => !empty($errors) ? implode('; ', $errors) : 'Не удалось сохранить запись.',
            ];
        }

        return ['success' => true, 'id' => $id];
    }

    public function deleteAction(): array
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
            return ['success' => false, 'error' => 'Неверный идентификатор записи.'];
        }

        $class = self::TABLE_CLASS;

        try {
            $result = $class::delete($id);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Ошибка удаления: ' . $e->getMessage()];
        }

        if (!$result->isSuccess()) {
            return ['success' => false, 'error' => 'Не удалось удалить запись.'];
        }

        return ['success' => true];
    }

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

        $catalogIblockId = $this->getCatalogIblockId();
        if ($catalogIblockId <= 0) {
            return ['success' => true, 'items' => []];
        }

        $items = [];
        $rs = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $catalogIblockId,
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
