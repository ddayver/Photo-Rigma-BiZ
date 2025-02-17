<?php

/**
 * @file        include/work_image.php
 * @brief       Файл содержит класс Work_Image, который отвечает за работу с изображениями.
 *
 * @author      Dark Dayver
 * @version     0.4.0
 * @date        2025-02-12
 * @namespace   PhotoRigma\\Classes
 *
 * @details     Этот файл содержит класс `Work_Image`, который реализует интерфейс `Work_Image_Interface`.
 *              Класс предоставляет методы для работы с изображениями, включая изменение размеров, вывод через HTTP,
 *              обработку отсутствующих изображений, корректировку расширения файла в соответствии с его MIME-типом
 *              и логирование ошибок. Публичные методы класса являются редиректами
 *              на защищённые методы с суффиксом `_internal`, что обеспечивает более гибкую архитектуру (паттерн "фасад").
 *
 * @see         PhotoRigma::Classes::Work_Image_Interface Интерфейс для работы с изображениями.
 * @see         PhotoRigma::Classes::Work Класс, через который вызываются методы для работы с изображениями.
 * @see         PhotoRigma::Classes::Work::validate_mime_type() Метод для проверки MIME-типов изображений.
 * @see         index.php Файл, который подключает work_image.php.
 *
 * @note        Этот файл является частью системы PhotoRigma и играет ключевую роль в обработке изображений.
 *              Класс поддерживает работу с библиотеками GraphicsMagick, ImageMagick и GD для изменения размеров изображений.
 *
 * @copyright   Copyright (c) 2025 Dark Dayver. Все права защищены.
 * @license     MIT License (https://opensource.org/licenses/MIT)
 *              Разрешается использовать, копировать, изменять, объединять, публиковать, распространять, сублицензировать
 *              и/или продавать копии программного обеспечения, а также разрешать лицам, которым предоставляется данное
 *              программное обеспечение, делать это при соблюдении следующих условий:
 *              - Уведомление об авторских правах и условия лицензии должны быть включены во все копии или значимые части
 *                программного обеспечения.
 */

namespace PhotoRigma\Classes;

// Предотвращение прямого вызова файла
if (!defined('IN_GALLERY') || IN_GALLERY !== true) {
    error_log(
        date('H:i:s') .
        " [ERROR] | " .
        (filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP) ?: 'UNKNOWN_IP') .
        " | " . __FILE__ . " | Попытка прямого вызова файла"
    );
    die("HACK!");
}

/**
 * @brief Интерфейс для работы с изображениями.
 *
 * @details Интерфейс определяет методы для выполнения операций с изображениями:
 *          - Вычисление размеров эскизов.
 *          - Изменение размеров изображений.
 *          - Обработка отсутствующих изображений.
 *          - Вывод изображений через HTTP.
 *          - Корректировка расширения файла в соответствии с его MIME-типом.
 *
 * @see PhotoRigma::Classes::Work_Image Класс, реализующий данный интерфейс.
 */
interface Work_Image_Interface
{
    /**
     * @brief Вычисляет размеры для вывода эскиза изображения.
     *
     * @details Метод рассчитывает ширину и высоту эскиза на основе реальных размеров изображения
     * и конфигурационных параметров (`temp_photo_w` и `temp_photo_h`). Если изображение меньше
     * целевого размера, возвращаются оригинальные размеры. В противном случае размеры масштабируются
     * пропорционально.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $path = 'path/to/image.jpg';
     * $thumbnail_size = $work->size_image($path);
     * echo "Ширина: {$thumbnail_size['width']}, Высота: {$thumbnail_size['height']}";
     * @endcode
     *
     * @param string $path_image Путь к файлу изображения.
     *
     * @return array{
     *     width: int,  // Ширина эскиза.
     *     height: int  // Высота эскиза.
     * } Массив с шириной и высотой эскиза.
     *
     * @throws RuntimeException Если файл не существует или не удалось получить размеры изображения.
     */
    public function size_image(string $path_image): array;

    /**
     * @brief Изменяет размер изображения.
     *
     * @details Метод изменяет размер исходного изображения и создаёт эскиз заданных размеров.
     * Размеры эскиза рассчитываются на основе конфигурации (`temp_photo_w`, `temp_photo_h`)
     * с использованием метода `calculate_thumbnail_size`. Если файл эскиза уже существует
     * и его размеры совпадают с рассчитанными, метод завершает работу без изменений.
     * В противном случае создаётся новый эскиз с использованием одной из доступных библиотек:
     * GraphicsMagick, ImageMagick или GD (в порядке приоритета).
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work(['temp_photo_w' => 800, 'temp_photo_h' => 600]);
     * $result = $work->image_resize('/path/to/full_image.jpg', '/path/to/thumbnail.jpg');
     * if ($result) {
     *     echo "Эскиз успешно создан или обновлён!";
     * }
     * @endcode
     *
     * @param string $full_path Путь к исходному изображению.
     * @param string $thumbnail_path Путь для сохранения эскиза.
     *
     * @return bool True, если операция выполнена успешно, иначе False.
     *
     * @throws InvalidArgumentException Если пути к файлам некорректны или имеют недопустимый формат.
     * @throws RuntimeException Если возникли ошибки при проверке файлов, директорий или размеров изображения.
     */
    public function image_resize(string $full_path, string $thumbnail_path): bool;

    /**
     * @brief Возвращает данные для отсутствующего изображения.
     *
     * @details Метод формирует массив данных, который используется для представления информации
     * об отсутствующем изображении. Это может быть полезно, например, если изображение не найдено
     * или недоступно. Метод использует конфигурацию приложения (`site_url`) для формирования URL-адресов.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work(['site_url' => 'https://example.com']);
     * $noPhotoData = $work->no_photo();
     * echo "URL изображения: {$noPhotoData['url']}\n";
     * echo "Описание: {$noPhotoData['description']}\n";
     * @endcode
     *
     * @return array{
     *     url: string, // URL полноразмерного изображения.
     *     thumbnail_url: string, // URL эскиза изображения.
     *     name: string, // Название изображения.
     *     description: string, // Описание изображения.
     *     category_name: string, // Название категории.
     *     category_description: string, // Описание категории.
     *     rate: string, // Рейтинг изображения.
     *     url_user: null, // URL пользователя (если доступен).
     *     real_name: string // Имя пользователя.
     * } Массив с данными об отсутствующем изображении.
     */
    public function no_photo(): array;

    /**
     * @brief Вывод изображения через HTTP.
     *
     * @details Метод проверяет существование и доступность файла, определяет его MIME-тип
     * и отправляет содержимое файла через HTTP. Если файл не найден или недоступен,
     * возвращается HTTP-статус 404. Если возникли проблемы с чтением файла или определением
     * MIME-типа, возвращается HTTP-статус 500.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $work->image_attach('/path/to/image.jpg', 'image.jpg');
     * @endcode
     *
     * @param string $full_path Полный путь к файлу.
     * @param string $name_file Имя файла для заголовка Content-Disposition.
     *
     * @return void Метод ничего не возвращает.
     */
    public function image_attach(string $full_path, string $name_file): void;

    /**
     * Корректировка расширения файла в соответствии с его MIME-типом.
     *
     * @details Метод проверяет MIME-тип файла и возвращает полный путь с правильным расширением.
     * Если у файла отсутствует расширение, оно добавляется автоматически.
     * Метод используется для обеспечения корректного соответствия расширения файла его реальному типу.
     *
     * @see PhotoRigma::Classes::Work::fix_file_extension() Вызывается из этого метода.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     *
     * @param string $full_path Полный путь к файлу.
     * @return string Полный путь к файлу с правильным расширением.
     *
     * @throws InvalidArgumentException Если путь к файлу некорректен или файл не существует.
     * @throws RuntimeException Если MIME-тип файла не поддерживается.
     *
     * @example Пример использования метода:
     * @code
     * $work = new Work();
     * $fixed_path = $work->fix_file_extension('/path/to/file');
     * echo "Исправленный путь: {$fixed_path}";
     * @endcode
     */
    public function fix_file_extension(string $full_path): string;
}

/**
 * @brief Класс для работы с изображениями.
 *
 * @details Класс `Work_Image` реализует интерфейс `Work_Image_Interface` и предоставляет методы для:
 *          - Загрузки, обработки и сохранения изображений.
 *          - Изменения размеров изображений с использованием библиотек GraphicsMagick, ImageMagick или GD.
 *          - Применения фильтров и эффектов к изображениям.
 *          - Работы с различными форматами изображений (JPEG, PNG, GIF, WebP и др.).
 *          - Вывода изображений через HTTP с защитой от MIME-sniffing и кликджекинга.
 *          - Логирования ошибок через функцию `log_in_file`.
 *          - Корректировки расширения файла в соответствии с его MIME-типом.
 *
 * @see PhotoRigma::Classes::Work_Image_Interface Интерфейс для работы с изображениями.
 * @see PhotoRigma::Classes::Work Класс, через который вызываются методы для работы с изображениями.
 * @see PhotoRigma::Classes::Work::validate_mime_type() Метод для проверки MIME-типов изображений.
 * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
 *
 * @note Этот класс реализует интерфейс Work_Image_Interface.
 * @warning Не используйте этот класс напрямую, все вызовы должны выполняться через класс Work.
 */
class Work_Image implements Work_Image_Interface
{
    // Свойства:
    private array $config; ///< Конфигурация приложения.
    public const MAX_IMAGE_WIDTH = 5000; // Максимальная ширина изображения (в пикселях)
    public const MAX_IMAGE_HEIGHT = 5000; // Максимальная высота изображения (в пикселях)

    /**
     * @brief Конструктор класса.
     *
     * @details Инициализирует объект класса `Work_Image` с переданной конфигурацией.
     * Этот класс является дочерним для PhotoRigma::Classes::Work.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work Родительский класс, который создаёт объект класса Work_Image.
     * @see PhotoRigma::Classes::Work_Image::$config Свойство, содержащее конфигурацию приложения.
     *
     * @param array $config Конфигурация приложения.
     *                      Должен быть массивом. Если передан некорректный тип, выбрасывается исключение.
     *
     * @throws InvalidArgumentException Если параметр $config не является массивом.
     *
     * @note Конфигурация должна содержать все необходимые параметры для корректной работы класса.
     *       Параметры сохраняются в свойство $this->config.
     *
     * @warning Отсутствие необходимых ключей в массиве $config может привести к ошибкам при работе класса.
     *          Убедитесь, что конфигурация содержит все требуемые параметры.
     *
     * @example PhotoRigma::Classes::Work_Image::__construct
     * @code
     * // Пример использования конструктора
     * $config = [
     *     'temp_photo_w' => 800,
     *     'temp_photo_h' => 600,
     * ];
     * $workImage = new \PhotoRigma\Classes\Work_Image($config);
     * @endcode
     */
    public function __construct(array $config)
    {
        if (!is_array($config)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неверный тип конфигурации | Ожидался массив, получено: " . gettype($config)
            );
        }
        $this->config = $config;
    }

    /**
     * @brief Получает значение приватного свойства.
     *
     * @details Метод позволяет получить доступ к приватному свойству `$config`.
     * Если запрашиваемое свойство не существует, выбрасывается исключение.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work_Image::$config Свойство, к которому обращается метод.
     *
     * @param string $name Имя свойства:
     *                     - Допустимое значение: 'config'.
     *                     - Если указано другое имя, выбрасывается исключение.
     *
     * @return mixed Значение свойства `$config`.
     *
     * @throws InvalidArgumentException Если запрашиваемое свойство не существует.
     *
     * @note Этот метод предназначен только для доступа к свойству `$config`.
     *       Любые другие запросы будут игнорироваться с выбросом исключения.
     *
     * @warning Попытка доступа к несуществующему свойству вызовет исключение.
     *          Убедитесь, что вы запрашиваете только допустимые свойства.
     *
     * @example PhotoRigma::Classes::Work_Image::__get
     * @code
     * // Пример использования метода
     * $workImage = new \PhotoRigma\Classes\Work_Image(['temp_photo_w' => 800]);
     * echo $workImage->config['temp_photo_w']; // Выведет: 800
     * @endcode
     */
    public function __get(string $name)
    {
        if ($name === 'config') {
            return $this->config;
        }
        throw new \InvalidArgumentException(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не существует | Получено: '{$name}'"
        );
    }

    /**
     * @brief Устанавливает значение приватного свойства.
     *
     * @details Метод позволяет изменить значение приватного свойства `$config`.
     * Если переданное имя свойства не соответствует `$config`, выбрасывается исключение.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work_Image::$config Свойство, которое изменяет метод.
     *
     * @param string $name Имя свойства:
     *                     - Допустимое значение: 'config'.
     *                     - Если указано другое имя, выбрасывается исключение.
     * @param mixed $value Новое значение свойства:
     *                     - Может быть любого типа, но рекомендуется использовать массив для конфигурации.
     *
     * @throws InvalidArgumentException Если переданное имя свойства не соответствует `$config`.
     *
     * @note Этот метод предназначен только для изменения свойства `$config`.
     *       Любые другие попытки установки значений будут игнорироваться с выбросом исключения.
     *
     * @warning Попытка установки значения для несуществующего свойства вызовет исключение.
     *          Убедитесь, что вы устанавливаете значение только для допустимых свойств.
     *
     * @example PhotoRigma::Classes::Work_Image::__set
     * @code
     * // Пример использования метода
     * $workImage = new \PhotoRigma\Classes\Work_Image([]);
     * $workImage->config = ['temp_photo_w' => 1024];
     * echo $workImage->config['temp_photo_w']; // Выведет: 1024
     * @endcode
     */
    public function __set(string $name, $value)
    {
        if ($name === 'config') {
            $this->config = $value;
        } else {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Свойство не может быть установлено | Получено: '{$name}'"
            );
        }
    }

    /**
     * @brief Вычисляет размеры для вывода эскиза изображения.
     *
     * @details Это публичный редирект на защищённый метод `_size_image_internal`.
     *
     * @see PhotoRigma::Classes::Work Класс, через который вызывается этот метод.
     * @see PhotoRigma::Classes::Work_CoreLogic::_create_photo_internal() Метод, вызывающий этот метод.
     * @see PhotoRigma::Classes::Work_Image::_size_image_internal() Защищённый метод, выполняющий основную логику.
     *
     * @param string $path_image Путь к файлу изображения.
     *
     * @return array{
     *     width: int,  // Ширина эскиза.
     *     height: int  // Высота эскиза.
     * } Массив с шириной и высотой эскиза.
     *
     * @throws RuntimeException Если файл не существует или не удалось получить размеры изображения.
     */
    public function size_image(string $path_image): array
    {
        return $this->_size_image_internal($path_image);
    }

    /**
     * @brief Изменяет размер изображения.
     *
     * @details Это публичный редирект на защищённый метод `_image_resize_internal`.
     *
     * @see PhotoRigma::Classes::Work::image_resize() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Image::_image_resize_internal() Защищённый метод, выполняющий основную логику.
     *
     * @param string $full_path Путь к исходному изображению.
     * @param string $thumbnail_path Путь для сохранения эскиза.
     *
     * @return bool True, если операция выполнена успешно, иначе False.
     *
     * @throws InvalidArgumentException Если пути к файлам некорректны или имеют недопустимый формат.
     * @throws RuntimeException Если возникли ошибки при проверке файлов, директорий или размеров изображения.
     */
    public function image_resize(string $full_path, string $thumbnail_path): bool
    {
        return $this->_image_resize_internal($full_path, $thumbnail_path);
    }

    /**
     * @brief Возвращает данные для отсутствующего изображения.
     *
     * @details Это публичный редирект на защищённый метод `_no_photo_internal`.
     *
     * @see PhotoRigma::Classes::Work::no_photo() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Image::_no_photo_internal() Защищённый метод, выполняющий основную логику.
     *
     * @return array{
     *     url: string, // URL полноразмерного изображения.
     *     thumbnail_url: string, // URL эскиза изображения.
     *     name: string, // Название изображения.
     *     description: string, // Описание изображения.
     *     category_name: string, // Название категории.
     *     category_description: string, // Описание категории.
     *     rate: string, // Рейтинг изображения.
     *     url_user: null, // URL пользователя (если доступен).
     *     real_name: string // Имя пользователя.
     * } Массив с данными об отсутствующем изображении.
     */
    public function no_photo(): array
    {
        return $this->_no_photo_internal();
    }

    /**
     * @brief Вывод изображения через HTTP.
     *
     * @details Это публичный редирект на защищённый метод `_image_attach_internal`.
     *
     * @see PhotoRigma::Classes::Work::image_attach() Этот метод вызывается через класс Work.
     * @see PhotoRigma::Classes::Work_Image::_image_attach_internal() Защищённый метод, выполняющий основную логику.
     *
     * @param string $full_path Полный путь к файлу.
     * @param string $name_file Имя файла для заголовка Content-Disposition.
     *
     * @return void Метод ничего не возвращает.
     */
    public function image_attach(string $full_path, string $name_file): void
    {
        $this->_image_attach_internal($full_path, $name_file);
    }

    /**
     * Корректировка расширения файла в соответствии с его MIME-типом (публичный интерфейс).
     *
     * Метод вызывает защищённый метод _fix_file_extension_internal() для проверки MIME-типа файла
     * и возвращает полный путь с правильным расширением.
     * Если у файла отсутствует расширение, оно добавляется автоматически.
     *
     * @see PhotoRigma::Classes::Work_Image::_fix_file_extension_internal() Вызывает этот метод для корректировки расширения.
     * @see PhotoRigma::Classes::Work::fix_file_extension() Вызывается из этого метода.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     *
     * @param string $full_path Полный путь к файлу.
     * @return string Полный путь к файлу с правильным расширением.
     *
     * @throws InvalidArgumentException Если путь к файлу некорректен или файл не существует.
     * @throws RuntimeException Если MIME-тип файла не поддерживается.
     */
    public function fix_file_extension(string $full_path): string
    {
        return $this->_fix_file_extension_internal($full_path);
    }

    /**
     * @brief Вычисляет размеры для вывода эскиза изображения.
     *
     * @details Метод рассчитывает ширину и высоту эскиза на основе реальных размеров изображения
     * и конфигурационных параметров (`temp_photo_w` и `temp_photo_h`). Если изображение меньше
     * целевого размера, возвращаются оригинальные размеры. В противном случае размеры масштабируются
     * пропорционально.
     *
     * @see PhotoRigma::Classes::Work_Image::size_image() Публичный редирект, вызывающий этот метод.
     * @see PhotoRigma::Classes::Work_Image::calculate_thumbnail_size() Этот метод используется для расчёта размера эскиза.
     *
     * @param string $path_image Путь к файлу изображения.
     *
     * @return array{
     *     width: int,  // Ширина эскиза.
     *     height: int  // Высота эскиза.
     * } Массив с шириной и высотой эскиза.
     *
     * @throws RuntimeException Если файл не существует или не удалось получить размеры изображения.
     */
    protected function _size_image_internal(string $path_image): array
    {
        // Проверяем существование файла
        if (!file_exists($path_image)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл не найден | Путь: '{$path_image}'"
            );
        }
        // Получаем размеры изображения
        $size = getimagesize($path_image);
        if ($size === false) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить размеры изображения | Путь: '{$path_image}'"
            );
        }
        // Используем готовый метод для расчёта размеров эскиза
        return $this->calculate_thumbnail_size($size);
    }

    /**
     * @brief Изменяет размер изображения.
     *
     * @details Метод изменяет размер исходного изображения и создаёт эскиз заданных размеров.
     * Размеры эскиза рассчитываются на основе конфигурации (`temp_photo_w`, `temp_photo_h`)
     * с использованием метода `calculate_thumbnail_size`. Если файл эскиза уже существует
     * и его размеры совпадают с рассчитанными, метод завершает работу без изменений.
     * В противном случае создаётся новый эскиз с использованием одной из доступных библиотек:
     * GraphicsMagick, ImageMagick или GD (в порядке приоритета).
     *
     * @see PhotoRigma::Classes::Work_Image::image_resize() Публичный редирект, вызывающий этот метод.
     * @see PhotoRigma::Classes::Work_Image::calculate_thumbnail_size() Метод для расчёта размеров эскиза.
     * @see PhotoRigma::Classes::Work_Image::process_image_resize_gmagick() Обработка через GraphicsMagick.
     * @see PhotoRigma::Classes::Work_Image::process_image_resize_imagick() Обработка через ImageMagick.
     * @see PhotoRigma::Classes::Work_Image::process_image_resize_gd() Обработка через GD.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     *
     * @param string $full_path Путь к исходному изображению.
     * @param string $thumbnail_path Путь для сохранения эскиза.
     *
     * @return bool True, если операция выполнена успешно, иначе False.
     *
     * @throws InvalidArgumentException Если пути к файлам некорректны или имеют недопустимый формат.
     * @throws RuntimeException Если возникли ошибки при проверке файлов, директорий или размеров изображения.
     */
    protected function _image_resize_internal(string $full_path, string $thumbnail_path): bool
    {
        // Нормализация путей через realpath()
        $full_path = realpath($full_path);
        $thumbnail_path = realpath($thumbnail_path);
        if (!$full_path || !$thumbnail_path) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный путь к файлу | \$full_path = {$full_path}, \$thumbnail_path = {$thumbnail_path}"
            );
        }
        // Блок 1: Проверка корректности путей через filter_var
        $path_errors = [];
        foreach (['full_path' => $full_path, 'thumbnail_path' => $thumbnail_path] as $key => $path) {
            if (!filter_var($path, FILTER_VALIDATE_REGEXP, [
                'options' => ['regexp' => '/^[a-zA-Z0-9\/\.\-_]+$/']
            ])) {
                $path_errors[] = "Некорректный формат пути: \${$key} = {$path}.";
            }
        }
        if (!empty($path_errors)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный формат пути | Ошибки: " . implode(' ', $path_errors)
            );
        }
        // Блок 2: Проверка существования и доступности файлов
        if (!file_exists($full_path) || !is_readable($full_path)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Исходное изображение не найдено или недоступно для чтения | Путь: \$full_path = {$full_path}"
            );
        }
        // Проверка доступности директории для записи
        $thumbnail_dir = dirname($thumbnail_path);
        if (!is_writable($thumbnail_dir)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Директория для сохранения эскиза недоступна для записи | Путь: \$thumbnail_dir = {$thumbnail_dir}"
            );
        }
        $full_size = getimagesize($full_path);
        if ($full_size === false) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить размеры изображения | Путь: \$full_path = {$full_path}"
            );
        }
        if ($full_size[0] > self::MAX_IMAGE_WIDTH || $full_size[1] > self::MAX_IMAGE_HEIGHT) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Размеры исходного изображения слишком велики | Путь: \$full_path = {$full_path}, ширина = {$full_size[0]}, высота = {$full_size[1]}"
            );
        }
        $thumbnail_exists = file_exists($thumbnail_path);
        if ($thumbnail_exists && !is_writable($thumbnail_path)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл эскиза существует, но недоступен для записи | Путь: \$thumbnail_path = {$thumbnail_path}"
            );
        }
        // Блок 3: Расчет размеров будущего эскиза
        $photo = $this->calculate_thumbnail_size($full_size);
        // Если файл эскиза существует и его размеры совпадают с расчетными, завершаем работу
        if ($thumbnail_exists) {
            $thumbnail_size = getimagesize($thumbnail_path);
            if ($thumbnail_size !== false && $thumbnail_size[0] == $photo['width'] && $thumbnail_size[1] == $photo['height']) {
                return true;
            }
        }
        // Блок 4: Определение MIME-типа
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $full_path);
        finfo_close($finfo);
        // Блок 5: Подготовка данных для обработки изображения
        $original_data = [
            'path' => $full_path,
            'type' => $mime_type,
            'width' => $full_size[0],
            'height' => $full_size[1],
        ];
        $thumbnail_data = [
            'path' => $thumbnail_path,
            'width' => $photo['width'],
            'height' => $photo['height'],
            'exists' => $thumbnail_exists,
        ];
        // Блок 6: Логика выбора библиотеки с fallback
        try {
            if (extension_loaded('gmagick')) {
                return $this->process_image_resize_gmagick($original_data, $thumbnail_data);
            }
        } catch (\Exception $e) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при обработке через GraphicsMagick | Аргументы: \$full_path = {$full_path}, \$thumbnail_path = {$thumbnail_path} | Сообщение об ошибке: {$e->getMessage()}"
            );
        }
        try {
            if (extension_loaded('imagick')) {
                return $this->process_image_resize_imagick($original_data, $thumbnail_data);
            }
        } catch (\Exception $e) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при обработке через ImageMagick | Аргументы: \$full_path = {$full_path}, \$thumbnail_path = {$thumbnail_path} | Сообщение об ошибке: {$e->getMessage()}"
            );
        }
        // Если ни одна из библиотек не сработала, используем GD
        return $this->process_image_resize_gd($original_data, $thumbnail_data);
    }

    /**
     * @brief Возвращает данные для отсутствующего изображения.
     *
     * @details Метод формирует массив данных, который используется для представления информации
     * об отсутствующем изображении. Это может быть полезно, например, если изображение не найдено
     * или недоступно. Метод использует конфигурацию приложения (`site_url`) для формирования URL-адресов.
     *
     * @see PhotoRigma::Classes::Work_Image::no_photo() Публичный редирект, вызывающий этот метод.
     *
     * @return array{
     *     url: string, // URL полноразмерного изображения.
     *     thumbnail_url: string, // URL эскиза изображения.
     *     name: string, // Название изображения.
     *     description: string, // Описание изображения.
     *     category_name: string, // Название категории.
     *     category_description: string, // Описание категории.
     *     rate: string, // Рейтинг изображения.
     *     url_user: null, // URL пользователя (если доступен).
     *     real_name: string // Имя пользователя.
     * } Массив с данными об отсутствующем изображении.
     */
    protected function _no_photo_internal(): array
    {
        return [
            'url' => sprintf('%s?action=photo&id=0', $this->config['site_url']),
            'thumbnail_url' => sprintf('%s?action=attach&foto=0&thumbnail=1', $this->config['site_url']),
            'name' => 'No photo',
            'description' => 'No photo available',
            'category_name' => 'No category',
            'category_description' => 'No category available',
            'rate' => 'Rate: 0/0',
            'url_user' => null,
            'real_name' => 'No user'
        ];
    }

    /**
     * @brief Вывод изображения через HTTP.
     *
     * @details Метод проверяет существование и доступность файла, определяет его MIME-тип
     * и отправляет содержимое файла через HTTP. Если файл не найден или недоступен,
     * возвращается HTTP-статус 404. Если возникли проблемы с чтением файла или определением
     * MIME-типа, возвращается HTTP-статус 500.
     *
     * @see PhotoRigma::Classes::Work_Image::image_attach() Публичный редирект, вызывающий этот метод.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     *
     * @param string $full_path Полный путь к файлу.
     * @param string $name_file Имя файла для заголовка Content-Disposition.
     *
     * @return void Метод ничего не возвращает.
     */
    protected function _image_attach_internal(string $full_path, string $name_file): void
    {
        // Проверяем существование и доступность файла
        if (!file_exists($full_path) || !is_readable($full_path)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл не найден или недоступен для чтения | Путь: {$full_path}"
            );
            header("HTTP/1.0 404 Not Found");
            exit();
        }
        // Получаем MIME-тип и размеры изображения через finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $full_path);
        finfo_close($finfo);
        if (!$mime_type || strpos($mime_type, 'image/') !== 0) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось получить MIME-тип изображения | Путь: {$full_path}"
            );
            header("HTTP/1.0 500 Internal Server Error");
            exit();
        }
        // Проверяем размер файла
        $file_size = filesize($full_path);
        if ($file_size === false || $file_size > 10 * 1024 * 1024) { // Ограничение: 10 МБ
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Размер файла слишком велик или неизвестен | Путь: {$full_path}, Размер: {$file_size}"
            );
            header("HTTP/1.0 413 Payload Too Large");
            exit();
        }
        // Устанавливаем заголовки для вывода изображения
        header("Content-Type: " . $mime_type);
        header("Content-Disposition: inline; filename=\"" . $name_file . "\"");
        header("Content-Length: " . (string)$file_size);

        // Дополнительные заголовки для безопасности
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: no-referrer");
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:;");

        flush();
        // Открываем файл и отправляем его содержимое
        $fh = fopen($full_path, 'rb');
        if ($fh === false) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось открыть файл для чтения | Путь: {$full_path}"
            );
            header("HTTP/1.0 500 Internal Server Error");
            exit();
        }
        fpassthru($fh);
        fclose($fh);
        exit();
    }

    /**
     * Корректировка расширения файла в соответствии с его MIME-типом (публичный интерфейс).
     *
     * Метод вызывает защищённый метод _fix_file_extension_internal() для проверки MIME-типа файла
     * и возвращает полный путь с правильным расширением.
     * Если у файла отсутствует расширение, оно добавляется автоматически.
     *
     * @see PhotoRigma::Classes::Work_Image::_fix_file_extension_internal() Вызывает этот метод для корректировки расширения.
     * @see PhotoRigma::Classes::Work::fix_file_extension() Вызывается из этого метода.
     * @see PhotoRigma::Include::log_in_file Функция для логирования ошибок.
     *
     * @param string $full_path Полный путь к файлу.
     * @return string Полный путь к файлу с правильным расширением.
     *
     * @throws InvalidArgumentException Если путь к файлу некорректен или файл не существует.
     * @throws RuntimeException Если MIME-тип файла не поддерживается.
     */
    protected function _fix_file_extension_internal(string $full_path): string
    {
        // Проверка корректности пути
        if (!filter_var($full_path, FILTER_VALIDATE_REGEXP, [
            'options' => ['regexp' => '/^[a-zA-Z0-9\/\.\-_]+$/']
        ])) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Некорректный формат пути | Путь: \$full_path = {$full_path}"
            );
        }
        // Нормализация пути через realpath()
        $normalized_path = realpath($full_path);
        if (!$normalized_path) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл не существует или недоступен | Путь: \$full_path = {$full_path}"
            );
        }
        // Проверка существования файла и прав доступа
        if (!file_exists($normalized_path) || !is_file($normalized_path)) {
            throw new \InvalidArgumentException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл не существует или недоступен | Путь: \$full_path = {$full_path}"
            );
        }
        if (!is_readable($normalized_path)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Файл недоступен для чтения | Путь: \$full_path = {$full_path}"
            );
        }
        // Определение реального MIME-типа файла
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime_type = finfo_file($finfo, $normalized_path);
        finfo_close($finfo);
        // Список соответствий MIME-типов и расширений
        $mime_to_extension = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/tiff' => 'tiff',
            'image/svg+xml' => 'svg',
            'image/bmp' => 'bmp',
            'image/x-icon' => 'ico',
            'image/avif' => 'avif',
            'image/heic' => 'heic',
            'image/vnd.adobe.photoshop' => 'psd',
            'image/x-canon-cr2' => 'cr2',
            'image/x-nikon-nef' => 'nef',
            'image/x-xbitmap' => 'xbm',
            'image/x-portable-anymap' => 'pnm',
            'image/x-pcx' => 'pcx',
        ];
        // Проверка поддержки MIME-типа
        if (!array_key_exists($real_mime_type, $mime_to_extension)) {
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неподдерживаемый MIME-тип файла | Получено: {$real_mime_type}"
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
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Добавлено расширение '{$correct_extension}' к файлу | Путь: {$normalized_path}"
            );
            return $new_full_path;
        }
        if (strtolower($current_extension) === $correct_extension) {
            return $normalized_path; // Расширение уже корректное
        }
        // Формирование нового пути с правильным расширением
        $new_full_path = preg_replace('/\.[^.]+$/', '.' . $correct_extension, $normalized_path);
        log_in_file(
            __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Исправлено расширение файла | Старый путь: {$normalized_path}, Новый путь: {$new_full_path}"
        );
        return $new_full_path;
    }

    /**
     * @brief Расчёт размера эскиза.
     *
     * @details Метод вычисляет ширину и высоту эскиза на основе реальных размеров изображения
     *          и конфигурационных параметров (`temp_photo_w` и `temp_photo_h`).
     *          Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work_Image::$config
     *      Массив с конфигурацией приложения.
     * @see PhotoRigma::Classes::Work_Image::size_image()
     *      Вычисляет размеры для вывода эскиза изображения.
     * @see PhotoRigma::Classes::Work_Image::image_resize()
     *      Изменяет размер изображения.
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
     *          Некорректные размеры изображения
     *          Требуется массив с двумя положительными целыми числами.
     *          Получено: width = [значение], height = [значение]
     *
     * @throws RuntimeException Если значения `temp_photo_w` или `temp_photo_h` в конфигурации некорректны.
     *      Пример сообщения:
     *
     *          Некорректная конфигурация temp_photo_w или temp_photo_h
     *          Значения должны быть числами ≥ 0.
     *          Получено: temp_photo_w = [значение], temp_photo_h = [значение]
     *
     * @todo Перевести аргументы на массивовые значения ($size[0] -> $size['width']; $size[1] -> $size['height']).
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
     * $thumbnailSize = $this->calculate_thumbnail_size($originalSize);
     *
     * // Результат
     * echo "Ширина эскиза: {$thumbnailSize['width']}, Высота эскиза: {$thumbnailSize['height']}";
     * // Вывод: Ширина эскиза: 800, Высота эскиза: 450
     * @endcode
     */
    private function calculate_thumbnail_size(array $size): array
    {
        // Валидация параметра $size
        if (!isset($size[0], $size[1]) ||
            !is_int($size[0]) || !is_int($size[1]) ||
            $size[0] <= 0 || $size[1] <= 0) {
            throw new \InvalidArgumentException(
                "[{__FILE__}:{__LINE__} ({__METHOD__ ?: __FUNCTION__ ?: 'global'})] | Некорректные размеры изображения | " .
                "Требуется массив с двумя положительными целыми числами. Получено: width = " . (isset($size[0]) ? $size[0] : 'undefined') . ", height = " . (isset($size[1]) ? $size[1] : 'undefined')
            );
        }

        // Валидация конфигурации
        if (!isset($this->config['temp_photo_w'], $this->config['temp_photo_h']) ||
            !is_numeric($this->config['temp_photo_w']) || !is_numeric($this->config['temp_photo_h']) ||
            $this->config['temp_photo_w'] < 0 || $this->config['temp_photo_h'] < 0) {
            throw new \RuntimeException(
                "[{__FILE__}:{__LINE__} ({__METHOD__ ?: __FUNCTION__ ?: 'global'})] | Некорректная конфигурация temp_photo_w или temp_photo_h | " .
                "Значения должны быть числами ≥ 0. Получено: temp_photo_w = " . (isset($this->config['temp_photo_w']) ? $this->config['temp_photo_w'] : 'undefined') . ", temp_photo_h = " . (isset($this->config['temp_photo_h']) ? $this->config['temp_photo_h'] : 'undefined')
            );
        }

        // Преобразуем конфигурацию в целые числа
        $target_width = (int)$this->config['temp_photo_w'];
        $target_height = (int)$this->config['temp_photo_h'];

        // Вычисляем коэффициенты масштабирования через match
        $ratio_width = match (true) {
            $target_width > 0 => $size[0] / $target_width,
            default => 1,
        };

        $ratio_height = match (true) {
            $target_height > 0 => $size[1] / $target_height,
            default => 1,
        };

        // Если исходное изображение меньше целевого размера, возвращаем оригинальные размеры
        if ($size[0] < $target_width && $size[1] < $target_height && $target_width > 0 && $target_height > 0) {
            return [
                'width' => $size[0],
                'height' => $size[1]
            ];
        }

        // Масштабируем изображение пропорционально
        if ($ratio_width < $ratio_height) {
            return [
                'width' => (int)($size[0] / $ratio_height),
                'height' => (int)($size[1] / $ratio_height)
            ];
        } else {
            return [
                'width' => (int)($size[0] / $ratio_width),
                'height' => (int)($size[1] / $ratio_width)
            ];
        }
    }

    /**
     * @brief Обработка создания эскиза изображения с использованием библиотеки GraphicsMagick.
     *
     * @details Метод выполняет следующие шаги:
     * 1. Проверяет поддержку MIME-типа через массив разрешённых форматов.
     * 2. Преобразует MIME-тип в формат GraphicsMagick.
     * 3. Проверяет поддержку формата через Gmagick::queryFormats().
     * 4. Ограничивает использование памяти до 25% от доступной.
     * 5. Создаёт резервную копию старого эскиза (если он существует).
     * 6. Загружает исходное изображение и создаёт новый эскиз заданных размеров.
     * 7. Сохраняет новый эскиз в указанный файл.
     * 8. Восстанавливает старый эскиз в случае ошибки.
     *
     * Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work_Image::_image_resize_internal()
     *      Метод, вызывающий этот приватный метод.
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     * @param array $original_data Данные об оригинальном изображении:
     *                             - 'path' (string): Абсолютный путь к исходному файлу.
     *                             - 'type' (string): MIME-тип изображения (например, 'image/jpeg').
     *                                                Должен быть одним из разрешённых MIME-типов.
     *                             - 'width' (int): Ширина исходного изображения (положительное число).
     *                             - 'height' (int): Высота исходного изображения (положительное число).
     * @param array $thumbnail_data Данные об эскизе:
     *                              - 'path' (string): Абсолютный путь для сохранения эскиза.
     *                              - 'width' (int): Желаемая ширина эскиза (положительное число).
     *                              - 'height' (int): Желаемая высота эскиза (положительное число).
     *                              - 'exists' (bool): Существует ли файл эскиза (`true` или `false`).
     *
     * @return bool True, если эскиз создан успешно.
     *
     * @throws GmagickException Если возникает ошибка при работе с GraphicsMagick (например, при загрузке или обработке изображения).
     *
     * @warning Метод чувствителен к правам доступа при работе с файлами (например, при переименовании или удалении файлов).
     *          Убедитесь, что скрипт имеет необходимые права на запись и чтение.
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
     * $result = $this->process_image_resize_gmagick($original_data, $thumbnail_data);
     * if ($result) {
     *     echo "Эскиз успешно создан!";
     * } else {
     *     echo "Не удалось создать эскиз.";
     * }
     * @endcode
     */
    private function process_image_resize_gmagick(array $original_data, array $thumbnail_data): bool
    {
        // Разрешённые MIME-типы и их соответствие форматам GraphicsMagick
        $gmagick_formats = [
            'image/jpeg' => 'JPEG',
            'image/png' => 'PNG',
            'image/gif' => 'GIF',
            'image/webp' => 'WEBP',
            'image/tiff' => 'TIFF',
            'image/svg+xml' => 'SVG',
            'image/bmp' => 'BMP',
            'image/x-icon' => 'ICO', // Иконки
            'image/avif' => 'AVIF', // Современный формат
            'image/heic' => 'HEIC', // Формат с камер iPhone
        ];
        // Проверка MIME-типа
        if (!array_key_exists($original_data['type'], $gmagick_formats)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неподдерживаемый MIME-тип для GraphicsMagick | Получено: {$original_data['type']}"
            );
            return false;
        }
        // Получение формата GraphicsMagick
        $format = $gmagick_formats[$original_data['type']];
        // Проверка поддержки формата через GraphicsMagick
        $supported_formats = \Gmagick::queryFormats();
        if (!in_array($format, $supported_formats)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Формат {$format} не поддерживается GraphicsMagick"
            );
            return false;
        }
        // Ограничение памяти
        $available_memory = (int)ini_get('memory_limit'); // Например, '128M' → 128 * 1024 * 1024
        $max_memory_usage = 0.25 * $available_memory; // 25% от доступной памяти
        $estimated_memory = $original_data['width'] * $original_data['height'] * 3;
        if ($estimated_memory > $max_memory_usage) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Изображение слишком велико для обработки через GraphicsMagick"
            );
            return false;
        }
        // Создание резервной копии старого эскиза, если он существует
        if ($thumbnail_data['exists']) {
            $backup_thumbnail_path = tempnam(dirname($thumbnail_data['path']), 'bak_');
            if (!$backup_thumbnail_path || !rename($thumbnail_data['path'], $backup_thumbnail_path)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось создать резервную копию старого эскиза через GraphicsMagick"
                );
                return false;
            }
        } else {
            $backup_thumbnail_path = null;
        }
        $image = null;
        try {
            // Создание объекта Gmagick
            $image = new \Gmagick($original_data['path']);
            // Настройка прозрачности для PNG и WebP
            if ($original_data['type'] === 'image/png' || $original_data['type'] === 'image/webp') {
                $image->setImageFormat($format);
                $image->setImageAlphaChannel(\Gmagick::ALPHACHANNEL_ACTIVATE);
            }
            // Масштабирование изображения
            $image->resizeImage(
                $thumbnail_data['width'],
                $thumbnail_data['height'],
                \Gmagick::FILTER_LANCZOS, // Высококачественный фильтр
                1 // Коэффициент размытия (1 = без размытия)
            );
            // Сохранение нового эскиза
            $image->writeImage($thumbnail_data['path']);
            // Удаление резервной копии старого эскиза
            if ($backup_thumbnail_path && file_exists($backup_thumbnail_path)) {
                unlink($backup_thumbnail_path);
            }
            return true;
        } catch (\Exception $e) {
            // Логирование ошибки с контекстом
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при создании эскиза через GraphicsMagick | Оригинальный файл: {$original_data['path']}, Эскиз: {$thumbnail_data['path']}, Сообщение об ошибке: {$e->getMessage()}"
            );
            // Восстановление старого эскиза
            if ($backup_thumbnail_path && file_exists($backup_thumbnail_path)) {
                rename($backup_thumbnail_path, $thumbnail_data['path']);
            }
            return false;
        } finally {
            // Освобождение ресурсов
            if (isset($image) && $image instanceof \Gmagick) {
                $image->destroy();
            }
        }
    }

    /**
     * @brief Обработка создания эскиза изображения с использованием библиотеки ImageMagick.
     *
     * @details Метод выполняет следующие шаги:
     * 1. Проверяет поддержку MIME-типа через массив разрешённых форматов.
     * 2. Преобразует MIME-тип в формат ImageMagick.
     * 3. Проверяет поддержку формата через Imagick::queryFormats().
     * 4. Ограничивает использование памяти до 25% от доступной.
     * 5. Создаёт резервную копию старого эскиза (если он существует).
     * 6. Загружает исходное изображение и создаёт новый эскиз заданных размеров.
     * 7. Сохраняет новый эскиз в указанный файл.
     * 8. Восстанавливает старый эскиз в случае ошибки.
     *
     * Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work_Image::_image_resize_internal()
     *      Метод, вызывающий этот приватный метод.
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     * @param array $original_data Данные об оригинальном изображении:
     *                             - 'path' (string): Абсолютный путь к исходному файлу.
     *                             - 'type' (string): MIME-тип изображения (например, 'image/jpeg').
     *                                                Должен быть одним из разрешённых MIME-типов.
     *                             - 'width' (int): Ширина исходного изображения (положительное число).
     *                             - 'height' (int): Высота исходного изображения (положительное число).
     * @param array $thumbnail_data Данные об эскизе:
     *                              - 'path' (string): Абсолютный путь для сохранения эскиза.
     *                              - 'width' (int): Желаемая ширина эскиза (положительное число).
     *                              - 'height' (int): Желаемая высота эскиза (положительное число).
     *                              - 'exists' (bool): Существует ли файл эскиза (`true` или `false`).
     *
     * @return bool True, если эскиз создан успешно.
     *
     * @throws ImagickException Если возникает ошибка при работе с ImageMagick (например, при загрузке или обработке изображения).
     *
     * @warning Метод чувствителен к правам доступа при работе с файлами (например, при переименовании или удалении файлов).
     *          Убедитесь, что скрипт имеет необходимые права на запись и чтение.
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
     * $result = $this->process_image_resize_imagick($original_data, $thumbnail_data);
     * if ($result) {
     *     echo "Эскиз успешно создан!";
     * } else {
     *     echo "Не удалось создать эскиз.";
     * }
     * @endcode
     */
    private function process_image_resize_imagick(array $original_data, array $thumbnail_data): bool
    {
        // Разрешённые MIME-типы и их соответствие форматам ImageMagick
        $imagick_formats = [
            'image/jpeg' => 'JPEG',
            'image/png' => 'PNG',
            'image/gif' => 'GIF',
            'image/webp' => 'WEBP',
            'image/tiff' => 'TIFF',
            'image/svg+xml' => 'SVG',
            'image/bmp' => 'BMP',
            'image/x-icon' => 'ICO', // Иконки
            'image/avif' => 'AVIF', // Современный формат
            'image/heic' => 'HEIC', // Формат с камер iPhone
            'image/vnd.adobe.photoshop' => 'PSD', // Photoshop Document
            'image/x-canon-cr2' => 'CR2', // RAW Canon
            'image/x-nikon-nef' => 'NEF', // RAW Nikon
            'image/x-xbitmap' => 'XBM', // X Bitmap
            'image/x-portable-anymap' => 'PNM', // Portable Any Map
            'image/x-pcx' => 'PCX', // PCX
        ];
        // Проверка MIME-типа
        if (!array_key_exists($original_data['type'], $imagick_formats)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неподдерживаемый MIME-тип для ImageMagick | Получено: {$original_data['type']}"
            );
            return false;
        }
        // Получение формата ImageMagick
        $format = $imagick_formats[$original_data['type']];
        // Проверка поддержки формата через ImageMagick
        $supported_formats = \Imagick::queryFormats();
        if (!in_array($format, $supported_formats)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Формат {$format} не поддерживается ImageMagick"
            );
            return false;
        }
        // Ограничение памяти
        $available_memory = (int)ini_get('memory_limit'); // Например, '128M' → 128 * 1024 * 1024
        $max_memory_usage = 0.25 * $available_memory; // 25% от доступной памяти
        $estimated_memory = $original_data['width'] * $original_data['height'] * 3;
        if ($estimated_memory > $max_memory_usage) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Изображение слишком велико для обработки через ImageMagick"
            );
            return false;
        }
        // Создание резервной копии старого эскиза, если он существует
        if ($thumbnail_data['exists']) {
            $backup_thumbnail_path = tempnam(dirname($thumbnail_data['path']), 'bak_');
            if (!$backup_thumbnail_path || !rename($thumbnail_data['path'], $backup_thumbnail_path)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось создать резервную копию старого эскиза через ImageMagick"
                );
                return false;
            }
        } else {
            $backup_thumbnail_path = null;
        }
        $image = null;
        try {
            // Создание объекта Imagick
            $image = new \Imagick($original_data['path']);
            // Масштабирование изображения
            $image->resizeImage(
                $thumbnail_data['width'],
                $thumbnail_data['height'],
                \Imagick::FILTER_LANCZOS, // Высококачественный фильтр
                1 // Коэффициент размытия (1 = без размытия)
            );
            // Сохранение нового эскиза
            $image->writeImage($thumbnail_data['path']);
            // Удаление резервной копии старого эскиза
            if ($backup_thumbnail_path && file_exists($backup_thumbnail_path)) {
                unlink($backup_thumbnail_path);
            }
            return true;
        } catch (\Exception $e) {
            // Логирование ошибки
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при создании эскиза через ImageMagick | Оригинальный файл: {$original_data['path']}, Эскиз: {$thumbnail_data['path']}, Сообщение об ошибке: {$e->getMessage()}"
            );
            // Восстановление старого эскиза
            if ($backup_thumbnail_path && file_exists($backup_thumbnail_path)) {
                rename($backup_thumbnail_path, $thumbnail_data['path']);
            }
            return false;
        } finally {
            // Освобождение ресурсов
            if (isset($image) && $image instanceof \Imagick) {
                $image->destroy();
            }
        }
    }

    /**
     * @brief Обработка создания эскиза изображения с использованием библиотеки GD.
     *
     * @details Метод выполняет следующие шаги:
     * 1. Проверяет поддержку MIME-типа через массив разрешённых форматов.
     * 2. Преобразует MIME-тип в константу IMAGETYPE_*.
     * 3. Проверяет поддержку формата через функцию imagetypes().
     * 4. Ограничивает использование памяти до 25% от доступной.
     * 5. Создаёт резервную копию старого эскиза (если он существует).
     * 6. Загружает исходное изображение и создаёт новый эскиз заданных размеров.
     * 7. Сохраняет новый эскиз в указанный файл.
     * 8. Восстанавливает старый эскиз в случае ошибки.
     *
     * Этот метод является приватным и предназначен только для использования внутри класса.
     *
     * @callergraph
     * @callgraph
     *
     * @see PhotoRigma::Classes::Work_Image::_image_resize_internal()
     *      Метод, вызывающий этот приватный метод.
     * @see PhotoRigma::Include::log_in_file()
     *      Функция для логирования ошибок.
     *
     * @param array $original_data Данные об оригинальном изображении:
     *                             - 'path' (string): Абсолютный путь к исходному файлу.
     *                             - 'type' (string): MIME-тип изображения (например, 'image/jpeg').
     *                                                Должен быть одним из разрешённых MIME-типов.
     *                             - 'width' (int): Ширина исходного изображения (положительное число).
     *                             - 'height' (int): Высота исходного изображения (положительное число).
     * @param array $thumbnail_data Данные об эскизе:
     *                              - 'path' (string): Абсолютный путь для сохранения эскиза.
     *                              - 'width' (int): Желаемая ширина эскиза (положительное число).
     *                              - 'height' (int): Желаемая высота эскиза (положительное число).
     *                              - 'exists' (bool): Существует ли файл эскиза (`true` или `false`).
     *
     * @return bool True, если эскиз создан успешно.
     *
     * @throws InvalidArgumentException Если MIME-тип или тип изображения не поддерживаются.
     *      Пример сообщения:
     *          Неподдерживаемый MIME-тип для GD | Получено: [значение]
     * @throws RuntimeException Если возникает ошибка при работе с GD (например, при загрузке, обработке или сохранении изображения).
     *      Пример сообщения:
     *          Не удалось создать ресурс изображения через GD | Путь: [значение]
     *
     * @warning Метод чувствителен к правам доступа при работе с файлами (например, при переименовании или удалении файлов).
     *          Убедитесь, что скрипт имеет необходимые права на запись и чтение.
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
     * $result = $this->process_image_resize_gd($original_data, $thumbnail_data);
     * if ($result) {
     *     echo "Эскиз успешно создан!";
     * } else {
     *     echo "Не удалось создать эскиз.";
     * }
     * @endcode
     */
    private function process_image_resize_gd(array $original_data, array $thumbnail_data): bool
    {
        // Разрешённые MIME-типы и их соответствие константам IMAGETYPE_*
        $gd_mime_to_type = [
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png' => IMAGETYPE_PNG,
            'image/gif' => IMAGETYPE_GIF,
            'image/webp' => IMAGETYPE_WEBP,
        ];
        // Проверка MIME-типа
        if (!array_key_exists($original_data['type'], $gd_mime_to_type)) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неподдерживаемый MIME-тип для GD | Получено: {$original_data['type']}"
            );
            return false;
        }
        // Преобразование MIME-типа в константу IMAGETYPE_*
        $image_type = $gd_mime_to_type[$original_data['type']];
        // Проверка поддержки формата через imagetypes()
        if (!($image_type === IMAGETYPE_GIF && (imagetypes() & IMG_GIF)) &&
            !($image_type === IMAGETYPE_JPEG && (imagetypes() & IMG_JPG)) &&
            !($image_type === IMAGETYPE_PNG && (imagetypes() & IMG_PNG)) &&
            !($image_type === IMAGETYPE_WEBP && (imagetypes() & IMG_WEBP))) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Формат {$original_data['type']} не поддерживается текущей версией GD"
            );
            return false;
        }
        // Ограничение памяти
        $available_memory = (int)ini_get('memory_limit'); // Например, '128M' → 128 * 1024 * 1024
        $max_memory_usage = 0.25 * $available_memory; // 25% от доступной памяти
        $estimated_memory = $original_data['width'] * $original_data['height'] * 3;
        if ($estimated_memory > $max_memory_usage) {
            log_in_file(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Изображение слишком велико для обработки через GD"
            );
            return false;
        }
        // Создание резервной копии старого эскиза, если он существует
        if ($thumbnail_data['exists']) {
            $backup_thumbnail_path = tempnam(dirname($thumbnail_data['path']), 'bak_');
            if (!$backup_thumbnail_path || !rename($thumbnail_data['path'], $backup_thumbnail_path)) {
                log_in_file(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось создать резервную копию старого эскиза через GD"
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
                IMAGETYPE_GIF => imagecreatefromgif($original_data['path']),
                IMAGETYPE_JPEG => imagecreatefromjpeg($original_data['path']),
                IMAGETYPE_PNG => imagecreatefrompng($original_data['path']),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($original_data['path']) : null,
                default => throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неподдерживаемый тип изображения для GD | Путь: {$original_data['path']}"
                ),
            };
            if (!$imorig) {
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось создать ресурс изображения через GD | Путь: {$original_data['path']}"
                );
            }
            // Создание нового изображения для эскиза
            $im = imagecreatetruecolor($thumbnail_data['width'], $thumbnail_data['height']);
            if (!$im) {
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось создать полноцветное изображение для эскиза через GD"
                );
            }
            // Настройка прозрачности для PNG и WebP
            if ($image_type === IMAGETYPE_PNG || $image_type === IMAGETYPE_WEBP) {
                imagealphablending($im, false); // Отключаем смешивание цветов
                imagesavealpha($im, true);      // Сохраняем альфа-канал
            }
            // Копируем и масштабируем изображение
            if (!imagecopyresampled($im, $imorig, 0, 0, 0, 0, $thumbnail_data['width'], $thumbnail_data['height'], $original_data['width'], $original_data['height'])) {
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Не удалось масштабировать изображение через GD | Путь: {$original_data['path']}"
                );
            }
            // Сохраняем новый эскиз
            $save_function = match ($image_type) {
                IMAGETYPE_GIF => 'imagegif',
                IMAGETYPE_JPEG => 'imagejpeg',
                IMAGETYPE_PNG => 'imagepng',
                IMAGETYPE_WEBP => function_exists('imagewebp') ? 'imagewebp' : throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Функция imagewebp недоступна в текущей версии GD"
                ),
                default => throw new \InvalidArgumentException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Неподдерживаемый тип изображения для GD | Путь: {$original_data['path']}"
                ),
            };
            if (!function_exists($save_function)) {
                throw new \RuntimeException(
                    __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Функция {$save_function} недоступна в текущей версии GD"
                );
            }
            $save_function($im, $thumbnail_data['path']);
            // Удаление резервной копии старого эскиза
            if ($backup_thumbnail_path && file_exists($backup_thumbnail_path)) {
                unlink($backup_thumbnail_path);
            }
            return true;
        } catch (\Exception $e) {
            // Восстановление старого эскиза
            if ($backup_thumbnail_path && file_exists($backup_thumbnail_path)) {
                rename($backup_thumbnail_path, $thumbnail_data['path']);
            }
            // Выбрасываем исключение с подробным сообщением
            throw new \RuntimeException(
                __FILE__ . ":" . __LINE__ . " (" . (__METHOD__ ?: __FUNCTION__ ?: 'global') . ") | Ошибка при создании эскиза через GD | Оригинальный файл: {$original_data['path']}, Эскиз: {$thumbnail_data['path']}, Сообщение об ошибке: {$e->getMessage()}"
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
}
