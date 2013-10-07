<?php
/**
* @file		action/category.php
* @brief	Обзор и управление разделами.
* @author	Dark Dayver
* @version	0.2.0
* @date		28/03-2012
* @details	Обзор, редактирование, удаление и добавление разделов в галерею.
*/

if (IN_GALLERY !== true)
{
	die('HACK!');
}

include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/main.php');
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/menu.php');
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/category.php');

$act = 'category';

if (!empty($_SERVER['HTTP_REFERER'])) $redirect_url = $_SERVER['HTTP_REFERER'];
else $redirect_url = $work->config['site_url'];

if (!isset($_REQUEST['cat'])) $cat = false;
else $cat = $_REQUEST['cat'];

if ($cat == 'user' || $cat === 0)
{
	if (!isset($_REQUEST['id']) || !(mb_ereg('^[0-9]+$', $_REQUEST['id']) || $_REQUEST['id'] == 'curent'))
	{
		$act = 'user_category';
		if ($db->select('DISTINCT `user_upload`', TBL_PHOTO, '`category` = 0', array('user_upload' => 'up')))
		{
			$temp = $db->res_arr();
			$array_data = array();
			if ($temp)
			{
				$temp_category = '';
				foreach ($temp as $val)
				{
					if ($db->select('id', TBL_USERS, '`id` = ' . $val['user_upload']))
					{
						$temp2 = $db->res_row();
						if ($temp2)
						{
							$temp_category .= $work->category($val['user_upload'], 1);
						}
					}
					else log_in_file($db->error, DIE_IF_ERROR);
				}

				if ($db->select(array('name', 'description'), TBL_CATEGORY, '`id` = 0'))
				{
					$temp2 = $db->res_row();
					if ($temp2)
					{
						$array_data = array(
									'NAME_BLOCK' => $lang['category_users_album'],
									'L_NAME_CATEGORY' => $temp2['name'],
									'L_DESCRIPTION_CATEGORY' => $temp2['description'],
									'L_COUNT_PHOTO' => $lang['category_count_photo'],
									'L_LAST_PHOTO' => $lang['main_last_foto'] . $lang['category_of_category'],
									'L_TOP_PHOTO' => $lang['main_top_foto'] . $lang['category_of_category'],

									'TEXT_CATEGORY' => $temp_category
						);
					}
					else log_in_file('Unable to get the user category', DIE_IF_ERROR);
				}
				else log_in_file($db->error, DIE_IF_ERROR);
			}
			else
			{
				$array_data = array(
							'NAME_BLOCK' => $lang['category_users_album'],
							'L_NAME_CATEGORY' => '',
							'L_DESCRIPTION_CATEGORY' => '',
							'L_COUNT_PHOTO' => $lang['category_count_photo'],
							'L_LAST_PHOTO' => $lang['main_last_foto'] . $lang['category_of_category'],
							'L_TOP_PHOTO' => $lang['main_top_foto'] . $lang['category_of_category'],

							'TEXT_CATEGORY' => ''
				);
			}
			$main_block = $template->create_template('category_view.tpl', $array_data);
		}
		else log_in_file($db->error, DIE_IF_ERROR);
	}
	else
	{
		if ($_REQUEST['id'] == 'curent' && $user->user['id'] > 0) $cat_id = $user->user['id'];
		else $cat_id = $_REQUEST['id'];
		if ($cat_id == $user->user['id'] && $user->user['id'] > 0) $act = 'you_category';

		if ($db->select('id', TBL_PHOTO, '`category` = 0 AND `user_upload` = ' . $cat_id, array('date_upload' => 'down')))
		{
			$temp = $db->res_arr();
			if ($temp && $user->user['pic_view'] == true)
			{
				$temp_category = '';
				foreach ($temp as $val)
				{
            	    $temp_category .= $template->create_foto('cat', $val['id']);
				}
				if ($db->select('real_name', TBL_USERS, '`id` = ' . $cat_id))
				{
					$temp_user = $db->res_row();
					if ($temp_user)
					{
						if ($db->select(array('name', 'description'), TBL_CATEGORY, '`id` = 0'))
						{
							$temp2 = $db->res_row();
							if ($temp2)
							{
								$array_data = array();
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
								);
								$main_block = $template->create_template('category_view_pic.tpl', $array_data);
							}
							else log_in_file('Unable to get the user category', DIE_IF_ERROR);
						}
						else log_in_file($db->error, DIE_IF_ERROR);
					}
					else log_in_file('Unable to get the user', DIE_IF_ERROR);
				}
				else log_in_file($db->error, DIE_IF_ERROR);
			}
			else
			{
				$array_data = array();
				$array_data = array(
							'IF_EDIT_BLOCK' => false,
							'NAME_BLOCK' => $lang['category_name_block'],
							'L_NEWS_DATA' => $lang['category_error_no_photo'],
							'L_TEXT_POST' => ''
				);
				$main_block = $template->create_template('news.tpl', $array_data);
			}
		}
		else log_in_file($db->error, DIE_IF_ERROR);
	}
}
elseif (mb_ereg('^[0-9]+$', $cat))
{
	if (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'saveedit' && $user->user['cat_moderate'] == true && $cat != 0)
	{
		if ($db->select('*', TBL_CATEGORY, '`id` = ' . $cat))
		{
			$temp = $db->res_row();
			if ($temp)
			{
				if(!isset($_POST['name_category']) || empty($_POST['name_category'])) $name_category = $temp['name'];
				else $name_category = $_POST['name_category'];

				if(!isset($_POST['description_category']) || empty($_POST['description_category'])) $description_category = $temp['description'];
				else $description_category = $_POST['description_category'];

				if (!$db->update(array('name' => $name_category, 'description' => $description_category), TBL_CATEGORY, '`id` = ' . $cat)) log_in_file($db->error, DIE_IF_ERROR);
			}
			else log_in_file('Unable to get the category', DIE_IF_ERROR);
		}
		else log_in_file($db->error, DIE_IF_ERROR);
	}

	if (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'edit' && $user->user['cat_moderate'] == true && $cat != 0)
	{
		if ($db->select('*', TBL_CATEGORY, '`id` = ' . $cat))
		{
			$temp = $db->res_row();
			if ($temp)
			{
				$array_data = array();
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
				);
				$main_block = $template->create_template('category_edit.tpl', $array_data);
			}
			else
			{
				$array_data = array();
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
				);
				$main_block = $template->create_template('category_view_pic.tpl', $array_data);
			}
		}
		else log_in_file($db->error, DIE_IF_ERROR);
	}
	elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'delete' && $user->user['cat_moderate'] == true && $cat != 0)
	{
		if ($db->select('*', TBL_CATEGORY, '`id` = ' . $cat))
		{
			$temp = $db->res_row();
			if ($temp)
			{
				if ($db->select('id', TBL_PHOTO, '`category` = ' . $cat))
				{
					$temp2 = $db->res_row();
					if ($temp2)
					{
						foreach ($temp2 as $val)
						{
							$work->del_photo($val['id']);
						}
					}
				}
				else log_in_file($db->error, DIE_IF_ERROR);

				$cat_dir = dir($work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp['folder']);
				while (false !== ($entry = $cat_dir->read()))
				{
					if($entry != '.' && $entry !='..') unlink($cat_dir->path . '/' . $entry);
				}
				$cat_dir->close();

				$cat_dir = dir($work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp['folder']);
				while (false !== ($entry = $cat_dir->read()))
				{
					if($entry != '.' && $entry !='..') unlink($cat_dir->path . '/' . $entry);
				}
				$cat_dir->close();

				rmdir($work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp['folder']);
				rmdir($work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp['folder']);

				if (!$db->delete(TBL_CATEGORY, '`id` = ' . $cat)) log_in_file($db->error, DIE_IF_ERROR);

				$redirect_url = $work->config['site_url'];
				if ($db->select('COUNT(*) as `count_category`', TBL_CATEGORY))
				{
					$temp2 = $db->res_row();
					if (isset($temp2['count_category']) && $temp2['count_category'] > 0) $redirect_url .= '?action=category';
				}
				else log_in_file($db->error, DIE_IF_ERROR);

				$redirect_time = 5;
				$redirect_message = $lang['category_category'] . ' ' . $temp['name'] . ' ' . $lang['category_deleted_sucesful'];
			}
			else
			{
				$redirect_url = $work->config['site_url'];
				$redirect_time = 5;
				$redirect_message = $lang['category_deleted_error'];
			}
		}
		else log_in_file($db->error, DIE_IF_ERROR);
	}
	else
	{
		if ($db->select('id', TBL_PHOTO, '`category` = ' . $cat, array('date_upload' => 'down')))
		{
			$temp = $db->res_arr();
			if ($temp && $user->user['pic_view'] == true)
			{
				$temp_category = '';
				foreach ($temp as $val)
				{
		               $temp_category .= $template->create_foto('cat', $val['id']);
				}
				if ($db->select(array('name', 'description'), TBL_CATEGORY, '`id` = ' . $cat))
				{
					$temp2 = $db->res_row();
					if ($temp2)
					{
						$array_data = array();
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
						);
						$main_block = $template->create_template('category_view_pic.tpl', $array_data);
					}
					else log_in_file('Unable to get the category', DIE_IF_ERROR);
				}
				else log_in_file($db->error, DIE_IF_ERROR);
			}
			else
			{
				if ($db->select(array('name', 'description'), TBL_CATEGORY, '`id` = ' . $cat))
				{
					$temp2 = $db->res_row();
					if ($temp2)
					{
						$category_name = $lang['category_category'] . ' - ' . $temp2['name'];
						$category_description = $temp2['description'];
						$if_edit = $user->user['cat_moderate'];
						$pic_category = $lang['category_error_no_photo'];
					}
					else
					{
						$category_name = $lang['category_error_no_category'];
						$category_description = '';
						$if_edit = false;
						$pic_category = $lang['category_error_no_category'];
					}
				}
				else log_in_file($db->error, DIE_IF_ERROR);
				$array_data = array();
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
				);
				$main_block = $template->create_template('category_view_pic.tpl', $array_data);
			}
		}
		else log_in_file($db->error, DIE_IF_ERROR);
	}
}
else
{
	if(isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'add' && $user->user['cat_moderate'] == true)
	{
		$act = 'add_category';

			$array_data = array();
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
			);
			$main_block = $template->create_template('category_add.tpl', $array_data);
	}
	elseif (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'saveadd' && $user->user['cat_moderate'] == true)
	{
		if(!isset($_POST['name_dir']) || empty($_POST['name_dir'])) $name_dir = time();
		else $name_dir = $work->encodename($_POST['name_dir']);

		if ($db->select('COUNT(*) AS `count_dir`', TBL_CATEGORY, '`folder` = \'' . $name_dir . '\''))
		{
			$temp = $db->res_row();
			if ((isset($temp['count_dir']) && $temp['count_dir'] > 0) || is_dir($work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $name_dir) || is_dir($work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $name_dir)) $name_dir = time() . '_' . $name_dir;
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		if(!isset($_POST['name_category']) || empty($_POST['name_category'])) $name_category = $lang['category_no_name'] . ' (' . $name_dir . ')';
		else $name_category = $_POST['name_category'];

		if(!isset($_POST['description_category']) || empty($_POST['description_category'])) $description_category = $lang['category_no_description'] . ' (' . $name_dir . ')';
		else $description_category = $_POST['description_category'];

		if(mkdir($work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $name_dir, 0777) && mkdir($work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $name_dir, 0777))
		{
			$index_in_gallery = @file_get_contents($work->config['site_dir'] . $work->config['gallery_folder'] . '/index.php');
			if ($index_in_gallery !== false && !empty($index_in_gallery))
			{
				$index_in_gallery = str_replace('gallery/index.php', 'gallery/' . $name_dir . '/index.php', $index_in_gallery);
				if ($file = @fopen($work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $name_dir . '/index.php', 'w'))
				{
					@fwrite($file, $index_in_gallery);
					@fclose($file);
				}
			}
			$index_in_thumbnail = @file_get_contents($work->config['site_dir'] . $work->config['thumbnail_folder'] . '/index.php');
			if ($index_in_thumbnail !== false && !empty($index_in_thumbnail))
			{
				$index_in_thumbnail = str_replace('thumbnail/index.php', 'thumbnail/' . $name_dir . '/index.php', $index_in_thumbnail);
				if ($file = @fopen($work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $name_dir . '/index.php', 'w'))
				{
					@fwrite($file, $index_in_thumbnail);
					@fclose($file);
				}
			}
			if ($db->insert(array('folder' => $name_dir, 'name' => $name_category, 'description' => $description_category), TBL_CATEGORY))
			{
				$new_cat = $db->insert_id;
				if($new_cat != 0)
				{
					$redirect_url = $work->config['site_url'] . '?action=category&cat=' . $new_cat;
					$redirect_message = $lang['category_category'] . ' ' . $name_category . ' ' . $lang['category_added_sucesful'];
				}
				else
				{
					$redirect_url = $work->config['site_url'] . '?action=category';
					$redirect_message = $lang['category_added_error'];
				}
				$redirect_time = 5;
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}
		else
		{
			$redirect_url = $work->config['site_url'] . '?action=category&subact=add';
			$redirect_time = 5;
			$redirect_message = $lang['category_added_error'];
		}
	}
	else
	{
		if ($db->select('id', TBL_CATEGORY, '`id` != 0'))
		{
			$temp = $db->res_arr();
			if ($temp)
			{
				$temp_category = '';
				foreach ($temp as $val)
				{
					$temp_category .= $work->category($val['id'], 0);
				}
				$temp_category .= $work->category(0, 0);
				$array_data = array();
				$array_data = array(
							'NAME_BLOCK' => $lang['category_name_block'],
							'L_NAME_CATEGORY' => $lang['main_name_of'] . $lang['category_of_category'],
							'L_DESCRIPTION_CATEGORY' => $lang['main_description_of'] .  $lang['category_of_category'],
							'L_COUNT_PHOTO' => $lang['category_count_photo'],
							'L_LAST_PHOTO' => $lang['main_last_foto'] . $lang['category_of_category'],
							'L_TOP_PHOTO' => $lang['main_top_foto'] . $lang['category_of_category'],

							'TEXT_CATEGORY' => $temp_category
				);
				$main_block = $template->create_template('category_view.tpl', $array_data);
			}
			else
			{
				$array_data = array();
				$array_data = array(
							'IF_EDIT_SHORT' => false,
							'NAME_BLOCK' => $lang['category_name_block'],
							'L_NEWS_DATA' => $lang['main_no_category'],
							'L_TEXT_POST' => ''
				);
				$main_block = $template->create_template('news.tpl', $array_data);
			}
		}
		else log_in_file($db->error, DIE_IF_ERROR);
	}
}

if ((isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'delete' && $user->user['cat_moderate'] == true && $cat != 0) || (isset($_REQUEST['subact']) && $_REQUEST['subact'] == 'saveadd' && $user->user['cat_moderate'] == true))
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
	$title = $lang['main_redirect_title'];
	$main_block = $template->create_template('redirect.tpl', $array_data);
}
else
{
	$redirect = array();
	$title = $lang['category_name_block'];
}
echo $template->create_main_template($act, $title, $main_block, $redirect);
?>
