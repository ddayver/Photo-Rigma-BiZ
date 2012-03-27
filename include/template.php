<?php
/**
* @file		include/template.php
* @brief	Класс обработки шаблонов перед выводом пользователю.
* @author	Dark Dayver
* @version	0.1.1
* @date		27/03-2012
* @details	Класс обработки шаблонов перед выводом пользователю.
*/

// Проверка, что файл подключается из индексного, а не набран напрямую в адресной строке
if (IN_GALLERY)
{
	die('HACK!');
}

class template
{
	// В качестве переменных классу передаются:
	// $site_url - URL корня сайта
	// $site_dir - системный путь к корню сайта
	// $themes - имя используемой темы

	// Внутри класса используются:
	// $themes_path - содержит системный путь к файлам шаблона
	// $themes_url - URL к файлам темы
	// $themes_path и $themes_url - генерируются при создании объекта класса

	// Функции:
	// Themes_Work($site_url, $site_dir, $themes) - создает объект класа и генерирует внутренние переменные
	// create_main_template($action_menu, $title, $main_block, $redirect) - формирует главную страницу сайта испрользуя как полученные переменные, так и внутренние функции класса; $action_menu - указатель на активный пункт меню, $title - значение дополнения к названию сайта в шапку страницы, $main_block - центральный блок страницы, $redirect = массив с данными для редиректа страницы, если поступает пустой, то редирект не происходит
	// create_template($file_name, $array_data) - обрабатывает шаблон из файла $file_name, заменяя в файле данные, используя массив $array_data - ключи используются как фрагменты шаблона, значения - как данные, которые на которые они будут заменены.
	// create_menu($action, $menu) - генерирует меню, в качестве данных получает: $action - пункт меню, который является активным, $menu - если равно 0 - создает горизонтальное краткое меню, если 1- вертикальное боковое меню, на выходе - сформированный HTML-код.
	// create_foto($type, $id_photo) - генерирует блок вывода последнего, лучшего, случайного или указанного в $id_photo изображения, если на входе значение $top равно 'top' - вывести лучшее фото по оценкам пользователя, если 'last' - последнее добавленое фото, если 'cat' - вывести фото, указанное в $id_photo, если не равно пустому - вывести случайное изображение; на выходе - сформированный HTML-код
	// template_user() - формирует блок для входа пользователя (если в режиме "Гость") или краткий вид информации о пользователе
	// template_news($news_data, $act) - формируем вывод новостей сайта, если $act = 'last', то выводим последнии новости сайта в количестве, указанном в $news_data, иначе если $act = 'id', то выводим новость с идентификатором, полученным в $news_data
	// template_stat() - формирует блок статистики для сайта
	// template_best_user($best_user) формирует список из пользователей, заливших максимальное кол-во изображений в кол-ве, полученном в $best_user
	// template_rate($if_who, $rate) - формирует фрагмент оценки от пользователя, если $if_who = 'user', то формирует для пользователя, если $if_who = 'moder', то для преподавателя; если $rate = 'false', то предлагает возможность проголосовать пользователю, иначе выводит только текущий результат голоса для пользователя
	// Image_Attach($full_path, $name_file) - выводит изображение, скрывая путь к нему, на входе: $full_path - системный путь к изображению, $name_file - имя файла, на выходе - изображение.
	// Image_Resize($full_path, $thumbnail_path) - преобразует изображение, полученное из $full_path, в эскиз, путь к которому казан в $thumbnail_path; при этом проводится проверка - если эскиз уже существует и его размеры соотвествуют нстройкам, указанным в конфигурации сайта, то просто возвращает уведомление о том, что изображение преобразовано - не выполняя никаких операций

	var $themes_path;
	var $themes_url;

	function template($site_url, $site_dir, $themes)
	{
		$this->themes_path = $site_dir . 'themes/' . $themes . '/'; // генерация системного пути к файлам шаблона
		$this->themes_url = $site_url . 'themes/' . $themes . '/'; // генерация URL к файлам шаблона
	}

	function create_main_template($action_menu = '', $title, $main_block, $redirect = array())
	{
		global $work, $lang; // Подключение массива глобальных настроек и языковых значений

		if (!isset($redirect['IF_NEED_REDIRECT']) || empty($redirect['IF_NEED_REDIRECT']))
		{
			$redirect['IF_NEED_REDIRECT'] = false; // если данные о редиректе не поступили, то редирект происходить не будет
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
		); // наполняем массив данными для замены по шаблону

		$content = $this->create_template('main.tpl', $array_data); // формируем основную страницу сайта

		// Дополнительная обработка сформированной страницы
		$content = str_replace('{THEMES_PATH}', $this->themes_url, $content); // замена всех упоминаний пути к файлам шаблона на соотвествующий URL
		$content = str_replace('{LEFT_PANEL_WIDHT}', $work->config['left_panel'], $content); // замена всех упоминаний значения ширины левой панели
		$content = str_replace('{RIGHT_PANEL_WIDHT}', $work->config['right_panel'], $content); // замена всех упоминаний значения ширины правой панели
		$content = str_replace('{GALLERY_WIDHT}', $work->config['gal_width'], $content); // замена всех упоминаний значения ширины галлереи
		$content = str_replace('{COPYRIGHT_YEAR}', $work->config['copyright_year'], $content); // замена всех на год копирайта
		$content = str_replace('{COPYRIGHT_URL}', $work->config['copyright_url'], $content); // замена всех упоминаний ссылки копирайта
		$content = str_replace('{COPYRIGHT_TEXT}', $work->config['copyright_text'], $content); // замена всех упоминаний текста копирайта
		$content = str_replace('{U_SEARCH}', $work->config['site_url'] . '?action=search', $content); // замена всех ссылки на поиск
		$content = str_replace('{L_SEARCH}', $lang['main_search'], $content); // замена всех упоминаний названия кнопки поиска

		if(get_magic_quotes_gpc()) // если включены магические кавычки, то...
		{
			$content = stripslashes($content); // удаляем все экранирующие слеши
		}

		return $content; // вернуть полученный обработанный шаблон главной страницы
	}

	function create_template($file_name, $array_data)
	{
		$content = file_get_contents($this->themes_path . $file_name); // получение содержимого файла шаблона с именем $file_name
		if (!$content) die ('Error template file!'); // если файла нет - выдать сообщение с ошибкой и остановка скрипта

		while (list($key, $val) = each($array_data)) // обработка полученного массива
		{
			if (substr($key, 0, 3) == 'IF_' && $val == false) // если получена перменная - указатель на условность вывода на экран - название переменной начинается на 'IF_'...
			{
				if ($val == false) // и значение переменной равно false, то...
				{
					$content = mb_ereg_replace('<!-- ' . $key . '_BEGIN -->.*<!-- ' . $key . '_END -->', '', $content); // удаляем из шаблона блок, содержащийся в закоментированном участке вида: <!-- IF_KEY_BEGIN -->блок шаблона<!-- IF_KEY_END -->
				}
				else // иначе...
				{
					$content = str_replace('<!-- ' . $key . '_BEGIN -->', '', $content); // удаляем из шаблона <!-- IF_KEY_BEGIN -->
					$content = str_replace('<!-- ' . $key . '_END -->', '', $content); // удаляем из шаблона <!-- IF_KEY_END -->
				}
			}
			else // иначе
			{
				$content = str_replace('{' . $key . '}', $val, $content); // замена переменных в шаблоне используя массив $array_data
			}
		}

		return $content; // вернуть полученный обработанный файл
	}

	function create_menu($action = 'main', $menu = 0)
	{
		global $db, $work, $lang, $user; // подключаем глобальные массивы и объекты: объект для работы с базой данных ($db), массив настроек сайта ($config), массив языковых переменных ($lang), объект текущего пользователя на сайте ($user)

		$m[0] = 'short'; // формируем массив для
		$m[1] = 'long'; // работы с шаблонами

		$temp_menu = $db->fetch_big_array("SELECT * FROM `menu` WHERE `" . $m[$menu] . "` = '1' ORDER BY `id` ASC"); // запрашиваем из базы пункты меню в зависимости от $menu - краткое или длинное
		$text_menu = ''; // инициируем переменную для хранения кода меню

		if ($temp_menu) // если получены данные для меню...
		{
			for ($i = 1; $i <= $temp_menu[0]; $i++) // цикл обработки каждого пункта меню
			{
				$visible = true; // по умолчанию пункт меню - видим

				if($temp_menu[$i]['user_login'] != '') // если есть указатель условия на то, вошел ли пользователь под своим именем, то...
				{
					if ($temp_menu[$i]['user_login'] == 0 && $user->user['id'] > 0) $visible = false; // если требуется только "Гостям", а пользователь уже онлайн, то скрываем пункт меню
					if ($temp_menu[$i]['user_login'] == 1 && $user->user['id'] == 0) $visible = false; // если пользователь должен быть онлайн, но является "гостем" - скрываем пункт меню
				}

				if ($temp_menu[$i]['user_access'] != '') // если есть указатель на определенные привелегии пользователя, то...
				{
					if($user->user[$temp_menu[$i]['user_access']] != 1) $visible = false; // если указанное значение привлелегии у пользователя отсутствует - скрываем пункт меню
				}

				if($visible) // если разрешено показать пункт меню пользователю, то...
				{
					$array_data = array(); // инициируем массив

					$array_data = array(
							'U_MENU' => $work->config['site_url'] . $temp_menu[$i]['url_action'],
							'L_MENU' => $lang['menu'][$temp_menu[$i]['name_action']]
					); // заполняем массив данными

					if ($temp_menu[$i]['action'] == $action) // проверяем, является ли текущий пункт - активным
					{
						$text_menu .= $this->create_template($m[$menu] . '_menu_txt.tpl', $array_data); // если активный, то обрабатываем через шаблон для активного пункта
					}
					else
					{
						$text_menu .= $this->create_template($m[$menu] . '_menu_url.tpl', $array_data); // иначе через обычным шаблон
					}
				}
			}
		}

		$array_data = array(); // инициируем массив

		$array_data = array (
						'MENU_TEXT' => $text_menu,
						'NAME_BLOCK' => $lang['menu']['name_block']
		); // передаем собранные данные в массив

		$text_menu = $this->create_template($m[$menu] . '_menu.tpl', $array_data); // обрабатываем данные и формируем полный код

		return $text_menu; // возвращаем полностью сформированный код меню
	}

	function create_foto($type = 'top', $id_photo = 0)
	{
		global $db, $work, $lang, $user; // подключаем глобальные массивы и объекты: объект для работы с базой данных ($db), массив настроек сайта ($config), массив языковых переменных ($lang), объект текущего пользователя на сайте ($user)

		if ($user->user['pic_view'] == true) // если пользователь имеет право просматривать изображения, то...
		{
			if ($type == 'top') // если получили top - вывести лучшее...
			{
				$query = "SELECT * FROM `photo` WHERE `rate_user` != 0 ORDER BY `rate_user` DESC LIMIT 1"; // запрос на получение данных по лучшему фото
			}
			elseif ($type == 'last') // если получили last - вывести последнее...
			{
				$query = "SELECT * FROM `photo` ORDER BY `date_upload` DESC LIMIT 1"; // запрос на получение данных по последнему фото
			}
			elseif ($type == 'cat') //если получили cat - вывести для разделов...
			{
                $query = "SELECT * FROM `photo` WHERE `id` = " . $id_photo; // запрос на полчение данных по опеределеному изображению
			}
			else // иначе - случайное фото
			{
				$temp = $db->fetch_big_array("SELECT `id` FROM `photo`"); // получаем массив идентификаторов
				if ($temp) // если есть в наличии идентификаторы, то...
				{
					$id_array = array(); // инициируем одномерный массив идентификаторов
					for($i = 1; $i <= $temp[0]; $i++)
					{
						$id_array[$i] = $temp[$i]['id']; // формируем одномерный массив идентификаторов
					}
						shuffle($id_array); // перемешиваем полученный одномерный массив идентификаторов
				}
				else // иначе...
				{
					$id_array[0] = 0; // присваиваем нулевому элементу значение "0"
				}
				$query = "SELECT * FROM `photo` WHERE `id` = " . $id_array[0]; // запрос на получение данных по случайной фотографии (используя 0-вой элемент перемешанного одномерного массива идентификаторов)
			}
			$temp_foto = $db->fetch_array($query); // отправляем сформированный ранее запрос
		}
		else
		{
			$temp_foto = false; // иначе запрет на вывод изображения
		}

		$name_block = $lang['main_' . $type . '_foto']; // формируем заголовок окна в зависимости от полученного значения $type

		if ($temp_foto) // если запрос выполнился без ошибок, то...
		{
			$temp_category = $db->fetch_array("SELECT * FROM `category` WHERE `id` = " . $temp_foto['category']); // запрос на получение данных о категории, где хранится фото
			if ($temp_category) // если запрос выполнился без ошибок, то...
			{
				$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_category['folder'] . '/' . $temp_foto['file']; // формируем путь к изображению
				$foto['url'] = $work->config['site_url'] . '?action=photo&id=' . $temp_foto['id']; // формируем ссылку на изображение
				$foto['thumbnail_url'] = $work->config['site_url'] . '?action=attach&foto=' . $temp_foto['id'] . '&thumbnail=1'; // формируем ссылку для вывода эскиза изображения
				$foto['name'] = $temp_foto['name']; // формируем имя фото
				$foto['category_name'] = $temp_category['name']; // формируем название раздела
				$foto['description'] = $temp_foto['description']; // формируем описание фото
				$foto['category_description'] = $temp_category['description']; // формируем описание раздела
				$foto['rate'] = $lang['main_rate'] . ': ' . $temp_foto['rate_user'] . '/' . $temp_foto['rate_moder']; // формируем ввыод оценки фото ввиде: Оценка: оценка пользователей/оценка модераторов
				$user_add = $db->fetch_array("SELECT `real_name` FROM `user` WHERE `id` = " . $temp_foto['user_upload']); // получаем данные об отображаемом имени пользователя, разместившего данное фото
				if ($user_add) // если пользователь существует, то...
				{
					$foto['user'] = $lang['main_user_add'] . ': <a href="' . $work->config['site_url']  . '?action=login&subact=profile&uid=' . $temp_foto['user_upload'] . '" title="' . $user_add['real_name'] . '">' . $user_add['real_name'] . '</a>'; // формируем имя пользователя с ссылкой на его профиль
				}
				else // иначе
				{
					$foto['user'] = $lang['main_no_user_add']; // указываем, что данный пользователь не существует
				}
				if($temp_category['id'] == 0) // если раздел является пользовательским альбомом, то..
				{
					$foto['category_name'] = $temp_category['name'] . ' ' . $user_add['real_name']; // к названию раздела добавляем имя пользователя - владельца персонального альбома
					$foto['category_description'] = $foto['category_name']; // устанавливаем в качестве описания - имя раздела
					$foto['category_url'] = $work->config['site_url'] . '?action=category&cat=user&id=' . $temp_foto['user_upload']; // формируем ссылку для перехода на пользовательский раздел
				}
				else
				{
					$foto['category_url'] = $work->config['site_url'] . '?action=category&cat=' . $temp_category['id']; // иначе формируем ссылку на переход в обычный раздел
				}
			}
			else // иначе формируем вывод для несуществующего фото
			{
				$temp_foto['file'] = 'no_foto.png'; // меняем имя файла
				$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file']; // прописываем путь к новому файлу
				$foto['url'] = $work->config['site_url'] . '?action=photo&id=0'; // формируем ссылку на изображение
				$foto['thumbnail_url'] = $work->config['site_url'] . '?action=attach&foto=0&thumbnail=1'; // формируем ссылку для вывода эскиза изображения
				$foto['name'] = $lang['main_no_foto']; // название - НЕТ ФОТО
				$foto['description'] = $lang['main_no_foto']; // описание - НЕТ ФОТО
				$foto['category_name'] = $lang['main_no_category']; // название раздела - НЕТ РАЗДЕЛА
				$foto['category_description'] = $lang['main_no_category']; // описание раздела - НЕТ РАЗДЕЛА
				$foto['rate'] = $lang['main_rate'] . ': ' . $lang['main_no_foto']; // Оценка: НЕТ ФОТО
				$foto['user'] = $lang['main_no_user_add']; // пользователь - не существует
				$foto['category_url'] = $work->config['site_url']; // ссылка на категорию заменяется ссылкой на главную страницу
			}
		}
		else // иначе формируем вывод для несуществующего фото
		{
			$temp_foto['file'] = 'no_foto.png'; // меняем имя файла
			$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file']; // прописываем путь к новому файлу
			$foto['url'] = $work->config['site_url'] . '?action=photo&id=0'; // формируем ссылку на изображение
			$foto['thumbnail_url'] = $work->config['site_url'] . '?action=attach&foto=0&thumbnail=1'; // формируем ссылку для вывода эскиза изображения
			$foto['name'] = $lang['main_no_foto']; // название - НЕТ ФОТО
			$foto['description'] = $lang['main_no_foto']; // описание - НЕТ ФОТО
			$foto['category_name'] = $lang['main_no_category']; // название раздела - НЕТ РАЗДЕЛА
			$foto['category_description'] = $lang['main_no_category']; // описание раздела - НЕТ РАЗДЕЛА
			$foto['rate'] = $lang['main_rate'] . ': ' . $lang['main_no_foto']; // Оценка: НЕТ ФОТО
			$foto['user'] = $lang['main_no_user_add']; // пользователь - не существует
			$foto['category_url'] = $work->config['site_url']; // ссылка на категорию заменяется ссылкой на главную страницу
		}

		if(!@fopen($temp_path, 'r')) // проверяем доступность файла, если не доступен, то формируем замену для несуществующего фото
		{
			$temp_foto['file'] = 'no_foto.png'; // меняем имя файла
			$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file']; // прописываем путь к новому файлу
			$foto['url'] = $work->config['site_url'] . '?action=photo&id=0'; // формируем ссылку на изображение
			$foto['thumbnail_url'] = $work->config['site_url'] . '?action=attach&foto=0&thumbnail=1'; // формируем ссылку для вывода эскиза изображения
			$foto['name'] = $lang['main_no_foto']; // название - НЕТ ФОТО
			$foto['description'] = $lang['main_no_foto']; // описание - НЕТ ФОТО
			$foto['category_name'] = $lang['main_no_category']; // название раздела - НЕТ РАЗДЕЛА
			$foto['category_description'] = $lang['main_no_category']; // описание раздела - НЕТ РАЗДЕЛА
			$foto['rate'] = $lang['main_rate'] . ': ' . $lang['main_no_foto']; // Оценка: НЕТ ФОТО
			$foto['user'] = $lang['main_no_user_add']; // пользователь - не существует
			$foto['category_url'] = $work->config['site_url']; // ссылка на категорию заменяется ссылкой на главную страницу
		}

		$size = getimagesize($temp_path); // получаем размеры файла

		if ($work->config['temp_photo_w'] == '0') // если ширина вывода не ограничена...
		{
			$ratioWidth = 1; // коэффициент изменения размера по ширине приравниваем 1
		}
		else
		{
			$ratioWidth = $size[0]/$work->config['temp_photo_w']; // иначе рассчитываем этот коэффициент
		}

		if ($work->config['temp_photo_h'] == '0') // если высота вывода не ограничена...
		{
			$ratioHeight = 1;  // коэффициент изменения размера по высоте приравниваем 1
		}
		else
		{
			$ratioHeight = $size[1]/$work->config['temp_photo_h']; // иначе рассчитываем этот коэффициент
		}

		if($size[0] < $work->config['temp_photo_w'] && $size[1] < $work->config['temp_photo_h'] && $work->config['temp_photo_w'] != '0' && $work->config['temp_photo_h'] != '0') // если размеры изображения соответствуют или ограничения отсутствуют, то...
		{
			$foto['width'] = $size[0]; // выводимая ширина равна ширине изображения
			$foto['height'] = $size[1]; // выводимая высота равна высоте изображения
		}
		else // иначе...
		{
			if($ratioWidth < $ratioHeight) // если высота больше ширины, то...
			{
				$foto['width'] = $size[0]/$ratioHeight; // выводимая ширина рассчитываается по высоте изображения
				$foto['height'] = $size[1]/$ratioHeight; // выводимая высота рассчитываается по высоте изображения
			}
			else // иначе...
			{
				$foto['width'] = $size[0]/$ratioWidth; // выводимая ширина рассчитываается по ширине изображения
				$foto['height'] = $size[1]/$ratioWidth; // выводимая высота рассчитываается по ширине изображения
			}
		}

		$array_data = array(); // инициируем массив

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
		); // передаем в массив собранные данные по изображению

			if ($type == 'cat') //если получили cat - формируем и отдаем полный HTML-код для вывода фото для разделов
			{
				return $this->create_template('mini_foto_category.tpl', $array_data);
			}
			else // формируем и отдаем полный HTML-код для вывода лучшего, последнего или случайного фото
			{
				return $this->create_template('mini_foto.tpl', $array_data);
			}
	}

	function template_user()
	{
		global $db, $lang, $work, $user; // подключаем глобальные массивы и объекты: объект для работы с базой данных ($db), массив языковых переменных ($lang), массив настроек сайта ($config), объект текущего пользователя на сайте ($user)

		if ($_SESSION['login_id'] == 0) // если пользователь НЕ онлайн, то...
		{
			$array_data = array(); // инициируем массив

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
			); // наполняем массив данными

			return $this->create_template('login_user.tpl', $array_data); // возвращаем блок для входа или регистрации пользователя
		}
		else // иначе если пользователь онлайн, то...
		{
			$array_data = array(); // инициируем массив

			$array_data = array(
					'NAME_BLOCK' => $lang['main_user_block'],
					'L_HI_USER' => $lang['main_hi_user'] . ', ' . $user->user['real_name'],
					'L_GROUP' => $lang['main_group'] . ': ' . $user->user['group'],

					'U_AVATAR' => $work->config['site_url'] . $work->config['avatar_folder'] . '/' . $user->user['avatar']
			); // наполняем массив данными

			return $this->create_template('profile_user.tpl', $array_data); // возвращаем краткую информацию о пользователе
		}
	}

	function template_news($news_data = 1, $act='last')
	{
		global $db, $lang, $work, $user; // подключаем глобальные массивы и объекты: объект для работы с базой данных ($db), массив языковых переменных ($lang), массив настроек сайта ($config), объект текущего пользователя на сайте ($user)

		$news['IF_EDIT_LONG'] = false; //спорно - есть ли необходимость в таком выводе, но зарезервировано

		if($act == 'id') // если требуется вывести определенную новость, то...
		{
			$temp_news = $db->fetch_big_array("SELECT * FROM `news` WHERE `id` = " . $news_data); // получаем данные по этой новости
		}
		else // иначе...
		{
			$temp_news = $db->fetch_big_array("SELECT * FROM `news` ORDER BY `data_last_edit` DESC LIMIT 0 , " . $news_data); // получаем данные по $news_data последним новостям (список берется по дате последнего редактирования)
		}

		if ($temp_news && $user->user['news_view'] == true) // если есть данные о новостях и если пользователь имеет право просматривать новости, то...
		{
			$result = ''; // инициируем переменную для хранения новостей
			for ($i = 1; $i <= $temp_news[0]; $i++) // запускаем цикл по обработке списка новостей
			{
				$news['NAME_BLOCK'] = $lang['main_title_news'] . ' - ' . $temp_news[$i]['name_post']; // формируем название блока в виде Новость - Название новости
				$news['L_NEWS_DATA'] = $lang['main_data_add'] . ': ' . $temp_news[$i]['data_post'] . ' (' . $temp_news[$i]['data_last_edit'] . ').'; // формирует вывод данных о дате публикации и последнего редактирования новости
				$news['L_TEXT_POST'] = $temp_news[$i]['text_post']; // формируем текст самой новости
				$user_add = $db->fetch_array("SELECT `real_name` FROM `user` WHERE `id` = " . $temp_news[$i]['user_post']); // делаем запрос на отобржаемое имя автора ноости
				if ($user_add) // если автор существует, то...
				{
					$news['L_NEWS_DATA'] .= '<br />' . $lang['main_user_add'] . ': <a href="' . $work->config['site_url']  . '?action=login&subact=profile&uid=' . $temp_news[$i]['user_post'] . '" title="' . $user_add['real_name'] . '">' . $user_add['real_name'] . '</a>.'; // добавляем данные об автаре, разместившем новость с указанием ссылки на его профиль
				}
				$news['L_TEXT_POST'] = trim(nl2br($news['L_TEXT_POST'])); // обрабатываем текст новости - удаляем пробелы в начале и конце, заменяем символы перевода строки тэгами перевода строки

				if ($user->user['news_moderate'] == true || ($user->user['id'] != 0 && $user->user['id'] == $temp_news[$i]['user_post'])) // если пользователь имеет право на редактирование ноости или является её автором, то...
				{
					// заполняем данные для кнопок и ссылок по редактированию или удалению новости
					$news['L_EDIT_BLOCK'] = $lang['main_edit_news'];
					$news['L_DELETE_BLOCK'] = $lang['main_delete_news'];
					$news['L_CONFIRM_DELETE_BLOCK'] = $lang['main_confirm_delete_news'] . ' ' . $temp_news[$i]['name_post'] . '?';
					$news['U_EDIT_BLOCK'] = $work->config['site_url'] . '?action=news&subact=edit&news=' . $temp_news[$i]['id'];
					$news['U_DELETE_BLOCK'] = $work->config['site_url'] . '?action=news&subact=delete&news=' . $temp_news[$i]['id'];
					$news['IF_EDIT_SHORT'] = true; // разрешаем вывод в шаблоне данных кнопок
				}
				else // иначе все значения равны пустуым строкам и...
				{
					$news['L_EDIT_BLOCK'] = '';
					$news['L_DELETE_BLOCK'] = '';
					$news['L_CONFIRM_DELETE_BLOCK'] = '';
					$news['U_EDIT_BLOCK'] = '';
					$news['U_DELETE_BLOCK'] = '';
					$news['IF_EDIT_SHORT'] = false; // запрещаем вывод кнопок редактирования и удаления
				}
				$result .= $this->create_template('news.tpl', $news); // добавляем к текущему списку новостей очередную обработанную новость
			}
		}
		else // иначе
		{
			$news['NAME_BLOCK'] = $lang['main_no_news']; // присваиваем НЕТ НОВОСТЕЙ
			$news['L_NEWS_DATA'] = ''; // указываем пустую строку по информации о новости
			$news['L_TEXT_POST'] = $lang['main_no_news']; // присваиваем НЕТ НОВСТЕЙ
			$news['L_TEXT_POST'] = trim(nl2br($news['L_TEXT_POST'])); // дополнительно обрабатываем вывод текста новости
			$news['IF_EDIT_SHORT'] = false;
			$result = $this->create_template('news.tpl', $news); // формируем пстой вывод новости
		}

		return $result; // возвращаем полностью сформированный список ноостей
	}

	function template_stat()
	{
		global $db, $lang, $work; // подключаем глобальные массивы и объекты: объект для работы с базой данных ($db), массив языковых переменных ($lang), массив настроек сайта ($config)

		$temp = $db->num_rows("SELECT `id` FROM `user`"); // получаем информацию о кол-ве пользователей на сайте
		if($temp) // если информация есть, то...
		{
			$stat['regist'] = $temp; // наполняем массив статистики данными о кол-ве зарегистрированных пользователей
		}
		else // иначе
		{
			$stat['regist'] = 0; // зарегистрировано 0 пользователей
		}

		$temp = $db->num_rows("SELECT `id` FROM `photo`"); // получаем информацию о кол-ве изображений на сайте
		if($temp) // если информация есть, то...
		{
			$stat['photo'] = $temp; // наполняем массив статистики данными о кол-ве загруженных изображений
		}
		else // иначе...
		{
			$stat['photo'] = 0; // загружено 0 изображений
		}

		$temp = $db->num_rows("SELECT `id` FROM `category` WHERE `id` !=0"); // получаем данные о кол-ве НЕ пользовательских разделов на сайте
		if($temp) // если данные есть, то...
		{
			$stat['category'] = $temp; // наполняем массив статистики данными о кол-ве НЕ пользовательских разделов
		}
		else // иначе...
		{
			$stat['category'] = 0; // НЕ пользовательских разделов на сайте нет
		}
		$temp = $db->num_rows("SELECT DISTINCT `user_upload` FROM `photo` WHERE `category` = 0"); // подсчитываем кол-во пользовательских альбомов на сайте
		if($temp) // если данные есть, то...
		{
			$stat['category_user'] = $temp; // наполняем массив статистики данными о кол-ве пользовательских альбомов
		}
		else // иначе
		{
			$stat['category_user'] = 0; // пользовательских альбомов на сайте 0
		}
		$stat['category'] = $stat['category'] + $stat['category_user']; // сумируем кол-во пользовательских альбомов и НЕ пользовательских разделов и получаем общее число разделов

		$temp = $db->num_rows("SELECT `id` FROM `user` WHERE `group` =3"); // получаем данных о кол-ве пользователей в группе Администратор
		if($temp) // если данные есть, то...
		{
			$stat['user_admin'] = $temp; // наполняем массив статистики данными о кол-ве Администраторов
		}
		else // иначе...
		{
			$stat['user_admin'] = 0; // на сайте 0 Администраторов
		}

		$temp = $db->num_rows("SELECT `id` FROM `user` WHERE `group` =2"); // получаем данных о кол-ве пользователей в группе Преподаватель (модератор)
		if($temp) // если данные есть, то...
		{
			$stat['user_moder'] = $temp; // наполняем массив статистики данными о кол-ве Преподаватель (модератор)
		}
		else // иначе
		{
			$stat['user_moder'] = 0; // на сайте 0 Преподавателей (модераторов)
		}

		$temp = $db->num_rows("SELECT * FROM `rate_user`"); // получаем данных о кол-ве оценок, поставленных пользователями
		if($temp) // если данные есть, то...
		{
			$stat['rate_user'] = $temp; // наполняем массив статистики данными о кол-ве оценок, поставленных пользователями
		}
		else // иначе...
		{
			$stat['rate_user'] = 0; // пользователи оставили 0 оценок
		}

		$temp = $db->num_rows("SELECT * FROM `rate_moder`"); // получаем данных о кол-ве оценок, поставленных преподавателями (модераторами)
		if($temp) // если данные есть, то...
		{
			$stat['rate_moder'] = $temp; // наполняем массив статистики данными о кол-ве оценок, поставленных  преподавателями (модераторами)
		}
		else // иначе...
		{
			$stat['rate_moder'] = 0; //  преподаватели (модераторы) оставили 0 оценок
		}

		$temp = $db->fetch_big_array("SELECT `id`, `real_name` FROM `user` WHERE `date_last_activ` >= (CURRENT_TIMESTAMP - 900 )"); // получаем данные об активности зарегистрированных пользователей за последнии 900 секунд (15 минут)
		if($temp || $temp[0] > 0) // если есть такие пользователи, то...
		{
			$stat['online'] =''; // инициируем список онлайн-пользователей
			for($i=1; $i<=$temp[0]; $i++) // обрабатываем список активных пользователей
			{
				$stat['online'] .= '<a href="' . $work->config['site_url']  . '?action=login&subact=profile&uid=' . $temp[$i]['id'] . '" title="' . $temp[$i]['real_name'] . '">' . $temp[$i]['real_name'] . '</a>'; // формируем ссылку на профиль активного пользователя с выводом его отображаемого имени
				if ($i < $temp[0]) $stat['online'] .= ', '; // если это НЕ последний пользователь, то добавляем запятую
				if ($i == $temp[0]) $stat['online'] .= '.'; // если последний, то точку
			}
		}
		else // иначе
		{
			$stat['online'] = $lang['main_stat_no_online']; // сообщаем, что нет активных зарегистрированных пользователей на сайте
		}

			$array_data = array(); // инициируем массив

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
			); // наполняем массив данными статистики

			return $this->create_template('stat.tpl', $array_data); // формируем и передаем блок ститистики
	}

	function template_best_user($best_user = 1)
	{
		global $db, $lang, $work; // подключаем глобальные массивы и объекты: объект для работы с базой данных ($db), массив языковых переменных ($lang), массив настроек сайта ($config)

		$temp = $db->fetch_big_array("SELECT DISTINCT `user_upload` FROM `photo`"); // получаем данные о заливавших фото пользователях
		$name_block = $lang['main_best_user_1'] . $best_user . $lang['main_best_user_2']; // формируем название блока в стиле "кол-во лучших пользователей"
		if($temp) // если такие данные есть, то...
		{
			$best_user_array = array(); // инициируем масиив лучших пользователей
			for ($i = 1; $i <= $temp[0]; $i++) // обрабатываем полученные идентификаторы пользователей, заливших фото на сайт
			{
				$temp2 = $db->fetch_array("SELECT COUNT(`id`) AS `user_photo` FROM `photo` WHERE `user_upload` = " . $temp[$i]['user_upload']); // подсчитываем число изображений, залитых пользователем
				if($temp2)
				{
					$temp[$i]['user_photo'] = $temp2['user_photo']; // если такие данные есть, то присваиваем их в массив
				}
				else // иначе
				{
					$temp[$i]['user_photo'] = 0; // число залитых изображений равно 0
				}

				$temp2 = $db->fetch_array("SELECT `real_name` FROM `user` WHERE `id` = " . $temp[$i]['user_upload']); // запрашиваем отображаемое имя пользователя
				if(!$temp2) // если таких данных НЕТ, то...
				{
					$temp[$i]['user_photo'] = 0; // кол-во залитых изображений равно 0 (защита от удаленных пользователей)
				}
				$best_user_array[$temp[$i]['user_upload']] = $temp[$i]['user_photo']; // формируем массив, где ключ равен идентификатору пользователя, а значение - кол-ву залитых им изображений
			}
			arsort($best_user_array); // сортируем массив в порядке убывания кол-ва залитых изображений
			$text_best_user = ''; // инициируем переменную для вывода списка лучших пользователей
			if (count($best_user_array) < $best_user) $best_user = count($best_user_array);
			reset($best_user_array); // если число пользователей, заливших изображение меньше требуемого, то выведем только их
			for ($i = 1; $i <= $best_user; $i++) // цикл обработки лучших пользователей
			{
				list($best_user_name, $best_user_photo) = each($best_user_array); // выносим из масива ключ и значение
				$temp2 = $db->fetch_array("SELECT `real_name` FROM `user` WHERE `id` = " . $best_user_name); // по ключу получаем отображаемое имя пользователя
				$array_data = array(); // инициируем массив
				$array_data = array(
						'D_USER_NAME' => '<a href="' . $work->config['site_url']  . '?action=login&subact=profile&uid=' . $best_user_name . '" title="' . $temp2['real_name'] . '">' . $temp2['real_name'] . '</a>',
						'D_USER_PHOTO' => $best_user_photo
				); // наполняем мссив данными о пользователе с ссылкой на его профиль + кол-во залитых им изображений
				$text_best_user .= $this->create_template('best_user.tpl', $array_data); // пополняем массив очередным пользователем
			}
		}
		else // иначе...
		{
			$array_data = array(); // инициируем массив
			$array_data = array(
					'D_USER_NAME' => '---',
					'D_USER_PHOTO' => '-'
			); // вносим в него пометку об отсутствии пользователей, заливших изображение
			$text_best_user = $this->create_template('best_user.tpl', $array_data); // формируем пустой список
		}
		$array_data = array(); // инициируем массив
		$array_data = array(
				'NAME_BLOCK' => $name_block,
				'L_USER_NAME' => $lang['main_user_name'],
				'L_USER_PHOTO' => $lang['main_best_user_photo'],

				'TEXT_BEST_USER' => $text_best_user
		); // наполняем мссив полученными ранее данными
		return $this->create_template('best.tpl', $array_data); // формируем и выводим блок лучших пользователей
	}

	function template_rate($if_who = 'user', $rate = 'false')
	{
		global $lang, $work; // подключаем глобальные массивы: массив языковых переменных ($lang), массив настроек сайта ($config)

		if ($rate == '') $rate = 'false'; // если нет данных о голое пользователя, то указываем, что пользователь должен проголосовать

		$array_data = array(); // инициируем массив
		$array_data['L_IF_RATE'] = $lang['photo_if_' . $if_who]; // указываем, кто голосует - пользователь или преподаватель (модератор)

		if($rate == 'false') // если НЕ голосовал, то...
		{
            $array_data['D_IF_RATE'] = '<select name="rate_' . $if_who . '">'; // формируем список вариантов голоса
            for ($i=-$work->config['max_rate']; $i<=$work->config['max_rate']; $i++) // для варианта от - максимальная оценка до + максимальная оценка (максимальная оценка берется из настроек сайта)
            {
				if($i == 0) $selected = ' selected'; else $selected = ''; // указываем, что по умолчанию выбрана оценка с значением 0
				$array_data['D_IF_RATE'] .= '<option value="' . $i . '"' . $selected . '>' . $i . '</option>'; // формируем пункт списка
			}
			$array_data['D_IF_RATE'] .= '</select>'; // закрываем список
		}
		else // иначе...
		{
		    $array_data['D_IF_RATE'] = $rate; // выводим результат голоса
		}

		return $this->create_template('rate_blank.tpl', $array_data); // возвращаем сформированный фрагмент голосования
	}

	function Image_Attach($full_path, $name_file)
	{
		$size = getimagesize($full_path); // получаем данные об изображении

		header("Content-Type: " . $size['mime']); // передаем в заголовок - MIME-тип файла
		header("Content-Disposition: inline; filename=\"" . $name_file . "\""); // передаем в заголовке имя файла
		header("Content-Length: " . (string)(filesize($full_path))); // передаем в заголовке размер файла

		flush(); // инициируем вывод

		$fh = fopen($full_path, 'rb'); // открываем файл для бинарного чтения
		fpassthru($fh); // выводим содержимое файла
		fclose($fh); // закрываем файл
		exit(); // выходим из скрипта
	}

	function Image_Resize($full_path, $thumbnail_path)
	{
		global $work; // подключаем глобальный массив настроек сайта

		$thumbnail_size = @getimagesize($thumbnail_path); // получаем размеры файла эскиза
		$full_size = getimagesize($full_path); // получаем размеры файла оригинала
		$foto['type'] = $full_size[2]; // тип изображения берем из файла оригинала

		if ($work->config['temp_photo_w'] == '0') // если ширина вывода не ограничена...
		{
			$ratioWidth = 1; // коэффициент изменения размера по ширине приравниваем 1
		}
		else
		{
			$ratioWidth = $full_size[0]/$work->config['temp_photo_w']; // иначе рассчитываем этот коэффициент
		}

		if ($work->config['temp_photo_h'] == '0') // если высота вывода не ограничена...
		{
			$ratioHeight = 1;  // коэффициент изменения размера по высоте приравниваем 1
		}
		else
		{
			$ratioHeight = $full_size[1]/$work->config['temp_photo_h']; // иначе рассчитываем этот коэффициент
		}

		if($full_size[0] < $work->config['temp_photo_w'] && $full_size[1] < $work->config['temp_photo_h'] && $work->config['temp_photo_w'] != '0' && $work->config['temp_photo_h'] != '0') // если размеры изображения соответствуют или ограничения отсутствуют, то...
		{
			$foto['width'] = $full_size[0]; // выводимая ширина равна ширине изображения
			$foto['height'] = $full_size[1]; // выводимая высота равна высоте изображения
		}
		else // иначе...
		{
			if($ratioWidth < $ratioHeight) // если высота больше ширины, то...
			{
				$foto['width'] = $full_size[0]/$ratioHeight; // выводимая ширина рассчитываается по высоте изображения
				$foto['height'] = $full_size[1]/$ratioHeight; // выводимая высота рассчитываается по высоте изображения
			}
			else // иначе...
			{
				$foto['width'] = $full_size[0]/$ratioWidth; // выводимая ширина рассчитываается по ширине изображения
				$foto['height'] = $full_size[1]/$ratioWidth; // выводимая высота рассчитываается по ширине изображения
			}
		}

		if ($thumbnail_size[0] != $foto['width'] || $thumbnail_size[1] != $foto['height']) // если размер эскиза не соответствует требуемым, то...
		{
			switch($foto['type']) // согласно типа файла оригинала открываем его как...
			{
				case "1":
					$imorig = imagecreatefromgif($full_path); // gif-файл
					break;
				case "2":
					$imorig = imagecreatefromjpeg($full_path); // jpeg-файл
					break;
				case "3":
					$imorig = imagecreatefrompng($full_path); // png-файл
					break;
				default:
					$imorig = imagecreatefromjpeg($full_path); // если не определен тип, то открываем как jpeg-файл
			}
			$im = imagecreatetruecolor($foto['width'], $foto['height']); // создаем пустой макет
			if (imagecopyresampled($im, $imorig , 0, 0, 0, 0, $foto['width'], $foto['height'], $full_size[0], $full_size[1])) // если удалось сжать исходный файл в указанный макет, то...
			{
				@unlink($thumbnail_path); // удаляем текущий эскиз, если он есть...

				switch($foto['type']) // сохраняем новый эских в зависимости от типа фала оригианала, как...
				{
					case "1":
						imagegif($im, $thumbnail_path); // gif-файл
						break;
					case "2":
						imagejpeg($im, $thumbnail_path); // jpeg-файл
						break;
					case "3":
						imagepng($im, $thumbnail_path); // png-файл
						break;
					default:
						imagejpeg($im, $thumbnail_path); // если не определен тип, то сохраняем как jpeg-файл
				}
				return true; // возвращаем, что создание эскиза успешно завершено
			}
			return false; // возвращаем, что эскиз НЕ создан
		}
		else // иначе...
		{
			return true; // возвращаем, что эскиз НЕ создан
		}
	}
}
?>
