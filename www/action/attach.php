<?php
/**
* @file		action/attach.php
* @brief	Вывод фото из галлереи по идентификатору.
* @author	Dark Dayver
* @version	0.2.0
* @date		28/03-2012
* @details	Используется для вывод фото из галлереи по идентификатору и скрытия настоящего пути к файлу.
*/

if (IN_GALLERY)
{
	die('HACK!');
}

if (!isset($_REQUEST['foto']) || empty($_REQUEST['foto']) || $user->user['pic_view'] == false) // проверка - указан ли в запросе идентификатор выводимого изображения и имеет ли пользователь право на просмотр изображения, если не указан, то...
{
	$temp_foto['file'] = 'no_foto.png'; // формируем вывод для отсустствующего изображения
	$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file']; // включая полный путь
	$thumbnail_path = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_foto['file']; // включая полный путь к эскизу
}
else
{
	if ($db2->select('*', TBL_PHOTO, '`id` = ' . $_REQUEST['foto']))
	{
		$temp_foto = $db2->res_row();
		if ($temp_foto)
		{
			if ($db2->select('*', TBL_CATEGORY, '`id` = ' . $temp_foto['category']))
			{
				$temp_category = $db2->res_row();
				if ($temp_category)
				{
					$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_category['folder'] . '/' . $temp_foto['file'];
					$thumbnail_path = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_category['folder'] . '/' . $temp_foto['file'];
				}
				else
				{
					$temp_foto['file'] = 'no_foto.png';
					$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file'];
					$thumbnail_path = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_foto['file'];
				}
			}
			else log_in_file($db2->error, DIE_IF_ERROR);
		}
		else
		{
			$temp_foto['file'] = 'no_foto.png';
			$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file'];
			$thumbnail_path = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_foto['file'];
		}
	}
	else log_in_file($db2->error, DIE_IF_ERROR);
}

if(!@fopen($temp_path, 'r')) // проверяем доступность файла с изображением, если файл недоступен, то...
{
	$temp_foto['file'] = 'no_foto.png'; // формируем вывод для отсустствующего изображения
	$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file']; // включая полный путь
	$thumbnail_path = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_foto['file']; // включая полный путь к эскизу
}

if (isset($_REQUEST['thumbnail']) && $_REQUEST['thumbnail'] == 1) // если был запрошен эскиз, то...
{
	if($template->image_resize($temp_path, $thumbnail_path)) // создаем эскиз, если удалось создать эскиз, то...
	{
		echo $template->image_attach($thumbnail_path, $temp_foto['file']); // выводим полученное изображение
	}
	else // иначе выдадим сообщение об ошибке...
	{
		die('Error Image Resize'); // и остановим скрипт
	}
}
else // иначе если надо вывести полное изображение
{
	echo $template->image_attach($temp_path, $temp_foto['file']); // выводим полное изображение
}
?>
