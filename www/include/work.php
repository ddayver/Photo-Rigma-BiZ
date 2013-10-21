<?php
/**
* @file		include/work.php
* @brief	Общий класс (набор функций)
* @author	Dark Dayver
* @version	0.2.0
* @date		28/03-2012
* @details	Содержит общий класс (набор функций) + хранилище для данных о конфигурации.
*/
/// @cond
if (IN_GALLERY !== true)
{
	die('HACK!');
}
/// @endcond

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
	* @see ::$config, db
	*/
	function work()
	{
		global $db, $config;
		unset($config['dbpass']);
		$this->config = $config;

		$this->array_rules = array('http_', '_server', 'delete%20', 'delete ', 'delete-', 'delete(', '(delete',  'drop%20', 'drop ', 'create%20', 'update-', 'update(', '(update', 'insert-', 'insert(', '(insert', 'create ', 'create(', 'create-', '(create', 'update%20', 'update ', 'insert%20', 'insert ', 'select%20', 'select ', 'bulk%20', 'bulk ', 'union%20', 'union ', 'select-', 'select(', '(select', 'union-', '(union', 'union(', 'or%20', 'or ', 'and%20', 'and ', 'exec', '@@', '%22', '"', 'openquery', 'openrowset', 'msdasql', 'sqloledb', 'sysobjects', 'syscolums',  'syslogins', 'sysxlogins', 'char%20', 'char ', 'into%20', 'into ', 'load%20', 'load ', 'msys', 'alert%20', 'alert ', 'eval%20', 'eval ', 'onkeyup', 'x5cx', 'fromcharcode', 'javascript:', 'javascript.', 'vbscript:', 'vbscript.', 'http-equiv', '->', 'expression%20', 'expression ', 'url%20', 'url ', 'innerhtml', 'document.', 'dynsrc', 'jsessionid', 'style%20', 'style ', 'phpsessid', '<applet', '<div', '<emded', '<iframe', '<img', '<meta', '<object', '<script', '<textarea', 'onabort', 'onblur', 'onchange', 'onclick', 'ondblclick', 'ondragdrop', 'onerror',  'onfocus', 'onkeydown', 'onkeypress', 'onload', 'onmouse', 'onmove', 'onreset', 'onresize', 'onselect', 'onsubmit', 'onunload', 'onreadystatechange', 'xmlhttp', 'uname%20', 'uname ',  '%2C', 'union+', 'select+', 'delete+', 'create+', 'bulk+', 'or+', 'and+', 'into+', 'kill+', '+echr', '+chr', 'cmd+', '+1', 'user_password', 'id%20', 'id ', 'ls%20', 'ls ', 'cat%20', 'cat ', 'rm%20', 'rm ', 'kill%20', 'kill ', 'mail%20', 'mail ', 'wget%20', 'wget ', 'wget(', 'pwd%20', 'pwd ', 'objectclass', 'objectcategory', '<!-%20', '<!- ', 'total%20', 'total ', 'http%20request', 'http request', 'phpb8b4f2a0', 'phpinfo', 'php:', 'globals', '%2527', '%27', '\'', 'chr(', 'chr=', 'chr%20', 'chr ', '%20chr', ' chr', 'cmd=', 'cmd%20', 'cmd', '%20cmd', ' cmd', 'rush=', '%20rush', ' rush', 'rush%20', 'rush ', 'union%20', 'union ', '%20union', ' union', 'union(', 'union=', '%20echr', ' echr', 'esystem', 'cp%20', 'cp ', 'cp(', '%20cp', ' cp', 'mdir%20', 'mdir ', '%20mdir', ' mdir', 'mdir(', 'mcd%20', 'mcd ', 'mrd%20', 'mrd ', 'rm%20', 'rm ', '%20mcd', ' mcd', '%20mrd', ' mrd', '%20rm', ' rm', 'mcd(', 'mrd(', 'rm(', 'mcd=', 'mrd=', 'mv%20', 'mv ', 'rmdir%20', 'rmdir ', 'mv(', 'rmdir(', 'chmod(', 'chmod%20', 'chmod ', 'cc%20', 'cc ', '%20chmod', ' chmod', 'chmod(', 'chmod=', 'chown%20', 'chown ', 'chgrp%20', 'chgrp ', 'chown(', 'chgrp(', 'locate%20', 'locate ', 'grep%20', 'grep ', 'locate(', 'grep(', 'diff%20', 'diff ', 'kill%20', 'kill ', 'kill(', 'killall', 'passwd%20', 'passwd ', '%20passwd', ' passwd', 'passwd(', 'telnet%20', 'telnet ', 'vi(', 'vi%20', 'vi ', 'nigga(', '%20nigga', ' nigga', 'nigga%20', 'nigga ', 'fopen', 'fwrite', '%20like', ' like', 'like%20', 'like ', '$_', '$get', '.system', 'http_php', '%20getenv', ' getenv', 'getenv%20', 'getenv ', 'new_password', '/password', 'etc/', '/groups', '/gshadow', 'http_user_agent', 'http_host', 'bin/', 'wget%20', 'wget ', 'uname%5c', 'uname', 'usr', '/chgrp', '=chown', 'usr/bin', 'g%5c', 'g\\', 'bin/python', 'bin/tclsh', 'bin/nasm', 'perl%20', 'perl ', '.pl', 'traceroute%20', 'traceroute ', 'tracert%20', 'tracert ', 'ping%20', 'ping ', '/usr/x11r6/bin/xterm', 'lsof%20', 'lsof ', '/mail', '.conf', 'motd%20', 'motd ', 'http/1.', '.inc.php', 'config.php', 'cgi-', '.eml', 'file%5c://', 'file\:', 'file://', 'window.open', 'img src', 'img%20src', 'img src', '.jsp', 'ftp.', 'xp_enumdsn', 'xp_availablemedia', 'xp_filelist', 'nc.exe', '.htpasswd', 'servlet', '/etc/passwd', '/etc/shadow', 'wwwacl', '~root', '~ftp', '.js', '.jsp', '.history', 'bash_history', '~nobody', 'server-info', 'server-status', '%20reboot', ' reboot', '%20halt', ' halt', '%20powerdown', ' powerdown', '/home/ftp', '=reboot', 'www/', 'init%20', 'init ','=halt', '=powerdown', 'ereg(', 'secure_site', 'chunked', 'org.apache', '/servlet/con', '/robot', 'mod_gzip_status', '.inc', '.system', 'getenv', 'http_', '_php', 'php_', 'phpinfo()', '<?php', '?>', '%3C%3Fphp', '%3F>', 'sql=', '_global', 'global_', 'global[', '_server', 'server_', 'server[', '/modules', 'modules/', 'phpadmin', 'root_path', '_globals', 'globals_', 'globals[', 'iso-8859-1', '?hl=', '%3fhl=', '.exe', '.sh', '%00', rawurldecode('%00'), '_env', '/*', '\\*');

		if ($db->select('*', TBL_CONFIG))
		{
			$result = $db->res_arr();
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
		else log_in_file($db->error, DIE_IF_ERROR);
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
	* @see db, $lang, user
	*/
	function category($cat_id = 0, $user_flag = 0)
	{
		global $db, $lang, $user, $template;

		$photo = array();

		if ($user_flag == 1)
		{
			if ($db->select(array('id', 'name'), TBL_CATEGORY, '`id` = 0'))
			{
				$temp = $db->res_row();
				if ($temp)
				{
					if ($db->select('real_name', TBL_USERS, '`id` = ' . $cat_id))
					{
						$temp2 = $db->res_row();
						if ($temp2)
						{
							$add_query = ' AND `user_upload` = ' . $cat_id;
							$temp['description'] = $temp['name'] . ' ' . $temp2['real_name'];
							$temp['name'] = $temp2['real_name'];
						}
						else log_in_file('Unable to get the user', DIE_IF_ERROR);
					}
					else log_in_file($db->error, DIE_IF_ERROR);
				}
				else log_in_file('Unable to get the category', DIE_IF_ERROR);
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}
		else
		{
			if ($db->select(array('id', 'name', 'description'), TBL_CATEGORY, '`id` = ' . $cat_id))
			{
				$temp = $db->res_row();
				if ($temp) $add_query = '';
				else log_in_file('Unable to get the category', DIE_IF_ERROR);
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}

		if ($db->select('COUNT(*) AS `num_photo`', TBL_PHOTO, '`category` = ' . $temp['id'] . $add_query))
		{
			$temp_photo = $db->res_row();
			if ($temp_photo)
			{
				if ($db->select(array('id', 'name', 'description'), TBL_PHOTO, '`category` = ' . $temp['id'] . $add_query, array('date_upload' => 'down'), false, 1)) $temp_last = $db->res_row();
				else log_in_file($db->error, DIE_IF_ERROR);
				if ($db->select(array('id', 'name', 'description'), TBL_PHOTO, '`category` = ' . $temp['id'] . $add_query . ' AND `rate_user` != 0', array('rate_user' => 'down'), false, 1)) $temp_top = $db->res_row();
				else log_in_file($db->error, DIE_IF_ERROR);
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
		else log_in_file($db->error, DIE_IF_ERROR);

		$photo['last_name'] = $lang['main']['no_foto'];
		$photo['last_url'] = $this->config['site_url'] . '?action=photo&amp;id=0';
		$photo['top_name'] = $lang['main']['no_foto'];
		$photo['top_url'] = $this->config['site_url'] . '?action=photo&amp;id=0';
		if ($user->user['pic_view'] == true)
		{
			if ($temp_last)
			{
				$photo['last_name'] = $temp_last['name'] . ' (' . $temp_last['description'] . ')';
				$photo['last_url'] = $this->config['site_url'] . '?action=photo&amp;id=' . $temp_last['id'];
			}
			if ($temp_top)
			{
				$photo['top_name'] = $temp_top['name'] . ' (' . $temp_top['description'] . ')';
				$photo['top_url'] = $this->config['site_url'] . '?action=photo&amp;id=' . $temp_top['id'];
			}
		}

		if ($cat_id == 0)
		{
			if ($db->select('COUNT(DISTINCT `user_upload`) AS `num_user_upload`', TBL_PHOTO, '`category` = 0'))
			{
				$temp_user = $db->res_row();
				$temp['id'] = 'user';
				if ($temp_user) $temp['name'] .= ' (' . $lang['category']['count_user_category'] . ': ' . $temp_user['num_user_upload'] . ')';
				else $temp['name'] .= '<br />(' . $lang['category']['no_user_category'] . ')';
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}

		if ($user_flag == 1) $temp['id'] = 'user&amp;id=' . $cat_id;
		
		$category = array(
			'name' => $temp['name'],
			'description' => $temp['description'],
			'count_photo' => $photo['count'],
			'last_photo' => $photo['last_name'],
			'top_photo' => $photo['top_name'],
			'url_cat' => $this->config['site_url'] . '?action=category&amp;cat=' . $temp['id'],
			'url_last_photo' => $photo['last_url'],
			'url_top_photo' => $photo['top_url']
		);
		return $category;
	}

	/// Функция удаляет изображение с полученным идентификатором, а так же все упоминания об этом изображении в таблицах сайта, удаляет файл в каталогах как полноразмерных изображений, так и в каталогах эскизов
	/**
	* @param $photo_id содержит идентификатор удаляемого изображения (обязательное поле).
	* @return True если удалось удалить, иначе False.
	* @see db
	*/
	function del_photo($photo_id)
	{
		global $db;

		if (mb_ereg('^[0-9]+$', $photo_id))
		{
			if ($db->select('*', TBL_PHOTO, '`id` = ' . $photo_id))
			{
				$temp_photo = $db->res_row();
				if ($temp_photo)
				{
					if ($db->select('*', TBL_CATEGORY, '`id` = ' . $temp_photo['category']))
					{
						$temp_category = $db->res_row();
						if ($temp_category)
						{
							$path_thumbnail = $this->config['site_dir'] . $this->config['thumbnail_folder'] . '/' . $temp_category['folder'] . '/' . $temp_photo['file'];
							$path_photo = $this->config['site_dir'] . $this->config['gallery_folder'] . '/' . $temp_category['folder'] . '/' . $temp_photo['file'];
							if ($db->delete(TBL_PHOTO, '`id` = ' . $photo_id))
							{
								if ($db->aff_rows == 1)
								{
									@unlink($path_thumbnail);
									@unlink($path_photo);
									if (!$db->delete(TBL_RATE_USER, '`id_foto` = ' . $photo_id)) log_in_file($db->error, DIE_IF_ERROR);
									if (!$db->delete(TBL_RATE_MODER, '`id_foto` = ' . $photo_id)) log_in_file($db->error, DIE_IF_ERROR);
									return true;
								}
							}
							else log_in_file($db->error, DIE_IF_ERROR);
						}
					}
					else log_in_file($db->error, DIE_IF_ERROR);
				}
			}
			else log_in_file($db->error, DIE_IF_ERROR);
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

	/// Функция формирует вывод новостей сайта
	/**
	* @param $news_data сожержит идентификатор новости (если $act = 'id') или количество выводимых новостей (если $act = 'last')
	* @param $act если $act = 'last', то выводим последнии новости сайта, иначе если $act = 'id', то выводим новость с указанным идентификатором
	* @return Подготовленный блок новостей
	* @see db
	*/
	function news($news_data = 1, $act='last')
	{
		global $db;

		if ($act == 'id')
		{
			if ($db->select('*', TBL_NEWS, '`id` = ' . $news_data)) $temp_news = $db->res_arr();
			else log_in_file($db->error, DIE_IF_ERROR);
		}
		else
		{
			if ($db->select('*', TBL_NEWS, false, array('data_last_edit' => 'down'), false, $news_data)) $temp_news = $db->res_arr();
			else log_in_file($db->error, DIE_IF_ERROR);
		}
		return $temp_news;
	}

	/// Функция генерирует меню
	/**
	* @param $action содержит пункт меню, который является активным
	* @param $menu если равно 0 - создает горизонтальное краткое меню, если 1- вертикальное боковое меню
	* @return Сформированный массив меню
	* @see db, $lang, user, work::clean_field
	*/
	function create_menu($action = 'main', $menu = 0)
	{
		global $db, $lang, $user;

		$m[0] = 'short';
		$m[1] = 'long';
		$array_menu = array();

		if ($db->select('*', TBL_MENU, '`' . $m[$menu] . '` = 1', array('id' => 'up')))
		{
			$temp_menu = $db->res_arr();
			if ($temp_menu)
			{
				foreach ($temp_menu as $key => $val)
				{
					$visible = true;

					if ($val['user_login'] != '')
					{
						if ($val['user_login'] == 0 && $user->user['id'] > 0) $visible = false;
						if ($val['user_login'] == 1 && $user->user['id'] == 0) $visible = false;
					}
					if ($val['user_access'] != '') if ($user->user[$val['user_access']] != 1) $visible = false;

					if ($visible)
					{
						$array_menu[$key] = array(
								'url' => ($val['action'] == $action ? NULL : $this->config['site_url'] . $this->clean_field($val['url_action'])),
								'name' => (isset($lang['menu'][$this->clean_field($val['name_action'])]) ? $lang['menu'][$this->clean_field($val['name_action'])] : ucfirst($this->clean_field($val['name_action'])))
						);
					}
				}
			}
			else log_in_file('Unable to get the ' . $m[$menu] . ' menu', DIE_IF_ERROR);
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		return $array_menu;
	}

	/// Функция генерирует блок вывода последнего, лучшего, случайного или указанного изображения
	/**
	* @param $type если значение равно 'top' - вывести лучшее фото по оценкам пользователя, если 'last' - последнее добавленое фото, если 'cat' - вывести фото, указанное в $id_photo, если не равно пустому - вывести случайное изображение
	* @param $id_photo если $type равно 'cat' - выводит фото с указанным идентификатором
	* @return Сформированный массив для вывода изображения
	* @see db, $lang, user, work::size_image, work::clean_field
	*/
	function create_photo($type = 'top', $id_photo = 0)
	{
		global $db, $lang, $user;

		if ($user->user['pic_view'] == true)
		{
			$where = false;
			$order = false;
			$limit = false;
			if ($type == 'top')
			{
				$where = '`rate_user` != 0';
				$order = array('rate_user' => 'down');
				$limit = 1;
			}
			elseif ($type == 'last')
			{
				$order = array('date_upload' => 'down');
				$limit = 1;
			}
			elseif ($type == 'cat') $where = '`id` = ' . $id_photo;
			else
			{
				$order = 'rand()';
				$limit = 1;
			}
			if ($db->select('*', TBL_PHOTO, $where, $order, false, $limit)) $temp_photo = $db->res_row();
			else log_in_file($db->error, DIE_IF_ERROR);
		}
		else
		{
			$temp_photo = false;
		}

		$photo['name_block'] = $lang['main'][$type . '_foto'];

		if ($temp_photo)
		{
			if ($db->select('*', TBL_CATEGORY, '`id` = ' . $temp_photo['category']))
			{
				$temp_category = $db->res_row();
				if ($temp_category)
				{
					$temp_path = $this->config['site_dir'] . $this->config['gallery_folder'] . '/' . $temp_category['folder'] . '/' . $temp_photo['file'];
					$photo['url'] = $this->config['site_url'] . '?action=photo&amp;id=' . $temp_photo['id'];
					$photo['thumbnail_url'] = $this->config['site_url'] . '?action=attach&amp;foto=' . $temp_photo['id'] . '&amp;thumbnail=1';
					$photo['name'] = $this->clean_field($temp_photo['name']);
					$photo['category_name'] = $this->clean_field($temp_category['name']);
					$photo['description'] = $this->clean_field($temp_photo['description']);
					$photo['category_description'] = $this->clean_field($temp_category['description']);
					$photo['rate'] = $lang['main']['rate'] . ': ' . $temp_photo['rate_user'] . '/' . $temp_photo['rate_moder'];

					if ($db->select('real_name', TBL_USERS, '`id` = ' . $temp_photo['user_upload']))
					{
						$user_add = $db->res_row();
						if ($user_add)
						{
							$photo['url_user'] = $this->config['site_url']  . '?action=profile&amp;subact=profile&amp;uid=' . $temp_photo['user_upload'];
							$photo['real_name'] = $this->clean_field($user_add['real_name']);
						}
						else
						{
							$photo['url_user'] = NULL;
							$photo['real_name'] = $lang['main']['no_user_add'];
						}
					}
					else log_in_file($db->error, DIE_IF_ERROR);
					if ($temp_category['id'] == 0)
					{
						$photo['category_name'] = $this->clean_field($temp_category['name'] . ' ' . $user_add['real_name']);
						$photo['category_description'] = $this->clean_field($photo['category_name']);
						$photo['category_url'] = $this->config['site_url'] . '?action=category&amp;cat=user&amp;id=' . $temp_photo['user_upload'];
					}
					else $photo['category_url'] = $this->config['site_url'] . '?action=category&amp;cat=' . $temp_category['id'];
				}
				else
				{
					$temp_photo['file'] = 'no_foto.png';
					$temp_path = $this->config['site_dir'] . $this->config['gallery_folder'] . '/' . $temp_photo['file'];
					$photo['url'] = $this->config['site_url'] . '?action=photo&amp;id=0';
					$photo['thumbnail_url'] = $this->config['site_url'] . '?action=attach&amp;foto=0&amp;thumbnail=1';
					$photo['name'] = $lang['main']['no_foto'];
					$photo['description'] = $lang['main']['no_foto'];
					$photo['category_name'] = $lang['main']['no_category'];
					$photo['category_description'] = $lang['main']['no_category'];
					$photo['rate'] = $lang['main']['rate'] . ': ' . $lang['main']['no_foto'];
					$photo['url_user'] = NULL;
					$photo['real_name'] = $lang['main']['no_user_add'];
					$photo['category_url'] = $this->config['site_url'];
				}
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}
		else
		{
			$temp_photo['file'] = 'no_foto.png';
			$temp_path = $this->config['site_dir'] . $this->config['gallery_folder'] . '/' . $temp_photo['file'];
			$photo['url'] = $this->config['site_url'] . '?action=photo&amp;id=0';
			$photo['thumbnail_url'] = $this->config['site_url'] . '?action=attach&amp;foto=0&amp;thumbnail=1';
			$photo['name'] = $lang['main']['no_foto'];
			$photo['description'] = $lang['main']['no_foto'];
			$photo['category_name'] = $lang['main']['no_category'];
			$photo['category_description'] = $lang['main']['no_category'];
			$photo['rate'] = $lang['main']['rate'] . ': ' . $lang['main']['no_foto'];
			$photo['url_user'] = NULL;
			$photo['real_name'] = $lang['main']['no_user_add'];
			$photo['category_url'] = $this->config['site_url'];
		}

		if (!@fopen($temp_path, 'r'))
		{
			$temp_photo['file'] = 'no_foto.png';
			$temp_path = $this->config['site_dir'] . $this->config['gallery_folder'] . '/' . $temp_photo['file'];
			$photo['url'] = $this->config['site_url'] . '?action=photo&amp;id=0';
			$photo['thumbnail_url'] = $this->config['site_url'] . '?action=attach&amp;foto=0&amp;thumbnail=1';
			$photo['name'] = $lang['main']['no_foto'];
			$photo['description'] = $lang['main']['no_foto'];
			$photo['category_name'] = $lang['main']['no_category'];
			$photo['category_description'] = $lang['main']['no_category'];
			$photo['rate'] = $lang['main']['rate'] . ': ' . $lang['main']['no_foto'];
			$photo['url_user'] = NULL;
			$photo['real_name'] = $lang['main']['no_user_add'];
			$photo['category_url'] = $this->config['site_url'];
		}

		$size = $this->size_image($temp_path);
		$photo['width'] = $size['width'];
		$photo['height'] = $size['height'];
		return $photo;
	}

	/// Функция вычисляет необходимый размер для вывода эскиза изображения
	/**
	* @param $path_image содержит путь к файлу изображения
	* @return Массив с шириной и высотой изображения для вывода
	* @see db, $lang, user, work::create_photo
	*/
	function size_image($path_image)
	{
		$size = getimagesize($path_image);
		if ($this->config['temp_photo_w'] == '0') $ratio_width = 1;
		else $ratio_width = $size[0]/$this->config['temp_photo_w'];
		if ($this->config['temp_photo_h'] == '0') $ratio_height = 1;
		else $ratio_height = $size[1]/$this->config['temp_photo_h'];

		if ($size[0] < $this->config['temp_photo_w'] && $size[1] < $this->config['temp_photo_h'] && $this->config['temp_photo_w'] != '0' && $this->config['temp_photo_h'] != '0')
		{
			$size_photo['width'] = $size[0];
			$size_photo['height'] = $size[1];
		}
		else
		{
			if ($ratio_width < $ratio_height)
			{
				$size_photo['width'] = $size[0]/$ratio_height;
				$size_photo['height'] = $size[1]/$ratio_height;
			}
			else
			{
				$size_photo['width'] = $size[0]/$ratio_width;
				$size_photo['height'] = $size[1]/$ratio_width;
			}
		}
		return $size_photo;
	}

	/// Функция формирует блок для входа пользователя (если в режиме "Гость") или краткий вид информации о пользователе
	/**
	* @return Сформированный массив блока пользователя
	* @see user, $lang, work::clean_field
	*/
	function template_user()
	{
		global $lang, $user;

		$array_data = array();

		if ($_SESSION['login_id'] == 0)
		{
			$array_data = array(
					'NAME_BLOCK' => $lang['main']['user_block'],
					'L_LOGIN' => $lang['main']['login'],
					'L_PASSWORD' => $lang['main']['pass'],
					'L_ENTER' => $lang['main']['enter'],
					'L_FORGOT_PASSWORD' => $lang['main']['forgot_password'],
					'L_REGISTRATION' => $lang['main']['registration'],
					'U_LOGIN' => $this->config['site_url'] . '?action=profile&amp;subact=login',
					'U_FORGOT_PASSWORD' => $this->config['site_url'] . '?action=profile&amp;subact=forgot',
					'U_REGISTRATION' => $this->config['site_url'] . '?action=profile&amp;subact=regist'
			);
			return $array_data;
		}
		else
		{
			$array_data = array(
					'NAME_BLOCK' => $lang['main']['user_block'],
					'L_HI_USER' => $lang['main']['hi_user'] . ', ' . $this->clean_field($user->user['real_name']),
					'L_GROUP' => $lang['main']['group'] . ': ' . $user->user['group'],
					'U_AVATAR' => $this->config['site_url'] . $this->config['avatar_folder'] . '/' . $user->user['avatar']
			);
			return $array_data;
		}
	}

	/// Функция формирует блок статистики для сайта
	/**
	* @return Сформированный массив блока статистики
	* @see db, $lang, work::clean_field
	*/
	function template_stat()
	{
		global $db, $lang;

		if ($db->select('COUNT(*) AS `regist_user`', TBL_USERS))
		{
			$temp = $db->res_row();
			if ($temp) $stat['regist'] = $temp['regist_user'];
			else $stat['regist'] = 0;
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		if ($db->select('COUNT(*) AS `photo_count`', TBL_PHOTO))
		{
			$temp = $db->res_row();
			if ($temp) $stat['photo'] = $temp['photo_count'];
			else $stat['photo'] = 0;
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		if ($db->select('COUNT(*) AS `category`', TBL_CATEGORY, '`id` != 0'))
		{
			$temp = $db->res_row();
			if ($temp) $stat['category'] = $temp['category'];
			else $stat['category'] = 0;
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		if ($db->select('COUNT(DISTINCT `user_upload`) AS `category_user`', TBL_PHOTO, '`category` = 0'))
		{
			$temp = $db->res_row();
			if ($temp) $stat['category_user'] = $temp['category_user'];
			else $stat['category_user'] = 0;
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		$stat['category'] = $stat['category'] + $stat['category_user'];

		if ($db->select('COUNT(*) AS `user_admin`', TBL_USERS, '`group` = 3'))
		{
			$temp = $db->res_row();
			if ($temp) $stat['user_admin'] = $temp['user_admin'];
			else $stat['user_admin'] = 0;
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		if ($db->select('COUNT(*) AS `user_moder`', TBL_USERS, '`group` = 2'))
		{
			$temp = $db->res_row();
			if ($temp) $stat['user_moder'] = $temp['user_moder'];
			else $stat['user_moder'] = 0;
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		if ($db->select('COUNT(*) AS `rate_user`', TBL_RATE_USER))
		{
			$temp = $db->res_row();
			if ($temp) $stat['rate_user'] = $temp['rate_user'];
			else $stat['rate_user'] = 0;
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		if ($db->select('COUNT(*) AS `rate_moder`', TBL_RATE_MODER))
		{
			$temp = $db->res_row();
			if ($temp) $stat['rate_moder'] = $temp['rate_moder'];
			else $stat['rate_moder'] = 0;
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		if ($db->select(array('id', 'real_name'), TBL_USERS, '`date_last_activ` >= (CURRENT_TIMESTAMP - 900 )'))
		{
			$temp = $db->res_arr();
			if ($temp)
			{
				$stat['online'] ='';
				foreach ($temp as $val)
				{
					$stat['online'] .= ', <a href="' . $this->config['site_url']  . '?action=profile&amp;subact=profile&amp;uid=' . $val['id'] . '" title="' . $this->clean_field($val['real_name']) . '">' . $this->clean_field($val['real_name']) . '</a>';
				}
				$stat['online'] = substr($stat['online'], 2) . '.';
			}
			else $stat['online'] = $lang['main']['stat_no_online'];
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		$array_data = array();

		$array_data = array(
				'NAME_BLOCK' => $lang['main']['stat_title'],
				'L_STAT_REGIST' => $lang['main']['stat_regist'],
				'L_STAT_PHOTO' => $lang['main']['stat_photo'],
				'L_STAT_CATEGORY' => $lang['main']['stat_category'],
				'L_STAT_USER_ADMIN' => $lang['main']['stat_user_admin'],
				'L_STAT_USER_MODER' => $lang['main']['stat_user_moder'],
				'L_STAT_RATE_USER' => $lang['main']['stat_rate_user'],
				'L_STAT_RATE_MODER' => $lang['main']['stat_rate_moder'],
				'L_STAT_ONLINE' => $lang['main']['stat_online'],

				'D_STAT_REGIST' => $stat['regist'],
				'D_STAT_PHOTO' => $stat['photo'],
				'D_STAT_CATEGORY' => $stat['category'] . '(' . $stat['category_user'] . ')',
				'D_STAT_USER_ADMIN' => $stat['user_admin'],
				'D_STAT_USER_MODER' => $stat['user_moder'],
				'D_STAT_RATE_USER' => $stat['rate_user'],
				'D_STAT_RATE_MODER' => $stat['rate_moder'],
				'D_STAT_ONLINE' => $stat['online']
		);

		return $array_data;
	}

	/// Функция формирует список из пользователей, заливших максимальное кол-во изображений
	/**
	* @param $best_user сожержит указатель, сколько выводить лучших пользователей
	* @return Сформированный массив блока лучших пользователей
	* @see db, $lang, work::clean_field
	*/
	function template_best_user($best_user = 1)
	{
		global $db, $lang;

		$array_data = array();
		if ($db->select('DISTINCT `user_upload`', TBL_PHOTO))
		{
			$temp = $db->res_arr();
			if ($temp)
			{
				$best_user_array = array();
				foreach ($temp as $val)
				{
					if ($db->select('real_name', TBL_USERS, '`id` = ' . $val['user_upload']))
					{
						$temp2 = $db->res_row();
						if ($temp2)
						{
							if ($db->select('COUNT(*) AS `user_photo`', TBL_PHOTO, '`user_upload` = ' . $val['user_upload']))
							{
								$temp2 = $db->res_row();
								if ($temp2) $val['user_photo'] = $temp2['user_photo'];
								else $val['user_photo'] = 0;
							}
							else log_in_file($db->error, DIE_IF_ERROR);
						}
						else $val['user_photo'] = 0;
					}
					else log_in_file($db->error, DIE_IF_ERROR);
					$best_user_array[$val['user_upload']] = $val['user_photo'];
				}
				arsort($best_user_array);
				$idx = 1;
				foreach ($best_user_array as $best_user_name => $best_user_photo)
				{
					if ($db->select('real_name', TBL_USERS, '`id` = ' . $best_user_name))
					{
						$temp2 = $db->res_row();
						$array_data[$idx] = array(
								'user_url' => $this->config['site_url']  . '?action=profile&amp;subact=profile&amp;uid=' . $best_user_name,
								'user_name' => $this->clean_field($temp2['real_name']),
								'user_photo' => $best_user_photo
						);
						$idx++;
					}
					else log_in_file($db->error, DIE_IF_ERROR);
				}
			}
			else
			{
				$array_data[1] = array(
					'user_url' => NULL,
					'user_name' => '---',
					'user_photo' => '-'
				);
			}
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		$array_data[0] = array(
				'NAME_BLOCK' => sprintf($lang['main']['best_user'], $best_user),
				'L_USER_NAME' => $lang['main']['user_name'],
				'L_USER_PHOTO' => $lang['main']['best_user_photo'],
		);

		return $array_data;
	}

	/// Функция преобразует BBCode в HTML-код (например в тексте новостей)
	/**
	* @param $text сожержит текст, в котором необходимо произвести парсинг BBCode
	* @return текст, где BBCode заменены на соотвествующие HTML-теги
	* @see work::clean_field
	*/
	function ubb($text)
	{
		$text = $this->clean_field($text);
		$text = preg_replace('#\[b\](.*?)\[/b\]#si', '<strong>\\1</strong>', $text);
		$text = preg_replace('#\[u\](.*?)\[/u\]#si', '<u>\\1</u>', $text);
		$text = preg_replace('#\[i\](.*?)\[/i\]#si', '<em>\\1</em>', $text);
		$text = preg_replace('#\[url\](.*?)\[/url\]#si', '<a href="\\1" target="_blank" title="\\1">\\1</a>', $text);
		$text = preg_replace('#\[url=(.*?)\](.*?)\[/url\]#si', '<a href="\\1" target="_blank" title="\\2">\\2</a>', $text);
		$text = preg_replace('#\[color=(.*?)\](.*?)\[/color\]#si', '<font color="\\1">\\2</font>', $text);
		$text = str_replace('[hr]', '<hr />', $text);
		$text = str_replace('[br]', '<br />', $text);
		$text = preg_replace('#\[left\](.*?)\[/left\]#si', '<p align="left">\\1</p>', $text);
		$text = preg_replace('#\[center\](.*?)\[/center\]#si', '<p align="center">\\1</p>', $text);
		$text = preg_replace('#\[right\](.*?)\[/right\]#si', '<p align=right>\\1</p>', $text);
		$text = preg_replace('#\[img\](.*?)\[/img\]#si', '<img src="\\1" alt="\\1" />', $text);
		return $text;
	}

	/// Функция выводит массив данных для несуществующего фото
	/**
	* @return массив, содержащий наименование файла, полный путь к изображению и полный путь к эскизу
	*/
	function no_photo()
	{
		$temp['file'] = 'no_foto.png';
		$temp['full_path'] = $this->config['site_dir'] . $this->config['gallery_folder'] . '/' . $temp['file'];
		$temp['thumbnail_path'] = $this->config['site_dir'] . $this->config['thumbnail_folder'] . '/' . $temp['file'];
		return $temp;
	}
	
	/// Функция преобразует изображение в эскиз, при этом проводится проверка - если эскиз уже существует и его размеры соотвествуют нстройкам, указанным в конфигурации сайта, то просто возвращает уведомление о том, что изображение преобразовано - не выполняя никаких операций
	/**
	* @param $full_path системный путь к изображению
	* @param $thumbnail_path системный путь к эскизу
	* @return True если удалось создать эскиз, иначе False
	*/
	function image_resize($full_path, $thumbnail_path)
	{
		$thumbnail_size = @getimagesize($thumbnail_path);
		$full_size = getimagesize($full_path);
		$photo['type'] = $full_size[2];

		if ($this->config['temp_photo_w'] == '0') $ratio_width = 1;
		else $ratio_width = $full_size[0]/$this->config['temp_photo_w'];
		if ($this->config['temp_photo_h'] == '0') $ratio_height = 1;
		else $ratio_height = $full_size[1]/$this->config['temp_photo_h'];

		if ($full_size[0] < $this->config['temp_photo_w'] && $full_size[1] < $this->config['temp_photo_h'] && $this->config['temp_photo_w'] != '0' && $this->config['temp_photo_h'] != '0')
		{
			$photo['width'] = $full_size[0];
			$photo['height'] = $full_size[1];
		}
		else
		{
			if ($ratio_width < $ratio_height)
			{
				$photo['width'] = (int)$full_size[0]/$ratio_height;
				$photo['height'] = (int)$full_size[1]/$ratio_height;
			}
			else
			{
				$photo['width'] = (int)$full_size[0]/$ratio_width;
				$photo['height'] = (int)$full_size[1]/$ratio_width;
			}
		}

		if ($thumbnail_size[0] != $photo['width'] || $thumbnail_size[1] != $photo['height'])
		{
			switch($photo['type'])
			{
				case "1":
					$imorig = imagecreatefromgif ($full_path);
					break;
				case "2":
					$imorig = imagecreatefromjpeg($full_path);
					break;
				case "3":
					$imorig = imagecreatefrompng($full_path);
					break;
				default:
					$imorig = imagecreatefromjpeg($full_path);
			}
			$im = imagecreatetruecolor($photo['width'], $photo['height']);
			if (imagecopyresampled($im, $imorig , 0, 0, 0, 0, $photo['width'], $photo['height'], $full_size[0], $full_size[1]))
			{
				@unlink($thumbnail_path);

				switch($photo['type'])
				{
					case "1":
						imagegif ($im, $thumbnail_path);
						break;
					case "2":
						imagejpeg($im, $thumbnail_path);
						break;
					case "3":
						imagepng($im, $thumbnail_path);
						break;
					default:
						imagejpeg($im, $thumbnail_path);
				}
				return true;
			}
			return false;
		}
		else return true;
	}

	/// Функция выводит изображение, скрывая путь к нему
	/**
	* @param $full_path системный путь к изображению
	* @param $name_file имя файла
	* @return Изображение
	*/
	function image_attach($full_path, $name_file)
	{
		$size = getimagesize($full_path);

		header("Content-Type: " . $size['mime']);
		header("Content-Disposition: inline; filename=\"" . $name_file . "\"");
		header("Content-Length: " . (string)(filesize($full_path)));

		flush();

		$fh = fopen($full_path, 'rb');
		fpassthru($fh);
		fclose($fh);
		exit();
	}
}
?>
