<?php

/**
 * @file        action/attach.php
 * @brief       Реализует безопасный вывод фото из галереи по идентификатору с проверкой прав доступа и ограничением
 *              путей.
 *
 * @details     Файл используется для вывода фото из галереи по идентификатору, переданному через параметр
 *              `$_GET['foto']`. Реализованы следующие особенности:
 *              - Проверка прав пользователя на просмотр изображений (`$user->user['pic_view']`).
 *              - Ограничение доступа к файлам только внутри директории галереи.
 *              - Проверка MIME-типа файла для предотвращения несанкционированного доступа.
 *              - Поддержка создания миниатюр при наличии параметра `$_GET['thumbnail']`.
 *              - Возврат данных об отсутствующем изображении, если файл недоступен.
 *
 * @section     Основные функции
 * - Получение данных о фотографии из базы данных.
 * - Проверка прав доступа и безопасности пути к файлу.
 * - Создание миниатюр изображений.
 * - Логирование ошибок и подозрительных действий.
 *
 * @throws      Exception Если не удалось определить путь к фото или файл недоступен.
 *              Пример сообщения: "Не удалось определить путь к фото | Путь: {$photo_data['full_path']}".
 * @throws      RuntimeException Если возникла ошибка при создании миниатюры.
 *              Пример сообщения: "Ошибка при создании миниатюры | Путь: {$photo_data['full_path']}".
 * @throws      RuntimeException Если пользователь не имеет прав на просмотр изображений.
 *              Пример сообщения: "Пользователь не имеет прав на просмотр изображений | ID: {$user->user['id']}".
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-03-12
 * @namespace   PhotoRigma\Action
 *
 * @see         PhotoRigma::Classes::Work Класс используется для выполнения различных операций.
 * @see         PhotoRigma::Classes::Database Класс для работы с базой данных.
 * @see         PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
 * @see         file index.php Этот файл подключает action/attach.php по запросу из `$_GET`.
 *
 * @note        Этот файл является частью системы PhotoRigma.
 *              Реализованы меры безопасности для предотвращения несанкционированного доступа к файлам.
 *              Используются подготовленные выражения для защиты от SQL-инъекций.
 *
 * @copyright   Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать, распространять,
 *              сублицензировать и/или продавать копии программного обеспечения, а также разрешать лицам, которым
 *              предоставляется данное программное обеспечение, делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые
 *              части программного обеспечения.
 */

namespace PhotoRigma\Action;

use Exception;
use PhotoRigma\Classes\Database;
use PhotoRigma\Classes\Template;
use PhotoRigma\Classes\User;
use PhotoRigma\Classes\Work;
use RuntimeException;

use function PhotoRigma\Include\log_in_file;

/** @var Database $db */
/** @var Work $work */
/** @var User $user */

/** @var Template $template */

// Предотвращение прямого вызова файла
if (!defined('IN_GALLERY') || IN_GALLERY !== true) {
    error_log(
        date('H:i:s') . " [ERROR] | " . (filter_input(
            INPUT_SERVER,
            'REMOTE_ADDR',
            FILTER_VALIDATE_IP
        ) ?: 'UNKNOWN_IP') . " | " . __FILE__ . " | Попытка прямого вызова файла"
    );
    die("HACK!");
}

// Проверка параметра 'foto'
if (!$user->user['pic_view'] || !$work->check_input('_GET', 'foto', [
        'isset'    => true,
        'empty'    => true,
        'regexp'   => '/^[0-9]+$/',
        'not_zero' => true,
    ])) {
    $photo_data = $work->no_photo();
} else {
    // Получение данных о фотографии и категории через JOIN
    $db->join(
        [TBL_PHOTO . '.`id`', TBL_PHOTO . '.`file`', TBL_PHOTO . '.`category`', TBL_CATEGORY . '.`folder`'],
        TBL_PHOTO,
        [
            ['type' => 'INNER', 'table' => TBL_CATEGORY, 'on' => TBL_PHOTO . '.`category` = ' . TBL_CATEGORY . '.`id`'],
        ],
        [
            'where'  => TBL_PHOTO . '.`id` = :id',
            'params' => [':id' => $_GET['foto']],
        ]
    );
    $query_result = $db->res_row();
    if ($query_result) {
        // Формирование путей к файлам
        $photo_data = [
            'id'             => $query_result['id'],
            'file'           => $query_result['file'],
            'category'       => $query_result['category'],
            'full_path'      => $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $query_result['folder'] . '/' . $query_result['file'],
            'thumbnail_path' => $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $query_result['folder'] . '/' . $query_result['file'],
        ];
    } else {
        $photo_data = $work->no_photo();
    }
}
// Проверка существования файла
if (empty($photo_data['full_path'])) {
    $photo_data = $work->no_photo();
} else {
    // Проверяем, что путь безопасен и файл существует
    $real_path = realpath($photo_data['full_path']);
    if ($real_path === false || !is_file($real_path) || !is_readable($real_path)) {
        log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл недоступен или не существует | Путь: {$photo_data['full_path']}"
        );
        $photo_data = $work->no_photo();
    } else {
        // Ограничиваем директорию папкой галереи
        $allowed_directory = realpath($work->config['site_dir'] . $work->config['gallery_folder']);
        if ($allowed_directory === false || !str_starts_with($real_path, $allowed_directory)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка доступа к запрещенному файлу | Путь: {$photo_data['full_path']}"
            );
            $photo_data = $work->no_photo();
        } else {
            // Проверка MIME-типа файла
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $real_mime_type = finfo_file($finfo, $real_path);
            finfo_close($finfo);
            if (!Work::validate_mime_type($real_mime_type)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Недопустимый MIME-тип файла | MIME: $real_mime_type"
                );
                $photo_data = $work->no_photo();
            }
        }
    }
}
// Проверяем, что $photo_data['full_path'] теперь существует и валиден
if (empty($photo_data['full_path'])) {
    throw new Exception(
        __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось определить путь к фото"
    );
}
// Дополнительная проверка через realpath
$final_real_path = realpath($photo_data['full_path']);
if ($final_real_path === false || !is_file($final_real_path) || !is_readable($final_real_path)) {
    throw new Exception(
        __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл недоступен или не существует | Путь: {$photo_data['full_path']}"
    );
}
// Обработка миниатюры
if ($work->check_input('_GET', 'thumbnail', [
        'isset'  => true,
        'empty'  => true,
        'regexp' => '/^[0-1]+$/',
    ]) && $_GET['thumbnail'] === '1') {
    if ($work->image_resize($photo_data['full_path'], $photo_data['thumbnail_path'])) {
        $work->image_attach($photo_data['thumbnail_path'], $photo_data['file']);
    } else {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при создании миниатюры | Путь: {$photo_data['full_path']}"
        );
    }
} else {
    $work->image_attach($photo_data['full_path'], $photo_data['file']);
}
