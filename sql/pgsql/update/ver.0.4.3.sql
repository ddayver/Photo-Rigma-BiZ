-- 1. Внедряем полнотекстовые индексы в таблицы, где требуется полнотекстовый поиск.
-- Добавляем расширение если его нет
CREATE EXTENSION IF NOT EXISTS pg_trgm;
-- Добавление вычисляемой колонки tsv_weighted с полнотекстовым вектором и разными языками в таблицу users
ALTER TABLE users
ADD COLUMN tsv_weighted TSVECTOR
GENERATED ALWAYS AS (
    setweight(to_tsvector('russian', login), 'A') ||
    setweight(to_tsvector('russian', real_name), 'A') ||
    setweight(to_tsvector('russian', email), 'A') ||
    setweight(to_tsvector('english', login), 'B') ||
    setweight(to_tsvector('english', real_name), 'B') ||
    setweight(to_tsvector('english', email), 'B') ||
    setweight(to_tsvector('simple', login), 'D') ||
    setweight(to_tsvector('simple', real_name), 'D') ||
    setweight(to_tsvector('simple', email), 'D')
) STORED;
CREATE INDEX idx_users_tsv_weighted ON users USING GIN (tsv_weighted);
ALTER INDEX idx_users_tsv_weighted OWNER TO photorigma;
COMMENT ON COLUMN users.tsv_weighted IS 'Взвешенный полнотекстовый вектор для пользовательских данных (login, real_name, email). Используется для улучшенного поиска.';
CREATE INDEX idx_users_login_trgm ON users USING GIN (login gin_trgm_ops);
CREATE INDEX idx_users_real_name_trgm ON users USING GIN (real_name gin_trgm_ops);
CREATE INDEX idx_users_email_trgm ON users USING GIN (email gin_trgm_ops);
-- Добавление вычисляемой колонки tsv_weighted с полнотекстовым вектором и разными языками
ALTER TABLE category
ADD COLUMN tsv_weighted TSVECTOR
GENERATED ALWAYS AS (
    setweight(to_tsvector('russian', name), 'A') ||
    setweight(to_tsvector('russian', description), 'A') ||
    setweight(to_tsvector('english', name), 'B') ||
    setweight(to_tsvector('english', description), 'B') ||
    setweight(to_tsvector('simple', name), 'D') ||
    setweight(to_tsvector('simple', description), 'D')
) STORED;
CREATE INDEX idx_category_tsv_weighted ON category USING GIN (tsv_weighted);
ALTER INDEX idx_category_tsv_weighted OWNER TO photorigma;
COMMENT ON COLUMN category.tsv_weighted IS 'Взвешенный полнотекстовый вектор для данных таблицы category (name, description). Используется для улучшенного поиска.';
CREATE INDEX idx_category_name_trgm ON category USING GIN (name gin_trgm_ops);
CREATE INDEX idx_category_description_trgm ON category USING GIN (description gin_trgm_ops);
-- Добавление вычисляемой колонки tsv_weighted с полнотекстовым вектором и разными языками
ALTER TABLE news
ADD COLUMN tsv_weighted TSVECTOR
GENERATED ALWAYS AS (
    setweight(to_tsvector('russian', name_post), 'A') ||
    setweight(to_tsvector('russian', text_post), 'A') ||
    setweight(to_tsvector('english', name_post), 'B') ||
    setweight(to_tsvector('english', text_post), 'B') ||
    setweight(to_tsvector('simple', name_post), 'D') ||
    setweight(to_tsvector('simple', text_post), 'D')
) STORED;
CREATE INDEX idx_news_tsv_weighted ON news USING GIN (tsv_weighted);
ALTER INDEX idx_news_tsv_weighted OWNER TO photorigma;
COMMENT ON COLUMN news.tsv_weighted IS 'Взвешенный полнотекстовый вектор для данных таблицы news (name_post, text_post). Используется для улучшенного поиска.';
CREATE INDEX idx_news_namepost_trgm ON news USING GIN (name_post gin_trgm_ops);
CREATE INDEX idx_news_textpost_trgm ON news USING GIN (text_post gin_trgm_ops);
-- Добавление вычисляемой колонки tsv_weighted с полнотекстовым вектором и разными языками
ALTER TABLE photo
ADD COLUMN tsv_weighted TSVECTOR
GENERATED ALWAYS AS (
    setweight(to_tsvector('russian', name), 'A') ||
    setweight(to_tsvector('russian', description), 'B') ||
    setweight(to_tsvector('english', name), 'C') ||
    setweight(to_tsvector('english', description), 'D') ||
    setweight(to_tsvector('simple', name), 'D') ||
    setweight(to_tsvector('simple', description), 'D')
) STORED;
CREATE INDEX idx_photo_tsv_weighted ON photo USING GIN (tsv_weighted);
ALTER INDEX idx_photo_tsv_weighted OWNER TO photorigma;
COMMENT ON COLUMN photo.tsv_weighted IS 'Взвешенный полнотекстовый вектор для данных таблицы photo (name, description). Используется для улучшенного поиска.';
CREATE INDEX idx_photo_name_trgm ON photo USING GIN (name gin_trgm_ops);
CREATE INDEX idx_photo_description_trgm ON photo USING GIN (description gin_trgm_ops);

-- Final
-- Очистка таблицы
TRUNCATE TABLE db_version;
-- Вставка значения
INSERT INTO db_version (ver) VALUES ('0.4.3');
