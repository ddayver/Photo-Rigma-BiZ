--
-- Файл сгенерирован с помощью SQLiteStudio v3.4.17 в пн мая 12 00:15:28 2025
--
-- Использованная кодировка текста: UTF-8
--
PRAGMA foreign_keys = off;
BEGIN TRANSACTION;

-- Таблица: category
DROP TABLE IF EXISTS category;
CREATE TABLE IF NOT EXISTS category (
  id INTEGER PRIMARY KEY AUTOINCREMENT, -- Идентификатор
  folder TEXT NOT NULL, -- Имя папки раздела
  name TEXT NOT NULL, -- Название раздела
  description TEXT NOT NULL -- Описание раздела
);
INSERT INTO category (id, folder, name, description) VALUES (0, 'user', 'Пользовательский альбом', 'Персональный пользовательский альбом');

-- Таблица: category_fts
DROP TABLE IF EXISTS category_fts;
CREATE VIRTUAL TABLE IF NOT EXISTS category_fts USING fts5(
    name,
    description,
    content='category',
    content_rowid='id',
    tokenize='porter'
);
INSERT INTO category_fts (name, description) VALUES ('Пользовательский альбом', 'Персональный пользовательский альбом');

-- Таблица: change_timestamp
DROP TABLE IF EXISTS change_timestamp;
CREATE TABLE IF NOT EXISTS change_timestamp (
    table_name TEXT PRIMARY KEY, -- Имя таблицы
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP -- Время последнего обновления
);
INSERT INTO change_timestamp (table_name, last_update) VALUES ('config', '2025-05-11 21:14:49');

-- Таблица: config
DROP TABLE IF EXISTS config;
CREATE TABLE IF NOT EXISTS config (
  name TEXT PRIMARY KEY, -- Имя параметра
  value TEXT NOT NULL -- Значение параметра
);
INSERT INTO config (name, value) VALUES ('max_avatar_h', '100');
INSERT INTO config (name, value) VALUES ('max_avatar_w', '100');
INSERT INTO config (name, value) VALUES ('max_rate', '2');
INSERT INTO config (name, value) VALUES ('temp_photo_h', '200');
INSERT INTO config (name, value) VALUES ('temp_photo_w', '200');
INSERT INTO config (name, value) VALUES ('copyright_year', '2008-2025');
INSERT INTO config (name, value) VALUES ('left_panel', '250');
INSERT INTO config (name, value) VALUES ('right_panel', '250');
INSERT INTO config (name, value) VALUES ('max_photo_h', '480');
INSERT INTO config (name, value) VALUES ('best_user', '5');
INSERT INTO config (name, value) VALUES ('last_news', '5');
INSERT INTO config (name, value) VALUES ('max_file_size', '5M');
INSERT INTO config (name, value) VALUES ('max_photo_w', '640');
INSERT INTO config (name, value) VALUES ('time_user_online', '900');
INSERT INTO config (name, value) VALUES ('gal_width', '95%');
INSERT INTO config (name, value) VALUES ('theme', 'default');
INSERT INTO config (name, value) VALUES ('copyright_url', 'https://rigma.biz/');
INSERT INTO config (name, value) VALUES ('title_name', 'Rigma Foto');
INSERT INTO config (name, value) VALUES ('meta_description', 'Rigma.BiZ - фотогалерея Gold Rigma');
INSERT INTO config (name, value) VALUES ('meta_keywords', 'Rigma.BiZ photo gallery Gold Rigma');
INSERT INTO config (name, value) VALUES ('language', 'russian');
INSERT INTO config (name, value) VALUES ('copyright_text', 'Проекты Rigma.BiZ');
INSERT INTO config (name, value) VALUES ('title_description', 'Фотогалерея Rigma и Co');
INSERT INTO config (name, value) VALUES ('timezone', 'UTC');

-- Таблица: db_version
DROP TABLE IF EXISTS db_version;
CREATE TABLE IF NOT EXISTS db_version (
  ver TEXT PRIMARY KEY -- Номер версии
);
INSERT INTO db_version (ver) VALUES ('0.4.4');

-- Таблица: groups
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

-- Таблица: menu
DROP TABLE IF EXISTS menu;
CREATE TABLE IF NOT EXISTS menu (
  id INTEGER PRIMARY KEY AUTOINCREMENT, -- Порядковый номер пункта меню
  action TEXT NOT NULL, -- Фрагмент из URL, указывающий, что данный пункт меню должен быть неактивным (текущим)
  url_action TEXT NOT NULL, -- URL перехода при выборе пункта меню
  name_action TEXT NOT NULL, -- Пункт из массива $lang, содержащий название пункта меню
  short INTEGER NOT NULL DEFAULT 0, -- Использовать пункт в кратком (верхнем) меню
  long INTEGER NOT NULL DEFAULT 0, -- Использовать пункт в длинном (боковом) меню
  user_login INTEGER DEFAULT NULL, -- Проверка - зарегистрирован ли пользователь
  user_access TEXT DEFAULT NULL -- Дополнительные права
);
INSERT INTO menu (id, action, url_action, name_action, short, long, user_login, user_access) VALUES (1, 'main', './', 'home', 1, 1, NULL, NULL);
INSERT INTO menu (id, action, url_action, name_action, short, long, user_login, user_access) VALUES (2, 'regist', '?action=profile&subact=regist', 'regist', 1, 1, 0, NULL);
INSERT INTO menu (id, action, url_action, name_action, short, long, user_login, user_access) VALUES (3, 'category', '?action=category', 'category', 1, 1, NULL, NULL);
INSERT INTO menu (id, action, url_action, name_action, short, long, user_login, user_access) VALUES (4, 'user_category', '?action=category&cat=user', 'user_category', 0, 1, NULL, NULL);
INSERT INTO menu (id, action, url_action, name_action, short, long, user_login, user_access) VALUES (5, 'you_category', '?action=category&cat=user&id=curent', 'you_category', 0, 1, 1, 'cat_user');
INSERT INTO menu (id, action, url_action, name_action, short, long, user_login, user_access) VALUES (6, 'upload', '?action=photo&subact=upload', 'upload', 0, 1, 1, 'pic_upload');
INSERT INTO menu (id, action, url_action, name_action, short, long, user_login, user_access) VALUES (7, 'add_category', '?action=category&subact=add', 'add_category', 0, 1, 1, 'cat_moderate');
INSERT INTO menu (id, action, url_action, name_action, short, long, user_login, user_access) VALUES (8, 'search', '?action=search', 'search', 1, 1, NULL, NULL);
INSERT INTO menu (id, action, url_action, name_action, short, long, user_login, user_access) VALUES (9, 'news', '?action=news', 'news', 1, 1, NULL, 'news_view');
INSERT INTO menu (id, action, url_action, name_action, short, long, user_login, user_access) VALUES (10, 'news_add', '?action=news&subact=add', 'news_add', 0, 1, 1, 'news_add');
INSERT INTO menu (id, action, url_action, name_action, short, long, user_login, user_access) VALUES (11, 'profile', '?action=profile&subact=profile', 'profile', 1, 1, 1, NULL);
INSERT INTO menu (id, action, url_action, name_action, short, long, user_login, user_access) VALUES (12, 'admin', '?action=admin', 'admin', 1, 1, 1, 'admin');
INSERT INTO menu (id, action, url_action, name_action, short, long, user_login, user_access) VALUES (13, 'logout', '?action=profile&subact=logout', 'logout', 1, 1, 1, NULL);

-- Таблица: news
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

-- Таблица: news_fts
DROP TABLE IF EXISTS news_fts;
CREATE VIRTUAL TABLE IF NOT EXISTS news_fts USING fts5(
    name_post,
    text_post,
    content='news',
    content_rowid='id',
    tokenize='porter'
);

-- Таблица: photo
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

-- Таблица: photo_fts
DROP TABLE IF EXISTS photo_fts;
CREATE VIRTUAL TABLE IF NOT EXISTS photo_fts USING fts5(
    name,
    description,
    content='photo',
    content_rowid='id',
    tokenize='porter'
);

-- Таблица: query_logs
DROP TABLE IF EXISTS query_logs;
CREATE TABLE IF NOT EXISTS query_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT, -- Уникальный идентификатор записи
  query_hash TEXT NOT NULL UNIQUE, -- Хэш запроса для проверки дублирования
  query_text TEXT NOT NULL, -- Текст SQL-запроса
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Время первого логирования
  last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Время последнего использования
  usage_count INTEGER NOT NULL DEFAULT 1, -- Количество использований запроса
  reason TEXT DEFAULT 'other', -- Причина логирования: медленный запрос, отсутствие плейсхолдеров или другое
  execution_time REAL DEFAULT NULL -- Время выполнения запроса (в миллисекундах)
);

-- Таблица: rate_moder
DROP TABLE IF EXISTS rate_moder;
CREATE TABLE IF NOT EXISTS rate_moder (
    id_foto INTEGER NOT NULL,
    id_user INTEGER NOT NULL,
    rate INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (id_foto, id_user),
    FOREIGN KEY (id_foto) REFERENCES photo(id) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE CASCADE
);

-- Таблица: rate_user
DROP TABLE IF EXISTS rate_user;
CREATE TABLE IF NOT EXISTS rate_user (
    id_foto INTEGER NOT NULL,
    id_user INTEGER NOT NULL,
    rate INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (id_foto, id_user),
    FOREIGN KEY (id_foto) REFERENCES photo(id) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE CASCADE
);

-- Таблица: user_bans
DROP TABLE IF EXISTS user_bans;
CREATE TABLE IF NOT EXISTS user_bans (
    user_id INTEGER NOT NULL PRIMARY KEY,
    banned INTEGER NOT NULL DEFAULT 1,        
    reason TEXT,
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Таблица: users
DROP TABLE IF EXISTS users;
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
INSERT INTO users (id, login, password, real_name, email, email_confirmed, avatar, language, theme, timezone, activation, date_regist, date_last_activ, date_last_logout, deleted_at, permanently_deleted, token, token_expires_at, group_id, allow_newsletter, user_rights, other_params) VALUES (1, 'admin', '$2y$12$66PqD9l3yDp3qj40j.rXNeh7JGzjt/AKkizosLmdbyjB7pQmt6UxW', 'Администратор', 'admin@rigma.biz', 1, 'no_avatar.jpg', 'russian', 'default', 'UTC', 1, '2009-01-20 12:31:35', '2025-04-07 15:10:36', '2025-04-05 11:21:57', NULL, 0, NULL, NULL, 3, 0, '{"pic_view": true, "pic_rate_user": true, "pic_rate_moder": true, "pic_upload": true, "pic_moderate": true, "cat_moderate": true, "cat_user": true, "comment_view": true, "comment_add": true, "comment_moderate": true, "news_view": true, "news_add": true, "news_moderate": true, "admin": true}', NULL);

-- Таблица: users_fts
DROP TABLE IF EXISTS users_fts;
CREATE VIRTUAL TABLE IF NOT EXISTS users_fts USING fts5(
    login,
    real_name,
    email,
    content='users',
    content_rowid='id',
    tokenize='porter'
);
INSERT INTO users_fts (login, real_name, email) VALUES ('admin', 'Администратор', 'admin@rigma.biz');

-- Индекс: idx_config_value
DROP INDEX IF EXISTS idx_config_value;
CREATE INDEX IF NOT EXISTS "idx_config_value" ON "config" (
	"value"
);

-- Индекс: idx_menu_long
DROP INDEX IF EXISTS idx_menu_long;
CREATE INDEX IF NOT EXISTS "idx_menu_long" ON "menu" (
	"long"
);

-- Индекс: idx_menu_short
DROP INDEX IF EXISTS idx_menu_short;
CREATE INDEX IF NOT EXISTS "idx_menu_short" ON "menu" (
	"short"
);

-- Индекс: idx_photo_date_upload
DROP INDEX IF EXISTS idx_photo_date_upload;
CREATE INDEX IF NOT EXISTS idx_photo_date_upload ON photo (date_upload);

-- Индекс: idx_photo_user_upload_group
DROP INDEX IF EXISTS idx_photo_user_upload_group;
CREATE INDEX IF NOT EXISTS idx_photo_user_upload_group ON photo (user_upload, id);

-- Индекс: idx_rate_moder_id_user
DROP INDEX IF EXISTS idx_rate_moder_id_user;
CREATE INDEX IF NOT EXISTS idx_rate_moder_id_user ON rate_moder (id_user);

-- Индекс: idx_rate_user_id_user
DROP INDEX IF EXISTS idx_rate_user_id_user;
CREATE INDEX IF NOT EXISTS idx_rate_user_id_user ON rate_user (id_user);

-- Индекс: idx_user_bans_expires
DROP INDEX IF EXISTS idx_user_bans_expires;
CREATE INDEX IF NOT EXISTS idx_user_bans_expires ON user_bans(expires_at);

-- Индекс: idx_user_bans_user_id
DROP INDEX IF EXISTS idx_user_bans_user_id;
CREATE INDEX IF NOT EXISTS idx_user_bans_user_id ON user_bans(user_id);

-- Индекс: idx_users_allow_newsletter
DROP INDEX IF EXISTS idx_users_allow_newsletter;
CREATE INDEX IF NOT EXISTS idx_users_allow_newsletter ON users(allow_newsletter);

-- Индекс: idx_users_date_last_activ
DROP INDEX IF EXISTS idx_users_date_last_activ;
CREATE INDEX IF NOT EXISTS idx_users_date_last_activ ON users (date_last_activ);

-- Индекс: idx_users_deleted_at
DROP INDEX IF EXISTS idx_users_deleted_at;
CREATE INDEX IF NOT EXISTS idx_users_deleted_at ON users (deleted_at);

-- Индекс: idx_users_group_id
DROP INDEX IF EXISTS idx_users_group_id;
CREATE INDEX IF NOT EXISTS idx_users_group_id ON users (group_id);

-- Индекс: idx_users_token
DROP INDEX IF EXISTS idx_users_token;
CREATE INDEX IF NOT EXISTS idx_users_token ON users(token);

-- Индекс: idx_users_token_expires
DROP INDEX IF EXISTS idx_users_token_expires;
CREATE INDEX IF NOT EXISTS idx_users_token_expires ON users(token_expires_at);

-- Представление: random_photo
DROP VIEW IF EXISTS random_photo;
CREATE VIEW IF NOT EXISTS random_photo AS
SELECT photo.id AS id, photo.file AS file, photo.name AS name, photo.description AS description, photo.category AS category,
       photo.rate_user AS rate_user, photo.rate_moder AS rate_moder, photo.user_upload AS user_upload
FROM photo
WHERE photo.id = (
    SELECT photo.id FROM photo ORDER BY RANDOM() LIMIT 1
);

-- Представление: users_online
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

-- Триггер: category_fts_after_insert
DROP TRIGGER IF EXISTS category_fts_after_insert;
CREATE TRIGGER IF NOT EXISTS category_fts_after_insert AFTER INSERT ON category BEGIN
    INSERT INTO category_fts(rowid, name, description)
    VALUES (new.id, new.name, new.description);
END;

-- Триггер: category_fts_after_update
DROP TRIGGER IF EXISTS category_fts_after_update;
CREATE TRIGGER IF NOT EXISTS category_fts_after_update AFTER UPDATE ON category BEGIN
    DELETE FROM category_fts WHERE rowid = old.id;
    INSERT INTO category_fts(rowid, name, description)
    VALUES (new.id, new.name, new.description);
END;

-- Триггер: category_fts_before_delete
DROP TRIGGER IF EXISTS category_fts_before_delete;
CREATE TRIGGER IF NOT EXISTS category_fts_before_delete BEFORE DELETE ON category BEGIN
    DELETE FROM category_fts WHERE rowid = old.id;
END;

-- Триггер: news_fts_after_insert
DROP TRIGGER IF EXISTS news_fts_after_insert;
CREATE TRIGGER IF NOT EXISTS news_fts_after_insert AFTER INSERT ON news BEGIN
    INSERT INTO news_fts(rowid, name_post, text_post)
    VALUES (new.id, new.name_post, new.text_post);
END;

-- Триггер: news_fts_after_update
DROP TRIGGER IF EXISTS news_fts_after_update;
CREATE TRIGGER IF NOT EXISTS news_fts_after_update AFTER UPDATE ON news BEGIN
    DELETE FROM news_fts WHERE rowid = old.id;
    INSERT INTO news_fts(rowid, name_post, text_post)
    VALUES (new.id, new.name_post, new.text_post);
END;

-- Триггер: news_fts_before_delete
DROP TRIGGER IF EXISTS news_fts_before_delete;
CREATE TRIGGER IF NOT EXISTS news_fts_before_delete BEFORE DELETE ON news BEGIN
    DELETE FROM news_fts WHERE rowid = old.id;
END;

-- Триггер: photo_fts_after_insert
DROP TRIGGER IF EXISTS photo_fts_after_insert;
CREATE TRIGGER IF NOT EXISTS photo_fts_after_insert AFTER INSERT ON photo BEGIN
    INSERT INTO photo_fts(rowid, name, description)
    VALUES (new.id, new.name, new.description);
END;

-- Триггер: photo_fts_after_update
DROP TRIGGER IF EXISTS photo_fts_after_update;
CREATE TRIGGER IF NOT EXISTS photo_fts_after_update AFTER UPDATE ON photo BEGIN
    DELETE FROM photo_fts WHERE rowid = old.id;
    INSERT INTO photo_fts(rowid, name, description)
    VALUES (new.id, new.name, new.description);
END;

-- Триггер: photo_fts_before_delete
DROP TRIGGER IF EXISTS photo_fts_before_delete;
CREATE TRIGGER IF NOT EXISTS photo_fts_before_delete BEFORE DELETE ON photo BEGIN
    DELETE FROM photo_fts WHERE rowid = old.id;
END;

-- Триггер: trg_config_delete
DROP TRIGGER IF EXISTS trg_config_delete;
CREATE TRIGGER IF NOT EXISTS trg_config_delete
AFTER DELETE ON config
BEGIN
    INSERT OR REPLACE INTO change_timestamp (table_name, last_update)
    VALUES ('config', CURRENT_TIMESTAMP);
END;

-- Триггер: trg_config_insert
DROP TRIGGER IF EXISTS trg_config_insert;
CREATE TRIGGER IF NOT EXISTS trg_config_insert
AFTER INSERT ON config
BEGIN
    INSERT OR REPLACE INTO change_timestamp (table_name, last_update)
    VALUES ('config', CURRENT_TIMESTAMP);
END;

-- Триггер: trg_config_update
DROP TRIGGER IF EXISTS trg_config_update;
CREATE TRIGGER IF NOT EXISTS trg_config_update
AFTER UPDATE ON config
BEGIN
    INSERT OR REPLACE INTO change_timestamp (table_name, last_update)
    VALUES ('config', CURRENT_TIMESTAMP);
END;

-- Триггер: trg_prevent_deletion_category
DROP TRIGGER IF EXISTS trg_prevent_deletion_category;
CREATE TRIGGER IF NOT EXISTS trg_prevent_deletion_category BEFORE DELETE ON category
FOR EACH ROW
BEGIN
    -- Проверяем, является ли id служебным
    SELECT RAISE(IGNORE) WHERE OLD.id = 0;
END;

-- Триггер: trg_prevent_deletion_groups
DROP TRIGGER IF EXISTS trg_prevent_deletion_groups;
CREATE TRIGGER IF NOT EXISTS trg_prevent_deletion_groups
BEFORE DELETE ON groups
FOR EACH ROW
WHEN OLD.id BETWEEN 0 AND 3
BEGIN
    SELECT RAISE(IGNORE);
END;

-- Триггер: trg_update_rate_moder_after_delete
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

-- Триггер: trg_update_rate_moder_after_insert
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

-- Триггер: trg_update_rate_user_after_delete
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

-- Триггер: trg_update_rate_user_after_insert
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

-- Триггер: users_fts_after_insert
DROP TRIGGER IF EXISTS users_fts_after_insert;
CREATE TRIGGER IF NOT EXISTS users_fts_after_insert AFTER INSERT ON users BEGIN
    INSERT INTO users_fts(rowid, login, real_name, email)
    VALUES (new.id, new.login, new.real_name, new.email);
END;

-- Триггер: users_fts_after_update
DROP TRIGGER IF EXISTS users_fts_after_update;
CREATE TRIGGER IF NOT EXISTS users_fts_after_update AFTER UPDATE ON users BEGIN
    DELETE FROM users_fts WHERE rowid = old.id;
    INSERT INTO users_fts(rowid, login, real_name, email)
    VALUES (new.id, new.login, new.real_name, new.email);
END;

-- Триггер: users_fts_before_delete
DROP TRIGGER IF EXISTS users_fts_before_delete;
CREATE TRIGGER IF NOT EXISTS users_fts_before_delete BEFORE DELETE ON users BEGIN
    DELETE FROM users_fts WHERE rowid = old.id;
END;

COMMIT TRANSACTION;
PRAGMA foreign_keys = on;
