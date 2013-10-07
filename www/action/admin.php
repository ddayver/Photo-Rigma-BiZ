<?php
/**
* @file		action/admin.php
* @brief	Администрирование сайта.
* @author	Dark Dayver
* @version	0.2.0
* @date		28/03-2012
* @details	Используется для управления настройками и пользователями галереи.
*/

if (IN_GALLERY !== true)
{
	die('HACK!');
}

include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/main.php'); // подключаем языковый файл основной страницы
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/menu.php'); // подключаем языковый файл меню
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/admin.php'); // подключаем языковый файл Админки

if ((!isset($_SESSION['admin_on']) || $_SESSION['admin_on'] !== true) && $user->user['admin'] == true && isset($_POST['admin_password']) && !empty($_POST['admin_password']) && $user->user['password'] == md5($_POST['admin_password'])) // если не открыта сессия для Админа, пользователь имеет право на вход в Админку, был передан пароль для входа и пароль совпадает с паролем пользователя, то...
{
	$_SESSION['admin_on'] = true; // открываем сессию для Админа
}

if (isset($_SESSION['admin_on']) && $_SESSION['admin_on'] === true && $user->user['admin'] == true) // если открыта сессия для Админа и пользователь имеет права Админа, то...
{
	if (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'settings') // если была команда на общие настройки, то...
	{
		if (isset($_POST['submit_x']) && !empty($_POST['submit_x']) && isset($_POST['submit_y']) && !empty($_POST['submit_y'])) // если поступил запрос на сохранение общих настроек, то...
		{
			$new_config = $work->config; // формируем массив настроек, хранящихся в базе на текущий момент

			// проверим введенные пользователем данные на соотвествие формата, если формат соответствует, то используем введенное пользователем, если нет - остается старая настройка
			if (isset($_POST['title_name']) && !empty($_POST['title_name'])) $new_config['title_name'] = $_POST['title_name'];
			if (isset($_POST['title_description']) && !empty($_POST['title_description'])) $new_config['title_description'] = $_POST['title_description'];
			if (isset($_POST['meta_description']) && !empty($_POST['meta_description'])) $new_config['meta_description'] = $_POST['meta_description'];
			if (isset($_POST['meta_keywords']) && !empty($_POST['meta_keywords'])) $new_config['meta_keywords'] = $_POST['meta_keywords'];
			if (isset($_POST['gal_width']) && !empty($_POST['gal_width']) && mb_ereg('^([0-9]+)(%){0,1}$', $_POST['gal_width'])) $new_config['gal_width'] = $_POST['gal_width'];
			if (isset($_POST['left_panel']) && !empty($_POST['left_panel']) && mb_ereg('^([0-9]+)(%){0,1}$', $_POST['left_panel'])) $new_config['left_panel'] = $_POST['left_panel'];
			if (isset($_POST['right_panel']) && !empty($_POST['right_panel']) && mb_ereg('^([0-9]+)(%){0,1}$', $_POST['right_panel'])) $new_config['right_panel'] = $_POST['right_panel'];
			if (isset($_POST['language']) && !empty($_POST['language']) && is_dir($work->config['site_dir'] . 'language/' . $_POST['language'])) $new_config['language'] = $_POST['language'];
			if (isset($_POST['themes']) && !empty($_POST['themes']) && is_dir($work->config['site_dir'] . 'themes/' . $_POST['themes'])) $new_config['themes'] = $_POST['themes'];
			if (isset($_POST['max_file_size']) && !empty($_POST['max_file_size']) && mb_ereg('^[0-9]+$', $_POST['max_file_size']) && isset($_POST['max_file_size_letter']) && mb_ereg('^(B|K|M|G)$', $_POST['max_file_size_letter']))
			{
				if ($_POST['max_file_size_letter'] == 'B')
				{
					$new_config['max_file_size'] = $_POST['max_file_size'];
				}
				else
				{
					$new_config['max_file_size'] = $_POST['max_file_size'] . $_POST['max_file_size_letter'];
				}
			}
			if (isset($_POST['max_photo_w']) && !empty($_POST['max_photo_w']) && mb_ereg('^[0-9]+$', $_POST['max_photo_w'])) $new_config['max_photo_w'] = $_POST['max_photo_w'];
			if (isset($_POST['max_photo_h']) && !empty($_POST['max_photo_h']) && mb_ereg('^[0-9]+$', $_POST['max_photo_h'])) $new_config['max_photo_h'] = $_POST['max_photo_h'];
			if (isset($_POST['temp_photo_w']) && !empty($_POST['temp_photo_w']) && mb_ereg('^[0-9]+$', $_POST['temp_photo_w'])) $new_config['temp_photo_w'] = $_POST['temp_photo_w'];
			if (isset($_POST['temp_photo_h']) && !empty($_POST['temp_photo_h']) && mb_ereg('^[0-9]+$', $_POST['temp_photo_h'])) $new_config['temp_photo_h'] = $_POST['temp_photo_h'];
			if (isset($_POST['max_avatar_w']) && !empty($_POST['max_avatar_w']) && mb_ereg('^[0-9]+$', $_POST['max_avatar_w'])) $new_config['max_avatar_w'] = $_POST['max_avatar_w'];
			if (isset($_POST['max_avatar_h']) && !empty($_POST['max_avatar_h']) && mb_ereg('^[0-9]+$', $_POST['max_avatar_h'])) $new_config['max_avatar_h'] = $_POST['max_avatar_h'];
			if (isset($_POST['copyright_year']) && !empty($_POST['copyright_year']) && mb_ereg('^[0-9\-]+$', $_POST['copyright_year'])) $new_config['copyright_year'] = $_POST['copyright_year'];
			if (isset($_POST['copyright_text']) && !empty($_POST['copyright_text'])) $new_config['copyright_text'] = $_POST['copyright_text'];
			if (isset($_POST['copyright_url']) && !empty($_POST['copyright_url'])) $new_config['copyright_url'] = $_POST['copyright_url'];
			if (isset($_POST['last_news']) && !empty($_POST['last_news']) && mb_ereg('^[0-9]+$', $_POST['last_news'])) $new_config['last_news'] = $_POST['last_news'];
			if (isset($_POST['best_user']) && !empty($_POST['best_user']) && mb_ereg('^[0-9]+$', $_POST['best_user'])) $new_config['best_user'] = $_POST['best_user'];
			if (isset($_POST['max_rate']) && !empty($_POST['max_rate']) && mb_ereg('^[0-9]+$', $_POST['max_rate'])) $new_config['max_rate'] = $_POST['max_rate'];

			foreach ($new_config as $name => $value)
			{
				if ($work->config[$name] !== $value)
				{
					if ($db->update(array('value' => $value), TBL_CONFIG, '`name` = \'' . $name . '\'')) $work->config[$name] = $value;
					else log_in_file($db->error, DIE_IF_ERROR);
				}
			}
		}

		$max_file_size = trim($work->config['max_file_size']); // удаляем пробельные символы в начале и конце строки
		$max_file_size_letter = strtolower($max_file_size[strlen($max_file_size)-1]); // получаем последний символ строки и переводим его в нижний регистр
		if ($max_file_size_letter == 'k' || $max_file_size_letter == 'm' || $max_file_size_letter == 'g') // если последний символ является указателем на кило-, мега- или гиго-байты
		{
			$max_file_size = substr($max_file_size, 0, strlen($max_file_size)-1); // получаем все, кроме последнего символа строки
		}
		else // иначе
		{
			$max_file_size_letter = 'b'; // пометим показатель в байтах
		}

		$language = '<select name="language">'; // открываем список доступных языков
		$language_dir = dir($work->config['site_dir'] . '/language'); // проверяем допустимые для выбора языки
		while (false !== ($entry = $language_dir->read())) // до тех пор, пока существуют файлы в папке
		{
			if ($entry != '.' && $entry !='..' && is_dir($language_dir->path . '/' . $entry))
			{
				if ($entry == $work->config['language']) $selected = ' selected'; else $selected = ''; // если очередной язык является текущим для сайта - помечаем его выбранным по-умолчанию
				$language .= '<option value="' . $entry . '"' . $selected . '>' . $entry . '</option>'; // наполняем список доступных языков
			}
		}
		$language_dir->close(); // закрываем работу с папкой языков
		$language .= '</select>'; // открываем список доступных языков

		$themes = '<select name="themes">'; // открываем список доступных тем
		$themes_dir = dir($work->config['site_dir'] . '/themes'); // проверяем допустимые для выбора темы
		while (false !== ($entry = $themes_dir->read())) // до тех пор, пока существуют файлы в папке
		{
			if ($entry != '.' && $entry !='..' && is_dir($themes_dir->path . '/' . $entry))
			{
				if ($entry == $work->config['themes']) $selected = ' selected'; else $selected = ''; // если очередная тема является текущей для сайта - помечаем его выбранным по-умолчанию
				$themes .= '<option value="' . $entry . '"' . $selected . '>' . $entry . '</option>'; // наполняем список доступных тем
			}
		}
		$themes_dir->close(); // закрываем работу с папкой тем
		$themes .= '</select>'; // открываем список доступных тем

		$array_data = array(); // инициируем массив

		$array_data = array(
					'NAME_BLOCK' => $lang['admin_title'] . ' - ' . $lang['admin_settings'],
					'L_MAIN_SETTINGS' => $lang['admin_main_settings'],
					'L_TITLE_NAME' => $lang['admin_title_name'],
					'L_TITLE_NAME_DESCRIPTION' => $lang['admin_title_name_description'],
					'L_TITLE_DESCRIPTION' => $lang['admin_title_description'],
					'L_TITLE_DESCRIPTION_DESCRIPTION' => $lang['admin_title_description_description'],
					'L_META_DESCRIPTION' => $lang['admin_meta_description'],
					'L_META_DESCRIPTION_DESCRIPTION' => $lang['admin_meta_description_description'],
					'L_META_KEYWORDS' => $lang['admin_meta_keywords'],
					'L_META_KEYWORDS_DESCRIPTION' => $lang['admin_meta_keywords_description'],
					'L_APPEARANCE_SETTINGS' => $lang['admin_appearance_settings'],
					'L_GAL_WIDTH' => $lang['admin_gal_width'],
					'L_GAL_WIDTH_DESCRIPTION' => $lang['admin_gal_width_description'],
					'L_LEFT_PANEL' => $lang['admin_left_panel'],
					'L_LEFT_PANEL_DESCRIPTION' => $lang['admin_left_panel_description'],
					'L_RIGHT_PANEL' => $lang['admin_right_panel'],
					'L_RIGHT_PANEL_DESCRIPTION' => $lang['admin_right_panel_description'],
					'L_LANGUAGE' => $lang['admin_language'],
					'L_LANGUAGE_DESCRIPTION' => $lang['admin_language_description'],
					'L_THEMES' => $lang['admin_themes'],
					'L_THEMES_DESCRIPTION' => $lang['admin_themes_description'],
					'L_SIZE_SETTINGS' => $lang['admin_size_settings'],
					'L_MAX_FILE_SIZE' => $lang['admin_max_file_size'],
					'L_MAX_FILE_SIZE_DESCRIPTION' => $lang['admin_max_file_size_description'],
					'L_MAX_PHOTO' => $lang['admin_max_photo'],
					'L_MAX_PHOTO_DESCRIPTION' => $lang['admin_max_photo_description'],
					'L_TEMP_PHOTO' => $lang['admin_temp_photo'],
					'L_TEMP_PHOTO_DESCRIPTION' => $lang['admin_temp_photo_description'],
					'L_MAX_AVATAR' => $lang['admin_max_avatar'],
					'L_MAX_AVATAR_DESCRIPTION' => $lang['admin_max_avatar_description'],
					'L_COPYRIGHT_SETTINGS' => $lang['admin_copyright_settings'],
					'L_COPYRIGHT_YEAR' => $lang['admin_copyright_year'],
					'L_COPYRIGHT_YEAR_DESCRIPTION' => $lang['admin_copyright_year_description'],
					'L_COPYRIGHT_TEXT' => $lang['admin_copyright_text'],
					'L_COPYRIGHT_TEXT_DESCRIPTION' => $lang['admin_copyright_text_description'],
					'L_COPYRIGHT_URL' => $lang['admin_copyright_url'],
					'L_COPYRIGHT_URL_DESCRIPTION' => $lang['admin_copyright_url_description'],
					'L_ADDITIONAL_SETTINGS' => $lang['admin_additional_settings'],
					'L_LAST_NEWS' => $lang['admin_last_news'],
					'L_LAST_NEWS_DESCRIPTION' => $lang['admin_last_news_description'],
					'L_BEST_USER' => $lang['admin_best_user'],
					'L_BEST_USER_DESCRIPTION' => $lang['admin_best_user_description'],
					'L_MAX_RATE' => $lang['admin_max_rate'],
					'L_MAX_RATE_DESCRIPTION' => $lang['admin_max_rate_description'],
					'L_SAVE_SETTINGS' => $lang['admin_save_settings'],

					'D_TITLE_NAME' => $work->config['title_name'],
					'D_TITLE_DESCRIPTION' => $work->config['title_description'],
					'D_META_DESCRIPTION' => $work->config['meta_description'],
					'D_META_KEYWORDS' => $work->config['meta_keywords'],
					'D_GAL_WIDTH' => $work->config['gal_width'],
					'D_LEFT_PANEL' => $work->config['left_panel'],
					'D_RIGHT_PANEL' => $work->config['right_panel'],
					'D_LANGUAGE' => $language,
					'D_THEMES' => $themes,
					'D_MAX_FILE_SIZE' => $max_file_size,
					'D_SEL_B' => $max_file_size_letter == 'b' ? ' selected' : '',
					'D_SEL_K' => $max_file_size_letter == 'k' ? ' selected' : '',
					'D_SEL_M' => $max_file_size_letter == 'm' ? ' selected' : '',
					'D_SEL_G' => $max_file_size_letter == 'g' ? ' selected' : '',
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
		); // наполняем массив данными для замены по шаблону

		$act = ''; // активного пункта меню - нет
		$title = $lang['admin_settings']; // дполнительный заговловок - Общие настройки
		$main_block = $template->create_template('admin_settings.tpl', $array_data); // формируем центральный блок - Общие настройки
	}
	elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'admin_user') // иначе если было запрошено Управление пользователями
	{
		$array_data = array(); // инициируем массив

		$array_data = array(
						'NAME_BLOCK' => $lang['admin_title'] . ' - ' . $lang['admin_admin_user'],

						'IF_NEED_FIND' => false,
						'IF_NEED_USER' => false,
						'IF_FIND_USER' => false
		); // наполняем массив данными для замены по шаблону - по умолчанию все блоки кода - отключены

		if (isset($_REQUEST['uid']) && !empty($_REQUEST['uid']) && mb_ereg('^[0-9]+$', $_REQUEST['uid'])) // если был передан идентификатор пользователя для редактирования, то...
		{
			if ($db->select('*', TBL_USERS, '`id` = ' . $_REQUEST['uid']))
			{
				$temp = $db->res_row();
				if ($temp)
				{
					if (isset($_POST['submit_x']) && !empty($_POST['submit_x']) && isset($_POST['submit_y']) && !empty($_POST['submit_y']))
					{
						if ($_POST['group'] != $temp['group'])
						{
							$query['group'] = $_POST['group'];
							$new_temp = $temp;
							if ($db->select('*', TBL_GROUP, '`id` = ' . $_POST['group']))
							{
								$temp_group = $db->res_row();
								if ($temp_group)
								{
									foreach ($temp_group as $key => $value)
									{
										if ($key != 'id' && $key != 'name')
										{
											$query[$key] = $value;
											$new_temp[$key] = $value;
										}
									}
									if ($db->update($query, TBL_USERS, '`id` = ' . $_REQUEST['uid'])) $temp = $new_temp;
									else log_in_file($db->error, DIE_IF_ERROR);
								}
								else log_in_file('Unable to get the group', DIE_IF_ERROR);
							}
							else log_in_file($db->error, DIE_IF_ERROR);
						}
						else
						{
							foreach ($temp as $key => $value)
							{
								if ($key != 'id' && $key != 'login' && $key != 'password' && $key != 'real_name' && $key != 'email' && $key != 'avatar' && $key != 'date_regist' && $key != 'date_last_activ' && $key != 'date_last_logout' && $key != 'group')
								{
									if (isset($_POST[$key]) && ($_POST[$key] == 'on' || $_POST[$key] === true)) $_POST[$key] = '1';
									else $_POST[$key] = '0';
									if ($_POST[$key] != $value)
									{
										if ($db->update(array($key => $_POST[$key]), TBL_USERS, '`id` = ' . $_REQUEST['uid'])) $temp[$key] = $_POST[$key];
										else log_in_file($db->error, DIE_IF_ERROR);
									}
								}
							}
						}
					}

					if ($db->select('*', TBL_GROUP, '`id` !=0'))
					{
						$group = $db->res_arr();
						if ($group)
						{
							$select_group = '<select name="group">';
							foreach ($group as $val)
							{
								if ($val['id'] == $temp['group']) $selected = ' selected'; else $selected = '';
								$select_group .= '<option value="' . $val['id'] . '"' . $selected . '>' . $val['name'] . '</option>';
							}
							$select_group .= '</select>';

							foreach ($temp as $key => $value)
							{
								if ($key != 'id' && $key != 'login' && $key != 'password' && $key != 'real_name' && $key != 'email' && $key != 'avatar' && $key != 'date_regist' && $key != 'date_last_activ' && $key != 'date_last_logout' && $key != 'group')
								{
									$array_data['L_' . strtoupper($key)] = $lang['admin_' . $key];
									if ($value == 1 || $value == '1' || $value === true) $array_data['D_' . strtoupper($key)] = ' checked'; else $array_data['D_' . strtoupper($key)] = '';
								}
							}

							$array_data = array_merge ($array_data, array (
											'IF_FIND_USER' => true,
											'L_LOGIN' => $lang['admin_login'],
											'L_EMAIL' => $lang['admin_email'],
											'L_REAL_NAME' => $lang['admin_real_name'],
											'L_AVATAR' => $lang['admin_avatar'],
											'L_GROUP' => $lang['main_group'],
											'L_USER_RIGHTS' => $lang['admin_user_rights'],
											'L_HELP_EDIT' => $lang['admin_help_edit_user'],
											'L_SAVE_USER' => $lang['admin_save_user'],

											'D_LOGIN' => $temp['login'],
											'D_EMAIL' => $temp['email'],
											'D_REAL_NAME' => $temp['real_name'],
											'D_GROUP' => $select_group,

											'U_AVATAR' => $work->config['site_url'] . $work->config['avatar_folder'] . '/' . $temp['avatar']
							));
						}
						else log_in_file('Unable to get the group', DIE_IF_ERROR);
					}
					else log_in_file($db->error, DIE_IF_ERROR);
				}
				else log_in_file('Unable to get the user', DIE_IF_ERROR);
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}
		else
		{
			if (isset($_POST['submit_x']) && !empty($_POST['submit_x']) && isset($_POST['submit_y']) && !empty($_POST['submit_y']) && !empty($_POST['search_user']))
			{
				if ($_POST['search_user'] == '*') $_POST['search_user'] = '%';

				if ($db->select('*', TBL_USERS, '`real_name` LIKE \'%' . $_POST['search_user'] . '%\''))
				{
					$find = $db->res_arr();
					if ($find)
					{
						$find_data = '';
						foreach ($find as $val)
						{
							$find_data .= ', <a href="' . $work->config['site_url']  . '?action=admin&subact=admin_user&uid=' . $val['id'] . '" title="' . $val['real_name'] . '">' . $val['real_name'] . '</a>';
						}
						$find_data = $lang['admin_find_user'] . ': ' . substr($find_data, 2) . '.';
					}
					else $find_data = $lang['admin_no_find_user'];
				}
				else log_in_file($db->error, DIE_IF_ERROR);

				$array_data = array_merge ($array_data, array (
							'D_FIND_USER' => $find_data,

							'IF_NEED_USER' => true
				));
				if ($_POST['search_user'] == '%') $_POST['search_user'] = '*';
			}

			$array_data = array_merge ($array_data, array (
							'L_SEARCH_USER' => $lang['admin_search_user'],
							'L_HELP_SEARCH' => $lang['admin_help_search_user'],

							'D_SEARCH_USER' => isset($_POST['search_user']) ? $_POST['search_user'] : '',
							'IF_NEED_FIND' => true
			));
		}

		$act = '';
		$title = $lang['admin_admin_user'];
		$main_block = $template->create_template('admin_user.tpl', $array_data);
	}
	elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'admin_group') // иначе если запрошено Управление группами, то...
	{
		$array_data = array(); // инициируем массив

		$array_data = array(
						'NAME_BLOCK' => $lang['admin_title'] . ' - ' . $lang['admin_admin_group'],

						'IF_SELECT_GROUP' => false,
						'IF_EDIT_GROUP' => false
		); // наполняем массив данными для замены по шаблону - по умолчанию все блоки отключены

		if (isset($_POST['submit_x']) && !empty($_POST['submit_x']) && isset($_POST['submit_y']) && !empty($_POST['submit_y']) && (isset($_POST['id_group']) && mb_ereg('^[0-9]+$', $_POST['id_group'])))
		{
			if ($db->select('*', TBL_GROUP, '`id` = ' . $_POST['id_group']))
			{
				$temp = $db->res_row();
				if ($temp)
				{
					if (isset($_POST['name_group']) && !empty($_POST['name_group']) && $_POST['name_group'] != $temp['name'])
					{
						if ($db->update(array('name' => $_POST['name_group']), TBL_GROUP, '`id` = ' . $_POST['id_group'])) $temp['name'] = $_POST['name_group'];
						else log_in_file($db->error, DIE_IF_ERROR);
					}
					foreach ($temp as $key => $value)
					{
						if ($key != 'id' && $key != 'name')
						{
							if (isset($_POST[$key]) && ($_POST[$key] == 'on' || $_POST[$key] === true)) $_POST[$key] = '1';
							else $_POST[$key] = '0';
							if ($_POST[$key] != $value)
							{
								if ($db->update(array($key => $_POST[$key]), TBL_GROUP, '`id` = ' . $_POST['id_group'])) $temp[$key] = $_POST[$key];
								else log_in_file($db->error, DIE_IF_ERROR);
							}
						}
					}
					$_POST['group'] = $_POST['id_group'];
				}
				else log_in_file('Unable to get the group', DIE_IF_ERROR);
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}

		if (isset($_POST['submit_x']) && !empty($_POST['submit_x']) && isset($_POST['submit_y']) && !empty($_POST['submit_y']) && mb_ereg('^[0-9]+$', $_POST['group']))
		{
			if ($db->select('*', TBL_GROUP, '`id` = ' . $_POST['group']))
			{
				$temp = $db->res_row();
				if ($temp)
				{
					foreach ($temp as $key => $value)
					{
						if ($key != 'id' && $key != 'name')
						{
							$array_data['L_' . strtoupper($key)] = $lang['admin_' . $key];
							if ($value == 1) $array_data['D_' . strtoupper($key)] = ' checked'; else $array_data['D_' . strtoupper($key)] = '';
						}
					}

					$array_data = array_merge ($array_data, array (
									'L_NAME_GROUP' => $lang['main_group'],
									'L_GROUP_RIGHTS' => $lang['admin_group_rights'],
									'L_SAVE_GROUP' => $lang['admin_save_group'],

									'D_ID_GROUP' => $temp['id'],
									'D_NAME_GROUP' => $temp['name'],
									'IF_EDIT_GROUP' => true
					));
				}
				else log_in_file('Unable to get the group', DIE_IF_ERROR);
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}
		else
		{
			if ($db->select('*', TBL_GROUP))
			{
				$group = $db->res_arr();
				if ($group)
				{
					$select_group = '<select name="group">';
					foreach ($group as $val)
					{
						$select_group .= '<option value="' . $val['id'] . '">' . $val['name'] . '</option>';
					}
					$select_group .= '</select>';
					$array_data = array_merge ($array_data, array (
									'L_SELECT_GROUP' => $lang['admin_select_group'],
									'L_EDIT' => $lang['admin_edit_group'],

									'D_GROUP' => $select_group,
									'IF_SELECT_GROUP' => true
					));
				}
				else log_in_file('Unable to get the group', DIE_IF_ERROR);
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}

		$act = '';
		$title = $lang['admin_admin_group'];
		$main_block = $template->create_template('admin_group.tpl', $array_data);
	}
	else
	{
		// наполним данные для пунктов выбора в Админке
		$select_subact = '&bull;&nbsp;<a href="' . $work->config['site_url'] . '?action=admin&subact=settings" title="' . $lang['admin_settings'] . '">' . $lang['admin_settings'] . '</a><br />'; // Общие настройки
		$select_subact .= '&bull;&nbsp;<a href="' . $work->config['site_url'] . '?action=admin&subact=admin_user" title="' . $lang['admin_admin_user'] . '">' . $lang['admin_admin_user'] . '</a><br />'; // Управление пользователями
		$select_subact .= '&bull;&nbsp;<a href="' . $work->config['site_url'] . '?action=admin&subact=admin_group" title="' . $lang['admin_admin_group'] . '">' . $lang['admin_admin_group'] . '</a><br />'; // Управление группами

		$array_data = array(); // инициируем массив

		$array_data = array(
					'NAME_BLOCK' => $lang['admin_title'],
					'L_SELECT_SUBACT' => $lang['admin_select_subact'],

					'D_SELECT_SUBACT' => $select_subact,

					'IF_SESSION_ADMIN_ON' => true,
					'IF_SESSION_ADMIN_OFF' => false
		); // наполняем массив данными для замены по шаблону

		$act = 'admin'; // активный пункт меню - admin
		$title = $lang['admin_title']; // дполнительный заговловок - Администрирование
		$main_block = $template->create_template('admin_start.tpl', $array_data); // формируем центральный блок - список выбора пунктов Админки
	}
}
elseif ((!isset($_SESSION['admin_on']) || $_SESSION['admin_on'] !== true) && $user->user['admin'] == true) // иначе если сессия для Админа не открыта, но пользователь имеет право её открыть, то...
{
	$array_data = array(); // инициируем массив

	$array_data = array(
				'NAME_BLOCK' => $lang['admin_title'],
				'L_ENTER_ADMIN_PASS' => $lang['admin_admin_pass'],
				'L_ENTER' => $lang['main_enter'],

				'IF_SESSION_ADMIN_ON' => false,
				'IF_SESSION_ADMIN_OFF' => true
	); // наполняем массив данными для замены по шаблону

	$act = 'admin'; // активный пункт меню - admin
	$title = $lang['admin_title']; // дполнительный заговловок - Администрирование
	$main_block = $template->create_template('admin_start.tpl', $array_data); // формируем блок для ввода пароля админа
}
else // иначе...
{
	$act = 'main'; // активный пункт меню - main
	$title = $lang['main_main']; // дополнительный заголовок - Главная страницы
	$main_block = $template->template_news($work->config['last_news']); // формируем главную страницу сайта
}

echo $template->create_main_template($act, $title, $main_block); // выводим сформированную страницу сайта
?>
