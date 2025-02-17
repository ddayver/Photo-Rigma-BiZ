<?php

/**
 * @file        include/template.php
 * @brief       Класс для работы с HTML-шаблонами, включая рендеринг и подстановку данных.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-02-14
 * @namespace   PhotoRigma\\Classes
 *
 * @details     Основные функции — обработка шаблонов с реализацией алгоритмов IF/ELSE, ARRAY, SWITCH/CASE.
 *              Остальное будет добавлено после документирования класса.
 *
 * @see         \\PhotoRigma\\Classes\\Template Класс для работы с шаблонами.
 * @see         \\PhotoRigma\\Classes\\Template_Interface Интерфейс для работы с шаблонами.
 * @see         \\PhotoRigma\\Include\\log_in_file() Функция для логирования ошибок.
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
 * @class template
 * @brief Класс для работы с шаблонами
 */
class template
{
    private $site_url;
    private $site_dir;
    private $theme;
    private $themes_path;
    private $themes_url;
    private $template_file;
    private $block_string = [];
    private $block_if = [];
    private $block_case = [];
    private $block_object = [];
    private $mod_rewrite;
    public $ins_body = '';
    public $ins_header = '';
    public $content;

    /**
     * Конструктор класса.
     *
     * @param string $site_url URL сайта.
     * @param string $site_dir Директория сайта.
     * @param string $theme Тема оформления.
     */
    public function __construct(string $site_url, string $site_dir, string $theme)
    {
        $this->site_url = $site_url;
        $this->site_dir = $site_dir;
        $this->theme = $theme;
        $this->themes_path = $this->site_dir . 'themes/' . $this->theme . '/';
        $this->themes_url = $this->site_url . 'themes/' . $this->theme . '/';
    }
    /**
     * Указание какой именно файл шаблона использовать
     */
    public function point_template_file(string $temp_file)
    {
        $this->template_file = $temp_file;
    }

    /**
     * Поиск файла шаблона.
     *
     * @return bool True, если файл найден, иначе False.
     */
    private function find_template_file(): bool
    {
        $full_path = $this->themes_path . $this->template_file;

        // Проверяем существование объекта по указанному пути
        if (!file_exists($full_path)) {
            log_in_file('Not found template file: ' . $full_path, DIE_IF_ERROR);
            return false;
        }

        // Проверяем, является ли объект файлом
        if (!is_file($full_path)) {
            log_in_file('The path is not a file: ' . $full_path, DIE_IF_ERROR);
            return false;
        }

        // Проверяем, доступен ли файл для чтения
        if (!is_readable($full_path)) {
            log_in_file('File is not readable: ' . $full_path, DIE_IF_ERROR);
            return false;
        }

        // Если все проверки пройдены, сохраняем полный путь к файлу
        $this->template_file = $full_path;
        return true;
    }

    /**
     * Создание и обработка шаблона.
     */
    public function create_template(): void
    {
        $this->find_template_file();
        $this->content = file_get_contents($this->template_file);
        if (!$this->content) {
            log_in_file('Error template file: ' . $this->template_file, DIE_IF_ERROR);
        }
        $this->pars_template();
        $this->content = str_replace('{SITE_URL}', htmlspecialchars($this->site_url, ENT_QUOTES, 'UTF-8'), $this->content);
        $this->content = str_replace('{THEME_URL}', htmlspecialchars($this->themes_url, ENT_QUOTES, 'UTF-8'), $this->content);
        if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
            $this->content = stripslashes($this->content);
        }
    }

    /**
     * Добавление строковой переменной для замены в шаблоне.
     *
     * @param string $name Название переменной.
     * @param mixed $value Значение переменной.
     * @param string|false $path_array Путь для рекурсивного размещения переменной.
     */
    public function add_string(string $name, $value, $path_array = false): void
    {
        if ($path_array === false) {
            $this->block_string[strtoupper($name)] = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        } else {
            $temp_result = $this->test_is_object($path_array);
            $this->block_object[$temp_result['current']][$temp_result['index']]->add_string($name, $value, $temp_result['next_path']);
        }
    }

    /**
     * Добавление массива строковых данных для замены в шаблоне.
     *
     * @param array $array_data Массив данных.
     * @param string|false $path_array Путь для рекурсивного размещения переменной.
     */
    public function add_string_ar(array $array_data, $path_array = false): void
    {
        foreach ($array_data as $key => $value) {
            $this->add_string($key, $value, $path_array);
        }
    }

    /**
     * Добавление данных об условиях вывода фрагментов шаблона.
     *
     * @param string $name Название условия.
     * @param bool $value Значение условия.
     * @param string|false $path_array Путь для рекурсивного размещения переменной.
     */
    public function add_if(string $name, bool $value, $path_array = false): void
    {
        if ($path_array === false) {
            $this->block_if['IF_' . strtoupper($name)] = $value;
        } else {
            $temp_result = $this->test_is_object($path_array);
            $this->block_object[$temp_result['current']][$temp_result['index']]->add_if($name, $value, $temp_result['next_path']);
        }
    }

    /**
    * Добавление массива данных об условиях вывода фрагментов шаблона.
    *
    * @param array $array_data Массив условий.
    * @param string|false $path_array Путь для рекурсивного размещения переменной.
    */
    public function add_if_ar(array $array_data, $path_array = false): void
    {
        foreach ($array_data as $key => $value) {
            $this->add_if($key, $value, $path_array);
        }
    }

    /**
     * Добавление данных о выборе блока для вывода фрагментов шаблона.
     *
     * @param string $name Название условия.
     * @param string $value Значение условия.
     * @param string|false $path_array Путь для рекурсивного размещения переменной.
     */
    public function add_case(string $name, string $value, $path_array = false): void
    {
        if ($path_array === false) {
            $this->block_case['SELECT_' . strtoupper($name)] = strtoupper($value);
        } else {
            $temp_result = $this->test_is_object($path_array);
            $this->block_object[$temp_result['current']][$temp_result['index']]->add_case($name, $value, $temp_result['next_path']);
        }
    }

    /**
    * Проверяет существование и создает рекурсивный блок массивов-объектов.
    *
    * @param string $path_array Строка пути, по которому необходимо создать объект-массив.
    *                           Формат: Массив1[0]->Массив1.0[0].
    * @return array Результат проверки:
    *               - 'current' - текущий объект (имя массива),
    *               - 'index' - индекс элемента в массиве,
    *               - 'next_path' - остаток пути (если путь полностью разобран, то FALSE).
    */
    private function test_is_object(string $path_array): array
    {
        // Разбиваем путь на части по разделителю '->'
        $tmp_path = explode('->', $path_array);
        $tmp_p = $tmp_path[0]; // Берем первую часть пути
        $tmp_p = strtoupper($tmp_p); // Приводим к верхнему регистру

        // Разбиваем имя массива и индекс по символу '['
        $tmp = explode('[', $tmp_p);

        // Проверяем корректность имени массива и индекса
        if (mb_ereg('^[A-Z_]+$', $tmp[0]) && isset($tmp[1]) && !empty($tmp[1])) {
            $tmp[1] = str_replace(']', '', $tmp[1]); // Убираем закрывающую скобку ']'
            if (!mb_ereg('^[0-9]+$', $tmp[1])) {
                log_in_file('Error in Path OBJ::' . $path_array, DIE_IF_ERROR);
            }
        } else {
            log_in_file('Error in Path OBJ::' . $path_array, DIE_IF_ERROR);
        }

        // Проверяем, существует ли объект в массиве block_object
        if (!isset($this->block_object[$tmp[0]][$tmp[1]]) || !is_object($this->block_object[$tmp[0]][$tmp[1]])) {
            // Если объект не существует, создаем новый экземпляр класса template
            $temp = new template($this->site_url, $this->site_dir, $this->theme);
            $this->block_object[$tmp[0]][$tmp[1]] = $temp;
        }

        // Формируем результат
        $result['current'] = $tmp[0]; // Имя массива
        $result['index'] = $tmp[1];  // Индекс элемента
        if (count($tmp_path) > 1) {
            // Если есть продолжение пути, сохраняем его
            unset($tmp_path[0]);
            $result['next_path'] = implode('->', $tmp_path);
        } else {
            // Если путь полностью разобран, возвращаем FALSE
            $result['next_path'] = false;
        }

        return $result;
    }

    /**
     * Обработка шаблона.
     */
    private function pars_template(): void
    {
        foreach ($this->block_object as $key => $val) {
            $this->template_object($key, $val);
        }
        foreach ($this->block_if as $key => $val) {
            $this->template_if($key, $val);
        }
        foreach ($this->block_case as $key => $val) {
            $this->template_case($key, $val);
        }
        foreach ($this->block_string as $key => $val) {
            $this->content = str_replace('{' . $key . '}', $val, $this->content);
        }
        $this->content = $this->url_mod_rewrite($this->content);
        $this->content = str_replace(["\r\n", "\r", "\n"], '{BR}', $this->content);
        while (strpos($this->content, '{BR}{BR}') !== false) {
            $this->content = str_replace('{BR}{BR}', '{BR}', $this->content);
        }
        $this->content = str_replace('{BR}', PHP_EOL, $this->content);
        $this->content = str_replace('&amp;amp;', '&amp;', $this->content);
    }

    /**
    * Обработка рекурсивного блока массивов-объектов.
    *
    * @param string $key Ключ блока.
    * @param array $index Индекс блока.
    */
    private function template_object($key, $index)
    {
        // Регулярное выражение для поиска блоков
        $pattern = '/<!-- ARRAY_' . preg_quote($key, '/') . '_BEGIN -->(.*?)<!-- ARRAY_' . preg_quote($key, '/') . '_END -->/s';
        if (preg_match_all($pattern, $this->content, $matches)) {
            foreach ($matches[1] as $match) {
                $block_content = '';
                foreach ($index as $id => $value) {
                    $value->content = $match;
                    $value->pars_template();
                    $block_content .= $value->content;
                }
                // Заменяем исходный блок на обработанный
                $this->content = str_replace(
                    '<!-- ARRAY_' . $key . '_BEGIN -->' . $match . '<!-- ARRAY_' . $key . '_END -->',
                    $block_content,
                    $this->content
                );
            }
        } else {
            log_in_file('Error template OBJ::' . $key, DIE_IF_ERROR);
        }
    }

    /**
    * Обработка блока условий вывода фрагментов шаблона.
    *
    * @param string $key Ключ условия.
    * @param bool $val Значение условия.
     */
    private function template_if($key, $val)
    {
        $pattern = '/<!-- IF_' . preg_quote($key, '/') . '_BEGIN -->(.*?)<!-- IF_' . preg_quote($key, '/') . '_ELSE -->(.*?)<!-- IF_' . preg_quote($key, '/') . '_END -->/s';
        if (preg_match_all($pattern, $this->content, $matches)) {
            foreach ($matches[0] as $i => $match) {
                $temp_content = $matches[0][$i];
                $if_block = $matches[1][$i];
                $else_block = $matches[2][$i];

                // Выбираем блок в зависимости от значения $val
                $replacement = $val ? $if_block : $else_block;

                // Заменяем исходный блок на выбранный
                $this->content = str_replace($temp_content, $replacement, $this->content);
            }
        } else {
            // Если нет ELSE, обрабатываем только BEGIN-END
            $pattern_no_else = '/<!-- IF_' . preg_quote($key, '/') . '_BEGIN -->(.*?)<!-- IF_' . preg_quote($key, '/') . '_END -->/s';
            if (preg_match_all($pattern_no_else, $this->content, $matches_no_else)) {
                foreach ($matches_no_else[0] as $i => $match) {
                    $temp_content = $matches_no_else[0][$i];
                    $if_block = $matches_no_else[1][$i];

                    // Выбираем блок в зависимости от значения $val
                    $replacement = $val ? $if_block : '';

                    // Заменяем исходный блок на выбранный
                    $this->content = str_replace($temp_content, $replacement, $this->content);
                }
            } else {
                log_in_file('Error template IF: ' . $key, DIE_IF_ERROR);
            }
        }
    }

    /**
       * Обработка блока выбора фрагмента для вывода в шаблон.
       *
       * @param string $key Ключ условия.
       * @param string $val Значение условия.
    */
    private function template_case($key, $val)
    {
        $pattern = '/<!-- SELECT_' . preg_quote($key, '/') . '_BEGIN -->(.*?)<!-- CASE_(DEFAULT|[A-Z0-9_]+) -->(.*?)<!-- BREAK -->(.*?)<!-- SELECT_' . preg_quote($key, '/') . '_END -->/s';
        if (preg_match_all($pattern, $this->content, $matches)) {
            foreach ($matches[0] as $i => $match) {
                $temp_content = $matches[0][$i];
                $case_value = $matches[2][$i];
                $case_block = $matches[3][$i];
                $default_block = null;

                // Проверяем, есть ли блок DEFAULT
                if ($case_value === 'DEFAULT') {
                    $default_block = $case_block;
                    continue; // Пропускаем DEFAULT при первом проходе
                }

                // Если значение совпадает, выбираем соответствующий блок
                if (strtoupper($val) === $case_value) {
                    $this->content = str_replace($temp_content, $case_block, $this->content);
                    return;
                }
            }

            // Если ни один блок не совпал, используем DEFAULT
            if ($default_block !== null) {
                $this->content = str_replace($temp_content, $default_block, $this->content);
            } else {
                log_in_file('Error template SELECT-CASE: No matching case or default block for ' . $key, DIE_IF_ERROR);
            }
        } else {
            log_in_file('Error template SELECT-CASE: ' . $key, DIE_IF_ERROR);
        }
    }

    /**
     * Формирование заголовка страницы.
     *
     * @param string $title Дополнительное название страницы для тега Title.
     */
    public function page_header(string $title): void
    {
        global $lang, $work, $action;

        $s_menu = $work->create_menu($action, 0);
        $l_menu = $work->create_menu($action, 1);
        $photo_top = $work->create_photo('top');
        $photo_last = $work->create_photo('last');

        $temp_template = new template($this->site_url, $this->site_dir, $this->theme);

        if ($this->ins_body !== '') {
            $this->ins_body = ' ' . $this->ins_body;
        }

        $temp_template->add_string_ar([
        'TITLE' => empty($title) ? $work->config['title_name'] : htmlspecialchars(strip_tags($work->config['title_name'])) . ' - ' . htmlspecialchars(strip_tags($title)),
            'INSERT_HEADER' => $this->ins_header,
            'INSERT_BODY' => $this->ins_body,
        'META_DESCRIPTION' => htmlspecialchars(strip_tags($work->config['meta_description'])),
        'META_KEYWORDS' => htmlspecialchars(strip_tags($work->config['meta_keywords'])),
            'GALLERY_WIDTH' => $work->config['gal_width'],
        'SITE_NAME' => htmlspecialchars(strip_tags($work->config['title_name'])),
        'SITE_DESCRIPTION' => htmlspecialchars(strip_tags($work->config['title_description'])),
            'U_SEARCH' => $work->config['site_url'] . '?action=search',
            'L_SEARCH' => $lang['main']['search'],
            'LEFT_PANEL_WIDTH' => $work->config['left_panel'],
            'RIGHT_PANEL_WIDTH' => $work->config['right_panel']
        ]);

        if ($s_menu && is_array($s_menu)) {
            $temp_template->add_if('SHORT_MENU', true);
            foreach ($s_menu as $id => $value) {
                $temp_template->add_string_ar([
                    'U_SHORT_MENU' => $value['url'],
                    'L_SHORT_MENU' => $value['name']
                ], 'SHORT_MENU[' . $id . ']');
                $temp_template->add_if('SHORT_MENU_URL', !empty($value['url']), 'SHORT_MENU[' . $id . ']');
            }
        } else {
            $temp_template->add_if('SHORT_MENU', false);
        }

        $temp_template->add_case('LEFT_BLOCK', 'MENU', 'LEFT_PANEL[0]');
        $temp_template->add_if('LONG_MENU', false, 'LEFT_PANEL[0]');

        if ($l_menu && is_array($l_menu)) {
            $temp_template->add_if('LONG_MENU', true, 'LEFT_PANEL[0]');
            $temp_template->add_string('LONG_MENU_NAME_BLOCK', $lang['menu']['name_block'], 'LEFT_PANEL[0]');
            foreach ($l_menu as $id => $value) {
                $temp_template->add_string_ar([
                    'U_LONG_MENU' => $value['url'],
                    'L_LONG_MENU' => $value['name']
                ], 'LEFT_PANEL[0]->LONG_MENU[' . $id . ']');
                $temp_template->add_if('LONG_MENU_URL', !empty($value['url']), 'LEFT_PANEL[0]->LONG_MENU[' . $id . ']');
            }
        }

        $temp_template->add_case('LEFT_BLOCK', 'TOP_LAST_PHOTO', 'LEFT_PANEL[1]');
        $temp_template->add_string_ar([
            'NAME_BLOCK' => $photo_top['name_block'],
            'PHOTO_WIDTH' => $photo_top['width'],
            'PHOTO_HEIGHT' => $photo_top['height'],
            'MAX_FOTO_HEIGHT' => $work->config['temp_photo_h'] + 10,
            'D_NAME_PHOTO' => $photo_top['name'],
            'D_DESCRIPTION_PHOTO' => $photo_top['description'],
            'D_NAME_CATEGORY' => $photo_top['category_name'],
            'D_DESCRIPTION_CATEGORY' => $photo_top['category_description'],
            'PHOTO_RATE' => $photo_top['rate'],
            'L_USER_ADD' => $lang['main']['user_add'],
            'U_PROFILE_USER_ADD' => $photo_top['url_user'],
            'D_REAL_NAME_USER_ADD' => $photo_top['real_name'],
            'U_PHOTO' => $photo_top['url'],
            'U_THUMBNAIL_PHOTO' => $photo_top['thumbnail_url'],
            'U_CATEGORY' => $photo_top['category_url']
        ], 'LEFT_PANEL[1]');
        $temp_template->add_if('USER_EXISTS', !empty($photo_top['url_user']), 'LEFT_PANEL[1]');

        $temp_template->add_case('LEFT_BLOCK', 'TOP_LAST_PHOTO', 'LEFT_PANEL[2]');
        $temp_template->add_string_ar([
            'NAME_BLOCK' => $photo_last['name_block'],
            'PHOTO_WIDTH' => $photo_last['width'],
            'PHOTO_HEIGHT' => $photo_last['height'],
            'MAX_FOTO_HEIGHT' => $work->config['temp_photo_h'] + 10,
            'D_NAME_PHOTO' => $photo_last['name'],
            'D_DESCRIPTION_PHOTO' => $photo_last['description'],
            'D_NAME_CATEGORY' => $photo_last['category_name'],
            'D_DESCRIPTION_CATEGORY' => $photo_last['category_description'],
            'PHOTO_RATE' => $photo_last['rate'],
            'L_USER_ADD' => $lang['main']['user_add'],
            'U_PROFILE_USER_ADD' => $photo_last['url_user'],
            'D_REAL_NAME_USER_ADD' => $photo_last['real_name'],
            'U_PHOTO' => $photo_last['url'],
            'U_THUMBNAIL_PHOTO' => $photo_last['thumbnail_url'],
            'U_CATEGORY' => $photo_last['category_url']
        ], 'LEFT_PANEL[2]');
        $temp_template->add_if('USER_EXISTS', !empty($photo_last['url_user']), 'LEFT_PANEL[2]');

        $temp_template->point_template_file('header.html');
        $temp_template->create_template();
        $this->content = $temp_template->content . $this->content;
        unset($temp_template);
    }

    /**
     * Формирование "подвала" страницы.
     */
    public function page_footer(): void
    {
        global $lang, $work;

        $user = $work->template_user();
        $stat = $work->template_stat();
        $best_user = $work->template_best_user($work->config['best_user']);
        $rand_photo = $work->create_photo('rand');

        $temp_template = new template($this->site_url, $this->site_dir, $this->theme);

        $temp_template->add_string_ar([
        'COPYRIGHT_YEAR' => htmlspecialchars(strip_tags($work->config['copyright_year'])),
        'COPYRIGHT_URL' => htmlspecialchars(strip_tags($work->config['copyright_url'])),
        'COPYRIGHT_TEXT' => htmlspecialchars(strip_tags($work->config['copyright_text']))
        ]);

        $temp_template->add_case('RIGHT_BLOCK', 'USER_INFO', 'RIGHT_PANEL[0]');
        $temp_template->add_string_ar($user, 'RIGHT_PANEL[0]');
        $temp_template->add_if('USER_NOT_LOGIN', (isset($_SESSION['login_id']) && $_SESSION['login_id'] == 0), 'RIGHT_PANEL[0]');

        $temp_template->add_case('RIGHT_BLOCK', 'STATISTIC', 'RIGHT_PANEL[1]');
        $temp_template->add_string_ar($stat, 'RIGHT_PANEL[1]');

        $temp_template->add_case('RIGHT_BLOCK', 'BEST_USER', 'RIGHT_PANEL[2]');
        $temp_template->add_string_ar($best_user[0], 'RIGHT_PANEL[2]');
        unset($best_user[0]);

        foreach ($best_user as $key => $val) {
            $temp_template->add_string_ar([
                'U_BEST_USER_PROFILE' => $val['user_url'],
                'D_USER_NAME' => $val['user_name'],
                'D_USER_PHOTO' => $val['user_photo']
            ], 'RIGHT_PANEL[2]->BEST_USER[' . $key . ']');
            $temp_template->add_if('USER_EXIST', !empty($val['user_url']), 'RIGHT_PANEL[2]->BEST_USER[' . $key . ']');
        }

        $temp_template->add_case('RIGHT_BLOCK', 'RANDOM_PHOTO', 'RIGHT_PANEL[3]');
        $temp_template->add_string_ar([
            'NAME_BLOCK' => $rand_photo['name_block'],
            'PHOTO_WIDTH' => $rand_photo['width'],
            'PHOTO_HEIGHT' => $rand_photo['height'],
            'MAX_FOTO_HEIGHT' => $work->config['temp_photo_h'] + 10,
            'D_NAME_PHOTO' => $rand_photo['name'],
            'D_DESCRIPTION_PHOTO' => $rand_photo['description'],
            'D_NAME_CATEGORY' => $rand_photo['category_name'],
            'D_DESCRIPTION_CATEGORY' => $rand_photo['category_description'],
            'PHOTO_RATE' => $rand_photo['rate'],
            'L_USER_ADD' => $lang['main']['user_add'],
            'U_PROFILE_USER_ADD' => $rand_photo['url_user'],
            'D_REAL_NAME_USER_ADD' => $rand_photo['real_name'],
            'U_PHOTO' => $rand_photo['url'],
            'U_THUMBNAIL_PHOTO' => $rand_photo['thumbnail_url'],
            'U_CATEGORY' => $rand_photo['category_url']
        ], 'RIGHT_PANEL[3]');
        $temp_template->add_if('USER_EXISTS', !empty($rand_photo['url_user']), 'RIGHT_PANEL[3]');

        $temp_template->point_template_file('footer.html');
        $temp_template->create_template();
        $this->content = $this->content . $temp_template->content;
        unset($temp_template);
    }

    /**
     * Замена ссылок на более читаемый вид.
     *
     * @param string $content Содержимое для обработки.
     * @param bool $txt Флаг текстового содержимого.
     * @return string Обработанное содержимое.
     */
    private function url_mod_rewrite(string $content, bool $txt = false): string
    {
        if ($this->mod_rewrite) {
            $end = $txt ? '()' : '("|\')';
            $patterns = [
                '\?action=([A-Za-z0-9]+)(\&|\&)id=([0-9]+)' . $end,
                '\?action=([A-Za-z0-9]+)(\&|\&)login=([^"?]+)(\&|\&)email=([^"?]+)(\&|\&)resend=true' . $end,
                '\?action=([A-Za-z0-9]+)(\&|\&)login=([^"?]+)(\&|\&)email=([^"?]+)(\&|\&)activated_code=([A-Za-z0-9]+)' . $end,
                '\?action=([A-Za-z0-9]+)(\&|\&)login=([^"?]+)(\&|\&)email=([^"?]+)' . $end,
                '\?action=([A-Za-z0-9]+)' . $end
            ];
            $replacements = [
                '\\1/id_\\3.html\\4',
                '\\1/login=\\3/email=\\5/resend.html\\7',
                '\\1/login=\\3/email=\\5/activated_code_\\6.html\\7',
                '\\1/login=\\3/email=\\5/\\6',
                '\\1/\\2'
            ];
            $content = preg_replace($patterns, $replacements, $content);
        }
        return $content;
    }
}
