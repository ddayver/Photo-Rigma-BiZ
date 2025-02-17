<?php

/**
 * @file        include/work_helper.php
 * @brief       Файл содержит класс Work_Helper, который предоставляет вспомогательные методы для работы с данными.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-02-12
 * @namespace   PhotoRigma\\Classes
 *
 * @details     Этот файл содержит класс `Work_Helper`, который реализует вспомогательные методы для обработки данных.
 *              Класс предоставляет методы для очистки строк от HTML-тегов, преобразования размеров в байты,
 *              транслитерации строк, преобразования BBCode в HTML, разбиения строк на ограниченные по длине части
 *              и проверки MIME-типов. Публичные методы класса являются редиректами на защищённые методы
 *              с суффиксом `_internal`, что обеспечивает более гибкую архитектуру (паттерн "фасад").
 *              Все методы класса являются статическими.
 *
 * @see         PhotoRigma::Classes::Work Класс, через который вызываются методы для работы с данными.
 * @see         PhotoRigma::Include::log_in_file Функция для логирования ошибок.
 * @see         index.php Файл, который подключает work_helper.php.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в обработке данных.
 *              Класс поддерживает работу с различными форматами данных, включая строки, числа, изображения и MIME-типы.
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
 * @brief Интерфейс для вспомогательных методов.
 *
 * @details Интерфейс определяет контракт для класса `Work_Helper`, который предоставляет статические методы для выполнения различных вспомогательных задач:
 *          - Очистка строк от HTML-тегов и специальных символов (`clean_field`).
 *          - Преобразование размеров в байты (`return_bytes`).
 *          - Транслитерация строк и замена знаков пунктуации (`encodename`).
 *          - Преобразование BBCode в HTML (`ubb`).
 *          - Разбиение строк на несколько строк ограниченной длины (`utf8_wordwrap`).
 *          - Проверка MIME-типов файлов через доступные библиотеки (`validate_mime_type`).
 *
 *          Методы интерфейса предназначены для использования в различных частях приложения, таких как обработка пользовательского ввода,
 *          работа с файлами, генерация безопасного вывода и т.д.
 *
 * @see PhotoRigma::Classes::Work_Helper Реализация интерфейса.
 */
interface Work_Helper_Interface
{
    /**
     * @brief Очистка строки от HTML-тегов и специальных символов.
     *
     * @details Метод удаляет HTML-теги и экранирует специальные символы, такие как `<`, `>`, `&`, `"`, `'`.
     *          Используется для защиты от XSS-атак и других проблем, связанных с некорректными данными.
     *
     * @param mixed $field Строка или данные для очистки.
     * @return string Очищенная строка.
     *
     * @see PhotoRigma::Classes::Work::clean_field() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     * @see PhotoRigma::Classes::Work_Helper::clean_field() Реализация метода в классе Work_Helper.
     *
     * @example Пример использования метода:
     * @code
     * $cleaned = Work::clean_field('<script>alert("XSS")</script>');
     * echo $cleaned; // Выведет: <script>alert(&quot;XSS&quot;)</script>
     * @endcode
     */
    public static function clean_field($field): string;

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
     *
     * @param string|int $val Размер в формате "число[K|M|G]" или число.
     * @return int Размер в байтах.
     *
     * @see PhotoRigma::Classes::Work::return_bytes() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     * @see PhotoRigma::Classes::Work_Helper::return_bytes() Реализация метода в классе Work_Helper.
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
    public static function return_bytes($val): int;

    /**
     * @brief Транслитерация строки и замена знаков пунктуации на "_".
     *
     * @details Метод выполняет транслитерацию кириллических символов в латиницу и заменяет знаки пунктуации на "_".
     *          Используется для создания "безопасных" имен файлов или URL.
     *          Если входная строка пустая, она возвращается без обработки.
     *          Если транслитерация невозможна (например, расширение intl недоступно), используется резервная таблица.
     *
     * @param string $string Исходная строка.
     * @return string Строка после транслитерации и замены символов.
     *
     * @see PhotoRigma::Classes::Work::encodename() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     * @see PhotoRigma::Classes::Work_Helper::encodename() Реализация метода в классе Work_Helper.
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
    public static function encodename(string $string): string;

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
     *
     * @param string $text Текст с BBCode.
     * @return string Текст с HTML-разметкой.
     *
     * @see PhotoRigma::Classes::Work::ubb() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     * @see PhotoRigma::Classes::Work_Helper::ubb() Реализация метода в классе Work_Helper.
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
    public static function ubb(string $text): string;

    /**
     * @brief Разбивка строки на несколько строк ограниченной длины.
     *
     * @details Метод разбивает строку на несколько строк, каждая из которых имеет длину не более указанной.
     *          Разрыв строки выполняется только по пробелам, чтобы сохранить читаемость текста.
     *          Поддерживается работа с UTF-8 символами.
     *          Если параметры некорректны (например, $width <= 0 или $break пустой), возвращается исходная строка.
     *
     * @param string $str Исходная строка.
     * @param int $width Максимальная длина строки (по умолчанию 70).
     * @param string $break Символ разрыва строки (по умолчанию PHP_EOL).
     * @return string Строка, разбитая на несколько строк.
     *
     * @see PhotoRigma::Classes::Work::utf8_wordwrap() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     * @see PhotoRigma::Classes::Work_Helper::utf8_wordwrap() Реализация метода в классе Work_Helper.
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
    /**
     * @brief Проверка MIME-типа файла через доступные библиотеки.
     *
     * @details Метод проверяет, поддерживается ли указанный MIME-тип хотя бы одной из доступных библиотек:
     *          - Imagick
     *          - Gmagick
     *          - GD
     *          Если MIME-тип не поддерживается ни одной библиотекой, возвращается false.
     *          Поддерживаются MIME-типы для изображений, таких как JPEG, PNG, GIF, WebP и другие.
     *
     * @param string $real_mime_type Реальный MIME-тип файла.
     * @return bool True, если MIME-тип поддерживается хотя бы одной библиотекой, иначе False.
     *
     * @see PhotoRigma::Classes::Work::validate_mime_type() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     * @see PhotoRigma::Classes::Work_Helper::validate_mime_type() Реализация метода в классе Work_Helper.
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
    public static function validate_mime_type(string $real_mime_type): bool;
}

/**
 * @brief Класс для вспомогательных методов.
 *
 * @details Класс реализует интерфейс `Work_Helper_Interface` и предоставляет статические методы для выполнения различных вспомогательных задач:
 *          - Очистка строк от HTML-тегов и специальных символов (`clean_field`).
 *          - Преобразование размеров в байты (`return_bytes`).
 *          - Транслитерация строк и замена знаков пунктуации (`encodename`).
 *          - Преобразование BBCode в HTML (`ubb`).
 *          - Разбиение строк на несколько строк ограниченной длины (`utf8_wordwrap`).
 *          - Проверка MIME-типов файлов через доступные библиотеки (`validate_mime_type`).
 *
 *          Методы класса предназначены для использования в различных частях приложения, таких как обработка пользовательского ввода,
 *          работа с файлами, генерация безопасного вывода и т.д.
 *
 * @see PhotoRigma::Classes::Work_Helper_Interface Интерфейс для вспомогательных методов.
 * @see PhotoRigma::Classes::Work Класс, через который вызываются вспомогательные методы.
 * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
 */
class Work_Helper implements Work_Helper_Interface
{
    /**
     * @brief Очистка строки от HTML-тегов и специальных символов (публичная обёртка).
     *
     * @details Публичная обёртка для вызова защищённого метода, который удаляет HTML-теги и экранирует специальные символы,
     *          такие как `<`, `>`, `&`, `"`, `'`. Используется для защиты от XSS-атак и других проблем,
     *          связанных с некорректными данными.
     *
     * @param mixed $field Строка или данные для очистки.
     * @return string|null Очищенная строка или null, если входные данные пусты.
     *
     * @see PhotoRigma::Classes::Work_Helper::_clean_field_internal() Защищённый метод, выполняющий очистку.
     * @see PhotoRigma::Classes::Work::clean_field() Этот метод вызывается через класс Work.
     *
     * @example Пример использования метода:
     * @code
     * $cleaned = Work::clean_field('<script>alert("XSS")</script>');
     * echo $cleaned; // Выведет: <script>alert(&quot;XSS&quot;)</script>
     * @endcode
     */
    public static function clean_field($field): ?string
    {
        return self::_clean_field_internal($field);
    }

    /**
     * @brief Преобразование размера в байты (публичная обёртка).
     *
     * @details Публичная обёртка для вызова защищённого метода, который преобразует размер,
     *          заданный в формате "число[K|M|G]", в количество байт. Поддерживаются суффиксы:
     *          - K (килобайты): умножается на 1024.
     *          - M (мегабайты): умножается на 1024².
     *          - G (гигабайты): умножается на 1024³.
     *          Если суффикс отсутствует или недопустим, значение считается в байтах.
     *          Если входные данные некорректны, возвращается 0.
     *
     * @param string|int $val Размер в формате "число[K|M|G]" или число.
     * @return int Размер в байтах.
     *
     * @see PhotoRigma::Classes::Work_Helper::_return_bytes_internal() Защищённый метод, выполняющий преобразование.
     * @see PhotoRigma::Classes::Work::return_bytes() Этот метод вызывается через класс Work.
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
        return self::_return_bytes_internal($val);
    }

    /**
     * @brief Транслитерация строки и замена знаков пунктуации на "_" (публичная обёртка).
     *
     * @details Публичная обёртка для вызова защищённого метода, который выполняет транслитерацию кириллических символов
     *          в латиницу и заменяет знаки пунктуации на "_". Используется для создания "безопасных" имен файлов или URL.
     *          Если входная строка пустая, она возвращается без обработки.
     *          Если транслитерация невозможна (например, расширение intl недоступно), используется резервная таблица.
     *
     * @param string $string Исходная строка.
     * @return string Строка после транслитерации и замены символов.
     *
     * @see PhotoRigma::Classes::Work_Helper::_encodename_internal() Защищённый метод, выполняющий транслитерацию.
     * @see PhotoRigma::Classes::Work::encodename() Этот метод вызывается через класс Work.
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
        return self::_encodename_internal($string);
    }

    /**
     * @brief Преобразование BBCode в HTML (публичная обёртка).
     *
     * @details Публичная обёртка для вызова защищённого метода, который преобразует BBCode-теги в соответствующие HTML-теги.
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
     *
     * @param string $text Текст с BBCode.
     * @return string Текст с HTML-разметкой.
     *
     * @see PhotoRigma::Classes::Work_Helper::_ubb_internal() Защищённый метод, выполняющий преобразование BBCode.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     * @see PhotoRigma::Classes::Work::ubb() Этот метод вызывается через класс Work.
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
        return self::_ubb_internal($text);
    }

    /**
     * @brief Разбивка строки на несколько строк ограниченной длины (публичная обёртка).
     *
     * @details Публичная обёртка для вызова защищённого метода, который разбивает строку на несколько строк,
     *          каждая из которых имеет длину не более указанной. Разрыв строки выполняется только по пробелам,
     *          чтобы сохранить читаемость текста. Поддерживается работа с UTF-8 символами.
     *          Если параметры некорректны (например, $width <= 0 или $break пустой), возвращается исходная строка.
     *
     * @param string $str Исходная строка.
     * @param int $width Максимальная длина строки (по умолчанию 70).
     * @param string $break Символ разрыва строки (по умолчанию PHP_EOL).
     * @return string Строка, разбитая на несколько строк.
     *
     * @see PhotoRigma::Classes::Work_Helper::_utf8_wordwrap_internal() Защищённый метод, выполняющий разбиение строки.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     * @see PhotoRigma::Classes::Work::utf8_wordwrap() Этот метод вызывается через класс Work.
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
        return self::_utf8_wordwrap_internal($str, $width, $break);
    }

    /**
     * @brief Проверка MIME-типа файла через доступные библиотеки (публичная обёртка).
     *
     * @details Публичная обёртка для вызова защищённого метода, который проверяет, поддерживается ли указанный MIME-тип
     *          хотя бы одной из доступных библиотек:
     *          - Imagick
     *          - Gmagick
     *          - GD
     *          Если MIME-тип не поддерживается ни одной библиотекой, возвращается false.
     *          Поддерживаются MIME-типы для изображений, таких как JPEG, PNG, GIF, WebP и другие.
     *
     * @param string $real_mime_type Реальный MIME-тип файла.
     * @return bool True, если MIME-тип поддерживается хотя бы одной библиотекой, иначе False.
     *
     * @see PhotoRigma::Classes::Work_Helper::_validate_mime_type_internal() Защищённый метод, выполняющий проверку MIME-типа.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     * @see PhotoRigma::Classes::Work::validate_mime_type() Этот метод вызывается через класс Work.
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
        return self::_validate_mime_type_internal($real_mime_type);
    }

    /**
     * @brief Очистка строки от HTML-тегов и специальных символов (защищённый метод).
     *
     * @details Этот защищённый метод удаляет HTML-теги и экранирует специальные символы, такие как `<`, `>`, `&`, `"`, `'`.
     *          Используется для защиты от XSS-атак и других проблем, связанных с некорректными данными.
     *
     * @param mixed $field Строка или данные для очистки.
     * @return string|null Очищенная строка или null, если входные данные пусты.
     *
     * @see PhotoRigma::Classes::Work_Helper::clean_field() Публичный метод, вызывающий этот защищённый метод.
     * @see PhotoRigma::Classes::Work::clean_field() Этот метод вызывается через класс Work.
     */
    protected static function _clean_field_internal($field): ?string
    {
        // Если входные данные пусты, возвращаем null
        if ($field === null || $field === '') {
            return null;
        }
        // Преобразуем данные в строку, если они не являются строкой
        if (!is_string($field)) {
            $field = (string)$field;
        }
        // Гарантируем корректную кодировку UTF-8
        $field = mb_convert_encoding($field, 'UTF-8', 'UTF-8');
        // Удаляем HTML-теги
        $field = strip_tags($field);
        // Экранируем специальные символы с использованием современных флагов
        $field = htmlspecialchars($field, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $field;
    }

    /**
     * @brief Преобразование размера в байты (защищённый метод).
     *
     * @details Этот защищённый метод преобразует размер, заданный в формате "число[K|M|G]", в количество байт.
     *          Поддерживаются суффиксы:
     *          - K (килобайты): умножается на 1024.
     *          - M (мегабайты): умножается на 1024².
     *          - G (гигабайты): умножается на 1024³.
     *          Если суффикс отсутствует или недопустим, значение считается в байтах.
     *          Если входные данные некорректны, возвращается 0.
     *
     * @param string|int $val Размер в формате "число[K|M|G]" или число.
     * @return int Размер в байтах.
     *
     * @see PhotoRigma::Classes::Work_Helper::return_bytes() Публичный метод, вызывающий этот защищённый метод.
     * @see PhotoRigma::Classes::Work::return_bytes() Этот метод вызывается через класс Work.
     */
    protected static function _return_bytes_internal($val): int
    {
        // Проверяем, что входные данные являются строкой или числом
        if (!is_string($val) && !is_numeric($val)) {
            return 0; // Возвращаем 0 для некорректных данных
        }
        // Удаляем лишние пробелы
        $val = trim((string)$val);
        // Если строка содержит только цифры, возвращаем значение как есть
        if (ctype_digit($val)) {
            return (int)$val;
        }
        // Извлекаем последний символ (суффикс)
        $last = strtolower($val[-1]);
        $number = rtrim($val, 'kmg');
        // Проверяем, является ли числовая часть корректной
        if (!is_numeric($number)) {
            return 0; // Возвращаем 0 для некорректных данных
        }
        // Преобразуем в абсолютное значение
        $number = abs((float)$number);
        // Преобразуем в байты с использованием match
        return match ($last) {
            'g' => (int)($number * 1024 * 1024 * 1024),
            'm' => (int)($number * 1024 * 1024),
            'k' => (int)($number * 1024),
            default => (int)$number, // Если суффикс недопустим, возвращаем только числовую часть
        };
    }

    /**
     * @brief Транслитерация строки и замена знаков пунктуации на "_" (защищённый метод).
     *
     * @details Этот защищённый метод выполняет транслитерацию кириллических символов в латиницу и заменяет знаки пунктуации на "_".
     *          Используется для создания "безопасных" имен файлов или URL.
     *          Если входная строка пустая, она возвращается без обработки.
     *          Если транслитерация невозможна (например, расширение intl недоступно), используется резервная таблица.
     *
     * @param string $string Исходная строка.
     * @return string Строка после транслитерации и замены символов.
     *
     * @see PhotoRigma::Classes::Work_Helper::encodename() Публичный метод, вызывающий этот защищённый метод.
     * @see PhotoRigma::Classes::Work::encodename() Этот метод вызывается через класс Work.
     */
    protected static function _encodename_internal(string $string): string
    {
        // Если входная строка пустая, возвращаем её без обработки
        if (empty($string)) {
            return $string;
        }
        // Таблица транслитерации
        $table = [
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G',
            'Д' => 'D', 'Е' => 'E', 'Ё' => 'YO', 'Ж' => 'ZH',
            'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K',
            'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
            'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
            'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'CSH', 'Ь' => '',
            'Ы' => 'Y', 'Ъ' => '', 'Э' => 'E', 'Ю' => 'YU',
            'Я' => 'YA', 'а' => 'a', 'б' => 'b', 'в' => 'v',
            'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'j',
            'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's',
            'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h',
            'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'csh',
            'ь' => '', 'ы' => 'y', 'ъ' => '', 'э' => 'e',
            'ю' => 'yu', 'я' => 'ya'
        ];
        // Транслитерация строки
        if (extension_loaded('intl')) {
            // Используем расширение intl, если оно доступно
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($transliterator !== null) {
                $string = $transliterator->transliterate($string);
            }
        } else {
            // Если расширение intl недоступно, используем таблицу транслитерации
            $string = str_replace(array_keys($table), array_values($table), $string);
        }
        // Замена специальных символов на "_" и удаление лишних подчёркиваний
        $string = preg_replace('/[^a-zA-Z0-9\s]/', '_', $string); // Заменяем все символы, кроме букв, цифр и пробелов, на "_"
        $string = preg_replace('/_{2,}/', '_', $string); // Заменяем множественные подчёркивания на одно
        $string = trim($string, '_'); // Удаляем подчёркивания в начале и конце строки
        // Если после обработки строка пустая, генерируем уникальную последовательность
        if (empty($string)) {
            $string = substr(md5(uniqid(microtime(true), true)), 0, 16);
        }
        return $string;
    }

    /**
     * @brief Преобразование BBCode в HTML (защищённый метод).
     *
     * @details Этот защищённый метод преобразует BBCode-теги в соответствующие HTML-теги с учетом рекурсивной обработки вложенных тегов.
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
     *
     * @param string $text Текст с BBCode.
     * @return string Текст с HTML-разметкой.
     *
     * @see PhotoRigma::Classes::Work_Helper::ubb() Публичный метод, вызывающий этот защищённый метод.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     * @see PhotoRigma::Classes::Work::ubb() Этот метод вызывается через класс Work.
     */
    protected static function _ubb_internal(string $text): string
    {
        // Очищаем текст от потенциально опасных символов
        $text = self::clean_field($text);
        // Максимальная глубина рекурсии для вложенных BBCode
        $max_recursion_depth = 10;
        // Рекурсивная функция для обработки BBCode
        $process_recursively = function ($text, $depth) use (&$process_recursively, $max_recursion_depth) {
            if ($depth > $max_recursion_depth) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Превышена максимальная глубина рекурсии при обработке BBCode"
                );
                return $text; // Прерываем обработку
            }
            // Проверяем, есть ли BBCode-теги
            if (!preg_match('/\[[a-z].*?\]/', $text)) {
                return $text; // Нет BBCode-тегов — завершаем обработку
            }
            // Паттерны для преобразования BBCode в HTML
            $patterns = [
                // Жирный текст
                '#\[b\](.*?)\[/b\]#si' => fn ($matches) => '<strong>' . $process_recursively($matches[1], $depth + 1) . '</strong>',
                // Подчёркнутый текст
                '#\[u\](.*?)\[/u\]#si' => fn ($matches) => '<u>' . $process_recursively($matches[1], $depth + 1) . '</u>',
                // Курсив
                '#\[i\](.*?)\[/i\]#si' => fn ($matches) => '<em>' . $process_recursively($matches[1], $depth + 1) . '</em>',
                // Простая ссылка
                '#\[url\](.*?)\[/url\]#si' => function ($matches) {
                    $url = self::clean_field($matches[1]);
                    if (
                        !filter_var($url, FILTER_VALIDATE_URL) ||
                        preg_match('/^(javascript|data|vbscript):/i', $url) ||
                        strlen($url) > 2000
                    ) {
                        log_in_file(
                            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный URL | Получено: '$url'"
                        );
                        return '<a href="#" title="#">A-a-a-a!</a>'; // Безопасное значение
                    }
                    return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" title="' . $url . '">' . $url . '</a>';
                },
                // Ссылка с текстом
                '#\[url=(.*?)\](.*?)\[/url\]#si' => function ($matches) {
                    $url = self::clean_field($matches[1]);
                    $text = self::clean_field($matches[2]);
                    if (
                        !filter_var($url, FILTER_VALIDATE_URL) ||
                        preg_match('/^(javascript|data|vbscript):/i', $url) ||
                        strlen($url) > 2000
                    ) {
                        log_in_file(
                            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный URL | Получено: '$url'"
                        );
                        return '<a href="#" title="#">A-a-a-a!</a>'; // Безопасное значение
                    }
                    return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" title="' . $text . '">' . $text . '</a>';
                },
                // Цвет текста
                '#\[color=(.*?)\](.*?)\[/color\]#si' => fn ($matches) => '<span style="color:' . self::clean_field($matches[1]) . ';">' . $process_recursively($matches[2], $depth + 1) . '</span>',
                // Размер текста
                '#\[size=(.*?)\](.*?)\[/size\]#si' => function ($matches) {
                    $size = (int)$matches[1];
                    $size = max(8, min(48, $size)); // Ограничение размера от 8px до 48px
                    return '<span style="font-size:' . $size . 'px;">' . $process_recursively($matches[2], $depth + 1) . '</span>';
                },
                // Цитирование
                '#\[quote\](.*?)\[/quote\]#si' => fn ($matches) => '<blockquote>' . $process_recursively($matches[1], $depth + 1) . '</blockquote>',
                '#\[quote=(.*?)\](.*?)\[/quote\]#si' => function ($matches) {
                    $author = self::clean_field($matches[1]); // Защита от XSS
                    return '<blockquote><strong>' . $author . ' писал:</strong><br />' . $process_recursively($matches[2], $depth + 1) . '</blockquote>';
                },
                // Списки
                '#\[list\](.*?)\[/list\]#si' => fn ($matches) => '<ul>' . preg_replace('#\[\*\](.*?)#si', '<li>$1</li>', $process_recursively($matches[1], $depth + 1)) . '</ul>',
                '#\[list=1\](.*?)\[/list\]#si' => fn ($matches) => '<ol type="1">' . preg_replace('#\[\*\](.*?)#si', '<li>$1</li>', $process_recursively($matches[1], $depth + 1)) . '</ol>',
                '#\[list=a\](.*?)\[/list\]#si' => fn ($matches) => '<ol type="a">' . preg_replace('#\[\*\](.*?)#si', '<li>$1</li>', $process_recursively($matches[1], $depth + 1)) . '</ol>',
                // Код
                '#\[code\](.*?)\[/code\]#si' => fn ($matches) => '<pre><code>' . self::clean_field($matches[1]) . '</code></pre>',
                // Спойлер
                '#\[spoiler\](.*?)\[/spoiler\]#si' => fn ($matches) => '<details><summary>Показать/скрыть</summary>' . $process_recursively($matches[1], $depth + 1) . '</details>',
                // Горизонтальная линия
                '[hr]' => '<hr />',
                // Перенос строки
                '[br]' => '<br />',
                // Выравнивание текста
                '#\[left\](.*?)\[/left\]#si' => fn ($matches) => '<p style="text-align:left;">' . $process_recursively($matches[1], $depth + 1) . '</p>',
                '#\[center\](.*?)\[/center\]#si' => fn ($matches) => '<p style="text-align:center;">' . $process_recursively($matches[1], $depth + 1) . '</p>',
                '#\[right\](.*?)\[/right\]#si' => fn ($matches) => '<p style="text-align:right;">' . $process_recursively($matches[1], $depth + 1) . '</p>',
                // Изображение
                '#\[img\](.*?)\[/img\]#si' => function ($matches) {
                    $src = self::clean_field($matches[1]);
                    if (!filter_var($src, FILTER_VALIDATE_URL)) {
                        log_in_file(
                            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный URL изображения | Получено: '$src'"
                        );
                        return ''; // Удаляем некорректные изображения
                    }
                    return '<img src="' . $src . '" alt="' . $src . '" />';
                },
            ];
            // Применяем паттерны к тексту
            return preg_replace_callback_array($patterns, $text);
        };
        // Начинаем обработку с глубины 0
        return $process_recursively($text, 0);
    }

    /**
     * @brief Разбивка строки на несколько строк ограниченной длины (защищённый метод).
     *
     * @details Этот защищённый метод разбивает строку на несколько строк, каждая из которых имеет длину не более указанной.
     *          Разрыв строки выполняется только по пробелам, чтобы сохранить читаемость текста.
     *          Поддерживается работа с UTF-8 символами.
     *          Если параметры некорректны (например, $width <= 0 или $break пустой), возвращается исходная строка.
     *
     * @param string $str Исходная строка.
     * @param int $width Максимальная длина строки (по умолчанию 70).
     * @param string $break Символ разрыва строки (по умолчанию PHP_EOL).
     * @return string Строка, разбитая на несколько строк.
     *
     * @see PhotoRigma::Classes::Work_Helper::utf8_wordwrap() Публичный метод, вызывающий этот защищённый метод.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     * @see PhotoRigma::Classes::Work::utf8_wordwrap() Этот метод вызывается через класс Work.
     */
    protected static function _utf8_wordwrap_internal(string $str, int $width = 70, string $break = PHP_EOL): string
    {
        // Проверка граничных условий
        if ($width <= 0 || empty($break)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректные параметры | width = {$width}, break = '{$break}'"
            );
            return $str;
        }
        // Если строка пустая или её длина меньше или равна $width, возвращаем её без изменений
        if (empty($str) || mb_strlen($str, 'UTF-8') <= $width) {
            return $str;
        }
        // Разбиваем строку с использованием wordwrap()
        $wrapped = wordwrap($str, $width, $break, true);
        // Если строка содержит многобайтовые символы, корректируем разбиение
        $lines = explode($break, $wrapped);
        $result = [];
        foreach ($lines as $line) {
            if (mb_strlen($line, 'UTF-8') > $width) {
                $result[] = mb_substr($line, 0, $width, 'UTF-8');
                $result[] = mb_substr($line, $width, null, 'UTF-8');
            } else {
                $result[] = $line;
            }
        }
        return implode($break, $result);
    }

    /**
     * @brief Проверка MIME-типа файла через доступные библиотеки (защищённый метод).
     *
     * @details Этот защищённый метод проверяет, поддерживается ли указанный MIME-тип хотя бы одной из доступных библиотек:
     *          - Imagick
     *          - Gmagick
     *          - GD
     *          Если MIME-тип не поддерживается ни одной библиотекой, возвращается false.
     *          Поддерживаются MIME-типы для изображений, таких как JPEG, PNG, GIF, WebP и другие.
     *
     * @param string $real_mime_type Реальный MIME-тип файла.
     * @return bool True, если MIME-тип поддерживается хотя бы одной библиотекой, иначе False.
     *
     * @see PhotoRigma::Classes::Work_Helper::validate_mime_type() Публичный метод, вызывающий этот защищённый метод.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     * @see PhotoRigma::Classes::Work::validate_mime_type() Этот метод вызывается через класс Work.
     */
    protected static function _validate_mime_type_internal(string $real_mime_type): bool
    {
        // Массив допустимых MIME-типов для каждой библиотеки
        $allowed_mime_types = [
            'gmagick' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/tiff', 'image/svg+xml', 'image/bmp', 'image/x-icon', 'image/avif', 'image/heic'],
            'imagick' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/tiff', 'image/svg+xml', 'image/bmp', 'image/x-icon', 'image/avif', 'image/heic', 'image/vnd.adobe.photoshop', 'image/x-canon-cr2', 'image/x-nikon-nef', 'image/x-xbitmap', 'image/x-portable-anymap', 'image/x-pcx'],
            'GD'      => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        ];
        // Флаг для отслеживания успешности проверки
        $is_mime_supported = false;
        // 1. Проверка через imagick
        if (extension_loaded('imagick')) {
            if (in_array($real_mime_type, $allowed_mime_types['imagick'], true)) {
                $is_mime_supported = true;
            } else {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | MIME-тип не поддерживается через imagick | Получено: '{$real_mime_type}'"
                );
            }
        }
        // 2. Проверка через gmagick (если imagick не поддерживает или не подключён)
        if (!$is_mime_supported && extension_loaded('gmagick')) {
            if (in_array($real_mime_type, $allowed_mime_types['gmagick'], true)) {
                $is_mime_supported = true;
            } else {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | MIME-тип не поддерживается через gmagick | Получено: '{$real_mime_type}'"
                );
            }
        }
        // 3. Проверка через GD (если ни imagick, ни gmagick не поддерживают)
        if (!$is_mime_supported) {
            if (in_array($real_mime_type, $allowed_mime_types['GD'], true)) {
                $is_mime_supported = true;
            } else {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | MIME-тип не поддерживается через GD | Получено: '{$real_mime_type}'"
                );
            }
        }
        return $is_mime_supported;
    }
}
