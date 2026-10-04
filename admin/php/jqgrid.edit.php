<?php
    include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";

    db_connect();

    // [SEC-1] запись только для вошедших пользователей; [SEC-5] только POST + CSRF-токен
    require_auth();
    csrf_check();

    $tblName = $_GET["tblName"] ?? "";
    $table = new ObjectTable($tblName); // [SEC] имя таблицы проверяется по белому списку, [SEC-1] и право чтения таблицы - в конструкторе

    // [SEC] oper и id проверяются внутри saveData() (белый список операций, id - только целое)
    // [SEC-2] права на таблицу/запись/поля тоже проверяются внутри saveData()
    $oper = $_POST["oper"] ?? "";
    $id = $_POST["ID_" . $tblName] ?? null;
    if ($oper === "del") {
        $id = $_POST["id"] ?? null;
    }

    $table->saveData($oper, $id, $_POST);

    db_disconnect();
?>
