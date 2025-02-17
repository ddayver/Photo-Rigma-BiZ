<?php

/**
 * @file        include/work.php
 * @brief       Файл содержит класс Work, который является основным классом приложения.
 *              Объединяет подклассы для выполнения задач.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-02-11
 * @namespace   PhotoRigma\\Classes
 *
 * @details     Этот файл содержит основной класс приложения `Work`, который объединяет подклассы для выполнения различных задач.
 *              Класс предоставляет:
 *              - Хранилище для данных о конфигурации.
 *              - Механизмы для работы с безопасностью, включая проверку входных данных и защиту от спам-ботов.
 *              - Механизмы для работы с изображениями через интерфейс `Work_Image_Interface`.
 *              - Класс Work_Helper предоставляет методы для очистки строк, преобразования размеров и проверки MIME-типов.
 *              - Интеграцию с интерфейсами для работы с базой данных, обработкой данных.
 *              - Механизмы для управления директориями, пользовательской статистикой и другими компонентами системы.
 *
 * @see         PhotoRigma::Classes::Database_Interface Интерфейс для работы с базой данных.
 * @see         PhotoRigma::Classes::Work_Security_Interface Интерфейс для работы с безопасностью приложения.
 * @see         PhotoRigma::Classes::Work_Image_Interface Интерфейс, определяющий методы для работы с изображениями.
 * @see         PhotoRigma::Classes::Work_Template_Interface Интерфейс для работы с шаблонами.
 * @see         PhotoRigma::Classes::Work_Helper_Interface Интерфейс для вспомогательных функций, таких как очистка строк и проверка MIME-типов.
 * @see         PhotoRigma::Classes::Work_CoreLogic_Interface Интерфейс для реализации основной логики приложения.
 * @see         PhotoRigma::Classes::User_Interface Интерфейс для работы с пользователями.
 * @see         PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
 * @see         index.php Файл, который подключает work.php.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в организации работы приложения.
 *
 * @todo        Реализовать поддержку кэширования языковых переменных с хранением в формате JSON в файлах.
 *
 * @copyright   Copyright (c) 2025 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать, распространять, сублицензировать
 *              и/или продавать копии программного обеспечения, а также разрешать лицам, которым предоставляется данное
 *              программное обеспечение, делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые части
 *                программного обеспечения.
 */

namespace PhotoRigma\Classes;

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

use PhotoRigma\Classes\Work_Security;
use PhotoRigma\Classes\Work_Image;
use PhotoRigma\Classes\Work_Template;
use PhotoRigma\Classes\Work_Helper;
use PhotoRigma\Classes\Work_CoreLogic;

/**
 * Основной класс приложения.
 *
 * @details     Класс `Work` является центральной точкой приложения и предоставляет:
 *              - Хранилище для данных о конфигурации.
 *              - Механизмы для работы с безопасностью, включая проверку входных данных и защиту от спам-ботов.
 *              - Механизмы для работы с изображениями через интерфейс `Work_Image_Interface`.
 *              - Интеграцию с интерфейсами для работы с базой данных, обработкой данных.
 *              - Механизмы для управления директориями, пользовательской статистикой и другими компонентами системы.
 *
 * @see         PhotoRigma::Classes::Database Класс для работы с базой данных.
 * @see         PhotoRigma::Classes::Work_Security Класс для работы с безопасностью приложения.
 * @see         PhotoRigma::Classes::Work_Image Класс, реализующий интерфейс `Work_Image_Interface` для работы с изображениями.
 * @see         PhotoRigma::Classes::Work_Template Класс для работы с шаблонами.
 * @see         PhotoRigma::Classes::Work_Helper Класс для очистки строк, преобразования размеров и проверки MIME-типов.
 * @see         PhotoRigma::Classes::Work_CoreLogic Класс для реализации основной логики приложения.
 * @see         PhotoRigma::Classes::User Класс для работы с пользователями.
 *
 * Пример использования класса Work:
 * @code
 *              $db = new Database(); // Инициализация объекта базы данных
 *              $config = ['site_name' => 'PhotoRigma', 'theme' => 'dark']; // Конфигурация
 *              $work = new Work($db, $config); // Создание экземпляра класса
 *
 *              // Установка языковых данных
 *              $lang = [
 *                  'news' => [
 *                      'month_name' => 'Месяц',
 *                      'months' => [
 *                          '01' => 'январь',
 *                          '02' => 'февраль',
 *                      ],
 *                  ],
 *              ];
 *              $work->set_lang($lang);
 *
 *              // Установка пользователя
 *              $user = new User(1, 'John Doe');
 *              $work->set_user($user);
 * @endcode
 */
class Work
{
    // Свойства:
    private array $config = []; ///< Массив, хранящий конфигурацию приложения.
    private ?Database_Interface $db = null; ///< Объект для работы с базой данных.
    private Work_Security $security; ///< Объект для работы с безопасностью.
    private Work_Image $image; ///< Объект класса `Work_Image` для работы с изображениями.
    private Work_Template $template; ///< Объект для работы с шаблонами.
    private Work_Helper $helper; ///< Объект для очистки строк, преобразования размеров и проверки MIME-типов.
    private Work_CoreLogic $core_logic; ///< Объект для основной логики приложения.

    /**
     * @brief Конструктор класса.
     *
     * @details Этот метод вызывается автоматически при создании нового объекта класса.
     *          Используется для инициализации основных компонентов приложения:
     *          - Загрузка конфигурации из базы данных (таблица TBL_CONFIG).
     *          - Инициализация дочерних классов: Work_Helper, Work_Security, Work_Image, Work_Template, Work_CoreLogic.
     *          - Установка кодировки UTF-8 для работы с мультибайтовыми строками.
     *
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work::$config Свойство, содержащее конфигурацию.
     * @see PhotoRigma::Classes::Work::$db Объект для работы с базой данных.
     * @see PhotoRigma::Classes::Work::$security Свойство, содержащее объект Work_Security.
     * @see PhotoRigma::Classes::Work::$image Свойство, содержащее объект Work_Image.
     * @see PhotoRigma::Classes::Work::$template Свойство, содержащее объект Work_Template.
     * @see PhotoRigma::Classes::Work::$helper Свойство, содержащее объект для очистки строк, преобразования размеров и проверки MIME-типов.
     * @see PhotoRigma::Classes::Work::$core_logic Свойство, содержащее объект Work_CoreLogic.
     * @see PhotoRigma::Include::log_in_file() Логирует ошибки.
     *
     * @param Database_Interface $db Объект для работы с базой данных.
     * @param array $config Конфигурация приложения.
     *
     * @note В конструкторе инициализируются 5 дочерних классов:
     *       - Work_Helper: Для вспомогательных функций.
     *       - Work_Security: Для работы с безопасностью.
     *       - Work_Image: Для работы с изображениями.
     *       - Work_Template: Для работы с шаблонами.
     *       - Work_CoreLogic: Для реализации бизнес-логики приложения.
     *       Также используется константа TBL_CONFIG для загрузки конфигурации из базы данных.
     *
     * @warning Если таблица TBL_CONFIG отсутствует или данные не загружены, это может привести к неполному функционированию приложения.
     *          Ошибки записываются в лог через log_in_file.
     *
     * Пример создания объекта класса Work:
     * @code
     * $db = new \PhotoRigma\Classes\Database($db_config);
     * $config = [
     *     'app_name' => 'PhotoRigma',
     *     'debug_mode' => true,
     * ];
     * $work = new \PhotoRigma\Classes\Work($db, $config);
     * @endcode
     */
    public function __construct(Database_Interface $db, array $config)
    {
        $this->db = $db;
        // Загружаем конфигурацию из базы данных
        $this->db->select(['*'], TBL_CONFIG, ['params' => []]);
        $result = $this->db->res_arr();
        if (is_array($result)) {
            foreach ($result as $tmp) {
                $this->config[$tmp['name']] = $tmp['value'];
            }
        } else {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Ошибка загрузки конфигурации | Не удалось получить данные из таблицы " . TBL_CONFIG
            );
        }
        // Инициализация подклассов
        $this->helper = new Work_Helper();
        $this->security = new Work_Security();
        $this->image = new Work_Image($config);
        $this->template = new Work_Template($config, $db);
        $this->core_logic = new Work_CoreLogic($config, $db, $this);
        // Устанавливаем кодировку для работы с мультибайтовыми строками
        mb_regex_encoding('UTF-8');
    }

    /**
     * @brief Магический метод для получения значения свойства `$config`.
     *
     * @details Этот метод вызывается автоматически при попытке получить значение недоступного свойства.
     *          Доступ разрешён только к свойству `$config`. Если запрашивается другое свойство,
     *          выбрасывается исключение InvalidArgumentException.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work::$config Свойство, содержащее конфигурацию.
     *
     * @param string $name Имя свойства:
     *                     - Допустимое значение: 'config'.
     *                     - Если указано другое имя, выбрасывается исключение.
     *
     * @return array Значение свойства `$config`.
     *
     * @throws InvalidArgumentException Если запрашиваемое свойство не существует или недоступно.
     *
     * @note Этот метод предназначен только для доступа к свойству `$config`.
     *       Любые другие запросы будут игнорироваться с выбросом исключения.
     *
     * @warning Попытка доступа к несуществующему свойству вызовет исключение.
     *          Убедитесь, что вы запрашиваете только допустимые свойства.
     *
     * @example \\PhotoRigma\\Classes\\Work::__get
     * @code
     * // Пример использования метода
     * $work = new \PhotoRigma\Classes\Work();
     * echo $work->config['key']; // Выведет значение ключа 'key' из конфигурации
     * @endcode
     */
    public function __get(string $name): array
    {
        if ($name === 'config') {
            return $this->config;
        }
        throw new \InvalidArgumentException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не существует | Получено: '{$name}'"
        );
    }

    /**
     * @brief Устанавливает значение свойства `$config`.
     *
     * @details Этот метод вызывается автоматически при попытке установить значение недоступного свойства.
     *          Доступ разрешён только к свойству `$config`. Если запрашивается другое свойство,
     *          выбрасывается исключение Exception.
     *          Значение `$config` должно быть массивом, где ключи и значения являются строками.
     *          При успешном обновлении конфигурации:
     *          - Изменения логируются с помощью функции log_in_file (за исключением ключей из списка exclude_from_logging).
     *          - Обновлённая конфигурация передаётся в дочерние классы ($this->image, $this->template, $this->core_logic)
     *            через их свойства config.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work::$config Свойство, содержащее конфигурацию.
     * @see PhotoRigma::Classes::Work_CoreLogic::$config Свойство дочернего класса Work_CoreLogic.
     * @see PhotoRigma::Classes::Work_Image::$config Свойство дочернего класса Work_Image.
     * @see PhotoRigma::Classes::Work_Template::$config Свойство дочернего класса Work_Template.
     * @see PhotoRigma::Include::log_in_file() Логирует ошибки.
     *
     * @param string $name Имя свойства:
     *                     - Допустимое значение: 'config'.
     *                     - Если указано другое имя, выбрасывается исключение.
     * @param mixed $value Значение свойства:
     *                     - Должен быть массивом, где ключи и значения являются строками.
     *
     * @throws InvalidArgumentException Если значение некорректно (не массив или содержатся некорректные ключи/значения).
     * @throws Exception Если запрашивается несуществующее свойство.
     *
     * @note Этот метод предназначен только для изменения свойства `$config`.
     *       Логирование изменений выполняется только для определённых ключей.
     *
     * @warning Некорректные данные (не массив, нестроковые ключи или значения) вызывают исключение.
     *          Попытка установки значения для несуществующего свойства также вызывает исключение.
     *
     * @example \\PhotoRigma\\Classes\\Work::__set
     * @code
     * // Пример использования метода
     * $work = new \PhotoRigma\Classes\Work();
     * $work->config = [
     *     'theme' => 'dark',
     *     'language' => 'en'
     * ];
     * @endcode
     */
    public function __set($name, $value)
    {
        if ($name === 'config') {
            // Проверка, что значение является массивом
            if (!is_array($value)) {
                throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                    "Некорректный тип значения | Значение config должно быть массивом"
                );
            }
            // Проверка ключей и значений
            $errors = [];
            foreach ($value as $key => $val) {
                if (!is_string($key)) {
                    $errors[] = "Ключ '{$key}' должен быть строкой.";
                }
                if (!is_string($val)) {
                    $errors[] = "Значение для ключа '{$key}' должно быть строкой.";
                }
            }
            if (!empty($errors)) {
                throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                    "Обнаружены ошибки в конфигурации | Ошибки: " . json_encode($errors)
                );
            }
            // Логирование изменений
            $exclude_from_logging = ['language', 'themes']; // Ключи, которые не нужно логировать
            $updated_settings = [];
            $added_settings = [];
            foreach ($value as $key => $val) {
                if (in_array($key, $exclude_from_logging)) {
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
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                    "Обновление настроек | Настройки: " . json_encode($updated_settings)
                );
            }
            if (!empty($added_settings)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                    "Добавление настроек | Настройки: " . json_encode($added_settings)
                );
            }
            // Обновляем основной конфиг
            $this->config = $value;
            // Передаём конфигурацию в подклассы через магические методы
            $this->image->config = $this->config;
            $this->template->config = $this->config;
            $this->core_logic->config = $this->config;
        } else {
            throw new \Exception(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Несуществующее свойство | Свойство: {$name}"
            );
        }
    }

    /**
     * @brief Установка массива языковых данных через сеттер.
     *
     * @details Этот метод позволяет установить массив языковых данных ($lang).
     *          Метод выполняет следующие действия:
     *          1. Проверяет, что массив не пустой.
     *          2. Рекурсивно проверяет корректность ключей и значений с помощью метода validate_array.
     *          3. Обрабатывает массив через метод sanitize_array, который использует Work::clean_field.
     *          4. Логирует изменения, если они были выполнены.
     *          5. Передаёт обработанные данные в дочерние классы (Work_Template и Work_CoreLogic)
     *             через их методы set_lang.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work::validate_array Рекурсивная проверка массива на корректность ключей и значений.
     * @see PhotoRigma::Classes::Work::sanitize_array Рекурсивная обработка массива через Work::clean_field.
     * @see PhotoRigma::Classes::Work_CoreLogic::$lang Свойство дочернего класса Work_CoreLogic.
     * @see PhotoRigma::Classes::Work_Template::$lang Свойство дочернего класса Work_Template.
     * @see PhotoRigma::Include::log_in_file() Логирует ошибки.
     *
     * @param array $lang Массив языковых данных:
     *                    - Не должен быть пустым.
     *                    - Ключи и значения должны соответствовать ограничениям (глубина до 4 уровней).
     *
     * @throws InvalidArgumentException Если массив некорректен (пустой или содержит некорректные ключи/значения).
     *
     * @note Метод рекурсивно проверяет и обрабатывает массив языковых данных.
     *       Изменения логируются только при наличии изменений.
     *
     * @warning Некорректные данные (пустой массив или некорректные ключи/значения) вызывают исключение.
     *          Массив должен соответствовать ограничению глубины до 4 уровней.
     *
     * @todo Интегрировать в систему кеширования языковых переменных.
     *
     * @example \\PhotoRigma\\Classes\\Work::set_lang
     * @code
     * // Пример использования метода
     * $work = new \PhotoRigma\Classes\Work();
     * $lang = [
     *     'key1' => 'value1',
     *     'key2' => [
     *         'subkey1' => 'subvalue1'
     *     ]
     * ];
     * $work->set_lang($lang);
     * @endcode
     */
    public function set_lang(array $lang)
    {
        if (empty($lang)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Некорректный массив языковых данных | Массив не должен быть пустым"
            );
        }
        // Проверяем массив на корректность
        $errors = $this->validate_array($lang, 4); // Ограничение глубины до 4 уровней
        if (!empty($errors)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Обнаружены ошибки в массиве языковых данных | Ошибки: " . json_encode($errors)
            );
        }
        // Обрабатываем массив через clean_field
        $changes = $this->sanitize_array($lang);
        // Логируем изменения, если они есть
        if (!empty($changes)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Очищенные значения | Значения: " . json_encode($changes)
            );
        }
        // Передаём языковые данные в подклассы
        $this->template->set_lang($lang);
        $this->core_logic->set_lang($lang);
    }

    /**
     * @brief Установка объекта пользователя через сеттер.
     *
     * @details Этот метод позволяет установить объект пользователя ($user).
     *          Метод выполняет следующие действия:
     *          1. Проверяет, что переданный объект является экземпляром класса User.
     *          2. Передаёт объект в дочерние классы (Work_Template и Work_CoreLogic)
     *             через их методы set_user.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::User Класс с объектом пользователя.
     * @see PhotoRigma::Classes::Work_CoreLogic::$user Свойство дочернего класса Work_CoreLogic.
     * @see PhotoRigma::Classes::Work_Template::$user Свойство дочернего класса Work_Template.
     * @see PhotoRigma::Include::log_in_file() Логирует ошибки.
     *
     * @param User $user Объект пользователя:
     *                   - Должен быть экземпляром класса User.
     *
     * @throws InvalidArgumentException Если передан некорректный объект (не экземпляр класса User).
     *
     * @note Метод проверяет тип переданного объекта.
     *       Объект пользователя передаётся в дочерние классы для дальнейшего использования.
     *
     * @warning Некорректный объект (не экземпляр класса User) вызывает исключение.
     *
     * @example \\PhotoRigma\\Classes\\Work::set_user
     * @code
     * // Пример использования метода
     * $work = new \PhotoRigma\Classes\Work();
     * $user = new \PhotoRigma\Classes\User();
     * $work->set_user($user);
     * @endcode
     */
    public function set_user(User $user)
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Некорректный тип аргумента | Ожидается объект класса User"
            );
        }
        $this->template->set_user($user);
        $this->core_logic->set_user($user);
    }

    /**
     * Заглушка для check_post().
     *
     * @deprecated Этот метод устарел. Используйте PhotoRigma::Classes::Work::check_input() вместо него.
     * @see PhotoRigma::Classes::Work_Security::check_input() Метод, который проверяет входные данные.
     * @param string $field Поле для проверки.
     * @param bool $isset Флаг, указывающий, что поле должно существовать.
     * @param bool $empty Флаг, указывающий, что поле не должно быть пустым.
     * @param string|false $regexp Регулярное выражение для проверки поля или false, если проверка не требуется.
     * @param bool $not_zero Флаг, указывающий, что значение не должно быть нулём.
     * @return bool True, если данные прошли проверку, иначе False.
     */
    public function check_post(string $field, bool $isset = false, bool $empty = false, string|false $regexp = false, bool $not_zero = false): bool
    {
        return $this->security->check_input($_POST, $field, [
            'isset' => $isset,
            'empty' => $empty,
            'regexp' => $regexp,
            'not_zero' => $not_zero,
        ]);
    }

    /**
     * Заглушка для check_get().
     *
     * @deprecated Этот метод устарел. Используйте PhotoRigma::Classes::Work::check_input() вместо него.
     * @see PhotoRigma::Classes::Work_Security::check_input() Метод, который проверяет входные данные.
     * @param string $field Поле для проверки.
     * @param bool $isset Флаг, указывающий, что поле должно существовать.
     * @param bool $empty Флаг, указывающий, что поле не должно быть пустым.
     * @param string|false $regexp Регулярное выражение для проверки поля или false, если проверка не требуется.
     * @param bool $not_zero Флаг, указывающий, что значение не должно быть нулём.
     * @return bool True, если данные прошли проверку, иначе False.
     */
    public function check_get(string $field, bool $isset = false, bool $empty = false, string|false $regexp = false, bool $not_zero = false): bool
    {
        return $this->security->check_input($_GET, $field, [
            'isset' => $isset,
            'empty' => $empty,
            'regexp' => $regexp,
            'not_zero' => $not_zero,
        ]);
    }

    /**
     * Заглушка для check_session().
     *
     * @deprecated Этот метод устарел. Используйте PhotoRigma::Classes::Work::check_input() вместо него.
     * @see PhotoRigma::Classes::Work_Security::check_input() Метод, который проверяет входные данные.
     * @param string $field Поле для проверки.
     * @param bool $isset Флаг, указывающий, что поле должно существовать.
     * @param bool $empty Флаг, указывающий, что поле не должно быть пустым.
     * @param string|false $regexp Регулярное выражение для проверки поля или false, если проверка не требуется.
     * @param bool $not_zero Флаг, указывающий, что значение не должно быть нулём.
     * @return bool True, если данные прошли проверку, иначе False.
     */
    public function check_session(string $field, bool $isset = false, bool $empty = false, string|false $regexp = false, bool $not_zero = false): bool
    {
        return $this->security->check_input($_SESSION, $field, [
            'isset' => $isset,
            'empty' => $empty,
            'regexp' => $regexp,
            'not_zero' => $not_zero,
        ]);
    }

    /**
     * @brief Проверяет URL на наличие вредоносного кода.
     *
     * @details Этот метод является делегатом для метода url_check класса Work_Security.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * if (!$work->url_check()) {
     *     echo "Обнаружен подозрительный URL!";
     * }
     * @endcode
     *
     * @see PhotoRigma::Classes::Work_Security::url_check() Реализация метода внутри класса Work_Security.
     *
     * @return bool True, если URL безопасен, иначе False.
     */
    public function url_check(): bool
    {
        return $this->security->url_check();
    }

    /**
     * @brief Универсальная проверка входных данных.
     *
     * @details Этот метод является делегатом для метода check_input класса Work_Security.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $result = $work->check_input('_POST', 'username', [
     *     'isset' => true,
     *     'empty' => false,
     *     'regexp' => '/^[a-z0-9]+$/i',
     * ]);
     * if ($result) {
     *     echo "Проверка пройдена!";
     * }
     * @endcode
     *
     * @see PhotoRigma::Classes::Work_Security::check_input() Реализация метода внутри класса Work_Security.
     *
     * @param string $source_name Источник данных ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES).
     * @param string $field Поле для проверки (имя ключа в массиве источника данных).
     * @param array{
     *     isset?: bool,          // Проверять наличие поля в источнике данных.
     *     empty?: bool,          // Проверять, что значение поля не пустое.
     *     regexp?: string|false, // Регулярное выражение для проверки значения поля.
     *     not_zero?: bool,       // Проверять, что значение поля не равно нулю.
     *     max_size?: int         // Максимальный размер файла (в байтах) для $_FILES.
     * } $options Дополнительные параметры проверки.
     *
     * @return bool True, если данные прошли проверку, иначе False.
     */
    public function check_input(string $source_name, string $field, array $options = []): bool
    {
        return $this->security->check_input($source_name, $field, $options);
    }

    /**
     * @brief Заменяет символы в email-адресах для "обмана" ботов.
     *
     * @details Этот метод является делегатом для метода filt_email класса Work_Security.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $filteredEmail = $work->filt_email('example@example.com');
     * echo $filteredEmail; // Выведет: example[at]example[dot]com
     * @endcode
     *
     * @see PhotoRigma::Classes::Work_Security::filt_email() Реализация метода внутри класса Work_Security.
     *
     * @param string $email Email-адрес для обработки.
     *
     * @return string Обработанный email-адрес.
     */
    public function filt_email(string $email): string
    {
        return $this->security->filt_email($email);
    }

    /**
     * @brief Проверяет содержимое поля на соответствие регулярному выражению или другим условиям.
     *
     * @details Этот метод является делегатом для метода check_field класса Work_Security.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $result = $work->check_field('test123', '/^[a-z0-9]+$/i', false);
     * if ($result) {
     *     echo "Поле прошло проверку!";
     * }
     * @endcode
     *
     * @see PhotoRigma::Classes::Work_Security::check_field() Реализация метода внутри класса Work_Security.
     *
     * @param string $field Значение поля для проверки.
     * @param string|false $regexp Регулярное выражение (необязательно). Если задано, значение должно соответствовать этому выражению.
     * @param bool $not_zero Флаг, указывающий, что значение не должно быть числом 0.
     *
     * @return bool True, если поле прошло все проверки, иначе False.
     */
    public function check_field(string $field, string|false $regexp = false, bool $not_zero = false): bool
    {
        return $this->security->check_field($field, $regexp, $not_zero);
    }

    /**
     * @brief Генерирует математический CAPTCHA-вопрос и ответ.
     *
     * @details Этот метод является делегатом для метода gen_captcha класса Work_Security.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $captcha = $work->gen_captcha();
     * echo "Вопрос: {$captcha['question']}, Ответ: {$captcha['answer']}";
     * // Пример вывода: Вопрос: 2 x (3 + 4), Ответ: 14
     * @endcode
     *
     * @see PhotoRigma::Classes::Work_Security::gen_captcha() Реализация метода внутри класса Work_Security.
     *
     * @return array{
     *     question: string, // Математическое выражение (например, "2 x (3 + 4)")
     *     answer: int       // Числовой ответ на выражение (например, 14)
     * } Массив с ключами 'question' и 'answer'.
     */
    public function gen_captcha(): array
    {
        return $this->security->gen_captcha();
    }

    /**
     * Получение данных о категории.
     *
     * @see PhotoRigma::Classes::Work_CoreLogic::category() Метод для получения данных о категории.
     * @param int $cat_id ID категории.
     * @param bool $user_flag Флаг пользователя.
     * @return array Данные о категории.
     */
    public function category($cat_id, $user_flag)
    {
        return $this->core_logic->category($cat_id, $user_flag);
    }

    /**
     * Удаление изображения.
     *
     * @see PhotoRigma::Classes::Work_CoreLogic::del_photo() Метод для удаления изображения.
     * @param int $photo_id ID изображения.
     * @return void
     */
    public function del_photo($photo_id)
    {
        return $this->core_logic->del_photo($photo_id);
    }

    /**
     * Формирование новостей.
     *
     * @see PhotoRigma::Classes::Work_CoreLogic::news() Метод для формирования новостей.
     * @param array $news_data Данные новостей.
     * @param string $act Действие.
     * @return string HTML-код новостей.
     */
    public function news($news_data, $act)
    {
        return $this->core_logic->news($news_data, $act);
    }

    /**
     * Получение списка доступных языков.
     *
     * @see PhotoRigma::Classes::Work_CoreLogic::get_languages() Метод для получения списка языков.
     * @return array Список языков.
     */
    public function get_languages()
    {
        return $this->core_logic->get_languages();
    }

    /**
     * Получение списка доступных тем оформления.
     *
     * @see PhotoRigma::Classes::Work_CoreLogic::get_themes() Метод для получения списка тем.
     * @return array Список тем.
     */
    public function get_themes()
    {
        return $this->core_logic->get_themes();
    }

    /**
     * @brief Метод формирует массив данных для меню в зависимости от типа и активного пункта.
     *
     * @details Данный метод служит точкой доступа к функционалу формирования данных для меню,
     * делегируя выполнение соответствующему методу create_menu() в подклассе Work_Template.
     *
     * @see PhotoRigma::Classes::Work_Template::create_menu() Метод, реализующий логику.
     *
     * @param string $action Активный пункт меню.
     * @param int    $menu   Тип меню:
     *                       - 0: Горизонтальное краткое меню.
     *                       - 1: Вертикальное боковое меню.
     *
     * @return array Массив с данными для меню. Если меню пустое, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный $menu или $action.
     * @throws RuntimeException         Если произошла ошибка при выполнении запроса к базе данных.
     *
     * @note Данные для меню берутся из таблицы TBL_MENU. Для получения дополнительной информации см. структуру таблицы.
     * @warning Убедитесь, что передаваемые параметры корректны, так как это может привести к ошибкам.
     *
     * @example
     * @code
     * // Пример использования метода create_menu()
     * $short_menu = $work->create_menu('home', 0); // Создание горизонтального меню
     * print_r($short_menu);
     *
     * $long_menu = $work->create_menu('profile', 1); // Создание вертикального меню
     * print_r($long_menu);
     * @endcode
     */
    public function create_menu(string $action, int $menu): array
    {
        return $this->template->create_menu($action, $menu);
    }

    /**
     * @brief Формирование блока пользователя.
     *
     * @details Данный метод служит точкой доступа к функционалу формирования данных для блока
     * пользователя, делегируя выполнение соответствующему методу template_user() в подклассе Work_Template.
     *
     * @see PhotoRigma::Classes::Work_Template::template_user() Метод, реализующий логику.
     *
     * @return array Массив с данными для блока пользователя.
     *
     * @throws RuntimeException Если объект пользователя не установлен или данные некорректны.
     *
     * @note Используется глобальная переменная $_SESSION для проверки статуса авторизации.
     * @todo Заменить использование $_SESSION на метод или свойство класса для инкапсуляции доступа к сессии.
     * @todo Внедрить кэширование результатов для повышения производительности.
     *
     * @example
     * @code
     * // Пример использования метода template_user()
     * $work = new Work($db, $config);
     * $work->set_user(new User());
     * $user_block = $work->template_user();
     * print_r($user_block);
     * @endcode
     */
    public function template_user(): array
    {
        return $this->template->template_user();
    }

    /**
     * @brief Генерирует массив статистических данных для шаблона.
     *
     * @details Данный метод служит точкой доступа к функционалу генерации статистических данных,
     * делегируя выполнение соответствующему методу template_stat() в подклассе Work_Template.
     *
     * @see PhotoRigma::Classes::Work_Template::template_stat() Метод, реализующий логику.
     *
     * @return array Ассоциативный массив данных для вывода статистики:
     *               - NAME_BLOCK: Название блока статистики.
     *               - L_STAT_REGIST: Подпись для количества зарегистрированных пользователей.
     *               - D_STAT_REGIST: Количество зарегистрированных пользователей.
     *               - L_STAT_PHOTO: Подпись для количества фотографий.
     *               - D_STAT_PHOTO: Количество фотографий.
     *               - L_STAT_CATEGORY: Подпись для количества категорий.
     *               - D_STAT_CATEGORY: Количество категорий (включая пользовательские альбомы).
     *               - L_STAT_USER_ADMIN: Подпись для количества администраторов.
     *               - D_STAT_USER_ADMIN: Количество администраторов.
     *               - L_STAT_USER_MODER: Подпись для количества модераторов.
     *               - D_STAT_USER_MODER: Количество модераторов.
     *               - L_STAT_RATE_USER: Подпись для количества пользовательских оценок.
     *               - D_STAT_RATE_USER: Количество пользовательских оценок.
     *               - L_STAT_RATE_MODER: Подпись для количества модераторских оценок.
     *               - D_STAT_RATE_MODER: Количество модераторских оценок.
     *               - L_STAT_ONLINE: Подпись для онлайн-пользователей.
     *               - D_STAT_ONLINE: Список онлайн-пользователей.
     *
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных.
     *
     * @todo Вынести время онлайн в настройки через БД.
     * @note Время онлайна жестко закодировано как 900 секунд (15 минут). Для изменения требуется ручное внесение изменений в код.
     */
    public function template_stat(): array
    {
        return $this->template->template_stat();
    }

    /**
     * @brief Формирует список пользователей, загрузивших наибольшее количество изображений.
     *
     * @details Данный метод служит точкой доступа к функционалу формирования списка лучших
     * пользователей, делегируя выполнение соответствующему методу template_best_user() в подклассе Work_Template.
     *
     * @see PhotoRigma::Classes::Work_Template::template_best_user() Метод, реализующий логику.
     *
     * @param int $best_user Количество лучших пользователей для вывода. Должно быть положительным целым числом.
     *
     * @return array Массив данных для вывода в шаблон:
     *               - NAME_BLOCK: Название блока.
     *               - L_USER_NAME: Подпись для имени пользователя.
     *               - L_USER_PHOTO: Подпись для количества фотографий.
     *               - user_url: Ссылка на профиль пользователя.
     *               - user_name: Имя пользователя.
     *               - user_photo: Количество загруженных фотографий.
     *
     * @throws InvalidArgumentException Если параметр $best_user не является положительным целым числом.
     *
     * @note Если запрос к базе данных не возвращает данных, добавляется запись "пустого" пользователя.
     */
    public function template_best_user(int $best_user = 1): array
    {
        return $this->template->template_best_user($best_user);
    }

    /**
     * @brief Очистка строки от HTML-тегов и специальных символов.
     *
     * @details Метод удаляет HTML-теги и экранирует специальные символы, такие как `<`, `>`, `&`, `"`, `'`.
     *          Используется для защиты от XSS-атак и других проблем, связанных с некорректными данными.
     *          Этот метод является отсылкой на соответствующий метод в классе Work_Helper.
     *
     * @param mixed $field Строка или данные для очистки.
     * @return string|null Очищенная строка или null, если входные данные пусты.
     *
     * @see PhotoRigma::Classes::Work_Helper::clean_field() Публичный метод в классе Work_Helper.
     *
     * @example Пример использования метода:
     * @code
     * $cleaned = Work::clean_field('<script>alert("XSS")</script>');
     * echo $cleaned; // Выведет: <script>alert(&quot;XSS&quot;)</script>
     * @endcode
     */
    public static function clean_field($field): ?string
    {
        return Work_Helper::clean_field($field);
    }

    /**
     * @brief Преобразование размера в байты.
     *
     * @details Метод преобразует размер, заданный в формате "число[K|M|G]", в количество байт.
     *          Поддерживаются суффиксы:
     *          - K (килобайты): умножается на 1024.
     *          - M (мегабайты): умножается на 1024².
     *          - G (гигабайты): умножается на 1024³.
     *          Если суффикс отсутствует или недопустим, значение считается в байтах.
     *          Если входные данные некорректны, возвращается 0.
     *          Этот метод является отсылкой на соответствующий метод в классе Work_Helper.
     *
     * @param string|int $val Размер в формате "число[K|M|G]" или число.
     * @return int Размер в байтах.
     *
     * @see PhotoRigma::Classes::Work_Helper::return_bytes() Публичный метод в классе Work_Helper.
     *
     * @example Пример использования метода:
     * @code
     * $bytes = Work::return_bytes('2M');
     * echo $bytes; // Выведет: 2097152
     *
     * $bytes = Work::return_bytes('-1G');
     * echo $bytes; // Выведет: 1073741824
     *
     * $bytes = Work::return_bytes('10X');
     * echo $bytes; // Выведет: 10
     *
     * $bytes = Work::return_bytes('abc');
     * echo $bytes; // Выведет: 0
     * @endcode
     */
    public static function return_bytes($val): int
    {
        return Work_Helper::return_bytes($val);
    }

    /**
     * @brief Транслитерация строки и замена знаков пунктуации на "_".
     *
     * @details Метод выполняет транслитерацию кириллических символов в латиницу и заменяет знаки пунктуации на "_".
     *          Используется для создания "безопасных" имен файлов или URL.
     *          Если входная строка пустая, она возвращается без обработки.
     *          Если транслитерация невозможна (например, расширение intl недоступно), используется резервная таблица.
     *          Этот метод является отсылкой на соответствующий метод в классе Work_Helper.
     *
     * @param string $string Исходная строка.
     * @return string Строка после транслитерации и замены символов.
     *
     * @see PhotoRigma::Classes::Work_Helper::encodename() Публичный метод в классе Work_Helper.
     *
     * @example Пример использования метода:
     * @code
     * $encoded = Work::encodename('Привет, мир!');
     * echo $encoded; // Выведет: Privet__mir_
     *
     * $encoded = Work::encodename('');
     * echo $encoded; // Выведет: пустую строку
     *
     * $encoded = Work::encodename('12345');
     * echo $encoded; // Выведет: 12345
     * @endcode
     */
    public static function encodename(string $string): string
    {
        return Work_Helper::encodename($string);
    }

    /**
     * @brief Преобразование BBCode в HTML.
     *
     * @details Метод преобразует BBCode-теги в соответствующие HTML-теги с учетом рекурсивной обработки вложенных тегов.
     *          Поддерживаются следующие BBCode-теги:
     *          - [b]Жирный текст[/b]
     *          - [u]Подчёркнутый текст[/u]
     *          - [i]Курсив[/i]
     *          - [url]Ссылка[/url], [url=URL]Текст ссылки[/url]
     *          - [color=COLOR]Цвет текста[/color]
     *          - [size=SIZE]Размер текста[/size]
     *          - [quote]Цитата[/quote], [quote=AUTHOR]Цитата автора[/quote]
     *          - [list], [list=1], [list=a] — списки
     *          - [code]Блок кода[/code]
     *          - [spoiler]Спойлер[/spoiler]
     *          - [hr] — горизонтальная линия
     *          - [br] — перенос строки
     *          - [left], [center], [right] — выравнивание текста
     *          - [img]Изображение[/img]
     *
     *          Метод защищает от XSS-атак, проверяет корректность URL и ограничивает глубину рекурсии для вложенных тегов.
     *          Этот метод является отсылкой на соответствующий метод в классе Work_Helper.
     *
     * @param string $text Текст с BBCode.
     * @return string Текст с HTML-разметкой.
     *
     * @see PhotoRigma::Classes::Work_Helper::ubb() Публичный метод в классе Work_Helper.
     *
     * @example Пример использования метода:
     * @code
     * $html = Work::ubb('[b]Bold text[/b]');
     * echo $html; // Выведет: <strong>Bold text</strong>
     *
     * $html = Work::ubb('[url=https://example.com]Example[/url]');
     * echo $html; // Выведет: <a href="https://example.com" target="_blank" rel="noopener noreferrer" title="Example">Example</a>
     * @endcode
     */
    public static function ubb(string $text): string
    {
        return Work_Helper::ubb($text);
    }

    /**
     * @brief Разбивка строки на несколько строк ограниченной длины.
     *
     * @details Метод разбивает строку на несколько строк, каждая из которых имеет длину не более указанной.
     *          Разрыв строки выполняется только по пробелам, чтобы сохранить читаемость текста.
     *          Поддерживается работа с UTF-8 символами.
     *          Если параметры некорректны (например, $width <= 0 или $break пустой), возвращается исходная строка.
     *          Этот метод является отсылкой на соответствующий метод в классе Work_Helper.
     *
     * @param string $str Исходная строка.
     * @param int $width Максимальная длина строки (по умолчанию 70).
     * @param string $break Символ разрыва строки (по умолчанию PHP_EOL).
     * @return string Строка, разбитая на несколько строк.
     *
     * @see PhotoRigma::Classes::Work_Helper::utf8_wordwrap() Публичный метод в классе Work_Helper.
     *
     * @example Пример использования метода:
     * @code
     * $wrapped = Work::utf8_wordwrap('This is a very long string that needs to be wrapped.', 10);
     * echo $wrapped;
     * // Выведет:
     * // This is a
     * // very long
     * // string that
     * // needs to be
     * // wrapped.
     * @endcode
     */
    public static function utf8_wordwrap(string $str, int $width = 70, string $break = PHP_EOL): string
    {
        return Work_Helper::utf8_wordwrap($str, $width, $break);
    }

    /**
     * @brief Проверка MIME-типа файла через доступные библиотеки.
     *
     * @details Метод проверяет, поддерживается ли указанный MIME-тип хотя бы одной из доступных библиотек:
     *          - Imagick
     *          - Gmagick
     *          - GD
     *          Если MIME-тип не поддерживается ни одной библиотекой, возвращается false.
     *          Поддерживаются MIME-типы для изображений, таких как JPEG, PNG, GIF, WebP и другие.
     *          Этот метод является отсылкой на соответствующий метод в классе Work_Helper.
     *
     * @param string $real_mime_type Реальный MIME-тип файла.
     * @return bool True, если MIME-тип поддерживается хотя бы одной библиотекой, иначе False.
     *
     * @see PhotoRigma::Classes::Work_Helper::validate_mime_type() Публичный метод в классе Work_Helper.
     *
     * @example Пример использования метода:
     * @code
     * $is_supported = Work::validate_mime_type('image/jpeg');
     * var_dump($is_supported); // Выведет: true
     *
     * $is_supported = Work::validate_mime_type('application/pdf');
     * var_dump($is_supported); // Выведет: false
     * @endcode
     */
    public static function validate_mime_type(string $real_mime_type): bool
    {
        return Work_Helper::validate_mime_type($real_mime_type);
    }

    /**
     * @brief Вычисляет размеры для вывода эскиза изображения.
     *
     * @details Это метод-витрина, который вызывает соответствующий метод из класса Work_Image.
     *
     * @see PhotoRigma::Classes::Work_Image::size_image() Оригинальный метод в Work_Image.
     *
     * @param string $path_image Путь к файлу изображения.
     * @return array Массив с шириной и высотой эскиза.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $thumbnail_size = $work->size_image('/path/to/image.jpg');
     * echo "Ширина: {$thumbnail_size['width']}, Высота: {$thumbnail_size['height']}";
     * @endcode
     */
    public function size_image(string $path_image): array
    {
        return $this->image->size_image($path_image);
    }

    /**
     * @brief Изменяет размер изображения.
     *
     * @details Это метод-витрина, который вызывает соответствующий метод из класса Work_Image.
     *
     * @see PhotoRigma::Classes::Work_Image::image_resize() Оригинальный метод в Work_Image.
     *
     * @param string $full_path Путь к исходному изображению.
     * @param string $thumbnail_path Путь для сохранения эскиза.
     * @return bool True, если операция выполнена успешно, иначе False.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $result = $work->image_resize('/path/to/full_image.jpg', '/path/to/thumbnail.jpg');
     * if ($result) {
     *     echo "Эскиз успешно создан!";
     * }
     * @endcode
     */
    public function image_resize(string $full_path, string $thumbnail_path): bool
    {
        return $this->image->image_resize($full_path, $thumbnail_path);
    }

    /**
     * @brief Возвращает данные для отсутствующего изображения.
     *
     * @details Это метод-витрина, который вызывает соответствующий метод из класса Work_Image.
     *
     * @see PhotoRigma::Classes::Work_Image::no_photo() Оригинальный метод в Work_Image.
     *
     * @return array Массив с данными об отсутствующем изображении.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $noPhotoData = $work->no_photo();
     * echo "URL изображения: {$noPhotoData['url']}\n";
     * echo "Описание: {$noPhotoData['description']}\n";
     * @endcode
     */
    public function no_photo(): array
    {
        return $this->image->no_photo();
    }

    /**
     * @brief Вывод изображения через HTTP.
     *
     * @details Это метод-витрина, который вызывает соответствующий метод из класса Work_Image.
     *
     * @see PhotoRigma::Classes::Work_Image::image_attach() Оригинальный метод в Work_Image.
     *
     * @param string $full_path Полный путь к файлу.
     * @param string $name_file Имя файла для заголовка Content-Disposition.
     * @return void Метод ничего не возвращает.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $work->image_attach('/path/to/image.jpg', 'image.jpg');
     * @endcode
     */
    public function image_attach(string $full_path, string $name_file): void
    {
        $this->image->image_attach($full_path, $name_file);
    }

    /**
     * @brief Корректировка расширения файла в соответствии с его MIME-типом.
     *
     * @details Это метод-витрина, который вызывает соответствующий метод из класса Work_Image.
     *
     * @see PhotoRigma::Classes::Work_Image::fix_file_extension() Оригинальный метод в Work_Image.
     *
     * @param string $full_path Полный путь к файлу.
     * @return string Полный путь к файлу с правильным расширением.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $fixed_path = $work->fix_file_extension('/path/to/file');
     * echo "Исправленный путь: {$fixed_path}";
     * @endcode
     */
    public function fix_file_extension(string $full_path): string
    {
        return $this->image->fix_file_extension($full_path);
    }

    /**
     * Создание изображения на основе типа и ID.
     *
     * @see PhotoRigma::Classes::Work_Image::create_photo() Метод для создания изображения.
     * @param string $type Тип изображения.
     * @param int|null $id_photo ID изображения.
     * @return array Данные созданного изображения.
     */
    public function create_photo($type, $id_photo)
    {
        return $this->image->create_photo($type, $id_photo);
    }

    /**
     * Генерация данных изображения.
     *
     * @see PhotoRigma::Classes::Work_Image::generate_photo_data() Метод для генерации данных изображения.
     * @param array $photo_data Исходные данные изображения.
     * @return array Сгенерированные данные изображения.
     */
    public function generate_photo_data($photo_data)
    {
        return $this->image->generate_photo_data($photo_data);
    }

    /**
     * @brief Рекурсивная проверка массива на корректность ключей и значений.
     *
     * @details Метод выполняет рекурсивную проверку массива на соответствие следующим условиям:
     *          1. Все ключи должны быть строками.
     *          2. Все значения должны быть либо строками, либо вложенными массивами.
     *          3. Глубина массива не должна превышать указанное значение `$max_depth`.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work::set_lang
     *      Сеттер для установки массива $lang.
     *
     * @param array $array Массив для проверки. Пустые массивы недопустимы.
     * @param int $max_depth Максимальная допустимая глубина массива (должна быть положительным целым числом).
     * @param int $current_depth Текущая глубина рекурсии (используется внутренне для контроля максимальной глубины).
     *
     * @return array Массив ошибок. Каждый элемент представляет собой строку с описанием проблемы.
     *               Пример: ['Ключ должен быть строкой', 'Глубина превышена'].
     *
     * @warning Метод чувствителен к глубине массива. Убедитесь, что параметр `$max_depth` установлен корректно.
     * @warning Не используйте метод для очень больших массивов из-за риска переполнения стека.
     *
     * @todo Планируется интеграция в систему кеширования языковых параметров.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $data = [
     *     'key1' => 'value1',
     *     'key2' => [
     *         'nested_key' => 'nested_value',
     *     ],
     * ];
     * $errors = $this->validate_array($data, 2);
     * if (!empty($errors)) {
     *     echo "Обнаружены ошибки:\n";
     *     foreach ($errors as $error) {
     *         echo "- {$error}\n";
     *     }
     * } else {
     *     echo "Массив прошёл проверку успешно.";
     * }
     * @endcode
     */
    private function validate_array(array $array, int $max_depth, int $current_depth = 1): array
    {
        $errors = [];
        foreach ($array as $key => $value) {
            if (!is_string($key)) {
                $errors[] = "Ключ '{$key}' на уровне {$current_depth} должен быть строкой.";
            }
            if (is_array($value)) {
                if ($current_depth >= $max_depth) {
                    $errors[] = "Глубина массива превышает допустимое значение ({$max_depth}).";
                } else {
                    $nested_errors = $this->validate_array($value, $max_depth, $current_depth + 1);
                    $errors = array_merge($errors, $nested_errors);
                }
            } elseif (!is_string($value)) {
                $errors[] = "Значение для ключа '{$key}' на уровне {$current_depth} должно быть строкой.";
            }
        }
        return $errors;
    }

    /**
     * @brief Рекурсивная обработка массива для безопасного вывода через HTML.
     *
     * @details Метод выполняет рекурсивную обработку массива, вызывая `Work::clean_field` для каждого строкового значения.
     *          Если значение изменяется в процессе очистки, фиксируются изменения в массиве `$changes`.
     *          Исходный массив модифицируется по ссылке.
     *
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work::set_lang
     *      Сеттер для установки массива $lang.
     * @see PhotoRigma::Classes::Work::clean_field()
     *      Обработка значений для безопасного вывода через HTML.
     *
     * @param array $array Массив для обработки. Пустые массивы недопустимы.
     *
     * @return array Массив изменений. Каждый элемент представляет собой ассоциативный массив с ключами:
     *               - 'original' (string): Исходное значение.
     *               - 'cleaned' (string): Очищенное значение.
     *               Пример: ['key' => ['original' => 'unsafe', 'cleaned' => 'safe']].
     *
     * @warning Метод изменяет исходный массив по ссылке. Убедитесь, что это допустимо в контексте использования.
     * @warning Не используйте метод для очень больших массивов из-за риска переполнения стека.
     *
     * @todo Планируется интеграция в систему кеширования языковых параметров.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $data = [
     *     'title' => '<script>alert("XSS")</script>',
     *     'description' => 'Безопасное описание',
     *     'nested' => [
     *         'unsafe_key' => '<img src=x onerror=alert(1)>',
     *     ],
     * ];
     * $changes = $this->sanitize_array($data);
     * if (!empty($changes)) {
     *     echo "Обнаружены изменения:\n";
     *     foreach ($changes as $key => $change) {
     *         echo "- Ключ '{$key}':\n";
     *         echo "  Исходное значение: {$change['original']}\n";
     *         echo "  Очищенное значение: {$change['cleaned']}\n";
     *     }
     * } else {
     *     echo "Массив не требует очистки.";
     * }
     * @endcode
     */
    private function sanitize_array(array $array): array
    {
        $changes = [];
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $nested_changes = $this->sanitize_array($value);
                $changes = array_merge($changes, $nested_changes);
            } elseif (is_string($value)) {
                $original_value = $value;
                $cleaned_value = Work::clean_field($value);
                if ($original_value !== $cleaned_value) {
                    $changes["{$key}"] = [
                        'original' => $original_value,
                        'cleaned' => $cleaned_value,
                    ];
                    $value = $cleaned_value;
                }
            }
        }
        return $changes;
    }
}
