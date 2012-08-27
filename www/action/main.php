<?php
/**
* @file		action/main.php
* @brief	Главная страница.
* @author	Dark Dayver
* @version	0.2.0
* @date		28/03-2012
* @details	Вывод и обработка главной страницы сайта.
*/

if (IN_GALLERY)
{
	die('HACK!');
}

include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/main.php');
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/menu.php');
$title = $lang['main_main'];

// $template_TMP = true;

$news = $work->news($work->config['last_news'], 'last');
if ($news && $user->user['news_view'] == true)
{
	foreach ($news as $key => $val)
	{
		$template_new->add_string_ar(array(
			'L_TITLE_NEWS_BLOCK' => $lang['main_title_news'] . ' - ' . $val['name_post'],
			'L_NEWS_DATA' => $lang['main_data_add'] . ': ' . $val['data_post'] . ' (' . $val['data_last_edit'] . ').',
			'L_TEXT_POST' => trim(nl2br($val['text_post']))
		), 'LAST_NEWS[' . $key . ']');
		$template_new->add_if_ar(array(
			'USER_EXISTS' => false,
			'EDIT_SHORT' => false,
			'EDIT_LONG' => false
		), 'LAST_NEWS[' . $key . ']');
		if ($db->select('real_name', TBL_USERS, '`id` = ' . $val['user_post']))
		{
			$user_add = $db->res_row();
			if ($user_add)
			{
				$template_new->add_if('USER_EXISTS', true, 'LAST_NEWS[' . $key . ']');
				$template_new->add_string_ar(array(
					'L_USER_ADD' => $lang['main_user_add'],
					'U_PROFILE_USER_POST' => $work->config['site_url']  . '?action=login&subact=profile&uid=' . $val['user_post'],
					'D_REAL_NAME_USER_POST' => $user_add['real_name']
				), 'LAST_NEWS[' . $key . ']');
			}
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		if ($user->user['news_moderate'] == true || ($user->user['id'] != 0 && $user->user['id'] == $val['user_post']))
		{
			$template_new->add_if_ar(array(
				'EDIT_SHORT' => true,
				'EDIT_LONG' => true
			), 'LAST_NEWS[' . $key . ']');
			$template_new->add_string_ar(array(
				'L_EDIT_BLOCK' => $lang['main_edit_news'],
				'L_DELETE_BLOCK' => $lang['main_delete_news'],
				'L_CONFIRM_DELETE_BLOCK' => $lang['main_confirm_delete_news'] . ' ' . $val['name_post'] . '?',
				'U_EDIT_BLOCK' => $work->config['site_url'] . '?action=news&subact=edit&news=' . $val['id'],
				'U_DELETE_BLOCK' => $work->config['site_url'] . '?action=news&subact=delete&news=' . $val['id']
			), 'LAST_NEWS[' . $key . ']');
		}
	}
}
else
{
	$template_new->add_if_ar(array(
		'USER_EXISTS' => false,
		'EDIT_SHORT' => false,
		'EDIT_LONG' => false
	), 'LAST_NEWS[0]');
	$template_new->add_string_ar(array(
		'L_TITLE_NEWS_BLOCK' => $lang['main_no_news'],
		'L_NEWS_DATA' => '',
		'L_TEXT_POST' => trim(nl2br($news['L_TEXT_POST']))
	), 'LAST_NEWS[0]');
}

if (!$template_TMP) echo $template->create_main_template('main', $lang['main_main'], $template->template_news($work->config['last_news']));
?>
