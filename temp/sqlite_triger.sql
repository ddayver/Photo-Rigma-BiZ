-- Триггеры для rate_user

CREATE TRIGGER update_rate_user_after_insert
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

CREATE TRIGGER update_rate_user_after_delete
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

-- Триггеры для rate_moder

CREATE TRIGGER update_rate_moder_after_insert
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

CREATE TRIGGER update_rate_moder_after_delete
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

-- Предотвращение удаления служебных групп.
CREATE TRIGGER trg_prevent_deletion_groups
BEFORE DELETE ON groups
FOR EACH ROW
WHEN OLD.id BETWEEN 0 AND 3
BEGIN
    -- Просто игнорируем удаление
    SELECT RAISE(IGNORE);
END;

-- Предотвращение удаления служебных категорий.
CREATE TRIGGER trg_prevent_deletion_category
BEFORE DELETE ON category
FOR EACH ROW
WHEN OLD.id = 0
BEGIN
    -- Просто игнорируем удаление
    SELECT RAISE(IGNORE);
END;

