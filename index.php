<?php
// index.php - с поиском по названию теста

require_once __DIR__ . '/lib/Utils.php';
require_once __DIR__ . '/lib/TestStorage.php';

$config = require __DIR__ . '/config.php';
$storage = new TestStorage($config['data_dir']);

// Обработка удаления
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $storage->deleteTest($id);
    header('Location: index.php');
    exit;
}

$tests = $storage->getAllTests();
$appUrl = $config['app_url'] ?? '';

// --- ПОИСК ---
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

if (!empty($searchQuery)) {
    $filteredTests = [];
    $searchLower = mb_strtolower($searchQuery);
    foreach ($tests as $id => $test) {
        $title = mb_strtolower($test['title'] ?? '');
        $description = mb_strtolower($test['description'] ?? '');
        if (strpos($title, $searchLower) !== false || strpos($description, $searchLower) !== false) {
            $filteredTests[$id] = $test;
        }
    }
    $tests = $filteredTests;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Конструктор тестов</title>
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
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header h1 { 
            margin: 0; 
            font-size: 1.8em;
            flex-shrink: 0;
        }
        .header-actions { 
            display: flex; 
            gap: 10px; 
            flex-wrap: wrap;
            align-items: center;
        }
        
        /* Стили для поиска */
        .search-container {
            display: flex;
            gap: 10px;
            align-items: center;
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            padding: 4px 4px 4px 16px;
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.3s;
            min-width: 200px;
        }
        .search-container:focus-within {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.4);
        }
        .search-container .search-icon {
            color: rgba(255,255,255,0.7);
            font-size: 1.1em;
        }
        .search-container input {
            background: transparent;
            border: none;
            padding: 8px 0;
            color: white;
            font-size: 14px;
            width: 180px;
            outline: none;
        }
        .search-container input::placeholder {
            color: rgba(255,255,255,0.6);
        }
        .search-container input:focus {
            outline: none;
        }
        .search-container button {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
            white-space: nowrap;
        }
        .search-container button:hover {
            background: rgba(255,255,255,0.3);
        }
        .search-container .clear-search {
            background: none;
            color: rgba(255,255,255,0.6);
            padding: 4px 8px;
            font-size: 1.1em;
        }
        .search-container .clear-search:hover {
            color: white;
            background: none;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95em;
        }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; transform: translateY(-2px); }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #219a52; transform: translateY(-2px); }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; transform: translateY(-2px); }
        .btn-outline { background: transparent; border: 2px solid rgba(255,255,255,0.5); color: white; }
        .btn-outline:hover { background: rgba(255,255,255,0.1); border-color: white; }
        .btn-sm { padding: 6px 12px; font-size: 0.85em; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; transform: translateY(-2px); }
        
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
            position: relative;
        }
        .test-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }
        .test-card .title {
            font-size: 1.2em;
            font-weight: 600;
            margin: 0 0 8px 0;
            color: #2c3e50;
        }
        .test-card .description {
            color: #666;
            font-size: 0.95em;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .btn-warning { 
            background: #f39c12; 
            color: white; 
        }
        .btn-warning:hover { 
            background: #e67e22; 
            transform: translateY(-2px); 
        }

        .test-card .meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85em;
            color: #888;
            padding-top: 12px;
            border-top: 1px solid #eee;
        }
        .test-card .actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        .test-card .actions .btn { font-size: 0.85em; padding: 6px 12px; }
        .test-card .stats {
            display: flex;
            gap: 15px;
            font-size: 0.9em;
            color: #555;
        }
        .test-card .stats span { display: flex; align-items: center; gap: 4px; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state .icon { font-size: 4em; margin-bottom: 20px; }
        .empty-state h2 { color: #555; margin-bottom: 10px; }
        
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
        }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-draft { background: #fff3cd; color: #856404; }
        .badge-archived { background: #f8d7da; color: #721c24; }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .search-info {
            background: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .search-info .result-count {
            color: #555;
            font-size: 0.95em;
        }
        .search-info .result-count strong {
            color: #2c3e50;
        }
        .search-info .clear-link {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        .search-info .clear-link:hover {
            text-decoration: underline;
        }
        
        .highlight {
            background: #fff3cd;
            padding: 0 2px;
            border-radius: 2px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            .header h1 { text-align: center; }
            .header-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .search-container {
                width: 100%;
                padding: 4px 4px 4px 12px;
            }
            .search-container input {
                width: 100%;
                min-width: 100px;
            }
            .search-container button {
                flex-shrink: 0;
            }
            .tests-grid {
                grid-template-columns: 1fr;
            }
            .search-info {
                flex-direction: column;
                text-align: center;
            }
        }
        @media (max-width: 480px) {
            .header { padding: 15px; }
            .header h1 { font-size: 1.4em; }
            .test-card .actions .btn { 
                font-size: 0.75em; 
                padding: 4px 8px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📝 Конструктор тестов</h1>
        <div class="header-actions">
            <!-- ПОИСК -->
            <form method="GET" class="search-container" id="searchForm">
                <span class="search-icon">🔍</span>
                <input type="text" name="search" placeholder="Поиск тестов..." 
                       value="<?= htmlspecialchars($searchQuery) ?>" 
                       autocomplete="off">
                <?php if (!empty($searchQuery)): ?>
                    <a href="index.php" class="clear-search" title="Очистить поиск">✕</a>
                <?php endif; ?>
                <button type="submit">Найти</button>
            </form>
            <a href="import_test.php" class="btn btn-warning">📥 Импорт JSON</a>
            <a href="create_test.php" class="btn btn-success">➕ Создать тест</a>
            <a href="access_management.php" class="btn btn-info">🔐 Доступ</a>
            <a href="my_tests.php" class="btn btn-primary">📋 Мои тесты</a>
            <a href="results.php" class="btn btn-info">📊 Результаты</a>
            <a href="https://sporbita-developers.ru/testrus/NewTest/NEWtesting/results.php?" class="btn btn-outline">📊 Старая система</a>
        </div>
    </div>
    
    <?php if (isset($_GET['created'])): ?>
        <div class="alert-success">✅ Тест успешно создан!</div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
        <div class="alert-success">✅ Тест успешно обновлен!</div>
    <?php endif; ?>
    
    <!-- Информация о результатах поиска -->
    <?php if (!empty($searchQuery)): ?>
        <div class="search-info">
            <span class="result-count">
                Найдено <strong><?= count($tests) ?></strong> тестов по запросу 
                <strong>"<?= htmlspecialchars($searchQuery) ?>"</strong>
            </span>
            <a href="index.php" class="clear-link">✕ Очистить поиск</a>
        </div>
    <?php endif; ?>
    
    <?php if (empty($tests)): ?>
        <div class="empty-state">
            <div class="icon"><?= !empty($searchQuery) ? '🔍' : '📋' ?></div>
            <h2><?= !empty($searchQuery) ? 'Ничего не найдено' : 'Нет созданных тестов' ?></h2>
            <p>
                <?= !empty($searchQuery) 
                    ? 'Попробуйте изменить поисковый запрос' 
                    : 'Нажмите "Создать тест", чтобы начать создание нового опроса.' 
                ?>
            </p>
            <?php if (!empty($searchQuery)): ?>
                <a href="index.php" class="btn btn-primary" style="margin-top: 20px;">🔍 Показать все тесты</a>
            <?php else: ?>
                <a href="create_test.php" class="btn btn-primary" style="margin-top: 20px;">➕ Создать первый тест</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="tests-grid">
            <?php foreach ($tests as $id => $test): 
                $stats = $storage->getTestStats($id);
                $testUrl = $appUrl . '/take_test.php?id=' . urlencode($id);
                
                // Подсветка найденного текста
                $displayTitle = $test['title'] ?? 'Без названия';
                $displayDescription = $test['description'] ?? '';
                
                if (!empty($searchQuery)) {
                    $searchLower = mb_strtolower($searchQuery);
                    $titleLower = mb_strtolower($displayTitle);
                    $descLower = mb_strtolower($displayDescription);
                    
                    if (strpos($titleLower, $searchLower) !== false) {
                        $displayTitle = preg_replace('/(' . preg_quote($searchQuery, '/') . ')/iu', '<span class="highlight">$1</span>', $displayTitle);
                    }
                    if (strpos($descLower, $searchLower) !== false) {
                        $displayDescription = preg_replace('/(' . preg_quote($searchQuery, '/') . ')/iu', '<span class="highlight">$1</span>', $displayDescription);
                    }
                }
            ?>
                <div class="test-card">
                    <h3 class="title"><?= $displayTitle ?></h3>
                    <p class="description"><?= nl2br($displayDescription) ?></p>
                    
                    <div class="stats">
                        <span>📝 <?= count($test['questions'] ?? []) ?> вопросов</span>
                        <span>👤 <?= $stats['total'] ?> попыток</span>
                        <span>✅ <?= $stats['pass_rate'] ?>% успеха</span>
                    </div>
                    
                    <div class="meta">
                        <span>📅 <?= Utils::formatDate($test['updated_at'] ?? $test['created_at'] ?? '') ?></span>
                        <span class="badge badge-<?= $test['status'] ?? 'draft' ?>">
                            <?= $test['status'] ?? 'Черновик' ?>
                        </span>
                    </div>
                    
                    <div class="actions">
                        <a href="take_test.php?id=<?= urlencode($id) ?>" class="btn btn-primary btn-sm">▶ Пройти</a>
                        <a href="edit_test.php?id=<?= urlencode($id) ?>" class="btn btn-info btn-sm">✏ Редактировать</a>
                        <a href="test_stats.php?id=<?= urlencode($id) ?>" class="btn btn-success btn-sm">📊 Статистика</a>
                        <button onclick="shareTest('<?= addslashes($testUrl) ?>', '<?= addslashes($test['title'] ?? '') ?>')" class="btn btn-primary btn-sm">🔗 Поделиться</button>
                        <a href="?delete=<?= urlencode($id) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить тест?')">🗑</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function shareTest(url, title) {
    // Проверяем поддержку clipboard API
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => {
            alert('✅ Ссылка на тест "' + title + '" скопирована в буфер обмена!');
        }).catch(() => {
            fallbackCopy(url);
        });
    } else {
        fallbackCopy(url);
    }
}

function fallbackCopy(text) {
    // Альтернативный способ копирования
    if (confirm('📋 Скопируйте ссылку для отправки:\n\n' + text + '\n\nНажмите OK для копирования')) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        textarea.style.top = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            alert('✅ Ссылка скопирована в буфер обмена!');
        } catch (e) {
            alert('❌ Не удалось скопировать ссылку. Скопируйте её вручную:\n\n' + text);
        }
        document.body.removeChild(textarea);
    }
}

// Автофокус на поле поиска при нажатии Ctrl+F или Cmd+F
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.querySelector('.search-container input');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
});

// Поиск по Enter
document.getElementById('searchForm')?.addEventListener('submit', function(e) {
    const input = this.querySelector('input');
    if (!input.value.trim()) {
        e.preventDefault();
        window.location.href = 'index.php';
    }
});
</script>
</body>
</html>