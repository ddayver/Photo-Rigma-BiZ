<?php
/**
* @file		action/admin.php
* @brief	Администрирование сайта.
* @author	Dark Dayver
* @version	0.1.1
* @date		27/03-2012
* @details	Используется для управления настройками и пользователями галереи.
*/

// Проверка, что файл подключается из индексного, а не набран напрямую в адресной строке
if (IN_GALLERY)
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
			$new_config = $work->config(); // формируем массив настроек, хранящихся в базе на текущий момент

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

			foreach ($new_config as $name => $value) // по результатам проверки внесем новые настройки в базу данных
			{
				$db->query("UPDATE `config` SET `value` = '" . $value . "' WHERE `name` = '" . $name . "'"); // обновление настроек сайта в базе данных
				$work->config[$name] = $value;
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
			if($entry != '.' && $entry !='..' && is_dir($language_dir->path . '/' . $entry))
			{
				if($entry == $work->config['language']) $selected = ' selected'; else $selected = ''; // если очередной язык является текущим для сайта - помечаем его выбранным по-умолчанию
				$language .= '<option value="' . $entry . '"' . $selected . '>' . $entry . '</option>'; // наполняем список доступных языков
			}
		}
		$language_dir->close(); // закрываем работу с папкой языков
		$language .= '</select>'; // открываем список доступных языков

		$themes = '<select name="themes">'; // открываем список доступных тем
		$themes_dir = dir($work->config['site_dir'] . '/themes'); // проверяем допустимые для выбора темы
		while (false !== ($entry = $themes_dir->read())) // до тех пор, пока существуют файлы в папке
		{
			if($entry != '.' && $entry !='..' && is_dir($themes_dir->path . '/' . $entry))
			{
				if($entry == $work->config['themes']) $selected = ' selected'; else $selected = ''; // если очередная тема является текущей для сайта - помечаем его выбранным по-умолчанию
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
			$temp = $db->fetch_array("SELECT * FROM `user` WHERE `id` = " . $_REQUEST['uid']); // запрашиваем данные о пользователе

			if (isset($_POST['submit_x']) && !empty($_POST['submit_x']) && isset($_POST['submit_y']) && !empty($_POST['submit_y'])) // если поступил запрос на сохранение пользователя, то...
			{
				if ($_POST['group'] != $temp['group']) // если была изменена группа пользователя, то...
				{
					$query = 'UPDATE `user` SET `group` = ' . "'" . $_POST['group'] . "'"; //создаем заготовку запроса
					$temp_group = $db->fetch_array("SELECT * FROM `group` WHERE `id` = " . $_POST['group']); // запрашиваем данные о новой групе для получения прав доступа
					foreach ($temp_group as $key => $value) // разносим данные о правах из ключей и значений в переменные
					{
						if ($key != 'id' && $key != 'name') // если ключ не равен идентификатору или названию группы, то...
						{
							$query .= ', `' . $key . "` = '" . $value . "'"; // дополняем запрос
						}
					}
					$query .= " WHERE `id` = " . $_REQUEST['uid']; // заканчиваем формировать запрос на изменение прав
					$db->query($query); // изменяем группу и права пользователя
				}
				else // иначе, если группа не менялась, то...
				{
					foreach ($temp as $key => $value) // извлекаем права доступа для пользователя, переданные с страницы
					{
						if ($key != 'id' && $key != 'login' && $key != 'password' && $key != 'real_name' && $key != 'email' && $key != 'avatar' && $key != 'date_regist' && $key != 'date_last_activ' && $key != 'date_last_logout' && $key != 'group') // если это поля прав доступа, то...
						{
							if (isset($_POST[$key]) && $_POST[$key] == on) // если галочка была установлена, то...
							{
								$db->query("UPDATE `user` SET `" . $key . "` = '1' WHERE `id` = " . $_REQUEST['uid']); // включим её для пользователя
							}
							else // иначе
							{
								$db->query("UPDATE `user` SET `" . $key . "` = '0' WHERE `id` = " . $_REQUEST['uid']); // отключим эти права
							}
						}
					}
				}
				$temp = $db->fetch_array("SELECT * FROM `user` WHERE `id` = " . $_REQUEST['uid']); // запрашиваем данные о пользователе
			}

			$group = $db->fetch_big_array("SELECT * FROM `group` WHERE `id` !=0"); // запрашиваем список групп, кроме Гость
			$select_group = '<select name="group">'; // начинаем формировать список групп для выбора
			for($i = 1; $i <= $group[0]; $i++) // формируем список групп для выбора
			{
				if ($group[$i]['id'] == $temp['group']) $selected = ' selected'; else $selected = '';
				$select_group .= '<option value="' . $group[$i]['id'] . '"' . $selected . '>' . $group[$i]['name'] . '</option>'; // вносим группы в пункты списка
			}
			$select_group .= '</select>'; // завершаем формировать список групп

			foreach ($temp as $key => $value) // извлекаем права доступа для пользователя
			{
				if ($key != 'id' && $key != 'login' && $key != 'password' && $key != 'real_name' && $key != 'email' && $key != 'avatar' && $key != 'date_regist' && $key != 'date_last_activ' && $key != 'date_last_logout' && $key != 'group') // если это поля прав доступа, то...
				{
					$array_data['L_' . strtoupper($key)] = $lang['admin'][$key]; // передаем название права доступа
					if ($value == 1) $array_data['D_' . strtoupper($key)] = ' checked'; else $array_data['D_' . strtoupper($key)] = ''; // и, если пункт активен - отмечаем его галочкой, иначе - нет
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
			)); // наполняем массив данными для замены по шаблону и включаем блок отображения прав пользователя
		}
		else // иначе если идентификатор небыл запрошен, то...
		{
			if (isset($_POST['submit_x']) && !empty($_POST['submit_x']) && isset($_POST['submit_y']) && !empty($_POST['submit_y']) && !empty($_POST['search_user'])) // если поступил запрос на поиск пользователя, то...
			{
				if ($_POST['search_user'] == '*') $_POST['search_user'] = '%'; // если пуступила '*' - поиск всех - делаем замену по шаблону
				$find = $db->fetch_big_array("SELECT * FROM `user` WHERE `real_name` LIKE '%" . $_POST['search_user'] . "%'"); // делаем запрос на поиск пользователей, в отображаемом имени которых содержится искомая строка

				if($find && $find[0] > 0) // если найдены такие пользователи, то...
				{
					$find_data = $lang['admin_find_user'] . ': '; // инициируем список пользователей
					for($i = 1; $i <= $find[0]; $i++) // обрабатываем найденных пользователей по списку
					{
						$find_data .= '<a href="' . $work->config['site_url']  . '?action=admin&subact=admin_user&uid=' . $find[$i]['id'] . '" title="' . $find[$i]['real_name'] . '">' . $find[$i]['real_name'] . '</a>'; // формируем список, выводя на экран отображаемое имя пользователя ввиде ссылки на профиль
						if ($i < $find[0]) $find_data .= ', '; // если НЕ последний пользователь, ставим после него запятую
						if ($i == $find[0]) $find_data .= '.'; // если последний - точку
					}
				}
				else // иначе если пользователи не найдены, то...
				{
					$find_data = $lang['admin_no_find_user']; // сообщаем об этом пользователю
				}

				$array_data = array_merge ($array_data, array (
							'D_FIND_USER' => $find_data,

							'IF_NEED_USER' => true
				)); // наполняем массив данными для замены по шаблону - включаем блок вывода найденных пользователей
				if ($_POST['search_user'] == '%') $_POST['search_user'] = '*'; // если изменяли '*' на '%', то возвращаем обратно '*'
			}

			$array_data = array_merge ($array_data, array (
							'L_SEARCH_USER' => $lang['admin_search_user'],
							'L_HELP_SEARCH' => $lang['admin_help_search_user'],

							'D_SEARCH_USER' => isset($_POST['search_user']) ? $_POST['search_user'] : '',
							'IF_NEED_FIND' => true
			)); // наполняем массив данными для замены по шаблону - включаем блок поиска пользователей
		}

		$act = ''; // активного пункта меню - нет
		$title = $lang['admin_admin_user']; // дполнительный заговловок - Управление пользователями
		$main_block = $template->create_template('admin_user.tpl', $array_data); // формируем центральный блок - Управление пользователями
	}
	elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'admin_group') // иначе если запрошено Управление группами, то...
	{
		$array_data = array(); // инициируем массив

		$array_data = array(
						'NAME_BLOCK' => $lang['admin_title'] . ' - ' . $lang['admin_admin_group'],

						'IF_SELECT_GROUP' => false,
						'IF_EDIT_GROUP' => false
		); // наполняем массив данными для замены по шаблону - по умолчанию все блоки отключены

		if(isset($_POST['submit_x']) && !empty($_POST['submit_x']) && isset($_POST['submit_y']) && !empty($_POST['submit_y']) && mb_ereg('^[0-9]+$', $_POST['id_group'])) // если поступила команда на сохранение настроек группы, то...
		{
			$temp = $db->fetch_array("SELECT * FROM `group` WHERE `id` = " . $_POST['id_group']); // запрашиваем текущие данные о группе
			if(isset($_POST['name_group']) && !empty($_POST['name_group'])) $db->query("UPDATE `group` SET `name` = '" . $_POST['name_group'] . "' WHERE `id` = " . $_POST['id_group']); // если не пустое поле названия группы, то заменяем текущее на переданное со страницы
			foreach ($temp as $key => $value) // извлекаем права доступа для группы из базы
			{
				if ($key != 'id' && $key != 'name') // если это поля прав доступа, то...
				{
					if (isset($_POST[$key]) && $_POST[$key] == on) // если со страницы поступил включить право доступа, то...
					{
						$db->query("UPDATE `group` SET `" . $key . "` = '1' WHERE `id` = " . $_POST['id_group']); // включить в базе соотвествующие права
					}
					else // иначе
					{
						$db->query("UPDATE `group` SET `" . $key . "` = '0' WHERE `id` = " . $_POST['id_group']); // отключить права в базе
					}
				}
			}
			$_POST['group'] = $_POST['id_group']; // передаем следующему блоку кода идентификатор группы
		}

		if(isset($_POST['submit_x']) && !empty($_POST['submit_x']) && isset($_POST['submit_y']) && !empty($_POST['submit_y']) && mb_ereg('^[0-9]+$', $_POST['group'])) // если выбрана группа для редактирования, то...
		{
			$temp = $db->fetch_array("SELECT * FROM `group` WHERE `id` = " . $_POST['group']); // запрашиваем данные о групе

			foreach ($temp as $key => $value) // извлекаем права доступа для группы
			{
				if ($key != 'id' && $key != 'name') // если это поля прав доступа, то...
				{
					$array_data['L_' . strtoupper($key)] = $lang['admin'][$key]; // формируем название права доступа
					if ($value == 1) $array_data['D_' . strtoupper($key)] = ' checked'; else $array_data['D_' . strtoupper($key)] = ''; // если право включено - ставим галочку, иначе - нет
				}
			}

			$array_data = array_merge ($array_data, array (
							'L_NAME_GROUP' => $lang['main_group'],
							'L_GROUP_RIGHTS' => $lang['admin_group_rights'],
							'L_SAVE_GROUP' => $lang['admin_save_group'],

							'D_ID_GROUP' => $temp['id'],
							'D_NAME_GROUP' => $temp['name'],
							'IF_EDIT_GROUP' => true
			)); // наполняем массив данными для замены по шаблону - включаем блок редактирования группы
		}
		else // иначе если группа не выбрана
		{
			$group = $db->fetch_big_array("SELECT * FROM `group`"); // формируем список групп
			$select_group = '<select name="group">'; // начинаем список групп
			for($i = 1; $i <= $group[0]; $i++) // формируем список групп для выбора
			{
				$select_group .= '<option value="' . $group[$i]['id'] . '">' . $group[$i]['name'] . '</option>'; // вносим группу в поле
			}
			$select_group .= '</select>'; // закрываем список групп

			$array_data = array_merge ($array_data, array (
							'L_SELECT_GROUP' => $lang['admin_select_group'],
							'L_EDIT' => $lang['admin_edit_group'],

							'D_GROUP' => $select_group,
							'IF_SELECT_GROUP' => true
			)); // наполняем массив данными для замены по шаблону - включен блок выбора группы
		}

		$act = ''; // активного пункта меню - нет
		$title = $lang['admin_admin_group']; // дполнительный заговловок - Управление группами
		$main_block = $template->create_template('admin_group.tpl', $array_data); // формируем центральный блок - Управление группами
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