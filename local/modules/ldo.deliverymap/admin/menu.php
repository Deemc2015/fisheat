<?php
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if ($APPLICATION->GetGroupRight('ldo.deliverymap') >= 'R') {
    return [
        [
            'parent_menu' => 'global_menu_store',
            'sort' => 100,
            'text' => 'Зоны доставки',
            'title' => 'Управление зонами доставки',
            'url' => '/bitrix/admin/ldo_deliverymap/zones.php',  // новый путь
            'icon' => 'sale_menu_icon',
            'page_icon' => 'sale_page_icon',
            'items_id' => 'menu_ldo_deliverymap'
        ]
    ];
}

return [];