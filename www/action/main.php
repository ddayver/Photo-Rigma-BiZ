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

echo $template->create_main_template('main', $lang['main_main'], $template->template_news($work->config['last_news']));
?>
