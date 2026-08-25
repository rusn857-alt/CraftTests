<?php
/**
 * Страница прохождения теста
 * 
 * 
 */

require_once '../config.php';

$slug = $_GET['slug'] ?? '';
$token = $_GET['token'] ?? '';

$testManager = new TestManager();
$testRunner = new TestRunner();
$db = Database::getInstance();

// Если есть токен - восстанавливаем сессию
if ($token) {
    $session = $testRunner->getSession($token);
    if (!$session || $session['status'] !== 'in_progress') {
        die('Сессия не найдена или уже завершена');
    }
    $test = $testManager->getTest($session['test_id']);
} else {
    // Получаем тест по slug
    $test = $testManager->getTestBySlug($slug);
    if (!$test) {
        die('Тест не найден или не активен');
    }
    $session = null;
}

// Если тест не активен и нет сессии
if (!$session && $test['status'] !== 'active') {
    die('Тест не доступен для прохождения');
}

// Обработка начала теста
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_test'])) {
    $userName = trim($_POST['user_name'] ?? '');
    $userEmail = trim($_POST['user_email'] ?? '');
    
    if (empty($userName)) {
        $error = 'Введите ваше имя';
    } else {
        $newToken = $testRunner->startSession($test['id'], $userName, $userEmail);
        if ($newToken) {
            header("Location: take.php?token=" . $newToken);
            exit;
        } else {
            $error = 'Ошибка начала теста';
        }
    }
}

// Инициализация переменных
$questions = [];
$progress = ['total' => 0, 'answered' => 0, 'percentage' => 0];
$settings = [];
$timeLimit = 0;

// Если есть сессия - показываем тест
if ($session) {
    $questions = $testRunner->getTestQuestions(
        $session['test_id'], 
        $session['test_settings']['randomize_questions'] ?? false
    );
    $progress = $testRunner->getProgress($token);
    $settings = $session['test_settings'] ?? [];
    $timeLimit = $settings['time_limit'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Прохождение теста</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .take-wrapper {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .take-header {
            background: #2c3e50;
            color: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .take-header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .take-header .info {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            font-size: 14px;
            color: #bdc3c7;
        }
        
        .progress-bar {
            background: #ecf0f1;
            height: 8px;
            border-radius: 4px;
            margin: 15px 0;
            overflow: hidden;
        }
        
        .progress-bar .fill {
            height: 100%;
            background: #4CAF50;
            transition: width 0.3s;
        }
        
        .question-card {
            background: #fff;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .question-number {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .question-text {
            font-size: 17px;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .question-points {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .option-label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .option-label:hover {
            border-color: #4CAF50;
            background: #f8f9fa;
        }
        
        .option-label input[type="radio"],
        .option-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .option-label.selected {
            border-color: #4CAF50;
            background: #e8f5e9;
        }
        
        .option-label .option-text {
            flex: 1;
        }
        
        .option-label .option-points {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .text-answer {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        
        .text-answer:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn-next {
            padding: 12px 30px;
            background: #4CAF50;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-next:hover {
            background: #45a049;
        }
        
        .btn-finish {
            padding: 12px 30px;
            background: #f44336;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-finish:hover {
            background: #d32f2f;
        }
        
        .btn-prev {
            padding: 12px 30px;
            background: #6c757d;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-prev:hover {
            background: #5a6268;
        }
        
        .start-form {
            max-width: 500px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .start-form h2 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .start-form .form-group {
            margin-bottom: 15px;
        }
        
        .start-form label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .start-form input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .start-form input:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .btn-start {
            display: inline-block;
            padding: 12px 30px;
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
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        @media (max-width: 768px) {
            .take-header .info {
                flex-direction: column;
                gap: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="take-wrapper">
        <?php if (!$session): ?>
            <!-- Форма начала теста -->
            <div class="take-header">
                <h1>📝 <?php echo htmlspecialchars($test['title']); ?></h1>
                <div class="info">
                    <span>📋 <?php 
                        $questionsCount = $db->fetchOne(
                            "SELECT COUNT(*) as count FROM questions WHERE test_id = ?",
                            [$test['id']]
                        );
                        echo $questionsCount['count'] ?? 0; 
                    ?> вопросов</span>
                    <?php if ($test['settings']['time_limit'] ?? 0 > 0): ?>
                        <span>⏱️ <?php echo $test['settings']['time_limit']; ?> минут</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="start-form">
                <h2>👋 Введите ваши данные</h2>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="user_name">Ваше имя *</label>
                        <input type="text" id="user_name" name="user_name" required 
                               value="<?php echo htmlspecialchars($_POST['user_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="user_email">Email (опционально)</label>
                        <input type="email" id="user_email" name="user_email" 
                               value="<?php echo htmlspecialchars($_POST['user_email'] ?? ''); ?>">
                    </div>
                    
                    <button type="submit" name="start_test" class="btn-start" style="width: 100%; text-align: center;">
                        Начать тест
                    </button>
                </form>
            </div>
            
        <?php else: ?>
            <!-- Прохождение теста -->
            <div class="take-header">
                <h1>📝 <?php echo htmlspecialchars($session['test_title']); ?></h1>
                <div class="info">
                    <span>👤 <?php echo htmlspecialchars($session['user_name']); ?></span>
                    <span>📋 Вопрос <?php echo $progress['answered'] + 1; ?> из <?php echo $progress['total']; ?></span>
                    <span>✅ <?php echo $progress['percentage']; ?>% выполнено</span>
                    <?php if ($timeLimit > 0): ?>
                        <span id="timer">⏱️ <?php echo $timeLimit; ?>:00</span>
                    <?php endif; ?>
                </div>
                <div class="progress-bar">
                    <div class="fill" style="width: <?php echo $progress['percentage']; ?>%"></div>
                </div>
            </div>
            
            <form id="testForm" method="POST" action="finish.php">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <?php if (empty($questions)): ?>
                    <div class="alert alert-warning" style="background: #fff3cd; padding: 20px; border-radius: 8px; text-align: center;">
                        <p style="font-size: 18px; margin-bottom: 10px;">⚠️ В этом тесте пока нет вопросов</p>
                        <p style="color: #856404;">Обратитесь к администратору для добавления вопросов</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-card" 
                             data-question-id="<?php echo $question['id']; ?>" 
                             data-question-type="<?php echo $question['type']; ?>"
                             style="<?php echo $index > 0 ? 'display: none;' : ''; ?>">
                            <div class="question-number">
                                Вопрос <?php echo $index + 1; ?> из <?php echo count($questions); ?>
                                <span style="font-weight: 400; font-size: 14px; color: #7f8c8d;">
                                    (<?php echo $question['points']; ?> баллов)
                                </span>
                            </div>
                            <div class="question-text"><?php echo htmlspecialchars($question['text']); ?></div>
                            
                            <?php if ($question['type'] === 'single'): ?>
                                <div class="options">
                                    <?php foreach ($question['options'] as $option): ?>
                                        <label class="option-label">
                                            <input type="radio" 
                                                   name="question_<?php echo $question['id']; ?>[]" 
                                                   value="<?php echo $option['id']; ?>"
                                                   data-question="<?php echo $question['id']; ?>">
                                            <span class="option-text"><?php echo htmlspecialchars($option['text']); ?></span>
                                            <?php if ($option['points'] !== null && $option['points'] != 0): ?>
                                                <span class="option-points">(+<?php echo $option['points']; ?> баллов)</span>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($question['type'] === 'multiple'): ?>
                                <div class="options">
                                    <?php foreach ($question['options'] as $option): ?>
                                        <label class="option-label">
                                            <input type="checkbox" 
                                                   name="question_<?php echo $question['id']; ?>[]" 
                                                   value="<?php echo $option['id']; ?>"
                                                   data-question="<?php echo $question['id']; ?>">
                                            <span class="option-text"><?php echo htmlspecialchars($option['text']); ?></span>
                                            <?php if ($option['points'] !== null && $option['points'] != 0): ?>
                                                <span class="option-points">(+<?php echo $option['points']; ?> баллов)</span>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($question['type'] === 'text'): ?>
                                <textarea class="text-answer" 
                                          name="question_<?php echo $question['id']; ?>[]" 
                                          rows="4" 
                                          placeholder="Введите ваш ответ..."></textarea>
                            <?php elseif ($question['type'] === 'number'): ?>
                                <input type="number" 
                                       class="text-answer" 
                                       name="question_<?php echo $question['id']; ?>[]" 
                                       placeholder="Введите число..." 
                                       step="any">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <button type="button" id="prevBtn" class="btn-prev" style="display: none;">◀ Назад</button>
                    <button type="button" id="nextBtn" class="btn-next">Далее ▶</button>
                    <button type="submit" id="finishBtn" class="btn-finish" style="display: none;">📊 Завершить тест</button>
                </div>
            </form>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const questions = document.querySelectorAll('.question-card');
                    let currentQuestion = 0;
                    const totalQuestions = questions.length;
                    
                    const prevBtn = document.getElementById('prevBtn');
                    const nextBtn = document.getElementById('nextBtn');
                    const finishBtn = document.getElementById('finishBtn');
                    
                    function showQuestion(index) {
                        questions.forEach((q, i) => {
                            q.style.display = i === index ? 'block' : 'none';
                        });
                        
                        prevBtn.style.display = index > 0 ? 'inline-block' : 'none';
                        
                        if (index === totalQuestions - 1) {
                            nextBtn.style.display = 'none';
                            finishBtn.style.display = 'inline-block';
                        } else {
                            nextBtn.style.display = 'inline-block';
                            finishBtn.style.display = 'none';
                        }
                    }
                    
                    // Функция сохранения текущего ответа
                    function saveCurrentQuestion() {
                        const currentQuestionEl = questions[currentQuestion];
                        if (!currentQuestionEl) return;
                        
                        const questionId = currentQuestionEl.dataset.questionId;
                        const questionType = currentQuestionEl.dataset.questionType;
                        const formData = new FormData();
                        
                        // Добавляем токен
                        const tokenInput = document.querySelector('input[name="token"]');
                        if (tokenInput) {
                            formData.append('token', tokenInput.value);
                        }
                        
                        let hasAnswer = false;
                        
                        if (questionType === 'single') {
                            // Для одиночного выбора
                            const selected = currentQuestionEl.querySelector('input[type="radio"]:checked');
                            if (selected) {
                                formData.append('question_' + questionId + '[]', selected.value);
                                hasAnswer = true;
                            }
                        } else if (questionType === 'multiple') {
                            // Для множественного выбора - сохраняем все выбранные
                            const selected = currentQuestionEl.querySelectorAll('input[type="checkbox"]:checked');
                            if (selected.length > 0) {
                                selected.forEach(input => {
                                    formData.append('question_' + questionId + '[]', input.value);
                                });
                                hasAnswer = true;
                            }
                        } else {
                            // Для текстовых и числовых ответов
                            const input = currentQuestionEl.querySelector('.text-answer');
                            if (input && input.value.trim() !== '') {
                                formData.append('question_' + questionId + '[]', input.value);
                                hasAnswer = true;
                            }
                        }
                        
                        if (!hasAnswer) return;
                        
                        // Отправляем AJAX для сохранения
                        fetch('save_answer.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                console.error('Error saving answer:', data.error);
                            }
                        })
                        .catch(error => console.error('Error saving answer:', error));
                    }
                    
                    // Отметка выбранных опций
                    document.querySelectorAll('.option-label input').forEach(input => {
                        input.addEventListener('change', function() {
                            const label = this.closest('.option-label');
                            const parentOptions = this.closest('.options');
                            
                            if (this.type === 'radio') {
                                parentOptions.querySelectorAll('.option-label').forEach(l => {
                                    l.classList.remove('selected');
                                });
                                label.classList.add('selected');
                            } else if (this.type === 'checkbox') {
                                label.classList.toggle('selected', this.checked);
                            }
                            
                            // Автосохранение при выборе
                            saveCurrentQuestion();
                        });
                    });
                    
                    // Обработка кнопки "Далее"
                    nextBtn.addEventListener('click', function() {
                        saveCurrentQuestion();
                        if (currentQuestion < totalQuestions - 1) {
                            currentQuestion++;
                            showQuestion(currentQuestion);
                        }
                    });
                    
                    // Обработка кнопки "Назад"
                    prevBtn.addEventListener('click', function() {
                        if (currentQuestion > 0) {
                            currentQuestion--;
                            showQuestion(currentQuestion);
                        }
                    });
                    
                    // Автоматическое сохранение при вводе текста
                    document.querySelectorAll('.text-answer').forEach(input => {
                        let timeout;
                        input.addEventListener('input', function() {
                            clearTimeout(timeout);
                            timeout = setTimeout(() => {
                                saveCurrentQuestion();
                            }, 500);
                        });
                        
                        input.addEventListener('blur', function() {
                            saveCurrentQuestion();
                        });
                    });
                    
                    // Восстановление сохраненных ответов при загрузке страницы
                    function restoreAnswers() {
                        const token = document.querySelector('input[name="token"]').value;
                        if (!token) return;
                        
                        // Здесь можно добавить загрузку сохраненных ответов
                        // через AJAX запрос к get_answers.php
                    }
                    
                    // Таймер
                    <?php if ($timeLimit > 0): ?>
                        let timeLeft = <?php echo $timeLimit * 60; ?>;
                        const timerElement = document.getElementById('timer');
                        
                        function updateTimer() {
                            const minutes = Math.floor(timeLeft / 60);
                            const seconds = timeLeft % 60;
                            timerElement.textContent = `⏱️ ${minutes}:${seconds.toString().padStart(2, '0')}`;
                            
                            if (timeLeft <= 0) {
                                alert('Время вышло! Тест будет завершен автоматически.');
                                document.getElementById('testForm').submit();
                            }
                            
                            timeLeft--;
                        }
                        
                        setInterval(updateTimer, 1000);
                    <?php endif; ?>
                    
                    // Показываем первый вопрос
                    if (totalQuestions > 0) {
                        showQuestion(0);
                        // Восстанавливаем ответы
                        restoreAnswers();
                    }
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>