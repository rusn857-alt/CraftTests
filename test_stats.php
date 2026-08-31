<?php
// test_stats.php

require_once __DIR__ . '/lib/Utils.php';
require_once __DIR__ . '/lib/TestStorage.php';

$config = require __DIR__ . '/config.php';
$storage = new TestStorage($config['data_dir']);

$testId = $_GET['id'] ?? '';
$test = $storage->getTest($testId);

if (!$test) {
    header('Location: index.php');
    exit;
}

$results = $storage->getAllResults($testId);
$stats = $storage->getTestStats($testId);

// Анализ вопросов
$questionStats = [];
foreach ($results as $result) {
    foreach ($result['answers'] ?? [] as $ans) {
        $qKey = md5($ans['question'] ?? '');
        if (!isset($questionStats[$qKey])) {
            $questionStats[$qKey] = [
                'question' => $ans['question'],
                'total' => 0,
                'answered' => 0,
                'not_answered' => 0
            ];
        }
        $questionStats[$qKey]['total']++;
        if (!empty($ans['answer']) && $ans['answer'] !== '(не указан)') {
            $questionStats[$qKey]['answered']++;
        } else {
            $questionStats[$qKey]['not_answered']++;
        }
    }
}

// Сортируем вопросы по сложности
usort($questionStats, function($a, $b) {
    $rateA = $a['total'] > 0 ? $a['not_answered'] / $a['total'] : 0;
    $rateB = $b['total'] > 0 ? $b['not_answered'] / $b['total'] : 0;
    return $rateB <=> $rateA;
});

$hardestQuestions = array_slice($questionStats, 0, 5);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика теста</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; margin: 0; padding: 20px; color: #333; }
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
        .btn-back { color: white; text-decoration: none; opacity: 0.8; }
        .btn-back:hover { opacity: 1; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-card .number {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-card .label {
            color: #888;
            font-size: 0.85em;
            margin-top: 4px;
        }
        .stat-card .number.green { color: #27ae60; }
        .stat-card .number.red { color: #e74c3c; }
        .stat-card .number.blue { color: #3498db; }
        
        .chart-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        .chart-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .chart-box h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 10px;
        }
        .chart-box canvas { max-height: 250px; max-width: 100%; }
        
        .questions-list {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .questions-list h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 10px;
        }
        .question-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .question-item .q-text {
            flex: 1;
            font-size: 0.95em;
            padding-right: 15px;
        }
        .question-item .q-stats {
            display: flex;
            gap: 15px;
            font-size: 0.9em;
            white-space: nowrap;
        }
        .question-item .q-stats .answered { color: #27ae60; }
        .question-item .q-stats .not-answered { color: #e74c3c; }
        .progress-bar {
            width: 100px;
            height: 8px;
            background: #ecf0f1;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-bar .fill { height: 100%; border-radius: 4px; transition: width 0.5s; }
        .progress-bar .fill.green { background: #27ae60; }
        .progress-bar .fill.red { background: #e74c3c; }
        
        .results-table {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow-x: auto;
        }
        .results-table table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95em;
        }
        .results-table th {
            background: #f8f9fa;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #dee2e6;
        }
        .results-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #eee;
        }
        .results-table tr:hover { background: #f8f9fa; }
        .badge-pass { background: #d4edda; color: #155724; padding: 2px 10px; border-radius: 12px; font-size: 0.8em; }
        .badge-fail { background: #f8d7da; color: #721c24; padding: 2px 10px; border-radius: 12px; font-size: 0.8em; }

        @media (max-width: 900px) {
            .chart-row { grid-template-columns: 1fr; }
            .question-item { flex-direction: column; align-items: flex-start; gap: 5px; }
            .question-item .q-stats { width: 100%; justify-content: space-between; }
        }
        @media (max-width: 600px) {
            .header { flex-direction: column; text-align: center; gap: 10px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📊 Статистика: <?= htmlspecialchars($test['title'] ?? 'Без названия') ?></h1>
        <div>
            <a href="take_test.php?id=<?= urlencode($testId) ?>" class="btn-back" style="margin-right:15px;">▶ Пройти</a>
            <a href="index.php" class="btn-back">← Назад</a>
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="number blue"><?= $stats['total'] ?></div>
            <div class="label">Всего попыток</div>
        </div>
        <div class="stat-card">
            <div class="number green"><?= $stats['passed'] ?></div>
            <div class="label">✅ Успешно</div>
        </div>
        <div class="stat-card">
            <div class="number red"><?= $stats['failed'] ?></div>
            <div class="label">❌ Провалено</div>
        </div>
        <div class="stat-card">
            <div class="number green"><?= $stats['pass_rate'] ?>%</div>
            <div class="label">Процент успеха</div>
        </div>
        <div class="stat-card">
            <div class="number blue"><?= $stats['avg_score'] ?></div>
            <div class="label">Средний балл</div>
        </div>
    </div>
    
    <div class="chart-row">
        <div class="chart-box">
            <h3>📊 Соотношение результатов</h3>
            <canvas id="resultChart"></canvas>
        </div>
        <div class="chart-box">
            <h3>📈 Распределение баллов</h3>
            <canvas id="scoreChart"></canvas>
        </div>
    </div>
    
    <div class="questions-list">
        <h3>❓ Самые сложные вопросы</h3>
        <?php if (!empty($hardestQuestions)): ?>
            <?php foreach ($hardestQuestions as $q): 
                $rate = $q['total'] > 0 ? round(($q['not_answered'] / $q['total']) * 100, 2) : 0;
                $answeredRate = 100 - $rate;
            ?>
                <div class="question-item">
                    <span class="q-text"><?= htmlspecialchars($q['question']) ?></span>
                    <div class="q-stats">
                        <span class="answered">✅ <?= $q['answered'] ?></span>
                        <span class="not-answered">❌ <?= $q['not_answered'] ?></span>
                        <div class="progress-bar">
                            <div class="fill <?= $answeredRate > 50 ? 'green' : 'red' ?>" 
                                 style="width:<?= $answeredRate ?>%;"></div>
                        </div>
                        <span style="font-size:0.85em; color:#888;"><?= $answeredRate ?>%</span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #999;">Нет данных для анализа</p>
        <?php endif; ?>
    </div>
    
    <div class="results-table">
        <h3 style="margin-top:0; color:#2c3e50;">📋 Все попытки</h3>
        <?php if (!empty($results)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Баллы</th>
                        <th>%</th>
                        <th>Результат</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                        <tr>
                            <td><?= Utils::formatDate($r['created_at'] ?? '') ?></td>
                            <td><?= $r['score'] ?? 0 ?> / <?= $r['max_score'] ?? 0 ?></td>
                            <td><?= $r['score_percent'] ?? 0 ?>%</td>
                            <td>
                                <span class="badge-<?= ($r['status'] ?? '') === 'passed' ? 'pass' : 'fail' ?>">
                                    <?= ($r['status'] ?? '') === 'passed' ? '✅ Успешно' : '❌ Провален' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #999;">Пока нет результатов</p>
        <?php endif; ?>
    </div>
</div>

<script>
// График соотношения результатов
const ctx1 = document.getElementById('resultChart').getContext('2d');
new Chart(ctx1, {
    type: 'doughnut',
    data: {
        labels: ['✅ Успешно', '❌ Провалено'],
        datasets: [{
            data: [<?= $stats['passed'] ?>, <?= $stats['failed'] ?>],
            backgroundColor: ['#27ae60', '#e74c3c'],
            borderWidth: 0
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// График распределения баллов
<?php
$scoreRanges = ['0-20%' => 0, '21-40%' => 0, '41-60%' => 0, '61-80%' => 0, '81-100%' => 0];
foreach ($results as $r) {
    $p = $r['score_percent'] ?? 0;
    if ($p <= 20) $scoreRanges['0-20%']++;
    elseif ($p <= 40) $scoreRanges['21-40%']++;
    elseif ($p <= 60) $scoreRanges['41-60%']++;
    elseif ($p <= 80) $scoreRanges['61-80%']++;
    else $scoreRanges['81-100%']++;
}
?>

const ctx2 = document.getElementById('scoreChart').getContext('2d');
new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($scoreRanges)) ?>,
        datasets: [{
            label: 'Количество попыток',
            data: <?= json_encode(array_values($scoreRanges)) ?>,
            backgroundColor: '#3498db',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>
</body>
</html>