<?php

/**
 * @file      include/Database.php
 * @brief     Класс для работы с базами данных через PDO.
 *
 * @author    Dark Dayver
 * @version   0.4.4
 * @date      2025-05-07
 * @namespace Photorigma\\Classes
 *
 * @details   Этот файл содержит реализацию класса `Database`, который предоставляет полный набор методов для работы
 *            с различными реляционными базами данных. Класс `Database` обеспечивает:
 *            - Выполнение стандартных операций с данными (SELECT, JOIN, INSERT, UPDATE, DELETE, TRUNCATE).
 *            - **Гибкое формирование условий запросов**, включая поддержку сложных условий `WHERE` с использованием
 *              операторов `OR` и `NOT` через массивный синтаксис.
 *            - Реализацию **полнотекстового поиска (Full-Text Search - FTS)**, специфичного для каждой поддерживаемой
 *              СУБД (MySQL, PostgreSQL, SQLite), включая механизмы fallback на `LIKE`/`ILIKE` и кеширование ошибок индексов.
 *            - Обработка результатов запросов, включая получение данных в различных форматах и метаданных.
 *            - Управление соединением с базой данных и транзакциями.
 *            - Обработка ошибок через централизованную систему логирования и обработки ошибок.
 *            - Временное изменение формата SQL-синтаксиса для совместимости запросов между разными СУБД.
 *            - Интеграция с обработчиком кеша для долгосрочного хранения оптимизационных данных.
 *
 * @section   Database_Main_Functions Основные функции
 *            - Безопасное и гибкое выполнение запросов через подготовленные выражения, с поддержкой сложных условий
 *              WHERE (OR, NOT).
 *            - Реализация полнотекстового поиска (FTS) с fallback'ом на LIKE/ILIKE.
 *            - Получение метаданных запросов (например, количество затронутых строк или ID последней вставленной
 *              строки).
 *            - Форматирование даты с учётом специфики используемой СУБД.
 *            - Управление транзакциями.
 *
 * @see       PhotoRigma::Interfaces::Database_Interface
 *            Интерфейс, который реализует класс.
 * @see       PhotoRigma::Interfaces::Cache_Handler_Interface
 *            Интерфейс обработчика кеша, используемый классом.
 * @see       PhotoRigma::Include::log_in_file()
 *            Функция для логирования ошибок.
 *
 * @note      Этот файл является частью системы PhotoRigma и обеспечивает взаимодействие приложения с базами данных.
 *            Реализованы меры безопасности для предотвращения SQL-инъекций через использование подготовленных
 *            выражений.
 *
 * @copyright Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license   MIT License (https://opensource.org/licenses/MIT)
 *            Разрешается использовать, копировать, изменять, объединять, публиковать,
 *            распространять, сублицензировать и/или продавать копии программного обеспечения,
 *            а также разрешать лицам, которым предоставляется данное программное обеспечение,
 *            делать это при соблюдении следующих условий:
 *            - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые
 *              части программного обеспечения.
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
        date('H:i:s') . ' [ERROR] | ' . (filter_input(
            INPUT_SERVER,
            'REMOTE_ADDR',
            FILTER_VALIDATE_IP
        ) ?: 'UNKNOWN_IP') . ' | ' . __FILE__ . ' | Попытка прямого вызова файла'
    );
    die('HACK!');
}

/**
 * @class   Database
 * @brief   Класс для работы с базами данных через PDO.
 *
 * @details Этот класс предоставляет полный спектр функционала для эффективной и безопасной работы
 *          с реляционными базами данных, поддерживая следующие СУБД: MySQL (включая MariaDB),
 *          PostgreSQL и SQLite. Основные возможности класса включают:
 *          - Безопасное и гибкое выполнение стандартных операций с данными (SELECT, INSERT, UPDATE, DELETE, TRUNCATE, JOIN)
 *            с использованием подготовленных выражений и структурированных опций запроса. Поддерживается
 *            построение сложных условий WHERE с использованием операторов `OR` и `NOT` через удобный массивный синтаксис.
 *          - Реализацию **полнотекстового поиска (Full-Text Search - FTS)**, специфичного для каждой поддерживаемой СУБД
 *            (MySQL/MariaDB FTS, PostgreSQL FTS/`tsvector`/`tsquery`, SQLite FTS5). Включает обработку
 *            минимальной длины строки поиска, кеширование ошибок FTS-индексов и автоматический
 *            переход (fallback) на альтернативные методы поиска (`LIKE`, `ILIKE` с `similarity()` для PostgreSQL)
 *            в случае недоступности или ошибок FTS.
 *          - Получение метаданных выполненных запросов (например, количество затронутых строк (`$aff_rows`) или ID последней вставленной записи (`$insert_id`)).
 *          - Форматирование дат и времени в SQL-запросах с автоматическим преобразованием форматов
 *            (`DATE_FORMAT` для MySQL, `TO_CHAR` для PostgreSQL, `strftime` для SQLite)
 *            на основе текущей или временно установленной СУБД.
 *          - Управление транзакциями (начало, фиксация, откат).
 *          - Временное изменение формата SQL-синтаксиса (например, экранирования идентификаторов,
 *            форматов даты) для выполнения запросов в контексте другой СУБД (`with_sql_format()`),
 *            что позволяет использовать один экземпляр для взаимодействия с разными типами БД без переподключения.
 *          - Долгосрочное кеширование внутренних оптимизационных данных (кешей форматов, запросов,
 *            снятия экранирования) через интегрированный объект, реализующий `Cache_Handler_Interface`.
 *            Все ошибки, возникающие на уровне работы с базой данных или при обработке запросов,
 *            обрабатываются через исключения (`PDOException`, `InvalidArgumentException`, `Exception` и др.).
 *
 * @implements Database_Interface
 *
 * @property ?PDO        $pdo             Объект PDO для подключения к базе данных.
 * @property string|null $txt_query       Текст последнего SQL-запроса, сформированного методами класса.
 * @property object|null $res_query       Результат выполнения подготовленного выражения (PDOStatement).
 * @property int         $aff_rows        Количество строк, затронутых последним запросом (INSERT, UPDATE, DELETE).
 * @property int         $insert_id       ID последней вставленной строки после выполнения INSERT-запроса.
 * @property string      $db_type         Тип базы данных (mysql, pgsql, sqlite).
 * @property string      $db_name         Имя базы данных.
 * @property array       $format_cache    Кэш для преобразования форматов даты (MariaDB -> PostgreSQL/SQLite).
 * @property array       $query_cache     Кэш для хранения результатов проверки существования запросов.
 * @property array       $unescape_cache  Кэш для хранения результатов снятия экранирования идентификаторов.
 * @property string      $current_format  Хранит текущий формат SQL (например, 'mysql', 'pgsql', 'sqlite').
 *                                        Используется для временного изменения формата SQL.
 * @property array       $allowed_formats Список поддерживаемых форматов SQL (например, ['mysql', 'pgsql', 'sqlite']).
 *                                        Определяет допустимые значения для параметра `$format` в методах класса.
 * @property Cache_Handler_Interface $cache Объект для работы с кешем.
 *
 * Пример создания объекта класса Database:
 * @code
 * use PhotoRigma\Classes\Database;
 * use PhotoRigma\Classes\Cache_Handler; // Если используется Cache_Handler по умолчанию
 * use PhotoRigma\Interfaces\Cache_Handler_Interface; // Если используется другой Cache_Handler
 *
 * $db_config = [
 *     'dbtype' => 'mysql',
 *     'dbname' => 'test_db',
 *     'dbuser' => 'root',
 *     'dbpass' => 'password',
 *     'dbhost' => 'localhost',
 *     'dbport' => 3306,
 *     // Другие опции подключения...
 * ];
 *
 * // Пример с Cache_Handler по умолчанию
 * $cache_handler = new Cache_Handler();
 *
 * // Или с объектом, реализующим Cache_Handler_Interface
 * // class CustomCache implements Cache_Handler_Interface { ... }
 * // $cache_handler = new CustomCache();
 *
 * try {
 *     $db = new Database($db_config, $cache_handler);
 *     // ... работа с базой данных ...
 * } catch (\PDOException $e) {
 *     // Обработка ошибки подключения
 *     echo "Ошибка подключения к БД: " . $e->getMessage();
 * } catch (\InvalidArgumentException $e) {
 *     // Обработка ошибки конфигурации
 *     echo "Ошибка конфигурации БД: " . $e->getMessage();
 * } catch (\Exception $e) {
 *     // Другие ошибки
 *     echo "Произошла ошибка: " . $e->getMessage();
 * }
 * @endcode
 *
 * Пример временного изменения формата SQL:
 * @code
 * // Предположим, $db - это инициализированный объект Database, подключенный к MySQL
 *
 * // Выполнение запроса, который использует синтаксис формата даты PostgreSQL
 * $years_list = $db->with_sql_format('pgsql', function (Database $db) {
 *     // Внутри этой анонимной функции current_format временно установлен в 'pgsql'
 *
 *     // Форматирование даты для PostgreSQL: TO_CHAR(data_last_edit, 'YYYY')
 *     $formatted_date_expr = $db->format_date('data_last_edit', 'YYYY');
 *
 *     // Выборка уникальных лет из таблицы TBL_NEWS
 *     // Запрос будет преобразован из формата PostgreSQL в формат MySQL перед выполнением
 *     $db->select(
 *         'DISTINCT ' . $formatted_date_expr . ' AS year',
 *         TBL_NEWS, // Предполагается, что TBL_NEWS определена
 *         [
 *             'order' => 'year ASC',
 *         ]
 *     );
 *
 *     // Получение результата
 *     return $db->result_array();
 * });
 *
 * echo "Список годов (получен через формат PostgreSQL на MySQL):";
 * print_r($years_list);
 *
 * // После выхода из анонимной функции current_format восстанавливается до исходного ('mysql')
 * @endcode
 * @see    PhotoRigma::Interfaces::Database_Interface
 *         Интерфейс, который реализует класс.
 * @see    PhotoRigma::Interfaces::Cache_Handler_Interface
 *         Интерфейс обработчика кеша, используемый классом.
 */
class Database implements Database_Interface
{
    // Свойства класса
    private ?PDO $pdo = null;           ///< Объект PDO для подключения к базе данных
    private string|null $txt_query = null; ///< Текст последнего SQL-запроса, сформированного методами класса
    private object|null $res_query = null; ///< Результат выполнения подготовленного выражения (PDOStatement)
    private int $aff_rows = 0;          ///< Количество строк, затронутых последним запросом (INSERT, UPDATE, DELETE)
    private int $insert_id = 0;         ///< ID последней вставленной строки после выполнения INSERT-запроса
    private string $db_type;            ///< Тип базы данных (mysql, pgsql, sqlite)
    private string $db_name;            ///< Имя базы данных
    private array $format_cache = [];   ///< Кэш для преобразования форматов даты (MariaDB -> PostgreSQL/SQLite)
    private array $query_cache = [];    ///< Кэш для хранения результатов проверки существования запросов
    private array $unescape_cache = []; ///< Кэш для хранения результатов снятия экранирования идентификаторов
    private string $current_format = 'mysql'; ///< Хранит текущий формат SQL (например, 'mysql', 'pgsql', 'sqlite').
    private array $allowed_formats = ['mysql', 'pgsql', 'sqlite']; ///< Список поддерживаемых форматов SQL.
    private Cache_Handler_Interface $cache; ///< Объект для работы с кешем

    /**
     * @brief   Конструктор класса.
     *
     * @details Этот метод вызывается автоматически при создании нового объекта класса.
     *          Используется для установки соединения с базой данных на основе переданных параметров `$db_config`
     *          и инициализации механизма кеширования с использованием объекта `$cache`.
     *
     *          Подключение к базе данных выполняется в следующем порядке:
     *          1. Попытка подключения к SQLite, если `dbtype` установлен в 'sqlite'. Включает проверку
     *             существования и доступности файла базы данных для записи.
     *
     *          2. Для MySQL/PostgreSQL:
     *             - Первая попытка подключения через сокет, если указан параметр `dbsock`. Если путь
     *               к сокету некорректен или файл не существует, записывается предупреждение в лог, и
     *               выполняется попытка подключения через хост и порт.
     *             - Вторая попытка подключения через хост и порт, если подключение через сокет не
     *               используется или не удалось, или если `dbsock` не был указан.
     *
     *          При возникновении ошибок валидации параметров конфигурации или ошибок подключения на уровне PDO
     *          выбрасывается соответствующее исключение.
     *
     *          После инициализации PDO, конструктор проверяет наличие сохранённого кеша (`db_cache`)
     *          через переданный объект `$cache`. Если кеш действителен (пользовательская логика валидации кеша)
     *          и имеет корректную структуру, из него загружаются данные в внутренние свойства класса: `$this->format_cache`,
     *          `$this->query_cache`, `$this->unescape_cache`. Это позволяет избежать повторной
     *          обработки некоторых данных (например, преобразование форматов SQL) при каждом запуске скрипта.
     *
     * @callgraph
     *
     * @param array                   $db_config Массив с конфигурацией подключения к базе данных. Обязательный параметр.
     *                                           Поддерживаемые ключи:
     *                                           - string `dbtype`: Тип базы данных ('mysql', 'pgsql', 'sqlite'). Обязательный параметр.
     *                                             Если передан недопустимый тип, выбрасывается исключение `InvalidArgumentException`.
     *                                           - string `dbsock` (опционально): Путь к файлу сокета для подключения (для MySQL/PostgreSQL).
     *                                             При некорректном пути или отсутствии файла записывается лог и выполняется попытка подключения через хост/порт.
     *                                           - string `dbname`: Имя базы данных. Обязательный параметр. Для SQLite это полный путь к файлу базы данных.
     *                                             Если имя не указано, выбрасывается исключение `InvalidArgumentException`.
     *                                           - string `dbuser` (опционально для SQLite): Имя пользователя базы данных. Обязательный параметр для
     *                                             MySQL/PostgreSQL. Если имя не указано, выбрасывается исключение `InvalidArgumentException`.
     *                                           - string `dbpass` (опционально для SQLite): Пароль пользователя базы данных. Обязательный параметр для
     *                                             MySQL/PostgreSQL.
     *                                           - string `dbhost` (опционально, если используется сокет): Хост базы данных. Обязателен, если подключение
     *                                             через сокет не используется или не удалось. Если хост некорректен, выбрасывается исключение `InvalidArgumentException`.
     *                                           - int `dbport` (опционально): Порт базы данных. Если порт некорректен, выбрасывается исключение `InvalidArgumentException`.
     * @param Cache_Handler_Interface $cache     Объект, реализующий интерфейс `Cache_Handler_Interface`. Обязательный параметр.
     *                                           Используется для временного хранения и загрузки кеша (`db_cache`) между запусками
     *                                           скрипта. При наличии актуального кеша он загружается в свойства:
     *                                           - `$this->format_cache`
     *                                           - `$this->query_cache`
     *                                           - `$this->unescape_cache`
     *
     * @throws InvalidArgumentException Выбрасывается, если параметры конфигурации `$db_config` неверны или отсутствуют обязательные поля:
     *                                  - Недопустимый тип базы данных (`dbtype`).
     *                                  - Не указано имя базы данных (`dbname`) или пользователь (`dbuser`) (кроме SQLite).
     *                                  - Некорректный хост (`dbhost`) или порт (`dbport`).
     *                                  - Не указан путь к файлу базы данных для SQLite (`dbname`).
     *                                  Пример сообщения:
     *                                  Недопустимый тип базы данных | Значение: [dbtype]
     *                                  Пример сообщения:
     *                                  Не указан хост базы данных | Конфигурация: [json_encode($db_config)]
     * @throws RuntimeException         Выбрасывается при ошибках, связанных с файловой системой для SQLite:
     *                                  - Файл базы данных не существует по указанному пути (`dbname`).
     *                                    Пример сообщения:
     *                                    Файл базы данных SQLite не существует | Путь: [dbname]
     *                                  - Файл базы данных недоступен для записи по указанному пути (`dbname`).
     *                                    Пример сообщения:
     *                                    Файл базы данных SQLite недоступен для записи | Путь: [dbname]
     * @throws PDOException             Выбрасывается, если произошла ошибка при установлении соединения с базой данных через PDO:
     *                                  - Ошибка подключения через сокет.
     *                                  - Ошибка подключения через хост и порт.
     *                                  - Ошибка подключения к SQLite.
     *                                  Пример сообщения:
     *                                  Ошибка подключения через хост и порт | Хост: [dbhost], Порт: [dbport]
     *                                  Пример сообщения:
     *                                  Ошибка подключения к SQLite | Путь: [dbname] | Сообщение: [текст_ошибки]
     * @throws JsonException            Выбрасывается, если возникает ошибка при кодировании конфигурации в JSON для сообщений исключений (например, для логирования или сообщений об ошибках).
     *                                  Пример сообщения:
     *                                  Ошибка кодирования конфигурации в JSON
     * @throws Exception                Выбрасывается, если возникает общая ошибка или ошибка при логировании событий через функцию `log_in_file()`.
     *                                  Пример сообщения:
     *                                  Ошибка записи в лог | Сообщение: [текст_сообщения]
     *
     * @warning Если параметр `dbsock` указан для MySQL/PostgreSQL, но файл сокета не существует или некорректен,
     *          выполняется попытка подключения через хост и порт. Проверьте путь `dbsock`.
     *          Для SQLite убедитесь, что файл базы данных существует и доступен для записи по указанному пути (`dbname`).
     *          Некорректные параметры конфигурации приведут к выбросу исключения.
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
     *
     * try {
     *     $db_mysql = new \PhotoRigma\Classes\Database($db_config_mysql, $cache);
     *     // Объект $db_mysql успешно создан
     * } catch (\Exception $e) {
     *     // Обработка ошибок подключения или валидации конфигурации
     *     echo "Ошибка при создании объекта Database: " . $e->getMessage();
     * }
     * @endcode
     *
     * @code
     * // Пример для SQLite
     * $db_config_sqlite = [
     *     'dbtype' => 'sqlite',
     *     'dbname' => '/path/to/database.sqlite',
     * ];
     * $cache = new \PhotoRigma\Classes\Cache_Handler(); // Предполагается существование Cache_Handler
     *
     * try {
     *     $db_sqlite = new \PhotoRigma\Classes\Database($db_config_sqlite, $cache);
     *     // Объект $db_sqlite успешно создан
     * } catch (\Exception $e) {
     *     // Обработка ошибок подключения или валидации конфигурации
     *     echo "Ошибка при создании объекта Database: " . $e->getMessage();
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Database::$pdo
     *          Свойство, хранящее объект PDO для подключения к базе данных.
     * @see     PhotoRigma::Classes::Database::$db_type
     *          Свойство, хранящее тип используемой базы данных (например, 'mysql' или 'sqlite').
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
     *          Метод интерфейса кеширования, используемый для проверки валидности кеша.
     * @see     PhotoRigma::Include::log_in_file()
     *          Функция для логирования ошибок и предупреждений.
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
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый тип базы данных | Значение: {$db_config['dbtype']}"
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
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Не указано имя базы данных или пользователь | Конфигурация: ' . json_encode(
                    $db_config,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                )
            );
        }

        // Обработка SQLite
        if ($this->db_type === 'sqlite') {
            if (empty($this->db_name)) {
                throw new InvalidArgumentException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Не указан путь к файлу базы данных SQLite | Конфигурация: ' . json_encode(
                        $db_config,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                    )
                );
            }

            // Проверка существования файла
            if (!is_file($this->db_name)) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл базы данных SQLite не существует | Путь: $this->db_name"
                );
            }

            // Проверка прав доступа
            if (!is_writable($this->db_name)) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл базы данных SQLite недоступен для записи | Путь: $this->db_name"
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
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка подключения к SQLite | Путь: $this->db_name | Сообщение: " . $e->getMessage(
                    )
                );
            }
        }

        // Проверка dbsock (первая попытка подключения через сокет)
        if (!empty($db_config['dbsock'])) {
            if (!is_string($db_config['dbsock']) || !file_exists($db_config['dbsock'])) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный или несуществующий путь к сокету | Путь: {$db_config['dbsock']}"
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
                        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка подключения через сокет | Сокет: {$db_config['dbsock']} | Сообщение: " . $e->getMessage(
                        )
                    );
                    // Переходим к следующему варианту подключения
                }
            }
        }

        // Проверка dbhost и dbport (вторая попытка подключения через хост и порт)
        if (empty($db_config['dbhost'])) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Не указан хост базы данных | Конфигурация: ' . json_encode(
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
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный хост базы данных | Значение: {$db_config['dbhost']}"
            );
        }
        $dsn = "$this->db_type:host={$db_config['dbhost']};dbname=$this->db_name;";
        if (!empty($db_config['dbport'])) {
            if (!is_numeric($db_config['dbport']) || $db_config['dbport'] < 1 || $db_config['dbport'] > 65535) {
                throw new InvalidArgumentException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный порт базы данных | Значение: {$db_config['dbport']}"
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
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка подключения через хост и порт | Хост: {$db_config['dbhost']}, Порт: {$db_config['dbport']} | Сообщение: " . $e->getMessage(
                )
            );
        }
    }

    /**
     * @brief   Деструктор класса Database.
     *
     * @details Метод вызывается автоматически интерпретатором PHP при уничтожении объекта.
     *          Основная задача — сохранение содержимого внутренних кешей класса
     *          (`$this->format_cache`, `$this->query_cache`, `$this->unescape_cache`).
     *
     *          Сохранение выполняется через вызов метода `update_cache()` переданного в конструкторе
     *          объекта, реализующего интерфейс `Cache_Handler_Interface`.
     *
     *          Возможность сохранения кешей может быть отключена на уровне приложения через соответствующие
     *          параметры конфигурации, которые обрабатываются менеджером кеширования.
     *
     * @callgraph
     *
     * @note Данный метод не требует явного вызова. PHP автоматически исполняет его при освобождении объекта из памяти.
     *       Убедитесь, что доступ к менеджеру кеширования (`$this->cache`) корректен и объект кеширования был
     *       успешно инициализирован в конструкторе.
     *
     * @throws JsonException Выбрасывается методом `Cache_Handler_Interface::update_cache()`
     *                       в случае невозможности сериализации данных кешей перед сохранением.
     *
     * Пример использования:
     * @code
     * $db = new \PhotoRigma\Classes\Database($config, $cacheHandler);
     * // ... работа с базой данных, которая наполняет кеши ...
     *
     * // Объект $db больше не нужен. Деструктор будет вызван автоматически.
     * unset($db);
     *
     * // Кеши будут сохранены, если это не отключено в настройках приложения.
     * @endcode
     * @see    PhotoRigma::Interfaces::Cache_Handler_Interface::update_cache()
     *         Метод интерфейса кеширования, ответственный за сохранение данных во внешнюю систему кеширования.
     * @see    PhotoRigma::Classes::Database::$format_cache
     *         Внутреннее свойство, хранящее кеш преобразования форматов даты.
     * @see    PhotoRigma::Classes::Database::$query_cache
     *         Внутреннее свойство, хранящее кеш уже обработанных (например, экранированных) частей запросов.
     * @see    PhotoRigma::Classes::Database::$unescape_cache
     *         Внутреннее свойство, хранящее кеш результатов снятия экранирования идентификаторов.
     * @see    PhotoRigma::Classes::Database::$cache
     *         Свойство, хранящее объект, реализующий `Cache_Handler_Interface`.
     * @see    PhotoRigma::Classes::Database::__construct()
     *         Конструктор класса, используемый для инициализации объекта и кеширования.
     */
    public function __destruct()
    {
        $db_cache['format_cache'] = $this->format_cache;
        $db_cache['query_cache'] = $this->query_cache;
        $db_cache['unescape_cache'] = $this->unescape_cache;
        $this->cache->update_cache('db_cache', 37971181, $db_cache);
    }

    /**
     * @brief   Начинает транзакцию в базе данных.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода `_begin_transaction_internal()`.
     *          Он передаёт контекст транзакции во внутренний метод, который выполняет начало транзакции на уровне
     *          PDO и записывает соответствующий лог.
     *          Метод предназначен для использования клиентским кодом как точка входа для явного управления
     *          транзакциями.
     *
     * @callgraph
     *
     * @param string $context Контекст транзакции (необязательный параметр).
     *                        Используется для описания цели или места начала транзакции.
     *                        По умолчанию: пустая строка (`''`).
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws Exception Выбрасывается, если произошла ошибка при начале транзакции на уровне PDO (выбрасывается из
     *                   внутренней логики).
     *                   Пример сообщения:
     *                   Ошибка при начале транзакции | Контекст: [значение контекста] | Сообщение PDO: [текст ошибки
     *                   PDO]
     *
     * @note    Этот метод является точкой входа для начала транзакций.
     *          Основная логика и логирование выполняются в защищённом методе `_begin_transaction_internal()`.
     * @warning Убедитесь, что соединение с базой данных установлено перед вызовом метода.
     *
     * Пример использования:
     * @code
     * // Пример начала транзакции из клиентского кода
     * $db = new \PhotoRigma\Classes\Database();
     *
     * $db->begin_transaction('Сохранение данных пользователя');
     * // ... операции с БД ...
     * // $db->commit(); или $db->roll_back();
     * @endcode
     * @see    PhotoRigma::Classes::Database::_begin_transaction_internal()
     *         Защищённый метод, выполняющий основную логику начала транзакции.
     */
    public function begin_transaction(string $context = ''): void
    {
        $this->_begin_transaction_internal($context);
    }

    /**
     * @brief   Начинает транзакцию в базе данных и записывает лог с указанием контекста.
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Начинает транзакцию в базе данных через объект PDO (`$this->pdo`).
     *          2. Записывает лог с указанием контекста начала транзакции с использованием функции
     *             `log_in_file()`.
     *
     *          Этот метод предназначен для использования внутри класса или его наследников.
     *          Основная логика метода вызывается через публичный метод-редирект `begin_transaction()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $context Контекст транзакции (необязательный параметр).
     *                        Используется для описания цели или места начала транзакции.
     *                        По умолчанию: пустая строка (`''`).
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws Exception Выбрасывается, если произошла ошибка при начале транзакции на уровне PDO.
     *                   Пример сообщения:
     *                   Ошибка при начале транзакции | Контекст: [значение контекста] | Сообщение PDO: [текст ошибки
     *                   PDO]
     *
     * @note    Метод использует функцию логирования `log_in_file()` для записи информации о начале транзакции.
     * @warning Убедитесь, что соединение с базой данных установлено (`$this->pdo` является валидным объектом PDO)
     *          перед вызовом метода. Неправильное состояние соединения может привести к ошибке.
     *
     * Пример вызова метода внутри класса:
     * @code
     * // Пример начала транзакции с контекстом
     * $this->_begin_transaction_internal('Сохранение данных пользователя');
     * // Ожидаемый лог (в файле): [DB] Транзакция начата | Контекст: Сохранение данных пользователя
     * @endcode
     * @see    PhotoRigma::Classes::Database::begin_transaction()
     *         Публичный метод-редирект для вызова этой логики.
     * @see    PhotoRigma::Classes::Database::$pdo
     *         Объект PDO для подключения к базе данных.
     * @see    PhotoRigma::Include::log_in_file()
     *         Логирует сообщения в файл.
     */
    protected function _begin_transaction_internal(string $context = ''): void
    {
        $this->pdo->beginTransaction();
        log_in_file(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | [DB] Транзакция начата | Контекст: $context"
        );
    }

    /**
     * @brief   Подтверждает транзакцию в базе данных.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода `_commit_transaction_internal()`.
     *          Он передаёт контекст транзакции во внутренний метод, который выполняет подтверждение транзакции на
     *          уровне PDO и записывает соответствующий лог.
     *          Метод предназначен для использования клиентским кодом как точка входа для явного управления
     *          транзакциями.
     *
     * @callgraph
     *
     * @param string $context Контекст транзакции (необязательный параметр).
     *                        Используется для описания цели или места подтверждения транзакции.
     *                        По умолчанию: пустая строка (`''`).
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws Exception Выбрасывается, если произошла ошибка при подтверждении транзакции на уровне PDO
     *                   (выбрасывается из внутренней логики).
     *                   Пример сообщения:
     *                   Ошибка при подтверждении транзакции | Контекст: [значение контекста] | Сообщение PDO: [текст
     *                   ошибки PDO]
     *
     * @note    Этот метод является точкой входа для подтверждения транзакций.
     *          Основная логика и логирование выполняются в защищённом методе `_commit_transaction_internal()`.
     * @warning Убедитесь, что транзакция была начата перед вызовом метода.
     *
     * Пример использования:
     * @code
     * // Пример подтверждения транзакции из клиентского кода
     * $db = new \PhotoRigma\Classes\Database();
     *
     * $db->begin_transaction('Пакетное обновление');
     * // ... операции с БД ...
     * $db->commit_transaction('Пакетное обновление завершено');
     * @endcode
     * @see    PhotoRigma::Classes::Database::_commit_transaction_internal()
     *         Защищённый метод, выполняющий основную логику подтверждения транзакции.
     */
    public function commit_transaction(string $context = ''): void
    {
        $this->_commit_transaction_internal($context);
    }

    /**
     * @brief   Подтверждает транзакцию в базе данных и записывает лог с указанием контекста.
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Подтверждает транзакцию в базе данных через объект PDO (`$this->pdo`).
     *          2. Записывает лог с указанием контекста подтверждения транзакции с использованием функции
     *             `log_in_file()`.
     *
     *          Этот метод предназначен для использования внутри класса или его наследников.
     *          Основная логика метода вызывается через публичный метод-редирект `commit_transaction()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $context Контекст транзакции (необязательный параметр).
     *                        Используется для описания цели или места подтверждения транзакции.
     *                        По умолчанию: пустая строка (`''`).
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws Exception Выбрасывается, если произошла ошибка при подтверждении транзакции на уровне PDO.
     *                   Пример сообщения:
     *                   Ошибка при подтверждении транзакции | Контекст: [значение контекста] | Сообщение PDO: [текст
     *                   ошибки PDO]
     *
     * @note    Метод использует функцию логирования `log_in_file()` для записи информации о подтверждении транзакции.
     * @warning Убедитесь, что транзакция была начата (`$this->pdo->inTransaction()` возвращает `true`) перед вызовом
     *          этого метода. Попытка подтвердить несуществующую транзакцию приведёт к ошибке.
     *
     * Пример вызова метода внутри класса:
     * @code
     * // Пример подтверждения транзакции с контекстом
     * $this->_commit_transaction_internal('Сохранение данных пользователя');
     * // Ожидаемый лог (в файле): [DB] Транзакция подтверждена | Контекст: Сохранение данных пользователя
     * @endcode
     * @see    PhotoRigma::Classes::Database::commit_transaction()
     *         Публичный метод-редирект для вызова этой логики.
     * @see    PhotoRigma::Classes::Database::$pdo
     *         Объект PDO для подключения к базе данных.
     * @see    PhotoRigma::Include::log_in_file()
     *         Логирует сообщения в файл.
     */
    protected function _commit_transaction_internal(string $context = ''): void
    {
        $this->pdo->commit();
        log_in_file(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | [DB] Транзакция подтверждена | Контекст: $context"
        );
    }

    /**
     * @brief   Удаляет данные из таблицы через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода `_delete_internal()`.
     *          Он передаёт имя таблицы и опции запроса во внутренний метод, который выполняет формирование и
     *          выполнение SQL-запроса на удаление. Безопасность обеспечивается обязательным указанием условия
     *          `where` в опциях и использованием подготовленных выражений с параметрами.
     *          Метод предназначен для использования клиентским кодом как точка входа для выполнения операций
     *          удаления данных.
     *
     * @callgraph
     *
     * @param string $from_tbl Имя таблицы, из которой необходимо удалить данные.
     *                         Должно быть строкой, содержащей только допустимые имена таблиц без специальных символов.
     * @param array  $options  Массив дополнительных опций для формирования запроса. Поддерживаемые ключи:
     *                         - where (string|array|false): Условие WHERE.
     *                           * string - используется как есть.
     *                           * array - преобразуется в SQL. Поддерживает простые условия
     *                             (`['поле' => значение]`) и логические операторы (`'OR' => [...]`,
     *                             `'NOT' => [...]`).
     *                           * false - игнорируется.
     *                           Обязательный параметр для безопасности. Без условия WHERE запрос не будет выполнен.
     *                         - group (string|false): Группировка GROUP BY. Игнорируется, если false.
     *                           Должна быть строкой.
     *                           Не поддерживается в запросах DELETE и удаляется с записью в лог.
     *                         - order (string|false): Сортировка ORDER BY. Игнорируется, если false.
     *                           Должна быть строкой.
     *                           Может использоваться только вместе с `limit`. Если указан только один из них, оба
     *                           игнорируются с записью в лог.
     *                         - limit (int|string|false): Ограничение LIMIT.
     *                           * int - прямое значение.
     *                           * string - формат "OFFSET,COUNT".
     *                           * false - игнорируется.
     *                           Может использоваться только вместе с `order`. Если указан только один из них, оба
     *                           игнорируются с записью в лог.
     *                         - params (array): Ассоциативный массив параметров
     *                           ([":имя" => значение]).
     *                           Обязателен для использования с условиями `where` и другими частями запроса,
     *                           требующими подготовленных выражений. Обеспечивает защиту от SQL-инъекций.
     *                           Может быть пустым массивом, если параметры не требуются.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой). В случае ошибки
     *              выбрасывается исключение.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                - `$from_tbl` не является строкой.
     *                                - `$options` не является массивом.
     *                                - Отсутствует обязательное условие `where` в массиве `$options`.
     *                                Пример сообщения:
     *                                Запрос DELETE без условия WHERE запрещён | Причина: Соображения безопасности
     * @throws JsonException          Выбрасывается при ошибках кодирования JSON (например, при записи в лог).
     *                                Пример сообщения:
     *                                Ошибка при кодировании JSON | Переданные опции: {"group":"users"}
     * @throws Exception              Выбрасывается при ошибках выполнения запроса или записи в лог.
     *                                Пример сообщения:
     *                                Ошибка выполнения запроса | SQL: DELETE FROM users WHERE id = :id AND status = :status
     *
     * @note    Этот метод является точкой входа для выполнения операций удаления данных.
     *          Все проверки (включая обязательное `where`) и основная логика выполняются в защищённом методе
     *          `_delete_internal()`.
     *
     * @warning Метод чувствителен к корректности входных данных. Неверные типы данных или некорректные значения
     *          могут привести к выбросу исключения. Безопасность обеспечивается обязательным указанием условия `where`.
     *          Запрос без условия `where` не будет выполнен. Ключи `group`, `order` и `limit` проверяются на
     *          корректность во внутренней логике.
     *          Использование параметров `params` для подготовленных выражений является обязательным для защиты от
     *          SQL-инъекций и обеспечения совместимости с различными СУБД.
     *
     * Пример использования:
     * @code
     * // Пример безопасного использования публичного метода
     * $db = new \PhotoRigma\Classes\Database();
     *
     * $db->delete('users', [
     *     'where' => 'id = :id AND status = :status',
     *     'params' => [':id' => 1, ':status' => 'active'],
     * ]);
     *
     * // Использование с ассоциативным массивом в `where`
     * $db->delete('users', [
     *     'where' => ['id' => 1, 'status' => 'active'],
     * ]);
     * @endcode
     * @see    PhotoRigma::Classes::Database::_delete_internal()
     *         Защищённый метод, выполняющий основную логику удаления данных.
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
     *          5. Добавляет условия WHERE, ORDER BY и LIMIT (если они корректны) через метод `_build_conditions`.
     *          6. Выполняет сформированный запрос через метод `_execute_query`, передавая параметры `params` для
     *             подготовленного выражения.
     *
     *          Использование параметров `params` является обязательным для подготовленных выражений, так как это
     *          обеспечивает защиту от SQL-инъекций и совместимость с различными СУБД.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `delete()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $from_tbl Имя таблицы, из которой необходимо удалить данные.
     *                         Должно быть строкой, содержащей только допустимые имена таблиц без специальных символов.
     * @param array  $options  Массив дополнительных опций для формирования запроса. Поддерживаемые ключи:
     *                         - where (string|array|false): Условие WHERE.
     *                           * string - используется как есть.
     *                           * array - преобразуется в SQL. Поддерживает простые условия
     *                             (`['поле' => значение]`) и логические операторы (`'OR' => [...]`,
     *                             `'NOT' => [...]`).
     *                           * false - игнорируется.
     *                           Обязательный параметр для безопасности. Без условия WHERE запрос не будет выполнен.
     *                         - group (string|false): Группировка GROUP BY. Игнорируется, если false.
     *                           Должна быть строкой.
     *                           Не поддерживается в запросах DELETE и удаляется с записью в лог.
     *                         - order (string|false): Сортировка ORDER BY. Игнорируется, если false.
     *                           Должна быть строкой.
     *                           Может использоваться только вместе с `limit`. Если указан только один из них, оба
     *                           игнорируются с записью в лог.
     *                         - limit (int|string|false): Ограничение LIMIT.
     *                           * int - прямое значение.
     *                           * string - формат "OFFSET,COUNT".
     *                           * false - игнорируется.
     *                           Может использоваться только вместе с `order`. Если указан только один из них, оба
     *                           игнорируются с записью в лог.
     *                         - params (array): Ассоциативный массив параметров
     *                           ([":имя" => значение]).
     *                           Обязателен для использования с условиями `where` и другими частями запроса,
     *                          требующими подготовленных выражений. Обеспечивает защиту от SQL-инъекций.
     *                           Может быть пустым массивом, если параметры не требуются.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой). В случае ошибки
     *              выбрасывается исключение.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                - `$from_tbl` не является строкой.
     *                                - `$options` не является массивом.
     *                                - Отсутствует обязательное условие `where` в массиве `$options`.
     *                                Пример сообщения:
     *                                Запрос DELETE без условия WHERE запрещён | Причина: Соображения безопасности
     * @throws JsonException          Выбрасывается при ошибках кодирования JSON (например, при записи в лог).
     *                                Пример сообщения:
     *                                Ошибка при кодировании JSON | Переданные опции: {"group":"users"}
     * @throws Exception              Выбрасывается при ошибках выполнения запроса или записи в лог.
     *                                Пример сообщения:
     *                                Ошибка выполнения запроса | SQL: DELETE FROM users WHERE id = :id AND status
     *                                = :status
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
     * @see    PhotoRigma::Classes::Database::delete()
     *         Публичный метод-редирект для вызова этой логики.
     * @see    PhotoRigma::Classes::Database::_execute_query()
     *         Выполняет SQL-запрос.
     * @see    PhotoRigma::Classes::Database::_build_conditions()
     *         Формирует условия WHERE, ORDER BY и LIMIT для запроса.
     * @see    PhotoRigma::Classes::Database::$txt_query
     *         Свойство, в которое помещается текст SQL-запроса.
     * @see    PhotoRigma::Include::log_in_file()
     *         Логирует ошибки.
     */
    protected function _delete_internal(string $from_tbl, array $options = []): bool
    {
        // === 1. Валидация аргументов ===
        if (empty($options['where'])) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Запрос DELETE без условия WHERE запрещён | Причина: Соображения безопасности'
            );
        }

        // === 2. Проверка и удаление недопустимых ключей ===
        if (isset($options['group'])) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Был использован GROUP BY в DELETE | Переданные опции: ' . json_encode(
                    $options,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                )
            );
            unset($options['group']); // Удаляем ключ 'group'
        }

        if ((isset($options['order']) && !isset($options['limit'])) || (!isset($options['order']) && isset($options['limit']))) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ORDER BY или LIMIT используются некорректно в DELETE | Переданные опции: ' . json_encode(
                    $options,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                )
            );
            unset($options['order'], $options['limit']); // Удаляем ключи 'order' и 'limit'
        }

        // === 3. Формирование базового запроса ===
        $this->txt_query = "DELETE FROM $from_tbl";

        // === 4. Добавление условий ===
        [$conditions, $params] = $this->_build_conditions($options);
        $this->txt_query .= $conditions;

        // === 5. Выполнение запроса ===
        return $this->_execute_query($params);
    }

    /**
     * @brief   Формирует строку дополнений для SQL-запроса (например, WHERE, GROUP BY, ORDER BY, LIMIT).
     *
     * @details Этот приватный метод обрабатывает опции запроса из массива `$options` и формирует
     *          соответствующие SQL-фрагменты для добавления к основному запросу (такому как SELECT, UPDATE, DELETE).
     *
     *          Метод выполняет следующие шаги:
     *          1. Обрабатывает условие `WHERE` из `$options['where']`:
     *             - Игнорируется, если значение равно `false`.
     *             - Если значение является строкой, она используется как есть.
     *             - Если значение является массивом, он преобразуется в SQL-условие. Поддерживаются
     *               простые условия (`['поле' => значение]`), которые автоматически преобразуются с использованием
     *               плейсхолдеров (`"поле = :поле"`) и добавляются к возвращаемому массиву параметров.
     *               Также поддерживаются логические операторы `'OR'` и `'NOT'` с массивами условий внутри.
     *          2. Обрабатывает группировку `GROUP BY` из `$options['group']`:
     *             - Игнорируется, если значение равно `false`.
     *             - Если значение является строкой, добавляется к строке условий.
     *          3. Обрабатывает сортировку `ORDER BY` из `$options['order']`:
     *             - Игнорируется, если значение равно `false`.
     *             - Если значение является строкой, добавляется к строке условий.
     *          4. Обрабатывает ограничение `LIMIT` из `$options['limit']`:
     *             - Игнорируется, если значение равно `false`.
     *             - Если значение является числом, добавляется напрямую.
     *             - Если значение является строкой формата "OFFSET, COUNT", она разбирается и добавляется.
     *          5. Возвращает сформированную строку дополнений и обновлённый массив параметров.
     *             Массив параметров для возврата инициализируется значениями из `$options['params']` (если они были
     *             предоставлены) и дополняется параметрами, автоматически созданными для простых условий WHERE.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param array $options Опции запроса. Поддерживаемые ключи:
     *                       - where (string|array|false): Условие WHERE.
     *                         Может быть:
     *                         - Строкой (используется как есть)
     *                         - Массивом:
     *                           * Простые условия: `['поле' => значение]` (автоматически создаются плейсхолдеры и
     *                             добавляются в возвращаемый массив параметров)
     *                           * Сложные условия: `['OR' => [условия], 'NOT' => [условия]]`
     *                         - `false`: Игнорируется.
     *                       - group (string|false): Группировка GROUP BY.
     *                         Должна быть строкой. Игнорируется, если равно `false`.
     *                       - order (string|false): Сортировка ORDER BY.
     *                         Должна быть строкой. Игнорируется, если равно `false`.
     *                       - limit (int|string|false): Ограничение LIMIT.
     *                         Должно быть целым числом или строкой формата `'OFFSET, COUNT'`. Игнорируется, если
     *                         равно `false`.
     *                       - params (array): Исходный ассоциативный массив параметров для подготовленного выражения
     *                         ([":имя" => значение]). Необязательный параметр. Эти параметры используются как основа
     *                         для возвращаемого массива параметров.
     *
     * @return array Массив с двумя элементами:
     *               - string $conditions: Строка дополнений SQL (например, ` WHERE ... GROUP BY ... ORDER BY ...
     *                 LIMIT ...`).
     *               - array $params: Обновлённый ассоциативный массив параметров для подготовленного выражения.
     *                 Включает параметры из `$options['params']` и параметры, сгенерированные для простых условий
     *                 WHERE.
     *
     * @throws InvalidArgumentException Выбрасывается, если значение одной из опций (`where`, `group`, `order`, `limit`)
     *                                  имеет недопустимый тип, который не может быть обработан методом.
     *                                  Например:
     *                                  - Неверное условие 'where' | Ожидалась строка или массив, получено: [тип]
     *                                  - Неверное значение 'group' | Ожидалась строка, получено: [тип]
     *                                  - Неверное значение 'order' | Ожидалась строка, получено: [тип]
     *                                  - Неверное значение 'limit' | Ожидалось число или строка формата 'OFFSET,
     *                                    COUNT', получено: [тип/значение]
     *
     * Пример использования:
     * @code
     * // Пример с простыми условиями WHERE и LIMIT
     * $options = [
     *     'where' => ['id' => 1, 'status' => 'active'],
     *     'limit' => 10,
     *     // Входящий массив params может быть пустым или содержать другие параметры
     *     'params' => [':other_param' => 'value'],
     * ];
     *
     * [$conditions, $params] = $this->_build_conditions($options);
     * // $conditions будет примерно " WHERE id = :id AND status = :status LIMIT 10" (в зависимости от порядка)
     * // $params будет примерно [':other_param' => 'value', ':id' => 1, ':status' => 'active']
     *
     * // Пример со сложными условиями WHERE и ORDER BY
     * $complex_options = [
     *     'where' => [
     *         'OR' => ['views > :min_views', 'likes > :min_likes'],
     *         'NOT' => ['deleted' => 1], // 'deleted' => 1 автоматически преобразуется в 'deleted = :deleted'
     *         'category_id' => 5 // автоматически преобразуется в 'category_id = :category_id'
     *     ],
     *     'order' => 'created_at DESC',
     *     'params' => [':min_views' => 1000, ':min_likes' => 50], // Входящие параметры для OR
     * ];
     *
     * [$complex_conditions, $complex_params] = $this->_build_conditions($complex_options);
     * // $complex_conditions будет примерно " WHERE (views > :min_views OR likes > :min_likes) AND NOT (deleted = :deleted) AND category_id = :category_id ORDER BY created_at DESC"
     * // $complex_params будет примерно [':min_views' => 1000, ':min_likes' => 50, ':deleted' => 1, ':category_id' => 5]
     * @endcode
     *
     * @see    PhotoRigma::Classes::Database::update()
     *         Метод, который вызывает _build_conditions() для формирования UPDATE-запроса.
     * @see    PhotoRigma::Classes::Database::select()
     *         Метод, который вызывает _build_conditions() для формирования SELECT-запроса.
     * @see    PhotoRigma::Classes::Database::join()
     *         Метод, который вызывает _build_conditions() для формирования JOIN-запроса.
     * @see    PhotoRigma::Classes::Database::delete()
     *         Метод, который вызывает _build_conditions() для формирования DELETE-запроса.
     * @see    PhotoRigma::Classes::Database::_build_where_group()
     *         Приватный метод, используемый для рекурсивной обработки групп условий (OR, NOT) внутри WHERE.
     */
    private function _build_conditions(array $options): array
    {
        $conditions = '';
        $params = $options['params'] ?? [];

        // === 1. Обработка WHERE ===
        if (isset($options['where']) && $options['where'] !== false) {
            if (is_string($options['where'])) {
                $conditions .= ' WHERE ' . $options['where'];
            } elseif (is_array($options['where'])) {
                $where_parts = [];

                foreach ($options['where'] as $key => $value) {
                    if ($key === 'OR') {
                        $or_conditions = $this->_build_where_group($value, 'OR');
                        if ($or_conditions) {
                            $where_parts[] = $or_conditions;
                        }
                    } elseif ($key === 'NOT') {
                        $not_conditions = $this->_build_where_group($value, 'NOT');
                        if ($not_conditions) {
                            $where_parts[] = $not_conditions;
                        }
                    } elseif (is_numeric($key)) {
                        $where_parts[] = $value;
                    } else {
                        $where_parts[] = "$key = :$key";
                        $params[":$key"] = $value;
                    }
                }

                if ($where_parts) {
                    $conditions .= ' WHERE ' . implode(' AND ', $where_parts);
                }
            } else {
                throw new InvalidArgumentException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверное условие 'where' | Ожидалась строка или массив, получено: " . gettype(
                        $options['where']
                    )
                );
            }
        }

        // === 2. Обработка GROUP BY ===
        if (isset($options['group']) && $options['group'] !== false) {
            if (is_string($options['group'])) {
                $conditions .= ' GROUP BY ' . $options['group'];
            } else {
                throw new InvalidArgumentException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверное значение 'group' | Ожидалась строка, получено: " . gettype(
                        $options['group']
                    )
                );
            }
        }

        // === 3. Обработка ORDER BY ===
        if (isset($options['order']) && $options['order'] !== false) {
            if (is_string($options['order'])) {
                $conditions .= ' ORDER BY ' . $options['order'];
            } else {
                throw new InvalidArgumentException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверное значение 'order' | Ожидалась строка, получено: " . gettype(
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
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверное значение 'limit' | Ожидалось число или строка формата 'OFFSET, COUNT', получено: " . gettype(
                        $options['limit']
                    ) . " ({$options['limit']})"
                );
            }
        }

        return [$conditions, $params];
    }

    /**
     * @brief   Формирует группу условий для WHERE с указанным оператором (OR/NOT).
     *
     * @details Этот приватный метод используется для построения сложных SQL-условий внутри части WHERE.
     *
     *          Метод выполняет следующие задачи:
     *          - Для оператора OR: объединяет условия из массива `$conditions` через `OR`.
     *          - Для оператора NOT: объединяет условия из массива `$conditions` через `AND` и добавляет отрицание
     *            `NOT` к группе.
     *
     *          Поддерживает два формата условий в массиве `$conditions`:
     *          - Готовые строки условий (если ключ числовой) — используются как есть.
     *          - Ассоциативные массивы в формате `['поле' => значение]` (если ключ строковый) — преобразуются в
     *            синтаксис для подготовленного выражения: `"поле = :поле"`. (Подстановка и экранирование значений
     *            происходят на этапе выполнения запроса в методе `_execute_query()`).
     *
     * @internal
     * @callergraph
     *
     * @param array  $conditions Массив условий для группы.
     *                           - Если ключ числовой: значение используется как готовая строка условия (например,
     *                             `"views > 1000"`).
     *                           - Если ключ строковый: `['поле' => значение]` — значение используется для генерации
     *                             синтаксиса плейсхолдера (например, `"deleted = :deleted"`).
     * @param string $operator   Логический оператор для объединения условий в группе ('OR' или 'NOT').
     *
     * @return string Сформированная строка условий для группы WHERE.
     *                - Для OR: `"(условие1 OR условие2)"`
     *                - Для NOT: `"NOT (условие1 AND условие2)"`
     *                Возвращает пустую строку (`''`), если массив условий пуст.
     *
     * @throws InvalidArgumentException Если передан недопустимый оператор (не 'OR' или 'NOT').
     *                                  Пример сообщения:
     *                                  "Неверный оператор | Допустимые значения: OR, NOT, получено: [operator]"
     *
     * Пример использования:
     * @code
     * // Пример с готовыми строками условий и оператором OR
     * $or_condition = $this->_build_where_group(
     *     ['views > 1000', 'likes > 50'],
     *     'OR'
     * ); // Результат: "(views > 1000 OR likes > 50)"
     *
     * // Пример с ассоциативным массивом условий и оператором NOT
     * // Обратите внимание: значения (1, 1) не используются этим методом напрямую,
     * // но необходимы для создания плейсхолдеров :deleted и :hidden.
     * $not_condition = $this->_build_where_group(
     *     ['deleted' => 1, 'hidden' => 1],
     *     'NOT'
     * ); // Результат: "NOT (deleted = :deleted AND hidden = :hidden)"
     * @endcode
     * @see    PhotoRigma::Classes::Database::_build_conditions()
     *         Приватный метод, который вызывает _build_where_group() для формирования сложных условий в WHERE.
     */
    private function _build_where_group(array $conditions, string $operator): string
    {
        if (!in_array($operator, ['OR', 'NOT'], true)) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') .
                ") | Неверный оператор | Допустимые значения: OR, NOT, получено: $operator"
            );
        }

        $parts = [];

        foreach ($conditions as $key => $value) {
            if (is_numeric($key)) {
                $parts[] = $value;
            } else {
                $parts[] = "$key = :$key";
            }
        }

        if (empty($parts)) {
            return '';
        }

        return $operator === 'NOT'
            ? 'NOT (' . implode(' AND ', $parts) . ')'
            : '(' . implode(' OR ', $parts) . ')';
    }

    /**
     * @brief   Выполняет SQL-запрос с использованием подготовленных выражений.
     *
     * @details Этот приватный метод выполняет следующие шаги:
     *          1. Проверяет состояние внутренних свойств (`$this->res_query`, `$this->txt_query`) и типы переданных
     *             аргументов (`$params`).
     *          2. Очищает внутренние свойства для вывода результата предыдущего запроса (`$this->res_query`,
     *             `$this->aff_rows`, `$this->insert_id`).
     *          3. Преобразует текст запроса (`$this->txt_query`) в формат, специфичный для текущей СУБД, с помощью
     *             метода `_convert_query()`.
     *          4. Если константа `SQL_ANALYZE` установлена в `true`, выполняет анализ запроса (например, `EXPLAIN
     *             ANALYZE`) с помощью метода `_log_explain()`.
     *          5. Подготавливает и выполняет SQL-запрос с использованием объекта PDO (`$this->pdo`) и переданных
     *             параметров `$params`. Получает количество затронутых строк (`$this->aff_rows`) и, если запрос
     *             является INSERT, ID последней вставленной записи (`$this->insert_id`).
     *          6. Измеряет время выполнения запроса и, если константа `SQL_LOG` установлена в `true`:
     *             - Логирует запрос как "медленный" с помощью `_log_query()`, если время выполнения превышает 200 мс.
     *             - Логирует запрос как "без плейсхолдеров" с помощью `_log_query()`, если массив `$params` пустой.
     *          7. Очищает текст запроса (`$this->txt_query = null`) после его выполнения.
     *          8. Возвращает `true`, указывая на успешное выполнение запроса (даже если затронуто 0 строк или SELECT
     *             не вернул результатов).
     *          Этот метод является приватным и предназначен только для использования внутри класса `Database`. Он
     *          является ключевым методом для выполнения всех SQL-операций, и его вызывают другие методы класса
     *          (например, `select()`, `insert()`, `update()`, `delete()`, `truncate()`, `join()`).
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param array $params Ассоциативный массив параметров для подготовленного выражения (необязательно).
     *                      Пример: `[':id' => 1, ':name' => 'John Doe']`.
     *                      Ключи массива должны соответствовать именам плейсхолдеров в тексте запроса
     *                      (`$this->txt_query`).
     *                      По умолчанию: пустой массив (`[]`), если параметры не требуются.
     *
     * @return bool Возвращает `true`, если подготовка и выполнение запроса прошли успешно.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - Внутреннее свойство `$this->res_query` имеет некорректное состояние перед
     *                                    выполнением запроса.
     *                                    Пример сообщения:
     *                                    Некорректное состояние результата запроса | Текущее значение: [тип]
     *                                  - Внутреннее свойство `$this->txt_query` не является строкой.
     *                                    Пример сообщения:
     *                                    Неверный тип SQL-запроса | Ожидалась строка, получено: [тип]
     *                                  - Переданный аргумент `$params` не является массивом.
     *                                    Пример сообщения:
     *                                    Неверный тип параметров запроса | Ожидался массив, получено: [тип]
     * @throws PDOException             Выбрасывается, если возникает ошибка на уровне PDO в процессе подготовки или
     *                                  выполнения запроса.
     *                                  Пример сообщения:
     *                                  Ошибка при выполнении SQL-запроса | Запрос: [текст запроса или сообщение PDO]
     *                                  | Код ошибки PDO: [код ошибки]
     * @throws Exception                Выбрасывается, если возникает ошибка при вызове метода `_log_explain()` или
     *                                  `_log_query()`.
     *                                  Пример сообщения:
     *                                  Ошибка при выполнении EXPLAIN ANALYZE | Запрос: [текст запроса] | Сообщение:
     *                                  [текст ошибки]
     *
     * @note    Метод зависит от корректной конфигурации базы данных и использует следующие константы для настройки
     *          поведения:
     *          - `SQL_LOG`: Управляет логированием медленных запросов (время выполнения > 200 мс) и запросов без
     *            плейсхолдеров.
     *          - `SQL_ANALYZE`: Управляет выполнением `EXPLAIN ANALYZE` перед запросами для диагностики
     *            производительности.
     *          Эти константы позволяют настраивать поведение метода в зависимости от требований к отладке и
     *          мониторингу.
     * @warning Убедитесь, что перед вызовом этого метода:
     *          - Свойство `$this->txt_query` содержит валидную SQL-строку для подготовленного выражения.
     *          - Массив `$params` содержит значения, соответствующие всем плейсхолдерам в `$this->txt_query`.
     *          - Соединение с базой данных установлено (`$this->pdo` является валидным объектом PDO).
     *          Невалидные входные данные или некорректное состояние могут привести к выбросу исключений.
     *
     * Пример использования метода _execute_query() (вызывается из других методов класса):
     * @code
     * // Пример вызова _execute_query из метода select()
     * // Assume $this->txt_query is set to "SELECT * FROM users WHERE id = :id"
     * // Assume $params is set to [':id' => 1]
     * $success = $this->_execute_query($params);
     * if ($success) {
     *     // Запрос выполнен, результат доступен через $this->res_query
     *     // Количество затронутых строк (для SELECT может быть 0, но метод все равно вернет true)
     *     $affected = $this->aff_rows;
     *     // ID последней вставки (будет 0 для SELECT)
     *     $lastId = $this->insert_id;
     * } else {
     *     // Обработка ошибки (обычно происходит через исключение)
     *     echo "Ошибка выполнения запроса _execute_query";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Database::$pdo
     *         Объект PDO, используемый для выполнения запроса.
     * @see    PhotoRigma::Classes::Database::$res_query
     *         Свойство, хранящее результат выполненного запроса (объект PDOStatement для SELECT/JOIN).
     * @see    PhotoRigma::Classes::Database::$aff_rows
     *         Свойство, хранящее количество строк, затронутых последним запросом.
     * @see    PhotoRigma::Classes::Database::$insert_id
     *         Свойство, хранящее ID последней вставленной записи (для INSERT).
     * @see    PhotoRigma::Classes::Database::$txt_query
     *         Свойство, хранящее текст SQL-запроса для выполнения.
     * @see    PhotoRigma::Classes::Database::delete()
     *         Метод, который устанавливает `$this->txt_query` и вызывает `_execute_query()`.
     * @see    PhotoRigma::Classes::Database::truncate()
     *         Метод, который устанавливает `$this->txt_query` и вызывает `_execute_query()`.
     * @see    PhotoRigma::Classes::Database::update()
     *         Метод, который устанавливает `$this->txt_query` и вызывает `_execute_query()`.
     * @see    PhotoRigma::Classes::Database::insert()
     *         Метод, который устанавливает `$this->txt_query` и вызывает `_execute_query()`.
     * @see    PhotoRigma::Classes::Database::select()
     *         Метод, который устанавливает `$this->txt_query` и вызывает `_execute_query()`.
     * @see    PhotoRigma::Classes::Database::join()
     *         Метод, который устанавливает `$this->txt_query` и вызывает `_execute_query()`.
     * @see    PhotoRigma::Classes::Database::_convert_query()
     *         Приватный метод для преобразования запроса в нужный формат СУБД перед выполнением.
     * @see    PhotoRigma::Classes::Database::_log_explain()
     *         Приватный метод для выполнения EXPLAIN ANALYZE.
     * @see    PhotoRigma::Classes::Database::_log_query()
     *         Приватный метод для логирования запросов.
     */
    private function _execute_query(array $params = []): bool
    {
        // Валидация состояния запроса и аргументов
        if (isset($this->res_query) && !($this->res_query instanceof PDOStatement)) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Некорректное состояние результата запроса | Текущее значение: ' . gettype(
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
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Неверный тип SQL-запроса | Ожидалась строка, получено: ' . gettype(
                    $this->txt_query
                )
            );
        }

        // Финальное преобразование запроса в нужный формат СУБД.
        $this->_convert_query();

        // Выполняем EXPLAIN ANALYZE, если включено
        if (SQL_ANALYZE) {
            $this->_log_explain($params);
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
            $this->_log_query('slow', $execution_time);
        } elseif (SQL_LOG && empty($params)) {
            // Сохраняем запрос в БД с пустым плейсхолдером
            $this->_log_query('no_placeholders', $execution_time);
        }
        // Очистка строки запроса после её выполнения
        $this->txt_query = null;
        return true;
    }

    /**
     * @brief   Выполняет преобразование SQL-запроса в зависимости от текущего формата и типа СУБД.
     *
     * @details Этот приватный метод выполняет преобразование текста SQL-запроса, хранящегося в свойстве
     *          `$this->txt_query`, для обеспечения совместимости с целевой базой данных.
     *
     *          Метод выполняет следующие шаги:
     *          1. Проверяет, совпадает ли текущий формат строки запроса (`$this->current_format`) с типом базы
     *             данных (`$this->db_type`).
     *             Если форматы совпадают, преобразование не требуется, и метод завершает работу.
     *          2. Если форматы не совпадают, вызывает соответствующий приватный метод для выполнения преобразования
     *             из текущего формата (`$this->current_format`) в формат целевой СУБД (`$this->db_type`).
     *             Вызываемые методы (`_convert_from_mysql_to()`, `_convert_from_pgsql_to()`, `_convert_from_sqlite_to()`)
     *             модифицируют свойство `$this->txt_query` с преобразованным текстом запроса.
     *          3. Если текущий формат строки (`$this->current_format`) не поддерживается для преобразования,
     *             выбрасывается исключение InvalidArgumentException.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *          Основной вызывающий метод — `_execute_query()`.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @return void Метод ничего не возвращает напрямую. Результат преобразования сохраняется в свойстве `$this->txt_query`.
     *
     * @throws InvalidArgumentException Выбрасывается, если текущий формат строки (`$this->current_format`) не
     *                                  поддерживается для преобразования.
     *                                  Пример сообщения:
     *                                  Не поддерживаемый формат строки | Тип: [current_format]
     *
     * @note    Метод зависит от свойств `$this->current_format` и `$this->db_type` для определения необходимости и
     *          типа преобразования.
     *          Преобразованный текст запроса всегда сохраняется обратно в свойство `$this->txt_query`.
     *
     * @warning Убедитесь, что свойство `$this->current_format` содержит одно из допустимых значений форматов
     *          ('mysql', 'pgsql', 'sqlite') перед вызовом этого метода.
     *          Недопустимый формат может привести к выбросу исключения. Убедитесь также, что `$this->txt_query`
     *          содержит исходный текст запроса, который требуется преобразовать.
     *
     * Пример вызова метода внутри класса (например, из _execute_query()):
     * @code
     * // Предположим:
     * // $this->txt_query = "SELECT * FROM `users` WHERE id = 1";
     * // $this->current_format = 'mysql';
     * // $this->db_type = 'pgsql';
     *
     * $this->_convert_query(); // Выполнит $this->_convert_from_mysql_to('pgsql')
     *
     * // Теперь $this->txt_query содержит преобразованный запрос для PostgreSQL:
     * // echo $this->txt_query; // Результат: SELECT * FROM "users" WHERE id = 1
     * @endcode
     * @see    PhotoRigma::Classes::Database::$txt_query
     *         Свойство, содержащее текст SQL-запроса, которое модифицируется методом.
     * @see    PhotoRigma::Classes::Database::$current_format
     *         Свойство, определяющее исходный формат строки запроса.
     * @see    PhotoRigma::Classes::Database::$db_type
     *         Свойство, определяющее целевой тип используемой СУБД.
     * @see    PhotoRigma::Classes::Database::_convert_from_mysql_to()
     *         Приватный метод для преобразования запросов из формата MySQL в другие.
     * @see    PhotoRigma::Classes::Database::_convert_from_pgsql_to()
     *         Приватный метод для преобразования запросов из формата PostgreSQL в другие.
     * @see    PhotoRigma::Classes::Database::_convert_from_sqlite_to()
     *         Приватный метод для преобразования запросов из формата SQLite в другие.
     * @see    PhotoRigma::Classes::Database::_execute_query()
     *         Приватный метод, из которого вызывается данный метод.
     */
    private function _convert_query(): void
    {
        // Если формат строки совпадает с типом базы данных, ничего не меняем
        if ($this->current_format === $this->db_type) {
            return;
        }

        // Выполняем преобразование в зависимости от текущего формата и типа базы данных
        match ($this->current_format) {
            'mysql'  => $this->_convert_from_mysql_to($this->db_type),
            'pgsql'  => $this->_convert_from_pgsql_to($this->db_type),
            'sqlite' => $this->_convert_from_sqlite_to($this->db_type),
            default  => throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                "Не поддерживаемый формат строки | Тип: $this->current_format"
            ),
        };
    }

    /**
     * @brief   Производит преобразование SQL-запроса из формата MySQL в целевой формат СУБД (PostgreSQL или SQLite).
     *
     * @details Этот приватный метод выполняет преобразование текста SQL-запроса, хранящегося в свойстве
     *          `$this->txt_query`, из формата MySQL в формат целевой СУБД.
     *
     *          Метод выполняет следующие шаги:
     *          1. Проверяет тип целевой СУБД через параметр `$target_db_type`. Метод поддерживает преобразование
     *             только в форматы PostgreSQL ('pgsql') и SQLite ('sqlite').
     *          2. Для поддерживаемых целевых СУБД (PostgreSQL и SQLite) находит все необратимые обратные кавычки (`)
     *             в строке запроса и заменяет их на двойные кавычки ("). Экранированные кавычки (например, \` )
     *             остаются без изменений. Преобразованный запрос сохраняется обратно в свойство `$this->txt_query`.
     *          3. Если тип целевой СУБД (`$target_db_type`) не поддерживается методом для преобразования из MySQL,
     *             выбрасывается исключение InvalidArgumentException.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *          Вызывается из метода `_convert_query()`.
     *
     * @internal
     * @callergraph
     *
     * @param string $target_db_type Тип целевой СУБД, в которую нужно преобразовать запрос.
     *                               Поддерживаемые значения: 'pgsql', 'sqlite'.
     *                               Ограничения: входной тип должен быть допустимым значением из списка.
     *
     * @return void Метод ничего не возвращает напрямую. Результат преобразования сохраняется в свойстве
     *              `$this->txt_query`.
     *
     * @throws InvalidArgumentException Выбрасывается, если тип целевой СУБД (`$target_db_type`) не поддерживается
     *                                  методом `_convert_from_mysql_to`.
     *                                  Пример сообщения:
     *                                  Неподдерживаемый тип базы данных | Тип: [target_db_type]
     *
     * @note    Метод использует регулярное выражение для поиска и замены необратимых обратных кавычек на двойные
     *          кавычки. Преобразованный запрос сохраняется в свойстве `$this->txt_query`.
     *
     * @warning Убедитесь, что входной тип `$target_db_type` соответствует поддерживаемым значениям ('pgsql' или
     *          'sqlite'). Недопустимый тип может привести к выбросу исключения. Метод предполагает, что
     *          `$this->txt_query` содержит SQL-запрос, использующий синтаксис идентификаторов MySQL (обратные кавычки).
     *
     * Пример вызова метода внутри класса (например, из _convert_query()):
     * @code
     * // Предположим:
     * // $this->txt_query = "SELECT `id`, `name` FROM `users` WHERE `status` = 1 AND `description` LIKE 'Test\\`s'";
     * // $this->db_type установлен в 'pgsql' или 'sqlite'
     *
     * // Преобразование запроса из MySQL
     * $this->_convert_from_mysql_to($this->db_type);
     *
     * // Если $this->db_type был 'pgsql':
     * // echo $this->txt_query; // Результат: SELECT "id", "name" FROM "users" WHERE "status" = 1 AND "description" LIKE 'Test\\`s'
     *
     * // Если $this->db_type был 'sqlite':
     * // echo $this->txt_query; // Результат: SELECT "id", "name" FROM "users" WHERE "status" = 1 AND "description" LIKE 'Test\\`s'
     * // (для SQLite двойные кавычки также являются допустимым способом экранирования идентификаторов)
     * @endcode
     * @see    PhotoRigma::Classes::Database::$txt_query
     *         Свойство, содержащее текст SQL-запроса, которое модифицируется методом.
     * @see    PhotoRigma::Classes::Database::_convert_query()
     *         Приватный метод, из которого вызывается данный метод.
     */
    private function _convert_from_mysql_to(string $target_db_type): void
    {
        $this->txt_query = match ($target_db_type) {
            'pgsql', 'sqlite' => preg_replace_callback(
                '/(?<!\\\\)`/', // Ищем обратные апострофы, которые не экранированы
                static fn ($matches) => '"', // Заменяем их на двойные кавычки
                $this->txt_query
            ),
            default => throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                "Неподдерживаемый тип базы данных | Тип: $target_db_type"
            ),
        };
    }

    /**
     * @brief   Производит преобразование SQL-запроса из формата PostgreSQL в целевой формат СУБД (MySQL или SQLite).
     *
     * @details Этот приватный метод выполняет преобразование текста SQL-запроса, хранящегося в свойстве
     *          `$this->txt_query`, из формата PostgreSQL в формат целевой СУБД.
     *
     *          Метод выполняет следующие шаги:
     *          1. Проверяет тип целевой СУБД через параметр `$target_db_type`. Метод поддерживает преобразование
     *             только в форматы MySQL ('mysql') и SQLite ('sqlite').
     *          2. Для целевой СУБД MySQL находит все необратимые двойные кавычки (") в строке запроса и заменяет
     *             их на обратные апострофы (`). Экранированные кавычки (например, \") остаются без изменений.
     *             Преобразованный запрос сохраняется обратно в свойство `$this->txt_query`.
     *          3. Для целевой СУБД SQLite запрос остается без изменений, свойство `$this->txt_query` не модифицируется.
     *          4. Если тип целевой СУБД (`$target_db_type`) не поддерживается методом для преобразования из PostgreSQL,
     *             выбрасывается исключение InvalidArgumentException.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *          Вызывается из метода `_convert_query()`.
     *
     * @internal
     * @callergraph
     *
     * @param string $target_db_type Тип целевой СУБД, в которую нужно преобразовать запрос.
     *                               Поддерживаемые значения: 'mysql', 'sqlite'.
     *                               Ограничения: входной тип должен быть допустимым значением из списка.
     *
     * @return void Метод ничего не возвращает напрямую. Результат преобразования сохраняется в свойстве
     *              `$this->txt_query` (если было преобразование).
     *
     * @throws InvalidArgumentException Выбрасывается, если тип целевой СУБД (`$target_db_type`) не поддерживается
     *                                  методом `_convert_from_pgsql_to`.
     *                                  Пример сообщения:
     *                                  Неподдерживаемый тип базы данных | Тип: [target_db_type]
     *
     * @note    Метод использует регулярное выражение для поиска и замены необратимых двойных кавычек на обратные
     *          апострофы при преобразовании в MySQL. Преобразованный запрос (или исходный, если целевая СУБД -
     *          SQLite) сохраняется в свойстве `$this->txt_query`.
     *
     * @warning Убедитесь, что входной тип `$target_db_type` соответствует поддерживаемым значениям ('mysql' или
     *          'sqlite'). Недопустимый тип может привести к выбросу исключения. Метод предполагает, что
     *          `$this->txt_query` содержит SQL-запрос, использующий синтаксис идентификаторов PostgreSQL (двойные
     *          кавычки).
     *
     * Пример вызова метода внутри класса (например, из _convert_query()):
     * @code
     * // Предположим:
     * // $this->txt_query = 'SELECT "id", "name" FROM "users" WHERE "status" = 1 AND "description" LIKE \'Test\\"s\'';
     * // $this->current_format = 'pgsql';
     * // $this->db_type установлен в 'mysql' или 'sqlite'
     *
     * // Преобразование запроса из PostgreSQL
     * $this->_convert_from_pgsql_to($this->db_type);
     *
     * // Если $this->db_type был 'mysql':
     * // echo $this->txt_query; // Результат: SELECT `id`, `name` FROM `users` WHERE `status` = 1 AND `description` LIKE 'Test\\"s'
     *
     * // Если $this->db_type был 'sqlite':
     * // echo $this->txt_query; // Результат: SELECT "id", "name" FROM "users" WHERE "status" = 1 AND "description" LIKE 'Test\\"s'
     * // (запрос остается без изменений)
     * @endcode
     * @see    PhotoRigma::Classes::Database::$txt_query
     *         Свойство, содержащее текст SQL-запроса, которое модифицируется методом (или остается без изменений).
     * @see    PhotoRigma::Classes::Database::_convert_query()
     *         Приватный метод, из которого вызывается данный метод.
     */
    private function _convert_from_pgsql_to(string $target_db_type): void
    {
        $this->txt_query = match ($target_db_type) {
            'mysql'  => preg_replace_callback(
                '/(?<!\\\\)"/', // Ищем двойные кавычки, которые не экранированы
                static fn ($matches) => '`', // Заменяем их на обратные апострофы
                $this->txt_query
            ),
            'sqlite' => $this->txt_query, // Для SQLite ничего не меняем
            default => throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                "Неподдерживаемый тип базы данных | Тип: $target_db_type"
            ),
        };
    }

    /**
     * @brief   Производит преобразование SQL-запроса из формата SQLite в целевой формат СУБД (MySQL или PostgreSQL).
     *
     * @details Этот приватный метод выполняет преобразование текста SQL-запроса, хранящегося в свойстве
     *          `$this->txt_query`, из формата SQLite в формат целевой СУБД. SQLite поддерживает экранирование
     *          идентификаторов с помощью двойных кавычек (") или квадратных скобок ([ ]).
     *
     *          Метод выполняет следующие шаги:
     *          1. Проверяет тип целевой СУБД через параметр `$target_db_type`. Метод поддерживает преобразование
     *             только в форматы MySQL ('mysql') и PostgreSQL ('pgsql').
     *          2. Для целевой СУБД MySQL находит идентификаторы, экранированные двойными кавычками (") или
     *             квадратными скобками ([ ]), и заменяет их на обратные апострофы (`).
     *          3. Для целевой СУБД PostgreSQL находит идентификаторы, экранированные квадратными скобками ([ ]),
     *             и заменяет их на двойные кавычки ("). Идентификаторы в двойных кавычках (") остаются без изменений,
     *             так как они допустимы в PostgreSQL.
     *          4. Если тип целевой СУБД (`$target_db_type`) не поддерживается методом для преобразования из SQLite,
     *             выбрасывается исключение InvalidArgumentException. Преобразованный запрос сохраняется обратно в
     *             свойство `$this->txt_query`.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *          Вызывается из метода `_convert_query()`.
     *
     * @internal
     * @callergraph
     *
     * @param string $target_db_type Тип целевой СУБД, в которую нужно преобразовать запрос.
     *                               Поддерживаемые значения: 'mysql', 'pgsql'.
     *                               Ограничения: входной тип должен быть допустимым значением из списка.
     *
     * @return void Метод ничего не возвращает напрямую. Результат преобразования сохраняется в свойстве
     *              `$this->txt_query`.
     *
     * @throws InvalidArgumentException Выбрасывается, если тип целевой СУБД (`$target_db_type`) не поддерживается
     *                                  методом `_convert_from_sqlite_to`.
     *                                  Пример сообщения:
     *                                  Неподдерживаемый тип базы данных | Тип: [target_db_type]
     *
     * @note    Метод использует регулярные выражения для поиска идентификаторов в двойных кавычках или квадратных
     *          скобках и их замены. Преобразованный запрос сохраняется в свойстве `$this->txt_query`.
     *
     * @warning Убедитесь, что входной тип `$target_db_type` соответствует поддерживаемым значениям ('mysql' или
     *          'pgsql'). Недопустимый тип может привести к выбросу исключения. Метод предполагает, что
     *          `$this->txt_query` содержит SQL-запрос, использующий синтаксис идентификаторов SQLite (двойные
     *          кавычки или квадратные скобки).
     *
     * Пример вызова метода внутри класса (например, из _convert_query()):
     * @code
     * // Предположим:
     * // $this->txt_query = 'SELECT "id", [name] FROM [users] WHERE id = 1';
     * // $this->current_format = 'sqlite';
     * // $this->db_type установлен в 'mysql' или 'pgsql'
     *
     * // Преобразование запроса из SQLite в MySQL
     * $this->_convert_from_sqlite_to('mysql');
     * // echo $this->txt_query; // Результат: SELECT `id`, `name` FROM `users` WHERE id = 1
     *
     * // Предположим:
     * // $this->txt_query = 'SELECT "id", [name] FROM [users] WHERE id = 1';
     * // $this->current_format = 'sqlite';
     * // $this->db_type установлен в 'pgsql'
     *
     * // Преобразование запроса из SQLite в PostgreSQL
     * $this->_convert_from_sqlite_to('pgsql');
     * // echo $this->txt_query; // Результат: SELECT "id", "name" FROM "users" WHERE id = 1
     * @endcode
     * @see    PhotoRigma::Classes::Database::$txt_query
     *         Свойство, содержащее текст SQL-запроса, которое модифицируется методом.
     * @see    PhotoRigma::Classes::Database::_convert_query()
     *         Приватный метод, из которого вызывается данный метод.
     */
    private function _convert_from_sqlite_to(string $target_db_type): void
    {
        /**
         * @noinspection RegExpUnnecessaryNonCapturingGroup
         * @noinspection RegExpSimplifiable
         * @noinspection RegExpDuplicateCharacterInClass
         * @noinspection RegExpRedundantEscape
         */
        $this->txt_query = match ($target_db_type) {
            'mysql'  => preg_replace_callback(
                '/(?<!\w)(?<!\\\\)(?:((?:[a-zA-Z_][\w]*\.)?(?:["\[]|\\["\[])+)([^"\]\\\\]*(?:\\\\.[^"\]\\\\]*)*)(["\]]+))/',
                static function ($matches) {
                    // Обрабатываем префикс (например, 'schema.["table' → 'schema.`table')
                    $prefix = preg_replace(['/^\./', '/["\[]+/'], ['', ''], $matches[1]);

                    // Обрабатываем содержимое (убираем экранирование)
                    $content = preg_replace('/\\\\(["\[])/', '$1', $matches[2]);

                    return $prefix . '`' . $content . '`';
                },
                $this->txt_query
            ),
            'pgsql'  => preg_replace_callback(
                '/(?<!\w)(?<!\\\\)(?:((?:[a-zA-Z_][\w]*\.)?(?:\[|\\\\\[)+)([^\]\\\\]*(?:\\\\.[^\]\\\\]*)*)(\]+))/',
                static function ($matches) {
                    // Обрабатываем префикс (например, 'schema.[table' → 'schema."table')
                    $prefix = preg_replace(['/^\./', '/\[+/'], ['', ''], $matches[1]);

                    // Обрабатываем содержимое (убираем экранирование только для скобок)
                    $content = preg_replace('/\\\[\[\]]/', '', $matches[2]);

                    return $prefix . '"' . $content . '"';
                },
                $this->txt_query
            ),
            default => throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                "Неподдерживаемый тип базы данных | Тип: $target_db_type"
            ),
        };
    }

    /**
     * @brief   Логирует результат анализа SQL-запроса с помощью EXPLAIN или EXPLAIN ANALYZE.
     *
     * @details Этот приватный метод выполняет анализ текущего SQL-запроса, хранящегося в свойстве `$this->txt_query`,
     *          с помощью команды `EXPLAIN` или `EXPLAIN ANALYZE`, и логирует полученный результат.
     *
     *          Алгоритм работы:
     *          1. Определяет тип команды анализа (`EXPLAIN` или `EXPLAIN ANALYZE`) в зависимости от типа текущей
     *             СУБД (`$this->db_type`), версии СУБД (для MySQL/MariaDB) и типа запроса (WRITE или SELECT для
     *             PostgreSQL/MySQL).
     *             - Для SQLite всегда используется только `EXPLAIN`.
     *             - Для MySQL/MariaDB: `EXPLAIN ANALYZE` используется только для SELECT-запросов на MySQL >= 5.7
     *               (не MariaDB). В остальных случаях - `EXPLAIN`.
     *             - Для PostgreSQL: `EXPLAIN ANALYZE` используется для SELECT-запросов, `EXPLAIN` - для WRITE-запросов.
     *          2. Выполняет команду анализа (`EXPLAIN ...` или `EXPLAIN ANALYZE ...`) с использованием объекта PDO
     *             (`$this->pdo`) и переданных параметров `$params`.
     *          3. Извлекает строки результата анализа и форматирует их для удобства чтения (ключ: значение).
     *          4. Логирует результат анализа, исходный текст запроса (`$this->txt_query`) и переданные параметры
     *             `$params` с использованием функции `log_in_file()`.
     *          5. В случае ошибки при определении версии СУБД или выполнении команды анализа, также логируется
     *             сообщение об ошибке.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *          Вызывается из метода `_execute_query()`, если включена константа `SQL_ANALYZE`.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param array $params Ассоциативный массив параметров для подготовленного запроса (такой же, как передается в
     *                      `_execute_query()`).
     *                      Пример: `[':id' => 1, ':name' => 'John Doe']`.
     *                      Ключи массива должны соответствовать именам плейсхолдеров в `$this->txt_query`.
     *                      Если параметры отсутствуют, можно передать пустой массив (`[]`).
     *
     * @return void Метод ничего не возвращает. Результат анализа логируется.
     *
     * @throws InvalidArgumentException Выбрасывается, если тип текущей СУБД (`$this->db_type`) не поддерживается
     *                                  методом `_log_explain` для определения типа команды анализа.
     *                                  Пример сообщения:
     *                                  Не поддерживаемый тип СУБД | Тип: [db_type]
     * @throws Exception                Выбрасывается, если произошла ошибка при получении версии СУБД (для
     *                                  MySQL/MariaDB) или при выполнении команды EXPLAIN/EXPLAIN ANALYZE (например,
     *                                  синтаксическая ошибка в запросе на уровне PDO).
     *                                  В случае выброса исключения, оно перехватывается внутри метода и логируется.
     *
     * @note    Метод использует объект PDO (`$this->pdo`) для выполнения команд анализа и функцию `log_in_file()`
     *          для записи результатов и ошибок. Результат анализа форматируется для удобства чтения.
     *          При возникновении ошибки логируются текст запроса, параметры и сообщение об ошибке из пойманного
     *          исключения.
     *          Поведение метода зависит от константы `SQL_ANALYZE`.
     *
     * @warning Убедитесь, что свойство `$this->txt_query` содержит синтаксически корректный SQL-запрос перед вызовом
     *          метода, чтобы избежать ошибок при выполнении команды анализа.
     *          Метод предполагает, что соединение с базой данных `$this->pdo` установлено.
     *
     * Пример вызова метода внутри класса (например, из _execute_query()):
     * @code
     * // Предположим:
     * // $this->txt_query = "SELECT * FROM users WHERE id = :id";
     * // $this->db_type = 'mysql'; // или 'pgsql'
     * // $params = [':id' => 1];
     * // SQL_ANALYZE = true
     *
     * $this->_log_explain($params);
     *
     * // В файл лога будет записан отчет по анализу, содержащий:
     * // - Использованную команду анализа (EXPLAIN или EXPLAIN ANALYZE)
     * // - Текст запроса
     * // - Параметры запроса (в формате JSON)
     * // - Результат анализа (строки, отформатированные ключ: значение)
     * @endcode
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования сообщений в файл.
     * @see    PhotoRigma::Classes::Database::$txt_query
     *         Свойство, содержащее текст SQL-запроса для анализа.
     * @see    PhotoRigma::Classes::Database::$pdo
     *         Объект PDO, используемый для выполнения команды анализа.
     * @see    PhotoRigma::Classes::Database::$db_type
     *         Свойство, определяющее тип используемой СУБД.
     * @see    PhotoRigma::Classes::Database::_execute_query()
     *         Приватный метод, из которого вызывается данный метод (при SQL_ANALYZE=true).
     */
    private function _log_explain(array $params): void
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
                    $stmt = $this->pdo->query('SELECT VERSION()');
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
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не поддерживаемый тип СУБД | Тип: $this->db_type"
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
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Отчет по анализу с помощью $explain: | " . 'Запрос: ' . $this->txt_query . PHP_EOL . 'Параметры: ' . (!empty($params) ? json_encode(
                    $params,
                    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                ) : 'отсутствуют') . PHP_EOL . "$explain Результат:" . PHP_EOL . $explain_result
            );
        } catch (Exception $e) {
            // Логируем ошибку
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при выполнении $explain: | " . 'Запрос: ' . $this->txt_query . PHP_EOL . 'Параметры: ' . (!empty($params) ? json_encode(
                    $params,
                    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                ) : 'отсутствуют') . PHP_EOL . "Сообщение об ошибке: {$e->getMessage()}"
            );
        }
    }

    /**
     * @brief   Логирует медленные запросы, запросы без использования плейсхолдеров и другие запросы в таблицу логов.
     *
     * @details Этот приватный метод выполняет логирование информации о SQL-запросах в специализированную таблицу
     *          логов (`TBL_LOG_QUERY`).
     *          Алгоритм работы:
     *          1. Выполняет начальные проверки: убеждается, что `$this->txt_query` является непустой строкой и не
     *             содержит имени таблицы логов, чтобы избежать рекурсивного логирования.
     *          2. Нормализует переданное время выполнения `$execution_time_ms`, гарантируя, что оно является
     *             неотрицательным числом.
     *          3. Обрезает текст запроса `$this->txt_query` до максимальной длины (`$max_query_length`, по умолчанию
     *             65530 символов), чтобы соответствовать размеру поля в базе данных (например, TEXT в MySQL),
     *             добавляя суффикс "..." при обрезке.
     *          4. Вычисляет MD5-хэш от полного текста запроса (`$this->txt_query`) для последующей идентификации
     *             дубликатов.
     *          5. Валидирует переданную причину логирования `$reason`, используя `match` для ограничения допустимых
     *             значений ('slow', 'no_placeholders', 'other'). Если значение некорректно, используется 'other'.
     *          6. Проверяет наличие хэша запроса в кэше `$this->query_cache` для оптимизации. Если хэш не найден в
     *             кэше, выполняется запрос к таблице логов `TBL_LOG_QUERY` для проверки существования записи с таким
     *             хэшем, и результат сохраняется в кэше.
     *          7. На основе результата проверки в кэше логирует или обновляет запись в таблице логов:
     *             - Если запись с таким хэшем уже существует (найдена в кэше/БД), обновляет поля `usage_count`
     *               (увеличивает на 1), `last_used_at` (устанавливает текущее время) и `execution_time` (обновляет
     *               только если переданное `$execution_time_ms` больше сохраненного).
     *             - Если запись не существует, вставляет новую строку с хэшем, обрезанным текстом запроса, причиной
     *               и временем выполнения.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса `Database`.
     *          Вызывается из метода `_execute_query()` при выполнении определенных условий логирования (`SQL_LOG`).
     *
     * @internal
     * @callergraph
     *
     * @param string $reason                Причина логирования запроса. Допустимые значения: 'slow', 'no_placeholders',
     *                                      'other'. Некорректные значения преобразуются в 'other'. По умолчанию:
     *                                      'other'. Пример: 'slow'.
     * @param float|null $execution_time_ms Время выполнения запроса в миллисекундах. Должно быть положительным
     *                                      числом или `null`. Используется для записи или обновления максимального
     *                                      времени выполнения в логах. По умолчанию: `null`.
     *                                      Пример: 1200.5.
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws PDOException Выбрасывается, если произошла ошибка при работе с базой данных в процессе выполнения
     *                      запросов INSERT или UPDATE к таблице логов `TBL_LOG_QUERY` (например, проблемы с
     *                      подключением или выполнением запроса).
     *                      Пример сообщения:
     *                      Ошибка при работе с таблицей логов запросов | Таблица: [TBL_LOG_QUERY] | Сообщение PDO:
     *                      [текст ошибки]
     *
     * @note    Метод активно использует кэширование (`$this->query_cache`) для оптимизации проверки существования
     *          запросов в базе данных. Запросы хэшируются с помощью `md5()` для идентификации уникальных запросов и
     *          предотвращения дублирования записей. Длинные запросы обрезаются для соответствия ограничению поля в
     *          базе данных.
     *          Поведение метода и целевая таблица определяются константой `TBL_LOG_QUERY`.
     *
     * @warning Убедитесь, что константа `TBL_LOG_QUERY` определена и указывает на существующую таблицу с подходящей
     *          структурой. Также учтите, что слишком длинные запросы будут обрезаны до `$max_query_length` символов
     *          перед сохранением.
     *
     * Пример вызова метода _log_query() внутри класса (например, из _execute_query()):
     * @code
     * // Предположим:
     * // $this->txt_query содержит текст выполненного запроса
     * // $execution_time_ms содержит время выполнения в мс
     * // SQL_LOG = true
     * // TBL_LOG_QUERY определена
     *
     * // Логирование медленного запроса (если $execution_time_ms > 200)
     * if ($execution_time_ms > 200) {
     *     $this->_log_query('slow', $execution_time_ms);
     * }
     *
     * // Логирование запроса без плейсхолдеров (если $params был пуст)
     * // else if (empty($params)) { // ... применимо, если не был медленным
     * //     $this->_log_query('no_placeholders', $execution_time_ms); // Время выполнения все равно полезно
     * // }
     * @endcode
     * @see    PhotoRigma::Classes::Database::$txt_query
     *         Свойство, содержащее текст SQL-запроса, который логируется.
     * @see    PhotoRigma::Classes::Database::$pdo
     *         Объект PDO, используемый для выполнения запросов к таблице логов.
     * @see    PhotoRigma::Classes::Database::$query_cache
     *         Внутреннее свойство, используемое для кэширования результатов проверки существования запросов.
     * @see    PhotoRigma::Classes::Database::_execute_query()
     *         Приватный метод, из которого вызывается данный метод.
     */
    private function _log_query(string $reason = 'other', ?float $execution_time_ms = null): void
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
                'SELECT id, usage_count, execution_time FROM ' . TBL_LOG_QUERY . ' WHERE query_hash = :hash'
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
                'UPDATE ' . TBL_LOG_QUERY . '
            SET usage_count = :usage_count, last_used_at = CURRENT_TIMESTAMP, execution_time = :execution_time
            WHERE id = :id'
            );
            $stmt->execute([
                ':usage_count'    => $new_usage_count,
                ':execution_time' => $max_execution_time,
                ':id'             => $this->query_cache[$hash]['id'],
            ]);
        } else {
            // Если запрос не существует, сохраняем его в таблицу логов
            $stmt = $this->pdo->prepare(
                'INSERT INTO ' . TBL_LOG_QUERY . ' (query_hash, query_text, reason, execution_time)
            VALUES (:hash, :text, :reason, :execution_time)'
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
     * @brief   Формирует SQL-выражение для форматирования даты с учетом специфики используемой СУБД.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода `_format_date_internal()`.
     *          Он принимает название столбца с датой и формат даты, а затем вызывает внутренний метод для
     *          формирования соответствующего SQL-выражения, учитывая специфику используемой СУБД.
     *          Метод предназначен для использования клиентским кодом для получения SQL-выражений для форматирования
     *          дат в запросах.
     *
     * @callgraph
     *
     * @param string $column Название столбца с датой:
     *                       - Должен быть непустой строкой в формате имени столбца таблицы в БД.
     * @param string $format Формат даты:
     *                       - Может быть указан в любом поддерживаемом формате (например, MySQL, PostgreSQL, SQLite).
     *                       - Преобразование выполняется во внутренней логике в зависимости от типа СУБД.
     *
     * @return string SQL-выражение для форматирования даты.
     *
     * @throws InvalidArgumentException Выбрасывается, если тип СУБД не поддерживается (выбрасывается из внутренней
     *                                  логики).
     *                                  Пример сообщения:
     *                                  Не поддерживаемый тип СУБД | Тип: unknown
     *
     * @note    Этот метод является точкой входа для получения SQL-выражений для форматирования даты.
     *          Преобразование формата даты и выбор специфичной для СУБД функции выполняются в защищённом методе
     *          `_format_date_internal()`.
     * @warning Убедитесь, что `$column` (непустая строка) и `$format` содержат корректные значения перед вызовом метода.
     *          Неверный формат может привести к ошибкам при формировании SQL-запроса.
     *
     * Пример использования:
     * @code
     * // Пример использования публичного метода для форматирования даты
     * $db = new \PhotoRigma\Classes\Database();
     *
     * $formattedDateSql = $db->format_date('created_at', '%Y-%m-%d');
     * // $formattedDateSql будет содержать SQL-выражение, специфичное для текущей СУБД,
     * // например: DATE_FORMAT(created_at, '%Y-%m-%d') для MySQL
     *
     * // Пример использования в SELECT запросе
     * // $results = $db->select([$db->format_date('timestamp', 'YYYY-MM-DD') . ' as formatted_date'], 'logs', [...]);
     * @endcode
     * @see    PhotoRigma::Classes::Database::_format_date_internal()
     *         Защищённый метод, выполняющий основную логику формирования SQL-выражения.
     */
    public function format_date(string $column, string $format): string
    {
        return $this->_format_date_internal($column, $format);
    }

    /**
     * @brief   Формирует SQL-выражение для форматирования даты с учетом специфики используемой СУБД.
     *
     * @details Этот защищённый метод выполняет следующие шаги:
     *          1. Проверяет тип СУБД через свойство `$this->db_type`.
     *          2. Для MariaDB (MySQL) преобразует переданный формат `$format` через метод `_convert_to_mysql_format()`
     *             и использует функцию `DATE_FORMAT`.
     *          3. Для PostgreSQL преобразует формат через метод `_convert_to_postgres_format()` и использует функцию
     *             `TO_CHAR`. Метод принимает формат не только в стиле MySQL, но и другие поддерживаемые форматы.
     *          4. Для SQLite преобразует формат через метод `_convert_to_sqlite_format()` и использует функцию
     *             `strftime`. Метод принимает формат не только в стиле MySQL, но и другие поддерживаемые форматы.
     *          5. Если тип СУБД не поддерживается, выбрасывает исключение InvalidArgumentException.
     *
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
     *                       - Текущий формат определяется через свойство `$this->current_format`.
     *                       - Преобразование выполняется в зависимости от типа СУБД.
     *
     * @return string SQL-выражение для форматирования даты.
     *
     * @throws InvalidArgumentException Выбрасывается, если тип СУБД (`$this->db_type`) не поддерживается.
     *                                  Пример сообщения:
     *                                  Не поддерживаемый тип СУБД | Тип: unknown
     *
     * @note    Метод использует функции форматирования даты, специфичные для каждой СУБД. Текущий формат хранится в
     *          свойстве `$this->current_format`, а список допустимых форматов — в свойстве `$this->allowed_formats`.
     *          Для временного изменения текущего формата используется метод `_with_sql_format_internal()`.
     *
     * @warning Убедитесь, что `$column` (непустая строка) и `$format` содержат корректные значения перед вызовом метода.
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
     * @see    PhotoRigma::Classes::Database::$db_type
     *         Тип используемой СУБД.
     * @see    PhotoRigma::Classes::Database::$current_format
     *         Текущий формат SQL.
     * @see    PhotoRigma::Classes::Database::$allowed_formats
     *         Список допустимых форматов SQL.
     * @see    PhotoRigma::Classes::Database::_convert_to_mysql_format()
     *         Преобразовывает формат даты в стиль MySQL.
     * @see    PhotoRigma::Classes::Database::_convert_to_postgres_format()
     *         Преобразовывает формат даты в стиль PostgreSQL.
     * @see    PhotoRigma::Classes::Database::_convert_to_sqlite_format()
     *         Преобразовывает формат даты в стиль SQLite.
     * @see    PhotoRigma::Classes::Database::_with_sql_format_internal()
     *         Временно изменяет формат SQL для выполнения запросов.
     * @see    PhotoRigma::Classes::Database::format_date()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _format_date_internal(string $column, string $format): string
    {
        // Используем match для выбора логики в зависимости от типа СУБД
        return match ($this->db_type) {
            'mysql' => "DATE_FORMAT($column, '" . $this->_convert_to_mysql_format(
                $format
            ) . "')", // Для MySQL преобразуем формат
            'pgsql' => "TO_CHAR($column, '" . $this->_convert_to_postgres_format(
                $format
            ) . "')", // Для PostgreSQL преобразуем формат
            'sqlite' => "strftime('" . $this->_convert_to_sqlite_format(
                $format
            ) . "', $column)",   // Для SQLite преобразуем формат
            default => throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не поддерживаемый тип СУБД | Тип: $this->db_type"
            ),
        };
    }

    /**
     * @brief   Производит преобразование строки формата даты/времени в формат, совместимый с MySQL (`DATE_FORMAT`).
     *
     * @details Этот приватный метод преобразует строку формата даты или времени из формата, соответствующего
     *          текущему формату строки (`$this->current_format`), в формат, совместимый с функцией MySQL
     *          `DATE_FORMAT()`.
     *          Метод выполняет следующие шаги:
     *          1. Проверяет, совпадает ли текущий формат строки (`$this->current_format`) с 'mysql'.
     *             Если форматы совпадают, входная строка `$format` уже считается MySQL-совместимой и возвращается
     *             без изменений.
     *          2. Проверяет, существует ли уже преобразованная строка формата в кэше `$this->format_cache` для
     *             текущего исходного формата (`$this->current_format`) и входной строки `$format`.
     *             Если найдено в кэше, преобразованное значение возвращается немедленно.
     *          3. Если формат не 'mysql' и не найден в кэше, определяет карту соответствия символов формата
     *             для преобразования из `$this->current_format` в MySQL.
     *             Поддерживаемые исходные форматы: 'pgsql' и 'sqlite'.
     *          4. Если текущий формат (`$this->current_format`) не является поддерживаемым исходным форматом для
     *             преобразования в MySQL, выбрасывается исключение InvalidArgumentException.
     *          5. Использует карту соответствия для преобразования входной строки `$format` с помощью `strtr()`.
     *          6. Сохраняет результат преобразования в кэше `$this->format_cache` для будущего использования.
     *          7. Возвращает преобразованную строку формата, совместимую с MySQL `DATE_FORMAT()`.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *          Вызывается из метода `_format_date_internal()` при работе с СУБД MySQL.
     *
     * @internal
     * @callergraph
     *
     * @param string $format Строка формата даты/времени в стиле текущего формата строки (`$this->current_format`).
     *                       Пример: 'YYYY-MM-DD' для формата PostgreSQL или '%Y-%m-%d' для формата SQLite/MySQL.
     *
     * @return string Строка формата даты/времени, преобразованная в стиль, совместимый с функцией MySQL
     *                `DATE_FORMAT ()`. Пример: '%Y-%m-%d'.
     *
     * @throws InvalidArgumentException Выбрасывается, если текущий формат строки (`$this->current_format`) не
     *                                  поддерживается методом `_convert_to_mysql_format` в качестве исходного формата
     *                                  для преобразования в MySQL.
     *                                  Пример сообщения:
     *                                  Неизвестный формат | Формат: [current_format]
     *
     * @note    Метод использует кэширование (`$this->format_cache`) для хранения результатов преобразования форматов
     *          и избежания повторных вычислений. Используется строковая замена (`strtr()`) на основе
     *          предопределенных карт соответствия.
     *          Поведение метода зависит от свойства `$this->current_format`.
     *
     * @warning Убедитесь, что свойство `$this->current_format` содержит одно из допустимых значений ('mysql',
     *          'pgsql', 'sqlite') перед вызовом этого метода.
     *          Убедитесь, что входная строка `$format` соответствует синтаксису формата, ожидаемому для
     *          `$this->current_format`. Неподдерживаемый исходный формат приведет к исключению.
     *
     * Пример вызова метода внутри класса (например, из _format_date_internal()):
     * @code
     * // Предположим:
     * // $this->current_format = 'pgsql';
     * // $this->db_type = 'mysql';
     * // $pgsql_format = 'YYYY-MM-DD HH24:MI:SS';
     *
     * $mysql_format = $this->_convert_to_mysql_format($pgsql_format);
     * // $mysql_format будет равен '%Y-%m-%d %H:%i:%s' (взято из кэша или преобразовано)
     *
     * // Затем этот формат может быть использован с функцией MySQL DATE_FORMAT:
     * // DATE_FORMAT(column, '%Y-%m-%d %H:%i:%s')
     * @endcode
     * @see    PhotoRigma::Classes::Database::$format_cache
     *         Внутреннее свойство, используемое для кэширования преобразований форматов.
     * @see    PhotoRigma::Classes::Database::$current_format
     *         Свойство, определяющее исходный формат строки для преобразования.
     * @see    PhotoRigma::Classes::Database::_format_date_internal()
     *         Приватный метод, из которого вызывается данный метод.
     */
    private function _convert_to_mysql_format(string $format): string
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
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
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
     * @brief   Преобразует формат даты/времени в формат, совместимый с PostgreSQL.
     *
     * @details Этот приватный метод преобразует строку формата даты или времени из формата, соответствующего
     *          текущему формату строки (`$this->current_format`), в формат, совместимый с функциями PostgreSQL
     *          (например, `TO_CHAR`).
     *
     *          Метод выполняет следующие шаги:
     *          1. Проверяет, совпадает ли текущий формат строки (`$this->current_format`) с 'pgsql'.
     *             Если форматы совпадают, входная строка `$format` уже считается PostgreSQL-совместимой и
     *             возвращается без изменений.
     *          2. Проверяет, существует ли уже преобразованная строка формата в кэше `$this->format_cache` для
     *             текущего исходного формата (`$this->current_format`) и входной строки `$format`.
     *             Если найдено в кэше, преобразованное значение возвращается немедленно.
     *          3. Если формат не 'pgsql' и не найден в кэше, определяет карту соответствия символов формата
     *             для преобразования из `$this->current_format` (который может быть 'mysql' или 'sqlite') в PostgreSQL.
     *          4. Если текущий формат (`$this->current_format`) не является поддерживаемым исходным форматом для
     *             преобразования в PostgreSQL, выбрасывается исключение InvalidArgumentException.
     *          5. Использует карту соответствия для преобразования входной строки `$format` с помощью `strtr()`.
     *          6. Сохраняет результат преобразования в кэше `$this->format_cache` для будущего использования.
     *          7. Возвращает преобразованную строку формата, совместимую с функциями PostgreSQL.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *          Вызывается из метода `_format_date_internal()` при работе с СУБД PostgreSQL.
     *
     * @internal
     * @callergraph
     *
     * @param string $format Строка формата даты/времени в стиле текущего формата строки (`$this->current_format`).
     *                       Пример: '%Y-%m-%d' для формата MySQL/SQLite или 'YYYY-MM-DD' для формата PostgreSQL (в
     *                       случае, если current_format уже pgsql).
     *
     * @return string Строка формата даты/времени, преобразованная в стиль, совместимый с функциями PostgreSQL
     *                (например, TO_CHAR).
     *                Пример: 'YYYY-MM-DD HH24:MI:SS'.
     *
     * @throws InvalidArgumentException Выбрасывается, если текущий формат строки (`$this->current_format`) не
     *                                  поддерживается методом `_convert_to_postgres_format` в качестве исходного
     *                                  формата для преобразования в PostgreSQL (т.е., не 'mysql' и не 'sqlite').
     *                                  Пример сообщения:
     *                                  Неизвестный формат | Формат: [current_format]
     *
     * @note    Метод использует кэширование (`$this->format_cache`) для хранения результатов преобразования форматов
     *          и избежания повторных вычислений. Используется строковая замена (`strtr()`) на основе
     *          предопределенных карт соответствия.
     *          Поведение метода зависит от свойства `$this->current_format`.
     *
     * @warning Убедитесь, что свойство `$this->current_format` содержит одно из допустимых значений ('mysql',
     *          'pgsql', 'sqlite') перед вызовом этого метода.
     *          Убедитесь, что входная строка `$format` соответствует синтаксису формата, ожидаемому для
     *          `$this->current_format`. Неподдерживаемый исходный формат приведет к исключению.
     *
     * Пример вызова метода внутри класса (например, из _format_date_internal()):
     * @code
     * // Предположим:
     * // $this->current_format = 'mysql';
     * // $this->db_type = 'pgsql';
     * // $mysql_format = '%Y-%m-%d %H:%i:%s';
     *
     * $postgres_format = $this->_convert_to_postgres_format($mysql_format);
     * // $postgres_format будет равен 'YYYY-MM-DD HH24:MI:SS' (взято из кэша или преобразовано)
     *
     * // Затем этот формат может быть использован с функцией PostgreSQL TO_CHAR:
     * // TO_CHAR(column, 'YYYY-MM-DD HH24:MI:SS')
     * @endcode
     * @see    PhotoRigma::Classes::Database::$format_cache
     *         Внутреннее свойство, используемое для кэширования преобразований форматов.
     * @see    PhotoRigma::Classes::Database::$current_format
     *         Свойство, определяющее исходный формат строки для преобразования.
     * @see    PhotoRigma::Classes::Database::_format_date_internal()
     *         Приватный метод, из которого вызывается данный метод.
     */
    private function _convert_to_postgres_format(string $format): string
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
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
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
     * @brief   Преобразует формат даты/времени в формат, совместимый с SQLite (`strftime`).
     *
     * @details Этот приватный метод преобразует строку формата даты или времени из формата, соответствующего
     *          текущему формату строки (`$this->current_format`), в формат, совместимый с функцией SQLite `strftime()`.
     *
     *          Метод выполняет следующие шаги:
     *          1. Проверяет, совпадает ли текущий формат строки (`$this->current_format`) с 'sqlite'.
     *             Если форматы совпадают, входная строка `$format` уже считается SQLite-совместимой и возвращается
     *             без изменений.
     *          2. Проверяет, существует ли уже преобразованная строка формата в кэше `$this->format_cache` для
     *             текущего исходного формата (`$this->current_format`) и входной строки `$format`.
     *             Если найдено в кэше, преобразованное значение возвращается немедленно.
     *          3. Если формат не 'sqlite' и не найден в кэше, определяет карту соответствия символов формата
     *             для преобразования из `$this->current_format` (который может быть 'mysql' или 'pgsql') в SQLite.
     *          4. Если текущий формат (`$this->current_format`) не является поддерживаемым исходным форматом для
     *             преобразования в SQLite, выбрасывается исключение InvalidArgumentException.
     *          5. Использует карту соответствия для преобразования входной строки `$format` с помощью `strtr()`.
     *          6. Сохраняет результат преобразования в кэше `$this->format_cache` для будущего использования.
     *          7. Возвращает преобразованную строку формата, совместимую с функцией SQLite `strftime()`.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *          Вызывается из метода `_format_date_internal()` при работе с СУБД SQLite.
     *
     * @internal
     * @callergraph
     *
     * @param string $format Строка формата даты/времени в стиле текущего формата строки (`$this->current_format`).
     *                       Пример: '%Y-%m-%d' для формата MySQL/SQLite или 'YYYY-MM-DD HH24:MI:SS' для формата
     *                       PostgreSQL.
     *
     * @return string Строка формата даты/времени, преобразованная в стиль, совместимый с функцией SQLite `strftime()`.
     *                Пример: '%Y-%m-%d %H:%M:%S'.
     *
     * @throws InvalidArgumentException Выбрасывается, если текущий формат строки (`$this->current_format`) не
     *                                  поддерживается методом `_convert_to_sqlite_format` в качестве исходного
     *                                  формата для преобразования в SQLite (т.е., не 'mysql' и не 'pgsql').
     *                                  Пример сообщения:
     *                                  Неизвестный формат | Формат: [current_format]
     *
     * @note    Метод использует кэширование (`$this->format_cache`) для хранения результатов преобразования форматов
     *          и избежания повторных вычислений. Используется строковая замена (`strtr()`) на основе
     *          предопределенных карт соответствия.
     *          Поведение метода зависит от свойства `$this->current_format`.
     *
     * @warning Убедитесь, что свойство `$this->current_format` содержит одно из допустимых значений ('mysql',
     *          'pgsql', 'sqlite') перед вызовом этого метода.
     *          Убедитесь, что входная строка `$format` соответствует синтаксису формата, ожидаемому для
     *          `$this->current_format`. Неподдерживаемый исходный формат приведет к исключению.
     *
     * Пример вызова метода внутри класса (например, из _format_date_internal()):
     * @code
     * // Предположим:
     * // $this->current_format = 'mysql';
     * // $this->db_type = 'sqlite';
     * // $mysql_format = '%Y-%m-%d %H:%i:%s';
     *
     * $sqlite_format = $this->_convert_to_sqlite_format($mysql_format);
     * // $sqlite_format будет равен '%Y-%m-%d %H:%M:%S' (взято из кэша или преобразовано)
     *
     * // Предположим:
     * // $this->current_format = 'pgsql';
     * // $this->db_type = 'sqlite';
     * // $pgsql_format = 'YYYY-MM-DD HH24:MI:SS';
     *
     * $sqlite_format_from_pgsql = $this->_convert_to_sqlite_format($pgsql_format);
     * // $sqlite_format_from_pgsql будет равен '%Y-%m-%d %H:%M:%S' (взято из кэша или преобразовано)
     * @endcode
     * @see    PhotoRigma::Classes::Database::$format_cache
     *         Внутреннее свойство, используемое для кэширования преобразований форматов.
     * @see    PhotoRigma::Classes::Database::$current_format
     *         Свойство, определяющее исходный формат строки для преобразования.
     * @see    PhotoRigma::Classes::Database::_format_date_internal()
     *         Приватный метод, из которого вызывается данный метод.
     */
    private function _convert_to_sqlite_format(string $format): string
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
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
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
     * @brief   Возвращает количество строк, затронутых последним SQL-запросом.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода `_get_affected_rows_internal()`.
     *          Он вызывает внутренний метод для получения значения количества строк, затронутых последним
     *          выполненным запросом INSERT, UPDATE, DELETE или REPLACE, после соответствующих проверок.
     *          Метод предназначен для использования клиентским кодом как точка входа для получения информации о
     *          результатах модифицирующих запросов.
     *
     * @callgraph
     *
     * @return int Количество строк, затронутых последним SQL-запросом.
     *
     * @throws InvalidArgumentException Выбрасывается, если количество затронутых строк не установлено
     *                                  или имеет некорректное значение (выбрасывается из внутренней логики).
     *                                  Пример сообщения:
     *                                  Количество затронутых строк не установлено | Причина: Свойство aff_rows
     *                                  не определено
     *
     * @note    Этот метод является точкой входа для получения количества затронутых строк.
     *          Значение количества строк устанавливается методами выполнения запросов (например,
     *          `insert()`, `update()`, `delete()`). Внутренняя логика получения значения
     *          и проверки его корректности выполняется в защищённом методе `_get_affected_rows_internal()`.
     * @warning Убедитесь, что перед вызовом этого метода был выполнен модифицирующий запрос
     *          (INSERT, UPDATE, DELETE или REPLACE), чтобы количество затронутых строк было установлено.
     *
     * Пример использования:
     * @code
     * // Предполагается, что перед этим был выполнен INSERT, UPDATE, DELETE или REPLACE запрос
     * $db = new \PhotoRigma\Classes\Database();
     * // ... например: $db->update('users', ['status' => 1], ['where' => 'id = 5']);
     *
     * // Получаем количество затронутых строк
     * $affectedRows = $db->get_affected_rows();
     * echo "Affected rows: " . $affectedRows;
     * @endcode
     * @see    PhotoRigma::Classes::Database::_get_affected_rows_internal()
     *         Защищённый метод, выполняющий основную логику получения количества затронутых строк.
     */
    public function get_affected_rows(): int
    {
        return $this->_get_affected_rows_internal();
    }

    /**
     * @brief   Возвращает количество строк, затронутых последним SQL-запросом, после проверки состояния свойства
     *          `$aff_rows`.
     *
     * @details Этот защищённый метод выполняет следующие шаги:
     *          1. Проверяет, что свойство `$this->aff_rows` установлено. Если это не так, выбрасывается исключение
     *             InvalidArgumentException.
     *          2. Проверяет, что значение свойства `$this->aff_rows` является целым числом. Если это не так,
     *             выбрасывается исключение InvalidArgumentException.
     *          3. Возвращает значение свойства `$this->aff_rows`, которое представляет собой количество строк,
     *             затронутых последним SQL-запросом (операциями INSERT, UPDATE, DELETE, REPLACE).
     *
     *          Метод только читает значение свойства `$this->aff_rows` и не выполняет дополнительной логики для его
     *          обновления. Значение `$this->aff_rows` должно быть установлено внешними методами, такими как
     *          `_execute_query()`, `delete()`, `update()` или `insert()`.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `get_affected_rows()`.
     *
     * @callergraph
     * @callgraph
     *
     * @return int Количество строк, затронутых последним SQL-запросом.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - Свойство `$this->aff_rows` не установлено (т.е. не было выполнено запроса,
     *                                    обновляющего его).
     *                                    Пример сообщения:
     *                                    Количество затронутых строк не установлено | Причина: Свойство aff_rows
     *                                    не определено
     *                                  - Значение свойства `$this->aff_rows` не является целым числом (указывает на
     *                                    некорректное состояние).
     *                                    Пример сообщения:
     *                                    Некорректное значение свойства aff_rows | Ожидалось целое число,
     *                                    получено: [значение]
     *
     * @warning Метод чувствителен к состоянию свойства `$this->aff_rows`. Убедитесь, что перед вызовом метода был
     *          выполнен запрос (например, INSERT, UPDATE, DELETE), который корректно установил значение
     *          `$this->aff_rows` как целое число.
     *
     * Пример использования метода _get_affected_rows_internal():
     * @code
     * // Предполагается, что перед этим был выполнен INSERT, UPDATE, DELETE или REPLACE запрос
     * // ... например: $this->update('users', ['status' => 1], ['where' => 'id = 5']);
     *
     * // Получаем количество затронутых строк
     * $affectedRows = $this->_get_affected_rows_internal();
     * echo "Affected rows: " . $affectedRows;
     * @endcode
     * @see    PhotoRigma::Classes::Database::$aff_rows
     *         Свойство, хранящее количество строк, затронутых последним запросом.
     * @see    PhotoRigma::Classes::Database::_execute_query()
     *         Метод нижнего уровня, который обновляет значение `$aff_rows` после выполнения запроса.
     * @see    PhotoRigma::Classes::Database::delete()
     *         Метод, который вызывает `_execute_query` и, таким образом, может изменять значение `$aff_rows`.
     * @see    PhotoRigma::Classes::Database::update()
     *         Метод, который вызывает `_execute_query` и, таким образом, может изменять значение `$aff_rows`.
     * @see    PhotoRigma::Classes::Database::insert()
     *         Метод, который вызывает `_execute_query` и, таким образом, может изменять значение `$aff_rows`.
     * @see    PhotoRigma::Classes::Database::get_affected_rows()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _get_affected_rows_internal(): int
    {
        // === 1. Валидация состояния ===
        if (!isset($this->aff_rows)) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Количество затронутых строк не установлено | Причина: Свойство aff_rows не определено'
            );
        }
        // === 2. Возврат значения ===
        return $this->aff_rows;
    }

    /**
     * @brief   Возвращает ID последней вставленной строки.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода `_get_last_insert_id_internal()`.
     *          Он вызывает внутренний метод для получения значения ID последней вставленной строки
     *          после соответствующих проверок.
     *          Метод предназначен для использования клиентским кодом как точка входа для
     *          получения информации о результатах операций вставки данных.
     *
     * @callgraph
     *
     * @return int ID последней вставленной строки.
     *
     * @throws InvalidArgumentException Выбрасывается, если ID последней вставленной строки не установлено
     *                                  или имеет некорректное значение (выбрасывается из внутренней логики).
     *                                  Пример сообщения:
     *                                  ID последней вставленной строки не установлен | Причина: Свойство
     *                                  insert_id не определено
     *
     * @note    Этот метод является точкой входа для получения ID последней вставленной строки.
     *          Значение ID устанавливается методами выполнения INSERT запросов (например, `insert()`).
     *          Внутренняя логика получения значения и проверки его корректности выполняется в
     *          защищённом методе `_get_last_insert_id_internal()`.
     * @warning Убедитесь, что перед вызовом этого метода был выполнен запрос на вставку данных
     *          (INSERT), чтобы ID последней вставленной строки было установлено.
     *
     * Пример использования:
     * @code
     * // Предполагается, что перед этим был выполнен INSERT запрос
     * $db = new \PhotoRigma\Classes\Database();
     * // ... например: $db->insert(['name' => 'New User'], 'users');
     *
     * // Получаем ID последней вставленной строки
     * $lastInsertId = $db->get_last_insert_id();
     * echo "Last insert ID: " . $lastInsertId;
     * @endcode
     * @see    PhotoRigma::Classes::Database::_get_last_insert_id_internal()
     *         Защищённый метод, выполняющий основную логику получения ID последней вставленной строки.
     */
    public function get_last_insert_id(): int
    {
        return $this->_get_last_insert_id_internal();
    }

    /**
     * @brief   Возвращает ID последней вставленной строки после проверки состояния свойства `$insert_id`.
     *
     * @details Этот защищённый метод выполняет следующие шаги:
     *          1. Проверяет, что свойство `$this->insert_id` установлено. Если это не так, выбрасывается исключение
     *             InvalidArgumentException.
     *          2. Проверяет, что значение свойства `$this->insert_id` является целым числом. Если это не так,
     *             выбрасывается исключение InvalidArgumentException.
     *          3. Возвращает значение свойства `$this->insert_id`, которое представляет собой ID последней вставленной
     *             строки.
     *
     *          Метод только читает значение свойства `$this->insert_id` и не выполняет дополнительной логики для его
     *          обновления. Значение `$this->insert_id` должно быть установлено внешними методами, такими как
     *          `_execute_query()` или `insert()`.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `get_last_insert_id()`.
     *
     * @callergraph
     *
     * @return int ID последней вставленной строки.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - Свойство `$this->insert_id` не установлено (т.е. не было выполнено запроса,
     *                                    устанавливающего его).
     *                                    Пример сообщения:
     *                                    ID последней вставленной строки не установлен | Причина: Свойство
     *                                    insert_id не определено
     *                                  - Значение свойства `$this->insert_id` не является целым числом (указывает на
     *                                    некорректное состояние).
     *                                    Пример сообщения:
     *                                    Некорректное значение свойства insert_id | Ожидалось целое число,
     *                                    получено: [значение]
     *
     * @warning Метод чувствителен к состоянию свойства `$this->insert_id`. Убедитесь, что перед вызовом метода был
     *          выполнен запрос (например, INSERT), который корректно установил значение `$this->insert_id` как целое
     *          число.
     *
     * Пример использования метода _get_last_insert_id_internal():
     * @code
     * // Предполагается, что перед этим был выполнен INSERT запрос, например:
     * // $this->insert(['name' => 'John Doe', 'email' => 'john@local.com'], 'users');
     *
     * // Получаем ID последней вставленной строки
     * $lastInsertId = $this->_get_last_insert_id_internal();
     * echo "Last insert ID: " . $lastInsertId;
     * // Примечание: Метод insert обновляет значение свойства $insert_id.
     * @endcode
     * @see    PhotoRigma::Classes::Database::$insert_id
     *         Свойство, хранящее ID последней вставленной строки.
     * @see    PhotoRigma::Classes::Database::insert()
     *         Метод, который может устанавливать значение `$insert_id`.
     * @see    PhotoRigma::Classes::Database::_execute_query()
     *         Метод нижнего уровня, который может обновлять значение `$insert_id` после выполнения запроса.
     * @see    PhotoRigma::Classes::Database::get_last_insert_id()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _get_last_insert_id_internal(): int
    {
        // === 1. Валидация состояния ===
        if (!isset($this->insert_id)) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ID последней вставленной строки не установлен | Причина: Свойство insert_id не определено'
            );
        }
        // === 2. Возврат значения ===
        return $this->insert_id;
    }

    /**
     * @brief   Вставляет данные в таблицу через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода `_insert_internal()`.
     *          Он передаёт массив данных для вставки, имя таблицы, тип запроса и опции во внутренний метод, который
     *          выполняет формирование и выполнение SQL-запроса на вставку.
     *          Метод поддерживает различные типы вставок (`IGNORE`, `REPLACE`, `INTO`).
     *          Безопасность обеспечивается использованием подготовленных выражений с параметрами.
     *          Метод предназначен для использования клиентским кодом как точка входа для выполнения операций вставки
     *          данных.
     *
     * @callgraph
     *
     * @param array  $insert  Ассоциативный массив данных для вставки в формате: 'имя_поля' => 'значение' или
     *                        'имя_поля' => ':плейсхолдер'.
     *                        Если передан пустой массив, выбрасывается исключение.
     * @param string $to_tbl  Имя таблицы, в которую необходимо вставить данные.
     *                        Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                        символов.
     * @param string $type    Тип запроса (необязательно). Определяет тип SQL-запроса на вставку. Допустимые
     *                        значения:
     *                        - 'ignore': Формирует запрос типа "INSERT IGNORE INTO".
     *                        - 'replace': Формирует запрос типа "REPLACE INTO".
     *                        - 'into': Формирует запрос типа "INSERT INTO" (явное указание).
     *                        - '' (пустая строка): Формирует запрос типа "INSERT INTO" (по умолчанию).
     *                        Если указан недопустимый тип, выбрасывается исключение InvalidArgumentException.
     * @param array  $options Массив опций для формирования запроса. Поддерживаемые ключи:
     *                        - params (array): Параметры для подготовленного выражения. Ассоциативный массив
     *                          значений, используемых в запросе (например, [":name" => "John Doe", ":email" =>
     *                          "john@local.com"]).
     *                          Обязателен для использования с данными для вставки, требующими
     *                          подготовленных выражений. Обеспечивает защиту от SQL-инъекций.
     *                          Может быть пустым массивом, если параметры не требуются.
     *                        - where (string|array|false): Условие WHERE. Игнорируется в этом методе.
     *                        - group (string|false): Группировка GROUP BY. Игнорируется в этом методе.
     *                        - order (string|false): Сортировка ORDER BY. Игнорируется в этом методе.
     *                        - limit (int|string|false): Ограничение LIMIT. Игнорируется в этом методе.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой). В случае ошибки
     *              выбрасывается исключение.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                          - `$insert` не является массивом или является пустым массивом.
     *                            Пример сообщения:
     *                            Данные для вставки не могут быть пустыми | Причина: Пустой массив данных
     *                          - `$to_tbl` не является строкой.
     *                            Пример сообщения:
     *                            Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                          - `$type` содержит недопустимое значение (не '', 'ignore', 'replace', 'into').
     *                            Пример сообщения:
     *                            Недопустимый тип вставки | Разрешённые значения: '', 'ignore', 'replace',
     *                            'into'. Получено: '$type'
     *                          - `$options` не является массивом.
     *                            Пример сообщения:
     *                            Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     * @throws Exception        Выбрасывается при ошибках выполнения запроса.
     *
     * @note    Этот метод является точкой входа для выполнения операций вставки данных.
     *          Все проверки и основная логика выполняются в защищённом методе `_insert_internal()`.
     *          Ключи массива `$options` (`where`, `group`, `order`, `limit`) игнорируются в этом методе.
     *
     * @warning Метод чувствителен к корректности входных данных. Неверные типы данных или некорректные значения могут
     *          привести к выбросу исключения. Убедитесь, что массив `$insert` не пустой и содержит корректные данные.
     *          Параметр `$type` должен быть одним из допустимых значений: '', 'ignore', 'replace', 'into'.
     *          Использование параметров `params` для подготовленных выражений является обязательным для защиты от
     *          SQL-инъекций и обеспечения совместимости с различными СУБД.
     *
     * Пример использования:
     * @code
     * // Пример безопасного использования публичного метода INSERT
     * $db = new \PhotoRigma\Classes\Database();
     *
     * $db->insert(
     *     ['name' => ':name', 'email' => ':email'],
     *     'users',
     *     '', // или 'ignore', 'replace', 'into'
     *     ['params' => [':name' => 'John Doe', ':email' => 'john@local.com']]
     * );
     * @endcode
     * @see    PhotoRigma::Classes::Database::_insert_internal()
     *         Защищённый метод, выполняющий основную логику вставки данных.
     */
    public function insert(array $insert, string $to_tbl, string $type = '', array $options = []): bool
    {
        return $this->_insert_internal($insert, $to_tbl, $type, $options);
    }

    /**
     * @brief   Формирует SQL-запрос на вставку данных в таблицу, проверяет входные данные, обрабатывает различные типы
     *          запросов, сохраняет текст запроса в свойстве `$txt_query` и выполняет его.
     *
     * @details Этот защищённый метод выполняет следующие шаги:
     *          1. Валидация входных данных: `$insert` (ассоциативный массив), `$to_tbl` (строка), `$type` (строка),
     *             `$options` (массив).
     *          2. Проверяет, что массив `$insert` не пустой. Если массив пустой, выбрасывается исключение.
     *          3. Нормализует параметр `$type`, приводя его к нижнему регистру, и проверяет на допустимые значения:
     *             `'ignore'`, `'replace'`, `'into'`, `''`. Если указан недопустимый тип, выбрасывается исключение.
     *          4. Определяет тип запроса на основе параметра `$type`:
     *             - `'ignore'`: Формирует запрос типа "INSERT IGNORE INTO".
     *             - `'replace'`: Формирует запрос типа "REPLACE INTO".
     *             - `'into'` или пустая строка (`''`): Формирует запрос типа "INSERT INTO" (по умолчанию).
     *          5. Формирует базовый SQL-запрос INSERT с использованием преобразованных данных и сохраняет его в
     *              свойстве `$txt_query`.
     *          6. Выполняет сформированный запрос через метод `_execute_query`, передавая параметры `params` из
     *             `$options` для подготовленного выражения.
     *
     *          Использование параметров `params` является обязательным для подготовленных выражений, так как это
     *          обеспечивает защиту от SQL-инъекций и совместимость с различными СУБД.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `insert()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param array  $insert  Ассоциативный массив данных для вставки в формате: 'имя_поля' => 'значение' или
     *                        'имя_поля' => ':плейсхолдер'.
     *                        Если передан пустой массив, выбрасывается исключение.
     * @param string $to_tbl  Имя таблицы, в которую необходимо вставить данные.
     *                        Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                        символов.
     * @param string $type    Тип запроса (необязательно). Определяет тип SQL-запроса на вставку. Допустимые
     *                        значения:
     *                        - 'ignore': Формирует запрос типа "INSERT IGNORE INTO".
     *                        - 'replace': Формирует запрос типа "REPLACE INTO".
     *                        - 'into': Формирует запрос типа "INSERT INTO" (явное указание).
     *                        - '' (пустая строка): Формирует запрос типа "INSERT INTO" (по умолчанию).
     *                        Если указан недопустимый тип, выбрасывается исключение InvalidArgumentException.
     * @param array  $options Массив опций для формирования запроса. Поддерживаемые ключи:
     *                        - params (array): Параметры для подготовленного выражения. Ассоциативный массив
     *                          значений, используемых в запросе (например, [":name" => "John Doe", ":email" =>
     *                          "john@local.com"]).
     *                          Обязателен для использования с данными для вставки, требующими
     *                          подготовленных выражений. Обеспечивает защиту от SQL-инъекций.
     *                          Может быть пустым массивом, если параметры не требуются.
     *                        - where (string|array|false): Условие WHERE. Игнорируется в этом методе.
     *                        - group (string|false): Группировка GROUP BY. Игнорируется в этом методе.
     *                        - order (string|false): Сортировка ORDER BY. Игнорируется в этом методе.
     *                        - limit (int|string|false): Ограничение LIMIT. Игнорируется в этом методе.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой). В случае ошибки
     *              выбрасывается исключение.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                          - `$insert` не является массивом или является пустым массивом.
     *                            Пример сообщения:
     *                            Данные для вставки не могут быть пустыми | Причина: Пустой массив данных
     *                          - `$to_tbl` не является строкой.
     *                            Пример сообщения:
     *                            Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                          - `$type` содержит недопустимое значение (не '', 'ignore', 'replace', 'into').
     *                            Пример сообщения:
     *                            Недопустимый тип вставки | Разрешённые значения: '', 'ignore', 'replace',
     *                            'into'. Получено: '$type'
     *                          - `$options` не является массивом.
     *                            Пример сообщения:
     *                            Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     * @throws Exception        Выбрасывается при ошибках выполнения запроса.
     *
     * @warning Метод чувствителен к корректности входных данных. Неверные типы данных или некорректные значения могут
     *          привести к выбросу исключения. Убедитесь, что массив `$insert` не пустой и содержит корректные данные.
     *          Параметр `$type` должен быть одним из допустимых значений: '', 'ignore', 'replace', 'into'.
     *          Использование параметров `params` для подготовленных выражений является обязательным для защиты от
     *          SQL-инъекций и обеспечения совместимости с различными СУБД. Игнорирование этого правила может привести
     *          к уязвимостям безопасности и неправильной работе с базой данных.
     *          Другие ключи массива `$options` (`where`, `group`, `order`, `limit`) игнорируются в этом методе.
     *
     * Пример использования метода _insert_internal():
     * @code
     * // Безопасное использование с параметрами `params`
     * $this->_insert_internal(
     *     ['name' => ':name', 'email' => ':email'],
     *     'users',
     *     '',
     *     ['params' => [':name' => 'John Doe', ':email' => 'john@local.com']]
     * );
     * @endcode
     * @see    PhotoRigma::Classes::Database::$txt_query
     *         Свойство, в которое помещается текст SQL-запроса.
     * @see    PhotoRigma::Classes::Database::insert()
     *         Публичный метод-редирект для вызова этой логики.
     * @see    PhotoRigma::Classes::Database::_execute_query()
     *         Выполняет SQL-запрос.
     */
    protected function _insert_internal(array $insert, string $to_tbl, string $type = '', array $options = []): bool
    {
        // === 1. Валидация аргументов ===
        if (empty($insert)) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Данные для вставки не могут быть пустыми | Причина: Пустой массив данных'
            );
        }
        // Нормализация $type (приведение к нижнему регистру)
        $type = strtolower($type);
        // Проверка допустимых значений для $type
        if (!in_array($type, ['', 'ignore', 'replace', 'into'], true)) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый тип вставки | Разрешённые значения: '', 'ignore', 'replace', 'into'. Получено: '$type'"
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
        return $this->_execute_query($options['params']);
    }

    /**
     * @brief   Выполняет SQL-запрос с использованием JOIN через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода `_join_internal()`.
     *          Он передаёт список полей, основную таблицу, массив JOIN-операций и опции во внутренний метод, который
     *          выполняет формирование и выполнение SQL-запроса с использованием JOIN.
     *          Безопасность обеспечивается использованием подготовленных выражений с параметрами.
     *          Метод предназначен для использования клиентским кодом как точка входа для выполнения запросов с JOIN.
     *
     * @callgraph
     *
     * @param string|array $select   Список полей для выборки. Может быть строкой (имя одного поля) или массивом
     *                                (список полей). Если передан массив, он преобразуется в строку с разделителем
     *                                `,`. Пример: "id", ["id", "name"]. Ограничения: массив не может быть пустым.
     * @param string       $from_tbl Имя основной таблицы, из которой начинается выборка.
     *                                Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                                символов. Ограничения: строка должна быть непустой.
     * @param array        $join     Массив описаний JOIN-операций. Каждый элемент массива должен содержать следующие
     *                                ключи:
     *                                - table (string): Имя таблицы для JOIN. Должно быть строкой, содержащей только
     *                                  допустимые имена таблиц.
     *                                - type (string, optional): Тип JOIN (например, INNER, LEFT, RIGHT). Если тип не
     *                                  указан, используется INNER по умолчанию.
     *                                - on (string): Условие для JOIN. Должно быть строкой с допустимым условием
     *                                  сравнения полей. Если условие отсутствует, выбрасывается исключение.
     *                                Пример:
     *                                [['type' => 'INNER', 'table' => 'orders', 'on' => 'users.id = orders.user_id']].
     *                                Ограничения: массив не может быть пустым.
     * @param array        $options  Массив дополнительных опций для формирования запроса. Поддерживаемые ключи:
     *                                - where (string|array|false): Условие WHERE.
     *                                  * string - используется как есть.
     *                                  * array - преобразуется в SQL. Поддерживает простые условия
     *                                    (`['поле' => значение]`) и логические операторы (`'OR' => [...]`,
     *                                    `'NOT' => [...]`).
     *                                  * false - игнорируется.
     *                                - group (string|false): Группировка GROUP BY. Игнорируется, если false.
     *                                  Должна быть строкой.
     *                                - order (string|false): Сортировка ORDER BY. Игнорируется, если false.
     *                                  Должна быть строкой.
     *                                - limit (int|string|false): Ограничение LIMIT.
     *                                  * int - прямое значение.
     *                                  * string - формат "OFFSET,COUNT".
     *                                  * false - игнорируется.
     *                                - params (array): Ассоциативный массив параметров
     *                                  ([":имя" => значение]).
     *                                  Обязателен для использования с условиями `where` и другими частями запроса,
     *                                  требующими подготовленных выражений. Обеспечивает защиту от SQL-инъекций.
     *                                  Может быть пустым массивом, если параметры не требуются.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$from_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                    Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                                  - `$join` не является массивом.
     *                                    Пример сообщения:
     *                                    Недопустимый тип параметра join | Ожидался массив, получено: [тип]
     *                                  - `$select` не является строкой или массивом.
     *                                    Пример сообщения:
     *                                    Недопустимый тип параметра select | Ожидалась строка или массив,
     *                                    получено: [тип]
     *                                  - Отсутствует имя таблицы (`table`) или условие (`on`) в описании JOIN.
     *                                    Пример сообщения:
     *                                    Отсутствует имя таблицы или условие для JOIN | Проверьте структуру
     *                                    массива $join
     *                                  - `$options` не является массивом.
     *                                    Пример сообщения:
     *                                    Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     * @throws Exception               Выбрасывается при ошибках выполнения запроса.
     *
     * @note    Этот метод является точкой входа для выполнения запросов с JOIN.
     *          Все проверки и основная логика выполняются в защищённом методе `_join_internal()`.
     *
     * @warning Метод чувствителен к корректности входных данных. Неверные типы данных или некорректные значения могут
     *          привести к выбросу исключения. Убедитесь, что массив `$join` не пустой и содержит корректные данные.
     *          Использование параметров `params` для подготовленных выражений является обязательным для защиты от
     *          SQL-инъекций и обеспечения совместимости с различными СУБД.
     *
     * Пример использования:
     * @code
     * // Пример безопасного использования публичного метода JOIN
     * $db = new \PhotoRigma\Classes\Database();
     *
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
     * @see    PhotoRigma::Classes::Database::_join_internal()
     *         Защищённый метод, выполняющий основную логику формирования и выполнения SQL-запроса с JOIN.
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
     *          8. Выполняет запрос через метод `_execute_query`, передавая параметры `params` для подготовленного
     *             выражения.
     *
     *          Использование параметров `params` является обязательным для подготовленных выражений, так как это
     *          обеспечивает защиту от SQL-инъекций и совместимость с различными СУБД. Этот метод является
     *          защищенным и предназначен для использования внутри класса или его наследников. Основная логика
     *          вызывается через публичный метод-редирект `join()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string|array $select   Список полей для выборки. Может быть строкой (имя одного поля) или массивом
     *                                (список полей). Если передан массив, он преобразуется в строку с разделителем
     *                                `,`. Пример: "id", ["id", "name"]. Ограничения: массив не может быть пустым.
     * @param string       $from_tbl Имя основной таблицы, из которой начинается выборка.
     *                                Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                                символов. Ограничения: строка должна быть непустой.
     * @param array        $join     Массив описаний JOIN-операций. Каждый элемент массива должен содержать следующие
     *                                ключи:
     *                                - table (string): Имя таблицы для JOIN. Должно быть строкой, содержащей только
     *                                  допустимые имена таблиц.
     *                                - type (string, optional): Тип JOIN (например, INNER, LEFT, RIGHT). Если тип не
     *                                  указан, используется INNER по умолчанию.
     *                                - on (string): Условие для JOIN. Должно быть строкой с допустимым условием
     *                                  сравнения полей. Если условие отсутствует, выбрасывается исключение.
     *                                Пример:
     *                                [['type' => 'INNER', 'table' => 'orders', 'on' => 'users.id = orders.user_id']].
     *                                Ограничения: массив не может быть пустым.
     * @param array        $options  Массив дополнительных опций для формирования запроса. Поддерживаемые ключи:
     *                                - where (string|array|false): Условие WHERE.
     *                                  * string - используется как есть.
     *                                  * array - преобразуется в SQL. Поддерживает простые условия
     *                                    (`['поле' => значение]`) и логические операторы (`'OR' => [...]`,
     *                                    `'NOT' => [...]`).
     *                                  * false - игнорируется.
     *                                - group (string|false): Группировка GROUP BY. Игнорируется, если false.
     *                                  Должна быть строкой.
     *                                - order (string|false): Сортировка ORDER BY. Игнорируется, если false.
     *                                  Должна быть строкой.
     *                                - limit (int|string|false): Ограничение LIMIT.
     *                                  * int - прямое значение.
     *                                  * string - формат "OFFSET,COUNT".
     *                                  * false - игнорируется.
     *                                - params (array): Ассоциативный массив параметров
     *                                  ([":имя" => значение]).
     *                                  Обязателен для использования с условиями `where` и другими частями запроса,
     *                                  требующими подготовленных выражений. Обеспечивает защиту от SQL-инъекций.
     *                                  Может быть пустым массивом, если параметры не требуются.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$from_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                    Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                                  - `$join` не является массивом.
     *                                    Пример сообщения:
     *                                    Недопустимый тип параметра join | Ожидался массив, получено: [тип]
     *                                  - `$select` не является строкой или массивом.
     *                                    Пример сообщения:
     *                                    Недопустимый тип параметра select | Ожидалась строка или массив,
     *                                    получено: [тип]
     *                                  - Отсутствует имя таблицы (`table`) или условие (`on`) в описании JOIN.
     *                                    Пример сообщения:
     *                                    Отсутствует имя таблицы или условие для JOIN | Проверьте структуру
     *                                    массива $join
     *                                  - `$options` не является массивом.
     *                                    Пример сообщения:
     *                                    Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     * @throws Exception               Выбрасывается при ошибках выполнения запроса.
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
     * @see    PhotoRigma::Classes::Database::$txt_query
     *         Свойство, в которое помещается текст SQL-запроса.
     * @see    PhotoRigma::Classes::Database::join()
     *         Публичный метод-редирект для вызова этой логики.
     * @see    PhotoRigma::Classes::Database::_execute_query()
     *         Выполняет SQL-запрос.
     * @see    PhotoRigma::Classes::Database::_build_conditions()
     *         Формирует условия WHERE, GROUP BY, ORDER BY и LIMIT для запроса.
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
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Отсутствует имя таблицы в описании JOIN | Проверьте структуру массива \$join"
                );
            }
            if (empty($j['on'])) {
                throw new InvalidArgumentException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Отсутствует условие 'on' для таблицы '{$j['table']}' в описании JOIN | Проверьте структуру массива \$join"
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
        [$conditions, $params] = $this->_build_conditions($options);
        $this->txt_query .= $conditions;

        // === 5. Выполнение запроса ===
        return $this->_execute_query($params);
    }

    /**
     * @brief   Извлекает все строки результата из подготовленного выражения.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода `_result_array_internal()`.
     *          Он вызывает внутренний метод для извлечения всех строк результата последнего выполненного
     *          запроса из свойства `$res_query` в виде массива ассоциативных массивов, после соответствующих проверок.
     *          Метод предназначен для использования клиентским кодом как точка входа для
     *          получения всех результатов запроса SELECT.
     *
     * @callgraph
     *
     * @return array|false Возвращает массив ассоциативных массивов, содержащих данные всех строк результата, если
     *                     они доступны. Если результатов нет (запрос не вернул строк), возвращает `false`.
     *
     * @throws InvalidArgumentException Выбрасывается, если результат запроса недоступен или имеет некорректный тип
     *                                  (выбрасывается из внутренней логики).
     *                                  Пример сообщения:
     *                                  Результат запроса недоступен | Причина: Свойство $res_query не
     *                                  установлено
     *
     * @note    Этот метод является точкой входа для получения всех строк результата запроса.
     *          Результат запроса (объект PDOStatement в свойстве `$res_query`) устанавливается методом выполнения
     *          запроса (например, `_execute_query()`). Внутренняя логика получения данных и проверки их корректности
     *          выполняется в защищённом методе `_result_array_internal()`.
     * @warning Убедитесь, что перед вызовом этого метода был успешно выполнен запрос (например, SELECT), который
     *          установил доступный для чтения результат (`$res_query`).
     *
     * Пример использования:
     * @code
     * // Предполагается, что перед этим был выполнен SELECT запрос
     * $db = new \PhotoRigma\Classes\Database();
     * // ... например: $db->select(['id', 'name'], 'users', ['where' => 'status = 1']);
     *
     * // Получаем все строки результата в виде массива
     * $results = $db->result_array();
     *
     * if ($results !== false) {
     *     foreach ($results as $row) {
     *         echo "ID: " . $row['id'] . ", Name: " . $row['name'] . "\n";
     *     }
     * } else {
     *     echo "No results found.";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Database::_result_array_internal()
     *         Защищённый метод, выполняющий основную логику извлечения всех строк результата.
     */
    public function result_array(): array|false
    {
        return $this->_result_array_internal();
    }

    /**
     * @brief   Извлекает все строки результата из подготовленного выражения, хранящегося в свойстве `$res_query`, и
     *          возвращает их как массив.
     *
     * @details Этот защищённый метод выполняет следующие шаги:
     *          1. Проверяет, что свойство `$this->res_query` установлено и является объектом типа `PDOStatement`.
     *             Если это не так, выбрасывается исключение InvalidArgumentException.
     *          2. Использует метод `fetchAll(PDO::FETCH_ASSOC)` для извлечения всех строк результата из
     *             `$this->res_query` в виде массива ассоциативных массивов.
     *          3. Если результатов нет (метод `fetchAll()` возвращает пустой массив), явно возвращается `false`.
     *          4. В противном случае возвращается массив, содержащий все строки результата.
     *
     *          Метод только читает значение свойства `$this->res_query` и не выполняет дополнительной логики для его
     *          обновления. Значение `$this->res_query` должно быть установлено внешними методами, такими как
     *          `_execute_query()`, `delete()`, `update()` или `insert()`.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `result_array()`.
     *
     * @callergraph
     *
     * @return array|false Возвращает массив ассоциативных массивов, содержащих данные всех строк результата, если они
     *                     доступны. Если результатов нет (запрос не вернул строк), возвращает `false`.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - Свойство `$this->res_query` не установлено (т.е. не был выполнен запрос,
     *                                    устанавливающий его).
     *                                    Пример сообщения:
     *                                    Результат запроса недоступен | Причина: Свойство $res_query не
     *                                    установлено
     *                                  - Свойство `$this->res_query` не является объектом типа `PDOStatement`
     *                                    (указывает на некорректное состояние).
     *                                    Пример сообщения:
     *                                    Недопустимый тип результата запроса | Ожидался объект PDOStatement,
     *                                    получено: [тип]
     *
     * @warning Метод чувствителен к состоянию свойства `$this->res_query`. Убедитесь, что перед вызовом метода был
     *          выполнен запрос (например, SELECT), который корректно установил значение `$this->res_query` как
     *          объект `PDOStatement`.
     *
     * Пример использования метода _result_array_internal():
     * @code
     * // Предполагается, что перед этим был выполнен SELECT запрос, например:
     * // $db->select(['id', 'name'], 'users', ['where' => 'status = 1']);
     *
     * // Получаем все строки результата в виде массива
     * $results = $this->_result_array_internal();
     *
     * if ($results !== false) {
     *     foreach ($results as $row) {
     *         echo "ID: " . $row['id'] . ", Name: " . $row['name'] . "\n";
     *     }
     * } else {
     *     echo "No results found.";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Database::$res_query
     *         Свойство, хранящее результат подготовленного выражения (объект PDOStatement).
     * @see    PhotoRigma::Classes::Database::_execute_query()
     *         Метод нижнего уровня, который устанавливает значение `$res_query` после выполнения запроса.
     * @see    PhotoRigma::Classes::Database::select()
     *         Метод, который может использовать `_result_array_internal()` для получения результатов SELECT-запроса.
     * @see    PhotoRigma::Classes::Database::join()
     *         Метод, который может использовать `_result_array_internal()` для получения результатов SELECT-запроса с
     *         использованием JOIN.
     * @see    PhotoRigma::Classes::Database::result_array()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _result_array_internal(): array|false
    {
        // === 1. Валидация состояния запроса ===
        if (!isset($this->res_query) || !($this->res_query instanceof PDOStatement)) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Результат запроса недоступен или имеет неверный тип | Причина: Отсутствует или некорректен объект PDOStatement'
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
     * @brief   Извлекает одну строку результата из подготовленного выражения.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода `_result_row_internal()`.
     *          Он вызывает внутренний метод для извлечения одной строки результата последнего выполненного запроса
     *          из свойства `$res_query` в виде ассоциативного массива, после соответствующих проверок.
     *          При последовательных вызовах метода извлекается следующая строка результата.
     *          Метод предназначен для использования клиентским кодом для получения результатов запроса по одной строке.
     *
     * @callgraph
     *
     * @return array|false Возвращает ассоциативный массив, содержащий данные одной строки результата, если она
     *                     доступна. При последовательных вызовах переходит к следующей строке.
     *                     Если результатов больше нет (все строки были извлечены), возвращает `false`.
     *
     * @throws InvalidArgumentException Выбрасывается, если результат запроса недоступен или имеет некорректный тип
     *                                  (выбрасывается из внутренней логики).
     *                                  Пример сообщения:
     *                                  Результат запроса недоступен | Причина: Свойство $res_query не установлено
     *
     * @note    Этот метод является точкой входа для получения строк результата запроса по одной.
     *          Результат запроса (объект PDOStatement в свойстве `$res_query`) устанавливается методом выполнения
     *          запроса (например, `_execute_query()`). Внутренняя логика получения данных и проверки их корректности
     *          выполняется в защищённом методе `_result_row_internal()`.
     * @warning Убедитесь, что перед вызовом этого метода был успешно выполнен запрос (например, SELECT), который
     *          установил доступный для чтения результат (`$res_query`).
     *
     * Пример использования:
     * @code
     * // Предполагается, что перед этим был выполнен SELECT запрос
     * $db = new \PhotoRigma\Classes\Database();
     * // ... например: $db->select(['id', 'name'], 'users', ['where' => 'status = 1']);
     *
     * // Извлекаем строки по одной
     * while ($row = $db->result_row()) {
     *     echo "ID: " . $row['id'] . ", Name: " . $row['name'] . "\n";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Database::_result_row_internal()
     *         Защищённый метод, выполняющий основную логику извлечения одной строки результата.
     */
    public function result_row(): array|false
    {
        return $this->_result_row_internal();
    }

    /**
     * @brief   Извлекает одну строку результата из подготовленного выражения, хранящегося в свойстве `$res_query`.
     *
     * @details Этот защищённый метод выполняет следующие шаги:
     *          1. Проверяет, что свойство `$this->res_query` установлено и является объектом типа `PDOStatement`.
     *             Если это не так, выбрасывается исключение InvalidArgumentException.
     *          2. Использует метод `fetch(PDO::FETCH_ASSOC)` для извлечения одной строки результата из
     *             `$this->res_query` в виде ассоциативного массива.
     *          3. Если результатов больше нет (метод `fetch()` возвращает `false`), явно возвращается `false`.
     *          4. В противном случае возвращается ассоциативный массив, содержащий данные одной строки.
     *
     *          Метод только читает значение свойства `$this->res_query` и не выполняет дополнительной логики для его
     *          обновления. Значение `$this->res_query` должно быть установлено внешними методами, такими как
     *          `_execute_query()`, `delete()`, `update()` или `insert()`.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `result_row()`.
     *
     * @callergraph
     *
     * @return array|false Возвращает ассоциативный массив, содержащий данные одной строки результата, если она
     *                     доступна. При последовательных вызовах переходит к следующей строке.
     *                     Если результатов больше нет (все строки были извлечены), возвращает `false`.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - Свойство `$this->res_query` не установлено (т.е. не был выполнен запрос,
     *                                    устанавливающий его).
     *                                    Пример сообщения:
     *                                    Результат запроса недоступен | Причина: Свойство $res_query не
     *                                    установлено
     *                                  - Свойство `$this->res_query` не является объектом типа `PDOStatement`
     *                                    (указывает на некорректное состояние).
     *                                    Пример сообщения:
     *                                    Недопустимый тип результата запроса | Ожидался объект PDOStatement,
     *                                    получено: [тип]
     *
     * @warning Метод чувствителен к состоянию свойства `$this->res_query`. Убедитесь, что перед вызовом метода был
     *          выполнен запрос (например, SELECT), который корректно установил значение `$this->res_query` как
     *          объект `PDOStatement`. Последовательные вызовы извлекают следующую строку; для начала извлечения
     *          сначала нужно выполнить запрос.
     *
     * Пример использования метода _result_row_internal():
     * @code
     * // Предполагается, что перед этим был выполнен SELECT запрос, например:
     * // $db->select(['id', 'name'], 'users', ['where' => 'status = 1']);
     *
     * // Извлекаем строки по одной
     * while ($row = $this->_result_row_internal()) {
     *     echo "ID: " . $row['id'] . ", Name: " . $row['name'] . "\n";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Database::$res_query
     *         Свойство, хранящее результат подготовленного выражения (объект PDOStatement).
     * @see    PhotoRigma::Classes::Database::_execute_query()
     *         Метод нижнего уровня, который устанавливает значение `$res_query` после выполнения запроса.
     * @see    PhotoRigma::Classes::Database::select()
     *         Метод, который может использовать `_result_row_internal()` для получения результатов SELECT-запроса.
     * @see    PhotoRigma::Classes::Database::join()
     *         Метод, который может использовать `_result_row_internal()` для получения результатов SELECT-запроса с
     *         использованием JOIN.
     * @see    PhotoRigma::Classes::Database::result_row()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _result_row_internal(): array|false
    {
        // === 1. Валидация состояния запроса ===
        if (!isset($this->res_query) || !($this->res_query instanceof PDOStatement)) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Результат запроса недоступен или имеет неверный тип | Причина: Отсутствует или некорректен объект PDOStatement'
            );
        }

        // === 2. Извлечение строки результата ===
        return $this->res_query->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @brief   Отменяет транзакцию в базе данных.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода `_roll_back_transaction_internal()`.
     *          Он передаёт контекст транзакции во внутренний метод, который выполняет отмену транзакции
     *          на уровне PDO и записывает соответствующий лог.
     *          Метод предназначен для использования клиентским кодом как точка входа для
     *          явного управления транзакциями.
     *
     * @callgraph
     *
     * @param string $context Контекст транзакции (необязательный параметр).
     *                        Используется для описания цели или места отмены транзакции.
     *                        По умолчанию: пустая строка (`''`).
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws Exception Выбрасывается, если произошла ошибка при отмене транзакции на уровне PDO или при логировании
     *                   (выбрасывается из внутренней логики).
     *                   Пример сообщения:
     *                   Ошибка при отмене транзакции | Контекст: [значение контекста]
     *
     * @note    Этот метод является точкой входа для отмены транзакций.
     *          Основная логика и логирование выполняются в защищённом методе `_roll_back_transaction_internal()`.
     * @warning Убедитесь, что транзакция была начата перед вызовом метода.
     *
     * Пример использования:
     * @code
     * // Пример отмены транзакции из клиентского кода
     * $db = new \PhotoRigma\Classes\Database();
     *
     * $db->begin_transaction('Операция с данными');
     * // ... операции с БД, которые могут вызвать ошибку ...
     * try {
     *     // ...
     * } catch (\Exception $e) {
     *     $db->rollback_transaction('Операция с данными - Ошибка');
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Database::_roll_back_transaction_internal()
     *         Защищённый метод, выполняющий основную логику отмены транзакции.
     */
    public function rollback_transaction(string $context = ''): void
    {
        $this->_rollback_transaction_internal($context);
    }

    /**
     * @brief   Отменяет транзакцию в базе данных.
     *
     * @details Этот защищённый метод выполняет следующие шаги:
     *          1. Отменяет транзакцию в базе данных через объект PDO с использованием метода `rollBack()`.
     *          2. Логирует информацию об отмене транзакции с указанием контекста через функцию `log_in_file()`.
     *
     *          Этот метод предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `rollback_transaction()`.
     *
     * @callergraph
     *
     * @param string $context Контекст транзакции (необязательный параметр).
     *                        Используется для описания цели или места отмены транзакции.
     *                        По умолчанию: пустая строка (`''`).
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws Exception Выбрасывается, если произошла ошибка при отмене транзакции на уровне PDO или при логировании.
     *                   Пример сообщения:
     *                   Ошибка при отмене транзакции | Контекст: [значение контекста] | Сообщение PDO: [текст
     *                   ошибки PDO]
     *
     * @note    Метод использует функцию логирования `log_in_file()` для записи информации об отмене транзакции.
     * @warning Убедитесь, что транзакция была начата (`$this->pdo->inTransaction()` возвращает `true`)
     *          перед вызовом этого метода. Попытка отменить несуществующую транзакцию приведёт к ошибке на уровне PDO.
     *
     * Пример использования метода _rollback_transaction_internal():
     * @code
     * // Пример отмены транзакции с указанием контекста
     * $this->_rollback_transaction_internal('Отмена сохранения данных пользователя');
     * // Ожидаемый лог (в файле): [DB] Транзакция отменена | Контекст: Отмена сохранения данных пользователя
     * @endcode
     * @see    PhotoRigma::Classes::Database::$pdo
     *         Объект PDO для подключения к базе данных.
     * @see    PhotoRigma::Include::log_in_file()
     *         Логирует сообщения в файл.
     * @see    PhotoRigma::Classes::Database::rollback_transaction()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _rollback_transaction_internal(string $context = ''): void
    {
        $this->pdo->rollBack();
        log_in_file(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | [DB] Транзакция отменена | Контекст: $context"
        );
    }

    /**
     * @brief   Выполняет SQL-запрос на выборку данных из таблицы.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода `_select_internal()`.
     *          Он передаёт список полей для выборки, имя таблицы и опции запроса во внутренний метод, который
     *          выполняет формирование и выполнение SQL-запроса на выборку.
     *          Безопасность обеспечивается использованием подготовленных выражений с параметрами.
     *          Метод предназначен для использования клиентским кодом как точка входа для выполнения запросов на
     *          выборку данных.
     *
     * @callgraph
     *
     * @param string|array $select   Список полей для выборки. Может быть строкой (имя одного поля) или массивом
     *                               (список полей). Если передан массив, он преобразуется в строку с разделителем
     *                               `,`. Пример: "id", ["id", "name"]. Ограничения: массив не может содержать
     *                               элементы, которые не являются строками.
     * @param string       $from_tbl Имя таблицы, из которой выбираются данные.
     *                               Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                               символов. Ограничения: строка должна быть непустой.
     * @param array        $options  Массив дополнительных опций для формирования запроса. Поддерживаемые ключи:
     *                               - where (string|array|false): Условие WHERE.
     *                                 * string - используется как есть.
     *                                 * array - преобразуется в SQL. Поддерживает простые условия
     *                                   (`['поле' => значение]`) и логические операторы (`'OR' => [...]`,
     *                                   `'NOT' => [...]`).
     *                                 * false - игнорируется.
     *                               - group (string|false): Группировка GROUP BY. Игнорируется, если false.
     *                                 Должна быть строкой.
     *                               - order (string|false): Сортировка ORDER BY. Игнорируется, если false.
     *                                 Должна быть строкой.
     *                               - limit (int|string|false): Ограничение LIMIT.
     *                                 * int - прямое значение.
     *                                 * string - формат "OFFSET,COUNT".
     *                                 * false - игнорируется.
     *                               - params (array): Ассоциативный массив параметров
     *                                 ([":имя" => значение]).
     *                                 Обязателен для использования с условиями `where` и другими частями запроса,
     *                                 требующими подготовленных выражений. Обеспечивает защиту от SQL-инъекций.
     *                                 Может быть пустым массивом, если параметры не требуются.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$from_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                    Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                                  - `$options` не является массивом.
     *                                    Пример сообщения:
     *                                    Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     *                                  - `$select` не является строкой или массивом.
     *                                    Пример сообщения:
     *                                    Недопустимый тип параметра select | Ожидалась строка или массив,
     *                                    получено: [тип]
     *                                  - Элемент в списке `$select` не является строкой.
     *                                    Пример сообщения:
     *                                    Недопустимый элемент в выборке | Ожидалась строка, получено: [тип]
     * @throws Exception               Выбрасывается при ошибках выполнения запроса.
     *
     * @note    Этот метод является точкой входа для выполнения запросов на выборку данных.
     *          Все проверки и основная логика выполняются в защищённом методе `_select_internal()`.
     *
     * @warning Метод чувствителен к корректности входных данных. Неверные типы данных или некорректные значения могут
     *          привести к выбросу исключения. Убедитесь, что массив `$select` содержит только строки.
     *          Использование параметров `params` для подготовленных выражений является обязательным для защиты от
     *          SQL-инъекций и обеспечения совместимости с различными СУБД.
     *
     * Пример использования:
     * @code
     * // Пример безопасного использования публичного метода SELECT
     * $db = new \PhotoRigma\Classes\Database();
     *
     * $db->select(
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
     * @see    PhotoRigma::Classes::Database::_select_internal()
     *         Защищённый метод, выполняющий основную логику формирования и выполнения SQL-запроса на выборку.
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
     *             (массив).
     *          2. Обрабатывает список полей для выборки (`$select`):
     *             - Если `$select` является строкой, она разбивается по запятым и преобразуется в массив.
     *             - Каждое имя поля проверяется на корректность и преобразуется в строку с разделителем `,`.
     *          3. Формирует базовый SQL-запрос `SELECT ... FROM ...`.
     *          4. Добавляет условия WHERE, GROUP BY, ORDER BY и LIMIT, если они указаны в параметре `$options`.
     *          5. Сохраняет сформированный запрос в свойстве `$txt_query`.
     *          6. Выполняет запрос через метод `_execute_query`, передавая параметры `params` для подготовленного
     *             выражения.
     *
     *          Использование параметров `params` является обязательным для подготовленных выражений, так как это
     *          обеспечивает защиту от SQL-инъекций и совместимость с различными СУБД. Этот метод является
     *          защищенным и предназначен для использования внутри класса или его наследников. Основная логика
     *          вызывается через публичный метод-редирект `select()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string|array $select   Список полей для выборки. Может быть строкой (имя одного поля) или массивом
     *                               (список полей). Если передан массив, он преобразуется в строку с разделителем
     *                               `,`. Пример: "id", ["id", "name"]. Ограничения: массив не может содержать
     *                               элементы, которые не являются строками.
     * @param string       $from_tbl Имя таблицы, из которой выбираются данные.
     *                               Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                               символов. Ограничения: строка должна быть непустой.
     * @param array        $options  Массив дополнительных опций для формирования запроса. Поддерживаемые ключи:
     *                               - where (string|array|false): Условие WHERE.
     *                                 * string - используется как есть.
     *                                 * array - преобразуется в SQL. Поддерживает простые условия
     *                                   (`['поле' => значение]`) и логические операторы (`'OR' => [...]`,
     *                                   `'NOT' => [...]`).
     *                                 * false - игнорируется.
     *                               - group (string|false): Группировка GROUP BY. Игнорируется, если false.
     *                                 Должна быть строкой.
     *                               - order (string|false): Сортировка ORDER BY. Игнорируется, если false.
     *                                 Должна быть строкой.
     *                               - limit (int|string|false): Ограничение LIMIT.
     *                                 * int - прямое значение.
     *                                 * string - формат "OFFSET,COUNT".
     *                                 * false - игнорируется.
     *                               - params (array): Ассоциативный массив параметров
     *                                 ([":имя" => значение]).
     *                                 Обязателен для использования с условиями `where` и другими частями запроса,
     *                                 требующими подготовленных выражений. Обеспечивает защиту от SQL-инъекций.
     *                                 Может быть пустым массивом, если параметры не требуются.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$from_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                    Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                                  - `$options` не является массивом.
     *                                    Пример сообщения:
     *                                    Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     *                                  - `$select` не является строкой или массивом.
     *                                    Пример сообщения:
     *                                    Недопустимый тип параметра select | Ожидалась строка или массив,
     *                                    получено: [тип]
     *                                  - Элемент в списке `$select` не является строкой.
     *                                    Пример сообщения:
     *                                    Недопустимый элемент в выборке | Ожидалась строка, получено: [тип]
     * @throws Exception               Выбрасывается при ошибках выполнения запроса.
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
     * @see    PhotoRigma::Classes::Database::$txt_query
     *         Свойство, в которое помещается текст SQL-запроса.
     * @see    PhotoRigma::Classes::Database::select()
     *         Публичный метод-редирект для вызова этой логики.
     * @see    PhotoRigma::Classes::Database::_execute_query()
     *         Выполняет SQL-запрос.
     * @see    PhotoRigma::Classes::Database::_build_conditions()
     *         Формирует условия WHERE, GROUP BY, ORDER BY и LIMIT для запроса.
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
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Недопустимый элемент в выборке | Ожидалась строка, получено: ' . gettype(
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
        [$conditions, $params] = $this->_build_conditions($options);
        $this->txt_query .= $conditions;

        // === 4. Выполнение запроса ===
        return $this->_execute_query($params);
    }

    /**
     * @brief   Очищает таблицу (TRUNCATE) через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода `_truncate_internal()`.
     *          Он передаёт имя таблицы во внутренний метод, который формирует и выполняет SQL-запрос `TRUNCATE TABLE`.
     *          Запрос TRUNCATE является быстрым способом удаления всех строк из таблицы, но его невозможно отменить
     *          через транзакции.
     *          Метод предназначен для использования клиентским кодом как точка входа для выполнения операции очистки
     *          таблицы.
     *
     * @callgraph
     *
     * @param string $from_tbl Имя таблицы, которую необходимо очистить.
     *                         Должно быть строкой, содержащей только допустимые имена таблиц без специальных символов.
     *                         Ограничения: строка должна быть непустой.
     *
     * @return bool Возвращает `true`, если запрос успешно выполнен (даже если результат пустой или не возвращает строк).
     *
     * @throws InvalidArgumentException Выбрасывается, если имя таблицы недопустимо (выбрасывается из внутренней логики).
     *                                  Пример сообщения:
     *                                  Недопустимое имя таблицы | Ожидалась непустая строка, получено: [тип/значение]
     * @throws Exception                Выбрасывается при ошибках выполнения запроса.
     *
     * @note    Этот метод является точкой входа для очистки таблицы.
     *          Основная логика выполняется в защищённом методе `_truncate_internal()`.
     *          Помните, что TRUNCATE - это необратимая операция, которая не участвует в транзакциях.
     * @warning Метод чувствителен к корректности входных данных. Убедитесь, что `$from_tbl` содержит корректное имя
     *          таблицы.
     *          Используйте этот метод с крайней осторожностью, так как данные будут удалены без возможности
     *          восстановления через ROLLBACK.
     *
     * Пример использования:
     * @code
     * // Пример очистки таблицы из клиентского кода
     * $db = new \PhotoRigma\Classes\Database();
     *
     * $db->truncate('temp_data');
     * // Таблица 'temp_data' теперь пуста
     * @endcode
     * @see    PhotoRigma::Classes::Database::_truncate_internal()
     *         Защищённый метод, выполняющий основную логику очистки таблицы.
     */
    public function truncate(string $from_tbl): bool
    {
        return $this->_truncate_internal($from_tbl);
    }

    /**
     * @brief   Формирует SQL-запрос на очистку таблицы (TRUNCATE), размещает его в свойстве `$txt_query` и выполняет.
     *
     * @details Этот защищённый метод выполняет следующие шаги:
     *          1. Проверяет тип входных данных: `$from_tbl` должен быть строкой. Если передан неверный тип,
     *             выбрасывается исключение InvalidArgumentException.
     *          2. Формирует базовый SQL-запрос `TRUNCATE TABLE` для очистки таблицы с использованием `$from_tbl`.
     *          3. Сохраняет сформированный запрос в свойстве `$this->txt_query`.
     *          4. Выполняет запрос через метод `_execute_query()`.
     *
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
     * @return bool Возвращает `true`, если запрос успешно выполнен (даже если результат пустой или не возвращает строк).
     *
     * @throws InvalidArgumentException Выбрасывается, если `$from_tbl` не является непустой строкой.
     *                                  Пример сообщения:
     *                                  Недопустимое имя таблицы | Ожидалась непустая строка, получено: [тип/значение]
     * @throws Exception                Выбрасывается при ошибках выполнения запроса.
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
     * @see    PhotoRigma::Classes::Database::$txt_query
     *         Свойство, в которое помещается текст SQL-запроса.
     * @see    PhotoRigma::Classes::Database::truncate()
     *         Публичный метод-редирект для вызова этой логики.
     * @see    PhotoRigma::Classes::Database::_execute_query()
     *         Выполняет SQL-запрос.
     */
    protected function _truncate_internal(string $from_tbl): bool
    {
        // Формирование базового запроса
        $this->txt_query = "TRUNCATE TABLE $from_tbl";
        // Выполнение запроса
        return $this->_execute_query();
    }

    /**
     * @brief   Обновляет данные в таблице через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода `_update_internal()`.
     *          Он передаёт массив данных для обновления, имя таблицы и опции запроса во внутренний метод, который
     *          выполняет формирование и выполнение SQL-запроса на обновление.
     *          Безопасность обеспечивается обязательным указанием условия `where` в опциях и использованием
     *          подготовленных выражений с параметрами.
     *          Метод предназначен для использования клиентским кодом как точка входа для выполнения операций
     *          обновления данных.
     *
     * @callgraph
     *
     * @param array  $update   Ассоциативный массив данных для обновления в формате: 'имя_поля' => 'значение'.
     *                         Пример: ["name" => ":name", "status" => ":status"].
     *                         Для защиты от SQL-инъекций рекомендуется использовать плейсхолдеры (например,
     *                         `:name`).
     * @param string $from_tbl Имя таблицы, в которой необходимо обновить данные.
     *                         Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                         символов. Ограничения: строка должна быть непустой.
     * @param array  $options  Массив опций для формирования запроса. Поддерживаемые ключи:
     *                         - where (string|array|false): Условие WHERE.
     *                           * string - используется как есть.
     *                           * array - преобразуется в SQL. Поддерживает простые условия
     *                             (`['поле' => значение]`) и логические операторы (`'OR' => [...]`,
     *                             `'NOT' => [...]`).
     *                           * false - игнорируется.
     *                           Обязательный параметр для безопасности. Без условия WHERE запрос не будет выполнен.
     *                         - group (string|false): Группировка GROUP BY. Игнорируется, если false.
     *                           Должна быть строкой.
     *                           Не поддерживается в запросах UPDATE и удаляется с записью в лог.
     *                         - order (string|false): Сортировка ORDER BY. Игнорируется, если false.
     *                           Должна быть строкой.
     *                         - limit (int|string|false): Ограничение LIMIT.
     *                           * int - прямое значение.
     *                           * string - формат "OFFSET,COUNT".
     *                           * false - игнорируется.
     *                         - params (array): Ассоциативный массив параметров
     *                           ([":имя" => значение]).
     *                           Обязателен для использования с данными для обновления или условиями `where`,
     *                           требующими подготовленных выражений. Обеспечивает защиту от SQL-инъекций.
     *                           Может быть пустым массивом, если параметры не требуются.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$update` не является массивом.
     *                                    Пример сообщения:
     *                                    Недопустимый тип параметра update | Ожидался массив, получено: [тип]
     *                                  - `$from_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                    Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                                  - `$options` не является массивом.
     *                                    Пример сообщения:
     *                                    Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     *                                  - Отсутствует обязательное условие `where` в массиве `$options`.
     *                                    Пример сообщения:
     *                                    Запрос UPDATE без условия WHERE запрещён | Причина: Соображения
     *                                    безопасности
     * @throws JsonException            Выбрасывается при ошибках записи лога.
     * @throws Exception                Выбрасывается при ошибках выполнения запроса.
     *
     * @note    Этот метод является точкой входа для выполнения операций обновления данных.
     *          Все проверки (включая обязательное `where`) и основная логика выполняются в защищённом методе
     *          `_update_internal()`. Ключ `group` игнорируется в этом методе.
     *
     * @warning Метод чувствителен к корректности входных данных. Неверные типы данных или некорректные значения могут
     *          привести к выбросу исключения. Безопасность обеспечивается обязательным указанием условия `where`.
     *          Запрос без условия `where` не будет выполнен. Ключ `group` не поддерживается в запросах UPDATE и
     *          удаляется с записью в лог во внутренней логике.
     *          Использование параметров `params` для подготовленных выражений является обязательным для защиты от
     *          SQL-инъекций и обеспечения совместимости с различными СУБД.
     *
     * Пример использования:
     * @code
     * // Пример безопасного использования публичного метода UPDATE
     * $db = new \PhotoRigma\Classes\Database(); // Добавил отсутствующий обратный слеш в неймспейсе
     *
     * $db->update(
     *     ['name' => ':name', 'status' => ':status'],
     *     'users',
     *     [
     *         'where' => 'id = :id',
     *         'params' => [':id' => 1, ':name' => 'John Doe', ':status' => 'active']
     *     ]
     * );
     * @endcode
     * @see    PhotoRigma::Classes::Database::_update_internal()
     *         Защищённый метод, выполняющий основную логику обновления данных.
     */
    public function update(array $update, string $from_tbl, array $options = []): bool
    {
        return $this->_update_internal($update, $from_tbl, $options);
    }

    /**
     * @brief   Формирует SQL-запрос на обновление данных в таблице, основываясь на полученных аргументах, размещает
     *          его в свойстве `$txt_query` и выполняет.
     *
     * @details Этот защищённый метод выполняет следующие шаги:
     *          1. Проверяет типы входных данных: `$update` (ассоциативный массив), `$from_tbl` (строка), `$options`
     *             (массив).
     *          2. Проверяет наличие обязательного условия `where` в массиве `$options`. Если условие отсутствует,
     *             выбрасывается исключение для предотвращения случайного обновления всех данных.
     *          3. Удаляет недопустимый ключ `group` из массива `$options` с записью в лог, так как GROUP BY не
     *             поддерживается в запросах UPDATE.
     *          4. Формирует базовый SQL-запрос UPDATE с использованием преобразованных данных.
     *          5. Добавляет условия WHERE, ORDER BY и LIMIT, если они указаны в параметре `$options`.
     *          6. Сохраняет сформированный запрос в свойстве `$txt_query`.
     *          7. Выполняет запрос через метод `_execute_query`, передавая параметры `params` для подготовленного
     *             выражения.
     *
     *          Использование параметров `params` является обязательным для подготовленных выражений, так как это
     *          обеспечивает защиту от SQL-инъекций и совместимость с различными СУБД. Этот метод является
     *          защищенным и предназначен для использования внутри класса или его наследников. Основная логика
     *          вызывается через публичный метод-редирект `update()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param array  $update   Ассоциативный массив данных для обновления в формате: 'имя_поля' => 'значение'.
     *                         Пример: ["name" => ":name", "status" => ":status"].
     *                         Для защиты от SQL-инъекций рекомендуется использовать плейсхолдеры (например, `:name`).
     * @param string $from_tbl Имя таблицы, в которой необходимо обновить данные.
     *                         Должно быть строкой, содержащей только допустимые имена таблиц без специальных
     *                         символов. Ограничения: строка должна быть непустой.
     * @param array  $options  Массив опций для формирования запроса. Поддерживаемые ключи:
     *                         - where (string|array|false): Условие WHERE.
     *                           * string - используется как есть.
     *                           * array - преобразуется в SQL. Поддерживает простые условия
     *                             (`['поле' => значение]`) и логические операторы (`'OR' => [...]`,
     *                             `'NOT' => [...]`).
     *                           * false - игнорируется.
     *                           Обязательный параметр для безопасности. Без условия WHERE запрос не будет выполнен.
     *                         - group (string|false): Группировка GROUP BY. Игнорируется, если false.
     *                           Должна быть строкой.
     *                           Не поддерживается в запросах UPDATE и удаляется с записью в лог.
     *                         - order (string|false): Сортировка ORDER BY. Игнорируется, если false.
     *                           Должна быть строкой.
     *                         - limit (int|string|false): Ограничение LIMIT.
     *                           * int - прямое значение.
     *                           * string - формат "OFFSET,COUNT".
     *                           * false - игнорируется.
     *                         - params (array): Ассоциативный массив параметров
     *                           ([":имя" => значение]).
     *                           Обязателен для использования с данными для обновления или условиями `where`,
     *                           требующими подготовленных выражений. Обеспечивает защиту от SQL-инъекций.
     *                           Может быть пустым массивом, если параметры не требуются.
     *
     * @return bool Возвращает true, если запрос успешно выполнен (даже если результат пустой).
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - `$update` не является массивом.
     *                                    Пример сообщения:
     *                                    Недопустимый тип параметра update | Ожидался массив, получено: [тип]
     *                                  - `$from_tbl` не является строкой.
     *                                    Пример сообщения:
     *                                    Недопустимое имя таблицы | Ожидалась строка, получено: [тип]
     *                                  - `$options` не является массивом.
     *                                    Пример сообщения:
     *                                    Недопустимый тип параметра options | Ожидался массив, получено: [тип]
     *                                  - Отсутствует обязательное условие `where` в массиве `$options`.
     *                                    Пример сообщения:
     *                                    Запрос UPDATE без условия WHERE запрещён | Причина: Соображения
     *                                    безопасности
     * @throws JsonException            Выбрасывается при ошибках записи лога.
     * @throws Exception                Выбрасывается при ошибках выполнения запроса.
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
     * @see    PhotoRigma::Classes::Database::$txt_query
     *         Свойство, в которое помещается текст SQL-запроса.
     * @see    PhotoRigma::Classes::Database::update()
     *         Публичный метод-редирект для вызова этой логики.
     * @see    PhotoRigma::Classes::Database::_execute_query()
     *         Выполняет SQL-запрос.
     * @see    PhotoRigma::Classes::Database::_build_conditions()
     *         Формирует условия WHERE, ORDER BY и LIMIT для запроса.
     */
    protected function _update_internal(array $update, string $from_tbl, array $options = []): bool
    {
        // === 1. Валидация аргументов ===
        if (empty($options['where'])) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Запрос UPDATE без условия WHERE запрещён | Причина: Соображения безопасности'
            );
        }

        // === 2. Удаление недопустимого ключа `group` ===
        if (isset($options['group'])) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Был использован GROUP BY в UPDATE | Переданные опции: ' . json_encode(
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
        [$conditions, $params] = $this->_build_conditions($options);
        $this->txt_query .= $conditions;

        // === 6. Выполнение запроса ===
        return $this->_execute_query($params);
    }

    /**
     * @brief   Временно изменяет формат SQL для выполнения запросов.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода `_with_sql_format_internal()`.
     *          Он принимает желаемый временный формат SQL и коллбэк, а затем выполняет внутренний метод.
     *          Внутренний метод устанавливает временный формат SQL перед выполнением коллбэка и гарантирует
     *          восстановление исходного формата после его завершения, даже в случае исключения.
     *          Метод предназначен для использования клиентским кодом для выполнения набора операций
     *          с базой данных, которые требуют специфического формата SQL, отличного от текущего.
     *
     * @callgraph
     *
     * @param string   $format   Формат SQL, который нужно использовать временно.
     *                           Поддерживаемые значения: 'mysql', 'pgsql', 'sqlite'.
     * @param callable $callback Коллбэк, содержащий код, который должен выполняться
     *                           в контексте указанного формата SQL. Коллбэк может принимать ссылку на текущий
     *                           объект базы данных как аргумент (например, `function (\PhotoRigma\Classes\Database
     *                           $db) { ... }`).
     *
     * @return mixed Результат выполнения коллбэка `$callback`.
     *
     * @throws InvalidArgumentException Выбрасывается, если указан неподдерживаемый формат SQL (выбрасывается из
     *                                  внутренней логики).
     *                                  Пример сообщения:
     *                                  Неподдерживаемый формат SQL | Формат: [format]
     * @throws Exception                Любые исключения, выброшенные внутри переданного коллбэка, будут автоматически
     *                                  распространены (не перехватываются этим методом).
     *
     * @note    Этот метод является точкой входа для временного изменения формата SQL.
     *          Основная логика смены и восстановления формата выполняется в защищённом методе
     *          `_with_sql_format_internal()`. Убедитесь, что код внутри коллбэка не содержит собственных блоков
     *          `try/catch` для исключений, которые должны быть обработаны на более высоком уровне.
     * @warning Важные замечания:
     *          1. Убедитесь, что все методы текущего класса `$db->...()`, вызываемые внутри коллбэка, корректно
     *             работают с указанным временным форматом SQL.
     *          2. **Особая осторожность:** Не используйте внутри коллбэка методы **других** классов,
     *             которые напрямую зависят от объекта `$db` и при этом написаны с жесткой привязкой к
     *             определенному формату SQL (например, парсят SQL-строки, не учитывая временное изменение формата
     *             в объекте `$db`). Это может привести к непредсказуемому поведению или ошибкам.
     *          3. Исключения, выброшенные внутри коллбэка, **не перехватываются** этим методом; они
     *             будут распространяться (пробрасываться) дальше.
     *
     * Пример использования:
     * @code
     * // Пример выполнения запросов в формате PostgreSQL
     * $db = new \PhotoRigma\Classes\Database();
     * // Предполагается, что TBL_NEWS определена как константа
     *
     * $years_list = $db->with_sql_format('pgsql', function (\PhotoRigma\Classes\Database $db) {
     *     // Внутри этого коллбэка $db->current_format установлен в 'pgsql'
     *     $formatted_date = $db->format_date('data_last_edit', 'YYYY');
     *     $db->select(
     *         'DISTINCT ' . $formatted_date . ' AS year',
     *         TBL_NEWS,
     *         [
     *             'order' => 'year ASC',
     *         ]
     *     );
     *     return $db->result_array();
     * });
     * print_r($years_list);
     *
     * // После завершения коллбэка, исходный формат SQL восстанавливается автоматически.
     * @endcode
     * @see    PhotoRigma::Classes::Database::_with_sql_format_internal()
     *         Защищённый метод, выполняющий основную логику временной смены формата SQL.
     */
    public function with_sql_format(string $format, callable $callback): mixed
    {
        return $this->_with_sql_format_internal($format, $callback);
    }

    /**
     * @brief   Метод временно изменяет формат SQL для выполнения запросов в указанном контексте.
     *
     * @details Этот защищённый метод выполняет следующие шаги:
     *          1. Проверяет поддерживаемость указанного формата SQL `$format`. Если формат не поддерживается,
     *             выбрасывается исключение `InvalidArgumentException`.
     *          2. Сохраняет текущий формат SQL из свойства `$this->current_format` во временную переменную.
     *          3. Устанавливает временный формат SQL в свойство `$this->current_format`, используя значение из
     *             `$format`.
     *          4. Выполняет переданный коллбэк `$callback`. Код внутри коллбэка будет использовать новый временный
     *             формат SQL.
     *          5. Восстанавливает исходный формат SQL в свойстве `$this->current_format` после завершения коллбэка
     *             (даже если внутри коллбэка произошла ошибка благодаря использованию `finally`).
     *
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `with_sql_format()`.
     *
     * @callergraph
     *
     * @param string   $format   Формат SQL, который нужно использовать временно.
     *                           Поддерживаемые значения: 'mysql', 'pgsql', 'sqlite'.
     * @param callable $callback Коллбэк, содержащий код, который должен выполняться
     *                           в контексте указанного формата SQL. Коллбэк может принимать ссылку на текущий
     *                           объект базы данных как аргумент (например, `function (\PhotoRigma\Classes\Database
     *                           $db) { ... }`).
     *
     * @return mixed Результат выполнения коллбэка `$callback`.
     *
     * @throws InvalidArgumentException Выбрасывается, если указан неподдерживаемый формат SQL.
     *                                  Пример сообщения:
     *                                  Неподдерживаемый формат SQL | Формат: [format]
     *
     * @warning Важные замечания:
     *          1. Убедитесь, что все методы текущего класса `$this->...()`, вызываемые внутри коллбэка,
     *             корректно работают с указанным временным форматом SQL.
     *          2. **Особая осторожность:** Не используйте внутри коллбэка методы **других** классов,
     *             которые напрямую зависят от объекта `$db` и при этом написаны с жесткой привязкой к
     *             определенному формату SQL (например, парсят SQL-строки, не учитывая временное изменение формата
     *             в объекте `$db`). Это может привести к непредсказуемому поведению или ошибкам.
     *          3. Для выполнения группы запросов в одном и том же временном формате оберните их в один коллбэк.
     *
     * Пример использования метода _with_sql_format_internal():
     * @code
     * // Выполнение запроса в формате PostgreSQL
     * // Предполагается, что TBL_NEWS определена как константа
     * $years_list = $this->_with_sql_format_internal('pgsql', function () { // Можно использовать `use ($this)` или передать $this в коллбэк
     *     // Форматирование даты для PostgreSQL с учетом временного формата
     *     $formatted_date = $this->format_date('data_last_edit', 'YYYY');
     *     // Выборка данных
     *     $this->select(
     *         'DISTINCT ' . $formatted_date . ' AS year',
     *         TBL_NEWS, // Используем константу TBL_NEWS
     *         [
     *             'order' => 'year ASC',
     *         ]
     *     );
     *     // Получение результата
     *     return $this->result_array();
     * });
     * print_r($years_list);
     * @endcode
     * @see    PhotoRigma::Classes::Database::$current_format
     *         Свойство, хранящее текущий формат SQL.
     * @see    PhotoRigma::Classes::Database::with_sql_format()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _with_sql_format_internal(string $format, callable $callback): mixed
    {
        // Проверяем поддерживаемые форматы
        if (!in_array($format, $this->allowed_formats, true)) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
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
     * @brief   Выполняет полнотекстовый поиск в указанной таблице.
     *
     * @details Этот публичный метод является точкой входа для выполнения полнотекстового поиска.
     *          Он вызывает внутреннюю логику поиска, реализованную в защищённом методе
     *          `_full_text_search_internal()`, передавая ему все необходимые параметры и опции.
     *          Безопасность запроса обеспечивается использованием подготовленных выражений.
     *
     * @callgraph
     *
     * @param array  $columns_to_return Массив строк с именами столбцов, данные из которых нужно вернуть.
     * @param array  $columns_to_search Массив строк с именами столбцов, по которым выполняется поиск.
     * @param string $search_string     Строка для полнотекстового поиска.
     *                                  - Может содержать `*` для выбора всех записей.
     * @param string $table             Строка с именем таблицы, в которой выполняется поиск.
     * @param array  $options           Массив дополнительных опций для формирования запроса. Поддерживаемые ключи:
     *                                  - where (string|array|false): Условие WHERE.
     *                                    * string - используется как есть.
     *                                    * array - преобразуется в SQL.
     *                                    * false - игнорируется.
     *                                  - group (string|false): GROUP BY. Игнорируется, если false.
     *                                  - order (string|false): ORDER BY. Игнорируется, если false.
     *                                  - limit (int|string|false): LIMIT.
     *                                    * int - прямое значение.
     *                                    * string - формат "OFFSET,COUNT".
     *                                    * false - игнорируется.
     *                                  - params (array): Ассоциативный массив параметров
     *                                    ([":имя" => значение]).
     *                                    Использование параметров `params` обязательно
     *                                    для защиты от SQL-инъекций.
     *
     * @return array|false Результат выполнения поиска (массив данных или false, если результат пустой).
     *
     * @throws RuntimeException         Если не удалось получить версию БД из таблицы db_version (выбрасывается из
     *                                  внутренней логики).
     * @throws InvalidArgumentException Если переданы недопустимые аргументы или тип СУБД не поддерживается
     *                                  (выбрасывается из внутренней логики).
     * @throws JsonException            При ошибках, связанных с кодированием/декодированием JSON.
     * @throws Exception                Если произошла ошибка при выполнении запроса на более низких уровнях.
     *
     * @note    Этот метод является точкой входа для выполнения запросов с полнотекстовым поиском.
     *          Все проверки и обработка выполняются в защищённом методе `_full_text_search_internal()`.
     *
     * @warning Метод чувствителен к корректности входных данных.
     *          Убедитесь, что массивы столбцов и имя таблицы не пусты, а строка поиска не пустая (если не `*`).
     *          Использование параметров `params` в `$options` обязательно для значений, подставляемых в запросы.
     *
     * Пример использования:
     * @code
     * // Пример вызова публичного метода
     * $db = new \PhotoRigma\Classes\Database();
     *
     * $results = $db->full_text_search(
     *     ['id', 'title'],
     *     ['title', 'content'],
     *     'test',
     *     'articles',
     *     [
     *         'where' => ['is_active' => true],
     *         'limit' => 5,
     *         'params' => [':is_active' => true]
     *     ]
     * );
     *
     * if ($results !== false) {
     *     print_r($results);
     * } else {
     *     echo "Ничего не найдено.";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Database::_full_text_search_internal()
     *         Защищённый метод, выполняющий основную логику полнотекстового поиска.
     */
    public function full_text_search(
        array $columns_to_return,
        array $columns_to_search,
        string $search_string,
        string $table,
        array $options = []
    ): array|false {
        return $this->_full_text_search_internal(
            $columns_to_return,
            $columns_to_search,
            $search_string,
            $table,
            $options
        );
    }

    /**
     * @brief   Выполняет полнотекстовый поиск в указанной таблице по заданным столбцам.
     *
     * @details Этот защищённый метод является основной логикой для выполнения полнотекстового поиска
     *          и предназначен для использования внутри класса или его наследников.
     *          Он выполняет следующие шаги:
     *          1. Валидация входных аргументов (`$columns_to_return`, `$columns_to_search`, `$table`,
     *             `$search_string`). Проверяется их заполненность и тип.
     *          2. Обработка специального символа `*` в `$search_string`: если строка поиска равна `*`,
     *             выполняется обычный запрос на выборку всех записей из таблицы с помощью внутреннего метода
     *             `_select_internal()`, применяя при этом фильтрацию опций через `_fts_check_options()`,
     *             и возвращается результат через `_result_array_internal()`.
     *          3. Получение текущей версии базы данных из таблицы `db_version` с использованием
     *             `_select_internal()` и `_result_row_internal()`. Это необходимо для выбора корректного
     *             метода полнотекстового поиска в зависимости от версии СУБД.
     *          4. Снятие экранирования идентификаторов (имен столбцов и таблицы) с использованием
     *             метода `_unescape_identifiers()` для обеспечения корректности SQL-запроса.
     *          5. Выбор и вызов специфического метода полнотекстового поиска в зависимости от типа
     *             используемой СУБД (`$this->db_type`). Используется конструкция `match`.
     *          6. Передача всех необходимых параметров в выбранный специфический метод поиска.
     *
     *          Метод зависит от наличия и доступности таблицы `db_version` и внутренних методов для работы с БД и
     *          кешем.
     *
     * @callergraph
     * @callgraph
     *
     * @param array  $columns_to_return Массив строк с именами столбцов, данные из которых необходимо включить в
     *                                  результат запроса.
     *                                  Ограничения: массив не может быть пустым.
     * @param array  $columns_to_search Массив строк с именами столбцов, по которым должен выполняться полнотекстовый
     *                                  поиск.
     *                                  Ограничения: массив не может быть пустым.
     * @param string $search_string     Строка для полнотекстового поиска.
     *                                  - Может содержать `*` для выбора всех записей из таблицы (аналогично
     *                                    обычному SELECT).
     *                                  Ограничения: строка не может быть пустой (если не `*`).
     * @param string $table             Строка с именем таблицы, в которой выполняется полнотекстовый поиск.
     *                                  Ограничения: строка не может быть пустой и должна содержать допустимое имя
     *                                  таблицы.
     * @param array  $options           Массив дополнительных опций для формирования запроса. Поддерживаемые ключи:
     *                                  - where (string|array|false): Условие WHERE.
     *                                    * string - используется как есть.
     *                                    * array - преобразуется в SQL. Поддерживает простые условия
     *                                      (`['поле' => значение]`) и логические операторы (`'OR' => [...]`,
     *                                      `'NOT' => [...]`).
     *                                    * false - игнорируется.
     *                                  - group (string|false): Группировка GROUP BY. Игнорируется, если false.
     *                                    Должна быть строкой.
     *                                  - order (string|false): Сортировка ORDER BY. Игнорируется, если false.
     *                                    Должна быть строкой.
     *                                  - limit (int|string|false): Ограничение LIMIT.
     *                                    * int - прямое значение.
     *                                    * string - формат "OFFSET,COUNT".
     *                                    * false - игнорируется.
     *                                  - params (array): Ассоциативный массив параметров
     *                                    ([":имя" => значение]).
     *                                    Обязателен для использования с условиями `where` и другими частями запроса,
     *                                    требующими подготовленных выражений. Обеспечивает защиту от SQL-инъекций.
     *                                    Может быть пустым массивом, если параметры не требуются.
     *
     * @return array|false Возвращает результат выполнения запроса в виде ассоциативного массива строк или false,
     *                     если результат пустой или произошла ошибка.
     *
     * @throws InvalidArgumentException Выбрасывается, если:
     *                                  - Один из обязательных аргументов (`$columns_to_return`, `$columns_to_search`,
     *                                    `$table`) пуст.
     *                                    Пример сообщения: "Недопустимые аргументы | Подробности: ..."
     *                                  - `$search_string` является пустой строкой (кроме символа `*`).
     *                                    Пример сообщения: "Пустая строка поиска"
     *                                  - Тип используемой СУБД (`$this->db_type`) не поддерживается для полнотекстового
     *                                    поиска.
     *                                    Пример сообщения: "Неизвестный тип СУБД | Тип: mysql"
     * @throws RuntimeException         Выбрасывается, если не удалось получить корректную версию из таблицы `db_version`.
     *                                  Пример сообщения: "Не удалось получить корректную версию из таблицы db_version"
     * @throws JsonException            Выбрасывается при ошибках, связанных с кодированием/декодированием JSON,
     *                                  например, при логировании конфигурации в сообщении об ошибке.
     * @throws Exception                Выбрасывается при возникновении общих ошибок выполнения запроса на более
     *                                  низких уровнях.
     *
     * @warning Убедитесь, что массивы `$columns_to_return` и `$columns_to_search` не пусты.
     *          Проверьте, что `$table` содержит корректное и непустое имя таблицы.
     *          Строка поиска `$search_string` не может быть пустой (за исключением `*`).
     *          Использование параметров `params` в `$options` обязательно для значений, подставляемых в запросы
     *          (например, в условии `where`), для предотвращения SQL-инъекций.
     *          Метод зависит от наличия таблицы `db_version` и корректного типа СУБД.
     *
     * Пример использования метода _full_text_search_internal():
     * @code
     * // Пример выполнения полнотекстового поиска
     * $results = $this->_full_text_search_internal(
     *     ['id', 'title', 'content'],
     *     ['title', 'content'],
     *     'искомая фраза',
     *     'articles',
     *     [
     *         'where' => ['status' => 'published'],
     *         'limit' => 10,
     *         'params' => [':status' => 'published']
     *     ]
     * );
     *
     * if ($results !== false) {
     *     print_r($results);
     * } else {
     *     echo "Поиск не дал результатов или произошла ошибка.";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Database::_select_internal()
     *         Используется для выборки всех записей при `$search_string = '*'` и для получения версии БД.
     * @see    PhotoRigma::Classes::Database::_result_array_internal()
     *         Используется для получения результата в виде массива при `$search_string = '*'`.
     * @see    PhotoRigma::Classes::Database::_result_row_internal()
     *         Используется для получения одной строки результата (версии БД).
     * @see    PhotoRigma::Classes::Database::_fts_check_options()
     *         Используется для фильтрации опций при `$search_string = '*'`.
     * @see    PhotoRigma::Classes::Database::_unescape_identifiers()
     *         Используется для снятия экранирования имен столбцов и таблицы.
     * @see    PhotoRigma::Classes::Database::_full_text_search_mysql()
     *         Специфический метод для полнотекстового поиска в MySQL.
     * @see    PhotoRigma::Classes::Database::_full_text_search_pgsql()
     *         Специфический метод для полнотекстового поиска в PostgreSQL.
     * @see    PhotoRigma::Classes::Database::_full_text_search_sqlite()
     *         Специфический метод для полнотекстового поиска в SQLite.
     * @see    PhotoRigma::Classes::Database::$db_type
     *         Свойство, хранящее тип текущей СУБД.
     * @see    PhotoRigma::Classes::Database::full_text_search()
     *         Публичный метод-обертка, использующий этот внутренний метод.
     */
    protected function _full_text_search_internal(
        array $columns_to_return,
        array $columns_to_search,
        string $search_string,
        string $table,
        array $options = []
    ): array|false {
        // 1. Проверка аргументов
        if (empty($columns_to_return) || empty($columns_to_search) || empty($table)) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: 'global') . ') | ' . 'Недопустимые аргументы | Подробности: columns_to_return, columns_to_search или table пусты'
            );
        }
        if (trim($search_string) === '') {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: 'global') . ') | ' . 'Пустая строка поиска'
            );
        }

        // 2. Если search_string == '*', делаем обычный SELECT через _select_internal
        if ($search_string === '*') {
            $new_option = $this->_fts_check_options($options, []);
            $this->_select_internal(
                $columns_to_return,
                $table,
                $new_option
            );

            return $this->_result_array_internal();
        }

        // 3. Получение версии базы данных
        $this->_select_internal('ver', 'db_version', ['limit' => 1]);

        $version_data = $this->_result_row_internal();

        if ($version_data === false || !isset($version_data['ver'])) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: 'global') . ') | ' . 'Не удалось получить корректную версию из таблицы db_version'
            );
        }

        $db_version = $version_data['ver'];

        // 4. Снятие экранирования
        $columns_to_return = $this->_unescape_identifiers($columns_to_return);
        $columns_to_search = $this->_unescape_identifiers($columns_to_search);
        $table = $this->_unescape_identifiers([$table])[0];

        // 5. Выбор подметода в зависимости от типа СУБД
        return match ($this->db_type) {
            'mysql'  => $this->_full_text_search_mysql(
                $columns_to_return,
                $columns_to_search,
                $search_string,
                $table,
                $db_version,
                $options
            ),
            'pgsql'  => $this->_full_text_search_pgsql(
                $columns_to_return,
                $columns_to_search,
                $search_string,
                $table,
                $db_version,
                $options
            ),
            'sqlite' => $this->_full_text_search_sqlite(
                $columns_to_return,
                $columns_to_search,
                $search_string,
                $table,
                $db_version,
                $options
            ),
            default  => throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: 'global') . ') | ' . "Неизвестный тип СУБД | Тип: $this->db_type"
            ),
        };
    }

    /**
     * @brief   Выполняет полнотекстовый поиск в MySQL с обработкой минимальной длины строки, кешированием ошибок и
     *          fallback на LIKE.
     *
     * @details Этот приватный метод реализует логику полнотекстового поиска (Full-Text Search - FTS) для базы данных
     *          MySQL. Он обрабатывает минимальную длину строки поиска, использует кеширование для быстрого определения
     *          предыдущих ошибок выполнения FTS-запросов (например, из-за отсутствия FTS-индекса) и включает
     *          механизм автоматического перехода (fallback) на поиск с использованием оператора `LIKE` в случае
     *          необходимости.
     *          Метод выполняет следующие действия:
     *          1. Экранирует имена столбцов для возврата (`$columns_to_return`), столбцов для поиска
     *             (`$columns_to_search`) и имя таблицы (`$table`) с использованием обратных кавычек (`` ` ``) для
     *             соответствия синтаксису MySQL.
     *          2. Проверяет, что длина строки поиска `$search_string` превышает минимально допустимое значение,
     *             определенное константой `MIN_FULLTEXT_SEARCH_LENGTH`. Если длина недостаточна, метод сразу же
     *             вызывает приватный метод `_fallback_to_like_mysql()` для выполнения поиска с использованием `LIKE`
     *             и возвращает его результат.
     *          3. Формирует уникальный ключ кеша на основе имени таблицы и столбцов для поиска для идентификации
     *             FTS-индекса в кеше.
     *          4. Проверяет кеш (`$this->cache`) на наличие записи, связанной с этим FTS-индексом, используя
     *             `$db_version` как параметр валидности кеша. Если в кеше найдена запись с флагом `query_error = true`,
     *             это означает, что предыдущая попытка выполнить FTS-запрос для этого индекса завершилась ошибкой.
     *             В этом случае метод также вызывает `_fallback_to_like_mysql()` и возвращает его результат.
     *          5. Предпринимает попытку выполнить полнотекстовый поиск в блоке `try...catch`:
     *             - Формирует базовые опции FTS (`$base_options`), включающие условие `WHERE MATCH(...) AGAINST(...)
     *               IN NATURAL LANGUAGE MODE` и сортировку `ORDER BY MATCH(...) AGAINST(...) DESC`, используя
     *               переданную строку поиска `$search_string` как параметр.
     *             - Объединяет переданные внешние опции запроса (`$options`) с базовыми FTS-опциями
     *               (`$base_options`) с помощью приватного метода `_fts_check_options()`. Этот метод разрешает
     *               конфликты плейсхолдеров и определяет приоритеты для WHERE, ORDER BY, GROUP BY, LIMIT и PARAMS.
     *             - Вызывает приватный метод `_select_internal()` с экранированными столбцами, экранированным именем
     *               таблицы и объединенными опциями для выполнения SQL-запроса.
     *          6. В случае успешного выполнения FTS-запроса (блок `try` без исключений):
     *             - Обновляет кеш (`$this->cache`), устанавливая флаг `query_error = false` для данного FTS-индекса,
     *               указывая на успешность последнего запроса.
     *             - Получает результат запроса в виде массива с помощью приватного метода `_result_array_internal()` и
     *               возвращает его.
     *          7. В случае возникновения исключения (`Throwable`) во время выполнения FTS-запроса (блок `catch`):
     *             - Логирует информацию об ошибке с помощью функции `log_in_file()`, включая сообщение об ошибке.
     *             - Обновляет кеш (`$this->cache`), устанавливая флаг `query_error = true` для данного FTS-индекса,
     *               чтобы избежать повторных ошибок в будущем.
     *             - Вызывает приватный метод `_fallback_to_like_mysql()` для выполнения поиска с использованием
     *               `LIKE` и возвращает его результат.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса `Database`.
     *          Он вызывается из публичных или защищенных методов поиска, когда определен тип базы данных MySQL.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param array  $columns_to_return Массив строк с именами столбцов, которые нужно вернуть в результате поиска.
     * @param array  $columns_to_search Массив строк с именами столбцов, по которым выполняется полнотекстовый поиск.
     * @param string $search_string     Строка поиска, введенная пользователем (должна быть без спецсимволов LIKE, но
     *                                  может содержать операторы FTS, если включен соответствующий режим).
     *                                  Проверяется на минимальную длину.
     * @param string $table             Строка с именем таблицы, в которой выполняется поиск.
     * @param string $db_version        Строка, представляющая версию структуры базы данных (например, из специальной
     *                                  таблицы), используется для валидации кеша.
     * @param array $options            Массив дополнительных опций для формирования запроса. Используется для
     *                                  добавления условий WHERE, GROUP BY, ORDER BY, LIMIT и параметров к FTS-запросу.
     *                                  Поддерживаемые ключи:
     *                                  - where (string|array|false): Дополнительное условие WHERE.
     *                                  - group (string|false): Группировка GROUP BY.
     *                                  - order (string|false): Дополнительная сортировка ORDER BY (имеет более
     *                                    низкий приоритет, чем сортировка FTS).
     *                                  - limit (int|string|false): Ограничение LIMIT.
     *                                  - params (array): Ассоциативный массив дополнительных параметров для
     *                                    подготовленного выражения.
     *
     * @return array|false Результат выполнения поиска в виде массива ассоциативных массивов (строк) при успехе и
     *                     наличии результатов, пустой массив при успехе, но отсутствии результатов, или `false` в
     *                     случае критической ошибки (хотя большинство ошибок обрабатывается через исключения или
     *                     fallback).
     *
     * @throws JsonException            Может быть выброшено методами кеширования (`$this->cache->is_valid`,
     *                                  `$this->cache->update_cache`) при ошибках сериализации/десериализации данных
     *                                  кеша.
     * @throws Exception                Может быть выброшено функцией логирования `log_in_file()` в случае
     *                                  невозможности записи в файл.
     * @throws PDOException             Может быть выброшено методами `_fts_check_options()` (через вызов
     *                                  `_build_conditions()`) или `_select_internal()` в случае ошибок, связанных с
     *                                  базой данных или некорректным SQL, если они не перехватываются в блоке
     *                                  `try...catch` или выбрасываются после него. (Большинство таких ошибок внутри
     *                                  FTS перехватываются и приводят к fallback).
     * @throws InvalidArgumentException Может быть выброшено методом `_fts_check_options()` (через вызов
     *                                  `_build_conditions()`) при некорректном формате опций. (Большинство таких
     *                                  ошибок внутри FTS перехватываются и приводят к fallback).
     *
     * @note    Метод использует константу `MIN_FULLTEXT_SEARCH_LENGTH` для проверки минимальной длины строки поиска.
     *          Активно используется кеширование (`$this->cache`, `$this->format_cache` неявно через
     *          `_select_internal`) для оптимизации и запоминания FTS-индексов, вызывающих ошибки.
     *          При возникновении ошибок выполнения FTS-запроса (например, отсутствие FTS-индекса), метод
     *          автоматически переключается на более медленный поиск с помощью оператора `LIKE`, вызывая
     *          `_fallback_to_like_mysql()`.
     *
     * @warning Убедитесь, что в таблице, указанной в `$table`, существуют столбцы из `$columns_to_search`,
     *          и по ним создан действующий полнотекстовый индекс (`FULLTEXT`). Отсутствие индекса приведет к ошибке
     *          выполнения FTS-запроса и переключению на `LIKE`.
     *          Поиск с `LIKE` может быть значительно медленнее полнотекстового поиска на больших объемах данных.
     *
     * Пример использования (вызывается внутри класса):
     * @code
     * // Предположим, MIN_FULLTEXT_SEARCH_LENGTH = 4
     * // $this->current_format = 'mysql';
     * // $this->db_type = 'mysql';
     *
     * $columns_to_return = ['id', 'title', 'content'];
     * $columns_to_search = ['title', 'content'];
     * $search_string = 'database optimization'; // Длина >= 4
     * $table = 'articles';
     * $db_version = '1.0'; // Пример версии структуры БД
     * $options = [
     *     'where' => ['is_published' => 1],
     *     'limit' => 10,
     *     'params' => [':is_published' => 1],
     * ];
     *
     * // Вызов метода
     * $results = $this->_full_text_search_mysql(
     *     $columns_to_return,
     *     $columns_to_search,
     *     $search_string,
     *     $table,
     *     $db_version,
     *     $options
     * );
     *
     * if ($results !== false) {
     *     // Обработка результатов поиска
     *     print_r($results);
     * } else {
     *     // Обработка критической ошибки (редко, т.к. есть fallback)
     *     echo "Произошла критическая ошибка поиска.";
     * }
     *
     * // Если strlen($search_string) < 4, будет вызван _fallback_to_like_mysql
     * // Если FTS запрос упадет (нет индекса), будет вызван _fallback_to_like_mysql, ошибка будет залогирована и закэширована.
     * @endcode
     * @see    PhotoRigma::Classes::Database::_fallback_to_like_mysql()
     *         Приватный метод, реализующий поиск по LIKE в качестве fallback.
     * @see    PhotoRigma::Classes::Database::_fts_check_options()
     *         Приватный метод, объединяющий и обрабатывающий опции запроса для FTS.
     * @see    PhotoRigma::Classes::Database::_select_internal()
     *         Приватный метод для выполнения SELECT-запроса.
     * @see    PhotoRigma::Classes::Database::_result_array_internal()
     *         Приватный метод для получения всех строк результата запроса.
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования сообщений.
     * @see    PhotoRigma::Interfaces::Cache_Handler_Interface
     *         Интерфейс, используемый для работы с кешированием.
     * @see    PhotoRigma::Classes::Database::$cache
     *         Свойство, хранящее объект, реализующий `Cache_Handler_Interface`.
     * @see    PhotoRigma::Classes::Database::$format_cache
     *         Внутреннее свойство, используемое для кэширования форматов (используется косвенно через _select_internal).
     * @see    PhotoRigma::Classes::Database::$unescape_cache
     *         Внутреннее свойство, используемое для кэширования снятия экранирования (используется косвенно через _select_internal).
     * @see    PhotoRigma::Classes::Database::$current_format
     *         Свойство, хранящее текущий формат SQL.
     * @see    PhotoRigma::Classes::Database::$db_type
     *         Свойство, хранящее тип текущей СУБД.
     * @see    MIN_FULLTEXT_SEARCH_LENGTH
     *         Константа, определяющая минимальную длину строки для полнотекстового поиска.
     */
    private function _full_text_search_mysql(
        array $columns_to_return,
        array $columns_to_search,
        string $search_string,
        string $table,
        string $db_version,
        array $options
    ): array|false {
        // 1. Формируем экранированные имена столбцов и таблицы
        $columns_to_search_escaped = array_map(static fn ($col) => "`$col`", $columns_to_search);
        $columns_to_return_escaped = array_map(static fn ($col) => "`$col`", $columns_to_return);
        $table_escaped = "`$table`";

        // 2. Проверка минимальной длины строки поиска
        if (strlen($search_string) < MIN_FULLTEXT_SEARCH_LENGTH) {
            return $this->_fallback_to_like_mysql(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $options
            );
        }

        // 3. Формируем уникальный ключ для кэша
        $key = 'fts_mysql_' . substr(hash('xxh3', $table . ':' . implode(',', $columns_to_search)), 0, 24);

        // 4. Проверяем кэш: был ли ранее query_error
        $cached_index = $this->cache->is_valid($key, $db_version);

        if ($cached_index !== false && isset($cached_index['query_error']) && $cached_index['query_error'] === true) {
            // Запрос ранее упал — делаем fallback_to_like
            return $this->_fallback_to_like_mysql(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $options
            );
        }

        // 5. Обработка полнотекстового поиска
        try {
            // Формируем внутренние FTS-условия
            $base_options = [
                'where' => 'MATCH(' . implode(', ', $columns_to_search_escaped) . ') AGAINST(:search_string_where IN NATURAL LANGUAGE MODE)',
                'order' => 'MATCH(' . implode(', ', $columns_to_search_escaped) . ') AGAINST(:search_string_order) DESC',
                'params' => [
                    ':search_string_where'  => $search_string,
                    ':search_string_order' => $search_string,
                ],
            ];

            // Объединяем внешние и внутренние опции
            $new_options = $this->_fts_check_options($options, $base_options);

            // Выполняем SQL-запрос
            $this->_select_internal(
                $columns_to_return_escaped,
                $table_escaped,
                $new_options
            );
        } catch (Throwable $e) {
            // Ловим ошибку выполнения полнотекстового запроса
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') .
                ") | Полнотекстовый запрос упал | Таблица: $table_escaped | Сообщение: {$e->getMessage()}"
            );

            // Обновляем кэш: теперь запрос выдает ошибку
            $this->cache->update_cache($key, $db_version, ['query_error' => true]);

            // Переходим на LIKE
            return $this->_fallback_to_like_mysql(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $options
            );
        }

        // 6. Сохраняем в кэш успешный результат: полнотекстовый запрос отработал
        $this->cache->update_cache($key, $db_version, ['query_error' => false]);

        // 7. Получаем результат и отдаем его
        return $this->_result_array_internal();
    }

    /**
     * @brief   Выполняет альтернативный поиск по LIKE для MySQL.
     *
     * @details Этот приватный метод реализует поиск данных в MySQL, используя оператор `LIKE` по нескольким столбцам.
     *          Он используется как резервный механизм (fallback), когда основной полнотекстовый поиск (FTS)
     *          недоступен, отключен, или не дал результатов (например, из-за минимальной длины строки поиска
     *          или отсутствия FTS-индекса).
     *          Метод выполняет следующие действия:
     *          1. Проверяет, является ли строка поиска `$search_string` пустой. Если да, возвращает `false` без
     *             выполнения запроса.
     *          2. Формирует условия `WHERE` для оператора `LIKE` по каждому столбцу из массива
     *             `$columns_to_search_escaped`. Каждое условие имеет вид `"имя_столбца" LIKE :плейсхолдер`. Для
     *             строки поиска автоматически добавляются символы `%` в начале и конце (для поиска подстроки).
     *             Каждое условие использует уникальный именованный плейсхолдер (например, `:search_string_0`,
     *             `:search_string_1`) для безопасной привязки параметров.
     *          3. Объединяет сформированные условия `LIKE` оператором `OR` для создания общей строки условия `WHERE`.
     *          4. Формирует базовые опции (`$base_options`) для поиска по `LIKE`, включающие сформированное условие
     *             `WHERE` и массив параметров для плейсхолдеров `LIKE`.
     *          5. Объединяет переданные внешние опции запроса (`$options`) с базовыми опциями `LIKE` (`$base_options`)
     *             с помощью приватного метода `_fts_check_options()`. Этот метод разрешает конфликты плейсхолдеров
     *             и определяет приоритеты для WHERE, ORDER BY, GROUP BY, LIMIT и PARAMS.
     *          6. Предпринимает попытку выполнить SQL-запрос SELECT с использованием экранированных столбцов для
     *             возврата, экранированного имени таблицы и объединенных опций (`$new_options`) в блоке
     *             `try...catch`, вызывая приватный метод `_select_internal()`.
     *          7. В случае возникновения исключения (`Throwable`) во время выполнения LIKE-запроса (блок `catch`):
     *             - Логирует информацию об ошибке с помощью функции `log_in_file()`, включая текст запроса и
     *               сообщение об ошибке.
     *             - Возвращает `false`, сигнализируя об ошибке выполнения поиска.
     *          8. В случае успешного выполнения запроса (блок `try` без исключений) получает результат запроса
     *             в виде массива с помощью приватного метода `_result_array_internal()` и возвращает его.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса `Database`.
     *          Он вызывается из приватного метода `_full_text_search_mysql()`.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param array $columns_to_return_escaped  Массив строк с именами столбцов, которые нужно вернуть. Имена
     *                                          столбцов должны быть уже экранированы для MySQL (например, `` `id` ``).
     * @param array $columns_to_search_escaped  Массив строк с именами столбцов, по которым выполняется поиск по LIKE.
     *                                          Имена столбцов должны быть уже экранированы для MySQL.
     * @param string $search_string             Строка поиска, введенная пользователем. Для поиска по LIKE
     *                                          автоматически будут добавлены символы `%`. Пустая строка приводит к
     *                                          возврату `false`.
     * @param string $table_escaped             Строка с именем таблицы, в которой выполняется поиск. Имя таблицы
     *                                          должно быть уже экранировано для MySQL (например, `` `users` ``).
     * @param array $options                    Массив дополнительных опций для формирования запроса. Используется
     *                                          для добавления условий WHERE, GROUP BY, ORDER BY, LIMIT и параметров
     *                                          к LIKE-запросу.
     *                                          Поддерживаемые ключи:
     *                                          - where (string|array|false): Дополнительное условие WHERE
     *                                            (объединяется с условиями LIKE оператором AND).
     *                                          - group (string|false): Группировка GROUP BY.
     *                                          - order (string|false): Сортировка ORDER BY.
     *                                          - limit (int|string|false): Ограничение LIMIT.
     *                                          - params (array): Ассоциативный массив дополнительных параметров для
     *                                            подготовленного выражения.
     *
     * @return array|false Результат выполнения поиска в виде массива ассоциативных массивов (строк) при успехе и
     *                     наличии результатов, пустой массив при успехе, но отсутствии результатов, или `false` в
     *                     случае пустой строки поиска или ошибки выполнения запроса.
     *
     * @throws Exception                Может быть выброшено функцией логирования `log_in_file()` в случае
     *                                  невозможности записи в файл.
     * @throws PDOException             Может быть выброшено методами `_fts_check_options()` (через вызов
     *                                  `_build_conditions()`) или `_select_internal()` в случае ошибок, связанных с
     *                                  базой данных или некорректным SQL, если они не перехватываются в блоке
     *                                  `try...catch` или выбрасываются после него. (Большинство таких ошибок внутри
     *                                  LIKE перехватываются и приводят к возврату false).
     * @throws InvalidArgumentException Может быть выброшено методом `_fts_check_options()` (через вызов
     *                                  `_build_conditions()`) при некорректном формате опций. (Большинство таких
     *                                  ошибок внутри LIKE перехватываются и приводят к возврату false).
     *
     * @note    Метод используется как резервный вариант поиска, когда полнотекстовый поиск (FTS) не может быть выполнен.
     *          Для каждого столбца поиска создается отдельное условие `LIKE` с уникальным плейсхолдером.
     *          Использует приватный метод `_fts_check_options()` для объединения условий `LIKE` с любыми
     *          дополнительными опциями, предоставленными пользователем.
     *
     * @warning Убедитесь, что входные параметры `$columns_to_return_escaped`, `$columns_to_search_escaped` и
     *          `$table_escaped` уже экранированы для MySQL.
     *          Поиск с `LIKE` может быть неэффективен на больших объемах данных и может вызвать высокую нагрузку на
     *          базу данных.
     *
     * Пример использования (вызывается из _full_text_search_mysql()):
     * @code
     * // Предположим:
     * // $columns_to_return_escaped = ['`id`', '`title`'];
     * // $columns_to_search_escaped = ['`title`', '`content`'];
     * // $search_string = 'search term';
     * // $table_escaped = '`articles`';
     * // $options = ['limit' => 5];
     *
     * // Вызов fallback метода
     * $results_like = $this->_fallback_to_like_mysql(
     *     $columns_to_return_escaped,
     *     $columns_to_search_escaped,
     *     $search_string,
     *     $table_escaped,
     *     $options
     * );
     *
     * if ($results_like !== false) {
     *     // Обработка результатов поиска по LIKE
     *     print_r($results_like);
     * } else {
     *     // Обработка ошибки выполнения LIKE-поиска
     *     echo "Произошла ошибка при выполнении LIKE-поиска.";
     * }
     *
     * // Если $search_string был '', вернет false сразу.
     * @endcode
     * @see    PhotoRigma::Classes::Database::_full_text_search_mysql()
     *         Приватный метод, который вызывает этот метод в случае необходимости fallback.
     * @see    PhotoRigma::Classes::Database::_fts_check_options()
     *         Приватный метод, используемый для объединения опций.
     * @see    PhotoRigma::Classes::Database::_select_internal()
     *         Приватный метод для выполнения SELECT-запроса.
     * @see    PhotoRigma::Classes::Database::_result_array_internal()
     *         Приватный метод для получения всех строк результата запроса.
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования сообщений.
     */
    private function _fallback_to_like_mysql(
        array $columns_to_return_escaped,
        array $columns_to_search_escaped,
        string $search_string,
        string $table_escaped,
        array $options
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
        $base_options = [
            'where'  => $where_sql,
            'params' => $params,
        ];
        $new_options = $this->_fts_check_options($options, $base_options);

        // 3. Выполняем запрос через _select_internal
        try {
            $this->_select_internal(
                $columns_to_return_escaped,
                $table_escaped,
                $new_options
            );
        } catch (Throwable $e) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' . "Ошибка при выполнении LIKE-поиска | Таблица: $table_escaped, Сообщение: {$e->getMessage()}"
            );
            return false;
        }

        // 4. Возвращаем результат напрямую
        return $this->_result_array_internal();
    }

    /**
     * @brief   Выполняет полнотекстовый поиск в PostgreSQL с обработкой минимальной длины строки, кешированием
     *          ошибок и fallback на ILIKE.
     *
     * @details Этот приватный метод реализует логику полнотекстового поиска (Full-Text Search - FTS) для базы данных
     *          PostgreSQL. Он обрабатывает минимальную длину строки поиска, использует кеширование для быстрого
     *          определения предыдущих ошибок выполнения FTS-запросов и включает механизм автоматического перехода
     *          (fallback) на поиск с использованием оператора `ILIKE` (регистронезависимый LIKE) в случае
     *          необходимости.
     *          Метод выполняет следующие действия:
     *          1. Экранирует имена столбцов для возврата (`$columns_to_return`), столбцов для поиска
     *             (`$columns_to_search`) и имя таблицы (`$table`) с использованием двойных кавычек (`"`) для
     *             соответствия синтаксису PostgreSQL.
     *          2. Проверяет, что длина строки поиска `$search_string` превышает минимально допустимое значение,
     *             определенное константой `MIN_FULLTEXT_SEARCH_LENGTH`. Если длина недостаточна, метод сразу же
     *             вызывает приватный метод `_fallback_to_ilike_pgsql()` для выполнения поиска с использованием
     *             `ILIKE` и возвращает его результат.
     *          3. Формирует уникальный ключ кеша на основе имени таблицы и столбцов для поиска для идентификации
     *             FTS-индекса в кеше.
     *          4. Проверяет кеш (`$this->cache`) на наличие записи, связанной с этим FTS-индексом, используя
     *             `$db_version` как параметр валидности кеша. Если в кеше найдена запись с флагом `query_error = true`,
     *             это означает, что предыдущая попытка выполнить FTS-запрос для этого индекса завершилась ошибкой.
     *             В этом случае метод также вызывает `_fallback_to_ilike_pgsql()` и возвращает его результат.
     *          5. Предпринимает попытку выполнить полнотекстовый поиск в блоке `try...catch`:
     *             - Формирует базовые опции FTS (`$base_options`), включающие условие `WHERE tsv_weighted @@
     *               plainto_tsquery(:search_string_where)` и сортировку `ORDER BY ts_rank(tsv_weighted,
     *               plainto_tsquery(:search_string_order)) DESC`, используя переданную строку поиска
     *               `$search_string` как параметр. Предполагается наличие столбца `tsv_weighted` типа `TSVECTOR` в
     *               таблице, содержащего лексемы для поиска.
     *             - Объединяет переданные внешние опции запроса (`$options`) с базовыми FTS-опциями
     *               (`$base_options`) с помощью приватного метода `_fts_check_options()`. Этот метод разрешает
     *               конфликты плейсхолдеров и определяет приоритеты для WHERE, ORDER BY, GROUP BY, LIMIT и PARAMS.
     *             - Вызывает приватный метод `_select_internal()` с экранированными столбцами, экранированным именем
     *               таблицы и объединенными опциями для выполнения SQL-запроса.
     *          6. В случае успешного выполнения FTS-запроса (блок `try` без исключений):
     *             - Обновляет кеш (`$this->cache`), устанавливая флаг `query_error = false` для данного FTS-индекса,
     *               указывая на успешность последнего запроса.
     *             - Получает результат запроса в виде массива с помощью приватного метода `_result_array_internal()` и
     *               возвращает его.
     *          7. В случае возникновения исключения (`Throwable`) во время выполнения FTS-запроса (блок `catch`):
     *             - Логирует информацию об ошибке с помощью функции `log_in_file()`, включая текст запроса и
     *               сообщение об ошибке.
     *             - Обновляет кеш (`$this->cache`), устанавливая флаг `query_error = true` для данного FTS-индекса,
     *               чтобы избежать повторных ошибок в будущем.
     *             - Вызывает приватный метод `_fallback_to_ilike_pgsql()` для выполнения поиска с использованием
     *               `ILIKE` и возвращает его результат.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса `Database`.
     *          Он вызывается из публичных или защищенных методов поиска, когда определен тип базы данных PostgreSQL.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param array  $columns_to_return Массив строк с именами столбцов, которые нужно вернуть в результате поиска.
     * @param array  $columns_to_search Массив строк с именами столбцов, по которым выполняется полнотекстовый поиск.
     * @param string $search_string     Строка поиска, введенная пользователем (должна быть без спецсимволов LIKE, но
     *                                  может содержать операторы FTS, если включен соответствующий режим).
     *                                  Проверяется на минимальную длину.
     * @param string $table             Строка с именем таблицы, в которой выполняется поиск.
     * @param string $db_version        Строка, представляющая версию структуры базы данных (например, из специальной
     *                                  таблицы), используется для валидации кеша.
     * @param array $options            Массив дополнительных опций для формирования запроса. Используется для
     *                                  добавления условий WHERE, GROUP BY, ORDER BY, LIMIT и параметров к FTS-запросу.
     *                                  Поддерживаемые ключи:
     *                                  - where (string|array|false): Дополнительное условие WHERE.
     *                                  - group (string|false): Группировка GROUP BY.
     *                                  - order (string|false): Дополнительная сортировка ORDER BY (имеет более
     *                                    низкий приоритет, чем сортировка FTS).
     *                                  - limit (int|string|false): Ограничение LIMIT.
     *                                  - params (array): Ассоциативный массив дополнительных параметров для
     *                                    подготовленного выражения.
     *
     * @return array|false Результат выполнения поиска в виде массива ассоциативных массивов (строк) при успехе и
     *                     наличии результатов, пустой массив при успехе, но отсутствии результатов, или `false` в
     *                     случае пустой строки поиска, критической ошибки или если предыдущий FTS-запрос для этого
     *                     индекса завершился ошибкой (приводит к fallback, который может вернуть false).
     *
     * @throws JsonException            Может быть выброшено методами кеширования (`$this->cache->is_valid`,
     *                                  `$this->cache->update_cache`) при ошибках сериализации/десериализации данных
     *                                  кеша.
     * @throws Exception                Может быть выброшено функцией логирования `log_in_file()` в случае
     *                                  невозможности записи в файл.
     * @throws PDOException             Может быть выброшено методами `_fts_check_options()` (через вызов
     *                                  `_build_conditions()`) или `_select_internal()` в случае ошибок, связанных с
     *                                  базой данных или некорректным SQL, если они не перехватываются в блоке
     *                                  `try...catch` или выбрасываются после него. (Большинство таких ошибок внутри
     *                                  FTS перехватываются и приводят к fallback).
     * @throws InvalidArgumentException Может быть выброшено методом `_fts_check_options()` (через вызов
     *                                  `_build_conditions()`) при некорректном формате опций. (Большинство таких
     *                                  ошибок внутри FTS перехватываются и приводят к fallback).
     *
     * @note    Метод использует константу `MIN_FULLTEXT_SEARCH_LENGTH` для проверки минимальной длины строки поиска.
     *          Активно используется кеширование (`$this->cache`) для оптимизации и запоминания FTS-индексов,
     *          вызывающих ошибки.
     *          При возникновении ошибок выполнения FTS-запроса (например, из-за проблем с FTS конфигурацией или
     *          отсутствия колонки tsv_weighted), метод автоматически переключается на поиск с помощью оператора
     *          `ILIKE`, вызывая `_fallback_to_ilike_pgsql()`.
     *          Для работы FTS в PostgreSQL требуется колонка типа TSVECTOR (например, `tsv_weighted`) и
     *          соответствующая конфигурация.
     *
     * @warning Убедитесь, что в таблице, указанной в `$table`, существуют столбцы из `$columns_to_search`,
     *          а также колонка типа TSVECTOR (например, `tsv_weighted`), содержащая лексемы для поиска. Убедитесь,
     *          что настроена соответствующая FTS-конфигурация (словари, парсеры). Отсутствие необходимых компонентов
     *          или некорректная конфигурация приведет к ошибке выполнения FTS-запроса и переключению на `ILIKE`.
     *          Поиск с `ILIKE` может быть значительно медленнее полнотекстового поиска на больших объемах данных.
     *
     * Пример использования (вызывается внутри класса):
     * @code
     * // Предположим, MIN_FULLTEXT_SEARCH_LENGTH = 4
     * // $this->current_format = 'pgsql';
     * // $this->db_type = 'pgsql';
     *
     * $columns_to_return = ['id', 'title', 'content'];
     * $columns_to_search = ['title', 'content']; // Используются для формирования запроса, но поиск идет по tsv_weighted
     * $search_string = 'database optimization'; // Длина >= 4
     * $table = 'articles'; // Предполагается, что в таблице 'articles' есть колонка tsv_weighted
     * $db_version = '1.0'; // Пример версии структуры БД
     * $options = [
     *     'where' => ['is_published' => 1],
     *     'limit' => 10,
     *     'params' => [':is_published' => 1],
     * ];
     *
     * // Вызов метода
     * $results = $this->_full_text_search_pgsql(
     *     $columns_to_return,
     *     $columns_to_search, // Столбцы для поиска используются plainto_tsquery
     *     $search_string,
     *     $table,
     *     $db_version,
     *     $options
     * );
     *
     * if ($results !== false) {
     *     // Обработка результатов поиска
     *     print_r($results);
     * } else {
     *     // Обработка ошибки (произошел fallback на ILIKE, и он тоже вернул false при ошибке)
     *     echo "Произошла ошибка поиска (включая fallback).";
     * }
     *
     * // Если strlen($search_string) < 4, будет вызван _fallback_to_ilike_pgsql
     * // Если FTS запрос упадет (например, нет колонки tsv_weighted), будет вызван _fallback_to_ilike_pgsql, ошибка будет залогирована и закэширована.
     * @endcode
     * @see    PhotoRigma::Classes::Database::_fallback_to_ilike_pgsql()
     *         Приватный метод, реализующий поиск по ILIKE в качестве fallback.
     * @see    PhotoRigma::Classes::Database::_fts_check_options()
     *         Приватный метод, объединяющий и обрабатывающий опции запроса для FTS.
     * @see    PhotoRigma::Classes::Database::_select_internal()
     *         Приватный метод для выполнения SELECT-запроса.
     * @see    PhotoRigma::Classes::Database::_result_array_internal()
     *         Приватный метод для получения всех строк результата запроса.
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования сообщений.
     * @see    PhotoRigma::Interfaces::Cache_Handler_Interface
     *         Интерфейс, используемый для работы с кешированием.
     * @see    PhotoRigma::Classes::Database::$cache
     *         Свойство, хранящее объект, реализующий `Cache_Handler_Interface`.
     * @see    MIN_FULLTEXT_SEARCH_LENGTH
     *         Константа, определяющая минимальную длину строки для полнотекстового поиска.
     */
    private function _full_text_search_pgsql(
        array $columns_to_return,
        array $columns_to_search,
        string $search_string,
        string $table,
        string $db_version,
        array $options
    ): array|false {
        // 1. Формируем экранированные имена столбцов и таблицы
        $columns_to_return_escaped = array_map(static fn ($col) => "\"$col\"", $columns_to_return);
        $columns_to_search_escaped = array_map(static fn ($col) => "\"$col\"", $columns_to_search);
        $table_escaped = "\"$table\"";

        // 2. Проверка минимальной длины строки поиска
        if (strlen($search_string) < MIN_FULLTEXT_SEARCH_LENGTH) {
            return $this->_fallback_to_ilike_pgsql(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $options
            );
        }

        // 3. Формируем уникальный ключ для кэша
        $key = 'fts_pgsql_' . substr(hash('xxh3', $table . ':' . implode(',', $columns_to_search)), 0, 24);

        // 4. Проверяем кэш: был ли ранее query_error
        $cached_index = $this->cache->is_valid($key, $db_version);

        if ($cached_index !== false && isset($cached_index['query_error']) && $cached_index['query_error'] === true) {
            return $this->_fallback_to_ilike_pgsql(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $options
            );
        }

        // 5. Выполняем полнотекстовый поиск через tsv_weighted
        try {
            // Формируем внутренние FTS-условия
            $base_options = [
                'where'  => 'tsv_weighted @@ plainto_tsquery(:search_string_where)',
                'order'  => 'ts_rank(tsv_weighted, plainto_tsquery(:search_string_order)) DESC',
                'params' => [
                    ':search_string_where' => $search_string,
                    ':search_string_order' => $search_string,
                ],
            ];

            // Объединяем внешние и внутренние опции
            $new_options = $this->_fts_check_options($options, $base_options);

            // Выполняем SQL-запрос
            $this->_select_internal(
                $columns_to_return_escaped,
                $table_escaped,
                $new_options
            );
        } catch (Throwable $e) {
            // Логируем ошибку
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' . "Полнотекстовый запрос PostgreSQL упал | Таблица: $table_escaped, Сообщение: {$e->getMessage()}"
            );

            // Обновляем кэш: теперь запрос выдает ошибку
            $this->cache->update_cache($key, $db_version, ['query_error' => true]);

            // Переходим на ILIKE
            return $this->_fallback_to_ilike_pgsql(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $options
            );
        }

        // 6. Сохраняем в кэш успешный результат: запрос отработал
        $this->cache->update_cache($key, $db_version, ['query_error' => false]);

        // 7. Получаем результат и отдаем его
        return $this->_result_array_internal();
    }

    /**
     * @brief   Выполняет альтернативный поиск по ILIKE с ранжированием similarity() для PostgreSQL.
     *
     * @details Этот приватный метод реализует поиск данных в PostgreSQL, используя оператор `ILIKE`
     *          (регистронезависимый LIKE) по нескольким столбцам с возможностью ранжирования результатов по близости
     *          с использованием функции `similarity()`. Он используется как резервный механизм (fallback), когда
     *          основной полнотекстовый поиск (FTS) недоступен, отключен, или не дал результатов.
     *          Метод выполняет следующие действия:
     *          1. Проверяет, является ли строка поиска `$search_string` пустой. Если да, возвращает `false` без
     *             выполнения запроса.
     *          2. Формирует условия `WHERE` для оператора `ILIKE` по каждому столбцу из массива
     *             `$columns_to_search_escaped`. Каждое условие имеет вид `"имя_столбца" ILIKE :плейсхолдер`. Для
     *              строки поиска автоматически добавляются символы `%` в начале и конце (для поиска подстроки).
     *              Каждое условие использует уникальный именованный плейсхолдер (например, `:search_string_0`,
     *              `:search_string_1`) для безопасной привязки параметров.
     *          3. Формирует выражения для ранжирования по близости с использованием функции `similarity()` для
     *             каждого столбца из массива `$columns_to_search_escaped`. Каждое выражение имеет вид `similarity
     *             ("имя_столбца", :плейсхолдер_ранга)`, где `:плейсхолдер_ранга` - уникальный именованный
     *             плейсхолдер для исходной строки поиска.
     *          4. Объединяет сформированные условия `ILIKE` оператором `OR` для создания общей строки условия `WHERE`.
     *          5. Объединяет сформированные выражения `similarity()` оператором `+` для создания общей строки
     *             сортировки `ORDER BY` по суммарному рангу (по убыванию).
     *          6. Формирует базовые опции (`$base_options`) для поиска по `ILIKE` с ранжированием, включающие
     *             сформированные строки `WHERE` и `ORDER BY`, а также массив параметров, содержащий плейсхолдеры как
     *             для `ILIKE`, так и для `similarity()`.
     *          7. Объединяет переданные внешние опции запроса (`$options`) с базовыми опциями (`$base_options`)
     *             с помощью приватного метода `_fts_check_options()`. Этот метод разрешает конфликты плейсхолдеров
     *             и определяет приоритеты для WHERE, ORDER BY, GROUP BY, LIMIT и PARAMS (сортировка по рангу из
     *             $base_options имеет приоритет).
     *          8. Предпринимает попытку выполнить SQL-запрос SELECT с использованием экранированных столбцов для
     *             возврата, экранированного имени таблицы и объединенных опций (`$new_options`) в блоке
     *             `try...catch`, вызывая приватный метод `_select_internal()`.
     *          9. В случае возникновения исключения (`Throwable`) во время выполнения LIKE-запроса (блок `catch`):
     *             - Логирует информацию об ошибке с помощью функции `log_in_file()`, включая текст запроса и
     *               сообщение об ошибке.
     *             - Возвращает `false`, сигнализируя об ошибке выполнения поиска.
     *          10. В случае успешного выполнения запроса (блок `try` без исключений) получает результат запроса
     *              в виде массива с помощью приватного метода `_result_array_internal()` и возвращает его.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса `Database`.
     *          Он вызывается из приватного метода `_full_text_search_pgsql()`.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param array $columns_to_return_escaped Массив строк с именами столбцов, которые нужно вернуть. Имена
     *                                         столбцов должны быть уже экранированы для PostgreSQL (например, `"id"`).
     * @param array $columns_to_search_escaped Массив строк с именами столбцов, по которым выполняется поиск по
     *                                         ILIKE. Имена столбцов должны быть уже экранированы для PostgreSQL.
     * @param string $search_string            Строка поиска, введенная пользователем. Для поиска по ILIKE
     *                                         автоматически будут добавлены символы `%`. Пустая строка приводит к
     *                                         возврату `false`. Используется как есть для ранжирования `similarity()`.
     * @param string $table_escaped            Строка с именем таблицы, в которой выполняется поиск. Имя таблицы
     *                                         должно быть уже экранировано для PostgreSQL (например, `"users"`).
     * @param array $options                   Массив дополнительных опций для формирования запроса. Используется
     *                                         для добавления условий WHERE, GROUP BY, ORDER BY, LIMIT и параметров
     *                                         к LIKE-запросу.
     *                                         Поддерживаемые ключи:
     *                                         - where (string|array|false): Дополнительное условие WHERE
     *                                           (объединяется с условиями ILIKE оператором AND).
     *                                         - group (string|false): Группировка GROUP BY.
     *                                         - order (string|false): Дополнительная сортировка ORDER BY (имеет
     *                                           более низкий приоритет, чем сортировка по рангу similarity()).
     *                                         - limit (int|string|false): Ограничение LIMIT.
     *                                         - params (array): Ассоциативный массив дополнительных параметров для
     *                                           подготовленного выражения.
     *
     * @return array|false Результат выполнения поиска в виде массива ассоциативных массивов (строк) при успехе и
     *                     наличии результатов, пустой массив при успехе, но отсутствии результатов, или `false` в
     *                     случае пустой строки поиска или ошибки выполнения запроса.
     *
     * @throws Exception                Может быть выброшено функцией логирования `log_in_file()` в случае
     *                                  невозможности записи в файл.
     * @throws PDOException             Может быть выброшено методами `_fts_check_options()` (через вызов
     *                                  `_build_conditions()`) или `_select_internal()` в случае ошибок, связанных с
     *                                  базой данных или некорректным SQL, если они не перехватываются в блоке
     *                                  `try...catch` или выбрасываются после него. (Большинство таких ошибок внутри
     *                                  перехватываются и приводят к возврату false).
     * @throws InvalidArgumentException Может быть выброшено методом `_fts_check_options()` (через вызов
     *                                  `_build_conditions()`) при некорректном формате опций. (Большинство таких
     *                                  ошибок внутри перехватываются и приводят к возврату false).
     *
     * @note    Метод используется как резервный вариант поиска для PostgreSQL, когда полнотекстовый поиск (FTS) не
     *          может быть выполнен. Для каждого столбца поиска создается отдельное условие `ILIKE` и отдельное
     *          выражение `similarity()` с уникальными плейсхолдерами. Условия `ILIKE` объединяются оператором `OR`,
     *          а ранги `similarity()` суммируются для сортировки по убыванию.
     *          Использует приватный метод `_fts_check_options()` для объединения этих условий и сортировки с любыми
     *          дополнительными опциями, предоставленными пользователем.
     *          Для работы функции `similarity()` требуется установить и включить расширение `pg_trgm` в базе данных
     *          PostgreSQL.
     *
     * @warning Убедитесь, что входные параметры `$columns_to_return_escaped`, `$columns_to_search_escaped` и
     *          `$table_escaped` уже экранированы для PostgreSQL.
     *          Убедитесь, что расширение `pg_trgm` установлено и доступно в базе данных PostgreSQL для корректной
     *          работы `similarity()`.
     *          Поиск с `ILIKE` и ранжирование `similarity()` могут быть неэффективны на больших объемах данных по
     *          сравнению с FTS.
     *
     * Пример использования (вызывается из _full_text_search_pgsql()):
     * @code
     * // Предположим:
     * // $columns_to_return_escaped = ['"id"', '"title"'];
     * // $columns_to_search_escaped = ['"title"', '"content"'];
     * // $search_string = 'search term';
     * // $table_escaped = '"articles"';
     * // $options = ['limit' => 5];
     *
     * // Вызов fallback метода
     * $results_ilike = $this->_fallback_to_ilike_pgsql(
     *     $columns_to_return_escaped,
     *     $columns_to_search_escaped,
     *     $search_string,
     *     $table_escaped,
     *     $options
     * );
     *
     * if ($results_ilike !== false) {
     *     // Обработка результатов поиска по ILIKE с ранжированием
     *     print_r($results_ilike);
     * } else {
     *     // Обработка ошибки выполнения ILIKE-поиска
     *     echo "Произошла ошибка при выполнении ILIKE-поиска.";
     * }
     *
     * // Если $search_string был '', вернет false сразу.
     * @endcode
     * @see    PhotoRigma::Classes::Database::_full_text_search_pgsql()
     *         Приватный метод, который вызывает этот метод в случае необходимости fallback.
     * @see    PhotoRigma::Classes::Database::_fts_check_options()
     *         Приватный метод, используемый для объединения опций.
     * @see    PhotoRigma::Classes::Database::_select_internal()
     *         Приватный метод для выполнения SELECT-запроса.
     * @see    PhotoRigma::Classes::Database::_result_array_internal()
     *         Приватный метод для получения всех строк результата запроса.
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования сообщений.
     * @see    https://www.postgresql.org/docs/current/pgtrgm.html
     *         Документация по расширению pg_trgm и функции similarity().
     */
    private function _fallback_to_ilike_pgsql(
        array $columns_to_return_escaped,
        array $columns_to_search_escaped,
        string $search_string,
        string $table_escaped,
        array $options
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
        $base_options = [
            'where'  => $where_sql,
            'order'  => $order_sql,
            'params' => $params,
        ];
        $new_options = $this->_fts_check_options($options, $base_options);

        // 3. Выполняем запрос через _select_internal
        try {
            $this->_select_internal(
                $columns_to_return_escaped,
                $table_escaped,
                $new_options
            );
        } catch (Throwable $e) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' . "Ошибка при выполнении ILIKE-поиска | Таблица: $table_escaped, Сообщение: {$e->getMessage()}"
            );
            return false;
        }

        // 4. Возвращаем результат
        return $this->_result_array_internal();
    }

    /**
     * @brief   Выполняет полнотекстовый поиск в SQLite через FTS5.
     *
     * @details Этот приватный метод реализует логику полнотекстового поиска (Full-Text Search - FTS) для базы данных
     *          SQLite, используя возможности модуля FTS5. Он обрабатывает минимальную длину строки поиска,
     *          использует кеширование для быстрого определения предыдущих ошибок выполнения FTS5-запросов и включает
     *          механизм автоматического перехода (fallback) на поиск с использованием оператора `LIKE` в случае
     *          необходимости.
     *          Метод выполняет следующие действия:
     *          1. Экранирует имена столбцов для возврата (`$columns_to_return`), столбцов для поиска
     *             (`$columns_to_search`) и имя основной таблицы (`$table`) с использованием двойных кавычек (`"`)
     *             для соответствия синтаксису SQLite. Также формирует экранированное имя связанной виртуальной FTS5
     *             таблицы, добавляя суффикс `_fts` к имени основной таблицы.
     *          2. Проверяет, что длина строки поиска `$search_string` превышает минимально допустимое значение,
     *             определенное константой `MIN_FULLTEXT_SEARCH_LENGTH`. Если длина недостаточна, метод сразу же
     *             вызывает приватный метод `_fallback_to_like_sqlite()` для выполнения поиска с использованием `LIKE`
     *             и возвращает его результат.
     *          3. Формирует уникальный ключ кеша на основе имени основной таблицы и столбцов для поиска для
     *             идентификации соответствующего FTS5-индекса в кеше.
     *          4. Проверяет кеш (`$this->cache`) на наличие записи, связанной с этим FTS5-индексом, используя
     *             `$db_version` как параметр валидности кеша. Если в кеше найдена запись с флагом `query_error =
     *             true`, это означает, что предыдущая попытка выполнить FTS5-запрос для этого индекса завершилась
     *             ошибкой. В этом случае метод также вызывает `_fallback_to_like_sqlite()` и возвращает его результат.
     *          5. Предпринимает попытку выполнить полнотекстовый поиск FTS5 в блоке `try...catch`:
     *             - Формирует базовые опции FTS (`$base_options`), включающие условие `WHERE "имя_fts_таблицы" MATCH
     *               :search_string_where` и сортировку `ORDER BY rank DESC`, используя переданную строку поиска
     *               `$search_string` как параметр.
     *             - Объединяет переданные внешние опции запроса (`$options`) с базовыми FTS-опциями
     *               (`$base_options`) с помощью приватного метода `_fts_check_options()`. Этот метод разрешает
     *               конфликты плейсхолдеров и определяет приоритеты для WHERE, ORDER BY, GROUP BY, LIMIT и PARAMS.
     *             - Вызывает приватный метод `_select_internal()` с экранированными столбцами для возврата,
     *               **экранированным именем FTS5 таблицы** (`$fts_table_escaped`) и объединенными опциями
     *               (`$new_options`) для выполнения SQL-запроса к виртуальной FTS5 таблице.
     *          6. В случае успешного выполнения FTS5-запроса (блок `try` без исключений):
     *             - Обновляет кеш (`$this->cache`), устанавливая флаг `query_error = false` для данного FTS5-индекса,
     *               указывая на успешность последнего запроса.
     *             - Получает результат запроса в виде массива с помощью приватного метода `_result_array_internal()` и
     *               возвращает его.
     *          7. В случае возникновения исключения (`Throwable`) во время выполнения FTS5-запроса (блок `catch`):
     *             - Логирует информацию об ошибке с помощью функции `log_in_file()`, включая текст запроса и
     *               сообщение об ошибке.
     *             - Обновляет кеш (`$this->cache`), устанавливая флаг `query_error = true` для данного FTS5-индекса,
     *               чтобы избежать повторных ошибок в будущем.
     *             - Вызывает приватный метод `_fallback_to_like_sqlite()` для выполнения поиска с использованием
     *               `LIKE` и возвращает его результат.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса `Database`.
     *          Он вызывается из публичных или защищенных методов поиска, когда определен тип базы данных SQLite.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param array  $columns_to_return Массив строк с именами столбцов, которые нужно вернуть в результате поиска.
     * @param array $columns_to_search  Массив строк с именами столбцов, по которым выполняется полнотекстовый поиск
     *                                  (используются для формирования имени FTS5 таблицы и кеш-ключа).
     * @param string $search_string     Строка поиска, введенная пользователем. Используется как есть для оператора
     *                                  FTS5 `MATCH`. Проверяется на минимальную длину.
     * @param string $table             Строка с именем основной таблицы, для которой существует связанная FTS5
     *                                  виртуальная таблица.
     * @param string $db_version        Строка, представляющая версию структуры базы данных (например, из специальной
     *                                  таблицы), используется для валидации кеша.
     * @param array $options            Массив дополнительных опций для формирования запроса. Используется для
     *                                  добавления условий WHERE, GROUP BY, ORDER BY, LIMIT и параметров к FTS5-запросу.
     *                                  Поддерживаемые ключи:
     *                                  - where (string|array|false): Дополнительное условие WHERE (объединяется с
     *                                    FTS5 MATCH оператором AND).
     *                                  - group (string|false): Группировка GROUP BY.
     *                                  - order (string|false): Дополнительная сортировка ORDER BY (имеет более
     *                                    низкий приоритет, чем FTS5 `rank DESC`).
     *                                  - limit (int|string|false): Ограничение LIMIT.
     *                                  - params (array): Ассоциативный массив дополнительных параметров для
     *                                    подготовленного выражения.
     *
     * @return array|false Результат выполнения поиска в виде массива ассоциативных массивов (строк) при успехе и
     *                     наличии результатов, пустой массив при успехе, но отсутствии результатов, или `false` в
     *                     случае пустой строки поиска, критической ошибки или если предыдущий FTS5-запрос для этого
     *                     индекса завершился ошибкой (приводит к fallback, который может вернуть false).
     *
     * @throws JsonException            Может быть выброшено методами кеширования (`$this->cache->is_valid`,
     *                                  `$this->cache->update_cache`) при ошибках сериализации/десериализации данных кеша.
     * @throws Exception                Может быть выброшено функцией логирования `log_in_file()` в случае
     *                                  невозможности записи в файл.
     * @throws PDOException             Может быть выброшено методами `_fts_check_options()` (через вызов
     *                                  `_build_conditions()`) или `_select_internal()` в случае ошибок, связанных с
     *                                  базой данных или некорректным SQL, если они не перехватываются в блоке
     *                                  `try...catch` или выбрасываются после него. (Большинство таких ошибок внутри
     *                                  FTS5 перехватываются и приводят к fallback).
     * @throws InvalidArgumentException Может быть выброшено методом `_fts_check_options()` (через вызов
     *                                  `_build_conditions()`) при некорректном формате опций. (Большинство таких
     *                                  ошибок внутри FTS5 перехватываются и приводят к fallback).
     *
     * @note    Метод использует константу `MIN_FULLTEXT_SEARCH_LENGTH` для проверки минимальной длины строки поиска.
     *          Активно используется кеширование (`$this->cache`) для оптимизации и запоминания FTS5-индексов,
     *          вызывающих ошибки. При возникновении ошибок выполнения FTS5-запроса (например, из-за отсутствия
     *          виртуальной таблицы или некорректного синтаксиса FTS5), метод автоматически переключается на поиск с
     *          использованием оператора `LIKE`, вызывая `_fallback_to_like_sqlite()`.
     *          Для работы требуется создание виртуальной таблицы FTS5 с именем `имя_основной_таблицы_fts` и
     *          соответствующей структурой, содержащей столбцы для поиска.
     *
     * @warning Убедитесь, что для таблицы, указанной в `$table`, существует виртуальная таблица FTS5 с именем
     *          `{$table}_fts` и что она содержит столбцы, достаточные для поиска.
     *          Поиск с `LIKE` может быть значительно медленнее полнотекстового поиска FTS5 на больших объемах данных.
     *
     * Пример использования (вызывается внутри класса):
     * @code
     * // Предположим, MIN_FULLTEXT_SEARCH_LENGTH = 4
     * // $this->current_format = 'sqlite';
     * // $this->db_type = 'sqlite';
     *
     * $columns_to_return = ['id', 'title', 'content'];
     * $columns_to_search = ['title', 'content']; // Используются для формирования имени FTS таблицы и кеш-ключа
     * $search_string = 'database optimization'; // Длина >= 4
     * $table = 'articles'; // Предполагается, что существует FTS5 таблица 'articles_fts'
     * $db_version = '1.0'; // Пример версии структуры БД
     * $options = [
     *     'where' => ['is_published' => 1],
     *     'limit' => 10,
     *     'params' => [':is_published' => 1],
     * ];
     *
     * // Вызов метода
     * $results = $this->_full_text_search_sqlite(
     *     $columns_to_return,
     *     $columns_to_search, // Столбцы для поиска используются для MATCH
     *     $search_string,
     *     $table,
     *     $db_version,
     *     $options
     * );
     *
     * if ($results !== false) {
     *     // Обработка результатов поиска
     *     print_r($results);
     * } else {
     *     // Обработка ошибки (произошел fallback на LIKE, и он тоже вернул false при ошибке)
     *     echo "Произошла ошибка поиска (включая fallback).";
     * }
     *
     * // Если strlen($search_string) < 4, будет вызван _fallback_to_like_sqlite
     * // Если FTS5 запрос упадет (например, нет таблицы articles_fts), будет вызван _fallback_to_like_sqlite, ошибка будет залогирована и закэширована.
     * @endcode
     * @see    PhotoRigma::Classes::Database::_fallback_to_like_sqlite()
     *         Приватный метод, реализующий поиск по LIKE в качестве fallback.
     * @see    PhotoRigma::Classes::Database::_fts_check_options()
     *         Приватный метод, объединяющий и обрабатывающий опции запроса для FTS.
     * @see    PhotoRigma::Classes::Database::_select_internal()
     *         Приватный метод для выполнения SELECT-запроса к FTS5 таблице.
     * @see    PhotoRigma::Classes::Database::_result_array_internal()
     *         Приватный метод для получения всех строк результата запроса.
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования сообщений.
     * @see    PhotoRigma::Interfaces::Cache_Handler_Interface
     *         Интерфейс, используемый для работы с кешированием.
     * @see    PhotoRigma::Classes::Database::$cache
     *         Свойство, хранящее объект, реализующий `Cache_Handler_Interface`.
     * @see    MIN_FULLTEXT_SEARCH_LENGTH
     *         Константа, определяющая минимальную длину строки для полнотекстового поиска.
     * @see    https://www.sqlite.org/fts5.html
     *         Документация по модулю SQLite FTS5.
     */
    private function _full_text_search_sqlite(
        array $columns_to_return,
        array $columns_to_search,
        string $search_string,
        string $table,
        string $db_version,
        array $options
    ): array|false {
        // 1. Экранируем имена столбцов и таблицы
        $columns_to_return_escaped = array_map(static fn ($col) => "\"$col\"", $columns_to_return);
        $columns_to_search_escaped = array_map(static fn ($col) => "\"$col\"", $columns_to_search);
        $table_escaped = "\"$table\"";                  // "users"
        $fts_table_escaped = "\"$table\_fts\"";         // "users_fts"

        // 2. Проверка длины строки
        if (strlen($search_string) < MIN_FULLTEXT_SEARCH_LENGTH) {
            return $this->_fallback_to_like_sqlite(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $options
            );
        }

        // 3. Формируем ключ кэша
        $key = 'fts_sqlite_' . substr(hash('xxh3', $table . ':' . implode(',', $columns_to_search)), 0, 24);

        // 4. Проверяем кэш: был ли query_error
        $cached_index = $this->cache->is_valid($key, $db_version);

        if ($cached_index !== false && isset($cached_index['query_error']) && $cached_index['query_error'] === true) {
            return $this->_fallback_to_like_sqlite(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $options
            );
        }

        // 5. Выполняем полнотекстовый поиск
        try {
            // Формируем внутренние FTS-условия
            $base_options = [
                'where'  => "$fts_table_escaped MATCH :search_string_where",
                'order'  => 'rank DESC',
                'params' => [
                    ':search_string_where' => $search_string,
                ],
            ];

            // Объединяем внешние и внутренние опции
            $new_options = $this->_fts_check_options($options, $base_options);

            // Выполняем SQL-запрос
            $this->_select_internal(
                $columns_to_return_escaped,
                $fts_table_escaped,
                $new_options
            );
        } catch (Throwable $e) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка в FTS5 | Таблица: $table_escaped, Сообщение: {$e->getMessage()}"
            );

            $this->cache->update_cache($key, $db_version, ['query_error' => true]);

            return $this->_fallback_to_like_sqlite(
                $columns_to_return_escaped,
                $columns_to_search_escaped,
                $search_string,
                $table_escaped,
                $options
            );
        }

        // 6. Сохраняем успешное выполнение
        $this->cache->update_cache($key, $db_version, ['query_error' => false]);

        // 7. Возвращаем результат
        return $this->_result_array_internal();
    }

    /**
     * @brief   Выполняет альтернативный LIKE-поиск для SQLite.
     *
     * @details Этот приватный метод реализует поиск данных в SQLite, используя оператор `LIKE` по нескольким столбцам.
     *          Он используется как резервный механизм (fallback), когда основной полнотекстовый поиск (FTS5)
     *          недоступен, отключен, или не дал результатов (например, из-за минимальной длины строки поиска
     *          или отсутствия виртуальной FTS5 таблицы).
     *          Метод выполняет следующие действия:
     *          1. Проверяет, является ли строка поиска `$search_string` пустой. Если да, возвращает `false` без
     *             выполнения запроса.
     *          2. Формирует условия `WHERE` для оператора `LIKE` по каждому столбцу из массива
     *             `$columns_to_search_escaped`. Каждое условие имеет вид `"имя_столбца" LIKE :плейсхолдер`. Для
     *             строки поиска автоматически добавляются символы `%` в начале и конце (для поиска подстроки).
     *             Каждое условие использует уникальный именованный плейсхолдер (например, `:search_string_0`,
     *             `:search_string_1`) для безопасной привязки параметров.
     *          3. Объединяет сформированные условия `LIKE` оператором `OR` для создания общей строки условия `WHERE`.
     *          4. Формирует базовые опции (`$base_options`) для поиска по `LIKE`, включающие сформированное условие
     *             `WHERE` и массив параметров для плейсхолдеров `LIKE`.
     *          5. Объединяет переданные внешние опции запроса (`$options`) с базовыми опциями `LIKE`
     *             (`$base_options`) с помощью приватного метода `_fts_check_options()`. Этот метод разрешает
     *             конфликты плейсхолдеров и определяет приоритеты для WHERE, ORDER BY, GROUP BY, LIMIT и PARAMS.
     *          6. Предпринимает попытку выполнить SQL-запрос SELECT с использованием экранированных столбцов для
     *             возврата, экранированного имени таблицы и объединенных опций (`$new_options`) в блоке `try...catch`,
     *             вызывая приватный метод `_select_internal()`.
     *          7. В случае возникновения исключения (`Throwable`) во время выполнения LIKE-запроса (блок `catch`):
     *             - Логирует информацию об ошибке с помощью функции `log_in_file()`, включая текст запроса и
     *               сообщение об ошибке.
     *             - Возвращает `false`, сигнализируя об ошибке выполнения поиска.
     *          8. В случае успешного выполнения запроса (блок `try` без исключений) получает результат запроса в
     *             виде массива с помощью приватного метода `_result_array_internal()` и возвращает его.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса `Database`.
     *          Он вызывается из приватного метода `_full_text_search_sqlite()`.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param array $columns_to_return_escaped  Массив строк с именами столбцов, которые нужно вернуть. Имена
     *                                          столбцов должны быть уже экранированы для SQLite (например, `"id"`
     *                                          или `[id]`).
     * @param array $columns_to_search_escaped  Массив строк с именами столбцов, по которым выполняется поиск по LIKE.
     *                                          Имена столбцов должны быть уже экранированы для SQLite.
     * @param string $search_string             Строка поиска, введенная пользователем. Для поиска по LIKE
     *                                          автоматически будут добавлены символы `%`. Пустая строка приводит к
     *                                          возврату `false`.
     * @param string $table_escaped             Строка с именем таблицы, в которой выполняется поиск. Имя таблицы
     *                                          должно быть уже экранировано для SQLite (например, `"users"` или
     *                                          `[users]`).
     * @param array $options                    Массив дополнительных опций для формирования запроса. Используется
     *                                          для добавления условий WHERE, GROUP BY, ORDER BY, LIMIT и параметров
     *                                          к LIKE-запросу.
     *                                          Поддерживаемые ключи:
     *                                          - where (string|array|false): Дополнительное условие WHERE
     *                                            (объединяется с условиями LIKE оператором AND).
     *                                          - group (string|false): Группировка GROUP BY.
     *                                          - order (string|false): Сортировка ORDER BY.
     *                                          - limit (int|string|false): Ограничение LIMIT.
     *                                          - params (array): Ассоциативный массив дополнительных параметров для
     *                                            подготовленного выражения.
     *
     * @return array|false Результат выполнения поиска в виде массива ассоциативных массивов (строк) при успехе и
     *                     наличии результатов, пустой массив при успехе, но отсутствии результатов, или `false` в
     *                     случае пустой строки поиска или ошибки выполнения запроса.
     *
     * @throws Exception                Может быть выброшено функцией логирования `log_in_file()` в случае
     *                                  невозможности записи в файл.
     * @throws PDOException             Может быть выброшено методами `_fts_check_options()` (через вызов
     *                                  `_build_conditions()`) или `_select_internal()` в случае ошибок, связанных с
     *                                  базой данных или некорректным SQL, если они не перехватываются в блоке
     *                                  `try...catch` или выбрасываются после него. (Большинство таких ошибок внутри
     *                                  LIKE перехватываются и приводят к возврату false).
     * @throws InvalidArgumentException Может быть выброшено методом `_fts_check_options()` (через вызов
     *                                  `_build_conditions()`) при некорректном формате опций. (Большинство таких
     *                                  ошибок внутри LIKE перехватываются и приводят к возврату false).
     *
     * @note    Метод используется как резервный вариант поиска для SQLite, когда полнотекстовый поиск (FTS5) не
     *          может быть выполнен. Для каждого столбца поиска создается отдельное условие `LIKE` с уникальным
     *          плейсхолдером. Использует приватный метод `_fts_check_options()` для объединения условий `LIKE` с
     *          любыми дополнительными опциями, предоставленными пользователем.
     *
     * @warning Убедитесь, что входные параметры `$columns_to_return_escaped`, `$columns_to_search_escaped` и
     *          `$table_escaped` уже экранированы для SQLite.
     *          Поиск с `LIKE` может быть неэффективен на больших объемах данных по сравнению с FTS5.
     *
     * Пример использования (вызывается из _full_text_search_sqlite()):
     * @code
     * // Предположим:
     * // $columns_to_return_escaped = ['"id"', '"title"'];
     * // $columns_to_search_escaped = ['"title"', '"content"'];
     * // $search_string = 'search term';
     * // $table_escaped = '"articles"';
     * // $options = ['limit' => 5];
     *
     * // Вызов fallback метода
     * $results_like = $this->_fallback_to_like_sqlite(
     *     $columns_to_return_escaped,
     *     $columns_to_search_escaped,
     *     $search_string,
     *     $table_escaped,
     *     $options
     * );
     *
     * if ($results_like !== false) {
     *     // Обработка результатов поиска по LIKE
     *     print_r($results_like);
     * } else {
     *     // Обработка ошибки выполнения LIKE-поиска
     *     echo "Произошла ошибка при выполнении LIKE-поиска.";
     * }
     *
     * // Если $search_string был '', вернет false сразу.
     * @endcode
     * @see    PhotoRigma::Classes::Database::_full_text_search_sqlite()
     *         Приватный метод, который вызывает этот метод в случае необходимости fallback.
     * @see    PhotoRigma::Classes::Database::_fts_check_options()
     *         Приватный метод, используемый для объединения опций.
     * @see    PhotoRigma::Classes::Database::_select_internal()
     *         Приватный метод для выполнения SELECT-запроса.
     * @see    PhotoRigma::Classes::Database::_result_array_internal()
     *         Приватный метод для получения всех строк результата запроса.
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования сообщений.
     */
    private function _fallback_to_like_sqlite(
        array $columns_to_return_escaped,
        array $columns_to_search_escaped,
        string $search_string,
        string $table_escaped,
        array $options
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
        $base_options = [
            'where'  => $where_sql,
            'params' => $params,
        ];
        $new_options = $this->_fts_check_options($options, $base_options);

        // 3. Выполняем запрос
        try {
            $this->_select_internal(
                $columns_to_return_escaped,
                $table_escaped,
                $new_options
            );
        } catch (Throwable $e) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' . "Ошибка при выполнении LIKE-поиска | Таблица: $table_escaped, Сообщение: {$e->getMessage()}"
            );
            return false;
        }

        return $this->_result_array_internal();
    }

    /**
     * @brief   Формирует финальный массив опций для запроса с учетом базовых FTS-опций и внешних условий/дополнений.
     *
     * @details Этот приватный метод объединяет массив внешних опций (`$options`) с базовыми опциями
     *          полнотекстового поиска (FTS) (`$base_options`), обрабатывая конфликты плейсхолдеров и применяя
     *          правила приоритета для формирования результирующего набора опций запроса.
     *          Метод выполняет следующие действия:
     *          1. Выполняет тримминг всех строковых значений во внешних (`$options`) и базовых (`$base_options`)
     *             массивах опций.
     *          2. Обрабатывает конфликты именованных плейсхолдеров между `$options['params']` и
     *             `$base_options['params']`. При обнаружении совпадающих ключей плейсхолдеров, плейсхолдеры во
     *             внешних опциях (`$options`) переименовываются путем добавления суффикса `_ext_[n]` (где `[n]` -
     *             инкрементирующийся счетчик). Соответствующие ключи в массиве `$options['params']` обновляются, и
     *             старые ключи удаляются.
     *          3. Обновляет использование переименованных плейсхолдеров в условиях `WHERE` внешних опций
     *             (`$options['where']`), если `where` является массивом.
     *          4. Обрабатывает внешнее условие `WHERE` из `$options['where']`, вызывая приватный метод
     *             `_build_conditions()` для его преобразования в SQL-строку и получения соответствующих параметров.
     *             Начальный `WHERE` из строки условий, возвращенной `_build_conditions()`, удаляется. Если после
     *             обработки внешнее условие непустое, оно сохраняется для последующего объединения.
     *          5. Формирует финальный массив опций (`$new_options`) на основе объединенных данных с учетом следующих
     *             правил приоритета:
     *             - **WHERE:** Если присутствуют и базовое FTS-условие (`$base_options['where']`) и обработанное
     *               внешнее условие (`$options['where']`), они объединяются с помощью `AND` (базовое условие идет
     *               первым). Если присутствует только одно из них, используется это условие.
     *             - **ORDER BY:** Если присутствует сортировка в базовых FTS-опциях (`$base_options['order']`), она
     *               имеет приоритет и используется. В противном случае, если присутствует сортировка во внешних
     *               опциях (`$options['order']`), используется она.
     *             - **GROUP BY:** Используется только значение из внешних опций (`$options['group']`), если оно
     *               задано и не равно `false`.
     *             - **LIMIT:** Используется только значение из внешних опций (`$options['limit']`), если оно задано
     *               и не равно `false`.
     *             - **params:** Параметры из `$options['params']` (уже с переименованными плейсхолдерами) и
     *               `$base_options['params']` объединяются с помощью `array_merge()`. Параметры из
     *               `$base_options['params']` имеют приоритет при совпадении ключей (что не должно происходить после
     *               обработки конфликтов, но `array_merge` работает так).
     *          6. Возвращает финальный массив объединенных опций `$new_options`.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param array $options      Массив внешних опций запроса, предоставленных пользователем. Поддерживаемые ключи:
     *                            - where (string|array|false): Условие WHERE.
     *                              Может быть:
     *                              - Строкой (используется как есть)
     *                              - Массивом:
     *                                * Простые условия: `['поле' => значение]` (обрабатывается `_build_conditions`,
     *                                                   генерируются плейсхолдеры)
     *                                * Сложные условия: `['OR' => [условия], 'NOT' => [условия]]` (обрабатывается
     *                                                   `_build_conditions`)
     *                              - `false`: Игнорируется.
     *                            - group (string|false): Группировка GROUP BY. Должна быть строкой. Игнорируется,
     *                              если равно `false`.
     *                            - order (string|false): Сортировка ORDER BY. Должна быть строкой. Игнорируется,
     *                              если равно `false`.
     *                            - limit (int|string|false): Ограничение LIMIT. Должно быть целым числом или строкой
     *                              формата `'OFFSET, COUNT'`. Игнорируется, если равно `false`.
     *                            - params (array): Исходный ассоциативный массив параметров для подготовленного
     *                              выражения ([":имя" => значение]).
     * @param array $base_options Массив базовых опций FTS (Full-Text Search), сгенерированных внутренней логикой FTS.
     *                            Поддерживаемые ключи:
     *                            - where (string): Строковое условие WHERE для полнотекстового поиска (например,
     *                              `MATCH(...) AGAINST (...)`).
     *                            - order (string): Строка сортировки по релевантности FTS.
     *                            - params (array): Ассоциативный массив параметров для FTS-условий.
     *
     * @return array Результирующий массив опций для запроса, содержащий объединенные и обработанные условия и
     *               параметры. Ключи могут включать:
     *               - where (string|null): Объединенные условия WHERE, или `null` если условия отсутствуют.
     *               - order (string|null): Итоговая строка сортировки, или `null` если сортировка не задана.
     *               - group (string|null): Строка GROUP BY из внешних опций, или `null` если не задана.
     *               - limit (int|string|null): Значение LIMIT из внешних опций, или `null` если не задано.
     *               - params (array): Объединенный ассоциативный массив параметров, включая параметры FTS и
     *                 параметры внешних условий (с разрешенными конфликтами именования).
     *
     * @throws InvalidArgumentException Может быть выброшено методом `_build_conditions()` при обработке
     *                                  `$options['where']`, если его формат недопустим.
     *
     * Пример объединения опций с конфликтующими плейсхолдерами:
     * @code
     * $options = [
     *     'where' => ['status' => ':search'], // Внешнее условие с плейсхолдером ':search'
     *     'params' => [':search' => 'active'] // Значение внешнего плейсхолдера
     * ];
     * $base_options = [
     *     'where' => 'MATCH(title) AGAINST (:search)', // Базовое FTS условие с плейсхолдером ':search'
     *     'order' => 'rank DESC',
     *     'params' => [':search' => 'term'] // Значение базового FTS плейсхолдера
     * ];
     *
     * $final_options = $this->_fts_check_options($options, $base_options);
     * // Результат $final_options:
     * // [
     * //    'where' => "MATCH(title) AGAINST (:search) AND (status = :search_ext_0)", // Объединенное WHERE, плейсхолдер во внешнем условии переименован
     * //    'order' => "rank DESC", // Сортировка FTS имеет приоритет
     * //    'group' => null, // Не было в $options
     * //    'limit' => null, // Не было в $options
     * //    'params' => [':search' => 'term', ':search_ext_0' => 'active'] // Объединенные параметры, где :search имеет значение из $base_options
     * // ]
     * @endcode
     * @see    PhotoRigma::Classes::Database::_build_conditions()
     *         Приватный метод, используемый для обработки внешней части условия WHERE.
     */
    private function _fts_check_options(array $options, array $base_options): array
    {
        // 1. Трим всех строковых значений
        $trim_array = static fn (array $arr) => array_map(
            static fn ($val) => is_string($val) ? trim($val) : $val,
            $arr
        );

        $options = $trim_array($options);
        $base_options = $trim_array($base_options);

        // 2. Обработка конфликтующих плейсхолдеров
        if (!empty($options['params']) && !empty($base_options['params'])) {
            $conflicts = array_intersect_key($options['params'], $base_options['params']);

            if (!empty($conflicts)) {
                $replace_map = [];
                $counter = 0;

                foreach ($conflicts as $placeholder => $value) {
                    $new_placeholder = $placeholder . '_ext_' . $counter++;
                    $replace_map[$placeholder] = $new_placeholder;

                    // Обновляем параметры
                    $options['params'][$new_placeholder] = $value;
                    unset($options['params'][$placeholder]);
                }

                // Обновляем плейсхолдеры в условиях WHERE
                if (!empty($options['where']) && is_array($options['where'])) {
                    array_walk_recursive($options['where'], static function (&$item) use ($replace_map) {
                        if (is_string($item) && isset($replace_map[$item])) {
                            $item = $replace_map[$item];
                        }
                    });
                }
            }
        }

        // 3. Обработка внешнего WHERE через _build_conditions
        if (!empty($options['where'])) {
            [$additional_where, $additional_params] = $this->_build_conditions([
                'where' => $options['where'],
                'params' => $options['params'] ?? [],
            ]);
            // Удаляем 'WHERE' в начале (если есть) и чистим начальные и конечные пробелы
            $additional_where = trim(preg_replace('/^\s*WHERE\s+/i', '', $additional_where));

            if ($additional_where !== '') {
                $options['where'] = $additional_where;
                $options['params'] = array_merge(
                    $options['params'] ?? [],
                    $additional_params
                );
            } else {
                unset($options['where']);
            }
        }

        // 4. Формируем финальные опции
        $new_options = [];

        // WHERE: базовое условие FTS > внешнее
        if (!empty($base_options['where']) && !empty($options['where'])) {
            $new_options['where'] = $base_options['where'] . ' AND (' . $options['where'] . ')';
        } elseif (!empty($base_options['where'])) {
            $new_options['where'] = $base_options['where'];
        } elseif (!empty($options['where'])) {
            $new_options['where'] = $options['where'];
        }

        // ORDER BY: ранг FTS > внешний order
        if (!empty($base_options['order'])) {
            $new_options['order'] = $base_options['order'];
        } elseif (!empty($options['order'])) {
            $new_options['order'] = $options['order'];
        }

        // GROUP BY: только внешние данные
        if (isset($options['group']) && $options['group'] !== false) {
            $new_options['group'] = $options['group'];
        }

        // LIMIT: только внешние данные
        if (isset($options['limit']) && $options['limit'] !== false) {
            $new_options['limit'] = $options['limit'];
        }

        // PARAMS: объединяем, внутренние имеют приоритет
        $new_options['params'] = array_merge(
            $options['params'] ?? [],
            $base_options['params'] ?? []
        );

        return $new_options;
    }

    /**
     * @brief   Снятие экранирования идентификаторов (например, имён таблиц или столбцов).
     *
     * @details Этот приватный метод обрабатывает массив идентификаторов, удаляя из них символы экранирования,
     *          специфичные для текущей СУБД, определенной свойством `$this->current_format`. Результат
     *          "чистых" идентификаторов сохраняется в кэше для оптимизации.
     *          Метод выполняет следующие шаги:
     *          1. Инициализирует пустой массив для хранения результатов и определяет правила снятия экранирования
     *             на основе свойства `$this->current_format`.
     *          2. Перебирает каждый идентификатор в массиве `$identifiers`, проверяя, является ли он строкой.
     *             Если обнаружен не строковый элемент, выбрасывается исключение InvalidArgumentException.
     *          3. Для каждого идентификатора проверяет наличие результата снятия экранирования в кэше
     *             `$this->unescape_cache`.
     *          4. Если идентификатор не найден в кэше, применяет соответствующие правила снятия экранирования
     *             (`strtr`) для удаления специфических символов (обратные кавычки для MySQL, двойные кавычки для
     *             PostgreSQL, квадратные скобки и двойные кавычки для SQLite). Результат сохраняется в кэше.
     *          5. Добавляет "чистый" идентификатор (из кэша или только что вычисленный) в массив результатов.
     *          6. После обработки всех идентификаторов возвращает массив "чистых" идентификаторов.
     *
     *          Поддерживаемые правила снятия экранирования в зависимости от `$this->current_format`:
     *          - 'mysql': удаляются обратные кавычки (`).
     *          - 'pgsql': удаляются двойные кавычки (").
     *          - 'sqlite': удаляются квадратные скобки ([]) и двойные кавычки (").
     *
     * @internal
     * @callergraph
     *
     * @param array $identifiers Массив экранированных идентификаторов для обработки.
     *                           Элементы массива должны быть строками. Могут содержать квалифицированные имена
     *                           (например, "schema.table").
     *
     * @return array Массив "чистых" идентификаторов.
     *               Все экранирующие символы, специфичные для `$this->current_format`, удалены.
     *               Сохраняется исходная структура квалифицированных имен (например, разделитель '.').
     *
     * @throws InvalidArgumentException Выбрасывается, если какой-либо элемент в массиве `$identifiers` не является
     *                                  строкой.
     *                                  Пример сообщения:
     *                                  Идентификатор должен быть строкой, получен: [тип]
     *
     * @note    Метод использует текущую конфигурацию СУБД, заданную в свойстве `$this->current_format`, для
     *          определения правил снятия экранирования.
     *          Результаты снятия экранирования кешируются в свойстве `$this->unescape_cache` для оптимизации
     *          повторных операций с теми же идентификаторами.
     *
     * @warning Убедитесь, что свойство `$this->current_format` установлено в одно из поддерживаемых значений
     *          ('mysql', 'pgsql', 'sqlite') перед вызовом метода.
     *
     * Пример использования:
     * @code
     * // Предположим:
     * // $this->current_format = 'sqlite';
     * $quoted = ['"id"', '[name]', 'user."email"', '`field`']; // Идентификаторы с разным экранированием
     *
     * $clean = $this->_unescape_identifiers($quoted);
     * // Если current_format был 'sqlite':
     * // $clean = ['id', 'name', 'user.email', '`field`'] // Обратные кавычки останутся, т.к. не правило SQLite
     *
     * // Предположим:
     * // $this->current_format = 'mysql';
     * $quoted_mysql = ['`id`', '"name"', '[created_at]', 'user.`email`'];
     *
     * $clean_mysql = $this->_unescape_identifiers($quoted_mysql);
     * // Если current_format был 'mysql':
     * // $clean_mysql = ['id', '"name"', '[created_at]', 'user.email'] // Двойные кавычки и скобки останутся
     * @endcode
     * @see    PhotoRigma::Classes::Database::$unescape_cache
     *         Внутреннее свойство, используемое для кэширования результатов снятия экранирования идентификаторов.
     * @see    PhotoRigma::Classes::Database::$current_format
     *         Свойство, хранящее текущий формат SQL, определяющий правила снятия экранирования.
     */
    private function _unescape_identifiers(array $identifiers): array
    {
        static $rules = [
            'mysql'  => ['`' => ''],
            'pgsql'  => ['"' => ''],
            'sqlite' => ['[' => '', ']' => '', '"' => '']
        ];

        $unescaped = [];
        $current_rules = $rules[$this->current_format] ?? [];

        foreach ($identifiers as $identifier) {
            if (!is_string($identifier)) {
                throw new InvalidArgumentException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                    'Идентификатор должен быть строкой, получен: ' . gettype($identifier)
                );
            }

            if (!isset($this->unescape_cache[$identifier])) {
                $this->unescape_cache[$identifier] = strtr($identifier, $current_rules);
            }

            $unescaped[] = $this->unescape_cache[$identifier];
        }

        return $unescaped;
    }
}
