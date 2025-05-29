<?php

/** @noinspection PhpUnusedLocalVariableInspection */

namespace PhotoRigma;

use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'Этот скрипт должен запускаться только из CLI' . PHP_EOL);
    exit(1);
}

if (!defined('IN_GALLERY')) {
    fwrite(STDERR, 'Этот скрипт должен использоваться только внутри других скриптов' . PHP_EOL);
    exit(1);
}

$workDir = dirname(__DIR__, 2);

// 1. Проверка наличия .env
$envFile = "$workDir/config/.env";
if (!file_exists($envFile)) {
    fwrite(STDERR, '[ERROR] Файл .env не найден. Сначала выполните composer install' . PHP_EOL);
    exit(1);
}

require_once "$workDir/vendor/autoload.php";
require_once 'function.php';

// 2. Загрузка .env
$dotenv = Dotenv::createImmutable($workDir . '/config');
/** @noinspection UnusedFunctionResultInspection */
$dotenv->load();

// 3. Чтение настроек из .env
$dbType = $_ENV['DB_TYPE'] ?? null;
$dbHost = $_ENV['DB_HOST'] ?? null;
$dbPort = $_ENV['DB_PORT'] ?? null;
$dbSocket = $_ENV['DB_SOCKET'] ?? null;
$dbUser = $_ENV['DB_USER'] ?? null;
$dbPass = $_ENV['DB_PASSWORD'] ?? null;
$dbName = $_ENV['DB_NAME'] ?? null;

$taskEnable = filter_var($_ENV['TASK_ENABLE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$taskType = strtolower($_ENV['TASK_TYPE'] ?? 'none');
$taskHour = (int)($_ENV['TASK_TIME'] ?? 2);
