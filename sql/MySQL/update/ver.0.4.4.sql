-- 1. Внедряем функционал мягкого и окончательного удаления пользователей.

-- Добавление поля deleted_at: дата и время мягкого удаления (NULL, если аккаунт активен)
ALTER TABLE users
    ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL COMMENT 'Дата и время мягкого удаления пользователя' AFTER date_last_logout;

-- Добавление поля permanently_deleted: флаг окончательного удаления (0 - нет, 1 - да)
ALTER TABLE users
    ADD COLUMN permanently_deleted TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Флаг окончательного удаления пользователя' AFTER deleted_at;

-- Добавляем индекс на deleted_at для фоновых задач
CREATE INDEX idx_users_deleted_at ON users(deleted_at);

-- 2. Добавление дополнительных полей в таблицу users для будущих функций
-- Добавление поля activation: Флаг активации аккаунта
ALTER TABLE users
    ADD COLUMN activation TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Флаг активации аккаунта' AFTER theme;

-- Добавление поля token: Для временных токенов (восстановление пароля, активация)
ALTER TABLE users
    ADD COLUMN token VARCHAR(255) NULL DEFAULT NULL COMMENT 'Для временных токенов' AFTER permanently_deleted;

-- Добавление поля token_expires_at: Время истечения токена
ALTER TABLE users
    ADD COLUMN token_expires_at DATETIME NULL DEFAULT NULL COMMENT 'Время истечения токена' AFTER token;

-- Добавление поля email_confirmed: Подтверждён ли email
ALTER TABLE users
    ADD COLUMN email_confirmed TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Подтверждён ли email' AFTER email;

-- Добавление поля allow_newsletter: Разрешает ли пользователь рассылку
ALTER TABLE users
    ADD COLUMN allow_newsletter TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Разрешает ли пользователь рассылку' AFTER group_id;

-- Добавление поля timezone: Часовой пояс пользователя
ALTER TABLE users
    ADD COLUMN timezone VARCHAR(50) NOT NULL DEFAULT 'UTC' COMMENT 'Часовой пояс пользователя' AFTER theme;

-- Добавление поля other_params: Остальные параметрыпользователя
ALTER TABLE users
    ADD other_params JSON NULL DEFAULT NULL COMMENT 'Прочие параметры пользователя' AFTER user_rights;

-- Индексы для новых полей + удаление лишнего индекса
CREATE INDEX idx_users_token ON users(token);
CREATE INDEX idx_users_token_expires ON users(token_expires_at);
CREATE INDEX idx_users_allow_newsletter ON users(allow_newsletter);
ALTER TABLE users DROP INDEX user_rights;

-- 3. Обновление: все существующие аккаунты считаем активированными и подтвердившими email
UPDATE users SET activation = 1;
UPDATE users SET email_confirmed = 1;

-- 4. Удаление темы 'old' в связи с окончанием её поддержки, изменение имени переменной 'themes' на 'theme'
UPDATE `users` SET `theme` = 'default' WHERE `theme` = 'old';
UPDATE `config` SET `value` = 'default' WHERE `name` = 'themes' AND `value` = 'old';
UPDATE `config` SET `name` = 'theme' WHERE `config`.`name` = 'themes';

-- 5. Добавление настройки часового пояса сервера
INSERT INTO `config` (`name`, `value`) VALUES ('timezone', 'UTC') ON DUPLICATE KEY UPDATE `value` = 'UTC';

-- 6. Добавление таблицы для отправления пользователей в бан
DROP TABLE IF EXISTS user_bans;
CREATE TABLE IF NOT EXISTS user_bans (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY COMMENT 'Идентификатор пользователя',
    banned BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Флаг: пользователь заблокирован',
    reason TEXT COMMENT 'Причина блокировки',
    expires_at DATETIME DEFAULT NULL COMMENT 'Дата окончания бана (если временный)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата и время установки бана',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Информация о бане пользователей';

CREATE INDEX idx_user_bans_expires ON user_bans(expires_at);
CREATE INDEX idx_user_bans_user_id ON user_bans(user_id);

-- 7. Обновляем представление для получение списка онлайн-пользователей
DROP TABLE IF EXISTS `users_online`;
DROP VIEW IF EXISTS `users_online`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `users_online` AS
SELECT
    u.id,
    u.real_name,
    CASE
        WHEN b.user_id IS NOT NULL THEN 1
        ELSE 0
    END AS banned
FROM users u
LEFT JOIN user_bans b ON u.id = b.user_id AND b.banned = 1
WHERE
    u.date_last_activ >= CURRENT_TIMESTAMP - INTERVAL (
        SELECT config.value FROM config WHERE config.name = 'time_user_online'
    ) SECOND
    AND u.activation = 1
    AND u.email_confirmed = 1
    AND u.deleted_at IS NULL
    AND u.permanently_deleted = 0;

-- 8. Изменяем поле `id` в таблице `groups`: включаем AUTO_INCREMENT.
ALTER TABLE users DROP FOREIGN KEY users_groups;

DROP TABLE IF EXISTS `groups`;
CREATE TABLE IF NOT EXISTS `groups` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор группы',
  `name` varchar(50) NOT NULL COMMENT 'Название группы',
  `user_rights` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Права доступа' CHECK (json_valid(`user_rights`)),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Таблица групп пользователей и прав доступа';

INSERT INTO `groups` (`id`, `name`, `user_rights`) VALUES
(0, 'Гость', '{\"pic_view\":true,\"pic_rate_user\":false,\"pic_rate_moder\":false,\"pic_upload\":false,\"pic_moderate\":false,\"cat_moderate\":false,\"cat_user\":false,\"comment_view\":true,\"comment_add\":false,\"comment_moderate\":false,\"news_view\":true,\"news_add\":false,\"news_moderate\":false,\"admin\":false}');
UPDATE `groups` SET `id`= 0  WHERE `id` = 1;
ALTER TABLE groups AUTO_INCREMENT = 1;
INSERT INTO `groups` (`id`, `name`, `user_rights`) VALUES
(1, 'Пользователь', '{\"pic_view\": true, \"pic_rate_user\": true, \"pic_rate_moder\": false, \"pic_upload\": true, \"pic_moderate\": false, \"cat_moderate\": false, \"cat_user\": true, \"comment_view\": true, \"comment_add\": true, \"comment_moderate\": false, \"news_view\": true, \"news_add\": false, \"news_moderate\": false, \"admin\": false}'),
(2, 'Модератор', '{\"pic_view\": true, \"pic_rate_user\": false, \"pic_rate_moder\": true, \"pic_upload\": true, \"pic_moderate\": true, \"cat_moderate\": true, \"cat_user\": true, \"comment_view\": true, \"comment_add\": true, \"comment_moderate\": true, \"news_view\": true, \"news_add\": true, \"news_moderate\": true, \"admin\": false}'),
(3, 'Администратор', '{\"pic_view\": true, \"pic_rate_user\": true, \"pic_rate_moder\": true, \"pic_upload\": true, \"pic_moderate\": true, \"cat_moderate\": true, \"cat_user\": true, \"comment_view\": true, \"comment_add\": true, \"comment_moderate\": true, \"news_view\": true, \"news_add\": true, \"news_moderate\": true, \"admin\": true}');

ALTER TABLE users ADD CONSTRAINT fk_users_group_id
FOREIGN KEY (group_id) REFERENCES groups(id);

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

-- Final
TRUNCATE TABLE db_version;
INSERT INTO db_version (ver) VALUES ('0.4.4');

