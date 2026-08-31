<?php
// access_management.php - упрощенная версия с поиском

require_once __DIR__ . '/lib/Utils.php';
require_once __DIR__ . '/lib/TestStorage.php';
require_once __DIR__ . '/lib/AccessStorage.php';
require_once __DIR__ . '/lib/BitrixUserApi.php';

$config = require __DIR__ . '/config.php';
$storage = new TestStorage($config['data_dir']);
$accessStorage = new AccessStorage($config['data_dir']);
$bitrixApi = new BitrixUserApi($config['bitrix_webhook'], $config['paths']['cache'] ?? __DIR__ . '/cache');

$tests = $storage->getAllTests();
$rules = $accessStorage->getAllRules();
$structure = $bitrixApi->getCompanyStructure();

// Получаем плоский список всех сотрудников и отделов для быстрого поиска
$allItems = [];

// Добавляем отделы
foreach ($structure['departments'] ?? [] as $dept) {
    $allItems[] = [
        'id' => $dept['id'],
        'name' => $dept['name'],
        'type' => 'department',
        'type_label' => 'Отдел',
        'icon' => '🏢',
        'search_text' => mb_strtolower($dept['name'])
    ];
}

// Добавляем сотрудников
foreach ($structure['users'] ?? [] as $user) {
    $allItems[] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'type' => 'user',
        'type_label' => 'Сотрудник',
        'icon' => '👤',
        'position' => $user['position'] ?? '',
        'department_id' => $user['department_id'] ?? '',
        'search_text' => mb_strtolower($user['name'] . ' ' . ($user['position'] ?? ''))
    ];
}

$error = '';
$success = '';

// Обработка добавления правила
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_rule') {
        $targetType = $_POST['target_type'] ?? 'user';
        $targetId = $_POST['target_id'] ?? '';
        $targetName = $_POST['target_name'] ?? '';
        $testIds = $_POST['test_ids'] ?? [];
        
        if (empty($targetId)) {
            $error = 'Выберите сотрудника или отдел';
        } elseif (empty($testIds)) {
            $error = 'Выберите хотя бы один тест';
        } else {
            $rule = [
                'targets' => [
                    [
                        'type' => $targetType,
                        'id' => $targetId,
                        'name' => $targetName
                    ]
                ],
                'test_ids' => $testIds,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if ($accessStorage->saveRule($rule)) {
                $success = 'Правило доступа добавлено!';
                header('Location: access_management.php?added=1');
                exit;
            } else {
                $error = 'Ошибка сохранения правила';
            }
        }
    } elseif ($action === 'delete_rule') {
        $ruleId = $_POST['rule_id'] ?? '';
        if ($accessStorage->deleteRule($ruleId)) {
            header('Location: access_management.php?deleted=1');
            exit;
        }
    } elseif ($action === 'clear_cache') {
        $bitrixApi->clearCache();
        header('Location: access_management.php?cache=1');
        exit;
    }
}

// Группировка правил по цели
$rulesByTarget = [];
foreach ($rules as $rule) {
    $targets = $rule['targets'] ?? [];
    foreach ($targets as $target) {
        $key = $target['type'] . '_' . $target['id'];
        if (!isset($rulesByTarget[$key])) {
            $rulesByTarget[$key] = [
                'target' => $target,
                'tests' => []
            ];
        }
        foreach ($rule['test_ids'] ?? [] as $testId) {
            if (!in_array($testId, $rulesByTarget[$key]['tests'])) {
                $rulesByTarget[$key]['tests'][] = $testId;
            }
        }
    }
}

// Преобразуем данные для JavaScript
$allItemsJson = json_encode($allItems, JSON_UNESCAPED_UNICODE);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление доступом</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f0f2f5; 
            margin: 0; 
            padding: 20px; 
            color: #333; 
        }
        .container { max-width: 1400px; margin: 0 auto; }
        
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
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
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
        
        .form-group { margin-bottom: 15px; }
        .form-group label { 
            display: block; 
            font-weight: 500; 
            margin-bottom: 5px; 
            color: #555; 
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-control:focus { border-color: #3498db; outline: none; }
        select[multiple] { min-height: 120px; }
        
        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        
        .rules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .rule-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px 20px;
            border-left: 4px solid #3498db;
        }
        .rule-card .target {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .rule-card .target-type {
            font-size: 0.8em;
            color: #888;
            text-transform: uppercase;
        }
        .rule-card .tests-list {
            margin: 10px 0;
            padding-left: 20px;
        }
        .rule-card .tests-list li {
            color: #555;
            margin-bottom: 3px;
        }
        .rule-card .actions {
            margin-top: 10px;
            display: flex;
            gap: 8px;
        }
        
        .split-view {
            display: flex;
            gap: 20px;
        }
        .left-panel { flex: 1; }
        .right-panel { flex: 1; }
        
        /* Упрощенный поиск */
        .search-wrapper {
            position: relative;
        }
        .search-wrapper input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .search-wrapper input:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 300px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
            z-index: 100;
        }
        .search-results.active {
            display: block;
        }
        .search-results .result-item {
            padding: 8px 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        .search-results .result-item:hover {
            background: #e8f0fe;
        }
        .search-results .result-item .icon {
            font-size: 1.1em;
        }
        .search-results .result-item .name {
            flex: 1;
        }
        .search-results .result-item .type {
            font-size: 0.8em;
            color: #888;
        }
        .search-results .result-item .position {
            font-size: 0.8em;
            color: #888;
        }
        .search-results .result-item.selected {
            background: #d4edda;
        }
        .search-results .no-results {
            padding: 20px;
            text-align: center;
            color: #999;
        }
        
        .selected-target {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            background: #e8f0fe;
            border-radius: 8px;
            margin: 10px 0;
        }
        .selected-target .remove-target {
            cursor: pointer;
            color: #e74c3c;
            font-weight: bold;
            background: none;
            border: none;
            font-size: 1.2em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }
        .stats-grid .stat-item {
            text-align: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .stats-grid .stat-item .num {
            font-size: 1.8em;
            font-weight: bold;
            color: #2c3e50;
        }
        .stats-grid .stat-item .label {
            font-size: 0.8em;
            color: #888;
        }

        .loading-indicator {
            display: none;
            text-align: center;
            padding: 10px;
            color: #888;
        }
        .loading-indicator.active {
            display: block;
        }
        .loading-indicator .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #e8f0fe;
            border-top-color: #3498db;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 900px) {
            .split-view { flex-direction: column; }
        }
        @media (max-width: 600px) {
            .header { flex-direction: column; text-align: center; gap: 10px; }
            .rules-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔐 Управление доступом к тестам</h1>
        <div class="header-actions">
            <a href="index.php" class="btn-back">← К тестам</a>
            <a href="my_tests.php" class="btn btn-success">📋 Мои тесты</a>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="clear_cache">
                <button class="btn btn-warning" onclick="return confirm('Обновить структуру компании из Битрикс?')">
                    🔄 Обновить структуру
                </button>
            </form>
        </div>
    </div>
    
    <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success">✅ Правило доступа добавлено!</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">✅ Правило доступа удалено!</div>
    <?php endif; ?>
    <?php if (isset($_GET['cache'])): ?>
        <div class="alert alert-info">🔄 Структура компании обновлена!</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <!-- Статистика -->
    <div class="card" style="padding: 15px;">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="num"><?= count($structure['departments'] ?? []) ?></div>
                <div class="label">🏢 Отделов</div>
            </div>
            <div class="stat-item">
                <div class="num"><?= count($structure['users'] ?? []) ?></div>
                <div class="label">👤 Сотрудников</div>
            </div>
            <div class="stat-item">
                <div class="num"><?= count($rules) ?></div>
                <div class="label">📋 Правил</div>
            </div>
            <div class="stat-item">
                <div class="num"><?= count($tests) ?></div>
                <div class="label">📝 Тестов</div>
            </div>
        </div>
    </div>
    
    <div class="split-view">
        <!-- ЛЕВАЯ ПАНЕЛЬ: Добавление правила -->
        <div class="left-panel">
            <div class="card">
                <h3 class="card-title">➕ Добавить правило доступа</h3>
                
                <form method="POST" id="accessForm">
                    <input type="hidden" name="action" value="add_rule">
                    <input type="hidden" name="target_type" id="targetType" value="user">
                    <input type="hidden" name="target_id" id="targetId">
                    <input type="hidden" name="target_name" id="targetName">
                    
                    <div class="form-group">
                        <label>Выберите сотрудника или отдел:</label>
                        <div class="search-wrapper">
                            <input type="text" class="form-control" id="searchInput" 
                                   placeholder="🔍 Введите имя сотрудника или название отдела..." 
                                   autocomplete="off">
                            <div class="search-results" id="searchResults"></div>
                            <div class="loading-indicator" id="loadingIndicator">
                                <span class="spinner"></span> Поиск...
                            </div>
                        </div>
                    </div>
                    
                    <div id="selectedDisplay" class="selected-target" style="display:none;">
                        <span>✅ Выбран: <strong id="selectedLabel"></strong></span>
                        <button type="button" class="remove-target" onclick="clearSelection()">✕</button>
                    </div>
                    
                    <div class="form-group">
                        <label>Доступные тесты:</label>
                        <select name="test_ids[]" class="form-control" multiple required>
                            <?php foreach ($tests as $id => $test): ?>
                                <option value="<?= $id ?>">
                                    <?= htmlspecialchars($test['title'] ?? 'Без названия') ?>
                                    (<?= count($test['pages'] ?? []) ?> страниц)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="help-text">Удерживайте Ctrl для выбора нескольких тестов</small>
                    </div>
                    
                    <button type="submit" class="btn btn-success">✅ Добавить правило</button>
                </form>
            </div>
        </div>
        
        <!-- ПРАВАЯ ПАНЕЛЬ: Список правил -->
        <div class="right-panel">
            <div class="card">
                <h3 class="card-title">📋 Существующие правила</h3>
                
                <?php if (empty($rulesByTarget)): ?>
                    <p style="color: #999;">Нет настроенных правил доступа</p>
                <?php else: ?>
                    <div class="rules-grid">
                        <?php foreach ($rulesByTarget as $key => $data): 
                            $target = $data['target'];
                            $targetTypeLabel = $target['type'] === 'user' ? '👤 Сотрудник' : '🏢 Отдел';
                            $testNames = [];
                            foreach ($data['tests'] as $testId) {
                                if (isset($tests[$testId])) {
                                    $testNames[] = $tests[$testId]['title'] ?? 'Без названия';
                                }
                            }
                        ?>
                            <div class="rule-card">
                                <div class="target">
                                    <?= htmlspecialchars($target['name'] ?? $target['id']) ?>
                                </div>
                                <div class="target-type"><?= $targetTypeLabel ?></div>
                                
                                <div class="tests-list">
                                    <strong>Доступные тесты:</strong>
                                    <ul>
                                        <?php foreach ($testNames as $name): ?>
                                            <li><?= htmlspecialchars($name) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                
                                <div class="actions">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_rule">
                                        <input type="hidden" name="rule_id" value="<?= $key ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Удалить это правило?')">
                                            🗑 Удалить
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Все данные для поиска
const allItems = <?= $allItemsJson ?>;
let selectedItem = null;
let searchTimeout = null;

function selectItem(item) {
    selectedItem = item;
    document.getElementById('targetType').value = item.type;
    document.getElementById('targetId').value = item.id;
    document.getElementById('targetName').value = item.name;
    
    document.getElementById('selectedLabel').textContent = item.icon + ' ' + item.name;
    document.getElementById('selectedDisplay').style.display = 'flex';
    document.getElementById('searchInput').value = item.name;
    document.getElementById('searchResults').classList.remove('active');
}

function clearSelection() {
    selectedItem = null;
    document.getElementById('targetId').value = '';
    document.getElementById('targetName').value = '';
    document.getElementById('selectedDisplay').style.display = 'none';
    document.getElementById('searchInput').value = '';
    document.getElementById('searchResults').classList.remove('active');
}

function performSearch(query) {
    const results = document.getElementById('searchResults');
    const loading = document.getElementById('loadingIndicator');
    
    if (!query || query.length < 2) {
        results.classList.remove('active');
        return;
    }
    
    loading.classList.add('active');
    
    // Используем setTimeout для плавности
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const lowerQuery = query.toLowerCase().trim();
        const found = [];
        
        // Ищем совпадения
        for (const item of allItems) {
            if (item.search_text.includes(lowerQuery) || 
                item.name.toLowerCase().includes(lowerQuery)) {
                found.push(item);
            }
            if (found.length >= 20) break; // Ограничиваем результат
        }
        
        loading.classList.remove('active');
        
        if (found.length === 0) {
            results.innerHTML = '<div class="no-results">😕 Ничего не найдено</div>';
        } else {
            let html = '';
            for (const item of found) {
                const positionText = item.position ? ` (${item.position})` : '';
                html += `
                    <div class="result-item" onclick="selectItem(${JSON.stringify(item).replace(/"/g, '&quot;')})">
                        <span class="icon">${item.icon}</span>
                        <span class="name">${item.name}</span>
                        <span class="position">${positionText}</span>
                        <span class="type">${item.type_label}</span>
                    </div>
                `;
            }
            results.innerHTML = html;
        }
        
        results.classList.add('active');
    }, 200);
}

// Поиск при вводе
document.getElementById('searchInput').addEventListener('input', function() {
    performSearch(this.value);
});

// Закрытие результатов при клике вне
document.addEventListener('click', function(e) {
    const wrapper = document.querySelector('.search-wrapper');
    if (!wrapper.contains(e.target)) {
        document.getElementById('searchResults').classList.remove('active');
    }
});

// Обработка Enter
document.getElementById('searchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const firstResult = document.querySelector('.result-item');
        if (firstResult) {
            firstResult.click();
        }
    }
});

// Предотвращаем зависание при большом количестве данных
// Используем debounce для поиска
let searchDebounce = null;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        performSearch(this.value);
    }, 300);
});
</script>
</body>
</html>