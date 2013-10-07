<?php
/**
* @file		action/news.php
* @brief	Новости сайта.
* @author	Dark Dayver
* @version	0.2.0
* @date		28/03-2012
* @details	Вывод и обработка новостей сайта.
*/

if (IN_GALLERY !== true)
{
	die('HACK!');
}

include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/main.php');
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/menu.php');
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/news.php');

$title = $lang['news_title'];
$act = '';

if (!isset($_REQUEST['news']) || empty($_REQUEST['news']) || !(mb_ereg('^[0-9]+$', $_REQUEST['news']))) $news = false;
else
{
	$news = $_REQUEST['news'];
	if ($db->select('*', TBL_NEWS, '`id` = ' . $news))
	{
		$temp = $db->res_row();
		if (!$temp) $news = false;
	}
	else log_in_file($db->error, DIE_IF_ERROR);
}

if (isset($_REQUEST['subact']) && !empty($_REQUEST['subact'])) $subact = $_REQUEST['subact'];
else $subact = '';

if ($subact == 'save')
{
	if ($news === false && $user->user['news_add'] == true)
	{
		if (!isset($_POST['name_post']) || empty($_POST['name_post']) || !isset($_POST['text_post']) || empty($_POST['text_post']))
		{
			$subact = 'add';
		}
		else
		{
			$query_news = array();
			$query_news['data_post'] = date('Y-m-d');
			$query_news['data_last_edit'] = date('Y-m-d H:m:s');
			$query_news['user_post'] = $user->user['id'];
			$query_news['name_post'] = $_POST['name_post'];
			$query_news['text_post'] = trim($_POST['text_post']);
			if ($db->insert($query_news, TBL_NEWS)) $news = $db->insert_id;
			else log_in_file($db->error, DIE_IF_ERROR);
		}
	}
	elseif ($news !== false && $user->user['news_moderate'] == true)
	{
		if (!isset($_POST['name_post']) || empty($_POST['name_post']))
		{
			$name_post = $temp['name_post'];
			$ch_name = false;
		}
		else
		{
			$name_post = $_POST['name_post'];
			$ch_name = true;
		}

		if (!isset($_POST['text_post']) || empty($_POST['text_post']))
		{
			$text_post = trim($temp['text_post']);
			$ch_text = false;
		}
		else
		{
			$text_post = trim($_POST['text_post']);
			$ch_text = true;
		}

		if ($ch_name || $ch_text)
		{
			$query_news = array();
			$query_news['data_last_edit'] = date('Y-m-d H:m:s');
			$query_news['name_post'] = $name_post;
			$query_news['text_post'] = $text_post;
			if (!$db->update($query_news, TBL_NEWS, '`id` = ' . $news)) log_in_file($db->error, DIE_IF_ERROR);
		}
	}
	else $news = false;
}

if ($subact == 'edit' && $news !== false && ($user->user['news_moderate'] == true || ($user->user['id'] != 0 && $user->user['id'] == $temp['user_post'])))
{
	$title = $lang['main_edit_news'];

	if ($db->select('*', TBL_NEWS, '`id` = ' . $news))
	{
		$temp = $db->res_row();
		if ($temp)
		{
			if ($db->select('real_name', TBL_USERS, '`id` = ' . $temp['user_post']))
			{
				$user_add = $db->res_row();
				if ($user_add) $name_user = '<a href="' . $work->config['site_url']  . '?action=profile&subact=profile&uid=' . $temp['user_post'] . '" title="' . $user_add['real_name'] . '">' . $user_add['real_name'] . '</a>';
				else $name_user = $lang['main_no_user_add'];
			}
			else log_in_file($db->error, DIE_IF_ERROR);
			$array_data = array();
			$array_data = array(
						'NAME_BLOCK' => $lang['main_edit_news'] . ' - ' . $temp['name_post'],
						'L_NAME_USER' => $lang['main_user_add'],
						'L_NAME_POST' => $lang['news_name_post'],
						'L_TEXT_POST' => $lang['news_text_post'],
						'L_SAVE_NEWS' => $lang['news_edit_post'],

						'D_NAME_USER' => $name_user,
						'D_NAME_POST' => $temp['name_post'],
						'D_TEXT_POST' => $temp['text_post'],

						'IF_NEED_USER' => true,

						'U_SAVE_NEWS' => $work->config['site_url'] . '?action=news&subact=save&news=' . $news
			);
			$main_block = $template->create_template('news_save.tpl', $array_data);
		}
		else log_in_file('Unable to get the news', DIE_IF_ERROR);
	}
	else log_in_file($db->error, DIE_IF_ERROR);
}
elseif ($subact == 'delete' && $news !== false && ($user->user['news_moderate'] == true || ($user->user['id'] != 0 && $user->user['id'] == $temp['user_post'])))
{
	if (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'action=news') !== false) $redirect_url = $work->config['site_url'] . '?action=news';
	else $redirect_url = $work->config['site_url'];
	$redirect_time = 5;
	$redirect_message = $lang['news_title'] . ' ' . $temp['name_post'] . ' ' . $lang['news_del_post'];
	if (!$db->delete(TBL_NEWS, '`id` = ' . $news)) log_in_file($db->error, DIE_IF_ERROR);
}
elseif ($subact == 'add' && $news === false && $user->user['news_add'] == true)
{
	$title = $lang['news_add_post'];
	$act = 'news_add';

	$array_data = array();
	$array_data = array(
				'NAME_BLOCK' => $lang['news_add_post'],
				'L_NAME_USER' => '',
				'L_NAME_POST' => $lang['news_name_post'],
				'L_TEXT_POST' => $lang['news_text_post'],
				'L_SAVE_NEWS' => $lang['news_edit_post'],

				'D_NAME_USER' => '',
				'D_NAME_POST' => '',
				'D_TEXT_POST' => '',

				'IF_NEED_USER' => false,

				'U_SAVE_NEWS' => $work->config['site_url'] . '?action=news&subact=save'
	);
	$main_block = $template->create_template('news_save.tpl', $array_data);
}
else
{
	if ($news !== false)
	{
		$main_block = $template->template_news($news, 'id');
		$act = '';
	}
	else
	{
		if (!isset($_REQUEST['y']) || empty($_REQUEST['y']) || !mb_ereg('^[0-9]{4}$', $_REQUEST['y']))
		{
			$act = 'news';
			if ($db->select('DISTINCT DATE_FORMAT(`data_last_edit`, \'%Y\') AS `year`', TBL_NEWS, false, array('data_last_edit' => 'up')))
			{
				$temp = $db->res_arr();
				if (!$temp) $main_block = $template->template_news($work->config['last_news']);
				else
				{
					$spisok = '<br />';
					foreach ($temp as $val)
					{
						if ($db->select('COUNT(*) as `count_news`', TBL_NEWS, 'DATE_FORMAT(`data_last_edit`, \'%Y\') = \'' . $val['year'] . '\''))
						{
							$temp2 = $db->res_row();
							if (isset($temp2['count_news'])) $temp2 = $temp2['count_news'];
							else $temp2 = 0;
						}
						else log_in_file($db->error, DIE_IF_ERROR);
						$spisok .= '&bull;&nbsp;<a href="' . $work->config['site_url'] . '?action=news&y=' . $val['year'] . '" title="' . $val['year'] . ' (' . $lang['news_num_news'] . ': ' . $temp2 . ')">' . $val['year'] . ' (' . $temp2 . ')</a><br /><br />';
					}
					$array_data = array();
					$array_data = array(
									'NAME_BLOCK' => $lang['news_news'],
									'L_NEWS_DATA' => $lang['news_news'] . ' ' . $lang['news_on_years'],
									'L_TEXT_POST' => $spisok,

									'IF_EDIT_SHORT' => false,
									'IF_EDIT_LONG' => false
					);
					$main_block = $template->create_template('news.tpl', $array_data);
					$title = $lang['news_news'] . ' ' . $lang['news_on_years'];
				}
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}
		else
		{
			$year = $_REQUEST['y'];
			if (!isset($_REQUEST['m']) || empty($_REQUEST['m']) || !mb_ereg('^[0-9]{2}$', $_REQUEST['m']))
			{
				$act = '';
				if ($db->select('DISTINCT DATE_FORMAT(`data_last_edit`, \'%m\') AS `month`', TBL_NEWS, 'DATE_FORMAT(`data_last_edit`, \'%Y\') = \'' . $year . '\'', array('data_last_edit' => 'up')))
				{
					$temp = $db->res_arr();
					if (!$temp) $main_block = $template->template_news($work->config['last_news']);
					else
					{
						$spisok = '<br />';
						foreach ($temp as $val)
						{
							if ($db->select('COUNT(*) as `count_news`', TBL_NEWS, 'DATE_FORMAT(`data_last_edit`, \'%Y\') = \'' . $year . '\' AND DATE_FORMAT(`data_last_edit`, \'%m\') = \'' . $val['month'] . '\''))
							{
								$temp2 = $db->res_row();
								if (isset($temp2['count_news'])) $temp2 = $temp2['count_news'];
								else $temp2 = 0;
							}
							else log_in_file($db->error, DIE_IF_ERROR);
							$spisok .= '&bull;&nbsp;<a href="' . $work->config['site_url'] . '?action=news&y=' . $year . '&m=' . $val['month'] . '" title="' . $lang['news'][$val['month']] . ' (' . $lang['news_num_news'] . ': ' . $temp2 . ')">' . $lang['news'][$val['month']] . ' (' . $temp2 . ')</a><br />';
						}
						$array_data = array();
						$array_data = array(
									'NAME_BLOCK' => $lang['news_news'],
									'L_NEWS_DATA' => $lang['news_news'] . ' ' . $lang['news_on'] . ' ' . $year . ' ' . $lang['news_on_month'],
									'L_TEXT_POST' => $spisok,

									'IF_EDIT_SHORT' => false,
									'IF_EDIT_LONG' => false
						);
						$main_block = $template->create_template('news.tpl', $array_data);
						$title = $lang['news_news'] . ' ' . $lang['news_on'] . ' ' . $year. ' ' . $lang['news_on_month'];
					}
				}
				else log_in_file($db->error, DIE_IF_ERROR);
			}
			else
			{
				$month = $_REQUEST['m'];
				$act = '';
				if ($db->select('*', TBL_NEWS, 'DATE_FORMAT(`data_last_edit`, \'%Y\') = \'' . $year . '\' AND DATE_FORMAT(`data_last_edit`, \'%m\') = \'' . $month . '\'', array('data_last_edit' => 'up')))
				{
					$temp = $db->res_arr();
					if (!$temp) $main_block = $template->template_news($work->config['last_news']);
					else
					{
						$spisok = '<br />';
						foreach ($temp as $val)
						{
							$spisok .= '&bull;&nbsp;<a href="' . $work->config['site_url'] . '?action=news&news=' . $val['id'] . '" title="' . substr($work->clean_field($val['text_post']), 0, 100) . '...">' . $val['name_post'] . '</a><br />';
						}
						$array_data = array();
						$array_data = array(
										'NAME_BLOCK' => $lang['news_news'],
										'L_NEWS_DATA' => $lang['news_news'] . ' ' . $lang['news_on'] . ' ' . $lang['news'][$month] . ' ' . $year . ' ' . $lang['news_years'],
										'L_TEXT_POST' => $spisok,

										'IF_EDIT_SHORT' => false,
										'IF_EDIT_LONG' => false
						);
						$main_block = $template->create_template('news.tpl', $array_data);
						$title = $lang['news_news'] . ' ' . $lang['news_on'] . ' ' . $lang['news'][$month] . ' ' . $year . ' ' . $lang['news_years'];
					}
				}
				else log_in_file($db->error, DIE_IF_ERROR);
			}
		}
	}
}

$redirect = array();

if (!empty($redirect_time))
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
echo $template->create_main_template($act, $title, $main_block, $redirect);
?>
