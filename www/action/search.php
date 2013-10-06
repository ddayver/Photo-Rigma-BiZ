<?php
/**
* @file		action/search.php
* @brief	Поиск по сайту.
* @author	Dark Dayver
* @version	0.2.0
* @date		28/03-2012
* @details	Обработка поисковых запросов.
*/

if (IN_GALLERY !== true)
{
	die('HACK!');
}

include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/main.php');
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/menu.php');
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/search.php');

if(isset($_POST['search_main_text']) && !empty($_POST['search_main_text']) && empty($_POST['search_text']))
{
	$_POST['search_text'] = $_POST['search_main_text'];
	$_POST['search_user'] = 'true';
	$_POST['search_category'] = 'true';
	$_POST['search_news'] = 'true';
	$_POST['search_photo'] = 'true';
}

$check = array();
$search_user = false;
$search_category = false;
$search_news = false;
$search_photo = false;
$find_data = array();

if(!empty($_POST['search_user']) && $_POST['search_user'] == 'true' && !empty($_POST['search_text']))
{
	$search_user = true;
	$check['user'] = 'checked';
}

if(!empty($_POST['search_category']) && $_POST['search_category'] == 'true' && !empty($_POST['search_text']))
{
	$search_category = true;
	$check['category'] = 'checked';
}

if(!empty($_POST['search_news']) && $_POST['search_news'] == 'true' && !empty($_POST['search_text']))
{
	$search_news = true;
	$check['news'] = 'checked';
}

if(!empty($_POST['search_photo']) && $_POST['search_photo'] == 'true' && !empty($_POST['search_text']))
{
	$search_photo = true;
	$check['photo'] = 'checked';
}

if (!($search_user || $search_category || $search_news || $search_photo)) $check['photo'] = 'checked';

$array_data = array();
if (isset($_POST['search_text']) && $_POST['search_text'] == '*') $_POST['search_text'] = '%';

if($search_user)
{
	$find_data['l_search_user'] = $lang['search_find'] . ' ' . $lang['search_need_user'];
	if ($db->select('*', TBL_USERS, '`real_name` LIKE \'%' . $_POST['search_text'] . '%\''))
	{
		$find = $db->res_arr();
		if ($find)
		{
			$find_data['d_search_user'] = '';
			foreach ($find as $val)
			{
				$find_data['d_search_user'] .= ', <a href="' . $work->config['site_url']  . '?action=profile&subact=profile&uid=' . $val['id'] . '" title="' . $val['real_name'] . '">' . $val['real_name'] . '</a>';
			}
			if (!empty($find_data['d_search_user'])) $find_data['d_search_user'] = substr($find_data['d_search_user'], 2) . '.';
		}
		else $find_data['d_search_user'] = $lang['search_no_find'];
	}
	else log_in_file($db->error, DIE_IF_ERROR);
}

if($search_category)
{
	$find_data['l_search_category'] = $lang['search_find'] . ' ' . $lang['search_need_category'];
	if ($db->select('*', TBL_CATEGORY, '`id` != 0 AND (`name` LIKE \'%' . $_POST['search_text'] . '%\' OR `description` LIKE \'%'. $_POST['search_text'] . '%\')'))
	{
		$find = $db->res_arr();
		if ($find)
		{
			$find_data['d_search_category'] = '';
			foreach ($find as $val)
			{
				$find_data['d_search_category'] .= ', <a href="' . $work->config['site_url']  . '?action=category&cat=' . $val['id'] . '" title="' . $val['description'] . '">' . $val['name'] . '</a>';
			}
			if (!empty($find_data['d_search_category'])) $find_data['d_search_category'] = substr($find_data['d_search_category'], 2) . '.';
		}
		else $find_data['d_search_category'] = $lang['search_no_find'];
	}
	else log_in_file($db->error, DIE_IF_ERROR);
}

if($search_news)
{
	$find_data['l_search_news'] = $lang['search_find'] . ' ' . $lang['search_need_news'];
	if ($db->select('*', TBL_NEWS, '`name_post` LIKE \'%' . $_POST['search_text'] . '%\' OR `text_post` LIKE \'%'. $_POST['search_text'] . '%\''))
	{
		$find = $db->res_arr();
		if ($find)
		{
			$find_data['d_search_news'] = '';
			foreach ($find as $val)
			{
				$find_data['d_search_news'] .= ', <a href="' . $work->config['site_url']  . '?action=news&news=' . $val['id'] . '" title="' . substr($work->clean_field($val['text_post']), 0, 100) . '...">' . $val['name_post'] . '</a>';
			}
			if (!empty($find_data['d_search_news'])) $find_data['d_search_news'] = substr($find_data['d_search_news'], 2) . '.';
		}
		else $find_data['d_search_news'] = $lang['search_no_find'];
	}
	else log_in_file($db->error, DIE_IF_ERROR);
}

if($search_photo)
{
	$find_data['l_search_photo'] = $lang['search_find'] . ' ' . $lang['search_need_photo'];
	if ($db->select('id', TBL_PHOTO, '`name` LIKE \'%' . $_POST['search_text'] . '%\' OR `description` LIKE \'%'. $_POST['search_text'] . '%\''))
	{
		$find = $db->res_arr();
		if ($find)
		{
			$find_data['d_search_photo'] = '';
			foreach ($find as $val)
			{
				$find_data['d_search_photo'] .= $template->create_foto('cat', $val['id']);
			}
			if (empty($find_data['d_search_photo'])) $find_data['d_search_photo'] = $lang['search_no_find'];
		}
		else $find_data['d_search_photo'] = $lang['search_no_find'];
	}
	else log_in_file($db->error, DIE_IF_ERROR);
}

if (isset($_POST['search_text']) && $_POST['search_text'] == '%') $_POST['search_text'] = '*';
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
);

echo $template->create_main_template('search', $lang['main_search'], $template->create_template('search.tpl', $array_data));
?>
