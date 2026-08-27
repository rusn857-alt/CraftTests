<?php
/**
 * Главная страница административной панели
 */

require_once '../config.php';

$auth = new Auth();
if (!$auth->isAuthenticated()) {
}

$adminLogin = $_SESSION['admin_login'] ?? '';

// Получение статистики
$db = Database::getInstance();

// Общее количество тестов
$testsCount = $db->fetchOne("SELECT COUNT(*) as count FROM tests");
$testsCount = $testsCount['count'] ?? 0;

// Количество активных тестов
$activeTests = $db->fetchOne("SELECT COUNT(*) as count FROM tests WHERE status = 'active'");
$activeTests = $activeTests['count'] ?? 0;

// Количество пройденных тестов
$completedSessions = $db->fetchOne("SELECT COUNT(*) as count FROM test_sessions WHERE status = 'completed'");
$completedSessions = $completedSessions['count'] ?? 0;

// Количество пользователей
$usersCount = $db->fetchOne("SELECT COUNT(*) as count FROM users");
$usersCount = $usersCount['count'] ?? 0;

// Последние сессии
$recentSessions = $db->fetchAll(
    "SELECT ts.id, ts.started_at, ts.status, ts.total_score, 
            u.name as user_name, t.title as test_title
     FROM test_sessions ts
     LEFT JOIN users u ON ts.user_id = u.id
     LEFT JOIN tests t ON ts.test_id = t.id
     WHERE ts.status = 'completed'
     ORDER BY ts.completed_at DESC
     LIMIT 10"
);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - Система тестирования</title>
    <link rel="stylesheet" href="/../assets/css/style.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Header -->
        <header class="admin-header">
            <div class="container">
                <div class="header-content">
                    <h1>📊 Система тестирования</h1>
                    <div class="user-info">
                        <span>👤 <?php echo htmlspecialchars($adminLogin); ?></span>
                        <a href="logout.php" class="btn btn-sm btn-danger">Выход</a>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Navigation -->
        <nav class="admin-nav">
            <div class="container">
                <ul>
                    <li><a href="index.php" class="active">📊 Главная</a></li>
                    <li><a href="tests.php">📝 Тесты</a></li>
                    <li><a href="/public/index.php">📝Список</a></li>
                    <li><a href="results.php">📈 Результаты</a></li>
                    <li><a href="import_json.php">📈 Загрузить тест</a></li>
                </ul>
            </div>
        </nav>
        
        <!-- Main Content -->
        <main class="admin-main">
            <div class="container">
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $testsCount; ?></div>
                        <div class="stat-label">Всего тестов</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $activeTests; ?></div>
                        <div class="stat-label">Активных тестов</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $completedSessions; ?></div>
                        <div class="stat-label">Пройдено тестов</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $usersCount; ?></div>
                        <div class="stat-label">Пользователей</div>
                    </div>
                </div>
                
                <div class="dashboard-sections">
                    <div class="section">
                        <h2>Быстрые действия</h2>
                        <div class="quick-actions">
                            <a href="test_edit.php" class="btn btn-primary">➕ Создать тест</a>
                            <a href="tests.php" class="btn btn-secondary">📝 Управление тестами</a>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h2>Последние результаты</h2>
                        <?php if (empty($recentSessions)): ?>
                            <p style="color: #999;">Нет завершенных тестов</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Пользователь</th>
                                        <th>Тест</th>
                                        <th>Баллы</th>
                                        <th>Дата</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentSessions as $session): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($session['user_name'] ?? 'Неизвестно'); ?></td>
                                            <td><?php echo htmlspecialchars($session['test_title'] ?? 'Удален'); ?></td>
                                            <td><?php echo $session['total_score'] ?? '0'; ?></td>
                                            <td><?php echo date('d.m.Y H:i', strtotime($session['started_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>