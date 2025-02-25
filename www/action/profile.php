<?php

/**
 * @file        action/profile.php
 * @brief       Работа с пользователями.
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-02-25
 * @details     Обработка процедур входа/выхода/регистрации/изменения и просмотра профиля пользователя.
 */

namespace PhotoRigma\Action;

use PhotoRigma\Classes\Work;

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

// Устанавливаем файл шаблона
$template->template_file = 'profile.html';

// Проверяем и получаем значение subact через check_input
$subact = $work->check_input('_GET', 'subact', [
    'isset' => true,
    'empty' => true,
    'regexp' => '/^[_A-Za-z0-9\-]+$/',
])
    ? $_GET['subact']
    : ($user->session['login_id'] == 0 ? 'login' : 'logout');

// Определяем URL для перенаправления
$redirect_url = !empty($_SERVER['HTTP_REFERER'])
    ? filter_var($_SERVER['HTTP_REFERER'], FILTER_VALIDATE_URL) ?: $work->config['site_url']
    : $work->config['site_url'];

if ($subact == 'saveprofile') {
    $subact = 'profile';
    if ($user->session['login_id'] == 0) {
        header('Location: ' . $redirect_url);
        \PhotoRigma\Include\log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: saveprofile, Пользователь ID: {$user->session['login_id']}"
        );
    } else {
        // Проверяем параметр uid через check_input
        if ($work->check_input('_GET', 'uid', [
            'isset' => true,
            'empty' => true,
            'regexp' => '/^[0-9]+$/',
            'not_zero' => true
        ])) {
            $user_id = $_GET['uid'];
        } else {
            $user_id = $user->session['login_id'];
        }
        // Проверяем права доступа
        if ($user_id === $user->session['login_id'] || $user->user['admin'] == true) {
            // Запрос с плейсхолдерами
            $db->select('*', TBL_USERS, [
                'where' => '`id` = :user_id',
                'params' => [':user_id' => $user_id]
            ]);
            $user_data = $db->res_row();
            if (!$user_data) {
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные пользователя | ID: {$user_id}"
                );
            } else {
                $max_size_php = $work->return_bytes(ini_get('post_max_size'));
                $max_size = $work->return_bytes($work->config['max_file_size']);
                if ($max_size > $max_size_php) {
                    $max_size = $max_size_php;
                }
                // Будущий метод update_user_data
                // Входные данные:
                // - $user_id (ID редактируемого пользователя)
                // - $_POST (данные из формы: пароль, email, имя)
                // - $_FILES (данные загруженного аватара)
                // - $max_size (максимальный размер файла для аватара)
                // - $work - (класс вспомогательных методов)
                $new_user_data = [];

                // Проверяем пароль
                if ($user_id !== $user->session['login_id'] || (
                    $work->check_input('_POST', 'password', [
                        'isset' => true,
                        'empty' => true
                    ]) && password_verify($_POST['password'], $user_data['password'])
                )) {
                    if ($work->check_input('_POST', 'edit_password', [
                        'isset' => true,
                        'empty' => true
                    ])) {
                        $new_user_data['password'] = match (true) {
                            $_POST['re_password'] !== $_POST['edit_password'] => $user_data['password'],
                            default => password_hash($_POST['re_password'], PASSWORD_BCRYPT),
                        };
                    } else {
                        $new_user_data['password'] = $user_data['password'];
                    }

                    // Проверяем email
                    if ($work->check_input('_POST', 'email', [
                        'isset' => true,
                        'empty' => true,
                        'regexp' => REG_EMAIL
                    ])) {
                        $filtered_email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

                        // Объединяем проверку email и real_name в один запрос
                        $db->select('SUM(`email` = :email) as `email_count`, SUM(`real_name` = :real_name) as `real_count`', TBL_USERS, [
                            'where' => '`id` != :user_id',
                            'params' => [':user_id' => $user_id, ':email' => $filtered_email, ':real_name' => $_POST['real_name']]
                        ]);
                        $counts = $db->res_row();

                        $new_user_data['email'] = match (true) {
                            $counts && isset($counts['email_count']) && $counts['email_count'] > 0 => $user_data['email'],
                            default => $filtered_email,
                        };

                        $new_user_data['real_name'] = match (true) {
                            $counts && isset($counts['real_count']) && $counts['real_count'] > 0 => $user_data['real_name'],
                            default => $_POST['real_name'],
                        };
                    } else {
                        $new_user_data['email'] = $user_data['email'];
                        $new_user_data['real_name'] = $user_data['real_name'];
                    }

                    // Обработка аватара через check_input
                    if (!$work->check_input('_POST', 'delete_avatar', [
                        'isset' => true,
                        'empty' => true
                    ]) || $_POST['delete_avatar'] != 'true') {
                        // Будущий приват-метод edit_avatar
                        // Входные данные:
                        // - $_FILES (данные загруженного аватара)
                        // - $max_size (максимальный размер файла для аватара)
                        // - $work - (класс вспомогательных методов)
                        if ($work->check_input('_FILES', 'file_avatar', [
                            'isset' => true,
                            'empty' => true,
                            'max_size' => $max_size
                        ])) {
                            // Генерация имени файла
                            $file_avatar = time() . '_' . $work->encodename(basename($_FILES['file_avatar']['name']));
                            $path_avatar = $work->config['site_dir'] . $work->config['avatar_folder'] . '/' . $file_avatar;

                            // Перемещение загруженного файла
                            if (move_uploaded_file($_FILES['file_avatar']['tmp_name'], $path_avatar)) {
                                // Корректировка расширения файла
                                $fixed_path = $work->fix_file_extension($path_avatar);
                                // Если расширение изменилось, обновляем имя файла
                                if ($fixed_path !== $path_avatar) {
                                    rename($path_avatar, $fixed_path);
                                    $file_avatar = basename($fixed_path);
                                }
                                $new_user_data['avatar'] = $file_avatar;
                                if ($user_data['avatar'] != 'no_avatar.jpg') {
                                    @unlink($work->config['site_dir'] . $work->config['avatar_folder'] . '/' . $user_data['avatar']);
                                }
                            } else {
                                $new_user_data['avatar'] = $user_data['avatar'];
                            }
                        } else {
                            $new_user_data['avatar'] = $user_data['avatar'];
                        }
                        // Результаты на выходе:
                        // - $user_data['avatar'] (Новый аватар пользователя)
                        // Конец приват-метода edit_avatar
                    } else {
                        if ($user_data['avatar'] != 'no_avatar.jpg') {
                            @unlink($work->config['site_dir'] . $work->config['avatar_folder'] . '/' . $user_data['avatar']);
                        }
                        $new_user_data['avatar'] = 'no_avatar.jpg';
                    }

                    // Обновление данных пользователя
                    $db->update($new_user_data, TBL_USERS, [
                        'where' => '`id` = :user_id',
                        'params' => [':user_id' => $user_id]
                    ]);
                    $rows = $db->get_affected_rows();
                    // Результаты на выходе:
                    // - $rows (количество затронутых строк в базе данных)
                    // Конец метода update_user_data
                    if ($rows > 0) {
                        $user = new User($db, $user->session);
                        $work->set_user($user);
                        header('Location: ' . $redirect_url);
                        \PhotoRigma\Include\log_in_file(
                            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: saveprofile, Пользователь ID: {$user->session['login_id']}"
                        );
                    } else {
                        throw new \RuntimeException(
                            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось обновить данные пользователя | ID: {$user_id}"
                        );
                    }
                }
            }
        } else {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Отказано в праве редактирования пользователя | Редактируемый ID: {$user_id}, Текущий ID: {$user->session['login_id']}"
            );
        }
    }
}

if ($subact == 'logout') {
    if ($user->session['login_id'] == 0) {
        header('Location: ' . $redirect_url);
        \PhotoRigma\Include\log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: logout, Пользователь ID: {$user->session['login_id']}"
        );
    } else {
        if (!$db->update(array('date_last_activ' => null, 'date_last_logout' => date('Y-m-d H:m:s')), TBL_USERS, '`id` = ' . $user->session['login_id'])) {
            log_in_file($db->error, DIE_IF_ERROR);
        }
        $user->session['login_id'] = 0;
        $user->session['admin_on'] = false;
        $user = new user();
        @session_destroy();
        header('Location: ' . $work->config['site_url']);
        \PhotoRigma\Include\log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: logout, Пользователь ID: {$user->session['login_id']}"
        );
    }
} /* elseif ($subact == 'forgot') // Восстановление пароля будет готово после реализации работы с почтой
{
    $redirect_time = 3;
    $redirect_message = $work->lang['main']['redirect_title'];
} */ elseif ($subact == 'regist') {
    if ($work->check_session('login_id', true, true, '^[0-9]+\$', true)) {
        header('Location: ' . $work->config['site_url']);
        \PhotoRigma\Include\log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: regist, Пользователь ID: {$user->session['login_id']}"
        );
    } else {
        $template->add_case('PROFILE_BLOCK', 'REGIST');
        $title = $work->lang['profile']['regist'];
        $action = 'regist';
        $captcha = $work->gen_captcha();
        $user->session['captcha'] = $captcha['answer'];
        $template->add_if_ar(array(
            'ERROR_LOGIN'       => false,
            'ERROR_PASSWORD'    => false,
            'ERROR_RE_PASSWORD' => false,
            'ERROR_EMAIL'       => false,
            'ERROR_REAL_NAME'   => false,
            'ERROR_CAPTCHA'     => false
        ));
        $template->add_string_ar(array(
            'NAME_BLOCK'         => $work->lang['profile']['regist'],
            'L_LOGIN'            => $work->lang['profile']['login'],
            'L_PASSWORD'         => $work->lang['profile']['password'],
            'L_RE_PASSWORD'      => $work->lang['profile']['re_password'],
            'L_EMAIL'            => $work->lang['profile']['email'],
            'L_REAL_NAME'        => $work->lang['profile']['real_name'],
            'L_REGISTER'         => $work->lang['profile']['register'],
            'L_CAPTCHA'          => $work->lang['profile']['captcha'],
            'U_REGISTER'         => $work->config['site_url'] . '?action=profile&amp;subact=register',
            'D_CAPTCHA_QUESTION' => $captcha['question'],
            'D_LOGIN'            => '',
            'D_EMAIL'            => '',
            'D_REAL_NAME'        => ''
        ));
        if (isset($user->session['error']) && !empty($user->session['error']) && is_array($user->session['error'])) {
            foreach ($user->session['error'] as $key => $val) {
                $template->add_if('ERROR_' . strtoupper($key), $val['if']);
                $template->add_string_ar(array(
                    'D_' . strtoupper($key)       => $val['data'],
                    'D_ERROR_' . strtoupper($key) => (isset($val['text']) ? $val['text'] : '')
                ));
            }
            unset($user->session['error']);
        }
    }
} elseif ($subact == 'register') {
    if ($work->check_session('login_id', true, true, '^[0-9]+\$', true)) {
        header('Location: ' . $redirect_url);
        \PhotoRigma\Include\log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: register, Пользователь ID: {$user->session['login_id']}"
        );
    } else {
        $error = false;
        if ($work->check_post('login', true, true, REG_LOGIN)) {
            $user->session['error']['login']['if'] = false;
            $user->session['error']['login']['data'] = $_POST['login'];
            $register['login'] = $_POST['login'];
        } else {
            $error = true;
            $user->session['error']['login']['if'] = true;
            $user->session['error']['login']['data'] = '';
            $user->session['error']['login']['text'] = $work->lang['profile']['error_login'];
        }

        if ($work->check_post('password', true, true)) {
            $user->session['error']['password']['if'] = false;
            $user->session['error']['password']['data'] = '';
            $register['password'] = $_POST['password'];
        } else {
            $error = true;
            $user->session['error']['password']['if'] = true;
            $user->session['error']['password']['data'] = '';
            $user->session['error']['password']['text'] = $work->lang['profile']['error_password'];
        }

        if ($work->check_post('re_password', true, true) && $_POST['re_password'] === $register['password']) {
            $user->session['error']['re_password']['if'] = false;
            $user->session['error']['re_password']['data'] = '';
            $register['password'] = md5($register['password']);
        } else {
            $error = true;
            $user->session['error']['re_password']['if'] = true;
            $user->session['error']['re_password']['data'] = '';
            if ($user->session['error']['password']['if']) {
                $user->session['error']['password']['text'] = $work->lang['profile']['error_password'];
                $user->session['error']['re_password']['text'] = $work->lang['profile']['error_password'];
            } else {
                $user->session['error']['password']['text'] = $work->lang['profile']['error_re_password'];
                $user->session['error']['re_password']['text'] = $work->lang['profile']['error_re_password'];
                $user->session['error']['password']['if'] = true;
            }
        }

        if ($work->check_post('email', true, true, REG_EMAIL)) {
            $user->session['error']['email']['if'] = false;
            $user->session['error']['email']['data'] = $_POST['email'];
            $register['email'] = $_POST['email'];
        } else {
            $error = true;
            $user->session['error']['email']['if'] = true;
            $user->session['error']['email']['data'] = '';
            $user->session['error']['email']['text'] = $work->lang['profile']['error_email'];
        }

        if ($work->check_post('real_name', true, true, REG_NAME)) {
            $user->session['error']['real_name']['if'] = false;
            $user->session['error']['real_name']['data'] = $_POST['real_name'];
            $register['real_name'] = $_POST['real_name'];
        } else {
            $error = true;
            $user->session['error']['real_name']['if'] = true;
            $user->session['error']['real_name']['data'] = '';
            $user->session['error']['real_name']['text'] = $work->lang['profile']['error_real_name'];
        }

        if ($work->check_post('captcha', true, true, '^[0-9]+\$') && $_POST['captcha'] == $user->session['captcha']) {
            $user->session['error']['captcha']['if'] = false;
            $user->session['error']['captcha']['data'] = '';
        } else {
            $error = true;
            $user->session['error']['captcha']['if'] = true;
            $user->session['error']['captcha']['data'] = '';
            $user->session['error']['captcha']['text'] = $work->lang['profile']['error_captcha'];
        }
        unset($user->session['captcha']);

        if ($db->select('COUNT(*) as `login_count`', TBL_USERS, '`login` = \'' . $register['login'] . '\'')) {
            $temp = $db->res_row();
            if (isset($temp['login_count']) && $temp['login_count'] > 0) {
                $error = true;
                if ($user->session['error']['login']['if']) {
                    $user->session['error']['login']['text'] .= ' ' . $work->lang['profile']['error_login_exists'];
                } else {
                    $user->session['error']['login']['if'] = true;
                    $user->session['error']['login']['data'] = '';
                    $user->session['error']['login']['text'] = $work->lang['profile']['error_login_exists'];
                }
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }

        if ($db->select('COUNT(*) as `email_count`', TBL_USERS, '`email` = \'' . $register['email'] . '\'')) {
            $temp = $db->res_row();
            if (isset($temp['email_count']) && $temp['email_count'] > 0) {
                $error = true;
                if ($user->session['error']['email']['if']) {
                    $user->session['error']['email']['text'] .= ' ' . $work->lang['profile']['error_email_exists'];
                } else {
                    $user->session['error']['email']['if'] = true;
                    $user->session['error']['email']['data'] = '';
                    $user->session['error']['email']['text'] = $work->lang['profile']['error_email_exists'];
                }
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }

        if ($db->select('COUNT(*) as `real_count`', TBL_USERS, '`real_name` = \'' . $register['real_name'] . '\'')) {
            $temp = $db->res_row();
            if (isset($temp['real_count']) && $temp['real_count'] > 0) {
                $error = true;
                if ($user->session['error']['real_name']['if']) {
                    $user->session['error']['real_name']['text'] .= ' ' . $work->lang['profile']['error_real_name_exists'];
                } else {
                    $user->session['error']['real_name']['if'] = true;
                    $user->session['error']['real_name']['data'] = '';
                    $user->session['error']['real_name']['text'] = $work->lang['profile']['error_real_name_exists'];
                }
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }

        if ($error) {
            header('Location: ' . $redirect_url);
            \PhotoRigma\Include\log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: register, Пользователь ID: {$user->session['login_id']}"
            );
        } else {
            unset($user->session['error']);
            $query = array();
            $query = $register;
            $query['group'] = DEFAULT_GROUP;

            if ($db->select('*', TBL_GROUP, '`id` = ' . DEFAULT_GROUP)) {
                $temp = $db->res_row();
                if ($temp) {
                    foreach ($temp as $key => $value) {
                        if ($key != 'id' && $key != 'name') {
                            $query[$key] = $value;
                        }
                    }
                    if ($db->insert($query, TBL_USERS)) {
                        $new_user = $db->insert_id;
                        if ($new_user != 0) {
                            $user->session['login_id'] = $new_user;
                            header('Location: ' . $work->config['site_url'] . '?action=profile&amp;subact=profile');
                            \PhotoRigma\Include\log_in_file(
                                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: register, Пользователь ID: {$user->session['login_id']}"
                            );
                        } else {
                            header('Location: ' . $redirect_url);
                            \PhotoRigma\Include\log_in_file(
                                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: register, Пользователь ID: {$user->session['login_id']}"
                            );
                        }
                    } else {
                        log_in_file($db->error, DIE_IF_ERROR);
                    }
                } else {
                    log_in_file('Unable to get the default group', DIE_IF_ERROR);
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        }
    }
} elseif ($subact == 'login') {
    if ($work->check_session('login_id', true, true, '^[0-9]+\$', true)) {
        header('Location: ' . $work->config['site_url']);
        \PhotoRigma\Include\log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: login, Пользователь ID: {$user->session['login_id']}"
        );
    } else {
        $user->session['login_id'] = 0;
        if ($work->check_post('login', true, true, REG_LOGIN) && $work->check_post('password', true, true)) {
            if ($db->select(array('id', 'login', 'password'), TBL_USERS, '`login` = \'' . $_POST['login'] . '\'')) {
                $temp_user = $db->res_row();
                if ($temp_user) {
                    if (md5($_POST['password']) === $temp_user['password']) {
                        $user->session['login_id'] = $temp_user['id'];
                        $user = new user();
                        header('Location: ' . $redirect_url);
                        \PhotoRigma\Include\log_in_file(
                            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: login, Пользователь ID: {$user->session['login_id']}"
                        );
                    } else {
                        header('Location: ' . $redirect_url);
                        \PhotoRigma\Include\log_in_file(
                            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: login, Пользователь ID: {$user->session['login_id']}"
                        );
                    }
                } else {
                    header('Location: ' . $redirect_url);
                    \PhotoRigma\Include\log_in_file(
                        __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: login, Пользователь ID: {$user->session['login_id']}"
                    );
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        } else {
            header('Location: ' . $redirect_url);
            \PhotoRigma\Include\log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: login, Пользователь ID: {$user->session['login_id']}"
            );
        }
    }
} elseif ($subact == 'profile') {
    if (((isset($user->session['login_id']) && $user->session['login_id'] == 0) || !isset($user->session['login_id'])) && (!isset($_GET['uid']) || empty($_GET['uid']))) {
        header('Location: ' . $work->config['site_url']);
        \PhotoRigma\Include\log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: profile, Пользователь ID: {$user->session['login_id']}"
        );
    } else {
        $action = 'profile';
        if ($work->check_get('uid', true, true, '^[0-9]+\$', true)) {
            $uid = $_GET['uid'];
        } else {
            $uid = $user->session['login_id'];
        }

        if ($uid === $user->session['login_id'] || $user->user['admin'] == true) {
            if ($db->select('*', TBL_USERS, '`id` = ' . $uid)) {
                $temp = $db->res_row();
                if ($temp) {
                    $name_block = $work->lang['profile']['edit_profile'] . ' ' . $temp['real_name'];
                    $max_size_php = $work->return_bytes(ini_get('post_max_size'));
                    $max_size = $work->return_bytes($work->config['max_file_size']);
                    if ($max_size > $max_size_php) {
                        $max_size = $max_size_php;
                    }

                    if ($uid === $user->session['login_id']) {
                        $confirm_password = true;
                    } else {
                        $confirm_password = false;
                    }

                    if ($db->select('*', TBL_GROUP, '`id` = ' . $temp['group'])) {
                        $temp2 = $db->res_row();
                        if (!$temp2) {
                            $temp['group'] = 0;
                            if ($db->select('*', TBL_GROUP, '`id` = ' . $temp['group'])) {
                                $temp2 = $db->res_row();
                                if (!$temp2) {
                                    log_in_file('Unable to get the guest group', DIE_IF_ERROR);
                                }
                            } else {
                                log_in_file($db->error, DIE_IF_ERROR);
                            }
                        }
                    } else {
                        log_in_file($db->error, DIE_IF_ERROR);
                    }

                    $template->add_case('PROFILE_BLOCK', 'PROFILE_EDIT');
                    $title = $name_block;
                    $template->add_if('NEED_PASSWORD', $confirm_password);
                    $template->add_string_ar(array(
                        'NAME_BLOCK'      => $name_block,
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

                        'D_LOGIN'         => $temp['login'],
                        'D_EMAIL'         => $temp['email'],
                        'D_REAL_NAME'     => $temp['real_name'],
                        'D_MAX_FILE_SIZE' => $max_size,
                        'D_GROUP'         => $temp2['name'],

                        'U_AVATAR'        => $work->config['site_url'] . $work->config['avatar_folder'] . '/' . $temp['avatar'],
                        'U_PROFILE_EDIT'  => $work->config['site_url'] . '?action=profile&amp;subact=saveprofile&amp;uid=' . $uid
                    ));
                } else {
                    header('Location: ' . $work->config['site_url']);
                    \PhotoRigma\Include\log_in_file(
                        __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: profile, Пользователь ID: {$user->session['login_id']}"
                    );
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        } else {
            if ($db->select('*', TBL_USERS, '`id` = ' . $uid)) {
                $temp = $db->res_row();
                if ($temp) {
                    $name_block = $work->lang['profile']['profile'] . ' ' . $temp['real_name'];
                    $action = '';

                    if ($db->select('*', TBL_GROUP, '`id` = ' . $temp['group'])) {
                        $temp2 = $db->res_row();
                        if (!$temp2) {
                            $temp['group'] = 0;
                            if ($db->select('*', TBL_GROUP, '`id` = ' . $temp['group'])) {
                                $temp2 = $db->res_row();
                                if (!$temp2) {
                                    log_in_file('Unable to get the guest group', DIE_IF_ERROR);
                                }
                            } else {
                                log_in_file($db->error, DIE_IF_ERROR);
                            }
                        }
                    } else {
                        log_in_file($db->error, DIE_IF_ERROR);
                    }
                    $template->add_case('PROFILE_BLOCK', 'PROFILE_VIEW');
                    $title = $name_block;
                    $template->add_string_ar(array(
                        'NAME_BLOCK'  => $name_block,
                        'L_EMAIL'     => $work->lang['profile']['email'],
                        'L_REAL_NAME' => $work->lang['profile']['real_name'],
                        'L_AVATAR'    => $work->lang['profile']['avatar'],
                        'L_GROUP'     => $work->lang['main']['group'],

                        'D_EMAIL'     => $work->filt_email($temp['email']),
                        'D_REAL_NAME' => $temp['real_name'],
                        'D_GROUP'     => $temp2['name'],

                        'U_AVATAR'    => $work->config['site_url'] . $work->config['avatar_folder'] . '/' . $temp['avatar']
                    ));
                    $array_data = array();
                    $array_data = array();
                } else {
                    header('Location: ' . $work->config['site_url']);
                    \PhotoRigma\Include\log_in_file(
                        __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: profile, Пользователь ID: {$user->session['login_id']}"
                    );
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        }
    }
} else {
    header('Location: ' . $work->config['site_url']);
    \PhotoRigma\Include\log_in_file(
        __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: неопределено, Пользователь ID: {$user->session['login_id']}"
    );
}
