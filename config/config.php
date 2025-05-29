<?php

/**
 * Редактируемые настройки сервера.
 *
 * Этот файл содержит настройки, необходимые для конфигурации приложения:
 * - Указываются внешние (URL) и внутренние (на диске) пути к корню и подпапкам проекта.
 * - Указываются параметры подключения к базе данных (например, хост, имя пользователя, пароль, имя базы данных).
 * - Файл используется для централизованного управления конфигурацией приложения.
 * Важно: Этот файл должен быть защищён от прямого доступа через веб-сервер.
 *
 * @author    Dark Dayver
 * @version   0.5.0
 * @since     2025-05-29
 * @namespace PhotoRigma
 * @package   PhotoRigma
 *
 * @section   Config_Main_Functions Основные функции
 *            - Настройка подключения к базе данных.
 *            - Определение путей к директориям проекта.
 *            - Автоматическое определение протокола (HTTP/HTTPS) и URL сайта.
 *            - Проверка доступности и корректности путей к директориям.
 *
 * @section   Config_Database_Settings Настройки подключения к базе данных
 *            Массив `$config['db']` содержит параметры подключения к базе данных:
 *            - **dbtype**: Тип базы данных (по умолчанию: `mysql`, можно использовать `pgsql` или `sqlite`).
 *            - **dbhost**: Сервер основной базы данных (по умолчанию: `localhost`). Не используется для SQLite.
 *            - **dbport**: Порт сервера основной базы данных (оставить пустым для значения по умолчанию, например: `3306` для MySQL).
 *                          Не используется для SQLite.
 *            - **dbsock**: Путь к сокету (используется, если хост не указан, например: `/var/lib/mysql/mysql.sock`).
 *                          Не используется для SQLite.
 *            - **dbuser**: Имя пользователя в БД (например: `root`). Не используется для SQLite.
 *            - **dbpass**: Пароль пользователя БД (например: `my_secure_password`). Не используется для SQLite.
 *            - **dbname**: Имя базы данных (например: `photorigma`). Для SQLite это путь к файлу базы данных.
 *
 * @uses      \PhotoRigma\Classes\Database Класс, где используется массив `$config['db']` для подключения к БД.
 *
 * @section   Config_Folder_Settings Настройки папок проекта
 *            - **gallery_folder**:   Имя папки для хранения фотографий (указывается относительно корня проекта).
 *                                    Пример: `'gallery'`.
 *            - **thumbnail_folder**: Имя папки для хранения эскизов фотографий (указывается относительно корня проекта).
 *                                    Пример: `'thumbnail'`.
 *            - **avatar_folder**:    Имя папки для хранения аватаров пользователей (указывается относительно корня проекта).
 *                                    Пример: `'avatar'`.
 *
 * @note      Убедитесь, что указанные папки существуют и доступны для записи. Несоблюдение этого условия может привести к ошибкам при работе с файлами.
 *
 * @section   Config_Local_Network Настройки использования проекта в локальной или внешней сети
 *            Константа `LOCALHOST_SERVER` указывает, является ли сервер локальным:
 *            - Если значение `true`, загрузка JS и CSS ограничивается локальными ресурсами (без интернета).
 *            - Если значение `false`, используются внешние ресурсы (например, CDN).
 *
 * @section   Config_Cache_Settings Настройки кеширования
 *            - Поддерживаемые типы кеширования:
 *              - **'file'**:      Файловое кеширование (данные хранятся в файлах).
 *              - **'redis'**:     Кеширование с использованием Redis.
 *              - **'memcached'**: Кеширование с использованием Memcached.
 *            - Параметры настройки зависят от выбранного типа кеширования:
 *              - Для файлового кеширования требуется указать путь к директории кеша (`cache_dir`).
 *              - Для Redis и Memcached требуется указать хост (`host`) и порт (`port`).
 *                Если порт не указан, используются значения по умолчанию:
 *                - Redis: 6379
 *                - Memcached: 11211
 *            - Массив `$config['cache']` содержит следующие ключи:
 *              - `type`:      Тип кеширования (`file`, `redis`, `memcached`). По умолчанию: `file`.
 *              - `cache_dir`: Путь к директории для файлового кеширования. Используется только при `type = 'file'`.
 *              - `host`:      Хост для Redis или Memcached. Используется только при `type = 'redis'` или `'memcached'`.
 *              - `port`:      Порт для Redis или Memcached. Если не указан, используются значения по умолчанию.
 *
 * @uses      \PhotoRigma\Classes\Cache_Handler Класс, где используется массив `$config['cache']` для работы с кешем.
 *
 * @section   Config_Error_Handling Обработка ошибок
 *            При возникновении ошибок генерируются исключения. Поддерживаемые типы исключений:
 *            - `RuntimeException`: Если не удалось определить `HTTP_HOST` или `SCRIPT_NAME`.
 *            - `RuntimeException`: Если путь к директории сайта некорректен или недоступен.
 *            - `RuntimeException`: Если обязательные директории отсутствуют или недоступны для записи.
 *
 * @throws    RuntimeException Если не удалось определить HTTP_HOST или SCRIPT_NAME.
 *            Пример сообщения: "Не удалось определить HTTP_HOST. Проверьте настройки сервера.".
 * @throws    RuntimeException Если путь к директории сайта некорректен или недоступен.
 *            Пример сообщения: "Директория недоступна для записи: {$config['site_dir']}".
 * @throws    RuntimeException Если обязательные директории отсутствуют или недоступны для записи.
 *            Пример сообщения: "Директория отсутствует или недоступна для записи: {$dir}".
 *
 * @section   Config_Related_Files Связанные файлы и компоненты
 *            - Классы приложения:
 *              -@uses \PhotoRigma\Classes\Work::$config Массив, куда копируются настройки из $config.
 *
 * @note      Этот файл является частью системы PhotoRigma и играет ключевую роль в настройке приложения.
 *            Реализованы меры безопасности для предотвращения несанкционированного доступа и выполнения действий.
 *
 * @copyright Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license   MIT License {@link https://opensource.org/licenses/MIT}
 *            Разрешается использовать, копировать, изменять, объединять, публиковать,
 *            распространять, сублицензировать и/или продавать копии программного обеспечения,
 *            а также разрешать лицам, которым предоставляется данное программное обеспечение,
 *            делать это при соблюдении следующих условий:
 *            - Уведомление об авторских правах и условия лицензии должны быть включены во все
 *              копии или значимые части программного обеспечения.
 */

namespace PhotoRigma;

use RuntimeException;

// Предотвращение прямого вызова файла
if (!defined('IN_GALLERY') || IN_GALLERY !== true) {
    /** @noinspection ForgottenDebugOutputInspection */
    error_log(
        date('H:i:s') . ' [ERROR] | ' . (filter_input(
            INPUT_SERVER,
            'REMOTE_ADDR',
            FILTER_VALIDATE_IP
        ) ?: 'UNKNOWN_IP') . ' | ' . __FILE__ . ' | Попытка прямого вызова файла'
    );
    die('HACK!');
}

/**
 * Массив с настройками сервера.
 *
 * Содержит как редактируемые пользователем параметры (например, параметры БД), так и автоматически генерируемые
 * параметры (например, URL сайта). После инициализации массив $config передаётся в свойство
 * \PhotoRigma\Classes\Work::$config.
 */
$config = [];

// =============================================================================
// НАЧАЛО РЕДАКТИРУЕМОЙ ЧАСТИ НАСТРОЕК
// =============================================================================

// =============================================================================
// Настройки подключения к базы данных
// =============================================================================
/**
 * Параметры подключения к базе данных.
 *
 * Включает следующие параметры:
 * - dbtype: Тип базы данных (по умолчанию: mysql, можно использовать pgsql или sqlite).
 * - dbhost: Сервер основной базы данных (по умолчанию: localhost). Не используется для SQLite.
 * - dbport: Порт сервера основной базы данных (оставить пустым для значения по умолчанию, например: 3306 для MySQL).
 *           Не используется для SQLite.
 * - dbsock: Путь к сокету (используется, если хост не указан, например: /var/lib/mysql/mysql.sock). Не используется
 *           для SQLite.
 * - dbuser: Имя пользователя в БД (например: root). Не используется для SQLite.
 * - dbpass: Пароль пользователя БД (например: my_secure_password). Не используется для SQLite.
 * - dbname: Имя базы данных (например: photorigma).
 *
 * @uses \PhotoRigma\Classes\Database Класс, где используется этот массив для подключения к БД.
 */
$config['db'] = [
    'dbtype' => 'mysql', // Тип базы данных (по умолчанию: mysql, можно использовать pgsql или sqlite)
    'dbhost' => 'localhost', // Сервер основной базы данных (по умолчанию: localhost). Не используется для SQLite.
    'dbport' => '', // Порт сервера основной базы данных (оставить пустым для значения по умолчанию, например: 3306
                    // для MySQL). Не используется для SQLite.
    'dbsock' => '/var/lib/mysql/mysql.sock', // Путь к сокету (используется, если хост не указан, например:
                                             // /var/lib/mysql/mysql.sock). Не используется для SQLite.
    'dbuser' => 'root', // Имя пользователя в БД (например: root). Не используется для SQLite.
    'dbpass' => '', // Пароль пользователя БД (например: my_secure_password). Не используется для SQLite.
    'dbname' => 'photorigma', // Имя базы данных (например: photorigma).
];

// =============================================================================
// Настройки папок проекта
// =============================================================================
$config['gallery_dir'] = 'gallery'; // Имя папки для хранения фотографий (указывается относительно корня проекта).
$config['thumbnail_dir'] = 'thumbnail'; // Имя папки для хранения эскизов фотографий (указывается относительно корня проекта).
$config['avatar_dir'] = 'avatar'; // Имя папки для хранения аватаров пользователей (указывается относительно корня
// проекта).

// =============================================================================
// Настройки кеширования
// =============================================================================
/**
 * Параметры конфигурации системы кеширования.
 *
 * Система поддерживает три типа кеширования:
 * - 'file': Файловое кеширование (данные хранятся в файлах).
 * - 'redis': Кеширование с использованием Redis.
 * - 'memcached': Кеширование с использованием Memcached.
 * Параметры настройки зависят от выбранного типа кеширования:
 * - Для файлового кеширования требуется указать путь к директории кеша ('cache_dir').
 * - Для Redis и Memcached требуется указать хост ('host') и порт ('port').
 *   Если порт не указан, используются значения по умолчанию:
 *   - Redis: 6379
 *   - Memcached: 11211
 *
 * @uses \PhotoRigma\Classes\Cache_Handler Класс, где используется этот массив для работы с кешем.
 */
$config['cache'] = [
    'type' => 'file',
    ///< Тип кеширования ('file', 'redis', 'memcached'). По умолчанию: file.
    'cache_dir' => 'cache',
    ///< Путь к директории для файлового кеширования. Используется только при type = 'file'.
    'host' => '127.0.0.1',
    ///< Хост для Redis или Memcached. Используется только при type = 'redis' или 'memcached'.
    'port' => '',
    ///< Порт для Redis или Memcached. Если не указан, используются значения по умолчанию.
];

// =============================================================================
// Настройки использования проекта в локальной или внешней сети
// =============================================================================
/** @var $localhost_server
 * Используется для указания, что сервер является локальным и не все его пользователи могут иметь доступ в интернет
 * (ограничивает загрузку JS и CSS с интернета).
 */
$localhost_server = false;

// =============================================================================
// КОНЕЦ РЕДАКТИРУЕМОЙ ЧАСТИ НАСТРОЕК
// =============================================================================

// =============================================================================
// НАЧАЛО БЛОКА АВТО-НАСТРОЕК
// =============================================================================
// Если в файле .env есть переменные, то они перезаписывают значения из $config.
/** @noinspection PhpArrayWriteIsNotUsedInspection */
$config['db'] = array_merge($config['db'], [
    'dbtype' => $_ENV['DB_TYPE'] ?? $config['db']['dbtype'],
    'dbhost' => $_ENV['DB_HOST'] ?? $config['db']['dbhost'],
    'dbport' => $_ENV['DB_PORT'] ?? $config['db']['dbport'],
    'dbsock' => $_ENV['DB_SOCKET'] ?? $config['db']['dbsock'],
    'dbuser' => $_ENV['DB_USER'] ?? $config['db']['dbuser'],
    'dbpass' => $_ENV['DB_PASSWORD'] ?? $config['db']['dbpass'],
    'dbname' => $_ENV['DB_NAME'] ?? $config['db']['dbname'],
]);

$config['gallery_dir']     = $_ENV['GALLERY_DIR'] ?? $config['gallery_dir'];
$config['thumbnail_dir']   = $_ENV['THUMBNAIL_DIR'] ?? $config['thumbnail_dir'];
$config['avatar_dir']      = $_ENV['AVATAR_DIR'] ?? $config['avatar_dir'];

/** @noinspection PhpArrayWriteIsNotUsedInspection */
$config['cache'] = array_merge($config['cache'], [
    'type' => $_ENV['CACHE_TYPE'] ?? $config['cache']['type'],
    'cache_dir' => $_ENV['CACHE_DIR'] ?? $config['cache']['cache_dir'],
    'host' => $_ENV['CACHE_HOST'] ?? $config['cache']['host'],
    'port' => $_ENV['CACHE_PORT'] ?? $config['cache']['port'],
]);

/** @noinspection PhpConditionAlreadyCheckedInspection */
$localhost_server = $_ENV['LOCALHOST_SERVER'] ?? $localhost_server;
/** Используется для указания, что сервер является локальным и не все его пользователи могут иметь доступ в интернет */
define('LOCALHOST_SERVER', filter_var($localhost_server, FILTER_VALIDATE_BOOLEAN));

/**
 * Определение протокола (HTTP/HTTPS) и URL сайта.
 *
 * Проверяем:
 * 1. $_ENV['APP_URL'] — приоритетный источник
 * 2. $_SERVER — альтернативные способы определения
 * Если ничего не найдено, выбрасываем исключение
 */
$config['site_url'] = '';

// 1. Проверяем APP_URL из .env, если задан и валиден
if (!empty($_ENV['APP_URL'])) {
    $app_url = $_ENV['APP_URL'];

    /** @noinspection BypassedUrlValidationInspection */
    if (filter_var($app_url, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $app_url)) {
        // Извлекаем хост из URL для последующей валидации
        $parsed_url = parse_url($app_url);
        $http_host = $parsed_url['host'] ?? '';

        if ($http_host && preg_match('/^[a-z0-9.-]+$/i', $http_host)) {
            $config['site_url'] = rtrim($app_url, '/') . '/';
        } else {
            /** @noinspection ForgottenDebugOutputInspection */
            error_log(
                date('H:i:s') . ' [ERROR] | ' . (filter_input(
                    INPUT_SERVER,
                    'REMOTE_ADDR',
                    FILTER_VALIDATE_IP
                ) ?: 'UNKNOWN_IP') . ' | ' . __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый хост в APP_URL | Значение: $app_url"
            );
        }
    } else {
        /** @noinspection ForgottenDebugOutputInspection */
        error_log(
            date('H:i:s') . ' [ERROR] | ' . (filter_input(
                INPUT_SERVER,
                'REMOTE_ADDR',
                FILTER_VALIDATE_IP
            ) ?: 'UNKNOWN_IP') . ' | ' . __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Невалидный формат или протокол APP_URL | Значение: $app_url"
        );
    }
}

// 2. Если site_url ещё не установлен → используем данные из сервера
if (empty($config['site_url'])) {
    // Определяем протокол
    $scheme = 'http://';

    if (PHP_SAPI !== 'cli') {
        if (!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) {
            $scheme = 'https://';
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && in_array(
            strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']),
            ['https', 'h2'],
            true
        )) {
            $scheme = 'https://';
        } elseif (!empty($_SERVER['REQUEST_SCHEME']) && strtolower($_SERVER['REQUEST_SCHEME']) === 'https') {
            $scheme = 'https://';
        } elseif (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === 443) {
            $scheme = 'https://';
        }
    }

    // Определяем хост
    if (PHP_SAPI === 'cli') {
        $http_host = 'localhost';
    } else {
        $http_host = filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (!$http_host) {
            /** @noinspection HostnameSubstitutionInspection */
            $http_host = filter_var($_SERVER['SERVER_NAME'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        if (!$http_host || !preg_match('/^[a-z0-9.-]+$/i', $http_host)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Невалидный формат хоста'
            );
        }
    }

    // Собираем site_url
    $config['site_url'] = $scheme . $http_host . '/';

    // Добавляем порт, если он не стандартный
    if (PHP_SAPI !== 'cli' && !in_array($_SERVER['SERVER_PORT'] ?? 80, [80, 443], true)) {
        $config['site_url'] = preg_replace(
            '~^(https?://[^/]+)~',
            '$1:' . ($_SERVER['SERVER_PORT'] ?? 80) . '/',
            $config['site_url']
        );
    }
}

/**
 * Безопасное определение SCRIPT_NAME.
 */
$script_name = filter_input(INPUT_SERVER, 'SCRIPT_NAME', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!$script_name) {
    $script_name = filter_var($_SERVER['PHP_SELF'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

if (!$script_name) {
    throw new RuntimeException(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Не удалось определить SCRIPT_NAME | Рекомендация: проверьте настройки сервера'
    );
}

if (PHP_SAPI !== 'cli' && !preg_match('/^\/[a-zA-Z0-9._\-\/]*$/', $script_name)) {
    throw new RuntimeException(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный формат SCRIPT_NAME | Значение: $script_name"
    );
}

/**
 * Генерация полного URL сайта
 */
$base_path = dirname($script_name);

if ($base_path === '.') {
    $base_path = '/';
} elseif ($base_path === '') {
    $base_path = '/';
} elseif (!str_ends_with($base_path, '/')) {
    $base_path .= '/';
}

// Формируем окончательный URL
/** @noinspection PhpArrayWriteIsNotUsedInspection */
$config['site_url'] = rtrim($config['site_url'], '/') . $base_path;

/**
 * Определение site_dir с нормализацией пути.
 *
 * @details Используется realpath для получения абсолютного пути к директории.
 *          Выполняются дополнительные проверки:
 *          - Является ли путь директорией.
 *          - Доступна ли директория для чтения и записи.
 */
$config['site_dir'] = WORK_DIR;

// Если realpath вернул false, выбрасываем исключение
if (empty($config['site_dir'])) {
    throw new RuntimeException(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Не удалось определить директорию сайта | Рекомендация: проверьте права доступа или корректность пути'
    );
}

// Убедимся, что путь является директорией
if (!is_dir($config['site_dir'])) {
    throw new RuntimeException(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Указанный путь не является директорией | Путь: {$config['site_dir']}"
    );
}

// Проверяем доступность директории для чтения и записи
if (!is_readable($config['site_dir'])) {
    throw new RuntimeException(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Директория недоступна для чтения | Путь: {$config['site_dir']}"
    );
}
if (PHP_SAPI !== 'cli' && !is_writable($config['site_dir'])) {
    throw new RuntimeException(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Директория недоступна для записи | Путь: {$config['site_dir']}"
    );
}

// Добавляем завершающий слеш
$config['site_dir'] = rtrim($config['site_dir'], '/') . '/';

// Пути к директориям действий, языков, шаблонов
$config['action_dir'] = [
    $config['site_dir'] . 'var/Action',
    $config['site_dir'] . 'core/Action'
];
$config['language_dirs'] = [
    $config['site_dir'] . 'var/language',
    $config['site_dir'] . 'core/language'
];
$config['template_dirs'] = [
    $config['site_dir'] . 'var/templates',
    $config['site_dir'] . 'core/templates'
];

/**
 * Проверка существования и доступности директорий.
 */
$required_directories_write = [
    $config['site_dir'] . 'var/' . $config['gallery_dir'] . '/',
    $config['site_dir'] . 'var/' . $config['thumbnail_dir'] . '/',
    $config['site_dir'] . 'var/' . $config['avatar_dir'] . '/',
    $config['site_dir'] . 'var/log/',
    $config['site_dir'] . 'var/' . $config['cache']['cache_dir'] . '/',
];
$required_directories_read = array_merge(
    $config['action_dir'],
    $config['language_dirs'],
    $config['template_dirs']
);

$error_dir = [];

// Проверяем доступ на чтение
foreach ($required_directories_read as $dir) {
    if (!is_dir($dir)) {
        $error_dir[] = "Директория не существует (для чтения): $dir";
    } elseif (!is_readable($dir)) {
        $error_dir[] = "Нет прав на чтение: $dir";
    }
}

// Проверяем доступ на запись
foreach ($required_directories_write as $dir) {
    if (!is_dir($dir)) {
        $error_dir[] = "Директория не существует (для записи): $dir";
    } elseif (!is_writable($dir)) {
        $error_dir[] = "Нет прав на запись: $dir";
    }
}

// Если есть ошибки → выбрасываем исключение
if (!empty($error_dir)) {
    $error = implode(PHP_EOL ?? '\n', $error_dir);
    throw new RuntimeException(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Проблемы с доступом к директориям: ' . $error
    );
}
// =============================================================================
// КОНЕЦ БЛОКА АВТО-НАСТРОЕК
// =============================================================================
