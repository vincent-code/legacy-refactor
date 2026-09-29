<div class="main">
    <ul>
        <li><a href="/admin/parts/journals.php?cache=18">Журналы</a></li>
        <?
            if ($_SESSION["ID_NL_USER_PERMISSION"] == "2") {
                ?>
                <li><a href="/admin/parts/dicts.php?cache=18">Справочники</a></li>
                <?
            }
        ?>
    </ul>
</div>
<div class="admin__left">
    <input id="admin__my" type="checkbox" class="admin__my g-checkbox" <?= $_SESSION["onlymy"] == "1" ? 'checked="checked"' : "" ?> /> <label class="g-label" for="admin__my">Показывать только мои записи</label>
</div>
<div class="admin__right">
    <span class="admin__userName"><?= $_SESSION["NL_USER_SHORT"] ?></span>
    <button class="g-btn admin__exit">Выход</button>
    <span class="g-stretch"></span>
</div>
<script>
    $(".g-btn").button();
</script>