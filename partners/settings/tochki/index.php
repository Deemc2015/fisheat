<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\HttpClient;
use Ldo\Deliverymap\RestaurantsTable;
use Ldo\Iiko\Auth;
use Ldo\Iiko\SettingsTable as IikoSettingsTable;

global $USER;

$APPLICATION->SetTitle("Настройка точек");
$APPLICATION->AddChainItem("Партнёрский раздел", "/partners/");
$APPLICATION->AddChainItem("Настройки", "/partners/settings/");
$APPLICATION->AddChainItem("Настройка точек");

$request = Context::getCurrent()->getRequest();

if ($request->getQuery('logout') === 'yes') {
    $USER->Logout();
    LocalRedirect('/partners/');
}

$siteId = 's1';
$iikoModuleLoaded      = Loader::includeModule('ldo.iiko');
$deliveryModuleLoaded  = Loader::includeModule('ldo.deliverymap');

// ============================================================
// AJAX: загрузка организаций (точек) из iiko и сохранение маппинга
// ============================================================
if ($request->isPost() && $request->getPost('ajax_tochka') === '1') {
    header('Content-Type: application/json; charset=utf-8');

    $response = function ($payload) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    };

    if (!$USER->IsAuthorized()) {
        $response(['success' => false, 'error' => 'Требуется авторизация.']);
    }

    if (!check_bitrix_sessid()) {
        $response(['success' => false, 'error' => 'Сессия истекла, обновите страницу.']);
    }

    if (!$iikoModuleLoaded || !$deliveryModuleLoaded) {
        $response(['success' => false, 'error' => 'Не подключены модули ldo.iiko / ldo.deliverymap.']);
    }

    $action = (string)$request->getPost('action');

    // --- Загрузка точек (организаций) из iiko ---
    if ($action === 'load') {
        $row = IikoSettingsTable::getRow($siteId);
        if (empty($row['API_LOGIN']) || empty($row['SECRET']) || empty($row['APP_ID'])) {
            $response(['success' => false, 'error' => 'Не заданы настройки подключения к iiko. Заполните их в разделе «Синхронизация с iiko».']);
        }

        $auth = new Auth($siteId);
        $token = $auth->getToken();
        if (!$token) {
            $response(['success' => false, 'error' => 'Не удалось получить токен iiko. Проверьте настройки подключения.']);
        }

        try {
            $httpClient = new HttpClient();
            $httpClient->setHeader('Authorization', 'Bearer ' . $token);
            $httpClient->setHeader('Content-Type', 'application/json');

            $httpResponse = $httpClient->post(
                'https://api-ru.iiko.services/api/1/organizations',
                json_encode([
                    'organizationIds'      => null, // все организации (точки) аккаунта
                    'returnAdditionalInfo' => false,
                    'includeDisabled'      => true,
                ])
            );

            if ($httpResponse === false) {
                $response(['success' => false, 'error' => 'Ошибка HTTP-запроса к iiko: ' . implode('; ', $httpClient->getError())]);
            }

            $result = json_decode($httpResponse, true);
            if (!is_array($result) || !isset($result['organizations']) || !is_array($result['organizations'])) {
                addMessage2Log('Tochki: unexpected organizations response: ' . substr($httpResponse, 0, 2000));
                $response(['success' => false, 'error' => 'Некорректный ответ от iiko. Подробности в журнале.']);
            }

            $organizations = [];
            foreach ($result['organizations'] as $org) {
                if (!is_array($org) || empty($org['id'])) {
                    continue;
                }
                $organizations[] = [
                    'id'   => (string)$org['id'],
                    'name' => (string)($org['name'] ?? ''),
                ];
            }

            if (empty($organizations)) {
                $response(['success' => false, 'error' => 'iiko вернул пустой список точек (организаций).']);
            }

            // Отмечаем связь как установленную
            IikoSettingsTable::setStatus($siteId, 'OK');

            $response(['success' => true, 'organizations' => $organizations]);
        } catch (\Exception $e) {
            addMessage2Log('Tochki load error: ' . $e->getMessage());
            $response(['success' => false, 'error' => 'Ошибка загрузки: ' . $e->getMessage()]);
        }
    }

    // --- Сохранение маппинга точка (ресторан) => ID организации iiko ---
    if ($action === 'save') {
        $mapRaw = (string)$request->getPost('map');
        $map = json_decode($mapRaw, true);
        if (!is_array($map)) {
            $map = [];
        }

        $saved = 0;
        foreach ($map as $restaurantId => $iikoOrgId) {
            $restaurantId = (int)$restaurantId;
            $iikoOrgId    = trim((string)$iikoOrgId);

            if ($restaurantId <= 0) {
                continue;
            }

            RestaurantsTable::updateRestaurant($restaurantId, [
                'XML_ID' => $iikoOrgId,
            ]);
            $saved++;
        }

        $response(['success' => true, 'saved' => $saved]);
    }

    $response(['success' => false, 'error' => 'Неизвестное действие']);
}

// ============================================================
// Загрузка точек (ресторанов) для отображения
// ============================================================
$restaurants = [];
if ($deliveryModuleLoaded) {
    $dbRestaurants = RestaurantsTable::getList([
        'filter' => ['=SITE_ID' => $siteId],
        'order'  => ['ID' => 'ASC'],
    ]);
    while ($r = $dbRestaurants->fetch()) {
        $restaurants[] = $r;
    }
}

$iikoConfigured = $iikoModuleLoaded
    && ($row = IikoSettingsTable::getRow($siteId))
    && !empty($row['API_LOGIN']) && !empty($row['SECRET']) && !empty($row['APP_ID']);

$partnersActivePage  = 'settings';
$partnersPageTitle   = 'Настройки';
$partnersHeaderStyle = 'padding-bottom:0; border-bottom:none;';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
?>

<style>
/* ---------- Настройка точек ---------- */
.tochka-list {
    margin: 16px 0;
    border: 1px solid var(--color-border, #868686);
    border-radius: 10px;
    overflow: hidden;
}
.tochka-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 16px;
    background: var(--bg-black, #1B1818);
}
.tochka-row + .tochka-row {
    border-top: 1px solid rgba(255,255,255,.06);
}
.tochka-row__name {
    flex: 1;
    min-width: 0;
    font-size: 15px;
    font-weight: 500;
    color: var(--bg-white, #FFFFFF);
}
.tochka-row__field {
    flex: 0 0 360px;
    max-width: 100%;
}
.tochka-select {
    width: 100%;
    padding: 10px 12px;
    background: var(--bg-black, #1B1818);
    border: 1px solid var(--color-border, #868686);
    border-radius: 8px;
    color: var(--bg-white, #FFFFFF);
    font-size: 14px;
    font-family: var(--font-family, 'Blogger Sans', 'Roboto');
    box-sizing: border-box;
}
.tochka-select:focus {
    border-color: var(--bg-button, #F44336);
    outline: none;
}
.tochka-select:disabled {
    opacity: .55;
    cursor: not-allowed;
}
.tochka-inactive-note {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    margin-bottom: 8px;
    background: rgba(243,156,18,.1);
    color: #f39c12;
    border-radius: 8px;
    font-size: 14px;
}
.tochka-inactive-note.is-active {
    background: rgba(46,204,113,.1);
    color: #2ecc71;
}
.tochka-actions {
    display: flex;
    gap: 12px;
    margin-top: 16px;
    flex-wrap: wrap;
}
@media (max-width: 768px) {
    .tochka-row {
        flex-direction: column;
        align-items: stretch;
    }
    .tochka-row__field {
        flex: 1 1 auto;
    }
}
</style>

        <div class="p-main">
            <div class="p-section">
                <div class="p-section__header">
                    <h2 class="p-section__title">Настройка точек</h2>
                    <span style="font-size:13px; color:var(--color-muted);">Сайт: <?= htmlspecialchars($siteId) ?></span>
                </div>

                <!-- Статус -->
                <div id="tochka-inactive-note" class="tochka-inactive-note">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="flex-shrink:0;">
                        <path d="M18 8H17V6C17 3.24 14.76 1 12 1C9.24 1 7 3.24 7 6V8H6C4.9 8 4 8.9 4 10V20C4 21.1 4.9 22 6 22H18C19.1 22 20 21.1 20 20V10C20 8.9 19.1 8 18 8ZM12 17C10.9 17 10 16.1 10 15C10 13.9 10.9 13 12 13C13.1 13 14 13.9 14 15C14 16.1 13.1 17 12 17ZM9 8V6C9 4.34 10.34 3 12 3C13.66 3 15 4.34 15 6V8H9Z" fill="currentColor"/>
                    </svg>
                    <span id="tochka-note-text">Область неактивна. Нажмите «Загрузить данные из IIKO», чтобы получить список точек.</span>
                </div>

                <!-- Сообщения -->
                <div id="tochka-message" style="display:none; padding:12px 16px; border-radius:8px; margin-bottom:16px;"></div>

                <?php if (!$iikoModuleLoaded || !$deliveryModuleLoaded): ?>
                    <div style="background:rgba(231,76,60,.12); color:#e74c3c; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
                        Не подключены модули ldo.iiko / ldo.deliverymap.
                    </div>
                <?php endif; ?>

                <?php if (!$iikoConfigured): ?>
                    <div style="background:rgba(231,76,60,.12); color:#e74c3c; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
                        Не заданы настройки подключения к iiko. Заполните их в
                        <a href="/partners/settings/" style="color:var(--bg-button);">настройках синхронизации</a>.
                    </div>
                <?php endif; ?>

                <!-- Список точек: слева название ресторана, справа выпадающий список ID в iiko -->
                <div class="tochka-list" id="tochka-list">
                    <?php foreach ($restaurants as $r): ?>
                        <div class="tochka-row" data-id="<?= (int)$r['ID'] ?>" data-xml="<?= htmlspecialchars($r['XML_ID'] ?? '') ?>">
                            <div class="tochka-row__name"><?= htmlspecialchars($r['NAME']) ?></div>
                            <div class="tochka-row__field">
                                <select class="tochka-select" name="iiko_id[<?= (int)$r['ID'] ?>]" disabled>
                                    <?php if (!empty($r['XML_ID'])): ?>
                                        <option value="<?= htmlspecialchars($r['XML_ID']) ?>" selected><?= htmlspecialchars($r['XML_ID']) ?></option>
                                    <?php else: ?>
                                        <option value="">— данные не загружены —</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($restaurants)): ?>
                        <div style="padding:20px;text-align:center;color:var(--color-muted);">
                            Рестораны не найдены. Добавьте их в разделе
                            <a href="/partners/delivery-zones/restorany/" style="color:var(--bg-button);">«Рестораны»</a>.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Действия -->
                <div class="tochka-actions">
                    <button type="button" id="tochka-load-btn" class="p-btn p-btn--primary"
                            style="padding:10px 24px; font-size:14px;" onclick="loadTochkiIiko()">
                        Загрузить данные из IIKO
                    </button>
                    <button type="button" id="tochka-save-btn" class="p-btn p-btn--outline"
                            style="padding:10px 24px; font-size:14px; display:none;" onclick="saveTochki()">
                        Сохранить соответствия
                    </button>
                </div>
            </div>
        </div>

<script>
var tochkiOrganizations = [];

function showTochkaMsg(text, ok) {
    var msg = document.getElementById('tochka-message');
    msg.style.display = 'block';
    msg.style.background = ok ? 'rgba(46,204,113,.12)' : 'rgba(231,76,60,.12)';
    msg.style.color = ok ? '#2ecc71' : '#e74c3c';
    msg.innerHTML = (ok ? '✓ ' : '✗ ') + text;
}

function setTochkaAreaActive(active) {
    var note = document.getElementById('tochka-inactive-note');
    var text = document.getElementById('tochka-note-text');
    var selects = document.querySelectorAll('.tochka-select');
    var saveBtn = document.getElementById('tochka-save-btn');

    selects.forEach(function (s) {
        s.disabled = !active;
    });

    if (active) {
        note.classList.add('is-active');
        text.textContent = 'Область активна. Выберите ID точки iiko для каждого ресторана и сохраните соответствия.';
        saveBtn.style.display = 'inline-block';
    } else {
        note.classList.remove('is-active');
        text.textContent = 'Область неактивна. Нажмите «Загрузить данные из IIKO», чтобы получить список точек.';
        saveBtn.style.display = 'none';
    }
}

function fillTochkaSelects() {
    document.querySelectorAll('.tochka-row').forEach(function (row) {
        var select = row.querySelector('.tochka-select');
        if (!select) return;

        var current = (row.getAttribute('data-xml') || '').trim();
        var name    = row.querySelector('.tochka-row__name').textContent.trim();

        // Сохраняем список опций (без удаления текущего выбора)
        select.innerHTML = '';
        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = '— выберите точку iiko —';
        select.appendChild(empty);

        tochkiOrganizations.forEach(function (org) {
            var opt = document.createElement('option');
            opt.value = org.id;
            opt.textContent = org.name ? (org.name + ' (' + org.id + ')') : org.id;
            select.appendChild(opt);
        });

        // Если сохранённое значение отсутствует в списке организаций — оставляем его опцией,
        // чтобы выбранное соответствие не терялось
        var hasCurrent = false;
        for (var j = 0; j < tochkiOrganizations.length; j++) {
            if (tochkiOrganizations[j].id === current) { hasCurrent = true; break; }
        }
        if (current && !hasCurrent) {
            var curOpt = document.createElement('option');
            curOpt.value = current;
            curOpt.textContent = current;
            select.appendChild(curOpt);
        }

        // Предвыбор: сначала сохранённый XML_ID, иначе сопоставление по названию
        var matchValue = '';
        if (current) {
            matchValue = current;
        } else {
            var lowerName = name.toLowerCase();
            for (var i = 0; i < tochkiOrganizations.length; i++) {
                if (tochkiOrganizations[i].name.toLowerCase() === lowerName) {
                    matchValue = tochkiOrganizations[i].id;
                    break;
                }
            }
        }
        select.value = matchValue;
    });
}

function loadTochkiIiko() {
    var btn = document.getElementById('tochka-load-btn');
    btn.disabled = true;
    btn.textContent = 'Загрузка...';

    var formData = new FormData();
    formData.append('ajax_tochka', '1');
    formData.append('action', 'load');
    formData.append('sessid', '<?= bitrix_sessid() ?>');

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        btn.disabled = false;
        btn.textContent = 'Загрузить данные из IIKO';

        if (data && data.success) {
            tochkiOrganizations = data.organizations || [];
            fillTochkaSelects();
            setTochkaAreaActive(true);
            showTochkaMsg('Данные загружены из IIKO: ' + tochkiOrganizations.length + ' точек.', true);
        } else {
            showTochkaMsg((data && data.error) ? data.error : 'Ошибка загрузки данных из IIKO.', false);
        }
    })
    .catch(function () {
        btn.disabled = false;
        btn.textContent = 'Загрузить данные из IIKO';
        showTochkaMsg('Ошибка запроса к серверу.', false);
    });
}

function saveTochki() {
    var map = {};
    document.querySelectorAll('.tochka-row').forEach(function (row) {
        var select = row.querySelector('.tochka-select');
        var id = row.getAttribute('data-id');
        if (select && id) {
            map[id] = select.value;
        }
    });

    var btn = document.getElementById('tochka-save-btn');
    btn.disabled = true;
    btn.textContent = 'Сохранение...';

    var formData = new FormData();
    formData.append('ajax_tochka', '1');
    formData.append('action', 'save');
    formData.append('sessid', '<?= bitrix_sessid() ?>');
    formData.append('map', JSON.stringify(map));

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        btn.disabled = false;
        btn.textContent = 'Сохранить соответствия';

        if (data && data.success) {
            showTochkaMsg('Соответствия сохранены (' + (data.saved || 0) + ').', true);
            // Обновляем сохранённые XML_ID на текущей странице
            document.querySelectorAll('.tochka-row').forEach(function (row) {
                var select = row.querySelector('.tochka-select');
                var id = row.getAttribute('data-id');
                if (select && id && map.hasOwnProperty(id)) {
                    row.setAttribute('data-xml', select.value);
                }
            });
        } else {
            showTochkaMsg((data && data.error) ? data.error : 'Ошибка сохранения.', false);
        }
    })
    .catch(function () {
        btn.disabled = false;
        btn.textContent = 'Сохранить соответствия';
        showTochkaMsg('Ошибка запроса к серверу.', false);
    });
}
</script>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
