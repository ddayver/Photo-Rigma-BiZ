<?php
/**
 * @file        action/photo.php
 * @brief       Работа с фото.
 * @author      Dark Dayver
 * @version     0.2.0
 * @date        28/03-2012
 * @details     Вывод, редактировани, загрузка и обработка изображений, оценок.
 */
/// @cond
if (IN_GALLERY !== TRUE)
{
	die('HACK!');
}
/// @endcond

include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/main.php');

/// \todo Убрать заглушку после перехода на новый класс формирования шаблонов
$template_TMP = TRUE;

/// @cond
if (!$work->check_get('id', TRUE, TRUE, '^[0-9]+\$') || $user->user['pic_view'] != TRUE) $photo_id = 0;
else $photo_id = $_GET['id'];

$photo = array();

if ($db->select(array('file', 'category'), TBL_PHOTO, '`id` = ' . $photo_id))
{
	$temp = $db->res_row();
	if ($temp)
	{
		if ($db->select('folder', TBL_CATEGORY, '`id` = ' . $temp['category']))
		{
			$temp2 = $db->res_row();
			if ($temp2)
			{
				if (!@fopen($work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp2['folder'] . '/' . $temp['file'], 'r')) $photo_id = 0;
			}
			else $photo_id = 0;
		}
		else log_in_file($db->error, DIE_IF_ERROR);
	}
	else $photo_id = 0;
}
else log_in_file($db->error, DIE_IF_ERROR);

$template_new->template_file = 'photo.html';
$max_photo_w = $work->config['max_photo_w'];
$max_photo_h = $work->config['max_photo_h'];
$template_new->add_if_ar(array(
	'EDIT_BLOCK' => FALSE,
	'RATE_PHOTO' => FALSE,
	'RATE_USER'  => FALSE,
	'RATE_MODER' => FALSE
));

if ($photo_id != 0)
{
	if ($db->select('*', TBL_PHOTO, '`id` = ' . $photo_id))
	{
		$temp_photo = $db->res_row();
		if ($temp_photo)
		{
			if ($db->select('*', TBL_CATEGORY, '`id` = ' . $temp_photo['category']))
			{
				$temp_category = $db->res_row();
				if ($temp_category)
				{
					if ($work->check_get('subact', TRUE, TRUE) && $_GET['subact'] == "rate" && ($user->user['pic_rate_user'] == TRUE || $user->user['pic_rate_moder'] == TRUE) && $temp_photo['user_upload'] != $user->user['id'])
					{
						if ($db->select('rate', TBL_RATE_USER, '`id_foto` = ' . $photo_id . ' AND `id_user` = ' . $user->user['id']))
						{
							$temp = $db->res_row();
							if ($temp) $rate_user = $temp['rate'];
							else $rate_user = FALSE;
						}
						else log_in_file($db->error, DIE_IF_ERROR);
						if ($user->user['pic_rate_user'] == TRUE && $work->check_post('rate_user', TRUE, TRUE, '^\-?[0-9]+\$') && abs($_POST['rate_user']) <= $work->config['max_rate'] && $rate_user === FALSE)
						{
							$query_rate = array();
							$query_rate['id_foto'] = $photo_id;
							$query_rate['id_user'] = $user->user['id'];
							$query_rate['rate'] = $_POST['rate_user'];
							if (!$db->insert($query_rate, TBL_RATE_USER, 'ignore')) log_in_file($db->error, DIE_IF_ERROR);
							if ($db->select('rate', TBL_RATE_USER, '`id_foto` = ' . $photo_id))
							{
								$temp = $db->res_arr();
								$rate_user = 0;
								if ($temp)
								{
									foreach ($temp as $val)
									{
										$rate_user += $val['rate'];
									}
									$rate_user = $rate_user / count($temp);
								}
								if (!$db->update(array('rate_user' => $rate_user), TBL_PHOTO, '`id` = ' . $photo_id)) log_in_file($db->error, DIE_IF_ERROR);
							}
							else log_in_file($db->error, DIE_IF_ERROR);
						}

						if ($db->select('rate', TBL_RATE_MODER, '`id_foto` = ' . $photo_id . ' AND `id_user` = ' . $user->user['id']))
						{
							$temp = $db->res_row();
							if ($temp) $rate_moder = $temp['rate'];
							else $rate_moder = FALSE;
						}
						else log_in_file($db->error, DIE_IF_ERROR);
						if ($user->user['pic_rate_moder'] == TRUE && $work->check_post('rate_moder', TRUE, TRUE, '^\-?[0-9]+\$') && abs($_POST['rate_moder']) <= $work->config['max_rate'] && $rate_moder === FALSE)
						{
							$query_rate = array();
							$query_rate['id_foto'] = $photo_id;
							$query_rate['id_user'] = $user->user['id'];
							$query_rate['rate'] = $_POST['rate_user'];
							if (!$db->insert($query_rate, TBL_RATE_MODER, 'ignore')) log_in_file($db->error, DIE_IF_ERROR);
							if ($db->select('rate', TBL_RATE_MODER, '`id_foto` = ' . $photo_id))
							{
								$temp = $db->res_arr();
								$rate_moder = 0;
								if ($temp)
								{
									foreach ($temp as $val)
									{
										$rate_moder += $val['rate'];
									}
									$rate_moder = $rate_moder / count($temp);
								}
								if (!$db->update(array('rate_moder' => $rate_moder), TBL_PHOTO, '`id` = ' . $photo_id)) log_in_file($db->error, DIE_IF_ERROR);
							}
							else log_in_file($db->error, DIE_IF_ERROR);
						}
					}
					elseif ($work->check_get('subact', TRUE, TRUE) && $_GET['subact'] == 'saveedit' && (($temp_photo['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == TRUE))
					{
						if ($db->select('*', TBL_PHOTO, '`id` = ' . $photo_id))
						{
							$temp_photo = $db->res_row();
							if ($temp_photo)
							{
								if (!$work->check_post('name_photo', TRUE, TRUE)) $photo['name'] = $temp_photo['name'];
								else $photo['name'] = trim(htmlentities($_POST['name_photo']));

								if (!$work->check_post('description_photo', TRUE, TRUE)) $photo['description'] = $temp_photo['description'];
								else $photo['description'] = trim(htmlentities($_POST['description_photo']));

								$category = TRUE;

								if (!$work->check_post('name_category', TRUE, TRUE, '^[0-9]+\$')) $category = FALSE;
								else
								{
									if ($user->user['cat_user'] == TRUE || $user->user['pic_moderate'] == TRUE) $select_cat = '`id` = ' . $_POST['name_category'];
									else $select_cat = '`id` != 0 AND `id` =' . $_POST['name_category'];
									if ($db->select('*', TBL_CATEGORY, $select_cat))
									{
										$temp = $db->res_row();
										if (!$temp) $category = FALSE;
									}
									else log_in_file($db->error, DIE_IF_ERROR);
								}

								if ($category && $temp_photo['category'] != $_POST['name_category'])
								{
									if ($db->select('folder', TBL_CATEGORY, '`id` = ' . $temp_photo['category']))
									{
										$temp_old = $db->res_row();
										if (!$temp_old) log_in_file('Unable to get the category', DIE_IF_ERROR);
									}
									else log_in_file($db->error, DIE_IF_ERROR);
									if ($db->select('folder', TBL_CATEGORY, '`id` = ' . $_POST['name_category']))
									{
										$temp_new = $db->res_row();
										if (!$temp_new) log_in_file('Unable to get the category', DIE_IF_ERROR);
									}
									else log_in_file($db->error, DIE_IF_ERROR);
									$path_old_photo = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_old['folder'] . '/' . $temp_photo['file'];
									$path_new_photo = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_new['folder'] . '/' . $temp_photo['file'];
									$path_old_thumbnail = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_old['folder'] . '/' . $temp_photo['file'];
									$path_new_thumbnail = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_new['folder'] . '/' . $temp_photo['file'];
									if (!rename($path_old_photo, $path_new_photo)) $photo['category_name'] = $temp_photo['category'];
									else
									{
										if (!rename($path_old_thumbnail, $path_new_thumbnail)) @unlink($path_old_thumbnail);
										$photo['category_name'] = $_POST['name_category'];
									}
								}
								else $photo['category_name'] = $temp_photo['category'];

								if (!get_magic_quotes_gpc())
								{
									$photo['name'] = addslashes($photo['name']);
									$photo['description'] = addslashes($photo['description']);
								}

								if (!$db->update(array('name' => $photo['name'], 'description' => $photo['description'], 'category' => $photo['category_name']), TBL_PHOTO, '`id` = ' . $photo_id)) log_in_file($db->error, DIE_IF_ERROR);
							}
							else log_in_file('Unable to get the photo', DIE_IF_ERROR);
						}
						else log_in_file($db->error, DIE_IF_ERROR);
					}

					if ($work->check_get('subact', TRUE, TRUE) && $_GET['subact'] == "edit" && (($temp_photo['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == TRUE))
					{
						$template_new->add_case('PHOTO_BLOCK', 'EDIT_PHOTO');
						if ($db->select('*', TBL_PHOTO, '`id` = ' . $photo_id))
						{
							$temp_photo = $db->res_row();
							if ($temp_photo)
							{
								if ($db->select('*', TBL_CATEGORY, '`id` = ' . $temp_photo['category']))
								{
									$temp_category = $db->res_row();
									if ($temp_category)
									{
										$photo['path'] = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_category['folder'] . '/' . $temp_photo['file'];
										if ($db->select('real_name', TBL_USERS, '`id` = ' . $temp_photo['user_upload']))
										{
											$user_add = $db->res_row();
											if ($user_add)
											{
												$photo['user'] = $user_add['real_name'];
												$photo['user_url'] = $work->config['site_url'] . '?action=profile&amp;subact=profile&amp;uid=' . $temp_photo['user_upload'];
											}
											else
											{
												$photo['user'] = $lang['main']['no_user_add'];
												$photo['user_url'] = '';
											}
										}
										else log_in_file($db->error, DIE_IF_ERROR);

										if ($user->user['cat_user'] == TRUE || $user->user['pic_moderate'] == TRUE) $select_cat = FALSE;
										else $select_cat = '`id` != 0';

										if ($db->select('*', TBL_CATEGORY, $select_cat))
										{
											$temp_category = $db->res_arr();
											if ($temp_category)
											{
												foreach ($temp_category as $key => $val)
												{
													if ($val['id'] == $temp_photo['category']) $selected = ' selected="selected"';
													else $selected = '';
													if ($val['id'] == 0) $val['name'] .= ' ' . $photo['user'];
													$template_new->add_string_ar(array(
															'D_ID_CATEGORY'   => $val['id'],
															'D_NAME_CATEGORY' => $val['name'],
															'D_SELECTED'      => $selected
														), 'SELECT_CATEGORY[' . $key . ']');
												}
												$max_photo_w = $work->config['temp_photo_w'];
												$max_photo_h = $work->config['temp_photo_h'];
												$template_new->add_string_ar(array(
													'L_NAME_BLOCK'        => $lang['photo']['edit'] . ' - ' . $temp_photo['name'],
													'L_NAME_PHOTO'        => $lang['main']['name_of'] . ' ' . $lang['photo']['of_photo'],
													'L_DESCRIPTION_PHOTO' => $lang['main']['description_of'] . ' ' . $lang['photo']['of_photo'],
													'L_NAME_CATEGORY'     => $lang['main']['name_of'] . ' ' . $lang['category']['of_category'],
													'L_NAME_FILE'         => $lang['photo']['filename'],
													'L_EDIT_THIS'         => $lang['photo']['save'],
													'D_NAME_FILE'         => $temp_photo['file'],
													'D_NAME_PHOTO'        => $temp_photo['name'],
													'D_DESCRIPTION_PHOTO' => $temp_photo['description'],
													'U_EDITED'            => $work->config['site_url'] . '?action=photo&amp;subact=saveedit&amp;id=' . $temp_photo['id'],
													'U_PHOTO'             => $work->config['site_url'] . '?action=attach&amp;foto=' . $temp_photo['id'] . '&amp;thumbnail=1'
												));
											}
											else log_in_file('Unable to get the category', DIE_IF_ERROR);
										}
										else log_in_file($db->error, DIE_IF_ERROR);
									}
									else log_in_file('Unable to get the category', DIE_IF_ERROR);
								}
								else log_in_file($db->error, DIE_IF_ERROR);
							}
							else log_in_file('Unable to get the photo', DIE_IF_ERROR);
						}
						else log_in_file($db->error, DIE_IF_ERROR);
					}
					elseif ($work->check_get('subact', TRUE, TRUE) && $_GET['subact'] == "delete" && (($temp_photo['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == TRUE))
					{
						$photo['name'] = $temp_photo['name'];
						if ($temp_category['id'] == 0)
						{
							if ($db->select('COUNT(*) as `count_photo`', TBL_PHOTO, '`id` != ' . $photo_id . ' AND `category` = 0 AND `user_upload` = ' . $temp_photo['user_upload']))
							{
								$temp = $db->res_row();
								if (isset($temp['count_photo']) && $temp['count_photo'] > 0) $photo['category_url'] = $work->config['site_url'] . '?action=category&cat=user&id=' . $temp_photo['user_upload'];
								else
								{
									if ($db->select('COUNT(*) as `count_photo`', TBL_PHOTO, '`id` != ' . $photo_id . ' AND `category` = 0'))
									{
										$temp = $db->res_row();
										if (isset($temp['count_photo']) && $temp['count_photo'] > 0) $photo['category_url'] = $work->config['site_url'] . '?action=categorycat=user';
										else
										{
											if ($db->select('COUNT(*) as `count_photo`', TBL_PHOTO, '`id` != ' . $photo_id))
											{
												$temp = $db->res_row();
												if (isset($temp['count_photo']) && $temp['count_photo'] > 0) $photo['category_url'] = $work->config['site_url'] . '?action=category';
												else $photo['category_url'] = $work->config['site_url'];
											}
											else log_in_file($db->error, DIE_IF_ERROR);
										}
									}
									else log_in_file($db->error, DIE_IF_ERROR);
								}
							}
							else log_in_file($db->error, DIE_IF_ERROR);
						}
						else
						{
							if ($db->select('COUNT(*) as `count_photo`', TBL_PHOTO, '`id` != ' . $photo_id . ' AND `category` = ' . $temp_category['id']))
							{
								$temp = $db->res_row();
								if (isset($temp['count_photo']) && $temp['count_photo'] > 0) $photo['category_url'] = $work->config['site_url'] . '?action=category&cat=' . $temp_category['id'];
								else
								{
									if ($db->select('COUNT(*) as `count_photo`', TBL_PHOTO, '`id` != ' . $photo_id))
									{
										$temp = $db->res_row();
										if (isset($temp['count_photo']) && $temp['count_photo'] > 0) $photo['category_url'] = $work->config['site_url'] . '?action=category';
										else $photo['category_url'] = $work->config['site_url'];
									}
									else log_in_file($db->error, DIE_IF_ERROR);
								}
							}
							else log_in_file($db->error, DIE_IF_ERROR);
						}

						if ($work->del_photo($photo_id))
						{
							header('Location: ' . $photo['category_url']);
							log_in_file('Hack attempt!');
						}
						else
						{
							header('Location: ' . $work->config['site_url'] . '?action=photo&id=' . $photo_id);
							log_in_file('Hack attempt!');
						}
					}
					else
					{
						if ($db->select('*', TBL_PHOTO, '`id` = ' . $photo_id))
						{
							$temp_photo = $db->res_row();
							if ($temp_photo)
							{
								if ($db->select('*', TBL_CATEGORY, '`id` = ' . $temp_photo['category']))
								{
									$temp_category = $db->res_row();
									if ($temp_category)
									{
										$template_new->add_case('PHOTO_BLOCK', 'VIEW_PHOTO');
										$template_new->add_string_ar(array(
											'L_NAME_BLOCK'           => $lang['photo']['title'] . ' - ' . $temp_photo['name'],
											'L_DESCRIPTION_BLOCK'    => $temp_photo['description'],
											'L_USER_ADD'             => $lang['main']['user_add'],
											'L_NAME_CATEGORY'        => $lang['main']['name_of'] . ' ' . $lang['category']['of_category'],
											'L_DESCRIPTION_CATEGORY' => $lang['main']['description_of'] . ' ' . $lang['category']['of_category'],
											'D_NAME_PHOTO'           => $temp_photo['name'],
											'D_DESCRIPTION_PHOTO'    => $temp_photo['description'],
											'U_PHOTO'                => $work->config['site_url'] . '?action=attach&amp;foto=' . $temp_photo['id']
										));
										$photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_category['folder'] . '/' . $temp_photo['file'];

										if (($temp_photo['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == TRUE)
										{
											$template_new->add_string_ar(array(
												'L_EDIT_BLOCK'           => $lang['photo']['edit'],
												'L_CONFIRM_DELETE_BLOCK' => $lang['photo']['confirm_delete'] . ' ' . $temp_photo['name'],
												'L_DELETE_BLOCK'         => $lang['photo']['delete'],
												'U_EDIT_BLOCK'           => $work->config['site_url'] . '?action=photo&amp;subact=edit&amp;id=' . $temp_photo['id'],
												'U_DELETE_BLOCK'         => $work->config['site_url'] . '?action=photo&amp;subact=delete&amp;id=' . $temp_photo['id']
											));
											$template_new->add_if('EDIT_BLOCK', TRUE);
										}
										if ($db->select('real_name', TBL_USERS, '`id` = ' . $temp_photo['user_upload']))
										{
											$user_add = $db->res_row();
											if ($user_add)
											{
												$template_new->add_string_ar(array(
													'D_USER_ADD' => $user_add['real_name'],
													'U_USER_ADD' => $work->config['site_url'] . '?action=profile&amp;subact=profile&amp;uid=' . $temp_photo['user_upload']
												));
											}
											else
											{
												$template_new->add_string_ar(array(
													'D_USER_ADD' => $lang['main']['no_user_add'],
													'U_USER_ADD' => ''
												));
											}
										}
										else log_in_file($db->error, DIE_IF_ERROR);
										if ($temp_category['id'] == 0)
										{
											$template_new->add_string_ar(array(
												'D_NAME_CATEGORY'        => $temp_category['name'] . ' ' . $user_add['real_name'],
												'D_DESCRIPTION_CATEGORY' => $temp_category['description'] . ' ' . $user_add['real_name'],
												'U_CATEGORY'             => $work->config['site_url'] . '?action=category&amp;cat=user&amp;id=' . $temp_photo['user_upload']
											));
										}
										else
										{
											$template_new->add_string_ar(array(
												'D_NAME_CATEGORY'        => $temp_category['name'],
												'D_DESCRIPTION_CATEGORY' => $temp_category['description'],
												'U_CATEGORY'             => $work->config['site_url'] . '?action=category&amp;cat=' . $temp_category['id']
											));
										}
										$template_new->add_string_ar(array(
											'D_RATE_USER'  => $lang['photo']['rate_user'] . ': ' . $temp_photo['rate_user'],
											'D_RATE_MODER' => $lang['photo']['rate_moder'] . ': ' . $temp_photo['rate_moder']
										));
										if ($user->user['pic_rate_user'] == TRUE)
										{
											if ($db->select('rate', TBL_RATE_USER, '`id_foto` = ' . $photo_id . ' AND `id_user` = ' . $user->user['id']))
											{
												$temp_rate = $db->res_row();
												if ($temp_rate) $user_rate = FALSE;
												else $user_rate = TRUE;
											}
											else log_in_file($db->error, DIE_IF_ERROR);
											if ($user_rate !== FALSE)
											{
												$template_new->add_if('RATE_USER', TRUE);
												$key = 0;
												for ($i = -$work->config['max_rate']; $i <= $work->config['max_rate']; $i++)
												{
													if ($i == 0) $selected = ' selected="selected"';
													else $selected = '';
													$template_new->add_string_ar(array(
															'D_LVL_RATE' => $i,
															'D_SELECTED' => $selected
														), 'SELECT_USER_RATE[' . $key . ']');
													$key++;
												}
											}
										}
										else
										{
											$user_rate = FALSE;
										}

										if ($user->user['pic_rate_moder'] == TRUE)
										{
											if ($db->select('rate', TBL_RATE_MODER, '`id_foto` = ' . $photo_id . ' AND `id_user` = ' . $user->user['id']))
											{
												$temp_rate = $db->res_row();
												if ($temp_rate) $moder_rate = FALSE;
												else $moder_rate = TRUE;
											}
											else log_in_file($db->error, DIE_IF_ERROR);
											if ($moder_rate !== FALSE)
											{
												$template_new->add_if('RATE_MODER', TRUE);
												$key = 0;
												for ($i = -$work->config['max_rate']; $i <= $work->config['max_rate']; $i++)
												{
													if ($i == 0) $selected = ' selected="selected"';
													else $selected = '';
													$template_new->add_string_ar(array(
															'D_LVL_RATE' => $i,
															'D_SELECTED' => $selected
														), 'SELECT_MODER_RATE[' . $key . ']');
													$key++;
												}
											}
										}
										else
										{
											$moder_rate = FALSE;
										}

										if ((($user->user['pic_rate_user'] == TRUE || $user->user['pic_rate_moder'] == TRUE) && $temp_photo['user_upload'] != $user->user['id']) && ($user_rate !== FALSE || $moder_rate !== FALSE))
										{
											$template_new->add_if('RATE_PHOTO', TRUE);
											$template_new->add_string_ar(array(
												'L_RATE'       => $lang['photo']['rate_you'],
												'L_USER_RATE'  => $lang['photo']['if_user'],
												'L_MODER_RATE' => $lang['photo']['if_moder'],
												'L_RATE_THIS'  => $lang['photo']['rate'],
												'U_RATE'       => $work->config['site_url'] . '?action=photo&amp;subact=rate&amp;id=' . $photo_id
											));
										}
									}
									else log_in_file('Unable to get the category', DIE_IF_ERROR);
								}
								else log_in_file($db->error, DIE_IF_ERROR);
							}
							else log_in_file('Unable to get the photo', DIE_IF_ERROR);
						}
						else log_in_file($db->error, DIE_IF_ERROR);
					}
				}
				else log_in_file('Unable to get the category', DIE_IF_ERROR);
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}
		else log_in_file('Unable to get the photo', DIE_IF_ERROR);
	}
	else log_in_file($db->error, DIE_IF_ERROR);
}
else
{
	if ($work->check_get('subact', TRUE, TRUE) && $_GET['subact'] == "upload" && $user->user['id'] != 0 && $user->user['pic_upload'] == TRUE)
	{
		$template_new->add_case('PHOTO_BLOCK', 'UPLOAD_PHOTO');
		$max_size_php = $work->return_bytes(ini_get('post_max_size'));
		$max_size = $work->return_bytes($work->config['max_file_size']);
		if ($max_size > $max_size_php) $max_size = $max_size_php;
		if ($user->user['cat_user'] == TRUE) $select_cat = FALSE;
		else $select_cat = '`id` != 0';

		if ($db->select('*', TBL_CATEGORY, $select_cat))
		{
			$temp_category = $db->res_arr();
			if ($temp_category)
			{
				foreach ($temp_category as $key => $val)
				{
					if ($val['id'] == 0)
					{
						$val['name'] .= ' ' . $user->user['real_name'];
						$selected = ' selected="selected"';
					}
					else $selected = '';
					$template_new->add_string_ar(array(
							'D_ID_CATEGORY'   => $val['id'],
							'D_NAME_CATEGORY' => $val['name'],
							'D_SELECTED'      => $selected
						), 'UPLOAD_CATEGORY[' . $key . ']');
				}
				$template_new->add_string_ar(array(
					'L_NAME_BLOCK'        => $lang['photo']['title'] . ' - ' . $lang['photo']['upload'],
					'L_NAME_PHOTO'        => $lang['main']['name_of'] . ' ' . $lang['photo']['of_photo'],
					'L_DESCRIPTION_PHOTO' => $lang['main']['description_of'] . ' ' . $lang['photo']['of_photo'],
					'L_NAME_CATEGORY'     => $lang['main']['name_of'] . ' ' . $lang['category']['of_category'],
					'L_UPLOAD_THIS'       => $lang['photo']['upload'],
					'L_FILE_PHOTO'        => $lang['photo']['select_file'],
					'D_MAX_FILE_SIZE'     => $max_size,
					'U_UPLOADED'          => $work->config['site_url'] . '?action=photo&amp;subact=uploaded'
				));
			}
			else log_in_file('Unable to get the category', DIE_IF_ERROR);
		}
		else log_in_file($db->error, DIE_IF_ERROR);
	}
	elseif ($work->check_get('subact', TRUE, TRUE) && $_GET['subact'] == "uploaded" && $user->user['id'] != 0 && $user->user['pic_upload'] == TRUE)
	{
		$submit_upload = TRUE;
		$max_size_php = $work->return_bytes(ini_get('post_max_size'));
		$max_size = $work->return_bytes($work->config['max_file_size']);
		if ($max_size > $max_size_php) $max_size = $max_size_php;

		if (!$work->check_post('name_photo', TRUE, TRUE)) $photo['name'] = $lang['photo']['no_name'] . ' (' . $work->encodename(basename($_FILES['file_photo']['name'])) . ')';
		else $photo['name'] = $_POST['name_photo'];

		if (!$work->check_post('description_photo', TRUE, TRUE)) $photo['description'] = $lang['photo']['no_description'] . ' (' . $work->encodename(basename($_FILES['file_photo']['name'])) . ')';
		else $photo['description'] = $_POST['description_photo'];

		if (!$work->check_post('name_category', TRUE, FALSE, '^[0-9]+\$')) $submit_upload = FALSE;
		else
		{
			if ($user->user['cat_user'] == TRUE || $user->user['pic_moderate'] == TRUE) $select_cat = '`id` = ' . $_POST['name_category'];
			else $select_cat = '`id` != 0 AND `id` = ' . $_POST['name_category'];
			if ($db->select('*', TBL_CATEGORY, $select_cat))
			{
				$temp_category = $db->res_row();
				if (!$temp_category) $submit_upload = FALSE;
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}

		if ($submit_upload)
		{
			$photo['category_name'] = $_POST['name_category'];
			if (!get_magic_quotes_gpc())
			{
				$photo['name'] = addslashes($photo['name']);
				$photo['description'] = addslashes($photo['description']);
			}
			if ($_FILES['file_photo']['error'] == 0 && $_FILES['file_photo']['size'] > 0 && $_FILES['file_photo']['size'] <= $max_size && mb_eregi('(gif|jpeg|png)$', $_FILES['file_photo']['type'], $type))
			{
				$file_name = time() . '_' . $work->encodename(basename($_FILES['file_photo']['name'])) . '.' . $type[0];

				if ($db->select('*', TBL_CATEGORY, '`id` = ' . $photo['category_name']))
				{
					$temp_category = $db->res_row();
					if ($temp_category)
					{
						$photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_category['folder'] . '/' . $file_name;
						$photo['thumbnail_path'] = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_category['folder'] . '/' . $file_name;
						if (move_uploaded_file($_FILES['file_photo']['tmp_name'], $photo['path'])) $template->image_resize($photo['path'], $photo['thumbnail_path']);
						else $submit_upload = FALSE;
					}
					else $submit_upload = FALSE;
				}
				else log_in_file($db->error, DIE_IF_ERROR);
			}
			else $submit_upload = FALSE;
		}
		if ($submit_upload)
		{
			$query = array(
				'file'        => $file_name,
				'name'        => $photo['name'],
				'description' => $photo['description'],
				'category'    => $photo['category_name'],
				'date_upload' => date('Y-m-d H:m:s'),
				'user_upload' => $user->user['id'],
				'rate_user'   => '0',
				'rate_moder'  => '0'
			);
			if ($db->insert($query, TBL_PHOTO, 'ignore')) $photo_id = $db->insert_id;
			else log_in_file($db->error, DIE_IF_ERROR);
			if ($photo_id)
			{
				$redirect_url = $work->config['site_url'] . '?action=photo&id=' . $photo_id;
			}
			else
			{
				@unlink($photo['path']);
				@unlink($photo['thumbnail_path']);
				$redirect_url = $work->config['site_url'] . '?action=photo&subact=upload';
			}
		}
		else
		{
			$redirect_url = $work->config['site_url'] . '?action=photo&subact=upload';
		}
	}
	else
	{
		$template_new->add_case('PHOTO_BLOCK', 'VIEW_PHOTO');
		$temp_photo['file'] = 'no_foto.png';
		$template_new->add_string_ar(array(
			'L_NAME_BLOCK'           => $lang['photo']['title'] . ' - ' . $lang['main']['no_foto'],
			'L_DESCRIPTION_BLOCK'    => $lang['main']['no_foto'],
			'L_USER_ADD'             => $lang['main']['user_add'],
			'L_NAME_CATEGORY'        => $lang['main']['name_of'] . ' ' . $lang['category']['of_category'],
			'L_DESCRIPTION_CATEGORY' => $lang['main']['description_of'] . ' ' . $lang['category']['of_category'],
			'D_NAME_PHOTO'           => $lang['main']['no_foto'],
			'D_DESCRIPTION_PHOTO'    => $lang['main']['no_foto'],
			'D_USER_ADD'             => $lang['main']['no_user_add'],
			'D_NAME_CATEGORY'        => $lang['main']['no_category'],
			'D_DESCRIPTION_CATEGORY' => $lang['main']['no_category'],
			'D_RATE_USER'            => $lang['photo']['rate_user'] . ': ' . $lang['main']['no_foto'],
			'D_RATE_MODER'           => $lang['photo']['rate_moder'] . ': ' . $lang['main']['no_foto'],
			'U_CATEGORY'             => $work->config['site_url'],
			'U_USER_ADD'             => '',
			'U_PHOTO'                => $work->config['site_url'] . '?action=attach&amp;foto=0'
		));

		$photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_photo['file'];
	}
}

if (!$work->check_get('subact', TRUE, TRUE, '^[upload|uploaded]\$'))
{
	$size = getimagesize($photo['path']);

	if ($max_photo_w == '0') $ratioWidth = 1;
	else $ratioWidth = $size[0] / $max_photo_w;
	if ($max_photo_h == '0') $ratioHeight = 1;
	else $ratioHeight = $size[1] / $max_photo_h;

	if ($size[0] < $max_photo_w && $size[1] < $max_photo_h && $max_photo_w != '0' && $max_photo_h != '0')
	{
		$photo['width'] = $size[0];
		$photo['height'] = $size[1];
	}
	else
	{
		if ($ratioWidth < $ratioHeight)
		{
			$photo['width'] = $size[0] / $ratioHeight;
			$photo['height'] = $size[1] / $ratioHeight;
		}
		else
		{
			$photo['width'] = $size[0] / $ratioWidth;
			$photo['height'] = $size[1] / $ratioWidth;
		}
	}
	$template_new->add_string_ar(array(
		'D_FOTO_WIDTH'  => $photo['width'],
		'D_FOTO_HEIGHT' => $photo['height']
	));
}

if ((isset($_GET['subact']) && $_GET['subact'] == "uploaded" && $user->user['id'] != 0 && $user->user['pic_upload'] == TRUE))
{
	header('Location: ' . $redirect_url);
	log_in_file('Hack attempt!', DIE_IF_ERROR);
}
/// @endcond
?>
