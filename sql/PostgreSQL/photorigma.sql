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

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: category; Type: TABLE; Schema: public; Owner: photorigma
--

CREATE TABLE public.category (
    id integer NOT NULL,
    folder character varying(50) NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(250) NOT NULL
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
-- Name: groups; Type: TABLE; Schema: public; Owner: photorigma
--

CREATE TABLE public.groups (
    id integer DEFAULT 0 NOT NULL,
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
    text_post text NOT NULL
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
    rate_moder double precision DEFAULT 0 NOT NULL
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
    theme character varying(32) DEFAULT 'default'::character varying NOT NULL
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
-- Name: users id; Type: DEFAULT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: category; Type: TABLE DATA; Schema: public; Owner: photorigma
--

COPY public.category (id, folder, name, description) FROM stdin;
0	user	Пользовательский альбом	Персональный пользовательский альбом
\.


--
-- Data for Name: config; Type: TABLE DATA; Schema: public; Owner: photorigma
--

COPY public.config (name, value) FROM stdin;
best_user	5
copyright_text	Проекты Rigma.BiZ
copyright_url	http://rigma.biz/
copyright_year	2008-2013
gal_width	95%
language	russian
last_news	5
left_panel	250
max_avatar_h	100
max_avatar_w	100
max_file_size	2M
max_photo_h	480
max_photo_w	640
max_rate	2
meta_description	Rigma.BiZ - фотогалерея Gold Rigma
meta_keywords	Rigma.BiZ photo gallery Gold Rigma
right_panel	250
temp_photo_h	200
temp_photo_w	200
themes	default
title_description	Фотогалерея Rigma и Co
title_name	Rigma Foto
time_user_online	900
\.


--
-- Data for Name: db_version; Type: TABLE DATA; Schema: public; Owner: photorigma
--

COPY public.db_version (ver) FROM stdin;
0.4.0
\.


--
-- Data for Name: groups; Type: TABLE DATA; Schema: public; Owner: photorigma
--

COPY public.groups (id, name, user_rights) FROM stdin;
0	Гость	{"admin": 0, "cat_user": 0, "news_add": 0, "pic_view": 1, "news_view": 1, "pic_upload": 0, "comment_add": 0, "cat_moderate": 0, "comment_view": 1, "pic_moderate": 0, "news_moderate": 0, "pic_rate_user": 0, "pic_rate_moder": 0, "comment_moderate": 0}
1	Пользователь	{"admin": 0, "cat_user": 1, "news_add": 0, "pic_view": 1, "news_view": 1, "pic_upload": 1, "comment_add": 1, "cat_moderate": 0, "comment_view": 1, "pic_moderate": 0, "news_moderate": 0, "pic_rate_user": 1, "pic_rate_moder": 0, "comment_moderate": 0}
2	Модератор	{"admin": 0, "cat_user": 1, "news_add": 1, "pic_view": 1, "news_view": 1, "pic_upload": 1, "comment_add": 1, "cat_moderate": 1, "comment_view": 1, "pic_moderate": 1, "news_moderate": 1, "pic_rate_user": 0, "pic_rate_moder": 1, "comment_moderate": 1}
3	Администратор	{"admin": 1, "cat_user": 1, "news_add": 1, "pic_view": 1, "news_view": 1, "pic_upload": 1, "comment_add": 1, "cat_moderate": 1, "comment_view": 1, "pic_moderate": 1, "news_moderate": 1, "pic_rate_user": 1, "pic_rate_moder": 1, "comment_moderate": 1}
\.


--
-- Data for Name: menu; Type: TABLE DATA; Schema: public; Owner: photorigma
--

COPY public.menu (id, action, url_action, name_action, short, long, user_login, user_access) FROM stdin;
1	main	./	home	1	1	\N	\N
2	regist	?action=profile&subact=regist	regist	1	1	0	\N
3	category	?action=category	category	1	1	\N	\N
4	user_category	?action=category&cat=user	user_category	0	1	\N	\N
5	you_category	?action=category&cat=user&id=curent	you_category	0	1	1	cat_user
6	upload	?action=photo&subact=upload	upload	0	1	1	pic_upload
7	add_category	?action=category&subact=add	add_category	0	1	1	cat_moderate
8	search	?action=search	search	1	1	\N	\N
9	news	?action=news	news	1	1	\N	news_view
10	news_add	?action=news&subact=add	news_add	0	1	1	news_add
11	profile	?action=profile&subact=profile	profile	1	1	1	\N
12	admin	?action=admin	admin	1	1	1	admin
13	logout	?action=profile&subact=logout	logout	1	1	1	\N
\.


--
-- Data for Name: news; Type: TABLE DATA; Schema: public; Owner: photorigma
--

COPY public.news (id, data_post, data_last_edit, user_post, name_post, text_post) FROM stdin;
\.


--
-- Data for Name: photo; Type: TABLE DATA; Schema: public; Owner: photorigma
--

COPY public.photo (id, file, name, description, category, date_upload, user_upload, rate_user, rate_moder) FROM stdin;
\.


--
-- Data for Name: rate_moder; Type: TABLE DATA; Schema: public; Owner: photorigma
--

COPY public.rate_moder (id_foto, id_user, rate) FROM stdin;
\.


--
-- Data for Name: rate_user; Type: TABLE DATA; Schema: public; Owner: photorigma
--

COPY public.rate_user (id_foto, id_user, rate) FROM stdin;
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: photorigma
--

COPY public.users (id, login, password, real_name, email, avatar, language, date_regist, date_last_activ, date_last_logout, group_id, user_rights, theme) FROM stdin;
1	admin	$2y$12$66PqD9l3yDp3qj40j.rXNeh7JGzjt/AKkizosLmdbyjB7pQmt6UxW	Администратор	admin@rigma.biz	no_avatar.jpg	russian	2009-01-20 12:31:35	2025-02-28 21:04:24	2025-02-27 09:45:08	3	{"admin": 1, "cat_user": 1, "news_add": 1, "pic_view": 1, "news_view": 1, "pic_upload": 1, "comment_add": 1, "cat_moderate": 1, "comment_view": 1, "pic_moderate": 1, "news_moderate": 1, "pic_rate_user": 1, "pic_rate_moder": 1, "comment_moderate": 1}	default
\.


--
-- Name: category_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photorigma
--

SELECT pg_catalog.setval('public.category_id_seq', 1, false);


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
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: photorigma
--

SELECT pg_catalog.setval('public.users_id_seq', 1, false);


--
-- Name: category category_pkey; Type: CONSTRAINT; Schema: public; Owner: photorigma
--

ALTER TABLE ONLY public.category
    ADD CONSTRAINT category_pkey PRIMARY KEY (id);


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
-- PostgreSQL database dump complete
--

