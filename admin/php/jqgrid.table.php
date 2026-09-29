<?
    include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";

    db_connect();

    $tblName = $_GET["tblName"];
    $table = new ObjectTable($tblName);

    $table->renderTable();

    db_disconnect();
?>