<?php
    include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";

    db_connect();

    $baseFileName = "";
    if (isset($_FILES["files"])) {
        $baseFileName = $_FILES["files"]["name"][0];
    } else {
        $baseFileName = $_FILES[0]["name"];
    }

    $baseTmpName = "";
    $ext = "";
    if (isset($_FILES["files"])) {
        $baseTmpName = $_FILES["files"]["tmp_name"][0];
        $ext = $_FILES["files"]["name"][0];
    } else {
        $baseTmpName = $_FILES[0]["tmp_name"];
        $ext = $_FILES[0]["name"];
    }
    $ext = explode(".", $ext);
    $ext = $ext[count($ext) - 1];

    $blacklist = array(".php", ".phtml", ".php3", ".php4", ".php5");
    foreach ($blacklist as $item) {
        //for ($i = 0; $i < count($_FILES["files"]["name"]); $i++) {
        if (preg_match("/$item\$/i", $baseFileName)) {
            echo "Error: We do not allow uploading PHP files!\n";
            die();
        }
        //}
    }

    $tbl = $_REQUEST["table"];
    $col = mb_ereg_replace($tbl . "_", "", $_REQUEST["col"]);
    $dir = strtolower(str_replace("NL_", "", $tbl));
    $date = date('ymd_his', time());
    $id = $_REQUEST["id"];

    $strpos = mb_strrpos($baseTmpName, "/");
    if ($strpos === false) {
        $strpos = mb_strrpos($baseTmpName, "\\");
    }

    //$tmpName = mb_ereg_replace(".tmp", "", mb_substr($baseTmpName, $strpos + 1));

    //print_r($_FILES["files"]);

    //$files_array = Array();
    //for ($i = 0; $i < count($_FILES["files"]["name"]); $i++) {
    $filename = "/img/" . $dir . "/" . $col . "_" . $id . "_" /*. $tmpName . "_"*/ . $date . ".$ext";
    $uploadfile = $_SERVER["DOCUMENT_ROOT"] . $filename;

    if (move_uploaded_file($baseTmpName, $uploadfile)) {
        //array_push($files_array, $filename);
        echo '"' . $filename . '"';
    } else {
        echo "Error: File uploading failed.\n";
        die();
    }
    //}

    //echo json_encode($files_array);

    //print_r($_SERVER);
    db_disconnect();
?>