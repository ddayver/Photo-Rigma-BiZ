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

$header_footer = true;
$template_output = true;
$template_TMP = false; // Заглушка
$action = 'main';
/// @cond
if (isset($_GET['action']) && !empty($_GET['action']) && $_GET['action'] != 'index' && !$work->url_check())
{
	$action = $_GET['action'];
	if (!file_exists($work->config['site_dir'] . 'action/' . $action . '.php')) $action = 'main';
}

include_once($config['site_dir'] . 'action/' . $action . '.php');

if ($template_TMP)
{

$template_new->create_template();

if ($header_footer) // Заглушка
{
	$template_new->page_header($title, $local_menu);
	$template_new->page_footer($debug);
}
if ($template_output) echo $template_new->content;
} // Заглушка
/// @endcond
// Документация для описани директорий (для DoxyGen)
/** @dir action
* Содержит подключаемые модули
*/
/** @dir include
* Содержит подключаемые классы
*/
/** @dir language
* Содержит папки с языковыми файлами
*/
?>
