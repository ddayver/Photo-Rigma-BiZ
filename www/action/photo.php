<?php
/**
* @file		action/photo.php
* @brief	Работа с фото.
* @author	Dark Dayver
* @version	0.2.0
* @date		28/03-2012
* @details	Вывод, редактировани, загрузка и обработка изображений, оценок.
*/

if (IN_GALLERY)
{
	die('HACK!');
}

include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/main.php');
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/menu.php');
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/photo.php');
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/category.php');

if (!isset($_REQUEST['id']) || empty($_REQUEST['id']) || !mb_ereg('^[0-9]+$', $_REQUEST['id']) || $user->user['pic_view'] != true) $photo_id = 0;
else $photo_id = $_REQUEST['id'];

$cur_act = '';
$photo = array();

if ($db2->select(array('file', 'category'), TBL_PHOTO, '`id` = ' . $photo_id))
{
	$temp = $db2->res_row();
	if ($temp)
	{
		if ($db2->select('folder', TBL_CATEGORY, '`id` = ' . $temp['category']))
		{
			$temp2 = $db2->res_row();
			if ($temp2)
			{
				if(!@fopen($work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp2['folder'] . '/' . $temp['file'], 'r')) $photo_id = 0;
			}
			else $photo_id = 0;
		}
		else log_in_file($db2->error, DIE_IF_ERROR);
	}
	else $photo_id = 0;
}
else log_in_file($db2->error, DIE_IF_ERROR);

$main_tpl = 'photo_view.tpl';
$max_photo_w = $work->config['max_photo_w'];
$max_photo_h = $work->config['max_photo_h'];

if ($photo_id != 0)
{
	if ($db2->select('*', TBL_PHOTO, '`id` = ' . $photo_id))
	{
		$temp_foto = $db2->res_row();
		if ($temp_foto)
		{
			if ($db2->select('*', TBL_CATEGORY, '`id` = ' . $temp_foto['category']))
			{
				$temp_category = $db2->res_row();
				if ($temp_category)
				{
					if(isset($_REQUEST['subact']) && $_REQUEST['subact'] == "rate" && ($user->user['pic_rate_user'] == true || $user->user['pic_rate_moder'] == true) && $temp_foto['user_upload'] != $user->user['id'])
					{
						if ($db2->select('rate', TBL_RATE_USER, '`id_foto` = ' . $photo_id . ' AND `id_user` = ' . $user->user['id']))
						{
							$temp = $db2->res_row();
							if ($temp) $rate_user = $temp['rate'];
							else $rate_user = false;
						}
						else log_in_file($db2->error, DIE_IF_ERROR);
						if ($user->user['pic_rate_user'] == true && isset($_POST['rate_user']) && mb_ereg('^[0-9]+$', abs($_POST['rate_user'])) && abs($_POST['rate_user']) <= $work->config['max_rate'] && $rate_user === false)
						{
							$query_rate = array();
							$query_rate['id_foto'] = $photo_id;
							$query_rate['id_user'] = $user->user['id'];
							$query_rate['rate'] = $_POST['rate_user'];
							if (!$db2->insert($query_rate, TBL_RATE_USER, 'ignore')) log_in_file($db2->error, DIE_IF_ERROR);
							if ($db2->select('rate', TBL_RATE_USER, '`id_foto` = ' . $photo_id))
							{
								$temp = $db2->res_arr();
								$rate_user = 0;
								if ($temp)
								{
									foreach ($temp as $val)
									{
										$rate_user += $val['rate'];
									}
									$rate_user = $rate_user/count($temp);
								}
								if (!$db2->update(array('rate_user' => $rate_user), TBL_PHOTO, '`id` = ' . $photo_id)) log_in_file($db2->error, DIE_IF_ERROR);
							}
							else log_in_file($db2->error, DIE_IF_ERROR);
						}

						if ($db2->select('rate', TBL_RATE_MODER, '`id_foto` = ' . $photo_id . ' AND `id_user` = ' . $user->user['id']))
						{
							$temp = $db2->res_row();
							if ($temp) $rate_moder = $temp['rate'];
							else $rate_moder = false;
						}
						else log_in_file($db2->error, DIE_IF_ERROR);
						if ($user->user['pic_rate_moder'] == true && isset($_POST['rate_moder']) && mb_ereg('^[0-9]+$', abs($_POST['rate_moder'])) && abs($_POST['rate_moder']) <= $work->config['max_rate'] && $rate_moder === false)
						{
							$query_rate = array();
							$query_rate['id_foto'] = $photo_id;
							$query_rate['id_user'] = $user->user['id'];
							$query_rate['rate'] = $_POST['rate_user'];
							if (!$db2->insert($query_rate, TBL_RATE_MODER, 'ignore')) log_in_file($db2->error, DIE_IF_ERROR);
							if ($db2->select('rate', TBL_RATE_MODER, '`id_foto` = ' . $photo_id))
							{
								$temp = $db2->res_arr();
								$rate_moder = 0;
								if ($temp)
								{
									foreach ($temp as $val)
									{
										$rate_moder += $val['rate'];
									}
									$rate_moder = $rate_moder/count($temp);
								}
								if (!$db2->update(array('rate_moder' => $rate_moder), TBL_PHOTO, '`id` = ' . $photo_id)) log_in_file($db2->error, DIE_IF_ERROR);
							}
							else log_in_file($db2->error, DIE_IF_ERROR);
						}
					}
					elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'saveedit' && (($temp_foto['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == true))
					{
						if ($db2->select('*', TBL_PHOTO, '`id` = ' . $photo_id))
						{
							$temp_foto = $db2->res_row();
							if ($temp_foto)
							{
								if(!isset($_POST['name_photo']) || empty($_POST['name_photo'])) $photo['name'] = $temp_foto['name'];
								else $photo['name'] = $_POST['name_photo'];

								if(!isset($_POST['description_photo']) || empty($_POST['description_photo'])) $photo['description'] = $temp_foto['description'];
								else $photo['description'] = $_POST['description_photo'];

    							$category = true;

								if(!isset($_POST['name_category']) || !mb_ereg('^[0-9]+$', $_POST['name_category'])) $category = false;
								else
								{
									if ($user->user['cat_user'] == true || $user->user['pic_moderate'] == true) $select_cat = 'WHERE `id` = ' . $_POST['name_category'];
									else $select_cat = 'WHERE `id` != 0 AND `id` =' . $_POST['name_category'];
									if ($db2->select('*', TBL_CATEGORY, $select_cat))
									{
										$temp = $db2->res_row();
										if (!$temp) $category = false;
									}
									else log_in_file($db2->error, DIE_IF_ERROR);
								}

								if($category && $temp_foto['category'] != $_POST['name_category'])
								{
									if ($db2->select('folder', TBL_CATEGORY, '`id` = ' . $temp_foto['category']))
									{
										$temp_old = $db2->res_row();
										if (!$temp_old) log_in_file('Unable to get the category', DIE_IF_ERROR);
									}
									else log_in_file($db2->error, DIE_IF_ERROR);
									if ($db2->select('folder', TBL_CATEGORY, '`id` = ' . $_POST['name_category']))
									{
										$temp_new = $db2->res_row();
										if (!$temp_new) log_in_file('Unable to get the category', DIE_IF_ERROR);
									}
									else log_in_file($db2->error, DIE_IF_ERROR);
									$path_old_photo = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_old['folder'] . '/' . $temp_foto['file'];
									$path_new_photo = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_new['folder'] . '/' . $temp_foto['file'];
									$path_old_thumbnail = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_old['folder'] . '/' . $temp_foto['file'];
									$path_new_thumbnail = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_new['folder'] . '/' . $temp_foto['file'];
									if (!rename($path_old_photo, $path_new_photo)) $photo['category_name'] = $temp_foto['category'];
									else
									{
										if(!rename($path_old_thumbnail, $path_new_thumbnail)) @unlink($path_old_thumbnail);
										$photo['category_name'] = $_POST['name_category'];
									}
								}
								else $photo['category_name'] = $temp_foto['category'];

								if(!get_magic_quotes_gpc())
								{
									$photo['name'] = addslashes($photo['name']);
									$photo['description'] = addslashes($photo['description']);
								}

								if (!$db2->update(array('name' => $photo['name'], 'description' => $photo['description'], 'category' => $photo['category_name']), TBL_PHOTO, '`id` = ' . $photo_id)) log_in_file($db2->error, DIE_IF_ERROR);
							}
							else log_in_file('Unable to get the photo', DIE_IF_ERROR);
						}
						else log_in_file($db2->error, DIE_IF_ERROR);
					}

					if (isset($_REQUEST['subact']) && $_REQUEST['subact'] == "edit" && (($temp_foto['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == true))
					{
						$main_tpl = 'photo_edit.tpl';

						if ($db2->select('*', TBL_PHOTO, '`id` = ' . $photo_id))
						{
							$temp_foto = $db2->res_row();
							if ($temp_foto)
							{
								if ($db2->select('*', TBL_CATEGORY, '`id` = ' . $temp_foto['category']))
								{
									$temp_category = $db2->res_row();
									if ($temp_category)
									{
										$photo['path'] = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_category['folder'] . '/' . $temp_foto['file'];
										$photo['url'] = $work->config['site_url'] . '?action=attach&foto=' . $temp_foto['id'] . '&thumbnail=1';
										$photo['name'] = $temp_foto['name'];
										$photo['file'] = $temp_foto['file'];
										$photo['description'] = $temp_foto['description'];
										if ($db2->select('real_name', TBL_USERS, '`id` = ' . $temp_foto['user_upload']))
										{
											$user_add = $db2->res_row();
											if ($user_add)
											{
												$photo['user'] = $user_add['real_name'];
												$photo['user_url'] = $work->config['site_url']  . '?action=login&subact=profile&uid=' . $temp_foto['user_upload'];
											}
											else
											{
												$photo['user'] = $lang['main_no_user_add'];
												$photo['user_url'] = '';
											}
										}
										else log_in_file($db2->error, DIE_IF_ERROR);

										if ($user->user['cat_user'] == true || $user->user['pic_moderate'] == true) $select_cat = false;
										else $select_cat = ' WHERE `id` != 0';

										if ($db2->select('*', TBL_CATEGORY, $select_cat))
										{
											$temp_category = $db2->res_arr();
											if ($temp_category)
											{
												$photo['category_name'] = '<select name="name_category">';
												foreach ($temp_category as $val)
												{
            										if($val['id'] == $temp_foto['category']) $selected = ' selected'; else $selected = '';
				        		    				if($val['id'] == 0) $val['name'] .= ' ' . $photo['user'];
													$photo['category_name'] .= '<option value="' . $val['id'] . '"' . $selected . '>' . $val['name'] . '</option>';
												}
												$photo['category_name'] .= '</select>';
						   	    				$photo['url_edited'] = $work->config['site_url'] . '?action=photo&subact=saveedit&id=' . $temp_foto['id'];
       											$photo['url_edited_text'] = $lang['photo_save'];
												$max_photo_w = $work->config['temp_photo_w'];
												$max_photo_h = $work->config['temp_photo_h'];
											}
											else log_in_file('Unable to get the category', DIE_IF_ERROR);
										}
										else log_in_file($db2->error, DIE_IF_ERROR);
									}
									else log_in_file('Unable to get the category', DIE_IF_ERROR);
								}
								else log_in_file($db2->error, DIE_IF_ERROR);
							}
							else log_in_file('Unable to get the photo', DIE_IF_ERROR);
						}
						else log_in_file($db2->error, DIE_IF_ERROR);
					}
					elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == "delete" && (($temp_foto['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == true))
					{
						$photo['name'] = $temp_foto['name'];
						if($temp_category['id'] == 0)
						{
							if ($db2->select('COUNT(*) as `count_photo`', TBL_PHOTO, '`id` != ' . $photo_id . ' AND `category` = 0 AND `user_upload` = ' . $temp_foto['user_upload']))
							{
								$temp = $db2->res_row();
								if (isset($temp['count_photo']) && $temp['count_photo'] > 0) $photo['category_url'] = $work->config['site_url'] . '?action=category&cat=user&id=' . $temp_foto['user_upload'];
								else
								{
									if ($db2->select('COUNT(*) as `count_photo`', TBL_PHOTO, '`id` != ' . $photo_id . ' AND `category` = 0'))
									{
										$temp = $db2->res_row();
										if (isset($temp['count_photo']) && $temp['count_photo'] > 0) $photo['category_url'] = $work->config['site_url'] . '?action=category&cat=user';
										else
										{
											if ($db2->select('COUNT(*) as `count_photo`', TBL_PHOTO, '`id` != ' . $photo_id))
											{
												$temp = $db2->res_row();
												if (isset($temp['count_photo']) && $temp['count_photo'] > 0) $photo['category_url'] = $work->config['site_url'] . '?action=category';
												else $photo['category_url'] = $work->config['site_url'];
											}
											else log_in_file($db2->error, DIE_IF_ERROR);
										}
									}
									else log_in_file($db2->error, DIE_IF_ERROR);
								}
							}
							else log_in_file($db2->error, DIE_IF_ERROR);
						}
						else
						{
							if ($db2->select('COUNT(*) as `count_photo`', TBL_PHOTO, '`id` != ' . $photo_id . ' AND `category` = ' . $temp_category['id']))
							{
								$temp = $db2->res_row();
								if (isset($temp['count_photo']) && $temp['count_photo'] > 0) $photo['category_url'] = $work->config['site_url'] . '?action=category&cat=' . $temp_category['id'];
								else
								{
									if ($db2->select('COUNT(*) as `count_photo`', TBL_PHOTO, '`id` != ' . $photo_id))
									{
										$temp = $db2->res_row();
										if (isset($temp['count_photo']) && $temp['count_photo'] > 0) $photo['category_url'] = $work->config['site_url'] . '?action=category';
										else $photo['category_url'] = $work->config['site_url'];
									}
									else log_in_file($db2->error, DIE_IF_ERROR);
								}
							}
							else log_in_file($db2->error, DIE_IF_ERROR);
						}

						if($work->del_photo($photo_id))
						{
							$redirect_url = $photo['category_url'];
							$redirect_time = 5;
							$redirect_message = $lang['photo_title'] . ' ' . $photo['name'] . ' ' . $lang['photo_complite_delete'];
						}
						else
						{
							$redirect_url = $work->config['site_url'] . '?action=photo&id=' . $photo_id;
							$redirect_time = 5;
							$redirect_message = $lang['photo_title'] . ' ' . $photo['name'] . ' ' . $lang['photo_error_delete'];
						}
					}
					else
					{

						if ($db2->select('*', TBL_PHOTO, '`id` = ' . $photo_id))
						{
							$temp_foto = $db2->res_row();
							if ($temp_foto)
							{
								if ($db2->select('*', TBL_CATEGORY, '`id` = ' . $temp_foto['category']))
								{
									$temp_category = $db2->res_row();
									if ($temp_category)
									{
										$photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_category['folder'] . '/' . $temp_foto['file'];
										$photo['url'] = $work->config['site_url'] . '?action=attach&foto=' . $temp_foto['id'];
										$photo['name'] = $temp_foto['name'];
										$photo['description'] = $temp_foto['description'];
										$photo['category_name'] = $temp_category['name'];
										$photo['category_description'] = $temp_category['description'];
										
										if (($temp_foto['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == true)
										{
				    	    				$photo['url_edit'] = $work->config['site_url'] . '?action=photo&subact=edit&id=' . $temp_foto['id'];
				        					$photo['url_edit_text'] = $lang['photo_edit'];
    	    								$photo['url_delete'] = $work->config['site_url'] . '?action=photo&subact=delete&id=' . $temp_foto['id'];
				        					$photo['url_delete_text'] = $lang['photo_delete'];
        									$photo['url_delete_confirm'] = $lang['photo_confirm_delete'] . ' ' . $photo['name'];
											$photo['if_edit_photo'] = true;
										}
										else
										{
				        					$photo['url_edit'] = '';
	        								$photo['url_edit_text'] = '';
				    	    				$photo['url_delete'] = '';
        									$photo['url_delete_text'] = '';
				        					$photo['url_delete_confirm'] = '';
    	    								$photo['if_edit_photo'] = false;
										}

										if ($db2->select('real_name', TBL_USERS, '`id` = ' . $temp_foto['user_upload']))
										{
											$user_add = $db2->res_row();
											if ($user_add)
											{
												$photo['user'] = $user_add['real_name'];
												$photo['user_url'] = $work->config['site_url']  . '?action=login&subact=profile&uid=' . $temp_foto['user_upload'];
											}
											else
											{
												$photo['user'] = $lang['main_no_user_add'];
												$photo['user_url'] = '';
											}
										}
										else log_in_file($db2->error, DIE_IF_ERROR);

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
									else log_in_file('Unable to get the category', DIE_IF_ERROR);
								}
								else log_in_file($db2->error, DIE_IF_ERROR);
							}
							else log_in_file('Unable to get the photo', DIE_IF_ERROR);
						}
						else log_in_file($db2->error, DIE_IF_ERROR);
					}
				}
				else log_in_file('Unable to get the category', DIE_IF_ERROR);
			}
			else log_in_file($db2->error, DIE_IF_ERROR);
		}
		else log_in_file('Unable to get the photo', DIE_IF_ERROR);
	}
	else log_in_file($db2->error, DIE_IF_ERROR);
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
					$template->image_resize($photo['path'], $photo['thumbnail_path']); // выполняем команду на создание эскиза к изображению
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
				'L_CONFIRM_DELETE_BLOCK' => isset($photo['url_delete_confirm']) ? $photo['url_delete_confirm'] : '',
				'L_DELETE_BLOCK' => isset($photo['url_delete_text']) ? $photo['url_delete_text'] : '',
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
				'U_DELETE_BLOCK' => isset($photo['url_delete']) ? $photo['url_delete'] : '',
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