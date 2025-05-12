--
-- PostgreSQL database dump
--

-- Dumped from database version 16.8
-- Dumped by pg_dump version 16.8

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

ALTER TABLE ONLY public.users DROP CONSTRAINT users_groups;
ALTER TABLE ONLY public.user_bans DROP CONSTRAINT user_bans_user_id_fkey;
ALTER TABLE ONLY public.rate_user DROP CONSTRAINT u_rate_users;
ALTER TABLE ONLY public.rate_user DROP CONSTRAINT u_rate_photo;
ALTER TABLE ONLY public.photo DROP CONSTRAINT photo_users;
ALTER TABLE ONLY public.photo DROP CONSTRAINT photo_category;
ALTER TABLE ONLY public.news DROP CONSTRAINT news_users;
ALTER TABLE ONLY public.rate_moder DROP CONSTRAINT m_rate_users;
ALTER TABLE ONLY public.rate_moder DROP CONSTRAINT m_rate_photo;
DROP TRIGGER update_rate_user_after_insert ON public.rate_user;
DROP TRIGGER update_rate_user_after_delete ON public.rate_user;
DROP TRIGGER update_rate_moder_after_insert ON public.rate_moder;
DROP TRIGGER update_rate_moder_after_delete ON public.rate_moder;
DROP TRIGGER trg_prevent_deletion_groups ON public.groups;
DROP TRIGGER trg_prevent_deletion_category ON public.category;
DROP TRIGGER trg_config_update ON public.config;
DROP TRIGGER trg_config_insert ON public.config;
DROP TRIGGER trg_config_delete ON public.config;
DROP INDEX public.query_logs_query_hash_key;
DROP INDEX public.idx_users_tsv_weighted;
DROP INDEX public.idx_users_token_expires;
DROP INDEX public.idx_users_token;
DROP INDEX public.idx_users_real_name_trgm;
DROP INDEX public.idx_users_login_trgm;
DROP INDEX public.idx_users_email_trgm;
DROP INDEX public.idx_users_deleted_at;
DROP INDEX public.idx_users_date_last_activ;
DROP INDEX public.idx_users_allow_newsletter;
DROP INDEX public.idx_user_bans_user_id;
DROP INDEX public.idx_user_bans_expires;
DROP INDEX public.idx_photo_user_upload_group;
DROP INDEX public.idx_photo_tsv_weighted;
DROP INDEX public.idx_photo_name_trgm;
DROP INDEX public.idx_photo_description_trgm;
DROP INDEX public.idx_photo_date_upload;
DROP INDEX public.idx_photo_category_user_upload;
DROP INDEX public.idx_news_tsv_weighted;
DROP INDEX public.idx_news_textpost_trgm;
DROP INDEX public.idx_news_namepost_trgm;
DROP INDEX public.idx_news_data_last_edit;
DROP INDEX public.idx_menu_short;
DROP INDEX public.idx_menu_long;
DROP INDEX public.idx_config_value;
DROP INDEX public.idx_category_tsv_weighted;
DROP INDEX public.idx_category_name_trgm;
DROP INDEX public.idx_category_description_trgm;
ALTER TABLE ONLY public.users DROP CONSTRAINT users_pkey;
ALTER TABLE ONLY public.users DROP CONSTRAINT users_login_key;
ALTER TABLE ONLY public.user_bans DROP CONSTRAINT user_bans_pkey;
ALTER TABLE ONLY public.rate_user DROP CONSTRAINT rate_user_pkey;
ALTER TABLE ONLY public.rate_moder DROP CONSTRAINT rate_moder_pkey;
ALTER TABLE ONLY public.query_logs DROP CONSTRAINT query_logs_pkey;
ALTER TABLE ONLY public.photo DROP CONSTRAINT photo_pkey;
ALTER TABLE ONLY public.news DROP CONSTRAINT news_pkey;
ALTER TABLE ONLY public.menu DROP CONSTRAINT menu_pkey;
ALTER TABLE ONLY public.groups DROP CONSTRAINT group_pkey;
ALTER TABLE ONLY public.db_version DROP CONSTRAINT db_version_pkey;
ALTER TABLE ONLY public.config DROP CONSTRAINT config_pkey;
ALTER TABLE ONLY public.change_timestamp DROP CONSTRAINT change_timestamp_pkey;
ALTER TABLE ONLY public.category DROP CONSTRAINT category_pkey;
ALTER TABLE public.users ALTER COLUMN id DROP DEFAULT;
ALTER TABLE public.query_logs ALTER COLUMN id DROP DEFAULT;
ALTER TABLE public.photo ALTER COLUMN id DROP DEFAULT;
ALTER TABLE public.news ALTER COLUMN id DROP DEFAULT;
ALTER TABLE public.menu ALTER COLUMN id DROP DEFAULT;
ALTER TABLE public.category ALTER COLUMN id DROP DEFAULT;
DROP VIEW public.users_online;
DROP SEQUENCE public.users_id_seq;
DROP TABLE public.users;
DROP TABLE public.user_bans;
DROP TABLE public.rate_user;
DROP TABLE public.rate_moder;
DROP VIEW public.random_photo;
DROP SEQUENCE public.query_logs_id_seq;
DROP TABLE public.query_logs;
DROP SEQUENCE public.photo_id_seq;
DROP TABLE public.photo;
DROP SEQUENCE public.news_id_seq;
DROP TABLE public.news;
DROP SEQUENCE public.menu_id_seq;
DROP TABLE public.menu;
DROP TABLE public.groups;
DROP SEQUENCE public.groups_id_seq;
DROP TABLE public.db_version;
DROP TABLE public.config;
DROP TABLE public.change_timestamp;
DROP SEQUENCE public.category_id_seq;
DROP TABLE public.category;
DROP FUNCTION public.update_rate_user_after_insert();
DROP FUNCTION public.update_rate_user_after_delete();
DROP FUNCTION public.update_rate_moder_after_insert();
DROP FUNCTION public.update_rate_moder_after_delete();
DROP FUNCTION public.update_change_timestamp();
DROP FUNCTION public.prevent_deletion_of_service_groups();
DROP FUNCTION public.prevent_deletion_of_service_categories();
DROP EXTENSION pg_trgm;
--
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner:
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


--
-- Name: prevent_deletion_of_service_categories(); Type: FUNCTION; Schema: public; Owner: photorigma
--

CREATE FUNCTION public.prevent_deletion_of_service_categories() RETURNS trigger
    LANGUAGE plpgsql
    AS $$BEGIN
    -- Проверяем, является ли id служебным
    IF OLD.id = 0 THEN
        -- Просто выходим без ошибки (удаление игнорируется)
        RETURN NULL;
    END IF;

    -- Разрешаем удаление
    RETURN OLD;
END;
$$;


ALTER FUNCTION public.prevent_deletion_of_service_categories() OWNER TO photorigma;

--
-- Name: FUNCTION prevent_deletion_of_service_categories(); Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON FUNCTION public.prevent_deletion_of_service_categories() IS 'Функция-триггер: запрещает удаление категории с id = 0 (служебная)';


--
-- Name: prevent_deletion_of_service_groups(); Type: FUNCTION; Schema: public; Owner: photorigma
--

CREATE FUNCTION public.prevent_deletion_of_service_groups() RETURNS trigger
    LANGUAGE plpgsql
    AS $$BEGIN
    -- Проверяем, является ли id служебным
    IF OLD.id BETWEEN 0 AND 3 THEN
        -- Просто выходим без ошибки (удаление игнорируется)
        RETURN NULL;
    END IF;
    -- Разрешаем удаление
    RETURN OLD;
END;
$$;


ALTER FUNCTION public.prevent_deletion_of_service_groups() OWNER TO photorigma;

--
-- Name: FUNCTION prevent_deletion_of_service_groups(); Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON FUNCTION public.prevent_deletion_of_service_groups() IS 'Функция-триггер: запрещает удаление групп с id от 0 до 3 (служебные)';


--
-- Name: update_change_timestamp(); Type: FUNCTION; Schema: public; Owner: photorigma
--

CREATE FUNCTION public.update_change_timestamp() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    INSERT INTO change_timestamp (table_name, last_update)
    VALUES (TG_TABLE_NAME, CURRENT_TIMESTAMP)
    ON CONFLICT (table_name) DO UPDATE
    SET last_update = EXCLUDED.last_update;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_change_timestamp() OWNER TO photorigma;

--
-- Name: FUNCTION update_change_timestamp(); Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON FUNCTION public.update_change_timestamp() IS 'Функция-триггер: обновляет время последнего изменения config в таблице change_timestamp';


--
-- Name: update_rate_moder_after_delete(); Type: FUNCTION; Schema: public; Owner: photorigma
--

CREATE FUNCTION public.update_rate_moder_after_delete() RETURNS trigger
    LANGUAGE plpgsql
    AS $$BEGIN
    IF EXISTS (SELECT 1 FROM photo WHERE id = OLD.id_foto) THEN
        UPDATE photo
        SET rate_moder = (
            SELECT COALESCE(AVG(rate), 0)
            FROM rate_moder
            WHERE id_foto = OLD.id_foto
        )
        WHERE id = OLD.id_foto;
    END IF;
    RETURN OLD;
END;
$$;


ALTER FUNCTION public.update_rate_moder_after_delete() OWNER TO photorigma;

--
-- Name: FUNCTION update_rate_moder_after_delete(); Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON FUNCTION public.update_rate_moder_after_delete() IS 'Функция-триггер: обновляет рейтинг фото после удаления оценки от модератора';


--
-- Name: update_rate_moder_after_insert(); Type: FUNCTION; Schema: public; Owner: photorigma
--

CREATE FUNCTION public.update_rate_moder_after_insert() RETURNS trigger
    LANGUAGE plpgsql
    AS $$BEGIN
    IF EXISTS (SELECT 1 FROM photo WHERE id = NEW.id_foto) THEN
        UPDATE photo
        SET rate_moder = (
            SELECT COALESCE(AVG(rate), 0)
            FROM rate_moder
            WHERE id_foto = NEW.id_foto
        )
        WHERE id = NEW.id_foto;
    END IF;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_rate_moder_after_insert() OWNER TO photorigma;

--
-- Name: FUNCTION update_rate_moder_after_insert(); Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON FUNCTION public.update_rate_moder_after_insert() IS 'Функция-триггер: обновляет рейтинг фото при новой оценке от модератора';


--
-- Name: update_rate_user_after_delete(); Type: FUNCTION; Schema: public; Owner: photorigma
--

CREATE FUNCTION public.update_rate_user_after_delete() RETURNS trigger
    LANGUAGE plpgsql
    AS $$BEGIN
    IF EXISTS (SELECT 1 FROM photo WHERE id = OLD.id_foto) THEN
        UPDATE photo
        SET rate_user = (
            SELECT COALESCE(AVG(rate), 0)
            FROM rate_user
            WHERE id_foto = OLD.id_foto
        )
        WHERE id = OLD.id_foto;
    END IF;
    RETURN OLD;
END;
$$;


ALTER FUNCTION public.update_rate_user_after_delete() OWNER TO photorigma;

--
-- Name: FUNCTION update_rate_user_after_delete(); Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON FUNCTION public.update_rate_user_after_delete() IS 'Функция-триггер: обновляет рейтинг фото после удаления оценки от пользователя';


--
-- Name: update_rate_user_after_insert(); Type: FUNCTION; Schema: public; Owner: photorigma
--

CREATE FUNCTION public.update_rate_user_after_insert() RETURNS trigger
    LANGUAGE plpgsql
    AS $$BEGIN
    IF EXISTS (SELECT 1 FROM photo WHERE id = NEW.id_foto) THEN
        UPDATE photo
        SET rate_user = (
            SELECT COALESCE(AVG(rate), 0)
            FROM rate_user
            WHERE id_foto = NEW.id_foto
        )
        WHERE id = NEW.id_foto;
    END IF;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_rate_user_after_insert() OWNER TO photorigma;

--
-- Name: FUNCTION update_rate_user_after_insert(); Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON FUNCTION public.update_rate_user_after_insert() IS 'Функция-триггер: обновляет рейтинг фото при новой оценке от пользователя';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: category; Type: TABLE; Schema: public; Owner: photorigma
--

CREATE TABLE public.category (
    id integer NOT NULL,
    folder character varying(50) NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(250) NOT NULL,
    tsv_weighted tsvector GENERATED ALWAYS AS ((((((setweight(to_tsvector('russian'::regconfig, (name)::text), 'A'::"char") || setweight(to_tsvector('russian'::regconfig, (description)::text), 'A'::"char")) || setweight(to_tsvector('english'::regconfig, (name)::text), 'B'::"char")) || setweight(to_tsvector('english'::regconfig, (description)::text), 'B'::"char")) || setweight(to_tsvector('simple'::regconfig, (name)::text), 'D'::"char")) || setweight(to_tsvector('simple'::regconfig, (description)::text), 'D'::"char"))) STORED
);


ALTER TABLE public.category OWNER TO photorigma;

--
-- Name: TABLE category; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON TABLE public.category IS 'Таблица разделов';


--
-- Name: COLUMN category.id; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.category.id IS 'Идентификатор';


--
-- Name: COLUMN category.folder; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.category.folder IS 'Имя папки раздела';


--
-- Name: COLUMN category.name; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.category.name IS 'Название раздела';


--
-- Name: COLUMN category.description; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.category.description IS 'Описание раздела';


--
-- Name: COLUMN category.tsv_weighted; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.category.tsv_weighted IS 'Взвешенный полнотекстовый вектор для данных таблицы category (name, description). Используется для улучшенного поиска.';


--
-- Name: category_id_seq; Type: SEQUENCE; Schema: public; Owner: photorigma
--

CREATE SEQUENCE public.category_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.category_id_seq OWNER TO photorigma;

--
-- Name: category_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: photorigma
--

ALTER SEQUENCE public.category_id_seq OWNED BY public.category.id;


--
-- Name: change_timestamp; Type: TABLE; Schema: public; Owner: photorigma
--

CREATE TABLE public.change_timestamp (
    table_name character varying(255) NOT NULL,
    last_update timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.change_timestamp OWNER TO photorigma;

--
-- Name: TABLE change_timestamp; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON TABLE public.change_timestamp IS 'Хранение даты последних изменений в таблицах';


--
-- Name: COLUMN change_timestamp.table_name; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.change_timestamp.table_name IS 'Имя таблицы';


--
-- Name: COLUMN change_timestamp.last_update; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.change_timestamp.last_update IS 'Время последнего обновления';


--
-- Name: config; Type: TABLE; Schema: public; Owner: photorigma
--

CREATE TABLE public.config (
    name character varying(50) NOT NULL,
    value character varying(255) NOT NULL
);


ALTER TABLE public.config OWNER TO photorigma;

--
-- Name: TABLE config; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON TABLE public.config IS 'Таблица параметров';


--
-- Name: COLUMN config.name; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.config.name IS 'Имя параметра';


--
-- Name: COLUMN config.value; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.config.value IS 'Значение параметра';


--
-- Name: db_version; Type: TABLE; Schema: public; Owner: photorigma
--

CREATE TABLE public.db_version (
    ver character varying(20) NOT NULL
);


ALTER TABLE public.db_version OWNER TO photorigma;

--
-- Name: TABLE db_version; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON TABLE public.db_version IS 'Номер версии сайта';


--
-- Name: COLUMN db_version.ver; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.db_version.ver IS 'Номер версии';


--
-- Name: groups_id_seq; Type: SEQUENCE; Schema: public; Owner: photorigma
--

CREATE SEQUENCE public.groups_id_seq
    START WITH 4
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.groups_id_seq OWNER TO photorigma;

--
-- Name: groups; Type: TABLE; Schema: public; Owner: photorigma
--

CREATE TABLE public.groups (
    id integer DEFAULT nextval('public.groups_id_seq'::regclass) NOT NULL,
    name character varying(50) NOT NULL,
    user_rights jsonb
);


ALTER TABLE public.groups OWNER TO photorigma;

--
-- Name: TABLE groups; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON TABLE public.groups IS 'Таблица групп пользователей и прав доступа';


--
-- Name: COLUMN groups.id; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.groups.id IS 'Идентификатор группы';


--
-- Name: COLUMN groups.name; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.groups.name IS 'Название группы';


--
-- Name: COLUMN groups.user_rights; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.groups.user_rights IS 'Права доступа';


--
-- Name: menu; Type: TABLE; Schema: public; Owner: photorigma
--

CREATE TABLE public.menu (
    id integer NOT NULL,
    action character varying(50) NOT NULL,
    url_action character varying(250) NOT NULL,
    name_action character varying(250) NOT NULL,
    short smallint DEFAULT 0 NOT NULL,
    long smallint DEFAULT 0 NOT NULL,
    user_login smallint,
    user_access character varying(250) DEFAULT NULL::character varying
);


ALTER TABLE public.menu OWNER TO photorigma;

--
-- Name: TABLE menu; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON TABLE public.menu IS 'Таблица пунктов меню';


--
-- Name: COLUMN menu.id; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.menu.id IS 'Порядковый номер пункта меню';


--
-- Name: COLUMN menu.action; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.menu.action IS 'Фрагмент из URL, указывающий, что данный пункт меню должен быть неактивным (текущим)';


--
-- Name: COLUMN menu.url_action; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.menu.url_action IS 'URL перехода при выборе пункта меню';


--
-- Name: COLUMN menu.name_action; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.menu.name_action IS 'Пункт из массива $lang, содержащий название пункта меню';


--
-- Name: COLUMN menu.short; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.menu.short IS 'Использовать пункт в кратком (верхнем) меню';


--
-- Name: COLUMN menu.long; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.menu.long IS 'Использовать пункт в длинном (боковом) меню';


--
-- Name: COLUMN menu.user_login; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.menu.user_login IS 'Проверка - зарегистрирован ли пользователь';


--
-- Name: COLUMN menu.user_access; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.menu.user_access IS 'Дополнительные права';


--
-- Name: menu_id_seq; Type: SEQUENCE; Schema: public; Owner: photorigma
--

CREATE SEQUENCE public.menu_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.menu_id_seq OWNER TO photorigma;

--
-- Name: menu_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: photorigma
--

ALTER SEQUENCE public.menu_id_seq OWNED BY public.menu.id;


--
-- Name: news; Type: TABLE; Schema: public; Owner: photorigma
--

CREATE TABLE public.news (
    id integer NOT NULL,
    data_post date NOT NULL,
    data_last_edit timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    user_post integer NOT NULL,
    name_post character varying(50) NOT NULL,
    text_post text NOT NULL,
    tsv_weighted tsvector GENERATED ALWAYS AS ((((((setweight(to_tsvector('russian'::regconfig, (name_post)::text), 'A'::"char") || setweight(to_tsvector('russian'::regconfig, text_post), 'A'::"char")) || setweight(to_tsvector('english'::regconfig, (name_post)::text), 'B'::"char")) || setweight(to_tsvector('english'::regconfig, text_post), 'B'::"char")) || setweight(to_tsvector('simple'::regconfig, (name_post)::text), 'D'::"char")) || setweight(to_tsvector('simple'::regconfig, text_post), 'D'::"char"))) STORED
);


ALTER TABLE public.news OWNER TO photorigma;

--
-- Name: TABLE news; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON TABLE public.news IS 'Новости сайта';


--
-- Name: COLUMN news.id; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.news.id IS 'Идентификатор новости';


--
-- Name: COLUMN news.data_post; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.news.data_post IS 'Дата публикации';


--
-- Name: COLUMN news.data_last_edit; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.news.data_last_edit IS 'Дата обновления';


--
-- Name: COLUMN news.user_post; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.news.user_post IS 'Идентификатор добавившего новость пользователя';


--
-- Name: COLUMN news.name_post; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.news.name_post IS 'Название новости';


--
-- Name: COLUMN news.text_post; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.news.text_post IS 'Текст новости';


--
-- Name: COLUMN news.tsv_weighted; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.news.tsv_weighted IS 'Взвешенный полнотекстовый вектор для данных таблицы news (name_post, text_post). Используется для улучшенного поиска.';


--
-- Name: news_id_seq; Type: SEQUENCE; Schema: public; Owner: photorigma
--

CREATE SEQUENCE public.news_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.news_id_seq OWNER TO photorigma;

--
-- Name: news_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: photorigma
--

ALTER SEQUENCE public.news_id_seq OWNED BY public.news.id;


--
-- Name: photo; Type: TABLE; Schema: public; Owner: photorigma
--

CREATE TABLE public.photo (
    id integer NOT NULL,
    file character varying(50) NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(250) NOT NULL,
    category integer NOT NULL,
    date_upload timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    user_upload integer NOT NULL,
    rate_user double precision DEFAULT 0 NOT NULL,
    rate_moder double precision DEFAULT 0 NOT NULL,
    tsv_weighted tsvector GENERATED ALWAYS AS ((((((setweight(to_tsvector('russian'::regconfig, (name)::text), 'A'::"char") || setweight(to_tsvector('russian'::regconfig, (description)::text), 'B'::"char")) || setweight(to_tsvector('english'::regconfig, (name)::text), 'C'::"char")) || setweight(to_tsvector('english'::regconfig, (description)::text), 'D'::"char")) || setweight(to_tsvector('simple'::regconfig, (name)::text), 'D'::"char")) || setweight(to_tsvector('simple'::regconfig, (description)::text), 'D'::"char"))) STORED
);


ALTER TABLE public.photo OWNER TO photorigma;

--
-- Name: TABLE photo; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON TABLE public.photo IS 'Таблица размещения фотографий';


--
-- Name: COLUMN photo.id; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.photo.id IS 'Идентификатор';


--
-- Name: COLUMN photo.file; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.photo.file IS 'Имя файла';


--
-- Name: COLUMN photo.name; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.photo.name IS 'Название фотографии';


--
-- Name: COLUMN photo.description; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.photo.description IS 'Описание фотографии';


--
-- Name: COLUMN photo.category; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.photo.category IS 'Идентификатор раздела';


--
-- Name: COLUMN photo.date_upload; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.photo.date_upload IS 'Дата загрузки фото';


--
-- Name: COLUMN photo.user_upload; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.photo.user_upload IS 'Идентификатор пользователя, залившего фото';


--
-- Name: COLUMN photo.rate_user; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.photo.rate_user IS 'Оценка от пользователя';


--
-- Name: COLUMN photo.rate_moder; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.photo.rate_moder IS 'Оценка от модератора';


--
-- Name: COLUMN photo.tsv_weighted; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.photo.tsv_weighted IS 'Взвешенный полнотекстовый вектор для данных таблицы photo (name, description). Используется для улучшенного поиска.';


--
-- Name: photo_id_seq; Type: SEQUENCE; Schema: public; Owner: photorigma
--

CREATE SEQUENCE public.photo_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.photo_id_seq OWNER TO photorigma;

--
-- Name: photo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: photorigma
--

ALTER SEQUENCE public.photo_id_seq OWNED BY public.photo.id;


--
-- Name: query_logs; Type: TABLE; Schema: public; Owner: photorigma
--

CREATE TABLE public.query_logs (
    id integer NOT NULL,
    query_hash character(32) NOT NULL,
    query_text text NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    last_used_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    usage_count integer DEFAULT 1 NOT NULL,
    reason text DEFAULT 'other'::text,
    execution_time double precision,
    CONSTRAINT query_logs_reason_check CHECK ((reason = ANY (ARRAY['slow'::text, 'no_placeholders'::text, 'other'::text])))
);


ALTER TABLE public.query_logs OWNER TO photorigma;

--
-- Name: TABLE query_logs; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON TABLE public.query_logs IS 'Логирование SQL-запросов для анализа производительности';


--
-- Name: COLUMN query_logs.id; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.query_logs.id IS 'Уникальный идентификатор записи';


--
-- Name: COLUMN query_logs.query_hash; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.query_logs.query_hash IS 'Хэш запроса для проверки дублирования';


--
-- Name: COLUMN query_logs.query_text; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.query_logs.query_text IS 'Текст SQL-запроса';


--
-- Name: COLUMN query_logs.created_at; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.query_logs.created_at IS 'Время первого логирования';


--
-- Name: COLUMN query_logs.last_used_at; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.query_logs.last_used_at IS 'Время последнего использования';


--
-- Name: COLUMN query_logs.usage_count; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.query_logs.usage_count IS 'Количество использований запроса';


--
-- Name: COLUMN query_logs.reason; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.query_logs.reason IS 'Причина логирования: медленный запрос, отсутствие плейсхолдеров или другое';


--
-- Name: COLUMN query_logs.execution_time; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.query_logs.execution_time IS 'Время выполнения запроса (в миллисекундах)';


--
-- Name: query_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: photorigma
--

CREATE SEQUENCE public.query_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.query_logs_id_seq OWNER TO photorigma;

--
-- Name: query_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: photorigma
--

ALTER SEQUENCE public.query_logs_id_seq OWNED BY public.query_logs.id;


--
-- Name: random_photo; Type: VIEW; Schema: public; Owner: photorigma
--

CREATE VIEW public.random_photo AS
 SELECT id,
    file,
    name,
    description,
    category,
    rate_user,
    rate_moder,
    user_upload
   FROM public.photo
  WHERE (id = ( SELECT photo_1.id
           FROM public.photo photo_1
          ORDER BY (random())
         LIMIT 1));


ALTER VIEW public.random_photo OWNER TO photorigma;

--
-- Name: VIEW random_photo; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON VIEW public.random_photo IS 'Случайно фото';


--
-- Name: rate_moder; Type: TABLE; Schema: public; Owner: photorigma
--

CREATE TABLE public.rate_moder (
    id_foto integer NOT NULL,
    id_user integer NOT NULL,
    rate smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.rate_moder OWNER TO photorigma;

--
-- Name: TABLE rate_moder; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON TABLE public.rate_moder IS 'Оценки модераторов';


--
-- Name: COLUMN rate_moder.id_foto; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.rate_moder.id_foto IS 'Идентификатор фото';


--
-- Name: COLUMN rate_moder.id_user; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.rate_moder.id_user IS 'Идентификатор пользователя';


--
-- Name: COLUMN rate_moder.rate; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.rate_moder.rate IS 'Оценка от -2 до +2';


--
-- Name: rate_user; Type: TABLE; Schema: public; Owner: photorigma
--

CREATE TABLE public.rate_user (
    id_foto integer NOT NULL,
    id_user integer NOT NULL,
    rate smallint DEFAULT 0 NOT NULL
);


ALTER TABLE public.rate_user OWNER TO photorigma;

--
-- Name: TABLE rate_user; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON TABLE public.rate_user IS 'Оценки пользователей';


--
-- Name: COLUMN rate_user.id_foto; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.rate_user.id_foto IS 'Идентификатор фото';


--
-- Name: COLUMN rate_user.id_user; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.rate_user.id_user IS 'Идентификатор пользователя';


--
-- Name: COLUMN rate_user.rate; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.rate_user.rate IS 'Оценка от -2 до +2';


--
-- Name: user_bans; Type: TABLE; Schema: public; Owner: photorigma
--

CREATE TABLE public.user_bans (
    user_id integer NOT NULL,
    banned boolean DEFAULT true NOT NULL,
    reason text,
    expires_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.user_bans OWNER TO photorigma;

--
-- Name: TABLE user_bans; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON TABLE public.user_bans IS 'Информация о бане пользователей';


--
-- Name: COLUMN user_bans.user_id; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.user_bans.user_id IS 'Идентификатор пользователя';


--
-- Name: COLUMN user_bans.banned; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.user_bans.banned IS 'Флаг: пользователь заблокирован';


--
-- Name: COLUMN user_bans.reason; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.user_bans.reason IS 'Причина блокировки';


--
-- Name: COLUMN user_bans.expires_at; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.user_bans.expires_at IS 'Дата окончания бана (если временный)';


--
-- Name: COLUMN user_bans.created_at; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.user_bans.created_at IS 'Дата и время установки бана';


--
-- Name: users; Type: TABLE; Schema: public; Owner: photorigma
--

CREATE TABLE public.users (
    id integer NOT NULL,
    login character varying(32) NOT NULL,
    password character varying(255) NOT NULL,
    real_name character varying(50) NOT NULL,
    email character varying(50) NOT NULL,
    avatar character varying(50) DEFAULT 'no_avatar.jpg'::character varying NOT NULL,
    language character varying(32) DEFAULT 'russian'::character varying NOT NULL,
    date_regist timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    date_last_activ timestamp without time zone,
    date_last_logout timestamp without time zone,
    group_id integer DEFAULT 0 NOT NULL,
    user_rights jsonb,
    theme character varying(32) DEFAULT 'default'::character varying NOT NULL,
    tsv_weighted tsvector GENERATED ALWAYS AS (((((((((setweight(to_tsvector('russian'::regconfig, (login)::text), 'A'::"char") || setweight(to_tsvector('russian'::regconfig, (real_name)::text), 'A'::"char")) || setweight(to_tsvector('russian'::regconfig, (email)::text), 'A'::"char")) || setweight(to_tsvector('english'::regconfig, (login)::text), 'B'::"char")) || setweight(to_tsvector('english'::regconfig, (real_name)::text), 'B'::"char")) || setweight(to_tsvector('english'::regconfig, (email)::text), 'B'::"char")) || setweight(to_tsvector('simple'::regconfig, (login)::text), 'D'::"char")) || setweight(to_tsvector('simple'::regconfig, (real_name)::text), 'D'::"char")) || setweight(to_tsvector('simple'::regconfig, (email)::text), 'D'::"char"))) STORED,
    deleted_at timestamp without time zone,
    permanently_deleted boolean DEFAULT false NOT NULL,
    activation boolean DEFAULT false NOT NULL,
    token character varying(255) DEFAULT NULL::character varying,
    token_expires_at timestamp without time zone,
    email_confirmed boolean DEFAULT false NOT NULL,
    allow_newsletter boolean DEFAULT false NOT NULL,
    timezone character varying(50) DEFAULT 'UTC'::character varying NOT NULL,
    other_params jsonb
);


ALTER TABLE public.users OWNER TO photorigma;

--
-- Name: TABLE users; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON TABLE public.users IS 'Таблица данных пользователя';


--
-- Name: COLUMN users.id; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.id IS 'Идентификатор пользователя';


--
-- Name: COLUMN users.login; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.login IS 'Логин пользователя';


--
-- Name: COLUMN users.password; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.password IS 'Пароль пользователя';


--
-- Name: COLUMN users.real_name; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.real_name IS 'Отображаемое имя пользователя';


--
-- Name: COLUMN users.email; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.email IS 'E-mail пользователя';


--
-- Name: COLUMN users.avatar; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.avatar IS 'Имя файла аватара пользователя';


--
-- Name: COLUMN users.language; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.language IS 'Язык сайта';


--
-- Name: COLUMN users.date_regist; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.date_regist IS 'Дата регистрации';


--
-- Name: COLUMN users.date_last_activ; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.date_last_activ IS 'Дата последней активности';


--
-- Name: COLUMN users.date_last_logout; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.date_last_logout IS 'Дата последнего выхода';


--
-- Name: COLUMN users.group_id; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.group_id IS 'Идентификатор группы пользователя';


--
-- Name: COLUMN users.user_rights; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.user_rights IS 'Права доступа';


--
-- Name: COLUMN users.theme; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.theme IS 'Тема сайта';


--
-- Name: COLUMN users.tsv_weighted; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.tsv_weighted IS 'Взвешенный полнотекстовый вектор для пользовательских данных (login, real_name, email). Используется для улучшенного поиска.';


--
-- Name: COLUMN users.deleted_at; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.deleted_at IS 'Дата и время мягкого удаления пользователя';


--
-- Name: COLUMN users.permanently_deleted; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.permanently_deleted IS 'Флаг окончательного удаления пользователя';


--
-- Name: COLUMN users.activation; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.activation IS 'Флаг активации аккаунта';


--
-- Name: COLUMN users.token; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.token IS 'Для временных токенов';


--
-- Name: COLUMN users.token_expires_at; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.token_expires_at IS 'Время истечения токена';


--
-- Name: COLUMN users.email_confirmed; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.email_confirmed IS 'Подтверждён ли email';


--
-- Name: COLUMN users.allow_newsletter; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.allow_newsletter IS 'Часовой пояс пользователя';


--
-- Name: COLUMN users.other_params; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users.other_params IS 'Прочие параметры пользователя';


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: photorigma
--

CREATE SEQUENCE public.users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO photorigma;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: photorigma
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: users_online; Type: VIEW; Schema: public; Owner: photorigma
--

CREATE VIEW public.users_online AS
 SELECT u.id,
    u.real_name,
    COALESCE(b.banned, false) AS banned
   FROM (public.users u
     LEFT JOIN public.user_bans b ON (((u.id = b.user_id) AND (b.banned = true))))
  WHERE ((u.date_last_activ >= (now() - ( SELECT (((config.value)::text || ' seconds'::text))::interval AS "interval"
           FROM public.config
          WHERE ((config.name)::text = 'time_user_online'::text)))) AND (u.activation = true) AND (u.email_confirmed = true) AND (u.deleted_at IS NULL) AND (u.permanently_deleted = false));


ALTER VIEW public.users_online OWNER TO photorigma;

--
-- Name: VIEW users_online; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON VIEW public.users_online IS 'Список пользователей онлайн';


--
-- Name: COLUMN users_online.id; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users_online.id IS 'Идентификатор пользователя';


--
-- Name: COLUMN users_online.real_name; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users_online.real_name IS 'Отображаемое имя пользователя';


--
-- Name: COLUMN users_online.banned; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON COLUMN public.users_online.banned IS 'Флаг: пользователь забанен';


--
-- Name: category id; Type: DEFAULT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.category ALTER COLUMN id SET DEFAULT nextval('public.category_id_seq'::regclass);


--
-- Name: menu id; Type: DEFAULT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.menu ALTER COLUMN id SET DEFAULT nextval('public.menu_id_seq'::regclass);


--
-- Name: news id; Type: DEFAULT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.news ALTER COLUMN id SET DEFAULT nextval('public.news_id_seq'::regclass);


--
-- Name: photo id; Type: DEFAULT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.photo ALTER COLUMN id SET DEFAULT nextval('public.photo_id_seq'::regclass);


--
-- Name: query_logs id; Type: DEFAULT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.query_logs ALTER COLUMN id SET DEFAULT nextval('public.query_logs_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: category; Type: TABLE DATA; Schema: public; Owner: photorigma
--

INSERT INTO public.category VALUES (0, 'user', 'Пользовательский альбом', 'Персональный пользовательский альбом', DEFAULT);


--
-- Data for Name: change_timestamp; Type: TABLE DATA; Schema: public; Owner: photorigma
--

INSERT INTO public.change_timestamp VALUES ('config', '2025-05-11 02:06:20.053567');


--
-- Data for Name: config; Type: TABLE DATA; Schema: public; Owner: photorigma
--

INSERT INTO public.config VALUES ('best_user', '5');
INSERT INTO public.config VALUES ('copyright_text', 'Проекты Rigma.BiZ');
INSERT INTO public.config VALUES ('gal_width', '95%');
INSERT INTO public.config VALUES ('language', 'russian');
INSERT INTO public.config VALUES ('last_news', '5');
INSERT INTO public.config VALUES ('left_panel', '250');
INSERT INTO public.config VALUES ('max_avatar_h', '100');
INSERT INTO public.config VALUES ('max_avatar_w', '100');
INSERT INTO public.config VALUES ('max_file_size', '2M');
INSERT INTO public.config VALUES ('max_photo_h', '480');
INSERT INTO public.config VALUES ('max_photo_w', '640');
INSERT INTO public.config VALUES ('max_rate', '2');
INSERT INTO public.config VALUES ('meta_description', 'Rigma.BiZ - фотогалерея Gold Rigma');
INSERT INTO public.config VALUES ('meta_keywords', 'Rigma.BiZ photo gallery Gold Rigma');
INSERT INTO public.config VALUES ('right_panel', '250');
INSERT INTO public.config VALUES ('temp_photo_h', '200');
INSERT INTO public.config VALUES ('temp_photo_w', '200');
INSERT INTO public.config VALUES ('title_description', 'Фотогалерея Rigma и Co');
INSERT INTO public.config VALUES ('title_name', 'Rigma Foto');
INSERT INTO public.config VALUES ('time_user_online', '900');
INSERT INTO public.config VALUES ('copyright_year', '2008-2025');
INSERT INTO public.config VALUES ('copyright_url', 'https://rigma.biz/');
INSERT INTO public.config VALUES ('theme', 'default');
INSERT INTO public.config VALUES ('timezone', 'UTC');


--
-- Data for Name: db_version; Type: TABLE DATA; Schema: public; Owner: photorigma
--

INSERT INTO public.db_version VALUES ('0.4.4');


--
-- Data for Name: groups; Type: TABLE DATA; Schema: public; Owner: photorigma
--

INSERT INTO public.groups VALUES (0, 'Гость', '{"admin": 0, "cat_user": 0, "news_add": 0, "pic_view": 1, "news_view": 1, "pic_upload": 0, "comment_add": 0, "cat_moderate": 0, "comment_view": 1, "pic_moderate": 0, "news_moderate": 0, "pic_rate_user": 0, "pic_rate_moder": 0, "comment_moderate": 0}');
INSERT INTO public.groups VALUES (1, 'Пользователь', '{"admin": 0, "cat_user": 1, "news_add": 0, "pic_view": 1, "news_view": 1, "pic_upload": 1, "comment_add": 1, "cat_moderate": 0, "comment_view": 1, "pic_moderate": 0, "news_moderate": 0, "pic_rate_user": 1, "pic_rate_moder": 0, "comment_moderate": 0}');
INSERT INTO public.groups VALUES (2, 'Модератор', '{"admin": 0, "cat_user": 1, "news_add": 1, "pic_view": 1, "news_view": 1, "pic_upload": 1, "comment_add": 1, "cat_moderate": 1, "comment_view": 1, "pic_moderate": 1, "news_moderate": 1, "pic_rate_user": 0, "pic_rate_moder": 1, "comment_moderate": 1}');
INSERT INTO public.groups VALUES (3, 'Администратор', '{"admin": 1, "cat_user": 1, "news_add": 1, "pic_view": 1, "news_view": 1, "pic_upload": 1, "comment_add": 1, "cat_moderate": 1, "comment_view": 1, "pic_moderate": 1, "news_moderate": 1, "pic_rate_user": 1, "pic_rate_moder": 1, "comment_moderate": 1}');


--
-- Data for Name: menu; Type: TABLE DATA; Schema: public; Owner: photorigma
--

INSERT INTO public.menu VALUES (1, 'main', './', 'home', 1, 1, NULL, NULL);
INSERT INTO public.menu VALUES (2, 'regist', '?action=profile&subact=regist', 'regist', 1, 1, 0, NULL);
INSERT INTO public.menu VALUES (3, 'category', '?action=category', 'category', 1, 1, NULL, NULL);
INSERT INTO public.menu VALUES (4, 'user_category', '?action=category&cat=user', 'user_category', 0, 1, NULL, NULL);
INSERT INTO public.menu VALUES (5, 'you_category', '?action=category&cat=user&id=curent', 'you_category', 0, 1, 1, 'cat_user');
INSERT INTO public.menu VALUES (6, 'upload', '?action=photo&subact=upload', 'upload', 0, 1, 1, 'pic_upload');
INSERT INTO public.menu VALUES (7, 'add_category', '?action=category&subact=add', 'add_category', 0, 1, 1, 'cat_moderate');
INSERT INTO public.menu VALUES (8, 'search', '?action=search', 'search', 1, 1, NULL, NULL);
INSERT INTO public.menu VALUES (9, 'news', '?action=news', 'news', 1, 1, NULL, 'news_view');
INSERT INTO public.menu VALUES (10, 'news_add', '?action=news&subact=add', 'news_add', 0, 1, 1, 'news_add');
INSERT INTO public.menu VALUES (11, 'profile', '?action=profile&subact=profile', 'profile', 1, 1, 1, NULL);
INSERT INTO public.menu VALUES (12, 'admin', '?action=admin', 'admin', 1, 1, 1, 'admin');
INSERT INTO public.menu VALUES (13, 'logout', '?action=profile&subact=logout', 'logout', 1, 1, 1, NULL);


--
-- Data for Name: news; Type: TABLE DATA; Schema: public; Owner: photorigma
--



--
-- Data for Name: photo; Type: TABLE DATA; Schema: public; Owner: photorigma
--



--
-- Data for Name: query_logs; Type: TABLE DATA; Schema: public; Owner: photorigma
--



--
-- Data for Name: rate_moder; Type: TABLE DATA; Schema: public; Owner: photorigma
--



--
-- Data for Name: rate_user; Type: TABLE DATA; Schema: public; Owner: photorigma
--



--
-- Data for Name: user_bans; Type: TABLE DATA; Schema: public; Owner: photorigma
--



--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: photorigma
--

INSERT INTO public.users VALUES (1, 'admin', '$2y$12$66PqD9l3yDp3qj40j.rXNeh7JGzjt/AKkizosLmdbyjB7pQmt6UxW', 'Администратор', 'admin@rigma.biz', 'no_avatar.jpg', 'russian', '2009-01-20 12:31:35', '2025-04-07 15:40:15.054838', '2025-04-07 15:40:06.894219', 3, '{"admin": 1, "cat_user": 1, "news_add": 1, "pic_view": 1, "news_view": 1, "pic_upload": 1, "comment_add": 1, "cat_moderate": 1, "comment_view": 1, "pic_moderate": 1, "news_moderate": 1, "pic_rate_user": 1, "pic_rate_moder": 1, "comment_moderate": 1}', 'default', DEFAULT, NULL, false, true, NULL, NULL, true, false, 'UTC', NULL);


--
-- Name: category_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photorigma
--

SELECT pg_catalog.setval('public.category_id_seq', 1, false);


--
-- Name: groups_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photorigma
--

SELECT pg_catalog.setval('public.groups_id_seq', 4, false);


--
-- Name: menu_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photorigma
--

SELECT pg_catalog.setval('public.menu_id_seq', 1, false);


--
-- Name: news_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photorigma
--

SELECT pg_catalog.setval('public.news_id_seq', 1, false);


--
-- Name: photo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photorigma
--

SELECT pg_catalog.setval('public.photo_id_seq', 1, false);


--
-- Name: query_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photorigma
--

SELECT pg_catalog.setval('public.query_logs_id_seq', 17, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photorigma
--

SELECT pg_catalog.setval('public.users_id_seq', 1, false);


--
-- Name: category category_pkey; Type: CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.category
    ADD CONSTRAINT category_pkey PRIMARY KEY (id);


--
-- Name: change_timestamp change_timestamp_pkey; Type: CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.change_timestamp
    ADD CONSTRAINT change_timestamp_pkey PRIMARY KEY (table_name);


--
-- Name: config config_pkey; Type: CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.config
    ADD CONSTRAINT config_pkey PRIMARY KEY (name);


--
-- Name: db_version db_version_pkey; Type: CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.db_version
    ADD CONSTRAINT db_version_pkey PRIMARY KEY (ver);


--
-- Name: groups group_pkey; Type: CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.groups
    ADD CONSTRAINT group_pkey PRIMARY KEY (id);


--
-- Name: menu menu_pkey; Type: CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.menu
    ADD CONSTRAINT menu_pkey PRIMARY KEY (id);


--
-- Name: news news_pkey; Type: CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.news
    ADD CONSTRAINT news_pkey PRIMARY KEY (id);


--
-- Name: photo photo_pkey; Type: CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.photo
    ADD CONSTRAINT photo_pkey PRIMARY KEY (id);


--
-- Name: query_logs query_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.query_logs
    ADD CONSTRAINT query_logs_pkey PRIMARY KEY (id);


--
-- Name: rate_moder rate_moder_pkey; Type: CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.rate_moder
    ADD CONSTRAINT rate_moder_pkey PRIMARY KEY (id_foto, id_user);


--
-- Name: rate_user rate_user_pkey; Type: CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.rate_user
    ADD CONSTRAINT rate_user_pkey PRIMARY KEY (id_foto, id_user);


--
-- Name: user_bans user_bans_pkey; Type: CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.user_bans
    ADD CONSTRAINT user_bans_pkey PRIMARY KEY (user_id);


--
-- Name: users users_login_key; Type: CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_login_key UNIQUE (login);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: idx_category_description_trgm; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_category_description_trgm ON public.category USING gin (description public.gin_trgm_ops);


--
-- Name: idx_category_name_trgm; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_category_name_trgm ON public.category USING gin (name public.gin_trgm_ops);


--
-- Name: idx_category_tsv_weighted; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_category_tsv_weighted ON public.category USING gin (tsv_weighted);


--
-- Name: idx_config_value; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_config_value ON public.config USING btree (value);


--
-- Name: idx_menu_long; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_menu_long ON public.menu USING btree (long);


--
-- Name: idx_menu_short; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_menu_short ON public.menu USING btree (short);


--
-- Name: idx_news_data_last_edit; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_news_data_last_edit ON public.news USING btree (data_last_edit);


--
-- Name: idx_news_namepost_trgm; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_news_namepost_trgm ON public.news USING gin (name_post public.gin_trgm_ops);


--
-- Name: idx_news_textpost_trgm; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_news_textpost_trgm ON public.news USING gin (text_post public.gin_trgm_ops);


--
-- Name: idx_news_tsv_weighted; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_news_tsv_weighted ON public.news USING gin (tsv_weighted);


--
-- Name: idx_photo_category_user_upload; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_photo_category_user_upload ON public.photo USING btree (category, user_upload);


--
-- Name: idx_photo_date_upload; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_photo_date_upload ON public.photo USING btree (date_upload);


--
-- Name: idx_photo_description_trgm; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_photo_description_trgm ON public.photo USING gin (description public.gin_trgm_ops);


--
-- Name: idx_photo_name_trgm; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_photo_name_trgm ON public.photo USING gin (name public.gin_trgm_ops);


--
-- Name: idx_photo_tsv_weighted; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_photo_tsv_weighted ON public.photo USING gin (tsv_weighted);


--
-- Name: idx_photo_user_upload_group; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_photo_user_upload_group ON public.photo USING btree (user_upload, id);


--
-- Name: idx_user_bans_expires; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_user_bans_expires ON public.user_bans USING btree (expires_at);


--
-- Name: idx_user_bans_user_id; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_user_bans_user_id ON public.user_bans USING btree (user_id);


--
-- Name: idx_users_allow_newsletter; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_users_allow_newsletter ON public.users USING btree (allow_newsletter);


--
-- Name: idx_users_date_last_activ; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_users_date_last_activ ON public.users USING btree (date_last_activ);


--
-- Name: idx_users_deleted_at; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_users_deleted_at ON public.users USING btree (deleted_at);


--
-- Name: idx_users_email_trgm; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_users_email_trgm ON public.users USING gin (email public.gin_trgm_ops);


--
-- Name: idx_users_login_trgm; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_users_login_trgm ON public.users USING gin (login public.gin_trgm_ops);


--
-- Name: idx_users_real_name_trgm; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_users_real_name_trgm ON public.users USING gin (real_name public.gin_trgm_ops);


--
-- Name: idx_users_token; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_users_token ON public.users USING btree (token);


--
-- Name: idx_users_token_expires; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_users_token_expires ON public.users USING btree (token_expires_at);


--
-- Name: idx_users_tsv_weighted; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE INDEX idx_users_tsv_weighted ON public.users USING gin (tsv_weighted);


--
-- Name: INDEX idx_users_tsv_weighted; Type: COMMENT; Schema: public; Owner: photorigma
--

COMMENT ON INDEX public.idx_users_tsv_weighted IS 'GIN-индекс для поля tsv_weighted';


--
-- Name: query_logs_query_hash_key; Type: INDEX; Schema: public; Owner: photorigma
--

CREATE UNIQUE INDEX query_logs_query_hash_key ON public.query_logs USING btree (query_hash);


--
-- Name: config trg_config_delete; Type: TRIGGER; Schema: public; Owner: photorigma
--

CREATE TRIGGER trg_config_delete AFTER DELETE ON public.config FOR EACH ROW EXECUTE FUNCTION public.update_change_timestamp();


--
-- Name: config trg_config_insert; Type: TRIGGER; Schema: public; Owner: photorigma
--

CREATE TRIGGER trg_config_insert AFTER INSERT ON public.config FOR EACH ROW EXECUTE FUNCTION public.update_change_timestamp();


--
-- Name: config trg_config_update; Type: TRIGGER; Schema: public; Owner: photorigma
--

CREATE TRIGGER trg_config_update AFTER UPDATE ON public.config FOR EACH ROW EXECUTE FUNCTION public.update_change_timestamp();


--
-- Name: category trg_prevent_deletion_category; Type: TRIGGER; Schema: public; Owner: photorigma
--

CREATE TRIGGER trg_prevent_deletion_category BEFORE DELETE ON public.category FOR EACH ROW EXECUTE FUNCTION public.prevent_deletion_of_service_categories();


--
-- Name: groups trg_prevent_deletion_groups; Type: TRIGGER; Schema: public; Owner: photorigma
--

CREATE TRIGGER trg_prevent_deletion_groups BEFORE DELETE ON public.groups FOR EACH ROW EXECUTE FUNCTION public.prevent_deletion_of_service_groups();


--
-- Name: rate_moder update_rate_moder_after_delete; Type: TRIGGER; Schema: public; Owner: photorigma
--

CREATE TRIGGER update_rate_moder_after_delete AFTER DELETE ON public.rate_moder FOR EACH ROW EXECUTE FUNCTION public.update_rate_moder_after_delete();


--
-- Name: rate_moder update_rate_moder_after_insert; Type: TRIGGER; Schema: public; Owner: photorigma
--

CREATE TRIGGER update_rate_moder_after_insert AFTER INSERT ON public.rate_moder FOR EACH ROW EXECUTE FUNCTION public.update_rate_moder_after_insert();


--
-- Name: rate_user update_rate_user_after_delete; Type: TRIGGER; Schema: public; Owner: photorigma
--

CREATE TRIGGER update_rate_user_after_delete AFTER DELETE ON public.rate_user FOR EACH ROW EXECUTE FUNCTION public.update_rate_user_after_delete();


--
-- Name: rate_user update_rate_user_after_insert; Type: TRIGGER; Schema: public; Owner: photorigma
--

CREATE TRIGGER update_rate_user_after_insert AFTER INSERT ON public.rate_user FOR EACH ROW EXECUTE FUNCTION public.update_rate_user_after_insert();


--
-- Name: rate_moder m_rate_photo; Type: FK CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.rate_moder
    ADD CONSTRAINT m_rate_photo FOREIGN KEY (id_foto) REFERENCES public.photo(id) ON DELETE CASCADE;


--
-- Name: rate_moder m_rate_users; Type: FK CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.rate_moder
    ADD CONSTRAINT m_rate_users FOREIGN KEY (id_user) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: news news_users; Type: FK CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.news
    ADD CONSTRAINT news_users FOREIGN KEY (user_post) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: photo photo_category; Type: FK CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.photo
    ADD CONSTRAINT photo_category FOREIGN KEY (category) REFERENCES public.category(id) ON DELETE RESTRICT;


--
-- Name: photo photo_users; Type: FK CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.photo
    ADD CONSTRAINT photo_users FOREIGN KEY (user_upload) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: rate_user u_rate_photo; Type: FK CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.rate_user
    ADD CONSTRAINT u_rate_photo FOREIGN KEY (id_foto) REFERENCES public.photo(id) ON DELETE CASCADE;


--
-- Name: rate_user u_rate_users; Type: FK CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.rate_user
    ADD CONSTRAINT u_rate_users FOREIGN KEY (id_user) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_bans user_bans_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.user_bans
    ADD CONSTRAINT user_bans_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: users users_groups; Type: FK CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_groups FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--

