<?php

/**
 * @file        action/photo.php
 * @brief       Работа с фотографиями: вывод, редактирование, загрузка и обработка изображений, оценок.
 *
 * @details     Этот файл отвечает за управление фотографиями в системе, включая:
 *              - Отображение фотографий с информацией о них (название, описание, категория, автор, оценки).
 *              - Редактирование данных фотографии (название, описание, категория) для автора или модератора.
 *              - Удаление фотографий (для автора или модератора).
 *              - Загрузку новых фотографий (для авторизованных пользователей с правами на загрузку).
 *              - Оценку фотографий пользователями и модераторами.
 *              - Логирование ошибок и подозрительных действий.
 *
 * @section     Основные функции
 * - Отображение фотографий с детальной информацией.
 * - Редактирование данных фотографии.
 * - Удаление фотографий.
 * - Загрузка новых фотографий.
 * - Оценка фотографий пользователями и модераторами.
 * - Логирование ошибок и подозрительных действий.
 *
 * @throws      RuntimeException Если возникают ошибки при работе с базой данных или файловой системой.
 *              Пример сообщения: "Не удалось получить данные фотографии | ID: $photo_id".
 * @throws      RuntimeException Если пользователь не имеет прав на выполнение действия.
 *              Пример сообщения: "Пользователь не имеет прав на редактирование фотографии | ID: {$user->user['id']}".
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-04-07
 * @namespace   PhotoRigma\Action
 *
 * @see         PhotoRigma::Classes::Work Класс, содержащий основные методы для работы с данными.
 * @see         PhotoRigma::Classes::Database Класс для работы с базой данных.
 * @see         PhotoRigma::Classes::User Класс для управления пользователями.
 * @see         PhotoRigma::Classes::Template Класс для работы с шаблонами.
 * @see         PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
 *
 * @note        Этот файл является частью системы PhotoRigma.
 *              Реализованы меры безопасности для предотвращения несанкционированного доступа и выполнения действий.
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

// Проверка прав доступа пользователя и входных данных
if (!$user->user['pic_view'] || !$work->check_input(
    '_GET',
    'id',
    ['isset' => true, 'empty' => true, 'regexp' => '/^\d+$/']
)) {
    $photo_id = 0;
} else {
    $photo_id = $_GET['id'];
}

/** @var array<string, mixed> $photo */
$photo = [];

// Объединение данных из таблиц TBL_PHOTO и TBL_CATEGORY
$db->join(
    [
        TBL_PHOTO . '.`file`',
        TBL_PHOTO . '.`category`',
        TBL_CATEGORY . '.`folder`',
    ], // Список полей для выборки
    TBL_PHOTO, // Основная таблица
    [
        [
            'type'  => 'LEFT', // Тип JOIN
            'table' => TBL_CATEGORY, // Таблица для JOIN
            'on'    => TBL_PHOTO . '.`category` = ' . TBL_CATEGORY . '.`id`', // Условие JOIN
        ],
    ],
    [
        'where'  => TBL_PHOTO . '.`id` = :id', // Условие WHERE
        'params' => [':id' => $photo_id], // Параметры для prepared statements
    ]
);

$photo_data = $db->res_row();

if ($photo_data) {
    // Формирование пути к файлу с использованием sprintf()
    $file_path = sprintf(
        '%s%s/%s/%s',
        $work->config['site_dir'],
        $work->config['gallery_folder'],
        $photo_data['folder'],
        $photo_data['file']
    );

    // Проверка существования файла на диске
    if (!file_exists($file_path) || !is_readable($file_path)) {
        $photo_id = 0;
    }
} else {
    $photo_id = 0;
}

// Настройка шаблона для отображения фотографии
$template->template_file = 'photo.html';

// Добавление условий в шаблон
$template->add_if_ar([
    'EDIT_BLOCK' => false,
    'RATE_PHOTO' => false,
    'RATE_USER'  => false,
    'RATE_MODER' => false,
]);

if ($photo_id !== 0) {
    // Объединение данных из таблиц TBL_PHOTO, TBL_CATEGORY и TBL_USERS
    $db->join(
        [
            TBL_PHOTO . '.`id`',
            TBL_PHOTO . '.`file`',
            TBL_PHOTO . '.`name`',
            TBL_PHOTO . '.`description`',
            TBL_PHOTO . '.`category`',
            TBL_PHOTO . '.`date_upload`',
            TBL_PHOTO . '.`user_upload`',
            TBL_PHOTO . '.`rate_user`',
            TBL_PHOTO . '.`rate_moder`',
            TBL_CATEGORY . '.`folder`',
            TBL_CATEGORY . '.`name` AS `category_name`',
            TBL_CATEGORY . '.`description` AS `category_description`',
            TBL_USERS . '.`real_name`', // Добавляем поле real_name из таблицы TBL_USERS
        ], // Список полей для выборки
        TBL_PHOTO, // Основная таблица
        [
            [
                'type'  => 'LEFT', // Тип JOIN
                'table' => TBL_CATEGORY, // Таблица для JOIN
                'on'    => TBL_PHOTO . '.`category` = ' . TBL_CATEGORY . '.`id`', // Условие JOIN
            ],
            [
                'type'  => 'LEFT', // Тип JOIN
                'table' => TBL_USERS, // Таблица для JOIN
                'on'    => TBL_PHOTO . '.`user_upload` = ' . TBL_USERS . '.`id`', // Условие JOIN
            ],
        ],
        [
            'where'  => TBL_PHOTO . '.`id` = :id', // Условие WHERE
            'params' => [':id' => $photo_id], // Параметры для prepared statements
        ]
    );

    $photo_data = $db->res_row();

    if (!$photo_data) {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные фотографии | ID: $photo_id"
        );
    }

    if (($user->user['pic_rate_user'] || $user->user['pic_rate_moder']) && $photo_data['user_upload'] !== $user->user['id'] && $work->check_input(
        '_GET',
        'subact',
        [
                'isset' => true,
                'empty' => true,
            ]
    ) && $_GET['subact'] === "rate") {
        // Проверяем CSRF-токен
        if (empty($_POST['csrf_token']) || !hash_equals(
            $user->session['csrf_token'],
            $_POST['csrf_token']
        )) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
            );
        }
        $user->unset_property_key('session', 'csrf_token');

        // Проверка текущей оценки пользователя
        $db->select(
            '`rate`',
            TBL_RATE_USER,
            [
                'where'  => '`id_foto` = :id_foto AND `id_user` = :id_user',
                'params' => [':id_foto' => $photo_id, ':id_user' => $user->user['id']],
            ]
        );
        $temp = $db->res_row();
        $rate_user = $temp ? $temp['rate'] : false;

        if ($user->user['pic_rate_user'] && !$rate_user && $work->check_input('_POST', 'rate_user', [
                'isset'  => true,
                'empty'  => true,
                'regexp' => '/^-?[0-9]+$/',
            ]) && abs($_POST['rate_user']) <= $work->config['max_rate']) {
            // Вызов метода для обработки оценки пользователя
            $photo_data['rate_user'] = $work->process_rating(TBL_RATE_USER, $photo_id, $user->user['id'], $_POST['rate_user']);
        }

        // Проверка текущей оценки модератора
        $db->select(
            '`rate`',
            TBL_RATE_MODER,
            [
                'where'  => '`id_foto` = :id_foto AND `id_user` = :id_user',
                'params' => [':id_foto' => $photo_id, ':id_user' => $user->user['id']],
            ]
        );
        $temp = $db->res_row();
        $rate_moder = $temp ? $temp['rate'] : false;

        if ($user->user['pic_rate_moder'] && !$rate_moder && $work->check_input('_POST', 'rate_moder', [
                'isset'  => true,
                'empty'  => true,
                'regexp' => '/^-?[0-9]+$/',
            ]) && abs($_POST['rate_moder']) <= $work->config['max_rate']) {
            // Вызов метода для обработки оценки модератора
            $photo_data['rate_moder'] = $work->process_rating(TBL_RATE_MODER, $photo_id, $user->user['id'], $_POST['rate_moder']);
        }
    } elseif ((($photo_data['user_upload'] === $user->user['id'] && $user->user['id'] !== 0) || $user->user['pic_moderate']) && $work->check_input(
        '_GET',
        'subact',
        [
                'isset' => true,
                'empty' => true,
            ]
    ) && $_GET['subact'] === 'saveedit') {
        // Проверяем CSRF-токен
        if (empty($_POST['csrf_token']) || !hash_equals(
            $user->session['csrf_token'],
            $_POST['csrf_token']
        )) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
            );
        }
        $user->unset_property_key('session', 'csrf_token');

        // Обновление имени фотографии
        if (!$work->check_input('_POST', 'name_photo', [
            'isset' => true,
            'empty' => true,
        ])) {
            $photo['name'] = $photo_data['name'];
        } else {
            $photo['name'] = trim(Work::clean_field($_POST['name_photo']));
        }

        // Обновление описания фотографии
        if (!$work->check_input('_POST', 'description_photo', [
            'isset' => true,
            'empty' => true,
        ])) {
            $photo['description'] = $photo_data['description'];
        } else {
            $photo['description'] = trim(Work::clean_field($_POST['description_photo']));
        }

        // Проверка категории
        $category = true;
        if (!$work->check_input('_POST', 'name_category', [
            'isset'  => true,
            'empty'  => true,
            'regexp' => '/^[0-9]+$/',
        ])) {
            $category = false;
        } else {
            if ($user->user['cat_user'] || $user->user['pic_moderate']) {
                $select_cat = '`id` = :id';
            } else {
                $select_cat = '`id` != 0 AND `id` = :id';
            }
            $db->select(
                '*', // Список полей для выборки
                TBL_CATEGORY, // Имя таблицы
                [
                    'where'  => $select_cat, // Условие WHERE
                    'params' => [':id' => $_POST['name_category']], // Параметры для prepared statements
                ]
            );
            $temp_category_data = $db->res_row();
            if (!$temp_category_data) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные категории | ID: {$_POST['name_category']}"
                );
            }
        }

        // Если категория изменена
        if ($category && $photo_data['category'] !== $_POST['name_category']) {
            // Получаем папку новой категории
            $db->select(
                '`folder`', // Список полей для выборки
                TBL_CATEGORY, // Имя таблицы
                [
                    'where'  => '`id` = :id', // Условие WHERE
                    'params' => [':id' => $_POST['name_category']], // Параметры для prepared statements
                ]
            );
            $temp_new_category_data = $db->res_row();
            if (!$temp_new_category_data) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные новой категории | ID: {$_POST['name_category']}"
                );
            }

            // Формируем пути для перемещения файлов
            $old_photo_path = sprintf(
                "%s%s/%s/%s",
                $work->config['site_dir'],
                $work->config['gallery_folder'],
                $photo_data['folder'],
                $photo_data['file']
            );
            $old_thumbnail_path = sprintf(
                "%s%s/%s/%s",
                $work->config['site_dir'],
                $work->config['thumbnail_folder'],
                $photo_data['folder'],
                $photo_data['file']
            );
            $photo['file'] = $photo_data['file']; // Начальное имя файла

            // Применяем fix_file_extension к основному файлу
            $fixed_photo_path = $work->fix_file_extension($old_photo_path);
            if ($fixed_photo_path !== $old_photo_path) {
                // Обновляем имя файла
                $photo['file'] = basename($fixed_photo_path);
            }

            // Формируем новые пути с учетом возможного изменения имени файла
            $new_photo_path = sprintf(
                "%s%s/%s/%s",
                $work->config['site_dir'],
                $work->config['gallery_folder'],
                $temp_new_category_data['folder'],
                $photo['file']
            );
            $new_thumbnail_path = sprintf(
                "%s%s/%s/%s",
                $work->config['site_dir'],
                $work->config['thumbnail_folder'],
                $temp_new_category_data['folder'],
                $photo['file']
            );

            // Проверяем существование старого файла и права доступа
            if (!is_file($old_photo_path) || !is_writable($old_photo_path)) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Старый файл недоступен или нет прав на запись | Path: $old_photo_path"
                );
            }

            // Проверяем права на запись в папку нового файла
            $new_photo_dir = dirname($new_photo_path);
            if (!is_writable($new_photo_dir)) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Нет прав на запись в папку нового файла | Directory: $new_photo_dir"
                );
            }

            // Проверяем существование старой миниатюры и права доступа
            if (!is_file($old_thumbnail_path) || !is_writable($old_thumbnail_path)) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Старая миниатюра недоступна или нет прав на запись | Path: $old_thumbnail_path"
                );
            }

            // Проверяем права на запись в папку новой миниатюры
            $new_thumbnail_dir = dirname($new_thumbnail_path);
            if (!is_writable($new_thumbnail_dir)) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Нет прав на запись в папку новой миниатюры | Directory: $new_thumbnail_dir"
                );
            }

            // Перемещаем файлы
            if (!rename($old_photo_path, $new_photo_path)) {
                $photo['category_id'] = $photo_data['category'];
                $photo['path'] = $old_photo_path;
            } else {
                if (!rename($old_thumbnail_path, $new_thumbnail_path)) {
                    unlink($old_thumbnail_path);
                }
                $photo['category_id'] = $_POST['name_category'];
                $photo['path'] = $new_photo_path;
            }
        } else {
            $photo['category_id'] = $photo_data['category'];
        }

        // Обновляем данные в таблице фотографий
        $db->update(
            [
                '`name`'        => ':name',
                '`description`' => ':desc',
                '`category`'    => ':cat',
                '`file`'        => ':file',
            ],
            TBL_PHOTO, // Таблица
            [
                'where'  => '`id` = :id', // Условие WHERE
                'params' => [
                    ':id'   => $photo_id,
                    ':name' => $photo['name'],
                    ':desc' => $photo['description'],
                    ':cat'  => $photo['category_id'],
                    ':file' => $photo['file'] ?? $photo_data['file'],
                ], // Параметры для prepared statements
            ]
        );

        header(sprintf("Location: %s?action=photo&id=%d", $work->config['site_url'], $photo_id));
        exit;
    }

    if ((($photo_data['user_upload'] === $user->user['id'] && $user->user['id'] !== 0) || $user->user['pic_moderate']) && $work->check_input(
        '_GET',
        'subact',
        [
                'isset' => true,
                'empty' => true,
            ]
    ) && $_GET['subact'] === "edit") {
        $template->add_case('PHOTO_BLOCK', 'EDIT_PHOTO');

        // Получаем данные о пользователе из JOIN (уже есть в $photo_data)
        $photo['user'] = Work::clean_field($photo_data['real_name']) ?? $work->lang['main']['no_user_add'];
        $photo['path'] = sprintf(
            "%s%s/%s/%s",
            $work->config['site_dir'],
            $work->config['thumbnail_folder'],
            $photo_data['folder'],
            $photo_data['file']
        );
        // Условия выборки категорий
        $select_cat = ($user->user['cat_user'] || $user->user['pic_moderate']) ? [] : ['where' => '`id` != 0'];

        // Получаем список категорий
        $db->select('*', TBL_CATEGORY, $select_cat);
        $category_list = $db->res_arr();
        if (!$category_list) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить список категорий"
            );
        }

        // Формируем выпадающий список категорий
        foreach ($category_list as $key => $val) {
            if ($val['id'] === 0) {
                $val['name'] .= ' ' . $photo['user'];
            }
            if ($val['id'] === $photo_data['category']) {
                $template->add_string_ar([
                    'D_SELECTED_CATEGORY' => (string)$val['id'],
                    'L_SELECT_CATEGORY'   => Work::clean_field($val['name']),
                ]);
            }
            $template->add_string_ar([
                'D_ID_CATEGORY'   => (string)$val['id'],
                'D_NAME_CATEGORY' => $val['name'],
            ], 'SELECT_CATEGORY[' . $key . ']');
        }

        // Генерируем CSRF-токен для защиты от атак типа CSRF
        $template->add_string('CSRF_TOKEN', $user->csrf_token());
        // Добавляем данные в шаблон
        $template->add_string_ar([
            'L_NAME_BLOCK'        => $work->lang['photo']['edit'] . ' - ' . Work::clean_field($photo_data['name']),
            'L_NAME_PHOTO'        => $work->lang['main']['name_of'] . ' ' . $work->lang['photo']['of_photo'],
            'L_DESCRIPTION_PHOTO' => $work->lang['main']['description_of'] . ' ' . $work->lang['photo']['of_photo'],
            'L_NAME_CATEGORY'     => $work->lang['main']['name_of'] . ' ' . $work->lang['category']['of_category'],
            'L_NAME_FILE'         => $work->lang['photo']['filename'],
            'L_EDIT_THIS'         => $work->lang['photo']['save'],
            'D_NAME_FILE'         => $photo_data['file'],
            'D_NAME_PHOTO'        => $photo_data['name'],
            'D_DESCRIPTION_PHOTO' => $photo_data['description'],
            'U_EDITED'            => sprintf(
                '%s?action=photo&amp;subact=saveedit&amp;id=%d',
                $work->config['site_url'],
                $photo_data['id']
            ),
            'U_PHOTO'             => sprintf(
                '%s?action=attach&amp;foto=%d&amp;thumbnail=1',
                $work->config['site_url'],
                $photo_data['id']
            ),
        ]);
    } elseif ((($photo_data['user_upload'] === $user->user['id'] && $user->user['id'] !== 0) || $user->user['pic_moderate']) && $work->check_input(
        '_GET',
        'subact',
        [
                'isset' => true,
                'empty' => true,
            ]
    ) && $_GET['subact'] === "delete") {
        if ($photo_data['category'] === 0) {
            // Запрос для категории 0
            $db->select(
                'SUM(CASE WHEN `category` = 0 AND `user_upload` = :user_upload THEN 1 ELSE 0 END) AS `count_user_category`, SUM(CASE WHEN `category` = 0 THEN 1 ELSE 0 END) AS `count_category`, COUNT(*) AS `count_total`',
                TBL_PHOTO,
                [
                    'where'  => '`id` != :photo_id',
                    'params' => [
                        ':user_upload' => $photo_data['user_upload'],
                        ':photo_id'    => $photo_id,
                    ],
                ]
            );

            $result = $db->res_row();
            if ($result === false) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные о количестве фотографий"
                );
            }

            // Формирование ссылки через match и sprintf
            $photo['category_url'] = match (true) {
                $result['count_user_category'] > 0 => sprintf(
                    '%s?action=category&cat=user&id=%d',
                    $work->config['site_url'],
                    $photo_data['user_upload']
                ),
                $result['count_category'] > 0      => sprintf('%s?action=category&cat=user', $work->config['site_url']),
                $result['count_total'] > 0         => sprintf('%s?action=category', $work->config['site_url']),
                default                            => $work->config['site_url']
            };
        } else {
            // Запрос для обычной категории
            $db->select(
                'SUM(CASE WHEN `category` = :category THEN 1 ELSE 0 END) AS `count_category`, COUNT(*) AS `count_total`',
                TBL_PHOTO,
                [
                    'where'  => '`id` != :photo_id',
                    'params' => [
                        ':category' => $photo_data['category'],
                        ':photo_id' => $photo_id,
                    ],
                ]
            );

            $result = $db->res_row();
            if ($result === false) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные о количестве фотографий"
                );
            }

            // Формирование ссылки через match и sprintf
            $photo['category_url'] = match (true) {
                $result['count_category'] > 0 => sprintf(
                    '%s?action=category&cat=%d',
                    $work->config['site_url'],
                    $photo_data['category']
                ),
                $result['count_total'] > 0    => sprintf('%s?action=category', $work->config['site_url']),
                default                       => $work->config['site_url']
            };
        }

        // Удаление фотографии
        if ($work->del_photo($photo_id)) {
            header('Location: ' . $photo['category_url']);
            exit;
        }

        header('Location: ' . sprintf('%s?action=photo&id=%d', $work->config['site_url'], $photo_id));
        exit;
    } else {
        // Добавляем блок PHOTO_BLOCK в шаблон
        $template->add_case('PHOTO_BLOCK', 'VIEW_PHOTO');

        // Формируем путь к фотографии
        $photo['path'] = sprintf(
            '%s%s/%s/%s',
            $work->config['site_dir'],
            $work->config['gallery_folder'],
            $photo_data['folder'],
            $photo_data['file']
        );

        // Добавляем основные данные в шаблон
        $template->add_string_ar([
            'L_NAME_BLOCK'           => $work->lang['photo']['title'] . ' - ' . Work::clean_field($photo_data['name']),
            'L_DESCRIPTION_BLOCK'    => Work::clean_field($photo_data['description']),
            'L_USER_ADD'             => $work->lang['main']['user_add'],
            'L_NAME_CATEGORY'        => $work->lang['main']['name_of'] . ' ' . $work->lang['category']['of_category'],
            'L_DESCRIPTION_CATEGORY' => $work->lang['main']['description_of'] . ' ' . $work->lang['category']['of_category'],
            'D_NAME_PHOTO'           => Work::clean_field($photo_data['name']),
            'D_DESCRIPTION_PHOTO'    => Work::clean_field($photo_data['description']),
            'U_PHOTO'                => sprintf(
                '%s?action=attach&foto=%d',
                $work->config['site_url'],
                $photo_data['id']
            ),
        ]);

        // Если пользователь имеет права на редактирование/удаление
        if (($photo_data['user_upload'] === $user->user['id'] && $user->user['id'] !== 0) || $user->user['pic_moderate']) {
            $template->add_string_ar([
                'L_EDIT_BLOCK'           => $work->lang['photo']['edit'],
                'L_CONFIRM_DELETE_BLOCK' => $work->lang['photo']['confirm_delete'] . ' ' . Work::clean_field(
                    $photo_data['name']
                ),
                'L_DELETE_BLOCK'         => $work->lang['photo']['delete'],
                'L_CONFIRM_DELETE'       => $work->lang['main']['delete'],
                'L_CANCEL_DELETE'        => $work->lang['main']['cancel'],
                'U_EDIT_BLOCK'           => sprintf(
                    '%s?action=photo&subact=edit&id=%d',
                    $work->config['site_url'],
                    $photo_data['id']
                ),
                'U_DELETE_BLOCK'         => sprintf(
                    '%s?action=photo&subact=delete&id=%d',
                    $work->config['site_url'],
                    $photo_data['id']
                ),
            ]);
            $template->add_if('EDIT_BLOCK', true);
        }

        // Добавляем данные о пользователе
        $template->add_string_ar([
            'D_USER_ADD' => $photo_data['real_name'] ?? $work->lang['main']['no_user_add'],
            'U_USER_ADD' => $photo_data['real_name'] ? sprintf(
                '%s?action=profile&subact=profile&uid=%d',
                $work->config['site_url'],
                $photo_data['user_upload']
            ) : '',
        ]);

        // Добавляем данные о категории
        if ($photo_data['category'] === 0) {
            $template->add_string_ar([
                'D_NAME_CATEGORY'        => $photo_data['category_name'] . ' ' . ($photo_data['real_name'] ?? ''),
                'D_DESCRIPTION_CATEGORY' => $photo_data['category_description'] . ' ' . ($photo_data['real_name'] ?? ''),
                'U_CATEGORY'             => sprintf(
                    '%s?action=category&cat=user&id=%d',
                    $work->config['site_url'],
                    $photo_data['user_upload']
                ),
            ]);
        } else {
            $template->add_string_ar([
                'D_NAME_CATEGORY'        => $photo_data['category_name'],
                'D_DESCRIPTION_CATEGORY' => $photo_data['category_description'],
                'U_CATEGORY'             => sprintf(
                    '%s?action=category&cat=%d',
                    $work->config['site_url'],
                    $photo_data['category']
                ),
            ]);
        }

        // Добавляем данные об оценках
        $template->add_string_ar([
            'L_RATE_USER'  => $work->lang['photo']['rate_user'] . ': ' . $photo_data['rate_user'],
            'L_RATE_MODER' => $work->lang['photo']['rate_moder'] . ': ' . $photo_data['rate_moder'],
            'D_RATE_USER'  => (string)$photo_data['rate_user'],
            'D_RATE_MODER' => (string)$photo_data['rate_moder'],
            'D_MAX_RATE'   => (string)$work->config['max_rate'],
        ]);

        // Проверка возможности оценки пользователем
        if ($user->user['pic_rate_user']) {
            $db->select('`rate`', TBL_RATE_USER, [
                'where'  => '`id_foto` = :id_foto AND `id_user` = :id_user',
                'params' => [':id_foto' => $photo_id, ':id_user' => $user->user['id']],
            ]);
            $user_rate = $db->res_row() === false;

            if ($user_rate) {
                $template->add_if('RATE_USER', true);
                $key = 0;
                for ($i = -$work->config['max_rate']; $i <= $work->config['max_rate']; $i++) {
                    $template->add_string('D_SELECTED_LVL_USER', '0');
                    $template->add_string_ar([
                        'D_LVL_RATE' => (string)$i,
                    ], 'SELECT_USER_RATE[' . $key++ . ']');
                }
            }
        } else {
            $user_rate = false;
        }

        // Проверка возможности оценки модератором
        if ($user->user['pic_rate_moder']) {
            $db->select('`rate`', TBL_RATE_MODER, [
                'where'  => '`id_foto` = :id_foto AND `id_user` = :id_user',
                'params' => [':id_foto' => $photo_id, ':id_user' => $user->user['id']],
            ]);
            $moder_rate = $db->res_row() === false;

            if ($moder_rate) {
                $template->add_if('RATE_MODER', true);
                $key = 0;
                for ($i = -$work->config['max_rate']; $i <= $work->config['max_rate']; $i++) {
                    $template->add_string('D_SELECTED_LVL_MODER', '0');
                    $template->add_string_ar([
                        'D_LVL_RATE' => (string)$i,
                    ], 'SELECT_MODER_RATE[' . $key++ . ']');
                }
            }
        } else {
            $moder_rate = false;
        }

        // Если пользователь может оценить фотографию.
        // Проверяем права пользователя и наличие возможности оценки.
        $can_rate = ($user->user['pic_rate_user'] || $user->user['pic_rate_moder']) && $photo_data['user_upload'] !== $user->user['id'];
        if ($can_rate && ($user_rate || $moder_rate)) {
            $template->add_if('RATE_PHOTO', true);
            // Генерируем CSRF-токен для защиты от атак типа CSRF
            $template->add_string('CSRF_TOKEN', $user->csrf_token());

            $template->add_string_ar([
                'L_RATE'       => $work->lang['photo']['rate_you'],
                'L_USER_RATE'  => $work->lang['photo']['if_user'],
                'L_MODER_RATE' => $work->lang['photo']['if_moder'],
                'L_RATE_THIS'  => $work->lang['photo']['rate'],
                'U_RATE'       => sprintf(
                    '%s?action=photo&subact=rate&id=%d',
                    $work->config['site_url'],
                    $photo_data['id']
                ),
            ]);
        }
    }
} elseif ($work->check_input(
    '_GET',
    'subact',
    [
            'isset' => true,
            'empty' => true,
        ]
) && $_GET['subact'] === "upload" && $user->user['id'] !== 0 && $user->user['pic_upload']) {
    // Добавляем блок PHOTO_BLOCK в шаблон
    $template->add_case('PHOTO_BLOCK', 'UPLOAD_PHOTO');

    // Определяем максимальный размер файла для загрузки
    $max_size_php = Work::return_bytes(ini_get('post_max_size'));
    $max_size = Work::return_bytes($work->config['max_file_size']);
    $max_size = min($max_size, $max_size_php);

    // Формируем условия выборки категорий
    $select_cat = $user->user['cat_user'] ? [] : ['where' => '`id` != :id', 'params' => [':id' => 0]];

    // Получаем список категорий
    $db->select('*', TBL_CATEGORY, $select_cat);
    $category_data = $db->res_arr();
    if (!$category_data) {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить список категорий"
        );
    }

    // Формируем выпадающий список категорий
    foreach ($category_data as $key => $val) {
        if ($val['id'] === 0) {
            $val['name'] .= ' ' . $user->user['real_name'];
            $template->add_string_ar([
                'D_SELECTED_CATEGORY' => (string)$val['id'],
                'L_SELECT_CATEGORY'   => Work::clean_field($val['name']),
            ]);
        }
        $template->add_string_ar([
            'D_ID_CATEGORY'   => (string)$val['id'],
            'D_NAME_CATEGORY' => Work::clean_field($val['name']),
        ], 'UPLOAD_CATEGORY[' . $key . ']');
    }

    // Генерируем CSRF-токен для защиты от атак типа CSRF
    $template->add_string('CSRF_TOKEN', $user->csrf_token());
    // Добавляем данные в шаблон
    $template->add_string_ar([
        'L_NAME_BLOCK'        => $work->lang['photo']['title'] . ' - ' . $work->lang['photo']['upload'],
        'L_NAME_PHOTO'        => $work->lang['main']['name_of'] . ' ' . $work->lang['photo']['of_photo'],
        'L_DESCRIPTION_PHOTO' => $work->lang['main']['description_of'] . ' ' . $work->lang['photo']['of_photo'],
        'L_NAME_CATEGORY'     => $work->lang['main']['name_of'] . ' ' . $work->lang['category']['of_category'],
        'L_UPLOAD_THIS'       => $work->lang['photo']['upload'],
        'L_FILE_PHOTO'        => $work->lang['photo']['select_file'],
        'D_MAX_FILE_SIZE'     => (string)$max_size,
        'U_UPLOADED'          => sprintf('%s?action=photo&subact=uploaded', $work->config['site_url']),
    ]);
} elseif ($user->user['id'] !== 0 && $user->user['pic_upload'] && $work->check_input(
    '_GET',
    'subact',
    [
            'isset' => true,
            'empty' => true,
        ]
) && $_GET['subact'] === "uploaded") {
    // Проверяем CSRF-токен
    if (empty($_POST['csrf_token']) || !hash_equals(
        $user->session['csrf_token'],
        $_POST['csrf_token']
    )) {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
        );
    }
    $user->unset_property_key('session', 'csrf_token');

    // Флаг успешной загрузки (bool)
    $submit_upload = true;

    // Определяем максимальный размер файла для загрузки (int)
    $max_size_php = Work::return_bytes(ini_get('post_max_size'));
    $max_size = Work::return_bytes($work->config['max_file_size']);
    $max_size = min($max_size, $max_size_php);

    // Проверяем название фотографии
    if (!$work->check_input('_POST', 'name_photo', ['isset' => true, 'empty' => true])) {
        $photo['name'] = false; // Если название не указано, оставляем флаг false
    } else {
        $photo['name'] = Work::clean_field($_POST['name_photo']); // Очищаем название
    }

    // Проверяем описание фотографии
    if (!$work->check_input('_POST', 'description_photo', ['isset' => true, 'empty' => true])) {
        $photo['description'] = false; // Если описание не указано, оставляем флаг false
    } else {
        $photo['description'] = Work::clean_field($_POST['description_photo']); // Очищаем описание
    }

    // Проверяем категорию
    if (!$work->check_input('_POST', 'name_category', ['isset' => true, 'regexp' => '/^[0-9]+$/'])) {
        $submit_upload = false; // Если категория не указана или некорректна, отменяем загрузку
    } else {
        // Формируем условие для выборки категории
        $category_condition = $user->user['cat_user'] || $user->user['pic_moderate'] ? [
            'where'  => '`id` = :id',
            'params' => [':id' => $_POST['name_category']],
        ] : ['where' => '`id` != 0 AND `id` = :id', 'params' => [':id' => $_POST['name_category']]];

        // Выполняем запрос к базе данных для проверки существования категории
        $db->select('`id`, `folder`', TBL_CATEGORY, $category_condition); // Ограничиваем выборку только нужными полями
        $category_data = $db->res_row();
        if (!$category_data) {
            $submit_upload = false; // Если категория не найдена, отменяем загрузку
        }
    }

    // Если все проверки пройдены, обрабатываем загрузку файла
    if ($submit_upload) {
        $photo['category_id'] = $_POST['name_category']; // Сохраняем ID категории

        // Проверяем корректность загруженного файла через $work->check_input
        if ($work->check_input('_FILES', 'file_photo', [
            'isset'    => true,
            'max_size' => $max_size,
        ])) {
            // Разделяем имя файла на базовое имя и расширение
            $file_info = pathinfo($_FILES['file_photo']['name']);
            $file_base_name = $file_info['filename']; // Имя файла без расширения
            $file_extension = $file_info['extension']; // Расширение файла

            // Формируем новое имя файла
            $file_name = time() . '_' . Work::encodename($file_base_name) . '.' . $file_extension;

            // Формируем пути для сохранения файла и миниатюры
            $photo['path'] = sprintf(
                '%s%s/%s/%s',
                $work->config['site_dir'],
                $work->config['gallery_folder'],
                $category_data['folder'],
                $file_name
            );
            $photo['thumbnail_path'] = sprintf(
                '%s%s/%s/%s',
                $work->config['site_dir'],
                $work->config['thumbnail_folder'],
                $category_data['folder'],
                $file_name
            );

            // Нормализуем пути с помощью realpath
            $photo['path'] = realpath($photo['path']) ?: $photo['path'];
            $photo['thumbnail_path'] = realpath($photo['thumbnail_path']) ?: $photo['thumbnail_path'];

            // Проверяем права доступа к директориям
            $gallery_dir = dirname($photo['path']);
            $thumbnail_dir = dirname($photo['thumbnail_path']);
            if (!is_writable($gallery_dir) || !is_writable($thumbnail_dir)) {
                log_in_file(
                    "Ошибка прав доступа: директории недоступны для записи | Пользователь: {$user->user['id']} | Файл: {$_FILES['file_photo']['name']}"
                );
                $submit_upload = false;
            }

            // Перемещаем файл и создаем миниатюру
            if ($submit_upload && move_uploaded_file($_FILES['file_photo']['tmp_name'], $photo['path'])) {
                $work->image_resize($photo['path'], $photo['thumbnail_path']);
            } else {
                log_in_file(
                    "Ошибка перемещения загруженного файла: {$_FILES['file_photo']['name']} | Пользователь: {$user->user['id']}"
                );
                $submit_upload = false;
            }
        } else {
            $submit_upload = false; // Если файл не прошел проверку, отменяем загрузку
        }
    }

    // Если загрузка успешна, сохраняем данные в базу данных
    if ($submit_upload) {
        // Проверяем правильность расширения
        $old_file_path = $photo['path'];
        $photo['path'] = $work->fix_file_extension($photo['path']);
        if ($photo['path'] !== $old_file_path) {
            rename($old_file_path, $photo['path']);
            $file_info = pathinfo($photo['path']);
            $file_name = $file_info['filename'] . '.' . $file_info['extension'];
        }

        // Устанавливаем значения по умолчанию для названия и описания
        $photo['name'] = $photo['name'] ?: ($work->lang['photo']['no_name'] . ' (' . $file_name . ')');
        $photo['description'] = $photo['description'] ?: ($work->lang['photo']['no_description'] . ' (' . $file_name . ')');

        $query = [
            '`file`'        => ':file',
            '`name`'        => ':name',
            '`description`' => ':desc',
            '`category`'    => ':cat',
            '`date_upload`' => ':date',
            '`user_upload`' => ':user',
            '`rate_user`'   => ':r_user',
            '`rate_moder`'  => ':r_moder',
        ];
        $params = [
            ':file'    => $file_name,
            ':name'    => $photo['name'],
            ':desc'    => $photo['description'],
            ':cat'     => $photo['category_id'],
            ':date'    => date('Y-m-d H:i:s'),
            ':user'    => $user->user['id'],
            ':r_user'  => '0',
            ':r_moder' => '0',
        ];

        // Вставка данных в базу данных
        $db->insert($query, TBL_PHOTO, 'ignore', ['params' => $params]);
        $photo_id = $db->get_last_insert_id();

        if ($photo_id > 0) {
            // Если ID фотографии успешно получен, формируем URL для редиректа
            $redirect_url = sprintf('%s?action=photo&id=%d', $work->config['site_url'], $photo_id);
        } else {
            // Удаляем файлы, если сохранение в БД не удалось
            if (is_file($photo['path']) && is_writable($photo['path'])) {
                unlink($photo['path']);
            }
            if (is_file($photo['thumbnail_path']) && is_writable($photo['thumbnail_path'])) {
                unlink($photo['thumbnail_path']);
            }
            // Формируем URL для редиректа на форму загрузки
            $redirect_url = sprintf('%s?action=photo&subact=upload', $work->config['site_url']);
        }
    } else {
        // Редирект на форму загрузки в случае ошибки
        $redirect_url = sprintf('%s?action=photo&subact=upload', $work->config['site_url']);
    }

    // Выполняем редирект
    header('Location: ' . $redirect_url);
    exit;
} else {
    $template->add_case('PHOTO_BLOCK', 'VIEW_PHOTO');
    $photo_data['file'] = 'no_foto.png';

    $template->add_string_ar([
        'L_NAME_BLOCK'           => $work->lang['photo']['title'] . ' - ' . $work->lang['main']['no_foto'],
        'L_DESCRIPTION_BLOCK'    => $work->lang['main']['no_foto'],
        'L_USER_ADD'             => $work->lang['main']['user_add'],
        'L_NAME_CATEGORY'        => $work->lang['main']['name_of'] . ' ' . $work->lang['category']['of_category'],
        'L_DESCRIPTION_CATEGORY' => $work->lang['main']['description_of'] . ' ' . $work->lang['category']['of_category'],
        'D_NAME_PHOTO'           => $work->lang['main']['no_foto'],
        'D_DESCRIPTION_PHOTO'    => $work->lang['main']['no_foto'],
        'D_USER_ADD'             => $work->lang['main']['no_user_add'],
        'D_NAME_CATEGORY'        => $work->lang['main']['no_category'],
        'D_DESCRIPTION_CATEGORY' => $work->lang['main']['no_category'],
        'D_RATE_USER'            => $work->lang['photo']['rate_user'] . ': ' . $work->lang['main']['no_foto'],
        'D_RATE_MODER'           => $work->lang['photo']['rate_moder'] . ': ' . $work->lang['main']['no_foto'],
        'U_CATEGORY'             => $work->config['site_url'],
        'U_USER_ADD'             => '',
        'U_PHOTO'                => sprintf('%s?action=attach&amp;foto=0', $work->config['site_url']),
    ]);

    $photo['path'] = sprintf(
        '%s%s/%s',
        $work->config['site_dir'],
        $work->config['gallery_folder'],
        $photo_data['file']
    );
}

if (!empty($photo['path'])) {
    $size_photo = $work->size_image($photo['path']);
    $template->add_string_ar([
        'D_FOTO_WIDTH'  => (string)$size_photo['width'],
        'D_FOTO_HEIGHT' => (string)$size_photo['height'],
    ]);
}

if ($user->user['id'] !== 0 && $user->user['pic_upload'] && $work->check_input(
    '_GET',
    'subact',
    [
            'isset' => true,
            'empty' => true,
        ]
) && $_GET['subact'] === "uploaded") {
    $redirect_url = $redirect_url ?? $work->config['site_url'];
    header('Location: ' . $redirect_url);
    exit;
}
