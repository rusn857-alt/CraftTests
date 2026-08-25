<?php
/**
 * Класс для управления аутентификацией администраторов
 */

class Auth
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
     * Попытка входа в систему
     * 
     * @param string $login Логин
     * @param string $password Пароль
     * @return bool
     */
    public function login($login, $password)
    {
        $login = trim($login);
        $password = trim($password);
        
        if (empty($login) || empty($password)) {
            return false;
        }
        
        // Получение данных администратора
        $admin = $this->db->fetchOne(
            "SELECT id, login, password_hash FROM administrators WHERE login = ?",
            [$login]
        );
        
        if (!$admin) {
            return false;
        }
        
        // Проверка пароля
        if (password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_login'] = $admin['login'];
            $_SESSION['admin_logged_in'] = true;
            return true;
        }
        
        return false;
    }
    
    /**
     * Выход из системы
     */
    public function logout()
    {
        $_SESSION = [];
        session_destroy();
    }
    
    /**
     * Проверка авторизации
     * 
     * @return bool
     */
    public function isAuthenticated()
    {
        return isset($_SESSION['admin_id']) && 
               isset($_SESSION['admin_login']) && 
               $_SESSION['admin_logged_in'] === true;
    }
    
    /**
     * Получение ID текущего администратора
     * 
     * @return int|null
     */
    public function getAdminId()
    {
        return $this->isAuthenticated() ? (int)$_SESSION['admin_id'] : null;
    }
    
    /**
     * Получение логина текущего администратора
     * 
     * @return string|null
     */
    public function getAdminLogin()
    {
        return $this->isAuthenticated() ? $_SESSION['admin_login'] : null;
    }
    
    /**
     * Проверка существования администратора
     * 
     * @param string $login
     * @return bool
     */
    public function adminExists($login)
    {
        $result = $this->db->fetchOne(
            "SELECT id FROM administrators WHERE login = ?",
            [$login]
        );
        return $result !== false;
    }
    
    /**
     * Создание нового администратора
     * 
     * @param string $login
     * @param string $password
     * @return int|bool
     */
    public function createAdmin($login, $password)
    {
        $login = trim($login);
        $password = trim($password);
        
        if (empty($login) || empty($password) || strlen($password) < 6) {
            return false;
        }
        
        if ($this->adminExists($login)) {
            return false;
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $this->db->query(
                "INSERT INTO administrators (login, password_hash) VALUES (?, ?)",
                [$login, $hash]
            );
            return (int)$this->db->lastInsertId();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Обновление пароля администратора
     * 
     * @param int $id
     * @param string $password
     * @return bool
     */
    public function updatePassword($id, $password)
    {
        $id = (int)$id;
        $password = trim($password);
        
        if ($id <= 0 || empty($password) || strlen($password) < 6) {
            return false;
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $this->db->query(
                "UPDATE administrators SET password_hash = ? WHERE id = ?",
                [$hash, $id]
            );
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}