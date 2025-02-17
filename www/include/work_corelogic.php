<?php

/**
 * @file        include/work_corelogic.php
 * @brief       Файл содержит класс Work_CoreLogic, который отвечает за выполнение базовой логики приложения.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-02-13
 * @namespace   PhotoRigma\\Classes
 *
 * @details     Этот файл содержит класс `Work_CoreLogic`, который реализует интерфейс `Work_CoreLogic_Interface`.
 *              Класс предоставляет методы для выполнения ключевых операций приложения, таких как:
 *              - Работа с категориями и альбомами (метод `category`).
 *              - Управление изображениями (методы `del_photo`, `create_photo`).
 *              - Получение данных о новостях, языках и темах (методы `news`, `get_languages`, `get_themes`).
 *              Все методы зависят от конфигурации приложения и данных, полученных из базы данных.
 *
 * @see         PhotoRigma::Classes::Work_CoreLogic_Interface Интерфейс, который реализует данный класс.
 * @see         PhotoRigma::Classes::Database Класс для работы с базой данных.
 * @see         PhotoRigma::Classes::Work_Helper::clean_field() Метод для очистки данных.
 * @see         index.php Файл, который подключает work_corelogic.php.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в выполнении базовой логики приложения.
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
 * @interface Work_CoreLogic_Interface
 * @brief Интерфейс, определяющий контракт для классов, реализующих базовую логику приложения.
 *
 * @details Интерфейс Work_CoreLogic_Interface предоставляет набор методов для выполнения ключевых операций:
 *          - Работа с категориями и альбомами (category).
 *          - Управление изображениями (del_photo, create_photo).
 *          - Получение данных о новостях, языках и темах (news, get_languages, get_themes).
 *
 * @see PhotoRigma::Classes::Work_CoreLogic Реализация интерфейса.
 * @see PhotoRigma::Classes::Database Класс для работы с базой данных.
 */
interface Work_CoreLogic_Interface
{
    /**
     * Формирует информационную строку для конкретного раздела или пользовательского альбома.
     *
     * @param int $cat_id Идентификатор раздела или пользователя (если $user_flag = 1).
     * @param int $user_flag Флаг, указывающий формировать ли обычный список разделов (0) или список пользовательских альбомов (1).
     *
     * @return array Информационная строка для конкретного раздела или пользовательского альбома:
     *               - name: Название категории или альбома.
     *               - description: Описание категории или альбома.
     *               - count_photo: Количество фотографий.
     *               - last_photo: Название последней фотографии.
     *               - top_photo: Название лучшей фотографии.
     *               - url_cat: Ссылка на категорию или альбом.
     *               - url_last_photo: Ссылка на последнюю фотографию.
     *               - url_top_photo: Ссылка на лучшую фотографию.
     *
     * @throws InvalidArgumentException Если входные параметры имеют некорректный тип.
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных.
     */
    public function category(int $cat_id = 0, int $user_flag = 0): array;

    /**
     * Удаляет изображение с указанным идентификатором, а также все упоминания об этом изображении в таблицах сайта.
     *
     * @param int $photo_id Идентификатор удаляемого изображения (обязательное поле).
     *
     * @return bool True, если удаление успешно, иначе False.
     *
     * @throws InvalidArgumentException Если параметр $photo_id имеет некорректный тип или значение.
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных или удалении файлов.
     */
    public function del_photo(int $photo_id): bool;

    /**
     * Получает данные о новостях.
     *
     * @param int    $news_id_or_limit Количество новостей или ID новости (в зависимости от параметра $act).
     * @param string $act              Тип запроса:
     *                                 - 'id': Получение новости по её ID.
     *                                 - 'list': Получение списка новостей с сортировкой по дате последнего редактирования.
     *
     * @return array Массив с данными о новостях. Если новостей нет, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный $act или $news_id_or_limit.
     * @throws RuntimeException         Если произошла ошибка при выполнении запроса к базе данных.
     */
    public function news(int $news_id_or_limit, string $act): array;

    /**
     * Загружает доступные языки из директории /language/.
     *
     * @return array Массив с данными о доступных языках. Каждый элемент массива содержит:
     *               - `value`: Имя директории языка (строка).
     *               - `name`: Название языка из файла `main.php` (строка).
     *
     * @throws RuntimeException Если:
     *                           - Директория `/language/` недоступна или не существует.
     *                           - Ни один язык не найден в указанной директории.
     */
    public function get_languages(): array;

    /**
     * Загружает доступные темы из директории /themes/.
     *
     * @return array Массив с именами доступных тем.
     *
     * @throws RuntimeException Если:
     *                           - Директория `/themes/` не существует или недоступна для чтения.
     *                           - Ни одна тема не найдена в указанной директории.
     */
    public function get_themes(): array;

    /**
     * Генерирует блок вывода изображений для различных типов запросов.
     *
     * @param string $type Тип изображения:
     *                     - 'top': Лучшее изображение (по рейтингу).
     *                     - 'last': Последнее загруженное изображение.
     *                     - 'cat': Изображение из конкретной категории (требует указания $id_photo).
     *                     - Случайное: Любое случайное изображение.
     * @param int    $id_photo Идентификатор фото. Используется только при $type == 'cat'.
     *
     * @return array Массив данных для вывода изображения. Содержит следующие ключи:
     *               - 'name_block': Название блока изображения (например, "Лучшее фото").
     *               - 'url': URL для просмотра полного изображения.
     *               - 'thumbnail_url': URL для миниатюры изображения.
     *               - 'name': Название изображения.
     *               - 'category_name': Название категории.
     *               - 'description': Описание изображения.
     *               - 'category_description': Описание категории.
     *               - 'rate': Рейтинг изображения (например, "Рейтинг: 5/10").
     *               - 'url_user': URL профиля пользователя, добавившего изображение.
     *               - 'real_name': Реальное имя пользователя.
     *               - 'category_url': URL категории или пользовательского альбома.
     *               - 'width': Ширина изображения после масштабирования.
     *               - 'height': Высота изображения после масштабирования.
     *
     * @throws InvalidArgumentException Если передан недопустимый $type или $id_photo.
     * @throws RuntimeException         Если произошла ошибка при выборке данных из базы данных или доступе к файлу.
     */
    public function create_photo(string $type, int $id_photo): array;
}

/**
 * @class Work_CoreLogic
 * @brief Класс, реализующий интерфейс Work_CoreLogic_Interface для выполнения базовой логики приложения.
 *
 * @details Класс Work_CoreLogic предоставляет реализацию всех методов, определенных в интерфейсе Work_CoreLogic_Interface.
 *          Он отвечает за выполнение следующих задач:
 *          - Формирование информационных строк для категорий и пользовательских альбомов.
 *          - Удаление изображений и связанных данных.
 *          - Получение данных о новостях, языках и темах.
 *          - Генерация блоков вывода изображений для различных типов запросов.
 *
 * @implements Work_CoreLogic_Interface
 *
 * @see PhotoRigma::Classes::Work_CoreLogic_Interface Интерфейс, который реализует данный класс.
 * @see PhotoRigma::Classes::Database Класс для работы с базой данных.
 */
class Work_CoreLogic implements Work_CoreLogic_Interface
{
    private array $config; ///< Конфигурация приложения.
    private ?array $lang = null; ///< Языковые данные (могут быть null при инициализации).
    private Database_Interface $db; ///< Объект для работы с базой данных (обязательный).
    private Work $work; ///< Основной объект приложения (обязательный).
    private ?User $user = null; ///< Объект пользователя (может быть null при инициализации).

    /**
     * @brief Конструктор класса.
     *
     * @details Инициализирует зависимости: конфигурацию, базу данных и объект класса Work.
     *          Этот класс является дочерним для PhotoRigma::Classes::Work.
     *          Все параметры обязательны для корректной работы класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work Родительский класс, через который передаются зависимости.
     * @see PhotoRigma::Classes::Work_CoreLogic::$config Свойство, содержащее конфигурацию приложения.
     * @see PhotoRigma::Classes::Work_CoreLogic::$db Свойство, содержащее объект для работы с базой данных.
     * @see PhotoRigma::Classes::Work_CoreLogic::$work Свойство, содержащее основной объект приложения.
     *
     * @param array $config Конфигурация приложения.
     *                      Должен быть массивом. Если передан некорректный тип, выбрасывается исключение.
     * @param Database_Interface $db Объект для работы с базой данных.
     * @param Work $work Основной объект приложения.
     *
     * @throws InvalidArgumentException Если параметр $config не является массивом.
     *
     * @note Важно: все зависимости должны быть корректно инициализированы перед использованием класса.
     * @warning Не передавайте в конструктор некорректные или пустые зависимости, так как это может привести к ошибкам.
     *
     * @example PhotoRigma::Classes::Work_CoreLogic::__construct
     * @code
     * // Пример использования конструктора
     * $config = ['temp_photo_w' => 800];
     * $db = new Database();
     * $work = new Work();
     * $corelogic = new \PhotoRigma\Classes\Work_CoreLogic($config, $db, $work);
     * @endcode
     */
    public function __construct(array $config, Database_Interface $db, Work $work)
    {
        if (!is_array($config)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный тип конфигурации | Ожидался массив, получено: " . gettype($config)
            );
        }
        $this->config = $config;
        $this->db = $db;
        $this->work = $work;
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
     * @see PhotoRigma::Classes::Work_CoreLogic::$config Свойство, к которому обращается метод.
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
     * @example PhotoRigma::Classes::Work_CoreLogic::__get
     * @code
     * // Пример использования метода
     * $corelogic = new \PhotoRigma\Classes\Work_CoreLogic(['temp_photo_w' => 800], $db, $work);
     * echo $corelogic->config['temp_photo_w']; // Выведет: 800
     * @endcode
     */
    public function __get(string $name): array
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
     * @see PhotoRigma::Classes::Work_CoreLogic::$config Свойство, которое изменяет метод.
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
     * @example PhotoRigma::Classes::Work_CoreLogic::__set
     * @code
     * // Пример использования метода
     * $corelogic = new \PhotoRigma\Classes\Work_CoreLogic([], $db, $work);
     * $corelogic->config = ['temp_photo_w' => 1024];
     * @endcode
     */
    public function __set(string $name, array $value): void
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
     * @see PhotoRigma::Classes::Work_CoreLogic::$lang Свойство, которое изменяет метод.
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
     * @example PhotoRigma::Classes::Work_CoreLogic::set_lang
     * @code
     * // Пример использования метода
     * $corelogic = new \PhotoRigma\Classes\Work_CoreLogic($config, $db, $work);
     * $corelogic->set_lang(['key' => 'value']);
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
     * @see PhotoRigma::Classes::Work_CoreLogic::$user Свойство, которое изменяет метод.
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
     * @example PhotoRigma::Classes::Work_CoreLogic::set_user
     * @code
     * // Пример использования метода
     * $corelogic = new \PhotoRigma\Classes\Work_CoreLogic($config, $db, $work);
     * $user = new \PhotoRigma\Classes\User();
     * $corelogic->set_user($user);
     * @endcode
     */
    public function set_user(User $user): void
    {
        $this->user = $user;
    }

    /**
     * @brief Формирует информационную строку для конкретного раздела или пользовательского альбома.
     *
     * @details Метод выполняет запросы к базе данных для получения информации о категории или пользовательском альбоме,
     * включая количество фотографий, данные о последней и лучшей фотографии. Этот метод является редиректом на защищенный
     * метод _category_internal, где реализована основная логика.
     *
     * @param int $cat_id Идентификатор раздела или пользователя (если $user_flag = 1).
     * @param int $user_flag Флаг, указывающий формировать ли обычный список разделов (0) или список пользовательских альбомов (1).
     *
     * @return array Информационная строка для конкретного раздела или пользовательского альбома:
     *               - name: Название категории или альбома.
     *               - description: Описание категории или альбома.
     *               - count_photo: Количество фотографий.
     *               - last_photo: Название последней фотографии.
     *               - top_photo: Название лучшей фотографии.
     *               - url_cat: Ссылка на категорию или альбом.
     *               - url_last_photo: Ссылка на последнюю фотографию.
     *               - url_top_photo: Ссылка на лучшую фотографию.
     *
     * @throws InvalidArgumentException Если входные параметры имеют некорректный тип.
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных.
     *
     * @see PhotoRigma::Classes::Work::category() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_CoreLogic::_category_internal() Основная логика метода реализована здесь.
     */
    public function category(int $cat_id = 0, int $user_flag = 0): array
    {
        return $this->_category_internal($cat_id, $user_flag);
    }

    /**
     * @brief Удаляет изображение с указанным идентификатором, а также все упоминания об этом изображении в таблицах сайта.
     *
     * @details Метод удаляет файлы из каталогов полноразмерных изображений и эскизов, а также записи из таблиц базы данных.
     * Этот метод является редиректом на защищенный метод _del_photo_internal, где реализована основная логика.
     *
     * @param int $photo_id Идентификатор удаляемого изображения (обязательное поле).
     *
     * @return bool True, если удаление успешно, иначе False.
     *
     * @throws InvalidArgumentException Если параметр \$photo_id имеет некорректный тип или значение.
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных или удалении файлов.
     *
     * @see PhotoRigma::Classes::Work::del_photo() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_CoreLogic::_del_photo_internal() Основная логика метода реализована здесь.
     */
    public function del_photo(int $photo_id): bool
    {
        return $this->_del_photo_internal($photo_id);
    }

    /**
     * Получение данных о новостях.
     *
     * Метод формирует массив данных о новостях в зависимости от типа запроса.
     * Этот метод является редиректом на защищенный метод _news_internal, где реализована основная логика.
     *
     * @see PhotoRigma::Classes::Work::news() Вызывает этот метод для получения данных о новостях.
     * @see PhotoRigma::Classes::Work_CoreLogic::_news_internal() Основная логика метода реализована здесь.
     *
     * @param int    $news_id_or_limit Количество новостей или ID новости (в зависимости от параметра $act).
     * @param string $act              Тип запроса:
     *                                 - 'id': Получение новости по её ID.
     *                                 - 'list': Получение списка новостей с сортировкой по дате последнего редактирования.
     *
     * @return array Массив с данными о новостях. Если новостей нет, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный $act или $news_id_or_limit.
     * @throws RuntimeException         Если произошла ошибка при выполнении запроса к базе данных.
     *
     * @example
     * @code
     * // Пример использования метода news()
     * $news_by_id = $this->news(5, 'id'); // Получение новости с ID 5
     * print_r($news_by_id);
     *
     * $news_list = $this->news(10, 'list'); // Получение 10 последних новостей
     * print_r($news_list);
     * @endcode
     */
    public function news(int $news_id_or_limit, string $act): array
    {
        return $this->_news_internal($news_id_or_limit, $act);
    }

    /**
     * Загружает доступные языки из директории /language/.
     *
     * Метод перебирает все поддиректории в `/language/` и проверяет наличие файла `main.php`
     * в каждой из них. Если файл найден и содержит корректное значение переменной `$lang_name`,
     * язык добавляется в список доступных языков.
     * Этот метод является редиректом на защищенный метод _get_languages_internal, где реализована основная логика.
     *
     * @see PhotoRigma::Classes::Work::get_languages()
     *      Вызывает этот метод для получения списка доступных языков.
     * @see PhotoRigma::Classes::Work_CoreLogic::_get_languages_internal()
     *      Основная логика метода реализована здесь.
     *
     * @return array Массив с данными о доступных языках. Каждый элемент массива содержит:
     *               - `value`: Имя директории языка (строка).
     *               - `name`: Название языка из файла `main.php` (строка).
     *
     * @throws RuntimeException Если:
     *                           - Директория `/language/` недоступна или не существует.
     *                           - Ни один язык не найден в указанной директории.
     *
     * @example
     * @code
     * $languages = $databaseDirectory->get_languages();
     * foreach ($languages as $language) {
     *     echo "Язык: " . $language['name'] . " (ID: " . $language['value'] . ")\n";
     * }
     * @endcode
     */
    public function get_languages(): array
    {
        return $this->_get_languages_internal();
    }

    /**
     * Загружает доступные темы из директории /themes/.
     *
     * Метод перебирает все поддиректории в `/themes/` и добавляет их имена в список доступных тем.
     * Этот метод является редиректом на защищенный метод _get_themes_internal, где реализована основная логика.
     *
     * @see PhotoRigma::Classes::Work::get_themes()
     *      Вызывает этот метод для получения списка доступных тем.
     * @see PhotoRigma::Classes::Work_CoreLogic::_get_themes_internal()
     *      Основная логика метода реализована здесь.
     *
     * @return array Массив с именами доступных тем.
     *
     * @throws RuntimeException Если:
     *                           - Директория `/themes/` не существует или недоступна для чтения.
     *                           - Ни одна тема не найдена в указанной директории.
     */
    public function get_themes(): array
    {
        return $this->_get_themes_internal();
    }

    /**
     * Генерирует блок вывода изображений для различных типов запросов.
     *
     * Метод выполняет следующие действия:
     * 1. Проверяет права пользователя на просмотр изображений.
     * 2. Формирует SQL-запрос для получения данных изображения и пользователя через JOIN:
     *    - Для типа 'top': Выбирает лучшее изображение с учетом рейтинга.
     *    - Для типа 'last': Выбирает последнее загруженное изображение.
     *    - Для типа 'cat': Выбирает изображение из конкретной категории по $id_photo.
     *    - Для случайного типа: Выбирает любое случайное изображение.
     * 3. Проверяет существование файла изображения и его доступность.
     * 4. Вычисляет размеры изображения через метод size_image().
     * 5. Возвращает массив данных для вывода изображения.
     * Этот метод является редиректом на защищенный метод _create_photo_internal, где реализована основная логика.
     *
     * @param string $type Тип изображения:
     *                     - 'top': Лучшее изображение (по рейтингу).
     *                     - 'last': Последнее загруженное изображение.
     *                     - 'cat': Изображение из конкретной категории (требует указания $id_photo).
     *                     - Случайное: Любое случайное изображение.
     * @param int    $id_photo Идентификатор фото. Используется только при $type == 'cat'.
     *
     * @return array Массив данных для вывода изображения. Содержит следующие ключи:
     *               - 'name_block': Название блока изображения (например, "Лучшее фото").
     *               - 'url': URL для просмотра полного изображения.
     *               - 'thumbnail_url': URL для миниатюры изображения.
     *               - 'name': Название изображения.
     *               - 'category_name': Название категории.
     *               - 'description': Описание изображения.
     *               - 'category_description': Описание категории.
     *               - 'rate': Рейтинг изображения (например, "Рейтинг: 5/10").
     *               - 'url_user': URL профиля пользователя, добавившего изображение.
     *               - 'real_name': Реальное имя пользователя.
     *               - 'category_url': URL категории или пользовательского альбома.
     *               - 'width': Ширина изображения после масштабирования.
     *               - 'height': Высота изображения после масштабирования.
     *
     * @throws InvalidArgumentException Если передан недопустимый $type или $id_photo.
     * @throws RuntimeException         Если произошла ошибка при выборке данных из базы данных или доступе к файлу.
     *
     * @see PhotoRigma::Classes::Work::create_photo() Метод, используемый для генерации блока вывода изображений.
     * @see PhotoRigma::Classes::Work_CoreLogic::_create_photo_internal() Основная логика метода реализована здесь.
     */
    public function create_photo(string $type, int $id_photo): array
    {
        return $this->_create_photo_internal($type, $id_photo);
    }

    /**
     * @brief Формирует информационную строку для конкретного раздела или пользовательского альбома.
     *
     * @details Метод выполняет запросы к базе данных для получения информации о категории или пользовательском альбоме,
     * включая количество фотографий, данные о последней и лучшей фотографии. Этот метод содержит основную логику,
     * вызываемую через публичный метод category().
     *
     * @param int $cat_id Идентификатор раздела или пользователя (если $user_flag = 1).
     * @param int $user_flag Флаг, указывающий формировать ли обычный список разделов (0) или список пользовательских альбомов (1).
     *
     * @return array Информационная строка для конкретного раздела или пользовательского альбома:
     *               - name: Название категории или альбома.
     *               - description: Описание категории или альбома.
     *               - count_photo: Количество фотографий.
     *               - last_photo: Название последней фотографии.
     *               - top_photo: Название лучшей фотографии.
     *               - url_cat: Ссылка на категорию или альбом.
     *               - url_last_photo: Ссылка на последнюю фотографию.
     *               - url_top_photo: Ссылка на лучшую фотографию.
     *
     * @throws InvalidArgumentException Если входные параметры имеют некорректный тип.
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных.
     *
     * @see PhotoRigma::Classes::Work_CoreLogic::category() Публичный метод-редирект для вызова этой логики.
     * @see PhotoRigma::Classes::Work_CoreLogic::$db Свойство, содержащее объект для работы с базой данных.
     * @see PhotoRigma::Classes::Work_CoreLogic::$lang Свойство, содержащее языковые строки.
     * @see PhotoRigma::Classes::Work_CoreLogic::$user Свойство, содержащее данные текущего пользователя.
     * @see PhotoRigma::Classes::Work_Helper::clean_field() Метод для очистки данных.
     */
    protected function _category_internal(int $cat_id = 0, int $user_flag = 0): array
    {
        // Проверка входных параметров
        if (!is_int($cat_id) || $cat_id < 0) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверное значение параметра \$cat_id | Ожидалось целое положительное число"
            );
        }
        if (!in_array($user_flag, [0, 1], true)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверное значение параметра \$user_flag | Ожидалось 0 или 1"
            );
        }
        $photo = [];
        $category_data = [];
        $add_query = '';
        if ($user_flag == 1) {
            // Получение данных о пользователе и его альбоме
            $this->db->select(['id', 'name'], TBL_CATEGORY, ['where' => '`id` = 0']);
            $category_data = $this->db->res_row();
            if (!$category_data) {
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные о корневой категории | Причина: Отсутствуют данные в таблице " . TBL_CATEGORY
                );
            }
            $this->db->select(['real_name'], TBL_USERS, ['where' => '`id` = :user_id', 'params' => [':user_id' => $cat_id]]);
            $user_data = $this->db->res_row();
            if (!$user_data) {
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные о пользователе | Переменная \$cat_id = $cat_id"
                );
            }
            $add_query = ' AND `user_upload` = :user_id';
            $category_data['description'] = $category_data['name'] . ' ' . Work::clean_field($user_data['real_name']);
            $category_data['name'] = Work::clean_field($user_data['real_name']);
        } else {
            // Получение данных о категории
            $this->db->select(['id', 'name', 'description'], TBL_CATEGORY, ['where' => '`id` = :cat_id', 'params' => [':cat_id' => $cat_id]]);
            $category_data = $this->db->res_row();
            if (!$category_data) {
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные о категории | Переменная \$cat_id = $cat_id"
                );
            }
        }
        // Получение количества фотографий
        $this->db->select(['COUNT(*) AS num_photo'], TBL_PHOTO, [
            'where' => '`category` = :cat_id' . $add_query,
            'params' => array_merge([':cat_id' => $category_data['id']], $user_flag ? [':user_id' => $cat_id] : [])
        ]);
        $photo_count_data = $this->db->res_row();
        if (!$photo_count_data) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить количество фотографий для категории | Переменная \$cat_id = $cat_id"
            );
        }
        // Формирование массива $photo
        $photo['count'] = $photo_count_data['num_photo'];
        $photo['last_name'] = $this->lang['main']['no_foto'];
        $photo['last_url'] = sprintf('%s?action=photo&amp;id=0', $this->config['site_url']);
        $photo['top_name'] = $this->lang['main']['no_foto'];
        $photo['top_url'] = sprintf('%s?action=photo&amp;id=0', $this->config['site_url']);
        if ($this->user->user['pic_view']) {
            // Получение данных о последней фотографии
            $this->db->select(
                ['p.id', 'p.name', 'p.description'],
                TBL_PHOTO . ' p',
                [
                    'where' => '`p.category` = :cat_id' . $add_query,
                    'order' => 'p.date_upload DESC',
                    'limit' => 1,
                    'params' => array_merge([':cat_id' => $category_data['id']], $user_flag ? [':user_id' => $cat_id] : [])
                ]
            );
            $last_photo_data = $this->db->res_row();
            // Получение данных о лучшей фотографии
            $this->db->select(
                ['p.id', 'p.name', 'p.description'],
                TBL_PHOTO . ' p',
                [
                    'where' => '`p.category` = :cat_id' . $add_query . ' AND `p.rate_user` != 0',
                    'order' => 'p.rate_user DESC',
                    'limit' => 1,
                    'params' => array_merge([':cat_id' => $category_data['id']], $user_flag ? [':user_id' => $cat_id] : [])
                ]
            );
            $top_photo_data = $this->db->res_row();
            if ($last_photo_data) {
                $photo['last_name'] = sprintf('%s (%s)', Work::clean_field($last_photo_data['name']), Work::clean_field($last_photo_data['description']));
                $photo['last_url'] = sprintf('%s?action=photo&amp;id=%d', $this->config['site_url'], $last_photo_data['id']);
            }
            if ($top_photo_data) {
                $photo['top_name'] = sprintf('%s (%s)', Work::clean_field($top_photo_data['name']), Work::clean_field($top_photo_data['description']));
                $photo['top_url'] = sprintf('%s?action=photo&amp;id=%d', $this->config['site_url'], $top_photo_data['id']);
            }
        }
        // Дополнительная информация для корневой категории
        if ($cat_id == 0) {
            $this->db->select(['COUNT(DISTINCT `user_upload`) AS num_user_upload'], TBL_PHOTO, ['where' => '`category` = 0']);
            $user_upload_data = $this->db->res_row();
            $category_data['id'] = 'user';
            if ($user_upload_data) {
                $category_data['name'] .= sprintf(' (%s: %d)', $this->lang['category']['count_user_category'], $user_upload_data['num_user_upload']);
            } else {
                $category_data['name'] .= sprintf('<br />(%s)', $this->lang['category']['no_user_category']);
            }
        }
        if ($user_flag == 1) {
            $category_data['id'] = 'user&amp;id=' . $cat_id;
        }
        // Формирование результирующего массива
        $category = [
            'name'           => Work::clean_field($category_data['name']),
            'description'    => Work::clean_field($category_data['description']),
            'count_photo'    => $photo['count'],
            'last_photo'     => $photo['last_name'],
            'top_photo'      => $photo['top_name'],
            'url_cat'        => sprintf('%s?action=category&amp;cat=%s', $this->config['site_url'], $category_data['id']),
            'url_last_photo' => $photo['last_url'],
            'url_top_photo'  => $photo['top_url']
        ];
        return $category;
    }

    /**
     * @brief Удаляет изображение с указанным идентификатором, а также все упоминания об этом изображении в таблицах сайта.
     *
     * @details Метод удаляет файлы из каталогов полноразмерных изображений и эскизов, а также записи из таблиц базы данных.
     * Этот метод содержит основную логику, вызываемую через публичный метод del_photo().
     *
     * @param int $photo_id Идентификатор удаляемого изображения (обязательное поле).
     *
     * @return bool True, если удаление успешно, иначе False.
     *
     * @throws InvalidArgumentException Если параметр \$photo_id имеет некорректный тип или значение.
     * @throws RuntimeException Если возникает ошибка при выполнении запросов к базе данных или удалении файлов.
     *
     * @see PhotoRigma::Classes::Work_CoreLogic::del_photo() Публичный метод-редирект для вызова этой логики.
     * @see PhotoRigma::Classes::Work_CoreLogic::$db Свойство, содержащее объект для работы с базой данных.
     * @see PhotoRigma::Classes::Work_CoreLogic::$config Свойство, содержащее конфигурацию приложения.
     * @see PhotoRigma::Classes::Database::join() Метод, используемый для объединения данных из нескольких таблиц.
     * @see PhotoRigma::Classes::Database::delete() Метод, используемый для удаления записей из таблиц базы данных.
     * @see PhotoRigma::Classes::Database::aff_rows Свойство, содержащее количество затронутых строк после выполнения запроса.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     */
    protected function _del_photo_internal(int $photo_id): bool
    {
        // Проверка входного параметра
        if (!is_int($photo_id) || $photo_id <= 0) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверное значение параметра \$photo_id | Ожидалось положительное целое число"
            );
        }
        // Получение данных об изображении и категории через JOIN
        $this->db->join(
            ['p.*', 'c.folder'],
            TBL_PHOTO . ' p',
            [
                'table' => TBL_CATEGORY . ' c',
                'on' => 'p.category = c.id'
            ],
            [
                'where' => 'p.id = :photo_id',
                'params' => [':photo_id' => $photo_id]
            ]
        );
        $temp_data = $this->db->res_row();
        if (!$temp_data) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось найти изображение | Переменная \$photo_id = $photo_id"
            );
        }
        // Определение путей к файлам
        $path_thumbnail = $this->config['site_dir'] . $this->config['thumbnail_folder'] . '/' . $temp_data['folder'] . '/' . $temp_data['file'];
        $path_photo = $this->config['site_dir'] . $this->config['gallery_folder'] . '/' . $temp_data['folder'] . '/' . $temp_data['file'];
        // Удаление записи об изображении из таблицы
        $this->db->delete(TBL_PHOTO, ['where' => '`id` = :photo_id', 'params' => [':photo_id' => $photo_id]]);
        if ($this->db->aff_rows !== 1) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось удалить запись об изображении | Переменная \$photo_id = $photo_id"
            );
        }
        // Удаление файлов
        if (file_exists($path_thumbnail)) {
            if (!unlink($path_thumbnail)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось удалить файл эскиза | Путь: $path_thumbnail"
                );
            }
        }
        if (file_exists($path_photo)) {
            if (!unlink($path_photo)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось удалить файл изображения | Путь: $path_photo"
                );
            }
        }
        // Удаление связанных записей из других таблиц
        $this->db->delete(TBL_RATE_USER, ['where' => '`id_foto` = :photo_id', 'params' => [':photo_id' => $photo_id]]);
        $this->db->delete(TBL_RATE_MODER, ['where' => '`id_foto` = :photo_id', 'params' => [':photo_id' => $photo_id]]);
        return true;
    }

    /**
     * Получение данных о новостях.
     *
     * Метод формирует массив данных о новостях в зависимости от типа запроса.
     * Этот метод содержит основную логику, вызываемую через публичный метод news().
     *
     * @see PhotoRigma::Classes::Work_CoreLogic::news() Публичный метод-редирект для вызова этой логики.
     * @see PhotoRigma::Classes::Work_CoreLogic::$db Свойство, содержащее объект базы данных.
     *
     * @param int    $news_id_or_limit Количество новостей или ID новости (в зависимости от параметра $act).
     * @param string $act              Тип запроса:
     *                                 - 'id': Получение новости по её ID.
     *                                 - 'list': Получение списка новостей с сортировкой по дате последнего редактирования.
     *
     * @return array Массив с данными о новостях. Если новостей нет, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный $act или $news_id_or_limit.
     * @throws RuntimeException         Если произошла ошибка при выполнении запроса к базе данных.
     *
     * @example
     * @code
     * // Пример использования метода news()
     * $news_by_id = $this->news(5, 'id'); // Получение новости с ID 5
     * print_r($news_by_id);
     *
     * $news_list = $this->news(10, 'list'); // Получение 10 последних новостей
     * print_r($news_list);
     * @endcode
     */
    protected function _news_internal(int $news_id_or_limit, string $act): array
    {
        // Проверка входных данных
        if ($act === 'id') {
            if (!filter_var($news_id_or_limit, FILTER_VALIDATE_INT)) {
                throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный ID новости | Переменная \$news_id_or_limit = $news_id_or_limit"
                );
            }
        } elseif ($act === 'list') {
            if ($news_id_or_limit <= 0 || !filter_var($news_id_or_limit, FILTER_VALIDATE_INT)) {
                throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное количество новостей | Переменная \$news_id_or_limit = $news_id_or_limit"
                );
            }
        } else {
            // Обработка некорректного типа запроса
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный тип запроса | Переменная \$act = '$act'"
            );
        }
        // Формирование параметров запроса через match()
        $query_params = match ($act) {
            'id' => [
                // Формируем запрос для 'id'
                'columns' => ['id', 'title', 'content', 'data_last_edit'],
                'table' => TBL_NEWS,
                'where' => '`id` = :id',
                'params' => [':id' => $news_id_or_limit],
            ],
            'list' => [
                // Формируем запрос для 'list'
                'columns' => ['id', 'title', 'content', 'data_last_edit'],
                'table' => TBL_NEWS,
                'order_by' => ['data_last_edit' => 'DESC'],
                'limit' => $news_id_or_limit,
            ],
        };
        // Выполняем запрос по результатам match()
        $this->db->select(
            $query_params['columns'],
            $query_params['table'],
            array_filter([
                'where' => $query_params['where'] ?? null,
                'params' => $query_params['params'] ?? [],
                'order_by' => $query_params['order_by'] ?? null,
                'limit' => $query_params['limit'] ?? null,
            ])
        );
        // Получение результатов
        $news_results = $this->db->res_arr();
        if (!$news_results) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные из базы данных | Тип запроса: '$act'"
            );
        }
        // Возврат результата
        return $news_results ?: [];
    }

    /**
     * Загружает доступные языки из директории /language/.
     *
     * Метод перебирает все поддиректории в `/language/` и проверяет наличие файла `main.php`
     * в каждой из них. Если файл найден и содержит корректное значение переменной `$lang_name`,
     * язык добавляется в список доступных языков.
     * Этот метод содержит основную логику, вызываемую через публичный метод get_languages().
     *
     * @see PhotoRigma::Classes::Work_CoreLogic::get_languages()
     *      Публичный метод-редирект для вызова этой логики.
     * @see PhotoRigma::Classes::Work_CoreLogic::$config
     *      Свойство, содержащее конфигурацию приложения, включая путь к директории (`site_dir`).
     * @see PhotoRigma::Include::log_in_file
     *      Функция для логирования ошибок.
     *
     * @return array Массив с данными о доступных языках. Каждый элемент массива содержит:
     *               - `value`: Имя директории языка (строка).
     *               - `name`: Название языка из файла `main.php` (строка).
     *
     * @throws RuntimeException Если:
     *                           - Директория `/language/` недоступна или не существует.
     *                           - Ни один язык не найден в указанной директории.
     *
     * @example
     * @code
     * $languages = $databaseDirectory->get_languages();
     * foreach ($languages as $language) {
     *     echo "Язык: " . $language['name'] . " (ID: " . $language['value'] . ")\n";
     * }
     * @endcode
     */
    protected function _get_languages_internal(): array
    {
        $list_languages = [];
        // Нормализуем путь к site_dir и проверяем его существование
        $site_dir = realpath(rtrim($this->config['site_dir'], '/'));
        $language_dir = $site_dir . '/language/';
        if (!is_dir($language_dir) || !is_readable($language_dir)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Директория языков недоступна или не существует | Путь: $language_dir"
            );
        }
        // Проверяем, что директория не пуста
        $iterator = new \DirectoryIterator($language_dir);
        $has_subdirs = false;
        foreach ($iterator as $file) {
            if (!$file->isDot() && $file->isDir()) {
                $has_subdirs = true;
                break;
            }
        }
        if (!$has_subdirs) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Директория языков пуста | Путь: $language_dir"
            );
        }
        // Перебираем поддиректории
        foreach ($iterator as $file) {
            if ($file->isDot() || !$file->isDir()) {
                continue;
            }
            $lang_subdir = $file->getPathname();
            // Проверяем, что директория существует и доступна для чтения
            if (!is_dir($lang_subdir) || !is_readable($lang_subdir)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Поддиректория языка недоступна для чтения | Директория: $lang_subdir"
                );
                continue;
            }
            // Формируем полный путь к main.php и нормализуем его
            $main_php_path = realpath($lang_subdir . '/main.php');
            if ($main_php_path === false || !is_file($main_php_path) || !is_readable($main_php_path)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл main.php отсутствует или недоступен | Директория: $lang_subdir"
                );
                continue;
            }
            // Проверяем, что файл находится внутри разрешенной директории
            if (strncmp($main_php_path, $language_dir, strlen($language_dir)) !== 0) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Подозрительный путь к файлу main.php | Директория: $lang_subdir"
                );
                continue;
            }
            // Безопасное подключение файла
            $lang_name = null;
            include($main_php_path);
            if (!is_string($lang_name) || trim($lang_name) === '') {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Переменная \$lang_name не определена или некорректна | Файл: $main_php_path"
                );
                continue;
            }
            $list_languages[] = [
                'value' => $file->getFilename(),
                'name' => mb_trim($lang_name),
            ];
        }
        if (empty($list_languages)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ни один язык не найден | Путь: $language_dir"
            );
        }
        return $list_languages;
    }

    /**
     * Загружает доступные темы из директории /themes/.
     *
     * Метод перебирает все поддиректории в `/themes/` и добавляет их имена в список доступных тем.
     * Этот метод содержит основную логику, вызываемую через публичный метод get_themes().
     *
     * @see PhotoRigma::Classes::Work_CoreLogic::get_themes()
     *      Публичный метод-редирект для вызова этой логики.
     * @see PhotoRigma::Classes::Work_CoreLogic::$config
     *      Свойство, содержащее конфигурацию приложения, включая путь к директории (`site_dir`).
     * @see PhotoRigma::Include::log_in_file
     *      Функция для логирования ошибок.
     *
     * @return array Массив с именами доступных тем.
     *
     * @throws RuntimeException Если:
     *                           - Директория `/themes/` не существует или недоступна для чтения.
     *                           - Ни одна тема не найдена в указанной директории.
     */
    protected function _get_themes_internal(): array
    {
        $list_themes = [];
        // Нормализуем путь к site_dir
        $site_dir = realpath(rtrim($this->config['site_dir'], '/'));
        $themes_dir = $site_dir . '/themes/';
        // Проверяем существование и доступность директории /themes/
        if (!is_dir($themes_dir) || !is_readable($themes_dir)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Директория тем недоступна | Путь: $themes_dir"
            );
        }
        // Проверяем, что директория не пуста
        $iterator = new \DirectoryIterator($themes_dir);
        $has_subdirs = false;
        foreach ($iterator as $file) {
            if (!$file->isDot() && $file->isDir()) {
                $has_subdirs = true;
                break;
            }
        }
        if (!$has_subdirs) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Директория тем пуста | Путь: $themes_dir"
            );
        }
        // Перебираем поддиректории
        foreach ($iterator as $file) {
            // Пропускаем точки (.) и файлы
            if ($file->isDot() || !$file->isDir()) {
                continue;
            }
            // Получаем нормализованный путь к поддиректории
            $theme_dir = $file->getRealPath();
            // Проверяем, что директория доступна для чтения
            if (!is_readable($theme_dir)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Поддиректория темы недоступна для чтения | Директория: $theme_dir"
                );
                continue;
            }
            // Проверяем, что директория находится внутри $themes_dir
            if (strncmp($theme_dir, $themes_dir, strlen($themes_dir)) !== 0) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Подозрительная директория темы | Директория: $theme_dir"
                );
                continue;
            }
            // Добавляем имя папки в список тем
            $list_themes[] = $file->getFilename();
        }
        // Если ни одна тема не найдена, выбрасываем исключение
        if (empty($list_themes)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ни одна тема не найдена | Путь: $themes_dir"
            );
        }
        return $list_themes;
    }

    /**
     * Генерирует блок вывода изображений для различных типов запросов.
     *
     * Метод выполняет следующие действия:
     * 1. Проверяет права пользователя на просмотр изображений.
     * 2. Формирует SQL-запрос для получения данных изображения и пользователя через JOIN:
     *    - Для типа 'top': Выбирает лучшее изображение с учетом рейтинга.
     *    - Для типа 'last': Выбирает последнее загруженное изображение.
     *    - Для типа 'cat': Выбирает изображение из конкретной категории по $id_photo.
     *    - Для случайного типа: Выбирает любое случайное изображение.
     * 3. Проверяет существование файла изображения и его доступность.
     * 4. Вычисляет размеры изображения через метод size_image().
     * 5. Возвращает массив данных для вывода изображения.
     * Этот метод содержит основную логику, вызываемую через публичный метод create_photo().
     *
     * @param string $type Тип изображения:
     *                     - 'top': Лучшее изображение (по рейтингу).
     *                     - 'last': Последнее загруженное изображение.
     *                     - 'cat': Изображение из конкретной категории (требует указания $id_photo).
     *                     - Случайное: Любое случайное изображение.
     * @param int    $id_photo Идентификатор фото. Используется только при $type == 'cat'.
     *
     * @return array Массив данных для вывода изображения. Содержит следующие ключи:
     *               - 'name_block': Название блока изображения (например, "Лучшее фото").
     *               - 'url': URL для просмотра полного изображения.
     *               - 'thumbnail_url': URL для миниатюры изображения.
     *               - 'name': Название изображения.
     *               - 'category_name': Название категории.
     *               - 'description': Описание изображения.
     *               - 'category_description': Описание категории.
     *               - 'rate': Рейтинг изображения (например, "Рейтинг: 5/10").
     *               - 'url_user': URL профиля пользователя, добавившего изображение.
     *               - 'real_name': Реальное имя пользователя.
     *               - 'category_url': URL категории или пользовательского альбома.
     *               - 'width': Ширина изображения после масштабирования.
     *               - 'height': Высота изображения после масштабирования.
     *
     * @throws InvalidArgumentException Если передан недопустимый $type или $id_photo.
     * @throws RuntimeException         Если произошла ошибка при выборке данных из базы данных или доступе к файлу.
     *
     * @see PhotoRigma::Classes::Work_CoreLogic::create_photo() Публичный метод-редирект для вызова этой логики.
     * @see PhotoRigma::Classes::Work_CoreLogic::$config Свойство, содержащее конфигурацию приложения.
     * @see PhotoRigma::Classes::Work_CoreLogic::$user Свойство, содержащее данные текущего пользователя.
     * @see PhotoRigma::Classes::Work_CoreLogic::generate_photo_data() Приватный метод для формирования массива данных по умолчанию.
     * @see PhotoRigma::Classes::Work::size_image() Метод, используемый для вычисления размеров изображения.
     * @see PhotoRigma::Classes::Database::join() Метод, используемый для объединения данных из нескольких таблиц.
     * @see PhotoRigma::Classes::Database::res_row() Метод, используемый для получения одной строки результата.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     * @see PhotoRigma::Classes::Work::clean_field() Метод для очистки данных.
     */
    protected function _create_photo_internal(string $type, int $id_photo): array
    {
        // Валидация входных параметров
        if (!in_array($type, ['top', 'last', 'cat'], true) && !is_string($type)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый тип запроса | Получено: '$type'"
            );
        }
        if ($type === 'cat' && (!is_int($id_photo) || $id_photo <= 0)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый идентификатор фото для типа 'cat' | Переменная \$id_photo = $id_photo"
            );
        }
        // Проверяем права пользователя на просмотр изображений
        if ($this->user->user['pic_view'] !== true) {
            return $this->generate_photo_data();
        }
        // Формируем условия для SQL-запроса
        $conditions = match ($type) {
            'top' => ['where' => 'rate_user != :rate', 'order' => 'rate_user DESC', 'params' => [':rate' => 0]],
            'last' => ['order' => 'date_upload DESC'],
            'cat' => ['where' => 'id = :id', 'params' => [':id' => $id_photo]],
            default => ['order' => 'RAND()'],
        };
        $where = $conditions['where'] ?? null;
        $order = $conditions['order'] ?? null;
        $params = $conditions['params'] ?? [];
        // Получаем данные изображения и пользователя через JOIN
        $this->db->join(
            ['photos.*', 'users.real_name', 'categories.id as category_id', 'categories.name as category_name', 'categories.folder'],
            TBL_PHOTO,
            [
                ['type' => 'LEFT', 'table' => TBL_USERS, 'on' => 'photos.user_upload = users.id'],
                ['type' => 'LEFT', 'table' => TBL_CATEGORY, 'on' => 'photos.category = categories.id'],
            ],
            [
                'where' => $where,
                'order' => $order,
                'limit' => 1,
                'params' => $params,
            ]
        );
        $photo_data = $this->db->res_row();
        if (!$photo_data) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при выборке данных из таблицы фото | Тип: '$type'"
            );
        }
        // Формируем путь к файлу изображения
        $gallery_directory = realpath($this->config['site_dir'] . $this->config['gallery_folder']);
        $image_path = realpath($this->config['site_dir'] . $this->config['gallery_folder'] . '/' . $photo_data['file']);
        if (!$image_path || !str_starts_with($image_path, $gallery_directory)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Запрещенный доступ к файлу | Путь: '$image_path'"
            );
        }
        if (!file_exists($image_path) || !is_readable($image_path)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл изображения недоступен или не существует | Путь: '$image_path' | Тип: '$type'"
            );
            return $this->generate_photo_data();
        }
        // Вычисляем размеры изображения
        $image_size = $this->work->size_image($image_path);
        // Наполняем массив данными для вывода изображения
        $photo_data = [
            'url' => sprintf('%s?action=photo&amp;id=%d', $this->config['site_url'], $photo_data['id']),
            'thumbnail_url' => sprintf('%s?action=attach&amp;foto=%d&amp;thumbnail=1', $this->config['site_url'], $photo_data['id']),
            'name' => Work::clean_field($photo_data['name']),
            'category_name' => $photo_data['category_name']
                ? Work::clean_field($photo_data['category_name'])
                : $this->lang['main']['no_category'],
            'description' => Work::clean_field($photo_data['description']),
            'category_description' => $photo_data['category_name']
                ? Work::clean_field($photo_data['category_name'])
                : $this->lang['main']['no_category'],
            'rate' => $this->lang['main']['rate'] . ': ' . $photo_data['rate_user'] . '/' . $photo_data['rate_moder'],
            'url_user' => $photo_data['real_name']
                ? sprintf('%s?action=profile&amp;subact=profile&amp;uid=%d', $this->config['site_url'], $photo_data['user_upload'])
                : null,
            'real_name' => $photo_data['real_name']
                ? Work::clean_field($photo_data['real_name'])
                : $this->lang['main']['no_user_add'],
            'width' => $image_size['width'],
            'height' => $image_size['height'],
            'name_block' => $this->lang['main'][$type . '_foto'] ?? $this->lang['main']['no_foto'],
            'category_url' => $photo_data['category_id'] == 0
                ? sprintf('%s?action=category&amp;cat=user&amp;id=%d', $this->config['site_url'], $photo_data['user_upload'])
                : sprintf('%s?action=category&amp;cat=%d', $this->config['site_url'], $photo_data['category_id']),
        ];
        // Возвращаем результат через generate_photo_data()
        return $this->generate_photo_data($photo_data);
    }

    /**
     * @brief Генерация массива данных для вывода изображения.
     *
     * @details Метод формирует массив данных для вывода изображения, используя значения по умолчанию
     *          или данные, переданные в параметре $photo_data. Если какое-либо значение отсутствует
     *          в $photo_data, используется соответствующее значение по умолчанию. Значения по умолчанию
     *          берутся из конфигурации приложения ($this->config) и языковых переменных ($this->lang).
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work_CoreLogic::$config
     *      Свойство, содержащее конфигурацию приложения.
     * @see PhotoRigma::Classes::Work_CoreLogic::$lang
     *      Свойство, содержащее языковые переменные.
     * @see PhotoRigma::Classes::Work_CoreLogic::_create_photo_internal()
     *      Метод, вызывающий этот приватный метод.
     *
     * @param array $photo_data Массив данных изображения, полученных из базы данных.
     *                          Может быть пустым, если требуется сгенерировать массив
     *                          только со значениями по умолчанию.
     *
     * @return array Массив данных для вывода изображения. Содержит следующие ключи:
     *               - 'url' (string): URL для просмотра полного изображения.
     *               - 'thumbnail_url' (string): URL для миниатюры изображения.
     *               - 'name' (string): Название изображения.
     *               - 'description' (string): Описание изображения.
     *               - 'category_name' (string): Название категории.
     *               - 'category_description' (string): Описание категории.
     *               - 'rate' (string): Рейтинг изображения (например, "Рейтинг: 0/0").
     *               - 'url_user' (string|null): URL профиля пользователя, добавившего изображение.
     *               - 'real_name' (string): Реальное имя пользователя.
     *
     * @throws InvalidArgumentException Если $photo_data не является массивом.
     *      Пример сообщения:
     *          Аргумент $photo_data должен быть массивом. Получено: [тип]
     *
     * @warning Метод зависит от конфигурации приложения ($this->config) и языковых переменных ($this->lang).
     *          Убедитесь, что эти свойства правильно инициализированы перед вызовом метода.
     *
     * Пример вызова метода внутри класса:
     * @code
     * // Пример генерации массива данных только со значениями по умолчанию
     * $default_photo = $this->generate_photo_data();
     * print_r($default_photo);
     *
     * // Пример с передачей данных
     * $photo_data = [
     *     'name' => 'Лучшее фото',
     *     'description' => 'Описание лучшего фото',
     * ];
     * $photo_block = $this->generate_photo_data($photo_data);
     * print_r($photo_block);
     * @endcode
     */
    private function generate_photo_data(array $photo_data = []): array
    {
        // Значения по умолчанию
        $defaults = [
            'url' => sprintf('%s?action=photo&amp;id=0', $this->config['site_url']),
            'thumbnail_url' => sprintf('%s?action=attach&amp;foto=0&amp;thumbnail=1', $this->config['site_url']),
            'name' => $this->lang['main']['no_foto'],
            'description' => $this->lang['main']['no_foto'],
            'category_name' => $this->lang['main']['no_category'],
            'category_description' => $this->lang['main']['no_category'],
            'rate' => $this->lang['main']['rate'] . ': 0/0',
            'url_user' => null,
            'real_name' => $this->lang['main']['no_user_add'],
        ];

        // Формируем массив данных, используя значения из $photo_data или значения по умолчанию
        $photo = [];
        foreach ($defaults as $key => $default_value) {
            $photo[$key] = $photo_data[$key] ?? $default_value;
        }

        return $photo;
    }
}
