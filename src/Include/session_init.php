<?php

/**
 * Файл содержит настройки и инициализацию сессий и куков для управления состоянием пользователя.
 *
 * Этот файл содержит настройки и инициализацию сессий и куков, которые используются для управления состоянием
 * пользователя в приложении:
 * - Безопасная проверка значения HTTP_HOST для предотвращения атак через заголовки.
 * - Настройка параметров куков для сессий (время жизни, путь, домен, флаги secure и httponly).
 * - Инициализация сессии после настройки параметров куков.
 *
 * @author    Dark Dayver
 * @version   0.5.0
 * @since     2025-05-29
 * @namespace PhotoRigma\\Include
 * @package   PhotoRigma
 *
 * @section   SessionInit_Main_Functions Основные функции
 *            - Проверка и валидация HTTP_HOST для безопасного использования в куках.
 *            - Настройка параметров куков с использованием текущих параметров из session_get_cookie_params().
 *            - Инициализация сессии с применением настроенных параметров куков.
 *
 * @section   SessionInit_Error_Handling Обработка ошибок
 *            При возникновении ошибок генерируются исключения. Поддерживаемые типы исключений:
 *            - `RuntimeException`: Если не удалось определить HTTP_HOST или его формат некорректен.
 *
 * @throws    RuntimeException Если не удалось определить HTTP_HOST или его формат некорректен.
 *
 * @note      Этот файл является частью системы PhotoRigma и играет ключевую роль в управлении состоянием
 *            пользователя. Реализованы меры безопасности для предотвращения несанкционированного доступа через куки
 *            и сессии, такие как использование флагов secure и httponly.
 *
 * @copyright Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license   MIT License {@link https://opensource.org/licenses/MIT}
 *            Разрешается использовать, копировать, изменять, объединять, публиковать, распространять,
 *            сублицензировать и/или продавать копии программного обеспечения, а также разрешать лицам, которым
 *            предоставляется данное программное обеспечение, делать это при соблюдении следующих условий:
 *            - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые
 *              части программного обеспечения.
 */

namespace PhotoRigma\Include;

use RuntimeException;

// Предотвращение прямого вызова файла
if (!defined('IN_GALLERY') || IN_GALLERY !== true) {
    /** @noinspection ForgottenDebugOutputInspection */
    error_log(
        date('H:i:s') . ' [ERROR] | ' . (filter_input(
            INPUT_SERVER,
            'REMOTE_ADDR',
            FILTER_VALIDATE_IP
        ) ?: 'UNKNOWN_IP') . ' | ' . __FILE__ . ' | Попытка прямого вызова файла'
    );
    die('HACK!');
}

// =============================================================================
// НАСТРОИКИ СЕССИй И КУКОВ
// =============================================================================

/**
 * @brief   Безопасная проверка HTTP_HOST.
 * @details Используются различные источники данных для определения HTTP_HOST:
 *          - $_SERVER['HTTP_HOST'] (основной источник).
 *          - $_SERVER['SERVER_NAME'] (резервный источник).
 *          Выполняется валидация формата домена для предотвращения атак через заголовки.
 *
 * @var string|null $cookie_domain
 * @brief   Домен, используемый для настройки куков.
 * @details Значение извлекается из $_SERVER['HTTP_HOST'] с применением фильтрации для защиты от вредоносных данных.
 *          Если значение недоступно или некорректно, используется резервный источник $_SERVER['SERVER_NAME'].
 */
$cookie_domain = filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Если HTTP_HOST не установлен, пробуем SERVER_NAME
if (!$cookie_domain) {
    /** @noinspection HostnameSubstitutionInspection */
    $cookie_domain = filter_var($_SERVER['SERVER_NAME'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

// Установка значения HTTP_HOST для CLI
if (PHP_SAPI === 'cli' && !$cookie_domain) {
    $cookie_domain = 'localhost';
}

// Если HTTP_HOST всё ещё не установлен, выбрасываем исключение
if (!$cookie_domain) {
    throw new RuntimeException(
        __FILE__ . ':' . __LINE__ . ' (' . (__FUNCTION__ ?: 'global') . ') | Не удалось определить HTTP_HOST | Проверьте настройки сервера'
    );
}
// Валидация формата домена
if (!preg_match('/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i', $cookie_domain)) {
    throw new RuntimeException(
        __FILE__ . ':' . __LINE__ . ' (' . (__FUNCTION__ ?: 'global') . ") | Некорректный формат HTTP_HOST | Значение: $cookie_domain"
    );
}

/**
 * @brief   Настройка параметров куков для сессий.
 * @details Используются текущие параметры куков, полученные через session_get_cookie_params().
 *          Эти параметры применяются для настройки сессий с помощью session_set_cookie_params().
 *
 *
 * @brief   Текущие параметры куков, полученные через session_get_cookie_params().
 * @details Содержит следующие ключи:
 *          - lifetime: Время жизни куки в секундах.
 *          - path: Путь, для которого действует куки.
 *          - domain: Домен, для которого действует куки.
 *          - secure: Флаг, указывающий, что куки должны передаваться только по HTTPS.
 *          - httponly: Флаг, указывающий, что куки доступны только через HTTP(S), но не через JavaScript.
 */
$cur_cookie = session_get_cookie_params();

// Настройка параметров куков для сессий
session_set_cookie_params(
    $cur_cookie['lifetime'], // Время жизни куки
    $cur_cookie['path'],     // Путь, для которого действует куки
    $cookie_domain,          // Домен, для которого действует куки
    $cur_cookie['secure'],   // Куки передаются только по HTTPS
    $cur_cookie['httponly']  // Куки недоступны через JavaScript
);

/**
 * Инициализация сессии.
 *
 * @details Запускает сессию после настройки параметров куков.
 */
session_start();
