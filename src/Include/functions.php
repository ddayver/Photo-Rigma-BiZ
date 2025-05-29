<?php

/**
 * Файл содержит глобальные функции для работы с логами.
 *
 * Этот файл содержит глобальные функции для работы с логами:
 * - Архивация старых логов (функция `archive_old_logs`).
 * - Логирование ошибок (функция `log_in_file`).
 * Функции обеспечивают безопасное управление логами, включая архивацию старых файлов и запись новых данных.
 * Реализованы меры безопасности, такие как проверка доступа к файлам и директориям, ограничение размера логов и
 * использование блокировок при записи.
 *
 * @author    Dark Dayver
 * @version   0.5.0
 * @since     2025-05-29
 * @namespace PhotoRigma\\Include
 * @package   PhotoRigma
 *
 * @section   Functions_Main_Functions Основные функции
 *            - **Архивация старых логов**:
 *              - Сжимает логи старше недели в формате `.gz` и удаляет исходные файлы.
 *              - Использует константы `COMPRESSION_LEVEL` и `LOG_LIFETIME_DAYS`.
 *              - Требует расширение zlib для работы.
 *            - **Логирование ошибок**:
 *              - Записывает сообщения об ошибках в лог-файл.
 *              - Поддерживает ограничение размера файла лога (`MAX_LOG_SIZE`) и архивацию при превышении.
 *              - Может завершить выполнение скрипта при критических ошибках (устаревший параметр `$die`).
 *              - Использует блокировки для безопасной записи через потоки (flock).
 *
 * @uses      \PhotoRigma\Include\archive_old_logs Архивирует старые логи, сжимая их в формат .gz и удаляя исходные
 *                                                 файлы.
 * @uses      \PhotoRigma\Include\log_in_file Записывает сообщение об ошибке в лог-файл.
 *
 * @note      Этот файл является частью системы PhotoRigma и предоставляет инструменты для управления логами.
 *            Реализованы меры безопасности для предотвращения несанкционированного доступа и повреждения данных.
 *
 * @section   Functions_Error_Handling Обработка ошибок
 *            При возникновении ошибок генерируются исключения. Поддерживаемые типы исключений:
 *            - `RuntimeException`: Если не удалось создать директорию для логов или она недоступна для записи.
 *            - `RuntimeException`: Если не удалось записать данные в лог-файл.
 *            - `Exception`: Любые непредвиденные ошибки.
 *
 * @throws    RuntimeException Если не удалось создать директорию для логов или она недоступна для записи.
 * @throws    RuntimeException Если не удалось записать данные в лог-файл.
 * @throws    Exception Любые непредвиденные ошибки.
 *
 * @copyright Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license   MIT License {@link https://opensource.org/licenses/MIT}
 *            Разрешается использовать, копировать, изменять, объединять, публиковать, распространять,
 *            сублицензировать и/или продавать копии программного обеспечения, а также разрешать лицам, которым
 *            предоставляется данное программное обеспечение, делать это при соблюдении следующих условий:
 *            - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые
 *              части программного обеспечения.
 */
/** @noinspection ForgottenDebugOutputInspection */

namespace PhotoRigma\Include;

use Exception;
use RuntimeException;

// Предотвращение прямого вызова файла
if (!defined('IN_GALLERY') || IN_GALLERY !== true) {
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
// ГЛОБАЛЬНЫЕ ФУНКЦИИ ПРОЕКТА
// =============================================================================

/**
 * Архивирует старые логи, сжимая их в формат .gz и удаляя исходные файлы.
 *
 * Сжимает логи старше недели в формате .gz и удаляет исходные файлы.
 * Требует расширение zlib для работы. Если возникают проблемы (например,
 * отсутствие расширения zlib или доступа к файлам), ошибки логируются
 * через стандартную PHP-функцию error_log, но выполнение скрипта не прерывается.
 * Последовательность действий:
 * - Проверка наличия расширения zlib.
 * - Проверка доступности директории логов.
 * - Поиск файлов логов с расширением .txt.
 * - Определение даты неделю назад.
 * - Обработка каждого файла: проверка даты, сжатие, удаление исходного файла.
 * - Логирование ошибок через error_log при возникновении проблем.
 *
 * @return void Функция ничего не возвращает.
 *
 * @note    Используются константы:
 *          - COMPRESSION_LEVEL: Уровень сжатия (максимальный, значение 9).
 *          - LOG_LIFETIME_DAYS: Срок хранения логов ('-7 days').
 *          Требуется расширение zlib для работы функции.
 *
 * @warning Функция может пропустить файлы при возникновении ошибок (например, если файл недоступен для чтения или
 *          записи).
 */
function archive_old_logs(): void
{
    // Проверяем, подключено ли расширение zlib
    if (!extension_loaded('zlib')) {
        error_log(
            date(
                'H:i:s'
            ) . ' [ERROR] | UNKNOWN_IP | ' . __FILE__ . ':' . __LINE__ . ' (' . __FUNCTION__ . ') | Расширение zlib не подключено'
        );
        return;
    }

    // Проверяем существование директории логов
    if (!is_dir(LOG_DIR) || !is_writable(LOG_DIR)) {
        error_log(
            date('H:i:s') . ' [ERROR] | ' . (filter_input(
                INPUT_SERVER,
                'REMOTE_ADDR',
                FILTER_VALIDATE_IP
            ) ?: 'UNKNOWN_IP') . ' | ' . __FILE__ . ':' . __LINE__ . ' (' . __FUNCTION__ . ') | Директория логов отсутствует или недоступна для записи | Путь: ' . LOG_DIR
        );
        return;
    }

    // Получаем список файлов логов с расширением .txt
    $log_files = glob(LOG_DIR . '/*_log.txt', GLOB_NOSORT);
    if (empty($log_files)) {
        return; // Если файлов нет, выходим
    }

    // Определяем дату неделю назад
    $week_ago = strtotime(LOG_LIFETIME_DAYS);
    foreach ($log_files as $file) {
        // Извлекаем дату из имени файла
        $file_name = basename($file);
        if (preg_match('/^(\d{4}_\d{2}_\d{2})_log\.txt$/', $file_name, $matches)) {
            $file_date = strtotime(str_replace('_', '-', $matches[1])); // Преобразуем дату в timestamp

            // Если файл старше недели
            if ($file_date !== false && $file_date < $week_ago) {
                // Создаем имя архива
                $archive_file = $file . '.gz';

                // Читаем содержимое файла
                $file_content = file_get_contents($file);
                if ($file_content === false) {
                    error_log(
                        date('H:i:s') . ' [ERROR] | ' . (filter_input(
                            INPUT_SERVER,
                            'REMOTE_ADDR',
                            FILTER_VALIDATE_IP
                        ) ?: 'UNKNOWN_IP') . ' | ' . __FILE__ . ':' . __LINE__ . ' (' . __FUNCTION__ . ") | Не удалось прочитать файл | Путь: $file"
                    );
                    continue; // Пропускаем этот файл и переходим к следующему
                }

                // Сжимаем файл
                $gz_handle = gzopen($archive_file, 'w' . COMPRESSION_LEVEL);
                if ($gz_handle === false) {
                    error_log(
                        date('H:i:s') . ' [ERROR] | ' . (filter_input(
                            INPUT_SERVER,
                            'REMOTE_ADDR',
                            FILTER_VALIDATE_IP
                        ) ?: 'UNKNOWN_IP') . ' | ' . __FILE__ . ':' . __LINE__ . ' (' . __FUNCTION__ . ") | Не удалось создать архив | Путь: $archive_file"
                    );
                    continue; // Пропускаем этот файл и переходим к следующему
                }

                if (gzwrite($gz_handle, $file_content) === false) {
                    gzclose($gz_handle);
                    error_log(
                        date('H:i:s') . ' [ERROR] | ' . (filter_input(
                            INPUT_SERVER,
                            'REMOTE_ADDR',
                            FILTER_VALIDATE_IP
                        ) ?: 'UNKNOWN_IP') . ' | ' . __FILE__ . ':' . __LINE__ . ' (' . __FUNCTION__ . ") | Не удалось записать данные в архив | Путь: $archive_file"
                    );
                    continue; // Пропускаем этот файл и переходим к следующему
                }
                gzclose($gz_handle);

                // Удаляем исходный файл
                if (!unlink($file)) {
                    error_log(
                        date('H:i:s') . ' [ERROR] | ' . (filter_input(
                            INPUT_SERVER,
                            'REMOTE_ADDR',
                            FILTER_VALIDATE_IP
                        ) ?: 'UNKNOWN_IP') . ' | ' . __FILE__ . ':' . __LINE__ . ' (' . __FUNCTION__ . ") | Не удалось удалить исходный файл | Путь: $file"
                    );
                }
            }
        }
    }
}

/**
 * Записывает сообщение об ошибке в лог-файл и, при необходимости, завершает выполнение скрипта.
 *
 * Записывает сообщение об ошибке в лог-файл с возможностью завершения выполнения скрипта.
 * Последовательность действий:
 * - Проверка существования директории логов (создание, если отсутствует).
 * - Ограничение размера файла логов (архивация при превышении MAX_LOG_SIZE с использованием расширения zlib или без него).
 * - Подготовка текста для записи (включая трассировку, если включён DEBUG_GALLERY).
 * - Безопасная запись в файл через потоки с блокировкой (flock).
 * - Завершение работы скрипта при необходимости (если $die === true и DIE_IF_ERROR установлено).
 * Если возникают проблемы (например, отсутствие доступа к файлам), ошибки логируются через стандартную PHP-функцию error_log.
 *
 * @param string $txt Сообщение для записи в лог. Не должно быть пустым.
 * @param bool   $die Флаг, указывающий, следует ли завершить выполнение скрипта после записи. Значение по умолчанию —
 *                    false. Этот параметр скоро будет исключён из функции как устаревший.
 *
 * @return bool True, если запись успешна, иначе False.
 *
 * @throws RuntimeException Возникает при проблемах с созданием директории, доступом к файлам, чтением/записью данных.
 * @throws Exception        Любые непредвиденные ошибки.
 *
 * @note    Используются константы:
 *          - MAX_LOG_SIZE: Максимальный размер файла лога (10 MB).
 *          - COMPRESSION_LEVEL: Уровень сжатия (максимальный, значение 9).
 *          Требуется расширение zlib для архивации логов.
 *
 * @warning Функция может завершить выполнение скрипта, если установлен флаг $die и константа DIE_IF_ERROR.
 */
function log_in_file(string $txt, bool $die = false): bool
{
    static $log_in_process = false; // Статический флаг для предотвращения рекурсии
    if ($log_in_process) {
        return false;
    }
    $log_in_process = true;
    try {
        // Проверка существования директории для логов
        if (!is_dir(LOG_DIR)) {
            if (!mkdir($concurrent_directory = LOG_DIR, 0755, true) && !is_dir($concurrent_directory)) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . __FUNCTION__ . ') | Не удалось создать директорию | Путь: ' . LOG_DIR
                );
            }
            // Проверяем права доступа после создания
            if (!is_writable(LOG_DIR)) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . __FUNCTION__ . ') | Созданная директория недоступна для записи | Путь: ' . LOG_DIR
                );
            }
        }

        // Проверка прав доступа к директории логов
        if (!is_writable(LOG_DIR)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . __FUNCTION__ . ') | Директория недоступна для записи | Путь: ' . LOG_DIR
            );
        }

        // Определение имени файла лога
        $log_file = LOG_DIR . '/' . date('Y_m_d') . '_log.txt';

        // Ограничение размера файла логов
        if (is_file($log_file) && filesize($log_file) > MAX_LOG_SIZE) {
            $backup_file = $log_file . '_bak-' . date('Y_m_d_H_i_s') . '.gz';
            if (extension_loaded('zlib')) {
                $gz_handle = gzopen($backup_file, 'w' . COMPRESSION_LEVEL);
                if ($gz_handle) {
                    $file_content = file_get_contents($log_file);
                    if ($file_content === false) {
                        throw new RuntimeException(
                            __FILE__ . ':' . __LINE__ . ' (' . __FUNCTION__ . ") | Не удалось прочитать содержимое файла | Путь: $log_file"
                        );
                    }
                    gzwrite($gz_handle, $file_content);
                    gzclose($gz_handle);
                    unlink($log_file); // Удаляем исходный файл после сжатия
                }
            } else {
                // Меняем расширение файла с .gz на .txt
                $backup_file = pathinfo($backup_file, PATHINFO_DIRNAME) . '/' . pathinfo(
                    $backup_file,
                    PATHINFO_FILENAME
                ) . '.txt';
                rename($log_file, $backup_file); // Без сжатия
            }

            // Проверяем успешность создания архива
            if (!file_exists($backup_file)) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . __FUNCTION__ . ") | Не удалось создать архив | Путь: $backup_file"
                );
            }
        }

        // Подготовка текста для записи в лог
        $level = $die ? 'ERROR' : 'WARNING';
        $remote_ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
        $write_txt = date('H:i:s') . " [$level] | " . ($remote_ip ?: 'UNKNOWN_IP') . ' | ' . $txt . PHP_EOL;

        // Добавление трассировки, если DEBUG_GALLERY включен
        if (defined('DEBUG_GALLERY')) {
            /** @noinspection InsufficientTypesControlInspection */
            $trace_depth = match (true) {
                DEBUG_GALLERY === false                                           => 0,
                is_int(DEBUG_GALLERY) && DEBUG_GALLERY >= 1 && DEBUG_GALLERY <= 9 => DEBUG_GALLERY,
                default                                                           => 5, // Глубина трассировки по умолчанию
            };
            if ($trace_depth > 0) {
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, $trace_depth + 1);
                array_shift($backtrace);
                $trace_info = [];
                foreach ($backtrace as $index => $trace) {
                    $step_number = $index + 1;
                    $args = array_map(static function ($arg) {
                        $arg_str = is_string($arg) ? $arg : json_encode(
                            $arg,
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                        );
                        return mb_strlen($arg_str, 'UTF-8') > 80 ? mb_substr(
                            $arg_str,
                            0,
                            80,
                            'UTF-8'
                        ) . '...' : $arg_str;
                    }, $trace['args'] ?? []);
                    $trace_info[] = sprintf(
                        'Шаг %d:' . PHP_EOL . '  Файл: %s' . PHP_EOL . '  Строка: %s' . PHP_EOL . '  Функция: %s' . PHP_EOL . '  Аргументы: %s',
                        $step_number,
                        $trace['file'] ?: 'неизвестный файл',
                        $trace['line'] ?: 'неизвестная строка',
                        $trace['function'] ?: 'неизвестная функция',
                        json_encode($args, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                    );
                }
                $write_txt .= 'Трассировка:' . PHP_EOL . implode(PHP_EOL . PHP_EOL, $trace_info) . PHP_EOL;
            }
        }

        // Безопасная запись в файл через потоки
        $handle = fopen($log_file, 'ab');
        if ($handle) {
            flock($handle, LOCK_EX);
            if (fwrite($handle, $write_txt) === false) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . __FUNCTION__ . ") | Не удалось записать данные в файл | Путь: $log_file"
                );
            }
            flock($handle, LOCK_UN);
            fclose($handle);
        } else {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . __FUNCTION__ . ") | Не удалось открыть файл для записи | Путь: $log_file"
            );
        }

        // Завершение работы скрипта при необходимости
        if ($die) {
            $safe_output = htmlspecialchars($write_txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            die($safe_output);
        }
    } catch (RuntimeException $e) {
        error_log(
            date('H:i:s') . ' [ERROR] | ' . (filter_input(
                INPUT_SERVER,
                'REMOTE_ADDR',
                FILTER_VALIDATE_IP
            ) ?: 'UNKNOWN_IP') . ' | ' . __FILE__ . ':' . __LINE__ . ' (' . __FUNCTION__ . ') | Ошибка во время выполнения | Сообщение: ' . $e->getMessage(
            )
        );
        $log_in_process = false;
        return false;
    } catch (Exception $e) {
        error_log(
            date('H:i:s') . ' [ERROR] | ' . (filter_input(
                INPUT_SERVER,
                'REMOTE_ADDR',
                FILTER_VALIDATE_IP
            ) ?: 'UNKNOWN_IP') . ' | ' . __FILE__ . ':' . __LINE__ . ' (' . __FUNCTION__ . ') | Непредвиденная ошибка | Сообщение: ' . $e->getMessage(
            )
        );
        $log_in_process = false;
        return false;
    }
    $log_in_process = false;
    return true;
}
