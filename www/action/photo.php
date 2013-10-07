<?php
/**
* @file		action/photo.php
* @brief	Работа с фото.
* @author	Dark Dayver
* @version	0.2.0
* @date		28/03-2012
* @details	Вывод, редактировани, загрузка и обработка изображений, оценок.
*/

if (IN_GALLERY !== true)
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

$main_tpl = 'photo_view.tpl';
$max_photo_w = $work->config['max_photo_w'];
$max_photo_h = $work->config['max_photo_h'];

if ($photo_id != 0)
{
	if ($db->select('*', TBL_PHOTO, '`id` = ' . $photo_id))
	{
		$temp_foto = $db->res_row();
		if ($temp_foto)
		{
			if ($db->select('*', TBL_CATEGORY, '`id` = ' . $temp_foto['category']))
			{
				$temp_category = $db->res_row();
				if ($temp_category)
				{
					if (isset($_REQUEST['subact']) && $_REQUEST['subact'] == "rate" && ($user->user['pic_rate_user'] == true || $user->user['pic_rate_moder'] == true) && $temp_foto['user_upload'] != $user->user['id'])
					{
						if ($db->select('rate', TBL_RATE_USER, '`id_foto` = ' . $photo_id . ' AND `id_user` = ' . $user->user['id']))
						{
							$temp = $db->res_row();
							if ($temp) $rate_user = $temp['rate'];
							else $rate_user = false;
						}
						else log_in_file($db->error, DIE_IF_ERROR);
						if ($user->user['pic_rate_user'] == true && isset($_POST['rate_user']) && mb_ereg('^[0-9]+$', abs($_POST['rate_user'])) && abs($_POST['rate_user']) <= $work->config['max_rate'] && $rate_user === false)
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
									$rate_user = $rate_user/count($temp);
								}
								if (!$db->update(array('rate_user' => $rate_user), TBL_PHOTO, '`id` = ' . $photo_id)) log_in_file($db->error, DIE_IF_ERROR);
							}
							else log_in_file($db->error, DIE_IF_ERROR);
						}

						if ($db->select('rate', TBL_RATE_MODER, '`id_foto` = ' . $photo_id . ' AND `id_user` = ' . $user->user['id']))
						{
							$temp = $db->res_row();
							if ($temp) $rate_moder = $temp['rate'];
							else $rate_moder = false;
						}
						else log_in_file($db->error, DIE_IF_ERROR);
						if ($user->user['pic_rate_moder'] == true && isset($_POST['rate_moder']) && mb_ereg('^[0-9]+$', abs($_POST['rate_moder'])) && abs($_POST['rate_moder']) <= $work->config['max_rate'] && $rate_moder === false)
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
									$rate_moder = $rate_moder/count($temp);
								}
								if (!$db->update(array('rate_moder' => $rate_moder), TBL_PHOTO, '`id` = ' . $photo_id)) log_in_file($db->error, DIE_IF_ERROR);
							}
							else log_in_file($db->error, DIE_IF_ERROR);
						}
					}
					elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'saveedit' && (($temp_foto['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == true))
					{
						if ($db->select('*', TBL_PHOTO, '`id` = ' . $photo_id))
						{
							$temp_foto = $db->res_row();
							if ($temp_foto)
							{
								if (!isset($_POST['name_photo']) || empty($_POST['name_photo'])) $photo['name'] = $temp_foto['name'];
								else $photo['name'] = $_POST['name_photo'];

								if (!isset($_POST['description_photo']) || empty($_POST['description_photo'])) $photo['description'] = $temp_foto['description'];
								else $photo['description'] = $_POST['description_photo'];

    							$category = true;

								if (!isset($_POST['name_category']) || !mb_ereg('^[0-9]+$', $_POST['name_category'])) $category = false;
								else
								{
									if ($user->user['cat_user'] == true || $user->user['pic_moderate'] == true) $select_cat = '`id` = ' . $_POST['name_category'];
									else $select_cat = '`id` != 0 AND `id` =' . $_POST['name_category'];
									if ($db->select('*', TBL_CATEGORY, $select_cat))
									{
										$temp = $db->res_row();
										if (!$temp) $category = false;
									}
									else log_in_file($db->error, DIE_IF_ERROR);
								}

								if ($category && $temp_foto['category'] != $_POST['name_category'])
								{
									if ($db->select('folder', TBL_CATEGORY, '`id` = ' . $temp_foto['category']))
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
									$path_old_photo = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_old['folder'] . '/' . $temp_foto['file'];
									$path_new_photo = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_new['folder'] . '/' . $temp_foto['file'];
									$path_old_thumbnail = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_old['folder'] . '/' . $temp_foto['file'];
									$path_new_thumbnail = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_new['folder'] . '/' . $temp_foto['file'];
									if (!rename($path_old_photo, $path_new_photo)) $photo['category_name'] = $temp_foto['category'];
									else
									{
										if (!rename($path_old_thumbnail, $path_new_thumbnail)) @unlink($path_old_thumbnail);
										$photo['category_name'] = $_POST['name_category'];
									}
								}
								else $photo['category_name'] = $temp_foto['category'];

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

					if (isset($_REQUEST['subact']) && $_REQUEST['subact'] == "edit" && (($temp_foto['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == true))
					{
						$main_tpl = 'photo_edit.tpl';

						if ($db->select('*', TBL_PHOTO, '`id` = ' . $photo_id))
						{
							$temp_foto = $db->res_row();
							if ($temp_foto)
							{
								if ($db->select('*', TBL_CATEGORY, '`id` = ' . $temp_foto['category']))
								{
									$temp_category = $db->res_row();
									if ($temp_category)
									{
										$photo['path'] = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_category['folder'] . '/' . $temp_foto['file'];
										$photo['url'] = $work->config['site_url'] . '?action=attach&foto=' . $temp_foto['id'] . '&thumbnail=1';
										$photo['name'] = $temp_foto['name'];
										$photo['file'] = $temp_foto['file'];
										$photo['description'] = $temp_foto['description'];
										if ($db->select('real_name', TBL_USERS, '`id` = ' . $temp_foto['user_upload']))
										{
											$user_add = $db->res_row();
											if ($user_add)
											{
												$photo['user'] = $user_add['real_name'];
												$photo['user_url'] = $work->config['site_url']  . '?action=profile&subact=profile&uid=' . $temp_foto['user_upload'];
											}
											else
											{
												$photo['user'] = $lang['main_no_user_add'];
												$photo['user_url'] = '';
											}
										}
										else log_in_file($db->error, DIE_IF_ERROR);

										if ($user->user['cat_user'] == true || $user->user['pic_moderate'] == true) $select_cat = false;
										else $select_cat = '`id` != 0';

										if ($db->select('*', TBL_CATEGORY, $select_cat))
										{
											$temp_category = $db->res_arr();
											if ($temp_category)
											{
												$photo['category_name'] = '<select name="name_category">';
												foreach ($temp_category as $val)
												{
            										if ($val['id'] == $temp_foto['category']) $selected = ' selected'; else $selected = '';
				        		    				if ($val['id'] == 0) $val['name'] .= ' ' . $photo['user'];
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
					elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == "delete" && (($temp_foto['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == true))
					{
						$photo['name'] = $temp_foto['name'];
						if ($temp_category['id'] == 0)
						{
							if ($db->select('COUNT(*) as `count_photo`', TBL_PHOTO, '`id` != ' . $photo_id . ' AND `category` = 0 AND `user_upload` = ' . $temp_foto['user_upload']))
							{
								$temp = $db->res_row();
								if (isset($temp['count_photo']) && $temp['count_photo'] > 0) $photo['category_url'] = $work->config['site_url'] . '?action=category&cat=user&id=' . $temp_foto['user_upload'];
								else
								{
									if ($db->select('COUNT(*) as `count_photo`', TBL_PHOTO, '`id` != ' . $photo_id . ' AND `category` = 0'))
									{
										$temp = $db->res_row();
										if (isset($temp['count_photo']) && $temp['count_photo'] > 0) $photo['category_url'] = $work->config['site_url'] . '?action=category&cat=user';
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

						if ($db->select('*', TBL_PHOTO, '`id` = ' . $photo_id))
						{
							$temp_foto = $db->res_row();
							if ($temp_foto)
							{
								if ($db->select('*', TBL_CATEGORY, '`id` = ' . $temp_foto['category']))
								{
									$temp_category = $db->res_row();
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

										if ($db->select('real_name', TBL_USERS, '`id` = ' . $temp_foto['user_upload']))
										{
											$user_add = $db->res_row();
											if ($user_add)
											{
												$photo['user'] = $user_add['real_name'];
												$photo['user_url'] = $work->config['site_url']  . '?action=profile&subact=profile&uid=' . $temp_foto['user_upload'];
											}
											else
											{
												$photo['user'] = $lang['main_no_user_add'];
												$photo['user_url'] = '';
											}
										}
										else log_in_file($db->error, DIE_IF_ERROR);

										if ($temp_category['id'] == 0)
										{
											$photo['category_name'] .= ' ' . $photo['user'];
											$photo['category_description'] .=  ' ' . $photo['user'];
											$photo['category_url'] = $work->config['site_url'] . '?action=category&cat=user&id=' . $temp_foto['user_upload'];
										}
										else
										{
											$photo['category_url'] = $work->config['site_url'] . '?action=category&cat=' . $temp_category['id'];
										}
    									$photo['rate_user'] = $lang['photo_rate_user'] . ': ' . $temp_foto['rate_user'];
					    				$photo['rate_moder'] = $lang['photo_rate_moder'] . ': ' . $temp_foto['rate_moder'];
										$photo['rate_you'] = '';

										if ($user->user['pic_rate_user'] == true)
										{
											if ($db->select('rate', TBL_RATE_USER, '`id_foto` = ' . $photo_id . ' AND `id_user` = ' . $user->user['id']))
											{
												$temp_rate = $db->res_row();
												if ($temp_rate) $user_rate = $temp_rate['rate'];
												else $user_rate = 'false';
											}
											else log_in_file($db->error, DIE_IF_ERROR);
											$photo['rate_you_user'] = $template->template_rate('user', $user_rate);
										}
										else
										{
											$user_rate = 'false';
											$photo['rate_you_user'] = '';
										}

										if ($user->user['pic_rate_moder'] == true)
										{
											if ($db->select('rate', TBL_RATE_MODER, '`id_foto` = ' . $photo_id . ' AND `id_user` = ' . $user->user['id']))
											{
												$temp_rate = $db->res_row();
												if ($temp_rate) $moder_rate = $temp_rate['rate'];
												else $moder_rate = 'false';
											}
											else log_in_file($db->error, DIE_IF_ERROR);
											$photo['rate_you_moder'] = $template->template_rate('moder', $moder_rate);
										}
										else
										{
											$moder_rate = 'false';
											$photo['rate_you_moder'] = '';
										}

										if (($user->user['pic_rate_user'] == true || $user->user['pic_rate_moder'] == true) && $temp_foto['user_upload'] != $user->user['id'])
										{
											$array_data = array();

											if ($user_rate == 'false' && $moder_rate == 'false')
											{
				        	    				$photo['rate_you_url'] = $work->config['site_url'] . '?action=photo&subact=rate&id=' . $photo_id;
            									$rate_this = true;
											}
											else
											{
												$photo['rate_you_url'] = '';
    	        								$rate_this = false;
											}
											$array_data = array(
														'U_RATE' => $photo['rate_you_url'],
														'L_RATE' => $lang['photo_rate_you'],
														'L_RATE_THIS' => $lang['photo_rate'],
														'D_RATE' => $photo['rate_you_user'] . $photo['rate_you_moder'],

														'IF_RATE_THIS' => $rate_this
											);
	        								$photo['rate_you'] = $template->create_template('rate_form.tpl', $array_data);
										}
										else $photo['rate_you'] = '';
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
	if (isset($_REQUEST['subact']) && $_REQUEST['subact'] == "upload" && $user->user['id'] != 0 && $user->user['pic_upload'] == true)
	{
		$main_tpl = 'photo_upload.tpl';
		$temp_foto['file'] = 'no_foto.png';
		$photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file'];

		$max_size_php = $work->return_bytes(ini_get('post_max_size'));
		$max_size = $work->return_bytes($work->config['max_file_size']);
		if ($max_size > $max_size_php) $max_size = $max_size_php;

		if ($user->user['cat_user'] == true) $select_cat = false;
		else $select_cat = '`id` != 0';

		if ($db->select('*', TBL_CATEGORY, $select_cat))
		{
			$temp_category = $db->res_arr();
			if ($temp_category)
			{
		        $photo['category_name'] = '<select name="name_category">';
		        foreach ($temp_category as $val)
				{
		            if ($val['id'] == 0)
					{
						$temp_category[$i]['name'] .= ' ' . $user->user['real_name'];
						$selected = ' selected';
					}
					else $selected = '';
					$photo['category_name'] .= '<option value="' . $val['id'] . '"' . $selected . '>' . $$val['name'] . '</option>';
				}
				$photo['category_name'] .= '</select>';
		   	    $photo['url_uploaded'] = $work->config['site_url'] . '?action=photo&subact=uploaded';
			}
			else log_in_file('Unable to get the category', DIE_IF_ERROR);
		}
		else log_in_file($db->error, DIE_IF_ERROR);
	}
	elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == "uploaded" && $user->user['id'] != 0 && $user->user['pic_upload'] == true)
	{
		$temp_foto['file'] = 'no_foto.png';
		$photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file'];
    	$submit_upload = true;
		$max_size_php = $work->return_bytes(ini_get('post_max_size'));
		$max_size = $work->return_bytes($work->config['max_file_size']);
		if ($max_size > $max_size_php) $max_size = $max_size_php;

		if (!isset($_POST['name_photo']) || empty($_POST['name_photo']))
		{
            $photo['name'] = $lang['photo_no_name'] . ' (' . $work->encodename(basename($_FILES['file_photo']['name'])) . ')';
		}
		else $photo['name'] = $_POST['name_photo'];

		if (!isset($_POST['description_photo']) || empty($_POST['description_photo'])) $photo['description'] = $lang['photo_no_description'] . ' (' . $work->encodename(basename($_FILES['file_photo']['name'])) . ')';
		else $photo['description'] = $_POST['description_photo'];

		if (!isset($_POST['name_category']) || !mb_ereg('^[0-9]+$', $_POST['name_category'])) $submit_upload = false;
		else
		{
			if ($user->user['cat_user'] == true || $user->user['pic_moderate'] == true) $select_cat = '`id` = ' . $_POST['name_category'];
			else $select_cat = '`id` != 0 AND `id` = ' . $_POST['name_category'];
			if ($db->select('*', TBL_CATEGORY, $select_cat))
			{
				$temp_category = $db->res_row();
				if (!$temp_category) $submit_upload = false;
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
			if ($_FILES['file_photo']['error'] == 0 && $_FILES['file_photo']['size'] > 0 && $_FILES['file_photo']['size'] <= $max_size && mb_eregi('(gif|jpeg|png)$', $_FILES['file_photo']['type']))
			{
				$file_name = time() . '_' . $work->encodename(basename($_FILES['file_photo']['name'])) . '.' . $_FILES['file_photo']['type'];

				if ($db->select('*', TBL_CATEGORY, '`id` = ' .  $photo['category_name']))
				{
					$temp_category = $db->res_row();
					if ($temp_category)
					{
						$photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_category['folder'] . '/' . $file_name;
						$photo['thumbnail_path'] = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_category['folder'] . '/' . $file_name;
						if (move_uploaded_file($_FILES['file_photo']['tmp_name'], $photo['path'])) $template->image_resize($photo['path'], $photo['thumbnail_path']);
						else $submit_upload = false;
					} else $submit_upload = false;
				}
				else log_in_file($db->error, DIE_IF_ERROR);
			}
			else $submit_upload = false;
		}
		if ($submit_upload)
		{
			$query = array(
						'file' => $file_name,
						'name' => $photo['name'],
						'description' => $photo['description'],
						'category' => $photo['category_name'],
						'date_upload' => date('Y-m-d H:m:s'),
						'user_upload' => $user->user['id'],
						'rate_user' => '0',
						'rate_moder' => '0'
			);
			if ($db->insert($query, TBL_PHOTO, 'ignore')) $photo_id = $db->insert_id;
			else log_in_file($db->error, DIE_IF_ERROR);
			if ($photo_id)
			{
				$redirect_url = $work->config['site_url'] . '?action=photo&id=' . $photo_id;
				$redirect_time = 5;
				$redirect_message = $lang['photo_title'] . ' ' . $file_name . ' ' . $lang['photo_complite_upload'];
			}
			else
			{
                @unlink($photo['path']);
                @unlink($photo['thumbnail_path']);
				$redirect_url = $work->config['site_url'] . '?action=photo&subact=upload';
				$redirect_time = 3;
				$redirect_message = $lang['photo_error_upload'];
			}
		}
		else
		{
			$redirect_url = $work->config['site_url'] . '?action=photo&subact=upload';
			$redirect_time = 3;
			$redirect_message = $lang['photo_error_upload'];
		}
	}
	else
	{
		$temp_foto['file'] = 'no_foto.png';
		$photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_foto['file'];
		$photo['url'] = $work->config['site_url'] . '?action=attach&foto=0';
		$photo['name'] = $lang['main_no_foto'];
		$photo['description'] = $lang['main_no_foto'];
		$photo['category_name'] = $lang['main_no_category'];
		$photo['category_description'] = $lang['main_no_category'];
		$photo['user'] = $lang['main_no_user_add'];
		$photo['user_url'] = '';
		$photo['category_url'] = $work->config['site_url'];
    	$photo['rate_user'] = $lang['photo_rate_user'] . ': ' . $lang['main_no_foto'];
    	$photo['rate_moder'] = $lang['photo_rate_moder'] . ': ' . $lang['main_no_foto'];
    	$photo['rate_you'] = '';
		$photo['url_edit'] = '';
		$photo['url_edit_text'] = '';
		$photo['if_edit_photo'] = false;
	}
}

$size = getimagesize($photo['path']);

if ($max_photo_w == '0') $ratioWidth = 1;
else $ratioWidth = $size[0]/$max_photo_w;
if ($max_photo_h == '0') $ratioHeight = 1;
else $ratioHeight = $size[1]/$max_photo_h;

if ($size[0] < $max_photo_w && $size[1] < $max_photo_h && $max_photo_w != '0' && $max_photo_h != '0')
{
	$photo['width'] = $size[0];
	$photo['height'] = $size[1];
}
else
{
	if ($ratioWidth < $ratioHeight)
	{
		$photo['width'] = $size[0]/$ratioHeight;
		$photo['height'] = $size[1]/$ratioHeight;
	}
	else
	{
		$photo['width'] = $size[0]/$ratioWidth;
		$photo['height'] = $size[1]/$ratioWidth;
	}
}

$array_data = array();

if ($photo_id != 0 && isset($_REQUEST['subact']) && $_REQUEST['subact'] == "edit" && (($temp_foto['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == true))
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
	);
}
elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == "upload" && $user->user['id'] != 0 && $user->user['pic_upload'] == true)
{
	$cur_act = 'upload';
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
	);
}
else
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
	);
}

if ((isset($_REQUEST['subact']) && $_REQUEST['subact'] == "uploaded" && $user->user['id'] != 0 && $user->user['pic_upload'] == true) || (isset($_REQUEST['subact']) && $_REQUEST['subact'] == "delete" && (($temp_foto['user_upload'] == $user->user['id'] && $user->user['id'] != 0) || $user->user['pic_moderate'] == true)))
{
	$array_data = array();
	$array_data = array(
				'L_REDIRECT_DESCRIPTION' => $lang['main_redirect_description'],
				'L_REDIRECT_URL' => $lang['main_redirect_url'],

				'L_REDIRECT_MASSAGE' => $redirect_message,
				'U_REDIRECT_URL' => $redirect_url
	);
	$redirect = array(
				'U_REDIRECT_URL' => $redirect_url,
				'REDIRECT_TIME' => $redirect_time,
				'IF_NEED_REDIRECT' => true
	);
	$title = $work->config['title_name'] . ' - ' . $lang['main_redirect_title'];
	$main_block = $template->create_template('redirect.tpl', $array_data);
}
else
{
	$redirect = array();
	$title = $lang['photo_title'];
	$main_block = $template->create_template($main_tpl, $array_data);
}
echo $template->create_main_template($cur_act, $title, $main_block, $redirect);
?>
