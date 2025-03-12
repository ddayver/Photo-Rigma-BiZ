<?php

/**
 * @file        action/search.php
 * @brief       Поиск по сайту.
 *
 * @details     Этот файл отвечает за обработку поисковых запросов на сайте. Основные функции включают:
 *              - Поиск по пользователям, категориям, новостям и фотографиям.
 *              - Фильтрация результатов поиска в зависимости от выбранных параметров.
 *              - Отображение результатов поиска в удобном формате.
 *              - Логирование ошибок и подозрительных действий.
 *
 * @section     Основные функции
 * - Поиск по пользователям (по имени).
 * - Поиск по категориям (по названию и описанию).
 * - Поиск по новостям (по заголовку и тексту).
 * - Поиск по фотографиям (по названию и описанию).
 * - Логирование ошибок и подозрительных действий.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2015-03-12
 * @namespace   PhotoRigma\Action
 *
 * @see         PhotoRigma::Classes::Work Класс используется для выполнения вспомогательных операций.
 * @see         PhotoRigma::Classes::Database Класс для работы с базой данных.
 * @see         PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
 *
 * @note        Этот файл является частью системы PhotoRigma.
 *              Реализованы меры безопасности для предотвращения несанкционированного доступа и выполнения действий.
 *              Используются подготовленные выражения для защиты от SQL-инъекций.
 *
 * @copyright   Copyright (c) 2012 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать, распространять, сублицензировать
 *              и/или продавать копии программного обеспечения, а также разрешать лицам, которым предоставляется данное
 *              программное обеспечение, делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые части
 *                программного обеспечения.
 */

namespace PhotoRigma\Action;

use PhotoRigma\Classes\Database;
use PhotoRigma\Classes\Template;
use PhotoRigma\Classes\Work;

use function PhotoRigma\Include\log_in_file;

/** @var Database $db */
/** @var Work $work */
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

$title = $work->lang['search']['title'];
$template->template_file = 'search.html';

if ($work->check_post('search_main_text', true, true) && !$work->check_post('search_text', true, true)) {
    $_POST['search_text'] = $_POST['search_main_text'];
    $_POST['search_user'] = 'true';
    $_POST['search_category'] = 'true';
    $_POST['search_news'] = 'true';
    $_POST['search_photo'] = 'true';
}

$check = [];
$search['user'] = false;
$search['category'] = false;
$search['news'] = false;
$search['photo'] = false;

if ($work->check_post('search_user', true, true) && $_POST['search_user'] === 'true' && $work->check_post(
    'search_text',
    true,
    true
)) {
    $search['user'] = true;
    $check['user'] = 'checked';
}

if ($work->check_post('search_category', true, true) && $_POST['search_category'] === 'true' && $work->check_post(
    'search_text',
    true,
    true
)) {
    $search['category'] = true;
    $check['category'] = 'checked';
}

if ($work->check_post('search_news', true, true) && $_POST['search_news'] === 'true' && $work->check_post(
    'search_text',
    true,
    true
)) {
    $search['news'] = true;
    $check['news'] = 'checked';
}

if ($work->check_post('search_photo', true, true) && $_POST['search_photo'] === 'true' && $work->check_post(
    'search_text',
    true,
    true
)) {
    $search['photo'] = true;
    $check['photo'] = 'checked';
}

if (!($search['user'] || $search['category'] || $search['news'] || $search['photo'])) {
    $check['photo'] = 'checked';
}

if ($work->check_post('search_text', true, true) && $_POST['search_text'] === '*') {
    $_POST['search_text'] = '%';
}

$template->add_if_ar(array(
    'NEED_USER' => false,
    'NEED_CATEGORY' => false,
    'NEED_NEWS' => false,
    'NEED_PHOTO' => false,
    'USER_FIND' => false,
    'CATEGORY_FIND' => false,
    'NEWS_FIND' => false,
    'PHOTO_FIND' => false
));

if ($search['user']) {
    $template->add_if('NEED_USER', true);
    $template->add_string('L_FIND_USER', $work->lang['search']['find'] . ' ' . $work->lang['search']['need_user']);
    if ($db->select('*', TBL_USERS, '`real_name` LIKE \'%' . $_POST['search_text'] . '%\'')) {
        $find = $db->res_arr();
        if ($find) {
            $template->add_if('USER_FIND', true);
            foreach ($find as $key => $val) {
                $template->add_string_ar(array(
                    'D_USER_FIND' => $val['real_name'],
                    'U_USER_FIND' => $work->config['site_url'] . '?action=profile&amp;subact=profile&amp;uid=' . $val['id']
                ), 'SEARCH_USER[' . $key . ']');
            }
        } else {
            $template->add_string('D_USER_FIND', $work->lang['search']['no_find'], 'SEARCH_USER[0]');
        }
    } else {
        log_in_file($db->error, DIE_IF_ERROR);
    }
}

if ($search['category']) {
    $template->add_if('NEED_CATEGORY', true);
    $template->add_string(
        'L_FIND_CATEGORY',
        $work->lang['search']['find'] . ' ' . $work->lang['search']['need_category']
    );
    if ($db->select(
        '*',
        TBL_CATEGORY,
        '`id` != 0 AND (`name` LIKE \'%' . $_POST['search_text'] . '%\' OR `description` LIKE \'%' . $_POST['search_text'] . '%\')'
    )) {
        $find = $db->res_arr();
        if ($find) {
            $template->add_if('CATEGORY_FIND', true);
            foreach ($find as $key => $val) {
                $template->add_string_ar(array(
                    'D_CATEGORY_FIND_NAME' => $val['name'],
                    'D_CATEGORY_FIND_DESC' => $val['description'],
                    'U_CATEGORY_FIND' => $work->config['site_url'] . '?action=category&amp;cat=' . $val['id']
                ), 'SEARCH_CATEGORY[' . $key . ']');
            }
        } else {
            $template->add_string('D_CATEGORY_FIND', $work->lang['search']['no_find'], 'SEARCH_CATEGORY[0]');
        }
    } else {
        log_in_file($db->error, DIE_IF_ERROR);
    }
}

if ($search['news']) {
    $template->add_if('NEED_NEWS', true);
    $template->add_string('L_FIND_NEWS', $work->lang['search']['find'] . ' ' . $work->lang['search']['need_news']);
    if ($db->select(
        '*',
        TBL_NEWS,
        '`name_post` LIKE \'%' . $_POST['search_text'] . '%\' OR `text_post` LIKE \'%' . $_POST['search_text'] . '%\''
    )) {
        $find = $db->res_arr();
        if ($find) {
            $template->add_if('NEWS_FIND', true);
            foreach ($find as $key => $val) {
                $template->add_string_ar(array(
                    'D_NEWS_FIND_NAME' => $val['name_post'],
                    'D_NEWS_FIND_DESC' => substr(Work::clean_field($val['text_post']), 0, 100) . '...',
                    'U_NEWS_FIND' => $work->config['site_url'] . '?action=news&amp;news=' . $val['id']
                ), 'SEARCH_NEWS[' . $key . ']');
            }
        } else {
            $template->add_string('D_NEWS_FIND', $work->lang['search']['no_find'], 'SEARCH_NEWS[0]');
        }
    } else {
        log_in_file($db->error, DIE_IF_ERROR);
    }
}

if ($search['photo']) {
    $template->add_if('NEED_PHOTO', true);
    $template->add_string('L_FIND_PHOTO', $work->lang['search']['find'] . ' ' . $work->lang['search']['need_photo']);
    if ($db->select(
        'id',
        TBL_PHOTO,
        '`name` LIKE \'%' . $_POST['search_text'] . '%\' OR `description` LIKE \'%' . $_POST['search_text'] . '%\''
    )) {
        $find = $db->res_arr();
        if ($find) {
            $template->add_if('PHOTO_FIND', true);
            foreach ($find as $key => $val) {
                $find_photo = $work->create_photo('cat', $val['id']);
                $template->add_string_ar(array(
                    'MAX_PHOTO_HEIGHT' => $work->config['temp_photo_h'] + 10,
                    'PHOTO_WIDTH' => $find_photo['width'],
                    'PHOTO_HEIGHT' => $find_photo['height'],
                    'D_DESCRIPTION_PHOTO' => $find_photo['description'],
                    'D_NAME_PHOTO' => $find_photo['name'],
                    'U_THUMBNAIL_PHOTO' => $find_photo['thumbnail_url'],
                    'U_PHOTO' => $find_photo['url']
                ), 'SEARCH_PHOTO[' . $key . ']');
            }
        } else {
            $template->add_string('D_PHOTO_FIND', $work->lang['search']['no_find'], 'SEARCH_PHOTO[0]');
        }
    } else {
        log_in_file($db->error, DIE_IF_ERROR);
    }
}

if (isset($_POST['search_text']) && $_POST['search_text'] === '%') {
    $_POST['search_text'] = '*';
}
if (isset($_POST['search_text'])) {
    $_POST['search_text'] = htmlspecialchars($_POST['search_text'], ENT_QUOTES);
}

$template->add_string_ar(array(
    'NAME_BLOCK' => $work->lang['main']['search'],
    'L_SEARCH' => $work->lang['main']['search'],
    'L_SEARCH_TITLE' => $work->lang['search']['title'],
    'L_NEED_USER' => $work->lang['search']['need_user'],
    'L_NEED_CATEGORY' => $work->lang['search']['need_category'],
    'L_NEED_NEWS' => $work->lang['search']['need_news'],
    'L_NEED_PHOTO' => $work->lang['search']['need_photo'],
    'D_SEARCH_TEXT' => $_POST['search_text'] ?? '',
    'D_NEED_USER' => isset($check['user']) ? 'checked="checked"' : '',
    'D_NEED_CATEGORY' => isset($check['category']) ? 'checked="checked"' : '',
    'D_NEED_NEWS' => isset($check['news']) ? 'checked="checked"' : '',
    'D_NEED_PHOTO' => isset($check['photo']) ? 'checked="checked"' : '',
    'U_SEARCH' => $work->config['site_url'] . '?action=search'
));
