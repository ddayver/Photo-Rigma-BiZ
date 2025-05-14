-- 1. Внедряем функционал мягкого и окончательного удаления пользователей.
-- Добавление поля deleted_at
ALTER TABLE users
    ADD COLUMN deleted_at TIMESTAMP NULL;
COMMENT ON COLUMN users.deleted_at IS 'Дата и время мягкого удаления пользователя';
-- Добавление поля permanently_deleted
ALTER TABLE users
    ADD COLUMN permanently_deleted BOOLEAN NOT NULL DEFAULT FALSE;
COMMENT ON COLUMN users.permanently_deleted IS 'Флаг окончательного удаления пользователя';
-- Создание индекса на поле deleted_at для фоновых задач
CREATE INDEX idx_users_deleted_at ON users(deleted_at);
ALTER INDEX idx_users_deleted_at OWNER TO photorigma;
DROP INDEX public.idx_users_user_rights

-- 2. Добавление дополнительных полей в таблицу users для будущих функций
-- Добавление поля activation: Флаг активации аккаунта
ALTER TABLE users ADD COLUMN IF NOT EXISTS activation BOOLEAN NOT NULL DEFAULT FALSE;
COMMENT ON COLUMN users.activation IS 'Флаг активации аккаунта';

-- Добавление поля token: Для временных токенов (восстановление пароля, активация)
ALTER TABLE users ADD COLUMN IF NOT EXISTS token VARCHAR(255) DEFAULT NULL;
COMMENT ON COLUMN users.token IS 'Для временных токенов';

-- Добавление поля token_expires_at: Время истечения токена
ALTER TABLE users ADD COLUMN IF NOT EXISTS token_expires_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NULL;
COMMENT ON COLUMN users.token_expires_at IS 'Время истечения токена';

-- Добавление поля email_confirmed: Подтверждён ли email
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_confirmed BOOLEAN NOT NULL DEFAULT FALSE;
COMMENT ON COLUMN users.email_confirmed IS 'Подтверждён ли email';

-- Добавление поля allow_newsletter: Разрешает ли пользователь рассылку
ALTER TABLE users ADD COLUMN IF NOT EXISTS allow_newsletter BOOLEAN NOT NULL DEFAULT FALSE;
COMMENT ON COLUMN users.allow_newsletter IS 'Разрешает ли пользователь рассылку';

-- Добавление поля timezone: Часовой пояс пользователя
ALTER TABLE users ADD COLUMN IF NOT EXISTS timezone VARCHAR(50) NOT NULL DEFAULT 'UTC';
COMMENT ON COLUMN users.allow_newsletter IS 'Часовой пояс пользователя';

-- Добавление поля other_params: Прочие параметры пользовател
ALTER TABLE users ADD COLUMN IF NOT EXISTS other_params JSONB;
COMMENT ON COLUMN users.other_params IS 'Прочие параметры пользователя';

-- Индекс на token
CREATE INDEX IF NOT EXISTS idx_users_token ON users(token);
ALTER INDEX idx_users_token OWNER TO photorigma;

-- Индекс на token_expires_at
CREATE INDEX IF NOT EXISTS idx_users_token_expires ON users(token_expires_at);
ALTER INDEX idx_users_token_expires OWNER TO photorigma;

-- Индекс на allow_newsletter
CREATE INDEX IF NOT EXISTS idx_users_allow_newsletter ON users(allow_newsletter);
ALTER INDEX idx_users_allow_newsletter OWNER TO photorigma;

-- 3. Обновление: все существующие аккаунты считаем активированными и подтвердившими email
UPDATE users SET activation = TRUE WHERE activation IS NULL OR activation = FALSE;
UPDATE users SET email_confirmed = TRUE WHERE email_confirmed IS NULL OR email_confirmed = FALSE;

-- 4. Удаление темы 'old' в связи с окончанием её поддержки, изменение имени переменной 'themes' на 'theme'
UPDATE users SET theme = 'default' WHERE theme = 'old';
UPDATE config SET value = 'default' WHERE name = 'themes' AND value = 'old';
UPDATE config SET name = 'theme' WHERE name = 'themes';

-- 5. Добавление настройки часового пояса сервера
INSERT INTO config (name, value)
VALUES ('timezone', 'UTC')
ON CONFLICT (name) DO UPDATE SET value = EXCLUDED.value;

-- 6. Добавление таблицы для хранения информации о бане пользователя
DROP TABLE IF EXISTS user_bans CASCADE;

CREATE TABLE IF NOT EXISTS user_bans (
    user_id INT NOT NULL PRIMARY KEY,
    banned BOOLEAN NOT NULL DEFAULT TRUE,
    reason TEXT,
    expires_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE public.user_bans OWNER TO photorigma;
COMMENT ON TABLE user_bans IS 'Информация о бане пользователей';
COMMENT ON COLUMN user_bans.user_id IS 'Идентификатор пользователя';
COMMENT ON COLUMN user_bans.banned IS 'Флаг: пользователь заблокирован';
COMMENT ON COLUMN user_bans.reason IS 'Причина блокировки';
COMMENT ON COLUMN user_bans.expires_at IS 'Дата окончания бана (если временный)';
COMMENT ON COLUMN user_bans.created_at IS 'Дата и время установки бана';

-- Индексы
-- Индекс на expires_at: полезен для фоновой задачи по очистке просроченных банов
CREATE INDEX IF NOT EXISTS idx_user_bans_expires ON user_bans(expires_at);
ALTER INDEX idx_user_bans_expires OWNER TO photorigma;

-- Индекс на user_id (уже есть как PK, но можно создать отдельно для явности)
CREATE INDEX IF NOT EXISTS idx_user_bans_user_id ON user_bans(user_id);
ALTER INDEX idx_user_bans_user_id OWNER TO photorigma;

-- 7. Обновляем представление для получение списка онлайн-пользователей
DROP VIEW IF EXISTS public.users_online;

CREATE VIEW public.users_online AS
SELECT
    u.id,
    u.real_name,
    COALESCE(b.banned, FALSE) AS banned  -- TRUE/FALSE
FROM public.users u
LEFT JOIN public.user_bans b ON u.id = b.user_id AND b.banned = TRUE
WHERE
    u.date_last_activ >= NOW() - (
        SELECT (value || ' seconds')::INTERVAL
        FROM public.config
        WHERE name = 'time_user_online'
    )
    AND u.activation = TRUE
    AND u.email_confirmed = TRUE
    AND u.deleted_at IS NULL
    AND u.permanently_deleted = FALSE;

ALTER VIEW public.users_online OWNER TO photorigma;
COMMENT ON VIEW public.users_online IS 'Список пользователей онлайн';
COMMENT ON COLUMN public.users_online.id IS 'Идентификатор пользователя';
COMMENT ON COLUMN public.users_online.real_name IS 'Отображаемое имя пользователя';
COMMENT ON COLUMN public.users_online.banned IS 'Флаг: пользователь забанен';

-- 8. Изменяем поле `id` в таблице `groups`: включаем SEQUENCE.
CREATE SEQUENCE IF NOT EXISTS groups_id_seq START WITH 4;
ALTER SEQUENCE public.groups_id_seq OWNER TO photorigma;
ALTER TABLE groups ALTER COLUMN id SET DEFAULT nextval('groups_id_seq');

-- 9. Добавляем описания к функциям.
COMMENT ON FUNCTION prevent_deletion_of_service_categories () IS 'Функция-триггер: запрещает удаление категории с id = 0 (служебная)';
COMMENT ON FUNCTION prevent_deletion_of_service_groups () IS 'Функция-триггер: запрещает удаление групп с id от 0 до 3 (служебные)';
COMMENT ON FUNCTION update_rate_moder_after_delete () IS 'Функция-триггер: обновляет рейтинг фото после удаления оценки от модератора';
COMMENT ON FUNCTION update_rate_moder_after_insert () IS 'Функция-триггер: обновляет рейтинг фото при новой оценке от модератора';
COMMENT ON FUNCTION update_rate_user_after_delete () IS 'Функция-триггер: обновляет рейтинг фото после удаления оценки от пользователя';
COMMENT ON FUNCTION update_rate_user_after_insert () IS 'Функция-триггер: обновляет рейтинг фото при новой оценке от пользователя';
COMMENT ON FUNCTION update_change_timestamp () IS 'Функция-триггер: обновляет время последнего изменения config в таблице change_timestamp';

-- Final
TRUNCATE TABLE db_version;
INSERT INTO db_version (ver) VALUES ('0.4.4');

