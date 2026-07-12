<?php
use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

$module_id = 'ldo.deliverymap';
$request = Application::getInstance()->getContext()->getRequest();

if ($request->isPost() && check_bitrix_sessid()) {
    Option::set($module_id, 'yandex_api_key', $request->getPost('yandex_api_key'));
    Option::set($module_id, 'default_lat', $request->getPost('default_lat'));
    Option::set($module_id, 'default_lng', $request->getPost('default_lng'));
    Option::set($module_id, 'default_zoom', $request->getPost('default_zoom'));

    LocalRedirect('/bitrix/admin/settings.php?mid=' . $module_id . '&lang=' . LANGUAGE_ID);
}

$tabs = [
    [
        'DIV' => 'edit1',
        'TAB' => 'Настройки карты',
        'ICON' => 'sale_settings',
        'TITLE' => 'Настройки Яндекс.Карт'
    ]
];

$tabControl = new CAdminTabControl('tabControl', $tabs);

?>
<form method="POST" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= $module_id ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <? $tabControl->Begin() ?>

    <? $tabControl->BeginNextTab() ?>

    <tr>
        <td width="40%">
            <label>API ключ Яндекс.Карт:</label>
        </td>
        <td width="60%">
            <input type="text" name="yandex_api_key" value="<?= Option::get($module_id, 'yandex_api_key') ?>" size="50">
            <br><small>Получить можно в <a href="https://developer.tech.yandex.ru/" target="_blank">кабинете разработчика</a></small>
        </td>
    </tr>

    <tr>
        <td>Центр карты (широта):</td>
        <td><input type="text" name="default_lat" value="<?= Option::get($module_id, 'default_lat', '55.751574') ?>"></td>
    </tr>

    <tr>
        <td>Центр карты (долгота):</td>
        <td><input type="text" name="default_lng" value="<?= Option::get($module_id, 'default_lng', '37.573856') ?>"></td>
    </tr>

    <tr>
        <td>Зум по умолчанию:</td>
        <td><input type="text" name="default_zoom" value="<?= Option::get($module_id, 'default_zoom', '10') ?>"></td>
    </tr>

    <? $tabControl->Buttons() ?>
    <input type="submit" class="adm-btn-save" value="Сохранить">
    <? $tabControl->End() ?>
</form>