<?php
    include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";

    db_connect();

    // [SEC-1] чтение данных - только для вошедших пользователей (раньше было доступно анониму)
    require_auth();

    $tblName = $_GET["tblName"] ?? "";
    $table = new ObjectTable($tblName); // [SEC] белый список + [SEC-1] право чтения таблицы в конструкторе

    echo $table->showData();

    db_disconnect();
?>
