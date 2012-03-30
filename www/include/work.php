<?php
/**
* @file		include/work.php
* @brief	Общий класс (набор функций)
* @author	Dark Dayver
* @version	0.2.0
* @date		28/03-2012
* @details	Содержит общий класс (набор функций) + хранилище для данных о конфигурации.
*/
if (IN_GALLERY)
{
	die('HACK!');
}

/// Общий класс (набор функций) + хранилище для данных о конфигурации.
/**
* Данный класс содержит набор общих для всей галереи функций, а так же используется для хранения данных о конфигурации.
*/
class work
{
	var $config = array(); ///< Массив, хранящий конфигурацию.
	private $array_rules = array(); ///< Массив с правилами защиты.

	/// Конструктор класса, выполняет ряд ключевых задач.
	/**
	* -# Формирует массив, хранящий конфигурацию;
	* -# Формирует массив, хранящий правила защиты.
	* @see ::$config, ::$db
	*/
	function work()
	{
		global $db2, $config;
		unset($config['dbpass']);
		$this->config = $config;

		$this->array_rules = array('http_', '_server', 'delete%20', 'delete ', 'delete-', 'delete(', '(delete',  'drop%20', 'drop ', 'create%20', 'update-', 'update(', '(update', 'insert-', 'insert(', '(insert', 'create ', 'create(', 'create-', '(create', 'update%20', 'update ', 'insert%20', 'insert ', 'select%20', 'select ', 'bulk%20', 'bulk ', 'union%20', 'union ', 'select-', 'select(', '(select', 'union-', '(union', 'union(', 'or%20', 'or ', 'and%20', 'and ', 'exec', '@@', '%22', '"', 'openquery', 'openrowset', 'msdasql', 'sqloledb', 'sysobjects', 'syscolums',  'syslogins', 'sysxlogins', 'char%20', 'char ', 'into%20', 'into ', 'load%20', 'load ', 'msys', 'alert%20', 'alert ', 'eval%20', 'eval ', 'onkeyup', 'x5cx', 'fromcharcode', 'javascript:', 'javascript.', 'vbscript:', 'vbscript.', 'http-equiv', '->', 'expression%20', 'expression ', 'url%20', 'url ', 'innerhtml', 'document.', 'dynsrc', 'jsessionid', 'style%20', 'style ', 'phpsessid', '<applet', '<div', '<emded', '<iframe', '<img', '<meta', '<object', '<script', '<textarea', 'onabort', 'onblur', 'onchange', 'onclick', 'ondblclick', 'ondragdrop', 'onerror',  'onfocus', 'onkeydown', 'onkeypress', 'onload', 'onmouse', 'onmove', 'onreset', 'onresize', 'onselect', 'onsubmit', 'onunload', 'onreadystatechange', 'xmlhttp', 'uname%20', 'uname ',  '%2C', 'union+', 'select+', 'delete+', 'create+', 'bulk+', 'or+', 'and+', 'into+', 'kill+', '+echr', '+chr', 'cmd+', '+1', 'user_password', 'id%20', 'id ', 'ls%20', 'ls ', 'cat%20', 'cat ', 'rm%20', 'rm ', 'kill%20', 'kill ', 'mail%20', 'mail ', 'wget%20', 'wget ', 'wget(', 'pwd%20', 'pwd ', 'objectclass', 'objectcategory', '<!-%20', '<!- ', 'total%20', 'total ', 'http%20request', 'http request', 'phpb8b4f2a0', 'phpinfo', 'php:', 'globals', '%2527', '%27', '\'', 'chr(', 'chr=', 'chr%20', 'chr ', '%20chr', ' chr', 'cmd=', 'cmd%20', 'cmd', '%20cmd', ' cmd', 'rush=', '%20rush', ' rush', 'rush%20', 'rush ', 'union%20', 'union ', '%20union', ' union', 'union(', 'union=', '%20echr', ' echr', 'esystem', 'cp%20', 'cp ', 'cp(', '%20cp', ' cp', 'mdir%20', 'mdir ', '%20mdir', ' mdir', 'mdir(', 'mcd%20', 'mcd ', 'mrd%20', 'mrd ', 'rm%20', 'rm ', '%20mcd', ' mcd', '%20mrd', ' mrd', '%20rm', ' rm', 'mcd(', 'mrd(', 'rm(', 'mcd=', 'mrd=', 'mv%20', 'mv ', 'rmdir%20', 'rmdir ', 'mv(', 'rmdir(', 'chmod(', 'chmod%20', 'chmod ', 'cc%20', 'cc ', '%20chmod', ' chmod', 'chmod(', 'chmod=', 'chown%20', 'chown ', 'chgrp%20', 'chgrp ', 'chown(', 'chgrp(', 'locate%20', 'locate ', 'grep%20', 'grep ', 'locate(', 'grep(', 'diff%20', 'diff ', 'kill%20', 'kill ', 'kill(', 'killall', 'passwd%20', 'passwd ', '%20passwd', ' passwd', 'passwd(', 'telnet%20', 'telnet ', 'vi(', 'vi%20', 'vi ', 'nigga(', '%20nigga', ' nigga', 'nigga%20', 'nigga ', 'fopen', 'fwrite', '%20like', ' like', 'like%20', 'like ', '$_', '$get', '.system', 'http_php', '%20getenv', ' getenv', 'getenv%20', 'getenv ', 'new_password', '/password', 'etc/', '/groups', '/gshadow', 'http_user_agent', 'http_host', 'bin/', 'wget%20', 'wget ', 'uname%5c', 'uname', 'usr', '/chgrp', '=chown', 'usr/bin', 'g%5c', 'g\\', 'bin/python', 'bin/tclsh', 'bin/nasm', 'perl%20', 'perl ', '.pl', 'traceroute%20', 'traceroute ', 'tracert%20', 'tracert ', 'ping%20', 'ping ', '/usr/x11r6/bin/xterm', 'lsof%20', 'lsof ', '/mail', '.conf', 'motd%20', 'motd ', 'http/1.', '.inc.php', 'config.php', 'cgi-', '.eml', 'file%5c://', 'file\:', 'file://', 'window.open', 'img src', 'img%20src', 'img src', '.jsp', 'ftp.', 'xp_enumdsn', 'xp_availablemedia', 'xp_filelist', 'nc.exe', '.htpasswd', 'servlet', '/etc/passwd', '/etc/shadow', 'wwwacl', '~root', '~ftp', '.js', '.jsp', '.history', 'bash_history', '~nobody', 'server-info', 'server-status', '%20reboot', ' reboot', '%20halt', ' halt', '%20powerdown', ' powerdown', '/home/ftp', '=reboot', 'www/', 'init%20', 'init ','=halt', '=powerdown', 'ereg(', 'secure_site', 'chunked', 'org.apache', '/servlet/con', '/robot', 'mod_gzip_status', '.inc', '.system', 'getenv', 'http_', '_php', 'php_', 'phpinfo()', '<?php', '?>', '%3C%3Fphp', '%3F>', 'sql=', '_global', 'global_', 'global[', '_server', 'server_', 'server[', '/modules', 'modules/', 'phpadmin', 'root_path', '_globals', 'globals_', 'globals[', 'iso-8859-1', '?hl=', '%3fhl=', '.exe', '.sh', '%00', rawurldecode('%00'), '_env', '/*', '\\*');

		if ($db2->select('*', TBL_CONFIG))
		{
			$result = $db2->res_arr();
			if ($result)
			{
				foreach ($result as $tmp)
				{
					$this->config[$tmp['name']] = $tmp['value'];
				}
			}
			else
			{
				log_in_file('Unable to get the settings', DIE_IF_ERROR);
			}
		}
		else log_in_file($db2->error, DIE_IF_ERROR);
		mb_regex_encoding ('UTF-8');
	}

	/// Функция проверки полученного сервером URL на содержание вредоносного кода.
	/**
	* @return True, если строка содержала вредоносный код, иначе возвращает False.
	*/
	function url_check()
	{
		$query_string = strtolower($_SERVER['QUERY_STRING']);

		$hack = false;

		foreach ($this->array_rules as $rules)
		{
			$rules = mb_convert_encoding($rules, 'UTF-8', 'auto');
			$query_string = mb_convert_encoding($query_string, 'UTF-8', 'auto');
			if (mb_ereg(quotemeta($rules), $query_string))
			{
				$_SERVER['QUERY_STRING'] = '';
				log_in_file('Hack attempt: "' . $rules . '" in query string: "' . $query_string . '"!');
				$hack = true;
			}
		}
		return $hack;
	}

	/// Функция проверки данных, переданных через $_POST[]
	/**
	* @param $field является текстовой переменной и указывает на имя элемента массива $_POST[] (обязательное поле)
	* @param $isset указывает, проверять ли функции наличие $_POST[$field] через isset() (по-умолчанию False - не проверять)
	* @param $empty указывает, проверять ли функции, что $_POST[$field] не является пустым (по-умолчанию False - не проверять)
	* @param $regexp указывает, проверять ли функции, что $_POST[$field] соответствует регулярному выражению (по-умолчанию False - не проверять)
	* @param $not_zero указывает, проверять ли функции, что $_POST[$field] не равно нулю (по-умолчанию False - не проверять)
	* @return False, если $_POST[$field] не соотвествует заданному набору параметров, иначе True.
	* @see work::check_field
	*/
	function check_post($field, $isset = false, $empty = false, $regexp = false, $not_zero = false)
	{
		if ($isset && !isset($_POST[$field])) return false;
		else if ($empty && empty($_POST[$field])) return false;
		else return $this->check_field($_POST[$field], $regexp, $not_zero);
	}

	/// Функция проверки данных, переданных через $_GET[]
	/**
	* @param $field является текстовой переменной и указывает на имя элемента массива $_GET[] (обязательное поле)
	* @param $isset указывает, проверять ли функции наличие $_GET[$field] через isset() (по-умолчанию False - не проверять)
	* @param $empty указывает, проверять ли функции, что $_GET[$field] не является пустым (по-умолчанию False - не проверять)
	* @param $regexp указывает, проверять ли функции, что $_GET[$field] соответствует регулярному выражению (по-умолчанию False - не проверять)
	* @param $not_zero указывает, проверять ли функции, что $_GET[$field] не равно нулю (по-умолчанию False - не проверять)
	* @return False, если $_GET[$field] не соотвествует заданному набору параметров, иначе True.
	* @see work::check_field
	*/
	function check_get($field, $isset = false, $empty = false, $regexp = false, $not_zero = false)
	{
		if ($isset && !isset($_GET[$field])) return false;
		else if ($empty && empty($_GET[$field])) return false;
		else return $this->check_field($_GET[$field], $regexp, $not_zero);
	}

	/// Функция проверки данных, переданных через $_SESSION[]
	/**
	* @param $field является текстовой переменной и указывает на имя элемента массива $_SESSION[] (обязательное поле)
	* @param $isset указывает, проверять ли функции наличие $_SESSION[$field] через isset() (по-умолчанию False - не проверять)
	* @param $empty указывает, проверять ли функции, что $_SESSION[$field] не является пустым (по-умолчанию False - не проверять)
	* @param $regexp указывает, проверять ли функции, что $_SESSION[$field] соответствует регулярному выражению (по-умолчанию False - не проверять)
	* @param $not_zero указывает, проверять ли функции, что $_SESSION[$field] не равно нулю (по-умолчанию False - не проверять)
	* @return False, если $_SESSION[$field] не соотвествует заданному набору параметров, иначе True.
	* @see work::check_field
	*/
	function check_session($field, $isset = false, $empty = false, $regexp = false, $not_zero = false)
	{
		if ($isset && !isset($_SESSION[$field])) return false;
		else if ($empty && empty($_SESSION[$field])) return false;
		else return $this->check_field($_SESSION[$field], $regexp, $not_zero);
	}

	/// Функция проверки содержимого на соотвествие регулярному выражению
	/**
	* @param $field содержит значение, которое надо проверить (обязательное поле)
	* @param $regexp указывает, проверять ли функции, что $field соответствует регулярному выражению (по-умолчанию False - не проверять)
	* @param $not_zero указывает, проверять ли функции, что $field не равно нулю (по-умолчанию False - не проверять)
	* @return False, если $field не соотвествует заданному набору параметров, иначе True.
	* @see work::check_get, work::check_post, work::check_session
	*/
	function check_field($field, $regexp = false, $not_zero = false)
	{
		if (empty($field) && $regexp === false)
		{
			return true;
		}
		$test = true;
		$field_lower = strtolower($field);
		foreach ($this->array_rules as $rules)
		{
			$rules = mb_convert_encoding($rules, 'UTF-8', 'auto');
			$field_lower = mb_convert_encoding($field_lower, 'UTF-8', 'auto');
			if (mb_ereg(quotemeta($rules), $field_lower))
			{
				$test = false;
			}
		}
		if ($regexp)
		{
			$field = mb_convert_encoding($field, 'UTF-8', 'auto');
			if (mb_ereg($regexp, $field)) $test = false;
		}
		if ($not_zero && $field === 0) $test = false;
		return $test;
	}

	/// Функция очистки строки от HTML-тегов и замены специальных символов HTML
	/**
	* @param $field содержит значение, которое надо обработать (обязательное поле).
	* @return Строку, обработанную функциями strip_tags и htmlspecialchars.
	*/
	function clean_field($field)
	{
		$field = strip_tags($field);
		$field = htmlspecialchars($field);
		return $field;
	}

	/// Функция разбивки строки на несколько строк ограниченной длины (на практике пока не проверил)
	/**
	* @param $str содержит строку, которую надо обработать (обязательное поле)
	* @param $width содержит максимально допустимую длину строки на выходе (по-умолчанию 70 символов)
	* @param $break содержит символ, используемый как разделитель строк (по-умолчанию PHP_EOL)
	* @return Строку, разбитую на несколько с указанной максимальной длиной и указанным разделителем.
	*/
	function utf8_wordwrap($str, $width = 70, $break = PHP_EOL)
	{
		if (empty($str) || mb_strlen($str, 'UTF-8') <= $width)
		return $str;
		$br_width = mb_strlen($break, 'UTF-8');
		$str_width = mb_strlen($str, 'UTF-8');
		$return = '';
		$last_space = false;
		for ($i = 0, $count = 0; $i < $str_width; $i++, $count++)
		{
			if (mb_substr($str, $i, $br_width, 'UTF-8') == $break)
			{
				$count = 0;
				$return .= mb_substr($str, $i, $br_width, 'UTF-8');
				$i += $br_width - 1;
				continue;
			}
			if (mb_substr($str, $i, 1, 'UTF-8') == " ")
			{
				$last_space = $i;
			}
			if ($count > $width)
			{
				if (!$last_space)
				{
					$return .= $break;
					$count = 0;
				}
				else
				{
					$drop = $i - $last_space;
					if ($drop > 0)
					{
						$return = mb_substr($return, 0, -$drop);
					}
					$return .= $break;
					$i = $last_space + ($br_width - 1);
					$last_space = false;
					$count = 0;
				}
			}
			$return .= mb_substr($str, $i, 1, 'UTF-8');
		}
		return $return;
	}

	/// Функция получения списка доступных языков на сервере
	/**
	* @return Массив, содержащий данные о доступных на сервере языках, с локализованным выводом названий языков.
	*/
	function get_languages()
	{
		$list_languages = array();
		$i = 0;
		if ($dh = opendir($this->config['site_dir'] . 'language/'))
		{
			while (($file = readdir($dh)) !== false)
			{
				if (is_dir($this->config['site_dir'] . 'language/' . $file) && $file != '..' && $file != '.')
				{
					$list_languages[$i]['value'] = $file;
					include($this->config['site_dir'] . 'language/' . $file . '/main.php');
					$list_languages[$i]['name'] = $lang_name;
					$i++;
				}
			}
			closedir($dh);
		}
		return $list_languages;
	}

	/// Функция получения списка доступных тем оформления на сервере
	/**
	* @return Массив, содержащий данные о доступных на сервере темах оформления.
	*/
	function get_themes()
	{
		$list_themes = array();
		$i = 0;
		if ($dh = opendir($this->config['site_dir'] . 'themes/'))
		{
			while (($file = readdir($dh)) !== false)
			{
				if (is_dir($this->config['site_dir'] . 'themes/' . $file) && $file != '..' && $file != '.' && !preg_match("/^s([0-9]+)$/i", $file))
				{
					$list_themes[$i] = $file;
					$i++;
				}
			}
			closedir($dh);
		}
		return $list_themes;
	}

	/// Функция, генерирующая анти-спам-бот вопрос и ответ
	/**
	* @return Массив, содержащий в элементе question сам вопрос и в элементе answer - ответ на него.
	*/
	function gen_captcha()
	{
		$captcha = array();
		$tmp[1] = rand(1, 9);
		$tmp[2] = rand(1, 9);
		$tmp[3] = rand(1, 9);
		$tmp[4] = rand(1, 2);
		$tmp[5] = rand(1, 2);
		$captcha['question'] = '';
		$captcha['answer'] = 0;
		switch ($tmp[5])
		{
			case 1:
				$captcha['question'] = '( ' . $tmp[2] . ' x ' . $tmp[3] . ' )';
				$captcha['answer'] = $tmp[2] * $tmp[3];
				break;
			case 2:
			default:
				$captcha['question'] = '( ' . $tmp[2] . ' + ' . $tmp[3] . ' )';
				$captcha['answer'] = $tmp[2] + $tmp[3];
				break;
		}
		switch ($tmp[4])
		{
			case 1:
				$captcha['question'] = $tmp[1] . ' x ' . $captcha['question'];
				$captcha['answer'] = $tmp[1] * $captcha['answer'];
				break;
			case 2:
			default:
				$captcha['question'] = $tmp[1] . ' + ' . $captcha['question'];
				$captcha['answer'] = $tmp[1] + $captcha['answer'];
				break;
		}
		return $captcha;
	}

	/// Функция обработки email-адреса
	/**
	* @param $email содержит email-адрес для обработки (обязательный параметр)
	* @return Email-адрес, в котором '@' заменено на '[at]' и '.' - на '[dot]'.
	*/
	function filt_email($email)
	{
		$email = str_replace('@', '[at]', $email);
		$email = str_replace('.', '[dot]', $email);
		return $email;
	}

	/// Функция формирует информационную строку по конктретному разделу
	/**
	* @param $cat_id содержит идентификатор раздела или, если $user_flag = 1,то идентификатор пользователя
	* @param $user_flag флаг, указывающий формировать ли обычный список разделов (0) или список пользовательских альбомов (1)
	* @return Информационная строка по конктретному разделу.
	* @see ::$db
	*/
	function category($cat_id = 0, $user_flag = 0)
	{
		global $db2, $lang, $user, $template;

		$photo = array();

		if($user_flag == 1)
		{
			if ($db2->select(array('id', 'name'), TBL_CATEGORY, '`id` = 0'))
			{
				$temp = $db2->res_row();
				if ($temp)
				{
					if ($db2->select('real_name', TBL_USERS, '`id` = ' . $cat_id))
					{
						$temp2 = $db2->res_row();
						if ($temp2)
						{
							$add_query = ' AND `user_upload` = ' . $cat_id;
							$temp['description'] = $temp['name'] . ' ' . $temp2['real_name'];
							$temp['name'] = $temp2['real_name'];
						}
						else log_in_file('Unable to get the user', DIE_IF_ERROR);
					}
					else log_in_file($db2->error, DIE_IF_ERROR);
				}
				else log_in_file('Unable to get the category', DIE_IF_ERROR);
			}
			else log_in_file($db2->error, DIE_IF_ERROR);
		}
		else
		{
			if ($db2->select(array('id', 'name', 'description'), TBL_CATEGORY, '`id` = ' . $cat_id))
			{
				$temp = $db2->res_row();
				if ($temp) $add_query = '';
				else log_in_file('Unable to get the category', DIE_IF_ERROR);
			}
			else log_in_file($db2->error, DIE_IF_ERROR);
		}
		$array_data = array(); // инициируем массив

		if ($db2->select('COUNT(*) AS `num_photo`', TBL_PHOTO, '`category` = ' . $temp['id'] . $add_query))
		{
			$temp_photo = $db2->res_row();
			if ($temp_photo)
			{
				if ($db2->select(array('id', 'name', 'description'), TBL_PHOTO, '`category` = ' . $temp['id'] . $add_query, array('date_upload' => 'down'), false, 1)) $temp_last = $db2->res_row();
				else log_in_file($db2->error, DIE_IF_ERROR);
				if ($db2->select(array('id', 'name', 'description'), TBL_PHOTO, '`category` = ' . $temp['id'] . $add_query . ' AND `rate_user` != 0', array('rate_user' => 'down'), false, 1)) $temp_top = $db2->res_row();
				else log_in_file($db2->error, DIE_IF_ERROR);
				$photo['count'] = $temp_photo['num_photo'];
			}
			else
			{
				$temp_photo = false;
				$temp_last = false;
				$temp_top = false;
				$photo['count'] = 0;
			}
		}
		else log_in_file($db2->error, DIE_IF_ERROR);

		$photo['last_name'] = $lang['main_no_foto'];
		$photo['last_url'] = $this->config['site_url'] . '?action=photo&id=0';
		$photo['top_name'] = $lang['main_no_foto'];
		$photo['top_url'] = $this->config['site_url'] . '?action=photo&id=0';
		if ($user->user['pic_view'] == true)
		{
			if($temp_last)
			{
				$photo['last_name'] = $temp_last['name'] . ' (' . $temp_last['description'] . ')';
				$photo['last_url'] = $this->config['site_url'] . '?action=photo&id=' . $temp_last['id'];
			}
			if($temp_top)
			{
				$photo['top_name'] = $temp_top['name'] . ' (' . $temp_top['description'] . ')';
				$photo['top_url'] = $this->config['site_url'] . '?action=photo&id=' . $temp_top['id'];
			}
		}

		if($cat_id == 0)
		{
			if ($db2->select('COUNT(DISTINCT `user_upload`) AS `num_user_upload`', TBL_PHOTO, '`category` = 0'))
			{
				$temp_user = $db2->res_row();
				$temp['id'] = 'user';
				if ($temp_user) $temp['name'] .= ' (' . $lang['category_count_user_category'] . ': ' . $temp_user['num_user_upload'] . ')';
				else $temp['name'] .= '<br />(' . $lang['category_no_user_category'] . ')';
			}
			else log_in_file($db2->error, DIE_IF_ERROR);
		}

		if($user_flag == 1) $temp['id'] = 'user&id=' . $cat_id;

		$array_data = array(
					'D_NAME_CATEGORY' => $temp['name'],
					'D_DESCRIPTION_CATEGORY' => $temp['description'],
					'D_COUNT_PHOTO' => $photo['count'],
					'D_LAST_PHOTO' => $photo['last_name'],
					'D_TOP_PHOTO' => $photo['top_name'],

					'U_CATEGORY' => $this->config['site_url'] . '?action=category&cat=' . $temp['id'],
					'U_LAST_PHOTO' => $photo['last_url'],
					'U_TOP_PHOTO' => $photo['top_url']
		);

		return $template->create_template('category_dir.tpl', $array_data);
	}

	/// Функция удаляет изображение с полученным идентификатором, а так же все упоминания об этом изображении в таблицах сайта, удаляет файл в каталогах как полноразмерных изображений, так и в каталогах эскизов
	/**
	* @param $photo_id содержит идентификатор удаляемого изображения (обязательное поле).
	* @return True если удалось удалить, иначе False.
	* @see ::$db
	*/
	function del_photo($photo_id)
	{
		global $db2;

		if (mb_ereg('^[0-9]+$', $photo_id))
		{
			if ($db2->select('*', TBL_PHOTO, '`id` = ' . $photo_id))
			{
				$temp_photo = $db2->res_row();
				if ($temp_photo)
				{
					if ($db2->select('*', TBL_CATEGORY, '`id` = ' . $temp_photo['category']))
					{
						$temp_category = $db2->res_row();
						if ($temp_category)
						{
							$path_thumbnail = $this->config['site_dir'] . $this->config['thumbnail_folder'] . '/' . $temp_category['folder'] . '/' . $temp_photo['file'];
							$path_photo = $this->config['site_dir'] . $this->config['gallery_folder'] . '/' . $temp_category['folder'] . '/' . $temp_photo['file'];
							if ($db2->delete(TBL_PHOTO, '`id` = ' . $photo_id))
							{
								if ($db2->aff_rows == 1)
								{
									@unlink($path_thumbnail);
									@unlink($path_photo);
									if (!$db2->delete(TBL_RATE_USER, '`id_foto` = ' . $photo_id)) log_in_file($db2->error, DIE_IF_ERROR);
									if (!$db2->delete(TBL_RATE_MODER, '`id_foto` = ' . $photo_id)) log_in_file($db2->error, DIE_IF_ERROR);
									return true;
								}
							}
							else log_in_file($db2->error, DIE_IF_ERROR);
						}
					}
					else log_in_file($db2->error, DIE_IF_ERROR);
				}
			}
			else log_in_file($db2->error, DIE_IF_ERROR);
		}
		return false;
	}

	/// Функция преобразует полученное значение в байты - используется для преобразования значений типа 2M(егабайта) в размер в байтах
	/**
	* @param $val текстовое значение размера, например 2M (обязательное поле).
	* @return Полученное значение в байтах.
	*/
	function return_bytes($val)
	{
		$val = trim($val);
		$last = strtolower($val[strlen($val)-1]);
		switch($last)
		{
			case 'g':
				$val *= 1024;
			case 'm':
				$val *= 1024;
			case 'k':
				$val *= 1024;
		}
		return $val;
	}

	/// Функция преобразует полученную строку в транслит (в случае использования русских букв) и заменяет все использованный знаки пунктуации - символом "_" (подчеркивания)
	/**
	* @param $string строка для перекодировки (обязательное поле).
	* @return Перекодированная строка.
	*/
	function encodename($string)
	{
		$table = array(
				'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G',
				'Д' => 'D', 'Е' => 'E', 'Ё' => 'YO', 'Ж' => 'ZH',
				'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K',
				'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
				'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
				'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
				'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'CSH', 'Ь' => '',
				'Ы' => 'Y', 'Ъ' => '', 'Э' => 'E', 'Ю' => 'YU',
				'Я' => 'YA', 'а' => 'a', 'б' => 'b', 'в' => 'v',
				'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
				'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'j',
				'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
				'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's',
				'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h',
				'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'csh',
				'ь' => '', 'ы' => 'y', 'ъ' => '', 'э' => 'e',
				'ю' => 'yu', 'я' => 'ya'
		);

		$string = str_replace(array_keys($table), array_values($table), $string);

		$string=strtr($string,'"', '_');
		$string=strtr($string,"-!#$%&'()*+,./:;<=>?@[\]`{|}~", "_____________________________");

		return $string;
	}
}
?>
