<?
    include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";
    
    db_connect();
    if ((isset($_POST["login"])) && (isset($_POST["password"]))) {
        user_auth($_POST["login"], $_POST["password"]);
        header("Location: /admin/", true, 303);
        die();
    }
    if ((!isset($_SESSION["ID_NL_USER"]) || (!isset($_SESSION["ID_NL_USER_PERMISSION"]))) && ($url[0] != "/admin/login/")) {
        header("Location: /admin/login/", true, 303);
        die();
    }
?>
<!doctype html>
<html lang="ru" class="html-<?= $page ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Административная панель</title>
    <meta name="viewport" content="width=700, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="/admin/css/css.combined.020.css">
    <script src="/admin/js/js.combined.020.js"></script>
</head>
<body class="admin admin-<?= $page ?>">
    <? includeAdminPartsByLvl(); ?>
    <script src="//api-maps.yandex.ru/2.1/?lang=ru_RU"></script>
</body>
</html>
<? db_disconnect(); ?>