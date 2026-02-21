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
            if ($prop->isUtil()) {
                continue;
            }

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
        /* @var $shipmentCollection ShipmentCollection */
        $shipmentCollection = $this->order->getShipmentCollection();

        if ($deliveryId > 0) {
            $shipment = $shipmentCollection->createItem(
                Bitrix\Sale\Delivery\Services\Manager::getObjectById($deliveryId)
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

    public function getPersonUserAddress(){
        global $USER;

        $userId = $USER->GetID();
        /*тут данные по адресам доставки*/
        return $userId;
    }

    public function executeComponent()
    {
        try {
            $this->createVirtualOrder($this->arParams['PERSON_TYPE_ID']);

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
            // Публичное действие без авторизации

            'addQuantity' => [
                'prefilters' => [

                ],
            ],
            'removeCart' =>[
                'prefilters' => [

                ],
            ],
            'deleteProduct'=>[
                'prefilters' => [

                ],
            ],
        ];
    }

    public function removeCartAction($dataUser)
    {
        // Проверка сессии (обязательно!)
        if (!check_bitrix_sessid()) {
            return [
                'success' => false,
                'error' => 'Ошибка сессии. Пожалуйста, обновите страницу.'
            ];
        }

        try {
            $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
                \Bitrix\Sale\Fuser::getId(),
                \Bitrix\Main\Context::getCurrent()->getSite()
            );

            if (empty($basket) || $basket->count() == 0) {
                return [
                    'success' => true,
                    'message' => 'Корзина уже пуста'
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
                'message' => 'Корзина успешно очищена'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Ошибка при очистке корзины: ' . $e->getMessage()
            ];
        }
    }

    public function deleteProductAction($id){

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

            // Поиск товара
            $obItem = $basket->getExistsItem('catalog', $dataProduct['productId']);

            if (!$obItem) {
                foreach ($basket as $item) {
                    if ($item->getId() == $dataProduct['productId']) {
                        $obItem = $item;
                        break;
                    }
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

            // ОБЫЧНЫЙ ПЕРЕСЧЕТ (не всегда применяет скидки)
            $basket->refresh();

            // ПРИНУДИТЕЛЬНО ПРИМЕНЯЕМ СКИДКИ через Discount::buildFromBasket
            $fuser = new \Bitrix\Sale\Discount\Context\Fuser($basket->getFUserId(true));
            $discounts = \Bitrix\Sale\Discount::buildFromBasket($basket, $fuser);
            $discounts->calculate();
            $applyResult = $discounts->getApplyResult(true);

            // Получаем цены со скидками
            $pricesWithDiscount = $applyResult['PRICES']['BASKET'] ?? [];

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

            // ФОРМИРУЕМ ОТВЕТ С УЧЕТОМ СКИДОК
            $itemsData = [];
            $totalPrice = 0;

            foreach ($basket as $item) {
                $basketId = $item->getId();
                $productId = $item->getProductId();
                $quantity = $item->getQuantity();

                // Цена за ЕДИНИЦУ со скидкой
                $unitPrice = $pricesWithDiscount[$basketId]['PRICE'] ?? $item->getPrice();

                // ИТОГОВАЯ цена товара (с учетом количества)
                $itemTotalPrice = $unitPrice * $quantity;

                $totalPrice += $itemTotalPrice;

                $itemsData[$basketId] = [
                    'id' => $basketId,
                    'basketId' => $basketId,
                    'productId' => $productId,
                    'quantity' => $quantity,
                    'unitPrice' => $unitPrice,                    // Цена за единицу
                    'price' => $itemTotalPrice,                    // Итоговая сумма
                    'basePrice' => $item->getBasePrice(),          // Базовая цена за единицу
                    'priceFormatted' => $this->formatPrice($itemTotalPrice),
                    'unitPriceFormatted' => $this->formatPrice($unitPrice),
                    'discount' => ($item->getBasePrice() - $unitPrice) * $quantity,
                    'unitDiscount' => $item->getBasePrice() - $unitPrice,
                    'currency' => $item->getCurrency()
                ];
            }

            return [
                'success' => true,
                'items' => $itemsData,
                'totalPrice' => $totalPrice,
                'totalPriceFormatted' => $this->formatPrice($totalPrice),
                'currency' => $this->getCurrency(),
                'message' => 'Количество успешно изменено'
            ];

        } catch (\Exception $e) {
            addMessage2Log($e->getMessage(), 'addQuantityAction - ERROR');
            return [
                'success' => false,
                'error' => 'Ошибка: ' . $e->getMessage()
            ];
        }
    }

    private function getBasket(){
        return \Bitrix\Sale\Basket::loadItemsForFUser(
            \Bitrix\Sale\Fuser::getId(),
            \Bitrix\Main\Context::getCurrent()->getSite()
        );
    }

    private function formatPrice($price)
    {
        //Вариант 3: С использованием CCurrencyLang (как в Битриксе)
        return \CCurrencyLang::CurrencyFormat($price, 'RUB');
    }

    private function getCurrency()
    {
        return \Bitrix\Currency\CurrencyManager::getBaseCurrency();
    }

}