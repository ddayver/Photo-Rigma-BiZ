<?php

/**
 * @file      include/Work_Template.php
 * @brief     Файл содержит класс Work_Template, который отвечает за формирование данных для шаблонов.
 *
 * @author    Dark Dayver
 * @version   0.4.4
 * @date      2025-05-07
 * @namespace PhotoRigma\\Classes
 *
 * @details   Этот файл содержит класс `Work_Template`, который реализует интерфейс `Work_Template_Interface`.
 *            Класс предоставляет методы для генерации данных, необходимых для отображения различных блоков на
 *            странице, таких как меню, блок пользователя, статистика и список лучших пользователей. Все данные
 *            формируются на основе запросов к базе данных и конфигурации. Реализация методов зависит от глобальных
 *            переменных, таких как $_SESSION, для проверки статуса авторизации.
 *
 * @section   WorkTemplate_Main_Functions Основные функции
 *            - Формирование данных для меню.
 *            - Генерация списка лучших пользователей.
 *            - Генерация статистических данных.
 *            - Формирование блока пользователя.
 *
 * @see       PhotoRigma::Interfaces::Work_Template_Interface
 *            Интерфейс, который реализует данный класс.
 * @see       PhotoRigma::Classes::Work
 *            Класс, через который вызываются методы для работы с шаблонами.
 *
 * @note      Этот файл является частью системы PhotoRigma и играет ключевую роль в формировании данных для шаблонов.
 *            Реализованы меры безопасности для предотвращения несанкционированного доступа к данным через глобальные
 *            переменные.
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
use finfo;
use InvalidArgumentException;
use PhotoRigma\Interfaces\Database_Interface;
use PhotoRigma\Interfaces\User_Interface;
use PhotoRigma\Interfaces\Work_Template_Interface;
use Random;
use RuntimeException;

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
 * @class   Work_Template
 * @brief   Класс для формирования данных для шаблонов.
 *
 * @details Этот класс реализует интерфейс `Work_Template_Interface` и предоставляет функционал для генерации данных,
 *          необходимых для отображения различных блоков на странице, таких как меню, блок пользователя, статистика
 *          и список лучших пользователей. Он является дочерним классом для `PhotoRigma::Classes::Work` и наследует его
 *          методы. Все методы данного класса рекомендуется вызывать через родительский класс `Work`, так как их
 *          поведение может быть непредсказуемым при прямом вызове.
 *          Основные возможности:
 *          - Формирование данных для меню (`create_menu`).
 *          - Генерация списка лучших пользователей (`template_best_user`).
 *          - Генерация статистических данных (`template_stat`).
 *          - Формирование блока пользователя (`template_user`).
 *          Методы класса предназначены для использования в различных частях приложения для отображения данных в
 *          шаблонах.
 *
 * @implements Work_Template_Interface
 *
 * @property array               $config Конфигурация приложения.
 * @property array|null          $lang   Языковые данные (могут быть null при инициализации).
 * @property Database_Interface  $db     Объект для работы с базой данных.
 * @property User_Interface|null $user   Объект пользователя (может быть null при инициализации).
 *
 * Пример использования класса:
 * @code
 * // Инициализация объекта класса Work_Template
 * $db = new \\PhotoRigma\\Classes\\Database();
 * $config = ['site_url' => 'https://example.com'];
 * $template = new \\PhotoRigma\\Classes\\Work_Template($db, $config);
 *
 * // Вызов метода через родительский класс Work
 * $menu_data = \\PhotoRigma\\Classes\\Work::create_menu('home', 0);
 * print_r($menu_data);
 *
 * // Генерация статистических данных
 * $stat_data = \\PhotoRigma\\Classes\\Work::template_stat();
 * print_r($stat_data);
 *
 * // Генерация списка лучших пользователей
 * $best_users = \\PhotoRigma\\Classes\\Work::template_best_user(3);
 * print_r($best_users);
 * @endcode
 * @see    PhotoRigma::Interfaces::Work_Template_Interface
 *         Интерфейс, который реализует данный класс.
 */
class Work_Template implements Work_Template_Interface
{
    //Свойства:
    private array $config; ///< Конфигурация приложения.
    private ?array $lang = null; ///< Языковые данные.
    private Database_Interface $db; ///< Объект для работы с базой данных.
    private ?User_Interface $user = null; ///< Объект пользователя.

    /**
     * @brief   Конструктор класса.
     *
     * @details Инициализирует зависимости: конфигурацию приложения и объект для работы с базой данных.
     *          Все параметры обязательны для корректной работы класса.
     *          Алгоритм работы:
     *          1. Сохраняет переданные зависимости в соответствующие свойства:
     *             - `$config`: массив конфигурации приложения.
     *             - `$db`: объект для работы с базой данных.
     *          2. Проверяет, что все зависимости корректно инициализированы.
     *          Метод вызывается автоматически при создании нового объекта класса.
     *
     * @param array              $config Конфигурация приложения:
     *                                   - Должен быть ассоциативным массивом.
     *                                   - Используется для хранения настроек приложения.
     *                                   Пример: `['temp_photo_w' => 800]`.
     * @param Database_Interface $db     Объект для работы с базой данных:
     *                                   - Должен реализовывать интерфейс `Database_Interface`.
     *                                   - Используется для выполнения запросов к базе данных.
     *                                   Пример: `$db = new Database();`.
     *
     * @note    Важно: все зависимости должны быть корректно инициализированы перед использованием класса.
     *
     * @warning Убедитесь, что:
     *          - `$config` является ассоциативным массивом.
     *          - `$db` реализует интерфейс `Database_Interface`.
     *          Несоблюдение этих условий может привести к ошибкам инициализации.
     *
     * Пример использования конструктора:
     * @code
     * $config = ['temp_photo_w' => 800];
     * $db = new Database();
     * $template = new Work_Template($config, $db);
     * @endcode
     * @see    PhotoRigma::Classes::Work
     *         Родительский класс, через который передаются зависимости.
     * @see    PhotoRigma::Classes::Work_Template::$config
     *         Свойство, содержащее конфигурацию приложения.
     * @see    PhotoRigma::Classes::Work_Template::$db
     *         Свойство, содержащее объект для работы с базой данных.
     */
    public function __construct(Database_Interface $db, array $config)
    {
        $this->config = $config;
        $this->db = $db;
    }

    /**
     * @brief   Получает значение приватного свойства.
     *
     * @details Этот метод позволяет получить доступ к приватному свойству `$config`.
     *          Алгоритм работы:
     *          1. Проверяет, соответствует ли имя свойства допустимому значению (`config`).
     *          2. Если имя свойства корректно, возвращает значение свойства `$config`.
     *          3. Если имя свойства некорректно, выбрасывается исключение.
     *          Метод является публичным и предназначен для получения доступа к свойству `$config`.
     *
     * @param string $name Имя свойства:
     *                     - Допустимое значение: 'config'.
     *                     - Если указано другое имя, выбрасывается исключение.
     *                     Пример: 'config'.
     *                     Ограничения: только одно допустимое значение.
     *
     * @return array Значение свойства `$config`.
     *               Пример: ['temp_photo_w' => 800]
     *
     * @throws InvalidArgumentException Выбрасывается, если запрашиваемое свойство не существует.
     *                                  Пример сообщения:
     *                                      Свойство не существует | Получено: [$name]
     *
     * @note    Этот метод предназначен только для доступа к свойству `$config`.
     *          Любые другие запросы будут игнорироваться с выбросом исключения.
     *
     * @warning Убедитесь, что вы запрашиваете только допустимое свойство:
     *          - 'config'
     *          Некорректные имена свойств вызовут исключение.
     *
     * Пример использования метода:
     * @code
     * $template = new \PhotoRigma\Classes\Work_Template(['temp_photo_w' => 800], $db);
     * echo $template->config['temp_photo_w']; // Выведет: 800
     * @endcode
     * @see    PhotoRigma::Classes::Work_Template::$config
     *         Свойство, к которому обращается метод.
     */
    public function __get(string $name): array
    {
        if ($name === 'config') {
            return $this->config;
        }
        throw new InvalidArgumentException(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не существует | Получено: '$name'"
        );
    }

    /**
     * @brief   Устанавливает значение приватного свойства.
     *
     * @details Этот метод позволяет изменить значение приватного свойства `$config`.
     *          Алгоритм работы:
     *          1. Проверяет, соответствует ли имя свойства допустимому значению (`config`).
     *          2. Если имя свойства корректно, обновляет значение свойства `$config`.
     *          3. Если имя свойства некорректно, выбрасывается исключение.
     *          Метод является публичным и предназначен для изменения свойства `$config`.
     *
     * @param string $name  Имя свойства:
     *                      - Допустимое значение: 'config'.
     *                      - Если указано другое имя, выбрасывается исключение.
     *                      Пример: 'config'.
     *                      Ограничения: только одно допустимое значение.
     * @param array  $value Новое значение свойства:
     *                      - Должен быть ассоциативным массивом.
     *                      Пример: `['temp_photo_w' => 1024]`.
     *
     * @throws InvalidArgumentException Выбрасывается, если переданное имя свойства не соответствует `$config`.
     *                                  Пример сообщения:
     *                                      Свойство не может быть установлено | Получено: [$name]
     *
     * @note    Этот метод предназначен только для изменения свойства `$config`.
     *          Любые другие попытки установки значений будут игнорироваться с выбросом исключения.
     *
     * @warning Убедитесь, что вы устанавливаете значение только для допустимого свойства:
     *          - 'config'
     *          Некорректные имена свойств вызовут исключение.
     *
     * Пример использования метода:
     * @code
     * $template = new \PhotoRigma\Classes\Work_Template([], $db);
     * $template->config = ['temp_photo_w' => 1024];
     * echo $template->config['temp_photo_w']; // Выведет: 1024
     * @endcode
     * @see    PhotoRigma::Classes::Work_Template::$config
     *         Свойство, которое изменяет метод.
     */
    public function __set(string $name, array $value): void
    {
        if ($name === 'config') {
            $this->config = $value;
        } else {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не может быть установлено | Получено: '$name'"
            );
        }
    }

    /**
     * @brief   Проверяет существование недоступного свойства.
     *
     * @details Этот метод вызывается автоматически при использовании оператора `isset()` для проверки
     *          существования недоступного свойства. Метод возвращает `true`, если свойство существует,
     *          и `false` в противном случае.
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
     * $work_template = new \PhotoRigma\Classes\Work_Template();
     * if (isset($work_template->config)) {
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
     * @brief   Формирует массив данных для меню через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _create_menu_internal().
     *          Он формирует массив данных для меню на основе запроса к базе данных, учитывая тип меню и активный
     *          пункт.
     *          Метод также доступен через метод-фасад `create_menu()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $action Активный пункт меню:
     *                       - Указывается строка, соответствующая активному пункту меню (например, 'home', 'profile').
     * @param int    $menu   Тип меню:
     *                       - SHORT_MENU (0): Краткое горизонтальное меню.
     *                       - LONG_MENU (1): Полное вертикальное меню.
     *                       Другие значения недопустимы и приведут к выбросу исключения InvalidArgumentException.
     *
     * @return array Массив с данными для меню:
     *               - Каждый элемент массива содержит:
     *                 - Ключ 'url': URL пункта меню (null, если пункт активен).
     *                 - Ключ 'name': Название пункта меню (локализованное через $this->lang['menu'] или дефолтное
     *                   значение). Если меню пустое, возвращается пустой массив.
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
     *          Также убедитесь, что права доступа пользователя ($this->user) настроены правильно.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Template
     * $template = new \PhotoRigma\Classes\Work_Template();
     *
     * // Создание горизонтального меню
     * $short_menu = $template->create_menu('home', SHORT_MENU);
     * print_r($short_menu);
     *
     * // Создание вертикального меню
     * $long_menu = $template->create_menu('profile', LONG_MENU);
     * print_r($long_menu);
     * @endcode
     * @see    PhotoRigma::Classes::Work_Template::_create_menu_internal()
     *         Защищённый метод, выполняющий основную логику.
     * @see    PhotoRigma::Classes::Work::create_menu()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public function create_menu(string $action, int $menu): array
    {
        return $this->_create_menu_internal($action, $menu);
    }

    /**
     * @brief   Метод формирует массив данных для меню в зависимости от типа и активного пункта.
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Проверяет корректность входных параметров:
     *             - Параметр $menu должен быть равен одной из констант: SHORT_MENU или LONG_MENU.
     *               Если значение некорректно, выбрасывается исключение InvalidArgumentException.
     *          2. Определяет тип меню с помощью конструкции match():
     *             - SHORT_MENU: Краткое горизонтальное меню.
     *             - LONG_MENU: Полное вертикальное меню.
     *          3. Выполняет запрос к базе данных для получения данных меню из таблицы TBL_MENU.
     *             - Условие выборки зависит от типа меню (столбец 'short' или 'long').
     *             - Параметры запроса используются для обеспечения безопасности.
     *          4. Для каждого пункта меню проверяются права доступа текущего пользователя:
     *             - Поле 'user_login': 0 - только гостям, 1 - только зарегистрированным.
     *             - Поле 'user_access': NULL - всем, иначе проверяется значение в $this->user->user.
     *          5. Если пункт меню видим, он добавляется в результат:
     *             - URL формируется на основе активного пункта меню ($action).
     *             - Название пункта локализуется через $this->lang['menu'] или используется дефолтное значение.
     *             - Данные очищаются с помощью Work::clean_field().
     *          Метод является защищённым и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод create_menu().
     *
     * @callergraph
     * @callgraph
     *
     * @param string $action Активный пункт меню.
     *                       Указывается строка, соответствующая активному пункту меню (например, 'home', 'profile').
     * @param int    $menu   Тип меню:
     *                       - SHORT_MENU (0): Краткое горизонтальное меню.
     *                       - LONG_MENU (1): Полное вертикальное меню.
     *                       Другие значения недопустимы и приведут к выбросу исключения InvalidArgumentException.
     *
     * @return array Массив с данными для меню.
     *               Каждый элемент массива содержит:
     *               - Ключ 'url': URL пункта меню (null, если пункт активен).
     *               - Ключ 'name': Название пункта меню (локализованное через $this->lang['menu'] или дефолтное
     *                 значение). Если меню пустое, возвращается пустой массив.
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
     *          Также убедитесь, что права доступа пользователя ($this->user) настроены правильно.
     *
     * Пример использования:
     * @code
     * // Пример использования метода _create_menu_internal()
     * $short_menu = $this->_create_menu_internal('home', SHORT_MENU); // Создание горизонтального меню
     * print_r($short_menu);
     *
     * $long_menu = $this->_create_menu_internal('profile', LONG_MENU); // Создание вертикального меню
     * print_r($long_menu);
     * @endcode
     * @see    PhotoRigma::Classes::Work_Template::$db
     *         Свойство, содержащее объект базы данных.
     * @see    PhotoRigma::Classes::Work_Template::$lang
     *         Свойство, содержащее языковые строки.
     * @see    PhotoRigma::Classes::Work_Template::$user
     *         Свойство, содержащее данные текущего пользователя.
     * @see    PhotoRigma::Classes::Work::clean_field()
     *         Статический метод для очистки данных.
     * @see    PhotoRigma::Classes::Work_Template::create_menu()
     *         Публичный метод, который вызывает этот внутренний метод.
     */
    protected function _create_menu_internal(string $action, int $menu): array
    {
        // Валидация входных данных.
        if (!in_array($menu, [SHORT_MENU, LONG_MENU], true)) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный тип меню (\$menu)"
            );
        }
        // Определение типа меню через match() (поддерживается начиная с PHP 8.0)
        $menu_type = match ($menu) {
            SHORT_MENU => 'short',
            LONG_MENU  => 'long',
            default    => throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный тип меню (\$menu)"
            ),
        };
        // Формирование запроса для получения данных меню (используем параметры для безопасности)
        // Условие ":menu_type = 1" используется для выборки по имени столбца (смена имени столбца для выборки).
        $this->db->select(
            '*', // Все поля таблицы используются для этого запроса
            TBL_MENU,
            [
                'where'  => '`' . $menu_type . '` = :menu_type',
                'params' => [':menu_type' => 1],
                'order'  => '`id` ASC',
            ]
        );
        // Получение результатов запроса
        $menu_data = $this->db->result_array();
        if ($menu_data === false) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Не удалось получить данные из базы данных'
            );
        }

        $menu_items = [];
        foreach ($menu_data as $key => $menu_item) {
            $is_visible = true;

            // Проверяем права доступа для текущего пользователя
            if ($menu_item['user_login'] !== null) {
                // user_login: 0 - только гостям, 1 - только зарегистрированным
                if (($menu_item['user_login'] === 0 && $this->user->user['id'] > 0) || // Гость пытается получить доступ к пункту для зарегистрированных
                    ($menu_item['user_login'] === 1 && $this->user->user['id'] === 0)  // Зарегистрированный пытается получить доступ к пункту для гостей
                ) {
                    $is_visible = false;
                }
            }

            // user_access: NULL - всем, иначе проверяем значение в $this->user->user
            if (($menu_item['user_access'] !== null) && empty($this->user->user[$menu_item['user_access']])) {
                $is_visible = false;
            }
            // Если пункт меню видим, добавляем его в результат
            if ($is_visible) {
                // Очищаем данные с использованием Work::clean_field() (статический метод с расширенными проверками)
                $url_action = Work::clean_field($menu_item['url_action']);
                $name_action = Work::clean_field($menu_item['name_action']);
                $menu_items[$key] = [
                    'url'  => ($menu_item['action'] === $action ? null : $this->config['site_url'] . $url_action),
                    'name' => $this->lang['menu'][$name_action] ?? ucfirst($name_action),
                ];
            }
        }
        return $menu_items;
    }

    /**
     * @brief   Установка языковых данных через сеттер.
     *
     * @details Этот метод позволяет установить массив языковых данных для использования в системе. Присваивает
     *          массив свойству текущего класса для дальнейшего использования.
     *
     * @callergraph
     *
     * @param array $lang Языковые данные:
     *                    - Должен быть ассоциативным массивом.
     *                    - Каждый ключ должен быть строкой, представляющей собой уникальный идентификатор языковой
     *                      переменной.
     *                    - Каждое значение должно быть строкой или другим допустимым типом данных для языковых
     *                      значений.
     *
     * @return void Метод ничего не возвращает.
     *
     * @note    Метод проверяет тип переданных данных.
     *          Языковые данные используются для локализации интерфейса и других текстовых элементов системы.
     *
     * @warning Передавайте только корректные языковые данные. Пустой массив или некорректные значения могут привести к
     *          ошибкам при использовании.
     *
     * Пример использования:
     * @code
     * // Создание объекта Work_Template и установка языковых данных
     * $w_template = new \PhotoRigma\Classes\Work_Template($config, $db, $work);
     * $langData = [
     *     'welcome_message' => 'Добро пожаловать!',
     *     'error_message'   => 'Произошла ошибка.',
     * ];
     * $w_template->set_lang($langData);
     * @endcode
     * @see    PhotoRigma::Classes::Work::set_lang()
     *         Метод в родительском классе Work, который вызывает этот метод.
     */
    public function set_lang(array $lang): void
    {
        $this->lang = $lang;
    }

    /**
     * @brief   Установка объекта, реализующего интерфейс User_Interface, через сеттер.
     *
     * @details Этот метод позволяет установить объект пользователя, реализующий интерфейс `User_Interface`.
     *          Присваивает объект свойству текущего класса для дальнейшего использования.
     *
     * @callergraph
     *
     * @param User_Interface $user Объект, реализующий интерфейс `User_Interface`:
     *                             - Должен быть экземпляром класса, реализующего интерфейс `User_Interface`.
     *
     * @return void Метод ничего не возвращает.
     *
     * @note    Метод проверяет тип переданного объекта.
     *          Объект пользователя используется для взаимодействия с другими компонентами системы.
     *
     * @warning Некорректный объект (не реализует интерфейс `User_Interface`) вызывает исключение.
     *
     * Пример использования:
     * @code
     * // Создание объекта Work_Template и установка объекта пользователя
     * $template = new \PhotoRigma\Classes\Work_Template($config, $db, $work);
     * $user = new \PhotoRigma\Classes\User(); // Класс, реализующий User_Interface
     * $template->set_user($user);
     * @endcode
     * @see    PhotoRigma::Classes::User_Interface
     *         Интерфейс, которому должен соответствовать объект пользователя.
     */
    public function set_user(User_Interface $user): void
    {
        $this->user = $user;
    }

    /**
     * @brief   Формирует список пользователей, загрузивших наибольшее количество изображений, через вызов внутреннего
     *          метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _template_best_user_internal().
     *          Он выполняет запрос к базе данных для получения списка пользователей, которые загрузили наибольшее
     *          количество фотографий. Результат формируется в виде ассоциативного массива для отображения в шаблоне.
     *          Метод также доступен через метод-фасад `template_best_user()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @param int $best_user Количество лучших пользователей для вывода:
     *                       - Должно быть положительным целым числом.
     *                       Пример: 5.
     *                       Если передано недопустимое значение, выбрасывается исключение InvalidArgumentException.
     *
     * @return array Массив данных для вывода в шаблон:
     *               - NAME_BLOCK:   Название блока (локализованное через $this->lang['main']['best_user']).
     *               - L_USER_NAME:  Подпись для имени пользователя (локализованная строка).
     *               - L_USER_PHOTO: Подпись для количества фотографий (локализованная строка).
     *               - user_url:     Ссылка на профиль пользователя (null, если данных нет).
     *               - user_name:    Имя пользователя ('---', если данных нет).
     *               - user_photo:   Количество загруженных фотографий ('-', если данных нет).
     *               - banned:       Булево значение, указывающее, находится ли пользователь в бане.
     *
     * @throws InvalidArgumentException Если параметр $best_user не является положительным целым числом.
     * @throws Exception                При выполнении запросов к базам данных.
     *
     * @note    Если запрос к базе данных не возвращает данных, добавляется запись "пустого" пользователя:
     *          - user_url: null.
     *          - user_name: '---'.
     *          - user_photo: '-'.
     *          - banned: false.
     *
     * @warning Убедитесь, что таблицы базы данных (TBL_USERS, TBL_PHOTO, TBL_USERS_BANS)
     *          содержат корректные данные. Ошибки в структуре таблиц могут привести к некорректному результату.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Template
     * $template = new \PhotoRigma\Classes\Work_Template();
     *
     * // Получение списка лучших пользователей
     * $best_users = $template->template_best_user(3);
     * print_r($best_users);
     * @endcode
     * @see    PhotoRigma::Classes::Work_Template::_template_best_user_internal()
     *         Защищённый метод, выполняющий основную логику.
     * @see    PhotoRigma::Classes::Work::template_best_user()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public function template_best_user(int $best_user = 1): array
    {
        return $this->_template_best_user_internal($best_user);
    }

    /**
     * @brief   Формирует список пользователей, загрузивших наибольшее количество изображений.
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Выполняет запрос к базе данных с использованием JOIN для объединения таблиц TBL_USERS и TBL_PHOTO:
     *             - Основная таблица: TBL_USERS (пользователи).
     *             - Присоединяемая таблица: TBL_PHOTO (фотографии).
     *             - Условие JOIN: связь между ID пользователя (TBL_USERS.id) и полем user_upload
     *               (TBL_PHOTO.user_upload).
     *          2. Дополнительно присоединяет таблицу TBL_USERS_BANS для определения заблокированных пользователей.
     *          3. Группирует данные по ID пользователя (GROUP BY TBL_USERS.id).
     *          4. Сортирует результаты по количеству фотографий в порядке убывания (ORDER BY user_photo DESC).
     *          5. Ограничивает количество результатов параметром $best_user (LIMIT).
     *          6. Обрабатывает полученные данные:
     *             - Формирует ссылки на профиль пользователя.
     *             - Очищает имена пользователей с помощью Work::clean_field().
     *             - Преобразует количество фотографий в целочисленный формат.
     *             - Помечает пользователей в бане через булево поле `banned`.
     *          7. Если данные отсутствуют, добавляется запись "пустого" пользователя:
     *             - user_url:   null.
     *             - user_name:  '---'.
     *             - user_photo: '-'.
     *             - banned:     false.
     *          8. Формирует метаданные для шаблона:
     *             - NAME_BLOCK:   Название блока (локализованное через $this->lang['main']['best_user']).
     *             - L_USER_NAME:  Подпись для имени пользователя (локализованная строка).
     *             - L_USER_PHOTO: Подпись для количества фотографий (локализованная строка).
     *          Метод является защищённым и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод template_best_user().
     *
     * @callergraph
     * @callgraph
     *
     * @param int $best_user Количество лучших пользователей для вывода.
     *                       Должно быть положительным целым числом.
     *                       Если передано недопустимое значение, выбрасывается исключение InvalidArgumentException.
     *
     * @return array Массив данных для вывода в шаблон:
     *               - NAME_BLOCK:   Название блока (локализованное через $this->lang['main']['best_user']).
     *               - L_USER_NAME:  Подпись для имени пользователя (локализованная строка).
     *               - L_USER_PHOTO: Подпись для количества фотографий (локализованная строка).
     *               - user_url:     Ссылка на профиль пользователя (null, если данных нет).
     *               - user_name:    Имя пользователя ('---', если данных нет).
     *               - user_photo:   Количество загруженных фотографий ('-', если данных нет).
     *               - banned:       Булево значение, указывающее, находится ли пользователь в бане.
     *
     * @throws InvalidArgumentException Если параметр $best_user не является положительным целым числом.
     * @throws Exception                При выполнении запросов к базам данных.
     *
     * @note    Если запрос к базе данных не возвращает данных, добавляется запись "пустого" пользователя:
     *          - user_url:   null.
     *          - user_name:  '---'.
     *          - user_photo: '-'.
     *          - banned:     false.
     *          Константы, используемые в методе:
     *          - TBL_USERS: Таблица пользователей с их правами.
     *          - TBL_PHOTO: Таблица со списком фотографий на сервере.
     *          - TBL_USERS_BANS: Таблица с информацией о заблокированных пользователях.
     *          Все пользователи учитываются при условии:
     *          - activation = 1 (активация)
     *          - email_confirmed = 1 (подтверждение почты)
     *          - deleted_at IS NULL AND permanently_deleted = 0 (не удалённые)
     *
     * @warning Убедитесь, что таблицы базы данных (TBL_USERS, TBL_PHOTO, TBL_USERS_BANS)
     *          содержат корректные данные. Ошибки в структуре таблиц могут привести к некорректному результату.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода внутри класса
     * $best_users = $this->_template_best_user_internal(3);
     * print_r($best_users);
     * @endcode
     * @see    PhotoRigma::Classes::Work::clean_field()
     *         Метод для очистки данных.
     * @see    PhotoRigma::Classes::Work_Template::$lang
     *         Свойство, содержащее языковые строки.
     * @see    PhotoRigma::Classes::Work_Template::$db
     *         Свойство, содержащее объект для работы с базой данных.
     * @see    PhotoRigma::Classes::Work_Template::template_best_user()
     *         Публичный метод, который вызывает этот внутренний метод.
     */
    protected function _template_best_user_internal(int $best_user = 1): array
    {
        // Блок получения данных через join()
        $this->db->join(
            [
                TBL_USERS . '.id',
                TBL_USERS . '.real_name',
                'COUNT(' . TBL_PHOTO . '.id) AS user_photo',
                // Универсальная логика для banned
                'COUNT(b.user_id) > 0 AS banned'
            ],
            TBL_USERS,
            [
                [
                    'type' => 'INNER',
                    'table' => TBL_PHOTO,
                    'on' => TBL_USERS . '.id = ' . TBL_PHOTO . '.user_upload'
                ],
                [
                    'type' => 'LEFT',
                    'table' => TBL_USERS_BANS . ' b',
                    'on' => TBL_USERS . '.id = b.user_id AND b.banned = 1'
                ]
            ],
            [
                'where' => '
                    activation = :activated 
                    AND email_confirmed = :confirmed 
                    AND deleted_at IS NULL 
                    AND permanently_deleted = :permanently_deleted',
                'group' => TBL_USERS . '.id',
                'order' => 'user_photo DESC',
                'limit' => $best_user,
                'params' => [
                        ':activated' => 1,
                        ':confirmed' => 1,
                        ':permanently_deleted' => 0,
                ],
            ]
        );
        $user_data = $this->db->result_array();
        // Проверка: $user_data может быть массивом или false
        $array_data = ($user_data !== false) ? array_map(function ($current_user) {
            return [
                'user_url'   => sprintf(
                    '%s?action=profile&amp;subact=profile&amp;uid=%d',
                    $this->config['site_url'],
                    $current_user['id']
                ),
                'user_name'  => Work::clean_field($current_user['real_name']),
                'user_photo' => (int)$current_user['user_photo'],
                'banned'     => !empty($current_user['banned']) // универсально под все СУБД
            ];
        }, $user_data) : [];

        // Добавление данных для "пустого" пользователя
        if (empty($array_data)) {
            $array_data[1] = [
                'user_url'   => '',
                'user_name'  => '---',
                'user_photo' => '-',
                'banned'     => false
            ];
        }

        // Блок формирования метаданных для шаблона
        $top_data = [
            'NAME_BLOCK'   => sprintf($this->lang['main']['best_user'], $best_user),
            'L_USER_NAME'  => $this->lang['main']['user_name'],
            'L_USER_PHOTO' => $this->lang['main']['best_user_photo'],
        ];
        array_unshift($array_data, $top_data);

        return $array_data;
    }

    /**
     * @brief   Генерирует массив статистических данных для шаблона через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _template_stat_internal().
     *          Он выполняет запросы к базе данных для получения статистической информации о:
     *          - Зарегистрированных пользователях (с учётом активации, подтверждения email и мягкого удаления)
     *          - Категориях и пользовательских альбомах
     *          - Фотографиях
     *          - Оценках от пользователей и модераторов
     *          - Онлайн-пользователях (включая метку бана и перечёркнутое имя для заблокированных)
     *
     *          Результат формируется в виде ассоциативного массива для отображения статистики на странице.
     *          Метод также доступен через метод-фасад `template_stat()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @return array Ассоциативный массив данных для вывода статистики:
     *               - NAME_BLOCK:        Название блока статистики (локализованное).
     *               - L_STAT_REGIST:     Подпись для количества зарегистрированных пользователей.
     *               - D_STAT_REGIST:     Количество зарегистрированных пользователей.
     *               - L_STAT_PHOTO:      Подпись для количества фотографий.
     *               - D_STAT_PHOTO:      Количество фотографий.
     *               - L_STAT_CATEGORY:   Подпись для количества категорий.
     *               - D_STAT_CATEGORY:   Количество категорий (включая пользовательские альбомы).
     *               - L_STAT_USER_ADMIN: Подпись для количества администраторов.
     *               - D_STAT_USER_ADMIN: Количество администраторов.
     *               - L_STAT_USER_MODER: Подпись для количества модераторов.
     *               - D_STAT_USER_MODER: Количество модераторов.
     *               - L_STAT_RATE_USER:  Подпись для количества пользовательских оценок.
     *               - D_STAT_RATE_USER:  Количество пользовательских оценок.
     *               - L_STAT_RATE_MODER: Подпись для количества модераторских оценок.
     *               - D_STAT_RATE_MODER: Количество модераторских оценок.
     *               - L_STAT_ONLINE:     Подпись для онлайн-пользователей.
     *               - D_STAT_ONLINE:     Список онлайн-пользователей (HTML-ссылки) или сообщение об отсутствии
     *                                    онлайн-пользователей. Пользователи в бане дополнительно выделяются:
     *                                    - Иконкой `<i class="ban icon"></i>`
     *                                    - Перечёркнутым именем `<s>...</s>`.
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
     *          Также используются глобальные константы групп:
     *          - GROUP_ADMIN: ID группы администраторов.
     *          - GROUP_MODER: ID группы модераторов.
     *
     * @warning Убедитесь, что таблицы базы данных (TBL_USERS, TBL_CATEGORY, TBL_PHOTO, TBL_RATE_USER, TBL_RATE_MODER)
     *          и представление VIEW_USERS_ONLINE содержат корректные данные. Ошибки в структуре таблиц могут привести
     *          к некорректной статистике.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          Это может привести к неожидаемому результату.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Template
     * $template = new \PhotoRigma\Classes\Work_Template();
     *
     * // Получение статистических данных
     * $stat_data = $template->template_stat();
     * print_r($stat_data);
     * @endcode
     * @see    PhotoRigma::Classes::Work_Template::_template_stat_internal()
     *         Защищённый метод, выполняющий основную логику.
     * @see    PhotoRigma::Classes::Work::template_stat()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public function template_stat(): array
    {
        return $this->_template_stat_internal();
    }

    /**
     * @brief   Генерирует массив статистических данных для шаблона.
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Выполняет запросы к базе данных для получения статистической информации:
     *             - Количество зарегистрированных пользователей, администраторов и модераторов из TBL_USERS.
     *               Учитываются только:
     *               - Пользователи с активацией (`activation = 1`)
     *               - С подтверждённой почтой (`email_confirmed = 1`)
     *               - Не удалённые (`deleted_at IS NULL AND permanently_deleted = 0`)
     *             - Количество категорий (включая пользовательские альбомы) из TBL_CATEGORY и TBL_PHOTO.
     *             - Количество фотографий из TBL_PHOTO.
     *             - Количество пользовательских и модераторских оценок из TBL_RATE_USER и TBL_RATE_MODER.
     *             - Список онлайн-пользователей из VIEW_USERS_ONLINE, соответствующих тем же условиям,
     *               что и основная выборка.
     *          2. Обрабатывает результаты запросов:
     *             - Преобразует данные в целочисленные значения.
     *             - Для онлайн-пользователей формирует HTML-ссылки с очисткой данных через Work::clean_field().
     *             - Пользователи в бане дополнительно обозначаются иконкой и перечёркнутым именем.
     *          3. Возвращает ассоциативный массив с локализованными подписями и данными для вывода статистики.
     *          Метод является защищённым и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод template_stat().
     *
     * @callergraph
     * @callgraph
     *
     * @return array Ассоциативный массив данных для вывода статистики:
     *               - NAME_BLOCK:        Название блока статистики (локализованное).
     *               - L_STAT_REGIST:     Подпись для количества зарегистрированных пользователей.
     *               - D_STAT_REGIST:     Количество зарегистрированных пользователей.
     *               - L_STAT_PHOTO:      Подпись для количества фотографий.
     *               - D_STAT_PHOTO:      Количество фотографий.
     *               - L_STAT_CATEGORY:   Подпись для количества категорий.
     *               - D_STAT_CATEGORY:   Количество категорий (включая пользовательские альбомы).
     *               - L_STAT_USER_ADMIN: Подпись для количества администраторов.
     *               - D_STAT_USER_ADMIN: Количество администраторов.
     *               - L_STAT_USER_MODER: Подпись для количества модераторов.
     *               - D_STAT_USER_MODER: Количество модераторов.
     *               - L_STAT_RATE_USER:  Подпись для количества пользовательских оценок.
     *               - D_STAT_RATE_USER:  Количество пользовательских оценок.
     *               - L_STAT_RATE_MODER: Подпись для количества модераторских оценок.
     *               - D_STAT_RATE_MODER: Количество модераторских оценок.
     *               - L_STAT_ONLINE:     Подпись для онлайн-пользователей.
     *               - D_STAT_ONLINE:     Список онлайн-пользователей (HTML-ссылки) или сообщение об отсутствии
     *                                    онлайн-пользователей. Пользователи в бане выделяются:
     *                                    - Иконкой `<i class="ban icon"></i>`
     *                                    - Перечёркнутым именем `<s>...</s>`.
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
     *          Также используются глобальные константы групп:
     *          - GROUP_ADMIN: ID группы администраторов
     *          - GROUP_MODER: ID группы модераторов
     *
     * @warning Убедитесь, что таблицы базы данных (TBL_USERS, TBL_CATEGORY, TBL_PHOTO, TBL_RATE_USER, TBL_RATE_MODER)
     *          и представление VIEW_USERS_ONLINE содержат корректные данные. Ошибки в структуре таблиц могут привести
     *          к некорректной статистике.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода внутри класса
     * $stat_data = $this->_template_stat_internal();
     * print_r($stat_data);
     * @endcode
     * @see    PhotoRigma::Classes::Work_Template::$db
     *         Свойство, содержащее объект для работы с базой данных.
     * @see    PhotoRigma::Classes::Work::clean_field()
     *         Метод для очистки данных.
     * @see    PhotoRigma::Classes::Work_Template::template_stat()
     *         Публичный метод, который вызывает этот внутренний метод.
     * @see    PhotoRigma::Classes::Work_Template::$lang
     *         Свойство, содержащее языковые строки.
     */
    protected function _template_stat_internal(): array
    {
        $stat = [];
        // Получение статистики пользователей
        $this->db->select(
            [
                'COUNT(*) AS `regist_user`',
                'SUM(CASE WHEN `group_id` = ' . GROUP_ADMIN . ' THEN 1 ELSE 0 END) AS `user_admin`',
                'SUM(CASE WHEN `group_id` = ' . GROUP_MODER . ' THEN 1 ELSE 0 END) AS `user_moder`'
            ],
            TBL_USERS,
            [
                'where' => '`activation` = :activated AND `email_confirmed` = :confirmed 
                    AND `deleted_at` IS NULL AND `permanently_deleted` = :permanently_deleted',
                'params' => [
                    ':activated' => 1,
                    ':confirmed' => 1,
                    ':permanently_deleted' => 0
                ]
            ]
        );
        $user_stats = $this->db->result_row();
        $stat['regist'] = $user_stats ? (int)$user_stats['regist_user'] : 0;
        $stat['user_admin'] = $user_stats ? (int)$user_stats['user_admin'] : 0;
        $stat['user_moder'] = $user_stats ? (int)$user_stats['user_moder'] : 0;
        // Получение статистики категорий
        $this->db->join(
            [
                'COUNT(DISTINCT c.`id`) AS `category`',
                'COUNT(DISTINCT CASE WHEN p.`category` = 0 THEN p.`user_upload` END) AS `category_user`',
            ],
            TBL_CATEGORY . ' AS c', // Основная таблица (category)
            [
                [
                    'type'  => 'LEFT', // Тип JOIN
                    'table' => TBL_PHOTO . ' AS p', // Присоединяемая таблица (photo)
                    'on'    => 'c.`id` = p.`category`', // Условие JOIN
                ],
            ],
            [
                'where' => 'c.`id` != :id', // Условие WHERE
                'params' => [
                    ':id' => 0
                ]
            ]
        );
        $category_stats = $this->db->result_row();

        // Обработка результатов
        $stat['category'] = $category_stats ? (int)$category_stats['category'] : 0;
        $stat['category_user'] = $category_stats ? (int)$category_stats['category_user'] : 0;
        $stat['category'] += $stat['category_user'];
        // Получение статистики фотографий
        $this->db->select(['COUNT(*) AS `photo_count`'], TBL_PHOTO);
        $photo_stats = $this->db->result_row();
        $stat['photo'] = $photo_stats ? (int)$photo_stats['photo_count'] : 0;
        // Получение статистики оценок
        $this->db->select(['COUNT(*) AS `rate_user`'], TBL_RATE_USER);
        $rate_user_stats = $this->db->result_row();
        $stat['rate_user'] = $rate_user_stats ? (int)$rate_user_stats['rate_user'] : 0;
        $this->db->select(['COUNT(*) AS `rate_moder`'], TBL_RATE_MODER);
        $rate_moder_stats = $this->db->result_row();
        $stat['rate_moder'] = $rate_moder_stats ? (int)$rate_moder_stats['rate_moder'] : 0;
        // Получение онлайн-пользователей
        $this->db->select(
            '*',
            VIEW_USERS_ONLINE
        );
        $online_users_data = $this->db->result_array();
        $stat['online'] = $online_users_data ? implode(', ', array_map(function ($user) {
            // Чистим имя
            $name = Work::clean_field($user['real_name']);

            // Формируем ссылку
            /** @noinspection HtmlUnknownTarget */
            return sprintf(
                '<a href="%s?action=profile&amp;subact=profile&amp;uid=%d" style="white-space: nowrap;" title="%s">%s%s</a>',
                $this->config['site_url'],
                $user['id'],
                $name,
                (!empty($user['banned']) ? '<s><i class="ban icon"></i>' : ''),
                $name . (!empty($user['banned']) ? '</s>' : '')
            );
        }, $online_users_data)) . '.' : $this->lang['main']['stat_no_online'];
        // Формирование результирующего массива
        return [
            'NAME_BLOCK'        => $this->lang['main']['stat_title'],
            'L_STAT_REGIST'     => $this->lang['main']['stat_regist'],
            'D_STAT_REGIST'     => (string)$stat['regist'],
            'L_STAT_PHOTO'      => $this->lang['main']['stat_photo'],
            'D_STAT_PHOTO'      => (string)$stat['photo'],
            'L_STAT_CATEGORY'   => $this->lang['main']['stat_category'],
            'D_STAT_CATEGORY'   => $stat['category'] . '(' . $stat['category_user'] . ')',
            'L_STAT_USER_ADMIN' => $this->lang['main']['stat_user_admin'],
            'D_STAT_USER_ADMIN' => (string)$stat['user_admin'],
            'L_STAT_USER_MODER' => $this->lang['main']['stat_user_moder'],
            'D_STAT_USER_MODER' => (string)$stat['user_moder'],
            'L_STAT_RATE_USER'  => $this->lang['main']['stat_rate_user'],
            'D_STAT_RATE_USER'  => (string)$stat['rate_user'],
            'L_STAT_RATE_MODER' => $this->lang['main']['stat_rate_moder'],
            'D_STAT_RATE_MODER' => (string)$stat['rate_moder'],
            'L_STAT_ONLINE'     => $this->lang['main']['stat_online'],
            'D_STAT_ONLINE'     => $stat['online'],
        ];
    }

    /**
     * @brief   Формирует блок пользователя через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _template_user_internal().
     *          Он формирует массив данных для блока пользователя в зависимости от статуса авторизации:
     *          - Для неавторизованных пользователей: ссылки на вход, восстановление пароля и регистрацию.
     *          - Для авторизованных пользователей: приветствие, группа и аватар (или дефолтный аватар, если файл
     *            недоступен). Метод также доступен через метод-фасад `template_user()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @return array Массив с данными для блока пользователя:
     *               - Для неавторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока (локализованное через $this->lang['main']['user_block']).
     *                 - 'CSRF_TOKEN': CSRF-токен для защиты формы.
     *                 - 'L_LOGIN', 'L_PASSWORD', 'L_ENTER', 'L_FORGOT_PASSWORD', 'L_REGISTRATION': Локализованные
     *                   строки.
     *                 - 'U_LOGIN', 'U_FORGOT_PASSWORD', 'U_REGISTRATION': URL для входа, восстановления пароля и
     *                   регистрации.
     *               - Для авторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока (локализованное через $this->lang['main']['user_block']).
     *                 - 'L_HI_USER': Приветствие с именем пользователя (локализованное через
     *                   $this->lang['main']['hi_user']).
     *                 - 'L_GROUP': Группа пользователя (локализованная строка).
     *                 - 'U_AVATAR': URL аватара (или дефолтного аватара, если файл недоступен или некорректен).
     *
     * @throws RuntimeException Если объект пользователя не установлен или данные некорректны.
     * @throws Random\RandomException При ошибке генерации CSRF-токена.
     * @throws Exception При ошибках проверки MIME-типа файла или логирования.
     *
     * @note    Статус авторизации проверяется через $this->user->session['login_id'].
     *          Константа DEFAULT_AVATAR определяет значение аватара по умолчанию (например, 'no_avatar.jpg').
     *
     * @warning Убедитесь, что объект пользователя ($this->user) корректно установлен перед вызовом метода.
     *          Также убедитесь, что конфигурация аватаров ($this->config['avatar_folder']) настроена правильно.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Template
     * $template = new \PhotoRigma\Classes\Work_Template();
     *
     * // Получение данных для блока пользователя
     * $user_block = $template->template_user();
     * print_r($user_block);
     * @endcode
     * @see    PhotoRigma::Classes::Work_Template::_template_user_internal()
     *         Защищённый метод, выполняющий основную логику.
     * @see    PhotoRigma::Classes::Work::template_user()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public function template_user(): array
    {
        return $this->_template_user_internal();
    }

    /**
     * @brief   Формирование блока пользователя.
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Проверяет статус авторизации пользователя через $this->user->session['login_id']:
     *             - Если пользователь не авторизован (login_id == 0), формируется блок со ссылками на вход,
     *               восстановление пароля и регистрацию.
     *             - Если пользователь авторизован, формируется блок с приветствием, группой и аватаром.
     *          2. Для авторизованных пользователей проверяется существование аватара и его MIME-тип:
     *             - Если аватар недоступен или имеет недопустимый MIME-тип, используется дефолтный аватар
     *               (константа DEFAULT_AVATAR).
     *          3. Возвращает массив данных для блока пользователя, который используется для отображения в шаблоне.
     *          Метод является защищённым и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод template_user().
     *
     * @callergraph
     * @callgraph
     *
     * @return array Массив с данными для блока пользователя:
     *               - Для неавторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока (локализованное через $this->lang['main']['user_block']).
     *                 - 'CSRF_TOKEN': CSRF-токен для защиты формы.
     *                 - 'L_LOGIN', 'L_PASSWORD', 'L_ENTER', 'L_FORGOT_PASSWORD', 'L_REGISTRATION': Локализованные
     *                   строки.
     *                 - 'U_LOGIN', 'U_FORGOT_PASSWORD', 'U_REGISTRATION': URL для входа, восстановления пароля и
     *                   регистрации.
     *               - Для авторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока (локализованное через $this->lang['main']['user_block']).
     *                 - 'L_HI_USER': Приветствие с именем пользователя (локализованное через
     *                   $this->lang['main']['hi_user']).
     *                 - 'L_GROUP': Группа пользователя (локализованная строка).
     *                 - 'U_AVATAR': URL аватара (или дефолтного аватара, если файл недоступен или некорректен).
     *
     * @throws RuntimeException Если объект пользователя не установлен или данные некорректны.
     * @throws Random\RandomException При ошибке генерации CSRF-токена.
     * @throws Exception При ошибках проверки MIME-типа файла или логирования.
     *
     * @note    Статус авторизации проверяется через $this->user->session['login_id'].
     *          Константа DEFAULT_AVATAR определяет значение аватара по умолчанию (например, 'no_avatar.jpg').
     *
     * @warning Убедитесь, что объект пользователя ($this->user) корректно установлен перед вызовом метода.
     *          Также убедитесь, что конфигурация аватаров ($this->config['avatar_folder']) настроена правильно.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода внутри класса
     * $array_data = $this->_template_user_internal();
     * print_r($array_data);
     * @endcode
     * @see    PhotoRigma::Classes::Work_Template::$user
     *         Свойство, содержащее данные текущего пользователя.
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования ошибок.
     * @see    PhotoRigma::Classes::Work::clean_field()
     *         Метод для очистки данных.
     * @see    PhotoRigma::Classes::Work::validate_mime_type()
     *         Метод для проверки MIME-типа файла.
     * @see    PhotoRigma::Classes::Work_Template::template_user()
     *         Публичный метод, который вызывает этот внутренний метод.
     * @see    PhotoRigma::Classes::Work_Template::$lang
     *         Свойство, содержащее языковые строки.
     */
    protected function _template_user_internal(): array
    {
        // Проверка, что объект пользователя установлен
        if ($this->user === null) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Объект пользователя не установлен | Решение: используйте set_user() для внедрения зависимости'
            );
        }
        // Определение пути к аватару
        $avatar_path = sprintf(
            '%s/%s',
            $this->config['avatar_folder'],
            $this->user->user['avatar'] ?? DEFAULT_AVATAR
        );
        // Проверка существования файла и его MIME-типа
        if ($this->user->session['login_id'] > 0) {
            $full_avatar_path = $this->config['site_dir'] . '/' . $avatar_path;
            if (!file_exists($full_avatar_path)) {
                $avatar_path = sprintf('%s/%s', $this->config['avatar_folder'], DEFAULT_AVATAR);
            } else {
                $mime_type = new finfo(FILEINFO_MIME_TYPE)->file($full_avatar_path);
                if (!Work::validate_mime_type($mime_type)) {
                    log_in_file(
                        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый MIME-тип аватара | Файл: $full_avatar_path"
                    );
                    $avatar_path = sprintf('%s/%s', $this->config['avatar_folder'], DEFAULT_AVATAR);
                }
            }
        }
        // Проверка статуса авторизации через match
        return match ($this->user->session['login_id'] ?? 0) {
            0       => [
                'NAME_BLOCK'        => $this->lang['main']['user_block'],
                'CSRF_TOKEN'        => $this->user->csrf_token(),
                'L_LOGIN'           => $this->lang['main']['login'],
                'L_PASSWORD'        => $this->lang['main']['pass'],
                'L_ENTER'           => $this->lang['main']['enter'],
                'L_FORGOT_PASSWORD' => $this->lang['main']['forgot_password'],
                'L_REGISTRATION'    => $this->lang['main']['registration'],
                'U_LOGIN'           => sprintf('%s?action=profile&amp;subact=login', $this->config['site_url']),
                'U_FORGOT_PASSWORD' => sprintf('%s?action=profile&amp;subact=forgot', $this->config['site_url']),
                'U_REGISTRATION'    => sprintf('%s?action=profile&amp;subact=regist', $this->config['site_url']),
            ],
            default => [
                'NAME_BLOCK' => $this->lang['main']['user_block'],
                'L_HI_USER'  => $this->lang['main']['hi_user'] . ', ' . Work::clean_field(
                    $this->user->user['real_name']
                ),
                'L_GROUP'    => $this->lang['main']['group'] . ': ' . $this->user->user['group_name'],
                'U_AVATAR'   => $this->config['site_url'] . $avatar_path,
            ],
        };
    }
}
