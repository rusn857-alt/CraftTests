<?php
/**
 * Просмотр результатов тестирования
 */

require_once '../config.php';

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    redirect('/admin/login.php');
}

$testId = (int)($_GET['test_id'] ?? 0);
$sessionId = (int)($_GET['session_id'] ?? 0);
$action = $_GET['action'] ?? '';

$db = Database::getInstance();
$testManager = new TestManager();

// Получаем список тестов для фильтра
$tests = $db->fetchAll(
    "SELECT id, title FROM tests ORDER BY created_at DESC"
);

// Получаем результаты
$where = [];
$params = [];

if ($testId > 0) {
    $where[] = "ts.test_id = ?";
    $params[] = $testId;
}

if ($action === 'export' && $testId > 0) {
    // Экспорт результатов в CSV
    $results = $db->fetchAll(
        "SELECT ts.id, ts.started_at, ts.completed_at, ts.total_score, ts.max_possible_score,
                u.name as user_name, u.email as user_email, t.title as test_title
         FROM test_sessions ts
         JOIN users u ON ts.user_id = u.id
         JOIN tests t ON ts.test_id = t.id
         WHERE ts.status = 'completed' " . (!empty($where) ? "AND " . implode(" AND ", $where) : "") . "
         ORDER BY ts.completed_at DESC",
        $params
    );
    
    // Отправка CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="results_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Пользователь', 'Email', 'Тест', 'Баллы', 'Максимум', 'Процент', 'Дата']);
    
    foreach ($results as $row) {
        $percentage = $row['max_possible_score'] > 0 ? 
                      round(($row['total_score'] / $row['max_possible_score']) * 100, 2) : 0;
        fputcsv($output, [
            $row['id'],
            $row['user_name'],
            $row['user_email'] ?? '',
            $row['test_title'],
            $row['total_score'],
            $row['max_possible_score'],
            $percentage . '%',
            date('d.m.Y H:i', strtotime($row['completed_at']))
        ]);
    }
    fclose($output);
    exit;
}

// Если нужно показать детали конкретной сессии
if ($sessionId > 0) {
    $session = $db->fetchOne(
        "SELECT ts.*, u.name as user_name, u.email as user_email, t.title as test_title,
                t.settings as test_settings
         FROM test_sessions ts
         JOIN users u ON ts.user_id = u.id
         JOIN tests t ON ts.test_id = t.id
         WHERE ts.id = ?",
        [$sessionId]
    );
    
    if (!$session) {
        $_SESSION['message'] = 'Сессия не найдена';
        $_SESSION['message_type'] = 'danger';
        redirect('/admin/results.php' . ($testId > 0 ? '?test_id=' . $testId : ''));
    }
    
    // Получаем все вопросы теста
    $questions = $db->fetchAll(
        "SELECT q.* FROM questions q
         WHERE q.test_id = ?
         ORDER BY q.sort_order ASC, q.id ASC",
        [$session['test_id']]
    );
    
    // Получаем ответы пользователя
    $answers = $db->fetchAll(
        "SELECT ua.*, q.text as question_text, q.type as question_type,
                ao.text as option_text, ao.is_correct as option_is_correct
         FROM user_answers ua
         JOIN questions q ON ua.question_id = q.id
         LEFT JOIN answer_options ao ON ua.answer_option_id = ao.id
         WHERE ua.session_id = ?
         ORDER BY q.sort_order ASC, ua.id ASC",
        [$sessionId]
    );
    
    // Группируем ответы по вопросам
    $groupedAnswers = [];
    foreach ($answers as $answer) {
        $qId = $answer['question_id'];
        if (!isset($groupedAnswers[$qId])) {
            $groupedAnswers[$qId] = [
                'question' => null,
                'answers' => [],
                'is_correct' => false
            ];
        }
        $groupedAnswers[$qId]['answers'][] = $answer;
    }
    
    // Заполняем информацию о вопросах
    foreach ($questions as $question) {
        if (isset($groupedAnswers[$question['id']])) {
            $groupedAnswers[$question['id']]['question'] = $question;
            
            // Определяем правильность ответа
            $questionType = $question['type'];
            $userAnswers = $groupedAnswers[$question['id']]['answers'];
            
            if ($questionType === 'single') {
                // Для одиночного выбора
                $optionId = $userAnswers[0]['answer_option_id'] ?? null;
                if ($optionId) {
                    $option = $db->fetchOne(
                        "SELECT is_correct FROM answer_options WHERE id = ?",
                        [$optionId]
                    );
                    $groupedAnswers[$question['id']]['is_correct'] = $option && (bool)$option['is_correct'];
                }
            } elseif ($questionType === 'multiple') {
                // Для множественного выбора
                $selectedIds = [];
                foreach ($userAnswers as $ua) {
                    if ($ua['answer_option_id']) {
                        $selectedIds[] = (int)$ua['answer_option_id'];
                    }
                }
                
                $correctOptions = $db->fetchAll(
                    "SELECT id FROM answer_options WHERE question_id = ? AND is_correct = 1",
                    [$question['id']]
                );
                $correctIds = array_column($correctOptions, 'id');
                
                sort($selectedIds);
                sort($correctIds);
                
                $groupedAnswers[$question['id']]['is_correct'] = ($selectedIds == $correctIds && !empty($correctIds));
            } else {
                // Для текстовых и числовых ответов - всегда не правильно (оценивается вручную)
                $groupedAnswers[$question['id']]['is_correct'] = false;
            }
        }
    }
}

// Получение списка сессий
$sql = "SELECT ts.id, ts.started_at, ts.completed_at, ts.total_score, ts.max_possible_score,
               u.name as user_name, u.email as user_email, t.title as test_title,
               t.id as test_id
        FROM test_sessions ts
        JOIN users u ON ts.user_id = u.id
        JOIN tests t ON ts.test_id = t.id
        WHERE ts.status = 'completed'";
        
if (!empty($where)) {
    $sql .= " AND " . implode(" AND ", $where);
}
$sql .= " ORDER BY ts.completed_at DESC";

$sessions = $db->fetchAll($sql, $params);

// Статистика
$stats = [
    'total' => count($sessions),
    'avg_score' => 0,
    'max_score' => 0,
    'min_score' => 0
];

if (!empty($sessions)) {
    $scores = array_column($sessions, 'total_score');
    $stats['avg_score'] = round(array_sum($scores) / count($scores), 2);
    $stats['max_score'] = max($scores);
    $stats['min_score'] = min($scores);
}

$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Результаты тестирования</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .result-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            background: #fff;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-item .number {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .stat-item .label {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .session-row {
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .session-row:hover {
            background: #f8f9fa;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .filter-form .form-group {
            margin-bottom: 0;
        }
        
        .filter-form .form-group label {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .filter-form .form-group select,
        .filter-form .form-group input {
            min-width: 200px;
        }
        
        .export-btn {
            margin-left: auto;
        }
        
        /* Стили для деталей */
        .session-details {
            margin-top: 20px;
        }
        
        .session-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .session-info .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .session-info .info-item .label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .session-info .info-item .value {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .answer-item {
            padding: 10px 15px;
            margin-bottom: 8px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #ddd;
        }
        
        .answer-item.correct {
            background: #d4edda;
            border-left-color: #28a745;
        }
        
        .answer-item.incorrect {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        
        .answer-item .answer-content {
            flex: 1;
        }
        
        .answer-item .answer-content .question-text {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 3px;
        }
        
        .answer-item .answer-content .answer-text {
            font-size: 14px;
            color: #555;
        }
        
        .answer-item .status-icon {
            font-size: 20px;
            margin-left: 15px;
            flex-shrink: 0;
        }
        
        .answer-item .multiple-options {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 3px;
        }
        
        .answer-item .multiple-options .option-tag {
            background: #fff;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            border: 1px solid #ddd;
        }
        
        .answer-item .multiple-options .option-tag.correct-option {
            border-color: #28a745;
            background: #d4edda;
        }
        
        .answer-item .multiple-options .option-tag.incorrect-option {
            border-color: #dc3545;
            background: #f8d7da;
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-form .form-group select,
            .filter-form .form-group input {
                min-width: 100%;
            }
            
            .export-btn {
                margin-left: 0;
            }
            
            .session-info {
                grid-template-columns: 1fr;
            }
            
            .answer-item {
                flex-direction: column;
                align-items: stretch;
            }
            
            .answer-item .status-icon {
                margin-left: 0;
                margin-top: 5px;
                text-align: right;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <header class="admin-header">
            <div class="container">
                <div class="header-content">
                    <h1>📈 Результаты тестирования</h1>
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
                    <li><a href="results.php" class="active">📈 Результаты</a></li>
                </ul>
            </div>
        </nav>
        
        <main class="admin-main">
            <div class="container">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if ($sessionId > 0 && isset($session)): ?>
                    <!-- Детали сессии -->
                    <div class="section">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
                            <h2>📋 Детали прохождения</h2>
                            <a href="results.php<?php echo $testId > 0 ? '?test_id=' . $testId : ''; ?>" class="btn btn-secondary">← Назад к списку</a>
                        </div>
                        
                        <div class="session-info">
                            <div class="info-item">
                                <span class="label">Тест</span>
                                <span class="value"><?php echo htmlspecialchars($session['test_title']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Пользователь</span>
                                <span class="value"><?php echo htmlspecialchars($session['user_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Email</span>
                                <span class="value"><?php echo htmlspecialchars($session['user_email'] ?? 'Не указан'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Баллы</span>
                                <span class="value"><?php echo number_format($session['total_score'], 2); ?> / <?php echo number_format($session['max_possible_score'], 2); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Процент</span>
                                <span class="value"><?php echo $session['max_possible_score'] > 0 ? round(($session['total_score'] / $session['max_possible_score']) * 100, 2) : 0; ?>%</span>
                            </div>
                            <div class="info-item">
                                <span class="label">Дата</span>
                                <span class="value"><?php echo date('d.m.Y H:i', strtotime($session['completed_at'])); ?></span>
                            </div>
                        </div>
                        
                        <h3 style="margin-bottom: 15px;">📝 Ответы пользователя</h3>
                        
                        <?php if (!empty($groupedAnswers)): ?>
                            <?php foreach ($questions as $question): ?>
                                <?php 
                                $qData = $groupedAnswers[$question['id']] ?? null;
                                if (!$qData) continue;
                                
                                $isCorrect = $qData['is_correct'];
                                $userAnswers = $qData['answers'];
                                $answerDisplay = '';
                                
                                if ($question['type'] === 'single') {
                                    $optionId = $userAnswers[0]['answer_option_id'] ?? null;
                                    if ($optionId) {
                                        $opt = $db->fetchOne(
                                            "SELECT text FROM answer_options WHERE id = ?",
                                            [$optionId]
                                        );
                                        $answerDisplay = $opt['text'] ?? 'Нет ответа';
                                    } else {
                                        $answerDisplay = 'Нет ответа';
                                    }
                                } elseif ($question['type'] === 'multiple') {
                                    $selectedTexts = [];
                                    foreach ($userAnswers as $ua) {
                                        if ($ua['answer_option_id']) {
                                            $opt = $db->fetchOne(
                                                "SELECT text, is_correct FROM answer_options WHERE id = ?",
                                                [$ua['answer_option_id']]
                                            );
                                            if ($opt) {
                                                $selectedTexts[] = [
                                                    'text' => $opt['text'],
                                                    'is_correct' => (bool)$opt['is_correct']
                                                ];
                                            }
                                        }
                                    }
                                    $answerDisplay = $selectedTexts;
                                } else {
                                    $answerDisplay = $userAnswers[0]['answer_text'] ?? 'Нет ответа';
                                }
                                ?>
                                <div class="answer-item <?php echo $isCorrect ? 'correct' : 'incorrect'; ?>">
                                    <div class="answer-content">
                                        <div class="question-text">
                                            <?php echo htmlspecialchars($question['text']); ?>
                                            <span style="font-size: 12px; color: #999; font-weight: 400;">
                                                (<?php echo $question['type'] === 'single' ? 'Одиночный' : ($question['type'] === 'multiple' ? 'Множественный' : 'Свободный'); ?>)
                                            </span>
                                        </div>
                                        <?php if (is_array($answerDisplay)): ?>
                                            <div class="multiple-options">
                                                <?php foreach ($answerDisplay as $opt): ?>
                                                    <span class="option-tag <?php echo $opt['is_correct'] ? 'correct-option' : 'incorrect-option'; ?>">
                                                        <?php echo htmlspecialchars($opt['text']); ?>
                                                        <?php echo $opt['is_correct'] ? '✅' : '❌'; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="answer-text">
                                                <strong>Ответ:</strong> <?php echo htmlspecialchars($answerDisplay); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="status-icon">
                                        <?php echo $isCorrect ? '✅' : '❌'; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #999; text-align: center; padding: 20px 0;">Нет сохраненных ответов</p>
                        <?php endif; ?>
                    </div>
                    
                <?php else: ?>
                    <!-- Фильтры и статистика -->
                    <div class="section">
                        <form method="GET" action="" class="filter-form">
                            <div class="form-group">
                                <label for="test_id">Фильтр по тесту</label>
                                <select id="test_id" name="test_id" class="form-control" onchange="this.form.submit()">
                                    <option value="">Все тесты</option>
                                    <?php foreach ($tests as $test): ?>
                                        <option value="<?php echo $test['id']; ?>" <?php echo $testId == $test['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($test['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if ($testId > 0): ?>
                                <a href="results.php?action=export&test_id=<?php echo $testId; ?>" class="btn btn-success export-btn">
                                    📥 Экспорт CSV
                                </a>
                            <?php endif; ?>
                        </form>
                        
                        <?php if (!empty($sessions)): ?>
                            <div class="result-stats">
                                <div class="stat-item">
                                    <div class="number"><?php echo $stats['total']; ?></div>
                                    <div class="label">Всего прохождений</div>
                                </div>
                                <div class="stat-item">
                                    <div class="number"><?php echo $stats['avg_score']; ?></div>
                                    <div class="label">Средний балл</div>
                                </div>
                                <div class="stat-item">
                                    <div class="number"><?php echo $stats['max_score']; ?></div>
                                    <div class="label">Максимальный балл</div>
                                </div>
                                <div class="stat-item">
                                    <div class="number"><?php echo $stats['min_score']; ?></div>
                                    <div class="label">Минимальный балл</div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Пользователь</th>
                                            <th>Тест</th>
                                            <th>Баллы</th>
                                            <th>Процент</th>
                                            <th>Дата</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions as $session): 
                                            $percentage = $session['max_possible_score'] > 0 ? 
                                                          round(($session['total_score'] / $session['max_possible_score']) * 100, 2) : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($session['user_name']); ?></td>
                                                <td><?php echo htmlspecialchars($session['test_title']); ?></td>
                                                <td><?php echo number_format($session['total_score'], 2); ?></td>
                                                <td><?php echo $percentage; ?>%</td>
                                                <td><?php echo date('d.m.Y H:i', strtotime($session['completed_at'])); ?></td>
                                                <td>
                                                    <a href="results.php?session_id=<?php echo $session['id']; ?><?php echo $testId > 0 ? '&test_id=' . $testId : ''; ?>" 
                                                       class="btn btn-sm btn-info">📋 Детали</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; color: #999; padding: 40px 0;">
                                Нет завершенных тестов
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>