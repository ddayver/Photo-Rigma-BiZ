<?php
/*****************************************************************************
**	File:	action/main.php													**
**	Diplom:	Gallery															**
**	Date:	13/01-2009														**
**	Ver.:	0.1																**
**	Autor:	Gold Rigma														**
**	E-mail:	nvn62@mail.ru													**
**	Decr.:	Вывод и обработка главной страницы сайта						**
*****************************************************************************/

// Проверка, что файл подключается из индексного, а не набран напрямую в адресной строке
if (IN_DIPLOM)
{
	die('HACK!');
}

include_once($config['site_dir'] . 'language/' . $config['language'] . '/main.php'); // подключаем языковый файл основной страницы
include_once($config['site_dir'] . 'language/' . $config['language'] . '/menu.php'); // подключаем языковый файл меню

echo $template->create_main_template('main', $lang['main_main'], $template->template_news($config['last_news'])); // выводим сформированную главную страницу сайта

?>