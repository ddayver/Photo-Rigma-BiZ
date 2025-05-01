<?php

/**
 * @file        include/Work_Security.php
 * @brief       Файл содержит класс Work_Security, который отвечает за безопасность приложения.
 *
 * @author      Dark Dayver
 * @version     0.4.2
 * @date        2025-04-27
 * @namespace   PhotoRigma\\Classes
 *
 * @details     Этот файл содержит класс `Work_Security`, который реализует интерфейс `Work_Security_Interface`.
 *              Класс предоставляет методы для:
 *              - Проверки входных данных на наличие вредоносного кода.
 *              - Защиты от спам-ботов с использованием CAPTCHA.
 *              - Фильтрации email-адресов для затруднения автоматического парсинга ботами.
 *              - Универсальной проверки данных из различных источников ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES).
 *
 * @section     WorkSecurity_Main_Functions Основные функции
 *              - Проверка входных данных на соответствие регулярным выражениям.
 *              - Защита email-адресов от парсинга ботами.
 *              - Генерация математических CAPTCHA-вопросов для защиты от спам-ботов.
 *              - Проверка URL на наличие вредоносного кода.
 *
 * @see         PhotoRigma::Interfaces::Work_Security_Interface Интерфейс для работы с безопасностью приложения.
 * @see         PhotoRigma::Classes::Work Класс, через который вызываются методы безопасности.
 * @see         PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в обеспечении безопасности
 *              приложения. Реализованы меры для предотвращения атак, таких как XSS, SQL-инъекции и спам-боты.
 *
 * @copyright   Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать, распространять,
 *              сублицензировать и/или продавать копии программного обеспечения, а также разрешать лицам, которым
 *              предоставляется данное программное обеспечение, делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые
 *                части программного обеспечения.
 */

namespace PhotoRigma\Classes;

use Exception;
use PhotoRigma\Interfaces\Work_Security_Interface;
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
 * @class   Work_Security
 * @brief   Класс для обеспечения безопасности приложения.
 *
 * @details Этот класс реализует интерфейс `Work_Security_Interface` и предоставляет функционал для защиты приложения
 *          от различных угроз, таких как SQL-инъекции, XSS-атаки, спам-боты и другие. Он является дочерним классом для
 *          `PhotoRigma::Classes::Work` и наследует его методы. Все методы данного класса рекомендуется вызывать через
 *          родительский класс `Work`, так как их поведение может быть непредсказуемым при прямом вызове.
 *          Основные возможности:
 *          - Проверка входных данных на соответствие регулярным выражениям (`check_field`, `check_input`).
 *          - Защита email-адресов от парсинга ботами (`filt_email`).
 *          - Генерация математических CAPTCHA-вопросов для защиты от спам-ботов (`gen_captcha`).
 *          - Проверка URL на наличие вредоносного кода (`url_check`).
 *          Методы класса предназначены для использования в различных частях приложения для обеспечения безопасности.
 *
 * @property array $compiled_rules Предварительно скомпилированные правила защиты.
 * @property array $session        Массив, привязанный к глобальному массиву $_SESSION.
 *
 * Пример использования класса:
 * @code
 * // Инициализация объекта класса Work_Security
 * $security = new \\PhotoRigma\\Classes\\Work_Security($_SESSION);
 *
 * // Вызов метода через родительский класс Work
 * $is_valid = \\PhotoRigma\\Classes\\Work::check_field('username', '/^[a-zA-Z0-9]+$/', true);
 * var_dump($is_valid); // Выведет: true или false
 *
 * // Генерация CAPTCHA
 * $captcha = \\PhotoRigma\\Classes\\Work::gen_captcha();
 * echo "Вопрос: {$captcha['question']}, Ответ: {$captcha['answer']}";
 * @endcode
 * @see     PhotoRigma::Interfaces::Work_Security_Interface Интерфейс, который реализует данный класс.
 */
class Work_Security implements Work_Security_Interface
{
    // Свойства:
    private array $compiled_rules = []; ///< Предварительно скомпилированные правила защиты.
    private array $session; ///< Массив, привязанный к глобальному массиву $_SESSION

    /**
     * @brief   Конструктор класса.
     *
     * @details Инициализирует предварительно скомпилированные правила защиты из массива `$array_rules`.
     *          Эти правила используются для проверки URL и входных данных на наличие вредоносных паттернов.
     *          Алгоритм работы:
     *          1. Принимает ссылку на массив сессии (`$session`) для хранения пользовательских данных.
     *          2. Определяет массив правил `$array_rules`, содержащий паттерны для защиты от SQL-инъекций, XSS-атак и
     *             других угроз.
     *          3. Компилирует правила с использованием `preg_quote` для экранирования специальных символов.
     *          4. Сохраняет скомпилированные правила в свойство `$compiled_rules`.
     *          Этот класс является дочерним для `PhotoRigma::Classes::Work`.
     *
     * @callgraph
     *
     * @param array &$session Ссылка на массив сессии для хранения пользовательских данных.
     *                        Массив должен быть доступен для записи и чтения.
     *                        Пример: `$_SESSION`.
     *
     * @note    Правила компилируются с использованием `preg_quote` для экранирования специальных символов.
     *          Флаг `/i` делает правила регистронезависимыми.
     *
     * @warning Правила защиты жёстко заданы в конструкторе. При изменении или расширении массива правил потребуется
     *          обновление кода. Использование `preg_quote` с флагом `'/'` делает правила чувствительными к символу
     *          `'/'`, что может быть важно при их использовании.
     *
     * Пример создания объекта класса Work_Security:
     * @code
     * $security = new \PhotoRigma\Classes\Work_Security($_SESSION);
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
     * @see     PhotoRigma::Classes::Work
     *          Родительский класс.
     * @see     PhotoRigma::Classes::Work_Security::$compiled_rules
     *          Свойство, содержащее предварительно скомпилированные правила защиты.
     */
    public function __construct(array &$session)
    {
        $this->session = &$session;
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
     * @brief   Проверяет содержимое поля на соответствие регулярному выражению или другим условиям через вызов
     *          внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _check_field_internal().
     *          Он выполняет проверки поля на соответствие регулярному выражению, отсутствие запрещённых паттернов
     *          и выполнение условия $not_zero (если оно задано). Метод также доступен через метод-фасад
     *          `check_field()`
     *          в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @param string       $field    Значение поля для проверки:
     *                               - Указывается строковое значение, которое необходимо проверить.
     * @param string|false $regexp   Регулярное выражение (необязательно):
     *                               - Если задано, значение должно соответствовать этому выражению.
     *                               - Если регулярное выражение некорректно, метод завершает выполнение с ошибкой.
     * @param bool         $not_zero Флаг, указывающий, что значение не должно быть числом 0:
     *                               - Если флаг установлен, а значение равно '0', проверка завершается с ошибкой.
     *
     * @return bool True, если поле прошло все проверки, иначе False.
     *              Проверки включают соответствие регулярному выражению, отсутствие запрещённых паттернов
     *              и выполнение условия $not_zero (если оно задано).
     *
     * @throws Exception При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @note    Метод использует свойство `$this->compiled_rules` для проверки на наличие запрещённых паттернов.
     *          Свойство должно содержать массив скомпилированных регулярных выражений.
     *
     * @warning Метод зависит от корректности данных в свойстве `$this->compiled_rules`.
     *          Если правила некорректны, результат может быть непредсказуемым.
     *          Также важно убедиться, что регулярное выражение ($regexp) корректно перед использованием.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Security
     * $security = new \PhotoRigma\Classes\Work_Security();
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
     * @see     PhotoRigma::Classes::Work_Security::_check_field_internal()
     *          Защищённый метод, выполняющий основную логику.
     * @see     PhotoRigma::Classes::Work::check_field()
     *          Метод-фасад в родительском классе для вызова этой логики.
     */
    public function check_field(string $field, string|false $regexp = false, bool $not_zero = false): bool
    {
        return $this->_check_field_internal($field, $regexp, $not_zero);
    }

    /**
     * @brief   Внутренний метод для проверки содержимого поля на соответствие регулярному выражению или другим
     *          условиям.
     *
     * @details Этот защищённый метод выполняет следующие проверки:
     *          1. Если задано регулярное выражение ($regexp):
     *             - Проверяет корректность регулярного выражения (отсутствие ошибок компиляции).
     *             - Проверяет соответствие значения поля этому выражению.
     *             - При возникновении ошибки компиляции регулярного выражения используется `preg_last_error` для
     *             определения причины (например, внутренняя ошибка, превышение лимитов обратного отслеживания или
     *             рекурсии и т.д.).
     *          2. Если флаг $not_zero установлен, проверяет, что значение поля не равно '0'.
     *          3. Проверяет, что значение поля не содержит запрещённых паттернов из свойства `$this->compiled_rules`.
     *          Метод является защищённым и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод `check_field()`.
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
     * @throws Exception При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @note    Метод использует свойство `$this->compiled_rules` для проверки на наличие запрещённых паттернов.
     *          Свойство должно содержать массив скомпилированных регулярных выражений.
     *
     * @warning Метод зависит от корректности данных в свойстве `$this->compiled_rules`.
     *          Если правила некорректны, результат может быть непредсказуемым.
     *          Также важно убедиться, что регулярное выражение ($regexp) корректно перед использованием.
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
     * @see     PhotoRigma::Classes::Work_Security::$compiled_rules
     *          Свойство, содержащее массив скомпилированных правил для проверки.
     * @see     PhotoRigma::Classes::Work_Security::check_field()
     *          Публичный метод, который вызывает этот внутренний метод.
     * @see     PhotoRigma::Include::log_in_file()
     *          Функция для логирования ошибок.
     */
    protected function _check_field_internal(string $field, string|false $regexp = false, bool $not_zero = false): bool
    {
        if ($regexp !== false) {
            /** @noinspection OneTimeUseVariablesInspection */
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
     * @brief   Универсальная проверка входных данных через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _check_input_internal().
     *          Он выполняет проверку данных из различных источников ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES)
     *          на соответствие заданным условиям. Метод также доступен через метод-фасад `check_input()`
     *          в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @param string $source_name   Источник данных ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES):
     *                              - Должен быть одним из допустимых значений: '_GET', '_POST', '_SESSION', '_COOKIE',
     *                              '_FILES'.
     * @param string $field         Поле для проверки:
     *                              - Указывается имя ключа, которое необходимо проверить.
     * @param array  $options       Дополнительные параметры проверки. Массив может содержать следующие ключи:
     *                              - 'isset' (bool, опционально): Проверять наличие поля в источнике данных.
     *                              - 'empty' (bool, опционально): Проверять, что значение поля не пустое.
     *                              - 'regexp' (string|false, опционально): Регулярное выражение для проверки значения
     *                              поля.
     *                              - 'not_zero' (bool, опционально): Проверять, что значение поля не равно нулю.
     *                              - 'max_size' (int, опционально): Максимальный размер файла (в байтах) для $_FILES.
     *
     * @return bool True, если данные прошли проверку, иначе False.
     *              Для $_FILES также учитывается корректность MIME-типа и размера файла.
     *
     * @throws RuntimeException Если MIME-тип загруженного файла не поддерживается.
     * @throws Exception При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @note    Для источника данных `_SESSION` используется свойство класса `$this->session`.
     *          Метод зависит от корректности данных в источниках ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES).
     *          Если источник данных повреждён или содержит некорректные значения, результат может быть
     *          непредсказуемым.
     *
     * @warning Убедитесь, что переданные параметры проверки ($options) корректны и соответствуют требованиям.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Security
     * $security = new \PhotoRigma\Classes\Work_Security();
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
     * @see     PhotoRigma::Classes::Work_Security::_check_input_internal()
     *          Защищённый метод, выполняющий основную логику.
     * @see     PhotoRigma::Classes::Work::check_input()
     *          Метод-фасад в родительском классе для вызова этой логики.
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
     *          Для источника данных `_SESSION` используется свойство класса `$this->session`.
     *          Метод является защищённым и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод `check_input()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $source_name   Источник данных ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES).
     *                              Должен быть одним из допустимых значений: '_GET', '_POST', '_SESSION', '_COOKIE',
     *                              '_FILES'.
     * @param string $field         Поле для проверки (имя ключа в массиве источника данных).
     *                              Указывается имя ключа, которое необходимо проверить.
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
     * @note    Для источника данных `_SESSION` используется свойство класса `$this->session`.
     *          Метод зависит от корректности данных в источниках ($_GET, $_POST, $_SESSION, $_COOKIE, $_FILES).
     *          Если источник данных повреждён или содержит некорректные значения, результат может быть
     *          непредсказуемым.
     *
     * @warning Убедитесь, что переданные параметры проверки ($options) корректны и соответствуют требованиям.
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
     * @see     PhotoRigma::Classes::Work_Security::$session
     *          Свойство, содержащее данные сессии для проверки.
     * @see     PhotoRigma::Include::log_in_file()
     *          Функция для логирования ошибок.
     * @see     PhotoRigma::Classes::Work::validate_mime_type()
     *          Метод для проверки поддерживаемых MIME-типов.
     * @see     PhotoRigma::Classes::Work_Security::_check_field_internal()
     *          Защищённый метод, используемый для дополнительной проверки поля.
     * @see     PhotoRigma::Classes::Work_Security::check_input()
     *          Публичный метод, который вызывает этот внутренний метод.
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
            '_SESSION' => $this->session,
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
     * @brief   Заменяет символы в email-адресах для "обмана" ботов через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _filt_email_internal().
     *          Он заменяет символы '@' и '.' на '[at]' и '[dot]', чтобы затруднить автоматический парсинг
     *          email-адресов ботами. Метод также доступен через метод-фасад `filt_email()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
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
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Security
     * $security = new \PhotoRigma\Classes\Work_Security();
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
     * @see     PhotoRigma::Classes::Work_Security::_filt_email_internal()
     *          Защищённый метод, выполняющий основную логику.
     * @see     PhotoRigma::Classes::Work::filt_email()
     *          Метод-фасад в родительском классе для вызова этой логики.
     */
    public function filt_email(string $email): string
    {
        return $this->_filt_email_internal($email);
    }

    /**
     * @brief   Внутренний метод для замены символов в email-адресах.
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Проверяет, что входной email не пустой с помощью `empty($email)`.
     *          2. Проверяет корректность формата email с помощью `filter_var($email, FILTER_VALIDATE_EMAIL)`.
     *             Если email некорректен или пуст, метод возвращает пустую строку.
     *          3. Заменяет символы '@' и '.' на '[at]' и '[dot]' соответственно с помощью `str_replace`.
     *          Метод предназначен для затруднения автоматического парсинга email-адресов ботами.
     *          Является защищённым и используется внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод `filt_email()`.
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
     * $filtered_email = $this->_filt_email_internal($email);
     * if (!empty($filtered_email)) {
     *     echo "Обработанный email: {$filtered_email}";
     * } else {
     *     echo "Email некорректен или пуст.";
     * }
     * @endcode
     * @see     PhotoRigma::Include::log_in_file()
     *          Функция для логирования ошибок.
     * @see     PhotoRigma::Classes::Work_Security::filt_email()
     *          Публичный метод, который вызывает этот внутренний метод.
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
     * @brief   Генерирует математический CAPTCHA-вопрос и ответ через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _gen_captcha_internal().
     *          Он создаёт случайное математическое выражение (сложение или умножение) и возвращает его вместе с
     *          правильным ответом. Метод также доступен через метод-фасад `gen_captcha()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @return array Массив с ключами 'question' и 'answer':
     *               - Ключ 'question' содержит строку математического выражения (например, "2 x (3 + 4)").
     *               - Ключ 'answer' содержит целочисленный результат вычисления (например, 14).
     *
     * @throws Random\RandomException Если произошла ошибка при генерации случайных чисел.
     *
     * @note    Метод использует `random_int()` для генерации случайных чисел, что обеспечивает криптографическую
     *          безопасность. Если требуется замена на менее безопасную функцию, можно использовать `rand()`.
     *
     * @warning Убедитесь, что PHP поддерживает функцию `random_int()`, так как её отсутствие приведёт к ошибке.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Security
     * $security = new \PhotoRigma\Classes\Work_Security();
     *
     * // Генерация CAPTCHA-вопроса и ответа
     * $captcha = $security->gen_captcha();
     * echo "Вопрос: {$captcha['question']}\n";
     * echo "Ответ: {$captcha['answer']}\n";
     * // Пример вывода:
     * // Вопрос: 2 x (3 + 4)
     * // Ответ: 14
     * @endcode
     * @see     PhotoRigma::Classes::Work_Security::_gen_captcha_internal()
     *          Защищённый метод, выполняющий основную логику.
     * @see     PhotoRigma::Classes::Work::gen_captcha()
     *          Метод-фасад в родительском классе для вызова этой логики.
     */
    public function gen_captcha(): array
    {
        return $this->_gen_captcha_internal();
    }

    /**
     * @brief   Внутренний метод для генерации математического CAPTCHA-вопроса и ответа.
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Генерирует три случайных числа в диапазоне от 1 до 9 с помощью `random_int()`.
     *          2. Формирует математическое выражение, которое может включать сложение (`+`) или умножение (`x`)
     *             с использованием скобок.
     *          3. Случайным образом выбирает операции для каждой части выражения:
     *             - Первая операция: либо умножение, либо сложение.
     *             - Вторая операция: либо умножение, либо сложение.
     *          4. Вычисляет правильный ответ для сгенерированного выражения.
     *          Метод используется для защиты от спам-ботов и является защищённым, предназначенным для использования
     *          внутри класса или его наследников. Основная логика вызывается через публичный метод `gen_captcha()`.
     *
     * @callergraph
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
     * $captcha = $this->_gen_captcha_internal();
     * echo "Вопрос: {$captcha['question']}\n";
     * echo "Ответ: {$captcha['answer']}\n";
     * @endcode
     * @see     PhotoRigma::Classes::Work_Security::gen_captcha()
     *          Публичный метод, который вызывает этот внутренний метод.
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
     * @brief   Проверяет URL на наличие вредоносного кода через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _url_check_internal().
     *          Он проверяет строку запроса ($_SERVER['QUERY_STRING']) на наличие запрещённых паттернов,
     *          определённых в массиве `$this->compiled_rules`. Метод также доступен через метод-фасад `url_check()`
     *          в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @return bool True, если URL безопасен (не содержит запрещённых паттернов), иначе False.
     *
     * @throws Exception При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @note    Метод работает с глобальным массивом `$_SERVER['QUERY_STRING']`.
     *          Убедитесь, что этот массив доступен и содержит корректные данные.
     *
     * @warning Метод зависит от корректности данных в свойстве `$this->compiled_rules`.
     *          Если правила некорректны, результат может быть непредсказуемым.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Security
     * $security = new \PhotoRigma\Classes\Work_Security();
     *
     * // Проверка безопасности URL
     * if ($security->url_check()) {
     *     echo "URL безопасен.";
     * } else {
     *     echo "URL содержит запрещённые паттерны.";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_Security::_url_check_internal()
     *          Защищённый метод, выполняющий основную логику.
     * @see     PhotoRigma::Classes::Work::url_check()
     *          Метод-фасад в родительском классе для вызова этой логики.
     */
    public function url_check(): bool
    {
        return $this->_url_check_internal();
    }

    /**
     * @brief   Внутренний метод для проверки URL на наличие вредоносного кода.
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Получает строку запроса из глобального массива `$_SERVER['QUERY_STRING']`.
     *          2. Приводит строку запроса к нижнему регистру с помощью `strtolower()` для унификации проверки.
     *          3. Проверяет строку запроса на наличие запрещённых паттернов, определённых в массиве
     *          `$this->compiled_rules`. Каждый паттерн проверяется с помощью функции `preg_match()`.
     *          4. Если найден запрещённый паттерн, информация о попытке взлома логируется через `log_in_file`.
     *          Метод предназначен для защиты от вредоносных URL и является защищённым, используемым внутри класса или
     *          его наследников. Основная логика вызывается через публичный метод `url_check()`.
     *
     * @callergraph
     * @callgraph
     *
     * @return bool True, если URL безопасен (не содержит запрещённых паттернов), иначе False.
     *
     * @throws Exception При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @note    Метод работает с глобальным массивом `$_SERVER['QUERY_STRING']`.
     *          Убедитесь, что этот массив доступен и содержит корректные данные.
     *
     * @warning Метод зависит от корректности данных в свойстве `$this->compiled_rules`.
     *          Если правила некорректны, результат может быть непредсказуемым.
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
     * @see     PhotoRigma::Classes::Work_Security::$compiled_rules
     *          Свойство, содержащее массив скомпилированных правил для проверки.
     * @see     PhotoRigma::Classes::Work_Security::url_check()
     *          Публичный метод, который вызывает этот внутренний метод.
     * @see     PhotoRigma::Include::log_in_file()
     *          Функция для логирования ошибок.
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
