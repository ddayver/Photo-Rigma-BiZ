<?php
/*****************************************************************************
**	File:	action/category.php												**
**	Diplom:	Gallery															**
**	Date:	13/01-2009														**
**	Ver.:	0.1																**
**	Autor:	Gold Rigma														**
**	E-mail:	nvn62@mail.ru													**
**	Decr.:	Обзор и управление разделами									**
*****************************************************************************/

// Проверка, что файл подключается из индексного, а не набран напрямую в адресной строке
if (IN_DIPLOM)
{
	die('HACK!');
}

include_once($config['site_dir'] . 'language/' . $config['language'] . '/main.php'); // подключаем языковый файл основной страницы
include_once($config['site_dir'] . 'language/' . $config['language'] . '/menu.php'); // подключаем языковый файл меню
include_once($config['site_dir'] . 'language/' . $config['language'] . '/category.php'); // подключаем языковый файл категорий

if (!empty($_SERVER['HTTP_REFERER'])) // проверяем, есть ли реферальная ссылка (есть ли данные - с какой страницы пришел пользователь)
{
	$redirect_url = $_SERVER['HTTP_REFERER']; // если есть, то сохраняем эту ссылку для редиректа
}
else
{
	$redirect_url = $config['site_url']; // иначе для редиректа указываем главную страницу сайта
}

if (!isset($_REQUEST['cat'])) // если НЕ поступал запрос на вывод опеределенной категории, то...
{
	$cat = false; // требуемая категория равна false
}
else
{
	$cat = $_REQUEST['cat']; // иначе указываем, какая категория нужна
}

if ($cat == 'user' || $cat === 0) // если пользователь запросил пользовательские альбомы (фрагмент строки запроса cat=user или ошибочный неверный запрос другого раздела с указанием идентификатора пользовательского раздела = 0), то...
{
	if (!isset($_REQUEST['id']) || !(mb_ereg('^[0-9]+$', $_REQUEST['id']) || $_REQUEST['id'] == 'curent')) // если НЕ был запрошен вывод определенного пользовательского альбома (или в запросе есть ошибки), то формируем список всех пользовательских альбомов
	{
		$act = 'user_category'; // активный пункт меню будет user_category
		$temp = $db->fetch_big_array("SELECT DISTINCT `user_upload` FROM `photo` WHERE `category` = 0 ORDER BY `user_upload` ASC"); // запрашиваем список пользовательских альбомов из БД
		if($temp) // если есть данные о пользовательских альбомах, то...
		{
			$temp_category = ''; // инициируем переменную, в которой будет хранится вывод списка

			for ($i = 1; $i <= $temp[0]; $i++) // проходим по полученному массиву данных о пользовательских альбомах
			{
				$temp2 = $db->num_rows("SELECT `id` FROM `user` WHERE `id` = " . $temp[$i]['user_upload']); // проверяем, существует ли пользователь - владелец альбома
				if($temp2) // если таковой пользователь существует, то...
				{
					$temp_category .= $work->category($temp[$i]['user_upload'], 1); // пополняем список пользовательских альбомов, используя функцию из общего класса
				}
			}

			$temp2 = $db->fetch_array("SELECT `name`, `description` FROM `category` WHERE `id` = 0"); // запрашиваем общие данные для пользовательских альбомов

			$array_data = array(); // инициируем массив

			$array_data = array(
						'NAME_BLOCK' => $lang['category_users_album'],
						'L_NAME_CATEGORY' => $temp2['name'],
						'L_DESCRIPTION_CATEGORY' => $temp2['description'],
						'L_COUNT_PHOTO' => $lang['category_count_photo'],
						'L_LAST_PHOTO' => $lang['main_last_foto'] . $lang['category_of_category'],
						'L_TOP_PHOTO' => $lang['main_top_foto'] . $lang['category_of_category'],

						'TEXT_CATEGORY' => $temp_category
			); // наполняем массив данными для замены по шаблону

			$main_block = $template->create_template('category_view.tpl', $array_data); // заполняем центральный блок сайта полученными данными
		}
	}
	else // иначе был запрошен определенный альбом
	{
		if ($_REQUEST['id'] == 'curent' && $user->user['id'] > 0) // если запрошен альбом текущего пользоателя и текущий пользователь не является гостем
		{
			$cat_id = $user->user['id']; // укажем, что требуется вывести пользовательский альбом с идентификатором текущего пользователя
		}
		else
		{
			$cat_id = $_REQUEST['id']; // иначе укажем, запрошенный идентификатор (всегда равен числу)
		}
		if ($cat_id == $user->user['id'] && $user->user['id'] > 0) $act = 'you_category'; // если затребован текущий альбом, то активный пункт меню будет you_category
		$temp = $db->fetch_big_array("SELECT `id` FROM `photo` WHERE `category` =0 AND `user_upload` = " . $cat_id . " ORDER BY `date_upload` DESC"); // запрашиваем список изображений указанного пользовательского альбома, отсортированный по времени задливки от самого свежего до самого старого изображения
		if ($temp && $temp[0] > 0 && $user->user['pic_view'] == true) // если есть данные об изображения и пользователь имеет право их просматривать, то..
		{
			$temp_category = ''; // инициируем переменную для хранения выводимых изображений
			for ($i=1; $i<=$temp[0]; $i++) // проходим по циклу все полученные изображения
			{
                $temp_category .= $template->create_foto('cat', $temp[$i]['id']); // наполняем переменную для хранения изображений готовыми фрагментами выводимых изображений, формируемые в классе обработки шаблонов
			}
			$temp_user = $db->fetch_array("SELECT `real_name` FROM `user` WHERE `id` = " . $cat_id); // запрашиваем данные о пользователе - владельце альбома
			$temp2 = $db->fetch_array("SELECT `name`, `description` FROM `category` WHERE `id` = 0"); // запрашиваем общие данные о пользовательских альбомах

			$array_data = array(); // инициируем массив

			$array_data = array(
						'NAME_BLOCK' => $lang['category_category'] . ' - ' . $temp2['name'] . ' ' . $temp_user['real_name'],
						'DESCRIPTION_BLOCK' => $temp2['description'] . ' ' . $temp_user['real_name'],
						'IF_EDIT_BLOCK' => false,
						'L_EDIT_BLOCK' => '',
						'L_DELETE_BLOCK' => '',
						'L_CONFIRM_DELETE_BLOCK' => '',

						'U_EDIT_BLOCK' => '',
						'U_DELETE_BLOCK' => '',

						'PIC_CATEGORY' => $temp_category
			); // наполняем массив данными для замены по шаблону с указанием невозможности редактирования данных альбома

			$main_block = $template->create_template('category_view_pic.tpl', $array_data); // формируем вывод центрального блока сайта
		}
		else // иначе если нету данных об изображениях
		{
			$array_data = array(); // инициируем массив

			$array_data = array(
						'NAME_BLOCK' => $lang['category_name_block'],
						'L_NEWS_DATA' => $lang['category_error_no_photo'],
						'L_TEXT_POST' => ''
			); // наполняем массив данными для замены по шаблону

			$main_block = $template->create_template('news.tpl', $array_data); // выводим на центральный блок сообщение об отсутствии изображений
		}
	}
}
elseif (mb_ereg('^[0-9]+$', $cat)) // иначе если запрошен определенный раздел (идентификатор раздела равен числу - к примеру, cat=1)
{
	if (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'saveedit' && $user->user['cat_moderate'] == true && $cat != 0) // если поступила под-команда о необходимости сохранить данные о разделе, пользователь имеет право редактировать раздел и раздел не равен 0 (не является пользовательскими альбомами), то...
	{
		$temp = $db->fetch_array("SELECT * FROM `category` WHERE `id` = " . $cat); // запрашиваем текущие данные о разделе (НЕ измененные)
		if ($temp) // если данные существуют (а соотвественно и сам раздел), то...
		{
			if(!isset($_POST['name_category']) || empty($_POST['name_category'])) // если не поступило из формы данных о названии раздела или это поле было пустым, то...
			{
            	$name_category = $temp['name']; // название раздела будет равно уже существующему
			}
			else
			{
            	$name_category = $_POST['name_category']; // иначе сохраним новое название раздела
			}

			if(!isset($_POST['description_category']) || empty($_POST['description_category'])) // если не поступило из формы данных об описании раздела или это поле было пустым, то...
			{
            	$description_category = $temp['description']; // описание раздела будет равно уже существующему
			}
			else
			{
            	$description_category = $_POST['description_category']; // иначе сохраним новое описание раздела
			}

			$db->query("UPDATE `category` SET `name` = '" . $name_category . "', `description` = '" . $description_category . "' WHERE `id` = " . $cat); // обновим данные (название, описание) по указанному разделу
		}
	} // после возможного изменения продолжается нормальная обработка вывода разделов

	if (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'edit' && $user->user['cat_moderate'] == true && $cat != 0) // если поступила под-команда о необходимости вывести форму для редактироания данных о разделе, пользователь имеет право редактировать раздел и раздел не равен 0 (не является пользовательскими альбомами), то...
	{
		$temp = $db->fetch_array("SELECT * FROM `category` WHERE `id` = " . $cat); // запрашиваем данные о редактируемом разделе
		if($temp) // если данные существуют, то...
		{
			$array_data = array(); // инициируем массив

			$array_data = array(
						'NAME_BLOCK' => $lang['category_edit'] . ' - ' . $temp['name'],
						'L_NAME_DIR' => $lang['category_cat_dir'],
						'L_NAME_CATEGORY' => $lang['main_name_of'] . ' ' . $lang['category_of_category'],
						'L_DESCRIPTION_CATEGORY' => $lang['main_description_of'] . ' ' . $lang['category_of_category'],
						'L_EDIT_THIS' => $lang['category_save'],

						'D_NAME_DIR' => $temp['folder'],
						'D_NAME_CATEGORY' => $temp['name'],
						'D_DESCRIPTION_CATEGORY' => $temp['description'],

						'U_EDITED' => '?action=category&subact=saveedit&cat=' . $cat
			); // наполняем массив данными для замены по шаблону

			$main_block = $template->create_template('category_edit.tpl', $array_data); // формируем центральный блок - форму для редактирования названия и описания раздела
		}
		else // иначе если раздел не существует, то...
		{
			$array_data = array(); // инициируем массив

			$array_data = array(
						'NAME_BLOCK' => $lang['category_error_no_category'],
						'DESCRIPTION_BLOCK' => '',
						'IF_EDIT_BLOCK' => false,
						'L_EDIT_BLOCK' => '',
						'L_DELETE_BLOCK' => '',
						'L_DELETE_BLOCK' => '',
						'L_CONFIRM_DELETE_BLOCK' => '',

						'U_EDIT_BLOCK' => '',
						'U_DELETE_BLOCK' => '',

						'PIC_CATEGORY' => $lang['category_error_no_category']
			); // наполняем массив данными для замены по шаблону

			$main_block = $template->create_template('category_view_pic.tpl', $array_data); // формируем вывод в центральный блок сообщения о том, что раздел не существует
		}
	}
	elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'delete' && $user->user['cat_moderate'] == true && $cat != 0) // иначе если поступила под-команда о необходимости удалить раздел, пользователь имеет право удалить раздел и раздел не равен 0 (не является пользовательскими альбомами), то...
	{
		$temp = $db->fetch_array("SELECT * FROM `category` WHERE `id` = " . $cat); // запрашиваем данные об удаляемом разделе
		if($temp) // если раздел существует, то...
		{
			$temp2 = $db->fetch_big_array("SELECT `id` FROM `photo` WHERE `category` = " . $cat); // запрашиваем данные об изображениях, хранимых в данном разделе

			if ($temp2 && $temp2[0] > 0) // если такие изображения существуют, то...
			{
				for ($i=1; $i<=$temp2[0]; $i++) // проходим циклом по всем изображениям удаляемого раздела
				{
					$work->del_photo($temp2[$i]['id']); // и удаляем их
				}
			}

			$cat_dir = dir($config['site_dir'] . $config['gallery_folder'] . '/' . $temp['folder']); // делаем дополнительную чистку папки раздела - открываем папку раздела
			while (false !== ($entry = $cat_dir->read())) // до тех пор, пока существуют файлы в разделе
			{
				if($entry != '.' && $entry !='..') unlink($cat_dir->path . '/' . $entry); // если это не текущая папка "." и не выход на корневую папку "..", то удаляем файл
			}
			$cat_dir->close(); // закрываем работу с папкой раздела

			$cat_dir = dir($config['site_dir'] . $config['thumbnail_folder'] . '/' . $temp['folder']); // делаем дополнительную чистку папки эскизов раздела - открываем папку эскизов раздела
			while (false !== ($entry = $cat_dir->read())) // до тех пор, пока существуют файлы в папке эскизов раздела
			{
				if($entry != '.' && $entry !='..') unlink($cat_dir->path . '/' . $entry); // если это не текущая папка "." и не выход на корневую папку "..", то удаляем файл эскиза
			}
			$cat_dir->close(); // закрываем работу с папкой эскизов раздела

			rmdir($config['site_dir'] . $config['gallery_folder'] . '/' . $temp['folder']); // удаляем папку раздела галереи
			rmdir($config['site_dir'] . $config['thumbnail_folder'] . '/' . $temp['folder']); // удаляем папку эскизов раздела

			$db->query("DELETE FROM `category` WHERE `id` = " . $cat); // удаляем запись о разделе

			$redirect_url = $config['site_url']; // указываем о необходимости редиректа на главную страницу сайта
			if($db->num_rows("SELECT * FROM `photo`") != 0) $redirect_url .= '?action=category'; // если еще существуют разделы, то допишим к ссылке редиректа необходимоть перехода к списку разделов

			$redirect_time = 5; // установим время редиректа 5 сек
			$redirect_message = $lang['category_category'] . ' ' . $temp['name'] . ' ' . $lang['category_deleted_sucesful']; // Установим сообщение об успешном удалении раздела
		}
		else // иначе если не удалось удалить раздел, то...
		{
			$redirect_url = $config['site_url']; // перейдем на основную страницу сайта редиректом
			$redirect_time = 5; // установим время редиректа 5 сек
			$redirect_message = $lang['category_deleted_error']; // установим сообщение о невозможности удалить раздел
		}
	}
	else // иначе если не поступало никаких дополднительных команд, то выводим изображения из указанного раздела
	{
		$temp = $db->fetch_big_array("SELECT `id` FROM `photo` WHERE `category` = " . $cat . " ORDER BY `date_upload` DESC"); // запрашиваем данные о всех изображениях необходимого раздела, отсортирванные в порядке заливки от самого свежего до самого старого
		if ($temp && $temp[0] > 0 && $user->user['pic_view'] == true) // если есть данные об изображениях, то...
		{
			$temp_category = ''; // инициируем переменную для хранения выводимых изображений
			for ($i=1; $i<=$temp[0]; $i++) // проходим по циклу все полученные изображения
			{
	               $temp_category .= $template->create_foto('cat', $temp[$i]['id']); // наполняем переменную для хранения изображений готовыми фрагментами выводимых изображений, формируемые в классе обработки шаблонов
			}
			$temp2 = $db->fetch_array("SELECT `name`, `description` FROM `category` WHERE `id` = " . $cat); // запрашиваем данные о разделе

			$array_data = array(); // инициируем массив

			$array_data = array(
						'NAME_BLOCK' => $lang['category_category'] . ' - ' . $temp2['name'],
						'DESCRIPTION_BLOCK' => $temp2['description'],
						'IF_EDIT_BLOCK' => $user->user['cat_moderate'],
						'L_EDIT_BLOCK' => $lang['category_edit'],
						'L_DELETE_BLOCK' => $lang['category_delete'],
						'L_DELETE_BLOCK' => $lang['category_delete'],
						'L_CONFIRM_DELETE_BLOCK' => $lang['category_confirm_delete1'] . ' ' . $temp2['name'] .  $lang['category_confirm_delete2'],

						'U_EDIT_BLOCK' => '?action=category&subact=edit&cat=' . $cat,
						'U_DELETE_BLOCK' => '?action=category&subact=delete&cat=' . $cat,

						'PIC_CATEGORY' => $temp_category
			); // наполняем массив данными для замены по шаблону

			$main_block = $template->create_template('category_view_pic.tpl', $array_data); // формируем центральный блок, где будет выведен список изображений (эскизами) всех хранимых в данном разделе изображений
		}
		else // иначе, если нет изображений в разделе, то...
		{
			$temp2 = $db->fetch_array("SELECT `name`, `description` FROM `category` WHERE `id` = " . $cat); // запрашиваем данные о разделе и...

			if($temp2) // если данные есть, то сформируем вывод сообщения об отсутствии изображений в разделе
			{
				$category_name = $lang['category_category'] . ' - ' . $temp2['name'];
				$category_description = $temp2['description'];
				$if_edit = $user->user['cat_moderate'];
				$pic_category = $lang['category_error_no_photo'];
			}
			else // иначе сообщим пользователю, что раздела не существует
			{
				$category_name = $lang['category_error_no_category'];
				$category_description = '';
				$if_edit = false;
				$pic_category = $lang['category_error_no_category'];
			}
			$array_data = array(); // инициируем массив

			$array_data = array(
						'NAME_BLOCK' => $category_name,
						'DESCRIPTION_BLOCK' => $category_description,
						'IF_EDIT_BLOCK' => $if_edit,
						'L_EDIT_BLOCK' => $lang['category_edit'],
						'L_DELETE_BLOCK' => $lang['category_delete'],
						'L_DELETE_BLOCK' => $lang['category_delete'],
						'L_CONFIRM_DELETE_BLOCK' => $lang['category_confirm_delete1'] . ' ' . $category_name .  $lang['category_confirm_delete2'],

						'U_EDIT_BLOCK' => '?action=category&subact=edit&cat=' . $cat,
						'U_DELETE_BLOCK' => '?action=category&subact=delete&cat=' . $cat,

						'PIC_CATEGORY' => $pic_category
			); // наполняем массив данными для замены по шаблону

			$main_block = $template->create_template('category_view_pic.tpl', $array_data); //выведем в центральный блок уведомление о том, что нет изображений или раздела
		}
	}
}
else // иначе если не указан запрос на вывод определенного раздела, то или выведем список всех разделов или выполним доп-команду, не требующую идентификатора раздела (к примеру - добавить категорию)
{
	if(isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'add' && $user->user['cat_moderate'] == true) // если поступила под-команда о необходимости добавить раздел и пользователь имеет право добавить раздел, то...
	{
		$act = 'add_category'; // активный пункт меню равен add_category

			$array_data = array(); // инициируем массив

			$array_data = array(
						'NAME_BLOCK' => $lang['category_add'],
						'L_NAME_DIR' => $lang['category_cat_dir'],
						'L_NAME_CATEGORY' => $lang['main_name_of'] . ' ' . $lang['category_of_category'],
						'L_DESCRIPTION_CATEGORY' => $lang['main_description_of'] . ' ' . $lang['category_of_category'],
						'L_EDIT_THIS' => $lang['category_added'],

						'D_NAME_DIR' => '',
						'D_NAME_CATEGORY' => '',
						'D_DESCRIPTION_CATEGORY' => '',

						'U_EDITED' => '?action=category&subact=saveadd'
			); // наполняем массив данными для замены по шаблону

			$main_block = $template->create_template('category_add.tpl', $array_data); // выводим в центральный блок форму для создания нового раздела
	}
	elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'saveadd' && $user->user['cat_moderate'] == true) // иначе если поступила под-команда о необходимости сохранить созданный раздел и пользователь имеет право создавать раздел, то...
	{
		if(!isset($_POST['name_dir']) || empty($_POST['name_dir'])) // если не указано название папки раздела, то...
		{
           	$name_dir = time(); // присвоим названию раздела временный штамп
		}
		else
		{
           	$name_dir = $work->encodename($_POST['name_dir']); // иначе присвоим перкодированное указание имени папки (транслит русских символов, замена спец-символов)
		}

		if($db->num_rows("SELECT * FROM `category` WHERE `folder` = '" . $name_dir . "'") || is_dir($config['site_dir'] . $config['gallery_folder'] . '/' . $name_dir) || is_dir($config['site_dir'] . $config['thumbnail_folder'] . '/' . $name_dir)) // проверяем, если такая папка раздела уже существует, то...
		{
			$name_dir = time() . '_' . $name_dir; // добавим к имени папки временный штамп
		}

		if(!isset($_POST['name_category']) || empty($_POST['name_category'])) // Если не указано название раздела, то...
		{
           	$name_category = $lang['category_no_name'] . ' (' . $name_dir . ')'; // сформируем название в стиле "Без названия (имя папки)"
		}
		else // иначе
		{
           	$name_category = $_POST['name_category']; // сохраним указаное название раздела
		}

		if(!isset($_POST['description_category']) || empty($_POST['description_category'])) // если не указано описание раздела, то...
		{
           	$description_category = $lang['category_no_description'] . ' (' . $name_dir . ')'; // сформируем описание в стиле "Без описания (имя папки)"
		}
		else // иначе
		{
           	$description_category = $_POST['description_category']; // сохраним указаное название раздела
		}

		if(mkdir($config['site_dir'] . $config['gallery_folder'] . '/' . $name_dir, 0777) && mkdir($config['site_dir'] . $config['thumbnail_folder'] . '/' . $name_dir, 0777)) // если удалось создать папки для хранения изображений и эскизов раздела, то..
		{
			@copy($config['site_dir'] . $config['gallery_folder'] . '/index.php', $config['site_dir'] . $config['gallery_folder'] . '/' . $name_dir . '/index.php'); // скопируем индексный файл из корневой папки раздела (позволить скрыть вывод списка изображений)
			@copy($config['site_dir'] . $config['thumbnail_folder'] . '/index.php', $config['site_dir'] . $config['thumbnail_folder'] . '/' . $name_dir . '/index.php'); // и скопируем индексный файл из корневой папки эскизов раздела (позволить скрыть вывод списка эскизов изображений)

			$new_cat = $db->insert_id("INSERT IGNORE INTO `category` (`folder`, `name`, `description`) VALUES ('" . $name_dir . "', '" . $name_category . "', '" . $description_category . "')"); // добавим в базу запись о новом разделе и получим из базы идентификатор созданного раздела

			if($new_cat != 0) // если получен идентификатор созданного раздела, то...
			{
				$redirect_url = $config['site_url'] . '?action=category&cat=' . $new_cat; // организуем редирект в указанный раздел
			}
			else
			{
				$redirect_url = $config['site_url'] . '?action=category'; // иначе редирект будет к списку категорий
			}
			$redirect_time = 5; // установим время редиректа 5 сек
			$redirect_message = $lang['category_category'] . ' ' . $name_category . ' ' . $lang['category_added_sucesful']; // выведем сообщение о создании раздела
		}
		else // иначе если не удалось создать раздел
		{
			$redirect_url = $config['site_url'] . '?action=category&subact=add'; // сделаем редирект обратно на форму добавления раздела
			$redirect_time = 5; // установим время редиректа 5 сек
			$redirect_message = $lang['category_added_error']; // выведем сообщение о невозможности создать раздел
		}
	}
	else // иначе если дополнительных команд не поступало, то выведем список разделов
	{
		$act = 'category'; // текущий пункт меню равен category

		$temp = $db->fetch_big_array("SELECT `id` FROM `category` WHERE `id` !=0"); // запросим данные о всех разделах, кроме пользовательских альбомов

		if($temp) // если разделы существуют, то...
		{
			$temp_category = ''; // инициируем переменную для формирования списка разделов

			for($i = 1; $i<=$temp[0]; $i++) // сформируем  цикле список разделов
			{
				$temp_category .= $work->category($temp[$i]['id'], 0); // используя общий класс функций
			}
			$temp_category .= $work->category(0, 0); // добавим к списку пользовательские альбомы

			$array_data = array(); // инициируем массив

			$array_data = array(
						'NAME_BLOCK' => $lang['category_name_block'],
						'L_NAME_CATEGORY' => $lang['main_name_of'] . $lang['category_of_category'],
						'L_DESCRIPTION_CATEGORY' => $lang['main_description_of'] .  $lang['category_of_category'],
						'L_COUNT_PHOTO' => $lang['category_count_photo'],
						'L_LAST_PHOTO' => $lang['main_last_foto'] . $lang['category_of_category'],
						'L_TOP_PHOTO' => $lang['main_top_foto'] . $lang['category_of_category'],

						'TEXT_CATEGORY' => $temp_category
			); // наполняем массив данными для замены по шаблону

			$main_block = $template->create_template('category_view.tpl', $array_data); // формируем в центральном блоке список разделов
		}
		else // если разделов не существует
		{
			$array_data = array(); // инициируем массив

			$array_data = array(
						'NAME_BLOCK' => $lang['category_name_block'],
						'L_NEWS_DATA' => $lang['main_no_category'],
						'L_TEXT_POST' => ''
			); // наполняем массив данными для замены по шаблону

			$main_block = $template->create_template('news.tpl', $array_data); // выводим сообщение о том, что разделов не сущестует
		}
	}
}

if ((isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'delete' && $user->user['cat_moderate'] == true && $cat != 0) || (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'saveadd' && $user->user['cat_moderate'] == true)) // если была использованна одна из команд, требующая редирект (удаление, сохранение добавленного раздела), то...
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
	$title = $lang['main_redirect_title']; // устанавливаем дополнением к названию страницы сообщение о переадресации
	$main_block = $template->create_template('redirect.tpl', $array_data); // формируем центральный блок с сообщением для редиректа
}
else // во всех остальных случаях
{
	$redirect = array(); // массив с данными по редиректу создается пустым
	$title = $lang['category_name_block']; // дополнением к названию страницы будет являтся название центрального блока - "Разделы"
}
echo $template->create_main_template($act, $title, $main_block, $redirect); // выводим сформированную страницу
?>