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

echo '[INFO] Начато обновление проекта' . PHP_EOL;

// 4. Подключение к БД
try {
    $pdo = match ($dbType) {
        'sqlite' => connect_sqlite_update($workDir, $dbName),
        'mysql', 'pgsql' => connect_sql($dbType, $dbName, $dbSocket, $dbHost, $dbPort, $dbUser, $dbPass),
        default => throw new RuntimeException("Неизвестный тип БД: $dbType")
    };
} catch (PDOException $e) {
    fwrite(STDERR, '[ERROR] Не удалось подключиться к базе данных.' . PHP_EOL);
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo '[OK] Подключение к базе установлено' . PHP_EOL;

// 5. Получаем текущую версию из БД
try {
    $stmt = $pdo->query('SELECT ver FROM db_version LIMIT 1');
    $currentVersion = $stmt->fetchColumn();
    $stmt = null; // Освобождаем ресурсы
} catch (PDOException $e) {
    fwrite(STDERR, '[ERROR] Не удалось получить версию из БД: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

if (!$currentVersion) {
    fwrite(STDERR, '[ERROR] Версия БД не определена — возможно, проект не установлен' . PHP_EOL);
    exit(1);
}

echo "[INFO] Текущая версия БД: $currentVersion" . PHP_EOL;

// 6. Получаем список миграций
$updateDir = "$workDir/sql/$dbType/update";

if (!is_dir($updateDir)) {
    echo "[SKIP] Нет папки с миграциями: $updateDir" . PHP_EOL;
    exit(0);
}

$files = array_diff(scandir($updateDir, SCANDIR_SORT_NONE), ['.', '..']);
usort($files, static function ($a, $b) {
    return version_compare(extract_version($a), extract_version($b));
});

$updates = [];
foreach ($files as $file) {
    $version = extract_version($file);

    if ($version && version_compare($version, $currentVersion, '>')) {
        $updates[] = [
            'file' => "$updateDir/$file",
            'version' => $version,
        ];
    }
}

if (empty($updates)) {
    echo '[SKIP] Нет доступных обновлений' . PHP_EOL;
    exit(0);
}

echo '[INFO] Найдены миграции:' . PHP_EOL;
foreach ($updates as $update) {
    echo ' • ' . basename($update['file']) . PHP_EOL;
}

// 7. Применяем миграции
foreach ($updates as $update) {
    $targetVersion = $update['version'];
    $sqlFile = $update['file'];

    echo "[INFO] Применяется миграция: $sqlFile" . PHP_EOL;

    try {
        $sql = file_get_contents($sqlFile);

        if ($sql === false) {
            throw new RuntimeException("Не удалось прочитать файл миграции: $sqlFile");
        }

        $pdo->exec($sql);

        echo "[OK] Обновлено до версии: $targetVersion" . PHP_EOL;
    } catch (PDOException $e) {
        fwrite(STDERR, '[ERROR] Ошибка при выполнении миграции: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

echo '[OK] Все миграции применены' . PHP_EOL;
exit(0);
