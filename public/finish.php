<?php
/**
 * Завершение теста
 */

require_once '../config.php';

$token = $_POST['token'] ?? $_GET['token'] ?? '';

if (empty($token)) {
    die('Токен не найден');
}

$testRunner = new TestRunner();

// Сохраняем последние ответы
foreach ($_POST as $key => $value) {
    if (strpos($key, 'question_') === 0 && is_array($value)) {
        $questionId = str_replace('question_', '', $key);
        foreach ($value as $answer) {
            $testRunner->saveAnswer($token, $questionId, $answer);
        }
    }
}

// Завершаем тест
$result = $testRunner->finishTest($token);

if ($result) {
    // Перенаправляем на страницу результатов
    header("Location: result.php?token=" . $token);
    exit;
} else {
    die('Ошибка завершения теста');
}