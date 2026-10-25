<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\SiteTable;
use Bitrix\Main\Engine\Contract\Controllerable;

Loader::includeModule('sale');
Loader::includeModule('iblock');

// Модуль маркетинга подключаем на уровне файла: AJAX-действия компонента
// (runComponentAction) выполняются без вызова executeComponent().
if (!Loader::includeModule('ldo.marketing')) {
    $includeFile = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/ldo.marketing/include.php';
    if (is_file($includeFile)) {
        require_once $includeFile;
    }
}
if (!class_exists('\\Ldo\\Marketing\\PromoAdminService')) {
    $serviceFile = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/ldo.marketing/lib/PromoAdminService.php';
    if (is_file($serviceFile)) {
        require_once $serviceFile;
    }
}
if (!class_exists('\\Ldo\\Marketing\\Settings')) {
    $settingsFile = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/ldo.marketing/lib/Settings.php';
    if (is_file($settingsFile)) {
        require_once $settingsFile;
    }
}

/**
 * Компонент "Промокоды" для партнёрского раздела (страница /partners/promokody/).
 *
 * Промокод — это штатное правило работы с корзиной плюс купон Bitrix.
 * Создание, изменение и удаление выполняет сервис Ldo\Marketing\PromoAdminService,
 * компонент отвечает только за интерфейс и AJAX-контракт.
 *
 * Изменение данных — AJAX-контроллеры savePromoAction, deletePromoAction,
 * toggleActiveAction; поиск товаров каталога — searchProductsAction.
 */
class PromocodesList extends \CBitrixComponent implements Controllerable
{
    /** Код модуля с сервисом промокодов. */
    const MODULE_ID = 'ldo.marketing';

    /** ID инфоблока каталога по умолчанию. */
    const CATALOG_IBLOCK_ID = 4;

    /**
     * Основной вывод компонента.
     */
    public function executeComponent()
    {
        $sessionError = '';
        if (!Loader::includeModule('sale')) {
            $sessionError = 'Модуль sale не установлен.';
        } elseif (!class_exists('\\Ldo\\Marketing\\PromoAdminService')) {
            $sessionError = 'Модуль ldo.marketing не установлен. Установите модуль для работы раздела.';
        }

        $sites = $this->getAvailableSites();
        $siteId = $this->resolveSiteId($sites, (string)$this->request->get('site'));

        $this->arResult['SESSID']       = bitrix_sessid();
        $this->arResult['SITE_ID']      = $siteId;
        $this->arResult['SITES']        = $sites;
        $this->arResult['MULTISITE']    = count($sites) > 1;
        $this->arResult['TYPES']        = \Ldo\Marketing\PromoAdminService::getTypes();
        $this->arResult['COUPON_TYPES'] = \Ldo\Marketing\PromoAdminService::getCouponTypes();
        $this->arResult['ERROR']        = $sessionError;

        $promos = [];
        if ($sessionError === '') {
            $promos = \Ldo\Marketing\PromoAdminService::getList($siteId);
            $promos = $this->enrichWithProducts($promos);
        }

        $this->arResult['PROMOS'] = $promos;

        $this->includeComponentTemplate();
    }

    /**
     * Список сайтов, доступных для управления.
     *
     * Администратор видит все сайты, обычный пользователь — только текущий.
     *
     * @return array LID => NAME
     */
    private function getAvailableSites(): array
    {
        global $USER;

        $isAdmin = $USER instanceof \CUser && $USER->IsAdmin();

        $sites = [];
        $rs = SiteTable::getList([
            'select' => ['LID', 'NAME'],
            'order'  => ['SORT' => 'ASC', 'LID' => 'ASC'],
        ]);
        while ($site = $rs->fetch()) {
            $sites[(string)$site['LID']] = (string)($site['NAME'] !== '' ? $site['NAME'] : $site['LID']);
        }

        if ($isAdmin) {
            return $sites;
        }

        $current = $this->getCurrentSiteId();
        if (isset($sites[$current])) {
            return [$current => $sites[$current]];
        }

        return $sites;
    }

    /**
     * Определение сайта, для которого выводится список.
     *
     * @param array  $sites
     * @param string $requested
     * @return string
     */
    private function resolveSiteId(array $sites, string $requested): string
    {
        $requested = mb_substr(trim($requested), 0, 2);
        if ($requested !== '' && isset($sites[$requested])) {
            return $requested;
        }

        $current = $this->getCurrentSiteId();
        if (isset($sites[$current])) {
            return $current;
        }

        $first = array_key_first($sites);

        return $first !== null ? (string)$first : 's1';
    }

    /**
     * Текущий сайт.
     *
     * @return string
     */
    private function getCurrentSiteId(): string
    {
        $siteId = (string)Context::getCurrent()->getSite();

        return $siteId !== '' ? $siteId : 's1';
    }

    /**
     * Обогащение промокодов данными товара (для типа «товар по промокоду»).
     *
     * @param array $promos
     * @return array
     */
    private function enrichWithProducts(array $promos): array
    {
        $productIds = [];
        foreach ($promos as $promo) {
            $productId = (int)($promo['PRODUCT_ID'] ?? 0);
            if ($productId > 0) {
                $productIds[$productId] = $productId;
            }
        }

        $products = $this->getProductsInfo(array_values($productIds));

        foreach ($promos as &$promo) {
            $productId = (int)($promo['PRODUCT_ID'] ?? 0);
            $promo['PRODUCT'] = $productId > 0
                ? ($products[$productId] ?? ['ID' => $productId, 'NAME' => '#' . $productId, 'PICTURE' => ''])
                : null;
        }
        unset($promo);

        return $promos;
    }

    /**
     * Данные товаров каталога (название и уменьшенное фото).
     *
     * @param array $ids
     * @return array id => ['ID', 'NAME', 'PICTURE']
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
            ['ID', 'NAME', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']
        );
        while ($product = $rs->Fetch()) {
            $pictureId = (int)$product['PREVIEW_PICTURE'] > 0
                ? (int)$product['PREVIEW_PICTURE']
                : (int)$product['DETAIL_PICTURE'];

            $image = '';
            if ($pictureId > 0) {
                $resized = \CFile::ResizeImageGet(
                    $pictureId,
                    ['width' => 80, 'height' => 80],
                    BX_RESIZE_IMAGE_PROPORTIONAL,
                    true
                );
                if (is_array($resized)) {
                    $image = (string)$resized['src'];
                }
            }

            $result[(int)$product['ID']] = [
                'ID'      => (int)$product['ID'],
                'NAME'    => (string)$product['NAME'],
                'PICTURE' => $image,
            ];
        }

        return $result;
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

    /**
     * Конфигурация AJAX-действий. CSRF-защита выполняется вручную
     * через check_bitrix_sessid().
     *
     * @return array
     */
    public function configureActions()
    {
        return [
            'savePromo' => [
                'prefilters' => [],
            ],
            'deletePromo' => [
                'prefilters' => [],
            ],
            'toggleActive' => [
                'prefilters' => [],
            ],
            'searchProducts' => [
                'prefilters' => [],
            ],
        ];
    }

    /**
     * AJAX-контроллер: создание и обновление промокода.
     *
     * @return array{success: bool, id?: int, error?: string}
     */
    public function savePromoAction(): array
    {
        $accessError = $this->checkAccess();
        if ($accessError !== '') {
            return ['success' => false, 'error' => $accessError];
        }

        $request = Context::getCurrent()->getRequest();

        $data = [
            'id'          => (int)$request->getPost('id'),
            'site_id'     => (string)$request->getPost('site_id'),
            'name'        => (string)$request->getPost('name'),
            'code'        => (string)$request->getPost('code'),
            'type'        => (string)$request->getPost('type'),
            'value'       => (string)$request->getPost('value'),
            'value_type'  => (string)$request->getPost('value_type'),
            'min_sum'     => (string)$request->getPost('min_sum'),
            'product_id'  => (int)$request->getPost('product_id'),
            'active'      => (string)$request->getPost('active'),
            'coupon_type' => (string)$request->getPost('coupon_type'),
            'max_use'     => (int)$request->getPost('max_use'),
            'user_id'     => (int)$request->getPost('user_id'),
            'date_from'   => (string)$request->getPost('date_from'),
            'date_to'     => (string)$request->getPost('date_to'),
        ];

        return \Ldo\Marketing\PromoAdminService::save($data);
    }

    /**
     * AJAX-контроллер: удаление промокода.
     *
     * @return array{success: bool, error?: string}
     */
    public function deletePromoAction(): array
    {
        $accessError = $this->checkAccess();
        if ($accessError !== '') {
            return ['success' => false, 'error' => $accessError];
        }

        $request = Context::getCurrent()->getRequest();
        $id = (int)$request->getPost('id');

        return \Ldo\Marketing\PromoAdminService::delete($id);
    }

    /**
     * AJAX-контроллер: включение и выключение промокода.
     *
     * @return array{success: bool, error?: string}
     */
    public function toggleActiveAction(): array
    {
        $accessError = $this->checkAccess();
        if ($accessError !== '') {
            return ['success' => false, 'error' => $accessError];
        }

        $request = Context::getCurrent()->getRequest();
        $id = (int)$request->getPost('id');
        $active = (string)$request->getPost('active');

        return \Ldo\Marketing\PromoAdminService::setActive($id, $active);
    }

    /**
     * AJAX-контроллер: поиск товаров каталога по названию.
     *
     * @return array{success: bool, items?: array, error?: string}
     */
    public function searchProductsAction(): array
    {
        $accessError = $this->checkAccess();
        if ($accessError !== '') {
            return ['success' => false, 'error' => $accessError];
        }

        $request = Context::getCurrent()->getRequest();
        $query = trim((string)$request->getPost('q'));
        $query = mb_substr($query, 0, 100);

        if (mb_strlen($query) < 2) {
            return ['success' => true, 'items' => []];
        }

        $siteId = mb_substr(trim((string)$request->getPost('site_id')), 0, 2);
        $iblockId = $siteId !== ''
            ? \Ldo\Marketing\Settings::getCatalogIblockId($siteId)
            : $this->getCatalogIblockId();

        $items = [];
        $rs = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $iblockId,
                'ACTIVE'    => 'Y',
                '%NAME'     => $query,
            ],
            false,
            ['nTopCount' => 20],
            ['ID', 'NAME']
        );
        while ($product = $rs->Fetch()) {
            $items[] = [
                'id'   => (int)$product['ID'],
                'name' => (string)$product['NAME'],
            ];
        }

        return ['success' => true, 'items' => $items];
    }

    /**
     * Проверка сессии и авторизации для AJAX-действий.
     *
     * @return string Текст ошибки либо пустая строка
     */
    private function checkAccess(): string
    {
        if (!check_bitrix_sessid()) {
            return 'Сессия истекла. Обновите страницу.';
        }

        global $USER;
        if (!($USER instanceof \CUser) || !$USER->IsAuthorized()) {
            return 'Требуется авторизация.';
        }

        return '';
    }
}
