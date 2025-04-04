-- phpMyAdmin SQL Dump
-- version 5.2.2-1.fc42
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Апр 04 2025 г., 19:58
-- Версия сервера: 10.11.11-MariaDB
-- Версия PHP: 8.4.6RC1

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

-- --------------------------------------------------------

--
-- Структура таблицы `category`
--
-- Создание: Мар 07 2025 г., 18:07
-- Последнее обновление: Мар 12 2025 г., 17:14
--

DROP TABLE IF EXISTS `category`;
CREATE TABLE IF NOT EXISTS `category` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор',
  `folder` varchar(50) NOT NULL COMMENT 'Имя папки раздела',
  `name` varchar(50) NOT NULL COMMENT 'Название раздела',
  `description` varchar(250) NOT NULL COMMENT 'Описание раздела',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Таблица разделов';

--
-- ССЫЛКИ ТАБЛИЦЫ `category`:
--

--
-- Дамп данных таблицы `category`
--

INSERT INTO `category` (`id`, `folder`, `name`, `description`) VALUES
(0, 'user', 'Пользовательский альбом', 'Персональный пользовательский альбом');

-- --------------------------------------------------------

--
-- Структура таблицы `config`
--
-- Создание: Янв 29 2025 г., 23:43
-- Последнее обновление: Мар 20 2025 г., 22:18
--

DROP TABLE IF EXISTS `config`;
CREATE TABLE IF NOT EXISTS `config` (
  `name` varchar(50) NOT NULL COMMENT 'Имя параметра',
  `value` varchar(255) NOT NULL COMMENT 'Значение параметра',
  PRIMARY KEY (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Таблица параметров';

--
-- ССЫЛКИ ТАБЛИЦЫ `config`:
--

--
-- Дамп данных таблицы `config`
--

INSERT INTO `config` (`name`, `value`) VALUES
('best_user', '5'),
('copyright_text', 'Проекты Rigma.BiZ'),
('copyright_url', 'https://rigma.biz/'),
('copyright_year', '2008-2025'),
('gal_width', '95%'),
('language', 'russian'),
('last_news', '5'),
('left_panel', '250'),
('max_avatar_h', '100'),
('max_avatar_w', '100'),
('max_file_size', '5M'),
('max_photo_h', '480'),
('max_photo_w', '640'),
('max_rate', '2'),
('meta_description', 'Rigma.BiZ - фотогалерея Gold Rigma'),
('meta_keywords', 'Rigma.BiZ photo gallery Gold Rigma'),
('right_panel', '250'),
('temp_photo_h', '200'),
('temp_photo_w', '200'),
('themes', 'default'),
('title_description', 'Фотогалерея Rigma и Co'),
('title_name', 'Rigma Foto'),
('time_user_online', '900');

-- --------------------------------------------------------

--
-- Структура таблицы `db_version`
--
-- Создание: Фев 28 2025 г., 22:37
-- Последнее обновление: Фев 28 2025 г., 22:37
--

DROP TABLE IF EXISTS `db_version`;
CREATE TABLE IF NOT EXISTS `db_version` (
  `ver` varchar(20) NOT NULL COMMENT 'Номер версии',
  PRIMARY KEY (`ver`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Номер версии сайта';

--
-- ССЫЛКИ ТАБЛИЦЫ `db_version`:
--

--
-- Дамп данных таблицы `db_version`
--

INSERT INTO `db_version` (`ver`) VALUES
('0.4.0');

-- --------------------------------------------------------

--
-- Структура таблицы `groups`
--
-- Создание: Мар 19 2025 г., 09:52
-- Последнее обновление: Апр 01 2025 г., 21:55
--

DROP TABLE IF EXISTS `groups`;
CREATE TABLE IF NOT EXISTS `groups` (
  `id` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Идентификатор группы',
  `name` varchar(50) NOT NULL COMMENT 'Название группы',
  `user_rights` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Права доступа' CHECK (json_valid(`user_rights`)),
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Таблица групп пользователей и прав доступа';

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

-- --------------------------------------------------------

--
-- Структура таблицы `menu`
--
-- Создание: Янв 29 2025 г., 23:43
-- Последнее обновление: Янв 29 2025 г., 23:43
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
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Таблица пунктов меню' ROW_FORMAT=DYNAMIC;

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
-- Создание: Янв 29 2025 г., 23:43
-- Последнее обновление: Мар 08 2025 г., 18:51
--

DROP TABLE IF EXISTS `news`;
CREATE TABLE IF NOT EXISTS `news` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор новости',
  `data_post` date NOT NULL COMMENT 'Дата публикации',
  `data_last_edit` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Дата обновления',
  `user_post` int(10) NOT NULL COMMENT 'Идентификатор добавившего новость пользователя',
  `name_post` varchar(50) NOT NULL COMMENT 'Название новости',
  `text_post` text NOT NULL COMMENT 'Текст новости',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Новости сайта' ROW_FORMAT=DYNAMIC;

--
-- ССЫЛКИ ТАБЛИЦЫ `news`:
--   `user_post`
--       `users` -> `id`
--

-- --------------------------------------------------------

--
-- Структура таблицы `photo`
--
-- Создание: Янв 29 2025 г., 23:43
-- Последнее обновление: Мар 12 2025 г., 17:14
--

DROP TABLE IF EXISTS `photo`;
CREATE TABLE IF NOT EXISTS `photo` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор',
  `file` varchar(50) NOT NULL COMMENT 'Имя файла',
  `name` varchar(50) NOT NULL COMMENT 'Название фотографии',
  `description` varchar(250) NOT NULL COMMENT 'Описание фотографии',
  `category` int(10) NOT NULL COMMENT 'Идентификатор раздела',
  `date_upload` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Дата загрузки фото',
  `user_upload` int(10) NOT NULL COMMENT 'Идентификатор пользователя, залившего фото',
  `rate_user` double NOT NULL DEFAULT 0 COMMENT 'Оценка от пользователя',
  `rate_moder` double NOT NULL DEFAULT 0 COMMENT 'Оценка от модератора',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='таблица размещения фотографий' ROW_FORMAT=DYNAMIC;

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
-- Создание: Апр 04 2025 г., 17:55
-- Последнее обновление: Апр 04 2025 г., 19:18
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
-- Создание: Янв 29 2025 г., 23:43
-- Последнее обновление: Янв 29 2025 г., 23:43
--

DROP TABLE IF EXISTS `rate_moder`;
CREATE TABLE IF NOT EXISTS `rate_moder` (
  `id_foto` int(10) NOT NULL COMMENT 'Идентификатор фото',
  `id_user` int(10) NOT NULL COMMENT 'Идентификатор пользователя',
  `rate` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Оценка от -2 до +2',
  PRIMARY KEY (`id_foto`,`id_user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Оценки модераторов' ROW_FORMAT=FIXED;

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
    UPDATE photo
    SET rate_moder = (
        SELECT IFNULL(AVG(rate), 0)
        FROM rate_moder
        WHERE id_foto = OLD.id_foto
    )
    WHERE id = OLD.id_foto;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `update_rate_moder_after_insert`;
DELIMITER $$
CREATE TRIGGER `update_rate_moder_after_insert` AFTER INSERT ON `rate_moder` FOR EACH ROW BEGIN
    UPDATE photo
    SET rate_moder = (
        SELECT IFNULL(AVG(rate), 0)
        FROM rate_moder
        WHERE id_foto = NEW.id_foto
    )
    WHERE id = NEW.id_foto;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `rate_user`
--
-- Создание: Янв 29 2025 г., 23:43
-- Последнее обновление: Мар 12 2025 г., 15:01
--

DROP TABLE IF EXISTS `rate_user`;
CREATE TABLE IF NOT EXISTS `rate_user` (
  `id_foto` int(10) NOT NULL COMMENT 'Идентификатор фото',
  `id_user` int(10) NOT NULL COMMENT 'Идентификатор пользователя',
  `rate` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Оценка от -2 до +2',
  PRIMARY KEY (`id_foto`,`id_user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Оценки пользователей';

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
    UPDATE photo
    SET rate_user = (
        SELECT IFNULL(AVG(rate), 0)
        FROM rate_user
        WHERE id_foto = OLD.id_foto
    )
    WHERE id = OLD.id_foto;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `update_rate_user_after_insert`;
DELIMITER $$
CREATE TRIGGER `update_rate_user_after_insert` AFTER INSERT ON `rate_user` FOR EACH ROW BEGIN
    UPDATE photo
    SET rate_user = (
        SELECT IFNULL(AVG(rate), 0)
        FROM rate_user
        WHERE id_foto = NEW.id_foto
    )
    WHERE id = NEW.id_foto;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--
-- Создание: Мар 19 2025 г., 09:52
-- Последнее обновление: Апр 04 2025 г., 19:18
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор пользователя',
  `login` varchar(32) NOT NULL COMMENT 'Логин пользователя',
  `password` varchar(255) NOT NULL COMMENT 'Пароль пользователя',
  `real_name` varchar(50) NOT NULL COMMENT 'Отображаемое имя пользователя',
  `email` varchar(50) NOT NULL COMMENT 'E-mail пользователя',
  `avatar` varchar(50) NOT NULL DEFAULT 'no_avatar.jpg' COMMENT 'Имя файла аватара пользователя',
  `language` varchar(32) NOT NULL DEFAULT 'russian' COMMENT 'Язык сайта',
  `theme` varchar(32) NOT NULL DEFAULT 'default' COMMENT 'Тема сайта',
  `date_regist` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Дата регистрации',
  `date_last_activ` timestamp NULL DEFAULT NULL COMMENT 'Дата последней активности',
  `date_last_logout` timestamp NULL DEFAULT NULL,
  `group_id` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Идентификатор группы пользователя',
  `user_rights` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Права доступа' CHECK (json_valid(`user_rights`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Таблица данных пользователя' ROW_FORMAT=DYNAMIC;

--
-- ССЫЛКИ ТАБЛИЦЫ `users`:
--   `group_id`
--       `groups` -> `id`
--

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `login`, `password`, `real_name`, `email`, `avatar`, `language`, `theme`, `date_regist`, `date_last_activ`, `date_last_logout`, `group_id`, `user_rights`) VALUES
(1, 'admin', '$2y$12$66PqD9l3yDp3qj40j.rXNeh7JGzjt/AKkizosLmdbyjB7pQmt6UxW', 'Администратор', 'admin@rigma.biz', 'no_avatar.jpg', 'russian', 'default', '2009-01-20 12:31:35', '2025-04-04 16:18:09', '2025-04-02 19:52:30', 3, '{\"pic_view\": true, \"pic_rate_user\": true, \"pic_rate_moder\": true, \"pic_upload\": true, \"pic_moderate\": true, \"cat_moderate\": true, \"cat_user\": true, \"comment_view\": true, \"comment_add\": true, \"comment_moderate\": true, \"news_view\": true, \"news_add\": true, \"news_moderate\": true, \"admin\": true}');

-- --------------------------------------------------------

--
-- Структура для представления `random_photo`
--
DROP TABLE IF EXISTS `random_photo`;

DROP VIEW IF EXISTS `random_photo`;
CREATE VIEW `random_photo`  AS SELECT `photo`.`id` AS `id`, `photo`.`file` AS `file`, `photo`.`name` AS `name`, `photo`.`description` AS `description`, `photo`.`category` AS `category`, `photo`.`rate_user` AS `rate_user`, `photo`.`rate_moder` AS `rate_moder`, `photo`.`user_upload` AS `user_upload` FROM `photo` ORDER BY rand() ASC LIMIT 0, 1 ;

-- --------------------------------------------------------

--
-- Структура для представления `users_online`
--
DROP TABLE IF EXISTS `users_online`;

DROP VIEW IF EXISTS `users_online`;
CREATE VIEW `users_online`  AS SELECT `users`.`id` AS `id`, `users`.`real_name` AS `real_name` FROM `users` WHERE `users`.`date_last_activ` >= current_timestamp() - interval (select `config`.`value` from `config` where `config`.`name` = 'time_user_online') second ;


--
-- Метаданные
--
USE `phpmyadmin`;

--
-- Метаданные для таблицы category
--

--
-- Метаданные для таблицы config
--

--
-- Метаданные для таблицы db_version
--

--
-- Метаданные для таблицы groups
--

--
-- Метаданные для таблицы menu
--

--
-- Метаданные для таблицы news
--

--
-- Метаданные для таблицы photo
--

--
-- Метаданные для таблицы query_logs
--

--
-- Метаданные для таблицы random_photo
--

--
-- Метаданные для таблицы rate_moder
--

--
-- Метаданные для таблицы rate_user
--

--
-- Метаданные для таблицы users
--

--
-- Метаданные для таблицы users_online
--

--
-- Метаданные для базы данных photorigma
--

--
-- Дамп данных таблицы `pma__relation`
--

INSERT INTO `pma__relation` (`master_db`, `master_table`, `master_field`, `foreign_db`, `foreign_table`, `foreign_field`) VALUES
('photorigma', 'news', 'user_post', 'photorigma', 'users', 'id'),
('photorigma', 'photo', 'category', 'photorigma', 'category', 'id'),
('photorigma', 'photo', 'user_upload', 'photorigma', 'users', 'id'),
('photorigma', 'rate_moder', 'id_foto', 'photorigma', 'photo', 'id'),
('photorigma', 'rate_moder', 'id_user', 'photorigma', 'users', 'id'),
('photorigma', 'rate_user', 'id_foto', 'photorigma', 'photo', 'id'),
('photorigma', 'rate_user', 'id_user', 'photorigma', 'users', 'id'),
('photorigma', 'users', 'group_id', 'photorigma', 'groups', 'id');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
