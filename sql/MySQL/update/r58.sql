UPDATE `menu` SET `url_action` = '?action=profile&subact=regist' WHERE `id` =2;
UPDATE `menu` SET `url_action` = '?action=category&cat=user' WHERE `id` =4;
UPDATE `menu` SET `url_action` = '?action=category&cat=user&id=curent' WHERE `id` =5;
UPDATE `menu` SET `url_action` = '?action=photo&subact=upload' WHERE `id` =6;
UPDATE `menu` SET `url_action` = '?action=category&subact=add' WHERE `id` =7;
UPDATE `menu` SET `url_action` = '?action=news&subact=add' WHERE `id` =10;
UPDATE `menu` SET `url_action` = '?action=profile&subact=profile' WHERE `id` =11;
UPDATE `menu` SET `url_action` = '?action=profile&subact=logout' WHERE `id` =13;
UPDATE `config` SET `value` = '2008-2013' WHERE `name` = 'copyright_year';
DROP TABLE IF EXISTS `db_version`;
CREATE TABLE IF NOT EXISTS `db_version` (
  `rev` int(4) NOT NULL COMMENT 'Номер ревизии',
  PRIMARY KEY (`rev`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Номер ревизии сайта';
INSERT INTO `db_version` (`rev`) VALUES (58);
