<?php
/**
* @file		action/photo.php
* @brief	Работа с фото.
* @author	Dark Dayver
* @version	0.1.1
* @date		27/03-2012
* @details	Вывод, редактировани, загрузка и обработка изображений, оценок.
*/

// Проверка, что файл подключается из индексного, а не набран напрямую в адресной строке
if (IN_GALLERY)
{
	die('HACK!');
}

include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/main.php'); // подключаем языковый файл основной страницы
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/menu.php'); // подключаем языковый файл меню
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/photo.php'); // подключаем языковый файл изображений
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/category.php'); // подключаем языковый файл разделов

if (!isset($_REQUEST['id']) || empty($_REQUEST['id']) || !mb_ereg('^[0-9]+$', $_REQUEST['id']) || $user->user['pic_view'] != true) // если не поступил запрос на вывод определенного изображения, идентификатор не является числом или пользователь не имеет права просматривать изображения, то...
{
	$photo_id = 0; // установить идентификатор выводимого изоюражения равным 0
}
else // иначе
{
	$photo_id = $_REQUEST['id']; // сохранить поступивший идентификатор
}

$cur_act = ''; // активного пункта меню - нет
$photo = array(); // инициируем массив изображений

$temp = $db->fetch_array("SELECT `file` , `category` FROM `photo` WHERE `id` = " . $photo_id); // запрашиваем данные об изображении
if (!$temp) // если изображения не существует, то...
{
    $photo_id = 0; // установить идентификатор выводимого изоюражения равным 0
}
else // иначе
{
    $temp2 = $db->fetch_array("SELECT `folder` FROM `category` WHERE `id` = " .  $temp['category']); // запрашиваем данные о разделе изображения
    if(!$temp2) // если раздел не существует, то...
    {
        $photo_id = 0; // установить идентификатор выводимого изоюражения равным 0
	}
	else
	{
		if(!@fopen($work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp2['folder'] . '/' . $temp['file'], 'r')) // проверяем, существует ли файл изображения, если не существует, то...
		{
            $photo_id = 0; // установить идентификатор выводимого изоюражения равным 0
		}
	}
}

$main_tpl = 'photo_view.tpl'; // устанавливаем основной шаблон для вывода изображения
$max_photo_w = $work->config['max_photo_w']; // получаем данные о максимальной ширине изображения
$max_photo_h = $work->config['max_photo_h']; // и о максимальной высоте

if ($photo_id != 0) // если существует идентификатор изображения (не равен 0)
{
	$temp_foto = $db->fetch_array("SELECT * FROM `photo` WHERE `id` = " . $photo_id); // получаем данные об изображении
   	$temp_category = $db->fetch_array("SELECT * FROM `category` WHERE `id` = " .  $temp_foto['category']); // и данные о разделе изображения

	if(isset($_REQUEST['subact']) && $_REQUEST['subact'] == "rate" && ($user->user['pic_rate_user'] == true || $user->user['pic_rate_moder'] == true) && $temp_foto['user_upload'] != $user->user['id']) // если поступила команда на оценку изображения и пользователь имеет право оценивать изображение как пользователь или преподаватель (модератор), то...
	{
		if ($user->user['pic_rate_user'] == true && isset($_POST['rate_user']) && mb_ereg('^[0-9]+$', abs($_POST['rate_user'])) && abs($_POST['rate_user']) <= $work->config['max_rate'] && !($db->fetch_array("SELECT `rate` FROM `rate_user` WHERE `id_foto` = " . $photo_id . " AND `id_user` = " . $user->user['id']))) // если поступила оценка как от пользователя и данный пользователь еще не оценивал это изображение и не является его автором, то...
		{
			$db->query("INSERT IGNORE INTO `rate_user` (`id_foto`, `id_user`, `rate`) VALUES ('" . $photo_id . "', '" . $user->user['id'] . "', '" . $_POST['rate_user'] . "')"); // сохраним оценку от пользователя
			$temp = $db->fetch_big_array("SELECT `rate` FROM `rate_user` WHERE `id_foto` = " . $photo_id); // запросим данные о всех оценках указанного изображения от пользователей для перерасчета рейтинга
			if($temp && $temp[0] > 0) // если существуют оценки изображения, то...
			{
				$rate_user = 0; // стартовая оценка равна 0
				for($i=1; $i<= $temp[0]; $i++) // обработаем в цикле имеющиеся оценки
				{
					$rate_user += $temp[$i]['rate']; // суммируя их
				}
				$rate_user = $rate_user/$temp[0]; // расчитаем среднюю оценку от пользователей
				$db->query("UPDATE `photo` SET `rate_user` = '" . $rate_user . "' WHERE `id` = " . $photo_id); // обновим в данных об изображении рейтинг пользователей
			}
		}

		if ($user->user['pic_rate_moder'] == true && isset($_POST['rate_moder']) && mb_ereg('^[0-9]+$', abs($_POST['rate_moder'])) && abs($_POST['rate_moder']) <= $work->config['max_rate'] && !($db->fetch_array("SELECT `rate` FROM `rate_moder` WHERE `id_foto` = " . $photo_id . " AND `id_user` = " . $user->user['id']))) // если поступила оценка как от преподавателя (модератора) и данный пользователь еще не оценивал это изображение и не является его автором, то...
		{
			$db->query("INSERT IGNORE INTO `rate_moder` (`id_foto`, `id_user`, `rate`) VALUES ('" . $photo_id . "', '" . $user->user['id'] . "', '" . $_POST['rate_moder'] . "')"); // сохраним оценку от преподавателя (модератора)
			$temp = $db->fetch_big_array("SELECT `rate` FROM `rate_moder` WHERE `id_foto` = " . $photo_id); // запросим данные о всех оценках указанного изображения от преподавателей (модераторов) для перерасчета рейтинга
			if($temp && $temp[0] > 0) // если существуют оценки изображения, то...
			{
				$rate_moder = 0; // стартовая оценка равна 0
				for($i=1; $i<= $temp[0]; $i++) // обработаем в цикле имеющиеся оценки
				{
					$rate_moder += $temp[$i]['rate']; // суммируя их
				}
				$rate_moder = $rate_moder/$temp[0]; // расчитаем среднюю оценку от преподавателей (модераторов)
				$db->query("UPDATE `photo` SET `rate_moder` = '" . $rate_moder . "' WHERE `id` = " . $photo_id); // обновим в данных об изображении рейтинг преподавателей (модераторов)
			}
		}
	}
	elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'saveedit' && (($temp_foto['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == true)) // если поступила команда на сохранение изменений в изображении и пользователь имеет право редактировать изображение или является его автором, то...
	{
		$temp_foto = $db->fetch_array("SELECT * FROM `photo` WHERE `id` = " . $photo_id); // запросим данные об изображении

		if(!isset($_POST['name_photo']) || empty($_POST['name_photo'])) // если не поступило данных о названии или название пустое, то...
		{
            $photo['name'] = $temp_foto['name']; // используем старое название изображения
		}
		else
		{
            $photo['name'] = $_POST['name_photo']; // иначе сохраним новое название
		}

		if(!isset($_POST['description_photo']) || empty($_POST['description_photo'])) // если не поступило данных об описании или описание пустое, то...
		{
            $photo['description'] = $temp_foto['description']; // используем старое описание изображения
		}
		else
		{
            $photo['description'] = $_POST['description_photo']; // иначе сохраним новое описание
		}

    	$category = true; // служебная метка о смене раздела - разрешена смена

		if(!isset($_POST['name_category']) || !mb_ereg('^[0-9]+$', $_POST['name_category'])) // если не поступило данных о разделе или данные не являются числом, то...
		{
	    	$category = false; // смена раздела запрещена
		}
		else // иначе
		{
			if ($user->user['cat_user'] == true || $user->user['pic_moderate'] == true) // если пользователю разрешено использовать собственный альбом или пользователь является модератором изображений, то...
			{
		    	$select_cat = ' WHERE `id` = ' . $_POST['name_category']; // в запросе указать ВСЕ разделы
			}
			else // иначе
			{
		    	$select_cat = ' WHERE `id` != 0 AND `id` =' . $_POST['name_category']; // исключить из разделов пользовательские альбомы
			}

	    	if (!$db->fetch_array("SELECT * FROM `category`" .  $select_cat)) $category = false; // если указанный раздел не существует, то запретить смену раздела
		}

		if($category && $temp_foto['category'] != $_POST['name_category']) // если смена раздела разрешена и новый раздел не является таким же, как и был, то...
		{
			$temp_old = $db->fetch_array("SELECT `folder` FROM `category` WHERE `id` = " . $temp_foto['category']); // получаем данные о папке старого раздела
			$temp_new = $db->fetch_array("SELECT `folder` FROM `category` WHERE `id` = " . $_POST['name_category']); // получаем данные о папке нового раздела
			$path_old_photo = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_old['folder'] . '/' . $temp_foto['file']; // формируем старый путь к изображению
			$path_new_photo = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_new['folder'] . '/' . $temp_foto['file']; // формируем новый путь к изображению
			$path_old_thumbnail = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_old['folder'] . '/' . $temp_foto['file']; // формируем старый путь к эскизу
			$path_new_thumbnail = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_new['folder'] . '/' . $temp_foto['file']; // формируем новый путь к эскизу
			if (!rename($path_old_photo, $path_new_photo)) // пробуем перенести изображение из старого раздела в новый, если не получилось, то...
			{
    			$photo['category_name'] = $temp_foto['category']; // оставляем старый раздел
			}
			else // иначе
			{
				if(!rename($path_old_thumbnail, $path_new_thumbnail)) // пробуем перенести эскиз с старого места на новое, если не получилось
				{
                    @unlink($path_old_thumbnail); // удаляем старый эскиз (новый будет сформирован при первом же обржении к нему)
				}
				$photo['category_name'] = $_POST['name_category']; // сохраняем новый раздел
			}
		}
		else // если запрет на изменение раздела или раздел остался тем же, то...
		{
            $photo['category_name'] = $temp_foto['category']; // оставляем старый раздел для изображения
		}

		if(!get_magic_quotes_gpc()) // если не включены magic_quotes_gpc в настройках PHP, то...
		{
			$photo['name'] = addslashes($photo['name']); // экранируем название
			$photo['description'] = addslashes($photo['description']); // и описание изображения
		}

		$db->query("UPDATE `photo` SET `name` = '" . $photo['name'] . "', `description` = '" . $photo['description'] . "', `category` = '" . $photo['category_name'] . "' WHERE `id` = " . $photo_id); // обновляем данные об изображении в базе данных
	}

	if (isset($_REQUEST['subact']) && $_REQUEST['subact'] == "edit" && (($temp_foto['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == true)) // если поступила команда на редактирование изображения и пользователь является владельцем изображения и не является гстем или пользователь имеет право редактирования изображений, то формируем блок редактирования изображения
	{
		$main_tpl = 'photo_edit.tpl'; // устанавливаем шаблон для центрального блока
		$temp_foto = $db->fetch_array("SELECT * FROM `photo` WHERE `id` = " . $photo_id); // полчаем данные об изображении
    	$temp_category = $db->fetch_array("SELECT * FROM `category` WHERE `id` = " .  $temp_foto['category']); // получаем данные о разделе изображения
		$photo['path'] = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_category['folder'] . '/' . $temp_foto['file']; // формируем путь к эскизу изображения
		$photo['url'] = $work->config['site_url'] . '?action=attach&foto=' . $temp_foto['id'] . '&thumbnail=1'; // формируем ссылку для вывода эскиза изображения
		$photo['name'] = $temp_foto['name']; // сохраняем название изображения
		$photo['file'] = $temp_foto['file']; // сохраняем имя фафла изображения
		$photo['description'] = $temp_foto['description']; // сохраняем описание изображения
		$user_add = $db->fetch_array("SELECT `real_name` FROM `user` WHERE `id` = " . $temp_foto['user_upload']); // запрашиваем отображаемое имя автара изображения
		if ($user_add) // если пользователь существует, то...
		{
			$photo['user'] = $user_add['real_name']; // сохраняем отображаемое имя
			$photo['user_url'] = $work->config['site_url']  . '?action=login&subact=profile&uid=' . $temp_foto['user_upload']; // и формируем ссылку на профиль
		}
		else // иначе
		{
			$photo['user'] = $lang['main_no_user_add']; // указываем, что пользователя не существует
			$photo['user_url'] = ''; // и сосавляем пустую ссылку
		}

		if ($user->user['cat_user'] == true || $user->user['pic_moderate'] == true) // если пользователь имеет право добавлять в пользовательские альбомы или обладаем правами на редактирование изображений, то...
		{
		    $select_cat = ''; // не ограничиваем список разделов
		}
		else // иначе
		{
		    $select_cat = ' WHERE `id` != 0'; // убираем из списка допустимых разделов - пользовательские альбомы
		}

    	$temp_category = $db->fetch_big_array("SELECT * FROM `category`" .  $select_cat); // формируем массив для списка допустимых разделов для переноса изображения

        $photo['category_name'] = '<select name="name_category">'; // иниуиируем выпадающий список разделов
		for ($i = 1; $i <= $temp_category[0]; $i++) // обрабатываем массив с разделами
		{
            if($temp_category[$i]['id'] == $temp_foto['category']) $selected = ' selected'; else $selected = ''; // если очередной раздел является текущим для данного изображения - помечаем его выбранным по-умолчанию
            if($temp_category[$i]['id'] == 0) $temp_category[$i]['name'] .= ' ' . $photo['user']; // если раздел является пользовательским альбомом, то добавляем к название - отображаемое имя пользователя - владельца изображения
			$photo['category_name'] .= '<option value="' . $temp_category[$i]['id'] . '"' . $selected . '>' . $temp_category[$i]['name'] . '</option>'; // наполняем список разделов
		}
		$photo['category_name'] .= '</select>'; // закрываем список разделов
   	    $photo['url_edited'] = $work->config['site_url'] . '?action=photo&subact=saveedit&id=' . $temp_foto['id']; // ссылка для сохранения изменений
       	$photo['url_edited_text'] = $lang['photo_save']; // надпись для кнопки Сохранить
		$max_photo_w = $work->config['temp_photo_w']; // изменяем максимальную высоту изображения на максимальную высоту эскиза
		$max_photo_h = $work->config['temp_photo_h']; // и аналогично для ширины
	}
	elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == "delete" && (($temp_foto['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == true)) // если поступила команда на удаление изображения и пользователь является владельцем изображения и не является гостем или пользователь имеет право редактирования изображений, то производим процедуру удаления
	{
		$photo['name'] = $temp_foto['name']; // сохраняем название изображения
		if($temp_category['id'] == 0) // если удаляется файл из пользовательского альбома, формируем ссылку для редиректа...
		{
			$temp = $db->num_rows("SELECT * FROM `photo` WHERE `id` !=" . $photo_id . " AND `category` = 0 AND `user_upload` = " . $temp_foto['user_upload']); // проверяем, остались ли еще изображения в данном пользовательско альбоме
			if($temp > 0) // если да, то...
			{
				$photo['category_url'] = $work->config['site_url'] . '?action=category&cat=user&id=' . $temp_foto['user_upload']; // ссылка на редирект будет отправлять в этот пользовательский альбом
			}
			else // иначе
			{
				$temp = $db->num_rows("SELECT * FROM `photo` WHERE `id` !=" . $photo_id . " AND `category` = 0"); // проверяем есть ли вообще изображения в пользовательских альбомах
				if($temp > 0) // если да, то...
				{
					$photo['category_url'] = $work->config['site_url'] . '?action=category&cat=user'; // редирект произойдет к списку пользовательских альбомов
				}
				else // иначе
				{
					$temp = $db->num_rows("SELECT * FROM `photo` WHERE `id` !=" . $photo_id); // проверяем есть ли изображения на сайте
					if($temp > 0) // если да, то...
					{
						$photo['category_url'] = $work->config['site_url'] . '?action=category'; // редирект произойдет к списку разделов
					}
					else // иначе
					{
						$photo['category_url'] = $work->config['site_url']; // редирект будет на главную страницу сайта
					}
				}
			}
		}
		else // иначе если удаление НЕ из пользовательского раздела
		{
			$temp = $db->num_rows("SELECT * FROM `photo` WHERE `id` !=" . $photo_id . " AND `category` =  " . $temp_category['id']); // проверяем есть ли еще изображения в данном разделе
			if($temp > 0) // если да, то...
			{
				$photo['category_url'] = $work->config['site_url'] . '?action=category&cat=' . $temp_category['id']; // редирект в данный раздел
			}
			else // иначе
			{
				$temp = $db->num_rows("SELECT * FROM `photo` WHERE `id` !=" . $photo_id); // проверяем, есть ли еще изображения на сайте
				if($temp > 0) // если да, то...
				{
					$photo['category_url'] = $work->config['site_url'] . '?action=category'; // редирект к спску разделов
				}
				else // иначе
				{
					$photo['category_url'] = $work->config['site_url']; // редирект на главную страницу сайта
				}
			}
		}

		if($work->del_photo($photo_id)) // производим удаление изображения, если все получилось, то...
		{
			$redirect_url = $photo['category_url']; // сохраняем ссылку для редиректа
			$redirect_time = 5; // устанавливаем время редиректа 5 сек
			$redirect_message = $lang['photo_title'] . ' ' . $photo['name'] . ' ' . $lang['photo_complite_delete']; // сообщаем о том, что изображение успешно удалено
		}
		else // иначе
		{
			$redirect_url = $work->config['site_url'] . '?action=photo&id=' . $photo_id; // редирект на не удачно удаленное изображение
			$redirect_time = 5; // устанавливаем время редиректа 5 сек
			$redirect_message = $lang['photo_title'] . ' ' . $photo['name'] . ' ' . $lang['photo_error_delete']; // сообщаем о неудачном удалении
		}
	}
	else // если не поступило никаких команд, то формируем вывод изображения
	{
		$temp_foto = $db->fetch_array("SELECT * FROM `photo` WHERE `id` = " . $photo_id); // полчаем данные об изображении
    	$temp_category = $db->fetch_array("SELECT * FROM `category` WHERE `id` = " .  $temp_foto['category']); // получаем данные о разделе изображения
		$photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_category['folder'] . '/' . $temp_foto['file']; // формируем путь к изображению
		$photo['url'] = $work->config['site_url'] . '?action=attach&foto=' . $temp_foto['id']; // формируем ссылку для вывода изображения
		$photo['name'] = $temp_foto['name']; // сохраняем название изображения
		$photo['description'] = $temp_foto['description']; // сохраняем описание изображения
		$photo['category_name'] = $temp_category['name']; // сохраняем название раздела
		$photo['category_description'] = $temp_category['description']; // сохраняем описание раздела

		if (($temp_foto['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == true) // если пользователь является владельцем изображения или имеет прво на редактирование изображений, то формируем блок редактирования или удаления изображения
		{
    	    $photo['url_edit'] = $work->config['site_url'] . '?action=photo&subact=edit&id=' . $temp_foto['id']; // ссылка на редактирование
        	$photo['url_edit_text'] = $lang['photo_edit']; // текст Редактировать
    	    $photo['url_delete'] = $work->config['site_url'] . '?action=photo&subact=delete&id=' . $temp_foto['id']; // ссылка на удаление
        	$photo['url_delete_text'] = $lang['photo_delete']; // текст Удалить
        	$photo['url_delete_confirm'] = $lang['photo_confirm_delete'] . ' ' . $photo['name']; // текст для подтверждения удаления
			$photo['if_edit_photo'] = true; // разрешить вывести блок редактирования и удаления изображения
		}
		else // иначе формируем пустой массив данных
		{
        	$photo['url_edit'] = '';
	        $photo['url_edit_text'] = '';
    	    $photo['url_delete'] = '';
        	$photo['url_delete_text'] = '';
        	$photo['url_delete_confirm'] = '';
    	    $photo['if_edit_photo'] = false; // и запрещаем выводить блок редактирования и удаления изображений
		}

		$user_add = $db->fetch_array("SELECT `real_name` FROM `user` WHERE `id` = " . $temp_foto['user_upload']); // получаем данные об отображаемом имени автора изображения
		if ($user_add) // если автор существует, то...
		{
			$photo['user'] = $user_add['real_name']; // сохраняем отображаемое имя
			$photo['user_url'] = $work->config['site_url']  . '?action=login&subact=profile&uid=' . $temp_foto['user_upload']; // и ссылку на профиль автора
		}
		else // иначе
		{
			$photo['user'] = $lang['main_no_user_add']; // указываем на то, что автора не существует
			$photo['user_url'] = ''; // сохраняем пустую ссылку
		}
		if($temp_category['id'] == 0) // если раздел является пользовательским альбомом, то...
		{
			$photo['category_name'] .= ' ' . $user_add['real_name']; // к названию раздела добавляем отображаемое имя автора изображения
			$photo['category_description'] .=  ' ' . $user_add['real_name']; // аналогично с описанием
			$photo['category_url'] = $work->config['site_url'] . '?action=category&cat=user&id=' . $temp_foto['user_upload']; // сохраняем ссылку на пользовательский альбом
		}
		else // иначе
		{
			$photo['category_url'] = $work->config['site_url'] . '?action=category&cat=' . $temp_category['id']; // сохраняем ссылку на обычный раздел
		}
    	$photo['rate_user'] = $lang['photo_rate_user'] . ': ' . $temp_foto['rate_user']; // формируем сообщение о текущей оценке изображения пользователями
	    $photo['rate_moder'] = $lang['photo_rate_moder'] . ': ' . $temp_foto['rate_moder']; // формируем сообщение о текущей оценке изображения преподавателями (модераторами)
		$photo['rate_you'] = ''; // инициируем вывод собственной оценки

		if ($user->user['pic_rate_user'] == true) // если есть право оценивать как пользователь, то...
		{
			$temp_rate = $db->fetch_array("SELECT `rate` FROM `rate_user` WHERE `id_foto` = " . $photo_id . " AND `id_user` = " . $user->user['id']); // запрашиваем данные об текущей оценке, поставленной как пользователь
			if(!$temp_rate) // если таких данных нет, то...
			{
				$user_rate = 'false'; // оценки не существует
			}
			else // иначе
			{
				$user_rate = $temp_rate['rate']; // сохраняем поставленную оценку
			}
			$photo['rate_you_user'] = $template->template_rate('user', $user_rate); // формируем фрагмент блока оценки от имени пользователя
		}
		else // иначе
		{
			$user_rate = 'false'; // оценки не существует
			$photo['rate_you_user'] = ''; // фрагмент оценки от пользователя отсутствует
		}

		if ($user->user['pic_rate_moder'] == true) // если есть право оценивать как преподаватель (модератор), то...
		{
			$temp_rate = $db->fetch_array("SELECT `rate` FROM `rate_moder` WHERE `id_foto` = " . $photo_id . " AND `id_user` = " . $user->user['id']); // запрашиваем данные об текущей оценке, поставленной как преподаватель (модератор)
			if(!$temp_rate) // если таких данных нет, то...
			{
				$moder_rate = 'false'; // оценки не существует
			}
			else // иначе
			{
				$moder_rate = $temp_rate['rate']; // сохраняем поставленную оценку
			}
			$photo['rate_you_moder'] = $template->template_rate('moder', $moder_rate); // формируем фрагмент блока оценки от имени преподавателя (модератора)
		}
		else // иначе
		{
			$moder_rate = 'false'; // оценки не существует
			$photo['rate_you_moder'] = ''; // фрагмент оценки от преподавателя (модератора) отсутствует
		}

		if (($user->user['pic_rate_user'] == true || $user->user['pic_rate_moder'] == true) && $temp_foto['user_upload'] != $user->user['id']) // если пользователь имеет право оценивать изображения как пользователь или как преподаватель (модератор) и не является автором изображения, то...
		{
			$array_data = array(); // инициируем массив

			if($user_rate == 'false' && $moder_rate == 'false') // если не существует оценки от пользователя и преподавателя (модератора), то...
			{
        	    $photo['rate_you_url'] = $work->config['site_url'] . '?action=photo&subact=rate&id=' . $photo_id; // сохраняем ссылку для оценки изображения
            	$rate_this = true; // разрешаем вывести кнопку и форму для оценки
			}
			else // иначе
			{
				$photo['rate_you_url'] = ''; // ссылки не существует
    	        $rate_this = false; // и вывод кнопки и формы запрещен
			}

			$array_data = array(
						'U_RATE' => $photo['rate_you_url'],
						'L_RATE' => $lang['photo_rate_you'],
						'L_RATE_THIS' => $lang['photo_rate'],
						'D_RATE' => $photo['rate_you_user'] . $photo['rate_you_moder'],

						'IF_RATE_THIS' => $rate_this
			); // наполняем массив данными для замены по шаблону

	        $photo['rate_you'] = $template->create_template('rate_form.tpl', $array_data); // формируем фрагмент с оценками от пользователя
		}
	}
}
else // иначе если не указан был идентификатор изображения, то...
{
	if (isset($_REQUEST['subact']) && $_REQUEST['subact'] == "upload" && $user->user['id'] != 0 && $user->user['pic_upload'] == true) // если получена команда на загрузку изображения и пользователь имеет право загрузки изображения, то...
	{
		$main_tpl = 'photo_upload.tpl'; // указываем шаблон для загрузки изображения
		$temp_foto['file'] = 'no_foto.png'; // по умолчанию используем пустое изображение
		$photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file']; // и путь к нему

		$max_size_php = $work->return_bytes(ini_get('post_max_size')); // получаем данные о максимально допустимом размере загружаемого файла в настройках PHP (в байтах)
		$max_size = $work->return_bytes($work->config['max_file_size']); // получаем максимально разрешаемый размер файла для заливки в настройках сайта (в байтах)
		if ($max_size > $max_size_php) $max_size = $max_size_php; // если максимально разрешенный к заливке размер файла в настройках сайта больше допустимого с настройках PHP, то ограничиваем размер настройками PHP

		if ($user->user['cat_user'] == true) // если пользователь имеет право загружать в собственный пользовательский альбом, то...
		{
		    $select_cat = ''; // ограничений при выборе разделов нет
		}
		else // иначе
		{
		    $select_cat = ' WHERE `id` != 0'; // разрешить все разделы, кроме пользовательских альбомов
		}

    	$temp_category = $db->fetch_big_array("SELECT * FROM `category`" .  $select_cat); // запрашиваем данные для списка разделов

        $photo['category_name'] = '<select name="name_category">'; // открываем выпадающий список разделов
		for ($i = 1; $i <= $temp_category[0]; $i++) // добавляем разделы из списка
		{
            if($temp_category[$i]['id'] == 0) // если это пользовательский альбом
			{
				$temp_category[$i]['name'] .= ' ' . $user->user['real_name']; // добавляем отображаемое имя пользователя к названию пользовательских альбомов
				$selected = ' selected'; // по умолчанию выбираем данные раздел
			}
			else
			{
			    $selected = ''; // иначе по умолчанию разделы выбраны не будут
			}
			$photo['category_name'] .= '<option value="' . $temp_category[$i]['id'] . '"' . $selected . '>' . $temp_category[$i]['name'] . '</option>'; // формируем пункт списка разделов
		}
		$photo['category_name'] .= '</select>'; // закрываем список разделов
   	    $photo['url_uploaded'] = $work->config['site_url'] . '?action=photo&subact=uploaded'; // указываем ссылку для заливки изображения
	}
	elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == "uploaded" && $user->user['id'] != 0 && $user->user['pic_upload'] == true) // инче если получена команда на сохранение загруженного изображения и пользователь имеет право загрузки изображения, то...
	{
		$temp_foto['file'] = 'no_foto.png'; // по умолчанию используем пустое изображение
		$photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file']; // и путь к нему
    	$submit_upload = true; // загрузка удачна = true (ИСТИНА)
		$max_size_php = $work->return_bytes(ini_get('post_max_size')); // получаем данные о максимально допустимом размере загружаемого файла в настройках PHP (в байтах)
		$max_size = $work->return_bytes($work->config['max_file_size']); // получаем максимально разрешаемый размер файла для заливки в настройках сайта (в байтах)
		if ($max_size > $max_size_php) $max_size = $max_size_php; // если максимально разрешенный к заливке размер файла в настройках сайта больше допустимого с настройках PHP, то ограничиваем размер настройками PHP

		if(!isset($_POST['name_photo']) || empty($_POST['name_photo'])) // если не указано название изображения или оно пришло пустым, то...
		{
            $photo['name'] = $lang['photo_no_name'] . ' (' . $work->encodename(basename($_FILES['file_photo']['name'])) . ')'; // формируем название в виде "Без названия (имя_файла)"
		}
		else // иначе
		{
            $photo['name'] = $_POST['name_photo']; // сохраняем название изображения
		}

		if(!isset($_POST['description_photo']) || empty($_POST['description_photo'])) // если не указано описание изображения или оно пришло пустым, то...
		{
            $photo['description'] = $lang['photo_no_description'] . ' (' . basename($_FILES['file_photo']['name']) . ')'; // формируем описание в виде "Без описания (имя_файла)"
		}
		else // иначе
		{
            $photo['description'] = $_POST['description_photo']; // сохраняем описание изображения
		}

		if(!isset($_POST['name_category']) || !mb_ereg('^[0-9]+$', $_POST['name_category'])) // если не получены данные о разделе, куда разместить изображение или указатель на раздел не является числом, то...
		{
	    	$submit_upload = false; // загрузка будет запрещена
		}
		else // иначе
		{
			if ($user->user['cat_user'] == true || $user->user['pic_moderate'] == true) // если пользователю разрешена заливка в свой альбом или он является модератором изображений, то...
			{
		    	$select_cat = ' WHERE `id` = ' . $_POST['name_category']; // проверяем на существование раздела
			}
			else // иначе
			{
		    	$select_cat = ' WHERE `id` != 0 AND `id` =' . $_POST['name_category']; // проверяем существование раздела при условии, что он не является пользовательским альбомом
			}

	    	if (!$db->fetch_array("SELECT * FROM `category`" .  $select_cat)) $submit_upload = false; // если раздел не существует, запрещаем заливку
		}

		if($submit_upload) // если заливка еще разрешена, то...
		{
			$photo['category_name'] = $_POST['name_category']; // сохраняем данные о разделе
			if(!get_magic_quotes_gpc()) // если не включены magic_quotes_gpc, то...
			{
				$photo['name'] = addslashes($photo['name']); // экранируем название
				$photo['description'] = addslashes($photo['description']); // и описание изображения
			}
			if ($_FILES['file_photo']['error'] == 0 && $_FILES['file_photo']['size'] > 0 && $_FILES['file_photo']['size'] <= $max_size && mb_eregi('(gif|jpeg|png)$', $_FILES['file_photo']['type'])) // проверяем, что файл изображения загружен без ошибок, размер и тип файла соотвествуют настройкам, если да, то...
			{
				$file_name = time() . '_' . $work->encodename(basename($_FILES['file_photo']['name'])); // создаем имя файла типа: временный_штамп +  перекодированное имя файла (транслит с заменой спе-символов)
				$temp_category = $db->fetch_array("SELECT * FROM `category` WHERE `id` = " .  $photo['category_name']); // получаем данные о разделе, куда сохраняется изобрадение
				$photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_category['folder'] . '/' . $file_name; // формируем путь к изображению
				$photo['thumbnail_path'] = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_category['folder'] . '/' . $file_name; // формируем путь к эскизу
				if (move_uploaded_file($_FILES['file_photo']['tmp_name'], $photo['path'])) // пробуем поместить изображение в нужную папку, если получилось, то...
				{
					$template->Image_Resize($photo['path'], $photo['thumbnail_path']); // выполняем команду на создание эскиза к изображению
				}
				else // иначе, если не удалось поместить изображение
				{
                    $submit_upload = false; // сообщаем о невозможности загрузки
				}
			}
			else // иначе, если файл не соотвествует параметрами
			{
                $submit_upload = false; // сообщаем о невозможности загрузки
			}
		}
		if($submit_upload) // если загрузка успешна, то...
		{
			$photo_id = $db->insert_id("INSERT IGNORE INTO `photo` (`id`, `file`, `name`, `description`, `category`, `date_upload`, `user_upload`, `rate_user`, `rate_moder`) VALUES (NULL , '" . $file_name . "', '" . $photo['name'] . "', '" . $photo['description'] . "', '" . $photo['category_name'] . "',CURRENT_TIMESTAMP , '" . $user->user['id'] . "', '0', '0')"); // добавляем в базу запись о загруженном изображении и получаем его идентификатор
			if($photo_id) // если идентификатор получен, то...
			{
				$redirect_url = $work->config['site_url'] . '?action=photo&id=' . $photo_id; // ссылка редиректа будет вести на страницу просмотра загруженного изобрадения
				$redirect_time = 5; // устанавливаем время редиректа 5 сек
				$redirect_message = $lang['photo_title'] . ' ' . $file_name . ' ' . $lang['photo_complite_upload']; // выводим сообщение об удачной загрузке файла
			}
			else // иначе, если запись в базу не удалась
			{
                @unlink($photo['path']); // принудительно удаляем изображение
                @unlink($photo['thumbnail_path']); // и эскиз
				$redirect_url = $work->config['site_url'] . '?action=photo&subact=upload'; // редирект ведет на форму загрузки изображения
				$redirect_time = 3; // устанавливаем время редиректа 3 сек
				$redirect_message = $lang['photo_error_upload']; // сообщаем о неудачной загрузке
			}
		}
		else // иначе если произошла ошибка при загрзке
		{
			$redirect_url = $work->config['site_url'] . '?action=photo&subact=upload'; // редирект на страницу загрузки изображения
			$redirect_time = 3; // устанавливаем время редиректа 3 сек
			$redirect_message = $lang['photo_error_upload']; // сообщаем о неудачной загрузке
		}
	}
	else // иначе если данные об изображении получить не удалось, формируем вывод для отсутствующего изображения
	{
		$temp_foto['file'] = 'no_foto.png'; // устанавливаем имя файла
		$photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file']; // путь к файлу
		$photo['url'] = $work->config['site_url'] . '?action=attach&foto=0'; // ссылку на несуществующее изображение
		$photo['name'] = $lang['main_no_foto']; // название изображения в виде НЕТ ФОТО
		$photo['description'] = $lang['main_no_foto']; // аналогично с описанием,
		$photo['category_name'] = $lang['main_no_category']; // названием раздела,
		$photo['category_description'] = $lang['main_no_category']; // описанием раздела,
		$photo['user'] = $lang['main_no_user_add']; // и добавившем изображение пользователе
		$photo['user_url'] = ''; // пустая ссылка на профиль пользователя
		$photo['category_url'] = $work->config['site_url']; // вместо ссылки на раздел - ссылка на главную страницу сайта
    	$photo['rate_user'] = $lang['photo_rate_user'] . ': ' . $lang['main_no_foto']; // вместо оценок указываем
    	$photo['rate_moder'] = $lang['photo_rate_moder'] . ': ' . $lang['main_no_foto']; // сообщение об отсутствии изображения
    	$photo['rate_you'] = ''; // раздел оценок - пуст
		$photo['url_edit'] = ''; // ссылка на редактирование - не существует
		$photo['url_edit_text'] = ''; // текста для редактирования нет
		$photo['if_edit_photo'] = false; // блок редактирования - запрещен
	}
}

$size = getimagesize($photo['path']); // получаем размеры файла

if ($max_photo_w == '0') // если ширина вывода не ограничена...
{
	$ratioWidth = 1; // коэффициент изменения размера по ширине приравниваем 1
}
else
{
	$ratioWidth = $size[0]/$max_photo_w; // иначе рассчитываем этот коэффициент
}

if ($max_photo_h == '0') // если высота вывода не ограничена...
{
	$ratioHeight = 1;  // коэффициент изменения размера по высоте приравниваем 1
}
else
{
	$ratioHeight = $size[1]/$max_photo_h; // иначе рассчитываем этот коэффициент
}

if($size[0] < $max_photo_w && $size[1] < $max_photo_h && $max_photo_w != '0' && $max_photo_h != '0') // если размеры изображения соответствуют или ограничения отсутствуют, то...
{
	$photo['width'] = $size[0]; // выводимая ширина равна ширине изображения
	$photo['height'] = $size[1]; // выводимая высота равна высоте изображения
}
else // иначе...
{
	if($ratioWidth < $ratioHeight) // если высота больше ширины, то...
	{
		$photo['width'] = $size[0]/$ratioHeight; // выводимая ширина рассчитываается по высоте изображения
		$photo['height'] = $size[1]/$ratioHeight; // выводимая высота рассчитываается по высоте изображения
	}
	else // иначе...
	{
		$photo['width'] = $size[0]/$ratioWidth; // выводимая ширина рассчитываается по ширине изображения
		$photo['height'] = $size[1]/$ratioWidth; // выводимая высота рассчитываается по ширине изображения
	}
}

$array_data = array(); // инициируем массив

if ($photo_id != 0 && isset($_REQUEST['subact']) && $_REQUEST['subact'] == "edit" && (($temp_foto['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == true)) // если поступала команда на редактирование и все условия для этого выполнены, то формируем вывод блока редактирования избражения
{
	$array_data = array(
				'NAME_BLOCK' => $lang['photo_edit'] . ' - ' . $photo['name'],
				'L_NAME_PHOTO' => $lang['main_name_of'] . ' ' . $lang['photo_of_photo'],
				'L_DESCRIPTION_PHOTO' => $lang['main_description_of'] . ' ' . $lang['photo_of_photo'],
				'L_NAME_CATEGORY' => $lang['main_name_of'] . ' ' . $lang['category_of_category'],
				'L_NAME_FILE' => $lang['photo_filename'],
				'L_EDIT_THIS' => $photo['url_edited_text'],

				'D_NAME_CATEGORY' => $photo['category_name'],
				'D_NAME_FILE' => $photo['file'],
				'D_NAME_PHOTO' => $photo['name'],
				'D_DESCRIPTION_PHOTO' => $photo['description'],
				'D_FOTO_WIDTH' => $photo['width'],
				'D_FOTO_HEIGHT' => $photo['height'],

				'U_EDITED' => $photo['url_edited'],
				'U_FOTO' => $photo['url']
	); // наполняем массив данными для замены по шаблону
}
elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == "upload" && $user->user['id'] != 0 && $user->user['pic_upload'] == true) // иначе если поступила команда на загрузку изображения и все сопутствующие условия выполнены, то формируем блок загрузки изображения
{
	$cur_act = 'upload'; // активный пункт меню - upload
	$array_data = array(
				'NAME_BLOCK' => $lang['photo_title'] . ' - ' . $lang['photo_upload'],
				'L_NAME_PHOTO' => $lang['main_name_of'] . ' ' . $lang['photo_of_photo'],
				'L_DESCRIPTION_PHOTO' => $lang['main_description_of'] . ' ' . $lang['photo_of_photo'],
				'L_NAME_CATEGORY' => $lang['main_name_of'] . ' ' . $lang['category_of_category'],
				'L_UPLOAD_THIS' => $lang['photo_upload'],
				'L_FILE_PHOTO' => $lang['photo_select_file'],

				'D_NAME_CATEGORY' => $photo['category_name'],
				'D_MAX_FILE_SIZE' => $max_size,

				'U_UPLOADED' => $photo['url_uploaded']
	); // наполняем массив данными для замены по шаблону
}
else // если предыдущих команд не поступало, то формируем страницу вывода изображения
{
	$array_data = array(
				'NAME_BLOCK' => $lang['photo_title'] . ' - ' . $photo['name'],
				'DESCRIPTION_BLOCK' => $photo['description'],
				'L_EDIT_BLOCK' => $photo['url_edit_text'],
				'L_CONFIRM_DELETE_BLOCK' => $photo['url_delete_confirm'],
				'L_DELETE_BLOCK' => $photo['url_delete_text'],
				'L_USER_ADD' => $lang['main_user_add'],
				'L_NAME_CATEGORY' => $lang['main_name_of'] . ' ' . $lang['category_of_category'],
				'L_DESCRIPTION_CATEGORY' => $lang['main_description_of'] . ' ' . $lang['category_of_category'],

				'D_USER_ADD' => $photo['user'],
				'D_NAME_CATEGORY' => $photo['category_name'],
				'D_DESCRIPTION_CATEGORY' => $photo['category_description'],
				'D_NAME_PHOTO' => $photo['name'],
				'D_DESCRIPTION_PHOTO' => $photo['description'],
				'D_FOTO_WIDTH' => $photo['width'],
				'D_FOTO_HEIGHT' => $photo['height'],
				'D_RATE_USER' => $photo['rate_user'],
				'D_RATE_MODER' => $photo['rate_moder'],
				'D_RATE_YOU' => $photo['rate_you'],

				'U_USER_ADD' => $photo['user_url'],
				'U_EDIT_BLOCK' => $photo['url_edit'],
				'U_DELETE_BLOCK' => $photo['url_delete'],
				'U_CATEGORY' => $photo['category_url'],
				'U_FOTO' => $photo['url'],

				'IF_EDIT_BLOCK' => $photo['if_edit_photo']
	); // наполняем массив данными для замены по шаблону
}

if ((isset($_REQUEST['subact']) && $_REQUEST['subact'] == "uploaded" && $user->user['id'] != 0 && $user->user['pic_upload'] == true) || (isset($_REQUEST['subact']) && $_REQUEST['subact'] == "delete" && (($temp_foto['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == true))) // если были команды, требующие после себя редиректа (сохранение загруженного изображения или его удаление), то формируем блок редиректа
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
	$title = $work->config['title_name'] . ' - ' . $lang['main_redirect_title']; // дополнительным заголовком будет сообщение о переадресации
	$main_block = $template->create_template('redirect.tpl', $array_data); // формируем центральный блок сайта с сообщением о редиректе
}
else // иначе
{
	$redirect = array(); // созлаем пустой массив редиректа
	$title = $lang['photo_title']; // дополнительным заголовком страницы будет Изображение
	$main_block = $template->create_template($main_tpl, $array_data); // формируем центральный блок согласно ранее оформленных данных
}
echo $template->create_main_template($cur_act, $title, $main_block, $redirect); // выводим сформированную страницу
?>