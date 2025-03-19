<?php

/**
 * @file        action/admin.php
 * @brief       Администрирование сайта.
 *
 * @details     Этот файл отвечает за управление настройками сайта, пользователями и группами. Основные функции включают:
 *              - Управление общими настройками сайта (название, описание, мета-теги, размеры изображений и т.д.).
 *              - Управление пользователями (редактирование прав, групп, поиск пользователей).
 *              - Управление группами (редактирование прав групп, создание и удаление групп).
 *              - Защита от несанкционированного доступа к админке (CSRF-токены, проверка прав доступа).
 *              - Логирование ошибок и подозрительных действий.
 *
 * @section     Основные функции
 * - Управление общими настройками сайта.
 * - Управление пользователями (редактирование, поиск, изменение прав).
 * - Управление группами (редактирование, изменение прав).
 * - Логирование ошибок и подозрительных действий.
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
 *
 * @note        Этот файл является частью системы PhotoRigma.
 *              Реализованы меры безопасности для предотвращения несанкционированного доступа и выполнения действий.
 *              Используются подготовленные выражения для защиты от SQL-инъекций.
 *
 * @copyright   Copyright (c) 2025 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать, распространять, сублицензировать
 *              и/или продавать копии программного обеспечения, а также разрешать лицам, которым предоставляется данное
 *              программное обеспечение, делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые части
 *                программного обеспечения.
 */

namespace PhotoRigma\Action;

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

// Устанавливаем шаблон для административной панели
$template->template_file = 'admin.html';

// Инициализация флагов для шаблона
$template->add_if_ar([
    'SESSION_ADMIN_ON' => false, // Флаг активности сессии администратора
    'NEED_FIND' => false,        // Флаг необходимости поиска
    'NEED_USER' => false,        // Флаг необходимости отображения пользователей
    'FIND_USER' => false,        // Флаг найденных пользователей
    'SELECT_GROUP' => false,     // Флаг выбора группы
    'EDIT_GROUP' => false        // Флаг редактирования группы
]);

// Проверяем условия для входа в административную панель
if (// Если сессия администратора не активна
    (!$work->check_input(
        '_SESSION',
        'admin_on',
        ['isset' => true]
    ) || !$user->session['admin_on']) && // И пользователь имеет права администратора
    $user->user['admin'] && // И был передан пароль через POST-запрос
    $work->check_input('_POST', 'admin_password', ['isset' => true, 'empty' => true])) {
    // Инициализация данных о попытках входа в сессии
    if (!isset($user->session['login_attempts'])) {
        $user->session['login_attempts'] = 0;
    }
    if (!isset($user->session['last_attempt_time'])) {
        $user->session['last_attempt_time'] = 0;
    }

    // Текущее время
    $current_time = time();

    // Проверяем, прошло ли достаточно времени с момента последней попытки
    if ($current_time - $user->session['last_attempt_time'] > LOCKOUT_TIME) {
        // Сбрасываем счетчик попыток, если время истекло
        $user->session['login_attempts'] = 0;
    }

    // Проверяем, не превышено ли количество попыток
    if ($user->session['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        // Логируем блокировку
        log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Пользователь заблокирован из-за превышения попыток входа | ID пользователя: {$user->user['id']}"
        );

        // Выводим сообщение об ошибке и завершаем выполнение
        die(sprintf($work->lang['lockout_time'], LOCKOUT_TIME / 60));
    }

    // Проверяем пароль
    if (password_verify($_POST['admin_password'], $user->user['password'])) {
        // Открываем сессию для администратора
        $user->session['admin_on'] = true;

        // Сбрасываем счетчик попыток при успешном входе
        $user->session['login_attempts'] = 0;

        // Логируем успешный вход
        log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Администратор успешно вошел в систему | ID пользователя: {$user->user['id']}"
        );
    } else {
        // Увеличиваем счетчик неудачных попыток
        $user->session['login_attempts']++;
        $user->session['last_attempt_time'] = $current_time;

        // Логируем неудачную попытку входа
        log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неудачная попытка входа в административную панель | ID пользователя: {$user->user['id']}"
        );

        // Перенаправляем пользователя на главную страницу
        header('Location: ' . $work->config['site_url']);
        exit;
    }
}

if (!empty($user->user['admin']) && $work->check_input(
    '_SESSION',
    'admin_on',
    ['isset' => true]
) && $user->session['admin_on']) {
    if ($work->check_input('_GET', 'subact', ['isset' => true, 'empty' => true]) && $_GET['subact'] === 'settings') {
        if ($work->check_input('_POST', 'submit', ['isset' => true, 'empty' => true])) {
            // Массив правил для обработки настроек
            $config_rules = [
                'title_name' => ['regexp' => REG_NAME],
                'title_description' => ['regexp' => REG_NAME],
                'meta_description' => ['regexp' => REG_NAME],
                'meta_keywords' => ['regexp' => REG_NAME],
                'gal_width' => ['regexp' => '/^([0-9]+)(\%){0,1}$/'],
                'left_panel' => ['regexp' => '/^([0-9]+)(\%){0,1}$/'],
                'right_panel' => ['regexp' => '/^([0-9]+)(\%){0,1}$/'],
                'language' => [
                    'regexp' => REG_LOGIN,
                    'condition' => static function () use ($work) {
                        return is_dir($work->config['site_dir'] . 'language/' . $_POST['language']);
                    }
                ],
                'themes' => [
                    'regexp' => REG_LOGIN,
                    'condition' => static function () use ($work) {
                        return is_dir($work->config['site_dir'] . 'themes/' . $_POST['themes']);
                    }
                ],
                'max_file_size' => ['regexp' => '/^[0-9]+$/'],
                'max_file_size_letter' => ['regexp' => '/^(B|K|M|G)$/'],
                'max_photo_w' => ['regexp' => '/^[0-9]+$/'],
                'max_photo_h' => ['regexp' => '/^[0-9]+$/'],
                'temp_photo_w' => ['regexp' => '/^[0-9]+$/'],
                'temp_photo_h' => ['regexp' => '/^[0-9]+$/'],
                'max_avatar_w' => ['regexp' => '/^[0-9]+$/'],
                'max_avatar_h' => ['regexp' => '/^[0-9]+$/'],
                'copyright_year' => ['regexp' => '/^[0-9\-]+$/'],
                'copyright_text' => ['regexp' => REG_NAME],
                'copyright_url' => ['regexp' => '/^.+$/'],
                'last_news' => ['regexp' => '/^[0-9]+$/'],
                'best_user' => ['regexp' => '/^[0-9]+$/'],
                'max_rate' => ['regexp' => '/^[0-9]+$/'],
                'time_user_online' => ['regexp' => '/^[0-9]+$/'],
            ];

            // Новый конфиг
            $new_config = [];

            foreach ($config_rules as $name => $rule) {
                if ($work->check_input(
                    '_POST',
                    $name,
                    ['isset' => true, 'empty' => true, 'regexp' => $rule['regexp'] ?? null]
                )) {
                    if (isset($rule['condition']) && !$rule['condition']()) {
                        continue; // Пропускаем, если дополнительное условие не выполнено
                    }

                    // Обработка max_file_size и max_file_size_letter
                    if ($name === 'max_file_size' && isset($_POST['max_file_size_letter'])) {
                        $letter = $_POST['max_file_size_letter'];
                        $value = in_array(
                            $letter,
                            ['B', 'K', 'M', 'G'],
                            true
                        ) ? $_POST[$name] . $letter : $_POST[$name];
                    } else {
                        $value = Work::clean_field($_POST[$name]);
                    }

                    // Проверяем, отличается ли новое значение от текущего
                    if ($work->config[$name] !== $value) {
                        $new_config[$name] = $value;
                    }
                }
            }

            unset($new_config['max_file_size_letter']);

            // Обновление конфигурации в базе данных
            foreach ($new_config as $name => $value) {
                // Используем новый формат вызова update()
                $db->update(
                    ['value' => ':value'], // Данные для обновления
                    TBL_CONFIG, // Таблица
                    [
                        'where' => '`name` = :name', // Условие WHERE
                        'params' => [':value' => $value, ':name' => $name] // Параметры для prepared statements
                    ]
                );

                // Проверяем количество затронутых строк
                $rows = $db->get_affected_rows();
                if ($rows > 0) {
                    $work->config[$name] = $value; // Обновляем конфиг в памяти
                } else {
                    log_in_file("Ошибка при обновлении настройки: $name");
                }
            }
        }

        // Обработка max_file_size
        $max_file_size = trim($work->config['max_file_size']);
        $max_file_size_letter = strtolower($max_file_size[strlen($max_file_size) - 1]);
        if (!in_array($max_file_size_letter, ['k', 'm', 'g'], true)) {
            $max_file_size_letter = 'b';
        } else {
            $max_file_size = substr($max_file_size, 0, -1);
        }

        // Получение языков и тем
        $language = $work->get_languages();
        $themes = $work->get_themes();

        // Добавление данных в шаблон
        $template->add_case('ADMIN_BLOCK', 'ADMIN_SETTINGS');
        $template->add_string_ar([
            'L_NAME_BLOCK' => $work->lang['admin']['title'] . ' - ' . $work->lang['admin']['settings'],
            'L_MAIN_SETTINGS' => $work->lang['admin']['main_settings'],
            'L_TITLE_NAME' => $work->lang['admin']['title_name'],
            'L_TITLE_NAME_DESCRIPTION' => $work->lang['admin']['title_name_description'],
            'L_TITLE_DESCRIPTION' => $work->lang['admin']['title_description'],
            'L_TITLE_DESCRIPTION_DESCRIPTION' => $work->lang['admin']['title_description_description'],
            'L_META_DESCRIPTION' => $work->lang['admin']['meta_description'],
            'L_META_DESCRIPTION_DESCRIPTION' => $work->lang['admin']['meta_description_description'],
            'L_META_KEYWORDS' => $work->lang['admin']['meta_keywords'],
            'L_META_KEYWORDS_DESCRIPTION' => $work->lang['admin']['meta_keywords_description'],
            'L_APPEARANCE_SETTINGS' => $work->lang['admin']['appearance_settings'],
            'L_GAL_WIDTH' => $work->lang['admin']['gal_width'],
            'L_GAL_WIDTH_DESCRIPTION' => $work->lang['admin']['gal_width_description'],
            'L_LEFT_PANEL' => $work->lang['admin']['left_panel'],
            'L_LEFT_PANEL_DESCRIPTION' => $work->lang['admin']['left_panel_description'],
            'L_RIGHT_PANEL' => $work->lang['admin']['right_panel'],
            'L_RIGHT_PANEL_DESCRIPTION' => $work->lang['admin']['right_panel_description'],
            'L_LANGUAGE' => $work->lang['admin']['language'],
            'L_LANGUAGE_DESCRIPTION' => $work->lang['admin']['language_description'],
            'L_THEMES' => $work->lang['admin']['themes'],
            'L_THEMES_DESCRIPTION' => $work->lang['admin']['themes_description'],
            'L_SIZE_SETTINGS' => $work->lang['admin']['size_settings'],
            'L_MAX_FILE_SIZE' => $work->lang['admin']['max_file_size'],
            'L_MAX_FILE_SIZE_DESCRIPTION' => $work->lang['admin']['max_file_size_description'],
            'L_MAX_PHOTO' => $work->lang['admin']['max_photo'],
            'L_MAX_PHOTO_DESCRIPTION' => $work->lang['admin']['max_photo_description'],
            'L_TEMP_PHOTO' => $work->lang['admin']['temp_photo'],
            'L_TEMP_PHOTO_DESCRIPTION' => $work->lang['admin']['temp_photo_description'],
            'L_MAX_AVATAR' => $work->lang['admin']['max_avatar'],
            'L_MAX_AVATAR_DESCRIPTION' => $work->lang['admin']['max_avatar_description'],
            'L_COPYRIGHT_SETTINGS' => $work->lang['admin']['copyright_settings'],
            'L_COPYRIGHT_YEAR' => $work->lang['admin']['copyright_year'],
            'L_COPYRIGHT_YEAR_DESCRIPTION' => $work->lang['admin']['copyright_year_description'],
            'L_COPYRIGHT_TEXT' => $work->lang['admin']['copyright_text'],
            'L_COPYRIGHT_TEXT_DESCRIPTION' => $work->lang['admin']['copyright_text_description'],
            'L_COPYRIGHT_URL' => $work->lang['admin']['copyright_url'],
            'L_COPYRIGHT_URL_DESCRIPTION' => $work->lang['admin']['copyright_url_description'],
            'L_ADDITIONAL_SETTINGS' => $work->lang['admin']['additional_settings'],
            'L_LAST_NEWS' => $work->lang['admin']['last_news'],
            'L_LAST_NEWS_DESCRIPTION' => $work->lang['admin']['last_news_description'],
            'L_BEST_USER' => $work->lang['admin']['best_user'],
            'L_BEST_USER_DESCRIPTION' => $work->lang['admin']['best_user_description'],
            'L_MAX_RATE' => $work->lang['admin']['max_rate'],
            'L_MAX_RATE_DESCRIPTION' => $work->lang['admin']['max_rate_description'],
            'L_TIME_ONLINE' => $work->lang['admin']['time_online'],
            'L_TIME_ONLINE_DESCRIPTION' => $work->lang['admin']['time_online_description'],
            'L_SAVE_SETTINGS' => $work->lang['admin']['save_settings'],
            'D_TITLE_NAME' => $work->config['title_name'],
            'D_TITLE_DESCRIPTION' => $work->config['title_description'],
            'D_META_DESCRIPTION' => $work->config['meta_description'],
            'D_META_KEYWORDS' => $work->config['meta_keywords'],
            'D_GAL_WIDTH' => $work->config['gal_width'],
            'D_LEFT_PANEL' => $work->config['left_panel'],
            'D_RIGHT_PANEL' => $work->config['right_panel'],
            'D_MAX_FILE_SIZE' => $max_file_size,
            'D_SEL_B' => $max_file_size_letter === 'b' ? ' selected="selected"' : '',
            'D_SEL_K' => $max_file_size_letter === 'k' ? ' selected="selected"' : '',
            'D_SEL_M' => $max_file_size_letter === 'm' ? ' selected="selected"' : '',
            'D_SEL_G' => $max_file_size_letter === 'g' ? ' selected="selected"' : '',
            'D_MAX_PHOTO_W' => $work->config['max_photo_w'],
            'D_MAX_PHOTO_H' => $work->config['max_photo_h'],
            'D_TEMP_PHOTO_W' => $work->config['temp_photo_w'],
            'D_TEMP_PHOTO_H' => $work->config['temp_photo_h'],
            'D_MAX_AVATAR_W' => $work->config['max_avatar_w'],
            'D_MAX_AVATAR_H' => $work->config['max_avatar_h'],
            'D_COPYRIGHT_YEAR' => $work->config['copyright_year'],
            'D_COPYRIGHT_TEXT' => $work->config['copyright_text'],
            'D_COPYRIGHT_URL' => $work->config['copyright_url'],
            'D_LAST_NEWS' => $work->config['last_news'],
            'D_BEST_USER' => $work->config['best_user'],
            'D_MAX_RATE' => $work->config['max_rate'],
            'D_TIME_ONLINE' => $work->config['time_user_online']
        ]);

        // Добавление языков в шаблон
        foreach ($language as $key => $val) {
            $template->add_string_ar([
                'D_DIR_LANG' => $val['value'],
                'D_NAME_LANG' => $val['name'],
                'D_SELECTED_LANG' => $val['value'] === $work->config['language'] ? ' selected="selected"' : ''
            ], 'SELECT_LANGUAGE[' . $key . ']');
        }

        // Добавление тем в шаблон
        foreach ($themes as $key => $val) {
            $template->add_string_ar([
                'D_DIR_THEMES' => $val,
                'D_SELECTED_THEMES' => $val === $work->config['themes'] ? ' selected="selected"' : ''
            ], 'SELECT_THEMES[' . $key . ']');
        }

        // Инициализация переменных для ядра
        $action = ''; // Переменная для действия (используется в ядре)
        $title = $work->lang['admin']['settings']; // Заголовок страницы (используется в ядре)
    } elseif ($work->check_input(
        '_GET',
        'subact',
        ['isset' => true, 'empty' => true]
    ) && $_GET['subact'] === 'admin_user') {
        // Добавление блока для администрирования пользователей
        $template->add_case('ADMIN_BLOCK', 'ADMIN_USER');
        $template->add_string(
            'L_NAME_BLOCK',
            $work->lang['admin']['title'] . ' - ' . $work->lang['admin']['admin_user']
        );

        if ($work->check_input('_GET', 'uid', [
            'isset' => true,
            'empty' => true,
            'regexp' => '/^[0-9]+$/',
            'not_zero' => true
        ])) {
            // Проверяем существование пользователя с указанным uid
            $db->select('*', TBL_USERS, [
                'where' => '`id` = :uid',
                'params' => [':uid' => $_GET['uid']],
            ]);
            $user_data = $db->res_row();

            if ($user_data) {
                // Получаем поля прав доступа пользователя
                $user_permission_fields = $user->user_right_fields['all'];
                $user_data = array_merge($user_data, $user->process_user_rights($user_data['user_rights']));

                if ($work->check_input('_POST', 'submit', ['isset' => true, 'empty' => true])) {
                    // Обновление группы пользователя
                    if ($work->check_input('_POST', 'group', [
                            'isset' => true,
                            'empty' => true,
                            'regexp' => '/^[0-9]+$/',
                            'not_zero' => true
                        ]) && (int)$_POST['group'] !== $user_data['group_id']) {
                        $query = ['group_id' => $_POST['group']];
                        $new_user_data = $user_data;

                        // Получаем данные новой группы
                        $db->select('`id`, `user_rights`', TBL_GROUP, [
                            'where' => '`id` = :group_id',
                            'params' => [':group_id' => $_POST['group']],
                        ]);
                        $group_data = $db->res_row();

                        if ($group_data) {
                            foreach ($group_data as $key => $value) {
                                if ($key === 'id') {
                                    $new_user_data['group_id'] = $value;
                                } else {
                                    $query[$key] = $value;
                                    $new_user_data[$key] = $value;
                                }
                            }

                            // Обновляем данные пользователя в БД
                            $db->update($query, TBL_USERS, [
                                'where' => '`id` = :uid',
                                'params' => [':uid' => $_GET['uid']],
                            ]);
                            $rows = $db->get_affected_rows();

                            if ($rows > 0) {
                                $processed_rights = $user->process_user_rights($new_user_data['user_rights']);
                                unset($new_user_data['user_rights']);
                                $user_data = array_merge($new_user_data, $processed_rights);
                            } else {
                                throw new RuntimeException(
                                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при обновлении данных пользователя | ID пользователя: {$_GET['uid']}"
                                );
                            }
                        } else {
                            throw new RuntimeException(
                                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные группы | ID группы: {$_POST['group']}"
                            );
                        }
                    } else {
                        // Обновление прав доступа пользователя
                        $new_user_rights = [];

                        foreach ($user_permission_fields as $field) {
                            if ($work->check_input('_POST', $field, ['isset' => true])) {
                                $_POST[$field] = in_array($_POST[$field], ['on', 1, true], true);
                            } else {
                                $_POST[$field] = false;
                            }

                            $new_user_rights[$field] = $_POST[$field];
                        }

                        $encoded_user_rights = $user->encode_user_rights($new_user_rights);

                        $db->update(
                            ['user_rights' => $encoded_user_rights],
                            TBL_USERS,
                            [
                                'where' => '`id` = :uid',
                                'params' => [':uid' => $_GET['uid']],
                            ]
                        );

                        $rows = $db->get_affected_rows();

                        if ($rows > 0) {
                            $user_data = array_merge($user_data, $new_user_rights); // Обновляем данные в памяти
                        } else {
                            throw new RuntimeException(
                                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при обновлении данных пользователя | ID пользователя: {$_GET['uid']}"
                            );
                        }
                    }
                }

                // Получаем список групп для отображения в шаблоне
                $db->select('*', TBL_GROUP, [
                    'where' => '`id` != 0',
                ]);
                $groups = $db->res_arr();

                if ($groups) {
                    foreach ($groups as $key => $group) {
                        $template->add_string_ar([
                            'D_GROUP_ID' => (string)$group['id'],
                            'D_GROUP_NAME' => $group['name'],
                            'D_GROUP_SELECTED' => $group['id'] === $user_data['group_id'] ? ' selected="selected"' : '',
                        ], 'GROUP_USER[' . $key . ']');
                    }

                    // Добавляем права доступа в шаблон
                    foreach ($user_permission_fields as $field) {
                        $template->add_string('L_' . strtoupper($field), $work->lang['admin'][$field]);
                        $template->add_string(
                            'D_' . strtoupper($field),
                            $user_data[$field] ? ' checked="checked"' : ''
                        );
                    }

                    // Добавляем основные данные пользователя в шаблон
                    $template->add_if('FIND_USER', true);
                    $template->add_string_ar([
                        'L_LOGIN' => $work->lang['admin']['login'],
                        'L_EMAIL' => $work->lang['admin']['email'],
                        'L_REAL_NAME' => $work->lang['admin']['real_name'],
                        'L_AVATAR' => $work->lang['admin']['avatar'],
                        'L_GROUP' => $work->lang['main']['group'],
                        'L_USER_RIGHTS' => $work->lang['admin']['user_rights'],
                        'L_HELP_EDIT' => $work->lang['admin']['help_edit_user'],
                        'L_SAVE_USER' => $work->lang['admin']['save_user'],
                        'D_LOGIN' => $user_data['login'],
                        'D_EMAIL' => $user_data['email'],
                        'D_REAL_NAME' => $user_data['real_name'],
                        'U_AVATAR' => $work->config['site_url'] . $work->config['avatar_folder'] . '/' . $user_data['avatar'],
                    ]);
                } else {
                    throw new RuntimeException(
                        __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные группы"
                    );
                }
            } else {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные пользователя | ID пользователя: {$_GET['uid']}"
                );
            }
        } else {
            // Поиск пользователей
            if ($work->check_input('_POST', 'submit', ['isset' => true, 'empty' => true]) && $work->check_input(
                '_POST',
                'search_user',
                ['isset' => true, 'empty' => true]
            )) {
                $search_query = $_POST['search_user'] === '*' ? '%' : "%" . Work::clean_field(
                    $_POST['search_user']
                ) . "%";

                // Выполняем запрос к БД
                $db->select('*', TBL_USERS, [
                    'where' => '(real_name LIKE :real_name_query OR email LIKE :email_query OR login LIKE :login_query)',
                    'params' => [
                        ':real_name_query' => $search_query,
                        ':email_query' => $search_query,
                        ':login_query' => $search_query,
                    ],
                ]);
                $found_users = $db->res_arr();

                if ($found_users) {
                    foreach ($found_users as $key => $user) {
                        $template->add_string_ar([
                            'D_FIND_USER' => $user['real_name'],
                            'U_FIND_USER' => sprintf(
                                "%s?action=admin&amp;subact=admin_user&amp;uid=%s",
                                $work->config['site_url'],
                                $user['id']
                            ),
                        ], 'FIND_USER[' . $key . ']');
                    }
                } else {
                    $template->add_string_ar([
                        'D_FIND_USER' => $work->lang['admin']['no_find_user'],
                        'U_FIND_USER' => '#',
                    ], 'FIND_USER[0]');
                }

                $template->add_if('NEED_USER', true);
                $_POST['search_user'] = $search_query === '%' ? '*' : $search_query;
            }

            // Добавляем форму поиска в шаблон
            $template->add_if('NEED_FIND', true);
            $template->add_string_ar([
                'L_SEARCH_USER' => $work->lang['admin']['search_user'],
                'L_HELP_SEARCH' => $work->lang['admin']['help_search_user'],
                'L_SEARCH' => $work->lang['main']['search'],
                'D_SEARCH_USER' => $work->check_input(
                    '_POST',
                    'search_user',
                    ['isset' => true, 'empty' => true]
                ) ? Work::clean_field($_POST['search_user']) : '',
            ]);
        }

        // Инициализация переменных для ядра
        $action = '';
        $title = $work->lang['admin']['admin_user'];
    } elseif ($work->check_input(
        '_GET',
        'subact',
        ['isset' => true, 'empty' => true]
    ) && $_GET['subact'] === 'admin_group') {
        // Добавляем блок для администрирования групп
        $template->add_case('ADMIN_BLOCK', 'ADMIN_GROUP');
        $template->add_string(
            'L_NAME_BLOCK',
            $work->lang['admin']['title'] . ' - ' . $work->lang['admin']['admin_group']
        );
        $group_permission_fields = $user->user_right_fields['all'];

        if ($work->check_input('_POST', 'submit', ['isset' => true, 'empty' => true]) && $work->check_input(
            '_POST',
            'id_group',
            ['isset' => true, 'regexp' => '/^[0-9]+$/']
        )) {
            // Выбираем данные группы по ID
            $db->select('*', TBL_GROUP, [
                'where' => '`id` = :id_group',
                'params' => [':id_group' => $_POST['id_group']],
            ]);
            $group_data = $db->res_row();

            if ($group_data) {
                // Обновляем название группы
                if ($work->check_input(
                    '_POST',
                    'name_group',
                    ['isset' => true, 'empty' => true, 'regexp' => REG_NAME]
                ) && $_POST['name_group'] !== $group_data['name']) {
                    $clean_name = Work::clean_field($_POST['name_group']);
                    $db->update(
                        ['name' => ':name'],
                        TBL_GROUP,
                        [
                            'where' => '`id` = :id_group',
                            'params' => [
                                ':name' => $clean_name,
                                ':id_group' => $_POST['id_group'],
                            ],
                        ]
                    );
                    $rows = $db->get_affected_rows();
                    if ($rows > 0) {
                        $group_data['name'] = $clean_name;
                    }
                }

                $new_group_rights = [];

                // Обновляем права доступа группы
                foreach ($group_permission_fields as $value) {
                    if ($work->check_input('_POST', $value, ['isset' => true, 'empty' => true])) {
                        $_POST[$value] = in_array($_POST[$value], ['on', 1, '1', true], true);
                    } else {
                        $_POST[$value] = false;
                    }
                    $new_group_rights[$value] = $_POST[$value];
                }

                $encoded_group_rights = $user->encode_user_rights($new_group_rights);

                $db->update(
                    ['user_rights' => ':user_rights'],
                    TBL_GROUP,
                    [
                        'where' => '`id` = :id_group',
                        'params' => [
                            ':user_rights' => $encoded_group_rights,
                            ':id_group' => $_POST['id_group'],
                        ],
                    ]
                );
                $rows = $db->get_affected_rows();
                if ($rows > 0) {
                    $group_data = array_merge($group_data, $new_group_rights);
                } else {
                    $group_data = array_merge($group_data, $user->process_user_rights($group_data['user_rights']));
                }
                if (isset($group_data['user_rights'])) {
                    unset($group_data['user_rights']);
                }
                $_POST['group'] = $_POST['id_group'];
            }
        }

        if ($work->check_input('_POST', 'submit', ['isset' => true, 'empty' => true]) && $work->check_input(
            '_POST',
            'group',
            ['isset' => true, 'regexp' => '/^[0-9]+$/']
        )) {
            // Выбираем данные группы по ID
            $db->select('*', TBL_GROUP, [
                'where' => '`id` = :group_id',
                'params' => [':group_id' => $_POST['group']],
            ]);
            $group_data = $db->res_row();

            if ($group_data) {
                $group_data = array_merge($group_data, $user->process_user_rights($group_data['user_rights']));
                if (isset($group_data['user_rights'])) {
                    unset($group_data['user_rights']);
                }

                // Добавляем права доступа в шаблон
                foreach ($group_permission_fields as $value) {
                    $template->add_string('L_' . strtoupper($value), $work->lang['admin'][$value]);
                    $template->add_string(
                        'D_' . strtoupper($value),
                        $group_data[$value] ? ' checked="checked"' : ''
                    );
                }

                // Добавляем основные данные группы в шаблон
                $template->add_if('EDIT_GROUP', true);
                $template->add_string_ar([
                    'L_NAME_GROUP' => $work->lang['main']['group'],
                    'L_GROUP_RIGHTS' => $work->lang['admin']['group_rights'],
                    'L_SAVE_GROUP' => $work->lang['admin']['save_group'],

                    'D_ID_GROUP' => (string)$group_data['id'],
                    'D_NAME_GROUP' => $group_data['name'],
                ]);
            }
        } elseif ($db->select('*', TBL_GROUP)) {
            // Получаем список всех групп
            $groups = $db->res_arr();

            if ($groups) {
                foreach ($groups as $key => $group) {
                    $template->add_string_ar([
                        'D_ID_GROUP' => (string)$group['id'],
                        'D_NAME_GROUP' => $group['name'],
                    ], 'SELECT_GROUP[' . $key . ']');
                }

                // Добавляем форму выбора группы в шаблон
                $template->add_if('SELECT_GROUP', true);
                $template->add_string_ar([
                    'L_SELECT_GROUP' => $work->lang['admin']['select_group'],
                    'L_EDIT' => $work->lang['admin']['edit_group'],
                ]);
            }
        }

        // Инициализация переменных для ядра
        $action = '';
        $title = $work->lang['admin']['admin_group'];
    } else {
        // Подготовка данных для выбора подразделов администрирования
        $select_subact = [
            [
                'url' => sprintf("%s?action=admin&amp;subact=settings", $work->config['site_url']),
                'txt' => $work->lang['admin']['settings'],
            ],
            [
                'url' => sprintf("%s?action=admin&amp;subact=admin_user", $work->config['site_url']),
                'txt' => $work->lang['admin']['admin_user'],
            ],
            [
                'url' => sprintf("%s?action=admin&amp;subact=admin_group", $work->config['site_url']),
                'txt' => $work->lang['admin']['admin_group'],
            ],
        ];

        // Добавляем блок администрирования в шаблон
        $template->add_case('ADMIN_BLOCK', 'ADMIN_START');
        $template->add_if('SESSION_ADMIN_ON', true);
        $template->add_string_ar([
            'L_NAME_BLOCK' => $work->lang['admin']['title'],
            'L_SELECT_SUBACT' => $work->lang['admin']['select_subact'],
        ]);

        // Добавляем подразделы администрирования в шаблон
        foreach ($select_subact as $index => $item) {
            $template->add_string_ar([
                'L_SUBACT' => $item['txt'], // Текстовая метка подраздела
                'U_SUBACT' => $item['url'], // URL подраздела
            ], 'SELECT_SUBACT[' . $index . ']');
        }

        // Инициализация переменных для ядра
        $action = 'admin'; // Активный пункт меню - admin
        $title = $work->lang['admin']['title']; // Дополнительный заголовок - Администрирование
    }
} else { // Проверяем, что сессия администратора не равна true, но пользователь имеет права администратора
    if ((!isset($user->session['admin_on']) || $user->session['admin_on'] !== true) && $user->user['admin']) {
        // Добавляем блок администрирования в шаблон
        $template->add_case('ADMIN_BLOCK', 'ADMIN_START');
        $template->add_string_ar([
            'L_NAME_BLOCK' => $work->lang['admin']['title'],
            'L_ENTER_ADMIN_PASS' => $work->lang['admin']['admin_pass'],
            'L_ENTER' => $work->lang['main']['enter'],
        ]);

        // Инициализация переменных для ядра
        $action = 'admin'; // Активный пункт меню - admin
        $title = $work->lang['admin']['title']; // Дополнительный заголовок - Администрирование
    } else {
        Header('Location: ' . $work->config['site_url']);
        exit;
    }
}
