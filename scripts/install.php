#!/usr/bin/env php
<?php

namespace PhotoRigma;

use Dotenv\Dotenv;
use PDO;
use PDOException;
use RuntimeException;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'Этот скрипт должен запускаться только из CLI' . PHP_EOL);
    exit(1);
}

$workDir = dirname(__DIR__);

// 1. Проверка наличия .env
$envFile = "$workDir/config/.env";
if (!file_exists($envFile)) {
    fwrite(STDERR, '[ERROR] Файл .env не найден. Сначала выполните composer install' . PHP_EOL);
    exit(1);
}
define('IN_GALLERY', true);
require_once "$workDir/vendor/autoload.php";

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

if (!$dbType || !$dbName) {
    fwrite(STDERR, '[ERROR] Не хватает настроек для подключения к БД в .env' . PHP_EOL);
    exit(1);
}

echo '[INFO] Установка начата' . PHP_EOL;

// 4. Подключение к БД
try {
    $pdo = match ($dbType) {
        'sqlite' => connect_sqlite($workDir, $dbName),
        'mysql', 'pgsql' => connect_sql($dbType, $dbName, $dbSocket, $dbHost, $dbPort, $dbUser, $dbPass),
        default => throw new RuntimeException("Неизвестный тип БД: $dbType")
    };
} catch (PDOException $e) {
    fwrite(STDERR, '[ERROR] Не удалось подключиться к базе данных.' . PHP_EOL);
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo '[OK] Подключение к базе установлено' . PHP_EOL;

// 5. Выполнение SQL-скрипта
$sqlFile = "$workDir/sql/$dbType/photorigma.sql";
if (!file_exists($sqlFile)) {
    fwrite(STDERR, "[ERROR] SQL-скрипт отсутствует: $sqlFile" . PHP_EOL);
    exit(1);
}

echo '[INFO] Запуск SQL-скрипта...' . PHP_EOL;

try {
    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);
    echo '[OK] База данных инициализирована' . PHP_EOL;
} catch (PDOException $e) {
    fwrite(STDERR, '[ERROR] Ошибка выполнения SQL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

// 6. Дополнительные действия
echo '[INFO] Установка завершена.' . PHP_EOL;
echo '[INFO] Теперь вы можете запустить проект через public/index.php' . PHP_EOL;

exit(0);

// --- Функции для подключения к СУБД ---

/**
 * @param string $workDir
 * @param string $dbName
 * @return PDO
 */
function connect_sqlite(string $workDir, string $dbName): PDO
{
    $sqlitePath = "$workDir/var/sql/$dbName.sqlite";

    if (!is_dir(dirname($sqlitePath)) && !mkdir(dirname($sqlitePath), 0755, true) && !is_dir(dirname($sqlitePath))) {
        fwrite(STDERR, 'Не удалось создать директорию: ' . dirname($sqlitePath) . PHP_EOL);
        exit(1);
    }

    if (!is_file($sqlitePath)) {
        if (!touch($sqlitePath)) {
            fwrite(STDERR, "Не удалось создать файл SQLite: $sqlitePath" . PHP_EOL);
            exit(1);
        }
        chmod($sqlitePath, 0660);
        echo "[OK] Файл SQLite создан: $sqlitePath" . PHP_EOL;
    } elseif (!is_writable($sqlitePath)) {
        fwrite(STDERR, "[ERROR] Файл SQLite недоступен для записи: $sqlitePath" . PHP_EOL);
        exit(1);
    } else {
        echo "[SKIP] Файл SQLite уже существует: $sqlitePath" . PHP_EOL;
    }

    return new PDO("sqlite:$sqlitePath", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

/**
 * @param string $dbType
 * @param string $dbName
 * @param string|null $socket
 * @param string|null $host
 * @param string|null $port
 * @param string|null $user
 * @param string|null $pass
 * @return PDO
 */
function connect_sql(
    string $dbType,
    string $dbName,
    ?string $socket,
    ?string $host,
    ?string $port,
    ?string $user,
    ?string $pass
): PDO {
    try {
        // Сначала пробуем через сокет
        if ($socket && is_file($socket)) {
            $dsn = "$dbType:unix_socket=$socket;dbname=$dbName" . (($dbType === 'mysql') ? ';charset=utf8mb4' : '');
            echo "[INFO] Попытка подключения к $dbType через сокет: $socket" . PHP_EOL;
            return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }
    } catch (PDOException) {
        echo '[INFO] Сокет недоступен. Переход на host:port...' . PHP_EOL;
    }

    // Если сокет не работает — через host:port
    $dsn = "$dbType:host=$host;" . ($port ? "port=$port;" : '') . "dbname=$dbName" . (($dbType === 'mysql') ? ';charset=utf8mb4' : '');

    echo "[INFO] Подключение к $dbType через $host:" . ($port ?: 'default') . PHP_EOL;

    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
