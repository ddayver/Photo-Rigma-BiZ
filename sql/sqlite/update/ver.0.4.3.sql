-- 1. Внедряем полнотекстовые индексы в таблицы, где требуется полнотекстовый поиск.
-- Таблица users + users_fts
CREATE VIRTUAL TABLE users_fts USING fts5(
    login,
    real_name,
    email,
    content='users',
    content_rowid='id',
    tokenize='porter'
);
INSERT INTO users_fts(users_fts) VALUES('rebuild');
CREATE TRIGGER users_fts_after_insert AFTER INSERT ON users BEGIN
    INSERT INTO users_fts(rowid, login, real_name, email)
    VALUES (new.id, new.login, new.real_name, new.email);
END;
CREATE TRIGGER users_fts_after_update AFTER UPDATE ON users BEGIN
    DELETE FROM users_fts WHERE rowid = old.id;
    INSERT INTO users_fts(rowid, login, real_name, email)
    VALUES (new.id, new.login, new.real_name, new.email);
END;
CREATE TRIGGER users_fts_before_delete BEFORE DELETE ON users BEGIN
    DELETE FROM users_fts WHERE rowid = old.id;
END;
-- Таблица category + category_fts
CREATE VIRTUAL TABLE category_fts USING fts5(
    name,
    description,
    content='category',
    content_rowid='id',
    tokenize='porter'
);
INSERT INTO category_fts(category_fts) VALUES('rebuild');
CREATE TRIGGER category_fts_after_insert AFTER INSERT ON category BEGIN
    INSERT INTO category_fts(rowid, name, description)
    VALUES (new.id, new.name, new.description);
END;
CREATE TRIGGER category_fts_after_update AFTER UPDATE ON category BEGIN
    DELETE FROM category_fts WHERE rowid = old.id;
    INSERT INTO category_fts(rowid, name, description)
    VALUES (new.id, new.name, new.description);
END;
CREATE TRIGGER category_fts_before_delete BEFORE DELETE ON category BEGIN
    DELETE FROM category_fts WHERE rowid = old.id;
END;
-- Таблица news + news_fts
CREATE VIRTUAL TABLE news_fts USING fts5(
    name_post,
    text_post,
    content='news',
    content_rowid='id',
    tokenize='porter'
);
INSERT INTO news_fts(news_fts) VALUES('rebuild');
CREATE TRIGGER news_fts_after_insert AFTER INSERT ON news BEGIN
    INSERT INTO news_fts(rowid, name_post, text_post)
    VALUES (new.id, new.name_post, new.text_post);
END;
CREATE TRIGGER news_fts_after_update AFTER UPDATE ON news BEGIN
    DELETE FROM news_fts WHERE rowid = old.id;
    INSERT INTO news_fts(rowid, name_post, text_post)
    VALUES (new.id, new.name_post, new.text_post);
END;
CREATE TRIGGER news_fts_before_delete BEFORE DELETE ON news BEGIN
    DELETE FROM news_fts WHERE rowid = old.id;
END;
-- Таблица photo + photo_fts
CREATE VIRTUAL TABLE photo_fts USING fts5(
    name,
    description,
    content='photo',
    content_rowid='id',
    tokenize='porter'
);
INSERT INTO photo_fts(photo_fts) VALUES('rebuild');
CREATE TRIGGER photo_fts_after_insert AFTER INSERT ON photo BEGIN
    INSERT INTO photo_fts(rowid, name, description)
    VALUES (new.id, new.name, new.description);
END;
CREATE TRIGGER photo_fts_after_update AFTER UPDATE ON photo BEGIN
    DELETE FROM photo_fts WHERE rowid = old.id;
    INSERT INTO photo_fts(rowid, name, description)
    VALUES (new.id, new.name, new.description);
END;
CREATE TRIGGER photo_fts_before_delete BEFORE DELETE ON photo BEGIN
    DELETE FROM photo_fts WHERE rowid = old.id;
END;

-- Final
-- Очистка таблицы
DELETE FROM db_version;
-- Вставка значения
INSERT INTO db_version (ver) VALUES ('0.4.3');
