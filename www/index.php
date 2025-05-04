<?php

/**
 * @file        index.php
 * @brief       Точка входа в приложение PhotoRigma.
 *
 * @author      Dark Dayver
 * @version     0.4.3
 * @date        2025-05-05
 * @namespace   PhotoRigma
 *
 * @details     Этот файл является точкой входа в приложение и выполняет следующие задачи:
 *              - Подключает конфигурационный файл (`config.php`) и проверяет его доступность.
 *              - Проверяет наличие и доступность обязательных файлов (например, `common.php`,
 *                `Database.php`).
 *              - Создаёт объекты основных классов (`Database`, `Work`, `Template`, `User`).
 *              - Обрабатывает действия пользователя через параметр `action`. Значение,
 *                получаемое из запроса в URL, указывает на действие, которое должно выполнить
 *                приложение.
 *              - Выводит шаблонные элементы страницы (шапку, подвал и содержимое).
 *              - Реализует централизованную систему обработки ошибок и их логирования на уровне
 *                ядра приложения.
 *
 * @section     Index_Main_Functions Основные функции
 *              - Подключение конфигурации и обязательных файлов.
 *              - Инициализация основных классов (`Database`, `Work`, `Template`, `User`).
 *              - Обработка действий пользователя через параметр `action`.
 *              - Генерация HTML-контента страницы.
 *              - Логирование ошибок и обработка исключений.
 *
 * @section     Index_Action_Param Параметр `action`
 *              Параметр `action` определяет действие, которое должно быть выполнено приложением.
 *              Поддерживаемые значения:
 *              - `admin`: Администрирование сайта.
 *              - `attach`: Реализует безопасный вывод фото из галереи по идентификатору с
 *                проверкой прав доступа и ограничением путей.
 *              - `category`: Обзор и управление разделами галереи.
 *              - `main`: Главная страница. Формирует и выводит последние новости проекта,
 *                проверяя права пользователя на их просмотр.
 *              - `news`: Реализует функциональность работы с новостями: добавление,
 *                редактирование, удаление и отображение.
 *              - `photo`: Работа с фотографиями: вывод, редактирование, загрузка и обработка
 *                изображений, оценок.
 *              - `profile`: Работа с пользователями: вход, выход, регистрация, редактирование и
 *                просмотр профиля.
 *              - `search`: Обработка поисковых запросов на сайте.
 *
 * @section     Index_Error_Handling Обработка ошибок
 *              При возникновении ошибок генерируются исключения. Поддерживаемые типы исключений:
 *              - `RuntimeException`: Если конфигурационный файл или обязательные файлы
 *                отсутствуют.
 *              - `PDOException`: Если возникла ошибка при работе с базой данных.
 *              - `Exception`: Если возникла непредвиденная ошибка.
 *
 * @throws      RuntimeException Если конфигурационный файл отсутствует или недоступен для
 *              чтения. Пример сообщения: "Конфигурационный файл отсутствует или не является
 *              файлом | Путь: config.php".
 * @throws      RuntimeException Если обязательные файлы отсутствуют или недоступны для чтения.
 *              Пример сообщения: "Проверка обязательных файлов завершилась ошибкой | Ошибки:
 *              [список ошибок]".
 * @throws      RuntimeException Если файл действия не найден или недоступен для чтения.
 *              Пример сообщения: "Файл действий не найден или недоступен для чтения | Путь:
 *              [путь к файлу]".
 * @throws      PDOException Если возникла ошибка при работе с базой данных. Пример сообщения:
 *              "Ошибка базы данных | [сообщение об ошибке]".
 * @throws      Exception Если возникла непредвиденная ошибка. Пример сообщения: "Общая ошибка |
 *              [сообщение об ошибке]".
 *
 * @see         config.php Файл конфигурации, содержащий параметры приложения.
 *                - @see include/constants.php Файл с глобальными константами приложения.
 *                - @see include/session_init.php Файл для инициализации сессии.
 *                - @see include/functions.php Файл с глобальными функциями приложения.
 *              - Классы приложения:
 *                - @see PhotoRigma::Classes::Database Класс для работы с базами данных через PDO.
 *                - @see PhotoRigma::Classes::Work_Helper Класс для выполнения вспомогательных задач.
 *                - @see PhotoRigma::Classes::Work_CoreLogic Класс для выполнения базовой логики приложения.
 *                - @see PhotoRigma::Classes::Work_Image Класс для работы с изображениями.
 *                - @see PhotoRigma::Classes::Work_Template Класс для формирования данных для шаблонов.
 *                - @see PhotoRigma::Classes::Work_Security Класс для обеспечения безопасности приложения.
 *                - @see PhotoRigma::Classes::Work Основной класс приложения.
 *                - @see PhotoRigma::Classes::Template Класс для работы с шаблонами.
 *                - @see PhotoRigma::Classes::User Класс для работы с пользователями и хранения данных о текущем
 *                пользователе.
 *              - Интерфейсы:
 *                - @see PhotoRigma::Interfaces::Database_Interface Интерфейс для работы с базами данных через PDO.
 *                - @see PhotoRigma::Interfaces::Work_Helper_Interface Интерфейс для вспомогательных методов.
 *                - @see PhotoRigma::Interfaces::Work_CoreLogic_Interface Интерфейс, определяющий контракт для классов,
 *                  реализующих базовую логику приложения.
 *                - @see PhotoRigma::Interfaces::Work_Image_Interface Интерфейс для работы с изображениями.
 *                - @see PhotoRigma::Interfaces::Work_Template_Interface Интерфейс для формирования данных для
 *                шаблонов.
 *                - @see PhotoRigma::Interfaces::Work_Security_Interface Интерфейс для работы с безопасностью.
 *                - @see PhotoRigma::Interfaces::Work_Interface Интерфейс для центральной точки приложения.
 *                - @see PhotoRigma::Interfaces::Template_Interface Интерфейс для работы с шаблонами.
 *                - @see PhotoRigma::Interfaces::User_Interface Интерфейс для работы с пользователями и группами.
 *              - Вспомогательные функции:
 *                - @see PhotoRigma::Include::archive_old_logs Функция для архивации старых логов.
 *                - @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в запуске и
 *              работе приложения. Реализованы меры безопасности для предотвращения
 *              несанкционированного доступа и выполнения действий.
 *
 * Примеры вызова файла через URL с использованием параметра `action`:
 * @code
 * http://example.com/index.php?action=admin
 * http://example.com/index.php?action=attach&id=123
 * http://example.com/index.php?action=category
 * http://example.com/index.php?action=main
 * http://example.com/index.php?action=profile&uid=9
 * @endcode
 *
 * @copyright   Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать,
 *              распространять, сублицензировать и/или продавать копии программного обеспечения,
 *              а также разрешать лицам, которым предоставляется данное программное обеспечение,
 *              делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все
 *              копии или значимые части программного обеспечения.
 */

declare(strict_types=1);

namespace PhotoRigma;

// Импорт пространств имён
use BadFunctionCallException;
use BadMethodCallException;
use DomainException;
use Exception;
use InvalidArgumentException;
use JsonException;
use LengthException;
use LogicException;
use OutOfBoundsException;
use PDOException;
use PhotoRigma\Classes\Cache_Handler;
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
            __FILE__ . ':' . __LINE__ . ' (' . (__FUNCTION__ ?: 'global') . ') | Конфигурационный файл отсутствует или не является файлом | Путь: config.php'
        );
    }
    if (!is_readable('config.php')) {
        throw new RuntimeException(
            __FILE__ . ':' . __LINE__ . ' (' . (__FUNCTION__ ?: 'global') . ') | Конфигурационный файл существует, но недоступен для чтения | Путь: config.php'
        );
    }
    require_once 'config.php'; // Подключаем файл редактируемых пользователем настроек

    /**
     * @var array $required_files
     * @brief   Список обязательных файлов для подключения.
     * @details Содержит пути к файлам, которые необходимы для работы приложения.
     */
    $required_files = [
        $config['inc_dir'] . 'constants.php',
        $config['inc_dir'] . 'session_init.php',
        $config['inc_dir'] . 'functions.php',
        $config['inc_dir'] . 'Cache_Handler_Interface.php',
        $config['inc_dir'] . 'Cache_Handler.php',
        $config['inc_dir'] . 'Database_Interface.php',
        $config['inc_dir'] . 'Database.php',
        $config['inc_dir'] . 'Work_Helper_Interface.php',
        $config['inc_dir'] . 'Work_Helper.php',
        $config['inc_dir'] . 'Work_CoreLogic_Interface.php',
        $config['inc_dir'] . 'Work_CoreLogic.php',
        $config['inc_dir'] . 'Work_Image_Interface.php',
        $config['inc_dir'] . 'Work_Image.php',
        $config['inc_dir'] . 'Work_Template_Interface.php',
        $config['inc_dir'] . 'Work_Template.php',
        $config['inc_dir'] . 'Work_Security_Interface.php',
        $config['inc_dir'] . 'Work_Security.php',
        $config['inc_dir'] . 'Work_Interface.php',
        $config['inc_dir'] . 'Work.php',
        $config['inc_dir'] . 'Template_Interface.php',
        $config['inc_dir'] . 'Template.php',
        $config['inc_dir'] . 'User_Interface.php',
        $config['inc_dir'] . 'User.php',
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
            __FILE__ . ':' . __LINE__ . ' (' . (__FUNCTION__ ?: 'global') . ') | Проверка обязательных файлов завершилась ошибкой | Ошибки:' . PHP_EOL . $error_details
        );
    }

    // Подключаем все файлы из списка обязательных файлов
    foreach ($required_files as $file) {
        require_once $file;
    }

    // Архивирование старых логов
    archive_old_logs();

    /** @var PhotoRigma::Classes $cache
     * @brief   Создание объекта класса Cache_Handler для работы с системами кеширования.
     * @details Инициализируется через конструктор с параметрами из массива $config['cache'].
     *          - Для файлового кеширования (`file`) требуется указать путь к директории кеша.
     *          - Для Redis/Memcached требуется указать хост и порт.
     *          Дополнительно может быть передан путь к корневой директории проекта ($config['site_dir']) для файлового кеширования.
     * @see     PhotoRigma::Classes::Cache_Handler Класс для работы с системами кеширования.
     * @see     include/Cache_Handler.php Файл, содержащий реализацию класса Cache_Handler.
     * @see     $config Массив конфигурации, используемый для настройки системы кеширования.
     */
    $cache = new Cache_Handler($config['cache'], $config['site_dir']);

    /** @var PhotoRigma::Classes $db ::Database $db
     * @brief   Создание объекта класса Database для работы с основной БД.
     * @details Инициализируется через конструктор с параметрами из массива $config['db'].
     * @see     PhotoRigma::Classes::Database Класс для работы с базой данных.
     * @see     include/Database.php Файл, содержащий реализацию класса Database.
     * @see     $config Массив конфигурации, используемый для подключения к БД.
     */
    $db = new Database($config['db'], $cache);

    /** @var PhotoRigma::Classes $work
     * @brief   Создание объекта класса Work.
     * @details Используется для выполнения различных вспомогательных операций приложения:
     *          - Управление конфигурацией через свойство `$config`.
     *          - Работа с безопасностью через методы, такие как `check_input` и `url_check`.
     *          - Взаимодействие с системой кеширования через объект `$cache`.
     *          Объект создаётся с использованием экземпляра базы данных (`$db`), массива конфигурации (`$config`),
     *          ссылки на массив сессии (`$_SESSION`) и объекта кеширования (`$cache`).
     *
     * @see     PhotoRigma::Classes::Work Класс для выполнения вспомогательных операций.
     * @see     include/Work.php Файл, содержащий реализацию класса Work.
     * @see     PhotoRigma::Classes::Work::$config Свойство, хранящее конфигурацию приложения.
     * @see     PhotoRigma::Classes::Work::$cache Свойство, содержащее объект для работы с кешем.
     * @see     PhotoRigma::Classes::Work::check_input() Метод для проверки входных данных.
     * @see     PhotoRigma::Classes::Work::url_check() Метод для проверки URL на наличие вредоносного кода.
     */
    $work = new Work($db, $config, $_SESSION, $cache);

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
     * @see     include/User.php Файл, содержащий реализацию класса User.
     */
    $user = new User($db, $_SESSION);

    // Проверяем есть ли настройки темы у пользователя.
    $themes_config = $user->session['theme'] ?? $work->config['themes'];

    /** @var PhotoRigma::Classes $template ::Template $template
     * @brief   Создание объекта класса Template.
     * @details Используется для генерации HTML-контента страниц.
     * @see     PhotoRigma::Classes::Template Класс для работы с HTML-шаблонами.
     * @see     include/Template.php Файл, содержащий реализацию класса Template.
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

    // Загружаем языковый массив в классе Work
    $work->set_lang();

    // Передаем объект Work в классы Template и User
    $template->set_work($work);
    $user->set_work($work);

    /** @var string $title
     * @brief        Добавление текста к заголовку страницы.
     * @noinspection PhpRedundantVariableDocTypeInspection
     */
    $title = '';

    /**
     * @var string $action
     * @brief        Действие, которое необходимо выполнить.
     * @details      Возможные значения определяются динамически в зависимости от доступных файлов в директории 'action/'.
     * Пример вызова действия 'profile':
     * $_GET['action'] = 'profile';
     * @noinspection PhpRedundantVariableDocTypeInspection
     */
    $action = 'main'; // Значение по умолчанию

    /**
     * Обработка действия, указанного в параметре 'action'.
     * - Проверяется существование и безопасность параметра 'action'.
     * - Формируется путь к файлу действия.
     * - Если файл не существует или недоступен, используется файл по умолчанию ('main.php').
     */
    /** @noinspection NotOptimalIfConditionsInspection */
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
                __FILE__ . ':' . __LINE__ . ' (' . (__FUNCTION__ ?: 'global') . ") | Файл действия не найден или недоступен для чтения | Путь: $action_file"
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
            __FILE__ . ':' . __LINE__ . ' (' . (__FUNCTION__ ?: 'global') . ") | Файл действий не найден или недоступен для чтения | Путь: $action_file"
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
    $type = 'Ошибка базы данных';
} catch (UnexpectedValueException $e) {
    $type = 'Неожиданное значение';
} catch (OutOfBoundsException $e) {
    $type = 'Индекс или ключ вне границ';
} catch (RangeException $e) {
    $type = 'Значение вне допустимого диапазона';
} catch (RuntimeException $e) {
    $type = 'Ошибка времени выполнения';
} catch (InvalidArgumentException $e) {
    $type = 'Неверный аргумент';
} catch (BadMethodCallException $e) {
    $type = 'Вызов несуществующего метода';
} catch (BadFunctionCallException $e) {
    $type = 'Вызов несуществующей функции';
} catch (DomainException $e) {
    $type = 'Значение вне допустимой области';
} catch (LengthException $e) {
    $type = 'Ошибка длины значения';
} catch (LogicException $e) {
    $type = 'Логическая ошибка';
} catch (Exception $e) {
    $type = 'Общая ошибка';
} finally {
    if (isset($e) && ($e instanceof Throwable)) {
        // Формируем трассировку один раз
        $remote_ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
        $message = date('H:i:s') . ' [ERROR] | ' . ($remote_ip ?: 'UNKNOWN_IP') . ' | ' . $type . ': ' . $e->getMessage(
        );

        if (function_exists('\PhotoRigma\Include\log_in_file')) {
            /** @noinspection PhpUnhandledExceptionInspection */
            log_in_file($message, true);
        } else {
            // Добавление трассировки, если DEBUG_GALLERY включен
            if (defined('DEBUG_GALLERY')) {
                $trace_depth = 0;
                /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection */
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
                        $args = array_map(
                            /**
                             * @throws JsonException
                             */
                            static function ($arg) {
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
                            },
                            $trace['args'] ?? []
                        );
                        /** @noinspection PhpUnhandledExceptionInspection */
                        $trace_info[] = sprintf(
                            'Шаг %d:' . PHP_EOL . '  Файл: %s' . PHP_EOL . '  Строка: %s' . PHP_EOL . '  Функция: %s' . PHP_EOL . '  Аргументы: %s',
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
 *          - functions.php: Общие настройки и конфигурации.
 *          - Database.php: Класс для работы с базой данных через PDO.
 *          - Template.php: Класс для работы с HTML-шаблонами.
 *          - User.php: Класс для управления пользователями.
 *          - Work.php: Вспомогательные функции и утилиты.
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
