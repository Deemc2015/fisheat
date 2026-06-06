<?php
/**
 * Страница настроек модуля в админке
 */

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

$moduleId = 'mibazarow.smartconsultant';
Loader::includeModule($moduleId);

// Стандартный паттерн Bitrix: $REQUEST_METHOD + $Update/$Apply
$Update = $_POST['Update'] ?? '';
$Apply = $_POST['Apply'] ?? '';

if ($REQUEST_METHOD == 'POST' && ($Update <> '' || $Apply <> '') && check_bitrix_sessid()) {
    // Сохраняем ID выбранного инфоблока
    $iblockId = (int)($_POST['CATALOG_IBLOCK_ID'] ?? 0);
    Option::set($moduleId, 'CATALOG_IBLOCK_ID', (string)$iblockId);

    // Сохраняем текстовые настройки с валидацией
    $minSimilarity = (float)($_POST['MIN_SIMILARITY'] ?? 0.4);
    $minSimilarity = max(0.0, min(1.0, $minSimilarity));
    Option::set($moduleId, 'MIN_SIMILARITY', (string)$minSimilarity);

    $topCount = (int)($_POST['TOP_COUNT'] ?? 20);
    $topCount = max(1, min(1000, $topCount));
    Option::set($moduleId, 'TOP_COUNT', (string)$topCount);

    // Проверка HTTP-сервиса при сохранении
    try {
        $http = new \Bitrix\Main\Web\HttpClient();
        $response = $http->get('http://127.0.0.1:9876/health');
        if (str_contains($response, '"status":"ok"')) {
            CAdminMessage::ShowNote('Настройки сохранены. HTTP-сервис эмбеддингов доступен (порт 9876).');
        } else {
            CAdminMessage::ShowMessage([
                'TYPE' => 'ERROR',
                'MESSAGE' => 'Настройки сохранены, но HTTP-сервис эмбеддингов не отвечает.',
                'DETAILS' => 'Запустите: systemctl start mib-smartconsultant',
            ]);
        }
    } catch (\Throwable $e) {
        CAdminMessage::ShowMessage([
            'TYPE' => 'ERROR',
            'MESSAGE' => 'Настройки сохранены, но HTTP-сервис эмбеддингов недоступен.',
            'DETAILS' => 'Убедитесь, что Python-сервис запущен на порту 9876.',
        ]);
    }

    // После Update — редирект на ту же страницу без POST (защита от повторной отправки)
    if ($Update <> '') {
        LocalRedirect($APPLICATION->GetCurPage() . '?mid=' . urlencode($moduleId) . '&lang=' . LANGUAGE_ID);
    }
}

// Текущие значения
$currentIblockId = (int)Option::get($moduleId, 'CATALOG_IBLOCK_ID', '');
$currentMinSimilarity = Option::get($moduleId, 'MIN_SIMILARITY', '0.4');
$currentTopCount = Option::get($moduleId, 'TOP_COUNT', '20');

// Собираем список инфоблоков для выпадающего списка
$iblockList = [];
if (Loader::includeModule('iblock')) {
    $res = \CIBlock::GetList(['SORT' => 'ASC'], ['ACTIVE' => 'Y', 'CHECK_PERMISSIONS' => 'N']);
    while ($ib = $res->Fetch()) {
        $iblockList[$ib['ID']] = '[' . $ib['ID'] . '] ' . $ib['NAME'];
    }
}

// Форма
$tabs = [
    [
        'DIV' => 'tab_main',
        'TAB' => 'Основные настройки',
        'TITLE' => 'Основные настройки',
    ],
];

$tabControl = new CAdminTabControl('tabControl', $tabs);
$tabControl->Begin();
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>

    <?php $tabControl->BeginNextTab(); ?>

    <tr>
        <td class="adm-detail-content-cell-l" width="50%">
            <label for="CATALOG_IBLOCK_ID">Инфоблок каталога</label>
        </td>
        <td class="adm-detail-content-cell-r" width="50%">
            <select name="CATALOG_IBLOCK_ID" id="CATALOG_IBLOCK_ID" style="width:300px;">
                <option value="">— не выбран —</option>
                <?php foreach ($iblockList as $id => $name): ?>
                    <option value="<?= $id ?>" <?= ((string)$currentIblockId === (string)$id) ? 'selected' : '' ?>>
                        <?= htmlspecialcharsbx($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><small>Товары из этого инфоблока будут индексироваться и участвовать в поиске.</small>
        </td>
    </tr>

    <tr>
        <td class="adm-detail-content-cell-l" width="50%">
            <label for="MIN_SIMILARITY">Порог релевантности (0.0 — 1.0)</label>
        </td>
        <td class="adm-detail-content-cell-r" width="50%">
            <input type="text"
                   id="MIN_SIMILARITY"
                   name="MIN_SIMILARITY"
                   value="<?= htmlspecialcharsbx($currentMinSimilarity) ?>"
                   size="5">
            <br><small>0.3 — больше результатов, но больше шума. 0.6 — только точные совпадения.</small>
        </td>
    </tr>

    <tr>
        <td class="adm-detail-content-cell-l" width="50%">
            <label for="TOP_COUNT">Количество результатов поиска</label>
        </td>
        <td class="adm-detail-content-cell-r" width="50%">
            <input type="text"
                   id="TOP_COUNT"
                   name="TOP_COUNT"
                   value="<?= htmlspecialcharsbx($currentTopCount) ?>"
                   size="5">
        </td>
    </tr>

    <?php $tabControl->Buttons(); ?>
    <input type="submit" name="Update" value="Сохранить" class="adm-btn-save">
    <input type="submit" name="Apply" value="Применить">
    <?php $tabControl->End(); ?>
</form>
