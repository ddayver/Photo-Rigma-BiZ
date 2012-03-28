<?php
/**
* @file		action/attach.php
* @brief	Вывод фото из галлереи по идентификатору.
* @author	Dark Dayver
* @version	0.1.1
* @date		27/03-2012
* @details	Используется для вывод фото из галлереи по идентификатору и скрытия настоящего пути к файлу.
*/

// Проверка, что файл подключается из индексного, а не набран напрямую в адресной строке
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
else // иначе...
{
	$temp_foto = $db->fetch_array("SELECT * FROM `photo` WHERE `id` =" . $_REQUEST['foto']); // запрашиваем из базы инормацию о выводимом изображении...
	if ($temp_foto) // если информация есть, то...
	{
		$temp_category = $db->fetch_array("SELECT * FROM `category` WHERE `id` = " .  $temp_foto['category']); // запрашиваем из базы информации о категории изображения
		if ($temp_category) // если информация по категории есть, то...
		{
			$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_category['folder'] . '/' . $temp_foto['file']; // формируем полный путь к изображению
			$thumbnail_path = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_category['folder'] . '/' . $temp_foto['file']; // формируем полный путь к эскизу изображения
		}
		else // иначе...
		{
			$temp_foto['file'] = 'no_foto.png'; // формируем вывод для отсустствующего изображения
			$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file']; // включая полный путь
			$thumbnail_path = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_foto['file']; // включая полный путь к эскизу
		}
	}
	else // иначе...
	{
		$temp_foto['file'] = 'no_foto.png'; // формируем вывод для отсустствующего изображения
		$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file']; // включая полный путь
		$thumbnail_path = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_foto['file']; // включая полный путь к эскизу
	}
}

if(!@fopen($temp_path, 'r')) // проверяем доступность файла с изображением, если файл недоступен, то...
{
	$temp_foto['file'] = 'no_foto.png'; // формируем вывод для отсустствующего изображения
	$temp_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file']; // включая полный путь
	$thumbnail_path = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_foto['file']; // включая полный путь к эскизу
}

if (isset($_REQUEST['thumbnail']) && $_REQUEST['thumbnail'] == 1) // если был запрошен эскиз, то...
{
	if($template->Image_Resize($temp_path, $thumbnail_path)) // создаем эскиз, если удалось создать эскиз, то...
	{
		echo $template->Image_Attach($thumbnail_path, $temp_foto['file']); // выводим полученное изображение
	}
	else // иначе выдадим сообщение об ошибке...
	{
		die('Error Image Resize'); // и остановим скрипт
	}
}
else // иначе если надо вывести полное изображение
{
	echo $template->Image_Attach($temp_path, $temp_foto['file']); // выводим полное изображение
}
?>