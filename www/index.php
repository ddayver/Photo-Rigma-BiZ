<?php
/**
 * @file        index.php
 * @brief       Основной файл сервера.
 * @author      Dark Dayver
 * @version     0.2.0
 * @date        27/03-2012
 * @details     Используется для подключения как классов, так и вызываемых модулей.
 */

/// @cond
define('IN_GALLERY', TRUE); // Используется для дальнейшей проверки в файлах, что они подключены через index.php, а не вызваны напрямую.
/// @endcond

include_once('config.php'); // Подключаем файл редактируемых пользователем настроек

include_once($config['inc_dir'] . 'common.php');

include_once($config['inc_dir'] . 'db.php');
/**
 * @var $db
 * @brief Создание объекта класса db для работы с основной БД
 * @see   ::$config, db
 */
$db = new db($config['dbhost'], $config['dbuser'], $config['dbpass'], $config['dbname']);

include_once($config['inc_dir'] . 'work.php');
/**
 * @var $work
 * @brief Создание объекта класса work
 * @see   work
 */
$work = new work();

include_once($config['inc_dir'] . 'template.php');
/**
 * @var $template
 * @brief Создание объекта класса template
 * @see   template, work::$config
 */
$template = new template($config['site_url'], $config['site_dir'], $work->config['themes']);

include_once($config['inc_dir'] . 'user.php');
/**
 * @var $user
 * @brief Создание объекта класса user
 * @see   user
 */
$user = new user();

/// Выводить ли заголовок и подвал страницы
$header_footer = TRUE;
/// Выводить ли обработанный шаблон
$template_output = TRUE;
/// По-умолчанию к заголовку ничего не добавляется
$title = NULL;

/**
 * @var $action
 * @brief Действие, которое необходимо выполнить
 */
$action = 'main';
/// @cond
if ($work->check_get('action', TRUE, TRUE) && $_GET['action'] != 'index' && !$work->url_check() && file_exists($work->config['site_dir'] . 'action/' . $action . '.php')) $action = $_GET['action'];
include_once($config['site_dir'] . 'action/' . $action . '.php');

$template->create_template();
if ($header_footer)
{
	$template->page_header($title);
	$template->page_footer();
}
if ($template_output) echo $template->content;
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
