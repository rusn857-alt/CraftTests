<?php
/**
 * AJAX обработчик сохранения ответов
 */

require_once '../config.php';

header('Content-Type: application/json');

$token = $_POST['token'] ?? '';

if (empty($token)) {
    echo json_encode(['success' => false, 'error' => 'Токен не найден']);
    exit;
}

$testRunner = new TestRunner();
$db = Database::getInstance();

// Получаем сессию
$session = $testRunner->getSession($token);
if (!$session || $session['status'] !== 'in_progress') {
    echo json_encode(['success' => false, 'error' => 'Сессия не активна']);
    exit;
}

try {
    // Обрабатываем каждый вопрос
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'question_') === 0 && is_array($value)) {
            $questionId = (int)str_replace('question_', '', $key);
            
            // Получаем тип вопроса
            $question = $db->fetchOne(
                "SELECT type FROM questions WHERE id = ?",
                [$questionId]
            );
            
            if (!$question) continue;
            
            // Удаляем старые ответы на этот вопрос
            $db->query(
                "DELETE FROM user_answers WHERE session_id = ? AND question_id = ?",
                [$session['id'], $questionId]
            );
            
            // Для множественного выбора - сохраняем все выбранные варианты
            if ($question['type'] === 'multiple') {
                foreach ($value as $answer) {
                    if (!empty($answer)) {
                        $testRunner->saveAnswer($token, $questionId, $answer);
                    }
                }
            } else {
                // Для остальных типов сохраняем только первый ответ
                if (!empty($value[0])) {
                    $testRunner->saveAnswer($token, $questionId, $value[0]);
                }
            }
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Ответы сохранены']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}