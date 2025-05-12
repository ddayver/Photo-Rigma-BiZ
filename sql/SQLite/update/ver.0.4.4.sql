-- Begin
PRAGMA foreign_keys = off;

-- 1. Работа с таблицей users и её зависимостями
DROP TRIGGER IF EXISTS users_fts_after_insert;
DROP TRIGGER IF EXISTS users_fts_after_update;
DROP TRIGGER IF EXISTS users_fts_before_delete;

ALTER TABLE users RENAME TO users_old;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    login TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    real_name TEXT NOT NULL,
    email TEXT NOT NULL,
    email_confirmed INTEGER NOT NULL DEFAULT 0,
    avatar TEXT NOT NULL DEFAULT 'no_avatar.jpg',
    language TEXT NOT NULL DEFAULT 'russian',
    theme TEXT NOT NULL DEFAULT 'default',
    timezone TEXT NOT NULL DEFAULT 'UTC',
    activation INTEGER NOT NULL DEFAULT 0,
    date_regist TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_last_activ TIMESTAMP DEFAULT NULL,
    date_last_logout TIMESTAMP DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    permanently_deleted INTEGER NOT NULL DEFAULT 0,
    token TEXT DEFAULT NULL,
    token_expires_at DATETIME DEFAULT NULL,
    group_id INTEGER NOT NULL DEFAULT 0,
    allow_newsletter INTEGER NOT NULL DEFAULT 0,
    user_rights TEXT DEFAULT NULL,
    other_params TEXT DEFAULT NULL
);

INSERT INTO users (
    id, login, password, real_name, email, email_confirmed, avatar, language, theme, activation,
    date_regist, date_last_activ, date_last_logout, group_id, allow_newsletter, user_rights
) SELECT
    id, login, password, real_name, email, 1 AS email_confirmed, avatar, language, theme, 1 AS activation,
    date_regist, date_last_activ, date_last_logout, group_id, 0 AS allow_newsletter, user_rights
FROM users_old;

DROP TABLE users_old;

-- 2. Индексы для users
DROP INDEX IF EXISTS idx_users_group_id;
CREATE INDEX IF NOT EXISTS idx_users_group_id ON users (group_id);

DROP INDEX IF EXISTS idx_users_date_last_activ;
CREATE INDEX IF NOT EXISTS idx_users_date_last_activ ON users (date_last_activ);

DROP INDEX IF EXISTS idx_users_deleted_at;
CREATE INDEX IF NOT EXISTS idx_users_deleted_at ON users (deleted_at);

DROP INDEX IF EXISTS idx_users_token;
CREATE INDEX IF NOT EXISTS idx_users_token ON users(token);

DROP INDEX IF EXISTS idx_users_token_expires;
CREATE INDEX IF NOT EXISTS idx_users_token_expires ON users(token_expires_at);

DROP INDEX IF EXISTS idx_users_allow_newsletter;
CREATE INDEX IF NOT EXISTS idx_users_allow_newsletter ON users(allow_newsletter);

-- 3. Триггеры FTS для users
DROP TRIGGER IF EXISTS users_fts_after_insert;
CREATE TRIGGER IF NOT EXISTS users_fts_after_insert AFTER INSERT ON users BEGIN
    INSERT INTO users_fts(rowid, login, real_name, email)
    VALUES (new.id, new.login, new.real_name, new.email);
END;

DROP TRIGGER IF EXISTS users_fts_after_update;
CREATE TRIGGER IF NOT EXISTS users_fts_after_update AFTER UPDATE ON users BEGIN
    DELETE FROM users_fts WHERE rowid = old.id;
    INSERT INTO users_fts(rowid, login, real_name, email)
    VALUES (new.id, new.login, new.real_name, new.email);
END;

DROP TRIGGER IF EXISTS users_fts_before_delete;
CREATE TRIGGER IF NOT EXISTS users_fts_before_delete BEFORE DELETE ON users BEGIN
    DELETE FROM users_fts WHERE rowid = old.id;
END;

-- 4. Пересоздание news и FTS
CREATE TEMPORARY TABLE IF NOT EXISTS temp_news_backup (
    id INTEGER PRIMARY KEY,
    data_post DATE NOT NULL,
    data_last_edit TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_post INTEGER NOT NULL,
    name_post TEXT NOT NULL,
    text_post TEXT NOT NULL
);
DELETE FROM temp_news_backup;
INSERT INTO temp_news_backup SELECT * FROM news;

DROP TABLE IF EXISTS news;
CREATE TABLE IF NOT EXISTS news (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    data_post DATE NOT NULL,
    data_last_edit TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_post INTEGER NOT NULL,
    name_post TEXT NOT NULL,
    text_post TEXT NOT NULL,
    FOREIGN KEY (user_post) REFERENCES users (id)
);

INSERT OR IGNORE INTO news (id, data_post, data_last_edit, user_post, name_post, text_post)
SELECT id, data_post, data_last_edit, user_post, name_post, text_post FROM temp_news_backup;
UPDATE sqlite_sequence
SET seq = (SELECT MAX(id) FROM news)
WHERE name = 'news';
DROP TABLE IF EXISTS temp_news_backup;

DROP TABLE IF EXISTS news_fts;
CREATE VIRTUAL TABLE IF NOT EXISTS news_fts USING fts5(
    name_post,
    text_post,
    content='news',
    content_rowid='id',
    tokenize='porter'
);

DROP TRIGGER IF EXISTS news_fts_after_insert;
CREATE TRIGGER IF NOT EXISTS news_fts_after_insert AFTER INSERT ON news BEGIN
    INSERT INTO news_fts(rowid, name_post, text_post)
    VALUES (new.id, new.name_post, new.text_post);
END;

DROP TRIGGER IF EXISTS news_fts_after_update;
CREATE TRIGGER IF NOT EXISTS news_fts_after_update AFTER UPDATE ON news BEGIN
    DELETE FROM news_fts WHERE rowid = old.id;
    INSERT INTO news_fts(rowid, name_post, text_post)
    VALUES (new.id, new.name_post, new.text_post);
END;

DROP TRIGGER IF EXISTS news_fts_before_delete;
CREATE TRIGGER IF NOT EXISTS news_fts_before_delete BEFORE DELETE ON news BEGIN
    DELETE FROM news_fts WHERE rowid = old.id;
END;

-- 5. Пересоздание photo и FTS
CREATE TEMPORARY TABLE IF NOT EXISTS temp_photo_backup (
    id INTEGER PRIMARY KEY,
    file TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT NOT NULL,
    category INTEGER NOT NULL,
    date_upload TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_upload INTEGER NOT NULL,
    rate_user REAL NOT NULL DEFAULT 0,
    rate_moder REAL NOT NULL DEFAULT 0
);
DELETE FROM temp_photo_backup;
INSERT INTO temp_photo_backup SELECT * FROM photo;

DROP TABLE IF EXISTS photo;
CREATE TABLE IF NOT EXISTS photo (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT NOT NULL,
    category INTEGER NOT NULL,
    date_upload TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_upload INTEGER NOT NULL,
    rate_user REAL NOT NULL DEFAULT 0,
    rate_moder REAL NOT NULL DEFAULT 0,
    FOREIGN KEY (category) REFERENCES category(id),
    FOREIGN KEY (user_upload) REFERENCES users(id)
);

INSERT OR IGNORE INTO photo (id, file, name, description, category, date_upload, user_upload, rate_user, rate_moder)
SELECT id, file, name, description, category, date_upload, user_upload, rate_user, rate_moder
FROM temp_photo_backup;

UPDATE sqlite_sequence
SET seq = (SELECT MAX(id) FROM photo)
WHERE name = 'photo';
DROP TABLE IF EXISTS temp_photo_backup;

DROP TABLE IF EXISTS photo_fts;
CREATE VIRTUAL TABLE IF NOT EXISTS photo_fts USING fts5(
    name,
    description,
    content='photo',
    content_rowid='id',
    tokenize='porter'
);

DROP TRIGGER IF EXISTS photo_fts_after_insert;
CREATE TRIGGER IF NOT EXISTS photo_fts_after_insert AFTER INSERT ON photo BEGIN
    INSERT INTO photo_fts(rowid, name, description)
    VALUES (new.id, new.name, new.description);
END;

DROP TRIGGER IF EXISTS photo_fts_after_update;
CREATE TRIGGER IF NOT EXISTS photo_fts_after_update AFTER UPDATE ON photo BEGIN
    DELETE FROM photo_fts WHERE rowid = old.id;
    INSERT INTO photo_fts(rowid, name, description)
    VALUES (new.id, new.name, new.description);
END;

DROP TRIGGER IF EXISTS photo_fts_before_delete;
CREATE TRIGGER IF NOT EXISTS photo_fts_before_delete BEFORE DELETE ON photo BEGIN
    DELETE FROM photo_fts WHERE rowid = old.id;
END;

-- 6. Пересоздание rate_moder
CREATE TEMPORARY TABLE IF NOT EXISTS temp_rate_moder_backup (
    id_foto INTEGER NOT NULL,
    id_user INTEGER NOT NULL,
    rate INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (id_foto, id_user)
);
DELETE FROM temp_rate_moder_backup;
INSERT INTO temp_rate_moder_backup SELECT * FROM rate_moder;

DROP TABLE IF EXISTS rate_moder;
CREATE TABLE IF NOT EXISTS rate_moder (
    id_foto INTEGER NOT NULL,
    id_user INTEGER NOT NULL,
    rate INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (id_foto, id_user),
    FOREIGN KEY (id_foto) REFERENCES photo(id) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE CASCADE
);

INSERT OR IGNORE INTO rate_moder (id_foto, id_user, rate)
SELECT id_foto, id_user, rate FROM temp_rate_moder_backup;
DROP TABLE IF EXISTS temp_rate_moder_backup;

-- 7. Пересоздание rate_user
CREATE TEMPORARY TABLE IF NOT EXISTS temp_rate_user_backup (
    id_foto INTEGER NOT NULL,
    id_user INTEGER NOT NULL,
    rate INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (id_foto, id_user)
);
DELETE FROM temp_rate_user_backup;
INSERT INTO temp_rate_user_backup SELECT * FROM rate_user;

DROP TABLE IF EXISTS rate_user;
CREATE TABLE IF NOT EXISTS rate_user (
    id_foto INTEGER NOT NULL,
    id_user INTEGER NOT NULL,
    rate INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (id_foto, id_user),
    FOREIGN KEY (id_foto) REFERENCES photo(id) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE CASCADE
);

INSERT OR IGNORE INTO rate_user (id_foto, id_user, rate)
SELECT id_foto, id_user, rate FROM temp_rate_user_backup;
DROP TABLE IF EXISTS temp_rate_user_backup;

-- 8. Индексы
DROP INDEX IF EXISTS idx_photo_date_upload;
CREATE INDEX IF NOT EXISTS idx_photo_date_upload ON photo (date_upload);

DROP INDEX IF EXISTS idx_photo_user_upload_group;
CREATE INDEX IF NOT EXISTS idx_photo_user_upload_group ON photo (user_upload, id);

DROP INDEX IF EXISTS idx_rate_moder_id_user;
CREATE INDEX IF NOT EXISTS idx_rate_moder_id_user ON rate_moder (id_user);

DROP INDEX IF EXISTS idx_rate_user_id_user;
CREATE INDEX IF NOT EXISTS idx_rate_user_id_user ON rate_user (id_user);

-- 9. Представления
DROP VIEW IF EXISTS users_online;
CREATE VIEW IF NOT EXISTS users_online AS
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
    u.date_last_activ >= DATETIME('now', '-' || (SELECT value FROM config WHERE name = 'time_user_online') || ' seconds')
    AND u.activation = 1
    AND u.email_confirmed = 1
    AND u.deleted_at IS NULL
    AND u.permanently_deleted = 0;

DROP VIEW IF EXISTS random_photo;
CREATE VIEW IF NOT EXISTS random_photo AS
SELECT photo.id AS id, photo.file AS file, photo.name AS name, photo.description AS description, photo.category AS category,
       photo.rate_user AS rate_user, photo.rate_moder AS rate_moder, photo.user_upload AS user_upload
FROM photo
WHERE photo.id = (
    SELECT photo.id FROM photo ORDER BY RANDOM() LIMIT 1
);

-- 10. Триггеры для автоматического обновления рейтинга фото
DROP TRIGGER IF EXISTS trg_update_rate_moder_after_delete;
CREATE TRIGGER IF NOT EXISTS trg_update_rate_moder_after_delete
AFTER DELETE ON rate_moder
FOR EACH ROW
BEGIN
    UPDATE photo
    SET rate_moder = (
        SELECT IFNULL(AVG(rate), 0)
        FROM rate_moder
        WHERE id_foto = OLD.id_foto
    )
    WHERE id = OLD.id_foto
      AND EXISTS (SELECT 1 FROM photo WHERE id = OLD.id_foto);
END;

DROP TRIGGER IF EXISTS trg_update_rate_moder_after_insert;
CREATE TRIGGER IF NOT EXISTS trg_update_rate_moder_after_insert
AFTER INSERT ON rate_moder
FOR EACH ROW
BEGIN
    UPDATE photo
    SET rate_moder = (
        SELECT IFNULL(AVG(rate), 0)
        FROM rate_moder
        WHERE id_foto = NEW.id_foto
    )
    WHERE id = NEW.id_foto
      AND EXISTS (SELECT 1 FROM photo WHERE id = NEW.id_foto);
END;

DROP TRIGGER IF EXISTS trg_update_rate_user_after_delete;
CREATE TRIGGER IF NOT EXISTS trg_update_rate_user_after_delete
AFTER DELETE ON rate_user
FOR EACH ROW
BEGIN
    UPDATE photo
    SET rate_user = (
        SELECT IFNULL(AVG(rate), 0)
        FROM rate_user
        WHERE id_foto = OLD.id_foto
    )
    WHERE id = OLD.id_foto
      AND EXISTS (SELECT 1 FROM photo WHERE id = OLD.id_foto);
END;

DROP TRIGGER IF EXISTS trg_update_rate_user_after_insert;
CREATE TRIGGER IF NOT EXISTS trg_update_rate_user_after_insert
AFTER INSERT ON rate_user
FOR EACH ROW
BEGIN
    UPDATE photo
    SET rate_user = (
        SELECT IFNULL(AVG(rate), 0)
        FROM rate_user
        WHERE id_foto = NEW.id_foto
    )
    WHERE id = NEW.id_foto
      AND EXISTS (SELECT 1 FROM photo WHERE id = NEW.id_foto);
END;

-- 11.Добавление таблицы для отправления пользователей в бан
DROP TABLE IF EXISTS user_bans;

CREATE TABLE IF NOT EXISTS user_bans (
    user_id INTEGER NOT NULL PRIMARY KEY,
    banned INTEGER NOT NULL DEFAULT 1,        -- BOOLEAN = INTEGER в SQLite
    reason TEXT,
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Индекс на expires_at (для фоновых задач очистки банов)
CREATE INDEX IF NOT EXISTS idx_user_bans_expires ON user_bans(expires_at);

-- Индекс на user_id (уже есть как PK, но можно создать явно для совместимости с MySQL-скриптами)
CREATE INDEX IF NOT EXISTS idx_user_bans_user_id ON user_bans(user_id);

-- 12. Изменяем поле `id` в таблице `groups`: включаем AUTO_INCREMENT.
DROP TABLE IF EXISTS groups;

CREATE TABLE IF NOT EXISTS groups (
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    user_rights TEXT DEFAULT NULL
);

INSERT INTO groups (id, name, user_rights) VALUES (0, 'Гость', '{"pic_view":true,"pic_rate_user":false,"pic_rate_moder":false,"pic_upload":false,"pic_moderate":false,"cat_moderate":false,"cat_user":false,"comment_view":true,"comment_add":false,"comment_moderate":false,"news_view":true,"news_add":false,"news_moderate":false,"admin":false}');
INSERT INTO groups (id, name, user_rights) VALUES (1, 'Пользователь', '{"pic_view": true, "pic_rate_user": true, "pic_rate_moder": false, "pic_upload": true, "pic_moderate": false, "cat_moderate": false, "cat_user": true, "comment_view": true, "comment_add": true, "comment_moderate": false, "news_view": true, "news_add": false, "news_moderate": false, "admin": false}');
INSERT INTO groups (id, name, user_rights) VALUES (2, 'Модератор', '{"pic_view": true, "pic_rate_user": false, "pic_rate_moder": true, "pic_upload": true, "pic_moderate": true, "cat_moderate": true, "cat_user": true, "comment_view": true, "comment_add": true, "comment_moderate": true, "news_view": true, "news_add": true, "news_moderate": true, "admin": false}');
INSERT INTO groups (id, name, user_rights) VALUES (3, 'Администратор', '{"pic_view": true, "pic_rate_user": true, "pic_rate_moder": true, "pic_upload": true, "pic_moderate": true, "cat_moderate": true, "cat_user": true, "comment_view": true, "comment_add": true, "comment_moderate": true, "news_view": true, "news_add": true, "news_moderate": true, "admin": true}');

UPDATE sqlite_sequence SET seq = 3 WHERE name = 'groups';

DROP TRIGGER IF EXISTS trg_prevent_deletion_groups;

CREATE TRIGGER IF NOT EXISTS trg_prevent_deletion_groups
BEFORE DELETE ON groups
FOR EACH ROW
WHEN OLD.id BETWEEN 0 AND 3
BEGIN
    SELECT RAISE(IGNORE);
END;

-- 13. Удаление темы 'old' в связи с окончанием её поддержки, изменение имени переменной 'themes' на 'theme'
UPDATE users SET theme = 'default' WHERE theme = 'old';
UPDATE config SET value = 'default' WHERE name = 'themes' AND value = 'old';
UPDATE config SET name = 'theme' WHERE name = 'themes';

-- 14. Добавление настройки часового пояса сервера
INSERT OR REPLACE INTO config (name, value)
VALUES ('timezone', 'UTC');

-- Final: Обновление версии БД
DELETE FROM db_version;
INSERT INTO db_version (ver) VALUES ('0.4.4');
PRAGMA foreign_keys = on;
VACUUM;
PRAGMA integrity_check;
PRAGMA foreign_key_check;
