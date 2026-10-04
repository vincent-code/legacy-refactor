<?php
    // [SEC-1] Файл доступен по прямому URL (/admin/parts/*.php) - подключаем окружение сами
    // и проверяем вход. При подключении из index.php функции уже загружены и БД подключена.
    if (!function_exists("require_auth")) {
        include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
        include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
        include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";
        db_connect();
    }
    require_auth(array(2)); // [SEC-1] справочники - только администратор
?>
<div class="dicts g-tabs-full">
    <ul>
        <li><a href="/admin/php/jqgrid.table.php?cache=20&tblName=NL_VIEW">Вид из окна</a></li>
        <li><a href="/admin/php/jqgrid.table.php?cache=20&tblName=NL_HOUSES">Тип дома</a></li>
        <li><a href="/admin/php/jqgrid.table.php?cache=20&tblName=NL_MATERIAL">Материал дома</a></li>
        <li><a href="/admin/php/jqgrid.table.php?cache=20&tblName=NL_USER">Пользователи</a></li>
    </ul>
</div>
<div class="g-tabs-hide">←</div>
<script>
    detailTabs(".dicts");
</script>
