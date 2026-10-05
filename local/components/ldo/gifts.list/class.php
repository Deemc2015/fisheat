<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Engine\Contract\Controllerable;

Loader::includeModule('iblock');

/**
 * Компонент "Уровни подарков к заказам" для партнёрского раздела /partners/menu/gifts/.
 *
 * Работает поверх существующего инфоблока подарков (API_CODE = gifts, ID = 8):
 * элемент = уровень подарка, NAME — название, ATT_SUM_CART — сумма корзины,
 * ATT_PRODUCT — множественное свойство с прикреплёнными товарами (привязка к каталогу).
 *
 * Изменение данных выполняется AJAX-контроллерами компонента
 * (saveLevelAction / deleteLevelAction), выбор товаров — searchProductsAction.
 */
class GiftsList extends \CBitrixComponent implements Controllerable
{
    /** ID инфоблока подарков (API_CODE = gifts). */
    const IBLOCK_ID = 8;

    /** ID инфоблока каталога (товары для привязки). */
    const CATALOG_IBLOCK_ID = 4;

    /** Код множественного свойства с товарами. */
    const PROP_PRODUCTS = 'ATT_PRODUCT';

    /** Код свойства с суммой корзины. */
    const PROP_SUM = 'ATT_SUM_CART';

    /** Код свойства привязки к сайту. */
    const PROP_SITE = 'ATT_SITE';

    /**
     * Основной вывод компонента.
     */
    public function executeComponent()
    {
        $this->arResult['LEVELS'] = $this->getLevels();
        $this->arResult['SESSID'] = bitrix_sessid();
        $this->includeComponentTemplate();
    }

    /**
     * Список уровней подарков (элементы инфоблока подарков).
     *
     * @return array
     */
    private function getLevels(): array
    {
        $raw = [];

        $rs = \CIBlockElement::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            ['IBLOCK_ID' => self::IBLOCK_ID],
            false,
            false,
            ['ID', 'NAME', 'ACTIVE', 'SORT', 'PROPERTY_' . self::PROP_SUM]
        );

        while ($e = $rs->GetNext()) {
            $id = (int)$e['ID'];
            $raw[$id] = [
                'ID'       => $id,
                'NAME'     => (string)$e['NAME'],
                'ACTIVE'   => $e['ACTIVE'] === 'Y',
                'SORT'     => (int)$e['SORT'],
                'SUM'      => (float)($e['PROPERTY_' . self::PROP_SUM . '_VALUE'] ?? 0),
                'PRODUCTS' => [],
            ];
        }

        foreach ($raw as $id => &$level) {
            $level['PRODUCTS'] = $this->getLevelProducts($id);
        }
        unset($level);

        return array_values($raw);
    }

    /**
     * Товары, прикреплённые к уровню (значения множественного свойства).
     *
     * @param int $elementId
     * @return array
     */
    private function getLevelProducts(int $elementId): array
    {
        $productIds = [];

        $rs = \CIBlockElement::GetProperty(
            self::IBLOCK_ID,
            $elementId,
            ['sort' => 'asc'],
            ['CODE' => self::PROP_PRODUCTS]
        );
        while ($p = $rs->Fetch()) {
            $pid = (int)($p['VALUE'] ?? 0);
            if ($pid > 0) {
                $productIds[$pid] = $pid;
            }
        }

        if (empty($productIds)) {
            return [];
        }

        return $this->getProductsInfo(array_values($productIds));
    }

    /**
     * Данные товаров каталога (название + уменьшенное фото).
     *
     * @param array $ids
     * @return array
     */
    private function getProductsInfo(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $result = [];
        $rs = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::CATALOG_IBLOCK_ID, '=ID' => $ids],
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

            $result[] = [
                'ID'      => (int)$p['ID'],
                'NAME'    => (string)$p['NAME'],
                'PICTURE' => $img,
            ];
        }

        return $result;
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

        $request = Context::getCurrent()->getRequest();

        $id   = (int)$request->getPost('id');
        $name = trim((string)$request->getPost('name'));
        if ($name === '') {
            return ['success' => false, 'error' => 'Введите название уровня.'];
        }

        $sum   = (float)$request->getPost('sum');
        $sort  = (int)$request->getPost('sort');
        $active = $request->getPost('active') === 'Y' ? 'Y' : 'N';

        $productIds = [];
        $productIdsRaw = $request->getPost('product_ids');
        if (is_array($productIdsRaw)) {
            foreach ($productIdsRaw as $pid) {
                $pid = (int)$pid;
                if ($pid > 0) {
                    $productIds[] = $pid;
                }
            }
        }

        $fields = [
            'IBLOCK_ID'        => self::IBLOCK_ID,
            'NAME'             => $name,
            'ACTIVE'           => $active,
            'SORT'             => $sort,
            'PROPERTY_VALUES'  => [
                self::PROP_SUM     => $sum,
                self::PROP_PRODUCTS => $productIds,
            ],
        ];

        $element = new \CIBlockElement();

        if ($id > 0) {
            if (!$element->Update($id, $fields)) {
                return [
                    'success' => false,
                    'error'   => $element->LAST_ERROR ?: 'Не удалось сохранить уровень.',
                ];
            }
        } else {
            $fields['PROPERTY_VALUES'][self::PROP_SITE] = Context::getCurrent()->getSite();
            $id = (int)$element->Add($fields);
            if ($id <= 0) {
                return [
                    'success' => false,
                    'error'   => $element->LAST_ERROR ?: 'Не удалось создать уровень.',
                ];
            }
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

        $request = Context::getCurrent()->getRequest();
        $id = (int)$request->getPost('id');
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Неверный идентификатор уровня.'];
        }

        if (!\CIBlockElement::Delete($id)) {
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
                'IBLOCK_ID' => self::CATALOG_IBLOCK_ID,
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
}
