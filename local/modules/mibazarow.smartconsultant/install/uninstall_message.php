<p>Модуль <b>«AI Консультант»</b> удалён.</p>

<form action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>" method="post">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="uninstall" value="Y">
    <p>
        <input type="checkbox" name="save_tables" value="Y" id="save_tables">
        <label for="save_tables">Сохранить таблицу эмбеддингов (mib_smartconsultant_embedding)</label>
    </p>
    <p>
        <input type="checkbox" name="save_components" value="Y" id="save_components">
        <label for="save_components">Сохранить компонент в /bitrix/components/</label>
    </p>
</form>
