<?php
/**
* @file		language/russian/admin.php
* @brief	Переменные языка для Админки (русский)
* @author	Dark Dayver
* @version	0.1.1
* @date		27/03-2012
* @details	Содержит переменные языка для Админки (русский). Более подробная информация в группе @ref LanguageRussian
*/
/// @cond
if (IN_GALLERY)
{
	die('HACK!');
}
/// @endcond

/**
* @ingroup LanguageRussian
*/
/*@{*/

/**
* @defgroup LanguageRussianAdmin Переменные для Админки
*/
/*@{*/
$lang['admin_title'] = 'Администрирование';
$lang['admin_admin_pass'] = 'Введите пароль для входа в Админку';
$lang['admin_select_subact'] = 'Выберите пункт Админки';
$lang['admin_settings'] = 'Основные настройки';
$lang['admin_admin_user'] = 'Управление пользователями';
$lang['admin_admin_group'] = 'Управление группами';
$lang['admin_main_settings'] = 'Основные параметры';
$lang['admin_title_name'] = 'Название сайта';
$lang['admin_title_name_description'] = 'Название сайта используется в заговловке страниц сайта';
$lang['admin_title_description'] = 'Описание сайта';
$lang['admin_title_description_description'] = 'Описание сайта используется для замены логотипа сайта при невозможности пользователя загружать изображения';
$lang['admin_meta_description'] = 'Мета-тег описания сайта';
$lang['admin_meta_description_description'] = 'Мета-тег описания сайта используется поисковиками для отображения проиндексированого сайта в базах - должен кратко описывать назначение сайта';
$lang['admin_meta_keywords'] = 'Мета-тег ключевых слов';
$lang['admin_meta_keywords_description'] = 'Мета-тег ключевых слов используется поисковиками для индексирования сайта в базах - укажите через пробел ключевые слова, отображающие суть сайта';
$lang['admin_appearance_settings'] = 'Внешний вид сайта';
$lang['admin_gal_width'] = 'Ширина галлереи';
$lang['admin_gal_width_description'] = 'Ширина галлереи позволяет настроить отображаемую ширину всей галлереи, можно указать в пикселях или процентах';
$lang['admin_left_panel'] = 'Ширина левой колонки';
$lang['admin_left_panel_description'] = 'Ширина левой колонки сайта, можно указать в пикселях или процентах';
$lang['admin_right_panel'] = 'Ширина правой колонки';
$lang['admin_right_panel_description'] = 'Ширина правой колонки сайта, можно указать в пикселях или процентах';
$lang['admin_language'] = 'Язык сайта';
$lang['admin_language_description'] = 'Выбор языка сайта из существующих';
$lang['admin_themes'] = 'Шаблон сайта';
$lang['admin_themes_description'] = 'Выбор шаблона сайта из существующих';
$lang['admin_size_settings'] = 'Используемые размеры';
$lang['admin_max_file_size'] = 'Максимальный объем загружаемого файла';
$lang['admin_max_file_size_description'] = 'Максимальный объем загружаемого файла, можно указать в байтах, кило-, мега- и гиго-байтах. Если указанный размер превышает допустимый в настройках PHP, то будет установлен указанный в настройках PHP.';
$lang['admin_max_photo'] = 'Максимальный размер изображений';
$lang['admin_max_photo_description'] = 'Максимальный размер изображения, до которого будет сжато отображаемое загруженное изображение на страницах сайта (в пикселях в виде ВЫСОТА х ШИРИНА)';
$lang['admin_temp_photo'] = 'Максимальный размер эскизов';
$lang['admin_temp_photo_description'] = 'Максимальный размер эскизов изображений, отображаемых в списках категорий, в блоках сайта (в пикселях в виде ВЫСОТА х ШИРИНА)';
$lang['admin_max_avatar'] = 'Максимальный размер аватар';
$lang['admin_max_avatar_description'] = 'Максимальный размер аватар, загружаеміх пользователями в свой профиль (в пикселях в виде ВЫСОТА х ШИРИНА)';
$lang['admin_copyright_settings'] = 'Настройки копирайта';
$lang['admin_copyright_year'] = 'Год копирайта';
$lang['admin_copyright_year_description'] = 'Указываем, какой год (года) отображать в копирайте';
$lang['admin_copyright_text'] = 'Текст копирайта';
$lang['admin_copyright_text_description'] = 'Какой текст указать названием ссылки';
$lang['admin_copyright_url'] = 'Ссылка копирайта';
$lang['admin_copyright_url_description'] = 'Какую ссылку использовать в копирайте';
$lang['admin_additional_settings'] = 'Дополнительные настройки';
$lang['admin_last_news'] = 'Последнии новости';
$lang['admin_last_news_description'] = 'Количество последних отображаемых новостей на главной странице сайта';
$lang['admin_best_user'] = 'Лучшие пользователи';
$lang['admin_best_user_description'] = 'Количество отображаемых лучших пользователей';
$lang['admin_max_rate'] = 'Максимальная оценка';
$lang['admin_max_rate_description'] = 'Максимально допустимая оценка при оценке изображения (от -значение до +значение)';
$lang['admin_save_settings'] = 'Сохранить настройки';
$lang['admin_search_user'] = 'Поиск пользователя';
$lang['admin_find_user'] = 'Найдены следующие пользователи';
$lang['admin_no_find_user'] = 'Такого пользователя нет';
$lang['admin_login'] = 'Имя пользователя';
$lang['admin_email'] = 'E-mail';
$lang['admin_real_name'] = 'Отображаемое имя';
$lang['admin_avatar'] = 'Аватар пользователя';
$lang['admin_user_rights'] = 'Права пользователя';
$lang['admin_pic_view'] = 'Просматривать изображения';
$lang['admin_pic_rate_user'] = 'Оценивать как пользователь';
$lang['admin_pic_rate_moder'] = 'Оценивать как преподаватель (модератор)';
$lang['admin_pic_upload'] = 'Загружать изображения';
$lang['admin_pic_moderate'] = 'Модерировать изображения';
$lang['admin_cat_moderate'] = 'Управлять разделами';
$lang['admin_cat_user'] = 'Использовать личный пользовательский альбом';
$lang['admin_comment_view'] = 'Просматривать комментарии';
$lang['admin_comment_add'] = 'Оставлять комментарии';
$lang['admin_comment_moderate'] = 'Модерировать комментарии';
$lang['admin_news_view'] = 'Просматривать новости';
$lang['admin_news_add'] = 'Добавлять новости';
$lang['admin_news_moderate'] = 'Модерировать новости';
$lang['admin_admin'] = 'Права Админа';
$lang['admin_help_edit_user'] = 'При смене группы пользователя права пользователя будут изменены на права группы';
$lang['admin_help_search_user'] = 'Для отображения всех пользователей используйте "*" как шаблон для поиска';
$lang['admin_save_user'] = 'Сохранить пользователя';
$lang['admin_select_group'] = 'Выберите изменяемую группу';
$lang['admin_edit_group'] = 'Редактировать группу';
$lang['admin_group_rights'] = 'Права группы';
$lang['admin_save_group'] = 'Сохранить группу';
/*@}*/
/*@}*/
?>
