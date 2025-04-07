-- 1. Добавление нового столбца `language` после чтолбца `avatar` в группе в таблице `user` (с значением по-умолчанию 'russian')
ALTER TABLE `user` ADD `language` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'russian' COMMENT 'Язык сайта' AFTER `avatar`;
-- 2. Добавление нового столбца `user_rights` после столбца `admin` в таблице `user`
ALTER TABLE `user` ADD `user_rights` JSON NULL COMMENT 'Права доступа' AFTER `admin`;
-- 3. Заполнение нового столбца `user_rights` в таблице `user` JSON-данными
UPDATE `user`
SET `user_rights` = JSON_OBJECT(
    'pic_view', pic_view = 1,
    'pic_rate_user', pic_rate_user = 1,
    'pic_rate_moder', pic_rate_moder = 1,
    'pic_upload', pic_upload = 1,
    'pic_moderate', pic_moderate = 1,
    'cat_moderate', cat_moderate = 1,
    'cat_user', cat_user = 1,
    'comment_view', comment_view = 1,
    'comment_add', comment_add = 1,
    'comment_moderate', comment_moderate = 1,
    'news_view', news_view = 1,
    'news_add', news_add = 1,
    'news_moderate', news_moderate = 1,
    'admin', admin = 1
);
-- 4. Удаление старых полей в таблице `user`
ALTER TABLE `user` DROP COLUMN pic_view, DROP COLUMN pic_rate_user, DROP COLUMN pic_rate_moder, DROP COLUMN pic_upload, DROP COLUMN pic_moderate, DROP COLUMN cat_moderate, DROP COLUMN cat_user, DROP COLUMN comment_view, DROP COLUMN comment_add, DROP COLUMN comment_moderate, DROP COLUMN news_view, DROP COLUMN news_add, DROP COLUMN news_moderate, DROP COLUMN admin;
-- 5. Добавление нового столбца `user_rights` после столбца `admin` в таблице `group`
ALTER TABLE `group` ADD `user_rights` JSON NULL COMMENT 'Права доступа' AFTER `admin`;
-- 6. Заполнение нового столбца `user_rights` в таблице `group` JSON-данными
UPDATE `group`
SET `user_rights` = JSON_OBJECT(
    'pic_view', pic_view = 1,
    'pic_rate_user', pic_rate_user = 1,
    'pic_rate_moder', pic_rate_moder = 1,
    'pic_upload', pic_upload = 1,
    'pic_moderate', pic_moderate = 1,
    'cat_moderate', cat_moderate = 1,
    'cat_user', cat_user = 1,
    'comment_view', comment_view = 1,
    'comment_add', comment_add = 1,
    'comment_moderate', comment_moderate = 1,
    'news_view', news_view = 1,
    'news_add', news_add = 1,
    'news_moderate', news_moderate = 1,
    'admin', admin = 1
);
-- 7. Удаление старых полей в таблице `group`
ALTER TABLE `group` DROP COLUMN pic_view, DROP COLUMN pic_rate_user, DROP COLUMN pic_rate_moder, DROP COLUMN pic_upload, DROP COLUMN pic_moderate, DROP COLUMN cat_moderate, DROP COLUMN cat_user, DROP COLUMN comment_view, DROP COLUMN comment_add, DROP COLUMN comment_moderate, DROP COLUMN news_view, DROP COLUMN news_add, DROP COLUMN news_moderate, DROP COLUMN admin;

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

-- 14. Добавление нового столбца `theme` после чтолбца `language` в группе в таблице `users` (с значением по-умолчанию 'default')
ALTER TABLE `users` ADD `theme` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'default' COMMENT 'Тема сайта' AFTER `language`;

--15. Добавляем "представления" для сложных запросов, отличающихся своим форматом между MySQL и PostgreSQL
CREATE VIEW `users_online` AS
SELECT `id`, `real_name`
FROM `users`
WHERE `date_last_activ` >= NOW() - INTERVAL (
    SELECT `value` FROM `config` WHERE `name` = 'time_user_online'
) SECOND;

CREATE VIEW `random_photo` AS
SELECT `id`, `file`, `name`, `description`, `category`, `rate_user`, `rate_moder`, `user_upload`
FROM `photo`
ORDER BY rand()
LIMIT 1;

-- 16. Добавляем таблицу `query_logs` для логирования медленных запросов и запросов без плейсхолдеров.
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

-- 17. Добавляем тригеры для автоматического перерасчета рейтинга фотографий после изменения в таблицах с оценками.
DELIMITER $$

-- Триггеры для rate_user
CREATE TRIGGER update_rate_user_after_insert
AFTER INSERT ON rate_user
FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM photo WHERE id = NEW.id_foto) THEN
        UPDATE photo
        SET rate_user = (
            SELECT IFNULL(AVG(rate), 0)
            FROM rate_user
            WHERE id_foto = NEW.id_foto
        )
        WHERE id = NEW.id_foto;
    END IF;
END$$

CREATE TRIGGER update_rate_user_after_delete
AFTER DELETE ON rate_user
FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM photo WHERE id = OLD.id_foto) THEN
        UPDATE photo
        SET rate_user = (
            SELECT IFNULL(AVG(rate), 0)
            FROM rate_user
            WHERE id_foto = OLD.id_foto
        )
        WHERE id = OLD.id_foto;
    END IF;
END$$

-- Триггеры для rate_moder
CREATE TRIGGER update_rate_moder_after_insert
AFTER INSERT ON rate_moder
FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM photo WHERE id = NEW.id_foto) THEN
        UPDATE photo
        SET rate_moder = (
            SELECT IFNULL(AVG(rate), 0)
            FROM rate_moder
            WHERE id_foto = NEW.id_foto
        )
        WHERE id = NEW.id_foto;
    END IF;
END$$

CREATE TRIGGER update_rate_moder_after_delete
AFTER DELETE ON rate_moder
FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM photo WHERE id = OLD.id_foto) THEN
        UPDATE photo
        SET rate_moder = (
            SELECT IFNULL(AVG(rate), 0)
            FROM rate_moder
            WHERE id_foto = OLD.id_foto
        )
        WHERE id = OLD.id_foto;
    END IF;
END$$

DELIMITER ;

-- 18. Тригер для запрета удаления служебных групп в таблице `groups`
DELIMITER $$

CREATE TRIGGER trg_prevent_deletion
BEFORE DELETE ON groups
FOR EACH ROW
BEGIN
    -- Проверяем, является ли id служебным
    IF OLD.id BETWEEN 0 AND 3 THEN
        -- Генерируем пустое действие (игнорируем удаление)
        SIGNAL SQLSTATE '01000';
    END IF;
END$$

DELIMITER ;

-- 19. Преобразование таблиц в InnoDB
ALTER TABLE `category` ENGINE = InnoDB;
ALTER TABLE `config` ENGINE = InnoDB;
ALTER TABLE `db_version` ENGINE = InnoDB;
ALTER TABLE `groups` ENGINE = InnoDB;
ALTER TABLE `menu` ENGINE = InnoDB;
ALTER TABLE `news` ENGINE = InnoDB;
ALTER TABLE `news` CHANGE `user_post` `user_post` INT(10) UNSIGNED NOT NULL COMMENT 'Идентификатор добавившего новость пользователя';
ALTER TABLE `photo` ENGINE = InnoDB;
ALTER TABLE `photo` CHANGE `category` `category` INT(10) UNSIGNED NOT NULL COMMENT 'Идентификатор раздела', CHANGE `user_upload` `user_upload` INT(10) UNSIGNED NOT NULL COMMENT 'Идентификатор пользователя, залившего фото';
ALTER TABLE `users` ENGINE = InnoDB;
ALTER TABLE `rate_moder` DROP PRIMARY KEY;
ALTER TABLE `rate_moder` ENGINE = InnoDB ROW_FORMAT = DYNAMIC;
ALTER TABLE `rate_moder` CHANGE `id_foto` `id_foto` INT(10) UNSIGNED NOT NULL COMMENT 'Идентификатор фото', CHANGE `id_user` `id_user` INT(10) UNSIGNED NOT NULL COMMENT 'Идентификатор пользователя';
ALTER TABLE `rate_moder` ADD PRIMARY KEY(`id_foto`, `id_user`);
ALTER TABLE `rate_user` DROP PRIMARY KEY;
ALTER TABLE `rate_user` ENGINE = InnoDB ROW_FORMAT = DYNAMIC;
ALTER TABLE `rate_user` CHANGE `id_foto` `id_foto` INT(10) UNSIGNED NOT NULL COMMENT 'Идентификатор фото', CHANGE `id_user` `id_user` INT(10) UNSIGNED NOT NULL COMMENT 'Идентификатор пользователя';
ALTER TABLE `rate_user` ADD PRIMARY KEY(`id_foto`, `id_user`);

-- 20. Добавление внешних ключей к таблицам.
ALTER TABLE news
    ADD CONSTRAINT news_users FOREIGN KEY (user_post) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE photo
    ADD CONSTRAINT photo_category FOREIGN KEY (category) REFERENCES category(id) ON DELETE RESTRICT;

ALTER TABLE photo
    ADD CONSTRAINT photo_users FOREIGN KEY (user_upload) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE users
    ADD CONSTRAINT users_groups FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE RESTRICT;

ALTER TABLE rate_moder
    ADD CONSTRAINT m_rate_photo FOREIGN KEY (id_foto) REFERENCES photo(id) ON DELETE CASCADE;

ALTER TABLE rate_user
    ADD CONSTRAINT u_rate_photo FOREIGN KEY (id_foto) REFERENCES photo(id) ON DELETE CASCADE;

ALTER TABLE rate_moder
    ADD CONSTRAINT m_rate_users FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE rate_user
    ADD CONSTRAINT u_rate_users FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE CASCADE;


