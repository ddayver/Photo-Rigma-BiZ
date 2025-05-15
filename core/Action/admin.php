<?php

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUndefinedClassInspection */
/**
 * @file      action/admin.php
 * @brief     Администрирование сайта.
 *
 * @author    Dark Dayver
 * @version   0.4.4
 * @date      2025-05-07
 * @namespace PhotoRigma\\Action
 *
 * @details   Этот файл отвечает за управление настройками сайта, пользователями и группами. Основные функции
 *            включают:
 *            - Управление общими настройками сайта (название, описание, мета-теги, размеры изображений и т.д.).
 *            - Управление пользователями (редактирование прав, групп, поиск пользователей).
 *            - Управление группами (редактирование прав групп, создание и удаление групп).
 *            - Защита от несанкционированного доступа к админке (CSRF-токены, проверка прав доступа).
 *            - Логирование ошибок и подозрительных действий.
 *
 * @section   Admin_Related_Files Связанные файлы и компоненты
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
 *              - @see index.php Этот файл подключает action/admin.php по запросу из `$_GET`.
 *
 * @section   Admin_Main_Functions Основные функции
 *            - Управление общими настройками сайта.
 *            - Управление пользователями (редактирование, поиск, изменение прав).
 *            - Управление группами (редактирование, изменение прав).
 *            - Логирование ошибок и подозрительных действий.
 *
 * @section   Admin_Error_Handling Обработка ошибок
 *            При возникновении ошибок генерируются исключения. Поддерживаемые типы исключений:
 *            - `Random\RandomException`: При выполнении методов, использующих `random()`.
 *            - `JsonException`: При выполнении методов, использующих JSON.
 *            - `Exception`: При выполнении прочих методов классов, функций или операций.
 *
 * @throws    Random\RandomException При выполнении методов, использующих random().
 * @throws    JsonException          При выполнении методов, использующих JSON.
 * @throws    Exception              При выполнении прочих методов классов, функций или операций.
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
        date('H:i:s') . ' [ERROR] | ' . (filter_input(
            INPUT_SERVER,
            'REMOTE_ADDR',
            FILTER_VALIDATE_IP
        ) ?: 'UNKNOWN_IP') . ' | ' . __FILE__ . ' | Попытка прямого вызова файла'
    );
    die('HACK!');
}

// Устанавливаем шаблон для административной панели
$template->template_file = 'admin.html';

// Инициализация флагов для шаблона
$template->add_if_array([
    'SESSION_ADMIN_ON' => false, // Флаг активности сессии администратора
    'NEED_FIND'        => false,        // Флаг необходимости поиска
    'NEED_USER'        => false,        // Флаг необходимости отображения пользователей
    'FIND_USER'        => false,        // Флаг найденных пользователей
    'SELECT_GROUP'     => false,     // Флаг выбора группы
    'EDIT_GROUP'       => false,        // Флаг редактирования группы
]);

// Проверяем условия для входа в административную панель
/** @noinspection NotOptimalIfConditionsInspection */
if ((!$work->check_input(
    '_SESSION',
    'admin_on',
    ['isset' => true]
) || !$user->session['admin_on']) && // И пользователь имеет права администратора
    $user->user['admin'] && // И был передан пароль через POST-запрос
    $work->check_input('_POST', 'admin_password', ['isset' => true, 'empty' => true])) {
    // Инициализация данных о попытках входа в сессии
    if (!isset($user->session['login_attempts'])) {
        $user->set_property_key('session', 'login_attempts', 0);
    }
    if (!isset($user->session['last_attempt_time'])) {
        $user->set_property_key('session', 'last_attempt_time', 0);
    }

    // Текущее время
    $current_time = time();

    // Проверяем, прошло ли достаточно времени с момента последней попытки
    if ($current_time - $user->session['last_attempt_time'] > LOCKOUT_TIME) {
        // Сбрасываем счетчик попыток, если время истекло
        $user->set_property_key('session', 'login_attempts', 0);
    }

    // Проверяем, не превышено ли количество попыток
    if ($user->session['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        // Логируем блокировку
        log_in_file(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Пользователь заблокирован из-за превышения попыток входа | ID пользователя: {$user->user['id']}"
        );

        // Выводим сообщение об ошибке и завершаем выполнение
        die(sprintf($work->lang['lockout_time'], LOCKOUT_TIME / 60));
    }

    // Проверяем CSRF-токен
    if (!isset($_POST['csrf_token']) || !hash_equals($user->session['csrf_token'], $_POST['csrf_token'])) {
        throw new RuntimeException(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
        );
    }
    $user->unset_property_key('session', 'csrf_token'); // Удаляем использованный CSRF-токен из сессии

    // Проверяем пароль
    if (password_verify($_POST['admin_password'], $user->user['password'])) {
        // Открываем сессию для администратора
        $user->set_property_key('session', 'admin_on', true);

        // Сбрасываем счетчик попыток при успешном входе
        $user->set_property_key('session', 'login_attempts', 0);

        // Логируем успешный вход
        log_in_file(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Администратор успешно вошел в систему | ID пользователя: {$user->user['id']}"
        );
    } else {
        // Увеличиваем счетчик неудачных попыток
        $user->set_property_key('session', 'login_attempts', $user->session['login_attempts'] + 1);
        $user->set_property_key('session', 'last_attempt_time', $current_time);

        // Логируем неудачную попытку входа
        log_in_file(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неудачная попытка входа в административную панель | ID пользователя: {$user->user['id']}"
        );

        // Перенаправляем пользователя на главную страницу
        header('Location: ' . $work->config['site_url']);
        exit;
    }
}

/** @noinspection NotOptimalIfConditionsInspection */
if (!empty($user->user['admin']) && $work->check_input(
    '_SESSION',
    'admin_on',
    ['isset' => true]
) && $user->session['admin_on']) {
    /** @noinspection NotOptimalIfConditionsInspection */
    if ($work->check_input('_GET', 'subact', ['isset' => true, 'empty' => true]) && $_GET['subact'] === 'settings') {
        if ($work->check_input('_POST', 'submit', ['isset' => true, 'empty' => true])) {
            // Проверяем CSRF-токен
            if (!isset($_POST['csrf_token']) || !hash_equals($user->session['csrf_token'], $_POST['csrf_token'])) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
                );
            }
            $user->unset_property_key('session', 'csrf_token'); // Удаляем использованный CSRF-токен из сессии

            // Массив правил для обработки настроек
            $config_rules = [
                'title_name'           => ['regexp' => REG_NAME],
                'title_description'    => ['regexp' => REG_NAME],
                'meta_description'     => ['regexp' => REG_NAME],
                'meta_keywords'        => ['regexp' => REG_NAME],
                'gal_width'            => ['regexp' => '/^([0-9]+)(\%){0,1}$/'],
                'left_panel'           => ['regexp' => '/^([0-9]+)(\%){0,1}$/'],
                'right_panel'          => ['regexp' => '/^([0-9]+)(\%){0,1}$/'],
                'language'             => [
                    'regexp'    => REG_LOGIN,
                    'condition' => static function () use ($work) {
                        return is_dir($work->config['site_dir'] . 'language/' . $_POST['language']);
                    },
                ],
                'theme'               => [
                    'regexp'    => REG_LOGIN,
                    'condition' => static function () use ($work) {
                        return is_dir($work->config['site_dir'] . 'themes/' . $_POST['theme']);
                    },
                ],
                'max_file_size'        => ['regexp' => '/^[0-9]+$/'],
                'max_file_size_letter' => ['regexp' => '/^(B|K|M|G)$/'],
                'max_photo_w'          => ['regexp' => '/^[0-9]+$/'],
                'max_photo_h'          => ['regexp' => '/^[0-9]+$/'],
                'temp_photo_w'         => ['regexp' => '/^[0-9]+$/'],
                'temp_photo_h'         => ['regexp' => '/^[0-9]+$/'],
                'max_avatar_w'         => ['regexp' => '/^[0-9]+$/'],
                'max_avatar_h'         => ['regexp' => '/^[0-9]+$/'],
                'copyright_year'       => ['regexp' => '/^[0-9\-]+$/'],
                'copyright_text'       => ['regexp' => REG_NAME],
                'copyright_url'        => ['regexp' => '/^.+$/'],
                'last_news'            => ['regexp' => '/^[0-9]+$/'],
                'best_user'            => ['regexp' => '/^[0-9]+$/'],
                'max_rate'             => ['regexp' => '/^[0-9]+$/'],
                'time_user_online'     => ['regexp' => '/^[0-9]+$/'],
            ];

            // Новый конфиг
            $new_config = [];
            foreach ($config_rules as $name => $rule) {
                if ($work->check_input(
                    '_POST',
                    $name,
                    ['isset' => true, 'empty' => true, 'regexp' => $rule['regexp'] ?? false]
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
                    ['`value`' => ':value'], // Данные для обновления
                    TBL_CONFIG, // Таблица
                    [
                        'where'  => '`name` = :name', // Условие WHERE
                        'params' => [':value' => $value, ':name' => $name], // Параметры для prepared statements
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
        $template->add_string_array([
            'L_NAME_BLOCK'                    => $work->lang['admin']['title'] . ' - ' . $work->lang['admin']['settings'],
            'L_MAIN_SETTINGS'                 => $work->lang['admin']['main_settings'],
            'L_TITLE_NAME'                    => $work->lang['admin']['title_name'],
            'L_TITLE_NAME_DESCRIPTION'        => $work->lang['admin']['title_name_description'],
            'L_TITLE_DESCRIPTION'             => $work->lang['admin']['title_description'],
            'L_TITLE_DESCRIPTION_DESCRIPTION' => $work->lang['admin']['title_description_description'],
            'L_META_DESCRIPTION'              => $work->lang['admin']['meta_description'],
            'L_META_DESCRIPTION_DESCRIPTION'  => $work->lang['admin']['meta_description_description'],
            'L_META_KEYWORDS'                 => $work->lang['admin']['meta_keywords'],
            'L_META_KEYWORDS_DESCRIPTION'     => $work->lang['admin']['meta_keywords_description'],
            'L_APPEARANCE_SETTINGS'           => $work->lang['admin']['appearance_settings'],
            'L_GAL_WIDTH'                     => $work->lang['admin']['gal_width'],
            'L_GAL_WIDTH_DESCRIPTION'         => $work->lang['admin']['gal_width_description'],
            'L_LEFT_PANEL'                    => $work->lang['admin']['left_panel'],
            'L_LEFT_PANEL_DESCRIPTION'        => $work->lang['admin']['left_panel_description'],
            'L_RIGHT_PANEL'                   => $work->lang['admin']['right_panel'],
            'L_RIGHT_PANEL_DESCRIPTION'       => $work->lang['admin']['right_panel_description'],
            'L_LANGUAGE'                      => $work->lang['admin']['language'],
            'L_LANGUAGE_DESCRIPTION'          => $work->lang['admin']['language_description'],
            'L_THEMES'                        => $work->lang['admin']['themes'],
            'L_THEMES_DESCRIPTION'            => $work->lang['admin']['themes_description'],
            'L_SIZE_SETTINGS'                 => $work->lang['admin']['size_settings'],
            'L_MAX_FILE_SIZE'                 => $work->lang['admin']['max_file_size'],
            'L_MAX_FILE_SIZE_DESCRIPTION'     => $work->lang['admin']['max_file_size_description'],
            'L_MAX_PHOTO'                     => $work->lang['admin']['max_photo'],
            'L_MAX_PHOTO_DESCRIPTION'         => $work->lang['admin']['max_photo_description'],
            'L_TEMP_PHOTO'                    => $work->lang['admin']['temp_photo'],
            'L_TEMP_PHOTO_DESCRIPTION'        => $work->lang['admin']['temp_photo_description'],
            'L_MAX_AVATAR'                    => $work->lang['admin']['max_avatar'],
            'L_MAX_AVATAR_DESCRIPTION'        => $work->lang['admin']['max_avatar_description'],
            'L_COPYRIGHT_SETTINGS'            => $work->lang['admin']['copyright_settings'],
            'L_COPYRIGHT_YEAR'                => $work->lang['admin']['copyright_year'],
            'L_COPYRIGHT_YEAR_DESCRIPTION'    => $work->lang['admin']['copyright_year_description'],
            'L_COPYRIGHT_TEXT'                => $work->lang['admin']['copyright_text'],
            'L_COPYRIGHT_TEXT_DESCRIPTION'    => $work->lang['admin']['copyright_text_description'],
            'L_COPYRIGHT_URL'                 => $work->lang['admin']['copyright_url'],
            'L_COPYRIGHT_URL_DESCRIPTION'     => $work->lang['admin']['copyright_url_description'],
            'L_ADDITIONAL_SETTINGS'           => $work->lang['admin']['additional_settings'],
            'L_LAST_NEWS'                     => $work->lang['admin']['last_news'],
            'L_LAST_NEWS_DESCRIPTION'         => $work->lang['admin']['last_news_description'],
            'L_BEST_USER'                     => $work->lang['admin']['best_user'],
            'L_BEST_USER_DESCRIPTION'         => $work->lang['admin']['best_user_description'],
            'L_MAX_RATE'                      => $work->lang['admin']['max_rate'],
            'L_MAX_RATE_DESCRIPTION'          => $work->lang['admin']['max_rate_description'],
            'L_TIME_ONLINE'                   => $work->lang['admin']['time_online'],
            'L_TIME_ONLINE_DESCRIPTION'       => $work->lang['admin']['time_online_description'],
            'L_SAVE_SETTINGS'                 => $work->lang['admin']['save_settings'],
            'D_TITLE_NAME'                    => $work->config['title_name'],
            'D_TITLE_DESCRIPTION'             => $work->config['title_description'],
            'D_META_DESCRIPTION'              => $work->config['meta_description'],
            'D_META_KEYWORDS'                 => $work->config['meta_keywords'],
            'D_GAL_WIDTH'                     => $work->config['gal_width'],
            'D_LEFT_PANEL'                    => $work->config['left_panel'],
            'D_RIGHT_PANEL'                   => $work->config['right_panel'],
            'D_MAX_FILE_SIZE'                 => $max_file_size,
            'D_SELECTED_SIZE'                 => strtoupper($max_file_size_letter), // Буква размера в верхнем регистре
            'L_SELECTED_SIZE'                 => $max_file_size_letter === 'b' ? $max_file_size_letter : strtoupper(
                $max_file_size_letter
            ) . 'b', // Формируем метку размера
            'D_MAX_PHOTO_W'                   => $work->config['max_photo_w'],
            'D_MAX_PHOTO_H'                   => $work->config['max_photo_h'],
            'D_TEMP_PHOTO_W'                  => $work->config['temp_photo_w'],
            'D_TEMP_PHOTO_H'                  => $work->config['temp_photo_h'],
            'D_MAX_AVATAR_W'                  => $work->config['max_avatar_w'],
            'D_MAX_AVATAR_H'                  => $work->config['max_avatar_h'],
            'D_COPYRIGHT_YEAR'                => $work->config['copyright_year'],
            'D_COPYRIGHT_TEXT'                => $work->config['copyright_text'],
            'D_COPYRIGHT_URL'                 => $work->config['copyright_url'],
            'D_LAST_NEWS'                     => $work->config['last_news'],
            'D_BEST_USER'                     => $work->config['best_user'],
            'D_MAX_RATE'                      => $work->config['max_rate'],
            'D_TIME_ONLINE'                   => $work->config['time_user_online'],
            'CSRF_TOKEN'                      => $user->csrf_token(),
        ]);

        // Добавление языков в шаблон
        foreach ($language as $key => $val) {
            $template->add_string_array(
                [
                    'D_DIR_LANG'  => $val['value'],
                    'D_NAME_LANG' => $val['name'],
                ],
                'SELECT_LANGUAGE[' . $key . ']'
            );

            if ($val['value'] === $work->config['language']) {
                $template->add_string_array(
                    [
                        'D_SELECTED_LANG' => $val['value'],
                        'L_SELECTED_LANG' => $val['name'],
                    ]
                );
            }
        }

        // Добавление тем в шаблон
        foreach ($themes as $key => $val) {
            $template->add_string_array(
                [
                    'D_DIR_THEME'  => $val,
                    'D_NAME_THEME' => ucfirst($val),
                ],
                'SELECT_THEME[' . $key . ']'
            );

            if ($val === $work->config['theme']) {
                $template->add_string_array(
                    [
                        'D_SELECTED_THEME' => $val,
                        'L_SELECTED_THEME' => ucfirst($val),
                    ]
                );
            }
        }

        // Инициализация переменных для ядра
        /** @noinspection PhpUnusedLocalVariableInspection */
        $action = ''; // Переменная для действия (используется в ядре)
        /** @noinspection PhpUnusedLocalVariableInspection */
        $title = $work->lang['admin']['settings']; // Заголовок страницы (используется в ядре)
    } /** @noinspection NotOptimalIfConditionsInspection */ elseif ($work->check_input(
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
            'isset'    => true,
            'empty'    => true,
            'regexp'   => '/^[0-9]+$/',
            'not_zero' => true,
        ])) {
            // Проверяем существование пользователя с указанным uid
            $db->select(
                '`id`, `login`, `email`, `real_name`, `avatar`, `group_id`, `user_rights`, `deleted_at`, `permanently_deleted`',
                TBL_USERS,
                [
                    'where'  => '`id` = :uid',
                    'params' => [':uid' => $_GET['uid']],
                ]
            );
            $user_data = $db->result_row();

            if ($user_data) {
                /** @noinspection NotOptimalIfConditionsInspection */
                if ($user_data['id'] !== (int)$user->session['login_id'] && !$user_data['permanently_deleted'] && $work->check_input(
                    '_GET',
                    'do',
                    ['isset' => true, 'empty' => true]
                ) && $_GET['do'] === 'delete_user') {
                    $target_uid = (int)$_GET['uid'];
                    if ($user->delete_user($target_uid, true)) {
                        header(sprintf(
                            'Location: %s?action=admin&subact=admin_user',
                            $work->config['site_url']
                        ));
                        exit;
                    }
                    header(sprintf(
                        'Location: %s?action=admin&subact=admin_user&uid=%d',
                        $work->config['site_url'],
                        $target_uid
                    ));
                    exit;
                }
                // Получаем поля прав доступа пользователя
                $user_permission_fields = $user->user_right_fields['all'];
                $user_data = array_merge($user_data, $user->process_user_rights($user_data['user_rights']));

                if ($work->check_input('_POST', 'submit', ['isset' => true, 'empty' => true])) {
                    // Проверяем CSRF-токен
                    if (!isset($_POST['csrf_token']) || !hash_equals(
                        $user->session['csrf_token'],
                        $_POST['csrf_token']
                    )) {
                        throw new RuntimeException(
                            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
                        );
                    }
                    $user->unset_property_key('session', 'csrf_token'); // Удаляем использованный CSRF-токен из сессии
                    // Обновление данные пользователя
                    $user_data = $user->update_user_rights($_GET['uid'], $user_data, $_POST);
                }

                // Получаем список групп для отображения в шаблоне
                $db->select('*', TBL_GROUP, [
                    'where' => '`id` != 0',
                ]);
                $groups = $db->result_array();

                if ($groups) {
                    foreach ($groups as $key => $val) {
                        $template->add_string_array(
                            [
                                'D_GROUP_ID'   => (string)$val['id'],
                                'D_GROUP_NAME' => $val['name'],
                            ],
                            'GROUP_USER[' . $key . ']'
                        );

                        if ($val['id'] === $user_data['group_id']) {
                            $template->add_string_array(
                                [
                                    'D_SELECTED_GROUP' => (string)$val['id'],
                                    'L_SELECTED_GROUP' => $val['name'],
                                ]
                            );
                        }
                    }

                    // Добавляем права доступа в шаблон
                    foreach ($user_permission_fields as $field) {
                        $template->add_string('L_' . strtoupper($field), $work->lang['admin'][$field]);
                        $template->add_string(
                            'D_' . strtoupper($field),
                            $user_data[$field] ? ' checked="checked"' : ''
                        );
                    }
                    $template->add_string('D_DISABLE_FIELD', ((int)$user_data['permanently_deleted'] === 0) ? '' : ' disabled');
                    $template->add_if('SOFT_DELETE', false);
                    // Проверяем, является ли текущий пользователь админом и мягко удален ли
                    // просматриваемый пользователь и не окончательно
                    if (!empty($user_data['deleted_at']) && (int)$user_data['permanently_deleted'] === 0) {
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
                    // Добавляем основные данные пользователя в шаблон
                    $template->add_if('FIND_USER', true);
                    $template->add_if('CAN_DELETE', ($user_data['id'] !== (int)$user->session['login_id'] && (int)$user_data['permanently_deleted'] === 0));
                    $template->add_string_array([
                        'CSRF_TOKEN'    => $user->csrf_token(),
                        'L_LOGIN'       => $work->lang['admin']['login'],
                        'L_EMAIL'       => $work->lang['admin']['email'],
                        'L_REAL_NAME'   => $work->lang['admin']['real_name'],
                        'L_AVATAR'      => $work->lang['admin']['avatar'],
                        'L_GROUP'       => $work->lang['main']['group'],
                        'L_USER_RIGHTS' => $work->lang['admin']['user_rights'],
                        'L_HELP_EDIT'   => $work->lang['admin']['help_edit_user'],
                        'L_SAVE_USER'   => $work->lang['admin']['save_user'],
                        'L_DELETE_BLOCK'   => $work->lang['admin']['hard_delete_user'],
                        'L_CONFIRM_DELETE_BLOCK' => $work->lang['admin']['confirm_hard_delete_user'],
                        'L_CONFIRM_DELETE' => $work->lang['main']['delete'],
                        'L_CANCEL_DELETE'  => $work->lang['main']['cancel'],
                        'D_LOGIN'       => $user_data['login'],
                        'D_EMAIL'       => Work::clean_field($user_data['email']),
                        'D_REAL_NAME'   => Work::clean_field($user_data['real_name']),
                        'U_AVATAR'      => $work->config['site_url'] . $work->config['avatar_dir'] . '/' . $user_data['avatar'],
                        'U_DELETE_BLOCK'  => sprintf(
                            '%s?action=admin&amp;subact=admin_user&amp;do=delete_user&amp;uid=%s',
                            $work->config['site_url'],
                            $user_data['id']
                        ),
                    ]);
                } else {
                    throw new RuntimeException(
                        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Не удалось получить данные группы'
                    );
                }
            } else {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные пользователя | ID пользователя: {$_GET['uid']}"
                );
            }
        } else {
            // Поиск пользователей
            if ($work->check_input('_POST', 'submit', ['isset' => true, 'empty' => true]) && $work->check_input(
                '_POST',
                'search_user',
                ['isset' => true, 'empty' => true]
            )) {
                // Проверяем CSRF-токен
                if (!isset($_POST['csrf_token']) || !hash_equals($user->session['csrf_token'], $_POST['csrf_token'])) {
                    throw new RuntimeException(
                        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
                    );
                }
                $user->unset_property_key('session', 'csrf_token'); // Удаляем использованный CSRF-токен из сессии

                // Используем метод полнотекстового поиска.
                $found_users = $db->full_text_search(
                    ['`id`', '`real_name`'],
                    ['`login`', '`real_name`', '`email`'],
                    Work::clean_field(
                        $_POST['search_user']
                    ),
                    TBL_USERS
                );

                if ($found_users) {
                    foreach ($found_users as $key => $value) {
                        $template->add_string_array([
                            'D_FIND_USER' => $value['real_name'],
                            'U_FIND_USER' => sprintf(
                                '%s?action=admin&amp;subact=admin_user&amp;uid=%s',
                                $work->config['site_url'],
                                $value['id']
                            ),
                        ], 'FIND_USER[' . $key . ']');
                    }
                } else {
                    $template->add_string_array([
                        'D_FIND_USER' => $work->lang['admin']['no_find_user'],
                        'U_FIND_USER' => '#',
                    ], 'FIND_USER[0]');
                }

                $template->add_if('NEED_USER', true);
            }

            // Добавляем форму поиска в шаблон
            $template->add_if('NEED_FIND', true);
            $template->add_string_array([
                'L_SEARCH_USER' => $work->lang['admin']['search_user'],
                'L_HELP_SEARCH' => $work->lang['admin']['help_search_user'],
                'L_SEARCH'      => $work->lang['main']['search'],
                'D_SEARCH_USER' => $work->check_input(
                    '_POST',
                    'search_user',
                    ['isset' => true, 'empty' => true]
                ) ? Work::clean_field($_POST['search_user']) : '',
                'CSRF_TOKEN'    => $user->csrf_token(),
            ]);
        }

        // Инициализация переменных для ядра
        /** @noinspection PhpUnusedLocalVariableInspection */
        $action = '';
        /** @noinspection PhpUnusedLocalVariableInspection */
        $title = $work->lang['admin']['admin_user'];
    } /** @noinspection NotOptimalIfConditionsInspection */ elseif ($work->check_input(
        '_GET',
        'subact',
        ['isset' => true, 'empty' => true]
    ) && $_GET['subact'] === 'admin_group') {
        // Добавляем блок для администрирования групп
        $template->add_case('ADMIN_BLOCK', 'ADMIN_GROUP');
        $add_group = false;
        // Список полей для прав доступа группы
        $group_permission_fields = $user->user_right_fields['all'];

        // Удаление группы
        /** @noinspection NotOptimalIfConditionsInspection */
        if ($work->check_input('_GET', 'do', ['isset' => true, 'empty' => true])
            && $_GET['do'] === 'delete_group') {
            // Проверяем CSRF-токен
            if (!isset($_POST['csrf_token']) || !hash_equals(
                $user->session['csrf_token'],
                $_POST['csrf_token']
            )) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
                );
            }

            if ($work->check_input('_POST', 'group', ['isset' => true, 'empty' => true, 'regexp' => '/^[0-9]+$/'])) {
                $del_group_id = (int)$_POST['group'];
                if (!$user->delete_group($del_group_id)) {
                    log_in_file(
                        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                        'Ошибка при удалении группы | Группа ID: ' . $del_group_id
                    );
                }
            }
            header(sprintf('Location: %s?action=admin&subact=admin_group', $work->config['site_url']));
            exit;
        }

        // Добавление группы
        /** @noinspection NotOptimalIfConditionsInspection */
        if ($work->check_input('_GET', 'do', ['isset' => true, 'empty' => true])
            && $_GET['do'] === 'add_group') {

            // Создаем список чек-боксов для установки прав
            foreach ($group_permission_fields as $value) {
                $template->add_string('L_' . strtoupper($value), $work->lang['admin'][$value]);
                $template->add_string(
                    'D_' . strtoupper($value),
                    ''
                );
            }

            $template->add_if('EDIT_GROUP', true);
            $template->add_string_array([
                'L_NAME_GROUP'   => $work->lang['main']['group'],
                'L_GROUP_RIGHTS' => $work->lang['admin']['group_rights'],
                'L_SAVE_GROUP'   => $work->lang['admin']['save_group'],
                'D_ID_GROUP'     => 'new',
                'D_NAME_GROUP'   => '',
                'U_BLOCK_GROUP'  => sprintf(
                    '%s?action=admin&subact=admin_group',
                    $work->config['site_url']
                ),
            ]);
            $template->add_string(
                'L_NAME_BLOCK',
                $work->lang['admin']['title'] . ' - ' . $work->lang['admin']['add_group']
            );
            $template->add_string('CSRF_TOKEN', $user->csrf_token());
            $add_group = true;
        } else {
            $template->add_string(
                'L_NAME_BLOCK',
                $work->lang['admin']['title'] . ' - ' . $work->lang['admin']['admin_group']
            );
        }

        if ($work->check_input('_POST', 'submit', ['isset' => true, 'empty' => true]) && $work->check_input(
            '_POST',
            'id_group',
            ['isset' => true, 'regexp' => '/^(?:\d+|new)$/']
        )) {
            // Проверяем CSRF-токен
            if (!isset($_POST['csrf_token']) || !hash_equals(
                $user->session['csrf_token'],
                $_POST['csrf_token']
            )) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
                );
            }

            if ($_POST['id_group'] === 'new') {
                $new_group_id = $user->add_new_group($_POST);
                if ($new_group_id === 0) {
                    header(sprintf('Location: %s?action=admin&subact=admin_group&do=add_group', $work->config['site_url']));
                    exit;
                }
                $_POST['group'] = $new_group_id;
                $template->add_string(
                    'L_NAME_BLOCK',
                    $work->lang['admin']['title'] . ' - ' . $work->lang['admin']['admin_group']
                );
            } else {
                // Выбираем данные группы по ID
                $db->select('*', TBL_GROUP, [
                    'where'  => '`id` = :id_group',
                    'params' => [':id_group' => $_POST['id_group']],
                ]);
                $group_data = $db->result_row();

                if ($group_data) {
                    // Обновляем данные группы
                    $group_data = $user->update_group_data($group_data, $_POST);
                    $_POST['group'] = $group_data['id'];
                }
            }
        }

        if ($add_group || ($work->check_input('_POST', 'submit', ['isset' => true, 'empty' => true]) &&
            $work->check_input(
                '_POST',
                'group',
                ['isset' => true, 'regexp' => '/^[0-9]+$/']
            ))) {
            // Проверяем CSRF-токен
            if (!$add_group && (!isset($_POST['csrf_token']) || !hash_equals(
                $user->session['csrf_token'],
                $_POST['csrf_token']
            ))) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
                );
            }

            // Выбираем данные группы по ID
            $db->select('*', TBL_GROUP, [
                'where'  => '`id` = :group_id',
                'params' => [':group_id' => $_POST['group']],
            ]);
            $group_data = $db->result_row();

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
                $template->add_string_array([
                    'L_NAME_GROUP'   => $work->lang['main']['group'],
                    'L_GROUP_RIGHTS' => $work->lang['admin']['group_rights'],
                    'L_SAVE_GROUP'   => $work->lang['admin']['save_group'],
                    'D_ID_GROUP'     => (string)$group_data['id'],
                    'D_NAME_GROUP'   => $group_data['name'],
                ]);
            }
            $user->unset_property_key('session', 'csrf_token'); // Удаляем использованный CSRF-токен из сессии
            $template->add_string('CSRF_TOKEN', $user->csrf_token());
        } else {
            $db->select('*', TBL_GROUP);
            // Получаем список всех групп
            $groups = $db->result_array();

            if ($groups) {
                foreach ($groups as $key => $val) {
                    $template->add_string_array(
                        [
                            'D_ID_GROUP'   => (string)$val['id'],
                            'D_NAME_GROUP' => $val['name'],
                        ],
                        'SELECT_GROUP[' . $key . ']'
                    );
                }
                $template->add_string_array(
                    [
                        'D_SELECTED_GROUP' => (string)$groups[0]['id'],
                        'L_SELECTED_GROUP' => $groups[0]['name'],
                    ]
                );

                // Добавляем форму выбора группы в шаблон
                $data_protected_groups = json_encode(PROTECTED_GROUPS, JSON_THROW_ON_ERROR);
                $template->add_if('SELECT_GROUP', true);
                $template->add_string_array([
                    'L_SELECT_GROUP' => $work->lang['admin']['select_group'],
                    'L_EDIT'         => $work->lang['admin']['edit_group'],
                    'L_DELETE'       => $work->lang['admin']['delete_group'],
                    'L_CONFIRM_DELETE_BLOCK' => $work->lang['admin']['confirm_delete_group'],
                    'L_CONFIRM_DELETE'       => $work->lang['main']['delete'],
                    'L_CANCEL_DELETE'        => $work->lang['main']['cancel'],
                    'L_ADD_GROUP'        => $work->lang['admin']['add_group'],
                    'D_PROTECTED_GROUPS' => $data_protected_groups,
                    'U_BLOCK_GROUP'         => sprintf(
                        '%s?action=admin&subact=admin_group',
                        $work->config['site_url']
                    ),
                    'U_ADD_GROUP'         => sprintf(
                        '%s?action=admin&amp;subact=admin_group&amp;do=add_group',
                        $work->config['site_url'],
                    ),
                    'U_DELETE_BLOCK'         => sprintf(
                        '%s?action=admin&amp;subact=admin_group&amp;do=delete_group',
                        $work->config['site_url'],
                    ),
                ]);
                $user->unset_property_key('session', 'csrf_token'); // Удаляем использованный CSRF-токен из сессии
                $template->add_string('CSRF_TOKEN', $user->csrf_token());
            }
        }

        // Инициализация переменных для ядра
        /** @noinspection PhpUnusedLocalVariableInspection */
        $action = '';
        /** @noinspection PhpUnusedLocalVariableInspection */
        $title = $work->lang['admin']['admin_group'];
    } else {
        // Подготовка данных для выбора подразделов администрирования
        $select_subact = [
            [
                'url' => sprintf('%s?action=admin&amp;subact=settings', $work->config['site_url']),
                'txt' => $work->lang['admin']['settings'],
            ],
            [
                'url' => sprintf('%s?action=admin&amp;subact=admin_user', $work->config['site_url']),
                'txt' => $work->lang['admin']['admin_user'],
            ],
            [
                'url' => sprintf('%s?action=admin&amp;subact=admin_group', $work->config['site_url']),
                'txt' => $work->lang['admin']['admin_group'],
            ],
        ];

        // Добавляем блок администрирования в шаблон
        $template->add_case('ADMIN_BLOCK', 'ADMIN_START');
        $template->add_if('SESSION_ADMIN_ON', true);
        $template->add_string_array([
            'L_NAME_BLOCK'    => $work->lang['admin']['title'],
            'L_SELECT_SUBACT' => $work->lang['admin']['select_subact'],
        ]);

        // Добавляем подразделы администрирования в шаблон
        foreach ($select_subact as $index => $item) {
            $template->add_string_array([
                'L_SUBACT' => $item['txt'], // Текстовая метка подраздела
                'U_SUBACT' => $item['url'], // URL подраздела
            ], 'SELECT_SUBACT[' . $index . ']');
        }

        // Инициализация переменных для ядра
        /** @noinspection PhpUnusedLocalVariableInspection */
        $action = 'admin'; // Активный пункт меню - admin
        /** @noinspection PhpUnusedLocalVariableInspection */
        $title = $work->lang['admin']['title']; // Дополнительный заголовок - Администрирование
    }
} elseif ((!isset($user->session['admin_on']) || $user->session['admin_on'] !== true) && $user->user['admin']) {
    // Добавляем блок администрирования в шаблон
    $template->add_case('ADMIN_BLOCK', 'ADMIN_START');
    $template->add_string_array([
        'L_NAME_BLOCK'       => $work->lang['admin']['title'],
        'L_ENTER_ADMIN_PASS' => $work->lang['admin']['admin_pass'],
        'L_PASSWORD'         => $work->lang['profile']['password'],
        'L_ENTER'            => $work->lang['main']['enter'],
        'CSRF_TOKEN'         => $user->csrf_token(),
    ]);

    // Инициализация переменных для ядра
    /** @noinspection PhpUnusedLocalVariableInspection */
    $action = 'admin'; // Активный пункт меню - admin
    /** @noinspection PhpUnusedLocalVariableInspection */
    $title = $work->lang['admin']['title']; // Дополнительный заголовок - Администрирование
} else {
    Header('Location: ' . $work->config['site_url']);
    exit;
}
