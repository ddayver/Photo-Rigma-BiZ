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
        date('H:i:s') . " [ERROR] | " . (filter_input(
            INPUT_SERVER,
            'REMOTE_ADDR',
            FILTER_VALIDATE_IP
        ) ?: 'UNKNOWN_IP') . " | " . __FILE__ . " | Попытка прямого вызова файла"
    );
    die("HACK!");
}

use Exception;
use InvalidArgumentException;
use JetBrains\PhpStorm\NoReturn;
use PDOException;
use Random\RandomException;
use RuntimeException;

use function PhotoRigma\Include\log_in_file;

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
 *              $user = new User();
 *              $work->set_user($user);
 * @endcode
 */
class Work
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
     * @throws Exception
     * @see PhotoRigma::Classes::Work::$config Свойство, содержащее конфигурацию.
     * @see PhotoRigma::Classes::Work::$security Свойство, содержащее объект Work_Security.
     * @see PhotoRigma::Classes::Work::$image Свойство, содержащее объект Work_Image.
     * @see PhotoRigma::Classes::Work::$template Свойство, содержащее объект Work_Template.
     * @see PhotoRigma::Classes::Work::$core_logic Свойство, содержащее объект Work_CoreLogic.
     * @see PhotoRigma::Include::log_in_file() Логирует ошибки.
     *
     */
    public function __construct(Database_Interface $db, array $config, array &$session)
    {
        $this->session = &$session;
        $this->config = $config;
        // Загружаем конфигурацию из базы данных
        $db->select(['*'], TBL_CONFIG, ['params' => []]);
        $result = $db->res_arr();
        if (is_array($result)) {
            $this->config = array_merge($this->config, array_column($result, 'value', 'name'));
        } else {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Ошибка загрузки конфигурации | Не удалось получить данные из таблицы " . TBL_CONFIG
            );
        }
        // Инициализация подклассов
        $this->security = new Work_Security();
        $this->image = new Work_Image($this->config);
        $this->template = new Work_Template($db, $this->config);
        $this->core_logic = new Work_CoreLogic($db, $this->config, $this);
    }

    /**
     * @brief Преобразование размера в байты.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода return_bytes() класса Work_Helper.
     *          Метод преобразует размер, заданный в формате "число[K|M|G]", в количество байт.
     *          Поддерживаются суффиксы:
     *          - K (килобайты): умножается на 1024.
     *          - M (мегабайты): умножается на 1024².
     *          - G (гигабайты): умножается на 1024³.
     *          Если суффикс отсутствует или недопустим, значение считается в байтах.
     *          Отрицательные числа преобразуются в положительные.
     *          Если входные данные некорректны, возвращается 0.
     *
     * @callgraph
     *
     * @param string|int $val Размер в формате "число[K|M|G]" или число.
     *                        Если суффикс недопустим, он игнорируется, и значение преобразуется в число.
     *                        Отрицательные числа преобразуются в положительные.
     *
     * @return int Размер в байтах. Возвращает 0 для некорректных входных данных.
     *
     * @warning Если суффикс недопустим, он игнорируется, и значение преобразуется в число.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $bytes = \PhotoRigma\Classes\Work::return_bytes('2M');
     * echo $bytes; // Выведет: 2097152
     *
     * $bytes = \PhotoRigma\Classes\Work::return_bytes('-1G');
     * echo $bytes; // Выведет: 1073741824
     *
     * $bytes = \PhotoRigma\Classes\Work::return_bytes('10X');
     * echo $bytes; // Выведет: 10
     *
     * $bytes = \PhotoRigma\Classes\Work::return_bytes('abc');
     * echo $bytes; // Выведет: 0
     * @endcode
     * @see PhotoRigma::Classes::Work_Helper::return_bytes()
     *      Публичный метод в классе Work_Helper.
     *
     */
    public static function return_bytes(string|int $val): int
    {
        return Work_Helper::return_bytes($val);
    }

    /**
     * @brief Транслитерация строки и замена знаков пунктуации на "_".
     *
     * @details Этот метод является делегатом (метод-редирект) для метода encodename() класса Work_Helper.
     *          Метод выполняет транслитерацию не латинских символов в латиницу и заменяет знаки пунктуации на "_".
     *          Используется для создания "безопасных" имен файлов или URL.
     *          Если входная строка пустая, то она возвращается без обработки.
     *          Если транслитерация невозможна (например, расширение intl недоступно), используется резервная таблица.
     *
     * @callgraph
     *
     * @param string $string Исходная строка.
     *                       Если строка пустая, она возвращается без обработки.
     *                       Рекомендуется использовать строки в кодировке UTF-8.
     *
     * @return string Строка после транслитерации и замены символов.
     *                Если после обработки строка становится пустой, генерируется уникальная последовательность.
     *
     * @warning Если расширение intl недоступно, используется резервная таблица транслитерации.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $encoded = \PhotoRigma\Classes\Work::encodename('Привет, мир!');
     * echo $encoded; // Выведет: Privet__mir_
     *
     * $encoded = \PhotoRigma\Classes\Work::encodename('');
     * echo $encoded; // Выведет: пустую строку
     *
     * $encoded = \PhotoRigma\Classes\Work::encodename('12345');
     * echo $encoded; // Выведет: 12345
     * @endcode
     * @see PhotoRigma::Classes::Work_Helper::encodename()
     *      Публичный метод в классе Work_Helper.
     *
     */
    public static function encodename(string $string): string
    {
        return Work_Helper::encodename($string);
    }

    /**
     * @brief Преобразование BBCode в HTML.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода ubb() класса Work_Helper.
     *          Метод преобразует BBCode-теги в соответствующие HTML-теги с учетом рекурсивной обработки вложенных тегов.
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
     *          Метод защищает от XSS-атак, проверяет корректность URL и ограничивает глубину рекурсии для вложенных тегов.
     *
     * @callgraph
     *
     * @param string $text Текст с BBCode.
     *                     Рекомендуется использовать строки в кодировке UTF-8.
     *                     Если строка пустая, она возвращается без обработки.
     *
     * @return string Текст с HTML-разметкой.
     *                Некорректные BBCode-теги игнорируются или преобразуются в текст.
     *
     * @warning Метод ограничивает глубину рекурсии для вложенных тегов (максимум 10 уровней).
     * @warning Некорректные URL или изображения заменяются на безопасные значения или удаляются.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $html = \PhotoRigma\Classes\Work::ubb('[b]Bold text[/b]');
     * echo $html; // Выведет: <strong>Bold text</strong>
     *
     * $html = \PhotoRigma\Classes\Work::ubb('[url=https://example.com]Example[/url]');
     * echo $html; // Выведет: <a href="https://example.com" target="_blank" rel="noopener noreferrer" title="Example">Example</a>
     *
     * $html = \PhotoRigma\Classes\Work::ubb('[invalid]Invalid tag[/invalid]');
     * echo $html; // Выведет: [invalid]Invalid tag[/invalid]
     * @endcode
     * @throws Exception
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     * @see PhotoRigma::Classes::Work_Helper::ubb()
     *      Публичный метод в классе Work_Helper.
     */
    public static function ubb(string $text): string
    {
        return Work_Helper::ubb($text);
    }

    /**
     * @brief Разбивка строки на несколько строк ограниченной длины.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода utf8_wordwrap() класса Work_Helper.
     *          Метод разбивает строку на несколько строк, каждая из которых имеет длину не более указанной.
     *          Разрыв строки выполняется только по пробелам, чтобы сохранить читаемость текста.
     *          Поддерживается работа с UTF-8 символами.
     *          Если параметры некорректны (например, $width <= 0 или $break пустой), возвращается исходная строка.
     *
     * @callgraph
     *
     * @param string $str Исходная строка.
     *                    Рекомендуется использовать строки в кодировке UTF-8.
     *                    Если строка пустая или её длина меньше или равна $width, она возвращается без изменений.
     * @param int $width Максимальная длина строки (по умолчанию 70).
     *                   Должен быть положительным целым числом.
     * @param string $break Символ разрыва строки (по умолчанию PHP_EOL).
     *                      Не должен быть пустой строкой.
     *
     * @return string Строка, разбитая на несколько строк.
     *                В случае некорректных параметров возвращается исходная строка.
     *
     * @warning Метод корректно работает только с UTF-8 символами.
     * @warning Если параметры некорректны (например, $width <= 0 или $break пустой), возвращается исходная строка.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $wrapped = \PhotoRigma\Classes\Work::utf8_wordwrap('This is a very long string that needs to be wrapped.', 10);
     * echo $wrapped;
     * // Выведет:
     * // This is a
     * // very long
     * // string that
     * // needs to be
     * // wrapped.
     *
     * $wrapped = \PhotoRigma\Classes\Work::utf8_wordwrap('Short text', 20);
     * echo $wrapped; // Выведет: Short text
     *
     * $wrapped = \PhotoRigma\Classes\Work::utf8_wordwrap('Invalid width', -10);
     * echo $wrapped; // Выведет: Invalid width
     *
     * $wrapped = \PhotoRigma\Classes\Work::utf8_wordwrap('Empty break', 10, '');
     * echo $wrapped; // Выведет: Empty break
     * @endcode
     * @throws Exception
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     * @see PhotoRigma::Classes::Work_Helper::utf8_wordwrap()
     *      Публичный метод в классе Work_Helper.
     */
    public static function utf8_wordwrap(string $str, int $width = 70, string $break = PHP_EOL): string
    {
        return Work_Helper::utf8_wordwrap($str, $width, $break);
    }

    // Устаревшие методы, будут удалены в ближайших версиях.

    /**
     * @brief Проверка MIME-типа файла через доступные библиотеки.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода validate_mime_type() класса Work_Helper.
     *          Метод проверяет, поддерживается ли указанный MIME-тип хотя бы одной из доступных библиотек:
     *          - Imagick
     *          - Gmagick
     *          - Встроенные функции PHP по работе с изображениями (GD)
     *          Если MIME-тип не поддерживается ни одной библиотекой, возвращается false.
     *          Поддерживаются MIME-типы для изображений, таких как JPEG, PNG, GIF, WebP и другие.
     *
     * @callgraph
     *
     * @param string $real_mime_type Реальный MIME-тип файла.
     *                               Должен быть корректным MIME-типом для изображений.
     *
     * @return bool True, если MIME-тип поддерживается хотя бы одной библиотекой, иначе false.
     *
     * @warning Метод зависит от доступности библиотек (Imagick, Gmagick) и встроенных функций PHP по работе с изображениями (GD).
     *          Если ни одна из библиотек недоступна, метод может некорректно работать.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $is_supported = \PhotoRigma\Classes\Work::validate_mime_type('image/jpeg');
     * var_dump($is_supported); // Выведет: true
     *
     * $is_supported = \PhotoRigma\Classes\Work::validate_mime_type('application/pdf');
     * var_dump($is_supported); // Выведет: false
     *
     * $is_supported = \PhotoRigma\Classes\Work::validate_mime_type('invalid/mime');
     * var_dump($is_supported); // Выведет: false
     * @endcode
     * @throws Exception
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     * @see PhotoRigma::Classes::Work_Helper::validate_mime_type()
     *      Публичный метод, вызывающий этот защищённый метод.
     */
    public static function validate_mime_type(string $real_mime_type): bool
    {
        return Work_Helper::validate_mime_type($real_mime_type);
    }

    /**
     * @brief Магический метод для получения значений свойств `$config` и `$lang`.
     *
     * @details Этот метод вызывается автоматически при попытке получить значение недоступного свойства.
     *          Доступ разрешён только к свойствам `$config` и `$lang`. Если запрашивается другое свойство,
     *          выбрасывается исключение InvalidArgumentException.
     *
     * @callergraph
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
     * @note Этот метод предназначен только для доступа к свойствам `$config` и `$lang`.
     *       Любые другие запросы будут игнорироваться с выбросом исключения.
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
     * @see PhotoRigma::Classes::Work::$lang Свойство, содержащее языковые данные.
     *
     * @see PhotoRigma::Classes::Work::$config Свойство, содержащее конфигурацию.
     */
    public function &__get(string $name): array
    {
        switch ($name) {
            case 'config':
                $result = &$this->config;
                break;
            case 'lang':
                $result = &$this->lang;
                break;
            default:
                throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не существует | Получено: '$name'"
                );
        }

        return $result;
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
     * @param string $name Имя свойства:
     *                     - Допустимое значение: 'config'.
     *                     - Если указано другое имя, выбрасывается исключение.
     * @param mixed $value Значение свойства:
     *                     - Должен быть массивом, где ключи и значения являются строками.
     *
     * @throws InvalidArgumentException Если значение некорректно (не массив или содержатся некорректные ключи/значения).
     * @throws Exception Если запрашивается несуществующее свойство.*@throws \Exception
     *
     * @note Этот метод предназначен только для изменения свойства `$config`.
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
     * @see PhotoRigma::Include::log_in_file() Логирует ошибки.
     *
     * @see PhotoRigma::Classes::Work::$config Свойство, содержащее конфигурацию.
     * @see PhotoRigma::Classes::Work_CoreLogic::$config Свойство дочернего класса Work_CoreLogic.
     * @see PhotoRigma::Classes::Work_Image::$config Свойство дочернего класса Work_Image.
     * @see PhotoRigma::Classes::Work_Template::$config Свойство дочернего класса Work_Template.
     */
    public function __set(string $name, array $value)
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
    }

    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    // Методы из под-класса Work_Security.

    /**
     * @brief Устанавливает языковые данные из файла и передаёт их в дочерние классы.
     *
     * @details Этот метод используется для установки массива языковых данных ($lang) из файла.
     *          Выполняются следующие шаги:
     *          1. Формируется путь к файлу языковых данных на основе текущего языка сессии или конфигурации.
     *          2. Проверяется существование и доступность файла для чтения.
     *          3. Загружаются данные из файла, которые должны быть массивом с ключом `'lang'`.
     *          4. Проверяется корректность массива через метод `process_lang_array()`.
     *          5. Логируются изменения (если они есть).
     *          6. Обновляются языковые данные в текущем классе и передаются в дочерние классы (`Work_Template` и `Work_CoreLogic`).
     *
     * @param string $lang Имя файла языковых данных:
     *                     - Должен быть непустой строкой.
     *                     - Должен соответствовать имени файла в директории языковых данных.
     *                     - По умолчанию: `'main'`.
     *
     * @throws InvalidArgumentException Если имя файла некорректно (пустое или не существует).
     *                                  Пример сообщения: "Некорректное имя языкового файла | Поле не должно быть пустым".
     *                                  Если массив языковых данных некорректен (не содержит ключ `'lang'` или содержит ошибки).
     *                                  Пример сообщения: "Обнаружены ошибки в массиве языковых данных | Ошибки: [список ошибок]".
     * @throws RuntimeException         Если файл языковых данных недоступен для чтения.
     * @throws Exception
     *                                  Пример сообщения: "Файл языковых данных недоступен для чтения | Путь: [путь к файлу]".
     *
     * @see PhotoRigma::Include::log_in_file() Внешняя функция для логирования ошибок.
     * @see PhotoRigma::Classes::Work::process_lang_array() Метод для проверки корректности массива языковых данных.
     *
     * @see PhotoRigma::Classes::Work::$lang Свойство класса Work, которое изменяется.
     * @see PhotoRigma::Classes::Work_CoreLogic::$lang Свойство дочернего класса Work_CoreLogic.
     * @see PhotoRigma::Classes::Work_Template::$lang Свойство дочернего класса Work_Template.
     * @todo Интегрировать в систему кеширования языковых переменных.
     *
     * @note Файл языковых данных должен быть доступен для чтения.
     *       Массив языковых данных должен содержать ключ `'lang'`.
     *
     * @warning Если файл языковых данных отсутствует или содержит ошибки, метод выбрасывает исключение.
     *
     * Пример использования метода:
     * @code
     * $work = new \PhotoRigma\Classes\Work();
     * $work->set_lang('main');
     * @endcode
     */
    public function set_lang(string $lang = 'main'): void
    {
        if (empty($lang)) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " . "Некорректное имя языкового файла | Поле не должно быть пустым"
            );
        }

        // Устанавливаем язык из сессии или конфигурации
        if (empty($this->session['language'])) {
            $this->session['language'] = $this->config['language'];
        }

        // Формируем путь к файлу языковых данных
        $lang_file_path = $this->config['site_dir'] . '/language/' . $this->session['language'] . '/' . $lang . '.php';

        // Проверяем существование файла
        if (!file_exists($lang_file_path)) {
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
     * @brief Обрабатывает массив языковых переменных, проверяя его структуру и очищая значения.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет, что ключи первого уровня массива являются строками.
     *          2. Проверяет, что значения первого уровня являются массивами.
     *          3. Проверяет, что ключи второго уровня являются строками.
     *          4. Проверяет, что значения второго уровня являются строками.
     *          5. Очищает значения второго уровня через метод `clean_field()` для защиты от XSS.
     *          6. Возвращает массив с результатами обработки, включая ошибки, изменения и обработанный массив.
     *
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
     *               - `'changes'` (array): Массив изменений, если значения были очищены (содержит оригинальные и очищенные значения).
     *               - `'result'` (array): Обработанный массив языковых переменных.
     *
     * @note Используется метод `clean_field()` для очистки значений (защита от XSS).
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
     * @see PhotoRigma::Classes::Work::clean_field() Метод для очистки и экранирования полей (защита от XSS).
     *
     */
    private function process_lang_array(array $lang): array
    {
        $result = [
            'errors' => [],
            'changes' => [],
            'result' => $lang, // Инициализируем результат исходным массивом
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
                        'cleaned' => $cleaned_value,
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
     * @brief Очистка строки от HTML-тегов и специальных символов.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода clean_field() класса Work_Helper.
     *          Метод удаляет HTML-теги и экранирует специальные символы, такие как `&lt;`, `&gt;`, `&amp;`, `&quot;`, `&#039;`.
     *          Используется для защиты от XSS-атак и других проблем, связанных с некорректными данными.
     *
     * @callgraph
     *
     * @param mixed $field Строка или данные, которые могут быть преобразованы в строку.
     *                     Если входные данные пусты (null или пустая строка), метод вернёт null.
     *
     * @return string Очищенная строка или null, если входные данные пусты.
     *
     * @warning Метод не обрабатывает вложенные структуры данных (например, массивы).
     *          Убедитесь, что входные данные могут быть преобразованы в строку.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $dirty_input = '&lt;script&gt;alert(&quotXSS&quot)&lt;/script&gt;';
     * $cleaned = Work::clean_field($dirty_input);
     * echo $cleaned; // Выведет: &lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;
     * @endcode
     * @see PhotoRigma::Classes::Work_Helper::clean_field()
     *      Публичный метод в классе Work_Helper.
     *
     */
    public static function clean_field(string $field): string
    {
        return Work_Helper::clean_field($field);
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
     * Пример использования метода:
     * @code
     * $work = new \PhotoRigma\Classes\Work();
     * $user = new \PhotoRigma\Classes\User();
     * $work->set_user($user);
     * @endcode
     * @see PhotoRigma::Classes::Work_Template::$user Свойство дочернего класса Work_Template.
     * @see PhotoRigma::Include::log_in_file() Логирует ошибки.
     *
     * @see PhotoRigma::Classes::User Класс с объектом пользователя.
     * @see PhotoRigma::Classes::Work_CoreLogic::$user Свойство дочернего класса Work_CoreLogic.
     */
    public function set_user(User $user): void
    {
        $this->template->set_user($user);
        $this->core_logic->set_user($user);
    }

    /**
     * @brief Метод-редирект для проверки данных из POST-запроса.
     *
     * @details Этот метод является заглушкой и вызывает метод check_input() из дочернего класса Work_Security.
     *          Метод проверяет данные из массива $_POST на соответствие указанным условиям:
     *          - Существование поля (параметр $isset).
     *          - Пустота поля (параметр $empty).
     *          - Соответствие регулярному выражению (параметр $regexp).
     *          - Ненулевое значение (параметр $not_zero).
     *          Этот метод устарел. Рекомендуется использовать Work::check_input().
     *
     * @callgraph
     *
     * @param string $field Поле для проверки.
     *                      Указывается имя ключа в массиве $_POST, которое необходимо проверить.
     * @param bool $isset Флаг, указывающий, что поле должно существовать в массиве $_POST.
     *                    По умолчанию false.
     * @param bool $empty Флаг, указывающий, что поле не должно быть пустым.
     *                    По умолчанию false.
     * @param string|false $regexp Регулярное выражение для проверки поля или false, если проверка не требуется.
     *                              По умолчанию false.
     * @param bool $not_zero Флаг, указывающий, что значение поля не должно быть нулём.
     *                       По умолчанию false.
     *
     * @return bool True, если данные прошли проверку, иначе False.
     *
     * @throws Exception
     * @deprecated Этот метод устарел. Используйте Work::check_input() вместо него.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $result = $work->check_post('username', true, true, '/^[a-z0-9]+$/i', false);
     * if ($result) {
     *     echo "Данные прошли проверку.";
     * } else {
     *     echo "Данные не прошли проверку.";
     * }
     * @endcode
     * @see PhotoRigma::Classes::Work_Security::check_input()
     *      Метод, который проверяет входные данные.
     *
     */
    public function check_post(
        string $field,
        bool $isset = false,
        bool $empty = false,
        string|false $regexp = false,
        bool $not_zero = false
    ): bool {
        return $this->security->check_input('_POST', $field, [
            'isset' => $isset,
            'empty' => $empty,
            'regexp' => $regexp,
            'not_zero' => $not_zero,
        ]);
    }

    // Методы из под-класса Work_CoreLogic.

    /**
     * @brief Универсальная проверка входных данных.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода check_input() класса Work_Security.
     *          Метод выполняет проверку данных из различных источников ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES)
     *          на соответствие заданным условиям. Поддерживаются следующие типы проверок:
     *          - Проверка наличия поля в источнике данных (параметр 'isset').
     *          - Проверка, что значение поля не пустое (параметр 'empty').
     *          - Проверка значения поля по регулярному выражению (параметр 'regexp').
     *          - Проверка, что значение поля не равно нулю (параметр 'not_zero').
     *          - Проверка размера файла для $_FILES (параметр 'max_size') и его MIME-типа.
     *
     * @callgraph
     *
     * @param string $source_name Источник данных ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES).
     *                            Должен быть одним из допустимых значений: '_GET', '_POST', '_SESSION', '_COOKIE', '_FILES'.
     * @param string $field Поле для проверки (имя ключа в массиве источника данных).
     *                      Указывается имя ключа, которое необходимо проверить.
     * @param array $options Дополнительные параметры проверки:
     *                       - 'isset' (bool, опционально): Проверять наличие поля в источнике данных.
     *                       - 'empty' (bool, опционально): Проверять, что значение поля не пустое.
     *                       - 'regexp' (string|false, опционально): Регулярное выражение для проверки значения поля.
     *                       - 'not_zero' (bool, опционально): Проверять, что значение поля не равно нулю.
     *                       - 'max_size' (int, опционально): Максимальный размер файла (в байтах) для $_FILES.
     *
     * @return bool True, если данные прошли проверку, иначе False.
     *              Для $_FILES также учитывается корректность MIME-типа и размера файла.
     *
     * @warning Метод зависит от корректности данных в источниках ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES).
     *          Если источник данных повреждён или содержит некорректные значения, результат может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $result = $work->check_input('_POST', 'username', [
     *     'isset' => true,
     *     'empty' => false,
     *     'regexp' => '/^[a-z0-9]+$/i',
     * ]);
     * if ($result) {
     *     echo "Проверка пройдена!";
     * }
     * @endcode
     * @throws Exception
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     * @see PhotoRigma::Classes::Work_Security::check_input()
     *      Реализация метода внутри класса Work_Security.
     * @see PhotoRigma::Classes::Work::validate_mime_type()
     *      Метод для проверки поддерживаемых MIME-типов.
     */
    public function check_input(string $source_name, string $field, array $options = []): bool
    {
        return $this->security->check_input($source_name, $field, $options);
    }

    /**
     * @brief Метод-редирект для проверки данных из GET-запроса.
     *
     * @details Этот метод является заглушкой и вызывает метод check_input() из дочернего класса Work_Security.
     *          Метод проверяет данные из массива $_GET на соответствие указанным условиям:
     *          - Существование поля (параметр $isset).
     *          - Пустота поля (параметр $empty).
     *          - Соответствие регулярному выражению (параметр $regexp).
     *          - Ненулевое значение (параметр $not_zero).
     *          Этот метод устарел. Рекомендуется использовать Work::check_input().
     *
     * @callgraph
     *
     * @param string $field Поле для проверки.
     *                      Указывается имя ключа в массиве $_GET, которое необходимо проверить.
     * @param bool $isset Флаг, указывающий, что поле должно существовать в массиве $_GET.
     *                    По умолчанию false.
     * @param bool $empty Флаг, указывающий, что поле не должно быть пустым.
     *                    По умолчанию false.
     * @param string|false $regexp Регулярное выражение для проверки поля или false, если проверка не требуется.
     *                              По умолчанию false.
     * @param bool $not_zero Флаг, указывающий, что значение поля не должно быть нулём.
     *                       По умолчанию false.
     *
     * @return bool True, если данные прошли проверку, иначе False.
     *
     * @throws Exception
     * @deprecated Этот метод устарел. Используйте Work::check_input() вместо него.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $result = $work->check_get('id', true, false, '/^\d+$/', true);
     * if ($result) {
     *     echo "Данные прошли проверку.";
     * } else {
     *     echo "Данные не прошли проверку.";
     * }
     * @endcode
     * @see PhotoRigma::Classes::Work_Security::check_input()
     *      Метод, который проверяет входные данные.
     *
     */
    public function check_get(
        string $field,
        bool $isset = false,
        bool $empty = false,
        string|false $regexp = false,
        bool $not_zero = false
    ): bool {
        return $this->security->check_input('_GET', $field, [
            'isset' => $isset,
            'empty' => $empty,
            'regexp' => $regexp,
            'not_zero' => $not_zero,
        ]);
    }

    /**
     * @brief Метод-редирект для проверки данных из сессии.
     *
     * @details Этот метод является заглушкой и вызывает метод check_input() из дочернего класса Work_Security.
     *          Метод проверяет данные из массива $_SESSION на соответствие указанным условиям:
     *          - Существование поля (параметр $isset).
     *          - Пустота поля (параметр $empty).
     *          - Соответствие регулярному выражению (параметр $regexp).
     *          - Ненулевое значение (параметр $not_zero).
     *          Этот метод устарел. Рекомендуется использовать Work::check_input().
     *
     * @callgraph
     *
     * @param string $field Поле для проверки.
     *                      Указывается имя ключа в массиве $_SESSION, которое необходимо проверить.
     * @param bool $isset Флаг, указывающий, что поле должно существовать в массиве $_SESSION.
     *                    По умолчанию false.
     * @param bool $empty Флаг, указывающий, что поле не должно быть пустым.
     *                    По умолчанию false.
     * @param string|false $regexp Регулярное выражение для проверки поля или false, если проверка не требуется.
     *                              По умолчанию false.
     * @param bool $not_zero Флаг, указывающий, что значение поля не должно быть нулём.
     *                       По умолчанию false.
     *
     * @return bool True, если данные прошли проверку, иначе False.
     *
     * @throws Exception
     * @deprecated Этот метод устарел. Используйте Work::check_input() вместо него.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $result = $work->check_session('user_id', true, false, '/^\d+$/', true);
     * if ($result) {
     *     echo "Данные прошли проверку.";
     * } else {
     *     echo "Данные не прошли проверку.";
     * }
     * @endcode
     * @see PhotoRigma::Classes::Work_Security::check_input()
     *      Метод, который проверяет входные данные.
     *
     */
    public function check_session(
        string $field,
        bool $isset = false,
        bool $empty = false,
        string|false $regexp = false,
        bool $not_zero = false
    ): bool {
        return $this->security->check_input('_SESSION', $field, [
            'isset' => $isset,
            'empty' => $empty,
            'regexp' => $regexp,
            'not_zero' => $not_zero,
        ]);
    }

    /**
     * @brief Проверяет URL на наличие вредоносного кода.
     *
     * @details Метод проверяет строку запроса ($_SERVER['REQUEST_URI']) на наличие запрещённых паттернов,
     *          определяемых в массиве Work_Security::compiled_rules. Если формат URL некорректен или найден запрещённый паттерн,
     *          метод возвращает false. Этот метод является делегатом (метод-редирект) для метода url_check()
     *          класса Work_Security.
     *
     * @callgraph
     *
     * @return bool True, если URL безопасен (не содержит запрещённых паттернов), иначе False.
     *
     * @note Метод работает с глобальным массивом $_SERVER['REQUEST_URI'].
     *
     * @warning Метод зависит от корректности данных в свойстве Work_Security::compiled_rules.
     *          Если правила некорректны, результат может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * if (!$work->url_check()) {
     *     echo "Обнаружен подозрительный URL!";
     * }
     * @endcode
     * @throws Exception
     * @see PhotoRigma::Classes::Work_Security::url_check()
     *      Реализация метода внутри класса Work_Security.
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     */
    public function url_check(): bool
    {
        return $this->security->url_check();
    }

    /**
     * @brief Проверяет содержимое поля на соответствие регулярному выражению или другим условиям.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода check_field() класса Work_Security.
     *          Метод выполняет следующие проверки:
     *          - Если задано регулярное выражение ($regexp), проверяет соответствие значения этому выражению.
     *            При этом также проверяется корректность самого регулярного выражения (например, отсутствие ошибок компиляции).
     *          - Если флаг $not_zero установлен, проверяет, что значение не является числом 0.
     *          - Проверяет, что значение не содержит запрещённых паттернов из Work_Security::compiled_rules.
     *
     * @callgraph
     *
     * @param string $field Значение поля для проверки.
     *                      Указывается строковое значение, которое необходимо проверить.
     * @param string|false $regexp Регулярное выражение (необязательно). Если задано, значение должно соответствовать этому выражению.
     *                              Если регулярное выражение некорректно (например, содержит ошибки компиляции),
     *                              метод завершает выполнение с ошибкой.
     *                              По умолчанию false.
     * @param bool $not_zero Флаг, указывающий, что значение не должно быть числом 0.
     *                       Если флаг установлен, а значение равно '0', проверка завершается с ошибкой.
     *                       По умолчанию false.
     *
     * @return bool True, если поле прошло все проверки, иначе False.
     *              Проверки включают соответствие регулярному выражению, отсутствие запрещённых паттернов
     *              и выполнение условия $not_zero (если оно задано).
     *
     * @warning Метод зависит от корректности данных в свойстве Work_Security::compiled_rules.
     *          Если правила некорректны, результат может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $result = $work->check_field('test123', '/^[a-z0-9]+$/i', false);
     * if ($result) {
     *     echo "Поле прошло проверку!";
     * }
     * @endcode
     * @throws Exception
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     * @see PhotoRigma::Classes::Work_Security::check_field()
     *      Реализация метода внутри класса Work_Security.
     */
    public function check_field(string $field, string|false $regexp = false, bool $not_zero = false): bool
    {
        return $this->security->check_field($field, $regexp, $not_zero);
    }

    /**
     * @brief Генерирует математический CAPTCHA-вопрос и ответ.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода gen_captcha() класса Work_Security.
     *          Метод создаёт случайное математическое выражение (сложение или умножение)
     *          и возвращает его вместе с правильным ответом. Используется для защиты от спам-ботов.
     *          Выражение может включать комбинацию сложения и умножения с использованием скобок.
     *
     * @callgraph
     *
     * @return array Массив с ключами 'question' и 'answer':
     *               - 'question': строка математического выражения (например, "2 x (3 + 4)").
     *               - 'answer': целочисленный результат вычисления (например, 14).
     *
     * @warning Метод использует функцию rand() для генерации случайных чисел.
     *          Если требуется криптографическая безопасность, следует заменить её на random_int().
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $captcha = $work->gen_captcha();
     * echo "Вопрос: {$captcha['question']}, Ответ: {$captcha['answer']}";
     * // Пример вывода: Вопрос: 2 x (3 + 4), Ответ: 14
     * @endcode
     * @throws RandomException
     * @see PhotoRigma::Classes::Work_Security::gen_captcha()
     *      Реализация метода внутри класса Work_Security.
     *
     */
    public function gen_captcha(): array
    {
        return $this->security->gen_captcha();
    }

    // Методы из под-класса Work_Template.

    /**
     * @brief Заменяет символы в email-адресах для "обмана" ботов.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода filt_email() класса Work_Security.
     *          Метод заменяет символы '@' и '.' на '[at]' и '[dot]', чтобы затруднить автоматический парсинг email-адресов ботами.
     *          Проверяет, что входной email не пустой и соответствует формату email. Если email некорректен или пуст,
     *          метод возвращает пустую строку.
     *
     * @callgraph
     *
     * @param string $email Email-адрес для обработки.
     *                      Должен быть непустым и соответствовать формату email (например, "example@example.com").
     *                      Если email некорректен или пуст, метод возвращает пустую строку.
     *
     * @return string Обработанный email-адрес, где символы '@' и '.' заменены на '[at]' и '[dot]'.
     *                Если входной email некорректен или пуст, возвращается пустая строка.
     *
     * @warning Метод использует функцию filter_var() для проверки формата email.
     *          Если требуется более строгая валидация, следует учитывать дополнительные правила.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $filteredEmail = $work->filt_email('example@example.com');
     * echo $filteredEmail; // Выведет: example[at]example[dot]com
     * @endcode
     * @throws Exception
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     * @see PhotoRigma::Classes::Work_Security::filt_email()
     *      Реализация метода внутри класса Work_Security.
     */
    public function filt_email(string $email): string
    {
        return $this->security->filt_email($email);
    }

    /**
     * @brief Формирует информационную строку для категории или пользовательского альбома.
     *
     * @details Метод является редиректом, вызывающим метод `category()` из дочернего класса `Work_CoreLogic`.
     *          Основная логика включает:
     *          - Получение данных о категории или пользовательском альбоме из базы данных.
     *          - Подсчет количества фотографий.
     *          - Получение данных о последней и лучшей фотографии (если разрешено отображение).
     *          - Для корневой категории (`$cat_id = 0`) подсчет количества уникальных пользователей, загрузивших фотографии.
     *          - Формирование результирующего массива с информацией о категории или альбоме.
     *
     * @callgraph
     *
     * @param int $cat_id Идентификатор категории или пользователя (если `$user_flag = 1`). По умолчанию: `0`.
     *                    Должен быть целым числом >= `0`.
     * @param int $user_flag Флаг, указывающий формировать ли информацию о категории (`0`) или пользовательском альбоме (`1`).
     *                       По умолчанию: `0`. Допустимые значения: `0` или `1`.
     *
     * @return array Информационная строка для категории или пользовательского альбома:
     *               - 'name' (string): Название категории или альбома.
     *               - 'description' (string): Описание категории или альбома.
     *               - 'count_photo' (int): Количество фотографий.
     *               - 'last_photo' (string): Название последней фотографии.
     *               - 'top_photo' (string): Название лучшей фотографии.
     *               - 'url_cat' (string): Ссылка на категорию или альбом.
     *               - 'url_last_photo' (string): Ссылка на последнюю фотографию.
     *               - 'url_top_photo' (string): Ссылка на лучшую фотографию.
     *
     * @throws InvalidArgumentException Если входные параметры имеют некорректный тип или значение.
     * @throws PDOException Если возникают ошибки при получении данных из базы данных.
     *
     * @note Используются константы:
     *       - TBL_CATEGORY: Таблица для хранения данных о категориях.
     *       - TBL_USERS: Таблица для хранения данных о пользователях.
     *       - TBL_PHOTO: Таблица для хранения данных о фотографиях.
     *
     *       Конфигурационные ключи:
     *       - site_url: URL сайта, используемый для формирования ссылок на категории и фотографии.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $categoryData = $work->category(123, 0);
     * print_r($categoryData);
     * @endcode
     * @see PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
     *
     * @see PhotoRigma::Classes::Work_CoreLogic::category() Метод из дочернего класса, реализующий основную логику.
     */
    public function category(int $cat_id = 0, int $user_flag = 0): array
    {
        return $this->core_logic->category($cat_id, $user_flag);
    }

    /**
     * @brief Удаляет изображение с указанным идентификатором, а также все упоминания об этом изображении в таблицах сайта.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода del_photo() класса Work_CoreLogic.
     *          Метод выполняет удаление изображения с указанным идентификатором, включая следующие шаги:
     *          1. Удаляет файлы из каталогов полноразмерных изображений и эскизов, используя пути, заданные в конфигурации (`$this->config`).
     *             Перед удалением проверяется существование файлов.
     *          2. Удаляет запись об изображении из таблицы `TBL_PHOTO`.
     *          3. Удаляет связанные записи из таблиц `TBL_RATE_USER` и `TBL_RATE_MODER`.
     *          4. Логирует ошибки, возникающие при удалении файлов или выполнении запросов к базе данных, с помощью функции `log_in_file()`.
     *
     * @callgraph
     *
     * @param int $photo_id Идентификатор удаляемого изображения (обязательное поле).
     *                      Должен быть положительным целым числом.
     *
     * @return bool True, если удаление успешно, иначе False.
     *
     * @throws InvalidArgumentException Если параметр $photo_id имеет некорректный тип или значение.
     *      Пример сообщения:
     *          Неверное значение параметра $photo_id | Ожидалось положительное целое число
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных или удалении файлов.
     *      Пример сообщения:
     *          Не удалось найти изображение | Переменная $photo_id = [значение]
     * @throws Exception
     *
     * @warning Метод чувствителен к правам доступа при удалении файлов. Убедитесь, что скрипт имеет необходимые права на запись и чтение.
     * @warning Удаление файлов и записей из базы данных необратимо. Убедитесь, что передан корректный идентификатор изображения.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * if ($work->del_photo(123)) {
     *     echo "Изображение успешно удалено.";
     * } else {
     *     echo "Не удалось удалить изображение.";
     * }
     * @endcode
     * @see PhotoRigma::Classes::Work_CoreLogic::del_photo()
     *      Публичный метод-редирект для вызова этой логики.
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     * @note Используются константы:
     *       - TBL_PHOTO: Таблица для хранения данных об изображениях.
     *       - TBL_RATE_USER: Таблица для хранения пользовательских оценок изображений.
     *       - TBL_RATE_MODER: Таблица для хранения оценок модераторов.
     *
     */
    public function del_photo(int $photo_id): bool
    {
        return $this->core_logic->del_photo($photo_id);
    }

    /**
     * @brief Получает данные о новостях в зависимости от типа запроса.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода news() класса Work_CoreLogic.
     *          Метод выполняет запросы к базе данных для получения данных о новостях, включая следующие шаги:
     *          1. Проверяет входные параметры $news_id_or_limit и $act на корректность.
     *          2. Формирует параметры запроса через match():
     *             - Для $act = 'id': Получает новость по её ID.
     *             - Для $act = 'last': Получает список новостей с сортировкой по дате последнего редактирования.
     *          3. Выполняет запрос к таблице TBL_NEWS с использованием параметров.
     *          4. Возвращает массив с данными о новостях или пустой массив, если новости не найдены.
     *
     * @callgraph
     *
     * @param int $news_id_or_limit Количество новостей или ID новости (в зависимости от параметра $act).
     *                                 Должен быть положительным целым числом.
     * @param string $act Тип запроса:
     *                                 - 'id': Получение новости по её ID.
     *                                 - 'last': Получение списка новостей с сортировкой по дате последнего редактирования.
     *
     * @return array Массив с данными о новостях. Если новостей нет, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный $act или $news_id_or_limit.
     *      Пример сообщения:
     *          Некорректный ID новости | Переменная $news_id_or_limit = [значение]
     * @throws RuntimeException         Если произошла ошибка при выполнении запроса к базе данных.
     *      Пример сообщения:
     *          Не удалось получить данные из базы данных | Тип запроса: '$act'
     *
     * @note Используются константы:
     *       - TBL_NEWS: Таблица для хранения данных о новостях.
     *
     * @warning Метод чувствителен к корректности входных параметров $news_id_or_limit и $act.
     *          Убедитесь, что передаются допустимые значения.
     * @warning Если новости не найдены, метод возвращает пустой массив.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     *
     * // Получить новость с ID = 5
     * $newsById = $work->news(5, 'id');
     * print_r($newsById);
     *
     * // Получить последние 10 новостей
     * $newsList = $work->news(10, 'last');
     * print_r($newsList);
     * @endcode
     * @see PhotoRigma::Classes::Work_CoreLogic::news()
     *      Публичный метод-редирект для вызова этой логики.
     *
     */
    public function news(int $news_id_or_limit, string $act): array
    {
        return $this->core_logic->news($news_id_or_limit, $act);
    }

    // Методы из под-класса Work_Image.

    /**
     * @brief Загружает доступные языки из директории /language/.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода get_languages() класса Work_CoreLogic.
     *          Метод выполняет загрузку доступных языков, включая следующие шаги:
     *          1. Нормализует путь к директории `/language/` и проверяет её существование.
     *          2. Перебирает все поддиректории в `/language/` и проверяет наличие файла `main.php`.
     *          3. Для каждой поддиректории:
     *             - Проверяет доступность файла `main.php`.
     *             - Безопасно подключает файл и проверяет наличие переменной `$lang_name`.
     *             - Если переменная `$lang_name` определена и корректна, добавляет язык в список доступных.
     *          4. Возвращает массив с данными о доступных языках или выбрасывает исключение, если языки не найдены.
     *
     * @callgraph
     *
     * @return array Массив с данными о доступных языках. Каждый элемент массива содержит:
     *               - `value`: Имя директории языка (строка).
     *               - `name`: Название языка из файла `main.php` (строка).
     *
     * @throws RuntimeException Если:
     *                           - Директория `/language/` недоступна или не существует.
     *                           - Ни один язык не найден в указанной директории.
     * @throws Exception
     *
     * @warning Метод чувствителен к структуре директории `/language/` и содержимому файла `main.php`.
     *          Убедитесь, что файл `main.php` содержит корректную переменную `$lang_name`.
     * @warning Если директория `/language/` недоступна или пуста, метод выбрасывает исключение.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $languages = $work->get_languages();
     * print_r($languages);
     * @endcode
     * @see PhotoRigma::Classes::Work_CoreLogic::get_languages()
     *      Публичный метод-редирект для вызова этой логики.
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     */
    public function get_languages(): array
    {
        return $this->core_logic->get_languages();
    }

    /**
     * @brief Загружает доступные темы из директории /themes/.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода get_themes() класса Work_CoreLogic.
     *          Метод выполняет загрузку доступных тем, включая следующие шаги:
     *          1. Нормализует путь к директории `/themes/` и проверяет её существование.
     *          2. Перебирает все поддиректории в `/themes/`.
     *          3. Для каждой поддиректории:
     *             - Проверяет доступность директории.
     *             - Проверяет, что директория находится внутри разрешенной директории `/themes/`.
     *             - Добавляет имя поддиректории в список доступных тем.
     *          4. Возвращает массив с именами доступных тем или выбрасывает исключение, если темы не найдены.
     *
     * @callgraph
     *
     * @return array Массив строк с именами доступных тем. Если темы не найдены, возвращается пустой массив.
     *
     * @throws RuntimeException|Exception Если:
     *                           - Директория `/themes/` не существует или недоступна для чтения.
     *                           - Ни одна тема не найдена в указанной директории.
     *
     * @warning Метод чувствителен к структуре директории `/themes/`.
     *          Убедитесь, что директория существует и содержит хотя бы одну поддиректорию.
     * @warning Если директория `/themes/` недоступна или пуста, метод выбрасывает исключение.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $themes = $work->get_themes();
     * print_r($themes);
     * @endcode
     * @see PhotoRigma::Classes::Work_CoreLogic::get_themes()
     *      Публичный метод-редирект для вызова этой логики.
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     */
    public function get_themes(): array
    {
        return $this->core_logic->get_themes();
    }

    /**
     * @brief Генерирует блок данных для вывода изображений различных типов.
     *
     * @details Этот метод является делегатом (метод-редирект), вызывающим метод `create_photo()` из дочернего класса `Work_Image`.
     *          Основная логика метода включает:
     *          1. Проверку прав пользователя на просмотр изображений (`$this->user->user['pic_view']`).
     *          2. Формирование SQL-запроса для получения данных изображения:
     *             - Для типа `'top'`: Выбирает лучшее изображение с учетом рейтинга.
     *             - Для типа `'last'`: Выбирает последнее загруженное изображение.
     *             - Для типа `'cat'`: Выбирает изображение из конкретной категории по `$id_photo`.
     *             - Для типа `'rand'`: Выбирает любое случайное изображение.
     *          3. Проверку существования файла изображения и его доступности.
     *          4. Вычисление размеров изображения через метод `size_image()`.
     *          5. Возврат массива данных для вывода изображения или вызов `generate_photo_data()` в случае ошибки.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $type Тип изображения:
     *                     - `'top'`: Лучшее изображение (по рейтингу).
     *                     - `'last'`: Последнее загруженное изображение.
     *                     - `'cat'`: Изображение из конкретной категории (требует указания `$id_photo`).
     *                     - `'rand'`: Любое случайное изображение.
     * @param int $id_photo Идентификатор фото. Используется только при `$type == 'cat'`. Должен быть >= `0`.
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
     *                                  Пример сообщения: "Некорректный идентификатор фотографии | Значение: {$id_photo}".
     * @throws PDOException            Если произошла ошибка при выборке данных из базы данных.
     *                                  Пример сообщения: "Ошибка базы данных | Не удалось получить данные категории с ID: {$photo_data['category']}".
     * @throws RuntimeException|Exception         Если файл изображения недоступен или не существует.
     *
     * @note Используются следующие константы:
     *       - TBL_PHOTO: Таблица для хранения данных об изображениях.
     *       - TBL_USERS: Таблица для хранения данных о пользователях.
     *       - TBL_CATEGORY: Таблица для хранения данных о категориях.
     *
     * @warning Метод чувствителен к правам доступа пользователя (`$this->user->user['pic_view']`).
     *          Убедитесь, что пользователь имеет право на просмотр изображений.
     * @warning Если файл изображения недоступен или не существует, метод возвращает данные по умолчанию через `generate_photo_data()`.
     * @warning Проверка пути к файлу изображения гарантирует, что доступ возможен только к файлам внутри `$this->config['gallery_folder']`.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $imageData = $work->create_photo('top', 0);
     * print_r($imageData);
     * @endcode
     * @see PhotoRigma::Classes::Work_Image::create_photo() Метод из дочернего класса, реализующий основную логику.
     * @see PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
     *
     */
    public function create_photo(string $type = 'top', int $id_photo = 0): array
    {
        return $this->core_logic->create_photo($type, $id_photo);
    }

    /**
     * @brief Обрабатывает добавление новой оценки и пересчитывает среднюю оценку через вызов метода из дочернего класса.
     *
     * @details Этот публичный метод является редиректом, который вызывает метод `process_rating()` из дочернего класса
     * `PhotoRigma::Classes::Work_CoreLogic`. Вызываемый метод выполняет следующие действия:
     *          - Вставляет новую оценку в указанную таблицу через `$this->db->insert()`.
     *          - Проверяет успешность вставки по значению `get_last_insert_id()`.
     *          - Пересчитывает среднюю оценку на основе всех оценок для фотографии.
     *          Дополнительные проверки или преобразования данных перед вызовом дочернего метода отсутствуют.
     *          Метод предназначен для использования вне класса.
     *
     * @callgraph
     *
     * @param string $table Имя таблицы для вставки оценки.
     *                      Должен быть строкой, соответствующей существующей таблице в базе данных.
     * @param int $photo_id ID фотографии.
     *                      Должен быть положительным целым числом.
     * @param int $user_id ID пользователя.
     *                     Должен быть положительным целым числом.
     * @param int $rate_value Значение оценки.
     *                        Должен быть целым числом в диапазоне допустимых значений (например, 1–5).
     *
     * @return float Возвращает число с плавающей точкой, представляющее среднюю оценку.
     *               Если оценок нет, возвращается `0`.
     *
     * @throws RuntimeException Выбрасывается исключение, если не удалось добавить оценку.
     *                           Причина: `get_last_insert_id()` возвращает `0`, что указывает на неудачную вставку.
     *
     * @note Метод использует базу данных для вставки и выборки данных.
     *
     * @warning Убедитесь, что таблица существует и данные корректны перед вызовом метода.
     *
     * Пример внешнего вызова метода через родительский класс:
     * @code
     * $object = new \PhotoRigma\Classes\Work();
     * $averageRate = $object->process_rating('ratings', 123, 456, 5);
     * echo "Средняя оценка: {$averageRate}";
     * @endcode
     * @see PhotoRigma::Classes::Work_CoreLogic::process_rating() Метод из дочернего класса, реализующий основную логику.
     *
     */
    public function process_rating(string $table, int $photo_id, int $user_id, int $rate_value): float
    {
        return $this->core_logic->process_rating($table, $photo_id, $user_id, $rate_value);
    }

    /**
     * @brief Метод формирует массив данных для меню в зависимости от типа и активного пункта.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода create_menu() класса Work_Template.
     *          Он передает выполнение логики формирования данных для меню в метод create_menu() подкласса Work_Template.
     *          Поддерживаемые типы меню:
     *          - 0: Горизонтальное краткое меню.
     *          - 1: Вертикальное боковое меню.
     *          Для каждого пункта меню проверяются права доступа текущего пользователя (на основе свойств User).
     *          Если пункт меню видим, он добавляется в результат с очисткой данных через Work::clean_field().
     *
     * @callgraph
     *
     * @param string $action Активный пункт меню.
     *                       Указывается строка, соответствующая активному пункту меню (например, 'home', 'profile').
     * @param int $menu Тип меню:
     *                  - 0: Горизонтальное краткое меню.
     *                  - 1: Вертикальное боковое меню.
     *                  Другие значения недопустимы и приведут к выбросу исключения InvalidArgumentException.
     *
     * @return array Массив с данными для меню.
     *               Каждый элемент массива содержит:
     *               - Ключ 'url': URL пункта меню (null, если пункт активен).
     *               - Ключ 'name': Название пункта меню (локализованное через $lang['menu'] или дефолтное значение).
     *               Если меню пустое, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный $menu или $action.
     * @throws RuntimeException Если произошла ошибка при выполнении запроса к базе данных.
     *
     * @note Данные для меню берутся из таблицы TBL_MENU.
     *       Для получения дополнительной информации см. структуру таблицы.
     *
     * @warning Убедитесь, что передаваемые параметры корректны, так как это может привести к ошибкам.
     *          Также убедитесь, что права доступа пользователя (User) настроены правильно.
     *
     * Пример использования:
     * @code
     * // Пример использования метода create_menu()
     * $short_menu = $work->create_menu('home', 0); // Создание горизонтального меню
     * print_r($short_menu);
     *
     * $long_menu = $work->create_menu('profile', 1); // Создание вертикального меню
     * print_r($long_menu);
     * @endcode
     * @see PhotoRigma::Classes::Work::clean_field()
     *      Статический метод для очистки данных.
     *
     * @see PhotoRigma::Classes::Work_Template::create_menu()
     *      Публичный метод, который вызывает этот внутренний метод.
     */
    public function create_menu(string $action, int $menu): array
    {
        return $this->template->create_menu($action, $menu);
    }

    /**
     * @brief Формирование блока пользователя.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода template_user() класса Work_Template.
     *          Метод формирует массив данных для блока пользователя в зависимости от статуса авторизации:
     *          - Если пользователь не авторизован, формируется блок со ссылками на вход, восстановление пароля и регистрацию.
     *          - Если пользователь авторизован, формируется блок с приветствием, группой и аватаром.
     *          Для авторизованных пользователей проверяется существование аватара и его MIME-тип через Work::validate_mime_type().
     *          Если аватар недоступен или имеет недопустимый MIME-тип, используется дефолтный аватар (NO_USER_AVATAR).
     *
     * @callgraph
     *
     * @return array Массив с данными для блока пользователя:
     *               - Для неавторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока.
     *                 - 'L_LOGIN', 'L_PASSWORD', 'L_ENTER', 'L_FORGOT_PASSWORD', 'L_REGISTRATION': Локализованные строки.
     *                 - 'U_LOGIN', 'U_FORGOT_PASSWORD', 'U_REGISTRATION': URL для входа, восстановления пароля и регистрации.
     *               - Для авторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока.
     *                 - 'L_HI_USER': Приветствие с именем пользователя.
     *                 - 'L_GROUP': Группа пользователя.
     *                 - 'U_AVATAR': URL аватара (или дефолтного аватара, если файл недоступен или некорректен).
     *
     * @throws RuntimeException Если объект пользователя не установлен или данные некорректны.
     * @throws RandomException
     *
     * @warning Убедитесь, что объект пользователя (User) корректно установлен перед вызовом метода.
     *          Также убедитесь, что конфигурация аватаров ($work->config['avatar_folder']) настроена правильно.
     *
     * Пример использования:
     * @code
     * // Пример использования метода template_user()
     * $work = new Work($db, $config);
     * $work->set_user(new User());
     * $user_block = $work->template_user();
     * print_r($user_block);
     * @endcode
     * @see PhotoRigma::Classes::Work::validate_mime_type()
     *      Метод для проверки MIME-типа файла.
     *
     * @see PhotoRigma::Classes::Work_Template::template_user()
     *      Публичный метод, который вызывает этот внутренний метод.
     * @see PhotoRigma::Classes::Work::clean_field()
     *      Метод для очистки данных.
     */
    public function template_user(): array
    {
        return $this->template->template_user();
    }

    // Статические методы из под-класса Work_Helper.

    /**
     * @brief Генерирует массив статистических данных для шаблона.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода template_stat() класса Work_Template.
     *          Метод выполняет запросы к базе данных для получения статистической информации о пользователях,
     *          категориях, фотографиях, оценках и онлайн-пользователях. Результат формируется в виде ассоциативного массива,
     *          который используется для отображения статистики на странице.
     *
     * @callgraph
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
     *               - D_STAT_ONLINE: Список онлайн-пользователей (HTML-ссылки) или сообщение об отсутствии онлайн-пользователей.
     *
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных.
     *
     * @warning Убедитесь, что таблицы базы данных (TBL_USERS, TBL_CATEGORY, TBL_PHOTO, TBL_RATE_USER, TBL_RATE_MODER)
     *          содержат корректные данные. Ошибки в структуре таблиц могут привести к некорректной статистике.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $statData = $work->template_stat();
     * print_r($statData);
     * @endcode
     * @see PhotoRigma::Classes::Work_Template::template_stat()
     *      Публичный метод, который вызывает этот внутренний метод.
     * @see PhotoRigma::Classes::Work::clean_field()
     *      Метод для очистки данных.
     *
     */
    public function template_stat(): array
    {
        return $this->template->template_stat();
    }

    /**
     * @brief Формирует список пользователей, загрузивших наибольшее количество изображений.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода template_best_user() класса Work_Template.
     *          Метод выполняет запрос к базе данных с использованием JOIN для получения списка пользователей,
     *          которые загрузили наибольшее количество фотографий. Результат формируется в виде ассоциативного массива,
     *          который используется для отображения в шаблоне. Если данные отсутствуют, добавляется запись "пустого" пользователя.
     *
     * @callgraph
     *
     * @param int $best_user Количество лучших пользователей для вывода.
     *                       Должно быть положительным целым числом.
     *                       Если передано недопустимое значение, выбрасывается исключение InvalidArgumentException.
     *
     * @return array Массив данных для вывода в шаблон:
     *               - NAME_BLOCK: Название блока (локализованное через $this->lang['main']['best_user']).
     *               - L_USER_NAME: Подпись для имени пользователя (локализованная строка).
     *               - L_USER_PHOTO: Подпись для количества фотографий (локализованная строка).
     *               - user_url: Ссылка на профиль пользователя (null, если данных нет).
     *               - user_name: Имя пользователя ('---', если данных нет).
     *               - user_photo: Количество загруженных фотографий ('-', если данных нет).
     *
     * @throws InvalidArgumentException Если параметр $best_user не является положительным целым числом.
     *
     * @note Если запрос к базе данных не возвращает данных, добавляется запись "пустого" пользователя:
     *       - user_url: null
     *       - user_name: '---'
     *       - user_photo: '-'
     *
     * @warning Убедитесь, что таблицы базы данных (TBL_USERS и TBL_PHOTO) содержат корректные данные.
     *          Ошибки в структуре таблиц могут привести к некорректному результату.
     *
     * Пример использования:
     * @code
     * $work = new \PhotoRigma\Classes\Work();
     * $bestUsers = $work->template_best_user(5);
     * print_r($bestUsers);
     * @endcode
     * @see PhotoRigma::Classes::Work::clean_field()
     *      Метод для очистки данных.
     *
     * @see PhotoRigma::Classes::Work_Template::template_best_user()
     *      Публичный метод, который вызывает этот внутренний метод.
     */
    public function template_best_user(int $best_user = 1): array
    {
        return $this->template->template_best_user($best_user);
    }

    /**
     * @brief Вычисляет размеры для вывода эскиза изображения.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода size_image() класса Work_Image.
     *          Метод рассчитывает ширину и высоту эскиза на основе реальных размеров изображения
     *          и конфигурационных параметров (`temp_photo_w` и `temp_photo_h`). Если изображение меньше
     *          целевого размера, возвращаются оригинальные размеры. В противном случае размеры масштабируются
     *          пропорционально.
     *
     * @callgraph
     *
     * @param string $path_image Путь к файлу изображения.
     *                           Путь должен быть абсолютным, и файл должен существовать и быть доступным для чтения.
     *
     * @return array Массив с шириной и высотой эскиза:
     *               - 'width': int — Ширина эскиза (целое число ≥ 0).
     *               - 'height': int — Высота эскиза (целое число ≥ 0).
     *               Размеры могут совпадать с оригинальными размерами изображения,
     *               если оно меньше целевого размера.
     *
     * @throws RuntimeException Если файл не существует или не удалось получить размеры изображения.
     *
     * @warning Метод зависит от корректности данных в конфигурации (`temp_photo_w` и `temp_photo_h`).
     *          Если эти параметры некорректны, результат может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $thumbnail_size = $work->size_image('/path/to/image.jpg');
     * echo "Ширина: {$thumbnail_size['width']}, Высота: {$thumbnail_size['height']}";
     * @endcode
     * @see PhotoRigma::Classes::Work_Image::size_image()
     *      Публичный метод-редирект для вызова этой логики.
     *
     */
    public function size_image(string $path_image): array
    {
        return $this->image->size_image($path_image);
    }

    /**
     * @brief Изменяет размер изображения.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода image_resize() класса Work_Image.
     *          Метод изменяет размер исходного изображения и создаёт эскиз заданных размеров.
     *          Размеры эскиза рассчитываются на основе конфигурации (`temp_photo_w`, `temp_photo_h`)
     *          с использованием метода `calculate_thumbnail_size`. Если файл эскиза уже существует
     *          и его размеры совпадают с рассчитанными, метод завершает работу без изменений.
     *          В противном случае создаётся новый эскиз с использованием одной из доступных библиотек:
     *          GraphicsMagick, ImageMagick или GD (в порядке приоритета).
     *
     * @callgraph
     *
     * @param string $full_path Путь к исходному изображению.
     *                          Путь должен быть абсолютным, и файл должен существовать и быть доступным для чтения.
     * @param string $thumbnail_path Путь для сохранения эскиза.
     *                               Путь должен быть абсолютным, и директория должна быть доступна для записи.
     *
     * @return bool True, если операция выполнена успешно, иначе False.
     *
     * @throws InvalidArgumentException Если пути к файлам некорректны или имеют недопустимый формат.
     * @throws RuntimeException|Exception Если возникли ошибки при проверке файлов, директорий или размеров изображения.
     *
     * @warning Метод зависит от корректности данных в конфигурации (`temp_photo_w`, `temp_photo_h`).
     *          Если эти параметры некорректны, результат может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $result = $work->image_resize('/path/to/full_image.jpg', '/path/to/thumbnail.jpg');
     * if ($result) {
     *     echo "Эскиз успешно создан!";
     * } else {
     *     echo "Не удалось создать эскиз.";
     * }
     * @endcode
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     * @see PhotoRigma::Classes::Work_Image::image_resize()
     *      Публичный метод-редирект для вызова этой логики.
     */
    public function image_resize(string $full_path, string $thumbnail_path): bool
    {
        return $this->image->image_resize($full_path, $thumbnail_path);
    }

    /**
     * @brief Возвращает данные для отсутствующего изображения.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода no_photo() класса Work_Image.
     *          Метод формирует массив данных, который используется для представления информации
     *          об отсутствующем изображении. Это может быть полезно, например, если изображение не найдено
     *          или недоступно. Метод использует конфигурацию приложения (`site_url`) для формирования URL-адресов.
     *
     * @callgraph
     *
     * @return array Массив данных об изображении или его отсутствии:
     *               - `'url'` (string): URL полноразмерного изображения.
     *               - `'thumbnail_url'` (string): URL эскиза изображения.
     *               - `'name'` (string): Название изображения.
     *               - `'description'` (string): Описание изображения.
     *               - `'category_name'` (string): Название категории.
     *               - `'category_description'` (string): Описание категории.
     *               - `'rate'` (string): Рейтинг изображения.
     *               - `'url_user'` (string): URL пользователя (пустая строка '').
     *               - `'real_name'` (string): Имя пользователя.
     *               - `'full_path'` (string): Полный путь к изображению.
     *               - `'thumbnail_path'` (string): Полный путь к эскизу.
     *               - `'file'` (string): Имя файла.
     *               Значения по умолчанию используются для отсутствующих данных.
     *
     * @warning Метод зависит от корректности данных в конфигурации (`site_url`).
     *          Если этот параметр некорректен, результат может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $noPhotoData = $work->no_photo();
     * echo "URL изображения: {$noPhotoData['url']}\n";
     * echo "Описание: {$noPhotoData['description']}\n";
     * @endcode
     * @see PhotoRigma::Classes::Work_Image::no_photo()
     *      Публичный метод-редирект для вызова этой логики.
     *
     */
    public function no_photo(): array
    {
        return $this->image->no_photo();
    }

    /**
     * @brief Вывод изображения через HTTP.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода image_attach() класса Work_Image.
     *          Метод проверяет существование и доступность файла, определяет его MIME-тип
     *          и отправляет содержимое файла через HTTP. Если файл не найден или недоступен,
     *          возвращается HTTP-статус 404. Если возникли проблемы с чтением файла или определением
     *          MIME-типа, возвращается HTTP-статус 500. Метод завершает выполнение скрипта после отправки
     *          заголовков и содержимого файла.
     *
     * @callgraph
     *
     * @param string $full_path Полный путь к файлу.
     *                          Путь должен быть абсолютным, и файл должен существовать и быть доступным для чтения.
     * @param string $name_file Имя файла для заголовка Content-Disposition.
     *                          Имя должно быть корректным (например, без запрещённых символов).
     *
     * @return void Метод ничего не возвращает. Завершает выполнение скрипта после отправки заголовков и содержимого файла.
     *
     * @warning Метод завершает выполнение скрипта (`exit`), отправляя заголовки и содержимое файла.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $work->image_attach('/path/to/image.jpg', 'image.jpg');
     * @endcode
     * @throws Exception
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     * @see PhotoRigma::Classes::Work::clean_field()
     *      Публичный метод для очистки строк от HTML-тегов и специальных символов.
     *
     * @see PhotoRigma::Classes::Work_Image::image_attach()
     *      Публичный метод-редирект для вызова этой логики.
     */
    #[NoReturn] public function image_attach(string $full_path, string $name_file): void
    {
        $this->image->image_attach($full_path, $name_file);
    }

    // Внутренние методы класса Work.

    /**
     * @brief Корректировка расширения файла в соответствии с его MIME-типом.
     *
     * @details Этот метод является делегатом (метод-редирект) для метода fix_file_extension() класса Work_Image.
     *          Метод проверяет MIME-тип файла и корректирует его расширение на основе соответствия MIME-типу.
     *          Если у файла отсутствует расширение, оно добавляется автоматически. Если расширение уже корректное,
     *          файл остаётся без изменений.
     *
     * @callgraph
     *
     * @param string $full_path Полный путь к файлу.
     *                          Путь должен быть абсолютным, и файл должен существовать и быть доступным для чтения.
     *                          Формат пути должен соответствовать регулярному выражению /^[a-zA-Z0-9\/\.\-_]+$/.
     *
     * @return string Полный путь к файлу с правильным расширением.
     *                Если расширение было изменено или добавлено, возвращается новый путь.
     *                Если расширение уже корректное, возвращается исходный путь.
     *
     * @throws InvalidArgumentException Если путь к файлу некорректен, имеет недопустимый формат или файл не существует.
     * @throws RuntimeException Если MIME-тип файла не поддерживается или файл недоступен для чтения.
     * @throws Exception
     *
     * @warning Метод завершает выполнение с ошибкой, если MIME-тип файла не поддерживается.
     *          Убедитесь, что файл существует и доступен для чтения перед вызовом метода.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода через родительский класс Work
     * $work = new \PhotoRigma\Classes\Work();
     * $fixed_path = $work->fix_file_extension('/path/to/file');
     * echo "Исправленный путь: {$fixed_path}";
     * @endcode
     * @see PhotoRigma::Classes::Work_Image::fix_file_extension()
     *      Публичный метод, вызывающий этот защищённый метод.
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     */
    public function fix_file_extension(string $full_path): string
    {
        return $this->image->fix_file_extension($full_path);
    }

    /**
     * @brief Удаляет директорию и её содержимое через вызов метода из дочернего класса.
     *
     * @details Этот публичный метод является редиректом, который вызывает метод
     * `PhotoRigma::Classes::Work_Image::remove_directory()` из дочернего класса `Work_Image`.
     * Дополнительные проверки или преобразования данных перед вызовом дочернего метода отсутствуют.
     * Метод предназначен для использования через родительский класс `PhotoRigma::Classes::Work`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $path Путь к директории.
     *                     Должен быть строкой, указывающей на существующую директорию.
     *                     Директория должна быть доступна для записи.
     *
     * @return bool Возвращает `true`, если директория успешно удалена.
     *
     * @throws RuntimeException Выбрасывается исключение в следующих случаях:
     *                           - Если директория не существует.
     *                           - Если директория недоступна для записи.
     *                           - Если не удалось удалить файл внутри директории.
     *                           - Если не удалось удалить саму директорию.
     *
     * @note Метод рекурсивно удаляет все файлы внутри директории.
     *
     * @warning Используйте этот метод с осторожностью, так как удаление директории необратимо.
     *
     * Пример вызова метода через родительский класс:
     * @code
     * $object = new \PhotoRigma\Classes\Work();
     * $path = '/path/to/directory';
     * $result = $object->remove_directory($path);
     * if ($result) {
     *     echo "Директория успешно удалена!";
     * }
     * @endcode
     * @see PhotoRigma::Classes::Work_Image::remove_directory() Метод из дочернего класса, реализующий основную логику.
     *
     */
    public function remove_directory(string $path): bool
    {
        return $this->image->remove_directory($path);
    }

    /**
     * @brief Создает директории для категории и копирует файлы index.php через вызов метода из дочернего класса.
     *
     * @details Этот публичный метод является редиректом, который вызывает метод
     * `PhotoRigma::Classes::Work_Image::create_directory()` из дочернего класса `Work_Image`.
     * Дополнительные проверки или преобразования данных перед вызовом дочернего метода отсутствуют.
     * Метод предназначен для использования через родительский класс `PhotoRigma::Classes::Work`.
     *
     * @callgraph
     *
     * @param string $directory_name Имя директории.
     *                                Должен быть строкой, содержащей только допустимые символы для имён директорий.
     *                                Не должен содержать запрещённых символов (например, `\/:*?"<>|`).
     *
     * @return bool Возвращает `true`, если директории успешно созданы и файлы скопированы.
     *
     * @throws RuntimeException Выбрасывается исключение в следующих случаях:
     *                           - Если родительская директория недоступна для записи.
     *                           - Если не удалось создать директории.
     *                           - Если исходные файлы `index.php` не существуют.
     *                           - Если не удалось прочитать или записать файлы `index.php`.
     *
     * @note Метод использует конфигурационные параметры `site_dir`, `gallery_folder` и `thumbnail_folder`.
     *
     * @warning Используйте этот метод с осторожностью, так как он создаёт директории и изменяет файлы.
     *
     * Пример вызова метода через родительский класс:
     * @code
     * $object = new \PhotoRigma\Classes\Work();
     * $directoryName = 'new_category';
     * $result = $object->create_directory($directoryName);
     * if ($result) {
     *     echo "Директории успешно созданы!";
     * }
     * @endcode
     * @see PhotoRigma::Classes::Work_Image::create_directory() Метод из дочернего класса, реализующий основную логику.
     *
     */
    public function create_directory(string $directory_name): bool
    {
        return $this->image->create_directory($directory_name);
    }
}
