<?php
/*****************************************************************************
**	File:	language/russian/login.php										**
**	Diplom:	Gallery															**
**	Date:	13/01-2009														**
**	Ver.:	0.1																**
**	Autor:	Gold Rigma														**
**	E-mail:	nvn62@mail.ru													**
**	Decr.:	Языкоый файл для процедуры регистрации и восстановления пароля	**
**	Lang.:	Russian (Русский)												**
*****************************************************************************/

// Проверка, что файл подключается из индексного, а не набран напрямую в адресной строке
if (IN_DIPLOM)
{
	die('HACK!');
}

$lang['login_regist'] = 'Регистрация';
$lang['login_login'] = 'Имя пользователя';
$lang['login_password'] = 'Пароль';
$lang['login_re_password'] = 'Повторно пароль';
$lang['login_email'] = 'E-mail';
$lang['login_real_name'] = 'Отображаемое имя';
$lang['login_register'] = 'Зарегистрировать';
$lang['login_user'] = 'Пользователь';
$lang['login_registered'] = 'успешно зарегистрирован!';
$lang['login_error'] = 'Ошибка(и)';
$lang['login_error_login'] = 'не верно указано имя пользователя';
$lang['login_error_password'] = 'не верно указан пароль';
$lang['login_error_re_password'] = 'пароли не совпадают';
$lang['login_error_email'] = 'не верно указан e-mail';
$lang['login_error_real_name'] = 'не верно указано отображаемое имя';
$lang['login_error_login_exists'] = 'такое имя пользователя уже существует';
$lang['login_error_email_exists'] = 'такой e-mail уже существует';
$lang['login_error_real_name_exists'] = 'такое отображаемое имя уже существует';
$lang['login_profile'] = 'Профиль';
$lang['login_edit_profile'] = 'Редактирование профиля';
$lang['login_confirm_password'] = 'Подтвердите изменения паролем';
$lang['login_save_profile'] = 'Сохранить профиль';
$lang['login_help_edit'] = 'только если планируете изменить эти данные';
$lang['login_avatar'] = 'Аватар пользователя';
$lang['login_delete_avatar'] = 'Удалить аватар';

?>