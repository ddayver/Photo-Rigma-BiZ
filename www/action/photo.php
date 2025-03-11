<?php

/**
 * @file        action/photo.php
 * @brief       Работа с фото.
 * @author      Dark Dayver
 * @version     0.2.0
 * @date        28/03-2012
 * @details     Вывод, редактирование, загрузка и обработка изображений, оценок.
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
        TBL_CATEGORY . '.`folder`'
    ], // Список полей для выборки
    TBL_PHOTO, // Основная таблица
    [
        [
            'type' => 'LEFT', // Тип JOIN
            'table' => TBL_CATEGORY, // Таблица для JOIN
            'on' => TBL_PHOTO . '.`category` = ' . TBL_CATEGORY . '.`id`' // Условие JOIN
        ]
    ],
    [
        'where' => TBL_PHOTO . '.`id` = :id', // Условие WHERE
        'params' => [':id' => $photo_id] // Параметры для prepared statements
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

/** @var int $max_photo_w */
$max_photo_w = $work->config['max_photo_w'];

/** @var int $max_photo_h */
$max_photo_h = $work->config['max_photo_h'];

// Добавление условий в шаблон
$template->add_if_ar([
    'EDIT_BLOCK' => false,
    'RATE_PHOTO' => false,
    'RATE_USER' => false,
    'RATE_MODER' => false
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
            TBL_USERS . '.`real_name`' // Добавляем поле real_name из таблицы TBL_USERS
        ], // Список полей для выборки
        TBL_PHOTO, // Основная таблица
        [
            [
                'type' => 'LEFT', // Тип JOIN
                'table' => TBL_CATEGORY, // Таблица для JOIN
                'on' => TBL_PHOTO . '.`category` = ' . TBL_CATEGORY . '.`id`' // Условие JOIN
            ],
            [
                'type' => 'LEFT', // Тип JOIN
                'table' => TBL_USERS, // Таблица для JOIN
                'on' => TBL_PHOTO . '.`user_upload` = ' . TBL_USERS . '.`id`' // Условие JOIN
            ]
        ],
        [
            'where' => TBL_PHOTO . '.`id` = :id', // Условие WHERE
            'params' => [':id' => $photo_id] // Параметры для prepared statements
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
        // Проверка текущей оценки пользователя
        $db->select(
            '`rate`',
            TBL_RATE_USER,
            [
                'where' => '`id_foto` = :id_foto AND `id_user` = :id_user',
                'params' => [':id_foto' => $photo_id, ':id_user' => $user->user['id']]
            ]
        );
        $temp = $db->res_row();
        $rate_user = $temp ? $temp['rate'] : false;

        if ($user->user['pic_rate_user'] && !$rate_user && $work->check_input('_POST', 'rate_user', [
                'isset' => true,
                'empty' => true,
                'regexp' => '/^-?[0-9]+$/',
            ]) && abs($_POST['rate_user']) <= $work->config['max_rate']) {
            // Вызов метода для обработки оценки пользователя
            $rate_user = $work->process_rating(TBL_RATE_USER, $photo_id, $user->user['id'], $_POST['rate_user']);

            // Обновление средней оценки в таблице фотографий
            $db->update(
                ['`rate_user`' => ':rate_user'],
                TBL_PHOTO,
                [
                    'where' => '`id` = :id',
                    'params' => [':id' => $photo_id, ':rate_user' => $rate_user]
                ]
            );

            $affected_rows = $db->get_affected_rows();
            if ($affected_rows === 0) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось обновить оценку пользователя в таблице фотографий | ID фотографии: $photo_id"
                );
            }
        }

        // Проверка текущей оценки модератора
        $db->select(
            '`rate`',
            TBL_RATE_MODER,
            [
                'where' => '`id_foto` = :id_foto AND `id_user` = :id_user',
                'params' => [':id_foto' => $photo_id, ':id_user' => $user->user['id']]
            ]
        );
        $temp = $db->res_row();
        $rate_moder = $temp ? $temp['rate'] : false;

        if ($user->user['pic_rate_moder'] && !$rate_moder && $work->check_input('_POST', 'rate_moder', [
                'isset' => true,
                'empty' => true,
                'regexp' => '/^-?[0-9]+$/',
            ]) && abs($_POST['rate_moder']) <= $work->config['max_rate']) {
            // Вызов метода для обработки оценки модератора
            $rate_moder = $work->process_rating(TBL_RATE_MODER, $photo_id, $user->user['id'], $_POST['rate_moder']);

            // Обновление средней оценки в таблице фотографий
            $db->update(
                ['`rate_moder`' => ':rate_moder'],
                TBL_PHOTO,
                [
                    'where' => '`id` = :id',
                    'params' => [':id' => $photo_id, ':rate_moder' => $rate_moder]
                ]
            );

            $affected_rows = $db->get_affected_rows();
            if ($affected_rows === 0) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось обновить оценку модератора в таблице фотографий | ID фотографии: $photo_id"
                );
            }
        }
    } elseif ((($photo_data['user_upload'] === $user->user['id'] && $user->user['id'] !== 0) || $user->user['pic_moderate']) && $work->check_input(
        '_GET',
        'subact',
        [
                'isset' => true,
                'empty' => true,
            ]
    ) && $_GET['subact'] === 'saveedit') {
        // Используем данные, полученные из JOIN-запроса

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
            'isset' => true,
            'empty' => true,
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
                    'where' => $select_cat, // Условие WHERE
                    'params' => [':id' => $_POST['name_category']] // Параметры для prepared statements
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
                'folder', // Список полей для выборки
                TBL_CATEGORY, // Имя таблицы
                [
                    'where' => '`id` = :id', // Условие WHERE
                    'params' => [':id' => $_POST['name_category']] // Параметры для prepared statements
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
                'name' => ':name',
                'description' => ':desc',
                'category' => ':cat',
                'file' => ':file',
            ],
            TBL_PHOTO, // Таблица
            [
                'where' => '`id` = :id', // Условие WHERE
                'params' => [
                    ':id' => $photo_id,
                    ':name' => $photo['name'],
                    ':desc' => $photo['description'],
                    ':cat' => $photo['category_id'],
                    ':file' => $photo['file']
                ] // Параметры для prepared statements
            ]
        );

        // Проверяем количество изменённых строк
        $affected_rows = $db->get_affected_rows();
        if ($affected_rows === 0) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось обновить данные фотографии в базе данных | ID: $photo_id"
            );
        }
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
            $selected = ($val['id'] === $photo_data['category']) ? ' selected="selected"' : '';
            if ($val['id'] === 0) {
                $val['name'] .= ' ' . $photo['user'];
            }
            $template->add_string_ar([
                'D_ID_CATEGORY' => $val['id'],
                'D_NAME_CATEGORY' => $val['name'],
                'D_SELECTED' => $selected
            ], 'SELECT_CATEGORY[' . $key . ']');
        }

        // Добавляем данные в шаблон
        $template->add_string_ar([
            'L_NAME_BLOCK' => $work->lang['photo']['edit'] . ' - ' . Work::clean_field($photo_data['name']),
            'L_NAME_PHOTO' => $work->lang['main']['name_of'] . ' ' . $work->lang['photo']['of_photo'],
            'L_DESCRIPTION_PHOTO' => $work->lang['main']['description_of'] . ' ' . $work->lang['photo']['of_photo'],
            'L_NAME_CATEGORY' => $work->lang['main']['name_of'] . ' ' . $work->lang['category']['of_category'],
            'L_NAME_FILE' => $work->lang['photo']['filename'],
            'L_EDIT_THIS' => $work->lang['photo']['save'],
            'D_NAME_FILE' => $photo_data['file'],
            'D_NAME_PHOTO' => $photo_data['name'],
            'D_DESCRIPTION_PHOTO' => $photo_data['description'],
            'U_EDITED' => sprintf(
                '%s?action=photo&amp;subact=saveedit&amp;id=%d',
                $work->config['site_url'],
                $photo_data['id']
            ),
            'U_PHOTO' => sprintf(
                '%s?action=attach&amp;foto=%d&amp;thumbnail=1',
                $work->config['site_url'],
                $photo_data['id']
            )
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
                    'where' => '`id` != :photo_id',
                    'params' => [
                        ':user_upload' => $photo_data['user_upload'],
                        ':photo_id' => $photo_id
                    ]
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
                $result['count_category'] > 0 => sprintf('%s?action=category&cat=user', $work->config['site_url']),
                $result['count_total'] > 0 => sprintf('%s?action=category', $work->config['site_url']),
                default => $work->config['site_url']
            };
        } else {
            // Запрос для обычной категории
            $db->select(
                'SUM(CASE WHEN `category` = :category THEN 1 ELSE 0 END) AS `count_category`, COUNT(*) AS `count_total`',
                TBL_PHOTO,
                [
                    'where' => '`id` != :photo_id',
                    'params' => [
                        ':category' => $photo_data['category'],
                        ':photo_id' => $photo_id
                    ]
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
                $result['count_total'] > 0 => sprintf('%s?action=category', $work->config['site_url']),
                default => $work->config['site_url']
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
            'L_NAME_BLOCK' => $work->lang['photo']['title'] . ' - ' . Work::clean_field($photo_data['name']),
            'L_DESCRIPTION_BLOCK' => Work::clean_field($photo_data['description']),
            'L_USER_ADD' => $work->lang['main']['user_add'],
            'L_NAME_CATEGORY' => $work->lang['main']['name_of'] . ' ' . $work->lang['category']['of_category'],
            'L_DESCRIPTION_CATEGORY' => $work->lang['main']['description_of'] . ' ' . $work->lang['category']['of_category'],
            'D_NAME_PHOTO' => Work::clean_field($photo_data['name']),
            'D_DESCRIPTION_PHOTO' => Work::clean_field($photo_data['description']),
            'U_PHOTO' => sprintf('%s?action=attach&foto=%d', $work->config['site_url'], $photo_data['id'])
        ]);

        // Если пользователь имеет права на редактирование/удаление
        if (($photo_data['user_upload'] === $user->user['id'] && $user->user['id'] !== 0) || $user->user['pic_moderate']) {
            $template->add_string_ar([
                'L_EDIT_BLOCK' => $work->lang['photo']['edit'],
                'L_CONFIRM_DELETE_BLOCK' => $work->lang['photo']['confirm_delete'] . ' ' . Work::clean_field(
                    $photo_data['name']
                ),
                'L_DELETE_BLOCK' => $work->lang['photo']['delete'],
                'U_EDIT_BLOCK' => sprintf(
                    '%s?action=photo&subact=edit&id=%d',
                    $work->config['site_url'],
                    $photo_data['id']
                ),
                'U_DELETE_BLOCK' => sprintf(
                    '%s?action=photo&subact=delete&id=%d',
                    $work->config['site_url'],
                    $photo_data['id']
                )
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
            ) : ''
        ]);

        // Добавляем данные о категории
        if ($photo_data['category'] === 0) {
            $template->add_string_ar([
                'D_NAME_CATEGORY' => $photo_data['category_name'] . ' ' . ($photo_data['real_name'] ?? ''),
                'D_DESCRIPTION_CATEGORY' => $photo_data['category_description'] . ' ' . ($photo_data['real_name'] ?? ''),
                'U_CATEGORY' => sprintf(
                    '%s?action=category&cat=user&id=%d',
                    $work->config['site_url'],
                    $photo_data['user_upload']
                )
            ]);
        } else {
            $template->add_string_ar([
                'D_NAME_CATEGORY' => $photo_data['category_name'],
                'D_DESCRIPTION_CATEGORY' => $photo_data['category_description'],
                'U_CATEGORY' => sprintf('%s?action=category&cat=%d', $work->config['site_url'], $photo_data['category'])
            ]);
        }

        // Добавляем данные об оценках
        $template->add_string_ar([
            'D_RATE_USER' => $work->lang['photo']['rate_user'] . ': ' . $photo_data['rate_user'],
            'D_RATE_MODER' => $work->lang['photo']['rate_moder'] . ': ' . $photo_data['rate_moder']
        ]);

        // Проверка возможности оценки пользователем
        if ($user->user['pic_rate_user']) {
            $db->select('`rate`', TBL_RATE_USER, [
                'where' => '`id_foto` = :id_foto AND `id_user` = :id_user',
                'params' => [':id_foto' => $photo_id, ':id_user' => $user->user['id']]
            ]);
            $user_rate = $db->res_row() === false;

            if ($user_rate) {
                $template->add_if('RATE_USER', true);
                $key = 0;
                for ($i = -$work->config['max_rate']; $i <= $work->config['max_rate']; $i++) {
                    $selected = ($i === 0) ? ' selected="selected"' : '';
                    $template->add_string_ar([
                        'D_LVL_RATE' => $i,
                        'D_SELECTED' => $selected
                    ], 'SELECT_USER_RATE[' . $key++ . ']');
                }
            }
        } else {
            $user_rate = false;
        }

        // Проверка возможности оценки модератором
        if ($user->user['pic_rate_moder']) {
            $db->select('`rate`', TBL_RATE_MODER, [
                'where' => '`id_foto` = :id_foto AND `id_user` = :id_user',
                'params' => [':id_foto' => $photo_id, ':id_user' => $user->user['id']]
            ]);
            $moder_rate = $db->res_row() === false;

            if ($moder_rate) {
                $template->add_if('RATE_MODER', true);
                $key = 0;
                for ($i = -$work->config['max_rate']; $i <= $work->config['max_rate']; $i++) {
                    $selected = ($i === 0) ? ' selected="selected"' : '';
                    $template->add_string_ar([
                        'D_LVL_RATE' => $i,
                        'D_SELECTED' => $selected
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
            $template->add_string_ar([
                'L_RATE' => $work->lang['photo']['rate_you'],
                'L_USER_RATE' => $work->lang['photo']['if_user'],
                'L_MODER_RATE' => $work->lang['photo']['if_moder'],
                'L_RATE_THIS' => $work->lang['photo']['rate'],
                'U_RATE' => sprintf('%s?action=photo&subact=rate&id=%d', $work->config['site_url'], $photo_data['id'])
            ]);
        }
    }
} else {
    if ($work->check_get(
        'subact',
        true,
        true
    ) && $_GET['subact'] === "upload" && $user->user['id'] !== 0 && $user->user['pic_upload']) {
        $template->add_case('PHOTO_BLOCK', 'UPLOAD_PHOTO');
        $max_size_php = Work::return_bytes(ini_get('post_max_size'));
        $max_size = Work::return_bytes($work->config['max_file_size']);
        if ($max_size > $max_size_php) {
            $max_size = $max_size_php;
        }
        if ((bool)$user->user['cat_user']) {
            $select_cat = false;
        } else {
            $select_cat = '`id` != 0';
        }

        if ($db->select('*', TBL_CATEGORY, $select_cat)) {
            $category_data = $db->res_arr();
            if ($category_data) {
                foreach ($category_data as $key => $val) {
                    if ($val['id'] === 0) {
                        $val['name'] .= ' ' . Work::clean_field($user->user['real_name']);
                        $selected = ' selected="selected"';
                    } else {
                        $selected = '';
                    }
                    $template->add_string_ar(array(
                        'D_ID_CATEGORY' => $val['id'],
                        'D_NAME_CATEGORY' => $val['name'],
                        'D_SELECTED' => $selected
                    ), 'UPLOAD_CATEGORY[' . $key . ']');
                }
                $template->add_string_ar(array(
                    'L_NAME_BLOCK' => $work->lang['photo']['title'] . ' - ' . $work->lang['photo']['upload'],
                    'L_NAME_PHOTO' => $work->lang['main']['name_of'] . ' ' . $work->lang['photo']['of_photo'],
                    'L_DESCRIPTION_PHOTO' => $work->lang['main']['description_of'] . ' ' . $work->lang['photo']['of_photo'],
                    'L_NAME_CATEGORY' => $work->lang['main']['name_of'] . ' ' . $work->lang['category']['of_category'],
                    'L_UPLOAD_THIS' => $work->lang['photo']['upload'],
                    'L_FILE_PHOTO' => $work->lang['photo']['select_file'],
                    'D_MAX_FILE_SIZE' => $max_size,
                    'U_UPLOADED' => $work->config['site_url'] . '?action=photo&amp;subact=uploaded'
                ));
            } else {
                log_in_file('Unable to get the category', DIE_IF_ERROR);
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }
    } elseif ($work->check_get(
        'subact',
        true,
        true
    ) && $_GET['subact'] === "uploaded" && $user->user['id'] !== 0 && $user->user['pic_upload']) {
        $submit_upload = true;
        $max_size_php = Work::return_bytes(ini_get('post_max_size'));
        $max_size = Work::return_bytes($work->config['max_file_size']);
        if ($max_size > $max_size_php) {
            $max_size = $max_size_php;
        }

        if (!$work->check_post('name_photo', true, true)) {
            $photo['name'] = $work->lang['photo']['no_name'] . ' (' . Work::encodename(
                basename($_FILES['file_photo']['name'])
            ) . ')';
        } else {
            $photo['name'] = $_POST['name_photo'];
        }

        if (!$work->check_post('description_photo', true, true)) {
            $photo['description'] = $work->lang['photo']['no_description'] . ' (' . Work::encodename(
                basename($_FILES['file_photo']['name'])
            ) . ')';
        } else {
            $photo['description'] = $_POST['description_photo'];
        }

        if (!$work->check_post('name_category', true, false, '^[0-9]+\$')) {
            $submit_upload = false;
        } else {
            if ($user->user['cat_user'] || $user->user['pic_moderate']) {
                $select_cat = '`id` = ' . $_POST['name_category'];
            } else {
                $select_cat = '`id` != 0 AND `id` = ' . $_POST['name_category'];
            }
            if ($db->select('*', TBL_CATEGORY, $select_cat)) {
                $category_data = $db->res_row();
                if (!$category_data) {
                    $submit_upload = false;
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        }

        if ($submit_upload) {
            $photo['category_name'] = $_POST['name_category'];
            if ($_FILES['file_photo']['error'] === 0 && $_FILES['file_photo']['size'] > 0 && $_FILES['file_photo']['size'] <= $max_size && mb_eregi(
                '(gif|jpeg|png)$',
                $_FILES['file_photo']['type'],
                $type
            )) {
                $file_name = time() . '_' . Work::encodename(basename($_FILES['file_photo']['name'])) . '.' . $type[0];

                if ($db->select('*', TBL_CATEGORY, '`id` = ' . $photo['category_name'])) {
                    $category_data = $db->res_row();
                    if ($category_data) {
                        $photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $category_data['folder'] . '/' . $file_name;
                        $photo['thumbnail_path'] = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $category_data['folder'] . '/' . $file_name;
                        if (move_uploaded_file($_FILES['file_photo']['tmp_name'], $photo['path'])) {
                            $work->image_resize($photo['path'], $photo['thumbnail_path']);
                        } else {
                            $submit_upload = false;
                        }
                    } else {
                        $submit_upload = false;
                    }
                } else {
                    log_in_file($db->error, DIE_IF_ERROR);
                }
            } else {
                $submit_upload = false;
            }
        }
        if ($submit_upload) {
            $query = array(
                'file' => $file_name,
                'name' => $photo['name'],
                'description' => $photo['description'],
                'category' => $photo['category_name'],
                'date_upload' => date('Y-m-d H:m:s'),
                'user_upload' => $user->user['id'],
                'rate_user' => '0',
                'rate_moder' => '0'
            );
            if ($db->insert($query, TBL_PHOTO, 'ignore')) {
                $photo_id = $db->get_last_insert_id();
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
            if ($photo_id) {
                $redirect_url = $work->config['site_url'] . '?action=photo&id=' . $photo_id;
            } else {
                @unlink($photo['path']);
                @unlink($photo['thumbnail_path']);
                $redirect_url = $work->config['site_url'] . '?action=photo&subact=upload';
            }
        } else {
            $redirect_url = $work->config['site_url'] . '?action=photo&subact=upload';
        }
    } else {
        $template->add_case('PHOTO_BLOCK', 'VIEW_PHOTO');
        $photo_data['file'] = 'no_foto.png';
        $template->add_string_ar(array(
            'L_NAME_BLOCK' => $work->lang['photo']['title'] . ' - ' . $work->lang['main']['no_foto'],
            'L_DESCRIPTION_BLOCK' => $work->lang['main']['no_foto'],
            'L_USER_ADD' => $work->lang['main']['user_add'],
            'L_NAME_CATEGORY' => $work->lang['main']['name_of'] . ' ' . $work->lang['category']['of_category'],
            'L_DESCRIPTION_CATEGORY' => $work->lang['main']['description_of'] . ' ' . $work->lang['category']['of_category'],
            'D_NAME_PHOTO' => $work->lang['main']['no_foto'],
            'D_DESCRIPTION_PHOTO' => $work->lang['main']['no_foto'],
            'D_USER_ADD' => $work->lang['main']['no_user_add'],
            'D_NAME_CATEGORY' => $work->lang['main']['no_category'],
            'D_DESCRIPTION_CATEGORY' => $work->lang['main']['no_category'],
            'D_RATE_USER' => $work->lang['photo']['rate_user'] . ': ' . $work->lang['main']['no_foto'],
            'D_RATE_MODER' => $work->lang['photo']['rate_moder'] . ': ' . $work->lang['main']['no_foto'],
            'U_CATEGORY' => $work->config['site_url'],
            'U_USER_ADD' => '',
            'U_PHOTO' => $work->config['site_url'] . '?action=attach&amp;foto=0'
        ));

        $photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $photo_data['file'];
    }
}

if (!empty($photo['path'])) {
    $size = getimagesize($photo['path']);

    if ($max_photo_w === '0') {
        $ratioWidth = 1;
    } else {
        $ratioWidth = $size[0] / $max_photo_w;
    }
    if ($max_photo_h === '0') {
        $ratioHeight = 1;
    } else {
        $ratioHeight = $size[1] / $max_photo_h;
    }

    if ($size[0] < $max_photo_w && $size[1] < $max_photo_h && (int)$max_photo_w !== 0 && (int)$max_photo_h !== 0) {
        $photo['width'] = $size[0];
        $photo['height'] = $size[1];
    } else {
        if ($ratioWidth < $ratioHeight) {
            $photo['width'] = $size[0] / $ratioHeight;
            $photo['height'] = $size[1] / $ratioHeight;
        } else {
            $photo['width'] = $size[0] / $ratioWidth;
            $photo['height'] = $size[1] / $ratioWidth;
        }
    }
    $template->add_string_ar(array(
        'D_FOTO_WIDTH' => (string)$photo['width'],
        'D_FOTO_HEIGHT' => (string)$photo['height']
    ));
}

if ((isset($_GET['subact']) && $_GET['subact'] === "uploaded" && $user->user['id'] !== 0 && $user->user['pic_upload'])) {
    header('Location: ' . $redirect_url);
    log_in_file('Hack attempt!', DIE_IF_ERROR);
}
