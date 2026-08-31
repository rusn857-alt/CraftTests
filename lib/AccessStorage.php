<?php
// lib/AccessStorage.php - с методом deleteRule

class AccessStorage {
    private $dataDir;
    private $accessFile;
    private $resultsFile;
    private $bitrixApi;
    
    public function __construct(string $dataDir, $bitrixApi = null) {
        $this->dataDir = $dataDir;
        $this->accessFile = $dataDir . '/access_rules.json';
        $this->resultsFile = $dataDir . '/test_results.json';
        $this->bitrixApi = $bitrixApi;
        
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        if (!file_exists($this->accessFile)) {
            file_put_contents($this->accessFile, json_encode([]));
        }
        if (!file_exists($this->resultsFile)) {
            file_put_contents($this->resultsFile, json_encode([]));
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
     * Удалить правило доступа по ID
     */
    public function deleteRule(string $id): bool {
        $rules = $this->getAllRules();
        $newRules = [];
        $found = false;
        
        foreach ($rules as $rule) {
            $ruleId = $rule['id'] ?? '';
            if ($ruleId === $id) {
                $found = true;
                continue; // Пропускаем удаляемое правило
            }
            $newRules[] = $rule;
        }
        
        if (!$found) {
            return false; // Правило не найдено
        }
        
        return file_put_contents($this->accessFile, json_encode(array_values($newRules), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
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
            
            // Проверяем каждый целевой объект
            foreach ($targets as $target) {
                if ($this->isUserTarget($userId, $target)) {
                    // Добавляем все тесты из этого правила
                    foreach ($testIds as $testId) {
                        if (!in_array($testId, $availableTestIds)) {
                            $availableTestIds[] = $testId;
                        }
                    }
                    break; // Если пользователь найден в одном из target, выходим
                }
            }
        }
        
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
        
        // Прямое совпадение ID пользователя
        if ($type === 'user') {
            return $userId === $targetId;
        }
        
        // Проверка принадлежности к отделу
        if ($type === 'department') {
            return $this->isUserInDepartment($userId, $targetId);
        }
        
        return false;
    }
    
    /**
     * Проверяет, состоит ли пользователь в отделе
     */
    private function isUserInDepartment(string $userId, string $departmentId): bool {
        // Если нет API, возвращаем false
        if (!$this->bitrixApi) {
            return false;
        }
        
        try {
            // Получаем структуру компании
            $structure = $this->bitrixApi->getCompanyStructure();
            
            // Ищем пользователя
            foreach ($structure['users'] ?? [] as $user) {
                if ($user['id'] == $userId) {
                    // Проверяем, что отдел пользователя совпадает с целевым
                    return $user['department_id'] == $departmentId;
                }
            }
            
            // Если пользователь не найден в кэше, пробуем через API
            $userInfo = $this->bitrixApi->getUserInfo($userId);
            if ($userInfo) {
                return $userInfo['department_id'] == $departmentId;
            }
            
        } catch (Exception $e) {
            error_log("Ошибка проверки отдела: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Получить результаты пользователя по тесту
     */
    public function getUserTestResults(string $userId, string $testId): array {
        if (!file_exists($this->resultsFile)) {
            return [];
        }
        
        $content = file_get_contents($this->resultsFile);
        $results = json_decode($content, true) ?: [];
        
        $filtered = array_filter($results, function($r) use ($userId, $testId) {
            return ($r['employee_id'] ?? '') === $userId && 
                   ($r['test_id'] ?? '') === $testId;
        });
        
        // Сортируем по убыванию даты (сначала новые)
        usort($filtered, function($a, $b) {
            return strtotime($b['created_at'] ?? '') - strtotime($a['created_at'] ?? '');
        });
        
        return array_values($filtered);
    }
    
    /**
     * Получить правила для отображения в админке
     */
    public function getRulesForDisplay(): array {
        $rules = $this->getAllRules();
        $result = [];
        
        foreach ($rules as $rule) {
            $targets = $rule['targets'] ?? [];
            foreach ($targets as $target) {
                $key = $target['type'] . '_' . $target['id'];
                if (!isset($result[$key])) {
                    $result[$key] = [
                        'target' => $target,
                        'tests' => [],
                        'rule_ids' => []
                    ];
                }
                $result[$key]['tests'] = array_merge($result[$key]['tests'], $rule['test_ids'] ?? []);
                $result[$key]['rule_ids'][] = $rule['id'] ?? '';
            }
        }
        
        // Делаем тесты уникальными
        foreach ($result as &$item) {
            $item['tests'] = array_unique($item['tests']);
        }
        
        return $result;
    }
}