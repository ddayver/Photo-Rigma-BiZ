<?php

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUndefinedClassInspection */
/**
 * @file      action/search.php
 * @brief     Обработка поисковых запросов на сайте.
 *
 * @author    Dark Dayver
 * @version   0.4.4
 * @date      2025-05-07
 * @namespace PhotoRigma\\Action
 *
 * @details   Этот файл отвечает за обработку поисковых запросов на сайте. Основные функции включают:
 *            - Поиск по пользователям, категориям, новостям и фотографиям.
 *            - Фильтрация результатов поиска в зависимости от выбранных параметров.
 *            - Отображение результатов поиска в удобном формате.
 *            - Логирование ошибок и подозрительных действий.
 *
 * @section   Search_Main_Functions Основные функции
 *            - Поиск по пользователям (по имени).
 *            - Поиск по категориям (по названию и описанию).
 *            - Поиск по новостям (по заголовку и тексту).
 *            - Поиск по фотографиям (по названию и описанию).
 *            - Логирование ошибок и подозрительных действий.
 *
 * @section   Search_Error_Handling Обработка ошибок
 *            При возникновении ошибок генерируются исключения. Поддерживаемые типы исключений:
 *            - `Random\RandomException`: При выполнении методов, использующих `random()`.
 *            - `Exception`: При выполнении прочих методов классов, функций или операций.
 *
 * @throws    Random\RandomException При выполнении методов, использующих random().
 * @throws    Exception              При выполнении прочих методов классов, функций или операций.
 *
 * @section   Search_Related_Files Связанные файлы и компоненты
 *            - Классы приложения:
 *              - @see PhotoRigma::Classes::Work
 *                     Класс используется для выполнения вспомогательных операций.
 *              - @see PhotoRigma::Classes::Database
 *                     Класс для работы с базой данных.
 *              - @see PhotoRigma::Classes::User
 *                     Класс для управления пользователями.
 *              - @see PhotoRigma::Classes::Template
 *                     Класс для работы с шаблонами.
 *            - Файлы приложения:
 *              - @see index.php Этот файл подключает action/search.php по запросу из `$_GET`.
 *
 * @note      Этот файл является частью системы PhotoRigma.
 *            Реализованы меры безопасности для предотвращения несанкционированного доступа и выполнения действий.
 *            Используются подготовленные выражения для защиты от SQL-инъекций.
 *
 * @copyright Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license   MIT License (https://opensource.org/licenses/MIT)
 *            Разрешается использовать, копировать, изменять, объединять, публиковать,
 *            распространять, сублицензировать и/или продавать копии программного обеспечения,
 *            а также разрешать лицам, которым предоставляется данное программное обеспечение,
 *            делать это при соблюдении следующих условий:
 *            - Уведомление об авторских правах и условия лицензии должны быть включены во все
 *              копии или значимые части программного обеспечения.
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
    /** @noinspection ForgottenDebugOutputInspection */
    error_log(
        date('H:i:s') . ' [ERROR] | ' . (filter_input(
            INPUT_SERVER,
            'REMOTE_ADDR',
            FILTER_VALIDATE_IP
        ) ?: 'UNKNOWN_IP') . ' | ' . __FILE__ . ' | Попытка прямого вызова файла'
    );
    die('HACK!');
}

// Устанавливаем заголовок страницы и подключаем шаблон для отображения результатов поиска
/** @noinspection PhpUnusedLocalVariableInspection */
$title = $work->lang['search']['title'];
$template->template_file = 'search.html';

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
    /** @noinspection NotOptimalIfConditionsInspection */
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
$search_text = '';

// Обработка текста запроса
if ($work->check_input('_POST', 'search_text', ['isset' => true, 'empty' => true])) {
    $search_text = trim($_POST['search_text']); // Убираем лишние пробелы

    // Проверяем CSRF-токен
    if (empty($_POST['csrf_token']) || !hash_equals(
        $user->session['csrf_token'],
        $_POST['csrf_token']
    )) {
        throw new RuntimeException(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
        );
    }
    $user->unset_property_key('session', 'csrf_token');
}

// Настройка шаблона: инициализация флагов для отображения результатов поиска
$template->add_if_array([
    'NEED_USER'     => false,
    'NEED_CATEGORY' => false,
    'NEED_NEWS'     => false,
    'NEED_PHOTO'    => false,
    'USER_FIND'     => false,
    'CATEGORY_FIND' => false,
    'NEWS_FIND'     => false,
    'PHOTO_FIND'    => false,
]);

if ($search['user']) {
    // Если выбран поиск по пользователям, устанавливаем флаг NEED_USER и заголовок для раздела
    $template->add_if('NEED_USER', true);
    $template->add_string('L_FIND_USER', $work->lang['search']['find'] . ' ' . $work->lang['search']['need_user']);

    //Администратор может видеть всех пользователей
    $options = $user->user['admin']
        ? []
        : [
            'where' => '`activation` = :activated AND `email_confirmed` = :confirmed 
                    AND `deleted_at` IS NULL AND `permanently_deleted` = :permanently_deleted',
            'params' => [
                ':activated' => 1,
                ':confirmed' => 1,
                ':permanently_deleted' => 0
            ]
        ];
    // Используем метод полнотекстового поиска.
    $find = $db->full_text_search(
        ['`id`', '`real_name`'],
        ['`login`', '`real_name`', '`email`'],
        $search_text,
        TBL_USERS,
        $options
    );

    if ($find) {
        // Если найдены пользователи, добавляем их в шаблон
        $template->add_if('USER_FIND', true);
        foreach ($find as $key => $val) {
            $template->add_string_array([
                'D_USER_FIND' => Work::clean_field($val['real_name']), // Имя пользователя
                'U_USER_FIND' => sprintf(
                    '%s?action=profile&amp;subact=profile&amp;uid=%d',
                    SITE_URL,
                    $val['id']
                ), // Ссылка на профиль пользователя
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

    // Используем метод полнотекстового поиска.
    $find = $db->full_text_search(
        ['`id`', '`name`', '`description`'],
        ['`name`', '`description`'],
        $search_text,
        TBL_CATEGORY,
        ['where' => '`id` != 0']
    );

    if ($find) {
        // Если найдены категории, добавляем их в шаблон
        $template->add_if('CATEGORY_FIND', true);
        foreach ($find as $key => $val) {
            $template->add_string_array([
                'D_CATEGORY_FIND_NAME' => Work::clean_field($val['name']),
                // Название категории
                'D_CATEGORY_FIND_DESC' => Work::clean_field($val['description']),
                // Описание категории
                'U_CATEGORY_FIND'      => sprintf(
                    '%s?action=category&amp;cat=%d',
                    SITE_URL,
                    $val['id']
                ),
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

    // Используем метод полнотекстового поиска.
    $find = $db->full_text_search(
        ['`id`', '`name_post`', '`text_post`'],
        ['`name_post`', '`text_post`'],
        $search_text,
        TBL_NEWS
    );

    if ($find) {
        // Если найдены новости, добавляем их в шаблон
        $template->add_if('NEWS_FIND', true);
        foreach ($find as $key => $val) {
            $template->add_string_array([
                'D_NEWS_FIND_NAME' => Work::clean_field($val['name_post']),
                // Заголовок новости
                'D_NEWS_FIND_DESC' => mb_substr(Work::clean_field($val['text_post']), 0, 100, 'UTF-8') . '...',
                // Краткое описание новости (первые 100 символов)
                'U_NEWS_FIND'      => sprintf('%s?action=news&amp;news=%d', SITE_URL, $val['id']),
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

    // Используем метод полнотекстового поиска.
    $find = $db->full_text_search(
        ['`id`'],
        ['`name`', '`description`'],
        $search_text,
        TBL_PHOTO
    );

    if ($find) {
        // Если найдены фотографии, добавляем их в шаблон
        $template->add_if('PHOTO_FIND', true);
        foreach ($find as $key => $val) {
            $find_photo = $work->create_photo('cat', $val['id']); // Создаем объект фотографии
            $template->add_string_array([
                'MAX_PHOTO_HEIGHT'    => (string)($work->config['temp_photo_h'] + 10), // Максимальная высота фотографии
                'PHOTO_WIDTH'         => (string)$find_photo['width'], // Ширина фотографии
                'PHOTO_HEIGHT'        => (string)$find_photo['height'], // Высота фотографии
                'D_DESCRIPTION_PHOTO' => Work::clean_field($find_photo['description']), // Описание фотографии
                'D_NAME_PHOTO'        => Work::clean_field($find_photo['name']), // Название фотографии
                'U_THUMBNAIL_PHOTO'   => $find_photo['thumbnail_url'], // URL миниатюры
                'U_PHOTO'             => $find_photo['url'], // URL фотографии
            ], 'SEARCH_PHOTO[' . $key . ']');
        }
    } else {
        // Если фотографии не найдены, добавляем сообщение об этом в шаблон
        $template->add_string('D_PHOTO_FIND', $work->lang['search']['no_find'], 'SEARCH_PHOTO[0]');
    }
}

// Экранируем значение $search_text для безопасного вывода
if (!empty($search_text)) {
    $search_text = Work::clean_field($search_text);
}

// Генерируем CSRF-токен для защиты от атак типа CSRF
$template->add_string('CSRF_TOKEN', $user->csrf_token());

// Добавляем данные в шаблон для отображения формы поиска
$template->add_string_array([
    'NAME_BLOCK'      => $work->lang['main']['search'], // Заголовок блока
    'L_SEARCH'        => $work->lang['main']['search'], // Текст кнопки "Поиск"
    'L_SEARCH_TITLE'  => $work->lang['search']['title'], // Заголовок страницы поиска
    'L_NEED_USER'     => $work->lang['search']['need_user'], // Текст "Искать пользователей"
    'L_NEED_CATEGORY' => $work->lang['search']['need_category'], // Текст "Искать категории"
    'L_NEED_NEWS'     => $work->lang['search']['need_news'], // Текст "Искать новости"
    'L_NEED_PHOTO'    => $work->lang['search']['need_photo'], // Текст "Искать фотографии"
    'D_SEARCH_TEXT'   => $search_text, // Текущее значение поля поиска
    'D_NEED_USER'     => isset($check['user']) ? 'checked="checked"' : '', // Флаг "Искать пользователей"
    'D_NEED_CATEGORY' => isset($check['category']) ? 'checked="checked"' : '', // Флаг "Искать категории"
    'D_NEED_NEWS'     => isset($check['news']) ? 'checked="checked"' : '', // Флаг "Искать новости"
    'D_NEED_PHOTO'    => isset($check['photo']) ? 'checked="checked"' : '', // Флаг "Искать фотографии"
    'U_SEARCH'        => sprintf('%s?action=search', SITE_URL), // URL для отправки формы
]);
