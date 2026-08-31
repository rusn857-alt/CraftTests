<?php
// lib/AccessStorage.php

class AccessStorage {
    private $dataDir;
    private $accessFile;
    private $resultsFile;
    
    public function __construct(string $dataDir) {
        $this->dataDir = $dataDir;
        $this->accessFile = $dataDir . '/access_rules.json';
        $this->resultsFile = $dataDir . '/test_results.json';
        
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        if (!file_exists($this->accessFile)) {
            file_put_contents($this->accessFile, json_encode([]));
        }
    }
    
    /**
     * Получить все правила доступа
     */
    public function getAllRules(): array {
        $content = file_get_contents($this->accessFile);
        return json_decode($content, true) ?: [];
    }
    
    /**
     * Сохранить правило доступа
     */
    public function saveRule(array $rule): bool {
        $rules = $this->getAllRules();
        $rule['id'] = $rule['id'] ?? uniqid('rule_');
        $rule['created_at'] = $rule['created_at'] ?? date('Y-m-d H:i:s');
        $rules[] = $rule;
        return file_put_contents($this->accessFile, json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
    
    /**
     * Удалить правило доступа
     */
    public function deleteRule(string $id): bool {
        $rules = $this->getAllRules();
        $rules = array_filter($rules, function($r) use ($id) {
            return ($r['id'] ?? '') !== $id;
        });
        return file_put_contents($this->accessFile, json_encode(array_values($rules), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
    
    /**
     * Получить доступные тесты для пользователя
     */
    public function getAvailableTests(string $userId, array $allTests): array {
        $rules = $this->getAllRules();
        $availableTestIds = [];
        
        foreach ($rules as $rule) {
            $targets = $rule['targets'] ?? [];
            $testIds = $rule['test_ids'] ?? [];
            
            // Проверяем, есть ли пользователь в правилах
            foreach ($targets as $target) {
                if ($this->isUserTarget($userId, $target)) {
                    $availableTestIds = array_merge($availableTestIds, $testIds);
                    break;
                }
            }
        }
        
        // Уникальные ID тестов
        $availableTestIds = array_unique($availableTestIds);
        
        // Фильтруем тесты
        $availableTests = [];
        foreach ($allTests as $id => $test) {
            if (in_array($id, $availableTestIds)) {
                $availableTests[$id] = $test;
            }
        }
        
        return $availableTests;
    }
    
    /**
     * Проверяет, относится ли пользователь к цели
     */
    private function isUserTarget(string $userId, array $target): bool {
        $type = $target['type'] ?? 'user';
        $targetId = $target['id'] ?? '';
        
        if ($type === 'user') {
            return $userId === $targetId;
        }
        
        if ($type === 'department') {
            // Для отделов нужно проверить через API Битрикс
            // Пока возвращаем true для тестирования
            return true;
        }
        
        return false;
    }
    
    /**
     * Получить результаты пользователя по тесту
     */
    public function getUserTestResults(string $userId, string $testId): array {
        $content = file_get_contents($this->resultsFile);
        $results = json_decode($content, true) ?: [];
        
        $filtered = array_filter($results, function($r) use ($userId, $testId) {
            return ($r['employee_id'] ?? '') === $userId && 
                   ($r['test_id'] ?? '') === $testId;
        });
        
        return array_values($filtered);
    }
}