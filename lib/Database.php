<?php
/**
 * Класс для работы с базой данных
 * Использует PDO для безопасной работы с БД
 */

class Database
{
    /**
     * @var Database|null Экземпляр класса (Singleton)
     */
    private static $instance = null;
    
    /**
     * @var PDO Экземпляр PDO
     */
    private $pdo;
    
    /**
     * @var PDOStatement|null Последний подготовленный запрос
     */
    private $statement = null;
    
    /**
     * Приватный конструктор (Singleton)
     */
    private function __construct()
    {
        global $db;
        if ($db instanceof PDO) {
            $this->pdo = $db;
        } else {
            throw new Exception('Подключение к БД не установлено');
        }
    }
    
    /**
     * Получение экземпляра класса
     * 
     * @return Database
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Получение PDO объекта
     * 
     * @return PDO
     */
    public function getPdo()
    {
        return $this->pdo;
    }
    
    /**
     * Подготовка и выполнение запроса
     * 
     * @param string $sql SQL-запрос с плейсхолдерами
     * @param array $params Параметры для подстановки
     * @return PDOStatement
     */
    public function query($sql, $params = [])
    {
        $this->statement = $this->pdo->prepare($sql);
        $this->statement->execute($params);
        return $this->statement;
    }
    
    /**
     * Получение одной записи
     * 
     * @param string $sql SQL-запрос
     * @param array $params Параметры
     * @return array|null
     */
    public function fetchOne($sql, $params = [])
    {
        $result = $this->query($sql, $params);
        return $result->fetch();
    }
    
    /**
     * Получение всех записей
     * 
     * @param string $sql SQL-запрос
     * @param array $params Параметры
     * @return array
     */
    public function fetchAll($sql, $params = [])
    {
        $result = $this->query($sql, $params);
        return $result->fetchAll();
    }
    
    /**
     * Получение одной колонки из первой строки
     * 
     * @param string $sql SQL-запрос
     * @param array $params Параметры
     * @return mixed
     */
    public function fetchColumn($sql, $params = [])
    {
        $result = $this->query($sql, $params);
        return $result->fetchColumn();
    }
    
    /**
     * Получение ID последней вставленной записи
     * 
     * @return string
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Начало транзакции
     */
    public function beginTransaction()
    {
        $this->pdo->beginTransaction();
    }
    
    /**
     * Подтверждение транзакции
     */
    public function commit()
    {
        $this->pdo->commit();
    }
    
    /**
     * Откат транзакции
     */
    public function rollBack()
    {
        $this->pdo->rollBack();
    }
    
    /**
     * Экранирование строки
     * 
     * @param string $value
     * @return string
     */
    public function escape($value)
    {
        return $this->pdo->quote($value);
    }
}