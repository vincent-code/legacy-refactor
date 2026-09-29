<?php
    include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";

    db_connect();

    $tblName = $_GET["tblName"];
    $table = new ObjectTable($tblName);

    /*$id = -1;
    if (isset($_POST) && (isset($_POST["id"])) && (trim($_POST["id"] != "_empty"))) {
        $id = $_POST["id"];
    }*/
    $id = $_POST["ID_" . $tblName];
    if ($_POST["oper"] == "del") {
        $id = $_POST["id"];
    }

    $table->saveData($_POST["oper"], $id, $_POST);

    db_disconnect();
?>