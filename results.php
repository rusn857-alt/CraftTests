<?php
// results.php - Исправленная версия с правильной версткой

require_once __DIR__ . '/lib/Utils.php';
require_once __DIR__ . '/lib/TestStorage.php';

$config = require __DIR__ . '/config.php';
$storage = new TestStorage($config['data_dir']);

// --- НАСТРОЙКИ АДМИНКИ ---
$adminLogin = 'admin';
$adminPass = 'admin8800';

// --- ЛОГИКА ЭКСПОРТА В EXCEL ---
if (isset($_POST['export_excel']) && isset($_POST['selected_ids'])) {
    $selectedIds = $_POST['selected_ids'];
    if (!empty($selectedIds)) {
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=test_results_" . date('Y-m-d_H-i-s') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
        echo '<body>';
        
        echo '<style>';
        echo '.main-table th, .main-table td { border: 1px solid #000; padding: 5px; text-align: left; vertical-align: top; }';
        echo '.main-table th { background-color: #cccccc; font-weight: bold; }';
        echo '.answers-table { width: 100%; border-collapse: collapse; margin-top: 10px; }';
        echo '.answers-table th, .answers-table td { border: 1px solid #999; padding: 4px; text-align: left; }';
        echo '.answers-table th { background-color: #e0e0e0; }';
        echo '.score-box { display: inline-block; padding: 2px 6px; border-radius: 4px; font-weight: bold; min-width: 20px; text-align: center; border: 1px solid #ccc; }';
        echo '.score-1 { background: #e8f5e9; color: #2e7d32; }';
        echo '.score-0 { background: #ffebee; color: #c62828; }';
        echo '.score-none { background: #f5f5f5; color: #999; }';
        echo '</style>';

        $isFirstTest = true;
        foreach ($selectedIds as $id) {
            if (!$isFirstTest) {
                echo '<br style="page-break-before:always;">';
            }
            $isFirstTest = false;

            $result = $storage->getResult($id);
            if ($result) {
                $testId = $result['test_id'] ?? '';
                $test = $storage->getTest($testId);
                
                $dateObj = new DateTime($result['created_at'] ?? '');
                $date = $dateObj->format('d.m.Y H:i');
                $score = $result['score'] ?? 0;
                $maxScore = $result['max_score'] ?? 0;
                $resultText = ($result['status'] ?? '') === 'passed' ? 'Успешно пройден' : 'Провален';
                $testName = $test['title'] ?? $result['test_title'] ?? 'Без названия';
                $answers = $result['answers'] ?? [];

                echo '<table class="main-table" style="width:100%;">';
                echo '<tr><th colspan="4" style="text-align:center; font-size:1.2em;">Результаты тестирования</th></tr>';
                echo '<tr><td><strong>Сотрудник:</strong></td><td>ID: ' . htmlspecialchars($result['employee_id'] ?? '0') . '</td><td><strong>Дата:</strong></td><td>' . htmlspecialchars($date) . '</td></tr>';
                echo '<tr><td><strong>Название теста:</strong></td><td>' . htmlspecialchars($testName) . '</td><td><strong>Результат:</strong></td><td>' . htmlspecialchars($resultText) . '</td></tr>';
                echo '<tr><td><strong>Набранные баллы:</strong></td><td>' . htmlspecialchars($score) . ' из ' . htmlspecialchars($maxScore) . '</td><td colspan="2"></td></tr>';
                
                if (!empty($answers)) {
                    echo '<tr><td colspan="4"><table class="answers-table">';
                    echo '<thead><tr><th style="width: 5%;">№</th><th style="width: 45%;">Вопрос</th><th style="width: 40%;">Ответ</th><th style="width: 10%; text-align:center;">Итог</th></tr></thead>';
                    echo '<tbody>';
                    
                    foreach ($answers as $idx => $ans) {
                        $scoreVal = intval($ans['score'] ?? 0);
                        $maxVal = intval($ans['max_score'] ?? 1);
                        $isCorrect = $ans['is_correct'] ?? false;
                        $scoreClass = $isCorrect ? 'score-1' : 'score-0';
                        $displayScore = $scoreVal . '/' . $maxVal;

                        echo '<tr>';
                        echo '<td style="text-align:center;">' . ($idx + 1) . '</td>';
                        echo '<td>' . htmlspecialchars($ans['question'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($ans['user_answer'] ?? '-') . '</td>';
                        echo '<td style="text-align:center;"><span class="score-box ' . $scoreClass . '">' . $displayScore . '</span></td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table></td></tr>';
                } else {
                    echo '<tr><td colspan="4">Детальные ответы не найдены.</td></tr>';
                }
                
                echo '</table>';
            }
        }

        echo '</body></html>';
        exit;
    }
}

// --- ОБРАБОТКА ОЧИСТКИ БАЗЫ ---
if (isset($_POST['clear_db'])) {
    $login = $_POST['admin_login'] ?? '';
    $pass = $_POST['admin_pass'] ?? '';
    
    if ($login === $adminLogin && $pass === $adminPass) {
        file_put_contents($config['data_dir'] . '/test_results.json', json_encode([]));
        $message = "<div style='color: green; font-weight: bold; margin-bottom: 10px;'>✅ База данных успешно очищена!</div>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = "<div style='color: red; font-weight: bold; margin-bottom: 10px;'>❌ Неверный логин или пароль!</div>";
    }
}

// --- ПАРАМЕТРЫ ФИЛЬТРАЦИИ ---
$filterTest = $_GET['test'] ?? '';
$filterEmployee = $_GET['employee'] ?? '';
$selectedResultId = $_GET['id'] ?? '';

// Получаем все результаты
$allResults = $storage->getAllResults();

// Применяем фильтры
$filteredResults = $allResults;
if (!empty($filterTest)) {
    $filteredResults = array_filter($filteredResults, function($r) use ($filterTest) {
        return ($r['test_id'] ?? '') === $filterTest;
    });
}
if (!empty($filterEmployee)) {
    $filteredResults = array_filter($filteredResults, function($r) use ($filterEmployee) {
        return ($r['employee_id'] ?? '') === $filterEmployee;
    });
}
$filteredResults = array_values($filteredResults);

// --- ДАННЫЕ ДЛЯ ДЕТАЛЕЙ ---
$selectedResult = null;
if (!empty($selectedResultId)) {
    $selectedResult = $storage->getResult($selectedResultId);
}

// --- СБОР УНИКАЛЬНЫХ ЗНАЧЕНИЙ ДЛЯ ФИЛЬТРОВ ---
$allTests = $storage->getAllTests();
$allEmployees = [];

foreach ($allResults as $result) {
    if (!empty($result['employee_id'])) {
        $allEmployees[$result['employee_id']] = true;
    }
}
$allEmployees = array_keys($allEmployees);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Результаты тестирования</title>
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
        
        /* Стили для изображений в результатах */
.question-with-image {
    display: flex;
    gap: 15px;
    align-items: flex-start;
}
.question-with-image .question-image {
    flex-shrink: 0;
}
.question-with-image .question-image img {
    max-width: 150px;
    max-height: 120px;
    border-radius: 6px;
    border: 1px solid #eee;
}
.question-with-image .question-text {
    flex: 1;
}

@media (max-width: 600px) {
    .question-with-image {
        flex-direction: column;
        align-items: center;
    }
    .question-with-image .question-image img {
        max-width: 100%;
        max-height: 200px;
    }
}

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9em;
        }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #219a52; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-outline { background: transparent; border: 2px solid rgba(255,255,255,0.5); color: white; }
        .btn-outline:hover { background: rgba(255,255,255,0.1); }
        .btn-sm { padding: 4px 10px; font-size: 0.8em; }
        
        .filters {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filters label { font-weight: bold; color: #555; font-size: 0.9em; }
        .filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            min-width: 200px;
            background: white;
        }
        .filters select:focus { border-color: #3498db; outline: none; }
        .filters .btn-reset {
            background: #dc3545;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
        }
        .filters .btn-reset:hover { background: #c0392b; }
        
        .split-view { 
            display: flex; 
            gap: 20px; 
            align-items: flex-start;
        }
        .list-panel { 
            flex: 1; 
            min-width: 0;
            max-width: 60%;
        }
        .detail-panel { 
            flex: 1; 
            min-width: 0;
            max-width: 40%;
        }
        
        .export-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            background: white;
            padding: 12px 15px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            flex-wrap: wrap;
            gap: 10px;
        }
        .export-controls h2 {
            margin: 0;
            font-size: 1.1em;
            color: #2c3e50;
        }
        
        .table-wrapper {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            max-height: calc(100vh - 250px);
            overflow-y: auto;
        }
        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
        }
        .table-wrapper th, 
        .table-wrapper td { 
            padding: 10px 14px; 
            text-align: left; 
            border-bottom: 1px solid #eee; 
        }
        .table-wrapper th {
            background: #2c3e50;
            color: white;
            font-weight: 600;
            font-size: 0.75em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .table-wrapper tr:hover { background-color: #f8f9fa; }
        .table-wrapper tr.selected { 
            background-color: #e3f2fd; 
            border-left: 4px solid #3498db;
        }
        .table-wrapper td .test-name {
            font-weight: 500;
            font-size: 0.95em;
        }
        .table-wrapper td .employee-id {
            font-size: 0.8em;
            color: #999;
        }
        
        .badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            display: inline-block;
        }
        .badge-pass { background: #d4edda; color: #155724; }
        .badge-fail { background: #f8d7da; color: #721c24; }
        
        .details-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-top: 4px solid #3498db;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
            position: sticky;
            top: 20px;
        }
        .details-container h2 {
            margin: 0 0 15px 0;
            font-size: 1.2em;
            color: #2c3e50;
        }
        .details-container .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 20px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .details-container .info-item strong {
            display: block;
            color: #777;
            font-size: 0.75em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 2px;
        }
        .details-container .info-item span {
            font-size: 1em;
            font-weight: 500;
        }
        .details-container .info-item .badge {
            font-size: 0.85em;
        }
        
        .detail-table-wrapper {
            overflow-x: auto;
            margin-top: 10px;
        }
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }
        .detail-table th {
            background: #34495e;
            color: white;
            padding: 8px 12px;
            font-size: 0.75em;
            text-transform: uppercase;
            text-align: left;
        }
        .detail-table td { 
            padding: 8px 12px; 
            vertical-align: top;
            border-bottom: 1px solid #f0f0f0;
        }
        .detail-table tr:last-child td { border-bottom: none; }
        .detail-table .score-box {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.85em;
            white-space: nowrap;
        }
        .score-correct { background: #d4edda; color: #155724; }
        .score-wrong { background: #f8d7da; color: #721c24; }
        .score-partial { background: #fff3cd; color: #856404; }
        
        .detail-empty {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        .detail-empty .icon { font-size: 3em; margin-bottom: 10px; }
        .detail-empty h3 { color: #555; margin: 0 0 5px 0; }
        .detail-empty p { margin: 0; }
        
        .admin-panel {
            margin-top: 30px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border: 1px solid #ddd;
            max-width: 400px;
        }
        .admin-panel h3 { 
            margin: 0 0 10px 0; 
            color: #dc3545;
            font-size: 1em;
        }
        .admin-panel .form-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .admin-panel input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            flex: 1;
            min-width: 120px;
        }
        .admin-panel button {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .admin-panel button:hover { background: #c0392b; }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        .empty-state .icon { font-size: 3em; margin-bottom: 10px; }
        .empty-state p { margin: 0; }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 1024px) {
            .split-view { flex-direction: column; }
            .list-panel { max-width: 100%; }
            .detail-panel { max-width: 100%; }
            .details-container { 
                max-height: none; 
                position: static;
                margin-top: 20px;
            }
        }
        @media (max-width: 768px) {
            .filters { flex-direction: column; align-items: stretch; }
            .filters select { width: 100%; min-width: auto; }
            .details-container .info-grid { grid-template-columns: 1fr; }
            .admin-panel .form-row { flex-direction: column; }
            .admin-panel input { width: 100%; }
        }
        @media (max-width: 600px) {
            body { padding: 10px; }
            .header { flex-direction: column; text-align: center; gap: 10px; }
            .header-actions { width: 100%; justify-content: center; }
            .export-controls { flex-direction: column; text-align: center; }
            .table-wrapper th, .table-wrapper td { 
                padding: 8px 10px; 
                font-size: 0.85em;
            }
            .table-wrapper td .test-name { font-size: 0.85em; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📊 Результаты тестирования</h1>
        <div class="header-actions">
            <a href="index.php" class="btn btn-outline">📝 К тестам</a>
            <a href="../testrus/NewTest/NEWtesting/results.php" class="btn btn-outline">📊 Старая система</a>
        </div>
    </div>
    
    <?php if (isset($message)): ?>
        <div class="alert-success"><?= $message ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <!-- ФИЛЬТРЫ -->
    <div class="filters">
        <form method="GET" style="display: flex; gap: 15px; align-items: center; width: 100%; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 150px;">
                <label>Тест:</label>
                <select name="test" onchange="this.form.submit()" style="width: 100%;">
                    <option value="">Все тесты</option>
                    <?php foreach ($allTests as $testId => $test): ?>
                        <option value="<?= htmlspecialchars($testId) ?>" <?= ($filterTest == $testId) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($test['title'] ?? 'Без названия') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="flex: 1; min-width: 150px;">
                <label>Сотрудник:</label>
                <select name="employee" onchange="this.form.submit()" style="width: 100%;">
                    <option value="">Все сотрудники</option>
                    <?php foreach ($allEmployees as $empId): ?>
                        <option value="<?= htmlspecialchars($empId) ?>" <?= ($filterEmployee == $empId) ? 'selected' : '' ?>>
                            ID: <?= htmlspecialchars($empId) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn-reset">✕ Сбросить</a>
            </div>
        </form>
    </div>

    <div class="split-view">
        <!-- ЛЕВАЯ КОЛОНКА: СПИСОК РЕЗУЛЬТАТОВ -->
        <div class="list-panel">
            <div class="export-controls">
                <h2>📋 Список результатов</h2>
                <form method="POST" id="exportForm" onsubmit="return checkSelection()">
                    <input type="hidden" name="export_excel" value="1">
                    <button type="submit" class="btn btn-success btn-sm">📥 Скачать Excel</button>
                </form>
            </div>
            
            <?php if (empty($filteredResults)): ?>
                <div class="empty-state">
                    <div class="icon">📭</div>
                    <p>Нет результатов</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 30px;">
                                    <input type="checkbox" id="selectAll" onclick="toggleAll(this)">
                                </th>
                                <th>Дата</th>
                                <th>Тест</th>
                                <th>Баллы</th>
                                <th>Результат</th>
                                <th style="width: 70px;">Действие</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredResults as $result): 
                                $resultId = $result['id'] ?? '';
                                $testId = $result['test_id'] ?? '';
                                $test = $storage->getTest($testId);
                                $testName = $test['title'] ?? $result['test_title'] ?? 'Без названия';
                                $dateObj = new DateTime($result['created_at'] ?? '');
                                $date = $dateObj->format('d.m H:i');
                                $score = $result['score'] ?? 0;
                                $maxScore = $result['max_score'] ?? 0;
                                $status = ($result['status'] ?? '') === 'passed' ? 'pass' : 'fail';
                                $statusText = $status === 'pass' ? '✅ Успешно' : '❌ Провален';
                                $isSelected = ($resultId == $selectedResultId) ? 'selected' : '';
                            ?>
                                <tr class="<?= $isSelected ?>">
                                    <td>
                                        <input type="checkbox" name="selected_ids[]" value="<?= htmlspecialchars($resultId) ?>" class="row-checkbox">
                                    </td>
                                    <td><?= $date ?></td>
                                    <td>
                                        <div class="test-name"><?= htmlspecialchars($testName) ?></div>
                                        <div class="employee-id">👤 ID: <?= htmlspecialchars($result['employee_id'] ?? '0') ?></div>
                                    </td>
                                    <td><strong><?= $score ?></strong> / <?= $maxScore ?></td>
                                    <td><span class="badge badge-<?= $status ?>"><?= $statusText ?></span></td>
                                    <td>
                                        <a href="?test=<?= urlencode($filterTest) ?>&employee=<?= urlencode($filterEmployee) ?>&id=<?= $resultId ?>" class="btn btn-primary btn-sm">
                                            📄
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ПРАВАЯ КОЛОНКА: ДЕТАЛИ -->
        <div class="detail-panel">
            <?php if ($selectedResult): 
                $testId = $selectedResult['test_id'] ?? '';
                $test = $storage->getTest($testId);
                $testName = $test['title'] ?? $selectedResult['test_title'] ?? 'Без названия';
                $answers = $selectedResult['answers'] ?? [];
                $dateObj = new DateTime($selectedResult['created_at'] ?? '');
                $date = $dateObj->format('d.m.Y H:i:s');
                $score = $selectedResult['score'] ?? 0;
                $maxScore = $selectedResult['max_score'] ?? 0;
                $status = ($selectedResult['status'] ?? '') === 'passed' ? 'pass' : 'fail';
                $statusText = $status === 'pass' ? 'Успешно пройден' : 'Провален';
                $badgeClass = $status === 'pass' ? 'badge-pass' : 'badge-fail';
                
                // Подсчет правильных ответов
                $correctCount = 0;
                foreach ($answers as $ans) {
                    if (!empty($ans['is_correct'])) $correctCount++;
                }
                $totalQuestions = count($answers);
            ?>
                <div class="details-container">
                    <h2>📝 Детали ответа</h2>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>Сотрудник</strong>
                            <span>ID: <?= htmlspecialchars($selectedResult['employee_id'] ?? '0') ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Тест</strong>
                            <span><?= htmlspecialchars($testName) ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Дата</strong>
                            <span><?= $date ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Результат</strong>
                            <span class="badge <?= $badgeClass ?>" style="font-size: 0.9em;"><?= $statusText ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Баллы</strong>
                            <span><?= $score ?> из <?= $maxScore ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Процент</strong>
                            <span><?= $selectedResult['score_percent'] ?? 0 ?>%</span>
                        </div>
                        <?php if ($totalQuestions > 0): ?>
                        <div class="info-item">
                            <strong>Правильно</strong>
                            <span style="color: #27ae60;">✅ <?= $correctCount ?> / <?= $totalQuestions ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Неправильно</strong>
                            <span style="color: #e74c3c;">❌ <?= $totalQuestions - $correctCount ?> / <?= $totalQuestions ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($answers)): ?>
                        <div class="detail-table-wrapper">
                            <table class="detail-table">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;">№</th>
                                        <th>Вопрос</th>
                                        <th>Ответ</th>
                                        <th style="width: 80px; text-align: center;">Итог</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($answers as $idx => $ans): 
                                        $isCorrect = $ans['is_correct'] ?? false;
                                        $scoreVal = intval($ans['score'] ?? 0);
                                        $maxVal = intval($ans['max_score'] ?? 1);
                                        $scoreClass = $isCorrect ? 'score-correct' : 'score-wrong';
                                        $displayScore = $scoreVal . '/' . $maxVal;
                                        $userAnswer = !empty($ans['user_answer']) && $ans['user_answer'] !== '(не указан)' 
                                            ? $ans['user_answer'] 
                                            : '<span style="color:#ccc;">—</span>';
                                    ?>
                                        <tr>
                                            <td style="text-align: center; color: #999;"><?= $idx + 1 ?></td>
                                            <td><?= htmlspecialchars($ans['question'] ?? '') ?></td>
                                            <td><?= $userAnswer ?></td>
                                            <td style="text-align: center;">
                                                <span class="score-box <?= $scoreClass ?>"><?= $displayScore ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="detail-empty">
                            <div class="icon">📭</div>
                            <p>Нет детальных ответов</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="details-container" style="display: flex; align-items: center; justify-content: center; border-top-color: #ddd;">
                    <div class="detail-empty">
                        <div class="icon">👆</div>
                        <h3>Выберите результат</h3>
                        <p>Нажмите кнопку <strong>📄</strong> в списке слева</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- АДМИН-ПАНЕЛЬ -->
    <div class="admin-panel">
        <h3>🔒 Административная панель</h3>
        <form method="POST" class="form-row">
            <input type="text" name="admin_login" placeholder="Логин" required>
            <input type="password" name="admin_pass" placeholder="Пароль" required>
            <button type="submit" name="clear_db" onclick="return confirm('Вы уверены, что хотите удалить ВСЕ результаты? Это действие нельзя отменить!')">
                🗑 Очистить все
            </button>
        </form>
    </div>
</div>

<script>
function toggleAll(master) {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => cb.checked = master.checked);
}

function checkSelection() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Пожалуйста, выберите хотя бы одну запись для экспорта.');
        return false;
    }
    
    const exportForm = document.getElementById('exportForm');
    const oldInputs = exportForm.querySelectorAll('input[name="selected_ids[]"]');
    oldInputs.forEach(input => input.remove());

    checkboxes.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_ids[]';
        input.value = cb.value;
        exportForm.appendChild(input);
    });

    return true;
}
</script>
</body>
</html>