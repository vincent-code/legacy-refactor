<?php
    // [SEC-1] Страница входа доступна без авторизации, но при прямом обращении нужна сессия для CSRF-токена
    if (!function_exists("csrf_token")) {
        include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
        include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
        include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";
    }
?>
<h1>Выполните вход:</h1>
<?php
    // [SEC-5] Сообщение об ошибке входа / блокировке (текст задаётся только в коде, но экранируем по привычке)
    if (!empty($_SESSION["login_error"])) {
        ?>
        <p class="login__error"><?= html_esc($_SESSION["login_error"]) ?></p>
        <?php
        unset($_SESSION["login_error"]);
    }
?>
<br/>
<form action="/admin/" class="login" method="post" autocomplete="off">
    <?php /* [SEC-5] CSRF-токен формы входа */ ?>
    <input type="hidden" name="csrf_token" value="<?= html_esc(csrf_token()) ?>"/>
    <label class="login__label" for="login">Логин:</label>
    <input id="login" name="login" class="login__login" type="text" maxlength="50" autocomplete="username"/><br/><br/>
    <label class="login__label" for="password">Пароль:</label>
    <input id="password" name="password" class="login__password" type="password" maxlength="255" autocomplete="current-password"/><br/><br/>
    <input type="submit" id="submit" name="submit" class="login__submit" value="Вход"/>
</form>
