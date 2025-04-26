-- 1. Создание таблицы для хранения даты последних изменений
CREATE TABLE IF NOT EXISTS change_timestamp (
    table_name VARCHAR(255) PRIMARY KEY,
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Добавление комментариев к таблице и столбцам
COMMENT ON TABLE change_timestamp IS 'Хранение даты последних изменений в таблицах';
COMMENT ON COLUMN change_timestamp.table_name IS 'Имя таблицы';
COMMENT ON COLUMN change_timestamp.last_update IS 'Время последнего обновления';

-- Установка владельца таблицы
ALTER TABLE change_timestamp OWNER TO photorigma;

-- 2. Добавление или обновление записи в таблице change_timestamp
INSERT INTO change_timestamp (table_name, last_update)
VALUES ('config', CURRENT_TIMESTAMP)
ON CONFLICT (table_name) DO UPDATE
SET last_update = EXCLUDED.last_update;

-- 3. Функция для обновления времени последнего изменения
CREATE OR REPLACE FUNCTION update_change_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO change_timestamp (table_name, last_update)
    VALUES (TG_TABLE_NAME, CURRENT_TIMESTAMP)
    ON CONFLICT (table_name) DO UPDATE
    SET last_update = EXCLUDED.last_update;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Добавление комментария к функции
COMMENT ON FUNCTION update_change_timestamp IS 'Обновляет время последнего изменения в таблице change_timestamp';

-- Установка владельца функции
ALTER FUNCTION update_change_timestamp OWNER TO photorigma;

-- 4. Триггеры для таблицы config
CREATE TRIGGER trg_config_insert
AFTER INSERT ON config
FOR EACH ROW
EXECUTE FUNCTION update_change_timestamp();

CREATE TRIGGER trg_config_update
AFTER UPDATE ON config
FOR EACH ROW
EXECUTE FUNCTION update_change_timestamp();

CREATE TRIGGER trg_config_delete
AFTER DELETE ON config
FOR EACH ROW
EXECUTE FUNCTION update_change_timestamp();

CREATE INDEX idx_menu_short ON menu (short);
CREATE INDEX idx_menu_long ON menu (long);

-- 5. Обновление версии СУБД
UPDATE db_version SET ver = '0.4.1' WHERE db_version.ver = '0.4.0';

