<?php

/**
 * Точка входа в приложение PhotoRigma.
 * Этот файл является точкой входа в приложение и выполняет следующие задачи:
 * - Подключает конфигурационный файл (`config.php`) и проверяет его доступность.
 * - Проверяет наличие и доступность обязательных файлов (например, `common.php`, `Database.php`).
 * - Создаёт объекты основных классов (`Database`, `Work`, `Template`, `User`).
 * - Обрабатывает действия пользователя через параметр `action`. Значение, получаемое из запроса в URL, указывает на
 *   действие, которое должно выполнить приложение.
 * - Выводит шаблонные элементы страницы (шапку, подвал и содержимое).
 * - Реализует централизованную систему обработки ошибок и их логирования на уровне ядра приложения.
 * - При запуске из консоли выполняет cron-задачи.
 *
 * @author    Dark Dayver
 * @version   0.5.0
 * @since     2025-05-29
 * @namespace PhotoRigma
 * @package   PhotoRigma
 *
 * @section   Index_Main_Functions Основные функции
 *            - Подключение конфигурации и обязательных файлов.
 *            - Инициализация основных классов (`Database`, `Work`, `Template`, `User`).
 *            - Обработка действий пользователя через параметр `action`.
 *            - Генерация HTML-контента страницы.
 *            - Логирование ошибок и обработка исключений.
 *            - При запуске из консоли выполнение cron-задач.
 *
 * @section   Index_Action_Param Параметр `action`
 *            Параметр `action` определяет действие, которое должно быть выполнено приложением.
 *            Поддерживаемые значения:
 *            - `admin`: Администрирование сайта.
 *            - `attach`: Реализует безопасный вывод фото из галереи по идентификатору с
 *              проверкой прав доступа и ограничением путей.
 *            - `category`: Обзор и управление разделами галереи.
 *            - `main`: Главная страница. Формирует и выводит последние новости проекта,
 *              проверяя права пользователя на их просмотр.
 *            - `news`: Реализует функциональность работы с новостями: добавление,
 *              редактирование, удаление и отображение.
 *            - `photo`: Работа с фотографиями: вывод, редактирование, загрузка и обработка
 *              изображений, оценок.
 *            - `profile`: Работа с пользователями: вход, выход, регистрация, редактирование и
 *              просмотр профиля.
 *            - `search`: Обработка поисковых запросов на сайте.
 *            - `cron`: Выполнение фоновых задач через cron (только из консоли).
 *
 * @section   Index_Error_Handling Обработка ошибок
 *            При возникновении ошибок генерируются исключения. Поддерживаемые типы исключений:
 *            - `RuntimeException`: Если конфигурационный файл или обязательные файлы
 *              отсутствуют.
 *            - `PDOException`: Если возникла ошибка при работе с базой данных.
 *            - `Exception`: Если возникла непредвиденная ошибка.
 *
 * @throws    RuntimeException Если конфигурационный файл отсутствует или недоступен для
 *                             чтения. Пример сообщения:
 *                             "Конфигурационный файл отсутствует или не является файлом | Путь: config.php".
 * @throws    RuntimeException Если обязательные файлы отсутствуют или недоступны для чтения.
 *                             Пример сообщения:
 *                             "Проверка обязательных файлов завершилась ошибкой | Ошибки: [список ошибок]".
 * @throws    RuntimeException Если файл действия не найден или недоступен для чтения.
 *                             Пример сообщения:
 *                             "Файл действий не найден или недоступен для чтения | Путь: [путь к файлу]".
 * @throws    PDOException     Если возникла ошибка при работе с базой данных.
 *                             Пример сообщения:
 *                             "Ошибка базы данных | [сообщение об ошибке]".
 * @throws    Exception        Если возникла непредвиденная ошибка.
 *                             Пример сообщения:
 *                             "Общая ошибка | [сообщение об ошибке]".
 *
 * @section   Index_Related_Files Связанные файлы и компоненты
 *            - Подключаемые файлы:
 *              - config.php Файл конфигурации, содержащий параметры приложения.
 *              - src/Include/constants.php Файл с глобальными константами приложения.
 *              - src/Include/session_init.php Файл для инициализации сессии.
 *              - src/Include/functions.php Файл с глобальными функциями приложения.
 *            - Классы приложения:
 * @uses \PhotoRigma\Classes\Cache_Handler Класс для работы с системами кеширования.
 * @uses \PhotoRigma\Classes\Database Класс для работы с базами данных через PDO.
 * @uses \PhotoRigma\Classes\Work_Helper Класс для выполнения вспомогательных задач.
 * @uses \PhotoRigma\Classes\Work_CoreLogic Класс для выполнения базовой логики приложения.
 * @uses \PhotoRigma\Classes\Work_Image Класс для работы с изображениями.
 * @uses \PhotoRigma\Classes\Work_Template Класс для формирования данных для шаблонов.
 * @uses \PhotoRigma\Classes\Work_Security Класс для обеспечения безопасности приложения.
 * @uses \PhotoRigma\Classes\Work Основной класс приложения.
 * @uses \PhotoRigma\Classes\Template Класс для работы с шаблонами.
 * @uses \PhotoRigma\Classes\User Класс для работы с пользователями и хранения данных о текущем
 *                                               пользователе.
 *            - Интерфейсы:
 * @uses \PhotoRigma\Interfaces\Cache_Handler_Interface Интерфейс для работы с системами кеширования.
 * @uses \PhotoRigma\Interfaces\Database_Interface Интерфейс для работы с базами данных через PDO.
 * @uses \PhotoRigma\Interfaces\Work_Helper_Interface Интерфейс для вспомогательных методов.
 * @uses \PhotoRigma\Interfaces\Work_CoreLogic_Interface Интерфейс, определяющий контракт для классов,
 *                                                                      реализующих базовую логику приложения.
 * @uses \PhotoRigma\Interfaces\Work_Image_Interface Интерфейс для работы с изображениями.
 * @uses \PhotoRigma\Interfaces\Work_Template_Interface Интерфейс для формирования данных для шаблонов.
 * @uses \PhotoRigma\Interfaces\Work_Security_Interface Интерфейс для работы с безопасностью.
 * @uses \PhotoRigma\Interfaces\Work_Interface Интерфейс для центральной точки приложения.
 * @uses \PhotoRigma\Interfaces\Template_Interface Интерфейс для работы с шаблонами.
 * @uses \PhotoRigma\Interfaces\User_Interface Интерфейс для работы с пользователями и группами.
 *            - Вспомогательные функции:
 * @uses \PhotoRigma\Include\log_in_file Записывает сообщение об ошибке в лог-файл.
 *
 * @note      Этот файл является частью системы PhotoRigma и играет ключевую роль в запуске и
 *            работе приложения. Реализованы меры безопасности для предотвращения
 *            несанкционированного доступа и выполнения действий.
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

/** @var Cache_Handler $cache */
/** @var Database $db */
/** @var Work $work */
/** @var User $user */
/** @var Template $template */

// Устанавливаем кодировку для работы с мультибайтовыми строками
$encoding = mb_regex_encoding('UTF-8');
mb_internal_encoding('UTF-8');

/** Используется для проверки, что файлы подключены через index.php, а не вызваны напрямую. */
define('IN_GALLERY', true);

/**
 * Включение режима отладки (более подробный вывод информации об ошибках).
 *
 * Используется для включения режима отладки, который влияет на логирование ошибок.
 *
 * @uses \PhotoRigma\Include\log_in_file Записывает сообщение об ошибке в лог-файл.
 */
define('DEBUG_GALLERY', false);

/** Используется для установки корневой папки проекта */
define('WORK_DIR', rtrim(dirname(__DIR__), '/'));

try {
    /**
     * @var array $config
     * Массив с настройками сервера.
     *
     * Содержит как редактируемые пользователем параметры (например, параметры БД), так и автоматически генерируемые
     * параметры (например, URL сайта). После инициализации массив $config передаётся в свойство
     * \PhotoRigma\Classes\Work::$config.
     *
     * @uses \PhotoRigma\Classes\Work::$config
     * @noinspection PhpRedundantVariableDocTypeInspection
     */
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
    [$db, $work, $user, $template] = Bootstrap::load($config, $_SESSION);

    /**
     * Очищаем значение массива $config[], чтобы предотвратить его использование напрямую.
     * Все настройки теперь доступны только через свойство \PhotoRigma\Classes\Work::$config.
     *
     *@uses \PhotoRigma\Classes\Work::$config Свойство для хранения конфигурации проекта
     */
    unset($config);

    /**
     * Обработка действия, указанного в параметре 'action'.
     * - Установка значения $action для CLI
     * - Проверяется существование и безопасность параметра 'action'.
     * - Формируется путь к файлу действия.
     * - Если файл не существует или недоступен, используется файл по умолчанию ('main.php').
     */
    [$action, $action_file] = $work->find_action_file($_GET['action']);
    include_once $action_file;

    // Создаем токен для CSRF-защиты в полях поиска и входа
    $csrf_token = $user->csrf_token();

    // Создание шаблона
    $template->create_template();
    $template->page_header($title ?? '', $action ?? 'main', $csrf_token);
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
