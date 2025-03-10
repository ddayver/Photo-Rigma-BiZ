<?php

/**
 * @file        action/news.php
 * @brief       Реализует функциональность работы с новостями: добавление, редактирование, удаление и отображение.
 *
 * @throws      RuntimeException Если не удалось выполнить действие с новостью (например, добавление, редактирование или удаление).
 *              Пример сообщения: Ошибка добавления новости | Данные: json_encode($query_news).
 * @throws      RuntimeException Если новость не найдена.
 *              Пример сообщения: Новость не найдена | ID: {$news}.
 * @throws      RuntimeException Если CSRF-токен неверен.
 *              Пример сообщения: Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-02-24
 * @namespace   PhotoRigma\Action
 *
 * @details     Файл реализует функциональность работы с новостями, включая:
 *              - Добавление новой новости (при наличии прав `$user->user['news_add']`).
 *              - Редактирование существующей новости (при наличии прав `$user->user['news_moderate']` или если пользователь является автором новости).
 *              - Удаление новости (при наличии прав `$user->user['news_moderate']` или если пользователь является автором новости).
 *              - Отображение списка новостей с фильтрацией по годам и месяцам.
 *              - Защита от CSRF-атак при добавлении и редактировании новостей.
 *              - Логирование ошибок и подозрительных действий.
 *
 * @see         PhotoRigma::Classes::Work Класс используется для выполнения вспомогательных операций.
 * @see         PhotoRigma::Classes::Database Класс для работы с базой данных.
 * @see         PhotoRigma::Classes::User Класс для управления пользователями.
 * @see         PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
 * @see         file index.php Этот файл подключает action/news.php по запросу из `$_GET`.
 *
 * @note        Этот файл является частью системы PhotoRigma.
 *              Реализованы меры безопасности для предотвращения несанкционированного доступа и выполнения действий.
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

use PhotoRigma\Classes\Database;
use PhotoRigma\Classes\Template;
use PhotoRigma\Classes\User;
use PhotoRigma\Classes\Work;
use RuntimeException;

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
    if ($news === false && $user->user['news_add']) {
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
            $insert_data = array_map(static fn ($key) => "`$key`", array_keys($query_news)); // Экранируем имена столбцов
            $placeholders = array_map(static fn ($key) => ":$key", array_keys($query_news)); // Формируем плейсхолдеры
            $params = array_combine(
                array_map(static fn ($key) => ":$key", array_keys($query_news)), // Добавляем префикс ':' к каждому ключу
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
                        $query_news,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                    )
                );
            }
        }
    } // Обновление существующей новости
    elseif ($news !== false && $user->user['news_moderate']) {
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
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка обновления новости | ID: $news"
                );
            }
        }
    } else {
        $news = false;
    }
}

if ($subact === 'edit' && $news !== false && ($user->user['news_moderate'] || ($user->user['id'] !== 0 && $user->user['id'] === $news_data['user_post']))) {
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
        // Генерируем CSRF-токен для защиты от атак типа CSRF
        $template->add_string('CSRF_TOKEN', $user->csrf_token());
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
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Новость не найдена | ID: $news"
        );
    }
} elseif ($subact === 'delete' && $news !== false && ($user->user['news_moderate'] || ($user->user['id'] !== 0 && $user->user['id'] === $news_data['user_post']))) {
    // Проверка источника запроса (HTTP_REFERER)
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referer) && str_contains($referer, 'action=news')) {
        $redirect_url = sprintf('%s?action=news', $work->config['site_url']);
    } else {
        $redirect_url = $work->config['site_url'];
    }

    // Проверка на возможную атаку (хитрый момент)
    if (empty($_SERVER['HTTP_REFERER'])) {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Подозрительная попытка удаления новости | ID: $news"
        );
    }

    // Удаление новости с использованием подготовленных выражений
    $db->delete(TBL_NEWS, ['where' => '`id` = :id', 'params' => [':id' => $news]]);
    $rows = $db->get_affected_rows();

    if ($rows === 0) {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Новость не найдена для удаления | ID: $news"
        );
    }

    // Редирект после успешного удаления
    header('Location: ' . $redirect_url);
    exit;
} elseif ($subact === 'add' && $news === false && $user->user['news_add']) {
    $title = $work->lang['news']['add_post'];
    $action = 'news_add';

    $template->add_case('NEWS_BLOCK', 'NEWS_SAVE');
    $template->add_if('NEED_USER', false);
    // Генерируем CSRF-токен для защиты от атак типа CSRF
    $template->add_string('CSRF_TOKEN', $user->csrf_token());
    $template->add_string_ar([
        'NAME_BLOCK' => $work->lang['news']['add_post'],
        'L_NAME_USER' => '',
        'L_NAME_POST' => $work->lang['news']['name_post'],
        'L_TEXT_POST' => $work->lang['news']['text_post'],
        'L_SAVE_NEWS' => $work->lang['news']['edit_post'],

        'D_NAME_USER' => '',
        'D_NAME_POST' => '',
        'D_TEXT_POST' => '',

        'U_SAVE_NEWS' => sprintf('%s?action=news&amp;subact=save', $work->config['site_url']),
    ]);
} elseif ($news !== false) {
    $news_data = $work->news($news, 'id');

    if (!empty($news_data)) {
        foreach ($news_data as $key => $val) {
            $template->add_case('NEWS_BLOCK', 'LAST_NEWS');
            $template->add_string_ar([
                'L_TITLE_NEWS_BLOCK' => $work->lang['main']['title_news'] . ' - ' . Work::clean_field(
                    $val['name_post']
                ),
                'L_NEWS_DATA' => $work->lang['main']['data_add'] . ': ' . $val['data_post'] . ' (' . $val['data_last_edit'] . ').',
                'L_TEXT_POST' => trim(nl2br(Work::ubb($val['text_post'])))
            ], 'LAST_NEWS[0]');
            $template->add_if_ar([
                'USER_EXISTS' => false,
                'EDIT_SHORT' => false,
                'EDIT_LONG' => false
            ], 'LAST_NEWS[' . $key . ']');

            // Получение данных пользователя, добавившего новость
            $db->select(
                '`real_name`',
                TBL_USERS,
                ['where' => '`id` = :id', 'params' => [':id' => $val['user_post']]]
            );
            $user_data = $db->res_row();

            if ($user_data) {
                $template->add_if('USER_EXISTS', true, 'LAST_NEWS[0]');
                $template->add_string_ar([
                    'L_USER_ADD' => $work->lang['main']['user_add'],
                    'U_PROFILE_USER_POST' => sprintf(
                        '%s?action=profile&amp;subact=profile&amp;uid=%d',
                        $work->config['site_url'],
                        $val['user_post']
                    ),
                    'D_REAL_NAME_USER_POST' => Work::clean_field($user_data['real_name'])
                ], 'LAST_NEWS[0]');
            }

            // Проверка прав на редактирование
            if ($user->user['news_moderate'] || ($user->user['id'] !== 0 && $user->user['id'] === $val['user_post'])) {
                $template->add_if('EDIT_LONG', true, 'LAST_NEWS[0]');
                $template->add_string_ar([
                    'L_EDIT_BLOCK' => $work->lang['main']['edit_news'],
                    'L_DELETE_BLOCK' => $work->lang['main']['delete_news'],
                    'L_CONFIRM_DELETE_BLOCK' => $work->lang['main']['confirm_delete_news'] . ' ' . Work::clean_field(
                        $val['name_post']
                    ) . '?',
                    'L_CONFIRM_DELETE' => $work->lang['main']['delete'],
                    'L_CANCEL_DELETE' => $work->lang['main']['cancel'],
                    'U_EDIT_BLOCK' => sprintf(
                        '%s?action=news&amp;subact=edit&amp;news=%d',
                        $work->config['site_url'],
                        $val['id']
                    ),
                    'U_DELETE_BLOCK' => sprintf(
                        '%s?action=news&subact=delete&news=%d',
                        $work->config['site_url'],
                        $val['id']
                    )
                ], 'LAST_NEWS[0]');
            }
        }

        // Устанавливаем заголовок страницы
        $action = '';
        $title = $work->lang['main']['title_news'] . ' - ' . Work::clean_field(
            end($news_data)['name_post']
        );
    } else {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Новость не найдена | ID: $news"
        );
    }
} else {
    $template->add_case('NEWS_BLOCK', 'LIST_NEWS');

    // Проверка параметра 'y' (год)
    if (!$work->check_input('_GET', 'y', [
        'isset' => true,
        'empty' => true,
        'regexp' => '/^[0-9]{4}$/', // Добавлены ограничители "/"
        'not_zero' => true
    ])) {
        $action = 'news';

        // Получение списка годов
        $db->select(
            'DISTINCT DATE_FORMAT(`data_last_edit`, \'%Y\') AS `year`',
            TBL_NEWS,
            [
                'order' => 'data_last_edit ASC'
            ]
        );
        $years_list = $db->res_arr();

        if ($years_list === false) {
            header('Location: ' . $work->config['site_url']);
            exit;
        }

        foreach ($years_list as $key => $year_data) {
            // Получение количества новостей для года
            $db->select(
                'COUNT(*) AS `count_news`',
                TBL_NEWS,
                [
                    'where' => 'DATE_FORMAT(`data_last_edit`, \'%Y\') = :year',
                    'params' => [':year' => $year_data['year']]
                ]
            );
            $news_count_row = $db->res_row();
            $news_count = $news_count_row['count_news'] ?? 0;

            $template->add_string_ar([
                'L_LIST_DATA' => (string)$year_data['year'],
                'L_LIST_COUNT' => (string)$news_count,
                'L_LIST_TITLE' => $year_data['year'] . ' (' . $work->lang['news']['num_news'] . ': ' . $news_count . ')',
                'U_LIST_URL' => sprintf(
                    '%s?action=news&amp;y=%d',
                    $work->config['site_url'],
                    $year_data['year']
                )
            ], 'LIST_NEWS[' . $key . ']');
        }

        $template->add_string_ar([
            'L_TITLE_NEWS_BLOCK' => $work->lang['news']['news'],
            'L_NEWS_DATA' => $work->lang['news']['news'] . ' ' . $work->lang['news']['on_years']
        ]);
        $title = $work->lang['news']['news'] . ' ' . $work->lang['news']['on_years'];
    } else {
        $year = $_GET['y'];

        // Проверка параметра 'm' (месяц)
        if (!$work->check_input('_GET', 'm', [
            'isset' => true,
            'empty' => true,
            'regexp' => '/^[0-9]{2}$/', // Добавлены ограничители "/"
            'not_zero' => true
        ])) {
            $action = '';

            // Получение списка месяцев
            $db->select(
                'DISTINCT DATE_FORMAT(`data_last_edit`, \'%m\') AS `month`',
                TBL_NEWS,
                [
                    'where' => 'DATE_FORMAT(`data_last_edit`, \'%Y\') = :year',
                    'params' => [':year' => $year],
                    'order' => 'data_last_edit ASC'
                ]
            );
            $months_list = $db->res_arr();

            if ($months_list === false) {
                header('Location: ' . $work->config['site_url']);
                exit;
            }

            foreach ($months_list as $key => $month_data) {
                // Получение количества новостей для месяца
                $db->select(
                    'COUNT(*) AS `count_news`',
                    TBL_NEWS,
                    [
                        'where' => 'DATE_FORMAT(`data_last_edit`, \'%Y\') = :year AND DATE_FORMAT(`data_last_edit`, \'%m\') = :month',
                        'params' => [':year' => $year, ':month' => $month_data['month']]
                    ]
                );
                $news_count_row = $db->res_row();
                $news_count = $news_count_row['count_news'] ?? 0;

                $template->add_string_ar([
                    'L_LIST_DATA' => $work->lang['news'][$month_data['month']],
                    'L_LIST_COUNT' => (string)$news_count,
                    'L_LIST_TITLE' => $work->lang['news'][$month_data['month']] . ' (' . $work->lang['news']['num_news'] . ': ' . $news_count . ')',
                    'U_LIST_URL' => sprintf(
                        '%s?action=news&amp;y=%d&amp;m=%02d',
                        $work->config['site_url'],
                        $year,
                        $month_data['month']
                    )
                ], 'LIST_NEWS[' . $key . ']');
            }

            $template->add_string_ar([
                'L_TITLE_NEWS_BLOCK' => $work->lang['news']['news'],
                'L_NEWS_DATA' => $work->lang['news']['news'] . ' ' . $work->lang['news']['on'] . ' ' . $year . ' ' . $work->lang['news']['on_month']
            ]);
            $title = $work->lang['news']['news'] . ' ' . $work->lang['news']['on'] . ' ' . $year . ' ' . $work->lang['news']['on_month'];
        } else {
            $month = $_GET['m'];
            $action = '';

            // Получение списка новостей за выбранный месяц и год
            $db->select(
                '*',
                TBL_NEWS,
                [
                    'where' => 'DATE_FORMAT(`data_last_edit`, \'%Y\') = :year AND DATE_FORMAT(`data_last_edit`, \'%m\') = :month',
                    'params' => [':year' => $year, ':month' => $month],
                    'order' => 'data_last_edit ASC'
                ]
            );
            $news_list = $db->res_arr();

            if ($news_list === false) {
                header('Location: ' . $work->config['site_url']);
                exit;
            }

            foreach ($news_list as $key => $news_data) {
                $template->add_string_ar([
                    'L_LIST_DATA' => $news_data['name_post'],
                    'L_LIST_COUNT' => date('d.m.Y', strtotime($news_data['data_last_edit'])),
                    'L_LIST_TITLE' => Work::utf8_wordwrap(Work::clean_field($news_data['text_post']), 100),
                    'U_LIST_URL' => sprintf(
                        '%s?action=news&amp;news=%d',
                        $work->config['site_url'],
                        $news_data['id']
                    )
                ], 'LIST_NEWS[' . $key . ']');
            }

            $template->add_string_ar([
                'L_TITLE_NEWS_BLOCK' => $work->lang['news']['news'],
                'L_NEWS_DATA' => $work->lang['news']['news'] . ' ' . $work->lang['news']['on'] . ' ' . $work->lang['news'][$month] . ' ' . $year . ' ' . $work->lang['news']['years']
            ]);
            $title = $work->lang['news']['news'] . ' ' . $work->lang['news']['on'] . ' ' . $work->lang['news'][$month] . ' ' . $year . ' ' . $work->lang['news']['years'];
        }
    }
}
