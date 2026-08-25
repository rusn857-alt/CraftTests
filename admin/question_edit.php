<?php
/**
 * Создание и редактирование вопроса (отдельная страница)
 */

require_once '../config.php';

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    redirect('/admin/login.php');
}

$testId = (int)($_GET['test_id'] ?? 0);
$questionId = (int)($_GET['id'] ?? 0);
$isEdit = $questionId > 0;

$testManager = new TestManager();
$db = Database::getInstance();

// Проверка существования теста
$test = $testManager->getTest($testId);
if (!$test) {
    $_SESSION['message'] = 'Тест не найден';
    $_SESSION['message_type'] = 'danger';
    redirect('/admin/tests.php');
}

$question = null;
$options = [];

if ($isEdit) {
    $question = $db->fetchOne(
        "SELECT * FROM questions WHERE id = ? AND test_id = ?",
        [$questionId, $testId]
    );
    
    if (!$question) {
        $_SESSION['message'] = 'Вопрос не найден';
        $_SESSION['message_type'] = 'danger';
        redirect("/admin/questions.php?test_id={$testId}");
    }
    
    $options = $db->fetchAll(
        "SELECT * FROM answer_options WHERE question_id = ? ORDER BY sort_order ASC",
        [$questionId]
    );
}

// Обработка формы (для редактирования)
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    $type = $_POST['type'] ?? 'single';
    $text = trim($_POST['text'] ?? '');
    $points = (float)($_POST['points'] ?? 1);
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $optionTexts = $_POST['option_text'] ?? [];
    $optionCorrect = $_POST['option_correct'] ?? [];
    $optionPoints = $_POST['option_points'] ?? [];
    
    if (empty($text)) {
        $error = 'Введите текст вопроса';
    } else {
        try {
            $db->beginTransaction();
            
            // Обновление вопроса
            $db->query(
                "UPDATE questions SET type = ?, text = ?, points = ?, sort_order = ? WHERE id = ?",
                [$type, $text, $points, $sortOrder, $questionId]
            );
            
            // Удаление старых вариантов
            $db->query("DELETE FROM answer_options WHERE question_id = ?", [$questionId]);
            
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
                        [$questionId, $optionText, $isCorrect, $optPoints, $index]
                    );
                }
            }
            
            $db->commit();
            
            $_SESSION['message'] = 'Вопрос обновлен';
            $_SESSION['message_type'] = 'success';
            redirect("/admin/questions.php?test_id={$testId}");
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Ошибка сохранения: ' . $e->getMessage();
        }
    }
}

$typeLabels = [
    'single' => 'Одиночный выбор',
    'multiple' => 'Множественный выбор',
    'text' => 'Текстовый ответ',
    'number' => 'Числовой ответ'
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Редактирование' : 'Создание'; ?> вопроса</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="admin-wrapper">
        <header class="admin-header">
            <div class="container">
                <div class="header-content">
                    <h1><?php echo $isEdit ? '✏️ Редактирование вопроса' : '➕ Создание вопроса'; ?></h1>
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
                    <li><a href="questions.php?test_id=<?php echo $testId; ?>">📋 Вопросы</a></li>
                    <li><a href="#" class="active"><?php echo $isEdit ? '✏️ Редактирование' : '➕ Создание'; ?></a></li>
                </ul>
            </div>
        </nav>
        
        <main class="admin-main">
            <div class="container">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <div class="section">
                    <form method="POST" action="" id="questionForm">
                        <div class="form-group">
                            <label for="type">Тип вопроса *</label>
                            <select id="type" name="type" class="form-control" required>
                                <?php foreach ($typeLabels as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" 
                                            <?php echo ($question['type'] ?? 'single') === $key ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="text">Текст вопроса *</label>
                            <textarea id="text" name="text" class="form-control" rows="4" required><?php echo htmlspecialchars($question['text'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="points">Баллы за вопрос</label>
                            <input type="number" id="points" name="points" class="form-control" 
                                   value="<?php echo $question['points'] ?? 1; ?>" step="0.5" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="sort_order">Порядок сортировки</label>
                            <input type="number" id="sort_order" name="sort_order" class="form-control" 
                                   value="<?php echo $question['sort_order'] ?? 0; ?>" step="1">
                            <span class="help-text">Меньшее число - выше в списке</span>
                        </div>
                        
                        <hr style="margin: 30px 0; border: none; border-top: 2px solid #eee;">
                        
                        <!-- Блок вариантов ответов -->
                        <div id="optionsBlock">
                            <h3>📝 Варианты ответов</h3>
                            <p class="help-text" style="margin-bottom: 20px;">
                                Для типов "Одиночный выбор" и "Множественный выбор" необходимо добавить варианты.
                                Для "Текстовый ответ" и "Числовой ответ" варианты не требуются.
                            </p>
                            
                            <div id="optionsContainer">
                                <?php if (empty($options)): ?>
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
                                <?php else: ?>
                                    <?php foreach ($options as $index => $option): ?>
                                        <div class="option-row" data-index="<?php echo $index; ?>">
                                            <div class="option-input-group">
                                                <input type="text" name="option_text[]" class="form-control" 
                                                       value="<?php echo htmlspecialchars($option['text']); ?>" 
                                                       placeholder="Вариант ответа">
                                            </div>
                                            <div class="option-check-group">
                                                <label>
                                                    <input type="checkbox" name="option_correct[<?php echo $index; ?>]" value="1" 
                                                           <?php echo $option['is_correct'] ? 'checked' : ''; ?>>
                                                    Правильный
                                                </label>
                                                <input type="number" name="option_points[<?php echo $index; ?>]" class="form-control" 
                                                       value="<?php echo $option['points'] !== null ? $option['points'] : ''; ?>" 
                                                       placeholder="Баллы" step="0.5" min="0" style="width: 80px;">
                                            </div>
                                            <button type="button" class="btn btn-danger btn-sm remove-option">🗑️</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <button type="button" id="addOption" class="btn btn-secondary" style="margin-top: 10px;">
                                ➕ Добавить вариант
                            </button>
                        </div>
                        
                        <div style="margin-top: 30px; display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $isEdit ? 'Сохранить изменения' : 'Создать вопрос'; ?>
                            </button>
                            <a href="questions.php?test_id=<?php echo $testId; ?>" class="btn btn-secondary">Отмена</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        .option-row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .option-input-group {
            flex: 1;
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
        }
        
        .remove-option {
            flex-shrink: 0;
        }
        
        @media (max-width: 768px) {
            .option-row {
                flex-wrap: wrap;
            }
            
            .option-check-group {
                flex-wrap: wrap;
                width: 100%;
            }
        }

                #toggleFormBtn {
            transition: all 0.3s;
        }

        #toggleFormBtn.active {
            background: #dc3545;
        }

        #questionFormContainer {
            transition: all 0.3s ease;
            border: 2px dashed #4CAF50;
        }

        #questionFormContainer form {
            margin-bottom: 0;
        }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = document.getElementById('type');
            const optionsBlock = document.getElementById('optionsBlock');
            const optionsContainer = document.getElementById('optionsContainer');
            const addOptionBtn = document.getElementById('addOption');
            
            function toggleOptions() {
                const type = typeSelect.value;
                if (type === 'single' || type === 'multiple') {
                    optionsBlock.style.display = 'block';
                } else {
                    optionsBlock.style.display = 'none';
                }
            }
            
            typeSelect.addEventListener('change', toggleOptions);
            
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
            
            function updateRemoveHandlers() {
                document.querySelectorAll('.remove-option').forEach(btn => {
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
            
            toggleOptions();
            updateRemoveHandlers();
        });
    </script>
</body>
</html>