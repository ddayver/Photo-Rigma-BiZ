<?php

/**
 * @file        index.php
 * @brief       Точка входа в приложение PhotoRigma.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-02-07
 * @namespace   PhotoRigma
 *
 * @details     Этот файл является точкой входа в приложение и выполняет следующие задачи:
 *              - Подключает конфигурационный файл (`config.php`) и проверяет его доступность.
 *              - Проверяет наличие и доступность обязательных файлов (например, `common.php`, `db.php`).
 *              - Создаёт объекты основных классов (`Database`, `Work`, `Template`, `User`).
 *              - Обрабатывает действия пользователя через параметр `action`. Значение, получаемое из запроса в URL,
 *                указывает на действие, которое должно выполнить приложение.
 *              - Выводит шаблонные элементы страницы (шапку, подвал и содержимое).
 *              - Реализует централизованную систему обработки ошибок и их логирования на уровне ядра приложения.
 *
 * @see         config.php Файл конфигурации, содержащий параметры приложения.
 * @see         include/common.php Файл, содержащий глобальные константы и функции.
 * @see         \\PhotoRigma\\Classes\\Database Класс для работы с базой данных.
 * @see         \\PhotoRigma\\Classes\\Work Класс для выполнения вспомогательных операций.
 * @see         \\PhotoRigma\\Classes\\Template Класс для генерации HTML-контента.
 * @see         \\PhotoRigma\\Classes\\User Класс для управления пользователями.
 * @see         \\PhotoRigma\\Include\\archive_old_logs Функция для архивации старых логов.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в запуске и работе приложения.
 *
 * @copyright   Copyright (c) 2025 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать, распространять, сублицензировать
 *              и/или продавать копии программного обеспечения, а также разрешать лицам, которым предоставляется данное
 *              программное обеспечение, делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые части
 *                программного обеспечения.
 */

declare(strict_types=1);

namespace PhotoRigma;

// Импорт пространств имён
use PhotoRigma\Classes\Database;
use PhotoRigma\Classes\Work;
use PhotoRigma\Classes\Template;
use PhotoRigma\Classes\User;
use PhotoRigma\Classes\Language;
use PhotoRigma\Classes\Action;

// Импортируем функции из PhotoRigma\Include
use function PhotoRigma\Include\archive_old_logs;
use function PhotoRigma\Include\log_in_file;

/** @def IN_GALLERY
 * @brief Используется для проверки, что файлы подключены через index.php, а не вызваны напрямую.
 */
define('IN_GALLERY', true);

/** @def DEBUG_GALLERY
 * @brief Включение режима отладки (более подробный вывод информации об ошибках).
 * @details Используется для включения режима отладки, который влияет на логирование ошибок.
 * @see \\PhotoRigma\\Include\\log_in_file Функция для логирования ошибок.
 */
define('DEBUG_GALLERY', false);

try {

    /**
     * Безопасное подключение конфигурационного файла.
     * @details Файл config.php содержит настройки, редактируемые пользователем, такие как параметры подключения к базе данных,
     *          пути к директориям, настройки тем и другие важные параметры.
     * @see config.php Файл конфигурации приложения.
     * @see $config Массив, инициализируемый в файле config.php, содержащий все настройки приложения.
     */
    if (!is_file('config.php')) {
        throw new \RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__FUNCTION__ ?: 'global') . ") | Конфигурационный файл отсутствует или не является файлом | Путь: config.php"
        );
    }
    if (!is_readable('config.php')) {
        throw new \RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__FUNCTION__ ?: 'global') . ") | Конфигурационный файл существует, но недоступен для чтения | Путь: config.php"
        );
    }
    require_once 'config.php'; // Подключаем файл редактируемых пользователем настроек

    /**
     * @var array $required_files
     * @brief Список обязательных файлов для подключения.
     * @details Содержит пути к файлам, которые необходимы для работы приложения.
     */
    $required_files = [
        $config['inc_dir'] . 'common.php',
        $config['inc_dir'] . 'db.php',
        $config['inc_dir'] . 'work_helper.php',
        $config['inc_dir'] . 'work_corelogic.php',
        $config['inc_dir'] . 'work_image.php',
        $config['inc_dir'] . 'work_template.php',
        $config['inc_dir'] . 'work_security.php',
        $config['inc_dir'] . 'work.php',
        $config['inc_dir'] . 'template.php',
        $config['inc_dir'] . 'user.php',
    ];

    // Массив для хранения ошибок
    $errors = [];

    // Проверяем каждый файл
    foreach ($required_files as $file) {
        if (!is_file($file)) {
            $errors[] = "Файл отсутствует или не является файлом: {$file}";
        } elseif (!is_readable($file)) {
            $errors[] = "Файл существует, но недоступен для чтения: {$file}";
        }
    }

    // Если есть ошибки, логируем их и завершаем выполнение скрипта
    if (!empty($errors)) {
        $error_details = implode(PHP_EOL, $errors); // Подробности ошибок
        throw new \RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__FUNCTION__ ?: 'global') . ") | Проверка обязательных файлов завершилась ошибкой | Ошибки:" . PHP_EOL . $error_details
        );
    }

    // Подключаем все файлы из массива $required_files
    foreach ($required_files as $file) {
        require_once $file;
    }

    /** @var \\PhotoRigma\\Classes\\Database $db
     * @brief Создание объекта класса Database для работы с основной БД.
     * @details Инициализируется через конструктор с параметрами из массива $config['db'].
     * @see \\PhotoRigma\\Classes\\Database Класс для работы с базой данных.
     * @see include/db.php Файл, содержащий реализацию класса Database.
     * @see $config Массив конфигурации, используемый для подключения к БД.
     */
    $db = new Database($config['db']);

    /** @var \\PhotoRigma\\Classes\\Work $work
     * @brief Создание объекта класса Work.
     * @details Используется для выполнения различных вспомогательных операций.
     * @see \\PhotoRigma\\Classes\\Work Класс для выполнения вспомогательных операций.
     * @see include/work.php Файл, содержащий реализацию класса Work.
     * @see \\PhotoRigma\\Classes\\Work::$config Свойство, хранящее конфигурацию приложения.
     * @see \\PhotoRigma\\Classes\\Work::check_input() Метод для проверки входных данных.
     * @see \\PhotoRigma\\Classes\\Work::url_check() Метод для проверки URL на наличие вредоносного кода.
     */
    $work = new Work($db, $config);

    /**
     * Очищаем значение массива $config[], чтобы предотвратить его использование напрямую.
     * Все настройки теперь доступны только через свойство \PhotoRigma\Classes\Work::$config.
     * @see \PhotoRigma\Classes\Work::$config
     */
    unset($config);

    /** @var \\PhotoRigma\\Classes\\Template $template
     * @brief Создание объекта класса Template.
     * @details Используется для генерации HTML-контента страниц.
     * @see \\PhotoRigma\\Classes\\Template Класс для работы с HTML-шаблонами.
     * @see include/template.php Файл, содержащий реализацию класса Template.
     * @see \\PhotoRigma\\Classes\\Work::$config Свойство, хранящее конфигурацию для шаблонов.
     * @see \\PhotoRigma\\Classes\\Template::create_template() Метод для создания содержимого страницы.
     * @see \\PhotoRigma\\Classes\\Template::page_header() Метод для добавления шапки страницы.
     * @see \\PhotoRigma\\Classes\\Template::page_footer() Метод для добавления подвала страницы.
     * @see \\PhotoRigma\\Classes\\Template::$content Свойство, хранящее содержимое всей страницы.
     * @see $title Переменная, используемая для добавления текста к заголовку страницы.
     */
    $template = new Template(
        $work->config['site_url'],
        $work->config['site_dir'],
        $work->config['themes']
    );

    /** @var \\PhotoRigma\\Classes\\User $user
     * @brief Создание объекта класса User.
     * @details Используется для управления пользователями системы.
     * @see \\PhotoRigma\\Classes\\User Класс для управления пользователями.
     * @see include/user.php Файл, содержащий реализацию класса User.
     */
    $user = new User();

    /** @var bool $header_footer
     * @brief Флаг: выводить ли заголовок и подвал страницы.
     */
    $header_footer = true;

    /** @var bool $template_output
     * @brief Флаг: выводить ли обработанный шаблон.
     */
    $template_output = true;

    /** @var string|null $title
     * @brief Добавление текста к заголовку страницы.
     */
    $title = null;

    /**
     * @var string $action
     * @brief Действие, которое необходимо выполнить.
     * @details Возможные значения определяются динамически в зависимости от доступных файлов в директории 'action/'.
     * @example $action
     * $_GET['action'] = 'profile'; // Пример вызова действия 'profile'
     */
    $action = 'main'; // Значение по умолчанию

    /**
     * Обработка действия, указанного в параметре 'action'.
     * - Проверяется существование и безопасность параметра 'action'.
     * - Формируется путь к файлу действия.
     * - Если файл не существует или недоступен, используется файл по умолчанию ('main.php').
     */
    if (
        $work->check_input($_GET, 'action', ['isset' => true, 'empty' => true]) && // Проверяем, что параметр 'action' существует и не пустой
        $_GET['action'] !== 'index' &&                                             // Исключаем значение 'index'
        !$work->url_check()                                                        // Проверяем URL на наличие вредоносного кода
    ) {
        // Используем более безопасный метод фильтрации входных данных
        $action = filter_var($_GET['action'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        // Формируем путь к файлу действия
        $action_file = $work->config['site_dir'] . 'action/' . basename($action) . '.php';
        // Проверяем существование файла перед подключением
        if (!is_file($action_file) || !is_readable($action_file)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__FUNCTION__ ?: 'global') . ") | Файл действия не найден или недоступен для чтения | Путь: {$action_file}"
            );
            $action_file = $work->config['site_dir'] . 'action/main.php'; // Подключаем файл по умолчанию
        }
    } else {
        // Если $action остается равным значению по умолчанию ('main'), формируем путь к файлу по умолчанию
        $action_file = $work->config['site_dir'] . 'action/main.php';
    }
    // Проверяем существование файла действий перед подключением
    if (is_file($action_file) && is_readable($action_file)) {
        include_once $action_file;
    } else {
        throw new \RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__FUNCTION__ ?: 'global') . ") | Файл действий не найден или недоступен для чтения | Путь: {$action_file}"
        );
    }

    // Создание шаблона
    $template->create_template();
    if ($header_footer) {
        $template->page_header($title);
        $template->page_footer();
    }
    if ($template_output) {
        echo $template->content;
    }
} catch (\RuntimeException $e) {
    $type = "Ошибка времени выполнения";
    goto handle_exception;
} catch (\PDOException $e) {
    $type = "Ошибка базы данных";
    goto handle_exception;
} catch (\InvalidArgumentException $e) {
    $type = "Неверный аргумент";
    goto handle_exception;
} catch (\LogicException $e) {
    $type = "Логическая ошибка";
    goto handle_exception;
} catch (\BadMethodCallException $e) {
    $type = "Вызов несуществующего метода";
    goto handle_exception;
} catch (\BadFunctionCallException $e) {
    $type = "Вызов несуществующей функции";
    goto handle_exception;
} catch (\DomainException $e) {
    $type = "Значение вне допустимой области";
    goto handle_exception;
} catch (\UnexpectedValueException $e) {
    $type = "Неожиданное значение";
    goto handle_exception;
} catch (\LengthException $e) {
    $type = "Ошибка длины значения";
    goto handle_exception;
} catch (\OutOfBoundsException $e) {
    $type = "Индекс или ключ вне границ";
    goto handle_exception;
} catch (\RangeException $e) {
    $type = "Значение вне допустимого диапазона";
    goto handle_exception;
} catch (\Exception $e) {
    $type = "Общая ошибка";
    goto handle_exception;
}

handle_exception:
// Формируем трассировку один раз
$remote_ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
$message = date('H:i:s') . " [ERROR] | " . ($remote_ip ?: 'UNKNOWN_IP') . ' | ' . $type . ': ' . $e->getMessage() . PHP_EOL;

if (function_exists('log_in_file')) {
    log_in_file($message, true);
} else {
    // Добавление трассировки, если DEBUG_GALLERY включен
    if (defined('DEBUG_GALLERY')) {
        $trace_depth = 0;
        if (DEBUG_GALLERY === true || (is_int(DEBUG_GALLERY) && DEBUG_GALLERY >= 1 && DEBUG_GALLERY <= 9)) {
            $trace_depth = is_int(DEBUG_GALLERY) ? DEBUG_GALLERY : 5;
        }

        if ($trace_depth > 0) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, $trace_depth + 1);
            array_shift($backtrace);

            $trace_info = [];
            foreach ($backtrace as $index => $trace) {
                $step_number = $index + 1;
                $args = array_map(function ($arg) {
                    $arg_str = is_string($arg) ? $arg : json_encode($arg, JSON_UNESCAPED_UNICODE);
                    return mb_strlen($arg_str, 'UTF-8') > 80
                        ? mb_substr($arg_str, 0, 80, 'UTF-8') . '...'
                        : $arg_str;
                }, $trace['args'] ?? []);
                $trace_info[] = sprintf(
                    "Шаг %d:" . PHP_EOL . "  Файл: %s" . PHP_EOL . "  Строка: %s" . PHP_EOL . "  Функция: %s" . PHP_EOL . "  Аргументы: %s",
                    $step_number,
                    $trace['file'] ?? 'неизвестный файл',
                    $trace['line'] ?? 'неизвестная строка',
                    $trace['function'] ?? 'неизвестная функция',
                    json_encode($args, JSON_UNESCAPED_UNICODE)
                );
            }
            $message .= "Трассировка:\n" . implode("\n\n", $trace_info) . PHP_EOL;
        }
    }
    error_log($message);

    // Экранирование спецсимволов и HTML-тегов перед выводом
    $safe_output = htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    die($safe_output);
}

/** @dir action
 * @brief Содержит обработчики действий (например, формы, запросы).
 * @details Примеры файлов:
 *          - admin.php: Обработчик действий для административной панели.
 *          - attach.php: Обработчик для вывода изображений или файлов.
 *          - category.php: Обработчик для работы с категориями.
 *          - main.php: Обработчик основных действий.
 *          - photo.php: Обработчик для работы с фотографиями.
 *          - profile.php: Обработчик для работы с профилями пользователей.
 *          - search.php: Обработчик для поиска.
 */

/** @dir avatar
 * @brief Содержит аватары пользователей.
 */

/** @dir gallery
 * @brief Содержит полноформатные изображения.
 * @details Включает поддиректорию user/ для пользовательских галерей.
 */

/** @dir include
 * @brief Содержит вспомогательные классы и функции.
 * @details Примеры файлов:
 *          - common.php: Общие настройки и конфигурации.
 *          - db.php: Класс для работы с базой данных через PDO.
 *          - template.php: Класс для работы с HTML-шаблонами.
 *          - user.php: Класс для управления пользователями.
 *          - work.php: Вспомогательные функции и утилиты.
 */

/** @dir install
 * @brief Содержит файлы установки проекта.
 * @todo Разработать установочный скрипт.
 */

/** @dir language
 * @brief Содержит файлы локализации.
 * @details Включает поддиректорию russian/ с переводами на русский язык.
 */

/** @dir log
 * @brief Содержит логи системы.
 */

/** @dir themes
 * @brief Содержит темы оформления.
 * @details Включает поддиректорию default/ с темой по умолчанию.
 */

/** @dir thumbnail
 * @brief Содержит миниатюры изображений.
 * @details Включает поддиректорию user/ для миниатюр пользовательских галерей.
 */
