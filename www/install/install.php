<?php
/**
* @file		install/install.php
* @brief	Разработка установочного скрипта с возможностью обновления.
* @author	Dark Dayver
* @version	0.1.1
* @date		27/03-2012
* @details	Разработка установочного скрипта с возможностью обновления. На текущий момент отложена.
*/
/// @cond
define ('IN_GALLERY', FALSE); // Константа, используемая в подключаемых файлах для определения, что они вызываются из индексного файла, а не прямым набором с целью взлома

include_once ('../include/rev.php');
$cur_rev = $rev;
include_once ('../sql/rev.php');
$new_rev = $rev;
include_once ('../config.php');

$link = @mysql_connect($config['dbhost'], $config['dbuser'], $config['dbpass']);
if($link)
{
	@mysql_select_db($config['dbname'], $link);
	@mysql_query("SET CHARSET utf8");

	$version = @mysql_query('SELECT `rev` FROM `db_version`', $link);
	$ver = @mysql_fetch_assoc($version);

	$db_rev = $ver['rev'];
	echo 'Cur. revision = ' . $cur_rev . '<br />';
	echo 'New revision = ' . $new_rev . '<br />';
	echo 'DB revision = ' . $db_rev . '<br />';
}
else
{
	$config = file('../config.php');
	print_r($config);
}
/// @endcond
?>
