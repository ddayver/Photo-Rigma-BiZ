BEGIN TRANSACTION;
DROP TABLE IF EXISTS "category";
CREATE TABLE "category" (
	"id"	INTEGER,
	"folder"	TEXT NOT NULL,
	"name"	TEXT NOT NULL,
	"description"	TEXT NOT NULL,
	PRIMARY KEY("id" AUTOINCREMENT)
);
DROP TABLE IF EXISTS "change_timestamp";
CREATE TABLE "change_timestamp" (
	"table_name"	TEXT,
	"last_update"	TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY("table_name")
);
DROP TABLE IF EXISTS "config";
CREATE TABLE "config" (
	"name"	TEXT,
	"value"	TEXT NOT NULL,
	PRIMARY KEY("name")
);
DROP TABLE IF EXISTS "db_version";
CREATE TABLE "db_version" (
	"ver"	TEXT,
	PRIMARY KEY("ver")
);
DROP TABLE IF EXISTS "groups";
CREATE TABLE "groups" (
	"id"	INTEGER DEFAULT 0,
	"name"	TEXT NOT NULL,
	"user_rights"	TEXT DEFAULT NULL,
	PRIMARY KEY("id")
);
DROP TABLE IF EXISTS "menu";
CREATE TABLE "menu" (
	"id"	INTEGER,
	"action"	TEXT NOT NULL,
	"url_action"	TEXT NOT NULL,
	"name_action"	TEXT NOT NULL,
	"short"	INTEGER NOT NULL DEFAULT 0,
	"long"	INTEGER NOT NULL DEFAULT 0,
	"user_login"	INTEGER DEFAULT NULL,
	"user_access"	TEXT DEFAULT NULL,
	PRIMARY KEY("id" AUTOINCREMENT)
);
DROP TABLE IF EXISTS "news";
CREATE TABLE "news" (
	"id"	INTEGER,
	"data_post"	DATE NOT NULL,
	"data_last_edit"	TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	"user_post"	INTEGER NOT NULL,
	"name_post"	TEXT NOT NULL,
	"text_post"	TEXT NOT NULL,
	PRIMARY KEY("id" AUTOINCREMENT),
	FOREIGN KEY("user_post") REFERENCES "users"("id")
);
DROP TABLE IF EXISTS "photo";
CREATE TABLE "photo" (
	"id"	INTEGER,
	"file"	TEXT NOT NULL,
	"name"	TEXT NOT NULL,
	"description"	TEXT NOT NULL,
	"category"	INTEGER NOT NULL,
	"date_upload"	TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	"user_upload"	INTEGER NOT NULL,
	"rate_user"	REAL NOT NULL DEFAULT 0,
	"rate_moder"	REAL NOT NULL DEFAULT 0,
	PRIMARY KEY("id" AUTOINCREMENT),
	FOREIGN KEY("category") REFERENCES "category"("id"),
	FOREIGN KEY("user_upload") REFERENCES "users"("id")
);
DROP TABLE IF EXISTS "query_logs";
CREATE TABLE "query_logs" (
	"id"	INTEGER,
	"query_hash"	TEXT NOT NULL UNIQUE,
	"query_text"	TEXT NOT NULL,
	"created_at"	TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	"last_used_at"	TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	"usage_count"	INTEGER NOT NULL DEFAULT 1,
	"reason"	TEXT DEFAULT 'other',
	"execution_time"	REAL DEFAULT NULL,
	PRIMARY KEY("id" AUTOINCREMENT)
);
DROP TABLE IF EXISTS "rate_moder";
CREATE TABLE "rate_moder" (
	"id_foto"	INTEGER NOT NULL,
	"id_user"	INTEGER NOT NULL,
	"rate"	INTEGER NOT NULL DEFAULT 0,
	PRIMARY KEY("id_foto","id_user"),
	FOREIGN KEY("id_foto") REFERENCES "photo"("id") ON DELETE CASCADE,
	FOREIGN KEY("id_user") REFERENCES "users"("id") ON DELETE CASCADE
);
DROP TABLE IF EXISTS "rate_user";
CREATE TABLE "rate_user" (
	"id_foto"	INTEGER NOT NULL,
	"id_user"	INTEGER NOT NULL,
	"rate"	INTEGER NOT NULL DEFAULT 0,
	PRIMARY KEY("id_foto","id_user"),
	FOREIGN KEY("id_foto") REFERENCES "photo"("id") ON DELETE CASCADE,
	FOREIGN KEY("id_user") REFERENCES "users"("id") ON DELETE CASCADE
);
DROP TABLE IF EXISTS "users";
CREATE TABLE "users" (
	"id"	INTEGER,
	"login"	TEXT NOT NULL UNIQUE,
	"password"	TEXT NOT NULL,
	"real_name"	TEXT NOT NULL,
	"email"	TEXT NOT NULL,
	"avatar"	TEXT NOT NULL DEFAULT 'no_avatar.jpg',
	"language"	TEXT NOT NULL DEFAULT 'russian',
	"theme"	TEXT NOT NULL DEFAULT 'default',
	"date_regist"	TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	"date_last_activ"	TIMESTAMP DEFAULT NULL,
	"date_last_logout"	TIMESTAMP DEFAULT NULL,
	"group_id"	INTEGER NOT NULL DEFAULT 0,
	"user_rights"	TEXT DEFAULT NULL,
	PRIMARY KEY("id" AUTOINCREMENT),
	FOREIGN KEY("group_id") REFERENCES "groups"("id")
);
INSERT INTO "category" ("id","folder","name","description") VALUES (0,'user','Пользовательский альбом','Персональный пользовательский альбом');
INSERT INTO "change_timestamp" ("table_name","last_update") VALUES ('config','2025-04-10 00:41:12');
INSERT INTO "config" ("name","value") VALUES ('max_avatar_h','100'),
 ('max_avatar_w','100'),
 ('max_rate','2'),
 ('temp_photo_h','200'),
 ('temp_photo_w','200'),
 ('copyright_year','2008-2025'),
 ('left_panel','250'),
 ('right_panel','250'),
 ('max_photo_h','480'),
 ('best_user','5'),
 ('last_news','5'),
 ('max_file_size','5M'),
 ('max_photo_w','640'),
 ('time_user_online','900'),
 ('gal_width','95%'),
 ('themes','default'),
 ('copyright_url','https://rigma.biz/'),
 ('title_name','Rigma Foto'),
 ('meta_description','Rigma.BiZ - фотогалерея Gold Rigma'),
 ('meta_keywords','Rigma.BiZ photo gallery Gold Rigma'),
 ('language','russian'),
 ('copyright_text','Проекты Rigma.BiZ'),
 ('title_description','Фотогалерея Rigma и Co');
INSERT INTO "db_version" ("ver") VALUES ('0.4.0');
INSERT INTO "groups" ("id","name","user_rights") VALUES (0,'Гость','{"pic_view":true,"pic_rate_user":false,"pic_rate_moder":false,"pic_upload":false,"pic_moderate":false,"cat_moderate":false,"cat_user":false,"comment_view":true,"comment_add":false,"comment_moderate":false,"news_view":true,"news_add":false,"news_moderate":false,"admin":false}'),
 (1,'Пользователь','{"pic_view": true, "pic_rate_user": true, "pic_rate_moder": false, "pic_upload": true, "pic_moderate": false, "cat_moderate": false, "cat_user": true, "comment_view": true, "comment_add": true, "comment_moderate": false, "news_view": true, "news_add": false, "news_moderate": false, "admin": false}'),
 (2,'Модератор','{"pic_view": true, "pic_rate_user": false, "pic_rate_moder": true, "pic_upload": true, "pic_moderate": true, "cat_moderate": true, "cat_user": true, "comment_view": true, "comment_add": true, "comment_moderate": true, "news_view": true, "news_add": true, "news_moderate": true, "admin": false}'),
 (3,'Администратор','{"pic_view": true, "pic_rate_user": true, "pic_rate_moder": true, "pic_upload": true, "pic_moderate": true, "cat_moderate": true, "cat_user": true, "comment_view": true, "comment_add": true, "comment_moderate": true, "news_view": true, "news_add": true, "news_moderate": true, "admin": true}');
INSERT INTO "menu" ("id","action","url_action","name_action","short","long","user_login","user_access") VALUES (1,'main','./','home',1,1,NULL,NULL),
 (2,'regist','?action=profile&subact=regist','regist',1,1,0,NULL),
 (3,'category','?action=category','category',1,1,NULL,NULL),
 (4,'user_category','?action=category&cat=user','user_category',0,1,NULL,NULL),
 (5,'you_category','?action=category&cat=user&id=curent','you_category',0,1,1,'cat_user'),
 (6,'upload','?action=photo&subact=upload','upload',0,1,1,'pic_upload'),
 (7,'add_category','?action=category&subact=add','add_category',0,1,1,'cat_moderate'),
 (8,'search','?action=search','search',1,1,NULL,NULL),
 (9,'news','?action=news','news',1,1,NULL,'news_view'),
 (10,'news_add','?action=news&subact=add','news_add',0,1,1,'news_add'),
 (11,'profile','?action=profile&subact=profile','profile',1,1,1,NULL),
 (12,'admin','?action=admin','admin',1,1,1,'admin'),
 (13,'logout','?action=profile&subact=logout','logout',1,1,1,NULL);
INSERT INTO "users" ("id","login","password","real_name","email","avatar","language","theme","date_regist","date_last_activ","date_last_logout","group_id","user_rights") VALUES (1,'admin','$2y$12$66PqD9l3yDp3qj40j.rXNeh7JGzjt/AKkizosLmdbyjB7pQmt6UxW','Администратор','admin@rigma.biz','no_avatar.jpg','russian','default','2009-01-20 12:31:35','2025-04-07 15:10:36','2025-04-05 11:21:57',3,'{"pic_view": true, "pic_rate_user": true, "pic_rate_moder": true, "pic_upload": true, "pic_moderate": true, "cat_moderate": true, "cat_user": true, "comment_view": true, "comment_add": true, "comment_moderate": true, "news_view": true, "news_add": true, "news_moderate": true, "admin": true}');
DROP VIEW IF EXISTS "random_photo";
CREATE VIEW random_photo AS
SELECT photo.id AS id, photo.file AS file, photo.name AS name, photo.description AS description, photo.category AS category, photo.rate_user AS rate_user, photo.rate_moder AS rate_moder, photo.user_upload AS user_upload
FROM photo
WHERE photo.id = (
    SELECT photo.id FROM photo ORDER BY RANDOM() LIMIT 1
);
DROP VIEW IF EXISTS "users_online";
CREATE VIEW users_online AS
SELECT users.id AS id, users.real_name AS real_name
FROM users
WHERE users.date_last_activ >= DATETIME('now', '-' || (SELECT value FROM config WHERE name = 'time_user_online') || ' seconds');
DROP INDEX IF EXISTS "idx_config_value";
CREATE INDEX "idx_config_value" ON "config" (
	"value"
);
DROP INDEX IF EXISTS "idx_menu_long";
CREATE INDEX "idx_menu_long" ON "menu" (
	"long"
);
DROP INDEX IF EXISTS "idx_menu_short";
CREATE INDEX "idx_menu_short" ON "menu" (
	"short"
);
DROP INDEX IF EXISTS "idx_photo_category_user_upload";
CREATE INDEX "idx_photo_category_user_upload" ON "photo" (
	"category",
	"user_upload"
);
DROP INDEX IF EXISTS "idx_photo_date_upload";
CREATE INDEX "idx_photo_date_upload" ON "photo" (
	"date_upload"
);
DROP INDEX IF EXISTS "idx_photo_user_upload_group";
CREATE INDEX "idx_photo_user_upload_group" ON "photo" (
	"user_upload",
	"id"
);
DROP INDEX IF EXISTS "idx_rate_moder_id_user";
CREATE INDEX "idx_rate_moder_id_user" ON "rate_moder" (
	"id_user"
);
DROP INDEX IF EXISTS "idx_rate_user_id_user";
CREATE INDEX "idx_rate_user_id_user" ON "rate_user" (
	"id_user"
);
DROP INDEX IF EXISTS "idx_users_date_last_activ";
CREATE INDEX "idx_users_date_last_activ" ON "users" (
	"date_last_activ"
);
DROP INDEX IF EXISTS "idx_users_group_id";
CREATE INDEX "idx_users_group_id" ON "users" (
	"group_id"
);
DROP TRIGGER IF EXISTS "trg_config_delete";
CREATE TRIGGER trg_config_delete
AFTER DELETE ON config
BEGIN
    INSERT OR REPLACE INTO change_timestamp (table_name, last_update)
    VALUES ('config', CURRENT_TIMESTAMP);
END;
DROP TRIGGER IF EXISTS "trg_config_insert";
CREATE TRIGGER trg_config_insert
AFTER INSERT ON config
BEGIN
    INSERT OR REPLACE INTO change_timestamp (table_name, last_update)
    VALUES ('config', CURRENT_TIMESTAMP);
END;
DROP TRIGGER IF EXISTS "trg_config_update";
CREATE TRIGGER trg_config_update
AFTER UPDATE ON config
BEGIN
    INSERT OR REPLACE INTO change_timestamp (table_name, last_update)
    VALUES ('config', CURRENT_TIMESTAMP);
END;
DROP TRIGGER IF EXISTS "trg_prevent_deletion_category";
CREATE TRIGGER trg_prevent_deletion_category BEFORE DELETE ON category
FOR EACH ROW
BEGIN
    -- Проверяем, является ли id служебным
    SELECT RAISE(IGNORE) WHERE OLD.id = 0;
END;
DROP TRIGGER IF EXISTS "trg_prevent_deletion_groups";
CREATE TRIGGER trg_prevent_deletion_groups BEFORE DELETE ON groups
FOR EACH ROW
BEGIN
    -- Проверяем, является ли id служебным
    SELECT RAISE(IGNORE) WHERE OLD.id BETWEEN 0 AND 3;
END;
DROP TRIGGER IF EXISTS "trg_update_rate_moder_after_delete";
CREATE TRIGGER trg_update_rate_moder_after_delete
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
DROP TRIGGER IF EXISTS "trg_update_rate_moder_after_insert";
CREATE TRIGGER trg_update_rate_moder_after_insert
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
DROP TRIGGER IF EXISTS "trg_update_rate_user_after_delete";
CREATE TRIGGER trg_update_rate_user_after_delete
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
DROP TRIGGER IF EXISTS "trg_update_rate_user_after_insert";
CREATE TRIGGER trg_update_rate_user_after_insert
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
COMMIT;
