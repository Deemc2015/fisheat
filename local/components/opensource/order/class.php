<?php

use Bitrix\Main\Context;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Result;
use Bitrix\Main\SystemException;
use Bitrix\Sale;
use Bitrix\Sale\Basket;
use Bitrix\Sale\BasketItem;
use Bitrix\Sale\Fuser;
use Bitrix\Sale\Order;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PropertyValue;
use Bitrix\Sale\Shipment;
use Bitrix\Sale\ShipmentCollection;
use Bitrix\Sale\ShipmentItem;
use Bitrix\Sale\ShipmentItemCollection;
use Bitrix\Sale\Delivery;
use OpenSource\Order\ErrorCollection;
use OpenSource\Order\OrderHelper;
use Bitrix\Sale\PaySystem;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Ldo\Develop\Hlblock;
use Ldo\Deliverymap\DeliveryZoneTable;


class OpenSourceOrderComponent extends CBitrixComponent implements  Controllerable
{
    /**
     * @var Order
     */
    public $order;

    /**
     * @var ErrorCollection
     */
    public $errorCollection;

    protected $personTypes = [];

    /** @var bool Свойство ADDRESS_ID проверено/создано в текущем запросе */
    protected static $addressIdPropEnsured = false;

    /** @var bool Службы доставки проверены/переключены в текущем запросе */
    protected static $zoneServicesEnsured = false;

    /**
     * CustomOrder constructor.
     * @param CBitrixComponent|null $component
     * @throws Bitrix\Main\LoaderException
     */
    public function __construct(CBitrixComponent $component = null)
    {
        parent::__construct($component);

        Loader::includeModule('sale');
        Loader::includeModule('catalog');
        Loader::includeModule('opensource.order');

        $this->errorCollection = new ErrorCollection();
    }

    public function onIncludeComponentLang()
    {
        Loc::loadLanguageFile(__FILE__);
    }

    public function onPrepareComponentParams($arParams = []): array
    {
        if (isset($arParams['DEFAULT_PERSON_TYPE_ID']) && (int)$arParams['DEFAULT_PERSON_TYPE_ID'] > 0) {
            $arParams['DEFAULT_PERSON_TYPE_ID'] = (int)$arParams['DEFAULT_PERSON_TYPE_ID'];
        } else {
            $arPersonTypes = $this->getPersonTypes();
            $arPersonType = reset($arPersonTypes);
            if (is_array($arPersonType)) {
                $arParams['DEFAULT_PERSON_TYPE_ID'] = (int)reset($arPersonTypes)['ID'];
            } else {
                $arParams['DEFAULT_PERSON_TYPE_ID'] = 1;
            }
        }

        if (isset($this->request['person_type_id']) && (int)$this->request['person_type_id'] > 0) {
            $arParams['PERSON_TYPE_ID'] = (int)$this->request['person_type_id'];
        } else {
            $arParams['PERSON_TYPE_ID'] = $arParams['DEFAULT_PERSON_TYPE_ID'];
        }

        if (isset($arParams['SAVE'])) {
            $arParams['SAVE'] = $arParams['SAVE'] === 'Y';
        } elseif (isset($this->request['save'])) {
            $arParams['SAVE'] = $this->request['save'] === 'y';
        } else {
            $arParams['SAVE'] = false;
        }

        return $arParams;
    }

    /**
     * @return array
     */
    public function getPersonTypes(): array
    {
        if (empty($this->personTypes)) {
            $personType = new CSalePersonType();
            $rsPersonTypes = $personType->GetList(['SORT' => 'ASC']);
            while ($arPersonType = $rsPersonTypes->Fetch()) {
                $arPersonType['ID'] = (int)$arPersonType['ID'];
                $this->personTypes[$arPersonType['ID']] = $arPersonType;
            }
        }

        return $this->personTypes;
    }

    /**
     * @param int $personTypeId
     * @return Order
     * @throws Exception
     */
    public function createVirtualOrder(int $personTypeId)
    {
        global $USER;

        if (!isset($this->getPersonTypes()[$personTypeId])) {
            throw new RuntimeException(Loc::getMessage('OPEN_SOURCE_ORDER_UNKNOWN_PERSON_TYPE'));
        }

        $siteId = Context::getCurrent()
            ->getSite();

        $basketItems = Basket::loadItemsForFUser(Fuser::getId(), $siteId)
            ->getOrderableItems();

        if (count($basketItems) === 0) {
            throw new LengthException(Loc::getMessage('OPEN_SOURCE_ORDER_EMPTY_BASKET'));
        }

        $this->order = Order::create($siteId, $USER->GetID());
        $this->order->setPersonTypeId($personTypeId);
        $this->order->setBasket($basketItems);

        return $this->order;
    }

    /**
     * @param array $propertyValues
     * @throws Exception
     */
    public function setOrderProperties(array $propertyValues)
    {
        foreach ($this->order->getPropertyCollection() as $prop) {
            /**
             * @var PropertyValue $prop
             */
            // Служебное свойство ADDRESS_ID (выбранный адрес доставки) разрешаем заполнять,
            // остальные служебные свойства пропускаем
            if ($prop->isUtil() && $prop->getField('CODE') !== 'ADDRESS_ID') {
                continue;
            }

            addMessage2Log($prop);

            $value = $propertyValues[$prop->getField('CODE')] ?? null;

            if (empty($value)) {
                $value = $prop->getProperty()['DEFAULT_VALUE'];
            }

            if (!empty($value)) {
                $prop->setValue($value);
            }
        }
    }

    /**
     * @param int $deliveryId
     * @return Shipment
     * @throws Exception
     */
    public function createOrderShipment(int $deliveryId = 0)
    {
        // Модуль зон доставки (класс службы ZoneDelivery)
        Loader::includeModule('ldo.deliverymap');
        // Самовосстановление: «Доставка» должна использовать ZoneDelivery
        $this->ensureZoneDeliveryService($deliveryId);

        /* @var $shipmentCollection ShipmentCollection */
        $shipmentCollection = $this->order->getShipmentCollection();

        if ($deliveryId > 0) {
            $shipment = $shipmentCollection->createItem(
                $this->getDeliveryServiceObject($deliveryId)
            );
        } else {
            $shipment = $shipmentCollection->createItem();
        }

        /** @var $shipmentItemCollection ShipmentItemCollection */
        $shipmentItemCollection = $shipment->getShipmentItemCollection();
        $shipment->setField('CURRENCY', $this->order->getCurrency());

        foreach ($this->order->getBasket()->getOrderableItems() as $basketItem) {
            /**
             * @var $basketItem BasketItem
             * @var $shipmentItem ShipmentItem
             */
            $shipmentItem = $shipmentItemCollection->createItem($basketItem);
            $shipmentItem->setQuantity($basketItem->getQuantity());
        }

        return $shipment;
    }

    /**
     * Пересчитывает стоимость доставки по зоне выбранного адреса
     * (через службу доставки ZoneDelivery) перед сохранением заказа.
     *
     * @return float Цена доставки
     * @throws Exception
     */
    public function calculateShipmentDelivery()
    {
        // Модуль зон доставки (класс службы ZoneDelivery)
        Loader::includeModule('ldo.deliverymap');

        $shipment = OrderHelper::getFirstNonSystemShipment($this->order);
        if ($shipment === null) {
            return 0;
        }

        $delivery = $shipment->getDelivery();
        if (!$delivery instanceof Delivery\Services\Base) {
            return 0;
        }

        // Самовывоз — доставка бесплатна
        $deliveryName = mb_strtolower((string)$delivery->getNameWithParent());
        if ($deliveryName === 'самовывоз' || mb_strtolower((string)$delivery->getName()) === 'самовывоз') {
            $shipment->setBasePriceDelivery(0);
            $shipment->setField('PRICE_DELIVERY', 0);
            return 0;
        }

        $calculationResult = $delivery->calculate($shipment);
        if ($calculationResult->isSuccess()) {
            $price = (float)$calculationResult->getPrice();
            $shipment->setBasePriceDelivery($price);
            $shipment->setField('PRICE_DELIVERY', $price);
            return $price;
        }

        return 0;
    }

    /**
     * Гарантирует наличие служебного свойства заказа ADDRESS_ID.
     * Создаёт его для всех типов плательщиков, если его ещё нет.
     *
     * @return void
     */
    public function ensureAddressIdProperty()
    {
        if (self::$addressIdPropEnsured) {
            return;
        }
        self::$addressIdPropEnsured = true;

        if (!Loader::includeModule('sale')) {
            return;
        }

        $personTypes = \Bitrix\Sale\Internals\PersonTypeTable::getList(['select' => ['ID']])->fetchAll();

        foreach ($personTypes as $pt) {
            $personTypeId = (int)$pt['ID'];

            $exists = \Bitrix\Sale\Internals\OrderPropsTable::getList([
                'filter' => ['=CODE' => 'ADDRESS_ID', '=PERSON_TYPE_ID' => $personTypeId],
                'select' => ['ID'],
            ])->fetch();
            if ($exists) {
                continue;
            }

            $groupId = 0;
            $group = \Bitrix\Sale\Internals\OrderPropsGroupTable::getList([
                'select' => ['ID'],
                'filter' => ['=PERSON_TYPE_ID' => $personTypeId],
                'order' => ['ID' => 'ASC'],
                'limit' => 1,
            ])->fetch();
            if ($group) {
                $groupId = (int)$group['ID'];
            } else {
                $groupId = (int)(new \CSaleOrderPropsGroup())->Add([
                    'PERSON_TYPE_ID' => $personTypeId,
                    'NAME' => 'Служебные',
                    'SORT' => 700,
                ]);
            }

            $prop = new \CSaleOrderProps();
            $prop->Add([
                'PERSON_TYPE_ID' => $personTypeId,
                'NAME' => 'ID адреса доставки',
                'CODE' => 'ADDRESS_ID',
                'TYPE' => 'STRING',
                'REQUIED' => 'N',
                'DEFAULT_VALUE' => '',
                'SORT' => 700,
                'USER_PROPS' => 'N',
                'IS_LOCATION' => 'N',
                'PROPS_GROUP_ID' => $groupId,
                'DESCRIPTION' => '',
                'IS_EMAIL' => 'N',
                'IS_PROFILE_NAME' => 'N',
                'IS_PAYER' => 'N',
                'IS_ZIP' => 'N',
                'IS_PHONE' => 'N',
                'UTIL' => 'Y',
                'SETTINGS' => ['SIZE' => 30, 'ROWS' => 1],
                'ENTITY_REGISTRY_TYPE' => 'ORDER',
            ]);
        }
    }

    /**
     * Гарантирует, что служба «Доставка» (не «Самовывоз») использует класс ZoneDelivery.
     * Самовосстановление, чтобы не зависеть от ручного запуска миграции.
     *
     * @param int $deliveryId
     * @return void
     */
    private function ensureZoneDeliveryService($deliveryId)
    {
        if ($deliveryId <= 0 || !Loader::includeModule('ldo.deliverymap')) {
            return;
        }

        $row = \Bitrix\Sale\Delivery\Services\Table::getRowById($deliveryId);
        if (!$row) {
            return;
        }

        // «Самовывоз» и «Без доставки» не трогаем
        $rowName = (string)($row['NAME'] ?? '');
        $rowClass = (string)($row['CLASS_NAME'] ?? '');
        if (mb_stripos($rowName, 'самовывоз') !== false
            || mb_stripos($rowClass, 'EmptyDeliveryService') !== false) {
            return;
        }

        if ($rowClass === \Ldo\Deliverymap\DeliveryServices\ZoneDelivery::class) {
            return;
        }

        \Bitrix\Sale\Delivery\Services\Table::update($deliveryId, [
            'CLASS_NAME' => \Ldo\Deliverymap\DeliveryServices\ZoneDelivery::class,
        ]);
        \Bitrix\Main\Data\Cache::clearCache(true);
    }

    /**
     * Гарантирует, что все активные службы «Доставка» (не «Самовывоз»)
     * используют класс ZoneDelivery. Самовосстановление выполняется один раз
     * на запрос, чтобы отображение доставки на форме тоже работало от зоны.
     *
     * @return void
     */
    private function ensureZoneDeliveryServices()
    {
        if (self::$zoneServicesEnsured) {
            return;
        }
        self::$zoneServicesEnsured = true;

        if (!Loader::includeModule('ldo.deliverymap')) {
            return;
        }

        $services = \Bitrix\Sale\Delivery\Services\Table::getList([
            'select' => ['ID', 'NAME', 'CLASS_NAME', 'ACTIVE', 'PARENT_ID'],
        ])->fetchAll();

        foreach ($services as $service) {
            if ((int)$service['PARENT_ID'] > 0) {
                continue;
            }
            $serviceName = (string)($service['NAME'] ?? '');
            $serviceClass = (string)($service['CLASS_NAME'] ?? '');
            if (mb_stripos($serviceName, 'самовывоз') !== false
                || mb_stripos($serviceClass, 'EmptyDeliveryService') !== false) {
                continue;
            }
            if ($serviceClass === \Ldo\Deliverymap\DeliveryServices\ZoneDelivery::class) {
                continue;
            }

            \Bitrix\Sale\Delivery\Services\Table::update((int)$service['ID'], [
                'CLASS_NAME' => \Ldo\Deliverymap\DeliveryServices\ZoneDelivery::class,
            ]);
            \Bitrix\Main\Data\Cache::clearCache(true);
        }
    }

    /**
     * Возвращает объект службы доставки, гарантируя использование ZoneDelivery
     * для службы «Доставка» (не полагаясь на кэш Manager).
     *
     * @param int $deliveryId
     * @return Delivery\Services\Base|null
     */
    private function getDeliveryServiceObject($deliveryId)
    {
        $row = \Bitrix\Sale\Delivery\Services\Table::getRowById($deliveryId);
        if (!$row) {
            return Delivery\Services\Manager::getObjectById($deliveryId);
        }

        if (mb_stripos((string)($row['NAME'] ?? ''), 'самовывоз') !== false
            || mb_stripos((string)($row['CLASS_NAME'] ?? ''), 'EmptyDeliveryService') !== false) {
            return Delivery\Services\Manager::getObjectById($deliveryId);
        }

        $className = (string)($row['CLASS_NAME'] ?? '');
        if ($className !== \Ldo\Deliverymap\DeliveryServices\ZoneDelivery::class) {
            $this->ensureZoneDeliveryService($deliveryId);
            $row = \Bitrix\Sale\Delivery\Services\Table::getRowById($deliveryId);
            $className = (string)($row['CLASS_NAME'] ?? '');
        }

        if ($className !== \Ldo\Deliverymap\DeliveryServices\ZoneDelivery::class) {
            return Delivery\Services\Manager::getObjectById($deliveryId);
        }

        return new \Ldo\Deliverymap\DeliveryServices\ZoneDelivery($row);
    }

    /**
     * @param int $paySystemId
     * @return Payment
     * @throws Exception
     */
    public function createOrderPayment(int $paySystemId)
    {
        $paymentCollection = $this->order->getPaymentCollection();
        $payment = $paymentCollection->createItem(
            Bitrix\Sale\PaySystem\Manager::getObjectById($paySystemId)
        );
        $payment->setField('SUM', $this->order->getPrice());
        $payment->setField('CURRENCY', $this->order->getCurrency());

        return $payment;
    }

    /**
     * @return Result
     *
     * @throws Exception
     */
    public function validateProperties()
    {
        $result = new Result();

        foreach ($this->order->getPropertyCollection() as $prop) {
            /**
             * @var PropertyValue $prop
             */


            if ($prop->isUtil()) {
                continue;
            }

            $r = $prop->checkRequiredValue($prop->getField('CODE'), $prop->getValue());
            if ($r->isSuccess()) {
                $r = $prop->checkValue($prop->getField('CODE'), $prop->getValue());
                if (!$r->isSuccess()) {
                    $result->addErrors($r->getErrors());
                }
            } else {
                $result->addErrors($r->getErrors());
            }
        }

        return $result;
    }

    /**
     * @return Result
     * @throws Exception
     */
    public function validateDelivery()
    {
        $result = new Result();

        $shipment = OrderHelper::getFirstNonSystemShipment($this->order);

        if ($shipment !== null) {
            if ($shipment->getDelivery() instanceof Delivery\Services\Base) {
                $obDelivery = $shipment->getDelivery();
                $availableDeliveries = Delivery\Services\Manager::getRestrictedObjectsList($shipment);
                if (!isset($availableDeliveries[$obDelivery->getId()])) {
                    $result->addError(new Error(
                        Loc::getMessage(
                            'OPEN_SOURCE_ORDER_DELIVERY_UNAVAILABLE',
                            [
                                '#DELIVERY_NAME#' => $obDelivery->getNameWithParent()
                            ]
                        ),
                        'delivery',
                        [
                            'type' => 'unavailable'
                        ]
                    ));
                }
            } else {
                $result->addError(new Error(
                    Loc::getMessage('OPEN_SOURCE_ORDER_NO_DELIVERY_SELECTED'),
                    'delivery',
                    [
                        'type' => 'undefined'
                    ]
                ));
            }
        } else {
            $result->addError(new Error(
                Loc::getMessage('OPEN_SOURCE_ORDER_SHIPMENT_NOT_FOUND'),
                'delivery',
                [
                    'type' => 'undefined'
                ]
            ));
        }

        return $result;
    }

    /**
     * @return Result
     * @throws Exception
     */
    public function validatePayment()
    {
        $result = new Result();

        if (!$this->order->getPaymentCollection()->isEmpty()) {
            $payment = $this->order->getPaymentCollection()->current();
            /**
             * @var Payment $payment
             */
            $obPaySystem = $payment->getPaySystem();
            if ($obPaySystem instanceof PaySystem\Service) {
                $availablePaySystems = PaySystem\Manager::getListWithRestrictions($payment);
                if (!isset($availablePaySystems[$payment->getPaymentSystemId()])) {
                    $result->addError(new Error(
                        Loc::getMessage(
                            'OPEN_SOURCE_ORDER_PAYMENT_UNAVAILABLE',
                            [
                                '#PAYMENT_NAME#' => $payment->getPaymentSystemName()
                            ]
                        ),
                        'payment',
                        [
                            'type' => 'unavailable'
                        ]
                    ));
                }
            } else {
                $result->addError(new Error(
                    Loc::getMessage('OPEN_SOURCE_ORDER_NO_PAY_SYSTEM_SELECTED'),
                    'payment',
                    [
                        'type' => 'undefined'
                    ]
                ));
            }
        } else {
            $result->addError(new Error(
                Loc::getMessage('OPEN_SOURCE_ORDER_NO_PAY_SYSTEM_SELECTED'),
                'payment',
                [
                    'type' => 'undefined'
                ]
            ));
        }

        return $result;
    }

    /**
     * @return Result
     * @throws Exception
     */
    public function validateOrder()
    {
        $result = new Result();

        $propValidationResult = $this->validateProperties();
        if (!$propValidationResult->isSuccess()) {
            $result->addErrors($propValidationResult->getErrors());
        }

        $deliveryValidationResult = $this->validateDelivery();
        if (!$deliveryValidationResult->isSuccess()) {
            $result->addErrors($deliveryValidationResult->getErrors());
        }

        $paymentValidationResult = $this->validatePayment();
        if (!$paymentValidationResult->isSuccess()) {
            $result->addErrors($paymentValidationResult->getErrors());
        }

        return $result;
    }

    public function executeComponent()
    {
        try {
            // Гарантируем наличие служебного свойства ADDRESS_ID (создаётся при необходимости)
            $this->ensureAddressIdProperty();
            // Гарантируем, что службы «Доставка» используют ZoneDelivery (самовосстановление)
            $this->ensureZoneDeliveryServices();

            $this->createVirtualOrder($this->arParams['PERSON_TYPE_ID']);

            $this->prefillPropertiesWithUserData();

            $propertiesList = $this->request['properties'] ?? $this->arParams['DEFAULT_PROPERTIES'] ?? [];
            if (!empty($propertiesList)) {
                $this->setOrderProperties($propertiesList);
            }

            $deliveryId = $this->request['delivery_id'] ?? $this->arParams['DEFAULT_DELIVERY_ID'] ?? 0;
            $this->createOrderShipment($deliveryId);

            $paySystemId = $this->request['pay_system_id'] ?? $this->arParams['DEFAULT_PAY_SYSTEM_ID'] ?? 0;
            if ($paySystemId > 0) {
                $this->createOrderPayment($paySystemId);
            }

            if ($this->arParams['SAVE']) {
                $validationResult = $this->validateOrder();

                if ($validationResult->isSuccess()) {
                    // Стоимость доставки по зоне выбранного адреса (служба доставки ZoneDelivery)
                    $this->calculateShipmentDelivery();

                    $saveResult = $this->order->save();
                    if (!$saveResult->isSuccess()) {
                        $this->errorCollection->add($saveResult->getErrors());
                    }
                } else {
                    $this->errorCollection->add($validationResult->getErrors());
                }
            }
        } catch (Exception $exception) {
            $this->errorCollection->setError(new Error($exception->getMessage()));
        }

        $this->includeComponentTemplate();
    }

    public function configureActions()
    {
        return [
            'addQuantity' => [
                'prefilters' => [],
            ],
            'removeCart' => [
                'prefilters' => [],
            ],
            'deleteProduct' => [
                'prefilters' => [],
            ],
            'changePromo' => [
                'prefilters' => [],
            ],
            'clearPromo' => [
                'prefilters' => [],
            ],
            'deleteAddress' => [
                'prefilters' => [],
            ],
            'addAddress' => [
                'prefilters' => [],
            ],
            'editAddress' => [
                'prefilters' => [],
            ],
            'getBasketItemData' => [
                'prefilters' => [],
            ],
            'updateDeliveryPrice' => [
                'prefilters' => [],
            ],
            'updateAddressPrice' => [
                'prefilters' => [],
            ],
        ];
    }

    /**
     * Обновление стоимости доставки при смене типа доставки
     *
     * @param array $dataDelivery Данные: deliveryId, deliveryName
     * @return array
     */
    public function updateDeliveryPriceAction($dataDelivery)
    {
        global $USER;

        $deliveryId = (int)($dataDelivery['deliveryId'] ?? 0);
        $deliveryName = trim($dataDelivery['deliveryName'] ?? '');
        $addressId = (int)($dataDelivery['addressId'] ?? 0);

        $isPickup = (mb_strtolower($deliveryName) === 'самовывоз');

        $deliveryPrice = 0;
        $baseSum = 0;
        $discount = 0;
        $totalPrice = 0;

        try {
            $basket = $this->getBasket();
            if ($basket && $basket->count() > 0) {
                // Принудительно применяем скидки для получения актуальных цен
                $fuser = new \Bitrix\Sale\Discount\Context\Fuser($basket->getFUserId(true));
                $discounts = \Bitrix\Sale\Discount::buildFromBasket($basket, $fuser);
                $discounts->calculate();
                $applyResult = $discounts->getApplyResult(true);
                $pricesWithDiscount = $applyResult['PRICES']['BASKET'] ?? [];

                foreach ($basket as $item) {
                    $basketId = $item->getId();
                    $basePrice = $item->getBasePrice();
                    $unitPrice = $pricesWithDiscount[$basketId]['PRICE'] ?? $item->getPrice();
                    $quantity = $item->getQuantity();

                    $baseSum += $basePrice * $quantity;
                    $discount += ($basePrice - $unitPrice) * $quantity;
                    $totalPrice += $unitPrice * $quantity;
                }
            }

            if (!$isPickup) {
                // Доставка — цена по зоне выбранного адреса через службу доставки (ZoneDelivery)
                if ($addressId <= 0 && Loader::includeModule('ldo.iiko')) {
                    // Если ID адреса не передан — берём «отмеченный» адрес пользователя
                    $userAddresses = \Ldo\Iiko\UserAddress::getListForUser((int)$USER->GetID());
                    if (!empty($userAddresses)) {
                        foreach ($userAddresses as $address) {
                            if (!empty($address['CHECKED'])) {
                                $addressId = (int)$address['ID'];
                                break;
                            }
                        }
                        if ($addressId <= 0) {
                            $firstAddress = reset($userAddresses);
                            $addressId = (int)$firstAddress['ID'];
                        }
                    }
                }

                $deliveryPrice = $this->getDeliveryPriceFromOrder($deliveryId, $basket, $addressId);
            }
            // При самовывозе deliveryPrice остаётся 0
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }

        // Сохраняем цену доставки в сессию для использования при обновлении корзины
        $_SESSION['LDO_DELIVERY_PRICE'] = $deliveryPrice;
        $_SESSION['LDO_IS_PICKUP'] = $isPickup ? 'Y' : 'N';

        $totalWithDelivery = $totalPrice + $deliveryPrice;

        return [
            'success' => true,
            'deliveryPrice' => $deliveryPrice,
            'baseSum' => $baseSum,
            'discount' => $discount,
            'totalPrice' => $totalWithDelivery
        ];
    }

    /**
     * Обновление итоговых сумм при выборе адреса доставки
     *
     * @param array $dataAddress Данные: addressId, deliveryPrice
     * @return array
     */
    public function updateAddressPriceAction($dataAddress)
    {
        $addressId = (int)($dataAddress['addressId'] ?? 0);
        $deliveryId = (int)($dataAddress['deliveryId'] ?? 0);

        $baseSum = 0;
        $discount = 0;
        $totalPrice = 0;

        try {
            $basket = $this->getBasket();
            if ($basket && $basket->count() > 0) {
                $fuser = new \Bitrix\Sale\Discount\Context\Fuser($basket->getFUserId(true));
                $discounts = \Bitrix\Sale\Discount::buildFromBasket($basket, $fuser);
                $discounts->calculate();
                $applyResult = $discounts->getApplyResult(true);
                $pricesWithDiscount = $applyResult['PRICES']['BASKET'] ?? [];

                foreach ($basket as $item) {
                    $basketId = $item->getId();
                    $basePrice = $item->getBasePrice();
                    $unitPrice = $pricesWithDiscount[$basketId]['PRICE'] ?? $item->getPrice();
                    $quantity = $item->getQuantity();

                    $baseSum += $basePrice * $quantity;
                    $discount += ($basePrice - $unitPrice) * $quantity;
                    $totalPrice += $unitPrice * $quantity;
                }
            }

            // Доставка — цена по зоне выбранного адреса через службу доставки (ZoneDelivery)
            $deliveryPrice = ($deliveryId > 0)
                ? $this->getDeliveryPriceFromOrder($deliveryId, $basket, $addressId)
                : 0;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }

        // Сохраняем цену доставки в сессию
        $_SESSION['LDO_DELIVERY_PRICE'] = $deliveryPrice;
        $_SESSION['LDO_IS_PICKUP'] = 'N';

        $totalWithDelivery = $totalPrice + $deliveryPrice;

        return [
            'success' => true,
            'deliveryPrice' => $deliveryPrice,
            'baseSum' => $baseSum,
            'discount' => $discount,
            'totalPrice' => $totalWithDelivery
        ];
    }

    /**
     * Поиск цены доставки по координатам (по зонам доставки)
     *
     * @param float $lat Широта
     * @param float $lon Долгота
     * @return float
     */
    private function findDeliveryPriceByCoordinates($lat, $lon)
    {
        if (!Loader::includeModule('ldo.deliverymap')) {
            return 0;
        }

        $point = [$lat, $lon];

        $zones = DeliveryZoneTable::getList([
            'filter' => ['=ACTIVE' => 'Y'],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC']
        ]);

        while ($zone = $zones->fetch()) {
            $coordinates = $zone['COORDINATES'];
            if (is_string($coordinates)) {
                $coordinates = unserialize($coordinates);
            }
            if (!is_array($coordinates) || count($coordinates) < 3) {
                continue;
            }

            // Проверка вхождения точки в полигон (Ray Casting)
            if ($this->isPointInPolygon($point, $coordinates)) {
                return (float)$zone['PRICE'];
            }
        }

        return 0;
    }

    /**
     * Проверка принадлежности точки полигону (Ray Casting)
     *
     * @param array $point [lat, lng]
     * @param array $polygon [[lat, lng], ...]
     * @return bool
     */
    private function isPointInPolygon($point, $polygon)
    {
        $x = $point[0];
        $y = $point[1];
        $inside = false;
        $j = count($polygon) - 1;

        for ($i = 0; $i < count($polygon); $i++) {
            $xi = $polygon[$i][0];
            $yi = $polygon[$i][1];
            $xj = $polygon[$j][0];
            $yj = $polygon[$j][1];

            $intersect = (($yi > $y) != ($yj > $y)) &&
                ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = !$inside;
            }
            $j = $i;
        }

        return $inside;
    }

    /**
     * Получение цены доставки через виртуальный заказ.
     *
     * Цена берётся из службы доставки (ZoneDelivery определяет её по зоне адреса).
     *
     * @param int $deliveryId
     * @param Basket $basket
     * @param int $addressId ID выбранного адреса из HL-блока adress_user
     * @return float
     */
    private function getDeliveryPriceFromOrder($deliveryId, $basket, $addressId = 0)
    {
        if ($deliveryId <= 0) {
            return 0;
        }

        // Модуль зон доставки (класс службы ZoneDelivery)
        Loader::includeModule('ldo.deliverymap');

        try {
            global $USER;
            $siteId = Context::getCurrent()->getSite();
            $order = Order::create($siteId, $USER->GetID());
            $order->setPersonTypeId(1);
            $order->setBasket($basket);

            // Передаём выбранный адрес в службу доставки (свойство ADDRESS_ID)
            if ($addressId > 0) {
                foreach ($order->getPropertyCollection() as $prop) {
                    if ($prop->getField('CODE') === 'ADDRESS_ID') {
                        $prop->setValue($addressId);
                        break;
                    }
                }
            }

            $shipmentCollection = $order->getShipmentCollection();
            $deliveryService = $this->getDeliveryServiceObject($deliveryId);
            if ($deliveryService) {
                $shipment = $shipmentCollection->createItem($deliveryService);
                $shipmentItemCollection = $shipment->getShipmentItemCollection();
                $shipment->setField('CURRENCY', $order->getCurrency());

                foreach ($basket->getOrderableItems() as $basketItem) {
                    $shipmentItem = $shipmentItemCollection->createItem($basketItem);
                    $shipmentItem->setQuantity($basketItem->getQuantity());
                }

                $calculationResult = $deliveryService->calculate($shipment);
                if ($calculationResult->isSuccess()) {
                    return (float)$calculationResult->getPrice();
                }
            }
        } catch (\Exception $e) {
            addMessage2Log('Ошибка получения цены доставки: ' . $e->getMessage());
        }

        return 0;
    }

    /**
     * Единый метод формирования ответа с данными корзины
     */
    private function prepareBasketResponse($basket = null, $message = '')
    {
        if (!$basket) {
            $basket = $this->getBasket();
        }

        // Принудительно применяем скидки
        $fuser = new \Bitrix\Sale\Discount\Context\Fuser($basket->getFUserId(true));
        $discounts = \Bitrix\Sale\Discount::buildFromBasket($basket, $fuser);
        $discounts->calculate();
        $applyResult = $discounts->getApplyResult(true);

        // Получаем цены со скидками
        $pricesWithDiscount = $applyResult['PRICES']['BASKET'] ?? [];

        $itemsData = [];
        $totalPrice = 0;
        $baseTotalPrice = 0;
        $discountSum = 0;

        foreach ($basket as $item) {
            $basketId = $item->getId();
            $productId = $item->getProductId();
            $quantity = $item->getQuantity();
            $basePrice = $item->getBasePrice(); // Базовая цена за единицу
            $unitWeight = (int)$item->getWeight() * (int)$quantity;


            // Цена за ЕДИНИЦУ со скидкой
            $unitPrice = $pricesWithDiscount[$basketId]['PRICE'] ?? $item->getPrice();

            // ИТОГОВАЯ цена товара (с учетом количества)
            $itemTotalPrice = $unitPrice * $quantity;
            $itemBaseTotalPrice = $basePrice * $quantity;

            $totalPrice += $itemTotalPrice;
            $baseTotalPrice += $itemBaseTotalPrice;
            $discountSum += ($basePrice - $unitPrice) * $quantity;

            $itemsData[$productId] = [
                'id' => $basketId,
                'basketId' => $basketId,
                'productId' => $productId,
                'quantity' => $quantity,
                'price' => $itemTotalPrice,
                'unitPrice' => $unitPrice,
                'basePrice' => $basePrice,
                'baseTotalPrice' => $itemBaseTotalPrice,
                'discount' => ($basePrice - $unitPrice) * $quantity,
                'unitDiscount' => $basePrice - $unitPrice,
                'currency' => $item->getCurrency(),
                'priceFormatted' => $this->formatPrice($itemTotalPrice),
                'unitPriceFormatted' => $this->formatPrice($unitPrice),
                'unitWeight' => (int)$unitWeight
            ];
        }

        $deliveryPrice = $this->getDeliveryPrice();
        $totalWithDelivery = $totalPrice + $deliveryPrice;

        $response = [
            'success' => true,
            'items' => $itemsData,
            'totalPrice' => $totalPrice,
            'baseSum' => $baseTotalPrice,
            'discount' => $discountSum,
            'deliveryPrice' => $deliveryPrice,
            'total' => $totalWithDelivery,
            'currency' => $this->getCurrency(),
            'message' => $message ?: 'Данные корзины обновлены'
        ];

        return $response;
    }

    /**
     * Получение цены доставки из сессии
     */
    private function getDeliveryPrice()
    {
        return (float)($_SESSION['LDO_DELIVERY_PRICE'] ?? 0);
    }

    public function removeCartAction($dataUser)
    {
        // Проверка сессии
        if (!check_bitrix_sessid()) {
            return [
                'success' => false,
                'error' => 'Ошибка сессии. Пожалуйста, обновите страницу.'
            ];
        }

        try {
            // Очищаем купоны при удалении корзины
            Sale\DiscountCouponsManager::clear(true);

            $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
                \Bitrix\Sale\Fuser::getId(),
                \Bitrix\Main\Context::getCurrent()->getSite()
            );

            if (empty($basket) || $basket->count() == 0) {
                return [
                    'success' => true,
                    'message' => 'Корзина уже пуста',
                    'reload' => 'Y'
                ];
            }

            // Проверяем, привязана ли корзина к заказу
            if ($basket->getOrderId() > 0) {
                // Корзина привязана к заказу - работаем через заказ
                $order = \Bitrix\Sale\Order::load($basket->getOrderId());
                if (!$order) {
                    throw new \Exception('Заказ не найден');
                }

                $orderBasket = $order->getBasket();
                foreach ($orderBasket as $item) {
                    $deleteResult = $item->delete();
                    if (!$deleteResult->isSuccess()) {
                        throw new \Exception(implode(', ', $deleteResult->getErrorMessages()));
                    }
                }

                $saveResult = $order->save();
                if (!$saveResult->isSuccess()) {
                    throw new \Exception(implode(', ', $saveResult->getErrorMessages()));
                }

            } else {
                // Корзина не привязана - работаем напрямую
                foreach ($basket as $item) {
                    $deleteResult = $item->delete();
                    if (!$deleteResult->isSuccess()) {
                        throw new \Exception(implode(', ', $deleteResult->getErrorMessages()));
                    }
                }

                $saveResult = $basket->save();
                if (!$saveResult->isSuccess()) {
                    throw new \Exception(implode(', ', $saveResult->getErrorMessages()));
                }
            }

            return [
                'success' => true,
                'message' => 'Корзина успешно очищена',
                'reload' => 'Y'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Ошибка при очистке корзины: ' . $e->getMessage()
            ];
        }
    }

    public function deleteProductAction($dataProduct)
    {
        // Проверка сессии
        if (!check_bitrix_sessid()) {
            return [
                'success' => false,
                'error' => 'Ошибка сессии. Пожалуйста, обновите страницу.'
            ];
        }

        if ($dataProduct['action'] != 'deleteProduct') {
            return [
                'success' => false,
                'error' => 'Неизвестный тип операции'
            ];
        }

        if ($dataProduct['productId'] <= 0) {
            return [
                'success' => false,
                'error' => 'Неверный ID продукта: ' . $dataProduct['productId']
            ];
        }

        try {
            $basket = $this->getBasket();

            if (empty($basket) || $basket->count() == 0) {
                return [
                    'success' => false,
                    'error' => 'Корзина пуста'
                ];
            }

            foreach ($basket as $item) {
                if ($item->getProductId() == $dataProduct['productId']) {
                    $obItem = $item;
                    break;
                }
            }


            if (!$obItem) {
                return [
                    'success' => false,
                    'error' => 'Товар не найден в корзине. ID: ' . $dataProduct['productId']
                ];
            }

            $resultDelete = $obItem->delete();

            if (!$resultDelete->isSuccess()) {
                throw new \Exception(implode(', ', $resultDelete->getErrorMessages()));
            }

            $basket->save();

            $countProduct = $basket->count();

            // Если корзина пуста, возвращаем reload
            if ($countProduct == 0) {
                return [
                    'success' => true,
                    'message' => 'Товар успешно удален',
                    'reload' => 'Y'
                ];
            }

            // Возвращаем обновленные данные корзины
            return $this->prepareBasketResponse($basket, 'Товар успешно удален');

        } catch (\Exception $e) {
            addMessage2Log($e->getMessage(), 'deleteProductAction - ошибка в методе');
            return [
                'success' => false,
                'error' => 'Ошибка: ' . $e->getMessage()
            ];
        }
    }


    public function getBasketItemDataAction($productId)
    {
        $productId = (int)$productId;

        $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
            \Bitrix\Sale\Fuser::getId(),
            \Bitrix\Main\Context::getCurrent()->getSite()
        );

        $result = [
            'success' => true,
            'productId' => $productId,
            'inCart' => false,
            'quantity' => 0,
            'basketId' => null
        ];

        foreach ($basket as $item) {
            if ($item->getProductId() == $productId) {
                $result['inCart'] = true;
                $result['quantity'] = $item->getQuantity();
                $result['basketId'] = $item->getId();
                break;
            }
        }

        return $result;
    }


    public function addQuantityAction($dataProduct)
    {
        // Проверка сессии
        if (!check_bitrix_sessid()) {
            return [
                'success' => false,
                'error' => 'Ошибка сессии. Пожалуйста, обновите страницу.'
            ];
        }

        if ($dataProduct['action'] != 'updateQuantity') {
            return [
                'success' => false,
                'error' => 'Неизвестный тип операции'
            ];
        }

        if ($dataProduct['productId'] <= 0) {
            return [
                'success' => false,
                'error' => 'Неверный ID продукта: ' . $dataProduct['productId']
            ];
        }

        if ($dataProduct['quantity'] <= 0) {
            return [
                'success' => false,
                'error' => 'Количество должно быть больше 0'
            ];
        }

        try {
            $basket = $this->getBasket();

            if (empty($basket) || $basket->count() == 0) {
                return [
                    'success' => false,
                    'error' => 'Корзина пуста'
                ];
            }

            foreach ($basket as $item) {
                if ($item->getProductId() == $dataProduct['productId']) {
                    $obItem = $item;
                    break;
                }
            }

            if (!$obItem) {
                return [
                    'success' => false,
                    'error' => 'Товар не найден в корзине. ID: ' . $dataProduct['productId']
                ];
            }

            // Устанавливаем новое количество
            $resultUpdate = $obItem->setField('QUANTITY', $dataProduct['quantity']);

            if (!$resultUpdate->isSuccess()) {
                throw new \Exception(implode(', ', $resultUpdate->getErrorMessages()));
            }

            // Сохраняем корзину
            if ($basket->getOrderId() > 0) {
                $order = \Bitrix\Sale\Order::load($basket->getOrderId());
                $order->setBasket($basket);
                $saveResult = $order->save();
            } else {
                $saveResult = $basket->save();
            }

            if (!$saveResult->isSuccess()) {
                throw new \Exception(implode(', ', $saveResult->getErrorMessages()));
            }

            // Возвращаем единый формат ответа
            return $this->prepareBasketResponse($basket, 'Количество успешно изменено');

        } catch (\Exception $e) {

            return [
                'success' => false,
                'error' => 'Ошибка: ' . $e->getMessage()
            ];
        }
    }

    public function deleteAddressAction($dataAddress)
    {
        // Проверка сессии
        if (!check_bitrix_sessid()) {
            return [
                'success' => false,
                'error' => 'Ошибка сессии. Пожалуйста, обновите страницу.'
            ];
        }

        if ($dataAddress['action'] != 'deleteAddress') {
            return [
                'success' => false,
                'error' => 'Неизвестный тип операции'
            ];
        }

        if (!$dataAddress['addressId']) {
            return [
                'success' => false,
                'error' => 'Не передан ID адреса'
            ];
        }

        $addressId = (int)$dataAddress['addressId'];

        if (!Loader::includeModule('ldo.iiko')) {
            return [
                'success' => false,
                'error' => 'Модуль адресов доставки не найден'
            ];
        }

        $deleteResult = \Ldo\Iiko\UserAddress::delete($addressId);

        if ($deleteResult) {
            return [
                'success' => true,
                'message' => 'Адрес успешно удален'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Не удалось удалить адрес'
            ];
        }
    }

    public function addAddressAction($dataAddress)
    {
        // Проверка сессии
        if (!check_bitrix_sessid()) {
            return [
                'success' => false,
                'error' => 'Ошибка сессии. Пожалуйста, обновите страницу.'
            ];
        }

        if ($dataAddress['action'] != 'addAddress') {
            return [
                'success' => false,
                'error' => 'Неизвестный тип операции'
            ];
        }

        global $USER;

        // Город: передаётся с фронтенда, иначе — из настроек доставки по умолчанию
        $city = trim((string)($dataAddress['city'] ?? ''));
        if ($city === '' && Loader::includeModule('ldo.deliverymap')) {
            $city = trim((string)\Ldo\Deliverymap\SettingsTable::get('s1', 'default_city', ''));
        }

        // Адрес без города и региона — только улица и дом
        $address = $this->normalizeAddress((string)($dataAddress['address'] ?? ''));
        $address = $this->stripCityFromAddress($address, $city);

        // Координаты адреса
        $lat = trim((string)($dataAddress['lat'] ?? ''));
        $lon = trim((string)($dataAddress['lon'] ?? ''));

        // Запрет добавления адреса вне зоны доставки
        if (!Loader::includeModule('ldo.deliverymap') || !Loader::includeModule('ldo.develop')) {
            return [
                'success' => false,
                'error' => 'Модуль зон доставки недоступен'
            ];
        }

        $zoneId = Hlblock::findZoneIdByCoordinates((float)$lat, (float)$lon);
        if ($zoneId <= 0) {
            return [
                'success' => false,
                'error' => 'Адрес не входит в зону доставки'
            ];
        }

        // Сохраняем адрес в собственную таблицу (ldo_iiko_user_address)
        if (!Loader::includeModule('ldo.iiko')) {
            return [
                'success' => false,
                'error' => 'Модуль адресов доставки не найден'
            ];
        }

        $addressId = \Ldo\Iiko\UserAddress::add([
            'USER_ID' => (int)$USER->GetID(),
            'CITY' => $city,
            'ADDRESS' => $address,
            'KVARTIRA' => trim((string)($dataAddress['apartment'] ?? '')),
            'PODEZD' => trim((string)($dataAddress['entrance'] ?? '')),
            'ETAG' => trim((string)($dataAddress['floor'] ?? '')),
            'DOMOFON' => trim((string)($dataAddress['intercom'] ?? '')),
            'LAT' => $lat,
            'LON' => $lon,
            'ZONE_ID' => $zoneId,
        ]);

        if ($addressId) {
            return [
                'success' => true,
                'addressId' => $addressId,
                'address' => $address,
                'city' => $city,
                'zoneId' => $zoneId,
            ];
        }

        return [
            'success' => false,
            'error' => 'Не удалось добавить адрес'
        ];
    }

    public function editAddressAction($dataAddress)
    {
        // Проверка сессии
        if (!check_bitrix_sessid()) {
            return [
                'success' => false,
                'error' => 'Ошибка сессии. Пожалуйста, обновите страницу.'
            ];
        }

        if (($dataAddress['action'] ?? '') !== 'editAddress') {
            return [
                'success' => false,
                'error' => 'Неизвестный тип операции'
            ];
        }

        $addressId = (int)($dataAddress['addressId'] ?? 0);
        if (!$addressId) {
            return [
                'success' => false,
                'error' => 'Не передан ID адреса'
            ];
        }

        global $USER;

        // Город: из формы, иначе из настроек доставки по умолчанию
        $city = trim((string)($dataAddress['city'] ?? ''));
        if ($city === '' && Loader::includeModule('ldo.deliverymap')) {
            $city = trim((string)\Ldo\Deliverymap\SettingsTable::get('s1', 'default_city', ''));
        }

        // Адрес без города и региона — только улица и дом
        $address = $this->normalizeAddress((string)($dataAddress['address'] ?? ''));
        $address = $this->stripCityFromAddress($address, $city);

        if (!Loader::includeModule('ldo.iiko')) {
            return [
                'success' => false,
                'error' => 'Модуль адресов доставки не найден'
            ];
        }

        // Частичное обновление: UserAddress::update меняет только переданные поля,
        // поэтому USER_ID, координаты и зона существующего адреса не затираются.
        $updateResult = \Ldo\Iiko\UserAddress::update($addressId, [
            'ADDRESS' => $address,
            'CITY' => $city,
            'KVARTIRA' => trim((string)($dataAddress['apartment'] ?? '')),
            'PODEZD' => trim((string)($dataAddress['entrance'] ?? '')),
            'ETAG' => trim((string)($dataAddress['floor'] ?? '')),
            'DOMOFON' => trim((string)($dataAddress['intercom'] ?? '')),
        ]);

        if ($updateResult) {
            return [
                'success' => true,
                'addressId' => $addressId,
                'address' => $address,
                'city' => $city,
            ];
        }

        return [
            'success' => false,
            'error' => 'Не удалось обновить адрес'
        ];
    }


    /**
     * Приводит адрес к виду "город, улица, дом" — убирает страну и
     * региональные сегменты (область/республика/край/округ/район) в начале.
     * Страховка на случай, если фронтенд прислал полный адрес.
     *
     * @param string $address
     * @return string
     */
    private function normalizeAddress(string $address): string
    {
        $address = trim($address);
        if ($address === '') {
            return $address;
        }

        // Убираем страну в начале
        $address = preg_replace('/^Россия\s*,\s*/iu', '', $address);
        $address = preg_replace('/^РФ\s*,\s*/iu', '', $address);

        // Разбиваем на сегменты
        $chunks = array_map('trim', explode(',', $address));

        // Убираем ведущие региональные сегменты, пока не встретим город (locality)
        while (count($chunks) > 1) {
            $head = $chunks[0];
            if (preg_match('/(область|республика|край|округ|автономный|район)$/iu', $head)
                && !preg_match('/(город|г\.)/iu', $head)) {
                array_shift($chunks);
            } else {
                break;
            }
        }

        // Убираем служебные префиксы города
        if (!empty($chunks)) {
            $chunks[0] = preg_replace(
                '/^(?:городской округ|муниципальный округ|город|поселок городского типа|пгт|г)\s+/iu',
                '',
                $chunks[0]
            );
        }

        return implode(', ', array_filter($chunks, static function ($chunk) {
            return $chunk !== '';
        }));
    }

    /**
     * Убирает город из начала адреса, оставляя только улицу и дом.
     * Страховка на случай, если город остался в строке адреса
     * (например, при ручном вводе).
     *
     * @param string $address
     * @param string $city
     * @return string
     */
    private function stripCityFromAddress(string $address, string $city): string
    {
        $address = trim($address);
        if ($address === '' || $city === '') {
            return $address;
        }

        $chunks = array_map('trim', explode(',', $address));

        // Если первый сегмент совпадает с городом — убираем его
        if (!empty($chunks) && $chunks[0] !== '' && mb_stripos($chunks[0], $city) !== false) {
            array_shift($chunks);
        }

        return implode(', ', array_filter($chunks, static function ($chunk) {
            return $chunk !== '';
        }));
    }

    public function changePromoAction($dataPromo)
    {
        // Проверка сессии
        if (!check_bitrix_sessid()) {
            return [
                'success' => false,
                'error' => 'Ошибка сессии. Пожалуйста, обновите страницу.'
            ];
        }

        if (!$dataPromo || !isset($dataPromo['promokod'])) {
            return [
                'success' => false,
                'error' => 'Не передан промокод'
            ];
        }

        try {
            $promokod = trim($dataPromo['promokod']);

            if (empty($promokod)) {
                return [
                    'success' => false,
                    'error' => 'Введите промокод'
                ];
            }

            // Очищаем предыдущие купоны перед применением нового
            Sale\DiscountCouponsManager::clear(true);

            // Применяем купон
            $resultAdd = Sale\DiscountCouponsManager::add($promokod);

            if (!$resultAdd) {
                return [
                    'success' => false,
                    'error' => 'Не удалось применить промокод. Возможно, он недействителен или истек срок действия.'
                ];
            }

            $basket = $this->getBasket();

            // Обновляем поля в корзине с учетом купонов
            $basket->refreshData(['PRICE', 'COUPONS']);

            // Сохраняем корзину
            if ($basket->getOrderId() > 0) {
                $order = \Bitrix\Sale\Order::load($basket->getOrderId());
                $order->setBasket($basket);
                $saveResult = $order->save();
            } else {
                $saveResult = $basket->save();
            }

            if (!$saveResult->isSuccess()) {
                throw new \Exception(implode(', ', $saveResult->getErrorMessages()));
            }

            // Возвращаем единый формат ответа
            return $this->prepareBasketResponse($basket, 'Промокод успешно применен');

        } catch (\Exception $e) {
            addMessage2Log($e->getMessage(), 'changePromoAction - ошибка в методе');
            return [
                'success' => false,
                'error' => 'Ошибка при применении промокода: ' . $e->getMessage()
            ];
        }
    }

    public function clearPromoAction($dataPromo = null)
    {
        // Проверка сессии
        if (!check_bitrix_sessid()) {
            return [
                'success' => false,
                'error' => 'Ошибка сессии. Пожалуйста, обновите страницу.'
            ];
        }

        try {
            // Очищаем все купоны
            Sale\DiscountCouponsManager::clear(true);

            $basket = $this->getBasket();

            // Обновляем корзину без купонов
            $basket->refreshData(['PRICE', 'COUPONS']);

            // Сохраняем корзину
            if ($basket->getOrderId() > 0) {
                $order = \Bitrix\Sale\Order::load($basket->getOrderId());
                $order->setBasket($basket);
                $saveResult = $order->save();
            } else {
                $saveResult = $basket->save();
            }

            if (!$saveResult->isSuccess()) {
                throw new \Exception(implode(', ', $saveResult->getErrorMessages()));
            }

            // Возвращаем единый формат ответа
            return $this->prepareBasketResponse($basket, 'Промокод сброшен');

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Ошибка при сбросе промокода: ' . $e->getMessage()
            ];
        }
    }

    private function getBasket()
    {
        return \Bitrix\Sale\Basket::loadItemsForFUser(
            \Bitrix\Sale\Fuser::getId(),
            \Bitrix\Main\Context::getCurrent()->getSite()
        );
    }

    private function formatPrice($price)
    {
        return \CCurrencyLang::CurrencyFormat($price, 'RUB');
    }

    private function getCurrency()
    {
        return \Bitrix\Currency\CurrencyManager::getBaseCurrency();
    }

    // Добавьте этот метод в класс OpenSourceOrderComponent
    private function getUserInfo()
    {
        global $USER;

        $userID = $USER->GetID();

        if (!$userID) {
            return false;
        }

        $user = CUser::GetByID($userID)->Fetch();

        if (!$user) {
            return false;
        }

        return array(
            'EMAIL' => $user['EMAIL'],
            'NAME' => $user['NAME'],
            'PHONE' => $user['PERSONAL_PHONE'],
        );
    }

    /**
     * Предзаполнение свойств заказа данными авторизованного пользователя
     */
    private function prefillPropertiesWithUserData()
    {
        global $USER;

        if (!$USER->IsAuthorized()) {
            return;
        }

        $userInfo = $this->getUserInfo();
        if (!$userInfo) {
            return;
        }

        $propertyCollection = $this->order->getPropertyCollection();

        // Маппинг полей пользователя на свойства заказа
        $mapping = [
            'NAME' => 'NAME',      // свойство с кодом NAME
            'FIO' => 'NAME',       // если есть свойство FIO
            'EMAIL' => 'EMAIL',
            'PHONE' => 'PHONE',
        ];

        foreach ($mapping as $propertyCode => $userField) {
            $property = $propertyCollection->getItemByOrderPropertyCode($propertyCode);
            if ($property && empty($property->getValue())) {
                $value = $userInfo[$userField] ?? null;
                if (!empty($value)) {
                    $property->setValue($value);
                }
            }
        }
    }
}
