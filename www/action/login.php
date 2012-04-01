<?php
/**
* @file		action/login.php
* @brief	Работа с пользователями.
* @author	Dark Dayver
* @version	0.2.0
* @date		28/03-2012
* @details	Обработка процедур входа/выхода/регистрации/изменения и просмотра профиля пользователя.
*/

if (IN_GALLERY)
{
	die('HACK!');
}

include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/main.php');
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/menu.php');
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/login.php');

$menu_act = '';
if (!isset($_REQUEST['subact']) || empty($_REQUEST['subact']))
{
	if ($_SESSION['login_id'] == 0) $subact = 'login';
	else $subact = 'logout';
}
else $subact = $_REQUEST['subact'];

if (!empty($_SERVER['HTTP_REFERER'])) $redirect_url = $_SERVER['HTTP_REFERER'];
else $redirect_url = $work->config['site_url'];

if($subact == 'saveprofile') // если поступила команда сохранения измененного профиля, то...
{
	$subact = 'profile'; // активный пункт меню - profile

	if ($_SESSION['login_id'] == 0) // если пользователь НЕ зарегистрирован на сайте, то...
	{
		$redirect_time = 3; // устанавливаем время редиректа равным 3 секунды
		$redirect_message = $lang['main_redirect_title']; // указываем сообщение о переадресации
	}
	else // иначе если пользователь зарегистрирован, то...
	{
		if (!isset($_REQUEST['uid']) || empty($_REQUEST['uid'])) $uid = $_SESSION['login_id'];
		else $uid = $_REQUEST['uid'];

		if($uid == $_SESSION['login_id'] || $user->user['admin'] == true)
		{
			if ($db2->select('*', TBL_USERS, '`id` = ' . $uid))
			{
				$temp = $db2->res_row();
				if ($temp)
				{
					$max_size_php = $work->return_bytes(ini_get('post_max_size'));
					$max_size = $work->return_bytes($work->config['max_file_size']);
					if ($max_size > $max_size_php) $max_size = $max_size_php;

					if ($uid != $_SESSION['login_id'] || (isset($_REQUEST['password']) && !empty($_REQUEST['password']) && md5($_REQUEST['password']) == $temp['password']))
					{
						if (!isset($_REQUEST['edit_password']) || empty($_REQUEST['edit_password'])) $new_pass = $temp['password'];
						else
						{
							if ($_REQUEST['re_password'] != $_REQUEST['edit_password']) $new_pass = $temp['password'];
							else $new_pass = md5($_REQUEST['re_password']);
						}

						if ($db2->select('COUNT(*) as `email_count`', TBL_USERS, '`id` != ' . $uid . ' AND `email` = \'' . $_REQUEST['email'] . '\''))
						{
							$email_count = $db2->res_row();
							if (isset($email_count['email_count']) && $email_count['email_count'] > 0) $email_count = true;
							else $email_count = false;
						}
						else log_in_file($db2->error, DIE_IF_ERROR);
						if (!isset($_REQUEST['email']) || empty($_REQUEST['email']) || !mb_eregi(REG_EMAIL, $_REQUEST['email']) || $email_count) $new_email = $temp['email'];
						else $new_email = $_REQUEST['email'];

						if ($db2->select('COUNT(*) as `real_count`', TBL_USERS, '`id` != ' . $uid . ' AND `real_name` = \'' . $_REQUEST['real_name'] . '\''))
						{
							$real_count = $db2->res_row();
							if (isset($real_count['real_count']) && $real_count['real_count'] > 0) $real_count = true;
							else $real_count = false;
						}
						else log_in_file($db2->error, DIE_IF_ERROR);
						if (!isset($_REQUEST['real_name']) || empty($_REQUEST['real_name']) || !mb_eregi(REG_NAME, $_REQUEST['real_name']) || $real_count) $new_real_name = $temp['real_name'];
						else $new_real_name = $_REQUEST['real_name'];

						if (!isset($_REQUEST['delete_avatar']) || empty($_REQUEST['delete_avatar']) || $_REQUEST['delete_avatar'] != 'true')
						{
							if (isset($_FILES['file_avatar']) && $_FILES['file_avatar']['error'] == 0 && $_FILES['file_avatar']['size'] > 0 && $_FILES['file_avatar']['size'] <= $max_size && mb_eregi('(gif|jpeg|png)$', $_FILES['file_avatar']['type']))
							{
								$avatar_size = getimagesize($_FILES['file_avatar']['tmp_name']);
								$file_avatar = time() . '_' . $work->encodename(basename($_FILES['file_avatar']['name']));

								if($avatar_size[0] <= $work->config['max_avatar_w'] && $avatar_size[1] <= $work->config['max_avatar_h'])
								{
									$path_avatar = $work->config['site_dir'] . $work->config['avatar_folder'] . '/' . $file_avatar;
									if (move_uploaded_file($_FILES['file_avatar']['tmp_name'], $path_avatar))
									{
										$new_avatar = $file_avatar;
										if($temp['avatar'] != 'no_avatar.jpg') @unlink($work->config['site_dir'] . $work->config['avatar_folder'] . '/' . $temp['avatar']);
									}
									else $new_avatar = $temp['avatar'];
								}
								else $new_avatar = $temp['avatar'];
							}
							else $new_avatar = $temp['avatar'];
						}
						else
						{
							if($temp['avatar'] != 'no_avatar.jpg') @unlink($work->config['site_dir'] . $work->config['avatar_folder'] . '/' . $temp['avatar']);
							$new_avatar = 'no_avatar.jpg';
						}
						if ($db2->update(array('password' => $new_pass, 'real_name' => $new_real_name, 'email' => $new_email, 'avatar' => $new_avatar), TBL_USERS, '`id` = ' . $uid)) $user = new user();
						else log_in_file($db2->error, DIE_IF_ERROR);
					}
				}
				else log_in_file('Unable to get the user', DIE_IF_ERROR);
			}
			else log_in_file($db2->error, DIE_IF_ERROR);
		}
	}
}

if ($subact == 'logout')
{
	if ($_SESSION['login_id'] == 0)
	{
		$redirect_time = 3;
		$redirect_message = $lang['main_redirect_title'];
	}
	else
	{
		$redirect_time = 5;
		$redirect_message = $user->user['real_name'] . $lang['main_logout_ok'];
		if (!$db2->update(array('date_last_activ' => NULL, 'date_last_logout' => date('Y-m-d H:m:s')), TBL_USERS, '`id` = ' . $_SESSION['login_id'])) log_in_file($db2->error, DIE_IF_ERROR);
		$_SESSION['login_id'] = 0;
		$_SESSION['admin_on'] = false;
		$user = new user();
		@session_destroy();
	}
}
/* elseif ($subact == 'forgot') // Восстановление пароля будет готово после реализации работы с почтой
{
	$redirect_time = 3;
	$redirect_message = $lang['main_redirect_title'];
} */
elseif ($subact == 'regist')
{
	if ($_SESSION['login_id'] != 0)
	{
		$redirect_time = 3;
		$redirect_message = $lang['main_redirect_title'];
		$subact = 'logout';
	}
	else
	{
		$array_data = array();
		$array_data = array(
					'NAME_BLOCK' => $lang['login_regist'],
					'L_LOGIN' => $lang['login_login'],
					'L_PASSWORD' => $lang['login_password'],
					'L_RE_PASSWORD' => $lang['login_re_password'],
					'L_EMAIL' => $lang['login_email'],
					'L_REAL_NAME' => $lang['login_real_name'],
					'L_REGISTER' => $lang['login_register'],

					'U_REGISTER' => $work->config['site_url'] . '?action=login&subact=register'
		);

		$name_block = $lang['login_regist'];
		$menu_act = 'regist';

		$main_block = $template->create_template('register.tpl', $array_data);
	}
}
elseif ($subact == 'register')
{
	if ($_SESSION['login_id'] != 0)
	{
		$redirect_time = 3;
		$redirect_message = $lang['main_redirect_title'];
	}
	else
	{
		$error = false;
		$text_error = '';

		if (!isset($_REQUEST['login']) || empty($_REQUEST['login']) || !mb_ereg(REG_LOGIN, $_REQUEST['login']))
		{
			$error = true;
			$text_error .= '-&nbsp;' . $lang['login_error_login'] . '<br />';
		}
		else $register['login'] = $_REQUEST['login'];

		if (!isset($_REQUEST['password']) || empty($_REQUEST['password']))
		{
			$error = true;
			$text_error .= '-&nbsp;' . $lang['login_error_password'] . '<br />';
		}
		else $register['password'] = $_REQUEST['password'];

		if ($_REQUEST['re_password'] != $register['password'])
		{
			$error = true;
			$text_error .= '-&nbsp;' . $lang['login_error_re_password'] . '<br />';
		}
		else $register['re_password'] = md5($register['password']);

		if (!isset($_REQUEST['email']) || empty($_REQUEST['email']) || !mb_eregi(REG_EMAIL, $_REQUEST['email']))
		{
			$error = true;
			$text_error .= '-&nbsp;' . $lang['login_error_email'] . '<br />';
		}
		else $register['email'] = $_REQUEST['email'];

		if (!isset($_REQUEST['real_name']) || empty($_REQUEST['real_name']) || !mb_eregi(REG_NAME, $_REQUEST['real_name']))
		{
			$error = true;
			$text_error .= '-&nbsp;' . $lang['login_error_real_name'] . '<br />';
		}
		else $register['real_name'] = $_REQUEST['real_name'];

		if ($db2->select('COUNT(*) as `login_count`', TBL_USERS, '`login` = \'' . $register['login'] . '\''))
		{
			$temp = $db2->res_row();
			if (isset($temp['login_count']) && $temp['login_count'] > 0)
			{
				$error = true;
				$text_error .= '-&nbsp;' . $lang['login_error_login_exists'] . '<br />';
			}
		}
		else log_in_file($db2->error, DIE_IF_ERROR);

		if ($db2->select('COUNT(*) as `email_count`', TBL_USERS, '`email` = \'' . $register['email'] . '\''))
		{
			$temp = $db2->res_row();
			if (isset($temp['email_count']) && $temp['email_count'] > 0)
			{
				$error = true;
				$text_error .= '-&nbsp;' . $lang['login_error_email_exists'] . '<br />';
			}
		}
		else log_in_file($db2->error, DIE_IF_ERROR);

		if ($db2->select('COUNT(*) as `real_count`', TBL_USERS, '`real_name` = \'' . $register['real_name'] . '\''))
		{
			$temp = $db2->res_row();
			if (isset($temp['real_count']) && $temp['real_count'] > 0)
			{
				$error = true;
				$text_error .= '-&nbsp;' . $lang['login_error_real_name_exists'] . '<br />';
			}
		}
		else log_in_file($db2->error, DIE_IF_ERROR);

		if ($error)
		{
			$redirect_time = 10;
			$redirect_message = $lang['login_error'] . '<br /><br />' . $text_error;
		}
		else
		{
			$query = array();
			$query['login'] = $register['login'];
			$query['password'] = $register['re_password'];
			$query['real_name'] = $register['real_name'];
			$query['email'] = $register['email'];
			$query['group'] = DEFAULT_GROUP;

			if ($db2->select('*', TBL_GROUP, '`id` = ' . DEFAULT_GROUP))
			{
				$temp = $db2->res_row();
				if ($temp)
				{
					foreach ($temp as $key => $value)
					{
						if ($key != 'id' && $key != 'name') $query[$key] = $value;
					}
					if ($db2->insert($query, TBL_USERS))
					{
						$new_user = $db2->insert_id;
						if($new_user != 0)
						{
							$_SESSION['login_id'] = $new_user;
							$redirect_time = 5;
							$redirect_message = $lang['login_user'] . ' ' . $register['real_name'] . ' ' . $lang['login_registered'];
							$redirect_url = $work->config['site_url'] . '?action=login&subact=profile';
						}
						else
						{
							$redirect_time = 10;
							$redirect_message = $lang['login_error'];
						}
					}
					else log_in_file($db2->error, DIE_IF_ERROR);
				}
				else log_in_file('Unable to get the default group', DIE_IF_ERROR);
			}
			else log_in_file($db2->error, DIE_IF_ERROR);
		}
	}
}
elseif ($subact == 'login')
{
	if(isset($_SESSION['login_id']) && $_SESSION['login_id'] != 0)
	{
		$redirect_time = 3;
		$redirect_message = $lang['main_redirect_title'];
	}
	else
	{
		if(isset($_POST['login']) && !empty($_POST['login']) && isset($_POST['password']) && !empty($_POST['password']))
		{
			$redirect_time = 5;
			if ($db2->select(array('id', 'login', 'password', 'real_name'), TBL_USERS, '`login` = \'' . $_POST['login'] . '\''))
			{
				$temp_user = $db2->res_row();
				if ($temp_user)
				{
					if(md5($_POST['password']) == $temp_user['password'])
					{
						$_SESSION['login_id'] = $temp_user['id'];
						$redirect_message = $temp_user['real_name'] . $lang['main_login_ok'];
						$user = new user();
					}
					else
					{
						$_SESSION['login_id'] = 0;
						$redirect_message = $lang['main_login_error'];
					}
				}
				else
				{
					$_SESSION['login_id'] = 0;
					$redirect_message = $lang['main_login_error'];
				}
			}
			else log_in_file($db2->error, DIE_IF_ERROR);
		}
		else
		{
			$redirect_time = 3;
			$redirect_message = $lang['main_redirect_title'];
		}
	}
}
elseif($subact == 'profile')
{
	if (((isset($_SESSION['login_id']) && $_SESSION['login_id'] == 0) || !isset($_SESSION['login_id'])) && (!isset($_REQUEST['uid']) || empty($_REQUEST['uid'])))
	{
		$redirect_time = 3;
		$redirect_message = $lang['main_redirect_title'];
		$subact = 'logout';
	}
	else
	{
		$menu_act = 'profile';
		if (!isset($_REQUEST['uid']) || empty($_REQUEST['uid'])) $uid = $_SESSION['login_id'];
		else $uid = $_REQUEST['uid'];

		if($uid == $_SESSION['login_id'] || $user->user['admin'] == true)
		{
			if ($db2->select('*', TBL_USERS, '`id` = ' . $uid))
			{
				$temp = $db2->res_row();
				if ($temp)
				{
					$name_block = $lang['login_edit_profile'] . ' ' . $temp['real_name'];
					$max_size_php = $work->return_bytes(ini_get('post_max_size'));
					$max_size = $work->return_bytes($work->config['max_file_size']);
					if ($max_size > $max_size_php) $max_size = $max_size_php;

					if ($uid == $_SESSION['login_id']) $confirm_password = true;
					else $confirm_password = false;

					if ($db2->select('*', TBL_GROUP, '`id` = ' . $temp['group']))
					{
						$temp2 = $db2->res_row();
						if (!$temp2)
						{
							$temp['group'] = 0;
							if ($db2->select('*', TBL_GROUP, '`id` = ' . $temp['group']))
							{
								$temp2 = $db2->res_row();
								if (!$temp2) log_in_file('Unable to get the guest group', DIE_IF_ERROR);
							}
							else log_in_file($db2->error, DIE_IF_ERROR);
						}
					}
					else log_in_file($db2->error, DIE_IF_ERROR);
					$array_data = array();
					$array_data = array(
									'NAME_BLOCK' => $name_block,
									'L_LOGIN' => $lang['login_login'],
									'L_EDIT_PASSWORD' => $lang['login_password'],
									'L_RE_PASSWORD' => $lang['login_re_password'],
									'L_EMAIL' => $lang['login_email'],
									'L_REAL_NAME' => $lang['login_real_name'],
									'L_PASSWORD' => $lang['login_confirm_password'],
									'L_SAVE_PROFILE' => $lang['login_save_profile'],
									'L_HELP_EDIT' => $lang['login_help_edit'],
									'L_AVATAR' => $lang['login_avatar'],
									'L_GROUP' => $lang['main_group'],
									'L_DELETE_AVATAR' => $lang['login_delete_avatar'],

									'D_LOGIN' => $temp['login'],
									'D_EMAIL' => $temp['email'],
									'D_REAL_NAME' => $temp['real_name'],
									'D_MAX_FILE_SIZE' => $max_size,
									'D_GROUP' => $temp2['name'],

									'IF_NEED_PASSWORD' => $confirm_password,

									'U_AVATAR' => $work->config['site_url'] . $work->config['avatar_folder'] . '/' . $temp['avatar'],
									'U_PROFILE_EDIT' => $work->config['site_url'] . '?action=login&subact=saveprofile&uid=' . $uid
					);
					$main_block = $template->create_template('profile_edit.tpl', $array_data);
				}
				else
				{
					$redirect_time = 3;
					$redirect_message = $lang['main_redirect_title'];
					$subact = 'logout';
				}
			}
			else log_in_file($db2->error, DIE_IF_ERROR);
		}
		else
		{
			if ($db2->select('*', TBL_USERS, '`id` = ' . $uid))
			{
				$temp = $db2->res_row();
				if ($temp)
				{
					$name_block = $lang['login_profile'] . ' ' . $temp['real_name'];
					$menu_act = '';

					if ($db2->select('*', TBL_GROUP, '`id` = ' . $temp['group']))
					{
						$temp2 = $db2->res_row();
						if (!$temp2)
						{
							$temp['group'] = 0;
							if ($db2->select('*', TBL_GROUP, '`id` = ' . $temp['group']))
							{
								$temp2 = $db2->res_row();
								if (!$temp2) log_in_file('Unable to get the guest group', DIE_IF_ERROR);
							}
							else log_in_file($db2->error, DIE_IF_ERROR);
						}
					}
					else log_in_file($db2->error, DIE_IF_ERROR);
					$array_data = array();
					$array_data = array(
									'NAME_BLOCK' => $name_block,
									'L_EMAIL' => $lang['login_email'],
									'L_REAL_NAME' => $lang['login_real_name'],
									'L_AVATAR' => $lang['login_avatar'],
									'L_GROUP' => $lang['main_group'],

									'D_EMAIL' => $work->filt_email($temp['email']),
									'D_REAL_NAME' => $temp['real_name'],
									'D_GROUP' => $temp2['name'],

									'U_AVATAR' => $work->config['site_url'] . $work->config['avatar_folder'] . '/' . $temp['avatar']
					);
					$main_block = $template->create_template('profile_view.tpl', $array_data);
				}
				else
				{
					$redirect_time = 3;
					$redirect_message = $lang['main_redirect_title'];
					$subact = 'logout';
				}
			}
			else log_in_file($db2->error, DIE_IF_ERROR);
		}
	}
}
else
{
	$redirect_time = 3;
	$redirect_message = $lang['main_redirect_title'];
}

$redirect = array();

if ($subact != 'regist' && $subact != 'profile')
{
	$array_data = array();

	$array_data = array(
				'L_REDIRECT_DESCRIPTION' => $lang['main_redirect_description'],
				'L_REDIRECT_URL' => $lang['main_redirect_url'],

				'L_REDIRECT_MASSAGE' => $redirect_message,
				'U_REDIRECT_URL' => $redirect_url
	);

	$redirect = array(
				'U_REDIRECT_URL' => $redirect_url,
				'REDIRECT_TIME' => $redirect_time,
				'IF_NEED_REDIRECT' => true
	);
	$name_block = $lang['main_redirect_title'];
	$main_block = $template->create_template('redirect.tpl', $array_data);
}
echo $template->create_main_template($menu_act, $name_block, $main_block, $redirect);
?>
