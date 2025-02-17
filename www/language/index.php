<?php

/**
 * @file        language/index.php
 * @brief       Перенаправление на корневой URL сайта.
 * @details     Этот скрипт проверяет протокол (HTTP/HTTPS), формирует корректный URL сайта и выполняет перенаправление.
 */

// Проверка наличия HTTPS
$protocol = 'http://';
if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') {
    $protocol = 'https://';
}

// Безопасное получение HTTP_HOST
$http_host = filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_URL);
if (empty($http_host) || !preg_match('/^[a-zA-Z0-9.-]+$/', $http_host)) {
    die('Invalid host detected.');
}

// Безопасное получение SCRIPT_NAME
$script_name = filter_input(INPUT_SERVER, 'SCRIPT_NAME', FILTER_SANITIZE_URL);
if (empty($script_name) || !preg_match('/^[a-zA-Z0-9\/._-]+$/', $script_name)) {
    die('Invalid script path detected.');
}

// Формирование базового URL
$site_url = $protocol . $http_host . $script_name;
$site_url = str_replace('language/index.php', '', $site_url);

// Проверка корректности сформированного URL
if (!filter_var($site_url, FILTER_VALIDATE_URL)) {
    die('Invalid site URL generated.');
}

// Установка заголовков безопасности
header_remove('X-Powered-By'); // Удаление информации о сервере
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains'); // Для HTTPS
header('Location: ' . $site_url);

// Завершение работы скрипта
die('Redirecting...');
