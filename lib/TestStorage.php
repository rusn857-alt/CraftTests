<?php
// lib/TestStorage.php - с поддержкой страниц

class TestStorage {
    private $dataDir;
    private $testsFile;
    private $resultsFile;
    
    public function __construct(string $dataDir) {
        $this->dataDir = $dataDir;
        $this->testsFile = $dataDir . '/tests.json';
        $this->resultsFile = $dataDir . '/test_results.json';
        
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        if (!file_exists($this->testsFile)) {
            file_put_contents($this->testsFile, json_encode([]));
        }
        if (!file_exists($this->resultsFile)) {
            file_put_contents($this->resultsFile, json_encode([]));
        }
    }
    
    public function getAllTests(): array {
        $content = file_get_contents($this->testsFile);
        return json_decode($content, true) ?: [];
    }
    
    public function getTest(string $id): ?array {
        $tests = $this->getAllTests();
        return $tests[$id] ?? null;
    }
    
    public function saveTest(array $testData): bool {
        $tests = $this->getAllTests();
        $id = $testData['id'] ?? Utils::generateId();
        $testData['id'] = $id;
        
        $testData['updated_at'] = date('Y-m-d H:i:s');
        if (!isset($testData['created_at'])) {
            $testData['created_at'] = date('Y-m-d H:i:s');
        }
        
        if (!isset($testData['pages']) || !is_array($testData['pages'])) {
            // Конвертируем старый формат с вопросами в новый с страницами
            if (isset($testData['questions']) && is_array($testData['questions'])) {
                $testData['pages'] = [
                    [
                        'id' => 'page_1',
                        'title' => 'Страница 1',
                        'questions' => $testData['questions']
                    ]
                ];
                unset($testData['questions']);
            } else {
                $testData['pages'] = [
                    [
                        'id' => 'page_1',
                        'title' => 'Страница 1',
                        'questions' => []
                    ]
                ];
            }
        }
        
        $tests[$id] = $testData;
        return file_put_contents($this->testsFile, json_encode($tests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
    
    public function deleteTest(string $id): bool {
        $tests = $this->getAllTests();
        if (isset($tests[$id])) {
            unset($tests[$id]);
            return file_put_contents($this->testsFile, json_encode($tests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
        }
        return false;
    }
    
    public function getTestByFormId(string $formId): ?array {
        $tests = $this->getAllTests();
        foreach ($tests as $test) {
            if (($test['form_id'] ?? '') === $formId) {
                return $test;
            }
        }
        return null;
    }
    
    // ----- УПРАВЛЕНИЕ РЕЗУЛЬТАТАМИ -----
    
    public function saveResult(array $result): bool {
        $results = $this->getAllResults();
        $result['id'] = $result['id'] ?? Utils::generateId();
        $result['created_at'] = date('Y-m-d H:i:s');
        $results[] = $result;
        return file_put_contents($this->resultsFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
    
    public function getAllResults(string $testId = ''): array {
        $content = file_get_contents($this->resultsFile);
        $results = json_decode($content, true) ?: [];
        
        if ($testId) {
            $results = array_filter($results, function($r) use ($testId) {
                return ($r['test_id'] ?? '') === $testId;
            });
        }
        
        usort($results, function($a, $b) {
            return strtotime($b['created_at'] ?? '') - strtotime($a['created_at'] ?? '');
        });
        
        return array_values($results);
    }
    
    public function getResult(string $id): ?array {
        $results = $this->getAllResults();
        foreach ($results as $result) {
            if (($result['id'] ?? '') === $id) {
                return $result;
            }
        }
        return null;
    }
    
    public function getTestStats(string $testId): array {
        $results = $this->getAllResults($testId);
        $total = count($results);
        
        if ($total === 0) {
            return ['total' => 0, 'passed' => 0, 'failed' => 0, 'avg_score' => 0, 'max_score' => 0, 'pass_rate' => 0];
        }
        
        $passed = 0;
        $failed = 0;
        $scoreSum = 0;
        $maxScore = 0;
        
        foreach ($results as $r) {
            $score = intval($r['score'] ?? 0);
            $max = intval($r['max_score'] ?? 0);
            $scoreSum += $score;
            $maxScore = max($maxScore, $max);
            
            if (($r['status'] ?? '') === 'passed') {
                $passed++;
            } else {
                $failed++;
            }
        }
        
        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'avg_score' => round($scoreSum / $total, 2),
            'max_score' => $maxScore,
            'pass_rate' => $total > 0 ? round(($passed / $total) * 100, 2) : 0
        ];
    }
    
    // ----- РАСЧЕТ РЕЗУЛЬТАТОВ -----
    
    public function calculateResults(array $test, array $answers): array {
        $totalScore = 0;
        $maxScore = 0;
        $details = [];
        
        // Получаем все вопросы из всех страниц
        $allQuestions = $this->getAllQuestions($test);
        
        foreach ($allQuestions as $q) {
            $qId = $q['id'];
            $userAnswer = $answers['q_' . $qId] ?? null;
            $questionScore = 0;
            $maxQuestionScore = intval($q['points'] ?? 1);
            $maxScore += $maxQuestionScore;
            
            $correctAnswers = $this->getCorrectAnswers($q);
            $isCorrect = $this->checkAnswer($q, $userAnswer, $correctAnswers);
            
            if ($isCorrect) {
                $questionScore = $maxQuestionScore;
            }
            
            $totalScore += $questionScore;
            
            $details[] = [
                'question' => $q['text'],
                'user_answer' => $this->formatAnswer($userAnswer),
                'correct_answer' => implode(', ', $correctAnswers),
                'score' => $questionScore,
                'max_score' => $maxQuestionScore,
                'is_correct' => $isCorrect,
                'type' => $q['type'] ?? 'text'
            ];
        }
        
        return [
            'score' => $totalScore,
            'max_score' => $maxScore,
            'details' => $details,
            'percent' => $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0
        ];
    }
    
    public function getAllQuestions(array $test): array {
        $questions = [];
        $pages = $test['pages'] ?? [];
        
        foreach ($pages as $page) {
            if (isset($page['questions']) && is_array($page['questions'])) {
                $questions = array_merge($questions, $page['questions']);
            }
        }
        
        return $questions;
    }
    
    private function getCorrectAnswers(array $question): array {
        $correct = [];
        $options = $question['options'] ?? [];
        $type = $question['type'] ?? 'single';
        
        if ($type === 'text' || $type === 'textarea') {
            return [];
        }
        
        foreach ($options as $option) {
            if (is_array($option)) {
                if (!empty($option['is_correct'])) {
                    $correct[] = $option['text'] ?? '';
                }
            } elseif (is_string($option)) {
                if (isset($question['correct_options']) && in_array($option, $question['correct_options'])) {
                    $correct[] = $option;
                }
            }
        }
        
        return $correct;
    }
    
    private function checkAnswer(array $question, $userAnswer, array $correctAnswers): bool {
        $type = $question['type'] ?? 'text';
        
        if (empty($correctAnswers)) {
            if ($type === 'text' || $type === 'textarea') {
                return !empty($userAnswer);
            }
            return !empty($userAnswer);
        }
        
        if (empty($userAnswer)) {
            return false;
        }
        
        switch ($type) {
            case 'single':
                return in_array($userAnswer, $correctAnswers);
                
            case 'multiple':
                if (!is_array($userAnswer)) return false;
                $userAnswers = array_values($userAnswer);
                sort($userAnswers);
                $correctSorted = array_values($correctAnswers);
                sort($correctSorted);
                return $userAnswers == $correctSorted;
                
            case 'text':
            case 'textarea':
                foreach ($correctAnswers as $correct) {
                    if (!empty($correct) && stripos($userAnswer, $correct) !== false) {
                        return true;
                    }
                }
                return false;
                
            case 'rating':
            case 'scale':
                return in_array($userAnswer, $correctAnswers);
                
            default:
                return false;
        }
    }
    
    private function formatAnswer($answer): string {
        if ($answer === null || $answer === '') {
            return '(не указан)';
        }
        if (is_array($answer)) {
            return implode(', ', $answer);
        }
        return (string)$answer;
    }
}