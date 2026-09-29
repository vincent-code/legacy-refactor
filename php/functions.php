<?
    /* ==========================================================================
       DATABASE FUNCTIONS
       ========================================================================== */

    function db_connect() {
        global $mysqli;

        $mysqli = new mysqli(HOSTNAME, USERNAME, PASSWORD, DBNAME);

        if ($mysqli->connect_errno) {
            printf("Ошибка подключения к базе: %s\n", $mysqli->connect_error);
            exit();
        }

        if (!$mysqli->set_charset("utf8")) {
            printf("Ошибка загрузки кодировки utf8: %s\n", $mysqli->error);
            printf("Текущая кодировка: %s\n", $mysqli->character_set_name());
        }
    }

    function db_disconnect() {
        global $mysqli;
        $mysqli->close();
    }

    function db_data_seek($res, $row_number) {
        return $res->data_seek($row_number);
    }

    function db_query($query) {
        global $mysqli;
        return $mysqli->query($query);
    }

    function db_fetch_assoc($res) {
        return $res->fetch_assoc();
    }

    function db_num_rows($res) {
        return $res->num_rows;
    }

    function db_error($query) {
        $q_err = "<br /><br />Ошибка в запросе:<br />" . $query . "<br /><br />";
        return $q_err;
        //global $mysqli;
        //return $mysqli->error;
    }

    function db_real_escape_string($escapestr) {
        global $mysqli;
        return $mysqli->real_escape_string($escapestr);
    }