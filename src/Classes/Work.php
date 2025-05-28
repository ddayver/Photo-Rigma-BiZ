<?php

/**
 * Файл содержит класс Work, который является основным классом приложения. Объединяет подклассы для выполнения задач.
 *
 * Этот файл содержит основной класс приложения `Work`, который объединяет подклассы для выполнения различных задач.
 * Класс предоставляет:
 * - Хранилище для данных о конфигурации и языковых переменных проекта.
 * - Механизмы для работы с безопасностью, включая проверку входных данных и защиту от спам-ботов.
 * - Механизмы для работы с изображениями через интерфейс `Work_Image_Interface`.
 * - Интеграцию с интерфейсом `Cache_Handler_Interface` для кеширования данных.
 * - Кеширование используется для хранения настроек и языковых переменных.
 * - Класс `Work_Helper` предоставляет методы для очистки строк, преобразования размеров и проверки MIME-типов.
 * - Интеграцию с интерфейсами для работы с базой данных, обработкой данных.
 * - Механизмы для управления директориями, пользовательской статистикой и другими компонентами системы.
 *
 * @author    Dark Dayver
 * @version   0.5.0
 * @since     2025-05-29
 * @namespace PhotoRigma\\Classes
 * @package   PhotoRigma
 *
 * @section   Work_Main_Functions Основные функции
 *            - Хранилище для данных о конфигурации и языковых переменных проекта.
 *            - Механизмы для работы с безопасностью (проверка входных данных, защита от спам-ботов).
 *            - Работа с изображениями (загрузка, обработка, изменение размеров).
 *            - Формирование данных для шаблонов (меню, статистика, блок пользователя).
 *            - Реализация основной логики приложения (работа с категориями, новостями, рейтингами).
 *            - Вспомогательные методы (очистка строк, преобразование размеров, проверка MIME-типов).
 *            - Кеширование данных через `Cache_Handler_Interface` для повышения производительности.
 *
 * @uses      \PhotoRigma\Interfaces\Work_Security_Interface Интерфейс для работы с безопасностью приложения.
 * @uses      \PhotoRigma\Interfaces\Work_Image_Interface Интерфейс, определяющий методы для работы с изображениями.
 * @uses      \PhotoRigma\Interfaces\Work_Template_Interface Интерфейс для работы с шаблонами.
 * @uses      \PhotoRigma\Interfaces\Work_Helper_Interface Интерфейс для вспомогательных функций, таких как очистка
 *                                                         строк и проверка MIME-типов.
 * @uses      \PhotoRigma\Interfaces\Cache_Handler_Interface Интерфейс для работы с кешированием данных.
 * @uses      \PhotoRigma\Include\log_in_file() Записывает сообщение об ошибке в лог-файл.
 *
 * @note      Этот файл является частью системы PhotoRigma и играет ключевую роль в организации работы приложения.
 *            Реализованы меры безопасности для предотвращения несанкционированного доступа и повреждения данных.
 *            Кеширование используется для хранения настроек и языковых переменных, что повышает производительность
 *            приложения.
 *
 * @copyright Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license   MIT License {@link https://opensource.org/licenses/MIT}
 *            Разрешается использовать, копировать, изменять, объединять, публиковать, распространять,
 *            сублицензировать и/или продавать копии программного обеспечения, а также разрешать лицам, которым
 *            предоставляется данное программное обеспечение, делать это при соблюдении следующих условий:
 *            - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые
 *              части программного обеспечения.
 */

namespace PhotoRigma\Classes;

use DirectoryIterator;
use Exception;
use InvalidArgumentException;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use PDOException;
use PhotoRigma\Interfaces\Cache_Handler_Interface;
use PhotoRigma\Interfaces\Database_Interface;
use PhotoRigma\Interfaces\User_Interface;
use PhotoRigma\Interfaces\Work_Interface;
use Random;
use RuntimeException;

use function PhotoRigma\Include\log_in_file;

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
 * Основной класс приложения.
 *
 * Класс `Work` является центральной точкой приложения и предоставляет единый интерфейс для работы с различными
 * компонентами системы. Он реализует интерфейс `Work_Interface` и интегрирует функционал следующих классов:
 * - `Work_Security`: Механизмы для работы с безопасностью (проверка входных данных, защита от спам-ботов).
 * - `Work_Image`: Работа с изображениями (загрузка, обработка, изменение размеров).
 * - `Work_Template`: Формирование данных для шаблонов (меню, статистика, блок пользователя).
 * - `Work_CoreLogic`: Реализация основной логики приложения (работа с категориями, новостями, рейтингами).
 * - `Work_Helper`: Вспомогательные методы (очистка строк, преобразование размеров, проверка MIME-типов).
 * - `Cache_Handler_Interface`: Система кеширования для оптимизации работы приложения.
 * Все методы данного класса являются фасадами, которые перенаправляют вызовы на соответствующие методы дочерних
 * классов.
 *
 * @property      array $config Массив, хранящий конфигурацию приложения.
 * @property-read array $lang   Массив с языковыми переменными.
 *
 * @uses \PhotoRigma\Interfaces\Work_Interface Интерфейс, который реализует данный класс.
 * @uses \PhotoRigma\Classes\Work_Security Класс для работы с безопасностью.
 * @uses \PhotoRigma\Classes\Work_Image Класс для работы с изображениями.
 * @uses \PhotoRigma\Classes\Work_Template Класс для работы с шаблонами.
 * @uses \PhotoRigma\Classes\Work_CoreLogic Класс для реализации основной логики приложения.
 * @uses \PhotoRigma\Classes\Work_Helper Класс для вспомогательных методов.
 * @uses \PhotoRigma\Interfaces\Cache_Handler_Interface Интерфейс для работы с кешем.
 */
class Work implements Work_Interface
{
    /** @var array Массив, хранящий конфигурацию приложения. */
    private array $config;
    /** @var \PhotoRigma\Classes\Work_Security Объект для работы с безопасностью. */
    private Work_Security $security;
    /** @var \PhotoRigma\Classes\Work_Image Объект класса `Work_Image` для работы с изображениями. */
    private Work_Image $image;
    /** @var \PhotoRigma\Classes\Work_Template Объект для работы с шаблонами. */
    private Work_Template $template;
    /** @var \PhotoRigma\Classes\Work_CoreLogic Объект для основной логики приложения. */
    private Work_CoreLogic $core_logic;
    /** @var \PhotoRigma\Interfaces\Cache_Handler_Interface Объект для работы с кешем. */
    private Cache_Handler_Interface $cache;
    /** @var array Массив с языковыми переменными. */
    private array $lang = [];
    /** @var array Массив, привязанный к глобальному массиву $_SESSION. */
    private array $session;

    /**
     * Конструктор класса.
     *
     * Этот метод вызывается автоматически при создании нового объекта класса. Используется для инициализации
     * основных компонентов приложения:
     * - Загрузка конфигурации через кеш (метод `_load_cached_config`).
     * - Инициализация дочерних классов: Work_Security, Work_Image, Work_Template, Work_CoreLogic.
     * - Подключение сессии для хранения пользовательских данных.
     * - Инициализация системы кеширования через интерфейс `Cache_Handler_Interface`.
     *
     * @param Database_Interface      $db      Объект для работы с базой данных.
     *                                         Должен быть экземпляром класса, реализующего интерфейс
     *                                         `Database_Interface`.
     * @param array                   $config  Конфигурация приложения.
     *                                         Должна содержать ключи, такие как 'app_name', 'debug_mode' и другие
     *                                         настройки.
     *                                         Пример: ['app_name' => 'PhotoRigma', 'debug_mode' => true].
     * @param array                  &$session Ссылка на массив сессии для хранения пользовательских данных.
     *                                         Массив должен быть доступен для записи и чтения.
     * @param Cache_Handler_Interface $cache   Объект для работы с системами кеширования.
     *                                         Должен быть экземпляром класса, реализующего интерфейс
     *                                         `Cache_Handler_Interface`. Используется для загрузки конфигурации из
     *                                         кеша и других операций с кешем.
     *
     * @throws Exception Выбрасывается в случае ошибок при загрузке конфигурации или инициализации компонентов.
     *
     * @note    В конструкторе инициализируются следующие дочерние классы:
     *          - `Work_Security`: Для работы с безопасностью.
     *          - `Work_Image`: Для работы с изображениями.
     *          - `Work_Template`: Для работы с шаблонами.
     *          - `Work_CoreLogic`: Для реализации бизнес-логики приложения.
     *          Также используются:
     *          - Константа `KEY_SESSION` для хранения пользовательских данных в сессии.
     *          - Метод `_load_cached_config` для загрузки конфигурации из кеша.
     *
     * @warning Если файл кеша недоступен или данные в нём некорректны, это может привести к ошибкам инициализации.
     *          Ошибки обрабатываются через исключения и не записываются в лог напрямую.
     *
     * @uses \PhotoRigma\Classes\Work::$config Свойство, содержащее конфигурацию приложения.
     * @uses \PhotoRigma\Classes\Work::$security Свойство, содержащее объект `Work_Security`.
     * @uses \PhotoRigma\Classes\Work::$image Свойство, содержащее объект `Work_Image`.
     * @uses \PhotoRigma\Classes\Work::$template Свойство, содержащее объект `Work_Template`.
     * @uses \PhotoRigma\Classes\Work::$core_logic Свойство, содержащее объект `Work_CoreLogic`.
     * @uses \PhotoRigma\Classes\Work::$cache Свойство, содержащее объект `Cache_Handler_Interface` для работы с кешем.
     * @uses \PhotoRigma\Classes\Work::_load_cached_config() Метод для загрузки конфигурации через кеш.
     */
    public function __construct(Database_Interface $db, array $config, array &$session, Cache_Handler_Interface $cache)
    {
        if (!isset($session[KEY_SESSION])) {
            $session[KEY_SESSION] = [];
        }
        $this->session = &$session[KEY_SESSION];
        $this->config = $config;
        $this->cache = $cache;

        // Загружаем конфигурацию через кеш
        $cached_config = $this->_load_cached_config($db);
        $this->config = array_merge($this->config, $cached_config);

        // Инициализация подклассов
        $this->security = new Work_Security($this->session);
        $this->image = new Work_Image($this->config);
        $this->template = new Work_Template($db, $this->config);
        $this->core_logic = new Work_CoreLogic($db, $this->config, $this);
    }

    /**
     * Проверяет актуальность кеша конфигурации и обновляет его при необходимости.
     *
     * Этот метод выполняет следующие действия:
     * 1. Получает дату последнего изменения таблицы конфигурации (`TBL_CONFIG`) из таблицы временных меток
     *    (`TBL_CHANGE_TIMESTAMP`).
     * 2. Использует систему кеширования через `$this->cache` для проверки актуальности данных:
     *    - Если кеш актуален, данные загружаются из кеша.
     *    - Если кеш устарел или отсутствует, данные загружаются из базы данных и записываются в кеш.
     * 3. Загружает версию базы данных из таблицы `db_version`.
     * 4. Читает версию приложения из файла по пути: `WORK_DIR . '/config/ver.php'` (без использования `eval()` или
     *    `include()` — только парсинг строки `$ver = '...'`).
     * 5. Возвращает ассоциативный массив с данными конфигурации, включая поля:
     *    - `'db_version'` — версия БД,
     *    - `'code_version'` — версия приложения.
     *
     * @internal
     *
     * @param Database_Interface $db Объект базы данных для выполнения запросов.
     *                               Должен быть экземпляром класса, реализующего интерфейс `Database_Interface`.
     *
     * @return array Ассоциативный массив с данными конфигурации.
     *               Например: ['site_name' => 'PhotoRigma', 'theme' => 'dark', 'db_version' => '0.4.4',
     *                         'code_version' => '0.4.3'].
     *
     * @throws RuntimeException Выбрасывается в следующих случаях:
     *                          - Не удалось получить дату последнего изменения таблицы `TBL_CONFIG` из таблицы
     *                            `TBL_CHANGE_TIMESTAMP`.
     *                          - Не удалось загрузить данные из таблицы `TBL_CONFIG`.
     *                          - Не удалось записать данные в кеш.
     *                          - Не удалось получить версию из таблицы `db_version`.
     *                          - Не удалось прочитать файл версии `ver.php`.
     *                          - Файл `ver.php` не содержит корректную строку `$ver = '...'`.
     *                          - Значение `$ver` не является непустой строкой.
     * @throws Exception        Выбрасывается при ошибках выполнения SQL-запроса через `$db->select()`.
     *
     * @note    Для указания таблиц базы данных используются константы:
     *          - `TBL_CONFIG`: Таблица с настройками сервера. Например: '`config`'.
     *          - `TBL_CHANGE_TIMESTAMP`: Таблица с временными метками изменений. Например: '`change_timestamp`'.
     *          Метод использует систему кеширования через объект `$this->cache` (реализующий интерфейс
     *          `Cache_Handler_Interface`) для проверки актуальности данных (`is_valid`) и записи новых данных в кеш
     *          (`update_cache`).
     *
     * @warning Убедитесь, что:
     *          - Таблица `TBL_CHANGE_TIMESTAMP` содержит актуальные временные метки изменений для таблицы `TBL_CONFIG`.
     *          - Система кеширования настроена и доступна для чтения/записи.
     *          - Файл `ver.php` существует по указанному пути и содержит строго одну строку вида:
     *            $ver = 'x.x.x';
     *          Несоблюдение этих условий может привести к ошибкам при работе с кешем.
     *
     * @uses \PhotoRigma\Classes\Work::$config Свойство, содержащее конфигурацию приложения.
     * @uses \PhotoRigma\Interfaces\Database_Interface Интерфейс для работы с базой данных.
     * @uses \PhotoRigma\Interfaces\Cache_Handler_Interface Интерфейс для работы с кешем.
     * @uses \PhotoRigma\Classes\Cache_Handler::is_valid() Метод для проверки актуальности данных в кеше.
     * @uses \PhotoRigma\Classes\Cache_Handler::update_cache() Метод для записи данных в кеш.
     */
    private function _load_cached_config(Database_Interface $db): array
    {
        // Получаем время последнего изменения таблицы TBL_CONFIG из таблицы TBL_CHANGE_TIMESTAMP
        $table_name = str_replace('`', '', TBL_CONFIG); // Убираем символы экранирования
        $db->select('last_update', TBL_CHANGE_TIMESTAMP, [
            'where'  => '`table_name` = :table_name',
            'params' => [':table_name' => $table_name],
        ]);
        $timestamp_result = $db->result_row();

        // Если время последнего изменения таблицы не найдено, выбрасываем исключение
        if (!$timestamp_result || !isset($timestamp_result['last_update'])) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить дату последнего изменения таблицы: $table_name"
            );
        }

        // Преобразуем дату последнего изменения в timestamp для использования в качестве контрольной суммы
        $db_last_update = strtotime($timestamp_result['last_update']);

        // Проверяем актуальность кеша
        $config_data = $this->cache->is_valid('config_cache', $db_last_update);
        if (!$config_data) {
            // Если кеш устарел или отсутствует, загружаем данные из БД
            $db->select('*', TBL_CONFIG, ['params' => []]);
            $result = $db->result_array();

            // Загружаем все данные из таблицы конфигурации
            if ($result === false) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Не удалось загрузить данные из таблицы: ' . TBL_CONFIG
                );
            }

            // Преобразуем данные в ассоциативный массив, где ключи — имена параметров, значения — их значения
            $config_data = array_column($result, 'value', 'name');

            // Обновляем кеш новыми данными
            if (!$this->cache->update_cache('config_cache', $db_last_update, $config_data)) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Не удалось записать данные в кеш: config_cache'
                );
            }
        }

        // Получение версии базы данных
        $db->select('ver', 'db_version', ['limit' => 1]);

        $version_data = $db->result_row();

        if (!is_array($version_data) || !isset($version_data['ver'])) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: 'global') . ') | ' . 'Не удалось получить корректную версию из таблицы db_version'
            );
        }

        /** @noinspection PhpAutovivificationOnFalseValuesInspection */
        $config_data['db_version'] = $version_data['ver'];

        // Чтение версии приложения из файла ver.php через парсинг содержимого
        $version_file = WORK_DIR . '/config/ver.php';

        if (!is_file($version_file)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: 'global') . ") | Не найден файл версии: $version_file"
            );
        }

        $content = file_get_contents($version_file);

        // Регулярное выражение для поиска строки вида: $ver = '...';
        $pattern = '/^\\$ver\s*=\s*[\'"]([^\'"]+)[\'"]\s*;?\s*$/m';

        if (!preg_match($pattern, $content, $matches)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: 'global') . ") | Недопустимое содержимое файла версии. Должна быть только строковая переменная \$ver."
            );
        }

        // Получаем значение версии
        $ver = $matches[1];

        if (!is_string($ver) || trim($ver) === '') {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: 'global') . ") | Значение \$ver должно быть не пустой строкой"
            );
        }

        $config_data['code_version'] = $ver;

        return $config_data;
    }

    /**
     * Магический метод для получения значений свойств `$config` и `$lang`.
     *
     * Этот метод вызывается автоматически при попытке получить значение недоступного свойства.
     * Доступ разрешён только к свойствам `$config` и `$lang`. Если запрашивается другое свойство, выбрасывается
     * исключение InvalidArgumentException.
     *
     * @param string $name Имя свойства:
     *                     - Допустимые значения: 'config', 'lang'.
     *                     - Если указано другое имя, выбрасывается исключение.
     *
     * @return array Значение свойства `$config` или `$lang` (оба являются массивами).
     *
     * @throws InvalidArgumentException Если запрашиваемое свойство не существует или недоступно.
     *
     * @note    Этот метод предназначен только для доступа к свойствам `$config` и `$lang`.
     *          Любые другие запросы будут игнорироваться с выбросом исключения.
     *
     * @warning Попытка доступа к несуществующему свойству вызовет исключение.
     *          Убедитесь, что вы запрашиваете только допустимые свойства.
     *
     * @uses \PhotoRigma\Classes\Work::$lang Свойство, содержащее языковые данные.
     * @uses \PhotoRigma\Classes\Work::$config Свойство, содержащее конфигурацию.
     */
    public function __get(string $name): array
    {
        return match ($name) {
            'config' => $this->config,
            'lang'   => $this->lang,
            default  => throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не существует | Получено: '$name'"
            ),
        };
    }

    /**
     * Устанавливает значение свойства `$config`.
     *
     * Этот метод вызывается автоматически при попытке установить значение недоступного свойства.
     * Доступ разрешён только к свойству `$config`. Если запрашивается другое свойство, выбрасывается исключение
     * Exception.
     * Значение `$config` должно быть массивом, где ключи и значения являются строками.
     * При успешном обновлении конфигурации:
     * - Изменения логируются с помощью функции log_in_file (за исключением ключей из списка $exclude_from_logging).
     * - Обновлённая конфигурация передаётся в дочерние классы ($this->image, $this->template, $this->core_logic)
     *   через их свойства config.
     *
     * @param string $name  Имя свойства:
     *                      - Допустимое значение: 'config'.
     *                      - Если указано другое имя, выбрасывается исключение.
     * @param array  $value Значение свойства:
     *                      - Должен быть массивом, где ключи и значения являются строками.
     *
     * @throws InvalidArgumentException Если значение некорректно (не массив или содержатся некорректные
     *                                  ключи/значения).
     * @throws Exception Если запрашивается несуществующее свойство.
     *
     * @note    Этот метод предназначен только для изменения свойства `$config`.
     *          Логирование изменений выполняется только для определённых ключей.
     *
     * @warning Некорректные данные (не массив, нестроковые ключи или значения) вызывают исключение.
     *          Попытка установки значения для несуществующего свойства также вызывает исключение.
     *
     * @uses \PhotoRigma\Include\log_in_file() Записывает сообщение об ошибке в лог-файл.
     * @uses \PhotoRigma\Classes\Work::$config Свойство, содержащее конфигурацию.
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$config Свойство дочернего класса Work_CoreLogic.
     * @uses \PhotoRigma\Classes\Work_Image::$config Свойство дочернего класса Work_Image.
     * @uses \PhotoRigma\Classes\Work_Template::$config Свойство дочернего класса Work_Template.
     */
    public function __set(string $name, array $value): void
    {
        if ($name === 'config') {
            // Проверка ключей и значений
            $errors = [];
            foreach ($value as $key => $val) {
                if (!is_string($key)) {
                    $errors[] = "Ключ '$key' должен быть строкой.";
                }
                if (!is_string($val)) {
                    $errors[] = "Значение для ключа '$key' должно быть строкой.";
                }
            }
            if (!empty($errors)) {
                throw new InvalidArgumentException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' . 'Обнаружены ошибки в конфигурации | Ошибки: ' . json_encode(
                        $errors,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                    )
                );
            }
            // Логирование изменений
            $exclude_from_logging = ['language', 'themes']; // Ключи, которые не нужно логировать
            $updated_settings = [];
            $added_settings = [];
            foreach ($value as $key => $val) {
                if (in_array($key, $exclude_from_logging, true)) {
                    continue; // Пропускаем логирование для исключённых ключей
                }
                if (array_key_exists($key, $this->config)) {
                    $updated_settings[$key] = $val;
                } else {
                    $added_settings[$key] = $val;
                }
            }
            if (!empty($updated_settings)) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' . 'Обновление настроек | Настройки: ' . json_encode(
                        $updated_settings,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                    )
                );
            }
            if (!empty($added_settings)) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' . 'Добавление настроек | Настройки: ' . json_encode(
                        $added_settings,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                    )
                );
            }
            // Обновляем основной конфиг
            $this->config = $value;
            // Передаём конфигурацию в подклассы через магические методы
            $this->image->config = $this->config;
            $this->template->config = $this->config;
            $this->core_logic->config = $this->config;
        } else {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' . "Несуществующее свойство | Свойство: $name"
            );
        }
    }

    /**
     * Проверяет существование недоступного свойства.
     *
     * Этот метод вызывается автоматически при использовании оператора `isset()` для проверки существования
     * недоступного свойства. Метод возвращает `true`, если свойство существует, и `false` в противном случае.
     *
     * @param string $name Имя свойства:
     *                     - Проверяется на существование.
     *
     * @return bool Результат проверки:
     *              - `true`, если свойство существует.
     *              - `false`, если свойство не существует.
     *
     * @note    Этот метод предназначен только для проверки существования свойств.
     *
     * @warning Если свойство не определено или является недоступным, результат будет `false`.
     */
    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    /**
     * Преобразует размер в байты через вызов публичного метода в дочернем классе.
     *
     * Этот статический метод является фасадом для вызова публичного метода return_bytes() в дочернем классе
     * Work_Helper. Он преобразует размер, заданный в формате "число[K|M|G]", в количество байт.
     *
     * @param string|int $val Размер в формате "число[K|M|G]" или число:
     *                        - Поддерживаются суффиксы: K (килобайты), M (мегабайты), G (гигабайты).
     *                        - Если суффикс недопустим, значение считается в байтах.
     *                        - Отрицательные числа преобразуются в положительные.
     *
     * @return int Размер в байтах. Возвращает `0` для некорректных входных данных.
     *
     * @warning Если суффикс недопустим, он игнорируется, и значение преобразуется в число.
     *          Метод чувствителен к формату входных данных. Убедитесь, что они корректны.
     *
     * @uses \PhotoRigma\Classes\Work_Helper::return_bytes() Публичный метод в дочернем классе, реализующий основную
     *                                                       логику.
     */
    public static function return_bytes(string|int $val): int
    {
        return Work_Helper::return_bytes($val);
    }

    /**
     * Формирует информационную строку для категории или пользовательского альбома через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода category() в классе Work_CoreLogic. Он
     * выполняет запросы к базе данных для получения информации о категории или пользовательском альбоме, включая
     * количество фотографий, данные о последней и лучшей фотографии, а также ссылки на них.
     *
     * @param int $cat_id    Идентификатор категории или пользователя (если `$user_flag = 1`):
     *                       - Должен быть целым числом >= `0`.
     *                       - Пример: `5` (для категории) или `123` (для пользовательского альбома).
     * @param int $user_flag Флаг, указывающий формировать ли информацию о категории (`0`) или пользовательском
     *                       альбоме (`1`):
     *                       - По умолчанию: `0`.
     *                       - Допустимые значения: `0` или `1`.
     *
     * @return array Информационная строка для категории или пользовательского альбома:
     *               - 'name'           (string): Название категории или альбома.
     *               - 'description'    (string): Описание категории или альбома.
     *               - 'count_photo'    (int):    Количество фотографий.
     *               - 'last_photo'     (string): Форматированное название последней фотографии (например, "Название
     *                                            (Описание)").
     *               - 'top_photo'      (string): Форматированное название лучшей фотографии (например, "Название
     *                                            (Описание)").
     *               - 'url_cat'        (string): Ссылка на категорию или альбом.
     *               - 'url_last_photo' (string): Ссылка на последнюю фотографию.
     *               - 'url_top_photo'  (string): Ссылка на лучшую фотографию.
     *
     * @throws InvalidArgumentException Если входные параметры имеют некорректный тип или значение.
     * @throws PDOException             Если возникают ошибки при получении данных из базы данных.
     * @throws Exception                При выполнении запросов к базам данных.
     *
     * @note    Используются константы:
     *          - TBL_CATEGORY: Таблица для хранения данных о категориях (`category`).
     *          - TBL_USERS:    Таблица для хранения данных о пользователях (`users`).
     *          - TBL_PHOTO:    Таблица для хранения данных о фотографиях (`photo`).
     *
     * @warning Убедитесь, что:
     *          - Входные параметры `$cat_id` и `$user_flag` корректны.
     *          - База данных содержит необходимые данные для выполнения запросов.
     *          - Пользователь имеет права на просмотр фотографий (если это требуется).
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::category() Публичный метод в дочернем классе, реализующий основную
     *                                                      логику.
     */
    public function category(int $cat_id = 0, int $user_flag = 0): array
    {
        return $this->core_logic->category($cat_id, $user_flag);
    }

    /**
     * Проверяет содержимое поля на соответствие условиям через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода check_field() в классе Work_Security. Он
     * выполняет проверку значения поля на соответствие регулярному выражению, условию "не ноль" ($not_zero) и
     * отсутствие запрещённых паттернов из свойства \PhotoRigma\Classes\Work_Security::$compiled_rules.
     *
     * @param string       $field    Значение поля для проверки:
     *                               - Указывается строковое значение, которое необходимо проверить.
     * @param string|false $regexp   Регулярное выражение (необязательно):
     *                               - Если задано, значение должно соответствовать этому выражению.
     *                               - Если регулярное выражение некорректно (например, содержит ошибки компиляции),
     *                               метод завершает выполнение с ошибкой.
     * @param bool         $not_zero Флаг, указывающий, что значение не должно быть числом 0:
     *                               - Если флаг установлен, а значение равно '0', проверка завершается с ошибкой.
     *
     * @return bool True, если поле прошло все проверки, иначе False.
     *              Проверки включают соответствие регулярному выражению, отсутствие запрещённых паттернов
     *              и выполнение условия $not_zero (если оно задано).
     *
     * @throws Exception При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @note    Метод использует свойство\PhotoRigma\Classes\Work_Security::$compiled_rules
     *          для проверки на наличие запрещённых паттернов.
     *          Свойство должно содержать массив скомпилированных регулярных выражений.
     *
     * @warning Метод зависит от корректности данных в свойстве\PhotoRigma\Classes\Work_Security::$compiled_rules.
     *          Если правила некорректны, результат может быть непредсказуемым.
     *          Также важно убедиться, что регулярное выражение ($regexp) корректно перед использованием.
     *
     * @uses \PhotoRigma\Classes\Work_Security::check_field() Публичный метод в дочернем классе, реализующий основную
     *                                                        логику.
     */
    public function check_field(string $field, string|false $regexp = false, bool $not_zero = false): bool
    {
        return $this->security->check_field($field, $regexp, $not_zero);
    }

    /**
     * Проверяет входные данные через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода check_input() в классе Work_Security. Он
     * выполняет проверку данных из различных источников ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES) на
     * соответствие заданным условиям.
     *
     * @param string $source_name Источник данных ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES):
     *                            - Должен быть одним из допустимых значений: '_GET', '_POST', '_SESSION', '_COOKIE',
     *                              '_FILES'.
     * @param string $field       Поле для проверки (имя ключа в массиве источника данных):
     *                            - Указывается имя ключа, которое необходимо проверить.
     * @param array  $options     Дополнительные параметры проверки:
     *                            - Ключ 'isset' (bool, опционально): Проверять наличие поля в источнике данных.
     *                            - Ключ 'empty' (bool, опционально): Проверять, что значение поля не пустое.
     *                            - Ключ 'regexp' (string|false, опционально): Регулярное выражение для проверки
     *                              значения поля.
     *                            - Ключ 'not_zero' (bool, опционально): Проверять, что значение поля не равно нулю.
     *                            - Ключ 'max_size' (int, опционально): Максимальный размер файла (в байтах) для
     *                              $_FILES.
     *
     * @return bool True, если данные прошли проверку, иначе False.
     *              Для $_FILES также учитывается корректность MIME-типа и размера файла.
     *
     * @throws RuntimeException Если MIME-тип загруженного файла не поддерживается.
     * @throws Exception        При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @note    Для источника данных `_SESSION` используется свойство класса
     *          \PhotoRigma\Classes\Work_Security::$session. Метод зависит от корректности данных в источниках
     *          ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES). Если источник данных повреждён или содержит
     *          некорректные значения, результат может быть непредсказуемым.
     *
     * @warning Убедитесь, что переданные параметры проверки ($options) корректны и соответствуют требованиям.
     *
     * @uses \PhotoRigma\Classes\Work_Security::check_input() Публичный метод в дочернем классе, реализующий основную
     *                                                        логику.
     */
    public function check_input(string $source_name, string $field, array $options = []): bool
    {
        return $this->security->check_input($source_name, $field, $options);
    }

    /**
     * Транслитерация строки и замена знаков пунктуации на "_" через вызов публичного метода в дочернем классе.
     *
     * Этот статический метод является фасадом для вызова публичного метода encodename() в дочернем классе
     * Work_Helper. Он выполняет транслитерацию не латинских символов в латиницу и заменяет все символы, кроме букв и
     * цифр, на `"_"`.
     *
     * @param string $string Исходная строка:
     *                       - Если строка пустая, то она возвращается без обработки.
     *                       - Рекомендуется использовать строки в кодировке UTF-8.
     *
     * @return string Строка после транслитерации и замены символов:
     *                - Если после обработки строка становится пустой, генерируется уникальная последовательность.
     *
     * @warning Если расширение `intl` недоступно, используется резервная таблица транслитерации.
     *          Метод не гарантирует сохранение исходного формата строки, так как все специальные символы заменяются на
     *          `"_"`.
     *
     * @uses \PhotoRigma\Classes\Work_Helper::encodename() Публичный метод в дочернем классе, реализующий основную
     *                                                     логику.
     */
    public static function encodename(string $string): string
    {
        return Work_Helper::encodename($string);
    }

    /**
     * Преобразует BBCode в HTML через вызов публичного метода в дочернем классе.
     *
     * Этот статический метод является фасадом для вызова публичного метода ubb() в дочернем классе Work_Helper. Он
     * преобразует BBCode-теги в соответствующие HTML-теги, учитывая рекурсивную обработку вложенных тегов.
     *
     * @param string $text Текст с BBCode:
     *                     - Рекомендуется использовать строки в кодировке UTF-8.
     *                     - Если строка пустая, она возвращается без обработки.
     *
     * @return string Текст с HTML-разметкой:
     *                - Некорректные BBCode-теги игнорируются или преобразуются в текст.
     *
     * @throws Exception Если произошла ошибка при рекурсивной обработке BBCode.
     *
     * @warning Метод ограничивает глубину рекурсии для вложенных тегов (максимум 10 уровней).
     *          Некорректные URL или изображения заменяются на безопасные значения или удаляются.
     *
     * @uses \PhotoRigma\Classes\Work_Helper::ubb() Публичный метод в дочернем классе, реализующий основную логику.
     */
    public static function ubb(string $text): string
    {
        return Work_Helper::ubb($text);
    }

    /**
     * Разбивает строку на несколько строк ограниченной длины через вызов публичного метода в дочернем классе.
     *
     * Этот статический метод является фасадом для вызова публичного метода utf8_wordwrap() в дочернем классе
     * Work_Helper. Он разбивает строку на несколько строк, каждая из которых имеет длину не более указанной. Разрыв
     * строки выполняется только по пробелам, чтобы сохранить читаемость текста.
     *
     * @param string $str   Исходная строка:
     *                      - Рекомендуется использовать строки в кодировке UTF-8.
     *                      - Если строка пустая или её длина меньше или равна $width, она возвращается без изменений.
     * @param int    $width Максимальная длина строки (по умолчанию 70):
     *                      - Должен быть положительным целым числом.
     * @param string $break Символ разрыва строки (по умолчанию PHP_EOL):
     *                      - Не должен быть пустой строкой.
     *
     * @return string Строка, разбитая на несколько строк:
     *                - В случае некорректных параметров возвращается исходная строка.
     *
     * @throws Exception При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @warning Метод корректно работает только с UTF-8 символами.
     *          Если параметры некорректны (например, $width <= 0 или $break пустой), возвращается исходная строка.
     *          Если строка содержит многобайтовые символы, выполняется дополнительная корректировка разбиения.
     *
     * @uses \PhotoRigma\Classes\Work_Helper::utf8_wordwrap() Публичный метод в дочернем классе, реализующий основную
     *                                                        логику.
     */
    public static function utf8_wordwrap(string $str, int $width = 70, string $break = PHP_EOL): string
    {
        return Work_Helper::utf8_wordwrap($str, $width, $break);
    }

    /**
     * Проверяет MIME-тип файла через вызов публичного метода в дочернем классе.
     *
     * Этот статический метод является фасадом для вызова публичного метода validate_mime_type() в дочернем классе
     * Work_Helper. Он проверяет, поддерживается ли указанный MIME-тип хотя бы одной из доступных библиотек: Imagick,
     * Gmagick или встроенных функций PHP (GD).
     *
     * @param string $real_mime_type Реальный MIME-тип файла:
     *                               - Должен быть корректным MIME-типом для изображений.
     *
     * @return bool True, если MIME-тип поддерживается хотя бы одной библиотекой, иначе false.
     *
     * @throws Exception При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @warning Метод зависит от доступности библиотек (Imagick, Gmagick) и встроенных функций PHP по работе с
     *          изображениями (GD). Если ни одна из библиотек недоступна, метод может некорректно работать.
     *          Проверка выполняется последовательно, начиная с Imagick. Если MIME-тип поддерживается одной из
     *          библиотек, дальнейшие проверки не выполняются.
     *
     * @uses \PhotoRigma\Classes\Work_Helper::validate_mime_type() Публичный метод в дочернем классе, реализующий
     *                                                             основную логику.
     */
    public static function validate_mime_type(string $real_mime_type): bool
    {
        return Work_Helper::validate_mime_type($real_mime_type);
    }

    /**
     * Устанавливает языковые данные через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для защищённого метода `_set_lang_internal()`. Он выполняет загрузку и
     * обработку файлов языковых данных, передавая их в текущий класс и дочерние компоненты. Метод предназначен для
     * использования клиентским кодом как точка входа для установки языковых данных.
     *
     * @throws InvalidArgumentException Если массив языковых данных некорректен (например, отсутствует ключ `'lang'` или
     *                                  содержатся ошибки).
     * @throws RuntimeException         Если возникают проблемы с файлами языковых данных (например, файл отсутствует,
     *                                  недоступен для чтения или не содержит обязательный файл `main.php`).
     * @throws JsonException            При ошибках кодирования/декодирования JSON.
     * @throws Exception                При ошибках логирования через `log_in_file()`.
     *
     * @note    Этот метод является точкой входа для установки языковых данных. Все проверки и обработка выполняются в
     *          защищённом методе `_set_lang_internal()`.
     *
     * @warning Если файлы языковых данных отсутствуют, содержат ошибки или недоступны для чтения, метод выбрасывает
     *          исключение.
     *
     * @uses \PhotoRigma\Classes\Work::_set_lang_internal() Защищённый метод, который выполняет основную логику
     *                                                      установки языковых данных.
     */
    public function set_lang(): void
    {
        $this->_set_lang_internal();
    }

    /**
     * Загружает и обрабатывает файлы языковых данных, обновляя их в текущем классе и дочерних компонентах.
     *
     * Этот защищённый метод выполняет следующие действия:
     * 1. Формирует массив путей к директориям языковых данных на основе конфигурации
     *    (`$this->config['language_dirs']`).
     * 2. Сканирует каждую директорию с помощью `DirectoryIterator` и формирует список файлов для загрузки:
     *    - Пропускаются неподходящие файлы (не `.php`, `index.php`).
     *    - Для каждого файла вычисляется CRC32 контрольная сумма содержимого.
     *    - Формируется уникальный ключ кеша для каждого файла.
     * 3. Проверяет наличие обязательного файла `main.php`. Если файл отсутствует, выбрасывается исключение.
     * 4. Обрабатывает каждый файл:
     *    - Загружает данные из файла через `include()`. Файл должен возвращать массив с ключом `'lang'`.
     *    - Если данные некорректны, логируются ошибки или выбрасываются исключения (для `main.php`).
     *    - Проверяет актуальность данных в кеше через `$this->cache->is_valid()`. Если кеш актуален, данные
     *      используются из кеша.
     *    - Если кеш устарел или отсутствует, данные обрабатываются через `_process_lang_array()`:
     *      - Логируются изменения (`changes`) и ошибки (`errors`).
     *      - При наличии ошибок в `main.php` выбрасывается исключение.
     *    - Данные сохраняются в кеш через `$this->cache->update_cache()`.
     * 5. Обновляет языковые данные в текущем классе (`$this->lang`) и передаёт их в дочерние классы:
     *    - `Work_Template::$lang`.
     *    - `Work_CoreLogic::$lang`.
     * Метод является защищённым и предназначен для использования внутри класса или его наследников. Однако он может
     * быть вызван публично через фасад, например, через публичный метод `set_lang`.
     *
     * @protected
     *
     * @throws RuntimeException         Выбрасывается в следующих случаях:
     *                                  - Отсутствует хотя бы один подходящий файл языковых данных.
     *                                  - Отсутствует обязательный файл `main.php`.
     *                                  - Файл языковых данных не возвращает массив с ключом `'lang'`.
     *                                  - Некорректный или отсутствующий `lang_id` в файле `main.php`.
     *                                  - Не удалось сохранить данные в кеш.
     * @throws InvalidArgumentException Выбрасывается при обнаружении ошибок в массиве языковых данных в файле `main.php`.
     * @throws JsonException            Выбрасывается при ошибках кодирования JSON.
     * @throws Exception                Выбрасывается при ошибках логирования через `log_in_file()`.
     *
     * @throws RuntimeException (из Cache_Handler::is_valid) Выбрасывается в следующих случаях:
     *                           - Клиент хранилища Redis/Memcached не инициализирован или имеет неправильный тип (из
     *                             подметода `_is_valid_storage`).
     * @throws JsonException    (из Cache_Handler::is_valid) Выбрасывается при ошибках декодирования JSON:
     *                           - Для файлового кеширования: из подметода `_is_valid_file`.
     *                           - Для Redis/Memcached: из подметода `_is_valid_storage`.
     *
     * @throws RuntimeException (из Cache_Handler::update_cache) Выбрасывается в следующих случаях:
     *                           - Для файлового кеширования:
     *                             - Если директория для кеша не существует или недоступна для записи.
     *                             - Если файл кеша недоступен для записи.
     *                           - Для Redis/Memcached:
     *                             - Если клиент хранилища не инициализирован или имеет неправильный тип.
     * @throws JsonException    (из Cache_Handler::update_cache) Выбрасывается при ошибках кодирования JSON.
     *
     * @note    Файлы языковых данных должны быть доступны для чтения и возвращать массив с ключом `'lang'`.
     *          Обязательный файл `main.php` должен содержать корректный `lang_id` в виде строки.
     *
     * @warning Если файлы языковых данных отсутствуют, содержат ошибки или недоступны для чтения, метод выбрасывает
     *          исключение.
     *
     * @uses \PhotoRigma\Include\log_in_file() Записывает сообщение об ошибке в лог-файл.
     * @uses \PhotoRigma\Classes\Work::_process_lang_array() Метод для проверки и обработки массива языковых данных.
     * @uses \PhotoRigma\Classes\Work::$lang Свойство класса Work, которое изменяется.
     * @uses \PhotoRigma\Classes\Work_CoreLogic::set_lang() Метод дочернего класса Work_CoreLogic.
     * @uses \PhotoRigma\Classes\Work_Template::set_lang() Метод дочернего класса Work_Template.
     * @uses \PhotoRigma\Interfaces\Cache_Handler_Interface Интерфейс для работы с кешем.
     * @uses \PhotoRigma\Classes\Cache_Handler::is_valid() Метод для проверки актуальности данных в кеше.
     * @uses \PhotoRigma\Classes\Cache_Handler::update_cache() Метод для записи данных в кеш.
     */
    protected function _set_lang_internal(): void
    {
        // 1. Формирование массива путей
        $this->session['language'] = $this->session['language'] ?? $this->config['language'];
        $lang_files_path = array_map(
            fn (string $dir): string => rtrim($dir, '/') . '/' . $this->session['language'] . '/',
            $this->config['language_dirs']
        );

        // 2. Сканирование папок и формирование массива файлов для загрузки
        $files_to_load = [];
        foreach ($lang_files_path as $dir_index => $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (new DirectoryIterator($dir) as $file) {
                if (!$file->isFile() || str_ends_with($file->getFilename(), 'index.php')) {
                    continue; // Пропускаем неподходящие файлы
                }

                if (!str_ends_with($file->getFilename(), '.php')) {
                    continue; // Пропускаем файлы без расширения .php
                }

                $file_name = $file->getBasename('.php');
                $file_crc32 = crc32(file_get_contents($file->getPathname()));

                $files_to_load[] = [
                    'path' => $file->getPathname(),
                    'crc32' => $file_crc32,
                    'cache_key' => "{$this->session['language']}_{$file_name}_$dir_index",
                    'lang_id' => $file_name === 'main', // Только для main.php
                    'throw' => $file_name === 'main' && empty($files_to_load), // Только для первого main.php
                ];
            }
        }

        // Проверка: если список файлов пустой, выбрасываем исключение
        if (empty($files_to_load)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                'Не найдено ни одного подходящего файла языковых данных'
            );
        }

        // Проверка: если нет файла main.php, выбрасываем исключение
        if (!array_filter($files_to_load, static fn ($file) => $file['throw'])) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                'Не найден обязательный файл main.php'
            );
        }

        // 3. Обработка файлов
        $new_lang_data = [];
        foreach ($files_to_load as $file) {
            // Загрузка данных из файла
            $data = include($file['path']);
            if (!is_array($data) || !isset($data['lang'])) {
                if ($file['throw']) {
                    throw new RuntimeException(
                        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                        'Файл языковых данных не возвращает массив с ключом \'lang\' | Путь: ' . $file['path']
                    );
                }

                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                    'Некорректные данные в файле | Путь: ' . $file['path']
                );
                continue;
            }

            // Обработка lang_id (если требуется)
            if ($file['lang_id']) {
                $lang_id = $data['lang_id'] ?? null;
                if (!is_string($lang_id)) {
                    if ($file['throw']) {
                        throw new RuntimeException(
                            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                            'Некорректный или отсутствующий lang_id в файле | Путь: ' . $file['path']
                        );
                    }

                    log_in_file(
                        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                        'Некорректный или отсутствующий lang_id в файле | Путь: ' . $file['path']
                    );
                    continue;
                }
                // Обновляем значение html_lang
                $this->config['html_lang'] = $lang_id;
            }

            // Проверка кеша
            $cached_data = $this->cache->is_valid($file['cache_key'], $file['crc32']);
            if ($cached_data !== false) {
                // Если кеш актуален, используем данные из кеша
                $new_lang_data = [...($new_lang_data ?? []), ...$cached_data];
                continue;
            }

            // Обработка данных через _process_lang_array
            $result = $this->_process_lang_array($data['lang']);
            if (!empty($result['errors'])) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                    'Обнаружены ошибки в массиве языковых данных | Ошибки: ' .
                    json_encode($result['errors'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                );
                if ($file['throw']) {
                    throw new InvalidArgumentException(
                        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                        'Обнаружены ошибки в массиве языковых данных | Путь: ' . $file['path']
                    );
                }
            }

            // Логирование изменений
            if (!empty($result['changes'])) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                    'Очищенные значения | Значения: ' .
                    json_encode($result['changes'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                );
            }

            // Сохранение данных в кеш
            if (!$this->cache->update_cache($file['cache_key'], $file['crc32'], $result['result'])) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                    'Не удалось сохранить данные в кеш | Ключ: ' . $file['cache_key']
                );
            }

            // Наполнение нового массива языковых данных
            $new_lang_data = [...($new_lang_data ?? []), ...$result['result']];
        }

        // 4. Завершение работы
        $this->lang = $new_lang_data;
        $this->template->set_lang($this->lang);
        $this->core_logic->set_lang($this->lang);
    }

    /**
     * Обрабатывает массив языковых переменных, проверяя его структуру и очищая значения. Возвращает массив с
     * информацией об ошибках, изменениях и обработанном массиве.
     *
     * Этот метод выполняет следующие шаги:
     * 1. Проверяет, что ключи первого уровня массива являются строками.
     * 2. Проверяет, что значения первого уровня являются массивами.
     * 3. Проверяет, что ключи второго уровня являются строками:
     *    - Если ключ не является строкой, он преобразуется в строку.
     *    - Старый ключ удаляется, а новый добавляется в массив.
     * 4. Проверяет, что значения второго уровня являются строками.
     * 5. Очищает значения второго уровня через метод `clean_field()` для защиты от XSS:
     *    - Если значение было изменено при очистке, сохраняется информация об оригинальном и очищенном значениях.
     * 6. Возвращает массив с результатами обработки:
     *    - `'errors'`: Массив ошибок, если они есть (например, некорректные ключи или значения).
     *    - `'changes'`: Массив изменений, если значения были очищены (содержит оригинальные и очищенные значения).
     *    - `'result'`: Обработанный массив языковых переменных.
     * Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @internal
     *
     * @param array $lang Массив языковых переменных для обработки:
     *                    - Ключи первого уровня должны быть строками.
     *                    - Значения первого уровня должны быть массивами.
     *                    - Ключи второго уровня должны быть строками.
     *                    - Значения второго уровня должны быть строками.
     *
     * @return array Массив с результатами обработки:
     *               - `'errors'` (array): Массив ошибок, если они есть (например, некорректные ключи или значения).
     *               - `'changes'` (array): Массив изменений, если значения были очищены (содержит оригинальные и
     *                 очищенные значения).
     *               - `'result'` (array): Обработанный массив языковых переменных.
     *
     * @note    Используется метод `clean_field()` для очистки значений (защита от XSS).
     *
     * @warning Метод чувствителен к структуре входного массива. Некорректная структура может привести к ошибкам.
     *
     * @uses \PhotoRigma\Classes\Work::clean_field() Метод для очистки и экранирования полей (защита от XSS).
     */
    private function _process_lang_array(array $lang): array
    {
        $result = [
            'errors'  => [],
            'changes' => [],
            'result'  => $lang, // Инициализируем результат исходным массивом
        ];

        foreach ($lang as $name => $keys) {
            // Проверка ключей первого уровня
            if (!is_string($name)) {
                $result['errors'][] = 'Ключ первого уровня должен быть строкой. Получено: ' . gettype($name);
                continue;
            }

            // Проверка второго уровня
            if (!is_array($keys)) {
                $result['errors'][] = "Значение для ключа '$name' должно быть массивом.";
                continue;
            }

            foreach ($keys as $key => $value) {
                // Преобразование ключа в строку
                $test_key = (string)$key;
                if (empty($test_key)) {
                    $result['errors'][] = "Ключ второго уровня для '$name' должен быть строкой. Получено: $key - " . gettype(
                        $key
                    );
                    continue;
                }

                // Удаление старого ключа и добавление нового
                unset($result['result'][$name][$key]);
                $key = $test_key;

                // Проверка значений второго уровня
                if (!is_string($value)) {
                    $result['errors'][] = "Значение для ключа '{$name}['$key']' должно быть строкой. Получено: " . gettype(
                        $value
                    );
                    continue;
                }

                // Очистка значения
                $original_value = $value;
                $cleaned_value = self::clean_field($value);
                if ($original_value !== $cleaned_value) {
                    $result['changes']["{$name}['$key']"] = [
                        'original' => $original_value,
                        'cleaned'  => $cleaned_value,
                    ];
                    $value = $cleaned_value; // Обновляем значение
                }

                // Добавляем обработанный ключ и значение в результирующий массив
                $result['result'][$name][$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Очищает строку от HTML-тегов и специальных символов через вызов публичного метода в дочернем классе.
     *
     * Этот статический метод является фасадом для вызова публичного метода clean_field() в дочернем классе
     * Work_Helper. Он выполняет очистку строки от HTML-тегов и экранирует специальные символы для защиты от XSS-атак
     * и других проблем, связанных с некорректными данными.
     *
     * @param string $field Строка или данные, которые могут быть преобразованы в строку:
     *                      - Если входные данные пусты (`null` или пустая строка), метод вернёт пустую строку (`''`).
     *
     * @return string Очищенная строка или пустая строка (`''`), если входные данные пусты.
     *
     * @warning Метод не обрабатывает вложенные структуры данных (например, массивы).
     *          Убедитесь, что входные данные могут быть преобразованы в строку.
     *
     * @uses \PhotoRigma\Classes\Work_Helper::clean_field() Публичный метод в дочернем классе, реализующий основную
     *                                                      логику.
     */
    public static function clean_field(string $field): string
    {
        return Work_Helper::clean_field($field);
    }

    /**
     * Установка объекта пользователя через сеттер.
     *
     * Этот метод позволяет установить объект пользователя, реализующий интерфейс `User_Interface`.
     * Метод выполняет следующие действия:
     * 1. Проверяет, что переданный объект является экземпляром класса, реализующего интерфейс `User_Interface`.
     * 2. Передаёт объект пользователя в связанные компоненты системы (`Work_Template` и `Work_CoreLogic`) для
     *    дальнейшего использования.
     *
     * @param User_Interface $user Объект пользователя:
     *                             - Должен быть экземпляром класса, реализующего интерфейс `User_Interface`.
     *
     * @throws InvalidArgumentException Если передан некорректный объект (не экземпляр интерфейса `User_Interface`).
     *
     * @note    Метод проверяет тип переданного объекта.
     *          Объект пользователя используется в связанных компонентах системы (например, для авторизации,
     *          управления правами доступа и т.д.).
     *
     * @warning Некорректный объект (не экземпляр интерфейса `User_Interface`) вызывает исключение.
     *
     * @uses \PhotoRigma\Classes\User_Interface Интерфейс, которому должен соответствовать объект пользователя.
     * @uses \PhotoRigma\Classes\Work_Template::set_user() Метод, использующий объект пользователя.
     * @uses \PhotoRigma\Classes\Work_CoreLogic::set_user() Метод, использующий объект пользователя.
     */
    public function set_user(User_Interface $user): void
    {
        $this->template->set_user($user);
        $this->core_logic->set_user($user);
    }

    /**
     * Проверяет URL на наличие вредоносного кода через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода url_check() в классе Work_Security. Он
     * проверяет строку запроса из глобального массива `$_SERVER['QUERY_STRING']` на наличие запрещённых паттернов,
     * определённых в массиве \PhotoRigma\Classes\Work_Security::$compiled_rules.
     *
     * @return bool True, если URL безопасен (не содержит запрещённых паттернов), иначе False.
     *
     * @throws Exception При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @note    Метод работает с глобальным массивом `$_SERVER['QUERY_STRING']`.
     *          Убедитесь, что этот массив доступен и содержит корректные данные.
     *
     * @warning Метод зависит от корректности данных в свойстве\PhotoRigma\Classes\Work_Security::$compiled_rules.
     *          Если правила некорректны, результат может быть непредсказуемым.
     *
     * @uses \PhotoRigma\Classes\Work_Security::url_check() Публичный метод в дочернем классе, реализующий основную
     *                                                      логику.
     */
    public function url_check(): bool
    {
        return $this->security->url_check();
    }

    /**
     * Генерирует математический CAPTCHA-вопрос и ответ через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода gen_captcha() в классе Work_Security. Он
     * генерирует случайное математическое выражение и вычисляет его результат для защиты от спам-ботов.
     *
     * @return array Массив с ключами 'question' и 'answer':
     *               - Ключ 'question' содержит строку математического выражения (например, "2 x (3 + 4)").
     *               - Ключ 'answer' содержит целочисленный результат вычисления (например, 14).
     *
     * @throws Random\RandomException Если произошла ошибка при генерации случайных чисел с помощью `random_int()`.
     *
     * @note    Метод использует `random_int()` для генерации случайных чисел, что обеспечивает криптографическую
     *          безопасность. Если требуется замена на менее безопасную функцию, можно использовать `rand()`.
     *
     * @warning Убедитесь, что PHP поддерживает функцию `random_int()`, так как её отсутствие приведёт к ошибке.
     *
     * @uses \PhotoRigma\Classes\Work_Security::gen_captcha() Публичный метод в дочернем классе, реализующий основную
     *                                                        логику.
     */
    public function gen_captcha(): array
    {
        return $this->security->gen_captcha();
    }

    /**
     * Заменяет символы в email-адресах через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода filt_email() в классе Work_Security. Он
     * проверяет корректность формата email и заменяет символы '@' и '.' на '[at]' и '[dot]' соответственно для
     * затруднения автоматического парсинга email-адресов ботами.
     *
     * @param string $email Email-адрес для обработки:
     *                      - Должен быть непустым и соответствовать формату email (например, "example@local.com").
     *                      - Если email некорректен или пуст, метод возвращает пустую строку.
     *
     * @return string Обработанный email-адрес, где символы '@' и '.' заменены на '[at]' и '[dot]'.
     *                Если входной email некорректен или пуст, возвращается пустая строка.
     *
     * @throws Exception При возникновении ошибок логирования через `log_in_file`.
     *
     * @note    Метод использует функцию `filter_var()` для проверки формата email.
     *          Это базовая проверка, и если требуется более строгая валидация, следует учитывать дополнительные
     *          правила.
     *
     * @warning Убедитесь, что входной email соответствует формату перед вызовом метода.
     *          Если email некорректен или пуст, метод вернёт пустую строку.
     *
     * @uses \PhotoRigma\Classes\Work_Security::filt_email() Публичный метод в дочернем классе, реализующий основную
     *                                                        логику.
     */
    public function filt_email(string $email): string
    {
        return $this->security->filt_email($email);
    }

    /**
     * Удаляет изображение с указанным идентификатором через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода del_photo() в классе Work_CoreLogic. Он
     * выполняет удаление изображения с указанным идентификатором, а также все упоминания об этом изображении в
     * таблицах сайта.
     *
     * @param int $photo_id Идентификатор удаляемого изображения:
     *                      - Должен быть положительным целым числом.
     *
     * @return bool True, если удаление успешно, иначе False.
     *
     * @throws InvalidArgumentException Если параметр photo_id имеет некорректный тип или значение.
     * @throws RuntimeException         Если возникает ошибка при выполнении запросов к базе данных или удалении
     *                                  файлов.
     * @throws Exception                При записи ошибок в лог через log_in_file().
     *
     * @note    Используются константы:
     *          - TBL_PHOTO: Таблица для хранения данных об изображениях (photo).
     *          - TBL_CATEGORY: Таблица для хранения данных о категориях (category).
     *
     * @warning Метод чувствителен к правам доступа при удалении файлов. Убедитесь, что скрипт имеет необходимые права
     *          на запись и чтение.
     *          Удаление файлов и записей из базы данных необратимо. Убедитесь, что передан корректный идентификатор
     *          изображения.
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::del_photo() Публичный метод в дочернем классе, реализующий основную
     *                                                       логику.
     */
    public function del_photo(int $photo_id): bool
    {
        return $this->core_logic->del_photo($photo_id);
    }

    /**
     * Получает данные о новостях в зависимости от типа запроса через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода news() в классе Work_CoreLogic. Он
     * выполняет запросы к базе данных для получения данных о новостях:
     * - Для `$act = 'id'`: Возвращает новость по её ID.
     * - Для `$act = 'last'`: Возвращает список новостей с сортировкой по дате последнего редактирования.
     *
     * @param int    $news_id_or_limit Количество новостей или ID новости (в зависимости от параметра `$act`):
     *                                 - Должен быть положительным целым числом.
     * @param string $act              Тип запроса:
     *                                 - `'id'`: Получение новости по её ID.
     *                                 - `'last'`: Получение списка новостей с сортировкой по дате последнего
     *                                 редактирования.
     *
     * @return array Массив с данными о новостях. Если новостей нет, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный `$act` или `$news_id_or_limit`.
     * @throws RuntimeException         Если произошла ошибка при выполнении запроса к базе данных.
     * @throws Exception                При выполнении запросов к базам данных.
     *
     * @note    Используются константы:
     *          - TBL_NEWS: Таблица для хранения данных о новостях (`news`).
     *
     * @warning Метод чувствителен к корректности входных параметров `$news_id_or_limit` и `$act`.
     *          Убедитесь, что передаются допустимые значения.
     *          Если новости не найдены, метод возвращает пустой массив.
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::news() Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function news(int $news_id_or_limit, string $act): array
    {
        return $this->core_logic->news($news_id_or_limit, $act);
    }

    /**
     * Загружает доступные языки из директории /language/ через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода get_languages() в классе Work_CoreLogic.
     * Он выполняет загрузку доступных языков, проверяя поддиректории в `/language/` и безопасно подключая файлы
     * `main.php` для получения названий языков.
     *
     * @return array Массив с данными о доступных языках. Каждый элемент массива содержит:
     *               - `value`: Имя директории языка (строка).
     *               - `name`: Название языка из файла `main.php` (строка).
     *
     * @throws RuntimeException Если:
     *                           - Директория `/language/` недоступна или не существует.
     *                           - Ни один язык не найден в указанной директории.
     * @throws Exception         При записи ошибок в лог через `log_in_file()`.
     *
     * @warning Метод чувствителен к структуре директории `/language/` и содержимому файла `main.php`.
     *          Убедитесь, что:
     *          - Файл `main.php` содержит корректную переменную `lang_name` (строка, не пустая).
     *          - Поддиректории находятся внутри директории `/language/`.
     *          Если директория `/language/` пуста или содержит недоступные поддиректории, метод выбрасывает исключение.
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::get_languages() Публичный метод в дочернем классе, реализующий
     *                                                           основную логику.
     */
    public function get_languages(): array
    {
        return $this->core_logic->get_languages();
    }

    /**
     * Загружает доступные темы из директории /themes/ через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода get_themes() в классе Work_CoreLogic. Он
     * выполняет загрузку доступных тем, проверяя поддиректории в `/themes/` и добавляя их в список доступных тем.
     *
     * @return array Массив с именами доступных тем (строки).
     *
     * @throws RuntimeException Если:
     *                           - Директория `/themes/` не существует или недоступна для чтения.
     *                           - Ни одна тема не найдена в указанной директории.
     * @throws Exception         При записи ошибок в лог через `log_in_file()`.
     *
     * @warning Метод чувствителен к структуре директории `/themes/`.
     *          Убедитесь, что:
     *           - Директория `/themes/` существует и доступна для чтения.
     *           - Поддиректории находятся внутри директории `/themes/`.
     *          Если директория `/themes/` пуста или содержит недоступные поддиректории, метод выбрасывает исключение.
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::get_themes() Публичный метод в дочернем классе, реализующий основную
     *                                                        логику.
     */
    public function get_themes(): array
    {
        return $this->core_logic->get_themes();
    }

    /**
     * Генерирует блок данных для вывода изображений различных типов через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода create_photo() в классе Work_CoreLogic.
     * Он генерирует данные для вывода изображений различных типов:
     * - 'top':  Лучшее изображение (по рейтингу).
     * - 'last': Последнее загруженное изображение.
     * - 'cat':  Изображение из конкретной категории (требует указания `$id_photo`).
     * - 'rand': Любое случайное изображение.
     *
     * @param string $type     Тип изображения:
     *                         - 'top':  Лучшее изображение (по рейтингу).
     *                         - 'last': Последнее загруженное изображение.
     *                         - 'cat':  Изображение из конкретной категории (требует указания `$id_photo`).
     *                         - 'rand': Любое случайное изображение.
     *                         По умолчанию: 'top'.
     *                         Допустимые значения: 'top', 'last', 'cat', 'rand'.
     * @param int    $id_photo Идентификатор фото. Используется только при `$type == 'cat'`.
     *                         Должен быть целым числом >= `0`.
     *                         По умолчанию: `0`.
     *
     * @return array Массив данных для вывода изображения:
     *               - 'name_block'           (string):      Название блока изображения (например, "Лучшее фото").
     *               - 'url'                  (string):      URL для просмотра полного изображения.
     *               - 'thumbnail_url'        (string):      URL для миниатюры изображения.
     *               - 'name'                 (string):      Название изображения.
     *               - 'description'          (string):      Описание изображения.
     *               - 'category_name'        (string):      Название категории.
     *               - 'category_description' (string):      Описание категории.
     *               - 'rate'                 (string):      Рейтинг изображения (например, "Рейтинг: 5/10").
     *               - 'url_user'             (string|null): URL профиля пользователя, добавившего изображение.
     *               - 'real_name'            (string):      Реальное имя пользователя.
     *               - 'category_url'         (string):      URL категории или пользовательского альбома.
     *               - 'width'                (int):         Ширина изображения после масштабирования.
     *               - 'height'               (int):         Высота изображения после масштабирования.
     *
     * @throws InvalidArgumentException Если передан недопустимый `$type` или `$id_photo < 0`.
     * @throws PDOException             Если произошла ошибка при выборке данных из базы данных.
     * @throws RuntimeException         Если файл изображения недоступен или не существует.
     * @throws Exception                При записи ошибок в лог через `log_in_file()`.
     *
     * @note    Используются следующие константы:
     *          - TBL_CATEGORY: Таблица для хранения данных о категориях (`category`).
     *          - TBL_PHOTO: Таблица для хранения данных об изображениях (`photo`).
     *          - TBL_USERS: Таблица для хранения данных о пользователях (`users`).
     *          - VIEW_RANDOM_PHOTO: Представление для выбора случайных изображений (`random_photo`).
     *
     * @warning Метод чувствителен к правам доступа пользователя.
     *          Убедитесь, что пользователь имеет право на просмотр изображений.
     *          Если файл изображения недоступен или не существует, метод возвращает данные по умолчанию.
     *          Проверка пути к файлу изображения гарантирует, что доступ возможен только к файлам внутри
     *          директории, указанной в конфигурации.
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::create_photo() Публичный метод в дочернем классе, реализующий
     *                                                          основную логику.
     */
    public function create_photo(string $type = 'top', int $id_photo = 0): array
    {
        return $this->core_logic->create_photo($type, $id_photo);
    }

    /**
     * Добавляет новую оценку в таблицу и возвращает среднюю оценку через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода process_rating() в классе Work_CoreLogic.
     * Он выполняет добавление новой оценки в указанную таблицу, проверяет успешность вставки и возвращает среднюю
     * оценку для фотографии.
     *
     * @param string $table      Имя таблицы для вставки оценки:
     *                           - `'rate_user'`: Таблица с оценками фотографий от пользователей.
     *                           - `'rate_moder'`: Таблица с оценками фотографий от модераторов.
     * @param int    $photo_id   ID фотографии:
     *                           - Должен быть положительным целым числом.
     * @param int    $user_id    ID пользователя:
     *                           - Должен быть положительным целым числом.
     * @param int    $rate_value Значение оценки:
     *                           - Должен быть целым числом в диапазоне допустимых значений (например, 1–5).
     *
     * @return float Возвращает число с плавающей точкой, представляющее среднюю оценку.
     *               Если оценок нет, возвращается `0`.
     *
     * @throws RuntimeException Выбрасывается исключение, если не удалось добавить оценку. Причина:
     *                          `get_last_insert_id()` возвращает `0`, что указывает на неудачную вставку.
     * @throws Exception        При выполнении запросов к базам данных.
     *
     * @note    Используются константы:
     *          - TBL_PHOTO: Таблица для хранения данных о фотографиях (`photo`).
     *
     * @warning Убедитесь, что:
     *          - Параметр `$table` соответствует одной из допустимых таблиц (`'rate_user'` или `'rate_moder'`).
     *          - В СУБД настроены триггеры и функции для перерасчета средней оценки.
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::process_rating() Публичный метод в дочернем классе, реализующий
     *                                                            основную логику.
     */
    public function process_rating(string $table, int $photo_id, int $user_id, int $rate_value): float
    {
        return $this->core_logic->process_rating($table, $photo_id, $user_id, $rate_value);
    }

    /**
     * Поиск файла действия.
     *
     * Метод передаёт управление внутреннему методу `find_action_file()` из дочерного класса `Work_CoreLogic`.
     * Используется для получения:
     * - Имени действия
     * - Полного пути к .php-файлу в директории $this->config['action_dir']
     *
     * @param string|null $action Имя действия из $_GET или NULL (для CLI)
     *                            Пример: 'admin', 'profile'
     *
     * @return array [
     *               'action_name' => string,
     *               'full_path'   => string
     *              ]
     *              Возвращает имя действия и путь к найденному файлу
     *
     * @throws RuntimeException Выбрасывается, если файл действия не найден
     * @throws Exception        При ошибках проверки входных данных через check_input()
     *
     * @note    Реализация находится в `_find_action_file_internal()`
     *          Поддерживает вызов как через веб, так и через CLI
     *
     * @warning Не используйте недопустимые символы в $action — это может привести к ошибкам
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::find_action_file() Метод логики поиска файла из дочерного класса.
     */
    public function find_action_file(?string $action): array
    {
        return $this->core_logic->find_action_file($action);
    }

    /**
     * Формирует массив данных для меню через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода create_menu() в классе Work_Template. Он
     * формирует массив данных для меню в зависимости от типа и активного пункта, выполняя запросы к базе данных и
     * проверяя права доступа пользователя.
     *
     * @param string $action Активный пункт меню:
     *                       - Указывается строка, соответствующая активному пункту меню (например, 'home', 'profile').
     * @param int    $menu   Тип меню:
     *                       - SHORT_MENU (0): Краткое горизонтальное меню.
     *                       - LONG_MENU (1): Полное вертикальное меню.
     *                       Другие значения недопустимы и приведут к выбросу исключения InvalidArgumentException.
     *
     * @return array Массив с данными для меню.
     *               Каждый элемент массива содержит:
     *               - Ключ 'url': URL пункта меню (null, если пункт активен).
     *               - Ключ 'name': Название пункта меню (локализованное или дефолтное значение).
     *               Если меню пустое, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный $menu или $action.
     * @throws RuntimeException         Если произошла ошибка при выполнении запроса к базе данных.
     * @throws Exception                При выполнении запросов к базам данных.
     *
     * @note    Данные для меню берутся из таблицы TBL_MENU.
     *          Для получения дополнительной информации см. структуру таблицы.
     *          Константы SHORT_MENU и LONG_MENU определяют тип меню:
     *          - SHORT_MENU: Краткое горизонтальное меню.
     *          - LONG_MENU: Полное вертикальное меню.
     *
     * @warning Убедитесь, что передаваемые параметры корректны, так как это может привести к ошибкам.
     *          Также убедитесь, что права доступа пользователя настроены правильно.
     *
     * @uses \PhotoRigma\Classes\Work_Template::create_menu() Публичный метод в дочернем классе, реализующий основную
     *                                                        логику.
     */
    public function create_menu(string $action, int $menu): array
    {
        return $this->template->create_menu($action, $menu);
    }

    /**
     * Формирует блок пользователя через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода template_user() в классе Work_Template.
     * Он формирует массив данных для блока пользователя, который используется для отображения в шаблоне. Блок
     * зависит от статуса авторизации пользователя:
     * - Для неавторизованных пользователей: ссылки на вход, восстановление пароля и регистрацию.
     * - Для авторизованных пользователей: приветствие, группа и аватар.
     *
     * @return array Массив с данными для блока пользователя:
     *               - Для неавторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока (локализованное).
     *                 - 'CSRF_TOKEN': CSRF-токен для защиты формы.
     *                 - 'L_LOGIN', 'L_PASSWORD', 'L_ENTER', 'L_FORGOT_PASSWORD', 'L_REGISTRATION': Локализованные
     *                   строки.
     *                 - 'U_LOGIN', 'U_FORGOT_PASSWORD', 'U_REGISTRATION': URL для входа, восстановления пароля и
     *                   регистрации.
     *               - Для авторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока (локализованное).
     *                 - 'L_HI_USER': Приветствие с именем пользователя (локализованное).
     *                 - 'L_GROUP': Группа пользователя (локализованная строка).
     *                 - 'U_AVATAR': URL аватара (или дефолтного аватара, если файл недоступен или некорректен).
     *
     * @throws RuntimeException       Если данные пользователя некорректны.
     * @throws Random\RandomException При ошибке генерации CSRF-токена.
     * @throws Exception              При ошибках проверки MIME-типа файла или логирования.
     *
     * @note    Константа DEFAULT_AVATAR определяет значение аватара по умолчанию (например, 'no_avatar.jpg').
     *
     * @warning Убедитесь, что объект пользователя корректно установлен перед вызовом метода.
     *          Также убедитесь, что конфигурация аватаров настроена правильно.
     *
     * @uses \PhotoRigma\Classes\Work_Template::template_user() Публичный метод в дочернем классе, реализующий
     *                                                          основную логику.
     */
    public function template_user(): array
    {
        return $this->template->template_user();
    }

    /**
     * Генерирует массив статистических данных для шаблона через вызов внутреннего метода.
     *
     * Этот публичный метод является фасадом и перенаправляет вызов на реализацию в дочернем классе
     * `Work_Template::template_stat()`. Он обеспечивает единый интерфейс для получения общей статистики сайта,
     * независимо от конкретной реализации в подклассе.
     * Метод должен использоваться как основная точка входа для клиентского кода, минимизируя прямые зависимости от
     * дочерних реализаций.
     *
     * @return array Ассоциативный массив данных для вывода статистики:
     *               - NAME_BLOCK:        Название блока статистики (локализованное).
     *               - L_STAT_REGIST:     Подпись для количества зарегистрированных пользователей.
     *               - D_STAT_REGIST:     Количество зарегистрированных пользователей.
     *               - L_STAT_PHOTO:      Подпись для количества фотографий.
     *               - D_STAT_PHOTO:      Количество фотографий.
     *               - L_STAT_CATEGORY:   Подпись для количества категорий.
     *               - D_STAT_CATEGORY:   Количество категорий (включая пользовательские альбомы).
     *               - L_STAT_USER_ADMIN: Подпись для количества администраторов.
     *               - D_STAT_USER_ADMIN: Количество администраторов.
     *               - L_STAT_USER_MODER: Подпись для количества модераторов.
     *               - D_STAT_USER_MODER: Количество модераторов.
     *               - L_STAT_RATE_USER:  Подпись для количества пользовательских оценок.
     *               - D_STAT_RATE_USER:  Количество пользовательских оценок.
     *               - L_STAT_RATE_MODER: Подпись для количества модераторских оценок.
     *               - D_STAT_RATE_MODER: Количество модераторских оценок.
     *               - L_STAT_ONLINE:     Подпись для онлайн-пользователей.
     *               - D_STAT_ONLINE:     Список онлайн-пользователей (HTML-ссылки) или сообщение об отсутствии
     *                                    онлайн-пользователей.
     *
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных.
     * @throws Exception        При выполнении запросов к базам данных.
     *
     * @note    Реализация должна быть предоставлена в дочернем классе Work_Template.
     *          Фасад гарантирует совместимость и единую сигнатуру вызова.
     *
     * @warning Не рекомендуется переопределять этот метод в дочерних классах. Для изменения логики
     *          следует изменять реализацию в дочернем классе, сохраняя контракт метода.
     *
     * @uses \PhotoRigma\Classes\Work_Template::template_stat() Публичный метод в дочернем классе, реализующий
     *                                                          основную логику.
     */
    public function template_stat(): array
    {
        return $this->template->template_stat();
    }

    /**
     * Формирует список пользователей, загрузивших наибольшее количество изображений, через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода template_best_user() в классе
     * Work_Template. Он выполняет запросы к базе данных для получения списка пользователей, загрузивших наибольшее
     * количество изображений, и формирует массив данных для вывода в шаблон. Метод использует данные: только
     * активированных пользователей, с подтверждённой почтой и не удалённых. Помечает заблокированных пользователей
     * булевым полем.
     *
     * @param int $best_user Количество лучших пользователей для вывода:
     *                       - Должно быть положительным целым числом.
     *                       Если передано недопустимое значение, выбрасывается исключение InvalidArgumentException.
     *
     * @return array Массив данных для вывода в шаблон:
     *               - NAME_BLOCK:   Название блока (локализованное).
     *               - L_USER_NAME:  Подпись для имени пользователя (локализованная строка).
     *               - L_USER_PHOTO: Подпись для количества фотографий (локализованная строка).
     *               - user_url:     Ссылка на профиль пользователя (null, если данных нет).
     *               - user_name:    Имя пользователя ('---', если данных нет).
     *               - user_photo:   Количество загруженных фотографий ('-', если данных нет).
     *               - banned:       Булево значение, указывающее, находится ли пользователь в бане.
     *
     * @throws InvalidArgumentException Если параметр $best_user не является положительным целым числом.
     * @throws Exception                При выполнении запросов к базам данных.
     *
     * @note    Если запрос к базе данных не возвращает данных, добавляется запись "пустого" пользователя:
     *          - user_url: null.
     *          - user_name: '---'.
     *          - user_photo: '-'.
     *          - banned: false
     *          Константы, которые определяют таблицы:
     *          - TBL_USERS: Таблица пользователей с их правами.
     *          - TBL_PHOTO: Таблица со списком фотографий на сервере.
     *          - TBL_USERS_BANS: Таблица определения заблокированных пользователей.
     *
     * @warning Убедитесь, что таблицы базы данных (TBL_USERS, TBL_PHOTO, TBL_USERS_BANS)
     *          содержат корректные данные. Ошибки в структуре таблиц могут привести к некорректному результату.
     *
     * @uses \PhotoRigma\Classes\Work_Template::template_best_user() Публичный метод в дочернем классе, реализующий
     *                                                               основную логику.
     */
    public function template_best_user(int $best_user = 1): array
    {
        return $this->template->template_best_user($best_user);
    }

    /**
     * Вычисляет размеры для вывода эскиза изображения через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода size_image() в классе Work_Image. Он
     * вычисляет ширину и высоту эскиза на основе реальных размеров изображения и конфигурационных параметров.
     *
     * @param string $path_image Путь к файлу изображения:
     *                           - Путь должен быть абсолютным, и файл должен существовать и быть доступным для чтения.
     *
     * @return array Массив с шириной и высотой эскиза:
     *               - Ключ `'width'` (int): Ширина эскиза (целое число ≥ 0).
     *               - Ключ `'height'` (int): Высота эскиза (целое число ≥ 0).
     *               Размеры могут совпадать с оригинальными размерами изображения,
     *               если оно меньше целевого размера.
     *
     * @throws RuntimeException Выбрасывается исключение в следующих случаях:
     *                           - Если файл не существует.
     *                           - Если не удалось получить размеры изображения.
     *
     * @warning Метод зависит от корректности данных в конфигурации (`temp_photo_w` и `temp_photo_h`).
     *          Если эти параметры некорректны, результат может быть непредсказуемым.
     *
     * @uses \PhotoRigma\Classes\Work_Image::size_image() Публичный метод в дочернем классе, реализующий основную
     *                                                    логику.
     */
    public function size_image(string $path_image): array
    {
        return $this->image->size_image($path_image);
    }

    /**
     * Изменяет размер изображения через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода image_resize() в классе Work_Image. Он
     * изменяет размер исходного изображения и сохраняет результат как эскиз, проверяя корректность путей, размеры
     * изображения и доступность директорий.
     *
     * @param string $full_path      Путь к исходному изображению:
     *                               - Путь должен быть абсолютным, соответствовать регулярному выражению
     *                                 `/^[a-zA-Z0-9\/\.\-_]+$/`, и файл должен существовать и быть доступным для
     *                                 чтения.
     * @param string $thumbnail_path Путь для сохранения эскиза:
     *                               - Путь должен быть абсолютным, и директория должна быть доступна для записи.
     *
     * @return bool True, если операция выполнена успешно, иначе False.
     *
     * @throws InvalidArgumentException Если пути к файлам некорректны или имеют недопустимый формат.
     * @throws RuntimeException         Если возникли ошибки при проверке файлов, директорий или размеров изображения.
     * @throws Exception                При возникновении ошибок логирования информации.
     *
     * @note    Метод использует следующие константы класса Work_Image для ограничения размеров исходного изображения:
     *          - `MAX_IMAGE_WIDTH`:  Максимальная ширина исходного изображения (в пикселях). Значение: 5000.
     *          - `MAX_IMAGE_HEIGHT`: Максимальная высота исходного изображения (в пикселях). Значение: 5000.
     *
     * @warning Метод зависит от корректности данных в конфигурации (`temp_photo_w`, `temp_photo_h`).
     *          Если эти параметры некорректны, результат может быть непредсказуемым.
     *          Убедитесь, что пути к файлам и директориям корректны перед вызовом метода.
     *
     * @uses \PhotoRigma\Classes\Work_Image::image_resize() Публичный метод в дочернем классе, реализующий основную
     *                                                      логику.
     */
    public function image_resize(string $full_path, string $thumbnail_path): bool
    {
        return $this->image->image_resize($full_path, $thumbnail_path);
    }

    /**
     * Возвращает данные для отсутствующего изображения через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода no_photo() в классе Work_Image. Он
     * формирует массив данных, который используется для представления информации об отсутствующем изображении. Это
     * может быть полезно, например, если изображение не найдено или недоступно.
     *
     * @return array Массив данных об изображении или его отсутствии:
     *               - 'url'                  (string): URL полноразмерного изображения.
     *               - 'thumbnail_url'        (string): URL эскиза изображения.
     *               - 'name'                 (string): Название изображения. Значение по умолчанию: `'No photo'`.
     *               - 'description'          (string): Описание изображения. Значение по умолчанию: `'No photo
     *                                                  available'`.
     *               - 'category_name'        (string): Название категории. Значение по умолчанию: `'No category'`.
     *               - 'category_description' (string): Описание категории. Значение по умолчанию: `'No category
     *                                                  available'`.
     *               - 'rate'                 (string): Рейтинг изображения. Значение по умолчанию: `'Rate: 0/0'`.
     *               - 'url_user'             (string): URL пользователя. Значение по умолчанию: пустая строка (`''`).
     *               - 'real_name'            (string): Имя пользователя. Значение по умолчанию: `'No user'`.
     *               - 'full_path'            (string): Полный путь к изображению. Формируется на основе конфигурации.
     *               - 'thumbnail_path'       (string): Полный путь к эскизу. Формируется на основе конфигурации.
     *               - 'file'                 (string): Имя файла. Значение по умолчанию: `'no_foto.png'`.
     *
     * @warning Метод зависит от корректности данных в конфигурации (`site_url`, `site_dir`, `gallery_folder`,
     *          `thumbnail_folder`). Если эти параметры некорректны, результат может быть непредсказуемым.
     *
     * @uses \PhotoRigma\Classes\Work_Image::no_photo() Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function no_photo(): array
    {
        return $this->image->no_photo();
    }

    /**
     * Вывод изображения через HTTP через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода image_attach() в классе Work_Image. Он
     * проверяет существование файла, определяет его MIME-тип, отправляет заголовки HTTP и выводит содержимое файла.
     * После завершения отправки скрипт завершает выполнение.
     *
     * @param string $full_path Полный путь к файлу:
     *                          - Путь должен быть абсолютным, и файл должен существовать и быть доступным для чтения.
     * @param string $name_file Имя файла для заголовка `Content-Disposition`:
     *                          - Имя должно быть корректным (например, без запрещённых символов).
     *
     * @return void Метод ничего не возвращает. Завершает выполнение скрипта после отправки заголовков и содержимого
     *              файла.
     *
     * @throws Exception При возникновении ошибок логирования информации.
     *
     * @note    Метод завершает выполнение скрипта (`exit`), отправляя заголовки и содержимое файла.
     *          Дополнительные заголовки безопасности:
     *          - `X-Content-Type-Options: nosniff`
     *          - `X-Frame-Options: DENY`
     *          - `X-XSS-Protection: 1; mode=block`
     *          - `Referrer-Policy: no-referrer`
     *          - `Content-Security-Policy: default-src 'self'; img-src 'self' data:;`
     *
     * @warning Метод завершает выполнение скрипта (`exit`), отправляя заголовки и содержимое файла.
     *          Убедитесь, что файл существует и доступен для чтения перед вызовом метода.
     *
     * @uses \PhotoRigma\Classes\Work_Image::image_attach() Публичный метод в дочернем классе, реализующий основную
     *                                                      логику.
     */
    #[NoReturn] public function image_attach(string $full_path, string $name_file): void
    {
        $this->image->image_attach($full_path, $name_file);
    }

    /**
     * Корректировка расширения файла в соответствии с его MIME-типом через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода fix_file_extension() в классе Work_Image.
     * Он проверяет MIME-тип файла и корректирует или добавляет расширение, соответствующее типу файла.
     *
     * @param string $full_path Полный путь к файлу:
     *                          - Путь должен быть абсолютным, соответствовать регулярному выражению
     *                            `/^[a-zA-Z0-9\/\.\-_]+$/`, и файл должен существовать и быть доступным для чтения.
     *
     * @return string Полный путь к файлу с правильным расширением:
     *                - Если расширение было изменено или добавлено, возвращается новый путь.
     *                - Если расширение уже корректное, возвращается исходный путь.
     *
     * @throws InvalidArgumentException В следующих случаях:
     *                                  - Если путь к файлу имеет недопустимый формат.
     *                                  - Если файл не существует или недоступен.
     * @throws RuntimeException         В следующих случаях:
     *                                  - Если MIME-тип файла не поддерживается.
     *                                  - Если файл недоступен для чтения.
     * @throws Exception                При возникновении ошибок логирования информации.
     *
     * @warning Метод завершает выполнение с ошибкой, если MIME-тип файла не поддерживается.
     *          Убедитесь, что файл существует и доступен для чтения перед вызовом метода.
     *
     * @uses \PhotoRigma\Classes\Work_Image::fix_file_extension() Публичный метод в дочернем классе, реализующий
     *                                                            основную логику.
     */
    public function fix_file_extension(string $full_path): string
    {
        return $this->image->fix_file_extension($full_path);
    }

    /**
     * Удаляет директорию и её содержимое через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода remove_directory() в классе Work_Image.
     * Он удаляет указанную директорию и всё её содержимое, предварительно проверяя права доступа.
     *
     * @param string $path Путь к директории:
     *                     - Должен быть строкой, указывающей на существующую директорию.
     *                     - Директория должна быть доступна для записи.
     *
     * @return bool Возвращает `true`, если директория успешно удалена.
     *
     * @throws RuntimeException Выбрасывается исключение в следующих случаях:
     *                           - Если директория не существует.
     *                           - Если директория недоступна для записи.
     *                           - Если не удалось удалить файл внутри директории.
     *                           - Если не удалось удалить саму директорию.
     *
     * @note    Метод рекурсивно удаляет все файлы внутри директории.
     *
     * @warning Используйте этот метод с осторожностью, так как удаление директории необратимо.
     *          Убедитесь, что переданная директория действительно должна быть удалена.
     *
     * @uses \PhotoRigma\Classes\Work_Image::remove_directory() Публичный метод в дочернем классе, реализующий
     *                                                          основную логику.
     */
    public function remove_directory(string $path): bool
    {
        return $this->image->remove_directory($path);
    }

    /**
     * Создает директории для категории через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для вызова внутреннего метода create_directory() в классе Work_Image.
     * Он создаёт директории для галереи и миниатюр.
     *
     * @param string $directory_name Имя директории:
     *                               - Должен быть строкой, содержащей только допустимые символы для имён директорий.
     *                               - Не должен содержать запрещённых символов (например, `\/:*?"<>|`).
     *
     * @return bool Возвращает `true`, если директории успешно созданы.
     *
     * @throws RuntimeException Выбрасывается исключение в следующих случаях:
     *                           - Если родительская директория недоступна для записи.
     *                           - Если не удалось создать директории.
     *
     * @warning Используйте этот метод с осторожностью, так как он создаёт директории.
     *
     * @uses \PhotoRigma\Classes\Work_Image::create_directory() Публичный метод в дочернем классе, реализующий
     *                                                          основную логику.
     */
    public function create_directory(string $directory_name): bool
    {
        return $this->image->create_directory($directory_name);
    }
}
