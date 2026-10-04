<?php
    include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";

    db_connect();

    // [SEC-1] разметка таблицы (вместе с настройками колонок) - только для вошедших пользователей
    require_auth();

    $tblName = $_GET["tblName"] ?? "";
    $table = new ObjectTable($tblName); // [SEC] белый список + [SEC-1] право чтения таблицы в конструкторе

    $table->renderTable();

    db_disconnect();
?>
