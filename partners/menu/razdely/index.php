<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $USER;

$APPLICATION->SetTitle("Настройка разделов");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Меню", "/partners/menu/");
$APPLICATION->AddChainItem("Настройка разделов");

$request = Bitrix\Main\Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

$partnersActivePage  = 'menu';
$partnersPageTitle   = 'Меню';
$partnersHeaderStyle = 'padding-bottom:0; border-bottom:none;';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
?>

        <!-- Подменю меню -->
        <div class="p-dash-tabs">
            <a class="tab-btn" data-tab="sync" href="/partners/menu/">Синхронизация</a>
            <a class="tab-btn" data-tab="tovary" href="/partners/menu/tovary/">Настройка товаров</a>
            <a class="tab-btn active" data-tab="razdely" href="/partners/menu/razdely/">Настройка разделов</a>
        </div>

        <div class="p-main">
            <div class="p-section">
                <div class="p-section__header">
                    <h2 class="p-section__title">Настройка разделов</h2>
                    <span style="font-size:13px; color:var(--color-muted);">Тестовый список разделов</span>
                </div>

                <?php
                // Тестовые разделы (заглушка до подключения БД)
                $testSections = [
                    ['ID' => 1, 'NAME' => 'Рыба охлаждённая',  'ACTIVE' => 'Y', 'ON_MAIN' => 'Y'],
                    ['ID' => 2, 'NAME' => 'Морепродукты',      'ACTIVE' => 'Y', 'ON_MAIN' => 'N'],
                    ['ID' => 3, 'NAME' => 'Икра',              'ACTIVE' => 'Y', 'ON_MAIN' => 'Y'],
                    ['ID' => 4, 'NAME' => 'Полуфабрикаты',     'ACTIVE' => 'N', 'ON_MAIN' => 'N'],
                    ['ID' => 5, 'NAME' => 'Деликатесы',        'ACTIVE' => 'Y', 'ON_MAIN' => 'N'],
                ];
                ?>

                <div id="sections-list">
                    <?php foreach ($testSections as $s): ?>
                        <div class="rest-item" data-id="<?= (int)$s['ID'] ?>">
                            <div class="rest-item__main">
                                <div class="rest-item__info">
                                    <div class="rest-item__name"><?= htmlspecialchars($s['NAME']) ?></div>
                                    <div class="rest-item__meta" style="display:flex; align-items:center; gap:20px; flex-wrap:wrap; margin-top:6px;">
                                        <!-- Чекбокс: Активность -->
                                        <label class="toggle-switch" style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                            <input type="checkbox" class="section-active" value="Y" <?= $s['ACTIVE'] === 'Y' ? 'checked' : '' ?>>
                                            <span class="toggle-switch__slider"></span>
                                            <span style="font-size:13px; color:var(--color-muted);">Активность</span>
                                        </label>
                                        <!-- Чекбокс: Показать на главной -->
                                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; color:var(--color-muted);">
                                            <input type="checkbox" class="section-on-main" <?= $s['ON_MAIN'] === 'Y' ? 'checked' : '' ?> style="width:16px; height:16px;">
                                            Показать на главной
                                        </label>
                                    </div>
                                </div>
                                <div class="rest-item__actions" style="display:flex; align-items:center; gap:10px;">
                                    <!-- Иконка: Редактировать -->
                                    <button type="button" class="section-edit" title="Редактировать"
                                            style="background:none; border:none; cursor:pointer; padding:6px; color:var(--color-muted);"
                                            onclick="editSection(<?= (int)$s['ID'] ?>, '<?= CUtil::JSEscape($s['NAME']) ?>')">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21H6.75L17.81 9.94L14.06 6.19L3 17.25ZM20.71 7.04C21.1 6.65 21.1 6.02 20.71 5.63L18.37 3.29C17.98 2.9 17.35 2.9 16.96 3.29L15.13 5.12L18.88 8.87L20.71 7.04Z"/></svg>
                                    </button>
                                    <!-- Иконка: Удалить -->
                                    <button type="button" class="section-del" title="Удалить"
                                            style="background:none; border:none; cursor:pointer; padding:6px; color:#e74c3c;"
                                            onclick="deleteSection(<?= (int)$s['ID'] ?>, '<?= CUtil::JSEscape($s['NAME']) ?>')">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19C6 20.1 6.9 21 8 21H16C17.1 21 18 20.1 18 19V7H6V19ZM8 9H16V19H8V9ZM15.5 4L14.5 3H9.5L8.5 4H5V6H19V4H15.5Z"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:20px; font-size:13px; color:var(--color-muted);">
                    Это тестовый список. Сохранение изменений будет подключено позже.
                </div>
            </div>
        </div>

<script>
function editSection(id, name) {
    alert('Редактирование раздела #' + id + ' «' + name + '» — в разработке');
}

function deleteSection(id, name) {
    if (confirm('Удалить раздел «' + name + '»?')) {
        alert('Удаление раздела #' + id + ' — в разработке');
    }
}
</script>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
