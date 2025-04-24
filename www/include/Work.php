<?php

/**
 * @file        include/Work.php
 * @brief       Файл содержит класс Work, который является основным классом приложения.
 *              Объединяет подклассы для выполнения задач.
 *
 * @author      Dark Dayver
 * @version     0.4.1-rc1
 * @date        2025-04-25
 * @namespace   Photorigma\\Classes
 *
 * @details     Этот файл содержит основной класс приложения `Work`, который объединяет подклассы для выполнения
 *              различных задач. Класс предоставляет:
 *              - Хранилище для данных о конфигурации.
 *              - Механизмы для работы с безопасностью, включая проверку входных данных и защиту от спам-ботов.
 *              - Механизмы для работы с изображениями через интерфейс `Work_Image_Interface`.
 *              - Класс `Work_Helper` предоставляет методы для очистки строк, преобразования размеров и проверки
 *                MIME-типов.
 *              - Интеграцию с интерфейсами для работы с базой данных, обработкой данных.
 *              - Механизмы для управления директориями, пользовательской статистикой и другими компонентами системы.
 *
 * @section     Основные функции
 *              - Хранилище для данных о конфигурации.
 *              - Механизмы для работы с безопасностью (проверка входных данных, защита от спам-ботов).
 *              - Работа с изображениями (загрузка, обработка, изменение размеров).
 *              - Формирование данных для шаблонов (меню, статистика, блок пользователя).
 *              - Реализация основной логики приложения (работа с категориями, новостями, рейтингами).
 *              - Вспомогательные методы (очистка строк, преобразование размеров, проверка MIME-типов).
 *
 * @see         PhotoRigma::Interfaces::Work_Security_Interface Интерфейс для работы с безопасностью приложения.
 * @see         PhotoRigma::Interfaces::Work_Image_Interface Интерфейс, определяющий методы для работы с изображениями.
 * @see         PhotoRigma::Interfaces::Work_Template_Interface Интерфейс для работы с шаблонами.
 * @see         PhotoRigma::Interfaces::Work_Helper_Interface Интерфейс для вспомогательных функций, таких как очистка
 *              строк и проверка MIME-типов.
 * @see         PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в организации работы приложения.
 *
 * @todo        Реализовать поддержку кэширования языковых переменных с хранением в формате JSON в файлах.
 *
 * @copyright   Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать, распространять,
 *              сублицензировать и/или продавать копии программного обеспечения, а также разрешать лицам, которым
 *              предоставляется данное программное обеспечение, делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые
 *              части программного обеспечения.
 */

namespace PhotoRigma\Classes;

use Exception;
use InvalidArgumentException;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use PDOException;
use PhotoRigma\Interfaces\Database_Interface;
use PhotoRigma\Interfaces\User_Interface;
use PhotoRigma\Interfaces\Work_Interface;
use Random;
use RuntimeException;

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
 * @class   Work
 * @brief   Основной класс приложения.
 *
 * @details Класс `Work` является центральной точкой приложения и предоставляет единый интерфейс для работы с
 *          различными компонентами системы. Он реализует интерфейс `Work_Interface` и интегрирует функционал
 *          следующих классов:
 *          - `Work_Security`: Механизмы для работы с безопасностью (проверка входных данных, защита от спам-ботов).
 *          - `Work_Image`: Работа с изображениями (загрузка, обработка, изменение размеров).
 *          - `Work_Template`: Формирование данных для шаблонов (меню, статистика, блок пользователя).
 *          - `Work_CoreLogic`: Реализация основной логики приложения (работа с категориями, новостями, рейтингами).
 *          - `Work_Helper`: Вспомогательные методы (очистка строк, преобразование размеров, проверка MIME-типов).
 *          Все методы данного класса являются фасадами, которые перенаправляют вызовы на соответствующие методы
 *          дочерних классов.
 *
 * @property array          $config      Массив, хранящий конфигурацию приложения.
 * @property Work_Security  $security    Объект для работы с безопасностью.
 * @property Work_Image     $image       Объект для работы с изображениями.
 * @property Work_Template  $template    Объект для работы с шаблонами.
 * @property Work_CoreLogic $core_logic  Объект для основной логики приложения.
 * @property array          $lang        Массив с языковыми переменными.
 * @property array          $session     Массив, привязанный к глобальному массиву $_SESSION.
 *
 * Пример использования класса:
 * @code
 * // Инициализация объекта класса Work
 * $db = new \\PhotoRigma\\Classes\\Database();
 * $config = ['site_name' => 'PhotoRigma', 'theme' => 'dark'];
 * $work = new \\PhotoRigma\\Classes\\Work($db, $config, $_SESSION);
 *
 * // Установка языковых данных
 * $lang = [
 *     'news' => [
 *         'month_name' => 'Месяц',
 *         'months' => [
 *             '01' => 'январь',
 *             '02' => 'февраль',
 *         ],
 *     ],
 * ];
 * $work->set_lang('main');
 *
 * // Установка пользователя
 * $user = new \\PhotoRigma\\Classes\\User($db, $_SESSION);
 * $work->set_user($user);
 *
 * // Вызов метода через фасад (например, генерация CAPTCHA)
 * $captcha = $work->gen_captcha();
 * echo "Вопрос: {$captcha['question']}, Ответ: {$captcha['answer']}";
 * @endcode
 * @see     PhotoRigma::Interfaces::Work_Interface Интерфейс, который реализует данный класс.
 * @see     PhotoRigma::Classes::Work_Security Класс для работы с безопасностью.
 * @see     PhotoRigma::Classes::Work_Image Класс для работы с изображениями.
 * @see     PhotoRigma::Classes::Work_Template Класс для работы с шаблонами.
 * @see     PhotoRigma::Classes::Work_CoreLogic Класс для реализации основной логики приложения.
 * @see     PhotoRigma::Classes::Work_Helper Класс для вспомогательных методов.
 */
class Work implements Work_Interface
{
    // Свойства:
    private array $config; ///< Массив, хранящий конфигурацию приложения.
    private Work_Security $security; ///< Объект для работы с безопасностью.
    private Work_Image $image; ///< Объект класса `Work_Image` для работы с изображениями.
    private Work_Template $template; ///< Объект для работы с шаблонами.
    private Work_CoreLogic $core_logic; ///< Объект для основной логики приложения.
    private array $lang = []; ///< Массив с языковыми переменными
    private array $session; ///< Массив, привязанный к глобальному массиву $_SESSION

    /**
     * @brief   Конструктор класса.
     *
     * @details Этот метод вызывается автоматически при создании нового объекта класса.
     *          Используется для инициализации основных компонентов приложения:
     *          - Загрузка конфигурации из базы данных (таблица TBL_CONFIG).
     *          - Инициализация дочерних классов: Work_Helper, Work_Security, Work_Image, Work_Template,
     *          Work_CoreLogic.
     *          - Установка кодировки UTF-8 для работы с мультибайтовыми строками.
     *          - Подключение сессии для хранения пользовательских данных.
     *
     * @callgraph
     *
     * @param Database_Interface  $db       Объект для работы с базой данных.
     * @param array               $config   Конфигурация приложения.
     *                                      Должна содержать ключи, такие как 'app_name', 'debug_mode' и другие
     *                                      настройки.
     * @param array              &$session  Ссылка на массив сессии для хранения пользовательских данных.
     *                                      Массив должен быть доступен для записи и чтения.
     *
     * @note    В конструкторе инициализируются 5 дочерних классов:
     *       - Work_Helper: Для вспомогательных функций.
     *       - Work_Security: Для работы с безопасностью.
     *       - Work_Image: Для работы с изображениями.
     *       - Work_Template: Для работы с шаблонами.
     *       - Work_CoreLogic: Для реализации бизнес-логики приложения.
     *       Также используются константы:
     *       - TBL_CONFIG для загрузки конфигурации из базы данных,
     *       - KEY_SESSION для хранения пользовательских данных в сессии.
     *
     * @warning Если таблица TBL_CONFIG отсутствует или данные не загружены, это может привести к неполному
     *          функционированию приложения. Ошибки записываются в лог через log_in_file.
     *
     * Пример создания объекта класса Work:
     * @code
     * $db = new \PhotoRigma\Classes\Database($db_config);
     * $config = [
     *     'app_name' => 'PhotoRigma',
     *     'debug_mode' => true,
     * ];
     * session_start();
     * $work = new \PhotoRigma\Classes\Work($db, $config, $_SESSION);
     * @endcode
     *
     * @throws Exception Выбрасывается в случае ошибок при загрузке конфигурации или инициализации компонентов.
     *
     * @see     PhotoRigma::Classes::Work::$config Свойство, содержащее конфигурацию.
     * @see     PhotoRigma::Classes::Work::$security Свойство, содержащее объект Work_Security.
     * @see     PhotoRigma::Classes::Work::$image Свойство, содержащее объект Work_Image.
     * @see     PhotoRigma::Classes::Work::$template Свойство, содержащее объект Work_Template.
     * @see     PhotoRigma::Classes::Work::$core_logic Свойство, содержащее объект Work_CoreLogic.
     * @see     PhotoRigma::Include::log_in_file() Логирует ошибки.
     */
    public function __construct(Database_Interface $db, array $config, array &$session)
    {
        if (!isset($session[KEY_SESSION])) {
            $session[KEY_SESSION] = [];
        }
        $this->session = &$session[KEY_SESSION];
        $this->config = $config;
        // Загружаем конфигурацию из базы данных
        $db->select('*', TBL_CONFIG, ['params' => []]);
        $result = $db->res_arr();
        if (is_array($result)) {
            $this->config = array_merge($this->config, array_column($result, 'value', 'name'));
        } else {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Ошибка загрузки конфигурации | Не удалось получить данные из таблицы " . TBL_CONFIG
            );
        }
        // Инициализация подклассов
        $this->security = new Work_Security($this->session);
        $this->image = new Work_Image($this->config);
        $this->template = new Work_Template($db, $this->config);
        $this->core_logic = new Work_CoreLogic($db, $this->config, $this);
    }

    /**
     * @brief   Магический метод для получения значений свойств `$config` и `$lang`.
     *
     * @details Этот метод вызывается автоматически при попытке получить значение недоступного свойства.
     *          Доступ разрешён только к свойствам `$config` и `$lang`. Если запрашивается другое свойство,
     *          выбрасывается исключение InvalidArgumentException.
     *
     * @callgraph
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
     * Пример использования метода:
     * @code
     * $work = new \PhotoRigma\Classes\Work();
     * echo $work->config['key']; // Выведет значение ключа 'key' из конфигурации
     * echo $work->lang['message']; // Выведет значение ключа 'message' из языковых данных
     * @endcode
     * @see     PhotoRigma::Classes::Work::$lang Свойство, содержащее языковые данные.
     * @see     PhotoRigma::Classes::Work::$config Свойство, содержащее конфигурацию.
     */
    public function __get(string $name): array
    {
        return match ($name) {
            'config' => $this->config,
            'lang'   => $this->lang,
            default  => throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не существует | Получено: '$name'"
            ),
        };
    }

    /**
     * @brief   Устанавливает значение свойства `$config`.
     *
     * @details Этот метод вызывается автоматически при попытке установить значение недоступного свойства.
     *          Доступ разрешён только к свойству `$config`. Если запрашивается другое свойство,
     *          выбрасывается исключение Exception.
     *          Значение `$config` должно быть массивом, где ключи и значения являются строками.
     *          При успешном обновлении конфигурации:
     *          - Изменения логируются с помощью функции log_in_file (за исключением ключей из списка
     *          exclude_from_logging).
     *          - Обновлённая конфигурация передаётся в дочерние классы ($this->image, $this->template,
     *          $this->core_logic) через их свойства config.
     *
     * @callgraph
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
     *       Логирование изменений выполняется только для определённых ключей.
     *
     * @warning Некорректные данные (не массив, нестроковые ключи или значения) вызывают исключение.
     *          Попытка установки значения для несуществующего свойства также вызывает исключение.
     *
     * Пример использования метода:
     * @code
     * $work = new \PhotoRigma\Classes\Work();
     * $work->config = [
     *     'theme' => 'dark',
     *     'language' => 'en'
     * ];
     * @endcode
     * @see     PhotoRigma::Include::log_in_file() Логирует ошибки.
     * @see     PhotoRigma::Classes::Work::$config Свойство, содержащее конфигурацию.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$config Свойство дочернего класса Work_CoreLogic.
     * @see     PhotoRigma::Classes::Work_Image::$config Свойство дочернего класса Work_Image.
     * @see     PhotoRigma::Classes::Work_Template::$config Свойство дочернего класса Work_Template.
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
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Обнаружены ошибки в конфигурации | Ошибки: " . json_encode(
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
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Обновление настроек | Настройки: " . json_encode(
                        $updated_settings,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                    )
                );
            }
            if (!empty($added_settings)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Добавление настроек | Настройки: " . json_encode(
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
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Несуществующее свойство | Свойство: $name"
            );
        }
    }    /**
     * @brief   Преобразует размер в байты через вызов публичного метода в дочернем классе.
     *
     * @details Этот статический метод является фасадом для вызова публичного метода return_bytes()
     *          в дочернем классе Work_Helper. Он преобразует размер, заданный в формате "число[K|M|G]", в количество
     *          байт.
     *
     * @param string|int $val Размер в формате "число[K|M|G]" или число:
     *                        - Поддерживаются суффиксы: K (килобайты), M (мегабайты), G (гигабайты).
     *                        - Если суффикс недопустим, значение считается в байтах.
     *                        - Отрицательные числа преобразуются в положительные.
     *
     * @return int Размер в байтах. Возвращает `0` для некорректных входных данных.
     *
     * @warning Если суффикс недопустим, он игнорируется, и значение преобразуется в число.
     *           Метод чувствителен к формату входных данных. Убедитесь, что они корректны.
     *
     * Пример использования:
     * @code
     * // Преобразование с допустимым суффиксом
     * $bytes = \PhotoRigma\Classes\Work::return_bytes('2M');
     * echo $bytes; // Выведет: 2097152
     *
     * // Преобразование отрицательного значения
     * $bytes = \PhotoRigma\Classes\Work::return_bytes('-1G');
     * echo $bytes; // Выведет: 1073741824
     *
     * // Преобразование с недопустимым суффиксом
     * $bytes = \PhotoRigma\Classes\Work::return_bytes('10X');
     * echo $bytes; // Выведет: 10
     *
     * // Преобразование некорректных данных
     * $bytes = \PhotoRigma\Classes\Work::return_bytes('abc');
     * echo $bytes; // Выведет: 0
     * @endcode
     * @see     PhotoRigma::Classes::Work_Helper::return_bytes()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public static function return_bytes(string|int $val): int
    {
        return Work_Helper::return_bytes($val);
    }

    /**
     * @brief   Проверяет существование недоступного свойства.
     *
     * @details Этот метод вызывается автоматически при использовании оператора `isset()` для проверки
     *          существования недоступного свойства. Метод возвращает `true`, если свойство существует,
     *          и `false` в противном случае.
     *
     * @callgraph
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
     *
     * Пример использования метода:
     * @code
     * $work = new \PhotoRigma\Classes\Work();
     * if (isset($work->config)) {
     *     echo "Свойство 'config' существует.";
     * } else {
     *     echo "Свойство 'config' не существует.";
     * }
     * @endcode
     */
    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    /**
     * @brief   Формирует информационную строку для категории или пользовательского альбома через вызов внутреннего
     *          метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода category()
     *          в классе Work_CoreLogic. Он выполняет запросы к базе данных для получения информации о категории
     *          или пользовательском альбоме, включая количество фотографий, данные о последней и лучшей фотографии,
     *          а также ссылки на них.
     *
     * @param int $cat_id       Идентификатор категории или пользователя (если `$user_flag = 1`):
     *                          - Должен быть целым числом >= `0`.
     *                          - Пример: `5` (для категории) или `123` (для пользовательского альбома).
     * @param int $user_flag    Флаг, указывающий формировать ли информацию о категории (`0`) или пользовательском
     *                          альбоме (`1`):
     *                          - По умолчанию: `0`.
     *                          - Допустимые значения: `0` или `1`.
     *
     * @return array Информационная строка для категории или пользовательского альбома:
     *               - 'name'           (string): Название категории или альбома.
     *               - 'description'    (string): Описание категории или альбома.
     *               - 'count_photo'    (int):    Количество фотографий.
     *               - 'last_photo'     (string): Форматированное название последней фотографии (например, "Название
     *               (Описание)").
     *               - 'top_photo'      (string): Форматированное название лучшей фотографии (например, "Название
     *               (Описание)").
     *               - 'url_cat'        (string): Ссылка на категорию или альбом.
     *               - 'url_last_photo' (string): Ссылка на последнюю фотографию.
     *               - 'url_top_photo'  (string): Ссылка на лучшую фотографию.
     *
     * @throws InvalidArgumentException Если входные параметры имеют некорректный тип или значение.
     *                                  Пример сообщения:
     *                                      cat_id и user_flag должны быть 0 или положительным целым числом.
     * @throws PDOException            Если возникают ошибки при получении данных из базы данных.
     *                                  Пример сообщения:
     *                                      Не удалось получить данные категории или пользователя.
     * @throws Exception               При выполнении запросов к базам данных.
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
     * Пример использования:
     * @code
     * // Получение данных о категории с ID = 5
     * $category_data = $work->category(5, 0);
     * print_r($category_data);
     *
     * // Получение данных о пользовательском альбоме с ID = 123
     * $user_album_data = $work->category(123, 1);
     * print_r($user_album_data);
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::category()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function category(int $cat_id = 0, int $user_flag = 0): array
    {
        return $this->core_logic->category($cat_id, $user_flag);
    }    /**
     * @brief   Транслитерация строки и замена знаков пунктуации на "_" через вызов публичного метода в дочернем
     *          классе.
     *
     * @details Этот статический метод является фасадом для вызова публичного метода encodename()
     *          в дочернем классе Work_Helper. Он выполняет транслитерацию не латинских символов в латиницу и заменяет
     *          все символы, кроме букв и цифр, на `"_"`.
     *
     * @param string $string Исходная строка:
     *                       - Если строка пустая, она возвращается без обработки.
     *                       - Рекомендуется использовать строки в кодировке UTF-8.
     *
     * @return string Строка после транслитерации и замены символов:
     *                - Если после обработки строка становится пустой, генерируется уникальная последовательность.
     *
     * @warning Если расширение `intl` недоступно, используется резервная таблица транслитерации.
     *          Метод не гарантирует сохранение исходного формата строки, так как все специальные символы заменяются на
     *          `"_"`.
     *
     * Пример использования:
     * @code
     * // Транслитерация строки с заменой знаков пунктуации
     * $encoded = \PhotoRigma\Classes\Work::encodename('Привет, мир!');
     * echo $encoded; // Выведет: Privet_mir
     *
     * // Обработка пустой строки
     * $encoded = \PhotoRigma\Classes\Work::encodename('');
     * echo $encoded; // Выведет: пустую строку
     *
     * // Обработка строки без кириллицы и знаков пунктуации
     * $encoded = \PhotoRigma\Classes\Work::encodename('12345');
     * echo $encoded; // Выведет: 12345
     *
     * // Генерация уникальной последовательности для пустой строки после обработки
     * $encoded = \PhotoRigma\Classes\Work::encodename('!!!');
     * echo $encoded; // Выведет: уникальную последовательность из 16 символов
     * @endcode
     * @see     PhotoRigma::Classes::Work_Helper::encodename()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public static function encodename(string $string): string
    {
        return Work_Helper::encodename($string);
    }

    /**
     * @brief   Проверяет содержимое поля на соответствие условиям через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода check_field()
     *          в классе Work_Security. Он выполняет проверку значения поля на соответствие регулярному выражению,
     *          условию "не ноль" ($not_zero) и отсутствие запрещённых паттернов из свойства
     *          PhotoRigma::Classes::Work_Security::$compiled_rules.
     *
     * @param string       $field      Значение поля для проверки:
     *                                 - Указывается строковое значение, которое необходимо проверить.
     * @param string|false $regexp     Регулярное выражение (необязательно):
     *                                 - Если задано, значение должно соответствовать этому выражению.
     *                                 - Если регулярное выражение некорректно (например, содержит ошибки компиляции),
     *                                 метод завершает выполнение с ошибкой.
     * @param bool         $not_zero   Флаг, указывающий, что значение не должно быть числом 0:
     *                                 - Если флаг установлен, а значение равно '0', проверка завершается с ошибкой.
     *
     * @return bool True, если поле прошло все проверки, иначе False.
     *              Проверки включают соответствие регулярному выражению, отсутствие запрещённых паттернов
     *              и выполнение условия $not_zero (если оно задано).
     *
     * @throws Exception При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @note    Метод использует свойство PhotoRigma::Classes::Work_Security::$compiled_rules
     *          для проверки на наличие запрещённых паттернов.
     *          Свойство должно содержать массив скомпилированных регулярных выражений.
     *
     * @warning Метод зависит от корректности данных в свойстве PhotoRigma::Classes::Work_Security::$compiled_rules.
     *          Если правила некорректны, результат может быть непредсказуемым.
     *          Также важно убедиться, что регулярное выражение ($regexp) корректно перед использованием.
     *
     * Пример использования:
     * @code
     * // Проверка поля на соответствие регулярному выражению и условию not_zero
     * $field_value = "example123";
     * $regexp = "/^[a-z0-9]+$/i";
     * $is_valid = $work->check_field($field_value, $regexp, true);
     * if ($is_valid) {
     *     echo "Поле прошло проверку.";
     * } else {
     *     echo "Поле не прошло проверку.";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_Security::check_field()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function check_field(string $field, string|false $regexp = false, bool $not_zero = false): bool
    {
        return $this->security->check_field($field, $regexp, $not_zero);
    }

    /**
     * @brief   Проверяет входные данные через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода check_input()
     *          в классе Work_Security. Он выполняет проверку данных из различных источников ($_GET, $_POST, $_SESSION,
     *          $_COOKIE, $_FILES) на соответствие заданным условиям.
     *
     * @param string $source_name   Источник данных ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES):
     *                              - Должен быть одним из допустимых значений: '_GET', '_POST', '_SESSION', '_COOKIE',
     *                              '_FILES'.
     * @param string $field         Поле для проверки (имя ключа в массиве источника данных):
     *                              - Указывается имя ключа, которое необходимо проверить.
     * @param array  $options       Дополнительные параметры проверки:
     *                              - Ключ 'isset' (bool, опционально): Проверять наличие поля в источнике данных.
     *                              - Ключ 'empty' (bool, опционально): Проверять, что значение поля не пустое.
     *                              - Ключ 'regexp' (string|false, опционально): Регулярное выражение для проверки
     *                              значения поля.
     *                              - Ключ 'not_zero' (bool, опционально): Проверять, что значение поля не равно нулю.
     *                              - Ключ 'max_size' (int, опционально): Максимальный размер файла (в байтах) для
     *                              $_FILES.
     *
     * @return bool True, если данные прошли проверку, иначе False.
     *              Для $_FILES также учитывается корректность MIME-типа и размера файла.
     *
     * @throws RuntimeException Если MIME-тип загруженного файла не поддерживается.
     * @throws Exception При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @note    Для источника данных `_SESSION` используется свойство класса
     *          PhotoRigma::Classes::Work_Security::$session. Метод зависит от корректности данных в источниках ($_GET,
     *          $_POST, $_SESSION, $_COOKIE, $_FILES). Если источник данных повреждён или содержит некорректные
     *          значения, результат может быть непредсказуемым.
     *
     * @warning Убедитесь, что переданные параметры проверки ($options) корректны и соответствуют требованиям.
     *
     * Пример использования:
     * @code
     * // Проверка поля $_POST['username'] на наличие и непустое значение
     * $is_valid = $work->check_input('_POST', 'username', [
     *     'isset' => true,
     *     'empty' => true,
     * ]);
     * if ($is_valid) {
     *     echo "Поле username прошло проверку.";
     * } else {
     *     echo "Поле username не прошло проверку.";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_Security::check_input()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function check_input(string $source_name, string $field, array $options = []): bool
    {
        return $this->security->check_input($source_name, $field, $options);
    }    /**
     * @brief   Преобразует BBCode в HTML через вызов публичного метода в дочернем классе.
     *
     * @details Этот статический метод является фасадом для вызова публичного метода ubb()
     *          в дочернем классе Work_Helper. Он преобразует BBCode-теги в соответствующие HTML-теги,
     *          учитывая рекурсивную обработку вложенных тегов.
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
     * Пример использования:
     * @code
     * // Преобразование жирного текста
     * $html = \PhotoRigma\Classes\Work::ubb('[b]Bold text[/b]');
     * echo $html; // Выведет: <strong>Bold text</strong>
     *
     * // Преобразование ссылки
     * $html = \PhotoRigma\Classes\Work::ubb('[url=https://example.com]Example[/url]');
     * echo $html; // Выведет: <a href="https://example.com" target="_blank" rel="noopener noreferrer"
     * title="Example">Example</a>
     *
     * // Преобразование цитаты
     * $html = \PhotoRigma\Classes\Work::ubb('[quote=Author]This is a quote.[/quote]');
     * echo $html; // Выведет: <blockquote><cite>Author:</cite>This is a quote.</blockquote>
     *
     * // Преобразование списка
     * $html = \PhotoRigma\Classes\Work::ubb('[list][*]Item 1[*]Item 2[/list]');
     * echo $html; // Выведет: <ul><li>Item 1</li><li>Item 2</li></ul>
     * @endcode
     * @see     PhotoRigma::Classes::Work_Helper::ubb()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public static function ubb(string $text): string
    {
        return Work_Helper::ubb($text);
    }



    /**
     * @brief   Разбивает строку на несколько строк ограниченной длины через вызов публичного метода в дочернем классе.
     *
     * @details Этот статический метод является фасадом для вызова публичного метода utf8_wordwrap()
     *          в дочернем классе Work_Helper. Он разбивает строку на несколько строк, каждая из которых имеет длину не
     *          более указанной. Разрыв строки выполняется только по пробелам, чтобы сохранить читаемость текста.
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
     * Пример использования:
     * @code
     * // Разбивка строки на части длиной 10 символов
     * $wrapped = \PhotoRigma\Classes\Work::utf8_wordwrap('This is a very long string that needs to be wrapped.', 10);
     * echo $wrapped;
     * // Выведет:
     * // This is a
     * // very long
     * // string that
     * // needs to be
     * // wrapped.
     *
     * // Разбивка строки с пользовательским символом разрыва
     * $wrapped = \PhotoRigma\Classes\Work::utf8_wordwrap('This is another example.', 15, '---');
     * echo $wrapped;
     * // Выведет:
     * // This is another---
     * // example.
     *
     * // Некорректные параметры
     * $wrapped = \PhotoRigma\Classes\Work::utf8_wordwrap('Short text', 0);
     * echo $wrapped; // Выведет: Short text
     * @endcode
     * @see     PhotoRigma::Classes::Work_Helper::utf8_wordwrap()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public static function utf8_wordwrap(string $str, int $width = 70, string $break = PHP_EOL): string
    {
        return Work_Helper::utf8_wordwrap($str, $width, $break);
    }



    /**
     * @brief   Проверяет MIME-тип файла через вызов публичного метода в дочернем классе.
     *
     * @details Этот статический метод является фасадом для вызова публичного метода validate_mime_type()
     *          в дочернем классе Work_Helper. Он проверяет, поддерживается ли указанный MIME-тип хотя бы одной из
     *          доступных библиотек: Imagick, Gmagick или встроенных функций PHP (GD).
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
     * Пример использования:
     * @code
     * // Проверка поддерживаемого MIME-типа
     * $is_supported = \PhotoRigma\Classes\Work::validate_mime_type('image/jpeg');
     * var_dump($is_supported); // Выведет: true
     *
     * // Проверка неподдерживаемого MIME-типа
     * $is_supported = \PhotoRigma\Classes\Work::validate_mime_type('application/pdf');
     * var_dump($is_supported); // Выведет: false
     *
     * // Проверка MIME-типа, поддерживаемого только через Imagick
     * $is_supported = \PhotoRigma\Classes\Work::validate_mime_type('image/vnd.adobe.photoshop');
     * var_dump($is_supported); // Выведет: true (если Imagick доступен)
     * @endcode
     * @see     PhotoRigma::Classes::Work_Helper::validate_mime_type()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public static function validate_mime_type(string $real_mime_type): bool
    {
        return Work_Helper::validate_mime_type($real_mime_type);
    }



    /**
     * @brief   Устанавливает языковые данные через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _set_lang_internal().
     *          Он передаёт имя файла языковых данных в защищённый метод, который выполняет проверку существования
     *          файла, загрузку данных и их обработку. Метод предназначен для использования клиентским кодом как точка
     *          входа для установки языковых данных.
     *
     * @param string $lang Имя файла языковых данных:
     *                     - Должен быть непустой строкой.
     *                     - Должен соответствовать имени файла в директории языковых данных.
     *                     - По умолчанию: 'main'.
     *
     * @throws InvalidArgumentException Если имя файла некорректно (пустое или не существует).
     *                                  Пример сообщения: "Некорректное имя языкового файла | Поле не должно быть
     *                                  пустым". Если массив языковых данных некорректен (не содержит ключ 'lang' или
     *                                  содержит ошибки). Пример сообщения: "Обнаружены ошибки в массиве языковых
     *                                  данных | Ошибки:
     *                                  [список ошибок]".
     * @throws RuntimeException         Если файл языковых данных недоступен для чтения.
     *                                  Пример сообщения: "Файл языковых данных недоступен для чтения | Путь: [путь к
     *                                  файлу]".
     * @throws JsonException            При ошибках кодирования JSON.
     * @throws Exception                При ошибках логирования через log_in_file().
     *
     * @note    Этот метод является точкой входа для установки языковых данных. Все проверки и обработка выполняются в
     *          защищённом методе _set_lang_internal().
     *
     * @warning Если файл языковых данных отсутствует или содержит ошибки, метод выбрасывает исключение.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $work = new \PhotoRigma\Classes\Work();
     * $work->set_lang('main');
     * @endcode
     * @see     PhotoRigma::Classes::Work::_set_lang_internal()
     *          Защищённый метод, который выполняет основную логику установки языковых данных.
     */
    public function set_lang(string $lang = 'main'): void
    {
        $this->_set_lang_internal($lang);
    }

    /**
     * @brief   Устанавливает языковые данные из файла и передаёт их в дочерние классы.
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Проверяет, что имя файла языковых данных ($lang) не пустое.
     *             Если параметр пустой, выбрасывается исключение InvalidArgumentException.
     *          2. Формирует путь к файлу языковых данных на основе текущего языка сессии или конфигурации:
     *             - Директория языковых данных: $this->config['site_dir'] . '/language/'.
     *             - Файл: [текущий язык]/[имя файла].php.
     *          3. Проверяет существование файла с помощью is_file().
     *             Если файл не найден, выбрасывается исключение InvalidArgumentException.
     *          4. Проверяет доступность файла для чтения с помощью is_readable().
     *             Если файл недоступен, выбрасывается исключение RuntimeException.
     *          5. Загружает данные из файла с помощью include().
     *             Файл должен возвращать массив с ключом 'lang'. Если массив некорректен, выбрасывается исключение.
     *          6. Проверяет корректность массива через метод process_lang_array():
     *             - Если обнаружены ошибки, выбрасывается исключение InvalidArgumentException.
     *             - Если есть изменения, они логируются через log_in_file().
     *          7. Обновляет языковые данные в текущем классе ($this->lang) и передаёт их в дочерние классы:
     *             - Work_Template::$lang.
     *             - Work_CoreLogic::$lang.
     *          Метод является защищённым и предназначен для использования внутри класса или его наследников.
     *
     * @param string $lang Имя файла языковых данных:
     *                     - Должен быть непустой строкой.
     *                     - Должен соответствовать имени файла в директории языковых данных.
     *                     - По умолчанию: 'main'.
     *
     * @throws InvalidArgumentException Если имя файла некорректно (пустое или не существует).
     *                                  Пример сообщения: "Некорректное имя языкового файла | Поле не должно быть
     *                                  пустым". Если массив языковых данных некорректен (не содержит ключ 'lang' или
     *                                  содержит ошибки). Пример сообщения: "Обнаружены ошибки в массиве языковых
     *                                  данных | Ошибки:
     *                                  [список ошибок]".
     * @throws RuntimeException         Если файл языковых данных недоступен для чтения.
     *                                  Пример сообщения: "Файл языковых данных недоступен для чтения | Путь: [путь к
     *                                  файлу]".
     * @throws JsonException            При ошибках кодирования JSON.
     * @throws Exception                При ошибках логирования через log_in_file().
     *
     * @todo    Интегрировать в систему кеширования языковых переменных.
     *
     * @note    Файл языковых данных должен быть доступен для чтения.
     *          Массив языковых данных должен содержать ключ 'lang'.
     *
     * @warning Если файл языковых данных отсутствует или содержит ошибки, метод выбрасывает исключение.
     *
     * Пример использования метода:
     * @code
     * // Вызов метода внутри класса или его наследника
     * $this->_set_lang_internal('main');
     * @endcode
     * @see     PhotoRigma::Include::log_in_file()
     *          Внешняя функция для логирования ошибок.
     * @see     PhotoRigma::Classes::Work::process_lang_array()
     *          Метод для проверки корректности массива языковых данных.
     * @see     PhotoRigma::Classes::Work::$lang
     *          Свойство класса Work, которое изменяется.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$lang
     *          Свойство дочернего класса Work_CoreLogic.
     * @see     PhotoRigma::Classes::Work_Template::$lang
     *          Свойство дочернего класса Work_Template.
     */
    protected function _set_lang_internal(string $lang = 'main'): void
    {
        if (empty($lang)) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Некорректное имя языкового файла | Поле не должно быть пустым"
            );
        }

        // Устанавливаем язык из сессии или конфигурации
        $this->session['language'] ??= $this->config['language'];

        // Формируем путь к файлу языковых данных
        $lang_file_path = $this->config['site_dir'] . '/language/' . $this->session['language'] . '/' . $lang . '.php';

        // Проверяем существование файла
        if (!is_file($lang_file_path)) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Файл языковых данных не найден | Путь: $lang_file_path"
            );
        }

        // Проверяем доступность файла для чтения
        if (!is_readable($lang_file_path)) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Файл языковых данных недоступен для чтения | Путь: $lang_file_path"
            );
        }

        // Загружаем данные из файла
        $data = include($lang_file_path);
        if (!is_array($data) || !isset($data['lang'])) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Файл языковых данных не возвращает массив с ключом 'lang' | Путь: $lang_file_path"
            );
        }

        // Извлекаем данные
        $lang_data = $data['lang'];

        // Проверяем массив на корректность
        $result = $this->process_lang_array($lang_data);
        if (!empty($result['errors'])) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Обнаружены ошибки в массиве языковых данных | Ошибки: " . json_encode(
                    $result['errors'],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                )
            );
        }

        // Логируем изменения, если они есть
        if (!empty($result['changes'])) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Очищенные значения | Значения: " . json_encode(
                    $result['changes'],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                )
            );
        }

        // Обновляем языковые данные
        $this->lang = array_merge($this->lang ?? [], $result['result']);

        // Передаём языковые данные в свойство и подклассы
        $this->template->set_lang($this->lang);
        $this->core_logic->set_lang($this->lang);
    }

    /**
     * @brief   Обрабатывает массив языковых переменных, проверяя его структуру и очищая значения. Возвращает массив с
     *          информацией об ошибках, изменениях и обработанном массиве.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет, что ключи первого уровня массива являются строками.
     *          2. Проверяет, что значения первого уровня являются массивами.
     *          3. Проверяет, что ключи второго уровня являются строками:
     *             - Если ключ не является строкой, он преобразуется в строку.
     *             - Старый ключ удаляется, а новый добавляется в массив.
     *          4. Проверяет, что значения второго уровня являются строками.
     *          5. Очищает значения второго уровня через метод `clean_field()` для защиты от XSS:
     *             - Если значение было изменено при очистке, сохраняется информация об оригинальном и очищенном
     *             значениях.
     *          6. Возвращает массив с результатами обработки:
     *             - `'errors'`: Массив ошибок, если они есть (например, некорректные ключи или значения).
     *             - `'changes'`: Массив изменений, если значения были очищены (содержит оригинальные и очищенные
     *             значения).
     *             - `'result'`: Обработанный массив языковых переменных.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
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
     *               очищенные значения).
     *               - `'result'` (array): Обработанный массив языковых переменных.
     *
     * @note    Используется метод `clean_field()` для очистки значений (защита от XSS).
     *
     * @warning Метод чувствителен к структуре входного массива. Некорректная структура может привести к ошибкам.
     *
     * Пример вызова метода внутри класса
     * @code
     * $result = $this->process_lang_array([
     *     'greeting' => [
     *         'hello' => 'Hello, World!',
     *         'goodbye' => 'Goodbye, World!',
     *     ],
     * ]);
     * print_r($result);
     * @endcode
     * @see     PhotoRigma::Classes::Work::clean_field() Метод для очистки и экранирования полей (защита от XSS).
     */
    private function process_lang_array(array $lang): array
    {
        $result = [
            'errors'  => [],
            'changes' => [],
            'result'  => $lang, // Инициализируем результат исходным массивом
        ];

        foreach ($lang as $name => $keys) {
            // Проверка ключей первого уровня
            if (!is_string($name)) {
                $result['errors'][] = "Ключ первого уровня должен быть строкой. Получено: " . gettype($name);
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
     * @brief   Очищает строку от HTML-тегов и специальных символов через вызов публичного метода в дочернем классе.
     *
     * @details Этот статический метод является фасадом для вызова публичного метода clean_field()
     *          в дочернем классе Work_Helper. Он выполняет очистку строки от HTML-тегов и экранирует специальные
     *          символы для защиты от XSS-атак и других проблем, связанных с некорректными данными.
     *
     * @param string $field Строка или данные, которые могут быть преобразованы в строку:
     *                      - Если входные данные пусты (`null` или пустая строка), метод вернёт пустую строку (`''`).
     *
     * @return string Очищенная строка или пустая строка (`''`), если входные данные пусты.
     *
     * @warning Метод не обрабатывает вложенные структуры данных (например, массивы).
     *          Убедитесь, что входные данные могут быть преобразованы в строку.
     *
     * Пример использования:
     * @code
     * // Очистка строки от HTML-тегов и специальных символов
     * $dirty_input = '<script>alert("XSS")</script>';
     * $cleaned = \PhotoRigma\Classes\Work::clean_field($dirty_input);
     * echo $cleaned; // Выведет: <script>alert(&quot;XSS&quot;)</script>
     * @endcode
     * @see     PhotoRigma::Classes::Work_Helper::clean_field()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public static function clean_field(string $field): string
    {
        return Work_Helper::clean_field($field);
    }

    /**
     * @brief   Установка объекта пользователя через сеттер.
     *
     * @details Этот метод позволяет установить объект пользователя, реализующий интерфейс `User_Interface`.
     *          Метод выполняет следующие действия:
     *          1. Проверяет, что переданный объект является экземпляром класса, реализующего интерфейс
     *          `User_Interface`.
     *          2. Передаёт объект пользователя в связанные компоненты системы (`Work_Template` и `Work_CoreLogic`)
     *             для дальнейшего использования.
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
     * Пример использования:
     * @code
     * // Создание объекта Work и установка пользователя
     * $work = new \PhotoRigma\Classes\Work();
     * $user = new \PhotoRigma\Classes\User($db, $_SESSION);
     * $work->set_user($user);
     * @endcode
     * @see     PhotoRigma::Classes::User_Interface
     *          Интерфейс, которому должен соответствовать объект пользователя.
     * @see     PhotoRigma::Classes::Work_Template
     *          Класс, использующий объект пользователя.
     * @see     PhotoRigma::Classes::Work_CoreLogic
     *          Класс, использующий объект пользователя.
     */
    public function set_user(User_Interface $user): void
    {
        $this->template->set_user($user);
        $this->core_logic->set_user($user);
    }

    /**
     * @brief   Проверяет URL на наличие вредоносного кода через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода url_check()
     *          в классе Work_Security. Он проверяет строку запроса из глобального массива `$_SERVER['QUERY_STRING']`
     *          на наличие запрещённых паттернов, определённых в массиве
     *          PhotoRigma::Classes::Work_Security::$compiled_rules.
     *
     * @return bool True, если URL безопасен (не содержит запрещённых паттернов), иначе False.
     *
     * @throws Exception При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @note    Метод работает с глобальным массивом `$_SERVER['QUERY_STRING']`.
     *          Убедитесь, что этот массив доступен и содержит корректные данные.
     *
     * @warning Метод зависит от корректности данных в свойстве PhotoRigma::Classes::Work_Security::$compiled_rules.
     *          Если правила некорректны, результат может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Проверка безопасности URL
     * $is_safe = $work->url_check();
     * if ($is_safe) {
     *     echo "URL безопасен.";
     * } else {
     *     echo "URL содержит запрещённые паттерны.";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_Security::url_check()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function url_check(): bool
    {
        return $this->security->url_check();
    }

    /**
     * @brief   Генерирует математический CAPTCHA-вопрос и ответ через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода gen_captcha()
     *          в классе Work_Security. Он генерирует случайное математическое выражение и вычисляет его результат
     *          для защиты от спам-ботов.
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
     * Пример использования:
     * @code
     * // Генерация CAPTCHA-вопроса и ответа
     * $captcha = $work->gen_captcha();
     * echo "Вопрос: {$captcha['question']}\n";
     * echo "Ответ: {$captcha['answer']}\n";
     * @endcode
     * @see     PhotoRigma::Classes::Work_Security::gen_captcha()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function gen_captcha(): array
    {
        return $this->security->gen_captcha();
    }

    /**
     * @brief   Заменяет символы в email-адресах через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода filt_email()
     *          в классе Work_Security. Он проверяет корректность формата email и заменяет символы '@' и '.' на '[at]'
     *          и '[dot]' соответственно для затруднения автоматического парсинга email-адресов ботами.
     *
     * @param string $email Email-адрес для обработки:
     *                      - Должен быть непустым и соответствовать формату email (например, "example@example.com").
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
     * Пример использования:
     * @code
     * // Обработка email-адреса
     * $email = "example@example.com";
     * $filtered_email = $work->filt_email($email);
     * if (!empty($filtered_email)) {
     *     echo "Обработанный email: {$filtered_email}";
     * } else {
     *     echo "Email некорректен или пуст.";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_Security::filt_email()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function filt_email(string $email): string
    {
        return $this->security->filt_email($email);
    }

    /**
     * @brief   Удаляет изображение с указанным идентификатором через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода del_photo()
     *          в классе Work_CoreLogic. Он выполняет удаление изображения с указанным идентификатором, а также все
     *          упоминания об этом изображении в таблицах сайта.
     *
     * @param int $photo_id Идентификатор удаляемого изображения:
     *                      - Должен быть положительным целым числом.
     *
     * @return bool True, если удаление успешно, иначе False.
     *
     * @throws InvalidArgumentException Если параметр photo_id имеет некорректный тип или значение.
     *                                  Пример сообщения:
     *                                      Неверное значение параметра photo_id | Ожидалось положительное целое число.
     * @throws RuntimeException         Если возникает ошибка при выполнении запросов к базе данных или удалении
     *                                  файлов.
     *                                  Пример сообщения:
     *                                      Не удалось найти изображение | Переменная photo_id = [значение].
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
     * Пример использования:
     * @code
     * // Удаление изображения с ID = 42
     * $result = $work->del_photo(42);
     * if ($result) {
     *     echo "Изображение успешно удалено.";
     * } else {
     *     echo "Не удалось удалить изображение.";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::del_photo()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function del_photo(int $photo_id): bool
    {
        return $this->core_logic->del_photo($photo_id);
    }

    /**
     * @brief   Получает данные о новостях в зависимости от типа запроса через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода news()
     *          в классе Work_CoreLogic. Он выполняет запросы к базе данных для получения данных о новостях:
     *          - Для `$act = 'id'`: Возвращает новость по её ID.
     *          - Для `$act = 'last'`: Возвращает список новостей с сортировкой по дате последнего редактирования.
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
     *                                  Пример сообщения:
     *                                      Некорректный ID новости | Переменная $news_id_or_limit = [значение].
     *                                  Пример сообщения:
     *                                      Некорректное количество новостей | Переменная $news_id_or_limit =
     *                                      [значение]. Пример сообщения: Некорректный тип запроса | Переменная $act =
     *                                      '$act'.
     * @throws RuntimeException         Если произошла ошибка при выполнении запроса к базе данных.
     *                                  Пример сообщения:
     *                                      Не удалось получить данные из базы данных | Тип запроса: '$act'.
     * @throws Exception                При выполнении запросов к базам данных.
     *
     * @note    Используются константы:
     *          - TBL_NEWS: Таблица для хранения данных о новостях (`news`).
     *
     * @warning Метод чувствителен к корректности входных параметров `$news_id_or_limit` и `$act`.
     *          Убедитесь, что передаются допустимые значения.
     *          Если новости не найдены, метод возвращает пустой массив.
     *
     * Пример использования:
     * @code
     * // Получение новости с ID = 5
     * $news_by_id = $work->news(5, 'id');
     * print_r($news_by_id);
     *
     * // Получение 10 последних новостей
     * $news_list = $work->news(10, 'last');
     * print_r($news_list);
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::news()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function news(int $news_id_or_limit, string $act): array
    {
        return $this->core_logic->news($news_id_or_limit, $act);
    }

    /**
     * @brief   Загружает доступные языки из директории /language/ через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода get_languages()
     *          в классе Work_CoreLogic. Он выполняет загрузку доступных языков, проверяя поддиректории в `/language/`
     *          и безопасно подключая файлы `main.php` для получения названий языков.
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
     * @warning Если директория `/language/` пуста или содержит недоступные поддиректории, метод выбрасывает исключение.
     *
     * Пример использования:
     * @code
     * // Получение списка доступных языков
     * $languages = $work->get_languages();
     * foreach ($languages as $language) {
     *     echo "Язык: " . $language['name'] . " (ID: " . $language['value'] . ")\n";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::get_languages()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function get_languages(): array
    {
        return $this->core_logic->get_languages();
    }

    /**
     * @brief   Загружает доступные темы из директории /themes/ через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода get_themes()
     *          в классе Work_CoreLogic. Он выполняет загрузку доступных тем, проверяя поддиректории в `/themes/`
     *          и добавляя их в список доступных тем.
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
     * Пример использования:
     * @code
     * // Получение списка доступных тем
     * $themes = $work->get_themes();
     * foreach ($themes as $theme) {
     *     echo "Доступная тема: $theme\n";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::get_themes()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function get_themes(): array
    {
        return $this->core_logic->get_themes();
    }

    /**
     * @brief   Генерирует блок данных для вывода изображений различных типов через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода create_photo()
     *          в классе Work_CoreLogic. Он генерирует данные для вывода изображений различных типов:
     *          - `'top'`: Лучшее изображение (по рейтингу).
     *          - `'last'`: Последнее загруженное изображение.
     *          - `'cat'`: Изображение из конкретной категории (требует указания `$id_photo`).
     *          - `'rand'`: Любое случайное изображение.
     *
     * @param string $type     Тип изображения:
     *                         - `'top'`: Лучшее изображение (по рейтингу).
     *                         - `'last'`: Последнее загруженное изображение.
     *                         - `'cat'`: Изображение из конкретной категории (требует указания `$id_photo`).
     *                         - `'rand'`: Любое случайное изображение.
     *                         По умолчанию: `'top'`.
     *                         Допустимые значения: `'top'`, `'last'`, `'cat'`, `'rand'`.
     * @param int    $id_photo Идентификатор фото. Используется только при `$type == 'cat'`.
     *                         Должен быть целым числом >= `0`.
     *                         По умолчанию: `0`.
     *
     * @return array Массив данных для вывода изображения:
     *               - `'name_block'`         (string): Название блока изображения (например, "Лучшее фото").
     *               - `'url'`                (string): URL для просмотра полного изображения.
     *               - `'thumbnail_url'`      (string): URL для миниатюры изображения.
     *               - `'name'`               (string): Название изображения.
     *               - `'description'`        (string): Описание изображения.
     *               - `'category_name'`      (string): Название категории.
     *               - `'category_description'` (string): Описание категории.
     *               - `'rate'`               (string): Рейтинг изображения (например, "Рейтинг: 5/10").
     *               - `'url_user'`           (string|null): URL профиля пользователя, добавившего изображение.
     *               - `'real_name'`          (string): Реальное имя пользователя.
     *               - `'category_url'`       (string): URL категории или пользовательского альбома.
     *               - `'width'`              (int): Ширина изображения после масштабирования.
     *               - `'height'`             (int): Высота изображения после масштабирования.
     *
     * @throws InvalidArgumentException Если передан недопустимый `$type` или `$id_photo < 0`.
     *                                  Пример сообщения:
     *                                      Некорректный идентификатор фотографии | Значение: {$id_photo}.
     * @throws PDOException            Если произошла ошибка при выборке данных из базы данных.
     *                                  Пример сообщения:
     *                                      Не удалось получить данные категории с ID: {$photo_data['category']}.
     * @throws RuntimeException         Если файл изображения недоступен или не существует.
     * @throws Exception                При записи ошибок в лог через `log_in_file()`.
     *
     * @note    Используются следующие константы:
     *       - TBL_CATEGORY: Таблица для хранения данных о категориях (`category`).
     *       - TBL_PHOTO: Таблица для хранения данных об изображениях (`photo`).
     *       - TBL_USERS: Таблица для хранения данных о пользователях (`users`).
     *       - VIEW_RANDOM_PHOTO: Представление для выбора случайных изображений (`random_photo`).
     *
     * @warning Метод чувствителен к правам доступа пользователя.
     *          Убедитесь, что пользователь имеет право на просмотр изображений.
     *          Если файл изображения недоступен или не существует, метод возвращает данные по умолчанию.
     *          Проверка пути к файлу изображения гарантирует, что доступ возможен только к файлам внутри
     *          директории, указанной в конфигурации.
     *
     * Пример использования:
     * @code
     * // Получение данных для вывода лучшего изображения
     * $top_photo = $work->create_photo('top', 0);
     * print_r($top_photo);
     *
     * // Получение данных для вывода изображения из категории с ID = 5
     * $category_photo = $work->create_photo('cat', 5);
     * print_r($category_photo);
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::create_photo()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function create_photo(string $type = 'top', int $id_photo = 0): array
    {
        return $this->core_logic->create_photo($type, $id_photo);
    }

    /**
     * @brief   Добавляет новую оценку в таблицу и возвращает среднюю оценку через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода process_rating()
     *          в классе Work_CoreLogic. Он выполняет добавление новой оценки в указанную таблицу,
     *          проверяет успешность вставки и возвращает среднюю оценку для фотографии.
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
     * @throws RuntimeException Выбрасывается исключение, если не удалось добавить оценку.
     *                           Причина: `get_last_insert_id()` возвращает `0`, что указывает на неудачную вставку.
     * @throws Exception        При выполнении запросов к базам данных.
     *
     * @note    Используются константы:
     *          - TBL_PHOTO: Таблица для хранения данных о фотографиях (`photo`).
     *
     * @warning Убедитесь, что:
     *          - Параметр `$table` соответствует одной из допустимых таблиц (`'rate_user'` или `'rate_moder'`).
     *          - В СУБД настроены триггеры и функции для перерасчета средней оценки.
     *
     * Пример использования:
     * @code
     * // Добавление оценки и получение средней оценки
     * $averageRate = $work->process_rating('rate_user', 123, 456, 5);
     * echo "Средняя оценка: {$averageRate}";
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::process_rating()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function process_rating(string $table, int $photo_id, int $user_id, int $rate_value): float
    {
        return $this->core_logic->process_rating($table, $photo_id, $user_id, $rate_value);
    }

    /**
     * @brief   Формирует массив данных для меню через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода create_menu()
     *          в классе Work_Template. Он формирует массив данных для меню в зависимости от типа и активного пункта,
     *          выполняя запросы к базе данных и проверяя права доступа пользователя.
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
     * Пример использования:
     * @code
     * // Создание горизонтального меню
     * $short_menu = $work->create_menu('home', SHORT_MENU);
     * print_r($short_menu);
     *
     * // Создание вертикального меню
     * $long_menu = $work->create_menu('profile', LONG_MENU);
     * print_r($long_menu);
     * @endcode
     * @see     PhotoRigma::Classes::Work_Template::create_menu()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function create_menu(string $action, int $menu): array
    {
        return $this->template->create_menu($action, $menu);
    }

    /**
     * @brief   Формирует блок пользователя через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода template_user()
     *          в классе Work_Template. Он формирует массив данных для блока пользователя, который используется
     *          для отображения в шаблоне. Блок зависит от статуса авторизации пользователя:
     *          - Для неавторизованных пользователей: ссылки на вход, восстановление пароля и регистрацию.
     *          - Для авторизованных пользователей: приветствие, группа и аватар.
     *
     * @return array Массив с данными для блока пользователя:
     *               - Для неавторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока (локализованное).
     *                 - 'CSRF_TOKEN': CSRF-токен для защиты формы.
     *                 - 'L_LOGIN', 'L_PASSWORD', 'L_ENTER', 'L_FORGOT_PASSWORD', 'L_REGISTRATION': Локализованные
     *                 строки.
     *                 - 'U_LOGIN', 'U_FORGOT_PASSWORD', 'U_REGISTRATION': URL для входа, восстановления пароля и
     *                 регистрации.
     *               - Для авторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока (локализованное).
     *                 - 'L_HI_USER': Приветствие с именем пользователя (локализованное).
     *                 - 'L_GROUP': Группа пользователя (локализованная строка).
     *                 - 'U_AVATAR': URL аватара (или дефолтного аватара, если файл недоступен или некорректен).
     *
     * @throws RuntimeException Если данные пользователя некорректны.
     * @throws Random\RandomException При ошибке генерации CSRF-токена.
     * @throws Exception При ошибках проверки MIME-типа файла или логирования.
     *
     * @note    Константа DEFAULT_AVATAR определяет значение аватара по умолчанию (например, 'no_avatar.jpg').
     *
     * @warning Убедитесь, что объект пользователя корректно установлен перед вызовом метода.
     *          Также убедитесь, что конфигурация аватаров настроена правильно.
     *
     * Пример использования:
     * @code
     * // Получение данных для блока пользователя
     * $array_data = $work->template_user();
     * print_r($array_data);
     * @endcode
     * @see     PhotoRigma::Classes::Work_Template::template_user()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function template_user(): array
    {
        return $this->template->template_user();
    }

    /**
     * @brief   Генерирует массив статистических данных для шаблона через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода template_stat()
     *          в классе Work_Template. Он выполняет запросы к базе данных для получения статистической информации,
     *          включая количество пользователей, фотографий, категорий, оценок и онлайн-пользователей.
     *
     * @return array Ассоциативный массив данных для вывода статистики:
     *               - NAME_BLOCK: Название блока статистики (локализованное).
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
     *               - D_STAT_ONLINE: Список онлайн-пользователей (HTML-ссылки) или сообщение об отсутствии
     *                 онлайн-пользователей.
     *
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных.
     * @throws Exception        При выполнении запросов к базам данных.
     *
     * @note    Используются константы для определения таблиц и представлений:
     *          - TBL_USERS: Таблица пользователей с их правами.
     *          - TBL_CATEGORY: Таблица со списком категорий на сервере.
     *          - TBL_PHOTO: Таблица со списком фотографий на сервере.
     *          - TBL_RATE_USER: Таблица с оценками фотографий от пользователей.
     *          - TBL_RATE_MODER: Таблица с оценками фотографий от модераторов.
     *          - VIEW_USERS_ONLINE: Представление со списком онлайн-пользователей.
     *
     * @warning Убедитесь, что таблицы базы данных (TBL_USERS, TBL_CATEGORY, TBL_PHOTO, TBL_RATE_USER, TBL_RATE_MODER)
     *          и представление VIEW_USERS_ONLINE содержат корректные данные. Ошибки в структуре таблиц могут привести
     *          к некорректной статистике.
     *
     * Пример использования:
     * @code
     * // Получение данных для блока статистики
     * $stat_data = $work->template_stat();
     * print_r($stat_data);
     * @endcode
     * @see     PhotoRigma::Classes::Work_Template::template_stat()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function template_stat(): array
    {
        return $this->template->template_stat();
    }

    /**
     * @brief   Формирует список пользователей, загрузивших наибольшее количество изображений, через вызов внутреннего
     *          метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода template_best_user()
     *          в классе Work_Template. Он выполняет запросы к базе данных для получения списка пользователей,
     *          загрузивших наибольшее количество изображений, и формирует массив данных для вывода в шаблон.
     *
     * @param int $best_user Количество лучших пользователей для вывода:
     *                       - Должно быть положительным целым числом.
     *                       Если передано недопустимое значение, выбрасывается исключение InvalidArgumentException.
     *
     * @return array Массив данных для вывода в шаблон:
     *               - NAME_BLOCK: Название блока (локализованное).
     *               - L_USER_NAME: Подпись для имени пользователя (локализованная строка).
     *               - L_USER_PHOTO: Подпись для количества фотографий (локализованная строка).
     *               - user_url: Ссылка на профиль пользователя (null, если данных нет).
     *               - user_name: Имя пользователя ('---', если данных нет).
     *               - user_photo: Количество загруженных фотографий ('-', если данных нет).
     *
     * @throws InvalidArgumentException Если параметр $best_user не является положительным целым числом.
     * @throws Exception                При выполнении запросов к базам данных.
     *
     * @note    Если запрос к базе данных не возвращает данных, добавляется запись "пустого" пользователя:
     *          - user_url: null.
     *          - user_name: '---'.
     *          - user_photo: '-'.
     *          Константы TBL_USERS и TBL_PHOTO определяют таблицы:
     *          - TBL_USERS: Таблица пользователей с их правами.
     *          - TBL_PHOTO: Таблица со списком фотографий на сервере.
     *
     * @warning Убедитесь, что таблицы базы данных (TBL_USERS и TBL_PHOTO) содержат корректные данные.
     *          Ошибки в структуре таблиц могут привести к некорректному результату.
     *
     * Пример использования:
     * @code
     * // Получение списка 3 лучших пользователей
     * $best_users = $work->template_best_user(3);
     * print_r($best_users);
     * @endcode
     * @see     PhotoRigma::Classes::Work_Template::template_best_user()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function template_best_user(int $best_user = 1): array
    {
        return $this->template->template_best_user($best_user);
    }

    /**
     * @brief   Вычисляет размеры для вывода эскиза изображения через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода size_image()
     *          в классе Work_Image. Он вычисляет ширину и высоту эскиза на основе реальных размеров изображения
     *          и конфигурационных параметров.
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
     * Пример использования:
     * @code
     * // Вычисление размеров эскиза для изображения
     * $path = '/path/to/image.jpg';
     * $sizes = $work->size_image($path);
     * echo "Ширина: {$sizes['width']}, Высота: {$sizes['height']}";
     * @endcode
     * @see     PhotoRigma::Classes::Work_Image::size_image()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function size_image(string $path_image): array
    {
        return $this->image->size_image($path_image);
    }

    /**
     * @brief   Изменяет размер изображения через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода image_resize()
     *          в классе Work_Image. Он изменяет размер исходного изображения и сохраняет результат как эскиз,
     *          проверяя корректность путей, размеры изображения и доступность директорий.
     *
     * @param string $full_path        Путь к исходному изображению:
     *                                 - Путь должен быть абсолютным, соответствовать регулярному выражению
     *                                 `/^[a-zA-Z0-9\/\.\-_]+$/`, и файл должен существовать и быть доступным для
     *                                 чтения.
     * @param string $thumbnail_path   Путь для сохранения эскиза:
     *                                 - Путь должен быть абсолютным, и директория должна быть доступна для записи.
     *
     * @return bool True, если операция выполнена успешно, иначе False.
     *
     * @throws InvalidArgumentException Если пути к файлам некорректны или имеют недопустимый формат.
     * @throws RuntimeException Если возникли ошибки при проверке файлов, директорий или размеров изображения.
     * @throws Exception При возникновении ошибок логирования информации.
     *
     * @note    Метод использует следующие константы класса Work_Image для ограничения размеров исходного изображения:
     *          - `MAX_IMAGE_WIDTH`: Максимальная ширина исходного изображения (в пикселях). Значение: 5000.
     *          - `MAX_IMAGE_HEIGHT`: Максимальная высота исходного изображения (в пикселях). Значение: 5000.
     *
     * @warning Метод зависит от корректности данных в конфигурации (`temp_photo_w`, `temp_photo_h`).
     *          Если эти параметры некорректны, результат может быть непредсказуемым.
     *          Убедитесь, что пути к файлам и директориям корректны перед вызовом метода.
     *
     * Пример использования:
     * @code
     * // Изменение размера изображения
     * $full_path = '/path/to/source_image.jpg';
     * $thumbnail_path = '/path/to/thumbnail.jpg';
     * $success = $work->image_resize($full_path, $thumbnail_path);
     * if ($success) {
     *     echo "Эскиз успешно создан.";
     * } else {
     *     echo "Ошибка при создании эскиза.";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_Image::image_resize()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function image_resize(string $full_path, string $thumbnail_path): bool
    {
        return $this->image->image_resize($full_path, $thumbnail_path);
    }

    /**
     * @brief   Возвращает данные для отсутствующего изображения через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода no_photo()
     *          в классе Work_Image. Он формирует массив данных, который используется для представления информации
     *          об отсутствующем изображении. Это может быть полезно, например, если изображение не найдено или
     *          недоступно.
     *
     * @return array Массив данных об изображении или его отсутствии:
     *               - `'url'` (string): URL полноразмерного изображения.
     *               - `'thumbnail_url'` (string): URL эскиза изображения.
     *               - `'name'` (string): Название изображения. Значение по умолчанию: `'No photo'`.
     *               - `'description'` (string): Описание изображения. Значение по умолчанию: `'No photo available'`.
     *               - `'category_name'` (string): Название категории. Значение по умолчанию: `'No category'`.
     *               - `'category_description'` (string): Описание категории. Значение по умолчанию: `'No category
     *               available'`.
     *               - `'rate'` (string): Рейтинг изображения. Значение по умолчанию: `'Rate: 0/0'`.
     *               - `'url_user'` (string): URL пользователя. Значение по умолчанию: пустая строка (`''`).
     *               - `'real_name'` (string): Имя пользователя. Значение по умолчанию: `'No user'`.
     *               - `'full_path'` (string): Полный путь к изображению. Формируется на основе конфигурации.
     *               - `'thumbnail_path'` (string): Полный путь к эскизу. Формируется на основе конфигурации.
     *               - `'file'` (string): Имя файла. Значение по умолчанию: `'no_foto.png'`.
     *
     * @warning Метод зависит от корректности данных в конфигурации (`site_url`, `site_dir`, `gallery_folder`,
     *          `thumbnail_folder`). Если эти параметры некорректны, результат может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Получение данных для отсутствующего изображения
     * $noPhotoData = $work->no_photo();
     * echo "URL изображения: {$noPhotoData['url']}\n";
     * echo "Описание: {$noPhotoData['description']}\n";
     * @endcode
     * @see     PhotoRigma::Classes::Work_Image::no_photo()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function no_photo(): array
    {
        return $this->image->no_photo();
    }

    /**
     * @brief   Вывод изображения через HTTP через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода image_attach()
     *          в классе Work_Image. Он проверяет существование файла, определяет его MIME-тип,
     *          отправляет заголовки HTTP и выводит содержимое файла. После завершения отправки
     *          скрипт завершает выполнение.
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
     * Пример использования:
     * @code
     * // Вывод изображения через HTTP
     * $work->image_attach('/path/to/image.jpg', 'example.jpg');
     * @endcode
     * @see     PhotoRigma::Classes::Work_Image::image_attach()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    #[NoReturn] public function image_attach(string $full_path, string $name_file): void
    {
        $this->image->image_attach($full_path, $name_file);
    }

    /**
     * @brief   Корректировка расширения файла в соответствии с его MIME-типом через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода fix_file_extension()
     *          в классе Work_Image. Он проверяет MIME-тип файла и корректирует или добавляет расширение,
     *          соответствующее типу файла.
     *
     * @param string $full_path   Полный путь к файлу:
     *                            - Путь должен быть абсолютным, соответствовать регулярному выражению
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
     * @throws Exception               При возникновении ошибок логирования информации.
     *
     * @warning Метод завершает выполнение с ошибкой, если MIME-тип файла не поддерживается.
     *          Убедитесь, что файл существует и доступен для чтения перед вызовом метода.
     *
     * Пример использования:
     * @code
     * // Корректировка расширения файла
     * $full_path = '/path/to/file_without_extension';
     * $corrected_path = $work->fix_file_extension($full_path);
     * echo "Исправленный путь: {$corrected_path}";
     * @endcode
     * @see     PhotoRigma::Classes::Work_Image::fix_file_extension()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function fix_file_extension(string $full_path): string
    {
        return $this->image->fix_file_extension($full_path);
    }

    /**
     * @brief   Удаляет директорию и её содержимое через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода remove_directory()
     *          в классе Work_Image. Он удаляет указанную директорию и всё её содержимое, предварительно проверяя права
     *          доступа.
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
     * Пример использования:
     * @code
     * // Удаление директории
     * $path = '/path/to/directory';
     * $result = $work->remove_directory($path);
     * if ($result) {
     *     echo "Директория успешно удалена!";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_Image::remove_directory()
     *          Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function remove_directory(string $path): bool
    {
        return $this->image->remove_directory($path);
    }

    /**
     * @brief   Создает директории для категории и копирует файлы через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для вызова внутреннего метода create_directory()
     *          в классе Work_Image. Он создаёт директории для галереи и миниатюр, а также копирует и модифицирует
     *          файлы `index.php` для новой категории.
     *
     * @param string $directory_name Имя директории:
     *                               - Должен быть строкой, содержащей только допустимые символы для имён директорий.
     *                               - Не должен содержать запрещённых символов (например, `\/:*?"<>|`).
     *
     * @return bool Возвращает `true`, если директории успешно созданы и файлы скопированы.
     *
     * @throws RuntimeException Выбрасывается исключение в следующих случаях:
     *                           - Если родительская директория недоступна для записи.
     *                           - Если не удалось создать директории.
     *                           - Если исходные файлы `index.php` не существуют.
     *                           - Если не удалось прочитать или записать файлы `index.php`.
     *
     * @warning Используйте этот метод с осторожностью, так как он создаёт директории и изменяет файлы.
     *
     * Пример использования:
     * @code
     * // Создание директорий для новой категории
     * $directoryName = 'new_category';
     * $result = $work->create_directory($directoryName);
     * if ($result) {
     *     echo "Директории успешно созданы!";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_Image::create_directory()
     *      Публичный метод в дочернем классе, реализующий основную логику.
     */
    public function create_directory(string $directory_name): bool
    {
        return $this->image->create_directory($directory_name);
    }
}
