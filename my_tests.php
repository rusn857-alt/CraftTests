<?php
// my_tests.php - только свои тесты, без переключения

require_once __DIR__ . '/lib/Utils.php';
require_once __DIR__ . '/lib/TestStorage.php';
require_once __DIR__ . '/lib/AccessStorage.php';
require_once __DIR__ . '/lib/BitrixUserApi.php';

$config = require __DIR__ . '/config.php';

// Инициализация BitrixApi ДО использования
$bitrixApi = new BitrixUserApi(
    $config['bitrix_webhook'], 
    $config['paths']['cache'] ?? __DIR__ . '/cache'
);

$storage = new TestStorage($config['data_dir']);
$accessStorage = new AccessStorage($config['data_dir'], $bitrixApi);

// Функция для получения текущего пользователя
function getCurrentUserId($bitrixApi) {
    // 1. Проверяем через GET параметр (только если передан явно и есть права)
    if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
        // Проверяем, что пользователь имеет права на просмотр этого ID
        if (isAdmin()) {
            return $_GET['user_id'];
        }
    }
    
    // 2. Проверяем через сессию
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['current_user_id']) && !empty($_SESSION['current_user_id'])) {
        return $_SESSION['current_user_id'];
    }
    
    // 3. Проверяем через API Битрикс
    try {
        $response = $bitrixApi->request('user.current', []);
        if (!empty($response['result']) && isset($response['result']['ID'])) {
            return $response['result']['ID'];
        }
    } catch (Exception $e) {
        // Игнорируем ошибки
    }
    
    // 4. Проверяем через cookie
    if (isset($_COOKIE['current_user_id']) && !empty($_COOKIE['current_user_id'])) {
        return $_COOKIE['current_user_id'];
    }
    
    return null;
}

// Функция проверки администратора
function isAdmin() {
    // Простая проверка - можно расширить
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

// Получаем ID пользователя
$userId = getCurrentUserId($bitrixApi);

// Если пользователь не определен, пробуем получить через API
if (empty($userId)) {
    try {
        $response = $bitrixApi->request('user.current', []);
        if (!empty($response['result']) && isset($response['result']['ID'])) {
            $userId = $response['result']['ID'];
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['current_user_id'] = $userId;
        }
    } catch (Exception $e) {
        // Игнорируем
    }
}

// Получаем все тесты
$allTests = $storage->getAllTests();

// Получаем доступные тесты для пользователя
$availableTests = [];
if (!empty($userId)) {
    $availableTests = $accessStorage->getAvailableTests($userId, $allTests);
}

// Получаем результаты пользователя
$userResults = [];
foreach ($availableTests as $id => $test) {
    $results = $accessStorage->getUserTestResults($userId, $id);
    if (!empty($results)) {
        $userResults[$id] = $results;
    }
}

// Получаем информацию о пользователе
$userInfo = null;
if (!empty($userId)) {
    $structure = $bitrixApi->getCompanyStructure();
    foreach ($structure['users'] ?? [] as $user) {
        if ($user['id'] == $userId) {
            $userInfo = $user;
            break;
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои тесты</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f0f2f5; 
            margin: 0; 
            padding: 20px; 
            color: #333; 
        }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .header h1 { margin: 0; font-size: 1.5em; }
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn-back { color: white; text-decoration: none; opacity: 0.8; }
        .btn-back:hover { opacity: 1; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #219a52; }
        .btn-outline { background: transparent; border: 2px solid rgba(255,255,255,0.5); color: white; }
        .btn-outline:hover { background: rgba(255,255,255,0.1); }
        .btn-sm { padding: 4px 10px; font-size: 0.85em; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-warning:hover { background: #e67e22; }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .card-title {
            font-size: 1.1em;
            font-weight: 600;
            margin: 0 0 15px 0;
            color: #2c3e50;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 10px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .user-info .avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8em;
            font-weight: bold;
            flex-shrink: 0;
        }
        .user-info .name {
            font-size: 1.2em;
            font-weight: 600;
            color: #2c3e50;
        }
        .user-info .id {
            color: #888;
            font-size: 0.9em;
        }
        .user-info .position {
            color: #666;
            font-size: 0.95em;
        }
        
        .tests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .test-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            border-top: 4px solid #3498db;
            position: relative;
        }
        .test-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }
        .test-card .title {
            font-size: 1.1em;
            font-weight: 600;
            margin: 0 0 8px 0;
            color: #2c3e50;
            padding-right: 80px;
        }
        .test-card .description {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .test-card .result-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 10px 0;
            flex-wrap: wrap;
            gap: 8px;
        }
        .test-card .result-info .score {
            font-size: 1.3em;
            font-weight: bold;
            color: #2c3e50;
        }
        .test-card .result-info .score.passed { color: #27ae60; }
        .test-card .result-info .score.failed { color: #e74c3c; }
        .test-card .result-info .attempts {
            color: #888;
            font-size: 0.85em;
        }
        .test-card .actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        .test-card .status-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
        }
        .status-pass { background: #d4edda; color: #155724; }
        .status-fail { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-new { background: #e8f0fe; color: #3498db; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state .icon { font-size: 4em; margin-bottom: 20px; }
        .empty-state h2 { color: #555; margin-bottom: 10px; }
        
        .badge-status {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
        }
        .badge-pass { background: #d4edda; color: #155724; }
        .badge-fail { background: #f8d7da; color: #721c24; }
        .badge-pending { background: #fff3cd; color: #856404; }

        @media (max-width: 600px) {
            .header { flex-direction: column; text-align: center; gap: 10px; }
            .user-info { flex-direction: column; text-align: center; }
            .tests-grid { grid-template-columns: 1fr; }
            .test-card .title { padding-right: 0; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📋 Мои тесты</h1>
        <div class="header-actions">
            <a href="index.php" class="btn-back">← К тестам</a>
            <?php if (!empty($userId)): ?>
                <span style="color: rgba(255,255,255,0.8); font-size: 0.9em;">
                    👤 ID: <?= htmlspecialchars($userId) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (empty($userId)): ?>
        <!-- Если пользователь не определен -->
        <div class="card">
            <div class="empty-state">
                <div class="icon">👤</div>
                <h2>Пользователь не определен</h2>
                <p>Не удалось определить текущего пользователя. Пожалуйста, авторизуйтесь в системе.</p>
            </div>
        </div>
    <?php elseif (empty($availableTests)): ?>
        <!-- Нет доступных тестов -->
        <div class="card">
            <div class="user-info">
                <div class="avatar"><?= mb_substr($userInfo['name'] ?? $userId, 0, 1) ?></div>
                <div>
                    <div class="name"><?= htmlspecialchars($userInfo['name'] ?? $userId) ?></div>
                    <?php if (!empty($userInfo['position'])): ?>
                        <div class="position"><?= htmlspecialchars($userInfo['position']) ?></div>
                    <?php endif; ?>
                    <div class="id">ID: <?= htmlspecialchars($userId) ?></div>
                </div>
                <div style="margin-left: auto; color: #888;">
                    Доступно тестов: <strong>0</strong>
                </div>
            </div>
            
            <div class="empty-state">
                <div class="icon">📭</div>
                <h2>Нет доступных тестов</h2>
                <p>Для вас пока не назначены тесты. Обратитесь к администратору.</p>
            </div>
        </div>
    <?php else: ?>
        <!-- Отображение доступных тестов -->
        <div class="card">
            <div class="user-info">
                <div class="avatar"><?= mb_substr($userInfo['name'] ?? $userId, 0, 1) ?></div>
                <div>
                    <div class="name"><?= htmlspecialchars($userInfo['name'] ?? $userId) ?></div>
                    <?php if (!empty($userInfo['position'])): ?>
                        <div class="position"><?= htmlspecialchars($userInfo['position']) ?></div>
                    <?php endif; ?>
                    <div class="id">ID: <?= htmlspecialchars($userId) ?></div>
                </div>
                <div style="margin-left: auto; color: #888;">
                    Доступно тестов: <strong><?= count($availableTests) ?></strong>
                </div>
            </div>
        </div>
        
        <div class="tests-grid">
            <?php foreach ($availableTests as $id => $test):
                $results = $userResults[$id] ?? [];
                $lastResult = !empty($results) ? $results[0] : null;
                $attempts = count($results);
                $lastScore = $lastResult ? ($lastResult['score'] ?? 0) : 0;
                $maxScore = $lastResult ? ($lastResult['max_score'] ?? 0) : 0;
                $status = $lastResult ? ($lastResult['status'] ?? 'pending') : 'new';
                
                $statusLabels = [
                    'passed' => ['label' => '✅ Пройден', 'class' => 'status-pass'],
                    'failed' => ['label' => '❌ Провален', 'class' => 'status-fail'],
                    'pending' => ['label' => '⏳ Не пройден', 'class' => 'status-pending'],
                    'new' => ['label' => '🆕 Новый', 'class' => 'status-new']
                ];
                $statusInfo = $statusLabels[$status] ?? $statusLabels['new'];
            ?>
                <div class="test-card">
                    <span class="status-badge <?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
                    
                    <h3 class="title"><?= htmlspecialchars($test['title'] ?? 'Без названия') ?></h3>
                    <p class="description"><?= nl2br(htmlspecialchars($test['description'] ?? '')) ?></p>
                    
                    <?php if ($lastResult): ?>
                        <div class="result-info">
                            <div>
                                <div class="score <?= $status === 'passed' ? 'passed' : ($status === 'failed' ? 'failed' : '') ?>">
                                    <?= $lastScore ?> / <?= $maxScore ?>
                                </div>
                                <div style="font-size: 0.85em; color: #888;">
                                    🕐 <?= Utils::formatDate($lastResult['created_at'] ?? '') ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div class="attempts">Попыток: <?= $attempts ?></div>
                                <?php if ($attempts > 1): ?>
                                    <div style="font-size: 0.8em; color: #888;">
                                        Лучший: <?= $lastScore ?> / <?= $maxScore ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="result-info" style="background: #f8f9fa;">
                            <span style="color: #888;">🆕 Еще не пройден</span>
                            <span class="badge-status badge-pending">Ожидает</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="actions">
                        <a href="take_test.php?id=<?= urlencode($id) ?>&employee_id=<?= urlencode($userId) ?>" 
                           class="btn btn-success btn-sm">▶ Пройти тест</a>
                        <?php if ($lastResult): ?>
                            <a href="result.php?id=<?= urlencode($lastResult['id'] ?? '') ?>" 
                               class="btn btn-primary btn-sm">📊 Результат</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Автоматическое обновление при загрузке
document.addEventListener('DOMContentLoaded', function() {
    // Если пользователь не определен, пробуем получить через Битрикс
    <?php if (empty($userId)): ?>
        if (typeof BX24 !== 'undefined' && BX24.getCurrentUser) {
            BX24.getCurrentUser(function(user) {
                if (user && user.ID) {
                    window.location.href = '?user_id=' + user.ID;
                }
            });
        }
    <?php endif; ?>
});
</script>
</body>
</html>