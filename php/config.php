<?php

// [SEC-4] Секреты хранятся в .env в корне проекта.
// .env не должен попадать в репозиторий и не должен быть доступен
// напрямую через web-сервер.

// [SEC-4] Ошибки PHP не отображаются клиенту.
// Подробности записываются в серверный лог.
ini_set("display_errors", "0");
ini_set("log_errors", "1");


// Корень проекта.
// /php/config.php -> dirname(__DIR__) = корень проекта.
$projectRoot = dirname(__DIR__);


// Composer autoload.
require_once $projectRoot . "/vendor/autoload.php";


// Загружаем .env.
$dotenv = Dotenv\Dotenv::createImmutable($projectRoot);

try {
    $dotenv->load();

    // Все параметры БД обязательны и не могут быть пустыми.
    $dotenv->required([
        "HOST",
        "USERNAME",
        "PASSWORD",
        "DBNAME"
    ])->notEmpty();
} catch (\Dotenv\Exception\DotenvException $e) {
    error_log("NL config: unable to load .env: " . $e->getMessage());

    http_response_code(500);
    exit("Ошибка конфигурации сервера");
} catch (\Dotenv\Exception\ValidationException $e) {
    error_log("NL config: invalid .env configuration: " . $e->getMessage());

    http_response_code(500);
    exit("Ошибка конфигурации сервера");
}


// Database configuration.
define("HOST", $_ENV["HOST"]);
define("USERNAME", $_ENV["USERNAME"]);
define("PASSWORD", $_ENV["PASSWORD"]);
define("DBNAME", $_ENV["DBNAME"]);

$mysqli = null;

mb_internal_encoding("UTF-8");
date_default_timezone_set("Europe/Moscow");