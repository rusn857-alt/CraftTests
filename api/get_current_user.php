<?php
// api/get_current_user.php - получение текущего пользователя

require_once __DIR__ . '/../lib/BitrixUserApi.php';

$config = require __DIR__ . '/../config.php';
$bitrixApi = new BitrixUserApi($config['bitrix_webhook'], $config['paths']['cache'] ?? __DIR__ . '/../cache');

header('Content-Type: application/json');

// Получаем текущего пользователя через API
$response = $bitrixApi->request('user.current', []);

if (!empty($response['result'])) {
    $user = $response['result'];
    echo json_encode([
        'id' => $user['ID'],
        'name' => trim(($user['LAST_NAME'] ?? '') . ' ' . ($user['NAME'] ?? '')),
        'email' => $user['EMAIL'] ?? '',
        'position' => $user['WORK_POSITION'] ?? ''
    ]);
} else {
    echo json_encode(['error' => 'User not found']);
}