#!/usr/bin/env php
<?php

namespace PhotoRigma;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'Error: This script must be run from the command line' . PHP_EOL);
    exit(1);
}

define('IN_GALLERY', true);
$taskEnable = $taskType = $taskHour = $workDir = '';

require_once (__DIR__) . '/cli/bootstrap.php';

$cronScript = "$workDir/scripts/cron.php";

if ($taskHour < 0 || $taskHour > 23) {
    $taskHour = 2; // fallback
}

echo '[WARN] Автоматическая обработка следующего скрипта зависит от прав пользователя!' . PHP_EOL;

if (!function_exists('shell_exec') || !is_executable('/usr/bin/crontab')) {
    echo "[SKIP] crontab недоступен → автоматическая настройка отключена\n";
    exit(0);
}

echo '[INFO] Проверяем настройки расписания...' . PHP_EOL;

// Если TASK_ENABLE=false → удаляем cron-задачу
if (!$taskEnable) {
    echo '[INFO] TASK_ENABLE=false → удаляем cron-задачи для cron.php' . PHP_EOL;
    remove_cron_task($cronScript);
    exit(0);
}

switch ($taskType) {
    case 'cron':
        setup_cron_task($cronScript, $taskHour);
        break;

    case 'systemd':
        echo '[INFO] Режим systemd выбран. Автоматическая настройка временно недоступна.' . PHP_EOL;
        echo '[INFO] Подробнее: https://wiki.archlinux.org/title/Systemd/Timers ' . PHP_EOL;
        break;

    case 'manual':
        echo '[SKIP] Режим manual → автоматическое добавление задач отключено' . PHP_EOL;
        break;

    default:
        echo "[ERROR] Неизвестный тип задачи: $taskType" . PHP_EOL;
        exit(1);
}

exit(0);
