<p>✅ Модуль <b>«AI Консультант» (семантический поиск)</b> успешно установлен.</p>

<h4>Что дальше?</h4>
<ol>
    <li><b>Настройте инфоблоки каталога</b> в <a href="/bitrix/admin/settings.php?mid=mibazarow.smartconsultant&lang=ru">настройках модуля</a> — укажите ID инфоблоков через запятую.</li>
    <li><b>Убедитесь, что Python-окружение настроено</b> на Linux-сервере (см. <code>python/README.md</code>).</li>
    <li><b>Запустите HTTP-сервис эмбеддингов:</b> <code>systemctl enable --now mib-smartconsultant</code></li>
    <li><b>Запустите агента индексации вручную</b> для первой индексации товаров (или дождитесь запуска по расписанию).</li>
    <li><b>Разместите компонент</b> <code>mibazarow:smartconsultant.search</code> на нужной странице.</li>
</ol>

<p>📖 Подробная инструкция: <code>/local/modules/mibazarow.smartconsultant/README.md</code></p>
