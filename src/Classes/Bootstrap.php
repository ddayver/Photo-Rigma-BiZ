<?php

/**
 * Инициализация ядра проекта.
 *
 * Класс Bootstrap реализует методы, которые:
 * - Проверяют наличие WORK_DIR
 * - Проверяют существование обязательных файлов:
 *   - config/config.php
 *   - src/Include/constants.php
 *   - src/Include/session_init.php
 *   - src/Include/functions.php
 * - Проверяют доступность этих файлов для чтения
 * - Выбрасывают исключение при ошибках инициализации
 * - Инициируют объекты ядра класса
 *
 * @author     Dark Dayver
 * @version    0.5.0
 * @since      2025-05-26
 * @namespace  PhotoRigma\\Classes
 * @package   PhotoRigma
 *
 * @note       Файл должен быть подключен только через точку входа index.php.
 *             Методы init(), load() вызываются один раз — при запуске приложения.
 *
 * @warning    Прямой вызов файла запрещён — проверка IN_GALLERY не пройдена → скрипт завершится с "HACK!".
 *             Нарушение порядка проверки файлов может привести к ошибкам инициализации
 *
 * @uses       \PhotoRigma\Interfaces\Bootstrap_Interface Интерфейс точки инициализации.
 *
 * @throws     RuntimeException При ошибках инициализации.
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
 * Класс Bootstrap реализует инициализацию ядра проекта.
 *
 * Класс:
 * - Реализует метод init(), который проверяет наличие и доступность обязательных файлов.
 * - Метод load(), который инициирует объекты ядра проекта.
 * - Метод change_user(), который позволяет переинициировать объекты, связанные с пользователем.
 * - Используется только через точку входа index.php.
 * - Выбрасывает исключения при ошибках инициализации.
 *
 * @class Bootstrap
 *
 * @note init(), load() вызываются один раз — при запуске приложения.
 *       Все ошибки проверок выбрасываются как RuntimeException.
 *
 * @warning Нарушение порядка проверки или подключения файлов может привести к фатальным последствиям.
 *
 * @throws RuntimeException Если WORK_DIR не определена.
 * @throws RuntimeException Если один из обязательных файлов отсутствует или недоступен.
 */
class Bootstrap implements Bootstrap_Interface
{
    /**
     * Выполняет инициализацию ядра проекта.
     *
     * Метод является публичным редиректом на защищенный метод _init_internal(), который выполняет следующие действия:
     * - Проверяет наличие WORK_DIR
     * - Формирует список обязательных файлов
     * - Проверяет существование и доступность каждого файла
     * - Сохраняет ошибки в массив
     * - Выбрасывает исключение при ошибках проверки
     * - Подключает все обязательные файлы через require_once
     *
     * @return array Список доступных файлов для подключения с их полным путем.
     *
     * @note    Метод вызывается один раз — при запуске приложения.
     *          Все ошибки логируются через стандартное исключение RuntimeException.
     *
     * @warning Нарушение порядка подключения файлов может привести к фатальным ошибкам.
     *
     * @uses \PhotoRigma\Classes\Bootstrap::_init_internal() Метод, реализующий основную логику.
     */
    public static function init(): array
    {
        return self::_init_internal();
    }

    /**
     * Защищенный метод, который выполняет инициализацию ядра проекта.
     *
     * Метод:
     * - Проверяет наличие WORK_DIR
     * - Формирует список обязательных файлов
     * - Проверяет существование и доступность каждого файла
     * - Сохраняет ошибки в массив
     * - Выбрасывает исключение при ошибках проверки
     *
     * @return array Список доступных файлов для подключения с их полным путем.
     *
     * @note    Метод вызывается один раз — при запуске приложения.
     *          Все ошибки логируются через стандартное исключение RuntimeException.
     *
     * @warning Нарушение порядка подключения файлов может привести к фатальным ошибкам.
     */
    protected static function _init_internal(): array
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
     * Инициализирует основные компоненты приложения.
     *
     * Метод является публичным редиректом для защищенного метода _load_internal, который реализует:
     * - Создаёт объект кеширования Cache_Handler с настройками из $config['cache']
     * - Подключает базу данных через Database с параметрами из $config['db']
     * - Инициализирует класс Work с передачей $db, $config, $session, $cache
     * - Запускает change_user() для определения пользователя и шаблона
     * - Возвращает массив с объектами: [$db, $work, $user, $template]
     *
     * @param array $config   Конфигурационный массив проекта
     * @param array $session  Ссылка на сессию — используется для авторизации
     *
     * @return array Массив с инициализированными объектами: [Database, Work, User, Template]
     *
     * @note Метод вызывается один раз — при запуске приложения.
     *       Все зависимости создаются внутри метода: Cache_Handler, Database, Work.
     *       Для работы требует корректного $config и активной сессии.
     *
     * @warning Не меняйте порядок инициализации — это может привести к ошибкам.
     *          Передавайте $session по ссылке — иначе сессия не будет обновляться.
     *
     * @throws JsonException При ошибке кодирования прав доступа в JSON.
     * @throws Exception     При внутренних ошибках создания объектов.
     *
     * @uses \PhotoRigma\Classes\Bootstrap::_load_internal() Защищенный метод, реализующий логику.
     */
    public static function load(array $config, array &$session): array
    {
        return self::_load_internal($config, $session);
    }

    /**
     * Защищенный метод, который инициализирует основные компоненты приложения.
     *
     * Метод:
     * - Создаёт объект кеширования Cache_Handler с настройками из $config['cache']
     * - Подключает базу данных через Database с параметрами из $config['db']
     * - Инициализирует класс Work с передачей $db, $config, $session, $cache
     * - Запускает change_user() для определения пользователя и шаблона
     * - Возвращает массив с объектами: [$db, $work, $user, $template]
     *
     * @param array $config   Конфигурационный массив проекта
     * @param array $session  Ссылка на сессию — используется для авторизации
     *
     * @return array Массив с инициализированными объектами: [Database, Work, User, Template]
     *
     * @note Метод вызывается один раз — при запуске приложения.
     *       Все зависимости создаются внутри метода: Cache_Handler, Database, Work.
     *       Для работы требует корректного $config и активной сессии.
     *
     * @warning Не меняйте порядок инициализации — это может привести к ошибкам.
     *          Передавайте $session по ссылке — иначе сессия не будет обновляться.
     *
     * @throws JsonException При ошибке кодирования прав доступа в JSON.
     * @throws Exception     При внутренних ошибках создания объектов.
     *
     * @uses \PhotoRigma\Classes\Bootstrap::_change_user_internal() Метод, инициирующий объекты пользователя и
     *                                                              шаблонизатора.
     */
    protected static function _load_internal(array $config, array &$session): array
    {
        // --- Кеширование ---
        $cache = new Cache_Handler($config['cache'] ?? []);

        // --- База данных ---
        $db = new Database($config['db'] ?? [], $cache);

        // --- Work - основной класс приложения ---
        $work = new Work($db, $config, $session, $cache);

        [$user, $template] = self::_change_user_internal($db, $work, $session);

        return [$db, $work, $user, $template];
    }

    /**
     * Инициализирует объекты пользователя и шаблонизатора.
     *
     * Метод является публичным редиректом на защищенный метод _change_user_internal, который реализует:
     * - Создаёт объект User с переданным $db и $session
     * - Определяет тему оформления из сессии или конфигурации
     * - Создаёт объект Template с указанием директорий шаблона
     * - Выполняет DI между объектами: Work → User, Template → Work, User → Work
     * - Возвращает массив [$user, $template]
     *
     * @param Database_Interface $db      Объект подключения к базе данных
     * @param Work_Interface     $work    Основной класс приложения
     * @param array              $session Ссылка на сессию — используется для авторизации
     *
     * @return array Массив с объектами: [User, Template]
     *
     * @note Метод вызывается один раз — при инициализации ядра.
     *       Работает только с уже созданными $db и $work.
     *       Тема берётся из сессии пользователя или конфига сайта.
     *
     * @warning Не меняйте порядок DI — это может привести к ошибкам в связях.
     *          Передавайте $session по ссылке — иначе данные не сохранятся.
     *
     * @throws JsonException При ошибке кодирования прав доступа в JSON.
     *
     * @uses \PhotoRigma\Classes\Bootstrap::_change_user_internal() Метод, реализующий логику.
     */
    public static function change_user(Database_Interface $db, Work_Interface $work, array &$session): array
    {
        return self::_change_user_internal($db, $work, $session);
    }

    /**
     * Защищенный метод, который инициализирует объекты пользователя и шаблонизатора.
     *
     * Метод:
     * - Создаёт объект User с переданным $db и $session
     * - Определяет тему оформления из сессии или конфигурации
     * - Создаёт объект Template с указанием директорий шаблона
     * - Выполняет DI между объектами: Work → User, Template → Work, User → Work
     * - Возвращает массив [$user, $template]
     *
     * @param Database_Interface $db      Объект подключения к базе данных
     * @param Work_Interface     $work    Основной класс приложения
     * @param array              $session Ссылка на сессию — используется для авторизации
     *
     * @return array Массив с объектами: [User, Template]
     *
     * @note Метод вызывается один раз — при инициализации ядра.
     *       Работает только с уже созданными $db и $work.
     *       Тема берётся из сессии пользователя или конфига сайта.
     *
     * @warning Не меняйте порядок DI — это может привести к ошибкам в связях.
     *          Передавайте $session по ссылке — иначе данные не сохранятся.
     *
     * @throws JsonException При ошибке кодирования прав доступа в JSON.
     */
    protected static function _change_user_internal(Database_Interface $db, Work_Interface $work, array &$session): array
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
