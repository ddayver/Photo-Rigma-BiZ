<?php
/**
 * @file        include/user.php
 * @brief       Класс работы с пользователем.
 * @author      Dark Dayver
 * @version     0.2.0
 * @date        28/03-2012
 * @details     Класс работы с пользователем.
 */
/// @cond
if (IN_GALLERY !== TRUE)
{
	die('HACK!');
}
/// @endcond

/// Класс по работе с пользователями.
/**
 * Данный класс содержит набор функций для работы с пользователями, а так же используется для хранения всех данных о текущем пользователе.
 */
class user
{
	var $user = array(); ///< Массив, содержащий все данные о текущем пользователе.

	/// Конструктор класса, заполняет данные при создании объекта класса данными о текущем пользователе.
	/**
	 * @see ::$db
	 */
	function user()
	{
		global $db;

		if (!isset($_SESSION['login_id']) || (isset($_SESSION['login_id']) && empty($_SESSION['login_id']))) $_SESSION['login_id'] = 0;

		if ($_SESSION['login_id'] === 0)
		{
			if ($db->select('*', TBL_GROUP, '`id` = 0'))
			{
				$this->user = $db->res_row();
				if (!$this->user) log_in_file('Unable to get the guest group', DIE_IF_ERROR);
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}
		else
		{
			if ($db->select('*', TBL_USERS, '`id` = ' . $_SESSION['login_id']))
			{
				$this->user = $db->res_row();
				if ($this->user)
				{
					if ($db->select('*', TBL_GROUP, '`id` = ' . $this->user['group']))
					{
						$temp = $db->res_row();
						if (!$temp)
						{
							$this->user['group'] = 0;
							if ($db->select('*', TBL_GROUP, '`id` = 0'))
							{
								$temp = $db->res_row();
								if (!$temp) log_in_file('Unable to get the guest group', DIE_IF_ERROR);
							}
							else log_in_file($db->error, DIE_IF_ERROR);
						}
						foreach ($temp as $key => $value)
						{
							if ($key != 'id' && $key != 'name')
							{
								if ($this->user[$key] == 0 && $value == 0) $this->user[$key] = FALSE;
								else $this->user[$key] = TRUE;
							}
							elseif ($key == 'name')
							{
								$this->user['group_id'] = $this->user['group'];
								$this->user['group'] = $value;
							}
						}
						if (!$db->update(array('date_last_activ' => date('Y-m-d H:i:s')), TBL_USERS, '`id` = ' . $_SESSION['login_id'])) log_in_file($db->error, DIE_IF_ERROR);
					}
					else log_in_file($db->error, DIE_IF_ERROR);
				}
				else
				{
					$_SESSION['login_id'] = 0;
					if ($db->select('*', TBL_GROUP, '`id` = 0'))
					{
						$this->user = $db->res_row();
						if (!$this->user) log_in_file('Unable to get the guest group', DIE_IF_ERROR);
					}
					else log_in_file($db->error, DIE_IF_ERROR);
				}
			}
			else log_in_file($db->error, DIE_IF_ERROR);
		}
	}
}

?>
