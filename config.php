<?php
/**
 * Конфигурационный файл системы тестирования
 * 
 * http://localhost:8080/admin/login.php
 */

// Настройки базы данных для Docker
define('DB_HOST', 'database');
define('DB_NAME', 'test_system');
define('DB_USER', 'root');
define('DB_PASS', 'root');

// Настройки приложения
define('SITE_URL', 'http://localhost:8080/');
define('ADMIN_EMAIL', 'admin@example.com');
define('SESSION_NAME', 'test_system_session');
define('SESSION_LIFETIME', 3600);

// Настройки безопасности
define('SALT', 'your-secret-salt-here-change-it');

// Режим отладки
define('DEBUG_MODE', true);

// Пути к директориям
define('ROOT_PATH', '/var/www/html');
define('LIB_PATH', ROOT_PATH . '/lib/');
define('ADMIN_PATH', ROOT_PATH . '/admin/');
define('PUBLIC_PATH', ROOT_PATH . '/public/');
define('ASSETS_PATH', ROOT_PATH . '/assets/');

// Настройки ошибок
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

date_default_timezone_set('Europe/Moscow');

// Настройки сессии
ini_set('session.name', SESSION_NAME);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// Автозагрузка классов
spl_autoload_register(function($className) {
    $file = LIB_PATH . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    return false;
});

// Подключение к БД
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}

/**
 * Перенаправление на указанный URL
 * 
 * @param string $url URL для перенаправления
 * @return void
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Проверка авторизации администратора
 * 
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_login']);
}

/**
 * Очистка и экранирование ввода
 * 
 * @param string $input Входная строка
 * @return string Очищенная строка
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Генерация URL-дружественного слага
 * 
 * @param string $string Исходная строка
 * @return string Слаг
 */
function generateSlug($string) {
    $string = mb_strtolower($string, 'UTF-8');
    $string = str_replace(
        ['а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я'],
        ['a','b','v','g','d','e','e','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','ts','ch','sh','sch','','y','','e','yu','ya'],
        $string
    );
    $string = preg_replace('/[^a-z0-9\-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// Сессия
session_start();