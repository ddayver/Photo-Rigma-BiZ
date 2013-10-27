<?php
/**
* @file		include/template.php
* @brief	Работа с шаблонами
* @author	Dark Dayver
* @version	0.3.0
* @date		15/07-2012
* @details	Содержит класс работы с шаблонами на сервере
*/
/// @cond
if (IN_GALLERY !== true)
{
	die('HACK!');
}
/// @endcond

/// Новый класс работы с шаблонами.
/**
* Данный класс содержит функции по работе с шаблонами (наполнение данными, обработка). Внедряется взамен старого.
* @see template_old
*/
class template
{
	var $ins_header = ''; ///< Данные, вставляемые в заголовок
	var $ins_body = ''; ///< Данные, вставляемые в тег body
	var $content; ///< Содержимое для вывода
	var $mod_rewrite = false; ///< Включение читаемых URL
	var $template_file = 'main.html'; ///< Файл шаблона
	private $block_string = array(); ///< Блок строковых данных для замены
	private $block_if = array(); ///< Блок условий для обработки
	private $block_case = array(); ///< Блок массивов выбора блока для обработки
	var $block_object = array(); ///< Блок массивов объектов для обработки
	private $themes_path; ///< Путь к корню темы
	private $themes_url; ///< Ссылка на корень темы
	private $site_url; ///< Ссылка корня сайта
	private $site_dir; ///< Путь к корню сайта
	private $theme; ///< Тема пользователя

	/// Конструктор класса, формирует массив данных об используемой теме
	/**
	* @param $site_url содержит ссылку на корень сайта
	* @param $site_dir содержит путь к корню сайта
	* @param $theme содержит название используемой темы
	* @see $themes_path, $themes_url
	*/
	function template($site_url, $site_dir, $theme)
	{
		$this->site_url = $site_url;
		$this->site_dir = $site_dir;
		$this->theme = $theme;
		$this->themes_path = $this->site_dir . 'themes/' . $this->theme . '/';
		$this->themes_url = $this->site_url . 'themes/' . $this->theme . '/';
	}

	/// Поиск файла-шаблона
	/**
	* Находит файл-шаблон и помещает его путь в $template_file.
	* @see $template_file, $themes_path
	*/
	private function find_template_file()
	{
		if (file_exists($this->themes_path . $this->template_file))
		{
			$this->template_file = $this->themes_path . $this->template_file;
			return true;
		}
		else
		{
			log_in_file('Not found template file: ' . $this->themes_path . $this->template_file, DIE_IF_ERROR);
			return false;
		}
	}

	/// Создание и обработка шаблона
	/**
	* Создает шаблон из файла, указанного в $template_file, обрабатывает его согласно заполненных данных и размещает обработанный результат в $content
	* @see $template_file, $themes_path, $themes_url, $content, pars_template
	*/
	function create_template()
	{
		$this->find_template_file();
		$this->content = file_get_contents($this->template_file);
		if (!$this->content) log_in_file('Error template file: ' . $this->template_file, DIE_IF_ERROR);

		$this->pars_template();

		$this->content = str_replace('{SITE_URL}', $this->site_url, $this->content);
		$this->content = str_replace('{THEME_URL}', $this->themes_url, $this->content);

		if (get_magic_quotes_gpc()) $this->content = stripslashes($this->content);
	}

	/// Добавление строковой данной для замены в шаблоне
	/**
	* Добавляет строковую переменную для замены по шаблону как в основе шаблона, так и в рекурсивных массивах
	* @param $name содержит название пемеренной
	* @param $value содержит значение переменной
	* @param $path_array содержит путь, по которому рекурсивно необходимо разместить переменную в виде: Массив1[0]->Массив1.0[0] (по-умолчанию False)
	* @see $block_string, $block_object, test_is_object
	*/
	function add_string($name, $value, $path_array = false)
	{
		if ($path_array == false)
		{
			$this->block_string[strtoupper($name)] = $value;
		}
		else
		{
			$temp_result = $this->test_is_object($path_array);
			$this->block_object[$temp_result['current']][$temp_result['index']]->add_string($name, $value, $temp_result['next_path']);
		}
	}

	/// Добавление массива строковых данных для замены в шаблоне
	/**
	* Добавляет массив строковых переменных для замены по шаблону как в основе шаблона, так и в рекурсивных массивах
	* @param $array_data содержит массив строковых данных в стиле: 'название_переменной' => 'значение'
	* @param $path_array содержит путь, по которому рекурсивно необходимо разместить переменную в виде: Массив1[0]->Массив1.0[0] (по-умолчанию False)
	* @see add_string
	*/
	function add_string_ar($array_data, $path_array = false)
	{
		if (is_array($array_data))
		{
			foreach ($array_data as $key=>$value)
			{
				$this->add_string($key, $value, $path_array);
			}
		}
	}

	/// Добавление данных об условиях вывода фрагментов шаблона
	/**
	* Добавляет данные об условиях вывода фрагментов шаблона как в основе шаблона, так и в рекурсивных массивах
	* @param $name содержит название пемеренной условия
	* @param $value содержит значение переменной (True или False)
	* @param $path_array содержит путь, по которому рекурсивно необходимо разместить переменную в виде: Массив1[0]->Массив1.0[0] (по-умолчанию False)
	* @see $block_if, $block_object, test_is_object
	*/
	function add_if ($name, $value, $path_array = false)
	{
		if ($path_array == false)
		{
			if ($value != false) $value = true;
			else $value = false;
			$this->block_if['IF_' . strtoupper($name)] = $value;
		}
		else
		{
			$temp_result = $this->test_is_object($path_array);
			$this->block_object[$temp_result['current']][$temp_result['index']]->add_if ($name, $value, $temp_result['next_path']);
		}
	}

	/// Добавление массива данных об условиях вывода фрагментов шаблона
	/**
	* Добавляет массив данных об условиях вывода фрагментов шаблона как в основе шаблона, так и в рекурсивных массивах
	* @param $array_data содержит массив данных об условиях вывода фрагментов шаблона в стиле: 'название_условия' => 'значение'
	* @param $path_array содержит путь, по которому рекурсивно необходимо разместить переменную в виде: Массив1[0]->Массив1.0[0] (по-умолчанию False)
	* @see add_if
	*/
	function add_if_ar($array_data, $path_array = false)
	{
		if (is_array($array_data))
		{
			foreach ($array_data as $key=>$value)
			{
				$this->add_if ($key, $value, $path_array);
			}
		}
	}

	/// Добавление данных о выборе блока для вывода фрагментов шаблона
	/**
	* Добавляет данные о выборе блока для вывода фрагментов шаблона как в основе шаблона, так и в рекурсивных массивах
	* @param $name содержит название пемеренной условия
	* @param $value содержит значение переменной (наименование или номер блока)
	* @param $path_array содержит путь, по которому рекурсивно необходимо разместить переменную в виде: Массив1[0]->Массив1.0[0] (по-умолчанию False)
	* @see $block_case, $block_object, test_is_object
	*/
	function add_case($name, $value, $path_array = false)
	{
		if ($path_array == false)
		{
			$this->block_case['SELECT_' . strtoupper($name)] = strtoupper($value);
		}
		else
		{
			$temp_result = $this->test_is_object($path_array);
			$this->block_object[$temp_result['current']][$temp_result['index']]->add_case($name, $value, $temp_result['next_path']);
		}
	}

	/// Добавление массива данных о выборе блока для вывода фрагментов шаблона
	/**
	* Добавляет массив данных о выборе блока для вывода фрагментов шаблона как в основе шаблона, так и в рекурсивных массивах
	* @param $array_data содержит массив данных о выборе блока для вывода фрагментов шаблона в стиле: 'название_условия' => 'значение'
	* @param $path_array содержит путь, по которому рекурсивно необходимо разместить переменную в виде: Массив1[0]->Массив1.0[0] (по-умолчанию False)
	* @see add_if
	*/
	function add_case_ar($array_data, $path_array = false)
	{
		if (is_array($array_data))
		{
			foreach ($array_data as $key=>$value)
			{
				$this->add_case($key, $value, $path_array);
			}
		}
	}

	/// Создание рекурсивного блока массивов-объектов
	/**
	* Создание рекурсивного блока массивов-объектов с предварительной проверкой их существования и извлечением структуры из полученного аргумента
	* @param $path_array содержит путь, по которому рекурсивно необходимо создать объект-массив в виде: Массив1[0]->Массив1.0[0]
	* @return полученный элемент (имя объекта), порядковый номер в массиве, остаток пути (если путь полностью разобран, то возвращает вместо него False)
	*/
	private function test_is_object($path_array)
	{
		$tmp_path = explode('->', $path_array);
		$tmp_p = $tmp_path[0];
		$tmp_p = strtoupper($tmp_p);
		$tmp = explode('[', $tmp_p);
		if (mb_ereg('^[A-Z_]+$', $tmp[0]) && isset($tmp[1]) && !empty($tmp[1]))
		{
			$tmp[1] = str_replace(']', '', $tmp[1]);
			if (!mb_ereg('^[0-9]+$', $tmp[1])) log_in_file('Error in Path OBJ::' . $path_array, DIE_IF_ERROR);
		}
		else
		{
			log_in_file('Error in Path OBJ::' . $path_array, DIE_IF_ERROR);
		}
		if (!isset($this->block_object[$tmp[0]][$tmp[1]]) || !is_object($this->block_object[$tmp[0]][$tmp[1]]))
		{
			$temp = new template($this->site_url, $this->site_dir, $this->theme);
			$this->block_object[$tmp[0]][$tmp[1]] = $temp;
		}
		$result['current'] = $tmp[0];
		$result['index'] = $tmp[1];
		if (count($tmp_path) > 1)
		{
			unset($tmp_path[0]);
			$result['next_path'] = implode('->', $tmp_path);
		}
		else
		{
			$result['next_path'] = false;
		}
		return $result;
	}

	/// Функция обработки шаблона
	/**
	* Обрабатывает шаблон, наполняя его данными. Последовательно делает "прогонку" блока объектных элементов для рекурсивного наполнения, обработка условий вывода шаблонов, замена строковых переменных
	* @see $block_object, $block_if, $block_string, $content
	*/
	function pars_template()
	{
		foreach ($this->block_object as $key=>$val) $this->template_object($key, $val);
		foreach ($this->block_if as $key=>$val) $this->template_if ($key, $val);
		foreach ($this->block_case as $key=>$val) $this->template_case($key, $val);
		foreach ($this->block_string as $key=>$val) $this->content = str_replace('{' . $key . '}', $val, $this->content);
		$this->content = $this->url_mod_rewrite($this->content);
		$this->content = str_replace(chr(13) . chr(10), '{BR}', $this->content);
		$this->content = str_replace(chr(13), '{BR}', $this->content);
		$this->content = str_replace(chr(10), '{BR}', $this->content);
		while (strpos($this->content, '{BR}{BR}')) $this->content = str_replace('{BR}{BR}', '{BR}', $this->content);
		$this->content = str_replace('{BR}', PHP_EOL, $this->content);
	}

	/// Обработка рекурсивного блока массивов-объектов
	/**
	* Рекурсивная обработка блока массивов-объектов с их постобработкой и заменой в шаблоне. В шаблоне данные эелемнты заключены между <!-- ARRAY_НАЗВАНИЕ_BEGIN --> и <!-- ARRAY_НАЗВАНИЕ_END -->
	* @param $key ключ-название фрагмента заменемого блока
	* @param $index индекс-блок элементов дл рекурсивной замены
	* @see $content, pars_template
	*/
	private function template_object($key, $index)
	{
		while (strpos($this->content, '<!-- ARRAY_' . $key . '_BEGIN -->') !== false)
		{
			$begin_start = strpos($this->content, '<!-- ARRAY_' . $key . '_BEGIN -->');
			$begin_end = $begin_start + strlen('<!-- ARRAY_' . $key . '_BEGIN -->');
			$end_start = strpos($this->content, '<!-- ARRAY_' . $key . '_END -->', $begin_end);
			$end_end = $end_start + strlen('<!-- ARRAY_' . $key . '_END -->');
			if ($end_start !== false)
			{
				$block_content = '';
				$temp_content = substr($this->content, $begin_end, $end_start - $begin_end);
				foreach($index as $id=>$value)
				{
					$value->content = $temp_content;
					$value->pars_template();
					$block_content .= $value->content;
				}
				$tmp = substr($this->content, $begin_start, $end_end - $begin_start);
				$this->content = str_replace($tmp, $block_content, $this->content);
			}
			else log_in_file('Error template OBJ::' . $key, DIE_IF_ERROR);
		}
	}

	/// Обработка блока условий вывода фрагментов шаблона
	/**
	* Обработка блока условий вывода фрагментов шаблона. В шаблоне данные эелемнты заключены между <!-- IF_НАЗВАНИЕ_BEGIN --> и <!-- IF_НАЗВАНИЕ_END --> (между началом и концом как разделитель вывода можно расположить <!-- IF_НАЗВАНИЕ_ELSE -->)
	* @param $key ключ-название условия
	* @param $val значение ключа
	* @see $content
	*/
	private function template_if ($key, $val)
	{
		while (strpos($this->content, '<!-- ' . $key . '_BEGIN -->') !== false)
		{
			$begin_start = strpos($this->content, '<!-- ' . $key . '_BEGIN -->');
			$begin_end = $begin_start + strlen('<!-- ' . $key . '_BEGIN -->');
			$else_start = strpos($this->content, '<!-- ' . $key . '_ELSE -->', $begin_end);
			$else_end = $else_start + strlen('<!-- ' . $key . '_ELSE -->');
			$end_start = strpos($this->content, '<!-- ' . $key . '_END -->', $begin_end);
			$end_end = $end_start + strlen('<!-- ' . $key . '_END -->');

			if ($else_start !== false && $end_start !== false && $else_start < $end_start)
			{
				$temp_content = substr($this->content, $begin_start, $end_end - $begin_start);
				if ($val) $tmp = substr($this->content, $begin_end, $else_start - $begin_end);
				else $tmp = substr($this->content, $else_end, $end_start - $else_end);
				$this->content = str_replace($temp_content, $tmp, $this->content);
			}
			elseif ($end_start !== false)
			{
				$temp_content = substr($this->content, $begin_start, $end_end - $begin_start);
				if ($val) $tmp = substr($this->content, $begin_end, $end_start - $begin_end);
				else $tmp = '';
				$this->content = str_replace($temp_content, $tmp, $this->content);
			}
			else log_in_file('Error template IF: ' . $key, DIE_IF_ERROR);
		}
	}

	/// Обработка блока выбора фрагмента для вывода в шаблон
	/**
	* Обработка блока выбора фрагмента для вывода в шаблон. В шаблоне данные эелемнты заключены между <!-- SELECT_НАЗВАНИЕ_BEGIN --> и <!-- SELECT_НАЗВАНИЕ_END -->, необходимый блок заключается между <!-- CASE_ЗНАЧЕНИЕ --> и <!-- BREAK_ЗНАЧЕНИЕ -->
	* @param $key ключ-название условия
	* @param $val значение ключа
	* @see $content
	*/
	private function template_case($key, $val)
	{
		while (strpos($this->content, '<!-- ' . $key . '_BEGIN -->') !== false)
		{
			$begin_start = strpos($this->content, '<!-- ' . $key . '_BEGIN -->');
			$begin_end = $begin_start + strlen('<!-- ' . $key . '_BEGIN -->');
			$end_start = strpos($this->content, '<!-- ' . $key . '_END -->', $begin_end);
			$end_end = $end_start + strlen('<!-- ' . $key . '_END -->');

			if ($end_start !== false)
			{
				$temp_content = substr($this->content, $begin_start, $end_end - $begin_start);
				$tmp = '';
				$case_start = strpos($this->content, '<!-- CASE_' . $val . ' -->', $begin_end);
				$case_end = $case_start + strlen('<!-- CASE_' . $val . ' -->');
				$break_start = strpos($this->content, '<!-- BREAK_' . $val . ' -->', $case_end);
				$break_end = $end_start + strlen('<!-- BREAK_' . $val . ' -->');
				if ($break_start !== false && $case_start < $end_start && $break_start < $end_start) $tmp = substr($this->content, $case_end, $break_start - $case_end);
				else
				{
					$case_start = strpos($this->content, '<!-- CASE_DEFAULT -->', $begin_end);
					$case_end = $case_start + strlen('<!-- CASE_DEFAULT -->');
					$break_start = strpos($this->content, '<!-- BREAK_DEFAULT -->', $case_end);
					$break_end = $end_start + strlen('<!-- BREAK_DEFAULT -->');
					if ($break_start !== false && $case_start < $end_start && $break_start < $end_start) $tmp = substr($this->content, $case_end, $break_start - $case_end);
				}
				$this->content = str_replace($temp_content, $tmp, $this->content);
			}
			else log_in_file('Error template SELECT-CASE: ' . $key . '-' . $val, DIE_IF_ERROR);
		}
	}

	/// Формирование заголовка страницы
	/**
	* Формирует заголовок HTML-страницы с пунктами меню и возможностью вставки дополнительных полей. Использует рекурсивный вызов класса обработки шаблонов с передачей в качестве имени файла 'header.html'
	* @param $title дополнительное название страницы для тега Title
	* @see $lang, work, $ins_body, $ins_header, $content, $template_file, add_string, add_string_ar, add_if, add_case, create_template
	*/
	function page_header($title)
	{
		global $lang, $work, $action;

		$s_menu = $work->create_menu($action, 0);
		$l_menu = $work->create_menu($action, 1);
		$photo_top = $work->create_photo('top');
		$photo_last = $work->create_photo('last');
		$temp_template = new template($this->site_url, $this->site_dir, $this->theme);
		if ($this->ins_body != '') $this->ins_body = ' ' . $this->ins_body;
		$temp_template->add_string_ar(array(
			'TITLE' => (empty($title) ? $work->config['title_name'] : $work->clean_field($work->config['title_name']) . ' - ' . $work->clean_field($title)),
			'INSERT_HEADER' => $this->ins_header,
			'INSERT_BODY' => $this->ins_body,
			'META_DESRIPTION' => $work->clean_field($work->config['meta_description']),
			'META_KEYWORDS' => $work->clean_field($work->config['meta_keywords']),
			'GALLERY_WIDHT' => $work->config['gal_width'],
			'SITE_NAME' => $work->clean_field($work->config['title_name']),
			'SITE_DESCRIPTION' => $work->clean_field($work->config['title_description']),
			'U_SEARCH' => $work->config['site_url'] . '?action=search',
			'L_SEARCH' => $lang['main']['search'],
			'LEFT_PANEL_WIDHT' => $work->config['left_panel'],
			'RIGHT_PANEL_WIDHT' => $work->config['right_panel']
		));

		$temp_template->add_if ('SHORT_MENU', false);
		if ($s_menu && is_array($s_menu))
		{
			$temp_template->add_if ('SHORT_MENU', true);
			foreach($s_menu as $id => $value)
			{
				$temp_template->add_string_ar(array(
					'U_SHORT_MENU' => $value['url'],
					'L_SHORT_MENU' => $value['name']
				), 'SHORT_MENU[' . $id . ']');
				$temp_template->add_if ('SHORT_MENU_URL', (empty($value['url']) ? false : true), 'SHORT_MENU[' . $id . ']');
			}
		}

		$temp_template->add_case('LEFT_BLOCK', 'MENU', 'LEFT_PANEL[0]');
		$temp_template->add_if ('LONG_MENU', false, 'LEFT_PANEL[0]');
		if ($l_menu && is_array($l_menu))
		{
			$temp_template->add_if ('LONG_MENU', true, 'LEFT_PANEL[0]');
			$temp_template->add_string('LONG_MENU_NAME_BLOCK', $lang['menu']['name_block'], 'LEFT_PANEL[0]');
			foreach($l_menu as $id => $value)
			{
				$temp_template->add_string_ar(array(
					'U_LONG_MENU' => $value['url'],
					'L_LONG_MENU' => $value['name']
				), 'LEFT_PANEL[0]->LONG_MENU[' . $id . ']');
				$temp_template->add_if ('LONG_MENU_URL', (empty($value['url']) ? false : true), 'LEFT_PANEL[0]->LONG_MENU[' . $id . ']');
			}
		}

		$temp_template->add_case('LEFT_BLOCK', 'TOP_LAST_PHOTO', 'LEFT_PANEL[1]');
		$temp_template->add_string_ar(array(
			'NAME_BLOCK' => $photo_top['name_block'],
			'PHOTO_WIDTH' => $photo_top['width'],
			'PHOTO_HEIGHT' => $photo_top['height'],
			'MAX_FOTO_HEIGHT' => $work->config['temp_photo_h'] + 10,
			'D_NAME_PHOTO' => $photo_top['name'],
			'D_DESCRIPTION_PHOTO' => $photo_top['description'],
			'D_NAME_CATEGORY' => $photo_top['category_name'],
			'D_DESCRIPTION_CATEGORY' => $photo_top['category_description'],
			'PHOTO_RATE' => $photo_top['rate'],
			'L_USER_ADD' => $lang['main']['user_add'],
			'U_PROFILE_USER_ADD' => $photo_top['url_user'],
			'D_REAL_NAME_USER_ADD' => $photo_top['real_name'],
			'U_PHOTO' => $photo_top['url'],
			'U_THUMBNAIL_PHOTO' => $photo_top['thumbnail_url'],
			'U_CATEGORY' => $photo_top['category_url']
		), 'LEFT_PANEL[1]');
		$temp_template->add_if ('USER_EXISTS', (empty($photo_top['url_user']) ? false : true), 'LEFT_PANEL[1]');

		$temp_template->add_case('LEFT_BLOCK', 'TOP_LAST_PHOTO', 'LEFT_PANEL[2]');
		$temp_template->add_string_ar(array(
			'NAME_BLOCK' => $photo_last['name_block'],
			'PHOTO_WIDTH' => $photo_last['width'],
			'PHOTO_HEIGHT' => $photo_last['height'],
			'MAX_FOTO_HEIGHT' => $work->config['temp_photo_h'] + 10,
			'D_NAME_PHOTO' => $photo_last['name'],
			'D_DESCRIPTION_PHOTO' => $photo_last['description'],
			'D_NAME_CATEGORY' => $photo_last['category_name'],
			'D_DESCRIPTION_CATEGORY' => $photo_last['category_description'],
			'PHOTO_RATE' => $photo_last['rate'],
			'L_USER_ADD' => $lang['main']['user_add'],
			'U_PROFILE_USER_ADD' => $photo_last['url_user'],
			'D_REAL_NAME_USER_ADD' => $photo_last['real_name'],
			'U_PHOTO' => $photo_last['url'],
			'U_THUMBNAIL_PHOTO' => $photo_last['thumbnail_url'],
			'U_CATEGORY' => $photo_last['category_url']
		), 'LEFT_PANEL[2]');
		$temp_template->add_if ('USER_EXISTS', (empty($photo_last['url_user']) ? false : true), 'LEFT_PANEL[2]');

		$temp_template->template_file = 'header.html';
		$temp_template->create_template();
		$this->content = $temp_template->content . $this->content;
		unset($temp_template);
	}

	/// Формирование "подвала" страницы
	/**
	* Формирует "подвал" HTML-страницы с выводом копирайта. Использует рекурсивный вызов класса обработки шаблонов с передачей в качестве имени файла 'footer.html'
	* @see $lang, work, $content, $template_file, add_string_ar, add_if, add_case
	*/
	function page_footer()
	{
		global $lang, $work;

		$user = $work->template_user();
		$stat = $work->template_stat();
		$best_user = $work->template_best_user($work->config['best_user']);
		$rand_photo = $work->create_photo('rand');
		$temp_template = new template($this->site_url, $this->site_dir, $this->theme);
		$temp_template->add_string_ar(array(
			'COPYRIGHT_YEAR' => $work->clean_field($work->config['copyright_year']),
			'COPYRIGHT_URL' => $work->clean_field($work->config['copyright_url']),
			'COPYRIGHT_TEXT' => $work->clean_field($work->config['copyright_text'])
		));

		$temp_template->add_case('RIGHT_BLOCK', 'USER_INFO', 'RIGHT_PANEL[0]');
		$temp_template->add_string_ar($user, 'RIGHT_PANEL[0]');
		$temp_template->add_if ('USER_NOT_LOGIN', ($_SESSION['login_id'] == 0 ? true : false), 'RIGHT_PANEL[0]');

		$temp_template->add_case('RIGHT_BLOCK', 'STATISTIC', 'RIGHT_PANEL[1]');
		$temp_template->add_string_ar($stat, 'RIGHT_PANEL[1]');

		$temp_template->add_case('RIGHT_BLOCK', 'BEST_USER', 'RIGHT_PANEL[2]');
		$temp_template->add_string_ar($best_user[0], 'RIGHT_PANEL[2]');
		unset($best_user[0]);
		foreach ($best_user as $key => $val)
		{
			$temp_template->add_string_ar(array(
				'U_BEST_USER_PROFILE' => $val['user_url'],
				'D_USER_NAME' => $val['user_name'],
				'D_USER_PHOTO' => $val['user_photo']
			), 'RIGHT_PANEL[2]->BEST_USER[' . $key . ']');
			$temp_template->add_if ('USER_EXIST', (empty($val['user_url']) ? false : true), 'RIGHT_PANEL[2]->BEST_USER[' . $key . ']');
		}

		$temp_template->add_case('RIGHT_BLOCK', 'RANDOM_PHOTO', 'RIGHT_PANEL[3]');
		$temp_template->add_string_ar(array(
			'NAME_BLOCK' => $rand_photo['name_block'],
			'PHOTO_WIDTH' => $rand_photo['width'],
			'PHOTO_HEIGHT' => $rand_photo['height'],
			'MAX_FOTO_HEIGHT' => $work->config['temp_photo_h'] + 10,
			'D_NAME_PHOTO' => $rand_photo['name'],
			'D_DESCRIPTION_PHOTO' => $rand_photo['description'],
			'D_NAME_CATEGORY' => $rand_photo['category_name'],
			'D_DESCRIPTION_CATEGORY' => $rand_photo['category_description'],
			'PHOTO_RATE' => $rand_photo['rate'],
			'L_USER_ADD' => $lang['main']['user_add'],
			'U_PROFILE_USER_ADD' => $rand_photo['url_user'],
			'D_REAL_NAME_USER_ADD' => $rand_photo['real_name'],
			'U_PHOTO' => $rand_photo['url'],
			'U_THUMBNAIL_PHOTO' => $rand_photo['thumbnail_url'],
			'U_CATEGORY' => $rand_photo['category_url']
		), 'RIGHT_PANEL[3]');
		$temp_template->add_if ('USER_EXISTS', (empty($rand_photo['url_user']) ? false : true), 'RIGHT_PANEL[3]');

		$temp_template->template_file = 'footer.html';
		$temp_template->create_template();
		$this->content = $this->content . $temp_template->content;
		unset($temp_template);
	}

	/// Замена ссылок на более читаемый вид
	/**
	* Производит замену ссылок по всему полученному тексту в читаемый вид, при условии, что $mod_rewrite = true
	* @param $content содержимое, по которому необходимо произвестю замену ссылок
	* @param $txt указывает на то, что полученное содержимое является текстовым (True) или считать его как HTML (False, значение по-умолчанию)
	* @return Обработанное содержимое
	* @see $mod_rewrite
	*/
	function url_mod_rewrite($content, $txt = false)
	{
		if ($this->mod_rewrite)
		{
			$end = '()';
			if (!$txt) $end = '("|\')';
			$content = mb_ereg_replace('\?action=([A-Za-z0-9]+)(\&amp;|\&)id=([0-9]+)' . $end, '\\1/id_\\3.html\\4', $content);
			$content = mb_ereg_replace('\?action=([A-Za-z0-9]+)(\&amp;|\&)login=([^"?]+)(\&amp;|\&)email=([^"?]+)(\&amp;|\&)resend=true' . $end, '\\1/login=\\3/email=\\5/resend.html\\7', $content);
			$content = mb_ereg_replace('\?action=([A-Za-z0-9]+)(\&amp;|\&)login=([^"?]+)(\&amp;|\&)email=([^"?]+)(\&amp;|\&)activated_code=([A-Za-z0-9]+)' . $end, '\\1/login=\\3/email=\\5/activated_code_\\6.html\\7', $content);
			$content = mb_ereg_replace('\?action=([A-Za-z0-9]+)(\&amp;|\&)login=([^"?]+)(\&amp;|\&)email=([^"?]+)' . $end, '\\1/login=\\3/email=\\5/\\6', $content);
			$content = mb_ereg_replace('\?action=([A-Za-z0-9]+)' . $end, '\\1/\\2', $content);
		}
		return $content;
	}
}

/// Устаревший класс работы с шаблонами.
/**
* Данный класс содержит устаревшие функции по работе с шаблонами (наполнение данными, обработка). Будет заменен на новый.
* \todo Удалить все следы использования старого класса формирования вывода страницы
* @see template
*/
class template_old
{
	private $themes_path; ///< Путь к корню темы
	private $themes_url; ///< Ссылка на корень темы
	private $site_url; ///< Ссылка корня сайта
	private $site_dir; ///< Путь к корню сайта
	private $theme; ///< Тема пользователя

	/// Конструктор класса, формирует массив данных об используемой теме
	/**
	* @param $site_url содержит ссылку на корень сайта
	* @param $site_dir содержит путь к корню сайта
	* @param $theme содержит название используемой темы
	* @see $themes_path, $themes_url
	*/
	function template_old($site_url, $site_dir, $theme)
	{
		$this->site_url = $site_url;
		$this->site_dir = $site_dir;
		$this->theme = $theme;
		$this->themes_path = $site_dir . 'themes/' . $theme . '/'; // генерация системного пути к файлам шаблона
		$this->themes_url = $site_url . 'themes/' . $theme . '/'; // генерация URL к файлам шаблона
	}

	/// Функция формирует главную страницу сайта испрользуя как полученные переменные, так и внутренние функции класса
	/**
	* @param $action_menu содержит указатель на активный пункт меню
	* @param $title значение дополнения к названию сайта в шапку страницы
	* @param $main_block содержит центральный блок страницы
	* @param $redirect содержит массив с данными для редиректа страницы, если поступает пустой, то редирект не происходит
	* @return Обработанный шаблон главной страницы
	* @see work, create_menu, create_foto, template_user, template_stat, template_best_user, create_template
	*/
	function create_main_template($action_menu = '', $title, $main_block, $redirect = array())
	{
		global $work, $lang;

		if (!isset($redirect['IF_NEED_REDIRECT']) || empty($redirect['IF_NEED_REDIRECT']))
		{
			$redirect['IF_NEED_REDIRECT'] = false;
			$redirect['REDIRECT_TIME'] = false;
			$redirect['U_REDIRECT_URL'] = false;
		}

		$array_data = array(
						'META_DESRIPTION' => $work->config['meta_description'],
						'META_KEYWORDS' => $work->config['meta_keywords'],
						'IF_NEED_REDIRECT' => $redirect['IF_NEED_REDIRECT'],
						'REDIRECT_TIME' => $redirect['REDIRECT_TIME'],
						'U_REDIRECT_URL' => $redirect['U_REDIRECT_URL'],
						'TITLE' => $work->config['title_name'] . ' - ' . $title,
						'TEXT_SHORT_MENU' => $this->create_menu($action_menu, 0),
						'TEXT_LONG_MENU' => $this->create_menu($action_menu, 1),
						'TEXT_TOP_FOTO' => $this->create_foto('top'),
						'TEXT_LAST_FOTO' => $this->create_foto('last'),
						'TEXT_USER_INFO' => $this->template_user(),
						'MAIN_BLOCK' => $main_block,
						'TEXT_STATISTIC' => $this->template_stat(),
						'TEXT_BEST_USER' => $this->template_best_user($work->config['best_user']),
						'TEXT_RANDOM_FOTO' => $this->create_foto('rand'),

						'SITE_NAME' => $work->config['title_name'],
						'SITE_DESCRIPTION' => $work->config['title_description']
		);

		$content = $this->create_template('main.tpl', $array_data);

		$content = str_replace('{THEMES_PATH}', $this->themes_url, $content);
		$content = str_replace('{LEFT_PANEL_WIDHT}', $work->config['left_panel'], $content);
		$content = str_replace('{RIGHT_PANEL_WIDHT}', $work->config['right_panel'], $content);
		$content = str_replace('{GALLERY_WIDHT}', $work->config['gal_width'], $content);
		$content = str_replace('{COPYRIGHT_YEAR}', $work->config['copyright_year'], $content);
		$content = str_replace('{COPYRIGHT_URL}', $work->config['copyright_url'], $content);
		$content = str_replace('{COPYRIGHT_TEXT}', $work->config['copyright_text'], $content);
		$content = str_replace('{U_SEARCH}', $work->config['site_url'] . '?action=search', $content);
		$content = str_replace('{L_SEARCH}', $lang['main']['search'], $content);

		if (get_magic_quotes_gpc())
		{
			$content = stripslashes($content);
		}

		return $content;
	}

	/// Функция обрабатывает шаблон из указанного файла, заменяя в данными из полученного массива
	/**
	* @param $file_name содержит указатель на файл с шаблоном
	* @param $array_data сожержит массив, в котором ключи используются как фрагменты шаблона, значения - как данные, на которые они будут заменены
	* @return Обработанный шаблон
	* @see $themes_path
	*/
	function create_template($file_name, $array_data)
	{
		$content = @file_get_contents($this->themes_path . $file_name);
		if (!$content) log_in_file('Error template file: ' . $file_name . '!', DIE_IF_ERROR);

		foreach ($array_data as $key=>$val)
		{
			if (substr($key, 0, 3) == 'IF_' && $val == false)
			{
				if ($val == false)
				{
					$content = mb_ereg_replace('<!-- ' . $key . '_BEGIN -->.*<!-- ' . $key . '_END -->', '', $content);
				}
				else
				{
					$content = str_replace('<!-- ' . $key . '_BEGIN -->', '', $content);
					$content = str_replace('<!-- ' . $key . '_END -->', '', $content);
				}
			}
			else
			{
				$content = str_replace('{' . $key . '}', $val, $content);
			}
		}

		return $content;
	}

	/// Функция генерирует меню
	/**
	* @param $action содержит пункт меню, который является активным
	* @param $menu если равно 0 - создает горизонтальное краткое меню, если 1- вертикальное боковое меню
	* @return Сформированный HTML-код меню
	* @see db, work, user, create_template
	*/
	function create_menu($action = 'main', $menu = 0)
	{
		global $db, $work, $lang, $user;

		$m[0] = 'short';
		$m[1] = 'long';
		$text_menu = '';

		if ($db->select('*', TBL_MENU, '`' . $m[$menu] . '` = 1', array('id' => 'up')))
		{
			$temp_menu = $db->res_arr();
			if ($temp_menu)
			{
				foreach ($temp_menu as $val)
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
						$array_data = array();

						$array_data = array(
								'U_MENU' => $work->config['site_url'] . $val['url_action'],
								'L_MENU' => $lang['menu'][$val['name_action']]
						);

						if ($val['action'] == $action) $text_menu .= $this->create_template($m[$menu] . '_menu_txt.tpl', $array_data);
						else $text_menu .= $this->create_template($m[$menu] . '_menu_url.tpl', $array_data);
					}
				}
			}
			else log_in_file('Unable to get the menu', DIE_IF_ERROR);
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		$array_data = array();

		$array_data = array (
						'MENU_TEXT' => $text_menu,
						'NAME_BLOCK' => $lang['menu']['name_block']
		);

		$text_menu = $this->create_template($m[$menu] . '_menu.tpl', $array_data);

		return $text_menu;
	}

	/// Функция генерирует блок вывода последнего, лучшего, случайного или указанного изображения
	/**
	* @param $type если значение равно 'top' - вывести лучшее фото по оценкам пользователя, если 'last' - последнее добавленое фото, если 'cat' - вывести фото, указанное в $id_photo, если не равно пустому - вывести случайное изображение
	* @param $id_photo если $type равно 'cat' - выводит фото с указанным идентификатором
	* @return Сформированный HTML-код блока изображения
	* @see db, work, user, create_template
	*/
	function create_foto($type = 'top', $id_photo = 0)
	{
		global $db, $work, $lang, $user;

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
			if ($db->select('*', TBL_PHOTO, $where, $order, false, $limit)) $temp_foto = $db->res_row();
			else log_in_file($db->error, DIE_IF_ERROR);
		}
		else
		{
			$temp_foto = false;
		}

		$name_block = $lang['main'][$type . '_foto'];

		if ($temp_foto)
		{
			if ($db->select('*', TBL_CATEGORY, '`id` = ' . $temp_foto['category']))
			{
				$temp_category = $db->res_row();
				if ($temp_category)
				{
					$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_category['folder'] . '/' . $temp_foto['file'];
					$foto['url'] = $work->config['site_url'] . '?action=photo&id=' . $temp_foto['id'];
					$foto['thumbnail_url'] = $work->config['site_url'] . '?action=attach&foto=' . $temp_foto['id'] . '&thumbnail=1';
					$foto['name'] = $temp_foto['name'];
					$foto['category_name'] = $temp_category['name'];
					$foto['description'] = $temp_foto['description'];
					$foto['category_description'] = $temp_category['description'];
					$foto['rate'] = $lang['main']['rate'] . ': ' . $temp_foto['rate_user'] . '/' . $temp_foto['rate_moder'];

					if ($db->select('real_name', TBL_USERS, '`id` = ' . $temp_foto['user_upload']))
					{
						$user_add = $db->res_row();
						if ($user_add) $foto['user'] = $lang['main']['user_add'] . ': <a href="' . $work->config['site_url']  . '?action=profile&subact=profile&uid=' . $temp_foto['user_upload'] . '" title="' . $user_add['real_name'] . '">' . $user_add['real_name'] . '</a>';
						else
						{
							$foto['user'] = $lang['main']['no_user_add'];
							$user_add['real_name'] = $lang['main']['no_user_add'];
						}
					}
					else log_in_file($db->error, DIE_IF_ERROR);
					if ($temp_category['id'] == 0)
					{
						$foto['category_name'] = $temp_category['name'] . ' ' . $user_add['real_name'];
						$foto['category_description'] = $foto['category_name'];
						$foto['category_url'] = $work->config['site_url'] . '?action=category&cat=user&id=' . $temp_foto['user_upload'];
					}
					else $foto['category_url'] = $work->config['site_url'] . '?action=category&cat=' . $temp_category['id'];
				}
				else
				{
					$temp_foto['file'] = 'no_foto.png';
					$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file'];
					$foto['url'] = $work->config['site_url'] . '?action=photo&id=0';
					$foto['thumbnail_url'] = $work->config['site_url'] . '?action=attach&foto=0&thumbnail=1';
					$foto['name'] = $lang['main']['no_foto'];
					$foto['description'] = $lang['main']['no_foto'];
					$foto['category_name'] = $lang['main']['no_category'];
					$foto['category_description'] = $lang['main']['no_category'];
					$foto['rate'] = $lang['main']['rate'] . ': ' . $lang['main']['no_foto'];
					$foto['user'] = $lang['main']['no_user_add'];
					$foto['category_url'] = $work->config['site_url'];
				}
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}
		else
		{
			$temp_foto['file'] = 'no_foto.png';
			$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file'];
			$foto['url'] = $work->config['site_url'] . '?action=photo&id=0';
			$foto['thumbnail_url'] = $work->config['site_url'] . '?action=attach&foto=0&thumbnail=1';
			$foto['name'] = $lang['main']['no_foto'];
			$foto['description'] = $lang['main']['no_foto'];
			$foto['category_name'] = $lang['main']['no_category'];
			$foto['category_description'] = $lang['main']['no_category'];
			$foto['rate'] = $lang['main']['rate'] . ': ' . $lang['main']['no_foto'];
			$foto['user'] = $lang['main']['no_user_add'];
			$foto['category_url'] = $work->config['site_url'];
		}

		if (!@fopen($temp_path, 'r'))
		{
			$temp_foto['file'] = 'no_foto.png';
			$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file'];
			$foto['url'] = $work->config['site_url'] . '?action=photo&id=0';
			$foto['thumbnail_url'] = $work->config['site_url'] . '?action=attach&foto=0&thumbnail=1';
			$foto['name'] = $lang['main']['no_foto'];
			$foto['description'] = $lang['main']['no_foto'];
			$foto['category_name'] = $lang['main']['no_category'];
			$foto['category_description'] = $lang['main']['no_category'];
			$foto['rate'] = $lang['main']['rate'] . ': ' . $lang['main']['no_foto'];
			$foto['user'] = $lang['main']['no_user_add'];
			$foto['category_url'] = $work->config['site_url'];
		}

		$size = getimagesize($temp_path);
		if ($work->config['temp_photo_w'] == '0') $ratioWidth = 1;
		else $ratioWidth = $size[0]/$work->config['temp_photo_w'];
		if ($work->config['temp_photo_h'] == '0') $ratioHeight = 1;
		else $ratioHeight = $size[1]/$work->config['temp_photo_h'];

		if ($size[0] < $work->config['temp_photo_w'] && $size[1] < $work->config['temp_photo_h'] && $work->config['temp_photo_w'] != '0' && $work->config['temp_photo_h'] != '0')
		{
			$foto['width'] = $size[0];
			$foto['height'] = $size[1];
		}
		else
		{
			if ($ratioWidth < $ratioHeight)
			{
				$foto['width'] = $size[0]/$ratioHeight;
				$foto['height'] = $size[1]/$ratioHeight;
			}
			else
			{
				$foto['width'] = $size[0]/$ratioWidth;
				$foto['height'] = $size[1]/$ratioWidth;
			}
		}

		$array_data = array();

		$array_data = array(
				'NAME_BLOCK' => $name_block,
				'FOTO_WIDTH' => $foto['width'],
				'FOTO_HEIGHT' => $foto['height'],
				'MAX_FOTO_HEIGHT' => $work->config['temp_photo_h'] + 10,

				'D_NAME_PHOTO' => $foto['name'],
				'D_DESCRIPTION_PHOTO' => $foto['description'],
				'D_NAME_CATEGORY' => $foto['category_name'],
				'D_DESCRIPTION_CATEGORY' => $foto['category_description'],

				'FOTO_RATE' => $foto['rate'],
				'USER_ADD' => $foto['user'],

				'U_FOTO' => $foto['url'],
				'U_THUMBNAIL_PHOTO' => $foto['thumbnail_url'],
				'U_CATEGORY' => $foto['category_url']
		);

		if ($type == 'cat') return $this->create_template('mini_foto_category.tpl', $array_data);
		else return $this->create_template('mini_foto.tpl', $array_data);
	}

	/// Функция формирует блок для входа пользователя (если в режиме "Гость") или краткий вид информации о пользователе
	/**
	* @return Сформированный HTML-код блока пользователя
	* @see work, user, create_template
	*/
	function template_user()
	{
		global $lang, $work, $user;

		if ($_SESSION['login_id'] == 0)
		{
			$array_data = array();

			$array_data = array(
					'NAME_BLOCK' => $lang['main']['user_block'],
					'L_LOGIN' => $lang['main']['login'],
					'L_PASSWORD' => $lang['main']['pass'],
					'L_ENTER' => $lang['main']['enter'],
					'L_FORGOT_PASSWORD' => $lang['main']['forgot_password'],
					'L_REGISTRATION' => $lang['main']['registration'],

					'U_LOGIN' => $work->config['site_url'] . '?action=profile&subact=login',
					'U_FORGOT_PASSWORD' => $work->config['site_url'] . '?action=profile&subact=forgot',
					'U_REGISTRATION' => $work->config['site_url'] . '?action=profile&subact=regist'
			);

			return $this->create_template('login_user.tpl', $array_data);
		}
		else
		{
			$array_data = array();

			$array_data = array(
					'NAME_BLOCK' => $lang['main']['user_block'],
					'L_HI_USER' => $lang['main']['hi_user'] . ', ' . $user->user['real_name'],
					'L_GROUP' => $lang['main']['group'] . ': ' . $user->user['group'],
					'U_AVATAR' => $work->config['site_url'] . $work->config['avatar_folder'] . '/' . $user->user['avatar']
			);

			return $this->create_template('profile_user.tpl', $array_data);
		}
	}

	/// Функция формирует вывод новостей сайта
	/**
	* @param $news_data сожержит идентификатор новости (если $act = 'id') или количество выводимых новостей (если $act = 'last')
	* @param $act если $act = 'last', то выводим последнии новости сайта, иначе если $act = 'id', то выводим новость с указанным идентификатором
	* @return Сформированный HTML-код блока новостей
	* @see db, work, user, create_template
	*/
	function template_news($news_data = 1, $act='last')
	{
		global $db, $lang, $work, $user;

		$news['IF_EDIT_LONG'] = false;

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

		if ($temp_news && $user->user['news_view'] == true)
		{
			$result = '';
			foreach ($temp_news as $val)
			{
				$news['NAME_BLOCK'] = $lang['main']['title_news'] . ' - ' . $val['name_post'];
				$news['L_NEWS_DATA'] = $lang['main']['data_add'] . ': ' . $val['data_post'] . ' (' . $val['data_last_edit'] . ').';
				$news['L_TEXT_POST'] = $val['text_post'];
				if ($db->select('real_name', TBL_USERS, '`id` = ' . $val['user_post']))
				{
					$user_add = $db->res_row();
					if ($user_add) $news['L_NEWS_DATA'] .= '<br />' . $lang['main']['user_add'] . ': <a href="' . $work->config['site_url']  . '?action=profile&subact=profile&uid=' . $val['user_post'] . '" title="' . $user_add['real_name'] . '">' . $user_add['real_name'] . '</a>.';
				}
				else log_in_file($db->error, DIE_IF_ERROR);
				$news['L_TEXT_POST'] = trim(nl2br($news['L_TEXT_POST']));

				if ($user->user['news_moderate'] == true || ($user->user['id'] != 0 && $user->user['id'] == $val['user_post']))
				{
					$news['L_EDIT_BLOCK'] = $lang['main']['edit_news'];
					$news['L_DELETE_BLOCK'] = $lang['main']['delete_news'];
					$news['L_CONFIRM_DELETE_BLOCK'] = $lang['main']['confirm_delete_news'] . ' ' . $val['name_post'] . '?';
					$news['U_EDIT_BLOCK'] = $work->config['site_url'] . '?action=news&subact=edit&news=' . $val['id'];
					$news['U_DELETE_BLOCK'] = $work->config['site_url'] . '?action=news&subact=delete&news=' . $val['id'];
					$news['IF_EDIT_SHORT'] = true;
				}
				else
				{
					$news['L_EDIT_BLOCK'] = '';
					$news['L_DELETE_BLOCK'] = '';
					$news['L_CONFIRM_DELETE_BLOCK'] = '';
					$news['U_EDIT_BLOCK'] = '';
					$news['U_DELETE_BLOCK'] = '';
					$news['IF_EDIT_SHORT'] = false;
				}
				$result .= $this->create_template('news.tpl', $news);
			}
		}
		else
		{
			$news['NAME_BLOCK'] = $lang['main']['no_news'];
			$news['L_NEWS_DATA'] = '';
			$news['L_TEXT_POST'] = $lang['main']['no_news'];
			$news['L_TEXT_POST'] = trim(nl2br($news['L_TEXT_POST']));
			$news['L_EDIT_BLOCK'] = '';
			$news['L_DELETE_BLOCK'] = '';
			$news['L_CONFIRM_DELETE_BLOCK'] = '';
			$news['U_EDIT_BLOCK'] = '';
			$news['U_DELETE_BLOCK'] = '';
			$news['IF_EDIT_SHORT'] = false;
			$result = $this->create_template('news.tpl', $news);
		}

		return $result;
	}

	/// Функция формирует блок статистики для сайта
	/**
	* @return Сформированный HTML-код блока статистики
	* @see db, work, create_template
	*/
	function template_stat()
	{
		global $db, $lang, $work;

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
					$stat['online'] .= ', <a href="' . $work->config['site_url']  . '?action=profile&subact=profile&uid=' . $val['id'] . '" title="' . $val['real_name'] . '">' . $val['real_name'] . '</a>';
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

		return $this->create_template('stat.tpl', $array_data);
	}

	/// Функция формирует список из пользователей, заливших максимальное кол-во изображений
	/**
	* @param $best_user сожержит указатель, сколько выводить лучших пользователей
	* @return Сформированный HTML-код блока лучших пользователей
	* @see db, work, create_template
	*/
	function template_best_user($best_user = 1)
	{
		global $db, $lang, $work;

		$name_block = $lang['main']['best_user_1'] . $best_user . $lang['main']['best_user_2'];
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
				$text_best_user = '';
				foreach ($best_user_array as $best_user_name => $best_user_photo)
				{
					if ($db->select('real_name', TBL_USERS, '`id` = ' . $best_user_name))
					{
						$temp2 = $db->res_row();
						$array_data = array();
						$array_data = array(
								'D_USER_NAME' => '<a href="' . $work->config['site_url']  . '?action=profile&subact=profile&uid=' . $best_user_name . '" title="' . $temp2['real_name'] . '">' . $temp2['real_name'] . '</a>',
								'D_USER_PHOTO' => $best_user_photo
						);
					$text_best_user .= $this->create_template('best_user.tpl', $array_data);
					}
					else log_in_file($db->error, DIE_IF_ERROR);
				}
			}
			else
			{
				$array_data = array();
				$array_data = array(
						'D_USER_NAME' => '---',
						'D_USER_PHOTO' => '-'
				);
				$text_best_user = $this->create_template('best_user.tpl', $array_data);
			}
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		$array_data = array();
		$array_data = array(
				'NAME_BLOCK' => $name_block,
				'L_USER_NAME' => $lang['main']['user_name'],
				'L_USER_PHOTO' => $lang['main']['best_user_photo'],

				'TEXT_BEST_USER' => $text_best_user
		);

		return $this->create_template('best.tpl', $array_data);
	}

	/// Функция формирует фрагмент оценки
	/**
	* @param $if_who если равно 'user', то формирует для пользователя, если равно 'moder', то для преподавателя
	* @param $rate если равно 'false', то предлагает возможность проголосовать пользователю, иначе выводит только текущий результат голоса для пользователя
	* @return Сформированный HTML-код фрагмента оценки
	* @see work, create_template
	*/
	function template_rate($if_who = 'user', $rate = 'false')
	{
		global $lang, $work;

		if ($rate == '') $rate = 'false';

		$array_data = array();
		$array_data['L_IF_RATE'] = $lang['photo']['if_' . $if_who];

		if ($rate == 'false')
		{
            $array_data['D_IF_RATE'] = '<select name="rate_' . $if_who . '">';
            for ($i = -$work->config['max_rate']; $i <= $work->config['max_rate']; $i++)
            {
				if ($i == 0) $selected = ' selected'; else $selected = '';
				$array_data['D_IF_RATE'] .= '<option value="' . $i . '"' . $selected . '>' . $i . '</option>';
			}
			$array_data['D_IF_RATE'] .= '</select>';
		}
		else $array_data['D_IF_RATE'] = $rate;

		return $this->create_template('rate_blank.tpl', $array_data);
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

	/// Функция преобразует изображение в эскиз, при этом проводится проверка - если эскиз уже существует и его размеры соотвествуют нстройкам, указанным в конфигурации сайта, то просто возвращает уведомление о том, что изображение преобразовано - не выполняя никаких операций
	/**
	* @param $full_path системный путь к изображению
	* @param $thumbnail_path системный путь к эскизу
	* @return True если удалось создать эскиз, иначе False
	* @see work
	*/
	function image_resize($full_path, $thumbnail_path)
	{
		global $work;

		$thumbnail_size = @getimagesize($thumbnail_path);
		$full_size = getimagesize($full_path);
		$foto['type'] = $full_size[2];

		if ($work->config['temp_photo_w'] == '0') $ratioWidth = 1;
		else $ratioWidth = $full_size[0]/$work->config['temp_photo_w'];
		if ($work->config['temp_photo_h'] == '0') $ratioHeight = 1;
		else $ratioHeight = $full_size[1]/$work->config['temp_photo_h'];

		if ($full_size[0] < $work->config['temp_photo_w'] && $full_size[1] < $work->config['temp_photo_h'] && $work->config['temp_photo_w'] != '0' && $work->config['temp_photo_h'] != '0')
		{
			$foto['width'] = $full_size[0];
			$foto['height'] = $full_size[1];
		}
		else
		{
			if ($ratioWidth < $ratioHeight)
			{
				$foto['width'] = $full_size[0]/$ratioHeight;
				$foto['height'] = $full_size[1]/$ratioHeight;
			}
			else
			{
				$foto['width'] = $full_size[0]/$ratioWidth;
				$foto['height'] = $full_size[1]/$ratioWidth;
			}
		}

		if ($thumbnail_size[0] != $foto['width'] || $thumbnail_size[1] != $foto['height'])
		{
			switch($foto['type'])
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
			$im = imagecreatetruecolor($foto['width'], $foto['height']);
			if (imagecopyresampled($im, $imorig , 0, 0, 0, 0, $foto['width'], $foto['height'], $full_size[0], $full_size[1]))
			{
				@unlink($thumbnail_path);

				switch($foto['type'])
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
}
?>
