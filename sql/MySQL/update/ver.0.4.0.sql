-- 1. Добавление нового столбца `language` после чтолбца `avatar` в группе в таблице `user` (с значением по-умолчанию 'russian')
ALTER TABLE `user` ADD `language` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'russian' COMMENT 'Язык сайта' AFTER `avatar`;
-- 2. Добавление нового столбца `user_rights` после столбца `admin` в таблице `user`
ALTER TABLE `user` ADD `user_rights` JSON NULL COMMENT 'Права доступа' AFTER `admin`;
-- 3. Заполнение нового столбца `user_rights` в таблице `user` JSON-данными
UPDATE `user`
SET `user_rights` = JSON_OBJECT(
    'pic_view', pic_view,
    'pic_rate_user', pic_rate_user,
    'pic_rate_moder', pic_rate_moder,
    'pic_upload', pic_upload,
    'pic_moderate', pic_moderate,
    'cat_moderate', cat_moderate,
    'cat_user', cat_user,
    'comment_view', comment_view,
    'comment_add', comment_add,
    'comment_moderate', comment_moderate,
    'news_view', news_view,
    'news_add', news_add,
    'news_moderate', news_moderate,
    'admin', admin
);
-- 4. Удаление старых полей в таблице `user`
-- ALTER TABLE `user` DROP COLUMN pic_view, DROP COLUMN pic_rate_user, DROP COLUMN pic_rate_moder, DROP COLUMN pic_upload, DROP COLUMN pic_moderate, DROP COLUMN cat_moderate, DROP COLUMN cat_user, DROP COLUMN comment_view, DROP COLUMN comment_add, DROP COLUMN comment_moderate, DROP COLUMN news_view, DROP COLUMN news_add, DROP COLUMN news_moderate, DROP COLUMN admin;
-- 5. Добавление нового столбца `user_rights` после столбца `admin` в таблице `group`
ALTER TABLE `group` ADD `user_rights` JSON NULL COMMENT 'Права доступа' AFTER `admin`;
-- 6. Заполнение нового столбца `user_rights` в таблице `group` JSON-данными
UPDATE `group`
SET `user_rights` = JSON_OBJECT(
    'pic_view', pic_view,
    'pic_rate_user', pic_rate_user,
    'pic_rate_moder', pic_rate_moder,
    'pic_upload', pic_upload,
    'pic_moderate', pic_moderate,
    'cat_moderate', cat_moderate,
    'cat_user', cat_user,
    'comment_view', comment_view,
    'comment_add', comment_add,
    'comment_moderate', comment_moderate,
    'news_view', news_view,
    'news_add', news_add,
    'news_moderate', news_moderate,
    'admin', admin
);
-- 7. Удаление старых полей в таблице `group`
-- ALTER TABLE `group` DROP COLUMN pic_view, DROP COLUMN pic_rate_user, DROP COLUMN pic_rate_moder, DROP COLUMN pic_upload, DROP COLUMN pic_moderate, DROP COLUMN cat_moderate, DROP COLUMN cat_user, DROP COLUMN comment_view, DROP COLUMN comment_add, DROP COLUMN comment_moderate, DROP COLUMN news_view, DROP COLUMN news_add, DROP COLUMN news_moderate, DROP COLUMN admin;

-- 8. Изменение описания по-умолчанию.
UPDATE `config` SET `value` = 'Фотогалерея Rigma и Co' WHERE `config`.`name` = 'title_description'

-- 9. Добавляем настройку - сколько секунд от оследней активности польователя его считать онлайн.
INSERT INTO `config` (`name`, `value`) VALUES ('time_user_online', '900');

-- 10. Увеличиваем поле для хранения пароля в связи с переходом на password_hash(..., PASSWORD_BCRYPT)
ALTER TABLE `user` CHANGE `password` `password` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Пароль пользователя';

-- 11. Изменения в таблице `db_version`
ALTER TABLE `db_version` CHANGE `rev` `ver` VARCHAR(20) NOT NULL COMMENT 'Номер версии';
TRUNCATE `db_version`;
INSERT INTO `db_version` (`ver`) VALUES ('0.4.0');

-- 12. Переименование таблицы `group` в `groups` для избежание совпадений с зарезервированным словом GROUP
 RENAME TABLE `group` TO `groups`;

 -- 13. Переименование таблицы `user` в `users`, а так же столбца `group` в `group_id` для избежание совпадений с зарезервированным словом GROUP
 RENAME TABLE `user` TO `users`;
 ALTER TABLE `users` CHANGE `group` `group_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Идентификатор группы пользователя';
