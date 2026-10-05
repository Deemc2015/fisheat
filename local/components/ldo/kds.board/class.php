<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Context;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\Internals\BasketTable;
use Bitrix\Sale\Internals\OrderPropsGroupTable;
use Bitrix\Sale\Internals\OrderPropsTable;
use Bitrix\Sale\Internals\OrderPropsValueTable;
use Bitrix\Sale\Internals\OrderTable;
use Bitrix\Sale\Internals\PersonTypeTable;

/**
 * Компонент "Доска заказов ресторана" (KDS для кухни).
 *
 * Логика:
 *   1) если ресторан не выбран — отдаём список ресторанов для экрана выбора;
 *   2) если ресторан выбран — отдаём его заказы, сгруппированные по ЭТАПАМ ГОТОВКИ
 *      (колонки доски: Принят → Готовится → Готов → Передан).
 *
 * Этапы готовки не зависят от статусов заказа: они хранятся в служебном
 * свойстве заказа kds_stage (UTIL=Y — покупателю не показывается), которое
 * компонент создаёт при первом обращении.
 *
 * Связь заказа с рестораном — свойство заказа RESTORAN_ID (XML_ID ресторана),
 * тот же принцип, что и в компоненте ldo:orders.list.
 *
 * Данные собирает PHP, рисует Vue-приложение (расширение ldo.kds на ui.vue3).
 */
class KdsBoard extends \CBitrixComponent implements Controllerable
{
	/** @var string Код служебного свойства заказа с этапом готовки */
	const STAGE_PROP_CODE = 'kds_stage';

	/** @var string XML_ID выбранного ресторана */
	protected $restaurantXmlId = '';

	/** @var array Этапы-колонки доски: [['ID' => 'accepted', 'NAME' => 'Принят'], ...] */
	protected $stages = [];

	/** @var int За сколько дней показывать заказы */
	protected $daysBack = 1;

	/** @var int Максимум заказов на доске */
	protected $limit = 200;

	/** @var int ID служебного свойства заказа (0 — не найдено/не создано) */
	protected static $stagePropId = null;

	/**
	 * Этапы готовки по умолчанию: код => название.
	 *
	 * @return array
	 */
	protected function getDefaultStageNames(): array
	{
		return [
			'accepted' => 'Принят',
			'cooking'  => 'Готовится',
			'ready'    => 'Готов',
			'handed'   => 'Передан',
		];
	}

	/**
	 * Значения по умолчанию для параметров компонента.
	 *
	 * @return array
	 */
	protected function getDefaults(): array
	{
		return [
			'STAGES'    => array_keys($this->getDefaultStageNames()),
			'DAYS_BACK' => 1,
			'LIMIT'     => 200,
		];
	}

	public function onPrepareComponentParams($arParams)
	{
		$defaults = $this->getDefaults();

		if (!isset($arParams['STAGES']) || !is_array($arParams['STAGES']) || empty($arParams['STAGES'])) {
			$arParams['STAGES'] = $defaults['STAGES'];
		}
		if (!isset($arParams['STAGE_NAMES']) || !is_array($arParams['STAGE_NAMES'])) {
			$arParams['STAGE_NAMES'] = [];
		}

		$arParams['DAYS_BACK'] = max(1, (int)($arParams['DAYS_BACK'] ?? $defaults['DAYS_BACK']));
		$arParams['LIMIT']     = max(1, (int)($arParams['LIMIT'] ?? $defaults['LIMIT']));

		return $arParams;
	}

	public function executeComponent()
	{
		if (!Loader::includeModule('sale')) {
			$this->arResult['ERROR'] = 'Модуль sale не подключен.';
			$this->includeComponentTemplate();
			return;
		}

		$this->initStages();

		// Ресторан можно задать параметром компонента или GET-параметром ?RESTAURANT=<xml_id>
		$request = Context::getCurrent()->getRequest();
		$xmlId = trim((string)$request->getQuery('RESTAURANT'));
		if ($xmlId === '') {
			$xmlId = trim((string)($this->arParams['RESTAURANT'] ?? ''));
		}
		$this->restaurantXmlId = $xmlId;

		$this->arResult = $this->collectData();

		$this->includeComponentTemplate();
	}

	/**
	 * Конфигурация AJAX-действий.
	 *
	 * @return array
	 */
	public function configureActions()
	{
		return [
			'getList'  => ['prefilters' => []],
			'setStage' => ['prefilters' => []],
		];
	}

	/**
	 * AJAX: данные доски (используется для автообновления).
	 *
	 * @return array
	 */
	public function getListAction()
	{
		global $USER;

		if (!Loader::includeModule('sale')) {
			return ['success' => false, 'error' => 'Модуль sale не найден'];
		}
		if (!$USER->IsAuthorized()) {
			return ['success' => false, 'error' => 'Требуется авторизация'];
		}

		$request = Context::getCurrent()->getRequest();

		$this->initStages();
		$this->daysBack = max(1, (int)($request->getPost('DAYS_BACK') ?: $this->arParams['DAYS_BACK']));
		$this->limit = (int)$this->arParams['LIMIT'];
		$this->restaurantXmlId = trim((string)$request->getPost('RESTAURANT'));

		return [
			'success' => true,
			'data'    => $this->collectData(),
		];
	}

	/**
	 * AJAX: перевод заказа на другой этап готовки.
	 *
	 * @return array
	 */
	public function setStageAction()
	{
		global $USER;

		if (!Loader::includeModule('sale')) {
			return ['success' => false, 'error' => 'Модуль sale не найден'];
		}
		if (!$USER->IsAuthorized()) {
			return ['success' => false, 'error' => 'Требуется авторизация'];
		}

		$request = Context::getCurrent()->getRequest();
		$orderId = (int)$request->getPost('ORDER_ID');
		$stageId = trim((string)$request->getPost('STAGE'));

		if ($orderId <= 0 || $stageId === '') {
			return ['success' => false, 'error' => 'Не переданы ORDER_ID и STAGE'];
		}

		$this->initStages();
		if (!in_array($stageId, array_column($this->stages, 'ID'), true)) {
			return ['success' => false, 'error' => 'Неизвестный этап: ' . $stageId];
		}

		$error = $this->saveStage($orderId, $stageId);
		if ($error !== '') {
			return ['success' => false, 'error' => $error];
		}

		return ['success' => true, 'data' => ['ORDER_ID' => $orderId, 'STAGE' => $stageId]];
	}

	// ============================================================
	// Этапы готовки
	// ============================================================

	/**
	 * Инициализация списка этапов-колонок (параметры компонента + названия).
	 */
	protected function initStages()
	{
		$this->stages = $this->normalizeStages($this->arParams['STAGES'], $this->arParams['STAGE_NAMES']);
		$this->daysBack = (int)$this->arParams['DAYS_BACK'];
		$this->limit = (int)$this->arParams['LIMIT'];
	}

	/**
	 * Приведение списка этапов к [{ID, NAME}].
	 *
	 * @param array $stages
	 * @param array $names
	 * @return array
	 */
	protected function normalizeStages($stages, array $names = []): array
	{
		$defaults = $this->getDefaultStageNames();
		$result = [];

		foreach ((array)$stages as $key => $value) {
			// Поддерживаем два вида параметра: ['accepted', ...] и ['accepted' => 'Принят', ...]
			if (is_string($key) && $value !== '' && !is_numeric($key)) {
				$code = trim($key);
				$name = trim((string)$value);
			} else {
				$code = trim((string)$value);
				$name = '';
			}
			if ($code === '') {
				continue;
			}
			if ($name === '' && isset($names[$code])) {
				$name = trim((string)$names[$code]);
			}
			if ($name === '' && isset($defaults[$code])) {
				$name = $defaults[$code];
			}
			if ($name === '') {
				$name = $code;
			}

			$result[] = ['ID' => $code, 'NAME' => $name];
		}

		// Ничего не передали — используем этапы по умолчанию
		if (empty($result)) {
			foreach ($defaults as $code => $name) {
				$result[] = ['ID' => $code, 'NAME' => $name];
			}
		}

		return $result;
	}

	/**
	 * Первый этап доски (по умолчанию для заказов без сохранённого этапа).
	 *
	 * @return string
	 */
	protected function getFirstStageId(): string
	{
		return (string)($this->stages[0]['ID'] ?? '');
	}

	/**
	 * ID служебного свойства заказа kds_stage; при отсутствии — создаёт его
	 * для активных типов плательщиков. Возвращает 0, если свойство недоступно.
	 *
	 * @return int
	 */
	protected function getStagePropId(): int
	{
		if (static::$stagePropId !== null) {
			return static::$stagePropId;
		}

		static::$stagePropId = 0;

		try {
			// Свойство могло быть создано ранее — ищем по коду
			$exists = OrderPropsTable::getList([
				'select' => ['ID'],
				'filter' => ['=CODE' => self::STAGE_PROP_CODE],
				'limit'  => 1,
			])->fetch();
			if ($exists) {
				static::$stagePropId = (int)$exists['ID'];
				return static::$stagePropId;
			}

			// Создаём свойство для каждого активного типа плательщика
			$personTypeIds = [];
			try {
				$rows = PersonTypeTable::getList([
					'select' => ['ID'],
					'filter' => ['=ACTIVE' => 'Y'],
				])->fetchAll();
				foreach ($rows as $row) {
					$personTypeIds[(int)$row['ID']] = true;
				}
			} catch (\Throwable $e) {
				$personTypeIds = [];
			}

			// Запасной путь: типы плательщиков из уже существующих заказов сайта
			if (empty($personTypeIds)) {
				try {
					$rows = OrderTable::getList([
						'select' => ['PERSON_TYPE_ID'],
						'filter' => ['=LID' => SITE_ID],
						'group'  => ['PERSON_TYPE_ID'],
					])->fetchAll();
					foreach ($rows as $row) {
						$personTypeIds[(int)$row['PERSON_TYPE_ID']] = true;
					}
				} catch (\Throwable $e) {
					$personTypeIds = [];
				}
			}

			foreach (array_keys($personTypeIds) as $personTypeId) {
				// Группа свойств обязательна: берём существующую у типа плательщика
				// либо переиспользуем группу любого его свойства
				$groupId = 0;
				try {
					$group = OrderPropsGroupTable::getList([
						'select' => ['ID'],
						'filter' => ['=PERSON_TYPE_ID' => $personTypeId],
						'order'  => ['SORT' => 'ASC'],
						'limit'  => 1,
					])->fetch();
					if ($group) {
						$groupId = (int)$group['ID'];
					}
				} catch (\Throwable $e) {
					$groupId = 0;
				}
				if ($groupId <= 0) {
					try {
						$anyProp = OrderPropsTable::getList([
							'select' => ['PROPS_GROUP_ID'],
							'filter' => ['=PERSON_TYPE_ID' => $personTypeId, '>PROPS_GROUP_ID' => 0],
							'limit'  => 1,
						])->fetch();
						if ($anyProp) {
							$groupId = (int)$anyProp['PROPS_GROUP_ID'];
						}
					} catch (\Throwable $e) {
						$groupId = 0;
					}
				}
				if ($groupId <= 0) {
					continue;
				}

				$result = OrderPropsTable::add([
					'PERSON_TYPE_ID' => $personTypeId,
					'PROPS_GROUP_ID' => $groupId,
					'NAME'           => 'Этап готовки (доска кухни)',
					'CODE'           => self::STAGE_PROP_CODE,
					'TYPE'           => 'STRING',
					'REQUIRED'       => 'N',
					'UTIL'           => 'Y',
					'USER_PROPS'     => 'N',
					'IS_LOCATION'    => 'N',
					'IS_EMAIL'       => 'N',
					'IS_PROFILE_NAME' => 'N',
					'IS_PAY_NAME'    => 'N',
					'IS_FILTERED'    => 'N',
					'SORT'           => 500,
					'DEFAULT_VALUE'  => '',
					'MULTIPLE'       => 'N',
					'SETTINGS'       => ['SIZE' => 20, 'ROWS' => 1, 'MINLENGTH' => 0, 'MAXLENGTH' => 0],
				]);
				if ($result->isSuccess()) {
					$id = (int)$result->getId();
					if (static::$stagePropId === 0) {
						static::$stagePropId = $id;
					}
				}
			}
		} catch (\Throwable $e) {
			static::$stagePropId = 0;
		}

		return static::$stagePropId;
	}

	/**
	 * Этапы заказов одним запросом: ORDER_ID => код этапа.
	 *
	 * @param array $orderIds
	 * @return array
	 */
	protected function loadStages(array $orderIds): array
	{
		$stages = [];
		if (empty($orderIds)) {
			return $stages;
		}

		try {
			$rs = OrderPropsValueTable::getList([
				'select' => ['ORDER_ID', 'VALUE'],
				'filter' => ['=ORDER_ID' => $orderIds, '=CODE' => self::STAGE_PROP_CODE],
			]);
			while ($row = $rs->fetch()) {
				$stages[(int)$row['ORDER_ID']] = (string)$row['VALUE'];
			}
		} catch (\Throwable $e) {
			$stages = [];
		}

		return $stages;
	}

	/**
	 * Сохранение этапа заказа (upsert служебного свойства).
	 *
	 * @param int    $orderId
	 * @param string $stageId
	 * @return string Текст ошибки или пустая строка
	 */
	protected function saveStage(int $orderId, string $stageId): string
	{
		$propId = $this->getStagePropId();
		if ($propId <= 0) {
			return 'Не удалось инициализировать свойство заказа «kds_stage»';
		}

		try {
			$exists = OrderPropsValueTable::getList([
				'select' => ['ID'],
				'filter' => ['=ORDER_ID' => $orderId, '=CODE' => self::STAGE_PROP_CODE],
				'limit'  => 1,
			])->fetch();

			if ($exists) {
				$result = OrderPropsValueTable::update((int)$exists['ID'], ['VALUE' => $stageId]);
			} else {
				$result = OrderPropsValueTable::add([
					'ORDER_ID'       => $orderId,
					'ORDER_PROPS_ID' => $propId,
					'NAME'           => 'Этап готовки (доска кухни)',
					'CODE'           => self::STAGE_PROP_CODE,
					'VALUE'          => $stageId,
				]);
			}

			if (!$result->isSuccess()) {
				return implode('; ', $result->getErrorMessages());
			}
		} catch (\Throwable $e) {
			return $e->getMessage();
		}

		return '';
	}

	// ============================================================
	// Сбор данных
	// ============================================================

	/**
	 * Полный набор данных для шаблона.
	 *
	 * @return array
	 */
	protected function collectData(): array
	{
		$restaurants = $this->getRestaurants();

		$columns = [];
		foreach ($this->stages as $stage) {
			$columns[] = [
				'ID'    => $stage['ID'],
				'NAME'  => $stage['NAME'],
				'COUNT' => 0,
			];
		}

		$orders = [];
		$restaurantName = '';

		if ($this->restaurantXmlId !== '' && isset($restaurants[$this->restaurantXmlId])) {
			$restaurantName = (string)$restaurants[$this->restaurantXmlId];
			$orders = $this->getOrders($this->restaurantXmlId);

			foreach ($columns as $i => $column) {
				$cnt = 0;
				foreach ($orders as $order) {
					if ($order['STAGE'] === $column['ID']) {
						$cnt++;
					}
				}
				$columns[$i]['COUNT'] = $cnt;
			}
		}

		return [
			'RESTAURANTS'     => $restaurants,
			'RESTAURANT'      => $this->restaurantXmlId,
			'RESTAURANT_NAME' => $restaurantName,
			'STAGES'          => $columns,
			'ORDERS'          => $orders,
			'DAYS_BACK'       => $this->daysBack,
			'LIMIT'           => $this->limit,
			'SERVER_TIME'     => date('H:i:s'),
			'SERVER_DATE'     => date('d.m.Y'),
		];
	}

	/**
	 * Список ресторанов: XML_ID => название (только активные с заполненным XML_ID).
	 *
	 * @return array
	 */
	protected function getRestaurants(): array
	{
		$restaurants = [];
		if (!Loader::includeModule('ldo.deliverymap')) {
			return $restaurants;
		}

		try {
			$list = \Ldo\Deliverymap\RestaurantsTable::getActiveList(
				['=SITE_ID' => SITE_ID],
				['NAME' => 'ASC']
			);
			foreach ($list as $r) {
				$xmlId = (string)($r['XML_ID'] ?? '');
				// Заказы привязываются к ресторану по XML_ID — без него в доску не попадёт
				if ($xmlId !== '') {
					$restaurants[$xmlId] = (string)$r['NAME'];
				}
			}
		} catch (\Throwable $e) {
			$restaurants = [];
		}

		return $restaurants;
	}

	/**
	 * Заказы ресторана с составом, данными доставки и этапом готовки.
	 *
	 * @param string $xmlId
	 * @return array
	 */
	protected function getOrders(string $xmlId): array
	{
		$orderIds = $this->getOrderIdsByRestaurant($xmlId);
		if (empty($orderIds)) {
			return [];
		}

		$dateFrom = (new DateTime())->add('-' . $this->daysBack . ' days');

		$orders = [];
		$rsOrders = OrderTable::getList([
			'select' => [
				'ID', 'ACCOUNT_NUMBER', 'DATE_INSERT', 'PRICE', 'STATUS_ID', 'LID',
				'USER_DESCRIPTION', 'DELIVERY_ID', 'PAY_SYSTEM_ID', 'USER_ID',
				'USER.NAME', 'USER.LAST_NAME', 'USER.LOGIN',
			],
			'filter' => [
				'=ID' => $orderIds,
				'=LID' => SITE_ID,
				'>=DATE_INSERT' => $dateFrom,
			],
			'order' => ['DATE_INSERT' => 'ASC'],
			'limit' => $this->limit,
		]);
		while ($order = $rsOrders->fetch()) {
			$orders[] = $order;
		}

		if (empty($orders)) {
			return [];
		}

		$orderIds = array_column($orders, 'ID');
		$props = $this->loadOrderProps($orderIds);
		$baskets = $this->loadBaskets($orderIds);
		$deliveryClasses = $this->getDeliveryClassMap();
		$stages = $this->loadStages($orderIds);

		$allowedStages = array_column($this->stages, 'ID');
		$firstStage = $this->getFirstStageId();

		$result = [];
		foreach ($orders as $order) {
			$orderProps = $props[$order['ID']] ?? [];
			$deliveryClass = (string)($deliveryClasses[(int)$order['DELIVERY_ID']] ?? '');

			$isDelivery = (strpos($deliveryClass, 'ZoneDelivery') !== false);
			$isPickup = (
				!$isDelivery
				&& $deliveryClass !== ''
				&& strpos($deliveryClass, 'EmptyDeliveryService') === false
			);

			$items = [];
			$itemsTotal = 0.0;
			foreach ($baskets[$order['ID']] ?? [] as $basket) {
				$qty = (float)$basket['QUANTITY'];
				$summary = (float)$basket['SUMMARY_PRICE'];
				$itemsTotal += $summary;
				$items[] = [
					'NAME'  => (string)$basket['NAME'],
					'QTY'   => $qty,
					'PRICE' => $this->fmtMoney($basket['PRICE']),
					'SUM'   => $this->fmtMoney($summary),
				];
			}

			// Этап готовки: сохранённый, иначе первый этап доски
			$stage = (string)($stages[(int)$order['ID']] ?? '');
			if ($stage === '' || !in_array($stage, $allowedStages, true)) {
				$stage = $firstStage;
			}

			$fio = trim(
				(string)($orderProps['FIO'] ?? '')
				?: (' ' . (string)($order['USER_LAST_NAME'] ?? '') . ' ' . (string)($order['USER_NAME'] ?? '')
					?: (string)($order['USER_LOGIN'] ?? ''))
			);

			$result[] = [
				'ID'            => (int)$order['ID'],
				'NUMBER'        => (string)($order['ACCOUNT_NUMBER'] !== '' ? $order['ACCOUNT_NUMBER'] : $order['ID']),
				'DATE_INSERT'   => $this->fmtDate($order['DATE_INSERT']),
				'TIME_INSERT'   => $this->fmtTime($order['DATE_INSERT']),
				'STAGE'         => $stage,
				'STATUS_ID'     => (string)$order['STATUS_ID'],
				'PRICE'         => $this->fmtMoney($order['PRICE']),
				'ITEMS_TOTAL'   => $this->fmtMoney($itemsTotal),
				'IS_DELIVERY'   => $isDelivery,
				'IS_PICKUP'     => $isPickup,
				'ADDRESS'       => $this->buildAddress($orderProps),
				'PICKUP_POINT'  => (string)($orderProps['NAME_RESTORAN'] ?? ''),
				'PERSONS'       => (string)($orderProps['COUNT_PERSON'] ?? ''),
				'DELIVERY_TIME' => $this->buildDeliveryTime($orderProps),
				'COMMENT'       => (string)($order['USER_DESCRIPTION'] ?? ''),
				'FIO'           => $fio,
				'PHONE'         => (string)($orderProps['PHONE'] ?? ''),
				'ITEMS'         => $items,
			];
		}

		return $result;
	}

	/**
	 * ID заказов ресторана (свойство заказа RESTORAN_ID = XML_ID ресторана).
	 *
	 * @param string $xmlId
	 * @return int[]
	 */
	protected function getOrderIdsByRestaurant(string $xmlId): array
	{
		$ids = [];
		try {
			$rs = OrderPropsValueTable::getList([
				'select' => ['ORDER_ID'],
				'filter' => ['=CODE' => 'RESTORAN_ID', '=VALUE' => $xmlId],
			]);
			while ($row = $rs->fetch()) {
				$ids[(int)$row['ORDER_ID']] = true;
			}
		} catch (\Throwable $e) {
			return [];
		}

		return array_keys($ids);
	}

	/**
	 * Свойства заказов одним запросом.
	 *
	 * @param array $orderIds
	 * @return array
	 */
	protected function loadOrderProps(array $orderIds): array
	{
		$props = [];
		if (empty($orderIds)) {
			return $props;
		}

		$rsProps = OrderPropsValueTable::getList([
			'select' => ['ORDER_ID', 'CODE', 'VALUE'],
			'filter' => ['=ORDER_ID' => $orderIds],
		]);
		while ($prop = $rsProps->fetch()) {
			$props[$prop['ORDER_ID']][$prop['CODE']] = (string)$prop['VALUE'];
		}

		return $props;
	}

	/**
	 * Состав заказов одним запросом.
	 *
	 * @param array $orderIds
	 * @return array
	 */
	protected function loadBaskets(array $orderIds): array
	{
		$baskets = [];
		if (empty($orderIds)) {
			return $baskets;
		}

		$rsBasket = BasketTable::getList([
			'select' => ['ID', 'ORDER_ID', 'NAME', 'PRICE', 'QUANTITY', 'SUMMARY_PRICE'],
			'filter' => ['=ORDER_ID' => $orderIds],
			'order'  => ['ID' => 'ASC'],
		]);
		while ($basket = $rsBasket->fetch()) {
			$baskets[$basket['ORDER_ID']][] = $basket;
		}

		return $baskets;
	}

	/**
	 * Карта ID службы доставки => имя класса обработчика
	 * (доставка = ZoneDelivery, самовывоз = остальные непустые).
	 *
	 * @return array
	 */
	protected function getDeliveryClassMap(): array
	{
		$map = [];
		try {
			$rs = \Bitrix\Sale\Delivery\Services\Table::getList([
				'select' => ['ID', 'CLASS_NAME'],
			]);
			while ($row = $rs->fetch()) {
				$map[(int)$row['ID']] = (string)$row['CLASS_NAME'];
			}
		} catch (\Throwable $e) {
			$map = [];
		}

		return $map;
	}

	/**
	 * Адрес доставки одной строкой.
	 *
	 * @param array $props
	 * @return string
	 */
	protected function buildAddress(array $props): string
	{
		$parts = [];
		if (!empty($props['ADDRESS'])) {
			$parts[] = (string)$props['ADDRESS'];
		} elseif (!empty($props['CITY'])) {
			$parts[] = (string)$props['CITY'];
		}
		if (!empty($props['PODEZD'])) {
			$parts[] = 'подъезд ' . $props['PODEZD'];
		}
		if (!empty($props['ETAG'])) {
			$parts[] = 'этаж ' . $props['ETAG'];
		}
		if (!empty($props['KVARTIRA'])) {
			$parts[] = 'кв. ' . $props['KVARTIRA'];
		}
		if (!empty($props['DOMOFON'])) {
			$parts[] = 'домофон: ' . $props['DOMOFON'];
		}

		return implode(', ', $parts);
	}

	/**
	 * Время доставки заказа.
	 *
	 * @param array $props
	 * @return string
	 */
	protected function buildDeliveryTime(array $props): string
	{
		if (($props['DEFAULT_TIME'] ?? '') === 'Y') {
			return 'Как можно скорее';
		}

		return (string)($props['DATE_TIME_DELIVERY'] ?? '');
	}

	/**
	 * Форматирование даты заказа.
	 *
	 * @param mixed $value
	 * @return string
	 */
	protected function fmtDate($value): string
	{
		if ($value instanceof \Bitrix\Main\Type\Date) {
			return $value->format('d.m.Y');
		}

		return (string)$value;
	}

	/**
	 * Форматирование времени заказа (ЧЧ:ММ).
	 *
	 * @param mixed $value
	 * @return string
	 */
	protected function fmtTime($value): string
	{
		if ($value instanceof \Bitrix\Main\Type\Date) {
			return $value->format('H:i');
		}

		return '';
	}

	/**
	 * Форматирование суммы.
	 *
	 * @param mixed $value
	 * @return string
	 */
	protected function fmtMoney($value): string
	{
		return number_format((float)$value, 2, '.', ' ');
	}
}
