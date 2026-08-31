<?php
// api/get_children.php - API для загрузки дочерних элементов

require_once __DIR__ . '/../lib/Utils.php';
require_once __DIR__ . '/../lib/BitrixUserApi.php';

$config = require __DIR__ . '/../config.php';
$bitrixApi = new BitrixUserApi($config['bitrix_webhook'], $config['paths']['cache'] ?? __DIR__ . '/../cache');

header('Content-Type: application/json');

$departmentId = $_GET['id'] ?? '';

if (empty($departmentId)) {
    echo json_encode(['error' => 'Department ID required']);
    exit;
}

// Получаем структуру
$structure = $bitrixApi->getCompanyStructure();

// Находим отдел и его детей
$result = ['children' => [], 'users' => []];

// Ищем отдел в дереве
function findDepartment($tree, $id, $level = 0) {
    foreach ($tree as $dept) {
        if ($dept['id'] === $id) {
            return ['dept' => $dept, 'level' => $level];
        }
        if (!empty($dept['children'])) {
            $found = findDepartment($dept['children'], $id, $level + 1);
            if ($found) return $found;
        }
    }
    return null;
}

$found = findDepartment($structure['departments'] ?? [], $departmentId);

if ($found) {
    $dept = $found['dept'];
    $level = $found['level'];
    
    // Добавляем детей
    foreach ($dept['children'] ?? [] as $child) {
        $result['children'][] = [
            'id' => $child['id'],
            'name' => $child['name'],
            'level' => $level + 1,
            'has_children' => !empty($child['children']),
            'user_count' => count($child['users'] ?? [])
        ];
    }
    
    // Добавляем пользователей
    foreach ($dept['users'] ?? [] as $user) {
        $result['users'][] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'position' => $user['position'] ?? ''
        ];
    }
}

echo json_encode($result);