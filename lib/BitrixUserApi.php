<?php
// lib/BitrixUserApi.php - упрощенная версия

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
     * Получить структуру компании с кэшированием
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
        
        // Получаем отделы
        $departments = $this->requestAll('department.get');
        if (!empty($departments)) {
            foreach ($departments as $dept) {
                $structure['departments'][] = [
                    'id' => (string)$dept['ID'],
                    'name' => $dept['NAME'],
                    'parent_id' => isset($dept['PARENT']) ? (string)$dept['PARENT'] : null
                ];
            }
        }
        
        // Получаем всех активных пользователей
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
        
        // Сохраняем кэш
        file_put_contents($this->cacheFile, json_encode([
            'data' => $structure,
            'expires' => time() + 86400
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $structure;
    }
    
    private function requestAll(string $method, array $params = []): array {
        $allResults = [];
        $start = 0;
        $limit = 50;
        
        do {
            $params['start'] = $start;
            $response = $this->request($method, $params);
            
            if (empty($response['result'])) {
                break;
            }
            
            $allResults = array_merge($allResults, $response['result']);
            
            $total = $response['total'] ?? 0;
            $start += $limit;
            
            if ($total > 0 && count($allResults) >= $total) {
                break;
            }
            if (count($response['result']) < $limit) {
                break;
            }
            if ($start > 10000) {
                break;
            }
            
        } while (true);
        
        return $allResults;
    }
    
    private function request(string $method, array $params = []): array {
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
        curl_close($ch);
        
        return json_decode($response, true) ?: [];
    }
    
    public function clearCache(): void {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }
}