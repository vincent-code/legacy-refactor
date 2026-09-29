<?php
    include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";

    db_connect();

    $_SESSION["onlymy"] = $_POST["onlymy"];

    db_disconnect();
?>