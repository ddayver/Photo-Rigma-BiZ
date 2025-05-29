<?php

namespace PhotoRigma;

use PDO;
use PDOException;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'Этот скрипт должен запускаться только из CLI' . PHP_EOL);
    exit(1);
}

if (!defined('IN_GALLERY')) {
    fwrite(STDERR, 'Этот скрипт должен использоваться только внутри других скриптов' . PHP_EOL);
    exit(1);
}

// --- Функции для подключения к СУБД ---

/**
 * Подключается к базе SQLite или создаёт её в указанной директории.
 *
 * Функция:
 * - Формирует путь к файлу SQLite в подкаталоге var/sql/
 * - Создаёт директорию, если она отсутствует
 * - Создаёт файл БД, если он не существует
 * - Проверяет доступность файла для записи
 * - Возвращает PDO-соединение с SQLite
 *
 * @param string $workDir  Рабочая директория проекта
 *                           Пример: '/var/www/photorigma'
 * @param string $dbName   Имя создаваемой базы данных (без расширения)
 *                           Пример: 'main', 'cache'
 *
 * @return PDO Объект соединения с SQLite через PDO
 *
 * @note   При отсутствии директории — она создаётся с правами 0755
 * @note   Новый файл БД устанавливается с правами 0660
 * @note   Используется только при установке и настройке проекта
 *
 * @warning Для работы требуется доступ к touch(), is_writable(), mkdir(), chmod()
 * @warning Требуются права на чтение и запись в директории var/sql/
 * @warning Не используйте прямой вызов из веба — это может быть опасно
 *
 * // Пример вызова через CLI при установке
 * $pdo = connect_sqlite_install('/var/www/photorigma', 'main');
 */
function connect_sqlite_install(string $workDir, string $dbName): PDO
{
    $sqlitePath = "$workDir/var/sql/$dbName.sqlite";

    if (!is_dir(dirname($sqlitePath)) && !mkdir(dirname($sqlitePath), 0755, true) && !is_dir(dirname($sqlitePath))) {
        fwrite(STDERR, 'Не удалось создать директорию: ' . dirname($sqlitePath) . PHP_EOL);
        exit(1);
    }

    if (!is_file($sqlitePath)) {
        if (!touch($sqlitePath)) {
            fwrite(STDERR, "Не удалось создать файл SQLite: $sqlitePath" . PHP_EOL);
            exit(1);
        }
        chmod($sqlitePath, 0660);
        echo "[OK] Файл SQLite создан: $sqlitePath" . PHP_EOL;
    } elseif (!is_writable($sqlitePath)) {
        fwrite(STDERR, "[ERROR] Файл SQLite недоступен для записи: $sqlitePath" . PHP_EOL);
        exit(1);
    } else {
        echo "[SKIP] Файл SQLite уже существует: $sqlitePath" . PHP_EOL;
    }

    return new PDO("sqlite:$sqlitePath", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

/**
 * Подключается к существующей базе данных SQLite.
 *
 * Функция:
 * - Формирует путь к файлу SQLite в подкаталоге var/sql/
 * - Проверяет наличие файла БД
 * - Возвращает PDO-соединение с SQLite
 *
 * @param string $workDir  Рабочая директория проекта
 *                           Пример: '/var/www/photorigma'
 * @param string $dbName   Имя существующей базы данных (без расширения)
 *                           Пример: 'main', 'cache'
 *
 * @return PDO Объект соединения с SQLite через PDO
 *
 * @note   Файл базы должен существовать — иначе генерируется ошибка
 * @note   Не создаёт новые файлы, только подключение к уже существующим
 * @note   Используется при обновлении структуры БД или миграциях
 *
 * @warning Для работы требуется существующий файл SQLite
 * @warning Требуются права на чтение файла БД
 * @warning Не используйте прямой вызов из веба — это может быть опасно
 *
 * // Пример вызова через CLI (при выполнении миграции)
 * $pdo = connect_sqlite_update('/var/www/photorigma', 'main');
 */
function connect_sqlite_update(string $workDir, string $dbName): PDO
{
    $sqlitePath = "$workDir/var/sql/$dbName.sqlite";

    if (!file_exists($sqlitePath)) {
        fwrite(STDERR, "[ERROR] Файл SQLite отсутствует: $sqlitePath" . PHP_EOL);
        exit(1);
    }

    return new PDO("sqlite:$sqlitePath", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

/**
 * Подключается к базе данных через сокет или host:port.
 *
 * Функция:
 * - Сначала пробует подключение через сокет (если указан и доступен)
 * - При ошибке переходит на подключение через host и port
 * - Использует PDO для подключения
 * - Для MySQL автоматически устанавливает charset=utf8mb4
 *
 * @param string      $dbType Тип СУБД: 'mysql' или 'pgsql'
 * @param string      $dbName  Имя базы данных
 * @param string|null $socket Путь к сокету (для MySQL / PgSQL)
 *                            Пример: '/var/lib/mysql/mysql.sock'
 * @param string|null $host   Хост БД (например: 'localhost', '127.0.0.1')
 * @param string|null $port   Порт сервера БД (например: '3306' для MySQL, '5432' для PgSQL)
 * @param string|null $user   Имя пользователя БД
 * @param string|null $pass   Пароль пользователя БД
 *
 * @return PDO Объект соединения с базой данных через PDO
 *
 * @note   При использовании MySQL автоматически устанавливается utf8mb4
 * @note   В случае ошибки с сокетом выполняется переход на host:port
 * @note   Не используется для SQLite — только для MySQL и PostgreSQL
 *
 * @warning Для работы требуются параметры $host + $user + $pass (кроме случая с сокетом)
 * @warning Убедитесь, что указана хотя бы одна точка подключения: сокет или хост
 * @warning Не используйте прямой вызов из веба — это может быть опасно
 *
 * // Пример вызова через CLI при обновлении структуры MySQL
 * connect_sql('mysql', 'photorigma', null, 'localhost', '3306', 'root', 'password');
 *
 * // Пример использования для PgSQL
 * connect_sql('pgsql', 'photorigma', null, 'localhost', '5432', 'postgres', '');
 */
function connect_sql(
    string $dbType,
    string $dbName,
    ?string $socket,
    ?string $host,
    ?string $port,
    ?string $user,
    ?string $pass
): PDO {
    try {
        // Сначала пробуем через сокет
        if ($socket && is_file($socket)) {
            $dsn = "$dbType:unix_socket=$socket;dbname=$dbName" . (($dbType === 'mysql') ? ';charset=utf8mb4' : '');
            echo "[INFO] Попытка подключения к $dbType через сокет: $socket" . PHP_EOL;
            return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }
    } catch (PDOException) {
        echo '[INFO] Сокет недоступен. Переход на host:port...' . PHP_EOL;
    }

    // Если сокет не работает — через host:port
    $dsn = "$dbType:host=$host;" . ($port ? "port=$port;" : '') . "dbname=$dbName" . (($dbType === 'mysql') ? ';charset=utf8mb4' : '');

    echo "[INFO] Подключение к $dbType через $host:" . ($port ?: 'default') . PHP_EOL;

    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

// --- Функции для работы с версиями ---

/**
 * Извлекает версию из имени SQL-файла.
 *
 * Функция:
 * - Проверяет имя файла на соответствие шаблону 'ver.X.Y.Z.sql'
 * - Возвращает номер версии как строку, если имя соответствует
 * - Возвращает NULL, если имя не подходит под формат
 *
 * @param string $filename Имя файла для анализа
 *                         Пример: 'ver.0.4.4.sql'
 *
 * @return string|null Номер версии в виде строки или NULL, если не найден
 *
 * @note   Поддерживает только файлы с именем вида 'ver.X.Y.Z.sql'
 * @note   Регистр имени файла не важен (работает как с 'Ver', так и 'VER')
 *
 * @warning Не обрабатывает произвольные имена — только строго заданный формат
 *
 * // Пример вызова
 * $version = extract_version('ver.0.4.4.sql');
 */
function extract_version(string $filename): ?string
{
    if (preg_match('/^ver\.(\d+\.\d+\.\d+)\.sql$/i', $filename, $match)) {
        return $match[1];
    }
    return null;
}

// --- Функции для работы с настройкой cron ---

/**
 * Добавляет cron-задачу для указанного скрипта с ежедневным выполнением в заданное время.
 *
 * Функция:
 * - Проверяет текущий crontab на наличие уже существующей задачи
 * - Если задача не найдена — очищает список от дублирующих строк
 * - Добавляет новую запись с указанным временем и путём
 * - Перезаписывает crontab с новой задачей
 *
 * @param string $scriptPath Путь к скрипту, который нужно добавить в cron
 *                           Пример: '/var/www/photorigma/public/index.php'
 * @param int    $hour       Час ежедневного запуска (0–23)
 *                           Пример: 3 → запуск в 03:00
 *
 * @return void
 *
 * @note   Не добавляет дубли — проверяет перед установкой
 * @note   Все изменения сохраняются в файле /tmp/current_cron перед перезаписью
 *
 * @warning Для работы требуется доступ к shell_exec(), exec(), file_put_contents()
 * @warning Не используйте прямой вызов из веба — это может быть опасно
 * @warning Требуются права на чтение и редактирование crontab
 *
 * // Пример вызова через CLI (ежедневно в 04:00)
 * setup_cron_task('/var/www/photorigma/public/index.php', 4);
 */
function setup_cron_task(string $scriptPath, int $hour): void
{
    $escapedScriptPath = preg_quote($scriptPath, '/');
    $newEntry = "0 $hour * * * php $scriptPath > /dev/null 2>&1";

    // Получаем текущий crontab
    $output = shell_exec('crontab -l 2>/dev/null');

    if ($output === null) {
        fwrite(STDERR, '[ERROR] Не удалось получить текущий crontab. Проверьте права.' . PHP_EOL);
        exit(1);
    }

    $existingCrons = array_filter(array_map('trim', explode(PHP_EOL, $output)));
    $cleaned = [];

    foreach ($existingCrons as $line) {
        if (preg_match("/^\d+ \d+ \* \* \* php $escapedScriptPath/", $line)) {
            echo '[INFO] Задача уже существует в crontab' . PHP_EOL;
            return;
        }
        $cleaned[] = $line;
    }

    file_put_contents('/tmp/current_cron', implode(PHP_EOL, $cleaned) . PHP_EOL . $newEntry . PHP_EOL, LOCK_EX);
    exec('crontab /tmp/current_cron', $output, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, '[ERROR] Не удалось обновить crontab' . PHP_EOL);
        exit(1);
    }

    echo "[OK] Crontab обновлён. Ежедневное выполнение в $hour:00" . PHP_EOL;
}

/**
 * Удаляет cron-задачу для указанного скрипта.
 *
 * Функция:
 * - Проверяет текущий crontab на наличие задачи
 * - Убирает строку с задачей, если она найдена
 * - Перезаписывает crontab без удалённой задачи
 *
 * @param string $scriptPath Путь к скрипту, который нужно удалить из cron
 *                           Пример: '/var/www/photorigma/public/index.php'
 *
 * @return void
 *
 * @note   Если задача не найдена, выводится сообщение [SKIP] и ничего не меняется
 * @note   Используется только при управлении cron-задачами из CLI
 *
 * @warning Для работы требуется доступ к shell_exec(), exec() и правам на редактирование crontab
 * @warning Не используйте прямой вызов из веба — это может быть опасно
 *
 * // Пример вызова через CLI
 * remove_cron_task('/var/www/photorigma/public/index.php');
 */
function remove_cron_task(string $scriptPath): void
{
    $escapedScriptPath = preg_quote($scriptPath, '/');

    // Получаем текущий crontab
    $output = shell_exec('crontab -l 2>/dev/null');

    if ($output === null) {
        echo '[SKIP] Нет активных cron-задач' . PHP_EOL;
        return;
    }

    $existingCrons = array_filter(array_map('trim', explode(PHP_EOL, $output)));
    $cleaned = [];
    $found = false;

    foreach ($existingCrons as $line) {
        if (preg_match("/^\d+ \d+ \* \* \* php $escapedScriptPath/", $line)) {
            echo '[INFO] Удалена cron-задача для cron.php' . PHP_EOL;
            $found = true;
            continue;
        }
        $cleaned[] = $line;
    }

    // Если ничего не нашли → выходим
    if (!$found) {
        echo '[SKIP] Задача для cron.php не найдена в crontab' . PHP_EOL;
        return;
    }

    // Перезаписываем crontab без удалённой задачи
    file_put_contents('/tmp/current_cron', implode(PHP_EOL, $cleaned) . PHP_EOL, LOCK_EX);
    exec('crontab /tmp/current_cron', $output, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, '[ERROR] Не удалось очистить crontab' . PHP_EOL);
        exit(1);
    }
}
