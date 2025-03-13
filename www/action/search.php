<?php

/**
 * @file        action/search.php
 * @brief       Обработка поисковых запросов на сайте.
 *
 * @details     Этот файл отвечает за обработку поисковых запросов на сайте. Основные функции включают:
 *              - Поиск по пользователям, категориям, новостям и фотографиям.
 *              - Фильтрация результатов поиска в зависимости от выбранных параметров.
 *              - Отображение результатов поиска в удобном формате.
 *              - Логирование ошибок и подозрительных действий.
 *
 * @section     Основные функции
 *              - Поиск по пользователям (по имени).
 *              - Поиск по категориям (по названию и описанию).
 *              - Поиск по новостям (по заголовку и тексту).
 *              - Поиск по фотографиям (по названию и описанию).
 *              - Логирование ошибок и подозрительных действий.
 *
 * @todo        Полнотекстовый поиск для MySQL и PostgreSQL
 *
 *              1. Добавить поддержку полнотекстового поиска:
 *                 - Для MySQL:
 *                   - Создать FULLTEXT-индексы для полей:
 *                     - TBL_USERS: real_name
 *                     - TBL_CATEGORY: name, description
 *                     - TBL_NEWS: name_post, text_post
 *                     - TBL_PHOTO: name, description
 *                   - Использовать MATCH ... AGAINST для выполнения поиска с ранжированием результатов.
 *                 - Для PostgreSQL:
 *                   - Добавить столбцы tsvector для полей:
 *                     - TBL_USERS: real_name
 *                     - TBL_CATEGORY: name, description
 *                     - TBL_NEWS: name_post, text_post
 *                     - TBL_PHOTO: name, description
 *                   - Создать индексы GIN для tsvector.
 *                   - Настроить триггеры для автоматического обновления tsvector.
 *
 *              2. Обновить PHP-код:
 *                 - Реализовать универсальный метод для выполнения полнотекстового поиска:
 *                   - Для MySQL использовать MATCH ... AGAINST.
 *                   - Для PostgreSQL использовать @@ и to_tsquery.
 *                 - Добавить возможность сортировки результатов по релевантности (relevance).
 *                 - Учесть переключение между MySQL и PostgreSQL через проверку типа СУБД.
 *
 *              3. Оптимизировать производительность:
 *                 - Убедиться, что индексы корректно используются для ускорения поиска.
 *                 - Настроить минимальную длину слов для индексации (например, ft_min_word_len в MySQL).
 *                 - Учесть особенности языков (например, стемминг для русского или английского языка).
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-03-13
 * @namespace   PhotoRigma\Action
 *
 * @see         PhotoRigma::Classes::Work Класс для выполнения вспомогательных операций.
 * @see         PhotoRigma::Classes::Database Класс для работы с базой данных.
 * @see         PhotoRigma::Classes::Template Класс для работы шаблонами.
 *
 * @note        Этот файл является частью системы PhotoRigma.
 *              Реализованы меры безопасности для предотвращения несанкционированного доступа и выполнения действий.
 *              Используются подготовленные выражения для защиты от SQL-инъекций.
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
use PhotoRigma\Classes\Work;

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

// Устанавливаем заголовок страницы и подключаем шаблон для отображения результатов поиска
$title = $work->lang['search']['title'];
$template->template_file = 'search.html';

// Определяем константы для использования в логике
define('CHECKED_VALUE', 'checked'); // Значение для отметки выбранных типов поиска
define('TRUE_VALUE', 'true');       // Флаг для обозначения активного состояния

// Перенос текста из search_main_text в search_text, если search_text пуст
if ($work->check_input('_POST', 'search_main_text', ['isset' => true, 'empty' => true]) && !$work->check_input(
    '_POST',
    'search_text',
    ['isset' => true, 'empty' => true]
)) {
    $_POST['search_text'] = $_POST['search_main_text']; // Переносим значение
    $_POST['search_user'] = $_POST['search_category'] = $_POST['search_news'] = $_POST['search_photo'] = TRUE_VALUE;
}

// Инициализация массивов для хранения состояний поиска
$check = []; // Массив для отметки выбранных типов поиска
$search = ['user' => false, 'category' => false, 'news' => false, 'photo' => false]; // Состояния типов поиска

// Проверка типов поиска через цикл
$types = ['user', 'category', 'news', 'photo'];
foreach ($types as $type) {
    if ($work->check_input(
        '_POST',
        "search_$type",
        ['isset' => true, 'empty' => true]
    ) && $_POST["search_$type"] === TRUE_VALUE && $work->check_input(
        '_POST',
        'search_text',
        ['isset' => true, 'empty' => true]
    )) {
        $search[$type] = true; // Активируем тип поиска
        $check[$type] = CHECKED_VALUE; // Отмечаем как выбранный
    }
}

// Если ни один тип поиска не выбран, устанавливаем поиск по фотографиям по умолчанию
if (!in_array(true, $search, true)) {
    $check['photo'] = CHECKED_VALUE;
}

// Обработка текста запроса
if ($work->check_input('_POST', 'search_text', ['isset' => true, 'empty' => true])) {
    $raw_search_text = trim($_POST['search_text']); // Убираем лишние пробелы

    if ($raw_search_text === '*') {
        $search_text = '%'; // Поиск всех строк
    } elseif (strlen($raw_search_text) > 2) {
        $search_text = '%' . $raw_search_text . '%'; // Добавляем символы для частичного поиска
    } else {
        // Если текст не соответствует условиям, перенаправляем на главную страницу
        header('Location: ' . $work->config['site_url']);
        exit;
    }
}

// Настройка шаблона: инициализация флагов для отображения результатов поиска
$template->add_if_ar([
    'NEED_USER' => false,
    'NEED_CATEGORY' => false,
    'NEED_NEWS' => false,
    'NEED_PHOTO' => false,
    'USER_FIND' => false,
    'CATEGORY_FIND' => false,
    'NEWS_FIND' => false,
    'PHOTO_FIND' => false
]);

if ($search['user']) {
    // Если выбран поиск по пользователям, устанавливаем флаг NEED_USER и заголовок для раздела
    $template->add_if('NEED_USER', true);
    $template->add_string('L_FIND_USER', $work->lang['search']['find'] . ' ' . $work->lang['search']['need_user']);

    // Выполняем SQL-запрос для поиска пользователей по полю real_name
    $db->select(
        '*', // Выбираем все поля
        TBL_USERS, // Из таблицы пользователей
        [
            'where' => '`real_name` LIKE :search', // Условие: поле real_name содержит $search_text
            'params' => [':search' => $search_text] // Параметры для prepared statements
        ]
    );

    $find = $db->res_arr(); // Получаем результаты запроса
    if ($find) {
        // Если найдены пользователи, добавляем их в шаблон
        $template->add_if('USER_FIND', true);
        foreach ($find as $key => $val) {
            $template->add_string_ar([
                'D_USER_FIND' => Work::clean_field($val['real_name']), // Имя пользователя
                'U_USER_FIND' => sprintf(
                    '%s?action=profile&amp;subact=profile&amp;uid=%d',
                    $work->config['site_url'],
                    $val['id']
                ) // Ссылка на профиль пользователя
            ], 'SEARCH_USER[' . $key . ']');
        }
    } else {
        // Если пользователи не найдены, добавляем сообщение об этом в шаблон
        $template->add_string('D_USER_FIND', $work->lang['search']['no_find'], 'SEARCH_USER[0]');
    }
}

if ($search['category']) {
    // Если выбран поиск по категориям, устанавливаем флаг NEED_CATEGORY и заголовок для раздела
    $template->add_if('NEED_CATEGORY', true);
    $template->add_string(
        'L_FIND_CATEGORY',
        $work->lang['search']['find'] . ' ' . $work->lang['search']['need_category']
    );

    // Выполняем SQL-запрос для поиска категорий по полям name или description
    $db->select(
        '*', // Выбираем все поля
        TBL_CATEGORY, // Из таблицы категорий
        [
            'where' => '`id` != 0 AND (`name` LIKE :search_name OR `description` LIKE :search_desc)',
            // Условие: поля name или description содержат $search_text
            'params' => [':search_name' => $search_text, ':search_desc' => $search_text]
            // Параметры для prepared statements
        ]
    );

    $find = $db->res_arr(); // Получаем результаты запроса
    if ($find) {
        // Если найдены категории, добавляем их в шаблон
        $template->add_if('CATEGORY_FIND', true);
        foreach ($find as $key => $val) {
            $template->add_string_ar([
                'D_CATEGORY_FIND_NAME' => Work::clean_field($val['name']),
                // Название категории
                'D_CATEGORY_FIND_DESC' => Work::clean_field($val['description']),
                // Описание категории
                'U_CATEGORY_FIND' => sprintf("%s?action=category&amp;cat=%d", $work->config['site_url'], $val['id'])
                // Ссылка на категорию
            ], 'SEARCH_CATEGORY[' . $key . ']');
        }
    } else {
        // Если категории не найдены, добавляем сообщение об этом в шаблон
        $template->add_string('D_CATEGORY_FIND', $work->lang['search']['no_find'], 'SEARCH_CATEGORY[0]');
    }
}

if ($search['news']) {
    // Если выбран поиск по новостям, устанавливаем флаг NEED_NEWS и заголовок для раздела
    $template->add_if('NEED_NEWS', true);
    $template->add_string('L_FIND_NEWS', $work->lang['search']['find'] . ' ' . $work->lang['search']['need_news']);

    // Выполняем SQL-запрос для поиска новостей по полям name_post или text_post
    $db->select(
        '*', // Выбираем все поля
        TBL_NEWS, // Из таблицы новостей
        [
            'where' => '`name_post` LIKE :search_name OR `text_post` LIKE :search_text',
            // Условие: поля name_post или text_post содержат $search_text
            'params' => [':search_name' => $search_text, ':search_text' => $search_text]
            // Параметры для prepared statements
        ]
    );

    $find = $db->res_arr(); // Получаем результаты запроса
    if ($find) {
        // Если найдены новости, добавляем их в шаблон
        $template->add_if('NEWS_FIND', true);
        foreach ($find as $key => $val) {
            $template->add_string_ar([
                'D_NEWS_FIND_NAME' => Work::clean_field($val['name_post']),
                // Заголовок новости
                'D_NEWS_FIND_DESC' => mb_substr(Work::clean_field($val['text_post']), 0, 100, 'UTF-8') . '...',
                // Краткое описание новости (первые 100 символов)
                'U_NEWS_FIND' => sprintf("%s?action=news&amp;news=%d", $work->config['site_url'], $val['id'])
                // Ссылка на новость
            ], 'SEARCH_NEWS[' . $key . ']');
        }
    } else {
        // Если новости не найдены, добавляем сообщение об этом в шаблон
        $template->add_string('D_NEWS_FIND', $work->lang['search']['no_find'], 'SEARCH_NEWS[0]');
    }
}

if ($search['photo']) {
    // Если выбран поиск по фотографиям, устанавливаем флаг NEED_PHOTO и заголовок для раздела
    $template->add_if('NEED_PHOTO', true);
    $template->add_string('L_FIND_PHOTO', $work->lang['search']['find'] . ' ' . $work->lang['search']['need_photo']);

    // Выполняем SQL-запрос для поиска фотографий по полям name или description
    $db->select(
        'id', // Выбираем только id фотографий
        TBL_PHOTO, // Из таблицы фотографий
        [
            'where' => '`name` LIKE :search_name OR `description` LIKE :search_desc',
            // Условие: поля name или description содержат $search_text
            'params' => [':search_name' => $search_text, ':search_desc' => $search_text]
            // Параметры для prepared statements
        ]
    );

    $find = $db->res_arr(); // Получаем результаты запроса
    if ($find) {
        // Если найдены фотографии, добавляем их в шаблон
        $template->add_if('PHOTO_FIND', true);
        foreach ($find as $key => $val) {
            $find_photo = $work->create_photo('cat', $val['id']); // Создаем объект фотографии
            $template->add_string_ar([
                'MAX_PHOTO_HEIGHT' => (string)($work->config['temp_photo_h'] + 10), // Максимальная высота фотографии
                'PHOTO_WIDTH' => (string)$find_photo['width'], // Ширина фотографии
                'PHOTO_HEIGHT' => (string)$find_photo['height'], // Высота фотографии
                'D_DESCRIPTION_PHOTO' => Work::clean_field($find_photo['description']), // Описание фотографии
                'D_NAME_PHOTO' => Work::clean_field($find_photo['name']), // Название фотографии
                'U_THUMBNAIL_PHOTO' => $find_photo['thumbnail_url'], // URL миниатюры
                'U_PHOTO' => $find_photo['url'] // URL фотографии
            ], 'SEARCH_PHOTO[' . $key . ']');
        }
    } else {
        // Если фотографии не найдены, добавляем сообщение об этом в шаблон
        $template->add_string('D_PHOTO_FIND', $work->lang['search']['no_find'], 'SEARCH_PHOTO[0]');
    }
}

// Экранируем значение $_POST['search_text'] для безопасного вывода
if (isset($_POST['search_text'])) {
    $_POST['search_text'] = Work::clean_field($_POST['search_text']);
}

// Добавляем данные в шаблон для отображения формы поиска
$template->add_string_ar([
    'NAME_BLOCK' => $work->lang['main']['search'], // Заголовок блока
    'L_SEARCH' => $work->lang['main']['search'], // Текст кнопки "Поиск"
    'L_SEARCH_TITLE' => $work->lang['search']['title'], // Заголовок страницы поиска
    'L_NEED_USER' => $work->lang['search']['need_user'], // Текст "Искать пользователей"
    'L_NEED_CATEGORY' => $work->lang['search']['need_category'], // Текст "Искать категории"
    'L_NEED_NEWS' => $work->lang['search']['need_news'], // Текст "Искать новости"
    'L_NEED_PHOTO' => $work->lang['search']['need_photo'], // Текст "Искать фотографии"
    'D_SEARCH_TEXT' => $_POST['search_text'] ?? '', // Текущее значение поля поиска
    'D_NEED_USER' => isset($check['user']) ? 'checked="checked"' : '', // Флаг "Искать пользователей"
    'D_NEED_CATEGORY' => isset($check['category']) ? 'checked="checked"' : '', // Флаг "Искать категории"
    'D_NEED_NEWS' => isset($check['news']) ? 'checked="checked"' : '', // Флаг "Искать новости"
    'D_NEED_PHOTO' => isset($check['photo']) ? 'checked="checked"' : '', // Флаг "Искать фотографии"
    'U_SEARCH' => sprintf("%s?action=search", $work->config['site_url']) // URL для отправки формы
]);
