<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUndefinedClassInspection */
/**
 * @file      action/profile.php
 * @brief     Работа с пользователями: вход, выход, регистрация, редактирование и просмотр профиля.
 *
 * @author    Dark Dayver
 * @version   0.4.4
 * @date      2025-05-07
 * @namespace PhotoRigma\\Action
 *
 * @details   Этот файл отвечает за управление профилями пользователей, включая:
 *            - Вход и выход пользователя.
 *            - Регистрацию новых пользователей.
 *            - Редактирование профиля (изменение аватара, языка, темы и других данных).
 *            - Просмотр профиля другого пользователя.
 *            - Защиту от CSRF-атак при изменении данных.
 *            - Логирование ошибок и подозрительных действий.
 *
 * @section   Profile_Main_Functions Основные функции
 *            - Вход и выход пользователя.
 *            - Регистрация новых пользователей.
 *            - Редактирование профиля.
 *            - Просмотр профиля другого пользователя.
 *            - Логирование ошибок и подозрительных действий.
 *
 * @section   Profile_Error_Handling Обработка ошибок
 *            При возникновении ошибок генерируются исключения. Поддерживаемые типы исключений:
 *            - `RuntimeException`: Если не удалось выполнить действие с профилем (например, вход, выход,
 *              регистрация, редактирование).
 *            - `LogicException`: Если не удалось получить данные группы пользователя.
 *            - `Random\RandomException`: При выполнении методов, использующих `random()`.
 *            - `JsonException`: При выполнении методов, использующих JSON.
 *            - `Exception`: При выполнении прочих методов классов, функций или операций.
 *
 * @throws    RuntimeException      Если не удалось выполнить действие с профилем (например, вход, выход,
 *                                  регистрация, редактирование). Пример сообщения:
 *                                  "Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}".
 * @throws    LogicException        Если не удалось получить данные группы пользователя.
 *                                  Пример сообщения:
 *                                  "Не удалось получить данные гостевой группы | Вызов от пользователя с ID:
 *                                  {$user->session['login_id']}".
 * @throws    Random\RandomException При выполнении методов, использующих random().
 * @throws    JsonException          При выполнении методов, использующих JSON.
 * @throws    Exception              При выполнении прочих методов классов, функций или операций.
 *
 * @section   Profile_Related_Files Связанные файлы и компоненты
 *            - Классы приложения:
 *              - @see PhotoRigma::Classes::Work
 *                     Класс используется для выполнения вспомогательных операций.
 *              - @see PhotoRigma::Classes::Database
 *                     Класс для работы с базой данных.
 *              - @see PhotoRigma::Classes::User
 *                     Класс для управления пользователями.
 *              - @see PhotoRigma::Classes::Template
 *                     Класс для работы с шаблонами.
 *            - Вспомогательные функции:
 *              - @see PhotoRigma::Include::log_in_file()
 *                     Функция для логирования ошибок.
 *            - Файлы приложения:
 *              - @see index.php Этот файл подключает action/profile.php по запросу из `$_GET`.
 *
 * @note      Этот файл является частью системы PhotoRigma.
 *            Реализованы меры безопасности для предотвращения несанкционированного доступа и выполнения действий.
 *            Используются подготовленные выражения для защиты от SQL-инъекций.
 *
 * @copyright Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license   MIT License (https://opensource.org/licenses/MIT)
 *            Разрешается использовать, копировать, изменять, объединять, публиковать,
 *            распространять, сублицензировать и/или продавать копии программного обеспечения,
 *            а также разрешать лицам, которым предоставляется данное программное обеспечение,
 *            делать это при соблюдении следующих условий:
 *            - Уведомление об авторских правах и условия лицензии должны быть включены во все
 *              копии или значимые части программного обеспечения.
 */

namespace PhotoRigma\Action;

use DateTime;
use DateTimeZone;
use LogicException;
use PhotoRigma\Classes\Bootstrap;
use PhotoRigma\Classes\Database;
use PhotoRigma\Classes\Template;
use PhotoRigma\Classes\User;
use PhotoRigma\Classes\Work;
use RuntimeException;

use function PhotoRigma\Include\log_in_file;

/** @var Database $db */
/** @var Work $work */
/** @var User $user */
/** @var Template $template */

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

// Устанавливаем файл шаблона
$template->template_file = 'profile.html';

// Проверяем и получаем значение subact через check_input
$subact = match (true) {
    $work->check_input('_GET', 'subact', [
        'isset'  => true,
        'empty'  => true,
        'regexp' => '/^[_A-Za-z0-9\-]+$/',
    ])                               => $_GET['subact'],

    $user->session['login_id'] === 0 => 'login',

    default                          => 'logout',
};

// Определяем URL для перенаправления
/** @noinspection BypassedUrlValidationInspection */
$redirect_url = match (true) {
    !empty($_SERVER['HTTP_REFERER']) && filter_var(
        $_SERVER['HTTP_REFERER'],
        FILTER_VALIDATE_URL
    )       => $_SERVER['HTTP_REFERER'],
    default => SITE_URL,
};

if ($subact === 'delete_profile') {
    if ($user->session['login_id'] === 0) {
        header('Location: ' . $redirect_url);
        exit;
    }
    // Проверяем CSRF-токен
    if (empty($_POST['csrf_token']) || !hash_equals(
        $user->session['csrf_token'],
        $_POST['csrf_token']
    )) {
        throw new RuntimeException(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
        );
    }
    $user->unset_property_key('session', 'csrf_token');

    // Получаем ID текущего пользователя
    $current_uid = $user->session['login_id'];

    // Вызываем мягкое удаление
    if ($user->delete_user($current_uid)) {
        log_in_file(__FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Аккаунт успешно мягко удален | ID: $current_uid");

        // Убираем login_id из сессии → пользователь выходит
        $user->set_property_key('session', 'login_id', 0);

        // Необязательно, но можно обновить сессию
        session_regenerate_id(true);

        // Перенаправляем на главную
        header('Location: ' . SITE_URL);
        exit;
    }

    log_in_file(__FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось мягко удалить аккаунт | ID: $current_uid");
    header('Location: ' . $redirect_url);
    exit;
}

if ($subact === 'saveprofile') {
    /** @noinspection PhpUnusedLocalVariableInspection */
    $subact = 'profile';
    if ($user->session['login_id'] === 0) {
        header('Location: ' . $redirect_url);
        exit;
    }

    // Проверяем параметр uid через check_input
    if ($work->check_input('_GET', 'uid', [
        'isset'    => true,
        'empty'    => true,
        'regexp'   => '/^[0-9]+$/',
        'not_zero' => true,
    ])) {
        $user_id = (int)$_GET['uid'];
    } else {
        $user_id = $user->session['login_id'];
    }

    // Проверяем CSRF-токен
    if (empty($_POST['csrf_token']) || !hash_equals(
        $user->session['csrf_token'],
        $_POST['csrf_token']
    )) {
        throw new RuntimeException(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
        );
    }
    $user->unset_property_key('session', 'csrf_token');

    // Проверяем права доступа
    if ($user_id === $user->session['login_id'] || $user->user['admin']) {
        // Запрос с плейсхолдерами
        $db->select('*', TBL_USERS, [
            'where'  => '`id` = :user_id',
            'params' => [':user_id' => $user_id],
        ]);
        $user_data = $db->result_row();
        if (!$user_data) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные пользователя | ID: $user_id"
            );
        }

        // Определяем максимальный размер файла для аватара
        $max_size_php = Work::return_bytes(ini_get('post_max_size'));
        $max_size = Work::return_bytes($work->config['max_file_size']);
        if ($max_size > $max_size_php) {
            $max_size = $max_size_php;
        }

        // Вызываем метод update_user_data для обновления данных пользователя
        $user->update_user_data($user_id, $_POST, $_FILES, $max_size);

        // Переинициируем класс User для обновления данных в сессии.
        [$user, $template] = Bootstrap::change_user($db, $work, $_SESSION);
        header('Location: ' . $redirect_url);
        exit;
    }

    throw new RuntimeException(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Отказано в праве редактирования пользователя | Редактируемый ID: $user_id, Текущий ID: {$user->session['login_id']}"
    );
}

if ($subact === 'logout') {
    if ($user->session['login_id'] === 0) {
        header('Location: ' . $redirect_url);
        exit;
    }

    // Обновление данных пользователя через плейсхолдеры
    $db->update([
        '`date_last_activ`'  => ':last_activ',
        '`date_last_logout`' => 'NOW()',
    ], TBL_USERS, [
        'where'  => '`id` = :user_id',
        'params' => [
            ':user_id'    => $user->session['login_id'],
            ':last_activ' => null, // Передаем NULL как значение
        ],
    ]);

    // Проверка успешности обновления
    $rows = $db->get_affected_rows();
    if ($rows === 0) {
        throw new RuntimeException(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось обновить данные пользователя при выходе | ID: {$user->session['login_id']}"
        );
    }

    // Безопасное завершение сессии
    $user->set_property_key('session', 'login_id', 0);
    $user->set_property_key('session', 'admin_on', false);

    // Регенерация session_id для безопасности
    session_regenerate_id(true);

    [$user, $template] = Bootstrap::change_user($db, $work, $_SESSION);

    // Уничтожение сессии
    session_destroy();

    // Перенаправление и логирование
    header('Location: ' . SITE_URL);
    exit;
}

if ($subact === 'regist') {
    // Проверяем, авторизован ли пользователь через check_input
    if ($work->check_input('_SESSION', 'login_id', [
        'isset'    => true,
        'empty'    => true,
        'regexp'   => '/^[0-9]+$/',
        'not_zero' => true,
    ])) {
        // Если пользователь уже авторизован, перенаправляем на главную страницу
        header('Location: ' . SITE_URL);
        exit;
    }

    // Устанавливаем блок шаблона для регистрации
    $template->add_case('PROFILE_BLOCK', 'REGIST');

    // Задаем заголовок страницы
    /** @noinspection PhpUnusedLocalVariableInspection */
    $title = $work->lang['profile']['regist'];
    /** @noinspection PhpUnusedLocalVariableInspection */
    $action = 'regist';

    // Генерируем CAPTCHA и сохраняем её ответ в сессии (хэшированный)
    $captcha = $work->gen_captcha();
    $user->set_property_key('session', 'captcha', password_hash($captcha['answer'], PASSWORD_BCRYPT));

    // Генерируем CSRF-токен для защиты от атак типа CSRF
    $template->add_string('CSRF_TOKEN', $user->csrf_token());

    // Инициализируем флаги ошибок для всех полей формы
    $template->add_if_array([
        'ERROR_LOGIN'       => false,
        'ERROR_PASSWORD'    => false,
        'ERROR_RE_PASSWORD' => false,
        'ERROR_EMAIL'       => false,
        'ERROR_REAL_NAME'   => false,
        'ERROR_CAPTCHA'     => false,
    ]);

    // Добавляем строки для шаблона, включая метки, URL и данные CAPTCHA
    $template->add_string_array([
        'NAME_BLOCK'         => $work->lang['profile']['regist'],
        'L_LOGIN'            => $work->lang['profile']['login'],
        'L_PASSWORD'         => $work->lang['profile']['password'],
        'L_RE_PASSWORD'      => $work->lang['profile']['re_password'],
        'L_EMAIL'            => $work->lang['profile']['email'],
        'L_REAL_NAME'        => $work->lang['profile']['real_name'],
        'L_REGISTER'         => $work->lang['profile']['register'],
        'L_CAPTCHA'          => $work->lang['profile']['captcha'],
        'U_REGISTER'         => sprintf('%s?action=profile&subact=register', SITE_URL),
        'D_CAPTCHA_QUESTION' => $captcha['question'],
        'D_LOGIN'            => '',
        'D_EMAIL'            => '',
        'D_REAL_NAME'        => '',
    ]);

    // Если есть ошибки в сессии, добавляем их в шаблон
    if (!empty($user->session['error']) && is_array($user->session['error'])) {
        foreach ($user->session['error'] as $key => $value) {
            // Добавляем флаг ошибки для поля
            $template->add_if('ERROR_' . strtoupper($key), $value['if'] ?? false);

            // Добавляем данные ошибки в шаблон
            $template->add_string_array([
                'D_' . strtoupper($key)       => Work::clean_field($value['data'] ?? '') ?? '',
                'D_ERROR_' . strtoupper($key) => Work::clean_field($value['text'] ?? '') ?? '',
            ]);
        }
        // Очищаем ошибки из сессии после использования
        $user->unset_property_key('session', 'error');
    }
} elseif ($subact === 'register') {
    // Проверяем, авторизован ли пользователь через check_input
    if ($work->check_input('_SESSION', 'login_id', [
        'isset'    => true,
        'empty'    => true,
        'regexp'   => '/^[0-9]+$/',
        'not_zero' => true,
    ])) {
        header('Location: ' . $redirect_url);
        exit;
    }

    // Проверяем CSRF-токен
    if (empty($_POST['csrf_token']) || !hash_equals(
        $user->session['csrf_token'],
        $_POST['csrf_token']
    )) {
        throw new RuntimeException(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
        );
    }
    $user->unset_property_key('session', 'csrf_token');

    // Вызываем метод add_user_data
    $new_user = $user->add_new_user($_POST);

    // Обработка результата
    if ($new_user !== 0) {
        $user->set_property_key('session', 'login_id', $new_user);
        header('Location: ' . sprintf('%s?action=profile&subact=profile', SITE_URL));
        exit;
    }

    header('Location: ' . $redirect_url);
    exit;
} elseif ($subact === 'login') {
    // Проверяем, авторизован ли пользователь через сессию
    if ($work->check_input('_SESSION', 'login_id', [
        'isset'    => true,
        'empty'    => true,
        'regexp'   => '/^[0-9]+$/',
        'not_zero' => true,
    ])) {
        // Если пользователь уже авторизован, перенаправляем его на главную страницу
        header('Location: ' . SITE_URL);
        exit;
    }

    // Сбрасываем ID пользователя в сессии
    $user->set_property_key('session', 'login_id', 0);

    // Проверяем CSRF-токен
    if (!isset($_POST['csrf_token']) || !hash_equals($user->session['csrf_token'], $_POST['csrf_token'])) {
        throw new RuntimeException(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
        );
    }
    $user->unset_property_key('session', 'csrf_token'); // Удаляем использованный CSRF-токен из сессии

    // Вызываем метод login_user
    $user_id = $user->login_user($_POST, $redirect_url);

    // Авторизуем пользователя
    $user->set_property_key('session', 'login_id', $user_id);
    // Инициализация ядра для пользователя (включая гостя)
    [$user, $template] = Bootstrap::change_user($db, $work, $_SESSION);

    // Перенаправляем пользователя на целевую страницу
    header('Location: ' . $redirect_url);
    exit;
} elseif ($subact === 'profile') {
    // Проверка и установка $uid
    if ($work->check_input('_GET', 'uid', [
        'isset'    => true,
        'empty'    => false,
        'regexp'   => '/^[0-9]+$/',
        'not_zero' => true,
    ])) {
        $uid = (int)$_GET['uid'];
    } else {
        // Если $_GET['uid'] не прошёл проверку, используем ID из сессии
        if (!isset($user->session['login_id']) || $user->session['login_id'] === 0) {
            header('Location: ' . SITE_URL);
            exit;
        }
        $uid = (int)$user->session['login_id'];
    }
    // Проверка прав доступа
    $db->select(
        '*',
        TBL_USERS,
        [
            'where'  => '`id` = :id',
            'params' => [':id' => $uid],
        ]
    );
    $user_data = $db->result_row();
    if (!$user_data) {
        header('Location: ' . SITE_URL);
        exit;
    }
    if ($uid === $user->session['login_id'] || $user->user['admin']) {
        // Получение данных пользователя
        $name_block = $work->lang['profile']['edit_profile'] . ' ' . Work::clean_field($user_data['real_name']);
        $max_size_php = Work::return_bytes(ini_get('post_max_size'));
        $max_size = Work::return_bytes($work->config['max_file_size']);
        if ($max_size > $max_size_php) {
            $max_size = $max_size_php;
        }
        $confirm_password = ($uid === $user->session['login_id']);
        // Получение данных группы
        $db->select(
            '*',
            TBL_GROUP,
            [
                'where'  => '`id` = :id',
                'params' => [':id' => $user_data['group_id']],
            ]
        );
        $group_data = $db->result_row();
        if (!$group_data) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Отсутствует группа для пользователя | Группа {$user_data['group']} для пользователя $uid. Вызов от пользователя с ID: {$user->session['login_id']}"
            );
            $user_data['group_id'] = 0; // Устанавливаем группу по умолчанию (гости)
            $db->select(
                '*',
                TBL_GROUP,
                [
                    'where'  => '`id` = :id',
                    'params' => [':id' => $user_data['group_id']],
                ]
            );
            $group_data = $db->result_row();
            if (!$group_data) {
                throw new LogicException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные гостевой группы | Вызов от пользователя с ID: {$user->session['login_id']}"
                );
            }
        }
        // Вычисляем срок "мягкого" восстановления в случае если пользователь хочет удалить аккаунт
        $timezone_name = $user->session['timezone'];
        if (!$timezone_name || !in_array($timezone_name, DateTimeZone::listIdentifiers(), true)) {
            $timezone_name = 'UTC';
        }
        $now = new DateTime('now', new DateTimeZone($timezone_name));
        $restore_date = clone $now;
        $restore_date->modify('+' . SOFT_DELETE_RETENTION_INTERVAL);
        $formatted_restore_date = $restore_date->format('d.m.Y');
        unset($now, $restore_date);
        // Настройка шаблона для редактирования профиля
        $template->add_case('PROFILE_BLOCK', 'PROFILE_EDIT');
        /** @noinspection PhpUnusedLocalVariableInspection */
        $title = $name_block;
        $language = $work->get_languages();
        $themes = $work->get_themes();
        if ((int)$uid === (int)$user->session['login_id'] && empty($user->user['admin'])) {
            $can_delete = true;
        }
        $template->add_if('CAN_DELETE', $can_delete ?? false);
        $template->add_if('NEED_PASSWORD', $confirm_password);
        $template->add_if('SOFT_DELETE', false);
        // Проверяем, является ли текущий пользователь админом и мягко удален ли
        // просматриваемый пользователь и не окончательно
        if (!empty($user_data['deleted_at']) && (int)$user_data['permanently_deleted'] === 0 && !empty($user->user['admin'])) {
            /** @noinspection DuplicatedCode */
            $restore_info = $user->is_soft_delete_expired($user_data['deleted_at']);
            $template->add_if('SOFT_DELETE', true);
            $template->add_string_array([
                'L_DELETED_AT'   => $work->lang['profile']['user_deleted_at'],
                'L_RESTORE_EXPIRY' => $work->lang['profile']['user_restore_expiry'],
                'D_DELETED_AT'     => $restore_info['restore_date'] ?
                    $restore_info['restore_date']->format('d.m.Y') : $work->lang['main']['undefined'],
                'D_RESTORE_EXPIRY' => $restore_info['restore_expiry'] ?
                    $restore_info['restore_expiry']->format('d.m.Y') : $work->lang['main']['undefined'],
            ]);
        }
        $template->add_string('D_DISABLE_FIELD', ((int)$user_data['permanently_deleted'] === 0) ? '' : ' disabled');
        $template->add_string_array([
            'NAME_BLOCK'      => $name_block,
            'CSRF_TOKEN'      => $user->csrf_token(),
            'L_LOGIN'         => $work->lang['profile']['login'],
            'L_EDIT_PASSWORD' => $work->lang['profile']['password'],
            'L_RE_PASSWORD'   => $work->lang['profile']['re_password'],
            'L_EMAIL'         => $work->lang['profile']['email'],
            'L_REAL_NAME'     => $work->lang['profile']['real_name'],
            'L_PASSWORD'      => $work->lang['profile']['confirm_password'],
            'L_SAVE_PROFILE'  => $work->lang['profile']['save_profile'],
            'L_HELP_EDIT'     => $work->lang['profile']['help_edit'],
            'L_AVATAR'        => $work->lang['profile']['avatar'],
            'L_GROUP'         => $work->lang['main']['group'],
            'L_DELETE_AVATAR' => $work->lang['profile']['delete_avatar'],
            'L_CHANGE_LANG'   => $work->lang['admin']['language'],
            'L_CHANGE_THEME'  => $work->lang['admin']['themes'],
            'L_DELETE_BLOCK'         => $work->lang['profile']['soft_delete_user'],
            'L_CONFIRM_DELETE_BLOCK' => sprintf($work->lang['profile']['confirm_soft_delete_user'], $formatted_restore_date),
            'L_CONFIRM_DELETE'       => $work->lang['main']['delete'],
            'L_CANCEL_DELETE'        => $work->lang['main']['cancel'],
            'D_LOGIN'         => $user_data['login'],
            'D_EMAIL'         => $user_data['email'],
            'D_REAL_NAME'     => $user_data['real_name'],
            'D_MAX_FILE_SIZE' => (string)$max_size,
            'D_GROUP'         => Work::clean_field($group_data['name']),
            'U_AVATAR'        => AVATAR_URL . $user_data['avatar'],
            'U_PROFILE_EDIT'  => sprintf(
                '%s?action=profile&amp;subact=saveprofile&amp;uid=%d',
                SITE_URL,
                $uid
            ),
            'U_DELETE_BLOCK'  => sprintf(
                '%s?action=profile&amp;subact=delete_profile',
                SITE_URL
            ),
        ]);
        foreach ($language as $key => $val) {
            $template->add_string_array(
                [
                    'D_DIR_LANG'  => $val['value'],
                    'D_NAME_LANG' => $val['name'],
                ],
                'SELECT_LANGUAGE[' . $key . ']'
            );

            if ($val['value'] === $user_data['language']) {
                $template->add_string_array(
                    [
                        'D_SELECTED_LANG' => $val['value'],
                        'L_SELECTED_LANG' => $val['name'],
                    ]
                );
            }
        }
        foreach ($themes as $key => $val) {
            $template->add_string_array(
                [
                    'D_DIR_THEME'  => $val,
                    'D_NAME_THEME' => ucfirst($val),
                ],
                'SELECT_THEME[' . $key . ']'
            );

            if ($val === $user_data['theme']) {
                $template->add_string_array(
                    [
                        'D_SELECTED_THEME' => $val,
                        'L_SELECTED_THEME' => ucfirst($val),
                    ]
                );
            }
        }
    } else {
        // Получение данных пользователя для просмотра
        $name_block = $work->lang['profile']['profile'] . ' ' . Work::clean_field($user_data['real_name']);
        // Получение данных группы
        $db->select(
            '*',
            TBL_GROUP,
            [
                'where'  => '`id` = :id',
                'params' => [':id' => $user_data['group_id']],
            ]
        );
        $group_data = $db->result_row();
        if (!$group_data) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Отсутствует группа для пользователя | Группа {$user_data['group']} для пользователя $uid. Вызов от пользователя с ID: {$user->session['login_id']}"
            );
            $user_data['group_id'] = 0; // Устанавливаем группу по умолчанию (гости)
            $db->select(
                '*',
                TBL_GROUP,
                [
                    'where'  => '`id` = :id',
                    'params' => [':id' => $user_data['group_id']],
                ]
            );
            $group_data = $db->result_row();
            if (!$group_data) {
                throw new LogicException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные гостевой группы | Вызов от пользователя с ID: {$user->session['login_id']}"
                );
            }
        }
        // Настройка шаблона для просмотра профиля
        $template->add_case('PROFILE_BLOCK', 'PROFILE_VIEW');
        /** @noinspection PhpUnusedLocalVariableInspection */
        $title = $name_block;
        $template->add_string_array([
            'NAME_BLOCK'  => $name_block,
            'L_EMAIL'     => $work->lang['profile']['email'],
            'L_REAL_NAME' => $work->lang['profile']['real_name'],
            'L_AVATAR'    => $work->lang['profile']['avatar'],
            'L_GROUP'     => $work->lang['main']['group'],
            'D_EMAIL'     => $work->filt_email($user_data['email']),
            'D_REAL_NAME' => Work::clean_field($user_data['real_name']),
            'D_GROUP'     => Work::clean_field($group_data['name']),
            'U_AVATAR'    => AVATAR_URL . $user_data['avatar'],
        ]);
    }
} else {
    header('Location: ' . SITE_URL);
    exit;
}
