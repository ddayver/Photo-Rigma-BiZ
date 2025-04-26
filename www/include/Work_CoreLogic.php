<?php

/**
 * @file        include/Work_CoreLogic.php
 * @brief       Файл содержит класс Work_CoreLogic, который отвечает за выполнение базовой логики приложения.
 *
 * @author      Dark Dayver
 * @version     0.4.2
 * @date        2025-04-27
 * @namespace   Photorigma\\Classes
 *
 * @details     Этот файл содержит класс `Work_CoreLogic`, который реализует интерфейс `Work_CoreLogic_Interface`.
 *              Класс предоставляет методы для выполнения ключевых операций приложения, таких как:
 *              - Работа с категориями и альбомами (метод `category`).
 *              - Управление изображениями (методы `del_photo`, `create_photo`).
 *              - Получение данных о новостях, языках и темах (методы `news`, `get_languages`, `get_themes`).
 *              Все методы зависят от конфигурации приложения и данных, полученных из базы данных.
 *
 * @section     Основные функции
 *              - Формирование информационных строк для категорий и пользовательских альбомов.
 *              - Удаление изображений и связанных данных.
 *              - Получение данных о новостях, языках и темах.
 *              - Генерация блоков вывода изображений для различных типов запросов.
 *              - Обработка оценок и расчёт средней оценки.
 *
 * @see         PhotoRigma::Interfaces::Work_CoreLogic_Interface Интерфейс, который реализует данный класс.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в выполнении базовой логики
 *              приложения.
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

use DirectoryIterator;
use Exception;
use InvalidArgumentException;
use PDOException;
use PhotoRigma\Interfaces\Database_Interface;
use PhotoRigma\Interfaces\User_Interface;
use PhotoRigma\Interfaces\Work_CoreLogic_Interface;
use PhotoRigma\Interfaces\Work_Interface;
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
 * @class   Work_CoreLogic
 * @brief   Класс для выполнения базовой логики приложения.
 *
 * @details Этот класс реализует интерфейс `Work_CoreLogic_Interface` и предоставляет функционал для выполнения
 *          базовой логики приложения. Он является дочерним классом для `PhotoRigma::Classes::Work` и наследует его
 *          методы. Все методы данного класса рекомендуется вызывать через родительский класс `Work`, так как их
 *          поведение может быть непредсказуемым при прямом вызове.
 *          Основные возможности:
 *          - Формирование информационных строк для категорий и пользовательских альбомов.
 *          - Удаление изображений и связанных данных.
 *          - Получение данных о новостях, языках и темах.
 *          - Генерация блоков вывода изображений для различных типов запросов.
 *          - Обработка оценок и расчёт средней оценки.
 *          Все ошибки, возникающие при работе с данными, обрабатываются через исключения.
 *
 * @property array               $config Конфигурация приложения.
 * @property array|null          $lang   Языковые данные (могут быть null при инициализации).
 * @property Database_Interface  $db     Объект для работы с базой данных (обязательный).
 * @property Work_Interface      $work   Основной объект приложения (обязательный).
 * @property User_Interface|null $user   Объект пользователя (может быть null при инициализации).
 *
 * Пример использования класса:
 * @code
 * // Инициализация объекта Work_CoreLogic
 * $db = new \\PhotoRigma\\Classes\\Database();
 * $config = ['site_dir' => '/path/to/site', 'gallery_folder' => '/images'];
 * $work = new \\PhotoRigma\\Classes\\Work();
 *
 * $core_logic = new \\PhotoRigma\\Classes\\Work_CoreLogic($db, $config, $work);
 *
 * // Пример вызова метода create_photo через родительский класс Work
 * $top_photo = $work->create_photo('top', 0);
 * print_r($top_photo);
 * @endcode
 * @see     PhotoRigma::Interfaces::Work_CoreLogic_Interface Интерфейс, который реализует данный класс.
 */
class Work_CoreLogic implements Work_CoreLogic_Interface
{
    // Свойства:
    private array $config; ///< Конфигурация приложения.
    private ?array $lang = null; ///< Языковые данные (могут быть null при инициализации).
    private Database_Interface $db; ///< Объект для работы с базой данных (обязательный).
    private Work_Interface $work; ///< Основной объект приложения (обязательный).
    private ?User_Interface $user = null; ///< Объект пользователя (может быть null при инициализации).

    /**
     * @brief   Конструктор класса.
     *
     * @details Инициализирует зависимости: конфигурацию, базу данных и объект класса Work.
     *          Этот класс является дочерним для `PhotoRigma::Classes::Work`.
     *          Все параметры обязательны для корректной работы класса.
     *          Алгоритм работы:
     *          1. Сохраняет переданные зависимости в соответствующие свойства:
     *             - `$config`: массив конфигурации приложения.
     *             - `$db`: объект для работы с базой данных.
     *             - `$work`: основной объект приложения.
     *          2. Проверяет, что все зависимости корректно инициализированы.
     *          Метод вызывается автоматически при создании нового объекта класса.
     *
     * @callgraph
     *
     * @param array              $config Конфигурация приложения.
     *                                   Должен быть ассоциативным массивом.
     *                                   Пример: `['temp_photo_w' => 800]`.
     * @param Database_Interface $db     Объект для работы с базой данных.
     *                                   Должен реализовывать интерфейс `Database_Interface`.
     *                                   Пример: `$db = new Database();`.
     * @param Work_Interface     $work   Основной объект приложения.
     *                                   Должен реализовывать интерфейс `Work_Interface`.
     *                                   Пример: `$work = new Work();`.
     *
     * @note    Важно: все зависимости должны быть корректно инициализированы перед использованием класса.
     *
     * @warning Убедитесь, что:
     *          - `$config` является ассоциативным массивом.
     *          - `$db` реализует интерфейс `Database_Interface`.
     *          - `$work` реализуеет интерфейс `Work_Interface`.
     *          Несоблюдение этих условий может привести к ошибкам инициализации.
     *
     * Пример использования конструктора:
     * @code
     * $config = ['temp_photo_w' => 800];
     * $db = new Database();
     * $work = new Work();
     * $corelogic = new \PhotoRigma\Classes\Work_CoreLogic($config, $db, $work);
     * @endcode
     * @see     PhotoRigma::Classes::Work
     *         Родительский класс, через который передаются зависимости.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$config
     *         Свойство, содержащее конфигурацию приложения.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$db
     *         Свойство, содержащее объект для работы с базой данных.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$work
     *         Свойство, содержащее основной объект приложения.
     */
    public function __construct(Database_Interface $db, array $config, Work_Interface $work)
    {
        $this->config = $config;
        $this->db = $db;
        $this->work = $work;
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
     * @callgraph
     *
     * @param string $name Имя свойства:
     *                     - Допустимое значение: 'config'.
     *                     - Если указано другое имя, выбрасывается исключение.
     *                     Пример: 'config'.
     *                     Ограничения: только одно допустимое значение.
     *
     * @return array Значение свойства `$config`.
     *               Пример:
     *               - `['temp_photo_w' => 800]`.
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
     * $corelogic = new \PhotoRigma\Classes\Work_CoreLogic(['temp_photo_w' => 800], $db, $work);
     * echo $corelogic->config['temp_photo_w']; // Выведет: 800
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::$config
     *         Свойство, к которому обращается метод.
     */
    public function __get(string $name): array
    {
        if ($name === 'config') {
            return $this->config;
        }
        throw new InvalidArgumentException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не существует | Получено: '$name'"
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
     * @callgraph
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
     *          Любые другие запросы будут игнорироваться с выбросом исключения.
     *
     * @warning Убедитесь, что вы запрашиваете только допустимое свойство:
     *          - 'config'
     *          Некорректные имена свойств вызовут исключение.
     *
     * Пример использования метода:
     * @code
     * $corelogic = new \PhotoRigma\Classes\Work_CoreLogic([], $db, $work);
     * $corelogic->config = ['temp_photo_w' => 1024];
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::$config
     *         Свойство, которое изменяет метод.
     */
    public function __set(string $name, array $value): void
    {
        if ($name === 'config') {
            $this->config = $value;
        } else {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не может быть установлено | Получено: '$name'"
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
     * @callgraph
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
     * $work_corelogic = new \PhotoRigma\Classes\Work_CoreLogic();
     * if (isset($work_corelogic->config)) {
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
     * @brief   Формирует информационную строку для категории или пользовательского альбома через вызов внутреннего
     *          метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _category_internal().
     *          Он выполняет запросы к базе данных для получения информации о категории или пользовательском альбоме,
     *          включая подсчёт фотографий, получение данных о последней и лучшей фотографии, а также формирование
     *          результирующего массива с информацией. Метод также доступен через метод-фасад `category()` в
     *          родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым, так как он зависит от внутренней логики
     *          и настроек родительского класса.
     *
     * @param int $cat_id    Идентификатор категории или пользователя (если `$user_flag = 1`):
     *                       - Должен быть целым числом >= `0`.
     *                       - Пример: `5` (для категории) или `123` (для пользовательского альбома).
     * @param int $user_flag Флаг, указывающий формировать ли информацию о категории (`0`) или пользовательском альбоме
     *                       (`1`):
     *                       - По умолчанию: `0`.
     *                       - Допустимые значения: `0` или `1`.
     *
     * @return array Информационная строка для категории или пользовательского альбома:
     *               - 'name'           (string): Название категории или альбома.
     *               - 'description'    (string): Описание категории или альбома.
     *               - 'count_photo'    (int):    Количество фотографий.
     *               - 'last_photo'     (string): Форматированное название последней фотографии (например, "Название
     *               (Описание)").
     *               - 'top_photo'      (string): Форматированное название лучшей фотографии (например, "Название
     *               (Описание)").
     *               - 'url_cat'        (string): Ссылка на категорию или альбом.
     *               - 'url_last_photo' (string): Ссылка на последнюю фотографию.
     *               - 'url_top_photo'  (string): Ссылка на лучшую фотографию.
     *
     * @throws InvalidArgumentException Если входные параметры имеют некорректный тип или значение.
     *                                  Пример сообщения:
     *                                      cat_id и user_flag должны быть 0 или положительным целым числом.
     * @throws PDOException            Если возникают ошибки при получении данных из базы данных.
     *                                  Пример сообщения:
     *                                      Не удалось получить данные категории или пользователя.
     * @throws Exception               При выполнении запросов к базам данных.
     *
     * @note    Используются константы:
     *          - TBL_CATEGORY: Таблица для хранения данных о категориях (`category`).
     *          - TBL_USERS:    Таблица для хранения данных о пользователях (`users`).
     *          - TBL_PHOTO:    Таблица для хранения данных о фотографиях (`photo`).
     *
     * @warning Убедитесь, что:
     *           - Входные параметры `$cat_id` и `$user_flag` корректны.
     *           - База данных содержит необходимые данные для выполнения запросов.
     *           - Пользователь имеет права на просмотр фотографий (если это требуется).
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым, так как он зависит от внутренней логики
     *          и настроек родительского класса.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра дочернего класса
     * $childObject = new \PhotoRigma\Classes\Work_CoreLogic();
     *
     * // Получение данных о категории с ID = 5 через дочерний класс
     * $category_data = $childObject->category(5, 0);
     * print_r($category_data);
     *
     * // Получение данных о пользовательском альбоме с ID = 123 через дочерний класс
     * $user_album_data = $childObject->category(123, 1);
     * print_r($user_album_data);
     * @endcode
     *
     * @see     PhotoRigma::Classes::Work_CoreLogic::_category_internal()
     *          Защищённый метод, реализующий основную логику формирования информационной строки.
     * @see     PhotoRigma::Classes::Work::category()
     *          Метод-фасад в родительском классе для вызова этой логики.
     */
    public function category(int $cat_id = 0, int $user_flag = 0): array
    {
        return $this->_category_internal($cat_id, $user_flag);
    }

    /**
     * @brief   Формирует информационную строку для категории или пользовательского альбома.
     *
     * @details Этот метод выполняет запросы к базе данных для получения информации о категории или пользовательском
     *          альбоме. Алгоритм работы:
     *          1. Проверяет корректность входных параметров:
     *             - `$cat_id` должен быть целым числом >= `0`.
     *             - `$user_flag` должен принимать значения `0` (категория) или `1` (пользовательский альбом).
     *          2. Получает данные о категории из таблицы `TBL_CATEGORY` или данные пользователя из таблицы `TBL_USERS`
     *             (если `$user_flag = 1`).
     *          3. Подсчитывает количество фотографий в таблице `TBL_PHOTO` с использованием JOIN-запросов.
     *          4. Получает данные о последней и лучшей фотографии (если разрешено отображение фотографий).
     *          5. Для корневой категории (`$cat_id = 0`) подсчитывает количество уникальных пользователей, загрузивших
     *             фотографии.
     *          6. Формирует результирующий массив с информацией о категории или альбоме, включая название, описание,
     *             количество фотографий, данные о последней и лучшей фотографии, а также ссылки на них.
     *          Метод является защищенным и вызывается через публичный метод `category()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param int $cat_id       Идентификатор категории или пользователя (если `$user_flag = 1`).
     *                          Должен быть целым числом >= `0`.
     *                          Пример: `5` (для категории) или `123` (для пользовательского альбома).
     * @param int $user_flag    Флаг, указывающий формировать ли информацию о категории (`0`) или пользовательском
     *                          альбоме (`1`).
     *                          По умолчанию: `0`.
     *                          Допустимые значения: `0` или `1`.
     *
     * @return array Информационная строка для категории или пользовательского альбома:
     *               - 'name'           (string): Название категории или альбома.
     *               - 'description'    (string): Описание категории или альбома.
     *               - 'count_photo'    (int):    Количество фотографий.
     *               - 'last_photo'     (string): Форматированное название последней фотографии (например, "Название
     *               (Описание)").
     *               - 'top_photo'      (string): Форматированное название лучшей фотографии (например, "Название
     *               (Описание)").
     *               - 'url_cat'        (string): Ссылка на категорию или альбом.
     *               - 'url_last_photo' (string): Ссылка на последнюю фотографию.
     *               - 'url_top_photo'  (string): Ссылка на лучшую фотографию.
     *
     * @throws InvalidArgumentException Если входные параметры имеют некорректный тип или значение.
     *                                  Пример сообщения:
     *                                      cat_id и user_flag должны быть 0 или положительным целым числом.
     * @throws PDOException            Если возникают ошибки при получении данных из базы данных.
     *                                  Пример сообщения:
     *                                      Не удалось получить данные категории или пользователя.
     * @throws Exception               При выполнении запросов к базам данных.
     *
     * @note    Используются константы:
     *          - TBL_CATEGORY: Таблица для хранения данных о категориях (`category`).
     *          - TBL_USERS:    Таблица для хранения данных о пользователях (`users`).
     *          - TBL_PHOTO:    Таблица для хранения данных о фотографиях (`photo`).
     *
     * @warning Убедитесь, что:
     *          - Входные параметры `$cat_id` и `$user_flag` корректны.
     *          - База данных содержит необходимые данные для выполнения запросов.
     *          - Пользователь имеет права на просмотр фотографий (если это требуется).
     *
     * Пример вызова метода внутри класса или наследника:
     * @code
     * // Получение данных о категории с ID = 5
     * $category_data = $this->_category_internal(5, 0);
     * print_r($category_data);
     *
     * // Получение данных о пользовательском альбоме с ID = 123
     * $user_album_data = $this->_category_internal(123, 1);
     * print_r($user_album_data);
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::category()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$db
     *          Свойство, содержащее объект для работы с базой данных.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$lang
     *          Свойство, содержащее языковые строки.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$user
     *          Свойство, содержащее данные текущего пользователя.
     * @see     PhotoRigma::Classes::Work::clean_field()
     *          Метод для очистки данных.
     */
    protected function _category_internal(int $cat_id = 0, int $user_flag = 0): array
    {
        // Проверка аргументов
        if ($cat_id < 0 || $user_flag < 0) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Неверный аргумент | ' . 'cat_id и user_flag должны быть 0 или положительным целым числом'
            );
        }

        $photo_info = [];

        // Получение данных категории
        if ($user_flag === 1) {
            // Получение категории с id = 0
            $this->db->select(
                ['`id`', '`name`'],
                TBL_CATEGORY,
                ['where' => '`id` = :id', 'params' => [':id' => 0]]
            );
            $category_data = $this->db->res_row();
            if (!$category_data) {
                throw new PDOException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Ошибка базы данных | ' . 'Не удалось получить данные категории'
                );
            }

            // Получение данных пользователя
            $this->db->select(
                '`real_name`',
                TBL_USERS,
                ['where' => '`id` = :id', 'params' => [':id' => $cat_id]]
            );
            $user_data = $this->db->res_row();
            if (!$user_data) {
                throw new PDOException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Ошибка базы данных | ' . 'Не удалось получить данные пользователя с ID: ' . $cat_id
                );
            }

            // Обновление данных категории с учетом данных пользователя
            $category_data['description'] = $category_data['name'] . ' ' . $user_data['real_name'];
            $category_data['name'] = $user_data['real_name'];
        } else {
            // Получение категории по id
            $this->db->select(
                ['`id`', '`name`', '`description`'],
                TBL_CATEGORY,
                ['where' => '`id` = :id', 'params' => [':id' => $cat_id]]
            );
            $category_data = $this->db->res_row();
            if (!$category_data) {
                throw new PDOException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Ошибка базы данных | ' . 'Не удалось получить данные категории'
                );
            }
        }

        // Экранирование текстовых данных
        $category_data['name'] = Work::clean_field($category_data['name']);
        $category_data['description'] = Work::clean_field($category_data['description']);

        // Получение данных о фотографиях
        $select = [
            'COUNT(DISTINCT p.`id`) AS `num_photo`',
            'p1.`id` AS `latest_photo_id`',
            'p1.`name` AS `latest_photo_name`',
            'p1.`description` AS `latest_photo_description`',
            'p2.`id` AS `top_rated_photo_id`',
            'p2.`name` AS `top_rated_photo_name`',
            'p2.`description` AS `top_rated_photo_description`',
        ];

        $from_tbl = TBL_PHOTO . ' p';
        $join = [
            [
                'table' => TBL_PHOTO . ' p1',
                'type'  => 'LEFT',
                'on'    => 'p.`category` = p1.`category` AND p1.`date_upload` = (SELECT MAX(`date_upload`) FROM ' . TBL_PHOTO . ' WHERE `category` = p.`category`)',
            ],
            [
                'table' => TBL_PHOTO . ' p2',
                'type'  => 'LEFT',
                'on'    => 'p.`category` = p2.`category` AND p2.`rate_user` = (SELECT MAX(`rate_user`) FROM ' . TBL_PHOTO . ' WHERE `category` = p.`category` AND `rate_user` != 0)',
            ],
        ];

        $options = [
            'where'  => ['p.`category` = :category'],
            'params' => [':category' => $category_data['id']],
        ];

        if ($user_flag === 1) {
            $options['where'] = 'p.`category` = :category AND p.`user_upload` = :user_upload';
            $options['params'][':user_upload'] = $cat_id;
        }

        $this->db->join($select, $from_tbl, $join, $options);
        $photo_data = $this->db->res_row();
        if (!$photo_data) {
            throw new PDOException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Ошибка базы данных | ' . 'Не удалось получить данные фотографий для категории с ID: ' . $category_data['id']
            );
        }

        $latest_photo_data = false;
        $top_rated_photo_data = false;
        $photo_info['count'] = $photo_data['num_photo'];

        if ($photo_data['latest_photo_id']) {
            $latest_photo_data = [
                'id'          => $photo_data['latest_photo_id'],
                'name'        => Work::clean_field($photo_data['latest_photo_name']),
                'description' => Work::clean_field($photo_data['latest_photo_description']),
            ];
        }

        if ($photo_data['top_rated_photo_id']) {
            $top_rated_photo_data = [
                'id'          => $photo_data['top_rated_photo_id'],
                'name'        => Work::clean_field($photo_data['top_rated_photo_name']),
                'description' => Work::clean_field($photo_data['top_rated_photo_description']),
            ];
        }

        // Инициализация информации о фотографиях
        $photo_info['last_name'] = $this->lang['main']['no_foto'];
        $photo_info['last_url'] = sprintf('%s?action=photo&amp;id=%d', $this->config['site_url'], 0);
        $photo_info['top_name'] = $this->lang['main']['no_foto'];
        $photo_info['top_url'] = sprintf('%s?action=photo&amp;id=%d', $this->config['site_url'], 0);

        // Обновление информации о фотографиях, если пользователь имеет права на просмотр
        if ($this->user->user['pic_view']) {
            if ($latest_photo_data) {
                $photo_info['last_name'] = Work::clean_field($latest_photo_data['name']) . ' (' . Work::clean_field(
                    $latest_photo_data['description']
                ) . ')';
                $photo_info['last_url'] = sprintf(
                    '%s?action=photo&amp;id=%d',
                    $this->config['site_url'],
                    $latest_photo_data['id']
                );
            }

            if ($top_rated_photo_data) {
                $photo_info['top_name'] = Work::clean_field($top_rated_photo_data['name']) . ' (' . Work::clean_field(
                    $top_rated_photo_data['description']
                ) . ')';
                $photo_info['top_url'] = sprintf(
                    '%s?action=photo&amp;id=%d',
                    $this->config['site_url'],
                    $top_rated_photo_data['id']
                );
            }
        }

        // Обработка категорий с id = 0
        if ($cat_id === 0) {
            $this->db->select(
                'COUNT(DISTINCT `user_upload`) AS `num_user_upload`',
                TBL_PHOTO,
                ['where' => '`category` = :category', 'params' => [':category' => 0]]
            );
            $user_upload_count_data = $this->db->res_row();
            $num_user_upload = $user_upload_count_data['num_user_upload'];
            if ($num_user_upload > 0) {
                $category_data['id'] = 'user';
                $category_data['name'] .= ' (' . $this->lang['category']['count_user_category'] . ': ' . $user_upload_count_data['num_user_upload'] . ')';
            } else {
                $category_data['name'] .= '<br>(' . $this->lang['category']['no_user_category'] . ')';
            }
        } else {
            $num_user_upload = 0;
        }

        // Обновление id категории для пользовательских альбомов
        if ($user_flag === 1) {
            $category_data['id'] = 'user&amp;id=' . $cat_id;
        }

        // Формирование итоговой информации о категории
        return [
            'name'                   => $category_data['name'],
            'description'            => $category_data['description'],
            'count_photo'            => $photo_info['count'],
            'last_photo'             => $photo_info['last_name'],
            'top_photo'              => $photo_info['top_name'],
            'url_cat'                => sprintf(
                '%s?action=category&amp;cat=%s',
                $this->config['site_url'],
                $category_data['id']
            ),
            'url_last_photo'         => $photo_info['last_url'],
            'url_top_photo'          => $photo_info['top_url'],
            'user_upload_count_data' => $num_user_upload,
        ];
    }

    /**
     * @brief   Генерирует блок данных для вывода изображений различных типов через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _create_photo_internal().
     *          Он формирует массив данных для вывода изображения на основе типа (`$type`) и идентификатора категории
     *          или фото (`$id_photo`). Метод также доступен через метод-фасад `create_photo()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @param string $type     Тип изображения:
     *                         - `'top'`: Лучшее изображение (по рейтингу).
     *                         - `'last'`: Последнее загруженное изображение.
     *                         - `'cat'`: Изображение из конкретной категории (требует указания `$id_photo`).
     *                         - `'rand'`: Любое случайное изображение.
     *                         По умолчанию: `'top'`.
     *                         Допустимые значения: `'top'`, `'last'`, `'cat'`, `'rand'`.
     * @param int    $id_photo Идентификатор фото. Используется только при `$type == 'cat'`.
     *                         Должен быть целым числом >= `0`.
     *                         По умолчанию: `0`.
     *
     * @return array Массив данных для вывода изображения:
     *               - `'name_block'`         (string): Название блока изображения (например, "Лучшее фото").
     *               - `'url'`                (string): URL для просмотра полного изображения.
     *               - `'thumbnail_url'`      (string): URL для миниатюры изображения.
     *               - `'name'`               (string): Название изображения.
     *               - `'description'`        (string): Описание изображения.
     *               - `'category_name'`      (string): Название категории.
     *               - `'category_description'` (string): Описание категории.
     *               - `'rate'`               (string): Рейтинг изображения (например, "Рейтинг: 5/10").
     *               - `'url_user'`           (string|null): URL профиля пользователя, добавившего изображение.
     *               - `'real_name'`          (string): Реальное имя пользователя.
     *               - `'category_url'`       (string): URL категории или пользовательского альбома.
     *               - `'width'`              (int): Ширина изображения после масштабирования.
     *               - `'height'`             (int): Высота изображения после масштабирования.
     *
     * @throws InvalidArgumentException Если передан недопустимый `$type` или `$id_photo < 0`.
     *                                  Пример сообщения:
     *                                      Некорректный идентификатор фотографии | Значение: {$id_photo}.
     * @throws PDOException             Если произошла ошибка при выборке данных из базы данных.
     *                                  Пример сообщения:
     *                                      Не удалось получить данные категории с ID: {$photo_data['category']}.
     * @throws RuntimeException         Если файл изображения недоступен или не существует.
     * @throws Exception                При записи ошибок в лог через `log_in_file()`.
     *
     * @note    Используются следующие константы:
     *       - TBL_CATEGORY: Таблица для хранения данных о категориях (`category`).
     *       - TBL_PHOTO: Таблица для хранения данных об изображениях (`photo`).
     *       - TBL_USERS: Таблица для хранения данных о пользователях (`users`).
     *       - VIEW_RANDOM_PHOTO: Представление для выбора случайных изображений (`random_photo`).
     *
     * @warning Метод чувствителен к правам доступа пользователя (`$this->user->user['pic_view']`).
     *          Убедитесь, что пользователь имеет право на просмотр изображений.
     *          Если файл изображения недоступен или не существует, метод возвращает данные по умолчанию через
     *          `generate_photo_data()`.
     *          Проверка пути к файлу изображения гарантирует, что доступ возможен только к файлам внутри
     *          `$this->config['gallery_folder']`.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра дочернего класса
     * $childObject = new \PhotoRigma\Classes\Work_CoreLogic();
     *
     * // Получение данных для вывода лучшего изображения
     * $top_photo = $childObject->create_photo('top', 0);
     * print_r($top_photo);
     *
     * // Получение данных для вывода изображения из категории с ID = 5
     * $category_photo = $childObject->create_photo('cat', 5);
     * print_r($category_photo);
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::_create_photo_internal()
     *          Защищённый метод, реализующий основную логику генерации блока данных для вывода изображений.
     * @see     PhotoRigma::Classes::Work::create_photo()
     *          Метод-фасад в родительском классе для вызова этой логики.
     */
    public function create_photo(string $type = 'top', int $id_photo = 0): array
    {
        return $this->_create_photo_internal($type, $id_photo);
    }

    /**
     * @brief   Генерирует блок данных для вывода изображений различных типов.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет корректность входных параметров:
     *             - `$id_photo` должен быть целым числом >= `0`.
     *             - `$type` должен принимать одно из значений: `'top'`, `'last'`, `'cat'`, `'rand'`.
     *          2. Проверяет права пользователя на просмотр изображений (`$this->user->user['pic_view']`).
     *          3. Формирует SQL-запрос для получения данных изображения и категории через JOIN:
     *             - Для типа `'top'`: Выбирает лучшее изображение с учетом рейтинга.
     *             - Для типа `'last'`: Выбирает последнее загруженное изображение.
     *             - Для типа `'cat'`: Выбирает изображение из конкретной категории по `$id_photo`.
     *             - Для типа `'rand'`: Выбирает любое случайное изображение (использует представление
     *             `VIEW_RANDOM_PHOTO`).
     *          4. Проверяет существование файла изображения и его доступность.
     *          5. Ограничивает доступ к файлам внутри директории `$this->config['gallery_folder']`.
     *          6. Вычисляет размеры изображения через метод `size_image()`.
     *          7. Формирует массив данных для вывода изображения или вызывает `generate_photo_data()` в случае ошибки.
     *          Метод является защищенным и вызывается через публичный метод `create_photo()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $type     Тип изображения:
     *                         - `'top'`: Лучшее изображение (по рейтингу).
     *                         - `'last'`: Последнее загруженное изображение.
     *                         - `'cat'`: Изображение из конкретной категории (требует указания `$id_photo`).
     *                         - `'rand'`: Любое случайное изображение.
     *                         По умолчанию: `'top'`.
     *                         Допустимые значения: `'top'`, `'last'`, `'cat'`, `'rand'`.
     * @param int    $id_photo Идентификатор фото. Используется только при `$type == 'cat'`.
     *                         Должен быть целым числом >= `0`.
     *                         По умолчанию: `0`.
     *
     * @return array Массив данных для вывода изображения:
     *               - `'name_block'`         (string): Название блока изображения (например, "Лучшее фото").
     *               - `'url'`                (string): URL для просмотра полного изображения.
     *               - `'thumbnail_url'`      (string): URL для миниатюры изображения.
     *               - `'name'`               (string): Название изображения.
     *               - `'description'`        (string): Описание изображения.
     *               - `'category_name'`      (string): Название категории.
     *               - `'category_description'` (string): Описание категории.
     *               - `'rate'`               (string): Рейтинг изображения (например, "Рейтинг: 5/10").
     *               - `'url_user'`           (string|null): URL профиля пользователя, добавившего изображение.
     *               - `'real_name'`          (string): Реальное имя пользователя.
     *               - `'category_url'`       (string): URL категории или пользовательского альбома.
     *               - `'width'`              (int): Ширина изображения после масштабирования.
     *               - `'height'`             (int): Высота изображения после масштабирования.
     *
     * @throws InvalidArgumentException Если передан недопустимый `$type` или `$id_photo < 0`.
     *                                  Пример сообщения:
     *                                      Некорректный идентификатор фотографии | Значение: {$id_photo}.
     * @throws PDOException            Если произошла ошибка при выборке данных из базы данных.
     *                                  Пример сообщения:
     *                                      Не удалось получить данные категории с ID: {$photo_data['category']}.
     * @throws RuntimeException         Если файл изображения недоступен или не существует.
     * @throws Exception                При записи ошибок в лог через `log_in_file()`.
     *
     * @note    Используются следующие константы:
     *       - TBL_CATEGORY: Таблица для хранения данных о категориях (`category`).
     *       - TBL_PHOTO: Таблица для хранения данных об изображениях (`photo`).
     *       - TBL_USERS: Таблица для хранения данных о пользователях (`users`).
     *       - VIEW_RANDOM_PHOTO: Представление для выбора случайных изображений (`random_photo`).
     *
     * @warning Метод чувствителен к правам доступа пользователя (`$this->user->user['pic_view']`).
     *          Убедитесь, что пользователь имеет право на просмотр изображений.
     *          Если файл изображения недоступен или не существует, метод возвращает данные по умолчанию через
     *          `generate_photo_data()`.
     *          Проверка пути к файлу изображения гарантирует, что доступ возможен только к файлам внутри
     *          `$this->config['gallery_folder']`.
     *
     * Пример вызова метода внутри класса или наследника:
     * @code
     * // Получение данных для вывода лучшего изображения
     * $top_photo = $this->_create_photo_internal('top', 0);
     * print_r($top_photo);
     *
     * // Получение данных для вывода изображения из категории с ID = 5
     * $category_photo = $this->_create_photo_internal('cat', 5);
     * print_r($category_photo);
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::create_photo()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$config
     *          Свойство, содержащее конфигурацию приложения.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$user
     *          Свойство, содержащее данные текущего пользователя.
     * @see     PhotoRigma::Classes::Work_CoreLogic::generate_photo_data()
     *          Приватный метод для формирования массива данных по умолчанию.
     * @see     PhotoRigma::Classes::Work::size_image()
     *          Метод, используемый для вычисления размеров изображения.
     * @see     PhotoRigma::Include::log_in_file()
     *          Функция для логирования ошибок.
     * @see     PhotoRigma::Classes::Work::clean_field()
     *          Метод для очистки данных.
     */
    protected function _create_photo_internal(string $type = 'top', int $id_photo = 0): array
    {
        // Валидация входных данных $id_photo
        if ($id_photo < 0) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный идентификатор фотографии | Значение: $id_photo"
            );
        }

        // Нормализация значения $type (гарантируем, что оно будет одним из допустимых)
        $type = match ($type) {
            'top', 'last', 'cat', 'rand' => $type,
            default                      => 'rand', // Если тип неизвестен, считаем, что это случайное фото
        };

        // Проверка прав доступа
        if ($this->user->user['pic_view']) {
            // Определение условий выборки
            $options = match ($type) {
                'top'  => [
                    'where' => '`rate_user` != 0',
                    'order' => '`rate_user` DESC',
                    'limit' => 1,
                ],
                'last' => [
                    'order' => '`date_upload` DESC',
                    'limit' => 1,
                ],
                'cat'  => [
                    'where'  => '`id` = :id_photo',
                    'limit'  => 1,
                    'params' => [':id_photo' => $id_photo],
                ],
                'rand' => [
                    'order' => 'rand()',
                    'limit' => 1,
                ],
            };

            // Выполнение запроса к базе данных
            if ($type === 'rand') {
                $this->db->select(
                    '*',
                    VIEW_RANDOM_PHOTO
                );
            } else {
                $this->db->select(
                    '*',
                    TBL_PHOTO,
                    $options
                );
            }
            $photo_data = $this->db->res_row();

            // Если изображение не найдено, возвращаем данные по умолчанию
            if (!$photo_data) {
                $size = $this->work->size_image(
                    $this->config['site_dir'] . $this->config['gallery_folder'] . '/no_foto.png'
                );
                return $this->generate_photo_data(['width' => $size['width'], 'height' => $size['height']], $type);
            }
        } else {
            $size = $this->work->size_image(
                $this->config['site_dir'] . $this->config['gallery_folder'] . '/no_foto.png'
            );
            return $this->generate_photo_data(['width' => $size['width'], 'height' => $size['height']], $type);
        }

        // Получение данных категории
        $this->db->select(
            '*',
            TBL_CATEGORY,
            [
                'where'  => '`id` = :category_id',
                'params' => [':category_id' => $photo_data['category']],
            ]
        );
        $category_data = $this->db->res_row();
        if (!$category_data) {
            throw new PDOException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка базы данных | Не удалось получить данные категории с ID: {$photo_data['category']}"
            );
        }

        // Формирование пути к файлу изображения
        $image_path = $this->config['site_dir'] . $this->config['gallery_folder'] . '/' . ($category_data['folder'] ?? '') . '/' . $photo_data['file'];

        // Ограничение доступа к файлам через $image_path
        $base_dir = realpath($this->config['site_dir'] . $this->config['gallery_folder']);
        $resolved_path = realpath($image_path);
        if (!$resolved_path || !str_starts_with($resolved_path, $base_dir)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка доступа к недопустимому пути | Путь: $image_path"
            );
            $image_path = $this->config['site_dir'] . $this->config['gallery_folder'] . '/no_foto.png';
        }

        // Проверка существования файла
        if (!file_exists($image_path) || !is_readable($image_path)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл не найден или недоступен | Путь: $image_path, Пользователь: " . ($this->user->user['id'] ?? 'неизвестный')
            );
            $image_path = $this->config['site_dir'] . $this->config['gallery_folder'] . '/no_foto.png';
        }

        // Вычисление размеров изображения
        $size = $this->work->size_image($image_path);

        // Получение данных пользователя
        $this->db->select(
            '`real_name`',
            TBL_USERS,
            [
                'where'  => '`id` = :user_id',
                'params' => [':user_id' => $photo_data['user_upload']],
            ]
        );
        $user_data = $this->db->res_row();

        // Формирование массива данных для передачи в generate_photo_data
        $photo_data_for_generate = [
            'name_block'           => $this->lang['main'][$type . '_foto'],
            'url'                  => sprintf(
                '%s?action=photo&amp;id=%d',
                $this->config['site_url'],
                $photo_data['id']
            ),
            'thumbnail_url'        => sprintf(
                '%s?action=attach&amp;foto=%d&amp;thumbnail=1',
                $this->config['site_url'],
                $photo_data['id']
            ),
            'name'                 => Work::clean_field($photo_data['name']),
            'description'          => Work::clean_field($photo_data['description']),
            'category_name'        => Work::clean_field($category_data['name']),
            'category_description' => Work::clean_field($category_data['description']),
            'rate'                 => $this->lang['main']['rate'] . ': ' . $photo_data['rate_user'] . '/' . $photo_data['rate_moder'],
            'url_user'             => $user_data ? sprintf(
                '%s?action=profile&amp;subact=profile&amp;uid=%d',
                $this->config['site_url'],
                $photo_data['user_upload']
            ) : '',
            'real_name'            => $user_data ? Work::clean_field(
                $user_data['real_name']
            ) : $this->lang['main']['no_user_add'],
            'category_url'         => sprintf(
                '%s?action=category&amp;cat=%d',
                $this->config['site_url'],
                $category_data['id']
            ),
            'width'                => $size['width'],
            'height'               => $size['height'],
        ];

        // Генерация данных изображения
        return $this->generate_photo_data($photo_data_for_generate, $type);
    }

    /**
     * @brief   Генерирует массив данных для вывода изображения, используя значения по умолчанию или данные,
     *          переданные в параметре `$photo_data`.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Формирует массив данных для вывода изображения, используя значения по умолчанию из конфигурации
     *             приложения (`$this->config`) и языковых переменных (`$this->lang`).
     *          2. Если параметр `$photo_data` содержит данные, обновляет значения по умолчанию только для существующих
     *             ключей.
     *          3. Возвращает массив данных для вывода изображения.
     *          Метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @param array  $photo_data Массив данных изображения, полученных из базы данных.
     *                           Может быть пустым, если требуется сгенерировать массив только со значениями по
     *                           умолчанию. Пример: `['name' => 'Лучшее фото', 'description' => 'Описание лучшего
     *                           фото']`.
     * @param string $type       Тип изображения: `'top'`, `'last'`, `'cat'` или `'rand'`.
     *                           По умолчанию: `'top'`.
     *                           Пример: `'top'`.
     *
     * @return array Массив данных для вывода изображения:
     *               - 'name_block'         (string): Название блока изображения.
     *               - 'url'                (string): URL для просмотра полного изображения.
     *               - 'thumbnail_url'      (string): URL для миниатюры изображения.
     *               - 'name'               (string): Название изображения.
     *               - 'description'        (string): Описание изображения.
     *               - 'category_name'      (string): Название категории.
     *               - 'category_description' (string): Описание категории.
     *               - 'rate'               (string): Рейтинг изображения (например, "Рейтинг: 0/0").
     *               - 'url_user'           (string|null): URL профиля пользователя, добавившего изображение.
     *               - 'real_name'          (string): Реальное имя пользователя.
     *               - 'category_url'       (string): URL категории.
     *               - 'width'              (int): Ширина изображения.
     *               - 'height'             (int): Высота изображения.
     *
     * @note    Значения по умолчанию берутся из конфигурации приложения (`$this->config`) и языковых переменных
     *          (`$this->lang`).
     *
     * @warning Убедитесь, что:
     *          - Свойства `$this->config` и `$this->lang` правильно инициализированы перед вызовом метода.
     *          - Параметр `$photo_data` содержит корректные ключи, соответствующие массиву значений по умолчанию.
     *          Несоблюдение этих условий может привести к некорректному формированию массива данных.
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
     * $photo_block = $this->generate_photo_data($photo_data, 'top');
     * print_r($photo_block);
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::$config
     *         Свойство, содержащее конфигурацию приложения.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$lang
     *         Свойство, содержащее языковые переменные.
     * @see     PhotoRigma::Classes::Work_CoreLogic::_create_photo_internal()
     *         Метод, вызывающий этот приватный метод.
     */
    private function generate_photo_data(array $photo_data = [], string $type = 'top'): array
    {
        // Значения по умолчанию
        $default_data = [
            'name_block'           => $this->lang['main'][$type . '_foto'],
            'url'                  => sprintf('%s?action=photo&amp;id=0', $this->config['site_url']),
            'thumbnail_url'        => sprintf('%s?action=attach&amp;foto=0&amp;thumbnail=1', $this->config['site_url']),
            'name'                 => $this->lang['main']['no_foto'],
            'description'          => $this->lang['main']['no_foto'],
            'category_name'        => $this->lang['main']['no_category'],
            'category_description' => $this->lang['main']['no_category'],
            'rate'                 => $this->lang['main']['rate'] . ': ' . $this->lang['main']['no_foto'],
            'url_user'             => '',
            'real_name'            => $this->lang['main']['no_user_add'],
            'category_url'         => $this->config['site_url'],
            'width'                => 0,
            'height'               => 0,
        ];

        // Обновление значений по умолчанию данными из $photo_data
        foreach ($default_data as $key => $value) {
            if (isset($photo_data[$key])) {
                $default_data[$key] = $photo_data[$key];
            }
        }

        return $default_data;
    }

    /**
     * @brief   Удаляет изображение с указанным идентификатором через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _del_photo_internal().
     *          Он удаляет файлы изображения, связанные записи из базы данных и логирует ошибки.
     *          Метод также доступен через метод-фасад `del_photo()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @param int $photo_id Идентификатор удаляемого изображения:
     *                      - Должен быть положительным целым числом.
     *                      Пример: `42`.
     *
     * @return bool True, если удаление успешно, иначе False.
     *
     * @throws InvalidArgumentException Если параметр `$photo_id` имеет некорректный тип или значение.
     *                                  Пример сообщения:
     *                                      Неверное значение параметра photo_id | Ожидалось положительное целое число.
     * @throws RuntimeException         Если возникает ошибка при выполнении запросов к базе данных или удалении файлов.
     *                                  Пример сообщения:
     *                                      Не удалось найти изображение | Переменная photo_id = [значение].
     * @throws Exception                При записи ошибок в лог через `log_in_file()`.
     *
     * @note    Используются константы:
     *          - TBL_PHOTO: Таблица для хранения данных об изображениях (`photo`).
     *          - TBL_CATEGORY: Таблица для хранения данных о категориях (`category`).
     *
     * @warning Метод чувствителен к правам доступа при удалении файлов. Убедитесь, что скрипт имеет необходимые права
     *          на запись и чтение.
     *          Удаление файлов и записей из базы данных необратимо. Убедитесь, что передан корректный идентификатор
     *          изображения.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра дочернего класса
     * $childObject = new \PhotoRigma\Classes\Work_CoreLogic();
     *
     * // Удаление изображения с ID = 42
     * $result = $childObject->del_photo(42);
     * if ($result) {
     *     echo "Изображение успешно удалено.";
     * } else {
     *     echo "Не удалось удалить изображение.";
     * }
     * @endcode
     *
     * @see     PhotoRigma::Classes::Work_CoreLogic::_del_photo_internal()
     *          Защищённый метод, реализующий основную логику удаления изображения.
     * @see     PhotoRigma::Classes::Work::del_photo()
     *          Метод-фасад в родительском классе для вызова этой логики.
     */
    public function del_photo(int $photo_id): bool
    {
        return $this->_del_photo_internal($photo_id);
    }

    /**
     * @brief   Удаляет изображение с указанным идентификатором, а также все упоминания об этом изображении в таблицах
     *          сайта.
     *
     * @details Метод выполняет удаление изображения с указанным идентификатором, включая следующие шаги:
     *          1. Проверяет корректность входного параметра photo_id.
     *          2. Получает данные об изображении и категории через JOIN запрос к таблицам TBL_PHOTO и TBL_CATEGORY.
     *          3. Удаляет файлы из каталогов полноразмерных изображений и эскизов, используя пути, заданные в
     *          конфигурации
     *             (this->config). Перед удалением проверяется существование файлов.
     *          4. Удаляет запись об изображении из таблицы TBL_PHOTO.
     *          5. Связанные записи в таблицах TBL_RATE_USER и TBL_RATE_MODER удаляются автоматически благодаря
     *             внешним ключам с правилом ON DELETE CASCADE.
     *          6. Логирует ошибки, возникающие при удалении файлов или выполнении запросов к базе данных, с помощью
     *             функции log_in_file().
     *          Этот метод содержит основную логику, вызываемую через публичный метод del_photo().
     *
     * @callergraph
     * @callgraph
     *
     * @param int $photo_id Идентификатор удаляемого изображения (обязательное поле).
     *                      Должен быть положительным целым числом.
     *
     * @return bool True, если удаление успешно, иначе False.
     *
     * @throws InvalidArgumentException Если параметр photo_id имеет некорректный тип или значение.
     *                                  Пример сообщения:
     *                                      Неверное значение параметра photo_id | Ожидалось положительное целое число.
     * @throws RuntimeException         Если возникает ошибка при выполнении запросов к базе данных или удалении
     *                                  файлов.
     *                                  Пример сообщения:
     *                                      Не удалось найти изображение | Переменная photo_id = [значение].
     * @throws Exception                При записи ошибок в лог через log_in_file().
     *
     * @note    Используются константы:
     *          - TBL_PHOTO: Таблица для хранения данных об изображениях (photo).
     *          - TBL_CATEGORY: Таблица для хранения данных о категориях (category).
     *
     * @warning Метод чувствителен к правам доступа при удалении файлов. Убедитесь, что скрипт имеет необходимые права
     *          на запись и чтение.
     *          Удаление файлов и записей из базы данных необратимо. Убедитесь, что передан корректный идентификатор
     *          изображения.
     *
     * Пример вызова метода внутри класса или наследника:
     * @code
     * // Удаление изображения с ID = 42
     * $result = $this->_del_photo_internal(42);
     * if ($result) {
     *     echo "Изображение успешно удалено.";
     * } else {
     *     echo "Не удалось удалить изображение.";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::del_photo()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$db
     *          Свойство, содержащее объект для работы с базой данных.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$config
     *          Свойство, содержащее конфигурацию приложения.
     * @see     PhotoRigma::Include::log_in_file()
     *          Функция для логирования ошибок.
     */
    protected function _del_photo_internal(int $photo_id): bool
    {
        // Проверка входного параметра
        if ($photo_id <= 0) {
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверное значение параметра \$photo_id | Ожидалось положительное целое число"
            );
        }
        // Получение данных об изображении и категории через JOIN
        $this->db->join(
            ['p.*', 'c.`folder`'], // Список полей для выборки
            TBL_PHOTO . ' p', // Основная таблица
            [
                [
                    'table' => TBL_CATEGORY . ' c', // Таблица для JOIN
                    'type'  => 'LEFT', // Тип JOIN
                    'on'    => 'p.`category` = c.`id`', // Условие JOIN
                ],
            ],
            [
                'where'  => 'p.`id` = :photo_id', // Условие WHERE
                'params' => [':photo_id' => $photo_id], // Параметры для prepared statements
            ]
        );
        $temp_data = $this->db->res_row();
        if (!$temp_data) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось найти изображение | Переменная \$photo_id = $photo_id"
            );
        }
        // Определение путей к файлам
        $path_thumbnail = $this->config['site_dir'] . $this->config['thumbnail_folder'] . '/' . $temp_data['folder'] . '/' . $temp_data['file'];
        $path_photo = $this->config['site_dir'] . $this->config['gallery_folder'] . '/' . $temp_data['folder'] . '/' . $temp_data['file'];
        // Удаление записи об изображении из таблицы
        $this->db->delete(TBL_PHOTO, ['where' => '`id` = :photo_id', 'params' => [':photo_id' => $photo_id]]);
        $aff_rows = $this->db->get_affected_rows();
        if ($aff_rows !== 1) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось удалить запись об изображении | Переменная \$photo_id = $photo_id"
            );
        }
        // Удаление файлов
        if (is_file($path_thumbnail) && !unlink($path_thumbnail)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось удалить файл эскиза | Путь: $path_thumbnail"
            );
        }
        if (is_file($path_photo) && !unlink($path_photo)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось удалить файл изображения | Путь: $path_photo"
            );
        }
        return true;
    }

    /**
     * @brief   Загружает доступные языки из директории `/language/` через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _get_languages_internal().
     *          Он загружает доступные языки, проверяя структуру директории `/language/` и содержимое файлов `main.php`.
     *          Метод также доступен через метод-фасад `get_languages()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @return array Массив с данными о доступных языках. Каждый элемент массива содержит:
     *               - `value`: Имя директории языка (строка).
     *               - `name`: Название языка из файла `main.php` (строка).
     *
     * @throws RuntimeException Если:
     *                           - Директория `/language/` недоступна или не существует.
     *                           - Ни один язык не найден в указанной директории.
     * @throws Exception        При записи ошибок в лог через `log_in_file()`.
     *
     * @warning Метод чувствителен к структуре директории `/language/` и содержимому файла `main.php`.
     *          Убедитесь, что:
     *           - Файл `main.php` содержит корректную переменную `lang_name` (строка, не пустая).
     *           - Поддиректории находятся внутри директории `/language/`.
     *          Если директория `/language/` пуста или содержит недоступные поддиректории, метод выбрасывает исключение.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра дочернего класса
     * $childObject = new \PhotoRigma\Classes\Work_CoreLogic();
     *
     * // Получение списка доступных языков
     * $languages = $childObject->get_languages();
     * foreach ($languages as $language) {
     *     echo "Язык: " . $language['name'] . " (ID: " . $language['value'] . ")\n";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::_get_languages_internal()
     *          Защищённый метод, реализующий основную логику загрузки доступных языков.
     * @see     PhotoRigma::Classes::Work::get_languages()
     *          Метод-фасад в родительском классе для вызова этой логики.
     */
    public function get_languages(): array
    {
        return $this->_get_languages_internal();
    }

    /**
     * @brief   Загружает доступные языки из директории /language/.
     *
     * @details Метод выполняет загрузку доступных языков, включая следующие шаги:
     *          1. Нормализует путь к директории `/language/` и проверяет её существование и доступность для чтения.
     *          2. Перебирает все поддиректории в `/language/` с использованием `DirectoryIterator`.
     *          3. Для каждой поддиректории:
     *             - Проверяет наличие файла `main.php`.
     *             - Безопасно подключает файл `main.php` и проверяет наличие переменной `lang_name`.
     *             - Если переменная `lang_name` определена, является строкой и не пустой, добавляет язык в список.
     *          4. Возвращает массив с данными о доступных языках или выбрасывает исключение, если языки не найдены.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика метода вызывается через публичный метод `get_languages()`.
     *
     * @callergraph
     * @callgraph
     *
     * @return array Массив с данными о доступных языках. Каждый элемент массива содержит:
     *               - `value`: Имя директории языка (строка).
     *               - `name`: Название языка из файла `main.php` (строка).
     *
     * @throws RuntimeException Если:
     *                           - Директория `/language/` недоступна или не существует.
     *                           - Ни один язык не найден в указанной директории.
     * @throws Exception         При записи ошибок в лог через `log_in_file()`.
     *
     * @warning Метод чувствителен к структуре директории `/language/` и содержимому файла `main.php`.
     *          Убедитесь, что:
     *           - Файл `main.php` содержит корректную переменную `lang_name` (строка, не пустая).
     *           - Поддиректории находятся внутри директории `/language/`.
     *          Если директория `/language/` пуста или содержит недоступные поддиректории, метод выбрасывает исключение.
     *
     * Пример вызова метода внутри класса или наследника:
     * @code
     * // Получение списка доступных языков
     * $languages = $this->_get_languages_internal();
     * foreach ($languages as $language) {
     *     echo "Язык: " . $language['name'] . " (ID: " . $language['value'] . ")\n";
     * }
     * @endcode
     * @see     PhotoRigma::Include::log_in_file()
     *          Функция для логирования ошибок.
     * @see     PhotoRigma::Classes::Work_CoreLogic::get_languages()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$config
     *          Свойство, содержащее конфигурацию приложения, включая путь к директории (`site_dir`).
     */
    protected function _get_languages_internal(): array
    {
        $list_languages = [];
        // Нормализуем путь к site_dir и проверяем его существование
        $site_dir = realpath(rtrim($this->config['site_dir'], '/'));
        $language_dir = $site_dir . '/language/';
        if (!is_dir($language_dir) || !is_readable($language_dir)) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Директория языков недоступна или не существует | Путь: $language_dir"
            );
        }

        // Проверяем, что директория не пуста
        $iterator = new DirectoryIterator($language_dir);
        $has_subdirs = false;
        foreach ($iterator as $file) {
            if (!$file->isDot() && $file->isDir()) {
                $has_subdirs = true;
                break;
            }
        }
        if (!$has_subdirs) {
            throw new RuntimeException(
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
            $lang_data = include($main_php_path);

            // Проверяем наличие и корректность lang_name
            if (
                !is_array($lang_data) ||
                !isset($lang_data['lang_name']) || !is_string($lang_data['lang_name']) || trim(
                    $lang_data['lang_name']
                ) === ''
            ) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Переменная \$lang_name не определена или некорректна | Файл: $main_php_path"
                );
                continue;
            }

            // Извлекаем только lang_name и освобождаем память
            $lang_name = $lang_data['lang_name'];
            unset($lang_data); // Освобождаем память

            // Добавляем язык в список
            $list_languages[] = [
                'value' => $file->getFilename(),
                'name'  => mb_trim($lang_name),
            ];
        }

        if (empty($list_languages)) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ни один язык не найден | Путь: $language_dir"
            );
        }

        return $list_languages;
    }

    /**
     * @brief   Загружает доступные темы из директории `/themes/` через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _get_themes_internal().
     *          Он загружает доступные темы, проверяя структуру директории `/themes/` и её поддиректории.
     *          Метод также доступен через метод-фасад `get_themes()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @return array Массив с именами доступных тем (строки).
     *
     * @throws RuntimeException Если:
     *                           - Директория `/themes/` не существует или недоступна для чтения.
     *                           - Ни одна тема не найдена в указанной директории.
     * @throws Exception        При записи ошибок в лог через `log_in_file()`.
     *
     * @warning Метод чувствителен к структуре директории `/themes/`.
     *          Убедитесь, что:
     *           - Директория `/themes/` существует и доступна для чтения.
     *           - Поддиректории находятся внутри директории `/themes/`.
     *          Если директория `/themes/` пуста или содержит недоступные поддиректории, метод выбрасывает исключение.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра дочернего класса
     * $childObject = new \PhotoRigma\Classes\Work_CoreLogic();
     *
     * // Получение списка доступных тем
     * $themes = $childObject->get_themes();
     * foreach ($themes as $theme) {
     *     echo "Доступная тема: $theme\n";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::_get_themes_internal()
     *          Защищённый метод, реализующий основную логику загрузки доступных тем.
     * @see     PhotoRigma::Classes::Work::get_themes()
     *          Метод-фасад в родительском классе для вызова этой логики.
     */
    public function get_themes(): array
    {
        return $this->_get_themes_internal();
    }

    /**
     * @brief   Загружает доступные темы из директории /themes/.
     *
     * @details Метод выполняет загрузку доступных тем, включая следующие шаги:
     *          1. Нормализует путь к директории `/themes/` и проверяет её существование и доступность для чтения.
     *          2. Проверяет, что директория `/themes/` содержит хотя бы одну поддиректорию.
     *          3. Перебирает все поддиректории в `/themes/` с использованием `DirectoryIterator`.
     *          4. Для каждой поддиректории:
     *             - Проверяет её доступность для чтения.
     *             - Убеждается, что поддиректория находится внутри разрешенной директории `/themes/`.
     *             - Добавляет имя поддиректории в список доступных тем.
     *          5. Возвращает массив с именами доступных тем или выбрасывает исключение, если темы не найдены.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика метода вызывается через публичный метод `get_themes()`.
     *
     * @callergraph
     * @callgraph
     *
     * @return array Массив с именами доступных тем (строки).
     *
     * @throws RuntimeException Если:
     *                           - Директория `/themes/` не существует или недоступна для чтения.
     *                           - Ни одна тема не найдена в указанной директории.
     * @throws Exception         При записи ошибок в лог через `log_in_file()`.
     *
     * @warning Метод чувствителен к структуре директории `/themes/`.
     *          Убедитесь, что:
     *           - Директория `/themes/` существует и доступна для чтения.
     *           - Поддиректории находятся внутри директории `/themes/`.
     *          Если директория `/themes/` пуста или содержит недоступные поддиректории, метод выбрасывает исключение.
     *
     * Пример вызова метода внутри класса или наследника:
     * @code
     * // Получение списка доступных тем
     * $themes = $this->_get_themes_internal();
     * foreach ($themes as $theme) {
     *     echo "Доступная тема: $theme\n";
     * }
     * @endcode
     * @see     PhotoRigma::Include::log_in_file()
     *          Функция для логирования ошибок.
     * @see     PhotoRigma::Classes::Work_CoreLogic::get_themes()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$config
     *          Свойство, содержащее конфигурацию приложения, включая путь к директории (`site_dir`).
     */
    protected function _get_themes_internal(): array
    {
        $list_themes = [];
        // Нормализуем путь к site_dir
        $site_dir = realpath(rtrim($this->config['site_dir'], '/'));
        $themes_dir = $site_dir . '/themes/';
        // Проверяем существование и доступность директории /themes/
        if (!is_dir($themes_dir) || !is_readable($themes_dir)) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Директория тем недоступна | Путь: $themes_dir"
            );
        }
        // Проверяем, что директория не пуста
        $iterator = new DirectoryIterator($themes_dir);
        $has_subdirs = false;
        foreach ($iterator as $file) {
            if (!$file->isDot() && $file->isDir()) {
                $has_subdirs = true;
                break;
            }
        }
        if (!$has_subdirs) {
            throw new RuntimeException(
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
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ни одна тема не найдена | Путь: $themes_dir"
            );
        }
        return $list_themes;
    }

    /**
     * @brief   Получает данные о новостях в зависимости от типа запроса через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _news_internal().
     *          Он выполняет запросы к базе данных для получения данных о новостях:
     *          - Для `$act = 'id'`: Возвращает новость по её ID.
     *          - Для `$act = 'last'`: Возвращает список новостей с сортировкой по дате последнего редактирования.
     *          Метод также доступен через метод-фасад `news()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @param int    $news_id_or_limit Количество новостей или ID новости (в зависимости от параметра `$act`):
     *                                 - Должен быть положительным целым числом.
     * @param string $act              Тип запроса:
     *                                 - `'id'`: Получение новости по её ID.
     *                                 - `'last'`: Получение списка новостей с сортировкой по дате последнего
     *                                 редактирования.
     *
     * @return array Массив с данными о новостях. Если новостей нет, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный `$act` или `$news_id_or_limit`.
     *                                  Пример сообщения:
     *                                      Некорректный ID новости | Переменная $news_id_or_limit = [значение].
     *                                  Пример сообщения:
     *                                      Некорректное количество новостей | Переменная $news_id_or_limit =
     *                                      [значение]. Пример сообщения: Некорректный тип запроса | Переменная $act =
     *                                      '$act'.
     * @throws RuntimeException         Если произошла ошибка при выполнении запроса к базе данных.
     *                                  Пример сообщения:
     *                                      Не удалось получить данные из базы данных | Тип запроса: '$act'.
     * @throws Exception                При выполнении запросов к базам данных.
     *
     * @note    Используются константы:
     *          - TBL_NEWS: Таблица для хранения данных о новостях (`news`).
     *
     * @warning Метод чувствителен к корректности входных параметров `$news_id_or_limit` и `$act`.
     *          Убедитесь, что передаются допустимые значения.
     *          Если новости не найдены, метод возвращает пустой массив.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра дочернего класса
     * $childObject = new \PhotoRigma\Classes\Work_CoreLogic();
     *
     * // Получение новости с ID = 5
     * $news_by_id = $childObject->news(5, 'id');
     * print_r($news_by_id);
     *
     * // Получение 10 последних новостей
     * $news_list = $childObject->news(10, 'last');
     * print_r($news_list);
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::_news_internal()
     *          Защищённый метод, реализующий основную логику получения данных о новостях.
     * @see     PhotoRigma::Classes::Work::news()
     *          Метод-фасад в родительском классе для вызова этой логики.
     */
    public function news(int $news_id_or_limit, string $act): array
    {
        return $this->_news_internal($news_id_or_limit, $act);
    }

    /**
     * @brief   Получает данные о новостях в зависимости от типа запроса.
     *
     * @details Метод выполняет запросы к базе данных для получения данных о новостях, включая следующие шаги:
     *          1. Проверяет корректность входных параметров `$news_id_or_limit` и `$act`:
     *             - Для `$act = 'id'`: `$news_id_or_limit` должен быть положительным целым числом (ID новости).
     *             - Для `$act = 'last'`: `$news_id_or_limit` должен быть положительным целым числом (количество
     *             новостей).
     *          2. Формирует параметры запроса через `match()`:
     *             - Для `$act = 'id'`: Выполняется выборка новости по её ID.
     *             - Для `$act = 'last'`: Выполняется выборка списка новостей с сортировкой по дате последнего
     *             редактирования.
     *          3. Выполняет запрос к таблице `TBL_NEWS` с использованием сформированных параметров.
     *          4. Возвращает массив с данными о новостях или пустой массив, если новости не найдены.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика метода вызывается через публичный метод `news()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param int    $news_id_or_limit   Количество новостей или ID новости (в зависимости от параметра `$act`).
     *                                   Должен быть положительным целым числом.
     * @param string $act                Тип запроса:
     *                                   - `'id'`: Получение новости по её ID.
     *                                   - `'last'`: Получение списка новостей с сортировкой по дате последнего
     *                                   редактирования.
     *
     * @return array Массив с данными о новостях. Если новостей нет, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный `$act` или `$news_id_or_limit`.
     *                                  Пример сообщения:
     *                                      Некорректный ID новости | Переменная $news_id_or_limit = [значение].
     *                                  Пример сообщения:
     *                                      Некорректное количество новостей | Переменная $news_id_or_limit =
     *                                      [значение]. Пример сообщения: Некорректный тип запроса | Переменная $act =
     *                                      '$act'.
     * @throws RuntimeException         Если произошла ошибка при выполнении запроса к базе данных.
     *                                  Пример сообщения:
     *                                      Не удалось получить данные из базы данных | Тип запроса: '$act'.
     * @throws Exception                При выполнении запросов к базам данных.
     *
     * @note    Используются константы:
     *          - TBL_NEWS: Таблица для хранения данных о новостях (`news`).
     *
     * @warning Метод чувствителен к корректности входных параметров `$news_id_or_limit` и `$act`.
     *          Убедитесь, что передаются допустимые значения.
     *          Если новости не найдены, метод возвращает пустой массив.
     *
     * Пример вызова метода внутри класса или наследника:
     * @code
     * // Получение новости с ID = 5
     * $news_by_id = $this->_news_internal(5, 'id');
     * print_r($news_by_id);
     *
     * // Получение 10 последних новостей
     * $news_list = $this->_news_internal(10, 'last');
     * print_r($news_list);
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::$db
     *          Свойство, содержащее объект для работы с базой данных.
     * @see     PhotoRigma::Classes::Work_CoreLogic::news()
     *          Публичный метод-редирект для вызова этой логики.
     */
    protected function _news_internal(int $news_id_or_limit, string $act): array
    {
        // Проверка входных данных
        if ($act === 'id') {
            if (!filter_var($news_id_or_limit, FILTER_VALIDATE_INT)) {
                throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный ID новости | Переменная \$news_id_or_limit = $news_id_or_limit"
                );
            }
        } elseif ($act === 'last') {
            if ($news_id_or_limit <= 0 || !filter_var($news_id_or_limit, FILTER_VALIDATE_INT)) {
                throw new InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное количество новостей | Переменная \$news_id_or_limit = $news_id_or_limit"
                );
            }
        } else {
            // Обработка некорректного типа запроса
            throw new InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный тип запроса | Переменная \$act = '$act'"
            );
        }
        // Формирование параметров запроса через match()
        $query_params = match ($act) {
            'id'   => [
                // Формируем запрос для 'id'
                'table'  => TBL_NEWS,
                'where'  => '`id` = :id',
                'params' => [':id' => $news_id_or_limit],
            ],
            'last' => [
                // Формируем запрос для 'last'
                'table' => TBL_NEWS,
                'order' => '`data_last_edit` DESC',
                'limit' => $news_id_or_limit,
            ],
        };
        // Выполняем запрос по результатам match()
        $this->db->select(
            '*',
            $query_params['table'],
            array_filter([
                'where'  => $query_params['where'] ?? null,
                'params' => $query_params['params'] ?? [],
                'order'  => $query_params['order'] ?? null,
                'limit'  => $query_params['limit'] ?? null,
            ])
        );
        // Получение результатов
        $news_results = $this->db->res_arr();
        // Возврат результата
        return $news_results ?: [];
    }

    /**
     * @brief   Добавляет новую оценку и возвращает среднюю оценку через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _process_rating_internal().
     *          Он добавляет оценку в таблицу и возвращает среднюю оценку.
     *          Метод также доступен через метод-фасад `process_rating()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @param string $table      Имя таблицы для вставки оценки:
     *                           - `'rate_user'`: Таблица с оценками фотографий от пользователей.
     *                           - `'rate_moder'`: Таблица с оценками фотографий от модераторов.
     * @param int    $photo_id   ID фотографии:
     *                           - Должен быть положительным целым числом.
     * @param int    $user_id    ID пользователя:
     *                           - Должен быть положительным целым числом.
     * @param int    $rate_value Значение оценки:
     *                           - Должен быть целым числом в диапазоне допустимых значений (например, 1–5).
     *
     * @return float Возвращает число с плавающей точкой, представляющее среднюю оценку.
     *               Если оценок нет, возвращается `0`.
     *
     * @throws RuntimeException Если не удалось добавить оценку.
     *                           Причина: `get_last_insert_id()` возвращает `0`, что указывает на неудачную вставку.
     * @throws Exception        При выполнении запросов к базам данных.
     *
     * @note    Используются константы:
     *          - TBL_PHOTO: Таблица для хранения данных о фотографиях (`photo`).
     *
     * @warning Убедитесь, что:
     *           - Параметр `$table` соответствует одной из допустимых таблиц (`'rate_user'` или `'rate_moder'`).
     *           - В СУБД настроены триггеры и функции для перерасчета средней оценки.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра дочернего класса
     * $childObject = new \PhotoRigma\Classes\Work_CoreLogic();
     *
     * // Добавление оценки и получение средней оценки
     * $averageRate = $childObject->process_rating('rate_user', 123, 456, 5);
     * echo "Средняя оценка: {$averageRate}";
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::_process_rating_internal()
     *          Защищённый метод, реализующий основную логику добавления оценки и расчёта средней оценки.
     * @see     PhotoRigma::Classes::Work::process_rating()
     *          Метод-фасад в родительском классе для вызова этой логики.
     */
    public function process_rating(string $table, int $photo_id, int $user_id, int $rate_value): float
    {
        return $this->_process_rating_internal($table, $photo_id, $user_id, $rate_value);
    }

    /**
     * @brief   Добавляет новую оценку в таблицу, проверяет успешность вставки и возвращает среднюю оценку.
     *
     * @details Этот защищенный метод выполняет следующие действия:
     *          1. Вставляет новую оценку в указанную таблицу через `$this->db->insert()`.
     *          2. Проверяет успешность вставки по значению `get_last_insert_id()`.
     *          3. Пересчитывает среднюю оценку для фотографии автоматически через триггеры и функции в СУБД.
     *          4. Выполняет выборку средней оценки из таблицы `TBL_PHOTO` для указанной фотографии.
     *             В таблице `TBL_PHOTO` есть столбцы, имена которых совпадают с именем таблицы `$table`.
     *          Метод вызывается через публичный метод-редирект `process_rating()`
     *          и предназначен для использования внутри класса или его наследников.
     *
     * @callergraph
     *
     * @param string $table      Имя таблицы для вставки оценки.
     *                           Должен быть строкой, соответствующей одной из двух допустимых таблиц:
     *                           - `'rate_user'`: Таблица с оценками фотографий от пользователей.
     *                           - `'rate_moder'`: Таблица с оценками фотографий от модераторов.
     * @param int    $photo_id   ID фотографии.
     *                           Должен быть положительным целым числом.
     * @param int    $user_id    ID пользователя.
     *                           Должен быть положительным целым числом.
     * @param int    $rate_value Значение оценки.
     *                           Должен быть целым числом в диапазоне допустимых значений (например, 1–5).
     *
     * @return float Возвращает число с плавающей точкой, представляющее среднюю оценку.
     *               Если оценок нет, возвращается `0`.
     *
     * @throws RuntimeException Выбрасывается исключение, если не удалось добавить оценку.
     *                           Причина: `get_last_insert_id()` возвращает `0`, что указывает на неудачную вставку.
     * @throws Exception        При выполнении запросов к базам данных.
     *
     * @note    Используются константы:
     *          - TBL_PHOTO: Таблица для хранения данных о фотографиях (`photo`).
     *
     * @warning Убедитесь, что:
     *          - Параметр `$table` соответствует одной из допустимых таблиц (`'rate_user'` или `'rate_moder'`).
     *          - В СУБД настроены триггеры и функции для перерасчета средней оценки.
     *
     * Пример вызова метода внутри класса или наследника:
     * @code
     * // Добавление оценки и получение средней оценки
     * $averageRate = $this->_process_rating_internal('rate_user', 123, 456, 5);
     * echo "Средняя оценка: {$averageRate}";
     * @endcode
     * @see     PhotoRigma::Classes::Work_CoreLogic::process_rating()
     *          Публичный метод-редирект для вызова этой логики.
     * @see     PhotoRigma::Classes::Work_CoreLogic::$db
     *          Свойство, содержащее объект для работы с базой данных.
     */
    protected function _process_rating_internal(string $table, int $photo_id, int $user_id, int $rate_value): float
    {
        // Вставка новой оценки
        $query_rate = [
            ':id_foto' => $photo_id,
            ':id_user' => $user_id,
            ':rate'    => $rate_value,
        ];
        $this->db->insert(
            ['`id_foto`' => ':id_foto', '`id_user`' => ':id_user', '`rate`' => ':rate'],
            $table,
            '',
            ['params' => $query_rate]
        );

        // Получение средней оценки (перерасчет выполняется внутри СУБД с помощью Тригеров и функций).
        $this->db->select($table, TBL_PHOTO, ['where' => '`id` = :id_foto', 'params' => [':id_foto' => $photo_id]]);
        $rate = $this->db->res_row();
        return $rate ? $rate[trim($table, '`')] : 0;
    }

    /**
     * @brief   Установка языковых данных через сеттер.
     *
     * @details Этот метод позволяет установить массив языковых данных для использования в системе.
     *          Метод выполняет следующие действия:
     *          1. Проверяет, что переданные данные являются массивом.
     *          2. Присваивает массив свойству текущего класса для дальнейшего использования.
     *
     * @param array $lang Языковые данные:
     *                    - Должен быть ассоциативным массивом.
     *                    - Каждый ключ должен быть строкой, представляющей собой уникальный идентификатор языковой
     *                    переменной.
     *                    - Каждое значение должно быть строкой или другим допустимым типом данных для языковых
     *                    значений.
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws InvalidArgumentException Если передан некорректный тип данных (не массив) или пустые данные.
     *
     * @note    Метод проверяет тип переданных данных.
     *          Языковые данные используются для локализации интерфейса и других текстовых элементов системы.
     *
     * @warning Передавайте только корректные языковые данные. Пустой массив или некорректные значения могут привести к
     *          ошибкам при использовании.
     *
     * Пример использования:
     * @code
     * // Создание объекта Work_CoreLogic и установка языковых данных
     * $corelogic = new \PhotoRigma\Classes\Work_CoreLogic($config, $db, $work);
     * $langData = [
     *     'welcome_message' => 'Добро пожаловать!',
     *     'error_message'   => 'Произошла ошибка.',
     * ];
     * $corelogic->set_lang($langData);
     * @endcode
     * @see     PhotoRigma::Classes::Work::set_lang()
     *          Метод в родительском классе Work, который вызывает этот метод.
     */
    public function set_lang(array $lang): void
    {
        $this->lang = $lang;
    }

    /**
     * @brief   Установка объекта, реализующего интерфейс User_Interface, через сеттер.
     *
     * @details Этот метод позволяет установить объект пользователя, реализующий интерфейс `User_Interface`.
     *          Метод выполняет следующие действия:
     *          1. Проверяет, что переданный объект реализует интерфейс `User_Interface`.
     *          2. Присваивает объект свойству текущего класса для дальнейшего использования.
     *
     * @param User_Interface $user Объект, реализующий интерфейс `User_Interface`:
     *                             - Должен быть экземпляром класса, реализующего интерфейс `User_Interface`.
     *
     * @return void Метод ничего не возвращает.
     *
     * @throws InvalidArgumentException Если передан некорректный объект (не реализует интерфейс `User_Interface`).
     *
     * @note    Метод проверяет тип переданного объекта.
     *          Объект пользователя используется для взаимодействия с другими компонентами системы.
     *
     * @warning Некорректный объект (не реализует интерфейс `User_Interface`) вызывает исключение.
     *
     * Пример использования:
     * @code
     * // Создание объекта Work_CoreLogic и установка объекта пользователя
     * $corelogic = new \PhotoRigma\Classes\Work_CoreLogic($config, $db, $work);
     * $user = new \PhotoRigma\Classes\User(); // Класс, реализующий User_Interface
     * $corelogic->set_user($user);
     * @endcode
     * @see     PhotoRigma::Classes::User_Interface
     *          Интерфейс, которому должен соответствовать объект пользователя.
     */
    public function set_user(User_Interface $user): void
    {
        $this->user = $user;
    }
}
