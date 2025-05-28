#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace PhotoRigma;

use Dotenv\Dotenv;
use Exception;
use PhotoRigma\Classes\Bootstrap;

use function PhotoRigma\Include\archive_old_logs;
use function PhotoRigma\Include\log_in_file;

// Устанавливаем кодировку для работы с мультибайтовыми строками
$encoding = mb_regex_encoding('UTF-8');
mb_internal_encoding('UTF-8');

define('IN_GALLERY', true);
define('WORK_DIR', rtrim(dirname(__DIR__), '/'));

$config = [];

// Подключаем загрузчик конфигурации и ядра проекта
require_once WORK_DIR . '/vendor/autoload.php';

// Инициализация .env
$dotenv = Dotenv::createImmutable(WORK_DIR . '/config');
/** @noinspection UnusedFunctionResultInspection */
$dotenv->load();

// Инициализируем ядро проекта (конфигурация, константы, сессию и функции)
$required_files = Bootstrap::init();
// Подключаем все файлы из списка обязательных файлов
foreach ($required_files as $file) {
    require_once $file;
}

// Загрузка и инициализация объектов
try {
    /** @noinspection PhpUnusedLocalVariableInspection */
    [$db, $work, $user, $template] = Bootstrap::load($config, $_SESSION);
} catch (Exception $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    log_in_file(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
        'Возникла ошибка при инициализации объектов | Ошибка: ' . $e->getMessage()
    );
    exit(1);
}

/**
 * Очищаем значение массива $config[], чтобы предотвратить его использование напрямую.
 * Все настройки теперь доступны только через свойство \PhotoRigma\Classes\Work::$config.
 */
unset($config);

// Начало выполнения работ
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection DuplicatedCode */
log_in_file(
    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
    'Фоновая задача: начало выполнения работ'
);

// Защита от запуска через браузер
if (PHP_SAPI !== 'cli') {
    /** @noinspection PhpUnhandledExceptionInspection */
    log_in_file(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
        'Фоновая задача: запуск cron через веб-интерфейс запрещён'
    );
    exit(1);
}

// Включаем "таймер" выполнения скрипта
$start_time = microtime(true);

// Выполняем основную логику
try {
    // Окончательное удаление пользователей
    log_in_file(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
        'Фоновая задача: окончательное удаление пользователей'
    );
    $user->cron_user_delete();
    // Архивация старых логов
    log_in_file(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
        'Фоновая задача: архивирование старых логов'
    );
    archive_old_logs();
} catch (Exception $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    log_in_file(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
        'Фоновая задача: исключение при выполнении основной логики | Сообщение: ' . $e->getMessage()
    );
    exit(2);
}

// Получаем время выполнения скрипта
$end_time = microtime(true);
$execution_time = round(($end_time - $start_time) * 1000, 6); // Время в миллисекундах

// Логируем успешное завершение
/** @noinspection PhpUnhandledExceptionInspection */
log_in_file(
    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
    'Фоновая задача: все задачи завершены| Время выполнения: ' . $execution_time . 'мс'
);
exit(0);
