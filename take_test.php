<?php
// take_test.php - с поддержкой страниц

require_once __DIR__ . '/lib/Utils.php';
require_once __DIR__ . '/lib/TestStorage.php';
require_once __DIR__ . '/lib/TestRenderer.php';

$config = require __DIR__ . '/config.php';
$storage = new TestStorage($config['data_dir']);

$testId = $_GET['id'] ?? '';
$test = $storage->getTest($testId);

if (!$test) {
    header('Location: index.php');
    exit;
}

if (($test['status'] ?? '') === 'archived') {
    header('Location: index.php?archived=1');
    exit;
}

$renderer = new TestRenderer($test);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Прохождение теста</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f0f2f5; 
            margin: 0; 
            padding: 20px; 
            color: #333; 
        }
        .container { max-width: 800px; margin: 0 auto; }
        
        .test-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .test-header {
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .test-header h2 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        .test-header p {
            color: #666;
            margin: 0;
        }
        
        /* Прогресс-бар */
        .progress-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
        }
        .progress-bar {
            flex: 1;
            height: 8px;
            background: #ecf0f1;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-bar .progress-fill {
            height: 100%;
            background: #3498db;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        .progress-text {
            font-size: 0.85em;
            color: #666;
            white-space: nowrap;
        }
        
        /* Страницы */
        .page-container {
            display: none;
        }
        .page-container.active {
            display: block;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }
        .page-header .page-title {
            margin: 0;
            color: #2c3e50;
            font-size: 1.2em;
        }
        .page-header .page-number {
            color: #888;
            font-size: 0.9em;
        }
        
        .question-block {
            background: #fafbfc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #eee;
        }
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .question-number {
            font-weight: 600;
            color: #3498db;
        }
        .required-badge {
            color: #e74c3c;
            font-weight: bold;
        }
        .points-badge {
            background: #e8f0fe;
            color: #3498db;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.8em;
        }
        .question-text {
            font-size: 1.05em;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .question-image {
            margin: 10px 0 15px 0;
            text-align: center;
        }
        .question-image img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            border: 1px solid #eee;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .options-container { display: flex; flex-direction: column; gap: 8px; }
        .option-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
            border: 1px solid transparent;
        }
        .option-item:hover { background: #f0f4f8; }
        .option-item input[type="radio"],
        .option-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .option-item span { flex: 1; }
        
        .text-input, .textarea-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .text-input:focus, .textarea-input:focus { border-color: #3498db; outline: none; }
        .textarea-input { resize: vertical; min-height: 80px; font-family: inherit; }
        
        .rating-container {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .rating-item {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
            border: 1px solid #eee;
        }
        .rating-item:hover { background: #f0f4f8; }
        .rating-item input[type="radio"] { display: none; }
        .rating-item input[type="radio"]:checked + span {
            background: #3498db;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .rating-item span { font-size: 1.1em; }
        
        .scale-container { margin: 10px 0; }
        .scale-labels {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .scale-labels > span { font-size: 0.9em; color: #666; }
        .scale-values {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        .scale-values label {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
            border: 1px solid #eee;
        }
        .scale-values label:hover { background: #f0f4f8; }
        .scale-values input[type="radio"] { display: none; }
        .scale-values input[type="radio"]:checked + span {
            background: #3498db;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        .page-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #ecf0f1;
            display: flex;
            gap: 15px;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 1em;
        }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #219a52; }
        .btn-outline { background: transparent; border: 2px solid #ddd; color: #555; }
        .btn-outline:hover { border-color: #999; background: #f8f9fa; }
        
        @media (max-width: 600px) {
            .test-container { padding: 15px; }
            .scale-values { justify-content: center; }
            .page-actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .page-header { flex-direction: column; text-align: center; gap: 5px; }
        }
    </style>
</head>
<body>
<div class="container">
    <?= $renderer->render() ?>
</div>
</body>
</html>