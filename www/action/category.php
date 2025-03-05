<?php

/**
 * @file        action/category.php
 * @brief       Обзор и управление разделами.
 * @author      Dark Dayver
 * @version     0.2.0
 * @date        28/03-2012
 * @details     Обзор, редактирование, удаление и добавление разделов в галерею.
 */

namespace PhotoRigma\Action;

// Предотвращение прямого вызова файла
use function PhotoRigma\Include\log_in_file;

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

$template->template_file = 'category.html';

if (!$work->check_get('cat', true, true)) {
    $cat = false;
} else {
    $cat = $_GET['cat'];
}
$template->add_if_ar(array(
    'ISSET_CATEGORY' => false,
    'EDIT_BLOCK' => false,
    'ISSET_PIC' => false,
    'USER_EXISTS' => false,
    'CATEGORY_EDIT' => false
));

if ($cat === 'user' || $cat === 0) {
    if (!$work->check_get('id', true, true, '^[0-9|curent]+\$')) {
        $action = 'user_category';
        if ($db->select('DISTINCT `user_upload`', TBL_PHOTO, '`category` = 0', array('user_upload' => 'up'))) {
            $temp = $db->res_arr();
            $template->add_case('CATEGORY_BLOCK', 'VIEW_DIR');
            if ($temp) {
                foreach ($temp as $key => $val) {
                    if ($db->select('id', TBL_USERS, '`id` = ' . $val['user_upload'])) {
                        $temp2 = $db->res_row();
                        if ($temp2) {
                            $temp_category = $work->category($val['user_upload'], 1);
                            $template->add_string_ar(array(
                                'D_NAME_CATEGORY' => $temp_category['name'],
                                'D_DESCRIPTION_CATEGORY' => $temp_category['description'],
                                'D_COUNT_PHOTO' => $temp_category['count_photo'],
                                'D_LAST_PHOTO' => $temp_category['last_photo'],
                                'D_TOP_PHOTO' => $temp_category['top_photo'],
                                'U_CATEGORY' => $temp_category['url_cat'],
                                'U_LAST_PHOTO' => $temp_category['url_last_photo'],
                                'U_TOP_PHOTO' => $temp_category['url_top_photo']
                            ), 'LIST_CATEGORY[' . $key . ']');
                        } else {
                            log_in_file('Unable to get the user category', DIE_IF_ERROR);
                        }
                    } else {
                        log_in_file($db->error, DIE_IF_ERROR);
                    }
                }

                if ($db->select(array('name', 'description'), TBL_CATEGORY, '`id` = 0')) {
                    $temp2 = $db->res_row();
                    if ($temp2) {
                        $template->add_if('ISSET_CATEGORY', true);
                        $template->add_string_ar(array(
                            'NAME_BLOCK' => $work->lang['category']['users_album'],
                            'L_NAME_CATEGORY' => $temp2['name'],
                            'L_DESCRIPTION_CATEGORY' => $temp2['description'],
                            'L_COUNT_PHOTO' => $work->lang['category']['count_photo'],
                            'L_LAST_PHOTO' => $work->lang['main']['last_foto'] . $work->lang['category']['of_category'],
                            'L_TOP_PHOTO' => $work->lang['main']['top_foto'] . $work->lang['category']['of_category']
                        ));
                    } else {
                        log_in_file('Unable to get the user category', DIE_IF_ERROR);
                    }
                } else {
                    log_in_file($db->error, DIE_IF_ERROR);
                }
            } else {
                $template->add_string_ar(array(
                    'NAME_BLOCK' => $work->lang['category']['users_album'],
                    'L_NAME_CATEGORY' => $work->lang['main']['name_of'] . $work->lang['category']['of_category'],
                    'L_DESCRIPTION_CATEGORY' => $work->lang['main']['description_of'] . $work->lang['category']['of_category'],
                    'L_COUNT_PHOTO' => $work->lang['category']['count_photo'],
                    'L_LAST_PHOTO' => $work->lang['main']['last_foto'] . $work->lang['category']['of_category'],
                    'L_TOP_PHOTO' => $work->lang['main']['top_foto'] . $work->lang['category']['of_category'],
                    'L_NO_PHOTO' => $work->lang['category']['no_user_category']
                ));
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }
    } else {
        if ($_GET['id'] === 'curent' && $user->user['id'] > 0) {
            $cat_id = $user->user['id'];
        } else {
            $cat_id = $_GET['id'];
        }
        if ($cat_id == $user->user['id'] && $user->user['id'] > 0) {
            $action = 'you_category';
        }

        if ($db->select(
            'id',
            TBL_PHOTO,
            '`category` = 0 AND `user_upload` = ' . $cat_id,
            array('date_upload' => 'down')
        )) {
            $temp = $db->res_arr();
            $template->add_case('CATEGORY_BLOCK', 'VIEW_PIC');
            if ($temp && (bool)$user->user['pic_view']) {
                $template->add_if('ISSET_PIC', true);
                foreach ($temp as $key => $val) {
                    $temp_category = $work->create_photo('cat', $val['id']);
                    $template->add_string_ar(array(
                        'L_USER_ADD' => $work->lang['main']['user_add'],
                        'MAX_PHOTO_HEIGHT' => $work->config['temp_photo_h'] + 10,
                        'PHOTO_WIDTH' => $temp_category['width'],
                        'PHOTO_HEIGHT' => $temp_category['height'],
                        'D_DESCRIPTION_PHOTO' => $temp_category['description'],
                        'D_NAME_PHOTO' => $temp_category['name'],
                        'D_REAL_NAME_USER_ADD' => $temp_category['real_name'],
                        'D_PHOTO_RATE' => $temp_category['rate'],
                        'U_THUMBNAIL_PHOTO' => $temp_category['thumbnail_url'],
                        'U_PHOTO' => $temp_category['url'],
                        'U_PROFILE_USER_ADD' => $temp_category['url_user']
                    ), 'LIST_PIC[' . $key . ']');
                    if ($temp_category['url_user'] !== null) {
                        $template->add_if('USER_EXISTS', true, 'LIST_PIC[' . $key . ']');
                    }
                }
                if ($db->select('real_name', TBL_USERS, '`id` = ' . $cat_id)) {
                    $temp_user = $db->res_row();
                    if ($temp_user) {
                        if ($db->select(array('name', 'description'), TBL_CATEGORY, '`id` = 0')) {
                            $temp2 = $db->res_row();
                            if ($temp2) {
                                $template->add_string_ar(array(
                                    'L_NAME_BLOCK' => $work->lang['category']['category'] . ' - ' . $temp2['name'] . ' ' . $temp_user['real_name'],
                                    'L_DESCRIPTION_BLOCK' => $temp2['description'] . ' ' . $temp_user['real_name']
                                ));
                            } else {
                                log_in_file('Unable to get the user category', DIE_IF_ERROR);
                            }
                        } else {
                            log_in_file($db->error, DIE_IF_ERROR);
                        }
                    } else {
                        log_in_file('Unable to get the user', DIE_IF_ERROR);
                    }
                } else {
                    log_in_file($db->error, DIE_IF_ERROR);
                }
            } else {
                $template->add_string_ar(array(
                    'L_NAME_BLOCK' => $work->lang['category']['name_block'],
                    'L_DESCRIPTION_BLOCK' => $work->lang['category']['error_no_category'],
                    'L_NO_PHOTO' => $work->lang['category']['error_no_photo']
                ));
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }
    }
} elseif ($work->check_get('cat', true, true, '^[0-9]+\$')) {
    if ($work->check_get(
        'subact',
        true,
        true
    ) && $_GET['subact'] === 'saveedit' && (bool)$user->user['cat_moderate'] && $cat != 0) {
        if ($db->select('*', TBL_CATEGORY, '`id` = ' . $cat)) {
            $temp = $db->res_row();
            if ($temp) {
                if (!$work->check_post('name_category', true, true)) {
                    $name_category = $temp['name'];
                } else {
                    $name_category = $_POST['name_category'];
                }

                if (!$work->check_post('description_category', true, true)) {
                    $description_category = $temp['description'];
                } else {
                    $description_category = $_POST['description_category'];
                }

                if (!$db->update(
                    array('name' => $name_category, 'description' => $description_category),
                    TBL_CATEGORY,
                    '`id` = ' . $cat
                )) {
                    log_in_file($db->error, DIE_IF_ERROR);
                }
            } else {
                log_in_file('Unable to get the category', DIE_IF_ERROR);
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }
        header('Location: ' . $work->config['site_url'] . '?action=category&cat=' . $cat);
        log_in_file('Hack attempt!');
    }

    if ($work->check_get(
        'subact',
        true,
        true
    ) && $_GET['subact'] === 'edit' && (bool)$user->user['cat_moderate'] && $cat != 0) {
        if ($db->select('*', TBL_CATEGORY, '`id` = ' . $cat)) {
            $temp = $db->res_row();
            $template->add_case('CATEGORY_BLOCK', 'CATEGORY_EDIT');
            if ($temp) {
                $template->add_if_ar(array(
                    'ISSET_CATEGORY' => true,
                    'CATEGORY_EDIT' => true
                ));
                $template->add_string_ar(array(
                    'L_NAME_BLOCK' => $work->lang['category']['edit'] . ' - ' . $temp['name'],
                    'L_NAME_DIR' => $work->lang['category']['cat_dir'],
                    'L_NAME_CATEGORY' => $work->lang['main']['name_of'] . ' ' . $work->lang['category']['of_category'],
                    'L_DESCRIPTION_CATEGORY' => $work->lang['main']['description_of'] . ' ' . $work->lang['category']['of_category'],
                    'L_EDIT_THIS' => $work->lang['category']['save'],
                    'D_NAME_DIR' => $temp['folder'],
                    'D_NAME_CATEGORY' => $temp['name'],
                    'D_DESCRIPTION_CATEGORY' => $temp['description'],
                    'U_EDITED' => '?action=category&amp;subact=saveedit&amp;cat=' . $cat
                ));
            } else {
                $template->add_string_ar(array(
                    'L_NAME_BLOCK' => $work->lang['category']['error_no_category'],
                    'L_NO_CATEGORY' => $work->lang['category']['error_no_category']
                ));
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }
    } elseif ($work->check_get(
        'subact',
        true,
        true
    ) && $_GET['subact'] === 'delete' && (bool)$user->user['cat_moderate'] && $cat != 0) {
        if ($db->select('*', TBL_CATEGORY, '`id` = ' . $cat)) {
            $temp = $db->res_row();
            if ($temp) {
                if ($db->select('id', TBL_PHOTO, '`category` = ' . $cat)) {
                    $temp2 = $db->res_row();
                    if ($temp2) {
                        foreach ($temp2 as $val) {
                            $work->del_photo($val['id']);
                        }
                    }
                } else {
                    log_in_file($db->error, DIE_IF_ERROR);
                }

                $cat_dir = dir($work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp['folder']);
                while (false !== ($entry = $cat_dir->read())) {
                    if ($entry !== '.' && $entry !== '..') {
                        unlink($cat_dir->path . '/' . $entry);
                    }
                }
                $cat_dir->close();

                $cat_dir = dir($work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp['folder']);
                while (false !== ($entry = $cat_dir->read())) {
                    if ($entry !== '.' && $entry !== '..') {
                        unlink($cat_dir->path . '/' . $entry);
                    }
                }
                $cat_dir->close();

                rmdir($work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $temp['folder']);
                rmdir($work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $temp['folder']);

                if (!$db->delete(TBL_CATEGORY, '`id` = ' . $cat)) {
                    log_in_file($db->error, DIE_IF_ERROR);
                } else {
                    log_in_file($db->error, DIE_IF_ERROR);
                }
                header('Location: ' . $work->config['site_url'] . '?action=category');
                log_in_file('Hack attempt!');
            } else {
                header('Location: ' . $work->config['site_url']);
                log_in_file('Hack attempt!');
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }
    } else {
        if ($db->select('id', TBL_PHOTO, '`category` = ' . $cat, array('date_upload' => 'down'))) {
            $temp = $db->res_arr();
            $template->add_case('CATEGORY_BLOCK', 'VIEW_PIC');
            if ($temp && (bool)$user->user['pic_view']) {
                $template->add_if('ISSET_PIC', true);
                foreach ($temp as $key => $val) {
                    $temp_category = $work->create_photo('cat', $val['id']);
                    $template->add_string_ar(array(
                        'L_USER_ADD' => $work->lang['main']['user_add'],
                        'MAX_PHOTO_HEIGHT' => $work->config['temp_photo_h'] + 10,
                        'PHOTO_WIDTH' => $temp_category['width'],
                        'PHOTO_HEIGHT' => $temp_category['height'],
                        'D_DESCRIPTION_PHOTO' => $temp_category['description'],
                        'D_NAME_PHOTO' => $temp_category['name'],
                        'D_REAL_NAME_USER_ADD' => $temp_category['real_name'],
                        'D_PHOTO_RATE' => $temp_category['rate'],
                        'U_THUMBNAIL_PHOTO' => $temp_category['thumbnail_url'],
                        'U_PHOTO' => $temp_category['url'],
                        'U_PROFILE_USER_ADD' => $temp_category['url_user']
                    ), 'LIST_PIC[' . $key . ']');
                    if ($temp_category['url_user'] !== null) {
                        $template->add_if('USER_EXISTS', true, 'LIST_PIC[' . $key . ']');
                    }
                }
                if ($db->select(array('name', 'description'), TBL_CATEGORY, '`id` = ' . $cat)) {
                    $temp2 = $db->res_row();
                    if ($temp2) {
                        $template->add_if('EDIT_BLOCK', (bool)$user->user['cat_moderate']);
                        $template->add_string_ar(array(
                            'L_NAME_BLOCK' => $work->lang['category']['category'] . ' - ' . $temp2['name'],
                            'L_DESCRIPTION_BLOCK' => $temp2['description'],
                            'L_EDIT_BLOCK' => $work->lang['category']['edit'],
                            'L_DELETE_BLOCK' => $work->lang['category']['delete'],
                            'L_CONFIRM_DELETE_BLOCK' => $work->lang['category']['confirm_delete1'] . ' ' . $temp2['name'] . $work->lang['category']['confirm_delete2'],
                            'U_EDIT_BLOCK' => '?action=category&amp;subact=edit&amp;cat=' . $cat,
                            'U_DELETE_BLOCK' => '?action=category&amp;subact=delete&amp;cat=' . $cat,
                        ));
                    } else {
                        log_in_file('Unable to get the category', DIE_IF_ERROR);
                    }
                } else {
                    log_in_file($db->error, DIE_IF_ERROR);
                }
            } else {
                if ($db->select(array('name', 'description'), TBL_CATEGORY, '`id` = ' . $cat)) {
                    $temp2 = $db->res_row();
                    if ($temp2) {
                        $category_name = $work->lang['category']['category'] . ' - ' . $temp2['name'];
                        $category_description = $temp2['description'];
                        $if_edit = (bool)$user->user['cat_moderate'];
                        $pic_category = $work->lang['category']['error_no_photo'];
                    } else {
                        $category_name = $work->lang['category']['error_no_category'];
                        $category_description = '';
                        $if_edit = false;
                        $pic_category = $work->lang['category']['error_no_category'];
                    }
                } else {
                    log_in_file($db->error, DIE_IF_ERROR);
                }
                $template->add_if('EDIT_BLOCK', $if_edit);
                $template->add_string_ar(array(
                    'L_NAME_BLOCK' => $category_name,
                    'L_DESCRIPTION_BLOCK' => $category_description,
                    'L_EDIT_BLOCK' => $work->lang['category']['edit'],
                    'L_DELETE_BLOCK' => $work->lang['category']['delete'],
                    'L_CONFIRM_DELETE_BLOCK' => $work->lang['category']['confirm_delete1'] . ' ' . $category_name . $work->lang['category']['confirm_delete2'],
                    'U_EDIT_BLOCK' => '?action=category&amp;subact=edit&amp;cat=' . $cat,
                    'U_DELETE_BLOCK' => '?action=category&amp;subact=delete&amp;cat=' . $cat,
                    'L_NO_PHOTO' => $pic_category
                ));
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }
    }
} else {
    if ($work->check_get('subact', true, true) && $_GET['subact'] === 'add' && (bool)$user->user['cat_moderate']) {
        $action = 'add_category';
        $template->add_case('CATEGORY_BLOCK', 'CATEGORY_EDIT');
        $template->add_if('ISSET_CATEGORY', true);
        $template->add_string_ar(array(
            'L_NAME_BLOCK' => $work->lang['category']['add'],
            'L_NAME_DIR' => $work->lang['category']['cat_dir'],
            'L_NAME_CATEGORY' => $work->lang['main']['name_of'] . ' ' . $work->lang['category']['of_category'],
            'L_DESCRIPTION_CATEGORY' => $work->lang['main']['description_of'] . ' ' . $work->lang['category']['of_category'],
            'L_EDIT_THIS' => $work->lang['category']['added'],
            'D_NAME_DIR' => '',
            'D_NAME_CATEGORY' => '',
            'D_DESCRIPTION_CATEGORY' => '',
            'U_EDITED' => '?action=category&amp;subact=saveadd'
        ));
    } elseif ($work->check_get(
        'subact',
        true,
        true
    ) && $_GET['subact'] === 'saveadd' && (bool)$user->user['cat_moderate']) {
        if (!$work->check_post('name_dir', true, true)) {
            $name_dir = time();
        } else {
            $name_dir = $work->encodename($_POST['name_dir']);
        }

        if ($db->select('COUNT(*) AS `count_dir`', TBL_CATEGORY, '`folder` = \'' . $name_dir . '\'')) {
            $temp = $db->res_row();
            if ((isset($temp['count_dir']) && $temp['count_dir'] > 0) || is_dir(
                $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $name_dir
            ) || is_dir($work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $name_dir)) {
                $name_dir = time() . '_' . $name_dir;
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }

        if (!$work->check_post('name_category', true, true)) {
            $name_category = $work->lang['category']['no_name'] . ' (' . $name_dir . ')';
        } else {
            $name_category = $_POST['name_category'];
        }

        if (!$work->check_post('description_category', true, true)) {
            $description_category = $work->lang['category']['no_description'] . ' (' . $name_dir . ')';
        } else {
            $description_category = $_POST['description_category'];
        }

        if (mkdir($work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $name_dir, 0777) && mkdir(
            $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $name_dir,
            0777
        )) {
            $index_in_gallery = @file_get_contents(
                $work->config['site_dir'] . $work->config['gallery_folder'] . '/index.php'
            );
            if ($index_in_gallery !== false && !empty($index_in_gallery)) {
                $index_in_gallery = str_replace(
                    'gallery/index.php',
                    'gallery/' . $name_dir . '/index.php',
                    $index_in_gallery
                );
                if ($file = @fopen(
                    $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $name_dir . '/index.php',
                    'w'
                )) {
                    @fwrite($file, $index_in_gallery);
                    @fclose($file);
                }
            }
            $index_in_thumbnail = @file_get_contents(
                $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/index.php'
            );
            if ($index_in_thumbnail !== false && !empty($index_in_thumbnail)) {
                $index_in_thumbnail = str_replace(
                    'thumbnail/index.php',
                    'thumbnail/' . $name_dir . '/index.php',
                    $index_in_thumbnail
                );
                if ($file = @fopen(
                    $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $name_dir . '/index.php',
                    'w'
                )) {
                    @fwrite($file, $index_in_thumbnail);
                    @fclose($file);
                }
            }
            if ($db->insert(
                array('folder' => $name_dir, 'name' => $name_category, 'description' => $description_category),
                TBL_CATEGORY
            )) {
                $new_cat = $db->insert_id;
                if ($new_cat != 0) {
                    header('Location: ' . $work->config['site_url'] . '?action=category&amp;cat=' . $new_cat);
                    log_in_file('Hack attempt!');
                } else {
                    header('Location: ' . $work->config['site_url'] . '?action=category');
                    log_in_file('Hack attempt!');
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        } else {
            header('Location: ' . $work->config['site_url'] . '?action=category&amp;subact=add');
            log_in_file('Hack attempt!');
        }
    } else {
        if ($db->select('id', TBL_CATEGORY, '`id` != 0')) {
            $temp = $db->res_arr();
            $template->add_case('CATEGORY_BLOCK', 'VIEW_DIR');
            if ($temp) {
                foreach ($temp as $key => $val) {
                    $temp_category = $work->category($val['id'], 0);
                    $template->add_string_ar(array(
                        'D_NAME_CATEGORY' => $temp_category['name'],
                        'D_DESCRIPTION_CATEGORY' => $temp_category['description'],
                        'D_COUNT_PHOTO' => $temp_category['count_photo'],
                        'D_LAST_PHOTO' => $temp_category['last_photo'],
                        'D_TOP_PHOTO' => $temp_category['top_photo'],
                        'U_CATEGORY' => $temp_category['url_cat'],
                        'U_LAST_PHOTO' => $temp_category['url_last_photo'],
                        'U_TOP_PHOTO' => $temp_category['url_top_photo']
                    ), 'LIST_CATEGORY[' . $key . ']');
                }
                $temp_category = $work->category(0, 0);
                $template->add_string_ar(array(
                    'D_NAME_CATEGORY' => $temp_category['name'],
                    'D_DESCRIPTION_CATEGORY' => $temp_category['description'],
                    'D_COUNT_PHOTO' => $temp_category['count_photo'],
                    'D_LAST_PHOTO' => $temp_category['last_photo'],
                    'D_TOP_PHOTO' => $temp_category['top_photo'],
                    'U_CATEGORY' => $temp_category['url_cat'],
                    'U_LAST_PHOTO' => $temp_category['url_last_photo'],
                    'U_TOP_PHOTO' => $temp_category['url_top_photo']
                ), 'LIST_CATEGORY[' . ++$key . ']');

                $template->add_if('ISSET_CATEGORY', true);
                $template->add_string_ar(array(
                    'NAME_BLOCK' => $work->lang['category']['name_block'],
                    'L_NAME_CATEGORY' => $work->lang['main']['name_of'] . $work->lang['category']['of_category'],
                    'L_DESCRIPTION_CATEGORY' => $work->lang['main']['description_of'] . $work->lang['category']['of_category'],
                    'L_COUNT_PHOTO' => $work->lang['category']['count_photo'],
                    'L_LAST_PHOTO' => $work->lang['main']['last_foto'] . $work->lang['category']['of_category'],
                    'L_TOP_PHOTO' => $work->lang['main']['top_foto'] . $work->lang['category']['of_category']
                ));
            } else {
                $template->add_string_ar(array(
                    'NAME_BLOCK' => $work->lang['category']['name_block'],
                    'L_NAME_CATEGORY' => $work->lang['main']['name_of'] . $work->lang['category']['of_category'],
                    'L_DESCRIPTION_CATEGORY' => $work->lang['main']['description_of'] . $work->lang['category']['of_category'],
                    'L_COUNT_PHOTO' => $work->lang['category']['count_photo'],
                    'L_LAST_PHOTO' => $work->lang['main']['last_foto'] . $work->lang['category']['of_category'],
                    'L_TOP_PHOTO' => $work->lang['main']['top_foto'] . $work->lang['category']['of_category'],
                    'L_NO_PHOTO' => $work->lang['main']['no_category']
                ));
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }
    }
}
/// @endcond
