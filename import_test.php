<?php
// import_test.php - Импорт теста из JSON

require_once __DIR__ . '/lib/Utils.php';
require_once __DIR__ . '/lib/TestStorage.php';

$config = require __DIR__ . '/config.php';
$storage = new TestStorage($config['data_dir']);

$error = '';
$success = '';
$previewData = null;

// Обработка загрузки файла
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['json_file'])) {
    $file = $_FILES['json_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Ошибка загрузки файла: ' . $file['error'];
    } elseif ($file['type'] !== 'application/json' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'json') {
        $error = 'Пожалуйста, загрузите файл в формате JSON';
    } else {
        $content = file_get_contents($file['tmp_name']);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = 'Ошибка парсинга JSON: ' . json_last_error_msg();
        } else {
            // Проверяем структуру данных
            if (isset($data['questions']) && is_array($data['questions'])) {
                // Формат из примера - массив вопросов
                $previewData = $data;
                $success = 'Файл успешно загружен! Проверьте данные и нажмите "Импортировать".';
            } elseif (isset($data['test']) && isset($data['questions'])) {
                // Формат с оберткой test
                $previewData = $data;
                $success = 'Файл успешно загружен! Проверьте данные и нажмите "Импортировать".';
            } else {
                $error = 'Неверная структура JSON. Ожидается массив questions или объект с полями test и questions.';
            }
        }
    }
}

// Обработка импорта
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_data'])) {
    $importData = json_decode($_POST['import_data'], true);
    
    if (!$importData) {
        $error = 'Ошибка данных для импорта';
    } else {
        // Извлекаем данные
        $questions = [];
        $testTitle = 'Импортированный тест';
        
        if (isset($importData['questions']) && is_array($importData['questions'])) {
            // Проверяем, есть ли обертка test
            if (isset($importData['test']['title'])) {
                $testTitle = $importData['test']['title'];
            }
            $questions = $importData['questions'];
        } else {
            $error = 'Не найдены вопросы для импорта';
        }
        
        if (empty($error) && !empty($questions)) {
            // Конвертируем вопросы в формат конструктора
            $convertedQuestions = convertQuestions($questions);
            
            // Создаем тест
            $testData = [
                'id' => Utils::generateId(),
                'title' => $testTitle,
                'description' => 'Импортирован из JSON',
                'status' => 'active',
                'questions' => $convertedQuestions,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'form_id' => 'form_' . uniqid()
            ];
            
            if ($storage->saveTest($testData)) {
                header('Location: index.php?created=1');
                exit;
            } else {
                $error = 'Ошибка сохранения теста';
            }
        }
    }
}

/**
 * Конвертирует вопросы из формата JSON в формат конструктора
 */
function convertQuestions($questions) {
    $converted = [];
    $qCounter = 0;
    
    foreach ($questions as $q) {
        // Пропускаем вопрос ID Пользователя
        if (isset($q['question']['text']) && 
            stripos($q['question']['text'], 'ID Пользователя') !== false) {
            continue;
        }
        
        $qCounter++;
        $type = $q['question']['type'] ?? 'radio';
        $options = $q['allOptions'] ?? [];
        
        // Определяем тип вопроса
        $questionType = 'single';
        if ($type === 'radio') {
            $questionType = 'single';
        } elseif ($type === 'checkbox') {
            $questionType = 'multiple';
        } elseif ($type === 'short_text') {
            $questionType = 'text';
        } elseif ($type === 'long_text') {
            $questionType = 'textarea';
        } elseif ($type === 'rating') {
            $questionType = 'rating';
        } else {
            $questionType = 'single';
        }
        
        // Преобразуем опции
        $convertedOptions = [];
        $points = 1;
        
        if (!empty($options) && is_array($options)) {
            foreach ($options as $opt) {
                $text = $opt['text'] ?? '';
                $isCorrect = $opt['isCorrect'] ?? false;
                $score = intval($opt['score'] ?? 0);
                
                // Определяем правильность ответа
                // Если есть правильные ответы в отдельном поле
                if (isset($q['correctAnswers']) && is_array($q['correctAnswers'])) {
                    foreach ($q['correctAnswers'] as $correct) {
                        if (($correct['text'] ?? '') === $text) {
                            $isCorrect = true;
                            $score = intval($correct['score'] ?? 1);
                        }
                    }
                }
                
                // Берем максимальный балл из правильных ответов
                if ($isCorrect && $score > $points) {
                    $points = $score;
                }
                
                $convertedOptions[] = [
                    'text' => $text,
                    'is_correct' => $isCorrect
                ];
            }
        }
        
        // Если нет опций, добавляем две пустые для single/multiple
        if (empty($convertedOptions) && in_array($questionType, ['single', 'multiple'])) {
            $convertedOptions = [
                ['text' => '', 'is_correct' => false],
                ['text' => '', 'is_correct' => false]
            ];
        }
        
        // Определяем баллы
        if (isset($q['summary']['maxPossibleScore'])) {
            $points = intval($q['summary']['maxPossibleScore']);
        } elseif ($points === 0) {
            $points = 1;
        }
        
        $converted[] = [
            'id' => 'q_' . $qCounter,
            'type' => $questionType,
            'text' => $q['question']['text'] ?? 'Вопрос ' . $qCounter,
            'required' => $q['question']['isRequired'] ?? true,
            'points' => $points,
            'options' => $convertedOptions,
            'max_rating' => 5,
            'min' => 1,
            'max' => 10,
            'min_label' => '',
            'max_label' => '',
            'image' => ''
        ];
    }
    
    return $converted;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Импорт теста из JSON</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f0f2f5; 
            margin: 0; 
            padding: 20px; 
            color: #333; 
        }
        .container { max-width: 1000px; margin: 0 auto; }
        
        .header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .header h1 { margin: 0; font-size: 1.5em; }
        .btn-back { 
            color: white; 
            text-decoration: none; 
            opacity: 0.8;
            padding: 8px 16px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            transition: all 0.3s;
        }
        .btn-back:hover { 
            opacity: 1;
            background: rgba(255,255,255,0.1);
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .card-title {
            font-size: 1.1em;
            font-weight: 600;
            margin: 0 0 15px 0;
            color: #2c3e50;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 10px;
        }
        
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        .upload-area:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }
        .upload-area.dragover {
            border-color: #27ae60;
            background: #f0fff4;
        }
        .upload-area .icon {
            font-size: 4em;
            display: block;
            margin-bottom: 10px;
        }
        .upload-area .label {
            font-size: 1.1em;
            color: #555;
        }
        .upload-area .sub-label {
            font-size: 0.9em;
            color: #999;
            margin-top: 5px;
        }
        .upload-area input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #219a52; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-outline { background: transparent; border: 2px solid #ddd; color: #555; }
        .btn-outline:hover { border-color: #999; background: #f8f9fa; }
        
        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }
        .preview-table th {
            background: #2c3e50;
            color: white;
            padding: 10px 14px;
            text-align: left;
            font-size: 0.8em;
            text-transform: uppercase;
        }
        .preview-table td {
            padding: 8px 14px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        .preview-table tr:hover { background: #f8f9fa; }
        .preview-table .correct { color: #27ae60; font-weight: bold; }
        .preview-table .wrong { color: #e74c3c; }
        .preview-table .badge-option {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            margin: 1px 2px;
        }
        .badge-correct { background: #d4edda; color: #155724; }
        .badge-wrong { background: #f8d7da; color: #721c24; }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card .number {
            font-size: 1.8em;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-card .label {
            color: #888;
            font-size: 0.85em;
        }
        
        @media (max-width: 600px) {
            .header { flex-direction: column; text-align: center; gap: 10px; }
            .actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .preview-table { font-size: 0.8em; }
            .preview-table th, .preview-table td { padding: 6px 10px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📥 Импорт теста из JSON</h1>
        <a href="index.php" class="btn-back">← Вернуться к списку</a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success && !$previewData): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <?php if (!$previewData): ?>
        <!-- Форма загрузки -->
        <div class="card">
            <h3 class="card-title">📤 Загрузите JSON файл</h3>
            
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-area" id="dropZone">
                    <span class="icon">📁</span>
                    <div class="label">Нажмите или перетащите файл сюда</div>
                    <div class="sub-label">Поддерживаются файлы в формате JSON</div>
                    <input type="file" name="json_file" accept=".json,application/json" onchange="this.form.submit()">
                </div>
                
                <div style="margin-top: 15px; text-align: center;">
                    <button type="submit" class="btn btn-primary">📤 Загрузить и проверить</button>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h3 class="card-title">📋 Пример структуры JSON</h3>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 0.85em;">
                <pre style="margin: 0; white-space: pre-wrap; word-break: break-all;">
{
  "test": {
    "title": "Название теста",
    "totalQuestions": 10
  },
  "questions": [
    {
      "question": {
        "type": "radio",
        "text": "Текст вопроса",
        "isRequired": true
      },
      "allOptions": [
        {"text": "Вариант 1", "isCorrect": true, "score": 3},
        {"text": "Вариант 2", "isCorrect": false, "score": 0}
      ],
      "correctAnswers": [
        {"text": "Вариант 1", "score": 3}
      ]
    }
  ]
}
                </pre>
            </div>
            <div style="margin-top: 10px; font-size: 0.9em; color: #666;">
                <strong>Поддерживаемые типы вопросов:</strong> 
                radio (один вариант), checkbox (несколько вариантов), 
                short_text (краткий текст), long_text (развернутый ответ)
            </div>
        </div>
    <?php else: ?>
        <!-- Предпросмотр -->
        <div class="card">
            <h3 class="card-title">📋 Предпросмотр импорта</h3>
            
            <?php
            $questions = $importData['questions'] ?? $previewData['questions'] ?? [];
            $testTitle = $importData['test']['title'] ?? $previewData['test']['title'] ?? 'Импортированный тест';
            $totalQuestions = count($questions);
            $totalScore = 0;
            
            foreach ($questions as $q) {
                if (isset($q['summary']['maxPossibleScore'])) {
                    $totalScore += $q['summary']['maxPossibleScore'];
                }
            }
            ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number"><?= $totalQuestions ?></div>
                    <div class="label">Всего вопросов</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?= $totalScore ?></div>
                    <div class="label">Максимальный балл</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?= htmlspecialchars($testTitle) ?></div>
                    <div class="label">Название теста</div>
                </div>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">№</th>
                            <th>Вопрос</th>
                            <th>Тип</th>
                            <th>Варианты ответов</th>
                            <th style="text-align: center;">Баллы</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $qIndex = 0;
                        foreach ($questions as $q):
                            if (isset($q['question']['text']) && 
                                stripos($q['question']['text'], 'ID Пользователя') !== false) {
                                continue;
                            }
                            $qIndex++;
                            $qText = $q['question']['text'] ?? 'Вопрос ' . $qIndex;
                            $qType = $q['question']['type'] ?? 'radio';
                            $typeLabels = [
                                'radio' => 'Один вариант',
                                'checkbox' => 'Несколько вариантов',
                                'short_text' => 'Краткий текст',
                                'long_text' => 'Развернутый ответ',
                                'rating' => 'Рейтинг'
                            ];
                            $typeLabel = $typeLabels[$qType] ?? $qType;
                            $options = $q['allOptions'] ?? [];
                            $maxScore = $q['summary']['maxPossibleScore'] ?? 0;
                        ?>
                            <tr>
                                <td><?= $qIndex ?></td>
                                <td><?= htmlspecialchars($qText) ?></td>
                                <td><?= $typeLabel ?></td>
                                <td>
                                    <?php if (!empty($options)): ?>
                                        <?php foreach ($options as $opt): 
                                            $isCorrect = $opt['isCorrect'] ?? false;
                                            $text = $opt['text'] ?? '';
                                            $score = $opt['score'] ?? 0;
                                        ?>
                                            <span class="badge-option <?= $isCorrect ? 'badge-correct' : 'badge-wrong' ?>">
                                                <?= htmlspecialchars($text) ?>
                                                <?php if ($isCorrect): ?> ✅<?php endif; ?>
                                                (<?= $score ?>)
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color: #999;">Нет вариантов</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center; font-weight: bold;"><?= $maxScore ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="actions">
                <form method="POST">
                    <input type="hidden" name="import_data" value="<?= htmlspecialchars(json_encode($previewData)) ?>">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Импортировать тест?')">
                        ✅ Импортировать тест
                    </button>
                </form>
                <a href="import_test.php" class="btn btn-outline">🔄 Выбрать другой файл</a>
                <a href="index.php" class="btn btn-outline">← Отмена</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Drag and Drop поддержка
const dropZone = document.getElementById('dropZone');
if (dropZone) {
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    dropZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });
    
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const input = this.querySelector('input[type="file"]');
            input.files = files;
            // Автоматическая отправка формы
            document.getElementById('uploadForm').submit();
        }
    });
}
</script>
</body>
</html>