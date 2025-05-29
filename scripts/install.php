#!/usr/bin/env php
<?php

namespace PhotoRigma;

use PDOException;
use RuntimeException;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'Этот скрипт должен запускаться только из CLI' . PHP_EOL);
    exit(1);
}

define('IN_GALLERY', true);
$dbType = $dbHost = $dbName = $dbPass = $dbPort = $dbSocket = $dbUser = $workDir = '';

require_once (__DIR__) . '/cli/bootstrap.php';

if (!$dbType || !$dbName) {
    fwrite(STDERR, '[ERROR] Не хватает настроек для подключения к БД в .env' . PHP_EOL);
    exit(1);
}

echo '[INFO] Установка начата' . PHP_EOL;

// 4. Подключение к БД
try {
    $pdo = match ($dbType) {
        'sqlite' => connect_sqlite_install($workDir, $dbName),
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
