<?php
/*****************************************************************************
**	File:	include/common.php												**
**	Diplom:	Gallery															**
**	Date:	13/01-2009														**
**	Ver.:	0.1																**
**	Autor:	Gold Rigma														**
**	E-mail:	nvn62@mail.ru													**
**	Decr.:	Класс с общими функциями										**
*****************************************************************************/

// Проверка, что файл подключается из индексного, а не набран напрямую в адресной строке
if (IN_DIPLOM)
{
	die('HACK!');
}

class Work
{
	// Класс с общими функциями, принимаемые и отдаваемые переменные зависят от конкретной функции

	// При создании класса автоматически стартует сессия (функция Work())

	// Функции:
	// config($config) - формирует массив настроек из файла config.php и БД. На входе: значения, полученные из файла, на выходе - полностью заполненный массив конфигураций.
	// category($cat_id, $user_flag) - формирует информационную строку по конктретному разделу, идентификатор которого приходит в переменной $cat_id, если $user_flag = 0, то формируется по обычному списку разделов, если $user_flag = 1 - формирование идет по списку пользовательских альбомов и значением $cat_id приходит идентификатор пользователя
	// del_photo($photo_id) - удаляет изображение с полученным идентификатором, а так же все упоминания об этом изображении в таблицах сайта, удаляет файл в каталогах как полноразмерных изображений, так и в каталогах эскизов
	// return_bytes($val) - преобразует полученное значение в байты - используется для преобразования значений типа 2M(егабайта) в размер в байтах
	// encodename($string) - преобразует полученную строку в транслит (в случае использования русских букв) и заменяет все использованный знаки пунктуации - символом "_" (подчеркивания)

	var $conf = array(); // Резервируем и очищаем массив для хранения настроек

	function Work()
	{
		session_start(); // стартуем сессию
	}

	function config($config)
	{
		global $db; // Используем глобальный объект для работы с БД

		$this->conf = $config; // Внесем настроечные параметры, полученные на входе в текущий массив
		$result = $db->query("SELECT * FROM `config`"); // Запрос настроек из БД
		if ($result) // Проверка, что получен результат запроса к БД
		{
			while($res = mysql_fetch_array($result)) //До тех пор, пока еще есть строки результата - обрабатываем их
			{
				$this->conf[$res['name']] = $res['value']; // Дополняем настройки из БД в текущий массив
			}
		}
		else
		{
			die('Невозможно получить настройки'); // Если не получили настройки - выдаем сообщение и останавливаем скрипт
		}
		return $this->conf; // Передаем сформированный массив настроек
	}

	function category($cat_id = 0, $user_flag = 0)
	{
		global $db, $lang, $user, $template; // Используем глобальные объекты и массивы: объект для работы с базой данных ($db), массив языковых переменных ($lang), объект текущего пользователя на сайте ($user), объект для формирования и обработки шаблонов ($template)

		if($user_flag == 1) // если требуется вывести пользовательский альбом
		{
			$temp = $db->fetch_array("SELECT `id`, `name`, `description` FROM `category` WHERE `id` = 0"); // получаем наименование и описание для пользовательских альбомов (идентификатор всегда равен 0)
			$add_query = ' AND `user_upload` = ' . $cat_id; // формируем окончание для запросов к таблице хранения изображений - указатель на идентификатор пользователя, залившего изображения  свой альбом
			$temp2 = $db->fetch_array("SELECT `real_name` FROM `user` WHERE `id` = " . $cat_id); // получаем из пользовательской таблицы отображаемое имя пользователя - владельца альбома
			$temp['description'] = $temp['name'] . ' ' . $temp2['real_name']; // формируем описание альбома = описание пользовательских альбомов + отображаемое имя
			$temp['name'] = $temp2['real_name']; // формируем название альбома = отображаемое имя
		}
		else // иначе если требуется получить обычный раздел
		{
			$temp = $db->fetch_array("SELECT `id`, `name`, `description` FROM `category` WHERE `id` = " . $cat_id); // получаем название и описание раздела из БД
			$add_query = ''; // окончание запроса будет пустым
		}
		$array_data = array(); // инициируем массив

		$temp_photo = $db->num_rows("SELECT `id` FROM `photo` WHERE `category` = " . $temp['id'] . $add_query); // получаем количество изображений в разделе
		if($temp_photo) // если получен результат, то...
		{
			$temp_last = $db->fetch_array("SELECT `id` , `name` , `description` FROM `photo` WHERE `category` = " . $temp['id'] . $add_query . " ORDER BY `date_upload` DESC LIMIT 1"); // получаем последнее залитое изображение в раздел
			$temp_top = $db->fetch_array("SELECT `id` , `name` , `description` FROM `photo` WHERE `category` = " . $temp['id'] . $add_query . " AND `rate_user` != 0 ORDER BY `rate_user` DESC LIMIT 1"); // получаем изображение, получившее максимальную оценку
			$photo['count'] = $temp_photo; // сохраняем кол-во изображений в разделе
		}
		else // если нет данных
		{
			$temp_last = false; // последнее изображение - не существует
			$temp_top = false; // изображение с максимальной оценкой - не существует
			$photo['count'] = 0; // кол-во изображений в разделе равно 0
		}

		if($temp_last && $user->user['pic_view'] == true) // если есть последнее залитое изображение и пользователь имеет право просмотра изображений, то...
		{
			$photo['last_name'] = $temp_last['name'] . ' (' . $temp_last['description'] . ')'; // формируем название последнего изображения в виде: Название (Описание)
			$photo['last_url'] = $config['site_url'] . '?action=photo&id=' . $temp_last['id']; // формируем ссылку для просмотра данного изображения
		}
		else // иначе
		{
			$photo['last_name'] = $lang['main_no_foto']; // выдаем сообщение, что нет изображения
			$photo['last_url'] = $config['site_url'] . '?action=photo&id=0'; // ссылка будет указывать на вывод несуществующего изображения
		}

		if($temp_top && $user->user['pic_view'] == true) // если есть изображение с максимальной оценкой и пользователь имеет право просмотра изображений, то...
		{
			$photo['top_name'] = $temp_top['name'] . ' (' . $temp_top['description'] . ')'; // формируем название изображения с мксимальной оценкой в виде: Название (Описание)
			$photo['top_url'] = $config['site_url'] . '?action=photo&id=' . $temp_top['id']; // формируем ссылку для просмотра данного изображения
		}
		else // иначе
		{
			$photo['top_name'] = $lang['main_no_foto']; // выдаем сообщение, что нет изображения
			$photo['top_url'] = $config['site_url'] . '?action=photo&id=0'; // ссылка будет указывать на вывод несуществующего изображения
		}

		if($cat_id == 0) // если получен идентификатор на общий список пользовательских альбомов (идентификатор равен 0)
		{
			$temp_user = $db->num_rows("SELECT DISTINCT `user_upload` FROM `photo` WHERE `category` = 0"); // получаем кол-во пользовательских альбомов в базе данных
			$temp['id'] = 'user'; // заменяем идентификатор на указатель пользовательского альбома
			if($temp_user) // если есть пользовательские альбомы, то...
			{
				$temp['name'] .= ' (' . $lang['category_count_user_category'] . ': ' . $temp_user . ')'; // добавляем их кол-во  выводимое название
			}
			else // иначе
			{
				$temp['name'] .= '<br />(' . $lang['category_no_user_category'] . ')'; // указываем, что пользовательских альбомов на сайте нет
			}
		}

		if($user_flag == 1) // если идет обработка пользовательских разделов
		{
			$temp['id'] = 'user&id=' . $cat_id; // заменяем идентификатор на указатель пользовательских разделов с указанием идентификатора пользователя - владельца альбома
		}

		$array_data = array(
					'D_NAME_CATEGORY' => $temp['name'],
					'D_DESCRIPTION_CATEGORY' => $temp['description'],
					'D_COUNT_PHOTO' => $photo['count'],
					'D_LAST_PHOTO' => $photo['last_name'],
					'D_TOP_PHOTO' => $photo['top_name'],

					'U_CATEGORY' => $config['site_url'] . '?action=category&cat=' . $temp['id'],
					'U_LAST_PHOTO' => $photo['last_url'],
					'U_TOP_PHOTO' => $photo['top_url']
		); // наполняем массив данными для замены по шаблону

		return $template->create_template('category_dir.tpl', $array_data); // возвращаем сформированныый фрагмент списка разделов (пользовательских альбомов)
	}

	function del_photo($photo_id)
	{
		global $db; // Используем глобальный объект для работы с БД

		if (!mb_ereg('^[0-9]+$', $photo_id)) // если идентификатор изображения не является числом
		{
			return false; // передаем ответ о невозможности удалить изображение
		}
		else // иначе
		{
			$temp_foto = $db->fetch_array("SELECT * FROM `photo` WHERE `id` = " . $photo_id); // получаем данные об удаляемом изображении
			if ($temp_foto) // если есть данные об изображении в базе, то...
			{
	    		$temp_category = $db->fetch_array("SELECT * FROM `category` WHERE `id` = " .  $temp_foto['category']); // получаем данные о разделе, где хранится данное изображение
				if($temp_category) // если данные о разделе существуют, то...
				{
					$path_thumbnail = $this->conf['site_dir'] . $this->conf['thumbnail_folder'] . '/' . $temp_category['folder'] . '/' . $temp_foto['file']; // формируем полный путь к эскизу изображения
					$path_photo = $this->conf['site_dir'] . $this->conf['gallery_folder'] . '/' . $temp_category['folder'] . '/' . $temp_foto['file']; // и формируем полный путь к файлу изображения
					if($db->query("DELETE FROM `photo` WHERE `id` = " . $photo_id)) // если удалось удалить запись о файле из базы данных, то...
					{
						@unlink($path_thumbnail); // удаляем файл эскиза
						@unlink($path_photo); // и удаляем файл самого изображения
						$db->query("DELETE FROM `rate_user` WHERE `id_foto` = " . $photo_id); // удаляем проставленные оценки этого файла из базы пользовательских оценок
						$db->query("DELETE FROM `rate_moder` WHERE `id_foto` = " . $photo_id); // удаляем проставленные оценки этого файла из базы преподавательских оценок
						return true; // возвращаем данные, что файл успешно удален
					}
					else // иначе
					{
						return false; // передаем ответ о невозможности удалить изображение
					}
	    		}
	    		else // иначе
	    		{
	    			return false; // передаем ответ о невозможности удалить изображение
	    		}
	    	}
	    	else // иначе
	    	{
				return false; // передаем ответ о невозможности удалить изображение
	    	}
    	}
	}

	function return_bytes($val)
	{
		$val = trim($val); // удаляем пробельные символы в начале и конце строки
		$last = strtolower($val[strlen($val)-1]); // получаем последний символ строки и переводим его в нижний регистр
		switch($last) // используем ступени обработки
		{
			case 'g': // если данные были в гигабайтах
				$val *= 1024; // умножим значение на 1024 - перевод в мегабайты
			case 'm': // если в мегабайтах
				$val *= 1024; // умножим значение на 1024 - перевод в килобайты
			case 'k': // если в кидобайтах
				$val *= 1024; // умножим значение на 1024 - перевод в байты
		}
		return $val; // вернем полученное значение в байтах
	}

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
				'ю' => 'yu', 'я' => 'ya',
		); // формируем таблицу замены русских букв их транслит-аналогами

		$string = str_replace(array_keys($table), array_values($table), $string); // произведем замену русских букв в строке на траснлит

		// Заменим все возможные знаки пунктуации на "_", предварительно исключив оттуда символ """
		$string=strtr($string,'"', '_');
		$string=strtr($string,"-!#$%&'()*+,./:;<=>?@[\]`{|}~", "_____________________________");

		return $string; // возвращаем преобразованную строку
	}

	function url_check()
	{
		$array_rules = array('http_', '_server', 'delete%20', 'delete ', 'delete-', 'delete(', '(delete',  'drop%20', 'drop ', 'create%20', 'update-', 'update(', '(update', 'insert-', 'insert(', '(insert', 'create ', 'create(', 'create-', '(create', 'update%20', 'update ', 'insert%20', 'insert ', 'select%20', 'select ', 'bulk%20', 'bulk ', 'union%20', 'union ', 'select-', 'select(', '(select', 'union-', '(union', 'union(', 'or%20', 'or ', 'and%20', 'and ', 'exec', '@@', '%22', '"', 'openquery', 'openrowset', 'msdasql', 'sqloledb', 'sysobjects', 'syscolums',  'syslogins', 'sysxlogins', 'char%20', 'char ', 'into%20', 'into ', 'load%20', 'load ', 'msys', 'alert%20', 'alert ', 'eval%20', 'eval ', 'onkeyup', 'x5cx', 'fromcharcode', 'javascript:', 'javascript.', 'vbscript:', 'vbscript.', 'http-equiv', '->', 'expression%20', 'expression ', 'url%20', 'url ', 'innerhtml', 'document.', 'dynsrc', 'jsessionid', 'style%20', 'style ', 'phpsessid', '<applet', '<div', '<emded', '<iframe', '<img', '<meta', '<object', '<script', '<textarea', 'onabort', 'onblur', 'onchange', 'onclick', 'ondblclick', 'ondragdrop', 'onerror',  'onfocus', 'onkeydown', 'onkeypress', 'onload', 'onmouse', 'onmove', 'onreset', 'onresize', 'onselect', 'onsubmit', 'onunload', 'onreadystatechange', 'xmlhttp', 'uname%20', 'uname ',  '%2C', 'union+', 'select+', 'delete+', 'create+', 'bulk+', 'or+', 'and+', 'into+', 'kill+', '+echr', '+chr', 'cmd+', '+1', 'user_password', 'id%20', 'id ', 'ls%20', 'ls ', 'cat%20', 'cat ', 'rm%20', 'rm ', 'kill%20', 'kill ', 'mail%20', 'mail ', 'wget%20', 'wget ', 'wget(', 'pwd%20', 'pwd ', 'objectclass', 'objectcategory', '<!-%20', '<!- ', 'total%20', 'total ', 'http%20request', 'http request', 'phpb8b4f2a0', 'phpinfo', 'php:', 'globals', '%2527', '%27', '\'', 'chr(', 'chr=', 'chr%20', 'chr ', '%20chr', ' chr', 'cmd=', 'cmd%20', 'cmd', '%20cmd', ' cmd', 'rush=', '%20rush', ' rush', 'rush%20', 'rush ', 'union%20', 'union ', '%20union', ' union', 'union(', 'union=', '%20echr', ' echr', 'esystem', 'cp%20', 'cp ', 'cp(', '%20cp', ' cp', 'mdir%20', 'mdir ', '%20mdir', ' mdir', 'mdir(', 'mcd%20', 'mcd ', 'mrd%20', 'mrd ', 'rm%20', 'rm ', '%20mcd', ' mcd', '%20mrd', ' mrd', '%20rm', ' rm', 'mcd(', 'mrd(', 'rm(', 'mcd=', 'mrd=', 'mv%20', 'mv ', 'rmdir%20', 'rmdir ', 'mv(', 'rmdir(', 'chmod(', 'chmod%20', 'chmod ', 'cc%20', 'cc ', '%20chmod', ' chmod', 'chmod(', 'chmod=', 'chown%20', 'chown ', 'chgrp%20', 'chgrp ', 'chown(', 'chgrp(', 'locate%20', 'locate ', 'grep%20', 'grep ', 'locate(', 'grep(', 'diff%20', 'diff ', 'kill%20', 'kill ', 'kill(', 'killall', 'passwd%20', 'passwd ', '%20passwd', ' passwd', 'passwd(', 'telnet%20', 'telnet ', 'vi(', 'vi%20', 'vi ', 'nigga(', '%20nigga', ' nigga', 'nigga%20', 'nigga ', 'fopen', 'fwrite', '%20like', ' like', 'like%20', 'like ', '$_', '$get', '.system', 'http_php', '%20getenv', ' getenv', 'getenv%20', 'getenv ', 'new_password', '/password', 'etc/', '/groups', '/gshadow', 'http_user_agent', 'http_host', 'bin/', 'wget%20', 'wget ', 'uname%5c', 'uname', 'usr', '/chgrp', '=chown', 'usr/bin', 'g%5c', 'g\\', 'bin/python', 'bin/tclsh', 'bin/nasm', 'perl%20', 'perl ', '.pl', 'traceroute%20', 'traceroute ', 'tracert%20', 'tracert ', 'ping%20', 'ping ', '/usr/x11r6/bin/xterm', 'lsof%20', 'lsof ', '/mail', '.conf', 'motd%20', 'motd ', 'http/1.', '.inc.php', 'config.php', 'cgi-', '.eml', 'file%5c://', 'file\:', 'file://', 'window.open', 'img src', 'img%20src', 'img src', '.jsp', 'ftp.', 'xp_enumdsn', 'xp_availablemedia', 'xp_filelist', 'nc.exe', '.htpasswd', 'servlet', '/etc/passwd', '/etc/shadow', 'wwwacl', '~root', '~ftp', '.js', '.jsp', '.history', 'bash_history', '~nobody', 'server-info', 'server-status', '%20reboot', ' reboot', '%20halt', ' halt', '%20powerdown', ' powerdown', '/home/ftp', '=reboot', 'www/', 'init%20', 'init ','=halt', '=powerdown', 'ereg(', 'secure_site', 'chunked', 'org.apache', '/servlet/con', '/robot', 'mod_gzip_status', '.inc', '.system', 'getenv', 'http_', '_php', 'php_', 'phpinfo()', '<?php', '?>', '%3C%3Fphp', '%3F>', 'sql=', '_global', 'global_', 'global[', '_server', 'server_', 'server[', '/modules', 'modules/', 'phpadmin', 'root_path', '_globals', 'globals_', 'globals[', 'iso-8859-1', '?hl=', '%3fhl=', '.exe', '.sh', '%00', rawurldecode('%00'), '_env', '/*', '\\*');

		$query_string = strtolower($_SERVER['QUERY_STRING']);

		$hack = false;

		foreach($array_rules as $rules)
		{
			if (@mb_ereg(quotemeta($rules), $query_string))
			{
				$_SERVER['QUERY_STRING'] = '';
				$hack = true;
			}
		}
		return $hack;
	}
}

$work = new Work(); // инициируем общий класс и стартуем сессию

$config = $work->config($config); // формируем массив настроек

?>