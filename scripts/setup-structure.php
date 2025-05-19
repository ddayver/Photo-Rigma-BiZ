#!/usr/bin/env php
<?php

namespace PhotoRigma;

use Random\RandomException;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'Этот скрипт должен запускаться только из CLI' . PHP_EOL);
    exit(1);
}

$workDir = dirname(__DIR__);
$envFile = "$workDir/config/.env";
$envExample = "$workDir/config/.env.example";

echo 'Composer установлен.' . PHP_EOL;
echo 'Начинаем предварительную настройку:' . PHP_EOL;

// [1/5] Создание .env
echo '[1/5] Создание файла .env... ';
if (!file_exists($envFile)) {
    copy($envExample, $envFile);
    echo '[OK]' . PHP_EOL;
} else {
    echo '[SKIP] уже существует' . PHP_EOL;
}

// [2/5] Генерация APP_KEY и APP_SALT
echo '[2/5] Генерация ключей...' . PHP_EOL;

if (!is_writable($envFile)) {
    fwrite(STDERR, "Файл .env недоступен для записи: $envFile" . PHP_EOL);
    exit(1);
}

$envContent = file_get_contents($envFile);

if ($envContent === false) {
    fwrite(STDERR, 'Не удалось прочитать .env' . PHP_EOL);
    exit(1);
}

$appKeyPattern = '/^APP_KEY=.*$/m';
$appSaltPattern = '/^APP_SALT=.*$/m';

$keyExists = preg_match($appKeyPattern, $envContent, $keyMatch);
$saltExists = preg_match($appSaltPattern, $envContent, $saltMatch);

try {
    // APP_KEY
    echo ' • APP_KEY... ';
    $currentKey = $keyMatch[0] ?? '';
    if (!$keyExists || trim(str_replace('APP_KEY=', '', $currentKey)) === '') {
        $key = base64_encode(random_bytes(32));
        $envContent = preg_replace($appKeyPattern, "APP_KEY=base64:$key", $envContent);
        echo '[OK]' . PHP_EOL;
    } else {
        echo '[SKIP] уже существует' . PHP_EOL;
    }

    // APP_SALT
    echo ' • APP_SALT... ';
    $currentSalt = $saltMatch[0] ?? '';
    $saltValue = trim(str_replace('APP_SALT=', '', $currentSalt));
    if (!$saltExists || $saltValue === '' || $saltValue === 'change_this_to_random_string') {
        $salt = bin2hex(random_bytes(16));
        $envContent = preg_replace($appSaltPattern, "APP_SALT=$salt", $envContent);
        echo '[OK]' . PHP_EOL;
    } else {
        echo '[SKIP] уже существует' . PHP_EOL;
    }

    if (!file_put_contents($envFile, $envContent)) {
        fwrite(STDERR, PHP_EOL . 'Не удалось сохранить .env' . PHP_EOL);
        exit(1);
    }

    echo 'Генерация ключей: [OK]' . PHP_EOL;

} catch (RandomException $e) {
    fwrite(STDERR, 'Ошибка при генерации ключей: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

// [3/5] Развертывание структуры var/
echo '[3/5] Развертывание структуры var/... ';

$varDirs = [
    'var/Action',
    'var/avatar',
    'var/cache',
    'var/gallery/user',
    'var/language',
    'var/log',
    'var/templates',
    'var/themes',
    'var/thumbnail/user',
];

foreach ($varDirs as $dir) {
    if (!is_dir("$workDir/$dir") && !mkdir("$workDir/$dir", 0755, true) && !is_dir("$workDir/$dir")) {
        fwrite(STDERR, PHP_EOL . "Директория \"$dir\" не создана. Проверьте права доступа." . PHP_EOL);
        exit(1);
    }
}

echo '[OK]' . PHP_EOL;

// [4/5] Копирование default_files
echo '[4/5] Копирование default_files в var/... ' . PHP_EOL;

$sourceDir = "$workDir/core/Resources/default_files";
$targetMap = [
    'avatar' => 'var/avatar',
    'gallery' => 'var/gallery',
    'thumbnail' => 'var/thumbnail'
];

foreach ($targetMap as $srcSub => $destSub) {
    $srcPath = "$sourceDir/$srcSub/";
    $destPath = "$workDir/$destSub/";

    // Убедиться, что исходная папка доступна и является директорией
    if (!is_dir($srcPath)) {
        fwrite(STDERR, "Исходная директория отсутствует: $srcPath" . PHP_EOL);
        exit(1);
    }

    $files = scandir($srcPath, SCANDIR_SORT_NONE);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $srcFile = "$srcPath/$file";
        $destFile = "$destPath/$file";

        if (file_exists($destFile)) {
            echo " • $file -> [SKIP] уже существует в $destSub" . PHP_EOL;
            continue;
        }

        if (is_file($srcFile)) {
            if (!copy($srcFile, $destFile)) {
                fwrite(STDERR, " • Ошибка копирования $srcFile -> $destFile" . PHP_EOL);
                exit(1);
            }
            echo " • $file -> $destSub/" . basename($file) . ' [OK]' . PHP_EOL;
        }
    }
}

echo 'Копирование default_files: [OK]' . PHP_EOL;

// [5/5] Создание ссылок внутри проекта
echo '[5/5] Создание ссылок внутри проекта...' . PHP_EOL;

$symlinks = [
    'avatar' => '../var/avatar',
];

$publicDir = "$workDir/public";

// 1. Простые симлинки (например: avatar)
foreach ($symlinks as $link => $target) {
    $linkPath = "$publicDir/$link";

    if (file_exists($linkPath)) {
        echo " • $link -> [SKIP] уже существует" . PHP_EOL;
        continue;
    }

    if (is_link($linkPath)) {
        unlink($linkPath); // Чистим битые линки
    }

    symlink($target, $linkPath);
    echo " • $link -> создан" . PHP_EOL;
}

echo 'Создание базовых ссылок: [OK]' . PHP_EOL;

// 2. Темы из var/themes/
$varThemesDir = "$workDir/var/themes/";
if (is_dir($varThemesDir)) {
    $varThemes = array_diff(scandir($varThemesDir, SCANDIR_SORT_NONE), ['.', '..']);

    foreach ($varThemes as $theme) {
        $link = "themes/$theme";
        $linkPath = "$publicDir/$link";
        $targetPath = "../../var/themes/$theme";

        if (!is_dir("$varThemesDir/$theme")) {
            continue;
        }

        if (file_exists($linkPath)) {
            if (is_link($linkPath)) {
                $realTarget = realpath($linkPath);
                $expectedTarget = "$workDir/var/themes/$theme";

                if ($realTarget === $expectedTarget) {
                    echo " • $link -> [OK] уже ведёт на var/themes/$theme" . PHP_EOL;
                    continue;
                }

                echo " • $link -> [INFO] пересоздаю на var/themes/$theme" . PHP_EOL;
                unlink($linkPath);
            } else {
                echo " • $link -> [ERROR] файл или папка блокирует создание симлинка" . PHP_EOL;
                continue;
            }
        }

        symlink($targetPath, $linkPath);
        echo " • $link -> создан на основе var/themes/$theme" . PHP_EOL;
    }
}

echo 'Симлинки тем из var/: [OK]' . PHP_EOL;

// 3. Темы из core/themes/
$coreThemesDir = "$workDir/core/themes/";
if (is_dir($coreThemesDir)) {
    $coreThemes = array_diff(scandir($coreThemesDir, SCANDIR_SORT_NONE), ['.', '..']);

    foreach ($coreThemes as $theme) {
        $link = "themes/$theme";
        $linkPath = "$publicDir/$link";
        $targetCore = "../../core/themes/$theme";

        if (!is_dir("$coreThemesDir/$theme"))
        {
            continue;
        }
        if (file_exists($linkPath)) {
            if (is_link($linkPath)) {
                $realTarget = realpath($linkPath);

                if ($realTarget === "$workDir/var/themes/$theme") {
                    // Тема из var — НЕ перезатираем, продолжаем наполнение
                    echo " • $link -> [OK] уже ведёт на var/themes/$theme" . PHP_EOL;

                    // Копируем недостающие файлы из core/themes/$theme в var/themes/$theme
                    merge_theme_files($workDir, "core/themes/$theme", "var/themes/$theme");

                    continue;
                }

                if ($realTarget === "$workDir/core/themes/$theme") {
                    // Тема из core — ничего не делаем, пропускаем
                    echo " • $link -> [SKIP] уже ведёт на core/themes/$theme" . PHP_EOL;
                    continue;
                }

                // Битая ссылка → удаляем
                echo " • $link -> [WARN] найдена ссылка, но указывает не туда. Пересоздаю..." . PHP_EOL;
                unlink($linkPath);
            } else {
                // Не симлинк → нельзя перезаписать
                echo " • $link -> [ERROR] не является симлинком. Пропущено." . PHP_EOL;
                continue;
            }
        }

        // Если тема есть в core — создаём симлинк
        symlink($targetCore, $linkPath);
        echo " • $link -> создан на основе core/themes/$theme" . PHP_EOL;
    }
}

echo 'Симлинки тем из core/: [OK]' . PHP_EOL;

// Индикатор завершения
echo 'Создание ссылок внутри проекта: [OK]' . PHP_EOL;

// Инструкция для пользователя
echo PHP_EOL . 'Готово.' . PHP_EOL;
echo 'Для дальнейшей работы выполните:' . PHP_EOL;
echo '1. nano .env — внесите свои настройки.' . PHP_EOL;
echo '2. composer run build-app — завершите установку' . PHP_EOL . PHP_EOL;

exit(0);

/**
 * Рекурсивное копирование недостающих файлов из source в target
 *
 * @param string $workDir
 * @param string $source Относительный путь к источнику (например: core/themes/default)
 * @param string $target Относительный путь к целевой папке (например: var/themes/default)
 * @param int $depth Текущая глубина рекурсии
 * @param int $maxDepth Максимальная глубина рекурсии
 */
function merge_theme_files(string $workDir, string $source, string $target, int $depth = 0, int $maxDepth = 5): void
{
    if ($depth > $maxDepth) {
        return;
    }

    $sourcePath = "$workDir/$source";
    $targetPath = "$workDir/$target";

    if (!is_dir($sourcePath)) {
        return;
    }

    // Создание целевой директории, если её нет
    if (!is_dir($targetPath) && !mkdir($targetPath, 0755, true) && !is_dir($targetPath)) {
        fwrite(STDERR, PHP_EOL . "Директория \"$targetPath\" не создана. Проверьте права доступа." . PHP_EOL);
        exit(1);
    }

    // Обработка содержимого
    foreach (array_diff(scandir($sourcePath, SCANDIR_SORT_NONE), ['.', '..']) as $item) {
        $src = "$sourcePath/$item";
        $dest = "$targetPath/$item";

        if (file_exists($dest)) {
            if (is_dir($src) && is_dir($dest)) {
                merge_theme_files($workDir, $src, $dest, $depth + 1, $maxDepth);
            }
        } elseif (is_dir($src)) {
            if (!mkdir($dest, 0755, true) && !is_dir($dest)) {
                fwrite(STDERR, PHP_EOL . "Не удалось создать директорию: $dest" . PHP_EOL);
                exit(1);
            }
            merge_theme_files($workDir, $src, $dest, $depth + 1, $maxDepth);
        } elseif (is_file($src)) {
            if (!copy($src, $dest)) {
                fwrite(STDERR, PHP_EOL . "Ошибка копирования файла: $src -> $dest" . PHP_EOL);
                exit(1);
            }
        }
    }
}
