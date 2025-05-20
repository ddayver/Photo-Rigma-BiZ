<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnhandledExceptionInspection */
/**
 * @file      action/cron.php
 * @brief     Фоновые задачи системы: удаление пользователей, обновление данных, логирование.
 *
 * @author    Dark Dayver
 * @version   0.4.4
 * @date      2025-05-07
 * @namespace PhotoRigma\\Action
 *
 * @details   Этот файл предназначен для выполнения фоновых задач через cron.
 *            Он запускает системные процессы, которые должны выполняться вне контекста веб-запроса.
 *            Основные функции:
 *            - Вызов методов окончательного удаления пользователей.
 *            - Архивация старых логов.
 *            - Обработка других фоновых задач (в будущем).
 *            - Логирование действий и ошибок.
 *
 * @section   Cron_Main_Functions Основные задачи
 *            - Запуск окончательного удаления пользователей через метод `$user->cron_user_delete()`.
 *            - Архивироввание старых логов через функцию `archive_old_logs()`.
 *            - Возможность подключения других фоновых задач.
 *
 * @section   Cron_Error_Handling Обработка ошибок
 *            При возникновении ошибок генерируются исключения. Поддерживаемые типы исключений:
 *            - `Exception`: При выполнении прочих методов классов, функций или операций.
 *
 * @throws    Exception При выполнении прочих методов классов, функций или операций.
 *
 * @section   Cron_Related_Files Связанные файлы и классы
 *            - @see PhotoRigma::Classes::User::cron_user_delete()
 *                   Метод, выполняющий основную логику удаления пользователей.
 *            - @see PhotoRigma::Classes::Work::log_in_file()
 *                   Для записи событий в логи.
 *            - @see PhotoRigma::Include::archive_old_logs
 *                   Функция для архивации старых логов.
 *
 * @note      Этот файл должен запускаться только через CLI (cron), никогда не через веб-интерфейс.
 *            Использование exit() после вызова задачи гарантирует завершение скрипта.

 * @copyright Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license   MIT License (https://opensource.org/licenses/MIT)
 *            Разрешается использовать, копировать, изменять, объединять, публиковать,
 *            распространять, сублицензировать и/или продавать копии программного обеспечения,
 *            а также разрешать лицам, которым предоставляется данное программное обеспечение,
 *            делать это при соблюдении следующих условий:
 *            - Уведомление об авторских правах и условия лицензии должны быть включены во все
 *              копии или значимые части программного обеспечения.
 */

namespace PhotoRigma\Action;

use Exception;
use PhotoRigma\Classes\Database;
use PhotoRigma\Classes\Template;
use PhotoRigma\Classes\User;
use PhotoRigma\Classes\Work;

use function PhotoRigma\Include\archive_old_logs;
use function PhotoRigma\Include\log_in_file;

/** @var Database $db */
/** @var Work $work */
/** @var User $user */
/** @var Template $template */

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

// Начало выполнения работ
log_in_file(
    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
    'Фоновая задача: начало выполнения работ'
);

// Защита от запуска через браузер
if (PHP_SAPI !== 'cli') {
    log_in_file(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
        'Фоновая задача: запуск cron через веб-интерфейс запрещён'
    );
    exit(1);
}

// Включаем "таймер" выполнения скрипта
$start_time = microtime(true);

// Выполняем основную логику
try {
    // Окончательное удаление пользователей
    log_in_file(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
        'Фоновая задача: окончательное удаление пользователей'
    );
    $user->cron_user_delete();
    // Архивация старых логов
    log_in_file(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
        'Фоновая задача: архивирование старых логов'
    );
    archive_old_logs();
} catch (Exception $e) {
    log_in_file(
        __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
        'Фоновая задача: исключение при выполнении основной логики | Сообщение: ' . $e->getMessage()
    );
    exit(2);
}

// Получаем время выполнения скрипта
$end_time = microtime(true);
$execution_time = round(($end_time - $start_time) * 1000, 6); // Время в миллисекундах

// Логируем успешное завершение
log_in_file(
    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | ' .
    'Фоновая задача: все задачи завершены| Время выполнения: ' . $execution_time . 'мс'
);
exit(0);
