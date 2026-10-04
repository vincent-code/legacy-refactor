<?php
    include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";

    db_connect();

    // [SEC-1] только для вошедших; [SEC-5] только POST + CSRF-токен
    require_auth();
    csrf_check();

    // [SEC] $_REQUEST включает COOKIE и GET; JS шлёт POST. Имя таблицы - только из белого списка
    $tbl = $_POST["table"] ?? "";
    if (!ObjectTable::isAllowedTable($tbl)) {
        http_response_code(400);
        db_disconnect();
        exit("Bad request");
    }
    // [SEC-2] номер для новой записи нужен только тем, кто вправе добавлять в эту таблицу
    if (!ObjectTable::canAccess($tbl, "write")) {
        http_response_code(403);
        db_disconnect();
        exit("Forbidden");
    }

    // [SEC] значения - через плейсхолдеры
    $query = "SELECT AUTO_INCREMENT FROM INFORMATION_SCHEMA.TABLES WHERE (TABLE_SCHEMA = ?) AND (TABLE_NAME = ?)";
    $res = db_prepared($query, array(DBNAME, $tbl));
    $row = $res ? db_fetch_assoc($res) : null;
    $id = (int)($row["AUTO_INCREMENT"] ?? 0);

    // [SEC-9] УДАЛЕНО: ALTER TABLE ... AUTO_INCREMENT = id + 1 при каждом открытии формы.
    // Из-за него каждое открытие/отмена формы "сжигало" id (DoS нумерации), а два параллельных запроса
    // получали один и тот же номер (гонка). Теперь скрипт только ЧИТАЕТ ориентировочный номер для формы;
    // реальный id назначает СУБД при INSERT (saveData() игнорирует id из формы при добавлении).
    echo $id;

    db_disconnect();
?>
