<?php
    include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";

    db_connect();

    $tblParent = $_POST["tblParent"];
    $idParent = $_POST["idParent"];
    $tblChild = $_POST["tblChild"];

    $options = "";

    $query = "SELECT * FROM " . $tblChild . " WHERE ID_" . $tblParent . " = " . $idParent;
    $res = db_query($query);
    while ($row = db_fetch_array($res)) {
        $options .= '<option value="' . $row["ID_" . $tblChild] . '">' . $row[$tblChild . "_SHORT"] . '</option>';
    }

    echo $options;

    db_disconnect();
?>