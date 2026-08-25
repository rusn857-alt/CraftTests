<?php
/**
 * Класс для прохождения тестов
 */

class TestRunner
{
    /**
     * @var Database Экземпляр Database
     */
    private $db;
    
    /**
     * Конструктор
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Создание сессии прохождения теста
     * 
     * @param int $testId ID теста
     * @param string $userName Имя пользователя
     * @param string $userEmail Email пользователя (опционально)
     * @return string|bool Токен сессии или false
     */
    public function startSession($testId, $userName, $userEmail = null)
    {
        $testId = (int)$testId;
        $userName = trim($userName);
        
        if ($testId <= 0 || empty($userName)) {
            return false;
        }
        
        try {
            // Создаем или получаем пользователя
            $userId = $this->getOrCreateUser($userName, $userEmail);
            
            // Генерируем уникальный токен
            $token = bin2hex(random_bytes(32));
            
            // Создаем сессию
            $this->db->query(
                "INSERT INTO test_sessions (test_id, user_id, session_token, status) 
                 VALUES (?, ?, ?, 'in_progress')",
                [$testId, $userId, $token]
            );
            
            return $token;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Получение или создание пользователя
     * 
     * @param string $name Имя
     * @param string|null $email Email
     * @return int ID пользователя
     */
    private function getOrCreateUser($name, $email = null)
    {
        $email = $email ? trim($email) : null;
        
        // Проверяем существование пользователя по email или имени
        if ($email) {
            $user = $this->db->fetchOne(
                "SELECT id FROM users WHERE email = ?",
                [$email]
            );
            if ($user) {
                return (int)$user['id'];
            }
        }
        
        // Ищем по имени (если email не указан или пользователь не найден)
        $user = $this->db->fetchOne(
            "SELECT id FROM users WHERE name = ? AND email IS NULL",
            [$name]
        );
        if ($user) {
            return (int)$user['id'];
        }
        
        // Создаем нового пользователя
        $this->db->query(
            "INSERT INTO users (name, email) VALUES (?, ?)",
            [$name, $email]
        );
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Получение сессии по токену
     * 
     * @param string $token Токен сессии
     * @return array|null
     */
    public function getSession($token)
    {
        $session = $this->db->fetchOne(
            "SELECT ts.*, t.title as test_title, t.settings as test_settings,
                    u.name as user_name, u.email as user_email
             FROM test_sessions ts
             JOIN tests t ON ts.test_id = t.id
             JOIN users u ON ts.user_id = u.id
             WHERE ts.session_token = ?",
            [$token]
        );
        
        if ($session && $session['test_settings']) {
            $session['test_settings'] = json_decode($session['test_settings'], true);
        }
        
        return $session;
    }
    
    /**
     * Получение вопросов теста для прохождения
     * 
     * @param int $testId ID теста
     * @param bool $randomize Перемешивать вопросы
     * @return array
     */
    public function getTestQuestions($testId, $randomize = false)
    {
        $sql = "SELECT q.* FROM questions q 
                WHERE q.test_id = ? 
                ORDER BY q.sort_order ASC, q.id ASC";
        
        $questions = $this->db->fetchAll($sql, [$testId]);
        
        if ($randomize) {
            shuffle($questions);
        }
        
        // Добавляем варианты ответов
        foreach ($questions as &$question) {
            $options = $this->db->fetchAll(
                "SELECT * FROM answer_options WHERE question_id = ? ORDER BY sort_order ASC",
                [$question['id']]
            );
            $question['options'] = $options;
        }
        
        return $questions;
    }
    
    /**
 * Сохранение ответа пользователя
 * 
 * @param string $token Токен сессии
 * @param int $questionId ID вопроса
 * @param mixed $answer Ответ
 * @return bool
 */
public function saveAnswer($token, $questionId, $answer)
{
    $session = $this->getSession($token);
    if (!$session || $session['status'] !== 'in_progress') {
        return false;
    }
    
    // Получаем вопрос
    $question = $this->db->fetchOne(
        "SELECT * FROM questions WHERE id = ?",
        [$questionId]
    );
    
    if (!$question) {
        return false;
    }
    
    try {
        // Определяем тип ответа
        $answerText = null;
        $answerOptionId = null;
        
        if (in_array($question['type'], ['single', 'multiple'])) {
            $answerOptionId = (int)$answer;
        } else {
            $answerText = trim((string)$answer);
            if (empty($answerText)) {
                return true; // Пустой ответ не сохраняем
            }
        }
        
        // Проверяем, есть ли уже такой ответ
        $existing = null;
        if ($answerOptionId) {
            $existing = $this->db->fetchOne(
                "SELECT id FROM user_answers 
                 WHERE session_id = ? AND question_id = ? AND answer_option_id = ?",
                [$session['id'], $questionId, $answerOptionId]
            );
        } else {
            $existing = $this->db->fetchOne(
                "SELECT id FROM user_answers 
                 WHERE session_id = ? AND question_id = ? AND answer_text = ?",
                [$session['id'], $questionId, $answerText]
            );
        }
        
        if ($existing) {
            return true; // Ответ уже существует
        }
        
        // Сохраняем новый ответ
        $this->db->query(
            "INSERT INTO user_answers (session_id, question_id, answer_text, answer_option_id) 
             VALUES (?, ?, ?, ?)",
            [$session['id'], $questionId, $answerText, $answerOptionId]
        );
        
        return true;
    } catch (Exception $e) {
        error_log('Error saving answer: ' . $e->getMessage());
        return false;
    }
}
    
    /**
 * Завершение теста и подсчет результатов
 * 
 * @param string $token Токен сессии
 * @return array|bool Результаты или false
 */
public function finishTest($token)
{
    $session = $this->getSession($token);
    if (!$session || $session['status'] !== 'in_progress') {
        return false;
    }
    
    // Получаем все вопросы теста
    $questions = $this->getTestQuestions($session['test_id']);
    
    // Получаем ответы пользователя
    $answers = $this->db->fetchAll(
        "SELECT * FROM user_answers WHERE session_id = ?",
        [$session['id']]
    );
    
    $totalScore = 0;
    $maxScore = 0;
    $correctCount = 0;
    $totalCount = count($questions);
    $results = [];
    $userAnswersMap = [];
    
    // Группируем ответы по вопросам
    foreach ($answers as $answer) {
        $qId = $answer['question_id'];
        if (!isset($userAnswersMap[$qId])) {
            $userAnswersMap[$qId] = [];
        }
        $userAnswersMap[$qId][] = $answer;
    }
    
    foreach ($questions as $question) {
        $maxScore += (float)$question['points'];
        $questionResult = [
            'question' => $question,
            'user_answers' => $userAnswersMap[$question['id']] ?? [],
            'is_correct' => false,
            'points_earned' => 0
        ];
        
        $userAnswers = $userAnswersMap[$question['id']] ?? [];
        
        if (!empty($userAnswers)) {
            $isCorrect = false;
            $pointsEarned = 0;
            
            switch ($question['type']) {
                case 'single':
                    // Проверяем одиночный выбор
                    $selectedOptionId = $userAnswers[0]['answer_option_id'] ?? null;
                    if ($selectedOptionId) {
                        $option = $this->db->fetchOne(
                            "SELECT is_correct FROM answer_options WHERE id = ?",
                            [$selectedOptionId]
                        );
                        if ($option) {
                            $isCorrect = (bool)$option['is_correct'];
                            $pointsEarned = $isCorrect ? (float)$question['points'] : 0;
                        }
                    }
                    break;
                    
                case 'multiple':
                    // Проверяем множественный выбор
                    // Получаем выбранные варианты
                    $selectedIds = [];
                    foreach ($userAnswers as $ua) {
                        if ($ua['answer_option_id']) {
                            $selectedIds[] = (int)$ua['answer_option_id'];
                        }
                    }
                    
                    // Получаем все правильные варианты
                    $correctOptions = $this->db->fetchAll(
                        "SELECT id FROM answer_options WHERE question_id = ? AND is_correct = 1",
                        [$question['id']]
                    );
                    $correctIds = array_column($correctOptions, 'id');
                    
                    // Сортируем для сравнения
                    sort($selectedIds);
                    sort($correctIds);
                    
                    // Проверяем полное совпадение
                    $isCorrect = ($selectedIds == $correctIds && !empty($correctIds));
                    $pointsEarned = $isCorrect ? (float)$question['points'] : 0;
                    break;
                    
                case 'text':
                case 'number':
                    // Для текстового и числового ответа - проверка вручную
                    $isCorrect = false;
                    $pointsEarned = 0;
                    break;
            }
            
            $questionResult['is_correct'] = $isCorrect;
            $questionResult['points_earned'] = $pointsEarned;
            $totalScore += $pointsEarned;
            if ($isCorrect) $correctCount++;
        }
        
        $results[] = $questionResult;
    }
    
    // Обновляем сессию
    $this->db->query(
        "UPDATE test_sessions 
         SET status = 'completed', 
             completed_at = NOW(), 
             total_score = ?,
             max_possible_score = ?
         WHERE id = ?",
        [$totalScore, $maxScore, $session['id']]
    );
    
    // Возвращаем результаты
    $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
    $passingScore = $session['test_settings']['passing_score'] ?? 0;
    
    return [
        'session' => $session,
        'results' => $results,
        'total_score' => $totalScore,
        'max_possible_score' => $maxScore,
        'correct_count' => $correctCount,
        'total_count' => $totalCount,
        'percentage' => $percentage,
        'passed' => $maxScore > 0 && $percentage >= $passingScore
    ];
}
    
    /**
     * Получение прогресса прохождения
     * 
     * @param string $token Токен сессии
     * @return array
     */
    public function getProgress($token)
    {
        $session = $this->getSession($token);
        if (!$session) {
            return ['total' => 0, 'answered' => 0, 'percentage' => 0];
        }
        
        $questions = $this->getTestQuestions($session['test_id']);
        
        // Получаем количество уникальных вопросов, на которые даны ответы
        $answersResult = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT question_id) as count FROM user_answers WHERE session_id = ?",
            [$session['id']]
        );
        
        $total = count($questions);
        $answered = $answersResult ? (int)$answersResult['count'] : 0;
        
        return [
            'total' => $total,
            'answered' => $answered,
            'percentage' => $total > 0 ? round(($answered / $total) * 100, 2) : 0
        ];
    }
}