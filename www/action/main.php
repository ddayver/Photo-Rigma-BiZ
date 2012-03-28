<?php
/**
* @file		action/main.php
* @brief	Главная страница.
* @author	Dark Dayver
* @version	0.1.1
* @date		27/03-2012
* @details	Вывод и обработка главной страницы сайта.
*/

// Проверка, что файл подключается из индексного, а не набран напрямую в адресной строке
if (IN_GALLERY)
{
	die('HACK!');
}

include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/main.php'); // подключаем языковый файл основной страницы
include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/menu.php'); // подключаем языковый файл меню

echo $template->create_main_template('main', $lang['main_main'], $template->template_news($work->config['last_news'])); // выводим сформированную главную страницу сайта

?>