-- phpMyAdmin SQL Dump
-- version 5.2.2-1.fc42
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Май 11 2025 г., 21:49
-- Версия сервера: 10.11.11-MariaDB
-- Версия PHP: 8.4.7

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `photorigma`
--
CREATE DATABASE IF NOT EXISTS `photorigma` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `photorigma`;

-- --------------------------------------------------------

--
-- Структура таблицы `category`
--

DROP TABLE IF EXISTS `category`;
CREATE TABLE IF NOT EXISTS `category` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор',
  `folder` varchar(50) NOT NULL COMMENT 'Имя папки раздела',
  `name` varchar(50) NOT NULL COMMENT 'Название раздела',
  `description` varchar(250) NOT NULL COMMENT 'Описание раздела',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Таблица разделов';

--
-- ССЫЛКИ ТАБЛИЦЫ `category`:
--

--
-- Дамп данных таблицы `category`
--

INSERT INTO `category` (`id`, `folder`, `name`, `description`) VALUES
(0, 'user', 'Пользовательский альбом', 'Персональный пользовательский альбом');

--
-- Триггеры `category`
--
DROP TRIGGER IF EXISTS `trg_prevent_deletion_category`;
DELIMITER $$
CREATE TRIGGER `trg_prevent_deletion_category` BEFORE DELETE ON `category` FOR EACH ROW BEGIN
    -- Проверяем, является ли id служебным
    IF OLD.id = 0 THEN
        -- Генерируем пустое действие (игнорируем удаление)
        SIGNAL SQLSTATE '01000';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `change_timestamp`
--

DROP TABLE IF EXISTS `change_timestamp`;
CREATE TABLE IF NOT EXISTS `change_timestamp` (
  `table_name` varchar(255) NOT NULL COMMENT 'Имя таблицы',
  `last_update` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Время последнего обновления',
  PRIMARY KEY (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Хранение даты последних изменений в таблицах';

--
-- ССЫЛКИ ТАБЛИЦЫ `change_timestamp`:
--

--
-- Дамп данных таблицы `change_timestamp`
--

INSERT INTO `change_timestamp` (`table_name`, `last_update`) VALUES
('config', '2025-05-10 22:59:57');

-- --------------------------------------------------------

--
-- Структура таблицы `config`
--

DROP TABLE IF EXISTS `config`;
CREATE TABLE IF NOT EXISTS `config` (
  `name` varchar(50) NOT NULL COMMENT 'Имя параметра',
  `value` varchar(255) NOT NULL COMMENT 'Значение параметра',
  PRIMARY KEY (`name`),
  KEY `value` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Таблица параметров';

--
-- ССЫЛКИ ТАБЛИЦЫ `config`:
--

--
-- Дамп данных таблицы `config`
--

INSERT INTO `config` (`name`, `value`) VALUES
('max_avatar_h', '100'),
('max_avatar_w', '100'),
('max_rate', '2'),
('temp_photo_h', '200'),
('temp_photo_w', '200'),
('copyright_year', '2008-2025'),
('left_panel', '250'),
('right_panel', '250'),
('max_photo_h', '480'),
('best_user', '5'),
('last_news', '5'),
('max_file_size', '5M'),
('max_photo_w', '640'),
('time_user_online', '900'),
('gal_width', '95%'),
('theme', 'default'),
('copyright_url', 'https://rigma.biz/'),
('title_name', 'Rigma Foto'),
('meta_description', 'Rigma.BiZ - фотогалерея Gold Rigma'),
('meta_keywords', 'Rigma.BiZ photo gallery Gold Rigma'),
('language', 'russian'),
('timezone', 'UTC'),
('copyright_text', 'Проекты Rigma.BiZ'),
('title_description', 'Фотогалерея Rigma и Co');

--
-- Триггеры `config`
--
DROP TRIGGER IF EXISTS `trg_config_delete`;
DELIMITER $$
CREATE TRIGGER `trg_config_delete` AFTER DELETE ON `config` FOR EACH ROW UPDATE change_timestamp
SET last_update = CURRENT_TIMESTAMP
WHERE table_name = 'config'
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_config_insert`;
DELIMITER $$
CREATE TRIGGER `trg_config_insert` AFTER INSERT ON `config` FOR EACH ROW UPDATE change_timestamp
SET last_update = CURRENT_TIMESTAMP
WHERE table_name = 'config'
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_config_update`;
DELIMITER $$
CREATE TRIGGER `trg_config_update` AFTER UPDATE ON `config` FOR EACH ROW UPDATE change_timestamp
SET last_update = CURRENT_TIMESTAMP
WHERE table_name = 'config'
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `db_version`
--

DROP TABLE IF EXISTS `db_version`;
CREATE TABLE IF NOT EXISTS `db_version` (
  `ver` varchar(20) NOT NULL COMMENT 'Номер версии',
  PRIMARY KEY (`ver`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Номер версии сайта';

--
-- ССЫЛКИ ТАБЛИЦЫ `db_version`:
--

--
-- Дамп данных таблицы `db_version`
--

INSERT INTO `db_version` (`ver`) VALUES
('0.4.4');

-- --------------------------------------------------------

--
-- Структура таблицы `groups`
--

DROP TABLE IF EXISTS `groups`;
CREATE TABLE IF NOT EXISTS `groups` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор группы',
  `name` varchar(50) NOT NULL COMMENT 'Название группы',
  `user_rights` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Права доступа' CHECK (json_valid(`user_rights`)),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Таблица групп пользователей и прав доступа';

--
-- ССЫЛКИ ТАБЛИЦЫ `groups`:
--

--
-- Дамп данных таблицы `groups`
--

INSERT INTO `groups` (`id`, `name`, `user_rights`) VALUES
(0, 'Гость', '{\"pic_view\":true,\"pic_rate_user\":false,\"pic_rate_moder\":false,\"pic_upload\":false,\"pic_moderate\":false,\"cat_moderate\":false,\"cat_user\":false,\"comment_view\":true,\"comment_add\":false,\"comment_moderate\":false,\"news_view\":true,\"news_add\":false,\"news_moderate\":false,\"admin\":false}'),
(1, 'Пользователь', '{\"pic_view\": true, \"pic_rate_user\": true, \"pic_rate_moder\": false, \"pic_upload\": true, \"pic_moderate\": false, \"cat_moderate\": false, \"cat_user\": true, \"comment_view\": true, \"comment_add\": true, \"comment_moderate\": false, \"news_view\": true, \"news_add\": false, \"news_moderate\": false, \"admin\": false}'),
(2, 'Модератор', '{\"pic_view\": true, \"pic_rate_user\": false, \"pic_rate_moder\": true, \"pic_upload\": true, \"pic_moderate\": true, \"cat_moderate\": true, \"cat_user\": true, \"comment_view\": true, \"comment_add\": true, \"comment_moderate\": true, \"news_view\": true, \"news_add\": true, \"news_moderate\": true, \"admin\": false}'),
(3, 'Администратор', '{\"pic_view\": true, \"pic_rate_user\": true, \"pic_rate_moder\": true, \"pic_upload\": true, \"pic_moderate\": true, \"cat_moderate\": true, \"cat_user\": true, \"comment_view\": true, \"comment_add\": true, \"comment_moderate\": true, \"news_view\": true, \"news_add\": true, \"news_moderate\": true, \"admin\": true}');

--
-- Триггеры `groups`
--
DROP TRIGGER IF EXISTS `trg_prevent_deletion_groups`;
DELIMITER $$
CREATE TRIGGER `trg_prevent_deletion_groups` BEFORE DELETE ON `groups` FOR EACH ROW BEGIN
    -- Проверяем, является ли id служебным
    IF OLD.id BETWEEN 0 AND 3 THEN
        -- Генерируем пустое действие (игнорируем удаление)
        SIGNAL SQLSTATE '01000';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `menu`
--

DROP TABLE IF EXISTS `menu`;
CREATE TABLE IF NOT EXISTS `menu` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Порядковый номер пункта меню',
  `action` varchar(50) NOT NULL COMMENT 'Фрагмент из URL, указывающий, что данный пункт меню должен быть неактивным (текущим)',
  `url_action` varchar(250) NOT NULL COMMENT 'URL перехода при выборе пункта меню',
  `name_action` varchar(250) NOT NULL COMMENT 'Пункт из массива $lang, содержащий название пункта меню',
  `short` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Использовать пункт в кратком (верхнем) меню',
  `long` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Использовать пункт в длинном (боковом) меню',
  `user_login` tinyint(1) DEFAULT NULL COMMENT 'Проверка - зарегистрирован ли пользователь',
  `user_access` varchar(250) DEFAULT NULL COMMENT 'Дополнительные права',
  PRIMARY KEY (`id`),
  KEY `idx_menu_short` (`short`),
  KEY `idx_menu_long` (`long`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Таблица пунктов меню' ROW_FORMAT=DYNAMIC;

--
-- ССЫЛКИ ТАБЛИЦЫ `menu`:
--

--
-- Дамп данных таблицы `menu`
--

INSERT INTO `menu` (`id`, `action`, `url_action`, `name_action`, `short`, `long`, `user_login`, `user_access`) VALUES
(1, 'main', './', 'home', 1, 1, NULL, NULL),
(2, 'regist', '?action=profile&subact=regist', 'regist', 1, 1, 0, NULL),
(3, 'category', '?action=category', 'category', 1, 1, NULL, NULL),
(4, 'user_category', '?action=category&cat=user', 'user_category', 0, 1, NULL, NULL),
(5, 'you_category', '?action=category&cat=user&id=curent', 'you_category', 0, 1, 1, 'cat_user'),
(6, 'upload', '?action=photo&subact=upload', 'upload', 0, 1, 1, 'pic_upload'),
(7, 'add_category', '?action=category&subact=add', 'add_category', 0, 1, 1, 'cat_moderate'),
(8, 'search', '?action=search', 'search', 1, 1, NULL, NULL),
(9, 'news', '?action=news', 'news', 1, 1, NULL, 'news_view'),
(10, 'news_add', '?action=news&subact=add', 'news_add', 0, 1, 1, 'news_add'),
(11, 'profile', '?action=profile&subact=profile', 'profile', 1, 1, 1, NULL),
(12, 'admin', '?action=admin', 'admin', 1, 1, 1, 'admin'),
(13, 'logout', '?action=profile&subact=logout', 'logout', 1, 1, 1, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `news`
--

DROP TABLE IF EXISTS `news`;
CREATE TABLE IF NOT EXISTS `news` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор новости',
  `data_post` date NOT NULL COMMENT 'Дата публикации',
  `data_last_edit` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Дата обновления',
  `user_post` int(10) UNSIGNED NOT NULL COMMENT 'Идентификатор добавившего новость пользователя',
  `name_post` varchar(50) NOT NULL COMMENT 'Название новости',
  `text_post` text NOT NULL COMMENT 'Текст новости',
  PRIMARY KEY (`id`),
  KEY `news_users` (`user_post`),
  KEY `data_last_edit` (`data_last_edit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Новости сайта' ROW_FORMAT=DYNAMIC;

--
-- ССЫЛКИ ТАБЛИЦЫ `news`:
--   `user_post`
--       `users` -> `id`
--

-- --------------------------------------------------------

--
-- Структура таблицы `photo`
--

DROP TABLE IF EXISTS `photo`;
CREATE TABLE IF NOT EXISTS `photo` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор',
  `file` varchar(50) NOT NULL COMMENT 'Имя файла',
  `name` varchar(50) NOT NULL COMMENT 'Название фотографии',
  `description` varchar(250) NOT NULL COMMENT 'Описание фотографии',
  `category` int(10) UNSIGNED NOT NULL COMMENT 'Идентификатор раздела',
  `date_upload` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Дата загрузки фото',
  `user_upload` int(10) UNSIGNED NOT NULL COMMENT 'Идентификатор пользователя, залившего фото',
  `rate_user` double NOT NULL DEFAULT 0 COMMENT 'Оценка от пользователя',
  `rate_moder` double NOT NULL DEFAULT 0 COMMENT 'Оценка от модератора',
  PRIMARY KEY (`id`),
  KEY `date_upload` (`date_upload`),
  KEY `idx_photo_category_user_upload` (`category`,`user_upload`),
  KEY `idx_photo_user_upload_group` (`user_upload`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='таблица размещения фотографий' ROW_FORMAT=DYNAMIC;

--
-- ССЫЛКИ ТАБЛИЦЫ `photo`:
--   `category`
--       `category` -> `id`
--   `user_upload`
--       `users` -> `id`
--

-- --------------------------------------------------------

--
-- Структура таблицы `query_logs`
--

DROP TABLE IF EXISTS `query_logs`;
CREATE TABLE IF NOT EXISTS `query_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Уникальный идентификатор записи',
  `query_hash` char(32) NOT NULL COMMENT 'Хэш запроса для проверки дублирования',
  `query_text` text NOT NULL COMMENT 'Текст SQL-запроса',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Время первого логирования',
  `last_used_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Время последнего использования',
  `usage_count` int(11) NOT NULL DEFAULT 1 COMMENT 'Количество использований запроса',
  `reason` enum('slow','no_placeholders','other') DEFAULT 'other' COMMENT 'Причина логирования: медленный запрос, отсутствие плейсхолдеров или другое',
  `execution_time` float DEFAULT NULL COMMENT 'Время выполнения запроса (в миллисекундах)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `query_hash` (`query_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Логирование SQL-запросов для анализа производительности';

--
-- ССЫЛКИ ТАБЛИЦЫ `query_logs`:
--

-- --------------------------------------------------------

--
-- Структура таблицы `rate_moder`
--

DROP TABLE IF EXISTS `rate_moder`;
CREATE TABLE IF NOT EXISTS `rate_moder` (
  `id_foto` int(10) UNSIGNED NOT NULL COMMENT 'Идентификатор фото',
  `id_user` int(10) UNSIGNED NOT NULL COMMENT 'Идентификатор пользователя',
  `rate` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Оценка от -2 до +2',
  PRIMARY KEY (`id_foto`,`id_user`),
  KEY `m_rate_users` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Оценки модераторов' ROW_FORMAT=DYNAMIC;

--
-- ССЫЛКИ ТАБЛИЦЫ `rate_moder`:
--   `id_foto`
--       `photo` -> `id`
--   `id_user`
--       `users` -> `id`
--

--
-- Триггеры `rate_moder`
--
DROP TRIGGER IF EXISTS `update_rate_moder_after_delete`;
DELIMITER $$
CREATE TRIGGER `update_rate_moder_after_delete` AFTER DELETE ON `rate_moder` FOR EACH ROW BEGIN
    IF EXISTS (SELECT 1 FROM photo WHERE id = OLD.id_foto) THEN
        UPDATE photo
        SET rate_moder = (
            SELECT IFNULL(AVG(rate), 0)
            FROM rate_moder
            WHERE id_foto = OLD.id_foto
        )
        WHERE id = OLD.id_foto;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `update_rate_moder_after_insert`;
DELIMITER $$
CREATE TRIGGER `update_rate_moder_after_insert` AFTER INSERT ON `rate_moder` FOR EACH ROW BEGIN
    IF EXISTS (SELECT 1 FROM photo WHERE id = NEW.id_foto) THEN
        UPDATE photo
        SET rate_moder = (
            SELECT IFNULL(AVG(rate), 0)
            FROM rate_moder
            WHERE id_foto = NEW.id_foto
        )
        WHERE id = NEW.id_foto;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `rate_user`
--

DROP TABLE IF EXISTS `rate_user`;
CREATE TABLE IF NOT EXISTS `rate_user` (
  `id_foto` int(10) UNSIGNED NOT NULL COMMENT 'Идентификатор фото',
  `id_user` int(10) UNSIGNED NOT NULL COMMENT 'Идентификатор пользователя',
  `rate` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Оценка от -2 до +2',
  PRIMARY KEY (`id_foto`,`id_user`),
  KEY `u_rate_users` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Оценки пользователей';

--
-- ССЫЛКИ ТАБЛИЦЫ `rate_user`:
--   `id_foto`
--       `photo` -> `id`
--   `id_user`
--       `users` -> `id`
--

--
-- Триггеры `rate_user`
--
DROP TRIGGER IF EXISTS `update_rate_user_after_delete`;
DELIMITER $$
CREATE TRIGGER `update_rate_user_after_delete` AFTER DELETE ON `rate_user` FOR EACH ROW BEGIN
    IF EXISTS (SELECT 1 FROM photo WHERE id = OLD.id_foto) THEN
        UPDATE photo
        SET rate_user = (
            SELECT IFNULL(AVG(rate), 0)
            FROM rate_user
            WHERE id_foto = OLD.id_foto
        )
        WHERE id = OLD.id_foto;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `update_rate_user_after_insert`;
DELIMITER $$
CREATE TRIGGER `update_rate_user_after_insert` AFTER INSERT ON `rate_user` FOR EACH ROW BEGIN
    IF EXISTS (SELECT 1 FROM photo WHERE id = NEW.id_foto) THEN
        UPDATE photo
        SET rate_user = (
            SELECT IFNULL(AVG(rate), 0)
            FROM rate_user
            WHERE id_foto = NEW.id_foto
        )
        WHERE id = NEW.id_foto;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор пользователя',
  `login` varchar(32) NOT NULL COMMENT 'Логин пользователя',
  `password` varchar(255) NOT NULL COMMENT 'Пароль пользователя',
  `real_name` varchar(50) NOT NULL COMMENT 'Отображаемое имя пользователя',
  `email` varchar(50) NOT NULL COMMENT 'E-mail пользователя',
  `email_confirmed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Подтверждён ли email',
  `avatar` varchar(50) NOT NULL DEFAULT 'no_avatar.jpg' COMMENT 'Имя файла аватара пользователя',
  `language` varchar(32) NOT NULL DEFAULT 'russian' COMMENT 'Язык сайта',
  `theme` varchar(32) NOT NULL DEFAULT 'default' COMMENT 'Тема сайта',
  `timezone` varchar(50) NOT NULL DEFAULT 'UTC' COMMENT 'Часовой пояс пользователя',
  `activation` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Флаг активации аккаунта',
  `date_regist` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Дата регистрации',
  `date_last_activ` timestamp NULL DEFAULT NULL COMMENT 'Дата последней активности',
  `date_last_logout` timestamp NULL DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL COMMENT 'Дата и время мягкого удаления пользователя',
  `permanently_deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Флаг окончательного удаления пользователя',
  `token` varchar(255) DEFAULT NULL COMMENT 'Для временных токенов',
  `token_expires_at` datetime DEFAULT NULL COMMENT 'Время истечения токена',
  `group_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `allow_newsletter` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Разрешает ли пользователь рассылку',
  `user_rights` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Права доступа' CHECK (json_valid(`user_rights`)),
  `other_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Прочие параметры пользователя' CHECK (json_valid(`other_params`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`),
  KEY `date_last_activ` (`date_last_activ`),
  KEY `idx_users_deleted_at` (`deleted_at`),
  KEY `idx_users_token` (`token`),
  KEY `idx_users_token_expires` (`token_expires_at`),
  KEY `idx_users_allow_newsletter` (`allow_newsletter`),
  KEY `fk_users_group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Таблица данных пользователя' ROW_FORMAT=DYNAMIC;

--
-- ССЫЛКИ ТАБЛИЦЫ `users`:
--   `group_id`
--       `groups` -> `id`
--

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `login`, `password`, `real_name`, `email`, `email_confirmed`, `avatar`, `language`, `theme`, `timezone`, `activation`, `date_regist`, `date_last_activ`, `date_last_logout`, `deleted_at`, `permanently_deleted`, `token`, `token_expires_at`, `group_id`, `allow_newsletter`, `user_rights`, `other_params`) VALUES
(1, 'admin', '$2y$12$66PqD9l3yDp3qj40j.rXNeh7JGzjt/AKkizosLmdbyjB7pQmt6UxW', 'Администратор', 'admin@rigma.biz', 1, 'no_avatar.jpg', 'russian', 'default', 'UTC', 1, '2009-01-20 12:31:35', '2025-05-11 18:43:48', '2025-05-09 19:45:42', NULL, 0, NULL, NULL, 3, 0, '{\"pic_view\": true, \"pic_rate_user\": true, \"pic_rate_moder\": true, \"pic_upload\": true, \"pic_moderate\": true, \"cat_moderate\": true, \"cat_user\": true, \"comment_view\": true, \"comment_add\": true, \"comment_moderate\": true, \"news_view\": true, \"news_add\": true, \"news_moderate\": true, \"admin\": true}', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `user_bans`
--

DROP TABLE IF EXISTS `user_bans`;
CREATE TABLE IF NOT EXISTS `user_bans` (
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'Идентификатор пользователя',
  `banned` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Флаг: пользователь заблокирован',
  `reason` text DEFAULT NULL COMMENT 'Причина блокировки',
  `expires_at` datetime DEFAULT NULL COMMENT 'Дата окончания бана (если временный)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Дата и время установки бана',
  PRIMARY KEY (`user_id`),
  KEY `idx_user_bans_expires` (`expires_at`),
  KEY `idx_user_bans_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Информация о бане пользователей';

--
-- ССЫЛКИ ТАБЛИЦЫ `user_bans`:
--   `user_id`
--       `users` -> `id`
--

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `category`
--
ALTER TABLE `category` ADD FULLTEXT KEY `idx_fts_category` (`name`,`description`);

--
-- Индексы таблицы `news`
--
ALTER TABLE `news` ADD FULLTEXT KEY `idx_fts_news` (`name_post`,`text_post`);

--
-- Индексы таблицы `photo`
--
ALTER TABLE `photo` ADD FULLTEXT KEY `idx_fts_photo` (`name`,`description`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users` ADD FULLTEXT KEY `idx_fts_users` (`login`,`real_name`,`email`);

-- --------------------------------------------------------

--
-- Структура для представления `random_photo`
--
DROP TABLE IF EXISTS `random_photo`;
DROP VIEW IF EXISTS `random_photo`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `random_photo`  AS SELECT `photo`.`id` AS `id`, `photo`.`file` AS `file`, `photo`.`name` AS `name`, `photo`.`description` AS `description`, `photo`.`category` AS `category`, `photo`.`rate_user` AS `rate_user`, `photo`.`rate_moder` AS `rate_moder`, `photo`.`user_upload` AS `user_upload` FROM `photo` WHERE `photo`.`id` = (select `photo`.`id` from `photo` order by rand() limit 1) ;

-- --------------------------------------------------------

--
-- Структура для представления `users_online`
--
DROP TABLE IF EXISTS `users_online`;
DROP VIEW IF EXISTS `users_online`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `users_online`  AS SELECT `u`.`id` AS `id`, `u`.`real_name` AS `real_name`, CASE WHEN `b`.`user_id` is not null THEN 1 ELSE 0 END AS `banned` FROM (`users` `u` left join `user_bans` `b` on(`u`.`id` = `b`.`user_id` and `b`.`banned` = 1)) WHERE `u`.`date_last_activ` >= current_timestamp() - interval (select `config`.`value` from `config` where `config`.`name` = 'time_user_online') second AND `u`.`activation` = 1 AND `u`.`email_confirmed` = 1 AND `u`.`deleted_at` is null AND `u`.`permanently_deleted` = 0 ;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `news`
--
ALTER TABLE `news`
  ADD CONSTRAINT `news_users` FOREIGN KEY (`user_post`) REFERENCES `users` (`id`);

--
-- Ограничения внешнего ключа таблицы `photo`
--
ALTER TABLE `photo`
  ADD CONSTRAINT `photo_category` FOREIGN KEY (`category`) REFERENCES `category` (`id`),
  ADD CONSTRAINT `photo_users` FOREIGN KEY (`user_upload`) REFERENCES `users` (`id`);

--
-- Ограничения внешнего ключа таблицы `rate_moder`
--
ALTER TABLE `rate_moder`
  ADD CONSTRAINT `m_rate_photo` FOREIGN KEY (`id_foto`) REFERENCES `photo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `m_rate_users` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `rate_user`
--
ALTER TABLE `rate_user`
  ADD CONSTRAINT `u_rate_photo` FOREIGN KEY (`id_foto`) REFERENCES `photo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `u_rate_users` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_group_id` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`);

--
-- Ограничения внешнего ключа таблицы `user_bans`
--
ALTER TABLE `user_bans`
  ADD CONSTRAINT `user_bans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
