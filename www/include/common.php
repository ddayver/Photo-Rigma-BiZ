<?php
/**
* @file		include/common.php
* @brief	Константы и функция ведения логов
* @author	Dark Dayver
* @version	0.2.0
* @date		28/03-2012
* @details	Содержит константы, создает функцию логирования и настраивает хранение куков
*/
if (IN_GALLERY)
{
	die('HACK!');
}

// Включаем сессии
$cookie_domain = GetEnv("HTTP_HOST");
$cur_cookie = session_get_cookie_params();
session_set_cookie_params($cur_cookie["lifetime"], $cur_cookie["path"], $cookie_domain, $cur_cookie["secure"], $cur_cookie["httponly"]);
session_start();

/// Функция сохранения ошибок в лог-файл
/**
* @param $txt содержит текст, необходимый для сохранения в лог-файл (обязательный параметр)
* @param $die указывает, что необходимо завершить работу скрипта и вывести сообщение об ошибке (по-умолчанию не завершать)
* @return True, если запись успешна, иначе False.
* @see ::$config
*/
function log_in_file($txt, $die = false)
{
	global $config;

	$log_file = $config['site_dir'] . 'log/' . date('Y_m_d') . '_log.txt';
	$write_txt = date('H:i:s') . ' | ' . $_SERVER['REMOTE_ADDR'] . ' | ' . $txt . PHP_EOL;
	file_put_contents($log_file, $write_txt, FILE_APPEND | LOCK_EX);
	if ($die) die ($txt);
}

// Общие таблицы для серверов
/// Таблица с настройками сервера
define ('TBL_CONFIG', 'config');
/// Таблица с пунктами меню
define ('TBL_MENU', 'menu');
/// Таблица с новостями
define ('TBL_NEWS', 'news');
/// Таблица с списком категорий на сервере
define ('TBL_CATEGORY', 'category');
/// Таблица с списком фотографий на сервере
define ('TBL_PHOTO', 'photo');
/// Таблица с оценками фотографий от пользователей
define ('TBL_RATE_USER', 'rate_user');
/// Таблица с оценками фотографий от модераторов
define ('TBL_RATE_MODER', 'rate_moder');
/// Таблица пользователей
define ('TBL_USERS', 'user');
/// Таблица групп пользователей с их правами
define ('TBL_GROUP', 'group');

/// Останавливать ли скрипт во время серьезных ошибок? (True - останавливать) @see ::log_in_file
define ('DIE_IF_ERROR', true);

//Что использовать как конец строки (для Win-серверов)
if (!defined('PHP_EOL')) define ('PHP_EOL', strtoupper(substr(PHP_OS, 0, 3) == 'WIN') ? chr(13) . chr(10) : chr(10));

/// Регексп, включает в себя все символы, допустимые для проверки полей типа Имя пользователя для входа
define('REG_LOGIN', '^[\u0030-\u0039\u0041-\u005A\u0061-\u007A]{1}[\u0020\u0030-\u0039\u0041-\u005A\u005F\u0061-\u007A]*[\u0030-\u0039\u0041-\u005A\u0061-\u007A]?\$');
/// Регексп, включает в себя все символы, допустимые для проверки полей типа Отображаемое имя
define('REG_NAME', '^[\u0021-\u10FFFF]{1}[\u0020-\u10FFFF]*[\u0021-\u10FFFF]?\$');
/// Регексп, используемый для проверки E-mail на правильность формата
define('REG_EMAIL', '^[_a-z0-9-]+(.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*\$');

?>
