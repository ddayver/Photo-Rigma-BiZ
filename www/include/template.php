<?php
/**
* @file		include/template.php
* @brief	Работа с шаблонами
* @author	Dark Dayver
* @version	0.2.0
* @date		28/03-2012
* @details	Содержит класс работы с шаблонами на сервере
*/

if (IN_GALLERY)
{
	die('HACK!');
}

/// Класс работы с шаблонами.
/**
* Данный класс содержит функции по работе с шаблонами (наполнение данными, обработка).
*/
class template
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
	function template($site_url, $site_dir, $theme)
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
		$content = str_replace('{L_SEARCH}', $lang['main_search'], $content);

		if(get_magic_quotes_gpc())
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

					if($val['user_login'] != '')
					{
						if ($val['user_login'] == 0 && $user->user['id'] > 0) $visible = false;
						if ($val['user_login'] == 1 && $user->user['id'] == 0) $visible = false;
					}
					if ($val['user_access'] != '') if($user->user[$val['user_access']] != 1) $visible = false;

					if($visible)
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

		$name_block = $lang['main_' . $type . '_foto'];

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
					$foto['rate'] = $lang['main_rate'] . ': ' . $temp_foto['rate_user'] . '/' . $temp_foto['rate_moder'];

					if ($db->select('real_name', TBL_USERS, '`id` = ' . $temp_foto['user_upload']))
					{
						$user_add = $db->res_row();
						if ($user_add) $foto['user'] = $lang['main_user_add'] . ': <a href="' . $work->config['site_url']  . '?action=login&subact=profile&uid=' . $temp_foto['user_upload'] . '" title="' . $user_add['real_name'] . '">' . $user_add['real_name'] . '</a>';
						else
						{
							$foto['user'] = $lang['main_no_user_add'];
							$user_add['real_name'] = $lang['main_no_user_add'];
						}
					}
					else log_in_file($db->error, DIE_IF_ERROR);
					if($temp_category['id'] == 0)
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
					$foto['name'] = $lang['main_no_foto'];
					$foto['description'] = $lang['main_no_foto'];
					$foto['category_name'] = $lang['main_no_category'];
					$foto['category_description'] = $lang['main_no_category'];
					$foto['rate'] = $lang['main_rate'] . ': ' . $lang['main_no_foto'];
					$foto['user'] = $lang['main_no_user_add'];
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
			$foto['name'] = $lang['main_no_foto'];
			$foto['description'] = $lang['main_no_foto'];
			$foto['category_name'] = $lang['main_no_category'];
			$foto['category_description'] = $lang['main_no_category'];
			$foto['rate'] = $lang['main_rate'] . ': ' . $lang['main_no_foto'];
			$foto['user'] = $lang['main_no_user_add'];
			$foto['category_url'] = $work->config['site_url'];
		}

		if(!@fopen($temp_path, 'r'))
		{
			$temp_foto['file'] = 'no_foto.png';
			$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file'];
			$foto['url'] = $work->config['site_url'] . '?action=photo&id=0';
			$foto['thumbnail_url'] = $work->config['site_url'] . '?action=attach&foto=0&thumbnail=1';
			$foto['name'] = $lang['main_no_foto'];
			$foto['description'] = $lang['main_no_foto'];
			$foto['category_name'] = $lang['main_no_category'];
			$foto['category_description'] = $lang['main_no_category'];
			$foto['rate'] = $lang['main_rate'] . ': ' . $lang['main_no_foto'];
			$foto['user'] = $lang['main_no_user_add'];
			$foto['category_url'] = $work->config['site_url'];
		}

		$size = getimagesize($temp_path);
		if ($work->config['temp_photo_w'] == '0') $ratioWidth = 1;
		else $ratioWidth = $size[0]/$work->config['temp_photo_w'];
		if ($work->config['temp_photo_h'] == '0') $ratioHeight = 1;
		else $ratioHeight = $size[1]/$work->config['temp_photo_h'];

		if($size[0] < $work->config['temp_photo_w'] && $size[1] < $work->config['temp_photo_h'] && $work->config['temp_photo_w'] != '0' && $work->config['temp_photo_h'] != '0')
		{
			$foto['width'] = $size[0];
			$foto['height'] = $size[1];
		}
		else
		{
			if($ratioWidth < $ratioHeight)
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
					'NAME_BLOCK' => $lang['main_user_block'],
					'L_LOGIN' => $lang['main_login'],
					'L_PASSWORD' => $lang['main_pass'],
					'L_ENTER' => $lang['main_enter'],
					'L_FORGOT_PASSWORD' => $lang['main_forgot_password'],
					'L_REGISTRATION' => $lang['main_registration'],

					'U_LOGIN' => $work->config['site_url'] . '?action=login&subact=login',
					'U_FORGOT_PASSWORD' => $work->config['site_url'] . '?action=login&subact=forgot',
					'U_REGISTRATION' => $work->config['site_url'] . '?action=login&subact=regist'
			);

			return $this->create_template('login_user.tpl', $array_data);
		}
		else
		{
			$array_data = array();

			$array_data = array(
					'NAME_BLOCK' => $lang['main_user_block'],
					'L_HI_USER' => $lang['main_hi_user'] . ', ' . $user->user['real_name'],
					'L_GROUP' => $lang['main_group'] . ': ' . $user->user['group'],
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

		if($act == 'id')
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
				$news['NAME_BLOCK'] = $lang['main_title_news'] . ' - ' . $val['name_post'];
				$news['L_NEWS_DATA'] = $lang['main_data_add'] . ': ' . $val['data_post'] . ' (' . $val['data_last_edit'] . ').';
				$news['L_TEXT_POST'] = $val['text_post'];
				if ($db->select('real_name', TBL_USERS, '`id` = ' . $val['user_post']))
				{
					$user_add = $db->res_row();
					if ($user_add) $news['L_NEWS_DATA'] .= '<br />' . $lang['main_user_add'] . ': <a href="' . $work->config['site_url']  . '?action=login&subact=profile&uid=' . $val['user_post'] . '" title="' . $user_add['real_name'] . '">' . $user_add['real_name'] . '</a>.';
				}
				else log_in_file($db->error, DIE_IF_ERROR);
				$news['L_TEXT_POST'] = trim(nl2br($news['L_TEXT_POST']));

				if ($user->user['news_moderate'] == true || ($user->user['id'] != 0 && $user->user['id'] == $val['user_post']))
				{
					$news['L_EDIT_BLOCK'] = $lang['main_edit_news'];
					$news['L_DELETE_BLOCK'] = $lang['main_delete_news'];
					$news['L_CONFIRM_DELETE_BLOCK'] = $lang['main_confirm_delete_news'] . ' ' . $val['name_post'] . '?';
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
			$news['NAME_BLOCK'] = $lang['main_no_news'];
			$news['L_NEWS_DATA'] = '';
			$news['L_TEXT_POST'] = $lang['main_no_news'];
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
					$stat['online'] .= ', <a href="' . $work->config['site_url']  . '?action=login&subact=profile&uid=' . $val['id'] . '" title="' . $val['real_name'] . '">' . $val['real_name'] . '</a>';
				}
				$stat['online'] = substr($stat['online'], 2) . '.';
			}
			else $stat['online'] = $lang['main_stat_no_online'];
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		$array_data = array();

		$array_data = array(
				'NAME_BLOCK' => $lang['main_stat_title'],
				'L_STAT_REGIST' => $lang['main_stat_regist'],
				'L_STAT_PHOTO' => $lang['main_stat_photo'],
				'L_STAT_CATEGORY' => $lang['main_stat_category'],
				'L_STAT_USER_ADMIN' => $lang['main_stat_user_admin'],
				'L_STAT_USER_MODER' => $lang['main_stat_user_moder'],
				'L_STAT_RATE_USER' => $lang['main_stat_rate_user'],
				'L_STAT_RATE_MODER' => $lang['main_stat_rate_moder'],
				'L_STAT_ONLINE' => $lang['main_stat_online'],

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

		$name_block = $lang['main_best_user_1'] . $best_user . $lang['main_best_user_2'];
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
								'D_USER_NAME' => '<a href="' . $work->config['site_url']  . '?action=login&subact=profile&uid=' . $best_user_name . '" title="' . $temp2['real_name'] . '">' . $temp2['real_name'] . '</a>',
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
				'L_USER_NAME' => $lang['main_user_name'],
				'L_USER_PHOTO' => $lang['main_best_user_photo'],

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
		$array_data['L_IF_RATE'] = $lang['photo_if_' . $if_who];

		if($rate == 'false')
		{
            $array_data['D_IF_RATE'] = '<select name="rate_' . $if_who . '">';
            for ($i = -$work->config['max_rate']; $i <= $work->config['max_rate']; $i++)
            {
				if($i == 0) $selected = ' selected'; else $selected = '';
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

		if($full_size[0] < $work->config['temp_photo_w'] && $full_size[1] < $work->config['temp_photo_h'] && $work->config['temp_photo_w'] != '0' && $work->config['temp_photo_h'] != '0')
		{
			$foto['width'] = $full_size[0];
			$foto['height'] = $full_size[1];
		}
		else
		{
			if($ratioWidth < $ratioHeight)
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
					$imorig = imagecreatefromgif($full_path);
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
						imagegif($im, $thumbnail_path);
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
