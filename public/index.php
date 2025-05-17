<?php

/** @noinspection DuplicatedCode */
/**
 * Точка входа в приложение PhotoRigma.
 *
 * Этот файл является основной точкой входа в приложение.
 * Он отвечает за:
 * - Инициализацию ядра проекта через Bootstrap::init()
 * - Создание объектов основных классов: Cache_Handler, Database, Work, Template, User
 * - Обработку действий пользователя через параметр `action`
 * - Формирование HTML-ответа (шаблонизация)
 * - Логирование ошибок
 * - Выполнение фоновых задач (через cron) при запуске из CLI
 *
 * @package    PhotoRigma
 * @subpackage public
 * @author     Dark Dayver
 * @version    0.5.0
 * @since      2025-05-15
 *
 * @param string $_GET['action'] Параметр действия, определяет, какой модуль будет вызван.
 *                               Допустимые значения:
 *                               - admin: Админка
 *                               - attach: Безопасный вывод фото по ID
 *                               - category: Управление разделами галереи
 *                               - main: Главная страница
 *                               - news: Работа с новостями
 *                               - photo: Работа с фотографиями
 *                               - profile: Управление профилями пользователей
 *                               - search: Поиск по сайту
 *
 * @throws RuntimeException Если инициализация не удалась (например, ошибка чтения конфигурации)
 *                             Пример: "Ошибка инициализации | [описание]"
 * @throws PDOException     При ошибках подключения к базе данных
 *                             Пример: "Ошибка базы данных | [описание]"
 * @throws Exception        При прочих внутренних ошибках
 *                             Пример: "Общая ошибка | [описание]"
 *
 * @note Все зависимости загружаются через автолоадер PSR-4:
 *       require_once WORK_DIR . '/vendor/autoload.php'
 * @note Ядро проекта организовано следующим образом:
 *       - config/ — конфигурационные файлы
 *       - public/ — точка входа (index.php)
 *       - src/Classes/ — реализация классов
 *       - src/Include/ — глобальные функции, константы, сессии
 *       - src/Interfaces/ — контракты на реализацию
 * @note После создания объекта Work, массив $config очищается — доступ только через $work->config
 *
 * @warning Не изменяйте порядок инициализации или структуру вызова.
 *          Это может привести к фатальным последствиям, таким как потеря данных или нарушение безопасности.
 *
 * Примеры использования:
 * // Через браузер
 * http://example.com/public/index.php?action=admin
 * http://example.com/public/index.php?action=attach&id=123
 * http://example.com/public/index.php?action=profile&uid=9
 * // Через CLI (фоновая задача)
 * php public/index.php
 *
 * @uses \PhotoRigma\Classes\Cache_Handler Класс для работы с кешированием
 * @uses \PhotoRigma\Interfaces\Cache_Handler_Interface Интерфейс кеширования
 * @uses \PhotoRigma\Classes\Database Класс для работы с базой данных
 * @uses \PhotoRigma\Interfaces\Database_Interface Интерфейс базы данных
 * @uses \PhotoRigma\Classes\Work Класс с базовой логикой приложения
 * @uses \PhotoRigma\Interfaces\Work_Interface Интерфейс для класса Work
 * @uses \PhotoRigma\Classes\Template Класс для шаблонизации
 * @uses \PhotoRigma\Interfaces\Template_Interface Интерфейс для класса Template
 * @uses \PhotoRigma\Classes\User Класс для управления пользователями
 * @uses \PhotoRigma\Interfaces\User_Interface Интерфейс для класса User
 * @uses \PhotoRigma\Bootstrap::init() Инициализация ядра проекта
 * @uses \PhotoRigma\Bootstrap_Interface Интерфейс точки инициализации
 *
 * @see https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md
 *      PHPDoc стандарт PSR-5
 * @see https://www.php-fig.org/psr/psr-12/
 *      PHP стандарт PSR-12
 * @see https://www.php-fig.org/psr/psr-4/
 *      PHP стандарт PSR-4
 * @see \PhotoRigma\Classes\Work::$config
 *      Хранение и доступ к конфигурации проекта
 * @see \PhotoRigma\Classes\Work::$lang
 *      Хранение и доступ к языковым переменным проекта
 *
 * @copyright Copyright (c) 2008–2025 Dark Dayver. Все права защищены.
 * @license   MIT License {@link https://opensource.org/licenses/MIT}
 *            Разрешается использовать, копировать, изменять, объединять, публиковать,
 *            распространять, сублицензировать и/или продавать копии программного обеспечения,
 *            а также разрешать лицам, которым предоставляется данное программное обеспечение,
 *            делать это при соблюдении следующих условий:
 *            - Уведомление об авторских правах и условия лицензии должны быть включены во все
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
use PhotoRigma\Classes\Bootstrap;
use PhotoRigma\Classes\Cache_Handler;
use PhotoRigma\Classes\Database;
use PhotoRigma\Classes\Template;
use PhotoRigma\Classes\User;
use PhotoRigma\Classes\Work;
use RangeException;
use RuntimeException;
use Throwable;
use UnexpectedValueException;
use Dotenv\Dotenv;

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

/** @def WORK_DIR
 * @brief Используется для установки корневой папки проекта
 */
define('WORK_DIR', rtrim(dirname(__DIR__), '/'));

try {
    /**
     * @var array $config
     * @brief        Массив с настройками сервера.
     * @details      Содержит как редактируемые пользователем параметры (например, параметры БД), так и автоматически
     *          генерируемые параметры (например, URL сайта). После инициализации массив $config передаётся в свойство
     *          PhotoRigma::Classes::Work::$config.
     * @see          PhotoRigma::Classes::Work::$config
     * @noinspection PhpRedundantVariableDocTypeInspection
     */
    $config = [];

    // Подключаем загрузчик конфигурации и ядра проекта
    require_once WORK_DIR . '/vendor/autoload.php';

    // Инициализация .env
    $dotenv = Dotenv::createImmutable(WORK_DIR);
    /** @noinspection UnusedFunctionResultInspection */
    $dotenv->load();

    // Инициализируем ядро проекта (конфигурация, константы, сессию и функции)
    $required_files = Bootstrap::init();
    // Подключаем все файлы из списка обязательных файлов
    foreach ($required_files as $file) {
        require_once $file;
    }

    // Загрузка и инициализация объектов
    [$db, $work, $user, $template] = Bootstrap::load($config, $_SESSION, false);

    /**
     * Очищаем значение массива $config[], чтобы предотвратить его использование напрямую.
     * Все настройки теперь доступны только через свойство \PhotoRigma\Classes\Work::$config.
     *
     * @see PhotoRigma::Classes::Work::$config Свойство для хранения конфигурации проекта
     */
    unset($config);

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
    // Установка значения $action для CLI
    if (PHP_SAPI === 'cli') {
        $action = 'cron';
    }
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
        $action_file = $work->config['action_dir'][1] . '/' . basename($action. '.php');
        // Проверяем существование файла перед подключением
        if (!is_file($action_file) || !is_readable($action_file)) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__FUNCTION__ ?: 'global') . ") | Файл действия не найден или недоступен для чтения | Путь: $action_file"
            );
            $action_file = $work->config['action_dir'][1] . '/main.php'; // Подключаем файл по умолчанию
            $action = 'main';
        }
    } else {
        // Если $action остается равным значению по умолчанию ('main'), формируем путь к файлу по умолчанию
        $action_file = $work->config['action_dir'][1] . "/$action.php";
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
        $message = date('H:i:s') . ' [ERROR] | ' . ($remote_ip ?: 'UNKNOWN_IP') . ' | ' . ($type ?? 'Общая ошибка') . ': ' . $e->getMessage(
        );

        if (function_exists('\PhotoRigma\Include\log_in_file')) {
            /** @noinspection PhpUnhandledExceptionInspection */
            log_in_file($message, true);
        } else {
            // Добавление трассировки, если DEBUG_GALLERY включен
            if (defined('DEBUG_GALLERY')) {
                $trace_depth = 0;
                /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection */
                /** @noinspection InsufficientTypesControlInspection */
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
            /** @noinspection ForgottenDebugOutputInspection */
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
