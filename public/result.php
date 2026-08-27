<?php
/**
 * Страница результатов теста
 */

require_once '../config.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('Токен не найден');
}

$testRunner = new TestRunner();
$db = Database::getInstance();
$session = $testRunner->getSession($token);

if (!$session) {
    die('Сессия не найдена');
}

// Если тест не завершен - завершаем
if ($session['status'] === 'in_progress') {
    $result = $testRunner->finishTest($token);
    $session = $testRunner->getSession($token);
}

// Получаем результаты
$questions = $testRunner->getTestQuestions($session['test_id']);
$answers = $db->fetchAll(
    "SELECT * FROM user_answers WHERE session_id = ?",
    [$session['id']]
);

// Группируем ответы по вопросам
$groupedAnswers = [];
foreach ($answers as $answer) {
    $qId = $answer['question_id'];
    if (!isset($groupedAnswers[$qId])) {
        $groupedAnswers[$qId] = [];
    }
    $groupedAnswers[$qId][] = $answer;
}

$settings = $session['test_settings'] ?? [];
$showResults = $settings['show_results'] ?? true;
$passingScore = $settings['passing_score'] ?? 0;

$totalScore = $session['total_score'] ?? 0;
$maxScore = $session['max_possible_score'] ?? 1;
$percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
$passed = $maxScore > 0 && $percentage >= $passingScore;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Результаты теста</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .result-wrapper {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .result-header {
            text-align: center;
            padding: 40px 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .result-header .icon {
            font-size: 64px;
            margin-bottom: 15px;
        }
        
        .result-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .result-header .score {
            font-size: 48px;
            font-weight: 700;
            color: #2c3e50;
            margin: 15px 0;
        }
        
        .result-header .details {
            display: flex;
            justify-content: center;
            gap: 40px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #ecf0f1;
        }
        
        .result-header .details .item {
            text-align: center;
        }
        
        .result-header .details .item .value {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .result-header .details .item .label {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 4px;
        }
        
        .passed {
            color: #4CAF50;
        }
        
        .failed {
            color: #f44336;
        }
        
        .result-details {
            background: #fff;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .result-details h2 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .result-item {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .result-item:last-child {
            border-bottom: none;
        }
        
        .result-item .question-text {
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .result-item .answer-text {
            font-size: 14px;
            color: #555;
            margin-bottom: 5px;
        }
        
        .result-item .status {
            font-size: 20px;
        }
        
        .result-item .correct {
            color: #4CAF50;
        }
        
        .result-item .incorrect {
            color: #f44336;
        }
        
        .btn-again {
            display: inline-block;
            padding: 12px 30px;
            background: #4CAF50;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        
        .btn-again:hover {
            background: #45a049;
        }
        
        .result-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .result-header .details {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="result-wrapper">
        <div class="result-header">
            <div class="icon">
                <?php if ($passed): ?>
                    🎉
                <?php else: ?>
                    😅
                <?php endif; ?>
            </div>
            <h1 class="<?php echo $passed ? 'passed' : 'failed'; ?>">
                <?php echo $passed ? 'Тест пройден!' : 'Тест не пройден'; ?>
            </h1>
            <div class="score">
                <?php echo number_format($totalScore, 2); ?> / <?php echo number_format($maxScore, 2); ?>
            </div>
            <div class="details">
                <div class="item">
                    <div class="value"><?php echo $percentage; ?>%</div>
                    <div class="label">Правильных ответов</div>
                </div>
                <?php if ($passingScore > 0): ?>
                    <div class="item">
                        <div class="value"><?php echo $passingScore; ?>%</div>
                        <div class="label">Проходной балл</div>
                    </div>
                <?php endif; ?>
                <div class="item">
                    <div class="value"><?php echo htmlspecialchars($session['user_name']); ?></div>
                    <div class="label">Участник</div>
                </div>
            </div>
            <a href="index.php" class="btn-again">📚 К списку тестов</a>
            <a href="/admin/index.php" class="btn-again">📚 Перейти в меню</a>
        </div>
        
        <?php if ($showResults && !empty($questions)): ?>
            <div class="result-details">
                <h2>📋 Детали ответов</h2>
                <?php 
                $hasDisplayedAnswers = false;
                foreach ($questions as $index => $question):
                    $userAnswers = $groupedAnswers[$question['id']] ?? [];
                    
                    // Определяем правильность ответа
                    $isCorrect = false;
                    $answerDisplay = '';
                    
                    if (!empty($userAnswers)) {
                        switch ($question['type']) {
                            case 'single':
                                $optionId = $userAnswers[0]['answer_option_id'] ?? null;
                                if ($optionId) {
                                    $option = $db->fetchOne(
                                        "SELECT text, is_correct FROM answer_options WHERE id = ?",
                                        [$optionId]
                                    );
                                    if ($option) {
                                        $answerDisplay = $option['text'];
                                        $isCorrect = (bool)$option['is_correct'];
                                    }
                                }
                                break;
                                
                            case 'multiple':
                                $selectedTexts = [];
                                $allCorrect = true;
                                foreach ($userAnswers as $ua) {
                                    if ($ua['answer_option_id']) {
                                        $option = $db->fetchOne(
                                            "SELECT text, is_correct FROM answer_options WHERE id = ?",
                                            [$ua['answer_option_id']]
                                        );
                                        if ($option) {
                                            $selectedTexts[] = $option['text'];
                                            if (!$option['is_correct']) {
                                                $allCorrect = false;
                                            }
                                        }
                                    }
                                }
                                $answerDisplay = implode(', ', $selectedTexts);
                                
                                // Проверяем, что выбраны все правильные варианты
                                $correctOptions = $db->fetchAll(
                                    "SELECT id FROM answer_options WHERE question_id = ? AND is_correct = 1",
                                    [$question['id']]
                                );
                                $correctIds = array_column($correctOptions, 'id');
                                $selectedIds = array_column($userAnswers, 'answer_option_id');
                                sort($correctIds);
                                sort($selectedIds);
                                $isCorrect = ($selectedIds == $correctIds && !empty($correctIds));
                                break;
                                
                            case 'text':
                            case 'number':
                                $answerDisplay = $userAnswers[0]['answer_text'] ?? 'Нет ответа';
                                $isCorrect = false; // Оценивается вручную
                                break;
                        }
                    } else {
                        $answerDisplay = 'Нет ответа';
                        $isCorrect = false;
                    }
                    
                    $hasDisplayedAnswers = true;
                ?>
                    <div class="result-item">
                        <div class="result-item-header">
                            <div class="question-text">
                                Вопрос <?php echo $index + 1; ?>: <?php echo htmlspecialchars($question['text']); ?>
                            </div>
                            <div class="status <?php echo $isCorrect ? 'correct' : 'incorrect'; ?>">
                                <?php echo $isCorrect ? '✅' : '❌'; ?>
                            </div>
                        </div>
                        <div class="answer-text">
                            <strong>Ответ:</strong> <?php echo htmlspecialchars($answerDisplay ?: 'Нет ответа'); ?>
                        </div>
                        <?php if ($question['type'] === 'multiple'): ?>
                            <div style="font-size: 12px; color: #999; margin-top: 5px;">
                                <em>Выбрано вариантов: <?php echo count($userAnswers); ?></em>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!$hasDisplayedAnswers): ?>
                    <p style="text-align: center; color: #999; padding: 20px 0;">
                        Нет сохраненных ответов для отображения
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>