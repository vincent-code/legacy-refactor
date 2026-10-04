<?php
    include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";

    db_connect();

    // [SEC-5] выход меняет состояние: только POST + CSRF-токен (раньше - GET, выход по ссылке/картинке с чужого сайта)
    csrf_check();

    user_logout();

    db_disconnect();
?>
