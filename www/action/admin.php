<?php

/**
 * @file        action/admin.php
 * @brief       Администрирование сайта.
 * @author      Dark Dayver
 * @version     0.2.0
 * @date        28/03-2012
 * @details     Используется для управления настройками и пользователями галереи.
 */

namespace PhotoRigma\Action;

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

include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/main.php');

$template->template_file = 'admin.html';
$template->add_if_ar(array(
    'SESSION_ADMIN_ON' => false,
    'NEED_FIND' => false,
    'NEED_USER' => false,
    'FIND_USER' => false,
    'SELECT_GROUP' => false,
    'EDIT_GROUP' => false
));

if ((!$work->check_session(
    'admin_on',
    true
) || $_SESSION['admin_on'] !== true) && $user->user['admin'] == true && $work->check_post(
    'admin_password',
    true,
    true
) && $user->user['password'] == md5(
    $_POST['admin_password']
)) { // если не открыта сессия для Админа, пользователь имеет право на вход в Админку, был передан пароль для входа и пароль совпадает с паролем пользователя, то...
    $_SESSION['admin_on'] = true; // открываем сессию для Админа
}

if ($work->check_session(
    'admin_on',
    true
) && $_SESSION['admin_on'] === true && $user->user['admin'] == true) { // если открыта сессия для Админа и пользователь имеет права Админа, то...
    if ($work->check_get(
        'subact',
        true,
        true
    ) && $_GET['subact'] == 'settings') { // если была команда на общие настройки, то...
        if ($work->check_post('submit', true, true) || ($work->check_post('submit_x', true, true) && $work->check_post(
            'submit_y',
            true,
            true
        ))) { // если поступил запрос на сохранение общих настроек, то...
            $new_config = $work->config; // формируем массив настроек, хранящихся в базе на текущий момент

            // проверим введенные пользователем данные на соотвествие формата, если формат соответствует, то используем введенное пользователем, если нет - остается старая настройка
            if ($work->check_post('title_name', true, true, REG_NAME)) {
                $new_config['title_name'] = $work->clean_field($_POST['title_name']);
            }
            if ($work->check_post('title_description', true, true, REG_NAME)) {
                $new_config['title_description'] = $work->clean_field($_POST['title_description']);
            }
            if ($work->check_post('meta_description', true, true, REG_NAME)) {
                $new_config['meta_description'] = $work->clean_field($_POST['meta_description']);
            }
            if ($work->check_post('meta_keywords', true, true, REG_NAME)) {
                $new_config['meta_keywords'] = $work->clean_field($_POST['meta_keywords']);
            }
            if ($work->check_post('gal_width', true, true, '^([0-9]+)(%){0,1}\$')) {
                $new_config['gal_width'] = $_POST['gal_width'];
            }
            if ($work->check_post('left_panel', true, true, '^([0-9]+)(%){0,1}\$')) {
                $new_config['left_panel'] = $_POST['left_panel'];
            }
            if ($work->check_post('right_panel', true, true, '^([0-9]+)(%){0,1}\$')) {
                $new_config['right_panel'] = $_POST['right_panel'];
            }
            if ($work->check_post('language', true, true, REG_LOGIN) && is_dir(
                $work->config['site_dir'] . 'language/' . $_POST['language']
            )) {
                $new_config['language'] = $_POST['language'];
            }
            if ($work->check_post('themes', true, true, REG_LOGIN) && is_dir(
                $work->config['site_dir'] . 'themes/' . $_POST['themes']
            )) {
                $new_config['themes'] = $_POST['themes'];
            }
            if ($work->check_post('max_file_size', true, true, '^[0-9]+\$') && $work->check_post(
                'max_file_size_letter',
                true,
                true,
                '^(B|K|M|G)\$'
            )) {
                if ($_POST['max_file_size_letter'] == 'B') {
                    $new_config['max_file_size'] = $_POST['max_file_size'];
                } else {
                    $new_config['max_file_size'] = $_POST['max_file_size'] . $_POST['max_file_size_letter'];
                }
            }
            if ($work->check_post('max_photo_w', true, true, '^[0-9]+\$')) {
                $new_config['max_photo_w'] = $_POST['max_photo_w'];
            }
            if ($work->check_post('max_photo_h', true, true, '^[0-9]+\$')) {
                $new_config['max_photo_h'] = $_POST['max_photo_h'];
            }
            if ($work->check_post('temp_photo_w', true, true, '^[0-9]+\$')) {
                $new_config['temp_photo_w'] = $_POST['temp_photo_w'];
            }
            if ($work->check_post('temp_photo_h', true, true, '^[0-9]+\$')) {
                $new_config['temp_photo_h'] = $_POST['temp_photo_h'];
            }
            if ($work->check_post('max_avatar_w', true, true, '^[0-9]+\$')) {
                $new_config['max_avatar_w'] = $_POST['max_avatar_w'];
            }
            if ($work->check_post('max_avatar_h', true, true, '^[0-9]+\$')) {
                $new_config['max_avatar_h'] = $_POST['max_avatar_h'];
            }
            if ($work->check_post('copyright_year', true, true, '^[0-9\-]+\$')) {
                $new_config['copyright_year'] = $_POST['copyright_year'];
            }
            if ($work->check_post('copyright_text', true, true, REG_NAME)) {
                $new_config['copyright_text'] = $work->clean_field($_POST['copyright_text']);
            }
            if ($work->check_post('copyright_url', true, true)) {
                $new_config['copyright_url'] = $_POST['copyright_url'];
            }
            if ($work->check_post('last_news', true, true, '^[0-9]+\$')) {
                $new_config['last_news'] = $_POST['last_news'];
            }
            if ($work->check_post('best_user', true, true, '^[0-9]+\$')) {
                $new_config['best_user'] = $_POST['best_user'];
            }
            if ($work->check_post('max_rate', true, true, '^[0-9]+\$')) {
                $new_config['max_rate'] = $_POST['max_rate'];
            }

            foreach ($new_config as $name => $value) {
                if ($work->config[$name] !== $value) {
                    if ($db->update(array('value' => $value), TBL_CONFIG, '`name` = \'' . $name . '\'')) {
                        $work->config[$name] = $value;
                    } else {
                        log_in_file($db->error, DIE_IF_ERROR);
                    }
                }
            }
        }

        $max_file_size = trim($work->config['max_file_size']); // удаляем пробельные символы в начале и конце строки
        $max_file_size_letter = strtolower(
            $max_file_size[strlen($max_file_size) - 1]
        ); // получаем последний символ строки и переводим его в нижний регистр
        if ($max_file_size_letter == 'k' || $max_file_size_letter == 'm' || $max_file_size_letter == 'g') { // если последний символ является указателем на кило-, мега- или гиго-байты
            $max_file_size = substr(
                $max_file_size,
                0,
                strlen($max_file_size) - 1
            ); // получаем все, кроме последнего символа строки
        } else { // иначе
            $max_file_size_letter = 'b'; // пометим показатель в байтах
        }
        $language = $work->get_languages();
        $themes = $work->get_themes();

        $template->add_case('ADMIN_BLOCK', 'ADMIN_SETTINGS');
        $template->add_string_ar(array(
            'L_NAME_BLOCK' => $lang['admin']['title'] . ' - ' . $lang['admin']['settings'],
            'L_MAIN_SETTINGS' => $lang['admin']['main_settings'],
            'L_TITLE_NAME' => $lang['admin']['title_name'],
            'L_TITLE_NAME_DESCRIPTION' => $lang['admin']['title_name_description'],
            'L_TITLE_DESCRIPTION' => $lang['admin']['title_description'],
            'L_TITLE_DESCRIPTION_DESCRIPTION' => $lang['admin']['title_description_description'],
            'L_META_DESCRIPTION' => $lang['admin']['meta_description'],
            'L_META_DESCRIPTION_DESCRIPTION' => $lang['admin']['meta_description_description'],
            'L_META_KEYWORDS' => $lang['admin']['meta_keywords'],
            'L_META_KEYWORDS_DESCRIPTION' => $lang['admin']['meta_keywords_description'],
            'L_APPEARANCE_SETTINGS' => $lang['admin']['appearance_settings'],
            'L_GAL_WIDTH' => $lang['admin']['gal_width'],
            'L_GAL_WIDTH_DESCRIPTION' => $lang['admin']['gal_width_description'],
            'L_LEFT_PANEL' => $lang['admin']['left_panel'],
            'L_LEFT_PANEL_DESCRIPTION' => $lang['admin']['left_panel_description'],
            'L_RIGHT_PANEL' => $lang['admin']['right_panel'],
            'L_RIGHT_PANEL_DESCRIPTION' => $lang['admin']['right_panel_description'],
            'L_LANGUAGE' => $lang['admin']['language'],
            'L_LANGUAGE_DESCRIPTION' => $lang['admin']['language_description'],
            'L_THEMES' => $lang['admin']['themes'],
            'L_THEMES_DESCRIPTION' => $lang['admin']['themes_description'],
            'L_SIZE_SETTINGS' => $lang['admin']['size_settings'],
            'L_MAX_FILE_SIZE' => $lang['admin']['max_file_size'],
            'L_MAX_FILE_SIZE_DESCRIPTION' => $lang['admin']['max_file_size_description'],
            'L_MAX_PHOTO' => $lang['admin']['max_photo'],
            'L_MAX_PHOTO_DESCRIPTION' => $lang['admin']['max_photo_description'],
            'L_TEMP_PHOTO' => $lang['admin']['temp_photo'],
            'L_TEMP_PHOTO_DESCRIPTION' => $lang['admin']['temp_photo_description'],
            'L_MAX_AVATAR' => $lang['admin']['max_avatar'],
            'L_MAX_AVATAR_DESCRIPTION' => $lang['admin']['max_avatar_description'],
            'L_COPYRIGHT_SETTINGS' => $lang['admin']['copyright_settings'],
            'L_COPYRIGHT_YEAR' => $lang['admin']['copyright_year'],
            'L_COPYRIGHT_YEAR_DESCRIPTION' => $lang['admin']['copyright_year_description'],
            'L_COPYRIGHT_TEXT' => $lang['admin']['copyright_text'],
            'L_COPYRIGHT_TEXT_DESCRIPTION' => $lang['admin']['copyright_text_description'],
            'L_COPYRIGHT_URL' => $lang['admin']['copyright_url'],
            'L_COPYRIGHT_URL_DESCRIPTION' => $lang['admin']['copyright_url_description'],
            'L_ADDITIONAL_SETTINGS' => $lang['admin']['additional_settings'],
            'L_LAST_NEWS' => $lang['admin']['last_news'],
            'L_LAST_NEWS_DESCRIPTION' => $lang['admin']['last_news_description'],
            'L_BEST_USER' => $lang['admin']['best_user'],
            'L_BEST_USER_DESCRIPTION' => $lang['admin']['best_user_description'],
            'L_MAX_RATE' => $lang['admin']['max_rate'],
            'L_MAX_RATE_DESCRIPTION' => $lang['admin']['max_rate_description'],
            'L_SAVE_SETTINGS' => $lang['admin']['save_settings'],
            'D_TITLE_NAME' => $work->config['title_name'],
            'D_TITLE_DESCRIPTION' => $work->config['title_description'],
            'D_META_DESCRIPTION' => $work->config['meta_description'],
            'D_META_KEYWORDS' => $work->config['meta_keywords'],
            'D_GAL_WIDTH' => $work->config['gal_width'],
            'D_LEFT_PANEL' => $work->config['left_panel'],
            'D_RIGHT_PANEL' => $work->config['right_panel'],
            'D_MAX_FILE_SIZE' => $max_file_size,
            'D_SEL_B' => $max_file_size_letter == 'b' ? ' selected="selected"' : '',
            'D_SEL_K' => $max_file_size_letter == 'k' ? ' selected="selected"' : '',
            'D_SEL_M' => $max_file_size_letter == 'm' ? ' selected="selected"' : '',
            'D_SEL_G' => $max_file_size_letter == 'g' ? ' selected="selected"' : '',
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
            'D_MAX_RATE' => $work->config['max_rate']
        ));
        foreach ($language as $key => $val) {
            $template->add_string_ar(array(
                'D_DIR_LANG' => $val['value'],
                'D_NAME_LANG' => $val['name'],
                'D_SELECTED_LANG' => $val['value'] == $work->config['language'] ? ' selected="selected"' : ''
            ), 'SELECT_LANGUAGE[' . $key . ']');
        }
        foreach ($themes as $key => $val) {
            $template->add_string_ar(array(
                'D_DIR_THEMES' => $val,
                'D_SELECTED_THEMES' => $val == $work->config['themes'] ? ' selected="selected"' : ''
            ), 'SELECT_THEMES[' . $key . ']');
        }
        $action = ''; // активного пункта меню - нет
        $title = $lang['admin']['settings']; // дполнительный заговловок - Общие настройки
    } elseif ($work->check_get(
        'subact',
        true,
        true
    ) && $_GET['subact'] == 'admin_user') { // иначе если было запрошено Управление пользователями
        $template->add_case('ADMIN_BLOCK', 'ADMIN_USER');
        $template->add_string('L_NAME_BLOCK', $lang['admin']['title'] . ' - ' . $lang['admin']['admin_user']);

        if ($work->check_get(
            'uid',
            true,
            true,
            '^[0-9]+\$',
            true
        )) { // если был передан идентификатор пользователя для редактирования, то...
            if ($db->select('*', TBL_USERS, '`id` = ' . $work->clean_field($_GET['uid']))) {
                $temp = $db->res_row();
                if ($temp) {
                    if ($work->check_post('submit', true, true)) {
                        if ($work->check_post(
                            'group',
                            true,
                            true,
                            '^[0-9]+\$',
                            true
                        ) && $_POST['group'] != $temp['group']) {
                            $query['group'] = $work->clean_field($_POST['group']);
                            $new_temp = $temp;
                            if ($db->select('*', TBL_GROUP, '`id` = ' . $work->clean_field($_POST['group']))) {
                                $temp_group = $db->res_row();
                                if ($temp_group) {
                                    foreach ($temp_group as $key => $value) {
                                        if ($key != 'id' && $key != 'name') {
                                            $query[$key] = $value;
                                            $new_temp[$key] = $value;
                                        }
                                    }
                                    if ($db->update($query, TBL_USERS, '`id` = ' . $work->clean_field($_GET['uid']))) {
                                        $temp = $new_temp;
                                    } else {
                                        log_in_file($db->error, DIE_IF_ERROR);
                                    }
                                } else {
                                    log_in_file('Unable to get the group', DIE_IF_ERROR);
                                }
                            } else {
                                log_in_file($db->error, DIE_IF_ERROR);
                            }
                        } else {
                            foreach ($temp as $key => $value) {
                                if ($key != 'id' && $key != 'login' && $key != 'password' && $key != 'real_name' && $key != 'email' && $key != 'avatar' && $key != 'date_regist' && $key != 'date_last_activ' && $key != 'date_last_logout' && $key != 'group') {
                                    if ($work->check_post(
                                        $key,
                                        true
                                    ) && ($_POST[$key] == 'on' || $_POST[$key] === true)) {
                                        $_POST[$key] = '1';
                                    } else {
                                        $_POST[$key] = '0';
                                    }
                                    if ($_POST[$key] != $value) {
                                        if ($db->update(
                                            array($key => $_POST[$key]),
                                            TBL_USERS,
                                            '`id` = ' . $work->clean_field($_GET['uid'])
                                        )) {
                                            $temp[$key] = $_POST[$key];
                                        } else {
                                            log_in_file($db->error, DIE_IF_ERROR);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if ($db->select('*', TBL_GROUP, '`id` !=0')) {
                        $group = $db->res_arr();
                        if ($group) {
                            foreach ($group as $key => $val) {
                                $template->add_string_ar(array(
                                    'D_GROUP_ID' => $val['id'],
                                    'D_GROUP_NAME' => $val['name'],
                                    'D_GROUP_SELECTED' => $val['id'] == $temp['group'] ? ' selected="selected"' : ''
                                ), 'GROUP_USER[' . $key . ']');
                            }

                            foreach ($temp as $key => $value) {
                                if ($key != 'id' && $key != 'login' && $key != 'password' && $key != 'real_name' && $key != 'email' && $key != 'avatar' && $key != 'date_regist' && $key != 'date_last_activ' && $key != 'date_last_logout' && $key != 'group') {
                                    $template->add_string('L_' . strtoupper($key), $lang['admin'][$key]);
                                    if ($value == 1 || $value == '1' || $value === true) {
                                        $template->add_string('D_' . strtoupper($key), ' checked="checked"');
                                    } else {
                                        $template->add_string('D_' . strtoupper($key), '');
                                    }
                                }
                            }
                            $template->add_if('FIND_USER', true);
                            $template->add_string_ar(array(
                                'L_LOGIN' => $lang['admin']['login'],
                                'L_EMAIL' => $lang['admin']['email'],
                                'L_REAL_NAME' => $lang['admin']['real_name'],
                                'L_AVATAR' => $lang['admin']['avatar'],
                                'L_GROUP' => $lang['main']['group'],
                                'L_USER_RIGHTS' => $lang['admin']['user_rights'],
                                'L_HELP_EDIT' => $lang['admin']['help_edit_user'],
                                'L_SAVE_USER' => $lang['admin']['save_user'],
                                'D_LOGIN' => $temp['login'],
                                'D_EMAIL' => $temp['email'],
                                'D_REAL_NAME' => $temp['real_name'],
                                'U_AVATAR' => $work->config['site_url'] . $work->config['avatar_folder'] . '/' . $temp['avatar']
                            ));
                        } else {
                            log_in_file('Unable to get the group', DIE_IF_ERROR);
                        }
                    } else {
                        log_in_file($db->error, DIE_IF_ERROR);
                    }
                } else {
                    log_in_file('Unable to get the user', DIE_IF_ERROR);
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        } else {
            if ($work->check_post('submit', true, true) && $work->check_post('search_user', true, true)) {
                if ($_POST['search_user'] == '*') {
                    $_POST['search_user'] = '%';
                }

                if ($db->select(
                    '*',
                    TBL_USERS,
                    '`real_name` LIKE \'%' . $work->clean_field($_POST['search_user']) . '%\''
                )) {
                    $find = $db->res_arr();
                    if ($find) {
                        foreach ($find as $key => $val) {
                            $template->add_string_ar(array(
                                'D_FIND_USER' => $val['real_name'],
                                'U_FIND_USER' => $work->config['site_url'] . '?action=admin&amp;subact=admin_user&amp;uid=' . $val['id']
                            ), 'FIND_USER[' . $key . ']');
                        }
                    } else {
                        $template->add_string_ar(array(
                            'D_FIND_USER' => $lang['admin']['no_find_user'],
                            'U_FIND_USER' => '#'
                        ), 'FIND_USER[0]');
                    }
                } else {
                    log_in_file($db->error, DIE_IF_ERROR);
                }

                $template->add_if('NEED_USER', true);
                if ($_POST['search_user'] == '%') {
                    $_POST['search_user'] = '*';
                }
            }

            $template->add_if('NEED_FIND', true);
            $template->add_string_ar(array(
                'L_SEARCH_USER' => $lang['admin']['search_user'],
                'L_HELP_SEARCH' => $lang['admin']['help_search_user'],
                'L_SEARCH' => $lang['main']['search'],
                'D_SEARCH_USER' => $work->check_post('search_user', true, true) ? $work->clean_field(
                    $_POST['search_user']
                ) : ''
            ));
        }

        $action = '';
        $title = $lang['admin']['admin_user'];
    } elseif ($work->check_get(
        'subact',
        true,
        true
    ) && $_GET['subact'] == 'admin_group') { // иначе если запрошено Управление группами, то...
        $template->add_case('ADMIN_BLOCK', 'ADMIN_GROUP');
        $template->add_string('L_NAME_BLOCK', $lang['admin']['title'] . ' - ' . $lang['admin']['admin_group']);

        if ($work->check_post('submit', true, true) && $work->check_post('id_group', true, false, '^[0-9]+\$')) {
            if ($db->select('*', TBL_GROUP, '`id` = ' . $work->clean_field($_POST['id_group']))) {
                $temp = $db->res_row();
                if ($temp) {
                    if ($work->check_post(
                        'name_group',
                        true,
                        true,
                        REG_NAME
                    ) && $_POST['name_group'] != $temp['name']) {
                        if ($db->update(
                            array('name' => $work->clean_field($_POST['name_group'])),
                            TBL_GROUP,
                            '`id` = ' . $work->clean_field($_POST['id_group'])
                        )) {
                            $temp['name'] = $work->clean_field($_POST['name_group']);
                        } else {
                            log_in_file($db->error, DIE_IF_ERROR);
                        }
                    }
                    foreach ($temp as $key => $value) {
                        if ($key != 'id' && $key != 'name') {
                            if ($work->check_post(
                                $key,
                                true,
                                true
                            ) && ($_POST[$key] == 'on' || $_POST[$key] === true)) {
                                $_POST[$key] = '1';
                            } else {
                                $_POST[$key] = '0';
                            }
                            if ($_POST[$key] != $value) {
                                if ($db->update(
                                    array($key => $_POST[$key]),
                                    TBL_GROUP,
                                    '`id` = ' . $work->clean_field($_POST['id_group'])
                                )) {
                                    $temp[$key] = $_POST[$key];
                                } else {
                                    log_in_file($db->error, DIE_IF_ERROR);
                                }
                            }
                        }
                    }
                    $_POST['group'] = $work->clean_field($_POST['id_group']);
                } else {
                    log_in_file('Unable to get the group', DIE_IF_ERROR);
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        }

        if ($work->check_post('submit', true, true) && $work->check_post('group', true, false, '^[0-9]+\$')) {
            if ($db->select('*', TBL_GROUP, '`id` = ' . $work->clean_field($_POST['group']))) {
                $temp = $db->res_row();
                if ($temp) {
                    foreach ($temp as $key => $value) {
                        if ($key != 'id' && $key != 'name') {
                            $template->add_string('L_' . strtoupper($key), $lang['admin'][$key]);
                            if ($value == 1) {
                                $template->add_string('D_' . strtoupper($key), ' checked="checked"');
                            } else {
                                $template->add_string('D_' . strtoupper($key), '');
                            }
                        }
                    }
                    $template->add_if('EDIT_GROUP', true);
                    $template->add_string_ar(array(
                        'L_NAME_GROUP' => $lang['main']['group'],
                        'L_GROUP_RIGHTS' => $lang['admin']['group_rights'],
                        'L_SAVE_GROUP' => $lang['admin']['save_group'],

                        'D_ID_GROUP' => $temp['id'],
                        'D_NAME_GROUP' => $temp['name']
                    ));
                } else {
                    log_in_file('Unable to get the group', DIE_IF_ERROR);
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        } else {
            if ($db->select('*', TBL_GROUP)) {
                $group = $db->res_arr();
                if ($group) {
                    foreach ($group as $key => $val) {
                        $template->add_string_ar(array(
                            'D_ID_GROUP' => $val['id'],
                            'D_NAME_GROUP' => $val['name']
                        ), 'SELECT_GROUP[' . $key . ']');
                    }
                    $template->add_if('SELECT_GROUP', true);
                    $template->add_string_ar(array(
                        'L_SELECT_GROUP' => $lang['admin']['select_group'],
                        'L_EDIT' => $lang['admin']['edit_group']
                    ));
                } else {
                    log_in_file('Unable to get the group', DIE_IF_ERROR);
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        }

        $action = '';
        $title = $lang['admin']['admin_group'];
    } else {
        $select_subact = array();
        $select_subact[0]['url'] = $work->config['site_url'] . '?action=admin&amp;subact=settings';
        $select_subact[0]['txt'] = $lang['admin']['settings'];
        $select_subact[1]['url'] = $work->config['site_url'] . '?action=admin&amp;subact=admin_user';
        $select_subact[1]['txt'] = $lang['admin']['admin_user'];
        $select_subact[2]['url'] = $work->config['site_url'] . '?action=admin&amp;subact=admin_group';
        $select_subact[2]['txt'] = $lang['admin']['admin_group'];
        $template->add_case('ADMIN_BLOCK', 'ADMIN_START');
        $template->add_if('SESSION_ADMIN_ON', true);
        $template->add_string_ar(array(
            'L_NAME_BLOCK' => $lang['admin']['title'],
            'L_SELECT_SUBACT' => $lang['admin']['select_subact']
        ));
        foreach ($select_subact as $key => $val) {
            $template->add_string_ar(array(
                'L_SUBACT' => $val['txt'],
                'U_SUBACT' => $val['url']
            ), 'SELECT_SUBACT[' . $key . ']');
        }
        $action = 'admin'; // активный пункт меню - admin
        $title = $lang['admin']['title']; // дполнительный заговловок - Администрирование
    }
} elseif ((!isset($_SESSION['admin_on']) || $_SESSION['admin_on'] !== true) && $user->user['admin'] == true) { // иначе если сессия для Админа не открыта, но пользователь имеет право её открыть, то...
    $template->add_case('ADMIN_BLOCK', 'ADMIN_START');
    $template->add_string_ar(array(
        'L_NAME_BLOCK' => $lang['admin']['title'],
        'L_ENTER_ADMIN_PASS' => $lang['admin']['admin_pass'],
        'L_ENTER' => $lang['main']['enter']
    ));
    $action = 'admin'; // активный пункт меню - admin
    $title = $lang['admin']['title']; // дполнительный заговловок - Администрирование
} else { // иначе...
    Location('Location: ' . $work->config['site_url']);
    log_in_file('Hack attempt!', DIE_IF_ERROR);
}
/// @endcond
