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

// Обработка AJAX запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
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
        
        // Создание вопроса
        $db->query(
            "INSERT INTO questions (test_id, type, text, points, sort_order) VALUES (?, ?, ?, ?, ?)",
            [$testId, $type, $text, $points, $sortOrder]
        );
        $newQuestionId = (int)$db->lastInsertId();
        
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
        
        echo json_encode(['success' => true, 'message' => 'Вопрос создан', 'id' => $newQuestionId]);
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
                    
                    <!-- Форма добавления вопроса (скрыта по умолчанию) -->
                    <div id="questionFormContainer" style="display: none; background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h3 style="margin-bottom: 15px;">➕ Добавление вопроса</h3>
                        <form id="questionForm" method="POST">
                            <input type="hidden" name="ajax" value="1">
                            
                            <div class="form-row">
                                <div class="form-group half">
                                    <label for="q_type">Тип вопроса *</label>
                                    <select id="q_type" name="type" class="form-control" required>
                                        <?php foreach ($typeLabels as $key => $label): ?>
                                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group half">
                                    <label for="q_points">Баллы за вопрос</label>
                                    <input type="number" id="q_points" name="points" class="form-control" value="1" step="0.5" min="0">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="q_text">Текст вопроса *</label>
                                <textarea id="q_text" name="text" class="form-control" rows="3" required placeholder="Введите текст вопроса..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="q_sort_order">Порядок сортировки</label>
                                <input type="number" id="q_sort_order" name="sort_order" class="form-control" value="0" step="1">
                                <span class="help-text">Меньшее число - выше в списке</span>
                            </div>
                            
                            <!-- Варианты ответов -->
                            <div id="q_optionsBlock">
                                <h4 style="margin: 15px 0 10px 0;">📝 Варианты ответов</h4>
                                <p class="help-text" style="margin-bottom: 10px;">
                                    Для типов "Одиночный выбор" и "Множественный выбор" необходимо добавить варианты.
                                </p>
                                
                                <div id="q_optionsContainer">
                                    <div class="option-row" data-index="0">
                                        <div class="option-input-group">
                                            <input type="text" name="option_text[]" class="form-control" placeholder="Вариант ответа">
                                        </div>
                                        <div class="option-check-group">
                                            <label>
                                                <input type="checkbox" name="option_correct[0]" value="1">
                                                Правильный
                                            </label>
                                            <input type="number" name="option_points[0]" class="form-control" 
                                                   placeholder="Баллы" step="0.5" min="0" style="width: 80px;">
                                        </div>
                                        <button type="button" class="btn btn-danger btn-sm remove-option">🗑️</button>
                                    </div>
                                </div>
                                
                                <button type="button" id="q_addOption" class="btn btn-secondary btn-sm" style="margin-top: 10px;">
                                    ➕ Добавить вариант
                                </button>
                            </div>
                            
                            <div style="margin-top: 20px; display: flex; gap: 10px;">
                                <button type="submit" class="btn btn-primary" id="submitQuestionBtn">💾 Сохранить вопрос</button>
                                <button type="button" class="btn btn-secondary" id="cancelFormBtn">Отмена</button>
                            </div>
                            
                            <div id="formMessage" style="margin-top: 10px;"></div>
                        </form>
                    </div>
                    
                    <!-- Список вопросов -->
                    <?php if (empty($questions)): ?>
                        <p style="color: #999; text-align: center; padding: 40px 0;">
                            В тесте пока нет вопросов. Нажмите "Добавить вопрос" чтобы создать первый вопрос.
                        </p>
                    <?php else: ?>
                        <div class="questions-list">
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="question-card" data-id="<?php echo $question['id']; ?>">
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
                                            <a href="question_edit.php?test_id=<?php echo $testId; ?>&id=<?php echo $question['id']; ?>" 
                                               class="btn btn-sm btn-secondary" title="Редактировать">✏️</a>
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
                                        <?php
                                        $options = $db->fetchAll(
                                            "SELECT * FROM answer_options WHERE question_id = ? ORDER BY sort_order ASC",
                                            [$question['id']]
                                        );
                                        
                                        if (in_array($question['type'], ['single', 'multiple'])):
                                            foreach ($options as $option):
                                        ?>
                                            <div class="option-item <?php echo $option['is_correct'] ? 'correct' : ''; ?>">
                                                <span class="option-marker">
                                                    <?php echo $option['is_correct'] ? '✅' : '⬜'; ?>
                                                </span>
                                                <?php echo htmlspecialchars($option['text']); ?>
                                                <?php if ($option['points'] !== null && $option['points'] != 0): ?>
                                                    <span class="option-points">(+<?php echo number_format($option['points'], 2); ?> баллов)</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php 
                                            endforeach;
                                        elseif ($question['type'] === 'text' || $question['type'] === 'number'):
                                        ?>
                                            <div style="padding: 10px; background: #f8f9fa; border-radius: 4px; color: #666;">
                                                <em>Свободный ответ (без вариантов)</em>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        /* Стили для формы */
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-group.half {
            flex: 1;
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
        
        /* Стили для вариантов в форме */
        .option-row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 8px;
            padding: 8px 10px;
            background: #fff;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        
        .option-input-group {
            flex: 1;
        }
        
        .option-input-group input {
            font-size: 14px;
        }
        
        .option-check-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .option-check-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 400;
            margin: 0;
            white-space: nowrap;
            font-size: 14px;
        }
        
        .remove-option {
            flex-shrink: 0;
        }
        
        /* Адаптив */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .option-row {
                flex-wrap: wrap;
            }
            
            .option-check-group {
                flex-wrap: wrap;
                width: 100%;
            }
            
            .option-check-group input[type="number"] {
                width: 100% !important;
            }
        }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Элементы
            const toggleFormBtn = document.getElementById('toggleFormBtn');
            const cancelFormBtn = document.getElementById('cancelFormBtn');
            const formContainer = document.getElementById('questionFormContainer');
            const form = document.getElementById('questionForm');
            const typeSelect = document.getElementById('q_type');
            const optionsBlock = document.getElementById('q_optionsBlock');
            const optionsContainer = document.getElementById('q_optionsContainer');
            const addOptionBtn = document.getElementById('q_addOption');
            const submitBtn = document.getElementById('submitQuestionBtn');
            const formMessage = document.getElementById('formMessage');
            
            // Показать/скрыть форму
            function toggleForm(show) {
                if (show === undefined) {
                    formContainer.style.display = formContainer.style.display === 'none' ? 'block' : 'none';
                } else {
                    formContainer.style.display = show ? 'block' : 'none';
                }
                
                if (formContainer.style.display === 'block') {
                    toggleFormBtn.textContent = '✖️ Закрыть форму';
                    document.getElementById('q_text').focus();
                } else {
                    toggleFormBtn.textContent = '➕ Добавить вопрос';
                    resetForm();
                }
            }
            
            function resetForm() {
                form.reset();
                formMessage.innerHTML = '';
                formMessage.className = '';
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
                // Обновляем индексы
                updateIndices();
            }
            
            toggleFormBtn.addEventListener('click', function() {
                toggleForm();
            });
            
            cancelFormBtn.addEventListener('click', function() {
                toggleForm(false);
            });
            
            // Переключение видимости вариантов
            function toggleOptions() {
                const type = typeSelect.value;
                if (type === 'single' || type === 'multiple') {
                    optionsBlock.style.display = 'block';
                } else {
                    optionsBlock.style.display = 'none';
                }
            }
            
            typeSelect.addEventListener('change', toggleOptions);
            
            // Добавление варианта
            addOptionBtn.addEventListener('click', function() {
                const index = optionsContainer.children.length;
                const row = document.createElement('div');
                row.className = 'option-row';
                row.dataset.index = index;
                row.innerHTML = `
                    <div class="option-input-group">
                        <input type="text" name="option_text[]" class="form-control" placeholder="Вариант ответа">
                    </div>
                    <div class="option-check-group">
                        <label>
                            <input type="checkbox" name="option_correct[${index}]" value="1">
                            Правильный
                        </label>
                        <input type="number" name="option_points[${index}]" class="form-control" 
                               placeholder="Баллы" step="0.5" min="0" style="width: 80px;">
                    </div>
                    <button type="button" class="btn btn-danger btn-sm remove-option">🗑️</button>
                `;
                optionsContainer.appendChild(row);
                updateRemoveHandlers();
            });
            
            // Удаление варианта
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
            
            // Отправка формы через AJAX
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
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
                    submitBtn.textContent = '💾 Сохранить вопрос';
                    
                    if (data.success) {
                        formMessage.innerHTML = `<div class="alert alert-success">✅ ${data.message}</div>`;
                        // Перезагружаем страницу для отображения нового вопроса
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        formMessage.innerHTML = `<div class="alert alert-danger">❌ ${data.error}</div>`;
                    }
                })
                .catch(error => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '💾 Сохранить вопрос';
                    formMessage.innerHTML = `<div class="alert alert-danger">❌ Ошибка: ${error.message}</div>`;
                });
            });
            
            // Инициализация
            toggleOptions();
            updateRemoveHandlers();
        });
    </script>
</body>
</html>