<?php
/**
 * @file        include/db.php
 * @brief       Работа с MySQL
 * @author      Dark Dayver
 * @version     0.2.0
 * @date        28/03-2012
 * @details     Содержит класс по работе с БД MySQL.
 */
/// @cond
if (IN_GALLERY !== TRUE)
{
	die('HACK!');
}
/// @endcond

/// Класс по работе с БД MySQL.
/**
 * Данный класс содержит набор функций по работе с БД MySQL: установка соединения, формирование и отправка запросов, обработка результатов.
 */
class db
{
	private $link; ///< Ссылка на подключенную базу данных
	var $txt_query; ///< Сформированный запрос
	var $res_query; ///< Ссылка на полученный результат
	var $result = FALSE; ///< Результат выполнения запроса
	var $aff_rows = FALSE; ///< Количество вставленных строк
	var $insert_id = FALSE; ///< ID последней вставленной через INSERT строки
	var $error; ///< Номер и текст ошибки

	/// Конструктор класса, выполняет ряд ключевых задач.
	/**
	 * -# Создает ссылку на соединение с указанной базой данных;
	 * -# Передает базе запросы для принудительной установки соединения с таблицой символов UTF-8.
	 * .
	 * @param $dbhost содержит хост, где расположен сервер БД
	 * @param $dbuser содержит имя пользователя для работы с БД
	 * @param $dbpass содержит пароль пользователя для работы с БД
	 * @param $dbname содержит название БД
	 * @see ::$config
	 */
	function db($dbhost, $dbuser, $dbpass, $dbname)
	{
		$this->link = @mysql_connect($dbhost, $dbuser, $dbpass) or die ('Error connect DB: ' . mysql_errno($this->link) . ' - ' . mysql_error($this->link));
		@mysql_select_db($dbname, $this->link) or die ('Error select DB: ' . mysql_errno($this->link) . ' - ' . mysql_error($this->link));
		@mysql_query("SET CHARACTER SET utf8", $this->link);
		@mysql_query("SET NAMES 'utf8'", $this->link);
	}

	/// Функция обработки запроса с получением предварительных результатов
	/**
	 * -# Обнуляет $aff_rows, $insert_id;
	 * -# Выполняет запрос из $txt_query и размещает ссылку на результат выполнения в $res_query;
	 * -# Если запрос выполнился без ошибок, то заполняет $aff_rows, $insert_id соответствующими значениями.
	 * @return True, если запрос успешно выполнен, иначе False.
	 * @see $txt_query, $res_query, $aff_rows, $insert_id
	 */
	private function query()
	{
		$this->aff_rows = FALSE;
		$this->insert_id = FALSE;
		$this->res_query = @mysql_query($this->txt_query, $this->link);
		if ($this->res_query === FALSE)
		{
			$this->error = 'Error DB: ' . mysql_errno($this->link) . ' - ' . mysql_error($this->link) . ' - SQL: ' . $this->txt_query;
			return FALSE;
		}
		else
		{
			$this->aff_rows = @mysql_affected_rows($this->link);
			$this->insert_id = @mysql_insert_id($this->link);
			return TRUE;
		}
	}

	/// Функция формирования SELECT-запроса
	/**
	 * Формирует SELECT-запрос, основываясь на полученных аргументах, размещает его в $txt_query и выполняет
	 * @param $select   может содержать как одиночное название поля, так и массив с перечислением полей, которые необходимо получить в результате запроса
	 * @param $from_tbl указывает, с какой таблицой необходимо выполнять работу
	 * @param $where    содержит условия выбора, иначе False
	 * @param $order    содержит условия сортировки, иначе False
	 * @param $group    содержит условия группировки, иначе False
	 * @param $limit    содержит условия лимита выводимых строк, иначе False
	 * @see $txt_query, where, order, group, limit, query, clean
	 */
	function select($select, $from_tbl, $where = FALSE, $order = FALSE, $group = FALSE, $limit = FALSE)
	{
		if (strlen($this->txt_query) > 0) $this->clean();
		$this->txt_query = 'SELECT ';
		if (!is_array($select))
		{
			$select = array($select);
		}
		$selects = '';
		foreach ($select as $tmp)
		{
			if ($tmp == '*' || $tmp[0] == '`' || $tmp[strlen($tmp) - 1] == '`')
			{
				$selects .= ', ' . $tmp;
			}
			else
			{
				$selects .= ', `' . $tmp . '`';
			}
		}
		$selects = substr($selects, 2);
		$this->txt_query .= $selects . ' FROM `' . $from_tbl . '`';
		if ($where != FALSE) $this->where($where);
		if ($order != FALSE) $this->order($order);
		if ($group != FALSE) $this->group($group);
		if ($limit != FALSE) $this->limit($limit);
		return $this->query();
	}

	/// Функция формирования запроса на удаление
	/**
	 * Формирует запрос на удаление, основываясь на полученных аргументах, размещает его в $txt_query и выполняет
	 * @param $from_tbl указывает, с какой таблицой необходимо выполнять работу
	 * @param $where    содержит условия выбора, иначе False
	 * @param $order    содержит условия сортировки, иначе False
	 * @param $limit    содержит условия лимита выводимых строк, иначе False
	 * @see $txt_query, where, order, limit, query, clean
	 */
	function delete($from_tbl, $where = FALSE, $order = FALSE, $limit = FALSE)
	{
		if (strlen($this->txt_query) > 0) $this->clean();
		$this->txt_query = 'DELETE FROM `' . $from_tbl . '`';
		if ($where != FALSE) $this->where($where);
		if ($order != FALSE) $this->order($order);
		if ($limit != FALSE) $this->limit($limit);
		return $this->query();
	}

	/// Функция формирования запроса на очистку таблицы
	/**
	 * Формирует запрос на очистку таблицы, основываясь на полученных аргументах, размещает его в $txt_query и выполняет
	 * @param $tbl указывает, какую таблицу необходимо очистить
	 * @see $txt_query, query, clean
	 */
	function truncate($tbl)
	{
		if (strlen($this->txt_query) > 0) $this->clean();
		$this->txt_query = 'TRUNCATE TABLE `' . $tbl . '`';
		return $this->query();
	}

	/// Функция формирования простого запроса на обновление
	/**
	 * Формирует простой запрос на обновление, основываясь на полученных аргументах, размещает его в $txt_query и выполняет
	 * @param $update содержит массив данных, которые необходимо внести в БД в формате: 'имя_поля' => 'значение'
	 * @param $to_tbl указывает, с какой таблицой необходимо выполнять работу
	 * @param $where  содержит условия выбора, иначе False
	 * @param $order  содержит условия сортировки, иначе False
	 * @param $limit  содержит условия лимита выводимых строк, иначе False
	 * @see $txt_query, where, order, limit, query, clean
	 */
	function update($update, $to_tbl, $where = FALSE, $order = FALSE, $limit = FALSE)
	{
		if (strlen($this->txt_query) > 0) $this->clean();
		$this->txt_query = 'UPDATE `' . $to_tbl . '` SET ';
		$updates = '';
		foreach ($update as $key => $value)
		{
			if ($value === NULL)
			{
				$updates .= ', `' . $key . '` = NULL';
			}
			else
			{
				$updates .= ', `' . $key . "` = '" . $value . "'";
			}
		}
		$this->txt_query .= substr($updates, 2);
		if ($where != FALSE) $this->where($where);
		if ($order != FALSE) $this->order($order);
		if ($limit != FALSE) $this->limit($limit);
		return $this->query();
	}

	/// Функция формирования запроса на вставку строк
	/**
	 * Формирует запрос на вставку строк, основываясь на полученных аргументах, размещает его в $txt_query и выполняет
	 * @param $insert содержит массив данных, которые необходимо внести в БД в формате: 'имя_поля' => 'значение'
	 * @param $to_tbl указывает, с какой таблицой необходимо выполнять работу
	 * @param $type   указывает тип запроса: ignore - формирует запрос типа "INSERT IGNORE INTO"; replace - формирует запрос типа "REPLACE INTO", при любом другом значении формирует запрос типа "INSERT INTO" (по-умолчанию значение равно False)
	 * @see $txt_query, clean
	 */
	function insert($insert, $to_tbl, $type = FALSE)
	{
		if (strlen($this->txt_query) > 0) $this->clean();
		if ($type == 'ignore')
		{
			$this->txt_query = 'INSERT IGNORE INTO ';
		}
		else if ($type == 'replace')
		{
			$this->txt_query = 'REPLACE INTO ';
		}
		else
		{
			$this->txt_query = 'INSERT INTO ';
		}
		$keys = '';
		$values = '';
		foreach ($insert as $key => $value)
		{
			$keys .= ', `' . $key . '`';
			$values .= ", '" . $value . "'";
		}
		$this->txt_query .= '`' . $to_tbl . '` (' . substr($keys, 2) . ') VALUES (' . substr($values, 2) . ')';
		return $this->query();
	}

	/// Функция дополнения запроса условием
	/**
	 * Дополняет запрос, размещенный в $txt_query условием типа WHERE
	 * @param $where содержит строку для вставки в условие после WHERE
	 * @see $txt_query, select, delete, update
	 */
	private function where($where)
	{
		if (strlen($this->txt_query) > 0)
		{
			$this->txt_query .= ' WHERE ' . $where;
		}
	}

	/// Функция дополнения запроса сортировкой
	/**
	 * Дополняет запрос, размещенный в $txt_query сортировкой типа ORDER BY
	 * @param $order содержит имя поля, по которому следует сортировать результаты
	 * @param $sort  содержит указание на порядок сортировки: down - сортировка по убыванию, up - сортировка по возрастанию; любое другое значение предоставит БД самой выбирать порядок сортировки (по-умолчанию равно False)
	 * @see $txt_query, select, delete, update
	 */
	private function order($order, $sort = FALSE)
	{
		if (is_array($order)) list($order, $sort) = each($order);
		if (strlen($this->txt_query) > 0)
		{
			if ($order !== 'rand()')
			{
				$this->txt_query .= ' ORDER BY `' . $order . '`';
				if ($sort == 'down') $this->txt_query .= ' DESC';
				else if ($sort == 'up') $this->txt_query .= ' ASC';
			}
			else $this->txt_query .= ' ORDER BY rand()';
		}
	}

	/// Функция дополнения запроса группировкой
	/**
	 * Дополняет запрос, размещенный в $txt_query группировкой типа GROUP BY
	 * @param $group содержит имя поля, по которому следует группировать результаты
	 * @param $sort  содержит указание на порядок сортировки записей во время группировки: down - сортировка по убыванию, up - сортировка по возрастанию; любое другое значение предоставит БД самой выбирать порядок сортировки (по-умолчанию равно False)
	 * @see $txt_query, select
	 */
	private function group($group, $sort = FALSE)
	{
		if (is_array($group)) list($group, $sort) = each($group);
		if (strlen($this->txt_query) > 0)
		{
			$this->txt_query .= ' GROUP BY `' . $group . '`';
			if ($sort == 'down')
			{
				$this->txt_query .= ' DESC';
			}
			else if ($sort == 'up')
			{
				$this->txt_query .= ' ASC';
			}
		}
	}

	/// Функция дополнения запроса ограничением на число результатов
	/**
	 * Дополняет запрос, размещенный в $txt_query ограничением вывода числа результатов
	 * @param $limit указывает, сколько необходимо получить результатов
	 * @param $start указывает, начиная с какой записи выводит запрошенное количество результатов (по-умолчанию 0 - с первой)
	 * @see $txt_query, select, delete, update
	 */
	private function limit($limit, $start = 0)
	{
		if (is_array($limit)) list($limit, $start) = each($limit);
		if (strlen($this->txt_query) > 0 && $limit > 0)
		{
			$this->txt_query .= ' LIMIT ' . $start . ', ' . $limit;
		}
	}

	/// Функция получения строки результатов
	/**
	 * Обрабатывает ссылку на результаты, полученную через query ($res_query), помещая полученный результат в $result
	 * @return Строку результатов по ранее сформированному запросу
	 * @see    $res_query, $result
	 */
	function res_row()
	{
		if ($this->res_query)
		{
			$this->result = @mysql_fetch_assoc($this->res_query);
		}
		else
		{
			$this->result = FALSE;
		}
		return $this->result;
	}

	/// Функция получения массива результатов
	/**
	 * Обрабатывает ссылку на результаты, полученную через query ($res_query), помещая полученный массив результатов в $result
	 * @return Массив результатов по ранее сформированному запросу.
	 * @see    $res_query, $result
	 */
	function res_arr()
	{
		if ($this->res_query)
		{
			$i = 0;
			$this->result = array();
			while ($tmp = @mysql_fetch_assoc($this->res_query))
			{
				$this->result[$i] = $tmp;
				$i++;
			}
			if ($i = 0)
			{
				$this->result = FALSE;
			}
		}
		else
		{
			$this->result = FALSE;
		}
		return $this->result;
	}

	/// Функция очистки переменных класса
	/**
	 * Очищает переменные класса, возвращая им исходные значения, а именно: $txt_query, $res_query, $result, $aff_rows, $insert_id, $error
	 * @see $txt_query, $res_query, $result, $aff_rows, $insert_id, $error, select, delete, update
	 */
	private function clean()
	{
		$this->txt_query = '';
		$this->res_query = FALSE;
		$this->result = FALSE;
		$this->aff_rows = FALSE;
		$this->insert_id = FALSE;
		$this->error = '';
	}
}

?>
