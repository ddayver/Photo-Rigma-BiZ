<?php

/**
 * @file        action/news.php
 * @brief       Новости сайта.
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-02-24
 * @details     Вывод и обработка новостей сайта.
 */

namespace PhotoRigma\Action;

use PhotoRigma\Classes\Work;
use RuntimeException;

use function PhotoRigma\Include\log_in_file;

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

$template->template_file = 'news.html';

$title = $work->lang['news']['title'];
$action = '';

// === Обработка параметра 'news' ===
$news = match (true) {
    !$work->check_input('_GET', 'news', [
        'isset' => true,
        'empty' => true,
        'regexp' => '/^[0-9]+$/',
        'not_zero' => true,
    ]) => false,
    default => $_GET['news'],
};

if ($news !== false) {
    // Используем подготовленные выражения для защиты от SQL-инъекций
    $db->select('*', TBL_NEWS, ['where' => '`id` = :id', 'params' => [':id' => $news]]);
    $news_data = $db->res_row();

    if (!$news_data) {
        $news = false; // Если новость не найдена, сбрасываем значение
    }
}

// === Обработка параметра 'subact' ===
$subact = match (true) {
    !$work->check_input('_GET', 'subact', [
        'isset' => true,
        'empty' => true,
    ]) => '',
    default => $_GET['subact'],
};

if ($subact === 'save') {
    // Добавление новой новости
    if ($news === false && (bool)$user->user['news_add'] === true) {
        if (!$work->check_input('_POST', 'name_post', ['isset' => true, 'empty' => true]) || !$work->check_input(
            '_POST',
            'text_post',
            ['isset' => true, 'empty' => true]
        )) {
            $subact = 'add';
        } else {
            $query_news = [
                'data_post' => date('Y-m-d'),
                'data_last_edit' => date('Y-m-d H:i:s'),
                'user_post' => $user->user['id'],
                'name_post' => trim(Work::clean_field($_POST['name_post'])),
                'text_post' => trim(Work::clean_field($_POST['text_post'])),
            ];

            // Формируем плоский массив плейсхолдеров и ассоциативный массив для вставки
            $insert_data = array_map(fn ($key) => "`$key`", array_keys($query_news)); // Экранируем имена столбцов
            $placeholders = array_map(fn ($key) => ":$key", array_keys($query_news)); // Формируем плейсхолдеры
            $params = array_combine(
                array_map(fn ($key) => ":$key", array_keys($query_news)), // Добавляем префикс ':' к каждому ключу
                $query_news // Значения остаются без изменений
            );

            // Вставка новой новости
            $db->insert(
                array_combine($insert_data, $placeholders),
                // Передаём ассоциативный массив (имена столбцов => плейсхолдеры)
                TBL_NEWS,
                '',
                ['params' => $params] // Передаём преобразованный массив параметров
            );

            $news = $db->get_last_insert_id(); // Получаем ID новой новости

            // Проверяем, что ID новости больше 0
            if ($news === 0) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка добавления новости | Данные: " . json_encode(
                        $query_news
                    )
                );
            }
        }
    } // Обновление существующей новости
    elseif ($news !== false && (bool)$user->user['news_moderate'] === true) {
        $query_news = [];
        $has_changes = false;

        // Проверка и обновление name_post
        if ($work->check_input('_POST', 'name_post', ['isset' => true, 'empty' => true])) {
            $query_news['name_post'] = trim(Work::clean_field($_POST['name_post']));
            $has_changes = true;
        }

        // Проверка и обновление text_post
        if ($work->check_input('_POST', 'text_post', ['isset' => true, 'empty' => true])) {
            $query_news['text_post'] = trim(Work::clean_field($_POST['text_post']));
            $has_changes = true;
        }

        // Если есть изменения, обновляем новость
        if ($has_changes) {
            $query_news['data_last_edit'] = date('Y-m-d H:i:s');

            // === Формирование данных для обновления с плейсхолдерами ===
            $update_data = [];
            $params = [];
            foreach ($query_news as $field => $value) {
                $placeholder = ":update_$field"; // Уникальный плейсхолдер для каждого поля
                $update_data[$field] = $placeholder; // Формируем ассоциативный массив для update
                $params[$placeholder] = $value; // Добавляем значение в параметры
            }

            // Добавляем параметр для WHERE
            $params[':news_id'] = $news;

            // === Вызов метода update с явными плейсхолдерами ===
            $db->update(
                $update_data, // Данные для обновления (ассоциативный массив с плейсхолдерами)
                TBL_NEWS,     // Таблица (строка)
                [
                    'where' => '`id` = :news_id', // Условие WHERE (строка)
                    'params' => $params          // Все параметры для prepared statements (массив)
                ]
            );

            $rows = $db->get_affected_rows(); // Проверяем количество затронутых строк
            if ($rows === 0) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка обновления новости | ID: {$news}"
                );
            }
        }
    } else {
        $news = false;
    }
}

if ($subact === 'edit' && $news !== false && ((bool)$user->user['news_moderate'] === true || ($user->user['id'] !== 0 && $user->user['id'] === $news_data['user_post']))) {
    $title = $work->lang['main']['edit_news'];

    // Получение данных новости
    $db->select('*', TBL_NEWS, ['where' => '`id` = :id', 'params' => [':id' => $news]]);
    $news_data = $db->res_row();

    if ($news_data) {
        $template->add_if_ar([
            'NEED_USER' => true,
            'USER_EXISTS' => false,
        ]);

        // Получение данных пользователя, добавившего новость
        $db->select(
            '`real_name`',
            TBL_USERS,
            ['where' => '`id` = :id', 'params' => [':id' => $news_data['user_post']]]
        );
        $user_data = $db->res_row();

        if ($user_data) {
            $template->add_if('USER_EXISTS', true);
            $template->add_string_ar([
                'D_REAL_NAME_USER_POST' => Work::clean_field($user_data['real_name']),
                'U_PROFILE_USER_POST' => sprintf(
                    '%s?action=profile&amp;subact=profile&amp;uid=%d',
                    $work->config['site_url'],
                    $news_data['user_post']
                ),
            ]);
            $user_add = $work->lang['main']['user_add'];
        } else {
            $user_add = $work->lang['main']['user_add'] . ': ' . $work->lang['main']['no_user_add'];
        }

        // Передача данных в шаблонизатор
        $template->add_case('NEWS_BLOCK', 'NEWS_SAVE');
        $template->add_string_ar([
            'NAME_BLOCK' => $work->lang['main']['edit_news'] . ' - ' . Work::clean_field($news_data['name_post']),
            'L_NAME_USER' => $user_add,
            'L_NAME_POST' => $work->lang['news']['name_post'],
            'L_TEXT_POST' => $work->lang['news']['text_post'],
            'L_SAVE_NEWS' => $work->lang['news']['edit_post'],

            'D_NAME_POST' => Work::clean_field($news_data['name_post']),
            'D_TEXT_POST' => $news_data['text_post'], // Не оборачиваем!

            'U_SAVE_NEWS' => sprintf(
                '%s?action=news&amp;subact=save&amp;news=%d',
                $work->config['site_url'],
                $news
            ),
        ]);
    } else {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Новость не найдена | ID: {$news}"
        );
    }
} elseif ($subact === 'delete' && $news !== false && ((bool)$user->user['news_moderate'] === true || ($user->user['id'] !== 0 && $user->user['id'] === $news_data['user_post']))) {
    // Проверка источника запроса (HTTP_REFERER)
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referer) && strpos($referer, 'action=news') !== false) {
        $redirect_url = sprintf('%s?action=news', $work->config['site_url']);
    } else {
        $redirect_url = $work->config['site_url'];
    }

    // Проверка на возможную атаку (хитрый момент)
    if (empty($_SERVER['HTTP_REFERER'])) {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Подозрительная попытка удаления новости | ID: {$news}"
        );
    }

    // Удаление новости с использованием подготовленных выражений
    $db->delete(TBL_NEWS, ['where' => '`id` = :id', 'params' => [':id' => $news]]);
    $rows = $db->get_affected_rows();

    if ($rows === 0) {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Новость не найдена для удаления | ID: {$news}"
        );
    }

    // Редирект после успешного удаления
    header('Location: ' . $redirect_url);
    exit;

    // Страховочное логирование (если exit не сработал)
    log_in_file(
        __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Попытка взлома | Действие: delete, Пользователь ID: {$user->session['login_id']}"
    );
} elseif ($subact === 'add' && $news === false && (bool)$user->user['news_add'] == true) {
    $title = $work->lang['news']['add_post'];
    $action = 'news_add';

    $template->add_case('NEWS_BLOCK', 'NEWS_SAVE');
    $template->add_if('NEED_USER', false);
    $template->add_string_ar(array(
        'NAME_BLOCK' => $work->lang['news']['add_post'],
        'L_NAME_USER' => '',
        'L_NAME_POST' => $work->lang['news']['name_post'],
        'L_TEXT_POST' => $work->lang['news']['text_post'],
        'L_SAVE_NEWS' => $work->lang['news']['edit_post'],

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
                'L_TITLE_NEWS_BLOCK' => $work->lang['main']['title_news'] . ' - ' . $val['name_post'],
                'L_NEWS_DATA' => $work->lang['main']['data_add'] . ': ' . $val['data_post'] . ' (' . $val['data_last_edit'] . ').',
                'L_TEXT_POST' => trim(nl2br($work->ubb($val['text_post'])))
            ), 'LAST_NEWS[0]');
            $template->add_if_ar(array(
                'USER_EXISTS' => false,
                'EDIT_SHORT' => false,
                'EDIT_LONG' => false
            ), 'LAST_NEWS[0]');
            if ($db->select('real_name', TBL_USERS, '`id` = ' . $val['user_post'])) {
                $user_add = $db->res_row();
                if ($user_add) {
                    $template->add_if('USER_EXISTS', true, 'LAST_NEWS[0]');
                    $template->add_string_ar(array(
                        'L_USER_ADD' => $work->lang['main']['user_add'],
                        'U_PROFILE_USER_POST' => $work->config['site_url'] . '?action=profile&amp;subact=profile&amp;uid=' . $val['user_post'],
                        'D_REAL_NAME_USER_POST' => $user_add['real_name']
                    ), 'LAST_NEWS[0]');
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }

            if ($user->user['news_moderate'] == true || ($user->user['id'] != 0 && $user->user['id'] == $val['user_post'])) {
                $template->add_if('EDIT_LONG', true, 'LAST_NEWS[0]');
                $template->add_string_ar(array(
                    'L_EDIT_BLOCK' => $work->lang['main']['edit_news'],
                    'L_DELETE_BLOCK' => $work->lang['main']['delete_news'],
                    'L_CONFIRM_DELETE_BLOCK' => $work->lang['main']['confirm_delete_news'] . ' ' . $val['name_post'] . '?',
                    'U_EDIT_BLOCK' => $work->config['site_url'] . '?action=news&amp;subact=edit&amp;news=' . $val['id'],
                    'U_DELETE_BLOCK' => $work->config['site_url'] . '?action=news&amp;subact=delete&amp;news=' . $val['id']
                ), 'LAST_NEWS[0]');
            }
        }
        $action = '';
        $title = $work->lang['main']['title_news'] . ' - ' . $val['name_post'];
    } else {
        $template->add_case('NEWS_BLOCK', 'LIST_NEWS');
        if (!$work->check_get('y', true, true, '^[0-9]{4}\$', true)) {
            $action = 'news';
            if ($db->select(
                'DISTINCT DATE_FORMAT(`data_last_edit`, \'%Y\') AS `year`',
                TBL_NEWS,
                false,
                array('data_last_edit' => 'up')
            )) {
                $temp = $db->res_arr();
                if (!$temp) {
                    header('Location: ' . $work->config['site_url']);
                    log_in_file('Hack attempt!');
                } else {
                    foreach ($temp as $key => $val) {
                        if ($db->select(
                            'COUNT(*) as `count_news`',
                            TBL_NEWS,
                            'DATE_FORMAT(`data_last_edit`, \'%Y\') = \'' . $val['year'] . '\''
                        )) {
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
                            'L_LIST_DATA' => $val['year'] . ' (' . $temp2 . ')',
                            'L_LIST_TITLE' => $val['year'] . ' (' . $work->lang['news']['num_news'] . ': ' . $temp2 . ')',
                            'U_LIST_URL' => $work->config['site_url'] . '?action=news&amp;y=' . $val['year']
                        ), 'LIST_NEWS[' . $key . ']');
                    }
                    $template->add_string_ar(array(
                        'L_TITLE_NEWS_BLOCK' => $work->lang['news']['news'],
                        'L_NEWS_DATA' => $work->lang['news']['news'] . ' ' . $work->lang['news']['on_years']
                    ));
                    $title = $work->lang['news']['news'] . ' ' . $work->lang['news']['on_years'];
                }
            } else {
                log_in_file($db->error, DIE_IF_ERROR);
            }
        } else {
            $year = $_GET['y'];
            if (!$work->check_get('m', true, true, '^[0-9]{2}\$', true)) {
                $action = '';
                if ($db->select(
                    'DISTINCT DATE_FORMAT(`data_last_edit`, \'%m\') AS `month`',
                    TBL_NEWS,
                    'DATE_FORMAT(`data_last_edit`, \'%Y\') = \'' . $year . '\'',
                    array('data_last_edit' => 'up')
                )) {
                    $temp = $db->res_arr();
                    if (!$temp) {
                        header('Location: ' . $work->config['site_url']);
                        log_in_file('Hack attempt!');
                    } else {
                        foreach ($temp as $key => $val) {
                            if ($db->select(
                                'COUNT(*) as `count_news`',
                                TBL_NEWS,
                                'DATE_FORMAT(`data_last_edit`, \'%Y\') = \'' . $year . '\' AND DATE_FORMAT(`data_last_edit`, \'%m\') = \'' . $val['month'] . '\''
                            )) {
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
                                'L_LIST_DATA' => $work->lang['news'][$val['month']] . ' (' . $temp2 . ')',
                                'L_LIST_TITLE' => $work->lang['news'][$val['month']] . ' (' . $work->lang['news']['num_news'] . ': ' . $temp2 . ')',
                                'U_LIST_URL' => $work->config['site_url'] . '?action=news&amp;y=' . $year . '&amp;m=' . $val['month']
                            ), 'LIST_NEWS[' . $key . ']');
                        }
                        $template->add_string_ar(array(
                            'L_TITLE_NEWS_BLOCK' => $work->lang['news']['news'],
                            'L_NEWS_DATA' => $work->lang['news']['news'] . ' ' . $work->lang['news']['on'] . ' ' . $year . ' ' . $work->lang['news']['on_month']
                        ));
                        $title = $work->lang['news']['news'] . ' ' . $work->lang['news']['on'] . ' ' . $year . ' ' . $work->lang['news']['on_month'];
                    }
                } else {
                    log_in_file($db->error, DIE_IF_ERROR);
                }
            } else {
                $month = $_GET['m'];
                $action = '';
                if ($db->select(
                    '*',
                    TBL_NEWS,
                    'DATE_FORMAT(`data_last_edit`, \'%Y\') = \'' . $year . '\' AND DATE_FORMAT(`data_last_edit`, \'%m\') = \'' . $month . '\'',
                    array('data_last_edit' => 'up')
                )) {
                    $temp = $db->res_arr();
                    if (!$temp) {
                        header('Location: ' . $work->config['site_url']);
                        log_in_file('Hack attempt!');
                    } else {
                        foreach ($temp as $key => $val) {
                            $template->add_string_ar(array(
                                'L_LIST_DATA' => $val['name_post'],
                                'L_LIST_TITLE' => substr($work->clean_field($val['text_post']), 0, 100) . '...',
                                'U_LIST_URL' => $work->config['site_url'] . '?action=news&amp;news=' . $val['id']
                            ), 'LIST_NEWS[' . $key . ']');
                        }
                        $template->add_string_ar(array(
                            'L_TITLE_NEWS_BLOCK' => $work->lang['news']['news'],
                            'L_NEWS_DATA' => $work->lang['news']['news'] . ' ' . $work->lang['news']['on'] . ' ' . $work->lang['news'][$month] . ' ' . $year . ' ' . $work->lang['news']['years']
                        ));
                        $title = $work->lang['news']['news'] . ' ' . $work->lang['news']['on'] . ' ' . $work->lang['news'][$month] . ' ' . $year . ' ' . $work->lang['news']['years'];
                    }
                } else {
                    log_in_file($db->error, DIE_IF_ERROR);
                }
            }
        }
    }
}
