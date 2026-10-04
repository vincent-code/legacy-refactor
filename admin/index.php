<?php
include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";

db_connect();

if ((isset($_POST["login"])) && (isset($_POST["password"]))) {
    // [SEC-5] CSRF для формы входа (login CSRF); перебор пароля ограничивается внутри user_auth()
    if (csrf_token_valid()) {
        user_auth($_POST["login"], $_POST["password"]);
    } else {
        $_SESSION["login_error"] = "Сессия устарела, повторите попытку";
    }
    header("Location: /admin/", true, 303);
    die();
}
// [SEC-1] is_logged_in() сверяет сессию с БД (пользователь существует, актуальные права, таймаут простоя)
if ((!is_logged_in()) && ($url[0] != "/admin/login/")) {
    header("Location: /admin/login/", true, 303);
    die();
}
?>
<!doctype html>
<html lang="ru" class="html-<?= html_esc($page) ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Административная панель</title>
    <meta name="viewport" content="width=700, maximum-scale=1.0, user-scalable=no">
    <?php /* [SEC-5] CSRF-токен для JS: js.combined.js добавляет его во все не-GET AJAX-запросы */ ?>
    <meta name="csrf-token" content="<?= html_esc(csrf_token()) ?>">
    <link rel="stylesheet" href="/admin/css/css.combined.css">
    <script src="/admin/js/js.combined.js"></script>
</head>
<?php /* [SEC-6] $page выводился без экранирования */ ?>
<body class="admin admin-<?= html_esc($page) ?>">
<?php includeAdminPartsByLvl(); ?>
<script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU"></script>
</body>
</html>
<?php db_disconnect(); ?>
