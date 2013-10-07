<?php
/**
* @file		action/attach.php
* @brief	Вывод фото из галлереи по идентификатору.
* @author	Dark Dayver
* @version	0.2.0
* @date		28/03-2012
* @details	Используется для вывод фото из галлереи по идентификатору и скрытия настоящего пути к файлу.
*/
/// @cond
if (IN_GALLERY !== true)
{
	die('HACK!');
}
/// @endcond

/// \todo Убрать заглушку после перехода на новый класс формирования шаблонов
$template_TMP = true; // Заглушка

/// Запретить вывод шапки страницы
$header_footer = false;
/// Запретить вывод подвала страницы
$template_output = false;

/// @cond
if (!$work->check_get('foto', true, true, '^[0-9]+\$', true) || $user->user['pic_view'] == false) $temp_photo = $work->no_photo();
else
{
	if ($db->select('*', TBL_PHOTO, '`id` = ' . $_GET['foto']))
	{
		$temp_photo = $db->res_row();
		if ($temp_photo)
		{
			if ($db->select('*', TBL_CATEGORY, '`id` = ' . $temp_photo['category']))
			{
				$temp_category = $db->res_row();
				if ($temp_category)
				{
					$temp_photo['full_path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_category['folder'] . '/' . $temp_photo['file'];
					$temp_photo['thumbnail_path'] = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_category['folder'] . '/' . $temp_photo['file'];
				}
				else $temp_photo = $work->no_photo();
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}
		else $temp_photo = $work->no_photo();
	}
	else log_in_file($db->error, DIE_IF_ERROR);
}

if (!@fopen($temp_photo['full_path'], 'r')) $temp_photo = $work->no_photo();

if ($work->check_get('thumbnail', true, true) && $_GET['thumbnail'] == 1)
{
	if ($work->image_resize($temp_photo['full_path'], $temp_photo['thumbnail_path'])) echo $work->image_attach($temp_photo['thumbnail_path'], $temp_photo['file']);
	else log_in_file('Error Image Resize: ' . $temp_photo['full_path'], DIE_IF_ERROR);
}
else echo $work->image_attach($temp_photo['full_path'], $temp_photo['file']);
/// @endcond
?>
