<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Context;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\Sale\Delivery\Services\Table as DeliveryServicesTable;
use Bitrix\Sale\Internals\BasketTable;
use Bitrix\Sale\Internals\OrderPropsValueTable;
use Bitrix\Sale\Internals\OrderTable;
use Bitrix\Sale\Internals\PaySystemActionTable;
use Bitrix\Sale\Internals\ShipmentTable;
use Bitrix\Sale\OrderStatus;

/**
 * Компонент "Список заказов" для партнёрского раздела.
 *
 * Выводит список заказов модуля sale с фильтром по статусу/датам/поиску,
 * постраничной навигацией (PageNavigation), раскрытием состава заказа
 * и выгрузкой в Excel (CSV с BOM).
 *
 * Вся выборка — штатный ORM D7 модуля sale (OrderTable, BasketTable,
 * OrderPropsValueTable, ShipmentTable), статусы — Bitrix\Sale\OrderStatus.
 */
class OrdersList extends \CBitrixComponent implements Controllerable
{
	/** @var string[] Выбранные статусы */
	protected $filterStatus = [];

	/** @var int[] Выбранные службы доставки (DELIVERY_ID) */
	protected $filterDelivery = [];

	/** @var int[] Выбранные платёжные системы (PAY_SYSTEM_ID) */
	protected $filterPaySystem = [];

	/** @var string Выбранный ресторан (XML_ID из свойства заказа RESTORAN_ID) */
	protected $filterRestaurant = '';

	/** @var DateTime|null Дата "с" (начало дня) */
	protected $dateFrom = null;

	/** @var DateTime|null Дата "по" (конец дня) */
	protected $dateTo = null;

	/** @var string Строка поиска */
	protected $search = '';

	/** @var string Сырое значение DATE_FROM из запроса */
	protected $dateFromRaw = '';

	/** @var string Сырое значение DATE_TO из запроса */
	protected $dateToRaw = '';

	/** @var int[] ID заказов, найденных по свойствам (поиск) */
	protected $searchOrderIds = [];

	/** @var int[] ID заказов, отфильтрованных по ресторану (свойство RESTORAN_ID) */
	protected $restaurantOrderIds = [];

	/** @var array Карта статусов: ID => название */
	protected $statuses = [];

	/** @var int[] Службы доставки для фильтра (параметр DELIVERY_SERVICES; пусто — все) */
	protected $allowedDeliveryIds = [];

	/** @var int[] Платёжные системы для фильтра (параметр PAY_SYSTEMS; пусто — все) */
	protected $allowedPaySystemIds = [];

	/** @var array|null Кеш DEFAULT-параметров (чтобы не выбирать списки из БД на каждый хит) */
	protected static $defaultParamsCache = null;

	/**
	 * Применение значений по умолчанию из .parameters.php.
	 * При вызове через IncludeComponent Битрикс не подставляет DEFAULT автоматически —
	 * делаем это здесь (штатный метод CBitrixComponent).
	 *
	 * @param array $arParams
	 * @return array
	 */
	public function onPrepareComponentParams($arParams)
	{
		$arParams = array_merge($this->getDefaultParams(), (array)$arParams);

		// Параметры "какие способы выводить в фильтре" — нормализуем к int[]
		$this->allowedDeliveryIds = $this->normalizeIdList($arParams['DELIVERY_SERVICES'] ?? []);
		$this->allowedPaySystemIds = $this->normalizeIdList($arParams['PAY_SYSTEMS'] ?? []);

		return $arParams;
	}

	/**
	 * Приведение значения параметра (массив ID или строка "1,2,3") к массиву положительных int.
	 *
	 * @param mixed $value
	 * @return int[]
	 */
	protected function normalizeIdList($value): array
	{
		$ids = [];
		if (is_array($value)) {
			foreach ($value as $item) {
				$id = (int)$item;
				if ($id > 0) {
					$ids[] = $id;
				}
			}
		} elseif (is_string($value) && $value !== '') {
			foreach (explode(',', $value) as $item) {
				$id = (int)trim($item);
				if ($id > 0) {
					$ids[] = $id;
				}
			}
		}

		return array_values(array_unique($ids));
	}

	/**
	 * Читает DEFAULT всех параметров из .parameters.php.
	 * Результат кешируется в статическом свойстве — .parameters.php выполняет
	 * выборку списков доставок/оплат из БД, повторять её на каждый хит не нужно.
	 *
	 * @return array
	 */
	protected function getDefaultParams(): array
	{
		if (static::$defaultParamsCache !== null) {
			return static::$defaultParamsCache;
		}

		$defaults = [];
		$path = __DIR__ . '/.parameters.php';
		if (file_exists($path)) {
			$arComponentParameters = [];
			include $path;
			if (!empty($arComponentParameters['PARAMETERS']) && is_array($arComponentParameters['PARAMETERS'])) {
				foreach ($arComponentParameters['PARAMETERS'] as $key => $param) {
					if (array_key_exists('DEFAULT', $param)) {
						$defaults[$key] = $param['DEFAULT'];
					}
				}
			}
		}

		static::$defaultParamsCache = $defaults;
		return $defaults;
	}

	public function executeComponent()
	{
		if (!Loader::includeModule('sale')) {
			$this->arResult['ERROR'] = 'Модуль sale не подключен.';
			$this->includeComponentTemplate();
			return;
		}

		$this->collectFilter();

		// Экспорт в Excel: отдаём CSV и завершаем выполнение до вывода шаблона
		if ($this->isExportEnabled() && Context::getCurrent()->getRequest()->getQuery('EXPORT') === 'excel') {
			$this->exportToExcel();
		}

		$this->arResult = $this->collectData();

		$this->includeComponentTemplate();
	}

	/**
		* Конфигурация AJAX-действий (Engine Controllerable).
		*
		* @return array
		*/
	public function configureActions()
	{
		return [
			'getList' => [
				'prefilters' => [],
			],
		];
	}

	/**
		* AJAX: получение списка заказов по фильтру/странице без перезагрузки страницы.
		* Вызывается через BX.ajax.runComponentAction('ldo:orders.list', 'getList', {mode: 'class', data: {...}}).
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

		$rawStatuses = $request->getPost('STATUS');
		$filterStatus = [];
		if (is_array($rawStatuses)) {
			foreach ($rawStatuses as $status) {
				$status = trim((string)$status);
				if ($status !== '') {
					$filterStatus[] = $status;
				}
			}
		} else {
			$status = trim((string)$rawStatuses);
			if ($status !== '') {
				$filterStatus[] = $status;
			}
		}

		$rawDelivery = $request->getPost('DELIVERY');
		$filterDelivery = is_array($rawDelivery)
			? array_values(array_filter(array_map('intval', $rawDelivery)))
			: ((int)$rawDelivery > 0 ? [(int)$rawDelivery] : []);

		$rawPaySystem = $request->getPost('PAY_SYSTEM');
		$filterPaySystem = is_array($rawPaySystem)
			? array_values(array_filter(array_map('intval', $rawPaySystem)))
			: ((int)$rawPaySystem > 0 ? [(int)$rawPaySystem] : []);

		$filterRestaurant = trim((string)$request->getPost('RESTAURANT'));

		$this->applyFilterValues(
			$filterStatus,
			(string)$request->getPost('DATE_FROM'),
			(string)$request->getPost('DATE_TO'),
			(string)$request->getPost('SEARCH'),
			$filterDelivery,
			$filterPaySystem,
			$filterRestaurant
		);

		// Параметры "какие способы выводить в фильтре" — при AJAX (mode=class)
		// компонент создаётся без параметров, поэтому передаём их из JS
		$this->allowedDeliveryIds = $this->normalizeIdList($request->getPost('DELIVERY_SERVICES'));
		$this->allowedPaySystemIds = $this->normalizeIdList($request->getPost('PAY_SYSTEMS'));

		// При AJAX (mode=class) компонент создаётся заново без параметров —
		// принимаем PAGE_SIZE из запроса (JS передаёт значение параметра компонента)
		$pageSize = (int)$request->getPost('PAGE_SIZE');
		if ($pageSize > 0) {
			$this->arParams['PAGE_SIZE'] = $pageSize;
		}

		$page = max(1, (int)$request->getPost('PAGE'));

		return [
			'success' => true,
			'data'    => $this->collectData($page),
		];
	}

	/**
		* Разрешена ли выгрузка (параметр компонента EXPORT_ENABLED).
	 *
	 * @return bool
	 */
	protected function isExportEnabled(): bool
	{
		return !isset($this->arParams['EXPORT_ENABLED']) || $this->arParams['EXPORT_ENABLED'] !== 'N';
	}

	/**
	 * Разбор фильтров из GET-запроса (STATUS, DATE_FROM, DATE_TO, SEARCH).
	 */
	protected function collectFilter()
	{
		$request = Context::getCurrent()->getRequest();

		$rawStatuses = $request->getQuery('STATUS');
		$filterStatus = [];
		if (is_array($rawStatuses)) {
			foreach ($rawStatuses as $status) {
				$status = trim((string)$status);
				if ($status !== '') {
					$filterStatus[] = $status;
				}
			}
		} else {
			$status = trim((string)$rawStatuses);
			if ($status !== '') {
				$filterStatus[] = $status;
			}
		}

		$rawDelivery = $request->getQuery('DELIVERY');
		$filterDelivery = is_array($rawDelivery)
			? array_values(array_filter(array_map('intval', $rawDelivery)))
			: ((int)$rawDelivery > 0 ? [(int)$rawDelivery] : []);

		$rawPaySystem = $request->getQuery('PAY_SYSTEM');
		$filterPaySystem = is_array($rawPaySystem)
			? array_values(array_filter(array_map('intval', $rawPaySystem)))
			: ((int)$rawPaySystem > 0 ? [(int)$rawPaySystem] : []);

		$filterRestaurant = trim((string)$request->getQuery('RESTAURANT'));

		$this->applyFilterValues(
			$filterStatus,
			(string)$request->getQuery('DATE_FROM'),
			(string)$request->getQuery('DATE_TO'),
			(string)$request->getQuery('SEARCH'),
			$filterDelivery,
			$filterPaySystem,
			$filterRestaurant
		);
	}

	/**
	 * Установка значений фильтра (общий для страницы и AJAX).
	 *
	 * @param array  $filterStatus
	 * @param string $dateFromRaw
	 * @param string $dateToRaw
	 * @param string $search
	 * @param array  $filterDelivery
	 * @param array  $filterPaySystem
	 */
	protected function applyFilterValues(array $filterStatus, $dateFromRaw, $dateToRaw, $search, array $filterDelivery = [], array $filterPaySystem = [], $filterRestaurant = '')
	{
		$this->filterStatus = array_values(array_unique($filterStatus));
		$this->filterDelivery = array_values(array_unique(array_map('intval', $filterDelivery)));
		$this->filterPaySystem = array_values(array_unique(array_map('intval', $filterPaySystem)));
		$this->filterRestaurant = trim((string)$filterRestaurant);

		$this->dateFromRaw = trim((string)$dateFromRaw);
		$this->dateToRaw   = trim((string)$dateToRaw);
		$this->search      = trim((string)$search);

		$this->dateFrom = null;
		$this->dateTo   = null;
		if ($this->dateFromRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dateFromRaw)) {
			$this->dateFrom = new DateTime($this->dateFromRaw . ' 00:00:00', 'Y-m-d H:i:s');
		}
		if ($this->dateToRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dateToRaw)) {
			$this->dateTo = new DateTime($this->dateToRaw . ' 23:59:59', 'Y-m-d H:i:s');
		}
	}

	/**
	 * Список статусов заказов для текущего языка (штатный метод Битрикс).
	 *
	 * @return array
	 */
	protected function getStatuses(): array
	{
		try {
			return OrderStatus::getAllStatusesNames();
		} catch (\Throwable $e) {
			return [];
		}
	}

	/**
	 * Список служб доставки: ID => название (b_sale_delivery_srv).
	 *
	 * @return array
	 */
	protected function getDeliveryServices(): array
	{
		$services = [];
		try {
			$rs = DeliveryServicesTable::getList([
				'select' => ['ID', 'NAME'],
				'order'  => ['SORT' => 'ASC', 'ID' => 'ASC'],
			]);
			while ($row = $rs->fetch()) {
				$services[(int)$row['ID']] = (string)$row['NAME'];
			}
		} catch (\Throwable $e) {
			$services = [];
		}

		return $services;
	}

	/**
	 * Службы доставки для селекта фильтра: только выбранные в параметрах компонента
	 * (DELIVERY_SERVICES). Если параметр не задан — все.
	 *
	 * @return array
	 */
	protected function getFilterDeliveryServices(): array
	{
		$services = $this->getDeliveryServices();
		if (!empty($this->allowedDeliveryIds)) {
			$services = array_intersect_key($services, array_flip($this->allowedDeliveryIds));
		}

		return $services;
	}

	/**
	 * Список платёжных систем: ID => название (b_sale_pay_system_action).
	 *
	 * @return array
	 */
	protected function getPaySystems(): array
	{
		$systems = [];
		try {
			$rs = PaySystemActionTable::getList([
				'select' => ['ID', 'NAME'],
				'order'  => ['SORT' => 'ASC', 'ID' => 'ASC'],
			]);
			while ($row = $rs->fetch()) {
				$systems[(int)$row['ID']] = (string)$row['NAME'];
			}
		} catch (\Throwable $e) {
			$systems = [];
		}

		return $systems;
	}

	/**
	 * Платёжные системы для селекта фильтра: только выбранные в параметрах компонента
	 * (PAY_SYSTEMS). Если параметр не задан — все.
	 *
	 * @return array
	 */
	protected function getFilterPaySystems(): array
	{
		$systems = $this->getPaySystems();
		if (!empty($this->allowedPaySystemIds)) {
			$systems = array_intersect_key($systems, array_flip($this->allowedPaySystemIds));
		}

		return $systems;
	}

	/**
	 * Список ресторанов для фильтра: XML_ID => название (активные из ldo_delivery_restaurants).
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
			// Только рестораны текущего сайта (заказы тоже выбираются по LID = SITE_ID)
			$list = \Ldo\Deliverymap\RestaurantsTable::getActiveList(
				['=SITE_ID' => SITE_ID],
				['NAME' => 'ASC']
			);
			foreach ($list as $r) {
				$xmlId = (string)($r['XML_ID'] ?? '');
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
	 * Поля выборки заказа + связанного пользователя.
	 *
	 * @return array
	 */
	protected function getOrderSelect(): array
	{
		return [
			'ID', 'ACCOUNT_NUMBER', 'DATE_INSERT', 'PRICE', 'DISCOUNT_ALL', 'STATUS_ID', 'LID',
			'DELIVERY_ID', 'PAY_SYSTEM_ID',
			'USER_ID', 'USER.NAME', 'USER.LAST_NAME', 'USER.LOGIN', 'USER.EMAIL',
		];
	}

	/**
	 * ID заказов, найденных по свойствам заказа (ФИО / телефон / e-mail покупателя).
	 * Выполняется отдельно, чтобы не дублировать строки INNER-join'ом свойства PROPERTY.
	 */
	protected function collectSearchOrderIds()
	{
		$this->searchOrderIds = [];
		if ($this->search === '') {
			return;
		}

		$rsSearchProps = OrderPropsValueTable::getList([
			'select' => ['ORDER_ID'],
			'filter' => ['%VALUE' => $this->search],
		]);
		$ids = [];
		while ($sp = $rsSearchProps->fetch()) {
			$ids[(int)$sp['ORDER_ID']] = true;
		}
		$this->searchOrderIds = array_keys($ids);
	}

	/**
		* ID заказов, у которых свойство заказа RESTORAN_ID (XML_ID) равно выбранному ресторану.
		*/
	protected function collectRestaurantOrderIds()
	{
		$this->restaurantOrderIds = [];
		if ($this->filterRestaurant === '') {
			return;
		}

		$rs = OrderPropsValueTable::getList([
			'select' => ['ORDER_ID'],
			'filter' => ['=CODE' => 'RESTORAN_ID', '=VALUE' => $this->filterRestaurant],
		]);
		$ids = [];
		while ($p = $rs->fetch()) {
			$ids[(int)$p['ORDER_ID']] = true;
		}
		$this->restaurantOrderIds = array_keys($ids);
	}

	/**
	 * Применение фильтров к ORM-запросу (методы Битрикс: whereIn / where / whereLike / OR-логика).
	 *
	 * @param Query $query
	 */
	protected function applyFilters(Query $query)
	{
		$query->where('LID', SITE_ID);

		if (!empty($this->filterStatus)) {
			$query->whereIn('STATUS_ID', $this->filterStatus);
		}
		if (!empty($this->filterDelivery)) {
			$query->whereIn('DELIVERY_ID', $this->filterDelivery);
		}
		if (!empty($this->filterPaySystem)) {
			$query->whereIn('PAY_SYSTEM_ID', $this->filterPaySystem);
		}
		if ($this->filterRestaurant !== '') {
			if (!empty($this->restaurantOrderIds)) {
				$query->whereIn('ID', $this->restaurantOrderIds);
			} else {
				// Ресторан выбран, но заказов с ним нет — возвращаем пустой список
				$query->where('ID', -1);
			}
		}
		if ($this->dateFrom !== null) {
			$query->where('DATE_INSERT', '>=', $this->dateFrom);
		}
		if ($this->dateTo !== null) {
			$query->where('DATE_INSERT', '<=', $this->dateTo);
		}
		if ($this->search !== '') {
			// Поиск: № заказа ИЛИ клиент (имя/логин/e-mail) ИЛИ свойства заказа (ФИО/телефон/почта)
			$or = Query::filter()
				->logic('or')
				->whereLike('ACCOUNT_NUMBER', '%' . $this->search . '%')
				->whereLike('USER.LOGIN', '%' . $this->search . '%')
				->whereLike('USER.NAME', '%' . $this->search . '%')
				->whereLike('USER.LAST_NAME', '%' . $this->search . '%')
				->whereLike('USER.EMAIL', '%' . $this->search . '%');
			if (!empty($this->searchOrderIds)) {
				$or->whereIn('ID', $this->searchOrderIds);
			}
			$query->where($or);
		}
	}

	/**
	 * Телефон/e-mail из свойств заказа (один запрос для всех заказов).
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
	 * Состав заказов (позиции корзины) — одним запросом для переданных заказов.
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
			'select' => ['ID', 'ORDER_ID', 'NAME', 'PRICE', 'QUANTITY', 'WEIGHT', 'MEASURE_NAME', 'SUMMARY_PRICE'],
			'filter' => ['=ORDER_ID' => $orderIds],
			'order'  => ['ID' => 'ASC'],
		]);
		while ($basket = $rsBasket->fetch()) {
			$baskets[$basket['ORDER_ID']][] = $basket;
		}

		return $baskets;
	}

	/**
	 * Стоимость доставки (несистемные отгрузки) — одним запросом.
	 *
	 * @param array $orderIds
	 * @return array
	 */
	protected function loadDeliverySum(array $orderIds): array
	{
		$deliverySum = [];
		if (empty($orderIds)) {
			return $deliverySum;
		}

		$rsShipments = ShipmentTable::getList([
			'select' => ['ORDER_ID', 'PRICE_DELIVERY'],
			'filter' => ['=ORDER_ID' => $orderIds, '=SYSTEM' => 'N'],
		]);
		while ($shipment = $rsShipments->fetch()) {
			$deliverySum[$shipment['ORDER_ID']] = ($deliverySum[$shipment['ORDER_ID']] ?? 0) + (float)$shipment['PRICE_DELIVERY'];
		}

		return $deliverySum;
	}

	/**
	 * Форматирование даты для вывода.
	 *
	 * @param mixed $value
	 * @return string
	 */
	protected function fmtDate($value): string
	{
		if ($value instanceof DateTime) {
			return $value->format('d.m.Y H:i');
		}
		if (is_object($value) && method_exists($value, 'format')) {
			return $value->format('d.m.Y H:i');
		}
		if (is_string($value) && $value !== '') {
			return htmlspecialchars($value);
		}

		return '—';
	}

	/**
	 * Формирование ФИО клиента из полей пользователя.
	 *
	 * @param array $o
	 * @return string
	 */
	protected function fmtFio(array $o): string
	{
		$fio = trim(trim((string)($o['USER_LAST_NAME'] ?? '')) . ' ' . trim((string)($o['USER_NAME'] ?? '')));
		if ($fio === '') {
			$fio = trim((string)($o['USER_LOGIN'] ?? ''));
		}

		return $fio;
	}

	/**
	 * Форматирование веса (граммы/килограммы).
	 *
	 * @param mixed $weight
	 * @return string
	 */
	protected function fmtWeight($weight): string
	{
		$weight = (float)$weight;
		if ($weight <= 0) {
			return '—';
		}
		if ($weight >= 1000) {
			return rtrim(rtrim(number_format($weight / 1000, 2, ',', ' '), '0'), ',') . ' кг';
		}

		return rtrim(rtrim(number_format($weight, 1, ',', ' '), '0'), ',') . ' г';
	}

	/**
	 * Сбор данных для страницы/AJAX: статусы, заказы, свойства, корзины, отгрузки, пагинация.
	 *
	 * @param int|null $page Номер страницы для AJAX; null — определить из URI.
	 * @return array
	 */
	protected function collectData(int $page = null): array
	{
		$this->statuses = $this->getStatuses();
		$this->collectSearchOrderIds();
		$this->collectRestaurantOrderIds();

		$orderSelect = $this->getOrderSelect();
		$pageSize    = max(1, (int)($this->arParams['PAGE_SIZE'] ?? 50));

		$nav = new PageNavigation('orders');
		$nav->allowAllRecords(false)->setPageSize($pageSize);
		if ($page !== null && $page > 0) {
			$nav->setCurrentPage($page);
		} else {
			$nav->initFromUri();
		}

		$query = OrderTable::query()
			->setSelect($orderSelect)
			->setOrder(['DATE_INSERT' => 'DESC']);
		$this->applyFilters($query);
		$query
			->countTotal(true)
			->setOffset($nav->getOffset())
			->setLimit($nav->getLimit());

		$rsOrders = $query->exec();
		$nav->setRecordCount($rsOrders->getCount());

		$orders = [];
		while ($o = $rsOrders->fetch()) {
			// ORM D7 возвращает ключи связей как SALE_INTERNALS_ORDER_USER_* —
			// приводим к читаемым USER_* (используются в шаблонах)
			foreach (['NAME', 'LAST_NAME', 'LOGIN', 'EMAIL'] as $userField) {
				$srcKey = 'SALE_INTERNALS_ORDER_USER_' . $userField;
				if (array_key_exists($srcKey, $o) && !array_key_exists('USER_' . $userField, $o)) {
					$o['USER_' . $userField] = $o[$srcKey];
				}
			}
			// Дата к строке (для JSON/шаблона)
			if (isset($o['DATE_INSERT']) && is_object($o['DATE_INSERT']) && method_exists($o['DATE_INSERT'], 'format')) {
				$o['DATE_INSERT'] = $o['DATE_INSERT']->format('d.m.Y H:i');
			}
			$orders[] = $o;
		}

		$orderIds = array_column($orders, 'ID');

		// Параметры для ссылок пагинации/экспорта (фильтр сохраняется)
		$baseParams = [];
		if (!empty($this->filterStatus)) {
			$baseParams['STATUS'] = $this->filterStatus[0];
		}
		if (!empty($this->filterDelivery)) {
			$baseParams['DELIVERY'] = $this->filterDelivery[0];
		}
		if (!empty($this->filterPaySystem)) {
			$baseParams['PAY_SYSTEM'] = $this->filterPaySystem[0];
		}
		if ($this->filterRestaurant !== '') {
			$baseParams['RESTAURANT'] = $this->filterRestaurant;
		}
		if ($this->dateFromRaw !== '') {
			$baseParams['DATE_FROM'] = $this->dateFromRaw;
		}
		if ($this->dateToRaw !== '') {
			$baseParams['DATE_TO'] = $this->dateToRaw;
		}
		if ($this->search !== '') {
			$baseParams['SEARCH'] = $this->search;
		}

		return [
			'ORDERS'           => $orders,
			'STATUSES'         => $this->statuses,
			// Полные карты — для колонок таблицы (названия у ВСЕХ заказов)
			'DELIVERY_SERVICES'=> $this->getDeliveryServices(),
			'PAY_SYSTEMS'      => $this->getPaySystems(),
			// Отфильтрованные по параметрам карты — только для селектов фильтра
			'FILTER_DELIVERY'  => $this->getFilterDeliveryServices(),
			'FILTER_PAY'       => $this->getFilterPaySystems(),
			'FILTER_RESTAURANTS' => $this->getRestaurants(),
			'ORDER_PROPS'      => $this->loadOrderProps($orderIds),
			'BASKETS'          => $this->loadBaskets($orderIds),
			'DELIVERY_SUM'     => $this->loadDeliverySum($orderIds),
			'FILTER'           => [
				'STATUS'     => $this->filterStatus,
				'DELIVERY'   => $this->filterDelivery,
				'PAY_SYSTEM' => $this->filterPaySystem,
				'RESTAURANT' => $this->filterRestaurant,
				'DATE_FROM'  => $this->dateFromRaw,
				'DATE_TO'    => $this->dateToRaw,
				'SEARCH'     => $this->search,
			],
			'NAV'              => [
				'TOTAL_COUNT'  => (int)$nav->getRecordCount(),
				'PAGE_COUNT'   => $nav->getPageCount(),
				'CURRENT_PAGE' => $nav->getCurrentPage(),
			],
			'BASE_PARAMS'      => $baseParams,
			// id навигации PageNavigation ('orders') — initFromUri() читает именно этот GET-параметр
			'PAGE_PARAM'       => $nav->getId(),
			'PAGE_SIZE'        => $pageSize,
			'EXPORT_ENABLED'   => $this->isExportEnabled(),
		];
	}

	/**
	 * Выгрузка в Excel (CSV с BOM — открывается в MS Excel).
	 * Завершает выполнение скрипта.
	 */
	protected function exportToExcel()
	{
		global $APPLICATION;

		$this->collectSearchOrderIds();

		$query = OrderTable::query()
			->setSelect($this->getOrderSelect())
			->setOrder(['DATE_INSERT' => 'DESC']);
		$this->applyFilters($query);

		$orders = [];
		$rsOrders = $query->exec();
		while ($o = $rsOrders->fetch()) {
			$orders[] = $o;
		}

		$orderProps = $this->loadOrderProps(array_column($orders, 'ID'));

		$sep  = ';';
		$rows = [];
		$rows[] = ['№ заказа', 'Дата', 'Клиент', 'Телефон', 'Email', 'Сумма', 'Статус'];
		foreach ($orders as $o) {
			$number = (string)($o['ACCOUNT_NUMBER'] !== '' ? $o['ACCOUNT_NUMBER'] : $o['ID']);
			$props  = $orderProps[$o['ID']] ?? [];
			$fio    = ($props['FIO'] ?? '') !== '' ? $props['FIO'] : $this->fmtFio($o);
			$phone  = $props['PHONE'] ?? '';
			$email  = ($props['EMAIL'] ?? '') !== '' ? $props['EMAIL'] : (string)($o['USER_EMAIL'] ?? '');
			$rows[] = [
				$number,
				$this->fmtDate($o['DATE_INSERT']),
				$fio,
				$phone,
				$email,
				number_format((float)$o['PRICE'], 2, ',', ' '),
				(string)($this->statuses[$o['STATUS_ID']] ?? $o['STATUS_ID']),
			];
		}

		$APPLICATION->RestartBuffer();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="zakazy_' . date('Y-m-d_H-i') . '.csv"');
		$out = fopen('php://output', 'w');
		fwrite($out, "\xEF\xBB\xBF"); // BOM для корректной кириллицы в Excel
		foreach ($rows as $row) {
			fputcsv($out, $row, $sep);
		}
		fclose($out);
		die();
	}
}
