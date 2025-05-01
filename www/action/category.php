<?php

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUndefinedClassInspection */
/**
 * @file        action/category.php
 * @brief       Обзор и управление разделами галереи.
 *
 * @author      Dark Dayver
 * @version     0.4.2
 * @date        2025-04-27
 * @namespace   Photorigma\\Action
 *
 * @details     Этот файл отвечает за отображение, редактирование, удаление и добавление разделов в галерею.
 *              Основные функции:
 *              - Отображение списка категорий и фотографий в них.
 *              - Редактирование названия и описания категорий.
 *              - Удаление категорий и связанных с ними фотографий.
 *              - Добавление новых категорий.
 *              - Проверка прав доступа пользователя на выполнение операций.
 *
 * @section     Category_Main_Functions Основные функции
 *              - Отображение списка категорий и фотографий.
 *              - Редактирование данных категорий.
 *              - Удаление категорий и связанных фотографий.
 *              - Добавление новых категорий.
 *              - Проверка прав доступа пользователя.
 *
 * @section     Category_Error_Handling Обработка ошибок
 *              При возникновении ошибок генерируются исключения. Поддерживаемые типы исключений:
 *              - `RuntimeException`: Если возникают ошибки при выполнении операций с базой данных или файловой
 *                системой.
 *              - `Random\RandomException`: При выполнении методов, использующих `random()`.
 *              - `JsonException`: При выполнении методов, использующих JSON.
 *              - `Exception`: При выполнении прочих методов классов, функций или операций.
 *
 * @throws      RuntimeException Если возникают ошибки при выполнении операций с базой данных или файловой системой.
 *              Пример сообщения: "Не удалось получить данные категории | ID: $cat".
 * @throws      Random\RandomException При выполнении методов, использующих random().
 * @throws      JsonException При выполнении методов, использующих JSON.
 * @throws      Exception При выполнении прочих методов классов, функций или операций.
 *
 * @section     Category_Related_Files Связанные файлы и компоненты
 *              - Классы приложения:
 *                - @see PhotoRigma::Classes::Work Класс используется для выполнения вспомогательных операций.
 *                - @see PhotoRigma::Classes::Database Класс для работы с базой данных.
 *                - @see PhotoRigma::Classes::User Класс для управления пользователями.
 *                - @see PhotoRigma::Classes::Template Класс для работы с шаблонами.
 *              - Файлы приложения:
 *                - @see index.php Этот файл подключает action/category.php по запросу из `$_GET`.
 *
 * @note        Этот файл является частью системы PhotoRigma.
 *              Реализованы меры безопасности для предотвращения несанкционированного доступа к данным.
 *              Используются подготовленные выражения для защиты от SQL-инъекций.
 *
 * @copyright   Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать,
 *              распространять, сублицензировать и/или продавать копии программного обеспечения,
 *              а также разрешать лицам, которым предоставляется данное программное обеспечение,
 *              делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все
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
    error_log(
        date('H:i:s') . " [ERROR] | " . (filter_input(
            INPUT_SERVER,
            'REMOTE_ADDR',
            FILTER_VALIDATE_IP
        ) ?: 'UNKNOWN_IP') . " | " . __FILE__ . " | Попытка прямого вызова файла"
    );
    die("HACK!");
}

// Установка файла шаблона
$template->template_file = 'category.html';

// Проверка и получение параметра 'cat' из GET-запроса
if (!$work->check_input('_GET', 'cat', [
    'isset'  => true,
    'empty'  => true,
    'regexp' => '/^[a-zA-Z0-9_-]+$/', // Разрешаем только буквы, цифры, подчеркивания и дефисы
])) {
    $cat = false; // Если проверка не прошла, категория не установлена
} else {
    $cat = $_GET['cat']; // Очищаем значение от потенциально опасных символов
}

// Добавление начальных условий в шаблонизатор
$template->add_if_ar([
    'ISSET_CATEGORY' => false, // Категория не установлена
    'EDIT_BLOCK'     => false,     // Блок редактирования не активен
    'ISSET_PIC'      => false,      // Изображение не установлено
    'USER_EXISTS'    => false,    // Пользователь не существует
    'CATEGORY_EDIT'  => false,   // Редактирование категории не активно
]);

if ($cat === 'user' || $cat === 0) {
    // Проверка параметра 'id' через современный метод check_input
    if (!$work->check_input('_GET', 'id', [
        'isset'  => true,
        'empty'  => true,
        'regexp' => '/^(?:[0-9]+|curent)$/',
    ])) {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $action = 'user_category';

        // Получение списка пользователей с категорией = 0 (используем JOIN)
        // Связываем таблицы TBL_PHOTO и TBL_USERS через поле user_upload
        $db->join(
            [TBL_PHOTO . '.`user_upload`', TBL_USERS . '.`id`', TBL_USERS . '.`real_name`'], // Поля для выборки
            TBL_PHOTO, // Основная таблица
            [
                [
                    'type'  => 'LEFT', // Тип JOIN
                    'table' => TBL_USERS, // Таблица для JOIN
                    'on'    => TBL_PHOTO . '.`user_upload` = ' . TBL_USERS . '.`id`', // Условие JOIN
                ],
            ],
            [
                'where' => TBL_PHOTO . '.`category` = 0', // Условие WHERE
                'order' => TBL_PHOTO . '.`user_upload` ASC', // Сортировка ORDER BY
            ]
        );

        $users_list = $db->res_arr();
        $template->add_case('CATEGORY_BLOCK', 'VIEW_DIR');

        if ($users_list) {
            foreach ($users_list as $key => $user_data) {
                // Получаем данные о категории пользователя
                $photo_data = $work->category($user_data['user_upload'], 1);
                $template->add_string_ar([
                    'D_NAME_CATEGORY'        => $photo_data['name'],
                    'D_DESCRIPTION_CATEGORY' => $photo_data['description'],
                    'D_COUNT_PHOTO'          => (string)$photo_data['count_photo'],
                    'D_LAST_PHOTO'           => $photo_data['last_photo'],
                    'D_TOP_PHOTO'            => $photo_data['top_photo'],
                    'U_CATEGORY'             => $photo_data['url_cat'],
                    'U_LAST_PHOTO'           => $photo_data['url_last_photo'],
                    'U_TOP_PHOTO'            => $photo_data['url_top_photo'],
                ], 'LIST_CATEGORY[' . $key . ']');
            }

            // Получение данных категории с id = 0 (зарезервирована для "Пользовательских альбомов")
            $db->select(
                ['`name`', '`description`'],
                TBL_CATEGORY,
                [
                    'where' => '`id` = 0',
                ]
            );
            $category_data = $db->res_row();

            if ($category_data) {
                $template->add_if('ISSET_CATEGORY', true);
                $template->add_string_ar([
                    'NAME_BLOCK'             => $work->lang['category']['users_album'],
                    'L_NAME_CATEGORY'        => $category_data['name'],
                    'L_DESCRIPTION_CATEGORY' => $category_data['description'],
                    'L_COUNT_PHOTO'          => $work->lang['category']['count_photo'],
                    'L_LAST_PHOTO'           => $work->lang['main']['last_foto'] . $work->lang['category']['of_category'],
                    'L_TOP_PHOTO'            => $work->lang['main']['top_foto'] . $work->lang['category']['of_category'],
                ]);
            } else {
                // Если данные категории не найдены, выбрасываем исключение
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные категории пользователей"
                );
            }
        } else {
            // Если список пользователей пуст, отображаем сообщение об отсутствии данных
            $template->add_string_ar([
                'NAME_BLOCK'             => $work->lang['category']['users_album'],
                'L_NAME_CATEGORY'        => $work->lang['main']['name_of'] . $work->lang['category']['of_category'],
                'L_DESCRIPTION_CATEGORY' => $work->lang['main']['description_of'] . $work->lang['category']['of_category'],
                'L_COUNT_PHOTO'          => $work->lang['category']['count_photo'],
                'L_LAST_PHOTO'           => $work->lang['main']['last_foto'] . $work->lang['category']['of_category'],
                'L_TOP_PHOTO'            => $work->lang['main']['top_foto'] . $work->lang['category']['of_category'],
                'L_NO_PHOTO'             => $work->lang['category']['no_user_category'],
            ]);
        }
    } else {
        // Определение ID категории
        if ($_GET['id'] === 'curent' && $user->user['id'] > 0) {
            $cat_id = $user->user['id'];
        } else {
            $cat_id = $_GET['id'];
        }

        if ($cat_id === $user->user['id'] && $user->user['id'] > 0) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $action = 'you_category';
        }

        // Получение фотографий для категории = 0 и user_upload = $cat_id (используем JOIN)
        $db->join(
            [
                TBL_PHOTO . '.`id`',
                TBL_PHOTO . '.`file`',
                TBL_PHOTO . '.`name`',
                TBL_PHOTO . '.`description`',
                TBL_USERS . '.`real_name`',
            ], // Поля для выборки
            TBL_PHOTO, // Основная таблица
            [
                [
                    'type'  => 'LEFT', // Тип JOIN
                    'table' => TBL_USERS, // Таблица для JOIN
                    'on'    => TBL_PHOTO . '.`user_upload` = ' . TBL_USERS . '.`id`', // Условие JOIN
                ],
            ],
            [
                'where'    => TBL_PHOTO . '.`category` = :category AND ' . TBL_PHOTO . '.`user_upload` = :user_upload',
                // Условие WHERE
                'params'   => [
                    ':category'    => 0,
                    ':user_upload' => $cat_id,
                ],
                // Параметры для prepared statements
                'order_by' => TBL_PHOTO . '.`date_upload` DESC',
                // Сортировка ORDER BY
            ]
        );

        // Получаем результат запроса
        $photos_list = $db->res_arr();

        $template->add_case('CATEGORY_BLOCK', 'VIEW_PIC');

        if ($photos_list && $user->user['pic_view']) {
            $template->add_if('ISSET_PIC', true);

            foreach ($photos_list as $key => $photo_data) {
                // Формируем массив данных для отображения списка фотографий
                $photo_info = $work->create_photo('cat', $photo_data['id']);
                $template->add_string_ar([
                    'L_USER_ADD'           => $work->lang['main']['user_add'],
                    'MAX_PHOTO_HEIGHT'     => (string)($work->config['temp_photo_h'] + 10),
                    'PHOTO_WIDTH'          => (string)$photo_info['width'],
                    'PHOTO_HEIGHT'         => (string)$photo_info['height'],
                    'D_DESCRIPTION_PHOTO'  => $photo_info['description'],
                    'D_NAME_PHOTO'         => $photo_info['name'],
                    'D_REAL_NAME_USER_ADD' => $photo_info['real_name'],
                    'D_PHOTO_RATE'         => (string)$photo_info['rate'],
                    'U_THUMBNAIL_PHOTO'    => $photo_info['thumbnail_url'],
                    'U_PHOTO'              => $photo_info['url'],
                    'U_PROFILE_USER_ADD'   => $photo_info['url_user'],
                ], 'LIST_PIC[' . $key . ']');

                if ($photo_info['url_user'] !== null) {
                    $template->add_if('USER_EXISTS', true, 'LIST_PIC[' . $key . ']');
                }
            }

            // Получение данных категории с id = 0 (зарезервирована для "Пользовательских альбомов")
            $db->select(
                ['`name`', '`description`'],
                TBL_CATEGORY,
                [
                    'where' => '`id` = 0',
                ]
            );
            $category_data = $db->res_row();

            if ($category_data) {
                // Формируем заголовок и описание блока для отображения данных категории
                $template->add_string_ar([
                    'L_NAME_BLOCK'        => $work->lang['category']['category'] . ' - ' . $category_data['name'] . ' ' . $photos_list[0]['real_name'],
                    'L_DESCRIPTION_BLOCK' => $category_data['description'] . ' ' . $photos_list[0]['real_name'],
                ]);
            } else {
                // Если данные категории не найдены, выбрасываем исключение
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные категории пользователей | ID: $cat_id"
                );
            }
        } else {
            // Если список фотографий пуст, отображаем сообщение об отсутствии данных
            $template->add_string_ar([
                'L_NAME_BLOCK'        => $work->lang['category']['name_block'],
                'L_DESCRIPTION_BLOCK' => $work->lang['category']['error_no_category'],
                'L_NO_PHOTO'          => $work->lang['category']['error_no_photo'],
            ]);
        }
    }
} elseif ($work->check_input('_GET', 'cat', [
    'isset'  => true,   // Проверка, что параметр существует
    'empty'  => true,   // Проверка, что параметр не пустой
    'regexp' => '/^[0-9]+$/', // Регулярное выражение: только цифры
])) {
    /** @noinspection NotOptimalIfConditionsInspection */
    if ($user->user['cat_moderate'] && $work->check_input('_GET', 'subact', [
            'isset' => true,
            'empty' => true,
        ]) && $_GET['subact'] === 'saveedit') {
        // Проверяем CSRF-токен
        if (empty($_POST['csrf_token'] || $_POST['csrf_token'] === null) || !hash_equals(
            $user->session['csrf_token'],
            $_POST['csrf_token']
        )) {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный CSRF-токен | Пользователь ID: {$user->session['login_id']}"
            );
        }
        $user->unset_property_key('session', 'csrf_token');

        // Получение данных категории через подготовленный запрос
        $db->select('*', TBL_CATEGORY, [
            'where'  => '`id` = :id',
            'params' => [':id' => $cat],
        ]);
        $category_data = $db->res_row();

        if ($category_data) {
            // Проверка и получение имени категории с очисткой данных
            if (!$work->check_input('_POST', 'name_category', [
                'isset' => true,
                'empty' => true,
            ])) {
                $name_category = $category_data['name'];
            } else {
                $name_category = Work::clean_field($_POST['name_category']);
            }

            // Проверка и получение описания категории с очисткой данных
            if (!$work->check_input('_POST', 'description_category', [
                'isset' => true,
                'empty' => true,
            ])) {
                $description_category = $category_data['description'];
            } else {
                $description_category = Work::clean_field($_POST['description_category']);
            }

            // Обновление данных категории через подготовленный запрос.
            // Используем плейсхолдеры для защиты от SQL-инъекций.
            $db->update(
                ['`name`' => ':name', '`description`' => ':desc'],
                TBL_CATEGORY,
                [
                    'where'  => '`id` = :id',
                    'params' => [':id' => $cat, ':name' => $name_category, ':desc' => $description_category],
                ]
            );

            // Проверка количества затронутых строк
            $affected_rows = $db->get_affected_rows();
            if ($affected_rows === 0) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось обновить данные категории | ID: $cat"
                );
            }
        } else {
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные категории | ID: $cat"
            );
        }

        // Перенаправление пользователя после успешного обновления
        header(sprintf('Location: %s?action=category&cat=%d', $work->config['site_url'], $cat));
        exit;
    }

    /** @noinspection NotOptimalIfConditionsInspection */
    if ($user->user['cat_moderate'] && $work->check_input('_GET', 'subact', [
            'isset' => true,
            'empty' => true,
        ]) && $_GET['subact'] === 'edit') {
        // Явное преобразование $cat в целое число для безопасности
        $cat = (int)$cat;

        // Получение данных категории через подготовленный запрос
        $db->select('*', TBL_CATEGORY, [
            'where'  => '`id` = :id',
            'params' => [':id' => $cat],
        ]);
        $category_data = $db->res_row();

        if ($category_data) {
            // Добавляем шаблон для редактирования категории
            $template->add_case('CATEGORY_BLOCK', 'CATEGORY_EDIT');

            // Добавляем CSRF-токен для защиты формы от CSRF-атак
            $template->add_string('CSRF_TOKEN', $user->csrf_token());

            // Добавляем данные в шаблон
            $template->add_if_ar([
                'ISSET_CATEGORY' => true,
                'CATEGORY_EDIT'  => true,
            ]);
            $template->add_string_ar([
                'L_NAME_BLOCK'           => sprintf('%s - %s', $work->lang['category']['edit'], $category_data['name']),
                'L_NAME_DIR'             => $work->lang['category']['cat_dir'],
                'L_NAME_CATEGORY'        => sprintf(
                    '%s %s',
                    $work->lang['main']['name_of'],
                    $work->lang['category']['of_category']
                ),
                'L_DESCRIPTION_CATEGORY' => sprintf(
                    '%s %s',
                    $work->lang['main']['description_of'],
                    $work->lang['category']['of_category']
                ),
                'L_EDIT_THIS'            => $work->lang['category']['save'],
                'D_NAME_DIR'             => $category_data['folder'],
                'D_NAME_CATEGORY'        => $category_data['name'],
                'D_DESCRIPTION_CATEGORY' => $category_data['description'],
                'U_EDITED'               => sprintf(
                    '%s?action=category&subact=saveedit&cat=%d',
                    $work->config['site_url'],
                    $cat
                ),
                // Используем sprintf()
            ]);
        } else {
            // Если данные категории не найдены, добавляем сообщение об ошибке
            $template->add_string_ar([
                'L_NAME_BLOCK'  => $work->lang['category']['error_no_category'],
                'L_NO_CATEGORY' => $work->lang['category']['error_no_category'],
            ]);
        }
    } /** @noinspection NotOptimalIfConditionsInspection */ elseif ($user->user['cat_moderate'] && $work->check_input(
        '_GET',
        'subact',
        [
                'isset' => true,
                'empty' => true,
            ]
    ) && $_GET['subact'] === 'delete') {
        // Явное преобразование $cat в целое число для безопасности
        $cat = (int)$cat;

        // Получение данных категории через подготовленный запрос
        $db->select('*', TBL_CATEGORY, [
            'where'  => '`id` = :id',
            'params' => [':id' => $cat],
        ]);
        $category_data = $db->res_row();

        if ($category_data) {
            // Удаление всех фотографий, связанных с категорией
            $db->select('`id`', TBL_PHOTO, [
                'where'  => '`category` = :category',
                'params' => [':category' => $cat],
            ]);
            $photo_ids = $db->res_row();

            if ($photo_ids) {
                foreach ($photo_ids as $val) {
                    $work->del_photo($val['id']);
                }
            }

            // Удаление файлов из папки галереи
            $gallery_path = $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $category_data['folder'];
            $work->remove_directory($gallery_path);

            // Удаление файлов из папки миниатюр
            $thumbnail_path = $work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $category_data['folder'];
            $work->remove_directory($thumbnail_path);

            // Удаление категории из базы данных
            $db->delete(TBL_CATEGORY, [
                'where'  => '`id` = :id',
                'params' => [':id' => $cat],
            ]);

            // Проверка количества затронутых строк
            $affected_rows = $db->get_affected_rows();
            if ($affected_rows === 0) {
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось удалить категорию из базы данных | ID: $cat"
                );
            }

            // Перенаправление пользователя после успешного удаления
            header(sprintf('Location: %s?action=category', $work->config['site_url']));
            exit;
        }
        // Если данные категории не найдены, перенаправляем на главную страницу
        header(sprintf('Location: %s', $work->config['site_url']));
        exit;
    } else {
        // Получение фотографий категории
        $db->select('`id`', TBL_PHOTO, [
            'where'  => '`category` = :category',
            'order'  => '`date_upload` DESC',
            'params' => [':category' => $cat],
        ]);
        $photos = $db->res_arr();
        $template->add_case('CATEGORY_BLOCK', 'VIEW_PIC');

        if ($photos && $user->user['pic_view']) {
            // Добавляем шаблон для отображения фотографий
            $template->add_if('ISSET_PIC', true);

            // Проходим по всем фотографиям и добавляем их данные в шаблон
            foreach ($photos as $key => $val) {
                $photo_data = $work->create_photo('cat', $val['id']);
                $template->add_string_ar([
                    'L_USER_ADD'           => $work->lang['main']['user_add'],
                    'MAX_PHOTO_HEIGHT'     => (string)($work->config['temp_photo_h'] + 10),
                    // Максимальная высота фото
                    'PHOTO_WIDTH'          => (string)$photo_data['width'],
                    // Ширина фото
                    'PHOTO_HEIGHT'         => (string)$photo_data['height'],
                    // Высота фото
                    'D_DESCRIPTION_PHOTO'  => Work::clean_field($photo_data['description']),
                    // Описание фото
                    'D_NAME_PHOTO'         => Work::clean_field($photo_data['name']),
                    // Название фото
                    'D_REAL_NAME_USER_ADD' => Work::clean_field($photo_data['real_name']),
                    // Имя пользователя, добавившего фото
                    'D_PHOTO_RATE'         => $photo_data['rate'],
                    // Рейтинг фото
                    'U_THUMBNAIL_PHOTO'    => $photo_data['thumbnail_url'],
                    // URL миниатюры
                    'U_PHOTO'              => $photo_data['url'],
                    // URL полноразмерного фото
                    'U_PROFILE_USER_ADD'   => $photo_data['url_user'],
                    // URL профиля пользователя
                ], sprintf('LIST_PIC[%d]', $key));

                // Проверяем, существует ли ссылка на профиль пользователя
                if ($photo_data['url_user'] !== null) {
                    $template->add_if('USER_EXISTS', true, sprintf('LIST_PIC[%d]', $key));
                }
            }

            // Получение данных категории
            $db->select(['`name`', '`description`'], TBL_CATEGORY, [
                'where'  => '`id` = :id',
                'params' => [':id' => $cat],
            ]);
            $category_data = $db->res_row();

            if ($category_data) {
                // Добавляем данные категории в шаблон
                $template->add_if('EDIT_BLOCK', (bool)$user->user['cat_moderate']);
                $template->add_string_ar([
                    'L_NAME_BLOCK'           => sprintf(
                        '%s - %s',
                        $work->lang['category']['category'],
                        Work::clean_field($category_data['name'])
                    ),
                    // Название категории
                    'L_DESCRIPTION_BLOCK'    => Work::clean_field($category_data['description']),
                    // Описание категории
                    'L_EDIT_BLOCK'           => $work->lang['category']['edit'],
                    // Текст кнопки "Редактировать"
                    'L_DELETE_BLOCK'         => $work->lang['category']['delete'],
                    // Текст кнопки "Удалить"
                    'L_CONFIRM_DELETE_BLOCK' => sprintf(
                        '%s %s%s',
                        $work->lang['category']['confirm_delete1'],
                        Work::clean_field($category_data['name']),
                        $work->lang['category']['confirm_delete2']
                    ),
                    'L_CONFIRM_DELETE'       => $work->lang['main']['delete'],
                    'L_CANCEL_DELETE'        => $work->lang['main']['cancel'],
                    // Подтверждение удаления
                    'U_EDIT_BLOCK'           => sprintf(
                        '%s?action=category&subact=edit&cat=%d',
                        $work->config['site_url'],
                        $cat
                    ),
                    // Ссылка на редактирование категории
                    'U_DELETE_BLOCK'         => sprintf(
                        '%s?action=category&subact=delete&cat=%d',
                        $work->config['site_url'],
                        $cat
                    ),
                    // Ссылка на удаление категории
                ]);
            } else {
                // Если данные категории не найдены, выбрасываем исключение
                throw new RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить данные категории | ID: $cat"
                );
            }
        } else {
            // Если фотографий нет, получаем данные категории
            $db->select(['`name`', '`description`'], TBL_CATEGORY, [
                'where'  => '`id` = :id',
                'params' => [':id' => $cat],
            ]);
            $category_data = $db->res_row();

            if ($category_data) {
                // Формируем данные для отображения ошибки "Нет фотографий"
                $category_name = sprintf(
                    '%s - %s',
                    $work->lang['category']['category'],
                    Work::clean_field($category_data['name'])
                );
                $category_description = Work::clean_field($category_data['description']);
                $if_edit = (bool)$user->user['cat_moderate'];
                $pic_category = $work->lang['category']['error_no_photo'];
            } else {
                // Если данные категории не найдены, формируем сообщение об ошибке
                $category_name = $work->lang['category']['error_no_category'];
                $category_description = '';
                $if_edit = false;
                $pic_category = $work->lang['category']['error_no_category'];
            }

            // Добавляем данные в шаблон
            $template->add_if('EDIT_BLOCK', $if_edit);
            $template->add_string_ar([
                'L_NAME_BLOCK'           => $category_name,
                // Название категории
                'L_DESCRIPTION_BLOCK'    => $category_description,
                // Описание категории
                'L_EDIT_BLOCK'           => $work->lang['category']['edit'],
                // Текст кнопки "Редактировать"
                'L_DELETE_BLOCK'         => $work->lang['category']['delete'],
                // Текст кнопки "Удалить"
                'L_CONFIRM_DELETE_BLOCK' => sprintf(
                    '%s %s%s',
                    $work->lang['category']['confirm_delete1'],
                    $category_name,
                    $work->lang['category']['confirm_delete2']
                ),
                'L_CONFIRM_DELETE'       => $work->lang['main']['delete'],
                'L_CANCEL_DELETE'        => $work->lang['main']['cancel'],
                // Подтверждение удаления
                'U_EDIT_BLOCK'           => sprintf(
                    '%s?action=category&subact=edit&cat=%d',
                    $work->config['site_url'],
                    $cat
                ),
                // Ссылка на редактирование категории
                'U_DELETE_BLOCK'         => sprintf(
                    '%s?action=category&subact=delete&cat=%d',
                    $work->config['site_url'],
                    $cat
                ),
                // Ссылка на удаление категории
                'L_NO_PHOTO'             => $pic_category,
                // Сообщение об отсутствии фотографий
            ]);
        }
    }
} /** @noinspection NotOptimalIfConditionsInspection */ elseif ($user->user['cat_moderate'] && $work->check_input(
    '_GET',
    'subact',
    [
            'isset' => true,
            'empty' => true,
        ]
) && $_GET['subact'] === 'add') {
    // Устанавливаем действие "добавление категории"
    /** @noinspection PhpUnusedLocalVariableInspection */
    $action = 'add_category';

    // Добавляем шаблон для редактирования категории
    $template->add_case('CATEGORY_BLOCK', 'CATEGORY_EDIT');
    $template->add_if('ISSET_CATEGORY', true);

    // Добавляем CSRF-токен для защиты формы от CSRF-атак
    $template->add_string('CSRF_TOKEN', $user->csrf_token());

    // Добавляем данные в шаблон
    $template->add_string_ar([
        'L_NAME_BLOCK'           => $work->lang['category']['add'],
        // Заголовок блока
        'L_NAME_DIR'             => $work->lang['category']['cat_dir'],
        // Название директории
        'L_NAME_CATEGORY'        => sprintf(
            '%s %s',
            $work->lang['main']['name_of'],
            $work->lang['category']['of_category']
        ),
        // Название категории
        'L_DESCRIPTION_CATEGORY' => sprintf(
            '%s %s',
            $work->lang['main']['description_of'],
            $work->lang['category']['of_category']
        ),
        // Описание категории
        'L_EDIT_THIS'            => $work->lang['category']['added'],
        // Текст кнопки "Добавить"
        'D_NAME_DIR'             => '',
        // Имя директории (пустое по умолчанию)
        'D_NAME_CATEGORY'        => '',
        // Имя категории (пустое по умолчанию)
        'D_DESCRIPTION_CATEGORY' => '',
        // Описание категории (пустое по умолчанию)
        'U_EDITED'               => sprintf('%s?action=category&subact=saveadd', $work->config['site_url']),
        // URL для отправки формы
    ]);
} /** @noinspection NotOptimalIfConditionsInspection */ elseif ($user->user['cat_moderate'] && $work->check_input(
    '_GET',
    'subact',
    [
            'isset' => true,
            'empty' => true,
        ]
) && $_GET['subact'] === 'saveadd') {
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

    // Определяем имя директории
    if (!$work->check_input('_POST', 'name_dir', [
        'isset' => true,
        'empty' => true,
    ])) {
        $directory_name = time();
    } else {
        $directory_name = Work::encodename(Work::clean_field($_POST['name_dir']));
    }

    // Проверяем уникальность имени директории
    $db->select('COUNT(*) AS `count_dir`', TBL_CATEGORY, [
        'where'  => '`folder` = :folder',
        'params' => [':folder' => $directory_name],
    ]);
    $directory_count_data = $db->res_row();

    if ((isset($directory_count_data['count_dir']) && $directory_count_data['count_dir'] > 0) || is_dir(
        $work->config['site_dir'] . $work->config['gallery_folder'] . '/' . $directory_name
    ) || is_dir($work->config['site_dir'] . $work->config['thumbnail_folder'] . '/' . $directory_name)) {
        $directory_name = time() . '_' . $directory_name;
    }

    // Определяем название категории
    if (!$work->check_input('_POST', 'name_category', [
        'isset' => true,
        'empty' => true,
    ])) {
        $category_name = sprintf('%s (%s)', $work->lang['category']['no_name'], $directory_name);
    } else {
        $category_name = Work::clean_field($_POST['name_category']);
    }

    // Определяем описание категории
    if (!$work->check_input('_POST', 'description_category', [
        'isset' => true,
        'empty' => true,
    ])) {
        $category_description = sprintf('%s (%s)', $work->lang['category']['no_description'], $directory_name);
    } else {
        $category_description = Work::clean_field($_POST['description_category']);
    }

    // Создаем директории и копируем index.php
    $work->create_directory($directory_name);

    // Добавляем категорию в базу данных
    $db->insert([
        '`folder`'      => ':folder',
        '`name`'        => ':name',
        '`description`' => ':desc',
    ], TBL_CATEGORY, '', [
        'params' => [
            ':folder' => $directory_name,
            ':name'   => $category_name,
            ':desc'   => $category_description,
        ],
    ]);

    // Получаем ID новой категории
    $new_category_id = $db->get_last_insert_id();
    if ($new_category_id === 0) {
        throw new RuntimeException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось добавить категорию в базу данных | Имя директории: $directory_name"
        );
    }
    // Перенаправляем пользователя после успешного добавления
    header(sprintf('Location: %s?action=category&cat=%d', $work->config['site_url'], $new_category_id));
    exit;
} else {
    // Получаем список категорий
    $db->select('`id`', TBL_CATEGORY, [
        'where'  => '`id` != :id',
        'params' => [':id' => 0],
    ]);
    $categories = $db->res_arr();
    $template->add_case('CATEGORY_BLOCK', 'VIEW_DIR');

    if ($categories) {
        $key = 0;
        foreach ($categories as $key => $val) {
            $category_data = $work->category($val['id']);
            $template->add_string_ar([
                'D_NAME_CATEGORY'        => Work::clean_field($category_data['name']),
                'D_DESCRIPTION_CATEGORY' => Work::clean_field($category_data['description']),
                'D_COUNT_PHOTO'          => (string)$category_data['count_photo'],
                'D_LAST_PHOTO'           => $category_data['last_photo'],
                'D_TOP_PHOTO'            => $category_data['top_photo'],
                'U_CATEGORY'             => $category_data['url_cat'],
                'U_LAST_PHOTO'           => $category_data['url_last_photo'],
                'U_TOP_PHOTO'            => $category_data['url_top_photo'],
            ], 'LIST_CATEGORY[' . $key . ']');
        }

        // Добавляем данные для категории "Все фото"
        $all_photos_category = $work->category();
        if ($all_photos_category['user_upload_count_data'] > 0) {
            $template->add_string_ar([
                'D_NAME_CATEGORY'        => Work::clean_field($all_photos_category['name']),
                'D_DESCRIPTION_CATEGORY' => Work::clean_field($all_photos_category['description']),
                'D_COUNT_PHOTO'          => (string)$all_photos_category['count_photo'],
                'D_LAST_PHOTO'           => $all_photos_category['last_photo'],
                'D_TOP_PHOTO'            => $all_photos_category['top_photo'],
                'U_CATEGORY'             => $all_photos_category['url_cat'],
                'U_LAST_PHOTO'           => $all_photos_category['url_last_photo'],
                'U_TOP_PHOTO'            => $all_photos_category['url_top_photo'],
            ], 'LIST_CATEGORY[' . (++$key) . ']');
        }

        $template->add_if('ISSET_CATEGORY', true);
        $template->add_string_ar([
            'NAME_BLOCK'             => $work->lang['category']['name_block'],
            'L_NAME_CATEGORY'        => sprintf(
                '%s %s',
                $work->lang['main']['name_of'],
                $work->lang['category']['of_category']
            ),
            'L_DESCRIPTION_CATEGORY' => sprintf(
                '%s %s',
                $work->lang['main']['description_of'],
                $work->lang['category']['of_category']
            ),
            'L_COUNT_PHOTO'          => $work->lang['category']['count_photo'],
            'L_LAST_PHOTO'           => sprintf(
                '%s %s',
                $work->lang['main']['last_foto'],
                $work->lang['category']['of_category']
            ),
            'L_TOP_PHOTO'            => sprintf(
                '%s %s',
                $work->lang['main']['top_foto'],
                $work->lang['category']['of_category']
            ),
        ]);
    } else {
        $template->add_string_ar([
            'NAME_BLOCK'             => $work->lang['category']['name_block'],
            'L_NAME_CATEGORY'        => sprintf(
                '%s %s',
                $work->lang['main']['name_of'],
                $work->lang['category']['of_category']
            ),
            'L_DESCRIPTION_CATEGORY' => sprintf(
                '%s %s',
                $work->lang['main']['description_of'],
                $work->lang['category']['of_category']
            ),
            'L_COUNT_PHOTO'          => $work->lang['category']['count_photo'],
            'L_LAST_PHOTO'           => sprintf(
                '%s %s',
                $work->lang['main']['last_foto'],
                $work->lang['category']['of_category']
            ),
            'L_TOP_PHOTO'            => sprintf(
                '%s %s',
                $work->lang['main']['top_foto'],
                $work->lang['category']['of_category']
            ),
            'L_NO_PHOTO'             => $work->lang['main']['no_category'],
        ]);
    }
}
