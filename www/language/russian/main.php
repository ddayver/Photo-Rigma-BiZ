<?php
/**
 * @file        language/russian/main.php
 * @brief       Переменные языка для основной страницы сайта (русский)
 * @author      Dark Dayver
 * @version     0.2.0
 * @date        27/03-2012
 * @details     Содержит переменные языка для основной страницы сайта (русский). Более подробная информация в группе @ref LanguageRussian
 */
/// @cond
if (IN_GALLERY !== TRUE)
{
	die('HACK!');
}
/// @endcond

/// Название языка
$lang_name = 'Русский';
/**
 * @defgroup LanguageRussian Языковые переменные русского языка
 */
/*@{*/

/**
 * @var $lang
 * @brief Массив языковых переменных
 */
$lang = array();
/**
 * @defgroup LanguageRussianMain Основные языковые переменные
 */
/*@{*/
$lang['main']['main'] = 'Главная';
$lang['main']['search'] = 'Поиск';
$lang['main']['rate'] = 'Оценка';
$lang['main']['top_foto'] = 'Лучшее фото';
$lang['main']['last_foto'] = 'Последнее фото';
$lang['main']['rand_foto'] = 'Случайное фото';
$lang['main']['cat_foto'] = 'Раздел с фото';
$lang['main']['no_foto'] = 'Нет фото!';
$lang['main']['user_add'] = 'Добавил';
$lang['main']['data_add'] = 'Дата публикации (последней редакции)';
$lang['main']['title_news'] = 'Новости';
$lang['main']['no_news'] = 'Нет новостей';
$lang['main']['no_user_add'] = 'Автор не существует';
$lang['main']['user_block'] = 'Панель пользователя';
$lang['main']['login'] = 'Логин';
$lang['main']['pass'] = 'Пароль';
$lang['main']['enter'] = 'Войти';
$lang['main']['logout'] = 'Выйти';
$lang['main']['forgot_password'] = 'Потеряли пароль?';
$lang['main']['registration'] = 'Зарегистрироваться';
$lang['main']['redirect_title'] = 'Переадресация';
$lang['main']['redirect_description'] = 'Сейчас произойдет автоматический переход на страницу сайта';
$lang['main']['redirect_url'] = 'Нажмите здесь если Ваш браузер не поддерживает переадресацию';
$lang['main']['login_ok'] = ', вход успешно выполнен!';
$lang['main']['login_error'] = 'Неверные имя пользователя и/или пароль!';
$lang['main']['hi_user'] = 'Привет';
$lang['main']['group'] = 'Группа';
$lang['main']['stat_title'] = 'Статистика';
$lang['main']['stat_regist'] = 'Зарегистрировано пользователей на сайте';
$lang['main']['stat_photo'] = 'Всего загружено изображений';
$lang['main']['stat_category'] = 'Всего разделов (из них пользовательских)';
$lang['main']['stat_user_admin'] = 'Всего администраторов';
$lang['main']['stat_user_moder'] = 'Всего преподавателей';
$lang['main']['stat_rate_user'] = 'Выставлено оценок пользователями';
$lang['main']['stat_rate_moder'] = 'Выставлено оценок преподавателями';
$lang['main']['stat_online'] = 'Зарегистрированные пользователи на сайте';
$lang['main']['stat_no_online'] = 'отсутствуют.';
$lang['main']['user_name'] = 'Имя пользователя';
$lang['main']['best_user_1'] = 'ТОП-';
$lang['main']['best_user_2'] = ' пользователей';
$lang['main']['best_user'] = 'ТОП-%d пользователей';
$lang['main']['best_user_photo'] = 'Загружено изображений';
$lang['main']['name_of'] = 'Название';
$lang['main']['description_of'] = 'Описание';
$lang['main']['no_category'] = 'Нет разделов!';
$lang['main']['edit_news'] = 'Редактировать новость';
$lang['main']['delete_news'] = 'Удалить новость';
$lang['main']['confirm_delete_news'] = 'Вы уверены, что хотите удалить новость';
/*@}*/

/**
 * @defgroup LanguageRussianMenu Переменные для пунктов меню сайта
 */
/*@{*/
$lang['menu']['name_block'] = 'Панель навигации';
$lang['menu']['home'] = 'Главная';
$lang['menu']['regist'] = 'Регистрация';
$lang['menu']['category'] = 'Разделы';
$lang['menu']['user_category'] = 'Пользовательские альбомы';
$lang['menu']['you_category'] = 'Ваш альбом';
$lang['menu']['upload'] = 'Загрузить изображение';
$lang['menu']['add_category'] = 'Добавить раздел';
$lang['menu']['search'] = 'Поиск';
$lang['menu']['news'] = 'Архив новостей';
$lang['menu']['news_add'] = 'Добавить новость';
$lang['menu']['profile'] = 'Профиль';
$lang['menu']['admin'] = 'Администрирование';
$lang['menu']['logout'] = 'Выйти';
/*@}*/

/**
 * @defgroup LanguageRussianCategory Переменные для процедур обзора и управления разделами
 */
/*@{*/
$lang['category']['category'] = 'Раздел';
$lang['category']['name_block'] = 'Разделы';
$lang['category']['of_category'] = ' раздела';
$lang['category']['count_photo'] = 'Всего фото в разделе';
$lang['category']['count_user_category'] = 'всего';
$lang['category']['no_user_category'] = 'нет альбомов';
$lang['category']['error_no_category'] = 'Раздел не существует!';
$lang['category']['error_no_photo'] = 'Раздел не содержит фотографий!';
$lang['category']['users_album'] = 'Пользовательские альбомы';
$lang['category']['edit'] = 'Редактировать раздел';
$lang['category']['delete'] = 'Удалить раздел';
$lang['category']['add'] = 'Создание раздела';
$lang['category']['cat_dir'] = 'Директория раздела';
$lang['category']['confirm_delete1'] = 'Вы уверены, что хотите удалить раздел';
$lang['category']['confirm_delete2'] = '? Операция необратима! Все изображения из раздела будут удалены!';
$lang['category']['save'] = 'Изменить';
$lang['category']['cancel'] = 'Отменить';
$lang['category']['added'] = 'Создать';
$lang['category']['deleted_sucesful'] = 'успешно удален!';
$lang['category']['added_sucesful'] = 'успешно добавлен!';
$lang['category']['deleted_error'] = 'Невозможно удалить несуществующий раздел!';
$lang['category']['added_error'] = 'Невозможно создать раздел!';
$lang['category']['no_name'] = 'Без названия';
$lang['category']['no_description'] = 'Без описания';
/*@}*/

/**
 * @defgroup LanguageRussianLogin Переменные для процедур регистрации и восстановления пароля
 */
/*@{*/
$lang['profile']['regist'] = 'Регистрация';
$lang['profile']['login'] = 'Имя пользователя';
$lang['profile']['password'] = 'Пароль';
$lang['profile']['re_password'] = 'Повторно пароль';
$lang['profile']['email'] = 'E-mail';
$lang['profile']['real_name'] = 'Отображаемое имя';
$lang['profile']['register'] = 'Зарегистрировать';
$lang['profile']['user'] = 'Пользователь';
$lang['profile']['registered'] = 'успешно зарегистрирован!';
$lang['profile']['error'] = 'Ошибка(и)';
$lang['profile']['error_login'] = 'Не верно указано имя пользователя.';
$lang['profile']['error_password'] = 'Не верно указан пароль.';
$lang['profile']['error_re_password'] = 'Пароли не совпадают.';
$lang['profile']['error_email'] = 'Не верно указан e-mail.';
$lang['profile']['error_real_name'] = 'Не верно указано отображаемое имя.';
$lang['profile']['error_login_exists'] = 'Такое имя пользователя уже существует.';
$lang['profile']['error_email_exists'] = 'Такой e-mail уже существует.';
$lang['profile']['error_real_name_exists'] = 'Такое отображаемое имя уже существует.';
$lang['profile']['error_captcha'] = 'Неверное решеие примера для защиты от ботов.';
$lang['profile']['profile'] = 'Профиль';
$lang['profile']['edit_profile'] = 'Редактирование профиля';
$lang['profile']['confirm_password'] = 'Подтвердите изменения паролем';
$lang['profile']['save_profile'] = 'Сохранить профиль';
$lang['profile']['help_edit'] = 'только если планируете изменить эти данные';
$lang['profile']['avatar'] = 'Аватар пользователя';
$lang['profile']['delete_avatar'] = 'Удалить аватар';
$lang['profile']['captcha'] = 'Защита от ботов. Для регистрации решите следующий пример:';
/*@}*/

/**
 * @defgroup LanguageRussianNews Переменные для новостей сайта
 */
/*@{*/
$lang['news']['news'] = 'Архив новостей';
$lang['news']['title'] = 'Новость';
$lang['news']['name_post'] = 'Название';
$lang['news']['text_post'] = 'Содержание';
$lang['news']['edit_post'] = 'Сохранить изменения';
$lang['news']['add_post'] = 'Добавить новость';
$lang['news']['del_post'] = 'успешно удалена!';
$lang['news']['num_news'] = 'Всего новостей';
$lang['news']['on_years'] = 'по годам';
$lang['news']['on'] = 'за';
$lang['news']['year'] = 'год';
$lang['news']['years'] = 'года';
$lang['news']['on_month'] = 'по месяцам';
$lang['news']['01'] = 'январь';
$lang['news']['02'] = 'февраль';
$lang['news']['03'] = 'март';
$lang['news']['04'] = 'апрель';
$lang['news']['05'] = 'май';
$lang['news']['06'] = 'июнь';
$lang['news']['07'] = 'июль';
$lang['news']['08'] = 'август';
$lang['news']['09'] = 'сентябрь';
$lang['news']['10'] = 'октябрь';
$lang['news']['11'] = 'ноябрь';
$lang['news']['12'] = 'декабрь';
/*@}*/

/**
 * @defgroup LanguageRussianPhoto Переменные для процедур вывода, обработки, оценки изображений
 */
/*@{*/
$lang['photo']['title'] = 'Изображение';
$lang['photo']['of_photo'] = ' изображения';
$lang['photo']['rate_user'] = 'Оценка пользователей';
$lang['photo']['rate_moder'] = 'Оценка преподавателей';
$lang['photo']['rate_you'] = 'Ваша оценка';
$lang['photo']['if_user'] = 'как пользователя';
$lang['photo']['if_moder'] = 'как преподавателя';
$lang['photo']['rate'] = 'Оценить';
$lang['photo']['edit'] = 'Редактировать изображение';
$lang['photo']['delete'] = 'Удалить изображение';
$lang['photo']['confirm_delete'] = 'Вы уверены, что хотите удалить изображение';
$lang['photo']['filename'] = 'Изменить';
$lang['photo']['save'] = 'Изменить';
$lang['photo']['cancel'] = 'Отменить';
$lang['photo']['select_file'] = 'Выберите файл';
$lang['photo']['upload'] = 'Загрузить';
$lang['photo']['no_name'] = 'Без названия';
$lang['photo']['no_description'] = 'Без описания';
$lang['photo']['error_upload'] = 'Невозможно загрузить изображение!';
$lang['photo']['error_delete'] = 'невозможно удалить!';
$lang['photo']['complite_upload'] = 'успешно загружено!';
$lang['photo']['complite_delete'] = 'успешно удалено!';
/*@}*/

/**
 * @defgroup LanguageRussianSearch Переменные для страницы поиска
 */
/*@{*/
$lang['search']['title'] = 'Введите строку для поиска и выберите диапазон';
$lang['search']['need_user'] = 'пользователи';
$lang['search']['need_category'] = 'разделы';
$lang['search']['need_news'] = 'новости';
$lang['search']['need_photo'] = 'изображения';
$lang['search']['find'] = 'Найдены';
$lang['search']['no_find'] = 'Ничего не найдено';
/*@}*/

/**
 * @defgroup LanguageRussianAdmin Переменные для Админки
 */
/*@{*/
$lang['admin']['title'] = 'Администрирование';
$lang['admin']['admin_pass'] = 'Введите пароль для входа в Админку';
$lang['admin']['select_subact'] = 'Выберите пункт Админки';
$lang['admin']['settings'] = 'Основные настройки';
$lang['admin']['admin_user'] = 'Управление пользователями';
$lang['admin']['admin_group'] = 'Управление группами';
$lang['admin']['main_settings'] = 'Основные параметры';
$lang['admin']['title_name'] = 'Название сайта';
$lang['admin']['title_name_description'] = 'Название сайта используется в заговловке страниц сайта';
$lang['admin']['title_description'] = 'Описание сайта';
$lang['admin']['title_description_description'] = 'Описание сайта используется для замены логотипа сайта при невозможности пользователя загружать изображения';
$lang['admin']['meta_description'] = 'Мета-тег описания сайта';
$lang['admin']['meta_description_description'] = 'Мета-тег описания сайта используется поисковиками для отображения проиндексированого сайта в базах - должен кратко описывать назначение сайта';
$lang['admin']['meta_keywords'] = 'Мета-тег ключевых слов';
$lang['admin']['meta_keywords_description'] = 'Мета-тег ключевых слов используется поисковиками для индексирования сайта в базах - укажите через пробел ключевые слова, отображающие суть сайта';
$lang['admin']['appearance_settings'] = 'Внешний вид сайта';
$lang['admin']['gal_width'] = 'Ширина галлереи';
$lang['admin']['gal_width_description'] = 'Ширина галлереи позволяет настроить отображаемую ширину всей галлереи, можно указать в пикселях или процентах';
$lang['admin']['left_panel'] = 'Ширина левой колонки';
$lang['admin']['left_panel_description'] = 'Ширина левой колонки сайта, можно указать в пикселях или процентах';
$lang['admin']['right_panel'] = 'Ширина правой колонки';
$lang['admin']['right_panel_description'] = 'Ширина правой колонки сайта, можно указать в пикселях или процентах';
$lang['admin']['language'] = 'Язык сайта';
$lang['admin']['language_description'] = 'Выбор языка сайта из существующих';
$lang['admin']['themes'] = 'Шаблон сайта';
$lang['admin']['themes_description'] = 'Выбор шаблона сайта из существующих';
$lang['admin']['size_settings'] = 'Используемые размеры';
$lang['admin']['max_file_size'] = 'Максимальный объем загружаемого файла';
$lang['admin']['max_file_size_description'] = 'Максимальный объем загружаемого файла, можно указать в байтах, кило-, мега- и гиго-байтах. Если указанный размер превышает допустимый в настройках PHP, то будет установлен указанный в настройках PHP.';
$lang['admin']['max_photo'] = 'Максимальный размер изображений';
$lang['admin']['max_photo_description'] = 'Максимальный размер изображения, до которого будет сжато отображаемое загруженное изображение на страницах сайта (в пикселях в виде ВЫСОТА х ШИРИНА)';
$lang['admin']['temp_photo'] = 'Максимальный размер эскизов';
$lang['admin']['temp_photo_description'] = 'Максимальный размер эскизов изображений, отображаемых в списках категорий, в блоках сайта (в пикселях в виде ВЫСОТА х ШИРИНА)';
$lang['admin']['max_avatar'] = 'Максимальный размер аватар';
$lang['admin']['max_avatar_description'] = 'Максимальный размер аватар, загружаеміх пользователями в свой профиль (в пикселях в виде ВЫСОТА х ШИРИНА)';
$lang['admin']['copyright_settings'] = 'Настройки копирайта';
$lang['admin']['copyright_year'] = 'Год копирайта';
$lang['admin']['copyright_year_description'] = 'Указываем, какой год (года) отображать в копирайте';
$lang['admin']['copyright_text'] = 'Текст копирайта';
$lang['admin']['copyright_text_description'] = 'Какой текст указать названием ссылки';
$lang['admin']['copyright_url'] = 'Ссылка копирайта';
$lang['admin']['copyright_url_description'] = 'Какую ссылку использовать в копирайте';
$lang['admin']['additional_settings'] = 'Дополнительные настройки';
$lang['admin']['last_news'] = 'Последнии новости';
$lang['admin']['last_news_description'] = 'Количество последних отображаемых новостей на главной странице сайта';
$lang['admin']['best_user'] = 'Лучшие пользователи';
$lang['admin']['best_user_description'] = 'Количество отображаемых лучших пользователей';
$lang['admin']['max_rate'] = 'Максимальная оценка';
$lang['admin']['max_rate_description'] = 'Максимально допустимая оценка при оценке изображения (от -значение до +значение)';
$lang['admin']['save_settings'] = 'Сохранить настройки';
$lang['admin']['search_user'] = 'Поиск пользователя';
$lang['admin']['find_user'] = 'Найдены следующие пользователи';
$lang['admin']['no_find_user'] = 'Такого пользователя нет';
$lang['admin']['login'] = 'Имя пользователя';
$lang['admin']['email'] = 'E-mail';
$lang['admin']['real_name'] = 'Отображаемое имя';
$lang['admin']['avatar'] = 'Аватар пользователя';
$lang['admin']['user_rights'] = 'Права пользователя';
$lang['admin']['pic_view'] = 'Просматривать изображения';
$lang['admin']['pic_rate_user'] = 'Оценивать как пользователь';
$lang['admin']['pic_rate_moder'] = 'Оценивать как преподаватель (модератор)';
$lang['admin']['pic_upload'] = 'Загружать изображения';
$lang['admin']['pic_moderate'] = 'Модерировать изображения';
$lang['admin']['cat_moderate'] = 'Управлять разделами';
$lang['admin']['cat_user'] = 'Использовать личный пользовательский альбом';
$lang['admin']['comment_view'] = 'Просматривать комментарии';
$lang['admin']['comment_add'] = 'Оставлять комментарии';
$lang['admin']['comment_moderate'] = 'Модерировать комментарии';
$lang['admin']['news_view'] = 'Просматривать новости';
$lang['admin']['news_add'] = 'Добавлять новости';
$lang['admin']['news_moderate'] = 'Модерировать новости';
$lang['admin']['admin'] = 'Права Админа';
$lang['admin']['help_edit_user'] = 'При смене группы пользователя права пользователя будут изменены на права группы';
$lang['admin']['help_search_user'] = 'Для отображения всех пользователей используйте "*" как шаблон для поиска';
$lang['admin']['save_user'] = 'Сохранить пользователя';
$lang['admin']['select_group'] = 'Выберите изменяемую группу';
$lang['admin']['edit_group'] = 'Редактировать группу';
$lang['admin']['group_rights'] = 'Права группы';
$lang['admin']['save_group'] = 'Сохранить группу';
/*@}*/
/*@}*/
?>
