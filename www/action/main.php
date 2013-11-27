<?php
/**
 * @file        action/main.php
 * @brief       Главная страница.
 * @author      Dark Dayver
 * @version     0.2.0
 * @date        28/03-2012
 * @details     Вывод и обработка главной страницы сайта.
 */
/// @cond
if (IN_GALLERY !== TRUE)
{
	die('HACK!');
}

include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/main.php');

$title = $lang['main']['main'];
$news = $work->news($work->config['last_news'], 'last');
if ($news && $user->user['news_view'] == TRUE)
{
	foreach ($news as $key => $val)
	{
		$template->add_string_ar(array(
				'L_TITLE_NEWS_BLOCK' => $lang['main']['title_news'] . ' - ' . $val['name_post'],
				'L_NEWS_DATA'        => $lang['main']['data_add'] . ': ' . $val['data_post'] . ' (' . $val['data_last_edit'] . ').',
				'L_TEXT_POST'        => trim(nl2br($work->ubb($val['text_post'])))
			), 'LAST_NEWS[' . $key . ']');
		$template->add_if_ar(array(
				'USER_EXISTS' => FALSE,
				'EDIT_SHORT'  => FALSE,
				'EDIT_LONG'   => FALSE
			), 'LAST_NEWS[' . $key . ']');
		if ($db->select('real_name', TBL_USERS, '`id` = ' . $val['user_post']))
		{
			$user_add = $db->res_row();
			if ($user_add)
			{
				$template->add_if('USER_EXISTS', TRUE, 'LAST_NEWS[' . $key . ']');
				$template->add_string_ar(array(
						'L_USER_ADD'            => $lang['main']['user_add'],
						'U_PROFILE_USER_POST'   => $work->config['site_url'] . '?action=profile&amp;subact=profile&amp;uid=' . $val['user_post'],
						'D_REAL_NAME_USER_POST' => $user_add['real_name']
					), 'LAST_NEWS[' . $key . ']');
			}
		}
		else log_in_file($db->error, DIE_IF_ERROR);

		if ($user->user['news_moderate'] == TRUE || ($user->user['id'] != 0 && $user->user['id'] == $val['user_post']))
		{
			$template->add_if('EDIT_SHORT', TRUE, 'LAST_NEWS[' . $key . ']');
			$template->add_string_ar(array(
					'L_EDIT_BLOCK'           => $lang['main']['edit_news'],
					'L_DELETE_BLOCK'         => $lang['main']['delete_news'],
					'L_CONFIRM_DELETE_BLOCK' => $lang['main']['confirm_delete_news'] . ' ' . $val['name_post'] . '?',
					'U_EDIT_BLOCK'           => $work->config['site_url'] . '?action=news&amp;subact=edit&amp;news=' . $val['id'],
					'U_DELETE_BLOCK'         => $work->config['site_url'] . '?action=news&amp;subact=delete&amp;news=' . $val['id']
				), 'LAST_NEWS[' . $key . ']');
		}
	}
}
else
{
	$template->add_if_ar(array(
		'USER_EXISTS' => FALSE,
		'EDIT_SHORT'  => FALSE,
		'EDIT_LONG'   => FALSE
	), 'LAST_NEWS[0]');
	$template->add_string_ar(array(
		'L_TITLE_NEWS_BLOCK' => $lang['main']['no_news'],
		'L_NEWS_DATA'        => '',
		'L_TEXT_POST'        => $lang['main']['no_news']
	), 'LAST_NEWS[0]');
}
/// @endcond
?>
