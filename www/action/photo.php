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
    // Объединение данных из таблиц TBL_PHOTO и TBL_CATEGORY
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
            TBL_CATEGORY . '.`description` AS `category_description`'
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

    if (!$photo_data) {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные фотографии | ID: {$photo_id}"
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

            // TODO Проверить можно ли переголосовать оценку в проекте
            $affected_rows = $db->get_affected_rows();
            if ($affected_rows === 0) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось обновить оценку пользователя в таблице фотографий | ID фотографии: {$photo_id}"
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

            // TODO Проверить можно ли переголосовать оценку в проекте
            $affected_rows = $db->get_affected_rows();
            if ($affected_rows === 0) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось обновить оценку модератора в таблице фотографий | ID фотографии: {$photo_id}"
                );
            }
        }
    } elseif ($work->check_get(
        'subact',
        true,
        true
    ) && $_GET['subact'] === 'saveedit' && (($photo_data['user_upload'] === $user->user['id'] && $user->user['id'] !== 0) || $user->user['pic_moderate'])) {
        if ($db->select('*', TBL_PHOTO, '`id` = ' . $photo_id)) {
            $photo_data = $db->res_row();
            if ($photo_data) {
                if ($db->select('folder', TBL_CATEGORY, '`id` = ' . $photo_data['category'])) {
                    $temp_old = $db->res_row();
                    if (!$temp_old) {
                        log_in_file('Unable to get the category', DIE_IF_ERROR);
                    }
                } else {
                    log_in_file($db->error, DIE_IF_ERROR);
                }

                $photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_old['folder'] . '/' . $photo_data['file'];
                if (!$work->check_post('name_photo', true, true)) {
                    $photo['name'] = $photo_data['name'];
                } else {
                    $photo['name'] = trim(Work::clean_field($_POST['name_photo']));
                }

                if (!$work->check_post('description_photo', true, true)) {
                    $photo['description'] = $photo_data['description'];
                } else {
                    $photo['description'] = trim(Work::clean_field($_POST['description_photo']));
                }

                $category = true;

                if (!$work->check_post('name_category', true, true, '^[0-9]+\$')) {
                    $category = false;
                } else {
                    if ($user->user['cat_user'] || $user->user['pic_moderate']) {
                        $select_cat = '`id` = ' . $_POST['name_category'];
                    } else {
                        $select_cat = '`id` != 0 AND `id` =' . $_POST['name_category'];
                    }
                    if ($db->select('*', TBL_CATEGORY, $select_cat)) {
                        $temp = $db->res_row();
                        if (!$temp) {
                            $category = false;
                        }
                    } else {
                        log_in_file($db->error, DIE_IF_ERROR);
                    }
                }

                if ($category && $photo_data['category'] !== $_POST['name_category']) {
                    if ($db->select('folder', TBL_CATEGORY, '`id` = ' . $_POST['name_category'])) {
                        $temp_new = $db->res_row();
                        if (!$temp_new) {
                            log_in_file('Unable to get the category', DIE_IF_ERROR);
                        }
                    } else {
                        log_in_file($db->error, DIE_IF_ERROR);
                    }
                    $path_old_photo = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_old['folder'] . '/' . $photo_data['file'];
                    $path_new_photo = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp_new['folder'] . '/' . $photo_data['file'];
                    $path_old_thumbnail = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_old['folder'] . '/' . $photo_data['file'];
                    $path_new_thumbnail = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp_new['folder'] . '/' . $photo_data['file'];
                    if (!rename($path_old_photo, $path_new_photo)) {
                        $photo['category_name'] = $photo_data['category'];
                        $photo['path'] = $path_old_photo;
                    } else {
                        if (!rename($path_old_thumbnail, $path_new_thumbnail)) {
                            @unlink($path_old_thumbnail);
                        }
                        $photo['category_name'] = $_POST['name_category'];
                        $photo['path'] = $path_new_photo;
                    }
                } else {
                    $photo['category_name'] = $photo_data['category'];
                }

                if (!$db->update(
                    array(
                        'name' => $photo['name'],
                        'description' => $photo['description'],
                        'category' => $photo['category_name']
                    ),
                    TBL_PHOTO,
                    '`id` = ' . $photo_id
                )) {
                    log_in_file($db->error, DIE_IF_ERROR);
                }
            } else {
                log_in_file('Unable to get the photo', DIE_IF_ERROR);
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }
    }

    if ($work->check_get(
        'subact',
        true,
        true
    ) && $_GET['subact'] === "edit" && (($photo_data['user_upload'] === $user->user['id'] && $user->user['id'] !== 0) || $user->user['pic_moderate'])) {
        $template->add_case('PHOTO_BLOCK', 'EDIT_PHOTO');
        if ($db->select('*', TBL_PHOTO, '`id` = ' . $photo_id)) {
            $photo_data = $db->res_row();
            if ($photo_data) {
                if ($db->select('*', TBL_CATEGORY, '`id` = ' . $photo_data['category'])) {
                    $category_data = $db->res_row();
                    if ($category_data) {
                        $photo['path'] = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $category_data['folder'] . '/' . $photo_data['file'];
                        if ($db->select(
                            'real_name',
                            TBL_USERS,
                            '`id` = ' . $photo_data['user_upload']
                        )) {
                            $user_add = $db->res_row();
                            if ($user_add) {
                                $photo['user'] = $user_add['real_name'];
                                $photo['user_url'] = $work->config['site_url'] . '?action=profile&amp;subact=profile&amp;uid=' . $photo_data['user_upload'];
                            } else {
                                $photo['user'] = $work->lang['main']['no_user_add'];
                                $photo['user_url'] = '';
                            }
                        } else {
                            log_in_file($db->error, DIE_IF_ERROR);
                        }

                        if ($user->user['cat_user'] || $user->user['pic_moderate']) {
                            $select_cat = false;
                        } else {
                            $select_cat = '`id` != 0';
                        }

                        if ($db->select('*', TBL_CATEGORY, $select_cat)) {
                            $category_data = $db->res_arr();
                            if ($category_data) {
                                foreach ($category_data as $key => $val) {
                                    if ($val['id'] === $photo_data['category']) {
                                        $selected = ' selected="selected"';
                                    } else {
                                        $selected = '';
                                    }
                                    if ($val['id'] === 0) {
                                        $val['name'] .= ' ' . $photo['user'];
                                    }
                                    $template->add_string_ar(array(
                                        'D_ID_CATEGORY' => $val['id'],
                                        'D_NAME_CATEGORY' => $val['name'],
                                        'D_SELECTED' => $selected
                                    ), 'SELECT_CATEGORY[' . $key . ']');
                                }
                                $max_photo_w = $work->config['temp_photo_w'];
                                $max_photo_h = $work->config['temp_photo_h'];
                                $template->add_string_ar(array(
                                    'L_NAME_BLOCK' => $work->lang['photo']['edit'] . ' - ' . $photo_data['name'],
                                    'L_NAME_PHOTO' => $work->lang['main']['name_of'] . ' ' . $work->lang['photo']['of_photo'],
                                    'L_DESCRIPTION_PHOTO' => $work->lang['main']['description_of'] . ' ' . $work->lang['photo']['of_photo'],
                                    'L_NAME_CATEGORY' => $work->lang['main']['name_of'] . ' ' . $work->lang['category']['of_category'],
                                    'L_NAME_FILE' => $work->lang['photo']['filename'],
                                    'L_EDIT_THIS' => $work->lang['photo']['save'],
                                    'D_NAME_FILE' => $photo_data['file'],
                                    'D_NAME_PHOTO' => $photo_data['name'],
                                    'D_DESCRIPTION_PHOTO' => $photo_data['description'],
                                    'U_EDITED' => $work->config['site_url'] . '?action=photo&amp;subact=saveedit&amp;id=' . $photo_data['id'],
                                    'U_PHOTO' => $work->config['site_url'] . '?action=attach&amp;foto=' . $photo_data['id'] . '&amp;thumbnail=1'
                                ));
                            } else {
                                log_in_file('Unable to get the category', DIE_IF_ERROR);
                            }
                        } else {
                            log_in_file($db->error, DIE_IF_ERROR);
                        }
                    } else {
                        log_in_file('Unable to get the category', DIE_IF_ERROR);
                    }
                } else {
                    log_in_file($db->error, DIE_IF_ERROR);
                }
            } else {
                log_in_file('Unable to get the photo', DIE_IF_ERROR);
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }
    } elseif ($work->check_get(
        'subact',
        true,
        true
    ) && $_GET['subact'] === "delete" && (($photo_data['user_upload'] === $user->user['id'] && $user->user['id'] !== 0) || $user->user['pic_moderate'])) {
        $photo['name'] = $photo_data['name'];
        if ($photo_data['category'] === 0) {
            if ($db->select(
                'COUNT(*) as `count_photo`',
                TBL_PHOTO,
                '`id` != ' . $photo_id . ' AND `category` = 0 AND `user_upload` = ' . $photo_data['user_upload']
            )) {
                $temp = $db->res_row();
                if (isset($temp['count_photo']) && $temp['count_photo'] > 0) {
                    $photo['category_url'] = $work->config['site_url'] . '?action=category&cat=user&id=' . $photo_data['user_upload'];
                } else {
                    if ($db->select(
                        'COUNT(*) as `count_photo`',
                        TBL_PHOTO,
                        '`id` != ' . $photo_id . ' AND `category` = 0'
                    )) {
                        $temp = $db->res_row();
                        if (isset($temp['count_photo']) && $temp['count_photo'] > 0) {
                            $photo['category_url'] = $work->config['site_url'] . '?action=category&cat=user';
                        } else {
                            if ($db->select(
                                'COUNT(*) as `count_photo`',
                                TBL_PHOTO,
                                '`id` != ' . $photo_id
                            )) {
                                $temp = $db->res_row();
                                if (isset($temp['count_photo']) && $temp['count_photo'] > 0) {
                                    $photo['category_url'] = $work->config['site_url'] . '?action=category';
                                } else {
                                    $photo['category_url'] = $work->config['site_url'];
                                }
                            } else {
                                log_in_file($db->error, DIE_IF_ERROR);
                            }
                        }
                    } else {
                        log_in_file($db->error, DIE_IF_ERROR);
                    }
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        } else {
            if ($db->select(
                'COUNT(*) as `count_photo`',
                TBL_PHOTO,
                '`id` != ' . $photo_id . ' AND `category` = ' . $photo_data['category']
            )) {
                $temp = $db->res_row();
                if (isset($temp['count_photo']) && $temp['count_photo'] > 0) {
                    $photo['category_url'] = $work->config['site_url'] . '?action=category&cat=' . $photo_data['category'];
                } else {
                    if ($db->select('COUNT(*) as `count_photo`', TBL_PHOTO, '`id` != ' . $photo_id)) {
                        $temp = $db->res_row();
                        if (isset($temp['count_photo']) && $temp['count_photo'] > 0) {
                            $photo['category_url'] = $work->config['site_url'] . '?action=category';
                        } else {
                            $photo['category_url'] = $work->config['site_url'];
                        }
                    } else {
                        log_in_file($db->error, DIE_IF_ERROR);
                    }
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        }

        if ($work->del_photo($photo_id)) {
            header('Location: ' . $photo['category_url']);
            log_in_file('Hack attempt!');
        } else {
            header('Location: ' . $work->config['site_url'] . '?action=photo&id=' . $photo_id);
            log_in_file('Hack attempt!');
        }
    } else {
        if ($db->select('*', TBL_PHOTO, '`id` = ' . $photo_id)) {
            $photo_data = $db->res_row();
            if ($photo_data) {
                if ($db->select('*', TBL_CATEGORY, '`id` = ' . $photo_data['category'])) {
                    $category_data = $db->res_row();
                    if ($category_data) {
                        $template->add_case('PHOTO_BLOCK', 'VIEW_PHOTO');
                        $template->add_string_ar(array(
                            'L_NAME_BLOCK' => $work->lang['photo']['title'] . ' - ' . $photo_data['name'],
                            'L_DESCRIPTION_BLOCK' => $photo_data['description'],
                            'L_USER_ADD' => $work->lang['main']['user_add'],
                            'L_NAME_CATEGORY' => $work->lang['main']['name_of'] . ' ' . $work->lang['category']['of_category'],
                            'L_DESCRIPTION_CATEGORY' => $work->lang['main']['description_of'] . ' ' . $work->lang['category']['of_category'],
                            'D_NAME_PHOTO' => $photo_data['name'],
                            'D_DESCRIPTION_PHOTO' => $photo_data['description'],
                            'U_PHOTO' => $work->config['site_url'] . '?action=attach&amp;foto=' . $photo_data['id']
                        ));
                        $photo['path'] = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $category_data['folder'] . '/' . $photo_data['file'];

                        if (($photo_data['user_upload'] === $user->user['id'] && $user->user['id'] !== 0) || $user->user['pic_moderate']) {
                            $template->add_string_ar(array(
                                'L_EDIT_BLOCK' => $work->lang['photo']['edit'],
                                'L_CONFIRM_DELETE_BLOCK' => $work->lang['photo']['confirm_delete'] . ' ' . $photo_data['name'],
                                'L_DELETE_BLOCK' => $work->lang['photo']['delete'],
                                'U_EDIT_BLOCK' => $work->config['site_url'] . '?action=photo&amp;subact=edit&amp;id=' . $photo_data['id'],
                                'U_DELETE_BLOCK' => $work->config['site_url'] . '?action=photo&amp;subact=delete&amp;id=' . $photo_data['id']
                            ));
                            $template->add_if('EDIT_BLOCK', true);
                        }
                        if ($db->select(
                            'real_name',
                            TBL_USERS,
                            '`id` = ' . $photo_data['user_upload']
                        )) {
                            $user_add = $db->res_row();
                            if ($user_add) {
                                $template->add_string_ar(array(
                                    'D_USER_ADD' => $user_add['real_name'],
                                    'U_USER_ADD' => $work->config['site_url'] . '?action=profile&amp;subact=profile&amp;uid=' . $photo_data['user_upload']
                                ));
                            } else {
                                $template->add_string_ar(array(
                                    'D_USER_ADD' => $work->lang['main']['no_user_add'],
                                    'U_USER_ADD' => ''
                                ));
                            }
                        } else {
                            log_in_file($db->error, DIE_IF_ERROR);
                        }
                        if ($category_data['id'] === 0) {
                            $template->add_string_ar(array(
                                'D_NAME_CATEGORY' => $category_data['name'] . ' ' . $user_add['real_name'],
                                'D_DESCRIPTION_CATEGORY' => $category_data['description'] . ' ' . $user_add['real_name'],
                                'U_CATEGORY' => $work->config['site_url'] . '?action=category&amp;cat=user&amp;id=' . $photo_data['user_upload']
                            ));
                        } else {
                            $template->add_string_ar(array(
                                'D_NAME_CATEGORY' => $category_data['name'],
                                'D_DESCRIPTION_CATEGORY' => $category_data['description'],
                                'U_CATEGORY' => $work->config['site_url'] . '?action=category&amp;cat=' . $category_data['id']
                            ));
                        }
                        $template->add_string_ar(array(
                            'D_RATE_USER' => $work->lang['photo']['rate_user'] . ': ' . $photo_data['rate_user'],
                            'D_RATE_MODER' => $work->lang['photo']['rate_moder'] . ': ' . $photo_data['rate_moder']
                        ));
                        if ($user->user['pic_rate_user']) {
                            if ($db->select(
                                'rate',
                                TBL_RATE_USER,
                                '`id_foto` = ' . $photo_id . ' AND `id_user` = ' . $user->user['id']
                            )) {
                                $temp_rate = $db->res_row();
                                if ($temp_rate) {
                                    $user_rate = false;
                                } else {
                                    $user_rate = true;
                                }
                            } else {
                                log_in_file($db->error, DIE_IF_ERROR);
                            }
                            if ($user_rate !== false) {
                                $template->add_if('RATE_USER', true);
                                $key = 0;
                                for ($i = -$work->config['max_rate']; $i <= $work->config['max_rate']; $i++) {
                                    if ($i === 0) {
                                        $selected = ' selected="selected"';
                                    } else {
                                        $selected = '';
                                    }
                                    $template->add_string_ar(array(
                                        'D_LVL_RATE' => $i,
                                        'D_SELECTED' => $selected
                                    ), 'SELECT_USER_RATE[' . $key . ']');
                                    $key++;
                                }
                            }
                        } else {
                            $user_rate = false;
                        }

                        if ($user->user['pic_rate_moder']) {
                            if ($db->select(
                                'rate',
                                TBL_RATE_MODER,
                                '`id_foto` = ' . $photo_id . ' AND `id_user` = ' . $user->user['id']
                            )) {
                                $temp_rate = $db->res_row();
                                if ($temp_rate) {
                                    $moder_rate = false;
                                } else {
                                    $moder_rate = true;
                                }
                            } else {
                                log_in_file($db->error, DIE_IF_ERROR);
                            }
                            if ($moder_rate !== false) {
                                $template->add_if('RATE_MODER', true);
                                $key = 0;
                                for ($i = -$work->config['max_rate']; $i <= $work->config['max_rate']; $i++) {
                                    if ($i === 0) {
                                        $selected = ' selected="selected"';
                                    } else {
                                        $selected = '';
                                    }
                                    $template->add_string_ar(array(
                                        'D_LVL_RATE' => $i,
                                        'D_SELECTED' => $selected
                                    ), 'SELECT_MODER_RATE[' . $key . ']');
                                    $key++;
                                }
                            }
                        } else {
                            $moder_rate = false;
                        }

                        if ((($user->user['pic_rate_user'] || $user->user['pic_rate_moder']) && $photo_data['user_upload'] !== $user->user['id']) && ($user_rate !== false || $moder_rate !== false)) {
                            $template->add_if('RATE_PHOTO', true);
                            $template->add_string_ar(array(
                                'L_RATE' => $work->lang['photo']['rate_you'],
                                'L_USER_RATE' => $work->lang['photo']['if_user'],
                                'L_MODER_RATE' => $work->lang['photo']['if_moder'],
                                'L_RATE_THIS' => $work->lang['photo']['rate'],
                                'U_RATE' => $work->config['site_url'] . '?action=photo&amp;subact=rate&amp;id=' . $photo_id
                            ));
                        }
                    } else {
                        log_in_file('Unable to get the category', DIE_IF_ERROR);
                    }
                } else {
                    log_in_file($db->error, DIE_IF_ERROR);
                }
            } else {
                log_in_file('Unable to get the photo', DIE_IF_ERROR);
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
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
