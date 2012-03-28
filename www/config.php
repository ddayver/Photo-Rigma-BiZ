<?php
/**
* @file		config.php
* @brief	Редактируемые настройки сервера.
* @author	Dark Dayver
* @version	0.1.1
* @date		27/03-2012
* @details	Содержит как редактируемые, так и автоматически генерируемые настройки сервера.
*/

if (IN_GALLERY)
{
	die('HACK!');
}

/**
* @var $config
* @brief Содержит массив конфигурации сервера.
* @see work::$config
*/
$config = array();
/*****************************************************************************
**	Начало редактируемой части настроек										**
*****************************************************************************/

$config['dbhost'] = 'localhost'; ///< Сервер основной базы данных (по умолчанию: localhost)
$config['dbuser'] = 'root'; ///< Имя пользователя в БД
$config['dbpass'] = ''; ///< Пароль пользователя БД
$config['dbname'] = 'photorigma'; ///< Имя базы данных
$config['gallery_folder'] = 'gallery'; ///< Имя папки для хранения фотографий
$config['thumbnail_folder'] = 'thumbnail'; ///< Имя папки для хранения эскизов фотографий
$config['avatar_folder'] = 'avatar'; ///< Имя папки для хранения аватар пользовтелей

/*****************************************************************************
**	Конец редактируемой части настроек										**
*****************************************************************************/

/**
*	Начало блока авто-настроек
*	@cond
*/
if (isset($_SERVER['HTTPS'])) $config['site_url'] = 'https://'; else $config['site_url'] = 'http://';
$config['site_url'] .= GetEnv("HTTP_HOST") . $_SERVER['SCRIPT_NAME'];
$config['site_url'] = str_replace('index.php', '', $config['site_url']);
$config['site_dir'] = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']);
$config['site_dir'] = str_replace('index.php', '', $config['site_dir']);
$config['inc_dir'] = $config['site_dir'] . 'include/';
/**
*	@endcond
*	Конец блока авто-настроек
*/
?>
