<?php

/**
 * @file        config.php
 * @brief       Редактируемые настройки сервера.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-02-07
 * @namespace   PhotoRigma
 *
 * @details     Этот файл содержит настройки, необходимые для конфигурации приложения:
 *              - Указываются внешние (URL) и внутренние (на диске) пути к корню и подпапкам проекта.
 *              - Указываются параметры подключения к базе данных (например, хост, имя пользователя, пароль, имя базы данных).
 *              - Файл используется для централизованного управления конфигурацией приложения.
 *
 *              Важно: Этот файл должен быть защищён от прямого доступа через веб-сервер.
 *
 * @see         PhotoRigma::Classes::Work::$config Массив, куда копируются настройки из $config.
 * @see         index.php Файл, который подключает config.php.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в настройке приложения.
 *
 * @todo        Добавить защиту файла через `.htaccess` или проверку константы.
 *
 * @copyright   Copyright (c) 2025 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать, распространять, сублицензировать
 *              и/или продавать копии программного обеспечения, а также разрешать лицам, которым предоставляется данное
 *              программное обеспечение, делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые части
 *                программного обеспечения.
 */

namespace PhotoRigma;

// Предотвращение прямого вызова файла
if (!defined('IN_GALLERY') || IN_GALLERY !== true) {
    error_log(
        date('H:i:s') .
        " [ERROR] | " .
        (filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP) ?: 'UNKNOWN_IP') .
        " | " . __FILE__ . " | Попытка прямого вызова файла"
    );
    die("HACK!");
}

/**
 * @var array $config
 * @brief Массив с настройками сервера.
 * @details Содержит как редактируемые пользователем параметры (например, параметры БД), так и автоматически генерируемые параметры (например, URL сайта).
 *          После инициализации массив $config передаётся в свойство PhotoRigma::Classes::Work::$config.
 * @see PhotoRigma::Classes::Work::$config
 */
$config = [];

// =============================================================================
// НАЧАЛО РЕДАКТИРУЕМОЙ ЧАСТИ НАСТРОЕК
// =============================================================================

// =============================================================================
// Настройки подключения к базы данных
// =============================================================================
/**
 * @brief Параметры подключения к базе данных.
 * @details Включает следующие параметры:
 *          - dbtype: Тип базы данных (по умолчанию: mysql, можно использовать pgsql).
 *          - dbhost: Сервер основной базы данных (по умолчанию: localhost).
 *          - dbport: Порт сервера основной базы данных (оставить пустым для значения по умолчанию, например: 3306 для MySQL).
 *          - dbsock: Путь к сокету (используется, если хост не указан, например: /var/run/mysqld/mysqld.sock).
 *          - dbuser: Имя пользователя в БД (например: root).
 *          - dbpass: Пароль пользователя БД (например: my_secure_password).
 *          - dbname: Имя базы данных (например: photorigma).
 * @see include/db.php Файл, где используется этот массив для подключения к БД.
 */
$config['db'] = [
    'dbtype' => 'mysql',       ///< Тип базы данных (по умолчанию: mysql, можно использовать pgsql)
    'dbhost' => 'localhost',   ///< Сервер основной базы данных (по умолчанию: localhost)
    'dbport' => '',            ///< Порт сервера основной базы данных (оставить пустым для значения по умолчанию, например: 3306 для MySQL)
    'dbsock' => '/var/run/mysqld/mysqld.sock', ///< Путь к сокету (используется, если хост не указан, например: /var/run/mysqld/mysqld.sock)
    'dbuser' => 'root',        ///< Имя пользователя в БД (например: root)
    'dbpass' => '',            ///< Пароль пользователя БД (например: my_secure_password)
    'dbname' => 'photorigma',  ///< Имя базы данных (например: photorigma)
];

// =============================================================================
// Настройки папок проекта
// =============================================================================
$config['gallery_folder']   = 'gallery';     ///< Имя папки для хранения фотографий (указывается относительно корня проекта).
$config['thumbnail_folder'] = 'thumbnail';   ///< Имя папки для хранения эскизов фотографий (указывается относительно корня проекта).
$config['avatar_folder']    = 'avatar';      ///< Имя папки для хранения аватаров пользователей (указывается относительно корня проекта).

// =============================================================================
// КОНЕЦ РЕДАКТИРУЕМОЙ ЧАСТИ НАСТРОЕК
// =============================================================================

// =============================================================================
// НАЧАЛО БЛОКА АВТО-НАСТРОЕК
// =============================================================================
/**
 * Определение протокола (HTTP/HTTPS).
 * @details Проверяются различные способы определения протокола:
 *          - Переменная $_SERVER['HTTPS'] (учитываются значения 'on', '1', и случайный регистр).
 *          - Заголовок X-Forwarded-Proto (учитываются значения 'https', 'h2', и случайный регистр).
 *          - Порт сервера (443 для HTTPS).
 *          - Переменная $_SERVER['REQUEST_SCHEME'].
 *          Если ни один из способов не дал результата, используется HTTP.
 */
$config['site_url'] = 'http://'; // Резервное значение

// Проверка $_SERVER['HTTPS']
if (
    (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') || // Стандартное значение
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === '1')                // Числовое значение
) {
    $config['site_url'] = 'https://';
}

// Проверка заголовка X-Forwarded-Proto
elseif (
    !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
    in_array(strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']), ['https', 'h2'])
) {
    $config['site_url'] = 'https://';
}

// Проверка порта сервера
elseif (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
    $config['site_url'] = 'https://';
}

// Проверка $_SERVER['REQUEST_SCHEME']
elseif (
    !empty($_SERVER['REQUEST_SCHEME']) &&
    strtolower($_SERVER['REQUEST_SCHEME']) === 'https'
) {
    $config['site_url'] = 'https://';
}

/**
 * Безопасная проверка HTTP_HOST.
 * @details Используются различные источники данных для определения HTTP_HOST:
 *          - $_SERVER['HTTP_HOST'] (основной источник).
 *          - $_SERVER['SERVER_NAME'] (резервный источник).
 *          Выполняется валидация формата домена для предотвращения атак через заголовки.
 */
$http_host = filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Если HTTP_HOST не установлен, пробуем SERVER_NAME
if (!$http_host) {
    $http_host = filter_var($_SERVER['SERVER_NAME'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

// Если HTTP_HOST всё ещё не установлен, выбрасываем исключение
if (!$http_host) {
    throw new \RuntimeException("config.php(): Не удалось определить HTTP_HOST. Проверьте настройки сервера.");
}

// Валидация формата домена
if (!preg_match('/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i', $http_host)) {
    throw new \RuntimeException("config.php(): Некорректный формат HTTP_HOST: {$http_host}");
}

/**
 * Безопасное определение SCRIPT_NAME.
 * @details Используются различные источники данных для определения SCRIPT_NAME:
 *          - $_SERVER['SCRIPT_NAME'] (основной источник).
 *          - $_SERVER['PHP_SELF'] (резервный источник).
 *          Выполняется валидация формата пути для предотвращения атак через заголовки.
 */
$script_name = filter_input(INPUT_SERVER, 'SCRIPT_NAME', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Если SCRIPT_NAME не установлен, пробуем PHP_SELF
if (!$script_name) {
    $script_name = filter_var($_SERVER['PHP_SELF'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

// Если SCRIPT_NAME всё ещё не установлен, выбрасываем исключение
if (!$script_name) {
    throw new \RuntimeException("config.php(): Не удалось определить SCRIPT_NAME. Проверьте настройки сервера.");
}

// Валидация формата пути
if (!preg_match('/^\/[a-zA-Z0-9._\-\/]*$/', $script_name)) {
    throw new \RuntimeException("config.php(): Некорректный формат SCRIPT_NAME: {$script_name}");
}

/**
 * Генерация полного URL сайта.
 * @details Формируется на основе протокола, HTTP_HOST и SCRIPT_NAME.
 */
$base_path = dirname($script_name); // Получаем директорию скрипта

// Если директория равна '.', заменяем её на '/'
if ($base_path === '.') {
    $base_path = '/';
}

// Убедимся, что путь заканчивается слешем
if (substr($base_path, -1) !== '/') {
    $base_path .= '/';
}

// Формируем полный URL
$config['site_url'] .= $http_host . $base_path;

/**
 * Определение site_dir с нормализацией пути.
 * @details Используется realpath для получения абсолютного пути к директории.
 *          Выполняются дополнительные проверки:
 *          - Является ли путь директорией.
 *          - Доступна ли директория для чтения и записи.
 */
$config['site_dir'] = realpath(dirname(__FILE__));

// Если realpath вернул false, выбрасываем исключение
if ($config['site_dir'] === false) {
    throw new \RuntimeException("config.php(): Не удалось определить директорию сайта. Проверьте права доступа или корректность пути.");
}

// Убедимся, что путь является директорией
if (!is_dir($config['site_dir'])) {
    throw new \RuntimeException("config.php(): Указанный путь не является директорией: {$config['site_dir']}");
}

// Проверяем доступность директории для чтения и записи
if (!is_readable($config['site_dir'])) {
    throw new \RuntimeException("config.php(): Директория недоступна для чтения: {$config['site_dir']}");
}
if (!is_writable($config['site_dir'])) {
    throw new \RuntimeException("config.php(): Директория недоступна для записи: {$config['site_dir']}");
}

// Добавляем завершающий слеш
if (substr($config['site_dir'], -1) !== '/') {
    $config['site_dir'] .= '/';
}

/**
 * Определение inc_dir.
 * @details Указывает путь к директории include/, содержащей вспомогательные классы и функции.
 */
$config['inc_dir'] = $config['site_dir'] . 'include/';

/**
 * Проверка существования и доступности директорий.
 * @details Проверяются директории, указанные в настройках ($gallery_folder, $thumbnail_folder, $avatar_folder).
 */
$required_directories = [
    $config['site_dir'] . $config['gallery_folder'] . '/',
    $config['site_dir'] . $config['thumbnail_folder'] . '/',
    $config['site_dir'] . $config['avatar_folder'] . '/',
    $config['inc_dir'],
];
foreach ($required_directories as $dir) {
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new \RuntimeException("config.php(): Директория отсутствует или недоступна для записи: {$dir}");
    }
}
// =============================================================================
// КОНЕЦ БЛОКА АВТО-НАСТРОЕК
// =============================================================================
