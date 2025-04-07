<?php

/**
 * @file        language/english/main.php
 * @brief       Localized strings for the core of the site in English.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-04-07
 * @namespace   PhotoRigma\\Language
 *
 * @details     This file contains English language strings used in the core of the project.
 *              It includes texts for the interface, menus, forms, notifications, and other elements.
 *
 * @see         @ref LanguageEnglish Group for English localization.
 *
 * @note        This file is part of the PhotoRigma system and provides localization for the project in English.
 *
 * @copyright   Copyright (c) 2025 Dark Dayver. All rights reserved.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Permission is hereby granted, free of charge, to any person obtaining a copy
 *              of this software and associated documentation files (the "Software"), to deal
 *              in the Software without restriction, including without limitation the rights
 *              to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *              copies of the Software, and to permit persons to whom the Software is
 *              furnished to do so, subject to the following conditions:
 *              - The above copyright notice and this permission notice shall be included in all
 *                copies or substantial portions of the Software.
 */

namespace PhotoRigma\Language;

// Prevent direct file access
if (!defined('IN_GALLERY') || IN_GALLERY !== true) {
    error_log(
        date('H:i:s') . " [ERROR] | " . (filter_input(
            INPUT_SERVER,
            'REMOTE_ADDR',
            FILTER_VALIDATE_IP
        ) ?: 'UNKNOWN_IP') . " | " . __FILE__ . " | Попытка прямого вызова файла"
    );
    die("HACK!");
}

/**
 * @defgroup LanguageEnglish English Language Variables
 * @brief    Module for working with English language variables.
 */

/**
 * @var string $lang_name
 * @brief   Language name
 * @ingroup LanguageEnglish
 */
$lang_name = 'English';

/**
 * @var string $lang_id
 * @brief   Language identifier for HTML
 * @ingroup LanguageEnglish
 */
$lang_id = 'en';

/**
 * @addtogroup LanguageEnglish
 * @{
 */

/**
 * @var array $lang
 * @brief Array of language variables
 */
$lang = [];

/**
 * @defgroup LanguageEnglishMain Main Language Variables
 * @brief    Main text variables used on the site.
 */
$lang = [
    'main' => [
        'main'                 => 'Home',
        'search'               => 'Search',
        'rate'                 => 'Rating',
        'top_foto'             => 'Best Photo',
        'last_foto'            => 'Latest Photo',
        'rand_foto'            => 'Random Photo',
        'cat_foto'             => 'Photo Category',
        'no_foto'              => 'No photos!',
        'user_add'             => 'Added by',
        'data_add'             => 'Publication date (last edit)',
        'title_news'           => 'News',
        'no_news'              => 'No news',
        'no_user_add'          => 'Author does not exist',
        'user_block'           => 'User Panel',
        'login'                => 'Login',
        'pass'                 => 'Password',
        'enter'                => 'Sign In',
        'logout'               => 'Log Out',
        'forgot_password'      => 'Forgot your password?',
        'registration'         => 'Register',
        'redirect_title'       => 'Redirection',
        'redirect_description' => 'You will be automatically redirected to the site page',
        'redirect_url'         => 'Click here if your browser does not support redirection',
        'login_ok'             => ', login successful!',
        'login_error'          => 'Invalid username and/or password!',
        'hi_user'              => 'Hello',
        'group'                => 'Group',
        'stat_title'           => 'Statistics',
        'stat_regist'          => 'Total registered users on the site',
        'stat_photo'           => 'Total uploaded images',
        'stat_category'        => 'Total categories (including user-created ones)',
        'stat_user_admin'      => 'Total administrators',
        'stat_user_moder'      => 'Total moderators',
        'stat_rate_user'       => 'Ratings given by users',
        'stat_rate_moder'      => 'Ratings given by moderators',
        'stat_online'          => 'Registered users currently online',
        'stat_no_online'       => 'none.',
        'user_name'            => 'Username',
        'best_user_1'          => 'TOP-',
        'best_user_2'          => ' users',
        'best_user'            => 'TOP-%d users',
        'best_user_photo'      => 'Uploaded images',
        'name_of'              => 'Name',
        'description_of'       => 'Description',
        'no_category'          => 'No categories!',
        'edit_news'            => 'Edit news',
        'delete_news'          => 'Delete news',
        'confirm_delete_news'  => 'Are you sure you want to delete this news',
        'delete'               => 'Delete',
        'cancel'               => 'Cancel',
    ],
];

/**
 * @defgroup LanguageEnglishMenu Menu Item Variables
 * @brief    Text variables for site menu items.
 */
$lang += [
    'menu' => [
        'name_block'    => 'Navigation Panel',
        'home'          => 'Home',
        'regist'        => 'Registration',
        'category'      => 'Categories',
        'user_category' => 'User Albums',
        'you_category'  => 'Your Album',
        'upload'        => 'Upload Image',
        'add_category'  => 'Add Category',
        'search'        => 'Search',
        'news'          => 'News Archive',
        'news_add'      => 'Add News',
        'profile'       => 'Profile',
        'admin'         => 'Administration',
        'logout'        => 'Log Out',
    ],
];

/**
 * @defgroup LanguageEnglishCategory Category Variables
 * @brief    Text variables for category management and browsing.
 */
$lang += [
    'category' => [
        'category'            => 'Category',
        'name_block'          => 'Categories',
        'of_category'         => ' of the category',
        'count_photo'         => 'Total photos in the category',
        'count_user_category' => 'total',
        'no_user_category'    => 'no albums',
        'error_no_category'   => 'Category does not exist!',
        'error_no_photo'      => 'Category contains no photos!',
        'users_album'         => 'User Albums',
        'edit'                => 'Edit Category',
        'delete'              => 'Delete Category',
        'add'                 => 'Create Category',
        'cat_dir'             => 'Category Directory',
        'confirm_delete1'     => 'Are you sure you want to delete the category',
        'confirm_delete2'     => '? This operation is irreversible! All images in the category will be deleted!',
        'save'                => 'Save',
        'cancel'              => 'Cancel',
        'added'               => 'Create',
        'deleted_sucesful'    => 'successfully deleted!',
        'added_sucesful'      => 'successfully added!',
        'deleted_error'       => 'Unable to delete a non-existent category!',
        'added_error'         => 'Unable to create the category!',
        'no_name'             => 'Untitled',
        'no_description'      => 'No description',
    ],
];

/**
 * @defgroup LanguageEnglishLogin Login and Registration Variables
 * @brief    Text variables for registration, login, and password recovery.
 */
$lang += [
    'profile' => [
        'regist'                 => 'Registration',
        'login'                  => 'Username',
        'password'               => 'Password',
        're_password'            => 'Confirm Password',
        'email'                  => 'E-mail',
        'real_name'              => 'Display Name',
        'register'               => 'Register',
        'user'                   => 'User',
        'registered'             => 'successfully registered!',
        'error'                  => 'Error(s)',
        'error_login'            => 'Invalid username.',
        'error_password'         => 'Invalid password.',
        'error_re_password'      => 'Passwords do not match.',
        'error_email'            => 'Invalid e-mail.',
        'error_real_name'        => 'Invalid display name.',
        'error_login_exists'     => 'This username already exists.',
        'error_email_exists'     => 'This e-mail is already in use.',
        'error_real_name_exists' => 'This display name already exists.',
        'error_captcha'          => 'Incorrect CAPTCHA solution.',
        'profile'                => 'Profile',
        'edit_profile'           => 'Edit Profile',
        'confirm_password'       => 'Confirm changes with your password',
        'save_profile'           => 'Save Profile',
        'help_edit'              => 'Only if you plan to change this data',
        'avatar'                 => 'User Avatar',
        'delete_avatar'          => 'Delete Avatar',
        'captcha'                => 'Anti-bot protection. Solve the following example to register:',
    ],
];

/**
 * @defgroup LanguageEnglishNews News Variables
 * @brief    Text variables for site news.
 */
$lang += [
    'news' => [
        'news'      => 'News Archive',
        'title'     => 'News',
        'name_post' => 'Title',
        'text_post' => 'Content',
        'edit_post' => 'Save Changes',
        'add_post'  => 'Add News',
        'del_post'  => 'successfully deleted!',
        'num_news'  => 'Total News',
        'on_years'  => 'by years',
        'on'        => 'for',
        'year'      => 'year',
        'years'     => 'years',
        'on_month'  => 'by months',
        '01'        => 'January',
        '02'        => 'February',
        '03'        => 'March',
        '04'        => 'April',
        '05'        => 'May',
        '06'        => 'June',
        '07'        => 'July',
        '08'        => 'August',
        '09'        => 'September',
        '10'        => 'October',
        '11'        => 'November',
        '12'        => 'December',
    ],
];

/**
 * @defgroup LanguageEnglishPhoto Photo Variables
 * @brief    Text variables for image display, processing, and rating.
 */
$lang += [
    'photo' => [
        'title'           => 'Image',
        'of_photo'        => ' of the image',
        'rate_user'       => 'User Rating',
        'rate_moder'      => 'Moderator Rating',
        'rate_you'        => 'Your Rating',
        'if_user'         => 'as a user',
        'if_moder'        => 'as a moderator',
        'rate'            => 'Rate',
        'edit'            => 'Edit Image',
        'delete'          => 'Delete Image',
        'confirm_delete'  => 'Are you sure you want to delete the image',
        'filename'        => 'Change',
        'save'            => 'Save',
        'cancel'          => 'Cancel',
        'select_file'     => 'Select File',
        'upload'          => 'Upload',
        'no_name'         => 'Untitled',
        'no_description'  => 'No Description',
        'error_upload'    => 'Unable to upload the image!',
        'error_delete'    => 'Unable to delete!',
        'complite_upload' => 'successfully uploaded!',
        'complite_delete' => 'successfully deleted!',
    ],
];

/**
 * @defgroup LanguageEnglishSearch Search Variables
 * @brief    Text variables for the search page.
 */
$lang += [
    'search' => [
        'title'         => 'Enter a search string and select a range',
        'need_user'     => 'users',
        'need_category' => 'categories',
        'need_news'     => 'news',
        'need_photo'    => 'images',
        'find'          => 'Found',
        'no_find'       => 'Nothing found',
    ],
];

/**
 * @defgroup LanguageEnglishAdmin Admin Variables
 * @brief    Text variables for the admin panel.
 */
$lang += [
    'admin' => [
        'title'                         => 'Administration',
        'admin_pass'                    => 'Enter the password to access the Admin Panel',
        'select_subact'                 => 'Select an Admin Panel section',
        'settings'                      => 'General Settings',
        'admin_user'                    => 'User Management',
        'admin_group'                   => 'Group Management',
        'main_settings'                 => 'Main Parameters',
        'title_name'                    => 'Site Title',
        'title_name_description'        => 'The site title is used in the page headers of the site',
        'title_description'             => 'Site Description',
        'title_description_description' => 'The site description is used as a replacement for the site logo if users cannot upload images',
        'meta_description'              => 'Meta Description Tag',
        'meta_description_description'  => 'The meta description tag is used by search engines to display the indexed site in databases - it should briefly describe the purpose of the site',
        'meta_keywords'                 => 'Meta Keywords Tag',
        'meta_keywords_description'     => 'The meta keywords tag is used by search engines to index the site in databases - specify keywords separated by spaces that reflect the essence of the site',
        'appearance_settings'           => 'Site Appearance',
        'gal_width'                     => 'Gallery Width',
        'gal_width_description'         => 'The gallery width allows you to adjust the displayed width of the entire gallery; it can be specified in pixels or percentages',
        'left_panel'                    => 'Left Column Width',
        'left_panel_description'        => 'The width of the left column of the site; it can be specified in pixels or percentages',
        'right_panel'                   => 'Right Column Width',
        'right_panel_description'       => 'The width of the right column of the site; it can be specified in pixels or percentages',
        'language'                      => 'Site Language',
        'language_description'          => 'Select the site language from the available options',
        'themes'                        => 'Site Template',
        'themes_description'            => 'Select the site template from the available options',
        'size_settings'                 => 'Used Sizes',
        'max_file_size'                 => 'Maximum Upload File Size',
        'max_file_size_description'     => 'The maximum size of an uploaded file can be specified in bytes, kilobytes, megabytes, or gigabytes. If the specified size exceeds the limit in PHP settings, the value from PHP settings will be used.',
        'max_photo'                     => 'Maximum Image Size',
        'max_photo_description'         => 'The maximum size of an image to which the uploaded image will be compressed for display on site pages (in pixels as HEIGHT x WIDTH)',
        'temp_photo'                    => 'Maximum Thumbnail Size',
        'temp_photo_description'        => 'The maximum size of image thumbnails displayed in category lists and site blocks (in pixels as HEIGHT x WIDTH)',
        'max_avatar'                    => 'Maximum Avatar Size',
        'max_avatar_description'        => 'The maximum size of avatars uploaded by users to their profiles (in pixels as HEIGHT x WIDTH)',
        'copyright_settings'            => 'Copyright Settings',
        'copyright_year'                => 'Copyright Year',
        'copyright_year_description'    => 'Specify the year(s) to display in the copyright',
        'copyright_text'                => 'Copyright Text',
        'copyright_text_description'    => 'The text to use as the link name',
        'copyright_url'                 => 'Copyright Link',
        'copyright_url_description'     => 'The URL to use in the copyright',
        'additional_settings'           => 'Additional Settings',
        'last_news'                     => 'Latest News',
        'last_news_description'         => 'The number of latest news items to display on the main page of the site',
        'best_user'                     => 'Top Users',
        'best_user_description'         => 'The number of top users to display',
        'max_rate'                      => 'Maximum Rating',
        'max_rate_description'          => 'The maximum allowed rating when rating an image (from -value to +value)',
        'time_online'                   => 'User Online Time',
        'time_online_description'       => 'Time since the user\'s last activity, during which they are considered online (in seconds).',
        'save_settings'                 => 'Save Settings',
        'search_user'                   => 'Search for a User',
        'find_user'                     => 'The following users were found',
        'no_find_user'                  => 'No such user exists',
        'login'                         => 'Username',
        'email'                         => 'E-mail',
        'real_name'                     => 'Display Name',
        'avatar'                        => 'User Avatar',
        'user_rights'                   => 'User Rights',
        'pic_view'                      => 'View Images',
        'pic_rate_user'                 => 'Rate as a User',
        'pic_rate_moder'                => 'Rate as a Moderator (Teacher)',
        'pic_upload'                    => 'Upload Images',
        'pic_moderate'                  => 'Moderate Images',
        'cat_moderate'                  => 'Manage Categories',
        'cat_user'                      => 'Use Personal User Album',
        'comment_view'                  => 'View Comments',
        'comment_add'                   => 'Add Comments',
        'comment_moderate'              => 'Moderate Comments',
        'news_view'                     => 'View News',
        'news_add'                      => 'Add News',
        'news_moderate'                 => 'Moderate News',
        'admin'                         => 'Admin Rights',
        'help_edit_user'                => 'When changing a user\'s group, the user\'s rights will be updated to match the group\'s rights',
        'help_search_user'              => 'To display all users, use `*` as a search pattern',
        'save_user'                     => 'Save User',
        'select_group'                  => 'Select the group to modify',
        'edit_group'                    => 'Edit Group',
        'group_rights'                  => 'Group Rights',
        'save_group'                    => 'Save Group',
        'lockout_time'                  => 'Too many failed login attempts. Minutes remaining until the next attempt: %d.',
    ],
];

/**
 * @}
 */

return [
    'lang_name' => $lang_name,
    'lang_id'   => $lang_id,
    'lang'      => $lang,
];
