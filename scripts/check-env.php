#!/usr/bin/env php
<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'Этот скрипт должен запускаться только из CLI' . PHP_EOL);
    exit(1);
}

$workDir = dirname(__DIR__);
$envFile = "$workDir/config/.env";
$envExample = "$workDir/config/.env.example";

if (!file_exists($envFile)) {
    echo '[SKIP] .env не найден — пропускаю проверку окружения' . PHP_EOL;
    exit(0);
}

if (!file_exists($envExample)) {
    echo '[ERROR] .env.example отсутствует — невозможно сравнить окружение' . PHP_EOL;
    exit(1);
}

echo '[INFO] Проверяю актуальность .env' . PHP_EOL;

// Парсим содержимое .env и .env.example
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

// Сравниваем
$missingKeys = array_diff_key($exampleVars, $currentVars);

if (!empty($missingKeys)) {
    echo '[WARN] В .env.example появились новые переменные:' . PHP_EOL;
    foreach ($missingKeys as $key => $value) {
        echo " • $key=... ← рекомендуется добавить в .env" . PHP_EOL;
    }

    echo '[INFO] Обновите .env вручную или запустите:' . PHP_EOL;
    echo ' composer run update-env' . PHP_EOL;
    exit(1);
}

echo '[OK] .env содержит все необходимые переменные.' . PHP_EOL;
exit(0);
