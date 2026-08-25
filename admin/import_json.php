<?php
/**
 * Импорт теста из JSON
 */

require_once '../config.php';

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    redirect('/admin/login.php');
}

$db = Database::getInstance();
$testManager = new TestManager();

$message = '';
$messageType = '';

// Обработка импорта
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    $testTitle = trim($_POST['test_title'] ?? '');
    $testDescription = trim($_POST['test_description'] ?? '');
    $jsonData = trim($_POST['json_data'] ?? '');
    
    if (empty($testTitle)) {
        $message = 'Введите название теста';
        $messageType = 'danger';
    } elseif (empty($jsonData)) {
        $message = 'Введите JSON данные';
        $messageType = 'danger';
    } else {
        try {
            $data = json_decode($jsonData, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Ошибка парсинга JSON: ' . json_last_error_msg());
            }
            
            if (!is_array($data) || empty($data)) {
                throw new Exception('JSON должен содержать массив вопросов');
            }
            
            $db->beginTransaction();
            
            // Генерация уникального slug
            $baseSlug = generateSlug($testTitle);
            $slug = $baseSlug;
            $counter = 1;
            
            // Проверяем уникальность slug
            $existing = $db->fetchOne("SELECT id FROM tests WHERE slug = ?", [$slug]);
            while ($existing) {
                $slug = $baseSlug . '-' . $counter++;
                $existing = $db->fetchOne("SELECT id FROM tests WHERE slug = ?", [$slug]);
            }
            
            // Получаем ID текущего администратора
            $adminId = $_SESSION['admin_id'] ?? null;
            if (!$adminId) {
                $admin = $db->fetchOne(
                    "SELECT id FROM users WHERE login = ?",
                    [$_SESSION['admin_login']]
                );
                $adminId = $admin['id'] ?? null;
            }
            
            // Создание теста
            $db->query(
                "INSERT INTO tests (title, description, slug, created_by, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$testTitle, $testDescription, $slug, $adminId]
            );
            $testId = (int)$db->lastInsertId();
            
            $questionCount = 0;
            $optionCount = 0;
            $skippedQuestions = 0;
            
            // Маппинг типов вопросов
            $typeMapping = [
                'radio' => 'single',
                'checkbox' => 'multiple',
                'short_text' => 'text',
                'long_text' => 'text',
                'text' => 'text',
                'number' => 'number'
            ];
            
            foreach ($data as $index => $item) {
                // Проверяем наличие объекта question
                if (!isset($item['question'])) {
                    $skippedQuestions++;
                    continue;
                }
                
                $question = $item['question'];
                $text = trim($question['text'] ?? '');
                
                // Пропускаем пустые или служебные вопросы
                if (empty($text) || $text === 'ID Пользователя' || $text === 'ID не найден') {
                    $skippedQuestions++;
                    continue;
                }
                
                // Определяем тип вопроса
                $type = $typeMapping[$question['type'] ?? 'radio'] ?? 'single';
                
                // Получаем варианты ответов из allOptions
                $options = $item['allOptions'] ?? [];
                
                // Для single и multiple проверяем наличие вариантов
                if (in_array($type, ['single', 'multiple']) && empty($options)) {
                    $skippedQuestions++;
                    continue;
                }
                
                // Получаем баллы из summary
                $points = 1;
                if (isset($item['summary']['maxPossibleScore'])) {
                    $points = (float)$item['summary']['maxPossibleScore'];
                    if ($points <= 0) $points = 1;
                }
                
                // Создание вопроса
                $db->query(
                    "INSERT INTO questions (test_id, type, text, points, sort_order) VALUES (?, ?, ?, ?, ?)",
                    [$testId, $type, $text, $points, $index]
                );
                $questionId = (int)$db->lastInsertId();
                $questionCount++;
                
                // Сохранение вариантов ответов
                if (in_array($type, ['single', 'multiple']) && !empty($options)) {
                    $correctCount = 0;
                    
                    foreach ($options as $optIndex => $option) {
                        $optionText = trim($option['text'] ?? '');
                        if (empty($optionText)) continue;
                        
                        // Определяем правильность ответа
                        // Используем поле isCorrect или id (1 = правильный)
                        $isCorrect = 0;
                        if (isset($option['isCorrect'])) {
                            $isCorrect = $option['isCorrect'] ? 1 : 0;
                        } elseif (isset($option['id'])) {
                            $isCorrect = ($option['id'] === '1' || $option['id'] === 1) ? 1 : 0;
                        }
                        
                        // Для radio (single) - только один правильный ответ
                        if ($type === 'single' && $isCorrect) {
                            $correctCount++;
                            if ($correctCount > 1) {
                                $isCorrect = 0;
                            }
                        }
                        
                        // Баллы за вариант
                        $optPoints = isset($option['score']) && $option['score'] > 0 ? (float)$option['score'] : null;
                        
                        $db->query(
                            "INSERT INTO answer_options (question_id, text, is_correct, points, sort_order) 
                             VALUES (?, ?, ?, ?, ?)",
                            [$questionId, $optionText, $isCorrect, $optPoints, $optIndex]
                        );
                        $optionCount++;
                    }
                    
                    // Для single вопросов, если нет правильного ответа, делаем первый правильным
                    if ($type === 'single' && $correctCount === 0) {
                        $firstOption = $db->fetchOne(
                            "SELECT id FROM answer_options WHERE question_id = ? ORDER BY sort_order LIMIT 1",
                            [$questionId]
                        );
                        if ($firstOption) {
                            $db->query(
                                "UPDATE answer_options SET is_correct = 1 WHERE id = ?",
                                [$firstOption['id']]
                            );
                        }
                    }
                }
            }
            
            $db->commit();
            
            $message = "Тест успешно импортирован!";
            $messageType = 'success';
            
            if ($skippedQuestions > 0) {
                $message .= " Создано {$questionCount} вопросов и {$optionCount} вариантов ответов. Пропущено {$skippedQuestions} служебных вопросов.";
            } else {
                $message .= " Создано {$questionCount} вопросов и {$optionCount} вариантов ответов.";
            }
            
            $_SESSION['message'] = $message;
            $_SESSION['message_type'] = $messageType;
            
            redirect("/admin/tests.php");
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = 'Ошибка импорта: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Получение списка тестов для выбора (опционально)
$tests = $db->fetchAll("SELECT id, title FROM tests ORDER BY title ASC");
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Импорт теста из JSON</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .import-wrapper {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .import-container {
            background: #fff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .import-container h2 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }
        
        .json-editor {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            min-height: 400px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 12px;
            width: 100%;
            resize: vertical;
            tab-size: 2;
        }
        
        .json-editor:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        .help-text {
            font-size: 13px;
            color: #6c757d;
            margin-top: 8px;
        }
        
        .help-text code {
            background: #f1f3f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
        
        .stats-preview {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
            display: none;
        }
        
        .stats-preview.visible {
            display: block;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            background: #fff;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        
        .stat-item .number {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .stat-item .label {
            font-size: 12px;
            color: #6c757d;
            margin-top: 4px;
        }
        
        .format-example {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid #007bff;
        }
        
        .format-example pre {
            margin: 0;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .btn-import {
            padding: 10px 30px;
            font-size: 16px;
        }
        
        .btn-preview {
            margin-right: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .form-group .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .import-container {
                padding: 15px;
            }
            
            .json-editor {
                min-height: 250px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <header class="admin-header">
            <div class="container">
                <div class="header-content">
                    <h1>📥 Импорт теста из JSON</h1>
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
                    <li><a href="import_json.php" class="active">📥 Импорт JSON</a></li>
                </ul>
            </div>
        </nav>
        
        <main class="admin-main">
            <div class="container">
                <div class="import-wrapper">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    
                    <div class="import-container">
                        <h2>📥 Импорт теста из JSON</h2>
                        <p style="color: #6c757d; margin-bottom: 20px;">
                            Загрузите тест в формате JSON. Поддерживаются вопросы типов: 
                            <strong>radio</strong> (одиночный выбор), <strong>checkbox</strong> (множественный выбор),
                            <strong>short_text</strong> (текстовый ответ).
                        </p>
                        
                        <form method="POST" id="importForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="test_title">Название теста *</label>
                                    <input type="text" id="test_title" name="test_title" class="form-control" 
                                           placeholder="Введите название теста" required 
                                           value="<?php echo isset($_POST['test_title']) ? htmlspecialchars($_POST['test_title']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="test_description">Описание теста</label>
                                    <input type="text" id="test_description" name="test_description" class="form-control" 
                                           placeholder="Краткое описание (необязательно)"
                                           value="<?php echo isset($_POST['test_description']) ? htmlspecialchars($_POST['test_description']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="json_data">JSON данные *</label>
                                <textarea id="json_data" name="json_data" class="json-editor" 
                                          placeholder='Вставьте JSON данные теста здесь...' 
                                          required><?php echo isset($_POST['json_data']) ? htmlspecialchars($_POST['json_data']) : ''; ?></textarea>
                                <div class="help-text">
                                    💡 Вставьте JSON массив с вопросами. Каждый вопрос должен содержать объект <code>question</code> и массив <code>allOptions</code>.
                                    В options: <code>isCorrect</code> (true/false) или <code>id</code> (1 - правильный, 0 - неправильный).
                                </div>
                            </div>
                            
                            <div id="statsPreview" class="stats-preview">
                                <h4 style="margin-top: 0; margin-bottom: 10px;">📊 Статистика импорта</h4>
                                <div class="stats-grid" id="statsGrid">
                                    <div class="stat-item">
                                        <div class="number" id="totalQuestions">0</div>
                                        <div class="label">Всего вопросов</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="number" id="singleQuestions">0</div>
                                        <div class="label">Одиночный выбор</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="number" id="multipleQuestions">0</div>
                                        <div class="label">Множественный выбор</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="number" id="textQuestions">0</div>
                                        <div class="label">Текстовый ответ</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="number" id="skippedQuestions">0</div>
                                        <div class="label">Пропущено</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="number" id="totalOptions">0</div>
                                        <div class="label">Всего вариантов</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px;">
                                <button type="button" class="btn btn-secondary btn-preview" id="previewBtn">👁️ Предпросмотр</button>
                                <button type="submit" name="import" value="1" class="btn btn-primary btn-import">📥 Импортировать тест</button>
                                <a href="tests.php" class="btn btn-secondary">Отмена</a>
                            </div>
                        </form>
                        
                        <div class="format-example">
                            <strong>📌 Ваш формат JSON:</strong>
                            <pre>[
  {
    "question": {
      "id": "ID не найден",
      "type": "radio",
      "text": "Текст вопроса?"
    },
    "summary": {
      "totalOptions": 4,
      "correctCount": 1,
      "incorrectCount": 3,
      "totalScore": 1,
      "maxPossibleScore": 1
    },
    "allOptions": [
      {"id": "0", "text": "Неправильный ответ", "score": 0, "isCorrect": false},
      {"id": "1", "text": "Правильный ответ", "score": 1, "isCorrect": true}
    ]
  }
]</pre>
                            <p style="margin-top: 10px; font-size: 13px; color: #666;">
                                ⚠️ Вопросы с текстом "ID Пользователя" или "ID не найден" будут автоматически пропущены.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const jsonInput = document.getElementById('json_data');
            const previewBtn = document.getElementById('previewBtn');
            const statsPreview = document.getElementById('statsPreview');
            
            // Функция обновления предпросмотра
            function updatePreview() {
                const jsonData = jsonInput.value.trim();
                
                if (!jsonData) {
                    statsPreview.classList.remove('visible');
                    return;
                }
                
                try {
                    const data = JSON.parse(jsonData);
                    
                    if (!Array.isArray(data) || data.length === 0) {
                        showStats(0, 0, 0, 0, 0, 0);
                        statsPreview.classList.remove('visible');
                        return;
                    }
                    
                    let total = 0;
                    let single = 0;
                    let multiple = 0;
                    let text = 0;
                    let skipped = 0;
                    let totalOptions = 0;
                    
                    data.forEach(item => {
                        // Проверяем наличие объекта question
                        if (!item.question) {
                            skipped++;
                            return;
                        }
                        
                        const q = item.question;
                        const qText = q.text || '';
                        
                        // Пропускаем служебные вопросы
                        if (qText === 'ID Пользователя' || qText === 'ID не найден' || qText === '') {
                            skipped++;
                            return;
                        }
                        
                        const type = q.type || 'radio';
                        const options = item.allOptions || [];
                        
                        total++;
                        totalOptions += options.length;
                        
                        if (type === 'radio') {
                            single++;
                        } else if (type === 'checkbox') {
                            multiple++;
                        } else if (type === 'short_text' || type === 'long_text' || type === 'text') {
                            text++;
                        } else {
                            // Если тип не определен, считаем как single
                            single++;
                        }
                    });
                    
                    showStats(total, single, multiple, text, skipped, totalOptions);
                    statsPreview.classList.add('visible');
                    
                } catch (e) {
                    statsPreview.classList.remove('visible');
                }
            }
            
            function showStats(total, single, multiple, text, skipped, options) {
                document.getElementById('totalQuestions').textContent = total;
                document.getElementById('singleQuestions').textContent = single;
                document.getElementById('multipleQuestions').textContent = multiple;
                document.getElementById('textQuestions').textContent = text;
                document.getElementById('skippedQuestions').textContent = skipped;
                document.getElementById('totalOptions').textContent = options;
            }
            
            // Автоматический предпросмотр при изменении
            let previewTimeout;
            jsonInput.addEventListener('input', function() {
                clearTimeout(previewTimeout);
                previewTimeout = setTimeout(updatePreview, 300);
            });
            
            // Ручной предпросмотр по кнопке
            previewBtn.addEventListener('click', function(e) {
                e.preventDefault();
                updatePreview();
            });
            
            // Валидация перед отправкой
            document.getElementById('importForm').addEventListener('submit', function(e) {
                const jsonData = jsonInput.value.trim();
                if (!jsonData) {
                    e.preventDefault();
                    alert('Пожалуйста, введите JSON данные');
                    return;
                }
                
                try {
                    JSON.parse(jsonData);
                } catch (e) {
                    e.preventDefault();
                    alert('Ошибка в JSON: ' + e.message);
                    return;
                }
                
                if (!confirm('Будет создан новый тест. Продолжить?')) {
                    e.preventDefault();
                }
            });
            
            // Автоматический предпросмотр при загрузке, если есть данные
            if (jsonInput.value.trim()) {
                setTimeout(updatePreview, 500);
            }
        });
    </script>
</body>
</html>