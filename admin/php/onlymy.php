<?php
    include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";

    db_connect();

    // [SEC-1] только для вошедших; [SEC-5] только POST + CSRF-токен
    require_auth();
    csrf_check();

    // [SEC] в сессию пишем только "1" или "0" (раньше - произвольное значение из $_POST)
    $_SESSION["onlymy"] = (($_POST["onlymy"] ?? "") === "1") ? "1" : "0";

    db_disconnect();
?>
