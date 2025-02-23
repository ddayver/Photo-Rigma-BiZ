<?php

/**
 * @file        include/template.php
 * @brief       Класс для работы с HTML-шаблонами, включая рендеринг и подстановку данных.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-02-21
 * @namespace   PhotoRigma\\Classes
 *
 * @details     Основные функции — обработка шаблонов с реализацией алгоритмов IF/ELSE, ARRAY, SWITCH/CASE.
 *              Остальное будет добавлено после документирования класса.
 *
 * @see         PhotoRigma::Classes::Template Класс для работы с шаблонами.
 * @see         PhotoRigma::Classes::Template_Interface Интерфейс для работы с шаблонами.
 * @see         PhotoRigma::Classes::Work::clean_field() Внешняя функция, используемая для очистки данных.
 * @see         index.php Файл, который подключает template.php.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в организации работы приложения.
 *
 * @todo        Закончить реализацию интерфейса и документирование класса.
 * @todo        Полный рефакторинг кода с поддержкой PHP 8.4.3, централизованной системой логирования и обработки ошибок.
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
 * @class Template
 * @brief Класс для работы с шаблонами.
 */
class Template
{
    // Свойства:
    private string $ins_header = ''; ///< Данные, вставляемые в заголовок
    private string $ins_body = ''; ///< Данные, вставляемые в тег body
    private string $content = ''; ///< Содержимое для вывода
    private bool $mod_rewrite = false; ///< Включение читаемых URL
    private string $template_file = 'main.html'; ///< Файл шаблона
    private array $block_object = []; ///< Блок массивов объектов для обработки
    private array $block_string = []; ///< Блок строковых данных для замены
    private array $block_if = []; ///< Блок условий для обработки
    private array $block_case = []; ///< Блок массивов выбора блока для обработки
    private string $themes_path; ///< Путь к корню темы
    private string $themes_url; ///< Ссылка на корень темы
    private string $site_url; ///< Ссылка корня сайта
    private string $site_dir; ///< Путь к корню сайта
    private string $theme; ///< Тема пользователя
    private array $lang = []; ///< Массив языковых переменных
    private ?Work $work = null; ///< Свойство для объекта класса Work

    /**
     * @brief Конструктор класса, инициализирующий основные параметры сайта и темы оформления.
     *
     * @details Метод выполняет проверку входных данных, инициализирует свойства класса и вычисляет пути к директориям тем.
     *          Выполняется автоматически при создании объекта класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::$site_url Свойство, хранящее URL сайта.
     * @see PhotoRigma::Classes::Template::$site_dir Свойство, хранящее директорию сайта.
     * @see PhotoRigma::Classes::Template::$theme Свойство, хранящее имя темы.
     * @see PhotoRigma::Classes::Template::$themes_path Свойство, хранящее путь к директории тем.
     * @see PhotoRigma::Classes::Template::$themes_url Свойство, хранящее URL директории тем.
     * @see PhotoRigma::Classes::Template::$mod_rewrite Свойство, указывающее на включение читаемых URL.
     * @see PhotoRigma::Classes::Template::find_template_file() Метод, проверяющий правильность переданного имени файла шаблона.
     *
     * @param string $site_url URL сайта. Должен быть валидным URL.
     * @param string $site_dir Директория сайта. Должна существовать и быть доступной для чтения.
     * @param string $theme Имя темы оформления. Должно содержать только латинские буквы, цифры, дефисы и подчеркивания.
     * @param bool $mod_rewrite Включение читаемых URL. По умолчанию `false`.
     *
     * @throws InvalidArgumentException Если `$site_url` не является валидным URL.
     * @throws InvalidArgumentException Если `$site_dir` не существует или не является директорией.
     * @throws InvalidArgumentException Если `$theme` пустое или содержит недопустимые символы.
     * @throws RuntimeException Если директория тем не найдена.
     * @throws RuntimeException Если нет прав доступа к директории тем.
     *
     * Пример создания объекта:
     * @code
     * $object = new \PhotoRigma\Classes\Template(
     *     'https://example.com',
     *     '/var/www/example',
     *     'default',
     *     true
     * );
     * @endcode
     */
    public function __construct(string $site_url, string $site_dir, string $theme, bool $mod_rewrite = false)
    {
        // Проверяем, что $site_url является валидным URL
        if (!filter_var($site_url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный URL сайта | Значение: {$site_url}"
            );
        }

        // Проверяем, что $site_dir существует и является директорией
        if (!is_dir($site_dir)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректная директория сайта | Значение: {$site_dir}"
            );
        }

        // Проверяем корректность $theme
        if (empty($theme) || !preg_match('/^[a-zA-Z0-9_-]+$/', $theme)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное имя темы | Значение: {$theme}"
            );
        }

        // Инициализация свойств
        $this->site_url = $site_url;
        $this->site_dir = $site_dir;
        $this->theme = $theme;
        $this->mod_rewrite = ($mod_rewrite === true);

        // Вычисляем пути к темам
        $this->themes_path = $this->site_dir . 'themes/' . $this->theme . '/';
        $this->themes_url = $this->site_url . 'themes/' . $this->theme . '/';

        // Проверяем, что директория тем существует
        if (!is_dir($this->themes_path)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Директория тем не найдена | Путь: {$this->themes_path}"
            );
        }

        // Проверяем права доступа к директории тем
        if (!is_readable($this->themes_path)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Нет прав доступа к директории тем | Путь: {$this->themes_path}"
            );
        }

        // Проверяем существует ли шаблон по-умолчанию.
        $this->find_template_file();
    }

    /**
     * @brief Магический метод для получения значения свойства `$content`.
     *
     * @details Этот метод вызывается автоматически при попытке получить значение недоступного свойства.
     *          Доступ разрешён только к свойству `$content`. Если запрашивается другое свойство,
     *          выбрасывается исключение InvalidArgumentException.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::$content Свойство, содержащее содержимое для вывода.
     *
     * @param string $name Имя свойства:
     *                     - Допустимое значение: 'content'.
     *                     - Если указано другое имя, выбрасывается исключение.
     *
     * @return string Значение свойства `$content`.
     *
     * @throws InvalidArgumentException Если запрашиваемое свойство не существует или недоступно.
     *
     * @note Этот метод предназначен только для доступа к свойству `$content`.
     *       Любые другие запросы будут игнорироваться с выбросом исключения.
     *
     * @warning Попытка доступа к несуществующему свойству вызовет исключение.
     *          Убедитесь, что вы запрашиваете только допустимые свойства.
     *
     * Пример использования метода:
     * @code
     * $template = new \PhotoRigma\Classes\Template();
     * echo $template->content; // Выведет содержимое свойства $content
     * @endcode
     */
    public function __get(string $name): string
    {
        if ($name === 'content') {
            return $this->content;
        }
        throw new \InvalidArgumentException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не существует | Получено: '{$name}'"
        );
    }

    /**
     * @brief Устанавливает значение приватного свойства `$template_file`.
     *
     * @details Метод позволяет изменить значение приватного свойства `$template_file`.
     *          Если переданное имя свойства не соответствует `$template_file`, выбрасывается исключение.
     *          После установки значения выполняется проверка доступности файла по пути `$themes_path . $template_file`.
     *          Если файл не найден или недоступен для чтения, выбрасывается исключение.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::$template_file Свойство, содержащее имя файла шаблона.
     * @see PhotoRigma::Classes::Template::$themes_path Свойство, содержащее путь к директории тем.
     * @see PhotoRigma::Classes::Template::find_template_file() Метод, проверяющий правильность переданного имени файла шаблона.
     *
     * @param string $name Имя свойства:
     *                     - Допустимое значение: 'template_file'.
     *                     - Если указано другое имя, выбрасывается исключение.
     * @param string $value Новое значение свойства:
     *                      - Должно быть строкой, представляющей имя файла шаблона.
     *
     * @throws InvalidArgumentException Если переданное имя свойства не соответствует `$template_file`.
     * @throws RuntimeException Если файл по указанному пути не существует или недоступен для чтения.
     *
     * @note Этот метод предназначен только для изменения свойства `$template_file`.
     *       Любые другие запросы будут игнорироваться с выбросом исключения.
     *
     * @warning Попытка доступа к несуществующему свойству вызовет исключение.
     *          Убедитесь, что вы запрашиваете только допустимые свойства.
     *
     * Пример использования метода:
     * @code
     * $template = new \PhotoRigma\Classes\Template();
     * $template->template_file = 'main.html'; // Установит файл шаблона, если он доступен
     * @endcode
     */
    public function __set(string $name, string $value): void
    {
        if ($name === 'template_file') {
            $this->template_file = $value;
            $this->find_template_file();
            $this->template_file = $this->themes_path . $value;
        } else {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не может быть установлено | Получено: '{$name}'"
            );
        }
    }

    /**
     * @brief Установка объекта Work через сеттер.
     *
     * @details Этот метод позволяет установить объект Work ($work).
     *          Метод выполняет следующие действия:
     *          1. Проверяет, что переданный объект является экземпляром класса Work.
     *          2. Присваивает объект свойству $work текущего класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work Класс с объектом Work.
     *
     * @param Work $work Объект Work:
     *                   - Должен быть экземпляром класса Work.
     *
     * @throws InvalidArgumentException Если передан некорректный объект (не экземпляр класса Work).
     *
     * @note Метод проверяет тип переданного объекта.
     *       Объект Work используется для дальнейшего взаимодействия в текущем классе.
     *
     * @warning Некорректный объект (не экземпляр класса Work) вызывает исключение.
     *
     * Пример использования метода:
     * @code
     * $template = new \PhotoRigma\Classes\Template();
     * $work = new \PhotoRigma\Classes\Work();
     * $template->set_work($work);
     * @endcode
     */
    public function set_work(Work $work): void
    {
        if (!$work instanceof Work) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Некорректный тип аргумента | Ожидается объект класса Work"
            );
        }
        $this->work = $work;
    }

    /**
     * @brief Установка языковых данных через сеттер.
     *
     * @details Метод позволяет установить массив языковых данных.
     *          Если передан некорректный тип данных или `null`, выбрасывается исключение.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::$lang Свойство, которое изменяет метод.
     *
     * @param array $lang Языковые данные:
     *                    - Должен быть массивом.
     *                    - Каждый ключ должен быть строкой, а значение — допустимым для языковых данных.
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws InvalidArgumentException Если передан некорректный тип данных или `null`.
     *
     * @note Убедитесь, что передаваемые языковые данные корректны и соответствуют ожидаемому формату.
     * @warning Не передавайте пустые или некорректные данные, так как это может привести к ошибкам.
     *
     * Пример использования метода:
     * @code
     * $template = new \PhotoRigma\Classes\Template();
     * $template->set_lang(['key' => 'value']);
     * @endcode
     */
    public function set_lang(array $lang): void
    {
        if (!is_array($lang)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный тип данных для языковых строк | Ожидался массив, получено: " . gettype($lang)
            );
        }
        $this->lang = $lang;
    }

    /**
     * @brief Метод создает и обрабатывает шаблон, включая чтение файла, парсинг и замену плейсхолдеров.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Читает содержимое файла шаблона.
     *          2. Проверяет, что файл не пустой и содержит данные.
     *          3. Выполняет парсинг шаблона с помощью метода `pars_template()`.
     *          4. Заменяет плейсхолдеры `{SITE_URL}` и `{THEME_URL}` на реальные значения, используя функцию `Work::clean_field`.
     *          Метод является публичным и предназначен для прямого использования извне.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::$template_file Свойство, содержащее путь к файлу шаблона.
     * @see PhotoRigma::Classes::Template::$content Свойство, содержащее содержимое шаблона.
     * @see PhotoRigma::Classes::Template::pars_template() Метод, используемый для парсинга шаблона.
     * @see PhotoRigma::Classes::Work::clean_field() Внешняя функция, используемая для очистки данных.
     *
     * @throws RuntimeException Если произошла ошибка при чтении файла шаблона.
     * @throws RuntimeException Если файл шаблона пуст или содержит только пробелы.
     *
     * Пример вызова метода:
     * @code
     * $object = new \PhotoRigma\Classes\Template('https://example.com', '/var/www/example', 'default');
     * $object->create_template();
     * @endcode
     */
    public function create_template(): void
    {
        $this->content = file_get_contents($this->template_file);

        // Проверяем, что файл не пустой и содержит данные || trim($this->content) === ''
        if (!$this->content) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл шаблона пуст или содержит только пробелы | Путь: {$this->template_file}"
            );
        }

        // Заменяем плейсхолдеры на реальные значения с использованием Work::clean_field
        $this->content = str_replace('{SITE_URL}', Work::clean_field($this->site_url), $this->content);
        $this->content = str_replace('{THEME_URL}', Work::clean_field($this->themes_url), $this->content);

        // Парсим шаблон
        $this->pars_template();
    }

    /**
     * @brief Метод добавляет строковую переменную для замены в шаблоне, с возможностью рекурсивного размещения.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Проверяет корректность имени переменной (только латинские буквы, цифры и подчеркивания).
     *          2. Проверяет, что значение является строкой.
     *          3. Если указан путь (`$path_array`), проверяет его формат и разбирает с помощью метода `test_is_object()`.
     *          4. Если путь не указан, добавляет переменную в массив `block_string`. Если путь указан, рекурсивно добавляет переменную в объекты.
     *          Метод является публичным и предназначен для прямого использования извне.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::$block_string Свойство, содержащее массив строковых переменных.
     * @see PhotoRigma::Classes::Template::$block_object Свойство, содержащее объекты для рекурсивного размещения переменных.
     * @see PhotoRigma::Classes::Template::test_is_object() Метод, используемый для разбора пути.
     *
     * @param string $name Название переменной. Должно содержать только латинские буквы, цифры и подчеркивания.
     * @param string $value Значение переменной. Должно быть строкой.
     * @param string|false $path_array Путь для рекурсивного размещения переменной. Должен быть строкой (в виде: Массив1[0]->Массив1.0[0]) или `false` (по умолчанию).
     *
     * @throws InvalidArgumentException Если имя переменной некорректно (пустое или содержит недопустимые символы).
     * @throws InvalidArgumentException Если значение переменной не является строкой.
     * @throws InvalidArgumentException Если путь (`$path_array`) имеет некорректный формат (не строка и не `false`).
     * @throws RuntimeException Если результат метода `test_is_object()` некорректен.
     *
     * @warning Метод особенно чувствителен к параметру `$path_array`. Убедитесь, что он передается в правильном формате (строка или `false`).
     *
     * Пример вызова метода:
     * @code
     * $object = new \PhotoRigma\Classes\Template('https://example.com', '/var/www/example', 'default');
     * $object->add_string('TITLE', 'Welcome to the Site');
     * $object->add_string('HEADER_TITLE', 'Main Page', 'header.block');
     * @endcode
     */
    public function add_string(string $name, string $value, string|false $path_array = false): void
    {
        // Проверка корректности имени переменной
        if (empty($name) || !preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное имя переменной | Значение: {$name}"
            );
        }

        // Проверка типа значения
        if (!is_string($value)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный тип значения | Ожидается строка"
            );
        }

        // Проверка формата пути
        if ($path_array !== false && !is_string($path_array)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный формат пути | Ожидается строка или FALSE"
            );
        }

        // Если путь не указан, добавляем переменную в массив block_string
        if ($path_array == false) {
            $this->block_string[strtoupper($name)] = $value;
        } else {
            // Разбираем путь и проверяем результат
            $parsed_path = $this->test_is_object($path_array);
            if (!isset($parsed_path['current'], $parsed_path['index'])) {
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный результат test_is_object | Передавался путь: {$path_array}"
                );
            }

            // Рекурсивно добавляем переменную
            $this->block_object[$parsed_path['current']][$parsed_path['index']]->add_string(
                $name,
                $value,
                $parsed_path['next_path']
            );
        }
    }

    /**
     * @brief Метод добавляет массив строковых данных для замены в шаблоне, с возможностью рекурсивного размещения.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Проверяет, что массив данных не пуст.
     *          2. Проверяет формат пути (`$path_array`), если он указан.
     *          3. Для каждого элемента массива проверяет корректность ключа (только латинские буквы, цифры и подчеркивания) и значения (строка).
     *          4. Добавляет каждую пару ключ-значение в шаблон с помощью метода `add_string()`.
     *          Метод является публичным и предназначен для прямого использования извне.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::add_string() Метод, используемый для добавления строковых переменных.
     *
     * @param array $array_data Массив данных. Ключи должны содержать только латинские буквы, цифры и подчеркивания. Значения должны быть строками.
     * @param string|false $path_array Путь для рекурсивного размещения переменных. Должен быть строкой (в виде: Массив1[0]->Массив1.0[0]) или `false` (по умолчанию).
     *
     * @throws InvalidArgumentException Если массив данных пуст.
     * @throws InvalidArgumentException Если путь (`$path_array`) имеет некорректный формат (не строка и не `false`).
     * @throws InvalidArgumentException Если ключ массива некорректен (пустой или содержит недопустимые символы).
     * @throws InvalidArgumentException Если значение массива не является строкой.
     *
     * @warning Метод особенно чувствителен к параметру `$path_array`. Убедитесь, что он передается в правильном формате (строка или `false`).
     *
     * Пример вызова метода:
     * @code
     * $object = new \PhotoRigma\Classes\Template('https://example.com', '/var/www/example', 'default');
     * $data = [
     *     'TITLE' => 'Welcome to the Site',
     *     'HEADER_TITLE' => 'Main Page'
     * ];
     * $object->add_string_ar($data);
     * $object->add_string_ar($data, 'Массив1[0]->Массив1.0[0]');
     * @endcode
     */
    public function add_string_ar(array $array_data, string|false $path_array = false): void
    {
        // Проверка на пустой массив
        if (empty($array_data)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Массив данных пуст | Ожидался массив в аргументе \$array_data"
            );
        }

        // Проверка формата пути
        if ($path_array !== false && !is_string($path_array)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный формат пути | Ожидается строка или FALSE"
            );
        }

        // Обработка массива данных
        foreach ($array_data as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный ключ массива | Значение: {$key}"
                );
            }
            if (!is_string($value)) {
                throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное значение массива | Значение: {$key} = {$value}"
                );
            }
            $this->add_string($key, $value, $path_array);
        }
    }

    /**
     * @brief Метод добавляет данные об условиях вывода фрагментов шаблона, с возможностью рекурсивного размещения.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Проверяет корректность имени условия (только латинские буквы, цифры и подчеркивания).
     *          2. Проверяет формат пути (`$path_array`), если он указан.
     *          3. Если путь не указан, добавляет условие в массив `block_if`. Если путь указан, рекурсивно добавляет условие в объекты.
     *          Метод является публичным и предназначен для прямого использования извне.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::$block_if Свойство, содержащее массив условий.
     * @see PhotoRigma::Classes::Template::$block_object Свойство, содержащее объекты для рекурсивного размещения условий.
     * @see PhotoRigma::Classes::Template::test_is_object() Метод, используемый для разбора пути.
     *
     * @param string $name Название условия. Должно содержать только латинские буквы, цифры и подчеркивания.
     * @param bool $value Значение условия. Должно быть булевым значением (`true` или `false`).
     * @param string|false $path_array Путь для рекурсивного размещения условия. Должен быть строкой (в виде: Массив1[0]->Массив1.0[0]) или `false` (по умолчанию).
     *
     * @throws InvalidArgumentException Если имя условия некорректно (пустое или содержит недопустимые символы).
     * @throws InvalidArgumentException Если путь (`$path_array`) имеет некорректный формат (не строка и не `false`).
     * @throws RuntimeException Если результат метода `test_is_object()` некорректен.
     * @throws InvalidArgumentException Если формат `next_path` некорректен (не строка и не `false`).
     *
     * @warning Метод особенно чувствителен к параметру `$path_array`. Убедитесь, что он передается в правильном формате (строка или `false`).
     *
     * Пример вызова метода:
     * @code
     * $object = new \PhotoRigma\Classes\Template('https://example.com', '/var/www/example', 'default');
     * $object->add_if('SHOW_HEADER', true);
     * $object->add_if('SHOW_FOOTER', false, 'Массив1[0]->Массив1.0[0]');
     * @endcode
     */
    public function add_if(string $name, bool $value, string|false $path_array = false): void
    {
        // Проверка корректности имени условия
        if (empty($name) || !preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное имя условия | Значение: {$name}"
            );
        }

        // Проверка формата пути
        if ($path_array !== false && !is_string($path_array)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный формат пути | Ожидается строка или FALSE"
            );
        }

        // Если путь не указан, добавляем условие в массив block_if
        if ($path_array == false) {
            $this->block_if['IF_' . strtoupper($name)] = $value;
        } else {
            // Разбираем путь и проверяем результат
            $parsed_path = $this->test_is_object($path_array);
            if (!isset($parsed_path['current'], $parsed_path['index'])) {
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный результат test_is_object | Передавался путь: {$path_array}"
                );
            }

            // Проверка формата next_path
            if ($parsed_path['next_path'] !== false && !is_string($parsed_path['next_path'])) {
                throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный формат next_path | Ожидается строка или FALSE"
                );
            }

            // Рекурсивно добавляем условие
            $this->block_object[$parsed_path['current']][$parsed_path['index']]->add_if(
                $name,
                $value,
                $parsed_path['next_path']
            );
        }
    }

    /**
     * @brief Метод добавляет массив данных об условиях вывода фрагментов шаблона, с возможностью рекурсивного размещения.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Проверяет, что массив данных не пуст.
     *          2. Проверяет формат пути (`$path_array`), если он указан.
     *          3. Для каждого элемента массива проверяет корректность ключа (только латинские буквы, цифры и подчеркивания) и значения (булево значение).
     *          4. Добавляет каждую пару ключ-значение в шаблон с помощью метода `add_if()`.
     *          Метод является публичным и предназначен для прямого использования извне.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::add_if() Метод, используемый для добавления отдельных условий.
     *
     * @param array $array_data Массив условий. Ключи должны содержать только латинские буквы, цифры и подчеркивания. Значения должны быть булевыми (`true` или `false`).
     * @param string|false $path_array Путь для рекурсивного размещения условий. Должен быть строкой (в виде: Массив1[0]->Массив1.0[0]) или `false` (по умолчанию).
     *
     * @throws InvalidArgumentException Если массив данных пуст.
     * @throws InvalidArgumentException Если путь (`$path_array`) имеет некорректный формат (не строка и не `false`).
     * @throws InvalidArgumentException Если ключ массива некорректен (пустой или содержит недопустимые символы).
     * @throws InvalidArgumentException Если значение массива не является булевым значением (`true` или `false`).
     *
     * @warning Метод особенно чувствителен к параметру `$path_array`. Убедитесь, что он передается в правильном формате (строка или `false`).
     *
     * Пример вызова метода:
     * @code
     * $object = new \PhotoRigma\Classes\Template('https://example.com', '/var/www/example', 'default');
     * $data = [
     *     'SHOW_HEADER' => true,
     *     'SHOW_FOOTER' => false
     * ];
     * $object->add_if_ar($data);
     * $object->add_if_ar($data, 'Массив1[0]->Массив1.0[0]');
     * @endcode
     */
    public function add_if_ar(array $array_data, string|false $path_array = false): void
    {
        // Проверка на пустой массив
        if (empty($array_data)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Массив данных пуст | Ожидался массив в аргументе \$array_data"
            );
        }

        // Проверка формата пути
        if ($path_array !== false && !is_string($path_array)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный формат пути | Ожидается строка или FALSE"
            );
        }

        // Обработка массива данных
        foreach ($array_data as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный ключ массива | Значение: {$key}"
                );
            }
            if (!is_bool($value)) {
                throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное значение массива | Значение: {$value}"
                );
            }
            $this->add_if($key, $value, $path_array);
        }
    }

    /**
     * @brief Метод добавляет данные о выборе блока для вывода фрагментов шаблона, с возможностью рекурсивного размещения.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Проверяет корректность имени условия (только латинские буквы, цифры и подчеркивания).
     *          2. Проверяет корректность значения условия (только латинские буквы, цифры и подчеркивания).
     *          3. Проверяет формат пути (`$path_array`), если он указан.
     *          4. Если путь не указан, добавляет условие в массив `block_case`. Если путь указан, рекурсивно добавляет условие в объекты.
     *          Метод является публичным и предназначен для прямого использования извне.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::$block_case Свойство, содержащее массив данных о выборе блока.
     * @see PhotoRigma::Classes::Template::$block_object Свойство, содержащее объекты для рекурсивного размещения условий.
     * @see PhotoRigma::Classes::Template::test_is_object() Метод, используемый для разбора пути.
     *
     * @param string $name Название условия. Должно содержать только латинские буквы, цифры и подчеркивания.
     * @param string $value Значение условия. Должно содержать только латинские буквы, цифры и подчеркивания.
     * @param string|false $path_array Путь для рекурсивного размещения условия. Должен быть строкой (в виде: Массив1[0]->Массив1.0[0]) или `false` (по умолчанию).
     *
     * @throws InvalidArgumentException Если имя условия некорректно (пустое или содержит недопустимые символы).
     * @throws InvalidArgumentException Если значение условия некорректно (пустое или содержит недопустимые символы).
     * @throws InvalidArgumentException Если путь (`$path_array`) имеет некорректный формат (не строка и не `false`).
     * @throws RuntimeException Если результат метода `test_is_object()` некорректен.
     * @throws InvalidArgumentException Если формат `next_path` некорректен (не строка и не `false`).
     *
     * @warning Метод особенно чувствителен к параметру `$path_array`. Убедитесь, что он передается в правильном формате (строка или `false`).
     *
     * Пример вызова метода:
     * @code
     * $object = new \PhotoRigma\Classes\Template('https://example.com', '/var/www/example', 'default');
     * $object->add_case('BLOCK_TYPE', 'HEADER');
     * $object->add_case('BLOCK_TYPE', 'FOOTER', 'Массив1[0]->Массив1.0[0]');
     * @endcode
     */
    public function add_case(string $name, string $value, string|false $path_array = false): void
    {
        // Проверка корректности имени условия
        if (empty($name) || !preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное имя условия | Значение: {$name}"
            );
        }

        // Проверка корректности значения условия
        if (empty($value) || !preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное значение условия | Значение: {$value}"
            );
        }

        // Проверка формата пути
        if ($path_array !== false && !is_string($path_array)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный формат пути | Ожидается строка или FALSE"
            );
        }

        // Если путь не указан, добавляем условие в массив block_case
        if ($path_array == false) {
            $this->block_case['SELECT_' . strtoupper($name)] = strtoupper($value);
        } else {
            // Разбираем путь и проверяем результат
            $parsed_path = $this->test_is_object($path_array);
            if (!isset($parsed_path['current'], $parsed_path['index'])) {
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный результат test_is_object | Передавался путь: {$path_array}"
                );
            }

            // Проверка формата next_path
            if ($parsed_path['next_path'] !== false && !is_string($parsed_path['next_path'])) {
                throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный формат next_path | Ожидается строка или FALSE"
                );
            }

            // Рекурсивно добавляем условие
            $this->block_object[$parsed_path['current']][$parsed_path['index']]->add_case(
                $name,
                $value,
                $parsed_path['next_path']
            );
        }
    }

    /**
     * @brief Метод добавляет массив данных о выборе блока для вывода фрагментов шаблона, с возможностью рекурсивного размещения.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Проверяет, что массив данных не пуст.
     *          2. Проверяет формат пути (`$path_array`), если он указан.
     *          3. Для каждого элемента массива проверяет корректность ключа и значения (только латинские буквы, цифры и подчеркивания).
     *          4. Добавляет каждую пару ключ-значение в шаблон с помощью метода `add_case()`.
     *          Метод является публичным и предназначен для прямого использования извне.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::add_case() Метод, используемый для добавления отдельных условий выбора блока.
     *
     * @param array $array_data Массив данных о выборе блока для вывода фрагментов шаблона. Ключи и значения должны содержать только латинские буквы, цифры и подчеркивания.
     * @param string|false $path_array Путь для рекурсивного размещения условия. Должен быть строкой (в виде: Массив1[0]->Массив1.0[0]) или `false` (по умолчанию).
     *
     * @throws InvalidArgumentException Если массив данных пуст.
     * @throws InvalidArgumentException Если путь (`$path_array`) имеет некорректный формат (не строка и не `false`).
     * @throws InvalidArgumentException Если ключ массива некорректен (пустой или содержит недопустимые символы).
     * @throws InvalidArgumentException Если значение массива некорректно (пустое или содержит недопустимые символы).
     *
     * @warning Метод особенно чувствителен к параметру `$path_array`. Убедитесь, что он передается в правильном формате (строка или `false`).
     *
     * Пример вызова метода:
     * @code
     * $object = new \PhotoRigma\Classes\Template('https://example.com', '/var/www/example', 'default');
     * $data = [
     *     'BLOCK_TYPE' => 'HEADER',
     *     'BLOCK_STYLE' => 'COMPACT'
     * ];
     * $object->add_case_ar($data);
     * $object->add_case_ar($data, 'Массив1[0]->Массив1.0[0]');
     * @endcode
     */
    public function add_case_ar(array $array_data, $path_array = false): void
    {
        // Проверка на пустой массив
        if (empty($array_data)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Массив данных пуст | Ожидался массив в аргументе \$array_data"
            );
        }

        // Проверка формата пути
        if ($path_array !== false && !is_string($path_array)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный формат пути | Ожидается строка или FALSE"
            );
        }

        // Обработка массива данных
        foreach ($array_data as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный ключ массива | Значение: {$key}"
                );
            }
            if (!is_string($value) || !preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
                throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное значение массива | Значение: {$value}"
                );
            }
            $this->add_case($key, $value, $path_array);
        }
    }

    /**
     * @brief Метод формирует заголовок HTML-страницы с меню, метаданными и блоками контента.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Проверяет корректность параметра `$action`.
     *          2. Генерирует данные для меню и фотографий с помощью методов `create_menu()` и `create_photo()`.
     *          3. Создает экземпляр шаблона заголовка (`header.html`) и заполняет его строковыми данными, условиями и выбором блоков.
     *          4. Обрабатывает короткое и длинное меню, а также блоки с последними фотографиями.
     *          5. Устанавливает файл шаблона и создает контент, который добавляется в начало текущего содержимого страницы.
     *          Метод является публичным и предназначен для прямого использования извне.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work::create_menu() Метод, используемый для генерации данных меню.
     * @see PhotoRigma::Classes::Work::create_photo() Метод, используемый для генерации данных фотографий.
     * @see PhotoRigma::Classes::Template Класс, используемый для обработки шаблонов.
     * @see Work::clean_field() Функция, используемая для очистки данных.
     *
     * @param string $title Дополнительное название страницы для тега `<title>`. Может быть пустым.
     * @param string $action Текущее активное действие (пункт меню). Должно содержать только латинские буквы, цифры и подчеркивания. Не может быть пустым.
     *
     * @throws InvalidArgumentException Если параметр `$action` некорректен (пустой или содержит недопустимые символы).
     *
     * Пример использования метода:
     * @code
     * $object = new \PhotoRigma\Classes\Template('https://example.com', '/var/www/example', 'default');
     * $object->page_header('Главная страница', 'home');
     * @endcode
     */
    public function page_header(string $title, string $action): void
    {
        // Проверка параметра $action
        if (empty($action) || !preg_match('/^[a-zA-Z0-9_]+$/', $action)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный параметр \$action | Значение: {$action}"
            );
        }

        // Генерация данных для меню и фотографий
        $short_menu = $this->work->create_menu($action, 0);
        $long_menu = $this->work->create_menu($action, 1);
        $top_photo = $this->work->create_photo('top', 0);
        $last_photo = $this->work->create_photo('last', 0);

        // Создание экземпляра шаблона заголовка
        $header_template = new Template($this->site_url, $this->site_dir, $this->theme);
        if ($this->ins_body !== '') {
            $this->ins_body = ' ' . $this->ins_body;
        }

        // Добавление строковых данных в шаблон
        $header_template->add_string_ar([
            'TITLE'             => empty($title) ? $this->work->config['title_name'] : Work::clean_field($this->work->config['title_name']) . ' - ' . Work::clean_field($title),
            'INSERT_HEADER'     => $this->ins_header,
            'INSERT_BODY'       => $this->ins_body,
            'META_DESRIPTION'   => Work::clean_field($this->work->config['meta_description']),
            'META_KEYWORDS'     => Work::clean_field($this->work->config['meta_keywords']),
            'GALLERY_WIDHT'     => $this->work->config['gal_width'],
            'SITE_NAME'         => Work::clean_field($this->work->config['title_name']),
            'SITE_DESCRIPTION'  => Work::clean_field($this->work->config['title_description']),
            'U_SEARCH'          => $this->work->config['site_url'] . '?action=search',
            'L_SEARCH'          => $this->lang['main']['search'],
            'LEFT_PANEL_WIDHT'  => $this->work->config['left_panel'],
            'RIGHT_PANEL_WIDHT' => $this->work->config['right_panel']
        ]);

        // Обработка короткого меню
        $header_template->add_if('SHORT_MENU', false);
        if ($short_menu && is_array($short_menu)) {
            $header_template->add_if('SHORT_MENU', true);
            foreach ($short_menu as $id => $value) {
                $header_template->add_string_ar([
                    'U_SHORT_MENU' => $value['url'],
                    'L_SHORT_MENU' => $value['name']
                ], 'SHORT_MENU[' . $id . ']');
                $header_template->add_if('SHORT_MENU_URL', !empty($value['url']), 'SHORT_MENU[' . $id . ']');
            }
        }

        // Обработка длинного меню
        $header_template->add_case('LEFT_BLOCK', 'MENU', 'LEFT_PANEL[0]');
        $header_template->add_if('LONG_MENU', false, 'LEFT_PANEL[0]');
        if ($long_menu && is_array($long_menu)) {
            $header_template->add_if('LONG_MENU', true, 'LEFT_PANEL[0]');
            $header_template->add_string('LONG_MENU_NAME_BLOCK', $this->lang['menu']['name_block'], 'LEFT_PANEL[0]');
            foreach ($long_menu as $id => $value) {
                $header_template->add_string_ar([
                    'U_LONG_MENU' => $value['url'],
                    'L_LONG_MENU' => $value['name']
                ], 'LEFT_PANEL[0]->LONG_MENU[' . $id . ']');
                $header_template->add_if('LONG_MENU_URL', !empty($value['url']), 'LEFT_PANEL[0]->LONG_MENU[' . $id . ']');
            }
        }

        // Обработка блока с последними фотографиями
        $header_template->add_case('LEFT_BLOCK', 'TOP_LAST_PHOTO', 'LEFT_PANEL[1]');
        $header_template->add_string_ar([
            'NAME_BLOCK'             => $top_photo['name_block'],
            'PHOTO_WIDTH'            => (string)$top_photo['width'],
            'PHOTO_HEIGHT'           => (string)$top_photo['height'],
            'MAX_FOTO_HEIGHT'        => (string)($this->work->config['temp_photo_h'] + 10),
            'D_NAME_PHOTO'           => $top_photo['name'],
            'D_DESCRIPTION_PHOTO'    => $top_photo['description'],
            'D_NAME_CATEGORY'        => $top_photo['category_name'],
            'D_DESCRIPTION_CATEGORY' => $top_photo['category_description'],
            'PHOTO_RATE'             => $top_photo['rate'],
            'L_USER_ADD'             => $this->lang['main']['user_add'],
            'U_PROFILE_USER_ADD'     => $top_photo['url_user'],
            'D_REAL_NAME_USER_ADD'   => $top_photo['real_name'],
            'U_PHOTO'                => $top_photo['url'],
            'U_THUMBNAIL_PHOTO'      => $top_photo['thumbnail_url'],
            'U_CATEGORY'             => $top_photo['category_url']
        ], 'LEFT_PANEL[1]');
        $header_template->add_if('USER_EXISTS', !empty($top_photo['url_user']), 'LEFT_PANEL[1]');

        // Обработка блока с последними фотографиями (второй блок)
        $header_template->add_case('LEFT_BLOCK', 'TOP_LAST_PHOTO', 'LEFT_PANEL[2]');
        $header_template->add_string_ar([
            'NAME_BLOCK'             => $last_photo['name_block'],
            'PHOTO_WIDTH'            => (string)$last_photo['width'],
            'PHOTO_HEIGHT'           => (string)$last_photo['height'],
            'MAX_FOTO_HEIGHT'        => (string)($this->work->config['temp_photo_h'] + 10),
            'D_NAME_PHOTO'           => $last_photo['name'],
            'D_DESCRIPTION_PHOTO'    => $last_photo['description'],
            'D_NAME_CATEGORY'        => $last_photo['category_name'],
            'D_DESCRIPTION_CATEGORY' => $last_photo['category_description'],
            'PHOTO_RATE'             => $last_photo['rate'],
            'L_USER_ADD'             => $this->lang['main']['user_add'],
            'U_PROFILE_USER_ADD'     => $last_photo['url_user'],
            'D_REAL_NAME_USER_ADD'   => $last_photo['real_name'],
            'U_PHOTO'                => $last_photo['url'],
            'U_THUMBNAIL_PHOTO'      => $last_photo['thumbnail_url'],
            'U_CATEGORY'             => $last_photo['category_url']
        ], 'LEFT_PANEL[2]');
        $header_template->add_if('USER_EXISTS', !empty($last_photo['url_user']), 'LEFT_PANEL[2]');

        // Установка файла шаблона и создание контента
        $header_template->template_file = $this->themes_path . 'header.html';
        $header_template->create_template();
        $this->content = $header_template->content . $this->content;
        unset($header_template);
    }

    /**
     * @brief Метод формирует подвал HTML-страницы с выводом копирайта, статистики, информации о пользователе и случайной фотографии.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Генерирует данные для подвала, включая информацию о пользователе, статистику, лучших пользователей и случайную фотографию.
     *          2. Создает экземпляр шаблона подвала (`footer.html`) и заполняет его строковыми данными, условиями и выбором блоков.
     *          3. Обрабатывает блоки для правой панели (информация о пользователе, статистика, лучшие пользователи, случайная фотография).
     *          4. Устанавливает файл шаблона и создает контент, который добавляется в конец текущего содержимого страницы.
     *          Метод является публичным и предназначен для прямого использования извне.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work::template_user() Метод, используемый для генерации данных о пользователе.
     * @see PhotoRigma::Classes::Work::template_stat() Метод, используемый для генерации статистики.
     * @see PhotoRigma::Classes::Work::template_best_user() Метод, используемый для генерации данных о лучших пользователях.
     * @see PhotoRigma::Classes::Work::create_photo() Метод, используемый для генерации данных случайной фотографии.
     * @see PhotoRigma::Classes::Template Класс, используемый для обработки шаблонов.
     * @see Work::clean_field() Функция, используемая для очистки данных.
     *
     * Пример использования метода:
     * @code
     * $object = new \PhotoRigma\Classes\Template('https://example.com', '/var/www/example', 'default');
     * $object->page_footer();
     * @endcode
     */
    public function page_footer(): void
    {
        // Генерация данных для подвала
        $user = $this->work->template_user();
        $stat = $this->work->template_stat();
        $best_user = $this->work->template_best_user($this->work->config['best_user']);
        $rand_photo = $this->work->create_photo('rand');

        // Создание экземпляра шаблона подвала
        $footer_template = new Template($this->site_url, $this->site_dir, $this->theme);

        // Добавление строковых данных в шаблон
        $footer_template->add_string_ar([
            'COPYRIGHT_YEAR' => Work::clean_field($this->work->config['copyright_year']),
            'COPYRIGHT_URL'  => Work::clean_field($this->work->config['copyright_url']),
            'COPYRIGHT_TEXT' => Work::clean_field($this->work->config['copyright_text'])
        ]);

        // Обработка блока информации о пользователе
        $footer_template->add_case('RIGHT_BLOCK', 'USER_INFO', 'RIGHT_PANEL[0]');
        $footer_template->add_string_ar($user, 'RIGHT_PANEL[0]');
        $footer_template->add_if('USER_NOT_LOGIN', ($_SESSION['login_id'] == 0 ? true : false), 'RIGHT_PANEL[0]');

        // Обработка блока статистики
        $footer_template->add_case('RIGHT_BLOCK', 'STATISTIC', 'RIGHT_PANEL[1]');
        $footer_template->add_string_ar($stat, 'RIGHT_PANEL[1]');

        // Обработка блока лучших пользователей
        $footer_template->add_case('RIGHT_BLOCK', 'BEST_USER', 'RIGHT_PANEL[2]');
        $footer_template->add_string_ar($best_user[0], 'RIGHT_PANEL[2]');
        unset($best_user[0]);
        foreach ($best_user as $key => $val) {
            $footer_template->add_string_ar([
                'U_BEST_USER_PROFILE' => $val['user_url'],
                'D_USER_NAME'         => $val['user_name'],
                'D_USER_PHOTO'        => $val['user_photo']
            ], 'RIGHT_PANEL[2]->BEST_USER[' . $key . ']');
            $footer_template->add_if('USER_EXIST', !empty($val['user_url']), 'RIGHT_PANEL[2]->BEST_USER[' . $key . ']');
        }

        // Обработка блока случайной фотографии
        $footer_template->add_case('RIGHT_BLOCK', 'RANDOM_PHOTO', 'RIGHT_PANEL[3]');
        $footer_template->add_string_ar([
            'NAME_BLOCK'             => $rand_photo['name_block'],
            'PHOTO_WIDTH'            => $rand_photo['width'],
            'PHOTO_HEIGHT'           => $rand_photo['height'],
            'MAX_FOTO_HEIGHT'        => $this->work->config['temp_photo_h'] + 10,
            'D_NAME_PHOTO'           => $rand_photo['name'],
            'D_DESCRIPTION_PHOTO'    => $rand_photo['description'],
            'D_NAME_CATEGORY'        => $rand_photo['category_name'],
            'D_DESCRIPTION_CATEGORY' => $rand_photo['category_description'],
            'PHOTO_RATE'             => $rand_photo['rate'],
            'L_USER_ADD'             => $this->lang['main']['user_add'],
            'U_PROFILE_USER_ADD'     => $rand_photo['url_user'],
            'D_REAL_NAME_USER_ADD'   => $rand_photo['real_name'],
            'U_PHOTO'                => $rand_photo['url'],
            'U_THUMBNAIL_PHOTO'      => $rand_photo['thumbnail_url'],
            'U_CATEGORY'             => $rand_photo['category_url']
        ], 'RIGHT_PANEL[3]');
        $footer_template->add_if('USER_EXISTS', !empty($rand_photo['url_user']), 'RIGHT_PANEL[3]');

        // Установка файла шаблона и создание контента
        $footer_template->template_file = $this->themes_path . 'footer.html';
        $footer_template->create_template();
        $this->content = $this->content . $footer_template->content;
        unset($footer_template);
    }

    /**
     * @brief Метод проверяет существование рекурсивного блока массивов-объектов, разбирает путь и возвращает структуру для дальнейшей обработки.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Проверяет корректность входного пути (`$path_array`).
     *          2. Разбирает путь на составные части (имя объекта, индекс, остаток пути).
     *          3. Проверяет формат имени объекта и индекса.
     *          4. Создает новый объект в массиве `block_object`, если он не существует.
     *          5. Возвращает массив с текущим именем объекта, индексом и остатком пути.
     *          Метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::$block_object Свойство, содержащее массив объектов.
     * @see PhotoRigma::Classes::Template Класс, используемый для создания новых объектов.
     *
     * @param string $path_array Путь, по которому рекурсивно необходимо создать объект-массив. Должен быть строкой в формате: `Массив1[0]->Массив1.0[0]`. Обязательный параметр.
     *
     * @return array Массив с ключами:
     *               - `'current'` — имя объекта (строка).
     *               - `'index'` — порядковый номер в массиве (целое число).
     *               - `'next_path'` — остаток пути (строка или `false`, если путь полностью разобран).
     *
     * @throws InvalidArgumentException Если путь некорректен, содержит ошибки или имеет недопустимый формат.
     *
     * Пример вызова метода внутри класса
     * @code
     * $result = $this->test_is_object('BLOCK_ARRAY[0]->BLOCK_ARRAY.0[1]');
     * if ($result['next_path'] === false) {
     *     echo "Путь полностью разобран.";
     * }
     * @endcode
     */
    private function test_is_object(string $path_array): array
    {
        // Проверка входных данных
        if (!is_string($path_array) || empty($path_array)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный путь | Значение: {$path_array}"
            );
        }
        // Разбор пути
        $path_parts = explode('->', $path_array);
        $first_part = strtoupper($path_parts[0]);
        $first_part_details = explode('[', $first_part);
        // Проверка корректности первого элемента
        if (!preg_match('/^[A-Z_]+$/', $first_part_details[0]) || !isset($first_part_details[1]) || empty($first_part_details[1])) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка в пути объекта | Путь: {$path_array}"
            );
        }
        // Проверка индекса
        $index = str_replace(']', '', $first_part_details[1]);
        if (!preg_match('/^[0-9]+$/', $index)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный индекс в пути | Путь: {$path_array}"
            );
        }
        // Создание объекта, если он не существует
        if (!isset($this->block_object[$first_part_details[0]][$index]) ||
            !is_object($this->block_object[$first_part_details[0]][$index])) {
            $new_template = new Template($this->site_url, $this->site_dir, $this->theme);
            $this->block_object[$first_part_details[0]][$index] = $new_template;
        }
        // Формирование результата
        $result = [
            'current' => $first_part_details[0],
            'index' => $index,
            'next_path' => count($path_parts) > 1 ? implode('->', array_slice($path_parts, 1)) : false,
        ];
        return $result;
    }

    /**
     * @brief Метод обрабатывает шаблон, наполняя его данными: рекурсивно обрабатывает объектные блоки, условия вывода, выбор блоков и заменяет строковые переменные.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Проверяет корректность содержимого шаблона (`$this->content`), которое должно быть непустой строкой.
     *          2. Рекурсивно обрабатывает блоки объектов (`block_object`) с помощью метода `template_object()`.
     *          3. Обрабатывает условия вывода (`block_if`) с помощью метода `template_if()`.
     *          4. Обрабатывает выбор блоков (`block_case`) с помощью метода `template_case()`.
     *          5. Заменяет строковые переменные (`block_string`) в содержимом шаблона.
     *          6. Модифицирует URL в содержимом с помощью метода `url_mod_rewrite()`.
     *          7. Нормализует переносы строк в содержимом.
     *          Метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::$block_object Свойство, содержащее массив объектных блоков.
     * @see PhotoRigma::Classes::Template::$block_if Свойство, содержащее массив условий.
     * @see PhotoRigma::Classes::Template::$block_case Свойство, содержащее массив выбора блоков.
     * @see PhotoRigma::Classes::Template::$block_string Свойство, содержащее массив строковых переменных.
     * @see PhotoRigma::Classes::Template::template_object() Метод, используемый для обработки объектных блоков.
     * @see PhotoRigma::Classes::Template::template_if() Метод, используемый для обработки условий.
     * @see PhotoRigma::Classes::Template::template_case() Метод, используемый для обработки выбора блоков.
     * @see PhotoRigma::Classes::Template::url_mod_rewrite() Метод, используемый для модификации URL.
     *
     * @throws RuntimeException Если содержимое шаблона некорректно (не является непустой строкой).
     *
     * Пример вызова метода внутри класса
     * @code
     * $this->pars_template();
     * @endcode
     */
    private function pars_template(): void
    {
        // Проверка, что $this->content является строкой и не пустая
        if (!is_string($this->content) || empty($this->content)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное содержимое шаблона | Значение должно быть непустой строкой"
            );
        }
        // Обработка блоков объектов
        foreach ($this->block_object as $key => $val) {
            $this->template_object($key, $val);
        }
        // Обработка условий
        foreach ($this->block_if as $key => $val) {
            $this->template_if($key, $val);
        }
        // Обработка выбора блоков
        foreach ($this->block_case as $key => $val) {
            $this->template_case($key, $val);
        }
        // Замена строковых переменных
        foreach ($this->block_string as $key => $val) {
            $this->content = str_replace('{' . $key . '}', $val, $this->content);
        }
        // Модификация URL
        $this->content = $this->url_mod_rewrite($this->content);
        // Нормализация переносов строк
        $this->content = preg_replace('/\R+/', PHP_EOL, $this->content);
    }

    /**
     * @brief Метод выполняет рекурсивную обработку блока массивов-объектов, заменяя их содержимое в шаблоне между тегами `<!-- ARRAY_НАЗВАНИЕ_BEGIN -->` и `<!-- ARRAY_НАЗВАНИЕ_END -->`.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Проверяет корректность содержимого шаблона (`$this->content`), которое должно быть непустой строкой.
     *          2. Формирует ключи для поиска блоков в шаблоне: `<!-- ARRAY_НАЗВАНИЕ_BEGIN -->` и `<!-- ARRAY_НАЗВАНИЕ_END -->`.
     *          3. Использует регулярное выражение для поиска всех блоков, заключенных между этими тегами.
     *          4. Для каждого элемента массива `$index` проверяет, что он является объектом и имеет метод `pars_template()`.
     *          5. Рекурсивно обрабатывает содержимое каждого объекта с помощью метода `pars_template()` и объединяет результаты.
     *          6. Заменяет найденные блоки в шаблоне на обработанное содержимое.
     *          7. Если остались незакрытые или несоответствующие теги, выбрасывает исключение.
     *          Метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::$content Свойство, содержащее содержимое шаблона.
     * @see PhotoRigma::Classes::Template::pars_template() Метод, используемый для рекурсивной обработки объектов.
     *
     * @param string $key Ключ-название фрагмента заменяемого блока. Должен быть строкой, соответствующей имени блока в шаблоне (например, `НАЗВАНИЕ` для тегов `<!-- ARRAY_НАЗВАНИЕ_BEGIN -->`).
     * @param array $index Индекс-блок элементов для рекурсивной замены. Должен быть массивом объектов, каждый из которых имеет метод `pars_template()`.
     *
     * @throws RuntimeException Если содержимое шаблона некорректно (не является непустой строкой).
     * @throws RuntimeException Если объекты в индексе имеют неверный формат (не являются объектами или не имеют метода `pars_template()`).
     * @throws RuntimeException Если остались незакрытые или несоответствующие теги после обработки.
     *
     * Пример вызова метода внутри класса
     * @code
     * $this->template_object('EXAMPLE_BLOCK', $this->block_object['EXAMPLE_BLOCK']);
     * @endcode
     */
    private function template_object(string $key, array $index): void
    {
        // Проверка, что $this->content является строкой и не пустая
        if (!is_string($this->content) || empty($this->content)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное содержимое шаблона | Значение должно быть непустой строкой"
            );
        }
        // Формирование ключей для поиска
        $begin_tag = '<!-- ARRAY_' . $key . '_BEGIN -->';
        $end_tag = '<!-- ARRAY_' . $key . '_END -->';
        // Регулярное выражение для поиска всех блоков
        $pattern = '/' . preg_quote($begin_tag, '/') . '(.*?)' . preg_quote($end_tag, '/') . '/s';
        // Поиск всех блоков
        $this->content = preg_replace_callback($pattern, function ($matches) use ($index) {
            $block_content = '';
            $temp_content = $matches[1];
            // Проверка, что $index содержит только объекты с методом pars_template
            foreach ($index as $id => $value) {
                if (!is_object($value) || !method_exists($value, 'pars_template')) {
                    throw new \RuntimeException(
                        __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный объект в индексе | Ключ: {$key}, ID: {$id}"
                    );
                }
                // Обработка содержимого для каждого объекта
                $value->content = $temp_content;
                $value->pars_template();
                $block_content .= $value->content;
            }
            return $block_content;
        }, $this->content);
        // Если остались незакрытые теги, выбрасываем исключение
        if (strpos($this->content, $begin_tag) !== false || strpos($this->content, $end_tag) !== false) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка обработки блока объектов | Незакрытые или несоответствующие теги для ключа: {$key}"
            );
        }
    }

    /**
     * @brief Метод обрабатывает блоки условий вывода фрагментов шаблона, заменяя их содержимое в зависимости от значения условия.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Проверяет корректность содержимого шаблона (`$this->content`), которое должно быть непустой строкой.
     *          2. Формирует ключи для поиска блоков в шаблоне: `<!-- IF_НАЗВАНИЕ_BEGIN -->`, `<!-- IF_НАЗВАНИЕ_ELSE -->` и `<!-- IF_НАЗВАНИЕ_END -->`.
     *          3. Использует регулярное выражение для поиска всех блоков, заключенных между этими тегами.
     *          4. В зависимости от значения `$val` выбирает содержимое до тега `_ELSE` (если `$val` равно `true`) или после него (если `$val` равно `false`).
     *          5. Заменяет найденные блоки в шаблоне на выбранное содержимое.
     *          6. Если остались незакрытые или несоответствующие теги, выбрасывает исключение.
     *          Метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::$content Свойство, содержащее содержимое шаблона.
     *
     * @param string $key Ключ-название условия. Должен быть строкой, соответствующей имени условия в шаблоне (например, `IF_НАЗВАНИЕ`).
     * @param bool $val Значение условия. Определяет, какая часть блока будет использована: содержимое до `_ELSE` (если `true`) или после него (если `false`).
     *
     * @throws RuntimeException Если содержимое шаблона некорректно (не является непустой строкой).
     * @throws RuntimeException Если остались незакрытые или несоответствующие теги после обработки.
     *
     * Пример вызова метода внутри класса
     * @code
     * $this->template_if('IF_EXAMPLE', true);
     * @endcode
     */
    private function template_if(string $key, bool $val): void
    {
        // Проверка, что $this->content является строкой и не пустая
        if (!is_string($this->content) || empty($this->content)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное содержимое шаблона | Значение должно быть непустой строкой"
            );
        }
        // Формирование ключей для поиска
        $begin_tag = '<!-- ' . $key . '_BEGIN -->';
        $else_tag = '<!-- ' . $key . '_ELSE -->';
        $end_tag = '<!-- ' . $key . '_END -->';
        // Регулярное выражение для поиска всех блоков
        $pattern = '/' . preg_quote($begin_tag, '/') . '(.*?)' .
                   '(' . preg_quote($else_tag, '/') . '(.*?))?' .
                   preg_quote($end_tag, '/') . '/s';
        // Поиск всех блоков
        $this->content = preg_replace_callback($pattern, function ($matches) use ($val) {
            // $matches[1] — содержимое до ELSE или END
            // $matches[3] — содержимое после ELSE (если есть)
            $true_content = trim($matches[1]);
            $false_content = isset($matches[3]) ? trim($matches[3]) : '';
            // Выбор содержимого в зависимости от значения $val
            return $val ? $true_content : $false_content;
        }, $this->content);
        // Если остались незакрытые теги, выбрасываем исключение
        if (strpos($this->content, $begin_tag) !== false || strpos($this->content, $end_tag) !== false) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка обработки блока условий | Незакрытые или несоответствующие теги для ключа: {$key}"
            );
        }
    }

    /**
     * @brief Метод обрабатывает блок выбора фрагмента шаблона, заменяя его содержимое в зависимости от значения условия.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Проверяет корректность содержимого шаблона (`$this->content`), которое должно быть непустой строкой.
     *          2. Формирует ключи для поиска блоков в шаблоне: `<!-- SELECT_НАЗВАНИЕ_BEGIN -->` и `<!-- SELECT_НАЗВАНИЕ_END -->`.
     *          3. Использует регулярное выражение для поиска всех блоков, заключенных между этими тегами.
     *          4. Проверяет, что внутри блока нет вложенных блоков (вложенные блоки не допускаются).
     *          5. Ищет блоки `<!-- CASE_ЗНАЧЕНИЕ -->` и `<!-- BREAK_ЗНАЧЕНИЕ -->`, соответствующие значению `$val`.
     *          6. Если блок с указанным значением не найден, используется блок `<!-- CASE_DEFAULT -->` (если он существует).
     *          7. Заменяет найденный блок на его содержимое.
     *          8. Если остались незакрытые или несоответствующие теги, выбрасывает исключение.
     *          Метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Template::$content Свойство, содержащее содержимое шаблона.
     *
     * @param string $key Ключ-название условия. Должен быть строкой, соответствующей имени условия в шаблоне (например, `SELECT_НАЗВАНИЕ`).
     * @param mixed $val Значение условия. Определяет, какой блок `CASE_ЗНАЧЕНИЕ` будет выбран для вывода. Если блок не найден, используется `CASE_DEFAULT`.
     *
     * @throws RuntimeException Если содержимое шаблона некорректно (не является непустой строкой).
     * @throws RuntimeException Если обнаружены вложенные блоки внутри `SELECT_НАЗВАНИЕ`.
     * @throws RuntimeException Если остались незакрытые или несоответствующие теги после обработки.
     *
     * Пример вызова метода внутри класса
     * @code
     * $this->template_case('SELECT_EXAMPLE', 'VALUE1');
     * @endcode
     */
    private function template_case(string $key, $val): void
    {
        // Проверка, что $this->content является строкой и не пустая
        if (!is_string($this->content) || empty($this->content)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное содержимое шаблона | Значение должно быть непустой строкой"
            );
        }

        // Формирование ключей для поиска
        $begin_tag = '<!-- ' . $key . '_BEGIN -->';
        $end_tag = '<!-- ' . $key . '_END -->';

        // Регулярное выражение для поиска всех блоков
        $pattern = '/' . preg_quote($begin_tag, '/') . '(.*?)' . preg_quote($end_tag, '/') . '/s';

        // Поиск всех блоков
        $this->content = preg_replace_callback($pattern, function ($matches) use ($key, $val) {
            $block_content = $matches[1]; // Содержимое между BEGIN и END

            // Проверка на вложенность блоков SELECT_LEFT_BLOCK
            if (strpos($block_content, '<!-- ' . $key . '_BEGIN -->') !== false ||
                strpos($block_content, '<!-- ' . $key . '_END -->') !== false) {
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка обработки блока выбора | Вложенные блоки не допускаются для ключа: {$key}, значение: {$val}"
                );
            }

            // Регулярное выражение для поиска всех CASE и их содержимого
            $case_pattern = '/<!-- CASE_(.*?) -->\s*(.*?)\s*<!-- BREAK_\1 -->/s';
            preg_match_all($case_pattern, $block_content, $case_matches, PREG_SET_ORDER);

            // Добавляем DEFAULT, если он есть
            $default_pattern = '/<!-- CASE_DEFAULT -->\s*(.*?)\s*<!-- BREAK_DEFAULT -->/s';
            preg_match($default_pattern, $block_content, $default_match);

            // Поиск нужного CASE
            $result = '';
            foreach ($case_matches as $match) {
                if ($match[1] == $val) {
                    $result = $match[2]; // Содержимое найденного CASE
                    break;
                }
            }

            // Если CASE не найден, используем DEFAULT
            if (empty($result) && isset($default_match[1])) {
                $result = $default_match[1];
            }

            return $result;
        }, $this->content);

        // Если остались незакрытые теги, выбрасываем исключение
        if (strpos($this->content, $begin_tag) !== false || strpos($this->content, $end_tag) !== false) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка обработки блока выбора | Незакрытые или несоответствующие теги для ключа: {$key}, значение: {$val}"
            );
        }
    }

    /**
     * @brief Метод преобразует URL в более читаемый вид с использованием правил `mod_rewrite`, если они включены.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Проверяет, включен ли режим `mod_rewrite` (свойство `$this->mod_rewrite`).
     *          2. В зависимости от флага `$txt` определяет формат окончания ссылки.
     *          3. Определяет шаблоны для замены URL на основе параметров запроса (например, `?action=profile&id=123` → `profile/id_123.html`).
     *          4. Выполняет замену URL в переданном содержимом (`$content`) с использованием регулярных выражений.
     *          5. Возвращает обработанное содержимое.
     *          Метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     *
     * @see PhotoRigma::Classes::Template::$mod_rewrite Свойство, определяющее, включен ли режим `mod_rewrite`.
     *
     * @param string $content Содержимое для обработки. Должно быть строкой, содержащей HTML или текст с URL.
     * @param bool $txt Флаг текстового содержимого. Если `true`, предполагается, что ссылки заканчиваются на `()`. По умолчанию `false`.
     *
     * @return string Обработанное содержимое с преобразованными URL.
     *
     * Пример вызова метода внутри класса
     * @code
     * $processed_content = $this->url_mod_rewrite('<a href="?action=profile&id=123">Profile</a>');
     * echo $processed_content; // Результат: <a href="profile/id_123.html">Profile</a>
     * @endcode
     */
    private function url_mod_rewrite(string $content, bool $txt = false): string
    {
        // Проверка, включен ли mod_rewrite
        if ($this->mod_rewrite) {
            // Определение окончания ссылки в зависимости от флага $txt
            $end = $txt ? '()' : '("|\')';

            // Шаблоны для замены URL
            $patterns = [
                // Пример: ?action=profile&id=123 -> profile/id_123.html
                '/\?action=([A-Za-z0-9]+)(\&|\&)id=([0-9]+)' . $end . '/',

                // Пример: ?action=resend&login=user&email=user@example.com&resend=true -> resend/login=user/email=user@example.com/resend.html
                '/\?action=([A-Za-z0-9]+)(\&|\&)login=([^"?]+)(\&|\&)email=([^"?]+)(\&|\&)resend=true' . $end . '/',

                // Пример: ?action=activate&login=user&email=user@example.com&activated_code=abc123 -> activate/login=user/email=user@example.com/activated_code_abc123.html
                '/\?action=([A-Za-z0-9]+)(\&|\&)login=([^"?]+)(\&|\&)email=([^"?]+)(\&|\&)activated_code=([A-Za-z0-9]+)' . $end . '/',

                // Пример: ?action=reset&login=user&email=user@example.com -> reset/login=user/email=user@example.com/
                '/\?action=([A-Za-z0-9]+)(\&|\&)login=([^"?]+)(\&|\&)email=([^"?]+)' . $end . '/',

                // Пример: ?action=home -> home/
                '/\?action=([A-Za-z0-9]+)' . $end . '/'
            ];

            // Замены для шаблонов
            $replacements = [
                '\\1/id_\\3.html\\4', // profile/id_123.html
                '\\1/login=\\3/email=\\5/resend.html\\7', // resend/login=user/email=user@example.com/resend.html
                '\\1/login=\\3/email=\\5/activated_code_\\6.html\\7', // activate/login=user/email=user@example.com/activated_code_abc123.html
                '\\1/login=\\3/email=\\5/\\6', // reset/login=user/email=user@example.com/
                '\\1/\\2' // home/
            ];

            // Выполнение замены
            $content = preg_replace($patterns, $replacements, $content);
        }

        return $content;
    }

    /**
     * @brief Метод проверяет существование, тип и доступность файла шаблона для чтения.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Проверяет существование файла шаблона по указанному пути.
     *          2. Проверяет, является ли объект файлом.
     *          3. Проверяет, доступен ли файл для чтения.
     *          4. Если все проверки пройдены, сохраняет полный путь к файлу в свойство `$template_file`.
     *          Метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     *
     * @see PhotoRigma::Classes::Template::$themes_path Свойство, содержащее путь к директории тем.
     * @see PhotoRigma::Classes::Template::$template_file Свойство, содержащее имя или путь к файлу шаблона.
     *
     * @throws RuntimeException Если файл шаблона не найден.
     * @throws RuntimeException Если указанный путь не является файлом.
     * @throws RuntimeException Если файл недоступен для чтения.
     *
     * @note Метод автоматически проверяет корректность передаваемого имени файла шаблона.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $this->find_template_file();
     * @endcode
     */
    private function find_template_file(): void
    {
        $full_path = $this->themes_path . $this->template_file;
        // Проверяем существование объекта по указанному пути
        if (!file_exists($full_path)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл шаблона не найден | Путь: {$full_path}"
            );
        }
        // Проверяем, является ли объект файлом
        if (!is_file($full_path)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Указанный путь не является файлом | Путь: {$full_path}"
            );
        }
        // Проверяем, доступен ли файл для чтения
        if (!is_readable($full_path)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл недоступен для чтения | Путь: {$full_path}"
            );
        }
        // Если все проверки пройдены, сохраняем полный путь к файлу
        $this->template_file = $full_path;
    }
}
