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
    $autoTitle = isset($_POST['auto_title']) && $_POST['auto_title'] == '1';
    
    // Проверяем, был ли загружен файл
    if (empty($jsonData) && isset($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
        $fileContent = file_get_contents($_FILES['json_file']['tmp_name']);
        if ($fileContent !== false) {
            $jsonData = $fileContent;
        } else {
            $message = 'Не удалось прочитать загруженный файл';
            $messageType = 'danger';
        }
    }
    
    if (empty($jsonData)) {
        $message = 'Введите JSON данные или загрузите JSON файл';
        $messageType = 'danger';
    } else {
        try {
            $data = json_decode($jsonData, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Ошибка парсинга JSON: ' . json_last_error_msg());
            }
            
            // Проверяем структуру: есть ли объект test
            if (isset($data['test']) && isset($data['questions'])) {
                // Новый формат с объектом test
                $testData = $data['test'];
                $questions = $data['questions'];
                
                // Если auto_title включен или поле title пустое, берем из JSON
                if ($autoTitle || empty($testTitle)) {
                    $testTitle = $testData['title'] ?? 'Импортированный тест';
                }
                
                // Добавляем описание из JSON если есть
                if (empty($testDescription) && isset($testData['description'])) {
                    $testDescription = $testData['description'];
                }
            } else {
                // Старый формат - массив вопросов
                $questions = $data;
                if (empty($testTitle)) {
                    $testTitle = 'Импортированный тест';
                }
            }
            
            if (empty($testTitle)) {
                $message = 'Введите название теста или включите авто-определение';
                $messageType = 'danger';
                throw new Exception('Название теста не указано');
            }
            
            if (!is_array($questions) || empty($questions)) {
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
            
            foreach ($questions as $index => $item) {
                // Поддержка двух форматов: с объектом question и без
                if (isset($item['question'])) {
                    $question = $item['question'];
                    $options = $item['allOptions'] ?? [];
                    $summary = $item['summary'] ?? [];
                } else {
                    // Старый формат
                    $question = $item;
                    $options = $item['allOptions'] ?? $item['options'] ?? [];
                    $summary = $item['summary'] ?? [];
                }
                
                $text = trim($question['text'] ?? '');
                
                // Пропускаем пустые или служебные вопросы
                if (empty($text) || $text === 'ID Пользователя' || $text === 'ID не найден') {
                    $skippedQuestions++;
                    continue;
                }
                
                // Определяем тип вопроса
                $type = $typeMapping[$question['type'] ?? 'radio'] ?? 'single';
                
                // Для single и multiple проверяем наличие вариантов
                if (in_array($type, ['single', 'multiple']) && empty($options)) {
                    $skippedQuestions++;
                    continue;
                }
                
                // Получаем баллы из summary или из первого варианта
                $points = 1;
                if (!empty($summary) && isset($summary['maxPossibleScore'])) {
                    $points = (float)$summary['maxPossibleScore'];
                } elseif (!empty($options) && isset($options[0]['score'])) {
                    $points = (float)$options[0]['score'];
                }
                if ($points <= 0) $points = 1;
                
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
            flex-wrap: wrap;
        }
        
        .form-row .form-group {
            flex: 1;
            min-width: 150px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-top: 28px;
        }
        
        .checkbox-group label {
            font-weight: 400;
            cursor: pointer;
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

        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 6px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fafafa;
            margin-bottom: 15px;
        }
        
        .file-upload-area:hover {
            border-color: #007bff;
            background: #f8f9fa;
        }
        
        .file-upload-area.dragover {
            border-color: #007bff;
            background: #e7f1ff;
        }
        
        .file-upload-area .icon {
            font-size: 40px;
            color: #6c757d;
            display: block;
            margin-bottom: 10px;
        }
        
        .file-upload-area .text {
            font-size: 14px;
            color: #6c757d;
        }
        
        .file-upload-area .text strong {
            color: #2c3e50;
        }
        
        .file-upload-area .file-name {
            font-weight: 600;
            color: #007bff;
            margin-top: 5px;
            display: none;
        }
        
        .file-upload-area .file-name.visible {
            display: block;
        }
        
        #json_file {
            display: none;
        }
        
        .btn-upload {
            background: #6c757d;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }
        
        .btn-upload:hover {
            background: #5a6268;
        }
        
        .input-method-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .input-method-tabs button {
            padding: 8px 20px;
            border: 1px solid #dee2e6;
            background: #fff;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .input-method-tabs button:hover {
            background: #f8f9fa;
        }
        
        .input-method-tabs button.active {
            background: #007bff;
            color: #fff;
            border-color: #007bff;
        }
        
        .input-method-tabs button.active:hover {
            background: #0056b3;
        }
        
        .input-method {
            display: none;
        }
        
        .input-method.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .checkbox-group {
                padding-top: 0;
            }
            
            .import-container {
                padding: 15px;
            }
            
            .json-editor {
                min-height: 250px;
                font-size: 12px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            }
            
            .input-method-tabs {
                flex-direction: column;
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
                    <li><a href="/public/index.php">📝Список</a></li>
                    <li><a href="results.php">📈 Результаты</a></li>
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
                        
                        <form method="POST" id="importForm" enctype="multipart/form-data">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="test_title">Название теста</label>
                                    <input type="text" id="test_title" name="test_title" class="form-control" 
                                           placeholder="Оставьте пустым для авто-определения" 
                                           value="<?php echo isset($_POST['test_title']) ? htmlspecialchars($_POST['test_title']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="test_description">Описание теста</label>
                                    <input type="text" id="test_description" name="test_description" class="form-control" 
                                           placeholder="Краткое описание (необязательно)"
                                           value="<?php echo isset($_POST['test_description']) ? htmlspecialchars($_POST['test_description']) : ''; ?>">
                                </div>
                                <div class="form-group checkbox-group">
                                    <label>
                                        <input type="checkbox" name="auto_title" value="1" checked>
                                        Авто-определение названия из JSON
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Способ ввода JSON</label>
                                <div class="input-method-tabs">
                                    <button type="button" class="active" data-method="paste">📝 Вставить текст</button>
                                    <button type="button" data-method="file">📎 Загрузить файл</button>
                                </div>
                                
                                <div id="method-paste" class="input-method active">
                                    <label for="json_data">JSON данные *</label>
                                    <textarea id="json_data" name="json_data" class="json-editor" 
                                              placeholder='Вставьте JSON данные теста здесь...'><?php echo isset($_POST['json_data']) ? htmlspecialchars($_POST['json_data']) : ''; ?></textarea>
                                </div>
                                
                                <div id="method-file" class="input-method">
                                    <label>Загрузить JSON файл</label>
                                    <div class="file-upload-area" id="dropArea">
                                        <span class="icon">📄</span>
                                        <div class="text">
                                            <strong>Нажмите для выбора файла</strong> или перетащите его сюда<br>
                                            <small style="color: #999;">Поддерживаются файлы с расширением .json</small>
                                        </div>
                                        <div class="file-name" id="fileName">Выбран файл: <span id="fileNameText"></span></div>
                                        <input type="file" id="json_file" name="json_file" accept=".json,application/json">
                                    </div>
                                    <div class="help-text">
                                        💡 После загрузки файла, его содержимое будет автоматически вставлено в поле JSON выше.
                                    </div>
                                </div>
                                
                                <div class="help-text">
                                    💡 Поддерживаются два формата:<br>
                                    1. <code>{"test": {...}, "questions": [...]}</code> - с объектом test<br>
                                    2. <code>[...]</code> - массив вопросов<br>
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
                                    <div class="stat-item" style="grid-column: span 2;">
                                        <div class="number" id="testTitleFromJson" style="font-size: 16px; color: #007bff;">—</div>
                                        <div class="label">Название теста из JSON</div>
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
                            <strong>📌 Новый формат JSON (с объектом test):</strong>
                            <pre>{
  "test": {
    "id": "6a85b169eb614656728e11d5",
    "title": "Название теста",
    "totalQuestions": 23,
    "totalPossibleScore": 32
  },
  "questions": [
    {
      "question": {
        "type": "radio",
        "text": "Текст вопроса?"
      },
      "allOptions": [
        {"id": "0", "text": "Неправильный ответ", "isCorrect": false},
        {"id": "1", "text": "Правильный ответ", "isCorrect": true}
      ]
    }
  ]
}</pre>
                            <p style="margin-top: 10px; font-size: 13px; color: #666;">
                                ✅ Вопрос "ID Пользователя" автоматически пропускается.<br>
                                ✅ Название теста автоматически определяется из поля <code>test.title</code>.
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
            const testTitleInput = document.getElementById('test_title');
            const autoTitleCheckbox = document.querySelector('input[name="auto_title"]');
            const fileInput = document.getElementById('json_file');
            const dropArea = document.getElementById('dropArea');
            const fileName = document.getElementById('fileName');
            const fileNameText = document.getElementById('fileNameText');
            
            // Переключение между способами ввода
            const tabButtons = document.querySelectorAll('.input-method-tabs button');
            const methods = {
                paste: document.getElementById('method-paste'),
                file: document.getElementById('method-file')
            };
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    tabButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const method = this.dataset.method;
                    Object.keys(methods).forEach(key => {
                        methods[key].classList.toggle('active', key === method);
                    });
                    
                    // Если переключились на file, убираем обязательность textarea
                    if (method === 'file') {
                        jsonInput.removeAttribute('required');
                    } else {
                        jsonInput.setAttribute('required', 'required');
                    }
                });
            });
            
            // Обработка загрузки файла через клик
            dropArea.addEventListener('click', function() {
                fileInput.click();
            });
            
            // Обработка выбора файла
            fileInput.addEventListener('change', function(e) {
                const file = this.files[0];
                if (file) {
                    handleFile(file);
                }
            });
            
            // Обработка drag and drop
            dropArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            dropArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            
            dropArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const file = files[0];
                    if (file.type === 'application/json' || file.name.endsWith('.json')) {
                        handleFile(file);
                    } else {
                        alert('Пожалуйста, загрузите JSON файл');
                    }
                }
            });
            
            // Обработка файла
            function handleFile(file) {
                if (!file.name.endsWith('.json') && file.type !== 'application/json') {
                    alert('Пожалуйста, загрузите JSON файл (расширение .json)');
                    return;
                }
                
                fileNameText.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                fileName.classList.add('visible');
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const content = e.target.result;
                        jsonInput.value = content;
                        
                        // Автоматически переключаемся на вкладку "Вставить текст"
                        // но показываем содержимое
                        tabButtons.forEach(b => b.classList.remove('active'));
                        document.querySelector('[data-method="paste"]').classList.add('active');
                        methods.paste.classList.add('active');
                        methods.file.classList.remove('active');
                        jsonInput.setAttribute('required', 'required');
                        
                        // Триггерим предпросмотр
                        setTimeout(updatePreview, 300);
                    } catch (err) {
                        alert('Ошибка при чтении файла: ' + err.message);
                    }
                };
                reader.onerror = function() {
                    alert('Ошибка при чтении файла');
                };
                reader.readAsText(file);
            }
            
            // Функция обновления предпросмотра
            function updatePreview() {
                const jsonData = jsonInput.value.trim();
                
                if (!jsonData) {
                    statsPreview.classList.remove('visible');
                    return;
                }
                
                try {
                    const data = JSON.parse(jsonData);
                    
                    // Определяем формат и извлекаем данные
                    let questions = [];
                    let testTitle = '';
                    let totalQuestions = 0;
                    let totalPossibleScore = 0;
                    
                    if (data.test && data.questions) {
                        // Новый формат с объектом test
                        testTitle = data.test.title || '';
                        totalQuestions = data.test.totalQuestions || 0;
                        totalPossibleScore = data.test.totalPossibleScore || 0;
                        questions = data.questions || [];
                        
                        // Автозаполнение названия
                        if (autoTitleCheckbox.checked && testTitle) {
                            testTitleInput.value = testTitle;
                        }
                    } else if (Array.isArray(data)) {
                        // Старый формат - массив вопросов
                        questions = data;
                    } else {
                        showStats(0, 0, 0, 0, 0, 0, '');
                        statsPreview.classList.remove('visible');
                        return;
                    }
                    
                    // Показываем название теста
                    document.getElementById('testTitleFromJson').textContent = testTitle || '—';
                    
                    if (!Array.isArray(questions) || questions.length === 0) {
                        showStats(0, 0, 0, 0, 0, 0, testTitle);
                        statsPreview.classList.remove('visible');
                        return;
                    }
                    
                    let total = 0;
                    let single = 0;
                    let multiple = 0;
                    let text = 0;
                    let skipped = 0;
                    let totalOptions = 0;
                    
                    questions.forEach(item => {
                        // Поддержка двух форматов
                        let q, options;
                        if (item.question) {
                            q = item.question;
                            options = item.allOptions || [];
                        } else {
                            q = item;
                            options = item.allOptions || item.options || [];
                        }
                        
                        const qText = q.text || '';
                        
                        // Пропускаем служебные вопросы
                        if (qText === 'ID Пользователя' || qText === 'ID не найден' || qText === '') {
                            skipped++;
                            return;
                        }
                        
                        const type = q.type || 'radio';
                        
                        total++;
                        totalOptions += options.length;
                        
                        if (type === 'radio') {
                            single++;
                        } else if (type === 'checkbox') {
                            multiple++;
                        } else if (type === 'short_text' || type === 'long_text' || type === 'text') {
                            text++;
                        } else {
                            single++;
                        }
                    });
                    
                    showStats(total, single, multiple, text, skipped, totalOptions, testTitle);
                    statsPreview.classList.add('visible');
                    
                } catch (e) {
                    statsPreview.classList.remove('visible');
                }
            }
            
            function showStats(total, single, multiple, text, skipped, options, testTitle) {
                document.getElementById('totalQuestions').textContent = total;
                document.getElementById('singleQuestions').textContent = single;
                document.getElementById('multipleQuestions').textContent = multiple;
                document.getElementById('textQuestions').textContent = text;
                document.getElementById('skippedQuestions').textContent = skipped;
                document.getElementById('totalOptions').textContent = options;
                document.getElementById('testTitleFromJson').textContent = testTitle || '—';
            }
            
            // Автоматический предпросмотр при изменении
            let previewTimeout;
            jsonInput.addEventListener('input', function() {
                clearTimeout(previewTimeout);
                previewTimeout = setTimeout(updatePreview, 300);
            });
            
            // Обновление при изменении чекбокса
            autoTitleCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    updatePreview();
                }
            });
            
            // Ручной предпросмотр по кнопке
            previewBtn.addEventListener('click', function(e) {
                e.preventDefault();
                updatePreview();
            });
            
            // Валидация перед отправкой
            document.getElementById('importForm').addEventListener('submit', function(e) {
                const jsonData = jsonInput.value.trim();
                const fileInput = document.getElementById('json_file');
                const hasFile = fileInput.files && fileInput.files.length > 0;
                
                if (!jsonData && !hasFile) {
                    e.preventDefault();
                    alert('Пожалуйста, введите JSON данные или загрузите JSON файл');
                    return;
                }
                
                if (jsonData) {
                    try {
                        JSON.parse(jsonData);
                    } catch (e) {
                        e.preventDefault();
                        alert('Ошибка в JSON: ' + e.message);
                        return;
                    }
                }
                
                const title = testTitleInput.value.trim();
                if (!title && !autoTitleCheckbox.checked) {
                    e.preventDefault();
                    alert('Введите название теста или включите авто-определение');
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