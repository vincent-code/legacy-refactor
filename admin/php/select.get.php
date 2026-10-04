<?php
    include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";

    db_connect();

    // [SEC-1] список значений справочников - только для вошедших пользователей
    require_auth();

    $tblParent = $_POST["tblParent"] ?? "";
    $tblChild = $_POST["tblChild"] ?? "";
    // [SEC] idParent - только целое число
    $idParent = filter_var($_POST["idParent"] ?? null, FILTER_VALIDATE_INT);

    // [SEC] имена таблиц - только из белого списка (через плейсхолдер их передать нельзя)
    $lookupTables = array_merge(ObjectTable::TABLES, array("NL_USER_PERMISSION"));
    if (!in_array($tblParent, $lookupTables, true) || !in_array($tblChild, $lookupTables, true) || ($idParent === false)) {
        http_response_code(400);
        db_disconnect();
        exit("Bad request");
    }

    $options = "";

    $query = "SELECT * FROM " . db_ident($tblChild) . " WHERE " . db_ident("ID_" . $tblParent) . " = ?";
    $res = db_prepared($query, array($idParent));
    if ($res) {
        // [SEC] db_fetch_array в functions.php не определена - используем db_fetch_assoc;
        // значения экранируются для HTML (раньше - XSS через данные справочников)
        while ($row = db_fetch_assoc($res)) {
            $options .= '<option value="' . html_esc($row["ID_" . $tblChild] ?? "") . '">' . html_esc($row[$tblChild . "_SHORT"] ?? "") . '</option>';
        }
    }

    echo $options;

    db_disconnect();
?>
