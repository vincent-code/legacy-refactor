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
<div class="main">
    <ul>
        <li><a href="/admin/parts/journals.php?cache=18">Журналы</a></li>
        <?php
            // [SEC-1] права берутся из сессии, синхронизированной с БД в require_auth()
            if ((int)($_SESSION["ID_NL_USER_PERMISSION"] ?? 0) === 2) {
                ?>
                <li><a href="/admin/parts/dicts.php?cache=18">Справочники</a></li>
                <?php
            }
        ?>
    </ul>
</div>
<div class="admin__left">
    <input id="admin__my" type="checkbox" class="admin__my g-checkbox" <?= ($_SESSION["onlymy"] ?? "") == "1" ? 'checked="checked"' : "" ?> /> <label class="g-label" for="admin__my">Показывать только мои записи</label>
</div>
<div class="admin__right">
    <span class="admin__userName"><?= htmlspecialchars((string)($_SESSION["NL_USER_SHORT"] ?? ""), ENT_QUOTES, "UTF-8") ?></span>
    <button class="g-btn admin__exit">Выход</button>
    <span class="g-stretch"></span>
</div>
<script>
    $(".g-btn").button();
</script>
