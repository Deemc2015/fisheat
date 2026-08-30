<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Context;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\Sale\Internals\BasketTable;
use Bitrix\Sale\Internals\OrderPropsValueTable;
use Bitrix\Sale\Internals\OrderTable;
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

	/** @var array Карта статусов: ID => название */
	protected $statuses = [];

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
		return array_merge($this->getDefaultParams(), (array)$arParams);
	}

	/**
	 * Читает DEFAULT всех параметров из .parameters.php.
	 *
	 * @return array
	 */
	protected function getDefaultParams(): array
	{
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

		$this->applyFilterValues(
			$filterStatus,
			(string)$request->getPost('DATE_FROM'),
			(string)$request->getPost('DATE_TO'),
			(string)$request->getPost('SEARCH')
		);

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

		$this->applyFilterValues(
			$filterStatus,
			(string)$request->getQuery('DATE_FROM'),
			(string)$request->getQuery('DATE_TO'),
			(string)$request->getQuery('SEARCH')
		);
	}

	/**
	 * Установка значений фильтра (общий для страницы и AJAX).
	 *
	 * @param array  $filterStatus
	 * @param string $dateFromRaw
	 * @param string $dateToRaw
	 * @param string $search
	 */
	protected function applyFilterValues(array $filterStatus, $dateFromRaw, $dateToRaw, $search)
	{
		$this->filterStatus = array_values(array_unique($filterStatus));

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
	 * Поля выборки заказа + связанного пользователя.
	 *
	 * @return array
	 */
	protected function getOrderSelect(): array
	{
		return [
			'ID', 'ACCOUNT_NUMBER', 'DATE_INSERT', 'PRICE', 'DISCOUNT_ALL', 'STATUS_ID', 'LID',
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
			'ORDERS'      => $orders,
			'STATUSES'    => $this->statuses,
			'ORDER_PROPS' => $this->loadOrderProps($orderIds),
			'BASKETS'     => $this->loadBaskets($orderIds),
			'DELIVERY_SUM'=> $this->loadDeliverySum($orderIds),
			'FILTER'      => [
				'STATUS'    => $this->filterStatus,
				'DATE_FROM' => $this->dateFromRaw,
				'DATE_TO'   => $this->dateToRaw,
				'SEARCH'    => $this->search,
			],
			'NAV'         => [
				'TOTAL_COUNT'  => (int)$nav->getRecordCount(),
				'PAGE_COUNT'   => $nav->getPageCount(),
				'CURRENT_PAGE' => $nav->getCurrentPage(),
			],
			'BASE_PARAMS' => $baseParams,
			// id навигации PageNavigation ('orders') — initFromUri() читает именно этот GET-параметр
			'PAGE_PARAM'  => $nav->getId(),
			'PAGE_SIZE'   => $pageSize,
			'EXPORT_ENABLED' => $this->isExportEnabled(),
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
