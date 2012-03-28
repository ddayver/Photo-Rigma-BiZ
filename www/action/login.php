<?php
/**
* @file		action/login.php
* @brief	Работа с пользователями.
* @author	Dark Dayver
* @version	0.1.1
* @date		27/03-2012
* @details	Обработка процедур входа/выхода/регистрации/изменения и просмотра профиля пользователя.
*/

// Проверка, что файл подключается из индексного, а не набран напрямую в адресной строке
if (IN_GALLERY)
{
	die('HACK!');
}

include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/main.php'); // подключаем языковый файл основной страницы
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/menu.php'); // подключаем языковый файл меню
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/login.php'); // подключаем языковый файл пользовательского модуля

$menu_act = ''; // и активного пункта меню нету
if (!isset($_REQUEST['subact']) || empty($_REQUEST['subact'])) // если не указана ни одна дополнительная команда, то...
{
	if ($_SESSION['login_id'] == 0) // проверяем, зарегистрированный ли пользователь вызвал моудль
	{
		$subact = 'login'; // если Не зарегистрированный, то запускаем процедуру входа
	}
	else
	{
		$subact = 'logout'; // иначе - процедуру выхода
	}
}
else // если есть дополнительная команда, то...
{
	$subact = $_REQUEST['subact']; // сохраняем её
}

if (!empty($_SERVER['HTTP_REFERER'])) // проверяем, есть ли реферальная ссылка (есть ли данные - с какой страницы пришел пользователь)
{
	$redirect_url = $_SERVER['HTTP_REFERER']; // если есть, то сохраняем эту ссылку для редиректа
}
else
{
	$redirect_url = $work->config['site_url']; // иначе для редиректа указываем главную страницу сайта
}

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
		if (!isset($_REQUEST['uid']) || empty($_REQUEST['uid'])) // если не указан идентификатор профиля, то...
		{
			$uid = $_SESSION['login_id']; // идентификатор профиля будет равен идентификатору текущего пользователя
		}
		else // иначе
		{
			$uid = $_REQUEST['uid']; // сохраняем идентификатор профиля
		}

		if($uid == $_SESSION['login_id'] || $user->user['admin'] == true) // если идентификатор профиля равен идентификатору текущего пользователя или пользователь является админом (имеет право редактирования любого профиля), то...
		{
			$temp = $db->fetch_array("SELECT * FROM `user` WHERE `id` = " . $uid); // запрашиваем текущие данные о пользователе
			if($temp) // если есть текущие данные, то...
			{
				$max_size_php = $work->return_bytes(ini_get('post_max_size')); // получаем данные о максимально допустимом размере загружаемого файла в настройках PHP (в байтах)
				$max_size = $work->return_bytes($work->config['max_file_size']); // получаем максимально разрешаемый размер файла для заливки в настройках сайта (в байтах)
				if ($max_size > $max_size_php) $max_size = $max_size_php; // если максимально разрешенный к заливке размер файла в настройках сайта больше допустимого с настройках PHP, то ограничиваем размер настройками PHP

				if ($uid != $_SESSION['login_id'] || (isset($_REQUEST['password']) && !empty($_REQUEST['password']) && md5($_REQUEST['password']) == $temp['password'])) // проверяем, что идет редактирование чужого профиля, или, в случае редактирования своего профиля, проверяем првильность указания пароля - подтверждения редактирования, если все правильно, то...
				{
					if (!isset($_REQUEST['edit_password']) || empty($_REQUEST['edit_password'])) // если не указан пароль, то...
					{
						$new_pass = $temp['password']; // оставляем старый пароль пользователя
					}
					else // иначе...
					{
						if ($_REQUEST['re_password'] != $_REQUEST['edit_password']) // проверяем, что новый пароль сопадает с повторно введенным новым паролем
						{
							$new_pass = $temp['password']; // если не совпадают - оставляем старый пароль
						}
						else
						{
							$new_pass = md5($_REQUEST['re_password']); // иначе формируем новый пароль пользователя
						}
					}

					if (!isset($_REQUEST['email']) || empty($_REQUEST['email']) || !mb_eregi("^[a-z0-9_\+-]+(\.[a-z0-9_\+-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*\.([a-z]{2,4})$", $_REQUEST['email']) || $db->num_rows("SELECT * FROM `user` WHERE `id` != " . $uid . " AND `email` = '" . $_REQUEST['email'] . "'")) // проверяем правильность заполнения поля email используя регулярные выражения, так же проверяем, что новый email является уникальным
					{
						$new_email = $temp['email']; // если email введен не правильно - оставляем старый email
					}
					else
					{
						$new_email = $_REQUEST['email']; // иначе сохраняем новый email
					}

					if (!isset($_REQUEST['real_name']) || empty($_REQUEST['real_name']) || $db->num_rows("SELECT * FROM `user` WHERE `id` != " . $uid . " AND `real_name` = '" . $_REQUEST['real_name'] . "'")) // проверяем правильно заполнения Отображаемого имени пользователя и уникальность нового отображаемого имени пользователя
					{
						$new_real_name = $temp['real_name']; // если отображаемое имя не верно или уже существует - оставляем старое отображаемое имя
					}
					else
					{
						$new_real_name = $_REQUEST['real_name']; // иначе сохраняем новое отображаемое имя
					}

					if (!isset($_REQUEST['delete_avatar']) || empty($_REQUEST['delete_avatar']) || $_REQUEST['delete_avatar'] != 'true') // если НЕ поступал запрос на удаление текущей аватары пользователя, то...
					{
						if ($_FILES['file_avatar']['error'] == 0 && $_FILES['file_avatar']['size'] > 0 && $_FILES['file_avatar']['size'] <= $max_size && mb_eregi('(gif|jpeg|png)$', $_FILES['file_avatar']['type'])) // проверяем, указал ли пользователь новое изображение аватара и соотвествует ли загружаемый аватар требованиям (тип файла и его размер в байтах), если да, то...
						{
							$avatar_size = getimagesize($_FILES['file_avatar']['tmp_name']); // получаем размер загружаемого аватара
							$file_avatar = time() . '_' . $work->encodename(basename($_FILES['file_avatar']['name'])); // формируем имя файла аватара в стиле: временный_штамп + перекодированное (очистка от символов кирилицы и спец-символов) имя файла

							if($avatar_size[0] <= $work->config['max_avatar_w'] && $avatar_size[1] <= $work->config['max_avatar_h']) // если размер файла соотвествует настройкам сайта (высота и ширина)
							{
								$path_avatar = $work->config['site_dir'] . $work->config['avatar_folder'] . '/' . $file_avatar; // формируем путь к постоянному месту хранения аватара
								if (move_uploaded_file($_FILES['file_avatar']['tmp_name'], $path_avatar)) // перемещаем загруженный аватар в место постоянного хранения, если аватар переместился, то...
								{
									$new_avatar = $file_avatar; // сохраняем его имя файла
									if($temp['avatar'] != 'no_avatar.jpg') @unlink($work->config['site_dir'] . $work->config['avatar_folder'] . '/' . $temp['avatar']); // и удаляем старый аватар (если он не является стандартный "нет_аватары")
								}
								else
								{
									$new_avatar = $temp['avatar']; // иначе оставляем старое имя аватара
								}
							}
							else // если размер (высота и ширина) не соответствуют
							{
								$new_avatar = $temp['avatar']; // оставляем старое имя аватара
							}
						}
						else // если не указан файл для загрузки аватара или его размеры и тип не соотвествуют настройкам
						{
							$new_avatar = $temp['avatar']; // оставляем старое имя аватара
						}
					}
					else // если пользователь указал удалить аватар, то...
					{
						if($temp['avatar'] != 'no_avatar.jpg') @unlink($work->config['site_dir'] . $work->config['avatar_folder'] . '/' . $temp['avatar']); // удаляем старый аватар (если он не является стандартный "нет_аватары")
						$new_avatar = 'no_avatar.jpg'; // сохраняем пользователю стандартый вывод "нет_аватары"
					}
					$db->query("UPDATE `user` SET `password` = '" . $new_pass . "', `real_name` = '" . $new_real_name . "', `email` = '" . $new_email . "', `avatar` = '" . $new_avatar . "' WHERE `id` = " . $uid); // обновляем пользовательские поля
					$user = new User(); // и перезагружаем их в объект данных пользователя
				}
			}
		}
	}
} // продолжаем стандартное выполнение скрипта

if ($subact == 'logout') // если поступила команда на выход пользователя с сайта
{
	if ($_SESSION['login_id'] == 0) // если пользователь уже вышел или является гостем
	{
		$redirect_time = 3; // устанавливаем время редиректа 3 секунды
		$redirect_message = $lang['main_redirect_title']; // сообщаем о переадресации
	}
	else // иначе если пользователь зарегистрирован
	{
		$redirect_time = 5; // устанавливаем время редиректа 5 сек
		$redirect_message = $user->user['real_name'] . $lang['main_logout_ok']; // указываем сообщение об успешном выходе
		$db->query("UPDATE `user` SET `date_last_activ` = NULL, `date_last_logout` = NOW() WHERE `id` = " . $_SESSION['login_id']); // обновляем поле пользователя о последнем посещении сайта
		$_SESSION['login_id'] = 0; // указываем, что пользователь вышел с сайта и
		$_SESSION['admin_on'] = false; // отключаем вход в Админку (если он был включен)
		$user = new User(); // и перезагружаем данные о пользователя для обнуления всех полей
		@session_destroy(); // разрушаем сессию при необходимости
	}
}
/* elseif ($subact == 'forgot') // Восстановление пароля будет готово после реализации работы с почтой
{
	$redirect_time = 3;
	$redirect_message = $lang['main_redirect_title'];
} */
elseif ($subact == 'regist') // если поступила команда на регистрацию нового пользователя, то...
{
	if ($_SESSION['login_id'] != 0) // проверяем, что пользователь является гостем, если нет, то...
	{
		$redirect_time = 3; // устанавливаем время редиректа 3 сек
		$redirect_message = $lang['main_redirect_title']; // сообщаем о переадресации
		$subact = 'logout'; // указываем скрипты, что в дальнейшем потребуется переадресация
	}
	else // иначе формируем форму ввода данных для регистрации нового пользователя
	{
		$array_data = array(); // инициируем массив

		$array_data = array(
					'NAME_BLOCK' => $lang['login_regist'],
					'L_LOGIN' => $lang['login_login'],
					'L_PASSWORD' => $lang['login_password'],
					'L_RE_PASSWORD' => $lang['login_re_password'],
					'L_EMAIL' => $lang['login_email'],
					'L_REAL_NAME' => $lang['login_real_name'],
					'L_REGISTER' => $lang['login_register'],

					'U_REGISTER' => $work->config['site_url'] . '?action=login&subact=register'
		); // наполняем массив данными для замены по шаблону

		$name_block = $lang['login_regist']; // название центрального блока - Регистрация
		$menu_act = 'regist'; // текущий пункт меню - regist

		$main_block = $template->create_template('register.tpl', $array_data); // формируем страницу регистрации
	}
}
elseif ($subact == 'register') // иначе если поступила команда на сохранение данных, указанных для регистрации пользователя
{
	if ($_SESSION['login_id'] != 0) // проверяем, что пользователь является гостем, если нет, то...
	{
		$redirect_time = 3; // устанавливаем время редиректа 3 сек
		$redirect_message = $lang['main_redirect_title']; // сообщаем о переадресации
	}
	else // если является гостем, то выполняем регистрацию
	{
		$error = false; // формируем указатель, что наличие ошибок = false (ЛОЖЬ)
		$text_error = ''; // инициируем массив для сбора списка ошибок при регистрации

		if (!isset($_REQUEST['login']) || empty($_REQUEST['login']) || !mb_ereg('^[a-zA-Z0-9]{1,32}$', $_REQUEST['login'])) // если не указано имя пользователя (логин) или оно не является буквенно-цифрового формата а так же длина меньше 1 или больше 32, то...
		{
			$error = true; // указываем на ошибку при регистрации
			$text_error .= '-&nbsp;' . $lang['login_error_login'] . '<br />'; // сообщаем об ошибке в имени пользователя
		}
		else
		{
			$register['login'] = $_REQUEST['login']; // иначе сохраняем имя пользователя
		}

		if (!isset($_REQUEST['password']) || empty($_REQUEST['password'])) // если не указан пароль, то...
		{
			$error = true; // указываем на ошибку при регистрации
			$text_error .= '-&nbsp;' . $lang['login_error_password'] . '<br />'; // сообщаем об ошибке в пароле
		}
		else
		{
			$register['password'] = $_REQUEST['password']; // инче сохраняем пароль
		}

		if ($_REQUEST['re_password'] != $register['password']) // если повторно введенный пароль не совпадает с оригиналом, то...
		{
			$error = true; // указываем на ошибку при регистрации
			$text_error .= '-&nbsp;' . $lang['login_error_re_password'] . '<br />'; // сообщаем об ошибке повторно введенного пароля
		}
		else
		{
			$register['re_password'] = md5($register['password']); // иначе сохраняем кодированный вариант пароля
		}

		if (!isset($_REQUEST['email']) || empty($_REQUEST['email']) || !mb_eregi("^[a-z0-9_\+-]+(\.[a-z0-9_\+-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*\.([a-z]{2,4})$", $_REQUEST['email'])) // проверяем правильность ввода email, если email введен не правильно или имеет неверный формат, то...
		{
			$error = true; // указываем на ошибку при регистрации
			$text_error .= '-&nbsp;' . $lang['login_error_email'] . '<br />'; // сообщаем об ошибке ввода email
		}
		else
		{
			$register['email'] = $_REQUEST['email']; // иначе сохраняем введенный email
		}

		if (!isset($_REQUEST['real_name']) || empty($_REQUEST['real_name'])) // проверяем, что указано отображаемое имя, если нет, то...
		{
			$error = true; // указываем на ошибку при регистрации
			$text_error .= '-&nbsp;' . $lang['login_error_real_name'] . '<br />'; // сообщаем об ошибке в отображаемом имени
		}
		else
		{
			$register['real_name'] = $_REQUEST['real_name']; // иначе сохраняем отображаемое имя
		}

		if ($db->num_rows("SELECT * FROM `user` WHERE `login` = '" . $register['login'] . "'")) // если указанное имя пользователя уже существует в базе, то...
		{
			$error = true; // указываем на ошибку при регистрации
			$text_error .= '-&nbsp;' . $lang['login_error_login_exists'] . '<br />'; // укажем на ошибку в имени пользователя
		}

		if ($db->num_rows("SELECT * FROM `user` WHERE `email` = '" . $register['email'] . "'")) // если указанный email уже существует в базе, то...
		{
			$error = true; // указываем на ошибку при регистрации
			$text_error .= '-&nbsp;' . $lang['login_error_email_exists'] . '<br />'; // укажем на ошибку в email
		}

		if ($db->num_rows("SELECT * FROM `user` WHERE `real_name` = '" . $register['real_name'] . "'")) // если указанное отображаемое имя уже существует в базе, то...
		{
			$error = true; // указываем на ошибку при регистрации
			$text_error .= '-&nbsp;' . $lang['login_error_real_name_exists'] . '<br />'; // укажем на ошибку в отображаемом имени
		}

		if ($error) // если возникла ошибка при регистрации, то...
		{
			$redirect_time = 10; // устанавливаем время редиректа 10 сек
			$redirect_message = $lang['login_error'] . '<br /><br />' . $text_error; // формируем вывод сообщений об ошибках при регистрации
		}
		else // иначе проводим процедуру создания пользователя
		{
			$query = 'INSERT INTO `user` (`login`, `password`, `real_name`, `email`, `group`'; //создаем заготовку
			$query_end = ''; // команды в базу данных
			$temp = $db->fetch_array("SELECT * FROM `group` WHERE `id` = 1"); // запрашиваем данные о групе "Пользователи" для получения обычных прав доступа
			foreach ($temp as $key => $value) // разносим данные о правах из ключей и значений в переменные
			{
				if ($key != 'id' && $key != 'name') // если ключ не равен идентификатору или названию группы, то...
				{
					$query .= ', `' . $key . '`'; // дополняем начало запроса параметром ключа
					$query_end .= ', ' . $value; // и конец - значение ключа
				}
			}
			$query .= ") VALUES ('" . $register['login'] . "', '" . $register['re_password'] . "', '" . $register['real_name'] . "', '" . $register['email'] . "', 1" . $query_end . ")"; // формируем запрос на создание пользователя из ранее созданных переменных

			if($db->query($query)) // выполняем запрос, если удачно, то...
			{
				$redirect_time = 5; // устанавливаем время редиректа 5 сек
				$redirect_message = $lang['login_user'] . ' ' . $register['real_name'] . ' ' . $lang['login_registered']; // формируем сообщение об удачной регистрации пользователя
				$redirect_url = $work->config['site_url'] . '?action=login&subact=profile'; // редирект будем произведен в профиль пользователя для его дальнейшего редактирования
			}
			else // если запрос не выполнился, то...
			{
				$redirect_time = 10; // устанавливаем время редиректа 10 сек
				$redirect_message = $lang['login_error']; // выводим сообщение о невозможности регистрации
			}
		}
	}
}
elseif ($subact == 'login') // если поступила команда на вход на сайт, то...
{
	if($_SESSION['login_id'] != 0) // проверяем, что пользователь еще не зашел, если зашел, то...
	{
		$redirect_time = 3; // устанавливаем время редиректа 3 сек
		$redirect_message = $lang['main_redirect_title']; // сообщаем о переадресации
	}
	else // если является гостем, то проходим процедуру входа на сайт
	{
		if(!empty($_POST['login']) && !empty($_POST['password'])) // если поля login и password не пустые, то...
		{
			$redirect_time = 5; // устанавливаем время редиректа 5 сек
			$temp_user = $db->fetch_array("SELECT `id` , `login` , `password` , `real_name` FROM `user` WHERE `login` = '" . $_POST['login'] . "'"); // запрашиваем данные о пользователе с указанным именем
			if(!empty($temp_user)) // если данные существуют, то...
			{
				if(md5($_POST['password']) == $temp_user['password']) // проверяем правильность ввода пароля
				{
					$_SESSION['login_id'] = $temp_user['id']; // если совпал пароль, то сохраняем в сессии идентификатор пользователя
					$redirect_message = $temp_user['real_name'] . $lang['main_login_ok']; // сообщаем об удачном входе
					$user = new User(); // и обновляем данные о пользователе
				}
				else // если пароль не верен, то...
				{
					$_SESSION['login_id'] = 0; // указываем, что идентификатор - гостевой
					$redirect_message = $lang['main_login_error']; // сообщаем об ошибке входа
				}
			}
			else // если нет такого пользователя, то...
			{
				$_SESSION['login_id'] = 0; // указываем, что идентификатор - гостевой
				$redirect_message = $lang['main_login_error']; // сообщаем об ошибке входа
			}
		}
		else // если не введено поле имени или пароля пользователя, то...
		{
			$redirect_time = 3; // устанавливаем время редиректа 3 сек
			$redirect_message = $lang['main_redirect_title']; // сообщаем о переадресации
		}
	}
}
elseif($subact == 'profile') // иначе если поступила команда на вывод профиля для просмотр или редактирования
{
	if ($_SESSION['login_id'] == 0 && (!isset($_REQUEST['uid']) || empty($_REQUEST['uid']))) // если пользователь не зарегистрирован на сайте и нет запрошенного идентификатора профиля, то...
	{
		$redirect_time = 3; // устанавливаем время редиректа 3 сек
		$redirect_message = $lang['main_redirect_title']; // сообщаем о переадресации
		$subact = 'logout'; // указываем скрипту на необходимость редиректа
	}
	else // иначе...
	{
		$menu_act = 'profile'; // текущий пункт меню - profile

		if (!isset($_REQUEST['uid']) || empty($_REQUEST['uid'])) // если не указан запрашиваемый профиль
		{
			$uid = $_SESSION['login_id']; // указываем использовать текущий
		}
		else
		{
			$uid = $_REQUEST['uid']; // иначе - запрошенный профиль
		}

		if($uid == $_SESSION['login_id'] || $user->user['admin'] == true) // если запрошен текущий профиль или пользователь с правами админа (имеет право на редактирование любого профиля), то...
		{
			$temp = $db->fetch_array("SELECT * FROM `user` WHERE `id` = " . $uid); // запрашиваем данные о профиле
			if($temp) //если есть данные, то...
			{
				$name_block = $lang['login_edit_profile'] . ' ' . $temp['real_name']; // название блока - Редактирование профиля с указанием отображаемогоимени пользователя
				$max_size_php = $work->return_bytes(ini_get('post_max_size')); // получаем данные о максимально допустимом размере загружаемого файла в настройках PHP (в байтах)
				$max_size = $work->return_bytes($work->config['max_file_size']); // получаем максимально разрешаемый размер файла для заливки в настройках сайта (в байтах)
				if ($max_size > $max_size_php) $max_size = $max_size_php; // если максимально разрешенный к заливке размер файла в настройках сайта больше допустимого с настройках PHP, то ограничиваем размер настройками PHP

				if ($uid == $_SESSION['login_id']) // если редактирование собственного профиля, то...
				{
					$confirm_password = true;// требуется подтверждение изменений текущим паролем
				}
				else
				{
					$confirm_password = false; // иначе подтверждение изменений текущим паролем не требуется
				}

				$temp2 = $db->fetch_array("SELECT * FROM `group` WHERE `id` = " . $temp['group']); // запрашиваем данные о группе, в которой состоит пользователь
				if (!$temp2) // если группа не существует, то...
				{
					$temp['group'] = 0; // устанавливаем пользователю группу "Гость"
					$temp2 = $db->fetch_array("SELECT * FROM `group` WHERE `id` = " . $temp['group']); // и повторно запрашиваем данные о группе
				}

				$array_data = array(); // инициируем массив

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
				); // наполняем массив данными для замены по шаблону

				$main_block = $template->create_template('profile_edit.tpl', $array_data); // формируем страницу редактирования профиля
			}
			else // если профиль не существует, то...
			{
				$redirect_time = 3; // устанавливаем время редиректа 3 сек
				$redirect_message = $lang['main_redirect_title']; // сообщаем о переадресации
				$subact = 'logout'; // указываем скрипту на необходимость редиректа
			}
		}
		else // иначе выводим профиль в редиме просмотра
		{
			$temp = $db->fetch_array("SELECT * FROM `user` WHERE `id` = " . $uid); // запрашиваем данные о пользователе
			if($temp) // если данные существуют, то...
			{
				$name_block = $lang['login_profile'] . ' ' . $temp['real_name']; // формируем название блока - Профиль пользователя и отображаемое имя
				$menu_act = ''; // активного пункта меню - нет

				$temp2 = $db->fetch_array("SELECT * FROM `group` WHERE `id` = " . $temp['group']); // запрашиваем данные о группе, которой принадлежит пользователь
				if (!$temp2) // если группа не существует, то...
				{
					$temp['group'] = 0; // устанавливаем пользователю группу "Гость"
					$temp2 = $db->fetch_array("SELECT * FROM `group` WHERE `id` = " . $temp['group']); // и повторно запрашиваем данные о группе
				}

				$array_data = array(); // инициируем массив

				$array_data = array(
								'NAME_BLOCK' => $name_block,
								'L_EMAIL' => $lang['login_email'],
								'L_REAL_NAME' => $lang['login_real_name'],
								'L_AVATAR' => $lang['login_avatar'],
								'L_GROUP' => $lang['main_group'],

								'D_EMAIL' => str_replace('.', '-dot-', str_replace('@', '[at]', $temp['email'])),
								'D_REAL_NAME' => $temp['real_name'],
								'D_GROUP' => $temp2['name'],

								'U_AVATAR' => $work->config['site_url'] . $work->config['avatar_folder'] . '/' . $temp['avatar']
				); // наполняем массив данными для замены по шаблону

				$main_block = $template->create_template('profile_view.tpl', $array_data); // формируем страницу просмотра профиля
			}
			else // иначе если профиля не существует
			{
				$redirect_time = 3; // устанавливаем время редиректа 3 сек
				$redirect_message = $lang['main_redirect_title']; // сообщаем о переадресации
				$subact = 'logout'; // указываем скрипту на необходимость редиректа
			}
		}
	}
}
else // если не поступило ни одной известной команды, то формируем переадресацию
{
	$redirect_time = 3; // устанавливаем время редиректа 3 сек
	$redirect_message = $lang['main_redirect_title']; // сообщаем о переадресации
}

$redirect = array(); // создаем пустой массив переадресации

if ($subact != 'regist' && $subact != 'profile') // если командой было не regist и не profile (или не было указания на редирект путем изменения текущей команды, к примеру $subact = 'logout'), то формируем данные для редиректа
{
	$array_data = array(); // инициируем массив

	$array_data = array(
				'L_REDIRECT_DESCRIPTION' => $lang['main_redirect_description'],
				'L_REDIRECT_URL' => $lang['main_redirect_url'],

				'L_REDIRECT_MASSAGE' => $redirect_message,
				'U_REDIRECT_URL' => $redirect_url
	); // наполняем массив данными для замены по шаблону

	$redirect = array(
				'U_REDIRECT_URL' => $redirect_url,
				'REDIRECT_TIME' => $redirect_time,
				'IF_NEED_REDIRECT' => true
	); // наполняем массив данными для редиректа
	$name_block = $lang['main_redirect_title']; // устанавливаем нахванием блока - переадресацию
	$main_block = $template->create_template('redirect.tpl', $array_data); // формируем главный блок - блок переадерсации
}
echo $template->create_main_template($menu_act, $name_block, $main_block, $redirect); // выводим сформированную страницу
?>