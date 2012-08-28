SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

DROP TABLE IF EXISTS `category`;
CREATE TABLE IF NOT EXISTS `category` (
  `id` int(10) unsigned NOT NULL auto_increment COMMENT 'Идентификатор',
  `folder` varchar(50) NOT NULL COMMENT 'Имя папки раздела',
  `name` varchar(50) NOT NULL COMMENT 'Название раздела',
  `description` varchar(250) NOT NULL COMMENT 'Описание раздела',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Таблица разделов' AUTO_INCREMENT=1 ;

INSERT INTO `category` (`id`, `folder`, `name`, `description`) VALUES
(0, 'user', 'Пользовательский альбом', 'Персональный пользовательский альбом');

DROP TABLE IF EXISTS `config`;
CREATE TABLE IF NOT EXISTS `config` (
  `name` varchar(50) NOT NULL COMMENT 'Имя параметра',
  `value` varchar(255) NOT NULL COMMENT 'Значение параметра',
  PRIMARY KEY  (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Таблица параметров';

INSERT INTO `config` (`name`, `value`) VALUES
('best_user', '5'),
('copyright_text', 'Проекты Rigma.BiZ'),
('copyright_url', 'http://rigma.biz/'),
('copyright_year', '2008-2009'),
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

DROP TABLE IF EXISTS `group`;
CREATE TABLE IF NOT EXISTS `group` (
  `id` int(10) unsigned NOT NULL default '0' COMMENT 'Идентификатор группы',
  `name` varchar(50) NOT NULL COMMENT 'Название группы',
  `pic_view` tinyint(1) NOT NULL default '0' COMMENT 'Возможность просматривать изображения',
  `pic_rate_user` tinyint(1) NOT NULL default '0' COMMENT 'Возможность оценивать изображения как пользователь',
  `pic_rate_moder` tinyint(1) NOT NULL default '0' COMMENT 'Возможность оценивать изображения как модератор',
  `pic_upload` tinyint(1) NOT NULL default '0' COMMENT 'Возможность загружать изображения',
  `pic_moderate` tinyint(1) NOT NULL default '0' COMMENT 'Возможность модерирования изображений',
  `cat_moderate` tinyint(1) NOT NULL default '0' COMMENT 'Возможность управления категориями',
  `cat_user` tinyint(1) NOT NULL default '0' COMMENT 'Возможность создание пользовательской категории',
  `comment_view` tinyint(1) NOT NULL default '0' COMMENT 'Возможность просматривать комментарии',
  `comment_add` tinyint(1) NOT NULL default '0' COMMENT 'Возможность добавлять комментарии',
  `comment_moderate` tinyint(1) NOT NULL default '0' COMMENT 'Возможность редактировать комментарии',
  `news_view` tinyint(1) NOT NULL default '0' COMMENT 'Просмотр новостей',
  `news_add` tinyint(1) NOT NULL default '0' COMMENT 'Добавление новостей',
  `news_moderate` tinyint(1) NOT NULL default '0' COMMENT 'Редактирование новостей',
  `admin` tinyint(1) NOT NULL default '0' COMMENT 'Права администратора',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Таблица групп пользователей и прав доступа';

INSERT INTO `group` (`id`, `name`, `pic_view`, `pic_rate_user`, `pic_rate_moder`, `pic_upload`, `pic_moderate`, `cat_moderate`, `cat_user`, `comment_view`, `comment_add`, `comment_moderate`, `news_view`, `news_add`, `news_moderate`, `admin`) VALUES
(0, 'Гость', 1, 0, 0, 0, 0, 0, 0, 1, 0, 0, 1, 0, 0, 0),
(1, 'Пользователь', 1, 1, 0, 1, 0, 0, 1, 1, 1, 0, 1, 0, 0, 0),
(2, 'Модератор', 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0),
(3, 'Администратор', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1);

DROP TABLE IF EXISTS `menu`;
CREATE TABLE IF NOT EXISTS `menu` (
  `id` int(10) unsigned NOT NULL auto_increment COMMENT 'Порядковый номер пункта меню',
  `action` varchar(50) NOT NULL COMMENT 'Фрагмент из URL, указывающий, что данный пункт меню должен быть неактивным (текущим)',
  `url_action` varchar(250) NOT NULL COMMENT 'URL перехода при выборе пункта меню',
  `name_action` varchar(250) NOT NULL COMMENT 'Пункт из массива $lang, содержащий название пункта меню',
  `short` tinyint(1) NOT NULL default '0' COMMENT 'Использовать пункт в кратком (верхнем) меню',
  `long` tinyint(1) NOT NULL default '0' COMMENT 'Использовать пункт в длинном (боковом) меню',
  `user_login` tinyint(1) default NULL COMMENT 'Проверка - зарегистрирован ли пользователь',
  `user_access` varchar(250) default NULL COMMENT 'Дополнительные права',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='Таблица пунктов меню' AUTO_INCREMENT=14 ;

INSERT INTO `menu` (`id`, `action`, `url_action`, `name_action`, `short`, `long`, `user_login`, `user_access`) VALUES
(1, 'main', './', 'home', 1, 1, NULL, NULL),
(2, 'regist', '?action=login&amp;subact=regist', 'regist', 1, 1, 0, NULL),
(3, 'category', '?action=category', 'category', 1, 1, NULL, NULL),
(4, 'user_category', '?action=category&amp;cat=user', 'user_category', 0, 1, NULL, NULL),
(5, 'you_category', '?action=category&amp;cat=user&amp;id=curent', 'you_category', 0, 1, 1, 'cat_user'),
(6, 'upload', '?action=photo&amp;subact=upload', 'upload', 0, 1, 1, 'pic_upload'),
(7, 'add_category', '?action=category&amp;subact=add', 'add_category', 0, 1, 1, 'cat_moderate'),
(8, 'search', '?action=search', 'search', 1, 1, NULL, NULL),
(9, 'news', '?action=news', 'news', 1, 1, NULL, 'news_view'),
(10, 'news_add', '?action=news&amp;subact=add', 'news_add', 0, 1, 1, 'news_add'),
(11, 'profile', '?action=login&amp;subact=profile', 'profile', 1, 1, 1, NULL),
(12, 'admin', '?action=admin', 'admin', 1, 1, 1, 'admin'),
(13, 'logout', '?action=login&amp;subact=logout', 'logout', 1, 1, 1, NULL);

DROP TABLE IF EXISTS `news`;
CREATE TABLE IF NOT EXISTS `news` (
  `id` int(10) unsigned NOT NULL auto_increment COMMENT 'Идентификатор новости',
  `data_post` date NOT NULL COMMENT 'Дата публикации',
  `data_last_edit` timestamp NOT NULL default CURRENT_TIMESTAMP COMMENT 'Дата обновления',
  `user_post` int(10) NOT NULL COMMENT 'Идентификатор добавившего новость пользователя',
  `name_post` varchar(50) NOT NULL COMMENT 'Название новости',
  `text_post` text NOT NULL COMMENT 'Текст новости',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='Новости сайта' AUTO_INCREMENT=1 ;

DROP TABLE IF EXISTS `photo`;
CREATE TABLE IF NOT EXISTS `photo` (
  `id` int(10) unsigned NOT NULL auto_increment COMMENT 'Идентификатор',
  `file` varchar(50) NOT NULL COMMENT 'Имя файла',
  `name` varchar(50) NOT NULL COMMENT 'Название фотографии',
  `description` varchar(250) NOT NULL COMMENT 'Описание фотографии',
  `category` int(10) NOT NULL COMMENT 'Идентификатор раздела',
  `date_upload` timestamp NOT NULL default CURRENT_TIMESTAMP COMMENT 'Дата загрузки фото',
  `user_upload` int(10) NOT NULL COMMENT 'Идентификатор пользователя, залившего фото',
  `rate_user` double NOT NULL default '0' COMMENT 'Оценка от пользователя',
  `rate_moder` double NOT NULL default '0' COMMENT 'Оценка от модератора',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='таблица размещения фотографий' AUTO_INCREMENT=1 ;

DROP TABLE IF EXISTS `rate_moder`;
CREATE TABLE IF NOT EXISTS `rate_moder` (
  `id_foto` int(10) NOT NULL COMMENT 'Идентификатор фото',
  `id_user` int(10) NOT NULL COMMENT 'Идентификатор пользователя',
  `rate` tinyint(1) NOT NULL default '0' COMMENT 'Оценка от -2 до +2',
  PRIMARY KEY  (`id_foto`,`id_user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED COMMENT='Оценки модераторов';

DROP TABLE IF EXISTS `rate_user`;
CREATE TABLE IF NOT EXISTS `rate_user` (
  `id_foto` int(10) NOT NULL COMMENT 'Идентификатор фото',
  `id_user` int(10) NOT NULL COMMENT 'Идентификатор пользователя',
  `rate` tinyint(1) NOT NULL default '0' COMMENT 'Оценка от -2 до +2',
  PRIMARY KEY  (`id_foto`,`id_user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Оценки пользователей';

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `id` int(10) unsigned NOT NULL auto_increment COMMENT 'Идентификатор пользователя',
  `login` varchar(32) NOT NULL COMMENT 'Логин пользователя',
  `password` varchar(50) NOT NULL COMMENT 'Пароль пользователя',
  `real_name` varchar(50) NOT NULL COMMENT 'Отображаемое имя пользователя',
  `email` varchar(50) NOT NULL COMMENT 'E-mail пользователя',
  `avatar` varchar(50) NOT NULL default 'no_avatar.jpg' COMMENT 'Имя файла аватара пользователя',
  `date_regist` timestamp NOT NULL default CURRENT_TIMESTAMP COMMENT 'Дата регистрации',
  `date_last_activ` timestamp NULL default NULL COMMENT 'Дата последней активности',
  `date_last_logout` timestamp NULL default NULL,
  `group` int(10) unsigned NOT NULL default '0' COMMENT 'Идентификатор группы пользователя',
  `pic_view` tinyint(1) NOT NULL default '0' COMMENT 'Возможность просматривать изображения',
  `pic_rate_user` tinyint(1) NOT NULL default '0' COMMENT 'Возможность оценивать изображения как пользователь',
  `pic_rate_moder` tinyint(1) NOT NULL default '0' COMMENT 'Возможность оценивать изображения как модератор',
  `pic_upload` tinyint(1) NOT NULL default '0' COMMENT 'Возможность загружать изображения',
  `pic_moderate` tinyint(1) NOT NULL default '0' COMMENT 'Возможность модерирования изображений',
  `cat_moderate` tinyint(1) NOT NULL default '0' COMMENT 'Возможность управления категориями',
  `cat_user` tinyint(1) NOT NULL default '0' COMMENT 'Возможность создание пользовательской категории',
  `comment_view` tinyint(1) NOT NULL default '0' COMMENT 'Возможность просматривать комментарии',
  `comment_add` tinyint(1) NOT NULL default '0' COMMENT 'Возможность добавлять комментарии',
  `comment_moderate` tinyint(1) NOT NULL default '0' COMMENT 'Возможность редактировать комментарии',
  `news_view` tinyint(1) NOT NULL default '0' COMMENT 'Просмотр новостей',
  `news_add` tinyint(1) NOT NULL default '0' COMMENT 'Добавление новостей',
  `news_moderate` tinyint(1) NOT NULL default '0' COMMENT 'Редактирование новостей',
  `admin` tinyint(1) NOT NULL default '0' COMMENT 'Права администратора',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='Таблица данных пользователя' AUTO_INCREMENT=2 ;

INSERT INTO `user` (`id`, `login`, `password`, `real_name`, `email`, `avatar`, `date_regist`, `date_last_activ`, `date_last_logout`, `group`, `pic_view`, `pic_rate_user`, `pic_rate_moder`, `pic_upload`, `pic_moderate`, `cat_moderate`, `cat_user`, `comment_view`, `comment_add`, `comment_moderate`, `news_view`, `news_add`, `news_moderate`, `admin`) VALUES
(1, 'admin', '21232f297a57a5a743894a0e4a801fc3', 'Admin', 'admin@rigma.biz', 'no_avatar.jpg', '2009-01-20 16:31:35', NULL, NULL, 3, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1);
