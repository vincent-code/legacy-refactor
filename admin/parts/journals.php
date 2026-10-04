<?php
    // [SEC-1] Файл доступен по прямому URL (/admin/parts/*.php) - подключаем окружение сами
    // и проверяем вход. При подключении из index.php функции уже загружены и БД подключена.
    if (!function_exists("require_auth")) {
        include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
        include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
        include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";
        db_connect();
    }
    require_auth();
?>
<div class="objects g-tabs-full">
    <ul>
        <li><a href="/admin/php/jqgrid.table.php?cache=20&tblName=NL_PROP_RESALE">Вторичка</a></li>
    </ul>
</div>
<div class="g-tabs-hide">←</div>
<script>
    detailTabs(".objects");
</script>
