<?php

/**
 * @file        action/profile.php
 * @brief       Работа с пользователями: вход, выход, регистрация, редактирование и просмотр профиля.
 *
 * @details     Этот файл отвечает за управление профилями пользователей, включая:
 *              - Вход и выход пользователя.
 *              - Регистрацию новых пользователей.
 *              - Редактирование профиля (изменение аватара, языка, темы и других данных).
 *              - Просмотр профиля другого пользователя.
 *              - Защиту от CSRF-атак при изменении данных.
 *              - Логирование ошибок и подозрительных действий.
 *
 * @section     Основные функции
 * - Вход и выход пользователя.
 * - Регистрация новых пользователей.
 * - Редактирование профиля.
 * - Просмотр профиля другого пользователя.
 * - Логирование ошибок и подозрительных действий.
 *
 * @throws      RuntimeException Если не удалось выполнить действие с профилем (например, вход, выход, регистрация,
 *                               редактирование). Пример сообщения: "Неверный CSRF-токен | Пользователь ID:
 *                               {$user->session['login_id']}".
 * @throws      LogicException Если не удалось получить данные группы пользователя.
 *              Пример сообщения: "Не удалось получить данные гостевой группы | Вызов от пользователя с ID:
 *              {$user->session['login_id']}".
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-03-12
 * @namespace   PhotoRigma\Action
 *
 * @see         PhotoRigma::Classes::Work Класс используется для выполнения вспомогательных операций.
 * @see         PhotoRigma::Classes::Database Класс для работы с базой данных.
 * @see         PhotoRigma::Classes::User Класс для управления пользователями.
 * @see         PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
 * @see         file index.php Этот файл подключает action/profile.php по запросу из `$_GET`.
 *
 * @note        Этот файл является частью системы PhotoRigma.
 *              Реализованы меры безопасности для предотвращения несанкционированного доступа и выполнения действий.
 *              Используются подготовленные выражения для защиты от SQL-инъекций.
 *
 * @copyright   Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать, распространять,
 *              сублицензировать и/или продавать копии программного обеспечения, а также разрешать лицам, которым
 *              предоставляется данное программное обеспечение, делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые
 *              части программного обеспечения.
 */

namespace PhotoRigma\Action;

use LogicException;
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
    error_log(
        date('H:i:s') . " [ERROR] | " . (filter_input(
            INPUT_SERVER,
            'REMOTE_ADDR',
            FILTER_VALIDATE_IP
        ) ?: 'UNKNOWN_IP') . " | " . __FILE__ . " | Попытка прямого вызова файла"
    );
    die("HACK!");
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
$redirect_url = match (true) {
    !empty($_SERVER['HTTP_REFERER']) && filter_var(
        $_SERVER['HTTP_REFERER'],
        FILTER_VALIDATE_URL
    )       => $_SERVER['HTTP_REFERER'],
    default => $work->config['site_url'],
};

if ($subact === 'saveprofile') {
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
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
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
        $user_data = $db->res_row();
        if (!$user_data) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные пользователя | ID: $user_id"
            );
        }

        // Определяем максимальный размер файла для аватара
        $max_size_php = Work::return_bytes(ini_get('post_max_size'));
        $max_size = Work::return_bytes($work->config['max_file_size']);
        if ($max_size > $max_size_php) {
            $max_size = $max_size_php;
        }

        // Вызываем метод update_user_data для обновления данных пользователя
        $rows = $user->update_user_data($user_id, $_POST, $_FILES, $max_size);

        // Переинициируем класс User для обновления данных в сессии.
        $user = new User($db, $_SESSION);
        $work->set_user($user);
        $work->set_lang();
        $template->set_lang($work->lang);
        $template->set_work($work);
        header('Location: ' . $redirect_url);
        exit;
    }

    throw new RuntimeException(
        __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Отказано в праве редактирования пользователя | Редактируемый ID: $user_id, Текущий ID: {$user->session['login_id']}"
    );
}

if ($subact === 'logout') {
    if ($user->session['login_id'] === 0) {
        header('Location: ' . $redirect_url);
        exit;
    }

    // Обновление данных пользователя через плейсхолдеры
    $db->update([
        '`date_last_activ`'  => null,
        '`date_last_logout`' => date('Y-m-d H:i:s'),
    ], TBL_USERS, [
        'where'  => '`id` = :user_id',
        'params' => [':user_id' => $user->session['login_id']],
    ]);

    // Проверка успешности обновления
    $rows = $db->get_affected_rows();
    if ($rows === 0) {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось обновить данные пользователя при выходе | ID: {$user->session['login_id']}"
        );
    }

    // Безопасное завершение сессии
    $user->session['login_id'] = 0;
    $user->session['admin_on'] = false;

    // Регенерация session_id для безопасности
    session_regenerate_id(true);

    $user = new User($db, $_SESSION);
    $work->set_user($user);
    $work->set_lang();
    $template->set_lang($work->lang);
    $template->set_work($work);

    // Уничтожение сессии
    session_destroy();

    // Перенаправление и логирование
    header('Location: ' . $work->config['site_url']);
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
        header('Location: ' . $work->config['site_url']);
        exit;
    }

    // Устанавливаем блок шаблона для регистрации
    $template->add_case('PROFILE_BLOCK', 'REGIST');

    // Задаем заголовок страницы
    $title = $work->lang['profile']['regist'];
    $action = 'regist';

    // Генерируем CAPTCHA и сохраняем её ответ в сессии (хэшированный)
    $captcha = $work->gen_captcha();
    $user->session['captcha'] = password_hash($captcha['answer'], PASSWORD_BCRYPT);

    // Генерируем CSRF-токен для защиты от атак типа CSRF
    $template->add_string('CSRF_TOKEN', $user->csrf_token());

    // Инициализируем флаги ошибок для всех полей формы
    $template->add_if_ar([
        'ERROR_LOGIN'       => false,
        'ERROR_PASSWORD'    => false,
        'ERROR_RE_PASSWORD' => false,
        'ERROR_EMAIL'       => false,
        'ERROR_REAL_NAME'   => false,
        'ERROR_CAPTCHA'     => false,
    ]);

    // Добавляем строки для шаблона, включая метки, URL и данные CAPTCHA
    $template->add_string_ar([
        'NAME_BLOCK'         => $work->lang['profile']['regist'],
        'L_LOGIN'            => $work->lang['profile']['login'],
        'L_PASSWORD'         => $work->lang['profile']['password'],
        'L_RE_PASSWORD'      => $work->lang['profile']['re_password'],
        'L_EMAIL'            => $work->lang['profile']['email'],
        'L_REAL_NAME'        => $work->lang['profile']['real_name'],
        'L_REGISTER'         => $work->lang['profile']['register'],
        'L_CAPTCHA'          => $work->lang['profile']['captcha'],
        'U_REGISTER'         => sprintf('%s?action=profile&subact=register', $work->config['site_url']),
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
            $template->add_string_ar([
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
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
        );
    }
    $user->unset_property_key('session', 'csrf_token');

    // Вызываем метод add_user_data
    $new_user = $user->add_new_user($_POST, $redirect_url);

    // Обработка результата
    if ($new_user !== 0) {
        $user->session['login_id'] = $new_user;
        header('Location: ' . sprintf('%s?action=profile&subact=profile', $work->config['site_url']));
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
        header('Location: ' . $work->config['site_url']);
        exit;
    }

    // Сбрасываем ID пользователя в сессии
    $user->session['login_id'] = 0;

    // Проверяем CSRF-токен
    if (!isset($_POST['csrf_token']) || !hash_equals($user->session['csrf_token'], $_POST['csrf_token'])) {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
        );
    }
    $user->unset_property_key('session', 'csrf_token'); // Удаляем использованный CSRF-токен из сессии

    // Вызываем метод login_user
    $user_id = $user->login_user($_POST, $redirect_url);

    // Авторизуем пользователя
    $user->session['login_id'] = $user_id; // Устанавливаем ID или 0, если пользователь не найден
    // Инициализация ядра для пользователя (включая гостя)
    $user = new User($db, $_SESSION);
    $work->set_user($user);
    $work->set_lang();
    $template->set_lang($work->lang);
    $template->set_work($work);

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
            header('Location: ' . $work->config['site_url']);
            exit;
        }
        $uid = (int)$user->session['login_id'];
    }
    // Проверка прав доступа
    if ($uid === $user->session['login_id'] || $user->user['admin']) {
        // Получение данных пользователя
        $db->select(
            '*',
            TBL_USERS,
            [
                'where'  => '`id` = :id',
                'params' => [':id' => $uid],
            ]
        );
        $user_data = $db->res_row();
        if (!$user_data) {
            header('Location: ' . $work->config['site_url']);
            exit;
        }
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
        $group_data = $db->res_row();
        if (!$group_data) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Отсутствует группа для пользователя | Группа {$user_data['group']} для пользователя $uid. Вызов от пользователя с ID: {$user->session['login_id']}"
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
            $group_data = $db->res_row();
            if (!$group_data) {
                throw new LogicException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные гостевой группы | Вызов от пользователя с ID: {$user->session['login_id']}"
                );
            }
        }
        // Настройка шаблона для редактирования профиля
        $template->add_case('PROFILE_BLOCK', 'PROFILE_EDIT');
        $title = $name_block;
        $language = $work->get_languages();
        $themes = $work->get_themes();
        $template->add_if('NEED_PASSWORD', $confirm_password);
        $template->add_string_ar([
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
            'D_LOGIN'         => $user_data['login'],
            'D_EMAIL'         => $user_data['email'],
            'D_REAL_NAME'     => $user_data['real_name'],
            'D_MAX_FILE_SIZE' => (string)$max_size,
            'D_GROUP'         => Work::clean_field($group_data['name']),
            'U_AVATAR'        => sprintf(
                '%s%s/%s',
                $work->config['site_url'],
                $work->config['avatar_folder'],
                $user_data['avatar']
            ),
            'U_PROFILE_EDIT'  => sprintf(
                '%s?action=profile&amp;subact=saveprofile&amp;uid=%d',
                $work->config['site_url'],
                $uid
            ),
        ]);
        foreach ($language as $key => $val) {
            $template->add_string_ar(
                [
                    'D_DIR_LANG'  => $val['value'],
                    'D_NAME_LANG' => $val['name'],
                ],
                'SELECT_LANGUAGE[' . $key . ']'
            );

            if ($val['value'] === $user_data['language']) {
                $template->add_string_ar(
                    [
                        'D_SELECTED_LANG' => $val['value'],
                        'L_SELECTED_LANG' => $val['name'],
                    ]
                );
            }
        }
        foreach ($themes as $key => $val) {
            $template->add_string_ar(
                [
                    'D_DIR_THEME'  => $val,
                    'D_NAME_THEME' => ucfirst($val),
                ],
                'SELECT_THEME[' . $key . ']'
            );

            if ($val === $user_data['theme']) {
                $template->add_string_ar(
                    [
                        'D_SELECTED_THEME' => $val,
                        'L_SELECTED_THEME' => ucfirst($val),
                    ]
                );
            }
        }
    } else {
        // Получение данных пользователя для просмотра
        $db->select(
            '*',
            TBL_USERS,
            [
                'where'  => '`id` = :id',
                'params' => [':id' => $uid],
            ]
        );
        $user_data = $db->res_row();
        if (!$user_data) {
            header('Location: ' . $work->config['site_url']);
            exit;
        }
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
        $group_data = $db->res_row();
        if (!$group_data) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Отсутствует группа для пользователя | Группа {$user_data['group']} для пользователя $uid. Вызов от пользователя с ID: {$user->session['login_id']}"
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
            $group_data = $db->res_row();
            if (!$group_data) {
                throw new LogicException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные гостевой группы | Вызов от пользователя с ID: {$user->session['login_id']}"
                );
            }
        }
        // Настройка шаблона для просмотра профиля
        $template->add_case('PROFILE_BLOCK', 'PROFILE_VIEW');
        $title = $name_block;
        $template->add_string_ar([
            'NAME_BLOCK'  => $name_block,
            'L_EMAIL'     => $work->lang['profile']['email'],
            'L_REAL_NAME' => $work->lang['profile']['real_name'],
            'L_AVATAR'    => $work->lang['profile']['avatar'],
            'L_GROUP'     => $work->lang['main']['group'],
            'D_EMAIL'     => $work->filt_email($user_data['email']),
            'D_REAL_NAME' => Work::clean_field($user_data['real_name']),
            'D_GROUP'     => Work::clean_field($group_data['name']),
            'U_AVATAR'    => sprintf(
                '%s%s/%s',
                $work->config['site_url'],
                $work->config['avatar_folder'],
                $user_data['avatar']
            ),
        ]);
    }
} else {
    header('Location: ' . $work->config['site_url']);
    exit;
}
