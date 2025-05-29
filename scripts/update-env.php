#!/usr/bin/env php
<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'Этот скрипт должен запускаться только из CLI' . PHP_EOL);
    exit(1);
}

$workDir = dirname(__DIR__);
$envFile = "$workDir/config/.env";
$envExample = "$workDir/config/.env.example";

// Проверки
if (!file_exists($envFile)) {
    echo '[ERROR] .env не найден. Сначала выполните composer install' . PHP_EOL;
    exit(1);
}

if (!is_writable($envFile)) {
    echo '[ERROR] .env недоступен для записи. Проверьте права' . PHP_EOL;
    exit(1);
}

if (!file_exists($envExample)) {
    echo '[ERROR] .env.example отсутствует — невозможно обновить окружение' . PHP_EOL;
    exit(1);
}

echo '[INFO] Обновление .env...' . PHP_EOL;

// Парсер .env
$parseEnv = static function (string $file): array {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $vars = [];

    foreach ($lines as $line) {
        if (str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2) + ['', null];
        if ($key) {
            $vars[trim($key)] = trim($value);
        }
    }

    return $vars;
};

$exampleVars = $parseEnv($envExample);
$currentVars = $parseEnv($envFile);

// Новые переменные: есть в .env.example, но отсутствуют в .env или пустые
$missingKeys = array_filter($exampleVars, static function ($key) use ($currentVars) {
    return !array_key_exists($key, $currentVars);
}, ARRAY_FILTER_USE_KEY);

// Устаревшие переменные: есть в .env, но отсутствуют в .env.example
$deprecatedVars = array_diff_key($currentVars, $exampleVars);

// 1. Добавляем недостающие переменные
if (!empty($missingKeys)) {
    foreach ($missingKeys as $key => $value) {
        $line = PHP_EOL . '# Автоматически добавлено через update-env.php' . PHP_EOL . "$key=$value" . PHP_EOL;
        file_put_contents($envFile, $line, FILE_APPEND);
        echo "[INFO] Переменная '$key' добавлена в .env" . PHP_EOL;
    }
} else {
    echo '[SKIP] Все переменные из .env.example уже в .env' . PHP_EOL;
}

// 2. Предупреждаем об удалённых переменных
if (!empty($deprecatedVars)) {
    echo '[INFO] Эти переменные больше не нужны (удалены из .env.example):' . PHP_EOL;
    foreach ($deprecatedVars as $key => $value) {
        echo " • $key=... ← можно удалить из .env" . PHP_EOL;
    }
}

// Умный вывод завершения
if (!empty($missingKeys) || !empty($deprecatedVars)) {
    echo '[OK] .env частично обновлён.' . PHP_EOL;
    echo '[INFO] Рекомендуется вручную проверить новые и устаревшие переменные' . PHP_EOL;
} else {
    echo '[OK] .env полностью актуален.' . PHP_EOL;
}

exit(0);
