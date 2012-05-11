<?
if (isset($_SERVER['HTTPS'])) $site_url = 'https://'; else $site_url = 'http://';
$site_url .= GetEnv("HTTP_HOST") . $_SERVER['SCRIPT_NAME'];
$site_url = str_replace('thumbnail/index.php', '', $site_url);
header('Location: ' . $site_url);
die('HACK!');
?>
