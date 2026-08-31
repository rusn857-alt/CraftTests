<?php
// submit_test.php

require_once __DIR__ . '/lib/Utils.php';
require_once __DIR__ . '/lib/TestStorage.php';

$config = require __DIR__ . '/config.php';
$storage = new TestStorage($config['data_dir']);

$testId = $_POST['test_id'] ?? '';
$test = $storage->getTest($testId);

if (!$test) {
    header('Location: index.php');
    exit;
}

// Сбор ответов
$answers = [];
foreach ($_POST as $key => $value) {
    if (strpos($key, 'q_') === 0) {
        $answers[$key] = $value;
    }
}

// Расчет результатов
$resultData = $storage->calculateResults($test, $answers);

// Определение статуса
$passThreshold = 0.5; // 50% правильных ответов
$status = $resultData['percent'] >= ($passThreshold * 100) ? 'passed' : 'failed';

// Сохранение результата
$result = [
    'test_id' => $testId,
    'test_title' => $test['title'] ?? 'Без названия',
    'answers' => $resultData['details'],
    'score' => $resultData['score'],
    'max_score' => $resultData['max_score'],
    'status' => $status,
    'score_percent' => $resultData['percent'],
    'created_at' => date('Y-m-d H:i:s'),
    'employee_id' => $_POST['employee_id'] ?? '0'
];

$storage->saveResult($result);

// Отправка в основное приложение
if (!empty($config['api_url'])) {
    $answersText = '';
    foreach ($resultData['details'] as $idx => $ans) {
        $answersText .= "**" . $ans['question'] . "**\n```" . $ans['user_answer'] . "```\n\n";
    }
    
    $data = [
        'params' => [
            'vsego_voprosov' => $answersText,
            'voprosov_bals' => $test['title'] ?? 'Тест',
            'id_Employee' => $_POST['employee_id'] ?? '0',
            'rez_emplo' => $resultData['score'],
            'rez_exp' => $status === 'passed' ? 'Успешно пройден' : 'Провален',
            'exp_from' => json_encode(['answer' => ['data' => []]]),
            'max_exp' => $resultData['max_score'],
            'form_id' => $test['form_id'] ?? 'form_' . $testId,
            'source' => 'test_constructor'
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['api_url'],
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    curl_exec($ch);
    curl_close($ch);
}

header('Location: result.php?id=' . urlencode($result['id']));
exit;