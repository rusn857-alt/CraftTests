<?php
/**
 * Управление вопросами теста
 */

require_once '../config.php';

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    redirect('/admin/login.php');
}

$testId = (int)($_GET['test_id'] ?? 0);
$questionId = (int)($_GET['question_id'] ?? 0);
$action = $_GET['action'] ?? '';

$testManager = new TestManager();
$db = Database::getInstance();

// Проверка существования теста
$test = $testManager->getTest($testId);
if (!$test) {
    $_SESSION['message'] = 'Тест не найден';
    $_SESSION['message_type'] = 'danger';
    redirect('/admin/tests.php');
}

// Обработка AJAX запросов (создание и обновление)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $isEdit = isset($_POST['question_id']) && $_POST['question_id'] > 0;
    $questionId = (int)($_POST['question_id'] ?? 0);
    $type = $_POST['type'] ?? 'single';
    $text = trim($_POST['text'] ?? '');
    $points = (float)($_POST['points'] ?? 1);
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $optionTexts = $_POST['option_text'] ?? [];
    $optionCorrect = $_POST['option_correct'] ?? [];
    $optionPoints = $_POST['option_points'] ?? [];
    
    if (empty($text)) {
        echo json_encode(['success' => false, 'error' => 'Введите текст вопроса']);
        exit;
    }
    
    try {
        $db->beginTransaction();
        
        if ($isEdit) {
            // Обновление вопроса
            $db->query(
                "UPDATE questions SET type = ?, text = ?, points = ?, sort_order = ? WHERE id = ? AND test_id = ?",
                [$type, $text, $points, $sortOrder, $questionId, $testId]
            );
            
            // Удаление старых вариантов
            $db->query("DELETE FROM answer_options WHERE question_id = ?", [$questionId]);
            
            $newQuestionId = $questionId;
        } else {
            // Создание вопроса
            $db->query(
                "INSERT INTO questions (test_id, type, text, points, sort_order) VALUES (?, ?, ?, ?, ?)",
                [$testId, $type, $text, $points, $sortOrder]
            );
            $newQuestionId = (int)$db->lastInsertId();
        }
        
        // Сохранение вариантов ответов (для single и multiple)
        if (in_array($type, ['single', 'multiple']) && !empty($optionTexts)) {
            foreach ($optionTexts as $index => $optionText) {
                $optionText = trim($optionText);
                if (empty($optionText)) continue;
                
                $isCorrect = isset($optionCorrect[$index]) && $optionCorrect[$index] == 1 ? 1 : 0;
                $optPoints = isset($optionPoints[$index]) && $optionPoints[$index] !== '' ? 
                            (float)$optionPoints[$index] : null;
                
                $db->query(
                    "INSERT INTO answer_options (question_id, text, is_correct, points, sort_order) 
                     VALUES (?, ?, ?, ?, ?)",
                    [$newQuestionId, $optionText, $isCorrect, $optPoints, $index]
                );
            }
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => $isEdit ? 'Вопрос обновлен' : 'Вопрос создан', 
            'id' => $newQuestionId,
            'is_edit' => $isEdit
        ]);
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => 'Ошибка сохранения: ' . $e->getMessage()]);
        exit;
    }
}

// Удаление вопроса
if ($action === 'delete' && $questionId > 0) {
    try {
        $db->query("DELETE FROM questions WHERE id = ? AND test_id = ?", [$questionId, $testId]);
        $_SESSION['message'] = 'Вопрос удален';
        $_SESSION['message_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['message'] = 'Ошибка удаления вопроса';
        $_SESSION['message_type'] = 'danger';
    }
    redirect("/admin/questions.php?test_id={$testId}");
}

// Получение вопросов теста
$questions = $db->fetchAll(
    "SELECT q.*, 
            (SELECT COUNT(*) FROM answer_options WHERE question_id = q.id) as options_count
     FROM questions q
     WHERE q.test_id = ?
     ORDER BY q.sort_order ASC, q.id ASC",
    [$testId]
);

// Статистика по вопросам
$stats = [
    'total' => count($questions),
    'single' => 0,
    'multiple' => 0,
    'text' => 0,
    'number' => 0
];

foreach ($questions as $q) {
    $stats[$q['type']]++;
}

$typeLabels = [
    'single' => 'Одиночный выбор',
    'multiple' => 'Множественный выбор',
    'text' => 'Текстовый ответ',
    'number' => 'Числовой ответ'
];

$typeIcons = [
    'single' => '⭕',
    'multiple' => '☑️',
    'text' => '📝',
    'number' => '🔢'
];

$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вопросы теста - <?php echo htmlspecialchars($test['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Компактная форма */
        #questionFormContainer .form-control {
            font-size: 13px;
            padding: 3px 8px;
            height: 30px;
            border-radius: 4px;
            border: 1px solid #ced4da;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
        }
        
        #questionFormContainer textarea.form-control {
            height: auto;
            min-height: 40px;
        }
        
        #questionFormContainer .option-row .form-control {
            height: 26px;
            font-size: 12px;
            padding: 2px 6px;
        }
        
        #questionFormContainer .option-row {
            padding: 3px 6px;
            margin-bottom: 3px;
        }
        
        /* Стили для списка вопросов */
        .questions-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }
        
        .question-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px 20px;
            transition: box-shadow 0.3s;
        }
        
        .question-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .question-card.editing {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.15);
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .question-number {
            font-weight: 600;
            font-size: 15px;
            color: #2c3e50;
        }
        
        .question-type {
            display: inline-block;
            margin-left: 10px;
            padding: 2px 10px;
            background: #e9ecef;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 400;
            color: #495057;
        }
        
        .question-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .question-points {
            font-size: 13px;
            color: #6c757d;
            margin-right: 10px;
        }
        
        .question-text {
            font-size: 15px;
            padding: 8px 0;
            color: #333;
            line-height: 1.6;
        }
        
        .question-options {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #f0f0f0;
        }
        
        .option-item {
            padding: 4px 10px;
            margin-bottom: 3px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .option-item.correct {
            background: #d4edda;
        }
        
        .option-marker {
            font-size: 14px;
            width: 24px;
        }
        
        .option-points {
            font-size: 12px;
            color: #28a745;
            margin-left: auto;
        }
        
        /* Стили для inline редактирования */
        .inline-edit-form {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border: 1px solid #dee2e6;
            display: none;
        }
        
        .inline-edit-form.active {
            display: block;
        }
        
        .inline-edit-form .form-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .inline-edit-form .form-row .form-group {
            flex: 1;
            min-width: 120px;
            margin-bottom: 0;
        }
        
        .inline-edit-form .option-row {
            display: flex;
            gap: 8px;
            align-items: center;
            padding: 6px 10px;
            background: #fff;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
            margin-bottom: 5px;
        }
        
        .inline-edit-form .option-row .option-input {
            flex: 1;
        }
        
        .inline-edit-form .option-row .option-actions {
            display: flex;
            gap: 5px;
            align-items: center;
            flex-shrink: 0;
        }
        
        .inline-edit-form .option-row .option-actions label {
            font-size: 12px;
            font-weight: 400;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 3px;
            white-space: nowrap;
        }
        
        .inline-edit-form .option-row .option-actions input[type="number"] {
            width: 55px;
        }
        
        .inline-edit-form .form-actions {
            margin-top: 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* Адаптив */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .inline-edit-form .option-row {
                flex-wrap: wrap;
            }
            
            .inline-edit-form .option-row .option-actions {
                flex-wrap: wrap;
                width: 100%;
            }
            
            .inline-edit-form .option-row .option-actions input[type="number"] {
                width: 100%;
            }
            
            .question-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 576px) {
            .inline-edit-form {
                padding: 10px;
            }
            
            .inline-edit-form .option-row {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <header class="admin-header">
            <div class="container">
                <div class="header-content">
                    <h1>📋 Вопросы: <?php echo htmlspecialchars($test['title']); ?></h1>
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
                    <li><a href="questions.php?test_id=<?php echo $testId; ?>" class="active">📋 Вопросы</a></li>
                    <li><a href="results.php?test_id=<?php echo $testId; ?>">📈 Результаты</a></li>
                </ul>
            </div>
        </nav>
        
        <main class="admin-main">
            <div class="container">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <!-- Статистика -->
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Всего вопросов</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['single']; ?></div>
                        <div class="stat-label">Одиночный выбор</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['multiple']; ?></div>
                        <div class="stat-label">Множественный выбор</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['text'] + $stats['number']; ?></div>
                        <div class="stat-label">Свободный ответ</div>
                    </div>
                </div>
                
                <!-- Действия -->
                <div class="section">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
                        <div>
                            <button id="toggleFormBtn" class="btn btn-primary">➕ Добавить вопрос</button>
                            <a href="test_edit.php?id=<?php echo $testId; ?>" class="btn btn-secondary">⬅️ Назад к тесту</a>
                        </div>
                    </div>
                    
                    <!-- КОМПАКТНАЯ ФОРМА ДОБАВЛЕНИЯ ВОПРОСА -->
                    <div id="questionFormContainer" style="display: none; background: #f8f9fa; padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #dee2e6;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <h4 style="margin: 0; font-size: 14px; color: #2c3e50; font-weight: 600;">✏️ Новый вопрос</h4>
                            <button type="button" id="cancelFormBtn" class="btn btn-sm btn-secondary" style="padding: 0 10px; font-size: 13px; height: 26px; line-height: 26px;">✖</button>
                        </div>
                        
                        <form id="questionForm" method="POST">
                            <input type="hidden" name="ajax" value="1">
                            <input type="hidden" name="question_id" value="0">
                            
                            <!-- Строка: тип + баллы + сортировка -->
                            <div style="display: flex; gap: 8px; margin-bottom: 6px; flex-wrap: wrap;">
                                <div style="flex: 2; min-width: 130px;">
                                    <select id="q_type" name="type" class="form-control" style="font-size: 13px; padding: 3px 8px; height: 30px;" required>
                                        <?php foreach ($typeLabels as $key => $label): ?>
                                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div style="flex: 1; min-width: 70px;">
                                    <input type="number" id="q_points" name="points" class="form-control" value="1" step="0.5" min="0" 
                                           style="font-size: 13px; padding: 3px 8px; height: 30px;" placeholder="Баллы">
                                </div>
                                <div style="flex: 1; min-width: 70px;">
                                    <input type="number" id="q_sort_order" name="sort_order" class="form-control" value="0" step="1" 
                                           style="font-size: 13px; padding: 3px 8px; height: 30px;" placeholder="Порядок">
                                </div>
                            </div>
                            
                            <!-- Текст вопроса -->
                            <div style="margin-bottom: 6px;">
                                <textarea id="q_text" name="text" class="form-control" rows="2" required 
                                          style="font-size: 14px; padding: 5px 10px; resize: vertical; min-height: 40px;" 
                                          placeholder="Введите текст вопроса..."></textarea>
                            </div>
                            
                            <!-- Варианты ответов -->
                            <div id="q_optionsBlock">
                                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 3px;">
                                    <span style="font-size: 12px; color: #6c757d; font-weight: 500;">📝 Варианты</span>
                                    <span style="font-size: 10px; color: #999;">(для одиночного/множественного выбора)</span>
                                    <button type="button" id="q_addOption" class="btn btn-sm btn-secondary" style="padding: 0 10px; font-size: 12px; height: 22px; line-height: 22px; margin-left: auto;">+</button>
                                </div>
                                
                                <div id="q_optionsContainer" style="display: flex; flex-direction: column; gap: 3px;">
                                    <div class="option-row" data-index="0" style="display: flex; gap: 5px; align-items: center; padding: 3px 6px; background: #fff; border-radius: 4px; border: 1px solid #e0e0e0;">
                                        <div style="flex: 1; min-width: 100px;">
                                            <input type="text" name="option_text[]" class="form-control" placeholder="Вариант ответа" 
                                                   style="font-size: 12px; padding: 2px 6px; height: 26px;">
                                        </div>
                                        <div style="display: flex; gap: 5px; align-items: center; flex-shrink: 0;">
                                            <label style="font-size: 12px; font-weight: 400; margin: 0; display: flex; align-items: center; gap: 2px; cursor: pointer; white-space: nowrap;">
                                                <input type="checkbox" name="option_correct[0]" value="1" style="margin: 0; width: 14px; height: 14px;">
                                                Правильный
                                            </label>
                                            <input type="number" name="option_points[0]" class="form-control" 
                                                   placeholder="Баллы" step="0.5" min="0" 
                                                   style="font-size: 12px; padding: 2px 4px; height: 26px; width: 55px;">
                                        </div>
                                        <button type="button" class="btn btn-danger btn-sm remove-option" 
                                                style="padding: 0 5px; font-size: 11px; height: 22px; line-height: 22px; flex-shrink: 0;">✕</button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Кнопки -->
                            <div style="margin-top: 8px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                <button type="submit" class="btn btn-primary" id="submitQuestionBtn" 
                                        style="padding: 3px 16px; font-size: 13px; height: 30px;">💾 Сохранить</button>
                                <button type="button" class="btn btn-secondary" id="cancelFormBtn2" 
                                        style="padding: 3px 12px; font-size: 13px; height: 30px;">Отмена</button>
                                <div id="formMessage" style="margin: 0; font-size: 13px;"></div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Список вопросов -->
                    <?php if (empty($questions)): ?>
                        <p style="color: #999; text-align: center; padding: 40px 0;">
                            В тесте пока нет вопросов. Нажмите "Добавить вопрос" чтобы создать первый вопрос.
                        </p>
                    <?php else: ?>
                        <div class="questions-list">
                            <?php foreach ($questions as $index => $question): 
                                // Получаем варианты для вопроса
                                $options = $db->fetchAll(
                                    "SELECT * FROM answer_options WHERE question_id = ? ORDER BY sort_order ASC",
                                    [$question['id']]
                                );
                            ?>
                                <div class="question-card" data-id="<?php echo $question['id']; ?>" id="question-<?php echo $question['id']; ?>">
                                    <div class="question-header">
                                        <div class="question-number">
                                            Вопрос <?php echo $index + 1; ?>
                                            <span class="question-type">
                                                <?php echo $typeIcons[$question['type']] ?? '❓'; ?>
                                                <?php echo $typeLabels[$question['type']] ?? $question['type']; ?>
                                            </span>
                                        </div>
                                        <div class="question-actions">
                                            <span class="question-points">Баллы: <?php echo number_format($question['points'], 2); ?></span>
                                            <button type="button" class="btn btn-sm btn-secondary edit-question-btn" 
                                                    data-id="<?php echo $question['id']; ?>" title="Редактировать">✏️</button>
                                            <a href="?action=delete&test_id=<?php echo $testId; ?>&question_id=<?php echo $question['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Удалить вопрос?')"
                                               title="Удалить">🗑️</a>
                                        </div>
                                    </div>
                                    <div class="question-text">
                                        <?php echo htmlspecialchars($question['text']); ?>
                                    </div>
                                    <div class="question-options">
                                        <?php if (in_array($question['type'], ['single', 'multiple'])): ?>
                                            <?php foreach ($options as $option): ?>
                                                <div class="option-item <?php echo $option['is_correct'] ? 'correct' : ''; ?>">
                                                    <span class="option-marker">
                                                        <?php echo $option['is_correct'] ? '✅' : '⬜'; ?>
                                                    </span>
                                                    <?php echo htmlspecialchars($option['text']); ?>
                                                    <?php if ($option['points'] !== null && $option['points'] != 0): ?>
                                                        <span class="option-points">(+<?php echo number_format($option['points'], 2); ?> баллов)</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php elseif ($question['type'] === 'text' || $question['type'] === 'number'): ?>
                                            <div style="padding: 10px; background: #f8f9fa; border-radius: 4px; color: #666;">
                                                <em>Свободный ответ (без вариантов)</em>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Inline форма редактирования -->
                                    <div class="inline-edit-form" id="edit-form-<?php echo $question['id']; ?>">
                                        <form class="edit-question-form" data-id="<?php echo $question['id']; ?>">
                                            <input type="hidden" name="ajax" value="1">
                                            <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                            
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <select name="type" class="form-control edit-type" style="font-size: 13px; padding: 3px 8px; height: 30px;">
                                                        <?php foreach ($typeLabels as $key => $label): ?>
                                                            <option value="<?php echo $key; ?>" <?php echo $question['type'] === $key ? 'selected' : ''; ?>>
                                                                <?php echo $label; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <input type="number" name="points" class="form-control" value="<?php echo $question['points']; ?>" 
                                                           step="0.5" min="0" style="font-size: 13px; padding: 3px 8px; height: 30px;" placeholder="Баллы">
                                                </div>
                                                <div class="form-group">
                                                    <input type="number" name="sort_order" class="form-control" value="<?php echo $question['sort_order']; ?>" 
                                                           step="1" style="font-size: 13px; padding: 3px 8px; height: 30px;" placeholder="Порядок">
                                                </div>
                                            </div>
                                            
                                            <div style="margin-bottom: 8px;">
                                                <textarea name="text" class="form-control" rows="2" required 
                                                          style="font-size: 14px; padding: 5px 10px; resize: vertical; min-height: 40px;"><?php echo htmlspecialchars($question['text']); ?></textarea>
                                            </div>
                                            
                                            <div class="edit-options-block">
                                                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 5px;">
                                                    <span style="font-size: 12px; color: #6c757d; font-weight: 500;">📝 Варианты</span>
                                                    <button type="button" class="btn btn-sm btn-secondary edit-add-option" style="padding: 0 10px; font-size: 12px; height: 22px; line-height: 22px; margin-left: auto;">+</button>
                                                </div>
                                                <div class="edit-options-container">
                                                    <?php if (empty($options)): ?>
                                                        <div class="option-row" data-index="0">
                                                            <div class="option-input">
                                                                <input type="text" name="option_text[]" class="form-control" placeholder="Вариант ответа" 
                                                                       style="font-size: 12px; padding: 2px 6px; height: 26px;">
                                                            </div>
                                                            <div class="option-actions">
                                                                <label>
                                                                    <input type="checkbox" name="option_correct[0]" value="1" style="width: 14px; height: 14px;">
                                                                    Правильный
                                                                </label>
                                                                <input type="number" name="option_points[0]" class="form-control" 
                                                                       placeholder="Баллы" step="0.5" min="0" 
                                                                       style="font-size: 12px; padding: 2px 4px; height: 26px; width: 55px;">
                                                                <button type="button" class="btn btn-danger btn-sm edit-remove-option" 
                                                                        style="padding: 0 5px; font-size: 11px; height: 22px; line-height: 22px;">✕</button>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <?php foreach ($options as $optIndex => $option): ?>
                                                            <div class="option-row" data-index="<?php echo $optIndex; ?>">
                                                                <div class="option-input">
                                                                    <input type="text" name="option_text[]" class="form-control" 
                                                                           value="<?php echo htmlspecialchars($option['text']); ?>" 
                                                                           placeholder="Вариант ответа" 
                                                                           style="font-size: 12px; padding: 2px 6px; height: 26px;">
                                                                </div>
                                                                <div class="option-actions">
                                                                    <label>
                                                                        <input type="checkbox" name="option_correct[<?php echo $optIndex; ?>]" value="1" 
                                                                               <?php echo $option['is_correct'] ? 'checked' : ''; ?> style="width: 14px; height: 14px;">
                                                                        Правильный
                                                                    </label>
                                                                    <input type="number" name="option_points[<?php echo $optIndex; ?>]" class="form-control" 
                                                                           value="<?php echo $option['points'] !== null ? $option['points'] : ''; ?>" 
                                                                           placeholder="Баллы" step="0.5" min="0" 
                                                                           style="font-size: 12px; padding: 2px 4px; height: 26px; width: 55px;">
                                                                    <button type="button" class="btn btn-danger btn-sm edit-remove-option" 
                                                                            style="padding: 0 5px; font-size: 11px; height: 22px; line-height: 22px;">✕</button>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="form-actions">
                                                <button type="submit" class="btn btn-primary" style="padding: 3px 16px; font-size: 13px; height: 30px;">💾 Сохранить изменения</button>
                                                <button type="button" class="btn btn-secondary cancel-edit" style="padding: 3px 12px; font-size: 13px; height: 30px;">Отмена</button>
                                                <div class="edit-message" style="margin: 0; font-size: 13px;"></div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ========== ОБЩИЕ ПЕРЕМЕННЫЕ ==========
            const toggleFormBtn = document.getElementById('toggleFormBtn');
            const cancelFormBtn = document.getElementById('cancelFormBtn');
            const cancelFormBtn2 = document.getElementById('cancelFormBtn2');
            const formContainer = document.getElementById('questionFormContainer');
            const form = document.getElementById('questionForm');
            const typeSelect = document.getElementById('q_type');
            const optionsBlock = document.getElementById('q_optionsBlock');
            const optionsContainer = document.getElementById('q_optionsContainer');
            const addOptionBtn = document.getElementById('q_addOption');
            const submitBtn = document.getElementById('submitQuestionBtn');
            const formMessage = document.getElementById('formMessage');
            
            // ========== ФУНКЦИИ ДЛЯ ОСНОВНОЙ ФОРМЫ ==========
            function toggleForm(show) {
                if (show === undefined) {
                    formContainer.style.display = formContainer.style.display === 'none' ? 'block' : 'none';
                } else {
                    formContainer.style.display = show ? 'block' : 'none';
                }
                
                if (formContainer.style.display === 'block') {
                    toggleFormBtn.textContent = '✖️ Закрыть форму';
                    // Сбрасываем форму на создание
                    document.querySelector('input[name="question_id"]').value = '0';
                    document.querySelector('#questionForm h4').textContent = '✏️ Новый вопрос';
                    setTimeout(() => {
                        document.getElementById('q_text').focus();
                    }, 100);
                } else {
                    toggleFormBtn.textContent = '➕ Добавить вопрос';
                    resetForm();
                }
            }
            
            function resetForm() {
                form.reset();
                formMessage.innerHTML = '';
                formMessage.className = '';
                document.querySelector('input[name="question_id"]').value = '0';
                document.querySelector('#questionForm h4').textContent = '✏️ Новый вопрос';
                // Оставляем только один вариант
                const rows = optionsContainer.querySelectorAll('.option-row');
                rows.forEach((row, index) => {
                    if (index === 0) {
                        row.querySelector('input[type="text"]').value = '';
                        row.querySelector('input[type="checkbox"]').checked = false;
                        row.querySelector('input[type="number"]').value = '';
                    } else {
                        row.remove();
                    }
                });
                updateIndices();
            }
            
            function toggleOptions() {
                const type = typeSelect.value;
                if (type === 'single' || type === 'multiple') {
                    optionsBlock.style.display = 'block';
                } else {
                    optionsBlock.style.display = 'none';
                }
            }
            
            function updateIndices() {
                const rows = optionsContainer.querySelectorAll('.option-row');
                rows.forEach((row, index) => {
                    row.dataset.index = index;
                    const checkbox = row.querySelector('input[type="checkbox"]');
                    const pointsInput = row.querySelector('input[type="number"]');
                    if (checkbox) checkbox.name = `option_correct[${index}]`;
                    if (pointsInput) pointsInput.name = `option_points[${index}]`;
                });
            }
            
            function updateRemoveHandlers() {
                document.querySelectorAll('#q_optionsContainer .remove-option').forEach(btn => {
                    btn.removeEventListener('click', removeOption);
                    btn.addEventListener('click', removeOption);
                });
            }
            
            function removeOption(e) {
                const row = e.target.closest('.option-row');
                if (optionsContainer.children.length > 1) {
                    row.remove();
                    updateIndices();
                } else {
                    alert('Должен быть хотя бы один вариант ответа');
                }
            }
            
            // ========== СОБЫТИЯ ДЛЯ ОСНОВНОЙ ФОРМЫ ==========
            toggleFormBtn.addEventListener('click', function() {
                toggleForm();
            });
            
            cancelFormBtn.addEventListener('click', function() {
                toggleForm(false);
            });
            
            cancelFormBtn2.addEventListener('click', function() {
                toggleForm(false);
            });
            
            typeSelect.addEventListener('change', toggleOptions);
            
            addOptionBtn.addEventListener('click', function() {
                const index = optionsContainer.children.length;
                const row = document.createElement('div');
                row.className = 'option-row';
                row.dataset.index = index;
                row.style.cssText = 'display: flex; gap: 5px; align-items: center; padding: 3px 6px; background: #fff; border-radius: 4px; border: 1px solid #e0e0e0;';
                row.innerHTML = `
                    <div style="flex: 1; min-width: 100px;">
                        <input type="text" name="option_text[]" class="form-control" placeholder="Вариант ответа" 
                               style="font-size: 12px; padding: 2px 6px; height: 26px;">
                    </div>
                    <div style="display: flex; gap: 5px; align-items: center; flex-shrink: 0;">
                        <label style="font-size: 12px; font-weight: 400; margin: 0; display: flex; align-items: center; gap: 2px; cursor: pointer; white-space: nowrap;">
                            <input type="checkbox" name="option_correct[${index}]" value="1" style="margin: 0; width: 14px; height: 14px;">
                            Правильный
                        </label>
                        <input type="number" name="option_points[${index}]" class="form-control" 
                               placeholder="Баллы" step="0.5" min="0" 
                               style="font-size: 12px; padding: 2px 4px; height: 26px; width: 55px;">
                    </div>
                    <button type="button" class="btn btn-danger btn-sm remove-option" 
                            style="padding: 0 5px; font-size: 11px; height: 22px; line-height: 22px; flex-shrink: 0;">✕</button>
                `;
                optionsContainer.appendChild(row);
                updateRemoveHandlers();
            });
            
            // ========== ОТПРАВКА ОСНОВНОЙ ФОРМЫ ==========
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const type = typeSelect.value;
                if (type === 'single' || type === 'multiple') {
                    const optionInputs = optionsContainer.querySelectorAll('input[type="text"]');
                    let hasText = false;
                    optionInputs.forEach(input => {
                        if (input.value.trim() !== '') hasText = true;
                    });
                    if (!hasText) {
                        formMessage.innerHTML = `<div class="alert alert-danger" style="padding: 4px 10px; font-size: 13px; margin: 0;">❌ Добавьте хотя бы один вариант ответа</div>`;
                        return;
                    }
                }
                
                const formData = new FormData(form);
                submitBtn.disabled = true;
                submitBtn.textContent = '⏳ Сохранение...';
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '💾 Сохранить';
                    
                    if (data.success) {
                        formMessage.innerHTML = `<div class="alert alert-success" style="padding: 4px 10px; font-size: 13px; margin: 0;">✅ ${data.message}</div>`;
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        formMessage.innerHTML = `<div class="alert alert-danger" style="padding: 4px 10px; font-size: 13px; margin: 0;">❌ ${data.error}</div>`;
                    }
                })
                .catch(error => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '💾 Сохранить';
                    formMessage.innerHTML = `<div class="alert alert-danger" style="padding: 4px 10px; font-size: 13px; margin: 0;">❌ Ошибка: ${error.message}</div>`;
                });
            });
            
            // ========== INLINE РЕДАКТИРОВАНИЕ ==========
            
            // Открыть форму редактирования
            document.querySelectorAll('.edit-question-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const questionId = this.dataset.id;
                    const editForm = document.getElementById(`edit-form-${questionId}`);
                    const card = document.getElementById(`question-${questionId}`);
                    
                    // Закрываем все другие формы
                    document.querySelectorAll('.inline-edit-form.active').forEach(form => {
                        if (form.id !== `edit-form-${questionId}`) {
                            form.classList.remove('active');
                            form.closest('.question-card').classList.remove('editing');
                        }
                    });
                    
                    // Переключаем текущую
                    editForm.classList.toggle('active');
                    card.classList.toggle('editing');
                    
                    // Настраиваем видимость вариантов
                    const editType = editForm.querySelector('.edit-type');
                    const optionsBlock = editForm.querySelector('.edit-options-block');
                    toggleEditOptions(editType, optionsBlock);
                });
            });
            
            // Переключение видимости вариантов в форме редактирования
            function toggleEditOptions(typeSelect, optionsBlock) {
                if (!typeSelect || !optionsBlock) return;
                const type = typeSelect.value;
                if (type === 'single' || type === 'multiple') {
                    optionsBlock.style.display = 'block';
                } else {
                    optionsBlock.style.display = 'none';
                }
            }
            
            // Обработчики для select типа в формах редактирования
            document.querySelectorAll('.edit-type').forEach(select => {
                const optionsBlock = select.closest('form').querySelector('.edit-options-block');
                select.addEventListener('change', function() {
                    toggleEditOptions(this, optionsBlock);
                });
                // Инициализация
                toggleEditOptions(select, optionsBlock);
            });
            
            // Добавление варианта в форме редактирования
            document.querySelectorAll('.edit-add-option').forEach(btn => {
                btn.addEventListener('click', function() {
                    const container = this.closest('.edit-options-block').querySelector('.edit-options-container');
                    const index = container.children.length;
                    const row = document.createElement('div');
                    row.className = 'option-row';
                    row.dataset.index = index;
                    row.innerHTML = `
                        <div class="option-input">
                            <input type="text" name="option_text[]" class="form-control" placeholder="Вариант ответа" 
                                   style="font-size: 12px; padding: 2px 6px; height: 26px;">
                        </div>
                        <div class="option-actions">
                            <label>
                                <input type="checkbox" name="option_correct[${index}]" value="1" style="width: 14px; height: 14px;">
                                Правильный
                            </label>
                            <input type="number" name="option_points[${index}]" class="form-control" 
                                   placeholder="Баллы" step="0.5" min="0" 
                                   style="font-size: 12px; padding: 2px 4px; height: 26px; width: 55px;">
                            <button type="button" class="btn btn-danger btn-sm edit-remove-option" 
                                    style="padding: 0 5px; font-size: 11px; height: 22px; line-height: 22px;">✕</button>
                        </div>
                    `;
                    container.appendChild(row);
                    updateEditRemoveHandlers(container);
                });
            });
            
            // Удаление варианта в форме редактирования
            function updateEditRemoveHandlers(container) {
                container.querySelectorAll('.edit-remove-option').forEach(btn => {
                    btn.removeEventListener('click', function(e) {
                        const row = e.target.closest('.option-row');
                        if (container.children.length > 1) {
                            row.remove();
                            updateEditIndices(container);
                        } else {
                            alert('Должен быть хотя бы один вариант ответа');
                        }
                    });
                    btn.addEventListener('click', function(e) {
                        const row = e.target.closest('.option-row');
                        if (container.children.length > 1) {
                            row.remove();
                            updateEditIndices(container);
                        } else {
                            alert('Должен быть хотя бы один вариант ответа');
                        }
                    });
                });
            }
            
            function updateEditIndices(container) {
                const rows = container.querySelectorAll('.option-row');
                rows.forEach((row, index) => {
                    row.dataset.index = index;
                    const checkbox = row.querySelector('input[type="checkbox"]');
                    const pointsInput = row.querySelector('input[type="number"]');
                    if (checkbox) checkbox.name = `option_correct[${index}]`;
                    if (pointsInput) pointsInput.name = `option_points[${index}]`;
                });
            }
            
            // Инициализация удаления вариантов в формах редактирования
            document.querySelectorAll('.edit-options-container').forEach(container => {
                updateEditRemoveHandlers(container);
            });
            
            // Отмена редактирования
            document.querySelectorAll('.cancel-edit').forEach(btn => {
                btn.addEventListener('click', function() {
                    const editForm = this.closest('.inline-edit-form');
                    const card = editForm.closest('.question-card');
                    editForm.classList.remove('active');
                    card.classList.remove('editing');
                });
            });
            
            // Отправка формы редактирования
            document.querySelectorAll('.edit-question-form').forEach(editForm => {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const messageDiv = this.querySelector('.edit-message');
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const questionId = this.dataset.id;
                    
                    // Валидация
                    const type = this.querySelector('.edit-type').value;
                    if (type === 'single' || type === 'multiple') {
                        const optionInputs = this.querySelectorAll('.edit-options-container input[type="text"]');
                        let hasText = false;
                        optionInputs.forEach(input => {
                            if (input.value.trim() !== '') hasText = true;
                        });
                        if (!hasText) {
                            messageDiv.innerHTML = `<div class="alert alert-danger" style="padding: 4px 10px; font-size: 13px; margin: 0;">❌ Добавьте хотя бы один вариант ответа</div>`;
                            return;
                        }
                    }
                    
                    submitBtn.disabled = true;
                    submitBtn.textContent = '⏳ Сохранение...';
                    messageDiv.innerHTML = '';
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        submitBtn.disabled = false;
                        submitBtn.textContent = '💾 Сохранить изменения';
                        
                        if (data.success) {
                            messageDiv.innerHTML = `<div class="alert alert-success" style="padding: 4px 10px; font-size: 13px; margin: 0;">✅ ${data.message}</div>`;
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            messageDiv.innerHTML = `<div class="alert alert-danger" style="padding: 4px 10px; font-size: 13px; margin: 0;">❌ ${data.error}</div>`;
                        }
                    })
                    .catch(error => {
                        submitBtn.disabled = false;
                        submitBtn.textContent = '💾 Сохранить изменения';
                        messageDiv.innerHTML = `<div class="alert alert-danger" style="padding: 4px 10px; font-size: 13px; margin: 0;">❌ Ошибка: ${error.message}</div>`;
                    });
                });
            });
            
            // ========== ИНИЦИАЛИЗАЦИЯ ==========
            toggleOptions();
            updateRemoveHandlers();
        });
    </script>
</body>
</html>