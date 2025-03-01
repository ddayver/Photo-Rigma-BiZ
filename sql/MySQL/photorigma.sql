-- phpMyAdmin SQL Dump
-- version 3.5.8.1
-- http://www.phpmyadmin.net
--
-- Хост: localhost
-- Время создания: Окт 06 2013 г., 20:34
-- Версия сервера: 5.5.32-MariaDB
-- Версия PHP: 5.5.4

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- База данных: `photorigma`
--

-- --------------------------------------------------------

--
-- Структура таблицы `category`
--
-- Создание: Сен 21 2013 г., 17:45
-- Последнее обновление: Сен 21 2013 г., 17:45
-- Последняя проверка: Сен 25 2013 г., 15:33
--

DROP TABLE IF EXISTS `category`;
CREATE TABLE IF NOT EXISTS `category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор',
  `folder` varchar(50) NOT NULL COMMENT 'Имя папки раздела',
  `name` varchar(50) NOT NULL COMMENT 'Название раздела',
  `description` varchar(250) NOT NULL COMMENT 'Описание раздела',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Таблица разделов';

--
-- Дамп данных таблицы `category`
--

INSERT INTO `category` (`id`, `folder`, `name`, `description`) VALUES
(0, 'user', 'Пользовательский альбом', 'Персональный пользовательский альбом');

-- --------------------------------------------------------

--
-- Структура таблицы `config`
--
-- Создание: Сен 21 2013 г., 17:45
-- Последнее обновление: Окт 06 2013 г., 17:32
-- Последняя проверка: Сен 25 2013 г., 15:33
--

DROP TABLE IF EXISTS `config`;
CREATE TABLE IF NOT EXISTS `config` (
  `name` varchar(50) NOT NULL COMMENT 'Имя параметра',
  `value` varchar(255) NOT NULL COMMENT 'Значение параметра',
  PRIMARY KEY (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Таблица параметров';

--
-- Дамп данных таблицы `config`
--

INSERT INTO `config` (`name`, `value`) VALUES
('best_user', '5'),
('copyright_text', 'Проекты Rigma.BiZ'),
('copyright_url', 'http://rigma.biz/'),
('copyright_year', '2008-2013'),
('gal_width', '95%'),
('language', 'russian'),
('last_news', '5'),
('left_panel', '250'),
('max_avatar_h', '100'),
('max_avatar_w', '100'),
('max_file_size', '2M'),
('max_photo_h', '480'),
('max_photo_w', '640'),
('max_rate', '2'),
('meta_description', 'Rigma.BiZ - фотогалерея Gold Rigma'),
('meta_keywords', 'Rigma.BiZ photo gallery Gold Rigma'),
('right_panel', '250'),
('temp_photo_h', '200'),
('temp_photo_w', '200'),
('themes', 'default'),
('title_description', 'Фотогалерея Rigma & Co'),
('title_name', 'Rigma Foto');

-- --------------------------------------------------------

--
-- Структура таблицы `db_version`
--
-- Создание: Сен 21 2013 г., 18:50
-- Последнее обновление: Сен 21 2013 г., 18:50
-- Последняя проверка: Сен 25 2013 г., 15:34
--

DROP TABLE IF EXISTS `db_version`;
CREATE TABLE IF NOT EXISTS `db_version` (
  `rev` int(4) NOT NULL COMMENT 'Номер ревизии',
  PRIMARY KEY (`rev`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Номер ревизии сайта';

--
-- Дамп данных таблицы `db_version`
--

INSERT INTO `db_version` (`rev`) VALUES
(58);

-- --------------------------------------------------------

--
-- Структура таблицы `group`
--
-- Создание: Сен 21 2013 г., 17:45
-- Последнее обновление: Сен 21 2013 г., 17:45
-- Последняя проверка: Сен 25 2013 г., 15:33
--

DROP TABLE IF EXISTS `group`;
CREATE TABLE IF NOT EXISTS `group` (
  `id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Идентификатор группы',
  `name` varchar(50) NOT NULL COMMENT 'Название группы',
  `pic_view` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность просматривать изображения',
  `pic_rate_user` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность оценивать изображения как пользователь',
  `pic_rate_moder` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность оценивать изображения как модератор',
  `pic_upload` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность загружать изображения',
  `pic_moderate` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность модерирования изображений',
  `cat_moderate` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность управления категориями',
  `cat_user` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность создание пользовательской категории',
  `comment_view` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность просматривать комментарии',
  `comment_add` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность добавлять комментарии',
  `comment_moderate` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность редактировать комментарии',
  `news_view` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Просмотр новостей',
  `news_add` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Добавление новостей',
  `news_moderate` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Редактирование новостей',
  `admin` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Права администратора',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Таблица групп пользователей и прав доступа';

--
-- Дамп данных таблицы `group`
--

INSERT INTO `group` (`id`, `name`, `pic_view`, `pic_rate_user`, `pic_rate_moder`, `pic_upload`, `pic_moderate`, `cat_moderate`, `cat_user`, `comment_view`, `comment_add`, `comment_moderate`, `news_view`, `news_add`, `news_moderate`, `admin`) VALUES
(0, 'Гость', 1, 0, 0, 0, 0, 0, 0, 1, 0, 0, 1, 0, 0, 0),
(1, 'Пользователь', 1, 1, 0, 1, 0, 0, 1, 1, 1, 0, 1, 0, 0, 0),
(2, 'Модератор', 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0),
(3, 'Администратор', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `menu`
--
-- Создание: Сен 21 2013 г., 17:45
-- Последнее обновление: Сен 25 2013 г., 16:53
-- Последняя проверка: Сен 29 2013 г., 12:24
--

DROP TABLE IF EXISTS `menu`;
CREATE TABLE IF NOT EXISTS `menu` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Порядковый номер пункта меню',
  `action` varchar(50) NOT NULL COMMENT 'Фрагмент из URL, указывающий, что данный пункт меню должен быть неактивным (текущим)',
  `url_action` varchar(250) NOT NULL COMMENT 'URL перехода при выборе пункта меню',
  `name_action` varchar(250) NOT NULL COMMENT 'Пункт из массива $lang, содержащий название пункта меню',
  `short` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Использовать пункт в кратком (верхнем) меню',
  `long` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Использовать пункт в длинном (боковом) меню',
  `user_login` tinyint(1) DEFAULT NULL COMMENT 'Проверка - зарегистрирован ли пользователь',
  `user_access` varchar(250) DEFAULT NULL COMMENT 'Дополнительные права',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='Таблица пунктов меню';

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
-- Создание: Сен 21 2013 г., 17:45
-- Последнее обновление: Сен 21 2013 г., 17:45
--

DROP TABLE IF EXISTS `news`;
CREATE TABLE IF NOT EXISTS `news` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор новости',
  `data_post` date NOT NULL COMMENT 'Дата публикации',
  `data_last_edit` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата обновления',
  `user_post` int(10) NOT NULL COMMENT 'Идентификатор добавившего новость пользователя',
  `name_post` varchar(50) NOT NULL COMMENT 'Название новости',
  `text_post` text NOT NULL COMMENT 'Текст новости',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='Новости сайта';

-- --------------------------------------------------------

--
-- Структура таблицы `photo`
--
-- Создание: Сен 21 2013 г., 17:45
-- Последнее обновление: Сен 21 2013 г., 17:45
--

DROP TABLE IF EXISTS `photo`;
CREATE TABLE IF NOT EXISTS `photo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор',
  `file` varchar(50) NOT NULL COMMENT 'Имя файла',
  `name` varchar(50) NOT NULL COMMENT 'Название фотографии',
  `description` varchar(250) NOT NULL COMMENT 'Описание фотографии',
  `category` int(10) NOT NULL COMMENT 'Идентификатор раздела',
  `date_upload` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата загрузки фото',
  `user_upload` int(10) NOT NULL COMMENT 'Идентификатор пользователя, залившего фото',
  `rate_user` double NOT NULL DEFAULT '0' COMMENT 'Оценка от пользователя',
  `rate_moder` double NOT NULL DEFAULT '0' COMMENT 'Оценка от модератора',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='таблица размещения фотографий';

-- --------------------------------------------------------

--
-- Структура таблицы `rate_moder`
--
-- Создание: Сен 21 2013 г., 17:45
-- Последнее обновление: Сен 21 2013 г., 17:45
--

DROP TABLE IF EXISTS `rate_moder`;
CREATE TABLE IF NOT EXISTS `rate_moder` (
  `id_foto` int(10) NOT NULL COMMENT 'Идентификатор фото',
  `id_user` int(10) NOT NULL COMMENT 'Идентификатор пользователя',
  `rate` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Оценка от -2 до +2',
  PRIMARY KEY (`id_foto`,`id_user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED COMMENT='Оценки модераторов';

-- --------------------------------------------------------

--
-- Структура таблицы `rate_user`
--
-- Создание: Сен 21 2013 г., 17:45
-- Последнее обновление: Сен 21 2013 г., 17:45
--

DROP TABLE IF EXISTS `rate_user`;
CREATE TABLE IF NOT EXISTS `rate_user` (
  `id_foto` int(10) NOT NULL COMMENT 'Идентификатор фото',
  `id_user` int(10) NOT NULL COMMENT 'Идентификатор пользователя',
  `rate` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Оценка от -2 до +2',
  PRIMARY KEY (`id_foto`,`id_user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Оценки пользователей';

-- --------------------------------------------------------

--
-- Структура таблицы `user`
--
-- Создание: Сен 21 2013 г., 17:45
-- Последнее обновление: Окт 06 2013 г., 17:26
-- Последняя проверка: Окт 03 2013 г., 19:30
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор пользователя',
  `login` varchar(32) NOT NULL COMMENT 'Логин пользователя',
  `password` varchar(50) NOT NULL COMMENT 'Пароль пользователя',
  `real_name` varchar(50) NOT NULL COMMENT 'Отображаемое имя пользователя',
  `email` varchar(50) NOT NULL COMMENT 'E-mail пользователя',
  `avatar` varchar(50) NOT NULL DEFAULT 'no_avatar.jpg' COMMENT 'Имя файла аватара пользователя',
  `date_regist` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата регистрации',
  `date_last_activ` timestamp NULL DEFAULT NULL COMMENT 'Дата последней активности',
  `date_last_logout` timestamp NULL DEFAULT NULL,
  `group` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Идентификатор группы пользователя',
  `pic_view` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность просматривать изображения',
  `pic_rate_user` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность оценивать изображения как пользователь',
  `pic_rate_moder` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность оценивать изображения как модератор',
  `pic_upload` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность загружать изображения',
  `pic_moderate` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность модерирования изображений',
  `cat_moderate` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность управления категориями',
  `cat_user` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность создание пользовательской категории',
  `comment_view` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность просматривать комментарии',
  `comment_add` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность добавлять комментарии',
  `comment_moderate` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Возможность редактировать комментарии',
  `news_view` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Просмотр новостей',
  `news_add` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Добавление новостей',
  `news_moderate` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Редактирование новостей',
  `admin` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Права администратора',
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='Таблица данных пользователя';

--
-- Дамп данных таблицы `user`
--

INSERT INTO `user` (`id`, `login`, `password`, `real_name`, `email`, `avatar`, `date_regist`, `date_last_activ`, `date_last_logout`, `group`, `pic_view`, `pic_rate_user`, `pic_rate_moder`, `pic_upload`, `pic_moderate`, `cat_moderate`, `cat_user`, `comment_view`, `comment_add`, `comment_moderate`, `news_view`, `news_add`, `news_moderate`, `admin`) VALUES
(1, 'admin', '21232f297a57a5a743894a0e4a801fc3', 'Admin', 'admin@rigma.biz', 'no_avatar.jpg', '2009-01-20 14:31:35', NULL, '2013-10-06 17:10:30', 3, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
