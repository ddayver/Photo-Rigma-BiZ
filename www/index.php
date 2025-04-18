<?php

/**
 * @file        index.php
 * @brief       Точка входа в приложение PhotoRigma.
 *
 * @throws      RuntimeException Если конфигурационный файл отсутствует или недоступен для чтения.
 *              Пример сообщения: "Конфигурационный файл отсутствует или не является файлом | Путь: config.php".
 * @throws      RuntimeException Если обязательные файлы отсутствуют или недоступны для чтения.
 *              Пример сообщения: "Проверка обязательных файлов завершилась ошибкой | Ошибки: [список ошибок]".
 * @throws      RuntimeException Если файл действия не найден или недоступен для чтения.
 *              Пример сообщения: "Файл действий не найден или недоступен для чтения | Путь: [путь к файлу]".
 * @throws      PDOException Если возникла ошибка при работе с базой данных.
 *              Пример сообщения: "Ошибка базы данных | [сообщение об ошибке]".
 * @throws      Exception Если возникла непредвиденная ошибка.
 *              Пример сообщения: "Общая ошибка | [сообщение об ошибке]".
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-04-07
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
 * @section     Основные функции
 * - Подключение конфигурации и обязательных файлов.
 * - Инициализация основных классов (`Database`, `Work`, `Template`, `User`).
 * - Обработка действий пользователя через параметр `action`.
 * - Генерация HTML-контента страницы.
 * - Логирование ошибок и обработка исключений.
 *
 * @see         config.php Файл конфигурации, содержащий параметры приложения.
 * @see         include/common.php Файл, содержащий глобальные константы и функции.
 * @see         PhotoRigma::Classes::Database Класс для работы с базой данных.
 * @see         PhotoRigma::Classes::Work Класс для выполнения вспомогательных операций.
 * @see         PhotoRigma::Classes::Template Класс для генерации HTML-контента.
 * @see         PhotoRigma::Classes::User Класс для управления пользователями.
 * @see         PhotoRigma::Include::archive_old_logs Функция для архивации старых логов.
 * @see         PhotoRigma::Include::log_in_file Функция для логирования ошибок.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в запуске и работе приложения.
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

declare(strict_types=1);

namespace PhotoRigma;

// Импорт пространств имён
use BadFunctionCallException;
use BadMethodCallException;
use DomainException;
use Exception;
use InvalidArgumentException;
use LengthException;
use LogicException;
use OutOfBoundsException;
use PDOException;
use PhotoRigma\Classes\Database;
use PhotoRigma\Classes\Template;
use PhotoRigma\Classes\User;
use PhotoRigma\Classes\Work;
use RangeException;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

use function PhotoRigma\Include\archive_old_logs;
use function PhotoRigma\Include\log_in_file;

// Устанавливаем кодировку для работы с мультибайтовыми строками
$encoding = mb_regex_encoding('UTF-8');
mb_internal_encoding('UTF-8');

/** @def IN_GALLERY
 * @brief Используется для проверки, что файлы подключены через index.php, а не вызваны напрямую.
 */
define('IN_GALLERY', true);

/** @def DEBUG_GALLERY
 * @brief   Включение режима отладки (более подробный вывод информации об ошибках).
 * @details Используется для включения режима отладки, который влияет на логирование ошибок.
 * @see     PhotoRigma::Include::log_in_file Функция для логирования ошибок.
 */
define('DEBUG_GALLERY', false);

try {
    /**
     * Безопасное подключение конфигурационного файла.
     *
     * @details Файл config.php содержит настройки, редактируемые пользователем, такие как параметры подключения к базе данных,
     *          пути к директориям, настройки тем и другие важные параметры.
     * @see     config.php Файл конфигурации приложения.
     * @see     $config Массив, инициализируемый в файле config.php, содержащий все настройки приложения.
     */
    if (!is_file('config.php')) {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__FUNCTION__ ?: 'global') . ") | Конфигурационный файл отсутствует или не является файлом | Путь: config.php"
        );
    }
    if (!is_readable('config.php')) {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__FUNCTION__ ?: 'global') . ") | Конфигурационный файл существует, но недоступен для чтения | Путь: config.php"
        );
    }
    require_once 'config.php'; // Подключаем файл редактируемых пользователем настроек

    /**
     * @var array $required_files
     * @brief   Список обязательных файлов для подключения.
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

    // Проходит по массиву списку обязательных файлов и проверяет, существуют ли файлы и доступны ли они для чтения.
    foreach ($required_files as $file) {
        if (!is_file($file)) {
            $errors[] = "Файл отсутствует или не является файлом: $file";
        } elseif (!is_readable($file)) {
            $errors[] = "Файл существует, но недоступен для чтения: $file";
        }
    }

    // Если есть ошибки, логируем их и завершаем выполнение скрипта
    if (!empty($errors)) {
        $error_details = implode(PHP_EOL, $errors); // Подробности ошибок
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__FUNCTION__ ?: 'global') . ") | Проверка обязательных файлов завершилась ошибкой | Ошибки:" . PHP_EOL . $error_details
        );
    }

    // Подключаем все файлы из списка обязательных файлов
    foreach ($required_files as $file) {
        require_once $file;
    }

    // Архивирование старых логов
    archive_old_logs();

    /** @var PhotoRigma::Classes $db ::Database $db
     * @brief   Создание объекта класса Database для работы с основной БД.
     * @details Инициализируется через конструктор с параметрами из массива $config['db'].
     * @see     PhotoRigma::Classes::Database Класс для работы с базой данных.
     * @see     include/db.php Файл, содержащий реализацию класса Database.
     * @see     $config Массив конфигурации, используемый для подключения к БД.
     */
    $db = new Database($config['db']);

    /** @var PhotoRigma::Classes $work ::Work $work
     * @brief   Создание объекта класса Work.
     * @details Используется для выполнения различных вспомогательных операций.
     * @see     PhotoRigma::Classes::Work Класс для выполнения вспомогательных операций.
     * @see     include/work.php Файл, содержащий реализацию класса Work.
     * @see     PhotoRigma::Classes::Work::$config Свойство, хранящее конфигурацию приложения.
     * @see     PhotoRigma::Classes::Work::check_input() Метод для проверки входных данных.
     * @see     PhotoRigma::Classes::Work::url_check() Метод для проверки URL на наличие вредоносного кода.
     */
    $work = new Work($db, $config, $_SESSION);

    /**
     * Очищаем значение массива $config[], чтобы предотвратить его использование напрямую.
     * Все настройки теперь доступны только через свойство \PhotoRigma\Classes\Work::$config.
     *
     * @see PhotoRigma::Classes::Work::$config Свойство для хранения конфигурации проекта
     */
    unset($config);

    /** @var PhotoRigma::Classes $user ::User $user
     * @brief   Создание объекта класса User.
     * @details Используется для управления пользователями системы.
     * @see     PhotoRigma::Classes::User Класс для управления пользователями.
     * @see     include/user.php Файл, содержащий реализацию класса User.
     */
    $user = new User($db, $_SESSION);

    // Проверяем есть ли настройки темы у пользователя.
    $themes_config = $user->session['theme'] ?? $work->config['themes'];

    /** @var PhotoRigma::Classes $template ::Template $template
     * @brief   Создание объекта класса Template.
     * @details Используется для генерации HTML-контента страниц.
     * @see     PhotoRigma::Classes::Template Класс для работы с HTML-шаблонами.
     * @see     include/template.php Файл, содержащий реализацию класса Template.
     * @see     PhotoRigma::Classes::Work::$config Свойство, хранящее конфигурацию для шаблонов.
     * @see     PhotoRigma::Classes::Template::create_template() Метод для создания содержимого страницы.
     * @see     PhotoRigma::Classes::Template::page_header() Метод для добавления шапки страницы.
     * @see     PhotoRigma::Classes::Template::page_footer() Метод для добавления подвала страницы.
     * @see     PhotoRigma::Classes::Template::$content Свойство, хранящее содержимое всей страницы.
     * @see     $title Переменная, используемая для добавления текста к заголовку страницы.
     */
    $template = new Template(
        $work->config['site_url'],
        $work->config['site_dir'],
        $themes_config
    );

    // Передаем объект User в класс Work
    $work->set_user($user);

    // Загружаем языковый массив в классе Work и передаем его в класс Template
    $work->set_lang();
    $template->set_lang($work->lang);

    // Передаем объект Work в классы Template и User
    $template->set_work($work);
    $user->set_work($work);

    /** @var string $title
     * @brief Добавление текста к заголовку страницы.
     */
    $title = '';

    /**
     * @var string $action
     * @brief   Действие, которое необходимо выполнить.
     * @details Возможные значения определяются динамически в зависимости от доступных файлов в директории 'action/'.
     * Пример вызова действия 'profile':
     * $_GET['action'] = 'profile';
     */
    $action = 'main'; // Значение по умолчанию

    /**
     * Обработка действия, указанного в параметре 'action'.
     * - Проверяется существование и безопасность параметра 'action'.
     * - Формируется путь к файлу действия.
     * - Если файл не существует или недоступен, используется файл по умолчанию ('main.php').
     */
    if ($work->check_input(
        '_GET',
        'action',
        ['isset' => true, 'empty' => true]
    ) && // Проверяем, что параметр 'action' существует и не пустой
        $_GET['action'] !== 'index' &&                                             // Исключаем значение 'index'
        $work->url_check(
        )                                                        // Проверяем URL на наличие вредоносного кода
    ) {
        // Используем более безопасный метод фильтрации входных данных
        $action = filter_var($_GET['action'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        // Формируем путь к файлу действия
        $action_file = $work->config['site_dir'] . 'action/' . basename($action) . '.php';
        // Проверяем существование файла перед подключением
        if (!is_file($action_file) || !is_readable($action_file)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__FUNCTION__ ?: 'global') . ") | Файл действия не найден или недоступен для чтения | Путь: $action_file"
            );
            $action_file = $work->config['site_dir'] . 'action/main.php'; // Подключаем файл по умолчанию
            $action = 'main';
        }
    } else {
        // Если $action остается равным значению по умолчанию ('main'), формируем путь к файлу по умолчанию
        $action_file = $work->config['site_dir'] . 'action/main.php';
    }
    // Проверяем существование файла действий перед подключением
    if (is_file($action_file) && is_readable($action_file)) {
        include_once $action_file;
    } else {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__FUNCTION__ ?: 'global') . ") | Файл действий не найден или недоступен для чтения | Путь: $action_file"
        );
    }

    // Создаем токен для CSRF-защиты в полях поиска и входа
    $csrf_token = $user->csrf_token();

    // Создание шаблона
    $template->create_template();
    $template->page_header($title, $action, $csrf_token);
    $template->page_footer($user->session['login_id'], $csrf_token);
    header('Content-Type: text/html; charset=UTF-8');
    echo $template->content;
} catch (PDOException $e) {
    $type = "Ошибка базы данных";
} catch (UnexpectedValueException $e) {
    $type = "Неожиданное значение";
} catch (OutOfBoundsException $e) {
    $type = "Индекс или ключ вне границ";
} catch (RangeException $e) {
    $type = "Значение вне допустимого диапазона";
} catch (RuntimeException $e) {
    $type = "Ошибка времени выполнения";
} catch (InvalidArgumentException $e) {
    $type = "Неверный аргумент";
} catch (BadMethodCallException $e) {
    $type = "Вызов несуществующего метода";
} catch (BadFunctionCallException $e) {
    $type = "Вызов несуществующей функции";
} catch (DomainException $e) {
    $type = "Значение вне допустимой области";
} catch (LengthException $e) {
    $type = "Ошибка длины значения";
} catch (LogicException $e) {
    $type = "Логическая ошибка";
} catch (Exception $e) {
    $type = "Общая ошибка";
} finally {
    if (isset($e) && ($e instanceof Throwable)) {
        // Формируем трассировку один раз
        $remote_ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
        $message = date('H:i:s') . " [ERROR] | " . ($remote_ip ?: 'UNKNOWN_IP') . ' | ' . $type . ': ' . $e->getMessage(
        );

        if (function_exists('\PhotoRigma\Include\log_in_file')) {
            log_in_file($message, true);
        } else {
            // Добавление трассировки, если DEBUG_GALLERY включен
            if (defined('DEBUG_GALLERY')) {
                $trace_depth = 0;
                if (defined('DEBUG_GALLERY') && (DEBUG_GALLERY === true || (is_int(
                    DEBUG_GALLERY
                ) && DEBUG_GALLERY >= 1 && DEBUG_GALLERY <= 9))) {
                    $trace_depth = is_int(DEBUG_GALLERY) ? DEBUG_GALLERY : 5;
                }

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
                            $trace['file'] ?? 'неизвестный файл',
                            $trace['line'] ?? 'неизвестная строка',
                            $trace['function'] ?? 'неизвестная функция',
                            json_encode($args, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
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
    }
}
/** @dir action
 * @brief   Содержит обработчики действий (например, формы, запросы).
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

/** @dir cache
 * @brief Содержит кеш элементов сайта.
 */

/** @dir gallery
 * @brief   Содержит полноформатные изображения.
 * @details Включает поддиректорию user/ для пользовательских галерей.
 */

/** @dir include
 * @brief   Содержит вспомогательные классы и функции.
 * @details Примеры файлов:
 *          - common.php: Общие настройки и конфигурации.
 *          - db.php: Класс для работы с базой данных через PDO.
 *          - template.php: Класс для работы с HTML-шаблонами.
 *          - user.php: Класс для управления пользователями.
 *          - work.php: Вспомогательные функции и утилиты.
 */

/** @dir install
 * @brief Содержит файлы установки проекта.
 * @todo  Разработать установочный скрипт.
 */

/** @dir language
 * @brief   Содержит файлы локализации.
 * @details Включает поддиректорию russian/ с переводами на русский язык.
 */

/** @dir log
 * @brief Содержит логи системы.
 */

/** @dir themes
 * @brief   Содержит темы оформления.
 * @details Включает поддиректорию default/ с темой по умолчанию.
 */

/** @dir thumbnail
 * @brief   Содержит миниатюры изображений.
 * @details Включает поддиректорию user/ для миниатюр пользовательских галерей.
 */
