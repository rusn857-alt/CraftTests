<?php
// create_test.php - с поддержкой страниц

require_once __DIR__ . '/lib/Utils.php';
require_once __DIR__ . '/lib/TestStorage.php';

$config = require __DIR__ . '/config.php';
$storage = new TestStorage($config['data_dir']);

$error = '';
$success = '';

// Создаем папку для изображений
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    $pages = json_decode($_POST['pages_json'] ?? '[]', true);
    
    // Обработка загруженных изображений
    if (!empty($_FILES)) {
        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'image_') === 0 && $file['error'] === UPLOAD_ERR_OK) {
                // Ищем вопрос по ID
                $questionId = str_replace('image_', '', $key);
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $ext;
                $targetPath = $uploadDir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Сохраняем путь к изображению в вопросе
                    foreach ($pages as &$page) {
                        foreach ($page['questions'] as &$q) {
                            if ($q['id'] === $questionId) {
                                $q['image'] = 'uploads/' . $filename;
                                break 2;
                            }
                        }
                    }
                }
            }
        }
    }
    
    if (empty($title)) {
        $error = 'Введите название теста';
    } elseif (empty($pages) || empty($pages[0]['questions'])) {
        $error = 'Добавьте хотя бы один вопрос на первую страницу';
    } else {
        $testData = [
            'id' => Utils::generateId(),
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'pages' => $pages,
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

// Если это редактирование - получаем данные теста
$testId = $_GET['id'] ?? '';
$editTest = null;
if (!empty($testId)) {
    $editTest = $storage->getTest($testId);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editTest ? 'Редактирование' : 'Создание' ?> теста</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; margin: 0; padding: 20px; color: #333; }
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
        .btn-back { color: white; text-decoration: none; opacity: 0.8; }
        .btn-back:hover { opacity: 1; }
        
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
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 5px; color: #555; }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-control:focus { border-color: #3498db; outline: none; }
        textarea.form-control { resize: vertical; min-height: 60px; }
        
        .btn {
            padding: 10px 20px;
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
        .btn-sm { padding: 4px 10px; font-size: 0.85em; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-warning:hover { background: #e67e22; }
        
        /* Стили для страниц */
        .page-container {
            border: 2px solid #e8f0fe;
            border-radius: 12px;
            margin-bottom: 20px;
            padding: 20px;
            background: #fafbfc;
            position: relative;
        }
        .page-container .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e8f0fe;
        }
        .page-container .page-header .page-title {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1em;
        }
        .page-container .page-header .page-actions {
            display: flex;
            gap: 8px;
        }
        .page-container .remove-page {
            color: #e74c3c;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1em;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .page-container .remove-page:hover {
            background: #fee;
        }
        
        .question-editor {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
            position: relative;
        }
        .question-editor .remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            color: #e74c3c;
            font-size: 1.2em;
            cursor: pointer;
        }
        .question-editor .remove-btn:hover { color: #c0392b; }
        
        .options-list { margin-top: 10px; }
        .option-row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 8px;
            padding: 8px 12px;
            background: #fafbfc;
            border-radius: 6px;
            border: 1px solid #eee;
        }
        .option-row input[type="text"] { flex: 1; border: none; padding: 6px 8px; background: transparent; }
        .option-row input[type="text"]:focus { outline: none; }
        .option-row .correct-indicator {
            color: #27ae60;
            font-weight: bold;
            font-size: 1.1em;
            min-width: 30px;
            text-align: center;
        }
        .option-row .remove-option {
            color: #e74c3c;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1em;
        }
        .option-row .remove-option:hover { color: #c0392b; }
        
        .add-option-btn { margin-top: 8px; }
        .question-type-select { margin-bottom: 10px; }
        .status-select { padding: 8px 12px; border-radius: 8px; border: 1px solid #ddd; }
        
        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        
        .form-actions { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .question-count { font-size: 0.9em; color: #888; }
        
        .points-input {
            width: 80px;
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .points-input:focus { border-color: #3498db; outline: none; }
        
        .inline-group {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .help-text { font-size: 0.85em; color: #999; margin-top: 4px; }
        
        .image-upload {
            margin: 10px 0;
            padding: 15px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        .image-upload:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }
        .image-upload input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .image-upload .upload-label .icon {
            font-size: 2em;
            display: block;
            margin-bottom: 5px;
        }
        .image-preview {
            margin: 10px 0;
            position: relative;
            display: inline-block;
        }
        .image-preview img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .image-preview .remove-image {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-size: 14px;
            line-height: 24px;
            text-align: center;
        }
        .image-preview .remove-image:hover { background: #c0392b; }

        @media (max-width: 600px) {
            .header { flex-direction: column; text-align: center; gap: 10px; }
            .form-actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .option-row { flex-wrap: wrap; }
            .inline-group { flex-direction: column; align-items: stretch; }
            .page-container { padding: 15px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><?= $editTest ? '✏️ Редактирование теста' : '📝 Создание нового теста' ?></h1>
        <a href="index.php" class="btn-back">← Вернуться к списку</a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST" id="testForm" enctype="multipart/form-data">
        <div class="card">
            <h3 class="card-title">📋 Основная информация</h3>
            
            <div class="form-group">
                <label>Название теста *</label>
                <input type="text" name="title" class="form-control" required 
                       value="<?= htmlspecialchars($editTest['title'] ?? '') ?>" 
                       placeholder="Введите название теста">
            </div>
            
            <div class="form-group">
                <label>Описание</label>
                <textarea name="description" class="form-control" 
                          placeholder="Опишите, о чем этот тест"><?= htmlspecialchars($editTest['description'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Статус</label>
                <select name="status" class="status-select">
                    <option value="draft" <?= ($editTest['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Черновик</option>
                    <option value="active" <?= ($editTest['status'] ?? '') === 'active' ? 'selected' : '' ?>>Активный</option>
                    <option value="archived" <?= ($editTest['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Архивирован</option>
                </select>
            </div>
        </div>
        
        <div class="card">
            <h3 class="card-title">📄 Страницы и вопросы</h3>
            
            <div id="pagesContainer"></div>
            
            <button type="button" class="btn btn-primary" onclick="addPage()">➕ Добавить страницу</button>
        </div>
        
        <input type="hidden" name="pages_json" id="pagesJson">
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">💾 Сохранить тест</button>
            <a href="index.php" class="btn btn-outline">Отмена</a>
        </div>
    </form>
</div>

<script>
let pageCounter = 0;
let questionCounter = 0;
let pages = [];

const questionTypes = {
    single: 'Один вариант',
    multiple: 'Несколько вариантов',
    text: 'Краткий текст',
    textarea: 'Развернутый ответ',
    rating: 'Рейтинг',
    scale: 'Шкала'
};

// Инициализация из существующего теста
<?php if ($editTest && isset($editTest['pages'])): ?>
    pages = <?= json_encode($editTest['pages'], JSON_UNESCAPED_UNICODE) ?>;
    pageCounter = pages.length;
    pages.forEach(page => {
        if (page.questions) {
            page.questions.forEach(q => {
                const num = parseInt(q.id.replace('q_', ''));
                if (num > questionCounter) questionCounter = num;
            });
        }
    });
    if (pageCounter === 0) pageCounter = 1;
<?php else: ?>
    // Добавляем первую страницу по умолчанию
    pages = [{
        id: 'page_1',
        title: 'Страница 1',
        questions: []
    }];
    pageCounter = 1;
    // Добавляем первый вопрос
    addQuestion(0);
<?php endif; ?>

function addPage() {
    pageCounter++;
    pages.push({
        id: 'page_' + pageCounter,
        title: 'Страница ' + pageCounter,
        questions: []
    });
    renderPages();
}

function removePage(index) {
    if (pages.length <= 1) {
        alert('Должна быть хотя бы одна страница');
        return;
    }
    if (confirm('Удалить эту страницу?')) {
        pages.splice(index, 1);
        renderPages();
    }
}

function addQuestion(pageIndex) {
    const q = {
        id: 'q_' + ++questionCounter,
        type: 'single',
        text: '',
        required: true,
        points: 1,
        options: [
            { text: '', is_correct: false },
            { text: '', is_correct: false }
        ],
        max_rating: 5,
        min: 1,
        max: 10,
        min_label: '',
        max_label: '',
        image: ''
    };
    pages[pageIndex].questions.push(q);
    renderPages();
}

function removeQuestion(pageIndex, qIndex) {
    if (confirm('Удалить вопрос?')) {
        pages[pageIndex].questions.splice(qIndex, 1);
        renderPages();
    }
}

function addOption(pageIndex, qIndex) {
    pages[pageIndex].questions[qIndex].options.push({ text: '', is_correct: false });
    renderPages();
}

function removeOption(pageIndex, qIndex, oIndex) {
    const options = pages[pageIndex].questions[qIndex].options;
    if (options.length > 2) {
        options.splice(oIndex, 1);
        renderPages();
    } else {
        alert('Должно быть минимум 2 варианта');
    }
}

function toggleCorrect(pageIndex, qIndex, oIndex) {
    const q = pages[pageIndex].questions[qIndex];
    const type = q.type;
    
    if (type === 'single') {
        q.options.forEach((opt, idx) => {
            opt.is_correct = (idx === oIndex);
        });
    } else if (type === 'multiple') {
        q.options[oIndex].is_correct = !q.options[oIndex].is_correct;
    }
    renderPages();
}

function removeImage(pageIndex, qIndex) {
    pages[pageIndex].questions[qIndex].image = '';
    renderPages();
}

function handleImageUpload(pageIndex, qIndex, input) {
    const file = input.files[0];
    if (!file) return;
    
    if (!file.type.startsWith('image/')) {
        alert('Пожалуйста, загрузите изображение');
        input.value = '';
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) {
        alert('Изображение слишком большое. Максимальный размер 5MB');
        input.value = '';
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        pages[pageIndex].questions[qIndex].image = e.target.result;
        renderPages();
    };
    reader.readAsDataURL(file);
}

function changeType(pageIndex, qIndex, type) {
    const q = pages[pageIndex].questions[qIndex];
    q.type = type;
    if (!['single', 'multiple'].includes(type)) {
        q.options = [];
    } else if (!q.options || q.options.length === 0) {
        q.options = [
            { text: '', is_correct: false },
            { text: '', is_correct: false }
        ];
    }
    renderPages();
}

function renderPages() {
    const container = document.getElementById('pagesContainer');
    let html = '';
    
    pages.forEach((page, pIndex) => {
        const questions = page.questions || [];
        
        html += `
            <div class="page-container">
                <div class="page-header">
                    <span class="page-title">📄 ${escapeHtml(page.title)}</span>
                    <div class="page-actions">
                        <button type="button" class="btn btn-sm btn-warning" 
                                onclick="editPageTitle(${pIndex})">✏️</button>
                        <button type="button" class="remove-page" 
                                onclick="removePage(${pIndex})">✕</button>
                    </div>
                </div>
                
                <div style="margin-bottom: 10px;">
                    <span class="question-count">Вопросов: ${questions.length}</span>
                </div>
        `;
        
        questions.forEach((q, qIndex) => {
            const type = q.type;
            
            html += `
                <div class="question-editor">
                    <button type="button" class="remove-btn" 
                            onclick="removeQuestion(${pIndex}, ${qIndex})">✕</button>
                    
                    <div class="form-group" style="margin-bottom: 8px;">
                        <label>Текст вопроса</label>
                        <input type="text" class="form-control" value="${escapeHtml(q.text)}" 
                               onchange="pages[${pIndex}].questions[${qIndex}].text = this.value" 
                               placeholder="Введите текст вопроса">
                    </div>
                    
                    <div class="image-upload">
                        <span class="upload-label">
                            <span class="icon">🖼️</span>
                            ${q.image ? 'Заменить изображение' : 'Нажмите или перетащите изображение'}
                        </span>
                        <input type="file" id="file_${q.id}" name="image_${q.id}" accept="image/*" 
                               onchange="handleImageUpload(${pIndex}, ${qIndex}, this)">
                    </div>
                    
                    ${q.image ? `
                        <div class="image-preview">
                            <img src="${escapeHtml(q.image)}" alt="Изображение к вопросу">
                            <button type="button" class="remove-image" 
                                    onclick="removeImage(${pIndex}, ${qIndex})">✕</button>
                        </div>
                    ` : ''}
                    
                    <div class="inline-group" style="margin-bottom: 10px; margin-top: 10px;">
                        <div class="form-group" style="margin-bottom: 0; flex:1;">
                            <label>Тип вопроса</label>
                            <select class="form-control" onchange="changeType(${pIndex}, ${qIndex}, this.value)">
                                ${Object.entries(questionTypes).map(([key, label]) => 
                                    `<option value="${key}" ${q.type === key ? 'selected' : ''}>${label}</option>`
                                ).join('')}
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0; width: 120px;">
                            <label>Баллы</label>
                            <input type="number" class="points-input" value="${q.points || 1}" min="0" max="100"
                                   onchange="pages[${pIndex}].questions[${qIndex}].points = parseInt(this.value) || 1">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 4px;">
                        <label>
                            <input type="checkbox" ${q.required ? 'checked' : ''} 
                                   onchange="pages[${pIndex}].questions[${qIndex}].required = this.checked">
                            Обязательный вопрос
                        </label>
                    </div>
                    
                    ${renderQuestionOptions(pIndex, qIndex, q)}
                </div>
            `;
        });
        
        html += `
                <button type="button" class="btn btn-success btn-sm" 
                        onclick="addQuestion(${pIndex})">➕ Добавить вопрос</button>
            </div>
        `;
    });
    
    container.innerHTML = html;
    updateJson();
}

function renderQuestionOptions(pageIndex, qIndex, q) {
    let html = '';
    const type = q.type;
    
    if (['single', 'multiple'].includes(type)) {
        const options = q.options || [];
        html += `<div class="options-list">`;
        
        if (options.length === 0) {
            pages[pageIndex].questions[qIndex].options = [
                { text: '', is_correct: false },
                { text: '', is_correct: false }
            ];
            return renderQuestionOptions(pageIndex, qIndex, pages[pageIndex].questions[qIndex]);
        }
        
        options.forEach((opt, oIndex) => {
            const isCorrect = opt.is_correct || false;
            const optText = typeof opt === 'string' ? opt : (opt.text || '');
            
            html += `
                <div class="option-row">
                    <span class="correct-indicator">
                        ${isCorrect ? '✅' : '⬜'}
                    </span>
                    <input type="text" class="form-control" value="${escapeHtml(optText)}" 
                           placeholder="Вариант ${oIndex + 1}"
                           onchange="pages[${pageIndex}].questions[${qIndex}].options[${oIndex}].text = this.value">
                    <button type="button" class="btn btn-sm ${isCorrect ? 'btn-success' : 'btn-outline'}" 
                            onclick="toggleCorrect(${pageIndex}, ${qIndex}, ${oIndex})"
                            style="white-space:nowrap;">
                        ${isCorrect ? '✓ Правильный' : '☐ Отметить'}
                    </button>
                    <button type="button" class="remove-option" 
                            onclick="removeOption(${pageIndex}, ${qIndex}, ${oIndex})">✕</button>
                </div>
            `;
        });
        html += `
            <button type="button" class="btn btn-outline add-option-btn" 
                    onclick="addOption(${pageIndex}, ${qIndex})">➕ Добавить вариант</button>
            <div class="help-text">${type === 'single' ? 'Для одиночного выбора можно отметить только один правильный ответ' : 'Для множественного выбора можно отметить несколько правильных ответов'}</div>
        </div>`;
    } else if (type === 'rating') {
        html += `
            <div class="form-group">
                <label>Максимальный рейтинг</label>
                <input type="number" class="form-control" value="${q.max_rating || 5}" min="1" max="10"
                       onchange="pages[${pageIndex}].questions[${qIndex}].max_rating = parseInt(this.value) || 5">
            </div>
        `;
    } else if (type === 'scale') {
        html += `
            <div class="form-group">
                <label>Минимальное значение</label>
                <input type="number" class="form-control" value="${q.min || 1}" 
                       onchange="pages[${pageIndex}].questions[${qIndex}].min = parseInt(this.value) || 1">
            </div>
            <div class="form-group">
                <label>Максимальное значение</label>
                <input type="number" class="form-control" value="${q.max || 10}" 
                       onchange="pages[${pageIndex}].questions[${qIndex}].max = parseInt(this.value) || 10">
            </div>
            <div class="form-group">
                <label>Подпись минимума</label>
                <input type="text" class="form-control" value="${escapeHtml(q.min_label)}" 
                       placeholder="Например: Плохо"
                       onchange="pages[${pageIndex}].questions[${qIndex}].min_label = this.value">
            </div>
            <div class="form-group">
                <label>Подпись максимума</label>
                <input type="text" class="form-control" value="${escapeHtml(q.max_label)}" 
                       placeholder="Например: Отлично"
                       onchange="pages[${pageIndex}].questions[${qIndex}].max_label = this.value">
            </div>
        `;
    } else if (type === 'text' || type === 'textarea') {
        html += `
            <div class="help-text">Для текстовых ответов баллы начисляются за факт заполнения. Правильные ответы не проверяются автоматически.</div>
        `;
    }
    
    return html;
}

function editPageTitle(index) {
    const newTitle = prompt('Введите новое название страницы:', pages[index].title);
    if (newTitle !== null && newTitle.trim() !== '') {
        pages[index].title = newTitle.trim();
        renderPages();
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function updateJson() {
    // Очищаем data:image из JSON для отправки
    const cleanPages = pages.map(page => ({
        ...page,
        questions: page.questions.map(q => {
            const clean = { ...q };
            // Если это base64, оставляем как есть (для отображения), но не отправляем
            return clean;
        })
    }));
    document.getElementById('pagesJson').value = JSON.stringify(cleanPages);
}

document.addEventListener('DOMContentLoaded', function() {
    renderPages();
});

document.getElementById('testForm').addEventListener('submit', function(e) {
    updateJson();
    // Проверяем, что есть хотя бы один вопрос
    let hasQuestions = false;
    pages.forEach(page => {
        if (page.questions && page.questions.length > 0) {
            hasQuestions = true;
        }
    });
    if (!hasQuestions) {
        e.preventDefault();
        alert('Добавьте хотя бы один вопрос');
    }
});
</script>
</body>
</html>