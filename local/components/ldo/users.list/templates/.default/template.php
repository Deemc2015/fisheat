<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
?>
<div class="p-section__header">
    <h2 class="p-section__title">Пользователи сайта</h2>
    <?php if ($arResult['USER_ID'] > 0): ?>
        <span style="font-size:13px; color:var(--color-muted);">
            Пользователь #<?= (int)$arResult['USER_ID'] ?> ·
            <a href="./" style="color:var(--bg-button, #F44336);">показать всех</a>
        </span>
    <?php else: ?>
        <span style="font-size:13px; color:var(--color-muted);">Всего: <?= (int)$arResult['TOTAL_COUNT'] ?></span>
    <?php endif; ?>
</div>

<form class="p-users-filter" method="get" action="">
    <?php if ($arResult['USER_ID'] > 0): ?>
        <input type="hidden" name="ID" value="<?= (int)$arResult['USER_ID'] ?>">
    <?php endif; ?>
    <div class="p-users-filter__row">
        <div class="p-users-filter__group p-users-filter__group--grow">
            <label for="p-users-search">Поиск</label>
            <input type="text" id="p-users-search" name="SEARCH" value="<?= htmlspecialchars($arResult['SEARCH']) ?>" placeholder="Имя, фамилия, логин или телефон">
        </div>
        <div class="p-users-filter__group">
            <label for="p-users-status">Статус</label>
            <select id="p-users-status" name="USER_STATUS">
                <option value="">Все статусы</option>
                <option value="active" <?= $arResult['USER_STATUS'] === 'active' ? 'selected' : '' ?>>Активен</option>
                <option value="inactive" <?= $arResult['USER_STATUS'] === 'inactive' ? 'selected' : '' ?>>Не активен</option>
                <option value="blocked" <?= $arResult['USER_STATUS'] === 'blocked' ? 'selected' : '' ?>>Заблокирован</option>
            </select>
        </div>
        <div class="p-users-filter__actions">
            <button type="submit" class="p-btn p-btn--primary">Применить</button>
            <a class="p-btn p-btn--ghost" href="?">Сбросить</a>
        </div>
    </div>
</form>

<div id="users-message" style="display:none; padding:12px 16px; border-radius:8px; margin-bottom:16px; background:rgba(231,76,60,.12); color:#e74c3c;"></div>

<div class="p-users-wrap">
    <div style="overflow-x:auto;">
        <table class="p-users-table">
            <thead>
                <tr>
                    <th style="text-align:center;">ID</th>
                    <th>ФИО</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Сайт</th>
                    <th>Дата регистрации</th>
                    <th>Последний вход</th>
                    <th style="text-align:center;">Активность</th>
                    <th style="text-align:center;">Заблокирован</th>
                    <th style="text-align:center;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($arResult['USERS'])): ?>
                    <tr>
                        <td colspan="10">
                            <div class="p-users-empty">Пользователи не найдены</div>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($arResult['USERS'] as $u): ?>
                    <tr>
                        <td style="text-align:center; color:var(--color-muted);"><?= (int)$u['ID'] ?></td>
                        <td>
                            <div class="p-users-fio"><?= htmlspecialchars($u['FIO']) ?></div>
                            <div class="p-users-login"><?= htmlspecialchars($u['LOGIN']) ?></div>
                        </td>
                        <td><?= htmlspecialchars($u['EMAIL'] !== '' ? $u['EMAIL'] : '—') ?></td>
                        <td><?= htmlspecialchars($u['PHONE'] !== '' ? $u['PHONE'] : '—') ?></td>
                        <td>
                            <?php if ($u['SITE_NAME'] !== ''): ?>
                                <?= htmlspecialchars($u['SITE_NAME']) ?> <span style="color:var(--color-muted);">(<?= htmlspecialchars($u['LID']) ?>)</span>
                            <?php elseif ($u['LID'] !== ''): ?>
                                <?= htmlspecialchars($u['LID']) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($u['DATE_REGISTER']) ?></td>
                        <td><?= htmlspecialchars($u['LAST_LOGIN']) ?></td>
                        <td class="p-users-toggle-cell">
                            <?php if ($u['IS_SELF']): ?>
                                <span style="color:var(--color-muted); font-size:12px;">вы</span>
                            <?php else: ?>
                                <label class="p-checkbox" title="Активность">
                                    <input type="checkbox" class="js-users-toggle" data-id="<?= (int)$u['ID'] ?>" data-field="ACTIVE" <?= $u['ACTIVE'] ? 'checked' : '' ?>>
                                    <span class="p-checkbox__box">
                                        <svg class="p-checkbox__check" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" fill="none" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </span>
                                </label>
                            <?php endif; ?>
                        </td>
                        <td class="p-users-toggle-cell">
                            <?php if ($u['IS_SELF']): ?>
                                <span style="color:var(--color-muted); font-size:12px;">—</span>
                            <?php else: ?>
                                <label class="p-checkbox p-checkbox--danger" title="Заблокирован">
                                    <input type="checkbox" class="js-users-toggle" data-id="<?= (int)$u['ID'] ?>" data-field="BLOCKED" <?= $u['BLOCKED'] ? 'checked' : '' ?>>
                                    <span class="p-checkbox__box">
                                        <svg class="p-checkbox__check" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" fill="none" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </span>
                                </label>
                            <?php endif; ?>
                        </td>
                        <td class="p-users-toggle-cell">
                            <?php if ($u['IS_SELF']): ?>
                                <span style="color:var(--color-muted); font-size:12px;">—</span>
                            <?php else: ?>
                                <button type="button" class="p-users-delete" data-id="<?= (int)$u['ID'] ?>" data-name="<?= htmlspecialchars($u['FIO'], ENT_QUOTES) ?>" title="Удалить пользователя">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 19C6 20.1 6.9 21 8 21H16C17.1 21 18 20.1 18 19V7H6V19ZM8 9H16V19H8V9ZM15.5 4L14.5 3H9.5L8.5 4H5V6H19V4H15.5Z" fill="currentColor"/></svg>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($arResult['PAGE_COUNT'] > 1): ?>
        <div class="p-users-nav">
            <?php if ($arResult['CURRENT_PAGE'] > 1): ?>
                <a href="<?= htmlspecialchars($arResult['PREV_URL']) ?>">‹</a>
            <?php else: ?>
                <span class="page disabled">‹</span>
            <?php endif; ?>

            <?php foreach ($arResult['WINDOW'] as $p): ?>
                <?php if ($p['CURRENT']): ?>
                    <span class="page current"><?= (int)$p['PAGE'] ?></span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($p['URL']) ?>"><?= (int)$p['PAGE'] ?></a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($arResult['CURRENT_PAGE'] < $arResult['PAGE_COUNT']): ?>
                <a href="<?= htmlspecialchars($arResult['NEXT_URL']) ?>">›</a>
            <?php else: ?>
                <span class="page disabled">›</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
