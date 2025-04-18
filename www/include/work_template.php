<?php

/**
 * @file        include/work_template.php
 * @brief       Файл содержит класс Work_Template, который отвечает за формирование данных для шаблонов.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-04-07
 * @namespace   PhotoRigma\\Classes
 *
 * @details     Этот файл содержит класс `Work_Template`, который реализует интерфейс `Work_Template_Interface`.
 *              Класс предоставляет методы для генерации данных, необходимых для отображения различных блоков на
 *              странице, таких как меню, блок пользователя, статистика и список лучших пользователей. Все данные
 *              формируются на основе запросов к базе данных и конфигурации. Реализация методов зависит от глобальных
 *              переменных, таких как $_SESSION, для проверки статуса авторизации.
 *
 * @see         PhotoRigma::Classes::Work_Template_Interface Интерфейс, который реализует данный класс.
 * @see         PhotoRigma::Classes::Work Класс, через который вызываются методы для работы с шаблонами.
 * @see         PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
 * @see         PhotoRigma::Classes::Work::validate_mime_type() Метод для проверки MIME-типа файла.
 * @see         index.php Файл, который подключает work_template.php.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в формировании данных для шаблонов.
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
use finfo;
use InvalidArgumentException;
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
 * @interface Work_Template_Interface
 * @brief     Интерфейс Work_Template_Interface определяет контракт для классов, формирующих данные для шаблонов.
 *
 * @details   Интерфейс предоставляет методы для генерации данных, необходимых для отображения различных блоков
 *          на странице, таких как меню, блок пользователя, статистика и список лучших пользователей. Реализующие классы
 *          должны обеспечивать выполнение всех методов согласно их описанию.
 *
 * @see       PhotoRigma::Classes::Work_Template Класс, реализующий данный интерфейс.
 * @see       PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
 * @see       PhotoRigma::Classes::Work::validate_mime_type() Метод для проверки MIME-типа файла.
 *
 * @note      Методы интерфейса зависят от данных из базы данных и конфигурации.
 *       Реализующие классы должны обрабатывать возможные ошибки при работе с БД.
 *
 * @warning   Убедитесь, что передаваемые параметры в методы интерфейса корректны, так как это может привести к ошибкам.
 *
 * Пример класса, реализующего интерфейс:
 * @code
 * class MyClass implements \PhotoRigma\Classes\Work_Template_Interface {
 * }
 * @endcode
 */
interface Work_Template_Interface
{
    /**
     * @brief   Метод формирует массив данных для меню в зависимости от типа и активного пункта.
     *
     * @details Метод формирует массив данных для меню на основе запроса к базе данных, учитывая тип меню и активный
     *          пункт. Поддерживаемые типы меню:
     *          - 0: Горизонтальное краткое меню.
     *          - 1: Вертикальное боковое меню.
     *          Для каждого пункта меню проверяются права доступа текущего пользователя (на основе свойств User).
     *          Если пункт меню видим, он добавляется в результат с очисткой данных через Work::clean_field().
     *
     * @callgraph
     *
     * @param string $action Активный пункт меню.
     *                       Указывается строка, соответствующая активному пункту меню (например, 'home', 'profile').
     * @param int    $menu   Тип меню:
     *                       - 0: Горизонтальное краткое меню.
     *                       - 1: Вертикальное боковое меню.
     *                       Другие значения недопустимы и приведут к выбросу исключения InvalidArgumentException.
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
     * @note    Данные для меню берутся из таблицы TBL_MENU.
     *       Для получения дополнительной информации см. структуру таблицы.
     *
     * @warning Убедитесь, что передаваемые параметры корректны, так как это может привести к ошибкам.
     *          Также убедитесь, что права доступа пользователя (User) настроены правильно.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Template
     * $template = new \PhotoRigma\Classes\Work_Template();
     *
     * // Создание горизонтального меню
     * $short_menu = $template->create_menu('home', 0);
     * print_r($short_menu);
     *
     * // Создание вертикального меню
     * $long_menu = $template->create_menu('profile', 1);
     * print_r($long_menu);
     * @endcode
     * @see     PhotoRigma::Classes::Work::clean_field()
     *      Статический метод для очистки данных.
     *
     * @see     PhotoRigma::Classes::Work_Template::create_menu()
     *      Публичный метод, который вызывает этот внутренний метод.
     */
    public function create_menu(string $action, int $menu): array;

    /**
     * @brief   Формирование блока пользователя.
     *
     * @details Метод формирует массив данных для блока пользователя в зависимости от статуса авторизации:
     *          - Если пользователь не авторизован, формируется блок с ссылками на вход, восстановление пароля и
     *          регистрацию.
     *          - Если пользователь авторизован, формируется блок с приветствием, группой и аватаром.
     *          Для авторизованных пользователей проверяется существование аватара и его MIME-тип через
     *          Work::validate_mime_type(). Если аватар недоступен или имеет недопустимый MIME-тип, используется
     *          дефолтный аватар (DEFAULT_AVATAR).
     *
     * @callgraph
     *
     * @return array Массив с данными для блока пользователя:
     *               - Для неавторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока.
     *                 - 'L_LOGIN', 'L_PASSWORD', 'L_ENTER', 'L_FORGOT_PASSWORD', 'L_REGISTRATION': Локализованные
     *                 строки.
     *                 - 'U_LOGIN', 'U_FORGOT_PASSWORD', 'U_REGISTRATION': URL для входа, восстановления пароля и
     *                 регистрации.
     *               - Для авторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока.
     *                 - 'L_HI_USER': Приветствие с именем пользователя.
     *                 - 'L_GROUP': Группа пользователя.
     *                 - 'U_AVATAR': URL аватара (или дефолтного аватара, если файл недоступен или некорректен).
     *
     * @throws RuntimeException Если объект пользователя не установлен или данные некорректны.
     *
     * @note    Используется глобальная переменная $_SESSION для проверки статуса авторизации.
     *
     * @warning Убедитесь, что объект пользователя (User) корректно установлен перед вызовом метода.
     *          Также убедитесь, что конфигурация аватаров ($work->config['avatar_folder']) настроена правильно.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Template
     * $template = new \PhotoRigma\Classes\Work_Template();
     *
     * // Получение данных для блока пользователя
     * $array_data = $template->template_user();
     * print_r($array_data);
     * @endcode
     * @see     PhotoRigma::Classes::Work::validate_mime_type()
     *      Метод для проверки MIME-типа файла.
     *
     * @see     PhotoRigma::Classes::Work_Template::template_user()
     *      Публичный метод, который вызывает этот внутренний метод.
     * @see     PhotoRigma::Classes::Work::clean_field()
     *      Метод для очистки данных.
     */
    public function template_user(): array;

    /**
     * @brief   Генерирует массив статистических данных для шаблона.
     *
     * @details Метод выполняет запросы к базе данных для получения статистической информации о пользователях,
     *          категориях, фотографиях, оценках и онлайн-пользователях. Результат формируется в виде ассоциативного
     *          массива, который используется для отображения статистики на странице. Этот метод вызывается через
     *          редирект из публичного метода.
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
     *               - D_STAT_ONLINE: Список онлайн-пользователей (HTML-ссылки) или сообщение об отсутствии
     *               онлайн-пользователей.
     *
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных.
     *
     * @warning Убедитесь, что таблицы базы данных (TBL_USERS, TBL_CATEGORY, TBL_PHOTO, TBL_RATE_USER, TBL_RATE_MODER)
     *          содержат корректные данные. Ошибки в структуре таблиц могут привести к некорректной статистике.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Template
     * $template = new \PhotoRigma\Classes\Work_Template();
     *
     * // Получение данных для статистики
     * $stat_data = $template->template_stat();
     * print_r($stat_data);
     * @endcode
     * @see     PhotoRigma::Classes::Work_Template::template_stat()
     *      Публичный метод, который вызывает этот внутренний метод.
     * @see     PhotoRigma::Classes::Work::clean_field()
     *      Метод для очистки данных.
     *
     */
    public function template_stat(): array;

    /**
     * @brief   Формирует список пользователей, загрузивших наибольшее количество изображений.
     *
     * @details Метод выполняет запрос к базе данных с использованием JOIN для получения списка пользователей,
     *          которые загрузили наибольшее количество фотографий. Результат формируется в виде ассоциативного
     *          массива,
     *          который используется для отображения в шаблоне. Если данные отсутствуют, добавляется запись "пустого"
     *          пользователя.
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
     * @note    Если запрос к базе данных не возвращает данных, добавляется запись "пустого" пользователя:
     *       - user_url: null
     *       - user_name: '---'
     *       - user_photo: '-'
     *
     * @warning Убедитесь, что таблицы базы данных (TBL_USERS и TBL_PHOTO) содержат корректные данные.
     *          Ошибки в структуре таблиц могут привести к некорректному результату.
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
     * @see     PhotoRigma::Classes::Work::clean_field()
     *      Метод для очистки данных.
     *
     * @see     PhotoRigma::Classes::Work_Template::template_best_user()
     *      Публичный метод, который вызывает этот внутренний метод.
     */
    public function template_best_user(int $best_user = 1): array;

    /**
     * @brief   Установка языковых данных через сеттер.
     *
     * @details Метод позволяет установить массив языковых данных.
     *          Если передан некорректный тип данных или `null`, выбрасывается исключение.
     *
     * @param array $lang Языковые данные:
     *                    - Должен быть массивом.
     *                    - Каждый ключ должен быть строкой, а значение — допустимым для языковых данных.
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws InvalidArgumentException Если передан некорректный тип данных или `null`.
     *
     * @note    Убедитесь, что передаваемые языковые данные корректны и соответствуют ожидаемому формату.
     * @warning Не передавайте пустые или некорректные данные, так как это может привести к ошибкам.
     *
     * Пример использования метода:
     * @code
     * $template = new \PhotoRigma\Classes\Work_Template($config, $db, $work);
     * $template->set_lang(['key' => 'value']);
     * @endcode
     * @see     PhotoRigma::Classes::Work::set_lang() Метод в родительском классе Work, который вызывает этот метод.
     *
     * @see     PhotoRigma::Classes::Work_Template::$lang Свойство, которое изменяет метод.
     */
    public function set_lang(array $lang): void;

    /**
     * @brief   Установка данных пользователя через сеттер.
     *
     * @details Метод позволяет установить объект пользователя.
     *          Если передан некорректный тип данных или `null`, выбрасывается исключение.
     *
     * @param User $user Объект пользователя:
     *                   - Должен быть экземпляром класса User.
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws InvalidArgumentException Если передан некорректный тип данных или `null`.
     *
     * @note    Убедитесь, что передаваемый объект пользователя является экземпляром класса User.
     * @warning Не передавайте null или некорректные объекты, так как это может привести к ошибкам.
     *
     * Пример использования метода:
     * @code
     * $template = new \PhotoRigma\Classes\Work_Template($config, $db, $work);
     * $user = new \PhotoRigma\Classes\User();
     * $template->set_user($user);
     * @endcode
     * @see     PhotoRigma::Classes::Work::set_user() Метод в родительском классе Work, который вызывает этот метод.
     *
     * @see     PhotoRigma::Classes::Work_Template::$user Свойство, которое изменяет метод.
     */
    public function set_user(User $user): void;
}

/**
 * @class   Work_Template
 * @brief   Класс Work_Template отвечает за формирование данных для шаблонов.
 *
 * @details Класс реализует интерфейс Work_Template_Interface и предоставляет методы для генерации данных,
 *          необходимых для отображения различных блоков на странице, таких как меню, блок пользователя, статистика
 *          и список лучших пользователей. Все данные формируются на основе запросов к базе данных и конфигурации.
 *
 * @implements Work_Template_Interface
 *
 * @callergraph
 * @callgraph
 *
 * @see     PhotoRigma::Classes::Work_Template_Interface Интерфейс, который реализует данный класс.
 * @see     PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
 * @see     PhotoRigma::Classes::Work::validate_mime_type() Метод для проверки MIME-типа файла.
 *
 * @note    Использованы константы:
 *       - DEFAULT_AVATAR - Аваар по-умолчанию,
 *
 * @warning Убедитесь, что передаваемые параметры в методы корректны, так как это может привести к ошибкам.
 *
 * Пример использования класса:
 * @code
 * // Создание экземпляра класса Work_Template
 * $template = new PhotoRigma::Classes::Work_Template();
 *
 * // Генерация данных для меню
 * $menu_data = $template->create_menu('home', 0);
 * print_r($menu_data);
 *
 * // Генерация данных для блока пользователя
 * $user_block = $template->template_user();
 * print_r($user_block);
 *
 * // Генерация статистических данных
 * $stat_data = $template->template_stat();
 * print_r($stat_data);
 *
 * // Генерация списка лучших пользователей
 * $best_users = $template->template_best_user(3);
 * print_r($best_users);
 * @endcode
 */
class Work_Template implements Work_Template_Interface
{
    //Свойства:
    private array $config; ///< Конфигурация приложения.
    private ?array $lang = null; ///< Языковые данные.
    private Database_Interface $db; ///< Объект для работы с базой данных.
    private ?User $user = null; ///< Объект пользователя.

    /**
     * @brief   Конструктор класса.
     *
     * @details Инициализирует зависимости: конфигурацию, базу данных и объект класса Work.
     *          Все параметры обязательны для корректной работы класса.
     *
     * @callergraph
     * @callgraph
     *
     * @param array              $config Конфигурация приложения.
     *                                   Должен быть массивом. Если передан некорректный тип, выбрасывается исключение.
     * @param Database_Interface $db     Объект для работы с базой данных.
     *
     * @throws InvalidArgumentException Если параметр $config не является массивом.
     *
     * @note    Важно: все зависимости должны быть корректно инициализированы перед использованием класса.
     * @warning Не передавайте в конструктор некорректные или пустые зависимости, так как это может привести к ошибкам.
     *
     * Пример использования конструктора:
     * @code
     * $config = ['temp_photo_w' => 800];
     * $db = new Database();
     * $template = new Work_Template($config, $db);
     * @endcode
     * @see     PhotoRigma::Classes::Work Родительский класс, через который передаются зависимости.
     * @see     PhotoRigma::Classes::Work_Template::$config Свойство, содержащее конфигурацию приложения.
     * @see     PhotoRigma::Classes::Work_Template::$db Свойство, содержащее объект для работы с базой данных.
     *
     */
    public function __construct(Database_Interface $db, array $config)
    {
        $this->config = $config;
        $this->db = $db;
    }

    /**
     * @brief   Получает значение приватного свойства.
     *
     * @details Метод позволяет получить доступ к приватному свойству `$config`.
     * Если запрашиваемое свойство не существует, выбрасывается исключение.
     * Доступ разрешён только к свойству `$config`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $name Имя свойства:
     *                     - Допустимое значение: 'config'.
     *                     - Если указано другое имя, выбрасывается исключение.
     *
     * @return array Значение свойства `$config`.
     *
     * @throws InvalidArgumentException Если запрашиваемое свойство не существует.
     *
     * @note    Этот метод предназначен только для доступа к свойству `$config`.
     * @warning Не используйте этот метод для доступа к другим свойствам, так как это вызовет исключение.
     *
     * Пример использования метода:
     * @code
     * $template = new \PhotoRigma\Classes\Work_Template(['temp_photo_w' => 800], $db, $work);
     * echo $template->config['temp_photo_w']; // Выведет: 800
     * @endcode
     * @see     PhotoRigma::Classes::Work_Template::$config Свойство, к которому обращается метод.
     *
     */
    public function &__get(string $name)
    {
        if ($name === 'config') {
            $result = &$this->config;
            return $result;
        }
        throw new InvalidArgumentException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не существует | Получено: '$name'"
        );
    }

    /**
     * @brief   Устанавливает значение приватного свойства.
     *
     * @details Метод позволяет изменить значение приватного свойства `$config`.
     * Если переданное имя свойства не соответствует `$config`, выбрасывается исключение.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $name  Имя свойства:
     *                      - Допустимое значение: 'config'.
     *                      - Если указано другое имя, выбрасывается исключение.
     * @param array  $value Новое значение свойства:
     *                      - Должен быть массивом.
     *
     * @throws InvalidArgumentException Если переданное имя свойства не соответствует `$config`.
     *
     * @note    Этот метод предназначен только для изменения свойства `$config`.
     * @warning Не используйте этот метод для изменения других свойств, так как это вызовет исключение.
     *
     * Пример использования метода:
     * @code
     * $template = new \PhotoRigma\Classes\Work_Template([], $db, $work);
     * $template->config = ['temp_photo_w' => 1024];
     * @endcode
     * @see     PhotoRigma::Classes::Work_Template::$config Свойство, которое изменяет метод.
     *
     */
    public function __set(string $name, array $value)
    {
        if ($name === 'config') {
            $this->config = $value;
        } else {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не может быть установлено | Получено: '$name'"
            );
        }
    }

    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    /**
     * @brief   Метод формирует массив данных для меню в зависимости от типа и активного пункта.
     *
     * @details Этот метод является редиректом на защищённый метод `_create_menu_internal()`.
     *          Метод формирует массив данных для меню на основе запроса к базе данных, учитывая тип меню и активный
     *          пункт. Поддерживаемые типы меню:
     *          - 0: Горизонтальное краткое меню.
     *          - 1: Вертикальное боковое меню.
     *          Для каждого пункта меню проверяются права доступа текущего пользователя (на основе свойств
     *          $this->user).
     *          Если пункт меню видим, он добавляется в результат с очисткой данных через Work::clean_field().
     *
     * @callergraph
     * @callgraph
     *
     * @param string $action Активный пункт меню.
     *                       Указывается строка, соответствующая активному пункту меню (например, 'home', 'profile').
     * @param int    $menu   Тип меню:
     *                       - 0: Горизонтальное краткое меню.
     *                       - 1: Вертикальное боковое меню.
     *                       Другие значения недопустимы и приведут к выбросу исключения InvalidArgumentException.
     *
     * @return array Массив с данными для меню.
     *               Каждый элемент массива содержит:
     *               - Ключ 'url': URL пункта меню (null, если пункт активен).
     *               - Ключ 'name': Название пункта меню (локализованное через $this->lang['menu'] или дефолтное
     *               значение). Если меню пустое, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный $menu или $action.
     * @throws RuntimeException Если произошла ошибка при выполнении запроса к базе данных.
     *
     * @note    Данные для меню берутся из таблицы TBL_MENU.
     *       Для получения дополнительной информации см. структуру таблицы.
     *
     * @warning Убедитесь, что передаваемые параметры корректны, так как это может привести к ошибкам.
     *          Также убедитесь, что права доступа пользователя ($this->user) настроены правильно.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Template
     * $template = new Work_Template();
     *
     * // Создание горизонтального меню
     * $short_menu = $template->create_menu('home', 0);
     * print_r($short_menu);
     *
     * // Создание вертикального меню
     * $long_menu = $template->create_menu('profile', 1);
     * print_r($long_menu);
     * @endcode
     * @see     PhotoRigma::Classes::Work::clean_field() Статический метод для очистки данных.
     *
     * @see     PhotoRigma::Classes::Work::create_menu() Публичный метод, который вызывает этот внутренний метод.
     * @see     PhotoRigma::Classes::Work_Template::_create_menu_internal() Защищённый метод, реализующий основную
     *          логику.
     */
    public function create_menu(string $action, int $menu): array
    {
        return $this->_create_menu_internal($action, $menu);
    }

    /**
     * @brief   Метод формирует массив данных для меню в зависимости от типа и активного пункта.
     *
     * @details Метод формирует массив данных для меню на основе запроса к базе данных, учитывая тип меню и активный
     *          пункт. Поддерживаемые типы меню:
     *          - 0: Горизонтальное краткое меню.
     *          - 1: Вертикальное боковое меню.
     *          Для каждого пункта меню проверяются права доступа текущего пользователя (на основе свойств
     *          $this->user).
     *          Если пункт меню видим, он добавляется в результат с очисткой данных через Work::clean_field().
     *          Метод является защищённым и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод create_menu().
     *
     * @callergraph
     * @callgraph
     *
     * @param string $action Активный пункт меню.
     *                       Указывается строка, соответствующая активному пункту меню (например, 'home', 'profile').
     * @param int    $menu   Тип меню:
     *                       - 0: Горизонтальное краткое меню.
     *                       - 1: Вертикальное боковое меню.
     *                       Другие значения недопустимы и приведут к выбросу исключения InvalidArgumentException.
     *
     * @return array Массив с данными для меню.
     *               Каждый элемент массива содержит:
     *               - Ключ 'url': URL пункта меню (null, если пункт активен).
     *               - Ключ 'name': Название пункта меню (локализованное через $this->lang['menu'] или дефолтное
     *               значение). Если меню пустое, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный $menu или $action.
     * @throws RuntimeException Если произошла ошибка при выполнении запроса к базе данных.
     *
     * @note    Данные для меню берутся из таблицы TBL_MENU.
     *       Для получения дополнительной информации см. структуру таблицы.
     *
     * @warning Убедитесь, что передаваемые параметры корректны, так как это может привести к ошибкам.
     *          Также убедитесь, что права доступа пользователя ($this->user) настроены правильно.
     *
     * Пример использования:
     * @code
     * // Пример использования метода _create_menu_internal()
     * $short_menu = $this->_create_menu_internal('home', 0); // Создание горизонтального меню
     * print_r($short_menu);
     *
     * $long_menu = $this->_create_menu_internal('profile', 1); // Создание вертикального меню
     * print_r($long_menu);
     * @endcode
     * @see     PhotoRigma::Classes::Work_Template::create_menu()
     *      Публичный метод, который вызывает этот внутренний метод.
     * @see     PhotoRigma::Classes::Work_Template::$db
     *      Свойство, содержащее объект базы данных.
     * @see     PhotoRigma::Classes::Work_Template::$lang
     *      Свойство, содержащее языковые строки.
     * @see     PhotoRigma::Classes::Work_Template::$user
     *      Свойство, содержащее данные текущего пользователя.
     * @see     PhotoRigma::Classes::Work::clean_field()
     *      Статический метод для очистки данных.
     *
     */
    protected function _create_menu_internal(string $action, int $menu): array
    {
        // Валидация входных данных.
        if (!in_array($menu, [0, 1], true)) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный тип меню (\$menu)"
            );
        }
        // Определение типа меню через match() (поддерживается начиная с PHP 8.0)
        $menu_type = match ($menu) {
            0       => 'short',
            1       => 'long',
            default => throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный тип меню (\$menu)"
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
        $menu_data = $this->db->res_arr();
        if ($menu_data === false) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные из базы данных"
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
     * @details Метод позволяет установить массив языковых данных.
     *          Если передан некорректный тип данных или `null`, выбрасывается исключение.
     *
     * @callergraph
     * @callgraph
     *
     * @param array $lang Языковые данные:
     *                    - Должен быть массивом.
     *                    - Каждый ключ должен быть строкой, а значение — допустимым для языковых данных.
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws InvalidArgumentException Если передан некорректный тип данных или `null`.
     *
     * @note    Убедитесь, что передаваемые языковые данные корректны и соответствуют ожидаемому формату.
     * @warning Не передавайте пустые или некорректные данные, так как это может привести к ошибкам.
     *
     * Пример использования метода:
     * @code
     * $template = new \PhotoRigma\Classes\Work_Template($config, $db, $work);
     * $template->set_lang(['key' => 'value']);
     * @endcode
     * @see     PhotoRigma::Classes::Work::set_lang() Метод в родительском классе Work, который вызывает этот метод.
     *
     * @see     PhotoRigma::Classes::Work_Template::$lang Свойство, которое изменяет метод.
     */
    public function set_lang(array $lang): void
    {
        $this->lang = $lang;
    }

    /**
     * @brief   Установка данных пользователя через сеттер.
     *
     * @details Метод позволяет установить объект пользователя.
     *          Если передан некорректный тип данных или `null`, выбрасывается исключение.
     *
     * @callergraph
     * @callgraph
     *
     * @param User $user Объект пользователя:
     *                   - Должен быть экземпляром класса User.
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws InvalidArgumentException Если передан некорректный тип данных или `null`.
     *
     * @note    Убедитесь, что передаваемый объект пользователя является экземпляром класса User.
     * @warning Не передавайте null или некорректные объекты, так как это может привести к ошибкам.
     *
     * Пример использования метода:
     * @code
     * $template = new \PhotoRigma\Classes\Work_Template($config, $db, $work);
     * $user = new \PhotoRigma\Classes\User();
     * $template->set_user($user);
     * @endcode
     * @see     PhotoRigma::Classes::Work::set_user() Метод в родительском классе Work, который вызывает этот метод.
     *
     * @see     PhotoRigma::Classes::Work_Template::$user Свойство, которое изменяет метод.
     */
    public function set_user(User $user): void
    {
        $this->user = $user;
    }

    /**
     * @brief   Формирует список пользователей, загрузивших наибольшее количество изображений.
     *
     * @details Метод выполняет запрос к базе данных с использованием JOIN для получения списка пользователей,
     *          которые загрузили наибольшее количество фотографий. Результат формируется в виде ассоциативного
     *          массива,
     *          который используется для отображения в шаблоне. Если данные отсутствуют, добавляется запись "пустого"
     *          пользователя.
     *
     * @callergraph
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
     * @note    Если запрос к базе данных не возвращает данных, добавляется запись "пустого" пользователя:
     *       - user_url: null
     *       - user_name: '---'
     *       - user_photo: '-'
     *
     * @warning Убедитесь, что таблицы базы данных (TBL_USERS и TBL_PHOTO) содержат корректные данные.
     *          Ошибки в структуре таблиц могут привести к некорректному результату.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Template
     * $template = new Work_Template();
     *
     * // Получение списка лучших пользователей
     * $best_users = $template->template_best_user(3);
     * print_r($best_users);
     * @endcode
     * @see     PhotoRigma::Classes::Work::template_best_user() Публичный метод, который вызывает этот внутренний
     *          метод.
     * @see     PhotoRigma::Classes::Work_Template::_template_best_user_internal() Защищённый метод, реализующий
     *          основную логику.
     * @see     PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
     *
     */
    public function template_best_user(int $best_user = 1): array
    {
        return $this->_template_best_user_internal($best_user);
    }

    /**
     * @brief   Формирует список пользователей, загрузивших наибольшее количество изображений.
     *
     * @details Метод выполняет запрос к базе данных с использованием JOIN для получения списка пользователей,
     *          которые загрузили наибольшее количество фотографий. Результат формируется в виде ассоциативного
     *          массива,
     *          который используется для отображения в шаблоне. Если данные отсутствуют, добавляется запись "пустого"
     *          пользователя. Метод является защищённым и предназначен для использования внутри класса или его
     *          наследников. Основная логика вызывается через публичный метод template_best_user().
     *
     * @callergraph
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
     * @note    Если запрос к базе данных не возвращает данных, добавляется запись "пустого" пользователя:
     *       - user_url: null
     *       - user_name: '---'
     *       - user_photo: '-'
     *
     * @warning Убедитесь, что таблицы базы данных (TBL_USERS и TBL_PHOTO) содержат корректные данные.
     *          Ошибки в структуре таблиц могут привести к некорректному результату.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода внутри класса
     * $best_users = $this->_template_best_user_internal(3);
     * print_r($best_users);
     * @endcode
     * @see     PhotoRigma::Classes::Work::clean_field()
     *      Метод для очистки данных.
     *
     * @see     PhotoRigma::Classes::Work_Template::template_best_user()
     *      Публичный метод, который вызывает этот внутренний метод.
     * @see     PhotoRigma::Classes::Work_Template::$lang
     *      Свойство, содержащее языковые строки.
     * @see     PhotoRigma::Classes::Work_Template::$db
     *      Свойство, содержащее объект для работы с базой данных.
     */
    protected function _template_best_user_internal(int $best_user = 1): array
    {
        // Блок получения данных через join()
        $this->db->join(
            [TBL_USERS . '.`id`', TBL_USERS . '.`real_name`', 'COUNT(' . TBL_PHOTO . '.`id`) AS `user_photo`'],
            TBL_USERS, // Основная таблица (user)
            [
                [
                    'type'  => 'INNER', // Тип JOIN
                    'table' => TBL_PHOTO, // Присоединяемая таблица (photo)
                    'on'    => TBL_USERS . '.`id` = ' . TBL_PHOTO . '.`user_upload`', // Условие JOIN
                ],
            ],
            [
                'group' => TBL_USERS . '.`id`', // Группировка по ID пользователя
                'order' => '`user_photo` DESC', // Сортировка по количеству фотографий
                'limit' => $best_user, // Лимит на количество результатов
            ]
        );
        $user_data = $this->db->res_arr();
        // Проверка: $user_data может быть массивом (если данные есть) или false (если запрос не вернул данных).
        // Обработка данных пользователей
        $array_data = ($user_data !== false) ? array_map(function ($current_user) {
            return [
                'user_url'   => sprintf(
                    '%s?action=profile&amp;subact=profile&amp;uid=%d',
                    $this->config['site_url'],
                    $current_user['id']
                ),
                'user_name'  => Work::clean_field($current_user['real_name']),
                'user_photo' => (int)$current_user['user_photo'],
            ];
        }, $user_data) : [];

        // Добавление данных для пустого пользователя
        if (empty($array_data)) {
            $array_data[1] = [
                'user_url'   => '',
                'user_name'  => '---',
                'user_photo' => '-',
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
     * @brief   Генерирует массив статистических данных для шаблона.
     *
     * @details Метод выполняет запросы к базе данных для получения статистической информации о пользователях,
     *          категориях, фотографиях, оценках и онлайн-пользователях. Результат формируется в виде ассоциативного
     *          массива, который используется для отображения статистики на странице. Этот метод вызывается через
     *          редирект из публичного метода. Время онлайна жестко закодировано как 900 секунд (15 минут). Для
     *          изменения требуется ручное внесение изменений в код.
     *
     * @callergraph
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
     *               - D_STAT_ONLINE: Список онлайн-пользователей (HTML-ссылки) или сообщение об отсутствии
     *               онлайн-пользователей.
     *
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных.
     *
     * @warning Убедитесь, что таблицы базы данных (TBL_USERS, TBL_CATEGORY, TBL_PHOTO, TBL_RATE_USER, TBL_RATE_MODER)
     *          содержат корректные данные. Ошибки в структуре таблиц могут привести к некорректной статистике.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Template
     * $template = new Work_Template();
     *
     * // Получение статистических данных
     * $stat_data = $template->template_stat();
     * print_r($stat_data);
     * @endcode
     * @see     PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
     *
     * @see     PhotoRigma::Classes::Work::template_stat() Публичный метод, который вызывает этот внутренний метод.
     * @see     PhotoRigma::Classes::Work_Template::_template_stat_internal() Защищённый метод, реализующий основную
     *          логику.
     */
    public function template_stat(): array
    {
        return $this->_template_stat_internal();
    }

    /**
     * @brief   Генерирует массив статистических данных для шаблона.
     *
     * @details Метод выполняет запросы к базе данных для получения статистической информации о пользователях,
     *          категориях, фотографиях, оценках и онлайн-пользователях. Результат формируется в виде ассоциативного
     *          массива, который используется для отображения статистики на странице. Этот метод вызывается через
     *          редирект из публичного метода. Время онлайна жестко закодировано как 900 секунд (15 минут). Для
     *          изменения требуется ручное внесение изменений в код. Метод является защищённым и предназначен для
     *          использования внутри класса или его наследников. Основная логика вызывается через публичный метод
     *          template_stat().
     *
     * @callergraph
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
     *               - D_STAT_ONLINE: Список онлайн-пользователей (HTML-ссылки) или сообщение об отсутствии
     *               онлайн-пользователей.
     *
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных.
     *
     * @warning Убедитесь, что таблицы базы данных (TBL_USERS, TBL_CATEGORY, TBL_PHOTO, TBL_RATE_USER, TBL_RATE_MODER)
     *          содержат корректные данные. Ошибки в структуре таблиц могут привести к некорректной статистике.
     *
     * Пример использования:
     * @code
     * // Пример вызова метода внутри класса
     * $stat_data = $this->_template_stat_internal();
     * print_r($stat_data);
     * @endcode
     * @see     PhotoRigma::Classes::Work_Template::$db
     *      Свойство, содержащее объект для работы с базой данных.
     * @see     PhotoRigma::Classes::Work::clean_field()
     *      Метод для очистки данных.
     *
     * @see     PhotoRigma::Classes::Work_Template::template_stat()
     *      Публичный метод, который вызывает этот внутренний метод.
     * @see     PhotoRigma::Classes::Work_Template::$lang
     *      Свойство, содержащее языковые строки.
     */
    protected function _template_stat_internal(): array
    {
        $stat = [];
        // Получение статистики пользователей
        $this->db->select(
            [
                'COUNT(*) AS `regist_user`',
                'SUM(CASE WHEN `group_id` = 3 THEN 1 ELSE 0 END) AS `user_admin`',
                'SUM(CASE WHEN `group_id` = 2 THEN 1 ELSE 0 END) AS `user_moder`',
            ],
            TBL_USERS
        );
        $user_stats = $this->db->res_row();
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
                'where' => 'c.`id` != 0', // Условие WHERE
            ]
        );
        $category_stats = $this->db->res_row();

        // Обработка результатов
        $stat['category'] = $category_stats ? (int)$category_stats['category'] : 0;
        $stat['category_user'] = $category_stats ? (int)$category_stats['category_user'] : 0;
        $stat['category'] += $stat['category_user'];
        // Получение статистики фотографий
        $this->db->select(['COUNT(*) AS `photo_count`'], TBL_PHOTO);
        $photo_stats = $this->db->res_row();
        $stat['photo'] = $photo_stats ? (int)$photo_stats['photo_count'] : 0;
        // Получение статистики оценок
        $this->db->select(['COUNT(*) AS `rate_user`'], TBL_RATE_USER);
        $rate_user_stats = $this->db->res_row();
        $stat['rate_user'] = $rate_user_stats ? (int)$rate_user_stats['rate_user'] : 0;
        $this->db->select(['COUNT(*) AS `rate_moder`'], TBL_RATE_MODER);
        $rate_moder_stats = $this->db->res_row();
        $stat['rate_moder'] = $rate_moder_stats ? (int)$rate_moder_stats['rate_moder'] : 0;
        // Получение онлайн-пользователей
        $this->db->select(
            '*',
            VIEW_USERS_ONLINE
        );
        $online_users_data = $this->db->res_arr();
        $stat['online'] = $online_users_data ? implode(', ', array_map(function ($user) {
            return sprintf(
                '<a href="%s?action=profile&amp;subact=profile&amp;uid=%d" title="%s">%s</a>',
                $this->config['site_url'],
                $user['id'],
                Work::clean_field($user['real_name']),
                Work::clean_field($user['real_name'])
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
     * @brief   Формирование блока пользователя.
     *
     * @details Этот метод является редиректом на защищённый метод `_template_user_internal()`.
     *          Метод формирует массив данных для блока пользователя в зависимости от статуса авторизации:
     *          - Если пользователь не авторизован, формируется блок с ссылками на вход, восстановление пароля и
     *          регистрацию.
     *          - Если пользователь авторизован, формируется блок с приветствием, группой и аватаром.
     *          Для авторизованных пользователей проверяется существование аватара и его MIME-тип через
     *          Work::validate_mime_type(). Если аватар недоступен или имеет недопустимый MIME-тип, используется
     *          дефолтный аватар (DEFAULT_AVATAR).
     *
     * @callergraph
     * @callgraph
     *
     * @return array Массив с данными для блока пользователя:
     *               - Для неавторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока.
     *                 - 'L_LOGIN', 'L_PASSWORD', 'L_ENTER', 'L_FORGOT_PASSWORD', 'L_REGISTRATION': Локализованные
     *                 строки.
     *                 - 'U_LOGIN', 'U_FORGOT_PASSWORD', 'U_REGISTRATION': URL для входа, восстановления пароля и
     *                 регистрации.
     *               - Для авторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока.
     *                 - 'L_HI_USER': Приветствие с именем пользователя.
     *                 - 'L_GROUP': Группа пользователя.
     *                 - 'U_AVATAR': URL аватара (или дефолтного аватара, если файл недоступен или некорректен).
     *
     * @throws RuntimeException Если объект пользователя не установлен или данные некорректны.
     * @throws RandomException
     *
     * @note    Используется глобальная переменная $_SESSION для проверки статуса авторизации.
     *
     * @warning Убедитесь, что объект пользователя ($this->user) корректно установлен перед вызовом метода.
     *          Также убедитесь, что конфигурация аватаров ($this->config['avatar_folder']) настроена правильно.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Template
     * $template = new Work_Template();
     *
     * // Получение данных для блока пользователя
     * $user_block = $template->template_user();
     * print_r($user_block);
     * @endcode
     * @see     PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
     * @see     PhotoRigma::Classes::Work::validate_mime_type() Метод для проверки MIME-типа файла.
     *
     * @see     PhotoRigma::Classes::Work::template_user() Публичный метод, который вызывает этот внутренний метод.
     * @see     PhotoRigma::Classes::Work_Template::_template_user_internal() Защищённый метод, реализующий основную
     *          логику.
     */
    public function template_user(): array
    {
        return $this->_template_user_internal();
    }

    /**
     * @brief   Формирование блока пользователя.
     *
     * @details Метод формирует массив данных для блока пользователя в зависимости от статуса авторизации:
     *          - Если пользователь не авторизован, формируется блок со ссылками на вход, восстановление пароля и
     *          регистрацию.
     *          - Если пользователь авторизован, формируется блок с приветствием, группой и аватаром.
     *          Для авторизованных пользователей проверяется существование аватара и его MIME-тип через
     *          Work::validate_mime_type(). Если аватар недоступен или имеет недопустимый MIME-тип, используется
     *          дефолтный аватар (DEFAULT_AVATAR). Метод является защищённым и предназначен для использования внутри
     *          класса или его наследников. Основная логика вызывается через публичный метод template_user().
     *
     * @callergraph
     * @callgraph
     *
     * @return array Массив с данными для блока пользователя:
     *               - Для неавторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока.
     *                 - 'L_LOGIN', 'L_PASSWORD', 'L_ENTER', 'L_FORGOT_PASSWORD', 'L_REGISTRATION': Локализованные
     *                 строки.
     *                 - 'U_LOGIN', 'U_FORGOT_PASSWORD', 'U_REGISTRATION': URL для входа, восстановления пароля и
     *                 регистрации.
     *               - Для авторизованных пользователей:
     *                 - 'NAME_BLOCK': Название блока.
     *                 - 'L_HI_USER': Приветствие с именем пользователя.
     *                 - 'L_GROUP': Группа пользователя.
     *                 - 'U_AVATAR': URL аватара (или дефолтного аватара, если файл недоступен или некорректен).
     *
     * @throws RuntimeException Если объект пользователя не установлен или данные некорректны.
     * @throws RandomException
     * @throws Exception
     *
     * @note    Используется глобальная переменная $_SESSION для проверки статуса авторизации.
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
     * @see     PhotoRigma::Classes::Work_Template::$user
     *      Свойство, содержащее данные текущего пользователя.
     * @see     PhotoRigma::Classes::Work::clean_field()
     *      Метод для очистки данных.
     * @see     PhotoRigma::Classes::Work::validate_mime_type()
     *      Метод для проверки MIME-типа файла.
     *
     * @see     PhotoRigma::Classes::Work_Template::template_user()
     *      Публичный метод, который вызывает этот внутренний метод.
     * @see     PhotoRigma::Classes::Work_Template::$lang
     *      Свойство, содержащее языковые строки.
     */
    protected function _template_user_internal(): array
    {
        // Проверка, что объект пользователя установлен
        if ($this->user === null) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Объект пользователя не установлен | Решение: используйте set_user() для внедрения зависимости"
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
                        __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый MIME-тип аватара | Файл: $full_avatar_path"
                    );
                    $avatar_path = sprintf('%s/%s', $this->config['avatar_folder'], DEFAULT_AVATAR);
                }
            }
        }
        $csrf = $this->user->csrf_token();
        // Проверка статуса авторизации через match
        $array_data = match ($this->user->session['login_id'] ?? 0) {
            0       => [
                'NAME_BLOCK'        => $this->lang['main']['user_block'],
                'CSRF_TOKEN'        => $csrf,
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
        return $array_data;
    }
}
