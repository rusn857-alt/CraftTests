<?php
// lib/BitrixUserApi.php - полная версия

class BitrixUserApi {
    private $webhookUrl;
    private $cacheFile;
    
    public function __construct(string $webhookUrl, string $cacheDir) {
        $this->webhookUrl = rtrim($webhookUrl, '/') . '/';
        $this->cacheFile = $cacheDir . '/bitrix_structure.json';
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
    }
    
    /**
     * Выполнить запрос к API Битрикс (публичный метод)
     */
    public function request(string $method, array $params = []): array {
        $url = $this->webhookUrl . $method;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $decoded = json_decode($response, true);
        
        // Логируем ошибки
        if ($httpCode !== 200) {
            error_log("Bitrix API HTTP Error: $httpCode for method $method");
        }
        
        if (empty($decoded)) {
            error_log("Bitrix API Empty response for method $method");
            return [];
        }
        
        if (isset($decoded['error'])) {
            error_log("Bitrix API Error: " . ($decoded['error_description'] ?? $decoded['error']) . " for method $method");
        }
        
        return $decoded;
    }
    
    /**
     * Получить структуру компании (отделы и сотрудники) с полной пагинацией
     */
    public function getCompanyStructure(): array {
        // Проверяем кэш (24 часа)
        if (file_exists($this->cacheFile)) {
            $cache = json_decode(file_get_contents($this->cacheFile), true);
            if ($cache && isset($cache['expires']) && $cache['expires'] > time()) {
                return $cache['data'];
            }
        }
        
        $structure = [
            'departments' => [],
            'users' => []
        ];
        
        // Получаем все отделы с полной пагинацией
        $departments = $this->requestAll('department.get', []);
        if (!empty($departments)) {
            foreach ($departments as $dept) {
                $structure['departments'][] = [
                    'id' => (string)$dept['ID'],
                    'name' => $dept['NAME'],
                    'parent_id' => isset($dept['PARENT']) ? (string)$dept['PARENT'] : null
                ];
            }
        }
        
        // Получаем всех активных пользователей с полной пагинацией
        $users = $this->requestAll('user.get', [
            'ACTIVE' => 'Y',
            'SELECT' => ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'UF_DEPARTMENT', 'WORK_POSITION']
        ]);
        
        if (!empty($users)) {
            foreach ($users as $user) {
                $deptIds = isset($user['UF_DEPARTMENT']) ? (array)$user['UF_DEPARTMENT'] : [];
                $structure['users'][] = [
                    'id' => (string)$user['ID'],
                    'name' => trim(($user['LAST_NAME'] ?? '') . ' ' . ($user['NAME'] ?? '')),
                    'department_id' => !empty($deptIds) ? (string)$deptIds[0] : null,
                    'position' => $user['WORK_POSITION'] ?? ''
                ];
            }
        }
        
        // Логируем результат
        error_log("Bitrix: Загружено отделов: " . count($structure['departments']) . ", сотрудников: " . count($structure['users']));
        
        // Сохраняем в кэш
        file_put_contents($this->cacheFile, json_encode([
            'data' => $structure,
            'expires' => time() + 86400 // 24 часа
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $structure;
    }
    
    /**
     * Выполняет запрос с автоматической пагинацией
     */
    private function requestAll(string $method, array $params = []): array {
        $allResults = [];
        $start = 0;
        $limit = 50;
        
        do {
            $params['start'] = $start;
            $response = $this->request($method, $params);
            
            // Проверяем ошибки
            if (isset($response['error'])) {
                error_log("Bitrix API Error in requestAll: " . ($response['error_description'] ?? $response['error']));
                break;
            }
            
            if (empty($response['result'])) {
                break;
            }
            
            $allResults = array_merge($allResults, $response['result']);
            
            // Проверяем, есть ли еще данные
            $total = $response['total'] ?? 0;
            $start += $limit;
            
            // Если total известен и мы получили все
            if ($total > 0 && count($allResults) >= $total) {
                break;
            }
            
            // Если результатов меньше лимита - это последняя страница
            if (count($response['result']) < $limit) {
                break;
            }
            
            // Безопасность: предотвращаем бесконечный цикл
            if ($start > 10000) {
                break;
            }
            
        } while (true);
        
        return $allResults;
    }
    
    /**
     * Получить сотрудников отдела
     */
    public function getDepartmentUsers(string $departmentId): array {
        $users = $this->requestAll('user.get', [
            'UF_DEPARTMENT' => $departmentId,
            'ACTIVE' => 'Y'
        ]);
        
        return array_map(function($user) {
            return [
                'id' => (string)$user['ID'],
                'name' => trim(($user['LAST_NAME'] ?? '') . ' ' . ($user['NAME'] ?? '')),
                'email' => $user['EMAIL'] ?? '',
                'position' => $user['WORK_POSITION'] ?? ''
            ];
        }, $users);
    }
    
    /**
     * Получить информацию о пользователе
     */
    public function getUserInfo(string $userId): ?array {
        $response = $this->request('user.get', ['ID' => $userId]);
        if (!empty($response['result'][0])) {
            $user = $response['result'][0];
            return [
                'id' => (string)$user['ID'],
                'name' => trim(($user['LAST_NAME'] ?? '') . ' ' . ($user['NAME'] ?? '')),
                'email' => $user['EMAIL'] ?? '',
                'department_id' => isset($user['UF_DEPARTMENT'][0]) ? (string)$user['UF_DEPARTMENT'][0] : null,
                'position' => $user['WORK_POSITION'] ?? ''
            ];
        }
        return null;
    }
    
    /**
     * Очистить кэш структуры
     */
    public function clearCache(): void {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }
}