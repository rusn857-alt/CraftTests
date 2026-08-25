<?php
/**
 * Создание и редактирование теста
 */

require_once '../config.php';

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    redirect('/admin/login.php');
}

$testManager = new TestManager();
$db = Database::getInstance();

$testId = (int)($_GET['id'] ?? 0);
$test = null;
$isEdit = $testId > 0;

if ($isEdit) {
    $test = $testManager->getTest($testId);
    if (!$test) {
        $_SESSION['message'] = 'Тест не найден';
        $_SESSION['message_type'] = 'danger';
        redirect('/admin/tests.php');
    }
}

// Обработка формы
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    
    // Настройки
    $settings = [
        'time_limit' => (int)($_POST['time_limit'] ?? 0),
        'passing_score' => (int)($_POST['passing_score'] ?? 0),
        'show_results' => isset($_POST['show_results']),
        'randomize_questions' => isset($_POST['randomize_questions'])
    ];
    
    if (empty($title)) {
        $error = 'Введите название теста';
    } else {
        $data = [
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'settings' => $settings,
            'admin_id' => $_SESSION['admin_id']
        ];
        
        if ($isEdit) {
            if ($testManager->updateTest($testId, $data)) {
                $success = 'Тест успешно обновлен';
                $test = $testManager->getTest($testId);
            } else {
                $error = 'Ошибка обновления теста';
            }
        } else {
            $newId = $testManager->createTest($data);
            if ($newId) {
                $_SESSION['message'] = 'Тест успешно создан';
                $_SESSION['message_type'] = 'success';
                redirect('/admin/test_edit.php?id=' . $newId);
            } else {
                $error = 'Ошибка создания теста';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Редактирование' : 'Создание'; ?> теста</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="admin-wrapper">
        <header class="admin-header">
            <div class="container">
                <div class="header-content">
                    <h1><?php echo $isEdit ? '✏️ Редактирование теста' : '➕ Создание теста'; ?></h1>
                    <div class="user-info">
                        <span>👤 <?php echo htmlspecialchars($_SESSION['admin_login']); ?></span>
                        <a href="logout.php" class="btn btn-sm btn-danger">Выход</a>
                    </div>
                </div>
            </div>
        </header>
        
        <nav class="admin-nav">
            <div class="container">
                <ul>
                    <li><a href="index.php">📊 Главная</a></li>
                    <li><a href="tests.php">📝 Тесты</a></li>
                    <li><a href="results.php">📈 Результаты</a></li>
                </ul>
            </div>
        </nav>
        
        <main class="admin-main">
            <div class="container">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <div class="section">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="title">Название теста *</label>
                            <input type="text" id="title" name="title" class="form-control" 
                                   value="<?php echo htmlspecialchars($test['title'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Описание</label>
                            <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($test['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Статус</label>
                            <select id="status" name="status" class="form-control">
                                <option value="draft" <?php echo ($test['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Черновик</option>
                                <option value="active" <?php echo ($test['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Активный</option>
                                <option value="archived" <?php echo ($test['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Архивный</option>
                            </select>
                        </div>
                        
                        <hr style="margin: 30px 0; border: none; border-top: 2px solid #eee;">
                        
                        <h3 style="margin-bottom: 20px;">⚙️ Настройки теста</h3>
                        
                        <div class="form-group">
                            <label for="time_limit">Ограничение по времени (минуты)</label>
                            <input type="number" id="time_limit" name="time_limit" class="form-control" 
                                   value="<?php echo $test['settings']['time_limit'] ?? 0; ?>" min="0">
                            <span class="help-text">0 - без ограничения</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="passing_score">Проходной балл (%)</label>
                            <input type="number" id="passing_score" name="passing_score" class="form-control" 
                                   value="<?php echo $test['settings']['passing_score'] ?? 0; ?>" min="0" max="100">
                            <span class="help-text">Минимальный процент правильных ответов для прохождения</span>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="show_results" value="1" 
                                       <?php echo ($test['settings']['show_results'] ?? true) ? 'checked' : ''; ?>>
                                Показывать результаты после завершения
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="randomize_questions" value="1" 
                                       <?php echo ($test['settings']['randomize_questions'] ?? false) ? 'checked' : ''; ?>>
                                Перемешивать вопросы
                            </label>
                        </div>
                        
                        <div style="margin-top: 30px; display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $isEdit ? 'Сохранить изменения' : 'Создать тест'; ?>
                            </button>
                            <a href="tests.php" class="btn btn-secondary">Отмена</a>
                            
                            <?php if ($isEdit): ?>
                                <a href="questions.php?test_id=<?php echo $testId; ?>" class="btn btn-info" style="margin-left: auto;">
                                    📋 Управление вопросами
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <?php if ($isEdit && $test): ?>
                    <div class="section" style="margin-top: 30px;">
                        <h3>📊 Информация о тесте</h3>
                        <table class="table">
                            <tr>
                                <td style="width: 150px; font-weight: bold;">ID</td>
                                <td><?php echo $test['id']; ?></td>
                            </tr>
                            <tr>
                                <td style="font-weight: bold;">Слаг</td>
                                <td><code><?php echo htmlspecialchars($test['slug']); ?></code></td>
                            </tr>
                            <tr>
                                <td style="font-weight: bold;">Ссылка для прохождения</td>
                                <td>
                                    <a href="<?php echo SITE_URL; ?>public/take.php?slug=<?php echo htmlspecialchars($test['slug']); ?>" target="_blank">
                                        <?php echo SITE_URL; ?>public/take.php?slug=<?php echo htmlspecialchars($test['slug']); ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight: bold;">Создан</td>
                                <td><?php echo date('d.m.Y H:i', strtotime($test['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <td style="font-weight: bold;">Обновлен</td>
                                <td><?php echo date('d.m.Y H:i', strtotime($test['updated_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>