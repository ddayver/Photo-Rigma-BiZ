<?php
/**
 * @file        action/search.php
 * @brief       Поиск по сайту.
 * @author      Dark Dayver
 * @version     0.2.0
 * @date        28/03-2012
 * @details     Обработка поисковых запросов.
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
$title = $lang['search']['title'];
$template_new->template_file = 'search.html';

if ($work->check_post('search_main_text', TRUE, TRUE) && !$work->check_post('search_text', TRUE, TRUE))
{
	$_POST['search_text'] = $_POST['search_main_text'];
	$_POST['search_user'] = 'true';
	$_POST['search_category'] = 'true';
	$_POST['search_news'] = 'true';
	$_POST['search_photo'] = 'true';
}

$check = array();
$search['user'] = FALSE;
$search['category'] = FALSE;
$search['news'] = FALSE;
$search['photo'] = FALSE;
$find_data = array();

if ($work->check_post('search_user', TRUE, TRUE) && $_POST['search_user'] == 'true' && $work->check_post('search_text', TRUE, TRUE))
{
	$search['user'] = TRUE;
	$check['user'] = 'checked';
}

if ($work->check_post('search_category', TRUE, TRUE) && $_POST['search_category'] == 'true' && $work->check_post('search_text', TRUE, TRUE))
{
	$search['category'] = TRUE;
	$check['category'] = 'checked';
}

if ($work->check_post('search_news', TRUE, TRUE) && $_POST['search_news'] == 'true' && $work->check_post('search_text', TRUE, TRUE))
{
	$search['news'] = TRUE;
	$check['news'] = 'checked';
}

if ($work->check_post('search_photo', TRUE, TRUE) && $_POST['search_photo'] == 'true' && $work->check_post('search_text', TRUE, TRUE))
{
	$search['photo'] = TRUE;
	$check['photo'] = 'checked';
}

if (!($search['user'] || $search['category'] || $search['news'] || $search['photo'])) $check['photo'] = 'checked';

if ($work->check_post('search_text', TRUE, TRUE) && $_POST['search_text'] == '*') $_POST['search_text'] = '%';

$template_new->add_if_ar(array(
	'NEED_USER'     => FALSE,
	'NEED_CATEGORY' => FALSE,
	'NEED_NEWS'     => FALSE,
	'NEED_PHOTO'    => FALSE,
	'USER_FIND'     => FALSE,
	'CATEGORY_FIND' => FALSE,
	'NEWS_FIND'     => FALSE,
	'PHOTO_FIND'    => FALSE
));

if ($search['user'])
{
	$template_new->add_if('NEED_USER', TRUE);
	$template_new->add_string('L_FIND_USER', $lang['search']['find'] . ' ' . $lang['search']['need_user']);
	if ($db->select('*', TBL_USERS, '`real_name` LIKE \'%' . $_POST['search_text'] . '%\''))
	{
		$find = $db->res_arr();
		if ($find)
		{
			$template_new->add_if('USER_FIND', TRUE);
			foreach ($find as $key => $val)
			{
				$template_new->add_string_ar(array(
						'D_USER_FIND' => $val['real_name'],
						'U_USER_FIND' => $work->config['site_url'] . '?action=profile&amp;subact=profile&amp;uid=' . $val['id']
					), 'SEARCH_USER[' . $key . ']');
			}
		}
		else $template_new->add_string('D_USER_FIND', $lang['search']['no_find'], 'SEARCH_USER[0]');
	}
	else log_in_file($db->error, DIE_IF_ERROR);
}

if ($search['category'])
{
	$template_new->add_if('NEED_CATEGORY', TRUE);
	$template_new->add_string('L_FIND_CATEGORY', $lang['search']['find'] . ' ' . $lang['search']['need_category']);
	if ($db->select('*', TBL_CATEGORY, '`id` != 0 AND (`name` LIKE \'%' . $_POST['search_text'] . '%\' OR `description` LIKE \'%' . $_POST['search_text'] . '%\')'))
	{
		$find = $db->res_arr();
		if ($find)
		{
			$template_new->add_if('CATEGORY_FIND', TRUE);
			foreach ($find as $key => $val)
			{
				$template_new->add_string_ar(array(
						'D_CATEGORY_FIND_NAME' => $val['name'],
						'D_CATEGORY_FIND_DESC' => $val['description'],
						'U_CATEGORY_FIND'      => $work->config['site_url'] . '?action=category&amp;cat=' . $val['id']
					), 'SEARCH_CATEGORY[' . $key . ']');
			}
		}
		else $template_new->add_string('D_CATEGORY_FIND', $lang['search']['no_find'], 'SEARCH_CATEGORY[0]');
	}
	else log_in_file($db->error, DIE_IF_ERROR);
}

if ($search['news'])
{
	$template_new->add_if('NEED_NEWS', TRUE);
	$template_new->add_string('L_FIND_NEWS', $lang['search']['find'] . ' ' . $lang['search']['need_news']);
	if ($db->select('*', TBL_NEWS, '`name_post` LIKE \'%' . $_POST['search_text'] . '%\' OR `text_post` LIKE \'%' . $_POST['search_text'] . '%\''))
	{
		$find = $db->res_arr();
		if ($find)
		{
			$template_new->add_if('CATEGORY_FIND', TRUE);
			foreach ($find as $key => $val)
			{
				$template_new->add_string_ar(array(
						'D_NEWS_FIND_NAME' => $val['name_post'],
						'D_NEWS_FIND_DESC' => substr($work->clean_field($val['text_post']), 0, 100) . '...',
						'U_NEWS_FIND'      => $work->config['site_url'] . '?action=news&amp;news=' . $val['id']
					), 'SEARCH_NEWS[' . $key . ']');
			}
		}
		else $template_new->add_string('D_NEWS_FIND', $lang['search']['no_find'], 'SEARCH_NEWS[0]');
	}
	else log_in_file($db->error, DIE_IF_ERROR);
}

if ($search['photo'])
{
	$template_new->add_if('NEED_PHOTO', TRUE);
	$template_new->add_string('L_FIND_PHOTO', $lang['search']['find'] . ' ' . $lang['search']['need_photo']);
	if ($db->select('id', TBL_PHOTO, '`name` LIKE \'%' . $_POST['search_text'] . '%\' OR `description` LIKE \'%' . $_POST['search_text'] . '%\''))
	{
		$find = $db->res_arr();
		if ($find)
		{
			$template_new->add_if('PHOTO_FIND', TRUE);
			foreach ($find as $key => $val)
			{
				$find_photo = $work->create_photo('cat', $val['id']);
				$template_new->add_string_ar(array(
						'MAX_PHOTO_HEIGHT'    => $work->config['temp_photo_h'] + 10,
						'PHOTO_WIDTH'         => $find_photo['width'],
						'PHOTO_HEIGHT'        => $find_photo['height'],
						'D_DESCRIPTION_PHOTO' => $find_photo['description'],
						'D_NAME_PHOTO'        => $find_photo['name'],
						'U_THUMBNAIL_PHOTO'   => $find_photo['thumbnail_url'],
						'U_PHOTO'             => $find_photo['url']
					), 'SEARCH_PHOTO[' . $key . ']');
			}
		}
		else $template_new->add_string('D_PHOTO_FIND', $lang['search']['no_find'], 'SEARCH_PHOTO[0]');
	}
	else log_in_file($db->error, DIE_IF_ERROR);
}

if (isset($_POST['search_text']) && $_POST['search_text'] == '%') $_POST['search_text'] = '*';
if (isset($_POST['search_text'])) $_POST['search_text'] = htmlspecialchars($_POST['search_text'], ENT_QUOTES);

$template_new->add_string_ar(array(
	'NAME_BLOCK'      => $lang['main']['search'],
	'L_SEARCH'        => $lang['main']['search'],
	'L_SEARCH_TITLE'  => $lang['search']['title'],
	'L_NEED_USER'     => $lang['search']['need_user'],
	'L_NEED_CATEGORY' => $lang['search']['need_category'],
	'L_NEED_NEWS'     => $lang['search']['need_news'],
	'L_NEED_PHOTO'    => $lang['search']['need_photo'],
	'D_SEARCH_TEXT'   => isset($_POST['search_text']) ? $_POST['search_text'] : '',
	'D_NEED_USER'     => isset($check['user']) ? 'checked="checked"' : '',
	'D_NEED_CATEGORY' => isset($check['category']) ? 'checked="checked"' : '',
	'D_NEED_NEWS'     => isset($check['news']) ? 'checked="checked"' : '',
	'D_NEED_PHOTO'    => isset($check['photo']) ? 'checked="checked"' : '',
	'U_SEARCH'        => $work->config['site_url'] . '?action=search'
));
/// @endcond
?>
