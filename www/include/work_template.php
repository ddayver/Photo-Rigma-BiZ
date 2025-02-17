<?php

/**
 * @file        include/work_template.php
 * @brief       Файл содержит класс Work_Template, который отвечает за формирование данных для шаблонов.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-02-13
 * @namespace   PhotoRigma\\Classes
 *
 * @details     Этот файл содержит класс `Work_Template`, который реализует интерфейс `Work_Template_Interface`.
 *              Класс предоставляет методы для генерации данных, необходимых для отображения различных блоков на странице,
 *              таких как меню, блок пользователя, статистика и список лучших пользователей. Все данные формируются
 *              на основе запросов к базе данных и конфигурации. Реализация методов зависит от глобальных переменных,
 *              таких как $_SESSION, для проверки статуса авторизации.
 *
 * @see         PhotoRigma::Classes::Work_Template_Interface Интерфейс, который реализует данный класс.
 * @see         PhotoRigma::Classes::Work Класс, через который вызываются методы для работы с шаблонами.
 * @see         PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
 * @see         index.php Файл, который подключает work_template.php.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в формировании данных для шаблонов.
 *              Класс использует глобальную переменную $_SESSION для проверки статуса авторизации. Рекомендуется заменить её
 *              на метод или свойство класса для инкапсуляции доступа к сессии.
 *
 * @todo        Заменить использование $_SESSION на метод или свойство класса для инкапсуляции доступа к сессии.
 * @todo        Вынести время, за которое считать пользователей онлайн, в настройки через БД.
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
 * @brief Интерфейс Work_Template_Interface определяет контракт для классов, формирующих данные для шаблонов.
 *
 * @details Интерфейс предоставляет методы для генерации данных, необходимых для отображения различных блоков
 * на странице, таких как меню, блок пользователя, статистика и список лучших пользователей. Реализующие классы
 * должны обеспечивать выполнение всех методов согласно их описанию.
 *
 * @see PhotoRigma::Classes::Work_Template Класс, реализующий данный интерфейс.
 * @see PhotoRigma::Classes::Work_Template::$db Свойство, содержащее объект для работы с базой данных.
 * @see PhotoRigma::Classes::Work_Template::$lang Свойство, содержащее языковые строки.
 * @see PhotoRigma::Classes::Work_Template::$user Свойство, содержащее данные текущего пользователя.
 * @see PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
 * @see PhotoRigma::Classes::Work::validate_mime_type() Метод для проверки MIME-типа файла.
 *
 * @note Методы интерфейса зависят от данных из базы данных и конфигурации.
 * Реализующие классы должны обрабатывать возможные ошибки при работе с БД.
 *
 * @warning Убедитесь, что передаваемые параметры в методы интерфейса корректны, так как это может привести к ошибкам.
 */
interface Work_Template_Interface
{
    /**
     * @brief Метод формирует массив данных для меню в зависимости от типа и активного пункта.
     *
     * @details Метод формирует массив данных для меню на основе переданного типа ($menu) и активного пункта ($action).
     * Если данные отсутствуют, возвращается пустой массив.
     *
     * @see PhotoRigma::Classes::Work::create_menu() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Template::$db Свойство, содержащее объект базы данных.
     * @see PhotoRigma::Classes::Work_Template::$lang Свойство, содержащее языковые строки.
     * @see PhotoRigma::Classes::Work_Template::$user Свойство, содержащее данные текущего пользователя.
     *
     * @param string $action Активный пункт меню.
     * @param int    $menu   Тип меню. Допустимые значения: 0 (горизонтальное), 1 (вертикальное).
     *
     * @return array Ассоциативный массив с данными для меню. Если меню пустое, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный $menu или $action.
     * @throws RuntimeException         Если произошла ошибка при выполнении запроса к базе данных.
     *
     * @note Данные для меню берутся из таблицы TBL_MENU. Для получения дополнительной информации см. структуру таблицы.
     * @warning Убедитесь, что передаваемые параметры корректны, так как это может привести к ошибкам.
     *
     * @example
     * @code
     * // Пример использования метода create_menu()
     * $short_menu = $work->create_menu('home', 0); // Создание горизонтального меню
     * print_r($short_menu);
     *
     * $long_menu = $work->create_menu('profile', 1); // Создание вертикального меню
     * print_r($long_menu);
     * @endcode
     */
    public function create_menu(string $action, int $menu): array;

    /**
     * @brief Формирование блока пользователя.
     *
     * @details Метод формирует массив данных для блока пользователя в зависимости от статуса авторизации.
     * Если пользователь не авторизован, формируется блок с ссылками на вход, восстановление пароля и регистрацию.
     * Если пользователь авторизован, формируется блок с приветствием, группой и аватаром.
     *
     * @see PhotoRigma::Classes::Work::template_user() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Template::$lang Свойство, содержащее языковые строки.
     * @see PhotoRigma::Classes::Work_Template::$user Свойство, содержащее данные текущего пользователя.
     * @see PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
     * @see PhotoRigma::Classes::Work::validate_mime_type() Метод для проверки MIME-типа файла.
     *
     * @return array Ассоциативный массив с данными для блока пользователя:
     *               - NAME_BLOCK: Название блока.
     *               - L_LOGIN: Подпись для кнопки входа.
     *               - U_LOGIN: Ссылка для входа.
     *               - L_PASSWORD: Подпись для поля пароля.
     *               - L_ENTER: Подпись для кнопки входа.
     *               - L_FORGOT_PASSWORD: Подпись для ссылки "Забыли пароль?".
     *               - U_FORGOT_PASSWORD: Ссылка для восстановления пароля.
     *               - L_REGISTRATION: Подпись для ссылки регистрации.
     *               - U_REGISTRATION: Ссылка для регистрации.
     *               - L_HI_USER: Приветствие пользователя.
     *               - L_GROUP: Группа пользователя.
     *               - U_AVATAR: URL аватара пользователя.
     *
     * @throws RuntimeException Если объект пользователя не установлен или данные некорректны.
     *
     * @note Используется глобальная переменная $_SESSION для проверки статуса авторизации.
     * @todo Заменить использование $_SESSION на метод или свойство класса для инкапсуляции доступа к сессии.
     *
     * @example
     * @code
     * // Пример использования метода template_user()
     * $work = new Work($db, $config);
     * $work->set_user(new User());
     * $user_block = $work->template_user();
     * print_r($user_block);
     * @endcode
     */
    public function template_user(): array;

    /**
     * @brief Генерирует массив статистических данных для шаблона.
     *
     * @details Метод выполняет запросы к базе данных для получения статистической информации о пользователях,
     * категориях, фотографиях, оценках и онлайн-пользователях. Результат формируется в виде ассоциативного массива,
     * который используется для отображения статистики на странице.
     *
     * @see PhotoRigma::Classes::Work::template_stat() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Template::$lang Свойство, содержащее языковые строки.
     * @see PhotoRigma::Classes::Work_Template::$db Свойство, содержащее объект для работы с базой данных.
     * @see PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
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
     *               - D_STAT_ONLINE: Список онлайн-пользователей.
     *
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных.
     *
     * @todo Вынести время за которое считать пользователей онлайн в настройки через БД.
     * @note Время онлайна жестко закодировано как 900 секунд (15 минут). Для изменения требуется ручное внесение изменений в код.
     */
    public function template_stat(): array;

    /**
     * @brief Формирует список пользователей, загрузивших наибольшее количество изображений.
     *
     * @details Метод выполняет запрос к базе данных с использованием JOIN для получения списка пользователей,
     * которые загрузили наибольшее количество фотографий. Результат формируется в виде ассоциативного массива,
     * который используется для отображения в шаблоне. Если данные отсутствуют, добавляется запись "пустого" пользователя.
     *
     * @see PhotoRigma::Classes::Work::template_best_user() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Template::$lang Свойство, содержащее языковые строки.
     * @see PhotoRigma::Classes::Work_Template::$db Свойство, содержащее объект для работы с базой данных.
     * @see PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
     *
     * @param int $best_user Количество лучших пользователей для вывода. Должно быть положительным целым числом. Значение по умолчанию: 1.
     *
     * @return array Ассоциативный массив с данными для вывода в шаблон:
     *               - NAME_BLOCK: Название блока.
     *               - L_USER_NAME: Подпись для имени пользователя.
     *               - L_USER_PHOTO: Подпись для количества фотографий.
     *               - user_url: Ссылка на профиль пользователя (null, если данных нет).
     *               - user_name: Имя пользователя ('---', если данных нет).
     *               - user_photo: Количество загруженных фотографий ('-', если данных нет).
     *
     * @throws InvalidArgumentException Если параметр $best_user не является положительным целым числом.
     *
     * @note Если запрос к базе данных не возвращает данных, добавляется запись "пустого" пользователя.
     */
    public function template_best_user(int $best_user = 1): array;
}

/**
 * @brief Класс Work_Template отвечает за формирование данных для шаблонов.
 *
 * @details Класс реализует интерфейс Work_Template_Interface и предоставляет методы для генерации данных,
 * необходимых для отображения различных блоков на странице, таких как меню, блок пользователя, статистика
 * и список лучших пользователей. Все данные формируются на основе запросов к базе данных и конфигурации.
 *
 * @see PhotoRigma::Classes::Work_Template_Interface Интерфейс, который реализует данный класс.
 * @see PhotoRigma::Classes::Work_Template::$db Свойство, содержащее объект для работы с базой данных.
 * @see PhotoRigma::Classes::Work_Template::$lang Свойство, содержащее языковые строки.
 * @see PhotoRigma::Classes::Work_Template::$user Свойство, содержащее данные текущего пользователя.
 * @see PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
 * @see PhotoRigma::Classes::Work::validate_mime_type() Метод для проверки MIME-типа файла.
 *
 * @note Класс использует глобальную переменную $_SESSION для проверки статуса авторизации.
 * Рекомендуется заменить её на метод или свойство класса для инкапсуляции доступа к сессии.
 *
 * @warning Убедитесь, что передаваемые параметры в методы корректны, так как это может привести к ошибкам.
 */
class Work_Template implements Work_Template_Interface
{
    //Свойства:
    private array $config; ///< Конфигурация приложения.
    private ?array $lang = null; ///< Языковые данные.
    private Database_Interface $db = null; ///< Объект для работы с базой данных.
    private Work $work = null; ///< Основной объект приложения.
    private ?User $user = null; ///< Объект пользователя.

    /**
     * @brief Конструктор класса.
     *
     * @details Инициализирует зависимости: конфигурацию, базу данных и объект класса Work.
     *          Все параметры обязательны для корректной работы класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work Родительский класс, через который передаются зависимости.
     * @see PhotoRigma::Classes::Work_Template::$config Свойство, содержащее конфигурацию приложения.
     * @see PhotoRigma::Classes::Work_Template::$db Свойство, содержащее объект для работы с базой данных.
     *
     * @param array $config Конфигурация приложения.
     *                      Должен быть массивом. Если передан некорректный тип, выбрасывается исключение.
     * @param Database_Interface $db Объект для работы с базой данных.
     *
     * @throws InvalidArgumentException Если параметр $config не является массивом.
     *
     * @note Важно: все зависимости должны быть корректно инициализированы перед использованием класса.
     * @warning Не передавайте в конструктор некорректные или пустые зависимости, так как это может привести к ошибкам.
     *
     * @example PhotoRigma::Classes::Work_Template::__construct
     * @code
     * // Пример использования конструктора
     * $config = ['temp_photo_w' => 800];
     * $db = new Database();
     * $work = new Work();
     * $template = new Work_Template($config, $db, $work);
     * @endcode
     */
    public function __construct(array $config, Database_Interface $db)
    {
        if (!is_array($config)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка конструктора | Ожидается массив конфигурации"
            );
        }
        $this->config = $config;
        $this->db = $db;
    }

    /**
     * @brief Получает значение приватного свойства.
     *
     * @details Метод позволяет получить доступ к приватному свойству `$config`.
     * Если запрашиваемое свойство не существует, выбрасывается исключение.
     * Доступ разрешён только к свойству `$config`.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work_Template::$config Свойство, к которому обращается метод.
     *
     * @param string $name Имя свойства:
     *                     - Допустимое значение: 'config'.
     *                     - Если указано другое имя, выбрасывается исключение.
     *
     * @return array Значение свойства `$config`.
     *
     * @throws InvalidArgumentException Если запрашиваемое свойство не существует.
     *
     * @note Этот метод предназначен только для доступа к свойству `$config`.
     * @warning Не используйте этот метод для доступа к другим свойствам, так как это вызовет исключение.
     *
     * @example PhotoRigma::Classes::Work_Template::__get
     * @code
     * // Пример использования метода
     * $template = new \PhotoRigma\Classes\Work_Template(['temp_photo_w' => 800], $db, $work);
     * echo $template->config['temp_photo_w']; // Выведет: 800
     * @endcode
     */
    public function __get(string $name)
    {
        if ($name === 'config') {
            return $this->config;
        }
        throw new \InvalidArgumentException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не существует | Получено: '{$name}'"
        );
    }

    /**
     * @brief Устанавливает значение приватного свойства.
     *
     * @details Метод позволяет изменить значение приватного свойства `$config`.
     * Если переданное имя свойства не соответствует `$config`, выбрасывается исключение.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work_Template::$config Свойство, которое изменяет метод.
     *
     * @param string $name Имя свойства:
     *                     - Допустимое значение: 'config'.
     *                     - Если указано другое имя, выбрасывается исключение.
     * @param array $value Новое значение свойства:
     *                     - Должен быть массивом.
     *
     * @throws InvalidArgumentException Если переданное имя свойства не соответствует `$config`.
     *
     * @note Этот метод предназначен только для изменения свойства `$config`.
     * @warning Не используйте этот метод для изменения других свойств, так как это вызовет исключение.
     *
     * @example PhotoRigma::Classes::Work_Template::__set
     * @code
     * // Пример использования метода
     * $template = new \PhotoRigma\Classes\Work_Template([], $db, $work);
     * $template->config = ['temp_photo_w' => 1024];
     * @endcode
     */
    public function __set(string $name, $value)
    {
        if ($name === 'config') {
            $this->config = $value;
        } else {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не может быть установлено | Получено: '{$name}'"
            );
        }
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
     * @see PhotoRigma::Classes::Work_Template::$lang Свойство, которое изменяет метод.
     * @see PhotoRigma::Classes::Work::set_lang() Метод в родительском классе Work, который вызывает этот метод.
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
     * @example PhotoRigma::Classes::Work_Template::set_lang
     * @code
     * // Пример использования метода
     * $template = new \PhotoRigma\Classes\Work_Template($config, $db, $work);
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
     * @brief Установка данных пользователя через сеттер.
     *
     * @details Метод позволяет установить объект пользователя.
     *          Если передан некорректный тип данных или `null`, выбрасывается исключение.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work_Template::$user Свойство, которое изменяет метод.
     * @see PhotoRigma::Classes::Work::set_user() Метод в родительском классе Work, который вызывает этот метод.
     *
     * @param User $user Объект пользователя:
     *                   - Должен быть экземпляром класса User.
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws InvalidArgumentException Если передан некорректный тип данных или `null`.
     *
     * @note Убедитесь, что передаваемый объект пользователя является экземпляром класса User.
     * @warning Не передавайте null или некорректные объекты, так как это может привести к ошибкам.
     *
     * @example PhotoRigma::Classes::Work_Template::set_user
     * @code
     * // Пример использования метода
     * $template = new \PhotoRigma\Classes\Work_Template($config, $db, $work);
     * $user = new \PhotoRigma\Classes\User();
     * $template->set_user($user);
     * @endcode
     */
    public function set_user(User $user): void
    {
        $this->user = $user;
    }

    /**
     * @brief Метод формирует массив данных для меню в зависимости от типа и активного пункта.
     *
     * @details Этот метод является редиректом на защищённый метод _create_menu_internal().
     * Он предоставляет доступ к функционалу формирования данных для меню через публичный интерфейс.
     *
     * @see PhotoRigma::Classes::Work::create_menu() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Template::_create_menu_internal() Защищённый метод, реализующий логику.
     *
     * @param string $action Активный пункт меню.
     * @param int    $menu   Тип меню:
     *                       - 0: Горизонтальное краткое меню.
     *                       - 1: Вертикальное боковое меню.
     *
     * @return array Массив с данными для меню. Если меню пустое, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный $menu или $action.
     * @throws RuntimeException         Если произошла ошибка при выполнении запроса к базе данных.
     *
     * @note Данные для меню берутся из таблицы TBL_MENU. Для получения дополнительной информации см. структуру таблицы.
     * @warning Убедитесь, что передаваемые параметры корректны, так как это может привести к ошибкам.
     *
     * @example
     * @code
     * // Пример использования метода create_menu()
     * $short_menu = $work->create_menu('home', 0); // Создание горизонтального меню
     * print_r($short_menu);
     *
     * $long_menu = $work->create_menu('profile', 1); // Создание вертикального меню
     * print_r($long_menu);
     * @endcode
     */
    public function create_menu(string $action, int $menu): array
    {
        return $this->_create_menu_internal($action, $menu);
    }

    /**
     * @brief Формирует список пользователей, загрузивших наибольшее количество изображений.
     *
     * @details Этот метод является редиректом на защищённый метод _template_best_user_internal().
     * Он предоставляет доступ к функционалу формирования списка лучших пользователей через публичный интерфейс.
     *
     * @see PhotoRigma::Classes::Work::template_best_user() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Template::_template_best_user_internal() Защищённый метод, реализующий логику.
     *
     * @param int $best_user Количество лучших пользователей для вывода. Должно быть положительным целым числом.
     *
     * @return array Массив данных для вывода в шаблон:
     *               - NAME_BLOCK: Название блока.
     *               - L_USER_NAME: Подпись для имени пользователя.
     *               - L_USER_PHOTO: Подпись для количества фотографий.
     *               - user_url: Ссылка на профиль пользователя.
     *               - user_name: Имя пользователя.
     *               - user_photo: Количество загруженных фотографий.
     *
     * @throws InvalidArgumentException Если параметр $best_user не является положительным целым числом.
     *
     * @note Если запрос к базе данных не возвращает данных, добавляется запись "пустого" пользователя.
     */
    public function template_best_user(int $best_user = 1): array
    {
        return $this->_template_best_user_internal($best_user);
    }

    /**
     * @brief Генерирует массив статистических данных для шаблона.
     *
     * @details Этот метод является редиректом на защищённый метод _template_stat_internal().
     * Он предоставляет доступ к функционалу генерации статистических данных через публичный интерфейс.
     *
     * @see PhotoRigma::Classes::Work::template_stat() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Template::_template_stat_internal() Защищённый метод, реализующий логику.
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
     *               - D_STAT_ONLINE: Список онлайн-пользователей.
     *
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных.
     *
     * @todo Вынести время за которое считать пользователей онлайн в настройки через БД.
     * @note Время онлайна жестко закодировано как 900 секунд (15 минут). Для изменения требуется ручное внесение изменений в код.
     */
    public function template_stat(): array
    {
        return $this->_template_stat_internal();
    }

    /**
     * @brief Формирование блока пользователя.
     *
     * @details Этот метод является редиректом на защищённый метод _template_user_internal().
     * Он предоставляет доступ к функционалу формирования данных для блока пользователя через публичный интерфейс.
     *
     * @see PhotoRigma::Classes::Work::template_user() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Template::_template_user_internal() Защищённый метод, реализующий логику.
     *
     * @return array Массив с данными для блока пользователя.
     *
     * @throws RuntimeException Если объект пользователя не установлен или данные некорректны.
     *
     * @note Используется глобальная переменная $_SESSION для проверки статуса авторизации.
     * @todo Заменить использование $_SESSION на метод или свойство класса для инкапсуляции доступа к сессии.
     *
     * @example
     * @code
     * // Пример использования метода template_user()
     * $work = new Work($db, $config);
     * $work->set_user(new User());
     * $user_block = $work->template_user();
     * print_r($user_block);
     * @endcode
     */
    public function template_user(): array
    {
        return $this->_template_user_internal();
    }

    /**
     * @brief Метод формирует массив данных для меню в зависимости от типа и активного пункта.
     *
     * @details Метод формирует массив данных для меню в зависимости от типа и активного пункта. Результат формируется на основе запроса к базе данных.
     * Этот метод вызывается через редирект из публичного метода.
     *
     * @see PhotoRigma::Classes::Work_Template::create_menu() Этот метод вызывается через редирект из публичного метода.
     * @see PhotoRigma::Classes::Work_Template::$db Свойство, содержащее объект базы данных.
     * @see PhotoRigma::Classes::Work_Template::$lang Свойство, содержащее языковые строки.
     * @see PhotoRigma::Classes::Work_Template::$user Свойство, содержащее данные текущего пользователя.
     * @see PhotoRigma::Classes::Database::select() Метод, используемый для выполнения SELECT-запросов.
     * @see PhotoRigma::Classes::Database::res_arr() Метод, используемый для получения массива результатов.
     *
     * @param string $action Активный пункт меню.
     * @param int    $menu   Тип меню:
     *                       - 0: Горизонтальное краткое меню.
     *                       - 1: Вертикальное боковое меню.
     *
     * @return array Массив с данными для меню. Если меню пустое, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный $menu или $action.
     * @throws RuntimeException         Если произошла ошибка при выполнении запроса к базе данных.
     *
     * @note Данные для меню берутся из таблицы TBL_MENU. Для получения дополнительной информации см. структуру таблицы.
     * @warning Убедитесь, что передаваемые параметры корректны, так как это может привести к ошибкам.
     *
     * @example
     * @code
     * // Пример использования метода create_menu()
     * $short_menu = $work->create_menu('home', 0); // Создание горизонтального меню
     * print_r($short_menu);
     *
     * $long_menu = $work->create_menu('profile', 1); // Создание вертикального меню
     * print_r($long_menu);
     * @endcode
     */
    protected function _create_menu_internal(string $action, int $menu): array
    {
        // Валидация входных данных (лучше перебдеть :D)
        if (!is_string($action)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный тип параметра \$action"
            );
        }
        if (!in_array($menu, [0, 1], true)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный тип меню (\$menu)"
            );
        }
        // Определение типа меню через match() (поддерживается начиная с PHP 8.0)
        $menu_type = match ($menu) {
            0 => 'short',
            1 => 'long',
            default => throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный тип меню (\$menu)"
            ),
        };
        // Формирование запроса для получения данных меню (используем параметры для безопасности)
        // Условие ":menu_type = 1" используется для выборки по имени столбца (смена имени столбца для выборки).
        $this->db->select(
            ['*'], // Все поля таблицы используются для этого запроса
            TBL_MENU,
            [
                'where' => ':menu_type = 1',
                'params' => [':menu_type' => $menu_type],
                'order_by' => ['id' => 'ASC']
            ]
        );
        // Получение результатов запроса
        $menu_data = $this->db->res_arr();
        if ($menu_data === false) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные из базы данных"
            );
        }
        // Формирование массива меню
        $menu_items = [];
        foreach ($menu_data as $key => $menu_item) {
            $is_visible = true;
            // Проверяем права доступа для текущего пользователя
            // user_login: 0 - гость, 1 - не гость.
            // user_access: 0 - нельзя смотреть, 1 - можно смотреть.
            if (($menu_item['user_login'] !== '') && (
                ($menu_item['user_login'] == 0 && $this->user->user['id'] > 0) ||
                ($menu_item['user_login'] == 1 && $this->user->user['id'] == 0)
            )) {
                $is_visible = false;
            }
            if ($menu_item['user_access'] !== '' && $this->user->user[$menu_item['user_access']] != 1) {
                $is_visible = false;
            }
            // Если пункт меню видим, добавляем его в результат
            if ($is_visible) {
                // Очищаем данные с использованием Work::clean_field() (статический метод с расширенными проверками)
                $url_action = Work::clean_field($menu_item['url_action']);
                $name_action = Work::clean_field($menu_item['name_action']);
                $menu_items[$key] = [
                    'url'  => ($menu_item['action'] == $action ? null : $this->config['site_url'] . $url_action),
                    'name' => $this->lang['menu'][$name_action] ?? ucfirst($name_action)
                ];
            }
        }
        return $menu_items;
    }

    /**
     * @brief Формирование блока пользователя.
     *
     * @details Метод формирует массив данных для блока пользователя в зависимости от статуса авторизации.
     * Если пользователь не авторизован, формируется блок с ссылками на вход, восстановление пароля и регистрацию.
     * Если пользователь авторизован, формируется блок с приветствием, группой и аватаром.
     * Этот метод вызывается через редирект из публичного метода.
     *
     * @see PhotoRigma::Classes::Work_Template::template_user() Этот метод вызывается через редирект из публичного метода.
     * @see PhotoRigma::Classes::Work_Template::$lang Свойство, содержащее языковые строки.
     * @see PhotoRigma::Classes::Work_Template::$user Свойство, содержащее данные текущего пользователя.
     * @see PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
     * @see PhotoRigma::Classes::Work::validate_mime_type() Метод для проверки MIME-типа файла.
     *
     * @return array Массив с данными для блока пользователя.
     *
     * @throws RuntimeException Если объект пользователя не установлен или данные некорректны.
     *
     * @note Используется глобальная переменная $_SESSION для проверки статуса авторизации.
     * @todo Заменить использование $_SESSION на метод или свойство класса для инкапсуляции доступа к сессии.
     *
     * @example
     * @code
     * // Пример использования метода template_user()
     * $work = new Work($db, $config);
     * $work->set_user(new User());
     * $user_block = $work->template_user();
     * print_r($user_block);
     * @endcode
     */
    public const NO_USER_AVATAR = 'no_avatar.jpg'; // Объявление константы на уровне класса

    protected function _template_user_internal(): array
    {
        // Проверка, что объект пользователя установлен
        if ($this->user === null) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Объект пользователя не установлен | Решение: используйте set_user() для внедрения зависимости"
            );
        }
        // Определение пути к аватару
        $avatar_path = sprintf('%s/%s', $this->config['avatar_folder'], $this->user->user['avatar'] ?? self::NO_USER_AVATAR);
        // Проверка существования файла и его MIME-типа
        if ($_SESSION['login_id'] > 0) {
            $full_avatar_path = $this->config['site_dir'] . '/' . $avatar_path;
            if (!file_exists($full_avatar_path)) {
                $avatar_path = sprintf('%s/%s', $this->config['avatar_folder'], self::NO_USER_AVATAR);
            } else {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($full_avatar_path);
                if (!Work::validate_mime_type($mime_type)) {
                    log_in_file(
                        __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый MIME-тип аватара | Файл: {$full_avatar_path}"
                    );
                    $avatar_path = sprintf('%s/%s', $this->config['avatar_folder'], self::NO_USER_AVATAR);
                }
            }
        }
        // Проверка статуса авторизации через match
        $array_data = match ($_SESSION['login_id']) {
            0 => [
                'NAME_BLOCK'        => $this->lang['main']['user_block'],
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
                'L_HI_USER'  => $this->lang['main']['hi_user'] . ', ' . Work::clean_field($this->user->user['real_name']),
                'L_GROUP'    => $this->lang['main']['group'] . ': ' . $this->user->user['group'],
                'U_AVATAR'   => $this->config['site_url'] . $avatar_path,
            ],
        };
        return $array_data;
    }

    /**
     * @brief Генерирует массив статистических данных для шаблона.
     *
     * @details Метод выполняет запросы к базе данных для получения статистической информации о пользователях,
     * категориях, фотографиях, оценках и онлайн-пользователях. Результат формируется в виде ассоциативного массива,
     * который используется для отображения статистики на странице. Этот метод вызывается через редирект из публичного метода.
     *
     * @see PhotoRigma::Classes::Work_Template::template_stat() Этот метод вызывается через редирект из публичного метода.
     * @see PhotoRigma::Classes::Work_Template::$lang Свойство, содержащее языковые строки.
     * @see PhotoRigma::Classes::Work_Template::$db Свойство, содержащее объект для работы с базой данных.
     * @see PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
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
     *               - D_STAT_ONLINE: Список онлайн-пользователей.
     *
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных.
     *
     * @todo Вынести время за которое считать пользователей онлайн в настройки через БД.
     * @note Время онлайна жестко закодировано как 900 секунд (15 минут). Для изменения требуется ручное внесение изменений в код.
     */
    protected function _template_stat_internal(): array
    {
        $stat = [];
        // Получение статистики пользователей
        $this->db->select(
            [
                'COUNT(*) AS regist_user',
                'SUM(CASE WHEN `group` = 3 THEN 1 ELSE 0 END) AS user_admin',
                'SUM(CASE WHEN `group` = 2 THEN 1 ELSE 0 END) AS user_moder'
            ],
            TBL_USERS,
            []
        );
        $user_stats = $this->db->res_row();
        $stat['regist'] = $user_stats ? (int)$user_stats['regist_user'] : 0;
        $stat['user_admin'] = $user_stats ? (int)$user_stats['user_admin'] : 0;
        $stat['user_moder'] = $user_stats ? (int)$user_stats['user_moder'] : 0;
        // Получение статистики категорий
        $this->db->select(
            [
                'COUNT(*) AS category',
                'COUNT(DISTINCT CASE WHEN `category` = 0 THEN `user_upload` END) AS category_user'
            ],
            TBL_CATEGORY,
            ['where' => '`id` != 0']
        );
        $category_stats = $this->db->res_row();
        $stat['category'] = $category_stats ? (int)$category_stats['category'] : 0;
        $stat['category_user'] = $category_stats ? (int)$category_stats['category_user'] : 0;
        $stat['category'] += $stat['category_user'];
        // Получение статистики фотографий
        $this->db->select(['COUNT(*) AS photo_count'], TBL_PHOTO, []);
        $photo_stats = $this->db->res_row();
        $stat['photo'] = $photo_stats ? (int)$photo_stats['photo_count'] : 0;
        // Получение статистики оценок
        $this->db->select(['COUNT(*) AS rate_user'], TBL_RATE_USER, []);
        $rate_user_stats = $this->db->res_row();
        $stat['rate_user'] = $rate_user_stats ? (int)$rate_user_stats['rate_user'] : 0;
        $this->db->select(['COUNT(*) AS rate_moder'], TBL_RATE_MODER, []);
        $rate_moder_stats = $this->db->res_row();
        $stat['rate_moder'] = $rate_moder_stats ? (int)$rate_moder_stats['rate_moder'] : 0;
        // Получение онлайн-пользователей
        $this->db->select(
            ['id', 'real_name'],
            TBL_USERS,
            ['where' => '`date_last_activ` >= (CURRENT_TIMESTAMP - 900)']
        );
        $online_users_data = $this->db->res_arr();
        $stat['online'] = $online_users_data
            ? implode(', ', array_map(function ($user) {
                return sprintf(
                    '<a href="%s?action=profile&amp;subact=profile&amp;uid=%d" title="%s">%s</a>',
                    $this->config['site_url'],
                    $user['id'],
                    Work::clean_field($user['real_name']),
                    Work::clean_field($user['real_name'])
                );
            }, $online_users_data)) . '.'
            : $this->lang['main']['stat_no_online'];
        // Формирование результирующего массива
        return [
            'NAME_BLOCK'        => $this->lang['main']['stat_title'],
            'L_STAT_REGIST'     => $this->lang['main']['stat_regist'],
            'D_STAT_REGIST'     => $stat['regist'],
            'L_STAT_PHOTO'      => $this->lang['main']['stat_photo'],
            'D_STAT_PHOTO'      => $stat['photo'],
            'L_STAT_CATEGORY'   => $this->lang['main']['stat_category'],
            'D_STAT_CATEGORY'   => $stat['category'] . '(' . $stat['category_user'] . ')',
            'L_STAT_USER_ADMIN' => $this->lang['main']['stat_user_admin'],
            'D_STAT_USER_ADMIN' => $stat['user_admin'],
            'L_STAT_USER_MODER' => $this->lang['main']['stat_user_moder'],
            'D_STAT_USER_MODER' => $stat['user_moder'],
            'L_STAT_RATE_USER'  => $this->lang['main']['stat_rate_user'],
            'D_STAT_RATE_USER'  => $stat['rate_user'],
            'L_STAT_RATE_MODER' => $this->lang['main']['stat_rate_moder'],
            'D_STAT_RATE_MODER' => $stat['rate_moder'],
            'L_STAT_ONLINE'     => $this->lang['main']['stat_online'],
            'D_STAT_ONLINE'     => $stat['online']
        ];
    }

    /**
     * @brief Формирует список пользователей, загрузивших наибольшее количество изображений.
     *
     * @details Метод выполняет запрос к базе данных с использованием JOIN для получения списка пользователей,
     * которые загрузили наибольшее количество фотографий. Результат формируется в виде ассоциативного массива,
     * который используется для отображения в шаблоне. Если данные отсутствуют, добавляется запись "пустого" пользователя.
     * Этот метод вызывается через редирект из публичного метода.
     *
     * @see PhotoRigma::Classes::Work_Template::template_best_user() Этот метод вызывается через редирект из публичного метода.
     * @see PhotoRigma::Classes::Work_Template::$lang Свойство, содержащее языковые строки.
     * @see PhotoRigma::Classes::Work_Template::$db Свойство, содержащее объект для работы с базой данных.
     * @see PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
     *
     * @param int $best_user Количество лучших пользователей для вывода. Должно быть положительным целым числом.
     *
     * @return array Массив данных для вывода в шаблон:
     *               - NAME_BLOCK: Название блока.
     *               - L_USER_NAME: Подпись для имени пользователя.
     *               - L_USER_PHOTO: Подпись для количества фотографий.
     *               - user_url: Ссылка на профиль пользователя.
     *               - user_name: Имя пользователя.
     *               - user_photo: Количество загруженных фотографий.
     *
     * @throws InvalidArgumentException Если параметр $best_user не является положительным целым числом.
     *
     * @note Если запрос к базе данных не возвращает данных, добавляется запись "пустого" пользователя.
     */
    protected function _template_best_user_internal(int $best_user = 1): array
    {
        // Проверка параметра $best_user
        if (!is_int($best_user) || $best_user <= 0) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный параметр \$best_user | Ожидается положительное целое число, получено: {$best_user}"
            );
        }
        $array_data = [];
        // Блок получения данных через join()
        $this->db->join(
            ['users.id', 'users.real_name', 'COUNT(photos.id) AS user_photo'],
            TBL_USERS,
            [
                ['type' => 'INNER', 'table' => TBL_PHOTO, 'on' => 'users.id = photos.user_upload']
            ],
            [
                'group' => 'users.id',
                'order' => 'user_photo DESC',
                'limit' => $best_user
            ]
        );
        $user_data = $this->db->res_arr();
        // Проверка: $user_data может быть массивом (если данные есть) или false (если запрос не вернул данных).
        $array_data = ($user_data !== false)
            ? array_map(function ($current_user, $idx) {
                return [
                    'user_url'   => sprintf('%s?action=profile&amp;subact=profile&amp;uid=%d', $this->config['site_url'], $current_user['id']),
                    'user_name'  => Work::clean_field($current_user['real_name']),
                    'user_photo' => (int)$current_user['user_photo']
                ];
            }, $user_data, array_keys($user_data))
            : [[
                'user_url'   => null,
                'user_name'  => '---',
                'user_photo' => '-'
            ]];
        // Блок формирования метаданных для шаблона
        $array_data[0] = [
            'NAME_BLOCK'   => sprintf($this->lang['main']['best_user'], $best_user),
            'L_USER_NAME'  => $this->lang['main']['user_name'],
            'L_USER_PHOTO' => $this->lang['main']['best_user_photo']
        ];
        return $array_data;
    }
}
