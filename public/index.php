<?php
/**
 * Главная страница публичной части
 * Перенаправляет на список доступных тестов
 */

require_once '../config.php';

// Получаем список активных тестов
$db = Database::getInstance();
$tests = $db->fetchAll(
    "SELECT id, title, description, slug, 
            (SELECT COUNT(*) FROM questions WHERE test_id = tests.id) as questions_count
     FROM tests 
     WHERE status = 'active' 
     ORDER BY created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система тестирования</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .public-wrapper {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .public-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .public-header h1 {
            font-size: 36px;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .public-header p {
            color: #7f8c8d;
            font-size: 18px;
        }
        
        .test-list {
            display: grid;
            gap: 20px;
        }
        
        .test-card {
            background: #fff;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 4px solid #4CAF50;
        }
        
        .test-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .test-card h2 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 22px;
        }
        
        .test-card .description {
            color: #666;
            margin-bottom: 15px;
        }
        
        .test-card .meta {
            display: flex;
            gap: 20px;
            color: #999;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .test-card .meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-start {
            display: inline-block;
            padding: 10px 30px;
            background: #4CAF50;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .btn-start:hover {
            background: #45a049;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="public-wrapper">
        <div class="public-header">
            <h1>📚 Система тестирования</h1>
            <p>Выберите тест для прохождения</p>
        </div>
        
        <?php if (empty($tests)): ?>
            <div class="empty-state">
                <div class="icon">📝</div>
                <h3>Нет доступных тестов</h3>
                <p>На данный момент нет активных тестов для прохождения</p>
            </div>
        <?php else: ?>
            <div class="test-list">
                <?php foreach ($tests as $test): ?>
                    <div class="test-card">
                        <h2><?php echo htmlspecialchars($test['title']); ?></h2>
                        <?php if ($test['description']): ?>
                            <div class="description"><?php echo htmlspecialchars($test['description']); ?></div>
                        <?php endif; ?>
                        <div class="meta">
                            <span>📋 <?php echo $test['questions_count']; ?> вопросов</span>
                        </div>
                        <a href="take.php?slug=<?php echo htmlspecialchars($test['slug']); ?>" class="btn-start">
                            Начать тест →
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>