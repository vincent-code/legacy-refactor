<?php
    // [SEC-5] Cookie сессии: HttpOnly (недоступна JS), Secure (только по HTTPS), SameSite=Strict (не уходит с чужих сайтов);
    // session id - только из cookie (не из URL), строгий режим (сервер не принимает "придуманный" идентификатор сессии).
    $nlIsHttps = (!empty($_SERVER["HTTPS"]) && ($_SERVER["HTTPS"] !== "off"))
        || (($_SERVER["SERVER_PORT"] ?? "") == "443")
        || (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https"); // за обратным прокси с TLS
    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    session_set_cookie_params(array(
        "lifetime" => 0,
        "path" => "/",
        "secure" => $nlIsHttps,
        "httponly" => true,
        "samesite" => "Strict"
    ));
    session_start();

    // [SEC-5] Параметры защиты входа и сессии
    define("SESSION_IDLE_TIMEOUT", 7200); // сек. простоя до автоматического выхода
    define("LOGIN_MAX_FAILS_LOGIN", 5);   // неудачных попыток на один логин за окно блокировки
    define("LOGIN_MAX_FAILS_IP", 30);     // неудачных попыток с одного IP за окно блокировки
    define("LOGIN_LOCK_MINUTES", 15);     // окно блокировки, мин.

    // [SEC-5] Защитные заголовки для всех страниц и эндпоинтов админки (clickjacking, MIME-sniffing, утечка Referer).
    // CSP без ограничения script-src: в проекте много inline-скриптов, жёсткий CSP потребовал бы рефакторинга.
    function send_security_headers($isHttps) {
        if (headers_sent()) {
            return;
        }
        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: same-origin");
        header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'");
        if ($isHttps) {
            header("Strict-Transport-Security: max-age=31536000");
        }
    }
    send_security_headers($nlIsHttps);

    // IP клиента: только REMOTE_ADDR (заголовки X-Forwarded-For подделываются клиентом и для защиты не годятся)
    function client_ip() {
        $ip = $_SERVER["REMOTE_ADDR"] ?? "";
        return (filter_var($ip, FILTER_VALIDATE_IP) !== false) ? $ip : "unknown";
    }

    // [SEC-5] CSRF: токен хранится в сессии, сравнивается через hash_equals() (без утечки по времени)
    function csrf_token() {
        if (empty($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        }
        return $_SESSION["csrf_token"];
    }

    // Токен приходит в заголовке X-CSRF-Token (AJAX) или в поле csrf_token (формы, загрузка файлов)
    function csrf_token_valid() {
        $given = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? ($_POST["csrf_token"] ?? "");
        return is_string($given) && ($given !== "") && hash_equals(csrf_token(), $given);
    }

    // Для состояния-меняющих эндпоинтов: только POST и только с верным токеном
    function csrf_check() {
        if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
            http_response_code(405);
            header("Allow: POST");
            exit("Method Not Allowed");
        }
        if (!csrf_token_valid()) {
            http_response_code(403);
            exit("Bad CSRF token");
        }
    }

    // [SEC-1] Проверка входа. Состояние сессии сверяется с БД: удалённый пользователь теряет доступ сразу,
    // а смена прав (в т.ч. понижение администратора) действует без повторного входа. Нужен db_connect() заранее.
    function is_logged_in() {
        if (!isset($_SESSION["ID_NL_USER"]) || !isset($_SESSION["ID_NL_USER_PERMISSION"])) {
            return false;
        }
        // автоматический выход после простоя
        if ((time() - (int)($_SESSION["LAST_ACTIVITY"] ?? 0)) > SESSION_IDLE_TIMEOUT) {
            user_logout();
            return false;
        }
        $query = "SELECT ID_NL_USER_PERMISSION FROM NL_USER WHERE ID_NL_USER = ?";
        $res = db_prepared($query, array((int)$_SESSION["ID_NL_USER"]));
        $row = ($res instanceof mysqli_result) ? db_fetch_assoc($res) : null;
        if (!is_array($row)) {
            user_logout();
            return false;
        }
        $_SESSION["ID_NL_USER_PERMISSION"] = (int)$row["ID_NL_USER_PERMISSION"];
        $_SESSION["LAST_ACTIVITY"] = time();
        return true;
    }

    // [SEC-1] Единая точка входа для всех эндпоинтов и частей админки.
    // $allowedPerms - список допустимых ID_NL_USER_PERMISSION (null - любой вошедший пользователь).
    function require_auth($allowedPerms = null) {
        if (!is_logged_in()) {
            http_response_code(401);
            header("Content-Type: text/plain; charset=utf-8");
            exit("Unauthorized");
        }
        if (($allowedPerms !== null) && !in_array((int)$_SESSION["ID_NL_USER_PERMISSION"], $allowedPerms, true)) {
            http_response_code(403);
            header("Content-Type: text/plain; charset=utf-8");
            exit("Forbidden");
        }
    }

    // [SEC-5] Защита от перебора пароля: счётчики неудачных попыток в таблице NL_LOGIN_ATTEMPT (см. sql/stage2_migration.sql)
    function login_fail_count($column, $value) {
        $query = "SELECT COUNT(*) AS C FROM NL_LOGIN_ATTEMPT WHERE " . db_ident($column) . " = ? AND NL_LOGIN_ATTEMPT_TIME > (NOW() - INTERVAL " . (int)LOGIN_LOCK_MINUTES . " MINUTE)";
        $res = db_prepared($query, array($value));
        if (!($res instanceof mysqli_result)) {
            return 0; // таблица не создана миграцией - ошибка уже записана в лог, вход не блокируем
        }
        $row = db_fetch_assoc($res);
        return (int)($row["C"] ?? 0);
    }

    function login_is_locked($login) {
        return (login_fail_count("NL_LOGIN_ATTEMPT_LOGIN", $login) >= LOGIN_MAX_FAILS_LOGIN)
            || (login_fail_count("NL_LOGIN_ATTEMPT_IP", client_ip()) >= LOGIN_MAX_FAILS_IP);
    }

    function login_register_fail($login) {
        db_prepared("INSERT INTO NL_LOGIN_ATTEMPT (NL_LOGIN_ATTEMPT_LOGIN, NL_LOGIN_ATTEMPT_IP) VALUES (?, ?)", array($login, client_ip()));
        db_query("DELETE FROM NL_LOGIN_ATTEMPT WHERE NL_LOGIN_ATTEMPT_TIME < (NOW() - INTERVAL 1 DAY)"); // чистка старых записей
    }

    function login_reset($login) {
        db_prepared("DELETE FROM NL_LOGIN_ATTEMPT WHERE NL_LOGIN_ATTEMPT_LOGIN = ?", array($login));
    }

    $url = explode("?", $_SERVER["REQUEST_URI"] ?? "", 5);

    $page_array = explode("/admin/", $url[0]);
    $page = rtrim($page_array[1] ?? "", "/");
    // [SEC] $page берётся из REQUEST_URI и дальше попадает в include и в HTML-атрибуты:
    // допускаем только [A-Za-z0-9_-] (иначе path traversal в include и XSS)
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $page)) {
        $page = "";
    }

    class ObjectParam {
        public $dbName;
        public $rusName;
        public $required = false;
        public $type = "string"; // string, rich, integer, float, select, checkbox, photo, photos, file, encrypted, map
        public $maxLength = false;
        public $thisTable = true;
        public $onChange = false;
        public $onClick = false;
        public $onInit = false;
        public $onAddInit = false;
        public $onEditInit = false;
        public $formatter = false;
        public $defValue = false;
        public $editable = true;
        public $render = false;
        public $editHidden = true;
        public $width = 120;
    }

    class ObjectTable {
        public $leftJoins;
        public $colArray;
        public $dbName;
        public $where;
        public $jqGridCustom;
        public $editOnly = false;
        public $readOnly = false;

        // [SEC] Белый список таблиц: имя таблицы приходит из $_GET/$_POST и подставляется в SQL,
        // в пути файлов и в JS - через плейсхолдер его передать нельзя, поэтому только сверка со списком
        const TABLES = ["NL_VIEW", "NL_HOUSES", "NL_MATERIAL", "NL_USER", "NL_PROP_RESALE"];

        public static function isAllowedTable($tableName) {
            return is_string($tableName) && in_array($tableName, self::TABLES, true);
        }

        // [SEC-2] Матрица прав: таблица => список ID_NL_USER_PERMISSION (1 - пользователь, 2 - администратор, 3 - гость).
        // Справочники и пользователи - только администратору; журнал - всем вошедшим, запись - пользователю и администратору.
        const READ_PERMISSIONS = [
            "NL_VIEW" => [2],
            "NL_HOUSES" => [2],
            "NL_MATERIAL" => [2],
            "NL_USER" => [2],
            "NL_PROP_RESALE" => [1, 2, 3]
        ];
        const WRITE_PERMISSIONS = [
            "NL_VIEW" => [2],
            "NL_HOUSES" => [2],
            "NL_MATERIAL" => [2],
            "NL_USER" => [2],
            "NL_PROP_RESALE" => [1, 2]
        ];

        // [SEC-2] Может ли текущий пользователь читать ("read") или изменять ("write") таблицу
        public static function canAccess($tableName, $mode) {
            if (!self::isAllowedTable($tableName)) {
                return false;
            }
            $map = ($mode === "write") ? self::WRITE_PERMISSIONS : self::READ_PERMISSIONS;
            $perm = (int)($_SESSION["ID_NL_USER_PERMISSION"] ?? 0);
            //echo in_array($perm, $map[$tableName] ?? array(), true); die;
            return in_array($perm, $map[$tableName] ?? array(), true);
        }

        // [SEC-7] Единый каталог загрузки (URL) - используется и загрузчиком, и очисткой файлов
        public function getUploadDirUrl() {
            return "/img/" . strtolower(str_replace("NL_", "", $this->dbName)) . "/";
        }

        // [SEC-2] Колонка-владелец строки (есть только у журналов; у NL_USER одноимённая колонка - это первичный ключ)
        public function getOwnerColumn() {
            return (($this->dbName !== "NL_USER") && isset($this->colArray["ID_NL_USER"])) ? "ID_NL_USER" : null;
        }

        // [SEC-8] "Контакт собственника": видят только владелец записи и администратор
        public function isOwnerContactCol($col) {
            return $col->dbName === ($this->dbName . "_PHONE_OWNER");
        }

        public function canSeeOwnerContact($row) {
            return ((int)($_SESSION["ID_NL_USER_PERMISSION"] ?? 0) === 2)
                || ((int)($row["ID_NL_USER"] ?? 0) === (int)($_SESSION["ID_NL_USER"] ?? -1));
        }

        function __construct($tableName) {
            if (!self::isAllowedTable($tableName)) {
                http_response_code(400);
                exit("Bad request");
            }
            // [SEC-1] Таблица доступна на чтение не всем: без права чтения объект не создаётся вовсе,
            // поэтому ни show/table/edit/upload, ни любой другой код не получит данные закрытой таблицы
            if (!self::canAccess($tableName, "read")) {
                http_response_code(403);
                exit("Forbidden");
            }
            $this->dbName = $tableName;
            $this->colArray = $this->getTableColArray();
            $this->leftJoins = $this->getTableLeftJoins();
            $this->where = $this->getTableWhere();
            $this->jqGridCustom = $this->getjqGridCustom();
        }

        private function getMainTableCol($colName, $prefix) {
            $colObject = new ObjectParam();
            $colObject->dbName = "NL_" . $prefix . "_" . substr($colName, 3);
            switch ($colName) {
                // ОБЩИЕ ПОЛЯ
                case "ID":
                    $colObject->dbName = "ID_NL_" . $prefix;
                    $colObject->rusName = "И/н";
                    $colObject->type = "integer";
                    $colObject->render = true;
                    $colObject->editable = true;
                    $colObject->editHidden = false;
                    $colObject->defValue = 'function(){
                        var defVal = "";
                        $.ajax({
                            url: "/admin/php/set_id.php",
                            data: {
                                "table": "' . $this->dbName . '"
                            },
                            method: "POST",
                            async: false,
                            success: function (data) {
                                defVal = $.trim(data);
                            }
                        });
                        return defVal;
                    }';
                    $colObject->width = 40;
                    break;
                case "ID_NL_USER":
                    $colObject->dbName = "ID_NL_USER";
                    $colObject->rusName = "Ответственный";
                    $colObject->type = "select";
                    $colObject->defValue = (int)($_SESSION["ID_NL_USER"] ?? 0); // [SEC] только число: значение уходит в JS без кавычек
                    if ($_SESSION["ID_NL_USER_PERMISSION"] == "2") {
                        $colObject->editable = true;
                    } elseif ($this->dbName != "NL_USER") {
                        $colObject->editable = true;
                        $colObject->editHidden = false;
                    } else {
                        $colObject->editable = false;
                    }
                    $colObject->render = true;
                    break;
                case "NL_SHORT":
                    $colObject->rusName = "Кратко";
                    $colObject->required = true;
                    $colObject->type = "string";
                    $colObject->render = true;
                    $colObject->maxLength = 25;
                    break;
                case "NL_FULL":
                    $colObject->rusName = "Полное имя";
                    $colObject->required = true;
                    $colObject->type = "string";
                    $colObject->render = true;
                    $colObject->maxLength = 2550;
                    break;
                case "NL_PHONE":
                    $colObject->rusName = "Телефон";
                    $colObject->type = "string";
                    $colObject->render = true;
                    $colObject->maxLength = 50;
                    $colObject->defValue = $_SESSION["NL_USER_PHONE"] ?? ""; // [SEC-1] ключ может отсутствовать
                    break;
                case "NL_PHONE_OWNER":
                    $colObject->rusName = "Контакт собственника";
                    $colObject->type = "string";
                    $colObject->render = false;
                    $colObject->maxLength = 255;
                    break;

                // СПРАВОЧНИКОВ ПОЛЬЗОВАТЕЛЕЙ
                case "ID_NL_USER_PERMISSION":
                    $colObject->dbName = "ID_NL_USER_PERMISSION";
                    $colObject->rusName = "Права";
                    $colObject->required = true; // [SEC-10] колонка NOT NULL - обязательна и на сервере
                    $colObject->type = "select";
                    $colObject->render = true;
                    $colObject->defValue = 1;
                    $colObject->editable = true;
                    $colObject->editHidden = false;
                    break;
                case "NL_LOGIN":
                    $colObject->rusName = "Логин (на англ.)";
                    $colObject->required = true;
                    $colObject->type = "string";
                    $colObject->render = true;
                    $colObject->maxLength = 50;
                    break;
                case "NL_PASSWORD":
                    $colObject->rusName = "Пароль";
                    $colObject->required = true;
                    $colObject->type = "encrypted";
                    break;

                // СПРАВОЧНИКИ
                case "NL_PROP_IS_ACTIVE":
                    $colObject->rusName = "Объект активен?";
                    $colObject->type = "checkbox";
                    $colObject->render = true;
                    $colObject->required = true;
                    $colObject->defValue = '"Нет"';
                    break;

                // ЖУРНАЛЫ
                case "ID_NL_VIEW":
                    $colObject->dbName = "ID_NL_VIEW";
                    $colObject->rusName = "Вид из окна";
                    $colObject->type = "select";
                    $colObject->render = true;
                    break;
                case "ID_NL_HOUSES":
                    $colObject->dbName = "ID_NL_HOUSES";
                    $colObject->rusName = "Тип дома";
                    $colObject->type = "select";
                    $colObject->render = true;
                    break;
                case "ID_NL_MATERIAL":
                    $colObject->dbName = "ID_NL_MATERIAL";
                    $colObject->rusName = "Материал дома";
                    $colObject->type = "select";
                    $colObject->render = true;
                    break;
                case "NL_FLOOR":
                    $colObject->rusName = "Этаж";
                    $colObject->type = "string";
                    $colObject->render = true;
                    $colObject->maxLength = 25;
                    $colObject->width = 40;
                    break;
                case "NL_AREA_FULL":
                    $colObject->rusName = "Площадь (общая)";
                    $colObject->type = "float";
                    $colObject->render = true;
                    $colObject->required = true;
                    $colObject->width = 40;
                    break;
                case "NL_PHOTO_URLS":
                    $colObject->rusName = "Фотографии";
                    $colObject->type = "photos";
                    $colObject->render = true;
                    break;
                case "NL_COST_TOTAL":
                    $colObject->rusName = "Общая стоимость";
                    $colObject->type = "integer";
                    $colObject->render = true;
                    $colObject->width = 80;
                    break;
                case "NL_ADDRESS":
                    $colObject->rusName = "Адрес";
                    $colObject->type = "map";
                    $colObject->render = true;
                    $colObject->maxLength = 2550;
                    break;
                case "NL_DESCRIPTION":
                    $colObject->rusName = "Описание";
                    $colObject->type = "rich";
                    $colObject->render = false;
                    $colObject->required = true;
                    $colObject->maxLength = 5100;
                    break;
            }

            return $colObject;
        }

        private function getTableColArrayByColumnNames($tableName, $colNames) {
            $table = Array();

            array_push($table, $this->getMainTableCol("ID", $tableName));
            for ($i = 0; $i < count($colNames); $i++) {
                $colName = $colNames[$i];
                $colNameName = $colName;
                if (strpos($colName, "ID_") === false) {
                    $colNameName = mb_ereg_replace($tableName . "_", "", $colName);
                }
                array_push($table, $this->getMainTableCol($colNameName, $tableName));
            }

            $table["ID_" . $tableName] = $this->getMainTableCol("ID", $tableName);
            for ($i = 0; $i < count($colNames); $i++) {
                $colName = $colNames[$i];
                $colNameName = $colName;
                if (strpos($colName, "ID_") === false) {
                    $colNameName = mb_ereg_replace($tableName . "_", "", $colName);
                }
                $table[$colName] = $this->getMainTableCol($colNameName, $tableName);
            }

            return $table;
        }

        private function getTableColArray() {
            $table = Array();

            $tableName = substr($this->dbName, 3);
            switch ($this->dbName) {
                case "NL_USER":
                    $colNames = ["ID_NL_USER_PERMISSION", "NL_LOGIN", "NL_PASSWORD", "NL_SHORT", "NL_FULL", "NL_PHONE"];
                    $table = $this->getTableColArrayByColumnNames($tableName, $colNames);
                    break;
                case "NL_VIEW":
                    $colNames = ["NL_VIEW_SHORT"];
                    $table = $this->getTableColArrayByColumnNames($tableName, $colNames);
                    break;
                case "NL_HOUSES":
                    $colNames = ["NL_HOUSES_SHORT"];
                    $table = $this->getTableColArrayByColumnNames($tableName, $colNames);
                    break;
                case "NL_MATERIAL":
                    $colNames = ["NL_MATERIAL_SHORT"];
                    $table = $this->getTableColArrayByColumnNames($tableName, $colNames);
                    break;
                case "NL_PROP_RESALE":
                    $colNames = [
                        "NL_PROP_RESALE_AREA_FULL",
                        "NL_PROP_RESALE_ADDRESS",
                        "NL_PROP_RESALE_FLOOR",
                        "NL_PROP_RESALE_COST_TOTAL",
                        "NL_PROP_RESALE_PHONE_OWNER",
                        "ID_NL_VIEW",
                        "ID_NL_HOUSES",
                        "ID_NL_MATERIAL",
                        "ID_NL_USER",
                        "NL_PROP_RESALE_PHONE",
                        "NL_PROP_RESALE_PHOTO_URLS",
                        "NL_PROP_RESALE_DESCRIPTION"
                    ];
                    $table = $this->getTableColArrayByColumnNames($tableName, $colNames);
                    break;
            }

            return $table;
        }

        private function getTableLeftJoins() {
            switch ($this->dbName) {
                case "NL_USER":
                    return ["NL_USER_PERMISSION"];
                    break;
                case "NL_PROP_RESALE":
                    return ["NL_VIEW", "NL_USER", "NL_HOUSES", "NL_MATERIAL"];
                    break;
                default:
                    return [];
                    break;
            }
        }

        private function getTableWhere() {
            switch ($this->dbName) {
                case "NL_USER":
                    return "(tbl.ID_NL_USER_PERMISSION != 2) AND (2 = " . (int)($_SESSION["ID_NL_USER_PERMISSION"] ?? 0) . ")"; // [SEC] int-cast
                    break;
                case "NL_PROP_RESALE":
                    if (($_SESSION["onlymy"] ?? "") == "1") {
                        return "(tbl.ID_NL_USER = " . (int)($_SESSION["ID_NL_USER"] ?? 0) . ")"; // [SEC] int-cast
                    } else {
                        return "";
                    }
                    break;
                default:
                    return "";
                    break;
            }
        }

        public function getjqGridCustom() {
            switch ($this->dbName) {
                case "NL_PROP_RESALE":
                    $return = '
<script>
function showOnlyMy(jqgrid, row) {
    ';
                if ($_SESSION["ID_NL_USER_PERMISSION"] != "2") {
                    $return .= '
    if (row["ID_NL_USER"] != ' . js_str($_SESSION["NL_USER_SHORT"] ?? "") . ') {
        $("#edit_" + jqgrid + "_top, #del_" + jqgrid + "_top").hide();
    } else {
        $("#edit_" + jqgrid + "_top, #del_" + jqgrid + "_top").show();
    }
                    ';
                }
                    $return .= '
}
</script>
                    ';
                    return $return;
                    break;
                default:
                    return "";
                    break;
            }
        }

        public function renderTable() {
            $jqGridHtml = "";
            $jqGridHtml .= file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/admin/partial/jqgrid.html");
            if ($this->jqGridCustom) {
                $jqGridHtml .= "
                " . $this->jqGridCustom;
            }
            $colNames = '[';//""';
            $colModel = '[';//{ name: "ID_' . $this->dbName . '", index: "ID_' . $this->dbName . '", width : resizeData[pathName + "ID_' . $this->dbName . '"] ? resizeData[pathName + "ID_' . $this->dbName . '"] : 100}';
            $afterShowFormAdd = 'afterShowForm : function(formid) {
                initChosen($(formid.selector + " select:visible"));

                var $quill_elem = $(formid.selector + " .g-quill");
                if ($quill_elem.length > 0) {
                    
                    var toolbarOptions = [
                        ["bold", "italic", "underline", "strike"],        // toggled buttons
                        [{ "list": "ordered"}, { "list": "bullet" }],
                        [{ "script": "sub"}, { "script": "super" }],      // superscript/subscript
                        [{ "indent": "-1"}, { "indent": "+1" }],          // outdent/indent
                        [{ "header": [1, 2, 3, 4, 5, 6, false] }],
                        ["link", "image"],
                        [{ "color": [] }, { "background": [] }],          // dropdown with defaults from theme
                        [{ "align": [] }]
                    ];

                
                    var quill = new Quill($quill_elem[0], {
                        modules: {
                            toolbar: toolbarOptions
                        },
                        theme: "snow"
                    });
                
                    // [SEC-6] значение читаем как атрибут и разбираем безопасной quillParseValue() (js.combined.js):
                    // битый или подставленный JSON больше не вызывает исключение и не ломает форму
                    var quillEncoded = $quill_elem.attr("data-value");
                    if (quillEncoded) {
                        quill.setContents(quillParseValue(quillEncoded));
                    }
                
                    $quill_elem.data("quill", quill);
                }

                modalResize();';

            $afterShowFormEdit = $afterShowFormAdd;

            switch ($this->dbName) {
                case "NL_PROP_RESALE":
                    $afterShowFormEdit .= '
                        if ($(formid.selector + " #ID_NL_USER").val() == ' . (int)($_SESSION["ID_NL_USER"] ?? 0) . ') {
                            $(formid.selector + " #tr_' . $this->dbName . '_PHONE_OWNER").css("display", "");
                        } else {
                            $(formid.selector + " #tr_' . $this->dbName . '_PHONE_OWNER").css("display", "none");
                        }
                    ';
                    break;
            }

            for ($i = 0; $i < (count($this->colArray) / 2); $i++) {
                /* @var $col ObjectParam */
                $col = $this->colArray[$i];
                if ($i > 0) {
                    $colNames .= ',';
                    $colModel .= ',';
                }
                $colNames .= '"' . $col->rusName . '"';
                // colModel - first params
                $colWidth = 120;
                if ($col->width) {
                    $colWidth = $col->width;
                }
                $colModel .= '{ name: "' . $col->dbName . '", index: "' . $col->dbName . '", width : resizeData[pathName + "' . $col->dbName . '"] ? resizeData[pathName + "' . $col->dbName . '"] : ' . $colWidth;
                // colModel - hidden
                if (!$col->render) {
                    $colModel .= ', hidden : true';
                }
                // colModel - editable
                if ($col->editable) {
                    $colModel .= ', editable : true';
                    // colModel - edittype
                    if ($col->type == "string") {
                        if ($col->maxLength > 50) {
                            $colModel .= ', edittype : "textarea"';
                        }
                    } elseif ($col->type == "select") {
                        $colModel .= ', edittype : "select"';
                    } elseif ($col->type == "checkbox") {
                        $colModel .= ', edittype : "checkbox"';
                    } elseif (($col->type == "file") || ($col->type == "map")) {
                        $colModel .= ', edittype : "text"';
                    } elseif (($col->type == "photo") || ($col->type == "photos")) {
                        $colModel .= ', edittype : "text", formatter: photosFormatter ';
                    } elseif ($col->type == "encrypted") {
                        $colModel .= ', edittype : "password"';
                    } elseif ($col->type == "rich") {
                        $colModel .= ', edittype : "custom"';
                    } elseif ($col->type == "float") {
                        $colModel .= ", formatter: numberFormatter ";
                    }
                    if (($col->formatter !== false) && ($col->type != "photo") && ($col->type != "photos")) {
                        $colModel .= ', formatter: function(cellValue, options, rowObject) {
                        ' . $col->formatter . '
                        }';
                    }
                    // colModel - edit options
                    $colModel .= ", editoptions : {";
                    $colModelEditOptions = "";
                    $dataInit = "dataInit: function(el) {";
                    if ($col->editHidden == false) {
                        $dataInit .= '
                            $(el).parent().parent().addClass("g-hidden");
                        ';
                    }
                    if ($col->type == "map") {
                        $dataInit .= '
                            $(el).addClass("g-mapInput");
                            $(el).parent().append("<span class=\'g-mapShowHide g-link\'>Показать/скрыть карту</span>");
                            $(el).next().click(function() {
                                $(this).next().slideToggle();
                            });
                            ymaps.ready(function () {
                                var idYMaps = el.id + "_ymaps";
                                $(el).parent().append("<div id=\'" + idYMaps + "\' class=\'g-mapYandex\'></div>");
                                var myMap = new ymaps.Map(idYMaps, {
                                    center: [44.891026, 37.32193],
                                    zoom: 14,
                                    controls: ["searchControl", "typeSelector", "fullscreenControl", "zoomControl"]
                                });
                                
                                var searchControl = myMap.controls.get("searchControl");
                                searchControl.events.add("change", function () {
                                    $(el).val(searchControl.getRequestString());
                                    //console.log("request: " + searchControl.getRequestString());
                                }, this);
                                
                                $(el).change(function() {
                                    if ($(el).val()) {
                                        searchControl.search($(el).val()).then(function () { searchControl.showResult(0); });
                                    }
                                });
                                
                                searchControl.options.set("fitMaxWidth", true).set("float", "left");
                                
                                if ($(el).val()) {
                                    searchControl.search($(el).val()).then(function () { searchControl.showResult(0); });
                                }
                                
                                $(".g-mapYandex").hide();
                            });
                        ';
                    } elseif ($col->type == "date") {
                        $dataInit .= '
                            $(el).datetimepicker({format: "Y-m-d",timepicker: false});
                        ';
                    } elseif ($col->type == "encrypted") {
                        $dataInit .= '
                            var rndBtn = $("<button type=\"button\" class=\"g-btn\">случайный</button>");
                            rndBtn.click(function() {
                                // [SEC-3] криптостойкий генератор вместо Math.random(), 12 символов без похожих (0/O, 1/l/I)
                                var alphabet = "abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789";
                                var rnd = new Uint32Array(12);
                                window.crypto.getRandomValues(rnd);
                                var pass = "";
                                for (var i = 0; i < rnd.length; i++) {
                                    pass += alphabet.charAt(rnd[i] % alphabet.length);
                                }
                                $(el).attr("type", "text").val(pass);
                            });
                            $(el).after(rndBtn);
                        ';
                    } elseif ($col->type == "select") {
                        if ($colModelEditOptions != "") {
                            $colModelEditOptions .= ", ";
                        }
                        $selectOptions = ""; // [SEC] собираем значения отдельно и выводим как JS-литерал через js_str()

                        $selectOptions .= ':не выбрано';

                        $selectTableName = str_replace("_PARENT", "", str_replace("ID_", "", $col->dbName));
                        $query = "SELECT * FROM " . db_ident($selectTableName); // [SEC] идентификатор только [A-Za-z0-9_]
                        $res = db_query($query) or die(db_error($query));
                        if (db_num_rows($res) > 0) {
                            while ($row = db_fetch_assoc($res)) {
                                // [SEC-6] подпись попадает в HTML <option> на клиенте, а список собирается в строку "id:текст;id:текст".
                                // Раньше значение шло как есть (stored XSS через справочник; ";" и ":" ломали формат).
                                // Теперь из подписи убираются символы < > & " и ; : (в т.ч. мешающие разбору), id приводится к int.
                                $selectOptions .= ";" . (int)$row[str_replace("_PARENT", "", $col->dbName)] . ":" . trim(preg_replace('/[<>&"\';:\s]+/u', " ", (string)$row[$selectTableName . "_SHORT"]));
                            }
                        }
                        $colModelEditOptions .= 'value : ' . js_str($selectOptions);

                    } elseif ($col->type == "checkbox") {
                        if ($colModelEditOptions != "") {
                            $colModelEditOptions .= ", ";
                        }
                        $colModelEditOptions .= 'value : "Да:Нет"';
                    } elseif (($col->type == "photo") || ($col->type == "photos") || ($col->type == "file")) {
                        /*if ($colModelEditOptions != "") {
                            $colModelEditOptions .= ", ";
                        }
                        $colModelEditOptions .= 'src: "/img/empty.png?1", dataInit: function(el) {
                            var parent = $(el).parent();
                            var rowid = $(el).attr("rowid");
                            var name = $(el).attr("name);
                            $("el").remove();
                            parent.append(\'<input type="file" id="\' + name + \'" name="\' + name + \'" rowid="\' + rowid + \'" class="FormElement">\');
                        }';*/

                        $multiple = "false";
                        if ($col->type == "photos") {
                            $multiple = "true";
                        }

                        $only_photo = "true";
                        if ($col->type == "file") {
                            $only_photo = "false";
                        }

                        /*$colModelEditOptions .= 'defaultValue: "[\"\/img\/empty.png\"]", 'dataInit: function(el){
                            dataInitFileFunction(el, "' . $this->dbName . '", "' . $col->dbName . '", ' . $multiple . ');
                        }';*/

                        $dataInit .= '
                            // [SEC-6] раньше $.parseHTML($(el).val()) разбирал значение ячейки как HTML (DOM XSS).
                            // extractPhotosValue() (js.combined.js) читает его через DOMParser в "мёртвом" документе
                            $(el).val(extractPhotosValue($(el).val()));
                            dataInitFileFunction(el, "' . $this->dbName . '", "' . $col->dbName . '", ' . $multiple . ', ' . $only_photo . ');
                        ';
                    } elseif ($col->type == "rich") {
                        if ($colModelEditOptions != "") {
                            $colModelEditOptions .= ", ";
                        }
                        $colModelEditOptions .= 'custom_element : get_quill_elem, custom_value : get_quill_value';
                    }
                    if ($col->defValue != false) {
                        if ($colModelEditOptions != "") {
                            $colModelEditOptions .= ", ";
                        }
                        if ($col->type == "string") {
                            $colModelEditOptions .= 'defaultValue : ' . js_str($col->defValue); // [SEC] JS-литерал вместо ручных кавычек
                        } else {
                            $colModelEditOptions .= 'defaultValue : ' . $col->defValue;
                        }
                    }

                    $dataInit .= " }";
                    if ($colModelEditOptions != "") {
                        $colModelEditOptions .= ", ";
                    }
                    $colModelEditOptions .= $dataInit;

                    $colModel .= $colModelEditOptions . "}";
                    // colModel - edit rules
                    $colModel .= ", editrules : {";
                    $colModelEditRules = "";
                    if ($col->required && ($col->type != "encrypted")) { // [SEC-3] пароль: required проверяется на сервере только при add
                        if ($colModelEditRules != "") {
                            $colModelEditRules .= ", ";
                        }
                        $colModelEditRules .= "required: true";
                    }
                    if (!$col->render) {
                        if ($colModelEditRules != "") {
                            $colModelEditRules .= ", ";
                        }
                        $colModelEditRules .= "edithidden: true";
                    }
                    if ($col->type == "float") {
                        if ($colModelEditRules != "") {
                            $colModelEditRules .= ", ";
                        }
                        $colModelEditRules .= "number: true";
                    } elseif ($col->type == "integer") {
                        if ($colModelEditRules != "") {
                            $colModelEditRules .= ", ";
                        }
                        $colModelEditRules .= "integer: true";
                    }
                    $colModel .= $colModelEditRules . "}";
                }
                // colModel - form options
                $colModel .= ", formoptions : {";
                $colModel .= 'label: "' . $col->rusName . '"';
                if ($col->required && ($col->type != "encrypted")) {
                    $colModel .= ', elmsuffix: "(*)"';
                }
                $colModel .= "}";

                $colModel .= "}";

                if (($col->onInit !== false) || ($col->onChange !== false)) {
                    $asf = '
                        (function(){
                            var el = "#' . $col->dbName . '";
                    ';
                    $afterShowFormAdd .= $asf;
                    $afterShowFormEdit .= $asf;
                }
                if ($col->onInit !== false) {
                    $asf = '
                            ' . $col->onInit . '
                        ';
                    $afterShowFormAdd .= $asf;
                    $afterShowFormEdit .= $asf;
                }
                if ($col->onChange !== false) {
                    $asf = '
                            $(el).bind("change", function(){' . $col->onChange . ';});
                        ';
                    $afterShowFormAdd .= $asf;
                    $afterShowFormEdit .= $asf;
                }
                if (($col->onInit !== false) || ($col->onChange !== false)) {
                    $asf = '
                        }());
                    ';
                    $afterShowFormAdd .= $asf;
                    $afterShowFormEdit .= $asf;
                }
                if ($col->onAddInit !== false) {
                    $asf = '
                        (function(){
                            var el = "#' . $col->dbName . '";
                    ';
                    $afterShowFormAdd .= $asf;
                    $asf = '
                            ' . $col->onAddInit . '
                        ';
                    $afterShowFormAdd .= $asf;
                    $asf = '
                        }());
                    ';
                    $afterShowFormAdd .= $asf;
                }
                if ($col->onEditInit !== false) {
                    $asf = '
                        (function(){
                            var el = "#' . $col->dbName . '";
                    ';
                    $afterShowFormEdit .= $asf;
                    $asf = '
                            ' . $col->onEditInit . '
                        ';
                    $afterShowFormEdit .= $asf;
                    $asf = '
                        }());
                    ';
                    $afterShowFormEdit .= $asf;
                }
            }
            $colNames .= "]";
            $colModel .= "]";
            $jqGridHtml = str_replace("{tableName}", $this->dbName, $jqGridHtml);
            $jqGridHtml = str_replace("{colNames}", $colNames, $jqGridHtml);
            $jqGridHtml = str_replace("{colModel}", $colModel, $jqGridHtml);

            $addDelForm = "";
            if ($this->readOnly) {
                $addDelForm = 'add : false, edit : false, del : false';
            } elseif (($this->editOnly) || ($_SESSION["ID_NL_USER_PERMISSION"] == "3")) {
                $addDelForm = 'add : false, edit : true, edittext : "Изменить", del : false';
            } else {
                $addDelForm = 'add : true, addtext : "Добавить", edit : true, edittext : "Изменить", del : true, deltext : "Удалить"';
            }
            $jqGridHtml = str_replace("{addEditForm}", $addDelForm, $jqGridHtml);

            $asf = '
            }';
            $afterShowFormAdd .= $asf;
            $afterShowFormEdit .= $asf;
            $jqGridHtml = str_replace("{afterShowFormAdd}", $afterShowFormAdd, $jqGridHtml);
            $jqGridHtml = str_replace("{afterShowFormEdit}", $afterShowFormEdit, $jqGridHtml);

            echo $jqGridHtml;
        }

        // [SEC] Безопасный ORDER BY: колонка - только из белого списка колонок таблицы, направление - ASC/DESC
        private function getSafeOrderBy($sidx, $sord) {
            $column = "ID_" . $this->dbName; // по умолчанию
            if (is_string($sidx)) {
                foreach ($this->getOwnColumns() as $col) {
                    if (($col->dbName === $sidx) && ($col->type != "encrypted") && !$this->isRestrictedCol($col)) { // [SEC-8] нельзя сортировать по скрытой колонке (утечка порядка)
                        $column = $col->dbName;
                        break;
                    }
                }
            }
            $direction = (strtoupper((string)$sord) === "DESC") ? "DESC" : "ASC";
            return "tbl." . db_ident($column) . " " . $direction;
        }

        // [SEC-8] Колонка, закрытая для текущего пользователя целиком (поиск/сортировка): контакт собственника не-администратору
        private function isRestrictedCol($col) {
            return $this->isOwnerContactCol($col) && ((int)($_SESSION["ID_NL_USER_PERMISSION"] ?? 0) !== 2);
        }

        // Колонки самой таблицы (числовые ключи $this->colArray; строковые ключи - дубли)
        private function getOwnColumns() {
            $cols = Array();
            for ($i = 0; $i < (count($this->colArray) / 2); $i++) {
                $cols[] = $this->colArray[$i];
            }
            return $cols;
        }

        public function getData($page = 0, $limit = 0, $sidx = "1", $sord = "ASC", $search_where = "(1 = 1)", $search_params = array()) {
            $data = Array();

            $leftJoin = $this->get_query_left_joins();

            // [SEC] Все значения - через плейсхолдеры (?), идентификаторы - через db_ident()
            $params = Array();
            $query = "SELECT *";
            // [SEC-3] УДАЛЕНО: AES_DECRYPT(пароль, AESKEY) - пароли в открытом виде уходили в грид всем, кто его читал.
            // Пароль теперь хранится как password_hash() и клиенту не отдаётся ни в каком виде.
            $query .= " FROM " . db_ident($this->dbName) . " tbl " . $leftJoin . " WHERE " . $search_where;
            $params = array_merge($params, $search_params);
            if (isset($this->where) && ($this->where != false) && (trim($this->where) != "")) {
                $query .= " AND " . $this->where;
            }
            $query .= " ORDER BY " . $this->getSafeOrderBy($sidx, $sord);
            $query .= " LIMIT ?, ?";
            $params[] = max(0, (int)$limit * ((int)$page - 1));
            $params[] = max(0, (int)$limit);

            $res = db_prepared($query, $params) or die(db_error($query));
            $i = 0;
            if (db_num_rows($res) > 0) {
                //echo print_r($this->colArray);
                while ($row = db_fetch_assoc($res)) {
                    $data[$i] = Array();
                    //array_push($data[$i], $row["ID_" . $this->dbName]);
                    //array_push($data[$i], ($i + 1) + (($page * $limit) - $limit));
                    for ($j = 0; $j <= (count($this->colArray) / 2) - 1; $j++) {
                        $col = $this->colArray[$j];
                        $rowName = $this->colArray[$j]->dbName;
                        if ($col->type == "select") {
                            $rowName = mb_ereg_replace("ID_", "", $rowName) . "_SHORT";
                        }
                        $value = $row[$rowName] ?? "";
                        // [SEC-3] пароль (хеш) в ответ не попадает - вместо него пустая строка
                        if ($col->type == "encrypted") {
                            $value = "";
                        }
                        // [SEC-8] "Контакт собственника" отдаётся только владельцу записи и администратору.
                        // Раньше колонка с render=false лишь скрывалась в гриде, а значения приходили в XML всем
                        if ($this->isOwnerContactCol($col) && !$this->canSeeOwnerContact($row)) {
                            $value = "";
                        }
                        array_push($data[$i], $value);
                    }
                    $i++;
                }
            }

            return $data;
        }

        public function showData() {
            // [SEC] page/rows - только целые числа в допустимых границах (раньше шли в LIMIT и в XML как есть)
            $page = (int)($_GET['page'] ?? 1);
            if ($page < 1) {
                $page = 1;
            }
            $limit = (int)($_GET['rows'] ?? 50);
            if ($limit < 1) {
                $limit = 50;
            }
            if ($limit > 1000) {
                $limit = 1000;
            }
            // [SEC] Поле и направление сортировки проверяются по белому списку в getSafeOrderBy()
            $sidx = is_string($_GET['sidx'] ?? null) ? $_GET['sidx'] : "";
            $sord = is_string($_GET['sord'] ?? null) ? $_GET['sord'] : "ASC";
            // [SEC] Условия поиска строятся только по известным колонкам, значения - через плейсхолдеры
            list($search_where, $search_params) = $this->buildSearchWhere($_GET);

            // Выполним запрос, который вернет суммарное кол-во записей в таблице
            $leftJoin = $this->get_query_left_joins();
            $query = "SELECT COUNT(*) AS COUNT FROM " . db_ident($this->dbName) . " tbl $leftJoin WHERE $search_where";
            // [SEC] счётчик должен учитывать те же ограничения доступа, что и выборка данных
            if (isset($this->where) && ($this->where != false) && (trim($this->where) != "")) {
                $query .= " AND " . $this->where;
            }
            $res = db_prepared($query, $search_params) or die(db_error($query));
            $row = db_fetch_assoc($res);
            // Теперь эта переменная хранит кол-во записей в таблице
            $count = (int)$row['COUNT'];
            // Рассчитаем сколько всего страниц займут данные в БД
            if ($count > 0 && $limit > 0) {
                $total_pages = ceil($count / $limit);
            } else {
                $total_pages = 0;
            }
            // Если по каким-то причинам клиент запросил
            if (($page > $total_pages) && ($total_pages != 0))
                $page = $total_pages;
            // Рассчитываем стартовое значение для LIMIT запроса
            $start = $limit * $page - $limit;
            // Зашита от отрицательного значения
            if ($start < 0)
                $start = 0;
            // Запрос выборки данных
            $data = $this->getData($page, $limit, $sidx, $sord, $search_where, $search_params);

            // Начало xml разметки
            $s = "<?xml version='1.0' encoding='utf-8'?>";
            $s .= "<rows>";
            $s .= "<page>" . $page . "</page>";
            $s .= "<total>" . $total_pages . "</total>";
            $s .= "<records>" . $count . "</records>";
            // Строки данных для таблицы
            // Не забудьте обернуть текстовые данные в <![CDATA[]]>
            if (count($data) > 0) {
                for ($i = 0; $i < count($data); $i++) {
                    $s .= '<row id="' . $data[$i][0] . '">'; // [SEC] экранирование атрибута XML
                    for ($j = 0; $j < count($data[$i]); $j++) {
                        $s .= '<cell><![CDATA[' . str_replace("]]>", "]]]]><![CDATA[>", (string)$data[$i][$j]) . ']]></cell>'; // [SEC] нельзя выйти из CDATA
                    }
                    $s .= "</row>";
                }
            }
            $s .= "</rows>";
            // Перед выводом не забывайте выставить header с типом контента и кодировкой
            header("Content-type: text/xml; charset=utf-8");

            return $s;
        }

        // [SEC] Построение условий поиска из $_GET без подстановки значений в текст запроса.
        // Возвращает array(строка WHERE, массив параметров).
        private function buildSearchWhere($source) {
            $where = "(1=1)";
            $params = Array();

            // допустимые для поиска колонки (пароль - нельзя)
            $columns = Array();
            foreach ($this->getOwnColumns() as $col) {
                // [SEC-8] по скрытому "Контакту собственника" искать нельзя: LIKE-поиск позволял подбирать чужие номера
                if (($col->type != "encrypted") && !$this->isRestrictedCol($col)) {
                    $columns[$col->dbName] = $col;
                }
            }

            foreach ($source as $key => $value) {
                if (!is_string($key) || !is_string($value) || ($value === "") || (strpos($key, "NL_") === false)) {
                    continue;
                }

                $operator = "=";
                $baseKey = $key;
                if (preg_match('/^(.+)_(from|to)$/', $key, $m)) {
                    $baseKey = $m[1];
                    $operator = ($m[2] == "from") ? ">=" : "<=";
                }
                // неизвестные параметры игнорируем (раньше любое имя из $_GET попадало в SQL)
                if (!isset($columns[$baseKey])) {
                    continue;
                }
                $col = $columns[$baseKey];

                if ($col->type == "select") {
                    $field = db_ident(substr($col->dbName, 3) . "_SHORT"); // колонка присоединённого справочника
                } else {
                    $field = "tbl." . db_ident($col->dbName);
                }

                if (($col->type == "integer") || ($col->type == "float")) {
                    if (!is_numeric($value)) {
                        $where .= " AND (1=0)"; // нечисловое значение для числовой колонки - ничего не найдено
                        continue;
                    }
                    $params[] = trim($value);
                    $where .= " AND ($field $operator ?)";
                } elseif ($operator == "=") {
                    $params[] = db_like($value);
                    $where .= " AND ($field LIKE ?)";
                } else {
                    $params[] = $value;
                    $where .= " AND ($field $operator ?)";
                }
            }

            return array($where, $params);
        }

        // [SEC] Проверка типа значения из $_POST и подготовка фрагмента SQL + параметров.
        // Возвращает array(фрагмент SQL, массив параметров) или false, если значение не подходит колонке.
        private function prepareValue($col, $raw) {
            if (($raw === null) || (trim($raw) === "")) {
                return array("?", array(null));
            }
            switch ($col->type) {
                case "integer":
                case "select":
                    if (!preg_match('/^\s*-?\d{1,18}\s*$/', $raw)) {
                        return false;
                    }
                    return array("?", array((int)$raw));
                case "float":
                    if (!is_numeric($raw)) {
                        return false;
                    }
                    return array("?", array(trim($raw)));
                case "encrypted":
                    // [SEC-3] необратимый хеш (bcrypt) вместо AES_ENCRYPT с ключом из исходников
                    return array("?", array(password_hash($raw, PASSWORD_DEFAULT)));
                default:
                    return array("?", array($raw));
            }
        }

        public function saveData($oper, $id, $post) {
            // "add" - insert, "edit" - update, "del" - delete

            // [SEC] Операция - только из белого списка (раньше шла в лог и определяла запрос)
            if (!in_array($oper, array("add", "edit", "del"), true) || !is_array($post)) {
                http_response_code(400);
                die();
            }

            // [SEC-2] Право записи в таблицу проверяется на сервере: раньше любой вошедший пользователь
            // мог выполнить add/edit/del для любой таблицы из белого списка, в т.ч. для NL_USER
            if (!self::canAccess($this->dbName, "write")) {
                http_response_code(403);
                die("Forbidden");
            }
            $perm = (int)($_SESSION["ID_NL_USER_PERMISSION"] ?? 0);
            $myId = (int)($_SESSION["ID_NL_USER"] ?? 0);
            $ownerCol = $this->getOwnerColumn();

            // [SEC] id записи - только положительное целое (раньше подставлялся в SQL как есть)
            $id = filter_var($id, FILTER_VALIDATE_INT, array("options" => array("min_range" => 1)));
            if (($id === false) && ($oper != "add")) {
                http_response_code(400);
                die();
            }

            if ($this->readOnly) {
                die();
            }

            // Если пользователь может только редактировать запись
            if ((($this->editOnly) || ($perm == 3)) && (($oper == "add") || ($oper == "del"))) {
                die();
            }

            $res_cur = null;
            $row_cur = null;
            if ($oper != "add") {
                $query_cur = "SELECT * FROM " . db_ident($this->dbName) . " WHERE " . db_ident("ID_" . $this->dbName) . " = ?";
                $res_cur = db_prepared($query_cur, array($id)) or die(db_error($query_cur));
                $row_cur = db_fetch_assoc($res_cur);

                // [SEC-2] записи нет - менять и удалять нечего (раньше для администратора шёл "пустой" UPDATE/DELETE)
                if (!is_array($row_cur)) {
                    http_response_code(404);
                    die("Not found");
                }

                if ($perm == 3) {
                    die();
                }
                // [SEC-2] Владелец записи сравнивается с ТЕКУЩИМ пользователем по колонке-владельцу таблицы.
                // Раньше для NL_USER сравнивалась строка пользователя саму с собой (ID_NL_USER == ID_NL_USER),
                // и проверка не защищала ничего; теперь таблица пользователей закрыта правом записи (только администратор),
                // а для журналов обычный пользователь меняет/удаляет только свои записи.
                if (($perm != 2) && ($ownerCol !== null) && ((int)$row_cur[$ownerCol] !== $myId)) {
                    http_response_code(403);
                    die("Forbidden");
                }
                // [SEC-2] Учётные записи администраторов скрыты в гриде (getTableWhere) - прямым POST их менять/удалять тоже нельзя
                if (($this->dbName == "NL_USER") && ((int)$row_cur["ID_NL_USER_PERMISSION"] === 2)) {
                    http_response_code(403);
                    die("Forbidden");
                }
            }

            // [SEC-2] Mass assignment: ответственный (ID_NL_USER) для не-администратора назначается СЕРВЕРОМ,
            // значение из формы игнорируется (раньше можно было переназначить запись на другого пользователя)
            if (($ownerCol !== null) && ($oper != "del") && ($perm != 2)) {
                $post[$ownerCol] = ($oper == "add") ? (string)$myId : (string)$row_cur[$ownerCol];
            }

            $user_ip = 'unknown';
            if (getenv('REMOTE_ADDR')) {
                $user_ip = getenv('REMOTE_ADDR');
            } elseif (getenv('HTTP_FORWARDED_FOR')) {
                $user_ip = getenv('HTTP_FORWARDED_FOR');
            } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
                $user_ip = getenv('HTTP_X_FORWARDED_FOR');
            } elseif (getenv('HTTP_X_COMING_FROM')) {
                $user_ip = getenv('HTTP_X_COMING_FROM');
            } elseif (getenv('HTTP_VIA')) {
                $user_ip = getenv('HTTP_VIA');
            } elseif (getenv('HTTP_XROXY_CONNECTION')) {
                $user_ip = getenv('HTTP_XROXY_CONNECTION');
            } elseif (getenv('HTTP_CLIENT_IP')) {
                $user_ip = getenv('HTTP_CLIENT_IP');
            } else {
                $user_ip = 'unknown';
            }
            if (15 < strlen($user_ip)) {
                $ar = explode(', ', $user_ip); // [SEC] split() удалена в PHP 7 - вызывала фатальную ошибку
                for ($i = sizeof($ar) - 1; $i > 0; $i--) {
                    if ($ar[$i] != '' and !preg_match('/[a-zA-Zа-яА-Я]/', $ar[$i])) {
                        $user_ip = $ar[$i];
                        break;
                    }
                    if ($i == sizeof($ar) - 1) {
                        $user_ip = 'unknown';
                    }
                }
            }
            if (preg_match('/[a-zA-Zа-яА-Я]/', $user_ip)) {
                $user_ip = 'unknown';
            }
            // [SEC] IP (в т.ч. из заголовков X-Forwarded-For и др.) пишем в лог только если это валидный IP
            if (filter_var($user_ip, FILTER_VALIDATE_IP) === false) {
                $user_ip = 'unknown';
            }

            // [SEC] Проверяем и подготавливаем значения ВСЕХ полей до записи в лог и в БД
            $prepared = Array();
            foreach ($this->getOwnColumns() as $col) {
                if (($col->editable) && ($col->thisTable)) {
                    if ($oper == "del") {
                        $prepared[$col->dbName] = array("?", array(null)); // при удалении значения полей не используются
                        continue;
                    }
                    // [SEC-9] Первичный ключ никогда не пишем из формы: при add id назначает СУБД (AUTO_INCREMENT),
                    // при edit ключ менять нельзя
                    if ($col->dbName === ("ID_" . $this->dbName)) {
                        continue;
                    }
                    $raw = $post[$col->dbName] ?? null;
                    /*if (($raw !== null) && !is_string($raw)) {
                        http_response_code(400);
                        die("Некорректное значение поля «" . $col->rusName . "»");
                    }*/
                    // [SEC-10] Серверная валидация: required, maxLength, тип, диапазон, допустимые значения
                    $err = $this->validateValue($col, $raw, $oper, $row_cur);
                    if ($err !== null) {
                        http_response_code(400);
                        die("Некорректное значение поля «" . $col->rusName . "»: " . $err);
                    }
                    // [SEC-3] пустой пароль при редактировании означает "не менять" (клиенту хеш никогда не отдаётся)
                    if (($col->type == "encrypted") && (trim((string)$raw) === "")) {
                        continue;
                    }
                    $p = $this->prepareValue($col, $raw);
                    if ($p === false) {
                        http_response_code(400);
                        die("Некорректное значение поля «" . $col->rusName . "»");
                    }
                    $prepared[$col->dbName] = $p;
                }
            }

            // log master
            $query_log = "INSERT INTO NL_LOG(NL_LOG_DATE, NL_LOG_TIME, NL_LOG_IP, NL_LOG_IUD, NL_LOG_TABLE_NAME, ID_NL_USER) VALUES(?, ?, ?, ?, ?, ?)";
            db_prepared($query_log, array(date("Y.m.d"), date("H:i:s"), $user_ip, $oper, $this->dbName, $myId)) or die(db_error($query_log));
            $query_log = "SELECT LAST_INSERT_ID() AS ID_LOG";
            $res_log = db_query($query_log) or die(db_error($query_log));
            $row_log = db_fetch_assoc($res_log);

            $fields = Array();
            $placeholders = Array();
            $params = Array();
            foreach ($this->getOwnColumns() as $col) {
                /* @var $col ObjectParam */

                if (($col->editable) && ($col->thisTable)) {
                    $isPk = ($col->dbName === ("ID_" . $this->dbName));
                    // [SEC-9] ключ и неизменяемые (пустой пароль) поля в INSERT/UPDATE не попадают
                    if (!$isPk && isset($prepared[$col->dbName])) {
                        $fields[] = $col->dbName;
                        $placeholders[] = $prepared[$col->dbName][0];
                        $params = array_merge($params, $prepared[$col->dbName][1]);
                    }

                    if ($col->type != "encrypted") {
                        // [SEC] старые значения из БД и новые из $_POST пишем в лог через плейсхолдеры
                        // (раньше - конкатенацией: SQL-инъекция, в т.ч. второго порядка)
                        $valold = "";
                        if ($row_cur !== null) {
                            $valold = (string)($row_cur[$col->dbName] ?? "");
                        }
                        $valnew = "";
                        if ($oper != "del") {
                            // для ключа в лог идёт реальный id записи, а не значение из формы
                            $valnew = $isPk ? (($oper == "edit") ? (string)$id : "") : (string)($post[$col->dbName] ?? "");
                        }

                        $query_log_detail = "INSERT INTO NL_LOG_DETAIL(ID_NL_LOG, NL_LOG_DETAIL_OLD, NL_LOG_DETAIL_NEW, NL_LOG_DETAIL_FIELD) VALUES (?, ?, ?, ?)";
                        db_prepared($query_log_detail, array((int)$row_log["ID_LOG"], $valold, $valnew, $col->dbName)) or die(db_error($query_log_detail));
                    }
                }
            }

            // [SEC-7] Удаляем лишние изображения: файлы, которые были в записи и пропали из неё (или запись удалена).
            // Раньше: маска glob() по id в каталоге /img/objects/<TABLE>/, куда загрузчик ничего не писал
            // (каталоги не совпадали - файлы не удалялись никогда). Теперь каталог общий (getUploadDirUrl()),
            // имя файла проверяется по строгому шаблону, удаление выполняется ПОСЛЕ успешной записи в БД.
            $filesToDelete = Array();
            foreach ($this->getOwnColumns() as $col) {
                if (($col->type == "photo") || ($col->type == "photos")) {
                    $oldList = ($row_cur !== null) ? $this->decodeUrlList($row_cur[$col->dbName] ?? "") : array();
                    $newList = ($oper == "del") ? array() : $this->decodeUrlList($post[$col->dbName] ?? "");
                    foreach ($oldList as $oldUrl) {
                        $oldPath = $this->urlToPath($oldUrl);
                        if (($oldPath !== null) && !in_array($oldUrl, $newList, true) && is_file($oldPath)) {
                            $filesToDelete[] = $oldPath;
                        }
                    }
                }
            }
            //
            // [SEC] Итоговый запрос: имена - через db_ident(), значения - плейсхолдеры, id - int
            $query = "";
            $query_params = Array();
            $tbl = db_ident($this->dbName);
            $tblId = db_ident("ID_" . $this->dbName);
            if ($oper == "add") {
                $query = "INSERT INTO " . $tbl . " (" . implode(",", array_map("db_ident", $fields)) . ") VALUES(" . implode(",", $placeholders) . ")";
                $query_params = $params;
            } elseif ($oper == "edit") {
                $set = Array();
                for ($i = 0; $i < count($fields); $i++) {
                    $set[] = db_ident($fields[$i]) . " = " . $placeholders[$i];
                }

                $query = "UPDATE " . $tbl . " SET " . implode(",", $set) . " WHERE " . $tblId . " = ?";
                $query_params = array_merge($params, array($id));
            } elseif ($oper == "del") {
                $query = "DELETE FROM " . $tbl . " WHERE " . $tblId . " = ?";
                $query_params = array($id);
            }

            // [SEC] убрано "echo $query;" - клиенту отдавался текст SQL-запроса
            db_prepared($query, $query_params) or die(db_error($query));

            // [SEC-7] запись прошла - теперь можно удалять ненужные файлы
            foreach ($filesToDelete as $delPath) {
                @unlink($delPath);
            }
        }

        // [SEC-7] Строгий шаблон URL загруженного файла этой таблицы: /img/<таблица>/<имя>.<расширение>
        // (ни "/", ни "..", ни схем javascript:/data: - значение потом попадает в img.src и window.open на клиенте)
        private function isValidUploadUrl($url) {
            return is_string($url)
                && (preg_match('#^' . preg_quote($this->getUploadDirUrl(), '#') . '[A-Za-z0-9_.\-]+\.(jpg|jpeg|png|gif|webp|pdf|doc|docx|xls|xlsx)$#', $url) === 1);
        }

        // Абсолютный путь файла по URL или null, если URL не соответствует шаблону
        private function urlToPath($url) {
            return $this->isValidUploadUrl($url) ? ($_SERVER["DOCUMENT_ROOT"] . $url) : null;
        }

        // JSON-список URL из поля БД/формы -> массив строк (мусор -> пустой массив)
        private function decodeUrlList($value) {
            $list = json_decode((string)$value, true);
            return is_array($list) ? array_values(array_filter($list, "is_string")) : array();
        }

        // [SEC-10] Серверная валидация значения колонки. Возвращает текст ошибки или null, если значение допустимо.
        // Раньше required/maxLength/тип проверял только JS jqGrid - прямой POST обходил все ограничения.
        private function validateValue($col, $raw, $oper, $row_cur) {
            $isEmpty = (($raw === null) || (trim($raw) === ""));

            if ($col->type == "encrypted") {
                if ($isEmpty) {
                    return ($oper == "add") ? "пароль обязателен" : null; // при edit пусто = не менять
                }
                // bcrypt учитывает только первые 72 байта - длиннее не принимаем, чтобы не обрезать молча
                if ((strlen($raw) < 4) || (strlen($raw) > 72)) {
                    return "длина пароля - от 4 до 72 байт";
                }
                return null;
            }

            if ($isEmpty) {
                return $col->required ? "поле обязательно для заполнения" : null;
            }
            if (($col->maxLength !== false) && (mb_strlen($raw, "UTF-8") > $col->maxLength)) {
                return "максимальная длина - " . (int)$col->maxLength . " симв.";
            }

            switch ($col->type) {
                case "integer":
                    if (!preg_match('/^\s*-?\d{1,10}\s*$/', $raw) || (abs((int)$raw) > 2147483647)) {
                        return "ожидается целое число";
                    }
                    break;
                case "float":
                    // колонки decimal(6,2): не более 9999.99
                    if (!is_numeric($raw) || ((float)$raw < 0) || ((float)$raw >= 10000)) {
                        return "ожидается число от 0 до 9999.99";
                    }
                    break;
                case "select":
                    // значение должно существовать в справочнике: ID_NL_VIEW -> NL_VIEW и т.д.
                    if (!preg_match('/^\s*\d{1,10}\s*$/', $raw)) {
                        return "некорректный идентификатор";
                    }
                    $refRes = db_prepared("SELECT 1 FROM " . db_ident(substr($col->dbName, 3)) . " WHERE " . db_ident($col->dbName) . " = ?", array((int)$raw));
                    if (!($refRes instanceof mysqli_result) || (db_num_rows($refRes) == 0)) {
                        return "значение отсутствует в справочнике";
                    }
                    break;
                case "checkbox":
                    if (!in_array($raw, array("Да", "Нет"), true)) {
                        return "допустимо только Да или Нет";
                    }
                    break;
                case "photo":
                case "photos":
                case "file":
                    $list = json_decode($raw, true);
                    /*if (!is_array($list) || (count($list) > 50)) {
                        return "ожидается список файлов (до 50)";
                    }*/
                    $oldList = ($row_cur !== null) ? $this->decodeUrlList($row_cur[$col->dbName] ?? "") : array();
                    $mine = (isset($_SESSION["uploaded_files"]) && is_array($_SESSION["uploaded_files"])) ? $_SESSION["uploaded_files"] : array();
                    foreach ($list as $url) {
                        if (!$this->isValidUploadUrl($url)) {
                            return "недопустимый адрес файла";
                        }
                        // файл должен быть уже в этой записи или загружен этим пользователем в текущей сессии
                        if (!in_array($url, $oldList, true) && !isset($mine[$url])) {
                            return "файл не был загружен";
                        }
                    }
                    break;
                case "rich":
                    // Quill Delta: URL-кодированный JSON вида {"ops":[...]}
                    $delta = json_decode(rawurldecode($raw), true);
                    if (!is_array($delta) || !isset($delta["ops"]) || !is_array($delta["ops"])) {
                        return "некорректный формат текста";
                    }
                    break;
            }
            return null;
        }

        public function get_query_left_joins() {
            $leftJoin = "";
            for ($i = 0; $i < count($this->leftJoins); $i++) {
                $lj = $this->leftJoins[$i];
                $leftJoin .= " LEFT JOIN " . db_ident($lj) . " ON " . db_ident($lj) . "." . db_ident("ID_" . $lj) . " = tbl." . db_ident("ID_" . $lj);
            }
            return $leftJoin;
        }

    }

    function user_auth($login, $pass) {
        // [SEC] Из $_POST могут прийти массивы/не-строки - принимаем только строки разумной длины
        if (!is_string($login) || !is_string($pass) || ($login === "") || (strlen($login) > 50) || (strlen($pass) > 255)) {
            return false;
        }

        // [SEC-5] Защита от перебора: после серии неудачных попыток вход временно блокируется (по логину и по IP)
        if (login_is_locked($login)) {
            $_SESSION["login_error"] = "Слишком много неудачных попыток входа. Повторите через " . LOGIN_LOCK_MINUTES . " мин.";
            return false;
        }

        // [SEC] Логин - через плейсхолдер (раньше конкатенацией: обход авторизации через ' OR 1=1 --)
        // [SEC-3] Пароль больше не сравнивается в SQL через AES_ENCRYPT: хеш читаем и проверяем password_verify()
        $query = "SELECT * FROM NL_USER au WHERE (au.NL_USER_LOGIN = ?)";
        $res = db_prepared($query, array($login)) or die(db_error($query));
        $row = (db_num_rows($res) > 0) ? db_fetch_assoc($res) : null;

        $ok = false;
        if ($row !== null) {
            $hash = (string)$row["NL_USER_PASSWORD"];
            $ok = password_verify($pass, $hash);
            // при смене параметров хеширования (cost/алгоритм) хеш обновляется при успешном входе
            if ($ok && password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                db_prepared("UPDATE NL_USER SET NL_USER_PASSWORD = ? WHERE ID_NL_USER = ?", array(password_hash($pass, PASSWORD_DEFAULT), (int)$row["ID_NL_USER"]));
            }
        } else {
            // пользователя нет: тратим столько же времени, сколько на проверку пароля (нет подсказки "логин не существует")
            password_hash($pass, PASSWORD_DEFAULT);
        }

        if ($ok) {
            login_reset($login);
            session_regenerate_id(true); // защита от фиксации сессии
            // [SEC] числовые значения приводим к int: они потом подставляются в SQL и в JS
            $_SESSION["ID_NL_USER"] = (int)$row["ID_NL_USER"];
            $_SESSION["NL_USER_LOGIN"] = $row["NL_USER_LOGIN"];
            $_SESSION["NL_USER_SHORT"] = $row["NL_USER_SHORT"];
            $_SESSION["NL_USER_FULL"] = $row["NL_USER_FULL"];
            $_SESSION["NL_USER_PHONE"] = $row["NL_USER_PHONE"];
            $_SESSION["ID_NL_USER_PERMISSION"] = (int)$row["ID_NL_USER_PERMISSION"];
            $_SESSION["onlymy"] = "0";
            $_SESSION["LAST_ACTIVITY"] = time();
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32)); // новый CSRF-токен для новой сессии
            return true;
        }

        login_register_fail($login);
        // одинаковое сообщение для "нет логина" и "неверный пароль"
        $_SESSION["login_error"] = "Неверный логин или пароль";
        return false;
    }

    function user_logout() {
        // [SEC-5] Раньше удалялись только отдельные ключи, сама сессия (и её id) оставалась жить.
        // Теперь: очистка данных, удаление cookie и уничтожение сессии на сервере.
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), "", array(
                "expires" => time() - 42000,
                "path" => $p["path"],
                "domain" => $p["domain"],
                "secure" => $p["secure"],
                "httponly" => $p["httponly"],
                "samesite" => $p["samesite"] ?: "Strict"
            ));
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    function includeAdminPartsByLvl($wrap_start = "", $wrap_end = "") {
        global $page;

        $main_file = $_SERVER["DOCUMENT_ROOT"] . "/admin/parts/" . $page . ".php";
        // [SEC-1] страница справочников - только администратору (остальным показывается главная)
        $dictsDenied = ($page === "dicts") && ((int)($_SESSION["ID_NL_USER_PERMISSION"] ?? 0) !== 2);
        if (preg_match('/^[A-Za-z0-9_-]+$/', $page) && file_exists($main_file) && !$dictsDenied) { // [SEC] имя страницы из REQUEST_URI - только безопасные символы (LFI)
            if ($wrap_start != "") {
                echo $wrap_start;
            }
            include $main_file;
            if ($wrap_end != "") {
                echo $wrap_end;
            }
        } else {
            include $_SERVER["DOCUMENT_ROOT"] . "/admin/parts/main.php";
        }
    }
