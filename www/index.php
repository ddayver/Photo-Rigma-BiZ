<?php
/**
* @file		index.php
* @brief	Основной файл сервера.
* @author	Dark Dayver
* @version	0.1.1
* @date		27/03-2012
* @details	Используется для подключения как классов, так и вызываемых модулей.
*/

/// @cond
define('IN_GALLERY', true); // Используется для дальнейшей проверки в файлах, что они подключены через index.php, а не вызваны напрямую.
/// @endcond

/// \todo Убрать заглушку после перехода на новый класс формирования шаблонов
$template_TMP = false; // Заглушка

include_once ('config.php'); // Подключаем файл редактируемых пользователем настроек

include_once ($config['inc_dir'] . 'common.php');

include_once ($config['inc_dir'] . 'db.php');
/**
* @var $db
* @brief Создание объекта класса db для работы с основной БД
* @see ::$config, db
*/
$db = new db($config['dbhost'], $config['dbuser'], $config['dbpass'], $config['dbname']);

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
/// @cond
if (isset($_GET['action']) && !empty($_GET['action']) && $_GET['action'] != 'index' && !$work->url_check())
{
	$action = $_GET['action'];
	if (!file_exists($work->config['site_dir'] . 'action/' . $action . '.php')) $action = 'main';
}

include_once($config['site_dir'] . 'action/' . $action . '.php');

/// \todo Убрать заглушку после перехода на новый класс формирования шаблонов
if ($template_TMP) // Заглушка
{ // Заглушка

$template_new->create_template();

if ($header_footer)
{
	$template_new->page_header($title);
	$template_new->page_footer();
}
if ($template_output) echo $template_new->content;
} // Заглушка
/// @endcond
// Документация для описани директорий (для DoxyGen)
/** @dir action
* Содержит подключаемые модули
*/
/** @dir avatar
* Содержит аватары пользователей
*/
/** @dir gallery
* Содержит папки с альбомами
*/
/** @dir gallery/user
* Содержит пользовательские альбомы
*/
/** @dir include
* Содержит подключаемые классы
*/
/** @dir install
* Содержит установочный скрипт (пока в разработке)
*/
/** @dir language
* Содержит папки с языковыми файлами
*/
/** @dir log
* Содержит отчеты об ошибках
*/
/** @dir themes
* Содержит папки с шаблонами тем
*/
/** @dir thumbnail
* Содержит папки с превью альбомов
*/
/** @dir thumbnail/user
* Содержит превью пользовательских альбомов
*/
?>
