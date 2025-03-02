<?php

/**
 * @file        include/user.php
 * @brief       Класс для управления пользователями, включая регистрацию, аутентификацию, управление профилем и хранение текущих настроек пользователя.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-02-20
 * @namespace   PhotoRigma\\Classes
 *
 * @details     Содержит класс `User` и интерфейс `User_Interface` с набором методов для работы с пользователями, а также используется для хранения всех данных о текущем пользователе.
 *              Остальное будет добавлено по мере документирования класса.
 *
 * @see         PhotoRigma::Classes::User Класс для работы с пользователями.
 * @see         PhotoRigma::Classes::User_Interface Интерфейс для работы с пользователями.
 * @see         PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
 * @see         index.php Файл, который подключает user.php.
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
 * @interface User_Interface
 * @brief Интерфейс для работы с пользователями и группами.
 *
 * @details Интерфейс определяет контракт для классов, реализующих методы добавления, обновления, удаления пользователей
 *          и групп, а также входа пользователя в систему. Реализация методов должна обеспечивать взаимодействие
 *          с базой данных и обработку ошибок.
 *
 * @callgraph
 *
 * @see PhotoRigma::Classes::User Класс, который реализует интерфейс.
 * @see PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
 *
 * @todo Внедрить ряд методов по работе с группами и пользователями (добавление, изменение, удаление, вход).
 * @note Используются константы с именами таблиц в базе данных.
 * @warning Класс зависит от глобального массива $_SESSION.
 *
 * Пример класса, реализующего интерфейс:
 * @code
 * class MyClass implements \PhotoRigma\Classes\User_Interface {
 *     public function add_new_user(array $user_data): void {}
 *     public function update_user_data(int $user_id, array $user_data): void {}
 *     public function delete_user(int $user_id): void {}
 *     public function add_new_group(array $group_data): void {}
 *     public function update_group_data(int $group_id, array $group_data): void {}
 *     public function delete_group(int $group_id): void {}
 *     public function login_user(string $login, string $password): void {}
 * }
 * @endcode
 */
interface User_Interface
{
    /**
    * @brief Удаляет ключ из указанного свойства объекта (user или session).
    *        Метод является частью контракта интерфейса для управления данными пользователя и сессии при взаимодействии с пользователями.
    *
    * @details Этот метод выполняет следующие действия:
    *          - Проверяет, какой массив изменять (`$this->user` или `$this->session`).
    *          - Удаляет ключ, если он существует.
    *          - Выбрасывает исключение, если указано недопустимое свойство.
    *
    * @callgraph
    *
    * @see PhotoRigma::Classes::User::unset_property_key Реализация метода в классе User.
    *
    * @param string $name Имя свойства, из которого удаляется ключ ('user' или 'session').
    * @param string|int $key Ключ, который нужно удалить. Может быть строкой или целым числом.
    *
    * @throws InvalidArgumentException Если указано недопустимое свойство.
    *                                  Пример сообщения: "Недопустимое свойство | Свойство: [значение]."
    *                                  Условия выброса: Если `$name` не равно 'user' или 'session'.
    *
    * @note Метод изменяет только свойства 'user' и 'session'.
    *
    * @warning Не используйте этот метод для удаления ключей из других свойств.
    *
    * Пример вызова метода:
    * @code
    * $user = new \PhotoRigma\Classes\User();
    * $user->unset_property_key('user', 'email');
    * @endcode
    */
    public function unset_property_key(string $name, string|int $key): void;

    /**
    * @brief Добавляет нового пользователя в базу данных, выполняя валидацию входных данных.
    *
    * @details Этот метод выполняет следующие действия:
    *          - Проверяет корректность входных данных с использованием `$work->check_input()`.
    *          - Проверяет уникальность логина, email и имени пользователя в базе данных.
    *          - Добавляет нового пользователя в базу данных, назначая ему группу по умолчанию.
    *          - При возникновении ошибок сохраняет их в сессии и перенаправляет пользователя на указанный URL.
    *          - Использует CAPTCHA для защиты от автоматической регистрации.
    *
    * @callgraph
    *
    * @see PhotoRigma::Classes::User::add_new_user Реализация метода в классе User.
    *
    * @param array $post_data Массив данных из формы ($_POST), содержащий новые значения для полей пользователя:
    *                         - string $login: Логин пользователя (должен соответствовать регулярному выражению REG_LOGIN).
    *                         - string $password: Пароль пользователя (не должен быть пустым).
    *                         - string $re_password: Повтор пароля (должен совпадать с $password).
    *                         - string $email: Email пользователя (должен соответствовать регулярному выражению REG_EMAIL).
    *                         - string $real_name: Реальное имя пользователя (должно соответствовать регулярному выражению REG_NAME).
    *                         - string $captcha: Значение CAPTCHA (должно быть числом).
    * @param Work $work Объект класса `Work`, предоставляющий вспомогательные методы для проверки входных данных.
    * @param string $redirect_url URL для перенаправления пользователя в случае возникновения ошибок.
    *
    * @return int ID нового пользователя, если регистрация успешна, или 0 в случае ошибки.
    *
    * @throws RuntimeException Если группа по умолчанию не найдена в базе данных.
    *                           Пример сообщения: "Не удалось получить данные группы по умолчанию."
    *
    * @note Используется CAPTCHA для защиты от автоматической регистрации.
    *
    * @warning Метод зависит от корректной конфигурации базы данных и таблицы групп.
    *
    * Пример использования метода:
    * @code
    * $user = new \PhotoRigma\Classes\User();
    * $work = new \PhotoRigma\Classes\Work();
    * $redirectUrl = '/register/error';
    * $userId = $user->add_new_user($_POST, $work, $redirectUrl);
    * if ($userId > 0) {
    *     echo "Пользователь успешно зарегистрирован! ID: {$userId}";
    * } else {
    *     echo "Ошибка регистрации.";
    * }
    * @endcode
    */
    public function add_new_user(array $post_data, Work $work, string $redirect_url): int;

    /**
    * @brief Обновляет данные существующего пользователя, включая пароль, email, имя и аватар.
    *
    * @details Этот метод выполняет следующие действия:
    *          - Проверяет существование пользователя с указанным `$user_id` в базе данных.
    *          - Валидирует и обновляет пароль через `password_verify()` и `password_hash()`.
    *          - Проверяет уникальность email и имени пользователя в базе данных.
    *          - Обрабатывает загрузку нового аватара через метод `edit_avatar()`.
    *          - Удаляет старый аватар при необходимости.
    *          - Обновляет данные пользователя в базе данных.
    *          Этот метод является частью контракта, который должны реализовать классы, использующие интерфейс.
    *
    * @callgraph
    *
    * @see PhotoRigma::Classes::User::update_user_data() Метод реализации в классе User.
    * @see PhotoRigma::Classes::Work::check_input() Метод для проверки корректности входных данных.
    * @see PhotoRigma::Classes::Work Класс, передаваемый в качестве объекта аргументом.
    *
    * @param int $user_id Идентификатор пользователя, данные которого необходимо обновить.
    *                     Должен быть положительным целым числом и существовать в базе данных.
    * @param array $post_data Массив данных из формы ($_POST), содержащий новые значения для полей пользователя:
    *                         - 'password' (string): Текущий пароль пользователя (обязательно для изменения пароля).
    *                         - 'edit_password' (string): Новый пароль пользователя (необязательно).
    *                         - 're_password' (string): Повторный ввод нового пароля (должен совпадать с 'edit_password').
    *                         - 'email' (string): Новый email пользователя (должен соответствовать регулярному выражению REG_EMAIL).
    *                         - 'real_name' (string): Новое имя пользователя (должно быть строкой).
    *                         Все поля проходят валидацию перед использованием.
    * @param array $files_data Массив данных загруженного файла ($_FILES), содержащий информацию об аватаре:
    *                          - 'file_avatar' (array): Информация о загруженном файле (необязательно).
    *                          Если файл не передан или не проходит валидацию, аватар остается без изменений.
    * @param int $max_size Максимальный размер файла для аватара в байтах. Определяется на основе конфигурации
    *                      приложения и ограничений PHP (например, post_max_size). Если размер файла превышает
    *                      это значение, загрузка аватара отклоняется. Пример: 5 * 1024 * 1024 (5 MB).
    * @param Work $work Экземпляр класса `Work`, предоставляющий вспомогательные методы для работы с данными:
    *                   - `check_input()`: Валидация входных данных.
    *                   Класс используется для выполнения вспомогательных операций внутри метода.
    *
    * @return int Количество затронутых строк в базе данных после выполнения обновления.
    *             Возвращается 0, если данные не изменились или запрос завершился ошибкой.
    *
    * @throws \RuntimeException Выбрасывается исключение если пользователь с указанным `$user_id` не найден в базе данных.
    *
    * @note Метод зависит от корректной конфигурации базы данных.
    *
    * @warning Не используйте этот метод для массового обновления данных.
    *
    * Пример использования метода:
    * @code
    * $user = new \PhotoRigma\Classes\User();
    * $userId = 1;
    * $maxSize = 5 * 1024 * 1024; // 5 MB
    * $work = new \PhotoRigma\Classes\Work();
    * $affectedRows = $user->update_user_data($userId, $_POST, $_FILES, $maxSize, $work);
    * if ($affectedRows > 0) {
    *     echo "Данные успешно обновлены!";
    * } else {
    *     echo "Ошибка при обновлении данных.";
    * }
    * @endcode
    */
    public function update_user_data(int $user_id, array $post_data, array $files_data, int $max_size, Work $work): int;

    /**
     * Удаление пользователя.
     *
     * @param int $user_id Идентификатор пользователя.
     * @return void
     */
    public function delete_user(int $user_id): void;

    /**
     * Добавление новой группы.
     *
     * @param array $group_data Данные новой группы.
     * @return void
     */
    public function add_new_group(array $group_data): void;

    /**
     * Обновление данных группы.
     *
     * @param int $group_id Идентификатор группы.
     * @param array $group_data Новые данные группы.
     * @return void
     */
    public function update_group_data(int $group_id, array $group_data): void;

    /**
     * Удаление группы.
     *
     * @param int $group_id Идентификатор группы.
     * @return void
     */
    public function delete_group(int $group_id): void;

    /**
     * @brief Проверяет данные пользователя для входа в систему с "мягким" обновлением паролей на новый формат хранения.
     *
     * @details Этот метод выполняет следующие действия:
     *          - Проверяет корректность входных данных (логин и пароль) с использованием `$work->check_input()`.
     *          - Ищет пользователя в базе данных по логину.
     *          - Проверяет пароль через `password_verify()` и, при необходимости, через `md5`.
     *          - Если пароль хранится в старом формате (md5), он обновляется до формата `password_hash()`.
     *          - Возвращает ID пользователя или 0 в случае ошибки.
     *
     * @callgraph
     *
     * @see PhotoRigma::Classes::User::login_user Метод реализации в классе User.
     * @see PhotoRigma::Classes::Work::check_input() Метод для проверки правильности входных данных.
     * @see PhotoRigma::Include::log_in_file() Функция логирования событий.
     *
     * @param array $post Массив данных из формы ($_POST), содержащий ключи:
     *                    - string $login: Логин пользователя (должен соответствовать регулярному выражению REG_LOGIN).
     *                    - string $password: Пароль пользователя (не должен быть пустым).
     * @param Work $work Объект класса `Work`, предоставляющий вспомогательные методы для проверки входных данных.
     * @param string $redirect_url URL для перенаправления пользователя в случае возникновения ошибок.
     *
     * @return int ID пользователя, если авторизация успешна, или 0 в случае ошибки.
     *
     * @note Поддерживается совместимость со старым форматом хранения паролей (md5).
     *
     * @warning Метод зависит от корректной конфигурации базы данных.
     *
     * Пример использования метода:
     * @code
     * $user = new \PhotoRigma\Classes\User();
     * $work = new \PhotoRigma\Classes\Work();
     * $redirectUrl = '/login/error';
     * $userId = $user->login_user($_POST, $work, $redirectUrl);
     * if ($userId > 0) {
     *     echo "Вход выполнен успешно! ID пользователя: {$userId}";
     * } else {
     *     echo "Ошибка входа.";
     * }
     * @endcode
     */
    public function login_user(array $post, Work $work, string $redirect_url): int;

    /**
    * @brief Генерирует или возвращает CSRF-токен для защиты от межсайтовой подделки запросов.
    *        Метод является частью контракта интерфейса для обеспечения безопасности форм при взаимодействии с пользователями.
    *
    * @details Этот метод выполняет следующие действия:
    *          - Если CSRF-токен отсутствует в сессии, он генерируется с использованием `random_bytes(32)` (64 символа).
    *          - Если токен уже существует, он возвращается без повторной генерации.
    *          - Токен хранится в сессии и используется для защиты форм от CSRF-атак.
    *
    * @callgraph
    *
    * @see PhotoRigma::Classes::User::csrf_token() Реализация метода в классе User.
    *
    * @return string CSRF-токен длиной 64 символа, сгенерированный с использованием безопасного источника случайных данных.
    *
    * @note Токен хранится в сессии и используется для защиты форм от CSRF-атак.
    *
    * @warning Не используйте этот метод для генерации токенов, если сессия недоступна.
    *
    * Пример вызова метода:
    * @code
    * $user = new \PhotoRigma\Classes\User();
    * $csrfToken = $user->csrf_token();
    * echo "CSRF Token: {$csrfToken}";
    * @endcode
    */
    public function csrf_token(): string;
}

/**
 * @class User
 * @brief Класс по работе с пользователями и хранению данных о текущем пользователе.
 *
 * @details Класс содержит методы для работы с данными пользователей, включая загрузку, обработку и сохранение данных.
 *          Идентификация пользователя происходит по данным из глобального массива $_SESSION.
 *          Также класс содержит ряд "заглушек" для будущей реализации методов по работе с пользователями и группами
 *          (добавление, изменение, удаление, вход). Этот класс реализует интерфейс `User_Interface`, но все методы
 *          пока являются заглушками.
 *
 * @implements PhotoRigma::Classes::User_Interface Интерфейс для работы с пользователями и группами.
 *
 * @callergraph
 * @callgraph
 *
 * @see PhotoRigma::Classes::User_Interface Интерфейс, который реализует класс.
 * @see PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
 *
 * @todo Внедрить ряд методов по работе с группами и пользователями (добавление, изменение, удаление, вход).
 * @note Используются константы с именами таблиц в базе данных.
 * @warning Класс зависит от глобального массива $_SESSION.
 *
 * Пример использования класса:
 * @code
 * $db = new \PhotoRigma\Classes\Database();
 * $user = new \PhotoRigma\Classes\User($db, $_SESSION);
 * @endcode
 */
class User implements User_Interface
{
    // Свойства
    private array $user = []; ///< Массив, содержащий все данные о текущем пользователе.
    private Database_Interface $db; ///< Объект для работы с базой данных.
    private array $session = []; ///< Массив, привязанный к глобальному массиву $_SESSION
    private array $user_right_fields = []; ///< Массив с полями наименований прав доступа
    public const DEFAULT_AVATAR = 'no_avatar.jpg'; ///< Константа для значения аватара по умолчанию


    /**
     * @brief Конструктор класса.
     *
     * @details Этот метод вызывается автоматически при создании нового объекта класса.
     *          Используется для подключения объекта Database_Interface и глобального массива $_SESSION.
     *          После подключения выполняется инициализация и наполнение данных о текущем пользователе.
     *
     * @callgraph
     *
     * @see PhotoRigma::Classes::User::$db Свойство, содержащее объект для работы с базой данных.
     * @see PhotoRigma::Classes::User::$session Свойство, содержащее ссылку на массив сессии ($_SESSION).
     * @see PhotoRigma::Classes::User::initialize_user() Метод, выполняющий инициализацию данных о пользователе.
     * @see PhotoRigma::Classes::User::all_right_fields() Наполнение массива именами полей прав пользователей и групп.
     *
     * @param Database_Interface $db Объект для работы с базой данных:
     *                               - Должен реализовывать интерфейс Database_Interface.
     *                               - Используется для выполнения запросов к базе данных.
     * @param array &$session Ссылка на массив сессии ($_SESSION):
     *                        - Должен быть ассоциативным массивом.
     *                        - Используется для хранения данных текущей сессии пользователя.
     *
     * @throws InvalidArgumentException Если переданы некорректные параметры:
     *                                  - Параметр $db не реализует интерфейс Database_Interface.
     *                                  - Параметр $session не является массивом или пуст.
     *
     * Пример создания объекта:
     * @code
     * // Создание объекта базы данных
     * $db = new \PhotoRigma\Classes\Database();
     *
     * // Создание объекта класса User с передачей $_SESSION напрямую
     * $user = new \PhotoRigma\Classes\User($db, $_SESSION);
     * @endcode
     */
    public function __construct(Database_Interface $db, array &$session)
    {
        if (!is_array($session)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректные данные сессии | Проверьте, что массив сессии является корректным"
            );
        }
        $this->db = $db;
        $this->session = &$session;
        $this->all_right_fields();
        $this->initialize_user();
    }

    /**
     * @brief Получает значение приватного свойства.
     *
     * @details Метод позволяет получить доступ к приватным свойствам `$user` и `$session`.
     * Если запрашиваемое свойство не существует, выбрасывается исключение.
     * Доступ разрешён только к свойствам `$user` и `$session`.
     *
     * @callgraph
     *
     * @see User::$user Свойство, содержащее данные о текущем пользователе.
     * @see User::$session Свойство, привязанное к глобальному массиву $_SESSION.
     *
     * @param string $name Имя свойства:
     *                     - Допустимые значения: 'user', 'session'.
     *                     - Если указано другое имя, выбрасывается исключение.
     *
     * @return array Значение запрашиваемого свойства (`$user` или `$session`).
     *
     * @throws InvalidArgumentException Если запрашиваемое свойство не существует.
     *
     * @note Этот метод предназначен только для доступа к свойствам `$user` и `$session`.
     * @warning Не используйте этот метод для доступа к другим свойствам, так как это вызовет исключение.
     *
     * @todo Добавить скрытый "шифрованный ключ" к массиву `$session` для повышения безопасности.
     *
     * Пример использования метода:
     * @code
     * $user = new User($db, $_SESSION);
     * echo $user->user['name']; // Выведет: Имя пользователя (если установлено)
     * echo $user->session['user_id']; // Выведет: ID пользователя из сессии
     * @endcode
     */
    public function &__get(string $name): array
    {
        $result = null;

        switch ($name) {
            case 'user':
                $result = &$this->user;
                break;
            case 'session':
                $result = &$this->session;
                break;
            default:
                throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не существует | Получено: '{$name}'"
                );
        }

        return $result;
    }

    /**
     * @brief Устанавливает значение приватного свойства.
     *
     * @details Метод позволяет изменить значение приватных свойств `$user` и `$session`.
     * Если переданное имя свойства не соответствует допустимым значениям, выбрасывается исключение.
     * Все изменения логируются.
     *
     * @callgraph
     *
     * @see PhotoRigma::Classes::User::$user Свойство, содержащее данные о текущем пользователе.
     * @see PhotoRigma::Classes::User::$session Свойство, привязанное к глобальному массиву $_SESSION.
     * @see PhotoRigma::Include::log_in_file() Функция для логирования ошибок и изменений.
     *
     * @param string $name Имя свойства:
     *                     - Допустимые значения: 'user', 'session'.
     *                     - Если указано другое имя, выбрасывается исключение.
     * @param array $value Новое значение свойства:
     *                     - Должен быть массивом.
     *
     * @throws InvalidArgumentException Если переданное имя свойства или значение некорректны.
     *
     * @note Этот метод предназначен только для изменения свойств `$user` и `$session`.
     * @warning Не используйте этот метод для изменения других свойств, так как это вызовет исключение.
     *
     * @todo Добавить скрытый "шифрованный ключ" к массиву `$session` для повышения безопасности.
     *
     * Пример использования метода:
     * @code
     * $user = new User($db, $_SESSION);
     * $user->user = ['name' => 'John Doe']; // Установит новое значение для $user
     * $user->session = ['user_id' => 123]; // Установит новое значение для $session
     * @endcode
     */
    public function __set(string $name, $value): void
    {
        // Разрешаем изменять только свойства 'user' и 'session'
        if (!in_array($name, ['user', 'session'])) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Несуществующее свойство | Свойство: {$name}"
            );
        }

        // Проверка, что значение является массивом
        if (!is_array($value)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Некорректный тип значения | Значение должно быть массивом"
            );
        }

        // Получаем текущее значение свойства
        $current_value = $this->$name;

        // Обновляем только те ключи, которые переданы в $value
        foreach ($value as $key => $val) {
            $current_value[$key] = $val;
        }

        // Логируем изменения
        $updated_keys = array_intersect_key($value, $this->$name); // Ключи, которые были обновлены
        $added_keys = array_diff_key($value, $this->$name); // Новые ключи

        if (!empty($updated_keys)) {
            \PhotoRigma\Include\log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Обновление свойства '{$name}' | Изменённые ключи: " . json_encode($updated_keys)
            );
        }
        if (!empty($added_keys)) {
            \PhotoRigma\Include\log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                "Добавление в свойство '{$name}' | Новые ключи: " . json_encode($added_keys)
            );
        }

        // Обновляем свойство
        $this->$name = $current_value;
    }

    /**
     * @brief Удаляет ключ из указанного свойства объекта (user или session).
     *
     * @details Этот метод выполняет следующие действия:
     *          - Проверяет, какой массив изменять (`$this->user` или `$this->session`).
     *          - Удаляет ключ, если он существует.
     *          - Выбрасывает исключение, если указано недопустимое свойство.
     *
     *          Этот метод является публичным и предназначен для прямого использования извне.
     *
     * @see PhotoRigma::Classes::User::$user Свойство класса User, содержащее данные пользователя.
     * @see PhotoRigma::Classes::User::$session Свойство класса User, содержащее данные сессии.
     *
     * @param string $name Имя свойства, из которого удаляется ключ ('user' или 'session').
     * @param string|int $key Ключ, который нужно удалить. Может быть строкой или целым числом.
     *
     * @throws InvalidArgumentException Если указано недопустимое свойство.
     *                                  Пример сообщения: "Недопустимое свойство | Свойство: [значение]."
     *
     * @note Метод изменяет только свойства 'user' и 'session'.
     *
     * @warning Не используйте этот метод для удаления ключей из других свойств.
     *
     * Пример вызова метода:
     * @code
     * $user = new \PhotoRigma\Classes\User();
     * $user->unset_property_key('user', 'email');
     * @endcode
     */
    public function unset_property_key(string $name, string|int $key): void
    {
        // Проверяем, какой массив нужно изменить
        switch ($name) {
            case 'user':
                if (isset($this->user[$key])) {
                    unset($this->user[$key]);
                }
                break;

            case 'session':
                if (isset($this->session[$key])) {
                    unset($this->session[$key]);
                }
                break;

            default:
                throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | " .
                    "Недопустимое свойство | Свойство: {$name}"
                );
        }
    }

    /**
    * @brief Добавляет нового пользователя в базу данных, выполняя валидацию входных данных.
    *
    * @details Этот метод выполняет следующие действия:
    *          - Проверяет корректность входных данных с использованием `$work->check_input()`.
    *          - Проверяет уникальность логина, email и имени пользователя в базе данных.
    *          - Добавляет нового пользователя в базу данных, назначая ему группу по умолчанию.
    *          - При возникновении ошибок сохраняет их в сессии и перенаправляет пользователя на указанный URL.
    *          - Использует CAPTCHA для защиты от автоматической регистрации.
    *
    * @callgraph
    *
    * @see PhotoRigma::Classes::User::$session Свойство класса User, связанное с $_SESSION.
    * @see PhotoRigma::Classes::Work::check_input() Метод для проверки правильности входных данных.
    * @see PhotoRigma::Classes::Work Класс, объект которого передается в аргументах.
    * @see PhotoRigma::Include::log_in_file() Функция логирования ошибок.
    *
    * @param array $post_data Массив данных из формы ($_POST), содержащий новые значения для полей пользователя:
    *                         - string $login: Логин пользователя (должен соответствовать регулярному выражению REG_LOGIN).
    *                         - string $password: Пароль пользователя (не должен быть пустым).
    *                         - string $re_password: Повтор пароля (должен совпадать с $password).
    *                         - string $email: Email пользователя (должен соответствовать регулярному выражению REG_EMAIL).
    *                         - string $real_name: Реальное имя пользователя (должно соответствовать регулярному выражению REG_NAME).
    *                         - string $captcha: Значение CAPTCHA (должно быть числом).
    * @param object $work Объект класса `Work`, предоставляющий вспомогательные методы для проверки входных данных.
    * @param string $redirect_url URL для перенаправления пользователя в случае возникновения ошибок.
    *
    * @return int ID нового пользователя, если регистрация успешна, или 0 в случае ошибки.
    *
    * @throws RuntimeException Если группа по умолчанию не найдена в базе данных.
    *                           Пример сообщения: "Не удалось получить данные группы по умолчанию."
    *
    * @note Используется CAPTCHA для защиты от автоматической регистрации.
    *
    * @warning Метод зависит от корректной конфигурации базы данных и таблицы групп.
    *
    * Пример использования метода:
    * @code
    * $user = new \PhotoRigma\Classes\User();
    * $work = new \PhotoRigma\Classes\Work();
    * $redirectUrl = '/register/error';
    * $userId = $user->add_new_user($_POST, $work, $redirectUrl);
    * if ($userId > 0) {
    *     echo "Пользователь успешно зарегистрирован! ID: {$userId}";
    * } else {
    *     echo "Ошибка регистрации.";
    * }
    * @endcode
    */
    public function add_new_user(array $post_data, Work $work, string $redirect_url): int
    {
        // === 1. Валидация входных данных ===
        $error = false;
        $field_validators = [
            'login' => ['isset' => true, 'empty' => true, 'regexp' => REG_LOGIN],
            'password' => ['isset' => true, 'empty' => true],
            're_password' => ['isset' => true, 'empty' => true],
            'email' => ['isset' => true, 'empty' => true, 'regexp' => REG_EMAIL],
            'real_name' => ['isset' => true, 'empty' => true, 'regexp' => REG_NAME],
            'captcha' => ['isset' => true, 'empty' => true, 'regexp' => '/^[0-9]+$/']
        ];

        foreach ($field_validators as $field => $options) {
            if (!$work->check_input('_POST', $field, $options)) {
                $this->session['error'][$field]['if'] = true;
                $this->session['error'][$field]['text'] = match ($field) {
                    'login' => $work->lang['profile']['error_login'],
                    'password' => $work->lang['profile']['error_password'],
                    're_password' => $work->lang['profile']['error_re_password'],
                    'email' => $work->lang['profile']['error_email'],
                    'real_name' => $work->lang['profile']['error_real_name'],
                    'captcha' => $work->lang['profile']['error_captcha'],
                    default => 'Unknown error',
                };
                $error = true;
            } else {
                // Дополнительные проверки для re_password и captcha
                if ($field === 're_password' && $post_data['re_password'] !== $post_data['password']) {
                    $this->session['error']['re_password']['if'] = true;
                    $this->session['error']['re_password']['text'] = $work->lang['profile']['error_re_password'];
                    $error = true;
                }
                if ($field === 'captcha' && !password_verify($post_data['captcha'], $this->session['captcha'])) {
                    $this->session['error']['captcha']['if'] = true;
                    $this->session['error']['captcha']['text'] = $work->lang['profile']['error_captcha'];
                    $error = true;
                }
            }
        }
        $this->unset_property_key('session', 'captcha');

        // === 2. Проверка уникальности login, email и real_name ===
        $this->db->select(
            'COUNT(CASE WHEN `login` = :login THEN 1 END) as login_count,
             COUNT(CASE WHEN `email` = :email THEN 1 END) as email_count,
             COUNT(CASE WHEN `real_name` = :real_name THEN 1 END) as real_count',
            TBL_USERS,
            [
                'params' => [
                    ':login' => $post_data['login'],
                    ':email' => $post_data['email'],
                    ':real_name' => $post_data['real_name']
                ]
            ]
        );
        $unique_check_result = $this->db->res_row();

        if ($unique_check_result) {
            if ($unique_check_result['login_count'] > 0) {
                $error = true;
                $this->session['error']['login']['if'] = true;
                $this->session['error']['login']['text'] = $work->lang['profile']['error_login_exists'];
            }
            if ($unique_check_result['email_count'] > 0) {
                $error = true;
                $this->session['error']['email']['if'] = true;
                $this->session['error']['email']['text'] = $work->lang['profile']['error_email_exists'];
            }
            if ($unique_check_result['real_count'] > 0) {
                $error = true;
                $this->session['error']['real_name']['if'] = true;
                $this->session['error']['real_name']['text'] = $work->lang['profile']['error_real_name_exists'];
            }
        }

        // === 3. Если возникли ошибки, сохраняем их в сессии и завершаем ===
        if ($error) {
            return 0; // Возвращаем 0, чтобы обозначить ошибку
        }

        // === 4. Добавление нового пользователя в базу данных ===
        $query = [
            'login' => $post_data['login'],
            'password' => password_hash($post_data['re_password'], PASSWORD_BCRYPT),
            'email' => $post_data['email'],
            'real_name' => $post_data['real_name'],
            'group_id' => DEFAULT_GROUP
        ];

        // Получение данных группы по умолчанию
        $this->db->select('*', TBL_GROUP, [
            'where' => '`id` = :group_id',
            'params' => [':group_id' => DEFAULT_GROUP]
        ]);
        $group_data = $this->db->res_row();

        if (!$group_data) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные группы по умолчанию"
            );
        }

        // Добавляем данные группы в массив для вставки нового пользователя
        foreach ($group_data as $key => $value) {
            if ($key != 'id' && $key != 'name') {
                $query[$key] = $value;
            }
        }

        // Формируем плоский массив плейсхолдеров и ассоциативный массив для вставки
        $insert_data = array_map(fn ($key) => "`$key`", array_keys($query)); // Экранируем имена столбцов
        $placeholders = array_map(fn ($key) => ":$key", array_keys($query)); // Формируем плейсхолдеры
        $params = array_combine(
            array_map(fn ($key) => ":$key", array_keys($query)), // Добавляем префикс ':' к каждому ключу
            array_values($query) // Значения остаются без изменений
        );

        // Вставка нового пользователя в базу данных
        $this->db->insert(
            array_combine($insert_data, $placeholders), // Передаём ассоциативный массив (имена столбцов => плейсхолдеры)
            TBL_USERS,
            '',
            ['params' => $params] // Передаём преобразованный массив параметров
        );

        $new_user = $this->db->get_last_insert_id();

        // === 5. Возвращаем результат ===
        return $new_user; // ID нового пользователя
    }

    /**
    * @brief Обновляет данные существующего пользователя, включая пароль, email, имя и аватар.
    *
    * @details Этот публичный метод выполняет следующие действия:
    *          - Проверяет существование пользователя с указанным `$user_id` в базе данных.
    *          - Валидирует и обновляет пароль через `password_verify()` и `password_hash()`.
    *          - Проверяет уникальность email и имени пользователя в базе данных.
    *          - Обрабатывает загрузку нового аватара через метод `edit_avatar()`.
    *          - Удаляет старый аватар при необходимости.
    *          - Обновляет данные пользователя в базе данных через `$this->db->update()`.
    *          Метод предназначен для прямого использования извне.
    *
    * @callgraph
    *
    * @see PhotoRigma::Classes::Work::check_input() Метод для проверки корректности входных данных.
    * @see PhotoRigma::Classes::Work Класс, передаваемый в качестве объекта аргументом.
    * @see PhotoRigma::Classes::User::edit_avatar() Приватный метод для обработки загрузки аватара.
    *
    * @param int $user_id Идентификатор пользователя, данные которого необходимо обновить.
    *                     Должен быть положительным целым числом и существовать в базе данных.
    * @param array $post_data Массив данных из формы ($_POST), содержащий новые значения для полей пользователя:
    *                         - 'password' (string): Текущий пароль пользователя (обязательно для изменения пароля).
    *                         - 'edit_password' (string): Новый пароль пользователя (необязательно).
    *                         - 're_password' (string): Повторный ввод нового пароля (должен совпадать с 'edit_password').
    *                         - 'email' (string): Новый email пользователя (должен соответствовать регулярному выражению REG_EMAIL).
    *                         - 'real_name' (string): Новое имя пользователя (должно быть строкой).
    *                         Все поля проходят валидацию перед использованием.
    * @param array $files_data Массив данных загруженного файла ($_FILES), содержащий информацию об аватаре:
    *                          - 'file_avatar' (array): Информация о загруженном файле (необязательно).
    *                          Если файл не передан или не проходит валидацию, аватар остается без изменений.
    * @param int $max_size Максимальный размер файла для аватара в байтах. Определяется на основе конфигурации
    *                      приложения и ограничений PHP (например, post_max_size). Если размер файла превышает
    *                      это значение, загрузка аватара отклоняется. Пример: 5 * 1024 * 1024 (5 MB).
    * @param Work $work Экземпляр класса `Work`, предоставляющий вспомогательные методы для работы с данными:
    *                   - `check_input()`: Валидация входных данных.
    *                   Класс используется для выполнения вспомогательных операций внутри метода.
    *
    * @return int Количество затронутых строк в базе данных после выполнения обновления.
    *             Возвращается 0, если данные не изменились или запрос завершился ошибкой.
    *
    * @throws \RuntimeException Выбрасывается исключение если пользователь с указанным `$user_id` не найден в базе данных.
    *
    * @note Метод зависит от корректной конфигурации базы данных.
    *
    * @warning Не используйте этот метод для массового обновления данных.
    *
    * Пример использования метода:
    * @code
    * $user = new \PhotoRigma\Classes\User();
    * $userId = 1;
    * $maxSize = 5 * 1024 * 1024; // 5 MB
    * $work = new \PhotoRigma\Classes\Work();
    * $affectedRows = $user->update_user_data($userId, $_POST, $_FILES, $maxSize, $work);
    * if ($affectedRows > 0) {
    *     echo "Данные успешно обновлены!";
    * } else {
    *     echo "Ошибка при обновлении данных.";
    * }
    * @endcode
    */
    public function update_user_data(int $user_id, array $post_data, array $files_data, int $max_size, Work $work): int
    {
        // Проверяем, существует ли пользователь с указанным ID
        $this->db->select('*', TBL_USERS, [
            'where' => '`id` = :user_id',
            'params' => [':user_id' => $user_id]
        ]);
        $user_data = $this->db->res_row();
        if (!$user_data) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Пользователь не найден | ID: {$user_id}"
            );
        }
        $new_user_data = [];
        // === ПРОВЕРКА ПАРОЛЯ ===
        if ($user_id !== $this->session['login_id'] || (
            $work->check_input('_POST', 'password', ['isset' => true, 'empty' => true]) &&
            password_verify($post_data['password'], $user_data['password'])
        )) {
            if ($work->check_input('_POST', 'edit_password', ['isset' => true, 'empty' => true])) {
                $new_user_data['password'] = $post_data['re_password'] !== $post_data['edit_password']
                    ? $user_data['password']
                    : password_hash($post_data['re_password'], PASSWORD_BCRYPT);
            } else {
                $new_user_data['password'] = $user_data['password'];
            }
        }
        // === ПРОВЕРКА EMAIL И REAL_NAME ===
        if ($work->check_input('_POST', 'email', ['isset' => true, 'empty' => true, 'regexp' => REG_EMAIL])) {
            $filtered_email = filter_var($post_data['email'], FILTER_SANITIZE_EMAIL);
            $this->db->select('SUM(`email` = :email) as `email_count`, SUM(`real_name` = :real_name) as `real_count`', TBL_USERS, [
                'where' => '`id` != :user_id',
                'params' => [':user_id' => $user_id, ':email' => $filtered_email, ':real_name' => $post_data['real_name']]
            ]);
            $counts = $this->db->res_row();
            $new_user_data['email'] = $counts && isset($counts['email_count']) && $counts['email_count'] > 0
                ? $user_data['email']
                : $filtered_email;
            $new_user_data['real_name'] = $counts && isset($counts['real_count']) && $counts['real_count'] > 0
                ? $user_data['real_name']
                : $post_data['real_name'];
        } else {
            $new_user_data['email'] = $user_data['email'];
            $new_user_data['real_name'] = $user_data['real_name'];
        }
        // === ОБРАБОТКА АВАТАРА ===
        $delete_old_avatar = false; // Флаг для удаления старого аватара
        if (!$work->check_input('_POST', 'delete_avatar', ['isset' => true, 'empty' => true]) || $post_data['delete_avatar'] !== 'true') {
            $new_user_data['avatar'] = $this->edit_avatar($files_data, $max_size, $work);
            // Проверяем, нужно ли удалить старый аватар
            if ($user_data['avatar'] !== static::DEFAULT_AVATAR && $user_data['avatar'] !== $new_user_data['avatar']) {
                $delete_old_avatar = true; // Устанавливаем флаг на удаление
            }
        } else {
            $old_avatar_path = $work->config['site_dir'] . $work->config['avatar_folder'] . '/' . $user_data['avatar'];
            if ($user_data['avatar'] !== static::DEFAULT_AVATAR && file_exists($old_avatar_path) && is_writable($old_avatar_path)) {
                unlink($old_avatar_path); // Безопасное удаление старого аватара
            }
            $new_user_data['avatar'] = static::DEFAULT_AVATAR;
        }
        // === ОБНОВЛЕНИЕ ДАННЫХ В БАЗЕ ===
        // === Формирование данных для обновления с плейсхолдерами ===
        $update_data = [];
        $params = [];
        foreach ($new_user_data as $field => $value) {
            $placeholder = ":update_$field"; // Уникальный плейсхолдер для каждого поля
            $update_data[$field] = $placeholder; // Формируем ассоциативный массив для update
            $params[$placeholder] = $value; // Добавляем значение в параметры
        }

        // Добавляем параметр для WHERE
        $params[':user_id'] = $user_id;

        // === Вызов метода update с явными плейсхолдерами ===
        $this->db->update(
            $update_data, // Данные для обновления (ассоциативный массив с плейсхолдерами)
            TBL_USERS,    // Таблица (строка)
            [
                'where' => '`id` = :user_id', // Условие WHERE (строка)
                'params' => $params           // Все параметры для prepared statements (массив)
            ]
        );
        $affected_rows = $this->db->get_affected_rows();
        // Если данные успешно обновлены и флаг удаления установлен, удаляем старый аватар
        if ($affected_rows > 0 && $delete_old_avatar) {
            $old_avatar_path = $work->config['site_dir'] . $work->config['avatar_folder'] . '/' . $user_data['avatar'];
            if (file_exists($old_avatar_path) && is_writable($old_avatar_path)) {
                unlink($old_avatar_path); // Безопасное удаление старого аватара
            }
        }
        return $affected_rows;
    }

    /**
     * Удаление пользователя.
     *
     * @param int $user_id Идентификатор пользователя.
     * @return void
     */
    public function delete_user(int $user_id): void
    {
        throw new \RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Метод не реализован | Удаление пользователя с ID: {$user_id}"
        );
    }

    /**
     * Добавление новой группы.
     *
     * @param array $group_data Данные новой группы.
     * @return void
     */
    public function add_new_group(array $group_data): void
    {
        throw new \RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Метод не реализован | Добавление новой группы"
        );
    }

    /**
     * Обновление данных группы.
     *
     * @param int $group_id Идентификатор группы.
     * @param array $group_data Новые данные группы.
     * @return void
     */
    public function update_group_data(int $group_id, array $group_data): void
    {
        throw new \RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Метод не реализован | Обновление данных группы с ID: {$group_id}"
        );
    }

    /**
     * Удаление группы.
     *
     * @param int $group_id Идентификатор группы.
     * @return void
     */
    public function delete_group(int $group_id): void
    {
        throw new \RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Метод не реализован | Удаление группы с ID: {$group_id}"
        );
    }

    /**
    * @brief Проверяет данные пользователя для входа в систему с "мягким" обновлением паролей на новый формат хранения.
    *
    * @details Этот метод выполняет следующие действия:
    *          - Проверяет корректность входных данных (логин и пароль) с использованием `$work->check_input()`.
    *          - Ищет пользователя в базе данных по логину.
    *          - Проверяет пароль через `password_verify()` и, при необходимости, через `md5`.
    *          - Если пароль хранится в старом формате (md5), он обновляется до формата `password_hash()`.
    *          - Возвращает ID пользователя или 0 в случае ошибки.
    *
    * @callgraph
    *
    * @see PhotoRigma::Classes::Work::check_input() Метод для проверки правильности входных данных.
    * @see PhotoRigma::Classes::User::$db Свойство, содержащее объект класса Database.
    * @see PhotoRigma::Include::log_in_file() Функция логирования событий.
    *
    * @param array $post Массив данных из формы ($_POST), содержащий ключи:
    *                    - string $login: Логин пользователя (должен соответствовать регулярному выражению REG_LOGIN).
    *                    - string $password: Пароль пользователя (не должен быть пустым).
    * @param Work $work Объект класса `Work`, предоставляющий вспомогательные методы для проверки входных данных.
    * @param string $redirect_url URL для перенаправления пользователя в случае возникновения ошибок.
    *
    * @return int ID пользователя, если авторизация успешна, или 0 в случае ошибки.
    *
    * @note Поддерживается совместимость со старым форматом хранения паролей (md5).
    *
    * @warning Метод зависит от корректной конфигурации базы данных.
    *
    * Пример использования метода:
    * @code
    * $user = new \PhotoRigma\Classes\User();
    * $work = new \PhotoRigma\Classes\Work();
    * $redirectUrl = '/login/error';
    * $userId = $user->login_user($_POST, $work, $redirectUrl);
    * if ($userId > 0) {
    *     echo "Вход выполнен успешно! ID пользователя: {$userId}";
    * } else {
    *     echo "Ошибка входа.";
    * }
    * @endcode
    */
    public function login_user(array $post_data, Work $work, string $redirect_url): int
    {
        // === 1. Проверка входных данных (логин и пароль) ===
        if (!$work->check_input('_POST', 'login', [
                'isset' => true,
                'empty' => true,
                'regexp' => REG_LOGIN
            ]) || !$work->check_input('_POST', 'password', [
                'isset' => true,
                'empty' => true
            ])) {
            // Входные данные формы невалидны
            header('Location: ' . $redirect_url);
            exit();
            \PhotoRigma\Include\log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверные данные формы | Действие: login, Пользователь ID: {$this->session['login_id']}"
            );
        }

        // === 2. Поиск пользователя в базе данных по логину ===
        $this->db->select(
            ['id', 'login', 'password'], // Список полей для выборки
            TBL_USERS, // Имя таблицы
            [
                'where' => 'login = :login', // Условие WHERE
                'params' => [':login' => $post_data['login']] // Параметры для prepared statements
            ]
        );
        $user_data = $this->db->res_row();

        if ($user_data === false) {
            // Пользователь с указанным логином не найден
            header('Location: ' . $redirect_url);
            exit();
            \PhotoRigma\Include\log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Пользователь с логином '{$post_data['login']}' не найден"
            );
        }

        // === 3. Проверка пароля ===
        if (!password_verify($post_data['password'], $user_data['password'])) {
            // Если проверка через password_verify() не прошла, проверяем пароль через md5
            if (md5($post_data['password']) !== $user_data['password']) {
                // Пароль неверный
                header('Location: ' . $redirect_url);
                exit();
                \PhotoRigma\Include\log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный пароль | Действие: login, Пользователь ID: {$this->session['login_id']}"
                );
            } else {
                // Пароль верный, но хранится в формате md5. Преобразуем его в формат password_hash()
                $new_password_hash = password_hash($post_data['password'], PASSWORD_BCRYPT);

                // Обновляем пароль в базе данных
                $this->db->update(
                    ['`password`' => ':password'], // Данные для обновления
                    TBL_USERS, // Таблица
                    [
                        'where' => '`id` = :id', // Условие WHERE
                        'params' => [
                            ':password' => $new_password_hash,
                            ':id' => $user_data['id']
                        ]
                    ]
                );

                // Проверяем результат обновления
                $rows = $this->db->get_affected_rows();
                if ($rows > 0) {
                    // Пароль успешно обновлён
                    \PhotoRigma\Include\log_in_file(
                        __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Успешное обновление пароля | Действие: login, Пользователь ID: {$this->session['login_id']}"
                    );
                } else {
                    // Ошибка при обновлении пароля
                    \PhotoRigma\Include\log_in_file(
                        __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка обновления пароля | Действие: login, Пользователь ID: {$this->session['login_id']}"
                    );
                }
            }
        }

        // === 4. Возвращаем ID пользователя ===
        return $user_data['id'];
    }

    /**
     * @brief Генерирует или возвращает CSRF-токен для защиты от межсайтовой подделки запросов.
     *
     * @details Этот метод выполняет следующие действия:
     *          - Если CSRF-токен отсутствует в сессии, он генерируется с использованием `random_bytes(32)` (64 символа).
     *          - Если токен уже существует, он возвращается без повторной генерации.
     *          - Токен хранится в сессии и используется для защиты форм от CSRF-атак.
     *
     *          Этот метод является публичным и предназначен для прямого использования извне.
     *
     * @see PhotoRigma::Classes::User::$session Свойство класса User, содержащее данные сессии.
     *
     * @return string CSRF-токен длиной 64 символа (генерируется с использованием `random_bytes(32)`).
     *
     * @note Токен хранится в сессии и используется для защиты форм от CSRF-атак.
     *
     * @warning Не используйте этот метод для генерации токенов, если сессия недоступна.
     *
     * Пример вызова метода:
     * @code
     * $user = new \PhotoRigma\Classes\User();
     * $csrfToken = $user->csrf_token();
     * echo "CSRF Token: {$csrfToken}";
     * @endcode
     */
    public function csrf_token(): string
    {
        if (empty($this->session['csrf_token'])) {
            $this->session['csrf_token'] = bin2hex(random_bytes(32)); // 64 символа
        }
        return $this->session['csrf_token'];
    }

    /**
     * @brief Инициализация данных пользователя.
     *
     * @details Метод проверяет наличие `login_id` в массиве сессии. Если `login_id` равен 0, загружается гость,
     *          иначе — аутентифицированный пользователь. Этот метод является приватным и предназначен только
     *          для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::User::$session Свойство, содержащее данные сессии.
     * @see PhotoRigma::Classes::User::load_guest_user() Метод для загрузки данных гостя.
     * @see PhotoRigma::Classes::User::load_authenticated_user() Метод для загрузки данных аутентифицированного пользователя.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $this->initialize_user();
     * @endcode
     */
    private function initialize_user(): void
    {
        $login_id = (int)($this->session['login_id'] ?? 0);
        $this->session['login_id'] = $login_id;
        if ($login_id === 0) {
            $this->load_guest_user();
        } else {
            $this->load_authenticated_user($login_id);
        }
    }

    /**
     * @brief Метод загружает данные группы гостя из базы данных и сохраняет их в свойство `$user`.
     *
     * @details Метод выполняет запрос к базе данных для получения данных группы гостя с `id = 0`.
     *          Если данные не найдены или некорректны, выбрасываются исключения. Поле `user_rights`
     *          удаляется из данных группы гостя и передается в метод `process_user_rights()`, который
     *          преобразует строку JSON в ассоциативный массив. Итоговые данные сохраняются в свойство `$user`.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::User::$db Свойство, содержащее объект для работы с базой данных.
     * @see PhotoRigma::Classes::User::process_user_rights() Метод для обработки прав пользователя.
     * @see PhotoRigma::Classes::User::$user Свойство, содержащее данные текущего пользователя.
     *
     * @throws RuntimeException Если не удаётся получить данные группы гостя из базы данных.
     * @throws UnexpectedValueException Если данные группы гостя некорректны (например, пустые или не являются массивом).
     *
     * @note Используется константа TBL_GROUP для указания таблицы базы данных.
     * @warning Поле `user_rights` обязательно должно содержать валидный JSON. Невалидные данные могут привести к ошибкам.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $this->load_guest_user();
     * @endcode
     */
    private function load_guest_user(): void
    {
        // Выполняем запрос к базе данных для получения данных группы гостя
        if (!$this->db->select('`id`, `user_rights`', TBL_GROUP, ['where' => '`id` = :group_id', 'params' => ['group_id' => 0]])) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка базы данных | Не удалось получить данные группы гостя"
            );
        }

        // Получаем данные группы гостя
        $guest_group = $this->db->res_row();
        if (!$guest_group || !is_array($guest_group)) {
            throw new \UnexpectedValueException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректные данные группы гостя | Проверьте формат данных в таблице " . TBL_GROUP
            );
        }

        // Удаляем поле user_rights из данных гостя
        $user_rights = $guest_group['user_rights'] ?? null;
        unset($guest_group['user_rights']);

        // Обрабатываем права гостя (user_rights)
        $processed_rights = $this->process_user_rights($user_rights);

        // Присваиваем данные гостя с добавлением прав
        $this->user = array_merge($guest_group, $processed_rights);
    }

    /**
     * @brief Метод загружает данные аутентифицированного пользователя из базы данных, включая права пользователя и группы,
     *        обновляет дату последней активности и сохраняет данные в свойство `$user`.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Загружает данные пользователя из таблицы `TBL_USERS` по `user_id`.
     *          2. Если данные пользователя отсутствуют, сбрасывает сессию до гостя и вызывает метод `load_guest_user()`.
     *          3. Обрабатывает права пользователя (`user_rights`) с помощью метода `process_user_rights()` и сохраняет их
     *             в свойство `$user`.
     *          4. Загружает данные группы пользователя из таблицы `TBL_GROUP` по `group_id`.
     *          5. Если данные группы отсутствуют, сбрасывает сессию до гостя и вызывает метод `load_guest_user()`.
     *          6. Обрабатывает права группы (`user_rights`) с помощью метода `process_user_rights()` и объединяет их с
     *             данными пользователя через метод `merge_user_with_group()`.
     *          7. Обновляет дату последней активности пользователя в таблице `TBL_USERS`.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::User::$db Свойство, содержащее объект для работы с базой данных.
     * @see PhotoRigma::Classes::User::process_user_rights() Метод для обработки прав пользователя/группы.
     * @see PhotoRigma::Classes::User::merge_user_with_group() Метод для объединения данных пользователя и группы.
     * @see PhotoRigma::Classes::User::$user Свойство, содержащее данные текущего пользователя.
     *
     * @param int $user_id Идентификатор пользователя. Должен быть положительным целым числом.
     *
     * @throws RuntimeException Если не удаётся получить данные пользователя из таблицы `TBL_USERS`.
     * @throws RuntimeException Если не удаётся получить данные группы пользователя из таблицы `TBL_GROUP`.
     * @throws RuntimeException Если не удаётся обновить дату последней активности пользователя в таблице `TBL_USERS`.
     *
     * @note Используются константы `TBL_USERS` и `TBL_GROUP` для указания таблиц базы данных.
     * @warning Поля `user_rights` в данных пользователя и группы должны содержать валидный JSON. Невалидные данные могут
     *          привести к ошибкам.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $this->load_authenticated_user(123);
     * @endcode
     */
    private function load_authenticated_user(int $user_id): void
    {
        // Загружаем данные пользователя
        if (!$this->db->select('*', TBL_USERS, ['where' => '`id` = :user_id', 'params' => ['user_id' => $user_id]])) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка базы данных | Не удалось получить данные пользователя с ID: {$user_id}"
            );
        }

        $user_data = $this->db->res_row();
        if (!$user_data) {
            $this->session['login_id'] = 0;
            $this->load_guest_user();
            return;
        }

        // Удаляем конфиденциальное поле password из данных пользователя
        unset($user_data['password']);

        // Удаляем поле user_rights из данных пользователя
        $user_rights = $user_data['user_rights'] ?? null;
        unset($user_data['user_rights']);

        // Обрабатываем права пользователя (user_rights)
        $processed_rights = $this->process_user_rights($user_rights);

        // Присваиваем данные пользователя с добавлением прав
        $this->user = array_merge($user_data, $processed_rights);

        // Загружаем данные группы пользователя
        if (!$this->db->select('*', TBL_GROUP, ['where' => '`id` = :group_id', 'params' => ['group_id' => $this->user['group_id']]])) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка базы данных | Не удалось получить данные группы пользователя с ID: {$this->user['group']}"
            );
        }

        $group_data = $this->db->res_row();
        if (!$group_data) {
            $this->session['login_id'] = 0;
            $this->load_guest_user();
            return;
        }


        // Устанавливаем язык сайта
        $this->session['language'] = $this->user['language'];

        // Удаляем поле user_rights из данных группы
        $group_rights = $group_data['user_rights'] ?? null;
        unset($group_data['user_rights']);

        // Обрабатываем права группы (user_rights)
        $processed_rights = $this->process_user_rights($group_rights);

        // Присваиваем данные пользователя с добавлением прав группы
        $this->merge_user_with_group(array_merge($group_data, $processed_rights));

        // Обновляем данные о последней активности пользователя
        if (!$this->db->update(['date_last_activ' => ':current_time'], TBL_USERS, ['where' => '`id` = :user_id', 'params' => ['user_id' => $user_id, ':current_time' => date('Y-m-d H:i:s')]])) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка базы данных | Не удалось обновить дату последней активности пользователя с ID: {$user_id}"
            );
        }
    }

    /**
     * @brief Метод объединяет данные пользователя с данными его группы, применяя логику перезаписи значений.
     *
     * @details Метод проходит по массиву данных группы (`$group_data`) и объединяет их с данными пользователя (`$user`)
     *          согласно следующей логике:
     *          1. Ключ `name`: Значение сохраняется в `$user['group']`, а текущее значение `$user['group']` перемещается
     *             в `$user['group_id']`.
     *          2. Ключ `id`: Пропускается.
     *          3. Для всех остальных ключей:
     *             - Если ключ существует в `$user`:
     *               - Если оба значения равны `0`, сохраняется `0`.
     *               - Иначе сохраняется `1`.
     *             - Если ключ отсутствует в `$user`, значение берётся из `$group_data`.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     *
     * @see PhotoRigma::Classes::User::$user Свойство, содержащее данные текущего пользователя.
     *
     * @param array $group_data Ассоциативный массив, содержащий данные группы пользователя.
     *                          Ключи должны соответствовать полям в таблице групп.
     *
     * @note Ключ `id` в данных группы игнорируется.
     * @warning Метод предполагает, что структура данных группы совпадает с ожидаемой. Неправильная структура может
     *          привести к ошибкам.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $this->merge_user_with_group(['name' => 'Admins', 'id' => 1, 'permission_edit' => 1]);
     * @endcode
     */
    private function merge_user_with_group(array $group_data): void
    {
        foreach ($group_data as $key => $value) {
            match ($key) {
                'name' => [
                    $this->user['group_name'] = $value,
                ],
                'id' => null, // Пропускаем ключ 'id'
                default => $this->user[$key] = isset($this->user[$key])
                    ? (($this->user[$key] == 0 && $value == 0) ? 0 : 1)
                    : $value,
            };
        }
    }

    /**
     * @brief Метод обрабатывает поле `user_rights`, преобразуя его из строки JSON в массив прав.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Проверяет, существует ли поле `user_rights`. Если поле отсутствует, возвращается пустой массив.
     *          2. Проверяет, что поле является строкой. Если тип данных некорректен, выбрасывается исключение.
     *          3. Декодирует строку JSON в ассоциативный массив. Если декодирование завершается с ошибкой, выбрасывается исключение.
     *          4. Возвращает массив прав, готовый для добавления в свойство `$user`.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     *
     * @param mixed $user_rights Значение поля `user_rights`. Может быть строкой JSON, пустым значением или `null`.
     *                           Если значение не является строкой или содержит невалидный JSON, выбрасывается исключение.
     *
     * @return array Ассоциативный массив прав пользователя. Если поле `user_rights` отсутствует или содержит некорректные данные,
     *               возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если поле `user_rights` не является строкой.
     * @throws InvalidArgumentException Если поле `user_rights` содержит невалидный JSON.
     *
     * @note Метод возвращает пустой массив, если поле `user_rights` отсутствует или содержит некорректные данные.
     * @warning Поле `user_rights` должно содержать валидный JSON. Невалидные данные могут привести к исключению.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $rights = $this->process_user_rights('{"edit": 1, "delete": 0}');
     * @endcode
     */
    private function process_user_rights($user_rights): array
    {
        // Проверяем, существует ли поле user_rights
        if (!isset($user_rights)) {
            return [];
        }

        // Проверяем, что поле является строкой
        if (!is_string($user_rights)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный тип данных | Поле user_rights должно быть строкой"
            );
        }

        // Декодируем JSON
        $rights = json_decode($user_rights, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный формат JSON | Поле user_rights содержит невалидные данные"
            );
        }

        // Возвращаем массив прав
        return is_array($rights) ? $rights : [];
    }

    /**
     * @brief Метод загружает права первого пользователя и первой группы из базы данных, извлекает имена полей (ключи)
     *        из JSON-поля `user_rights` и сохраняет их в свойство `$user_right_fields`.
     *
     * @details Метод выполняет следующие шаги:
     *          1. Загружает первые доступные записи из таблиц `TBL_USERS` и `TBL_GROUP`.
     *          2. Извлекает поле `user_rights` (JSON) из каждой записи.
     *          3. Декодирует JSON с помощью метода `process_user_rights()` и сохраняет имена полей (ключи)
     *             в свойство `$user_right_fields`.
     *          4. Если данные отсутствуют или некорректны, сохраняется пустой массив.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::User::$db Свойство, содержащее объект для работы с базой данных.
     * @see PhotoRigma::Classes::User::process_user_rights() Метод для обработки прав пользователя/группы.
     * @see PhotoRigma::Classes::User::$user_right_fields Свойство, содержащее имена полей прав пользователей и групп.
     *
     * @throws RuntimeException Если не удаётся получить права первого пользователя из таблицы `TBL_USERS`.
     * @throws RuntimeException Если не удаётся получить права первой группы из таблицы `TBL_GROUP`.
     *
     * @note Метод сохраняет имена полей прав пользователей и групп в свойство `$user_right_fields`.
     *       Если данные отсутствуют, сохраняются пустые массивы.
     *       Используются константы `TBL_USERS` и `TBL_GROUP` для указания таблиц базы данных.
     *
     * @warning Поле `user_rights` должно содержать валидный JSON. Невалидные данные могут привести к исключению.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $this->all_right_fields();
     * @endcode
     */
    private function all_right_fields(): void
    {
        // === Загрузка прав первого пользователя ===
        if (!$this->db->select('user_rights', TBL_USERS, ['limit' => 1])) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка базы данных | Не удалось получить права первого пользователя"
            );
        }

        $user_result = $this->db->res_row();
        if ($user_result && isset($user_result['user_rights'])) {
            // Обрабатываем права пользователя
            $user_rights = $this->process_user_rights($user_result['user_rights']);
            // Сохраняем имена полей прав пользователя
            $this->user_right_fields['user'] = array_keys($user_rights);
        } else {
            // Если права пользователя отсутствуют, сохраняем пустой массив
            $this->user_right_fields['user'] = [];
        }

        // === Загрузка прав первой группы ===
        if (!$this->db->select('user_rights', TBL_GROUP, ['limit' => 1])) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка базы данных | Не удалось получить права первой группы"
            );
        }

        $group_result = $this->db->res_row();
        if ($group_result && isset($group_result['user_rights'])) {
            // Обрабатываем права группы
            $group_rights = $this->process_user_rights($group_result['user_rights']);
            // Сохраняем имена полей прав группы
            $this->user_right_fields['group'] = array_keys($group_rights);
        } else {
            // Если права группы отсутствуют, сохраняем пустой массив
            $this->user_right_fields['group'] = [];
        }
    }

    /**
    * @brief Обрабатывает загрузку аватара пользователя.
    *
    * @details Этот приватный метод выполняет следующие действия:
    *          - Проверяет входные данные через `$work->check_input()`.
    *          - Генерирует уникальное имя файла для аватара с использованием `$work->encodename()`.
    *          - Перемещает загруженный файл в директорию аватаров.
    *          - Корректирует расширение файла на основе его MIME-типа с помощью `$work->fix_file_extension()`.
    *          - Возвращает имя нового аватара или значение по умолчанию (`no_avatar.jpg`), если загрузка не удалась.
    *          Метод предназначен только для использования внутри класса.
    *
    * @callergraph
    * @callgraph
    *
    * @see PhotoRigma::Classes::Work::check_input() Метод для проверки корректности входных данных.
    * @see PhotoRigma::Classes::Work::encodename() Метод для генерации уникального имени файла.
    * @see PhotoRigma::Classes::Work::fix_file_extension() Метод для корректировки расширения файла на основе его MIME-типа.
    * @see PhotoRigma::Classes::Work::$config Свойство класса Work, содержащее конфигурацию приложения.
    *
    * @param array $files_data Массив данных загруженного файла ($_FILES), содержащий информацию об аватаре:
    *                          - 'file_avatar' (array): Информация о загруженном файле.
    *                          Если файл не передан или не проходит валидацию, аватар остается без изменений.
    * @param int $max_size Максимальный размер файла для аватара в байтах. Определяется на основе конфигурации
    *                      приложения и ограничений PHP (например, post_max_size). Если размер файла превышает
    *                      это значение, загрузка аватара отклоняется.
    * @param Work $work Экземпляр класса `Work`, предоставляющий вспомогательные методы для работы с данными:
    *                   - `check_input()`: Валидация входных данных.
    *                   - `encodename()`: Генерация уникального имени файла.
    *                   - `fix_file_extension()`: Корректировка расширения файла на основе его MIME-типа.
    *                   Класс используется для выполнения вспомогательных операций внутри метода.
    *
    * @return string Имя нового аватара пользователя или значение по умолчанию (`no_avatar.jpg`),
    *                если загрузка аватара была отменена или произошла ошибка.
    *
    * @throws \RuntimeException Выбрасывается исключение в следующих случаях:
    *                           - Директория для аватаров недоступна для записи.
    *                           - Не удалось переместить загруженный файл.
    *
    * @note Используются константы: `DEFAULT_AVATAR`.
    *
    * Пример вызова метода внутри класса:
    * @code
    * $maxSize = 5 * 1024 * 1024; // 5 MB
    * $work = new \PhotoRigma\Classes\Work();
    * $newAvatar = $this->edit_avatar($_FILES, $maxSize, $work);
    * if ($newAvatar !== static::DEFAULT_AVATAR) {
    *     echo "Новый аватар успешно загружен: {$newAvatar}";
    * } else {
    *     echo "Загрузка аватара не удалась.";
    * }
    * @endcode
    */
    private function edit_avatar(array $files_data, int $max_size, Work $work): string
    {
        // Проверяем входные данные через check_input
        if ($work->check_input('_FILES', 'file_avatar', [
            'isset' => true,
            'empty' => true,
            'max_size' => $max_size
        ])) {
            // Генерация имени файла
            $original_name = basename($files_data['file_avatar']['name']);
            $file_info = pathinfo($original_name);
            $file_name = $file_info['filename'];
            $file_extension = isset($file_info['extension']) ? '.' . $file_info['extension'] : '';
            $encoded_name = Work::encodename($file_name);
            $file_avatar = time() . '_' . $encoded_name . $file_extension;
            $path_avatar = $work->config['site_dir'] . $work->config['avatar_folder'] . '/' . $file_avatar;

            // Проверяем права доступа к директории
            if (!is_writable($work->config['site_dir'] . $work->config['avatar_folder'])) {
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Директория для аватаров недоступна для записи."
                );
            }

            // Перемещение загруженного файла
            if (!move_uploaded_file($files_data['file_avatar']['tmp_name'], $path_avatar)) {
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось переместить загруженный файл: {$files_data['file_avatar']['tmp_name']} -> {$path_avatar}"
                );
            }

            // Корректировка расширения файла
            $fixed_path = $work->fix_file_extension($path_avatar);
            if ($fixed_path !== $path_avatar) {
                rename($path_avatar, $fixed_path);
                $file_avatar = basename($fixed_path);
            }

            return $file_avatar;
        }

        return static::DEFAULT_AVATAR;
    }
}
