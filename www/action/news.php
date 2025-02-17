<?php

/**
 * @file        action/news.php
 * @brief       Новости сайта.
 * @author      Dark Dayver
 * @version     0.2.0
 * @date        28/03-2012
 * @details     Вывод и обработка новостей сайта.
 */

namespace PhotoRigma\Action;

// Предотвращение прямого вызова файла
if (!defined('IN_GALLERY') || IN_GALLERY !== true) {
    error_log(
        date('H:i:s') .
        " [ERROR] | " .
        (filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP) ?: 'UNKNOWN_IP') .
        " | " . __FILE__ . " | Попытка прямого вызова файла"
    );
    die("HACK!");
}

include_once($work->config['site_dir'] . 'language/' . $work->config['language'] . '/main.php');

$template->template_file = 'news.html';

$title = $lang['news']['title'];
$action = '';

if (!$work->check_get('news', true, true, '^[0-9]+\$', true)) {
    $news = false;
} else {
    $news = $_GET['news'];
    if ($db->select('*', TBL_NEWS, '`id` = ' . $news)) {
        $temp = $db->res_row();
        if (!$temp) {
            $news = false;
        }
    } else {
        log_in_file($db->error, DIE_IF_ERROR);
    }
}

if ($work->check_get('subact', true, true)) {
    $subact = $_GET['subact'];
} else {
    $subact = '';
}

if ($subact == 'save') {
    if ($news === false && $user->user['news_add'] == true) {
        if (!$work->check_post('name_post', true, true) || !$work->check_post('text_post', true, true)) {
            $subact = 'add';
        } else {
            $query_news = array();
            $query_news['data_post'] = date('Y-m-d');
            $query_news['data_last_edit'] = date('Y-m-d H:m:s');
            $query_news['user_post'] = $user->user['id'];
            $query_news['name_post'] = trim($work->clean_field($_POST['name_post']));
            $query_news['text_post'] = trim($work->clean_field($_POST['text_post']));
            if ($db->insert($query_news, TBL_NEWS)) {
                $news = $db->insert_id;
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        }
    } elseif ($news !== false && $user->user['news_moderate'] == true) {
        if (!$work->check_post('name_post', true, true)) {
            $name_post = $temp['name_post'];
            $ch_name = false;
        } else {
            $name_post = trim($work->clean_field($_POST['name_post']));
            $ch_name = true;
        }

        if (!$work->check_post('text_post', true, true)) {
            $text_post = $temp['text_post'];
            $ch_text = false;
        } else {
            $text_post = trim($work->clean_field($_POST['text_post']));
            $ch_text = true;
        }

        if ($ch_name || $ch_text) {
            $query_news = array();
            $query_news['data_last_edit'] = date('Y-m-d H:m:s');
            $query_news['name_post'] = $name_post;
            $query_news['text_post'] = $text_post;
            if (!$db->update($query_news, TBL_NEWS, '`id` = ' . $news)) {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        }
    } else {
        $news = false;
    }
}

if ($subact == 'edit' && $news !== false && ($user->user['news_moderate'] == true || ($user->user['id'] != 0 && $user->user['id'] == $temp['user_post']))) {
    $title = $lang['main']['edit_news'];

    if ($db->select('*', TBL_NEWS, '`id` = ' . $news)) {
        $temp = $db->res_row();
        if ($temp) {
            $template->add_if_ar(array(
                'NEED_USER'   => true,
                'USER_EXISTS' => false
            ));
            if ($db->select('real_name', TBL_USERS, '`id` = ' . $temp['user_post'])) {
                $user_add = $db->res_row();
                if ($user_add) {
                    $template->add_if('USER_EXISTS', true);
                    $template->add_string_ar(array(
                        'D_REAL_NAME_USER_POST' => $user_add['real_name'],
                        'U_PROFILE_USER_POST'   => $work->config['site_url'] . '?action=profile&amp;subact=profile&amp;uid=' . $temp['user_post']
                    ));
                    $user_add = $lang['main']['user_add'];
                } else {
                    $user_add = $lang['main']['user_add'] . ': ' . $lang['main']['no_user_add'];
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }

            $template->add_case('NEWS_BLOCK', 'NEWS_SAVE');
            $template->add_string_ar(array(
                'NAME_BLOCK'  => $lang['main']['edit_news'] . ' - ' . $temp['name_post'],
                'L_NAME_USER' => $user_add,
                'L_NAME_POST' => $lang['news']['name_post'],
                'L_TEXT_POST' => $lang['news']['text_post'],
                'L_SAVE_NEWS' => $lang['news']['edit_post'],

                'D_NAME_POST' => $temp['name_post'],
                'D_TEXT_POST' => $temp['text_post'],

                'U_SAVE_NEWS' => $work->config['site_url'] . '?action=news&amp;subact=save&amp;news=' . $news
            ));
        } else {
            log_in_file('Unable to get the news', DIE_IF_ERROR);
        }
    } else {
        log_in_file($db->error, DIE_IF_ERROR);
    }
} elseif ($subact == 'delete' && $news !== false && ($user->user['news_moderate'] == true || ($user->user['id'] != 0 && $user->user['id'] == $temp['user_post']))) {
    if (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'action=news') !== false) {
        $redirect_url = $work->config['site_url'] . '?action=news';
    } else {
        $redirect_url = $work->config['site_url'];
    }
    if (!$db->delete(TBL_NEWS, '`id` = ' . $news)) {
        log_in_file($db->error, DIE_IF_ERROR);
    }
    header('Location: ' . $redirect_url);
    log_in_file('Hack attempt!');
} elseif ($subact == 'add' && $news === false && $user->user['news_add'] == true) {
    $title = $lang['news']['add_post'];
    $action = 'news_add';

    $template->add_case('NEWS_BLOCK', 'NEWS_SAVE');
    $template->add_if('NEED_USER', false);
    $template->add_string_ar(array(
        'NAME_BLOCK'  => $lang['news']['add_post'],
        'L_NAME_USER' => '',
        'L_NAME_POST' => $lang['news']['name_post'],
        'L_TEXT_POST' => $lang['news']['text_post'],
        'L_SAVE_NEWS' => $lang['news']['edit_post'],

        'D_NAME_USER' => '',
        'D_NAME_POST' => '',
        'D_TEXT_POST' => '',

        'U_SAVE_NEWS' => $work->config['site_url'] . '?action=news&amp;subact=save'
    ));
} else {
    if ($news !== false) {
        $news = $work->news($news, 'id');
        foreach ($news as $key => $val) {
            $template->add_case('NEWS_BLOCK', 'LAST_NEWS');
            $template->add_string_ar(array(
                'L_TITLE_NEWS_BLOCK' => $lang['main']['title_news'] . ' - ' . $val['name_post'],
                'L_NEWS_DATA'        => $lang['main']['data_add'] . ': ' . $val['data_post'] . ' (' . $val['data_last_edit'] . ').',
                'L_TEXT_POST'        => trim(nl2br($work->ubb($val['text_post'])))
            ), 'LAST_NEWS[0]');
            $template->add_if_ar(array(
                'USER_EXISTS' => false,
                'EDIT_SHORT'  => false,
                'EDIT_LONG'   => false
            ), 'LAST_NEWS[0]');
            if ($db->select('real_name', TBL_USERS, '`id` = ' . $val['user_post'])) {
                $user_add = $db->res_row();
                if ($user_add) {
                    $template->add_if('USER_EXISTS', true, 'LAST_NEWS[0]');
                    $template->add_string_ar(array(
                        'L_USER_ADD'            => $lang['main']['user_add'],
                        'U_PROFILE_USER_POST'   => $work->config['site_url'] . '?action=profile&amp;subact=profile&amp;uid=' . $val['user_post'],
                        'D_REAL_NAME_USER_POST' => $user_add['real_name']
                    ), 'LAST_NEWS[0]');
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }

            if ($user->user['news_moderate'] == true || ($user->user['id'] != 0 && $user->user['id'] == $val['user_post'])) {
                $template->add_if('EDIT_LONG', true, 'LAST_NEWS[0]');
                $template->add_string_ar(array(
                    'L_EDIT_BLOCK'           => $lang['main']['edit_news'],
                    'L_DELETE_BLOCK'         => $lang['main']['delete_news'],
                    'L_CONFIRM_DELETE_BLOCK' => $lang['main']['confirm_delete_news'] . ' ' . $val['name_post'] . '?',
                    'U_EDIT_BLOCK'           => $work->config['site_url'] . '?action=news&amp;subact=edit&amp;news=' . $val['id'],
                    'U_DELETE_BLOCK'         => $work->config['site_url'] . '?action=news&amp;subact=delete&amp;news=' . $val['id']
                ), 'LAST_NEWS[0]');
            }
        }
        $action = '';
        $title = $lang['main']['title_news'] . ' - ' . $val['name_post'];
    } else {
        $template->add_case('NEWS_BLOCK', 'LIST_NEWS');
        if (!$work->check_get('y', true, true, '^[0-9]{4}\$', true)) {
            $action = 'news';
            if ($db->select('DISTINCT DATE_FORMAT(`data_last_edit`, \'%Y\') AS `year`', TBL_NEWS, false, array('data_last_edit' => 'up'))) {
                $temp = $db->res_arr();
                if (!$temp) {
                    header('Location: ' . $work->config['site_url']);
                    log_in_file('Hack attempt!');
                } else {
                    foreach ($temp as $key => $val) {
                        if ($db->select('COUNT(*) as `count_news`', TBL_NEWS, 'DATE_FORMAT(`data_last_edit`, \'%Y\') = \'' . $val['year'] . '\'')) {
                            $temp2 = $db->res_row();
                            if (isset($temp2['count_news'])) {
                                $temp2 = $temp2['count_news'];
                            } else {
                                $temp2 = 0;
                            }
                        } else {
                            log_in_file($db->error, DIE_IF_ERROR);
                        }
                        $template->add_string_ar(array(
                                'L_LIST_DATA'  => $val['year'] . ' (' . $temp2 . ')',
                                'L_LIST_TITLE' => $val['year'] . ' (' . $lang['news']['num_news'] . ': ' . $temp2 . ')',
                                'U_LIST_URL'   => $work->config['site_url'] . '?action=news&amp;y=' . $val['year']
                            ), 'LIST_NEWS[' . $key . ']');
                    }
                    $template->add_string_ar(array(
                        'L_TITLE_NEWS_BLOCK' => $lang['news']['news'],
                        'L_NEWS_DATA'        => $lang['news']['news'] . ' ' . $lang['news']['on_years']
                    ));
                    $title = $lang['news']['news'] . ' ' . $lang['news']['on_years'];
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        } else {
            $year = $_GET['y'];
            if (!$work->check_get('m', true, true, '^[0-9]{2}\$', true)) {
                $action = '';
                if ($db->select('DISTINCT DATE_FORMAT(`data_last_edit`, \'%m\') AS `month`', TBL_NEWS, 'DATE_FORMAT(`data_last_edit`, \'%Y\') = \'' . $year . '\'', array('data_last_edit' => 'up'))) {
                    $temp = $db->res_arr();
                    if (!$temp) {
                        header('Location: ' . $work->config['site_url']);
                        log_in_file('Hack attempt!');
                    } else {
                        foreach ($temp as $key => $val) {
                            if ($db->select('COUNT(*) as `count_news`', TBL_NEWS, 'DATE_FORMAT(`data_last_edit`, \'%Y\') = \'' . $year . '\' AND DATE_FORMAT(`data_last_edit`, \'%m\') = \'' . $val['month'] . '\'')) {
                                $temp2 = $db->res_row();
                                if (isset($temp2['count_news'])) {
                                    $temp2 = $temp2['count_news'];
                                } else {
                                    $temp2 = 0;
                                }
                            } else {
                                log_in_file($db->error, DIE_IF_ERROR);
                            }
                            $template->add_string_ar(array(
                                    'L_LIST_DATA'  => $lang['news'][$val['month']] . ' (' . $temp2 . ')',
                                    'L_LIST_TITLE' => $lang['news'][$val['month']] . ' (' . $lang['news']['num_news'] . ': ' . $temp2 . ')',
                                    'U_LIST_URL'   => $work->config['site_url'] . '?action=news&amp;y=' . $year . '&amp;m=' . $val['month']
                                ), 'LIST_NEWS[' . $key . ']');
                        }
                        $template->add_string_ar(array(
                            'L_TITLE_NEWS_BLOCK' => $lang['news']['news'],
                            'L_NEWS_DATA'        => $lang['news']['news'] . ' ' . $lang['news']['on'] . ' ' . $year . ' ' . $lang['news']['on_month']
                        ));
                        $title = $lang['news']['news'] . ' ' . $lang['news']['on'] . ' ' . $year . ' ' . $lang['news']['on_month'];
                    }
                } else {
                    log_in_file($db->error, DIE_IF_ERROR);
                }
            } else {
                $month = $_GET['m'];
                $action = '';
                if ($db->select('*', TBL_NEWS, 'DATE_FORMAT(`data_last_edit`, \'%Y\') = \'' . $year . '\' AND DATE_FORMAT(`data_last_edit`, \'%m\') = \'' . $month . '\'', array('data_last_edit' => 'up'))) {
                    $temp = $db->res_arr();
                    if (!$temp) {
                        header('Location: ' . $work->config['site_url']);
                        log_in_file('Hack attempt!');
                    } else {
                        foreach ($temp as $key => $val) {
                            $template->add_string_ar(array(
                                    'L_LIST_DATA'  => $val['name_post'],
                                    'L_LIST_TITLE' => substr($work->clean_field($val['text_post']), 0, 100) . '...',
                                    'U_LIST_URL'   => $work->config['site_url'] . '?action=news&amp;news=' . $val['id']
                                ), 'LIST_NEWS[' . $key . ']');
                        }
                        $template->add_string_ar(array(
                            'L_TITLE_NEWS_BLOCK' => $lang['news']['news'],
                            'L_NEWS_DATA'        => $lang['news']['news'] . ' ' . $lang['news']['on'] . ' ' . $lang['news'][$month] . ' ' . $year . ' ' . $lang['news']['years']
                        ));
                        $title = $lang['news']['news'] . ' ' . $lang['news']['on'] . ' ' . $lang['news'][$month] . ' ' . $year . ' ' . $lang['news']['years'];
                    }
                } else {
                    log_in_file($db->error, DIE_IF_ERROR);
                }
            }
        }
    }
}
/// @endcond
