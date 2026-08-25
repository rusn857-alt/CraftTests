<?php
/**
 * Статистика по тесту
 */

require_once '../config.php';

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    redirect('/admin/login.php');
}

$testId = (int)($_GET['test_id'] ?? 0);

if ($testId <= 0) {
    redirect('/admin/tests.php');
}

$testManager = new TestManager();
$db = Database::getInstance();

$test = $testManager->getTest($testId);
if (!$test) {
    $_SESSION['message'] = 'Тест не найден';
    $_SESSION['message_type'] = 'danger';
    redirect('/admin/tests.php');
}

// Статистика
$stats = $testManager->getTestStats($testId);

// Распределение баллов
$scoreDistribution = $db->fetchAll(
    "SELECT total_score, COUNT(*) as count 
     FROM test_sessions 
     WHERE test_id = ? AND status = 'completed'
     GROUP BY total_score
     ORDER BY total_score DESC",
    [$testId]
);

// Последние прохождения
$recentSessions = $db->fetchAll(
    "SELECT ts.*, u.name as user_name 
     FROM test_sessions ts
     JOIN users u ON ts.user_id = u.id
     WHERE ts.test_id = ? AND ts.status = 'completed'
     ORDER BY ts.completed_at DESC
     LIMIT 10",
    [$testId]
);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика - <?php echo htmlspecialchars($test['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .stat-card .label {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .chart-container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .bar-chart {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            height: 200px;
            padding: 20px 0;
        }
        
        .bar {
            flex: 1;
            background: #4CAF50;
            border-radius: 4px 4px 0 0;
            min-height: 10px;
            transition: height 0.3s;
            position: relative;
        }
        
        .bar .value {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 12px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .bar .label {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 11px;
            color: #7f8c8d;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <header class="admin-header">
            <div class="container">
                <div class="header-content">
                    <h1>📊 Статистика: <?php echo htmlspecialchars($test['title']); ?></h1>
                    <div class="user-info">
                        <span>👤 <?php echo htmlspecialchars($_SESSION['admin_login']); ?></span>
                        <a href="logout.php" class="btn btn-sm btn-danger">Выход</a>
                    </div>
                </div>
            </div>
        </header>
        
        <nav class="admin-nav">
            <div class="container">
                <ul>
                    <li><a href="index.php">📊 Главная</a></li>
                    <li><a href="tests.php">📝 Тесты</a></li>
                    <li><a href="results.php">📈 Результаты</a></li>
                </ul>
            </div>
        </nav>
        
        <main class="admin-main">
            <div class="container">
                <div style="margin-bottom: 20px;">
                    <a href="tests.php" class="btn btn-secondary">← Назад к тестам</a>
                    <a href="results.php?test_id=<?php echo $testId; ?>" class="btn btn-info">📈 Все результаты</a>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['sessions']['total'] ?? 0; ?></div>
                        <div class="label">Всего прохождений</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['sessions']['completed'] ?? 0; ?></div>
                        <div class="label">Завершено</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['questions']; ?></div>
                        <div class="label">Вопросов</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['sessions']['avg_score'] ? number_format($stats['sessions']['avg_score'], 2) : 0; ?></div>
                        <div class="label">Средний балл</div>
                    </div>
                </div>
                
                <?php if (!empty($scoreDistribution)): ?>
                    <div class="chart-container">
                        <h3 style="margin-bottom: 15px;">📊 Распределение баллов</h3>
                        <div class="bar-chart">
                            <?php 
                            $maxCount = max(array_column($scoreDistribution, 'count'));
                            foreach ($scoreDistribution as $item): 
                                $height = $maxCount > 0 ? ($item['count'] / $maxCount) * 180 : 0;
                            ?>
                                <div class="bar" style="height: <?php echo $height; ?>px;">
                                    <span class="value"><?php echo $item['count']; ?></span>
                                    <span class="label"><?php echo number_format($item['total_score'], 1); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($recentSessions)): ?>
                    <div class="section">
                        <h3>🕐 Последние прохождения</h3>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Пользователь</th>
                                    <th>Баллы</th>
                                    <th>Процент</th>
                                    <th>Дата</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSessions as $session): 
                                    $percentage = $session['max_possible_score'] > 0 ? 
                                                  round(($session['total_score'] / $session['max_possible_score']) * 100, 2) : 0;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($session['user_name']); ?></td>
                                        <td><?php echo number_format($session['total_score'], 2); ?></td>
                                        <td><?php echo $percentage; ?>%</td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($session['completed_at'])); ?></td>
                                        <td>
                                            <a href="results.php?session_id=<?php echo $session['id']; ?>&test_id=<?php echo $testId; ?>" 
                                               class="btn btn-sm btn-info">📋 Детали</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>