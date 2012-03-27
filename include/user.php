<?php
/**
* @file		include/user.php
* @brief	Класс работы с пользователем.
* @author	Dark Dayver
* @version	0.1.1
* @date		27/03-2012
* @details	Класс работы с пользователем.
*/

// Проверка, что файл подключается из индексного, а не набран напрямую в адресной строке
if (IN_GALLERY)
{
	die('HACK!');
}

class user
{
	// Внутри класса используются:
	// $user - массив, содержащий все данные о текущем пользователе

	// Функции:
	// User() - заполняет данные при создании объекта класса данными о текущем пользователе

	var $user = array();

	function user()
	{
		global $db; // подключаем глобальный объект для работы с БД

		if (empty($_SESSION['login_id'])) // если Не существует текущая сессия, то...
		{
			$_SESSION['login_id'] = 0; // указываем, что текущий пользователь является гостем
		}

		if ($_SESSION['login_id'] === 0) // если текущий пользователь является гостем, то...
		{
			$this->user = $db->fetch_array("SELECT * FROM `group` WHERE `id` = 0"); // получаем данные для пользователя из группы "Гость"
		}
		else // иначе...
		{
			$this->user = $db->fetch_array("SELECT * FROM `user` WHERE `id` = " . $_SESSION['login_id']); // получаем данные о текущем пользователе
			if ($this->user) // если данные получены, то...
			{
				$temp = $db->fetch_array("SELECT * FROM `group` WHERE `id` = " . $this->user['group']); // получаем данные о групе, в которой состоит пользователеь
				if (!$temp) // если нет данных о групе, то...
				{
					$this->user['group'] = 0; // принимаем, что пользователь состоит в группе "Гость"
					$temp = $db->fetch_array("SELECT * FROM `group` WHERE `id` = " . $this->user['group']); // повторно запрашиваем данные о группе (теперь уже о группе "Гость")
				}

				foreach ($temp as $key => $value) // извлекаем права доступа для группы, в которой пользователь состоит
				{
					if ($key != 'id' && $key != 'name') // если это НЕ поля идентификатора или названия группы, то...
					{
						if ($this->user[$key] == 0 && $value == 0) // если привелегия как в правах пользователя, так и правах группы не существует, то...
						{
							$this->user[$key] = false; // эта привелегия равна false (ложь)
						}
						else // иначе...
						{
							$this->user[$key] = true; // эта привлегеия равна true (истина)
						}
					}
					elseif ($key == 'name') // если это поле названия группы, то...
					{
						$this->user['group_id'] = $this->user['group']; // дополняем данные о группе, в которой состоит пользователь - идентификатором группы
						$this->user['group'] = $value; // заменяем текущее значение идентификатора группы её названием
					}
				}
				$db->query("UPDATE `user` SET `date_last_activ` = NOW() WHERE `id` = " . $_SESSION['login_id']); // обновляем поле последней активности пользователя на сайте (срабатывает при каждом переходе по сайту)
			}
			else // иначе
			{
				$_SESSION['login_id'] = 0; // пользователь является Гостем
				$this->user = $db->fetch_array("SELECT * FROM `group` WHERE `id` = 0"); // получаем данные для группы "Гость"
			}
		}
	}
}
?>
