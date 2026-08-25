<?php
/**
 * Установочный скрипт системы тестирования
 * Создает администратора и проверяет структуру БД
 */

require_once 'config.php';

// Проверка, что скрипт запускается в консоли или через браузер в режиме разработки
if (php_sapi_name() !== 'cli' && !DEBUG_MODE) {
    die('Установка доступна только в режиме отладки');
}

echo "=== Установка системы тестирования ===\n\n";

// Проверка подключения к БД
try {
    $db->query("SELECT 1");
    echo "✓ Подключение к БД успешно\n";
} catch (PDOException $e) {
    die("✗ Ошибка подключения к БД: " . $e->getMessage() . "\n");
}

// Создание администратора
$auth = new Auth();

if ($auth->adminExists('admin')) {
    echo "✓ Администратор 'admin' уже существует\n";
    
    // Обновление пароля, если нужно
    $answer = readline("Обновить пароль администратора? (y/n): ");
    if (strtolower($answer) === 'y') {
        $password = readline("Введите новый пароль: ");
        if (strlen($password) >= 6) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->query(
                "UPDATE administrators SET password_hash = ? WHERE login = 'admin'",
                
            );
            echo "✓ Пароль обновлен\n";
        } else {
            echo "✗ Пароль должен быть не менее 6 символов\n";
        }
    }
} else {
    echo "Создание администратора 'admin'\n";
    $password = 'admin8800';
    $id = $auth->createAdmin('admin', $password);
    if ($id) {
        echo "✓ Администратор создан (ID: $id)\n";
        echo "  Логин: admin\n";
        echo "  Пароль: admin8800\n";
    } else {
        echo "✗ Ошибка создания администратора\n";
    }
}

// Проверка структуры таблиц
$tables = [
    'administrators',
    'tests',
    'questions',
    'answer_options',
    'users',
    'test_sessions',
    'user_answers'
];

echo "\nПроверка структуры таблиц:\n";
foreach ($tables as $table) {
    try {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            echo "✓ Таблица '$table' существует\n";
        } else {
            echo "✗ Таблица '$table' не найдена\n";
        }
    } catch (PDOException $e) {
        echo "✗ Ошибка проверки таблицы '$table': " . $e->getMessage() . "\n";
    }
}

echo "\nУстановка завершена!\n";
echo "Для входа в админ-панель используйте:\n";
echo "Логин: admin\n";
echo "Пароль: admin8800\n";