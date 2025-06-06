<?php

/**
 * @file      include/User.php
 * @brief     Класс для управления пользователями, включая регистрацию, аутентификацию, управление профилем и
 *            хранение текущих настроек пользователя.
 *
 * @author    Dark Dayver
 * @version   0.4.4
 * @date      2025-05-07
 * @namespace PhotoRigma\\Classes
 *
 * @details   Этот файл содержит реализацию класса `User` и интерфейса `User_Interface`, которые предоставляют методы
 *            для работы с пользователями:
 *            - Управление данными текущего пользователя (добавление, обновление, удаление).
 *            - Работа с группами пользователей (добавление, обновление, удаление).
 *            - Генерация CSRF-токенов для защиты форм.
 *            - Обработка прав доступа пользователей и групп.
 *            - Проверка данных для входа в систему.
 *            Все ошибки, возникающие при работе с пользователями, обрабатываются через исключения.
 *
 * @section   User_Main_Functions Основные функции
 *            - Управление данными текущего пользователя.
 *            - Работа с группами пользователей.
 *            - Генерация CSRF-токенов для защиты форм.
 *            - Обработка прав доступа пользователей и групп.
 *            - Проверка данных для входа в систему.
 *
 * @see       PhotoRigma::Classes::User
 *            Класс для работы с пользователями.
 * @see       PhotoRigma::Interfaces::User_Interface
 *            Интерфейс для работы с пользователями.
 *
 * @note      Этот файл является частью системы PhotoRigma и играет ключевую роль в организации работы приложения.
 *            Реализованы меры безопасности для предотвращения несанкционированного доступа и модификации данных.
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

use DateTime;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use JsonException;
use PDOException;
use PhotoRigma\Interfaces\Database_Interface;
use PhotoRigma\Interfaces\User_Interface;
use PhotoRigma\Interfaces\Work_Interface;
use Random;
use RuntimeException;
use UnexpectedValueException;

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
 * @class   User
 * @brief   Класс для работы с пользователями и хранения данных о текущем пользователе.
 *
 * @details Этот класс реализует интерфейс `User_Interface` и предоставляет функционал для работы с данными
 *          пользователей. Идентификация пользователя происходит через данные из глобального массива $_SESSION.
 *          Основные возможности:
 *          - Управление данными текущего пользователя (добавление, обновление, удаление).
 *          - Поддержка мягкого удаления (`deleted_at`) и окончательного удаления (`permanently_deleted`)
 *          - Работа с группами пользователей (добавление, обновление, удаление)
 *          - Генерация CSRF-токенов для защиты форм
 *          - Обработка прав доступа пользователей и групп
 *          - Проверка данных для входа в систему
 *          Все ошибки, возникающие при работе с пользователями, обрабатываются через исключения.
 *
 * @implements User_Interface
 *
 * @property array               $user              Массив, содержащий все данные о текущем пользователе.
 * @property Database_Interface  $db                Объект для работы с базой данных.
 * @property array               $session           Массив, привязанный к глобальному массиву $_SESSION.
 * @property array               $user_right_fields Массив с полями наименований прав доступа.
 * @property Work_Interface|null $work              Свойство для объекта класса Work.
 *
 * @todo    Внедрить ряд методов по работе с группами (добавление, удаление).
 *
 * Пример создания объекта класса User:
 * @code
 * $db = new \PhotoRigma\Classes\Database();
 * $user = new \PhotoRigma\Classes\User($db, $_SESSION);
 * @endcode
 * @see    PhotoRigma::Interfaces::User_Interface
 *         Интерфейс, который реализует класс.
 */
class User implements User_Interface
{
    // Свойства
    private array $user = []; ///< Массив, содержащий все данные о текущем пользователе.
    private Database_Interface $db; ///< Объект для работы с базой данных.
    private array $session; ///< Массив, привязанный к глобальному массиву $_SESSION
    private array $user_right_fields = []; ///< Массив с полями наименований прав доступа
    private ?Work_Interface $work = null; ///< Свойство для объекта класса Work

    /**
     * @brief   Конструктор класса.
     *
     * @details Этот метод вызывается автоматически при создании нового объекта класса.
     *          Используется для подключения объекта Database_Interface и глобального массива $_SESSION.
     *          После подключения выполняется инициализация и наполнение данных о текущем пользователе.
     *          Алгоритм работы:
     *          1. Сохраняет объект базы данных в свойство `$db`.
     *          2. Проверяет наличие ключа `KEY_SESSION` в массиве сессии:
     *             - Если ключ отсутствует, инициализирует его пустым массивом.
     *          3. Создает ссылку на массив сессии по ключу `KEY_SESSION` в свойстве `$session`.
     *          4. Вызывает метод `_all_right_fields()` для наполнения массива именами полей прав пользователей и групп.
     *          5. Вызывает метод `_initialize_user()` для инициализации данных о текущем пользователе.
     *
     * @callgraph
     *
     * @param Database_Interface $db      Объект для работы с базой данных.
     *                                    Должен реализовывать интерфейс `Database_Interface`.
     *                                    Пример: `$db = new \PhotoRigma\Classes\Database();`.
     * @param array             &$session Ссылка на массив сессии ($_SESSION).
     *                                    Должен быть ассоциативным массивом.
     *                                    Пример: `$_SESSION`.
     *
     * @throws JsonException Выбрасывается, если произошла ошибка при работе с JSON (например, при сериализации данных
     *                       в методах `_initialize_user()` или `_all_right_fields()`).
     *
     * @note    Используется константа `KEY_SESSION`: Ключ для использования в глобальном массиве $_SESSION.
     *
     * @warning Убедитесь, что:
     *          - Переданный объект `$db` реализует интерфейс `Database_Interface`.
     *          - Массив `$_SESSION` является ассоциативным массивом.
     *          Несоблюдение этих условий может привести к ошибкам инициализации.
     *
     * Пример создания объекта:
     * @code
     * // Создание объекта базы данных
     * $db = new \PhotoRigma\Classes\Database();
     *
     * // Создание объекта класса User с передачей $_SESSION напрямую
     * $user = new \PhotoRigma\Classes\User($db, $_SESSION);
     * @endcode
     * @see    PhotoRigma::Classes::User::$db
     *         Свойство, содержащее объект для работы с базой данных.
     * @see    PhotoRigma::Classes::User::$session
     *         Свойство, содержащее ссылку на массив сессии ($_SESSION) по ключу `KEY_SESSION`.
     * @see    PhotoRigma::Classes::User::_initialize_user()
     *         Метод, выполняющий инициализацию данных о пользователе.
     * @see    PhotoRigma::Classes::User::_all_right_fields()
     *         Метод, наполняющий массив именами полей прав пользователей и групп.
     */
    public function __construct(Database_Interface $db, array &$session)
    {
        $this->db = $db;
        if (!isset($session[KEY_SESSION])) {
            $session[KEY_SESSION] = [];
        }
        $this->session = &$session[KEY_SESSION];
        $this->_all_right_fields();
        $this->_initialize_user();
    }

    /**
     * @brief   Метод загружает права первого пользователя и первой группы из базы данных, извлекает имена полей (ключи)
     *          из JSON-поля `user_rights`, объединяет их в один массив и сохраняет в свойство `$user_right_fields`.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Загружает первые доступные записи из таблиц `TBL_USERS` и `TBL_GROUP`.
     *             - Если запись не найдена, выбрасывается исключение.
     *          2. Извлекает поле `user_rights` (JSON) из каждой записи.
     *             - Если поле отсутствует или некорректно, сохраняется пустой массив.
     *          3. Декодирует JSON с помощью метода `process_user_rights()` и сохраняет имена полей (ключи)
     *             в свойство `$user_right_fields`.
     *          4. Объединяет поля прав пользователей и групп в один массив (`$user_right_fields['all']`),
     *             используя `array_unique()` для удаления дубликатов.
     *          5. Сохраняет результаты в свойство `$user_right_fields`:
     *             - Права пользователя: `$user_right_fields['user']`.
     *             - Права группы: `$user_right_fields['group']`.
     *             - Объединённые уникальные поля: `$user_right_fields['all']`.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @throws RuntimeException Выбрасывается, если:
     *                          - Не удаётся получить права первого пользователя из таблицы `TBL_USERS`.
     *                            Пример сообщения:
     *                                Ошибка базы данных | Не удалось получить права первого пользователя
     *                          - Не удаётся получить права первой группы из таблицы `TBL_GROUP`.
     *                            Пример сообщения:
     *                                Ошибка базы данных | Не удалось получить права первой группы
     * @throws JsonException    Выбрасывается, если произошла ошибка при декодировании JSON.
     *                            Пример сообщения:
     *                                Ошибка при обработке JSON | Поле: user_rights
     * @throws Exception        Выбрасывается при выполнении запросов к СУБД.
     *
     * @note    Метод сохраняет имена полей прав пользователей и групп в свойство `$user_right_fields`:
     *          - Права пользователя сохраняются в `$user_right_fields['user']`.
     *          - Права группы сохраняются в `$user_right_fields['group']`.
     *          - Объединённые уникальные поля сохраняются в `$user_right_fields['all']`.
     *          Если данные отсутствуют, сохраняются пустые массивы.
     *          Для указания таблиц базы данных используются константы:
     *          - `TBL_USERS`: Таблица пользователей с их правами. Например: '`users`'.
     *          - `TBL_GROUP`: Таблица групп пользователей с их правами. Например: '`groups`'.
     *          Эти константы позволяют гибко настраивать работу метода.
     *
     * @warning Поле `user_rights` должно содержать валидный JSON. Невалидные данные могут привести к исключению.
     *
     * Пример вызова метода _all_right_fields():
     * @code
     * $this->_all_right_fields();
     * @endcode
     * @see    PhotoRigma::Classes::User::$user_right_fields
     *         Свойство, содержащее имена полей прав пользователей и групп.
     * @see    PhotoRigma::Classes::User::$db
     *         Свойство, содержащее объект для работы с базой данных.
     * @see    PhotoRigma::Classes::User::process_user_rights()
     *         Метод для обработки прав пользователя/группы.
     */
    private function _all_right_fields(): void
    {
        // === Загрузка прав первого пользователя ===
        if (!$this->db->select('`user_rights`', TBL_USERS, ['limit' => 1])) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Ошибка базы данных | Не удалось получить права первого пользователя'
            );
        }

        $user_result = $this->db->result_row();
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
        if (!$this->db->select('`user_rights`', TBL_GROUP, ['limit' => 1])) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Ошибка базы данных | Не удалось получить права первой группы'
            );
        }

        $group_result = $this->db->result_row();
        if ($group_result && isset($group_result['user_rights'])) {
            // Обрабатываем права группы
            $group_rights = $this->process_user_rights($group_result['user_rights']);
            // Сохраняем имена полей прав группы
            $this->user_right_fields['group'] = array_keys($group_rights);
        } else {
            // Если права группы отсутствуют, сохраняем пустой массив
            $this->user_right_fields['group'] = [];
        }

        // Объединяем поля прав в один массив
        $this->user_right_fields['all'] = array_unique(
            array_merge(
                $this->user_right_fields['user'],
                $this->user_right_fields['group']
            )
        );
    }

    /**
     * @brief   Обрабатывает поле `user_rights`, преобразуя его из строки JSON в массив прав, через вызов внутреннего
     *          метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _process_user_rights_internal().
     *          Он проверяет корректность входных данных, декодирует строку JSON в ассоциативный массив прав и
     *          возвращает результат. Предназначен для прямого использования извне.
     *
     * @callgraph
     *
     * @param string $user_rights Значение поля `user_rights`:
     *                            - Должно быть строкой, содержащей валидный JSON (например, '{"edit": 1,
     *                            "delete": 0}').
     *                            - Если значение пустое или некорректное, возвращается пустой массив.
     *
     * @return array Ассоциативный массив прав пользователя. Если поле `user_rights` отсутствует или содержит
     *               некорректные данные, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Выбрасывается, если поле `user_rights` не является строкой.
     *                                  Пример сообщения:
     *                                      Некорректный формат JSON | Поле user_rights содержит невалидные данные
     * @throws JsonException            Выбрасывается, если поле `user_rights` содержит невалидный JSON.
     *
     * @note    Метод возвращает пустой массив, если поле `user_rights` отсутствует или содержит некорректные данные.
     *          Если декодирование JSON завершается успешно, возвращается ассоциативный массив прав.
     *
     * @warning Поле `user_rights` должно содержать валидный JSON. Невалидные данные могут привести к выбросу
     *          исключения.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $object = new \PhotoRigma\Classes\User();
     * $rights = $object->process_user_rights('{"edit": 1, "delete": 0}');
     * print_r($rights); // Выведет: ['edit' => 1, 'delete' => 0]
     *
     * // Обработка пустого значения
     * $rights = $object->process_user_rights('');
     * print_r($rights); // Выведет: []
     * @endcode
     * @see     PhotoRigma::Classes::User::_process_user_rights_internal()
     *          Защищённый метод, реализующий основную логику обработки поля `user_rights`.
     */
    public function process_user_rights(string $user_rights): array
    {
        return $this->_process_user_rights_internal($user_rights);
    }

    /**
     * @brief   Метод обрабатывает поле `user_rights`, преобразуя его из строки JSON в массив прав.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет, существует ли поле `user_rights`. Если поле отсутствует или пустое, возвращается пустой
     *             массив.
     *          2. Проверяет, что поле является строкой. Если тип данных некорректен, выбрасывается исключение.
     *          3. Декодирует строку JSON в ассоциативный массив с использованием `json_decode()`. Если декодирование
     *             завершается с ошибкой, выбрасывается исключение.
     *          4. Возвращает массив прав, готовый для добавления в свойство `$user`.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `process_user_rights()`.
     *
     * @callergraph
     *
     * @param string $user_rights Значение поля `user_rights`. Может быть строкой JSON, пустым значением или `null`.
     *                            Пример: '{"edit": 1, "delete": 0}'.
     *                            Ограничения: значение должно быть строкой, содержащей валидный JSON.
     *
     * @return array Ассоциативный массив прав пользователя. Если поле `user_rights` отсутствует или содержит
     *               некорректные данные, возвращается пустой массив.
     *
     * @throws InvalidArgumentException Выбрасывается, если поле `user_rights` не является строкой.
     *                                  Пример сообщения:
     *                                      Некорректный формат JSON | Поле user_rights содержит невалидные данные
     * @throws JsonException            Выбрасывается, если поле `user_rights` содержит невалидный JSON.
     *
     * @note    Метод возвращает пустой массив, если поле `user_rights` отсутствует или содержит некорректные данные.
     *          Если декодирование JSON завершается успешно, возвращается ассоциативный массив прав.
     *
     * @warning Поле `user_rights` должно содержать валидный JSON. Невалидные данные могут привести к выбросу
     *          исключения.
     *
     * Пример использования метода _process_user_rights_internal():
     * @code
     * // Обработка валидного JSON
     * $rights = $this->_process_user_rights_internal('{"edit": 1, "delete": 0}');
     * print_r($rights);
     *
     * // Обработка пустого значения
     * $rights = $this->_process_user_rights_internal('');
     * print_r($rights); // Выведет: []
     * @endcode
     * @see    PhotoRigma::Classes::User::process_user_rights()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _process_user_rights_internal(string $user_rights): array
    {
        // Проверяем, существует ли поле user_rights
        if (empty($user_rights)) {
            return [];
        }

        // Декодируем JSON
        $rights = json_decode($user_rights, true, 512, JSON_THROW_ON_ERROR);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Некорректный формат JSON | Поле user_rights содержит невалидные данные'
            );
        }

        // Возвращаем массив прав
        return is_array($rights) ? $rights : [];
    }

    /**
     * @brief   Инициализация данных пользователя: определение типа пользователя (гость или аутентифицированный) на
     *          основе значения `login_id` из сессии.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет наличие `login_id` в массиве сессии:
     *             - Если `login_id` отсутствует, используется значение по умолчанию: 0.
     *          2. Преобразует `login_id` в целое число и сохраняет его обратно в сессию.
     *          3. Определяет тип пользователя:
     *             - Если `login_id` равен 0, загружается гость с помощью метода `_load_guest_user()`.
     *             - Если `login_id` не равен 0, загружается аутентифицированный пользователь с помощью метода
     *               `_load_authenticated_user($login_id)`.
     *          Метод является приватным и предназначен только для использования внутри класса.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @throws JsonException Выбрасывается, если вызываемые методы `_load_guest_user()` или `_load_authenticated_user()`
     *                       выбрасывают исключение при работе с JSON.
     *
     * @note    Значение `login_id` всегда преобразуется в целое число и сохраняется в сессии.
     *
     * @warning Убедитесь, что:
     *          - Массив сессии содержит корректные данные.
     *          - Методы `_load_guest_user()` и `_load_authenticated_user()` корректно обрабатывают JSON.
     *          Несоблюдение этих условий может привести к ошибкам инициализации.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $this->_initialize_user();
     * @endcode
     * @see    PhotoRigma::Classes::User::$session
     *         Свойство, содержащее данные сессии.
     * @see    PhotoRigma::Classes::User::_load_guest_user()
     *         Метод для загрузки данных гостя.
     * @see    PhotoRigma::Classes::User::_load_authenticated_user()
     *         Метод для загрузки данных аутентифицированного пользователя.
     */
    private function _initialize_user(): void
    {
        $login_id = (int)($this->session['login_id'] ?? 0);
        $this->session['login_id'] = $login_id;
        if ($login_id === 0) {
            $this->_load_guest_user();
        } else {
            $this->_load_authenticated_user($login_id);
        }
    }

    /**
     * @brief   Метод загружает данные группы гостя из базы данных, обрабатывает права (`user_rights`) и сохраняет
     *          итоговые данные в свойство `$user`.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Выполняет запрос к базе данных для получения данных группы гостя с `id = GROUP_GUEST`, используя
     *             константу `GROUP_GUEST` и константу `TBL_GROUP` для указания таблицы.
     *             - Если запрос не выполнен или данные отсутствуют, выбрасывается исключение.
     *          2. Проверяет корректность полученных данных:
     *             - Данные должны быть массивом. Если данные некорректны, выбрасывается исключение.
     *          3. Удаляет поле `user_rights` из данных группы гостя.
     *          4. Обрабатывает поле `user_rights` с помощью метода `process_user_rights()`, преобразуя строку JSON
     *             в ассоциативный массив.
     *          5. Объединяет данные группы гостя с обработанными правами с помощью `array_merge()`.
     *          6. Сохраняет итоговые данные в свойство `$user`.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @throws RuntimeException         Выбрасывается, если:
     *                                  - Не удаётся получить данные группы гостя из базы данных.
     *                                    Пример сообщения:
     *                                        Ошибка базы данных | Не удалось получить данные группы гостя
     * @throws UnexpectedValueException Выбрасывается, если:
     *                                  - Данные группы гостя некорректны (например, пустые или не являются массивом).
     *                                    Пример сообщения:
     *                                        Некорректные данные группы гостя | Проверьте формат данных в таблице
     *                                        groups
     * @throws JsonException            Выбрасывается, если произошла ошибка при декодировании JSON.
     *                                  Пример сообщения:
     *                                        Ошибка при обработке JSON | Поле: user_rights
     * @throws Exception                Выбрасывается при выполнении запросов к СУБД.
     *
     * @note    Для указания таблицы базы данных используется константа `TBL_GROUP`. Например: '`groups`'.
     *          Константа `GROUP_GUEST` содержит ID группы гостей.
     *          Поле `user_rights` должно содержать валидный JSON. После обработки права объединяются с данными группы.
     *
     * @warning Поле `user_rights` обязательно должно содержать валидный JSON. Невалидные данные могут привести к
     *          ошибкам.
     *
     * Пример вызова метода _load_guest_user():
     * @code
     * $this->_load_guest_user();
     * @endcode
     * @see    PhotoRigma::Classes::User::$user
     *         Свойство, содержащее данные текущего пользователя.
     * @see    PhotoRigma::Classes::User::$db
     *         Свойство, содержащее объект для работы с базой данных.
     * @see    PhotoRigma::Classes::User::process_user_rights()
     *         Метод для обработки прав пользователя.
     */
    private function _load_guest_user(): void
    {
        // Выполняем запрос к базе данных для получения данных группы гостя
        if (!$this->db->select(
            '`id`, `user_rights`',
            TBL_GROUP,
            ['where' => '`id` = :group_id', 'params' => ['group_id' => GROUP_GUEST]]
        )) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Ошибка базы данных | Не удалось получить данные группы гостя'
            );
        }

        // Получаем данные группы гостя
        $guest_group = $this->db->result_row();
        if (!$guest_group || !is_array($guest_group)) {
            throw new UnexpectedValueException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Некорректные данные группы гостя | Проверьте формат данных в таблице ' . TBL_GROUP
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
     * @brief   Метод загружает данные аутентифицированного пользователя из базы данных, включая права пользователя и
     *          группы, обновляет дату последней активности, устанавливает язык и тему в сессии, и сохраняет данные в
     *          свойство `$user`. Если данные пользователя или группы отсутствуют, сбрасывает сессию до гостя.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Загружает данные пользователя из таблицы `TBL_USERS` по `user_id`.
     *             - Если данные пользователя отсутствуют, сбрасывает сессию до гостя и вызывает метод
     *               `_load_guest_user()`.
     *          2. Удаляет поле `user_rights` из данных пользователя и обрабатывает его с помощью метода
     *             `process_user_rights()`.
     *          3. Сохраняет обработанные права в свойство `$user`.
     *          4. Загружает данные группы пользователя из таблицы `TBL_GROUP` по `group_id`.
     *             - Если данные группы отсутствуют, сбрасывает сессию до гостя и вызывает метод `_load_guest_user()`.
     *          5. Удаляет поле `user_rights` из данных группы и обрабатывает его с помощью метода
     *             `process_user_rights()`.
     *          6. Объединяет данные пользователя и группы через метод `_merge_user_with_group()`.
     *          7. Устанавливает язык и тему сайта в сессии (`$this->session['language']` и `$this->session['theme']`)
     *             на основе данных пользователя.
     *          8. Обновляет дату последней активности пользователя в таблице `TBL_USERS`.
     *          9. Проверяет статус удаления пользователя:
     *              - Если пользователь окончательно удален (`permanently_deleted = TRUE`), переводит в гости.
     *              - Если пользователь был мягко удален, и срок восстановления истек, также переводит в гости.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param int $user_id Идентификатор пользователя. Должен быть положительным целым числом.
     *                     Пример: 123.
     *                     Ограничения: ID должен быть больше 0.
     *
     * @throws RuntimeException Выбрасывается, если:
     *                          - Не удаётся получить данные пользователя из таблицы `TBL_USERS`.
     *                            Пример сообщения:
     *                                Ошибка базы данных | Не удалось получить данные пользователя с ID:
     *                                [user_id]
     *                          - Не удаётся получить данные группы пользователя из таблицы `TBL_GROUP`.
     *                            Пример сообщения:
     *                                Ошибка базы данных | Не удалось получить данные группы пользователя с ID:
     *                                [group_id]
     *                          - Не удаётся обновить дату последней активности пользователя в таблице `TBL_USERS`.
     *                            Пример сообщения:
     *                                Ошибка базы данных | Не удалось обновить дату последней активности пользователя
     *                                с ID: [user_id]
     * @throws JsonException    Выбрасывается, если произошла ошибка при работе с JSON.
     *                          Пример сообщения:
     *                                Ошибка при обработке JSON | Поле: user_rights
     * @throws Exception        Выбрасывается при выполнении запросов к СУБД.
     *
     * @note    Для указания таблиц базы данных используются константы:
     *          - `TBL_USERS`: Таблица пользователей с их правами. Например: '`users`'.
     *          - `TBL_GROUP`: Таблица групп пользователей с их правами. Например: '`groups`'.
     *          Поля `user_rights` в данных пользователя и группы должны содержать валидный JSON. После обработки права
     *          объединяются с данными пользователя.
     *
     * @warning Поля `user_rights` в данных пользователя и группы обязательно должны содержать валидный JSON.
     *          Невалидные данные могут привести к ошибкам.
     *
     * Пример вызова метода _load_authenticated_user():
     * @code
     * $this->_load_authenticated_user(123);
     * @endcode
     * @see    PhotoRigma::Classes::User::$db
     *         Свойство, содержащее объект для работы с базой данных.
     * @see    PhotoRigma::Classes::User::$user
     *         Свойство, содержащее данные текущего пользователя.
     * @see    PhotoRigma::Classes::User::$session
     *         Свойство, содержащее данные сессии.
     * @see    PhotoRigma::Classes::User::process_user_rights()
     *         Метод для обработки прав пользователя/группы.
     * @see    PhotoRigma::Classes::User::_merge_user_with_group()
     *         Метод для объединения данных пользователя и группы.
     * @see    PhotoRigma::Classes::User::_is_soft_delete_expired_internal()
     *         Метод для проверки истечения срока мягкого удаления.
     */
    private function _load_authenticated_user(int $user_id): void
    {
        // Загружаем данные пользователя
        if (!$this->db->select('*', TBL_USERS, ['where' => '`id` = :user_id', 'params' => ['user_id' => $user_id]])) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка базы данных | Не удалось получить данные пользователя с ID: $user_id"
            );
        }

        $user_data = $this->db->result_row();
        if (!$user_data) {
            $this->session['login_id'] = 0;
            $this->_load_guest_user();
            return;
        }

        // Проверяем окончательное удаление
        if (!empty($user_data['permanently_deleted'])) {
            $this->session['login_id'] = 0;
            $this->_load_guest_user();
            return;
        }

        // Проверяем, прошло ли время восстановления после мягкого удаления
        if (!empty($user_data['deleted_at']) && $this->_is_soft_delete_expired_internal($user_data['deleted_at'])['expired']) {
            $this->session['login_id'] = 0;
            $this->_load_guest_user();
            return;
        }

        // Удаляем поле user_rights из данных пользователя
        $user_rights = $user_data['user_rights'] ?? null;
        if (is_array($user_data)) {
            unset($user_data['user_rights']);
        }

        // Обрабатываем права пользователя (user_rights)
        $processed_rights = $this->process_user_rights($user_rights);

        // Присваиваем данные пользователя с добавлением прав
        $this->user = array_merge($user_data, $processed_rights);

        // Загружаем данные группы пользователя
        if (!$this->db->select(
            '*',
            TBL_GROUP,
            ['where' => '`id` = :group_id', 'params' => ['group_id' => $this->user['group_id']]]
        )) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка базы данных | Не удалось получить данные группы пользователя с ID: {$this->user['group']}"
            );
        }

        $group_data = $this->db->result_row();
        if (!$group_data) {
            $this->session['login_id'] = 0;
            $this->_load_guest_user();
            return;
        }

        // Устанавливаем язык и тему сайта
        $this->session['language'] = $this->user['language'];
        $this->session['theme'] = $this->user['theme'];
        $this->session['timezone'] = $this->user['timezone'];

        // Удаляем поле user_rights из данных группы
        $group_rights = $group_data['user_rights'] ?? null;
        unset($group_data['user_rights']);

        // Обрабатываем права группы (user_rights)
        $processed_rights = $this->process_user_rights($group_rights);

        // Присваиваем данные пользователя с добавлением прав группы
        $this->_merge_user_with_group(array_merge($group_data, $processed_rights));

        // Обновляем данные о последней активности пользователя
        if (!$this->db->update(
            ['`date_last_activ`' => 'NOW()'],
            TBL_USERS,
            ['where' => '`id` = :user_id', 'params' => [':user_id' => $user_id]]
        )) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка базы данных | Не удалось обновить дату последней активности пользователя с ID: $user_id"
            );
        }
    }

    /**
     * @brief   Метод объединяет данные пользователя с данными его группы, применяя логику перезаписи значений, и
     *          изменяет свойство `$user` класса.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проходит по массиву данных группы (`$group_data`) и объединяет их с данными пользователя (`$user`)
     *             согласно следующей логике:
     *             - Ключ `name`: Значение сохраняется в `$user['group_name']`.
     *             - Ключ `id`: Игнорируется.
     *             - Для всех остальных ключей:
     *               - Если ключ существует в `$user`:
     *                 - Если оба значения равны `false`, сохраняется `false`.
     *                 - Иначе сохраняется `true`.
     *               - Если ключ отсутствует в `$user`, значение берётся из `$group_data`.
     *          2. Для обработки ключей используется оператор `match`.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @internal
     * @callergraph
     *
     * @param array $group_data Ассоциативный массив, содержащий данные группы пользователя.
     *                          Ключи должны соответствовать полям в таблице групп.
     *
     * @note    Ключ `id` в данных группы игнорируется.
     *
     * @warning Метод предполагает, что структура данных группы совпадает с ожидаемой. Неправильная структура может
     *          привести к ошибкам.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $this->_merge_user_with_group(['name' => 'Admins', 'id' => 1, 'permission_edit' => true]);
     * @endcode
     * @see    PhotoRigma::Classes::User::$user
     *         Свойство, содержащее данные текущего пользователя.
     */
    private function _merge_user_with_group(array $group_data): void
    {
        foreach ($group_data as $key => $value) {
            match ($key) {
                'name'  => [
                    $this->user['group_name'] = $value,
                ],
                'id'    => null, // Пропускаем ключ 'id'
                default => $this->user[$key] = isset($this->user[$key]) && !$this->user[$key] && !$value ? false : $value,
            };
        }
    }

    /**
     * @brief   Устанавливает значение свойства в массивах `$user` или `$session` через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _set_property_key_internal().
     *          Он проверяет корректность входных данных, определяет целевой массив (`$user` или `$session`) и
     *          устанавливает значение по указанному ключу. Предназначен для прямого использования извне.
     *
     * @callgraph
     *
     * @param string          $name  Имя свойства:
     *                               - Допустимые значения: 'user' (для массива данных пользователя) или 'session'
     *                               (для массива данных сессии).
     *                               - Пример: "user".
     *                               Ограничения: только допустимые значения ('user' или 'session').
     * @param string          $key   Ключ, по которому будет установлено значение:
     *                               - Должен быть строкой.
     *                               - Пример: "username".
     * @param string|int|bool $value Значение, которое будет установлено:
     *                               - Может быть строкой, целым числом или булевым значением.
     *                               - Пример: "admin" (строка), 123 (целое число), true (булево значение).
     *
     * @throws InvalidArgumentException Выбрасывается, если параметр `$name` содержит недопустимое значение.
     *                                  Пример сообщения:
     *                                      Недопустимое свойство | Свойство: [значение]
     *
     * @note    Метод изменяет внутренние массивы `$user` или `$session` напрямую.
     *          Перед установкой значения проверяется корректность имени свойства.
     *
     * @warning Недопустимое значение параметра `$name` приведет к выбросу исключения.
     *          Убедитесь, что передаете только допустимые значения ('user' или 'session').
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $object = new \PhotoRigma\Classes\User();
     *
     * // Установка значения в массиве $user
     * $object->set_property_key('user', 'username', 'admin');
     *
     * // Установка значения в массиве $session
     * $object->set_property_key('session', 'logged_in', true);
     * @endcode
     * @see    PhotoRigma::Classes::User::_set_property_key_internal()
     *         Защищённый метод, реализующий основную логику установки значения свойства.
     */
    public function set_property_key(string $name, string $key, string|int|bool $value): void
    {
        $this->_set_property_key_internal($name, $key, $value);
    }

    /**
     * @brief   Метод устанавливает значение свойства в массивах `$user` или `$session`.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет, какой массив (`$user` или `$session`) нужно изменить, на основе параметра `$name`.
     *          2. Устанавливает значение по указанному ключу в выбранном массиве.
     *          3. Если передано недопустимое имя свойства (не 'user' и не 'session'), выбрасывается исключение.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `set_property_key()`.
     *
     * @callergraph
     *
     * @param string          $name  Имя свойства. Допустимые значения: 'user' (для массива данных пользователя)
     *                               или 'session' (для массива данных сессии).
     *                               Пример: "user".
     *                               Ограничения: только допустимые значения ('user' или 'session').
     * @param string          $key   Ключ, по которому будет установлено значение. Должен быть строкой.
     *                               Пример: "username".
     *                               Ограничения: ключ должен быть строкой.
     * @param string|int|bool $value Значение, которое будет установлено. Может быть строкой, целым числом
     *                               или булевым значением.
     *                               Пример: "admin" (строка), 123 (целое число), true (булево значение).
     *
     * @throws InvalidArgumentException Выбрасывается, если параметр `$name` содержит недопустимое значение.
     *                                  Пример сообщения:
     *                                      Недопустимое свойство | Свойство: [значение]
     *
     * @note    Метод изменяет внутренние массивы `$user` или `$session` напрямую.
     *          Перед установкой значения проверяется корректность имени свойства.
     *
     * @warning Недопустимое значение параметра `$name` приведет к выбросу исключения.
     *          Убедитесь, что передаете только допустимые значения ('user' или 'session').
     *
     * Пример использования метода _set_property_key_internal():
     * @code
     * // Установка значения в массиве $user
     * $this->_set_property_key_internal('user', 'username', 'admin');
     *
     * // Установка значения в массиве $session
     * $this->_set_property_key_internal('session', 'logged_in', true);
     * @endcode
     * @see    PhotoRigma::Classes::User::$user
     *         Свойство класса User, содержащее данные пользователя.
     * @see    PhotoRigma::Classes::User::$session
     *         Свойство класса User, содержащее данные сессии.
     * @see    PhotoRigma::Classes::User::set_property_key()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _set_property_key_internal(string $name, string $key, string|int|bool $value): void
    {
        // Проверяем, какой массив нужно изменить
        switch ($name) {
            case 'user':
                $this->user[$key] = $value;
                break;

            case 'session':
                $this->session[$key] = $value;
                break;

            default:
                throw new InvalidArgumentException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' . "Недопустимое свойство | Свойство: $name"
                );
        }
    }

    /**
     * @brief   Устанавливает объект Work через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _set_work_internal().
     *          Он проверяет корректность переданного объекта и устанавливает его в свойство `$work`.
     *          Если в сессии отсутствует тема (`theme`), она инициализируется значением из конфигурации объекта,
     *          реализующего интерфейс `Work_Interface`.
     *          Предназначен для прямого использования извне.
     *
     * @callgraph
     *
     * @param Work_Interface $work Объект, реализующий интерфейс `Work_Interface`:
     *                             - Должен быть экземпляром класса, реализующего интерфейс `Work_Interface`.
     *
     * @note    Метод проверяет тип переданного объекта.
     *          Объект, реализующий интерфейс `Work_Interface`, используется для дальнейшего взаимодействия в текущем
     *          классе.
     *
     * @warning Некорректный объект (не реализует интерфейс `Work_Interface`) вызывает исключение.
     *
     * Пример использования:
     * @code
     * // Создание объекта User
     * $user = new \PhotoRigma\Classes\User();
     *
     * // Создание объекта Work
     * $work = new \PhotoRigma\Classes\Work();
     *
     * // Установка объекта Work
     * $user->set_work($work);
     * @endcode
     * @see    PhotoRigma::Classes::User::_set_work_internal()
     *         Защищённый метод, реализующий основную логику установки объекта Work.
     */
    public function set_work(Work_Interface $work): void
    {
        $this->_set_work_internal($work);
    }

    /**
     * @brief   Установка объекта Work_Interface через сеттер.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет, что переданный объект реализует интерфейс `Work_Interface`. Если объект некорректен,
     *             выбрасывается исключение.
     *          2. Присваивает объект свойству `$work` текущего класса.
     *          3. Если в сессии отсутствует тема (`theme`), устанавливает её значение из конфигурации объекта,
     *             реализующего интерфейс `Work_Interface`.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `set_work()`.
     *
     * @callergraph
     *
     * @param Work_Interface $work Объект, реализующий интерфейс `Work_Interface`:
     *                             - Должен быть экземпляром класса, реализующего интерфейс `Work_Interface`.
     *                             - После проверки присваивается свойству `$work` текущего класса.
     *
     * @throws InvalidArgumentException Выбрасывается, если передан некорректный объект (не реализует интерфейс
     *                                  `Work_Interface`). Пример сообщения: Некорректный объект | Передан: [тип]
     *
     * @note    Метод проверяет тип переданного объекта.
     *          Объект, реализующий интерфейс `Work_Interface`, используется для дальнейшего взаимодействия в текущем
     *          классе. Если в сессии отсутствует тема (`theme`), она инициализируется значением из конфигурации
     *          объекта, реализующего интерфейс `Work_Interface`.
     *
     * @warning Некорректный объект (не реализует интерфейс `Work_Interface`) вызывает исключение.
     *
     * Пример использования метода _set_work_internal():
     * @code
     * // Создание объекта, реализующего интерфейс Work_Interface
     * $work = new \PhotoRigma\Classes\Work();
     *
     * // Установка объекта Work_Interface
     * $this->_set_work_internal($work);
     * @endcode
     * @see    PhotoRigma::Classes::Work_Interface
     *         Интерфейс, который должен реализовывать передаваемый объект.
     * @see    PhotoRigma::Classes::User::$session
     *         Свойство класса User, содержащее данные сессии.
     * @see    PhotoRigma::Classes::User::$work
     *         Свойство для объекта, реализующего интерфейс Work_Interface.
     * @see    PhotoRigma::Classes::User::set_work()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _set_work_internal(Work_Interface $work): void
    {
        $this->work = $work;
        $this->session['theme'] ??= $work->config['theme'];
        $this->session['language'] ??= $work->config['language'];
        $this->session['timezone'] ??= $work->config['timezone'];
    }

    /**
     * @brief   Получает значение приватного свойства.
     *
     * @details Этот метод позволяет получить доступ к приватным свойствам `$user`, `$session` и `$user_right_fields`.
     *          Алгоритм работы:
     *          1. Проверяет, соответствует ли имя свойства допустимым значениям (`user`, `session`,
     *             `user_right_fields`).
     *          2. Если имя свойства корректно, возвращает значение соответствующего свойства.
     *          3. Если имя свойства некорректно, выбрасывается исключение.
     *          Метод является публичным и предназначен для получения доступа к указанным свойствам.
     *
     * @param string $name Имя свойства:
     *                     - Допустимые значения: 'user', 'session', 'user_right_fields'.
     *                     - Если указано другое имя, выбрасывается исключение.
     *                     Пример: 'user'.
     *                     Ограничения: только три допустимых значения.
     *
     * @return array Значение запрашиваемого свойства (`$user`, `$session` или `$user_right_fields`).
     *               Пример:
     *               - Для `$user`: массив данных о текущем пользователе.
     *               - Для `$session`: массив сессии, связанный с глобальным массивом $_SESSION.
     *               - Для `$user_right_fields`: массив допустимых значений прав пользователей и групп.
     *
     * @throws InvalidArgumentException Выбрасывается, если запрашиваемое свойство не существует.
     *                                  Пример сообщения:
     *                                      Свойство не существует | Получено: [$name]
     *
     * @note    Этот метод предназначен только для доступа к свойствам `$user`, `$session` и `$user_right_fields`.
     *          Любые другие запросы будут игнорироваться с выбросом исключения.
     *
     * @warning Убедитесь, что вы запрашиваете только допустимые свойства:
     *          - 'user'
     *          - 'session'
     *          - 'user_right_fields'
     *          Некорректные имена свойств вызовут исключение.
     *
     * Пример использования метода:
     * @code
     * $user = new User($db, $_SESSION);
     * echo $user->user['name']; // Выведет: Имя пользователя (если установлено)
     * echo $user->session['user_id']; // Выведет: ID пользователя из сессии
     * print_r($user->user_right_fields['user']); // Выведет список допустимых полей прав пользователя
     * @endcode
     * @see    PhotoRigma::Classes::User::$session
     *         Свойство, привязанное к глобальному массиву $_SESSION.
     * @see    PhotoRigma::Classes::User::$user
     *         Свойство, содержащее данные о текущем пользователе.
     * @see    PhotoRigma::Classes::User::$user_right_fields
     *         Свойство, содержащее допустимые значения прав пользователей и групп.
     */
    public function __get(string $name): array
    {
        return match ($name) {
            'user'              => $this->user,
            'session'           => $this->session,
            'user_right_fields' => $this->user_right_fields,
            default             => throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не существует | Получено: '$name'"
            ),
        };
    }

    /**
     * @brief   Устанавливает значение приватного свойства.
     *
     * @details Этот метод позволяет изменить значение приватного свойства `$session`.
     *          Алгоритм работы:
     *          1. Проверяет, соответствует ли имя свойства допустимым значениям (`session`).
     *             - Если имя свойства некорректно, выбрасывается исключение.
     *          2. Обновляет только те ключи в `$session`, которые переданы в `$value`.
     *             - Новые ключи добавляются в массив `$session`.
     *          3. Логирует изменения:
     *             - Отдельно обновлённые ключи.
     *             - Отдельно добавленные ключи.
     *          4. Все изменения сохраняются в свойстве `$session`.
     *          Метод является публичным и предназначен для изменения свойства `$session`.
     *
     * @callgraph
     *
     * @param string $name  Имя свойства:
     *                      - Допустимые значения: 'session'.
     *                      - Если указано другое имя, выбрасывается исключение.
     *                      Пример: 'session'.
     *                      Ограничения: только одно допустимое значение.
     * @param array  $value Новое значение свойства:
     *                      - Должен быть ассоциативным массивом.
     *                      Пример: `['user_id' => 123]`.
     *
     * @throws InvalidArgumentException Выбрасывается, если переданное имя свойства некорректно.
     *                                  Пример сообщения:
     *                                      Несуществующее свойство | Свойство: [$name]
     * @throws JsonException            Выбрасывается при ошибке кодирования данных в JSON (например, при логировании
     *                                  изменений).
     * @throws Exception                Выбрасывается, если произошла ошибка логирования в функции `log_in_file`.
     *
     * @note    Этот метод предназначен только для изменения свойства `$session`.
     *          Любые другие запросы будут игнорироваться с выбросом исключения.
     *
     * @warning Убедитесь, что:
     *          - Переданное имя свойства соответствует допустимым значениям.
     *          - Значение `$value` является ассоциативным массивом.
     *          Несоблюдение этих условий вызовет исключение.
     *
     * Пример использования метода:
     * @code
     * $user = new User($db, $_SESSION);
     * $user->session = ['user_id' => 123]; // Установит новое значение для $session
     * @endcode
     * @see    PhotoRigma::Classes::User::$session
     *         Свойство, привязанное к глобальному массиву $_SESSION.
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования ошибок и изменений.
     */
    public function __set(string $name, array $value): void
    {
        // Разрешаем изменять только свойство 'session'
        if ($name !== 'session') {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' . "Несуществующее свойство | Свойство: $name"
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
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' . "Обновление свойства '$name' | Изменённые ключи: " . json_encode(
                    $updated_keys,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                )
            );
        }
        if (!empty($added_keys)) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' . "Добавление в свойство '$name' | Новые ключи: " . json_encode(
                    $added_keys,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                )
            );
        }

        // Обновляем свойство
        $this->$name = $current_value;
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
     * $user = new User($db, $_SESSION);
     * if (isset($user->session)) {
     *     echo "Свойство 'session' существует.";
     * } else {
     *     echo "Свойство 'session' не существует.";
     * }
     * @endcode
     */
    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    /**
     * @brief   Добавляет новую группу в систему через Админку.
     *
     * @details Метод является обёрткой для внутреннего метода `_add_new_group_internal()`.
     *          Он передаёт данные о:
     *          - Имени группы (`name_group`)
     *          - Правах доступа (все поля из `$this->user_right_fields['all']`)
     *          Реализация вызывает:
     *          - Валидацию имени
     *          - Нормализацию прав доступа (true/false)
     *          - Кодирование прав в JSON
     *          - Вставку записи в TBL_GROUP
     *          Возвращает ID новой группы.
     *
     * @callgraph
     *
     * @param array $group_data Данные новой группы:
     *                          - `name_group`: Имя группы (валидируется через REG_NAME).
     *                          - Все права из `$this->user_right_fields['all']` (true/false или строковые значения).
     *                          Пример: ['name_group' => 'Moderators', 'read' => 'on', 'write' => false]
     *
     * @return int Возвращает ID новой группы при успешном добавлении.
     *
     * @throws RuntimeException Выбрасывается, если имя группы не прошло валидацию.
     * @throws JsonException    Выбрасывается, если не удалось кодировать права в JSON.
     * @throws Exception        Выбрасывается при ошибках SQL-запроса.
     *
     * @note    Метод использует защищённый `_add_new_group_internal()` для выполнения основной логики.
     *
     * @warning Убедитесь, что все поля прав доступа переданы в `$group_data`. Отсутствие полей может привести к ошибкам.
     *
     * Пример использования:
     * @code
     * // Через объект класса User
     * $groupId = $user->add_new_group($_POST);
     * echo "Группа добавлена с ID: " . $groupId;
     * @endcode
     * @see    PhotoRigma::Classes::User::_add_new_group_internal()
     *         Защищённый метод, реализующий добавление группы и валидацию данных.
     */
    public function add_new_group(array $group_data): int
    {
        return $this->_add_new_group_internal($group_data);
    }

    /**
     * @brief   Добавляет новую группу в систему через Админку.
     *
     * @details Метод:
     *          1. Принимает данные новой группы из `$group_data`:
     *             - name_group — имя группы (валидация через REG_NAME)
     *             - права доступа — булевы значения, фильтруются через FILTER_VALIDATE_BOOLEAN
     *          2. Очищает и проверяет имя группы:
     *             - Проверка на наличие
     *             - Проверка на пустое значение
     *             - Валидация через регулярное выражение REG_NAME
     *          3. Преобразует все поля прав доступа к булевому типу
     *          4. Кодирует права в JSON-строку через _encode_user_rights_internal()
     *          5. Выполняет вставку новой записи в TBL_GROUP
     *          6. Возвращает ID вставленной группы
     *          Метод является защищённым и предназначен для вызова внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод add_new_group().
     *
     * @callergraph
     * @callgraph
     *
     * @param array $group_data Данные новой группы:
     *                          - `name_group`: Имя группы (должно быть передано).
     *                          - Все ключи из `$this->user_right_fields['all']`.
     *                          Пример: ['name_group' => 'Moderators', 'read' => true, 'write' => false]
     *
     * @return int Возвращает ID новой группы при успешном добавлении.
     *
     * @throws RuntimeException Выбрасывается, если имя группы не прошло валидацию.
     * @throws JsonException    Выбрасывается, если не удалось кодировать права в JSON.
     * @throws Exception        Выбрасывается при ошибках SQL-запроса.
     *
     * @note    Для работы метода необходимо:
     *          - Поле `name_group` должно быть задано и пройти валидацию
     *          - Все поля прав должны быть определены в массиве `$this->user_right_fields`
     *          - Реализация зависит от констант: TBL_GROUP, REG_NAME
     *          Права доступа кодируются в JSON-строку для хранения в базе данных
     *          с последующим декодированием при работе с группами.
     *
     * @warning Убедитесь, что входные данные корректны. Невалидные данные могут привести к исключениям.
     *
     * Пример использования:
     * @code
     * // Внутри класса User
     * $newGroupId = $this->_add_new_group_internal($_POST);
     * echo "Группа добавлена с ID: " . $newGroupId;
     * @endcode
     * @see    PhotoRigma::Classes::User::add_new_group()
     *         Публичный метод-редирект для вызова этой логики.
     * @see    PhotoRigma::Classes::User::_encode_user_rights_internal()
     *         Для преобразования прав в JSON-строку.
     */
    protected function _add_new_group_internal(array $group_data): int
    {
        $query_data = [];
        $params_data = [];

        // Проверяем и добавляем имя группы
        if ($this->work->check_input('_POST', 'name_group', ['isset' => true, 'empty' => true, 'regexp' => REG_NAME])) {
            $clean_name = Work::clean_field($group_data['name_group']);
            $query_data['`name`'] = ':name';
            $params_data['name'] = $clean_name;
        } else {
            return 0;
        }

        $new_group_rights = [];

        foreach ($this->user_right_fields['all'] as $value) {
            // Используем filter_var для корректной конвертации всех значений в bool
            $new_group_rights[$value] = filter_var(
                $group_data[$value] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );
        }

        // Кодируем права доступа
        $encoded_group_rights = $this->_encode_user_rights_internal($new_group_rights);

        $query_data['`user_rights`'] = ':user_rights';
        $params_data['user_rights'] = $encoded_group_rights;

        // Выполняем вставку
        $this->db->insert($query_data, TBL_GROUP, '', ['params' => $params_data]);

        return $this->db->get_last_insert_id();
    }

    /**
     * @brief   Добавляет нового пользователя в базу данных через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _add_new_user_internal().
     *          Он выполняет проверку входных данных, проверяет уникальность логина, email и имени пользователя,
     *          добавляет нового пользователя в базу данных и назначает ему группу по умолчанию. В случае ошибок
     *          сохраняет их в сессии и завершает выполнение. Предназначен для прямого использования извне.
     *
     * @callgraph
     *
     * @param array $post_data Массив данных из формы ($_POST), содержащий новые значения для полей пользователя:
     *                         - string $login: Логин пользователя (должен соответствовать регулярному выражению
     *                           REG_LOGIN).
     *                         - string $password: Пароль пользователя (не должен быть пустым).
     *                         - string $re_password: Повтор пароля (должен совпадать с $password).
     *                         - string $email: Email пользователя (должен соответствовать регулярному выражению
     *                           REG_EMAIL).
     *                         - string $real_name: Реальное имя пользователя (должно соответствовать регулярному
     *                           выражению REG_NAME).
     *                         - string $captcha: Значение CAPTCHA (должно быть числом).
     *                         Все поля обязательны для заполнения.
     *
     * @return int ID нового пользователя, если регистрация успешна, или `0` в случае ошибки.
     *
     * @throws RuntimeException Выбрасывается, если группа по умолчанию не найдена в базе данных.
     *                          Пример сообщения:
     *                              Не удалось получить данные группы по умолчанию
     * @throws PDOException     Выбрасывается, если возникает ошибка при работе с базой данных.
     *                          Пример сообщения:
     *                              Ошибка при добавлении нового пользователя в базу данных
     * @throws Exception        Выбрасывается, если возникает ошибка при проверке входных данных.
     *                          Пример сообщения:
     *                              Ошибка валидации входных данных | Параметр: [имя_поля]
     *
     * @note    Используется CAPTCHA для защиты от автоматической регистрации.
     *          Константы, используемые в методе:
     *          - `REG_LOGIN`: Регулярное выражение для проверки логина.
     *            Пример: `/^[a-zA-Z0-9][a-zA-Z0-9_-]*[a-zA-Z0-9_]$/`.
     *          - `REG_EMAIL`: Регулярное выражение для проверки email.
     *            Пример: `/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/`.
     *          - `REG_NAME`: Регулярное выражение для проверки реального имени.
     *            Пример: `/^[^\x00-\x1F\x7F<>&"'\\\/`=]{1,100}$/`.
     *          - `DEFAULT_GROUP`: Идентификатор группы по умолчанию. Например: `1`.
     *          - `TBL_USERS`: Таблица пользователей в базе данных. Например: '`users`'.
     *          - `TBL_GROUP`: Таблица групп в базе данных. Например: '`groups`'.
     *          Эти константы позволяют гибко настраивать поведение метода.
     *
     * @warning Метод зависит от корректной конфигурации базы данных и таблицы групп. Убедитесь, что все необходимые
     *          константы и таблицы настроены правильно. Невалидные данные могут привести к ошибкам.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $user = new \PhotoRigma\Classes\User();
     * $userId = $user->add_new_user($_POST);
     * if ($userId > 0) {
     *     echo "Пользователь успешно зарегистрирован! ID: {$userId}";
     * } else {
     *     echo "Ошибка регистрации.";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::User::_add_new_user_internal()
     *         Защищённый метод, реализующий основную логику добавления нового пользователя.
     */
    public function add_new_user(array $post_data): int
    {
        return $this->_add_new_user_internal($post_data);
    }

    /**
     * @brief   Добавляет нового пользователя в базу данных, выполняя валидацию входных данных.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет корректность входных данных с использованием `Work::check_input()`:
     *             - Логин проверяется на соответствие регулярному выражению `REG_LOGIN`.
     *             - Пароль проверяется на пустоту и совпадение с повторным паролем.
     *             - Email проверяется на соответствие регулярному выражению `REG_EMAIL`.
     *             - Реальное имя проверяется на соответствие регулярному выражению `REG_NAME`.
     *             - CAPTCHA проверяется на числовое значение и совпадение с хешем из сессии.
     *             Если данные некорректны, ошибки сохраняются в сессии.
     *          2. Проверяет уникальность логина, email и реального имени в таблице `TBL_USERS`:
     *             - Если одно из значений уже существует, ошибка сохраняется в сессии.
     *          3. Если возникли ошибки, метод завершает выполнение и возвращает `0`.
     *          4. При успешной валидации добавляет нового пользователя в таблицу `TBL_USERS`, назначая ему группу по
     *             умолчанию (`DEFAULT_GROUP`).
     *             - Пароль хэшируется с использованием `password_hash()`.
     *             - Данные группы по умолчанию загружаются из таблицы `TBL_GROUP`.
     *          5. Возвращает ID нового пользователя или `0` в случае ошибки.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `add_new_user()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param array $post_data Массив данных из формы ($_POST), содержащий новые значения для полей пользователя:
     *                         - string $login: Логин пользователя (должен соответствовать регулярному выражению
     *                           REG_LOGIN).
     *                         - string $password: Пароль пользователя (не должен быть пустым).
     *                         - string $re_password: Повтор пароля (должен совпадать с $password).
     *                         - string $email: Email пользователя (должен соответствовать регулярному выражению
     *                           REG_EMAIL).
     *                         - string $real_name: Реальное имя пользователя (должно соответствовать регулярному
     *                           выражению REG_NAME).
     *                         - string $captcha: Значение CAPTCHA (должно быть числом).
     *                         Все поля обязательны для заполнения.
     *
     * @return int ID нового пользователя, если регистрация успешна, или `0` в случае ошибки.
     *
     * @throws RuntimeException Выбрасывается, если группа по умолчанию не найдена в базе данных.
     *                          Пример сообщения:
     *                              Не удалось получить данные группы по умолчанию
     * @throws PDOException     Выбрасывается, если возникает ошибка при работе с базой данных.
     *                          Пример сообщения:
     *                              Ошибка при добавлении нового пользователя в базу данных
     * @throws Exception        Выбрасывается, если возникает ошибка при проверке входных данных через
     *                          `Work::check_input()`. Пример сообщения: Ошибка валидации входных данных | Параметр:
     *                          [имя_поля]
     *
     * @note    Используется CAPTCHA для защиты от автоматической регистрации.
     *          Константы, используемые в методе:
     *          - `REG_LOGIN`: Регулярное выражение для проверки логина.
     *            Пример: `/^[a-zA-Z0-9][a-zA-Z0-9_-]*[a-zA-Z0-9_]$/`.
     *          - `REG_EMAIL`: Регулярное выражение для проверки email.
     *            Пример: `/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/`.
     *          - `REG_NAME`: Регулярное выражение для проверки реального имени.
     *            Пример: `/^[^\x00-\x1F\x7F<>&"'\\\/`=]{1,100}$/`.
     *          - `DEFAULT_GROUP`: Идентификатор группы по умолчанию. Например: `1`.
     *          - `TBL_USERS`: Таблица пользователей в базе данных. Например: '`users`'.
     *          - `TBL_GROUP`: Таблица групп в базе данных. Например: '`groups`'.
     *          Эти константы позволяют гибко настраивать поведение метода.
     *
     * @warning Метод зависит от корректной конфигурации базы данных и таблицы групп. Убедитесь, что все необходимые
     *          константы и таблицы настроены правильно. Невалидные данные могут привести к ошибкам.
     *
     * Пример использования метода _add_new_user_internal():
     * @code
     * // Внутри класса User
     * $userId = $this->_add_new_user_internal($_POST);
     * if ($userId > 0) {
     *     echo "Пользователь успешно зарегистрирован! ID: {$userId}";
     * } else {
     *     echo "Ошибка регистрации.";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Work::check_input()
     *         Метод для проверки правильности входных данных.
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция логирования ошибок.
     * @see    PhotoRigma::Classes::User::$session
     *         Свойство класса User, связанное с $_SESSION.
     * @see    PhotoRigma::Classes::$db
     *         Объект для работы с базой данных.
     * @see    PhotoRigma::Classes::User::add_new_user()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _add_new_user_internal(array $post_data): int
    {
        // === 1. Валидация входных данных ===
        $error = false;
        $field_validators = [
            'login'       => ['isset' => true, 'empty' => true, 'regexp' => REG_LOGIN],
            'password'    => ['isset' => true, 'empty' => true],
            're_password' => ['isset' => true, 'empty' => true],
            'email'       => ['isset' => true, 'empty' => true, 'regexp' => REG_EMAIL],
            'real_name'   => ['isset' => true, 'empty' => true, 'regexp' => REG_NAME],
            'captcha'     => ['isset' => true, 'empty' => true, 'regexp' => '/^[0-9]+$/'],
        ];

        foreach ($field_validators as $field => $options) {
            if (!$this->work->check_input('_POST', $field, $options)) {
                $this->session['error'][$field]['if'] = true;
                $this->session['error'][$field]['text'] = match ($field) {
                    'login'       => $this->work->lang['profile']['error_login'],
                    'password'    => $this->work->lang['profile']['error_password'],
                    're_password' => $this->work->lang['profile']['error_re_password'],
                    'email'       => $this->work->lang['profile']['error_email'],
                    'real_name'   => $this->work->lang['profile']['error_real_name'],
                    'captcha'     => $this->work->lang['profile']['error_captcha'],
                    default       => 'Unknown error',
                };
                $error = true;
            } else {
                // Дополнительные проверки для re_password и captcha
                if ($field === 're_password' && $post_data['re_password'] !== $post_data['password']) {
                    $this->session['error']['re_password']['if'] = true;
                    $this->session['error']['re_password']['text'] = $this->work->lang['profile']['error_re_password'];
                    $error = true;
                }
                if ($field === 'captcha' && !password_verify($post_data['captcha'], $this->session['captcha'])) {
                    $this->session['error']['captcha']['if'] = true;
                    $this->session['error']['captcha']['text'] = $this->work->lang['profile']['error_captcha'];
                    $error = true;
                }
            }
        }
        $this->unset_property_key('session', 'captcha');

        // === 2. Проверка уникальности login, email и real_name ===
        $this->db->select(
            'COUNT(CASE WHEN `login` = :login THEN 1 END) as `login_count`,
             COUNT(CASE WHEN `email` = :email THEN 1 END) as `email_count`,
             COUNT(CASE WHEN `real_name` = :real_name THEN 1 END) as `real_count`',
            TBL_USERS,
            [
                'params' => [
                    ':login'     => $post_data['login'],
                    ':email'     => $post_data['email'],
                    ':real_name' => $post_data['real_name'],
                ],
            ]
        );
        $unique_check_result = $this->db->result_row();

        if ($unique_check_result) {
            if ($unique_check_result['login_count'] > 0) {
                $error = true;
                $this->session['error']['login']['if'] = true;
                $this->session['error']['login']['text'] = $this->work->lang['profile']['error_login_exists'];
            }
            if ($unique_check_result['email_count'] > 0) {
                $error = true;
                $this->session['error']['email']['if'] = true;
                $this->session['error']['email']['text'] = $this->work->lang['profile']['error_email_exists'];
            }
            if ($unique_check_result['real_count'] > 0) {
                $error = true;
                $this->session['error']['real_name']['if'] = true;
                $this->session['error']['real_name']['text'] = $this->work->lang['profile']['error_real_name_exists'];
            }
        }

        // === 3. Если возникли ошибки, сохраняем их в сессии и завершаем ===
        if ($error) {
            return 0; // Возвращаем 0, чтобы обозначить ошибку
        }

        // === 4. Добавление нового пользователя в базу данных ===
        $query = [
            'login'     => $post_data['login'],
            'password'  => password_hash($post_data['re_password'], PASSWORD_BCRYPT),
            'email'     => $post_data['email'],
            'real_name' => $post_data['real_name'],
            'group_id'  => DEFAULT_GROUP,
        ];

        // Получение данных группы по умолчанию
        $this->db->select('*', TBL_GROUP, [
            'where'  => '`id` = :group_id',
            'params' => [':group_id' => DEFAULT_GROUP],
        ]);
        $group_data = $this->db->result_row();

        if (!$group_data) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Не удалось получить данные группы по умолчанию'
            );
        }

        // Добавляем данные группы в массив для вставки нового пользователя
        foreach ($group_data as $key => $value) {
            if ($key !== 'id' && $key !== 'name') {
                $query[$key] = $value;
            }
        }

        // Формируем плоский массив плейсхолдеров и ассоциативный массив для вставки
        $insert_data = array_map(static fn ($key) => "`$key`", array_keys($query)); // Экранируем имена столбцов
        $placeholders = array_map(static fn ($key) => ":$key", array_keys($query)); // Формируем плейсхолдеры
        $params = array_combine(
            array_map(static fn ($key) => ":$key", array_keys($query)), // Добавляем префикс ':' к каждому ключу
            $query // Значения остаются без изменений
        );

        // Вставка нового пользователя в базу данных
        $this->db->insert(
            array_combine($insert_data, $placeholders),
            // Передаём ассоциативный массив (имена столбцов => плейсхолдеры)
            TBL_USERS,
            '',
            ['params' => $params] // Передаём преобразованный массив параметров
        );

        // === 5. Возвращаем результат ===
        return $this->db->get_last_insert_id(); // ID нового пользователя
    }

    /**
     * @brief   Удаляет ключ из указанного свойства объекта (user или session) через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _unset_property_key_internal().
     *          Он проверяет корректность входных данных, определяет целевой массив (`$this->user` или
     *          `$this->session`) и удаляет указанный ключ, если он существует. Предназначен для прямого использования
     *          извне.
     *
     * @callgraph
     *
     * @param string $name Имя свойства, из которого удаляется ключ:
     *                     - Допустимые значения: 'user' (для массива данных пользователя) или 'session' (для массива
     *                       данных сессии).
     *                     - Пример: "user".
     *                     Ограничения: только допустимые значения ('user' или 'session').
     * @param string $key  Ключ, который нужно удалить:
     *                     - Должен быть строкой.
     *                     - Пример: "email".
     *                     Ограничения: если ключ отсутствует в массиве, метод завершается без ошибок.
     *
     * @throws InvalidArgumentException Выбрасывается, если указано недопустимое значение для параметра `$name`.
     *                                  Пример сообщения:
     *                                      Недопустимое свойство | Свойство: [значение]
     *
     * @note    Метод изменяет только свойства 'user' и 'session'.
     *          Если ключ отсутствует в массиве, метод завершается без ошибок.
     *
     * @warning Не используйте этот метод для удаления ключей из других свойств.
     *          Передавайте только допустимые значения для параметра `$name`.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $user = new \PhotoRigma\Classes\User();
     *
     * // Удаление ключа из массива $user
     * $user->unset_property_key('user', 'email');
     *
     * // Удаление ключа из массива $session
     * $user->unset_property_key('session', 'logged_in');
     * @endcode
     * @see    PhotoRigma::Classes::User::_unset_property_key_internal()
     *         Защищённый метод, реализующий основную логику удаления ключа из указанного свойства.
     */
    public function unset_property_key(string $name, string $key): void
    {
        $this->_unset_property_key_internal($name, $key);
    }

    /**
     * @brief   Удаляет ключ из указанного свойства объекта (user или session).
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет, какой массив изменять (`$this->user` или `$this->session`), на основе параметра `$name`.
     *          2. Если ключ существует в выбранном массиве, он удаляется с помощью `unset()`.
     *          3. Если указанный массив недопустим (не 'user' и не 'session'), выбрасывается исключение.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `unset_property_key()`.
     *
     * @callergraph
     *
     * @param string $name Имя свойства, из которого удаляется ключ.
     *                     Допустимые значения: 'user' (для массива данных пользователя) или 'session'
     *                     (для массива данных сессии).
     *                     Пример: "user".
     *                     Ограничения: только допустимые значения ('user' или 'session').
     * @param string $key  Ключ, который нужно удалить. Должен быть строкой.
     *                     Пример: "email".
     *                     Ограничения: если ключ отсутствует в массиве, метод завершается без ошибок.
     *
     * @throws InvalidArgumentException Выбрасывается, если указано недопустимое значение для параметра `$name`.
     *                                  Пример сообщения:
     *                                      Недопустимое свойство | Свойство: [значение]
     *
     * @note    Метод изменяет только свойства 'user' и 'session'.
     *          Если ключ отсутствует в массиве, метод завершается без ошибок.
     *
     * @warning Не используйте этот метод для удаления ключей из других свойств.
     *          Передавайте только допустимые значения для параметра `$name`.
     *
     * Пример использования метода _unset_property_key_internal():
     * @code
     * // Удаление ключа из массива $user
     * $this->_unset_property_key_internal('user', 'email');
     *
     * // Удаление ключа из массива $session
     * $this->_unset_property_key_internal('session', 'logged_in');
     * @endcode
     * @see    PhotoRigma::Classes::User::$user
     *         Свойство класса User, содержащее данные пользователя.
     * @see    PhotoRigma::Classes::User::$session
     *         Свойство класса User, содержащее данные сессии.
     * @see    PhotoRigma::Classes::User::unset_property_key()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _unset_property_key_internal(string $name, string $key): void
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
                throw new InvalidArgumentException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' . "Недопустимое свойство | Свойство: $name"
                );
        }
    }

    /**
     * @brief   Генерирует или возвращает CSRF-токен через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _csrf_token_internal().
     *          Он проверяет наличие CSRF-токена в сессии. Если токен отсутствует, он генерируется с использованием
     *          `random_bytes(32)` (64 символа) и сохраняется в сессии. Предназначен для прямого использования извне.
     *
     * @callgraph
     *
     * @return string CSRF-токен длиной 64 символа (генерируется с использованием `random_bytes(32)`).
     *
     * @throws Random\RandomException Выбрасывается, если возникает ошибка при генерации случайных байтов.
     *                                  Пример сообщения:
     *                                      Ошибка при генерации CSRF-токена | random_bytes()
     *
     * @note    Токен хранится в сессии и используется для защиты форм от CSRF-атак.
     *
     * @warning Не используйте этот метод для генерации токенов, если сессия недоступна или не инициализирована.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $user = new \PhotoRigma\Classes\User();
     * $csrfToken = $user->csrf_token();
     * echo "CSRF Token: {$csrfToken}";
     * @endcode
     * @see    PhotoRigma::Classes::User::_csrf_token_internal()
     *         Защищённый метод, реализующий основную логику генерации или получения CSRF-токена.
     */
    public function csrf_token(): string
    {
        return $this->_csrf_token_internal();
    }

    /**
     * @brief   Генерирует или возвращает CSRF-токен для защиты от межсайтовой подделки запросов.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет, существует ли CSRF-токен в сессии (`$session['csrf_token']`).
     *          2. Если токен отсутствует, генерирует новый токен длиной 64 символа с использованием `random_bytes(32)`.
     *          3. Сохраняет сгенерированный токен в сессии.
     *          4. Возвращает текущий CSRF-токен (либо существующий, либо вновь сгенерированный).
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `csrf_token()`.
     *
     * @callergraph
     *
     * @return string CSRF-токен длиной 64 символа (генерируется с использованием `random_bytes(32)`).
     *
     * @throws Random\RandomException Выбрасывается, если возникает ошибка при генерации случайных байтов.
     *                                  Пример сообщения:
     *                                      Ошибка при генерации CSRF-токена | random_bytes()
     *
     * @note    Токен хранится в сессии и используется для защиты форм от CSRF-атак.
     *
     * @warning Не используйте этот метод для генерации токенов, если сессия недоступна или не инициализирована.
     *
     * Пример использования метода _csrf_token_internal():
     * @code
     * // Генерация или получение CSRF-токена
     * $token = $this->_csrf_token_internal();
     * echo "CSRF Token: {$token}";
     * @endcode
     * @see    PhotoRigma::Classes::User::$session
     *         Свойство класса User, содержащее данные сессии.
     * @see    PhotoRigma::Classes::User::csrf_token()
     *         Метод, предоставляющий публичный доступ к методу _csrf_token_internal().
     */
    protected function _csrf_token_internal(): string
    {
        if (empty($this->session['csrf_token'])) {
            $this->session['csrf_token'] = bin2hex(random_bytes(32)); // 64 символа
        }
        return $this->session['csrf_token'];
    }

    /**
     * @brief   Удаляет группу из системы через Админку.
     *
     * @details Метод является обёрткой для защищённого метода `_delete_group_internal()`.
     *          Он предназначен для вызова из клиентского кода (например, интерфейса Админки).
     *          Передаёт управление на внутренний метод с проверками и логикой удаления.
     *          Не требует дополнительных параметров, кроме ID группы.
     *
     * @callgraph
     *
     * @param int $group_id ID группы, которую нужно удалить.
     *                      Должен быть положительным целым числом (`> 0`).
     *                      Пример: 123.
     *
     * @return bool `TRUE`, если группа успешно удалена.
     *              `FALSE`, если:
     *              - Группа защищена от удаления
     *              - Ни одна строка не была изменена при обновлении/удалении
     *
     * @throws Exception При ошибках выполнения SQL-запросов или записи в log_in_file().
     *
     * @note    Реализация находится в `_delete_group_internal()`.
     *          Для защиты от удаления используются константы `PROTECTED_GROUPS`.
     *
     * @warning Убедитесь, что вы действительно хотите удалить группу.
     *          Удаление затрагивает пользователей, состоящих в группе.
     *
     * Пример использования:
     * @code
     * // Из админки
     * if ($user->delete_group(123)) {
     *     echo "Группа удалена";
     * } else {
     *     echo "Ошибка удаления или группа защищена";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::User::_delete_group_internal()
     *         Защищённый метод, реализующий основную логику удаления группы.
     */
    public function delete_group(int $group_id): bool
    {
        return $this->_delete_group_internal($group_id);
    }

    /**
     * @brief   Удаляет группу из базы данных.
     *
     * @details Метод:
     *          1. Проверяет, не находится ли $group_id в PROTECTED_GROUPS → если да → возвращает FALSE.
     *          2. Переводит всех пользователей этой группы на DEFAULT_GROUP.
     *          3. Удаляет запись группы из TBL_GROUP.
     *          4. Возвращает TRUE, если были затронуты строки (то есть группа удалена), иначе FALSE.
     *          Метод является защищённым и предназначен для вызова внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод delete_group().
     *
     * @callergraph
     *
     * @param int $group_id ID группы, которую нужно удалить.
     *                      Должен быть положительным целым числом (`> 0`).
     *                      Пример: 123.
     *
     * @return bool `TRUE`, если группа успешно удалена (затронуты строки).
     *              `FALSE`, если:
     *              - Группа защищена от удаления
     *              - Ни одна строка не была изменена при обновлении/удалении
     *
     * @throws Exception При ошибках выполнения SQL-запросов или записи в log_in_file()
     *
     * @note    Реализация использует следующие константы:
     *          - `TBL_USERS`: Таблица пользователей
     *          - `TBL_GROUP`: Таблица групп
     *          - `PROTECTED_GROUPS`: Массив защищённых ID групп
     *          - `DEFAULT_GROUP`: ID группы по умолчанию
     *
     * @warning Убедитесь, что таблицы базы данных содержат корректные данные.
     *          Удаление может повлиять на большое количество пользователей.
     *
     * Пример использования:
     * @code
     * // Из метода delete_group()
     * if ($this->_delete_group_internal(123)) {
     *     echo "Группа удалена";
     * } else {
     *     echo "Ошибка удаления или группа защищена";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::User::delete_group()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _delete_group_internal(int $group_id): bool
    {
        // 1. Проверяем, защищена ли группа от удаления
        if (in_array($group_id, PROTECTED_GROUPS, true)) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                "Невозможно удалить защищенную группу | ID: $group_id"
            );
            return false;
        }

        // 2. Переводим всех пользователей группы на DEFAULT_GROUP
        $this->db->update(
            ['`group_id`' => ':new_group_id'],
            TBL_USERS,
            [
                'where' => '`group_id` = :old_group_id',
                'params' => [
                    ':new_group_id' => DEFAULT_GROUP,
                    ':old_group_id' => $group_id
                ]
            ]
        );

        // 3. Удаляем саму группу
        $this->db->delete(TBL_GROUP, [
            'where' => '`id` = :group_id',
            'params' => [':group_id' => $group_id]
        ]);

        // 4. Возвращаем результат
        return (bool)$this->db->get_affected_rows();
    }

    /**
     * @brief   Публичный метод-редирект для выполнения мягкого или окончательного удаления пользователя.
     *
     * @details Предназначен для прямого вызова из клиентского кода. Передаёт управление
     *          защищённому методу `_delete_user_internal()`, который реализует основную логику:
     *          - Проверяет, не пытается ли администратор удалить сам себя
     *          - При окончательном удалении:
     *            a) Инициатором считается текущий пользователь
     *            b) Проверяется, что это администратор (`$this->user['admin']`)
     *            c) Сессия админа должна быть подтверждена (`$this->session['admin_on']`)
     *            d) Запрещено удалять последнего админа
     *          Поддерживает мягкое удаление (пометка `deleted_at`) и окончательное удаление (через cron или админку).
     *
     * @callgraph
     *
     * @param int      $user_id   ID пользователя, которого нужно удалить.
     *                            Должен быть положительным целым числом (`> 0`).
     *                            Пример: 123.
     * @param bool     $permanent [optional] Указывает, какое удаление выполнить:
     *                            - `FALSE` (по умолчанию): мягкое удаление (помечаем `deleted_at`)
     *                            - `TRUE`: окончательное удаление (удаляем запись из БД)
     *
     * @return bool `TRUE`, если удаление прошло успешно, `FALSE` в случае ошибки:
     *              - Пользователь не найден
     *              - Ограничение безопасности (самоудаление админа, последний админ)
     *              - Не удалось выполнить SQL-запрос
     *
     * @throws Exception Выбрасывается, если произошла ошибка на уровне СУБД (например, ошибка запроса).
     *                   Это позволяет внешнему коду обрабатывать такие ошибки отдельно.
     *
     * @note    Подробнее о работе удаления см. в защищённом методе `_delete_user_internal()`.
     *
     * Пример использования:
     * @code
     * // Мягкое удаление
     * $success = $user->delete_user(123);
     *
     * // Окончательное удаление из админки
     * $success = $user->delete_user(123, true);
     * @endcode
     * @see    PhotoRigma::Classes::User::_delete_user_internal()
     *         Защищённый метод, реализующий основную логику удаления пользователя.
     */
    public function delete_user(int $user_id, bool $permanent = false): bool
    {
        return $this->_delete_user_internal($user_id, $permanent);
    }

    /**
     * @brief   Выполняет мягкое или окончательное удаление пользователя с предварительными проверками.
     *
     * @details Метод реализует следующую логику:
     *          1. Получает данные пользователя по `$user_id`.
     *             - Если пользователь не найден — возвращает `FALSE`.
     *          2. Проверяет, не пытается ли пользователь удалить сам себя:
     *             - Если это администратор — запрещено даже мягкое удаление → возвращает `FALSE`.
     *          3. При попытке окончательного удаления (`$permanent = TRUE`):
     *             a) Инициатором считается текущий пользователь
     *             b) Проверяется, что инициатор — администратор (`$this->user['admin']`)
     *             c) Сессия админа должна быть подтверждена (`$this->session['admin_on']`)
     *             d) Самоудаление админом запрещено
     *             e) Если удаляется администратор, дополнительно проверяется наличие других активных админов
     *                - Если он последний — запрещено → возвращаем `FALSE`
     *          4. В зависимости от флага `$permanent`, вызывает:
     *             - `_soft_delete($user_id)` — для пометки аккаунта как удалённого
     *             - `_hard_delete_user_internal($user_id, true)` — для окончательного удаления
     *          Все действия логируются через функцию `log_in_file()`.
     *
     *          Этот метод является защищённым и предназначен для использования внутри класса
     *          или его наследников. Он служит центральной точкой входа для всех типов удаления.
     *
     * @callergraph
     * @callgraph
     *
     * @param int      $user_id   ID пользователя, которого нужно удалить.
     *                             Должен быть положительным целым числом (`> 0`).
     *                             Пример: 123.
     * @param bool     $permanent [optional] Указывает, какое удаление выполнить:
     *                             - `FALSE` (по умолчанию): мягкое удаление (помечаем `deleted_at`)
     *                             - `TRUE`: окончательное удаление (через метод _hard_delete_user_internal())
     *
     * @return bool `TRUE`, если удаление прошло успешно, `FALSE` в случае ошибки:
     *              - Пользователь не найден
     *              - Нарушены ограничения безопасности (самоудаление админа, последний админ)
     *              - Не удалось выполнить SQL-запрос
     *
     * @throws Exception Выбрасывается, если произошла ошибка на уровне СУБД (например, ошибка запроса).
     *                   Это позволяет внешнему коду обрабатывать такие ошибки отдельно.
     *
     * @note    Для работы метода необходимо:
     *          - Поле `group_id` должно содержать корректную группу пользователя
     *          - Константа GROUP_ADMIN должна быть определена
     *          - При окончательном удалении должна быть установлена сессия админа (`$this->session['admin_on']`)
     * @note    Мягкое удаление доступно всем, кроме администраторов (или их попытки самоудаления)
     * @note    Окончательное удаление доступно только админам, и только если они не являются последним активным админом
     *
     * @warning Метод не выбрасывает исключений при бизнес-ошибках (например, "недостаточно прав").
     *          Вместо этого он возвращает `FALSE` и пишет событие в лог.
     *
     * Пример использования метода _delete_user_internal():
     * @code
     * // Мягкое удаление
     * $success = $this->_delete_user_internal(123);
     *
     * // Окончательное удаление из админки
     * $success = $this->_delete_user_internal(123, true);
     * @endcode
     * @see    PhotoRigma::Classes::User::delete_user()
     *         Публичный метод-редирект для вызова этой логики.
     * @see    PhotoRigma::Classes::User::_soft_delete()
     *         Метод для выполнения мягкого удаления.
     * @see    PhotoRigma::Classes::User::_hard_delete_user_internal()
     *         Метод для выполнения окончательного удаления.
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для записи логов.
     */
    protected function _delete_user_internal(int $user_id, bool $permanent = false): bool
    {
        // 1. Получаем данные пользователя
        $this->db->select(
            ['`id`', '`group_id`', '`deleted_at`', '`permanently_deleted`'],
            TBL_USERS,
            ['where' => '`id` = :id', 'params' => [':id' => $user_id]]
        );
        $user_data = $this->db->result_row();

        if (!$user_data) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Пользователь с ID $user_id не найден"
            );
            return false;
        }

        // 2. Проверяем, не удаляем ли мы самого себя
        if ((int)$user_data['id'] === $this->session['login_id'] && (int)$user_data['group_id'] === GROUP_ADMIN) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Администратор не может удалить сам себя | ID: $user_id"
            );
            return false;
        }

        // 3. Проверяем права на окончательное удаление
        if ($permanent) {
            // Только админ с подтверждённой сессией может окончательно удалить
            if (
                !$this->user['admin'] ||
                !$this->session['admin_on']
            ) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Только админ с подтвержденной сессией может окончательно удалить | ID: $user_id"
                );
                return false;
            }

            // Проверяем, не последний ли это админ
            $this->db->select('COUNT(*) AS cnt', TBL_USERS, [
                'where' => '`group_id` = :group AND `id` != :exclude_id AND `permanently_deleted` = 0',
                'params' => [
                    ':group' => GROUP_ADMIN,
                    ':exclude_id' => $user_id,
                ],
            ]);
            $count_result = $this->db->result_row();
            $admin_count = (int)$count_result['cnt'];

            if ($admin_count <= 0) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Невозможно удалить последнего админа | ID: $user_id"
                );
                return false;
            }

            // Вызываем окончательное удаление с флагом force = true
            return $this->_hard_delete_user_internal($user_id, true);
        }

        // 4. Выполняем мягкое удаление
        return $this->_soft_delete($user_id);
    }

    /**
     * @brief   Обновляет данные группы в системе через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _update_group_data_internal().
     *          Он проверяет и обновляет название группы, если оно изменилось, обновляет права доступа группы и
     *          возвращает обновленные данные. Предназначен для прямого использования извне (например, через Админку).
     *
     * @callgraph
     *
     * @param array $group_data Данные группы:
     *                          - `name`: Текущее название группы.
     *                          - `user_rights`: JSON-строка с текущими правами группы.
     *                          Может также содержать поля, перечисленные в свойстве класса `user_right_fields`.
     * @param array $post_data  Данные, полученные из POST-запроса:
     *                          - `id_group`: ID группы.
     *                          Может также содержать ключ:
     *                          - `name_group`: Новое название группы.
     *                          И поля, перечисленные в свойстве класса `user_right_fields`.
     *
     * @return array Возвращает массив данных группы:
     *               - `name`: Название группы.
     *               - Все поля, указанные в свойстве класса `user_right_fields`.
     *
     * @throws RuntimeException Выбрасывается, если произошла ошибка при обновлении данных в БД.
     *                          Пример сообщения:
     *                              Ошибка при обновлении данных группы | ID: [id_group]
     * @throws JsonException    Выбрасывается, если возникает ошибка при кодировании или декодировании JSON.
     *                          Пример сообщения:
     *                              Ошибка при кодировании прав доступа в JSON
     * @throws Exception        Выбрасывается, если возникает ошибка при проверке входных данных.
     *                          Пример сообщения:
     *                              Ошибка валидации входных данных | Поле: [имя_поля]
     *
     * @note    Метод использует следующие константы:
     *          - `TBL_GROUP`: Имя таблицы групп в базе данных. Например: '`groups`'.
     *          - `REG_NAME`: Регулярное выражение для проверки названия группы.
     *            Пример: `/^[^\x00-\x1F\x7F<>&"'\\\/`=]{1,100}$/`.
     *          Эти константы позволяют гибко настраивать поведение метода.
     *
     * @warning Убедитесь, что входные данные корректны. Невалидные данные могут привести к исключениям.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $object = new \PhotoRigma\Classes\User();
     * $group_data = [
     *     'name'        => 'Moderators',
     *     'user_rights' => '{"edit": true, "delete": false}',
     * ];
     * $post_data = [
     *     'id_group'    => 2,
     *     'name_group'  => 'Editors',
     *     'edit'        => 'on',
     *     'delete'      => false,
     * ];
     * $result = $object->update_group_data($group_data, $post_data);
     * print_r($result);
     * @endcode
     * @see    PhotoRigma::Classes::User::_update_group_data_internal()
     *         Защищённый метод, реализующий основную логику обновления данных группы.
     */
    public function update_group_data(array $group_data, array $post_data): array
    {
        return $this->_update_group_data_internal($group_data, $post_data);
    }

    /**
     * @brief   Обновляет данные группы в системе через Админку.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет и обновляет название группы, если оно изменилось в `$post_data`:
     *             - Очищает новое название с помощью `Work::clean_field()` для безопасности.
     *             - Обновляет поле `name` в таблице групп через SQL-запрос.
     *          2. Обновляет права доступа группы:
     *             - Проверяет каждое поле прав доступа из свойства класса `user_right_fields`.
     *             - Нормализует значения прав доступа (true/false).
     *             - Кодирует обновленные права доступа в JSON-строку с помощью метода `encode_user_rights()`.
     *          3. Обновляет поле `user_rights` в таблице групп через SQL-запрос.
     *          4. Возвращает обновленные данные группы, включая название и все поля прав доступа.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `update_group_data()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param array $group_data Данные группы.
     *                          Должен содержать ключи:
     *                          - `name`: Текущее название группы.
     *                          - `user_rights`: JSON-строка с текущими правами группы.
     *                          Может также содержать поля, перечисленные в свойстве класса `user_right_fields`.
     * @param array $post_data  Данные, полученные из POST-запроса.
     *                          Должен содержать ключ:
     *                          - `id_group`: ID группы.
     *                          Может также содержать ключ:
     *                          - `name_group`: Новое название группы.
     *                          И поля, перечисленные в свойстве класса `user_right_fields`.
     *
     * @return array Возвращает массив данных группы:
     *               - `name`: Название группы.
     *               - Все поля, указанные в свойстве класса `user_right_fields`.
     *
     * @throws RuntimeException Выбрасывается, если произошла ошибка при обновлении данных в БД.
     *                          Пример сообщения:
     *                              Ошибка при обновлении данных группы | ID: [id_group]
     * @throws JsonException    Выбрасывается, если возникает ошибка при кодировании или декодировании JSON.
     *                          Пример сообщения:
     *                              Ошибка при кодировании прав доступа в JSON
     * @throws Exception        Выбрасывается, если возникает ошибка при проверке входных данных через
     *                          `Work::check_input()`. Пример сообщения: Ошибка валидации входных данных | Поле:
     *                          [имя_поля]
     *
     * @note    Метод использует следующие константы:
     *          - `TBL_GROUP`: Имя таблицы групп в базе данных. Например: '`groups`'.
     *          - `REG_NAME`: Регулярное выражение для проверки названия группы.
     *            Пример: `/^[^\x00-\x1F\x7F<>&"'\\\/`=]{1,100}$/`.
     *          Эти константы позволяют гибко настраивать поведение метода.
     *
     * @warning Убедитесь, что входные данные корректны. Невалидные данные могут привести к исключениям.
     *
     * Пример использования метода _update_group_data_internal():
     * @code
     * // Обновление данных группы
     * $group_data = [
     *     'name'        => 'Moderators',
     *     'user_rights' => '{"edit": true, "delete": false}',
     * ];
     * $post_data = [
     *     'id_group'    => 2,
     *     'name_group'  => 'Editors',
     *     'edit'        => 'on',
     *     'delete'      => false,
     * ];
     * $result = $this->_update_group_data_internal($group_data, $post_data);
     * print_r($result);
     * @endcode
     * @see    PhotoRigma::Classes::User::$user_right_fields
     *         Свойство, содержащее массив с допустимыми полями прав пользователя.
     * @see    PhotoRigma::Classes::User::_process_user_rights_internal()
     *         Метод, декодирующий JSON-строку прав пользователя в массив.
     * @see    PhotoRigma::Classes::User::_encode_user_rights_internal()
     *         Метод, кодирующий массив прав пользователя в JSON-строку для хранения в БД.
     * @see    PhotoRigma::Classes::User::update_group_data()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _update_group_data_internal(array $group_data, array $post_data): array
    {
        // Обновляем название группы
        /** @noinspection NotOptimalIfConditionsInspection */
        if ($this->work->check_input(
            '_POST',
            'name_group',
            ['isset' => true, 'empty' => true, 'regexp' => REG_NAME]
        ) && $post_data['name_group'] !== $group_data['name']) {
            $clean_name = Work::clean_field($post_data['name_group']); // Очищаем название группы для безопасности
            $this->db->update(
                ['name' => ':name'], // Обновляем поле name в таблице групп
                TBL_GROUP,
                [
                    'where'  => '`id` = :id_group', // Условие: группа с указанным ID
                    'params' => [
                        ':name'     => $clean_name, // Новое название группы
                        ':id_group' => $post_data['id_group'], // ID группы
                    ],
                ]
            );
            $rows = $this->db->get_affected_rows(); // Получаем количество измененных строк
            if ($rows > 0) {
                $group_data['name'] = $clean_name; // Обновляем название группы в данных
            }
        }

        $new_group_rights = [];

        // Обновляем права доступа группы
        foreach ($this->user_right_fields['all'] as $value) {
            if ($this->work->check_input('_POST', $value, ['isset' => true, 'empty' => true])) {
                // Если поле существует и имеет допустимое значение, нормализуем его (true/false)
                $post_data[$value] = in_array($post_data[$value], ['on', 1, '1', true], true);
            } else {
                // Если поле не передано, устанавливаем значение в false
                $post_data[$value] = false;
            }
            $new_group_rights[$value] = $post_data[$value]; // Сохраняем обновленное значение права
        }

        // Кодируем права доступа для сохранения в базе данных
        $encoded_group_rights = $this->_encode_user_rights_internal($new_group_rights);

        // Обновляем права доступа группы в базе данных
        $this->db->update(
            ['`user_rights`' => ':u_rights'], // Обновляем поле user_rights
            TBL_GROUP,
            [
                'where'  => '`id` = :id_group', // Условие: группа с указанным ID
                'params' => [
                    ':u_rights' => $encoded_group_rights, // Закодированные права доступа
                    ':id_group' => $post_data['id_group'], // ID группы
                ],
            ]
        );
        $rows = $this->db->get_affected_rows(); // Получаем количество измененных строк
        if ($rows > 0) {
            // Если данные успешно обновлены, обновляем права доступа в памяти
            $group_data = array_merge($group_data, $new_group_rights);
        } else {
            // Если данные не изменились, обрабатываем права доступа через process_user_rights
            $group_data = array_merge($group_data, $this->_process_user_rights_internal($group_data['user_rights']));
        }

        // Удаляем ключ user_rights из результирующего массива, если он существует
        if (isset($group_data['user_rights'])) {
            unset($group_data['user_rights']);
        }

        return $group_data; // Возвращаем обновленные данные группы
    }

    /**
     * @brief   Преобразует массив прав пользователя в строку JSON через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _encode_user_rights_internal().
     *          Он проверяет корректность входных данных, преобразует массив прав в строку JSON и возвращает результат.
     *          Предназначен для прямого использования извне.
     *
     * @callgraph
     *
     * @param array $rights Массив прав пользователя:
     *                      - Может быть ассоциативным массивом, пустым значением или `null`.
     *                      - Пример: ['edit' => true, 'delete' => false].
     *                      Ограничения: значение должно быть массивом.
     *
     * @return string Строка JSON, представляющая права пользователя. Если массив отсутствует или содержит некорректные
     *                данные, возвращается пустая строка.
     *
     * @throws InvalidArgumentException Выбрасывается, если входное значение не является массивом.
     *                                  Пример сообщения:
     *                                      Некорректный тип данных | Ожидался массив
     * @throws JsonException            Выбрасывается, если возникает ошибка при кодировании массива в JSON.
     *                                  Пример сообщения:
     *                                      Ошибка кодирования JSON | [описание ошибки]
     *
     * @note    Метод возвращает пустую строку, если массив прав отсутствует или содержит некорректные данные.
     *          Если кодирование JSON завершается успешно, возвращается строка JSON.
     *
     * @warning Входные данные должны быть корректным массивом. Некорректные данные могут привести к выбросу
     *          исключения.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $object = new \PhotoRigma\Classes\User();
     *
     * // Преобразование массива прав в JSON
     * $json = $object->encode_user_rights(['edit' => true, 'delete' => false]);
     * echo "JSON-строка: $json";
     *
     * // Обработка пустого массива
     * $json = $object->encode_user_rights([]);
     * echo "JSON-строка: $json"; // Выведет: {}
     * @endcode
     * @see    PhotoRigma::Classes::User::_encode_user_rights_internal()
     *         Защищённый метод, реализующий основную логику преобразования массива прав в строку JSON.
     */
    public function encode_user_rights(array $rights): string
    {
        return $this->_encode_user_rights_internal($rights);
    }

    /**
     * @brief   Метод преобразует массив прав пользователя в строку JSON.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет, существует ли входной массив. Если массив отсутствует или пуст, возвращается пустая
     *             строка.
     *          2. Проверяет, что входное значение является массивом. Если тип данных некорректен, выбрасывается
     *             исключение.
     *          3. Кодирует массив в строку JSON с использованием `json_encode()` и флагов:
     *             - `JSON_THROW_ON_ERROR`: Генерирует исключение при ошибках кодирования.
     *             - `JSON_UNESCAPED_UNICODE`: Сохраняет Unicode-символы без экранирования.
     *             - `JSON_UNESCAPED_SLASHES`: Не экранирует слеши (`/`).
     *          4. Возвращает строку JSON, готовую для сохранения в поле `user_rights`.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `encode_user_rights()`.
     *
     * @callergraph
     *
     * @param array $rights Массив прав пользователя. Может быть ассоциативным массивом, пустым значением или `null`.
     *                      Пример: ['edit' => true, 'delete' => false].
     *                      Ограничения: значение должно быть массивом.
     *
     * @return string Строка JSON, представляющая права пользователя. Если массив отсутствует или содержит некорректные
     *                данные, возвращается пустая строка.
     *
     * @throws InvalidArgumentException Выбрасывается, если входное значение не является массивом.
     *                                  Пример сообщения:
     *                                      Некорректный тип данных | Ожидался массив
     * @throws JsonException            Выбрасывается, если возникает ошибка при кодировании массива в JSON.
     *                                  Пример сообщения:
     *                                      Ошибка кодирования JSON | [описание ошибки]
     *
     * @note    Метод возвращает пустую строку, если массив прав отсутствует или содержит некорректные данные.
     *          Если кодирование JSON завершается успешно, возвращается строка JSON.
     *
     * @warning Входные данные должны быть корректным массивом. Некорректные данные могут привести к выбросу
     *          исключения.
     *
     * Пример использования метода _encode_user_rights_internal():
     * @code
     * // Преобразование массива прав в JSON
     * $json = $this->_encode_user_rights_internal(['edit' => true, 'delete' => false]);
     * echo "JSON-строка: $json";
     *
     * // Обработка пустого массива
     * $json = $this->_encode_user_rights_internal([]);
     * echo "JSON-строка: $json"; // Выведет: {}
     * @endcode
     * @see    PhotoRigma::Classes::User::encode_user_rights()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _encode_user_rights_internal(array $rights): string
    {
        // Проверяем, существует ли массив прав
        if (!isset($rights)) {
            return '';
        }

        // Кодируем массив в JSON
        $json = json_encode($rights, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Проверяем наличие ошибок при кодировании
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Ошибка кодирования JSON | ' . json_last_error_msg(
                )
            );
        }

        // Возвращаем строку JSON
        return is_string($json) ? $json : '';
    }

    /**
     * @brief   Проверяет данные пользователя для входа в систему через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _login_user_internal().
     *          Он проверяет корректность входных данных (логин и пароль), ищет пользователя в базе данных, проверяет
     *          пароль и выполняет "мягкое" обновление паролей на новый формат хранения (`password_hash()`).
     *          Поддерживает восстановление аккаунтов, временно удалённых через мягкое удаление,
     *          если срок восстановления ещё не истёк.
     *          Предназначен для прямого использования извне.
     *
     * @callgraph
     *
     * @param array  $post         Массив данных из формы ($_POST), содержащий ключи:
     *                             - string $login: Логин пользователя (должен соответствовать регулярному выражению
     *                               REG_LOGIN).
     *                             - string $password: Пароль пользователя (не должен быть пустым).
     * @param string $redirect_url URL для перенаправления пользователя в случае возникновения ошибок.
     *                             Ограничения: должен быть валидным URL.
     *
     * @return int ID пользователя, если авторизация успешна, или `0` в случае ошибки.
     *
     * @throws Exception Выбрасывается, если:
     *                   - Возникает ошибка при проверке входных данных через `Work::check_input()`.
     *                     Пример сообщения:
     *                         Ошибка валидации входных данных | Поле: [имя_поля]
     *                   - Возникает ошибка при логировании событий через `log_in_file()`.
     *                     Пример сообщения:
     *                         Ошибка логирования события | Действие: login
     *
     * @note    Поддерживается совместимость со старым форматом хранения паролей (md5). При обнаружении старого формата
     *          пароль автоматически обновляется до формата `password_hash()`.
     *          Также поддерживается восстановление аккаунтов, временно удалённых через мягкое удаление,
     *          если срок восстановления ещё не истёк.
     *          Константы, используемые в методе:
     *          - `REG_LOGIN`: Регулярное выражение для проверки логина.
     *            Пример: `/^[a-zA-Z0-9][a-zA-Z0-9_-]*[a-zA-Z0-9_]$/`.
     *            - Допустимые символы: латинские буквы, цифры, подчеркивание (_), дефис (-).
     *            - Первый символ должен быть буквой или цифрой.
     *            - Последний символ должен быть буквой, цифрой или подчеркиванием.
     *            - Максимальная длина: 32 символа.
     *          - `TBL_USERS`: Имя таблицы пользователей в базе данных. Например: '`users`'.
     *          Эти константы позволяют гибко настраивать поведение метода.
     *
     * @warning Метод зависит от корректной конфигурации базы данных. Убедитесь, что таблица пользователей и поля
     *          (`id`, `login`, `password`, `deleted_at`, `permanently_deleted`) настроены правильно.
     *          Невалидные данные могут привести к исключениям.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $user = new \PhotoRigma\Classes\User();
     * $redirectUrl = '/login/error';
     * $userId = $user->login_user($_POST, $redirectUrl);
     * if ($userId > 0) {
     *     echo "Вход выполнен успешно! ID пользователя: {$userId}";
     * } else {
     *     echo "Ошибка входа.";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::User::_login_user_internal()
     *         Защищённый метод, реализующий основную логику проверки данных пользователя для входа.
     */
    public function login_user(array $post, string $redirect_url): int
    {
        return $this->_login_user_internal($post, $redirect_url);
    }

    /**
     * @brief   Проверяет данные пользователя для входа в систему с "мягким" обновлением паролей на новый формат
     *          хранения.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет корректность входных данных (логин и пароль) с использованием `Work::check_input()`.
     *             - Логин проверяется на соответствие регулярному выражению `REG_LOGIN`.
     *             - Пароль проверяется на пустоту.
     *             Если данные некорректны, пользователь перенаправляется на указанный URL (`$redirect_url`).
     *          2. Ищет пользователя в базе данных по логину:
     *             - Если пользователь не найден, происходит перенаправление на `$redirect_url`.
     *          3. Проверяет статус удаления пользователя:
     *             - Если установлен флаг `permanently_deleted`, доступ запрещён.
     *             - Если установлена дата `deleted_at` и срок восстановления истёк, доступ запрещён.
     *             - Если срок восстановления не истёк, аккаунт восстанавливается (обнуляется `deleted_at`).
     *          4. Проверяет пароль через `password_verify()`:
     *             - Если проверка успешна, возвращается ID пользователя.
     *             - Если проверка не проходит, выполняется дополнительная проверка через `md5` для поддержки старого
     *               формата хранения паролей.
     *          5. Если пароль хранится в старом формате (md5), он обновляется до формата `password_hash()` и
     *             сохраняется в базе данных.
     *          6. Возвращает ID пользователя или `0` в случае ошибки.
     *          Этот метод является защищённым и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `login_user()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param array  $post         Массив данных из формы ($_POST), содержащий ключи:
     *                             - string $login: Логин пользователя (должен соответствовать регулярному выражению
     *                               REG_LOGIN).
     *                             - string $password: Пароль пользователя (не должен быть пустым).
     * @param string $redirect_url URL для перенаправления пользователя в случае возникновения ошибок.
     *                             Ограничения: должен быть валидным URL.
     *
     * @return int ID пользователя, если авторизация успешна, или `0` в случае ошибки.
     *
     * @throws Exception Выбрасывается, если:
     *                   - Возникает ошибка при проверке входных данных через `Work::check_input()`.
     *                     Пример сообщения:
     *                         Ошибка валидации входных данных | Поле: [имя_поля]
     *                   - Возникает ошибка при логировании событий через `log_in_file()`.
     *                     Пример сообщения:
     *                         Ошибка логирования события | Действие: login
     *
     * @note    Поддерживается совместимость со старым форматом хранения паролей (md5). При обнаружении старого формата
     *          пароль автоматически обновляется до формата `password_hash()`.
     *          Константы, используемые в методе:
     *          - `REG_LOGIN`: Регулярное выражение для проверки логина.
     *            Пример: `/^[a-zA-Z0-9][a-zA-Z0-9_-]*[a-zA-Z0-9_]$/`.
     *            - Допустимые символы: латинские буквы, цифры, подчеркивание (_), дефис (-).
     *            - Первый символ должен быть буквой или цифрой.
     *            - Последний символ должен быть буквой, цифрой или подчеркиванием.
     *            - Максимальная длина: 32 символа.
     *          - `TBL_USERS`: Имя таблицы пользователей в базе данных. Например: '`users`'.
     *          Эти константы позволяют гибко настраивать поведение метода.
     *
     * @warning Метод зависит от корректной конфигурации базы данных. Убедитесь, что таблица пользователей и поля
     *          (`id`, `login`, `password`, `deleted_at`, `permanently_deleted`) настроены правильно.
     *          Невалидные данные могут привести к исключениям.
     *
     * Пример использования метода _login_user_internal():
     * @code
     * // Проверка данных пользователя для входа
     * $userId = $this->_login_user_internal($_POST, '/login/error');
     * if ($userId > 0) {
     *     echo "Вход выполнен успешно! ID пользователя: {$userId}";
     * } else {
     *     echo "Ошибка входа.";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Work::check_input()
     *         Метод для проверки правильности входных данных.
     * @see    PhotoRigma::Classes::User::$db
     *         Свойство, содержащее объект класса Database.
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция логирования событий.
     * @see    PhotoRigma::Classes::User::login_user()
     *         Публичный метод-редирект для вызова этой логики.
     * @see    PhotoRigma::Classes::User::_is_soft_delete_expired_internal()
     *         Метод для проверки истечения срока мягкого удаления.
     */
    protected function _login_user_internal(array $post, string $redirect_url): int
    {
        // === 1. Проверка входных данных (логин и пароль) ===
        if (!$this->work->check_input('_POST', 'login', [
                'isset'  => true,
                'empty'  => true,
                'regexp' => REG_LOGIN,
            ]) || !$this->work->check_input('_POST', 'password', [
                'isset' => true,
                'empty' => true,
            ])) {
            // Входные данные формы невалидны
            header('Location: ' . $redirect_url);
            exit;
        }

        // === 2. Поиск пользователя в базе данных по логину ===
        $this->db->select(
            ['`id`', '`login`', '`password`', '`deleted_at`', '`permanently_deleted`'],
            TBL_USERS,
            [
                'where'  => '`login` = :login',
                'params' => [':login' => $post['login']],
            ]
        );
        $user_data = $this->db->result_row();

        if ($user_data === false) {
            // Пользователь с указанным логином не найден
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка получения данных пользователя | Логин: {$post['login']}"
            );
            header('Location: ' . $redirect_url);
            exit;
        }

        // === 3. Проверяем статус удаления ===
        if (!empty($user_data['permanently_deleted'])) {
            // Пользователь окончательно удалён
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка входа заблокированного пользователя ID: {$user_data['id']}"
            );
            header('Location: ' . $redirect_url);
            exit;
        }

        if (!empty($user_data['deleted_at'])) {
            // Пользователь мягко удалён — проверяем истечение срока
            if ($this->_is_soft_delete_expired_internal($user_data['deleted_at'])['expired']) {
                // Срок истёк — считаем аккаунт окончательно удалённым
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Срок восстановления истёк | Пользователь ID: {$user_data['id']}"
                );
                header('Location: ' . $redirect_url);
                exit;
            }

            // Срок не истёк — восстанавливаем аккаунт
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Аккаунт мягко удалён, но срок восстановления не истёк | Пользователь ID: {$user_data['id']}"
            );

            $this->db->update(
                ['`deleted_at`' => 'NULL'],
                TBL_USERS,
                ['where' => '`id` = :id', 'params' => [':id' => $user_data['id']]]
            );

            $rows = $this->db->get_affected_rows();
            if ($rows <= 0) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка восстановления аккаунта | Пользователь ID: {$user_data['id']}"
                );
            }
        }

        // === 4. Проверка пароля ===
        if (!password_verify($post['password'], $user_data['password'])) {
            // Если проверка через password_verify() не прошла, проверяем через md5
            if (md5($post['password']) !== $user_data['password']) {
                // Пароль неверный
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный пароль | Логин: {$post['login']}"
                );
                header('Location: ' . $redirect_url);
                exit;
            }

            // Пароль верный, но хранится в формате md5. Преобразуем его в password_hash()
            $new_password_hash = password_hash($post['password'], PASSWORD_BCRYPT);

            $this->db->update(
                ['`password`' => ':password'],
                TBL_USERS,
                [
                    'where'  => '`id` = :id',
                    'params' => [
                        ':password' => $new_password_hash,
                        ':id'       => $user_data['id'],
                    ],
                ]
            );

            $rows = $this->db->get_affected_rows();
            if ($rows > 0) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Пароль успешно обновлён до нового формата | Пользователь ID: {$user_data['id']}"
                );
            } else {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка обновления пароля | Пользователь ID: {$user_data['id']}"
                );
            }
        }

        // === 5. Возвращаем ID пользователя ===
        return $user_data['id'];
    }

    /**
     * @brief   Обновляет данные существующего пользователя через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _update_user_data_internal().
     *          Он проверяет существование пользователя, валидирует и обновляет пароль, email, имя, аватар, язык и
     *          тему, а также удаляет старый аватар при необходимости. Предназначен для прямого использования извне.
     *
     * @callgraph
     *
     * @param int   $user_id    Идентификатор пользователя, данные которого необходимо обновить:
     *                          - Должен быть положительным целым числом и существовать в базе данных.
     *                          - Пример: 1.
     *                          Ограничения: ID должен быть больше 0.
     * @param array $post_data  Массив данных из формы ($_POST), содержащий новые значения для полей пользователя:
     *                          - string $password: Текущий пароль пользователя (обязательно для изменения пароля).
     *                          - string $edit_password: Новый пароль пользователя (необязательно).
     *                          - string $re_password: Повторный ввод нового пароля (должен совпадать с
     *                            `edit_password`).
     *                          - string $email: Новый email пользователя (должен соответствовать регулярному выражению
     *                            REG_EMAIL).
     *                          - string $real_name: Новое имя пользователя (должно быть строкой).
     *                          Все поля проходят валидацию перед использованием.
     * @param array $files_data Массив данных загруженного файла ($_FILES), содержащий информацию об аватаре:
     *                          - 'file_avatar' (array): Информация о загруженном файле (необязательно).
     *                          Если файл не передан или не проходит валидацию, аватар остается без изменений.
     * @param int   $max_size   Максимальный размер файла для аватара в байтах:
     *                          - Определяется на основе конфигурации приложения и ограничений PHP (например,
     *                            post_max_size).
     *                          - Пример: 5 * 1024 * 1024 (5 MB).
     *                          Ограничения: значение должно быть положительным целым числом.
     *
     * @return int Количество затронутых строк в базе данных после выполнения обновления.
     *             Возвращается `0`, если данные не изменились или запрос завершился ошибкой.
     *
     * @throws RuntimeException Выбрасывается, если пользователь с указанным `$user_id` не найден в базе данных.
     *                          Пример сообщения:
     *                              Пользователь не найден | ID: [user_id]
     * @throws Exception        Выбрасывается, если возникает ошибка при проверке входных данных через
     *                          `Work::check_input()` или при обработке аватара через `_edit_avatar()`.
     *                          Пример сообщения:
     *                              Ошибка валидации входных данных | Поле: [имя_поля]
     *
     * @note    Для обработки аватаров используется метод `_edit_avatar()`. Старый аватар удаляется, если новый успешно
     *          загружен и отличается от старого. Константы, используемые в методе:
     *          - `DEFAULT_AVATAR`: Имя аватара по умолчанию. Например: `'no_avatar.jpg'`.
     *          - `TBL_USERS`: Имя таблицы пользователей в базе данных. Например: '`users`'.
     *          - `REG_EMAIL`: Регулярное выражение для проверки email.
     *            Пример: `/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/`.
     *          Эти константы позволяют гибко настраивать поведение метода.
     *
     * @warning Не используйте этот метод для массового обновления данных. Он предназначен только для работы с одним
     *          пользователем за раз.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $user = new \PhotoRigma\Classes\User();
     * $userId = 1;
     * $maxSize = 5 * 1024 * 1024; // 5 MB
     * $affectedRows = $user->update_user_data($userId, $_POST, $_FILES, $maxSize);
     * if ($affectedRows > 0) {
     *     echo "Данные успешно обновлены!";
     * } else {
     *     echo "Ошибка при обновлении данных.";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::User::_update_user_data_internal()
     *         Защищённый метод, реализующий основную логику обновления данных пользователя.
     */
    public function update_user_data(int $user_id, array $post_data, array $files_data, int $max_size): int
    {
        return $this->_update_user_data_internal($user_id, $post_data, $files_data, $max_size);
    }

    /**
     * @brief   Обновляет данные существующего пользователя, включая пароль, email, имя и аватар.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет существование пользователя с указанным `$user_id` в базе данных. Если пользователь не
     *             найден, выбрасывается исключение.
     *          2. Валидирует и обновляет пароль:
     *             - Проверяет текущий пароль через `password_verify()`.
     *             - Если новый пароль передан и совпадает с повторным вводом, обновляет его через `password_hash()`.
     *          3. Проверяет уникальность email и имени пользователя в базе данных:
     *             - Если значения уже заняты другими пользователями, данные не обновляются.
     *          4. Обрабатывает загрузку нового аватара через метод `_edit_avatar()`:
     *             - Если файл проходит валидацию, загружает новый аватар.
     *             - Удаляет старый аватар, если он отличается от нового и не является аватаром по умолчанию.
     *          5. Проверяет и обновляет язык (`language`) и тему (`theme`) сайта, если они переданы.
     *          6. Формирует SQL-запрос для обновления данных пользователя в таблице `TBL_USERS`:
     *             - Использует prepared statements для безопасной вставки данных.
     *          7. Возвращает количество затронутых строк в базе данных после выполнения обновления.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `update_user_data()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param int   $user_id    Идентификатор пользователя, данные которого необходимо обновить.
     *                          Должен быть положительным целым числом и существовать в базе данных.
     *                          Пример: 1.
     *                          Ограничения: ID должен быть больше 0.
     * @param array $post_data  Массив данных из формы ($_POST), содержащий новые значения для полей пользователя:
     *                          - string $password: Текущий пароль пользователя (обязательно для изменения пароля).
     *                          - string $edit_password: Новый пароль пользователя (необязательно).
     *                          - string $re_password: Повторный ввод нового пароля (должен совпадать с
     *                            `edit_password`).
     *                          - string $email: Новый email пользователя (должен соответствовать регулярному выражению
     *                            REG_EMAIL).
     *                          - string $real_name: Новое имя пользователя (должно быть строкой).
     *                          Все поля проходят валидацию перед использованием.
     * @param array $files_data Массив данных загруженного файла ($_FILES), содержащий информацию об аватаре:
     *                          - 'file_avatar' (array): Информация о загруженном файле (необязательно).
     *                          Если файл не передан или не проходит валидацию, аватар остается без изменений.
     * @param int   $max_size   Максимальный размер файла для аватара в байтах. Определяется на основе конфигурации
     *                          приложения и ограничений PHP (например, post_max_size). Если размер файла превышает
     *                          это значение, загрузка аватара отклоняется.
     *                          Пример: 5 * 1024 * 1024 (5 MB).
     *                          Ограничения: значение должно быть положительным целым числом.
     *
     * @return int Количество затронутых строк в базе данных после выполнения обновления.
     *             Возвращается `0`, если данные не изменились или запрос завершился ошибкой.
     *
     * @throws RuntimeException Выбрасывается, если пользователь с указанным `$user_id` не найден в базе данных.
     *                          Пример сообщения:
     *                              Пользователь не найден | ID: [user_id]
     * @throws Exception        Выбрасывается, если возникает ошибка при проверке входных данных через
     *                          `Work::check_input()` или при обработке аватара через `_edit_avatar()`.
     *                          Пример сообщения:
     *                              Ошибка валидации входных данных | Поле: [имя_поля]
     *
     * @note    Для обработки аватаров используется метод `_edit_avatar()`. Старый аватар удаляется, если новый успешно
     *          загружен и отличается от старого. Константы, используемые в методе:
     *          - `DEFAULT_AVATAR`: Имя аватара по умолчанию. Например: `'no_avatar.jpg'`.
     *          - `TBL_USERS`: Имя таблицы пользователей в базе данных. Например: '`users`'.
     *          - `REG_EMAIL`: Регулярное выражение для проверки email.
     *            Пример: `/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/`.
     *          Эти константы позволяют гибко настраивать поведение метода.
     *
     * @warning Не используйте этот метод для массового обновления данных. Он предназначен только для работы с одним
     *          пользователем за раз.
     *
     * Пример использования метода _update_user_data_internal():
     * @code
     * // Обновление данных пользователя
     * $userId = 1;
     * $maxSize = 5 * 1024 * 1024; // 5 MB
     * $affectedRows = $this->_update_user_data_internal($userId, $_POST, $_FILES, $maxSize);
     * if ($affectedRows > 0) {
     *     echo "Данные успешно обновлены!";
     * } else {
     *     echo "Ошибка при обновлении данных.";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Work::check_input()
     *         Метод для проверки корректности входных данных.
     * @see    PhotoRigma::Classes::User::_edit_avatar()
     *         Приватный метод для обработки загрузки аватара.
     * @see    PhotoRigma::Classes::User::update_user_data()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _update_user_data_internal(int $user_id, array $post_data, array $files_data, int $max_size): int
    {
        // Проверяем, существует ли пользователь с указанным ID
        $this->db->select('*', TBL_USERS, [
            'where'  => '`id` = :user_id',
            'params' => [':user_id' => $user_id],
        ]);
        $user_data = $this->db->result_row();
        if (!$user_data) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Пользователь не найден | ID: $user_id"
            );
        }
        $new_user_data = [];
        // === ПРОВЕРКА ПАРОЛЯ ===
        if ($user_id !== $this->session['login_id'] || ($this->work->check_input(
            '_POST',
            'password',
            ['isset' => true, 'empty' => true]
        ) && password_verify($post_data['password'], $user_data['password']))) {
            if ($this->work->check_input('_POST', 'edit_password', ['isset' => true, 'empty' => true])) {
                $new_user_data['password'] = $post_data['re_password'] !== $post_data['edit_password'] ? $user_data['password'] : password_hash(
                    $post_data['re_password'],
                    PASSWORD_BCRYPT
                );
            } else {
                $new_user_data['password'] = $user_data['password'];
            }
        }
        // === ПРОВЕРКА EMAIL И REAL_NAME ===
        if ($this->work->check_input('_POST', 'email', ['isset' => true, 'empty' => true, 'regexp' => REG_EMAIL])) {
            $filtered_email = filter_var($post_data['email'], FILTER_SANITIZE_EMAIL);
            $this->db->select(
                'SUM(`email` = :email) as `email_count`, SUM(`real_name` = :real_name) as `real_count`',
                TBL_USERS,
                [
                    'where'  => '`id` != :user_id',
                    'params' => [
                        ':user_id'   => $user_id,
                        ':email'     => $filtered_email,
                        ':real_name' => $post_data['real_name'],
                    ],
                ]
            );
            $counts = $this->db->result_row();
            $new_user_data['email'] = $counts && isset($counts['email_count']) && $counts['email_count'] > 0 ? $user_data['email'] : $filtered_email;
            $new_user_data['real_name'] = $counts && isset($counts['real_count']) && $counts['real_count'] > 0 ? $user_data['real_name'] : $post_data['real_name'];
        } else {
            $new_user_data['email'] = $user_data['email'];
            $new_user_data['real_name'] = $user_data['real_name'];
        }
        // === ОБРАБОТКА АВАТАРА ===
        $delete_old_avatar = false; // Флаг для удаления старого аватара
        if ($post_data['delete_avatar'] !== 'true' || !$this->work->check_input(
            '_POST',
            'delete_avatar',
            ['isset' => true, 'empty' => true]
        )) {
            $new_user_data['avatar'] = $this->_edit_avatar($files_data, $max_size);
            // Проверяем, нужно ли удалить старый аватар
            if ($user_data['avatar'] !== DEFAULT_AVATAR && $user_data['avatar'] !== $new_user_data['avatar']) {
                $delete_old_avatar = true; // Устанавливаем флаг на удаление
            }
        } else {
            $old_avatar_path = $this->work->config['site_dir'] . $this->work->config['avatar_folder'] . '/' . $user_data['avatar'];
            if ($user_data['avatar'] !== DEFAULT_AVATAR && is_file($old_avatar_path) && is_writable(
                $old_avatar_path
            )) {
                unlink($old_avatar_path); // Безопасное удаление старого аватара
            }
            $new_user_data['avatar'] = DEFAULT_AVATAR;
        }
        // === Проверка языка и темы сайта ===
        $new_user_data['language'] = $this->work->check_input(
            '_POST',
            'language',
            ['isset' => true, 'empty' => true]
        ) ? $post_data['language'] : $user_data['language'];
        $new_user_data['theme'] = $this->work->check_input(
            '_POST',
            'theme',
            ['isset' => true, 'empty' => true]
        ) ? $post_data['theme'] : $user_data['theme'];
        // === ОБНОВЛЕНИЕ ДАННЫХ В БАЗЕ ===
        // === Формирование данных для обновления с плейсхолдерами ===
        $update_data = [];
        $params = [];
        foreach ($new_user_data as $field => $value) {
            $placeholder = ":update_$field"; // Уникальный плейсхолдер для каждого поля
            $update_data["`$field`"] = $placeholder; // Формируем ассоциативный массив для update
            $params[$placeholder] = $value; // Добавляем значение в параметры
        }

        // Добавляем параметр для WHERE
        $params[':user_id'] = $user_id;

        // === Вызов метода update с явными плейсхолдерами ===
        $this->db->update(
            $update_data, // Данные для обновления (ассоциативный массив с плейсхолдерами)
            TBL_USERS,    // Таблица (строка)
            [
                'where'  => '`id` = :user_id', // Условие WHERE (строка)
                'params' => $params,           // Все параметры для prepared statements (массив)
            ]
        );
        $affected_rows = $this->db->get_affected_rows();
        // Если данные успешно обновлены и флаг удаления установлен, удаляем старый аватар
        if ($affected_rows > 0 && $delete_old_avatar) {
            $old_avatar_path = $this->work->config['site_dir'] . $this->work->config['avatar_folder'] . '/' . $user_data['avatar'];
            if (is_file($old_avatar_path) && is_writable($old_avatar_path)) {
                unlink($old_avatar_path); // Безопасное удаление старого аватара
            }
        }
        return $affected_rows;
    }

    /**
     * @brief   Обрабатывает загрузку аватара пользователя и возвращает имя нового аватара или значение по умолчанию
     *          (`DEFAULT_AVATAR`), если загрузка не удалась.
     *
     * @details Этот приватный метод выполняет следующие действия:
     *          1. Проверяет входные данные через `Work::check_input()`:
     *             - Проверяется наличие и корректность файла.
     *             - Проверяется максимальный размер файла.
     *          2. Генерирует уникальное имя файла для аватара:
     *             - Используется временная метка (`time()`) и метод `Work::encodename()` для создания уникального
     *               имени.
     *          3. Перемещает загруженный файл в директорию аватаров:
     *             - Если директория недоступна для записи, выбрасывается исключение.
     *          4. Корректирует расширение файла на основе его MIME-типа с помощью `Work::fix_file_extension()`:
     *             - Если расширение изменено, файл переименовывается.
     *          5. Возвращает имя нового аватара или значение по умолчанию (`DEFAULT_AVATAR`), если загрузка не
     *             удалась.
     *          Метод является приватным и предназначен только для использования внутри класса.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param array $files_data Массив данных загруженного файла ($_FILES), содержащий информацию об аватаре.
     *                          Должен содержать ключ 'file_avatar' (массив):
     *                          - 'name': Имя файла.
     *                          - 'tmp_name': Временный путь к файлу.
     *                          - 'size': Размер файла в байтах.
     *                          Пример: $_FILES['file_avatar'].
     * @param int   $max_size   Максимальный размер файла для аватара в байтах.
     *                          Определяется на основе конфигурации приложения и ограничений PHP (например,
     *                          post_max_size). Пример: 5 * 1024 * 1024 (5 MB).
     *
     * @return string Имя нового аватара пользователя или значение по умолчанию (`DEFAULT_AVATAR`),
     *                если загрузка аватара была отменена или произошла ошибка.
     *                Пример: '1698765432_encoded_name.jpg'.
     *
     * @throws Exception        Может быть выброшено вызываемыми методами:
     *                          - `Work::check_input()`: При проверке входных данных.
     *                          - `rename()`: При переименовании файла после корректировки расширения.
     * @throws RuntimeException Выбрасывается исключение в следующих случаях:
     *                          - Директория для аватаров недоступна для записи.
     *                            Пример сообщения:
     *                                Директория для аватаров недоступна для записи.
     *                          - Не удалось переместить загруженный файл.
     *                            Пример сообщения:
     *                                Не удалось переместить загруженный файл: [временный_путь] -> [целевой_путь]
     *
     * @note    Используются константы:
     *          - `DEFAULT_AVATAR`: Значение аватара по умолчанию (например, 'no_avatar.jpg').
     *
     * @warning Убедитесь, что:
     *          - Массив `$files_data` содержит корректные данные о загруженном файле.
     *          - Директория для аватаров доступна для записи.
     *          Несоблюдение этих условий может привести к ошибкам загрузки аватара.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $maxSize = 5 * 1024 * 1024; // 5 MB
     * $newAvatar = $this->_edit_avatar($_FILES, $maxSize);
     * if ($newAvatar !== DEFAULT_AVATAR) {
     *     echo "Новый аватар успешно загружен: {$newAvatar}";
     * } else {
     *     echo "Загрузка аватара не удалась.";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Work::$config
     *         Свойство класса Work, содержащее конфигурацию приложения.
     * @see    PhotoRigma::Classes::Work::encodename()
     *         Метод для генерации уникального имени файла.
     * @see    PhotoRigma::Classes::Work::fix_file_extension()
     *         Метод для корректировки расширения файла на основе его MIME-типа.
     * @see    PhotoRigma::Classes::Work::check_input()
     *         Метод для проверки корректности входных данных.
     */
    private function _edit_avatar(array $files_data, int $max_size): string
    {
        // Проверяем входные данные через check_input
        if ($this->work->check_input('_FILES', 'file_avatar', [
            'isset'    => true,
            'empty'    => true,
            'max_size' => $max_size,
        ])) {
            // Генерация имени файла
            $original_name = basename($files_data['file_avatar']['name']);
            $file_info = pathinfo($original_name);
            $file_name = $file_info['filename'];
            $file_extension = isset($file_info['extension']) ? '.' . $file_info['extension'] : '';
            $encoded_name = Work::encodename($file_name);
            $file_avatar = time() . '_' . $encoded_name . $file_extension;
            $path_avatar = $this->work->config['site_dir'] . $this->work->config['avatar_folder'] . '/' . $file_avatar;

            // Проверяем права доступа к директории
            if (!is_writable($this->work->config['site_dir'] . $this->work->config['avatar_folder'])) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Директория для аватаров недоступна для записи.'
                );
            }

            // Перемещение загруженного файла
            if (!move_uploaded_file($files_data['file_avatar']['tmp_name'], $path_avatar)) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось переместить загруженный файл: {$files_data['file_avatar']['tmp_name']} -> $path_avatar"
                );
            }

            // Корректировка расширения файла
            $fixed_path = $this->work->fix_file_extension($path_avatar);
            if ($fixed_path !== $path_avatar) {
                rename($path_avatar, $fixed_path);
                $file_avatar = basename($fixed_path);
            }

            return $file_avatar;
        }

        return DEFAULT_AVATAR;
    }

    /**
     * @brief   Обновляет права пользователя или группу через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _update_user_rights_internal().
     *          Он проверяет, изменилась ли группа пользователя, обновляет её и связанные права доступа, а также
     *          возвращает обновленные данные пользователя. Предназначен для прямого использования извне (например,
     *          через Админку).
     *
     * @callgraph
     *
     * @param int   $user_id   Идентификатор пользователя:
     *                         - Должен быть положительным целым числом.
     *                         - Пример: 123.
     *                         Ограничения: ID должен быть больше 0.
     * @param array $user_data Данные пользователя:
     *                         - Должен содержать ключ `group_id` (ID текущей группы пользователя).
     *                         - Также должен содержать все поля, указанные в свойстве класса `user_right_fields`.
     *                         Пример: ['group_id' => 2, 'edit' => true, 'delete' => false].
     * @param array $post_data Данные, полученные из POST-запроса:
     *                         - Должен содержать ключ `group` (ID новой группы пользователя).
     *                         - Также должен содержать все поля, указанные в свойстве класса `user_right_fields`.
     *                         Пример: ['group' => 3, 'edit' => 'on', 'delete' => false].
     *
     * @return array Возвращает массив данных пользователя:
     *               - `group_id`: ID группы пользователя.
     *               - Все поля, указанные в свойстве класса `user_right_fields`.
     *
     * @throws RuntimeException Выбрасывается, если:
     *                          - Не удалось получить данные группы из БД.
     *                            Пример сообщения:
     *                                Не удалось получить данные группы | ID группы: [group_id]
     *                          - Произошла ошибка при обновлении данных пользователя в БД.
     *                            Пример сообщения:
     *                                Ошибка при обновлении данных пользователя | ID пользователя: [user_id]
     * @throws JsonException    Выбрасывается, если возникает ошибка при кодировании или декодировании JSON.
     *                          Пример сообщения:
     *                              Ошибка при кодировании прав доступа в JSON
     * @throws Exception        Выбрасывается, если возникает ошибка при проверке входных данных через
     *                          `Work::check_input()`. Пример сообщения: Ошибка валидации входных данных | Поле:
     *                          [имя_поля]
     *
     * @note    Метод использует свойство класса `user_right_fields` для определения допустимых полей прав
     *          пользователя.
     *          Обновление прав доступа происходит с нормализацией значений (true/false) и их последующим кодированием
     *          в JSON-строку.
     *          Константы, используемые в методе:
     *          - `TBL_USERS`: Имя таблицы пользователей в базе данных. Например: '`users`'.
     *          - `TBL_GROUP`: Имя таблицы групп в базе данных. Например: '`groups`'.
     *          Эти константы позволяют гибко настраивать поведение метода.
     *
     * @warning Убедитесь, что входные данные корректны. Невалидные данные могут привести к исключениям.
     *
     * Пример использования:
     * @code
     * // Вызов метода из клиентского кода
     * $object = new \PhotoRigma\Classes\User();
     * $user_id = 123;
     * $user_data = [
     *     'group_id' => 2,
     *     'edit'     => true,
     *     'delete'   => false,
     * ];
     * $post_data = [
     *     'group'  => 3,
     *     'edit'   => 'on',
     *     'delete' => false,
     * ];
     * $result = $object->update_user_rights($user_id, $user_data, $post_data);
     * print_r($result);
     * @endcode
     * @see    PhotoRigma::Classes::User::_update_user_rights_internal()
     *         Защищённый метод, реализующий основную логику обновления прав пользователя или группы.
     */
    public function update_user_rights(int $user_id, array $user_data, array $post_data): array
    {
        return $this->_update_user_rights_internal($user_id, $user_data, $post_data);
    }

    /**
     * @brief   Обновляет права пользователя или группу через Админку.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет, изменилась ли группа пользователя в `$post_data`. Если группа изменилась:
     *             - Получает данные новой группы из таблицы `TBL_GROUP`.
     *             - Обновляет ID группы пользователя и связанные права доступа в таблице `TBL_USERS`.
     *             - Декодирует обновленные права доступа пользователя в массив с помощью `process_user_rights()`.
     *          2. Если группа не изменилась:
     *             - Проверяет, отличаются ли текущие права пользователя от переданных в `$post_data`.
     *             - Нормализует значения прав доступа (true/false).
     *             - Кодирует обновленные права доступа в JSON-строку с помощью `encode_user_rights()`.
     *             - Обновляет права доступа пользователя в таблице `TBL_USERS`.
     *          3. Возвращает обновленные данные пользователя, включая все поля прав доступа и ID группы.
     *          Этот метод является защищенным и предназначен для использования внутри класса или его наследников.
     *          Основная логика вызывается через публичный метод-редирект `update_user_rights()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param int   $user_id   Идентификатор пользователя.
     *                         Должен быть положительным целым числом.
     *                         Пример: 123.
     *                         Ограничения: ID должен быть больше 0.
     * @param array $user_data Данные пользователя.
     *                         Должен содержать ключ `group_id` (ID текущей группы пользователя).
     *                         Также должен содержать все поля, указанные в свойстве класса `user_right_fields`.
     *                         Пример: ['group_id' => 2, 'edit' => true, 'delete' => false].
     * @param array $post_data Данные, полученные из POST-запроса.
     *                         Должен содержать ключ `group` (ID новой группы пользователя).
     *                         Также должен содержать все поля, указанные в свойстве класса `user_right_fields`.
     *                         Пример: ['group' => 3, 'edit' => 'on', 'delete' => false].
     *
     * @return array Возвращает массив данных пользователя:
     *               - `group_id`: ID группы пользователя.
     *               - Все поля, указанные в свойстве класса `user_right_fields`.
     *
     * @throws RuntimeException Выбрасывается, если:
     *                          - Не удалось получить данные группы из БД.
     *                            Пример сообщения:
     *                                Не удалось получить данные группы | ID группы: [group_id]
     *                          - Произошла ошибка при обновлении данных пользователя в БД.
     *                            Пример сообщения:
     *                                Ошибка при обновлении данных пользователя | ID пользователя: [user_id]
     * @throws JsonException    Выбрасывается, если возникает ошибка при кодировании или декодировании JSON.
     *                          Пример сообщения:
     *                              Ошибка при кодировании прав доступа в JSON
     * @throws Exception        Выбрасывается, если возникает ошибка при проверке входных данных через
     *                          `Work::check_input()`. Пример сообщения: Ошибка валидации входных данных | Поле:
     *                          [имя_поля]
     *
     * @note    Метод использует свойство класса `user_right_fields` для определения допустимых полей прав
     *          пользователя.
     *          Обновление прав доступа происходит с нормализацией значений (true/false) и их последующим кодированием
     *          в JSON-строку.
     *          Константы, используемые в методе:
     *          - `TBL_USERS`: Имя таблицы пользователей в базе данных. Например: '`users`'.
     *          - `TBL_GROUP`: Имя таблицы групп в базе данных. Например: '`groups`'.
     *          Эти константы позволяют гибко настраивать поведение метода.
     *
     * @warning Убедитесь, что входные данные корректны. Невалидные данные могут привести к исключениям.
     *
     * Пример использования метода _update_user_rights_internal():
     * @code
     * // Обновление прав пользователя
     * $userId = 123;
     * $userData = [
     *     'group_id' => 2,
     *     'edit'     => true,
     *     'delete'   => false,
     * ];
     * $postData = [
     *     'group'  => 3,
     *     'edit'   => 'on',
     *     'delete' => false,
     * ];
     * $result = $this->_update_user_rights_internal($userId, $userData, $postData);
     * print_r($result);
     * @endcode
     * @see    PhotoRigma::Classes::User::encode_user_rights()
     *         Метод, кодирующий массив прав пользователя в JSON-строку для хранения в БД.
     * @see    PhotoRigma::Classes::User::process_user_rights()
     *         Метод, декодирующий JSON-строку прав пользователя в массив.
     * @see    PhotoRigma::Classes::Work::check_input()
     *         Метод для проверки правильности входных данных.
     * @see    PhotoRigma::Classes::User::$user_right_fields
     *         Свойство, содержащее массив с допустимыми полями прав пользователя.
     * @see    PhotoRigma::Classes::User::update_user_rights()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _update_user_rights_internal(int $user_id, array $user_data, array $post_data): array
    {
        $new_user_data = $user_data;
        // Обновление группы пользователя
        /** @noinspection NotOptimalIfConditionsInspection */
        if ($this->work->check_input('_POST', 'group', [
                'isset'    => true,
                'empty'    => true,
                'regexp'   => '/^[0-9]+$/',
                'not_zero' => true,
            ]) && (int)$post_data['group'] !== $user_data['group_id']) {
            // Получаем данные новой группы из таблицы групп
            $this->db->select('`id`, `user_rights`', TBL_GROUP, [
                'where'  => '`id` = :group_id',
                'params' => [':group_id' => $post_data['group']],
            ]);
            $group_data = $this->db->result_row();

            if ($group_data) {
                $query = [];
                foreach ($group_data as $key => $value) {
                    if ($key === 'id') {
                        // Обновляем ID группы пользователя
                        $query['`group_id`'] = ':group_id';
                        $params[':group_id'] = $value;
                        $new_user_data['group_id'] = $value;
                    } else {
                        // Формируем массив для обновления данных пользователя в БД
                        $query["`$key`"] = ":$key";
                        $params[":$key"] = $value;
                        $new_user_data[$key] = $value;
                    }
                }

                $params[':uid'] = $user_id;

                // Обновляем данные пользователя в БД
                $this->db->update($query, TBL_USERS, [
                    'where'  => '`id` = :uid',
                    'params' => $params,
                ]);
                $rows = $this->db->get_affected_rows();

                if ($rows > 0) {
                    // Если данные успешно обновлены, обрабатываем права пользователя
                    $processed_rights = $this->process_user_rights($new_user_data['user_rights']);
                    unset($new_user_data['user_rights']);
                    $new_user_data = array_merge($new_user_data, $processed_rights);
                } else {
                    throw new RuntimeException(
                        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при обновлении данных пользователя | ID пользователя: $user_id"
                    );
                }
            } else {
                // Группа с указанным ID не найдена
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные группы | ID группы: {$post_data['group']}"
                );
            }
        } else {
            // Обновление прав доступа пользователя
            $new_user_rights = [];
            foreach ($this->user_right_fields['all'] as $field) {
                if ($this->work->check_input('_POST', $field, ['isset' => true])) {
                    // Если поле существует и имеет допустимое значение, устанавливаем его в true
                    $post_data[$field] = in_array($post_data[$field], ['on', 1, true], true);
                } else {
                    // Если поле не передано, устанавливаем его в false
                    $post_data[$field] = false;
                }

                $new_user_rights[$field] = $post_data[$field];
            }

            // Кодируем права доступа для сохранения в БД
            $encoded_user_rights = $this->encode_user_rights($new_user_rights);
            $new_user_data = array_merge($new_user_data, $new_user_rights);

            // Обновляем права доступа пользователя в БД
            $this->db->update(
                ['`user_rights`' => ':u_rights'],
                TBL_USERS,
                [
                    'where'  => '`id` = :uid',
                    'params' => [
                        ':uid'      => $user_id,
                        ':u_rights' => $encoded_user_rights,
                    ],
                ]
            );
        }

        return $new_user_data;
    }

    /**
     * @brief   Проверяет, истёк ли срок восстановления мягкого удаления.
     *
     * @details Метод является публичным редиректом для защищённого метода _is_soft_delete_expired_internal().
     *          Он используется как в веб-интерфейсе, так и фоновыми задачами.
     *
     * @callgraph
     *
     * @param string|null $deleted_at Дата и время мягкого удаления (формат MySQL DATETIME).
     *                                Может быть NULL, если пользователь не удален.
     *
     * @return array Ассоциативный массив с данными о сроке:
     *               - 'restore_date'   => ?DateTime Дата мягкого удаления
     *               - 'restore_expiry' => ?DateTime Дата истечения срока восстановления
     *               - 'expired'        => bool Указывает, истёк ли срок
     *
     * @throws Exception При ошибке парсинга даты или других внутренних ошибках.
     *
     * @note    Реализация находится в `_is_soft_delete_expired_internal()`.
     *
     * @warning Не передавайте невалидные строки как `$deleted_at`. Это приведёт к возврату пустых данных.
     *
     * Пример использования:
     * @code
     * // Проверка истечения срока
     * $restore_info = $user->is_soft_delete_expired('2025-05-01 10:00:00');
     *
     * if ($restore_info['expired']) {
     *     echo "Срок истёк";
     * } else {
     *     echo "Еще можно восстановить до " . $restore_info['restore_expiry']->format('Y-m-d H:i:s');
     * }
     * @endcode
     * @see     PhotoRigma::Classes::User::_is_soft_delete_expired_internal()
     *         Защищённый метод, реализующий основную логику.
     */
    public function is_soft_delete_expired(?string $deleted_at): array
    {
        return $this->_is_soft_delete_expired_internal($deleted_at);
    }

    /**
     * @brief   Возвращает данные о сроке восстановления аккаунта.
     *
     * @details Метод:
     *          1. Принимает дату мягкого удаления (`deleted_at`).
     *          2. Если дата не задана → возвращает пустые значения и `expired = false`.
     *          3. Парсит дату удаления в формате UTC.
     *          4. Вычисляет дату истечения срока восстановления:
     *             - На основе константы SOFT_DELETE_RETENTION_INTERVAL
     *             - Отсчёт от даты `$deleted_at`
     *          5. Проверяет, истёк ли срок:
     *             - TRUE → удаление окончательно
     *             - FALSE → пользователь ещё может восстановиться
     *          Все операции выполняются в защищённом методе _is_soft_delete_expired_internal().
     *          Реализация вызывается через публичный метод is_soft_delete_expired().
     *
     * @callergraph
     *
     * @param string|null $deleted_at Дата и время мягкого удаления (формат MySQL DATETIME).
     *                                Может быть NULL, если пользователь не удален.
     *
     * @return array Ассоциативный массив с данными о сроке восстановления:
     *               - 'restore_date'   => ?DateTime Дата мягкого удаления
     *               - 'restore_expiry' => ?DateTime Дата истечения срока восстановления
     *               - 'expired'        => bool Указывает, истёк ли срок
     *
     * @throws Exception При ошибке парсинга даты или других внутренних ошибках.
     *
     * @note    Метод использует константу SOFT_DELETE_RETENTION_INTERVAL для расчёта срока.
     *          Все даты обрабатываются в часовом поясе UTC.
     *
     * @warning Не передавайте невалидные строки как `$deleted_at`. Это приведёт к возврату пустых данных.
     *
     * Пример использования:
     * @code
     * // Получаем информацию о сроке
     * $restore_info = $this->_is_soft_delete_expired_internal('2025-05-01 10:00:00');
     *
     * if ($restore_info['expired']) {
     *     echo "Срок истёк";
     * } else {
     *     echo "Еще можно восстановить до " . $restore_info['restore_expiry']->format('Y-m-d H:i:s');
     * }
     * @endcode
     * @see    PhotoRigma::Classes::User::is_soft_delete_expired()
     *         Публичный метод, вызывающий этот внутренний метод.
     */
    protected function _is_soft_delete_expired_internal(?string $deleted_at): array
    {
        if (empty($deleted_at)) {
            return [
                'restore_date'   => null,
                'restore_expiry' => null,
                'expired'        => false,
            ];
        }

        try {
            // Парсим дату удаления
            $deleted_date = new DateTime($deleted_at, new DateTimeZone('UTC'));

            // Срок окончания восстановления
            $restore_expiry = clone $deleted_date;
            $restore_expiry->modify('+' . SOFT_DELETE_RETENTION_INTERVAL);

            // Проверяем истечение срока
            $now = new DateTime('now', new DateTimeZone($this->session['timezone']));
            $expired = $restore_expiry <= $now;

            return [
                'restore_date'   => $deleted_date,
                'restore_expiry' => $restore_expiry,
                'expired'        => $expired
            ];

        } catch (Exception) {
            return [
                'restore_date'   => null,
                'restore_expiry' => null,
                'expired'        => false
            ];
        }
    }

    /**
     * @brief   Выполняет мягкое удаление пользователя.
     *
     * @details Метод устанавливает дату удаления (`deleted_at`) для указанного пользователя.
     *          Если пользователь уже помечен как окончательно удалённый (`permanently_deleted = TRUE`),
     *          мягкое удаление не выполняется. Все действия логируются.
     *
     * @internal
     * @callergraph
     *
     * @param int $user_id ID пользователя, которого нужно мягко удалить
     *
     * @return bool TRUE при успехе, FALSE при ошибке или невозможности удаления
     *
     * @throws Exception Выбрасывается, если произошла ошибка на уровне БД
     *
     * @note    Этот метод должен вызываться только через `_delete_user_internal()`, чтобы соблюсти все проверки.
     * @see     _delete_user_internal()
     */
    private function _soft_delete(int $user_id): bool
    {
        // Проверяем, не был ли пользователь уже окончательно удалён
        $this->db->select('`permanently_deleted`', TBL_USERS, [
            'where' => '`id` = :id',
            'params' => [':id' => $user_id],
        ]);

        $result = $this->db->result_row();
        if ($result && (int)$result['permanently_deleted'] === 1) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Невозможно мягко удалить пользователя — аккаунт уже окончательно удалён | ID: $user_id"
            );
            return false;
        }

        // Устанавливаем дату мягкого удаления
        if (!$this->db->update(
            ['`deleted_at`' => 'CURRENT_TIMESTAMP'],
            TBL_USERS,
            ['where' => '`id` = :id', 'params' => [':id' => $user_id]]
        )) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось установить флаг deleted_at для пользователя | ID: $user_id"
            );
            return false;
        }

        $rows = $this->db->get_affected_rows();

        if ($rows > 0) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Аккаунт пользователя временно удален | ID: $user_id"
            );
            return true;
        }

        log_in_file(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось выполнить мягкое удаление | ID: $user_id"
        );

        return false;
    }

    /**
     * @brief   Публичный метод для окончательного обезличивания личных данных пользователя.
     *
     * @details Предназначен для вызова из клиентского кода:
     *          - Из фоновой задачи cron → с $force = FALSE
     *          - Из админки → с $force = TRUE
     *          Передаёт управление защищённому методу `_hard_delete_user_internal()`, который реализует основную логику.
     *          Метод должен использоваться только после истечения срока восстановления мягкого удаления.
     *
     * @callgraph
     *
     * @param int  $user_id ID пользователя, которого нужно окончательно удалить.
     *                       Должен быть положительным целым числом (`> 0`).
     *                       Пример: 123.
     * @param bool $force   [optional] Режим принудительного удаления:
     *                       - FALSE (по умолчанию): удаление через cron, после истечения срока мягкого удаления.
     *                       - TRUE: принудительное удаление из админки — требует:
     *                         - наличия прав администратора
     *                         - подтверждения в интерфейсе
     *                         - запрет на самоудаление админа
     *
     * @return bool TRUE при успехе, FALSE при ошибке или невозможности удаления.
     *
     * @throws Exception При ошибках выполнения SQL-запросов.
     *
     * @note    Подробнее о работе удаления см. в защищённом методе `_hard_delete_user_internal()`.
     *
     * Пример использования:
     * @code
     * // Из фонового скрипта (cron.php)
     * if ($user->hard_delete_user(123)) {
     *     echo "Пользователь 123 успешно обезличен\n";
     * } else {
     *     echo "Ошибка при обезличивании пользователя 123\n";
     * }
     *
     * // Из админки (принудительное удаление)
     * if ($user->hard_delete_user(123, true)) {
     *     echo "Пользователь 123 принудительно удален\n";
     * } else {
     *     echo "Ошибка при принудительном удалении пользователя 123\n";
     * }
     * @endcode
     * @see     PhotoRigma::Classes::User::_hard_delete_user_internal()
     *          Защищённый метод, реализующий окончательное обезличивание и удаление.
     */
    public function hard_delete_user(int $user_id, bool $force = false): bool
    {
        return $this->_hard_delete_user_internal($user_id, $force);
    }

    /**
     * @brief   Выполняет окончательное обезличивание записи пользователя.
     *
     * @details Метод:
     *          1. Получает данные пользователя по ID.
     *             - Если пользователь не найден → возвращает FALSE.
     *          2. При $force = FALSE:
     *             - Проверяет, был ли мягко удалён ранее
     *             - Убеждается, что срок истёк через _is_soft_delete_expired_internal()
     *          3. При $force = TRUE:
     *             - Проверяет, что вызов делает администратор
     *             - Проверяет, что админ подтвердил права в админке
     *             - НЕ позволяет админу удалить сам себя
     *          4. Проверяет, не последний ли это админ → если да, запрещаем
     *          5. Удаляет личный контент через _delete_personal_content()
     *          6. Обезличивает запись пользователя
     *             - email → deleted_{ID}@local.com
     *             - login → deleted_{ID}
     *             - password → случайная строка (до 20 символов)
     *             - avatar → DEFAULT_AVATAR
     *             - real_name → оставляем для фронтенда
     *             - permanently_deleted → устанавливаем в TRUE
     *          7. Все действия логируются через log_in_file()
     *
     * @param int  $user_id ID пользователя, которого нужно окончательно удалить
     * @param bool $force   [optional] Если TRUE → удаляем без проверок (админка), иначе только после мягкого удаления (cron)
     *
     * @return bool TRUE при успехе, FALSE при ошибке или невозможности удаления
     *
     * @throws Exception При ошибках выполнения SQL-запросов
     *
     * @note    Этот метод должен вызываться:
     *              - Из фоновой задачи cron → с $force = FALSE
     *              - Из админки → с $force = TRUE
     * @note    Поле real_name остаётся без изменений, так как используется на frontend
     * @note    Метод использует существующие свойства: $this->user['admin'], $this->session['admin_on']
     * @warning Не позволяет удалить последнего админа
     */
    protected function _hard_delete_user_internal(int $user_id, bool $force = false): bool
    {
        // 1. Получаем данные пользователя
        $this->db->select(
            ['`id`', '`group_id`', '`deleted_at`', '`permanently_deleted`', '`email`', '`real_name`', '`login`', '`avatar`'],
            TBL_USERS,
            ['where' => '`id` = :id', 'params' => [':id' => $user_id]]
        );
        $user_data = $this->db->result_row();

        if (!$user_data) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Пользователь с ID $user_id не найден"
            );
            return false;
        }

        // 2. Проверяем, уже ли помечен как окончательно удалённый
        if ((int)$user_data['permanently_deleted'] === 1) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Пользователь уже окончательно удалён | ID: $user_id"
            );
            return true;
        }

        // 3. Если НЕ force — проверяем срок мягкого удаления
        if (!$force) {
            if (empty($user_data['deleted_at'])) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Пользователь не был мягко удалён | ID: $user_id"
                );
                return false;
            }

            if (!$this->_is_soft_delete_expired_internal($user_data['deleted_at'])['expired']) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Срок мягкого удаления ещё не истёк | ID: $user_id"
                );
                return false;
            }
        }

        // 4. Если force = TRUE → проверяем права админа
        if ($force) {
            // Проверяем, является ли текущий пользователь админом
            if (!$this->user['admin']) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Текущий пользователь не администратор | ID: $user_id"
                );
                return false;
            }

            // Проверяем, что админ подтвердил сессию
            if (!$this->session['admin_on']) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Админ не подтвердил сессию | ID: $user_id"
                );
                return false;
            }

            // Проверяем, не пытается ли админ удалить сам себя
            if ((int)$user_data['id'] === $this->user['id']) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Админ не может удалить сам себя | ID: $user_id"
                );
                return false;
            }
        }

        // 5. Проверяем, не последний ли это админ
        if ((int)$user_data['group_id'] === GROUP_ADMIN) {
            $this->db->select('COUNT(*) AS cnt', TBL_USERS, [
                'where'  => '`group_id` = :group AND `id` != :exclude_id AND `permanently_deleted` = 0',
                'params' => [
                    ':group'      => GROUP_ADMIN,
                    ':exclude_id' => $user_id,
                ],
            ]);
            $count_result = $this->db->result_row();
            $admin_count = (int)$count_result['cnt'];

            if ($admin_count <= 0) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Невозможно удалить последнего админа | ID: $user_id"
                );
                return false;
            }
        }

        // 6. Удаляем личный контент
        if (!$this->_delete_personal_content($user_id)) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось удалить личный контент пользователя | ID: $user_id"
            );
            return false;
        }

        // 7. Генерируем обезличенные данные
        $anonymized_login = "deleted_$user_id";
        $anonymized_email = "deleted_$user_id@local.com";
        $delete_token = substr(basename(tempnam(sys_get_temp_dir(), 'del_')), 0, 20);

        // 8. Обезличиваем запись пользователя
        $this->db->update(
            [
                '`email`'               => ':email',
                '`login`'               => ':login',
                '`password`'            => ':password',
                '`avatar`'              => ':avatar',
                '`permanently_deleted`' => '1',
            ],
            TBL_USERS,
            [
                'where'  => '`id` = :id',
                'params' => [
                    ':id'       => $user_id,
                    ':login'    => $anonymized_login,
                    ':email'    => $anonymized_email,
                    ':password' => $delete_token,
                    ':avatar'   => DEFAULT_AVATAR,
                ],
            ]
        );

        // 9. Дополнительно проверяем, действительно ли обновилась запись
        $this->db->select('`email`, `login`, `password`, `avatar`, `permanently_deleted`', TBL_USERS, [
            'where'  => '`id` = :id',
            'params' => [':id' => $user_id],
        ]);
        $updated_data = $this->db->result_row();

        if (
            $updated_data === false ||
            $updated_data['email'] !== $anonymized_email ||
            $updated_data['login'] !== $anonymized_login ||
            $updated_data['password'] !== $delete_token ||
            $updated_data['avatar'] !== DEFAULT_AVATAR ||
            (int)$updated_data['permanently_deleted'] !== 1
        ) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось подтвердить обезличивание пользователя | ID: $user_id"
            );
            return false;
        }

        // 10. Логируем событие
        log_in_file(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Аккаунт пользователя окончательно удален | ID: $user_id"
        );

        return true;
    }

    /**
     * @brief   Удаляет личные фото пользователя (категория 0) через существующий метод `del_photo()`.
     *
     * @details Метод:
     *          1. Находит все фото, где user_upload = $user_id и category = 0.
     *          2. Для каждого photo_id вызывает $this->work->del_photo($photo_id).
     *          3. Считает количество успешно удалённых фото.
     *          4. Логирует общий результат.
     *          Не работает с альбомами — всё определяется полем category.
     *
     *          Этот метод является внутренним и предназначен только для вызова из `_hard_delete_user_internal()`.
     *          Он не выбрасывает исключений при ошибке отдельного удаления фото — только общая ошибка БД останавливает выполнение.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param int $user_id ID пользователя, чьи личные фото нужно удалить.
     *                     Должен быть положительным целым числом (`> 0`).
     *                     Пример: 123.
     *
     * @return bool TRUE если удаление начато и выполнено частично или полностью,
     *              FALSE только при критической ошибке БД (например, выборка фото не удалась).
     *
     * @throws Exception При ошибках SQL-запроса (например, ошибка выборки фото).
     *
     * @note    Личные фото = TBL_PHOTO WHERE user_upload = $user_id AND category = 0.
     *          Метод `$this->work->del_photo($photo_id)` уже содержит логику удаления файла и записи.
     *
     * @warning Этот метод должен вызываться только из `_hard_delete_user_internal()`.
     *          Он не обеспечивает полной гарантии удаления всех фото — отдельные ошибки игнорируются.
     *
     * Пример использования:
     * @code
     * // Из метода _hard_delete_user_internal()
     * if (!$this->_delete_personal_content(123)) {
     *     log_in_file(__FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при удалении личного контента | ID: 123");
     *     return false;
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Work::del_photo()
     *         Внешний метод, реализующий окончательное удаление одного фото.
     * @see    PhotoRigma::Classes::User::_hard_delete_user_internal()
     *         Защищённый метод, использующий этот метод перед обезличиванием аккаунта.
     */
    private function _delete_personal_content(int $user_id): bool
    {
        // 1. Получаем список личных фото
        $this->db->select(
            '`id` AS photo_id',
            TBL_PHOTO,
            [
                'where' => '`user_upload` = :user_id AND `category` = 0',
                'params' => [':user_id' => $user_id],
            ]
        );
        $photos = $this->db->result_array();

        if (!$photos) {
            return true; // Нет фото → ничего не удалять
        }

        $deleted_count = 0;

        foreach ($photos as $row) {
            $photo_id = (int)$row['photo_id'];

            // 2. Вызываем внешний метод удаления фото
            if ($this->work->del_photo($photo_id)) {
                $deleted_count++;
            }
        }

        // 3. Логируем результат
        log_in_file(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Успешно удален контент: $deleted_count шт. | Пользователь: $user_id"
        );

        return true;
    }

    /**
     * @brief   Публичный метод для запуска фоновой задачи окончательного удаления пользователей.
     *
     * @details Предназначен для вызова из cron.php. Содержит защиту от запуска через веб-интерфейс.
     *          Передаёт управление защищённому методу `_cron_user_delete_internal()`, который реализует логику:
     *          - Выборка пользователей с истёкшим сроком мягкого удаления
     *          - Обезличивание аккаунтов
     *          - Удаление личного контента
     *
     * @callgraph
     *
     * @return void Метод не возвращает значения.
     *
     * @throws Exception Меожет выбрасываться:
     *                   - При логировании ошибок.
     *                   - Из метода `_cron_user_delete_internal()`.
     *
     * @note    Метод содержит защиту от запуска через веб-запрос:
     *              - Проверяется, что скрипт запущен в режиме CLI (через cron).
     *              - В случае веб-запроса — выводится ошибка и выполнение прерывается.
     *
     * @warning Этот метод должен запускаться только через cron.php и никогда — из веб-интерфейса.
     *          Он не должен быть доступен извне.
     *
     * Пример использования:
     * @code
     * // Из cron.php
     * $user->cron_user_delete();
     * @endcode
     * @see    PhotoRigma::Classes::User::_cron_user_delete_internal()
     *         Защищённый метод, реализующий фоновую логику обезличивания пользователей.
     */
    public function cron_user_delete(): void
    {
        // Защита от запуска через веб-интерфейс
        if (PHP_SAPI !== 'cli') {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                'Запрещён запуск через веб | cron_user_delete()'
            );
            exit('Доступ запрещён');
        }

        // Запуск внутренней логики
        $this->_cron_user_delete_internal();
    }

    /**
     * @brief   Фоновая задача: выполняет окончательное обезличивание пользователей,
     *          у которых истёк срок восстановления.
     *
     * @details Алгоритм:
     *          1. Выбираем всех пользователей, где:
     *             - deleted_at ≠ NULL
     *             - permanently_deleted = 0
     *          2. Для каждого пользователя:
     *             - Проверяем, истёк ли срок мягкого удаления через _is_soft_delete_expired_internal()
     *             - Если истёк → вызываем _hard_delete_user_internal()
     *          Все действия логируются через log_in_file()
     *
     *          Этот метод является защищённым и предназначен только для вызова из публичного метода `cron_user_delete()`.
     *          Работает строго по PHP-логике, а не SQL, чтобы обеспечить совместимость со всеми СУБД.
     *          Должен вызываться только через cron.php.
     *
     * @callergraph
     * @callgraph
     *
     * @return void Метод не возвращает значения.
     *
     * @throws Exception                    Может выбрасываться:
     *                                      - При логировании ошибок.
     *                                      - При выполнении запросов к базе данных.
     *
     * @note    Метод не выбрасывает исключений, но может завершиться досрочно при критических ошибках БД.
     *          Логика реализована на стороне PHP, чтобы сохранить совместимость с разными СУБД.
     *
     * @warning Этот метод должен запускаться только фоновой задачей (cron).
     *          Он не должен быть доступен из внешнего интерфейса.
     *
     * Пример использования:
     * @code
     * $this->_cron_user_delete_internal();
     * @endcode
     * @see    PhotoRigma::Classes::User::cron_user_delete()
     *         Публичный метод-редирект для вызова этой логики.
     * @see    PhotoRigma::Classes::User::_is_soft_delete_expired_internal()
     *         Для проверки истечения срока мягкого удаления.
     * @see    PhotoRigma::Classes::User::_hard_delete_user_internal()
     *         Для окончательного обезличивания аккаунта.
     */
    protected function _cron_user_delete_internal(): void
    {
        // 1. Получаем список пользователей на окончательное удаление
        $this->db->select(
            ['`id`', '`deleted_at`'],
            TBL_USERS,
            [
                'where' => '`deleted_at` IS NOT NULL AND `permanently_deleted` = 0'
            ]
        );
        $users = $this->db->result_array();

        if (!$users) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                'Нет пользователей к окончательному удалению'
            );
            return;
        }

        foreach ($users as $user) {
            $user_id = (int)$user['id'];

            // 2. Проверяем, истёк ли срок мягкого удаления
            if (!$this->_is_soft_delete_expired_internal($user['deleted_at'])['expired']) {
                continue; // Срок ещё не истёк
            }

            // 3. Вызываем окончательное удаление
            $this->_hard_delete_user_internal($user_id);
        }

        log_in_file(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
            'Фоновая задача окончательного удаления завершена'
        );
    }
}
