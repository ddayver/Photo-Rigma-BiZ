<?php

/**
 * @file        action/main.php
 * @brief       Главная страница. Обрабатывает логику отображения главной страницы сайта.
 *
 * @author      Dark Dayver
 * @version     0.2.0
 * @date        2012-03-28
 * @namespace   PhotoRigma\\Action
 *
 * @details     Формирует и выводит последние 5 новостей проекта. В работе используются все ресурсы из пространства имён `PhotoRigma\Classes`.
 *
 * @see         index.php Файл, который подключает main.php.
 *
 * @note        Этот файл является частью системы PhotoRigma, отвечает за формирование главной страницы сайта.
 *
 * @todo        Полный рефакторинг кода с поддержкой PHP 8.4.3, централизованной системой логирования и обработки ошибок.
 *
 * @copyright   Copyright (c) 2025 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать, распространять, сублицензировать
 *              и/или продавать копии программного обеспечения, а также разрешать лицам, которым предоставляется данное
 *              программное обеспечение, делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые части
 *                программного обеспечения.
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

include($work->config['site_dir'] . '/language/' . $work->config['language'] . '/main.php');

// Передаем языковый массив в класс Work
$work->set_lang($lang);
$template->set_lang($work->lang);

$template->template_file = 'main.html';

$title = $lang['main']['main'];
$news = $work->news((int)$work->config['last_news'], 'last');
if (!empty($news) && $user->user['news_view'] == true) {
    foreach ($news as $key => $val) {
        $template->add_string_ar(array(
                'L_TITLE_NEWS_BLOCK' => $lang['main']['title_news'] . ' - ' . $val['name_post'],
                'L_NEWS_DATA'        => $lang['main']['data_add'] . ': ' . $val['data_post'] . ' (' . $val['data_last_edit'] . ').',
                'L_TEXT_POST'        => trim(nl2br($work->ubb($val['text_post'])))
            ), 'LAST_NEWS[' . $key . ']');
        $template->add_if_ar(array(
                'USER_EXISTS' => false,
                'EDIT_SHORT'  => false,
                'EDIT_LONG'   => false
            ), 'LAST_NEWS[' . $key . ']');
        if ($db->select('real_name', TBL_USERS, ['where' => '`id` = ' . $val['user_post']])) {
            $user_add = $db->res_row();
            if ($user_add) {
                $template->add_if('USER_EXISTS', true, 'LAST_NEWS[' . $key . ']');
                $template->add_string_ar(array(
                        'L_USER_ADD'            => $lang['main']['user_add'],
                        'U_PROFILE_USER_POST'   => $work->config['site_url'] . '?action=profile&amp;subact=profile&amp;uid=' . $val['user_post'],
                        'D_REAL_NAME_USER_POST' => $user_add['real_name']
                    ), 'LAST_NEWS[' . $key . ']');
            }
        } else {
            log_in_file($db->error, DIE_IF_ERROR);
        }

        if ($user->user['news_moderate'] == true || ($user->user['id'] != 0 && $user->user['id'] == $val['user_post'])) {
            $template->add_if('EDIT_SHORT', true, 'LAST_NEWS[' . $key . ']');
            $template->add_string_ar(array(
                    'L_EDIT_BLOCK'           => $lang['main']['edit_news'],
                    'L_DELETE_BLOCK'         => $lang['main']['delete_news'],
                    'L_CONFIRM_DELETE_BLOCK' => $lang['main']['confirm_delete_news'] . ' ' . $val['name_post'] . '?',
                    'U_EDIT_BLOCK'           => $work->config['site_url'] . '?action=news&amp;subact=edit&amp;news=' . $val['id'],
                    'U_DELETE_BLOCK'         => $work->config['site_url'] . '?action=news&amp;subact=delete&amp;news=' . $val['id']
                ), 'LAST_NEWS[' . $key . ']');
        }
    }
} else {
    $template->add_if_ar(array(
        'USER_EXISTS' => false,
        'EDIT_SHORT'  => false,
        'EDIT_LONG'   => false
    ), 'LAST_NEWS[0]');
    $template->add_string_ar(array(
        'L_TITLE_NEWS_BLOCK' => $lang['main']['no_news'],
        'L_NEWS_DATA'        => '',
        'L_TEXT_POST'        => $lang['main']['no_news']
    ), 'LAST_NEWS[0]');
}
