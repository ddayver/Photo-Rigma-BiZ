-- 1. Внедряем полнотекстовые индексы в таблицы, где требуется полнотекстовый поиск.
ALTER TABLE `category` ADD FULLTEXT KEY `idx_fts_category` (`name`,`description`);
ALTER TABLE `news` ADD FULLTEXT KEY `idx_fts_news` (`name_post`,`text_post`);
ALTER TABLE `photo` ADD FULLTEXT KEY `idx_fts_photo` (`name`,`description`);
ALTER TABLE `users` ADD FULLTEXT KEY `idx_fts_users` (`login`,`real_name`,`email`);

-- Final
-- Очистка таблицы
TRUNCATE TABLE db_version;
-- Вставка значения
INSERT INTO db_version (ver) VALUES ('0.4.3');
