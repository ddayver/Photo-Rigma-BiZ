<?
if ($_SERVER['HTTPS']) $host = 'https://'; else $host = 'http://';
$host .= GetEnv("HTTP_HOST");
Header("Location: $host/");
?>
