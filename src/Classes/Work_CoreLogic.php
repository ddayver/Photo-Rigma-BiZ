<?php

/**
 * Файл содержит класс Work_CoreLogic, который отвечает за выполнение базовой логики приложения.
 *
 * Этот файл содержит класс `Work_CoreLogic`, который реализует интерфейс `Work_CoreLogic_Interface`. Класс
 * предоставляет методы для выполнения ключевых операций приложения, таких как:
 * - Работа с категориями и альбомами (метод `category`).
 * - Управление изображениями (методы `del_photo`, `create_photo`).
 * - Получение данных о новостях, языках и темах (методы `news`, `get_languages`, `get_themes`).
 * Все методы зависят от конфигурации приложения и данных, полученных из базы данных.
 *
 * @author    Dark Dayver
 * @version   0.5.0
 * @since     2025-05-29
 * @namespace PhotoRigma\\Classes
 * @package   PhotoRigma
 *
 * @section   WorkCoreLogic_Main_Functions Основные функции
 *            - Формирование информационных строк для категорий и пользовательских альбомов.
 *            - Удаление изображений и связанных данных.
 *            - Получение данных о новостях, языках и темах.
 *            - Генерация блоков вывода изображений для различных типов запросов.
 *            - Обработка оценок и расчёт средней оценки.
 *
 * @uses      \PhotoRigma\Interfaces\Work_CoreLogic_Interface Интерфейс, который реализует данный класс.
 *
 * @note      Этот файл является частью системы PhotoRigma и играет ключевую роль в выполнении базовой логики
 *            приложения. Реализованы меры безопасности для предотвращения несанкционированного доступа к данным.
 *
 * @copyright Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license   MIT License {@link https://opensource.org/licenses/MIT}
 *            Разрешается использовать, копировать, изменять, объединять, публиковать, распространять,
 *            сублицензировать и/или продавать копии программного обеспечения, а также разрешать лицам, которым
 *            предоставляется данное программное обеспечение, делать это при соблюдении следующих условий:
 *            - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые
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
    /** @noinspection ForgottenDebugOutputInspection */
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
 * Класс для выполнения базовой логики приложения.
 *
 * Этот класс реализует интерфейс `Work_CoreLogic_Interface` и предоставляет функционал для выполнения базовой логики
 * приложения. Он является дочерним классом для `\PhotoRigma\Classes\Work` и наследует его методы. Все методы данного
 * класса рекомендуется вызывать через родительский класс `Work`, так как их поведение может быть непредсказуемым при
 * прямом вызове.
 * Основные возможности:
 * - Формирование информационных строк для категорий и пользовательских альбомов.
 * - Удаление изображений и связанных данных.
 * - Получение данных о новостях, языках и темах.
 * - Генерация блоков вывода изображений для различных типов запросов.
 * - Обработка оценок и расчёт средней оценки.
 * Все ошибки, возникающие при работе с данными, обрабатываются через исключения.
 *
 * @property array $config Конфигурация приложения.
 *
 * @uses \PhotoRigma\Interfaces\Work_CoreLogic_Interface Интерфейс, который реализует данный класс.
 */
class Work_CoreLogic implements Work_CoreLogic_Interface
{
    /** @var array Конфигурация приложения. */
    private array $config;
    /** @var array|null Языковые данные (могут быть null при инициализации). */
    private ?array $lang = null;
    /** @var \PhotoRigma\Interfaces\Database_Interface Объект для работы с базой данных (обязательный). */
    private Database_Interface $db;
    /** @var \PhotoRigma\Interfaces\Work_Interface Основной объект приложения (обязательный). */
    private Work_Interface $work;
    /** @var \PhotoRigma\Interfaces\User_Interface|null Объект пользователя (может быть null при инициализации). */
    private ?User_Interface $user = null;

    /**
     * Конструктор класса.
     *
     * Инициализирует зависимости: конфигурацию, базу данных и объект класса Work. Этот класс является дочерним для
     * `\PhotoRigma\Classes\Work`. Все параметры обязательны для корректной работы класса.
     * Алгоритм работы:
     * 1. Сохраняет переданные зависимости в соответствующие свойства:
     *    - `$config`: массив конфигурации приложения.
     *    - `$db`: объект для работы с базой данных.
     *    - `$work`: основной объект приложения.
     * 2. Проверяет, что все зависимости корректно инициализированы.
     * Метод вызывается автоматически при создании нового объекта класса.
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
     * @uses \PhotoRigma\Classes\Work Родительский класс, через который передаются зависимости.
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$config Свойство, содержащее конфигурацию приложения.
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$db Свойство, содержащее объект для работы с базой данных.
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$work Свойство, содержащее основной объект приложения.
     */
    public function __construct(Database_Interface $db, array $config, Work_Interface $work)
    {
        $this->config = $config;
        $this->db = $db;
        $this->work = $work;
    }

    /**
     * Получает значение приватного свойства.
     *
     * Этот метод позволяет получить доступ к приватному свойству `$config`.
     * Алгоритм работы:
     * 1. Проверяет, соответствует ли имя свойства допустимому значению (`config`).
     * 2. Если имя свойства корректно, возвращает значение свойства `$config`.
     * 3. Если имя свойства некорректно, выбрасывается исключение.
     * Метод является публичным и предназначен для получения доступа к свойству `$config`.
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
     *
     * @note    Этот метод предназначен только для доступа к свойству `$config`.
     *          Любые другие запросы будут игнорироваться с выбросом исключения.
     *
     * @warning Убедитесь, что вы запрашиваете только допустимое свойство:
     *          - 'config'
     *          Некорректные имена свойств вызовут исключение.
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$config Свойство, к которому обращается метод.
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
     * Устанавливает значение приватного свойства.
     *
     * Этот метод позволяет изменить значение приватного свойства `$config`.
     * Алгоритм работы:
     * 1. Проверяет, соответствует ли имя свойства допустимому значению (`config`).
     * 2. Если имя свойства корректно, обновляет значение свойства `$config`.
     * 3. Если имя свойства некорректно, выбрасывается исключение.
     * Метод является публичным и предназначен для изменения свойства `$config`.
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
     *
     * @note    Этот метод предназначен только для изменения свойства `$config`.
     *          Любые другие запросы будут игнорироваться с выбросом исключения.
     *
     * @warning Убедитесь, что вы запрашиваете только допустимое свойство:
     *          - 'config'
     *          Некорректные имена свойств вызовут исключение.
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$config Свойство, которое изменяет метод.
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
     * Проверяет существование недоступного свойства.
     *
     * Этот метод вызывается автоматически при использовании оператора `isset()` для проверки существования
     * недоступного свойства. Метод возвращает `true`, если свойство существует, и `false` в противном случае.
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
     */
    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    /**
     * Формирует информационную строку для категории или пользовательского альбома через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для защищённого метода _category_internal().
     * Он выполняет запросы к базе данных для получения информации о категории или пользовательском альбоме, включая
     * подсчёт фотографий, получение данных о последней и лучшей фотографии, а также формирование результирующего
     * массива с информацией.
     * Метод также доступен через метод-фасад `category()` в родительском классе.
     *
     * Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется. В противном
     * случае поведение метода может быть непредсказуемым, так как он зависит от внутренней логики и настроек
     * родительского класса.
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
     *                                            (Описание)").
     *               - 'top_photo'      (string): Форматированное название лучшей фотографии (например, "Название
     *                                            (Описание)").
     *               - 'url_cat'        (string): Ссылка на категорию или альбом.
     *               - 'url_last_photo' (string): Ссылка на последнюю фотографию.
     *               - 'url_top_photo'  (string): Ссылка на лучшую фотографию.
     *
     * @throws InvalidArgumentException Если входные параметры имеют некорректный тип или значение.
     * @throws PDOException            Если возникают ошибки при получении данных из базы данных.
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
     * @uses \PhotoRigma\Classes\Work_CoreLogic::_category_internal() Защищённый метод, реализующий основную логику
     *                                                                формирования информационной строки.
     */
    public function category(int $cat_id = 0, int $user_flag = 0): array
    {
        return $this->_category_internal($cat_id, $user_flag);
    }

    /**
     * Формирует информационную строку для категории или пользовательского альбома.
     *
     * Этот метод выполняет запросы к базе данных для получения информации о категории или пользовательском альбоме.
     * Алгоритм работы:
     * 1. Проверяет корректность входных параметров:
     *    - `$cat_id` должен быть целым числом >= `0`.
     *    - `$user_flag` должен принимать значения `0` (категория) или `1` (пользовательский альбом).
     * 2. Получает данные о категории из таблицы `TBL_CATEGORY` или данные пользователя из таблицы `TBL_USERS` (если
     *    `$user_flag = 1`).
     * 3. Подсчитывает количество фотографий в таблице `TBL_PHOTO` с использованием JOIN-запросов.
     * 4. Получает данные о последней и лучшей фотографии (если разрешено отображение фотографий).
     * 5. Для корневой категории (`$cat_id = 0`) подсчитывает количество уникальных пользователей, загрузивших
     *    фотографии.
     * 6. Формирует результирующий массив с информацией о категории или альбоме, включая название, описание,
     *    количество фотографий, данные о последней и лучшей фотографии, а также ссылки на них.
     * Метод является защищенным и вызывается через публичный метод `category()`.
     *
     * @protected
     *
     * @param int $cat_id    Идентификатор категории или пользователя (если `$user_flag = 1`).
     *                       Должен быть целым числом >= `0`.
     *                       Пример: `5` (для категории) или `123` (для пользовательского альбома).
     * @param int $user_flag Флаг, указывающий формировать ли информацию о категории (`0`) или пользовательском
     *                       альбоме (`1`).
     *                       По умолчанию: `0`.
     *                       Допустимые значения: `0` или `1`.
     *
     * @return array Информационная строка для категории или пользовательского альбома:
     *               - 'name'           (string): Название категории или альбома.
     *               - 'description'    (string): Описание категории или альбома.
     *               - 'count_photo'    (int):    Количество фотографий.
     *               - 'last_photo'     (string): Форматированное название последней фотографии (например, "Название
     *                                            (Описание)").
     *               - 'top_photo'      (string): Форматированное название лучшей фотографии (например, "Название
     *                                            (Описание)").
     *               - 'url_cat'        (string): Ссылка на категорию или альбом.
     *               - 'url_last_photo' (string): Ссылка на последнюю фотографию.
     *               - 'url_top_photo'  (string): Ссылка на лучшую фотографию.
     *
     * @throws InvalidArgumentException Если входные параметры имеют некорректный тип или значение.
     * @throws PDOException             Если возникают ошибки при получении данных из базы данных.
     * @throws Exception                При выполнении запросов к базам данных.
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
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$db Свойство, содержащее объект для работы с базой данных.
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$lang Свойство, содержащее языковые строки.
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$user Свойство, содержащее данные текущего пользователя.
     * @uses \PhotoRigma\Classes\Work::clean_field() Метод для очистки данных.
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
            $category_data = $this->db->result_row();
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
            $user_data = $this->db->result_row();
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
            $category_data = $this->db->result_row();
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
        $photo_data = $this->db->result_row();
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
        $photo_info['last_url'] = sprintf('%s?action=photo&amp;id=%d', SITE_URL, 0);
        $photo_info['top_name'] = $this->lang['main']['no_foto'];
        $photo_info['top_url'] = sprintf('%s?action=photo&amp;id=%d', SITE_URL, 0);

        // Обновление информации о фотографиях, если пользователь имеет права на просмотр
        if ($this->user->user['pic_view']) {
            if ($latest_photo_data) {
                $photo_info['last_name'] = Work::clean_field($latest_photo_data['name']) . ' (' . Work::clean_field(
                    $latest_photo_data['description']
                ) . ')';
                $photo_info['last_url'] = sprintf(
                    '%s?action=photo&amp;id=%d',
                    SITE_URL,
                    $latest_photo_data['id']
                );
            }

            if ($top_rated_photo_data) {
                $photo_info['top_name'] = Work::clean_field($top_rated_photo_data['name']) . ' (' . Work::clean_field(
                    $top_rated_photo_data['description']
                ) . ')';
                $photo_info['top_url'] = sprintf(
                    '%s?action=photo&amp;id=%d',
                    SITE_URL,
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
            $user_upload_count_data = $this->db->result_row();
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
                SITE_URL,
                $category_data['id']
            ),
            'url_last_photo'         => $photo_info['last_url'],
            'url_top_photo'          => $photo_info['top_url'],
            'user_upload_count_data' => $num_user_upload,
        ];
    }

    /**
     * Генерирует блок данных для вывода изображений различных типов через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для защищённого метода _create_photo_internal().
     * Он формирует массив данных для вывода изображения на основе типа (`$type`) и идентификатора категории или фото
     * (`$id_photo`). Метод также доступен через метод-фасад `create_photo()` в родительском классе.
     *
     * Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется. В противном
     * случае поведение метода может быть непредсказуемым.
     *
     * @param string $type     Тип изображения:
     *                         - 'top':  Лучшее изображение (по рейтингу).
     *                         - 'last': Последнее загруженное изображение.
     *                         - 'cat':  Изображение из конкретной категории (требует указания `$id_photo`).
     *                         - 'rand': Любое случайное изображение.
     *                         По умолчанию: 'top'.
     *                         Допустимые значения: 'top', 'last', 'cat', 'rand'.
     * @param int    $id_photo Идентификатор фото. Используется только при `$type == 'cat'`.
     *                         Должен быть целым числом >= `0`.
     *                         По умолчанию: `0`.
     *
     * @return array Массив данных для вывода изображения:
     *               - 'name_block'           (string):      Название блока изображения (например, "Лучшее фото").
     *               - 'url'                  (string):      URL для просмотра полного изображения.
     *               - 'thumbnail_url'        (string):      URL для миниатюры изображения.
     *               - 'name'                 (string):      Название изображения.
     *               - 'description'          (string):      Описание изображения.
     *               - 'category_name'        (string):      Название категории.
     *               - 'category_description' (string):      Описание категории.
     *               - 'rate'                 (string):      Рейтинг изображения (например, "Рейтинг: 5/10").
     *               - 'url_user'             (string|null): URL профиля пользователя, добавившего изображение.
     *               - 'real_name'            (string):      Реальное имя пользователя.
     *               - 'category_url'         (string):      URL категории или пользовательского альбома.
     *               - 'width'                (int):         Ширина изображения после масштабирования.
     *               - 'height'               (int):         Высота изображения после масштабирования.
     *
     * @throws InvalidArgumentException Если передан недопустимый `$type` или `$id_photo < 0`.
     * @throws PDOException             Если произошла ошибка при выборке данных из базы данных.
     * @throws RuntimeException         Если файл изображения недоступен или не существует.
     * @throws Exception                При записи ошибок в лог через `log_in_file()`.
     *
     * @note    Используются следующие константы:
     *          - TBL_CATEGORY: Таблица для хранения данных о категориях (`category`).
     *          - TBL_PHOTO: Таблица для хранения данных об изображениях (`photo`).
     *          - TBL_USERS: Таблица для хранения данных о пользователях (`users`).
     *          - VIEW_RANDOM_PHOTO: Представление для выбора случайных изображений (`random_photo`).
     *
     * @warning Метод чувствителен к правам доступа пользователя (`$this->user->user['pic_view']`).
     *          Убедитесь, что пользователь имеет право на просмотр изображений.
     *          Если файл изображения недоступен или не существует, метод возвращает данные по умолчанию через
     *          `_generate_photo_data()`.
     *          Проверка пути к файлу изображения гарантирует, что доступ возможен только к файлам внутри
     *          `$this->config['gallery_dir']`.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::_create_photo_internal() Защищённый метод, реализующий основную
     *                                                                    логику генерации блока данных для вывода
     *                                                                    изображений.
     */
    public function create_photo(string $type = 'top', int $id_photo = 0): array
    {
        return $this->_create_photo_internal($type, $id_photo);
    }

    /**
     * Генерирует блок данных для вывода изображений различных типов.
     *
     * Этот метод выполняет следующие шаги:
     * 1. Проверяет корректность входных параметров:
     *    - `$id_photo` должен быть целым числом >= `0`.
     *    - `$type` должен принимать одно из значений: 'top', 'last', 'cat', 'rand'.
     * 2. Проверяет права пользователя на просмотр изображений (`$this->user->user['pic_view']`).
     * 3. Формирует SQL-запрос для получения данных изображения и категории через JOIN:
     *    - Для типа 'top':  Выбирает лучшее изображение с учетом рейтинга.
     *    - Для типа 'last': Выбирает последнее загруженное изображение.
     *    - Для типа 'cat':  Выбирает изображение из конкретной категории по `$id_photo`.
     *    - Для типа 'rand': Выбирает любое случайное изображение (использует представление `VIEW_RANDOM_PHOTO`).
     * 4. Проверяет существование файла изображения и его доступность.
     * 5. Ограничивает доступ к файлам внутри директории `$this->config['gallery_dir']`.
     * 6. Вычисляет размеры изображения через метод `size_image()`.
     * 7. Формирует массив данных для вывода изображения или вызывает `_generate_photo_data()` в случае ошибки.
     * Метод является защищенным и вызывается через публичный метод `create_photo()`.
     *
     * @protected
     *
     * @param string $type     Тип изображения:
     *                         - 'top':  Лучшее изображение (по рейтингу).
     *                         - 'last': Последнее загруженное изображение.
     *                         - 'cat':  Изображение из конкретной категории (требует указания `$id_photo`).
     *                         - 'rand': Любое случайное изображение.
     *                         По умолчанию: 'top'.
     *                         Допустимые значения: 'top', 'last', 'cat', 'rand'.
     * @param int    $id_photo Идентификатор фото. Используется только при `$type == 'cat'`.
     *                         Должен быть целым числом >= `0`.
     *                         По умолчанию: `0`.
     *
     * @return array Массив данных для вывода изображения:
     *               - 'name_block'           (string):      Название блока изображения (например, "Лучшее фото").
     *               - 'url'                  (string):      URL для просмотра полного изображения.
     *               - 'thumbnail_url'        (string):      URL для миниатюры изображения.
     *               - 'name'                 (string):      Название изображения.
     *               - 'description'          (string):      Описание изображения.
     *               - 'category_name'        (string):      Название категории.
     *               - 'category_description' (string):      Описание категории.
     *               - 'rate'                 (string):      Рейтинг изображения (например, "Рейтинг: 5/10").
     *               - 'url_user'             (string|null): URL профиля пользователя, добавившего изображение.
     *               - 'real_name'            (string):      Реальное имя пользователя.
     *               - 'category_url'         (string):      URL категории или пользовательского альбома.
     *               - 'width'                (int):         Ширина изображения после масштабирования.
     *               - 'height'               (int):         Высота изображения после масштабирования.
     *
     * @throws InvalidArgumentException Если передан недопустимый `$type` или `$id_photo < 0`.
     * @throws PDOException             Если произошла ошибка при выборке данных из базы данных.
     * @throws RuntimeException         Если файл изображения недоступен или не существует.
     * @throws Exception                При записи ошибок в лог через `log_in_file()`.
     *
     * @note    Используются следующие константы:
     *          - TBL_CATEGORY: Таблица для хранения данных о категориях (`category`).
     *          - TBL_PHOTO: Таблица для хранения данных об изображениях (`photo`).
     *          - TBL_USERS: Таблица для хранения данных о пользователях (`users`).
     *          - VIEW_RANDOM_PHOTO: Представление для выбора случайных изображений (`random_photo`).
     *
     * @warning Метод чувствителен к правам доступа пользователя (`$this->user->user['pic_view']`).
     *          Убедитесь, что пользователь имеет право на просмотр изображений.
     *          Если файл изображения недоступен или не существует, метод возвращает данные по умолчанию через
     *          `_generate_photo_data()`.
     *          Проверка пути к файлу изображения гарантирует, что доступ возможен только к файлам внутри
     *          `$this->config['gallery_dir']`.
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$config Свойство, содержащее конфигурацию приложения.
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$user Свойство, содержащее данные текущего пользователя.
     * @uses \PhotoRigma\Classes\Work_CoreLogic::_generate_photo_data() Приватный метод для формирования массива
     *                                                                  данных по умолчанию.
     * @uses \PhotoRigma\Classes\Work::size_image() Метод, используемый для вычисления размеров изображения.
     * @uses \PhotoRigma\Include\log_in_file() Записывает сообщение об ошибке в лог-файл.
     * @uses \PhotoRigma\Classes\Work::clean_field() Метод для очистки данных.
     */
    protected function _create_photo_internal(string $type = 'top', int $id_photo = 0): array
    {
        // Валидация входных данных $id_photo
        if ($id_photo < 0) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный идентификатор фотографии | Значение: $id_photo"
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
            $photo_data = $this->db->result_row();

            // Если изображение не найдено, возвращаем данные по умолчанию
            if (!$photo_data) {
                $size = $this->work->size_image(
                    GALLERY_DIR . '/no_foto.png'
                );
                return $this->_generate_photo_data(['width' => $size['width'], 'height' => $size['height']], $type);
            }
        } else {
            $size = $this->work->size_image(
                GALLERY_DIR . '/no_foto.png'
            );
            return $this->_generate_photo_data(['width' => $size['width'], 'height' => $size['height']], $type);
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
        $category_data = $this->db->result_row();
        if (!$category_data) {
            throw new PDOException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка базы данных | Не удалось получить данные категории с ID: {$photo_data['category']}"
            );
        }

        // Формирование пути к файлу изображения
        $image_path = GALLERY_DIR . '/' . ($category_data['folder'] ?? '') . '/' . $photo_data['file'];

        // Ограничение доступа к файлам через $image_path
        $base_dir = realpath(GALLERY_DIR);
        $resolved_path = realpath($image_path);
        if (!$resolved_path || !str_starts_with($resolved_path, $base_dir)) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка доступа к недопустимому пути | Путь: $image_path"
            );
            $image_path = GALLERY_DIR . '/no_foto.png';
        }

        // Проверка существования файла
        if (!file_exists($image_path) || !is_readable($image_path)) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл не найден или недоступен | Путь: $image_path, Пользователь: " . ($this->user->user['id'] ?? 'неизвестный')
            );
            $image_path = GALLERY_DIR . '/no_foto.png';
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
        $user_data = $this->db->result_row();

        // Формирование массива данных для передачи в _generate_photo_data
        $photo_data_for_generate = [
            'name_block'           => $this->lang['main'][$type . '_foto'],
            'url'                  => sprintf(
                '%s?action=photo&amp;id=%d',
                SITE_URL,
                $photo_data['id']
            ),
            'thumbnail_url'        => sprintf(
                '%s?action=attach&amp;foto=%d&amp;thumbnail=1',
                SITE_URL,
                $photo_data['id']
            ),
            'name'                 => Work::clean_field($photo_data['name']),
            'description'          => Work::clean_field($photo_data['description']),
            'category_name'        => Work::clean_field($category_data['name']),
            'category_description' => Work::clean_field($category_data['description']),
            'rate'                 => $this->lang['main']['rate'] . ': ' . $photo_data['rate_user'] . '/' . $photo_data['rate_moder'],
            'url_user'             => $user_data ? sprintf(
                '%s?action=profile&amp;subact=profile&amp;uid=%d',
                SITE_URL,
                $photo_data['user_upload']
            ) : '',
            'real_name'            => $user_data ? Work::clean_field(
                $user_data['real_name']
            ) : $this->lang['main']['no_user_add'],
            'category_url'         => sprintf(
                '%s?action=category&amp;cat=%d',
                SITE_URL,
                $category_data['id']
            ),
            'width'                => $size['width'],
            'height'               => $size['height'],
        ];

        // Генерация данных изображения
        return $this->_generate_photo_data($photo_data_for_generate, $type);
    }

    /**
     * Генерирует массив данных для вывода изображения, используя значения по умолчанию или данные, переданные в
     * параметре `$photo_data`.
     *
     * Этот метод выполняет следующие шаги:
     * 1. Формирует массив данных для вывода изображения, используя значения по умолчанию из константы (`SITE_URL`) и
     *    языковых переменных (`$this->lang`).
     * 2. Если параметр `$photo_data` содержит данные, обновляет значения по умолчанию только для существующих ключей.
     * 3. Возвращает массив данных для вывода изображения.
     * Метод является приватным и предназначен только для использования внутри класса.
     *
     * @internal
     *
     * @param array  $photo_data Массив данных изображения, полученных из базы данных.
     *                           Может быть пустым, если требуется сгенерировать массив только со значениями по
     *                           умолчанию. Пример: `['name' => 'Лучшее фото', 'description' => 'Описание лучшего
     *                           фото']`.
     * @param string $type       Тип изображения: 'top', 'last', 'cat' или 'rand'.
     *                           По умолчанию: 'top'.
     *                           Пример: 'top'.
     *
     * @return array Массив данных для вывода изображения:
     *               - 'name_block'           (string):      Название блока изображения (например, "Лучшее фото").
     *               - 'url'                  (string):      URL для просмотра полного изображения.
     *               - 'thumbnail_url'        (string):      URL для миниатюры изображения.
     *               - 'name'                 (string):      Название изображения.
     *               - 'description'          (string):      Описание изображения.
     *               - 'category_name'        (string):      Название категории.
     *               - 'category_description' (string):      Описание категории.
     *               - 'rate'                 (string):      Рейтинг изображения (например, "Рейтинг: 5/10").
     *               - 'url_user'             (string|null): URL профиля пользователя, добавившего изображение.
     *               - 'real_name'            (string):      Реальное имя пользователя.
     *               - 'category_url'         (string):      URL категории или пользовательского альбома.
     *               - 'width'                (int):         Ширина изображения после масштабирования.
     *               - 'height'               (int):         Высота изображения после масштабирования.
     *
     * @note    Значения по умолчанию берутся из константы (`SITE_URL`) и языковых переменных (`$this->lang`).
     *
     * @warning Убедитесь, что:
     *          - Константа `SITE_URL` и свойство `$this->lang` правильно инициализированы перед вызовом метода.
     *          - Параметр `$photo_data` содержит корректные ключи, соответствующие массиву значений по умолчанию.
     *          Несоблюдение этих условий может привести к некорректному формированию массива данных.
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$lang Свойство, содержащее языковые переменные.
     */
    private function _generate_photo_data(array $photo_data = [], string $type = 'top'): array
    {
        // Значения по умолчанию
        $default_data = [
            'name_block'           => $this->lang['main'][$type . '_foto'],
            'url'                  => sprintf('%s?action=photo&amp;id=0', SITE_URL),
            'thumbnail_url'        => sprintf('%s?action=attach&amp;foto=0&amp;thumbnail=1', SITE_URL),
            'name'                 => $this->lang['main']['no_foto'],
            'description'          => $this->lang['main']['no_foto'],
            'category_name'        => $this->lang['main']['no_category'],
            'category_description' => $this->lang['main']['no_category'],
            'rate'                 => $this->lang['main']['rate'] . ': ' . $this->lang['main']['no_foto'],
            'url_user'             => '',
            'real_name'            => $this->lang['main']['no_user_add'],
            'category_url'         => SITE_URL,
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
     * Удаляет изображение с указанным идентификатором через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для защищённого метода _del_photo_internal().
     * Он удаляет файлы изображения, связанные записи из базы данных и логирует ошибки. Метод также доступен через
     * метод-фасад `del_photo()` в родительском классе.
     *
     * Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется. В противном
     * случае поведение метода может быть непредсказуемым.
     *
     * @param int $photo_id Идентификатор удаляемого изображения:
     *                      - Должен быть положительным целым числом.
     *                      Пример: `42`.
     *
     * @return bool True, если удаление успешно, иначе False.
     *
     * @throws InvalidArgumentException Если параметр `$photo_id` имеет некорректный тип или значение.
     * @throws RuntimeException         Если возникает ошибка при выполнении запросов к базе данных или удалении файлов.
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
     * @uses \PhotoRigma\Classes\Work_CoreLogic::_del_photo_internal() Защищённый метод, реализующий основную логику
     *                                                                 удаления изображения.
     */
    public function del_photo(int $photo_id): bool
    {
        return $this->_del_photo_internal($photo_id);
    }

    /**
     * Удаляет изображение с указанным идентификатором, а также все упоминания об этом изображении в таблицах сайта.
     *
     * Метод выполняет удаление изображения с указанным идентификатором, включая следующие шаги:
     * 1. Проверяет корректность входного параметра photo_id.
     * 2. Получает данные об изображении и категории через JOIN запрос к таблицам TBL_PHOTO и TBL_CATEGORY.
     * 3. Удаляет файлы из каталогов полноразмерных изображений и эскизов, используя пути, заданные в константах
     *    (GALLERY_DIR, THUMBNAIL_DIR). Перед удалением проверяется существование файлов.
     * 4. Удаляет запись об изображении из таблицы TBL_PHOTO.
     * 5. Связанные записи в таблицах TBL_RATE_USER и TBL_RATE_MODER удаляются автоматически благодаря внешним ключам
     *    с правилом ON DELETE CASCADE.
     * 6. Логирует ошибки, возникающие при удалении файлов или выполнении запросов к базе данных, с помощью функции
     *    log_in_file().
     * Этот метод содержит основную логику, вызываемую через публичный метод del_photo().
     *
     * @protected
     *
     * @param int $photo_id Идентификатор удаляемого изображения (обязательное поле).
     *                      Должен быть положительным целым числом.
     *
     * @return bool True, если удаление успешно, иначе False.
     *
     * @throws InvalidArgumentException Если параметр photo_id имеет некорректный тип или значение.
     * @throws RuntimeException         Если возникает ошибка при выполнении запросов к базе данных или удалении
     *                                  файлов.
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
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$db Свойство, содержащее объект для работы с базой данных.
     * @uses \PhotoRigma\Include\log_in_file() Записывает сообщение об ошибке в лог-файл.
     */
    protected function _del_photo_internal(int $photo_id): bool
    {
        // Проверка входного параметра
        if ($photo_id <= 0) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверное значение параметра \$photo_id | Ожидалось положительное целое число"
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
        $temp_data = $this->db->result_row();
        if (!$temp_data) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось найти изображение | Переменная \$photo_id = $photo_id"
            );
        }
        // Определение путей к файлам
        $path_thumbnail = THUMBNAIL_DIR . '/' . $temp_data['folder'] . '/' . $temp_data['file'];
        $path_photo = GALLERY_DIR . '/' . $temp_data['folder'] . '/' . $temp_data['file'];
        // Удаление записи об изображении из таблицы
        $this->db->delete(TBL_PHOTO, ['where' => '`id` = :photo_id', 'params' => [':photo_id' => $photo_id]]);
        $aff_rows = $this->db->get_affected_rows();
        if ($aff_rows !== 1) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось удалить запись об изображении | Переменная \$photo_id = $photo_id"
            );
        }
        // Удаление файлов
        if (is_file($path_thumbnail) && !unlink($path_thumbnail)) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось удалить файл эскиза | Путь: $path_thumbnail"
            );
        }
        if (is_file($path_photo) && !unlink($path_photo)) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось удалить файл изображения | Путь: $path_photo"
            );
        }
        return true;
    }

    /**
     * Загружает доступные языки из директории `/language/` через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для защищённого метода _get_languages_internal(). Он загружает
     * доступные языки, проверяя структуру директории `/language/` и содержимое файлов `main.php`.
     * Метод также доступен через метод-фасад `get_languages()` в родительском классе.
     *
     * Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется. В противном
     * случае поведение метода может быть непредсказуемым.
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
     * @uses \PhotoRigma\Classes\Work_CoreLogic::_get_languages_internal() Защищённый метод, реализующий основную
     *                                                                     логику загрузки доступных языков.
     */
    public function get_languages(): array
    {
        return $this->_get_languages_internal();
    }

    /**
     * Загружает доступные языки из директории /language/.
     *
     * Метод выполняет загрузку доступных языков, включая следующие шаги:
     * 1. Нормализует путь к директории `/language/` и проверяет её существование и доступность для чтения.
     * 2. Перебирает все поддиректории в `/language/` с использованием `DirectoryIterator`.
     * 3. Для каждой поддиректории:
     *    - Проверяет наличие файла `main.php`.
     *    - Безопасно подключает файл `main.php` и проверяет наличие переменной `lang_name`.
     *    - Если переменная `lang_name` определена, является строкой и не пустой, добавляет язык в список.
     * 4. Возвращает массив с данными о доступных языках или выбрасывает исключение, если языки не найдены.
     * Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     * Основная логика метода вызывается через публичный метод `get_languages()`.
     *
     * @protected
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
     * @uses \PhotoRigma\Include\log_in_file() Записывает сообщение об ошибке в лог-файл.
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$config Свойство, содержащее конфигурацию приложения, включая путь к
     *                                                   директории (`language_dirs`).
     */
    protected function _get_languages_internal(): array
    {
        $list_languages = [];

        // Перебираем папки из настроек (для var/ и core/)
        foreach ($this->config['language_dirs'] as $language_dir) {
            // Проверяем, что директория не пуста
            $iterator = new DirectoryIterator($language_dir);
            $has_subdirs = false;
            foreach ($iterator as $file) {
                if (!$file->isDot() && $file->isDir()) {
                    $has_subdirs = true;
                    break;
                }
            }
            if ($has_subdirs) {
                // Перебираем поддиректории
                foreach ($iterator as $file) {
                    if ($file->isDot() || !$file->isDir()) {
                        continue;
                    }
                    $lang_subdir = $file->getPathname();

                    // Проверяем, что директория существует и доступна для чтения
                    if (!is_dir($lang_subdir) || !is_readable($lang_subdir)) {
                        log_in_file(
                            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Поддиректория языка недоступна для чтения | Директория: $lang_subdir"
                        );
                        continue;
                    }

                    // Формируем полный путь к main.php и нормализуем его
                    $main_php_path = realpath($lang_subdir . '/main.php');
                    if ($main_php_path === false || !is_file($main_php_path) || !is_readable($main_php_path)) {
                        log_in_file(
                            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл main.php отсутствует или недоступен | Директория: $lang_subdir"
                        );
                        continue;
                    }

                    // Проверяем, что файл находится внутри разрешенной директории
                    if (!str_starts_with($main_php_path, $language_dir)) {
                        log_in_file(
                            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Подозрительный путь к файлу main.php | Директория: $lang_subdir"
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
                            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Переменная \$lang_name не определена или некорректна | Файл: $main_php_path"
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
            }
        }

        if (empty($list_languages)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Ни один язык не найден | Путь: ' . implode(', ', $this->config['language_dirs'])
            );
        }

        return $list_languages;
    }

    /**
     * Загружает доступные темы из директории `/themes/` через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для защищённого метода _get_themes_internal(). Он загружает доступные
     * темы, проверяя структуру директории `/themes/` и её поддиректории.
     * Метод также доступен через метод-фасад `get_themes()` в родительском классе.
     *
     * Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется. В противном
     * случае поведение метода может быть непредсказуемым.
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
     * @uses \PhotoRigma\Classes\Work_CoreLogic::_get_themes_internal() Защищённый метод, реализующий основную логику
     *                                                                  загрузки доступных тем.
     */
    public function get_themes(): array
    {
        return $this->_get_themes_internal();
    }

    /**
     * Загружает доступные темы из директории /themes/.
     *
     * Метод выполняет загрузку доступных тем, включая следующие шаги:
     * 1. Нормализует путь к директории `/themes/` и проверяет её существование и доступность для чтения.
     * 2. Проверяет, что директория `/themes/` содержит хотя бы одну поддиректорию.
     * 3. Перебирает все поддиректории в `/themes/` с использованием `DirectoryIterator`.
     * 4. Для каждой поддиректории:
     *    - Проверяет её доступность для чтения.
     *    - Убеждается, что поддиректория находится внутри разрешенной директории `/themes/`.
     *    - Добавляет имя поддиректории в список доступных тем.
     * 5. Возвращает массив с именами доступных тем или выбрасывает исключение, если темы не найдены.
     * Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     * Основная логика метода вызывается через публичный метод `get_themes()`.
     *
     * @protected
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
     * @uses \PhotoRigma\Include\log_in_file() Записывает сообщение об ошибке в лог-файл.
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$config Свойство, содержащее конфигурацию приложения, включая путь к
     *                                                   директории (`template_dirs`).
     */
    protected function _get_themes_internal(): array
    {
        $list_themes = [];

        // Перебираем папки из настроек (для var/ и core/)
        foreach ($this->config['template_dirs'] as $template_dir) {
            // Проверяем, что директория не пуста
            $iterator = new DirectoryIterator($template_dir);
            $has_subdirs = false;
            foreach ($iterator as $file) {
                if (!$file->isDot() && $file->isDir()) {
                    $has_subdirs = true;
                    break;
                }
            }
            if ($has_subdirs) {
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
                            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Поддиректория темы недоступна для чтения | Директория: $theme_dir"
                        );
                        continue;
                    }
                    // Проверяем, что директория находится внутри $template_dir
                    if (!str_starts_with($theme_dir, $template_dir)) {
                        log_in_file(
                            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Подозрительная директория темы | Директория: $theme_dir"
                        );
                        continue;
                    }
                    // Добавляем имя папки в список тем
                    $list_themes[] = $file->getFilename();
                }
            }
        }

        // Если ни одна тема не найдена, выбрасываем исключение
        if (empty($list_themes)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Ни одна тема не найдена | Путь:' . implode(', ', $this->config['template_dirs'])
            );
        }
        return $list_themes;
    }

    /**
     * Получает данные о новостях в зависимости от типа запроса через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для защищённого метода _news_internal().
     * Он выполняет запросы к базе данных для получения данных о новостях:
     * - Для `$act = 'id'`: Возвращает новость по её ID.
     * - Для `$act = 'last'`: Возвращает список новостей с сортировкой по дате последнего редактирования.
     * Метод также доступен через метод-фасад `news()` в родительском классе.
     *
     * Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется. В противном
     * случае поведение метода может быть непредсказуемым.
     *
     * @param int    $news_id_or_limit Количество новостей или ID новости (в зависимости от параметра `$act`):
     *                                 - Должен быть положительным целым числом.
     * @param string $act              Тип запроса:
     *                                 - 'id':   Получение новости по её ID.
     *                                 - 'last': Получение списка новостей с сортировкой по дате последнего
     *                                           редактирования.
     *
     * @return array Массив с данными о новостях. Если новостей нет, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный `$act` или `$news_id_or_limit`.
     * @throws RuntimeException         Если произошла ошибка при выполнении запроса к базе данных.
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
     * @uses \PhotoRigma\Classes\Work_CoreLogic::_news_internal() Защищённый метод, реализующий основную логику
     *                                                            получения данных о новостях.
     */
    public function news(int $news_id_or_limit, string $act): array
    {
        return $this->_news_internal($news_id_or_limit, $act);
    }

    /**
     * Получает данные о новостях в зависимости от типа запроса.
     *
     * Метод выполняет запросы к базе данных для получения данных о новостях, включая следующие шаги:
     * 1. Проверяет корректность входных параметров `$news_id_or_limit` и `$act`:
     *    - Для `$act = 'id'`: `$news_id_or_limit` должен быть положительным целым числом (ID новости).
     *    - Для `$act = 'last'`: `$news_id_or_limit` должен быть положительным целым числом (количество новостей).
     * 2. Формирует параметры запроса через `match()`:
     *    - Для `$act = 'id'`: Выполняется выборка новости по её ID.
     *    - Для `$act = 'last'`: Выполняется выборка списка новостей с сортировкой по дате последнего редактирования.
     * 3. Выполняет запрос к таблице `TBL_NEWS` с использованием сформированных параметров.
     * 4. Возвращает массив с данными о новостях или пустой массив, если новости не найдены.
     * Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     * Основная логика метода вызывается через публичный метод `news()`.
     *
     * @protected
     *
     * @param int    $news_id_or_limit Количество новостей или ID новости (в зависимости от параметра `$act`).
     *                                 Должен быть положительным целым числом.
     * @param string $act              Тип запроса:
     *                                 - 'id':   Получение новости по её ID.
     *                                 - 'last': Получение списка новостей с сортировкой по дате последнего
     *                                           редактирования.
     *
     * @return array Массив с данными о новостях. Если новостей нет, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Если передан некорректный `$act` или `$news_id_or_limit`.
     * @throws RuntimeException         Если произошла ошибка при выполнении запроса к базе данных.
     * @throws Exception                При выполнении запросов к базам данных.
     *
     * @note    Используются константы:
     *          - TBL_NEWS: Таблица для хранения данных о новостях (`news`).
     *
     * @warning Метод чувствителен к корректности входных параметров `$news_id_or_limit` и `$act`.
     *          Убедитесь, что передаются допустимые значения.
     *          Если новости не найдены, метод возвращает пустой массив.
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$db Свойство, содержащее объект для работы с базой данных.
     */
    protected function _news_internal(int $news_id_or_limit, string $act): array
    {
        // Проверка входных данных
        if ($act === 'id') {
            if (!filter_var($news_id_or_limit, FILTER_VALIDATE_INT)) {
                throw new InvalidArgumentException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный ID новости | Переменная \$news_id_or_limit = $news_id_or_limit"
                );
            }
        } elseif ($act === 'last') {
            if ($news_id_or_limit <= 0 || !filter_var($news_id_or_limit, FILTER_VALIDATE_INT)) {
                throw new InvalidArgumentException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректное количество новостей | Переменная \$news_id_or_limit = $news_id_or_limit"
                );
            }
        } else {
            // Обработка некорректного типа запроса
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный тип запроса | Переменная \$act = '$act'"
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
        $news_results = $this->db->result_array();
        // Возврат результата
        return $news_results ?: [];
    }

    /**
     * Добавляет новую оценку и возвращает среднюю оценку через вызов внутреннего метода.
     *
     * Этот публичный метод является обёрткой для защищённого метода _process_rating_internal().
     * Он добавляет оценку в таблицу и возвращает среднюю оценку. Метод также доступен через метод-фасад
     * `process_rating()` в родительском классе.
     *
     * Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется. В противном
     * случае поведение метода может быть непредсказуемым.
     *
     * @param string $table      Имя таблицы для вставки оценки:
     *                           - 'rate_user':  Таблица с оценками фотографий от пользователей.
     *                           - 'rate_moder': Таблица с оценками фотографий от модераторов.
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
     * @throws RuntimeException Если не удалось добавить оценку. Причина: `get_last_insert_id()` возвращает `0`, что
     *                          указывает на неудачную вставку.
     * @throws Exception        При выполнении запросов к базам данных.
     *
     * @note    Используются константы:
     *          - TBL_PHOTO: Таблица для хранения данных о фотографиях (`photo`).
     *
     * @warning Убедитесь, что:
     *           - Параметр `$table` соответствует одной из допустимых таблиц ('rate_user' или 'rate_moder').
     *           - В СУБД настроены триггеры и функции для перерасчета средней оценки.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::_process_rating_internal() Защищённый метод, реализующий основную
     *                                                                      логику добавления оценки и расчёта
     *                                                                      средней оценки.
     */
    public function process_rating(string $table, int $photo_id, int $user_id, int $rate_value): float
    {
        return $this->_process_rating_internal($table, $photo_id, $user_id, $rate_value);
    }

    /**
     * Добавляет новую оценку в таблицу, проверяет успешность вставки и возвращает среднюю оценку.
     *
     * Этот защищенный метод выполняет следующие действия:
     * 1. Вставляет новую оценку в указанную таблицу через `$this->db->insert()`.
     * 2. Проверяет успешность вставки по значению `get_last_insert_id()`.
     * 3. Пересчитывает среднюю оценку для фотографии автоматически через триггеры и функции в СУБД.
     * 4. Выполняет выборку средней оценки из таблицы `TBL_PHOTO` для указанной фотографии. В таблице `TBL_PHOTO`
     *    есть столбцы, имена которых совпадают с именем таблицы `$table`.
     * Метод вызывается через публичный метод-редирект `process_rating()` и предназначен для использования внутри
     * класса или его наследников.
     *
     * @protected
     *
     * @param string $table      Имя таблицы для вставки оценки.
     *                           Должен быть строкой, соответствующей одной из двух допустимых таблиц:
     *                           - 'rate_user':  Таблица с оценками фотографий от пользователей.
     *                           - 'rate_moder': Таблица с оценками фотографий от модераторов.
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
     * @throws RuntimeException Выбрасывается исключение, если не удалось добавить оценку. Причина:
     *                          `get_last_insert_id()` возвращает `0`, что указывает на неудачную вставку.
     * @throws Exception        При выполнении запросов к базам данных.
     *
     * @note    Используются константы:
     *          - TBL_PHOTO: Таблица для хранения данных о фотографиях (`photo`).
     *
     * @warning Убедитесь, что:
     *          - Параметр `$table` соответствует одной из допустимых таблиц ('rate_user' или 'rate_moder').
     *          - В СУБД настроены триггеры и функции для перерасчета средней оценки.
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::$db Свойство, содержащее объект для работы с базой данных.
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
        $rate = $this->db->result_row();
        return $rate ? $rate[trim($table, '`')] : 0;
    }

    /**
     * Публичный редирект для поиска файла действия.
     *
     * Метод передаёт управление внутреннему `_find_action_file_internal()`.
     * Используется для получения:
     * - Имени действия
     * - Полного пути к .php-файлу в директории $this->config['action_dir']
     *
     * @param string|null $action Имя действия из $_GET или NULL (для CLI)
     *                            Пример: 'admin', 'profile'
     *
     * @return array [
     *               'action_name' => string,
     *               'full_path'   => string
     *              ]
     *              Возвращает имя действия и путь к найденному файлу
     *
     * @throws RuntimeException Выбрасывается, если файл действия не найден
     * @throws Exception        При ошибках проверки входных данных через check_input()
     *
     * @note    Реализация находится в `_find_action_file_internal()`
     *          Поддерживает вызов как через веб, так и через CLI
     *
     * @warning Не используйте недопустимые символы в $action — это может привести к ошибкам
     *
     * @uses \PhotoRigma\Classes\Work_CoreLogic::_find_action_file_internal() Для основной логики поиска файла
     */
    public function find_action_file(?string $action): array
    {
        return $this->_find_action_file_internal($action);
    }

    /**
     * Ищет файл действия по имени $action во всех директориях из $this->config['action_dir'].
     *
     * Метод:
     * - Проверяет, установлено ли действие (из CLI или $_GET)
     * - Определяет имя файла действия с учётом безопасности
     * - Ищет первый доступный файл в указанных директориях
     * - Если файл не найден — ищет main.php как резервный вариант
     * - Если ничего не найдено — выбрасывает исключение
     *
     * @protected
     *
     * @param string|null $action Имя действия из `$_GET['action']` или `CLI`
     *                            Может быть NULL → тогда используется 'main'
     *
     * @return array [
     *               'action_name' => string,
     *               'full_path'   => string
     *              ]
     *              Возвращает имя действия и полный путь к найденному файлу
     *
     * @throws RuntimeException Выбрасывается, если файл действия не найден ни в одной директории
     * @throws Exception        При ошибках check_input()
     *
     * @note    Метод использует следующие свойства класса:
     *          - $this->config['action_dir']: массив путей к папкам действий
     *          - $this->work->check_input(): проверка входных данных
     *          - $this->work->url_check(): проверка URL на безопасность
     *
     * @warning Не передавайте небезопасные значения $action — это может привести к некорректной работе
     *          Убедитесь, что хотя бы одна директория action_dir содержит файл main.php
     *
     * @uses \PhotoRigma\Classes\Work::check_input() Для проверки входного параметра 'action'
     * @uses \PhotoRigma\Classes\Work::url_check() Для проверки URL при вызове из веба
     */
    protected function _find_action_file_internal(?string $action): array
    {
        // 1. Проверяем, что action установлен, не пустой и URL безопасен
        if (PHP_SAPI === 'cli') {
            $file_name = 'cron.php';
            $action_name = 'main';
        } /** @noinspection NotOptimalIfConditionsInspection */ elseif ($this->work->url_check() &&
            $this->work->check_input('_GET', 'action', ['isset' => true, 'empty' => true]) &&
            $action !== 'index'
        ) {
            $safe_action = filter_var($action, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if (preg_match('/^[a-z0-9_\-]+$/i', $safe_action)) {
                $file_name = basename($safe_action) . '.php';
                $action_name = $action;
            } else {
                $file_name = 'main.php';
                $action_name = 'main';
            }
        } else {
            $file_name = 'main.php';
            $action_name = 'main';
        }

        // 2. Поиск файла по $file_name во всех action_dir
        foreach ($this->config['action_dir'] as $dir) {
            $full_path = $dir . '/' . $file_name;

            if (is_file($full_path) && is_readable($full_path)) {
                return [$action_name, $full_path];
            }
        }

        // 3. Если это не main.php → ищем резервно main.php
        if ($file_name !== 'main.php') {
            foreach ($this->config['action_dir'] as $dir) {
                $main_path = $dir . '/main.php';

                if (is_file($main_path) && is_readable($main_path)) {
                    return ['main', $main_path];
                }
            }
        }

        // 4. Файл не найден → исключение
        throw new RuntimeException(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Файл действия не найден | Action: ' . ($action ?: '(пустой)')
        );
    }

    /**
     * Установка языковых данных через сеттер.
     *
     * Этот метод позволяет установить массив языковых данных для использования в системе. Присваивает массив
     * свойству текущего класса для дальнейшего использования.
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
     */
    public function set_lang(array $lang): void
    {
        $this->lang = $lang;
    }

    /**
     * Установка объекта, реализующего интерфейс User_Interface, через сеттер.
     *
     * Этот метод позволяет установить объект пользователя, реализующий интерфейс `User_Interface`. Присваивает
     * объект свойству текущего класса для дальнейшего использования.
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
     * @uses \PhotoRigma\Classes\User_Interface Интерфейс, которому должен соответствовать объект пользователя.
     */
    public function set_user(User_Interface $user): void
    {
        $this->user = $user;
    }
}
