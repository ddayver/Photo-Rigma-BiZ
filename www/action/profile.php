<?php

/**
 * @file        action/profile.php
 * @brief       Работа с пользователями.
 * @author      Dark Dayver
 * @version     0.2.0
 * @date        28/03-2012
 * @details     Обработка процедур входа/выхода/регистрации/изменения и просмотра профиля пользователя.
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

$template->template_file = 'profile.html';

if (!$work->check_get('subact', true, true, '^[_A-Za-z0-9-]?\$')) {
    if ($_SESSION['login_id'] == 0) {
        $subact = 'login';
    } else {
        $subact = 'logout';
    }
} else {
    $subact = $_GET['subact'];
}

if (!empty($_SERVER['HTTP_REFERER'])) {
    $redirect_url = $_SERVER['HTTP_REFERER'];
} else {
    $redirect_url = $work->config['site_url'];
}

if ($subact == 'saveprofile') {
    $subact = 'profile';

    if ($_SESSION['login_id'] == 0) {
        header('Location: ' . $redirect_url);
        log_in_file('Hack attempt!');
    } else {
        if ($work->check_get('uid', true, true, '^[0-9]+\$', true)) {
            $uid = $_GET['uid'];
        } else {
            $uid = $_SESSION['login_id'];
        }

        if ($uid === $_SESSION['login_id'] || $user->user['admin'] == true) {
            if ($db->select('*', TBL_USERS, '`id` = ' . $uid)) {
                $temp = $db->res_row();
                if ($temp) {
                    $new_data = array();
                    $max_size_php = $work->return_bytes(ini_get('post_max_size'));
                    $max_size = $work->return_bytes($work->config['max_file_size']);
                    if ($max_size > $max_size_php) {
                        $max_size = $max_size_php;
                    }

                    if ($uid !== $_SESSION['login_id'] || ($work->check_post('password', true, true) && md5($_POST['password']) === $temp['password'])) {
                        if ($work->check_post('edit_password', true, true)) {
                            if ($_POST['re_password'] !== $_POST['edit_password']) {
                                $new_data['password'] = $temp['password'];
                            } else {
                                $new_data['password'] = md5($_POST['re_password']);
                            }
                        } else {
                            $new_data['password'] = $temp['password'];
                        }

                        if ($work->check_post('email', true, true, REG_EMAIL)) {
                            if ($db->select('COUNT(*) as `email_count`', TBL_USERS, '`id` != ' . $uid . ' AND `email` = \'' . $_POST['email'] . '\'')) {
                                $email_count = $db->res_row();
                                if (isset($email_count['email_count']) && $email_count['email_count'] > 0) {
                                    $new_data['email'] = $temp['email'];
                                } else {
                                    $new_data['email'] = $_POST['email'];
                                }
                            } else {
                                log_in_file($db->error, DIE_IF_ERROR);
                            }
                        } else {
                            $new_data['email'] = $temp['email'];
                        }

                        if ($work->check_post('real_name', true, true, REG_NAME)) {
                            if ($db->select('COUNT(*) as `real_count`', TBL_USERS, '`id` != ' . $uid . ' AND `real_name` = \'' . $_POST['real_name'] . '\'')) {
                                $real_count = $db->res_row();
                                if (isset($real_count['real_count']) && $real_count['real_count'] > 0) {
                                    $new_data['real_name'] = $temp['real_name'];
                                } else {
                                    $new_data['real_name'] = $_POST['real_name'];
                                }
                            } else {
                                log_in_file($db->error, DIE_IF_ERROR);
                            }
                        } else {
                            $new_data['real_name'] = $temp['real_name'];
                        }

                        if (!$work->check_post('delete_avatar', true, true) || $_POST['delete_avatar'] != 'true') {
                            if (isset($_FILES['file_avatar']) && $_FILES['file_avatar']['error'] == 0 && $_FILES['file_avatar']['size'] > 0 && $_FILES['file_avatar']['size'] <= $max_size && mb_eregi('(gif|jpeg|png)$', $_FILES['file_avatar']['type'])) {
                                $avatar_size = getimagesize($_FILES['file_avatar']['tmp_name']);
                                $file_avatar = time() . '_' . $work->encodename(basename($_FILES['file_avatar']['name'])) . ($_FILES['file_avatar']['type'] == 'jpeg' ? '.jpg' : '.' . $_FILES['file_avatar']['type']);

                                if ($avatar_size[0] <= $work->config['max_avatar_w'] && $avatar_size[1] <= $work->config['max_avatar_h']) {
                                    $path_avatar = $work->config['site_dir'] . $work->config['avatar_folder'] . '/' . $file_avatar;
                                    if (move_uploaded_file($_FILES['file_avatar']['tmp_name'], $path_avatar)) {
                                        $new_data['avatar'] = $file_avatar;
                                        if ($temp['avatar'] != 'no_avatar.jpg') {
                                            @unlink($work->config['site_dir'] . $work->config['avatar_folder'] . '/' . $temp['avatar']);
                                        }
                                    } else {
                                        $new_data['avatar'] = $temp['avatar'];
                                    }
                                } else {
                                    $new_data['avatar'] = $temp['avatar'];
                                }
                            } else {
                                $new_data['avatar'] = $temp['avatar'];
                            }
                        } else {
                            if ($temp['avatar'] != 'no_avatar.jpg') {
                                @unlink($work->config['site_dir'] . $work->config['avatar_folder'] . '/' . $temp['avatar']);
                            }
                            $new_data['avatar'] = 'no_avatar.jpg';
                        }
                        if ($db->update($new_data, TBL_USERS, '`id` = ' . $uid)) {
                            $user = new user();
                            header('Location: ' . $redirect_url);
                            log_in_file('Hack attempt!');
                        } else {
                            log_in_file($db->error, DIE_IF_ERROR);
                        }
                    }
                } else {
                    log_in_file('Unable to get the user', DIE_IF_ERROR);
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        } else {
            log_in_file('Unable to edit user ' . $uid . ' from id=' . $_SESSION['login_id'], DIE_IF_ERROR);
        }
    }
}

if ($subact == 'logout') {
    if ($_SESSION['login_id'] == 0) {
        header('Location: ' . $redirect_url);
        log_in_file('Hack attempt!');
    } else {
        if (!$db->update(array('date_last_activ' => null, 'date_last_logout' => date('Y-m-d H:m:s')), TBL_USERS, '`id` = ' . $_SESSION['login_id'])) {
            log_in_file($db->error, DIE_IF_ERROR);
        }
        $_SESSION['login_id'] = 0;
        $_SESSION['admin_on'] = false;
        $user = new user();
        @session_destroy();
        header('Location: ' . $work->config['site_url']);
        log_in_file('Hack attempt!');
    }
}
/* elseif ($subact == 'forgot') // Восстановление пароля будет готово после реализации работы с почтой
{
    $redirect_time = 3;
    $redirect_message = $lang['main']['redirect_title'];
} */ elseif ($subact == 'regist') {
    if ($work->check_session('login_id', true, true, '^[0-9]+\$', true)) {
        header('Location: ' . $work->config['site_url']);
        log_in_file('Hack attempt!');
    } else {
        $template->add_case('PROFILE_BLOCK', 'REGIST');
        $title = $lang['profile']['regist'];
        $action = 'regist';
        $captcha = $work->gen_captcha();
        $_SESSION['captcha'] = $captcha['answer'];
        $template->add_if_ar(array(
            'ERROR_LOGIN'       => false,
            'ERROR_PASSWORD'    => false,
            'ERROR_RE_PASSWORD' => false,
            'ERROR_EMAIL'       => false,
            'ERROR_REAL_NAME'   => false,
            'ERROR_CAPTCHA'     => false
        ));
        $template->add_string_ar(array(
            'NAME_BLOCK'         => $lang['profile']['regist'],
            'L_LOGIN'            => $lang['profile']['login'],
            'L_PASSWORD'         => $lang['profile']['password'],
            'L_RE_PASSWORD'      => $lang['profile']['re_password'],
            'L_EMAIL'            => $lang['profile']['email'],
            'L_REAL_NAME'        => $lang['profile']['real_name'],
            'L_REGISTER'         => $lang['profile']['register'],
            'L_CAPTCHA'          => $lang['profile']['captcha'],
            'U_REGISTER'         => $work->config['site_url'] . '?action=profile&amp;subact=register',
            'D_CAPTCHA_QUESTION' => $captcha['question'],
            'D_LOGIN'            => '',
            'D_EMAIL'            => '',
            'D_REAL_NAME'        => ''
        ));
        if (isset($_SESSION['error']) && !empty($_SESSION['error']) && is_array($_SESSION['error'])) {
            foreach ($_SESSION['error'] as $key => $val) {
                $template->add_if('ERROR_' . strtoupper($key), $val['if']);
                $template->add_string_ar(array(
                    'D_' . strtoupper($key)       => $val['data'],
                    'D_ERROR_' . strtoupper($key) => (isset($val['text']) ? $val['text'] : '')
                ));
            }
            unset($_SESSION['error']);
        }
    }
} elseif ($subact == 'register') {
    if ($work->check_session('login_id', true, true, '^[0-9]+\$', true)) {
        header('Location: ' . $redirect_url);
        log_in_file('Hack attempt!');
    } else {
        $error = false;
        if ($work->check_post('login', true, true, REG_LOGIN)) {
            $_SESSION['error']['login']['if'] = false;
            $_SESSION['error']['login']['data'] = $_POST['login'];
            $register['login'] = $_POST['login'];
        } else {
            $error = true;
            $_SESSION['error']['login']['if'] = true;
            $_SESSION['error']['login']['data'] = '';
            $_SESSION['error']['login']['text'] = $lang['profile']['error_login'];
        }

        if ($work->check_post('password', true, true)) {
            $_SESSION['error']['password']['if'] = false;
            $_SESSION['error']['password']['data'] = '';
            $register['password'] = $_POST['password'];
        } else {
            $error = true;
            $_SESSION['error']['password']['if'] = true;
            $_SESSION['error']['password']['data'] = '';
            $_SESSION['error']['password']['text'] = $lang['profile']['error_password'];
        }

        if ($work->check_post('re_password', true, true) && $_POST['re_password'] === $register['password']) {
            $_SESSION['error']['re_password']['if'] = false;
            $_SESSION['error']['re_password']['data'] = '';
            $register['password'] = md5($register['password']);
        } else {
            $error = true;
            $_SESSION['error']['re_password']['if'] = true;
            $_SESSION['error']['re_password']['data'] = '';
            if ($_SESSION['error']['password']['if']) {
                $_SESSION['error']['password']['text'] = $lang['profile']['error_password'];
                $_SESSION['error']['re_password']['text'] = $lang['profile']['error_password'];
            } else {
                $_SESSION['error']['password']['text'] = $lang['profile']['error_re_password'];
                $_SESSION['error']['re_password']['text'] = $lang['profile']['error_re_password'];
                $_SESSION['error']['password']['if'] = true;
            }
        }

        if ($work->check_post('email', true, true, REG_EMAIL)) {
            $_SESSION['error']['email']['if'] = false;
            $_SESSION['error']['email']['data'] = $_POST['email'];
            $register['email'] = $_POST['email'];
        } else {
            $error = true;
            $_SESSION['error']['email']['if'] = true;
            $_SESSION['error']['email']['data'] = '';
            $_SESSION['error']['email']['text'] = $lang['profile']['error_email'];
        }

        if ($work->check_post('real_name', true, true, REG_NAME)) {
            $_SESSION['error']['real_name']['if'] = false;
            $_SESSION['error']['real_name']['data'] = $_POST['real_name'];
            $register['real_name'] = $_POST['real_name'];
        } else {
            $error = true;
            $_SESSION['error']['real_name']['if'] = true;
            $_SESSION['error']['real_name']['data'] = '';
            $_SESSION['error']['real_name']['text'] = $lang['profile']['error_real_name'];
        }

        if ($work->check_post('captcha', true, true, '^[0-9]+\$') && $_POST['captcha'] == $_SESSION['captcha']) {
            $_SESSION['error']['captcha']['if'] = false;
            $_SESSION['error']['captcha']['data'] = '';
        } else {
            $error = true;
            $_SESSION['error']['captcha']['if'] = true;
            $_SESSION['error']['captcha']['data'] = '';
            $_SESSION['error']['captcha']['text'] = $lang['profile']['error_captcha'];
        }
        unset($_SESSION['captcha']);

        if ($db->select('COUNT(*) as `login_count`', TBL_USERS, '`login` = \'' . $register['login'] . '\'')) {
            $temp = $db->res_row();
            if (isset($temp['login_count']) && $temp['login_count'] > 0) {
                $error = true;
                if ($_SESSION['error']['login']['if']) {
                    $_SESSION['error']['login']['text'] .= ' ' . $lang['profile']['error_login_exists'];
                } else {
                    $_SESSION['error']['login']['if'] = true;
                    $_SESSION['error']['login']['data'] = '';
                    $_SESSION['error']['login']['text'] = $lang['profile']['error_login_exists'];
                }
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }

        if ($db->select('COUNT(*) as `email_count`', TBL_USERS, '`email` = \'' . $register['email'] . '\'')) {
            $temp = $db->res_row();
            if (isset($temp['email_count']) && $temp['email_count'] > 0) {
                $error = true;
                if ($_SESSION['error']['email']['if']) {
                    $_SESSION['error']['email']['text'] .= ' ' . $lang['profile']['error_email_exists'];
                } else {
                    $_SESSION['error']['email']['if'] = true;
                    $_SESSION['error']['email']['data'] = '';
                    $_SESSION['error']['email']['text'] = $lang['profile']['error_email_exists'];
                }
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }

        if ($db->select('COUNT(*) as `real_count`', TBL_USERS, '`real_name` = \'' . $register['real_name'] . '\'')) {
            $temp = $db->res_row();
            if (isset($temp['real_count']) && $temp['real_count'] > 0) {
                $error = true;
                if ($_SESSION['error']['real_name']['if']) {
                    $_SESSION['error']['real_name']['text'] .= ' ' . $lang['profile']['error_real_name_exists'];
                } else {
                    $_SESSION['error']['real_name']['if'] = true;
                    $_SESSION['error']['real_name']['data'] = '';
                    $_SESSION['error']['real_name']['text'] = $lang['profile']['error_real_name_exists'];
                }
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }

        if ($error) {
            header('Location: ' . $redirect_url);
            log_in_file('Hack attempt!');
        } else {
            unset($_SESSION['error']);
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
                            $_SESSION['login_id'] = $new_user;
                            header('Location: ' . $work->config['site_url'] . '?action=profile&amp;subact=profile');
                            log_in_file('Hack attempt!');
                        } else {
                            header('Location: ' . $redirect_url);
                            log_in_file('Hack attempt!');
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
        log_in_file('Hack attempt!');
    } else {
        $_SESSION['login_id'] = 0;
        if ($work->check_post('login', true, true, REG_LOGIN) && $work->check_post('password', true, true)) {
            if ($db->select(array('id', 'login', 'password'), TBL_USERS, '`login` = \'' . $_POST['login'] . '\'')) {
                $temp_user = $db->res_row();
                if ($temp_user) {
                    if (md5($_POST['password']) === $temp_user['password']) {
                        $_SESSION['login_id'] = $temp_user['id'];
                        $user = new user();
                        header('Location: ' . $redirect_url);
                        log_in_file('Hack attempt!');
                    } else {
                        header('Location: ' . $redirect_url);
                        log_in_file('Hack attempt!');
                    }
                } else {
                    header('Location: ' . $redirect_url);
                    log_in_file('Hack attempt!');
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        } else {
            header('Location: ' . $redirect_url);
            log_in_file('Hack attempt!');
        }
    }
} elseif ($subact == 'profile') {
    if (((isset($_SESSION['login_id']) && $_SESSION['login_id'] == 0) || !isset($_SESSION['login_id'])) && (!isset($_GET['uid']) || empty($_GET['uid']))) {
        header('Location: ' . $work->config['site_url']);
        log_in_file('Hack attempt!');
    } else {
        $action = 'profile';
        if ($work->check_get('uid', true, true, '^[0-9]+\$', true)) {
            $uid = $_GET['uid'];
        } else {
            $uid = $_SESSION['login_id'];
        }

        if ($uid === $_SESSION['login_id'] || $user->user['admin'] == true) {
            if ($db->select('*', TBL_USERS, '`id` = ' . $uid)) {
                $temp = $db->res_row();
                if ($temp) {
                    $name_block = $lang['profile']['edit_profile'] . ' ' . $temp['real_name'];
                    $max_size_php = $work->return_bytes(ini_get('post_max_size'));
                    $max_size = $work->return_bytes($work->config['max_file_size']);
                    if ($max_size > $max_size_php) {
                        $max_size = $max_size_php;
                    }

                    if ($uid === $_SESSION['login_id']) {
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
                        'L_LOGIN'         => $lang['profile']['login'],
                        'L_EDIT_PASSWORD' => $lang['profile']['password'],
                        'L_RE_PASSWORD'   => $lang['profile']['re_password'],
                        'L_EMAIL'         => $lang['profile']['email'],
                        'L_REAL_NAME'     => $lang['profile']['real_name'],
                        'L_PASSWORD'      => $lang['profile']['confirm_password'],
                        'L_SAVE_PROFILE'  => $lang['profile']['save_profile'],
                        'L_HELP_EDIT'     => $lang['profile']['help_edit'],
                        'L_AVATAR'        => $lang['profile']['avatar'],
                        'L_GROUP'         => $lang['main']['group'],
                        'L_DELETE_AVATAR' => $lang['profile']['delete_avatar'],

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
                    log_in_file('Hack attempt!');
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        } else {
            if ($db->select('*', TBL_USERS, '`id` = ' . $uid)) {
                $temp = $db->res_row();
                if ($temp) {
                    $name_block = $lang['profile']['profile'] . ' ' . $temp['real_name'];
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
                        'L_EMAIL'     => $lang['profile']['email'],
                        'L_REAL_NAME' => $lang['profile']['real_name'],
                        'L_AVATAR'    => $lang['profile']['avatar'],
                        'L_GROUP'     => $lang['main']['group'],

                        'D_EMAIL'     => $work->filt_email($temp['email']),
                        'D_REAL_NAME' => $temp['real_name'],
                        'D_GROUP'     => $temp2['name'],

                        'U_AVATAR'    => $work->config['site_url'] . $work->config['avatar_folder'] . '/' . $temp['avatar']
                    ));
                    $array_data = array();
                    $array_data = array();
                } else {
                    header('Location: ' . $work->config['site_url']);
                    log_in_file('Hack attempt!');
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        }
    }
} else {
    header('Location: ' . $work->config['site_url']);
    log_in_file('Hack attempt!');
}
/// @endcond
