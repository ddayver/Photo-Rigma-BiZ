<?php

/**
 * @file        action/main.php
 * @brief       Главная страница. Формирует и выводит последние новости проекта, проверяя права пользователя на их
 *              просмотр.
 *
 * @details     Этот файл отвечает за формирование главной страницы сайта. Основная логика включает:
 *              - Загрузку последних новостей (количество определяется конфигурацией `last_news`).
 *              - Проверку прав пользователя на просмотр новостей (`$user->user['news_view']`).
 *              - Обработку ошибок при получении данных из базы данных.
 *              - Формирование ссылок для редактирования или удаления новостей (если пользователь имеет соответствующие
 *              права).
 *              - Использование всех ресурсов из пространства имён `PhotoRigma\Classes`.
 *
 * @section     Основные функции
 * - Загрузка последних новостей.
 * - Проверка прав пользователя на просмотр новостей.
 * - Формирование ссылок для редактирования или удаления новостей.
 * - Логирование ошибок и подозрительных действий.
 *
 * @throws      RuntimeException Если возникают ошибки при получении данных пользователя из базы данных.
 *              Пример сообщения: "Ошибка базы данных | Не удалось найти пользователя с ID: [ID пользователя]".
 * @throws      RuntimeException Если пользователь не имеет прав на просмотр новостей.
 *              Пример сообщения: "Пользователь не имеет прав на просмотр новостей | ID: {$user->user['id']}".
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-04-07
 * @namespace   PhotoRigma\Action
 *
 * @see         PhotoRigma::Classes::Work Класс, содержащий основные методы для работы с данными.
 * @see         PhotoRigma::Classes::Database Класс для работы с базой данных.
 * @see         PhotoRigma::Classes::User Класс для управления пользователями.
 * @see         PhotoRigma::Classes::Template Класс для работы с шаблонами.
 * @see         PhotoRigma::Classes::Work::clean_field() Метод для очистки и экранирования полей.
 * @see         PhotoRigma::Classes::Work::news() Метод для загрузки новостей.
 * @see         PhotoRigma::Include::log_in_file() Функция для логирования ошибок.
 *
 * @note        Этот файл является частью системы PhotoRigma и отвечает за формирование главной страницы сайта.
 *              Используется конфигурация `last_news` для определения количества новостей.
 *              Реализованы меры безопасности для предотвращения прямого вызова файла и SQL-инъекций.
 *
 * @copyright   Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать, распространять,
 *              сублицензировать и/или продавать копии программного обеспечения, а также разрешать лицам, которым
 *              предоставляется данное программное обеспечение, делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые
 *              части программного обеспечения.
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

// Устанавливаем файл шаблона
$template->template_file = 'main.html';

// Получаем заголовок страницы из языковых данных
$title = $work->lang['main']['main'];

// Загружаем последние новости (количество определяется конфигурацией)
$news = $work->news((int)$work->config['last_news'], 'last');

// Проверяем, есть ли новости и имеет ли пользователь право их просматривать
if (!empty($news) && $user->user['news_view']) {
    // Обрабатываем каждую новость
    foreach ($news as $key => $value) {
        // Добавляем строки для шаблона: заголовок, дата и текст новости
        $template->add_string_ar([
            'L_TITLE_NEWS_BLOCK' => $work->lang['main']['title_news'] . ' - ' . Work::clean_field($value['name_post']),
            'L_NEWS_DATA'        => $work->lang['main']['data_add'] . ': ' . $value['data_post'] . ' (' . $value['data_last_edit'] . ').',
            'L_TEXT_POST'        => trim(nl2br(Work::ubb($value['text_post']))),
        ], 'LAST_NEWS[' . $key . ']');

        // Устанавливаем флаги для условных блоков шаблона
        $template->add_if_ar([
            'USER_EXISTS' => false,
            'EDIT_SHORT'  => false,
            'EDIT_LONG'   => false,
        ], 'LAST_NEWS[' . $key . ']');

        // Проверяем, существует ли пользователь, добавивший новость.
        // Выполняем запрос с использованием плейсхолдеров.
        $db->select('`real_name`', TBL_USERS, [
            'where'  => '`id` = :user_id',
            'params' => [':user_id' => $value['user_post']],
        ]);

        // Получаем результат запроса
        $user_add = $db->res_row();
        if ($user_add === false) {
            // Если пользователь не найден, выбрасываем исключение
            throw new RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка базы данных | Не удалось найти пользователя с ID: {$value['user_post']}"
            );
        }

        // Если пользователь найден, добавляем данные о нем в шаблон
        $template->add_if('USER_EXISTS', true, 'LAST_NEWS[' . $key . ']');
        $template->add_string_ar([
            'L_USER_ADD'            => $work->lang['main']['user_add'],
            'U_PROFILE_USER_POST'   => sprintf(
                '%s?action=profile&amp;subact=profile&amp;uid=%d',
                $work->config['site_url'],
                $value['user_post']
            ),
            'D_REAL_NAME_USER_POST' => Work::clean_field($user_add['real_name']),
        ], 'LAST_NEWS[' . $key . ']');

        // Проверяем права пользователя на редактирование или удаление новости.
        // Используем match для проверки прав.
        $can_edit = match (true) {
            $user->user['news_moderate'], $user->user['id'] !== 0 && $user->user['id'] === $value['user_post'] => true,
            default                                                                                            => false,
        };

        if ($can_edit) {
            $template->add_if('EDIT_SHORT', true, 'LAST_NEWS[' . $key . ']');
            $template->add_string_ar([
                'L_EDIT_BLOCK'           => $work->lang['main']['edit_news'],
                'L_DELETE_BLOCK'         => $work->lang['main']['delete_news'],
                'L_CONFIRM_DELETE_BLOCK' => sprintf(
                    '%s %s?',
                    $work->lang['main']['confirm_delete_news'],
                    Work::clean_field($value['name_post'])
                ),
                'L_CONFIRM_DELETE'       => $work->lang['main']['delete'],
                'L_CANCEL_DELETE'        => $work->lang['main']['cancel'],
                'U_EDIT_BLOCK'           => sprintf(
                    '%s?action=news&amp;subact=edit&amp;news=%d',
                    $work->config['site_url'],
                    $value['id']
                ),
                'U_DELETE_BLOCK'         => sprintf(
                    '%s?action=news&subact=delete&news=%d',
                    $work->config['site_url'],
                    $value['id']
                ),
            ], 'LAST_NEWS[' . $key . ']');
        }
    }
} else {
    // Если новостей нет или пользователь не имеет права их просматривать, добавляем сообщение об отсутствии новостей
    $template->add_if_ar([
        'USER_EXISTS' => false,
        'EDIT_SHORT'  => false,
        'EDIT_LONG'   => false,
    ], 'LAST_NEWS[0]');

    $template->add_string_ar([
        'L_TITLE_NEWS_BLOCK' => $work->lang['main']['no_news'],
        'L_NEWS_DATA'        => '',
        'L_TEXT_POST'        => $work->lang['main']['no_news'],
    ], 'LAST_NEWS[0]');
}
