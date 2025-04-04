<?php

/**
 * @file        include/common.php
 * @brief       Общие настройки, константы и функции для работы приложения.
 *
 * @throws      RuntimeException Если не удалось определить HTTP_HOST или его формат некорректен.
 *              Пример сообщения: "Некорректный формат HTTP_HOST | Значение: {$cookie_domain}".
 * @throws      RuntimeException Если не удалось создать директорию для логов или она недоступна для записи.
 *              Пример сообщения: "Директория недоступна для записи | Путь: " . LOG_DIR.
 * @throws      RuntimeException Если не удалось записать данные в лог-файл.
 *              Пример сообщения: "Не удалось записать данные в файл | Путь: {$log_file}".
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-04-02
 * @namespace   PhotoRigma\Include
 *
 * @details     Этот файл содержит общие настройки, константы и функции, которые используются в приложении:
 *              - Настройка сессий и куков для управления состоянием пользователя.
 *              - Константы для таблиц базы данных, упрощающие работу с SQL-запросами.
 *              - Регулярные выражения для валидации входных данных (логин, отображаемое имя, e-mail).
 *              - Функции для логирования ошибок и архивации старых логов:
 *                - Архивация старых логов: Сжимает логи старше недели в формате `.gz` и удаляет исходные файлы.
 *                - Логирование ошибок: Сохраняет ошибки в лог-файл.
 *
 * @section     Основные функции
 * - Настройка сессий и куков.
 * - Определение констант для таблиц базы данных.
 * - Валидация входных данных с использованием регулярных выражений.
 * - Логирование ошибок и архивация старых логов.
 *
 * @see         PhotoRigma::Include::archive_old_logs Функция для архивации старых логов.
 * @see         PhotoRigma::Include::log_in_file Функция для логирования ошибок.
 * @see         index.php Файл, который подключает common.php.
 *
 * @note        Этот файл является частью системы PhotoRigma и предоставляет базовые инструменты для работы приложения.
 *              Реализованы меры безопасности для предотвращения несанкционированного доступа и выполнения действий.
 *
 * @copyright   Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать, распространять,
 *              сублицензировать и/или продавать копии программного обеспечения, а также разрешать лицам, которым
 *              предоставляется данное программное обеспечение, делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые
 *              части программного обеспечения.
 */

namespace PhotoRigma\Include;

/** @var array $config */

// Предотвращение прямого вызова файла
use Exception;
use RuntimeException;

if (!defined('IN_GALLERY') || IN_GALLERY !== true) {
    error_log(
        date('H:i:s') . " [ERROR] | " . (filter_input(
            INPUT_SERVER,
            'REMOTE_ADDR',
            FILTER_VALIDATE_IP
        ) ?: 'UNKNOWN_IP') . " | " . __FILE__ . " | Попытка прямого вызова файла"
    );
    die("HACK!");
}

// =============================================================================
// КОНСТАНТЫ ДЛЯ СИСТЕМНЫХ НАСТРОЕК, ТАБЛИЦ БАЗ ДАННЫХ
// =============================================================================

define('DIE_IF_ERROR', true); ///< Останавливать ли скрипт во время серьезных ошибок? (True - останавливать)
define("LOG_DIR", "{$config['site_dir']}log/"); ///< Путь к директории для хранения логов

/// Что использовать как конец строки (для Win-серверов).
if (!defined('PHP_EOL')) {
    define('PHP_EOL', PHP_OS_FAMILY === 'Windows' ? "\r\n" : "\n");
}

define('TBL_CONFIG', '`config`'); ///< Таблица с настройками сервера
define('TBL_MENU', '`menu`'); ///< Таблица с пунктами меню
define('TBL_NEWS', '`news`'); ///< Таблица с новостями
define('TBL_CATEGORY', '`category`'); ///< Таблица со списком категорий на сервере
define('TBL_PHOTO', '`photo`'); ///< Таблица со списком фотографий на сервере
define('TBL_RATE_USER', '`rate_user`'); ///< Таблица с оценками фотографий от пользователей
define('TBL_RATE_MODER', '`rate_moder`'); ///< Таблица с оценками фотографий от модераторов
define('TBL_USERS', '`users`'); ///< Таблица пользователей
define('TBL_GROUP', '`groups`'); ///< Таблица групп пользователей с их правами
define(
    'TBL_LOG_QUERY',
    'query_logs'
); ///< Таблица для логирования медленных запросов, запросов без плейсхолдеров (имя не экранируется для совместимости с различными базами)
define('VIEW_RANDOM_PHOTO', '`random_photo`'); ///< Представление с одним случайным фото
define('VIEW_USERS_ONLINE', '`users_online`'); ///< Представление со списком онлайн пользователей
define('DEFAULT_GROUP', 1); ///< Группа по-умолчанию для новых пользователей
define('MAX_LOGIN_ATTEMPTS', 5); ///< Максимальное количество попыток входа в админку
define(
    'LOCKOUT_TIME',
    300
); ///< Время блокировки после превышения попыток входа в админку (в секундах, например, 5 минут)
define('DEFAULT_AVATAR', 'no_avatar.jpg'); ///< Константа для значения аватара по умолчанию
// Константы для настройки архивации логов
define('COMPRESSION_LEVEL', 9); ///< Уровень сжатия (максимальный)
define('LOG_LIFETIME_DAYS', '-7 days'); ///< Срок хранения логов
define(
    'MAX_LOG_SIZE',
    10485760
); ///< 10 MB - максимальный размер лога (предварительно вычисленное значение: 10 * 1024 * 1024 = 10485760)

/**
 * @def     REG_LOGIN
 * @brief   Регулярное выражение для проверки логина.
 * @details Допустимые символы: латинские буквы, цифры, подчеркивание (_), дефис (-).
 *          - Первый символ должен быть буквой или цифрой.
 *          - Последний символ должен быть буквой, цифрой или подчеркиванием.
 *          - Максимальная длина: 32 символа (1 символ для начала + до 30 символов в середине + 1 символ для конца).
 *          Читаемый вариант: /^[a-zA-Z0-9][a-zA-Z0-9_-]*[a-zA-Z0-9_]$/
 * @see     include/user.php Файл, где используется для валидации логина при регистрации.
 * @see     PhotoRigma::Classes::User Класс для управления пользователями.
 */
define(
    'REG_LOGIN',
    '/^[\x30-\x39\x41-\x5A\x61-\x7A][\x30-\x39\x41-\x5A\x61-\x7A\x5F\x2D]{0,30}[\x30-\x39\x41-\x5A\x61-\x7A\x5F]$/u'
);

/**
 * @def     REG_NAME
 * @brief   Регулярное выражение для проверки отображаемого имени.
 * @details Допустимые символы:
 *          - Любые буквы (Unicode), цифры, пробелы, дефисы (-), точки (.), запятые (,), восклицательные знаки (!),
 *          вопросительные знаки (?).
 *          - Максимальная длина: 100 символов.
 *          Читаемый вариант: /^[^\x00-\x1F\x7F<>&"'\\\/`=]{1,100}$/
 * @see     action/profile.php Обработчик для работы с профилями пользователей.
 * @see     PhotoRigma::Classes::User Класс для управления пользователями.
 */
define('REG_NAME', '/^[\p{L}\p{N}\p{Zs}\-\.\,\!\?]{1,100}$/u');

/**
 * @def     REG_EMAIL
 * @brief   Регулярное выражение для проверки E-mail.
 * @details Допустимые символы:
 *          - Латинские буквы, цифры, точки (.), дефисы (-), подчеркивания (_) и знаки процента (%).
 *          - Доменное имя должно содержать хотя бы одну точку (.).
 *          - Минимальная длина доменной части: 2 символа.
 *          Читаемый вариант: /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/
 * @see     include/user.php Файл, где используется для валидации email при регистрации.
 * @see     PhotoRigma::Classes::User Класс для управления пользователями.
 */
define(
    'REG_EMAIL',
    '/^[\x30-\x39\x41-\x5A\x61-\x7A\x2E\x2D\x5F\x25\x2B]+@[\x30-\x39\x41-\x5A\x61-\x7A\x2E\x2D]+\.[\x41-\x5A\x61-\x7A\xC0-\xFF]{2,}$/u'
);

/**
 * @brief   Безопасная проверка HTTP_HOST.
 * @details Используются различные источники данных для определения HTTP_HOST:
 *          - $_SERVER['HTTP_HOST'] (основной источник).
 *          - $_SERVER['SERVER_NAME'] (резервный источник).
 *          Выполняется валидация формата домена для предотвращения атак через заголовки.
 *
 * @var string|null $cookie_domain
 * @brief   Домен, используемый для настройки куков.
 * @details Значение извлекается из $_SERVER['HTTP_HOST'] с применением фильтрации для защиты от вредоносных данных.
 *          Если значение недоступно или некорректно, используется резервный источник $_SERVER['SERVER_NAME'].
 */
$cookie_domain = filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Если HTTP_HOST не установлен, пробуем SERVER_NAME
if (!$cookie_domain) {
    $cookie_domain = filter_var($_SERVER['SERVER_NAME'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

// Если HTTP_HOST всё ещё не установлен, выбрасываем исключение
if (!$cookie_domain) {
    throw new RuntimeException(
        __FILE__ . ":" . __LINE__ . " (" . (__FUNCTION__ ?: 'global') . ") | Не удалось определить HTTP_HOST | Проверьте настройки сервера"
    );
}
// Валидация формата домена
if (!preg_match('/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i', $cookie_domain)) {
    throw new RuntimeException(
        __FILE__ . ":" . __LINE__ . " (" . (__FUNCTION__ ?: 'global') . ") | Некорректный формат HTTP_HOST | Значение: $cookie_domain"
    );
}

/**
 * @brief   Настройка параметров куков для сессий.
 * @details Используются текущие параметры куков, полученные через session_get_cookie_params().
 *          Эти параметры применяются для настройки сессий с помощью session_set_cookie_params().
 *
 *
 * @brief   Текущие параметры куков, полученные через session_get_cookie_params().
 * @details Содержит следующие ключи:
 *          - lifetime: Время жизни куки в секундах.
 *          - path: Путь, для которого действует куки.
 *          - domain: Домен, для которого действует куки.
 *          - secure: Флаг, указывающий, что куки должны передаваться только по HTTPS.
 *          - httponly: Флаг, указывающий, что куки доступны только через HTTP(S), но не через JavaScript.
 */
$cur_cookie = session_get_cookie_params();

// Настройка параметров куков для сессий
session_set_cookie_params(
    $cur_cookie['lifetime'], // Время жизни куки
    $cur_cookie['path'],     // Путь, для которого действует куки
    $cookie_domain,          // Домен, для которого действует куки
    $cur_cookie['secure'],   // Куки передаются только по HTTPS
    $cur_cookie['httponly']  // Куки недоступны через JavaScript
);

/**
 * Инициализация сессии.
 *
 * @details Запускает сессию после настройки параметров куков.
 */
session_start();

/**
 * @brief   Архивирует старые логи, сжимая их в формат .gz и удаляя исходные файлы.
 *
 * @details Сжимает логи старше недели в формате .gz и удаляет исходные файлы.
 *          Требует расширение zlib для работы. Если возникают проблемы (например,
 *          отсутствие расширения zlib или доступа к файлам), ошибки логируются
 *          через стандартную PHP-функцию error_log, но выполнение скрипта не прерывается.
 *
 *          Последовательность действий:
 *          - Проверка наличия расширения zlib.
 *          - Проверка доступности директории логов.
 *          - Поиск файлов логов с расширением .txt.
 *          - Определение даты неделю назад.
 *          - Обработка каждого файла: проверка даты, сжатие, удаление исходного файла.
 *          - Логирование ошибок через error_log при возникновении проблем.
 *
 * @note    Используются константы:
 *       - COMPRESSION_LEVEL: Уровень сжатия (максимальный, значение 9).
 *       - LOG_LIFETIME_DAYS: Срок хранения логов ('-7 days').
 *       Требуется расширение zlib для работы функции.
 *
 * @warning Функция может пропустить файлы при возникновении ошибок (например,
 *          если файл недоступен для чтения или записи).
 *
 * @return void Функция ничего не возвращает.
 *
 * "Тихая" архивация старых логов:
 * @code
 * archive_old_logs();
 * @endcode
 */
function archive_old_logs(): void
{
    // Проверяем, подключено ли расширение zlib
    if (!extension_loaded('zlib')) {
        error_log(
            date(
                'H:i:s'
            ) . " [ERROR] | UNKNOWN_IP | " . __FILE__ . ":" . __LINE__ . " (" . __FUNCTION__ . ") | Расширение zlib не подключено"
        );
        return;
    }

    // Проверяем существование директории логов
    if (!is_dir(LOG_DIR) || !is_writable(LOG_DIR)) {
        error_log(
            date('H:i:s') . " [ERROR] | " . (filter_input(
                INPUT_SERVER,
                'REMOTE_ADDR',
                FILTER_VALIDATE_IP
            ) ?: 'UNKNOWN_IP') . " | " . __FILE__ . ":" . __LINE__ . " (" . __FUNCTION__ . ") | Директория логов отсутствует или недоступна для записи | Путь: " . LOG_DIR
        );
        return;
    }

    // Получаем список файлов логов с расширением .txt
    $log_files = glob(LOG_DIR . '*_log.txt', GLOB_NOSORT);
    if (empty($log_files)) {
        return; // Если файлов нет, выходим
    }

    // Определяем дату неделю назад
    $week_ago = strtotime(LOG_LIFETIME_DAYS);
    foreach ($log_files as $file) {
        // Извлекаем дату из имени файла
        $file_name = basename($file);
        if (preg_match('/^(\d{4}_\d{2}_\d{2})_log\.txt$/', $file_name, $matches)) {
            $file_date = strtotime(str_replace('_', '-', $matches[1])); // Преобразуем дату в timestamp

            // Если файл старше недели
            if ($file_date !== false && $file_date < $week_ago) {
                // Создаем имя архива
                $archive_file = $file . '.gz';

                // Читаем содержимое файла
                $file_content = file_get_contents($file);
                if ($file_content === false) {
                    error_log(
                        date('H:i:s') . " [ERROR] | " . (filter_input(
                            INPUT_SERVER,
                            'REMOTE_ADDR',
                            FILTER_VALIDATE_IP
                        ) ?: 'UNKNOWN_IP') . " | " . __FILE__ . ":" . __LINE__ . " (" . __FUNCTION__ . ") | Не удалось прочитать файл | Путь: $file"
                    );
                    continue; // Пропускаем этот файл и переходим к следующему
                }

                // Сжимаем файл
                $gz_handle = gzopen($archive_file, 'w' . COMPRESSION_LEVEL);
                if ($gz_handle === false) {
                    error_log(
                        date('H:i:s') . " [ERROR] | " . (filter_input(
                            INPUT_SERVER,
                            'REMOTE_ADDR',
                            FILTER_VALIDATE_IP
                        ) ?: 'UNKNOWN_IP') . " | " . __FILE__ . ":" . __LINE__ . " (" . __FUNCTION__ . ") | Не удалось создать архив | Путь: $archive_file"
                    );
                    continue; // Пропускаем этот файл и переходим к следующему
                }

                if (gzwrite($gz_handle, $file_content) === false) {
                    gzclose($gz_handle);
                    error_log(
                        date('H:i:s') . " [ERROR] | " . (filter_input(
                            INPUT_SERVER,
                            'REMOTE_ADDR',
                            FILTER_VALIDATE_IP
                        ) ?: 'UNKNOWN_IP') . " | " . __FILE__ . ":" . __LINE__ . " (" . __FUNCTION__ . ") | Не удалось записать данные в архив | Путь: $archive_file"
                    );
                    continue; // Пропускаем этот файл и переходим к следующему
                }
                gzclose($gz_handle);

                // Удаляем исходный файл
                if (!unlink($file)) {
                    error_log(
                        date('H:i:s') . " [ERROR] | " . (filter_input(
                            INPUT_SERVER,
                            'REMOTE_ADDR',
                            FILTER_VALIDATE_IP
                        ) ?: 'UNKNOWN_IP') . " | " . __FILE__ . ":" . __LINE__ . " (" . __FUNCTION__ . ") | Не удалось удалить исходный файл | Путь: $file"
                    );
                }
            }
        }
    }
}

/**
 * @brief   Записывает сообщение об ошибке в лог-файл и, при необходимости, завершает выполнение скрипта.
 *
 * @details Записывает сообщение об ошибке в лог-файл с возможностью завершения выполнения скрипта.
 *          Последовательность действий:
 *          - Проверка существования директории логов (создание, если отсутствует).
 *          - Ограничение размера файла логов (архивация при превышении MAX_LOG_SIZE с использованием расширения zlib
 *          или без него).
 *          - Подготовка текста для записи (включая трассировку, если включён DEBUG_GALLERY).
 *          - Безопасная запись в файл через потоки с блокировкой (flock).
 *          - Завершение работы скрипта при необходимости (если $die === true и DIE_IF_ERROR установлено).
 *          Если возникают проблемы (например, отсутствие доступа к файлам), ошибки логируются через стандартную
 *          PHP-функцию error_log.
 *
 * @param string $txt Сообщение для записи в лог. Не должно быть пустым.
 * @param bool   $die Флаг, указывающий, следует ли завершить выполнение скрипта после записи. Значение по умолчанию —
 *                    false. Этот параметр скоро будет исключён из функции как устаревший.
 *
 * @return bool True, если запись успешна, иначе False.
 *
 * @throws RuntimeException Возникает при проблемах с созданием директории, доступом к файлам, чтением/записью данных.
 * @throws Exception Любые непредвиденные ошибки.
 *
 * @note    Используются константы:
 *       - MAX_LOG_SIZE: Максимальный размер файла лога (10 MB).
 *       - COMPRESSION_LEVEL: Уровень сжатия (максимальный, значение 9).
 *       Требуется расширение zlib для архивации логов.
 *
 * @warning Функция может завершить выполнение скрипта, если установлен флаг $die и константа DIE_IF_ERROR.
 *
 * Логирование ошибки без завершения скрипта:
 * @code
 * log_in_file("Ошибка при обработке данных.");
 * @endcode
 * Логирование критической ошибки с завершением скрипта (deprecated!):
 * @code
 * log_in_file("Критическая ошибка!", true);
 * @endcode
 */
function log_in_file(string $txt, bool $die = false): bool
{
    static $log_in_process = false; // Статический флаг для предотвращения рекурсии
    if ($log_in_process) {
        return false;
    }
    $log_in_process = true;
    try {
        // Проверка существования директории для логов
        if (!is_dir(LOG_DIR)) {
            if (!mkdir($concurrent_directory = LOG_DIR, 0755, true) && !is_dir($concurrent_directory)) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . __FUNCTION__ . ") | Не удалось создать директорию | Путь: " . LOG_DIR
                );
            }
            // Проверяем права доступа после создания
            if (!is_writable(LOG_DIR)) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . __FUNCTION__ . ") | Созданная директория недоступна для записи | Путь: " . LOG_DIR
                );
            }
        }

        // Проверка прав доступа к директории логов
        if (!is_writable(LOG_DIR)) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . __FUNCTION__ . ") | Директория недоступна для записи | Путь: " . LOG_DIR
            );
        }

        // Определение имени файла лога
        $log_file = LOG_DIR . date('Y_m_d') . '_log.txt';

        // Ограничение размера файла логов
        if (is_file($log_file) && filesize($log_file) > MAX_LOG_SIZE) {
            $backup_file = $log_file . '_bak-' . date('Y_m_d_H_i_s') . '.gz';
            if (extension_loaded('zlib')) {
                $gz_handle = gzopen($backup_file, 'w' . COMPRESSION_LEVEL);
                if ($gz_handle) {
                    $file_content = file_get_contents($log_file);
                    if ($file_content === false) {
                        throw new RuntimeException(
                            __FILE__ . ":" . __LINE__ . " (" . __FUNCTION__ . ") | Не удалось прочитать содержимое файла | Путь: $log_file"
                        );
                    }
                    gzwrite($gz_handle, $file_content);
                    gzclose($gz_handle);
                    unlink($log_file); // Удаляем исходный файл после сжатия
                }
            } else {
                // Меняем расширение файла с .gz на .txt
                $backup_file = pathinfo($backup_file, PATHINFO_DIRNAME) . '/' . pathinfo(
                    $backup_file,
                    PATHINFO_FILENAME
                ) . '.txt';
                rename($log_file, $backup_file); // Без сжатия
            }

            // Проверяем успешность создания архива
            if (!file_exists($backup_file)) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . __FUNCTION__ . ") | Не удалось создать архив | Путь: $backup_file"
                );
            }
        }

        // Подготовка текста для записи в лог
        $level = $die ? 'ERROR' : 'WARNING';
        $remote_ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
        $write_txt = date('H:i:s') . " [$level] | " . ($remote_ip ?: 'UNKNOWN_IP') . ' | ' . $txt . PHP_EOL;

        // Добавление трассировки, если DEBUG_GALLERY включен
        if (defined('DEBUG_GALLERY')) {
            $trace_depth = match (true) {
                DEBUG_GALLERY === false                                           => 0,
                is_int(DEBUG_GALLERY) && DEBUG_GALLERY >= 1 && DEBUG_GALLERY <= 9 => DEBUG_GALLERY,
                default                                                           => 5, // Глубина трассировки по умолчанию
            };
            if ($trace_depth > 0) {
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, $trace_depth + 1);
                array_shift($backtrace);
                $trace_info = [];
                foreach ($backtrace as $index => $trace) {
                    $step_number = $index + 1;
                    $args = array_map(static function ($arg) {
                        $arg_str = is_string($arg) ? $arg : json_encode(
                            $arg,
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                        );
                        return mb_strlen($arg_str, 'UTF-8') > 80 ? mb_substr(
                            $arg_str,
                            0,
                            80,
                            'UTF-8'
                        ) . '...' : $arg_str;
                    }, $trace['args'] ?? []);
                    $trace_info[] = sprintf(
                        "Шаг %d:" . PHP_EOL . "  Файл: %s" . PHP_EOL . "  Строка: %s" . PHP_EOL . "  Функция: %s" . PHP_EOL . "  Аргументы: %s",
                        $step_number,
                        $trace['file'] ?: 'неизвестный файл',
                        $trace['line'] ?: 'неизвестная строка',
                        $trace['function'] ?: 'неизвестная функция',
                        json_encode($args, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                    );
                }
                $write_txt .= "Трассировка:" . PHP_EOL . implode(PHP_EOL . PHP_EOL, $trace_info) . PHP_EOL;
            }
        }

        // Безопасная запись в файл через потоки
        $handle = fopen($log_file, 'ab');
        if ($handle) {
            flock($handle, LOCK_EX);
            if (fwrite($handle, $write_txt) === false) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . __FUNCTION__ . ") | Не удалось записать данные в файл | Путь: $log_file"
                );
            }
            flock($handle, LOCK_UN);
            fclose($handle);
        } else {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . __FUNCTION__ . ") | Не удалось открыть файл для записи | Путь: $log_file"
            );
        }

        // Завершение работы скрипта при необходимости
        if ($die && DIE_IF_ERROR) {
            $safe_output = htmlspecialchars($write_txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            die($safe_output);
        }
    } catch (RuntimeException $e) {
        error_log(
            date('H:i:s') . " [ERROR] | " . (filter_input(
                INPUT_SERVER,
                'REMOTE_ADDR',
                FILTER_VALIDATE_IP
            ) ?: 'UNKNOWN_IP') . " | " . __FILE__ . ":" . __LINE__ . " (" . __FUNCTION__ . ") | Ошибка во время выполнения | Сообщение: " . $e->getMessage(
            )
        );
        $log_in_process = false;
        return false;
    } catch (Exception $e) {
        error_log(
            date('H:i:s') . " [ERROR] | " . (filter_input(
                INPUT_SERVER,
                'REMOTE_ADDR',
                FILTER_VALIDATE_IP
            ) ?: 'UNKNOWN_IP') . " | " . __FILE__ . ":" . __LINE__ . " (" . __FUNCTION__ . ") | Непредвиденная ошибка | Сообщение: " . $e->getMessage(
            )
        );
        $log_in_process = false;
        return false;
    }
    $log_in_process = false;
    return true;
}
