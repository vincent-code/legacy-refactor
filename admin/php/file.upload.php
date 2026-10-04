<?php
    include $_SERVER["DOCUMENT_ROOT"] . "/php/config.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/php/functions.php";
    include $_SERVER["DOCUMENT_ROOT"] . "/admin/php/functions.admin.php";

    db_connect();

    // [SEC-1] загрузка файлов - только для вошедших пользователей; [SEC-5] только POST + CSRF-токен
    // (CSRF-токен клиент передаёт полем csrf_token в данных загрузки, см. js.combined.js)
    require_auth();
    csrf_check();

    function upload_fail($message) {
        http_response_code(400);
        echo "Error: " . $message . "\n";
        db_disconnect();
        exit;
    }

    // Файл приходит либо как files[] (jQuery File Upload), либо как $_FILES[0]
    $baseFileName = "";
    $baseTmpName = "";
    $uploadError = UPLOAD_ERR_NO_FILE;
    if (isset($_FILES["files"]) && is_array($_FILES["files"]["name"])) {
        $baseFileName = $_FILES["files"]["name"][0] ?? "";
        $baseTmpName = $_FILES["files"]["tmp_name"][0] ?? "";
        $uploadError = $_FILES["files"]["error"][0] ?? UPLOAD_ERR_NO_FILE;
    } elseif (isset($_FILES[0])) {
        $baseFileName = $_FILES[0]["name"] ?? "";
        $baseTmpName = $_FILES[0]["tmp_name"] ?? "";
        $uploadError = $_FILES[0]["error"] ?? UPLOAD_ERR_NO_FILE;
    }
    if (($uploadError !== UPLOAD_ERR_OK) || !is_string($baseTmpName) || !is_string($baseFileName) || !is_uploaded_file($baseTmpName)) {
        upload_fail("File uploading failed.");
    }

    // [SEC] table/col/id раньше брались из $_REQUEST и шли прямо в путь файла и в регулярное выражение
    // (path traversal: table=../../x, col=../../x). $_REQUEST включает COOKIE - берём только GET/POST.
    $input = array_merge($_GET, $_POST);

    // [SEC] таблица - только из белого списка
    $tbl = $input["table"] ?? "";
    if (!ObjectTable::isAllowedTable($tbl)) {
        upload_fail("Bad table.");
    }
    // [SEC-2] загружать файлы может только тот, кто вправе изменять записи этой таблицы
    // (гость и обычный пользователь для справочников - нет)
    if (!ObjectTable::canAccess($tbl, "write")) {
        http_response_code(403);
        db_disconnect();
        exit("Forbidden");
    }

    // [SEC] id - только цифры
    // [SEC-7] id больше НЕ входит в имя файла (оно случайное); проверка оставлена для совместимости с клиентом
    $id = $input["id"] ?? "";
    if (!is_string($id) || !ctype_digit($id) || (strlen($id) > 18)) {
        upload_fail("Bad id.");
    }

    // [SEC] колонка - только существующая колонка этой таблицы типа photo/photos/file
    $colParam = $input["col"] ?? "";
    $table = new ObjectTable($tbl);
    $colObj = null;
    if (is_string($colParam)) {
        foreach ($table->colArray as $key => $c) {
            if (is_int($key) && ($c->dbName === $colParam) && in_array($c->type, array("photo", "photos", "file"), true)) {
                $colObj = $c;
                break;
            }
        }
    }
    if ($colObj === null) {
        upload_fail("Bad column.");
    }
    $col = str_replace($tbl . "_", "", $colObj->dbName); // безопасно: имя колонки задано в коде

    // [SEC-7] ограничение размера на сервере (клиентский maxSize - только подсказка).
    // Не забудьте согласовать upload_max_filesize / post_max_size в php.ini (>= этих значений).
    $maxBytes = ($colObj->type === "file") ? (20 * 1024 * 1024) : (10 * 1024 * 1024);
    $fileSize = @filesize($baseTmpName);
    if (($fileSize === false) || ($fileSize <= 0) || ($fileSize > $maxBytes)) {
        upload_fail("File is too large or empty.");
    }

    // [SEC] расширение - белый список (раньше чёрный список из 5 расширений: .phar, .php7, .pht, .phps, .htaccess проходили)
    $ext = strtolower(pathinfo($baseFileName, PATHINFO_EXTENSION));
    if ($colObj->type === "file") {
        $allowedExt = array("jpg", "jpeg", "png", "gif", "webp", "pdf", "doc", "docx", "xls", "xlsx");
    } else {
        $allowedExt = array("jpg", "jpeg", "png", "gif", "webp");
    }
    if (!in_array($ext, $allowedExt, true)) {
        upload_fail("This file type is not allowed!");
    }
    // [SEC] для фото - проверяем, что содержимое действительно изображение
    if ($colObj->type !== "file") {
        $imgInfo = @getimagesize($baseTmpName);
        if ($imgInfo === false) {
            upload_fail("File is not an image!");
        }
        // [SEC-7] тип по содержимому должен быть из списка и соответствовать расширению
        // (файл evil.php.jpg с PHP-кодом в теле не проходит getimagesize; здесь отсекаем подмену типа)
        $imgTypeExt = array(IMAGETYPE_JPEG => array("jpg", "jpeg"), IMAGETYPE_PNG => array("png"), IMAGETYPE_GIF => array("gif"), IMAGETYPE_WEBP => array("webp"));
        if (!isset($imgTypeExt[$imgInfo[2]]) || !in_array($ext, $imgTypeExt[$imgInfo[2]], true)) {
            upload_fail("Image type does not match extension!");
        }
        if (($imgInfo[0] > 10000) || ($imgInfo[1] > 10000)) {
            upload_fail("Image is too big!");
        }
    } else {
        // [SEC-7] для документов - MIME по содержимому (finfo), а не по заголовку клиента
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($baseTmpName);
        $allowedMime = array("image/jpeg", "image/png", "image/gif", "image/webp", "application/pdf", "application/msword",
            "application/vnd.ms-excel", "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "application/zip", "application/CDFV2",
            "application/vnd.ms-office", "application/octet-stream");
        if (!in_array($mime, $allowedMime, true)) {
            upload_fail("This file type is not allowed!");
        }
    }

    // [SEC-7] каталог загрузки берём из ObjectTable - тот же каталог использует и очистка файлов в saveData()
    // (раньше: загрузка в /img/<table>/, а очистка искала в /img/objects/<TABLE>/ - файлы не удалялись никогда)
    $dirUrl = $table->getUploadDirUrl();
    $dirPath = $_SERVER["DOCUMENT_ROOT"] . $dirUrl;
    if (!is_dir($dirPath) && !@mkdir($dirPath, 0755, true) && !is_dir($dirPath)) {
        upload_fail("Upload directory is not available.");
    }

    // [SEC-7] имя файла - случайное (раньше col_id_дата - предсказуемое, можно угадать URL чужой загрузки)
    $filename = $dirUrl . $col . "_" . bin2hex(random_bytes(16)) . "." . $ext;
    $uploadfile = $_SERVER["DOCUMENT_ROOT"] . $filename;

    if (move_uploaded_file($baseTmpName, $uploadfile)) {
        @chmod($uploadfile, 0644); // без бита исполнения
        // [SEC-2] запоминаем загруженный файл в сессии: saveData() разрешит записать в строку только
        // свои загрузки или уже сохранённые в этой строке файлы (нельзя подставить чужой URL и потом удалить чужой файл)
        if (!isset($_SESSION["uploaded_files"]) || !is_array($_SESSION["uploaded_files"])) {
            $_SESSION["uploaded_files"] = array();
        }
        $_SESSION["uploaded_files"][$filename] = time();
        if (count($_SESSION["uploaded_files"]) > 200) {
            $_SESSION["uploaded_files"] = array_slice($_SESSION["uploaded_files"], -200, null, true);
        }
        // [SEC-7] ответ - JSON, браузер не должен "угадывать" тип
        header("Content-Type: application/json; charset=utf-8");
        header("X-Content-Type-Options: nosniff");
        echo json_encode($filename, JSON_UNESCAPED_SLASHES);
    } else {
        http_response_code(500);
        echo "Error: File uploading failed.\n";
        db_disconnect();
        die();
    }

    db_disconnect();
?>
