<?php

/** @noinspection TypoSafeNamingInspection */
/**
 * @file      include/Work_Image.php
 * @brief     Файл содержит класс Work_Image, который отвечает за работу с изображениями.
 *
 * @author    Dark Dayver
 * @version   0.4.4
 * @date      2025-05-07
 * @namespace PhotoRigma\\Classes
 *
 * @details   Этот файл содержит класс `Work_Image`, который реализует интерфейс `Work_Image_Interface`.
 *            Класс предоставляет методы для работы с изображениями, включая изменение размеров, вывод через HTTP,
 *            обработку отсутствующих изображений, корректировку расширения файла в соответствии с его MIME-типом
 *            и логирование ошибок. Публичные методы класса являются редиректами
 *            на защищённые методы с суффиксом `_internal`, что обеспечивает более гибкую архитектуру (паттерн
 *            "фасад").
 *
 * @section   WorkImage_Main_Functions Основные функции
 *            - Создание директорий для категорий и копирование файлов `index.php`.
 *            - Корректировка расширения файла в соответствии с его MIME-типом.
 *            - Вывод изображений через HTTP с защитой от MIME-sniffing и кликджекинга.
 *            - Изменение размеров изображений (создание эскизов).
 *            - Удаление директорий и их содержимого.
 *            - Вычисление размеров для вывода эскиза изображения.
 *            - Возвращение данных для отсутствующего изображения.
 *
 * @see       PhotoRigma::Interfaces::Work_Image_Interface
 *            Интерфейс для работы с изображениями.
 * @see       PhotoRigma::Classes::Work
 *            Класс, через который вызываются методы для работы с изображениями.
 * @see       PhotoRigma::Include::log_in_file()
 *            Функция для логирования ошибок.
 *
 * @note      Этот файл является частью системы PhotoRigma и играет ключевую роль в обработке изображений.
 *            Класс поддерживает работу с библиотеками GraphicsMagick, ImageMagick и GD для изменения размеров
 *            изображений. Реализованы меры безопасности для предотвращения уязвимостей, таких как MIME-sniffing
 *            и кликджекинг.
 *
 * @copyright Copyright (c) 2008-2025 Dark Dayver. Все права защищены.
 * @license   MIT License (https://opensource.org/licenses/MIT)
 *            Разрешается использовать, копировать, изменять, объединять, публиковать, распространять,
 *            сублицензировать и/или продавать копии программного обеспечения, а также разрешать лицам, которым
 *            предоставляется данное программное обеспечение, делать это при соблюдении следующих условий:
 *            - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые
 *              части программного обеспечения.
 */

namespace PhotoRigma\Classes;

use Exception;
use Gmagick;
use GmagickException;
use GmagickPixel;
use Imagick;
use InvalidArgumentException;
use JetBrains\PhpStorm\NoReturn;
use PhotoRigma\Interfaces\Work_Image_Interface;
use RuntimeException;

use function PhotoRigma\Include\log_in_file;

// Предотвращение прямого вызова файла
if (!defined('IN_GALLERY') || IN_GALLERY !== true) {
    /** @noinspection ForgottenDebugOutputInspection */
    error_log(
        date('H:i:s') . ' [ERROR] | ' . (filter_input(
            INPUT_SERVER,
            'REMOTE_ADDR',
            FILTER_VALIDATE_IP
        ) ?: 'UNKNOWN_IP') . ' | ' . __FILE__ . ' | Попытка прямого вызова файла'
    );
    die('HACK!');
}

/**
 * @class   Work_Image
 * @brief   Класс для работы с изображениями.
 *
 * @details Этот класс реализует интерфейс `Work_Image_Interface` и предоставляет функционал для работы с изображениями.
 *          Он является дочерним классом для `PhotoRigma::Classes::Work` и наследует его методы. Все методы данного
 *          класса рекомендуется вызывать через родительский класс `Work`, так как их поведение может быть
 *          непредсказуемым при прямом вызове.
 *          Основные возможности:
 *          - Создание директорий для категорий и копирование файлов `index.php`.
 *          - Корректировка расширения файла в соответствии с его MIME-типом.
 *          - Вывод изображений через HTTP с защитой от MIME-sniffing и кликджекинга.
 *          - Изменение размеров изображений (создание эскизов).
 *          - Удаление директорий и их содержимого.
 *          - Вычисление размеров для вывода эскиза изображения.
 *          - Возвращение данных для отсутствующего изображения.
 *          Методы класса предназначены для использования в различных частях приложения, таких как обработка
 *          изображений, работа с файловой системой и генерация безопасного вывода.
 *
 * @implements Work_Image_Interface
 *
 * @property array $config          Конфигурация приложения.
 * @property int   MAX_IMAGE_WIDTH  Максимальная ширина изображения (в пикселях).
 * @property int   MAX_IMAGE_HEIGHT Максимальная высота изображения (в пикселях).
 *
 * Пример использования класса:
 * @code
 * // Инициализация объекта класса Work_Image
 * $config = [
 *     'site_url' => 'https://example.com',
 *     'temp_photo_w' => 800,
 *     'temp_photo_h' => 600,
 * ];
 * $image = new \\PhotoRigma\\Classes\\Work_Image($config);
 *
 * // Вызов метода resize_image через объект класса
 * $success = $image->resize_image('/path/to/source.jpg', '/path/to/thumbnail.jpg');
 * var_dump($success); // Выведет: true или false
 *
 * // Вызов метода image_attach через объект класса
 * $image->image_attach('/path/to/image.jpg', 'image_name.jpg');
 * @endcode
 * @see    PhotoRigma::Interfaces::Work_Image_Interface
 *         Интерфейс, который реализует данный класс.
 */
class Work_Image implements Work_Image_Interface
{
    // Свойства:
    private const int MAX_IMAGE_WIDTH = 5000; ///< Максимальная ширина изображения (в пикселях)
    private const int MAX_IMAGE_HEIGHT = 5000; ///< Максимальная высота изображения (в пикселях)
    private array $config; ///< Конфигурация приложения.

    /**
     * @brief   Конструктор класса.
     *
     * @details Инициализирует объект класса `Work_Image` с переданной конфигурацией.
     *          Этот класс является дочерним для PhotoRigma::Classes::Work.
     *
     * @param array $config Конфигурация приложения:
     *                      - Должен быть массивом.
     *                      - Используется для хранения настроек приложения.
     *
     * @note    Конфигурация должна содержать все необходимые параметры для корректной работы класса.
     *          Параметры сохраняются в свойство $this->config.
     *
     * @warning Отсутствие необходимых ключей в массиве $config может привести к ошибкам при работе класса.
     *          Убедитесь, что конфигурация содержит все требуемые параметры.
     *
     * Пример использования конструктора:
     * @code
     * $config = [
     *     'temp_photo_w' => 800,
     *     'temp_photo_h' => 600,
     * ];
     * $workImage = new \PhotoRigma\Classes\Work_Image($config);
     * @endcode
     * @see    PhotoRigma::Classes::Work
     *         Родительский класс, который создаёт объект класса Work_Image.
     * @see    PhotoRigma::Classes::Work_Image::$config
     *         Свойство, содержащее конфигурацию приложения.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @brief   Получает значение приватного свойства.
     *
     * @details Этот метод позволяет получить доступ к приватному свойству `$config`.
     *          Алгоритм работы:
     *          1. Проверяет, соответствует ли имя свойства допустимому значению (`config`).
     *          2. Если имя свойства корректно, возвращает значение свойства `$config`.
     *          3. Если имя свойства некорректно, выбрасывается исключение.
     *          Метод является публичным и предназначен для получения доступа к свойству `$config`.
     *
     * @param string $name Имя свойства:
     *                     - Допустимое значение: 'config'.
     *                     - Если указано другое имя, выбрасывается исключение.
     *                     Пример: 'config'.
     *                     Ограничения: только одно допустимое значение.
     *
     * @return array Значение свойства `$config`.
     *               Пример: ['temp_photo_w' => 800]
     *
     * @throws InvalidArgumentException Выбрасывается, если запрашиваемое свойство не существует.
     *                                  Пример сообщения:
     *                                      Свойство не существует | Получено: [$name]
     *
     * @note    Этот метод предназначен только для доступа к свойству `$config`.
     *          Любые другие запросы будут игнорироваться с выбросом исключения.
     *
     * @warning Убедитесь, что вы запрашиваете только допустимое свойство:
     *          - 'config'
     *          Некорректные имена свойств вызовут исключение.
     *
     * Пример использования метода:
     * @code
     * $workImage = new \PhotoRigma\Classes\Work_Image(['temp_photo_w' => 800]);
     * echo $workImage->config['temp_photo_w']; // Выведет: 800
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::$config
     *         Свойство, к которому обращается метод.
     */
    public function __get(string $name): array
    {
        if ($name === 'config') {
            return $this->config;
        }
        throw new InvalidArgumentException(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не существует | Получено: '$name'"
        );
    }

    /**
     * @brief   Устанавливает значение приватного свойства.
     *
     * @details Этот метод позволяет изменить значение приватного свойства `$config`.
     *          Алгоритм работы:
     *          1. Проверяет, соответствует ли имя свойства допустимому значению (`config`).
     *          2. Если имя свойства корректно, обновляет значение свойства `$config`.
     *          3. Если имя свойства некорректно, выбрасывается исключение.
     *          Метод является публичным и предназначен для изменения свойства `$config`.
     *
     * @param string $name  Имя свойства:
     *                      - Допустимое значение: 'config'.
     *                      - Если указано другое имя, выбрасывается исключение.
     *                      Пример: 'config'.
     *                      Ограничения: только одно допустимое значение.
     * @param array  $value Новое значение свойства:
     *                      - Должен быть ассоциативным массивом.
     *                      Пример: `['temp_photo_w' => 1024]`.
     *
     * @throws InvalidArgumentException Выбрасывается, если переданное имя свойства не соответствует `$config`.
     *                                  Пример сообщения:
     *                                      Свойство не может быть установлено | Получено: [$name]
     *
     * @note    Этот метод предназначен только для изменения свойства `$config`.
     *          Любые другие попытки установки значений будут игнорироваться с выбросом исключения.
     *
     * @warning Убедитесь, что вы устанавливаете значение только для допустимого свойства:
     *          - 'config'
     *          Некорректные имена свойств вызовут исключение.
     *
     * Пример использования метода:
     * @code
     * $workImage = new \PhotoRigma\Classes\Work_Image([]);
     * $workImage->config = ['temp_photo_w' => 1024];
     * echo $workImage->config['temp_photo_w']; // Выведет: 1024
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::$config
     *         Свойство, которое изменяет метод.
     */
    public function __set(string $name, array $value): void
    {
        if ($name === 'config') {
            $this->config = $value;
        } else {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не может быть установлено | Получено: '$name'"
            );
        }
    }

    /**
     * @brief   Проверяет существование недоступного свойства.
     *
     * @details Этот метод вызывается автоматически при использовании оператора `isset()` для проверки
     *          существования недоступного свойства. Метод возвращает `true`, если свойство существует,
     *          и `false` в противном случае.
     *
     * @param string $name Имя свойства:
     *                     - Проверяется на существование.
     *
     * @return bool Результат проверки:
     *              - `true`, если свойство существует.
     *              - `false`, если свойство не существует.
     *
     * @note    Этот метод предназначен только для проверки существования свойств.
     *
     * @warning Если свойство не определено или является недоступным, результат будет `false`.
     *
     * Пример использования метода:
     * @code
     * $work_image = new \PhotoRigma\Classes\Work_Image();
     * if (isset($work_image->config)) {
     *     echo "Свойство 'config' существует.";
     * } else {
     *     echo "Свойство 'config' не существует.";
     * }
     * @endcode
     */
    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    /**
     * @brief   Создаёт директории для категории и копирует файлы `index.php` через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _create_directory_internal().
     *          Он создаёт директории для категории и копирует файлы `index.php`. Метод также доступен через метод-фасад
     *          `create_directory()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $directory_name Имя директории:
     *                               - Должен быть строкой, содержащей только допустимые символы для имён директорий.
     *                               - Не должен содержать запрещённых символов (например, `\/:*?"<>|`).
     *
     * @return bool Возвращает `true`, если директории успешно созданы и файлы скопированы.
     *
     * @throws RuntimeException Выбрасывается исключение в следующих случаях:
     *                           - Если родительская директория недоступна для записи.
     *                           - Если не удалось создать директории.
     *                           - Если исходные файлы `index.php` не существуют.
     *                           - Если не удалось прочитать или записать файлы `index.php`.
     *
     * @warning Используйте этот метод с осторожностью, так как он создаёт директории и изменяет файлы.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание директорий для новой категории
     * $object = new \PhotoRigma\Classes\Work_Image();
     * $directoryName = 'new_category';
     * $result = $object->create_directory($directoryName);
     * if ($result) {
     *     echo "Директории успешно созданы!";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::_create_directory_internal()
     *         Защищённый метод, реализующий основную логику.
     * @see    PhotoRigma::Classes::Work::create_directory()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public function create_directory(string $directory_name): bool
    {
        return $this->_create_directory_internal($directory_name);
    }

    /**
     * @brief   Создает директории для категории и копирует файлы `index.php`, предварительно проверяя права доступа.
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Формирует пути для директорий галереи и миниатюр с использованием конфигурационных параметров:
     *             - `site_dir`: Базовая директория сайта.
     *             - `gallery_folder`: Директория для галереи.
     *             - `thumbnail_folder`: Директория для миниатюр.
     *          2. Проверяет права доступа к родительским директориям с помощью `is_writable`.
     *          3. Рекурсивно создаёт директории для галереи и миниатюр с использованием `mkdir`.
     *          4. Проверяет существование исходных файлов `index.php` в директориях галереи и миниатюр.
     *          5. Копирует и модифицирует файлы `index.php` для новой директории галереи и миниатюр:
     *             - Читает содержимое исходных файлов с помощью `file_get_contents`.
     *             - Модифицирует содержимое с помощью `strtr`.
     *             - Записывает изменённые файлы в новые директории с использованием `file_put_contents`.
     *          Метод вызывается через публичный метод-редирект `PhotoRigma::Classes::Work_Image::create_directory()`
     *          и предназначен для использования внутри класса или его наследников.
     *
     * @callergraph
     *
     * @param string $directory_name Имя директории.
     *                               Должен быть строкой, содержащей только допустимые символы для имён директорий.
     *                               Не должен содержать запрещённых символов (например, `\/:*?"<>|`).
     *
     * @return bool Возвращает `true`, если директории успешно созданы и файлы скопированы.
     *
     * @throws RuntimeException Выбрасывается исключение в следующих случаях:
     *                           - Если родительская директория недоступна для записи.
     *                           - Если не удалось создать директории.
     *                           - Если исходные файлы `index.php` не существуют.
     *                           - Если не удалось прочитать или записать файлы `index.php`.
     *
     * @warning Используйте этот метод с осторожностью, так как он создаёт директории и изменяет файлы.
     *
     * Пример вызова метода внутри класса или наследника:
     * @code
     * // Создание директорий для новой категории
     * $directoryName = 'new_category';
     * $result = $this->_create_directory_internal($directoryName);
     * if ($result) {
     *     echo "Директории успешно созданы!";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::create_directory()
     *         Публичный метод-редирект для вызова этой логики.
     * @see    PhotoRigma::Classes::Work_Image::$config
     *         Конфигурационные параметры, используемые для формирования путей.
     */
    protected function _create_directory_internal(string $directory_name): bool
    {
        // Формируем пути для галереи и миниатюр с использованием sprintf()
        $gallery_path = sprintf(
            '%s/%s',
            GALLERY_DIR,
            $directory_name
        );
        $thumbnail_path = sprintf(
            '%s/%s',
            THUMBNAIL_DIR,
            $directory_name
        );

        // Проверяем права доступа к родительским директориям
        if (!is_writable(dirname($gallery_path))) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Родительская директория недоступна для записи | Путь: ' . dirname(
                    $gallery_path
                )
            );
        }
        if (!is_writable(dirname($thumbnail_path))) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Родительская директория недоступна для записи | Путь: ' . dirname(
                    $thumbnail_path
                )
            );
        }

        // Создаем директории рекурсивно
        if (!mkdir($gallery_path, 0755, true) || !is_dir($gallery_path) || !mkdir(
            $thumbnail_path,
            0755,
            true
        ) || !is_dir($thumbnail_path)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось создать директории для категории | Имя: $directory_name"
            );
        }

        // Определяем пути к исходным файлам index.php
        $gallery_index_file = sprintf('%s/index.php', GALLERY_DIR);
        $thumbnail_index_file = sprintf('%s/index.php',THUMBNAIL_DIR);

        // Проверяем существование исходных файлов index.php
        if (!is_file($gallery_index_file)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл index.php не существует | Путь: $gallery_index_file"
            );
        }
        if (!is_file($thumbnail_index_file)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл index.php не существует | Путь: $thumbnail_index_file"
            );
        }

        // Копируем index.php в новую директорию галереи
        $gallery_index_content = file_get_contents($gallery_index_file);
        if ($gallery_index_content === false) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось прочитать файл index.php | Путь: $gallery_index_file"
            );
        }
        $gallery_index_content = strtr($gallery_index_content, [
            $this->config['gallery_dir'] . '/index.php' => $this->config['gallery_dir'] . "/$directory_name/index.php",
        ]);
        if (file_put_contents($gallery_path . '/index.php', $gallery_index_content, LOCK_EX) === false) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось записать файл index.php | Путь: $gallery_path/index.php"
            );
        }

        // Копируем index.php в новую директорию миниатюр
        $thumbnail_index_content = file_get_contents($thumbnail_index_file);
        if ($thumbnail_index_content === false) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось прочитать файл index.php | Путь: $thumbnail_index_file"
            );
        }
        $thumbnail_index_content = strtr($thumbnail_index_content, [
            $this->config['thumbnail_dir'] . '/index.php' => $this->config['thumbnail_dir'] . "/$directory_name/index.php",
        ]);
        if (file_put_contents($thumbnail_path . '/index.php', $thumbnail_index_content, LOCK_EX) === false) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось записать файл index.php | Путь: $thumbnail_path/index.php"
            );
        }

        return true;
    }

    /**
     * @brief   Корректирует расширение файла в соответствии с его MIME-типом через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _fix_file_extension_internal().
     *          Он проверяет MIME-тип файла и корректирует его расширение на основе соответствия MIME-типу. Метод также
     *          доступен через метод-фасад `fix_file_extension()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $full_path Полный путь к файлу:
     *                          - Путь должен быть абсолютным.
     *                          - Файл должен существовать и быть доступным для чтения.
     *                          - Формат пути должен соответствовать регулярному выражению /^[a-zA-Z0-9\/\.\-_]+$/.
     *
     * @return string Полный путь к файлу с правильным расширением.
     *                Если расширение было изменено или добавлено, возвращается новый путь.
     *                Если расширение уже корректное, возвращается исходный путь.
     *
     * @throws InvalidArgumentException Если путь к файлу некорректен, имеет недопустимый формат или файл не существует.
     * @throws RuntimeException         Если MIME-тип файла не поддерживается или файл недоступен для чтения.
     * @throws Exception                При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @warning Метод завершает выполнение с ошибкой, если MIME-тип файла не поддерживается.
     *          Убедитесь, что файл существует и доступен для чтения перед вызовом метода.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Image
     * $image = new \PhotoRigma\Classes\Work_Image();
     *
     * // Корректировка расширения файла
     * $full_path = '/path/to/file_without_extension';
     * $corrected_path = $image->fix_file_extension($full_path);
     * echo "Исправленный путь: {$corrected_path}";
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::_fix_file_extension_internal()
     *         Защищённый метод, выполняющий основную логику.
     * @see    PhotoRigma::Classes::Work::fix_file_extension()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public function fix_file_extension(string $full_path): string
    {
        return $this->_fix_file_extension_internal($full_path);
    }

    /**
     * @brief   Корректировка расширения файла в соответствии с его MIME-типом (защищённый метод).
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Проверяет путь к файлу на соответствие регулярному выражению `/^[a-zA-Z0-9\/\.\-_]+$/`.
     *          2. Нормализует путь с помощью `realpath()` и проверяет, существует ли файл и доступен ли он для чтения.
     *          3. Определяет реальный MIME-тип файла с помощью `finfo_open(FILEINFO_MIME_TYPE)`.
     *          4. Проверяет соответствие MIME-типа поддерживаемым форматам:
     *             - 'image/jpeg' -> 'jpg'
     *             - 'image/png' -> 'png'
     *             - 'image/gif' -> 'gif'
     *             - 'image/webp' -> 'webp'
     *             - 'image/tiff' -> 'tiff'
     *             - 'image/svg+xml' -> 'svg'
     *             - 'image/bmp' -> 'bmp'
     *             - 'image/x-icon' -> 'ico'
     *             - 'image/avif' -> 'avif'
     *             - 'image/heic' -> 'heic'
     *             - 'image/vnd.adobe.photoshop' -> 'psd'
     *             - 'image/x-canon-cr2' -> 'cr2'
     *             - 'image/x-nikon-nef' -> 'nef'
     *             - 'image/x-xbitmap' -> 'xbm'
     *             - 'image/x-portable-anymap' -> 'pnm'
     *             - 'image/x-pcx' -> 'pcx'
     *          5. Если расширение отсутствует, добавляет его. Если расширение некорректное, заменяет его на
     *             правильное.
     *          6. Логирует случаи добавления или исправления расширения через `log_in_file`.
     *             Метод вызывается через публичный метод `fix_file_extension()` и предназначен для использования внутри
     *             класса или его наследников.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $full_path Полный путь к файлу.
     *                          Путь должен быть абсолютным, соответствовать регулярному выражению
     *                          `/^[a-zA-Z0-9\/\.\-_]+$/`, и файл должен существовать и быть доступным для чтения.
     *
     * @return string Полный путь к файлу с правильным расширением.
     *                Если расширение было изменено или добавлено, возвращается новый путь.
     *                Если расширение уже корректное, возвращается исходный путь.
     *
     * @throws InvalidArgumentException В следующих случаях:
     *                                  - Если путь к файлу имеет недопустимый формат.
     *                                  - Если файл не существует или недоступен.
     * @throws RuntimeException         В следующих случаях:
     *                                  - Если MIME-тип файла не поддерживается.
     *                                  - Если файл недоступен для чтения.
     * @throws Exception               При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @warning Метод завершает выполнение с ошибкой, если MIME-тип файла не поддерживается.
     *          Убедитесь, что файл существует и доступен для чтения перед вызовом метода.
     *
     * Пример использования:
     * @code
     * // Корректировка расширения файла
     * $full_path = '/path/to/file_without_extension';
     * $corrected_path = $this->_fix_file_extension_internal($full_path);
     * echo "Исправленный путь: {$corrected_path}";
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::fix_file_extension()
     *         Публичный метод, вызывающий этот защищённый метод.
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования ошибок.
     */
    protected function _fix_file_extension_internal(string $full_path): string
    {
        // Проверка корректности пути
        if (!filter_var($full_path, FILTER_VALIDATE_REGEXP, [
            'options' => ['regexp' => '/^[a-zA-Z0-9\/\.\-_]+$/'],
        ])) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный формат пути | Путь: \$full_path = $full_path"
            );
        }
        // Нормализация пути через realpath()
        $normalized_path = realpath($full_path);
        if (!$normalized_path) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл не существует или недоступен | Путь: \$full_path = $full_path"
            );
        }
        // Проверка существования файла и прав доступа
        if (!is_file($normalized_path)) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл не существует или недоступен | Путь: \$full_path = $full_path"
            );
        }
        if (!is_readable($normalized_path)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл недоступен для чтения | Путь: \$full_path = $full_path"
            );
        }
        // Определение реального MIME-типа файла
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime_type = finfo_file($finfo, $normalized_path);
        finfo_close($finfo);
        // Список соответствий MIME-типов и расширений
        $mime_to_extension = [
            'image/jpeg'                => 'jpg',
            'image/png'                 => 'png',
            'image/gif'                 => 'gif',
            'image/webp'                => 'webp',
            'image/tiff'                => 'tiff',
            'image/svg+xml'             => 'svg',
            'image/bmp'                 => 'bmp',
            'image/x-icon'              => 'ico',
            'image/avif'                => 'avif',
            'image/heic'                => 'heic',
            'image/vnd.adobe.photoshop' => 'psd',
            'image/x-canon-cr2'         => 'cr2',
            'image/x-nikon-nef'         => 'nef',
            'image/x-xbitmap'           => 'xbm',
            'image/x-portable-anymap'   => 'pnm',
            'image/x-pcx'               => 'pcx',
        ];
        // Проверка поддержки MIME-типа
        if (!array_key_exists($real_mime_type, $mime_to_extension)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неподдерживаемый MIME-тип файла | Получено: $real_mime_type"
            );
        }
        // Получение правильного расширения
        $correct_extension = $mime_to_extension[$real_mime_type];
        // Проверка текущего расширения файла
        $current_extension = pathinfo($normalized_path, PATHINFO_EXTENSION);
        if (empty($current_extension)) {
            // Если расширение отсутствует, добавляем его
            $new_full_path = $normalized_path . '.' . $correct_extension;
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Добавлено расширение '$correct_extension' к файлу | Путь: $normalized_path"
            );
            return $new_full_path;
        }
        if (strtolower($current_extension) === $correct_extension) {
            return $normalized_path; // Расширение уже корректное
        }
        // Формирование нового пути с правильным расширением
        $new_full_path = preg_replace('/\.[^.]+$/', '.' . $correct_extension, $normalized_path);
        log_in_file(
            __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Исправлено расширение файла | Старый путь: $normalized_path, Новый путь: $new_full_path"
        );
        return $new_full_path;
    }

    /**
     * @brief   Выводит изображение через HTTP через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _image_attach_internal().
     *          Он проверяет существование и доступность файла, определяет его MIME-тип и отправляет содержимое файла
     *          через HTTP. Метод также доступен через метод-фасад `image_attach()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $full_path Полный путь к файлу:
     *                          - Путь должен быть абсолютным.
     *                          - Файл должен существовать и быть доступным для чтения.
     * @param string $name_file Имя файла для заголовка `Content-Disposition`:
     *                          - Имя должно быть корректным (например, без запрещённых символов).
     *
     * @return void Метод ничего не возвращает. Завершает выполнение скрипта после отправки заголовков и содержимого
     *              файла.
     *
     * @throws Exception Если произошла ошибка при отправке файла или определении его MIME-типа.
     *
     * @warning Метод завершает выполнение скрипта (`exit`), отправляя заголовки и содержимое файла.
     *          Убедитесь, что файл существует и доступен для чтения перед вызовом метода.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Image
     * $image = new \PhotoRigma\Classes\Work_Image();
     *
     * // Вызов метода для вывода изображения через HTTP
     * $image->image_attach('/path/to/image.jpg', 'example.jpg');
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::_image_attach_internal()
     *         Защищённый метод, выполняющий основную логику.
     * @see    PhotoRigma::Classes::Work::image_attach()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    #[NoReturn] public function image_attach(string $full_path, string $name_file): void
    {
        $this->_image_attach_internal($full_path, $name_file);
    }

    /**
     * @brief   Вывод изображения через HTTP.
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Проверяет существование и доступность файла:
     *             - Если файл не существует или недоступен для чтения, отправляется HTTP-статус `404 Not Found`.
     *          2. Определяет MIME-тип файла с помощью `finfo_open(FILEINFO_MIME_TYPE)`:
     *             - Если MIME-тип не начинается с `image/`, отправляется HTTP-статус `500 Internal Server Error`.
     *          3. Проверяет размер файла:
     *             - Если размер превышает 10 МБ или неизвестен, отправляется HTTP-статус `413 Payload Too Large`.
     *          4. Устанавливает заголовки для вывода изображения:
     *             - `Content-Type`: MIME-тип файла.
     *             - `Content-Disposition`: Имя файла для заголовка.
     *             - `Content-Length`: Размер файла.
     *             - Дополнительные заголовки безопасности: `X-Content-Type-Options`, `X-Frame-Options`,
     *               `X-XSS-Protection`, `Referrer-Policy`, `Content-Security-Policy`.
     *          5. Открывает файл в режиме чтения и отправляет его содержимое через `fpassthru()`.
     *          6. Завершает выполнение скрипта после отправки заголовков и содержимого файла.
     *          Метод вызывается через публичный метод `image_attach()` и предназначен для использования внутри класса
     *          или его наследников.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $full_path Полный путь к файлу.
     *                          Путь должен быть абсолютным, и файл должен существовать и быть доступным для чтения.
     * @param string $name_file Имя файла для заголовка `Content-Disposition`.
     *                          Имя должно быть корректным (например, без запрещённых символов).
     *
     * @return void Метод ничего не возвращает. Завершает выполнение скрипта после отправки заголовков и содержимого
     *              файла.
     *
     * @throws Exception При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @note    Метод завершает выполнение скрипта (`exit`), отправляя заголовки и содержимое файла.
     *          Дополнительные заголовки безопасности:
     *          - `X-Content-Type-Options: nosniff`
     *          - `X-Frame-Options: DENY`
     *          - `X-XSS-Protection: 1; mode=block`
     *          - `Referrer-Policy: no-referrer`
     *          - `Content-Security-Policy: default-src 'self'; img-src 'self' data:;`
     *
     * @warning Метод завершает выполнение скрипта (`exit`), отправляя заголовки и содержимое файла.
     *          Убедитесь, что файл существует и доступен для чтения перед вызовом метода.
     *
     * Пример использования:
     * @code
     * // Вызов защищённого метода внутри класса или наследника
     * $this->_image_attach_internal('/path/to/image.jpg', 'example.jpg');
     * @endcode
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования ошибок.
     * @see    PhotoRigma::Classes::Work::clean_field()
     *         Метод для очистки имени файла.
     * @see    PhotoRigma::Classes::Work_Image::image_attach()
     *         Публичный метод, вызывающий этот защищённый метод.
     */
    #[NoReturn] protected function _image_attach_internal(string $full_path, string $name_file): void
    {
        // Проверяем существование и доступность файла
        if (!is_file($full_path) || !is_readable($full_path)) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл не найден или недоступен для чтения | Путь: $full_path"
            );
            header('HTTP/1.0 404 Not Found');
            exit;
        }
        // Получаем MIME-тип и размеры изображения через finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $full_path);
        finfo_close($finfo);
        if (!$mime_type || !str_starts_with($mime_type, 'image/')) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить MIME-тип изображения | Путь: $full_path"
            );
            header('HTTP/1.0 500 Internal Server Error');
            exit;
        }
        // Проверяем размер файла
        $file_size = filesize($full_path);
        if ($file_size === false || $file_size > 10 * 1024 * 1024) { // Ограничение: 10 МБ
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Размер файла слишком велик или неизвестен | Путь: $full_path, Размер: $file_size"
            );
            header('HTTP/1.0 413 Payload Too Large');
            exit;
        }
        // Устанавливаем заголовки для вывода изображения
        header('Content-Type: ' . $mime_type);
        header("Content-Disposition: inline; filename=\"" . Work::clean_field($name_file) . "\"");
        header('Content-Length: ' . $file_size);

        // Дополнительные заголовки для безопасности
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:;");

        flush();
        // Открываем файл и отправляем его содержимое
        $fh = fopen($full_path, 'rb');
        if ($fh === false) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось открыть файл для чтения | Путь: $full_path"
            );
            header('HTTP/1.0 500 Internal Server Error');
            exit;
        }
        fpassthru($fh);
        fclose($fh);
        exit;
    }

    /**
     * @brief   Изменяет размер изображения через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _image_resize_internal().
     *          Он изменяет размер исходного изображения и создаёт эскиз заданных размеров. Метод также доступен через
     *          метод-фасад `image_resize()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $full_path      Путь к исходному изображению:
     *                               - Путь должен быть абсолютным.
     *                               - Файл должен существовать и быть доступным для чтения.
     * @param string $thumbnail_path Путь для сохранения эскиза:
     *                               - Путь должен быть абсолютным.
     *                               - Директория должна быть доступна для записи.
     *
     * @return bool True, если операция выполнена успешно, иначе False.
     *
     * @throws InvalidArgumentException Если пути к файлам некорректны или имеют недопустимый формат.
     * @throws RuntimeException Если возникли ошибки при проверке файлов, директорий или размеров изображения.
     * @throws Exception При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @note    Метод использует следующие константы класса для ограничения размеров исходного изображения:
     *          - `MAX_IMAGE_WIDTH`: Максимальная ширина исходного изображения (в пикселях). Значение: 5000.
     *          - `MAX_IMAGE_HEIGHT`: Максимальная высота исходного изображения (в пикселях). Значение: 5000.
     *          Эти константы являются приватными и доступны только внутри класса.
     *
     * @warning Метод зависит от корректности данных в конфигурации (`temp_photo_w`, `temp_photo_h`).
     *          Если эти параметры некорректны, результат может быть непредсказуемым.
     *          Убедитесь, что пути к файлам и директориям корректны перед вызовом метода.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Image
     * $image = new \PhotoRigma\Classes\Work_Image();
     *
     * // Изменение размера изображения
     * $full_path = '/path/to/source_image.jpg';
     * $thumbnail_path = '/path/to/thumbnail.jpg';
     * $success = $image->image_resize($full_path, $thumbnail_path);
     *
     * if ($success) {
     *     echo "Эскиз успешно создан.";
     * } else {
     *     echo "Ошибка при создании эскиза.";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::_image_resize_internal()
     *         Защищённый метод, выполняющий основную логику.
     * @see    PhotoRigma::Classes::Work::image_resize()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public function image_resize(string $full_path, string $thumbnail_path): bool
    {
        return $this->_image_resize_internal($full_path, $thumbnail_path);
    }

    /**
     * @brief   Изменяет размер изображения.
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Проверяет корректность путей к исходному изображению и файлу эскиза с помощью `realpath()` и
     *             регулярного выражения `/^[a-zA-Z0-9\/\.\-_]+$/`.
     *          2. Проверяет существование и доступность исходного изображения для чтения (`file_exists`,
     *             `is_readable`).
     *          3. Проверяет доступность директории для сохранения эскиза для записи (`is_writable`).
     *          4. Получает размеры исходного изображения с помощью `getimagesize()` и проверяет их на соответствие
     *             максимальным значениям, заданным константами класса:
     *             - `MAX_IMAGE_WIDTH`: Максимальная ширина исходного изображения (в пикселях).
     *             - `MAX_IMAGE_HEIGHT`: Максимальная высота исходного изображения (в пикселях).
     *          5. Рассчитывает размеры эскиза с помощью метода `_calculate_thumbnail_size` на основе конфигурации
     *             (`temp_photo_w`, `temp_photo_h`).
     *          6. Если файл эскиза уже существует и его размеры совпадают с расчётными, метод завершает работу.
     *          7. Определяет MIME-тип исходного изображения с помощью `finfo_open(FILEINFO_MIME_TYPE)`.
     *          8. Выбирает библиотеку для обработки изображения в следующем порядке приоритета:
     *             - GraphicsMagick (если доступен),
     *             - ImageMagick (если доступен),
     *             - GD (если предыдущие библиотеки недоступны или вызвали ошибку).
     *          9. Логирует все ошибки через `log_in_file`.
     *          Метод вызывается через публичный метод `image_resize()` и предназначен для использования внутри класса
     *          или его наследников.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $full_path      Путь к исходному изображению.
     *                               Путь должен быть абсолютным, соответствовать регулярному выражению
     *                               `/^[a-zA-Z0-9\/\.\-_]+$/`, и файл должен существовать и быть доступным для чтения.
     * @param string $thumbnail_path Путь для сохранения эскиза.
     *                               Путь должен быть абсолютным, и директория должна быть доступна для записи.
     *
     * @return bool True, если операция выполнена успешно, иначе False.
     *
     * @throws InvalidArgumentException Если пути к файлам некорректны или имеют недопустимый формат.
     * @throws RuntimeException Если возникли ошибки при проверке файлов, директорий или размеров изображения.
     * @throws Exception При возникновении ошибок логирования информации через `log_in_file`.
     *
     * @note    Метод использует следующие константы класса для ограничения размеров исходного изображения:
     *          - `MAX_IMAGE_WIDTH`: Максимальная ширина исходного изображения (в пикселях). Значение: 5000.
     *          - `MAX_IMAGE_HEIGHT`: Максимальная высота исходного изображения (в пикселях). Значение: 5000.
     *          Эти константы являются приватными и доступны только внутри класса.
     *
     * @warning Метод зависит от корректности данных в конфигурации (`temp_photo_w`, `temp_photo_h`).
     *          Если эти параметры некорректны, результат может быть непредсказуемым.
     *          Убедитесь, что пути к файлам и директориям корректны перед вызовом метода.
     *
     * Пример использования:
     * @code
     * // Изменение размера изображения
     * $full_path = '/path/to/source_image.jpg';
     * $thumbnail_path = '/path/to/thumbnail.jpg';
     * $success = $this->_image_resize_internal($full_path, $thumbnail_path);
     * if ($success) {
     *     echo "Эскиз успешно создан.";
     * } else {
     *     echo "Ошибка при создании эскиза.";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::image_resize()
     *         Публичный метод, вызывающий этот защищённый метод.
     * @see    PhotoRigma::Classes::Work_Image::_calculate_thumbnail_size()
     *         Метод для расчёта размеров эскиза.
     * @see    PhotoRigma::Classes::Work_Image::_process_image_resize_gmagick()
     *         Обработка через GraphicsMagick.
     * @see    PhotoRigma::Classes::Work_Image::_process_image_resize_imagick()
     *         Обработка через ImageMagick.
     * @see    PhotoRigma::Classes::Work_Image::_process_image_resize_gd()
     *         Обработка через GD.
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования ошибок.
     */
    protected function _image_resize_internal(string $full_path, string $thumbnail_path): bool
    {
        // Нормализация путей через realpath()
        $full_path = realpath($full_path);
        if (!$full_path || !$thumbnail_path) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный путь к файлу | \$full_path = $full_path, \$thumbnail_path = $thumbnail_path"
            );
        }
        // Блок 1: Проверка корректности путей через filter_var
        $path_errors = [];
        foreach (['full_path' => $full_path, 'thumbnail_path' => $thumbnail_path] as $key => $path) {
            if (!filter_var($path, FILTER_VALIDATE_REGEXP, [
                'options' => ['regexp' => '/^[a-zA-Z0-9\/\.\-_]+$/'],
            ])) {
                $path_errors[] = "Некорректный формат пути: \$$key = $path.";
            }
        }
        if (!empty($path_errors)) {
            throw new InvalidArgumentException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Некорректный формат пути | Ошибки: ' . implode(
                    ' ',
                    $path_errors
                )
            );
        }
        // Блок 2: Проверка существования и доступности файлов
        if (!file_exists($full_path) || !is_readable($full_path)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Исходное изображение не найдено или недоступно для чтения | Путь: \$full_path = $full_path"
            );
        }
        // Проверка доступности директории для записи
        $thumbnail_dir = dirname($thumbnail_path);
        if (!is_writable($thumbnail_dir)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Директория для сохранения эскиза недоступна для записи | Путь: \$thumbnail_dir = $thumbnail_dir"
            );
        }
        $full_size = getimagesize($full_path);
        if ($full_size === false) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить размеры изображения | Путь: \$full_path = $full_path"
            );
        }
        if ($full_size[0] > self::MAX_IMAGE_WIDTH || $full_size[1] > self::MAX_IMAGE_HEIGHT) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Размеры исходного изображения слишком велики | Путь: \$full_path = $full_path, ширина = $full_size[0], высота = $full_size[1]"
            );
        }
        $thumbnail_exists = file_exists($thumbnail_path);
        if ($thumbnail_exists && !is_writable($thumbnail_path)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл эскиза существует, но недоступен для записи | Путь: \$thumbnail_path = $thumbnail_path"
            );
        }
        // Блок 3: Расчет размеров будущего эскиза
        $photo = $this->_calculate_thumbnail_size($full_size);
        // Если файл эскиза существует и его размеры совпадают с расчетными, завершаем работу
        if ($thumbnail_exists) {
            $thumbnail_size = getimagesize($thumbnail_path);
            if ($thumbnail_size !== false && $thumbnail_size[0] === $photo['width'] && $thumbnail_size[1] === $photo['height']) {
                return true;
            }
        }
        // Блок 4: Определение MIME-типа
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $full_path);
        finfo_close($finfo);
        // Блок 5: Подготовка данных для обработки изображения
        $original_data = [
            'path'   => $full_path,
            'type'   => $mime_type,
            'width'  => $full_size[0],
            'height' => $full_size[1],
        ];
        $thumbnail_data = [
            'path'   => $thumbnail_path,
            'width'  => $photo['width'],
            'height' => $photo['height'],
            'exists' => $thumbnail_exists,
        ];
        // Блок 6: Логика выбора библиотеки с fallback
        try {
            if (extension_loaded('gmagick')) {
                return $this->_process_image_resize_gmagick($original_data, $thumbnail_data);
            }
        } catch (Exception $e) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при обработке через GraphicsMagick | Аргументы: \$full_path = $full_path, \$thumbnail_path = $thumbnail_path | Сообщение об ошибке: {$e->getMessage()}"
            );
        }
        try {
            if (extension_loaded('imagick')) {
                return $this->_process_image_resize_imagick($original_data, $thumbnail_data);
            }
        } catch (Exception $e) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при обработке через ImageMagick | Аргументы: \$full_path = $full_path, \$thumbnail_path = $thumbnail_path | Сообщение об ошибке: {$e->getMessage()}"
            );
        }
        // Если ни одна из библиотек не сработала, используем GD
        return $this->_process_image_resize_gd($original_data, $thumbnail_data);
    }

    /**
     * @brief   Расчёт пропорциональных размеров эскиза на основе реальных размеров изображения и конфигурации.
     *
     * @details Этот метод выполняет следующие действия:
     *          1. Проверяет входной массив `$size`, содержащий реальные размеры изображения:
     *             - Ширина и высота должны быть положительными целыми числами.
     *          2. Проверяет конфигурацию (`temp_photo_w` и `temp_photo_h`):
     *             - Значения должны быть числами ≥ 0.
     *          3. Вычисляет коэффициенты масштабирования через оператор `match`:
     *             - Коэффициенты рассчитываются на основе реальных размеров и целевых размеров.
     *          4. Сохраняет пропорции изображения при масштабировании:
     *             - Если исходное изображение меньше целевого размера, возвращаются оригинальные размеры.
     *             - Иначе изображение масштабируется пропорционально до целевого размера.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @internal
     * @callergraph
     *
     * @param array $size Массив с реальными размерами изображения.
     *                    - Индекс 0: int — Ширина изображения (должна быть положительным целым числом).
     *                    - Индекс 1: int — Высота изображения (должна быть положительным целым числом).
     *
     * @return array Массив с шириной и высотой эскиза.
     *               - Ключ 'width': int — Ширина эскиза (целое число ≥ 0).
     *               - Ключ 'height': int — Высота эскиза (целое число ≥ 0).
     *
     * @throws InvalidArgumentException Если массив `$size` не содержит двух положительных целых чисел.
     *      Пример сообщения:
     *
     *          Некорректные размеры изображения.
     *          Требуется массив с двумя положительными целыми числами.
     *          Получено: width = 0, height = undefined
     *
     * @throws RuntimeException Если значения `temp_photo_w` или `temp_photo_h` в конфигурации некорректны.
     *      Пример сообщения:
     *
     *          Некорректная конфигурация temp_photo_w или temp_photo_h
     *          Значения должны быть числами ≥ 0.
     *          Получено: temp_photo_w = -100, temp_photo_h = undefined
     *
     * Пример вызова метода внутри класса:
     * @code
     * // Исходные размеры изображения
     * $originalSize = [1920, 1080];
     *
     * // Конфигурация приложения
     * $this->config['temp_photo_w'] = 800;
     * $this->config['temp_photo_h'] = 600;
     *
     * // Вычисление размеров эскиза
     * $thumbnailSize = $this->_calculate_thumbnail_size($originalSize);
     *
     * // Результат
     * echo "Ширина эскиза: {$thumbnailSize['width']}, Высота эскиза: {$thumbnailSize['height']}";
     * // Вывод: Ширина эскиза: 800, Высота эскиза: 450
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::size_image()
     *         Вычисляет размеры для вывода эскиза изображения.
     * @see    PhotoRigma::Classes::Work_Image::image_resize()
     *         Изменяет размер изображения.
     * @see    PhotoRigma::Classes::Work_Image::$config
     *         Массив с конфигурацией приложения.
     */
    private function _calculate_thumbnail_size(array $size): array
    {
        // Валидация параметра $size
        if (!isset($size[0], $size[1]) || !is_int($size[0]) || !is_int($size[1]) || $size[0] <= 0 || $size[1] <= 0) {
            throw new InvalidArgumentException(
                "[{__FILE__}:{__LINE__} ({__METHOD__ ?: __FUNCTION__ ?: 'global'})] | Некорректные размеры изображения | " . 'Требуется массив с двумя положительными целыми числами. Получено: width = ' . ($size[0] ?? 'undefined') . ', height = ' . ($size[1] ?? 'undefined')
            );
        }

        // Валидация конфигурации
        if (!isset($this->config['temp_photo_w'], $this->config['temp_photo_h']) || !is_numeric(
            $this->config['temp_photo_w']
        ) || !is_numeric(
            $this->config['temp_photo_h']
        ) || $this->config['temp_photo_w'] < 0 || $this->config['temp_photo_h'] < 0) {
            throw new RuntimeException(
                "[{__FILE__}:{__LINE__} ({__METHOD__ ?: __FUNCTION__ ?: 'global'})] | Некорректная конфигурация temp_photo_w или temp_photo_h | " . 'Значения должны быть числами ≥ 0. Получено: temp_photo_w = ' . ($this->config['temp_photo_w'] ?? 'undefined') . ', temp_photo_h = ' . ($this->config['temp_photo_h'] ?? 'undefined')
            );
        }

        // Преобразуем конфигурацию в целые числа
        $target_width = (int)$this->config['temp_photo_w'];
        $target_height = (int)$this->config['temp_photo_h'];

        // Вычисляем коэффициенты масштабирования через match
        $ratio_width = match (true) {
            $target_width > 0 => $size[0] / $target_width,
            default           => 1,
        };

        $ratio_height = match (true) {
            $target_height > 0 => $size[1] / $target_height,
            default            => 1,
        };

        // Если исходное изображение меньше целевого размера, возвращаем оригинальные размеры
        if ($size[0] < $target_width && $size[1] < $target_height && $target_width > 0 && $target_height > 0) {
            return [
                'width'  => $size[0],
                'height' => $size[1],
            ];
        }

        // Масштабируем изображение пропорционально
        if ($ratio_width < $ratio_height) {
            return [
                'width'  => (int)($size[0] / $ratio_height),
                'height' => (int)($size[1] / $ratio_height),
            ];
        }

        return [
            'width'  => (int)($size[0] / $ratio_width),
            'height' => (int)($size[1] / $ratio_width),
        ];
    }

    /**
     * @brief   Создаёт эскиз изображения с использованием библиотеки GraphicsMagick, включая резервное копирование
     *          старого эскиза и восстановление при ошибках.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет поддержку MIME-типа через массив разрешённых форматов.
     *          2. Преобразует MIME-тип в формат GraphicsMagick.
     *          3. Проверяет поддержку формата через `Gmagick::queryFormats()`.
     *          4. Ограничивает использование памяти до 25% от доступной.
     *          5. Создаёт резервную копию старого эскиза (если он существует).
     *          6. Загружает исходное изображение и создаёт новый эскиз заданных размеров:
     *             - Для форматов PNG и WebP настраивается прозрачность.
     *             - Используется фильтр `FILTER_LANCZOS` для высококачественного масштабирования.
     *          7. Сохраняет новый эскиз в указанный файл.
     *          8. Восстанавливает старый эскиз в случае ошибки.
     *          9. Освобождает ресурсы в блоке `finally`.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param array $original_data  Данные об оригинальном изображении:
     *                              - 'path' (string): Абсолютный путь к исходному файлу.
     *                              - 'type' (string): MIME-тип изображения (например, 'image/jpeg'). Должен быть
     *                                одним из разрешённых MIME-типов.
     *                              - 'width' (int):   Ширина исходного изображения (положительное число).
     *                              - 'height' (int):  Высота исходного изображения (положительное число).
     * @param array $thumbnail_data Данные об эскизе:
     *                              - 'path' (string): Абсолютный путь для сохранения эскиза.
     *                              - 'width' (int):   Желаемая ширина эскиза (положительное число).
     *                              - 'height' (int):  Желаемая высота эскиза (положительное число).
     *                              - 'exists' (bool): Существует ли файл эскиза (`true` или `false`).
     *
     * @return bool True, если эскиз создан успешно.
     *
     * @throws GmagickException Если возникает ошибка при работе с GraphicsMagick (например, при загрузке или обработке
     *                          изображения).
     * @throws Exception        Может быть выброшено вызываемой функцией `log_in_file` при записи ошибок в лог.
     *
     * @warning Метод чувствителен к правам доступа при работе с файлами (например, при переименовании или удалении
     *          файлов). Убедитесь, что скрипт имеет необходимые права на запись и чтение.
     * @warning Ограничение памяти (25% от доступной) может быть недостаточным для больших изображений.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $original_data = [
     *     'path' => '/path/to/original.jpg',
     *     'type' => 'image/jpeg',
     *     'width' => 1920,
     *     'height' => 1080,
     * ];
     * $thumbnail_data = [
     *     'path' => '/path/to/thumbnail.jpg',
     *     'width' => 300,
     *     'height' => 200,
     *     'exists' => false,
     * ];
     * $result = $this->_process_image_resize_gmagick($original_data, $thumbnail_data);
     * if ($result) {
     *     echo "Эскиз успешно создан!";
     * } else {
     *     echo "Не удалось создать эскиз.";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::_image_resize_internal()
     *         Метод, вызывающий этот приватный метод.
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования ошибок.
     */
    private function _process_image_resize_gmagick(array $original_data, array $thumbnail_data): bool
    {
        // Разрешённые MIME-типы и их соответствие форматам GraphicsMagick
        $gmagick_formats = [
            'image/jpeg'    => 'JPEG',
            'image/png'     => 'PNG',
            'image/gif'     => 'GIF',
            'image/webp'    => 'WEBP',
            'image/tiff'    => 'TIFF',
            'image/svg+xml' => 'SVG',
            'image/bmp'     => 'BMP',
            'image/x-icon'  => 'ICO', // Иконки
            'image/avif'    => 'AVIF', // Современный формат
            'image/heic'    => 'HEIC', // Формат с камер iPhone
        ];
        // Проверка MIME-типа
        if (!array_key_exists($original_data['type'], $gmagick_formats)) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неподдерживаемый MIME-тип для GraphicsMagick | Получено: {$original_data['type']}"
            );
            return false;
        }
        // Получение формата GraphicsMagick
        $format = $gmagick_formats[$original_data['type']];
        // Проверка поддержки формата через GraphicsMagick
        $gmagick_instance = new Gmagick(); // Создаем экземпляр Gmagick
        $supported_formats = $gmagick_instance->queryFormats(); // Вызываем метод queryFormats()
        unset($gmagick_instance);
        if (!in_array($format, $supported_formats, true)) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Формат $format не поддерживается GraphicsMagick"
            );
            return false;
        }
        // Ограничение памяти
        $available_memory = (int)ini_get('memory_limit') * 1024 * 1024; // Например, '128M' → 128 * 1024 * 1024
        $max_memory_usage = 0.25 * $available_memory; // 25% от доступной памяти
        $estimated_memory = $original_data['width'] * $original_data['height'] * 3;
        if ($estimated_memory > $max_memory_usage) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Изображение слишком велико для обработки через GraphicsMagick | Доступная память: ' . ini_get(
                    'memory_limit'
                )
            );
            return false;
        }
        // Создание резервной копии старого эскиза, если он существует
        if ($thumbnail_data['exists']) {
            $backup_thumbnail_path = tempnam(dirname($thumbnail_data['path']), 'bak_');
            if (!$backup_thumbnail_path || !rename($thumbnail_data['path'], $backup_thumbnail_path)) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Не удалось создать резервную копию старого эскиза через GraphicsMagick'
                );
                return false;
            }
        } else {
            $backup_thumbnail_path = null;
        }
        $image = null;
        try {
            // Создание объекта Gmagick
            $image = new Gmagick($original_data['path']);
            // Настройка прозрачности для PNG и WebP
            if ($original_data['type'] === 'image/png' || $original_data['type'] === 'image/webp') {
                $image->setImageFormat($format);
                $backgroundColor = new GmagickPixel('transparent'); // Создаем объект GmagickPixel с прозрачным цветом
                $image->setImageBackgroundColor($backgroundColor); // Установка прозрачного фона
            }
            // Масштабирование изображения
            $image->resizeImage(
                $thumbnail_data['width'],
                $thumbnail_data['height'],
                Gmagick::FILTER_LANCZOS, // Высококачественный фильтр
                1 // Коэффициент размытия (1 = без размытия)
            );
            // Сохранение нового эскиза
            $image->writeImage($thumbnail_data['path']);
            // Удаление резервной копии старого эскиза
            if ($backup_thumbnail_path && is_file($backup_thumbnail_path)) {
                unlink($backup_thumbnail_path);
            }
            return true;
        } catch (Exception $e) {
            // Логирование ошибки с контекстом
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при создании эскиза через GraphicsMagick | Оригинальный файл: {$original_data['path']}, Эскиз: {$thumbnail_data['path']}, Сообщение об ошибке: {$e->getMessage()}"
            );
            // Восстановление старого эскиза
            if ($backup_thumbnail_path && is_file($backup_thumbnail_path)) {
                rename($backup_thumbnail_path, $thumbnail_data['path']);
            }
            return false;
        } finally {
            // Освобождение ресурсов
            if (isset($image) && $image instanceof Gmagick) {
                $image->destroy();
            }
        }
    }

    /**
     * @brief   Создаёт эскиз изображения с использованием библиотеки ImageMagick, включая резервное копирование
     *          старого эскиза и восстановление при ошибках.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет поддержку MIME-типа через массив разрешённых форматов.
     *          2. Преобразует MIME-тип в формат ImageMagick.
     *          3. Проверяет поддержку формата через `Imagick::queryFormats()`.
     *          4. Ограничивает использование памяти до 25% от доступной.
     *          5. Создаёт резервную копию старого эскиза (если он существует).
     *          6. Загружает исходное изображение и создаёт новый эскиз заданных размеров:
     *             - Используется фильтр `FILTER_LANCZOS` для высококачественного масштабирования.
     *          7. Сохраняет новый эскиз в указанный файл.
     *          8. Восстанавливает старый эскиз в случае ошибки.
     *          9. Освобождает ресурсы в блоке `finally` через `$image->clear()`.
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param array $original_data  Данные об оригинальном изображении:
     *                              - 'path' (string): Абсолютный путь к исходному файлу.
     *                              - 'type' (string): MIME-тип изображения (например, 'image/jpeg'). Должен быть
     *                                одним из разрешённых MIME-типов.
     *                              - 'width' (int):   Ширина исходного изображения (положительное число).
     *                              - 'height' (int): Высота исходного изображения (положительное число).
     * @param array $thumbnail_data Данные об эскизе:
     *                              - 'path' (string): Абсолютный путь для сохранения эскиза.
     *                              - 'width' (int): Желаемая ширина эскиза (положительное число).
     *                              - 'height' (int): Желаемая высота эскиза (положительное число).
     *                              - 'exists' (bool): Существует ли файл эскиза (`true` или `false`).
     *
     * @return bool True, если эскиз создан успешно.
     *
     * @throws Exception Может быть выброшено вызываемой функцией `log_in_file` при записи ошибок в лог.
     *
     * @warning Метод чувствителен к правам доступа при работе с файлами (например, при переименовании или удалении
     *          файлов). Убедитесь, что скрипт имеет необходимые права на запись и чтение.
     * @warning Ограничение памяти (25% от доступной) может быть недостаточным для больших изображений.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $original_data = [
     *     'path' => '/path/to/original.jpg',
     *     'type' => 'image/jpeg',
     *     'width' => 1920,
     *     'height' => 1080,
     * ];
     * $thumbnail_data = [
     *     'path' => '/path/to/thumbnail.jpg',
     *     'width' => 300,
     *     'height' => 200,
     *     'exists' => false,
     * ];
     * $result = $this->_process_image_resize_imagick($original_data, $thumbnail_data);
     * if ($result) {
     *     echo "Эскиз успешно создан!";
     * } else {
     *     echo "Не удалось создать эскиз.";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::_image_resize_internal()
     *         Метод, вызывающий этот приватный метод.
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования ошибок.
     */
    private function _process_image_resize_imagick(array $original_data, array $thumbnail_data): bool
    {
        // Разрешённые MIME-типы и их соответствие форматам ImageMagick
        $imagick_formats = [
            'image/jpeg'                => 'JPEG',
            'image/png'                 => 'PNG',
            'image/gif'                 => 'GIF',
            'image/webp'                => 'WEBP',
            'image/tiff'                => 'TIFF',
            'image/svg+xml'             => 'SVG',
            'image/bmp'                 => 'BMP',
            'image/x-icon'              => 'ICO', // Иконки
            'image/avif'                => 'AVIF', // Современный формат
            'image/heic'                => 'HEIC', // Формат с камер iPhone
            'image/vnd.adobe.photoshop' => 'PSD', // Photoshop Document
            'image/x-canon-cr2'         => 'CR2', // RAW Canon
            'image/x-nikon-nef'         => 'NEF', // RAW Nikon
            'image/x-xbitmap'           => 'XBM', // X Bitmap
            'image/x-portable-anymap'   => 'PNM', // Portable Any Map
            'image/x-pcx'               => 'PCX', // PCX
        ];
        // Проверка MIME-типа
        if (!array_key_exists($original_data['type'], $imagick_formats)) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неподдерживаемый MIME-тип для ImageMagick | Получено: {$original_data['type']}"
            );
            return false;
        }
        // Получение формата ImageMagick
        $format = $imagick_formats[$original_data['type']];
        // Проверка поддержки формата через ImageMagick
        $imagick_instance = new Imagick(); // Создаем экземпляр Imagick
        $supported_formats = Imagick::queryFormats(); // Вызываем метод queryFormats()
        unset($imagick_instance);
        if (!in_array($format, $supported_formats, true)) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Формат $format не поддерживается ImageMagick"
            );
            return false;
        }
        // Ограничение памяти
        $available_memory = (int)ini_get('memory_limit') * 1024 * 1024; // Например, '128M' → 128 * 1024 * 1024
        $max_memory_usage = 0.25 * $available_memory; // 25% от доступной памяти
        $estimated_memory = $original_data['width'] * $original_data['height'] * 3;
        if ($estimated_memory > $max_memory_usage) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Изображение слишком велико для обработки через ImageMagick | Доступная память: ' . ini_get(
                    'memory_limit'
                )
            );
            return false;
        }
        // Создание резервной копии старого эскиза, если он существует
        if ($thumbnail_data['exists']) {
            $backup_thumbnail_path = tempnam(dirname($thumbnail_data['path']), 'bak_');
            if (!$backup_thumbnail_path || !rename($thumbnail_data['path'], $backup_thumbnail_path)) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Не удалось создать резервную копию старого эскиза через ImageMagick'
                );
                return false;
            }
        } else {
            $backup_thumbnail_path = null;
        }
        $image = null;
        try {
            // Создание объекта Imagick
            $image = new Imagick($original_data['path']);
            // Масштабирование изображения
            $image->resizeImage(
                $thumbnail_data['width'],
                $thumbnail_data['height'],
                Imagick::FILTER_LANCZOS, // Высококачественный фильтр
                1 // Коэффициент размытия (1 = без размытия)
            );
            // Сохранение нового эскиза
            $image->writeImage($thumbnail_data['path']);
            // Удаление резервной копии старого эскиза
            if ($backup_thumbnail_path && is_file($backup_thumbnail_path)) {
                unlink($backup_thumbnail_path);
            }
            return true;
        } catch (Exception $e) {
            // Логирование ошибки
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при создании эскиза через ImageMagick | Оригинальный файл: {$original_data['path']}, Эскиз: {$thumbnail_data['path']}, Сообщение об ошибке: {$e->getMessage()}"
            );
            // Восстановление старого эскиза
            if ($backup_thumbnail_path && is_file($backup_thumbnail_path)) {
                rename($backup_thumbnail_path, $thumbnail_data['path']);
            }
            return false;
        } finally {
            // Освобождение ресурсов
            if (isset($image) && $image instanceof Imagick) {
                $image->clear();
            }
        }
    }

    /**
     * @brief   Создаёт эскиз изображения с использованием библиотеки GD, включая резервное копирование старого эскиза
     *          и восстановление при ошибках.
     *
     * @details Этот метод выполняет следующие шаги:
     *          1. Проверяет поддержку MIME-типа через массив разрешённых форматов.
     *          2. Преобразует MIME-тип в константу IMAGETYPE_*.
     *          3. Проверяет поддержку формата через функцию imagetypes().
     *          4. Ограничивает использование памяти до 25% от доступной.
     *          5. Создаёт резервную копию старого эскиза (если он существует).
     *          6. Загружает исходное изображение и создаёт новый эскиз заданных размеров:
     *             - Для форматов PNG и WebP настраивается прозрачность.
     *             - Используется функция imagecopyresampled для высококачественного масштабирования.
     *          7. Сохраняет новый эскиз в указанный файл.
     *          8. Восстанавливает старый эскиз в случае ошибки.
     *          9. Освобождает ресурсы в блоке finally через imagedestroy().
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @internal
     * @callergraph
     * @callgraph
     *
     * @param array $original_data  Данные об оригинальном изображении:
     *                              - 'path' (string): Абсолютный путь к исходному файлу.
     *                              - 'type' (string): MIME-тип изображения (например, 'image/jpeg'). Должен быть
     *                                одним из разрешённых MIME-типов.
     *                              - 'width' (int):   Ширина исходного изображения (положительное число).
     *                              - 'height' (int):  Высота исходного изображения (положительное число).
     * @param array $thumbnail_data Данные об эскизе:
     *                              - 'path' (string): Абсолютный путь для сохранения эскиза.
     *                              - 'width' (int):   Желаемая ширина эскиза (положительное число).
     *                              - 'height' (int):  Желаемая высота эскиза (положительное число).
     *                              - 'exists' (bool): Существует ли файл эскиза (`true` или `false`).
     *
     * @return bool True, если эскиз создан успешно.
     *
     * @throws InvalidArgumentException Если MIME-тип или тип изображения не поддерживаются.
     *                                   Пример сообщения:
     *                                      Неподдерживаемый MIME-тип для GD | Получено: [значение]
     * @throws RuntimeException         Если возникает ошибка при работе с GD (например, при загрузке, обработке или
     *                                  сохранении изображения).
     *                                   Пример сообщения:
     *                                      Не удалось создать ресурс изображения через GD | Путь: [значение]
     * @throws Exception                Может быть выброшено вызываемой функцией `log_in_file` при записи ошибок в лог.
     *
     * @warning Метод чувствителен к правам доступа при работе с файлами (например, при переименовании или удалении
     *          файлов). Убедитесь, что скрипт имеет необходимые права на запись и чтение.
     * @warning Ограничение памяти (25% от доступной) может быть недостаточным для больших изображений.
     * @warning Поддержка форматов зависит от текущей версии GD. Например, WebP может быть недоступен.
     *
     * Пример вызова метода внутри класса:
     * @code
     * $original_data = [
     *     'path' => '/path/to/original.jpg',
     *     'type' => 'image/jpeg',
     *     'width' => 1920,
     *     'height' => 1080,
     * ];
     * $thumbnail_data = [
     *     'path' => '/path/to/thumbnail.jpg',
     *     'width' => 300,
     *     'height' => 200,
     *     'exists' => false,
     * ];
     * $result = $this->_process_image_resize_gd($original_data, $thumbnail_data);
     * if ($result) {
     *     echo "Эскиз успешно создан!";
     * } else {
     *     echo "Не удалось создать эскиз.";
     * }
     * @endcode
     * @see    PhotoRigma::Include::log_in_file()
     *         Функция для логирования ошибок.
     * @see    PhotoRigma::Classes::Work_Image::_image_resize_internal()
     *         Метод, вызывающий этот приватный метод.
     */
    private function _process_image_resize_gd(array $original_data, array $thumbnail_data): bool
    {
        // Разрешённые MIME-типы и их соответствие константам IMAGETYPE_*
        $gd_mime_to_type = [
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png'  => IMAGETYPE_PNG,
            'image/gif'  => IMAGETYPE_GIF,
            'image/webp' => IMAGETYPE_WEBP,
        ];
        // Проверка MIME-типа
        if (!array_key_exists($original_data['type'], $gd_mime_to_type)) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неподдерживаемый MIME-тип для GD | Получено: {$original_data['type']}"
            );
            return false;
        }
        // Преобразование MIME-типа в константу IMAGETYPE_*
        $image_type = $gd_mime_to_type[$original_data['type']];
        // Проверка поддержки формата через imagetypes()
        if (!($image_type === IMAGETYPE_GIF && (imagetypes(
        ) & IMG_GIF)) && !($image_type === IMAGETYPE_JPEG && (imagetypes(
        ) & IMG_JPG)) && !($image_type === IMAGETYPE_PNG && (imagetypes(
        ) & IMG_PNG)) && !($image_type === IMAGETYPE_WEBP && (imagetypes() & IMG_WEBP))) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Формат {$original_data['type']} не поддерживается текущей версией GD"
            );
            return false;
        }
        // Ограничение памяти
        $available_memory = (int)ini_get('memory_limit') * 1024 * 1024; // Например, '128M' → 128 * 1024 * 1024
        $max_memory_usage = 0.25 * $available_memory; // 25% от доступной памяти
        $estimated_memory = $original_data['width'] * $original_data['height'] * 3;
        if ($estimated_memory > $max_memory_usage) {
            log_in_file(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Изображение слишком велико для обработки через GD | Доступная память: ' . ini_get(
                    'memory_limit'
                )
            );
            return false;
        }
        // Создание резервной копии старого эскиза, если он существует
        if ($thumbnail_data['exists']) {
            $backup_thumbnail_path = tempnam(dirname($thumbnail_data['path']), 'bak_');
            if (!$backup_thumbnail_path || !rename($thumbnail_data['path'], $backup_thumbnail_path)) {
                log_in_file(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Не удалось создать резервную копию старого эскиза через GD'
                );
                return false;
            }
        } else {
            $backup_thumbnail_path = null;
        }
        $imorig = null;
        $im = null;
        try {
            // Создание исходного изображения
            $imorig = match ($image_type) {
                IMAGETYPE_GIF  => imagecreatefromgif($original_data['path']),
                IMAGETYPE_JPEG => imagecreatefromjpeg($original_data['path']),
                IMAGETYPE_PNG  => imagecreatefrompng($original_data['path']),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp(
                    $original_data['path']
                ) : null,
                default        => throw new InvalidArgumentException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неподдерживаемый тип изображения для GD | Путь: {$original_data['path']}"
                ),
            };
            if (!$imorig) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось создать ресурс изображения через GD | Путь: {$original_data['path']}"
                );
            }
            // Создание нового изображения для эскиза
            $im = imagecreatetruecolor($thumbnail_data['width'], $thumbnail_data['height']);
            if (!$im) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Не удалось создать полноцветное изображение для эскиза через GD'
                );
            }
            // Настройка прозрачности для PNG и WebP
            if ($image_type === IMAGETYPE_PNG || $image_type === IMAGETYPE_WEBP) {
                imagealphablending($im, false); // Отключаем смешивание цветов
                imagesavealpha($im, true);      // Сохраняем альфа-канал
            }
            // Копируем и масштабируем изображение
            if (!imagecopyresampled(
                $im,
                $imorig,
                0,
                0,
                0,
                0,
                $thumbnail_data['width'],
                $thumbnail_data['height'],
                $original_data['width'],
                $original_data['height']
            )) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось масштабировать изображение через GD | Путь: {$original_data['path']}"
                );
            }
            // Сохраняем новый эскиз
            $save_function = match ($image_type) {
                IMAGETYPE_GIF  => 'imagegif',
                IMAGETYPE_JPEG => 'imagejpeg',
                IMAGETYPE_PNG  => 'imagepng',
                IMAGETYPE_WEBP => function_exists('imagewebp') ? 'imagewebp' : throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ') | Функция imagewebp недоступна в текущей версии GD'
                ),
                default        => throw new InvalidArgumentException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неподдерживаемый тип изображения для GD | Путь: {$original_data['path']}"
                ),
            };
            if (!function_exists($save_function)) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Функция $save_function недоступна в текущей версии GD"
                );
            }
            $save_function($im, $thumbnail_data['path']);
            // Удаление резервной копии старого эскиза
            if ($backup_thumbnail_path && is_file($backup_thumbnail_path)) {
                unlink($backup_thumbnail_path);
            }
            return true;
        } catch (Exception $e) {
            // Восстановление старого эскиза
            if ($backup_thumbnail_path && is_file($backup_thumbnail_path)) {
                rename($backup_thumbnail_path, $thumbnail_data['path']);
            }
            // Выбрасываем исключение с подробным сообщением
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при создании эскиза через GD | Оригинальный файл: {$original_data['path']}, Эскиз: {$thumbnail_data['path']}, Сообщение об ошибке: {$e->getMessage()}"
            );
        } finally {
            // Освобождение ресурсов
            if (isset($imorig) && is_resource($imorig)) {
                imagedestroy($imorig);
            }
            if (isset($im) && is_resource($im)) {
                imagedestroy($im);
            }
        }
    }

    /**
     * @brief   Возвращает данные для отсутствующего изображения через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _no_photo_internal().
     *          Он формирует массив данных для представления информации об отсутствующем изображении. Метод также
     *          доступен через метод-фасад `no_photo()` в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @return array Массив данных об изображении или его отсутствии:
     *               - 'url'                  (string): URL полноразмерного изображения.
     *               - 'thumbnail_url'        (string): URL эскиза изображения.
     *               - 'name'                 (string): Название изображения. Значение по умолчанию: `'No photo'`.
     *               - 'description'          (string): Описание изображения. Значение по умолчанию: `'No photo
     *                                                  available'`.
     *               - 'category_name'        (string): Название категории. Значение по умолчанию: `'No category'`.
     *               - 'category_description' (string): Описание категории. Значение по умолчанию: `'No category
     *                                                  available'`.
     *               - 'rate'                 (string): Рейтинг изображения. Значение по умолчанию: `'Rate: 0/0'`.
     *               - 'url_user'             (string): URL пользователя. Значение по умолчанию: пустая строка (`''`).
     *               - 'real_name'            (string): Имя пользователя. Значение по умолчанию: `'No user'`.
     *               - 'full_path'            (string): Полный путь к изображению. Формируется на основе конфигурации.
     *               - 'thumbnail_path'       (string): Полный путь к эскизу. Формируется на основе конфигурации.
     *               - 'file'                 (string): Имя файла. Значение по умолчанию: `'no_foto.png'`.
     *               Значения по умолчанию используются для отсутствующих данных.
     *
     * @warning Метод зависит от корректности данных в конфигурации (`site_url`, `site_dir`, `gallery_folder`,
     *          `thumbnail_folder`). Если эти параметры некорректны, результат может быть непредсказуемым. Вызывать
     *          этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Image
     * $image = new \PhotoRigma\Classes\Work_Image();
     *
     * // Получение данных для отсутствующего изображения
     * $noPhotoData = $image->no_photo();
     *
     * // Вывод данных
     * echo "URL изображения: {$noPhotoData['url']}\n";
     * echo "Описание: {$noPhotoData['description']}\n";
     * echo "Категория: {$noPhotoData['category_name']} - {$noPhotoData['category_description']}\n";
     * echo "Рейтинг: {$noPhotoData['rate']}\n";
     * echo "Пользователь: {$noPhotoData['real_name']}\n";
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::_no_photo_internal()
     *         Защищённый метод, выполняющий основную логику.
     * @see    PhotoRigma::Classes::Work::no_photo()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public function no_photo(): array
    {
        return $this->_no_photo_internal();
    }

    /**
     * @brief   Возвращает данные для отсутствующего изображения.
     *
     * @details Этот защищённый метод формирует массив данных, который используется для представления информации
     *          об отсутствующем изображении. Это может быть полезно, например, если изображение не найдено или
     *          недоступно. Метод использует конфигурацию приложения для формирования URL-адресов и путей:
     *          - `site_url`: Базовый URL сайта.
     *          - `site_dir`: Базовая директория сайта.
     *          - `gallery_folder`: Директория для хранения изображений.
     *          - `thumbnail_folder`: Директория для хранения эскизов.
     *          Массив содержит предопределённые значения для всех ключей, таких как `'url'`, `'thumbnail_url'`,
     *          `'name'`, `'description'` и другие. Значения по умолчанию используются для отсутствующих данных.
     *          Этот метод является защищённым и предназначен для использования внутри класса или его наследников.
     *          Основная логика метода вызывается через публичный метод `no_photo()`.
     *
     * @callergraph
     *
     * @return array Массив данных об изображении или его отсутствии:
     *               - 'url'                  (string): URL полноразмерного изображения.
     *               - 'thumbnail_url'        (string): URL эскиза изображения.
     *               - 'name'                 (string): Название изображения. Значение по умолчанию: `'No photo'`.
     *               - 'description'          (string): Описание изображения. Значение по умолчанию: `'No photo
     *                                                  available'`.
     *               - 'category_name'        (string): Название категории. Значение по умолчанию: `'No category'`.
     *               - 'category_description' (string): Описание категории. Значение по умолчанию: `'No category
     *                                                  available'`.
     *               - 'rate'                 (string): Рейтинг изображения. Значение по умолчанию: `'Rate: 0/0'`.
     *               - 'url_user'             (string): URL пользователя. Значение по умолчанию: пустая строка (`''`).
     *               - 'real_name'            (string): Имя пользователя. Значение по умолчанию: `'No user'`.
     *               - 'full_path'            (string): Полный путь к изображению. Формируется на основе конфигурации.
     *               - 'thumbnail_path'       (string): Полный путь к эскизу. Формируется на основе конфигурации.
     *               - 'file'                 (string): Имя файла. Значение по умолчанию: `'no_foto.png'`.
     *
     * @warning Метод зависит от корректности данных в конфигурации (`site_url`, `site_dir`, `gallery_folder`,
     *          `thumbnail_folder`). Если эти параметры некорректны, результат может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Вызов защищённого метода внутри класса или наследника
     * $noPhotoData = $this->_no_photo_internal();
     * echo "URL изображения: {$noPhotoData['url']}\n";
     * echo "Описание: {$noPhotoData['description']}\n";
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::$config
     *         Свойство конфигурации проекта в классе.
     * @see    PhotoRigma::Classes::Work_Image::no_photo()
     *         Публичный метод, вызывающий этот защищённый метод.
     */
    protected function _no_photo_internal(): array
    {
        return [
            'url'                  => sprintf('%s?action=photo&id=0', SITE_URL),
            'thumbnail_url'        => sprintf('%s?action=attach&foto=0&thumbnail=1', SITE_URL),
            'name'                 => 'No photo',
            'description'          => 'No photo available',
            'category_name'        => 'No category',
            'category_description' => 'No category available',
            'rate'                 => 'Rate: 0/0',
            'url_user'             => '',
            'real_name'            => 'No user',
            'full_path'            => GALLERY_DIR . '/no_foto.png',
            'thumbnail_path'       => THUMBNAIL_DIR . '/no_foto.png',
            'file'                 => 'no_foto.png',
        ];
    }

    /**
     * @brief   Удаляет директорию и её содержимое через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _remove_directory_internal().
     *          Он удаляет директорию и всё её содержимое. Метод также доступен через метод-фасад `remove_directory()`
     *          в родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $path Путь к директории:
     *                     - Должен быть строкой, указывающей на существующую директорию.
     *                     - Директория должна быть доступна для записи.
     *
     * @return bool Возвращает `true`, если директория успешно удалена.
     *
     * @throws RuntimeException Выбрасывается исключение в следующих случаях:
     *                           - Если директория не существует.
     *                           - Если директория недоступна для записи.
     *                           - Если не удалось удалить файл внутри директории.
     *                           - Если не удалось удалить саму директорию.
     *
     * @note    Метод рекурсивно удаляет все файлы внутри директории.
     *
     * @warning Используйте этот метод с осторожностью, так как удаление директории необратимо.
     *          Убедитесь, что переданная директория действительно должна быть удалена.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Image
     * $image = new \PhotoRigma\Classes\Work_Image();
     *
     * // Удаление директории
     * $path = '/path/to/directory';
     * $result = $image->remove_directory($path);
     * if ($result) {
     *     echo "Директория успешно удалена!";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::_remove_directory_internal()
     *         Защищённый метод, выполняющий основную логику удаления.
     * @see    PhotoRigma::Classes::Work::remove_directory()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public function remove_directory(string $path): bool
    {
        return $this->_remove_directory_internal($path);
    }

    /**
     * @brief   Удаляет директорию и её содержимое, предварительно проверяя права доступа.
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Проверяет существование указанной директории с помощью `is_dir`.
     *          2. Проверяет права доступа к директории (должна быть доступна для записи) с помощью `is_writable`.
     *          3. Рекурсивно удаляет все файлы внутри директории:
     *             - Получает список файлов с помощью `glob`.
     *             - Удаляет каждый файл с помощью `unlink`.
     *          4. Удаляет саму директорию с помощью `rmdir`.
     *          Метод вызывается через публичный метод-редирект `PhotoRigma::Classes::Work_Image::remove_directory()`
     *          и предназначен для использования внутри класса или его наследников.
     *
     * @callergraph
     *
     * @param string $path Путь к директории.
     *                     Должен быть строкой, указывающей на существующую директорию.
     *                     Директория должна быть доступна для записи.
     *
     * @return bool Возвращает `true`, если директория успешно удалена.
     *
     * @throws RuntimeException Выбрасывается исключение в следующих случаях:
     *                           - Если директория не существует.
     *                           - Если директория недоступна для записи.
     *                           - Если не удалось удалить файл внутри директории.
     *                           - Если не удалось удалить саму директорию.
     *
     * @note    Метод рекурсивно удаляет все файлы внутри директории.
     *
     * @warning Используйте этот метод с осторожностью, так как удаление директории необратимо.
     *          Убедитесь, что переданная директория действительно должна быть удалена.
     *
     * Пример вызова метода внутри класса или наследника:
     * @code
     * // Удаление директории
     * $path = '/path/to/directory';
     * $result = $this->_remove_directory_internal($path);
     * if ($result) {
     *     echo "Директория успешно удалена!";
     * }
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::remove_directory()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _remove_directory_internal(string $path): bool
    {
        if (!is_dir($path)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Директория не существует | Путь: $path"
            );
        }

        if (!is_writable($path)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Директория недоступна для записи | Путь: $path"
            );
        }

        foreach (glob($path . '/*', GLOB_NOSORT) as $file) {
            if (is_file($file) && !unlink($file)) {
                throw new RuntimeException(
                    __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось удалить файл | Путь: $file"
                );
            }
        }

        if (!rmdir($path)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось удалить директорию | Путь: $path"
            );
        }

        return true;
    }

    /**
     * @brief   Вычисляет размеры для вывода эскиза изображения через вызов внутреннего метода.
     *
     * @details Этот публичный метод является обёрткой для защищённого метода _size_image_internal().
     *          Он рассчитывает ширину и высоту эскиза на основе реальных размеров изображения и конфигурационных
     *          параметров (`temp_photo_w` и `temp_photo_h`). Метод также доступен через метод-фасад `size_image()` в
     *          родительском классе.
     *
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *          В противном случае поведение метода может быть непредсказуемым.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $path_image Путь к файлу изображения:
     *                           - Путь должен быть абсолютным.
     *                           - Файл должен существовать и быть доступным для чтения.
     *
     * @return array Массив с шириной и высотой эскиза:
     *               - Ключ 'width': int — Ширина эскиза (целое число ≥ 0).
     *               - Ключ 'height': int — Высота эскиза (целое число ≥ 0).
     *               Размеры могут совпадать с оригинальными размерами изображения,
     *               если оно меньше целевого размера.
     *
     * @throws RuntimeException Если файл не существует или не удалось получить размеры изображения.
     *
     * @warning Метод зависит от корректности данных в конфигурации (`temp_photo_w` и `temp_photo_h`).
     *          Если эти параметры некорректны, результат может быть непредсказуемым.
     *          Вызывать этот метод напрямую, минуя метод-фасад родительского класса, крайне не рекомендуется.
     *
     * Пример использования:
     * @code
     * // Создание экземпляра класса Work_Image
     * $image = new \PhotoRigma\Classes\Work_Image();
     *
     * // Вычисление размеров эскиза для изображения
     * $path = '/path/to/image.jpg';
     * $sizes = $image->size_image($path);
     *
     * // Вывод результатов
     * echo "Ширина: {$sizes['width']}, Высота: {$sizes['height']}";
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::_size_image_internal()
     *         Защищённый метод, выполняющий основную логику.
     * @see    PhotoRigma::Classes::Work::size_image()
     *         Метод-фасад в родительском классе для вызова этой логики.
     */
    public function size_image(string $path_image): array
    {
        return $this->_size_image_internal($path_image);
    }

    /**
     * @brief   Вычисляет размеры для вывода эскиза изображения.
     *
     * @details Этот защищённый метод выполняет следующие действия:
     *          1. Проверяет существование файла с помощью `file_exists`.
     *          2. Получает реальные размеры изображения с помощью `getimagesize`.
     *          3. Вычисляет ширину и высоту эскиза на основе реальных размеров изображения и конфигурационных
     *          параметров:
     *             - `temp_photo_w`: Целевая ширина эскиза (в пикселях).
     *             - `temp_photo_h`: Целевая высота эскиза (в пикселях).
     *          4. Если изображение меньше целевого размера, возвращаются оригинальные размеры.
     *          5. В противном случае размеры масштабируются пропорционально.
     *          Расчёт размеров выполняется с использованием метода `_calculate_thumbnail_size`.
     *          Этот метод является защищённым и предназначен для использования внутри класса или его наследников.
     *          Основная логика метода вызывается через публичный метод `size_image()`.
     *
     * @callergraph
     * @callgraph
     *
     * @param string $path_image Путь к файлу изображения.
     *                           Путь должен быть абсолютным, и файл должен существовать и быть доступным для чтения.
     *
     * @return array Массив с шириной и высотой эскиза:
     *               - Ключ `'width'` (int): Ширина эскиза (целое число ≥ 0).
     *               - Ключ `'height'` (int): Высота эскиза (целое число ≥ 0).
     *               Размеры могут совпадать с оригинальными размерами изображения, если оно меньше целевого размера.
     *
     * @throws RuntimeException Выбрасывается исключение в следующих случаях:
     *                           - Если файл не существует.
     *                           - Если не удалось получить размеры изображения.
     *
     * @warning Метод зависит от корректности данных в конфигурации (`temp_photo_w` и `temp_photo_h`).
     *          Если эти параметры некорректны, результат может быть непредсказуемым.
     *
     * Пример использования:
     * @code
     * // Вычисление размеров эскиза для изображения
     * $path = '/path/to/image.jpg';
     * $sizes = $this->_size_image_internal($path);
     * echo "Ширина: {$sizes['width']}, Высота: {$sizes['height']}";
     * @endcode
     * @see    PhotoRigma::Classes::Work_Image::_calculate_thumbnail_size()
     *         Метод, используемый для расчёта размера эскиза.
     * @see    PhotoRigma::Classes::Work_Image::size_image()
     *         Публичный метод-редирект для вызова этой логики.
     */
    protected function _size_image_internal(string $path_image): array
    {
        // Проверяем существование файла
        if (!file_exists($path_image)) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл не найден | Путь: '$path_image'"
            );
        }
        // Получаем размеры изображения
        $size = getimagesize($path_image);
        if ($size === false) {
            throw new RuntimeException(
                __FILE__ . ':' . __LINE__ . ' (' . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить размеры изображения | Путь: '$path_image'"
            );
        }
        // Используем готовый метод для расчёта размеров эскиза
        return $this->_calculate_thumbnail_size($size);
    }
}
