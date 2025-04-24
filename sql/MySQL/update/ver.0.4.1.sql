-- 1. Добавляем таблицу для хранения даты последних изменений в СУБД.
CREATE TABLE IF NOT EXISTS `change_timestamp` (
  `table_name` varchar(255) NOT NULL COMMENT 'Имя таблицы',
  `last_update` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Время последнего обновления',
  PRIMARY KEY (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Хранение даты последних изменений в таблицах';

-- 2. Добавляем запись в таблицу `change_timestamp` и тригеры для учета даты последних изменений в таблице `config`.
-- Создание записи в таблице change_timestamp
INSERT INTO `change_timestamp` (`table_name`, `last_update`)
VALUES ('config', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE last_update = CURRENT_TIMESTAMP;

-- Триггер на INSERT
CREATE TRIGGER `trg_config_insert`
AFTER INSERT ON `config`
FOR EACH ROW
UPDATE change_timestamp
SET last_update = CURRENT_TIMESTAMP
WHERE table_name = 'config';

-- Триггер на UPDATE
CREATE TRIGGER `trg_config_update`
AFTER UPDATE ON `config`
FOR EACH ROW
UPDATE change_timestamp
SET last_update = CURRENT_TIMESTAMP
WHERE table_name = 'config';

-- Триггер на DELETE
CREATE TRIGGER `trg_config_delete`
AFTER DELETE ON `config`
FOR EACH ROW
UPDATE change_timestamp
SET last_update = CURRENT_TIMESTAMP
WHERE table_name = 'config';

CREATE INDEX `idx_menu_short` ON `menu` (`short`);
CREATE INDEX `idx_menu_long` ON `menu` (`long`);
