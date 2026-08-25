<?php
/**
 * Класс для управления тестами
 */

class TestManager
{
    /**
     * @var Database Экземпляр Database
     */
    private $db;
    
    /**
     * Конструктор
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Создание нового теста
     * 
     * @param array $data Данные теста
     * @return int|bool ID созданного теста или false
     */
    public function createTest($data)
    {
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $status = $data['status'] ?? 'draft';
        $adminId = (int)($data['admin_id'] ?? 0);
        $settings = $data['settings'] ?? null;
        
        if (empty($title) || $adminId <= 0) {
            return false;
        }
        
        // Генерация slug
        $slug = generateSlug($title);
        $slug = $this->makeUniqueSlug($slug);
        
        // Подготовка настроек
        $settingsJson = $settings ? json_encode($settings) : null;
        
        try {
            $this->db->query(
                "INSERT INTO tests (slug, title, description, status, settings, created_by) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$slug, $title, $description, $status, $settingsJson, $adminId]
            );
            return (int)$this->db->lastInsertId();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Обновление теста
     * 
     * @param int $id ID теста
     * @param array $data Данные для обновления
     * @return bool
     */
    public function updateTest($id, $data)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }
        
        $fields = [];
        $params = [];
        
        if (isset($data['title'])) {
            $fields[] = "title = ?";
            $params[] = trim($data['title']);
        }
        
        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $params[] = trim($data['description']);
        }
        
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
        }
        
        if (isset($data['settings'])) {
            $fields[] = "settings = ?";
            $params[] = json_encode($data['settings']);
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        $sql = "UPDATE tests SET " . implode(", ", $fields) . " WHERE id = ?";
        
        try {
            $this->db->query($sql, $params);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Получение теста по ID
     * 
     * @param int $id ID теста
     * @return array|null
     */
    public function getTest($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }
        
        $test = $this->db->fetchOne(
            "SELECT t.*, a.login as created_by_name 
             FROM tests t
             LEFT JOIN administrators a ON t.created_by = a.id
             WHERE t.id = ?",
            [$id]
        );
        
        if ($test && $test['settings']) {
            $test['settings'] = json_decode($test['settings'], true);
        }
        
        return $test;
    }
    
    /**
     * Получение теста по slug
     * 
     * @param string $slug Слаг теста
     * @return array|null
     */
    public function getTestBySlug($slug)
    {
        $test = $this->db->fetchOne(
            "SELECT t.*, a.login as created_by_name 
             FROM tests t
             LEFT JOIN administrators a ON t.created_by = a.id
             WHERE t.slug = ? AND t.status = 'active'",
            [$slug]
        );
        
        if ($test && $test['settings']) {
            $test['settings'] = json_decode($test['settings'], true);
        }
        
        return $test;
    }
    
    /**
     * Получение всех тестов
     * 
     * @param string $status Фильтр по статусу
     * @return array
     */
    public function getTests($status = null)
    {
        $sql = "SELECT t.*, a.login as created_by_name,
                (SELECT COUNT(*) FROM questions WHERE test_id = t.id) as questions_count
                FROM tests t
                LEFT JOIN administrators a ON t.created_by = a.id";
        
        $params = [];
        if ($status) {
            $sql .= " WHERE t.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY t.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Удаление теста
     * 
     * @param int $id ID теста
     * @return bool
     */
    public function deleteTest($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }
        
        try {
            $this->db->query("DELETE FROM tests WHERE id = ?", [$id]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Изменение статуса теста
     * 
     * @param int $id ID теста
     * @param string $status Новый статус
     * @return bool
     */
    public function changeStatus($id, $status)
    {
        $id = (int)$id;
        $allowedStatuses = ['draft', 'active', 'archived'];
        
        if ($id <= 0 || !in_array($status, $allowedStatuses)) {
            return false;
        }
        
        try {
            $this->db->query(
                "UPDATE tests SET status = ? WHERE id = ?",
                [$status, $id]
            );
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Создание уникального слага
     * 
     * @param string $slug Исходный слаг
     * @return string Уникальный слаг
     */
    private function makeUniqueSlug($slug)
    {
        $originalSlug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Проверка существования слага
     * 
     * @param string $slug
     * @return bool
     */
    private function slugExists($slug)
    {
        $result = $this->db->fetchOne(
            "SELECT id FROM tests WHERE slug = ?",
            [$slug]
        );
        return $result !== false;
    }
    
    /**
     * Получение статистики по тесту
     * 
     * @param int $testId ID теста
     * @return array
     */
    public function getTestStats($testId)
    {
        $testId = (int)$testId;
        if ($testId <= 0) {
            return [];
        }
        
        $stats = [];
        
        // Количество прохождений
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as total, 
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    AVG(total_score) as avg_score
             FROM test_sessions 
             WHERE test_id = ?",
            [$testId]
        );
        $stats['sessions'] = $result;
        
        // Количество вопросов
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as total FROM questions WHERE test_id = ?",
            [$testId]
        );
        $stats['questions'] = $result['total'] ?? 0;
        
        return $stats;
    }
}