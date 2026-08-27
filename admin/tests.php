<?php
/**
 * Управление тестами
 */

require_once '../config.php';

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    redirect('/admin/login.php');
}

$testManager = new TestManager();
$db = Database::getInstance();

// Обработка действий
$action = $_GET['action'] ?? '';
$testId = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $testId > 0) {
    if ($testManager->deleteTest($testId)) {
        $_SESSION['message'] = 'Тест успешно удален';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Ошибка удаления теста';
        $_SESSION['message_type'] = 'danger';
    }
    redirect('/admin/tests.php');
}

if ($action === 'status' && $testId > 0) {
    $status = $_GET['status'] ?? '';
    if ($testManager->changeStatus($testId, $status)) {
        $_SESSION['message'] = 'Статус теста изменен';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Ошибка изменения статуса';
        $_SESSION['message_type'] = 'danger';
    }
    redirect('/admin/tests.php');
}

// Фильтр по статусу
$statusFilter = $_GET['status'] ?? null;
$tests = $testManager->getTests($statusFilter);

// Статистика
$totalTests = count($tests);
$activeTests = count(array_filter($tests, function($t) { return $t['status'] === 'active'; }));
$draftTests = count(array_filter($tests, function($t) { return $t['status'] === 'draft'; }));
$archivedTests = count(array_filter($tests, function($t) { return $t['status'] === 'archived'; }));

// Сообщения
$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление тестами - Система тестирования</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-draft {
            background: #ffc107;
            color: #856404;
        }
        .badge-active {
            background: #28a745;
            color: #fff;
        }
        .badge-archived {
            background: #6c757d;
            color: #fff;
        }
        .btn-info {
            background: #17a2b8;
            color: #fff;
        }
        .btn-info:hover {
            background: #138496;
        }
        .btn-stats {
            background: #6c5ce7;
            color: #fff;
        }
        .btn-stats:hover {
            background: #5a4bd1;
        }
        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .actions .btn {
            font-size: 14px;
            padding: 5px 8px;
            line-height: 1;
        }
        .test-title {
            font-weight: 600;
            color: #2c3e50;
        }
        .test-slug {
            font-size: 12px;
            color: #999;
            font-family: monospace;
        }
        .table td {
            vertical-align: middle;
        }
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .filter-bar .left {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filter-bar .right {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .stats-badges {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stats-badges .stat-item {
            background: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .stats-badges .stat-item .number {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
        }
        .stats-badges .stat-item .label {
            font-size: 13px;
            color: #7f8c8d;
        }
        .stats-badges .stat-item .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        .dot-draft { background: #ffc107; }
        .dot-active { background: #28a745; }
        .dot-archived { background: #6c757d; }
        
        @media (max-width: 768px) {
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-bar .left,
            .filter-bar .right {
                width: 100%;
            }
            .stats-badges .stat-item {
                flex: 1;
                justify-content: center;
            }
            .table {
                font-size: 13px;
            }
            .table th,
            .table td {
                padding: 8px 10px;
            }
            .actions .btn {
                font-size: 12px;
                padding: 4px 6px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Header -->
        <header class="admin-header">
            <div class="container">
                <div class="header-content">
                    <h1>📝 Управление тестами</h1>
                    <div class="user-info">
                        <span>👤 <?php echo htmlspecialchars($_SESSION['admin_login']); ?></span>
                        <a href="logout.php" class="btn btn-sm btn-danger">Выход</a>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Navigation -->
        <nav class="admin-nav">
            <div class="container">
                <ul>
                    <li><a href="index.php">📊 Главная</a></li>
                    <li><a href="tests.php" class="active">📝 Тесты</a></li>
                    <li><a href="/public/index.php">📝Список</a></li>
                    <li><a href="results.php">📈 Результаты</a></li>
                    <li><a href="import_json.php">📈 Загрузить тест</a></li>
                </ul>
            </div>
        </nav>
        
        <!-- Main Content -->
        <main class="admin-main">
            <div class="container">
                <!-- Сообщения -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <!-- Статистика -->
                <div class="stats-badges">
                    <div class="stat-item">
                        <span class="number"><?php echo $totalTests; ?></span>
                        <span class="label">Всего тестов</span>
                    </div>
                    <div class="stat-item">
                        <span class="dot dot-active"></span>
                        <span class="number"><?php echo $activeTests; ?></span>
                        <span class="label">Активных</span>
                    </div>
                    <div class="stat-item">
                        <span class="dot dot-draft"></span>
                        <span class="number"><?php echo $draftTests; ?></span>
                        <span class="label">Черновиков</span>
                    </div>
                    <div class="stat-item">
                        <span class="dot dot-archived"></span>
                        <span class="number"><?php echo $archivedTests; ?></span>
                        <span class="label">Архивных</span>
                    </div>
                </div>
                
                <!-- Действия -->
                <div class="section">
                    <div class="filter-bar">
                        <div class="left">
                            <a href="test_edit.php" class="btn btn-primary">➕ Создать тест</a>
                        </div>
                        <div class="right">
                            <select onchange="window.location.href='?status=' + this.value" class="form-control" style="width: auto; display: inline-block;">
                                <option value="">Все статусы</option>
                                <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Черновик</option>
                                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Активный</option>
                                <option value="archived" <?php echo $statusFilter === 'archived' ? 'selected' : ''; ?>>Архивный</option>
                            </select>
                        </div>
                    </div>
                    
                    <?php if (empty($tests)): ?>
                        <p style="color: #999; text-align: center; padding: 40px 0;">
                            Нет созданных тестов. <a href="test_edit.php">Создайте первый тест</a>
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="min-width: 200px;">Название</th>
                                        <th style="min-width: 100px;">Статус</th>
                                        <th style="min-width: 80px; text-align: center;">Вопросов</th>
                                        <th style="min-width: 120px;">Создан</th>
                                        <th style="min-width: 200px;">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tests as $test): ?>
                                        <tr>
                                            <td>
                                                <div class="test-title"><?php echo htmlspecialchars($test['title']); ?></div>
                                                <div class="test-slug">/take/<?php echo htmlspecialchars($test['slug']); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $test['status']; ?>">
                                                    <?php 
                                                        $statusLabels = [
                                                            'draft' => 'Черновик',
                                                            'active' => 'Активный',
                                                            'archived' => 'Архивный'
                                                        ];
                                                        echo $statusLabels[$test['status']] ?? $test['status'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php echo $test['questions_count'] ?? 0; ?>
                                            </td>
                                            <td>
                                                <?php echo date('d.m.Y', strtotime($test['created_at'])); ?>
                                                <br>
                                                <small style="color: #999;"><?php echo htmlspecialchars($test['created_by_name'] ?? 'Unknown'); ?></small>
                                            </td>
                                            <td>
                                                <div class="actions">
                                                    <a href="test_edit.php?id=<?php echo $test['id']; ?>" 
                                                       class="btn btn-sm btn-secondary" 
                                                       title="Редактировать тест">✏️</a>
                                                    
                                                    <a href="questions.php?test_id=<?php echo $test['id']; ?>" 
                                                       class="btn btn-sm btn-info" 
                                                       title="Управление вопросами">📋</a>
                                                    
                                                    <a href="test_stats.php?test_id=<?php echo $test['id']; ?>" 
                                                       class="btn btn-sm btn-stats" 
                                                       title="Статистика теста">📊</a>
                                                    
                                                    <?php if ($test['status'] === 'draft'): ?>
                                                        <a href="?action=status&id=<?php echo $test['id']; ?>&status=active" 
                                                           class="btn btn-sm btn-success" 
                                                           title="Активировать тест"
                                                           onclick="return confirm('Активировать тест? Он станет доступен для прохождения.')">▶️</a>
                                                    <?php elseif ($test['status'] === 'active'): ?>
                                                        <a href="?action=status&id=<?php echo $test['id']; ?>&status=draft" 
                                                           class="btn btn-sm btn-warning" 
                                                           title="Перевести в черновик"
                                                           onclick="return confirm('Перевести в черновик? Тест станет недоступен для прохождения.')">⏸️</a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="?action=delete&id=<?php echo $test['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       title="Удалить тест"
                                                       onclick="return confirm('Удалить тест? Это действие нельзя отменить! Будут удалены все вопросы и ответы.')">🗑️</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>