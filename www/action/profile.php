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

        // Проверяем CSRF-токен
        if (!isset($_POST['csrf_token']) || !hash_equals($user->session['csrf_token'], $_POST['csrf_token'])) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
            );
        }
        $user->unset_property_key('session', 'csrf_token');
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
                        $user = new User($db, $_SESSION);
                        $work->set_user($user);
                        $work->set_lang();
                        $template->set_lang($work->lang);
                        $template->set_work($work);
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
        // Обновление данных пользователя через плейсхолдеры
        $db->update([
            'date_last_activ' => null,
            'date_last_logout' => date('Y-m-d H:i:s')
        ], TBL_USERS, [
            'where' => '`id` = :user_id',
            'params' => [':user_id' => $user->session['login_id']]
        ]);

        // Проверка успешности обновления
        $rows = $db->get_affected_rows();
        if ($rows === 0) {
            throw new \RuntimeException(
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
        \PhotoRigma\Include\log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: logout"
        );
    }
} /* elseif ($subact == 'forgot') // Восстановление пароля будет готово после реализации работы с почтой
{
    $redirect_time = 3;
    $redirect_message = $work->lang['main']['redirect_title'];
} */ elseif ($subact == 'regist') {
    // Проверяем, авторизован ли пользователь через check_input
    if ($work->check_input('_SESSION', 'login_id', [
        'isset' => true,
        'empty' => true,
        'regexp' => '/^[0-9]+$/',
        'not_zero' => true
    ])) {
        // Если пользователь уже авторизован, перенаправляем на главную страницу
        header('Location: ' . $work->config['site_url']);
        \PhotoRigma\Include\log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: regist, Пользователь ID: {$user->session['login_id']}"
        );
    } else {
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
        $template->add_if_ar(array(
            'ERROR_LOGIN'       => false,
            'ERROR_PASSWORD'    => false,
            'ERROR_RE_PASSWORD' => false,
            'ERROR_EMAIL'       => false,
            'ERROR_REAL_NAME'   => false,
            'ERROR_CAPTCHA'     => false
        ));

        // Добавляем строки для шаблона, включая метки, URL и данные CAPTCHA
        $template->add_string_ar(array(
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
            'D_REAL_NAME'        => ''
        ));

        // Если есть ошибки в сессии, добавляем их в шаблон
        if (isset($user->session['error']) && !empty($user->session['error']) && is_array($user->session['error'])) {
            foreach ($user->session['error'] as $key => $value) {
                // Добавляем флаг ошибки для поля
                $template->add_if('ERROR_' . strtoupper($key), $value['if'] ?? false);

                // Добавляем данные ошибки в шаблон
                $template->add_string_ar(array(
                    'D_' . strtoupper($key)       => Work::clean_field($value['data']) ?? '',
                    'D_ERROR_' . strtoupper($key) => Work::clean_field($value['text']) ?? ''
                ));
            }
            // Очищаем ошибки из сессии после использования
            $user->unset_property_key('session', 'error');
        }
    }
} elseif ($subact == 'register') {
    // Проверяем, авторизован ли пользователь через check_input
    // Если пользователь уже авторизован, перенаправляем его на другую страницу
    if ($work->check_input('_SESSION', 'login_id', [
        'isset' => true,
        'empty' => true,
        'regexp' => '/^[0-9]+$/',
        'not_zero' => true
    ])) {
        header('Location: ' . $redirect_url);
        \PhotoRigma\Include\log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: register, Пользователь ID: {$user->session['login_id']}"
        );
    } else {
        // Проверяем CSRF-токен для защиты от CSRF-атак
        if (!isset($_POST['csrf_token']) || !hash_equals($user->session['csrf_token'], $_POST['csrf_token'])) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
            );
        }
        $user->unset_property_key('session', 'csrf_token');

        // Будущий метод add_user_data
        // Входные данные:
        // - $_POST (данные из формы: логин, пароль, email, имя, CAPTCHA)
        // - $work (класс вспомогательных методов)
        // - $redirect_url (URL для перенаправления в случае ошибок)

        $error = false;
        // Массив правил валидации для полей формы
        // Ключи - имена полей, значения - параметры проверки
        $field_validators = [
            'login' => ['isset' => true, 'empty' => true, 'regexp' => REG_LOGIN],
            'password' => ['isset' => true, 'empty' => true],
            're_password' => ['isset' => true, 'empty' => true],
            'email' => ['isset' => true, 'empty' => true, 'regexp' => REG_EMAIL],
            'real_name' => ['isset' => true, 'empty' => true, 'regexp' => REG_NAME],
            'captcha' => ['isset' => true, 'empty' => true, 'regexp' => '/^[0-9]+$/']
        ];
        // Применяем правила валидации для каждого поля формы
        foreach ($field_validators as $field => $options) {
            if (!$work->check_input('_POST', $field, $options)) {
                $user->session['error'][$field]['if'] = true;
                $user->session['error'][$field]['text'] = match ($field) {
                    'login' => $work->lang['profile']['error_login'],
                    'password' => $work->lang['profile']['error_password'],
                    're_password' => $work->lang['profile']['error_re_password'],
                    'email' => $work->lang['profile']['error_email'],
                    'real_name' => $work->lang['profile']['error_real_name'],
                    'captcha' => $work->lang['profile']['error_captcha'],
                    default => 'Unknown error',
                };
                $error = true;
            } else {
                // Дополнительные проверки для re_password и captcha
                if ($field === 're_password' && $_POST['re_password'] !== $_POST['password']) {
                    $user->session['error']['re_password']['if'] = true;
                    $user->session['error']['re_password']['text'] = $work->lang['profile']['error_re_password'];
                    $error = true;
                }
                if ($field === 'captcha' && !password_verify($_POST['captcha'], $user->session['captcha'])) {
                    $user->session['error']['captcha']['if'] = true;
                    $user->session['error']['captcha']['text'] = $work->lang['profile']['error_captcha'];
                    $error = true;
                }
            }
        }
        $user->unset_property_key('session', 'captcha');
        // Проверка уникальности login, email и real_name в одном запросе
        // Используется агрегация данных через COUNT(CASE ...) для каждого поля
        $db->select(
            'COUNT(CASE WHEN `login` = :login THEN 1 END) as login_count,
             COUNT(CASE WHEN `email` = :email THEN 1 END) as email_count,
             COUNT(CASE WHEN `real_name` = :real_name THEN 1 END) as real_count',
            TBL_USERS,
            [
                'params' => [
                    ':login' => $_POST['login'],
                    ':email' => $_POST['email'],
                    ':real_name' => $_POST['real_name']
                ]
            ]
        );
        $unique_check_result = $db->res_row();
        if ($unique_check_result) {
            // Если login уже существует, добавляем ошибку
            if ($unique_check_result['login_count'] > 0) {
                $error = true;
                $user->session['error']['login']['if'] = true;
                $user->session['error']['login']['text'] = $work->lang['profile']['error_login_exists'];
            }
            // Если email уже существует, добавляем ошибку
            if ($unique_check_result['email_count'] > 0) {
                $error = true;
                $user->session['error']['email']['if'] = true;
                $user->session['error']['email']['text'] = $work->lang['profile']['error_email_exists'];
            }
            // Если real_name уже существует, добавляем ошибку
            if ($unique_check_result['real_count'] > 0) {
                $error = true;
                $user->session['error']['real_name']['if'] = true;
                $user->session['error']['real_name']['text'] = $work->lang['profile']['error_real_name_exists'];
            }
        }
        // Если возникли ошибки, сохраняем их в сессии и перенаправляем пользователя
        if ($error) {
            header('Location: ' . $redirect_url);
            \PhotoRigma\Include\log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка регистрации"
            );
        } else {
            $user->unset_property_key('session', 'error');
            $query = $register;
            $query['group'] = DEFAULT_GROUP;
            // Получение данных группы по умолчанию
            // Группа необходима для назначения прав доступа новому пользователю
            $db->select(
                '*',
                TBL_GROUP,
                [
                    'where' => '`id` = :group_id',
                    'params' => [':group_id' => DEFAULT_GROUP]
                ]
            );
            $group_data = $db->res_row();
            if ($group_data) {
                // Добавляем данные группы в массив для вставки нового пользователя
                foreach ($group_data as $key => $value) {
                    if ($key != 'id' && $key != 'name') {
                        $query[$key] = $value;
                    }
                }
                // Формируем массив плейсхолдеров для подготовленного выражения
                $placeholders = array_map(fn ($key) => ["`$key`" => ":$key"], array_keys($query));
                // Вставка нового пользователя в базу данных
                $db->insert(
                    $placeholders,
                    TBL_USERS,
                    '',
                    ['params' => $query]
                );
                $new_user = $db->get_last_insert_id();
            } else {
                // Группа по умолчанию не найдена - выбрасываем исключение
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные группы по умолчанию"
                );
            }
        }

        // Результаты на выходе:
        // - $new_user (результат добавления пользователя в БД)
        // Конец метода add_user_data
        if ($new_user != 0) {
            $user->session['login_id'] = $new_user;
            header('Location: ' . sprintf('%s?action=profile&subact=profile', $work->config['site_url']));
            \PhotoRigma\Include\log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Успешная регистрация | Пользователь ID: {$user->session['login_id']}"
            );
        } else {
            header('Location: ' . $redirect_url);
            \PhotoRigma\Include\log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка регистрации"
            );
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
