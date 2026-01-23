<?php
// /bitrix/php_interface/init.php
/*AddEventHandler('sale', 'OnBeforeBasketAdd', function(&$arFields) {
    if (!Bitrix\Main\Loader::includeModule('sale') || !Bitrix\Main\Loader::includeModule('catalog')) {
        return;
    }

    $ROLLS_IDS = [317];
    $SAUCE_ID = 318;
    $STICKS_ID = 319;

    if (in_array($arFields['PRODUCT_ID'], $ROLLS_IDS)) {
        $fuserId = Bitrix\Sale\Fuser::getId();
        $siteId = Bitrix\Main\Context::getCurrent()->getSite();
        
        $basket = Bitrix\Sale\Basket::loadItemsForFUser($fuserId, $siteId);
        
        // Проверяем наличие соуса
        $hasSauce = false;
        foreach ($basket as $item) {
            if ($item->getProductId() == $SAUCE_ID) {
                $hasSauce = true;
                break;
            }
        }
        
        if (!$hasSauce) {
            $item = $basket->createItem('catalog', $SAUCE_ID);
            $item->setFields([
                'QUANTITY' => 1,
                'PRICE' => 0,
                'CURRENCY' => 'RUB'
            ]);
            // Добавляем свойства отдельно
          
        }

        // Проверяем наличие палочек
        $hasSticks = false;
        foreach ($basket as $item) {
            if ($item->getProductId() == $STICKS_ID) {
                $hasSticks = true;
                break;
            }
        }
        
        if (!$hasSticks) {
            $item = $basket->createItem('catalog', $STICKS_ID);
            $item->setFields([
                'QUANTITY' => 1,
                'PRICE' => 0,
                'CURRENCY' => 'RUB'
            ]);
        }

        $basket->save();
    }
});*/
use Bitrix\Main\EventManager;
use Bitrix\Main\Event;
use Bitrix\Sale\Basket;

/*EventManager::getInstance()->addEventHandler(
    'sale',
    'OnSaleBasketBeforeSaved',
    function(Event $event) {
        $basket = $event->getParameter('ENTITY');
        if ($basket->getOrderId() > 0) return; // Пропускаем привязанные к заказу корзины
        $totalPrice = 0;
        foreach ($basket as $item) {
            $totalPrice+=$item->getPrice();
        }

        addMessage2Log($totalPrice);
    }
);*/

EventManager::getInstance()->addEventHandler(
    'sale',
    'OnSaleBasketItemRefreshData',
    'myFunction'
);
function myFunction(\Bitrix\Main\Event $event)
{
    $entity = $event->getParameter("ENTITY");
    $values = $event->getParameter("VALUES");
    addMessage2Log($entity);
}