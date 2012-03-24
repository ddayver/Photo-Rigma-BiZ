<?php
/*****************************************************************************
**	File:	action/news.php													**
**	Diplom:	Gallery															**
**	Date:	13/01-2009														**
**	Ver.:	0.1																**
**	Autor:	Gold Rigma														**
**	E-mail:	nvn62@mail.ru													**
**	Decr.:	Вывод и обработка новостей сайта								**
*****************************************************************************/

// Проверка, что файл подключается из индексного, а не набран напрямую в адресной строке
if (IN_DIPLOM)
{
	die('HACK!');
}

include_once($config['site_dir'] . 'language/' . $config['language'] . '/main.php'); // подключаем языковый файл основной страницы
include_once($config['site_dir'] . 'language/' . $config['language'] . '/menu.php'); // подключаем языковый файл меню
include_once($config['site_dir'] . 'language/' . $config['language'] . '/news.php'); // подключаем языковый файл новостей

if(!isset($_REQUEST['news']) || empty($_REQUEST['news']) || !(mb_ereg('^[0-9]+$', $_REQUEST['news']))) // если не указан идентификатор новости или идентификатор не является числом, то...
{
	$news = false; // идентификатор равен false (ЛОЖЬ)
}
else
{
	$news = $_REQUEST['news']; // иначе сохраняем идентификатор
	$temp = $db->fetch_array("SELECT * FROM `news` WHERE `id` = " . $news); // и проверяем - есть ли новость с данным идентификатором
	if (!$temp) $news = false; // если нету, то идентификатор равен false (ЛОЖЬ)
}

if(isset($_REQUEST['subact']) && !empty($_REQUEST['subact'])) // если указана дополнительная команда, то...
{
	$subact = $_REQUEST['subact']; // сохраняем данную команду
}

if ($subact == 'save') // если поступила команда н сохранение (добавленной новости или редактируемой)
{
	if($news === false && $user->user['news_add'] == true) // если не указан идентификатор новости и пользователь имеет право добавления новостей на сайт, то производим добавление новости в базу
	{
		if(!isset($_POST['name_post']) || empty($_POST['name_post']) || !isset($_POST['text_post']) || empty($_POST['text_post'])) // если не заполнены поля названия и текста новости, то...
		{
			$subact = 'add'; // указать дополнительной командой - добавление новости
		}
		else // иначе
		{
			$name_post = $_POST['name_post']; // сохраняем название новости
			$text_post = trim($_POST['text_post']); // сохраняем текст новости с удаленными в начале и конце пробелами
			$news = $db->insert_id("INSERT IGNORE INTO `news` (`data_post`, `data_last_edit`, `user_post`, `name_post`, `text_post`) VALUES (CURDATE(), CURRENT_TIMESTAMP, '" . $user->user['id'] . "', '" . $name_post . "', '" . $text_post . "')"); // вносим в базу новость и получаем в ответ идентификатор новости
		}
	}
	elseif ($news !== false && $user->user['news_moderate'] == true) // иначе если есть идентификатор новости и пользователь имеет право редактирования новостей, то проходим процедуру изменения новости
	{
		if(!isset($_POST['name_post']) || empty($_POST['name_post'])) // если не указано название новости, то...
		{
			$name_post = $temp['name_post']; // название новости остается старым
			$ch_name = false; // указываем, что не производилось изменение названия
		}
		else // иначе
		{
			$name_post = $_POST['name_post']; // сохраняем новое название новости
			$ch_name = true; // отмечаем, что произошло изменение новости
		}

		if(!isset($_POST['text_post']) || empty($_POST['text_post'])) // если не указан текст новости, то...
		{
			$text_post = trim($temp['text_post']); // текст новости остается старым
			$ch_text = false; // указываем, что изменений текста не производилось
		}
		else // иначе
		{
			$text_post = trim($_POST['text_post']); // сохраняем новый текст новости
			$ch_text = true; // и указываем, что он изменялся
		}

		if($ch_name || $ch_text) // если изменялись название или текст новости, то...
		{
			$db->query("UPDATE `news` SET `data_last_edit` = CURRENT_TIMESTAMP, `name_post` = '" . $name_post . "', `text_post` = '" . $text_post . "' WHERE `id` = " . $news); // вносим в базу изменения в новость
		}
	}
	else // во всех остальных случаях
	{
		$news = false; // идентификатор новости равен false (ЛОЖЬ)
	}
}

if ($subact == 'edit' && $news !== false && ($user->user['news_moderate'] == true || ($user->user['id'] != 0 && $user->user['id'] == $temp['user_post']))) // если поступила команда на редактирование новости и пользователь имеет право модерировать новости или является автором новости при идентификаторе пользователя не равном 0 (Гость), то...
{
	$title = $lang['main_edit_news']; // устанавливаем доп-название страницы - Редактирование новостей

	$temp = $db->fetch_array("SELECT * FROM `news` WHERE `id` = " . $news); // запрашиваем данные о новости

	$user_add = $db->fetch_array("SELECT `real_name` FROM `user` WHERE `id` = " . $temp['user_post']); // запрашиваем данные об отображаемом имени пользователя, добавившего новость
	if ($user_add) // если пользователь существует, то...
	{
		$name_user = '<a href="' . $config['site_url']  . '?action=login&subact=profile&uid=' . $temp_news['user_post'] . '" title="' . $user_add['real_name'] . '">' . $user_add['real_name'] . '</a>'; // сохраняем его отобржаемое имя и делаем его ссылкой на профиль пользователя
	}
	else // иначе
	{
		$name_user = $lang['main_no_user_add']; // указываем, что пользователя не существует
	}

	$array_data = array(); // инициируем массив

	$array_data = array(
				'NAME_BLOCK' => $lang['main_edit_news'] . ' - ' . $temp['name_post'],
				'L_NAME_USER' => $lang['main_user_add'],
				'L_NAME_POST' => $lang['news_name_post'],
				'L_TEXT_POST' => $lang['news_text_post'],
				'L_SAVE_NEWS' => $lang['news_edit_post'],

				'D_NAME_USER' => $name_user,
				'D_NAME_POST' => $temp['name_post'],
				'D_TEXT_POST' => $temp['text_post'],

				'IF_NEED_USER' => true,

				'U_SAVE_NEWS' => $config['site_url'] . '?action=news&subact=save&news=' . $news
	); // наполняем массив данными для замены по шаблону

	$main_block = $template->create_template('news_save.tpl', $array_data); // создаем центральный блок - заполненный шаблон для редактирования новости
}
elseif ($subact == 'delete' && $news !== false && ($user->user['news_moderate'] == true || ($user->user['id'] != 0 && $user->user['id'] == $temp['user_post']))) // если поступила команда на удаление новости и пользователь имеет право модерировать новости или является автором новости при идентификаторе пользователя не равном 0 (Гость), то...
{
	if (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'action=news') !== false) // проверяем реферальную ссылку - если пользователь удаляет новость из Архива новостей, то...
	{
		$redirect_url = $config['site_url'] . '?action=news'; // после удаления переместим пользователя обратно в архив
	}
	else // иначе
	{
		$redirect_url = $config['site_url']; // переместим пользователя на главную страницу
	}
	$redirect_time = 5; // устанавливаем время редиректа 5 сек
	$redirect_message = $lang['news_title'] . ' ' . $temp['name_post'] . ' ' . $lang['news_del_post']; // сообщаем пользователю об удачном удалении новости
	$db->query("DELETE FROM `fotorigma`.`news` WHERE `id` = " . $news); // удаляем новость из базы данных
}
elseif ($subact == 'add' && $news === false && $user->user['news_add'] == true) // если поступила команда на добавление новости и пользователь имеет право добавлять новости, то...
{
	$title = $lang['news_add_post']; // указываем дополнительным названием страницы - Добавление новости
	$act = 'news_add'; // активный пункт меню - news_add

	$array_data = array(); // инициируем массив

	$array_data = array(
				'NAME_BLOCK' => $lang['news_add_post'],
				'L_NAME_USER' => '',
				'L_NAME_POST' => $lang['news_name_post'],
				'L_TEXT_POST' => $lang['news_text_post'],
				'L_SAVE_NEWS' => $lang['news_edit_post'],

				'D_NAME_USER' => '',
				'D_NAME_POST' => '',
				'D_TEXT_POST' => '',

				'IF_NEED_USER' => false,

				'U_SAVE_NEWS' => $config['site_url'] . '?action=news&subact=save'
	); // наполняем массив данными для замены по шаблону

	$main_block = $template->create_template('news_save.tpl', $array_data); // формируем центральный блок - форму добавления новостей
}
else // иначе если не указаны доп-команды, то выводим или отдельно текст новости или архив по годам и месяцам
{
	if($news !== false) // если указан идентификатор новости, то...
	{
		$main_block = $template->template_news($news, 'id'); // выведем соотвествующую новость в центральный блок
		$act = ''; // активного пункта меню нет
		$title = $lang['news_title']; // дополнительным названием страницы будет Архив новостей
	}
	else // иначе, если не указан идентификатор, то...
	{
		if (!isset($_REQUEST['y']) || empty($_REQUEST['y']) || !mb_ereg('^[0-9]{4}$', $_REQUEST['y'])) // если не поступало запроса на отображение поределенного года из архива или год указан в неверном формате (YYYY), то...
		{
			$act = 'news'; // активный пункт меню - news
			$temp = $db->fetch_big_array("SELECT DISTINCT DATE_FORMAT(`data_last_edit`, '%Y') AS 'year' FROM `news` ORDER BY `data_last_edit` ASC"); // запрос из базы всех лет, в которые производились изменения новостей
			if(!$temp) // если нет таких данных, то...
			{
				$main_block = $template->template_news($config['last_news']); // вывести последнии новости (фактически фнукция класса выведет сообщение, что на сайте нет новостей)
			}
			else // иначе если такие данные есть, то формируем вывод пунктов по годам
			{
				$spisok = '<br />'; // инициируем переменную списка лет
				for($i = 1; $i <= $temp[0]; $i++) // обрабатываем в цикле имеющиемя данные
				{
					$temp2 = $db->num_rows("SELECT * FROM `news` WHERE DATE_FORMAT(`data_last_edit`, '%Y') = '" . $temp[$i]['year'] . "'"); // запрашиваем данные о том, сколько новостей существует в проверяемый год
					$spisok .= '&bull;&nbsp;<a href="' . $config['site_url'] . '?action=news&y=' . $temp[$i]['year'] . '" title="' . $temp[$i]['year'] . ' (' . $lang['news_num_news'] . ': ' . $temp2 . ')">' . $temp[$i]['year'] . ' (' . $temp2 . ')</a><br /><br />'; // формируем ссылку вида: YYYY (кол-во новостей) и добавляем её в список
				}

				$array_data = array(); // инициируем массив

				$array_data = array(
								'NAME_BLOCK' => $lang['news_news'],
								'L_NEWS_DATA' => $lang['news_news'] . ' ' . $lang['news_on_years'],
								'L_TEXT_POST' => $spisok,

								'IF_EDIT_SHORT' => false,
								'IF_EDIT_LONG' => false
				); // наполняем массив данными для замены по шаблону

				$main_block = $template->create_template('news.tpl', $array_data); // формируем центральный блок - список лет, в которые есть новости
				$title = $lang['news_news'] . ' ' . $lang['news_on_years']; // формируем дополнительный заголовок страницы вида Архив новостей по годам
			}
		}
		else // иначе если указан правильный запрос года, то...
		{
			$year = $_REQUEST['y']; // сохраняем запрошенный год
			if (!isset($_REQUEST['m']) || empty($_REQUEST['m']) || !mb_ereg('^[0-9]{2}$', $_REQUEST['m'])) // если не указан месяц, по которому надо вывести список новостей или формат месяца не соответствует формату (MM), то формируем список месяцев, в которые есть редактируемые новости соглсно запрошенного года
			{
				$act = ''; // активного пункта меняю - нет
				$temp = $db->fetch_big_array("SELECT DISTINCT DATE_FORMAT(`data_last_edit`, '%m') AS 'month' FROM `news` WHERE DATE_FORMAT(`data_last_edit`, '%Y') = '" . $year . "' ORDER BY `data_last_edit` ASC"); // запрашиваем список месяцев, когда были редактированы новости в указанном году
				if(!$temp) // если нет таких данных, то...
				{
					$main_block = $template->template_news($config['last_news']); // выводим список последних новостей
				}
				else // иначе...
				{
					$spisok = '<br />'; // инициируем список новостей
					for($i = 1; $i <= $temp[0]; $i++) // обрабатываем по циклу все месяца, полученный в запросе
					{
						$temp2 = $db->num_rows("SELECT * FROM `news` WHERE DATE_FORMAT(`data_last_edit`, '%Y') = '" . $year . "' AND DATE_FORMAT(`data_last_edit`, '%m') = '" . $temp[$i]['month'] . "'"); // запрашиываем количество новостей в указанном месяце указанного года
						$spisok .= '&bull;&nbsp;<a href="' . $config['site_url'] . '?action=news&y=' . $year . '&m=' . $temp[$i]['month'] . '" title="' . $lang['news'][$temp[$i]['month']] . ' (' . $lang['news_num_news'] . ': ' . $temp2 . ')">' . $lang['news'][$temp[$i]['month']] . ' (' . $temp2 . ')</a><br />'; // формируем ссылку вида Месяц (кол-во новостей)
					}

					$array_data = array(); // инициируем массив

					$array_data = array(
									'NAME_BLOCK' => $lang['news_news'],
									'L_NEWS_DATA' => $lang['news_news'] . ' ' . $lang['news_on'] . ' ' . $year . ' ' . $lang['news_on_month'],
									'L_TEXT_POST' => $spisok,

									'IF_EDIT_SHORT' => false,
									'IF_EDIT_LONG' => false
					); // наполняем массив данными для замены по шаблону

					$main_block = $template->create_template('news.tpl', $array_data); // формируем центральный блок - список новостей по месяцам
					$title = $lang['news_news'] . ' ' . $lang['news_on'] . ' ' . $year. ' ' . $lang['news_on_month']; // дополнительным заголовком страницы будет текст "Архив новостей за YYYY год по месяцам"
				}
			}
			else // иначе если был правильно указан месяц, то формируем список названий новостей в данном месяце данного года
			{
				$month = $_REQUEST['m']; // сохраняем месяц
				$act = ''; // активного пукта меню - нет

				$temp = $db->fetch_big_array("SELECT * FROM `news` WHERE DATE_FORMAT(`data_last_edit`, '%Y') = '" . $year . "' AND DATE_FORMAT(`data_last_edit`, '%m') = '" . $month . "' ORDER BY `data_last_edit` ASC"); // запрашиваем список новостей, которые были в указанном месяце указанного года
				if(!$temp) // если нет таких данных, то...
				{
					$main_block = $template->template_news($config['last_news']); // выводим список последних новостей
				}
				else // иначе формируем список названий новостей
				{
					$spisok = '<br />'; // инициируем переменную списка
					for($i = 1; $i <= $temp[0]; $i++) // обрабатываем полученные новости в цикле
					{
						$spisok .= '&bull;&nbsp;<a href="' . $config['site_url'] . '?action=news&news=' . $temp[$i]['id'] . '" title="' . substr($temp[$i]['text_post'], 0, 100) . '">' . $temp[$i]['name_post'] . '</a><br />'; // формируем ссылку типа "Название новости" с всплывающей подсказкой, содержащей 100 первых символов текста новости
					}

					$array_data = array(); // инициируем массив

					$array_data = array(
									'NAME_BLOCK' => $lang['news_news'],
									'L_NEWS_DATA' => $lang['news_news'] . ' ' . $lang['news_on'] . ' ' . $lang['news'][$month] . ' ' . $year . ' ' . $lang['news_years'],
									'L_TEXT_POST' => $spisok,

									'IF_EDIT_SHORT' => false,
									'IF_EDIT_LONG' => false
					); // наполняем массив данными для замены по шаблону

					$main_block = $template->create_template('news.tpl', $array_data); // формируем центральный блок - список новостей за указанный год и месяц
					$title = $lang['news_news'] . ' ' . $lang['news_on'] . ' ' . $lang['news'][$month] . ' ' . $year . ' ' . $lang['news_years']; // дополнительным заголовком страницы будет текст вида "Архив новостей за Месяц YYYY года"
				}
			}
		}
	}
}

$redirect = array(); // инициируем пустой массив для редиректа

if (!empty($redirect_time)) // если есть данные о времени редиректа, то...
{
	$array_data = array(); // инициируем массив

	$array_data = array(
				'L_REDIRECT_DESCRIPTION' => $lang['main_redirect_description'],
				'L_REDIRECT_URL' => $lang['main_redirect_url'],

				'L_REDIRECT_MASSAGE' => $redirect_message,
				'U_REDIRECT_URL' => $redirect_url
	); // наполняем массив данными для замены по шаблону

	$redirect = array(
				'U_REDIRECT_URL' => $redirect_url,
				'REDIRECT_TIME' => $redirect_time,
				'IF_NEED_REDIRECT' => true
	); // наполняем массив данными для редиректа
	$title = $lang['main_redirect_title']; // дополнительным заголовком будет сообщение о переадресации
	$main_block = $template->create_template('redirect.tpl', $array_data); // формируем центральный блок для редиректа
}
echo $template->create_main_template($act, $title, $main_block, $redirect); // выводим сформированную страницу
?>