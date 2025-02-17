<?php

/**
 * @file        include/work_security.php
 * @brief       Файл содержит класс Work_Security, который отвечает за безопасность приложения.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-02-11
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
 * @see         index.php Файл, который подключает work_security.php.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в обеспечении безопасности приложения.
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

/**
 * @brief Интерфейс для работы с безопасностью.
 *
 * @details Этот интерфейс определяет методы, которые должны быть реализованы
 * классами, отвечающими за безопасность приложения. Все методы вызываются через класс Work.
 */
interface Work_Security_Interface
{
    /**
     * @brief Проверяет URL на наличие вредоносного кода.
     *
     * @details Метод проверяет строку запроса ($_SERVER['REQUEST_URI']) на наличие запрещённых паттернов.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * if (!$work->url_check()) {
     *     echo "Обнаружен подозрительный URL!";
     * }
     * @endcode
     *
     * @see PhotoRigma::Classes::Work::url_check() Этот метод вызывается через класс Work.
     *
     * @return bool True, если URL безопасен, иначе False.
     */
    public function url_check(): bool;

    /**
     * @brief Универсальная проверка входных данных.
     *
     * @details Метод выполняет проверку данных из различных источников ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES)
     * на соответствие заданным условиям.
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
     * @see PhotoRigma::Classes::Work::check_input() Этот метод вызывается через класс Work.
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
    public function check_input(string $source_name, string $field, array $options = []): bool;

    /**
     * @brief Проверяет содержимое поля на соответствие регулярному выражению или другим условиям.
     *
     * @details Метод выполняет следующие проверки:
     * - Если задано регулярное выражение ($regexp), проверяет соответствие значения этому выражению.
     * - Если флаг $not_zero установлен, проверяет, что значение не является числом 0.
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
     * @see PhotoRigma::Classes::Work::check_field() Этот метод вызывается через класс Work.
     *
     * @param string $field Значение поля для проверки.
     * @param string|false $regexp Регулярное выражение (необязательно). Если задано, значение должно соответствовать этому выражению.
     * @param bool $not_zero Флаг, указывающий, что значение не должно быть числом 0.
     *
     * @return bool True, если поле прошло все проверки, иначе False.
     */
    public function check_field(string $field, string|false $regexp = false, bool $not_zero = false): bool;

    /**
     * @brief Генерирует математический CAPTCHA-вопрос и ответ.
     *
     * @details Метод создаёт случайное математическое выражение (сложение или умножение)
     * и возвращает его вместе с правильным ответом. Используется для защиты от спам-ботов.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $captcha = $work->gen_captcha();
     * echo "Вопрос: {$captcha['question']}, Ответ: {$captcha['answer']}";
     * // Пример вывода: Вопрос: 2 x (3 + 4), Ответ: 14
     * @endcode
     *
     * @see PhotoRigma::Classes::Work::gen_captcha() Этот метод вызывается через класс Work.
     *
     * @return array{
     *     question: string, // Математическое выражение (например, "2 x (3 + 4)")
     *     answer: int       // Числовой ответ на выражение (например, 14)
     * } Массив с ключами 'question' и 'answer'.
     */
    public function gen_captcha(): array;

    /**
     * @brief Заменяет символы в email-адресах для "обмана" ботов.
     *
     * @details Метод заменяет символы '@' и '.' на '[at]' и '[dot]', чтобы затруднить автоматический парсинг email-адресов ботами.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $filteredEmail = $work->filt_email('example@example.com');
     * echo $filteredEmail; // Выведет: example[at]example[dot]com
     * @endcode
     *
     * @see PhotoRigma::Classes::Work::filt_email() Этот метод вызывается через класс Work.
     *
     * @param string $email Email-адрес для обработки.
     *
     * @return string Обработанный email-адрес.
     */
    public function filt_email(string $email): string;
}

/**
 * @brief Класс для обеспечения безопасности приложения.
 *
 * @details Этот класс предоставляет методы для проверки входных данных, защиты от спам-ботов,
 * фильтрации email-адресов и других задач, связанных с безопасностью приложения.
 *
 * @see PhotoRigma::Classes::Work Все методы этого класса вызываются через класс Work.
 *
 * @note Этот класс реализует интерфейс Work_Security_Interface.
 * @warning Не используйте этот класс напрямую, все вызовы должны выполняться через класс Work.
 */
class Work_Security implements Work_Security_Interface
{
    // Свойства:
    private array $compiled_rules = []; ///< Предварительно скомпилированные правила защиты.

    /**
     * @brief Конструктор класса.
     *
     * @details Инициализирует предварительно скомпилированные правила защиты из массива $array_rules.
     * Эти правила используются для проверки URL и входных данных на наличие вредоносных паттернов.
     * Этот класс является дочерним для PhotoRigma::Classes::Work.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work Родительский класс.
     * @see PhotoRigma::Classes::Work_Security::$compiled_rules Свойство, содержащее предварительно скомпилированные правила защиты.
     *
     * @note Правила компилируются с использованием preg_quote для экранирования специальных символов.
     *
     * @warning Правила защиты жёстко заданы в конструкторе. При изменении или расширении массива правил потребуется обновление кода.
     *          Использование preg_quote с флагом '/' делает правила чувствительными к символу '/', что может быть важно при их использовании.
     *
     * @example PhotoRigma::Classes::Work_Security::__construct
     * @code
     * // Пример создания объекта класса Work_Security
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
            'http_', '_server', 'delete%20', 'delete ', 'delete-', 'delete(', '(delete',
            'drop%20', 'drop ', 'create%20', 'update-', 'update(', '(update',
            'insert-', 'insert(', '(insert', 'create ', 'create(', 'create-', '(create',
            'update%20', 'update ', 'insert%20', 'insert ', 'select%20', 'select ',
            'bulk%20', 'bulk ', 'union%20', 'union ', 'select-', 'select(', '(select',
            'union-', '(union', 'union(', 'or%20', 'and%20', 'exec', '@@',
            '%22', '"', 'openquery', 'openrowset', 'msdasql', 'sqloledb', 'sysobjects',
            'syscolums', 'syslogins', 'sysxlogins', 'char%20', 'char ', 'into%20', 'into ',
            'load%20', 'load ', 'msys', 'alert%20', 'alert ', 'eval%20', 'eval ',

            // XSS-атаки
            'onkeyup', 'x5cx', 'fromcharcode', 'javascript:', 'vbscript:',
            'vbscript.', 'http-equiv', '->', 'expression%20', 'expression ',
            'url%20', 'url ', 'innerhtml', 'document.', 'dynsrc', 'jsessionid',
            'style%20', 'style ', 'phpsessid', '<applet', '<div', '<embed', '<iframe',
            '<img', '<meta', '<object', '<script', '<textarea', 'script:', 'data:', 'base64,',
            'srcdoc', 'onerror', 'onload', 'onclick', 'onmouseover', 'onmouseout',
            'onfocus', 'onblur', 'onsubmit', 'onreset', 'onchange', 'oninput',
            'oninvalid', 'onselect', 'ondrag', 'ondrop', 'oncopy', 'oncut',
            'onpaste', 'oncontextmenu', 'onresize', 'onscroll', 'ontoggle',
            'onanimationstart', 'onanimationend', 'ontransitionend',
            'onbeforeunload', 'onhashchange', 'onmessage', 'onoffline',
            'ononline', 'onpagehide', 'onpageshow', 'onpopstate', 'onstorage',
            'onunhandledrejection', 'onwheel', 'onabort', 'oncanplay',
            'oncanplaythrough', 'ondurationchange', 'onemptied', 'onended',
            'onloadeddata', 'onloadedmetadata', 'onpause', 'onplay',
            'onplaying', 'onprogress', 'onratechange', 'onseeked', 'onseeking',
            'onstalled', 'onsuspend', 'ontimeupdate', 'onvolumechange',
            'onwaiting',

            // Редкие угрозы
            'ldap://', 'gopher://', 'file://', 'phar://', 'zlib://', 'compress://',
            'base64_decode', 'passthru', 'shell_exec', 'proc_open', 'popen',
            'pcntl_exec', 'window.open', 'img src',

            // Дополнительные кодировки и фрагменты
            '%3C', '%3E', '%27', '%22', '%00', rawurldecode('%00'),
        ];

        foreach ($array_rules as $rule) {
            $this->compiled_rules[] = '/' . preg_quote($rule, '/') . '/i';
        }
    }

    /**
     * @brief Проверяет URL на наличие вредоносного кода.
     *
     * @details Этот метод является "витриной" для вызова внутреннего метода _url_check_internal.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * if (!$work->url_check()) {
     *     echo "Обнаружен подозрительный URL!";
     * }
     * @endcode
     *
     * @see PhotoRigma::Classes::Work::url_check() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Security::_url_check_internal() Реализация метода внутри класса Work_Security.
     *
     * @return bool True, если URL безопасен, иначе False.
     */
    public function url_check(): bool
    {
        return $this->_url_check_internal();
    }

    /**
     * @brief Универсальная проверка входных данных.
     *
     * @details Этот метод является "витриной" для вызова внутреннего метода _check_input_internal.
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
     * @see PhotoRigma::Classes::Work::check_input() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Security::_check_input_internal() Реализация метода внутри класса Work_Security.
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
        return $this->_check_input_internal($source_name, $field, $options);
    }

    /**
     * @brief Проверяет содержимое поля на соответствие регулярному выражению или другим условиям.
     *
     * @details Этот метод является "витриной" для вызова внутреннего метода _check_field_internal.
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
     * @see PhotoRigma::Classes::Work::check_field() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Security::_check_field_internal() Реализация метода внутри класса Work_Security.
     *
     * @param string $field Значение поля для проверки.
     * @param string|false $regexp Регулярное выражение (необязательно). Если задано, значение должно соответствовать этому выражению.
     * @param bool $not_zero Флаг, указывающий, что значение не должно быть числом 0.
     *
     * @return bool True, если поле прошло все проверки, иначе False.
     */
    public function check_field(string $field, string|false $regexp = false, bool $not_zero = false): bool
    {
        return $this->_check_field_internal($field, $regexp, $not_zero);
    }

    /**
     * @brief Внутренний метод для проверки URL на наличие вредоносного кода.
     *
     * @details Метод проверяет строку запроса ($_SERVER['REQUEST_URI']) на наличие запрещённых паттернов.
     *
     * @see PhotoRigma::Classes::Work_Security::url_check() Публичный метод, который вызывает этот внутренний метод.
     *
     * @return bool True, если URL безопасен, иначе False.
     */
    protected function _url_check_internal(): bool
    {
        if (!filter_var($_SERVER['REQUEST_URI'], FILTER_VALIDATE_URL)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный формат URL в REQUEST_URI | Получено: {$_SERVER['REQUEST_URI']}"
            );
            return false;
        }
        $request_uri = strtolower($_SERVER['REQUEST_URI']);
        foreach ($this->compiled_rules as $rule) {
            if (preg_match($rule, $request_uri)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома: найден запрещённый паттерн | Паттерн: {$rule}, Запрос: {$request_uri}"
                );
                return false;
            }
        }
        return true;
    }

    /**
     * @brief Генерирует математический CAPTCHA-вопрос и ответ.
     *
     * @details Этот метод является "витриной" для вызова внутреннего метода _gen_captcha_internal.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $captcha = $work->gen_captcha();
     * echo "Вопрос: {$captcha['question']}, Ответ: {$captcha['answer']}";
     * // Пример вывода: Вопрос: 2 x (3 + 4), Ответ: 14
     * @endcode
     *
     * @see PhotoRigma::Classes::Work::gen_captcha() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Security::_gen_captcha_internal() Реализация метода внутри класса Work_Security.
     *
     * @return array{
     *     question: string, // Математическое выражение (например, "2 x (3 + 4)")
     *     answer: int       // Числовой ответ на выражение (например, 14)
     * } Массив с ключами 'question' и 'answer'.
     */
    public function gen_captcha(): array
    {
        return $this->_gen_captcha_internal();
    }

    /**
     * @brief Заменяет символы в email-адресах для "обмана" ботов.
     *
     * @details Этот метод является "витриной" для вызова внутреннего метода _filt_email_internal.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $filteredEmail = $work->filt_email('example@example.com');
     * echo $filteredEmail; // Выведет: example[at]example[dot]com
     * @endcode
     *
     * @see PhotoRigma::Classes::Work::filt_email() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Security::_filt_email_internal() Реализация метода внутри класса Work_Security.
     *
     * @param string $email Email-адрес для обработки.
     *
     * @return string Обработанный email-адрес.
     */
    public function filt_email(string $email): string
    {
        return $this->_filt_email_internal($email);
    }

    /**
     * @brief Внутренний метод для универсальной проверки входных данных.
     *
     * @details Метод выполняет проверку данных из различных источников ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES)
     * на соответствие заданным условиям.
     *
     * @see PhotoRigma::Classes::Work_Security::check_input() Публичный метод, который вызывает этот внутренний метод.
     *
     * @param string $source_name Источник данных ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES).
     * @param string $field Поле для проверки (имя ключа в массиве источника данных).
     * @param array $options Дополнительные параметры проверки.
     *                       - Ключ 'isset' (bool, опционально): Проверять наличие поля в источнике данных.
     *                       - Ключ 'empty' (bool, опционально): Проверять, что значение поля не пустое.
     *                       - Ключ 'regexp' (string|false, опционально): Регулярное выражение для проверки значения поля.
     *                       - Ключ 'not_zero' (bool, опционально): Проверять, что значение поля не равно нулю.
     *                       - Ключ 'max_size' (int, опционально): Максимальный размер файла (в байтах) для $_FILES.
     *
     * @return bool True, если данные прошли проверку, иначе False.
     */
    protected function _check_input_internal(string $source_name, string $field, array $options = []): bool
    {
        $allowed_sources = ['_GET', '_POST', '_SESSION', '_COOKIE', '_FILES'];
        if (!in_array($source_name, $allowed_sources, true)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый источник данных для check_input | Получено: {$source_name}"
            );
            return false;
        }
        $source = match ($source_name) {
            '_GET' => $_GET,
            '_POST' => $_POST,
            '_SESSION' => $_SESSION,
            '_COOKIE' => $_COOKIE,
            '_FILES' => $_FILES,
            default => null,
        };
        if (!is_array($source)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Источник данных не является массивом | Источник: {$source_name}"
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
            $file_name = basename($file['name']);
            if ($file['error'] !== UPLOAD_ERR_OK) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка загрузки файла для поля | Поле: {$field}"
                );
                return false;
            }
            $max_size = $options['max_size'] ?? 0;
            if ($max_size > 0 && $file['size'] > $max_size) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Размер файла превышает максимально допустимый размер | Поле: {$field}, Размер: {$file['size']}, Максимальный размер: {$max_size}"
                );
                return false;
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $real_mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $is_mime_supported = Work::validate_mime_type($real_mime_type);
            if (!$is_mime_supported) {
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый MIME-тип для загруженного файла | Поле: {$field}, MIME-тип: {$real_mime_type}"
                );
            }
        }
        return $this->_check_field_internal(
            $source[$field] ?? null,
            $options['regexp'] ?? false,
            $options['not_zero'] ?? false
        );
    }

    /**
     * @brief Внутренний метод для проверки содержимого поля на соответствие регулярному выражению или другим условиям.
     *
     * @details Метод выполняет следующие проверки:
     * - Если задано регулярное выражение ($regexp), проверяет соответствие значения этому выражению.
     * - Если флаг $not_zero установлен, проверяет, что значение не является числом 0.
     * - Проверяет, что значение не содержит запрещённых паттернов из $this->compiled_rules.
     *
     * @see PhotoRigma::Classes::Work_Security::check_field() Публичный метод, который вызывает этот внутренний метод.
     *
     * @param string $field Значение поля для проверки.
     * @param string|false $regexp Регулярное выражение (необязательно). Если задано, значение должно соответствовать этому выражению.
     * @param bool $not_zero Флаг, указывающий, что значение не должно быть числом 0.
     *
     * @return bool True, если поле прошло все проверки, иначе False.
     */
    protected function _check_field_internal(string $field, string|false $regexp = false, bool $not_zero = false): bool
    {
        if ($regexp !== false) {
            $isValidRegexp = preg_match($regexp, '') !== false;
            if (!$isValidRegexp) {
                $errorMessage = match (preg_last_error()) {
                    PREG_INTERNAL_ERROR => "Внутренняя ошибка регулярного выражения.",
                    PREG_BACKTRACK_LIMIT_ERROR => "Превышен лимит обратного отслеживания.",
                    PREG_RECURSION_LIMIT_ERROR => "Превышен лимит рекурсии.",
                    PREG_BAD_UTF8_ERROR => "Некорректная UTF-8 строка.",
                    PREG_BAD_UTF8_OFFSET_ERROR => "Некорректный смещение UTF-8.",
                    default => "Неизвестная ошибка регулярного выражения.",
                };
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка в регулярном выражении | Регулярное выражение: {$regexp}, Причина: {$errorMessage}"
                );
                return false;
            }
            if (!preg_match($regexp, $field)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Значение поля не соответствует регулярному выражению | Поле: '{$field}', Регулярное выражение: {$regexp}"
                );
                return false;
            }
        }
        if ($not_zero && $field === '0') {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Значение поля равно 0, но флаг not_zero установлен | Поле: '{$field}'"
            );
            return false;
        }
        foreach ($this->compiled_rules as $rule) {
            if (preg_match($rule, $field)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Значение поля содержит запрещённый паттерн | Поле: '{$field}', Паттерн: {$rule}"
                );
                return false;
            }
        }
        return true;
    }

    /**
     * @brief Внутренний метод для генерации математического CAPTCHA-вопроса и ответа.
     *
     * @details Метод создаёт случайное математическое выражение (сложение или умножение)
     * и возвращает его вместе с правильным ответом. Используется для защиты от спам-ботов.
     *
     * @see PhotoRigma::Classes::Work_Security::gen_captcha() Публичный метод, который вызывает этот внутренний метод.
     *
     * @return array{
     *     question: string, // Математическое выражение (например, "2 x (3 + 4)")
     *     answer: int       // Числовой ответ на выражение (например, 14)
     * } Массив с ключами 'question' и 'answer'.
     */
    protected function _gen_captcha_internal(): array
    {
        $num1 = rand(1, 9);
        $num2 = rand(1, 9);
        $num3 = rand(1, 9);

        $operation1 = rand(1, 2); // 1 - умножение, 2 - сложение
        $operation2 = rand(1, 2); // 1 - умножение, 2 - сложение

        $captcha = [
            'question' => '',
            'answer' => 0,
        ];

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
     * @brief Внутренний метод для замены символов в email-адресах.
     *
     * @details Метод заменяет символы '@' и '.' на '[at]' и '[dot]', чтобы затруднить автоматический парсинг email-адресов ботами.
     * Проверяет, что входной email не пустой и соответствует формату email.
     *
     * @see PhotoRigma::Classes::Work_Security::filt_email() Публичный метод, который вызывает этот внутренний метод.
     *
     * @param string $email Email-адрес для обработки.
     *
     * @return string Обработанный email-адрес.
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
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный email-адрес | Получено: {$email}"
            );
            return '';
        }
        $filtered_email = str_replace(['@', '.'], ['[at]', '[dot]'], $email);
        return $filtered_email;
    }
}
