<?php

/**
 * @file        include/work_security.php
 * @brief       Файл содержит класс Work_Security, который отвечает за безопасность приложения.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-04-02
 * @namespace   PhotoRigma\\Classes
 *
 * @details     Этот файл содержит класс `Work_Security`, который реализует интерфейс `Work_Security_Interface`.
 *              Класс предоставляет методы для:
 *              - Проверки входных данных на наличие вредоносного кода.
 *              - Защиты от спам-ботов с использованием CAPTCHA.
 *              - Фильтрации email-адресов для затруднения автоматического парсинга ботами.
 *              - Универсальной проверки данных из различных источников ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES).
 *
 * @see         PhotoRigma::Classes::Work_Security_Interface Интерфейс для работы с безопасностью приложения.
 * @see         PhotoRigma::Classes::Work Класс, через который вызываются методы безопасности.
 * @see         PhotoRigma::Classes::Work::validate_mime_type() Метод для проверки MIME-типов загружаемых файлов.
 * @see         PhotoRigma::Include::log_in_file Функция для логирования ошибок.
 * @see         index.php Файл, который подключает work_security.php.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в обеспечении безопасности
 *              приложения.
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

// Предотвращение прямого вызова файла
use Exception;
use Random\RandomException;
use RuntimeException;

use function PhotoRigma\Include\log_in_file;

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
 * @interface Work_Security_Interface
 * @brief     Интерфейс для работы с безопасностью.
 *
 * @details   Этот интерфейс определяет методы, которые должны быть реализованы
 *          классами, отвечающими за безопасность приложения. Все методы вызываются через класс Work.
 *          Интерфейс охватывает такие задачи, как проверка входных данных, защита от спам-ботов,
 *          фильтрация email-адресов и другие аспекты безопасности.
 *
 * @callgraph
 *
 * @see       PhotoRigma::Classes::Work_Security Класс, реализующий данный интерфейс.
 * @see       PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
 * @see       PhotoRigma::Classes::Work::validate_mime_type() Метод для проверки MIME-типов загружаемых файлов.
 *
 * @warning   Не используйте классы, реализующие этот интерфейс, напрямую. Все вызовы должны выполняться через класс
 *            Work.
 *
 * Пример класса, реализующего интерфейс:
 * @code
 * class Work_Security implements \PhotoRigma\Classes\Work_Security_Interface {
 * }
 * @endcode
 */
interface Work_Security_Interface
{
    /**
     * @brief   Проверяет URL на наличие вредоносного кода.
     *
     * @details Метод проверяет строку запроса ($_SERVER['REQUEST_URI']) на наличие запрещённых паттернов,
     *          определяемых в массиве Work_Security::compiled_rules. Если формат URL некорректен или найден
     *          запрещённый паттерн, метод возвращает false.
     *
     * @callgraph
     *
     * @return bool True, если URL безопасен (не содержит запрещённых паттернов), иначе False.
     *
     * @note    Метод работает с глобальным массивом $_SERVER['REQUEST_URI'].
     *
     * @warning Метод зависит от корректности данных в свойстве Work_Security::compiled_rules.
     *          Если правила некорректны, результат может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Проверка безопасности URL
     * $security = new \PhotoRigma\Classes\Work_Security();
     * $is_safe = $security->url_check();
     * if ($is_safe) {
     *     echo "URL безопасен.";
     * } else {
     *     echo "URL содержит запрещённые паттерны.";
     * }
     * @endcode
     * @see     PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     * @see     PhotoRigma::Classes::Work_Security::url_check()
     *      Публичный метод, который вызывает этот внутренний метод.
     */
    public function url_check(): bool;

    /**
     * @brief   Универсальная проверка входных данных.
     *
     * @details Метод выполняет проверку данных из различных источников ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES)
     *          на соответствие заданным условиям. Поддерживаются следующие типы проверок:
     *          - Проверка наличия поля в источнике данных (параметр 'isset').
     *          - Проверка, что значение поля не пустое (параметр 'empty').
     *          - Проверка значения поля по регулярному выражению (параметр 'regexp').
     *          - Проверка, что значение поля не равно нулю (параметр 'not_zero').
     *          - Проверка размера файла для $_FILES (параметр 'max_size') и его MIME-типа.
     *
     *          Метод зависит от корректности данных в источниках ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES).
     *          Если источник данных повреждён или содержит некорректные значения, результат может быть
     *          непредсказуемым.
     *
     * @callgraph
     *
     * @param string $source_name Источник данных ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES).
     *                            Должен быть одним из допустимых значений: '_GET', '_POST', '_SESSION', '_COOKIE',
     *                            '_FILES'.
     * @param string $field       Поле для проверки (имя ключа в массиве источника данных).
     *                            Указывается имя ключа, которое необходимо проверить.
     * @param array  $options     Дополнительные параметры проверки:
     *                            - 'isset' (bool, опционально): Проверять наличие поля в источнике данных.
     *                            - 'empty' (bool, опционально): Проверять, что значение поля не пустое.
     *                            - 'regexp' (string|false, опционально): Регулярное выражение для проверки значения
     *                            поля.
     *                            - 'not_zero' (bool, опционально): Проверять, что значение поля не равно нулю.
     *                            - 'max_size' (int, опционально): Максимальный размер файла (в байтах) для $_FILES.
     *
     * @return bool True, если данные прошли проверку, иначе False.
     *              Для $_FILES также учитывается корректность MIME-типа и размера файла.
     *
     * @throws RuntimeException Если MIME-тип загруженного файла не поддерживается.
     *
     * Пример использования:
     * @code
     * // Проверка поля $_POST['username'] на наличие и непустое значение
     * $security = new \PhotoRigma\Classes\Work_Security();
     * $is_valid = $security->check_input('_POST', 'username', [
     *     'isset' => true,
     *     'empty' => true,
     * ]);
     * if ($is_valid) {
     *     echo "Поле username прошло проверку.";
     * } else {
     *     echo "Поле username не прошло проверку.";
     * }
     * @endcode
     * @see     PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     * @see     PhotoRigma::Classes::Work_Security::check_input()
     *      Публичный метод, который вызывает этот внутренний метод.
     * @see     PhotoRigma::Classes::Work::validate_mime_type()
     *      Метод для проверки поддерживаемых MIME-типов.
     */
    public function check_input(string $source_name, string $field, array $options = []): bool;

    /**
     * @brief   Проверяет содержимое поля на соответствие регулярному выражению или другим условиям.
     *
     * @details Метод выполняет следующие проверки:
     *          - Если задано регулярное выражение ($regexp), проверяет соответствие значения этому выражению.
     *            При этом также проверяется корректность самого регулярного выражения (например, отсутствие ошибок
     *            компиляции).
     *          - Если флаг $not_zero установлен, проверяет, что значение не является числом 0.
     *          - Проверяет, что значение не содержит запрещённых паттернов из Work_Security::compiled_rules.
     *
     *          Метод зависит от корректности данных в свойстве Work_Security::compiled_rules.
     *          Если правила некорректны, результат может быть непредсказуемым.
     *
     * @callgraph
     *
     * @param string       $field    Значение поля для проверки.
     *                               Указывается строковое значение, которое необходимо проверить.
     * @param string|false $regexp   Регулярное выражение (необязательно). Если задано, значение должно соответствовать
     *                               этому выражению. Если регулярное выражение некорректно (например, содержит ошибки
     *                               компиляции), метод завершает выполнение с ошибкой.
     * @param bool         $not_zero Флаг, указывающий, что значение не должно быть числом 0.
     *                               Если флаг установлен, а значение равно '0', проверка завершается с ошибкой.
     *
     * @return bool True, если поле прошло все проверки, иначе False.
     *              Проверки включают соответствие регулярному выражению, отсутствие запрещённых паттернов
     *              и выполнение условия $not_zero (если оно задано).
     *
     * Пример использования:
     * @code
     * // Проверка поля на соответствие регулярному выражению и условию not_zero
     * $security = new \PhotoRigma\Classes\Work_Security();
     * $field_value = "example123";
     * $regexp = "/^[a-z0-9]+$/i";
     * $is_valid = $security->check_field($field_value, $regexp, true);
     * if ($is_valid) {
     *     echo "Поле прошло проверку.";
     * } else {
     *     echo "Поле не прошло проверку.";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_Security::check_field()
     *      Публичный метод, который вызывает этот внутренний метод.
     * @see     PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     */
    public function check_field(string $field, string|false $regexp = false, bool $not_zero = false): bool;

    /**
     * @brief   Генерирует математический CAPTCHA-вопрос и ответ.
     *
     * @details Метод создаёт случайное математическое выражение (сложение или умножение)
     *          и возвращает его вместе с правильным ответом. Используется для защиты от спам-ботов.
     *          Выражение может включать комбинацию сложения и умножения с использованием скобок.
     *
     *          Метод использует функцию rand() для генерации случайных чисел.
     *          Если требуется криптографическая безопасность, следует заменить её на random_int().
     *
     * @callgraph
     *
     * @return array Массив с ключами 'question' и 'answer':
     *               - 'question': строка математического выражения (например, "2 x (3 + 4)").
     *               - 'answer': целочисленный результат вычисления (например, 14).
     *
     * Пример использования:
     * @code
     * // Генерация CAPTCHA-вопроса и ответа
     * $security = new \PhotoRigma\Classes\Work_Security();
     * $captcha = $security->gen_captcha();
     * echo "Вопрос: {$captcha['question']}\n";
     * echo "Ответ: {$captcha['answer']}\n";
     * @endcode
     * @see     PhotoRigma::Classes::Work_Security::gen_captcha()
     *      Публичный метод, который вызывает этот внутренний метод.
     *
     */
    public function gen_captcha(): array;

    /**
     * @brief   Заменяет символы в email-адресах для "обмана" ботов.
     *
     * @details Метод заменяет символы '@' и '.' на '[at]' и '[dot]', чтобы затруднить автоматический парсинг
     *          email-адресов ботами. Проверяет, что входной email не пустой и соответствует формату email. Если email
     *          некорректен или пуст, метод возвращает пустую строку.
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
     * // Создание экземпляра класса Work_Security
     * $security = new Work_Security();
     *
     * // Обработка email-адреса
     * $email = "example@example.com";
     * $filtered_email = $security->filt_email($email);
     * if (!empty($filtered_email)) {
     *     echo "Обработанный email: {$filtered_email}";
     * } else {
     *     echo "Email некорректен или пуст.";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_Security::filt_email() Публичный метод, который вызывает этот внутренний
     *          метод.
     * @see     PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
     *
     */
    public function filt_email(string $email): string;
}

/**
 * @class   Work_Security
 * @brief   Класс для обеспечения безопасности приложения.
 *
 * @details Этот класс предоставляет методы для проверки входных данных, защиты от спам-ботов,
 *          фильтрации email-адресов и других задач, связанных с безопасностью приложения.
 *          Реализует интерфейс Work_Security_Interface.
 *
 * @implements Work_Security_Interface Интерфейс для работы с безопасностью.
 *
 * @callergraph
 * @callgraph
 *
 * @see     PhotoRigma::Classes::Work Все методы этого класса вызываются через класс Work.
 * @see     Work_Security_Interface Интерфейс, который реализует класс.
 * @see     PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
 * @see     PhotoRigma::Classes::Work::validate_mime_type() Метод для проверки MIME-типов загружаемых файлов.
 *
 * @note    Этот класс реализует интерфейс Work_Security_Interface.
 * @warning Не используйте этот класс напрямую, все вызовы должны выполняться через класс Work.
 *
 * Пример использования класса:
 * @code
 * // Создание экземпляра класса Work_Security
 * $security = new Work_Security();
 *
 * // Проверка email-адреса
 * $email = "example@example.com";
 * $filtered_email = $security->filt_email($email);
 * if (!empty($filtered_email)) {
 *     echo "Обработанный email: {$filtered_email}";
 * } else {
 *     echo "Email некорректен или пуст.";
 * }
 * @endcode
 */
class Work_Security implements Work_Security_Interface
{
    // Свойства:
    private array $compiled_rules = []; ///< Предварительно скомпилированные правила защиты.

    /**
     * @brief   Конструктор класса.
     *
     * @details Инициализирует предварительно скомпилированные правила защиты из массива $array_rules.
     * Эти правила используются для проверки URL и входных данных на наличие вредоносных паттернов.
     * Этот класс является дочерним для PhotoRigma::Classes::Work.
     *
     * @callergraph
     * @callgraph
     *
     * @see     PhotoRigma::Classes::Work Родительский класс.
     * @see     PhotoRigma::Classes::Work_Security::$compiled_rules Свойство, содержащее предварительно
     *          скомпилированные правила защиты.
     *
     * @note    Правила компилируются с использованием preg_quote для экранирования специальных символов.
     *
     * @warning Правила защиты жёстко заданы в конструкторе. При изменении или расширении массива правил потребуется
     *          обновление кода. Использование preg_quote с флагом '/' делает правила чувствительными к символу '/',
     *          что может быть важно при их использовании.
     *
     * Пример создания объекта класса Work_Security:
     * @code
     * $security = new \PhotoRigma\Classes\Work_Security();
     *
     * // Пример проверки входных данных
     * $input = "SELECT * FROM users WHERE id = 1";
     * foreach ($security->compiled_rules as $rule) {
     *     if (preg_match($rule, $input)) {
     *         echo "Обнаружен вредоносный паттерн!";
     *         break;
     *     }
     * }
     * @endcode
     */
    public function __construct()
    {
        $array_rules = [
            // SQL-инъекции
            'http_',
            '_server',
            'delete%20',
            'delete ',
            'delete-',
            'delete(',
            '(delete',
            'drop%20',
            'drop ',
            'create%20',
            'update-',
            'update(',
            '(update',
            'insert-',
            'insert(',
            '(insert',
            'create ',
            'create(',
            'create-',
            '(create',
            'update%20',
            'update ',
            'insert%20',
            'insert ',
            'select%20',
            'select ',
            'bulk%20',
            'bulk ',
            'union%20',
            'union ',
            'select-',
            'select(',
            '(select',
            'union-',
            '(union',
            'union(',
            'or%20',
            'and%20',
            'exec',
            '@@',
            '%22',
            '"',
            'openquery',
            'openrowset',
            'msdasql',
            'sqloledb',
            'sysobjects',
            'syscolums',
            'syslogins',
            'sysxlogins',
            'char%20',
            'char ',
            'into%20',
            'into ',
            'load%20',
            'load ',
            'msys',
            'alert%20',
            'alert ',
            'eval%20',
            'eval ',

            // XSS-атаки
            'onkeyup',
            'x5cx',
            'fromcharcode',
            'javascript:',
            'vbscript:',
            'vbscript.',
            'http-equiv',
            '->',
            'expression%20',
            'expression ',
            'url%20',
            'url ',
            'innerhtml',
            'document.',
            'dynsrc',
            'jsessionid',
            'style%20',
            'style ',
            'phpsessid',
            '<applet',
            '<div',
            '<embed',
            '<iframe',
            '<img',
            '<meta',
            '<object',
            '<script',
            '<textarea',
            'script:',
            'data:',
            'base64,',
            'srcdoc',
            'onerror',
            'onload',
            'onclick',
            'onmouseover',
            'onmouseout',
            'onfocus',
            'onblur',
            'onsubmit',
            'onreset',
            'onchange',
            'oninput',
            'oninvalid',
            'onselect',
            'ondrag',
            'ondrop',
            'oncopy',
            'oncut',
            'onpaste',
            'oncontextmenu',
            'onresize',
            'onscroll',
            'ontoggle',
            'onanimationstart',
            'onanimationend',
            'ontransitionend',
            'onbeforeunload',
            'onhashchange',
            'onmessage',
            'onoffline',
            'ononline',
            'onpagehide',
            'onpageshow',
            'onpopstate',
            'onstorage',
            'onunhandledrejection',
            'onwheel',
            'onabort',
            'oncanplay',
            'oncanplaythrough',
            'ondurationchange',
            'onemptied',
            'onended',
            'onloadeddata',
            'onloadedmetadata',
            'onpause',
            'onplay',
            'onplaying',
            'onprogress',
            'onratechange',
            'onseeked',
            'onseeking',
            'onstalled',
            'onsuspend',
            'ontimeupdate',
            'onvolumechange',
            'onwaiting',

            // Редкие угрозы
            'ldap://',
            'gopher://',
            'file://',
            'phar://',
            'zlib://',
            'compress://',
            'base64_decode',
            'passthru',
            'shell_exec',
            'proc_open',
            'popen',
            'pcntl_exec',
            'window.open',
            'img src',

            // Дополнительные кодировки и фрагменты
            '%3C',
            '%3E',
            '%27',
            '%22',
            '%00',
            rawurldecode('%00'),
        ];

        foreach ($array_rules as $rule) {
            $this->compiled_rules[] = '/' . preg_quote($rule, '/') . '/i';
        }
    }

    /**
     * @brief   Проверяет содержимое поля на соответствие регулярному выражению или другим условиям.
     *
     * @details Этот метод является публичным редиректом на защищённый метод `_check_field_internal`.
     *          Метод выполняет следующие проверки:
     *          - Если задано регулярное выражение ($regexp), проверяет соответствие значения этому выражению.
     *            При этом также проверяется корректность самого регулярного выражения (например, отсутствие ошибок
     *            компиляции).
     *          - Если флаг $not_zero установлен, проверяет, что значение не является числом 0.
     *          - Проверяет, что значение не содержит запрещённых паттернов из $this->compiled_rules.
     *
     * @callergraph
     * @callgraph
     *
     * @param string       $field    Значение поля для проверки.
     *                               Указывается строковое значение, которое необходимо проверить.
     * @param string|false $regexp   Регулярное выражение (необязательно). Если задано, значение должно соответствовать
     *                               этому выражению. Если регулярное выражение некорректно (например, содержит ошибки
     *                               компиляции), метод завершает выполнение с ошибкой.
     * @param bool         $not_zero Флаг, указывающий, что значение не должно быть числом 0.
     *                               Если флаг установлен, а значение равно '0', проверка завершается с ошибкой.
     *
     * @return bool True, если поле прошло все проверки, иначе False.
     *              Проверки включают соответствие регулярному выражению, отсутствие запрещённых паттернов
     *              и выполнение условия $not_zero (если оно задано).
     *
     * @warning Метод зависит от корректности данных в свойстве $this->compiled_rules.
     *          Если правила некорректны, результат может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Security
     * $security = new Work_Security();
     *
     * // Проверка поля на соответствие регулярному выражению и условию not_zero
     * $field_value = "example123";
     * $regexp = "/^[a-z0-9]+$/i";
     * $is_valid = $security->check_field($field_value, $regexp, true);
     * if ($is_valid) {
     *     echo "Поле прошло проверку.";
     * } else {
     *     echo "Поле не прошло проверку.";
     * }
     * @endcode
     * @throws Exception
     * @see     PhotoRigma::Classes::Work::check_field() Этот метод вызывается через класс Work.
     * @see     PhotoRigma::Classes::Work_Security::$compiled_rules Свойство, содержащее массив скомпилированных правил
     *          для проверки.
     * @see     PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
     *
     * @see     PhotoRigma::Classes::Work_Security::_check_field_internal() Защищённый метод, реализующий основную
     *          логику.
     */
    public function check_field(string $field, string|false $regexp = false, bool $not_zero = false): bool
    {
        return $this->_check_field_internal($field, $regexp, $not_zero);
    }

    /**
     * @brief   Универсальная проверка входных данных.
     *
     * @details Этот метод является публичным редиректом на защищённый метод `_check_input_internal`.
     *          Метод выполняет проверку данных из различных источников ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES)
     *          на соответствие заданным условиям. Поддерживаются следующие типы проверок:
     *          - Проверка наличия поля в источнике данных (параметр 'isset').
     *          - Проверка, что значение поля не пустое (параметр 'empty').
     *          - Проверка значения поля по регулярному выражению (параметр 'regexp').
     *          - Проверка, что значение поля не равно нулю (параметр 'not_zero').
     *          - Проверка размера файла для $_FILES (параметр 'max_size') и его MIME-типа.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $source_name Источник данных ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES).
     *                            Должен быть одним из допустимых значений: '_GET', '_POST', '_SESSION', '_COOKIE',
     *                            '_FILES'.
     * @param string $field       Поле для проверки (имя ключа в массиве источника данных).
     *                            Указывается имя ключа, которое необходимо проверить.
     * @param array  $options     Дополнительные параметры проверки. Массив может содержать следующие ключи:
     *                            - 'isset' (bool, опционально): Проверять наличие поля в источнике данных.
     *                            - 'empty' (bool, опционально): Проверять, что значение поля не пустое.
     *                            - 'regexp' (string|false, опционально): Регулярное выражение для проверки значения
     *                            поля.
     *                            - 'not_zero' (bool, опционально): Проверять, что значение поля не равно нулю.
     *                            - 'max_size' (int, опционально): Максимальный размер файла (в байтах) для $_FILES.
     *
     * @return bool True, если данные прошли проверку, иначе False.
     *              Для $_FILES также учитывается корректность MIME-типа и размера файла.
     *
     * @throws RuntimeException Если MIME-тип загруженного файла не поддерживается.
     * @throws Exception
     *
     * @warning Метод зависит от корректности данных в источниках ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES).
     *          Если источник данных повреждён или содержит некорректные значения, результат может быть
     *          непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Security
     * $security = new Work_Security();
     *
     * // Проверка поля $_POST['username'] на наличие и непустое значение
     * $is_valid = $security->check_input('_POST', 'username', [
     *     'isset' => true,
     *     'empty' => false,
     * ]);
     * if ($is_valid) {
     *     echo "Поле username прошло проверку.";
     * } else {
     *     echo "Поле username не прошло проверку.";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work::check_input() Этот метод вызывается через класс Work.
     * @see     PhotoRigma::Classes::Work::validate_mime_type() Метод для проверки поддерживаемых MIME-типов.
     * @see     PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
     *
     * @see     PhotoRigma::Classes::Work_Security::_check_input_internal() Защищённый метод, реализующий основную
     *          логику.
     */
    public function check_input(string $source_name, string $field, array $options = []): bool
    {
        return $this->_check_input_internal($source_name, $field, $options);
    }

    /**
     * @brief   Внутренний метод для универсальной проверки входных данных.
     *
     * @details Метод выполняет проверку данных из различных источников ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES)
     *          на соответствие заданным условиям. Поддерживаются следующие типы проверок:
     *          - Проверка наличия поля в источнике данных (параметр 'isset').
     *          - Проверка, что значение поля не пустое (параметр 'empty').
     *          - Проверка значения поля по регулярному выражению (параметр 'regexp').
     *          - Проверка, что значение поля не равно нулю (параметр 'not_zero').
     *          - Проверка размера файла для $_FILES (параметр 'max_size') и его MIME-типа.
     *          Метод является защищённым и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод check_input().
     *
     * @callergraph
     * @callgraph
     *
     * @param string $source_name Источник данных ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES).
     *                            Должен быть одним из допустимых значений: '_GET', '_POST', '_SESSION', '_COOKIE',
     *                            '_FILES'.
     * @param string $field       Поле для проверки (имя ключа в массиве источника данных).
     *                            Указывается имя ключа, которое необходимо проверить.
     * @param array  $options     Дополнительные параметры проверки:
     *                            - Ключ 'isset' (bool, опционально): Проверять наличие поля в источнике данных.
     *                            - Ключ 'empty' (bool, опционально): Проверять, что значение поля не пустое.
     *                            - Ключ 'regexp' (string|false, опционально): Регулярное выражение для проверки
     *                            значения поля.
     *                            - Ключ 'not_zero' (bool, опционально): Проверять, что значение поля не равно нулю.
     *                            - Ключ 'max_size' (int, опционально): Максимальный размер файла (в байтах) для
     *                            $_FILES.
     *
     * @return bool True, если данные прошли проверку, иначе False.
     *              Для $_FILES также учитывается корректность MIME-типа и размера файла.
     *
     * @throws RuntimeException Если MIME-тип загруженного файла не поддерживается.
     * @throws Exception
     *
     * @warning Метод зависит от корректности данных в источниках ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES).
     *          Если источник данных повреждён или содержит некорректные значения, результат может быть
     *          непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Проверка поля $_POST['username'] на наличие и непустое значение
     * $is_valid = $this->_check_input_internal('_POST', 'username', [
     *     'isset' => true,
     *     'empty' => true,
     * ]);
     * if ($is_valid) {
     *     echo "Поле username прошло проверку.";
     * } else {
     *     echo "Поле username не прошло проверку.";
     * }
     * @endcode
     * @see     PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     * @see     PhotoRigma::Classes::Work::validate_mime_type()
     *      Метод для проверки поддерживаемых MIME-типов.
     * @see     PhotoRigma::Classes::Work_Security::_check_field_internal()
     *      Защищённый метод, используемый для дополнительной проверки поля.
     *
     * @see     PhotoRigma::Classes::Work_Security::check_input()
     *      Публичный метод, который вызывает этот внутренний метод.
     */
    protected function _check_input_internal(string $source_name, string $field, array $options = []): bool
    {
        $allowed_sources = ['_GET', '_POST', '_SESSION', '_COOKIE', '_FILES'];
        if (!in_array($source_name, $allowed_sources, true)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый источник данных для check_input | Получено: $source_name"
            );
            return false;
        }
        $source = match ($source_name) {
            '_GET'     => $_GET,
            '_POST'    => $_POST,
            '_SESSION' => $_SESSION,
            '_COOKIE'  => $_COOKIE,
            '_FILES'   => $_FILES,
            default    => null,
        };
        if (!is_array($source)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Источник данных не является массивом | Источник: $source_name"
            );
            return false;
        }
        if (($options['isset'] ?? false) && !isset($source[$field])) {
            return false;
        }
        if (($options['empty'] ?? false) && empty($source[$field])) {
            return false;
        }
        if ($source_name === '_FILES') {
            if (!isset($source[$field])) {
                return false;
            }
            $file = $source[$field];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка загрузки файла для поля | Поле: $field"
                );
                return false;
            }
            $max_size = $options['max_size'] ?? 0;
            if ($max_size > 0 && $file['size'] > $max_size) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Размер файла превышает максимально допустимый размер | Поле: $field, Размер: {$file['size']}, Максимальный размер: $max_size"
                );
                return false;
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $real_mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!Work::validate_mime_type($real_mime_type)) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый MIME-тип для загруженного файла | Поле: $field, MIME-тип: $real_mime_type"
                );
            }
            return true;
        }
        return $this->_check_field_internal(
            $source[$field] ?? null,
            $options['regexp'] ?? false,
            $options['not_zero'] ?? false
        );
    }

    /**
     * @brief   Внутренний метод для проверки содержимого поля на соответствие регулярному выражению или другим
     *          условиям.
     *
     * @details Метод выполняет следующие проверки:
     *          - Если задано регулярное выражение ($regexp), проверяет соответствие значения этому выражению.
     *            При этом также проверяется корректность самого регулярного выражения (например, отсутствие ошибок
     *            компиляции).
     *          - Если флаг $not_zero установлен, проверяет, что значение не является числом 0.
     *          - Проверяет, что значение не содержит запрещённых паттернов из $this->compiled_rules.
     *          Метод является защищённым и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод check_field().
     *
     * @callergraph
     * @callgraph
     *
     * @param string       $field    Значение поля для проверки.
     *                               Указывается строковое значение, которое необходимо проверить.
     * @param string|false $regexp   Регулярное выражение (необязательно). Если задано, значение должно соответствовать
     *                               этому выражению. Если регулярное выражение некорректно (например, содержит ошибки
     *                               компиляции), метод завершает выполнение с ошибкой.
     * @param bool         $not_zero Флаг, указывающий, что значение не должно быть числом 0.
     *                               Если флаг установлен, а значение равно '0', проверка завершается с ошибкой.
     *
     * @return bool True, если поле прошло все проверки, иначе False.
     *              Проверки включают соответствие регулярному выражению, отсутствие запрещённых паттернов
     *              и выполнение условия $not_zero (если оно задано).
     *
     * @warning Метод зависит от корректности данных в свойстве $this->compiled_rules.
     *          Если правила некорректны, результат может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Проверка поля на соответствие регулярному выражению и условию not_zero
     * $field_value = "example123";
     * $regexp = "/^[a-z0-9]+$/i";
     * $is_valid = $this->_check_field_internal($field_value, $regexp, true);
     * if ($is_valid) {
     *     echo "Поле прошло проверку.";
     * } else {
     *     echo "Поле не прошло проверку.";
     * }
     * @endcode
     * @throws Exception
     * @see     PhotoRigma::Classes::Work_Security::$compiled_rules
     *      Свойство, содержащее массив скомпилированных правил для проверки.
     *
     * @see     PhotoRigma::Classes::Work_Security::check_field()
     *      Публичный метод, который вызывает этот внутренний метод.
     * @see     PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     */
    protected function _check_field_internal(string $field, string|false $regexp = false, bool $not_zero = false): bool
    {
        if ($regexp !== false) {
            $is_valid_regexp = preg_match($regexp, '') !== false;
            if (!$is_valid_regexp) {
                $errorMessage = match (preg_last_error()) {
                    PREG_INTERNAL_ERROR        => "Внутренняя ошибка регулярного выражения.",
                    PREG_BACKTRACK_LIMIT_ERROR => "Превышен лимит обратного отслеживания.",
                    PREG_RECURSION_LIMIT_ERROR => "Превышен лимит рекурсии.",
                    PREG_BAD_UTF8_ERROR        => "Некорректная UTF-8 строка.",
                    PREG_BAD_UTF8_OFFSET_ERROR => "Некорректный смещение UTF-8.",
                    default                    => "Неизвестная ошибка регулярного выражения.",
                };
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка в регулярном выражении | Регулярное выражение: $regexp, Причина: $errorMessage"
                );
                return false;
            }
            if (!preg_match($regexp, $field)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Значение поля не соответствует регулярному выражению | Поле: '$field', Регулярное выражение: $regexp"
                );
                return false;
            }
        }
        if ($not_zero && $field === '0') {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Значение поля равно 0, но флаг not_zero установлен | Поле: '$field'"
            );
            return false;
        }
        foreach ($this->compiled_rules as $rule) {
            if (preg_match($rule, $field)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Значение поля содержит запрещённый паттерн | Поле: '$field', Паттерн: $rule"
                );
                return false;
            }
        }
        return true;
    }

    /**
     * @brief   Заменяет символы в email-адресах для "обмана" ботов.
     *
     * @details Этот метод является публичным редиректом на защищённый метод `_filt_email_internal`.
     *          Метод заменяет символы '@' и '.' на '[at]' и '[dot]', чтобы затруднить автоматический парсинг
     *          email-адресов ботами. Проверяет, что входной email не пустой и соответствует формату email. Если email
     *          некорректен или пуст, метод возвращает пустую строку.
     *
     * @callergraph
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
     * // Создание экземпляра класса Work_Security
     * $security = new Work_Security();
     *
     * // Обработка email-адреса
     * $email = "example@example.com";
     * $filtered_email = $security->filt_email($email);
     * if (!empty($filtered_email)) {
     *     echo "Обработанный email: {$filtered_email}";
     * } else {
     *     echo "Email некорректен или пуст.";
     * }
     * @endcode
     * @throws Exception
     * @see     PhotoRigma::Classes::Work_Security::_filt_email_internal() Защищённый метод, реализующий основную
     *          логику.
     * @see     PhotoRigma::Classes::Work::filt_email() Этот метод вызывается через класс Work.
     * @see     PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
     *
     */
    public function filt_email(string $email): string
    {
        return $this->_filt_email_internal($email);
    }

    /**
     * @brief   Внутренний метод для замены символов в email-адресах.
     *
     * @details Метод заменяет символы '@' и '.' на '[at]' и '[dot]', чтобы затруднить автоматический парсинг
     *          email-адресов ботами. Проверяет, что входной email не пустой и соответствует формату email. Если email
     *          некорректен или пуст, метод возвращает пустую строку. Метод является защищённым и предназначен для
     *          использования внутри класса или его наследников. Основная логика вызывается через публичный метод
     *          filt_email().
     *
     * @callergraph
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
     * // Обработка email-адреса
     * $email = "example@example.com";
     * $filtered_email = $this->_filt_email_internal($email);
     * if (!empty($filtered_email)) {
     *     echo "Обработанный email: {$filtered_email}";
     * } else {
     *     echo "Email некорректен или пуст.";
     * }
     * @endcode
     * @throws Exception
     * @see     PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     * @see     PhotoRigma::Classes::Work_Security::filt_email()
     *      Публичный метод, который вызывает этот внутренний метод.
     */
    protected function _filt_email_internal(string $email): string
    {
        if (empty($email)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Пустой email-адрес передан для фильтрации"
            );
            return '';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный email-адрес | Получено: $email"
            );
            return '';
        }
        return str_replace(['@', '.'], ['[at]', '[dot]'], $email);
    }

    /**
     * @brief   Генерирует математический CAPTCHA-вопрос и ответ.
     *
     * @details Этот метод является публичным редиректом на защищённый метод `_gen_captcha_internal`.
     *          Метод создаёт случайное математическое выражение (сложение или умножение)
     *          и возвращает его вместе с правильным ответом. Используется для защиты от спам-ботов.
     *          Выражение может включать комбинацию сложения и умножения с использованием скобок.
     *
     * @callergraph
     * @callgraph
     *
     * @return array Массив с ключами 'question' и 'answer'.
     *               - Ключ 'question' содержит строку математического выражения (например, "2 x (3 + 4)").
     *               - Ключ 'answer' содержит целочисленный результат вычисления (например, 14).
     *
     * @warning Метод использует функцию rand() для генерации случайных чисел.
     *          Если требуется криптографическая безопасность, следует заменить её на random_int().
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Security
     * $security = new Work_Security();
     *
     * // Генерация CAPTCHA-вопроса и ответа
     * $captcha = $security->gen_captcha();
     * echo "Вопрос: {$captcha['question']}\n";
     * echo "Ответ: {$captcha['answer']}\n";
     * // Пример вывода:
     * // Вопрос: 2 x (3 + 4)
     * // Ответ: 14
     * @endcode
     * @throws RandomException
     * @see     PhotoRigma::Classes::Work_Security::_gen_captcha_internal() Защищённый метод, реализующий основную
     *          логику.
     * @see     PhotoRigma::Classes::Work::gen_captcha() Этот метод вызывается через класс Work.
     *
     */
    public function gen_captcha(): array
    {
        return $this->_gen_captcha_internal();
    }

    /**
     * @brief   Внутренний метод для генерации математического CAPTCHA-вопроса и ответа.
     *
     * @details Метод создаёт случайное математическое выражение (сложение или умножение)
     *          и возвращает его вместе с правильным ответом. Используется для защиты от спам-ботов.
     *          Выражение может включать комбинацию сложения и умножения с использованием скобок.
     *          Метод является защищённым и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод gen_captcha().
     *
     * @callergraph
     *
     * @return array Массив с ключами 'question' и 'answer'.
     *               - Ключ 'question' содержит строку математического выражения (например, "2 x (3 + 4)").
     *               - Ключ 'answer' содержит целочисленный результат вычисления (например, 14).
     *
     * @warning Метод использует функцию rand() для генерации случайных чисел.
     *          Если требуется криптографическая безопасность, следует заменить её на random_int().
     *
     * Пример использования:
     * @code
     * // Генерация CAPTCHA-вопроса и ответа
     * $captcha = $this->_gen_captcha_internal();
     * echo "Вопрос: {$captcha['question']}\n";
     * echo "Ответ: {$captcha['answer']}\n";
     * @endcode
     * @throws RandomException
     * @see     PhotoRigma::Classes::Work_Security::gen_captcha()
     *      Публичный метод, который вызывает этот внутренний метод.
     *
     */
    protected function _gen_captcha_internal(): array
    {
        $num1 = random_int(1, 9);
        $num2 = random_int(1, 9);
        $num3 = random_int(1, 9);

        $operation1 = random_int(1, 2); // 1 - умножение, 2 - сложение
        $operation2 = random_int(1, 2); // 1 - умножение, 2 - сложение

        if ($operation2 === 1) {
            $captcha['question'] = "($num2 x $num3)";
            $captcha['answer'] = $num2 * $num3;
        } else {
            $captcha['question'] = "($num2 + $num3)";
            $captcha['answer'] = $num2 + $num3;
        }

        if ($operation1 === 1) {
            $captcha['question'] = "$num1 x {$captcha['question']}";
            $captcha['answer'] = $num1 * $captcha['answer'];
        } else {
            $captcha['question'] = "$num1 + {$captcha['question']}";
            $captcha['answer'] = $num1 + $captcha['answer'];
        }

        return $captcha;
    }

    /**
     * @brief   Проверяет URL на наличие вредоносного кода.
     *
     * @details Этот метод является публичным редиректом на защищённый метод `_url_check_internal`.
     *          Метод проверяет строку запроса ($_SERVER['REQUEST_URI']) на наличие запрещённых паттернов,
     *          определяемых в массиве `$compiled_rules`. Если формат URL некорректен или найден запрещённый паттерн,
     *          метод возвращает false.
     *
     * @callergraph
     * @callgraph
     *
     * @return bool True, если URL безопасен (не содержит запрещённых паттернов), иначе False.
     *
     * @warning Метод зависит от корректности данных в свойстве $this->compiled_rules.
     *          Если правила некорректны, результат может быть непредсказуемым.
     *
     * @note    Метод работает с глобальным массивом $_SERVER['REQUEST_URI'].
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Security
     * $security = new Work_Security();
     *
     * // Проверка безопасности URL
     * if ($security->url_check()) {
     *     echo "URL безопасен.";
     * } else {
     *     echo "URL содержит запрещённые паттерны.";
     * }
     * @endcode
     * @throws Exception
     * @see     PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
     * @see     PhotoRigma::Classes::Work_Security::$compiled_rules Свойство, содержащее массив скомпилированных правил.
     *
     * @see     PhotoRigma::Classes::Work_Security::_url_check_internal() Защищённый метод, реализующий основную логику.
     * @see     PhotoRigma::Classes::Work::url_check() Этот метод вызывается через класс Work.
     */
    public function url_check(): bool
    {
        return $this->_url_check_internal();
    }

    /**
     * @brief   Внутренний метод для проверки URL на наличие вредоносного кода.
     *
     * @details Метод проверяет строку запроса ($_SERVER['REQUEST_URI']) на наличие запрещённых паттернов,
     *          определяемых в массиве $this->compiled_rules. Если формат URL некорректен или найден запрещённый
     *          паттерн, метод возвращает false. Этот метод является защищённым и предназначен для использования внутри
     *          класса или его наследников. Основная логика вызывается через публичный метод url_check().
     *
     * @callergraph
     * @callgraph
     *
     * @return bool True, если URL безопасен (не содержит запрещённых паттернов), иначе False.
     *
     * @warning Метод зависит от корректности данных в свойстве $this->compiled_rules.
     *          Если правила некорректны, результат может быть непредсказуемым.
     *
     * @note    Метод работает с глобальным массивом $_SERVER['REQUEST_URI'].
     *
     * Пример использования:
     * @code
     * // Проверка безопасности URL
     * $is_safe = $this->_url_check_internal();
     * if ($is_safe) {
     *     echo "URL безопасен.";
     * } else {
     *     echo "URL содержит запрещённые паттерны.";
     * }
     * @endcode
     * @throws Exception
     * @see     PhotoRigma::Classes::Work_Security::$compiled_rules
     *      Свойство, содержащее массив скомпилированных правил для проверки.
     *
     * @see     PhotoRigma::Classes::Work_Security::url_check()
     *      Публичный метод, который вызывает этот внутренний метод.
     * @see     PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     */
    protected function _url_check_internal(): bool
    {
        $request_uri = strtolower($_SERVER['QUERY_STRING']);
        foreach ($this->compiled_rules as $rule) {
            if (preg_match($rule, $request_uri)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома: найден запрещённый паттерн | Паттерн: $rule, Запрос: $request_uri"
                );
                return false;
            }
        }
        return true;
    }
}
