<?php

/**
 * @file      include/Work_Helper.php
 * @brief     Файл содержит класс Work_Helper, который предоставляет вспомогательные методы для работы с данными.
 *
 * @author    Dark Dayver
 * @version   0.4.4
 * @date      2025-05-07
 * @namespace PhotoRigma\\Classes
 *
 * @details   Этот файл содержит класс `Work_Helper`, который реализует вспомогательные методы для обработки данных.
 *            Класс предоставляет методы для очистки строк от HTML-тегов, преобразования размеров в байты,
 *            транслитерации строк, преобразования BBCode в HTML, разбиения строк на ограниченные по длине части
 *            и проверки MIME-типов. Все методы класса являются статическими.
 *
 * @section   WorkHelper_Main_Functions Основные функции
 *            - Очистка строк от HTML-тегов и специальных символов.
 *            - Преобразование размеров в байты.
 *            - Транслитерация строк и замена знаков пунктуации.
 *            - Преобразование BBCode в HTML.
 *            - Разбиение строк на несколько строк ограниченной длины.
 *            - Проверка MIME-типов файлов через доступные библиотеки.
 *
 * @see       PhotoRigma::Classes::Work
 *            Класс, через который вызываются методы для работы с данными.
 * @see       PhotoRigma::Include::log_in_file()
 *            Функция для логирования ошибок.
 *
 * @note      Этот файл является частью системы PhotoRigma и играет ключевую роль в обработке данных.
 *            Класс поддерживает работу с различными форматами данных, включая строки, числа, изображения и
 *            MIME-типы. Реализованы меры безопасности для предотвращения внедрения вредоносного кода.
 *
 * @copyright Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license   MIT License (https://opensource.org/licenses/MIT)
 *            Разрешается использовать, копировать, изменять, объединять, публиковать, распространять,
 *            сублицензировать и/или продавать копии программного обеспечения, а также разрешать лицам, которым
 *            предоставляется данное программное обеспечение, делать это при соблюдении следующих условий:
 *            - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые
 *              части программного обеспечения.
 */

namespace PhotoRigma\Classes;

use Exception;
use PhotoRigma\Interfaces\Work_Helper_Interface;
use Transliterator;

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
 * @class   Work_Helper
 * @brief   Класс для выполнения вспомогательных задач.
 *
 * @details Этот класс реализует интерфейс `Work_Helper_Interface` и предоставляет статические методы для выполнения
 *          различных вспомогательных задач. Он является дочерним классом для `PhotoRigma::Classes::Work` и наследует
 *          его методы. Все методы данного класса рекомендуется вызывать через родительский класс `Work`, так как их
 *          поведение может быть непредсказуемым при прямом вызове. Основные возможности:
 *          - Очистка строк от HTML-тегов и специальных символов (`clean_field`).
 *          - Преобразование размеров в байты (`return_bytes`).
 *          - Транслитерация строк и замена знаков пунктуации (`encodename`).
 *          - Преобразование BBCode в HTML (`ubb`).
 *          - Разбиение строк на несколько строк ограниченной длины (`utf8_wordwrap`).
 *          - Проверка MIME-типов файлов через доступные библиотеки (`validate_mime_type`).
 *          Методы класса предназначены для использования в различных частях приложения, таких как обработка
 *          пользовательского ввода, работа с файлами, генерация безопасного вывода и т.д.
 *
 * @implements Work_Helper_Interface
 *
 * Пример использования класса:
 * @code
 * // Очистка строки от HTML-тегов
 * $cleaned = \\PhotoRigma\\Classes\\Work::clean_field('<script>alert("XSS")</script>');
 * echo $cleaned; // Выведет: <script>alert(&quot;XSS&quot;)</script>
 *
 * // Преобразование размера в байты
 * $bytes = \\PhotoRigma\\Classes\\Work::return_bytes('2M');
 * echo $bytes; // Выведет: 2097152
 *
 * // Транслитерация строки
 * $encoded = \\PhotoRigma\\Classes\\Work::encodename('Привет, мир!');
 * echo $encoded; // Выведет: Privet_mir
 *
 * // Преобразование BBCode в HTML
 * $html = \\PhotoRigma\\Classes\\Work::ubb('[b]Bold text[/b]');
 * echo $html; // Выведет: <strong>Bold text</strong>
 *
 * // Разбиение строки на части
 * $wrapped = \\PhotoRigma\\Classes\\Work::utf8_wordwrap('This is a very long string that needs to be wrapped.', 10);
 * echo $wrapped;
 * // Выведет:
 * // This is a
 * // very long
 * // string that
 * // needs to be
 * // wrapped.
 *
 * // Проверка MIME-типа
 * $is_supported = \\PhotoRigma\\Classes\\Work::validate_mime_type('image/jpeg');
 * var_dump($is_supported); // Выведет: true
 * @endcode
 * @see    PhotoRigma::Interfaces::Work_Helper_Interface
 *         Интерфейс, который реализует данный класс.
 */
class Work_Helper implements Work_Helper_Interface
{
    /**
     * @brief   Очистка строки от HTML-тегов и специальных символов через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _clean_field_internal().
     *          Он удаляет HTML-теги и экранирует специальные символы (например, `<`, `>`, `&`, `"`, `'`) для защиты от
     *          XSS-атак. Метод также доступен через метод-фасад `clean_field()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @param string|int $field Строка или данные, которые могут быть преобразованы в строку.
     *                          Если входные данные пусты (`null` или пустая строка), метод вернёт пустую строку (`''`).
     *
     * @return string Очищенная строка или пустая строка (`''`), если входные данные пусты.
     *
     * @warning Метод не обрабатывает вложенные структуры данных (например, массивы).
     *          Убедитесь, что входные данные могут быть преобразованы в строку.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса
     * $dirty_input = '<script>alert("XSS")</script>';
     * $cleaned = Work_Helper::clean_field($dirty_input);
     * echo $cleaned; // Выведет: <script>alert(&quot;XSS&quot;)</script>
     * @endcode
     * @see    PhotoRigma::Classes::Work_Helper::_clean_field_internal()
     *         Защищённый метод, выполняющий очистку.
     * @see    PhotoRigma::Classes::Work::clean_field()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public static function clean_field(string|int $field): string
    {
        return self::_clean_field_internal($field);
    }

    /**
     * @brief   Очистка строки от HTML-тегов и специальных символов (защищённый метод).
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Проверяет, что входные данные не являются пустыми (`null` или пустая строка). Если данные пусты,
     *             возвращает пустую строку (`''`).
     *          2. Преобразует входные данные в кодировку UTF-8 с использованием `mb_convert_encoding`.
     *          3. Удаляет все HTML-теги из строки с помощью `strip_tags`.
     *          4. Экранирует специальные символы (например, `<`, `>`, `&`, `"`, `'`) с использованием
     *             `htmlspecialchars` и флагов `ENT_QUOTES | ENT_HTML5`. Этот метод используется для защиты от
     *             XSS-атак и других проблем, связанных с некорректными данными. Он является защищённым и
     *             предназначен для использования внутри класса или его наследников. Основная логика метода
     *             вызывается через публичный метод `clean_field()`.
     *
     * @callergraph
     *
     * @param string|int $field Строка или данные, которые могут быть преобразованы в строку.
     *                          Если входные данные пусты (`null` или пустая строка), метод вернёт пустую строку (`''`).
     *
     * @return string Очищенная строка или пустая строка (`''`), если входные данные пусты.
     *
     * @warning Метод не обрабатывает вложенные структуры данных (например, массивы).
     *          Убедитесь, что входные данные могут быть преобразованы в строку.
     *
     * Пример использования:
     * @code
     * // Вызов защищённого метода внутри класса
     * $dirty_input = '<script>alert("XSS")</script>';
     * $cleaned = self::_clean_field_internal($dirty_input);
     * echo $cleaned; // Выведет: <script>alert(&quot;XSS&quot;)</script>
     * @endcode
     * @see    PhotoRigma::Classes::Work_Helper::clean_field()
     *         Публичный метод, вызывающий этот защищённый метод.
     */
    protected static function _clean_field_internal(string|int $field): string
    {
        // Если входные данные пусты, возвращаем null
        if (trim($field) === '') {
            return '';
        }
        // Гарантируем корректную кодировку UTF-8 и удаляем HTML-теги
        $field = strip_tags(mb_convert_encoding($field, 'UTF-8', 'auto'));
        // Экранируем специальные символы с использованием современных флагов
        return htmlspecialchars($field, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @brief   Транслитерация строки и замена знаков пунктуации на "_" через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _encodename_internal().
     *          Он выполняет транслитерацию не латинских символов в латиницу и заменяет знаки пунктуации на "_".
     *          Используется для создания "безопасных" имён файлов или URL. Метод также доступен через метод-фасад
     *          `encodename()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $string Исходная строка:
     *                       - Если строка пустая, то она возвращается без обработки.
     *                       Рекомендуется использовать строки в кодировке UTF-8.
     *
     * @return string Строка после транслитерации и замены символов.
     *                Если после обработки строка становится пустой, генерируется уникальная последовательность.
     *
     * @warning Если расширение `intl` недоступно, используется резервная таблица транслитерации.
     *          Метод не гарантирует сохранение исходного формата строки, так как все специальные символы заменяются на
     *          `"_"`. Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса
     * $encoded = Work_Helper::encodename('Привет, мир!');
     * echo $encoded; // Выведет: Privet_mir
     *
     * // Обработка пустой строки
     * $encoded = Work_Helper::encodename('');
     * echo $encoded; // Выведет: пустую строку
     *
     * // Обработка строки без кириллицы и знаков пунктуации
     * $encoded = Work_Helper::encodename('12345');
     * echo $encoded; // Выведет: 12345
     *
     * // Генерация уникальной последовательности для пустой строки после обработки
     * $encoded = Work_Helper::encodename('!!!');
     * echo $encoded; // Выведет: уникальную последовательность из 16 символов
     * @endcode
     * @see    PhotoRigma::Classes::Work_Helper::_encodename_internal()
     *         Защищённый метод, выполняющий транслитерацию.
     * @see    PhotoRigma::Classes::Work::encodename()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public static function encodename(string $string): string
    {
        return self::_encodename_internal($string);
    }

    /**
     * @brief   Транслитерация строки и замена знаков пунктуации на "_" (защищённый метод).
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Проверяет, что входная строка не является пустой. Если строка пустая, она возвращается без
     *             обработки.
     *          2. Выполняет транслитерацию не латинских символов в латиницу:
     *             - Если расширение `intl` доступно, используется метод `Transliterator::create('Any-Latin;
     *             Latin-ASCII')`.
     *             - Если расширение `intl` недоступно, используется резервная таблица транслитерации.
     *          3. Заменяет все символы, кроме букв и цифр, на `"_"`:
     *             - Заменяет множественные подчёркивания на одно.
     *             - Удаляет подчёркивания в начале и конце строки.
     *          4. Если после обработки строка становится пустой, генерируется уникальная последовательность длиной 16
     *             символов с использованием `md5(uniqid(microtime(true), true))`.
     *          Этот метод используется для создания "безопасных" имен файлов или URL. Он является защищённым и
     *          предназначен для использования внутри класса или его наследников. Основная логика метода вызывается
     *          через публичный метод
     *          `encodename()`.
     *
     * @callergraph
     *
     * @param string $string Исходная строка.
     *                       Если строка пустая, она возвращается без обработки.
     *                       Рекомендуется использовать строки в кодировке UTF-8.
     *
     * @return string Строка после транслитерации и замены символов.
     *                Если после обработки строка становится пустой, генерируется уникальная последовательность.
     *
     * @warning Если расширение `intl` недоступно, используется резервная таблица транслитерации.
     *          Метод не гарантирует сохранение исходного формата строки, так как все специальные символы заменяются на
     *          `"_"`.
     *
     * Пример использования:
     * @code
     * // Транслитерация строки с заменой знаков пунктуации
     * $encoded = self::_encodename_internal('Привет, мир!');
     * echo $encoded; // Выведет: Privet_mir
     *
     * // Обработка пустой строки
     * $encoded = self->_encodename_internal('');
     * echo $encoded; // Выведет: пустую строку
     *
     * // Обработка строки без кириллицы и знаков пунктуации
     * $encoded = self::_encodename_internal('12345');
     * echo $encoded; // Выведет: 12345
     *
     * // Генерация уникальной последовательности для пустой строки после обработки
     * $encoded = self::_encodename_internal('!!!');
     * echo $encoded; // Выведет: уникальную последовательность из 16 символов
     * @endcode
     * @see    PhotoRigma::Classes::Work_Helper::encodename()
     *         Публичный метод, вызывающий этот защищённый метод.
     */
    protected static function _encodename_internal(string $string): string
    {
        // Если входная строка пустая, возвращаем её без обработки
        if (empty($string)) {
            return $string;
        }
        // Таблица транслитерации
        $table = [
            'А' => 'A',
            'Б' => 'B',
            'В' => 'V',
            'Г' => 'G',
            'Д' => 'D',
            'Е' => 'E',
            'Ё' => 'YO',
            'Ж' => 'ZH',
            'З' => 'Z',
            'И' => 'I',
            'Й' => 'J',
            'К' => 'K',
            'Л' => 'L',
            'М' => 'M',
            'Н' => 'N',
            'О' => 'O',
            'П' => 'P',
            'Р' => 'R',
            'С' => 'S',
            'Т' => 'T',
            'У' => 'U',
            'Ф' => 'F',
            'Х' => 'H',
            'Ц' => 'C',
            'Ч' => 'CH',
            'Ш' => 'SH',
            'Щ' => 'CSH',
            'Ь' => '',
            'Ы' => 'Y',
            'Ъ' => '',
            'Э' => 'E',
            'Ю' => 'YU',
            'Я' => 'YA',
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'yo',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'j',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'c',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'csh',
            'ь' => '',
            'ы' => 'y',
            'ъ' => '',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',
        ];
        // Транслитерация строки
        if (extension_loaded('intl')) {
            // Используем расширение intl, если оно доступно
            $transliterator = Transliterator::create('Any-Latin; Latin-ASCII');
            if ($transliterator !== null) {
                $string = (string)$transliterator->transliterate($string);
            }
        } else {
            // Если расширение intl недоступно, используем таблицу транслитерации
            $string = strtr($string, $table);
        }
        // Замена специальных символов на "_" и удаление лишних подчёркиваний
        // Заменяем все символы, кроме букв и цифр  на "_"
        $string = (string)preg_replace(
            ['/[^a-zA-Z0-9]/', '/_{2,}/'],
            '_',
            $string
        ); // Заменяем множественные подчёркивания на одно
        $string = trim($string, '_'); // Удаляем подчёркивания в начале и конце строки
        // Если после обработки строка пустая, генерируем уникальную последовательность
        if (empty($string)) {
            $string = substr(md5(uniqid(microtime(true), true)), 0, 16);
        }
        return $string;
    }

    /**
     * @brief   Преобразует размер в байты через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _return_bytes_internal().
     *          Он преобразует размер, заданный в формате "число[K|M|G]", в количество байт.
     *          Метод также доступен через метод-фасад `return_bytes()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @param string|int $val Размер в формате "число[K|M|G]" или число:
     *                        - Если суффикс недопустим, он игнорируется, и значение преобразуется в число.
     *                        - Отрицательные числа преобразуются в положительные.
     *
     * @return int Размер в байтах. Возвращает `0` для некорректных входных данных.
     *
     * @warning Если суффикс недопустим, он игнорируется, и значение преобразуется в число.
     *          Метод чувствителен к формату входных данных. Убедитесь, что они корректны.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Преобразование с допустимым суффиксом
     * $bytes = Work_Helper::return_bytes('2M');
     * echo $bytes; // Выведет: 2097152
     *
     * // Преобразование отрицательного значения
     * $bytes = Work_Helper::return_bytes('-1G');
     * echo $bytes; // Выведет: 1073741824
     *
     * // Преобразование с недопустимым суффиксом
     * $bytes = Work_Helper::return_bytes('10X');
     * echo $bytes; // Выведет: 10
     *
     * // Преобразование некорректных данных
     * $bytes = Work_Helper::return_bytes('abc');
     * echo $bytes; // Выведет: 0
     * @endcode
     * @see    PhotoRigma::Classes::Work_Helper::_return_bytes_internal()
     *         Защищённый метод, выполняющий преобразование.
     * @see    PhotoRigma::Classes::Work::return_bytes()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public static function return_bytes(string|int $val): int
    {
        return self::_return_bytes_internal($val);
    }

    /**
     * @brief   Преобразование размера в байты (защищённый метод).
     *
     * @details Этот защищённый метод преобразует размер, заданный в формате "число[K|M|G]", в количество байт.
     *          Поддерживаются следующие суффиксы:
     *          - K (килобайты): умножается на 1024.
     *          - M (мегабайты): умножается на 1024².
     *          - G (гигабайты): умножается на 1024³.
     *          Если суффикс отсутствует или недопустим, значение считается в байтах.
     *          Отрицательные числа преобразуются в положительные.
     *          Если входные данные некорректны, возвращается `0`.
     *          Этот метод является защищённым и предназначен для использования внутри класса или его наследников.
     *          Основная логика метода вызывается через публичный метод `return_bytes()`.
     *
     * @callergraph
     *
     * @param string|int $val Размер в формате "число[K|M|G]" или число.
     *                        Если суффикс недопустим, он игнорируется, и значение преобразуется в число.
     *                        Отрицательные числа преобразуются в положительные.
     *
     * @return int Размер в байтах. Возвращает `0` для некорректных входных данных.
     *
     * @warning Если суффикс недопустим, он игнорируется, и значение преобразуется в число.
     *          Метод чувствителен к формату входных данных. Убедитесь, что они корректны.
     *
     * Пример использования:
     * @code
     * // Преобразование с допустимым суффиксом
     * $bytes = self::_return_bytes_internal('2M');
     * echo $bytes; // Выведет: 2097152
     *
     * // Преобразование отрицательного значения
     * $bytes = self::_return_bytes_internal('-1G');
     * echo $bytes; // Выведет: 1073741824
     *
     * // Преобразование с недопустимым суффиксом
     * $bytes = self::_return_bytes_internal('10X');
     * echo $bytes; // Выведет: 10
     *
     * // Преобразование некорректных данных
     * $bytes = self::_return_bytes_internal('abc');
     * echo $bytes; // Выведет: 0
     * @endcode
     * @see    PhotoRigma::Classes::Work_Helper::return_bytes()
     *         Публичный метод, вызывающий этот защищённый метод.
     */
    protected static function _return_bytes_internal(string|int $val): int
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
        // Извлекаем числовую часть (все символы, кроме последнего)
        $number = substr($val, 0, -1);
        // Проверяем, является ли числовая часть корректной
        if (!is_numeric($number)) {
            return 0; // Возвращаем 0 для некорректных данных
        }
        // Преобразуем в абсолютное значение
        $number = abs((float)$number);
        // Преобразуем в байты с использованием match
        return match ($last) {
            'g'     => (int)($number * 1024 * 1024 * 1024),
            'm'     => (int)($number * 1024 * 1024),
            'k'     => (int)($number * 1024),
            default => (int)$number, // Если суффикс недопустим, возвращаем только числовую часть
        };
    }

    /**
     * @brief   Преобразует BBCode в HTML через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _ubb_internal().
     *          Он преобразует BBCode-теги в HTML-теги. Метод также доступен через метод-фасад `ubb()` в родительском
     *          классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $text Текст с BBCode:
     *                     - Рекомендуется использовать строки в кодировке UTF-8.
     *                     - Если строка пустая, она возвращается без обработки.
     *
     * @return string Текст с HTML-разметкой.
     *                Некорректные BBCode-теги игнорируются или преобразуются в текст.
     *
     * @throws Exception Если произошла ошибка при рекурсивной обработке BBCode.
     *
     * @warning Метод ограничивает глубину рекурсии для вложенных тегов (максимум 10 уровней).
     *          Некорректные URL или изображения заменяются на безопасные значения или удаляются.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Преобразование жирного текста
     * $html = Work_Helper::ubb('[b]Bold text[/b]');
     * echo $html; // Выведет: <strong>Bold text</strong>
     *
     * // Преобразование ссылки
     * $html = Work_Helper::ubb('[url=https://example.com]Example[/url]');
     * echo $html; // Выведет: <a href="https://example.com" target="_blank" rel="noopener noreferrer"
     * title="Example">Example</a>
     * @endcode
     * @see    PhotoRigma::Classes::Work_Helper::_ubb_internal()
     *         Защищённый метод, выполняющий преобразование BBCode.
     * @see    PhotoRigma::Classes::Work::ubb()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public static function ubb(string $text): string
    {
        return self::_ubb_internal($text);
    }

    /**
     * @brief   Преобразование BBCode в HTML (защищённый метод).
     *
     * @details Этот защищённый метод преобразует BBCode-теги в соответствующие HTML-теги с учетом рекурсивной
     *          обработки вложенных тегов. Поддерживаются следующие BBCode-теги:
     *          - [b]Жирный текст[/b] -> `<strong>Жирный текст</strong>`
     *          - [u]Подчёркнутый текст[/u] -> `<u>Подчёркнутый текст</u>`
     *          - [i]Курсив[/i] -> `<em>Курсив</em>`
     *          - [url]Ссылка[/url], [url=URL]Текст ссылки[/url] -> `<a href="URL" target="_blank" rel="noopener
     *            noreferrer">Текст ссылки</a>`
     *          - [color=COLOR]Цвет текста[/color] -> `<span style="color: COLOR;">Цвет текста</span>`
     *          - [size=SIZE]Размер текста[/size] -> `<span style="font-size: SIZE;">Размер текста</span>`
     *          - [quote]Цитата[/quote], [quote=AUTHOR]Цитата автора[/quote] -> `<blockquote>Цитата</blockquote>`
     *          - [list], [list=1], [list=a] — списки -> `<ul>`, `<ol>` с соответствующими элементами `<li>`
     *          - [code]Блок кода[/code] -> `<pre><code>Блок кода</code></pre>`
     *          - [spoiler]Спойлер[/spoiler] -> `<details><summary>Спойлер</summary>Содержимое</details>`
     *          - [hr] — горизонтальная линия -> `<hr>`
     *          - [br] — перенос строки -> `<br>`
     *          - [left], [center], [right] — выравнивание текста -> `<div style="text-align: left|center|right;">`
     *          - [img]Изображение[/img] -> `<img src="URL" alt="Изображение">`
     *          Метод защищает от XSS-атак, проверяет корректность URL и ограничивает глубину рекурсии для вложенных
     *          тегов. Этот метод является защищённым и предназначен для использования внутри класса или его
     *          наследников. Основная логика метода вызывается через публичный метод `ubb()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $text Текст с BBCode.
     *                     Рекомендуется использовать строки в кодировке UTF-8.
     *                     Если строка пустая, она возвращается без обработки.
     *
     * @return string Текст с HTML-разметкой.
     *                Некорректные BBCode-теги игнорируются или преобразуются в текст.
     *
     * @throws Exception Если произошла ошибка при рекурсивной обработке BBCode.
     *
     * @warning Метод ограничивает глубину рекурсии для вложенных тегов (максимум 10 уровней).
     *          Некорректные URL или изображения заменяются на безопасные значения или удаляются.
     *
     * Пример использования:
     * @code
     * // Преобразование жирного текста
     * $html = self::_ubb_internal('[b]Bold text[/b]');
     * echo $html; // Выведет: <strong>Bold text</strong>
     *
     * // Преобразование ссылки
     * $html = self::_ubb_internal('[url=https://example.com]Example[/url]');
     * echo $html; // Выведет: <a href="https://example.com" target="_blank" rel="noopener noreferrer"
     * title="Example">Example</a>
     *
     * // Преобразование цитаты
     * $html = self::_ubb_internal('[quote=Author]This is a quote.[/quote]');
     * echo $html; // Выведет: <blockquote><cite>Author:</cite>This is a quote.</blockquote>
     *
     * // Преобразование списка
     * $html = self::_ubb_internal('[list][*]Item 1[*]Item 2[/list]');
     * echo $html; // Выведет: <ul><li>Item 1</li><li>Item 2</li></ul>
     * @endcode
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования ошибок.
     * @see    PhotoRigma::Classes::Work_Helper::_process_bbcode_recursively()
     *         Приватный метод для рекурсивной обработки BBCode.
     * @see    PhotoRigma::Classes::Work_Helper::ubb()
     *         Публичный метод, вызывающий этот защищённый метод.
     */
    protected static function _ubb_internal(string $text): string
    {
        // Очищаем текст от потенциально опасных символов
        $text = self::_clean_field_internal($text);
        // Максимальная глубина рекурсии для вложенных BBCode
        $max_recursion_depth = 10;

        // Начинаем обработку с глубины 0
        return self::_process_bbcode_recursively($text, 0, $max_recursion_depth);
    }

    /**
     * @brief   Рекурсивно преобразует BBCode в HTML, проверяя глубину рекурсии и корректность тегов, а также защищая
     *          от XSS-атак.
     *
     * @details Этот приватный метод выполняет следующие действия:
     *          1. Проверяет текущую глубину рекурсии. Если она превышает максимальную, обработка прекращается.
     *          2. Ищет BBCode-теги в тексте с помощью регулярных выражений.
     *          3. Преобразует найденные BBCode-теги в соответствующую HTML-разметку:
     *             - Простые теги (например, `[b]`, `[i]`, `[u]`) преобразуются в HTML-теги (`<strong>`, `<em>`,
     *               `<u>`).
     *             - Сложные теги (например, `[url]`, `[img]`) включают дополнительные проверки (например, валидация
     *               URL).
     *          4. Рекурсивно обрабатывает вложенные BBCode-теги, увеличивая глубину рекурсии.
     *          5. Защищает от XSS-атак с помощью метода `_clean_field_internal`.
     *          6. Логирует ошибки при обнаружении некорректных данных (например, невалидный URL) с помощью
     *            `log_in_file`. Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param string $text                Текст для обработки.
     *                                    Должен быть строкой, содержащей BBCode-теги.
     *                                    Может содержать вложенные BBCode-теги.
     * @param int    $depth               Текущая глубина рекурсии.
     *                                    Должен быть целым числом.
     *                                    Не должен превышать `$max_recursion_depth`.
     * @param int    $max_recursion_depth Максимальная глубина рекурсии.
     *                                    Должен быть положительным целым числом.
     *
     * @return string Возвращает текст, преобразованный из BBCode в HTML.
     *                Содержит HTML-разметку, полученную из BBCode-тегов.
     *
     * @throws Exception Может быть выброшено вызываемой функцией `log_in_file` при записи ошибок в лог.
     *
     * @note    Метод использует рекурсию для обработки вложенных BBCode-тегов.
     *          Для защиты от XSS используется метод `_clean_field_internal`.
     *
     * @warning Не используйте слишком большую глубину рекурсии, чтобы избежать переполнения стека.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $text = '[b]Жирный текст[/b] [url=https://example.com]Ссылка[/url]';
     * $result = self::_process_bbcode_recursively($text, 0, 10);
     * echo $result; // <strong>Жирный текст</strong> <a href="https://example.com" target="_blank" rel="noopener
     * noreferrer">Ссылка</a>
     * @endcode
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования ошибок.
     * @see    PhotoRigma::Classes::Work_Helper::_clean_field_internal()
     *         Метод для очистки данных (защита от XSS).
     * @see    PhotoRigma::Classes::Work_Helper::_ubb_internal()
     *         Метод, вызывающий текущий метод.
     */
    private static function _process_bbcode_recursively(string $text, int $depth, int $max_recursion_depth): string
    {
        if ($depth > $max_recursion_depth) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Превышена максимальная глубина рекурсии при обработке BBCode'
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
            '#\[b\](.*?)\[/b\]#si'               => fn ($matches) => '<strong>' . self::_process_bbcode_recursively(
                $matches[1],
                $depth + 1,
                $max_recursion_depth
            ) . '</strong>',
            // Подчёркнутый текст
            '#\[u\](.*?)\[/u\]#si'               => fn ($matches) => '<u>' . self::_process_bbcode_recursively(
                $matches[1],
                $depth + 1,
                $max_recursion_depth
            ) . '</u>',
            // Курсив
            '#\[i\](.*?)\[/i\]#si'               => fn ($matches) => '<em>' . self::_process_bbcode_recursively(
                $matches[1],
                $depth + 1,
                $max_recursion_depth
            ) . '</em>',
            // Простая ссылка
            '#\[url\](.*?)\[/url\]#si'           => function ($matches) {
                $url = self::_clean_field_internal($matches[1]);
                if (!filter_var($url, FILTER_VALIDATE_URL) || preg_match(
                    '/^(javascript|data|vbscript):/i',
                    $url
                ) || strlen($url) > 2000) {
                    log_in_file(
                        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный URL | Получено: '$url'"
                    );
                    return '<a href="#" title="#">A-a-a-a!</a>'; // Безопасное значение
                }
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" title="' . $url . '">' . $url . '</a>';
            },
            // Ссылка с текстом
            '#\[url=(.*?)\](.*?)\[/url\]#si'     => function ($matches) {
                $url = self::_clean_field_internal($matches[1]);
                $text = self::_clean_field_internal($matches[2]);
                if (!filter_var($url, FILTER_VALIDATE_URL) || preg_match(
                    '/^(javascript|data|vbscript):/i',
                    $url
                ) || strlen($url) > 2000) {
                    log_in_file(
                        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный URL | Получено: '$url'"
                    );
                    return '<a href="#" title="#">A-a-a-a!</a>'; // Безопасное значение
                }
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" title="' . $text . '">' . $text . '</a>';
            },
            // Цвет текста
            '#\[color=(.*?)\](.*?)\[/color\]#si' => fn ($matches) => '<span style="color:' . self::_clean_field_internal(
                $matches[1]
            ) . ';">' . self::_process_bbcode_recursively(
                $matches[2],
                $depth + 1,
                $max_recursion_depth
            ) . '</span>',
            // Размер текста
            '#\[size=(.*?)\](.*?)\[/size\]#si'   => function ($matches) use ($depth, $max_recursion_depth) {
                $size = (int)$matches[1];
                $size = max(8, min(48, $size)); // Ограничение размера от 8px до 48px
                return '<span style="font-size:' . $size . 'px;">' . self::_process_bbcode_recursively(
                    $matches[2],
                    $depth + 1,
                    $max_recursion_depth
                ) . '</span>';
            },
            // Цитирование
            '#\[quote\](.*?)\[/quote\]#si'       => fn ($matches) => '<blockquote>' . self::_process_bbcode_recursively(
                $matches[1],
                $depth + 1,
                $max_recursion_depth
            ) . '</blockquote>',
            '#\[quote=(.*?)\](.*?)\[/quote\]#si' => function ($matches) use ($depth, $max_recursion_depth) {
                $author = self::_clean_field_internal($matches[1]); // Защита от XSS
                return '<blockquote><strong>' . $author . ' писал:</strong><br />' . self::_process_bbcode_recursively(
                    $matches[2],
                    $depth + 1,
                    $max_recursion_depth
                ) . '</blockquote>';
            },
            // Списки
            '#\[list\](.*?)\[/list\]#si'         => fn ($matches) => '<ul>' . preg_replace(
                '#\[\*\](.*?)#s',
                '<li>$1</li>',
                self::_process_bbcode_recursively($matches[1], $depth + 1, $max_recursion_depth)
            ) . '</ul>',
            '#\[list=1\](.*?)\[/list\]#si'       => fn ($matches) => '<ol type="1">' . preg_replace(
                '#\[\*\](.*?)#s',
                '<li>$1</li>',
                self::_process_bbcode_recursively($matches[1], $depth + 1, $max_recursion_depth)
            ) . '</ol>',
            '#\[list=a\](.*?)\[/list\]#si'       => fn ($matches) => '<ol type="a">' . preg_replace(
                '#\[\*\](.*?)#s',
                '<li>$1</li>',
                self::_process_bbcode_recursively($matches[1], $depth + 1, $max_recursion_depth)
            ) . '</ol>',
            // Код
            '#\[code\](.*?)\[/code\]#si'         => fn ($matches) => '<pre><code>' . self::_clean_field_internal(
                $matches[1]
            ) . '</code></pre>',
            // Спойлер
            '#\[spoiler\](.*?)\[/spoiler\]#si'   => fn (
                $matches
            ) => '<details><summary>Показать/скрыть</summary>' . self::_process_bbcode_recursively(
                $matches[1],
                $depth + 1,
                $max_recursion_depth
            ) . '</details>',
            // Горизонтальная линия
            '[hr]'                               => fn () => '<hr />',
            // Исправлено: строка заменена на анонимную функцию
            // Перенос строки
            '[br]'                               => fn () => '<br />',
            // Исправлено: строка заменена на анонимную функцию
            // Выравнивание текста
            '#\[left\](.*?)\[/left\]#si'         => fn (
                $matches
            ) => '<p style="text-align:left;">' . self::_process_bbcode_recursively(
                $matches[1],
                $depth + 1,
                $max_recursion_depth
            ) . '</p>',
            '#\[center\](.*?)\[/center\]#si'     => fn (
                $matches
            ) => '<p style="text-align:center;">' . self::_process_bbcode_recursively(
                $matches[1],
                $depth + 1,
                $max_recursion_depth
            ) . '</p>',
            '#\[right\](.*?)\[/right\]#si'       => fn (
                $matches
            ) => '<p style="text-align:right;">' . self::_process_bbcode_recursively(
                $matches[1],
                $depth + 1,
                $max_recursion_depth
            ) . '</p>',
            // Изображение
            '#\[img\](.*?)\[/img\]#si'           => function ($matches) {
                $src = self::_clean_field_internal($matches[1]);
                if (!filter_var($src, FILTER_VALIDATE_URL)) {
                    log_in_file(
                        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный URL изображения | Получено: '$src'"
                    );
                    return ''; // Удаляем некорректные изображения
                }
                return '<img src="' . $src . '" alt="' . $src . '" />';
            },
        ];

        // Применяем паттерны к тексту
        return preg_replace_callback_array($patterns, $text);
    }

    /**
     * @brief   Разбивает строку на несколько строк ограниченной длины через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _utf8_wordwrap_internal().
     *          Он разбивает строку на несколько строк, каждая из которых имеет длину не более указанной.
     *          Метод также доступен через метод-фасад `utf8_wordwrap()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $str   Исходная строка:
     *                      - Рекомендуется использовать строки в кодировке UTF-8.
     *                      - Если строка пустая или её длина меньше или равна $width, она возвращается без изменений.
     * @param int    $width Максимальная длина строки (по умолчанию 70).
     *                      Должен быть положительным целым числом.
     * @param string $break Символ разрыва строки (по умолчанию PHP_EOL).
     *                      Не должен быть пустой строкой.
     *
     * @return string Строка, разбитая на несколько строк.
     *                В случае некорректных параметров возвращается исходная строка.
     *
     * @throws Exception Если произошла ошибка при разбиении строки.
     *
     * @warning Метод корректно работает только с UTF-8 символами.
     *          Если параметры некорректны (например, $width <= 0 или $break пустой), возвращается исходная строка.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Разбивка строки на части длиной 10 символов
     * $wrapped = Work_Helper::utf8_wordwrap('This is a very long string that needs to be wrapped.', 10);
     * echo $wrapped;
     * // Выведет:
     * // This is a
     * // very long
     * // string that
     * // needs to be
     * // wrapped.
     * @endcode
     * @see    PhotoRigma::Classes::Work_Helper::_utf8_wordwrap_internal()
     *         Защищённый метод, выполняющий разбиение строки.
     * @see    PhotoRigma::Classes::Work::utf8_wordwrap()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public static function utf8_wordwrap(string $str, int $width = 70, string $break = PHP_EOL): string
    {
        return self::_utf8_wordwrap_internal($str, $width, $break);
    }

    /**
     * @brief   Разбивка строки на несколько строк ограниченной длины (защищённый метод).
     *
     * @details Этот защищённый метод разбивает строку на несколько строк, каждая из которых имеет длину не более
     *          указанной. Разрыв строки выполняется только по пробелам, чтобы сохранить читаемость текста.
     *          Поддерживается работа с UTF-8 символами. Если параметры некорректны (например, $width <= 0 или $break
     *          пустой), возвращается исходная строка. Этот метод является защищённым и предназначен для использования
     *          внутри класса или его наследников. Основная логика метода вызывается через публичный метод
     *          `utf8_wordwrap()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $str   Исходная строка.
     *                      Рекомендуется использовать строки в кодировке UTF-8.
     *                      Если строка пустая или её длина меньше или равна $width, она возвращается без изменений.
     * @param int    $width Максимальная длина строки (по умолчанию 70).
     *                      Должен быть положительным целым числом.
     * @param string $break Символ разрыва строки (по умолчанию PHP_EOL).
     *                      Не должен быть пустой строкой.
     *
     * @return string Строка, разбитая на несколько строк.
     *                В случае некорректных параметров возвращается исходная строка.
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
     * $wrapped = self::_utf8_wordwrap_internal('This is a very long string that needs to be wrapped.', 10);
     * echo $wrapped;
     * // Выведет:
     * // This is a
     * // very long
     * // string that
     * // needs to be
     * // wrapped.
     *
     * // Разбивка строки с пользовательским символом разрыва
     * $wrapped = self::_utf8_wordwrap_internal('This is another example.', 15, '---');
     * echo $wrapped;
     * // Выведет:
     * // This is another---
     * // example.
     *
     * // Некорректные параметры
     * $wrapped = self::_utf8_wordwrap_internal('Short text', 0);
     * echo $wrapped; // Выведет: Short text
     * @endcode
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования ошибок.
     * @see    PhotoRigma::Classes::Work_Helper::utf8_wordwrap()
     *         Публичный метод, вызывающий этот защищённый метод.
     */
    protected static function _utf8_wordwrap_internal(string $str, int $width = 70, string $break = PHP_EOL): string
    {
        // Проверка граничных условий
        if ($width <= 0 || empty($break)) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректные параметры | width = $width, break = '$break'"
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
     * @brief   Проверяет MIME-тип файла через доступные библиотеки через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _validate_mime_type_internal().
     *          Он проверяет, поддерживается ли указанный MIME-тип хотя бы одной из доступных библиотек:
     *          - Imagick
     *          - Gmagick
     *          - Встроенные функции PHP (GD).
     *          Метод также доступен через метод-фасад `validate_mime_type()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $real_mime_type Реальный MIME-тип файла:
     *                               - Должен быть корректным MIME-типом для изображений.
     *
     * @return bool True, если MIME-тип поддерживается хотя бы одной библиотекой, иначе false.
     *
     * @throws Exception Если произошла ошибка при проверке MIME-типа.
     *
     * @warning Метод зависит от доступности библиотек (Imagick, Gmagick) и встроенных функций PHP по работе с
     *          изображениями (GD). Если ни одна из библиотек недоступна, метод может некорректно работать. Вызывать
     *          этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Проверка поддерживаемого MIME-типа
     * $is_supported = Work_Helper::validate_mime_type('image/jpeg');
     * var_dump($is_supported); // Выведет: true
     *
     * // Проверка неподдерживаемого MIME-типа
     * $is_supported = Work_Helper::validate_mime_type('application/pdf');
     * var_dump($is_supported); // Выведет: false
     * @endcode
     * @see    PhotoRigma::Classes::Work_Helper::_validate_mime_type_internal()
     *         Защищённый метод, выполняющий проверку MIME-типа.
     * @see    PhotoRigma::Classes::Work::validate_mime_type()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public static function validate_mime_type(string $real_mime_type): bool
    {
        return self::_validate_mime_type_internal($real_mime_type);
    }

    /**
     * @brief   Проверка MIME-типа файла через доступные библиотеки (защищённый метод).
     *
     * @details Этот защищённый метод проверяет, поддерживается ли указанный MIME-тип хотя бы одной из доступных
     *          библиотек:
     *          - Imagick: Поддерживает широкий спектр форматов, включая JPEG, PNG, GIF, WebP, TIFF, SVG, BMP, ICO,
     *            AVIF, HEIC, PSD, CR2, NEF и другие.
     *          - Gmagick: Поддерживает основные форматы, такие как JPEG, PNG, GIF, WebP, TIFF, SVG, BMP, ICO, AVIF,
     *            HEIC.
     *          - Встроенные функции PHP (GD): Поддерживаются только базовые форматы, такие как JPEG, PNG, GIF, WebP.
     *          Проверка выполняется последовательно: сначала через Imagick, затем через Gmagick, и в конце через GD.
     *          Если MIME-тип не поддерживается ни одной библиотекой, возвращается `false`.
     *          Логируются случаи, когда MIME-тип не поддерживается конкретной библиотекой.
     *          Этот метод является защищённым и предназначен для использования внутри класса или его наследников.
     *          Основная логика метода вызывается через публичный метод `validate_mime_type()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $real_mime_type Реальный MIME-тип файла.
     *                               Должен быть корректным MIME-типом для изображений.
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
     * $is_supported = self::_validate_mime_type_internal('image/jpeg');
     * var_dump($is_supported); // Выведет: true
     *
     * // Проверка неподдерживаемого MIME-типа
     * $is_supported = self::_validate_mime_type_internal('application/pdf');
     * var_dump($is_supported); // Выведет: false
     *
     * // Проверка MIME-типа, поддерживаемого только через Imagick
     * $is_supported = self::_validate_mime_type_internal('image/vnd.adobe.photoshop');
     * var_dump($is_supported); // Выведет: true (если Imagick доступен)
     * @endcode
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования ошибок.
     * @see    PhotoRigma::Classes::Work_Helper::validate_mime_type()
     *         Публичный метод, вызывающий этот защищённый метод.
     */
    protected static function _validate_mime_type_internal(string $real_mime_type): bool
    {
        // Массив допустимых MIME-типов для каждой библиотеки
        $allowed_mime_types = [
            'gmagick' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/tiff',
                'image/svg+xml',
                'image/bmp',
                'image/x-icon',
                'image/avif',
                'image/heic',
            ],
            'imagick' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/tiff',
                'image/svg+xml',
                'image/bmp',
                'image/x-icon',
                'image/avif',
                'image/heic',
                'image/vnd.adobe.photoshop',
                'image/x-canon-cr2',
                'image/x-nikon-nef',
                'image/x-xbitmap',
                'image/x-portable-anymap',
                'image/x-pcx',
            ],
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
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | MIME-тип не поддерживается через imagick | Получено: '$real_mime_type'"
                );
            }
        }
        // 2. Проверка через gmagick (если imagick не поддерживает или не подключён)
        if (!$is_mime_supported && extension_loaded('gmagick')) {
            if (in_array($real_mime_type, $allowed_mime_types['gmagick'], true)) {
                $is_mime_supported = true;
            } else {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | MIME-тип не поддерживается через gmagick | Получено: '$real_mime_type'"
                );
            }
        }
        // 3. Проверка через GD (если ни imagick, ни gmagick не поддерживают)
        if (!$is_mime_supported) {
            if (in_array($real_mime_type, $allowed_mime_types['GD'], true)) {
                $is_mime_supported = true;
            } else {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | MIME-тип не поддерживается через GD | Получено: '$real_mime_type'"
                );
            }
        }
        return $is_mime_supported;
    }
}
