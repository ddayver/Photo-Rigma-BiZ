<?php
/**
 * @file        include/template.php
 * @brief       Работа с шаблонами
 * @author      Dark Dayver
 * @version     0.3.0
 * @date        15/07-2012
 * @details     Содержит класс работы с шаблонами на сервере
 */
/// @cond
if (IN_GALLERY !== TRUE)
{
	die('HACK!');
}
/// @endcond

/// Новый класс работы с шаблонами.
/**
 * Данный класс содержит функции по работе с шаблонами (наполнение данными, обработка).
 */
class template
{
	var $ins_header = ''; ///< Данные, вставляемые в заголовок
	var $ins_body = ''; ///< Данные, вставляемые в тег body
	var $content; ///< Содержимое для вывода
	var $mod_rewrite = FALSE; ///< Включение читаемых URL
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
	 * @param $theme    содержит название используемой темы
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
			return TRUE;
		}
		else
		{
			log_in_file('Not found template file: ' . $this->themes_path . $this->template_file, DIE_IF_ERROR);
			return FALSE;
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
	 * @param $name       содержит название пемеренной
	 * @param $value      содержит значение переменной
	 * @param $path_array содержит путь, по которому рекурсивно необходимо разместить переменную в виде: Массив1[0]->Массив1.0[0] (по-умолчанию False)
	 * @see $block_string, $block_object, test_is_object
	 */
	function add_string($name, $value, $path_array = FALSE)
	{
		if ($path_array == FALSE)
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
	function add_string_ar($array_data, $path_array = FALSE)
	{
		if (is_array($array_data))
		{
			foreach ($array_data as $key => $value)
			{
				$this->add_string($key, $value, $path_array);
			}
		}
	}

	/// Добавление данных об условиях вывода фрагментов шаблона
	/**
	 * Добавляет данные об условиях вывода фрагментов шаблона как в основе шаблона, так и в рекурсивных массивах
	 * @param $name       содержит название пемеренной условия
	 * @param $value      содержит значение переменной (True или False)
	 * @param $path_array содержит путь, по которому рекурсивно необходимо разместить переменную в виде: Массив1[0]->Массив1.0[0] (по-умолчанию False)
	 * @see $block_if, $block_object, test_is_object
	 */
	function add_if($name, $value, $path_array = FALSE)
	{
		if ($path_array == FALSE)
		{
			if ($value != FALSE) $value = TRUE;
			else $value = FALSE;
			$this->block_if['IF_' . strtoupper($name)] = $value;
		}
		else
		{
			$temp_result = $this->test_is_object($path_array);
			$this->block_object[$temp_result['current']][$temp_result['index']]->add_if($name, $value, $temp_result['next_path']);
		}
	}

	/// Добавление массива данных об условиях вывода фрагментов шаблона
	/**
	 * Добавляет массив данных об условиях вывода фрагментов шаблона как в основе шаблона, так и в рекурсивных массивах
	 * @param $array_data содержит массив данных об условиях вывода фрагментов шаблона в стиле: 'название_условия' => 'значение'
	 * @param $path_array содержит путь, по которому рекурсивно необходимо разместить переменную в виде: Массив1[0]->Массив1.0[0] (по-умолчанию False)
	 * @see add_if
	 */
	function add_if_ar($array_data, $path_array = FALSE)
	{
		if (is_array($array_data))
		{
			foreach ($array_data as $key => $value)
			{
				$this->add_if($key, $value, $path_array);
			}
		}
	}

	/// Добавление данных о выборе блока для вывода фрагментов шаблона
	/**
	 * Добавляет данные о выборе блока для вывода фрагментов шаблона как в основе шаблона, так и в рекурсивных массивах
	 * @param $name       содержит название пемеренной условия
	 * @param $value      содержит значение переменной (наименование или номер блока)
	 * @param $path_array содержит путь, по которому рекурсивно необходимо разместить переменную в виде: Массив1[0]->Массив1.0[0] (по-умолчанию False)
	 * @see $block_case, $block_object, test_is_object
	 */
	function add_case($name, $value, $path_array = FALSE)
	{
		if ($path_array == FALSE)
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
	function add_case_ar($array_data, $path_array = FALSE)
	{
		if (is_array($array_data))
		{
			foreach ($array_data as $key => $value)
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
			$result['next_path'] = FALSE;
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
		foreach ($this->block_object as $key => $val) $this->template_object($key, $val);
		foreach ($this->block_if as $key => $val) $this->template_if($key, $val);
		foreach ($this->block_case as $key => $val) $this->template_case($key, $val);
		foreach ($this->block_string as $key => $val) $this->content = str_replace('{' . $key . '}', $val, $this->content);
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
	 * @param $key   ключ-название фрагмента заменемого блока
	 * @param $index индекс-блок элементов дл рекурсивной замены
	 * @see $content, pars_template
	 */
	private function template_object($key, $index)
	{
		while (strpos($this->content, '<!-- ARRAY_' . $key . '_BEGIN -->') !== FALSE)
		{
			$begin_start = strpos($this->content, '<!-- ARRAY_' . $key . '_BEGIN -->');
			$begin_end = $begin_start + strlen('<!-- ARRAY_' . $key . '_BEGIN -->');
			$end_start = strpos($this->content, '<!-- ARRAY_' . $key . '_END -->', $begin_end);
			$end_end = $end_start + strlen('<!-- ARRAY_' . $key . '_END -->');
			if ($end_start !== FALSE)
			{
				$block_content = '';
				$temp_content = substr($this->content, $begin_end, $end_start - $begin_end);
				foreach ($index as $id => $value)
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
	private function template_if($key, $val)
	{
		while (strpos($this->content, '<!-- ' . $key . '_BEGIN -->') !== FALSE)
		{
			$begin_start = strpos($this->content, '<!-- ' . $key . '_BEGIN -->');
			$begin_end = $begin_start + strlen('<!-- ' . $key . '_BEGIN -->');
			$else_start = strpos($this->content, '<!-- ' . $key . '_ELSE -->', $begin_end);
			$else_end = $else_start + strlen('<!-- ' . $key . '_ELSE -->');
			$end_start = strpos($this->content, '<!-- ' . $key . '_END -->', $begin_end);
			$end_end = $end_start + strlen('<!-- ' . $key . '_END -->');

			if ($else_start !== FALSE && $end_start !== FALSE && $else_start < $end_start)
			{
				$temp_content = substr($this->content, $begin_start, $end_end - $begin_start);
				if ($val) $tmp = substr($this->content, $begin_end, $else_start - $begin_end);
				else $tmp = substr($this->content, $else_end, $end_start - $else_end);
				$this->content = str_replace($temp_content, $tmp, $this->content);
			}
			elseif ($end_start !== FALSE)
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
		while (strpos($this->content, '<!-- ' . $key . '_BEGIN -->') !== FALSE)
		{
			$begin_start = strpos($this->content, '<!-- ' . $key . '_BEGIN -->');
			$begin_end = $begin_start + strlen('<!-- ' . $key . '_BEGIN -->');
			$end_start = strpos($this->content, '<!-- ' . $key . '_END -->', $begin_end);
			$end_end = $end_start + strlen('<!-- ' . $key . '_END -->');

			if ($end_start !== FALSE)
			{
				$temp_content = substr($this->content, $begin_start, $end_end - $begin_start);
				$tmp = '';
				$case_start = strpos($this->content, '<!-- CASE_' . $val . ' -->', $begin_end);
				$case_end = $case_start + strlen('<!-- CASE_' . $val . ' -->');
				$break_start = strpos($this->content, '<!-- BREAK_' . $val . ' -->', $case_end);
				$break_end = $end_start + strlen('<!-- BREAK_' . $val . ' -->');
				if ($break_start !== FALSE && $case_start < $end_start && $break_start < $end_start) $tmp = substr($this->content, $case_end, $break_start - $case_end);
				else
				{
					$case_start = strpos($this->content, '<!-- CASE_DEFAULT -->', $begin_end);
					$case_end = $case_start + strlen('<!-- CASE_DEFAULT -->');
					$break_start = strpos($this->content, '<!-- BREAK_DEFAULT -->', $case_end);
					$break_end = $end_start + strlen('<!-- BREAK_DEFAULT -->');
					if ($break_start !== FALSE && $case_start < $end_start && $break_start < $end_start) $tmp = substr($this->content, $case_end, $break_start - $case_end);
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
			'TITLE'             => (empty($title) ? $work->config['title_name'] : $work->clean_field($work->config['title_name']) . ' - ' . $work->clean_field($title)),
			'INSERT_HEADER'     => $this->ins_header,
			'INSERT_BODY'       => $this->ins_body,
			'META_DESRIPTION'   => $work->clean_field($work->config['meta_description']),
			'META_KEYWORDS'     => $work->clean_field($work->config['meta_keywords']),
			'GALLERY_WIDHT'     => $work->config['gal_width'],
			'SITE_NAME'         => $work->clean_field($work->config['title_name']),
			'SITE_DESCRIPTION'  => $work->clean_field($work->config['title_description']),
			'U_SEARCH'          => $work->config['site_url'] . '?action=search',
			'L_SEARCH'          => $lang['main']['search'],
			'LEFT_PANEL_WIDHT'  => $work->config['left_panel'],
			'RIGHT_PANEL_WIDHT' => $work->config['right_panel']
		));

		$temp_template->add_if('SHORT_MENU', FALSE);
		if ($s_menu && is_array($s_menu))
		{
			$temp_template->add_if('SHORT_MENU', TRUE);
			foreach ($s_menu as $id => $value)
			{
				$temp_template->add_string_ar(array(
						'U_SHORT_MENU' => $value['url'],
						'L_SHORT_MENU' => $value['name']
					), 'SHORT_MENU[' . $id . ']');
				$temp_template->add_if('SHORT_MENU_URL', (empty($value['url']) ? FALSE : TRUE), 'SHORT_MENU[' . $id . ']');
			}
		}

		$temp_template->add_case('LEFT_BLOCK', 'MENU', 'LEFT_PANEL[0]');
		$temp_template->add_if('LONG_MENU', FALSE, 'LEFT_PANEL[0]');
		if ($l_menu && is_array($l_menu))
		{
			$temp_template->add_if('LONG_MENU', TRUE, 'LEFT_PANEL[0]');
			$temp_template->add_string('LONG_MENU_NAME_BLOCK', $lang['menu']['name_block'], 'LEFT_PANEL[0]');
			foreach ($l_menu as $id => $value)
			{
				$temp_template->add_string_ar(array(
						'U_LONG_MENU' => $value['url'],
						'L_LONG_MENU' => $value['name']
					), 'LEFT_PANEL[0]->LONG_MENU[' . $id . ']');
				$temp_template->add_if('LONG_MENU_URL', (empty($value['url']) ? FALSE : TRUE), 'LEFT_PANEL[0]->LONG_MENU[' . $id . ']');
			}
		}

		$temp_template->add_case('LEFT_BLOCK', 'TOP_LAST_PHOTO', 'LEFT_PANEL[1]');
		$temp_template->add_string_ar(array(
			'NAME_BLOCK'             => $photo_top['name_block'],
			'PHOTO_WIDTH'            => $photo_top['width'],
			'PHOTO_HEIGHT'           => $photo_top['height'],
			'MAX_FOTO_HEIGHT'        => $work->config['temp_photo_h'] + 10,
			'D_NAME_PHOTO'           => $photo_top['name'],
			'D_DESCRIPTION_PHOTO'    => $photo_top['description'],
			'D_NAME_CATEGORY'        => $photo_top['category_name'],
			'D_DESCRIPTION_CATEGORY' => $photo_top['category_description'],
			'PHOTO_RATE'             => $photo_top['rate'],
			'L_USER_ADD'             => $lang['main']['user_add'],
			'U_PROFILE_USER_ADD'     => $photo_top['url_user'],
			'D_REAL_NAME_USER_ADD'   => $photo_top['real_name'],
			'U_PHOTO'                => $photo_top['url'],
			'U_THUMBNAIL_PHOTO'      => $photo_top['thumbnail_url'],
			'U_CATEGORY'             => $photo_top['category_url']
		), 'LEFT_PANEL[1]');
		$temp_template->add_if('USER_EXISTS', (empty($photo_top['url_user']) ? FALSE : TRUE), 'LEFT_PANEL[1]');

		$temp_template->add_case('LEFT_BLOCK', 'TOP_LAST_PHOTO', 'LEFT_PANEL[2]');
		$temp_template->add_string_ar(array(
			'NAME_BLOCK'             => $photo_last['name_block'],
			'PHOTO_WIDTH'            => $photo_last['width'],
			'PHOTO_HEIGHT'           => $photo_last['height'],
			'MAX_FOTO_HEIGHT'        => $work->config['temp_photo_h'] + 10,
			'D_NAME_PHOTO'           => $photo_last['name'],
			'D_DESCRIPTION_PHOTO'    => $photo_last['description'],
			'D_NAME_CATEGORY'        => $photo_last['category_name'],
			'D_DESCRIPTION_CATEGORY' => $photo_last['category_description'],
			'PHOTO_RATE'             => $photo_last['rate'],
			'L_USER_ADD'             => $lang['main']['user_add'],
			'U_PROFILE_USER_ADD'     => $photo_last['url_user'],
			'D_REAL_NAME_USER_ADD'   => $photo_last['real_name'],
			'U_PHOTO'                => $photo_last['url'],
			'U_THUMBNAIL_PHOTO'      => $photo_last['thumbnail_url'],
			'U_CATEGORY'             => $photo_last['category_url']
		), 'LEFT_PANEL[2]');
		$temp_template->add_if('USER_EXISTS', (empty($photo_last['url_user']) ? FALSE : TRUE), 'LEFT_PANEL[2]');

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
			'COPYRIGHT_URL'  => $work->clean_field($work->config['copyright_url']),
			'COPYRIGHT_TEXT' => $work->clean_field($work->config['copyright_text'])
		));

		$temp_template->add_case('RIGHT_BLOCK', 'USER_INFO', 'RIGHT_PANEL[0]');
		$temp_template->add_string_ar($user, 'RIGHT_PANEL[0]');
		$temp_template->add_if('USER_NOT_LOGIN', ($_SESSION['login_id'] == 0 ? TRUE : FALSE), 'RIGHT_PANEL[0]');

		$temp_template->add_case('RIGHT_BLOCK', 'STATISTIC', 'RIGHT_PANEL[1]');
		$temp_template->add_string_ar($stat, 'RIGHT_PANEL[1]');

		$temp_template->add_case('RIGHT_BLOCK', 'BEST_USER', 'RIGHT_PANEL[2]');
		$temp_template->add_string_ar($best_user[0], 'RIGHT_PANEL[2]');
		unset($best_user[0]);
		foreach ($best_user as $key => $val)
		{
			$temp_template->add_string_ar(array(
					'U_BEST_USER_PROFILE' => $val['user_url'],
					'D_USER_NAME'         => $val['user_name'],
					'D_USER_PHOTO'        => $val['user_photo']
				), 'RIGHT_PANEL[2]->BEST_USER[' . $key . ']');
			$temp_template->add_if('USER_EXIST', (empty($val['user_url']) ? FALSE : TRUE), 'RIGHT_PANEL[2]->BEST_USER[' . $key . ']');
		}

		$temp_template->add_case('RIGHT_BLOCK', 'RANDOM_PHOTO', 'RIGHT_PANEL[3]');
		$temp_template->add_string_ar(array(
			'NAME_BLOCK'             => $rand_photo['name_block'],
			'PHOTO_WIDTH'            => $rand_photo['width'],
			'PHOTO_HEIGHT'           => $rand_photo['height'],
			'MAX_FOTO_HEIGHT'        => $work->config['temp_photo_h'] + 10,
			'D_NAME_PHOTO'           => $rand_photo['name'],
			'D_DESCRIPTION_PHOTO'    => $rand_photo['description'],
			'D_NAME_CATEGORY'        => $rand_photo['category_name'],
			'D_DESCRIPTION_CATEGORY' => $rand_photo['category_description'],
			'PHOTO_RATE'             => $rand_photo['rate'],
			'L_USER_ADD'             => $lang['main']['user_add'],
			'U_PROFILE_USER_ADD'     => $rand_photo['url_user'],
			'D_REAL_NAME_USER_ADD'   => $rand_photo['real_name'],
			'U_PHOTO'                => $rand_photo['url'],
			'U_THUMBNAIL_PHOTO'      => $rand_photo['thumbnail_url'],
			'U_CATEGORY'             => $rand_photo['category_url']
		), 'RIGHT_PANEL[3]');
		$temp_template->add_if('USER_EXISTS', (empty($rand_photo['url_user']) ? FALSE : TRUE), 'RIGHT_PANEL[3]');

		$temp_template->template_file = 'footer.html';
		$temp_template->create_template();
		$this->content = $this->content . $temp_template->content;
		unset($temp_template);
	}

	/// Замена ссылок на более читаемый вид
	/**
	 * Производит замену ссылок по всему полученному тексту в читаемый вид, при условии, что $mod_rewrite = true
	 * @param $content содержимое, по которому необходимо произвестю замену ссылок
	 * @param $txt     указывает на то, что полученное содержимое является текстовым (True) или считать его как HTML (False, значение по-умолчанию)
	 * @return Обработанное содержимое
	 * @see    $mod_rewrite
	 */
	function url_mod_rewrite($content, $txt = FALSE)
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
?>
