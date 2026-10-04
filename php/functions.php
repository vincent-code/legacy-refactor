<?php

/*
 * ==========================================================================
 * DATABASE FUNCTIONS
 * ==========================================================================
*/

    function db_connect() {
        global $mysqli;

        // [SEC] В PHP 8.1+ mysqli по умолчанию бросает исключения - ловим их сами,
        // чтобы не выводить пользователю хост/логин/стек вызовов.
        try {
            $mysqli = new mysqli(HOST, USERNAME, PASSWORD, DBNAME);
        } catch (mysqli_sql_exception $e) {
            error_log("DB connect error: " . $e->getMessage());
            http_response_code(500);
            exit("Ошибка подключения к базе данных");
        }

        if ($mysqli->connect_errno) {
            // [SEC] подробности - только в лог, пользователю - общее сообщение
            error_log("DB connect error: " . $mysqli->connect_error);
            http_response_code(500);
            exit("Ошибка подключения к базе данных");
        }

        if (!$mysqli->set_charset("utf8")) {
            error_log("DB charset error: " . $mysqli->error);
        }
    }

    function db_disconnect() {
        global $mysqli;
        $mysqli->close();
    }

    function db_data_seek($res, $row_number) {
        return $res->data_seek($row_number);
    }

    /**
     * [SEC] Только для запросов БЕЗ пользовательских данных (константные строки).
     * Любые переменные в запрос подставлять через db_prepared().
     */
    function db_query($query) {
        global $mysqli;
        try {
            return $mysqli->query($query);
        } catch (mysqli_sql_exception $e) {
            error_log("DB query error: " . $e->getMessage() . " | " . $query);
            return false;
        }
    }

    /**
     * [SEC] Подготовленный запрос (mysqli prepared statements).
     * Данные передаются ТОЛЬКО через $params (плейсхолдеры ?), в текст запроса не попадают.
     * Идентификаторы (таблицы/колонки) плейсхолдерами не передаются - для них db_ident().
     *
     * @return mysqli_result|bool  mysqli_result для SELECT, true для INSERT/UPDATE/DELETE, false при ошибке
     */
    function db_prepared($query, array $params = array()) {
        global $mysqli;
        try {
            $stmt = $mysqli->prepare($query);
            if ($stmt === false) {
                error_log("DB prepare error: " . $mysqli->error . " | " . $query);
                return false;
            }
            if (count($params) > 0) {
                $types = "";
                $values = array();
                foreach (array_values($params) as $p) {
                    if (is_bool($p)) {
                        $p = (int)$p;
                    }
                    if (is_int($p)) {
                        $types .= "i";
                    } elseif (is_float($p)) {
                        $types .= "d";
                    } else {
                        $types .= "s";
                        $p = ($p === null) ? null : (string)$p;
                    }
                    $values[] = $p;
                }
                $stmt->bind_param($types, ...$values);
            }
            if (!$stmt->execute()) {
                error_log("DB execute error: " . $stmt->error . " | " . $query);
                $stmt->close();
                return false;
            }
            $result = $stmt->get_result(); // false для запросов без набора строк
            $stmt->close();
            return ($result === false) ? true : $result;
        } catch (mysqli_sql_exception $e) {
            error_log("DB prepared error: " . $e->getMessage() . " | " . $query);
            return false;
        }
    }

    /**
     * [SEC] Имена таблиц и колонок нельзя передать через плейсхолдер.
     * Допускаем только [A-Za-z0-9_] и оборачиваем в обратные кавычки.
     */
    function db_ident($name) {
        if (!is_string($name) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
            error_log("DB bad identifier: " . (is_string($name) ? $name : gettype($name)));
            http_response_code(400);
            exit("Bad request");
        }
        return "`" . $name . "`";
    }

    /**
     * [SEC] Значение для LIKE '%...%': экранируем спецсимволы \ % _ (значение потом идёт через плейсхолдер)
     */
    function db_like($value) {
        return "%" . addcslashes((string)$value, "\\%_") . "%";
    }

    function db_fetch_assoc($res) {
        return $res->fetch_assoc();
    }

    function db_num_rows($res) {
        return $res->num_rows;
    }

    function db_error($query) {
        global $mysqli;
        // [SEC] Раньше возвращался текст запроса (раскрытие структуры БД + отражённый XSS,
        // т.к. в запрос подставлялись данные пользователя). Теперь - в лог, наружу общее сообщение.
        error_log("DB error: " . ($mysqli ? $mysqli->error : "") . " | " . $query);
        return "<br /><br />Ошибка выполнения запроса.<br /><br />";
    }

    /**
     * [SEC] Экранирование по-старому (real_escape_string) НЕ использовать для построения запросов:
     * для данных - db_prepared(), для идентификаторов - db_ident().
     * @deprecated
     */
    function db_real_escape_string($escapestr) {
        global $mysqli;
        return $mysqli->real_escape_string($escapestr);
    }

/*
 * ==========================================================================
 * OUTPUT ESCAPING
 * ==========================================================================
*/

    /** [SEC] Значение в HTML (текст и атрибуты) */
    function html_esc($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    }

    /**
     * [SEC] Значение как строковый литерал JavaScript (внутри <script>):
     * экранирует кавычки, слеши, переводы строк, < > & (чтобы нельзя было закрыть </script>).
     * Возвращает уже в кавычках: "...".
     */
    function js_str($value) {
        $json = json_encode((string)$value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return ($json === false) ? '""' : $json;
    }
