<?php
/**
* @file		index.php
* @brief	Основной файл сервера.
* @author	Dark Dayver
* @version	0.1.1
* @date		27/03-2012
* @details	Используется для подключения как классов, так и вызываемых модулей.
*/
/**
* @enum IN_GALLERY
* @brief Используется для дальнейшей проверки в файлах, что они подключены через index.php, а не вызваны напрямую.
*/
define ('IN_GALLERY', FALSE);

include_once ('config.php'); // Подключаем файл редактируемых пользователем настроек

include_once ($config['inc_dir'] . 'db.php');
/**
* @var $db
* @brief Создание объекта класса db для работы с основной БД
* @see ::$config, db
*/
$db = new db($config['dbhost'], $config['dbuser'], $config['dbpass'], $config['dbname']);

include_once ($config['inc_dir'] . 'common.php');

include_once ($config['inc_dir'] . 'work.php');
/**
* @var $work
* @brief Создание объекта класса work
* @see work
*/
$work = new work();

include_once ($config['inc_dir'] . 'template.php');
/**
* @var $template
* @brief Создание объекта класса template
* @see template, work::$config
*/
$template = new template_old($config['site_url'], $config['site_dir'], $work->config['themes']);
$template_new = new template($config['site_url'], $config['site_dir'], $work->config['themes']);

include_once ($config['inc_dir'] . 'user.php');
/**
* @var $user
* @brief Создание объекта класса user
* @see user
*/
$user = new user();

/**
* @var $action
* @brief Действие, которое необходимо выполнить
*/
$action = 'main';
if (isset($_GET['action']) && !empty($_GET['action']) && $_GET['action'] != 'index' && !$work->url_check() && file_exists($work->config['site_dir'] . 'action/' . $action . '.php')) $action = $_GET['action'];

/// Выводить ли заголовок и подвал страницы
$header_footer = true;
/// Выводить ли обработанный шаблон
$template_output = true;
/// По-умолчанию к заголовку ничего не добавляется
$title = NULL;
$template_TMP = false; // Заглушка
/// @cond
if (isset($_GET['action']) && !empty($_GET['action']) && $_GET['action'] != 'index' && !$work->url_check())
{
	$action = $_GET['action'];
	if (!file_exists($work->config['site_dir'] . 'action/' . $action . '.php')) $action = 'main';
}

include_once($config['site_dir'] . 'action/' . $action . '.php');

if ($template_TMP) // Заглушка
{

$template_new->create_template();

if ($header_footer)
{
	$template_new->page_header($title, $work->config, $work->create_menu($action, 0), $work->create_menu($action, 1), $work->create_photo('top'), $work->create_photo('last'));
	$template_new->page_footer($work->config);
}
if ($template_output) echo $template_new->content;
} // Заглушка
/// @endcond
// Документация для описани директорий (для DoxyGen)
/** @dir /www/action
* Содержит подключаемые модули
*/
/** @dir /www/avatar
* Содержит аватары пользователей
*/
/** @dir /www/gallery
* Содержит папки с альбомами
*/
/** @dir /www/gallery/user
* Содержит пользовательские альбомы
*/
/** @dir /www/include
* Содержит подключаемые классы
*/
/** @dir /www/install
* Содержит установочный скрипт (пока в разработке)
*/
/** @dir /www/language
* Содержит папки с языковыми файлами
*/
/** @dir /www/log
* Содержит отчеты об ошибках
*/
/** @dir /www/themes
* Содержит папки с шаблонами тем
*/
/** @dir /www/thumbnail
* Содержит папки с превью альбомов
*/
/** @dir /www/thumbnail/user
* Содержит превью пользовательских альбомов
*/
?>
