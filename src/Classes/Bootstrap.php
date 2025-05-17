<?php

/**
 * Инициализация ядра проекта.
 *
 * Класс Bootstrap реализует метод init(), который:
 * - Проверяет наличие WORK_DIR
 * - Проверяет существование обязательных файлов:
 *   - config/config.php
 *   - src/Include/constants.php
 *   - src/Impl/session_init.php
 *   - src/Include/functions.php
 * - Проверяет доступность этих файлов для чтения
 * - Выбрасывает исключение при ошибках инициализации
 * - Подключает файлы через require_once
 *
 * @package    PhotoRigma\Classes
 * @subpackage Bootstrap
 * @author     Dark Dayver
 * @version    0.5.0
 * @since      2025-05-15
 *
 * @note       Файл должен быть подключен только через точку входа index.php
 * @note       Метод init() вызывается один раз — при запуске приложения
 *
 * @warning    Прямой вызов файла запрещён — проверка IN_GALLERY не пройдена → скрипт завершится с "HACK!"
 * @warning    Нарушение порядка проверки файлов может привести к ошибкам инициализации
 *
 * Пример использования:
 * \PhotoRigma\Classes\Bootstrap::init();
 *
 * @uses \PhotoRigma\Interfaces\Bootstrap_Interface Интерфейс точки инициализации
 * @uses RuntimeException При ошибках инициализации
 *
 * @see config/config.php
 *      Конфигурационный файл проекта
 * @see src/Include/constants.php
 *      Глобальные константы
 * @see src/Include/session_init.php
 *      Логика инициализации сессии
 * @see src/Include/functions.php
 *      Глобальные функции
 * @see \PhotoRigma\Bootstrap_Interface::init()
 *      Интерфейсный контракт на метод init()
 *
 * @copyright Copyright (c) 2008–2025 Dark Dayver. Все права защищены.
 * @license   MIT License {@link https://opensource.org/licenses/MIT}
 *            Разрешается использовать, копировать, изменять, объединять, публиковать,
 *            распространять, сублицензировать и продавать копии программного обеспечения,
 *            а также разрешать лицам, которым предоставляется данное программное обеспечение,
 *            делать это при соблюдении следующих условий:
 *            - Уведомление об авторских правах и условия лицензии должны быть включены во все
 *              копии или значимые части программного обеспечения.
 */

namespace PhotoRigma\Classes;

use Exception;
use JsonException;
use PhotoRigma\Interfaces\Bootstrap_Interface;
use PhotoRigma\Interfaces\Database_Interface;
use PhotoRigma\Interfaces\Work_Interface;
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

/**
 * @class Bootstrap
 *
 * Класс Bootstrap реализует инициализацию ядра проекта.
 *
 * Класс:
 * - Реализует метод init(), который проверяет и подключает обязательные файлы
 * - Используется только через точку входа index.php
 * - Выбрасывает исключения при ошибках инициализации
 *
 * @note       init() вызывается один раз — при запуске приложения
 * @note       Все ошибки проверок выбрасываются как RuntimeException
 *
 * @warning    Нарушение порядка проверки или подключения файлов может привести к фатальным последствиям.
 *
 * @see \PhotoRigma\Bootstrap_Interface::init()
 *      Интерфейсный контракт на метод init()
 *
 * @throws RuntimeException Если WORK_DIR не определена
 * @throws RuntimeException Если один из обязательных файлов отсутствует или недоступен
 */
class Bootstrap implements Bootstrap_Interface
{
    /**
     * Выполняет инициализацию ядра проекта.
     *
     * Метод:
     * - Проверяет наличие WORK_DIR
     * - Формирует список обязательных файлов
     * - Проверяет существование и доступность каждого файла
     * - Сохраняет ошибки в массив
     * - Выбрасывает исключение при ошибках проверки
     * - Подключает все обязательные файлы через require_once
     *
     * @return array
     *
     * @note   Метод вызывается один раз — при запуске приложения
     * @note   Все ошибки логируются через стандартное исключение RuntimeException
     *
     * @warning Нарушение порядка подключения файлов может привести к фатальным ошибкам.
     *
     * Пример использования:
     * \PhotoRigma\Classes\Bootstrap::init();
     */
    public static function init(): array
    {
        // Проверяем, что WORK_DIR определена
        if (!defined('WORK_DIR')) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                'WORK_DIR не определена'
            );
        }

        // Список обязательных файлов
        $required_files = [
            WORK_DIR . '/config/config.php',
            WORK_DIR . '/src/Include/constants.php',
            WORK_DIR . '/src/Include/session_init.php',
            WORK_DIR . '/src/Include/functions.php',
        ];

        // Массив для хранения ошибок
        $errors = [];

        // Проходит по массиву обязательных файлов и проверяет, существуют ли они и доступны ли для чтения.
        foreach ($required_files as $file) {
            if (!is_file($file)) {
                $errors[] = "Файл отсутствует или не является файлом: $file";
            } elseif (!is_readable($file)) {
                $errors[] = "Файл существует, но недоступен для чтения: $file";
            }
        }

        // Если есть ошибки, логируем их и завершаем выполнение скрипта
        if (!empty($errors)) {
            $error_details = implode(PHP_EOL, $errors); // Подробности ошибок
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
                'Проверка обязательных файлов завершилась ошибкой | Ошибки: ' . PHP_EOL . ' | ' . $error_details
            );
        }

        return $required_files;
    }

    /**
     * @param array $config
     * @param array $session
     * @return array
     * @throws JsonException
     * @throws Exception
     */
    public static function load(array $config, array &$session): array
    {
        // --- Кеширование ---
        $cache = new Cache_Handler($config['cache'] ?? [], $config['site_dir'] ?? null);

        // --- База данных ---
        $db = new Database($config['db'] ?? [], $cache);

        // --- Work - основной класс приложения ---
        $work = new Work($db, $config, $session, $cache);

        [$user, $template] = self::change_user($db, $work, $session);

        return [$db, $work, $user, $template];
    }

    /**
     * @param Database_Interface $db
     * @param Work_Interface $work
     * @param array $session
     * @return array
     * @throws JsonException
     */
    public static function change_user(Database_Interface $db, Work_Interface $work, array &$session): array
    {
        // --- User - работа с пользователем ---
        $user = new User($db, $session);

        // --- Template - шаблонизация ---
        $themes_config = $user->session['theme'] ?? $work->config['theme'] ?? 'default';
        $template = new Template($work->config['template_dirs'], $themes_config);

        // --- DI между объектами ---
        $work->set_user($user);
        $work->set_lang();
        $template->set_work($work);
        $user->set_work($work);

        return [$user, $template];
    }
}
