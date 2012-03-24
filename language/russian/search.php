<?php
/*****************************************************************************
**	File:	language/search.php												**
**	Diplom:	Gallery															**
**	Date:	13/01-2009														**
**	Ver.:	0.1																**
**	Autor:	Gold Rigma														**
**	E-mail:	nvn62@mail.ru													**
**	Decr.:	Языкоый файл страницы поиска									**
**	Lang.:	Russian (Русский)												**
*****************************************************************************/

// Проверка, что файл подключается из индексного, а не набран напрямую в адресной строке
if (IN_DIPLOM)
{
	die('HACK!');
}

$lang['search_title'] = 'Введите строку для поиска и выберите диапазон';
$lang['search_need_user'] = 'пользователи';
$lang['search_need_category'] = 'разделы';
$lang['search_need_news'] = 'новости';
$lang['search_need_photo'] = 'изображения';
$lang['search_find'] = 'Найдены';
$lang['search_no_find'] = 'Ничего не найдено';

?>