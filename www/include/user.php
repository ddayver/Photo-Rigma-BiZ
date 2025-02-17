<?php

/**
 * @file        include/user.php
 * @brief       Класс для управления пользователями, включая регистрацию, аутентификацию, управление профилем и хранение текущих настроек пользователя.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-02-14
 * @namespace   PhotoRigma\\Classes
 *
 * @details     Содержит класс `User` и интерфейс `User_Interface` с набором методов для работы с пользователями, а также используется для хранения всех данных о текущем пользователе.
 *              Остальное будет добавлено по мере документирования класса.
 *
 * @see         \\PhotoRigma\\Classes\\User Класс для работы с пользователями.
 * @see         \\PhotoRigma\\Classes\\User_Interface Интерфейс для работы с пользователями.
 * @see         \\PhotoRigma\\Include\\log_in_file() Функция для логирования ошибок.
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
 * Класс по работе с пользователями.
 *
 * Данный класс содержит набор функций для работы с пользователями,
 * а также используется для хранения всех данных о текущем пользователе.
 */
class user
{
    /** @var array Массив, содержащий все данные о текущем пользователе. */
    public array $user = [];

    /** @var db|null Объект для работы с базой данных. */
    private ?db $db = null;

    /**
     * Конструктор класса.
     *
     * Заполняет данные при создании объекта класса данными о текущем пользователе.
     *
     * @param Database $db Объект для работы с базой данных.
     * @throws Exception Если возникают ошибки при работе с базой данных.
     */
    public function __construct()
    {
        global $db;
        if (!$db instanceof db) {
            throw new \InvalidArgumentException('Invalid database object provided.');
        }
        $this->db = $db;
        $this->initialize_user();
    }

    /**
     * Инициализация данных пользователя.
     *
     * @throws Exception Если возникают ошибки при загрузке данных пользователя.
     */
    private function initialize_user(): void
    {
        $login_id = $_SESSION['login_id'] ?? 0;

        if ($login_id === 0) {
            $this->load_guest_user();
        } else {
            $this->load_authenticated_user($login_id);
        }
    }

    /**
     * Загрузка данных гостя.
     *
     * @throws Exception Если не удается получить данные группы гостя.
     */
    private function load_guest_user(): void
    {
        if (!$this->db->select('*', TBL_GROUP, ['where' => '`id` = :group_id', 'params' => ['group_id' => 0]])) {
            throw new Exception('Unable to get the guest group');
        }

        $guest_group = $this->db->res_row();
        if (!$guest_group) {
            throw new Exception('Unable to get the guest group');
        }

        $this->user = $guest_group;
    }

    /**
     * Загрузка данных аутентифицированного пользователя.
     *
     * @param int $user_id Идентификатор пользователя.
     * @throws Exception Если возникают ошибки при загрузке данных пользователя.
     */
    private function load_authenticated_user(int $user_id): void
    {
        if (!$this->db->select('*', TBL_USERS, ['where' => '`id` = :user_id', 'params' => ['user_id' => $user_id]])) {
            throw new Exception('Unable to get user data');
        }

        $user_data = $this->db->res_row();
        if (!$user_data) {
            $_SESSION['login_id'] = 0;
            $this->load_guest_user();
            return;
        }

        $this->user = $user_data;

        if (!$this->db->select('*', TBL_GROUP, ['where' => '`id` = :group_id', 'params' => ['group_id' => $this->user['group']]])) {
            throw new Exception('Unable to get user group');
        }

        $group_data = $this->db->res_row();
        if (!$group_data) {
            $this->user['group'] = 0;
            $this->load_guest_user();
            return;
        }

        $this->merge_user_with_group($group_data);

        if (!$this->db->update(['date_last_activ' => date('Y-m-d H:i:s')], TBL_USERS, ['where' => '`id` = :user_id', 'params' => ['user_id' => $user_id]])) {
            throw new Exception('Failed to update user activity');
        }
    }

    /**
     * Объединение данных пользователя с данными группы.
     *
     * @param array $group_data Данные группы пользователя.
     */
    private function merge_user_with_group(array $group_data): void
    {
        foreach ($group_data as $key => $value) {
            if ($key === 'name') {
                $this->user['group_id'] = $this->user['group'];
                $this->user['group'] = $value;
            } elseif ($key !== 'id') {
                $this->user[$key] = ($this->user[$key] == 0 && $value == 0) ? false : true;
            }
        }
    }
}
