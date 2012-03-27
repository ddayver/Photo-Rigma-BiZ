<?php
/**
* @file		action/search.php
* @brief	Поиск по сайту.
* @author	Dark Dayver
* @version	0.1.1
* @date		27/03-2012
* @details	Обработка поисковых запросов.
*/

// Проверка, что файл подключается из индексного, а не набран напрямую в адресной строке
if (IN_GALLERY)
{
	die('HACK!');
}

include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/main.php'); // подключаем языковый файл основной страницы
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/menu.php'); // подключаем языковый файл меню
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/search.php'); // подключаем языковый файл поиска

if(isset($_POST['search_main_text']) && !empty($_POST['search_main_text']) && empty($_POST['search_text'])) // если запрос поступил с шапки сайта и нет данных с самой страницы поиска, то...
{
	$_POST['search_text'] = $_POST['search_main_text']; // указываем текст для поиска
	$_POST['search_user'] = 'true'; // включаем поиск по пользователям
	$_POST['search_category'] = 'true'; // включаем поиск по разделам
	$_POST['search_news'] = 'true'; // включаем поиск по ноостям
	$_POST['search_photo'] = 'true'; // включаем поиск по изображениям
}

// инициируем служебные переменные
$check = array(); // данные о включенных чек-боксах
$search_user = false; // не искать в пользователях
$search_category = false; // не искать в разделах
$search_news = false; // не искать в новостях
$search_photo = false; // не искать в изображениях
$find_data = array(); // найденные данные

if(!empty($_POST['search_user']) && $_POST['search_user'] == 'true' && !empty($_POST['search_text'])) // если есть отметка о поиске в пользователях и НЕ пустой текст поиска, то..
{
	$search_user = true; // включаем поиск в пользователях
	$check['user'] = 'checked'; // отмечаем это в массиве
}

if(!empty($_POST['search_category']) && $_POST['search_category'] == 'true' && !empty($_POST['search_text'])) // если есть отметка о поиске в разделах и НЕ пустой текст поиска, то..
{
	$search_category = true; // включаем поиск в разделах
	$check['category'] = 'checked'; // отмечаем это в массиве
}

if(!empty($_POST['search_news']) && $_POST['search_news'] == 'true' && !empty($_POST['search_text'])) // если есть отметка о поиске в новостях и НЕ пустой текст поиска, то..
{
	$search_news = true; // включаем поиск в новостях
	$check['news'] = 'checked'; // отмечаем это в массиве
}

if(!empty($_POST['search_photo']) && $_POST['search_photo'] == 'true' && !empty($_POST['search_text'])) // если есть отметка о поиске в изображениях и НЕ пустой текст поиска, то..
{
	$search_photo = true; // включаем поиск в изображениях
	$check['photo'] = 'checked'; // отмечаем это в массиве
}

if (!($search_user || $search_category || $search_news || $search_photo)) $check['photo'] = 'checked'; // если нет запроса на поиск (просто открыта изначально страница поиска), то указываем, что по-умолчанию поиск производить в изображениях

$array_data = array(); // инициируем массив

if($search_user) // если включен поиск по пользователям, то...
{
	$find_data['l_search_user'] = $lang['search_find'] . ' ' . $lang['search_need_user']; // формируем название блока результатов поиска по пользователям

	$find = $db->fetch_big_array("SELECT * FROM `user` WHERE `real_name` LIKE '%" . $_POST['search_text'] . "%'"); // делаем запрос на поиск пользователей, в отображаемом имени которых содержится искомая строка

	if($find && $find[0] > 0) // если найдены такие пользователи, то...
	{
		$find_data['d_search_user'] = ''; // инициируем мссив списка пользователей
		for($i = 1; $i <= $find[0]; $i++) // обрабатываем найденных пользователей по списку
		{
			$find_data['d_search_user'] .= '<a href="' . $work->config['site_url']  . '?action=login&subact=profile&uid=' . $find[$i]['id'] . '" title="' . $find[$i]['real_name'] . '">' . $find[$i]['real_name'] . '</a>'; // формируем список, выводя на экран отображаемое имя пользователя ввиде ссылки на профиль
			if ($i < $find[0]) $find_data['d_search_user'] .= ', '; // если НЕ последний пользователь, ставим после него запятую
			if ($i == $find[0]) $find_data['d_search_user'] .= '.'; // если последний - точку
		}
	}
	else // иначе если пользователи не найдены, то...
	{
		$find_data['d_search_user'] = $lang['search_no_find']; // сообщаем об этом пользователю
	}
}

if($search_category) // если включен поиск по атегориям, то...
{
	$find_data['l_search_category'] = $lang['search_find'] . ' ' . $lang['search_need_category']; // формируем название блока - результаты поиска по разделам

	$find = $db->fetch_big_array("SELECT * FROM `category` WHERE `id` !=0 AND (`name` LIKE '%" . $_POST['search_text'] . "%' OR `description` LIKE '%" . $_POST['search_text'] . "%')"); // поиск разделов, в названии или описании которых встречается искомая строка (кроме пользовательских)

	if($find && $find[0] > 0) // если такие разделы найдены, то формируем из них список
	{
		$find_data['d_search_category'] = ''; // инициируем список найденных разделов
		for($i = 1; $i <= $find[0]; $i++) // обрабатываем в цикле данный список
		{
			$find_data['d_search_category'] .= '<a href="' . $work->config['site_url']  . '?action=category&cat=' . $find[$i]['id'] . '" title="' . $find[$i]['description'] . '">' . $find[$i]['name'] . '</a>'; // формируем ссылку типа "Название раздела" и всплывающей подсказкой - описание раздела
			if ($i < $find[0]) $find_data['d_search_category'] .= ', '; // если раздел не последний - ставим после него запятую
			if ($i == $find[0]) $find_data['d_search_category'] .= '.'; // если последний - точку
		}
	}
	else // иначе если не надйен ни один раздел, то...
	{
		$find_data['d_search_category'] = $lang['search_no_find']; // сообщаем об этом порльзователю
	}
}

if($search_news) // если включен поиск по новостям
{
	$find_data['l_search_news'] = $lang['search_find'] . ' ' . $lang['search_need_news'];  // формируем заголовок блока - результаты поиска по новостям

	$find = $db->fetch_big_array("SELECT *  FROM `news` WHERE `name_post` LIKE '%" . $_POST['search_text'] . "%' OR `text_post` LIKE '%" . $_POST['search_text'] . "%'"); // делаем поиск новостей, в названии которых или тексте встречается искомая строка

	if($find && $find[0] > 0) // если найдены такие новости, то формируем список новостей
	{
		$find_data['d_search_news'] = ''; // инициируем список новостей
		for($i = 1; $i <= $find[0]; $i++) // обрабатываем найденные новости по циклу
		{
			$find_data['d_search_news'] .= '<a href="' . $work->config['site_url']  . '?action=news&news=' . $find[$i]['id'] . '" title="' . substr($find[$i]['text_post'], 0, 100) . '">' . $find[$i]['name_post'] . '</a>'; // формируем ссылку типа "Название новости" и во всплывающей подсказке - первые 100 символов новости
			if ($i < $find[0]) $find_data['d_search_news'] .= ', '; // если новость не последняя - ставим запятую
			if ($i == $find[0]) $find_data['d_search_news'] .= '.'; // если последняя - точка
		}
	}
	else // иначе если не надйена ни одна новость, то...
	{
		$find_data['d_search_news'] = $lang['search_no_find']; // сообщаем об этом пользователю
	}
}

if($search_photo) // если включен поис по изображениям
{
	$find_data['l_search_photo'] = $lang['search_find'] . ' ' . $lang['search_need_photo']; // формируем название блока - результаты поиска по изображениям

	$find = $db->fetch_big_array("SELECT `id` FROM `photo` WHERE `name` LIKE '%" . $_POST['search_text'] . "%' OR `description` LIKE '%" . $_POST['search_text'] . "%'"); // производим поиск изображений в названии или описании которых встречается искомая строка

	if($find && $find[0] > 0) // если есть такие изображения, то...
	{
		$find_data['d_search_photo'] = ''; // инициируем строку результатов поиска по изорбражениям
		for($i = 1; $i <= $find[0]; $i++) // обрабатываем в цикле найденные изображения
		{
			$find_data['d_search_photo'] .= $template->create_foto('cat', $find[$i]['id']); // формируем фрагменты каждого изображения в стиле вывода изображений в разделах
		}
	}
	else // если не найдено ни одного изображения, то...
	{
		$find_data['d_search_photo'] = $lang['search_no_find']; // сообщим об этом пользователю
	}
}

if (isset($_POST['search_text'])) $_POST['search_text'] = htmlspecialchars($_POST['search_text'], ENT_QUOTES);

$array_data = array(
			'NAME_BLOCK' => $lang['main_search'],
			'L_SEARCH' => $lang['main_search'],
			'L_SEARCH_TITLE' => $lang['search_title'],
			'L_NEED_USER' => $lang['search_need_user'],
			'L_NEED_CATEGORY' => $lang['search_need_category'],
			'L_NEED_NEWS' => $lang['search_need_news'],
			'L_NEED_PHOTO' => $lang['search_need_photo'],
			'L_FIND_USER' => isset($find_data['l_search_user']) ? $find_data['l_search_user'] : '',
			'L_FIND_CATEGORY' => isset($find_data['l_search_category']) ? $find_data['l_search_category'] : '',
			'L_FIND_NEWS' => isset($find_data['l_search_news']) ? $find_data['l_search_news'] : '',
			'L_FIND_PHOTO' => isset($find_data['l_search_photo']) ? $find_data['l_search_photo'] : '',

			'D_SEARCH_TEXT' => isset($_POST['search_text']) ? $_POST['search_text'] : '',
			'D_NEED_USER' => isset($check['user']) ? $check['user'] : '',
			'D_NEED_CATEGORY' => isset($check['category']) ? $check['category'] : '',
			'D_NEED_NEWS' => isset($check['news']) ? $check['news'] : '',
			'D_NEED_PHOTO' => isset($check['photo']) ? $check['photo'] : '',
			'D_FIND_USER' => isset($find_data['d_search_user']) ? $find_data['d_search_user'] : '',
			'D_FIND_CATEGORY' => isset($find_data['d_search_category']) ? $find_data['d_search_category'] : '',
			'D_FIND_NEWS' => isset($find_data['d_search_news']) ? $find_data['d_search_news'] : '',
			'D_FIND_PHOTO' => isset($find_data['d_search_photo']) ? $find_data['d_search_photo'] : '',

			'IF_NEED_USER' => $search_user,
			'IF_NEED_CATEGORY' => $search_category,
			'IF_NEED_NEWS' => $search_news,
			'IF_NEED_PHOTO' => $search_photo,

			'U_SEARCH' => $work->config['site_url'] . '?action=search'
); // наполняем массив данными для замены по шаблону, используя ранее полученные данные


echo $template->create_main_template('search', $lang['main_search'], $template->create_template('search.tpl', $array_data)); // выводим сформированную страницу

?>