<?php

/**
 * @file        include/Database.php
 * @brief       Класс для работы с базами данных через PDO.
 *
 * @author      Dark Dayver
 * @version     0.4.2
 * @date        2025-04-27
 * @namespace   Photorigma\\Classes
 *
 * @details     Этот файл содержит реализацию класса `Database`, который предоставляет методы для работы с различными
 *              базами данных:
 *              - Выполнение SQL-запросов (SELECT, JOIN, INSERT, UPDATE, DELETE, TRUNCATE).
 *              - Обработка результатов запросов, включая вывод данных.
 *              - Управление соединением с базой данных.
 *              - Обработка ошибок через централизованную систему логирования и обработки ошибок.
 *
 * @section     Database_Main_Functions Основные функции
 *              - Безопасное выполнение запросов через подготовленные выражения.
 *              - Получение метаданных запросов (например, количество затронутых строк или ID последней вставленной
 *                строки).
 *              - Форматирование даты с учётом специфики используемой СУБД.
 *              - Управление транзакциями.
 *
 * @see         PhotoRigma::Interfaces::Database_Interface Интерфейс, который реализует класс.
 * @see         PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
 *
 * @note        Этот файл является частью системы PhotoRigma и обеспечивает взаимодействие приложения с базами данных.
 *              Реализованы меры безопасности для предотвращения SQL-инъекций через использование подготовленных
 *              выражений.
 *
 * @copyright   Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать,
 *              распространять, сублицензировать и/или продавать копии программного обеспечения,
 *              а также разрешать лицам, которым предоставляется данное программное обеспечение,
 *              делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые
 *                части программного обеспечения.
 */

namespace PhotoRigma\Classes;

use Exception;
use InvalidArgumentException;
use JsonException;
use PDO;
use PDOException;
use PDOStatement;
use PhotoRigma\Interfaces\Cache_Handler_Interface;
use PhotoRigma\Interfaces\Database_Interface;
use RuntimeException;
use Throwable;

use function PhotoRigma\Include\log_in_file;

// Предотвращение прямого вызова файла
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

/**
 * @class   Database
 * @brief   Класс для работы с базами данных через PDO.
 *
 * @details Этот класс предоставляет функционал для выполнения операций с базами данных, таких как SELECT, INSERT,
 *          UPDATE, DELETE, TRUNCATE и JOIN. Поддерживаемые СУБД: MySQL (MariaDB), PostgreSQL и SQLite. Класс
 *          обеспечивает:
 *          - Безопасное выполнение запросов через подготовленные выражения.
 *          - Получение метаданных запросов (например, количество затронутых строк или ID последней вставленной строки).
 *          - Форматирование даты с учётом специфики используемой СУБД.
 *          - Управление транзакциями.
 *          - Временное изменение формата SQL для выполнения запросов в контексте другой СУББД (например, выполнение
 *            запроса в формате PostgreSQL при подключении к MySQL). Это позволяет использовать один экземпляр класса
 *            для работы с разными СУБД без необходимости создания дополнительных подключений.
 *          - Осуществляет долгосрочное кеширование временных кеш-свойств через методы Cache_Handler_Interface.
 *          Все ошибки, возникающие при работе с базой данных, обрабатываются через исключения.
 *
 * @property ?PDO        $pdo             Объект PDO для подключения к базе данных.
 * @property string|null $txt_query       Текст последнего SQL-запроса, сформированного методами класса.
 * @property object|null $res_query       Результат выполнения подготовленного выражения (PDOStatement).
 * @property int         $aff_rows        Количество строк, затронутых последним запросом (INSERT, UPDATE, DELETE).
 * @property int         $insert_id       ID последней вставленной строки после выполнения INSERT-запроса.
 * @property string      $db_type         Тип базы данных (mysql, pgsql, sqlite).
 * @property string      $db_name         Имя базы данных
 * @property array       $format_cache    Кэш для преобразования форматов даты (MariaDB -> PostgreSQL/SQLite).
 * @property array       $query_cache     Кэш для хранения результатов проверки существования запросов.
 * @property array       $unescape_cache  Кэш для хранения результатов снятия экранирования идентификаторов.
 * @property string      $current_format  Хранит текущий формат SQL (например, 'mysql', 'pgsql', 'sqlite').
 *                                        Используется для временного изменения формата SQL.
 * @property array       $allowed_formats Список поддерживаемых форматов SQL (например, ['mysql', 'pgsql', 'sqlite']).
 *                                        Определяет допустимые значения для параметра `$format` в методах класса.
 * @property Cache_Handler_Interface $cache Объект для работы с кешем
 *
 * Пример создания объекта класса Database:
 * @code
 * $db_config = [
 *     'dbtype' => 'mysql',
 *     'dbname' => 'test_db',
 *     'dbuser' => 'root',
 *     'dbpass' => 'password',
 *     'dbhost' => 'localhost',
 *     'dbport' => 3306,
 * ];
 * $cache = new \PhotoRigma\Classes\Cache_Handler();
 * $db = new \\PhotoRigma\\Classes\\Database($config);
 * @endcode
 *
 * Пример временного изменения формата SQL:
 * @code
 * // Выполнение запроса в формате PostgreSQL
 * $years_list = $db->with_sql_format('pgsql', function () use ($db) {
 *     // Форматирование даты для PostgreSQL
 *     $formatted_date = $db->format_date('data_last_edit', 'YYYY');
 *     // Выборка данных
 *     $db->select(
 *         'DISTINCT ' . $formatted_date . ' AS year',
 *         TBL_NEWS,
 *         [
 *             'order' => 'year ASC',
 *         ]
 *     );
 *     // Получение результата
 *     return $db->res_arr();
 * });
 * print_r($years_list);
 * @endcode
 * @see     Photorigma::Interfaces::Database_Interface Интерфейс, который реализует класс.
 */
class Database implements Database_Interface
{
    // Свойства класса
    private ?PDO $pdo = null;         ///< Объект PDO для подключения к базе данных
    private string|null $txt_query = null; ///< Текст последнего SQL-запроса, сформированного методами класса
    private object|null $res_query = null; ///< Результат выполнения подготовленного выражения (PDOStatement)
    private int $aff_rows = 0;        ///< Количество строк, затронутых последним запросом (INSERT, UPDATE, DELETE)
    private int $insert_id = 0;       ///< ID последней вставленной строки после выполнения INSERT-запроса
    private string $db_type;          ///< Тип базы данных (mysql, pgsql, sqlite)
    private string $db_name;          ///< Имя базы данных
    private array $format_cache = []; ///< Кэш для преобразования форматов даты (MariaDB -> PostgreSQL/SQLite)
    private array $query_cache = [];  ///< Кэш для хранения результатов проверки существования запросов
    private array $unescape_cache = []; ///< Кэш для хранения результатов снятия экранирования идентификаторов
    private string $current_format = 'mysql'; ///< Хранит текущий формат SQL (например, 'mysql', 'pgsql', 'sqlite').
    private array $allowed_formats = ['mysql', 'pgsql', 'sqlite']; ///< Список поддерживаемых форматов SQL.
    private Cache_Handler_Interface $cache; ///< Объект для работы с кешем

    /**
     * @brief   Конструктор класса.
     *
     * @details Этот метод вызывается автоматически при создании нового объекта класса.
     *          Используется для установки соединения с базой данных на основе переданных параметров.
     *          Подключение выполняется в следующем порядке:
     *          1. Для SQLite:
     *             - Проверяется путь к файлу базы данных (`dbname`).
     *             - Если файл не существует или недоступен для записи, выбрасываются соответствующие исключения.
     *             - Формируется DSN для SQLite.
     *          2. Для MySQL/PostgreSQL через сокет (если указан параметр `dbsock`):
     *             - Если путь к сокету некорректен или файл не существует, записывается предупреждение в лог.
     *             - Если подключение через сокет не удалось, выполняется попытка подключения через хост и порт.
     *          3. Для MySQL/PostgreSQL через хост и порт (если подключение через сокет не используется или не
     *          удалось).
     *          При возникновении ошибок валидации или подключения выбрасывается исключение.
     *          Важно: Если параметр `dbsock` указан, но файл сокета не существует, записывается предупреждение в лог,
     *                 и выполняется попытка подключения через хост и порт.
     *
     *          После инициализации PDO, конструктор проверяет наличие сохранённого кеша:
     *          - Методом `$this->cache->is_valid('db_cache', 37971181)` определяется актуальность кеша.
     *          - Если кеш действителен, из него загружаются данные в свойства:
     *            `$this->format_cache`, `$this->query_cache`, `$this->unescape_cache`.
     *          Это позволяет избежать повторной обработки SQL-запросов при каждом запуске скрипта.
     *
     * @callgraph
     *
     * @param array                   $db_config Массив с конфигурацией подключения:
     *                                           - string `dbtype`: Тип базы данных (mysql, pgsql, sqlite). Обязательный параметр.
     *                                           Если передан недопустимый тип, выбрасывается исключение `InvalidArgumentException`.
     *                                           - string `dbsock` (опционально): Путь к сокету.
     *                                           Если путь некорректен или файл не существует, записывается предупреждение в лог.
     *                                           Если подключение через сокет не удалось, выполняется попытка подключения через хост
     *                                           и порт.
     *                                           - string `dbname`: Имя базы данных. Обязательный параметр.
     *                                           Если имя не указано, выбрасывается исключение `InvalidArgumentException`.
     *                                           Для SQLite это путь к файлу базы данных.
     *                                           - string `dbuser`: Имя пользователя. Обязательный параметр (кроме SQLite).
     *                                           Если имя не указано, выбрасывается исключение `InvalidArgumentException`.
     *                                           - string `dbpass`: Пароль пользователя. Обязательный параметр (кроме SQLite).
     *                                           - string `dbhost`: Хост базы данных. Обязательный параметр, если не используется
     *                                           сокет. Если хост некорректен, выбрасывается исключение `InvalidArgumentException`.
     *                                           - int `dbport` (опционально): Порт базы данных.
     *                                           Если порт некорректен, выбрасывается исключение `InvalidArgumentException`.
     * @param Cache_Handler_Interface $cache     Объект, реализующий интерфейс `Cache_Handler_Interface`.
     *                                           Используется для временного хранения и загрузки кеша (`db_cache`) между запусками
     *                                           скрипта. При наличии актуального кеша он загружается в свойства:
     *                                           - `format_cache`
     *                                           - `query_cache`
     *                                           - `unescape_cache`
     *
     * @throws InvalidArgumentException Выбрасывается, если параметры конфигурации неверны:
     *                                  - Недопустимый тип базы данных (`dbtype`).
     *                                  - Не указано имя базы данных (`dbname`) или пользователь (`dbuser`) (кроме SQLite).
     *                                  - Некорректный хост (`dbhost`) или порт (`dbport`).
     *                                  Пример сообщения:
     *                                      Недопустимый тип базы данных | Значение: [dbtype]
     *                                  Пример сообщения:
     *                                      Не указан хост базы данных | Конфигурация: [json_encode($db_config)]
     * @throws RuntimeException         Выбрасывается в следующих случаях:
     *                                  - Для SQLite: файл базы данных не существует.
     *                                    Пример сообщения:
     *                                        Файл базы данных SQLite не существует | Путь: [dbname]
     *                                  - Для SQLite: файл базы данных недоступен для записи.
     *                                    Пример сообщения:
     *                                        Файл базы данных SQLite недоступен для записи | Путь: [dbname]
     * @throws PDOException             Выбрасывается, если произошла ошибка при подключении к базе данных:
     *                                  - Ошибка подключения через сокет.
     *                                  - Ошибка подключения через хост и порт.
     *                                  - Ошибка подключения к SQLite.
     *                                  Пример сообщения:
     *                                      Ошибка подключения через хост и порт | Хост: [dbhost], Порт: [dbport]
     *                                  Пример сообщения:
     *                                      Ошибка подключения к SQLite | Путь: [dbname] | Сообщение: [текст_ошибки]
     * @throws JsonException            Выбрасывается, если возникает ошибка при кодировании конфигурации в JSON.
     *                                  Пример сообщения:
     *                                      Ошибка кодирования конфигурации в JSON
     * @throws Exception                Выбрасывается, если возникает ошибка при логировании событий через
     *                                  `log_in_file()`. Пример сообщения: Ошибка записи в лог | Сообщение:
     *                                  [текст_сообщения]
     *
     * @warning Если параметр `dbsock` указан, но файл сокета не существует, выполняется попытка подключения через хост
     *          и порт. Убедитесь, что параметр `dbsock` содержит корректный путь.
     *          Для SQLite убедитесь, что файл базы данных существует и доступен для записи.
     *
     * Пример использования конструктора:
     * @code
     * // Пример для MySQL
     * $db_config_mysql = [
     *     'dbtype' => 'mysql',
     *     'dbname' => 'test_db',
     *     'dbuser' => 'root',
     *     'dbpass' => 'password',
     *     'dbhost' => 'localhost',
     *     'dbport' => 3306,
     * ];
     * $cache = new \PhotoRigma\Classes\Cache_Handler();
     * $db_mysql = new \PhotoRigma\Classes\Database($db_config_mysql, $cache);
     * @endcode
     *
     * @code
     * // Пример для SQLite
     * $db_config_sqlite = [
     *     'dbtype' => 'sqlite',
     *     'dbname' => '/path/to/database.sqlite',
     * ];
     * $cache = new \PhotoRigma\Classes\Cache_Handler();
     * $db_sqlite = new \PhotoRigma\Classes\Database($db_config_sqlite, $cache);
     * @endcode
     * @see     PhotoRigma::Classes::Database::$pdo
     *          Свойство, хранящее объект PDO для подключения к базе данных.
     * @see     PhotoRigma::Classes::Database::$db_type
     *          Свойство, хранящее тип базы данных (например, 'mysql' или 'sqlite').
     * @see     PhotoRigma::Classes::Database::$allowed_formats
     *          Массив допустимых форматов базы данных (например, 'mysql', 'sqlite', 'pgsql').
     * @see     PhotoRigma::Interfaces::Cache_Handler_Interface
     *          Интерфейс, реализуемый классом, который отвечает за работу с кешированием.
     * @see     PhotoRigma::Classes::Database::$format_cache
     *          Свойство, хранящее кеш преобразования форматов даты.
     * @see     PhotoRigma::Classes::Database::$query_cache
     *          Свойство, хранящее кеш уже выполненных запросов.
     * @see     PhotoRigma::Classes::Database::$unescape_cache
     *          Свойство, хранящее кеш результатов снятия экранирования идентификаторов.
     * @see     PhotoRigma::Interfaces::Cache_Handler_Interface::is_valid()
     *          Метод, проверяющий валидность кеша.
     * @see     PhotoRigma::Include::log_in_file()
     *          Функция для логирования ошибок.
     */
    public function __construct(array $db_config, Cache_Handler_Interface $cache)
    {
        $this->cache = $cache;

        // Проверяем наличие "догосрочного" кеша, если он существует - загружаем его.
        $db_cache = $this->cache->is_valid('db_cache', 37971181);
        if ($db_cache && is_array($db_cache)) {
            if (!empty($db_cache['format_cache']) && is_array($db_cache['format_cache'])) {
                $this->format_cache = $db_cache['format_cache'];
            }
            if (!empty($db_cache['query_cache']) && is_array($db_cache['query_cache'])) {
                $this->query_cache = $db_cache['query_cache'];
            }
            if (!empty($db_cache['unescape_cache']) && is_array($db_cache['unescape_cache'])) {
                $this->unescape_cache = $db_cache['unescape_cache'];
            }
        }

        // Проверка допустимых значений dbtype
        if (!in_array($db_config['dbtype'], $this->allowed_formats, true)) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый тип базы данных | Значение: {$db_config['dbtype']}"
            );
        }

        // Сохраняем тип используемой базы данных и её имя
        $this->db_type = $db_config['dbtype'];
        /** @noinspection UnusedConstructorDependenciesInspection */
        $this->db_name = $db_config['dbname'];

        // Определяем charset в зависимости от типа СУБД
        $charset = match ($this->db_type) {
            'mysql' => 'charset=utf8mb4',
            default => '', // Для PostgreSQL и SQLite charset не используется
        };

        // Проверка корректности dbname и dbuser (кроме SQLite)
        if ($this->db_type !== 'sqlite' && (empty($this->db_name) || empty($db_config['dbuser']))) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не указано имя базы данных или пользователь | Конфигурация: " . json_encode(
                    $db_config,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                )
            );
        }

        // Обработка SQLite
        if ($this->db_type === 'sqlite') {
            if (empty($this->db_name)) {
                throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не указан путь к файлу базы данных SQLite | Конфигурация: " . json_encode(
                        $db_config,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                    )
                );
            }

            // Проверка существования файла
            if (!is_file($this->db_name)) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл базы данных SQLite не существует | Путь: $this->db_name"
                );
            }

            // Проверка прав доступа
            if (!is_writable($this->db_name)) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл базы данных SQLite недоступен для записи | Путь: $this->db_name"
                );
            }

            // Формируем DSN для SQLite
            $dsn = "sqlite:$this->db_name";

            try {
                $this->pdo = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]);
                return; // Подключение успешно, завершаем выполнение метода
            } catch (PDOException $e) {
                throw new PDOException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка подключения к SQLite | Путь: $this->db_name | Сообщение: " . $e->getMessage(
                    )
                );
            }
        }

        // Проверка dbsock (первая попытка подключения через сокет)
        if (!empty($db_config['dbsock'])) {
            if (!is_string($db_config['dbsock']) || !file_exists($db_config['dbsock'])) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный или несуществующий путь к сокету | Путь: {$db_config['dbsock']}"
                );
            } else {
                try {
                    $dsn = "$this->db_type:unix_socket={$db_config['dbsock']};dbname=$this->db_name;";
                    $this->pdo = new PDO($dsn . $charset, $db_config['dbuser'], $db_config['dbpass'], [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                        PDO::ATTR_STRINGIFY_FETCHES  => false,
                    ]);
                    return; // Подключение успешно, завершаем выполнение метода
                } catch (PDOException $e) {
                    log_in_file(
                        __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка подключения через сокет | Сокет: {$db_config['dbsock']} | Сообщение: " . $e->getMessage(
                        )
                    );
                    // Переходим к следующему варианту подключения
                }
            }
        }

        // Проверка dbhost и dbport (вторая попытка подключения через хост и порт)
        if (empty($db_config['dbhost'])) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не указан хост базы данных | Конфигурация: " . json_encode(
                    $db_config,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                )
            );
        }
        if (!filter_var($db_config['dbhost'], FILTER_VALIDATE_IP) && !preg_match(
            '/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            $db_config['dbhost']
        ) && strtolower($db_config['dbhost']) !== 'localhost') {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный хост базы данных | Значение: {$db_config['dbhost']}"
            );
        }
        $dsn = "$this->db_type:host={$db_config['dbhost']};dbname=$this->db_name;";
        if (!empty($db_config['dbport'])) {
            if (!is_numeric($db_config['dbport']) || $db_config['dbport'] < 1 || $db_config['dbport'] > 65535) {
                throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный порт базы данных | Значение: {$db_config['dbport']}"
                );
            }
            $dsn .= ";port={$db_config['dbport']}";
        }
        try {
            $this->pdo = new PDO($dsn . $charset, $db_config['dbuser'], $db_config['dbpass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            throw new PDOException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка подключения через хост и порт | Хост: {$db_config['dbhost']}, Порт: {$db_config['dbport']} | Сообщение: " . $e->getMessage(
                )
            );
        }
    }

    /**
     * @brief       Деструктор класса Database.
     *
     * @details     Метод вызывается автоматически при уничтожении объекта.
     *              Сохраняет содержимое внутренних кешей (`$format_cache`, `$query_cache`, `$unescape_cache`)
     *              в общий кеш через вызов метода `Cache_Handler_Interface::update_cache()`.
     *              Сохранение кешей может быть отключено через параметры приложения.
     *
     * @callgraph
     *
     * @note        Данный метод не требует явного вызова — он исполняется автоматически PHP при уничтожении объекта.
     *              Убедитесь, что доступ к менеджеру кеширования (`$this->cache`) корректен и инициализирован до
     *              вызова деструктора.
     *
     * @throws JsonException         В случае невозможности сериализации кешей в методе update_cache().
     *
     * Пример использования:
     * @code
     * $db = new PhotoRigma\Classes\Database();
     * // ... работа с базой данных ...
     * unset($db); // деструктор вызывается автоматически, кеш сохраняется
     * @endcode
     * @see         PhotoRigma::Interfaces::Cache_Handler_Interface::update_cache()
     *              Интерфейс, реализуемый Cache_Handler, который сохраняет данные во внешнюю систему кеширования.
     * @see         PhotoRigma::Classes::Database::$format_cache
     *              Кэш форматов даты для оптимизации работы с SQL-запросами.
     * @see         PhotoRigma::Classes::Database::$query_cache
     *              Кэш выполненных SQL-запросов для предотвращения повторных проверок.
     * @see         PhotoRigma::Classes::Database::$unescape_cache
     *              Кэш результатов снятия экранирования идентификаторов.
     * @see         PhotoRigma::Classes::Database::$cache
     *              Объект, используемый для кеширования данных через Cache_Handler.
     * @see         PhotoRigma::Classes::Database::__construct()
     *              Используется для инициализации объекта базы данных.
     */
    public function __destruct()
    {
        $db_cache['format_cache'] = $this->format_cache;
        $db_cache['query_cache'] = $this->query_cache;
        $db_cache['unescape_cache'] = $this->unescape_cache;
        $this->cache->update_cache('db_cache', 37971181, $db_cache);
    }

    /**
     * @brief   Начинает транзакцию в базе данных через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _begin_transaction_internal().
     *          Он передаёт контекст транзакции в защищённый метод, который выполняет начало транзакции через объект PDO
     *          и записывает лог с указанием контекста. Метод предназначен для использования клиентским кодом как точка
     *          входа для начала транзакций.
     *
     * @param string $context Контекст транзакции (необязательный параметр):
     *                        - Используется для описания цели или места начала транзакции.
     *                        - По умолчанию: пустая строка.
     *
     * @throws Exception Выбрасывается, если произошла ошибка при начале транзакции.
     *                   Пример сообщения: "Ошибка при начале транзакции | Контекст: [значение]".
     *
     * @note    Этот метод является точкой входа для начала транзакций. Все проверки и обработка выполняются в
     *          защищённом методе _begin_transaction_internal().
     *
     * @warning Убедитесь, что соединение с базой данных установлено перед вызовом метода.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $db = new \PhotoRigma\Classes\Database();
     * $db->begin_transaction('Сохранение данных пользователя');
     * // Лог: [DB] Транзакция начата | Контекст: Сохранение данных пользователя
     * @endcode
     * @see     PhotoRigma::Classes::Database::_begin_transaction_internal()
     *          Защищённый метод, который выполняет основную логику начала транзакции.
     */
    public function begin_transaction(string $context = ''): void
    {
        $this->_begin_transaction_internal($context);
    }

    /**
     * @brief   Начинает транзакцию в базе данных и записывает лог с указанием контекста.
     *
     * @details Этот метод выполняет следующие действия:
     *          1. Начинает транзакцию в базе данных через объект PDO ($this->pdo).
     *          2. Записывает лог с указанием контекста начала транзакции.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика метода вызывается через публичный метод-редирект `begin_transaction()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $context Контекст транзакции (необязательный параметр).
     *                        Используется для описания цели или места начала транзакции.
     *                        По умолчанию: пустая строка.
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws Exception Выбрасывается, если произошла ошибка при начале транзакции.
     *      Пример сообщения:
     *          Ошибка при начале транзакции | Контекст: [значение]
     *
     * @note    Метод использует логирование для записи информации о начале транзакции.
     * @warning Убедитесь, что соединение с базой данных установлено перед вызовом метода.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $this->_begin_transaction_internal('Сохранение данных пользователя');
     * // Лог: [DB] Транзакция начата | Контекст: Сохранение данных пользователя
     * @endcode
     * @see     PhotoRigma::Classes::Database::begin_transaction()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Database::$pdo
     *          Объект PDO для подключения к базе данных.
     * @see     PhotoRigma::Include::log_in_file()
     *          Логирует сообщения в файл.
     */
    protected function _begin_transaction_internal(string $context = ''): void
    {
        $this->pdo->beginTransaction();
        log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | [DB] Транзакция начата | Контекст: $context"
        );
    }

    /**
     * @brief   Подтверждает транзакцию в базе данных через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _commit_transaction_internal().
     *          Он передаёт контекст транзакции в защищённый метод, который выполняет подтверждение транзакции через
     *          объект PDO и записывает лог с указанием контекста. Метод предназначен для использования клиентским
     *          кодом как точка входа для подтверждения транзакций.
     *
     * @param string $context Контекст транзакции (необязательный параметр):
     *                        - Используется для описания цели или места подтверждения транзакции.
     *                        - По умолчанию: пустая строка.
     *
     * @throws Exception Выбрасывается, если произошла ошибка при подтверждении транзакции.
     *                   Пример сообщения: "Ошибка при подтверждении транзакции | Контекст: [значение]".
     *
     * @note    Этот метод является точкой входа для подтверждения транзакций. Все проверки и обработка выполняются в
     *          защищённом методе _commit_transaction_internal().
     *
     * @warning Убедитесь, что транзакция была начата перед вызовом этого метода.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $db = new \PhotoRigma\Classes\Database();
     * $db->commit_transaction('Сохранение данных пользователя');
     * // Лог: [DB] Транзакция подтверждена | Контекст: Сохранение данных пользователя
     * @endcode
     * @see     PhotoRigma::Classes::Database::_commit_transaction_internal()
     *          Защищённый метод, который выполняет основную логику подтверждения транзакции.
     */
    public function commit_transaction(string $context = ''): void
    {
        $this->_commit_transaction_internal($context);
    }

    /**
     * @brief   Подтверждает транзакцию в базе данных и записывает лог с указанием контекста.
     *
     * @details Этот метод выполняет следующие действия:
     *          1. Подтверждает транзакцию в базе данных через объект PDO ($this->pdo).
     *          2. Записывает лог с указанием контекста подтверждения транзакции.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика метода вызывается через публичный метод-редирект `commit_transaction()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $context Контекст транзакции (необязательный параметр).
     *                        Используется для описания цели или места подтверждения транзакции.
     *                        По умолчанию: пустая строка.
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws Exception Выбрасывается, если произошла ошибка при подтверждении транзакции.
     *      Пример сообщения:
     *          Ошибка при подтверждении транзакции | Контекст: [значение]
     *
     * @note    Метод использует логирование для записи информации о подтверждении транзакции.
     * @warning Убедитесь, что транзакция была начата перед вызовом этого метода.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $this->_commit_transaction_internal('Сохранение данных пользователя');
     * // Лог: [DB] Транзакция подтверждена | Контекст: Сохранение данных пользователя
     * @endcode
     * @see     PhotoRigma::Classes::Database::commit_transaction()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Database::$pdo
     *          Объект PDO для подключения к базе данных.
     * @see     PhotoRigma::Include::log_in_file()
     *          Логирует сообщения в файл.
     */
    protected function _commit_transaction_internal(string $context = ''): void
    {
        $this->pdo->commit();
        log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | [DB] Транзакция подтверждена | Контекст: $context"
        );
    }

    /**
     * @brief   Удаляет данные из таблицы через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _delete_internal().
     *          Он передаёт имя таблицы и опции в защищённый метод, который выполняет формирование и выполнение
     *          SQL-запроса на удаление данных. Безопасность обеспечивается обязательным указанием условия `where`.
     *          Запрос без условия
     *          `where` не будет выполнен. Метод предназначен для использования клиентским кодом как точка входа для
     *          удаления данных.
     *
     * @param string $from_tbl        Имя таблицы, из которой необходимо удалить данные:
     *                                - Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                                символов.
     * @param array  $options         Массив опций для формирования запроса. Поддерживаемые ключи:
     *                                - where (string|array): Условие WHERE. Может быть строкой (например, "status =
     *                                1")
     *                                или ассоциативным массивом (например, ["id" => 1, "status" => "active"]).
     *                                Обязательный параметр для безопасности. Без условия WHERE запрос не будет
     *                                выполнен.
     *                                - order (string): Сортировка ORDER BY. Должна быть строкой с именами полей и
     *                                направлением (например, "created_at DESC"). Может использоваться только вместе с
     *                                `limit`. Если указан только один из этих ключей, оба игнорируются.
     *                                - limit (int|string): Ограничение LIMIT. Может быть числом (например, 10) или
     *                                строкой с диапазоном (например, "0, 10"). Может использоваться только вместе с
     *                                `order`. Если указан только один из этих ключей, оба игнорируются.
     *                                - params (array): Параметры для подготовленного выражения. Ассоциативный массив
     *                                значений, используемых в запросе (например, [":id" => 1, ":status" => "active"]).
     *                                Использование параметров `params` является обязательным для подготовленных
     *                                выражений, так как это обеспечивает защиту от SQL-инъекций и совместимость с
     *                                различными СУБД.
     *                                - group (string): Группировка GROUP BY. Не поддерживается в запросах DELETE и
     *                                удаляется с записью в лог.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$from_tbl` не является строкой.
     *                                  - `$options` не является массивом.
     *                                  - Отсутствует обязательное условие `where` в массиве `$options`.
     *                                  Пример сообщения:
     *                                      Запрос DELETE без условия WHERE запрещён | Причина: Соображения
     *                                      безопасности
     * @throws JsonException           Выбрасывается при ошибках кодирования JSON (например, при записи в лог).
     *                                  Пример сообщения:
     *                                      Ошибка при кодировании JSON | Переданные опции: {"group":"users"}
     * @throws Exception               Выбрасывается при ошибках выполнения запроса или записи в лог.
     *                                  Пример сообщения:
     *                                      Ошибка выполнения запроса | SQL: DELETE FROM users WHERE id = :id AND
     *                                      status = :status
     *
     * @note    Этот метод является точкой входа для удаления данных. Все проверки и обработка выполняются в
     *          защищённом методе _delete_internal().
     *
     * @warning Метод чувствителен к корректности входных данных. Неверные типы данных или некорректные значения могут
     *          привести к выбросу исключения. Безопасность обеспечивается обязательным указанием условия `where`.
     *          Запрос без условия `where` не будет выполнен. Ключи `group`, `order` и `limit` проверяются на
     *          корректность. Недопустимые ключи удаляются с записью в лог.
     *          Использование параметров `params` для подготовленных выражений является обязательным для защиты от
     *          SQL-инъекций и обеспечения совместимости с различными СУБД. Игнорирование этого правила может привести
     *          к уязвимостям безопасности и неправильной работе с базой данных.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $db = new \PhotoRigma\Classes\Database();
     * $db->delete('users', [
     *     'where' => ['id' => 1],
     * ]);
     * @endcode
     * @see     PhotoRigma::Classes::Database::_delete_internal()
     *          Защищённый метод, который выполняет основную логику формирования и выполнения SQL-запроса.
     */
    public function delete(string $from_tbl, array $options = []): bool
    {
        return $this->_delete_internal($from_tbl, $options);
    }

    /**
     * @brief   Формирует SQL-запрос на удаление данных из таблицы, проверяет входные данные, обрабатывает недопустимые
     *          ключи, сохраняет текст запроса в свойстве `$txt_query` и выполняет его.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет типы входных данных: `$from_tbl` (строка), `$options` (массив).
     *          2. Проверяет наличие обязательного условия `where` в массиве `$options`. Если условие отсутствует,
     *             выбрасывается исключение для предотвращения случайного удаления всех данных.
     *          3. Проверяет и удаляет недопустимые ключи из массива `$options`:
     *             - Ключ `group` не поддерживается в запросах DELETE и удаляется с записью в лог.
     *             - Ключи `order` и `limit` должны использоваться совместно. Если указан только один из них, оба
     *               удаляются с записью в лог.
     *          4. Формирует базовый SQL-запрос на удаление данных и сохраняет его в свойстве `$txt_query`.
     *          5. Добавляет условия WHERE, ORDER BY и LIMIT (если они корректны) через метод `build_conditions`.
     *          6. Выполняет сформированный запрос через метод `execute_query`, передавая параметры `params` для
     *             подготовленного выражения.
     *          Использование параметров `params` является обязательным для подготовленных выражений, так как это
     *          обеспечивает защиту от SQL-инъекций и совместимость с различными СУБД.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `delete()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $from_tbl        Имя таблицы, из которой необходимо удалить данные.
     *                                Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                                символов.
     * @param array  $options         Массив опций для формирования запроса. Поддерживаемые ключи:
     *                                - where (string|array): Условие WHERE. Может быть строкой (например, "status =
     *                                1")
     *                                или ассоциативным массивом (например, ["id" => 1, "status" => "active"]).
     *                                Обязательный параметр для безопасности. Без условия WHERE запрос не будет
     *                                выполнен.
     *                                - order (string): Сортировка ORDER BY. Должна быть строкой с именами полей и
     *                                направлением (например, "created_at DESC"). Может использоваться только вместе с
     *                                `limit`. Если указан только один из этих ключей, оба игнорируются.
     *                                - limit (int|string): Ограничение LIMIT. Может быть числом (например, 10) или
     *                                строкой с диапазоном (например, "0, 10"). Может использоваться только вместе с
     *                                `order`. Если указан только один из этих ключей, оба игнорируются.
     *                                - params (array): Параметры для подготовленного выражения. Ассоциативный массив
     *                                значений, используемых в запросе (например, [":id" => 1, ":status" => "active"]).
     *                                Использование параметров `params` является обязательным для подготовленных
     *                                выражений, так как это обеспечивает защиту от SQL-инъекций и совместимость с
     *                                различными СУБД.
     *                                - group (string): Группировка GROUP BY. Не поддерживается в запросах DELETE и
     *                                удаляется с записью в лог.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *              В случае ошибки выбрасывается исключение.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$from_tbl` не является строкой.
     *                                  - `$options` не является массивом.
     *                                  - Отсутствует обязательное условие `where` в массиве `$options`.
     *      Пример сообщения:
     *          Запрос DELETE без условия WHERE запрещён | Причина: Соображения безопасности
     * @throws JsonException Выбрасывается при ошибках кодирования JSON (например, при записи в лог).
     *      Пример сообщения:
     *          Ошибка при кодировании JSON | Переданные опции: {"group":"users"}
     * @throws Exception Выбрасывается при ошибках выполнения запроса или записи в лог.
     *      Пример сообщения:
     *          Ошибка выполнения запроса | SQL: DELETE FROM users WHERE id = :id AND status = :status
     *
     * @warning Метод чувствителен к корректности входных данных. Неверные типы данных или некорректные значения могут
     *          привести к выбросу исключения. Безопасность обеспечивается обязательным указанием условия `where`.
     *          Запрос без условия `where` не будет выполнен. Ключи `group`, `order` и `limit` проверяются на
     *          корректность. Недопустимые ключи удаляются с записью в лог.
     *          Использование параметров `params` для подготовленных выражений является обязательным для защиты от
     *          SQL-инъекций и обеспечения совместимости с различными СУБД. Игнорирование этого правила может привести
     *          к уязвимостям безопасности и неправильной работе с базой данных.
     *
     * Пример использования метода _delete_internal():
     * @code
     * // Безопасное использование с параметрами `params`
     * $this->_delete_internal('users', [
     *     'where' => 'id = :id AND status = :status',
     *     'params' => [':id' => 1, ':status' => 'active'],
     * ]);
     *
     * // Безопасное использование с ассоциативным массивом в `where`
     * $this->_delete_internal('users', [
     *     'where' => ['id' => 1, 'status' => 'active'],
     * ]);
     * @endcode
     * @see     PhotoRigma::Classes::Database::delete()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Database::execute_query()
     *          Выполняет SQL-запрос.
     * @see     PhotoRigma::Classes::Database::build_conditions()
     *          Формирует условия WHERE, ORDER BY и LIMIT для запроса.
     * @see     PhotoRigma::Classes::Database::$txt_query
     *          Свойство, в которое помещается текст SQL-запроса.
     * @see     PhotoRigma::Include::log_in_file()
     *          Логирует ошибки.
     */
    protected function _delete_internal(string $from_tbl, array $options = []): bool
    {
        // === 1. Валидация аргументов ===
        if (empty($options['where'])) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Запрос DELETE без условия WHERE запрещён | Причина: Соображения безопасности"
            );
        }

        // === 2. Проверка и удаление недопустимых ключей ===
        if (isset($options['group'])) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Был использован GROUP BY в DELETE | Переданные опции: " . json_encode(
                    $options,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                )
            );
            unset($options['group']); // Удаляем ключ 'group'
        }

        if ((isset($options['order']) && !isset($options['limit'])) || (!isset($options['order']) && isset($options['limit']))) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | ORDER BY или LIMIT используются некорректно в DELETE | Переданные опции: " . json_encode(
                    $options,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                )
            );
            unset($options['order'], $options['limit']); // Удаляем ключи 'order' и 'limit'
        }

        // === 3. Формирование базового запроса ===
        $this->txt_query = "DELETE FROM $from_tbl";

        // === 4. Добавление условий ===
        [$conditions, $params] = $this->build_conditions($options);
        $this->txt_query .= $conditions;

        // === 5. Выполнение запроса ===
        return $this->execute_query($params);
    }

    /**
     * @brief   Формирует строку дополнений для SQL-запроса (например, WHERE, GROUP BY, ORDER BY, LIMIT).
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Обрабатывает условие `WHERE`:
     *             - Если `where` является строкой, она используется как есть.
     *             - Если `where` является массивом, он преобразуется в SQL-условие через `implode(' AND ', ...)`.
     *          2. Обрабатывает группировку `GROUP BY`:
     *             - Если `group` является строкой, добавляется к строке запроса.
     *          3. Обрабатывает сортировку `ORDER BY`:
     *             - Если `order` является строкой, добавляется к строке запроса.
     *          4. Обрабатывает ограничение `LIMIT`:
     *             - Если `limit` является числом, добавляется напрямую.
     *             - Если `limit` является строкой формата "OFFSET, COUNT", она разбирается и добавляется.
     *          5. Возвращает результат:
     *             - Строка дополнений (например, WHERE, GROUP BY, ORDER BY, LIMIT).
     *             - Обновлённый массив параметров для подготовленного выражения.
     *
     * @callergraph
     * @callgraph
     *
     * @param array $options     Опции запроса:
     *                           - where (string|array): Условие WHERE.
     *                           Может быть строкой или ассоциативным массивом.
     *                           Если передан неверный тип, выбрасывается исключение InvalidArgumentException.
     *                           - group (string): Группировка GROUP BY.
     *                           Должна быть строкой. Если передан неверный тип, выбрасывается исключение
     *                           InvalidArgumentException.
     *                           - order (string): Сортировка ORDER BY.
     *                           Должна быть строкой. Если передан неверный тип, выбрасывается исключение
     *                           InvalidArgumentException.
     *                           - limit (int|string): Ограничение LIMIT.
     *                           Должно быть числом или строкой формата 'OFFSET, COUNT'.
     *                           Если передан неверный тип, выбрасывается исключение InvalidArgumentException.
     *                           - params (array): Параметры для подготовленного выражения (необязательно).
     *                           Если параметры не соответствуют условиям, выбрасывается исключение
     *                           InvalidArgumentException.
     *
     * @return array Массив с двумя элементами:
     *               - string $conditions - Строка дополнений (например, WHERE, GROUP BY, ORDER BY, LIMIT).
     *               - array $params - Обновлённый массив параметров для подготовленного выражения.
     *
     * @throws InvalidArgumentException Если параметры имеют недопустимый тип.
     *
     * Пример использования метода build_conditions():
     * @code
     * $options = [
     *     'where' => ['id' => 1, 'status' => 'active'],
     *     'group' => 'category_id',
     *     'order' => 'created_at DESC',
     *     'limit' => 10,
     *     'params' => [':id' => 1, ':status' => 'active'],
     * ];
     * [$conditions, $params] = $this->build_conditions($options);
     * echo "Условия: $conditions\n";
     * print_r($params);
     * @endcode
     * @see     PhotoRigma::Classes::Database::update()
     *          Метод, который вызывает build_conditions() для формирования UPDATE-запроса.
     * @see     PhotoRigma::Classes::Database::select()
     *          Метод, который вызывает build_conditions() для формирования SELECT-запроса.
     * @see     PhotoRigma::Classes::Database::join()
     *          Метод, который вызывает build_conditions() для формирования JOIN-запроса.
     * @see     PhotoRigma::Classes::Database::delete()
     *          Метод, который вызывает build_conditions() для формирования DELETE-запроса.
     */
    private function build_conditions(array $options): array
    {
        $conditions = '';
        $params = $options['params'] ?? [];

        // === 1. Обработка WHERE ===
        if (isset($options['where'])) {
            if (is_string($options['where'])) {
                // Если where — строка, добавляем её напрямую
                $conditions .= ' WHERE ' . $options['where'];
            } elseif (is_array($options['where'])) {
                // Если where — массив, обрабатываем его
                $conditions .= ' WHERE ' . implode(' AND ', $options['where']);
            } else {
                throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверное условие 'where' | Ожидалась строка или массив, получено: " . gettype(
                        $options['where']
                    )
                );
            }
        }

        // === 2. Обработка GROUP BY ===
        if (isset($options['group'])) {
            if (is_string($options['group'])) {
                $conditions .= ' GROUP BY ' . $options['group'];
            } else {
                throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверное значение 'group' | Ожидалась строка, получено: " . gettype(
                        $options['group']
                    )
                );
            }
        }

        // === 3. Обработка ORDER BY ===
        if (isset($options['order'])) {
            if (is_string($options['order'])) {
                $conditions .= ' ORDER BY ' . $options['order'];
            } else {
                throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверное значение 'order' | Ожидалась строка, получено: " . gettype(
                        $options['order']
                    )
                );
            }
        }

        // === 4. Обработка LIMIT ===
        if (isset($options['limit']) && $options['limit'] !== false) {
            if (is_numeric($options['limit'])) {
                $conditions .= ' LIMIT ' . (int)$options['limit'];
            } elseif (is_string($options['limit']) && preg_match('/^\d+\s*,\s*\d+$/', $options['limit'])) {
                [$offset, $count] = array_map('intval', explode(',', $options['limit']));
                $conditions .= ' LIMIT ' . $offset . ', ' . $count;
            } else {
                throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверное значение 'limit' | Ожидалось число или строка формата 'OFFSET, COUNT', получено: " . gettype(
                        $options['limit']
                    ) . " ({$options['limit']})"
                );
            }
        }

        return [$conditions, $params];
    }

    /**
     * @brief   Выполняет SQL-запрос с использованием подготовленных выражений.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет состояние запроса и типы аргументов:
     *             - Убедитесь, что `$res_query` является экземпляром `PDOStatement`.
     *             - Проверяется, что `$txt_query` является строкой.
     *             - Проверяется, что `$params` является массивом.
     *          2. Очищает внутренние свойства для вывода результата запроса:
     *             - Обнуляет `$res_query`, `$aff_rows`, `$insert_id`.
     *          3. Преобразует запрос в нужный формат СУБД с помощью метода `convert_query()`.
     *          4. Выполняет EXPLAIN ANALYZE (если включено):
     *             - Использует метод `log_explain()` для анализа запроса.
     *          5. Подготавливает и выполняет SQL-запрос с использованием PDO:
     *             - Использует `$pdo->prepare()` для подготовки запроса.
     *             - Выполняет запрос с переданными параметрами.
     *             - Получает количество затронутых строк (`$aff_rows`) и ID последней вставленной записи
     *               (`$insert_id`).
     *          6. Логирует медленные запросы:
     *             - Измеряет время выполнения запроса.
     *             - Если время выполнения превышает пороговое значение (200 мс), логирует запрос с помощью
     *               `log_query()`.
     *             - Логирует запросы с пустыми плейсхолдерами.
     *          7. Очищает строку запроса после выполнения.
     *          8. Возвращает результат выполнения запроса:
     *             - Возвращает `true`, если запрос успешно выполнен.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @param array $params Ассоциативный массив параметров для подготовленного выражения (необязательно).
     *                      Пример: [':id' => 1].
     *                      Ограничения: параметры должны соответствовать плейсхолдерам в запросе.
     *
     * @return bool Возвращает `true`, если запрос успешно выполнен.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$res_query` не является экземпляром `PDOStatement`.
     *                                  - `$txt_query` не является строкой.
     *                                  - `$params` не является массивом.
     *                                  Пример сообщения:
     *                                      Некорректное состояние результата запроса | Текущее значение: [тип]
     * @throws PDOException Выбрасывается, если возникает ошибка при выполнении запроса.
     *                                  Пример сообщения:
     *                                      Ошибка при выполнении SQL-запроса | Запрос: [текст запроса]
     * @throws Exception Выбрасывается, если возникают ошибки при вызове метода `log_explain()`.
     *                                  Пример сообщения:
     *                                      Ошибка при выполнении EXPLAIN ANALYZE | Запрос: [текст запроса]
     *
     * @note    Метод зависит от корректной конфигурации базы данных и использует следующие константы для настройки
     *          поведения:
     *          - `SQL_LOG`: Включает или отключает логирование запросов:
     *                       - Логируются медленные запросы (время выполнения превышает пороговое значение, 200 мс).
     *                       - Логируются запросы без плейсхолдеров.
     *          - `SQL_ANALYZE`: Включает или отключает анализ запросов с помощью команды EXPLAIN/EXPLAIN ANALYZE.
     *                           Анализ выполняется перед выполнением запроса для сбора статистики производительности.
     *          Эти константы позволяют настраивать поведение метода в зависимости от требований к отладке и
     *          мониторингу.
     *
     * @warning Убедитесь, что входные данные корректны. Невалидные данные могут привести к исключениям.
     *
     * Пример использования метода execute_query():
     * @code
     * // Выполнение SELECT-запроса
     * $this->txt_query = "SELECT * FROM users WHERE id = :id";
     * $params = [':id' => 1];
     * $success = $this->execute_query($params);
     * if ($success) {
     *     echo "Запрос выполнен успешно!";
     * } else {
     *     echo "Ошибка выполнения запроса.";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Database::$pdo
     *         Объект PDO, используемый для выполнения запроса.
     * @see     PhotoRigma::Classes::Database::$res_query
     *         Результат подготовленного выражения.
     * @see     PhotoRigma::Classes::Database::$aff_rows
     *         Количество затронутых строк после выполнения запроса.
     * @see     PhotoRigma::Classes::Database::$insert_id
     *         ID последней вставленной записи.
     * @see     PhotoRigma::Classes::Database::$txt_query
     *         Свойство, в которое помещается текст SQL-запроса.
     * @see     PhotoRigma::Classes::Database::delete()
     *         Метод, который вызывает execute_query() для выполнения DELETE-запроса.
     * @see     PhotoRigma::Classes::Database::truncate()
     *         Метод, который вызывает execute_query() для выполнения TRUNCATE-запроса.
     * @see     PhotoRigma::Classes::Database::update()
     *         Метод, который вызывает execute_query() для выполнения UPDATE-запроса.
     * @see     PhotoRigma::Classes::Database::insert()
     *         Метод, который вызывает execute_query() для выполнения INSERT-запроса.
     * @see     PhotoRigma::Classes::Database::select()
     *         Метод, который вызывает execute_query() для выполнения SELECT-запроса.
     * @see     PhotoRigma::Classes::Database::join()
     *         Метод, который вызывает execute_query() для выполнения JOIN-запроса.
     * @see     PhotoRigma::Classes::Database::convert_query()
     *         Метод для преобразования запроса в нужный формат СУБД.
     * @see     PhotoRigma::Classes::Database::log_explain()
     *         Метод для выполнения EXPLAIN ANALYZE.
     * @see     PhotoRigma::Classes::Database::log_query()
     *         Метод для логирования медленных запросов и запросов без плейсхолдеров.
     */
    private function execute_query(array $params = []): bool
    {
        // Валидация состояния запроса и аргументов
        if (isset($this->res_query) && !($this->res_query instanceof PDOStatement)) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное состояние результата запроса | Текущее значение: " . gettype(
                    $this->res_query
                )
            );
        }
        // Очистка внутренних свойств для вывода результата запроса
        $this->res_query = null;
        $this->aff_rows = 0;
        $this->insert_id = 0;
        // Валидация аргументов
        if (!is_string($this->txt_query)) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный тип SQL-запроса | Ожидалась строка, получено: " . gettype(
                    $this->txt_query
                )
            );
        }

        // Финальное преобразование запроса в нужный формат СУБД.
        $this->convert_query();

        // Выполняем EXPLAIN ANALYZE, если включено
        if (SQL_ANALYZE) {
            $this->log_explain($params);
        }

        // Начало замера времени
        $start_time = microtime(true);
        $this->res_query = $this->pdo->prepare($this->txt_query);
        $this->res_query->execute($params);
        $this->aff_rows = $this->res_query->rowCount();
        // Проверяем, является ли запрос INSERT
        if (stripos(trim($this->txt_query), 'INSERT') === 0) {
            $this->insert_id = (int)$this->pdo->lastInsertId();
        }
        // Конец замера времени
        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000; // Время в миллисекундах
        // Логирование медленных запросов
        $slow_query_threshold = 200; // Порог в миллисекундах
        if (SQL_LOG && $execution_time > $slow_query_threshold) {
            // Сохраняем запрос в БД
            $this->log_query('slow', $execution_time);
        } elseif (SQL_LOG && empty($params)) {
            // Сохраняем запрос в БД с пустым плейсхолдером
            $this->log_query('no_placeholders', $execution_time);
        }
        // Очистка строки запроса после её выполнения
        $this->txt_query = null;
        return true;
    }

    /**
     * @brief   Выполняет преобразование SQL-запроса в зависимости от текущего формата и типа СУБД.
     *
     * @details Этот метод выполняет преобразование SQL-запроса для обеспечения совместимости с целевой СУБД:
     *          1. Проверяет, совпадает ли текущий формат строки (`$current_format`) с типом базы данных (`$db_type`).
     *             - Если форматы совпадают, запрос остается без изменений.
     *          2. Если форматы не совпадают, вызывает соответствующий метод для преобразования:
     *             - Для MySQL используется метод `convert_from_mysql_to`.
     *             - Для PostgreSQL используется метод `convert_from_pgsql_to`.
     *             - Для SQLite используется метод `convert_from_sqlite_to`.
     *          3. Если текущий формат не поддерживается, выбрасывается исключение.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *          Вызывается из метода `execute_query`.
     *
     * @callergraph
     *
     * @return void Метод ничего не возвращает. Результат преобразования сохраняется в свойстве `$txt_query`.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - Текущий формат строки не поддерживается.
     *                                    Пример сообщения:
     *                                        Не поддерживаемый формат строки | Тип: [current_format]
     *
     * @note    Метод использует свойства `$current_format` и `$db_type` для определения необходимости преобразования.
     *          Преобразованный запрос сохраняется в свойстве `$txt_query`.
     *
     * @warning Убедитесь, что свойство `$current_format` содержит допустимое значение.
     *          Недопустимый формат может привести к исключениям.
     *
     * Пример вызова метода внутри класса:
     * @code
     * // Исходный запрос в формате MySQL
     * $this->txt_query = "SELECT * FROM `users` WHERE id = 1";
     * $this->current_format = 'mysql';
     * $this->db_type = 'pgsql';
     *
     * // Преобразование запроса для PostgreSQL
     * $this->convert_query();
     * echo $this->txt_query; // Результат: SELECT * FROM "users" WHERE id = 1
     * @endcode
     * @see     PhotoRigma::Classes::Database::$txt_query
     *          Свойство, содержащее текст SQL-запроса.
     * @see     PhotoRigma::Classes::Database::$current_format
     *          Текущий формат строки запроса.
     * @see     PhotoRigma::Classes::Database::$db_type
     *          Тип используемой СУБД.
     * @see     PhotoRigma::Classes::Database::convert_from_mysql_to()
     *          Метод для преобразования запросов из формата MySQL.
     * @see     PhotoRigma::Classes::Database::convert_from_pgsql_to()
     *          Метод для преобразования запросов из формата PostgreSQL.
     * @see     PhotoRigma::Classes::Database::convert_from_sqlite_to()
     *          Метод для преобразования запросов из формата SQLite.
     * @see     PhotoRigma::Classes::Database::execute_query()
     *          Метод, из которого вызывается данный метод.
     */
    private function convert_query(): void
    {
        // Если формат строки совпадает с типом базы данных, ничего не меняем
        if ($this->current_format === $this->db_type) {
            return;
        }

        // Выполняем преобразование в зависимости от текущего формата и типа базы данных
        match ($this->current_format) {
            'mysql'  => $this->convert_from_mysql_to($this->db_type),
            'pgsql'  => $this->convert_from_pgsql_to($this->db_type),
            'sqlite' => $this->convert_from_sqlite_to($this->db_type),
            default  => throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Не поддерживаемый формат строки | Тип: $this->current_format"
            ),
        };
    }

    /**
     * @brief   Производит преобразование SQL-запроса из формата MySQL в целевой формат СУБД.
     *
     * @details Этот метод выполняет преобразование SQL-запроса из формата MySQL в формат целевой СУБД:
     *          1. Проверяет тип целевой СУБД через параметр `$target_db_type`.
     *          2. Для PostgreSQL и SQLite заменяет обратные кавычки (`) на двойные кавычки (") или удаляет их.
     *             - Экранированные кавычки (например, \\` ) остаются без изменений.
     *          3. Если тип СУБД не поддерживается, выбрасывается исключение.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *          Вызывается из метода `convert_query`.
     *
     * @callergraph
     *
     * @param string $target_db_type Тип целевой СУБД:
     *                               - Поддерживаемые значения: 'pgsql', 'sqlite'.
     *                               - Пример: 'pgsql' для PostgreSQL или 'sqlite' для SQLite.
     *                               Ограничения: входной тип должен быть допустимым значением.
     *
     * @return void Метод ничего не возвращает. Результат преобразования сохраняется в свойстве `$txt_query`.
     *
     * @throws InvalidArgumentException Выбрасывается, если тип СУБД не поддерживается.
     *                                  Пример сообщения:
     *                                      Неподдерживаемый тип базы данных | Тип: [target_db_type]
     *
     * @note    Метод использует регулярное выражение для поиска и замены обратных кавычек.
     *          Преобразованный запрос сохраняется в свойстве `$txt_query`.
     *
     * @warning Убедитесь, что входной тип `$target_db_type` соответствует поддерживаемым значениям.
     *          Недопустимый тип может привести к исключениям.
     *
     * Пример вызова метода внутри класса:
     * @code
     * // Исходный запрос в формате MySQL
     * $this->txt_query = "SELECT * FROM `users` WHERE id = 1";
     *
     * // Преобразование запроса для PostgreSQL
     * $this->convert_from_mysql_to('pgsql');
     * echo $this->txt_query; // Результат: SELECT * FROM "users" WHERE id = 1
     *
     * // Преобразование запроса для SQLite
     * $this->convert_from_mysql_to('sqlite');
     * echo $this->txt_query; // Результат: SELECT * FROM users WHERE id = 1
     * @endcode
     * @see     PhotoRigma::Classes::Database::$txt_query
     *          Свойство, содержащее текст SQL-запроса.
     * @see     PhotoRigma::Classes::Database::convert_query()
     *          Метод, из которого вызывается данный метод.
     */
    private function convert_from_mysql_to(string $target_db_type): void
    {
        $this->txt_query = match ($target_db_type) {
            'pgsql', 'sqlite' => preg_replace_callback(
                '/(?<!\\\\)`/', // Ищем обратные апострофы, которые не экранированы
                static fn ($matches) => '"', // Заменяем их на двойные кавычки
                $this->txt_query
            ),
            default => throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Неподдерживаемый тип базы данных | Тип: $target_db_type"
            ),
        };
    }

    /**
     * @brief   Производит преобразование SQL-запроса из формата PostgreSQL в целевой формат СУБД.
     *
     * @details Этот метод выполняет преобразование SQL-запроса из формата PostgreSQL в формат целевой СУБД:
     *          1. Проверяет тип целевой СУБД через параметр `$target_db_type`.
     *          2. Для MySQL заменяет двойные кавычки (") на обратные апострофы (`).
     *             - Экранированные кавычки (например, \\") остаются без изменений.
     *          3. Для SQLite запрос остается без изменений.
     *          4. Если тип СУБД не поддерживается, выбрасывается исключение.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *          Вызывается из метода `convert_query`.
     *
     * @callergraph
     *
     * @param string $target_db_type Тип целевой СУБД:
     *                               - Поддерживаемые значения: 'mysql', 'sqlite'.
     *                               - Пример: 'mysql' для MySQL или 'sqlite' для SQLite.
     *                               Ограничения: входной тип должен быть допустимым значением.
     *
     * @return void Метод ничего не возвращает. Результат преобразования сохраняется в свойстве `$txt_query`.
     *
     * @throws InvalidArgumentException Выбрасывается, если тип СУБД не поддерживается.
     *                                  Пример сообщения:
     *                                      Неподдерживаемый тип базы данных | Тип: [target_db_type]
     *
     * @note    Метод использует регулярное выражение для поиска и замены двойных кавычек.
     *          Преобразованный запрос сохраняется в свойстве `$txt_query`.
     *
     * @warning Убедитесь, что входной тип `$target_db_type` соответствует поддерживаемым значениям.
     *          Недопустимый тип может привести к исключениям.
     *
     * Пример вызова метода внутри класса:
     * @code
     * // Исходный запрос в формате PostgreSQL
     * $this->txt_query = 'SELECT * FROM "users" WHERE id = 1';
     *
     * // Преобразование запроса для MySQL
     * $this->convert_from_pgsql_to('mysql');
     * echo $this->txt_query; // Результат: SELECT * FROM `users` WHERE id = 1
     *
     * // Преобразование запроса для SQLite
     * $this->convert_from_pgsql_to('sqlite');
     * echo $this->txt_query; // Результат: SELECT * FROM "users" WHERE id = 1
     * @endcode
     * @see     PhotoRigma::Classes::Database::$txt_query
     *          Свойство, содержащее текст SQL-запроса.
     * @see     PhotoRigma::Classes::Database::convert_query()
     *          Метод, из которого вызывается данный метод.
     */
    private function convert_from_pgsql_to(string $target_db_type): void
    {
        $this->txt_query = match ($target_db_type) {
            'mysql'  => preg_replace_callback(
                '/(?<!\\\\)"/', // Ищем двойные кавычки, которые не экранированы
                static fn ($matches) => '`', // Заменяем их на обратные апострофы
                $this->txt_query
            ),
            'sqlite' => $this->txt_query, // Для SQLite ничего не меняем
            default => throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Неподдерживаемый тип базы данных | Тип: $target_db_type"
            ),
        };
    }

    /**
     * @brief   Производит преобразование SQL-запроса из формата SQLite в целевой формат СУБД.
     *
     * @details Этот метод выполняет преобразование SQL-запроса из формата SQLite в формат целевой СУБД:
     *          1. Проверяет тип целевой СУБД через параметр `$target_db_type`.
     *          2. Для MySQL заменяет идентификаторы в двойных кавычках (") или квадратных скобках ([ ]) на обратные апострофы (`).
     *             - Пример: "users" или [users] → `users`.
     *          3. Для PostgreSQL заменяет идентификаторы в квадратных скобках ([ ]) на двойные кавычки (").
     *             - Пример: [users] → "users".
     *          4. Если тип СУБД не поддерживается, выбрасывается исключение.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *          Вызывается из метода `convert_query`.
     *
     * @callergraph
     *
     * @param string $target_db_type Тип целевой СУБД:
     *                               - Поддерживаемые значения: 'mysql', 'pgsql'.
     *                               - Пример: 'mysql' для MySQL или 'pgsql' для PostgreSQL.
     *                               Ограничения: входной тип должен быть допустимым значением.
     *
     * @return void Метод ничего не возвращает. Результат преобразования сохраняется в свойстве `$txt_query`.
     *
     * @throws InvalidArgumentException Выбрасывается, если тип СУБД не поддерживается.
     *                                  Пример сообщения:
     *                                      Неподдерживаемый тип базы данных | Тип: [target_db_type]
     *
     * @note    Метод использует регулярные выражения для поиска и замены идентификаторов в запросе.
     *          Преобразованный запрос сохраняется в свойстве `$txt_query`.
     *
     * @warning Убедитесь, что входной тип `$target_db_type` соответствует поддерживаемым значениям.
     *          Недопустимый тип может привести к исключениям.
     *
     * Пример вызова метода внутри класса:
     * @code
     * // Исходный запрос в формате SQLite
     * $this->txt_query = 'SELECT * FROM "users" WHERE id = 1';
     *
     * // Преобразование запроса для MySQL
     * $this->convert_from_sqlite_to('mysql');
     * echo $this->txt_query; // Результат: SELECT * FROM `users` WHERE id = 1
     *
     * // Исходный запрос с квадратными скобками
     * $this->txt_query = 'SELECT * FROM [users] WHERE id = 1';
     *
     * // Преобразование запроса для PostgreSQL
     * $this->convert_from_sqlite_to('pgsql');
     * echo $this->txt_query; // Результат: SELECT * FROM "users" WHERE id = 1
     * @endcode
     * @see     PhotoRigma::Classes::Database::$txt_query
     *          Свойство, содержащее текст SQL-запроса.
     * @see     PhotoRigma::Classes::Database::convert_query()
     *          Метод, из которого вызывается данный метод.
     */
    private function convert_from_sqlite_to(string $target_db_type): void
    {
        $this->txt_query = match ($target_db_type) {
            'mysql'  => preg_replace_callback(
                '/(?<!\\\\)["\[](.*?)[\]""]/', // Ищем идентификаторы в двойных кавычках или квадратных скобках
                static fn ($matches) => "`$matches[1]`", // Заменяем их на обратные апострофы
                $this->txt_query
            ),
            'pgsql'  => preg_replace_callback(
                '/(?<!\\\\)\[(.*?)\]/', // Ищем идентификаторы в квадратных скобках
                static fn ($matches) => "\"$matches[1]\"", // Заменяем их на двойные кавычки
                $this->txt_query
            ),
            default => throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Неподдерживаемый тип базы данных | Тип: $target_db_type"
            ),
        };
    }

    /**
     * @brief   Логирует результат анализа SQL-запроса с помощью EXPLAIN или EXPLAIN ANALYZE.
     *
     * @details Этот метод выполняет анализ SQL-запроса с помощью команды EXPLAIN или EXPLAIN ANALYZE.
     *          Алгоритм работы:
     *          1. Определяет тип EXPLAIN в зависимости от СУБД:
     *             - Для SQLite всегда используется только EXPLAIN.
     *             - Для MySQL/MariaDB проверяется версия СУБД:
     *               - Если версия >= 5.7 и это не MariaDB, используется EXPLAIN ANALYZE.
     *               - В противном случае используется только EXPLAIN.
     *             - Для PostgreSQL:
     *               - Для запросов INSERT, UPDATE, DELETE, TRUNCATE используется только EXPLAIN.
     *               - Для SELECT-запросов используется EXPLAIN ANALYZE.
     *          2. Выполняет EXPLAIN (или EXPLAIN ANALYZE) с использованием PDO.
     *          3. Логирует результат анализа вместе с текстом запроса и параметрами:
     *             - Результат форматируется для удобства чтения.
     *          4. В случае ошибки при выполнении EXPLAIN также логируется сообщение об ошибке.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @param array $params Параметры для подготовленного запроса.
     *                      Должен быть ассоциативным массивом, где ключи соответствуют плейсхолдерам в запросе.
     *                      Если параметры отсутствуют, можно передать пустой массив.
     *                      Пример: ['id' => 1].
     *
     * @throws InvalidArgumentException Выбрасывается, если тип СУБД не поддерживается.
     *                                  Пример сообщения:
     *                                      Не поддерживаемый тип СУБД | Тип: [db_type]
     * @throws Exception Выбрасывается, если произошла ошибка при выполнении EXPLAIN (например, синтаксическая ошибка в
     *                   запросе). Пример сообщения: Ошибка при выполнении EXPLAIN | Запрос: [текст_запроса]
     *
     * @note    Метод использует PDO для выполнения EXPLAIN и логирует результаты через `log_in_file`.
     *          Результат анализа форматируется для удобства чтения (ключ: значение).
     *          При возникновении ошибки логируются текст запроса, параметры и сообщение об ошибке.
     *
     * @warning Убедитесь, что текст запроса (`$this->txt_query`) корректен перед вызовом метода.
     *          Некорректные запросы могут привести к исключениям.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $this->txt_query = "SELECT * FROM users WHERE id = :id";
     * $this->log_explain([':id' => 1]);
     * // Лог: Отчет по анализу с помощью EXPLAIN ANALYZE:
     * // Запрос: SELECT * FROM users WHERE id = :id
     * // Параметры: {"id":1}
     * // EXPLAIN ANALYZE Результат:
     * // ...
     * @endcode
     * @see     PhotoRigma::Include::log_in_file()
     *         Функция для логирования сообщений в файл.
     * @see     PhotoRigma::Classes::Database::$txt_query
     *         Свойство, содержащее текст SQL-запроса.
     * @see     PhotoRigma::Classes::Database::$pdo
     *         Объект PDO для подключения к базе данных.
     */
    private function log_explain(array $params): void
    {
        $explain = '';
        try {
            // Определяем, нужно ли добавлять ANALYZE
            $is_write_query = stripos(trim($this->txt_query), 'INSERT') === 0 || stripos(
                trim($this->txt_query),
                'UPDATE'
            ) === 0 || stripos(trim($this->txt_query), 'DELETE') === 0 || stripos(
                trim($this->txt_query),
                'TRUNCATE'
            ) === 0;

            // Определяем тип EXPLAIN в зависимости от СУБД и версии
            if ($this->db_type === 'sqlite') {
                $explain = 'EXPLAIN'; // Для SQLite всегда только EXPLAIN
            } elseif ($this->db_type === 'mysql') {
                // Для MySQL или MariaDB проверяем версию и тип СУБД
                try {
                    $stmt = $this->pdo->query("SELECT VERSION()");
                    $version = $stmt->fetchColumn();

                    // Проверяем, является ли СУБД MariaDB
                    $is_mariadb = stripos($version, 'mariadb') !== false;

                    // Извлекаем числовую часть версии (например, "8.0.30" -> 8.0)
                    preg_match('/^(\d+\.\d+)/', $version, $matches);
                    $numeric_version = $matches[1] ?? null;

                    // Определяем, поддерживается ли ANALYZE
                    $supports_analyze = !$is_mariadb && $numeric_version !== null && version_compare(
                        $numeric_version,
                        '5.7',
                        '>='
                    );

                    $explain = $is_write_query || !$supports_analyze ? 'EXPLAIN' // Без ANALYZE для INSERT/UPDATE/DELETE/TRUNCATE или для MariaDB/MySQL < 5.7
                        : 'EXPLAIN ANALYZE';
                } catch (Exception) {
                    // Если не удалось получить версию, используем только EXPLAIN
                    $explain = 'EXPLAIN';
                }
            } elseif ($this->db_type === 'pgsql') {
                $explain = $is_write_query ? 'EXPLAIN' // Без ANALYZE для INSERT/UPDATE/DELETE/TRUNCATE
                    : 'EXPLAIN ANALYZE'; // С ANALYZE для SELECT
            } else {
                throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не поддерживаемый тип СУБД | Тип: $this->db_type"
                );
            }

            // Выполняем EXPLAIN (или EXPLAIN ANALYZE)
            $stmt = $this->pdo->prepare("$explain $this->txt_query");
            $stmt->execute($params);

            // Получаем результат
            $explain_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Форматируем результат для логирования
            $explain_result = '';
            foreach ($explain_rows as $row) {
                $explain_result .= implode(
                    ' | ',
                    array_map(static fn ($key, $value) => "$key: $value", array_keys($row), $row)
                ) . PHP_EOL;
            }

            // Логируем результат
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Отчет по анализу с помощью $explain: | " . "Запрос: " . $this->txt_query . PHP_EOL . "Параметры: " . (!empty($params) ? json_encode(
                    $params,
                    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                ) : "отсутствуют") . PHP_EOL . "$explain Результат:" . PHP_EOL . $explain_result
            );
        } catch (Exception $e) {
            // Логируем ошибку
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при выполнении $explain: | " . "Запрос: " . $this->txt_query . PHP_EOL . "Параметры: " . (!empty($params) ? json_encode(
                    $params,
                    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                ) : "отсутствуют") . PHP_EOL . "Сообщение об ошибке: {$e->getMessage()}"
            );
        }
    }

    /**
     * @brief   Логирует медленные запросы, запросы без использования плейсхолдеров и другие запросы.
     *
     * @details Этот метод выполняет логирование SQL-запросов в таблицу логов (`TBL_LOG_QUERY`). Алгоритм работы:
     *          1. Проверяет, что запрос существует, является строкой и не является запросом к таблице логов.
     *          2. Нормализует время выполнения запроса (минимальное значение 0).
     *          3. Обрезает запрос до допустимой длины (например, 65,530 символов для TEXT в MySQL).
     *          4. Хэширует запрос для проверки на дублирование.
     *          5. Проверяет значение `$reason` через `match`:
     *             - Допустимые значения: 'slow' (медленный запрос), 'no_placeholders' (без плейсхолдеров), 'other'
     *             (другое).
     *             - Если значение некорректно, используется 'other'.
     *          6. Проверяет наличие запроса в кэше (`$query_cache`) для оптимизации:
     *             - Если запроса нет в кэше, проверяет его наличие в базе данных.
     *          7. Логирует или обновляет запись в таблице логов:
     *             - Если запрос уже существует, обновляет счетчик использования, время последнего использования и
     *               максимальное время выполнения.
     *             - Если запрос не существует, сохраняет его в таблицу логов.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     *
     * @param string     $reason            Причина логирования запроса.
     *                                      Допустимые значения:
     *                                      - 'slow': Медленный запрос (время выполнения превышает пороговое значение).
     *                                      - 'no_placeholders': Запрос без использования плейсхолдеров.
     *                                      - 'other': Другие запросы.
     *                                      По умолчанию: 'other'.
     *                                      Пример: 'slow'.
     * @param float|null $execution_time_ms Время выполнения запроса в миллисекундах.
     *                                      Должно быть положительным числом или `null`.
     *                                      Используется только для новых запросов или обновления максимального времени
     *                                      выполнения.
     *                                      Пример: 1200.5.
     *
     * @throws PDOException Выбрасывается, если произошла ошибка при работе с базой данных (например, проблемы с
     *                       подключением или выполнением запроса).
     *                                  Пример сообщения:
     *                                      Ошибка при выполнении SQL-запроса | Таблица: [TBL_LOG_QUERY]
     *
     * @note    Метод использует кэширование (`$query_cache`) для оптимизации проверки существования запросов.
     *          Запросы хэшируются с помощью `md5()` для предотвращения дублирования записей в таблице логов.
     *          Слишком длинные запросы обрезаются до 65,530 символов (максимальная длина для типа TEXT в MySQL).
     *          Для настройки поведения метода используются следующие константы:
     *          - `TBL_LOG_QUERY`: Имя таблицы для логирования запросов. Например: 'log_queries'.
     *
     * @warning Убедитесь, что `$txt_query` содержит корректный SQL-запрос перед вызовом метода.
     *          Также учтите, что слишком длинные запросы будут обрезаны до 65,530 символов.
     *
     * Пример вызова метода log_query():
     * @code
     * // Логирование медленного запроса
     * $this->txt_query = "SELECT * FROM users WHERE id = 1";
     * $this->log_query('slow', 1200.5);
     *
     * // Логирование запроса без плейсхолдеров
     * $this->txt_query = "SELECT * FROM users WHERE id = 1";
     * $this->log_query('no_placeholders');
     * @endcode
     * @see     PhotoRigma::Classes::Database::$txt_query
     *         Текущий SQL-запрос.
     * @see     PhotoRigma::Classes::Database::$pdo
     *         Объект PDO для подключения к базе данных.
     * @see     PhotoRigma::Classes::Database::$query_cache
     *         Кэш для хранения результатов проверки существования запросов.
     * @see     PhotoRigma::Classes::Database::execute_query()
     *         Метод, из которого вызывается данный метод.
     */
    private function log_query(string $reason = 'other', ?float $execution_time_ms = null): void
    {
        // Проверяем, что запрос существует, является строкой и не является запросом к таблице логов
        if (!is_string($this->txt_query) || empty($this->txt_query) || str_contains($this->txt_query, TBL_LOG_QUERY)) {
            return;
        }

        // Проверяем и нормализуем время выполнения (минимальное значение 0)
        $execution_time_ms = max(0.0, (float)($execution_time_ms ?? 0));

        // Обрезаем запрос до допустимой длины (например, 65,530 символа для TEXT в MySQL)
        $max_query_length = 65530; // Максимальная длина для TEXT
        $log_txt_query = strlen($this->txt_query) > $max_query_length ? substr(
            $this->txt_query,
            0,
            $max_query_length
        ) . '...' // Добавляем суффикс, если текст обрезан
            : $this->txt_query;

        // Проверяем значение $reason через match
        $reason = match ($reason) {
            'slow', 'no_placeholders' => $reason, // Оставляем только допустимые значения
            default                   => 'other',
        };

        // Хэшируем запрос для проверки на дублирование
        $hash = md5($this->txt_query);

        // Проверяем кэш на наличие запроса
        if (!isset($this->query_cache[$hash])) {
            // Если запроса нет в кэше, проверяем его наличие в базе данных
            $stmt = $this->pdo->prepare(
                "SELECT id, usage_count, execution_time FROM " . TBL_LOG_QUERY . " WHERE query_hash = :hash"
            );
            $stmt->execute([':hash' => $hash]);
            $this->query_cache[$hash] = $stmt->fetch(PDO::FETCH_ASSOC); // Сохраняем результат в кэше
        }

        if ($this->query_cache[$hash]) {
            // Если запрос уже существует, обновляем счетчик использования, время последнего использования и максимальное время выполнения
            $new_usage_count = $this->query_cache[$hash]['usage_count'] + 1;
            $max_execution_time = max(
                (float)($this->query_cache[$hash]['execution_time'] ?? 0),
                (float)$execution_time_ms
            );

            $stmt = $this->pdo->prepare(
                "UPDATE " . TBL_LOG_QUERY . "
            SET usage_count = :usage_count, last_used_at = CURRENT_TIMESTAMP, execution_time = :execution_time
            WHERE id = :id"
            );
            $stmt->execute([
                ':usage_count'    => $new_usage_count,
                ':execution_time' => $max_execution_time,
                ':id'             => $this->query_cache[$hash]['id'],
            ]);
        } else {
            // Если запрос не существует, сохраняем его в таблицу логов
            $stmt = $this->pdo->prepare(
                "INSERT INTO " . TBL_LOG_QUERY . " (query_hash, query_text, reason, execution_time)
            VALUES (:hash, :text, :reason, :execution_time)"
            );
            $stmt->execute([
                ':hash'           => $hash,
                ':text'           => $log_txt_query,
                ':reason'         => $reason,
                ':execution_time' => $execution_time_ms, // Время в миллисекундах
            ]);
        }
    }

    /**
     * @brief   Форматирует дату в зависимости от типа СУБД через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _format_date_internal().
     *          Он передаёт название столбца и формат даты в защищённый метод, который выполняет формирование
     *          SQL-выражения для форматирования даты с учётом специфики используемой СУБД. Метод предназначен для
     *          использования клиентским кодом как точка входа для форматирования дат.
     *
     * @param string $column Название столбца с датой:
     *                       - Должен быть непустой строкой в формате имени столбца таблицы в БД.
     * @param string $format Формат даты:
     *                       - Может быть указан в любом поддерживаемом формате (например, MySQL, PostgreSQL, SQLite).
     *                       - Текущий формат определяется через свойство `$current_format`.
     *
     * @return string SQL-выражение для форматирования даты.
     *
     * @throws InvalidArgumentException Выбрасывается, если тип СУБД не поддерживается.
     *                                  Пример сообщения:
     *                                      Не поддерживаемый тип СУБД | Тип: unknown
     *
     * @note    Этот метод является точкой входа для форматирования дат. Все проверки и обработка выполняются в
     *          защищённом методе _format_date_internal().
     *
     * @warning Убедитесь, что `$column` и `$format` содержат корректные значения перед вызовом метода.
     *          Неверный формат может привести к ошибкам при формировании SQL-запроса.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $db = new \PhotoRigma\Classes\Database();
     * $result = $db->format_date('created_at', '%Y-%m-%d');
     * echo $result;
     * // Результат для MySQL: DATE_FORMAT(created_at, '%Y-%m-%d')
     * @endcode
     * @see     PhotoRigma::Classes::Database::_format_date_internal()
     *          Защищённый метод, который выполняет основную логику форматирования даты.
     */
    public function format_date(string $column, string $format): string
    {
        return $this->_format_date_internal($column, $format);
    }

    /**
     * @brief   Формирует SQL-выражение для форматирования даты с учетом специфики используемой СУБД.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет тип СУБД через свойство `$db_type`.
     *          2. Для MariaDB (MySQL) преобразует переданный формат `$format` через метод `convert_to_mysql_format`
     *             и использует функцию `DATE_FORMAT`.
     *          3. Для PostgreSQL преобразует формат через метод `convert_to_postgres_format` и использует функцию
     *             `TO_CHAR`. Метод принимает формат не только в стиле MySQL, но и другие поддерживаемые форматы.
     *          4. Для SQLite преобразует формат через метод `convert_to_sqlite_format` и использует функцию `strftime`.
     *             Метод принимает формат не только в стиле MySQL, но и другие поддерживаемые форматы.
     *          5. Если тип СУБД не поддерживается, выбрасывает исключение.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `format_date()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $column Название столбца с датой:
     *                       - Должен быть непустой строкой в формате имени столбца таблицы в БД.
     * @param string $format Формат даты:
     *                       - Может быть указан в любом поддерживаемом формате (например, MySQL, PostgreSQL, SQLite).
     *                       - Текущий формат определяется через свойство `$current_format`.
     *                       - Преобразование выполняется в зависимости от типа СУБД.
     *
     * @return string SQL-выражение для форматирования даты.
     *
     * @throws InvalidArgumentException Выбрасывается, если тип СУБД не поддерживается.
     *                                  Пример сообщения:
     *                                      Не поддерживаемый тип СУБД | Тип: unknown
     *
     * @note    Метод использует функции форматирования даты, специфичные для каждой СУБД. Текущий формат хранится в
     *          свойстве `$current_format`, а список допустимых форматов — в свойстве `$allowed_formats`.
     *          Для временного изменения текущего формата используется метод `_with_sql_format_internal`.
     *
     * @warning Убедитесь, что `$column` и `$format` содержат корректные значения перед вызовом метода.
     *          Неверный формат может привести к ошибкам при формировании SQL-запроса.
     *
     * Пример вызова метода внутри класса:
     * @code
     * // Форматирование даты для MySQL
     * $this->_format_date_internal('created_at', '%Y-%m-%d');
     * // Результат: DATE_FORMAT(created_at, '2023-10-01')
     *
     * // Форматирование даты для PostgreSQL
     * $this->_format_date_internal('created_at', 'YYYY-MM-DD');
     * // Результат: TO_CHAR(created_at, '2023-10-01')
     *
     * // Форматирование даты для SQLite
     * $this->_format_date_internal('created_at', '%Y-%m-%d');
     * // Результат: strftime('%Y-%m-%d', created_at)
     * @endcode
     * @see     PhotoRigma::Classes::Database::$db_type
     *          Тип используемой СУБД.
     * @see     PhotoRigma::Classes::Database::$current_format
     *          Текущий формат SQL.
     * @see     PhotoRigma::Classes::Database::$allowed_formats
     *          Список допустимых форматов SQL.
     * @see     PhotoRigma::Classes::Database::convert_to_mysql_format()
     *          Преобразовывает формат даты в стиль MySQL.
     * @see     PhotoRigma::Classes::Database::convert_to_postgres_format()
     *          Преобразовывает формат даты в стиль PostgreSQL.
     * @see     PhotoRigma::Classes::Database::convert_to_sqlite_format()
     *          Преобразовывает формат даты в стиль SQLite.
     * @see     PhotoRigma::Classes::Database::_with_sql_format_internal()
     *          Временно изменяет формат SQL для выполнения запросов.
     * @see     PhotoRigma::Classes::Database::format_date()
     *          Публичный метод-редирект для вызова этой логики.
     */
    protected function _format_date_internal(string $column, string $format): string
    {
        // Используем match для выбора логики в зависимости от типа СУБД
        return match ($this->db_type) {
            'mysql' => "DATE_FORMAT($column, '" . $this->convert_to_mysql_format(
                $format
            ) . "')", // Для MySQL преобразуем формат
            'pgsql' => "TO_CHAR($column, '" . $this->convert_to_postgres_format(
                $format
            ) . "')", // Для PostgreSQL преобразуем формат
            'sqlite' => "strftime('" . $this->convert_to_sqlite_format(
                $format
            ) . "', $column)",   // Для SQLite преобразуем формат
            default => throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не поддерживаемый тип СУБД | Тип: $this->db_type"
            ),
        };
    }

    /**
     * @param string $format
     *
     * @return string
     */
    private function convert_to_mysql_format(string $format): string
    {
        // Проверяем, нужно ли преобразование
        if ($this->current_format === 'mysql') {
            return $format; // Формат уже в MySQL, возвращаем как есть
        }

        // Проверяем кэш
        if (!isset($this->format_cache['mysql'][$this->current_format][$format])) {
            // Таблицы соответствия для преобразования в MySQL
            $format_map = match ($this->current_format) {
                'pgsql' => [
                    'YYYY' => '%Y', // Год (например, 2023)
                    'MM'   => '%m', // Месяц (например, 01..12)
                    'DD'   => '%d', // День месяца (например, 01..31)
                    'HH24' => '%H', // Часы в 24-часовом формате
                    'MI'   => '%i', // Минуты (00..59)
                    'SS'   => '%s', // Секунды (00..59)
                    'Dy'   => '%a', // Сокращенное название дня недели (например, Mon)
                    'Day'  => '%W', // Полное название дня недели (например, Monday)
                ],
                'sqlite' => [
                    '%Y' => '%Y',   // Год (например, 2023)
                    '%m' => '%m',   // Месяц (например, 01..12)
                    '%d' => '%d',   // День месяца (например, 01..31)
                    '%H' => '%H',   // Часы в 24-часовом формате
                    '%M' => '%i',   // Минуты (00..59)
                    '%S' => '%s',   // Секунды (00..59)
                    '%w' => '%a',   // День недели (0=воскресенье, 1=понедельник, ..., 6=суббота)
                ],
                default => throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                    "Неизвестный формат | Формат: $this->current_format"
                ),
            };

            // Преобразуем формат и сохраняем в кэше
            $this->format_cache['mysql'][$this->current_format][$format] = strtr($format, $format_map);
        }

        // Возвращаем преобразованный формат из кэша
        return $this->format_cache['mysql'][$this->current_format][$format];
    }

    /**
     * @brief   Преобразует формат даты из стиля MariaDB в стиль PostgreSQL.
     *
     * @details Этот метод преобразует формат даты из стиля MariaDB в стиль PostgreSQL. Алгоритм работы:
     *          1. Проверяет, есть ли уже преобразованный формат в кэше (`$format_cache`).
     *             - Если формат найден в кэше, он возвращается без дополнительных преобразований.
     *          2. Если формат отсутствует в кэше, создает карту соответствия между форматами MariaDB и PostgreSQL:
     *             - Карта соответствия включает ключи (например, '%Y' -> 'YYYY', '%m' -> 'MM').
     *          3. Преобразует формат с использованием функции `strtr` и сохраняет результат в кэше.
     *          4. Возвращает преобразованный формат из кэша.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     *
     * @param string $format Формат даты в стиле MariaDB.
     *                             Должен быть строкой, соответствующей формату MariaDB (например, '%Y-%m-%d').
     *                             Пример: '%Y-%m-%d %H:%i:%s'.
     *                             Ограничения: входной формат должен содержать только допустимые символы MariaDB.
     *
     * @return string Формат даты в стиле PostgreSQL.
     *                Например, 'YYYY-MM-DD HH24:MI:SS'.
     *
     * @throws InvalidArgumentException Выбрасывается, если входной формат `$mysql_format` содержит недопустимые символы
     *                                  или не соответствует спецификации MariaDB.
     *                                  Пример сообщения:
     *                                      Недопустимый формат даты | Значение: [$mysql_format]
     *
     * @note    Метод использует кэширование для оптимизации повторных преобразований.
     *          Преобразованные форматы хранятся в свойстве `$format_cache`.
     *
     * @warning Убедитесь, что входной формат `$mysql_format` соответствует спецификации MariaDB.
     *          Недопустимые символы или форматы могут привести к исключениям.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $converted_format = $this->convert_to_postgres_format('%Y-%m-%d');
     * echo $converted_format; // Результат: YYYY-MM-DD
     * @endcode
     * @see     PhotoRigma::Classes::Database::$format_cache
     *         Свойство класса для кеширования преобразованных форматов.
     * @see     PhotoRigma::Classes::Database::_format_date_internal()
     *         Защищённый метод, вызывающий этот метод для преобразования формата даты.
     */
    private function convert_to_postgres_format(string $format): string
    {
        // Проверяем, нужно ли преобразование
        if ($this->current_format === 'pgsql') {
            return $format; // Формат уже в PostgreSQL, возвращаем как есть
        }

        // Проверяем кэш
        if (!isset($this->format_cache['pgsql'][$this->current_format][$format])) {
            // Таблицы соответствия для преобразования в PostgreSQL
            $format_map = match ($this->current_format) {
                'mysql' => [
                    '%Y' => 'YYYY', // Год (например, 2023)
                    '%m' => 'MM',   // Месяц (например, 01..12)
                    '%d' => 'DD',   // День месяца (например, 01..31)
                    '%H' => 'HH24', // Часы в 24-часовом формате
                    '%i' => 'MI',   // Минуты (00..59)
                    '%s' => 'SS',   // Секунды (00..59)
                    '%a' => 'Dy',   // Сокращенное название дня недели (например, Mon)
                    '%W' => 'Day',  // Полное название дня недели (например, Monday)
                ],
                'sqlite' => [
                    '%Y' => 'YYYY', // Год (например, 2023)
                    '%m' => 'MM',   // Месяц (например, 01..12)
                    '%d' => 'DD',   // День месяца (например, 01..31)
                    '%H' => 'HH24', // Часы в 24-часовом формате
                    '%M' => 'MI',   // Минуты (00..59)
                    '%S' => 'SS',   // Секунды (00..59)
                    '%w' => '',     // День недели не имеет прямого аналога в PostgreSQL
                ],
                default => throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                    "Неизвестный формат | Формат: $this->current_format"
                ),
            };

            // Преобразуем формат и сохраняем в кэше
            $this->format_cache['pgsql'][$this->current_format][$format] = strtr($format, $format_map);
        }

        // Возвращаем преобразованный формат из кэша
        return $this->format_cache['pgsql'][$this->current_format][$format];
    }

    /**
     * @brief   Преобразует формат даты из стиля MariaDB в стиль SQLite.
     *
     * @details Этот метод преобразует формат даты из стиля MariaDB в стиль SQLite. Алгоритм работы:
     *          1. Проверяет, есть ли уже преобразованный формат в кэше (`$format_cache`).
     *             - Если формат найден в кэше, он возвращается без дополнительных преобразований.
     *          2. Если формат отсутствует в кэше, создает карту соответствия между форматами MariaDB и SQLite:
     *             - Карта соответствия включает ключи (например, '%Y' -> '%Y', '%i' -> '%M').
     *          3. Преобразует формат с использованием функции `strtr` и сохраняет результат в кэше.
     *          4. Возвращает преобразованный формат из кэша.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     *
     * @param string $format Формат даты в стиле MariaDB.
     *                             Должен быть строкой, соответствующей формату MariaDB (например, '%Y-%m-%d').
     *                             Пример: '%Y-%m-%d %H:%i:%s'.
     *                             Ограничения: входной формат должен содержать только допустимые символы MariaDB.
     *
     * @return string Формат даты в стиле SQLite.
     *                Например, '%Y-%m-%d %H:%M:%S'.
     *
     * @throws InvalidArgumentException Выбрасывается, если входной формат `$mysql_format` содержит недопустимые символы
     *                                  или не соответствует спецификации MariaDB.
     *                                  Пример сообщения:
     *                                      Недопустимый формат даты | Значение: [$mysql_format]
     *
     * @note    Метод использует кэширование для оптимизации повторных преобразований.
     *          Преобразованные форматы хранятся в свойстве `$format_cache`.
     *
     * @warning Убедитесь, что входной формат `$mysql_format` соответствует спецификации MariaDB.
     *          Недопустимые символы или форматы могут привести к исключениям.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $converted_format = $this->convert_to_sqlite_format('%Y-%m-%d');
     * echo $converted_format; // Результат: %Y-%m-%d
     * @endcode
     * @see     PhotoRigma::Classes::Database::$format_cache
     *         Свойство класса для кеширования преобразованных форматов.
     * @see     PhotoRigma::Classes::Database::_format_date_internal()
     *         Защищённый метод, вызывающий этот метод для преобразования формата даты.
     */
    private function convert_to_sqlite_format(string $format): string
    {
        // Проверяем, нужно ли преобразование
        if ($this->current_format === 'sqlite') {
            return $format; // Формат уже в SQLite, возвращаем как есть
        }

        // Проверяем кэш
        if (!isset($this->format_cache['sqlite'][$this->current_format][$format])) {
            // Таблицы соответствия для преобразования в SQLite
            $format_map = match ($this->current_format) {
                'mysql' => [
                    '%Y' => '%Y',   // Год (например, 2023)
                    '%m' => '%m',   // Месяц (например, 01..12)
                    '%d' => '%d',   // День месяца (например, 01..31)
                    '%H' => '%H',   // Часы в 24-часовом формате
                    '%i' => '%M',   // Минуты (00..59)
                    '%s' => '%S',   // Секунды (00..59)
                    '%a' => '%w',   // День недели (0=воскресенье, 1=понедельник, ..., 6=суббота)
                    '%W' => '',     // Полное название дня недели не поддерживается в SQLite
                ],
                'pgsql' => [
                    'YYYY' => '%Y', // Год (например, 2023)
                    'MM'   => '%m', // Месяц (например, 01..12)
                    'DD'   => '%d', // День месяца (например, 01..31)
                    'HH24' => '%H', // Часы в 24-часовом формате
                    'MI'   => '%M', // Минуты (00..59)
                    'SS'   => '%S', // Секунды (00..59)
                    'Dy'   => '',   // Сокращенное название дня недели не поддерживается в SQLite
                    'Day'  => '',   // Полное название дня недели не поддерживается в SQLite
                ],
                default => throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                    "Неизвестный формат | Формат: $this->current_format"
                ),
            };

            // Преобразуем формат и сохраняем в кэше
            $this->format_cache['sqlite'][$this->current_format][$format] = strtr($format, $format_map);
        }

        // Возвращаем преобразованный формат из кэша
        return $this->format_cache['sqlite'][$this->current_format][$format];
    }

    /**
     * @brief   Возвращает количество строк, затронутых последним SQL-запросом, через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _get_affected_rows_internal().
     *          Он возвращает количество строк, затронутых последним SQL-запросом, после проверки состояния свойства
     *          `$aff_rows`. Метод предназначен для использования клиентским кодом как точка входа для получения
     *          количества затронутых строк.
     *
     * @return int Количество строк, затронутых последним SQL-запросом.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - Свойство `$aff_rows` не установлено.
     *                                    Пример сообщения:
     *                                        Количество затронутых строк не установлено | Причина: Свойство aff_rows
     *                                        не определено
     *                                  - Значение свойства `$aff_rows` не является целым числом.
     *                                    Пример сообщения:
     *                                        Некорректное значение свойства aff_rows | Ожидалось целое число,
     *                                        получено: [значение]
     *
     * @note    Этот метод является точкой входа для получения количества затронутых строк. Все проверки и обработка
     *          выполняются в защищённом методе _get_affected_rows_internal().
     *
     * @warning Метод чувствителен к состоянию свойства `$aff_rows`. Убедитесь, что перед вызовом метода был выполнен
     *          запрос, который установил значение `$aff_rows` как целое число.
     *
     * Пример использования:
     * @code
     * // Выполняем запрос на удаление данных
     * $db->delete('users', ['where' => 'status = 0']);
     *
     * // Получаем количество затронутых строк
     * echo "Affected rows: " . $db->get_affected_rows();
     * @endcode
     * @see     PhotoRigma::Classes::Database::_get_affected_rows_internal()
     *          Защищённый метод, который выполняет основную логику получения количества затронутых строк.
     */
    public function get_affected_rows(): int
    {
        return $this->_get_affected_rows_internal();
    }

    /**
     * @brief   Возвращает количество строк, затронутых последним SQL-запросом, после проверки состояния свойства
     *          `$aff_rows`.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет, что свойство `$aff_rows` установлено. Если это не так, выбрасывается исключение.
     *          2. Проверяет, что значение свойства `$aff_rows` является целым числом. Если это не так, выбрасывается
     *             исключение.
     *          3. Возвращает значение свойства `$aff_rows`, которое представляет собой количество строк, затронутых
     *             последним SQL-запросом.
     *          Метод только читает значение свойства `$aff_rows` и не выполняет дополнительной логики для его
     *          обновления. Значение `$aff_rows` должно быть установлено внешними методами, такими как `execute_query`,
     *          `delete`,
     *          `update` или `insert`.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `get_affected_rows()`.
     *
     * @callergraph
     * @callgraph
     *
     * @return int Количество строк, затронутых последним SQL-запросом.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - Свойство `$aff_rows` не установлено.
     *                                    Пример сообщения:
     *                                        Количество затронутых строк не установлено | Причина: Свойство aff_rows
     *                                        не определено
     *                                  - Значение свойства `$aff_rows` не является целым числом.
     *                                    Пример сообщения:
     *                                        Некорректное значение свойства aff_rows | Ожидалось целое число,
     *                                        получено: [значение]
     *
     * @warning Метод чувствителен к состоянию свойства `$aff_rows`. Убедитесь, что перед вызовом метода был выполнен
     *          запрос, который установил значение `$aff_rows` как целое число.
     *
     * Пример использования метода _get_affected_rows_internal():
     * @code
     * // Выполняем запрос на удаление данных
     * $this->delete('users', ['where' => 'status = 0']);
     *
     * // Получаем количество затронутых строк
     * echo "Affected rows: " . $this->_get_affected_rows_internal();
     * // Примечание: Метод delete обновляет значение свойства $aff_rows.
     * @endcode
     * @see     PhotoRigma::Classes::Database::execute_query()
     *          Метод, который обновляет значение `$aff_rows` после выполнения запроса.
     * @see     PhotoRigma::Classes::Database::delete()
     *          Метод, который может изменять значение `$aff_rows`.
     * @see     PhotoRigma::Classes::Database::update()
     *          Метод, который может изменять значение `$aff_rows`.
     * @see     PhotoRigma::Classes::Database::insert()
     *          Метод, который может изменять значение `$aff_rows`.
     * @see     PhotoRigma::Classes::Database::get_affected_rows()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Database::$aff_rows
     *          Свойство, хранящее количество затронутых строк.
     */
    protected function _get_affected_rows_internal(): int
    {
        // === 1. Валидация состояния ===
        if (!isset($this->aff_rows)) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Количество затронутых строк не установлено | Причина: Свойство aff_rows не определено"
            );
        }
        // === 2. Возврат значения ===
        return $this->aff_rows;
    }

    /**
     * @brief   Возвращает ID последней вставленной строки через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _get_last_insert_id_internal().
     *          Он возвращает ID последней вставленной строки после проверки состояния свойства `$insert_id`.
     *          Метод предназначен для использования клиентским кодом как точка входа для получения ID последней
     *          вставленной строки.
     *
     * @return int ID последней вставленной строки.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - Свойство `$insert_id` не установлено.
     *                                    Пример сообщения:
     *                                        ID последней вставленной строки не установлен | Причина: Свойство
     *                                        insert_id не определено
     *                                  - Значение свойства `$insert_id` не является целым числом.
     *                                    Пример сообщения:
     *                                        Некорректное значение свойства insert_id | Ожидалось целое число,
     *                                        получено: [значение]
     *
     * @note    Этот метод является точкой входа для получения ID последней вставленной строки. Все проверки и
     *          обработка выполняются в защищённом методе _get_last_insert_id_internal().
     *
     * @warning Метод чувствителен к состоянию свойства `$insert_id`. Убедитесь, что перед вызовом метода был выполнен
     *          запрос, который установил значение `$insert_id` как целое число.
     *
     * Пример использования:
     * @code
     * // Выполняем запрос на вставку данных
     * $db->insert(['name' => 'John Doe', 'email' => 'john@example.com'], 'users');
     *
     * // Получаем ID последней вставленной строки
     * echo "Last insert ID: " . $db->get_last_insert_id();
     * @endcode
     * @see     PhotoRigma::Classes::Database::_get_last_insert_id_internal()
     *          Защищённый метод, который выполняет основную логику получения ID последней вставленной строки.
     */
    public function get_last_insert_id(): int
    {
        return $this->_get_last_insert_id_internal();
    }

    /**
     * @brief   Возвращает ID последней вставленной строки после проверки состояния свойства `$insert_id`.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет, что свойство `$insert_id` установлено. Если это не так, выбрасывается исключение.
     *          2. Проверяет, что значение свойства `$insert_id` является целым числом. Если это не так, выбрасывается
     *             исключение.
     *          3. Возвращает значение свойства `$insert_id`, которое представляет собой ID последней вставленной
     *          строки. Метод только читает значение свойства `$insert_id` и не выполняет дополнительной логики для его
     *          обновления. Значение `$insert_id` должно быть установлено внешними методами, такими как `execute_query`
     *          или `insert`. Этот метод является защищенным и предназначен для использования внутри класса или его
     *          наследников. Основная логика вызывается через публичный метод-редирект `get_last_insert_id()`.
     *
     * @callergraph
     * @callgraph
     *
     * @return int ID последней вставленной строки.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - Свойство `$insert_id` не установлено.
     *                                    Пример сообщения:
     *                                        ID последней вставленной строки не установлен | Причина: Свойство
     *                                        insert_id не определено
     *                                  - Значение свойства `$insert_id` не является целым числом.
     *                                    Пример сообщения:
     *                                        Некорректное значение свойства insert_id | Ожидалось целое число,
     *                                        получено: [значение]
     *
     * @warning Метод чувствителен к состоянию свойства `$insert_id`. Убедитесь, что перед вызовом метода был выполнен
     *          запрос, который установил значение `$insert_id` как целое число.
     *
     * Пример использования метода _get_last_insert_id_internal():
     * @code
     * // Выполняем запрос на вставку данных
     * $this->insert(['name' => 'John Doe', 'email' => 'john@example.com'], 'users');
     *
     * // Получаем ID последней вставленной строки
     * echo "Last insert ID: " . $this->_get_last_insert_id_internal();
     * // Примечание: Метод insert обновляет значение свойства $insert_id.
     * @endcode
     * @see     PhotoRigma::Classes::Database::insert()
     *          Метод, который устанавливает значение `$insert_id`.
     * @see     PhotoRigma::Classes::Database::execute_query()
     *          Метод, который обновляет значение `$insert_id` после выполнения запроса.
     * @see     PhotoRigma::Classes::Database::get_last_insert_id()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Database::$insert_id
     *          Свойство, хранящее ID последней вставленной строки.
     */
    protected function _get_last_insert_id_internal(): int
    {
        // === 1. Валидация состояния ===
        if (!isset($this->insert_id)) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | ID последней вставленной строки не установлен | Причина: Свойство insert_id не определено"
            );
        }
        // === 2. Возврат значения ===
        return $this->insert_id;
    }

    /**
     * @brief   Вставляет данные в таблицу через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _insert_internal().
     *          Он передаёт массив данных, имя таблицы, тип запроса и опции в защищённый метод, который выполняет
     *          формирование и выполнение SQL-запроса INSERT. Безопасность обеспечивается использованием подготовленных
     *          выражений с параметрами. Метод предназначен для использования клиентским кодом как точка входа для
     *          вставки данных.
     *
     * @param array  $insert    Ассоциативный массив данных для вставки в формате: 'имя_поля' => 'значение'.
     *                          Если передан пустой массив, выбрасывается исключение.
     * @param string $to_tbl    Имя таблицы, в которую необходимо вставить данные:
     *                          - Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                          символов.
     * @param string $type      Тип запроса (необязательно). Определяет тип SQL-запроса на вставку. Допустимые
     *                          значения:
     *                          - 'ignore': Формирует запрос типа "INSERT IGNORE INTO".
     *                          - 'replace': Формирует запрос типа "REPLACE INTO".
     *                          - 'into': Формирует запрос типа "INSERT INTO" (явное указание).
     *                          - '' (пустая строка): Формирует запрос типа "INSERT INTO" (по умолчанию).
     *                          Если указан недопустимый тип, выбрасывается исключение.
     * @param array  $options   Массив опций для формирования запроса. Поддерживаемые ключи:
     *                          - params (array): Параметры для подготовленного выражения. Ассоциативный массив
     *                          значений, используемых в запросе (например, [":name" => "John Doe", ":email" =>
     *                          "john@example.com"]).
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$insert` не является массивом или является пустым массивом.
     *                                    Пример сообщения:
     *                                        Данные для вставки не могут быть пустыми | Причина: Пустой массив данных
     *                                  - `$to_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                        Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                                  - `$type` содержит недопустимое значение (не '', 'ignore', 'replace', 'into').
     *                                    Пример сообщения:
     *                                        Недопустимый тип вставки | Разрешённые значения: '', 'ignore', 'replace',
     *                                        'into'. Получено: '$type'
     *                                  - `$options` не является массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     * @throws Exception Выбрасывается при ошибках выполнения запроса.
     *
     * @note    Этот метод является точкой входа для вставки данных. Все проверки и обработка выполняются в
     *          защищённом методе _insert_internal().
     *
     * @warning Метод чувствителен к корректности входных данных. Убедитесь, что массив `$insert` не пустой и содержит
     *          корректные данные. Параметр `$type` должен быть одним из допустимых значений: '', 'ignore', 'replace',
     *          'into'. Использование параметров `params` для подготовленных выражений является обязательным для защиты
     *          от SQL-инъекций и обеспечения совместимости с различными СУБД.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $db = new \PhotoRigma\Classes\Database();
     * $db->insert(['name' => 'John Doe', 'email' => 'john@example.com'], 'users');
     * @endcode
     * @see     PhotoRigma::Classes::Database::_insert_internal()
     *          Защищённый метод, который выполняет основную логику формирования и выполнения SQL-запроса INSERT.
     */
    public function insert(array $insert, string $to_tbl, string $type = '', array $options = []): bool
    {
        return $this->_insert_internal($insert, $to_tbl, $type, $options);
    }

    /**
     * @brief   Формирует SQL-запрос на вставку данных в таблицу, проверяет входные данные, обрабатывает различные типы
     *          запросов, сохраняет текст запроса в свойстве `$txt_query` и выполняет его.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет типы входных данных: `$insert` (ассоциативный массив), `$to_tbl` (строка), `$type`
     *          (строка),
     *             `$options` (массив).
     *          2. Проверяет, что массив `$insert` не пустой. Если массив пустой, выбрасывается исключение.
     *          3. Нормализует параметр `$type`, приводя его к нижнему регистру, и проверяет на допустимые значения:
     *             `'ignore'`, `'replace'`, `'into'`, `''`. Если указан недопустимый тип, выбрасывается исключение.
     *          4. Определяет тип запроса на основе параметра `$type`:
     *             - `'ignore'`: Формирует запрос типа "INSERT IGNORE INTO".
     *             - `'replace'`: Формирует запрос типа "REPLACE INTO".
     *             - `'into'` или пустая строка (`''`): Формирует запрос типа "INSERT INTO" (по умолчанию).
     *          5. Формирует базовый SQL-запрос INSERT с использованием преобразованных данных и сохраняет его в
     *          свойстве
     *             `$txt_query`.
     *          6. Выполняет сформированный запрос через метод `execute_query`, передавая параметры `params` для
     *             подготовленного выражения.
     *          Использование параметров `params` является обязательным для подготовленных выражений, так как это
     *          обеспечивает защиту от SQL-инъекций и совместимость с различными СУБД.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `insert()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param array  $insert    Ассоциативный массив данных для вставки в формате: 'имя_поля' => 'значение'.
     *                          Если передан пустой массив, выбрасывается исключение.
     * @param string $to_tbl    Имя таблицы, в которую необходимо вставить данные.
     *                          Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                          символов.
     * @param string $type      Тип запроса (необязательно). Определяет тип SQL-запроса на вставку. Допустимые
     *                          значения:
     *                          - 'ignore': Формирует запрос типа "INSERT IGNORE INTO".
     *                          - 'replace': Формирует запрос типа "REPLACE INTO".
     *                          - 'into': Формирует запрос типа "INSERT INTO" (явное указание).
     *                          - '' (пустая строка): Формирует запрос типа "INSERT INTO" (по умолчанию).
     *                          Если указан недопустимый тип, выбрасывается исключение.
     * @param array  $options   Массив опций для формирования запроса. Поддерживаемые ключи:
     *                          - params (array): Параметры для подготовленного выражения. Ассоциативный массив
     *                          значений, используемых в запросе (например, [":name" => "John Doe", ":email" =>
     *                          "john@example.com"]).
     *                          Использование параметров `params` является обязательным для подготовленных выражений,
     *                          так как это обеспечивает защиту от SQL-инъекций и совместимость с различными СУБД.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *              В случае ошибки выбрасывается исключение.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$insert` не является массивом или является пустым массивом.
     *                                    Пример сообщения:
     *                                        Данные для вставки не могут быть пустыми | Причина: Пустой массив данных
     *                                  - `$to_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                        Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                                  - `$type` содержит недопустимое значение (не '', 'ignore', 'replace', 'into').
     *                                    Пример сообщения:
     *                                        Недопустимый тип вставки | Разрешённые значения: '', 'ignore', 'replace',
     *                                        'into'. Получено: '$type'
     *                                  - `$options` не является массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     * @throws Exception Выбрасывается при ошибках выполнения запроса.
     *
     * @warning Метод чувствителен к корректности входных данных. Неверные типы данных или некорректные значения могут
     *          привести к выбросу исключения. Убедитесь, что массив `$insert` не пустой и содержит корректные данные.
     *          Параметр `$type` должен быть одним из допустимых значений: '', 'ignore', 'replace', 'into'.
     *          Использование параметров `params` для подготовленных выражений является обязательным для защиты от
     *          SQL-инъекций и обеспечения совместимости с различными СУБД. Игнорирование этого правила может привести
     *          к уязвимостям безопасности и неправильной работе с базой данных.
     *
     * Пример использования метода _insert_internal():
     * @code
     * // Безопасное использование с параметрами `params`
     * $this->_insert_internal(
     *     ['name' => ':name', 'email' => ':email'],
     *     'users',
     *     '',
     *     ['params' => [':name' => 'John Doe', ':email' => 'john@example.com']]
     * );
     * @endcode
     * @see     PhotoRigma::Classes::Database::$txt_query
     *          Свойство, в которое помещается текст SQL-запроса.
     * @see     PhotoRigma::Classes::Database::insert()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Database::execute_query()
     *          Выполняет SQL-запрос.
     */
    protected function _insert_internal(array $insert, string $to_tbl, string $type = '', array $options = []): bool
    {
        // === 1. Валидация аргументов ===
        if (empty($insert)) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Данные для вставки не могут быть пустыми | Причина: Пустой массив данных"
            );
        }
        // Нормализация $type (приведение к нижнему регистру)
        $type = strtolower($type);
        // Проверка допустимых значений для $type
        if (!in_array($type, ['', 'ignore', 'replace', 'into'], true)) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый тип вставки | Разрешённые значения: '', 'ignore', 'replace', 'into'. Получено: '$type'"
            );
        }

        // === 2. Определение типа запроса ===
        if ($type === 'ignore') {
            $query_type = 'INSERT IGNORE INTO';
        } elseif ($type === 'replace') {
            $query_type = 'REPLACE INTO';
        } else {
            $query_type = 'INSERT INTO'; // По умолчанию или если указано 'into'
        }

        // === 3. Подготовка данных для запроса ===
        $keys = implode(', ', array_keys($insert));
        $values = implode(', ', $insert);

        // === 4. Формирование базового запроса ===
        $this->txt_query = "$query_type $to_tbl ($keys) VALUES ($values)";

        // === 5. Выполнение запроса ===
        return $this->execute_query($options['params']);
    }

    /**
     * @brief   Выполняет SQL-запрос с использованием JOIN через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _join_internal().
     *          Он передаёт список полей, основную таблицу, массив JOIN-операций и опции в защищённый метод, который
     *          выполняет формирование и выполнение SQL-запроса с использованием JOIN. Безопасность обеспечивается
     *          использованием подготовленных выражений с параметрами. Метод предназначен для использования клиентским
     *          кодом как точка входа для выполнения запросов с JOIN.
     *
     * @param string|array $select         Список полей для выборки:
     *                                     - Может быть строкой (имя одного поля) или массивом (список полей).
     *                                     - Если передан массив, он преобразуется в строку с разделителем `,`.
     *                                     Пример: "id", ["id", "name"].
     * @param string       $from_tbl       Имя основной таблицы, из которой начинается выборка:
     *                                     - Должно быть строкой, содержащей только допустимые имена таблиц без
     *                                     специальных символов.
     *                                     - Ограничения: строка должна быть непустой.
     * @param array        $join           Массив описаний JOIN-операций:
     *                                     - Каждый элемент массива должен содержать ключи:
     *                                     - table (string): Имя таблицы для JOIN.
     *                                     - type (string, optional): Тип JOIN (например, INNER, LEFT, RIGHT). По
     *                                     умолчанию: INNER.
     *                                     - on (string): Условие для JOIN.
     *                                     - Ограничения: массив не может быть пустым.
     *                                     Пример: [['type' => 'INNER', 'table' => 'orders', 'on' => 'users.id =
     *                                     orders.user_id']].
     * @param array        $options        Массив опций для формирования запроса. Поддерживаемые ключи:
     *                                     - where (string|array): Условие WHERE.
     *                                     - group (string): Группировка GROUP BY.
     *                                     - order (string): Сортировка ORDER BY.
     *                                     - limit (int|string): Ограничение LIMIT.
     *                                     - params (array): Параметры для подготовленного выражения.
     *                                     Использование параметров `params` является обязательным для защиты от
     *                                     SQL-инъекций.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$from_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                        Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                                  - `$join` не является массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра join | Ожидался массив, получено: [тип]
     *                                  - `$select` не является строкой или массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра select | Ожидалась строка или массив,
     *                                        получено: [тип]
     *                                  - Отсутствует имя таблицы (`table`) или условие (`on`) в описании JOIN.
     *                                    Пример сообщения:
     *                                        Отсутствует имя таблицы или условие для JOIN | Проверьте структуру
     *                                        массива $join
     *                                  - `$options` не является массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     * @throws Exception Выбрасывается при ошибках выполнения запроса.
     *
     * @note    Этот метод является точкой входа для выполнения запросов с JOIN. Все проверки и обработка выполняются в
     *          защищённом методе _join_internal().
     *
     * @warning Метод чувствителен к корректности входных данных. Убедитесь, что массив `$join` не пустой и содержит
     *          корректные данные. Использование параметров `params` для подготовленных выражений является обязательным
     *          для защиты от SQL-инъекций и обеспечения совместимости с различными СУБД.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $db = new \PhotoRigma\Classes\Database();
     * $db->join(
     *     ['users.id', 'users.name', 'orders.order_date'],
     *     'users',
     *     [
     *         ['type' => 'INNER', 'table' => 'orders', 'on' => 'users.id = orders.user_id'],
     *     ],
     *     [
     *         'where' => ['users.status' => 1],
     *         'params' => [':status' => 1],
     *     ]
     * );
     * @endcode
     * @see     PhotoRigma::Classes::Database::_join_internal()
     *          Защищённый метод, который выполняет основную логику формирования и выполнения SQL-запроса с JOIN.
     */
    public function join(string|array $select, string $from_tbl, array $join, array $options = []): bool
    {
        return $this->_join_internal($select, $from_tbl, $join, $options);
    }

    /**
     * @brief   Формирует SQL-запрос с использованием JOIN для выборки данных из нескольких таблиц, проверяет входные
     *          данные, сохраняет текст запроса в свойстве `$txt_query` и выполняет его.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет типы входных данных: `$select` (строка или массив), `$from_tbl` (строка), `$join`
     *             (массив), `$options` (массив).
     *          2. Проверяет, что массив `$join` не пустой. Если массив пустой, выбрасывается исключение.
     *          3. Обрабатывает список полей для выборки (`$select`):
     *             - Если `$select` является строкой, она используется как есть.
     *             - Если `$select` является массивом, он преобразуется в строку с разделителем `, `. Каждое имя поля
     *               оборачивается в обратные кавычки (` `).
     *          4. Обрабатывает массив JOIN-операций (`$join`):
     *             - Для каждой операции проверяется наличие имени таблицы (`table`) и условия (`on`).
     *             - Если тип JOIN не указан, используется `INNER` по умолчанию.
     *          5. Формирует базовый SQL-запрос с использованием JOIN-операций.
     *          6. Добавляет условия WHERE, GROUP BY, ORDER BY и LIMIT, если они указаны в параметре `$options`.
     *          7. Сохраняет сформированный запрос в свойстве `$txt_query`.
     *          8. Выполняет запрос через метод `execute_query`, передавая параметры `params` для подготовленного
     *             выражения. Использование параметров `params` является обязательным для подготовленных выражений, так
     *             как это обеспечивает защиту от SQL-инъекций и совместимость с различными СУБД. Этот метод является
     *             защищенным и предназначен для использования внутри класса или его наследников. Основная логика
     *             вызывается через публичный метод-редирект `join()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string|array $select     Список полей для выборки. Может быть строкой (имя одного поля) или массивом
     *                                 (список полей). Если передан массив, он преобразуется в строку с разделителем
     *                                 `,`. Пример: "id", ["id", "name"]. Ограничения: массив не может быть пустым.
     * @param string       $from_tbl   Имя основной таблицы, из которой начинается выборка.
     *                                 Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                                 символов. Ограничения: строка должна быть непустой.
     * @param array        $join       Массив описаний JOIN-операций. Каждый элемент массива должен содержать следующие
     *                                 ключи:
     *                                 - table (string): Имя таблицы для JOIN. Должно быть строкой, содержащей только
     *                                 допустимые имена таблиц.
     *                                 - type (string, optional): Тип JOIN (например, INNER, LEFT, RIGHT). Если тип не
     *                                 указан, используется INNER по умолчанию.
     *                                 - on (string): Условие для JOIN. Должно быть строкой с допустимым условием
     *                                 сравнения полей. Если условие отсутствует, выбрасывается исключение. Пример:
     *                                 [['type' => 'INNER', 'table' => 'orders', 'on' => 'users.id = orders.user_id']].
     *                                 Ограничения: массив не может быть пустым.
     * @param array        $options    Массив опций для формирования запроса. Поддерживаемые ключи:
     *                                 - where (string|array): Условие WHERE. Может быть строкой (например, "status =
     *                                 1") или ассоциативным массивом (например, ["id" => 1, "status" => "active"]).
     *                                 - group (string): Группировка GROUP BY. Должна быть строкой с именами полей
     *                                 (например, "category_id").
     *                                 - order (string): Сортировка ORDER BY. Должна быть строкой с именами полей и
     *                                 направлением (например, "created_at DESC").
     *                                 - limit (int|string): Ограничение LIMIT. Может быть числом (например, 10) или
     *                                 строкой с диапазоном (например, "0, 10").
     *                                 - params (array): Параметры для подготовленного выражения. Ассоциативный массив
     *                                 значений, используемых в запросе (например, [":id" => 1, ":status" =>
     *                                 "active"]).
     *                                 Использование параметров `params` является обязательным для подготовленных
     *                                 выражений, так как это обеспечивает защиту от SQL-инъекций и совместимость с
     *                                 различными СУБД.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$from_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                        Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                                  - `$join` не является массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра join | Ожидался массив, получено: [тип]
     *                                  - `$select` не является строкой или массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра select | Ожидалась строка или массив,
     *                                        получено: [тип]
     *                                  - Отсутствует имя таблицы (`table`) или условие (`on`) в описании JOIN.
     *                                    Пример сообщения:
     *                                        Отсутствует имя таблицы или условие для JOIN | Проверьте структуру
     *                                        массива $join
     *                                  - `$options` не является массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     * @throws Exception Выбрасывается при ошибках выполнения запроса.
     *
     * @warning Метод чувствителен к корректности входных данных. Неверные типы данных или некорректные значения могут
     *          привести к выбросу исключения. Убедитесь, что массив `$join` не пустой и содержит корректные данные.
     *          Использование параметров `params` для подготовленных выражений является обязательным для защиты от
     *          SQL-инъекций и обеспечения совместимости с различными СУБД. Игнорирование этого правила может привести
     *          к уязвимостям безопасности и неправильной работе с базой данных.
     *
     * Пример использования метода _join_internal():
     * @code
     * // Безопасное использование с параметрами `params`
     * $this->_join_internal(
     *     ['users.id', 'users.name', 'orders.order_date'],
     *     'users',
     *     [
     *         ['type' => 'INNER', 'table' => 'orders', 'on' => 'users.id = orders.user_id'],
     *     ],
     *     [
     *         'where' => ['users.status' => 1],
     *         'params' => [':status' => 1],
     *     ]
     * );
     * @endcode
     * @see     PhotoRigma::Classes::Database::$txt_query
     *          Свойство, в которое помещается текст SQL-запроса.
     * @see     PhotoRigma::Classes::Database::join()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Database::execute_query()
     *          Выполняет SQL-запрос.
     * @see     PhotoRigma::Classes::Database::build_conditions()
     *          Формирует условия WHERE, GROUP BY, ORDER BY и LIMIT для запроса.
     */
    protected function _join_internal(string|array $select, string $from_tbl, array $join, array $options = []): bool
    {
        // === 1. Обработка $select ===
        if (!is_array($select)) {
            $select = [$select];
        }
        $select = implode(', ', $select);

        // === 2. Обработка $join ===
        $join_clauses = [];
        foreach ($join as $j) {
            if (empty($j['table'])) {
                throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Отсутствует имя таблицы в описании JOIN | Проверьте структуру массива \$join"
                );
            }
            if (empty($j['on'])) {
                throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Отсутствует условие 'on' для таблицы '{$j['table']}' в описании JOIN | Проверьте структуру массива \$join"
                );
            }
            $table = $j['table'];
            $on_condition = $j['on'];
            $type = !empty($j['type']) ? strtoupper($j['type']) . ' ' : 'INNER ';
            $join_clauses[] = "{$type}JOIN $table ON $on_condition";
        }

        // === 3. Формирование базового запроса ===
        $this->txt_query = "SELECT $select FROM $from_tbl " . implode(' ', $join_clauses);

        // === 4. Добавление условий ===
        [$conditions, $params] = $this->build_conditions($options);
        $this->txt_query .= $conditions;

        // === 5. Выполнение запроса ===
        return $this->execute_query($params);
    }

    /**
     * @brief   Извлекает все строки результата запроса через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _res_arr_internal().
     *          Он извлекает все строки результата из подготовленного выражения, хранящегося в свойстве `$res_query`, и
     *          возвращает их как массив. Безопасность обеспечивается проверкой типа свойства `$res_query`. Метод
     *          предназначен для использования клиентским кодом как точка входа для получения результатов запроса.
     *
     * @return array|false Возвращает массив ассоциативных массивов, содержащих данные всех строк результата, если они
     *                     доступны. Если результатов нет, возвращает `false`.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - Свойство `$res_query` не установлено.
     *                                    Пример сообщения:
     *                                        Результат запроса недоступен | Причина: Свойство $res_query не
     *                                        установлено
     *                                  - Свойство `$res_query` не является объектом типа `PDOStatement`.
     *                                    Пример сообщения:
     *                                        Недопустимый тип результата запроса | Ожидался объект PDOStatement,
     *                                        получено: [тип]
     *
     * @note    Этот метод является точкой входа для извлечения результатов запроса. Все проверки и обработка
     *          выполняются в защищённом методе _res_arr_internal().
     *
     * @warning Метод чувствителен к состоянию свойства `$res_query`. Убедитесь, что перед вызовом метода был выполнен
     *          запрос, который установил значение `$res_query` как объект `PDOStatement`.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $db->select(['id', 'name'], 'users', ['where' => 'status = 1']);
     * $results = $db->res_arr();
     * if ($results) {
     *     foreach ($results as $row) {
     *         echo "ID: " . $row['id'] . ", Name: " . $row['name'] . "\n";
     *     }
     * } else {
     *     echo "No results found.";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Database::_res_arr_internal()
     *          Защищённый метод, который выполняет основную логику извлечения результатов запроса.
     */
    public function res_arr(): array|false
    {
        return $this->_res_arr_internal();
    }

    /**
     * @brief   Извлекает все строки результата из подготовленного выражения, хранящегося в свойстве `$res_query`, и
     *          возвращает их как массив.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет, что свойство `$res_query` установлено и является объектом типа `PDOStatement`. Если это
     *          не
     *             так, выбрасывается исключение.
     *          2. Использует метод `fetchAll(PDO::FETCH_ASSOC)` для извлечения всех строк результата в виде массива
     *             ассоциативных массивов.
     *          3. Если результатов нет (метод `fetchAll` возвращает пустой массив), явно возвращается `false`.
     *          4. В противном случае возвращается массив, содержащий все строки результата.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `res_arr()`.
     *
     * @callergraph
     * @callgraph
     *
     * @return array|false Возвращает массив ассоциативных массивов, содержащих данные всех строк результата, если они
     *                     доступны. Если результатов нет, возвращает `false`.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - Свойство `$res_query` не установлено.
     *                                    Пример сообщения:
     *                                        Результат запроса недоступен | Причина: Свойство $res_query не
     *                                        установлено
     *                                  - Свойство `$res_query` не является объектом типа `PDOStatement`.
     *                                    Пример сообщения:
     *                                        Недопустимый тип результата запроса | Ожидался объект PDOStatement,
     *                                        получено: [тип]
     *
     * @warning Метод чувствителен к состоянию свойства `$res_query`. Убедитесь, что перед вызовом метода был выполнен
     *          запрос, который установил значение `$res_query` как объект `PDOStatement`.
     *
     * Пример использования метода _res_arr_internal():
     * @code
     * // Предполагается, что запрос уже выполнен и `$res_query` установлен
     * $db->select(['id', 'name'], 'users', ['where' => 'status = 1']);
     * $results = $this->_res_arr_internal();
     * if ($results) {
     *     foreach ($results as $row) {
     *         echo "ID: " . $row['id'] . ", Name: " . $row['name'] . "\n";
     *     }
     * } else {
     *     echo "No results found.";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Database::$res_query
     *          Свойство, хранящее результат подготовленного выражения.
     * @see     PhotoRigma::Classes::Database::execute_query()
     *          Метод, который устанавливает значение `$res_query`.
     * @see     PhotoRigma::Classes::Database::select()
     *          Метод, который может использовать `_res_arr_internal()` для получения результатов SELECT-запроса.
     * @see     PhotoRigma::Classes::Database::join()
     *          Метод, который может использовать `_res_arr_internal()` для получения результатов SELECT-запроса с
     *          использованием JOIN.
     * @see     PhotoRigma::Classes::Database::res_arr()
     *          Публичный метод-редирект для вызова этой логики.
     */
    protected function _res_arr_internal(): array|false
    {
        // === 1. Валидация состояния запроса ===
        if (!isset($this->res_query) || !($this->res_query instanceof PDOStatement)) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Результат запроса недоступен или имеет неверный тип | Причина: Отсутствует или некорректен объект PDOStatement"
            );
        }

        // === 2. Извлечение всех строк результата ===
        $rows = $this->res_query->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return false; // Явно возвращаем false, если результатов нет
        }
        return $rows;
    }

    /**
     * @brief   Извлекает одну строку результата запроса через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _res_row_internal().
     *          Он извлекает одну строку результата из подготовленного выражения, хранящегося в свойстве `$res_query`.
     *          Безопасность обеспечивается проверкой типа свойства `$res_query`. Метод предназначен для использования
     *          клиентским кодом как точка входа для получения результатов запроса построчно.
     *
     * @return array|false Возвращает ассоциативный массив, содержащий данные одной строки результата, если они
     *                     доступны. Если результатов нет, возвращает `false`.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - Свойство `$res_query` не установлено.
     *                                    Пример сообщения:
     *                                        Результат запроса недоступен | Причина: Свойство $res_query не
     *                                        установлено
     *                                  - Свойство `$res_query` не является объектом типа `PDOStatement`.
     *                                    Пример сообщения:
     *                                        Недопустимый тип результата запроса | Ожидался объект PDOStatement,
     *                                        получено: [тип]
     *
     * @note    Этот метод является точкой входа для извлечения одной строки результата. Все проверки и обработка
     *          выполняются в защищённом методе _res_row_internal().
     *
     * @warning Метод чувствителен к состоянию свойства `$res_query`. Убедитесь, что перед вызовом метода был выполнен
     *          запрос, который установил значение `$res_query` как объект `PDOStatement`.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $db->select(['id', 'name'], 'users', ['where' => 'status = 1']);
     * while ($row = $db->res_row()) {
     *     echo "ID: " . $row['id'] . ", Name: " . $row['name'] . "\n";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Database::_res_row_internal()
     *          Защищённый метод, который выполняет основную логику извлечения одной строки результата.
     */
    public function res_row(): array|false
    {
        return $this->_res_row_internal();
    }

    /**
     * @brief   Извлекает одну строку результата из подготовленного выражения, хранящегося в свойстве `$res_query`.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет, что свойство `$res_query` установлено и является объектом типа `PDOStatement`. Если это
     *          не
     *             так, выбрасывается исключение.
     *          2. Использует метод `fetch(PDO::FETCH_ASSOC)` для извлечения одной строки результата в виде
     *          ассоциативного массива.
     *          3. Если результатов нет (метод `fetch` возвращает `false`), явно возвращается `false`.
     *          4. В противном случае возвращается ассоциативный массив, содержащий данные строки.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `res_row()`.
     *
     * @callergraph
     * @callgraph
     *
     * @return array|false Возвращает ассоциативный массив, содержащий данные одной строки результата, если они
     *                     доступны. Если результатов нет, возвращает `false`.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - Свойство `$res_query` не установлено.
     *                                    Пример сообщения:
     *                                        Результат запроса недоступен | Причина: Свойство $res_query не
     *                                        установлено
     *                                  - Свойство `$res_query` не является объектом типа `PDOStatement`.
     *                                    Пример сообщения:
     *                                        Недопустимый тип результата запроса | Ожидался объект PDOStatement,
     *                                        получено: [тип]
     *
     * @warning Метод чувствителен к состоянию свойства `$res_query`. Убедитесь, что перед вызовом метода был выполнен
     *          запрос, который установил значение `$res_query` как объект `PDOStatement`.
     *
     * Пример использования метода _res_row_internal():
     * @code
     * // Предполагается, что запрос уже выполнен и `$res_query` установлен
     * $db->select(['id', 'name'], 'users', ['where' => 'status = 1']);
     * while ($row = $this->_res_row_internal()) {
     *     echo "ID: " . $row['id'] . ", Name: " . $row['name'] . "\n";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Database::$res_query
     *          Свойство, хранящее результат подготовленного выражения.
     * @see     PhotoRigma::Classes::Database::execute_query()
     *          Метод, который устанавливает значение `$res_query`.
     * @see     PhotoRigma::Classes::Database::select()
     *          Метод, который может использовать `_res_row_internal()` для получения результатов SELECT-запроса.
     * @see     PhotoRigma::Classes::Database::join()
     *          Метод, который может использовать `_res_row_internal()` для получения результатов SELECT-запроса с
     *          использованием JOIN.
     * @see     PhotoRigma::Classes::Database::res_row()
     *          Публичный метод-редирект для вызова этой логики.
     */
    protected function _res_row_internal(): array|false
    {
        // === 1. Валидация состояния запроса ===
        if (!isset($this->res_query) || !($this->res_query instanceof PDOStatement)) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Результат запроса недоступен или имеет неверный тип | Причина: Отсутствует или некорректен объект PDOStatement"
            );
        }

        // === 2. Извлечение строки результата ===
        return $this->res_query->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @brief   Отменяет транзакцию в базе данных через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _rollback_transaction_internal().
     *          Он отменяет транзакцию в базе данных через объект PDO и записывает лог с указанием контекста. Метод
     *          предназначен для использования клиентским кодом как точка входа для отмены транзакций.
     *
     * @param string $context Контекст транзакции (необязательный параметр):
     *                        - Используется для описания цели или места отмены транзакции.
     *                        - По умолчанию: пустая строка.
     *
     * @throws Exception Выбрасывается, если произошла ошибка при отмене транзакции (см.
     *                   `_rollback_transaction_internal()`).
     *
     * @note    Этот метод является точкой входа для отмены транзакций. Все проверки и обработка выполняются в
     *          защищённом методе _rollback_transaction_internal().
     *
     * @warning Убедитесь, что транзакция была начата перед вызовом этого метода.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $db = new \PhotoRigma\Classes\Database();
     * $db->rollback_transaction('Отмена сохранения данных пользователя');
     * // Лог: [DB] Транзакция отменена | Контекст: Отмена сохранения данных пользователя
     * @endcode
     * @see     PhotoRigma::Classes::Database::_rollback_transaction_internal()
     *          Защищённый метод, реализующий основную логику отмены транзакции.
     */
    public function rollback_transaction(string $context = ''): void
    {
        $this->_rollback_transaction_internal($context);
    }

    /**
     * @brief   Отменяет транзакцию в базе данных.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Отменяет транзакцию в базе данных через объект PDO с использованием метода `rollBack()`.
     *          2. Логирует информацию об отмене транзакции с указанием контекста через функцию `log_in_file()`.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `rollback_transaction()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $context Контекст транзакции (необязательный параметр).
     *                        Используется для описания цели или места отмены транзакции.
     *                        По умолчанию: пустая строка.
     *
     * @throws Exception Выбрасывается, если произошла ошибка при отмене транзакции или логировании.
     *
     * @note    Метод использует логирование для записи информации об отмене транзакции.
     * @warning Убедитесь, что транзакция была начата перед вызовом этого метода.
     *
     * Пример использования метода _rollback_transaction_internal():
     * @code
     * // Отмена транзакции с указанием контекста
     * $this->_rollback_transaction_internal('Отмена сохранения данных пользователя');
     * // Лог: [DB] Транзакция отменена | Контекст: Отмена сохранения данных пользователя
     * @endcode
     * @see     PhotoRigma::Classes::Database::$pdo
     *          Объект PDO для подключения к базе данных.
     * @see     PhotoRigma::Include::log_in_file()
     *          Логирует сообщения в файл.
     * @see     PhotoRigma::Classes::Database::rollback_transaction()
     *          Публичный метод-редирект для вызова этой логики.
     */
    protected function _rollback_transaction_internal(string $context = ''): void
    {
        $this->pdo->rollBack();
        log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | [DB] Транзакция отменена | Контекст: $context"
        );
    }

    /**
     * @brief   Выполняет SQL-запрос на выборку данных из таблицы через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _select_internal().
     *          Он формирует SQL-запрос на выборку данных из таблицы, основываясь на полученных аргументах, сохраняет
     *          его в свойстве `$txt_query` и выполняет. Безопасность обеспечивается использованием подготовленных
     *          выражений с параметрами. Метод предназначен для использования клиентским кодом как точка входа для
     *          выполнения SELECT-запросов.
     *
     * @param string|array $select     Список полей для выборки:
     *                                 - Может быть строкой (имя одного поля) или массивом (список полей).
     *                                 - Если передан массив, он преобразуется в строку с разделителем `,`.
     *                                 Пример: "id", ["id", "name"].
     *                                 Ограничения: массив не может содержать элементы, которые не являются строками.
     * @param string       $from_tbl   Имя таблицы, из которой выбираются данные:
     *                                 - Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                                 символов.
     *                                 - Ограничения: строка должна быть непустой.
     * @param array        $options    Массив опций для формирования запроса. Поддерживаемые ключи:
     *                                 - where (string|array): Условие WHERE. Может быть строкой или ассоциативным
     *                                 массивом. Для защиты от SQL-инъекций рекомендуется использовать плейсхолдеры
     *                                 (например, `:status`).
     *                                 - group (string): Группировка GROUP BY.
     *                                 - order (string): Сортировка ORDER BY.
     *                                 - limit (int|string): Ограничение LIMIT.
     *                                 - params (array): Параметры для подготовленного выражения.
     *                                 Использование параметров `params` является обязательным для защиты от
     *                                 SQL-инъекций.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$from_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                        Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                                  - `$options` не является массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     *                                  - `$select` не является строкой или массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра select | Ожидалась строка или массив,
     *                                        получено: [тип]
     *                                  - Элемент в списке `$select` не является строкой.
     *                                    Пример сообщения:
     *                                        Недопустимый элемент в выборке | Ожидалась строка, получено: [тип]
     * @throws Exception Выбрасывается при ошибках выполнения запроса.
     *
     * @note    Этот метод является точкой входа для выполнения SELECT-запросов. Все проверки и обработка выполняются в
     *          защищённом методе _select_internal().
     *
     * @warning Метод чувствителен к корректности входных данных. Убедитесь, что массив `$select` содержит только
     *          строки. Использование параметров `params` для подготовленных выражений является обязательным для защиты
     *          от SQL-инъекций и обеспечения совместимости с различными СУБД.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $db = new \PhotoRigma\Classes\Database();
     * $db->select(['id', 'name'], 'users', [
     *     'where' => 'status = :status',
     *     'group' => 'category_id',
     *     'order' => 'created_at DESC',
     *     'limit' => 10,
     *     'params' => [':status' => 'active']
     * ]);
     * @endcode
     * @see     PhotoRigma::Classes::Database::_select_internal()
     *          Защищённый метод, реализующий основную логику формирования и выполнения SQL-запроса SELECT.
     */
    public function select(string|array $select, string $from_tbl, array $options = []): bool
    {
        return $this->_select_internal($select, $from_tbl, $options);
    }

    /**
     * @brief   Формирует SQL-запрос на выборку данных из таблицы, основываясь на полученных аргументах, размещает его
     *          в свойстве `$txt_query` и выполняет.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет типы входных данных: `$select` (строка или массив), `$from_tbl` (строка), `$options`
     *          (массив).
     *          2. Обрабатывает список полей для выборки (`$select`):
     *             - Если `$select` является строкой, она разбивается по запятым и преобразуется в массив.
     *             - Каждое имя поля проверяется на корректность и преобразуется в строку с разделителем `,`.
     *          3. Формирует базовый SQL-запрос `SELECT ... FROM ...`.
     *          4. Добавляет условия WHERE, GROUP BY, ORDER BY и LIMIT, если они указаны в параметре `$options`.
     *          5. Сохраняет сформированный запрос в свойстве `$txt_query`.
     *          6. Выполняет запрос через метод `execute_query`, передавая параметры `params` для подготовленного
     *          выражения. Использование параметров `params` является обязательным для подготовленных выражений, так
     *          как это обеспечивает защиту от SQL-инъекций и совместимость с различными СУБД. Этот метод является
     *          защищенным и предназначен для использования внутри класса или его наследников. Основная логика
     *          вызывается через публичный метод-редирект `select()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string|array $select     Список полей для выборки. Может быть строкой (имя одного поля) или массивом
     *                                 (список полей). Если передан массив, он преобразуется в строку с разделителем
     *                                 `,`. Пример: "id", ["id", "name"]. Ограничения: массив не может содержать
     *                                 элементы, которые не являются строками.
     * @param string       $from_tbl   Имя таблицы, из которой выбираются данные.
     *                                 Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                                 символов. Ограничения: строка должна быть непустой.
     * @param array        $options    Массив опций для формирования запроса. Поддерживаемые ключи:
     *                                 - where (string|array): Условие WHERE. Может быть строкой (например, "status =
     *                                 :status") или ассоциативным массивом (например, ["id" => ":id", "status" =>
     *                                 ":status"]). Для защиты от SQL-инъекций рекомендуется использовать плейсхолдеры
     *                                 (например, `:status`).
     *                                 - group (string): Группировка GROUP BY. Должна быть строкой с именами полей
     *                                 (например, "category_id").
     *                                 - order (string): Сортировка ORDER BY. Должна быть строкой с именами полей и
     *                                 направлением (например, "created_at DESC").
     *                                 - limit (int|string): Ограничение LIMIT. Может быть числом (например, 10) или
     *                                 строкой с диапазоном (например, "0, 10").
     *                                 - params (array): Параметры для подготовленного выражения. Ассоциативный массив
     *                                 значений, используемых в запросе (например, [":id" => 1, ":status" =>
     *                                 "active"]).
     *                                 Использование параметров `params` является обязательным для подготовленных
     *                                 выражений, так как это обеспечивает защиту от SQL-инъекций и совместимость с
     *                                 различными СУБД.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$from_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                        Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                                  - `$options` не является массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     *                                  - `$select` не является строкой или массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра select | Ожидалась строка или массив,
     *                                        получено: [тип]
     *                                  - Элемент в списке `$select` не является строкой.
     *                                    Пример сообщения:
     *                                        Недопустимый элемент в выборке | Ожидалась строка, получено: [тип]
     * @throws Exception Выбрасывается при ошибках выполнения запроса.
     *
     * @warning Метод чувствителен к корректности входных данных. Неверные типы данных или некорректные значения могут
     *          привести к выбросу исключения. Убедитесь, что массив `$select` содержит только строки.
     *          Использование параметров `params` для подготовленных выражений является обязательным для защиты от
     *          SQL-инъекций и обеспечения совместимости с различными СУБД. Игнорирование этого правила может привести
     *          к уязвимостям безопасности и неправильной работе с базой данных.
     *
     * Пример использования метода _select_internal():
     * @code
     * // Безопасное использование с параметрами `params`
     * $this->_select_internal(
     *     ['id', 'name'],
     *     'users',
     *     [
     *         'where' => 'status = :status',
     *         'group' => 'category_id',
     *         'order' => 'created_at DESC',
     *         'limit' => 10,
     *         'params' => [':status' => 'active']
     *     ]
     * );
     * @endcode
     * @see     PhotoRigma::Classes::Database::$txt_query
     *          Свойство, в которое помещается текст SQL-запроса.
     * @see     PhotoRigma::Classes::Database::select()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Database::execute_query()
     *          Выполняет SQL-запрос.
     * @see     PhotoRigma::Classes::Database::build_conditions()
     *          Формирует условия WHERE, GROUP BY, ORDER BY и LIMIT для запроса.
     */
    protected function _select_internal(string|array $select, string $from_tbl, array $options = []): bool
    {
        // === 1. Обработка $select ===
        if (!is_array($select)) {
            // Разбиваем строку по запятым, если это строка
            $select = array_map('trim', explode(',', $select));
        }

        // Проверяем каждую часть выборки на корректность
        foreach ($select as $field) {
            if (!is_string($field)) {
                throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый элемент в выборке | Ожидалась строка, получено: " . gettype(
                        $field
                    )
                );
            }
        }
        // Формирования списка выборки
        $select = implode(', ', $select);

        // === 2. Формирование базового запроса ===
        $this->txt_query = "SELECT $select FROM $from_tbl";

        // === 3. Добавление условий ===
        [$conditions, $params] = $this->build_conditions($options);
        $this->txt_query .= $conditions;

        // === 4. Выполнение запроса ===
        return $this->execute_query($params);
    }

    /**
     * @brief   Очищает таблицу через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _truncate_internal().
     *          Он формирует SQL-запрос TRUNCATE TABLE для очистки таблицы, сохраняет его в свойстве `$txt_query` и
     *          выполняет. Запрос TRUNCATE полностью очищает таблицу, удаляя все строки без возможности восстановления.
     *          Метод предназначен для использования клиентским кодом как точка входа для выполнения операции TRUNCATE.
     *
     * @param string $from_tbl Имя таблицы, которую необходимо очистить:
     *                         - Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                         символов.
     *                         - Ограничения: строка должна быть непустой.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$from_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                        Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     * @throws Exception Выбрасывается при ошибках выполнения запроса.
     *
     * @note    Этот метод является точкой входа для выполнения TRUNCATE. Все проверки и обработка выполняются в
     *          защищённом методе _truncate_internal().
     *
     * @warning Метод чувствителен к корректности входных данных. Запрос TRUNCATE полностью очищает таблицу, удаляя все
     *          строки без возможности восстановления. Этот метод следует использовать с осторожностью, так как он не
     *          поддерживает откат операции через транзакции.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $db = new \PhotoRigma\Classes\Database();
     * $db->truncate('users');
     * @endcode
     * @see     PhotoRigma::Classes::Database::_truncate_internal()
     *          Защищённый метод, реализующий основную логику формирования и выполнения SQL-запроса TRUNCATE.
     */
    public function truncate(string $from_tbl): bool
    {
        return $this->_truncate_internal($from_tbl);
    }

    /**
     * @brief   Формирует SQL-запрос на очистку таблицы (TRUNCATE), размещает его в свойстве `$txt_query` и выполняет.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет тип входных данных: `$from_tbl` должен быть строкой. Если передан неверный тип,
     *          выбрасывается исключение.
     *          2. Формирует базовый SQL-запрос TRUNCATE TABLE для очистки таблицы.
     *          3. Сохраняет сформированный запрос в свойстве `$txt_query`.
     *          4. Выполняет запрос через метод `execute_query`.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `truncate()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $from_tbl Имя таблицы, которую необходимо очистить.
     *                         Должно быть строкой, содержащей только допустимые имена таблиц без специальных символов.
     *                         Ограничения: строка должна быть непустой.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$from_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                        Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     * @throws Exception Выбрасывается при ошибках выполнения запроса.
     *
     * @warning Метод чувствителен к корректности входных данных. Неверные типы данных или некорректные значения могут
     *          привести к выбросу исключения. Запрос TRUNCATE полностью очищает таблицу, удаляя все строки без
     *          возможности восстановления. Этот метод следует использовать с осторожностью, так как он не поддерживает
     *          откат операции через транзакции.
     *
     * Пример использования метода _truncate_internal():
     * @code
     * // Очистка таблицы 'users'
     * $this->_truncate_internal('users');
     * @endcode
     * @see     PhotoRigma::Classes::Database::$txt_query
     *          Свойство, в которое помещается текст SQL-запроса.
     * @see     PhotoRigma::Classes::Database::truncate()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Database::execute_query()
     *          Выполняет SQL-запрос.
     */
    protected function _truncate_internal(string $from_tbl): bool
    {
        // Формирование базового запроса
        $this->txt_query = "TRUNCATE TABLE $from_tbl";
        // Выполнение запроса
        return $this->execute_query();
    }

    /**
     * @brief   Выполняет SQL-запрос на обновление данных в таблице через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _update_internal().
     *          Он формирует SQL-запрос UPDATE с использованием переданных данных, сохраняет его в свойстве
     *          `$txt_query` и выполняет. Безопасность обеспечивается обязательным указанием условия `where`. Запрос
     *          без условия `where` не будет выполнен. Метод предназначен для использования клиентским кодом как точка
     *          входа для выполнения операции UPDATE.
     *
     * @param array  $update     Ассоциативный массив данных для обновления:
     *                           - Формат: 'имя_поля' => 'значение'.
     *                           - Пример: ["name" => ":name", "status" => ":status"].
     *                           Для защиты от SQL-инъекций рекомендуется использовать плейсхолдеры (например,
     *                           `:name`).
     * @param string $from_tbl   Имя таблицы, в которой необходимо обновить данные:
     *                           - Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                           символов.
     *                           - Ограничения: строка должна быть непустой.
     * @param array  $options    Массив опций для формирования запроса. Поддерживаемые ключи:
     *                           - where (string|array): Условие WHERE. Может быть строкой или ассоциативным массивом.
     *                           Обязательный параметр для безопасности. Без условия WHERE запрос не будет выполнен.
     *                           - order (string): Сортировка ORDER BY.
     *                           - limit (int|string): Ограничение LIMIT.
     *                           - params (array): Параметры для подготовленного выражения.
     *                           Использование параметров `params` является обязательным для защиты от SQL-инъекций.
     *                           - group (string): Группировка GROUP BY. Не поддерживается в запросах UPDATE и
     *                           удаляется
     *                           с записью в лог.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$update` не является массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра update | Ожидался массив, получено: [тип]
     *                                  - `$from_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                        Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                                  - `$options` не является массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     *                                  - Отсутствует обязательное условие `where` в массиве `$options`.
     *                                    Пример сообщения:
     *                                        Запрос UPDATE без условия WHERE запрещён | Причина: Соображения
     *                                        безопасности
     * @throws Exception Выбрасывается при ошибках выполнения запроса.
     *
     * @note    Этот метод является точкой входа для выполнения UPDATE. Все проверки и обработка выполняются в
     *          защищённом методе _update_internal().
     *
     * @warning Метод чувствителен к корректности входных данных. Безопасность обеспечивается обязательным указанием
     *          условия `where`. Запрос без условия `where` не будет выполнен. Ключ `group` не поддерживается в
     *          запросах UPDATE и удаляется с записью в лог. Использование параметров `params` для подготовленных
     *          выражений является обязательным для защиты от SQL-инъекций.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $db = new \PhotoRigma\Classes\Database();
     * $db->update(
     *     ['name' => ':name', 'status' => ':status'],
     *     'users',
     *     [
     *         'where' => 'id = :id',
     *         'params' => [':id' => 1, ':name' => 'John Doe', ':status' => 'active']
     *     ]
     * );
     * @endcode
     * @see     PhotoRigma::Classes::Database::_update_internal()
     *          Защищённый метод, реализующий основную логику формирования и выполнения SQL-запроса UPDATE.
     */
    public function update(array $update, string $from_tbl, array $options = []): bool
    {
        return $this->_update_internal($update, $from_tbl, $options);
    }

    /**
     * @brief   Формирует SQL-запрос на обновление данных в таблице, основываясь на полученных аргументах, размещает
     *          его в свойстве `$txt_query` и выполняет.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет типы входных данных: `$update` (ассоциативный массив), `$from_tbl` (строка), `$options`
     *          (массив).
     *          2. Проверяет наличие обязательного условия `where` в массиве `$options`. Если условие отсутствует,
     *             выбрасывается исключение для предотвращения случайного обновления всех данных.
     *          3. Удаляет недопустимый ключ `group` из массива `$options` с записью в лог, так как GROUP BY не
     *          поддерживается в запросах UPDATE.
     *          4. Формирует базовый SQL-запрос UPDATE с использованием преобразованных данных.
     *          5. Добавляет условия WHERE, ORDER BY и LIMIT, если они указаны в параметре `$options`.
     *          6. Сохраняет сформированный запрос в свойстве `$txt_query`.
     *          7. Выполняет запрос через метод `execute_query`, передавая параметры `params` для подготовленного
     *          выражения. Использование параметров `params` является обязательным для подготовленных выражений, так
     *          как это обеспечивает защиту от SQL-инъекций и совместимость с различными СУБД. Этот метод является
     *          защищенным и предназначен для использования внутри класса или его наследников. Основная логика
     *          вызывается через публичный метод-редирект `update()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param array  $update     Ассоциативный массив данных для обновления в формате: 'имя_поля' => 'значение'.
     *                           Пример: ["name" => ":name", "status" => ":status"].
     *                           Для защиты от SQL-инъекций рекомендуется использовать плейсхолдеры (например,
     *                           `:name`).
     * @param string $from_tbl   Имя таблицы, в которой необходимо обновить данные.
     *                           Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                           символов. Ограничения: строка должна быть непустой.
     * @param array  $options    Массив опций для формирования запроса. Поддерживаемые ключи:
     *                           - where (string|array): Условие WHERE. Может быть строкой (например, "id = :id") или
     *                           ассоциативным массивом (например, ["id" => ":id", "status" => ":status"]).
     *                           Обязательный параметр для безопасности. Без условия WHERE запрос не будет выполнен.
     *                           - order (string): Сортировка ORDER BY. Должна быть строкой с именами полей и
     *                           направлением (например, "created_at DESC").
     *                           - limit (int|string): Ограничение LIMIT. Может быть числом (например, 10) или строкой
     *                           с диапазоном (например, "0, 10").
     *                           - params (array): Параметры для подготовленного выражения. Ассоциативный массив
     *                           значений, используемых в запросе (например, [":id" => 1, ":name" => "John Doe"]).
     *                           Использование параметров `params` является обязательным для подготовленных выражений,
     *                           так как это обеспечивает защиту от SQL-инъекций и совместимость с различными СУБД.
     *                           - group (string): Группировка GROUP BY. Не поддерживается в запросах UPDATE и
     *                           удаляется
     *                           с записью в лог.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$update` не является массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра update | Ожидался массив, получено: [тип]
     *                                  - `$from_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                        Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                                  - `$options` не является массивом.
     *                                    Пример сообщения:
     *                                        Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     *                                  - Отсутствует обязательное условие `where` в массиве `$options`.
     *                                    Пример сообщения:
     *                                        Запрос UPDATE без условия WHERE запрещён | Причина: Соображения
     *                                        безопасности
     * @throws JsonException Выбрасывается при ошибках записи лога.
     * @throws Exception     Выбрасывается при ошибках выполнения запроса.
     *
     * @warning Метод чувствителен к корректности входных данных. Неверные типы данных или некорректные значения могут
     *          привести к выбросу исключения. Безопасность обеспечивается обязательным указанием условия `where`.
     *          Запрос без условия `where` не будет выполнен. Ключ `group` не поддерживается в запросах UPDATE и
     *          удаляется с записью в лог. Использование параметров `params` для подготовленных выражений является
     *          обязательным для защиты от SQL-инъекций и обеспечения совместимости с различными СУБД. Игнорирование
     *          этого правила может привести к уязвимостям безопасности и неправильной работе с базой данных.
     *
     * Пример использования метода _update_internal():
     * @code
     * // Безопасное использование с параметрами `params`
     * $this->_update_internal(
     *     ['name' => ':name', 'status' => ':status'],
     *     'users',
     *     [
     *         'where' => 'id = :id',
     *         'params' => [':id' => 1, ':name' => 'John Doe', ':status' => 'active']
     *     ]
     * );
     * @endcode
     * @see     PhotoRigma::Classes::Database::$txt_query
     *          Свойство, в которое помещается текст SQL-запроса.
     * @see     PhotoRigma::Classes::Database::update()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Database::execute_query()
     *          Выполняет SQL-запрос.
     * @see     PhotoRigma::Classes::Database::build_conditions()
     *          Формирует условия WHERE, ORDER BY и LIMIT для запроса.
     */
    protected function _update_internal(array $update, string $from_tbl, array $options = []): bool
    {
        // === 1. Валидация аргументов ===
        if (empty($options['where'])) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Запрос UPDATE без условия WHERE запрещён | Причина: Соображения безопасности"
            );
        }

        // === 2. Удаление недопустимого ключа `group` ===
        if (isset($options['group'])) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Был использован GROUP BY в UPDATE | Переданные опции: " . json_encode(
                    $options,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                )
            );
            unset($options['group']); // Удаляем ключ 'group'
        }

        // === 3. Формирование списка полей для обновления ===
        $update = array_map(static function ($key, $value) {
            return "$key = $value";
        }, array_keys($update), array_values($update));
        $set_clause = implode(', ', $update);

        // === 4. Формирование базового запроса ===
        $this->txt_query = "UPDATE $from_tbl SET $set_clause";

        // === 5. Добавление условий ===
        [$conditions, $params] = $this->build_conditions($options);
        $this->txt_query .= $conditions;

        // === 6. Выполнение запроса ===
        return $this->execute_query($params);
    }

    /**
     * @brief   Временно изменяет формат SQL для выполнения запросов в указанном контексте.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _with_sql_format_internal().
     *          Он временно изменяет формат SQL для выполнения запросов, передавая управление в коллбэк.
     *          После завершения коллбэка формат SQL автоматически сбрасывается до значения по умолчанию ('mysql').
     *          Метод предназначен для использования клиентским кодом как точка входа для временного изменения формата SQL.
     *
     * @param string   $format   Формат SQL, который нужно использовать временно:
     *                           - Поддерживаемые значения: 'mysql', 'pgsql', 'sqlite'.
     * @param callable $callback Коллбэк, содержащий код, который должен выполняться
     *                           в контексте указанного формата SQL:
     *                           - Должен быть callable (например, анонимная функция или замыкание).
     *
     * @return mixed Результат выполнения коллбэка.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$format` не является поддерживаемым значением.
     *                                    Пример сообщения:
     *                                        Неподдерживаемый формат SQL | Формат: [format]
     * @throws Exception Выбрасывается при ошибках выполнения запроса внутри коллбэка.
     *
     * @note    Этот метод является точкой входа для временного изменения формата SQL. Все проверки и обработка
     *          выполняются в защищённом методе _with_sql_format_internal().
     *
     * @warning Важные замечания:
     *          1. Убедитесь, что все методы, вызываемые внутри коллбэка, поддерживают указанный формат SQL.
     *          2. Не используйте методы других классов, зависящих от объекта `$db`, если они написаны
     *             с жесткой привязкой к определенному формату SQL (например, MySQL).
     *             Это может привести к ошибкам.
     *          3. Для выполнения нескольких запросов в одном формате оберните их в один коллбэк.
     *
     * Пример использования:
     * @code
     * // Выполнение запроса в формате PostgreSQL
     * $years_list = $db->with_sql_format('pgsql', function () use ($db) {
     *     // Форматирование даты для PostgreSQL
     *     $formatted_date = $db->format_date('data_last_edit', 'YYYY');
     *     // Выборка данных
     *     $db->select(
     *         'DISTINCT ' . $formatted_date . ' AS year',
     *         TBL_NEWS,
     *         [
     *             'order' => 'year ASC',
     *         ]
     *     );
     *     // Получение результата
     *     return $db->res_arr();
     * });
     * print_r($years_list);
     * @endcode
     * @see PhotoRigma::Classes::Database::_with_sql_format_internal()
     *      Защищённый метод, реализующий основную логику временного изменения формата SQL.
     */
    public function with_sql_format(string $format, callable $callback): mixed
    {
        return $this->_with_sql_format_internal($format, $callback);
    }

    /**
     * @brief   Метод временно изменяет формат SQL для выполнения запросов в указанном контексте.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет поддерживаемость указанного формата SQL. Если формат не поддерживается,
     *             выбрасывается исключение `InvalidArgumentException`.
     *          2. Сохраняет текущий формат SQL в свойстве `$current_format`.
     *          3. Устанавливает временный формат SQL, переданный в параметре `$format`.
     *          4. Выполняет коллбэк, содержащий код, который должен выполняться в новом формате SQL.
     *          5. Восстанавливает исходный формат SQL после завершения коллбэка (даже если произошла ошибка).
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `with_sql_format()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string   $format   Формат SQL, который нужно использовать временно.
     *                           Поддерживаемые значения: 'mysql', 'pgsql', 'sqlite'.
     * @param callable $callback Коллбэк, содержащий код, который должен выполняться
     *                           в контексте указанного формата SQL.
     *
     * @return mixed Результат выполнения коллбэка.
     *
     * @throws InvalidArgumentException Выбрасывается, если указан неподдерживаемый формат SQL.
     *                                  Пример сообщения:
     *                                      Неподдерживаемый формат SQL | Формат: [format]
     *
     * @warning Важные замечания:
     *          1. Убедитесь, что все методы, вызываемые внутри коллбэка, поддерживают указанный формат SQL.
     *          2. Не используйте методы других классов, зависящих от объекта `$db`, если они написаны
     *             с жесткой привязкой к определенному формату SQL (например, MySQL).
     *             Это может привести к ошибкам.
     *          3. Для выполнения нескольких запросов в одном формате оберните их в один коллбэк.
     *
     * Пример использования метода _with_sql_format_internal():
     * @code
     * // Выполнение запроса в формате PostgreSQL
     * $years_list = $this->with_sql_format('pgsql', function () use ($db) {
     *     // Форматирование даты для PostgreSQL
     *     $formatted_date = $this->format_date('data_last_edit', 'YYYY');
     *     // Выборка данных
     *     $this->select(
     *         'DISTINCT ' . $formatted_date . ' AS year',
     *         TBL_NEWS,
     *         [
     *             'order' => 'year ASC',
     *         ]
     *     );
     *     // Получение результата
     *     return $this->res_arr();
     * });
     * print_r($years_list);
     * @endcode
     * @see PhotoRigma::Classes::Database::$current_format
     *      Свойство, хранящее текущий формат SQL.
     * @see PhotoRigma::Classes::Database::with_sql_format()
     *      Публичный метод-редирект для вызова этой логики.
     */
    protected function _with_sql_format_internal(string $format, callable $callback): mixed
    {
        // Проверяем поддерживаемые форматы
        if (!in_array($format, $this->allowed_formats, true)) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Неподдерживаемый формат SQL | Формат: $format"
            );
        }

        // Сохраняем текущий формат
        $original_format = $this->current_format;

        // Устанавливаем временный формат
        $this->current_format = $format;

        try {
            // Выполняем коллбэк и возвращаем его результат
            return $callback();
        } finally {
            // Восстанавливаем исходный формат
            $this->current_format = $original_format;
        }
    }

    /**
     * Основной метод для выполнения полнотекстового поиска.
     *
     * Этот публичный метод служит точкой входа для выполнения полнотекстового поиска.
     * Он делегирует выполнение внутреннему методу `_full_text_search_internal`.
     *
     * @param array            $columns_to_return Массив столбцов, которые нужно вернуть.
     * @param array            $columns_to_search Массив столбцов, по которым выполняется поиск.
     * @param string           $search_string     Строка поиска (может быть "*" для поиска всех строк).
     * @param string           $table             Имя таблицы, в которой выполняется поиск.
     * @param int|string|false $limit             Ограничение на количество строк в результате.
     *                                            Может быть целым числом (например, 10), строкой (например, "0,10")
     *                                            или false (без ограничения).
     *
     * @return array|false Результат выполнения поиска (массив данных или false, если результат пустой).
     *
     * @throws RuntimeException         Если не удалось получить версию БД из таблицы db_version.
     * @throws InvalidArgumentException Если переданы недопустимые аргументы или тип СУБД не поддерживается.*
     * @throws JsonException при работе с методами для кеша
     * @throws Exception                Если произошла ошибка при выполнении запроса.
     */
    public function full_text_search(
        array $columns_to_return,
        array $columns_to_search,
        string $search_string,
        string $table,
        int|string|false $limit = false
    ): array|false {
        return $this->_full_text_search_internal(
            $columns_to_return,
            $columns_to_search,
            $search_string,
            $table,
            $limit
        );
    }

    /**
     * Основной метод для выполнения полнотекстового поиска.
     *
     * @param array            $columns_to_return Массив столбцов, которые нужно вернуть.
     * @param array            $columns_to_search Массив столбцов, по которым выполняется поиск.
     * @param string           $search_string     Строка поиска (может быть "*" для поиска всех строк).
     * @param string           $table             Имя таблицы, в которой выполняется поиск.
     * @param int|string|false $limit             Ограничение на количество строк в результате.
     *
     * @return array|false Результат выполнения поиска (массив данных или false, если результат пустой).
     *
     * @throws RuntimeException         Если не удалось получить версию БД из таблицы db_version.
     * @throws InvalidArgumentException Если переданы недопустимые аргументы или тип СУБД не поддерживается.*
     * @throws JsonException при работе с методами для кеша
     * @throws Exception                Если произошла ошибка при выполнении запроса.
     */
    protected function _full_text_search_internal(
        array $columns_to_return,
        array $columns_to_search,
        string $search_string,
        string $table,
        int|string|false $limit = false
    ): array|false {
        // 1. Проверка аргументов
        if (empty($columns_to_return) || empty($columns_to_search) || empty($table)) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: 'global') . ") | " . "Недопустимые аргументы | Подробности: columns_to_return, columns_to_search или table пусты"
            );
        }
        if ($search_string === '') {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: 'global') . ") | " . "Пустая строка поиска"
            );
        }

        // 2. Если search_string == '*', делаем обычный SELECT через _select_internal
        if ($search_string === '*') {
            $this->_select_internal(
                $columns_to_return,
                $table,
                ['limit' => $limit]
            );

            return $this->_res_arr_internal();
        }

        // 3. Получение версии базы данных
        $this->_select_internal('ver', 'db_version', ['limit' => 1]);

        $version_data = $this->_res_row_internal();

        if ($version_data === false || !isset($version_data['ver'])) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: 'global') . ") | " . "Не удалось получить корректную версию из таблицы db_version"
            );
        }

        $db_version = $version_data['ver'];

        // 4. Снятие экранирования
        $columns_to_return = $this->unescape_identifiers($columns_to_return);
        $columns_to_search = $this->unescape_identifiers($columns_to_search);
        $table = $this->unescape_identifiers([$table])[0];

        // 5. Выбор подметода в зависимости от типа СУБД
        return match ($this->db_type) {
            'mysql'  => $this->full_text_search_mysql(
                $columns_to_return,
                $columns_to_search,
                $search_string,
                $table,
                $limit,
                $db_version
            ),
            'pgsql'  => $this->full_text_search_pgsql(
                $columns_to_return,
                $columns_to_search,
                $search_string,
                $table,
                $limit,
                $db_version
            ),
            'sqlite' => $this->full_text_search_sqlite(
                $columns_to_return,
                $columns_to_search,
                $search_string,
                $table,
                $limit,
                $db_version
            ),
            default  => throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: 'global') . ") | " . "Неизвестный тип СУБД | Тип: $this->db_type"
            ),
        };
    }

    /**
     * Метод для выполнения полнотекстового поиска в MySQL.
     *
     * @param array            $columns_to_return Массив столбцов, которые нужно вернуть.
     * @param array            $columns_to_search Массив столбцов, по которым выполняется поиск.
     * @param string           $search_string     Строка поиска (уже подготовлена, без % или *).
     * @param string           $table             Имя таблицы, в которой выполняется поиск.
     * @param int|string|false $limit             Ограничение на количество строк в результате.
     * @param string           $db_version        Версия БД, полученная из SELECT ver FROM db_version.
     *
     * @return array|false Результат выполнения поиска (массив данных или false при ошибке/пустом результате)
     *
     * @throws JsonException при работе с методами для кеша
     * @throws Exception Если произошла ошибка при логировании через log_in_file()
     */
    private function full_text_search_mysql(
        array $columns_to_return,
        array $columns_to_search,
        string $search_string,
        string $table,
        int|string|false $limit,
        string $db_version
    ): array|false {
        // 1. Формируем экранированные имена столбцов и таблицы
        $columns_to_search_escaped = array_map(static fn ($col) => "`$col`", $columns_to_search);
        $columns_to_return_escaped = array_map(static fn ($col) => "`$col`", $columns_to_return);
        $table_escaped = "`$table`";

        // 2. Проверка минимальной длины строки поиска
        if (strlen($search_string) < MIN_FULLTEXT_SEARCH_LENGTH) {
            return $this->fallback_to_like_mysql(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $limit
            );
        }

        // 3. Формируем уникальный ключ для кэша
        $key = 'fts_mysql_' . substr(hash('xxh3', $table . ':' . implode(',', $columns_to_search)), 0, 24);

        // 4. Проверяем кэш: был ли ранее query_error
        $cached_index = $this->cache->is_valid($key, $db_version);

        if ($cached_index !== false && isset($cached_index['query_error']) && $cached_index['query_error'] === true) {
            // Запрос ранее упал — делаем fallback_to_like
            return $this->fallback_to_like_mysql(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $limit
            );
        }

        // 5. Выполняем полнотекстовый поиск
        try {
            $this->_select_internal(
                $columns_to_return_escaped,
                $table_escaped,
                [
                    'where'  => 'MATCH(' . implode(
                        ', ',
                        $columns_to_search_escaped
                    ) . ') AGAINST(:search_string_where IN NATURAL LANGUAGE MODE)',
                    'order'  => 'MATCH(' . implode(
                        ', ',
                        $columns_to_search_escaped
                    ) . ') AGAINST(:search_string_order) DESC',
                    'limit'  => $limit,
                    'params' => [
                        ':search_string_where' => $search_string,
                        ':search_string_order' => $search_string,
                    ],
                ]
            );
        } catch (Throwable $e) {
            // Ловим ошибку выполнения полнотекстового запроса
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') .
                ") | Полнотекстовый запрос упал | Таблица: $table_escaped | Сообщение: {$e->getMessage()}"
            );

            // Обновляем кэш: теперь запрос выдает ошибку
            $this->cache->update_cache($key, $db_version, ['query_error' => true]);

            // Переходим на LIKE
            return $this->fallback_to_like_mysql(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $limit
            );
        }

        // 6. Сохраняем в кэш успешный результат: полнотекстовый запрос отработал
        $this->cache->update_cache($key, $db_version, ['query_error' => false]);

        // 7. Получаем результат и отдаем его
        return $this->_res_arr_internal();
    }

    /**
     * Альтернативный поиск через LIKE для MySQL.
     *
     * Используется как fallback, когда полнотекстовый поиск недоступен или не дал результатов.
     * Поддерживает несколько столбцов и уникальные плейсхолдеры для каждого условия LIKE.
     *
     * @param array            $columns_to_return_escaped Уже экранированные столбцы (например, `login`, `real_name`)
     * @param array            $columns_to_search_escaped Уже экранированные столбцы для поиска
     * @param string           $search_string             Строка поиска (уже подготовлена, но без %)
     * @param string           $table_escaped             Уже экранированное имя таблицы (например, `users`)
     * @param int|string|false $limit                     Ограничение на количество строк в результате
     *
     * @return array|false Результат выполнения поиска (массив данных или false при ошибке/пустом результате)
     *
     * @throws Exception Если произошла ошибка логирования через log_in_file()
     */
    private function fallback_to_like_mysql(
        array $columns_to_return_escaped,
        array $columns_to_search_escaped,
        string $search_string,
        string $table_escaped,
        int|string|false $limit
    ): array|false {
        // 1. Проверяем пустую строку
        if ($search_string === '') {
            return false;
        }

        // 2. Формируем условия WHERE и параметры для PDO
        $where_conditions = [];
        $params = [];

        foreach ($columns_to_search_escaped as $i => $column) {
            $placeholder = ":search_string_$i";
            $where_conditions[] = "$column LIKE $placeholder";
            $params[$placeholder] = "%$search_string%";
        }

        $where_sql = '(' . implode(' OR ', $where_conditions) . ')';

        // 3. Выполняем запрос через _select_internal
        try {
            $this->_select_internal(
                $columns_to_return_escaped,
                $table_escaped,
                [
                    'where'  => $where_sql,
                    'limit'  => $limit,
                    'params' => $params,
                ]
            );
        } catch (Throwable $e) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Ошибка при выполнении LIKE-поиска | Таблица: $table_escaped, Сообщение: {$e->getMessage()}"
            );
            return false;
        }

        // 4. Возвращаем результат напрямую
        return $this->_res_arr_internal();
    }

    /**
     * Метод для выполнения полнотекстового поиска в PostgreSQL.
     *
     * @param array            $columns_to_return Массив столбцов, которые нужно вернуть.
     * @param array            $columns_to_search Массив столбцов, по которым выполняется поиск (только для кэша).
     * @param string           $search_string     Строка поиска (уже подготовлена).
     * @param string           $table             Имя таблицы, в которой выполняется поиск.
     * @param int|string|false $limit             Ограничение на количество строк в результате.
     * @param string           $db_version        Версия БД, полученная из SELECT ver FROM db_version.
     *
     * @return array|false Результат выполнения поиска (массив данных или false при ошибке/пустом результате)
     *
     * @throws JsonException при работе с методами для кеша
     * @throws Exception Если произошла ошибка при выполнении запроса или логировании через log_in_file()
     */
    private function full_text_search_pgsql(
        array $columns_to_return,
        array $columns_to_search,
        string $search_string,
        string $table,
        int|string|false $limit,
        string $db_version
    ): array|false {
        // 1. Формируем экранированные имена столбцов и таблицы
        $columns_to_return_escaped = array_map(static fn ($col) => "\"$col\"", $columns_to_return);
        $columns_to_search_escaped = array_map(static fn ($col) => "\"$col\"", $columns_to_search);
        $table_escaped = "\"$table\"";

        // 2. Проверка минимальной длины строки поиска
        if (strlen($search_string) < MIN_FULLTEXT_SEARCH_LENGTH) {
            return $this->fallback_to_ilike_pgsql(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $limit
            );
        }

        // 3. Формируем уникальный ключ для кэша
        $key = 'fts_pgsql_' . substr(hash('xxh3', $table . ':' . implode(',', $columns_to_search)), 0, 24);

        // 4. Проверяем кэш: был ли ранее query_error
        $cached_index = $this->cache->is_valid($key, $db_version);

        if ($cached_index !== false && isset($cached_index['query_error']) && $cached_index['query_error'] === true) {
            return $this->fallback_to_ilike_pgsql(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $limit
            );
        }

        // 5. Выполняем полнотекстовый поиск через tsv_weighted
        try {
            $this->_select_internal(
                $columns_to_return_escaped,
                $table_escaped,
                [
                    'where'  => 'tsv_weighted @@ plainto_tsquery(:search_string_where)',
                    'order'  => 'ts_rank(tsv_weighted, plainto_tsquery(:search_string_order)) DESC',
                    'limit'  => $limit,
                    'params' => [
                        ':search_string_where' => $search_string,
                        ':search_string_order' => $search_string,
                    ],
                ]
            );
        } catch (Throwable $e) {
            // Логируем ошибку
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Полнотекстовый запрос PostgreSQL упал | Таблица: $table_escaped, Сообщение: {$e->getMessage()}"
            );

            // Обновляем кэш: теперь запрос выдает ошибку
            $this->cache->update_cache($key, $db_version, ['query_error' => true]);

            // Переходим на ILIKE
            return $this->fallback_to_ilike_pgsql(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $limit
            );
        }

        // 6. Сохраняем в кэш успешный результат: запрос отработал
        $this->cache->update_cache($key, $db_version, ['query_error' => false]);

        // 7. Получаем результат и отдаем его
        return $this->_res_arr_internal();
    }

    /**
     * Альтернативный поиск через ILIKE с ранжированием similarity() для PostgreSQL.
     *
     * Используется как fallback, когда полнотекстовый поиск недоступен или не дал результатов.
     * Поддерживает несколько столбцов, уникальные плейсхолдеры и ранжирование по близости.
     *
     * @param array            $columns_to_return_escaped Уже экранированные столбцы для SELECT (например, "login",
     *                                                    "real_name").
     * @param array            $columns_to_search_escaped Уже экранированные столбцы для поиска.
     * @param string           $search_string             Строка поиска (уже подготовлена).
     * @param string           $table_escaped             Уже экранированное имя таблицы (например, "users").
     * @param int|string|false $limit                     Ограничение на количество строк в результате.
     *
     * @return array|false Результат выполнения поиска (массив данных или false при ошибке/пустом результате)
     *
     * @throws Exception Если произошла ошибка логирования через log_in_file()
     */
    private function fallback_to_ilike_pgsql(
        array $columns_to_return_escaped,
        array $columns_to_search_escaped,
        string $search_string,
        string $table_escaped,
        int|string|false $limit
    ): array|false {
        // 1. Проверяем пустую строку
        if ($search_string === '') {
            return false;
        }

        // 2. Формируем условия WHERE и ORDER BY
        $where_conditions = [];
        $order_ranks = [];
        $params = [];

        foreach ($columns_to_search_escaped as $i => $column) {
            $placeholder = ":search_string_$i";
            $rank_placeholder = ":search_string_rank_$i";

            $where_conditions[] = "$column ILIKE $placeholder";
            $order_ranks[] = "similarity($column, $rank_placeholder)";

            $params[$placeholder] = "%$search_string%";
            $params[$rank_placeholder] = $search_string;
        }

        $where_sql = '(' . implode(' OR ', $where_conditions) . ')';
        $order_sql = '(' . implode(' + ', $order_ranks) . ') DESC';

        // 3. Выполняем запрос через _select_internal
        try {
            $this->_select_internal(
                $columns_to_return_escaped,
                $table_escaped,
                [
                    'where'  => $where_sql,
                    'order'  => $order_sql,
                    'limit'  => $limit,
                    'params' => $params,
                ]
            );
        } catch (Throwable $e) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Ошибка при выполнении ILIKE-поиска | Таблица: $table_escaped, Сообщение: {$e->getMessage()}"
            );
            return false;
        }

        // 4. Возвращаем результат
        return $this->_res_arr_internal();
    }

    /**
     * Метод для выполнения полнотекстового поиска в SQLite через FTS5.
     *
     * @param array            $columns_to_return Массив столбцов, которые нужно вернуть.
     * @param array            $columns_to_search Массив столбцов, по которым выполняется поиск (только для кэша).
     * @param string           $search_string     Строка поиска (уже подготовлена).
     * @param string           $table             Имя таблицы, в которой выполняется поиск.
     * @param int|string|false $limit             Ограничение на количество строк.
     * @param string           $db_version        Версия БД, полученная из SELECT ver FROM db_version.
     *
     * @return array|false Результат выполнения поиска или false.
     * @throws JsonException при работе с методами для кеша.
     * @throws Exception Если произошла ошибка при выполнении запроса или логировании через log_in_file().
     */
    private function full_text_search_sqlite(
        array $columns_to_return,
        array $columns_to_search,
        string $search_string,
        string $table,
        int|string|false $limit,
        string $db_version
    ): array|false {
        // 1. Экранируем имена столбцов и таблицы
        $columns_to_return_escaped = array_map(static fn ($col) => "\"$col\"", $columns_to_return);
        $columns_to_search_escaped = array_map(static fn ($col) => "\"$col\"", $columns_to_search);
        $table_escaped = "\"$table\"";                  // "users"
        $fts_table_escaped = "\"$table\_fts\"";         // "users_fts"

        // 2. Проверка длины строки
        if (strlen($search_string) < MIN_FULLTEXT_SEARCH_LENGTH) {
            return $this->fallback_to_like_sqlite(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $limit
            );
        }

        // 3. Формируем ключ кэша
        $key = 'fts_sqlite_' . substr(hash('xxh3', $table . ':' . implode(',', $columns_to_search)), 0, 24);

        // 4. Проверяем кэш: был ли query_error
        $cached_index = $this->cache->is_valid($key, $db_version);

        if ($cached_index !== false && isset($cached_index['query_error']) && $cached_index['query_error'] === true) {
            return $this->fallback_to_like_sqlite(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $limit
            );
        }

        // 5. Выполняем полнотекстовый поиск
        try {
            $this->_select_internal(
                $columns_to_return_escaped,
                $fts_table_escaped,
                [
                    'where'  => "$fts_table_escaped MATCH :search_string_where",
                    'order'  => 'rank DESC',
                    'limit'  => $limit,
                    'params' => [
                        ':search_string_where' => $search_string,
                    ],
                ]
            );
        } catch (Throwable $e) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка в FTS5 | Таблица: $table_escaped, Сообщение: {$e->getMessage()}"
            );

            $this->cache->update_cache($key, $db_version, ['query_error' => true]);

            return $this->fallback_to_like_sqlite(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $limit
            );
        }

        // 6. Сохраняем успешное выполнение
        $this->cache->update_cache($key, $db_version, ['query_error' => false]);

        // 7. Возвращаем результат
        return $this->_res_arr_internal();
    }

    /**
     * Альтернативный LIKE-поиск для SQLite.
     *
     * @param array            $columns_to_return_escaped Уже экранированные столбцы.
     * @param array            $columns_to_search_escaped Уже экранированные столбцы для поиска.
     * @param string           $search_string             Подготовленная строка поиска (без %)
     * @param string           $table_escaped             Уже экранированное имя таблицы.
     * @param int|string|false $limit                     Ограничение на вывод.
     *
     * @return array|false Результат или false, если данных нет
     *
     * @throws Exception Если произошла ошибка при выполнении запроса или логировании через log_in_file()
     */
    private function fallback_to_like_sqlite(
        array $columns_to_return_escaped,
        array $columns_to_search_escaped,
        string $search_string,
        string $table_escaped,
        int|string|false $limit
    ): array|false {
        // 1. Проверяем пустую строку
        if ($search_string === '') {
            return false;
        }

        // 2. Формируем условия поиска
        $like_conditions = [];
        $params = [];

        foreach ($columns_to_search_escaped as $i => $column) {
            $placeholder = ":search_string_$i";
            $like_conditions[] = "$column LIKE $placeholder";
            $params[$placeholder] = "%$search_string%";
        }

        $where_sql = '(' . implode(' OR ', $like_conditions) . ')';

        // 3. Выполняем запрос
        try {
            $this->_select_internal(
                $columns_to_return_escaped,
                $table_escaped,
                [
                    'where'  => $where_sql,
                    'order'  => '',
                    'limit'  => $limit,
                    'params' => $params,
                ]
            );
        } catch (Throwable $e) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Ошибка при выполнении LIKE-поиска | Таблица: $table_escaped, Сообщение: {$e->getMessage()}"
            );
            return false;
        }

        return $this->_res_arr_internal();
    }

    /**
     * Снятие экранирования идентификаторов (например, имён таблиц или столбцов).
     *
     * Метод удаляет символы экранирования, специфичные для текущего типа СУБД,
     * чтобы получить "чистые" имена идентификаторов.
     *
     * @param array $identifiers Массив идентификаторов (например, столбцов или таблиц).
     *
     * @return array Массив идентификаторов без символов экранирования.
     */
    private function unescape_identifiers(array $identifiers): array
    {
        // Правила удаления символов экранирования для каждого типа СУБД
        $rules = [
            'mysql'  => ['`'],          // MySQL: обратные кавычки
            'pgsql'  => ['"'],          // PostgreSQL: двойные кавычки
            'sqlite' => ['[', ']', '"'], // SQLite: квадратные скобки и двойные кавычки
        ];

        $unescaped = [];

        foreach ($identifiers as $identifier) {
            // Если идентификатор уже обработан, берем его из кэша
            if (isset($this->unescape_cache[$identifier])) {
                $unescaped[] = $this->unescape_cache[$identifier];
                continue;
            }

            // Получаем символы экранирования для текущего типа СУБД
            $symbols = $rules[$this->current_format] ?? [];

            // Удаляем символы экранирования, если они определены
            $result = !empty($symbols) ? str_replace($symbols, '', $identifier) : $identifier;

            // Сохраняем результат в кэше и добавляем в массив
            $this->unescape_cache[$identifier] = $result;
            $unescaped[] = $result;
        }

        return $unescaped; // Возвращаем массив "чистых" идентификаторов
    }
}
